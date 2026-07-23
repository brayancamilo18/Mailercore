<?php

namespace App\Console\Commands;

use App\Services\Envio\PlanificadorDiario;
use App\Services\Soporte\Latido;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PlanificarEnvioCommand extends Command
{
    protected $signature = 'envio:planificar
                            {--fecha=}
                            {--dry-run}';

    protected $description = 'Planifica los correos del día según la rampa y los candidatos';

    public function handle(PlanificadorDiario $planificador): int
    {
        $fecha = $this->option('fecha')
            ? Carbon::parse((string) $this->option('fecha'))->startOfDay()
            : today();

        $dryRun = (bool) $this->option('dry-run');

        Latido::marcar('planificador');

        $resultado = $planificador->planificar($fecha, $dryRun);

        if ($dryRun) {
            $filas = [];
            foreach ($resultado['plan'] ?? [] as $item) {
                $filas[] = [
                    $item['hora'],
                    $item['sector'] ?? '—',
                    $item['dominio'],
                    $item['asunto'],
                    $item['hallazgo'] ?? '—',
                ];
            }

            $this->table(
                ['Hora', 'Sector', 'Dominio', 'Asunto', 'Hallazgo'],
                $filas
            );

            $this->info(sprintf(
                'Dry-run: %d primeros contactos, %d seguimientos, %d omitidos (cuota %d). %s',
                $resultado['primer_contacto'],
                $resultado['seguimientos'],
                $resultado['omitidos'],
                $resultado['cuota'],
                $resultado['motivo']
            ));

            return self::SUCCESS;
        }

        $creados = $resultado['primer_contacto'] + $resultado['seguimientos'];

        $this->info(sprintf(
            'Planificados %d mensajes (%d primeros, %d seguimientos, %d omitidos). Cuota %d. %s',
            $creados,
            $resultado['primer_contacto'],
            $resultado['seguimientos'],
            $resultado['omitidos'],
            $resultado['cuota'],
            $resultado['motivo']
        ));

        return self::SUCCESS;
    }
}
