<?php

namespace App\Console\Commands;

use App\Models\DiaEnvio;
use App\Models\Mensaje;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EstadoEnvioCommand extends Command
{
    protected $signature = 'envio:estado
                            {--dias=7}';

    protected $description = 'Muestra el estado del envío de outreach';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));

        $this->info('Mensajes de hoy por estado');
        $porEstado = Mensaje::query()
            ->whereDate('programado_para', today()->toDateString())
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $filasHoy = [];
        foreach (['pendiente', 'enviando', 'enviado', 'fallido', 'cancelado'] as $estado) {
            $filasHoy[] = [$estado, (int) ($porEstado[$estado] ?? 0)];
        }
        $this->table(['Estado', 'Total'], $filasHoy);

        $this->newLine();
        $this->info("Últimos {$dias} días");

        $filasDias = DiaEnvio::query()
            ->whereDate('fecha', '>=', today()->subDays($dias - 1)->toDateString())
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (DiaEnvio $dia): array => [
                $dia->fecha?->toDateString() ?? (string) $dia->fecha,
                $dia->escalon,
                $dia->cuota_planificada,
                $dia->enviados,
                $dia->rebotes_duros,
                $dia->tasa_rebote !== null ? (string) $dia->tasa_rebote : '—',
                $dia->salud,
            ])
            ->all();

        $this->table(
            ['Fecha', 'Escalón', 'Cuota', 'Enviados', 'Rebotes', 'Tasa', 'Salud'],
            $filasDias
        );

        $this->newLine();
        $this->info('Últimos 10 fallidos');

        $fallidos = Mensaje::query()
            ->where('estado', 'fallido')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (Mensaje $m): array => [
                $m->id,
                $m->destinatario,
                $m->plantilla,
                $m->paso,
                mb_substr((string) $m->ultimo_error, 0, 80),
            ])
            ->all();

        $this->table(['ID', 'Destinatario', 'Plantilla', 'Paso', 'Error'], $fallidos);

        $hoy = DiaEnvio::query()->whereDate('fecha', today()->toDateString())->first();
        $enviadosHoy = (int) ($hoy?->enviados ?? Mensaje::query()
            ->whereDate('programado_para', today()->toDateString())
            ->where('estado', 'enviado')
            ->count());

        $this->newLine();
        $this->line(sprintf(
            'Escalón actual: %s | Cuota de hoy: %s | Enviados hoy: %d | Salud: %s',
            $hoy?->escalon ?? '—',
            $hoy?->cuota_planificada ?? '—',
            $enviadosHoy,
            $hoy?->salud ?? '—'
        ));

        return self::SUCCESS;
    }
}
