<?php

/*
 * This file is part of seat-comlink.
 *
 * Copyright (C) 2026 Proudly-Snoring
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace ProudlySnoring\Seat\Comlink\Drivers\Discord;

use Illuminate\Support\Collection;
use ProudlySnoring\Seat\Comlink\Models\DiscordIssue;
use ProudlySnoring\Seat\Comlink\Models\DiscordMember;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use ProudlySnoring\Seat\Comlink\Services\IdentityResolver;

/**
 * Enforces the managed roles and nicknames of all the members of a discord server.
 */
class ServerSynchronizer
{
    private DiscordClient $client;

    private ServerRefresher $refresher;

    private IdentityResolver $identities;

    private NicknameRenderer $renderer;

    // per-server state
    private DiscordServer $server;

    private Collection $roles;

    private RoleResolver $resolver;

    private array $values = [];

    private array $touched_issues = [];

    private array $stats = [];

    // role ID => members holding it
    private array $role_counts = [];

    public function __construct(DiscordClient $client, ServerRefresher $refresher, IdentityResolver $identities, NicknameRenderer $renderer)
    {
        $this->client = $client;
        $this->refresher = $refresher;
        $this->identities = $identities;
        $this->renderer = $renderer;
    }

    /**
     * @param  \ProudlySnoring\Seat\Comlink\Models\DiscordServer  $server
     *
     * @throws \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException
     */
    public function sync(DiscordServer $server): void
    {
        $started_at = now();
        $this->server = $server;
        $this->touched_issues = [];
        $this->stats = ['roles_added' => 0, 'roles_removed' => 0, 'nicknames_updated' => 0, 'failures' => 0];
        $this->role_counts = [];
        $this->roles = $this->refresher->refresh($server);
        $server->refresh();

        // discord ID => SeAT user ID
        $links = DiscordUser::pluck('user_id', 'discord_id')->mapWithKeys(fn ($user_id, $discord_id) => [(string) $discord_id => (int) $user_id]);
        $this->resolver = new RoleResolver($server->server_id, $this->roles->keys()->map('strval')->all(), $links->values()->all());
        $this->values = $this->identities->forUsers($links->values()->all());

        $seen = [];

        foreach ($this->client->members($server->server_id) as $member) {
            foreach ($member['roles'] ?? [] as $role_id)
                $this->countRole((string) $role_id, 1);

            if ($member['user']['bot'] ?? false)
                continue;

            $discord_id = (string) $member['user']['id'];
            $user_id = $links->get($discord_id);
            $label = $user_id ? ($this->values[$user_id]['character'] ?? $member['user']['username']) : $member['user']['username'];

            if ($server->enforce_roles)
                $this->syncRoles($member, $user_id, $label);

            $nickname = $member['nick'] ?? null;

            if ($user_id && $server->enforce_nickname)
                $nickname = $this->syncNickname($member, $user_id, $label);

            if ($user_id) {
                DiscordMember::updateOrCreate(
                    ['server_id' => $server->server_id, 'discord_id' => $discord_id],
                    ['nickname' => $nickname]);
                $seen[] = $discord_id;
            }
        }

        // linked members who left the server (or have been unlinked)
        DiscordMember::where('server_id', $server->server_id)->whereNotIn('discord_id', $seen)->delete();

        // resolved (or gone) issues: a new failure will be logged again
        DiscordIssue::where('server_id', $server->server_id)->whereNotIn('id', $this->touched_issues)->delete();

        foreach ($this->roles as $role_id => $role) {
            $role->member_count = $this->role_counts[(string) $role_id] ?? 0;
            $role->save();
        }

        $ended_at = now();
        $server->update(['last_synced_at' => $ended_at]);

        ComlinkLogger::debug(sprintf('Synchronized server %s: %d updates (%d roles added, %d roles removed, %d nicknames updated, %d failures), started at %s, ended at %s',
            $server->name, $this->stats['roles_added'] + $this->stats['roles_removed'] + $this->stats['nicknames_updated'],
            $this->stats['roles_added'], $this->stats['roles_removed'], $this->stats['nicknames_updated'], $this->stats['failures'],
            $started_at->toDateTimeString(), $ended_at->toDateTimeString()), $this->stats);
    }

    /**
     * @param  string  $role_id
     * @param  int  $delta
     */
    private function countRole(string $role_id, int $delta): void
    {
        $this->role_counts[$role_id] = max(0, ($this->role_counts[$role_id] ?? 0) + $delta);
    }

