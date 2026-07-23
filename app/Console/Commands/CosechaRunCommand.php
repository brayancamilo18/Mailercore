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
    protected $signature = 'cosecha:ejecutar {--area=} {--dry-run} {--max-areas=0 : Máximo de áreas en esta pasada (0 = todas las pendientes)}';

    protected $description = 'Cosecha Overpass continua: procesa áreas pendientes, omite duplicados y sigue';

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
        $maxAreas = max(0, (int) $this->option('max-areas'));
        $pausa = max(0, (int) config('outreach.cosecha.pausa_entre_areas_segundos', 30));
        $soloUna = filled($this->option('area'));

        try {
            // Si tenemos el lock, no hay cosecha viva: cualquier «en_proceso» es huérfana.
            foreach (AreaCosecha::query()->where('estado', 'en_proceso')->get() as $enProceso) {
                $this->warn("Área «{$enProceso->nombre}» quedó huérfana en_proceso; se recupera.");
                $enProceso->recuperarHuerfana();
            }

            // Errores recuperables (duplicados, fallos puntuales) vuelven a cola.
            $this->reencolarErroresRecuperables();

            do {
                $nombreArea = $this->option('area');
                $area = $nombreArea
                    ? AreaCosecha::query()->where('nombre', $nombreArea)->first()
                    : AreaCosecha::siguientePendiente();

                if ($area === null) {
                    if ($procesadas === 0) {
                        $this->info('No hay áreas pendientes.');
                    }

                    break;
                }

                $this->info("Cosechando «{$area->nombre}» (admin_level={$area->admin_level})...");
                Latido::marcar('cosecha', $area->nombre);

                try {
                    $resultado = $servicio->cosechar($area, (bool) $this->option('dry-run'));
                    $this->table(
                        ['Área', 'Creados', 'Omitidos (dup/sin web)', 'Encolados'],
                        [[$area->nombre, $resultado['creados'], $resultado['omitidos'], $resultado['encolados']]]
                    );
                } catch (OverpassNoDisponible $e) {
                    // Overpass caído: el área queda en error; salimos para no martillar la API.
                    $this->error('Overpass no disponible: '.$e->getMessage());
                    Latido::marcar('cosecha', 'overpass_caido');

                    return self::FAILURE;
                } catch (\Throwable $e) {
                    // Fallo puntual: marcar error, reencolar si es recuperable y seguir
                    // con la siguiente área (duplicados ya no deben llegar aquí).
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
                        $this->warn("Área «{$area->nombre}» reencolada (error recuperable); se continúa.");
                    }
                }

                $procesadas++;
                Latido::marcar('cosecha');

                if ($soloUna || ($maxAreas > 0 && $procesadas >= $maxAreas)) {
                    break;
                }

                if (AreaCosecha::siguientePendiente() !== null && $pausa > 0) {
                    $this->comment("Pausa {$pausa}s antes del siguiente área…");
                    sleep($pausa);
                }
            } while (true);
        } finally {
            $lock->release();
        }

        $this->info("Pasada terminada: {$procesadas} área(s) procesada(s).");

        return self::SUCCESS;
    }

    /** Devuelve a pendiente las áreas en error recuperable (p. ej. email duplicado). */
    private function reencolarErroresRecuperables(): void
    {
        $errores = AreaCosecha::query()->where('estado', 'error')->get();

        foreach ($errores as $area) {
            $msg = (string) ($area->ultimo_error ?? '');
            if ($msg === '' || $this->esErrorRecuperable($msg)) {
                $area->reiniciar();
                $this->warn("Área «{$area->nombre}» reencolada desde error.");
            }
        }
    }

    private function esErrorRecuperable(string $mensaje): bool
    {
        $patrones = [
            'duplicate key',
            'Unique violation',
            'lead_emails_email_unique',
            'SQLSTATE[23505]',
        ];

        foreach ($patrones as $patron) {
            if (str_contains($mensaje, $patron)) {
                return true;
            }
        }

        return false;
    }
}
