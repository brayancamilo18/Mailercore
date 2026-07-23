@extends('panel.layout')

@section('title', 'Leads')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Leads</h1>
    </div>

    <form method="GET" action="{{ route('leads.indice') }}" class="bg-white border border-slate-200 p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-sm">
        <div>
            <label class="block text-slate-600 mb-1">Estado</label>
            <select name="estado" class="w-full border border-slate-300 px-2 py-1.5 bg-white">
                <option value="">Todos</option>
                @foreach ($estados as $clave => $etiqueta)
                    <option value="{{ $clave }}" @selected($filtros['estado'] === $clave)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 mb-1">Sector</label>
            <select name="sector" class="w-full border border-slate-300 px-2 py-1.5 bg-white">
                <option value="">Todos</option>
                @foreach ($sectores as $clave => $cfg)
                    <option value="{{ $clave }}" @selected($filtros['sector'] === $clave)>{{ $cfg['etiqueta'] ?? $clave }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-600 mb-1">Puntuación mínima</label>
            <input type="number" name="puntuacion_min" min="0" max="100" value="{{ $filtros['puntuacion_min'] }}"
                   class="w-full border border-slate-300 px-2 py-1.5">
        </div>
        <div class="flex items-end gap-2">
            <label class="inline-flex items-center gap-2 pb-1.5">
                <input type="checkbox" name="email_verificado" value="1" @checked($filtros['email_verificado'])>
                Email verificado
            </label>
        </div>
        <div class="flex items-end">
            <button class="w-full bg-slate-900 text-white px-3 py-1.5">Filtrar</button>
        </div>
    </form>

    <div class="bg-white border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Nombre</th>
                    <th class="px-3 py-2 font-medium">Dominio</th>
                    <th class="px-3 py-2 font-medium">Sector</th>
                    <th class="px-3 py-2 font-medium text-right">Puntuación</th>
                    <th class="px-3 py-2 font-medium">Hallazgo principal</th>
                    <th class="px-3 py-2 font-medium">Estado</th>
                    <th class="px-3 py-2 font-medium">Contacto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr class="border-t border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-2">
                            <a href="{{ route('leads.ficha', $lead) }}" class="underline">{{ $lead->nombre }}</a>
                        </td>
                        <td class="px-3 py-2">{{ $lead->website_dominio ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $lead->etiquetaSector() ?? '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $lead->auditoria?->puntuacion ?? '—' }}</td>
                        <td class="px-3 py-2 max-w-xs truncate text-slate-600">{{ $lead->auditoria?->hallazgo_principal ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $lead->etiquetaEstado() }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ optional($lead->contactado_at)?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">Sin leads con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $leads->links() }}</div>
@endsection
