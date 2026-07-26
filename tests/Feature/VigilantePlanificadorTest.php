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

    public function test_antes_de_las_20_en_domingo_espera(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00', 'Europe/Madrid')); // domingo

        $this->artisan('sistema:vigilante')->assertExitCode(0);

        $this->assertTrue(Latido::estaVivo('planificador'));
        $this->assertFalse(PlanificadorDiario::yaPlanificado(Carbon::parse('2026-07-27', 'Europe/Madrid')));
        $detalle = Cache::get('latido:planificador')['detalle'] ?? '';
        $this->assertStringContainsString('20:00', (string) $detalle);
    }

    public function test_tras_las_20_prepara_la_cola_del_lunes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-26 20:10:00', 'Europe/Madrid')); // domingo noche

        $lunes = Carbon::parse('2026-07-27', 'Europe/Madrid')->startOfDay();
        $this->assertFalse(PlanificadorDiario::yaPlanificado($lunes));

        $this->artisan('sistema:vigilante')->assertExitCode(0);

        $this->assertTrue(PlanificadorDiario::yaPlanificado($lunes));
        $this->assertTrue(Latido::estaVivo('planificador'));
    }

    public function test_antes_de_las_siete_en_lunes_con_cola_ya_lista(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-26 20:10:00', 'Europe/Madrid'));
        $this->artisan('sistema:vigilante')->assertExitCode(0);

        Carbon::setTestNow(Carbon::parse('2026-07-27 06:30:00', 'Europe/Madrid'));
        $this->artisan('sistema:vigilante')->assertExitCode(0);

        $this->assertTrue(Latido::estaVivo('planificador'));
        $this->assertTrue(PlanificadorDiario::yaPlanificado(Carbon::parse('2026-07-27', 'Europe/Madrid')));
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
