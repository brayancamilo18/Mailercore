<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeWebsiteJob;
use App\Models\HarvestArea;
use App\Services\AreaHarvestService;
use App\Services\EmailScraper;
use App\Services\EmailVerifier;
use App\Services\LeadCaptureService;
use App\Services\Sources\LeadSourceManager;
use Illuminate\Console\Command;

class SearchAgenciesCommand extends Command
{
    protected $signature = 'agencies:search
                            {--dry-run : Simula la búsqueda sin guardar en base de datos}
                            {--negocios : Incluye también filtros_negocios (segmento negocio; excluido por defecto)}
                            {--sync-scrape : Scrapea emails en línea (no recomendado; por defecto encola ScrapeWebsiteJob)}
                            {--area= : Cosecha solo esta HarvestArea por nombre (prioridad sobre nextPending)}';

    protected $description = 'Busca agencias/negocios: Overpass crea leads base y encola scrape en cola scraping';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeNegocios = (bool) $this->option('negocios');
        $syncScrape = (bool) $this->option('sync-scrape');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se guardará nada en la base de datos.');
        }

        if ($includeNegocios) {
            $this->info('Incluyendo filtros de negocios locales (segmento negocio).');
        }

        $capture = new LeadCaptureService(
            new EmailScraper(config('outreach.scraper')),
            new EmailVerifier(config('outreach.verifier')),
        );

        // Preferir recorrido persistente por HarvestArea si hay áreas sembradas.
        $harvestArea = $this->resolveHarvestArea();

        if ($harvestArea !== null) {
            $this->info("Cosechando área [{$harvestArea->name}] (admin_level {$harvestArea->admin_level})…");

            $stats = (new AreaHarvestService($capture))->harvest(
                $harvestArea,
                includeNegocios: $includeNegocios,
                dryRun: $dryRun,
            );

            $this->newLine();
            $this->info(sprintf(
                'Resumen área %s: %d leads creados, %d scrapes encolados, %d omitidos.',
                $harvestArea->name,
                $stats['leads_created'],
                $stats['jobs_dispatched'],
                $stats['omitted'],
            ));

            return self::SUCCESS;
        }

        return $this->harvestAllConfiguredSources($capture, $dryRun, $includeNegocios, $syncScrape);
    }

    private function resolveHarvestArea(): ?HarvestArea
    {
        $name = $this->option('area');

        if (is_string($name) && trim($name) !== '') {
            return HarvestArea::query()->where('name', trim($name))->first();
        }

        if (HarvestArea::query()->exists()) {
            return HarvestArea::nextPending();
        }

        return null;
    }

    private function harvestAllConfiguredSources(
        LeadCaptureService $capture,
        bool $dryRun,
        bool $includeNegocios,
        bool $syncScrape,
    ): int {
        $sources = (new LeadSourceManager)->active([
            'negocios' => $includeNegocios,
        ]);

        if ($sources === []) {
            $this->warn('No hay fuentes activas en config/outreach.php → sources.');

            return self::SUCCESS;
        }

        $nuevas = 0;
        $conEmail = 0;
        $omitidos = 0;
        $encolados = 0;
        $huboCandidatos = false;

        foreach ($sources as $source) {
            $this->info("Consultando fuente [{$source->key()}]…");

            foreach ($source->fetch() as $candidate) {
                $huboCandidatos = true;

                $result = $syncScrape
                    ? $capture->process($candidate, $dryRun)
                    : $capture->createBase($candidate, $dryRun);

                if ($result['outcome'] === 'omitted') {
                    if ($result['reason'] === 'email_o_dominio') {
                        $this->line("⏭ {$result['name']} — omitido (email/dominio ya conocido o suprimido)");
                    } elseif ($result['reason'] === 'sin_web') {
                        $this->line("⏭ {$result['name']} — omitido (negocio sin web)");
                    }
                    $omitidos++;

                    continue;
                }

                if ($result['outcome'] === 'error') {
                    $this->error("❌ {$result['name']}: {$result['error']}");

                    continue;
                }

                if (! $syncScrape && ! $dryRun && ($result['needs_scrape'] ?? false) && isset($result['lead_id'])) {
                    ScrapeWebsiteJob::dispatch($result['lead_id']);
                    $encolados++;
                    $this->line("⏳ {$result['name']} — scrape encolado (#{$result['lead_id']})");
                } else {
                    $icon = $result['status'] === 'nuevo' ? '✅' : '⚠️';
                    $emailLabel = $result['email'] ?? 'sin email';
                    $checkLabel = isset($result['email_check']) && $result['email_check'] !== null
                        ? " [{$result['email_check']}]"
                        : '';
                    $this->line("{$icon} {$result['name']} — {$emailLabel}{$checkLabel}");
                }

                if (($result['status'] ?? null) === 'nuevo') {
                    $conEmail++;
                }

                $nuevas++;
            }
        }

        if (! $huboCandidatos) {
            $this->warn('No se encontraron agencias.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Resumen: {$nuevas} nuevas, {$conEmail} con email en fuente, {$encolados} scrapes encolados, {$omitidos} omitidas.");

        return self::SUCCESS;
    }
}
