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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordOAuth;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use Seat\Web\Http\Controllers\Controller;

/**
 * Link / unlink the current user's discord account.
 *
 * @package ProudlySnoring\Seat\Comlink\Http\Controllers
 */
class IdentityController extends Controller
{
    const STATE_KEY = 'comlink.discord.oauth_state';

    const PENDING_KEY = 'comlink.discord.pending_link';

    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('comlink::identity.index', [
            'discord' => DiscordUser::where('user_id', auth()->id())->first(),
            'pending' => session(self::PENDING_KEY),
            'configured' => ComlinkSettings::hasCredentials(),
        ]);
    }

    /**
     * Start the OAuth flow.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function link(Request $request)
    {
        if (! ComlinkSettings::hasCredentials())
            return redirect()->route('seatcomlink::identity')->with('error', trans('comlink::comlink.not_configured'));

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away(DiscordOAuth::authorizeUrl($state));
    }

    /**
     * OAuth redirect target.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordOAuth  $oauth
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request, DiscordOAuth $oauth)
    {
        $state = $request->session()->pull(self::STATE_KEY);

        if ($request->filled('error'))
            return redirect()->route('seatcomlink::identity')->with('error', trans('comlink::comlink.link_cancelled'));

        if (empty($state) || ! hash_equals($state, (string) $request->query('state')) || ! $request->filled('code'))
            return redirect()->route('seatcomlink::identity')->with('error', trans('comlink::comlink.link_invalid_state'));

        try {
            $account = $oauth->identify((string) $request->query('code'));
        } catch (DiscordException $e) {
            ComlinkLogger::error(sprintf('Failed to identify discord account for user %d: %s', auth()->id(), $e->reason()));

            return redirect()->route('seatcomlink::identity')->with('error', trans('comlink::comlink.link_failed'));
        }

        $owner = DiscordUser::where('discord_id', $account['id'])->where('user_id', '<>', auth()->id())->first();

        // the account is linked to someone else: ask for confirmation first
        if ($owner) {
            $request->session()->put(self::PENDING_KEY, $account);

            return redirect()->route('seatcomlink::identity');
        }

        $this->applyLink($account['id'], $account['username']);

        return redirect()->route('seatcomlink::identity')->with('success', trans('comlink::comlink.linked'));
    }

    /**
     * Confirm a link which replaces the link of another user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function confirm(Request $request)
    {
        $pending = $request->session()->pull(self::PENDING_KEY);

        if (empty($pending))
            return redirect()->route('seatcomlink::identity');

        $this->applyLink($pending['id'], $pending['username']);

        return redirect()->route('seatcomlink::identity')->with('success', trans('comlink::comlink.linked'));
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancel(Request $request)
    {
        $request->session()->forget(self::PENDING_KEY);

        return redirect()->route('seatcomlink::identity');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unlink()
    {
        $link = DiscordUser::where('user_id', auth()->id())->first();

        if ($link) {
            $link->delete();

            ComlinkLogger::info(sprintf('Unlinked discord account %s with user %d', $link->discord_id, $link->user_id));
        }

        return redirect()->route('seatcomlink::identity')->with('success', trans('comlink::comlink.unlinked'));
    }

    /**
     * Link the current user to a discord account, removing any previous link of both sides.
     *
     * @param  string  $discord_id
     * @param  string  $username
     */
    private function applyLink(string $discord_id, string $username): void
    {
        $user_id = auth()->id();

        DB::transaction(function () use ($discord_id, $username, $user_id) {
            // previous owner of the discord account
            DiscordUser::where('discord_id', $discord_id)->where('user_id', '<>', $user_id)->get()
                ->each(function (DiscordUser $link) {
                    $link->delete();

                    ComlinkLogger::info(sprintf('Unlinked discord account %s from user %d', $link->discord_id, $link->user_id));
                });

            $current = DiscordUser::where('user_id', $user_id)->first();

            // same account: only refresh the username
            if ($current && $current->discord_id === $discord_id) {
                $current->update(['username' => $username]);

                return;
            }

            // previous account of the current user
            if ($current) {
                $current->delete();

                ComlinkLogger::info(sprintf('Unlinked discord account %s with user %d', $current->discord_id, $user_id));
            }

            DiscordUser::create(['user_id' => $user_id, 'discord_id' => $discord_id, 'username' => $username]);

            ComlinkLogger::info(sprintf('Linked discord account %s with user %d', $discord_id, $user_id));
        });
    }
}
