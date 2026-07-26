<?php

namespace App\Console\Commands;

use App\Excepciones\OverpassNoDisponible;
use App\Models\AreaCosecha;
use App\Models\PaisCosecha;
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
        // El servicio Docker «cosecha» relanza al terminar el presupuesto.
        $presupuesto = max(240, $lockSegundos - 60);

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

                    // Países con todas las áreas terminadas: +1 ciclo (máx 3) o check ✓.
                    $avanzados = PaisCosecha::procesarCiclosCompletos();
                    $siguiente = AreaCosecha::siguientePendiente();

                    if ($siguiente === null) {
                        $pendientesPais = PaisCosecha::query()->activos()->count();
                        if ($pendientesPais === 0) {
                            $this->info('Todos los países han completado sus ciclos de cosecha.');
                            Latido::marcar('cosecha', 'paises_completados');

                            break;
                        }

                        $this->warn("Esperando áreas activas. Pausa {$pausaCiclo}s…");
                        Latido::marcar('cosecha', 'ciclo_sin_areas');
                        sleep($pausaCiclo);
                        $creadosTotales = 0;
                        $procesadas = 0;

                        continue;
                    }

                    if ($avanzados > 0) {
                        $this->info("Avance de ciclo en {$avanzados} país(es). {$creadosTotales} leads nuevos en la ronda.");
                    }

                    $creadosTotales = 0;
                    $procesadas = 0;

                    continue;
                }

                $etiqueta = ($area->pais_codigo ?: '?').'/'.$area->nombre;
                $this->info("Cosechando «{$etiqueta}» (admin_level={$area->admin_level})...");
                Latido::marcar('cosecha', $etiqueta);

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
                    // Fallo transitorio: no abortar la campaña global.
                    $this->error('Overpass no disponible: '.$e->getMessage());
                    Latido::marcar('cosecha', 'overpass_caido');
                    $area->fresh()?->reiniciar();
                    sleep(min($pausaCiclo, 120));

                    continue;
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
                    } else {
                        // El área queda en error (cuenta como terminada) para no
                        // bloquear el ciclo del país; se reintentará en el siguiente ciclo.
                        $this->warn("Área «{$area->nombre}» en error; se sigue con la siguiente.");
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
            if ($area->pais?->completado()) {
                continue;
            }
            $msg = (string) ($area->ultimo_error ?? '');
            if ($msg === '' || $this->esErrorRecuperable($msg)) {
                $area->reiniciar();
                $this->warn("Área «{$area->pais_codigo}/{$area->nombre}» reencolada desde error.");
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
