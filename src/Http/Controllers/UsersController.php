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

namespace ProudlySnoring\Seat\Comlink\Http\Controllers;

use Illuminate\Http\Request;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use Seat\Web\Http\Controllers\Controller;

/**
 * Linked users and their discord state.
 *
 * @package ProudlySnoring\Seat\Comlink\Http\Controllers
 */
class UsersController extends Controller
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        $users = DiscordUser::with(['user', 'members.server'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('discord_id', $search)
                        ->orWhere('username', 'like', '%' . $search . '%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->orderBy('user_id')
            ->paginate(50)->withQueryString();

        return view('comlink::users.index', compact('users', 'search'));
    }
}
