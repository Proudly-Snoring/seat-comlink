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
use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;
use ProudlySnoring\Seat\Comlink\Models\DiscordMember;
use ProudlySnoring\Seat\Comlink\Models\DiscordRole;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;

/**
 * Refreshes the servers list and the roles cache from discord.
 */
class ServerRefresher
{
    /**
     * @var \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordClient
     */
    private DiscordClient $client;

    /**
     * @var string|null
     */
    private ?string $bot_id = null;

    public function __construct(DiscordClient $client)
    {
        $this->client = $client;
    }

    /**
     * Update the servers list, logging invitations and removals.
     *
     * @return \Illuminate\Support\Collection<\ProudlySnoring\Seat\Comlink\Models\DiscordServer>  present servers
     *
     * @throws \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException
     */
    public function discover(): Collection
    {
        $guilds = collect($this->client->guilds())->keyBy('id');

        foreach ($guilds as $id => $guild) {
            $server = DiscordServer::firstOrNew(['server_id' => (string) $id]);
            $invited = ! $server->exists || ! $server->present;

            $server->fill([
                'name' => $guild['name'],
                'present' => true,
            ]);

            if (! $server->exists)
                $server->nickname_format = config('comlink.config.defaults.nickname_format');

            $this->updateIcon($server, $guild['icon'] ?? null);
            $server->save();

            if ($invited)
                ComlinkLogger::info(sprintf('Invited to server %s', $server->name));
        }

        DiscordServer::present()->whereNotIn('server_id', $guilds->keys()->map('strval')->all())->get()
            ->each(function (DiscordServer $server) {
                $server->update(['present' => false]);

                // never synchronized again until re-invited: rebuilt by the next sync
                DiscordMember::where('server_id', $server->server_id)->delete();
                DiscordIssue::where('server_id', $server->server_id)->delete();

                ComlinkLogger::info(sprintf('Removed from server %s', $server->name));
            });

        return DiscordServer::present()->get();
    }

    /**
     * Refresh the server details, its roles cache and the bot's position. Deleted mapped roles become orphan.
     *
     * @param  \ProudlySnoring\Seat\Comlink\Models\DiscordServer  $server
     * @return \Illuminate\Support\Collection<\ProudlySnoring\Seat\Comlink\Models\DiscordRole>  current roles, by role ID
     *
     * @throws \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException
     */
    public function refresh(DiscordServer $server): Collection
    {
        $guild = $this->client->guild($server->server_id);
        $roles = collect($this->client->roles($server->server_id))->keyBy('id');

        foreach ($roles as $id => $role) {
            $model = DiscordRole::withTrashed()->firstOrNew(['role_id' => (string) $id]);
            $model->fill([
                'server_id' => $server->server_id,
                'name' => $role['name'],
                'position' => (int) $role['position'],
                'color' => (int) ($role['color'] ?? 0),
                'managed' => (bool) ($role['managed'] ?? false),
            ]);
            $model->deleted_at = null;
            $model->save();
        }

        // roles deleted on discord
        DiscordRole::where('server_id', $server->server_id)->whereNotIn('role_id', $roles->keys()->map('strval')->all())->get()
            ->each(function (DiscordRole $role) use ($server) {
                $role->delete();

                if (DiscordMapping::where('role_id', $role->role_id)->exists())
                    ComlinkLogger::error(sprintf('Role %s deleted from server %s, its mappings are now orphan', $role->name, $server->name));
            });

        $bot = $this->client->member($server->server_id, $this->botId());
        $bot_top = collect($bot['roles'] ?? [])->map(fn ($id) => (int) ($roles[$id]['position'] ?? 0))->max() ?? 0;

        // icon handled by discover() only: downloaded (or retried) once per synchronization
        $server->fill([
            'name' => $guild['name'] ?? $server->name,
            'owner_id' => isset($guild['owner_id']) ? (string) $guild['owner_id'] : null,
            'bot_top_position' => $bot_top,
        ])->save();

        return DiscordRole::where('server_id', $server->server_id)->get()->keyBy('role_id');
    }

    /**
     * Download the server icon when it changed (or a previous download failed). Failures never break the sync.
     *
     * @param  \ProudlySnoring\Seat\Comlink\Models\DiscordServer  $server
     * @param  string|null  $hash
     */
    private function updateIcon(DiscordServer $server, ?string $hash): void
    {
        if ($hash === $server->icon && (is_null($hash) || ! empty($server->icon_data)))
            return;

        $server->icon = $hash;
        $server->icon_data = null;

        if (is_null($hash))
            return;

        try {
            $server->icon_data = 'data:image/png;base64,' . base64_encode($this->client->icon($server->server_id, $hash));
        } catch (DiscordException $e) {
            ComlinkLogger::warning(sprintf('Unable to download the icon of server %s: %s', $server->name, $e->getMessage()));
        }
    }

    /**
     * @return string
     */
    public function botId(): string
    {
        return $this->bot_id ??= (string) $this->client->me()['id'];
    }
}
