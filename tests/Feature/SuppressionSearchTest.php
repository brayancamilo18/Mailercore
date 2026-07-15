<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Suppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuppressionSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un lead cuyo email está en suppressions no debe crearse al buscar.
     */
    public function test_lead_con_email_suprimido_no_se_crea(): void
    {
        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.areas' => [
                ['name' => 'Test City', 'admin_level' => 8],
            ],
            'outreach.overpass.filters' => [
                ['office', 'marketing'],
            ],
        ]);

        Suppression::query()->create([
            'email' => 'baja@agencia-test.com',
            'domain' => 'agencia-test.com',
            'reason' => 'baja',
            'created_at' => now(),
        ]);

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 999001,
                        'tags' => [
                            'name' => 'Agencia Suprimida SL',
                            'email' => 'baja@agencia-test.com',
                            'website' => 'https://www.agencia-test.com',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('agencies:search')
            ->assertSuccessful();

        $this->assertDatabaseMissing('leads', [
            'email' => 'baja@agencia-test.com',
        ]);

        $this->assertSame(0, Lead::query()->count());
    }

    /**
     * Un lead con email limpio sí se crea.
     */
    public function test_lead_con_email_valido_se_crea(): void
    {
        // Acota Overpass en tests: 1 área × 1 filtro, sin pausas.
        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.areas' => [
                ['name' => 'Test City', 'admin_level' => 8],
            ],
            'outreach.overpass.filters' => [
                ['office', 'marketing'],
            ],
            'outreach.verifier.smtp_probe' => false,
        ]);

        // Evita dependencia de DNS real en CI/sandbox.
        \Illuminate\Support\Facades\Cache::put('outreach:mx:gmail.com', true, now()->addDay());

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 999002,
                        'tags' => [
                            'name' => 'Agencia Válida SL',
                            'email' => 'Hola@gmail.com',
                            'website' => 'https://nueva-agencia-unica-test.com',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('agencies:search')
            ->assertSuccessful();

        $this->assertDatabaseHas('leads', [
            'email' => 'hola@gmail.com',
            'name' => 'Agencia Válida SL',
            'status' => 'nuevo',
            'email_check' => 'valido',
            'segmento' => 'agencia',
        ]);
    }
}
