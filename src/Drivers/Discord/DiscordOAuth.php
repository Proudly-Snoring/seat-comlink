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

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;

/**
 * Discord OAuth2 flow (identify scope only) and bot invitation URL.
 */
class DiscordOAuth
{
    /**
     * @param  string  $state  anti-CSRF value, checked on callback
     * @return string
     */
    public static function authorizeUrl(string $state): string
    {
        return config('comlink.config.discord.authorize') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => ComlinkSettings::clientId(),
            'scope' => 'identify',
            'redirect_uri' => self::callbackUrl(),
            'state' => $state,
        ]);
    }

    /**
     * Exchange the authorization code and return the identified discord account.
     *
     * @param  string  $code
     * @return array{id: string, username: string}
     *
     * @throws \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException
     */
    public function identify(string $code): array
    {
        $api = config('comlink.config.discord.api');

        try {
            $token = Http::asForm()->acceptJson()->timeout(30)->post($api . '/oauth2/token', [
                'client_id' => ComlinkSettings::clientId(),
                'client_secret' => ComlinkSettings::clientSecret(),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => self::callbackUrl(),
            ]);

            if ($token->failed() || empty($token->json('access_token')))
                throw new DiscordException($token->json('error_description') ?? $token->json('error') ?? 'OAuth token exchange failed', $token->status());

            $user = Http::withToken($token->json('access_token'))->acceptJson()->timeout(30)->get($api . '/users/@me');

            if ($user->failed() || empty($user->json('id')))
                throw new DiscordException($user->json('message') ?? 'Cannot retrieve the discord user', $user->status());
        } catch (ConnectionException $e) {
            throw new DiscordException($e->getMessage(), 0, null, $e);
        }

        return [
            'id' => (string) $user->json('id'),
            'username' => self::username($user->json()),
        ];
    }

    /**
     * @param  array  $user  discord user object
     * @return string  username, with the legacy discriminator if any
     */
    public static function username(array $user): string
    {
        $discriminator = $user['discriminator'] ?? '0';

        return in_array($discriminator, ['0', '0000', ''], true)
            ? (string) $user['username']
            : sprintf('%s#%s', $user['username'], $discriminator);
    }

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
