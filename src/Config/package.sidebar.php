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

return [
    'comlink' => [
        'name' => 'comlink',
        'label' => 'comlink::comlink.menu',
        'icon' => 'fas fa-headset',
        'route_segment' => 'seat-comlink',
        'permission' => [
            'comlink.identity', 'comlink.mappings', 'comlink.servers', 'comlink.settings', 'comlink.logs', 'comlink.users',
        ],
        'entries' => [
            [
                'name' => 'identity',
                'label' => 'comlink::comlink.menu_identity',
                'icon' => 'fas fa-id-card',
                'route' => 'seatcomlink::identity',
                'permission' => 'comlink.identity',
            ],
            [
                'name' => 'mappings',
                'label' => 'comlink::comlink.menu_mappings',
                'icon' => 'fab fa-discord',
                'route' => 'seatcomlink::mappings',
                'permission' => 'comlink.mappings',
            ],
            [
                'name' => 'servers',
                'label' => 'comlink::comlink.menu_servers',
                'icon' => 'fas fa-server',
                'route' => 'seatcomlink::servers',
                'permission' => 'comlink.servers',
            ],
            [
                'name' => 'settings',
                'label' => 'comlink::comlink.menu_settings',
                'icon' => 'fas fa-cogs',
                'route' => 'seatcomlink::settings',
                'permission' => 'comlink.settings',
            ],
            [
                'name' => 'logs',
                'label' => 'comlink::comlink.menu_logs',
                'icon' => 'fas fa-list',
                'route' => 'seatcomlink::logs',
                'permission' => 'comlink.logs',
            ],
            [
                'name' => 'users',
                'label' => 'comlink::comlink.menu_users',
                'icon' => 'fas fa-users',
                'route' => 'seatcomlink::users',
                'permission' => 'comlink.users',
            ],
        ],
    ],
];
