<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class RunSendJob implements ShouldQueue
{
    use Queueable;

    /** Tiempo máximo de ejecución en segundos (cubre warm-up completo con delays). */
    public int $timeout = 3600;

    /** Un solo intento: el bloqueo del comando ya evita solapes y no reenviamos el lote. */
    public int $tries = 1;

    public function __construct(public ?int $limit = null)
    {
    }

    /**
     * Ejecuta el envío de outreach en segundo plano.
     */
    public function handle(): void
    {
        Cache::put('outreach:send_running', true, now()->addMinutes(90));

        try {
            $parameters = [];

            if ($this->limit !== null) {
                $parameters['--limit'] = $this->limit;
            }

            Artisan::call('agencies:send', $parameters);
        } finally {
            Cache::forget('outreach:send_running');
            Cache::put('outreach:send_finished_at', now()->toIso8601String(), now()->addHour());
        }
    }
}
