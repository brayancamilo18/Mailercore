<?php

namespace Tests\Feature;

use App\Models\HarvestArea;
use App\Models\Lead;
use App\Services\HarvestControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HarvestRunCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Con 2 áreas pendientes, harvest:run procesa la de mayor prioridad y la deja hecha.
     */
    public function test_procesa_area_de_mayor_prioridad_y_la_marca_hecha(): void
    {
        config([
            'outreach.harvest.enabled' => true,
            'outreach.harvest.pause_between_areas_seconds' => 0,
            'outreach.harvest.overpass_delay' => 0,
            'outreach.harvest.include_negocios' => false,
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.filters' => [
                ['office', 'marketing'],
            ],
            'outreach.overpass.filters_negocios' => [],
            'outreach.verifier.smtp_probe' => false,
        ]);

        Cache::forget(HarvestControl::CACHE_ENABLED);
        Cache::forget(HarvestControl::CACHE_LAST_FINISHED);

        $prioridadAlta = HarvestArea::query()->create([
            'name' => 'Área Prioritaria',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 1,
        ]);

        $prioridadBaja = HarvestArea::query()->create([
            'name' => 'Área Secundaria',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 10,
        ]);

        // Email en OSM → sin scrape; finalize inmediato → hecho (cola sync en tests).
        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 910001,
                        'tags' => [
                            'name' => 'Agencia Prioritaria',
                            'email' => 'contacto@prioridad-harvest.test',
                            'website' => 'https://prioridad-harvest.test',
                        ],
                    ],
                    [
                        'type' => 'node',
                        'id' => 910002,
                        'tags' => [
                            'name' => 'Otra Agencia',
                            'email' => 'info@otra-harvest.test',
                            'website' => 'https://otra-harvest.test',
                        ],
                    ],
                ],
            ], 200),
        ]);

        \Illuminate\Support\Facades\Cache::put('outreach:mx:prioridad-harvest.test', true, now()->addDay());
        \Illuminate\Support\Facades\Cache::put('outreach:mx:otra-harvest.test', true, now()->addDay());

        $this->artisan('harvest:run')
            ->assertSuccessful()
            ->expectsOutputToContain('Área Prioritaria');

        $this->assertSame(HarvestArea::STATUS_HECHO, $prioridadAlta->fresh()->status);
        $this->assertNotNull($prioridadAlta->fresh()->finished_at);
        $this->assertSame(2, $prioridadAlta->fresh()->leads_found);
        $this->assertSame(2, $prioridadAlta->fresh()->emails_found);

        $this->assertSame(HarvestArea::STATUS_PENDIENTE, $prioridadBaja->fresh()->status);
        $this->assertSame(2, Lead::query()->count());
    }

    public function test_sin_areas_pendientes_loguea_recorrido_completo(): void
    {
        config([
            'outreach.harvest.enabled' => true,
            'outreach.harvest.pause_between_areas_seconds' => 0,
        ]);
        Cache::forget(HarvestControl::CACHE_ENABLED);

        $this->artisan('harvest:run')
            ->assertSuccessful()
            ->expectsOutputToContain('Recorrido completo');
    }

    public function test_pause_y_resume_controlan_el_flag(): void
    {
        Cache::forget(HarvestControl::CACHE_ENABLED);

        $this->artisan('harvest:pause')->assertSuccessful();
        $this->assertFalse(HarvestControl::isEnabled());

        $this->artisan('harvest:run')
            ->assertSuccessful()
            ->expectsOutputToContain('pausada');

        $this->artisan('harvest:resume')->assertSuccessful();
        $this->assertTrue(HarvestControl::isEnabled());
    }
}
