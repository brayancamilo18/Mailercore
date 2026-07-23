<?php

namespace Tests\Feature;

use App\DTO\ItemBandeja;
use App\DTO\MensajeEntrante;
use App\Models\EventoInbox;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Mensaje;
use App\Models\Suppression;
use App\Services\Inbox\LectorBandeja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProcesarBandejaTest extends TestCase
{
    use RefreshDatabase;

    private FakeLectorBandeja $lector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lector = new FakeLectorBandeja;
        $this->app->instance(LectorBandeja::class, $this->lector);
        Cache::forget('bandeja:fallos_seguidos');
    }

    public function test_rebote_duro_suprime_y_marca_rebotado(): void
    {
        $email = 'duro@cliente.es';
        $lead = $this->leadConEmail($email, 'contactado');
        $mensaje = Mensaje::factory()->enviado()->create([
            'lead_id' => $lead->id,
            'destinatario' => $email,
            'programado_para' => today(),
            'message_id' => '<msg-duro@local>',
        ]);

        $this->lector->poner([
            $this->item('1', $this->reboteDuro($email, '<msg-duro@local>')),
        ]);

        $this->artisan('outreach:bandeja')->assertSuccessful();

        $this->assertTrue(Suppression::existe($email));
        $this->assertSame('rebote_duro', Suppression::query()->where('email', $email)->value('motivo'));
        $this->assertSame('rebotado', $lead->fresh()->estado);
        $this->assertDatabaseHas('eventos_inbox', [
            'email' => $email,
            'tipo' => 'rebote_duro',
            'mensaje_id' => $mensaje->id,
        ]);
        $this->assertContains('1', $this->lector->vistos);
    }

    public function test_rebote_blando_no_suprime_y_reprograma(): void
    {
        $email = 'blando@cliente.es';
        $lead = $this->leadConEmail($email, 'contactado');
        $mensaje = Mensaje::factory()->enviado()->create([
            'lead_id' => $lead->id,
            'destinatario' => $email,
            'programado_para' => today(),
            'message_id' => '<msg-blando@local>',
            'enviado_at' => now()->subHour(),
        ]);

        $this->lector->poner([
            $this->item('2', $this->reboteBlando($email, '<msg-blando@local>')),
        ]);

        $this->artisan('outreach:bandeja')->assertSuccessful();

        $this->assertFalse(Suppression::existe($email));
        $mensaje->refresh();
        $this->assertSame('pendiente', $mensaje->estado);
        $this->assertNull($mensaje->enviado_at);
        $this->assertNull($mensaje->message_id);
        $this->assertTrue($mensaje->programado_para->greaterThan(now()->addHours(47)));
        $this->assertDatabaseHas('eventos_inbox', [
            'email' => $email,
            'tipo' => 'rebote_blando',
        ]);
        $this->assertSame('contactado', $lead->fresh()->estado);
    }

    public function test_tercer_rebote_blando_se_trata_como_duro(): void
    {
        $email = 'tres@cliente.es';
        $lead = $this->leadConEmail($email, 'contactado');
        Mensaje::factory()->enviado()->create([
            'lead_id' => $lead->id,
            'destinatario' => $email,
            'programado_para' => today(),
            'message_id' => '<msg-tres@local>',
        ]);

        EventoInbox::query()->create([
            'email' => $email,
            'tipo' => 'rebote_blando',
            'asunto' => 'prev1',
            'extracto' => 'prev1',
            'raw_hash' => 'prev-hash-1',
            'recibido_at' => now()->subDays(2),
        ]);
        EventoInbox::query()->create([
            'email' => $email,
            'tipo' => 'rebote_blando',
            'asunto' => 'prev2',
            'extracto' => 'prev2',
            'raw_hash' => 'prev-hash-2',
            'recibido_at' => now()->subDay(),
        ]);

        $this->lector->poner([
            $this->item('3', $this->reboteBlando($email, '<msg-tres@local>', 'hash-tercer')),
        ]);

        $this->artisan('outreach:bandeja')->assertSuccessful();

        $this->assertTrue(Suppression::existe($email));
        $this->assertSame('rebotado', $lead->fresh()->estado);
        $this->assertDatabaseHas('eventos_inbox', [
            'email' => $email,
            'tipo' => 'rebote_duro',
            'raw_hash' => 'hash-tercer',
        ]);
    }

    public function test_baja_suprime_y_cancela_pendientes(): void
    {
        $email = 'baja@negocio.es';
        $lead = $this->leadConEmail($email, 'contactado');
        $pendiente = Mensaje::factory()->pendiente()->create([
            'lead_id' => $lead->id,
            'destinatario' => $email,
            'paso' => 2,
        ]);

        $this->lector->poner([
            $this->item('4', $this->entrante(
                desdeEmail: $email,
                asunto: 'BAJA',
                cuerpo: 'Por favor dadme de baja.',
                rawHash: 'hash-baja',
            )),
        ]);

        $this->artisan('outreach:bandeja')->assertSuccessful();

        $this->assertTrue(Suppression::existe($email));
        $this->assertSame('baja', Suppression::query()->where('email', $email)->value('motivo'));
        $this->assertSame('baja', $lead->fresh()->estado);
        $this->assertSame('cancelado', $pendiente->fresh()->estado);
        $this->assertSame('Baja solicitada', $pendiente->fresh()->ultimo_error);
    }

    public function test_respuesta_marca_respondido_y_cancela_seguimiento(): void
    {
        $email = 'responde@negocio.es';
        $lead = $this->leadConEmail($email, 'contactado');
        $seguimiento = Mensaje::factory()->pendiente()->create([
            'lead_id' => $lead->id,
            'destinatario' => $email,
            'paso' => 2,
        ]);

        $this->lector->poner([
            $this->item('5', $this->entrante(
                desdeEmail: $email,
                asunto: 'Re: propuesta',
                cuerpo: 'Hola, me interesa saber más detalles del proyecto.',
                rawHash: 'hash-respuesta',
            )),
        ]);

        $this->artisan('outreach:bandeja')->assertSuccessful();

        $this->assertSame('respondido', $lead->fresh()->estado);
        $this->assertSame('cancelado', $seguimiento->fresh()->estado);
        $this->assertDatabaseHas('eventos_inbox', [
            'email' => $email,
            'tipo' => 'respuesta',
        ]);
    }

    public function test_autorespuesta_no_cambia_el_estado_del_lead(): void
    {
        $email = 'auto@empresa.es';
        $lead = $this->leadConEmail($email, 'contactado');

        $this->lector->poner([
            $this->item('6', $this->entrante(
                desdeEmail: $email,
                asunto: 'Automatic reply: fuera de la oficina',
                cuerpo: 'Estoy de vacaciones hasta el lunes.',
                cabeceras: ['auto-submitted' => 'auto-replied'],
                rawHash: 'hash-auto',
            )),
        ]);

        $this->artisan('outreach:bandeja')->assertSuccessful();

        $this->assertSame('contactado', $lead->fresh()->estado);
        $this->assertDatabaseHas('eventos_inbox', [
            'email' => $email,
            'tipo' => 'ignorado',
            'raw_hash' => 'hash-auto',
        ]);
        $this->assertFalse(Suppression::existe($email));
    }

    public function test_no_reprocesa_el_mismo_raw_hash(): void
    {
        $email = 'idem@negocio.es';
        $lead = $this->leadConEmail($email, 'contactado');

        EventoInbox::query()->create([
            'email' => $email,
            'tipo' => 'respuesta',
            'asunto' => 'prev',
            'extracto' => 'prev',
            'raw_hash' => 'hash-idem',
            'recibido_at' => now()->subHour(),
        ]);

        $this->lector->poner([
            $this->item('7', $this->entrante(
                desdeEmail: $email,
                asunto: 'BAJA',
                cuerpo: 'BAJA ahora',
                rawHash: 'hash-idem',
            )),
        ]);

        $this->artisan('outreach:bandeja')->assertSuccessful();

        $this->assertSame(1, EventoInbox::query()->where('raw_hash', 'hash-idem')->count());
        $this->assertSame('contactado', $lead->fresh()->estado);
        $this->assertFalse(Suppression::existe($email));
        $this->assertSame([], $this->lector->vistos);
    }

    public function test_dry_run_no_marca_seen_ni_escribe(): void
    {
        $email = 'dry@negocio.es';
        $lead = $this->leadConEmail($email, 'contactado');

        $this->lector->poner([
            $this->item('8', $this->entrante(
                desdeEmail: $email,
                asunto: 'BAJA',
                cuerpo: 'Quiero baja.',
                rawHash: 'hash-dry',
            )),
        ]);

        $this->artisan('outreach:bandeja', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, EventoInbox::query()->count());
        $this->assertFalse(Suppression::existe($email));
        $this->assertSame('contactado', $lead->fresh()->estado);
        $this->assertSame([], $this->lector->vistos);
    }

    public function test_fallo_de_conexion_incrementa_contador(): void
    {
        $this->lector->fallarCon = new \RuntimeException('IMAP down');

        $this->artisan('outreach:bandeja')->assertFailed();

        $this->assertSame(1, (int) Cache::get('bandeja:fallos_seguidos'));

        $this->artisan('outreach:bandeja')->assertFailed();
        $this->assertSame(2, (int) Cache::get('bandeja:fallos_seguidos'));
    }

    private function leadConEmail(string $email, string $estado): Lead
    {
        $lead = Lead::factory()->create(['estado' => $estado]);
        LeadEmail::factory()->principal()->create([
            'lead_id' => $lead->id,
            'email' => $email,
        ]);

        return $lead;
    }

    private function item(string $id, MensajeEntrante $entrante): ItemBandeja
    {
        return new ItemBandeja($id, $entrante);
    }

    private function reboteDuro(string $email, string $inReplyTo, string $rawHash = 'hash-duro'): MensajeEntrante
    {
        return $this->entrante(
            desdeEmail: 'mailer-daemon@mx.ejemplo.es',
            asunto: 'Delivery Status Notification',
            cuerpo: "Final-Recipient: rfc822; {$email}\nStatus: 5.1.1\nDiagnostic-Code: smtp; 550 User unknown",
            cabeceras: [
                'content-type' => 'multipart/report; report-type=delivery-status',
                'in-reply-to' => $inReplyTo,
            ],
            inReplyTo: $inReplyTo,
            rawHash: $rawHash,
        );
    }

    private function reboteBlando(string $email, string $inReplyTo, string $rawHash = 'hash-blando'): MensajeEntrante
    {
        return $this->entrante(
            desdeEmail: 'postmaster@mx.ejemplo.es',
            asunto: 'Mail delivery failed',
            cuerpo: "Final-Recipient: rfc822; {$email}\nStatus: 4.2.2\n",
            cabeceras: [
                'content-type' => 'multipart/report; report-type=delivery-status',
                'in-reply-to' => $inReplyTo,
            ],
            inReplyTo: $inReplyTo,
            rawHash: $rawHash,
        );
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
        string $rawHash = 'hash',
        string $desdeNombre = '',
    ): MensajeEntrante {
        return new MensajeEntrante(
            desdeEmail: $desdeEmail,
            desdeNombre: $desdeNombre,
            asunto: $asunto,
            cuerpo: $cuerpo,
            cabeceras: $cabeceras,
            messageId: '<'.uniqid('m', true).'@test>',
            inReplyTo: $inReplyTo,
            references: $inReplyTo,
            recibidoAt: now(),
            rawHash: $rawHash,
        );
    }
}

class FakeLectorBandeja implements LectorBandeja
{
    /** @var list<ItemBandeja> */
    public array $items = [];

    /** @var list<string> */
    public array $vistos = [];

    public ?\Throwable $fallarCon = null;

    /** @param  list<ItemBandeja>  $items */
    public function poner(array $items): void
    {
        $this->items = $items;
        $this->vistos = [];
        $this->fallarCon = null;
    }

    public function leerNoLeidos(int $limite): array
    {
        if ($this->fallarCon !== null) {
            throw $this->fallarCon;
        }

        return array_slice($this->items, 0, $limite);
    }

    public function marcarVisto(string $id): void
    {
        $this->vistos[] = $id;
    }
}
