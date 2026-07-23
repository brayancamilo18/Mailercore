<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Mensaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mensaje>
 */
class MensajeFactory extends Factory
{
    protected $model = Mensaje::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'lead_email_id' => null,
            'destinatario' => fake()->unique()->safeEmail(),
            'plantilla' => fake()->randomElement(array_keys(config('sectores'))),
            'paso' => 1,
            'asunto' => fake()->sentence(4),
            'cuerpo_texto' => fake()->paragraph(),
            'cuerpo_html' => null,
            'programado_para' => now(),
            'estado' => 'pendiente',
            'intentos' => 0,
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'pendiente',
        ]);
    }

    public function enviado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'enviado',
            'enviado_at' => now(),
            'message_id' => '<'.fake()->uuid().'@local>',
        ]);
    }
}
