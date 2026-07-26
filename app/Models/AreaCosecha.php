<?php

namespace App\Models;

use App\Services\Soporte\Latido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'pais_codigo',
        'nombre',
        'codigo_mapa',
        'admin_level',
        'estado',
        'prioridad',
        'leads_encontrados',
        'emails_encontrados',
        'candidatos_vistos',
        'omitidos',
        'ciclos_completados',
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

    /**
     * @return BelongsTo<PaisCosecha, $this>
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(PaisCosecha::class, 'pais_codigo', 'codigo');
    }

    /**
     * Siguiente área pendiente de un país que aún no ha agotado sus ciclos.
     */
    public static function siguientePendiente(): ?self
    {
        return self::query()
            ->where('areas_cosecha.estado', 'pendiente')
            ->whereHas('pais', function (Builder $q): void {
                $q->whereColumn('ciclos_completados', '<', 'max_ciclos')
                    ->where('estado', '!=', 'hecho');
            })
            ->join('paises_cosecha', 'paises_cosecha.codigo', '=', 'areas_cosecha.pais_codigo')
            ->orderBy('paises_cosecha.prioridad')
            ->orderBy('areas_cosecha.prioridad')
            ->orderBy('areas_cosecha.id')
            ->select('areas_cosecha.*')
            ->first();
    }

    /** @param  Builder<AreaCosecha>  $query */
    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('prioridad')->orderBy('nombre');
    }

    /** @param  Builder<AreaCosecha>  $query */
    public function scopeDelPais(Builder $query, string $codigo): Builder
    {
        return $query->where('pais_codigo', $codigo);
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
     * @deprecated Usar PaisCosecha::procesarCiclosCompletos()
     */
    public static function reiniciarCicloCompleto(): int
    {
        return PaisCosecha::procesarCiclosCompletos();
    }

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

    public static function recuperarHuerfanasSiMuertas(): int
    {
        $umbral = (int) config('outreach.cosecha.area_atascada_segundos', 600);
        $edadLatido = Latido::edad('cosecha');

        if ($edadLatido !== null && $edadLatido < $umbral) {
            return 0;
        }

        $huerfanas = self::query()->where('estado', 'en_proceso')->get();

        if ($huerfanas->isEmpty()) {
            return 0;
        }

        Cache::lock('cosecha:run')->forceRelease();

        foreach ($huerfanas as $area) {
            $area->recuperarHuerfana();
        }

        return $huerfanas->count();
    }
}
