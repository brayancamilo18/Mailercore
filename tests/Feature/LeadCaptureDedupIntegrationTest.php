<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Suppression;
use App\Services\EmailScraper;
use App\Services\EmailVerifier;
use App\Services\LeadCaptureService;
use App\Services\Sources\LeadCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeadCaptureDedupIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function capture(): LeadCaptureService
    {
        return new LeadCaptureService(
            new EmailScraper(array_merge(config('outreach.scraper'), ['respect_robots' => false])),
            new EmailVerifier(array_merge(config('outreach.verifier'), ['smtp_probe' => false])),
        );
    }

    public function test_omite_por_dominio_web_aunque_no_haya_email(): void
    {
        Lead::factory()->create([
            'name' => 'Ya en CRM',
            'website' => 'https://www.misma-agencia.test',
            'email' => 'otro@misma-agencia.test',
            'status' => 'contactado',
        ]);

        Http::fake([
            '*' => Http::response('<html></html>', 200),
        ]);

        $result = $this->capture()->process(new LeadCandidate(
            name: 'Duplicado dominio',
            website: 'https://misma-agencia.test',
            source: 'association_directory',
            email: null,
            segmento: 'agencia',
        ));

        $this->assertSame('omitted', $result['outcome']);
        $this->assertSame('email_o_dominio', $result['reason']);
        $this->assertSame(1, Lead::query()->count());
    }

    public function test_omite_si_dominio_web_esta_en_suppressions(): void
    {
        Suppression::query()->create([
            'email' => 'baja@dominio-suprimido.test',
            'domain' => 'dominio-suprimido.test',
            'reason' => 'baja',
            'created_at' => now(),
        ]);

        $result = $this->capture()->process(new LeadCandidate(
            name: 'Deberia omitirse',
            website: 'https://www.dominio-suprimido.test',
            source: 'overpass',
            email: null,
            segmento: 'agencia',
            externalId: 'node/dedup-sup-1',
        ));

        $this->assertSame('omitted', $result['outcome']);
        $this->assertSame(0, Lead::query()->count());
    }

    public function test_send_no_recontacta_email_suprimido(): void
    {
        Mail::fake();

        config([
            'outreach.sending.delay_min' => 0,
            'outreach.sending.delay_max' => 0,
            'outreach.sending.send_days' => [1, 2, 3, 4],
            'outreach.sending.warmup' => [0 => 5],
            'outreach.sending.max_daily' => 25,
            'outreach.sending.unsubscribe_email' => 'baja@onez.test',
        ]);

        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-13 10:00:00'));

        $lead = Lead::factory()->readyToSend()->create([
            'email' => 'suprimido-envio@example.test',
        ]);

        Suppression::query()->create([
            'email' => 'suprimido-envio@example.test',
            'domain' => 'example.test',
            'reason' => 'baja',
            'created_at' => now(),
        ]);

        $this->artisan('agencies:send', ['--limit' => 1])->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame('baja', $lead->fresh()->status);

        \Illuminate\Support\Carbon::setTestNow();
    }
}
