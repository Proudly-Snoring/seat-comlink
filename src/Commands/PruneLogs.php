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
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;

class PruneLogs extends Command
{
    /**
     * @var string
     */
    protected $signature = 'comlink:logs:prune {--days= : Override the retention setting (in days)}';

    /**
     * @var string
     */
    protected $description = 'Delete the comlink logs older than the retention setting';

    /**
     * @return int
     */
    public function handle()
    {
        $days = $this->option('days');

        if (! is_null($days) && (! ctype_digit((string) $days) || (int) $days < 1)) {
            $this->error('--days must be a positive integer');

            return self::FAILURE;
        }

        $days = is_null($days) ? ComlinkSettings::logRetention() : (int) $days;
        $count = ComlinkLogger::prune($days);

        $this->info(sprintf('Deleted %d log(s) older than %d day(s)', $count, $days));

        return self::SUCCESS;
    }
}
