<?php

namespace App\Jobs;

use App\Excepciones\CuotaPageSpeedExcedida;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ClientePageSpeed;
use App\Services\Auditoria\MotorAuditoria;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\RateLimiter;

class AnalizarPageSpeedJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public function __construct(public int $leadId)
    {
        $this->onQueue('scraping');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ClientePageSpeed $cliente, MotorAuditoria $motor): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead === null) {
            return;
        }

        if (blank($lead->website)) {
            return;
        }

        $clave = 'pagespeed';
        $maximo = (int) config('outreach.pagespeed.peticiones_por_minuto');

        if (RateLimiter::tooManyAttempts($clave, $maximo)) {
            $this->release(60);

            return;
        }

        RateLimiter::hit($clave, 60);

        try {
            $resultado = $cliente->analizar($lead->website);
        } catch (CuotaPageSpeedExcedida) {
            $this->release(900);

            return;
        }

        if ($resultado === null) {
            $this->guardarPsi($lead, [
                'psi_error' => 'Web no analizable por PageSpeed',
                'psi_solicitado_at' => now(),
            ]);

            return;
        }

        $this->guardarPsi($lead, [
            'psi_rendimiento' => $resultado->rendimiento,
            'psi_seo' => $resultado->seo,
            'psi_accesibilidad' => $resultado->accesibilidad,
            'psi_buenas_practicas' => $resultado->buenasPracticas,
            'psi_lcp_ms' => $resultado->lcpMs,
            'psi_cls' => $resultado->cls,
            'psi_tbt_ms' => $resultado->tbtMs,
            'psi_peso_kb' => $resultado->pesoKb,
            'psi_solicitado_at' => now(),
            'psi_error' => null,
        ]);

        $motor->auditar($lead->fresh(['paginas', 'auditoria']));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardarPsi(Lead $lead, array $datos): void
    {
        Auditoria::updateOrCreate(
            ['lead_id' => $lead->id],
            $datos
        );
    }
}
