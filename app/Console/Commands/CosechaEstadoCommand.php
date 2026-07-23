<?php

namespace App\Console\Commands;

use App\Models\AreaCosecha;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CosechaEstadoCommand extends Command
{
    protected $signature = 'cosecha:estado';

    protected $description = 'Muestra el estado de las áreas de cosecha';

    public function handle(): int
    {
        $activa = Cache::get('cosecha:activa', true) !== false;
        $areas = AreaCosecha::query()->ordenadas()->get();
        $total = $areas->count();
        $hechas = $areas->where('estado', 'hecho')->count();
        $pct = $total > 0 ? round(($hechas / $total) * 100, 1) : 0;

        $filas = $areas->map(fn (AreaCosecha $a): array => [
            $a->nombre,
            $a->estado,
            $a->leads_encontrados,
            $a->emails_encontrados,
            $a->prioridad,
        ])->all();

        $this->table(
            ['Área', 'Estado', 'Leads', 'Emails', 'Prioridad'],
            $filas
        );

        $edad = Latido::edad('cosecha');
        $edadTxt = $edad === null ? 'sin latido' : $edad.'s';

        $this->info("Avance: {$hechas}/{$total} ({$pct}%) | Cosecha: ".($activa ? 'activa' : 'pausada')." | Latido: {$edadTxt}");

        if ($activa && ! Latido::estaVivo('cosecha')) {
            $this->error('Latido de cosecha muerto.');

            return 2;
        }

        return self::SUCCESS;
    }
}
