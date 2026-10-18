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

namespace ProudlySnoring\Seat\Comlink\Drivers\Discord;

use RuntimeException;

/**
 * A failed discord API call.
 */
class DiscordException extends RuntimeException
{
    // https://discord.com/developers/docs/topics/opcodes-and-status-codes#json
    const MISSING_ACCESS = 50001;

    const MISSING_PERMISSIONS = 50013;

    const UNKNOWN_MEMBER = 10007;

    const UNKNOWN_ROLE = 10011;

    /**
     * @var int  HTTP status (0 on network error)
     */
    public int $status;

    /**
     * @var int|null  discord JSON error code
     */
    public ?int $discord_code;

    public function __construct(string $message, int $status = 0, ?int $discord_code = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);

        $this->status = $status;
        $this->discord_code = $discord_code;
    }

    /**
     * Human readable reason, used in logs. Ex: "Missing Permissions (50013)".
     *
     * @return string
     */
    public function reason(): string
    {
        return is_null($this->discord_code) ? $this->getMessage() : sprintf('%s (%d)', $this->getMessage(), $this->discord_code);
    }
}
