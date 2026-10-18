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

namespace ProudlySnoring\Seat\Comlink\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves the "selected character" of SeAT users (main character, else first linked character)
 * and its affiliation values used by nickname tokens. Batch queries on core tables, no model magic.
 */
class IdentityResolver
{
    /**
     * @param  int[]  $user_ids
     * @return array<int, array{main_name: ?string, corp_ticker: ?string, corp_name: ?string, alliance_ticker: ?string}>
     */
    public function forUsers(array $user_ids): array
    {
        $user_ids = array_values(array_unique(array_map('intval', $user_ids)));
        $result = array_fill_keys($user_ids, self::empty());

        if (empty($user_ids))
            return $result;

        // candidates: main character first, then linked characters by link date
        $candidates = [];

        foreach (DB::table('users')->whereIn('id', $user_ids)->get(['id', 'main_character_id']) as $user) {
            if (! empty($user->main_character_id))
                $candidates[$user->id][] = (int) $user->main_character_id;
        }

        $tokens = DB::table('refresh_tokens')->whereIn('user_id', $user_ids)->whereNull('deleted_at')
            ->orderBy('created_at')->orderBy('character_id')->get(['user_id', 'character_id']);

        foreach ($tokens as $token)
            $candidates[$token->user_id][] = (int) $token->character_id;

        $character_ids = array_unique(array_merge([], ...array_values($candidates)));

        if (empty($character_ids))
            return $result;

        $names = DB::table('character_infos')->whereIn('character_id', $character_ids)->pluck('name', 'character_id');

        $selected = [];

        foreach ($candidates as $user_id => $ids) {
            foreach ($ids as $id) {
                if ($names->has($id)) {
                    $selected[$user_id] = $id;
                    break;
                }
            }
        }

        $affiliations = DB::table('character_affiliations')->whereIn('character_id', array_values($selected))
            ->get(['character_id', 'corporation_id', 'alliance_id'])->keyBy('character_id');

        $corporations = DB::table('corporation_infos')
            ->whereIn('corporation_id', $affiliations->pluck('corporation_id')->filter()->unique())
            ->get(['corporation_id', 'name', 'ticker'])->keyBy('corporation_id');

        $alliances = DB::table('alliances')
            ->whereIn('alliance_id', $affiliations->pluck('alliance_id')->filter()->unique())
            ->pluck('ticker', 'alliance_id');

        foreach ($selected as $user_id => $character_id) {
            $affiliation = $affiliations->get($character_id);
            $corporation = $affiliation ? $corporations->get($affiliation->corporation_id) : null;

            $result[$user_id] = [
                'main_name' => $names->get($character_id),
                'corp_ticker' => $corporation->ticker ?? null,
                'corp_name' => $corporation->name ?? null,
                'alliance_ticker' => $affiliation && $affiliation->alliance_id ? $alliances->get($affiliation->alliance_id) : null,
            ];
        }

        return $result;
    }

    /**
     * @param  int  $user_id
     * @return array
     */
    public function forUser(int $user_id): array
    {
        return $this->forUsers([$user_id])[$user_id];
    }

    /**
     * @return array
     */
    public static function empty(): array
    {
        return ['main_name' => null, 'corp_ticker' => null, 'corp_name' => null, 'alliance_ticker' => null];
    }
}
