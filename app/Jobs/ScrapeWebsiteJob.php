<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\Suppression;
use App\Services\EmailScraper;
use App\Services\EmailVerifier;
use App\Services\HarvestHeartbeat;
use App\Services\LeadCaptureService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Scrapea la web de un lead (cola dedicada "scraping") y actualiza email/status.
 */
class ScrapeWebsiteJob implements ShouldQueue
{
    use Batchable;
    use InteractsWithQueue;
    use Queueable;

    /** Un solo reintento breve; no reencola en bucle. */
    public int $tries = 2;

    /** Segundos máximos del job (scraper timeout × rutas). */
    public int $timeout = 90;

    public function __construct(public int $leadId)
    {
        $this->onQueue('scraping');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(EmailScraper $scraper, EmailVerifier $verifier, LeadCaptureService $capture): void
    {
        HarvestHeartbeat::touch('scrape:lead:'.$this->leadId);

        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        if ($lead->website === null || trim($lead->website) === '') {
            return;
        }

        // Ya tiene email usable: no insiste.
        if ($lead->email !== null && $lead->email !== '' && $lead->status === 'nuevo') {
            return;
        }

        try {
            $email = $scraper->findEmail($lead->website);
            $email = $email !== null ? Suppression::normalizeEmail($email) : null;
            $email = $email === '' ? null : $email;

            if ($email === null) {
                $lead->update([
                    'status' => 'sin_email',
                    'email_check' => null,
                ]);

                return;
            }

            if ($capture->debeOmitirPorEmailODominio($email, $lead->website, $lead->id)) {
                Log::info('ScrapeWebsiteJob: email omitido por dedup/suppression', [
                    'lead_id' => $lead->id,
                    'email' => $email,
                ]);

                $lead->update([
                    'status' => 'sin_email',
                    'notes' => $this->appendNote($lead->notes, "Email scrapeado omitido (dup/suppression): {$email}"),
                ]);

                return;
            }

            $emailCheck = $verifier->verify($email);
            $status = $emailCheck === 'invalido' ? 'sin_email' : 'nuevo';

            $lead->update([
                'email' => $email,
                'email_check' => $emailCheck,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('ScrapeWebsiteJob falló', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);

            $lead->refresh();
            $lead->update([
                'status' => 'sin_email',
                'notes' => $this->appendNote($lead->notes, '[scrape] '.$e->getMessage()),
            ]);
        }
    }

    /**
     * Tras agotar intentos: deja el lead marcado sin tumbar la cola.
     */
    public function failed(?\Throwable $e): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $lead->update([
            'status' => 'sin_email',
            'notes' => $this->appendNote(
                $lead->notes,
                '[scrape failed] '.($e?->getMessage() ?? 'desconocido')
            ),
        ]);
    }

    private function appendNote(?string $notes, string $line): string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? $line : $notes."\n".$line;
    }
}
