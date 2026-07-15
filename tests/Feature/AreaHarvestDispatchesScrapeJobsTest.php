<?php

namespace Tests\Feature;

use App\Jobs\ScrapeWebsiteJob;
use App\Models\HarvestArea;
use App\Models\Lead;
use App\Services\AreaHarvestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AreaHarvestDispatchesScrapeJobsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sin email en OSM: no crea leads base; encola scrape al vuelo.
     */
    public function test_cosecha_area_no_crea_leads_sin_email_y_despacha_scrape_jobs(): void
    {
        Bus::fake();

        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.harvest.overpass_delay' => 0,
            'outreach.harvest.include_negocios' => false,
            'outreach.overpass.filters' => [
                ['office', 'marketing'],
            ],
            'outreach.overpass.filters_negocios' => [],
            'outreach.verifier.smtp_probe' => false,
        ]);

        $area = HarvestArea::query()->create([
            'name' => 'Área Test Scrape',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 1,
        ]);

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 700001,
                        'tags' => [
                            'name' => 'Agencia Uno',
                            'website' => 'https://agencia-uno-scrape.test',
                        ],
                    ],
                    [
                        'type' => 'node',
                        'id' => 700002,
                        'tags' => [
                            'name' => 'Agencia Dos',
                            'website' => 'https://agencia-dos-scrape.test',
                        ],
                    ],
                    [
                        'type' => 'node',
                        'id' => 700003,
                        'tags' => [
                            'name' => 'Agencia Tres',
                            'website' => 'https://agencia-tres-scrape.test',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $stats = app(AreaHarvestService::class)->harvest($area);

        $this->assertSame(0, $stats['leads_created']);
        $this->assertSame(3, $stats['jobs_dispatched']);
        $this->assertSame(0, Lead::query()->count());
        $this->assertSame(HarvestArea::STATUS_EN_PROCESO, $area->fresh()->status);

        Bus::assertDispatched(ScrapeWebsiteJob::class, 3);
    }

    /**
     * Email en OSM: se persiste al momento sin encolar scrape.
     */
    public function test_cosecha_area_con_email_en_fuente_crea_lead_directo(): void
    {
        Bus::fake();

        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.harvest.overpass_delay' => 0,
            'outreach.harvest.include_negocios' => false,
            'outreach.overpass.filters' => [['office', 'marketing']],
            'outreach.overpass.filters_negocios' => [],
            'outreach.verifier.smtp_probe' => false,
        ]);

        \Illuminate\Support\Facades\Cache::put('outreach:mx:directo-harvest.test', true, now()->addDay());

        $area = HarvestArea::query()->create([
            'name' => 'Área Email Directo',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 1,
        ]);

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 710001,
                        'tags' => [
                            'name' => 'Agencia Directa',
                            'email' => 'hola@directo-harvest.test',
                            'website' => 'https://directo-harvest.test',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $stats = app(AreaHarvestService::class)->harvest($area);

        $this->assertSame(1, $stats['leads_created']);
        $this->assertSame(0, $stats['jobs_dispatched']);
        $this->assertSame(1, Lead::withEmail()->count());
        Bus::assertNothingDispatched();
        $this->assertSame(HarvestArea::STATUS_HECHO, $area->fresh()->status);
        $this->assertSame(1, $area->fresh()->leads_found);
    }

    /**
     * El comando agencies:search --area encola scrapes al vuelo.
     */
    public function test_comando_search_area_despacha_jobs(): void
    {
        Bus::fake();

        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.harvest.overpass_delay' => 0,
            'outreach.harvest.include_negocios' => false,
            'outreach.overpass.filters' => [
                ['office', 'advertising_agency'],
            ],
            'outreach.overpass.filters_negocios' => [],
            'outreach.verifier.smtp_probe' => false,
        ]);

        HarvestArea::query()->create([
            'name' => 'Ciudad CLI',
            'admin_level' => 8,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 1,
        ]);

        Http::fake([
            '*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 800001,
                        'tags' => [
                            'name' => 'Lead CLI',
                            'website' => 'https://lead-cli-scrape.test',
                        ],
                    ],
                    [
                        'type' => 'node',
                        'id' => 800002,
                        'tags' => [
                            'name' => 'Lead CLI 2',
                            'website' => 'https://lead-cli-2-scrape.test',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('agencies:search', ['--area' => 'Ciudad CLI'])
            ->assertSuccessful();

        $this->assertSame(0, Lead::query()->count());
        Bus::assertDispatched(ScrapeWebsiteJob::class, 2);
    }
}
