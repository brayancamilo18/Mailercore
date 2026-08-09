<?php

namespace App\Http\Controllers;

use App\Models\DiaEnvio;
use App\Models\Mensaje;
use App\Models\Suppression;
use App\Services\Panel\DatosPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ColaController extends Controller
{
    public function __construct(private DatosPanel $datos) {}

    public function indice(): View
    {
        $hoy = today('Europe/Madrid');
        /** @var list<int> $dias */
        $dias = array_map('intval', config('outreach.envio.dias', [1, 2, 3, 4]));
        $proximoEnvio = $this->proximoDiaEnvio($hoy, $dias);
        $dia = DiaEnvio::paraFecha(
            in_array($hoy->dayOfWeekIso, $dias, true) ? $hoy : $proximoEnvio
        );

        $mensajes = Mensaje::query()
            ->with(['lead.auditoria'])
            ->where(function ($q) use ($hoy, $proximoEnvio): void {
                $q->whereDate('programado_para', $hoy->toDateString())
                    ->orWhereDate('programado_para', $proximoEnvio->toDateString());
            })
            ->orderBy('programado_para')
            ->get()
            ->map(function (Mensaje $mensaje): array {
                return [
                    'mensaje' => $mensaje,
                    'dominio' => Suppression::dominioDeEmail($mensaje->destinatario),
                    'sector' => $mensaje->lead?->etiquetaSector() ?? $mensaje->plantilla,
                    'hallazgo' => $mensaje->paso === 2
                        ? $mensaje->lead?->auditoria?->hallazgo_secundario
                        : $mensaje->lead?->auditoria?->hallazgo_principal,
                ];
            });

        return view('panel.cola', [
            'mensajes' => $mensajes,
            'enviados' => (int) $dia->enviados,
            'cuota' => (int) $dia->cuota_planificada,
            'proximoEnvio' => $proximoEnvio,
            'esDiaEnvio' => in_array($hoy->dayOfWeekIso, $dias, true),
        ]);
    }

    public function ver(Mensaje $mensaje): View
    {
        return view('panel.mensaje', [
            'mensaje' => $mensaje,
            'listUnsubscribe' => $this->datos->listUnsubscribeHeader(),
        ]);
    }

    public function cancelar(Mensaje $mensaje): RedirectResponse
    {
        if (in_array($mensaje->estado, ['pendiente', 'enviando'], true)) {
            $mensaje->cancelar('Cancelado desde el panel');
        }

        return redirect()->route('cola.indice');
    }

    /** @param  list<int>  $dias */
    private function proximoDiaEnvio(Carbon $desde, array $dias): Carbon
    {
        $cursor = $desde->copy()->startOfDay()->addDay();

        for ($i = 0; $i < 14; $i++) {
            if (in_array($cursor->dayOfWeekIso, $dias, true)) {
                return $cursor;
            }
            $cursor->addDay();
        }

        return $desde->copy()->addDay();
    }
}
