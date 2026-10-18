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

namespace ProudlySnoring\Seat\Comlink\Services;

use Illuminate\Support\Facades\Log;
use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;

/**
 * Writes the plugin logs (Comlink > Logs). The origin user is the authenticated one, null in background jobs.
 */
class ComlinkLogger
{
    const DISCORD = 'discord';

    public static function debug(string $message, array $context = [], string $driver = self::DISCORD): ComlinkLog
    {
        return self::log('debug', $message, $context, $driver);
    }

    public static function info(string $message, array $context = [], string $driver = self::DISCORD): ComlinkLog
    {
        return self::log('info', $message, $context, $driver);
    }

    public static function warning(string $message, array $context = [], string $driver = self::DISCORD): ComlinkLog
    {
        return self::log('warning', $message, $context, $driver);
    }

    public static function error(string $message, array $context = [], string $driver = self::DISCORD): ComlinkLog
    {
        return self::log('error', $message, $context, $driver);
    }

    public static function log(string $level, string $message, array $context = [], string $driver = self::DISCORD): ComlinkLog
    {
        if ($level === 'error')
            Log::error(sprintf('[comlink:%s] %s', $driver, $message), $context);

        return ComlinkLog::create([
            'level' => $level,
            'driver' => $driver,
            'message' => $message,
            'user_id' => auth()->id(),
            'context' => empty($context) ? null : $context,
        ]);
    }

    /**
     * Delete logs older than the given amount of days.
     *
     * @param  int  $days
     * @return int  deleted rows
     */
    public static function prune(int $days): int
    {
        return ComlinkLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
