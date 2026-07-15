<?php

namespace App\Jobs;

use App\Models\HarvestArea;
use App\Services\AreaHarvestService;
use App\Services\EmailScraper;
use App\Services\HarvestHeartbeat;
use App\Services\LeadCaptureService;
use App\Services\Sources\LeadCandidate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Scrapea la web de un candidato y solo persiste el lead si encuentra email válido.
 * Se encola en paralelo a Overpass para que el panel vaya sumando leads.
 */
class ScrapeWebsiteJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    /**
     * @param  array<string, mixed>  $candidatePayload
     */
    public function __construct(
        public array $candidatePayload,
        public ?int $harvestAreaId = null,
    ) {
        $this->onQueue('scraping');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(EmailScraper $scraper, LeadCaptureService $capture): void
    {
        $candidate = LeadCandidate::fromArray($this->candidatePayload);
        HarvestHeartbeat::touch('scrape:candidate:'.($candidate->externalId ?? $candidate->name));

        try {
            if ($candidate->website !== null && trim($candidate->website) !== '') {
                $email = $scraper->findEmail($candidate->website);

                if ($email !== null && trim($email) !== '') {
                    $result = $capture->createFromScrapedEmail($candidate, $email);

                    if ($result['outcome'] === 'created' && isset($result['lead_id']) && $this->harvestAreaId !== null) {
                        $area = HarvestArea::query()->find($this->harvestAreaId);

                        if ($area !== null) {
                            $area->rememberSessionLeadId((int) $result['lead_id']);
                        }
                    } elseif (($result['outcome'] ?? null) === 'omitted') {
                        Log::info('ScrapeWebsiteJob: candidato omitido tras scrape', [
                            'name' => $candidate->name,
                            'reason' => $result['reason'] ?? null,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
            Log::warning('ScrapeWebsiteJob falló', [
                'name' => $candidate->name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Solo al completar con éxito (sin reintento pendiente).
        $this->avisarScrapeTerminado();
    }

    public function failed(?\Throwable $e): void
    {
        Log::info('ScrapeWebsiteJob agotó reintentos sin crear lead', [
            'name' => $this->candidatePayload['name'] ?? '?',
            'error' => $e?->getMessage(),
        ]);

        $this->avisarScrapeTerminado();
    }

    private function avisarScrapeTerminado(): void
    {
        if ($this->harvestAreaId === null) {
            return;
        }

        app(AreaHarvestService::class)->markScrapeFinished($this->harvestAreaId);
    }
}
