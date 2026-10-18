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
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\ServerRefresher;
use ProudlySnoring\Seat\Comlink\Http\Validation\MappingRequest;
use ProudlySnoring\Seat\Comlink\Jobs\DiscoverServers;
use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use Seat\Web\Http\Controllers\Controller;
use Seat\Web\Models\Squads\Squad;

/**
 * Squads -> discord roles mappings.
 *
 * @package ProudlySnoring\Seat\Comlink\Http\Controllers
 */
class MappingsController extends Controller
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $filters = $request->only(['server', 'squad', 'role']);

        $mappings = DiscordMapping::with('server', 'squad', 'role')
            ->when($filters['server'] ?? null, fn ($query, $server) => $query->where('server_id', $server))
            ->when($filters['squad'] ?? null, fn ($query, $squad) => $query->where('squad_id', $squad))
            ->when($filters['role'] ?? null, function ($query, $role) {
                $query->whereHas('role', fn ($sub) => $sub->withTrashed()->where('name', 'like', '%' . $role . '%'));
            })
            ->orderBy('server_id')->orderBy('squad_id')->orderBy('id')
            ->paginate(50)->withQueryString();

        $servers = DiscordServer::present()->with(['roles' => fn ($query) => $query->orderByDesc('position')])
            ->orderBy('name')->get();

        return view('comlink::mappings.index', [
            'mappings' => $mappings,
            'servers' => $servers,
            'all_servers' => DiscordServer::orderBy('name')->get(),
            'squads' => Squad::withoutGlobalScopes()->orderBy('name')->get(['id', 'name']),
            'squad_counts' => DB::table('squad_member')->groupBy('squad_id')->select('squad_id', DB::raw('count(*) as total'))
                ->pluck('total', 'squad_id'),
            'filters' => $filters,
        ]);
    }

    /**
     * @param  \ProudlySnoring\Seat\Comlink\Http\Validation\MappingRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(MappingRequest $request)
    {
        $mapping = DiscordMapping::create($request->only(['server_id', 'squad_id', 'role_id']));

        $this->log('Added', $mapping);

        return redirect()->back()->with('success', trans('comlink::comlink.mapping_saved'));
    }

    /**
     * @param  \ProudlySnoring\Seat\Comlink\Http\Validation\MappingRequest  $request
     * @param  \ProudlySnoring\Seat\Comlink\Models\DiscordMapping  $mapping
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(MappingRequest $request, DiscordMapping $mapping)
    {
        $mapping->update($request->only(['server_id', 'squad_id', 'role_id']));

        $this->log('Edited', $mapping->fresh());

        return redirect()->back()->with('success', trans('comlink::comlink.mapping_saved'));
    }

    /**
     * @param  \ProudlySnoring\Seat\Comlink\Models\DiscordMapping  $mapping
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(DiscordMapping $mapping)
    {
        $mapping->delete();

        $this->log('Removed', $mapping);

        return redirect()->back()->with('success', trans('comlink::comlink.mapping_removed'));
    }

    /**
     * Refresh the servers and roles lists from discord right now.
     *
     * @param  \ProudlySnoring\Seat\Comlink\Drivers\Discord\ServerRefresher  $refresher
     * @return \Illuminate\Http\RedirectResponse
     */
    public function importRoles(ServerRefresher $refresher)
    {
        if (! ComlinkSettings::hasCredentials())
            return redirect()->back()->with('error', trans('comlink::comlink.not_configured'));

        try {
            $servers = $refresher->discover();

            foreach ($servers as $server)
                $refresher->refresh($server);
        } catch (DiscordException $e) {
            ComlinkLogger::error(sprintf('Failed to import discord roles: %s', $e->reason()));

            return redirect()->back()->with('error', trans('comlink::comlink.import_roles_failed', ['reason' => $e->reason()]));
        }

        ComlinkLogger::info(sprintf('Imported discord roles from %d servers', $servers->count()),
            ['servers' => $servers->pluck('name')->all()]);

        return redirect()->back()->with('success', trans_choice('comlink::comlink.import_roles_done', $servers->count(), ['count' => $servers->count()]));
    }

    /**
     * Queue a synchronization of all servers.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sync()
    {
        if (! ComlinkSettings::hasCredentials())
            return redirect()->back()->with('error', trans('comlink::comlink.not_configured'));

        DiscoverServers::dispatch();

        ComlinkLogger::info('Requested a manual synchronization of all discord servers');

        return redirect()->back()->with('success', trans('comlink::comlink.sync_queued'));
    }

    /**
     * @param  string  $action
     * @param  \ProudlySnoring\Seat\Comlink\Models\DiscordMapping  $mapping
     */
    private function log(string $action, DiscordMapping $mapping): void
    {
        ComlinkLogger::info(sprintf('%s mapping %s -> %s for discord server %s',
            $action, $mapping->squad_name, $mapping->role_name, $mapping->server_name));
    }
}
