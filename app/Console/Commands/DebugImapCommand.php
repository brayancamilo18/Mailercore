<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

/**
 * Comando temporal para diagnosticar IMAP (red vs credenciales).
 * Borrar cuando IMAP quede OK.
 */
class DebugImapCommand extends Command
{
    protected $signature = 'debug:imap';

    protected $description = 'Diagnóstico temporal de IMAP (config, TCP y conexión real)';

    public function handle(): int
    {
        $cuenta = config('imap.accounts.default', []);

        $host = (string) ($cuenta['host'] ?? '');
        $puerto = (int) ($cuenta['port'] ?? 0);
        $encryption = $cuenta['encryption'] ?? null;
        $username = (string) ($cuenta['username'] ?? '');

        $this->info('Config IMAP (sin contraseña):');
        $this->line('  host: '.($host !== '' ? $host : '(vacío)'));
        $this->line('  port: '.$puerto);
        $this->line('  encryption: '.var_export($encryption, true));
        $this->line('  username: '.($username !== '' ? $username : '(vacío)'));
        $this->newLine();

        if ($host === '' || $puerto <= 0) {
            $this->error('IMAP_HOST o IMAP_PORT incompletos. No se puede probar la conexión.');

            return self::FAILURE;
        }

        $destino = ($encryption === 'ssl' || $encryption === true)
            ? 'ssl://'.$host
            : $host;

        $this->info("Probando TCP: fsockopen('{$destino}', {$puerto}) timeout 10…");

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($destino, $puerto, $errno, $errstr, 10);

        if ($socket === false) {
            $this->error("TCP NO llega: [{$errno}] {$errstr}");
            $this->line('Si falla aquí, es red/firewall/DNS del contenedor, no (aún) la contraseña.');

            return self::FAILURE;
        }

        fclose($socket);
        $this->info('TCP OK: el puerto responde.');
        $this->newLine();

        $this->info('Probando conexión IMAP real (webklex/laravel-imap)…');

        try {
            $cliente = Client::account('default');
            $cliente->connect();
            $carpetas = $cliente->getFolders(false);
            $n = $carpetas->count();
            $cliente->disconnect();
            $this->info("IMAP OK: {$n} carpetas.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('IMAP FALLO (mensaje exacto):');
            $this->line(get_class($e).': '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
