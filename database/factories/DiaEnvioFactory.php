<?php

namespace Database\Factories;

use App\Models\DiaEnvio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaEnvio>
 */
class DiaEnvioFactory extends Factory
{
    protected $model = DiaEnvio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => fake()->unique()->date(),
            'escalon' => 1,
            'cuota_planificada' => 10,
            'generados' => 0,
            'enviados' => 0,
            'fallidos' => 0,
            'rebotes_duros' => 0,
            'rebotes_blandos' => 0,
            'bajas' => 0,
            'respuestas' => 0,
            'tasa_rebote' => null,
            'salud' => 'verde',
            'nota' => null,
        ];
    }
}
