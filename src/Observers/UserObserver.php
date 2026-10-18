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

use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use Seat\Web\Models\User;

/**
 * Deletes the discord link of deleted SeAT users.
 */
class UserObserver
{
    /**
     * @param  \Seat\Web\Models\User  $user
     */
    public function deleted(User $user)
    {
        DiscordUser::where('user_id', $user->id)->get()->each(function (DiscordUser $link) {
            $link->delete();

            ComlinkLogger::info(sprintf('Unlinked discord account %s with user %d', $link->discord_id, $link->user_id));
        });
    }
}
