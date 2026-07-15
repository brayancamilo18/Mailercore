<?php

namespace Tests\Unit;

use App\Services\OverpassClient;
use App\Services\Sources\LeadCandidate;
use App\Services\Sources\LeadSourceManager;
use App\Services\Sources\OverpassSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadSourceTest extends TestCase
{
    public function test_overpass_source_mapea_a_dto_con_external_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 42,
                        'tags' => [
                            'name' => 'Studio Test',
                            'website' => 'https://studio-test.example',
                            'email' => 'hola@studio-test.example',
                            'phone' => '+34600000000',
                            'addr:city' => 'Madrid',
                        ],
                    ],
                ],
            ], 200),
        ]);

        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.areas' => [['name' => 'Test', 'admin_level' => 8]],
            'outreach.overpass.filters' => [['office', 'marketing']],
        ]);

        $source = new OverpassSource(new OverpassClient(config('outreach.overpass')));
        $candidates = iterator_to_array($source->fetch());

        $this->assertCount(1, $candidates);
        $this->assertInstanceOf(LeadCandidate::class, $candidates[0]);
        $this->assertSame('Studio Test', $candidates[0]->name);
        $this->assertSame('overpass', $candidates[0]->source);
        $this->assertSame('node/42', $candidates[0]->externalId);
        $this->assertSame('hola@studio-test.example', $candidates[0]->email);
        $this->assertSame('agencia', $candidates[0]->segmento);
    }

    public function test_manager_solo_devuelve_fuentes_activas(): void
    {
        config([
            'outreach.sources' => [
                'overpass' => ['enabled' => true],
            ],
        ]);

        $active = (new LeadSourceManager)->active();

        $this->assertCount(1, $active);
        $this->assertSame('overpass', $active[0]->key());
    }

    public function test_overpass_client_api_search_sigue_intacta(): void
    {
        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'way',
                        'id' => 7,
                        'tags' => ['name' => 'Agencia API'],
                    ],
                ],
            ], 200),
        ]);

        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.areas' => [['name' => 'Test', 'admin_level' => 8]],
            'outreach.overpass.filters' => [['office', 'design']],
        ]);

        $rows = (new OverpassClient(config('outreach.overpass')))->search();

        $this->assertSame('way/7', $rows[0]['place_id']);
        $this->assertSame('Agencia API', $rows[0]['name']);
    }
}
