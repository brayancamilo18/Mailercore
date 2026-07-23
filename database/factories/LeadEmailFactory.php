<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadEmail>
 */
class LeadEmailFactory extends Factory
{
    protected $model = LeadEmail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();
        $prefijo = explode('@', $email)[0];

        return [
            'lead_id' => Lead::factory(),
            'email' => $email,
            'tipo' => 'rol',
            'prefijo' => $prefijo,
            'origen' => 'web',
            'url_origen' => null,
            'es_principal' => false,
            'prioridad' => 9,
            'mx_ok' => null,
            'es_catch_all' => null,
            'estado_verificacion' => null,
            'verificado_at' => null,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn (array $attributes) => [
            'es_principal' => true,
            'prioridad' => 1,
        ]);
    }

    public function valido(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_verificacion' => 'valido',
            'mx_ok' => true,
            'verificado_at' => now(),
        ]);
    }
}
