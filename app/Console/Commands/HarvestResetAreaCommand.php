<?php

namespace App\Console\Commands;

use App\Models\HarvestArea;
use Illuminate\Console\Command;

class HarvestResetAreaCommand extends Command
{
    protected $signature = 'harvest:reset-area
                            {name : Nombre exacto del área (p. ej. Madrid)}
                            {--admin-level= : Filtra por admin_level OSM si hay ambigüedad}';

    protected $description = 'Vuelve a marcar un área de cosecha como pendiente';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $adminLevel = $this->option('admin-level');

        $query = HarvestArea::query()->where('name', $name);

        if ($adminLevel !== null && $adminLevel !== '') {
            $query->where('admin_level', (int) $adminLevel);
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->error("No se encontró el área «{$name}».");

            return self::FAILURE;
        }

        if ($matches->count() > 1 && ($adminLevel === null || $adminLevel === '')) {
            $this->error('Hay varias áreas con ese nombre. Usa --admin-level=');
            foreach ($matches as $area) {
                $this->line("  - {$area->name} (admin_level {$area->admin_level}, status {$area->status})");
            }

            return self::FAILURE;
        }

        /** @var HarvestArea $area */
        $area = $matches->first();
        $area->resetToPending();

        $this->info("Área «{$area->name}» (lvl {$area->admin_level}) marcada como pendiente.");

        return self::SUCCESS;
    }
}
