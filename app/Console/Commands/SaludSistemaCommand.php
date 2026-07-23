<?php

namespace App\Console\Commands;

use App\Models\DiaEnvio;
use App\Models\EventoInbox;
use App\Models\Mensaje;
use App\Services\Envio\RampaEnvio;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class SaludSistemaCommand extends Command
{
    protected $signature = 'sistema:salud {--json}';

    protected $description = 'Comprueba la salud del sistema (monitorización)';

    /** @var list<array{comprobacion: string, estado: string, detalle: string}> */
    private array $resultados = [];

    public function handle(RampaEnvio $rampa): int
    {
        $this->comprobarLatidos();
        $this->comprobarBaseDatos();
        $this->comprobarRedis();
        $this->comprobarMensajesColgados();
        $this->comprobarPendientesVencidos();
        $this->comprobarJobsFallidos();
        $this->comprobarTasaRebote($rampa);
        $this->comprobarSaludDia();
        $this->comprobarUltimoEventoBandeja();
        $this->comprobarDisco();
        $this->comprobarTamanoBd();
        $this->comprobarFallosBandeja();

        $avisos = count(array_filter($this->resultados, fn (array $r): bool => $r['estado'] === 'AVISO'));
        $criticos = count(array_filter($this->resultados, fn (array $r): bool => $r['estado'] === 'CRÍTICO'));

        $codigo = match (true) {
            $criticos > 0 => 2,
            $avisos > 0 => 1,
            default => 0,
        };

        $etiqueta = match ($codigo) {
            2 => "CRÍTICO ({$criticos})",
            1 => "AVISOS ({$avisos})",
            default => 'OK',
        };

        if ($this->option('json')) {
            $this->line(json_encode([
                'resultado' => $etiqueta,
                'codigo' => $codigo,
                'avisos' => $avisos,
                'criticos' => $criticos,
                'comprobaciones' => $this->resultados,
            ], JSON_UNESCAPED_UNICODE));

            return $codigo;
        }

        $this->table(
            ['Comprobación', 'Estado', 'Detalle'],
            array_map(fn (array $r): array => [$r['comprobacion'], $r['estado'], $r['detalle']], $this->resultados)
        );

        $this->newLine();
        $this->line("RESULTADO: {$etiqueta}");

        return $codigo;
    }

    private function anadir(string $comprobacion, string $estado, string $detalle): void
    {
        $this->resultados[] = compact('comprobacion', 'estado', 'detalle');
    }

    private function comprobarLatidos(): void
    {
        $imapConfigurado = ProcesarBandejaCommand::imapConfigurado();
        $envioActivo = (bool) config('outreach.envio.activo');

        foreach (Latido::todos() as $proceso => $info) {
            $edadTxt = $info['edad'] === null ? 'nunca' : $info['edad'].'s';
            $detalle = "edad {$edadTxt} / umbral {$info['umbral']}s";

            // Procesos que aún no aplican en esta fase no deben dar falsos críticos.
            if ($proceso === 'bandeja' && ! $imapConfigurado) {
                $this->anadir('Latido bandeja', 'OK', 'IMAP no configurado (omitido)');

                continue;
            }
            if (in_array($proceso, ['despachador', 'planificador'], true) && ! $envioActivo) {
                $this->anadir("Latido {$proceso}", 'OK', 'envío desactivado (omitido)');

                continue;
            }

            if ($info['vivo']) {
                $this->anadir("Latido {$proceso}", 'OK', $detalle);

                continue;
            }

            $nivel = in_array($proceso, ['despachador', 'bandeja'], true) ? 'CRÍTICO' : 'AVISO';
            $this->anadir("Latido {$proceso}", $nivel, $detalle);
        }
    }

    private function comprobarBaseDatos(): void
    {
        try {
            DB::select('select 1');
            $this->anadir('Conexión a Postgres', 'OK', 'conectado ('.config('database.default').')');
        } catch (\Throwable $e) {
            $this->anadir('Conexión a Postgres', 'CRÍTICO', $e->getMessage());
        }
    }

    private function comprobarRedis(): void
    {
        try {
            $pong = Redis::connection()->ping();
            $ok = $pong === true || $pong === 'PONG' || $pong === '+PONG';
            if (! $ok && is_object($pong) && method_exists($pong, '__toString')) {
                $ok = str_contains((string) $pong, 'PONG');
            }

            if ($ok) {
                $this->anadir('Conexión a Redis', 'OK', 'PONG');
            } else {
                $this->anadir('Conexión a Redis', 'CRÍTICO', 'respuesta inesperada: '.var_export($pong, true));
            }
        } catch (\Throwable $e) {
            // En tests con CACHE_STORE=array no hay Redis real: la caché array cubre latidos.
            if (app()->environment('testing') && config('cache.default') === 'array') {
                $this->anadir('Conexión a Redis', 'OK', 'omitido en testing (cache array)');

                return;
            }

            $this->anadir('Conexión a Redis', 'CRÍTICO', $e->getMessage());
        }
    }

    private function comprobarMensajesColgados(): void
    {
        $n = Mensaje::query()->colgados(15)->count();
        if ($n > 0) {
            $this->anadir('Mensajes colgados', 'CRÍTICO', "{$n} en 'enviando' > 15 min");
        } else {
            $this->anadir('Mensajes colgados', 'OK', 'ninguno');
        }
    }

    private function comprobarPendientesVencidos(): void
    {
        $n = Mensaje::query()
            ->where('estado', 'pendiente')
            ->where('programado_para', '<=', now()->subHour())
            ->count();

        if ($n > 0) {
            $this->anadir('Pendientes vencidos', 'AVISO', "{$n} pendientes con > 1 h de retraso");
        } else {
            $this->anadir('Pendientes vencidos', 'OK', 'ninguno');
        }
    }

    private function comprobarJobsFallidos(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->anadir('Jobs fallidos 24h', 'OK', 'tabla inexistente');

            return;
        }

        $n = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        $estado = match (true) {
            $n > 50 => 'CRÍTICO',
            $n > 10 => 'AVISO',
            default => 'OK',
        };

        $this->anadir('Jobs fallidos 24h', $estado, "{$n} fallos");
    }

    private function comprobarTasaRebote(RampaEnvio $rampa): void
    {
        $tasa = $rampa->tasaRebote();
        $estado = match (true) {
            $tasa > 4.0 => 'CRÍTICO',
            $tasa > 2.0 => 'AVISO',
            default => 'OK',
        };

        $this->anadir('Tasa de rebote (200)', $estado, number_format($tasa, 2).'%');
    }

    private function comprobarSaludDia(): void
    {
        $dia = DiaEnvio::query()->whereDate('fecha', today()->toDateString())->first();
        $salud = $dia?->salud ?? 'verde';

        if ($salud === 'parado') {
            $this->anadir('Salud del día', 'CRÍTICO', 'parado');
        } else {
            $this->anadir('Salud del día', 'OK', $salud);
        }
    }

    private function comprobarUltimoEventoBandeja(): void
    {
        $ultimo = EventoInbox::query()->orderByDesc('recibido_at')->value('recibido_at');

        if ($ultimo === null) {
            $this->anadir('Último evento bandeja', 'OK', 'sin eventos todavía');

            return;
        }

        $edad = now()->diffInSeconds($ultimo);
        if ($edad > 3600) {
            $this->anadir('Último evento bandeja', 'AVISO', 'hace '.round($edad / 60).' min');
        } else {
            $this->anadir('Último evento bandeja', 'OK', 'hace '.$edad.'s');
        }
    }

    private function comprobarDisco(): void
    {
        $ruta = storage_path();
        $libre = @disk_free_space($ruta);
        $total = @disk_total_space($ruta);

        if ($libre === false || $total === false || $total <= 0) {
            $this->anadir('Espacio en disco', 'AVISO', 'no se pudo medir');

            return;
        }

        $pctLibre = ($libre / $total) * 100;
        $detalle = sprintf('%.1f%% libre (%.1f GB)', $pctLibre, $libre / 1024 / 1024 / 1024);

        $estado = match (true) {
            $pctLibre < 10 => 'CRÍTICO',
            $pctLibre < 20 => 'AVISO',
            default => 'OK',
        };

        $this->anadir('Espacio en disco', $estado, $detalle);
    }

    private function comprobarTamanoBd(): void
    {
        try {
            $driver = config('database.default');
            if ($driver === 'sqlite') {
                $path = config('database.connections.sqlite.database');
                $bytes = is_string($path) && $path !== ':memory:' && is_file($path)
                    ? filesize($path)
                    : 0;
                $this->anadir('Tamaño BD', 'OK', $this->formatoBytes((int) $bytes).' (sqlite)');

                return;
            }

            $db = config('database.connections.pgsql.database');
            $fila = DB::selectOne('select pg_database_size(?) as bytes', [$db]);
            $bytes = (int) ($fila->bytes ?? 0);
            $this->anadir('Tamaño BD', 'OK', $this->formatoBytes($bytes).' (pgsql)');
        } catch (\Throwable $e) {
            $this->anadir('Tamaño BD', 'AVISO', $e->getMessage());
        }
    }

    private function comprobarFallosBandeja(): void
    {
        if (! ProcesarBandejaCommand::imapConfigurado()) {
            $this->anadir('Fallos bandeja seguidos', 'OK', 'IMAP no configurado (omitido)');

            return;
        }

        $n = (int) Cache::get('bandeja:fallos_seguidos', 0);
        if ($n >= 6) {
            $this->anadir('Fallos bandeja seguidos', 'CRÍTICO', (string) $n);
        } else {
            $this->anadir('Fallos bandeja seguidos', 'OK', (string) $n);
        }
    }

    private function formatoBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }
}
