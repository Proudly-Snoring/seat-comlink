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

namespace ProudlySnoring\Seat\Comlink;

use ProudlySnoring\Seat\Comlink\Commands\DiscordImportWarlof;
use ProudlySnoring\Seat\Comlink\Commands\DiscordSync;
use ProudlySnoring\Seat\Comlink\Commands\PruneLogs;
use ProudlySnoring\Seat\Comlink\Database\Seeders\ComlinkScheduleSeeder;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordClient;
use ProudlySnoring\Seat\Comlink\Observers\SquadObserver;
use ProudlySnoring\Seat\Comlink\Observers\UserObserver;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use Seat\Services\AbstractSeatPlugin;
use Seat\Web\Models\Squads\Squad;
use Seat\Web\Models\User;

/**
 * Class ComlinkServiceProvider.
 *
 * @package ProudlySnoring\Seat\Comlink
 */
class ComlinkServiceProvider extends AbstractSeatPlugin
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->app->routesAreCached())
            $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');

        $this->loadViewsFrom(__DIR__ . '/resources/views', 'comlink');
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'comlink');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscordSync::class,
                DiscordImportWarlof::class,
                PruneLogs::class,
            ]);
        }

        User::observe(UserObserver::class);
        Squad::observe(SquadObserver::class);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/comlink.config.php', 'comlink.config');
        $this->mergeConfigFrom(__DIR__ . '/Config/package.sidebar.php', 'package.sidebar');
        $this->registerPermissions(__DIR__ . '/Config/Permissions/comlink.php', 'comlink');
        $this->registerDatabaseSeeders(ComlinkScheduleSeeder::class);

        // not a singleton: the token may change between two calls (settings update)
        $this->app->bind(DiscordClient::class, function () {
            return new DiscordClient(ComlinkSettings::botToken());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'SeAT Comlink';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): ?string
    {
        return 'Manage Discord roles from squads and enforce nicknames on multiple servers.';
    }

    /**
     * {@inheritdoc}
     */
    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/Proudly-Snoring/seat-comlink';
    }

    /**
     * {@inheritdoc}
     */
    public function getPackagistPackageName(): string
    {
        return 'seat-comlink';
    }

    /**
     * {@inheritdoc}
     */
    public function getPackagistVendorName(): string
    {
        return 'proudly-snoring';
    }
}
