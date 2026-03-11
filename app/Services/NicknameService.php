<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NicknameService
{
    /**
     * Pick a random city+firstname pair not already assigned to any user (excluding the given user ID).
     * Returns an array of [city_id, first_name_id], or null if no pair is available.
     */
    public function randomPairExcluding(int $excludeUserId): ?array
    {
        $pick = DB::selectOne('
            SELECT c.id AS city_id, fn.id AS first_name_id
            FROM cities c
            CROSS JOIN first_names fn
            WHERE NOT EXISTS (
                SELECT 1 FROM users u
                WHERE u.city_id = c.id
                  AND u.first_name_id = fn.id
                  AND u.id != ?
            )
            ORDER BY RANDOM()
            LIMIT 1
        ', [$excludeUserId]);

        if (! $pick) {
            return null;
        }

        return [$pick->city_id, $pick->first_name_id];
    }
}
