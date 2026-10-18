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

use Illuminate\Support\Facades\DB;
use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;

/**
 * Computes the managed roles of a server and the roles each user should have, from their squads.
 * Squad memberships are read from the pivot table: the Squad global scope depends on the authenticated user.
 */
class RoleResolver
{
    /**
     * @var string[]  managed (mapped, non orphan) role IDs
     */
    private array $managed;

    /**
     * @var array<int, string[]>  squad ID => role IDs
     */
    private array $squad_roles = [];

    /**
     * @var array<int, int[]>  user ID => squad IDs
     */
    private array $user_squads = [];

    /**
     * @param  string  $server_id
     * @param  string[]  $existing_roles  IDs of the roles currently existing on the server
     * @param  int[]  $user_ids  users to preload
     */
    public function __construct(string $server_id, array $existing_roles, array $user_ids)
    {
        $mappings = DiscordMapping::where('server_id', $server_id)
            ->whereIn('role_id', $existing_roles)
            ->get(['squad_id', 'role_id']);

        foreach ($mappings as $mapping)
            $this->squad_roles[(int) $mapping->squad_id][] = (string) $mapping->role_id;

        $this->managed = $mappings->pluck('role_id')->map('strval')->unique()->values()->all();

        if (empty($this->squad_roles) || empty($user_ids))
            return;

        DB::table('squad_member')
            ->whereIn('squad_id', array_keys($this->squad_roles))
            ->whereIn('user_id', $user_ids)
            ->get(['squad_id', 'user_id'])
            ->each(function ($row) {
                $this->user_squads[(int) $row->user_id][] = (int) $row->squad_id;
            });
    }

    /**
     * @return string[]
     */
    public function managed(): array
    {
        return $this->managed;
    }

    /**
     * Managed roles a user should have: the union of the roles mapped to their squads.
     *
     * @param  int|null  $user_id  null for unknown (not linked) members
     * @return string[]
     */
    public function expected(?int $user_id): array
    {
        if (is_null($user_id))
            return [];

        $roles = [];

        foreach ($this->user_squads[$user_id] ?? [] as $squad_id)
            $roles = array_merge($roles, $this->squad_roles[$squad_id] ?? []);

        return array_values(array_unique($roles));
    }
}
