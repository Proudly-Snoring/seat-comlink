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
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use Seat\Web\Http\Controllers\Controller;
use Seat\Web\Models\User;

/**
 * Class LogsController.
 *
 * @package ProudlySnoring\Seat\Comlink\Http\Controllers
 */
class LogsController extends Controller
{
    // user filter value for logs without origin user (synchronization)
    const SYSTEM = 'system';

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'level' => ['nullable', Rule::in(ComlinkLog::LEVELS)],
            'driver' => 'nullable|string|max:32',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'user' => ['nullable', 'regex:/^(system|\d+)$/'],
        ]);

        $logs = ComlinkLog::with('user')
            ->when($filters['level'] ?? null, fn ($query, $level) => $query->where('level', $level))
            ->when($filters['user'] ?? null, function ($query, $user) {
                $user === self::SYSTEM ? $query->whereNull('user_id') : $query->where('user_id', (int) $user);
            })
            ->when($filters['driver'] ?? null, fn ($query, $driver) => $query->where('driver', $driver))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('comlink::logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'levels' => ComlinkLog::LEVELS,
            'drivers' => ComlinkLog::distinct()->orderBy('driver')->pluck('driver'),
            // users at the origin of at least one log
            'users' => User::whereIn('id', ComlinkLog::whereNotNull('user_id')->distinct()->select('user_id'))
                ->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
