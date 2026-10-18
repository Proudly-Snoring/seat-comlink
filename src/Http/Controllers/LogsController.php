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
        ]);

        $logs = ComlinkLog::with('user')
            ->when($filters['level'] ?? null, fn ($query, $level) => $query->where('level', $level))
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
        ]);
    }
}
