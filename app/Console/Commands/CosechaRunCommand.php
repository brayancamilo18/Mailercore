<?php

namespace App\Console\Commands;

use App\Excepciones\OverpassNoDisponible;
use App\Models\AreaCosecha;
use App\Services\Overpass\ServicioCosecha;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CosechaRunCommand extends Command
{
    protected $signature = 'cosecha:ejecutar {--area=} {--dry-run} {--max-areas=0 : Máximo de áreas en esta pasada (0 = sin límite)}';

    protected $description = 'Cosecha Overpass en bucle hasta agotar negocios nuevos';

    public function handle(ServicioCosecha $servicio): int
    {
        if (Cache::get('cosecha:activa', true) === false) {
            $this->warn('La cosecha está pausada. Usa cosecha:reanudar para activarla.');

            return self::SUCCESS;
        }

        if (! (bool) config('outreach.cosecha.activa', true)) {
            $this->warn('OUTREACH_COSECHA_ACTIVA=false; no se cosecha.');

            return self::SUCCESS;
        }

        $lockSegundos = (int) config('outreach.cosecha.lock_segundos', 7200);
        $lock = Cache::lock('cosecha:run', $lockSegundos);

        if (! $lock->get()) {
            $this->warn('Ya hay una cosecha en ejecución (lock activo).');

            return self::SUCCESS;
        }

        $procesadas = 0;
        $creadosTotales = 0;
        $maxAreas = max(0, (int) $this->option('max-areas'));
        $pausa = max(0, (int) config('outreach.cosecha.pausa_entre_areas_segundos', 15));
        $pausaCiclo = max(60, (int) config('outreach.cosecha.pausa_entre_ciclos_segundos', 300));
        $soloUna = filled($this->option('area'));
        $inicio = time();
        // No monopolizar el worker más de ~lock-5min: el scheduler relanza.
        $presupuesto = max(300, $lockSegundos - 300);

        try {
            foreach (AreaCosecha::query()->where('estado', 'en_proceso')->get() as $enProceso) {
                $this->warn("Área «{$enProceso->nombre}» huérfana; se recupera.");
                $enProceso->recuperarHuerfana();
            }

            $this->reencolarErroresRecuperables();

            while (true) {
                if (time() - $inicio >= $presupuesto) {
                    $this->comment('Presupuesto de tiempo agotado; el scheduler continuará.');

                    break;
                }

                $nombreArea = $this->option('area');
                $area = $nombreArea
                    ? AreaCosecha::query()->where('nombre', $nombreArea)->first()
                    : AreaCosecha::siguientePendiente();

                if ($area === null) {
                    if ($soloUna) {
                        $this->info('No hay área que cosechar.');

                        break;
                    }

                    // Ciclo completo: si en esta ronda aún creamos leads, reiniciamos
                    // todas las áreas y seguimos. Si no, esperamos y reintentamos
                    // (OSM/webs cambian; no nos detenemos del todo).
                    $reiniciadas = AreaCosecha::reiniciarCicloCompleto();
                    if ($reiniciadas === 0) {
                        $this->info('No hay áreas configuradas.');

                        break;
                    }

                    if ($creadosTotales === 0 && $procesadas > 0) {
                        $this->warn("Ciclo sin leads nuevos. Pausa {$pausaCiclo}s y se vuelve a barrer España…");
                        Latido::marcar('cosecha', 'ciclo_sin_novedades');
                        sleep($pausaCiclo);
                        $creadosTotales = 0;
                        $procesadas = 0;

                        continue;
                    }

                    $this->info("Ciclo completo ({$creadosTotales} leads nuevos). Reiniciando {$reiniciadas} áreas para seguir buscando…");
                    $creadosTotales = 0;
                    $procesadas = 0;

                    continue;
                }

                $this->info("Cosechando «{$area->nombre}» (admin_level={$area->admin_level})...");
                Latido::marcar('cosecha', $area->nombre);

                try {
                    $resultado = $servicio->cosechar($area, (bool) $this->option('dry-run'));
                    $creadosTotales += (int) $resultado['creados'];
                    $this->table(
                        ['Área', 'Nuevos', 'Omitidos', 'Candidatos OSM', 'Encolados'],
                        [[
                            $area->nombre,
                            $resultado['creados'],
                            $resultado['omitidos'],
                            $resultado['candidatos'] ?? 0,
                            $resultado['encolados'],
                        ]]
                    );
                } catch (OverpassNoDisponible $e) {
                    $this->error('Overpass no disponible: '.$e->getMessage());
                    Latido::marcar('cosecha', 'overpass_caido');

                    return self::FAILURE;
                } catch (\Throwable $e) {
                    if ($area->fresh()?->estado === 'en_proceso') {
                        $area->forceFill([
                            'estado' => 'error',
                            'finalizada_at' => now(),
                            'ultimo_error' => mb_substr($e->getMessage(), 0, 2000),
                        ])->save();
                    }

                    $this->error("Área «{$area->nombre}» falló: ".$e->getMessage());

                    if ($this->esErrorRecuperable($e->getMessage())) {
                        $area->fresh()?->reiniciar();
                        $this->warn("Área «{$area->nombre}» reencolada; se continúa.");
                    }
                }

                $procesadas++;
                Latido::marcar('cosecha');

                if ($soloUna || ($maxAreas > 0 && $procesadas >= $maxAreas)) {
                    break;
                }

                if (AreaCosecha::siguientePendiente() !== null && $pausa > 0) {
                    sleep($pausa);
                }
            }
        } finally {
            $lock->release();
        }

        $this->info("Pasada terminada: {$procesadas} área(s), {$creadosTotales} lead(s) nuevos en esta ejecución.");

        return self::SUCCESS;
    }

    private function reencolarErroresRecuperables(): void
    {
        foreach (AreaCosecha::query()->where('estado', 'error')->get() as $area) {
            $msg = (string) ($area->ultimo_error ?? '');
            if ($msg === '' || $this->esErrorRecuperable($msg)) {
                $area->reiniciar();
                $this->warn("Área «{$area->nombre}» reencolada desde error.");
            }
        }
    }

    private function esErrorRecuperable(string $mensaje): bool
    {
        foreach (['duplicate key', 'Unique violation', 'lead_emails_email_unique', 'SQLSTATE[23505]'] as $patron) {
            if (str_contains($mensaje, $patron)) {
                return true;
            }
        }

        return false;
    }
}
