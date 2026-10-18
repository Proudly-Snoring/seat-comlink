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

namespace ProudlySnoring\Seat\Comlink\Commands;

use Illuminate\Console\Command;
use ProudlySnoring\Seat\Comlink\Jobs\DiscoverServers;
use ProudlySnoring\Seat\Comlink\Jobs\SyncServer;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;

class DiscordSync extends Command
{
    /**
     * @var string
     */
    protected $signature = 'comlink:discord:sync
                            {--server= : Discord ID of a single server to synchronize}
                            {--now : Run synchronously instead of queueing (debug)}';

    /**
     * @var string
     */
    protected $description = 'Synchronize the managed roles and nicknames on the discord servers';

    /**
     * @return int
     */
    public function handle()
    {
        if (empty(ComlinkSettings::botToken())) {
            $this->warn('The discord bot token is not configured (Comlink > Settings): nothing to do.');

            return self::SUCCESS;
        }

        $now = (bool) $this->option('now');

        if ($server_id = $this->option('server')) {
            $server = DiscordServer::present()->where('server_id', $server_id)->first();

            if (! $server) {
                $this->error(sprintf('Unknown discord server %s (the bot may not be present on it).', $server_id));

                return self::FAILURE;
            }

            $now ? SyncServer::dispatchSync($server->server_id) : SyncServer::dispatch($server->server_id);
            $this->info(sprintf('Synchronization of %s %s.', $server->name, $now ? 'done' : 'queued'));

            return self::SUCCESS;
        }

        $now ? DiscoverServers::dispatchSync(true) : DiscoverServers::dispatch();
        $this->info(sprintf('Synchronization of all servers %s.', $now ? 'done' : 'queued'));

        return self::SUCCESS;
    }
}
