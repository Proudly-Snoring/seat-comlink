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

use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;

/**
 * Discord OAuth2 helpers (identify scope only) and bot invitation URL.
 */
class DiscordOAuth
{
    /**
     * @return string
     */
    public static function callbackUrl(): string
    {
        return route('seatcomlink::discord.callback');
    }

    /**
     * @return string|null  null if the client ID is not set
     */
    public static function inviteUrl(): ?string
    {
        $client_id = ComlinkSettings::clientId();

        if (empty($client_id))
            return null;

        return config('comlink.config.discord.authorize') . '?' . http_build_query([
            'client_id' => $client_id,
            'scope' => 'bot',
            'permissions' => config('comlink.config.discord.permissions'),
        ]);
    }
}
