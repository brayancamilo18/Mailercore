<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\DiaEnvioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiaEnvio extends Model
{
    /** @use HasFactory<DiaEnvioFactory> */
    use HasFactory;

    protected $table = 'dias_envio';

    public const SALUD = [
        'verde',
        'ambar',
        'rojo',
        'parado',
    ];

    protected $fillable = [
        'fecha', 'escalon', 'cuota_planificada', 'generados', 'enviados',
        'fallidos', 'rebotes_duros', 'rebotes_blandos', 'bajas', 'respuestas',
        'tasa_rebote', 'salud', 'nota',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tasa_rebote' => 'decimal:2',
        ];
    }

    public static function paraFecha(Carbon $fecha): self
    {
        $existente = self::query()
            ->whereDate('fecha', $fecha->toDateString())
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        return self::query()->create([
            'fecha' => $fecha->toDateString(),
            'escalon' => 1,
            'cuota_planificada' => 0,
            'salud' => 'verde',
        ]);
    }

    public function incrementarContador(string $columna, int $cantidad = 1): void
    {
        $this->increment($columna, $cantidad);
    }
}
