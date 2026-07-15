<?php

namespace App\Console\Commands;

use App\Models\HarvestArea;
use Illuminate\Console\Command;

class HarvestAreasStatusCommand extends Command
{
    protected $signature = 'harvest:areas-status';

    protected $description = 'Muestra el estado del recorrido de áreas Overpass (provincias)';

    public function handle(): int
    {
        $areas = HarvestArea::query()->ordered()->get();

        if ($areas->isEmpty()) {
            $this->warn('No hay áreas. Ejecuta: php artisan db:seed --class=HarvestAreaSeeder');

            return self::SUCCESS;
        }

        $rows = $areas->map(fn (HarvestArea $area): array => [
            $area->priority,
            $area->name,
            $area->admin_level,
            HarvestArea::STATUSES[$area->status] ?? $area->status,
            $area->leads_found,
            $area->emails_found,
            $area->started_at?->format('d/m H:i') ?? '—',
            $area->finished_at?->format('d/m H:i') ?? '—',
            $area->last_error !== null
                ? \Illuminate\Support\Str::limit($area->last_error, 40)
                : '—',
        ])->all();

        $this->table(
            ['Pri', 'Área', 'Lvl', 'Estado', 'Leads', 'Emails', 'Inicio', 'Fin', 'Error'],
            $rows
        );

        $counts = $areas->groupBy('status')->map->count();

        $this->newLine();
        $this->info(sprintf(
            'Resumen: %d pendiente · %d en proceso · %d hecho · %d error (total %d)',
            $counts[HarvestArea::STATUS_PENDIENTE] ?? 0,
            $counts[HarvestArea::STATUS_EN_PROCESO] ?? 0,
            $counts[HarvestArea::STATUS_HECHO] ?? 0,
            $counts[HarvestArea::STATUS_ERROR] ?? 0,
            $areas->count(),
        ));

        $next = HarvestArea::nextPending();
        if ($next !== null) {
            $this->line("Siguiente: {$next->name} (priority {$next->priority})");
        } else {
            $this->line('Siguiente: ninguna pendiente.');
        }

        return self::SUCCESS;
    }
}
