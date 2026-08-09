<?php

namespace Tests\Unit;

use App\Services\Soporte\LimitadorRitmo;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class LimitadorRitmoCrawlDelayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        RateLimiter::clear('scrape:global');
        RateLimiter::clear('scrape:host:ejemplo.es');
        config([
            // 60 peticiones/minuto por dominio: un intervalo propio de 1 s.
            'outreach.scraper.peticiones_por_minuto' => 600,
            'outreach.scraper.peticiones_por_dominio_por_minuto' => 60,
        ]);
    }

    public function test_espera_el_crawl_delay_si_es_mayor_que_nuestro_intervalo(): void
    {
        $limitador = new LimitadorRitmo;

        $limitador->esperar('https://ejemplo.es/', 10.0);
        $limitador->esperar('https://ejemplo.es/contacto', 10.0);

        // La primera petición no espera: no hay nada anterior que respetar.
        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn (CarbonInterval $espera): bool => $espera->totalSeconds > 9);
    }

    public function test_ignora_un_crawl_delay_menor_que_nuestro_intervalo(): void
    {
        $limitador = new LimitadorRitmo;

        $limitador->esperar('https://ejemplo.es/', 0.5);
        $limitador->esperar('https://ejemplo.es/contacto', 0.5);

        Sleep::assertNeverSlept();
    }

    public function test_sin_crawl_delay_no_hay_espera_extra(): void
    {
        $limitador = new LimitadorRitmo;

        $limitador->esperar('https://ejemplo.es/');
        $limitador->esperar('https://ejemplo.es/contacto');

        Sleep::assertNeverSlept();
    }
}
