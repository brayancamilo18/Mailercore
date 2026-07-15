<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Comprueba robots.txt de un dominio, con caché por host, para scrapers y fuentes.
 */
class RobotsChecker
{
    private const CACHE_TTL_SECONDS = 86_400;

    /**
     * @param  array{
     *     timeout?: int,
     *     user_agent?: string,
     *     respect_robots?: bool
     * }  $config
     */
    public function __construct(private array $config = [])
    {
    }

    /**
     * Indica si la ruta relativa (p. ej. /equipo) está permitida para el host de $baseUrl.
     */
    public function isPathAllowed(string $baseUrl, string $path): bool
    {
        if (! ($this->config['respect_robots'] ?? true)) {
            return true;
        }

        $host = $this->hostFromUrl($baseUrl);

        if ($host === null) {
            return true;
        }

        $disallows = $this->disallowsForHost($host, rtrim($baseUrl, '/'));

        return $this->pathAllowedByDisallows($path, $disallows);
    }

    /**
     * Indica si una URL absoluta está permitida según robots.txt de su host.
     */
    public function isUrlAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return true;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $baseUrl = $scheme.'://'.$parts['host'];
        $path = $parts['path'] ?? '/';

        return $this->isPathAllowed($baseUrl, $path);
    }

    /**
     * Prefijos Disallow aplicables a nuestro User-Agent (caché por dominio).
     *
     * @return list<string>
     */
    public function disallowsForHost(string $host, ?string $baseUrl = null): array
    {
        if (! ($this->config['respect_robots'] ?? true)) {
            return [];
        }

        $cacheKey = 'outreach:robots:'.$host;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($host, $baseUrl): array {
            $origin = $baseUrl ?? 'https://'.$host;

            return $this->fetchDisallows(rtrim($origin, '/'));
        });
    }

    /**
     * @return list<string>
     */
    private function fetchDisallows(string $baseUrl): array
    {
        $timeout = (int) ($this->config['timeout'] ?? 12);
        $userAgent = (string) ($this->config['user_agent'] ?? 'SilgoDevBot/1.0');

        try {
            $response = Http::withHeaders(['User-Agent' => $userAgent])
                ->timeout($timeout)
                ->get($baseUrl.'/robots.txt');

            if (! $response->successful()) {
                return [];
            }

            return $this->parseRobotsBody($response->body(), $userAgent);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function parseRobotsBody(string $body, string $userAgent): array
    {
        $disallows = [];
        $aplica = false;

        foreach (preg_split('/\R/', $body) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                $agent = trim($m[1]);
                $aplica = $agent === '*' || stripos($userAgent, $agent) !== false;

                continue;
            }

            if ($aplica && preg_match('/^disallow:\s*(.*)$/i', $line, $m)) {
                $path = trim($m[1]);

                if ($path !== '') {
                    $disallows[] = $path;
                }
            }
        }

        return $disallows;
    }

    /**
     * @param  list<string>  $disallows
     */
    private function pathAllowedByDisallows(string $path, array $disallows): bool
    {
        $path = $path === '' ? '/' : $path;

        if ($path[0] !== '/') {
            $path = '/'.$path;
        }

        foreach ($disallows as $disallow) {
            if ($disallow === '/') {
                return false;
            }

            if (str_starts_with($path, $disallow)) {
                return false;
            }
        }

        return true;
    }

    private function hostFromUrl(string $url): ?string
    {
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
