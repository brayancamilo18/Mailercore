<?php

namespace Tests\Feature;

use App\Models\HarvestArea;
use App\Models\Lead;
use App\Services\HarvestControl;
use App\Services\HarvestHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HarvestStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(HarvestHeartbeat::CACHE_KEY);
        Cache::forget(HarvestControl::CACHE_ENABLED);
    }

    public function test_endpoint_json_devuelve_snapshot(): void
    {
        HarvestHeartbeat::touch('test');

        HarvestArea::query()->create([
            'name' => 'Madrid',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_HECHO,
            'priority' => 1,
            'leads_found' => 3,
            'emails_found' => 2,
            'finished_at' => now(),
        ]);
        HarvestArea::query()->create([
            'name' => 'Barcelona',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 2,
        ]);

        Lead::factory()->create(['email' => 'a@test.example', 'status' => 'nuevo']);

        $response = $this->getJson(route('harvest.status'));

        $response->assertOk()
            ->assertJsonPath('areas_hechas', 1)
            ->assertJsonPath('areas_total', 2)
            ->assertJsonPath('progress_percent', 50)
            ->assertJsonPath('leads_total', 1)
            ->assertJsonPath('heartbeat_ok', true)
            ->assertJsonStructure([
                'enabled',
                'area_en_proceso',
                'emails_hoy',
                'ultimas_areas',
            ]);
    }

    public function test_progreso_solo_cuenta_areas_hechas(): void
    {
        HarvestArea::query()->create([
            'name' => 'Madrid',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_HECHO,
            'priority' => 1,
            'leads_found' => 10,
            'finished_at' => now(),
        ]);
        HarvestArea::query()->create([
            'name' => 'Barcelona',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_EN_PROCESO,
            'priority' => 2,
            'started_at' => now(),
            'leads_found' => 170,
        ]);

        // 1 hecho de 2 → 50%. En proceso no infla el %.
        $this->getJson(route('harvest.status'))
            ->assertOk()
            ->assertJsonPath('progress_percent', 50);
    }

    public function test_area_hecha_con_contadores_cero_se_resincroniza_desde_leads(): void
    {
        $started = now()->subMinutes(10);
        $finished = now()->subMinutes(5);

        $area = HarvestArea::query()->create([
            'name' => 'Madrid',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_HECHO,
            'priority' => 1,
            'leads_found' => 0,
            'emails_found' => 0,
            'started_at' => $started,
            'finished_at' => $finished,
        ]);

        Lead::factory()->create([
            'captured_at' => $started->copy()->addMinute(),
            'email' => 'uno@madrid.test',
            'status' => 'nuevo',
        ]);
        Lead::factory()->create([
            'captured_at' => $started->copy()->addMinutes(2),
            'email' => 'dos@madrid.test',
            'status' => 'nuevo',
        ]);

        $response = $this->getJson(route('harvest.status'))->assertOk();

        $response->assertJsonPath('ultimas_areas.0.name', 'Madrid');
        $response->assertJsonPath('ultimas_areas.0.leads_found', 2);
        $response->assertJsonPath('ultimas_areas.0.emails_found', 2);

        $area->refresh();
        $this->assertSame(2, $area->leads_found);
        $this->assertSame(2, $area->emails_found);
    }

    public function test_dashboard_incluye_seccion_cosecha(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="harvest-panel"', false)
            ->assertSee('Cosecha');
    }

    public function test_comando_status_sale_ok_con_latido_fresco(): void
    {
        config(['outreach.harvest.enabled' => true]);
        HarvestControl::resume();
        HarvestHeartbeat::touch('cli');

        $this->artisan('harvest:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Cosecha');
    }

    public function test_comando_status_sale_error_si_latido_stale(): void
    {
        config([
            'outreach.harvest.enabled' => true,
            'outreach.harvest.heartbeat_stale_seconds' => 60,
        ]);
        HarvestControl::resume();

        Cache::put(HarvestHeartbeat::CACHE_KEY, [
            'at' => now()->subMinutes(15)->timestamp,
            'source' => 'old',
        ], now()->addDay());

        $this->artisan('harvest:status')
            ->assertExitCode(2);
    }

    public function test_comando_status_ok_si_pausado_aunque_stale(): void
    {
        config([
            'outreach.harvest.enabled' => true,
            'outreach.harvest.heartbeat_stale_seconds' => 60,
        ]);
        HarvestControl::pause();

        Cache::put(HarvestHeartbeat::CACHE_KEY, [
            'at' => now()->subHours(2)->timestamp,
            'source' => 'old',
        ], now()->addDay());

        $this->artisan('harvest:status')->assertSuccessful();
    }
}
