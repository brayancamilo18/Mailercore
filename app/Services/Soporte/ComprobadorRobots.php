<?php

namespace App\Services\Soporte;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ComprobadorRobots
{
    private const TTL_SEGUNDOS = 86400;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private array $config = []) {}

    /** ¿Se puede visitar esta ruta del sitio? */
    public function rutaPermitida(string $urlBase, string $ruta): bool
    {
        if (! ($this->config['respetar_robots'] ?? true)) {
            return true;
        }

        $host = parse_url($urlBase, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return true;
        }

        $disallows = $this->disallowsDeHost(strtolower($host), $urlBase);

        return $this->rutaPermitidaSegun($ruta, $disallows);
    }

    /**
     * @return list<string> prefijos Disallow que aplican a nuestro agente
     */
    public function disallowsDeHost(string $host, string $urlBase): array
    {
        return Cache::remember(
            'robots:'.$host,
            self::TTL_SEGUNDOS,
            fn (): array => $this->descargarDisallows($urlBase)
        );
    }

    /**
     * @return list<string>
     */
    private function descargarDisallows(string $urlBase): array
    {
        $userAgent = (string) ($this->config['user_agent'] ?? config('outreach.scraper.user_agent'));
        $url = rtrim($urlBase, '/').'/robots.txt';

        try {
            $respuesta = Http::withHeaders(['User-Agent' => $userAgent])
                ->timeout(10)
                ->get($url);

            if (! $respuesta->successful()) {
                return [];
            }

            return $this->parsear((string) $respuesta->body(), $userAgent);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function parsear(string $cuerpo, string $userAgent): array
    {
        $disallows = [];
        $aplica = false;

        foreach (preg_split('/\r\n|\r|\n/', $cuerpo) as $linea) {
            $linea = trim($linea);

            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }

            if (preg_match('/^user-agent:\s*(.+)$/i', $linea, $m) === 1) {
                $agente = trim($m[1]);
                $aplica = ($agente === '*' || stripos($userAgent, $agente) !== false);

                continue;
            }

            if (preg_match('/^disallow:\s*(.*)$/i', $linea, $m) === 1) {
                if (! $aplica) {
                    continue;
                }

                $ruta = trim($m[1]);

                if ($ruta !== '') {
                    $disallows[] = $ruta;
                }
            }
        }

        return $disallows;
    }

    /**
     * @param  list<string>  $disallows
     */
    private function rutaPermitidaSegun(string $ruta, array $disallows): bool
    {
        if ($ruta === '' || ! str_starts_with($ruta, '/')) {
            $ruta = '/'.$ruta;
        }

        foreach ($disallows as $disallow) {
            if ($disallow === '/') {
                return false;
            }

            if (str_starts_with($ruta, $disallow)) {
                return false;
            }
        }

        return true;
    }
}
