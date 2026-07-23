<?php

namespace Tests\Unit;

use App\Excepciones\LimiteRitmoExcedido;
use App\Services\Soporte\LimitadorRitmo;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LimitadorRitmoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('scrape:global');
        RateLimiter::clear('scrape:host:ejemplo.es');
    }

    public function test_lanza_excepcion_al_superar_el_limite(): void
    {
        Config::set('outreach.scraper.peticiones_por_minuto', 1);
        Config::set('outreach.scraper.peticiones_por_dominio_por_minuto', 100);

        $limitador = new LimitadorRitmo;

        $limitador->esperar('https://ejemplo.es/');

        $this->expectException(LimiteRitmoExcedido::class);
        $limitador->esperar('https://ejemplo.es/');
    }
}
