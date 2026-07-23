<?php

namespace App\Models;

use App\Services\Soporte\Latido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AreaCosecha extends Model
{
    protected $table = 'areas_cosecha';

    public const ESTADOS = [
        'pendiente',
        'en_proceso',
        'hecho',
        'error',
    ];

    protected $fillable = [
        'nombre',
        'admin_level',
        'estado',
        'prioridad',
        'leads_encontrados',
        'emails_encontrados',
        'ultimo_error',
        'iniciada_at',
        'finalizada_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciada_at' => 'datetime',
            'finalizada_at' => 'datetime',
        ];
    }

    public static function siguientePendiente(): ?self
    {
        return self::query()
            ->where('estado', 'pendiente')
            ->orderBy('prioridad')
            ->orderBy('id')
            ->first();
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('prioridad')->orderBy('nombre');
    }

    public function reiniciar(): void
    {
        $this->forceFill([
            'estado' => 'pendiente',
            'ultimo_error' => null,
            'iniciada_at' => null,
            'finalizada_at' => null,
        ])->save();
        Cache::forget("cosecha:reintentos:{$this->id}");
    }

    /**
     * Recupera esta área huérfana (proceso de cosecha muerto). La devuelve a
     * 'pendiente' para que se reintente, salvo que ya se haya recuperado
     * demasiadas veces: entonces la marca 'error' para no bloquear el barrido.
     */
    public function recuperarHuerfana(): void
    {
        $max = (int) config('outreach.cosecha.max_reintentos', 5);
        $clave = "cosecha:reintentos:{$this->id}";

        if (! Cache::has($clave)) {
            Cache::put($clave, 0, now()->addDays(7));
        }
        $intentos = (int) Cache::increment($clave);
        Cache::put($clave, $intentos, now()->addDays(7));

        if ($intentos > $max) {
            $this->forceFill([
                'estado' => 'error',
                'finalizada_at' => now(),
                'ultimo_error' => "Recuperada {$intentos} veces sin completar; requiere revisión manual.",
            ])->save();

            return;
        }

        $this->forceFill([
            'estado' => 'pendiente',
            'iniciada_at' => null,
            'finalizada_at' => null,
            'ultimo_error' => "Recuperada (intento {$intentos}): proceso de cosecha interrumpido.",
        ])->save();
    }

    /**
     * Barre áreas atascadas en 'en_proceso' cuyo proceso ha muerto (latido de
     * cosecha caduco) y las recupera. Devuelve cuántas recuperó.
     *
     * Si el latido es reciente, hay una cosecha viva y no se toca nada.
     */
    public static function recuperarHuerfanasSiMuertas(): int
    {
        $umbral = (int) config('outreach.cosecha.area_atascada_segundos', 600);
        $edadLatido = Latido::edad('cosecha');

        // Latido fresco = cosecha viva. No interferir.
        if ($edadLatido !== null && $edadLatido < $umbral) {
            return 0;
        }

        $huerfanas = self::query()->where('estado', 'en_proceso')->get();

        if ($huerfanas->isEmpty()) {
            return 0;
        }

        // El proceso está muerto: liberamos el lock (por si murió sin soltarlo).
        Cache::lock('cosecha:run')->forceRelease();

        foreach ($huerfanas as $area) {
            $area->recuperarHuerfana();
        }

        return $huerfanas->count();
    }
}
