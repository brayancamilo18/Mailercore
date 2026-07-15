<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Throttle global y por dominio para el scrape de webs.
 */
class ScrapeRateLimiter
{
    /**
     * Espera hasta poder hacer una petición (RPM global + por dominio).
     */
    public function throttle(string $url): void
    {
        $host = $this->hostFromUrl($url) ?? 'unknown';

        $this->waitForKey(
            'scrape:global',
            max(1, (int) config('outreach.harvest.requests_per_minute', 20)),
        );

        $this->waitForKey(
            'scrape:host:'.$host,
            max(1, (int) config('outreach.harvest.requests_per_domain_per_minute', 6)),
        );
    }

    private function waitForKey(string $key, int $maxAttempts): void
    {
        $decaySeconds = 60;
        $spins = 0;

        while (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $spins++;
            // Evita bucles infinitos en tests / configs absurdas.
            if ($spins > 120) {
                break;
            }

            $seconds = RateLimiter::availableIn($key);
            usleep(max(1, min(5, $seconds > 0 ? $seconds : 1)) * 200_000);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    public function hostFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower($host);
    }
}
