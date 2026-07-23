<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Suppression;
use App\Services\Clasificacion\ClasificadorSector;
use App\Services\Overpass\OverpassClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSectoresCommand extends Command
{
    protected $signature = 'leads:backfill-sectores
                            {--lote=300}
                            {--sin-overpass : No reconsulta OSM, solo reclasifica}
                            {--solo-sin-sector}
                            {--dry-run}';

    protected $description = 'Rellena tags OSM (opcional) y clasifica el sector de los leads';

    public function handle(ClasificadorSector $clasificador, OverpassClient $overpass): int
    {
        $lote = max(1, (int) $this->option('lote'));
        $dryRun = (bool) $this->option('dry-run');

        if (! $this->option('sin-overpass')) {
            $this->rellenarDesdeOverpass($overpass, $lote, $dryRun);
        }

        $query = Lead::query();
        if ($this->option('solo-sin-sector')) {
            $query->whereNull('sector');
        }

        $leads = $query->orderBy('id')->get();
        $barra = $this->output->createProgressBar($leads->count());
        $barra->start();

        foreach ($leads as $lead) {
            if (! $dryRun) {
                $clasificador->aplicar($lead);
            } else {
                $clasificador->clasificar($lead);
            }
            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);

        $this->mostrarResumen($dryRun);

        return self::SUCCESS;
    }

    private function rellenarDesdeOverpass(OverpassClient $overpass, int $lote, bool $dryRun): void
    {
        $ids = Lead::query()
            ->whereNotNull('place_id')
            ->where(function ($q): void {
                $q->whereNull('osm_tags_raw')
                    ->orWhere('osm_tags_raw', '=', '[]')
                    ->orWhere('osm_tags_raw', '=', '{}');
            })
            ->orderBy('id')
            ->pluck('place_id', 'id');

        if ($ids->isEmpty()) {
            $this->info('No hay leads pendientes de reconsultar en Overpass.');

            return;
        }

        $this->info("Reconsultando Overpass para {$ids->count()} leads...");
        $chunks = $ids->chunk($lote);
        $barra = $this->output->createProgressBar($chunks->count());
        $barra->start();

        foreach ($chunks as $chunk) {
            $porPlaceId = $overpass->buscarPorIds($chunk->values()->all());

            if (! $dryRun) {
                foreach ($chunk as $leadId => $placeId) {
                    $tags = $porPlaceId[$placeId] ?? null;
                    if (! is_array($tags)) {
                        continue;
                    }

                    $lat = $tags['_lat'] ?? null;
                    $lon = $tags['_lon'] ?? null;
                    unset($tags['_lat'], $tags['_lon']);

                    $datos = [
                        'osm_tags_raw' => $tags,
                        'updated_at' => now(),
                    ];

                    $lead = Lead::query()->find($leadId);
                    if ($lead === null) {
                        continue;
                    }

                    if ($lead->latitud === null && $lat !== null) {
                        $datos['latitud'] = $lat;
                    }
                    if ($lead->longitud === null && $lon !== null) {
                        $datos['longitud'] = $lon;
                    }
                    if (blank($lead->ciudad) && isset($tags['addr:city'])) {
                        $datos['ciudad'] = $tags['addr:city'];
                    }
                    if (blank($lead->codigo_postal) && isset($tags['addr:postcode'])) {
                        $datos['codigo_postal'] = $tags['addr:postcode'];
                    }
                    if (blank($lead->telefono)) {
                        $datos['telefono'] = $tags['phone'] ?? $tags['contact:phone'] ?? null;
                    }
                    if (blank($lead->website)) {
                        $web = $tags['website'] ?? $tags['contact:website'] ?? null;
                        if ($web) {
                            $datos['website'] = $web;
                            $datos['website_dominio'] = Suppression::dominioDeWeb($web);
                        }
                    }

                    Lead::query()->where('id', $leadId)->update($datos);
                }
            }

            $barra->advance();
            sleep(2);
        }

        $barra->finish();
        $this->newLine();
    }

    private function mostrarResumen(bool $dryRun): void
    {
        if ($dryRun) {
            $this->warn('Dry-run: no se ha escrito nada. Resumen sobre datos actuales:');
        }

        $porSector = Lead::query()
            ->select('sector', DB::raw('count(*) as total'))
            ->groupBy('sector')
            ->orderByDesc('total')
            ->get();

        $filas = [];
        foreach ($porSector as $fila) {
            $sector = $fila->sector ?? 'sin clasificar';
            $metodos = Lead::query()
                ->when(
                    $fila->sector === null,
                    fn ($q) => $q->whereNull('sector'),
                    fn ($q) => $q->where('sector', $fila->sector)
                )
                ->select('clasificacion_metodo', DB::raw('count(*) as total'))
                ->groupBy('clasificacion_metodo')
                ->pluck('total', 'clasificacion_metodo')
                ->all();

            $desglose = collect($metodos)
                ->map(fn ($n, $m) => ($m ?: '—').':'.$n)
                ->implode(', ');

            $filas[] = [$sector, $fila->total, $desglose];
        }

        $this->table(['Sector', 'Total', 'Métodos'], $filas);
    }
}
