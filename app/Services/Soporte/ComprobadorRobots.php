<?php

namespace App\Services\Soporte;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ComprobadorRobots
{
    private const TTL_SEGUNDOS = 86400;

    /** Tope de cortesía: un Crawl-delay mayor se recorta a esto. */
    private const CRAWL_DELAY_MAXIMO = 30.0;

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
        return $this->reglasDeHost($host, $urlBase)['disallows'];
    }

    /**
     * Segundos de espera entre peticiones que pide el sitio, o null si no lo
     * dice. Se ignora si robots está desactivado por configuración.
     */
    public function crawlDelayDe(string $urlBase): ?float
    {
        if (! ($this->config['respetar_robots'] ?? true)) {
            return null;
        }

        $host = parse_url($urlBase, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return $this->reglasDeHost(strtolower($host), $urlBase)['crawl_delay'];
    }

    /**
     * @return array{disallows: list<string>, crawl_delay: ?float}
     */
    private function reglasDeHost(string $host, string $urlBase): array
    {
        /** @var array{disallows: list<string>, crawl_delay: ?float} */
        return Cache::remember(
            'robots:'.$host,
            self::TTL_SEGUNDOS,
            fn (): array => $this->descargarReglas($urlBase)
        );
    }

    /**
     * @return array{disallows: list<string>, crawl_delay: ?float}
     */
    private function descargarReglas(string $urlBase): array
    {
        $userAgent = (string) ($this->config['user_agent'] ?? config('outreach.scraper.user_agent'));
        $url = rtrim($urlBase, '/').'/robots.txt';

        try {
            $respuesta = Http::withHeaders(['User-Agent' => $userAgent])
                ->timeout(10)
                ->get($url);

            if (! $respuesta->successful()) {
                return ['disallows' => [], 'crawl_delay' => null];
            }

            return $this->parsear((string) $respuesta->body(), $userAgent);
        } catch (\Throwable) {
            return ['disallows' => [], 'crawl_delay' => null];
        }
    }

    /**
     * @return array{disallows: list<string>, crawl_delay: ?float}
     */
    private function parsear(string $cuerpo, string $userAgent): array
    {
        $disallows = [];
        $crawlDelay = null;
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

                continue;
            }

            if (preg_match('/^crawl-delay:\s*([0-9]+(?:[.,][0-9]+)?)\s*$/i', $linea, $m) === 1) {
                if (! $aplica) {
                    continue;
                }

                $valor = (float) str_replace(',', '.', $m[1]);

                if ($valor > 0) {
                    // Con varios bloques aplicables mandamos el más lento.
                    $crawlDelay = min(max($crawlDelay ?? 0.0, $valor), self::CRAWL_DELAY_MAXIMO);
                }
            }
        }

        return ['disallows' => $disallows, 'crawl_delay' => $crawlDelay];
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
