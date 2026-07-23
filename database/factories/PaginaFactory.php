<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Pagina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pagina>
 */
class PaginaFactory extends Factory
{
    protected $model = Pagina::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = mb_substr(fake()->sentence(8), 0, 40);
        $meta = mb_substr(fake()->paragraph(3), 0, 140);
        $dominio = fake()->domainName();

        return [
            'lead_id' => Lead::factory(),
            'url' => 'https://'.$dominio.'/',
            'ruta' => '/',
            'http_status' => 200,
            'content_type' => 'text/html',
            'bytes' => 45000,
            'respuesta_ms' => 800,
            'title' => $title,
            'title_longitud' => 40,
            'meta_description' => $meta,
            'meta_desc_longitud' => 140,
            'h1_texto' => fake()->sentence(4),
            'h1_total' => 1,
            'h2_total' => 3,
            'idioma' => 'es',
            'tiene_viewport' => true,
            'tiene_favicon' => true,
            'tiene_og' => true,
            'tiene_jsonld' => false,
            'es_https' => true,
            'cert_valido' => true,
            'capturada_at' => now(),
        ];
    }

    public function sinViewport(): static
    {
        return $this->state(fn (array $attributes) => [
            'tiene_viewport' => false,
        ]);
    }

    public function lenta(): static
    {
        return $this->state(fn (array $attributes) => [
            'respuesta_ms' => 4500,
        ]);
    }

    public function sinHttps(): static
    {
        return $this->state(fn (array $attributes) => [
            'es_https' => false,
            'cert_valido' => false,
            'url' => str_replace('https://', 'http://', $attributes['url'] ?? 'http://ejemplo.es/'),
        ]);
    }

    public function abandonada(): static
    {
        $anios = (int) config('outreach.auditoria.anios_web_abandonada', 2);

        return $this->state(fn (array $attributes) => [
            'anio_copyright' => now()->year - $anios - 1,
        ]);
    }

    public function contactoRota(): static
    {
        return $this->state(fn (array $attributes) => [
            'ruta' => '/contacto',
            'url' => rtrim($attributes['url'] ?? 'https://ejemplo.es/', '/').'/contacto',
            'http_status' => 404,
            'error' => 'Not Found',
        ]);
    }
}
