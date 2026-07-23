<?php

namespace App\Jobs;

use App\Excepciones\LimiteRitmoExcedido;
use App\Excepciones\UrlNoPermitida;
use App\Models\Lead;
use App\Services\Soporte\Latido;
use App\Services\Web\RastreadorSitio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RastrearSitioJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $leadId)
    {
        $this->onQueue('scraping');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(RastreadorSitio $rastreador): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead === null) {
            return;
        }

        Latido::marcar('scrape', $lead->website_dominio);

        try {
            $resultado = $rastreador->rastrear($lead);
        } catch (LimiteRitmoExcedido $e) {
            // No consume intento: simplemente vuelve a la cola más tarde.
            $this->release(120);

            return;
        } catch (UrlNoPermitida $e) {
            $lead->update([
                'rastreado_at' => now(),
                'notas' => trim(($lead->notas ?? '')."\nURL no permitida: ".$e->getMessage()),
            ]);

            return;
        }

        Log::channel('outreach')->info('Sitio rastreado', [
            'lead_id' => $lead->id,
            'dominio' => $lead->website_dominio,
            'paginas' => $resultado->paginasGuardadas,
            'emails' => $resultado->emailsGuardados,
            'descartados' => $resultado->emailsDescartados,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead !== null) {
            $lead->update([
                'rastreado_at' => now(),
                'notas' => trim(($lead->notas ?? '')."\nRastreo fallido: ".($e?->getMessage() ?? 'desconocido')),
            ]);
        }
    }
}
