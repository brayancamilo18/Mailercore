<?php

namespace Tests\Feature;

use App\Excepciones\LimiteRitmoExcedido;
use App\Jobs\RastrearSitioJob;
use App\Models\Lead;
use App\Services\Web\RastreadorSitio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class RastrearSitioJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_hace_release_ante_limite_de_ritmo(): void
    {
        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
        ]);

        $this->mock(RastreadorSitio::class, function (MockInterface $mock): void {
            $mock->shouldReceive('rastrear')
                ->once()
                ->andThrow(new LimiteRitmoExcedido('límite'));
        });

        $job = new class($lead->id) extends RastrearSitioJob
        {
            public ?int $releaseDelay = null;

            public function release($delay = 0): void
            {
                $this->releaseDelay = (int) $delay;
            }
        };

        $job->handle($this->app->make(RastreadorSitio::class));

        $this->assertSame(120, $job->releaseDelay);
    }

    public function test_job_no_falla_si_el_lead_no_existe(): void
    {
        $this->mock(RastreadorSitio::class, function (MockInterface $mock): void {
            $mock->shouldReceive('rastrear')->never();
        });

        $job = new RastrearSitioJob(999999);
        $job->handle($this->app->make(RastreadorSitio::class));

        $this->assertTrue(true);
    }

    public function test_failed_deja_nota_en_el_lead(): void
    {
        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'notas' => null,
            'rastreado_at' => null,
        ]);

        $job = new RastrearSitioJob($lead->id);
        $job->failed(new \RuntimeException('timeout de prueba'));

        $lead->refresh();
        $this->assertNotNull($lead->rastreado_at);
        $this->assertStringContainsString('Rastreo fallido: timeout de prueba', (string) $lead->notas);
    }

    public function test_comando_encola_los_leads_sin_rastrear(): void
    {
        Queue::fake();

        Lead::factory()->create([
            'website' => 'https://a.es',
            'rastreado_at' => null,
        ]);
        Lead::factory()->create([
            'website' => 'https://b.es',
            'rastreado_at' => null,
        ]);
        Lead::factory()->create([
            'website' => 'https://c.es',
            'rastreado_at' => now(),
        ]);

        $this->artisan('leads:rastrear', ['--solo-sin-rastrear' => true, '--limite' => 50])
            ->assertExitCode(0);

        Queue::assertPushed(RastrearSitioJob::class, 2);
        Queue::assertPushedOn('scraping', RastrearSitioJob::class);
    }
}
