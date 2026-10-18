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

use Illuminate\Database\Eloquent\SoftDeletes;
use Seat\Services\Models\ExtensibleModel;

/**
 * A discord role, as seen at the last synchronization. Trashed roles have been deleted on discord ("orphan").
 *
 * @property int $id
 * @property string $server_id
 * @property string $role_id
 * @property string $name
 * @property int $position
 * @property int $color
 * @property bool $managed
 * @property int|null $member_count
 * @property \Carbon\Carbon|null $deleted_at
 */
class DiscordRole extends ExtensibleModel
{
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'comlink_discord_roles';

    /**
     * @var array
     */
    protected $fillable = ['server_id', 'role_id', 'name', 'position', 'color', 'managed'];

    /**
     * @var array
     */
    protected $casts = [
        'position' => 'integer',
        'color' => 'integer',
        'managed' => 'boolean',
        'member_count' => 'integer',
    ];

    /**
     * @return bool
     */
    public function isOrphan(): bool
    {
        return $this->trashed();
    }

    /**
     * Whether a bot can ever assign this role (not @everyone, not managed by an integration), whatever the hierarchy.
     *
     * @return bool
     */
    public function isAssignable(): bool
    {
        return $this->role_id !== $this->server_id && ! $this->managed;
    }

    /**
     * Return the reason why the bot cannot manage this role, or null if it can.
     *
     * @param  int  $bot_top_position  the position of the bot's highest role
     * @param  bool  $short  short version (roles list) instead of the actionable one (logs, validation)
     * @return string|null
     */
    public function unmanageableReason(int $bot_top_position, bool $short = false): ?string
    {
        if ($this->role_id === $this->server_id)
            return trans('comlink::comlink.role_reason_everyone');

        if ($this->managed)
            return trans('comlink::comlink.role_reason_integration');

        if ($this->position >= $bot_top_position)
            return trans($short ? 'comlink::comlink.role_reason_hierarchy_short' : 'comlink::comlink.role_reason_hierarchy');

        return null;
    }

    /**
     * @return string
     */
    public function getHexColorAttribute(): string
    {
        return $this->color ? sprintf('#%06x', $this->color) : '#99aab5';
    }
}
