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
    'discord' => [
        'api' => 'https://discord.com/api/v10',
        'authorize' => 'https://discord.com/oauth2/authorize',
        // Manage Roles (1 << 28) + Manage Nicknames (1 << 27)
        'permissions' => 402653184,
        'portal' => 'https://discord.com/developers/applications',
    ],
    'sync_rates' => [1, 2, 3, 4, 5, 6, 10, 12, 15, 20, 30, 60],
    'defaults' => [
        'sync_rate' => 5,
        'log_retention' => 30,
        'nickname_format' => '[{corp_ticker}] {main_name}',
    ],
    'queue' => 'default',
];
