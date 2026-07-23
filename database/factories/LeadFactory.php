<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dominio = fake()->domainName();
        $sectores = array_keys(config('sectores'));

        return [
            'nombre' => fake()->company(),
            'website' => 'https://'.$dominio,
            'website_dominio' => $dominio,
            'place_id' => 'node/'.fake()->unique()->numberBetween(1, 999999),
            'fuente' => 'overpass',
            'estado' => 'nuevo',
            'sector' => fake()->randomElement($sectores),
            'capturado_at' => now(),
        ];
    }

    public function auditado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'auditado',
            'rastreado_at' => now(),
        ]);
    }

    public function conSector(string $sector): static
    {
        return $this->state(fn (array $attributes) => [
            'sector' => $sector,
        ]);
    }

    public function sinWeb(): static
    {
        return $this->state(fn (array $attributes) => [
            'website' => null,
            'website_dominio' => null,
        ]);
    }
}
