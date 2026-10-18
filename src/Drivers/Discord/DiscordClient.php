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

use Closure;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Discord REST API (v10) wrapper for the bot: pagination, rate limits and error mapping.
 */
class DiscordClient
{
    const USER_AGENT = 'DiscordBot (https://github.com/Proudly-Snoring/seat-comlink, 1)';

    const AUDIT_REASON = 'seat-comlink synchronization';

    const MEMBERS_PAGE = 1000;

    const GUILDS_PAGE = 200;

    /**
     * @var string|null
     */
    private ?string $token;

    /**
     * @var \Closure  fn(float $seconds)
     */
    private Closure $sleeper;

    /**
     * @var int
     */
    private int $max_retries;

    /**
     * @param  string|null  $token  bot token
     * @param  \Closure|null  $sleeper  fn(float $seconds), used to wait on rate limits
     * @param  int  $max_retries  retries on HTTP 429
     */
    public function __construct(?string $token, ?Closure $sleeper = null, int $max_retries = 3)
    {
        $this->token = $token;
        $this->sleeper = $sleeper ?? function (float $seconds) {
            usleep((int) ceil($seconds * 1000000));
        };
        $this->max_retries = $max_retries;
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool
    {
        return ! empty($this->token);
    }

    /**
     * The bot user.
     *
     * @return array
     */
    public function me(): array
    {
        return $this->request('GET', '/users/@me');
    }

    /**
     * All the servers the bot is member of.
     *
     * @return array
     */
    public function guilds(): array
    {
        $guilds = [];
        $after = null;

        do {
            $page = $this->request('GET', '/users/@me/guilds', array_filter([
                'limit' => self::GUILDS_PAGE,
                'after' => $after,
            ]));

            $guilds = array_merge($guilds, $page);
            $after = empty($page) ? null : end($page)['id'];
        } while (count($page) >= self::GUILDS_PAGE);

        return $guilds;
    }

    /**
     * @param  string  $guild_id
     * @return array
     */
    public function guild(string $guild_id): array
    {
        return $this->request('GET', sprintf('/guilds/%s', $guild_id));
    }

    /**
     * Download a server icon from the CDN (public, no bot token).
     *
     * @param  string  $guild_id
     * @param  string  $hash
     * @return string  PNG binary
     *
     * @throws \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException
     */
    public function icon(string $guild_id, string $hash): string
    {
        $url = sprintf('%s/icons/%s/%s.png?size=64', config('comlink.config.discord.cdn'), $guild_id, $hash);

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(5)->get($url);
        } catch (ConnectionException $e) {
            throw new DiscordException($e->getMessage(), 0, null, $e);
        }

        if ($response->failed())
            throw new DiscordException(sprintf('HTTP %d', $response->status()), $response->status());

        return $response->body();
    }

    /**
     * @param  string  $guild_id
     * @return array
     */
    public function roles(string $guild_id): array
    {
        return $this->request('GET', sprintf('/guilds/%s/roles', $guild_id));
    }

    /**
     * @param  string  $guild_id
     * @param  string  $user_id
     * @return array
     */
    public function member(string $guild_id, string $user_id): array
    {
        return $this->request('GET', sprintf('/guilds/%s/members/%s', $guild_id, $user_id));
    }

    /**
     * Iterate over all the members of a server (requires the Server Members privileged intent).
     *
     * @param  string  $guild_id
     * @return \Generator<array>
     */
    public function members(string $guild_id): Generator
    {
        $after = '0';

        do {
            $page = $this->request('GET', sprintf('/guilds/%s/members', $guild_id), [
                'limit' => self::MEMBERS_PAGE,
                'after' => $after,
            ]);

            foreach ($page as $member) {
                $after = $member['user']['id'];

                yield $member;
            }
        } while (count($page) >= self::MEMBERS_PAGE);
    }

    /**
     * @param  string  $guild_id
     * @param  string  $user_id
     * @param  string  $role_id
     */
    public function addRole(string $guild_id, string $user_id, string $role_id): void
    {
        $this->request('PUT', sprintf('/guilds/%s/members/%s/roles/%s', $guild_id, $user_id, $role_id));
    }

    /**
     * @param  string  $guild_id
     * @param  string  $user_id
     * @param  string  $role_id
     */
    public function removeRole(string $guild_id, string $user_id, string $role_id): void
    {
        $this->request('DELETE', sprintf('/guilds/%s/members/%s/roles/%s', $guild_id, $user_id, $role_id));
    }

    /**
     * @param  string  $guild_id
     * @param  string  $user_id
     * @param  string  $nickname
     */
    public function setNickname(string $guild_id, string $user_id, string $nickname): void
    {
        $this->request('PATCH', sprintf('/guilds/%s/members/%s', $guild_id, $user_id), ['nick' => $nickname]);
    }

    /**
     * @param  string  $method
     * @param  string  $path
     * @param  array  $data  query string (GET) or JSON body
     * @return array|null  decoded JSON (null on 204)
     *
     * @throws \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException
     */
    public function request(string $method, string $path, array $data = []): ?array
    {
        if (! $this->isConfigured())
            throw new DiscordException('Bot token is not configured');

        $url = config('comlink.config.discord.api') . $path;
        $attempt = 0;

        while (true) {
            try {
                $response = $this->send($method, $url, $data);
            } catch (ConnectionException $e) {
                throw new DiscordException($e->getMessage(), 0, null, $e);
            }

            if ($response->status() === 429 && $attempt < $this->max_retries) {
                $attempt++;
                ($this->sleeper)($this->retryAfter($response));

                continue;
            }

            // proactively wait when the bucket is exhausted
            if ($response->header('X-RateLimit-Remaining') === '0' && is_numeric($response->header('X-RateLimit-Reset-After')))
                ($this->sleeper)((float) $response->header('X-RateLimit-Reset-After'));

            if ($response->failed()) {
                throw new DiscordException(
                    $response->json('message') ?? sprintf('HTTP %d', $response->status()),
                    $response->status(),
                    is_numeric($response->json('code')) ? (int) $response->json('code') : null);
            }

            return $response->status() === 204 ? null : $response->json();
        }
    }

    /**
     * @param  string  $method
     * @param  string  $url
     * @param  array  $data
     * @return \Illuminate\Http\Client\Response
     */
    private function send(string $method, string $url, array $data): Response
    {
        $request = Http::withHeaders([
            'Authorization' => 'Bot ' . $this->token,
            'User-Agent' => self::USER_AGENT,
            'X-Audit-Log-Reason' => self::AUDIT_REASON,
        ])->acceptJson()->timeout(30);

        return match ($method) {
            'GET' => $request->get($url, $data),
            'PATCH' => $request->patch($url, $data),
            default => $request->send($method, $url),
        };
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     * @return float  seconds
     */
    private function retryAfter(Response $response): float
    {
        $retry = $response->json('retry_after') ?? $response->header('Retry-After');

        return is_numeric($retry) ? max(0.0, (float) $retry) : 1.0;
    }
}
