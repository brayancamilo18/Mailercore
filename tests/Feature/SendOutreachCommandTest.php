<?php

namespace Tests\Feature;

use App\Mail\AgencyOutreachMail;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendOutreachCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Evita sleeps reales en tests.
        config([
            'outreach.sending.delay_min' => 0,
            'outreach.sending.delay_max' => 0,
            'outreach.sending.send_days' => [1, 2, 3, 4],
            'outreach.sending.warmup' => [0 => 5],
            'outreach.sending.max_daily' => 25,
            'outreach.sending.unsubscribe_email' => 'baja@onez.test',
            'outreach.sending.unsubscribe_url' => '',
            'outreach.sending.reply_to' => 'contacto@onez.test',
            'outreach.sending.sender_legal_name' => 'Test Sender',
            'outreach.sending.sender_address' => 'Madrid',
        ]);

        // Lunes laborable por defecto.
        Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', 'Europe/Madrid'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_respeta_cupo_diario_y_warmup(): void
    {
        Mail::fake();

        config([
            'outreach.sending.warmup' => [0 => 2],
            'outreach.sending.max_daily' => 2,
        ]);

        // 2 ya enviados hoy → cupo lleno (límite warm-up = 2).
        Lead::factory()->contactado(now())->count(2)->create();
        Lead::factory()->readyToSend()->count(3)->create();

        $this->artisan('agencies:send')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(3, Lead::query()->where('status', 'nuevo')->count());
    }

    public function test_no_envia_en_dia_no_laborable(): void
    {
        Mail::fake();

        // Domingo (ISO 7) fuera de send_days.
        Carbon::setTestNow(Carbon::parse('2026-07-12 10:00:00', 'Europe/Madrid'));

        Lead::factory()->readyToSend()->create([
            'email' => 'lead-domingo@example.test',
        ]);

        $this->artisan('agencies:send')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('leads', [
            'email' => 'lead-domingo@example.test',
            'status' => 'nuevo',
        ]);
    }

    public function test_marca_lead_como_contactado_y_lleva_list_unsubscribe(): void
    {
        Mail::fake();

        $lead = Lead::factory()->readyToSend()->create([
            'name' => 'Agencia Test Send',
            'email' => 'agencia-test-send@example.test',
        ]);

        $this->artisan('agencies:send', ['--limit' => 1])->assertSuccessful();

        Mail::assertSent(AgencyOutreachMail::class, function (AgencyOutreachMail $mail) use ($lead): bool {
            $headers = $mail->headers()->text;

            return $mail->agencyName === $lead->name
                && isset($headers['List-Unsubscribe'])
                && str_contains($headers['List-Unsubscribe'], 'mailto:')
                && str_contains(strtoupper($headers['List-Unsubscribe']), 'BAJA');
        });

        $lead->refresh();

        $this->assertSame('contactado', $lead->status);
        $this->assertNotNull($lead->contacted_at);
    }
}
