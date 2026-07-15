<?php

namespace App\Services;

use App\Jobs\ScrapeWebsiteJob;
use App\Models\HarvestArea;
use App\Services\Sources\OverpassSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cosecha Overpass de un área en streaming: cada lead/email aparece en el panel al vuelo.
 * Los scrapes se encolan enseguida (en paralelo a Overpass) y el área se cierra al terminar.
 */
class AreaHarvestService
{
    public function __construct(
        private LeadCaptureService $capture,
    ) {
    }

    /**
     * @return array{leads_created: int, jobs_dispatched: int, omitted: int, batch_id: ?string}
     */
    public function harvest(HarvestArea $area, bool $includeNegocios = false, bool $dryRun = false): array
    {
        HarvestHeartbeat::touch('area:start:'.$area->name);

        $area->clearSessionLeadIds();
        $this->clearScrapeCounters($area->id);

        $area->update([
            'status' => HarvestArea::STATUS_EN_PROCESO,
            'started_at' => now(),
            'last_error' => null,
            'finished_at' => null,
            'leads_found' => 0,
            'emails_found' => 0,
        ]);

        $leadsCreated = 0;
        $omitted = 0;
        $jobsDispatched = 0;
        /** @var list<int> $leadIds */
        $leadIds = [];

        try {
            $config = config('outreach.overpass');
            $config['areas'] = $this->areasOverpassPara($area);

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

                if ($result['outcome'] === 'pending_scrape') {
                    if (! $dryRun) {
                        $this->enqueueScrape($area->id, $candidate->toArray());
                        $jobsDispatched++;
                    }

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
                    $area->rememberSessionLeadId($result['lead_id']);

                    // Persiste contadores cada 10 leads (evita locks SQLite); el panel cuenta en vivo.
                    if ($leadsCreated % 10 === 0) {
                        $area->syncStatsFromLeads($area->leadIdsDeSesionActual());
                    }
                }
            }

            if ($dryRun) {
                $area->update([
                    'status' => HarvestArea::STATUS_PENDIENTE,
                    'started_at' => null,
                ]);

                return [
                    'leads_created' => $leadsCreated,
                    'jobs_dispatched' => $jobsDispatched,
                    'omitted' => $omitted,
                    'batch_id' => null,
                ];
            }

            HarvestHeartbeat::touch('area:overpass:'.$area->name);
            Cache::put($this->overpassDoneKey($area->id), true, now()->addDays(2));

            // Si no quedan scrapes, cierra ya; si quedan, ScrapeWebsiteJob irá cerrando.
            $this->tryFinalizeArea($area->id, $leadIds);

            return [
                'leads_created' => $leadsCreated,
                'jobs_dispatched' => $jobsDispatched,
                'omitted' => $omitted,
                'batch_id' => null,
            ];
        } catch (\Throwable $e) {
            report($e);
            $area->update([
                'status' => HarvestArea::STATUS_ERROR,
                'last_error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $this->clearScrapeCounters($area->id);
            HarvestControl::markAreaFinished();

            throw $e;
        }
    }

    /**
     * Encola scrape al momento (workers trabajan mientras Overpass sigue).
     *
     * @param  array<string, mixed>  $candidatePayload
     */
    public function enqueueScrape(int $areaId, array $candidatePayload): void
    {
        $key = $this->scrapeTotalKey($areaId);
        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key);

        ScrapeWebsiteJob::dispatch($candidatePayload, $areaId);
    }

    /**
     * Un scrape terminó (con o sin email): intenta cerrar el área si Overpass acabó.
     */
    public function markScrapeFinished(int $areaId): void
    {
        $key = $this->scrapeDoneKey($areaId);
        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key);

        $area = HarvestArea::query()->find($areaId);
        if ($area !== null && $area->status === HarvestArea::STATUS_EN_PROCESO) {
            $done = (int) Cache::get($this->scrapeDoneKey($areaId), 0);
            // Menos escrituras a SQLite bajo carga de scrape concurrente.
            if ($done % 5 === 0) {
                $area->syncStatsFromLeads($area->leadIdsDeSesionActual());
            }
        }

