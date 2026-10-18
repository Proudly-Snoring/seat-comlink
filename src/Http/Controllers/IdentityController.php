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

use Seat\Web\Http\Controllers\Controller;

/**
 * Class IdentityController.
 *
 * @package ProudlySnoring\Seat\Comlink\Http\Controllers
 */
class IdentityController extends Controller
{
    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('comlink::identity.index');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback()
    {
        return redirect()->route('seatcomlink::identity');
    }
}
