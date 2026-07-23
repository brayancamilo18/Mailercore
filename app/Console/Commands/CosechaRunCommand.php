<?php

namespace App\Console\Commands;

use App\Models\AreaCosecha;
use App\Services\Overpass\ServicioCosecha;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CosechaRunCommand extends Command
{
    protected $signature = 'cosecha:ejecutar {--area=} {--dry-run}';

    protected $description = 'Ejecuta la cosecha Overpass de un área pendiente';

    public function handle(ServicioCosecha $servicio): int
    {
        if (Cache::get('cosecha:activa', true) === false) {
            $this->warn('La cosecha está pausada. Usa cosecha:reanudar para activarla.');

            return self::SUCCESS;
        }

        $lockSegundos = (int) config('outreach.cosecha.lock_segundos', 3600);
        $lock = Cache::lock('cosecha:run', $lockSegundos);

        if (! $lock->get()) {
            $this->warn('Ya hay una cosecha en ejecución (lock activo).');

            return self::SUCCESS;
        }

        try {
            // Si hemos conseguido el lock, no hay ninguna cosecha viva. Cualquier
            // área «en_proceso» es un huérfano (proceso muerto / OOM / kill) y
            // hay que recuperarla ya: si no, bloquea el scheduler durante horas.
            foreach (AreaCosecha::query()->where('estado', 'en_proceso')->get() as $enProceso) {
                $this->warn("Área «{$enProceso->nombre}» quedó huérfana en_proceso; se recupera.");
                $enProceso->recuperarHuerfana();
            }

            $nombreArea = $this->option('area');
            $area = $nombreArea
                ? AreaCosecha::query()->where('nombre', $nombreArea)->first()
                : AreaCosecha::siguientePendiente();

            if ($area === null) {
                $this->info('No hay áreas pendientes.');

                return self::SUCCESS;
            }

            $this->info("Cosechando «{$area->nombre}» (admin_level={$area->admin_level})...");

            try {
                $resultado = $servicio->cosechar($area, (bool) $this->option('dry-run'));
            } catch (\Throwable $e) {
                // ServicioCosecha ya marca OverpassNoDisponible; aquí cubrimos
                // cualquier otro fallo (timeout, memoria, red) para no dejar
                // el área colgada en «en_proceso».
                if ($area->fresh()?->estado === 'en_proceso') {
                    $area->forceFill([
                        'estado' => 'error',
                        'finalizada_at' => now(),
                        'ultimo_error' => mb_substr($e->getMessage(), 0, 2000),
                    ])->save();
                }

                $this->error('Cosecha falló: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->table(
                ['Creados', 'Omitidos', 'Encolados'],
                [[$resultado['creados'], $resultado['omitidos'], $resultado['encolados']]]
            );

            Latido::marcar('cosecha');
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
