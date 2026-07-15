<?php

namespace App\Console\Commands;

use App\Models\HarvestArea;
use App\Services\HarvestStatusService;
use Illuminate\Console\Command;

class HarvestStatusCommand extends Command
{
    protected $signature = 'harvest:status
                            {--json : Salida JSON}';

    protected $description = 'Estado del recorrido de cosecha (vivacidad, avance, contadores)';

    public function handle(HarvestStatusService $status): int
    {
        $data = $status->snapshot();

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $this->exitCode($data);
        }

        $enabledLabel = $data['enabled'] ? 'activo' : 'PAUSADO';
        $hb = $data['heartbeat_age_seconds'];
        $hbLabel = $hb === null
            ? 'sin señal'
            : sprintf('hace %d s%s', $hb, $data['heartbeat_ok'] ? ' (ok)' : ($data['heartbeat_stale'] ? ' (STALE)' : ''));

        $this->info('=== Cosecha ===');
        $this->line("Estado: {$enabledLabel}");
        $this->line("Latido: {$hbLabel}".($data['heartbeat_source'] ? " [{$data['heartbeat_source']}]" : ''));
        $this->line(sprintf(
            'Avance España: %d / %d áreas hechas (%.1f%%) · pendientes %d · error %d · en_proceso %d',
            $data['areas_hechas'],
            $data['areas_total'],
            $data['progress_percent'],
            $data['areas_pendientes'],
            $data['areas_error'],
            $data['areas_en_proceso'],
        ));

        if ($data['area_en_proceso'] !== null) {
            $this->line('Área en proceso: '.$data['area_en_proceso']['name']);
        } else {
            $this->line('Área en proceso: —');
        }

        $this->line(sprintf(
            'Leads: %d · Leads hoy: %d',
            $data['leads_total'],
            $data['emails_hoy'],
        ));
        $this->line(sprintf(
            'Suma áreas (leads): %d',
            $data['leads_found_sum'],
        ));

        if ($data['ultimas_areas'] !== []) {
            $this->newLine();
            $this->table(
                ['Área', 'Estado', 'Leads', 'Fin'],
                collect($data['ultimas_areas'])->map(fn (array $a): array => [
                    $a['name'],
                    HarvestArea::STATUSES[$a['status']] ?? $a['status'],
                    $a['leads_found'],
                    $a['finished_at'] !== null
                        ? \Illuminate\Support\Carbon::parse($a['finished_at'])->format('d/m H:i')
                        : '—',
                ])->all()
            );
        }

        $code = $this->exitCode($data);

        if ($code !== self::SUCCESS) {
            $stale = (int) config('outreach.harvest.heartbeat_stale_seconds', 600);
            $this->error("Healthcheck: latido ausente o ≥ {$stale}s (código {$code}).");
        }

        return $code;
    }

    /**
     * @param  array{heartbeat_stale: bool, enabled: bool}  $data
     */
    private function exitCode(array $data): int
    {
        // Solo falla healthcheck si la cosecha está habilitada y el latido está viejo.
        if ($data['enabled'] && $data['heartbeat_stale']) {
            return 2;
        }

        return self::SUCCESS;
    }
}
