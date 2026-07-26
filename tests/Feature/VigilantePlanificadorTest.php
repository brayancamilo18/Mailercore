<?php

namespace Tests\Feature;

use App\Services\Envio\PlanificadorDiario;
use App\Services\Soporte\Latido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VigilantePlanificadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['outreach.envio.activo' => true]);
        config(['outreach.envio.dias' => [1, 2, 3, 4]]);
    }

    public function test_en_fin_de_semana_mantiene_latido_del_planificador(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00', 'Europe/Madrid')); // domingo

        $this->artisan('sistema:vigilante')->assertExitCode(0);

        $this->assertTrue(Latido::estaVivo('planificador'));
        $detalle = Cache::get('latido:planificador')['detalle'] ?? '';
        $this->assertStringContainsString('En espera', (string) $detalle);
    }

    public function test_antes_de_las_siete_marca_programado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 06:30:00', 'Europe/Madrid')); // lunes

        $this->artisan('sistema:vigilante')->assertExitCode(0);

        $this->assertTrue(Latido::estaVivo('planificador'));
        $detalle = Cache::get('latido:planificador')['detalle'] ?? '';
        $this->assertStringContainsString('07:00', (string) $detalle);
    }

    public function test_si_falta_la_cola_tras_las_siete_la_regenera(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 08:00:00', 'Europe/Madrid')); // lunes

        $this->assertFalse(PlanificadorDiario::yaPlanificado(Carbon::today()));

        $this->artisan('sistema:vigilante')->assertExitCode(0);

        $this->assertTrue(PlanificadorDiario::yaPlanificado(Carbon::today()));
        $this->assertTrue(Latido::estaVivo('planificador'));
    }
}
