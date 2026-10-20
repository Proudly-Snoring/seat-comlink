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

/**
 * Class LogsController.
 *
 * @package ProudlySnoring\Seat\Comlink\Http\Controllers
 */
class LogsController extends Controller
{
    // levels displayed when none is requested (debug logs clutter the view)
    const DEFAULT_LEVELS = ['info', 'warning', 'error'];

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'level' => 'nullable|array',
            'level.*' => [Rule::in(ComlinkLog::LEVELS)],
            'driver' => 'nullable|string|max:32',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'user' => 'nullable|string|max:255',
        ]);
        $filters['level'] = ($filters['level'] ?? null) ?: self::DEFAULT_LEVELS;
        $filters['user'] = trim((string) ($filters['user'] ?? ''));

        $logs = ComlinkLog::with('user')
            ->whereIn('level', $filters['level'])
            // partial match on the user name, or on the "System" label for logs without origin user
            ->when($filters['user'] !== '', function ($query) use ($filters) {
                $query->where(function ($sub) use ($filters) {
                    $sub->whereHas('user', fn ($user) => $user->where('name', 'like', '%' . $filters['user'] . '%'));
                    if (stripos(trans('comlink::comlink.log_system'), $filters['user']) !== false)
                        $sub->orWhereNull('user_id');
                });
            })
            ->when($filters['driver'] ?? null, fn ($query, $driver) => $query->where('driver', $driver))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('comlink::logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            // level => bootstrap contextual class
            'levels' => collect(ComlinkLog::LEVELS)->mapWithKeys(fn ($level) => [$level => ComlinkLog::levelClass($level)]),
            'drivers' => ComlinkLog::distinct()->orderBy('driver')->pluck('driver'),
        ]);
    }
}
