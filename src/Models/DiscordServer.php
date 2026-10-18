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

/**
 * A discord server (guild) the bot has been invited to.
 *
 * @property int $id
 * @property string $server_id
 * @property string $name
 * @property string|null $icon  discord icon hash
 * @property string|null $icon_data  downloaded icon, as a data URI
 * @property string|null $owner_id
 * @property bool $enforce_nickname
 * @property bool $enforce_roles
 * @property string $nickname_format
 * @property int $bot_top_position
 * @property bool $present
 * @property \Carbon\Carbon|null $last_synced_at
 */
class DiscordServer extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'comlink_discord_servers';

    /**
     * @var array
     */
    protected $fillable = [
        'server_id', 'name', 'icon', 'owner_id', 'enforce_nickname', 'enforce_roles', 'nickname_format',
        'bot_top_position', 'present', 'last_synced_at',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'enforce_nickname' => 'boolean',
        'enforce_roles' => 'boolean',
        'present' => 'boolean',
        'bot_top_position' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function roles()
    {
        return $this->hasMany(DiscordRole::class, 'server_id', 'server_id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePresent($query)
    {
        return $query->where('present', true);
    }

}
