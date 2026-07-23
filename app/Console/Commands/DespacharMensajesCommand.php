<?php

namespace App\Console\Commands;

use App\Jobs\EnviarMensajeJob;
use App\Models\Mensaje;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DespacharMensajesCommand extends Command
{
    protected $signature = 'envio:despachar
                            {--limite=20}';

    protected $description = 'Despacha a la cola los mensajes pendientes vencidos';

    public function handle(): int
    {
        Latido::marcar('despachador');

        if (! config('outreach.envio.activo')) {
            $this->comment('El envío está desactivado.');

            return self::SUCCESS;
        }

        if (Cache::get('envio:pausado')) {
            $this->comment('El envío está pausado desde el panel.');

            return self::SUCCESS;
        }

        $limite = max(1, (int) $this->option('limite'));

        $mensajes = Mensaje::query()
            ->pendientesVencidos()
            ->orderBy('programado_para')
            ->limit($limite)
            ->get();

        foreach ($mensajes as $mensaje) {
            EnviarMensajeJob::dispatch($mensaje->id);
        }

        $this->info("Despachados {$mensajes->count()} mensajes a la cola «envio».");

        return self::SUCCESS;
    }
}
