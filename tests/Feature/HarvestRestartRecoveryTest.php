<?php

namespace Tests\Feature;

use App\Models\HarvestArea;
use App\Models\Lead;
use App\Services\HarvestControl;
use App\Services\HarvestHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HarvestRestartRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        HarvestControl::resume();

        config([
            'outreach.harvest.enabled' => true,
            'outreach.harvest.pause_between_areas_seconds' => 0,
            'outreach.harvest.stale_area_seconds' => 900,
            'outreach.harvest.overpass_delay' => 0,
            'outreach.harvest.include_negocios' => false,
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.filters' => [['office', 'marketing']],
            'outreach.overpass.filters_negocios' => [],
            'outreach.verifier.smtp_probe' => false,
        ]);
    }

    /**
     * Un área atascada en 'en_proceso' sin lote y antigua se recupera y no bloquea.
     */
    public function test_area_atascada_en_proceso_se_recupera(): void
    {
        // Simula reinicio: área quedó en_proceso hace 1h, sin lote de scrape.
        $area = HarvestArea::query()->create([
            'name' => 'Área Atascada',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_EN_PROCESO,
            'priority' => 1,
            'started_at' => now()->subHour(),
        ]);

        // Overpass vacío: al reintentar, el área se cierra como 'hecho'.
        Http::fake(['*' => Http::response(['elements' => []], 200)]);

        $this->artisan('harvest:run')->assertSuccessful();

        $this->assertNotSame(
            HarvestArea::STATUS_EN_PROCESO,
            $area->fresh()->status,
            'El área atascada debe dejar de estar en_proceso.'
        );
        $this->assertSame(HarvestArea::STATUS_HECHO, $area->fresh()->status);
    }

    /**
     * Un área en_proceso reciente (arranque en curso) NO se reinicia todavía.
     */
    public function test_area_en_proceso_reciente_no_se_reinicia(): void
    {
        $area = HarvestArea::query()->create([
            'name' => 'Área Reciente',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_EN_PROCESO,
            'priority' => 1,
            'started_at' => now()->subSeconds(30),
        ]);

        Http::fake(['*' => Http::response(['elements' => []], 200)]);

        $this->artisan('harvest:run')
            ->assertSuccessful()
            ->expectsOutputToContain('en proceso');

        $this->assertSame(HarvestArea::STATUS_EN_PROCESO, $area->fresh()->status);
    }

    /**
     * Reintentar un área no duplica leads ya creados (dedup por place_id).
     */
    public function test_reintento_no_duplica_leads_por_place_id(): void
    {
        Cache::put('outreach:mx:agencia-dedup.test', true, now()->addDay());

        // Lead ya existente de una pasada previa (mismo place_id que devolverá Overpass).
        Lead::query()->create([
            'place_id' => 'node/5551',
            'name' => 'Agencia Dedup',
            'website' => 'https://agencia-dedup.test',
            'email' => 'hola@agencia-dedup.test',
            'email_check' => 'valido',
            'status' => 'nuevo',
            'segmento' => 'agencia',
            'captured_at' => now(),
        ]);

        $area = HarvestArea::query()->create([
            'name' => 'Área Dedup',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 1,
        ]);

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 5551,
                        'tags' => [
                            'name' => 'Agencia Dedup',
                            'email' => 'hola@agencia-dedup.test',
                            'website' => 'https://agencia-dedup.test',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('harvest:run')->assertSuccessful();

        $this->assertSame(1, Lead::query()->where('place_id', 'node/5551')->count());
        $this->assertSame(HarvestArea::STATUS_HECHO, $area->fresh()->status);
    }

    /**
     * Al procesar un área mockeada, heartbeat y panel reflejan el estado real.
     */
    public function test_heartbeat_y_panel_reflejan_area_procesada(): void
    {
        Cache::put('outreach:mx:panel-real.test', true, now()->addDay());

        HarvestArea::query()->create([
            'name' => 'Área Panel',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 1,
        ]);

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 6001,
                        'tags' => [
                            'name' => 'Agencia Panel',
                            'email' => 'hola@panel-real.test',
                            'website' => 'https://panel-real.test',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('harvest:run')->assertSuccessful();

        // Heartbeat fresco tras procesar.
        $this->assertNotNull(HarvestHeartbeat::ageSeconds());
        $this->assertTrue(HarvestHeartbeat::isFresh(120));

        // Panel/JSON refleja el área hecha y el lead con email.
        $snapshot = $this->getJson(route('harvest.status'))->assertOk()->json();

        $this->assertSame(1, $snapshot['areas_hechas']);
        $this->assertSame(1, $snapshot['leads_total']);
        $this->assertNull($snapshot['area_en_proceso']);
        $this->assertGreaterThanOrEqual(100.0, (float) $snapshot['progress_percent']);
    }
}
