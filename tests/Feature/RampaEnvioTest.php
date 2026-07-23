<?php

namespace Tests\Feature;

use App\Models\DiaEnvio;
use App\Models\EventoInbox;
use App\Models\Mensaje;
use App\Services\Envio\RampaEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RampaEnvioTest extends TestCase
{
    use RefreshDatabase;

    public function test_primer_dia_devuelve_diez(): void
    {
        $resultado = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-10')); // martes

        $this->assertSame(10, $resultado['cuota']);
        $this->assertSame(1, $resultado['escalon']);
        $this->assertSame('verde', $resultado['salud']);
    }

    public function test_escalon_sube_con_dias_consecutivos(): void
    {
        $this->crearMensajesEnviados(30);

        // 5 días laborables anteriores con envíos → racha 6 → escalón 2 → cuota 15
        $fecha = Carbon::parse('2026-03-16'); // lunes
        foreach ([
            '2026-03-13', // vie
            '2026-03-12', // jue
            '2026-03-11', // mié
            '2026-03-10', // mar
            '2026-03-09', // lun
        ] as $dia) {
            DiaEnvio::factory()->create([
                'fecha' => $dia,
                'enviados' => 5,
            ]);
        }

        $resultado = app(RampaEnvio::class)->calcular($fecha);

        $this->assertSame(2, $resultado['escalon']);
        $this->assertSame(15, $resultado['cuota']);
    }

    public function test_hueco_de_cinco_dias_retrocede_escalon(): void
    {
        $this->crearMensajesEnviados(30);

        // Racha lun–jue durante dos semanas; último envío el jueves 5.
        // Vie/sáb/dom no rompen diasConsecutivos. Hueco jueves→lunes = 4 > 3
        // (y jueves→martes = 5) activa rachaRota y baja el escalón.
        foreach ([
            '2026-02-23', '2026-02-24', '2026-02-25', '2026-02-26',
            '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05',
        ] as $dia) {
            DiaEnvio::factory()->create([
                'fecha' => $dia,
                'enviados' => 3,
            ]);
        }

        $this->assertSame(5, (int) Carbon::parse('2026-03-05')->diffInDays(Carbon::parse('2026-03-10')));

        $sinHueco = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-06')); // viernes
        DiaEnvio::query()->whereDate('fecha', '2026-03-06')->delete();

        $conHueco = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-09')); // lunes, hueco 4

        $this->assertGreaterThan(1, $sinHueco['escalon']);
        $this->assertSame($sinHueco['escalon'] - 1, $conHueco['escalon']);
    }

    public function test_fin_de_semana_no_rompe_la_racha(): void
    {
        $this->crearMensajesEnviados(30);

        // Vie con envíos (hueco vie→lun = 3, no rompe rachaRota);
        // sáb/dom vacíos no rompen diasConsecutivos.
        DiaEnvio::factory()->create(['fecha' => '2026-03-06', 'enviados' => 4]); // vie
        DiaEnvio::factory()->create(['fecha' => '2026-03-05', 'enviados' => 4]); // jue
        DiaEnvio::factory()->create(['fecha' => '2026-03-04', 'enviados' => 4]); // mié
        DiaEnvio::factory()->create(['fecha' => '2026-03-03', 'enviados' => 4]); // mar
        DiaEnvio::factory()->create(['fecha' => '2026-03-02', 'enviados' => 4]); // lun

        $resultado = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-09')); // lun

        // 5 días previos + hoy = 6 → escalón 2 → cuota 15
        $this->assertSame(2, $resultado['escalon']);
        $this->assertSame(15, $resultado['cuota']);
    }

    public function test_tasa_rebote_del_cinco_por_ciento_reduce_a_la_mitad(): void
    {
        $mensajes = $this->crearMensajesEnviados(200);

        foreach ($mensajes->take(10) as $mensaje) {
            EventoInbox::factory()->create([
                'mensaje_id' => $mensaje->id,
                'tipo' => 'rebote_duro',
            ]);
        }

        $resultado = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-10'));

        $this->assertSame('rojo', $resultado['salud']);
        $this->assertSame(5.0, $resultado['tasa_rebote']);
        $this->assertSame(5, $resultado['cuota']);
    }

    public function test_tasa_del_siete_por_ciento_para_el_envio(): void
    {
        $mensajes = $this->crearMensajesEnviados(200);

        foreach ($mensajes->take(14) as $mensaje) {
            EventoInbox::factory()->create([
                'mensaje_id' => $mensaje->id,
                'tipo' => 'rebote_duro',
            ]);
        }

        $resultado = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-10'));

        $this->assertSame('parado', $resultado['salud']);
        $this->assertSame(7.0, $resultado['tasa_rebote']);
        $this->assertSame(0, $resultado['cuota']);
    }

    public function test_una_queja_pone_salud_en_rojo(): void
    {
        $mensajes = $this->crearMensajesEnviados(30);

        EventoInbox::factory()->create([
            'mensaje_id' => $mensajes->first()->id,
            'tipo' => 'queja',
        ]);

        $resultado = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-10'));

        $this->assertSame('rojo', $resultado['salud']);
        $this->assertSame(5, $resultado['cuota']);
    }

    public function test_con_menos_de_treinta_envios_la_cuota_se_limita_a_diez(): void
    {
        $this->crearMensajesEnviados(10);

        foreach (['2026-03-09', '2026-03-10', '2026-03-11', '2026-03-12', '2026-03-13'] as $dia) {
            DiaEnvio::factory()->create(['fecha' => $dia, 'enviados' => 2]);
        }

        $resultado = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-16'));

        $this->assertSame(2, $resultado['escalon']);
        $this->assertSame(10, $resultado['cuota']);
        $this->assertSame('verde', $resultado['salud']);
    }

    public function test_nunca_supera_max_diario(): void
    {
        config(['outreach.envio.max_diario' => 7]);
        $this->crearMensajesEnviados(30);

        $resultado = app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-10'));

        $this->assertSame(7, $resultado['cuota']);
    }

    public function test_guarda_la_fila_de_dias_envio(): void
    {
        app(RampaEnvio::class)->calcular(Carbon::parse('2026-03-10'));

        $dia = DiaEnvio::query()->whereDate('fecha', '2026-03-10')->first();

        $this->assertNotNull($dia);
        $this->assertSame(10, $dia->cuota_planificada);
        $this->assertSame(1, $dia->escalon);
        $this->assertSame('verde', $dia->salud);
    }

    /**
     * @return Collection<int, Mensaje>
     */
    private function crearMensajesEnviados(int $cantidad): Collection
    {
        return Mensaje::factory()
            ->count($cantidad)
            ->create([
                'estado' => 'enviado',
                'enviado_at' => now(),
            ]);
    }
}
