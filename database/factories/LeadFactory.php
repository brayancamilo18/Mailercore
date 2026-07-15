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
        return [
            'place_id' => 'node/'.fake()->unique()->numberBetween(1_000_000, 9_999_999),
            'name' => fake()->company(),
            'website' => 'https://'.fake()->unique()->domainName(),
            'email' => fake()->unique()->safeEmail(),
            'email_check' => 'valido',
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'status' => 'nuevo',
            'segmento' => 'agencia',
            'captured_at' => now(),
            'contacted_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Lead listo para el primer envío (status nuevo + email).
     */
    public function readyToSend(): static
    {
        return $this->state(fn (): array => [
            'status' => 'nuevo',
            'email' => fake()->unique()->safeEmail(),
            'email_check' => 'valido',
            'contacted_at' => null,
        ]);
    }

    /**
     * Lead ya contactado (cuenta para cupo / warm-up).
     */
    public function contactado(?\DateTimeInterface $cuando = null): static
    {
        return $this->state(fn (): array => [
            'status' => 'contactado',
            'contacted_at' => $cuando ?? now(),
        ]);
    }

    /**
     * Sin email: no entra en agencies:send.
     */
    public function sinEmail(): static
    {
        return $this->state(fn (): array => [
            'status' => 'sin_email',
            'email' => null,
            'email_check' => null,
        ]);
    }
}
