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

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Seat\Services\Models\Schedule;
use Seat\Services\Settings\Seat;

/**
 * Plugin settings, stored as SeAT global settings. Secrets are encrypted with the application key.
 */
class ComlinkSettings
{
    const PREFIX = 'proudly-snoring.comlink.';

    const SYNC_COMMAND = 'comlink:discord:sync';

    const PRUNE_COMMAND = 'comlink:logs:prune';

    /**
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = Seat::get(self::PREFIX . $key);

        return is_null($value) || $value === '' ? $default : $value;
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    public static function set(string $key, $value): void
    {
        Seat::set(self::PREFIX . $key, $value);
    }

    /**
     * @return string|null
     */
    public static function clientId(): ?string
    {
        $value = self::get('discord.client_id');

        // numeric values are json-encoded as numbers by SeAT settings
        return is_null($value) ? null : (string) $value;
    }

    /**
     * @return string|null
     */
    public static function clientSecret(): ?string
    {
        return self::secret('discord.client_secret');
    }

    /**
     * @return string|null
     */
    public static function botToken(): ?string
    {
        return self::secret('discord.bot_token');
    }

    /**
     * @param  string|null  $client_id
     * @param  string|null  $client_secret
     * @param  string|null  $bot_token
     */
    public static function setCredentials(?string $client_id, ?string $client_secret, ?string $bot_token): void
    {
        self::set('discord.client_id', $client_id ?? '');
        self::set('discord.client_secret', empty($client_secret) ? '' : Crypt::encryptString($client_secret));
        self::set('discord.bot_token', empty($bot_token) ? '' : Crypt::encryptString($bot_token));
    }

    /**
     * @return bool
     */
    public static function hasCredentials(): bool
    {
        return ! empty(self::clientId()) && ! empty(self::clientSecret()) && ! empty(self::botToken());
    }

    /**
     * @return int
     */
    public static function syncRate(): int
    {
        $rate = (int) self::get('sync_rate', config('comlink.config.defaults.sync_rate'));

        return in_array($rate, config('comlink.config.sync_rates'), true) ? $rate : config('comlink.config.defaults.sync_rate');
    }

    /**
     * Save the synchronization rate and apply it to the SeAT schedule.
     *
     * @param  int  $rate  minutes, one of the comlink.config.sync_rates values
     */
    public static function setSyncRate(int $rate): void
    {
        self::set('sync_rate', $rate);

        Schedule::updateOrCreate(['command' => self::SYNC_COMMAND], [
            'expression' => self::cronExpression($rate),
            'allow_overlap' => false,
            'allow_maintenance' => false,
        ]);
    }

    /**
     * @param  int  $rate  minutes
     * @return string
     */
    public static function cronExpression(int $rate): string
    {
        return $rate >= 60 ? '0 * * * *' : sprintf('*/%d * * * *', $rate);
    }

    /**
     * @return int
     */
    public static function logRetention(): int
    {
        return max(1, (int) self::get('log_retention', config('comlink.config.defaults.log_retention')));
    }

    /**
     * @param  int  $days
     */
    public static function setLogRetention(int $days): void
    {
        self::set('log_retention', $days);
    }

    /**
     * @param  string  $key
     * @return string|null
     */
    private static function secret(string $key): ?string
    {
        $value = self::get($key);

        if (empty($value))
            return null;

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // the application key changed: the secret must be entered again
            return null;
        }
    }
}
