<?php

namespace App\Console\Commands;

use App\Models\HarvestArea;
use App\Services\AreaHarvestService;
use App\Services\HarvestControl;
use App\Services\HarvestHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HarvestRunCommand extends Command
{
    protected $signature = 'harvest:run
                            {--negocios : Incluye también filters_negocios}';

    protected $description = 'Cosecha la siguiente HarvestArea pendiente (Overpass + batch scrape)';

    public function handle(AreaHarvestService $harvest): int
    {
        HarvestHeartbeat::touch('harvest:run');

        if (! HarvestControl::isEnabled()) {
            $this->warn('Cosecha pausada. Reactiva con: php artisan harvest:resume');
            Log::info('harvest:run omitido: pausado');

            return self::SUCCESS;
        }

        $lockSeconds = max(60, (int) config('outreach.harvest.lock_seconds', 900));
        $lock = Cache::lock(HarvestControl::LOCK_KEY, $lockSeconds);

        if (! $lock->get()) {
            $this->warn('Otra ejecución de harvest:run está en curso (lock).');
            Log::info('harvest:run omitido: lock ocupado');

            return self::SUCCESS;
        }

        try {
            // Resistencia a reinicios: un área puede quedar "en_proceso" si el
            // contenedor cayó a mitad. Si su lote de scrape sigue vivo, esperamos
            // (la cola en DB reanuda sola); si está atascada, se recupera.
            if ($this->hayAreaEnProcesoActiva()) {
                HarvestHeartbeat::touch('harvest:run:waiting_scrape');
                $this->info('Hay un área en proceso (scrape del lote en curso); no se solapa.');

                return self::SUCCESS;
            }

            if (HarvestControl::isPauseBetweenAreasActive()) {
                $this->info('Pausa entre áreas activa; se reintentará en la próxima ejecución.');

                return self::SUCCESS;
            }

            $area = HarvestArea::nextPending();

            if ($area === null) {
                $this->info('Recorrido completo: no quedan áreas pendientes.');
                Log::info('harvest:run: recorrido completo');

                return self::SUCCESS;
            }

            $this->info("Cosechando [{$area->name}] (priority {$area->priority})…");
            HarvestHeartbeat::touch('harvest:run:'.$area->name);

            try {
                $stats = $harvest->harvest(
                    $area,
                    includeNegocios: (bool) $this->option('negocios'),
                );

                HarvestHeartbeat::touch('harvest:run:done');

                $this->info(sprintf(
                    'Área %s: %d leads, %d scrapes en lote%s.',
                    $area->name,
                    $stats['leads_created'],
                    $stats['jobs_dispatched'],
                    $stats['batch_id'] !== null ? " (batch {$stats['batch_id']})" : ' (sin scrape; marcada hecha)',
                ));
            } catch (\Throwable $e) {
                // AreaHarvestService ya marcó 'error'; la siguiente ejecución coge otra área.
                $this->error("Área [{$area->name}] falló: {$e->getMessage()}");
                Log::error('harvest:run: error de área', [
                    'area' => $area->name,
                    'error' => $e->getMessage(),
                ]);
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * ¿Hay algún área "en_proceso" con scrape realmente en curso?
     *
     * Recupera las áreas atascadas (reinicio a mitad): finaliza las cuyo lote ya
     * terminó pero no llegó a marcarse, y reintenta las que quedaron sin lote y
     * llevan demasiado tiempo. Devuelve true solo si queda scrape activo real.
     */
    private function hayAreaEnProcesoActiva(): bool
    {
        $enProceso = HarvestArea::query()
            ->where('status', HarvestArea::STATUS_EN_PROCESO)
            ->get();

        if ($enProceso->isEmpty()) {
            return false;
        }

        $staleSeconds = max(300, (int) config('outreach.harvest.stale_area_seconds', 1800));
        $activa = false;

        foreach ($enProceso as $area) {
            $batch = $this->batchDeArea($area->id);

            // Lote de scrape vivo: la cola en DB lo reanuda tras un reinicio.
            if ($batch !== null
                && $batch->finished_at === null
                && $batch->cancelled_at === null
                && (int) $batch->pending_jobs > 0
            ) {
                $activa = true;

                continue;
            }

            // El lote acabó pero el callback "finally" no llegó a cerrar el área.
            if ($batch !== null && ($batch->finished_at !== null || $batch->cancelled_at !== null)) {
                app(AreaHarvestService::class)->finalizeArea($area->id, []);
                Log::warning('harvest:run: área cerrada por recuperación (lote terminado)', [
                    'area' => $area->name,
                ]);

                continue;
            }

            // Sin lote (caída en fase Overpass o lote perdido/podado).
            $stale = $area->started_at === null
                || $area->started_at->copy()->addSeconds($staleSeconds)->isPast();

            if ($stale) {
                $area->resetToPending();
                Log::warning('harvest:run: área reiniciada por atasco (sin lote y stale)', [
                    'area' => $area->name,
                ]);

                continue;
            }

            // Arranque reciente sin lote todavía: dale margen.
            $activa = true;
        }

        return $activa;
    }

    /**
     * Última fila de job_batches del área (nombre "harvest-area-{id}"), o null.
     */
    private function batchDeArea(int $areaId): ?object
    {
        try {
            $connection = config('queue.batching.database');
            $table = (string) config('queue.batching.table', 'job_batches');

            return DB::connection($connection)
                ->table($table)
                ->where('name', "harvest-area-{$areaId}")
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
