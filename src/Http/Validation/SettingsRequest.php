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

namespace ProudlySnoring\Seat\Comlink\Http\Validation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'client_id' => 'nullable|regex:/^\d{1,32}$/',
            'client_secret' => 'nullable|string|max:255',
            'bot_token' => 'nullable|string|max:255',
            'sync_rate' => ['required', 'integer', Rule::in(config('comlink.config.sync_rates'))],
            'log_retention' => 'required|integer|min:1|max:3650',
        ];
    }
}
