<?php

namespace Tests\Feature;

use App\Excepciones\OverpassNoDisponible;
use App\Jobs\RastrearSitioJob;
use App\Models\AreaCosecha;
use App\Models\Lead;
use App\Services\Overpass\OverpassClient;
use App\Services\Overpass\ServicioCosecha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CosechaTest extends TestCase
{
    use RefreshDatabase;

    private function area(): AreaCosecha
    {
        return AreaCosecha::query()->create([
            'nombre' => 'Madrid',
            'admin_level' => 6,
            'estado' => 'pendiente',
            'prioridad' => 1,
        ]);
    }

    private function fixtureElements(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/json/overpass-restaurantes.json')),
            true
        )['elements'];
    }

    private function fakeOverpassOk(): void
    {
        Http::fake([
            '*' => Http::response(['elements' => $this->fixtureElements()], 200),
        ]);

        // Acelera: sin pausas entre filtros
        config([
            'outreach.overpass.pausa_peticion_ms' => 0,
            'outreach.overpass.endpoints' => ['https://overpass.test/api/interpreter'],
        ]);

        $this->app->forgetInstance(OverpassClient::class);
        $this->app->singleton(OverpassClient::class, fn () => new OverpassClient(config('outreach.overpass')));
    }

    public function test_cosecha_crea_leads_con_sector_ya_asignado(): void
    {
        $this->fakeOverpassOk();
        $area = $this->area();

        $servicio = $this->app->make(ServicioCosecha::class);
        $servicio->cosechar($area);

        $lead = Lead::query()->where('place_id', 'node/1001')->first();
        $this->assertNotNull($lead);
        $this->assertSame('hosteleria', $lead->sector);
        $this->assertSame('osm_filtro', $lead->clasificacion_metodo);
        $this->assertSame(100, $lead->clasificacion_confianza);
        $this->assertSame('amenity', $lead->osm_tag);
        $this->assertSame('restaurant', $lead->osm_valor);
    }

    public function test_omite_leads_sin_website(): void
    {
        $elementos = $this->fixtureElements();
        // way/2002 tiene website; node/1001 tiene website; node/3003 no name.
        // Quitamos website al node/1001 para forzar omisión.
        unset($elementos[0]['tags']['website']);

        Http::fake(['*' => Http::response(['elements' => $elementos], 200)]);
        config([
            'outreach.overpass.pausa_peticion_ms' => 0,
            'outreach.overpass.endpoints' => ['https://overpass.test/api/interpreter'],
        ]);
        $this->app->forgetInstance(OverpassClient::class);
        $this->app->singleton(OverpassClient::class, fn () => new OverpassClient(config('outreach.overpass')));

        $area = $this->area();
        $this->app->make(ServicioCosecha::class)->cosechar($area);

        $this->assertNull(Lead::query()->where('place_id', 'node/1001')->first());
        $this->assertNotNull(Lead::query()->where('place_id', 'way/2002')->first());
    }

    public function test_omite_place_id_duplicado(): void
    {
        $this->fakeOverpassOk();

        Lead::factory()->create([
            'place_id' => 'node/1001',
            'website' => 'https://otro.es',
            'website_dominio' => 'otro.es',
        ]);

        $area = $this->area();
        $this->app->make(ServicioCosecha::class)->cosechar($area);

        $this->assertSame(1, Lead::query()->where('place_id', 'node/1001')->count());
    }

    public function test_encola_rastreo_por_cada_lead(): void
    {
        Queue::fake();
        $this->fakeOverpassOk();

        $area = $this->area();
        $resultado = $this->app->make(ServicioCosecha::class)->cosechar($area);

        $this->assertGreaterThan(0, $resultado['encolados']);
        Queue::assertPushed(RastrearSitioJob::class, $resultado['encolados']);
        Queue::assertPushedOn('scraping', RastrearSitioJob::class);
    }

    public function test_marca_area_hecha_al_terminar(): void
    {
        $this->fakeOverpassOk();
        $area = $this->area();

        $this->app->make(ServicioCosecha::class)->cosechar($area);
        $area->refresh();

        $this->assertSame('hecho', $area->estado);
        $this->assertNotNull($area->finalizada_at);
    }

    public function test_marca_area_error_si_overpass_falla(): void
    {
        $mock = \Mockery::mock(OverpassClient::class);
        $mock->shouldReceive('buscarStream')
            ->once()
            ->andThrow(new OverpassNoDisponible('Ningún espejo respondió'));
        $this->app->instance(OverpassClient::class, $mock);

        $area = $this->area();

        try {
            $this->app->make(ServicioCosecha::class)->cosechar($area);
            $this->fail('Debía lanzar OverpassNoDisponible');
        } catch (OverpassNoDisponible) {
            // esperado
        }

        $area->refresh();
        $this->assertSame('error', $area->estado);
        $this->assertNotNull($area->ultimo_error);
    }

    public function test_no_arranca_si_esta_pausada(): void
    {
        Cache::forever('cosecha:activa', false);
        $this->fakeOverpassOk();
        $this->area();

        $this->artisan('cosecha:ejecutar')
            ->expectsOutputToContain('pausada')
            ->assertExitCode(0);

        $this->assertSame(0, Lead::query()->count());
    }
}
