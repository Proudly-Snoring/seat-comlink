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

/**
 * Renders nicknames from a format and adapts them to the discord naming rules.
 */
class NicknameRenderer
{
    const TOKENS = ['main_name', 'corp_ticker', 'corp_name', 'alliance_ticker'];

    const MAX_LENGTH = 32;

    const MISSING = '-';

    /**
     * Whitespaces (any kind) collapsed into a single space.
     */
    const WHITESPACES = '/[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u';

    /**
     * Zero-width and non-rendering characters, removed. Joiners and variation selectors are kept (emoji).
     */
    const FORBIDDEN = '/[\p{Cc}\p{Co}\p{Cn}\x{00AD}\x{034F}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{200B}\x{200C}' .
        '\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{206F}\x{2800}\x{3164}\x{FEFF}\x{FFA0}]/u';

    /**
     * @param  string  $format  ex: [{corp_ticker}] {main_name}
     * @param  array  $values  token => value
     * @return string
     */
    public function render(string $format, array $values): string
    {
        $nickname = preg_replace_callback('/\{(' . implode('|', self::TOKENS) . ')\}/', function ($match) use ($values) {
            $value = self::normalize((string) ($values[$match[1]] ?? ''), false);

            return $value === '' ? self::MISSING : $value;
        }, $format);

        return self::normalize($nickname);
    }

    /**
     * Apply the discord naming rules: forbidden characters and extra whitespaces removed, truncated to 32 characters.
     *
     * @param  string|null  $nickname
     * @param  bool  $truncate  false to only clean the value (for tokens)
     * @return string  never empty when truncating
     */
    public static function normalize(?string $nickname, bool $truncate = true): string
    {
        $nickname = mb_scrub((string) $nickname, 'UTF-8');
        $nickname = preg_replace(self::WHITESPACES, ' ', $nickname);
        $nickname = preg_replace(self::FORBIDDEN, '', $nickname);
        $nickname = trim(preg_replace('/ {2,}/', ' ', $nickname));

        if (! $truncate)
            return $nickname;

        $nickname = rtrim(mb_substr($nickname, 0, self::MAX_LENGTH));

        return $nickname === '' ? self::MISSING : $nickname;
    }
}
