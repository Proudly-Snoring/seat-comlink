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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;

/**
 * Import the users' discord links from warlof/seat-discord-connector.
 */
class DiscordImportWarlof extends Command
{
    const TABLE = 'seat_connector_users';

    /**
     * @var string
     */
    protected $signature = 'comlink:discord:import-warlof {--dry-run : Only report what would be imported}';

    /**
     * @var string
     */
    protected $description = 'Import the users\' discord links from seat-discord-connector (conflicts are skipped)';

    /**
     * @return int
     */
    public function handle()
    {
        if (! Schema::hasTable(self::TABLE)) {
            $this->warn(sprintf('Table %s not found: seat-discord-connector does not seem to be installed.', self::TABLE));

            return self::SUCCESS;
        }

        $dry_run = (bool) $this->option('dry-run');

        $rows = DB::table(self::TABLE)->where('connector_type', 'discord')
            ->orderBy('id')->get(['user_id', 'connector_id', 'connector_name']);

        $known_users = DB::table('users')->whereIn('id', $rows->pluck('user_id')->unique())->pluck('id')->map('intval')->flip();

        // current links, updated as we go (also in dry-run, to detect conflicts inside the source)
        $by_user = DiscordUser::pluck('discord_id', 'user_id')->mapWithKeys(fn ($d, $u) => [(int) $u => (string) $d])->all();
        $by_discord = array_flip($by_user);

        $counts = ['imported' => 0, 'already linked' => 0, 'conflicts' => 0, 'unknown users' => 0];
        $conflicts = [];

        foreach ($rows as $row) {
            $user_id = (int) $row->user_id;
            $discord_id = (string) $row->connector_id;

            if (! $known_users->has($user_id)) {
                $counts['unknown users']++;
                $conflicts[] = [$user_id, $discord_id, $row->connector_name, 'unknown SeAT user'];

                continue;
            }

            if (($by_user[$user_id] ?? null) === $discord_id) {
                $counts['already linked']++;

                continue;
            }

            if (isset($by_user[$user_id]) || isset($by_discord[$discord_id])) {
                $counts['conflicts']++;
                $conflicts[] = [$user_id, $discord_id, $row->connector_name, isset($by_user[$user_id])
                    ? sprintf('user already linked to discord account %s', $by_user[$user_id])
                    : sprintf('discord account already linked to user %d', $by_discord[$discord_id])];

                continue;
            }

            $by_user[$user_id] = $discord_id;
            $by_discord[$discord_id] = $user_id;
            $counts['imported']++;

            if ($dry_run)
                continue;

            DiscordUser::create([
                'user_id' => $user_id,
                'discord_id' => $discord_id,
                'username' => (string) $row->connector_name,
            ]);

            ComlinkLogger::info(sprintf('Linked discord account %s with user %d', $discord_id, $user_id), ['source' => 'seat-discord-connector']);
        }

        if (! empty($conflicts)) {
            $this->warn('Skipped links:');
            $this->table(['SeAT user', 'Discord ID', 'Discord name', 'Reason'], $conflicts);
        }

        $this->table(['Result', 'Count'], collect($counts)->map(fn ($count, $label) => [$label, $count])->values()->all());

        if ($dry_run)
            $this->info('Dry run: nothing has been imported.');

        return self::SUCCESS;
    }
}
