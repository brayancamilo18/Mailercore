<?php

namespace App\Services;

use App\Jobs\ScrapeWebsiteJob;
use App\Models\HarvestArea;
use App\Models\Lead;
use App\Services\Sources\OverpassSource;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Cosecha Overpass de un área: crea leads base y encola scrape en un Job Batch.
 * El área pasa a 'hecho' cuando termina el lote (o al momento si no hay scrape).
 */
class AreaHarvestService
{
    public function __construct(
        private LeadCaptureService $capture,
    ) {
    }

    /**
     * Consulta Overpass, actualiza leads_found y despacha batch de ScrapeWebsiteJob.
     *
     * @return array{leads_created: int, jobs_dispatched: int, omitted: int, batch_id: ?string}
     */
    public function harvest(HarvestArea $area, bool $includeNegocios = false, bool $dryRun = false): array
    {
        HarvestHeartbeat::touch('area:start:'.$area->name);

        $area->update([
            'status' => HarvestArea::STATUS_EN_PROCESO,
            'started_at' => now(),
            'last_error' => null,
            'finished_at' => null,
        ]);

        $leadsCreated = 0;
        $omitted = 0;
        /** @var list<int> $leadIds */
        $leadIds = [];
        /** @var list<ScrapeWebsiteJob> $scrapeJobs */
        $scrapeJobs = [];

        try {
            $config = config('outreach.overpass');
            $config['areas'] = [
                ['name' => $area->name, 'admin_level' => $area->admin_level],
            ];

            $source = new OverpassSource(
                new OverpassClient($config),
                includeNegocios: $includeNegocios,
            );

            foreach ($source->fetch() as $candidate) {
                $result = $this->capture->createBase($candidate, $dryRun);

                if ($result['outcome'] === 'omitted') {
                    $omitted++;

                    continue;
                }

                if ($result['outcome'] === 'error') {
                    Log::warning('AreaHarvestService: error creando lead', [
                        'name' => $candidate->name,
                        'error' => $result['error'],
                    ]);

                    continue;
                }

                $leadsCreated++;

                if (isset($result['lead_id']) && is_int($result['lead_id'])) {
                    $leadIds[] = $result['lead_id'];
                }

                if (! $dryRun && ($result['needs_scrape'] ?? false) && isset($result['lead_id'])) {
                    $scrapeJobs[] = new ScrapeWebsiteJob($result['lead_id']);
                }
            }

            if ($dryRun) {
                $area->update([
                    'status' => HarvestArea::STATUS_PENDIENTE,
                    'started_at' => null,
                ]);

                return [
                    'leads_created' => $leadsCreated,
                    'jobs_dispatched' => count($scrapeJobs),
                    'omitted' => $omitted,
                    'batch_id' => null,
                ];
            }

            $area->update([
                'leads_found' => $area->leads_found + $leadsCreated,
            ]);

            HarvestHeartbeat::touch('area:overpass:'.$area->name);

            if ($scrapeJobs === []) {
                $this->finalizeArea($area->id, $leadIds);

                return [
                    'leads_created' => $leadsCreated,
                    'jobs_dispatched' => 0,
                    'omitted' => $omitted,
                    'batch_id' => null,
                ];
            }

            $areaId = $area->id;

            $batch = Bus::batch($scrapeJobs)
                ->name("harvest-area-{$areaId}")
                ->onQueue('scraping')
                ->allowFailures()
                ->finally(function (Batch $batch) use ($areaId, $leadIds): void {
                    app(AreaHarvestService::class)->finalizeArea($areaId, $leadIds);
                })
                ->dispatch();

            return [
                'leads_created' => $leadsCreated,
                'jobs_dispatched' => count($scrapeJobs),
                'omitted' => $omitted,
                'batch_id' => $batch->id,
            ];
        } catch (\Throwable $e) {
            report($e);
            $area->update([
                'status' => HarvestArea::STATUS_ERROR,
                'last_error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            HarvestControl::markAreaFinished();

            throw $e;
        }
    }

    /**
     * Tras el lote de scrape (o si no hubo jobs): emails_found + estado hecho.
     *
     * @param  list<int>  $leadIds
     */
    public function finalizeArea(int $areaId, array $leadIds): void
    {
        $area = HarvestArea::query()->find($areaId);

        if ($area === null) {
            return;
        }

        // Solo finaliza si sigue en proceso (evita sobrescribir error/hecho).
        if ($area->status !== HarvestArea::STATUS_EN_PROCESO) {
            return;
        }

        $emailsNuevos = 0;

        if ($leadIds !== []) {
            $emailsNuevos = Lead::query()
                ->whereIn('id', $leadIds)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->count();
        }

        $area->update([
            'status' => HarvestArea::STATUS_HECHO,
            'emails_found' => $area->emails_found + $emailsNuevos,
            'finished_at' => now(),
            'last_error' => null,
        ]);

        HarvestControl::markAreaFinished();
        HarvestHeartbeat::touch('area:finalized:'.$area->name);

        Log::info('AreaHarvestService: área finalizada', [
            'area' => $area->name,
            'leads' => count($leadIds),
            'emails' => $emailsNuevos,
        ]);
    }
}
