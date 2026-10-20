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
 * @property int $id
 * @property string $level
 * @property string $driver
 * @property string $message
 * @property int|null $user_id
 * @property array|null $context
 * @property \Carbon\Carbon $created_at
 */
class ComlinkLog extends ExtensibleModel
{
    const LEVELS = ['debug', 'info', 'warning', 'error'];

    const UPDATED_AT = null;

    /**
     * @var string
     */
    protected $table = 'comlink_logs';

    /**
     * @var array
     */
    protected $fillable = ['level', 'driver', 'message', 'user_id', 'context'];

    /**
     * @var array
     */
    protected $casts = [
        'context' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return string
     */
    public function getLevelClassAttribute(): string
    {
        return self::levelClass($this->level);
    }

    /**
     * @param  string  $level
     * @return string bootstrap contextual class of the level
     */
    public static function levelClass(string $level): string
    {
        return [
            'debug' => 'secondary',
            'info' => 'info',
            'warning' => 'warning',
            'error' => 'danger',
        ][$level] ?? 'secondary';
    }
}
