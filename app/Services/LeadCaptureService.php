<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Suppression;
use App\Services\Sources\LeadCandidate;

/**
 * Dedup + scrape + verificación + persistencia compartidos por todas las fuentes.
 */
class LeadCaptureService
{
    public function __construct(
        private EmailScraper $scraper,
        private EmailVerifier $verifier,
    ) {
    }

    /**
     * Crea el lead base SIN scrapear la web.
     * Si hay email en la fuente, lo verifica aquí. Si falta email pero hay web → needs_scrape.
     *
     * @return array{
     *     outcome: 'created'|'omitted'|'error',
     *     name: string,
     *     email: ?string,
     *     email_check: ?string,
     *     status: ?string,
     *     reason: ?string,
     *     error: ?string,
     *     lead_id: ?int,
     *     needs_scrape: bool
     * }
     */
    public function createBase(LeadCandidate $candidate, bool $dryRun = false): array
    {
        $base = [
            'name' => $candidate->name,
            'email' => null,
            'email_check' => null,
            'status' => null,
            'reason' => null,
            'error' => null,
            'lead_id' => null,
            'needs_scrape' => false,
        ];

        try {
            if (
                ! $dryRun
                && $candidate->externalId !== null
                && Lead::query()->where('place_id', $candidate->externalId)->exists()
            ) {
                return [...$base, 'outcome' => 'omitted', 'reason' => 'place_id'];
            }

            if (
                $candidate->segmento === 'negocio'
                && ($candidate->website === null || trim($candidate->website) === '')
            ) {
                return [...$base, 'outcome' => 'omitted', 'reason' => 'sin_web'];
            }

            $email = $candidate->email !== null
                ? Suppression::normalizeEmail($candidate->email)
                : null;
            $email = $email === '' ? null : $email;

            if ($this->debeOmitirPorEmailODominio($email, $candidate->website)) {
                return [
                    ...$base,
                    'outcome' => 'omitted',
                    'email' => $email,
                    'reason' => 'email_o_dominio',
                ];
            }

            $emailCheck = null;
            $status = 'sin_email';

            if ($email !== null) {
                $emailCheck = $this->verifier->verify($email);
                $status = $emailCheck === 'invalido' ? 'sin_email' : 'nuevo';
            }

            $hasWebsite = $candidate->website !== null && trim($candidate->website) !== '';
            // Scrapeo async solo si hay web y aún no hay email usable.
            $needsScrape = $hasWebsite && ($email === null || $status === 'sin_email');

            $leadId = null;

            if (! $dryRun) {
                $lead = Lead::create([
                    'place_id' => $candidate->externalId,
                    'name' => $candidate->name,
                    'website' => $candidate->website,
                    'email' => $email,
                    'email_check' => $emailCheck,
                    'phone' => $candidate->phone,
                    'address' => $candidate->address,
                    'status' => $status,
                    'segmento' => $candidate->segmento,
                    'captured_at' => now(),
                ]);
                $leadId = $lead->id;
            }

            return [
                ...$base,
                'outcome' => 'created',
                'email' => $email,
                'email_check' => $emailCheck,
                'status' => $status,
                'lead_id' => $leadId,
                'needs_scrape' => $needsScrape,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                ...$base,
                'outcome' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Procesa un candidato en línea (incluye scrape síncrono). Preferir createBase + ScrapeWebsiteJob.
     *
     * @return array{
     *     outcome: 'created'|'omitted'|'error',
     *     name: string,
     *     email: ?string,
     *     email_check: ?string,
     *     status: ?string,
     *     reason: ?string,
     *     error: ?string
     * }
     */
    public function process(LeadCandidate $candidate, bool $dryRun = false): array
    {
        $result = $this->createBase($candidate, $dryRun);

        if ($result['outcome'] !== 'created' || ! ($result['needs_scrape'] ?? false)) {
            return $result;
        }

        if ($dryRun || $result['lead_id'] === null) {
            return $result;
        }

        // Modo síncrono legacy: scrapea ahora mismo.
        $lead = Lead::query()->find($result['lead_id']);

        if ($lead === null) {
            return $result;
        }

        try {
            $email = $this->scraper->findEmail($candidate->website);
            $email = $email !== null ? Suppression::normalizeEmail($email) : null;
            $email = $email === '' ? null : $email;

            if ($email !== null && ! $this->debeOmitirPorEmailODominio($email, $candidate->website, $lead->id)) {
                $emailCheck = $this->verifier->verify($email);
                $status = $emailCheck === 'invalido' ? 'sin_email' : 'nuevo';
                $lead->update([
                    'email' => $email,
                    'email_check' => $emailCheck,
                    'status' => $status,
                ]);

                return [
                    'outcome' => 'created',
                    'name' => $candidate->name,
                    'email' => $email,
                    'email_check' => $emailCheck,
                    'status' => $status,
                    'reason' => null,
                    'error' => null,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * Omite el lead si el email ya existe, está suprimido, el dominio web está
     * en suppressions, o el dominio de la web ya está en el CRM.
     *
     * @param  int|null  $exceptLeadId  Ignora este lead (p. ej. al actualizar tras scrape).
     */
    public function debeOmitirPorEmailODominio(?string $email, ?string $website, ?int $exceptLeadId = null): bool
    {
        if ($email !== null && $email !== '') {
            $emailQuery = Lead::query()->where('email', $email);

            if ($exceptLeadId !== null) {
                $emailQuery->where('id', '!=', $exceptLeadId);
            }

            if ($emailQuery->exists()) {
                return true;
            }

            if (Suppression::has($email)) {
                return true;
            }
        }

        $webDomain = Suppression::domainFromWebsite($website);

        if ($webDomain === null) {
            return false;
        }

        if (Suppression::query()->where('domain', $webDomain)->exists()) {
            return true;
        }

        return Lead::query()
            ->whereNotNull('website')
            ->when($exceptLeadId !== null, fn ($q) => $q->where('id', '!=', $exceptLeadId))
            ->get(['website'])
            ->contains(fn (Lead $lead): bool => Suppression::domainFromWebsite($lead->website) === $webDomain);
    }
}
