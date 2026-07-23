<?php

namespace Tests\Feature;

use App\Models\EventoInbox;
use App\Models\Lead;
use App\Models\Pagina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodarTest extends TestCase
{
    use RefreshDatabase;

    public function test_conserva_la_ultima_captura_de_cada_ruta(): void
    {
        $lead = Lead::factory()->create();

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'url' => 'https://ejemplo.test/',
            'capturada_at' => now()->subDays(400),
        ]);
        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'url' => 'https://ejemplo.test/?v=2',
            'capturada_at' => now()->subDays(300),
        ]);
        $ultima = Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'url' => 'https://ejemplo.test/?v=3',
            'capturada_at' => now()->subDays(10),
        ]);

        $this->artisan('sistema:podar')->assertSuccessful();

        $this->assertSame(1, Pagina::query()->count());
        $this->assertTrue(Pagina::query()->whereKey($ultima->id)->exists());
    }

    public function test_borra_eventos_ignorados_antiguos(): void
    {
        EventoInbox::query()->create([
            'email' => 'a@b.es',
            'tipo' => 'ignorado',
            'asunto' => 'viejo',
            'extracto' => 'x',
            'raw_hash' => 'hash-viejo',
            'recibido_at' => now()->subDays(100),
        ]);
        EventoInbox::query()->create([
            'email' => 'a@b.es',
            'tipo' => 'ignorado',
            'asunto' => 'reciente',
            'extracto' => 'y',
            'raw_hash' => 'hash-nuevo',
            'recibido_at' => now()->subDays(10),
        ]);
        EventoInbox::query()->create([
            'email' => 'a@b.es',
            'tipo' => 'respuesta',
            'asunto' => 'resp',
            'extracto' => 'z',
            'raw_hash' => 'hash-resp',
            'recibido_at' => now()->subDays(100),
        ]);

        $this->artisan('sistema:podar')->assertSuccessful();

        $this->assertSame(2, EventoInbox::query()->count());
        $this->assertFalse(EventoInbox::query()->where('raw_hash', 'hash-viejo')->exists());
        $this->assertTrue(EventoInbox::query()->where('raw_hash', 'hash-nuevo')->exists());
        $this->assertTrue(EventoInbox::query()->where('raw_hash', 'hash-resp')->exists());
    }

    public function test_dry_run_no_borra(): void
    {
        EventoInbox::query()->create([
            'email' => 'a@b.es',
            'tipo' => 'ignorado',
            'asunto' => 'viejo',
            'extracto' => 'x',
            'raw_hash' => 'hash-dry',
            'recibido_at' => now()->subDays(100),
        ]);

        $this->artisan('sistema:podar', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, EventoInbox::query()->count());
    }
}
