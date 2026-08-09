<?php

namespace App\Http\Controllers;

use App\Models\AreaCosecha;
use App\Models\EventoInbox;
use App\Models\Lead;
use App\Models\PaisCosecha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function indice(Request $request): View
    {
        $query = Lead::query()
            ->with(['auditoria', 'emailPrincipal'])
            ->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado')->toString());
        }

        if ($request->filled('sector')) {
            $query->where('sector', $request->string('sector')->toString());
        }

        if ($request->filled('pais')) {
            $query->where('pais_codigo', strtoupper($request->string('pais')->toString()));
        }

        if ($request->filled('provincia')) {
            $query->where('provincia', $request->string('provincia')->toString());
        }

        if ($request->boolean('email_verificado')) {
            $query->whereHas('emails', fn ($q) => $q->where('estado_verificacion', 'valido'));
        }

        if ($request->filled('puntuacion_min')) {
            $min = (int) $request->input('puntuacion_min');
            $query->whereHas('auditoria', fn ($q) => $q->where('puntuacion', '>=', $min));
        }

        $paises = PaisCosecha::query()->ordenados()->get(['codigo', 'nombre']);
        $provinciasPorPais = $this->provinciasPorPais();
        $paisSeleccionado = $request->filled('pais')
            ? strtoupper($request->string('pais')->toString())
            : '';

        return view('panel.leads', [
            'leads' => $query->paginate(50)->withQueryString(),
            'filtros' => [
                'estado' => $request->input('estado'),
                'sector' => $request->input('sector'),
                'pais' => $paisSeleccionado !== '' ? $paisSeleccionado : null,
                'provincia' => $request->input('provincia'),
                'email_verificado' => $request->boolean('email_verificado'),
                'puntuacion_min' => $request->input('puntuacion_min'),
            ],
            'estados' => Lead::ESTADOS,
            'sectores' => config('sectores', []),
            'paises' => $paises,
            'provinciasPorPais' => $provinciasPorPais,
            'provinciasOpciones' => $paisSeleccionado !== ''
                ? ($provinciasPorPais[$paisSeleccionado] ?? collect())
                : $provinciasPorPais->flatten()->unique()->sort()->values(),
        ]);
    }

    public function ficha(Lead $lead): View
    {
        $lead->load([
            'emails',
            'auditoria',
            'paginas' => fn ($q) => $q->orderBy('ruta'),
            'mensajes' => fn ($q) => $q->orderByDesc('programado_para'),
        ]);

        $emailsLead = $lead->emails->pluck('email')->all();
        $eventos = EventoInbox::query()
            ->where(function ($q) use ($lead, $emailsLead): void {
                $q->whereIn('mensaje_id', $lead->mensajes->pluck('id'));
                if ($emailsLead !== []) {
                    $q->orWhereIn('email', $emailsLead);
                }
            })
            ->orderByDesc('recibido_at')
            ->get();

        return view('panel.lead', [
            'lead' => $lead,
            'eventos' => $eventos,
        ]);
    }

    public function cambiarEstado(Request $request, Lead $lead): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'string', 'in:'.implode(',', array_keys(Lead::ESTADOS))],
        ]);

        $lead->update(['estado' => $datos['estado']]);

        return redirect()->route('leads.ficha', $lead);
    }

    /**
     * @return Collection<string, Collection<int, string>>
     */
    private function provinciasPorPais(): Collection
    {
        return AreaCosecha::query()
            ->whereNotNull('pais_codigo')
            ->orderBy('nombre')
            ->get(['pais_codigo', 'nombre'])
            ->groupBy('pais_codigo')
            ->map(fn (Collection $areas): Collection => $areas->pluck('nombre')->unique()->values());
    }
}
