<?php

namespace App\Console\Commands;

use App\Jobs\RastrearSitioJob;
use App\Models\Lead;
use Illuminate\Console\Command;

class RastrearCommand extends Command
{
    protected $signature = 'leads:rastrear
                            {--limite=200}
                            {--sector=}
                            {--dias-desde=90 : Rastrear los que no se tocan desde hace N días}
                            {--solo-sin-rastrear}
                            {--forzar}';

    protected $description = 'Encola el rastreo de sitios web de leads';

    public function handle(): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $diasDesde = max(1, (int) $this->option('dias-desde'));

        $query = Lead::query()->whereNotNull('website');

        if ($this->option('solo-sin-rastrear')) {
            $query->whereNull('rastreado_at');
        } elseif (! $this->option('forzar')) {
            $query->where(function ($q) use ($diasDesde): void {
                $q->whereNull('rastreado_at')
                    ->orWhere('rastreado_at', '<', now()->subDays($diasDesde));
            });
        }

        if ($this->option('sector')) {
            $query->where('sector', $this->option('sector'));
        }

        $leads = $query->orderBy('id')->limit($limite)->get(['id']);

        foreach ($leads as $lead) {
            RastrearSitioJob::dispatch($lead->id);
        }

        $this->info("Encolados {$leads->count()} jobs en la cola «scraping».");
        $this->comment('Asegúrate de que los workers queue-scraping estén levantados (docker compose up).');

        return self::SUCCESS;
    }
}
