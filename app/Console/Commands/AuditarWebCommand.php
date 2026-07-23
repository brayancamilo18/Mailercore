<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Auditoria\MotorAuditoria;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class AuditarWebCommand extends Command
{
    protected $signature = 'auditar:web
                            {--sector=}
                            {--limite=}
                            {--reauditar}
                            {--lead=}';

    protected $description = 'Audita sitios web de leads con páginas capturadas';

    public function handle(MotorAuditoria $motor): int
    {
        $query = $this->consultaBase();

        $limite = $this->option('limite') !== null
            ? max(1, (int) $this->option('limite'))
            : null;

        $totalEstimado = $limite !== null
            ? min($limite, (clone $query)->count())
            : (clone $query)->count();

        if ($totalEstimado === 0) {
            $this->info('No hay leads pendientes de auditar.');

            return self::SUCCESS;
        }

        $barra = $this->output->createProgressBar($totalEstimado);
        $barra->start();

        $procesados = 0;
        $auditoriasIds = [];
        /** @var array<string, array{total: int, suma: int, max: int}> $porSector */
        $porSector = [];
        /** @var array<string, int> $conteoHallazgos */
        $conteoHallazgos = [];

        $query->orderBy('id')->chunkById(200, function ($leads) use (
            $motor,
            $limite,
            &$procesados,
            &$auditoriasIds,
            &$porSector,
            &$conteoHallazgos,
            $barra,
        ): bool {
            foreach ($leads as $lead) {
                if ($limite !== null && $procesados >= $limite) {
                    return false;
                }

                /** @var Lead $lead */
                $auditoria = $motor->auditar($lead);

                if ($auditoria === null) {
                    $barra->advance();
                    $procesados++;

                    continue;
                }

                if (in_array($lead->estado, ['nuevo', 'rastreado'], true)) {
                    $lead->update(['estado' => 'auditado']);
                }

                $auditoriasIds[] = $auditoria->id;
                $sector = $lead->sector ?? 'sin clasificar';

                if (! isset($porSector[$sector])) {
                    $porSector[$sector] = ['total' => 0, 'suma' => 0, 'max' => 0];
                }

                $porSector[$sector]['total']++;
                $porSector[$sector]['suma'] += (int) $auditoria->puntuacion;
                $porSector[$sector]['max'] = max($porSector[$sector]['max'], (int) $auditoria->puntuacion);

                foreach ($auditoria->hallazgos ?? [] as $hallazgo) {
                    $codigo = $hallazgo['codigo'] ?? null;
                    if (! is_string($codigo) || $codigo === '') {
                        continue;
                    }
                    $conteoHallazgos[$codigo] = ($conteoHallazgos[$codigo] ?? 0) + 1;
                }

                $barra->advance();
                $procesados++;
            }

            return $limite === null || $procesados < $limite;
        });

        $barra->finish();
        $this->newLine(2);

        $this->mostrarResumen($porSector, $conteoHallazgos, count($auditoriasIds));

        return self::SUCCESS;
    }

    private function consultaBase(): Builder
    {
        $query = Lead::query()->whereHas('paginas');

        if ($this->option('lead') !== null) {
            $query->where('id', (int) $this->option('lead'));
        }

        if ($this->option('sector')) {
            $query->where('sector', $this->option('sector'));
        }

        if (! $this->option('reauditar')) {
            $query->where(function (Builder $q): void {
                $q->whereDoesntHave('auditoria')
                    ->orWhereRaw(
                        '(select auditada_at from auditorias where auditorias.lead_id = leads.id limit 1)
                         < (select max(capturada_at) from paginas where paginas.lead_id = leads.id)'
                    );
            });
        }

        return $query;
    }

    /**
     * @param  array<string, array{total: int, suma: int, max: int}>  $porSector
     * @param  array<string, int>  $conteoHallazgos
     */
    private function mostrarResumen(array $porSector, array $conteoHallazgos, int $totalAuditados): void
    {
        $this->info("RESUMEN FINAL — {$totalAuditados} leads auditados");
        $this->newLine();

        $filasSector = [];
        ksort($porSector);
        foreach ($porSector as $sector => $datos) {
            $media = $datos['total'] > 0
                ? round($datos['suma'] / $datos['total'], 1)
                : 0;

            $filasSector[] = [$sector, $datos['total'], $media, $datos['max']];
        }

        $this->table(
            ['Sector', 'Auditados', 'Puntuación media', 'Puntuación máx.'],
            $filasSector
        );

        arsort($conteoHallazgos);
        $top = array_slice($conteoHallazgos, 0, 15, true);

        $filasHallazgos = [];
        foreach ($top as $codigo => $cuantos) {
            $porcentaje = $totalAuditados > 0
                ? round(($cuantos / $totalAuditados) * 100, 1)
                : 0;

            $filasHallazgos[] = [$codigo, $cuantos, $porcentaje.'%'];
        }

        $this->table(
            ['Hallazgo', 'Leads', '% del total'],
            $filasHallazgos
        );
    }
}
