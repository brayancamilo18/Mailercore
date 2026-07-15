<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class EmailScraper
{
    /** Prefijos de buzón genérico; se priorizan por debajo de emails personales. */
    private const PREFIJOS_GENERICOS = ['info', 'contacto', 'hola', 'admin', 'soporte'];

    /** Extensiones de asset que suelen generar falsos positivos al extraer emails. */
    private const EXTENSIONES_ASSET = ['.png', '.jpg', '.svg', '.css', '.js'];

    /** Máximo de bytes de HTML a conservar en memoria por fetch. */
    private const MAX_HTML_BYTES = 1_048_576;

    private RobotsChecker $robots;

    private ScrapeRateLimiter $throttle;

    /**
     * @param  array{
     *     timeout: int,
     *     user_agent: string,
     *     contact_paths: array<int, string>,
     *     team_paths?: array<int, string>,
     *     blacklist_domains: array<int, string>,
     *     respect_robots?: bool
     * }  $config
     */
    public function __construct(
        private array $config,
        ?RobotsChecker $robots = null,
        ?ScrapeRateLimiter $throttle = null,
    ) {
        $this->robots = $robots ?? new RobotsChecker([
            'timeout' => $config['timeout'] ?? 12,
            'user_agent' => $config['user_agent'] ?? 'SilgoDevBot/1.0 (contacto@onez.es; +https://silgodev.es)',
            'respect_robots' => $config['respect_robots'] ?? true,
        ]);
        $this->throttle = $throttle ?? new ScrapeRateLimiter;
    }

    /**
     * Busca el mejor email de contacto en las rutas configuradas del sitio web.
     */
    public function findEmail(?string $websiteUrl): ?string
    {
        if ($websiteUrl === null || trim($websiteUrl) === '') {
            return null;
        }

        $baseUrl = $this->normalizeBaseUrl($websiteUrl);

        $emails = [];

        foreach ($this->config['contact_paths'] as $path) {
            if (! $this->robots->isPathAllowed($baseUrl, $path)) {
                continue;
            }

            $found = $this->fetchAndExtract($baseUrl.$path);

            if ($found === []) {
                continue;
            }

            $emails = array_merge($emails, $found);

            // En rutas de contacto distintas a home: cortamos si ya hay algo.
            if ($path !== '' && $this->filterEmails($found) !== []) {
                break;
            }
        }

        // Páginas de equipo: suelen tener emails personales (prioridad alta).
        if (! $this->tieneEmailPersonal($emails)) {
            foreach ($this->config['team_paths'] ?? [] as $path) {
                if (! $this->robots->isPathAllowed($baseUrl, $path)) {
                    continue;
                }

                $found = $this->fetchAndExtract($baseUrl.$path);

                if ($found === []) {
                    continue;
                }

                $emails = array_merge($emails, $found);

                if ($this->tieneEmailPersonal($found)) {
                    break;
                }
            }
        }

        $valid = $this->filterEmails($emails);
        unset($emails);

        if ($valid === []) {
            return null;
        }

        usort($valid, function (string $a, string $b): int {
            return $this->prioridadEmail($a) <=> $this->prioridadEmail($b);
        });

        $best = $valid[0];
        unset($valid);

        return $best;
    }

    /**
     * Descarga, extrae emails y libera el HTML de inmediato.
     *
     * @return list<string>
     */
    private function fetchAndExtract(string $url): array
    {
        $html = $this->fetch($url);

        if ($html === null) {
            return [];
        }

        $found = $this->extractEmails($html);
        unset($html);

        return $found;
    }

    /**
     * Extrae emails de HTML: JSON-LD, mailto, ofuscados y texto suelto.
     *
     * @return list<string>
     */
    public function extractEmails(string $html): array
    {
        $html = $this->deofuscar($html);
        $emails = [];

        foreach ($this->extractJsonLdEmails($html) as $email) {
            $emails[] = $email;
        }

        if (preg_match_all('/mailto:([^"\'\s>?]+)/i', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $emails[] = strtolower(urldecode((string) strtok($raw, '?')));
            }
        }

        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $html, $matches)) {
            foreach ($matches[0] as $raw) {
                $emails[] = strtolower($raw);
            }
        }

        unset($matches);

        return array_values(array_unique($emails));
    }

    private function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }

    /**
     * Descarga el HTML (con throttle + UA); recorta tamaño y no retiene body en propiedades.
     */
    private function fetch(string $url): ?string
    {
        try {
            $this->throttle->throttle($url);

            $userAgent = $this->config['user_agent']
                ?? 'SilgoDevBot/1.0 (contacto@onez.es; +https://silgodev.es)';

            $response = Http::withHeaders([
                'User-Agent' => $userAgent,
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            ])
                ->timeout($this->config['timeout'])
                ->get($url);

            $response->throw();

            $body = $response->body();
            // Libera la respuesta cuanto antes; el body ya está en $body.
            unset($response);

            if (strlen($body) > self::MAX_HTML_BYTES) {
                $body = substr($body, 0, self::MAX_HTML_BYTES);
            }

            return $body;
        } catch (\Throwable) {
            return null;
        }
    }

    private function deofuscar(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = preg_replace('/\s*\(\s*arroba\s*\)\s*/iu', '@', $html) ?? $html;
        $html = preg_replace('/\s*\(\s*punto\s*\)\s*/iu', '.', $html) ?? $html;
        $html = preg_replace('/\s*\[\s*at\s*\]\s*/iu', '@', $html) ?? $html;
        $html = preg_replace('/\s*\[\s*dot\s*\]\s*/iu', '.', $html) ?? $html;
        $html = preg_replace('/\s+AT\s+/u', '@', $html) ?? $html;
        $html = preg_replace('/\s+DOT\s+/u', '.', $html) ?? $html;
        $html = preg_replace('/\s+arroba\s+/iu', '@', $html) ?? $html;
        $html = preg_replace('/\s+punto\s+/iu', '.', $html) ?? $html;

        return $html;
    }

    /**
     * @return list<string>
     */
    private function extractJsonLdEmails(string $html): array
    {
        $emails = [];

        if (! preg_match_all(
            '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        )) {
            return [];
        }

        foreach ($matches[1] as $json) {
            $data = json_decode(trim($json), true);

            if (! is_array($data)) {
                continue;
            }

            $this->recorrerJsonLd($data, $emails);
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<string>  $emails
     */
    private function recorrerJsonLd(array $node, array &$emails): void
    {
        if (array_is_list($node)) {
            foreach ($node as $item) {
                if (is_array($item)) {
                    $this->recorrerJsonLd($item, $emails);
                }
            }

            return;
        }

        if (isset($node['email'])) {
            foreach ((array) $node['email'] as $raw) {
                if (! is_string($raw)) {
                    continue;
                }

                $email = strtolower(trim(preg_replace('#^mailto:#i', '', $raw) ?? $raw));

                if ($email !== '') {
                    $emails[] = $email;
                }
            }
        }

        foreach (['contactPoint', 'ContactPoint', 'department', 'employee', 'founder', 'member'] as $clave) {
            if (isset($node[$clave]) && is_array($node[$clave])) {
                $this->recorrerJsonLd($node[$clave], $emails);
            }
        }

        foreach ($node as $valor) {
            if (is_array($valor)) {
                $this->recorrerJsonLd($valor, $emails);
            }
        }
    }

    /**
     * @param  list<string>  $emails
     */
    private function tieneEmailPersonal(array $emails): bool
    {
        foreach ($this->filterEmails($emails) as $email) {
            if ($this->prioridadEmail($email) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function filterEmails(array $emails): array
    {
        return array_values(array_filter(array_unique($emails), function (string $email): bool {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            foreach (self::EXTENSIONES_ASSET as $extension) {
                if (str_ends_with($email, $extension)) {
                    return false;
                }
            }

            $domain = strtolower(substr(strrchr($email, '@'), 1) ?: '');

            foreach ($this->config['blacklist_domains'] as $blacklisted) {
                $blacklisted = strtolower($blacklisted);

                if ($domain === $blacklisted || str_ends_with($domain, '.'.$blacklisted)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function prioridadEmail(string $email): int
    {
        $local = strtolower(explode('@', $email)[0] ?? '');

        return in_array($local, self::PREFIJOS_GENERICOS, true) ? 1 : 0;
    }
}
