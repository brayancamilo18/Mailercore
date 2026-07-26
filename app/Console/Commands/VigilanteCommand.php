<?php

namespace App\Console\Commands;

use App\Models\AreaCosecha;
use App\Models\Mensaje;
use App\Services\Envio\PlanificadorDiario;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Vigilante de resiliencia: se ejecuta cada minuto y autocura el sistema para
 * que nunca se quede parado. No hace tareas de negocio; solo detecta y repara.
 *
 *  1. Recupera áreas de cosecha huérfanas (proceso muerto sin soltar el estado).
 *  2. Devuelve a la cola mensajes colgados en 'enviando' sin evidencia de envío.
 *  3. Mantiene vivo el latido del planificador y regenera la cola si faltó a las 07:00.
 *  4. Registra en el log cuando un latido crítico lleva demasiado tiempo mudo.
 *
 * Es idempotente y seguro de ejecutar en paralelo (usa withoutOverlapping en el
 * scheduler). Nunca lanza excepciones al scheduler: captura y registra.
 */
class VigilanteCommand extends Command
{
    protected $signature = 'sistema:vigilante {--json}';

    protected $description = 'Watchdog de resiliencia: detecta y repara procesos parados';

    /** @var list<string> */
    private array $acciones = [];

    public function handle(): int
    {
        $this->recuperarCosecha();
        $this->recuperarMensajesColgados();
        $this->asegurarPlanificador();
        $this->revisarLatidosCriticos();

        Latido::marcar('vigilante', implode(' | ', $this->acciones) ?: 'sin incidencias');

        if ($this->acciones !== []) {
            Log::channel('outreach')->info('Vigilante actuó', ['acciones' => $this->acciones]);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'acciones' => $this->acciones,
            ], JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($this->acciones === []) {
            $this->info('Vigilante: sin incidencias.');
        } else {
            foreach ($this->acciones as $accion) {
                $this->warn('· '.$accion);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Autocura de cosecha: huérfanas, locks muertos y mutexes de schedule.
     * El motor real es el contenedor Docker «cosecha»; esto es red de seguridad.
     */
    private function recuperarCosecha(): void
    {
        try {
            if (! Schema::hasTable('areas_cosecha')) {
                return;
            }

            $recuperadas = AreaCosecha::recuperarHuerfanasSiMuertas();

            if ($recuperadas > 0) {
                $this->acciones[] = "Cosecha: {$recuperadas} área(s) huérfana(s) recuperada(s)";
            }

            $hayTrabajo = DB::table('areas_cosecha')
                ->whereIn('estado', ['pendiente', 'en_proceso'])
                ->exists();

            if (! $hayTrabajo) {
                return;
            }

            $edad = Latido::edad('cosecha');
            $umbral = (int) config('outreach.cosecha.area_atascada_segundos', 180);
            $latidoMudo = $edad === null || $edad >= $umbral;

            // Siempre limpia mutexes de schedule huérfanos (ya no lanzamos cosecha
            // desde schedule, pero pueden quedar claves viejas bloqueando otras cosas).
            $borrados = $this->limpiarMutexSchedulerCosecha();

            if ($latidoMudo) {
                Cache::lock('cosecha:run')->forceRelease();
                // Áreas en_proceso sin latido fresco → pendiente.
                $huerfanas = AreaCosecha::query()->where('estado', 'en_proceso')->get();
                foreach ($huerfanas as $area) {
                    $area->recuperarHuerfana();
                }
                $this->acciones[] = 'Cosecha: lock liberado + '.count($huerfanas).' área(s) recuperada(s) (latido mudo)'
                    .($borrados > 0 ? ", {$borrados} mutex" : '');
            } elseif ($borrados > 0) {
                $this->acciones[] = "Cosecha: {$borrados} mutex de schedule residual(es) borrado(s)";
            }
        } catch (\Throwable $e) {
            Log::channel('outreach')->error('Vigilante: fallo recuperando cosecha', ['error' => $e->getMessage()]);
        }
    }

    /** @return int claves de mutex eliminadas */
    private function limpiarMutexSchedulerCosecha(): int
    {
        $borrados = 0;

        try {
            // Cliente raw: evita el doble prefijo de Redis::keys()/del() de Laravel.
            $client = \Illuminate\Support\Facades\Redis::connection()->client();
            $patrones = ['*framework/schedule*', '*schedule-*'];

            foreach ($patrones as $patron) {
                $claves = method_exists($client, 'keys') ? $client->keys($patron) : [];
                foreach ($claves as $clave) {
                    $clave = (string) $clave;
                    if (! str_contains($clave, 'schedule')) {
                        continue;
                    }
                    $client->del($clave);
                    $borrados++;
                }
            }
        } catch (\Throwable) {
            // Redis no disponible: el TTL del withoutOverlapping bastará.
        }

        return $borrados;
    }

    /**
     * Los mensajes que llevan mucho en 'enviando' sin message_id significan que
     * el worker murió a media tarea: los devolvemos a 'pendiente'.
     */
    private function recuperarMensajesColgados(): void
    {
        try {
            if (! Schema::hasTable('mensajes')) {
                return;
            }

            $colgados = Mensaje::query()->colgados(15)->get();
            $recuperados = 0;

            foreach ($colgados as $mensaje) {
                if ($mensaje->message_id !== null && $mensaje->enviado_at !== null) {
                    // El SMTP lo aceptó pero se cayó antes de anotar el estado.
                    $mensaje->update(['estado' => 'enviado', 'bloqueado_at' => null]);
                } else {
                    $mensaje->update(['estado' => 'pendiente', 'bloqueado_at' => null]);
                }
                $recuperados++;
            }

            if ($recuperados > 0) {
                $this->acciones[] = "Envío: {$recuperados} mensaje(s) colgado(s) recuperado(s)";
            }
        } catch (\Throwable $e) {
            Log::channel('outreach')->error('Vigilante: fallo recuperando mensajes', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Mantiene el latido del planificador y asegura la cola:
     *  - La noche anterior (≥20:00) prepara el siguiente día de envío.
     *  - En día de envío, si tras las 07:05 aún no hay cola, la regenera.
     */
    private function asegurarPlanificador(): void
    {
        try {
            $ahora = Carbon::now('Europe/Madrid');
            $hoy = $ahora->copy()->startOfDay();

            if (! (bool) config('outreach.envio.activo')) {
                Latido::marcar('planificador', 'Envío desactivado');

                return;
            }

            /** @var list<int> $dias */
            $dias = array_map('intval', config('outreach.envio.dias', [1, 2, 3, 4]));
            $proximo = $this->proximoDiaEnvio($ahora, $dias);
            $esDiaEnvio = in_array($ahora->dayOfWeekIso, $dias, true);

            // Desde las 20:00 del día previo, la cola del próximo envío debe existir.
            $horaPreparacion = $proximo->copy()->subDay()->setTime(20, 0);
            if ($ahora->gte($horaPreparacion) && ! PlanificadorDiario::yaPlanificado($proximo)) {
                if ($ahora->lt($horaPreparacion->copy()->addMinutes(5))) {
                    Latido::marcar(
                        'planificador',
                        'Preparando cola del '.$proximo->locale('es')->isoFormat('ddd D')
                    );

                    return;
                }

                Artisan::call('envio:planificar', ['--fecha' => $proximo->toDateString()]);
                $this->acciones[] = 'Planificador: cola de '.$proximo->toDateString().' preparada';
                Log::channel('outreach')->warning('Vigilante: preparó la cola del próximo día', [
                    'fecha' => $proximo->toDateString(),
                ]);
            }

            if ($esDiaEnvio && $ahora->gte($hoy->copy()->setTime(7, 5)) && ! PlanificadorDiario::yaPlanificado($hoy)) {
                Artisan::call('envio:planificar', ['--fecha' => $hoy->toDateString()]);
                $this->acciones[] = 'Planificador: cola del día regenerada (faltaba tras las 07:00)';
                Log::channel('outreach')->warning('Vigilante: regeneró la cola diaria', [
                    'fecha' => $hoy->toDateString(),
                ]);
            }

            // En día de envío priorizamos la cola de hoy si ya existe o ya pasó las 07:00.
            $fechaLatido = ($esDiaEnvio && (
                PlanificadorDiario::yaPlanificado($hoy)
                || $ahora->gte($hoy->copy()->setTime(7, 0))
            )) ? $hoy : $proximo;

            $n = Schema::hasTable('mensajes')
                ? Mensaje::query()->whereDate('programado_para', $fechaLatido->toDateString())->count()
                : 0;

            if (PlanificadorDiario::yaPlanificado($fechaLatido)) {
                $etiqueta = $fechaLatido->isSameDay($hoy) ? 'hoy' : $fechaLatido->locale('es')->isoFormat('ddd D');
                Latido::marcar(
                    'planificador',
                    $n > 0 ? "Cola de {$etiqueta} lista ({$n})" : "Cola de {$etiqueta} revisada"
                );

                return;
            }

            Latido::marcar(
                'planificador',
                'En espera — cola del '.$proximo->locale('es')->isoFormat('ddd D').' a las 20:00'
            );
        } catch (\Throwable $e) {
            Log::channel('outreach')->error('Vigilante: fallo asegurando planificador', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param  list<int>  $dias */
    private function proximoDiaEnvio(Carbon $desde, array $dias): Carbon
    {
        $cursor = $desde->copy()->startOfDay()->addDay();

        for ($i = 0; $i < 14; $i++) {
            if (in_array($cursor->dayOfWeekIso, $dias, true)) {
                return $cursor;
            }
            $cursor->addDay();
        }

        return $desde->copy()->addDay();
    }

    /** Deja constancia en el log si un latido crítico lleva demasiado mudo. */
    private function revisarLatidosCriticos(): void
    {
        // Solo avisamos de procesos que deberían estar activos ahora mismo.
        $criticos = [];

        if (ProcesarBandejaCommand::imapConfigurado()) {
            $criticos[] = 'bandeja';
        }

        if ((bool) config('outreach.envio.activo')) {
            $criticos[] = 'despachador';
        }

        if ((bool) config('outreach.cosecha.activa')) {
            // Cosecha solo es "crítica" si hay trabajo pendiente por hacer.
            $hayPendientes = Schema::hasTable('areas_cosecha')
                && DB::table('areas_cosecha')->where('estado', 'pendiente')->exists();
            if ($hayPendientes) {
                $criticos[] = 'cosecha';
            }
        }

        foreach ($criticos as $proceso) {
            if (! Latido::estaVivo($proceso)) {
                $edad = Latido::edad($proceso);
                $edadTxt = $edad === null ? 'nunca' : $edad.'s';
                $this->acciones[] = "Latido «{$proceso}» mudo ({$edadTxt})";
                Log::channel('outreach')->warning('Vigilante: latido crítico mudo', [
                    'proceso' => $proceso,
                    'edad' => $edad,
                ]);
            }
        }
    }
}
