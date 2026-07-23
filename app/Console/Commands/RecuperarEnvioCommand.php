<?php

namespace App\Console\Commands;

use App\Models\DiaEnvio;
use App\Models\Mensaje;
use Illuminate\Console\Command;

class RecuperarEnvioCommand extends Command
{
    protected $signature = 'envio:recuperar
                            {--dry-run}';

    protected $description = 'Recupera mensajes colgados, reprograma fallidos y limpia vencidos';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $colgadosAEnviado = 0;
        $colgadosAPendiente = 0;
        $fallidosReprogramados = 0;
        $pendientesCancelados = 0;

        // 1. Mensajes colgados en 'enviando' más de 15 minutos
        foreach (Mensaje::query()->colgados(15)->get() as $mensaje) {
            if ($mensaje->message_id !== null && $mensaje->enviado_at !== null) {
                // El SMTP lo aceptó pero se cayó antes de escribir el estado.
                if (! $dryRun) {
                    $mensaje->update(['estado' => 'enviado', 'bloqueado_at' => null]);
                }
                $colgadosAEnviado++;

                continue;
            }

            // No hay constancia de que se enviara: vuelve a la cola.
            if (! $dryRun) {
                $mensaje->update(['estado' => 'pendiente', 'bloqueado_at' => null]);
            }
            $colgadosAPendiente++;
        }

        // 2. Mensajes 'fallido' con menos de 3 intentos y programados para hoy
        $fallidos = Mensaje::query()
            ->where('estado', 'fallido')
            ->where('intentos', '<', 3)
            ->whereDate('programado_para', today()->toDateString())
            ->get();

        foreach ($fallidos as $mensaje) {
            if (! $dryRun) {
                $mensaje->update([
                    'estado' => 'pendiente',
                    'programado_para' => now()->addMinutes(30),
                    'bloqueado_at' => null,
                ]);
            }
            $fallidosReprogramados++;
        }

        // 3. Mensajes 'pendiente' cuya hora pasó hace más de 6 horas
        $vencidos = Mensaje::query()
            ->where('estado', 'pendiente')
            ->where('programado_para', '<', now()->subHours(6))
            ->get();

        foreach ($vencidos as $mensaje) {
            if (! $dryRun) {
                $mensaje->cancelar('Ventana de envío superada');
            }
            $pendientesCancelados++;
        }

        // 4. Recuento: recalcula DiaEnvio de hoy
        if (! $dryRun) {
            $this->recalcularDiaHoy();
        }

        $this->table(
            ['Tipo', 'Cantidad'],
            [
                ['Colgados → enviado (con message_id)', $colgadosAEnviado],
                ['Colgados → pendiente (sin evidencia)', $colgadosAPendiente],
                ['Fallidos reprogramados', $fallidosReprogramados],
                ['Pendientes cancelados (>6h)', $pendientesCancelados],
            ]
        );

        if ($dryRun) {
            $this->comment('Dry-run: no se ha modificado nada.');
        }

        return self::SUCCESS;
    }

    private function recalcularDiaHoy(): void
    {
        $hoy = today()->toDateString();

        $dia = DiaEnvio::paraFecha(today());
        $dia->update([
            'generados' => Mensaje::query()->whereDate('programado_para', $hoy)->count(),
            'enviados' => Mensaje::query()->whereDate('programado_para', $hoy)->where('estado', 'enviado')->count(),
            'fallidos' => Mensaje::query()->whereDate('programado_para', $hoy)->where('estado', 'fallido')->count(),
        ]);
    }
}
