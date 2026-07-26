<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaisCosecha extends Model
{
    protected $table = 'paises_cosecha';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    protected $keyType = 'string';

    public const ESTADOS = ['pendiente', 'en_proceso', 'hecho'];

    protected $fillable = [
        'codigo',
        'nombre',
        'prioridad',
        'estado',
        'ciclos_completados',
        'max_ciclos',
        'mapa_motor',
        'mapa_src',
    ];

    /**
     * @return HasMany<AreaCosecha, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(AreaCosecha::class, 'pais_codigo', 'codigo');
    }

    /** @param  Builder<PaisCosecha>  $query */
    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('prioridad')->orderBy('nombre');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereColumn('ciclos_completados', '<', 'max_ciclos')
            ->where('estado', '!=', 'hecho');
    }

    public function completado(): bool
    {
        return $this->estado === 'hecho'
            || (int) $this->ciclos_completados >= (int) $this->max_ciclos;
    }

    public function todasAreasTerminadas(): bool
    {
        if (! $this->areas()->exists()) {
            return false;
        }

        return ! $this->areas()
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->exists();
    }

    /**
     * Si el país terminó todas sus áreas y aún le quedan ciclos, reinicia áreas
     * y suma un ciclo. Si alcanzó el máximo, lo marca hecho.
     *
     * @return int áreas reiniciadas (0 si el país quedó hecho o no tocaba)
     */
    public function avanzarSiCicloCompleto(): int
    {
        if ($this->completado() || ! $this->todasAreasTerminadas()) {
            return 0;
        }

        $this->ciclos_completados = (int) $this->ciclos_completados + 1;

        if ($this->ciclos_completados >= (int) $this->max_ciclos) {
            $this->estado = 'hecho';
            $this->save();

            return 0;
        }

        $this->estado = 'en_proceso';
        $this->save();

        $reiniciadas = 0;
        foreach ($this->areas()->whereIn('estado', ['hecho', 'error'])->get() as $area) {
            $area->reiniciar();
            $reiniciadas++;
        }

        return $reiniciadas;
    }

    /** @return int países que avanzaron de ciclo o se cerraron */
    public static function procesarCiclosCompletos(): int
    {
        $tocados = 0;

        foreach (self::query()->activos()->ordenados()->get() as $pais) {
            if (! $pais->todasAreasTerminadas()) {
                continue;
            }

            $antes = (int) $pais->ciclos_completados;
            $pais->avanzarSiCicloCompleto();
            $pais->refresh();

            if ((int) $pais->ciclos_completados > $antes || $pais->estado === 'hecho') {
                $tocados++;
            }
        }

        return $tocados;
    }
}
