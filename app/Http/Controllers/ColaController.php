<?php

namespace App\Http\Controllers;

use App\Models\DiaEnvio;
use App\Models\Mensaje;
use App\Models\Suppression;
use App\Services\Panel\DatosPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ColaController extends Controller
{
    public function __construct(private DatosPanel $datos) {}

    public function indice(): View
    {
        $hoy = today();
        $manana = today()->addDay();
        $dia = DiaEnvio::paraFecha($hoy);

        $mensajes = Mensaje::query()
            ->with(['lead.auditoria'])
            ->where(function ($q) use ($hoy, $manana): void {
                $q->whereDate('programado_para', $hoy->toDateString())
                    ->orWhereDate('programado_para', $manana->toDateString());
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
}
