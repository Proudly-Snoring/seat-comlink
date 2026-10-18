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

use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordOAuth;
use ProudlySnoring\Seat\Comlink\Http\Validation\SettingsRequest;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use Seat\Web\Http\Controllers\Controller;

/**
 * Class SettingsController.
 *
 * @package ProudlySnoring\Seat\Comlink\Http\Controllers
 */
class SettingsController extends Controller
{
    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('comlink::settings.index', [
            'client_id' => ComlinkSettings::clientId(),
            'client_secret' => ComlinkSettings::clientSecret(),
            'bot_token' => ComlinkSettings::botToken(),
            'has_credentials' => ComlinkSettings::hasCredentials(),
            'callback_url' => DiscordOAuth::callbackUrl(),
            'invite_url' => DiscordOAuth::inviteUrl(),
            'portal_url' => config('comlink.config.discord.portal'),
            'sync_rate' => ComlinkSettings::syncRate(),
            'sync_rates' => config('comlink.config.sync_rates'),
            'log_retention' => ComlinkSettings::logRetention(),
        ]);
    }

    /**
     * @param  \ProudlySnoring\Seat\Comlink\Http\Validation\SettingsRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(SettingsRequest $request)
    {
        ComlinkSettings::setCredentials(
            $request->input('client_id'),
            $request->input('client_secret'),
            $request->input('bot_token'));
        ComlinkSettings::setSyncRate((int) $request->input('sync_rate'));
        ComlinkSettings::setLogRetention((int) $request->input('log_retention'));

        ComlinkLogger::info('Updated discord settings');

        return redirect()->route('seatcomlink::settings')
            ->with('success', trans('comlink::comlink.settings_saved'));
    }
}
