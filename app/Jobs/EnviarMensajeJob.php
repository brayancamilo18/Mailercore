<?php

namespace App\Jobs;

use App\Mail\CorreoOutreach;
use App\Models\DiaEnvio;
use App\Models\Mensaje;
use App\Models\Suppression;
use App\Services\Soporte\Latido;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarMensajeJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 1;      // UN SOLO INTENTO: el reintento lo hace envio:recuperar

    public int $timeout = 120;

    public function __construct(public int $mensajeId)
    {
        $this->onQueue('envio');
    }

    public function handle(): void
    {
        $mensaje = Mensaje::find($this->mensajeId);

        if ($mensaje === null || $mensaje->estado !== 'pendiente') {
            return;   // idempotencia: otro proceso ya lo cogió o no existe
        }

        if (! $mensaje->marcarEnviando()) {
            return;   // otro proceso ganó la carrera
        }

        // Última comprobación: puede haber pedido la baja entre la planificación
        // y este momento.
        if (Suppression::existe($mensaje->destinatario)) {
            $mensaje->cancelar('Destinatario suprimido antes del envío');
            $mensaje->lead?->update(['estado' => 'baja']);

            return;
        }

        try {
            Mail::to($mensaje->destinatario)->send(new CorreoOutreach($mensaje));
        } catch (\Throwable $e) {
            $mensaje->marcarFallido($e->getMessage());
            DiaEnvio::paraFecha($mensaje->programado_para)->incrementarContador('fallidos');
            report($e);

            return;
        }

        DB::transaction(function () use ($mensaje): void {
            $mensaje->marcarEnviado($mensaje->message_id);

            $mensaje->lead?->update([
                'estado' => $mensaje->paso === 1 ? 'contactado' : 'seguimiento',
                'contactado_at' => now(),
            ]);

            DiaEnvio::paraFecha($mensaje->programado_para)->incrementarContador('enviados');
        });

        Latido::marcar('despachador', (string) $mensaje->id);

        Log::channel('outreach')->info('Correo enviado', [
            'mensaje_id' => $mensaje->id,
            'lead_id' => $mensaje->lead_id,
            'plantilla' => $mensaje->plantilla,
            'paso' => $mensaje->paso,
            'dominio' => Suppression::dominioDeEmail($mensaje->destinatario),
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        $mensaje = Mensaje::find($this->mensajeId);

        if ($mensaje !== null && $mensaje->estado === 'enviando') {
            $mensaje->marcarFallido($e?->getMessage() ?? 'Fallo desconocido');
        }
    }
}
