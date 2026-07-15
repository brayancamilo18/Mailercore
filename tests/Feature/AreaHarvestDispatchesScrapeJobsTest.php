<?php

namespace Tests\Feature;

use App\Jobs\ScrapeWebsiteJob;
use App\Models\HarvestArea;
use App\Models\Lead;
use App\Services\AreaHarvestService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AreaHarvestDispatchesScrapeJobsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Al cosechar un área simulada: N leads base + batch de N ScrapeWebsiteJob.
     */
    public function test_cosecha_area_crea_leads_y_despacha_scrape_jobs(): void
    {
        Bus::fake();

        config([
            'outreach.overpass.request_pause_ms' => 0,
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

        $this->assertSame(3, $stats['leads_created']);
        $this->assertSame(3, $stats['jobs_dispatched']);
        $this->assertSame(3, Lead::query()->count());
        $this->assertSame(3, Lead::query()->where('status', 'sin_email')->count());
        // El área queda en_proceso hasta que el batch de scrape termine.
        $this->assertSame(HarvestArea::STATUS_EN_PROCESO, $area->fresh()->status);

        Bus::assertBatched(function (PendingBatch $batch) use ($area): bool {
            return $batch->name === "harvest-area-{$area->id}"
                && $batch->jobs->count() === 3
                && $batch->jobs->every(fn ($job): bool => $job instanceof ScrapeWebsiteJob);
        });
    }

    /**
     * El comando agencies:search --area encola scrapes en batch (no en línea).
     */
    public function test_comando_search_area_despacha_jobs(): void
    {
        Bus::fake();

        config([
            'outreach.overpass.request_pause_ms' => 0,
            'outreach.overpass.filters' => [
                ['office', 'advertising_agency'],
            ],
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

        $this->assertSame(2, Lead::query()->count());
        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2);
    }
}
