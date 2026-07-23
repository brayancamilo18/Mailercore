<?php

namespace App\Services\Soporte;

use App\Excepciones\LimiteRitmoExcedido;
use Illuminate\Support\Facades\RateLimiter;

class LimitadorRitmo
{
    /**
     * Espera hasta poder hacer una petición al host de $url.
     *
     * @throws LimiteRitmoExcedido si tras esperar el límite sigue activo.
     */
    public function esperar(string $url): void
    {
        $host = $this->hostDe($url) ?? 'desconocido';

        $this->esperarClave('scrape:global',
            (int) config('outreach.scraper.peticiones_por_minuto'));
        $this->esperarClave('scrape:host:'.$host,
            (int) config('outreach.scraper.peticiones_por_dominio_por_minuto'));
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
