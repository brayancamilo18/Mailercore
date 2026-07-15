<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Flag de recorrido harvest sin tocar .env (pause/resume vía Cache).
 */
class HarvestControl
{
    public const CACHE_ENABLED = 'outreach:harvest:enabled';

    public const CACHE_LAST_FINISHED = 'outreach:harvest:last_finished_at';

    public const LOCK_KEY = 'outreach:harvest:run';

    /**
     * ¿El orquestador puede arrancar un área?
     */
    public static function isEnabled(): bool
    {
        return (bool) Cache::get(self::CACHE_ENABLED, config('outreach.harvest.enabled', true));
    }

    public static function pause(): void
    {
        Cache::forever(self::CACHE_ENABLED, false);
    }

    public static function resume(): void
    {
        Cache::forever(self::CACHE_ENABLED, true);
    }

    /**
     * True si aún no ha pasado la pausa configurada tras el último área finalizada.
     */
    public static function isPauseBetweenAreasActive(): bool
    {
        $seconds = (int) config('outreach.harvest.pause_between_areas_seconds', 0);

        if ($seconds <= 0) {
            return false;
        }

        $last = Cache::get(self::CACHE_LAST_FINISHED);

        if (! is_string($last) || $last === '') {
            return false;
        }

        try {
            return \Illuminate\Support\Carbon::parse($last)->addSeconds($seconds)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function markAreaFinished(): void
    {
        Cache::put(self::CACHE_LAST_FINISHED, now()->toIso8601String(), now()->addDay());
    }
}
