<?php

namespace Database\Factories;

use App\Models\EventoInbox;
use App\Models\Mensaje;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventoInbox>
 */
class EventoInboxFactory extends Factory
{
    protected $model = EventoInbox::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mensaje_id' => Mensaje::factory(),
            'email' => fake()->safeEmail(),
            'tipo' => fake()->randomElement(EventoInbox::TIPOS),
            'codigo_smtp' => null,
            'asunto' => fake()->sentence(3),
            'extracto' => fake()->sentence(8),
            'raw_hash' => sha1(Str::random(40)),
            'recibido_at' => now(),
        ];
    }
}
