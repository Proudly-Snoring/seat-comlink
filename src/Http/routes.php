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

use Illuminate\Support\Facades\Route;
use ProudlySnoring\Seat\Comlink\Http\Controllers\IdentityController;
use ProudlySnoring\Seat\Comlink\Http\Controllers\LogsController;
use ProudlySnoring\Seat\Comlink\Http\Controllers\MappingsController;
use ProudlySnoring\Seat\Comlink\Http\Controllers\SettingsController;
use ProudlySnoring\Seat\Comlink\Http\Controllers\UsersController;

Route::group([
    'prefix' => 'seat-comlink',
    'middleware' => ['web', 'auth', 'locale'],
], function () {

    Route::group(['middleware' => 'can:comlink.identity'], function () {
        Route::get('/identity', [IdentityController::class, 'index'])
            ->name('seatcomlink::identity');
        Route::get('/discord/link', [IdentityController::class, 'link'])
            ->name('seatcomlink::discord.link');
        Route::get('/discord/callback', [IdentityController::class, 'callback'])
            ->name('seatcomlink::discord.callback');
        Route::post('/discord/link/confirm', [IdentityController::class, 'confirm'])
            ->name('seatcomlink::discord.link.confirm');
        Route::post('/discord/link/cancel', [IdentityController::class, 'cancel'])
            ->name('seatcomlink::discord.link.cancel');
        Route::post('/discord/unlink', [IdentityController::class, 'unlink'])
            ->name('seatcomlink::discord.unlink');
    });

    Route::group(['middleware' => 'can:comlink.mappings'], function () {
        Route::get('/mappings', [MappingsController::class, 'index'])
            ->name('seatcomlink::mappings');
        Route::post('/mappings', [MappingsController::class, 'store'])
            ->name('seatcomlink::mappings.store');
        Route::post('/mappings/import-roles', [MappingsController::class, 'importRoles'])
            ->name('seatcomlink::mappings.import');
        Route::put('/mappings/{mapping}', [MappingsController::class, 'update'])
            ->name('seatcomlink::mappings.update');
        Route::delete('/mappings/{mapping}', [MappingsController::class, 'destroy'])
            ->name('seatcomlink::mappings.destroy');
    });

    Route::group(['middleware' => 'can:comlink.settings'], function () {
        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('seatcomlink::settings');
        Route::post('/settings', [SettingsController::class, 'update'])
            ->name('seatcomlink::settings.update');
        Route::get('/settings/preview', [SettingsController::class, 'preview'])
            ->name('seatcomlink::settings.preview');
        Route::post('/settings/servers/{server}', [SettingsController::class, 'updateServer'])
            ->name('seatcomlink::settings.server');
    });

    Route::group(['middleware' => 'can:comlink.logs'], function () {
        Route::get('/logs', [LogsController::class, 'index'])
            ->name('seatcomlink::logs');
    });

    Route::group(['middleware' => 'can:comlink.users'], function () {
        Route::get('/users', [UsersController::class, 'index'])
            ->name('seatcomlink::users');
    });
});
