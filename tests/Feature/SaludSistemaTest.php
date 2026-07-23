<?php

namespace Tests\Feature;

use App\Models\Mensaje;
use App\Services\Soporte\Latido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SaludSistemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_devuelve_cero_con_todo_fresco(): void
    {
        $this->marcarTodosLosLatidos();

        $this->artisan('sistema:salud')
            ->assertExitCode(0);
    }

    public function test_devuelve_dos_si_el_despachador_esta_muerto(): void
    {
        $this->marcarTodosLosLatidos();
        Cache::forget('latido:despachador');

        $this->artisan('sistema:salud')
            ->assertExitCode(2);
    }

    public function test_devuelve_dos_con_mensajes_colgados(): void
    {
        $this->marcarTodosLosLatidos();

        Mensaje::factory()->create([
            'estado' => 'enviando',
            'bloqueado_at' => now()->subMinutes(20),
            'programado_para' => now()->subHour(),
        ]);

        $this->artisan('sistema:salud')
            ->assertExitCode(2);
    }

    public function test_devuelve_uno_con_pendientes_vencidos(): void
    {
        $this->marcarTodosLosLatidos();

        Mensaje::factory()->pendiente()->create([
            'programado_para' => now()->subHours(2),
        ]);

        $this->artisan('sistema:salud')
            ->assertExitCode(1);
    }

    public function test_salida_json_es_valida(): void
    {
        $this->marcarTodosLosLatidos();

        $codigo = $this->withoutMockingConsoleOutput()
            ->artisan('sistema:salud', ['--json' => true]);

        $this->assertSame(0, $codigo);

        $payload = json_decode(Artisan::output(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('codigo', $payload);
        $this->assertArrayHasKey('comprobaciones', $payload);
        $this->assertIsArray($payload['comprobaciones']);
    }

    private function marcarTodosLosLatidos(): void
    {
        foreach (array_keys(config('outreach.latido.procesos')) as $proceso) {
            Latido::marcar($proceso);
        }
    }
}
