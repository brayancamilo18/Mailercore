<?php

namespace App\Services\Envio;

use App\Models\DiaEnvio;
use App\Models\EventoInbox;
use App\Models\Mensaje;
use Illuminate\Support\Carbon;

class RampaEnvio
{
    /**
     * Calcula la cuota de correos para una fecha.
     *
     * @return array{cuota:int,escalon:int,salud:string,motivo:string,tasa_rebote:float}
     */
    public function calcular(Carbon $fecha): array
    {
        $diasRacha = $this->diasConsecutivos($fecha);
        $rachaRota = $this->rachaRota($fecha);
        $escalon = $this->escalonPara($diasRacha);

        if ($rachaRota && $escalon > 1) {
            $escalon--;
        }

        $salud = $this->evaluarSalud();
        $cuotaBase = $this->cuotaDeEscalon($escalon);
        $motivo = "Escalón {$escalon}, racha de {$diasRacha} días";

        $enviados = $this->enviadosRecientes();

        if ($enviados < config('outreach.rampa.minimo_para_evaluar')) {
            $cuota = min($cuotaBase, config('outreach.rampa.cuota_si_pocos_datos'));
            $motivo .= '; pocos datos todavía, cuota limitada';

            return $this->guardar($fecha, $cuota, $escalon, 'verde', $motivo, 0.0);
        }

        $tasa = $this->tasaRebote();

        $cuota = match ($salud) {
            'parado' => 0,
            'rojo' => (int) floor($cuotaBase / 2),
            'ambar' => $this->cuotaDelDiaAnterior($fecha) ?: $cuotaBase,
            default => $cuotaBase,
        };

        if ($salud === 'rojo' && $escalon > 1) {
            $escalon--;
            $cuota = min($cuota, $this->cuotaDeEscalon($escalon));
        }

        $motivo .= sprintf('; salud %s (%.2f%% de rebotes duros)', $salud, $tasa);

        $cuota = min($cuota, (int) config('outreach.envio.max_diario'));

        return $this->guardar($fecha, $cuota, $escalon, $salud, $motivo, $tasa);
    }

    /** Días de racha (incluye el día calculado). */
    public function diasRacha(Carbon $fecha): int
    {
        return $this->diasConsecutivos($fecha);
    }

    /** Días consecutivos con envíos hasta el día anterior a $fecha. */
    private function diasConsecutivos(Carbon $fecha): int
    {
        $dias = 0;
        $cursor = $fecha->copy()->subDay();

        for ($i = 0; $i < 120; $i++) {
            $dia = DiaEnvio::query()->whereDate('fecha', $cursor->toDateString())->first();

            if ($dia === null || $dia->enviados === 0) {
                // Fin de semana sin envíos no rompe la racha.
                if (in_array($cursor->dayOfWeekIso, [5, 6, 7], true)) {
                    $cursor->subDay();

                    continue;
                }

                break;
            }

            $dias++;
            $cursor->subDay();
        }

        return $dias + 1;   // el día que se está calculando cuenta
    }

    /** ¿Han pasado más de N días naturales sin enviar nada? */
    private function rachaRota(Carbon $fecha): bool
    {
        $ultimo = DiaEnvio::query()
            ->where('enviados', '>', 0)
            ->orderByDesc('fecha')
            ->first();

        if ($ultimo === null) {
            return false;
        }

        $hueco = Carbon::parse($ultimo->fecha)->diffInDays($fecha);

        return $hueco > (int) config('outreach.rampa.dias_hueco_rompe_racha');
    }

    private function escalonPara(int $dias): int
    {
        foreach (config('outreach.rampa.escalones') as $indice => $escalon) {
            if ($dias >= $escalon['dia_desde'] && $dias <= $escalon['dia_hasta']) {
                return $indice + 1;
            }
        }

        return 1;
    }

    private function cuotaDeEscalon(int $escalon): int
    {
        $escalones = config('outreach.rampa.escalones');

        return (int) ($escalones[$escalon - 1]['cuota'] ?? $escalones[0]['cuota']);
    }

    /** Evalúa la salud sobre la ventana de últimos envíos. */
    public function evaluarSalud(): string
    {
        $enviados = $this->enviadosRecientes();

        if ($enviados < config('outreach.rampa.minimo_para_evaluar')) {
            return 'verde';
        }

        if ($this->quejasRecientes() > 0) {
            return 'rojo';
        }

        $tasa = $this->tasaRebote();
        $umbrales = config('outreach.rampa.umbrales');

        return match (true) {
            $tasa >= $umbrales['parado'] => 'parado',
            $tasa >= $umbrales['rojo'] => 'rojo',
            $tasa >= $umbrales['ambar'] => 'ambar',
            default => 'verde',
        };
    }

    public function tasaRebote(): float
    {
        $enviados = $this->enviadosRecientes();

        if ($enviados === 0) {
            return 0.0;
        }

        $ids = Mensaje::query()->ultimosEnviados(
            (int) config('outreach.rampa.ventana_salud')
        )->pluck('id');

        $rebotes = EventoInbox::query()
            ->where('tipo', 'rebote_duro')
            ->whereIn('mensaje_id', $ids)
            ->count();

        return round(($rebotes / $enviados) * 100, 2);
    }

    private function enviadosRecientes(): int
    {
        return Mensaje::query()->ultimosEnviados(
            (int) config('outreach.rampa.ventana_salud')
        )->count();
    }

    private function quejasRecientes(): int
    {
        $ids = Mensaje::query()->ultimosEnviados(
            (int) config('outreach.rampa.ventana_salud')
        )->pluck('id');

        return EventoInbox::query()
            ->where('tipo', 'queja')
            ->whereIn('mensaje_id', $ids)
            ->count();
    }

    private function cuotaDelDiaAnterior(Carbon $fecha): ?int
    {
        $dia = DiaEnvio::query()
            ->whereDate('fecha', '<', $fecha->toDateString())
            ->orderByDesc('fecha')
            ->first();

        return $dia?->cuota_planificada;
    }

    /** @return array{cuota:int,escalon:int,salud:string,motivo:string,tasa_rebote:float} */
    private function guardar(Carbon $fecha, int $cuota, int $escalon, string $salud, string $motivo, float $tasa): array
    {
        DiaEnvio::query()->updateOrCreate(
            ['fecha' => $fecha->toDateString()],
            [
                'escalon' => $escalon,
                'cuota_planificada' => $cuota,
                'salud' => $salud,
                'tasa_rebote' => $tasa,
                'nota' => $motivo,
            ]
        );

        return compact('cuota', 'escalon', 'salud', 'motivo') + ['tasa_rebote' => $tasa];
    }

    /** Saca el sistema del estado 'parado' (acción manual). */
    public function reanudar(): void
    {
        DiaEnvio::query()
            ->where('fecha', today()->toDateString())
            ->update(['salud' => 'verde', 'nota' => 'Reanudado manualmente']);
    }
}
