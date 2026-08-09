<?php

namespace App\Services\Soporte;

use App\Excepciones\LimiteRitmoExcedido;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

class LimitadorRitmo
{
    /** @var array<string, float> host => microtime de la última petición */
    private array $ultimaPeticion = [];

    /**
     * Espera hasta poder hacer una petición al host de $url.
     *
     * @param  float|null  $crawlDelay  segundos que pide robots.txt entre peticiones
     *
     * @throws LimiteRitmoExcedido si tras esperar el límite sigue activo.
     */
    public function esperar(string $url, ?float $crawlDelay = null): void
    {
        $host = $this->hostDe($url) ?? 'desconocido';

        $this->esperarClave('scrape:global',
            (int) config('outreach.scraper.peticiones_por_minuto'));
        $this->esperarClave('scrape:host:'.$host,
            (int) config('outreach.scraper.peticiones_por_dominio_por_minuto'));

        $this->esperarCrawlDelay($host, $crawlDelay);

        $this->ultimaPeticion[$host] = microtime(true);
    }

    /** Intervalo mínimo, en segundos, que impone nuestra propia cuota por dominio. */
    public function intervaloPorDominio(): float
    {
        $porMinuto = max(1, (int) config('outreach.scraper.peticiones_por_dominio_por_minuto'));

        return 60 / $porMinuto;
    }

    /**
     * Si el sitio pide más margen del que ya impone nuestra cuota, se lo damos.
     */
    private function esperarCrawlDelay(string $host, ?float $crawlDelay): void
    {
        if ($crawlDelay === null || $crawlDelay <= $this->intervaloPorDominio()) {
            return;
        }

        $ultima = $this->ultimaPeticion[$host] ?? null;

        if ($ultima === null) {
            return;
        }

        $restante = $crawlDelay - (microtime(true) - $ultima);

        if ($restante > 0) {
            Sleep::for($restante)->seconds();
        }
    }

    private function esperarClave(string $clave, int $maximo): void
    {
        $maximo = max(1, $maximo);
        $vueltas = 0;

        while (RateLimiter::tooManyAttempts($clave, $maximo)) {
            if (++$vueltas > 30) {
                throw new LimiteRitmoExcedido(
                    "Límite de ritmo persistente en {$clave}; hay que reintentar más tarde."
                );
            }

            usleep(500_000);
        }

        RateLimiter::hit($clave, 60);
    }

    private function hostDe(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
