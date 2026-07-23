<?php

namespace App\Console\Commands;

use App\Jobs\AnalizarPageSpeedJob;
use App\Models\Lead;
use Illuminate\Console\Command;

class AuditarPageSpeedCommand extends Command
{
    protected $signature = 'auditar:pagespeed
                            {--limite=50}
                            {--sector=}
                            {--forzar}';

    protected $description = 'Encola análisis PageSpeed para auditorías caducadas';

    public function handle(): int
    {
        $limite = max(1, (int) $this->option('limite'));

        $query = Lead::query()
            ->whereNotNull('website')
            ->whereHas('auditoria', function ($q): void {
                if (! $this->option('forzar')) {
                    $q->psiCaducado();
                }
            })
            ->join('auditorias', 'auditorias.lead_id', '=', 'leads.id')
            ->select('leads.*')
            ->orderByDesc('auditorias.puntuacion');

        if ($this->option('sector')) {
            $query->where('leads.sector', $this->option('sector'));
        }

        $ids = $query->limit($limite)->pluck('leads.id');

        foreach ($ids as $i => $id) {
            AnalizarPageSpeedJob::dispatch((int) $id)
                ->delay(now()->addSeconds($i * 2));
        }

        $this->info("Encolados {$ids->count()} jobs de PageSpeed (cola «scraping»).");
        $this->comment('PageSpeed solo se ejecuta sobre la lista corta del día siguiente. No lo lances sobre toda la base.');

        return self::SUCCESS;
    }
}
