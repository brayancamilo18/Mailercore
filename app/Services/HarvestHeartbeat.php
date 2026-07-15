<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Latido de cosecha en Cache (vivacidad de harvest:run y workers).
 */
class HarvestHeartbeat
{
    public const CACHE_KEY = 'harvest:heartbeat';

    /**
     * Registra un latido (timestamp Unix).
     */
    public static function touch(?string $source = null): void
    {
        Cache::put(self::CACHE_KEY, [
            'at' => now()->timestamp,
            'source' => $source,
        ], now()->addDay());
    }

    /**
     * Timestamp Unix del último latido, o null si nunca hubo.
     */
    public static function lastAt(): ?int
    {
        $raw = Cache::get(self::CACHE_KEY);

        if (is_array($raw) && isset($raw['at'])) {
            return (int) $raw['at'];
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }

    /**
     * Segundos desde el último latido (null = sin señal).
     */
    public static function ageSeconds(): ?int
    {
        $at = self::lastAt();

        if ($at === null) {
            return null;
        }

        return max(0, now()->timestamp - $at);
    }

    /**
     * ¿Latido reciente (< umbral “vivo”, por defecto 2 min)?
     */
    public static function isFresh(?int $okSeconds = null): bool
    {
        $okSeconds ??= (int) config('outreach.harvest.heartbeat_ok_seconds', 120);
        $age = self::ageSeconds();

        return $age !== null && $age < $okSeconds;
    }

    /**
     * ¿Latido ausente o más viejo que el umbral de healthcheck?
     */
    public static function isStale(?int $staleSeconds = null): bool
    {
        $staleSeconds ??= (int) config('outreach.harvest.heartbeat_stale_seconds', 600);
        $age = self::ageSeconds();

        return $age === null || $age >= $staleSeconds;
    }

    public static function lastCarbon(): ?Carbon
    {
        $at = self::lastAt();

        return $at !== null ? Carbon::createFromTimestamp($at) : null;
    }
}
