<?php

namespace Tests\Feature;

use App\Enums\PropositoRastreo;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Soporte\ComprobadorRobots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class RastreoDiagnosticoCommandTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://diagnostico-demo.es';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Sleep::fake();
        RateLimiter::clear('scrape:global');
        RateLimiter::clear('scrape:host:diagnostico-demo.es');
        config([
            'outreach.scraper.peticiones_por_minuto' => 1000,
            'outreach.scraper.peticiones_por_dominio_por_minuto' => 1000,
        ]);
        $this->app->forgetInstance(ComprobadorRobots::class);
    }

    public function test_simula_sin_escribir_paginas_ni_marcar_el_lead(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'robots.txt')) {
                return Http::response("User-agent: *\nAllow: /\n", 200);
            }

            $cuerpo = '<!DOCTYPE html><html><head><title>Bar Demo</title>'
                .'<meta name="viewport" content="width=device-width">'
                .'</head><body><h1>Bar Demo</h1>'
                .'<a href="/reserva-online">Reservar mesa</a>'
                .'<a href="mailto:info@diagnostico-demo.es">info</a>'
                .'</body></html>';

            if (str_contains($request->url(), '/reserva-online')) {
                $cuerpo = '<!DOCTYPE html><html><body><h1>Reservas</h1>'
                    .'<p>Reserva con CoverManager</p></body></html>';
            }

            return Http::response($cuerpo, 200, ['Content-Type' => 'text/html']);
        });

        $lead = Lead::factory()->create([
            'website' => self::BASE,
            'website_dominio' => 'diagnostico-demo.es',
            'sector' => 'hosteleria',
            'estado' => 'nuevo',
            'rastreado_at' => null,
        ]);

        $this->artisan('rastreo:diagnostico', [
            'lead' => (string) $lead->id,
            '--proposito' => 'auditoria',
        ])
            ->expectsOutputToContain('Rastreo diagnóstico')
            ->expectsOutputToContain('Fijas')
            ->expectsOutputToContain('Descubiertas')
            ->expectsOutputToContain('/reserva-online')
            ->expectsOutputToContain('Señales')
            ->expectsOutputToContain('Hallazgos')
            ->assertSuccessful();

        $this->assertSame(0, Pagina::query()->where('lead_id', $lead->id)->count());
        $this->assertNull($lead->fresh()->rastreado_at);
        $this->assertSame('nuevo', $lead->fresh()->estado);
        $this->assertDatabaseMissing('lead_emails', ['lead_id' => $lead->id]);
    }

    /** La tabla de señales dice por qué algo quedó en «no observado». */
    public function test_imprime_el_motivo_de_las_senales_no_observadas(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'robots.txt')) {
                return Http::response("User-agent: *\nAllow: /\n", 200);
            }

            if (rtrim($request->url(), '/') !== self::BASE) {
                return Http::response('No existe', 404, ['Content-Type' => 'text/html']);
            }

            return Http::response(
                '<!DOCTYPE html><html><head><title>Bar Demo</title></head>'
                .'<body><h1>Bar Demo</h1><a href="/nosotros">Quiénes somos</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            );
        });

        $lead = Lead::factory()->create([
            'website' => self::BASE,
            'website_dominio' => 'diagnostico-demo.es',
            'sector' => 'hosteleria',
            'estado' => 'nuevo',
        ]);

        $this->artisan('rastreo:diagnostico', [
            'lead' => (string) $lead->id,
            '--proposito' => 'auditoria',
        ])
            // Una sola expectativa por escritura: la fila de reservas lleva el
            // «no observado» y el motivo en la misma línea.
            ->expectsOutputToContain('no observado '.Pagina::MOTIVO_SIN_RUTA_SECTORIAL)
            ->assertSuccessful();
    }

    public function test_acepta_dominio_como_argumento(): void
    {
        Http::fake([
            'diagnostico-demo.es/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            '*' => Http::response(
                '<!DOCTYPE html><html><head><title>Ok</title></head><body>hola</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        Lead::factory()->create([
            'website' => self::BASE,
            'website_dominio' => 'diagnostico-demo.es',
            'sector' => 'retail',
            'estado' => 'nuevo',
        ]);

        $this->artisan('rastreo:diagnostico', [
            'lead' => 'diagnostico-demo.es',
            '--proposito' => PropositoRastreo::EMAIL->value,
        ])->assertSuccessful();
    }

    public function test_proposito_invalido_falla(): void
    {
        $lead = Lead::factory()->create([
            'website' => self::BASE,
            'website_dominio' => 'diagnostico-demo.es',
        ]);

        $this->artisan('rastreo:diagnostico', [
            'lead' => (string) $lead->id,
            '--proposito' => 'inventado',
        ])->assertFailed();
    }
}
