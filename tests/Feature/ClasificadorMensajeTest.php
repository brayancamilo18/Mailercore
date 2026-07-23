<?php

namespace Tests\Feature;

use App\DTO\MensajeEntrante;
use App\Models\Lead;
use App\Models\Mensaje;
use App\Services\Inbox\ClasificadorMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClasificadorMensajeTest extends TestCase
{
    use RefreshDatabase;

    private ClasificadorMensaje $clasificador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clasificador = app(ClasificadorMensaje::class);
    }

    public function test_respuesta_interesada_con_correo_citado_no_es_baja(): void
    {
        $mensaje = new MensajeEntrante(
            desdeEmail: 'info@restaurante.es',
            desdeNombre: 'Restaurante El Puerto',
            asunto: 'Re: la carta en el móvil',
            cuerpo: "Hola Camilo,\n\nMe interesa, mándame lo que has visto.\n\n"
                  ."El 15 jul 2026, Camilo Silva escribió:\n"
                  .'> Si no quieres recibir más mensajes míos, responde BAJA a este correo.',
            cabeceras: [],
            messageId: '<abc@mail.es>',
            inReplyTo: null,
            references: null,
            recibidoAt: now(),
            rawHash: 'hash1',
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('respuesta', $resultado->tipo);
    }

    public function test_dsn_cinco_uno_uno_es_rebote_duro(): void
    {
        $mensaje = $this->entrante(
            desdeEmail: 'mailer-daemon@mx.ejemplo.es',
            asunto: 'Delivery Status Notification',
            cuerpo: "Final-Recipient: rfc822; x@y.es\nStatus: 5.1.1\nDiagnostic-Code: smtp; 550 User unknown",
            cabeceras: ['content-type' => 'multipart/report; report-type=delivery-status'],
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('rebote_duro', $resultado->tipo);
        $this->assertSame('x@y.es', $resultado->emailAfectado);
        $this->assertSame('5.1.1', $resultado->codigoSmtp);
    }

    public function test_dsn_cuatro_dos_dos_es_rebote_blando(): void
    {
        $mensaje = $this->entrante(
            desdeEmail: 'postmaster@mx.ejemplo.es',
            asunto: 'Mail delivery failed',
            cuerpo: "Final-Recipient: rfc822; lleno@cliente.es\nStatus: 4.2.2\n",
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('rebote_blando', $resultado->tipo);
        $this->assertSame('4.2.2', $resultado->codigoSmtp);
    }

    public function test_rebote_sin_codigo_es_blando(): void
    {
        $mensaje = $this->entrante(
            desdeEmail: 'mailer-daemon@servidor.es',
            asunto: 'Undelivered Mail Returned to Sender',
            cuerpo: "Could not be delivered to <alguien@destino.es>\nNo hay código de estado.",
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('rebote_blando', $resultado->tipo);
        $this->assertNull($resultado->codigoSmtp);
    }

    public function test_autorespuesta_de_vacaciones_se_ignora(): void
    {
        $mensaje = $this->entrante(
            desdeEmail: 'jefe@empresa.es',
            asunto: 'Automatic reply: fuera de la oficina',
            cuerpo: 'Estoy de vacaciones hasta el lunes.',
            cabeceras: ['auto-submitted' => 'auto-replied'],
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('ignorado', $resultado->tipo);
    }

    public function test_baja_en_el_asunto(): void
    {
        $mensaje = $this->entrante(
            desdeEmail: 'cliente@negocio.es',
            asunto: 'BAJA',
            cuerpo: 'Hola.',
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('baja', $resultado->tipo);
    }

    public function test_baja_en_texto_nuevo(): void
    {
        $mensaje = $this->entrante(
            desdeEmail: 'cliente@negocio.es',
            asunto: 'Re: vuestra web',
            cuerpo: 'Por favor dadme de baja',
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('baja', $resultado->tipo);
    }

    public function test_queja_de_abuse(): void
    {
        $mensaje = $this->entrante(
            desdeEmail: 'abuse@proveedor.es',
            asunto: 'Spam complaint',
            cuerpo: 'This is a spam complaint about your message.',
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('queja', $resultado->tipo);
    }

    public function test_correlaciona_por_in_reply_to(): void
    {
        $enviado = Mensaje::factory()->create([
            'lead_id' => Lead::factory(),
            'estado' => 'enviado',
            'enviado_at' => now()->subDay(),
            'message_id' => 'orig-123@silgodev.es',
            'destinatario' => 'info@restaurante.es',
            'plantilla' => 'hosteleria',
            'paso' => 1,
        ]);

        $mensaje = $this->entrante(
            desdeEmail: 'info@restaurante.es',
            asunto: 'Re: la carta',
            cuerpo: 'Me interesa, hablamos.',
            inReplyTo: '<orig-123@silgodev.es>',
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('respuesta', $resultado->tipo);
        $this->assertSame($enviado->id, $resultado->mensajeId);
    }

    public function test_correlaciona_por_destinatario_si_no_hay_in_reply_to(): void
    {
        $enviado = Mensaje::factory()->create([
            'lead_id' => Lead::factory(),
            'estado' => 'enviado',
            'enviado_at' => now()->subDay(),
            'message_id' => 'otro@silgodev.es',
            'destinatario' => 'dueño@bar.es',
            'plantilla' => 'hosteleria',
            'paso' => 1,
        ]);

        $mensaje = $this->entrante(
            desdeEmail: 'dueño@bar.es',
            asunto: 'Re: vuestra web',
            cuerpo: 'Sí, mándame más info por favor.',
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('respuesta', $resultado->tipo);
        $this->assertSame($enviado->id, $resultado->mensajeId);
    }

    public function test_extracto_no_supera_trescientos_caracteres(): void
    {
        $largo = str_repeat('palabra ', 80);

        $mensaje = $this->entrante(
            desdeEmail: 'a@b.es',
            asunto: 'Re: web',
            cuerpo: $largo,
        );

        $resultado = $this->clasificador->clasificar($mensaje);

        $this->assertSame('respuesta', $resultado->tipo);
        $this->assertLessThanOrEqual(300, mb_strlen($resultado->extracto));
        $this->assertStringNotContainsString("\n", $resultado->extracto);
    }

    /**
     * @param  array<string, string>  $cabeceras
     */
    private function entrante(
        string $desdeEmail,
        string $asunto,
        string $cuerpo,
        array $cabeceras = [],
        ?string $inReplyTo = null,
        string $desdeNombre = 'Remitente',
    ): MensajeEntrante {
        return new MensajeEntrante(
            desdeEmail: $desdeEmail,
            desdeNombre: $desdeNombre,
            asunto: $asunto,
            cuerpo: $cuerpo,
            cabeceras: $cabeceras,
            messageId: '<test@mail.es>',
            inReplyTo: $inReplyTo,
            references: null,
            recibidoAt: now(),
            rawHash: sha1($cuerpo.$asunto),
        );
    }
}
