<?php

namespace App\Console\Commands;

use App\Models\HarvestArea;
use App\Models\Lead;
use App\Jobs\RunSearchJob;
use App\Services\HarvestControl;
use App\Services\HarvestHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HarvestResetAllCommand extends Command
{
    protected $signature = 'harvest:reset-all
                            {--no-run : No lanza harvest:run al terminar}
                            {--force : Sin confirmación interactiva}';

    protected $description = 'Borra leads, colas y progreso de cosecha; deja las 52 áreas pendientes y reanuda';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('¿Borrar TODOS los leads y reiniciar la cosecha desde cero?', false)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $leads = Lead::query()->count();

        $this->info("Eliminando {$leads} leads…");
        Lead::query()->delete();

        // Por si quedaran filas huérfanas / ruido.
        Lead::query()->where(function ($q): void {
            $q->whereNull('email')->orWhere('email', '');
        })->delete();

        $this->info('Reiniciando áreas de cosecha…');
        Artisan::call('db:seed', ['--class' => 'HarvestAreaSeeder', '--force' => true]);

        $this->vaciarColas();

        $this->info('Limpiando caché de cosecha y jobs…');
        Cache::forget(HarvestControl::CACHE_ENABLED);
        Cache::forget(HarvestControl::CACHE_LAST_FINISHED);
        Cache::forget(HarvestHeartbeat::CACHE_KEY);
        Cache::forget('outreach:search_running');
        Cache::forget('outreach:search_started_at');
        Cache::forget('outreach:search_finished_at');
        Cache::forget('outreach:send_running');
        Cache::lock(HarvestControl::LOCK_KEY)->forceRelease();

        foreach (HarvestArea::query()->pluck('id') as $areaId) {
            Cache::forget("harvest:area:{$areaId}:lead_ids");
        }

        HarvestControl::resume();
        HarvestHeartbeat::touch('harvest:reset-all');

        $this->info('Cosecha reanudada. Áreas pendientes: '.HarvestArea::query()->where('status', HarvestArea::STATUS_PENDIENTE)->count());

        if (! $this->option('no-run')) {
            $this->info('Encolando harvest:run (Madrid primero)…');
            RunSearchJob::dispatch();
            $this->line('Job encolado; el worker de cola lo ejecutará en segundos.');
        }

        $this->newLine();
        $this->info('Reset completo. El scheduler seguirá con el recorrido automáticamente.');

        return self::SUCCESS;
    }

    private function vaciarColas(): void
    {
        if (Schema::hasTable('jobs')) {
            DB::table('jobs')->delete();
        }

        if (Schema::hasTable('job_batches')) {
            DB::table('job_batches')->delete();
        }

        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->delete();
        }
    }
}
