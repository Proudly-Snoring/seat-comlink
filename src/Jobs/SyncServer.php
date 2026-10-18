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
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\ServerSynchronizer;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;

/**
 * Synchronize the roles and nicknames of a discord server. Never runs twice at the same time for a server.
 */
class SyncServer implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var int
     */
    public $timeout = 900;

    /**
     * @var int
     */
    public $tries = 1;

    /**
     * @var int  release the unique lock if the job never ends (seconds)
     */
    public $uniqueFor = 3600;

    /**
     * @var string
     */
    public string $server_id;

    public function __construct(string $server_id)
    {
        $this->server_id = $server_id;
        $this->onQueue(config('comlink.config.queue'));
    }

    /**
     * @return string
     */
    public function uniqueId(): string
    {
        return $this->server_id;
    }

    /**
     * @return array
     */
    public function tags(): array
    {
        return ['comlink', 'discord', 'server:' . $this->server_id];
    }

    /**
     * @param  \ProudlySnoring\Seat\Comlink\Drivers\Discord\ServerSynchronizer  $synchronizer
     */
    public function handle(ServerSynchronizer $synchronizer): void
    {
        $server = DiscordServer::present()->where('server_id', $this->server_id)->first();

        if (! $server)
            return;

        try {
            $synchronizer->sync($server);
        } catch (DiscordException $e) {
            $hint = $e->discord_code === DiscordException::MISSING_ACCESS ? ' - check the bot has the Server Members Intent' : '';

            ComlinkLogger::error(sprintf('Failed to synchronize server %s: %s%s', $server->name, $e->reason(), $hint));
        }
    }
}
