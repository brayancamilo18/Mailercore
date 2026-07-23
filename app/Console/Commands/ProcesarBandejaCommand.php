<?php

namespace App\Console\Commands;

use App\Services\Inbox\ProcesadorBandeja;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcesarBandejaCommand extends Command
{
    protected $signature = 'outreach:bandeja
                            {--dry-run}
                            {--limite=100}';

    protected $description = 'Procesa la bandeja IMAP de respuestas, rebotes y bajas';

    public function handle(ProcesadorBandeja $procesador): int
    {
        // Sin IMAP configurado (p. ej. fase de solo cosecha) no tiene sentido
        // intentar conectar en bucle: se saltaría y acumularía falsos críticos.
        if (! self::imapConfigurado()) {
            Cache::forget('bandeja:fallos_seguidos');
            $this->comment('IMAP no configurado; se omite el procesado de bandeja.');

            return self::SUCCESS;
        }

        $limite = max(1, (int) $this->option('limite'));
        $dryRun = (bool) $this->option('dry-run');

        $resultado = $procesador->procesar($limite, $dryRun);

        if (! $resultado['ok']) {
            $this->error('No se pudo conectar a la bandeja: '.($resultado['motivo'] ?? 'error desconocido'));

            return self::FAILURE;
        }

        $filas = [];
        foreach ($resultado['resumen'] as $tipo => $total) {
            $filas[] = [$tipo, $total];
        }

        $this->table(['Tipo', 'Total'], $filas);

        if ($dryRun) {
            $this->comment('Dry-run: no se ha escrito nada ni marcado Seen.');
        }

        return self::SUCCESS;
    }

    /** ¿Hay una cuenta IMAP real configurada (host y usuario no de ejemplo)? */
    public static function imapConfigurado(): bool
    {
        $host = (string) config('imap.accounts.default.host', '');
        $usuario = (string) config('imap.accounts.default.username', '');

        $hostValido = $host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true);
        $usuarioValido = $usuario !== '' && $usuario !== 'root@example.com';

        return $hostValido && $usuarioValido;
    }
}
