<?php

namespace Database\Factories;

use App\Models\Auditoria;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auditoria>
 */
class AuditoriaFactory extends Factory
{
    protected $model = Auditoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'puntuacion' => 0,
            'hallazgos' => null,
            'auditada_at' => now(),
        ];
    }
}
