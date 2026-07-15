<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NegocioSegmentSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un lead capturado vía filters_negocios se guarda con segmento=negocio.
     */
    public function test_lead_de_filters_negocios_guarda_segmento_negocio(): void
    {
        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.areas' => [
                ['name' => 'Test City', 'admin_level' => 8],
            ],
            // Sin filtros de agencia: solo el grupo negocios.
            'outreach.overpass.filters' => [],
            'outreach.overpass.filters_negocios' => [
                ['shop', 'jewelry'],
            ],
            'outreach.verifier.smtp_probe' => false,
        ]);

        Cache::put('outreach:mx:joyeria-test.example', true, now()->addDay());

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 888001,
                        'tags' => [
                            'name' => 'Joyería Local Test',
                            'email' => 'hola@joyeria-test.example',
                            'website' => 'https://www.joyeria-test.example',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('agencies:search', ['--negocios' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('leads', [
            'name' => 'Joyería Local Test',
            'email' => 'hola@joyeria-test.example',
            'segmento' => 'negocio',
            'status' => 'nuevo',
        ]);

        $this->assertSame(1, Lead::query()->where('segmento', 'negocio')->count());
    }

    /**
     * Sin --negocios no se consultan filters_negocios.
     */
    public function test_sin_flag_negocios_no_captura_filters_negocios(): void
    {
        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.harvest.overpass_delay' => 0,
            'outreach.harvest.include_negocios' => false,
            'outreach.overpass.areas' => [
                ['name' => 'Test City', 'admin_level' => 8],
            ],
            'outreach.overpass.filters' => [],
            'outreach.overpass.filters_negocios' => [
                ['shop', 'jewelry'],
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 888002,
                        'tags' => [
                            'name' => 'Joyería Ignorada',
                            'website' => 'https://joyeria-ignorada.example',
                            'email' => 'info@joyeria-ignorada.example',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('agencies:search')
            ->assertSuccessful();

        $this->assertSame(0, Lead::query()->count());
    }

    /**
     * Negocio sin website no se captura aunque se active --negocios.
     */
    public function test_negocio_sin_web_se_descarta(): void
    {
        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.areas' => [
                ['name' => 'Test City', 'admin_level' => 8],
            ],
            'outreach.overpass.filters' => [],
            'outreach.overpass.filters_negocios' => [
                ['shop', 'clothes'],
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 888003,
                        'tags' => [
                            'name' => 'Tienda Sin Web',
                            'email' => 'info@sinweb.example',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('agencies:search', ['--negocios' => true])
            ->assertSuccessful();

        $this->assertSame(0, Lead::query()->count());
    }
}
