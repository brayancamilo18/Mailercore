<?php

namespace App\Console\Commands;

use App\Models\AreaCosecha;
use App\Models\Mensaje;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
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
 *  3. Registra en el log cuando un latido crítico lleva demasiado tiempo mudo.
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

    /** Recupera áreas de cosecha atascadas por un proceso muerto. */
    private function recuperarCosecha(): void
    {
        try {
            $recuperadas = AreaCosecha::recuperarHuerfanasSiMuertas();

            if ($recuperadas > 0) {
                $this->acciones[] = "Cosecha: {$recuperadas} área(s) huérfana(s) recuperada(s)";
            }

            // Si hay trabajo pendiente y el latido lleva mucho mudo, fuerza
            // la liberación del lock por si una pasada murió sin soltarlo.
            $hayPendientes = Schema::hasTable('areas_cosecha')
                && DB::table('areas_cosecha')->where('estado', 'pendiente')->exists();
            $edad = Latido::edad('cosecha');
            $umbral = (int) config('outreach.cosecha.area_atascada_segundos', 600);

            if ($hayPendientes && ($edad === null || $edad >= $umbral)) {
                Cache::lock('cosecha:run')->forceRelease();
                // Limpia mutex del scheduler por si withoutOverlapping quedó colgado.
                $this->limpiarMutexSchedulerCosecha();
                $this->acciones[] = 'Cosecha: lock/mutex liberados (latido mudo con pendientes)';
            }
        } catch (\Throwable $e) {
            Log::channel('outreach')->error('Vigilante: fallo recuperando cosecha', ['error' => $e->getMessage()]);
        }
    }

    private function limpiarMutexSchedulerCosecha(): void
    {
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection();
            foreach ($redis->keys('*schedule*') as $clave) {
                // Solo tocamos mutexes; no borramos otros datos de schedule.
                if (str_contains((string) $clave, 'schedule-')) {
                    $redis->del($clave);
                }
            }
        } catch (\Throwable) {
            // Redis no disponible: el TTL del withoutOverlapping bastará.
        }
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
