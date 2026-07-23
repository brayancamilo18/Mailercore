<?php

namespace Tests\Feature;

use App\Jobs\EnviarMensajeJob;
use App\Mail\CorreoOutreach;
use App\Models\DiaEnvio;
use App\Models\Lead;
use App\Models\Mensaje;
use App\Models\Suppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnviarMensajeJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'outreach.envio.activo' => true,
            'outreach.envio.remitente.email_baja' => 'baja@silgodev.es',
            'outreach.envio.remitente.responder_a' => 'hola@silgodev.es',
            'app.url' => 'https://silgodev.es',
        ]);
    }

    public function test_envia_el_correo_y_marca_enviado(): void
    {
        Mail::fake();

        $mensaje = $this->mensajePendiente();

        (new EnviarMensajeJob($mensaje->id))->handle();

        Mail::assertSent(CorreoOutreach::class, 1);

        $mensaje->refresh();
        $this->assertSame('enviado', $mensaje->estado);
        $this->assertNotNull($mensaje->enviado_at);
        $this->assertSame('contactado', $mensaje->lead->estado);
    }

    public function test_doble_ejecucion_solo_envia_una_vez(): void
    {
        Mail::fake();

        $mensaje = $this->mensajePendiente();

        (new EnviarMensajeJob($mensaje->id))->handle();
        (new EnviarMensajeJob($mensaje->id))->handle();

        Mail::assertSent(CorreoOutreach::class, 1);
        $this->assertSame('enviado', $mensaje->fresh()->estado);
    }

    public function test_no_envia_si_el_destinatario_esta_suprimido(): void
    {
        Mail::fake();

        $mensaje = $this->mensajePendiente(['destinatario' => 'baja@ejemplo.es']);
        Suppression::registrar('baja@ejemplo.es', 'baja');

        (new EnviarMensajeJob($mensaje->id))->handle();

        Mail::assertNothingSent();
        $this->assertSame('cancelado', $mensaje->fresh()->estado);
        $this->assertSame('baja', $mensaje->lead->fresh()->estado);
    }

    public function test_fallo_smtp_marca_fallido_y_no_contacta_al_lead(): void
    {
        $mensaje = $this->mensajePendiente();
        $leadEstado = $mensaje->lead->estado;

        $pending = \Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP down'));

        Mail::shouldReceive('to')
            ->once()
            ->with($mensaje->destinatario)
            ->andReturn($pending);

        (new EnviarMensajeJob($mensaje->id))->handle();

        $mensaje->refresh();
        $this->assertSame('fallido', $mensaje->estado);
        $this->assertSame($leadEstado, $mensaje->lead->fresh()->estado);
        $this->assertSame('en_cola', $mensaje->lead->estado);
    }

    public function test_actualiza_contador_del_dia(): void
    {
        Mail::fake();

        $mensaje = $this->mensajePendiente([
            'programado_para' => now()->startOfDay()->setTime(10, 0),
        ]);

        (new EnviarMensajeJob($mensaje->id))->handle();

        $dia = DiaEnvio::query()->whereDate('fecha', now()->toDateString())->first();
        $this->assertNotNull($dia);
        $this->assertSame(1, $dia->enviados);
    }

    public function test_paso_dos_deja_el_lead_en_seguimiento(): void
    {
        Mail::fake();

        $mensaje = $this->mensajePendiente(['paso' => 2]);

        (new EnviarMensajeJob($mensaje->id))->handle();

        $this->assertSame('seguimiento', $mensaje->lead->fresh()->estado);
        $this->assertSame('enviado', $mensaje->fresh()->estado);
    }

    public function test_no_hace_nada_si_el_mensaje_no_esta_pendiente(): void
    {
        Mail::fake();

        $mensaje = $this->mensajePendiente(['estado' => 'enviado', 'enviado_at' => now()]);

        (new EnviarMensajeJob($mensaje->id))->handle();

        Mail::assertNothingSent();
    }

    public function test_comando_despacha_solo_los_vencidos(): void
    {
        Queue::fake();

        $vencido = $this->mensajePendiente([
            'programado_para' => now()->subMinute(),
            'destinatario' => 'vencido@a.es',
            'plantilla' => 'hosteleria',
        ]);
        $futuro = $this->mensajePendiente([
            'programado_para' => now()->addHour(),
            'destinatario' => 'futuro@b.es',
            'plantilla' => 'retail',
            'lead' => Lead::factory()->create(['estado' => 'en_cola']),
        ]);

        $this->artisan('envio:despachar', ['--limite' => 20])
            ->assertSuccessful();

        Queue::assertPushed(EnviarMensajeJob::class, 1);
        Queue::assertPushed(
            EnviarMensajeJob::class,
            fn (EnviarMensajeJob $job): bool => $job->mensajeId === $vencido->id
        );
        Queue::assertNotPushed(
            EnviarMensajeJob::class,
            fn (EnviarMensajeJob $job): bool => $job->mensajeId === $futuro->id
        );
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function mensajePendiente(array $attrs = []): Mensaje
    {
        $lead = $attrs['lead'] ?? Lead::factory()->create([
            'estado' => 'en_cola',
        ]);
        unset($attrs['lead']);

        return Mensaje::factory()->create(array_merge([
            'lead_id' => $lead->id,
            'destinatario' => 'destino@ejemplo.es',
            'plantilla' => 'hosteleria',
            'paso' => 1,
            'asunto' => 'vuestra web en el móvil',
            'cuerpo_texto' => "Hola,\n\nPrueba.\n\n---\nPie",
            'cuerpo_html' => '<p>Hola,</p><p>Prueba.</p>',
            'programado_para' => now()->subMinute(),
            'estado' => 'pendiente',
            'message_id' => 'test-'.uniqid('', true).'@silgodev.es',
        ], $attrs));
    }
}
