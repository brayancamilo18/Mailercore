<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Suppression;
use App\Services\InboxMessage;
use App\Services\InboxProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessInboxBounceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebote_simulado_crea_suppression_y_marca_lead_rebotado(): void
    {
        Lead::query()->create([
            'place_id' => 'node/bounce-1',
            'name' => 'Agencia Rebote SL',
            'email' => 'destino@agencia-rebote.test',
            'website' => 'https://agencia-rebote.test',
            'status' => 'contactado',
            'segmento' => 'agencia',
            'captured_at' => now(),
            'contacted_at' => now(),
        ]);

        $message = new InboxMessage(
            fromAddress: 'mailer-daemon@mail.hostinger.com',
            subject: 'Undelivered Mail Returned to Sender',
            body: <<<'BODY'
This is the mail system at host mail.hostinger.com.

I'm sorry to have to inform you that your message could not
be delivered to one or more recipients.

Final-Recipient: rfc822; destino@agencia-rebote.test
Action: failed
Status: 5.1.1
Diagnostic-Code: smtp; 550 User unknown
BODY,
            headers: [
                'x-failed-recipients' => 'destino@agencia-rebote.test',
                'content-type' => 'multipart/report; report-type=delivery-status',
            ],
            fromName: 'Mail Delivery System',
        );

        $resultado = (new InboxProcessor)->process($message);

        $this->assertSame('rebote', $resultado);

        $this->assertDatabaseHas('suppressions', [
            'email' => 'destino@agencia-rebote.test',
            'domain' => 'agencia-rebote.test',
            'reason' => 'rebote',
        ]);

        $this->assertDatabaseHas('leads', [
            'email' => 'destino@agencia-rebote.test',
            'status' => 'rebotado',
        ]);

        $this->assertTrue(Suppression::has('destino@agencia-rebote.test'));
    }

    public function test_respuesta_baja_crea_suppression_y_marca_lead(): void
    {
        Lead::query()->create([
            'place_id' => 'node/baja-1',
            'name' => 'Agencia Baja SL',
            'email' => 'hola@agencia-baja.test',
            'website' => 'https://agencia-baja.test',
            'status' => 'contactado',
            'segmento' => 'agencia',
            'captured_at' => now(),
            'contacted_at' => now(),
        ]);

        $message = new InboxMessage(
            fromAddress: 'hola@agencia-baja.test',
            subject: 'Re: Propuesta SilgoDev',
            body: "Hola,\n\nPor favor DADME DE BAJA de vuestra lista.\n\nGracias.",
        );

        $resultado = (new InboxProcessor)->process($message);

        $this->assertSame('baja', $resultado);

        $this->assertDatabaseHas('suppressions', [
            'email' => 'hola@agencia-baja.test',
            'reason' => 'baja',
        ]);

        $this->assertDatabaseHas('leads', [
            'email' => 'hola@agencia-baja.test',
            'status' => 'baja',
        ]);
    }

    public function test_respuesta_humana_marca_lead_como_respondido(): void
    {
        Lead::query()->create([
            'place_id' => 'node/reply-1',
            'name' => 'Agencia Respuesta SL',
            'email' => 'ceo@agencia-respuesta.test',
            'website' => 'https://agencia-respuesta.test',
            'status' => 'contactado',
            'segmento' => 'agencia',
            'captured_at' => now(),
            'contacted_at' => now(),
        ]);

        $message = new InboxMessage(
            fromAddress: 'ceo@agencia-respuesta.test',
            subject: 'Re: Propuesta',
            body: 'Interesante, ¿podemos hablar la semana que viene?',
        );

        $resultado = (new InboxProcessor)->process($message);

        $this->assertSame('respondido', $resultado);
        $this->assertDatabaseHas('leads', [
            'email' => 'ceo@agencia-respuesta.test',
            'status' => 'respondido',
        ]);
        $this->assertDatabaseMissing('suppressions', [
            'email' => 'ceo@agencia-respuesta.test',
        ]);
    }
}
