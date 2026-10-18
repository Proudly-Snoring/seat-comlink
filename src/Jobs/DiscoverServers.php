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

namespace ProudlySnoring\Seat\Comlink\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordClient;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\ServerRefresher;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;

/**
 * List the bot's servers (logging invitations / removals), then synchronize each one of them.
 */
class DiscoverServers implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var int
     */
    public $timeout = 300;

    /**
     * @var int
     */
    public $tries = 1;

    /**
     * @var int
     */
    public $uniqueFor = 600;

    /**
     * @var bool  run the servers synchronizations synchronously
     */
    public bool $now;

    public function __construct(bool $now = false)
    {
        $this->now = $now;
        $this->onQueue(config('comlink.config.queue'));
    }

    /**
     * @return array
     */
    public function tags(): array
    {
        return ['comlink', 'discord'];
    }

    /**
     * @param  \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordClient  $client
     * @param  \ProudlySnoring\Seat\Comlink\Drivers\Discord\ServerRefresher  $refresher
     */
    public function handle(DiscordClient $client, ServerRefresher $refresher): void
    {
        if (! $client->isConfigured())
            return;

        try {
            $servers = $refresher->discover();
        } catch (DiscordException $e) {
            ComlinkLogger::error(sprintf('Failed to list discord servers: %s', $e->reason()));

            return;
        }

        foreach ($servers as $server) {
            if ($this->now)
                SyncServer::dispatchSync($server->server_id);
            else
                SyncServer::dispatch($server->server_id);
        }
    }
}
