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
use Illuminate\Validation\Validator;
use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;
use ProudlySnoring\Seat\Comlink\Models\DiscordRole;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;

class MappingRequest extends FormRequest
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
            'server_id' => 'required|string',
            'squad_id' => 'required|integer|exists:squads,id',
            'role_id' => 'required|string',
        ];
    }

    /**
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty())
                return;

            $server = DiscordServer::present()->where('server_id', $this->input('server_id'))->first();

            if (! $server) {
                $validator->errors()->add('server_id', trans('comlink::comlink.mapping_invalid_server'));

                return;
            }

            $role = DiscordRole::where('server_id', $server->server_id)->where('role_id', $this->input('role_id'))->first();

            if (! $role) {
                $validator->errors()->add('role_id', trans('comlink::comlink.mapping_invalid_role'));

                return;
            }

            if ($reason = $role->unmanageableReason($server->bot_top_position)) {
                $validator->errors()->add('role_id', $reason);

                return;
            }

            $duplicate = DiscordMapping::where('server_id', $server->server_id)
                ->where('squad_id', $this->input('squad_id'))
                ->where('role_id', $role->role_id)
                ->when($this->route('mapping'), fn ($query, $mapping) => $query->where('id', '<>', $mapping->id))
                ->exists();

            if ($duplicate)
                $validator->errors()->add('role_id', trans('comlink::comlink.mapping_duplicate'));
        });
    }
}
