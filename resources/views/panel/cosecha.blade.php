@extends('panel.layout')

@section('title', 'Cosecha')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Cosecha</h1>
        <p class="text-sm text-slate-600 mt-1">
            Avance global: <span class="font-semibold tabular-nums">{{ $avance }}%</span>
            ({{ $hechas }} / {{ $total }} áreas hechas)
        </p>
        <div class="mt-3 h-3 bg-slate-200 max-w-md overflow-hidden">
            <div class="h-full bg-slate-800" @style(['width' => max(0, min(100, (float) $avance)).'%'])></div>
        </div>
    </div>

    <div class="bg-white border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Área</th>
                    <th class="px-3 py-2 font-medium">Estado</th>
                    <th class="px-3 py-2 font-medium text-right">Leads nuevos</th>
                    <th class="px-3 py-2 font-medium text-right">Emails</th>
                    <th class="px-3 py-2 font-medium">Iniciada</th>
                    <th class="px-3 py-2 font-medium">Finalizada</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($areas as $area)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ $area->nombre }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'font-medium' => $area->estado === 'error',
                                'text-red-700' => $area->estado === 'error',
                            ])>{{ $area->estado }}</span>
                            @if ($area->estado === 'error' && filled($area->ultimo_error))
                                <p class="mt-1 max-w-md text-xs text-red-600/90 break-words" title="{{ $area->ultimo_error }}">
                                    {{ \Illuminate\Support\Str::limit($area->ultimo_error, 140) }}
                                </p>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $area->leads_encontrados }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $area->emails_encontrados }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ optional($area->iniciada_at)?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ optional($area->finalizada_at)?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">Sin áreas. Ejecuta el seeder de cosecha.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
