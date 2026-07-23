<?php

namespace Tests\Feature;

use App\Models\DiaEnvio;
use App\Models\Lead;
use App\Models\Mensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecuperarEnvioTest extends TestCase
{
    use RefreshDatabase;

    public function test_mensaje_colgado_sin_message_id_vuelve_a_pendiente(): void
    {
        $mensaje = $this->mensaje([
            'estado' => 'enviando',
            'bloqueado_at' => now()->subMinutes(20),
            'message_id' => null,
            'enviado_at' => null,
        ]);

        $this->artisan('envio:recuperar')->assertSuccessful();

        $mensaje->refresh();
        $this->assertSame('pendiente', $mensaje->estado);
        $this->assertNull($mensaje->bloqueado_at);
    }

    public function test_mensaje_colgado_con_message_id_pasa_a_enviado(): void
    {
        Mail::fake();

        $mensaje = $this->mensaje([
            'estado' => 'enviando',
            'bloqueado_at' => now()->subMinutes(20),
            'message_id' => 'abc@silgodev.es',
            'enviado_at' => now()->subMinutes(18),
        ]);

        $this->artisan('envio:recuperar')->assertSuccessful();

        $mensaje->refresh();
        $this->assertSame('enviado', $mensaje->estado);
        $this->assertNull($mensaje->bloqueado_at);
        Mail::assertNothingSent();
    }

    public function test_fallido_con_dos_intentos_se_reprograma(): void
    {
        $mensaje = $this->mensaje([
            'estado' => 'fallido',
            'intentos' => 2,
            'programado_para' => now()->setTime(10, 0),
            'ultimo_error' => 'SMTP timeout',
        ]);

        $this->artisan('envio:recuperar')->assertSuccessful();

        $mensaje->refresh();
        $this->assertSame('pendiente', $mensaje->estado);
        $this->assertTrue($mensaje->programado_para->greaterThan(now()->addMinutes(25)));
        $this->assertTrue($mensaje->programado_para->lessThanOrEqualTo(now()->addMinutes(35)));
    }

    public function test_fallido_con_tres_intentos_no_se_reprograma(): void
    {
        $mensaje = $this->mensaje([
            'estado' => 'fallido',
            'intentos' => 3,
            'programado_para' => now()->setTime(10, 0),
            'ultimo_error' => 'SMTP timeout',
        ]);

        $this->artisan('envio:recuperar')->assertSuccessful();

        $this->assertSame('fallido', $mensaje->fresh()->estado);
    }

    public function test_pendiente_vencido_seis_horas_se_cancela(): void
    {
        $mensaje = $this->mensaje([
            'estado' => 'pendiente',
            'programado_para' => now()->subHours(7),
        ]);

        $this->artisan('envio:recuperar')->assertSuccessful();

        $mensaje->refresh();
        $this->assertSame('cancelado', $mensaje->estado);
        $this->assertStringContainsString('Ventana de envío superada', (string) $mensaje->ultimo_error);
    }

    public function test_pendiente_vencido_una_hora_no_se_toca(): void
    {
        $mensaje = $this->mensaje([
            'estado' => 'pendiente',
            'programado_para' => now()->subHour(),
        ]);

        $this->artisan('envio:recuperar')->assertSuccessful();

        $this->assertSame('pendiente', $mensaje->fresh()->estado);
    }

    public function test_recalcula_contadores_del_dia(): void
    {
        $this->mensaje([
            'estado' => 'enviado',
            'enviado_at' => now(),
            'programado_para' => now()->setTime(9, 0),
            'plantilla' => 'hosteleria',
            'destinatario' => 'a@a.es',
        ]);
        $this->mensaje([
            'estado' => 'enviado',
            'enviado_at' => now(),
            'programado_para' => now()->setTime(10, 0),
            'plantilla' => 'retail',
            'destinatario' => 'b@b.es',
            'lead' => Lead::factory()->create(),
        ]);
        $this->mensaje([
            'estado' => 'fallido',
            'intentos' => 3,
            'programado_para' => now()->setTime(11, 0),
            'plantilla' => 'salud',
            'destinatario' => 'c@c.es',
            'lead' => Lead::factory()->create(),
        ]);

        $dia = DiaEnvio::paraFecha(today());
        $dia->update([
            'enviados' => 99,
            'fallidos' => 99,
            'generados' => 99,
        ]);

        $this->artisan('envio:recuperar')->assertSuccessful();

        $dia->refresh();
        $this->assertSame(2, $dia->enviados);
        $this->assertSame(1, $dia->fallidos);
        $this->assertSame(3, $dia->generados);
    }

    public function test_dry_run_no_modifica_nada(): void
    {
        $colgado = $this->mensaje([
            'estado' => 'enviando',
            'bloqueado_at' => now()->subMinutes(20),
            'message_id' => null,
        ]);
        $fallido = $this->mensaje([
            'estado' => 'fallido',
            'intentos' => 1,
            'programado_para' => now()->setTime(10, 0),
            'plantilla' => 'retail',
            'destinatario' => 'f@f.es',
            'lead' => Lead::factory()->create(),
        ]);
        $vencido = $this->mensaje([
            'estado' => 'pendiente',
            'programado_para' => now()->subHours(8),
            'plantilla' => 'salud',
            'destinatario' => 'v@v.es',
            'lead' => Lead::factory()->create(),
        ]);

        $this->artisan('envio:recuperar', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('enviando', $colgado->fresh()->estado);
        $this->assertSame('fallido', $fallido->fresh()->estado);
        $this->assertSame('pendiente', $vencido->fresh()->estado);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function mensaje(array $attrs = []): Mensaje
    {
        $lead = $attrs['lead'] ?? Lead::factory()->create(['estado' => 'en_cola']);
        unset($attrs['lead']);

        return Mensaje::factory()->create(array_merge([
            'lead_id' => $lead->id,
            'destinatario' => 'destino@ejemplo.es',
            'plantilla' => 'hosteleria',
            'paso' => 1,
            'asunto' => 'asunto de prueba',
            'cuerpo_texto' => 'texto',
            'cuerpo_html' => '<p>texto</p>',
            'programado_para' => now()->subMinute(),
            'estado' => 'pendiente',
            'intentos' => 0,
        ], $attrs));
    }
}
