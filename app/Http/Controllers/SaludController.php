<?php

namespace App\Http\Controllers;

use App\Models\DiaEnvio;
use App\Models\EventoInbox;
use App\Services\Envio\RampaEnvio;
use App\Services\Panel\DatosPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SaludController extends Controller
{
    public function __construct(
        private DatosPanel $datos,
        private RampaEnvio $rampa,
    ) {}

    public function indice(): View
    {
        $dias = DiaEnvio::query()
            ->where('fecha', '>=', today()->subDays(29)->toDateString())
            ->orderBy('fecha')
            ->get();

        $maxEnviados = max(1, (int) $dias->max('enviados'));

        $eventosPorTipo = EventoInbox::query()
            ->where('recibido_at', '>=', now()->subDays(30))
            ->select('tipo', DB::raw('count(*) as total'))
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $conteosEventos = [];
        foreach (EventoInbox::TIPOS as $tipo) {
            $conteosEventos[$tipo] = (int) ($eventosPorTipo[$tipo] ?? 0);
        }

        return view('panel.salud', [
            'dias' => $dias,
            'maxEnviados' => $maxEnviados,
            'conteosEventos' => $conteosEventos,
            'latidos' => $this->datos->latidos(),
            'pausado' => $this->datos->envioPausado(),
        ]);
    }

    public function pausar(): RedirectResponse
    {
        Cache::forever('envio:pausado', true);

        return back();
    }

    public function reanudar(): RedirectResponse
    {
        Cache::forget('envio:pausado');
        $this->rampa->reanudar();

        return back();
    }
}
