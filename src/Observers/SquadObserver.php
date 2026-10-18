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

namespace ProudlySnoring\Seat\Comlink\Observers;

use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use Seat\Web\Models\Squads\Squad;

/**
 * Deletes the mappings of deleted squads.
 */
class SquadObserver
{
    /**
     * @param  \Seat\Web\Models\Squads\Squad  $squad
     */
    public function deleted(Squad $squad)
    {
        DiscordMapping::with('server', 'role')->where('squad_id', $squad->id)->get()
            ->each(function (DiscordMapping $mapping) use ($squad) {
                $mapping->delete();

                ComlinkLogger::info(sprintf('Removed mapping %s -> %s for discord server %s',
                    $squad->name, $mapping->role_name, $mapping->server_name));
            });
    }
}