    /**
     * @param  array  $member
     * @param  int|null  $user_id
     * @param  string  $label
     */
    private function syncRoles(array $member, ?int $user_id, string $label): void
    {
        $current = array_map('strval', $member['roles'] ?? []);
        $expected = $this->resolver->expected($user_id);
        $managed = $this->resolver->managed();

        foreach (array_diff($expected, $current) as $role_id)
            $this->applyRole(true, $member, (string) $role_id, $label);

        foreach (array_diff(array_intersect($current, $managed), $expected) as $role_id)
            $this->applyRole(false, $member, (string) $role_id, $label);
    }

    /**
     * @param  bool  $add
     * @param  array  $member
     * @param  string  $role_id
     * @param  string  $label
     */
    private function applyRole(bool $add, array $member, string $role_id, string $label): void
    {
        $role = $this->roles->get($role_id);
        $discord_id = (string) $member['user']['id'];
        $action = sprintf('%s role %s', $add ? 'add' : 'remove', $role->name);

        if ($reason = $role->unmanageableReason($this->server->bot_top_position)) {
            $this->warn($discord_id, DiscordIssue::KIND_ROLE, $role_id, $label, sprintf('%s: %s', $action, $reason));

            return;
        }

        try {
            if ($add)
                $this->client->addRole($this->server->server_id, $discord_id, $role_id);
            else
                $this->client->removeRole($this->server->server_id, $discord_id, $role_id);
        } catch (DiscordException $e) {
            $this->warn($discord_id, DiscordIssue::KIND_ROLE, $role_id, $label, sprintf('%s: %s', $action, $e->reason()));

            return;
        }

        $this->countRole($role_id, $add ? 1 : -1);
        $this->stats[$add ? 'roles_added' : 'roles_removed']++;

        ComlinkLogger::info(sprintf('%s role %s %s user %s (discord id: %s) on server %s',
            $add ? 'Added' : 'Removed', $role->name, $add ? 'to' : 'from', $label, $discord_id, $this->server->name));
    }

    /**
     * @param  array  $member
     * @param  int  $user_id
     * @param  string  $label
     * @return string|null  the member nickname after enforcement
     */
    private function syncNickname(array $member, int $user_id, string $label): ?string
    {
        $discord_id = (string) $member['user']['id'];
        $current = $member['nick'] ?? null;
        $target = $this->renderer->render($this->server->nickname_format, $this->values[$user_id] ?? IdentityResolver::empty());

        if (! is_null($current) && NicknameRenderer::normalize($current) === $target)
            return $current;

        if ($discord_id === $this->server->owner_id) {
            $this->warn($discord_id, DiscordIssue::KIND_NICKNAME, '', $label, trans('comlink::comlink.reason_owner'));

            return $current;
        }

        $top = collect($member['roles'] ?? [])->map(fn ($id) => $this->roles->get((string) $id)->position ?? 0)->max() ?? 0;

        if ($top >= $this->server->bot_top_position) {
            $this->warn($discord_id, DiscordIssue::KIND_NICKNAME, '', $label, trans('comlink::comlink.reason_above_bot'));

            return $current;
        }

        try {
            $this->client->setNickname($this->server->server_id, $discord_id, $target);
        } catch (DiscordException $e) {
            $this->warn($discord_id, DiscordIssue::KIND_NICKNAME, '', $label, $e->reason());

            return $current;
        }

        $this->stats['nicknames_updated']++;

        ComlinkLogger::info(sprintf('Updated nickname of user %s (discord id: %s) on server %s to %s',
            $label, $discord_id, $this->server->name, $target));

        return $target;
    }

    /**
     * Log an enforcement failure, only once until it is resolved.
     *
     * @param  string  $discord_id
     * @param  string  $kind
     * @param  string  $target
     * @param  string  $label
     * @param  string  $reason
     */
    private function warn(string $discord_id, string $kind, string $target, string $label, string $reason): void
    {
        $issue = DiscordIssue::firstOrNew([
            'server_id' => $this->server->server_id,
            'discord_id' => $discord_id,
            'kind' => $kind,
            'target' => $target,
        ]);

        $hash = hash('sha256', $reason);
        $this->stats['failures']++;

        if (! $issue->exists || $issue->reason_hash !== $hash) {
            ComlinkLogger::warning(sprintf('Failed to update %s for user %s (discord id: %s) on server %s: %s',
                $kind, $label, $discord_id, $this->server->name, $reason));
        }

        $issue->reason_hash = $hash;
        $issue->save();

        $this->touched_issues[] = $issue->id;
    }
}
