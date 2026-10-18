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
use Seat\Web\Models\User;

/**
 * Link between a SeAT user and a discord account.
 *
 * @property int $id
 * @property int $user_id
 * @property string $discord_id
 * @property string $username
 */
class DiscordUser extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'comlink_discord_users';

    /**
     * @var array
     */
    protected $fillable = ['user_id', 'discord_id', 'username'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function members()
    {
        return $this->hasMany(DiscordMember::class, 'discord_id', 'discord_id');
    }
}