        $this->tryFinalizeArea($areaId, []);
    }

    /**
     * Cierra el área si Overpass terminó y todos los scrapes encolados acabaron.
     *
     * @param  list<int>  $leadIds
     */
    public function tryFinalizeArea(int $areaId, array $leadIds = []): void
    {
        $lock = Cache::lock("harvest:finalize:{$areaId}", 15);

        if (! $lock->get()) {
            return;
        }

        try {
            if (! Cache::get($this->overpassDoneKey($areaId))) {
                return;
            }

            $total = (int) Cache::get($this->scrapeTotalKey($areaId), 0);
            $done = (int) Cache::get($this->scrapeDoneKey($areaId), 0);

            if ($done < $total) {
                return;
            }

            $this->finalizeArea($areaId, $leadIds);
        } finally {
            $lock->release();
        }
    }

    /**
     * ¿Quedan scrapes pendientes de esta área? (recuperación tras reinicio).
     */
    public function tieneScrapesPendientes(int $areaId): bool
    {
        $total = (int) Cache::get($this->scrapeTotalKey($areaId), 0);
        $done = (int) Cache::get($this->scrapeDoneKey($areaId), 0);

        return $total > 0 && $done < $total;
    }

    public function overpassHaTerminado(int $areaId): bool
    {
        return (bool) Cache::get($this->overpassDoneKey($areaId), false);
    }

    /**
     * @return list<array{name: string, admin_level: int}>
     */
    private function areasOverpassPara(HarvestArea $area): array
    {
        $expansions = config('outreach.harvest.area_expansions.'.$area->name);

        if (is_array($expansions) && $expansions !== []) {
            /** @var list<array{name: string, admin_level: int}> $expansions */
            return array_values($expansions);
        }

        return [
            ['name' => $area->name, 'admin_level' => (int) $area->admin_level],
        ];
    }

    /**
     * @param  list<int>  $leadIds
     * @return list<int>
     */
    private function resolveLeadIdsParaFinalizar(HarvestArea $area, array $leadIds): array
    {
        $merged = $leadIds;

        $cached = Cache::get($area->sessionLeadIdsCacheKey());
        if (is_array($cached)) {
            foreach ($cached as $id) {
                $merged[] = (int) $id;
            }
        }

        foreach ($area->leadIdsEnVentana() as $id) {
            $merged[] = $id;
        }

        return array_values(array_unique(array_filter($merged)));
    }

    /**
     * @param  list<int>  $leadIds
     */
    public function finalizeArea(int $areaId, array $leadIds): void
    {
        $area = HarvestArea::query()->find($areaId);

        if ($area === null) {
            return;
        }

        if ($area->status !== HarvestArea::STATUS_EN_PROCESO) {
            return;
        }

        $leadIds = $this->resolveLeadIdsParaFinalizar($area, $leadIds);

        $area->update([
            'status' => HarvestArea::STATUS_HECHO,
            'finished_at' => now(),
            'last_error' => null,
        ]);

        $area->syncStatsFromLeads($leadIds);
        $area->clearSessionLeadIds();
        $this->clearScrapeCounters($areaId);

        HarvestControl::markAreaFinished();
        HarvestHeartbeat::touch('area:finalized:'.$area->name);

        Log::info('AreaHarvestService: área finalizada', [
            'area' => $area->name,
            'leads' => $area->fresh()->leads_found,
            'emails' => $area->fresh()->emails_found,
        ]);
    }

    private function clearScrapeCounters(int $areaId): void
    {
        Cache::forget($this->scrapeTotalKey($areaId));
        Cache::forget($this->scrapeDoneKey($areaId));
        Cache::forget($this->overpassDoneKey($areaId));
    }

    private function scrapeTotalKey(int $areaId): string
    {
        return "harvest:scrape:{$areaId}:total";
    }

    private function scrapeDoneKey(int $areaId): string
    {
        return "harvest:scrape:{$areaId}:done";
    }

    private function overpassDoneKey(int $areaId): string
    {
        return "harvest:scrape:{$areaId}:overpass_done";
    }
}
