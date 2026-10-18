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

namespace ProudlySnoring\Seat\Comlink\Models;

use Seat\Services\Models\ExtensibleModel;
use Seat\Web\Models\Squads\Squad;

/**
 * Squad -> discord role mapping on a discord server.
 *
 * @property int $id
 * @property string $server_id
 * @property int $squad_id
 * @property string $role_id
 */
class DiscordMapping extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'comlink_discord_mappings';

    /**
     * @var array
     */
    protected $fillable = ['server_id', 'squad_id', 'role_id'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function server()
    {
        return $this->belongsTo(DiscordServer::class, 'server_id', 'server_id');
    }

    /**
     * Squads are protected by a global scope depending on the authenticated user: admins must see them all.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function squad()
    {
        return $this->belongsTo(Squad::class, 'squad_id', 'id')->withoutGlobalScopes();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(DiscordRole::class, 'role_id', 'role_id')->withTrashed();
    }

    /**
     * @return string
     */
    public function getSquadNameAttribute(): string
    {
        return $this->squad->name ?? ('#' . $this->squad_id);
    }

    /**
     * @return string
     */
    public function getRoleNameAttribute(): string
    {
        return $this->role->name ?? $this->role_id;
    }

    /**
     * @return string
     */
    public function getServerNameAttribute(): string
    {
        return $this->server->name ?? $this->server_id;
    }
}
