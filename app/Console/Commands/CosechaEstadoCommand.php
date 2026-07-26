<?php

namespace App\Console\Commands;

use App\Models\AreaCosecha;
use App\Models\PaisCosecha;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CosechaEstadoCommand extends Command
{
    protected $signature = 'cosecha:estado {--pais=}';

    protected $description = 'Muestra el estado de países y áreas de cosecha';

    public function handle(): int
    {
        $activa = Cache::get('cosecha:activa', true) !== false;

        $paises = PaisCosecha::query()->ordenados()->withCount('areas')->get();
        $this->table(
            ['País', 'Código', 'Estado', 'Ciclos', 'Áreas'],
            $paises->map(fn (PaisCosecha $p): array => [
                $p->nombre,
                $p->codigo,
                $p->completado() ? '✓ hecho' : $p->estado,
                $p->ciclos_completados.'/'.$p->max_ciclos,
                $p->areas_count,
            ])->all()
        );

        $query = AreaCosecha::query()->ordenadas();
        if ($this->option('pais')) {
            $query->delPais(strtoupper((string) $this->option('pais')));
        }

        $areas = $query->get();
        $total = $areas->count();
        $hechas = $areas->where('estado', 'hecho')->count();
        $pct = $total > 0 ? round(($hechas / $total) * 100, 1) : 0;

        $this->table(
            ['País', 'Área', 'Estado', 'Leads', 'Emails'],
            $areas->map(fn (AreaCosecha $a): array => [
                $a->pais_codigo,
                $a->nombre,
                $a->estado,
                $a->leads_encontrados,
                $a->emails_encontrados,
            ])->all()
        );

        $edad = Latido::edad('cosecha');
        $edadTxt = $edad === null ? 'sin latido' : $edad.'s';
        $hechos = $paises->filter(fn (PaisCosecha $p) => $p->completado())->count();

        $this->info("Países ✓ {$hechos}/{$paises->count()} | Áreas {$hechas}/{$total} ({$pct}%) | Cosecha: ".($activa ? 'activa' : 'pausada')." | Latido: {$edadTxt}");

        if ($activa && ! Latido::estaVivo('cosecha')) {
            $this->error('Latido de cosecha muerto.');

            return 2;
        }

        return self::SUCCESS;
    }
}
