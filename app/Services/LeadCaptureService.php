<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Suppression;
use App\Services\Sources\LeadCandidate;

/**
 * Dedup + scrape + verificación + persistencia compartidos por todas las fuentes.
 * Solo persiste leads con email verificable (no se guarda ruido sin correo).
 */
class LeadCaptureService
{
    public function __construct(
        private EmailScraper $scraper,
        private EmailVerifier $verifier,
    ) {
    }

    /**
     * Evalúa un candidato: persiste solo si ya trae email válido; si no, encola scrape.
     *
     * @return array{
     *     outcome: 'created'|'omitted'|'error'|'pending_scrape',
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

            if ($email === null) {
                $hasWebsite = $candidate->website !== null && trim($candidate->website) !== '';

                if (! $hasWebsite) {
                    return [...$base, 'outcome' => 'omitted', 'reason' => 'sin_email'];
                }

                return [
                    ...$base,
                    'outcome' => 'pending_scrape',
                    'needs_scrape' => true,
                ];
            }

            $emailCheck = $this->verifier->verify($email);

            if ($emailCheck === 'invalido') {
                return [...$base, 'outcome' => 'omitted', 'email' => $email, 'reason' => 'email_invalido'];
            }

            if ($dryRun) {
                return [
                    ...$base,
                    'outcome' => 'created',
                    'email' => $email,
                    'email_check' => $emailCheck,
                    'status' => 'nuevo',
                ];
            }

            $lead = $this->persistLead($candidate, $email, $emailCheck);

            return [
                ...$base,
                'outcome' => 'created',
                'email' => $email,
                'email_check' => $emailCheck,
                'status' => 'nuevo',
                'lead_id' => $lead->id,
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
     * Persiste un lead tras encontrar email en la web (scrape async o sync).
     *
     * @return array{
     *     outcome: 'created'|'omitted'|'error',
     *     name: string,
     *     email: ?string,
     *     email_check: ?string,
     *     status: ?string,
     *     reason: ?string,
     *     error: ?string,
     *     lead_id: ?int
     * }
     */
    public function createFromScrapedEmail(LeadCandidate $candidate, string $rawEmail): array
    {
        $base = [
            'name' => $candidate->name,
            'email' => null,
            'email_check' => null,
            'status' => null,
            'reason' => null,
            'error' => null,
            'lead_id' => null,
        ];

        try {
            if (
                $candidate->externalId !== null
                && Lead::query()->where('place_id', $candidate->externalId)->exists()
            ) {
                return [...$base, 'outcome' => 'omitted', 'reason' => 'place_id'];
            }

            $email = Suppression::normalizeEmail($rawEmail);
            $email = $email === '' ? null : $email;

            if ($email === null) {
                return [...$base, 'outcome' => 'omitted', 'reason' => 'sin_email'];
            }

            if ($this->debeOmitirPorEmailODominio($email, $candidate->website)) {
                return [...$base, 'outcome' => 'omitted', 'email' => $email, 'reason' => 'email_o_dominio'];
            }

            $emailCheck = $this->verifier->verify($email);

            if ($emailCheck === 'invalido') {
                return [...$base, 'outcome' => 'omitted', 'email' => $email, 'reason' => 'email_invalido'];
            }

            $lead = $this->persistLead($candidate, $email, $emailCheck);

            return [
                ...$base,
                'outcome' => 'created',
                'email' => $email,
                'email_check' => $emailCheck,
                'status' => 'nuevo',
                'lead_id' => $lead->id,
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
     * Procesa un candidato en línea (scrape síncrono). Solo persiste si hay email usable.
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

        if ($result['outcome'] !== 'pending_scrape') {
            unset($result['needs_scrape'], $result['lead_id']);

            return $result;
        }

        if ($dryRun) {
            return [
                'outcome' => 'omitted',
                'name' => $candidate->name,
                'email' => null,
                'email_check' => null,
                'status' => null,
                'reason' => 'sin_email',
                'error' => null,
            ];
        }

        try {
            $email = $this->scraper->findEmail($candidate->website ?? '');

            if ($email === null || trim($email) === '') {
                return [
                    'outcome' => 'omitted',
                    'name' => $candidate->name,
                    'email' => null,
                    'email_check' => null,
                    'status' => null,
                    'reason' => 'sin_email',
                    'error' => null,
                ];
            }

            $created = $this->createFromScrapedEmail($candidate, $email);
            unset($created['lead_id']);

            return $created;
        } catch (\Throwable $e) {
            report($e);

            return [
                'outcome' => 'error',
                'name' => $candidate->name,
                'email' => null,
                'email_check' => null,
                'status' => null,
                'reason' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Omite el lead si el email ya existe, está suprimido, el dominio web está
     * en suppressions, o el dominio de la web ya está en el CRM.
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

    private function persistLead(LeadCandidate $candidate, string $email, string $emailCheck): Lead
    {
        return Lead::create([
            'place_id' => $candidate->externalId,
            'name' => $candidate->name,
            'website' => $candidate->website,
            'email' => $email,
            'email_check' => $emailCheck,
            'phone' => $candidate->phone,
            'address' => $candidate->address,
            'status' => 'nuevo',
            'segmento' => $candidate->segmento,
            'captured_at' => now(),
        ]);
    }
}
