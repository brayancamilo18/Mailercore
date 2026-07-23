<?php

namespace App\Services\Soporte;

use Illuminate\Support\Facades\Cache;

class Latido
{
    public static function marcar(string $proceso, ?string $detalle = null): void
    {
        Cache::put("latido:{$proceso}", [
            'at' => now()->timestamp,
            'detalle' => $detalle,
        ], now()->addDays(2));
    }

    /** Segundos desde el último latido, o null si nunca hubo. */
    public static function edad(string $proceso): ?int
    {
        $valor = Cache::get("latido:{$proceso}");

        if ($valor === null) {
            return null;
        }

        $at = is_array($valor) ? ($valor['at'] ?? null) : $valor;

        if ($at === null) {
            return null;
        }

        return max(0, now()->timestamp - (int) $at);
    }

    public static function estaVivo(string $proceso): bool
    {
        $edad = self::edad($proceso);
        if ($edad === null) {
            return false;
        }

        $umbral = (int) (config("outreach.latido.procesos.{$proceso}") ?? 900);

        return $edad < $umbral;
    }

    /**
     * @return array<string, array{edad: ?int, umbral: int, vivo: bool, detalle: ?string}>
     */
    public static function todos(): array
    {
        $resultado = [];

        foreach (config('outreach.latido.procesos', []) as $proceso => $umbral) {
            $valor = Cache::get("latido:{$proceso}");
            $detalle = is_array($valor) ? ($valor['detalle'] ?? null) : null;

            $resultado[$proceso] = [
                'edad' => self::edad($proceso),
                'umbral' => (int) $umbral,
                'vivo' => self::estaVivo($proceso),
                'detalle' => is_string($detalle) ? $detalle : null,
            ];
        }

        return $resultado;
    }
}
