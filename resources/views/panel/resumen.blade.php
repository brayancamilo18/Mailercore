@extends('panel.layout')

@section('title', 'Resumen')

@section('content')
    @php
        $dia = $rampa['dia'];
        $colores = [
            'verde' => 'bg-emerald-500',
            'ambar' => 'bg-amber-400',
            'rojo' => 'bg-red-500',
            'parado' => 'bg-slate-500',
        ];
        $color = $colores[$dia->salud] ?? 'bg-slate-400';
    @endphp

    {{-- Rampa --}}
    <section class="bg-white border border-slate-200 p-6 mb-8">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Rampa de envío</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Escalón {{ $dia->escalon }} · cuota de hoy {{ $dia->cuota_planificada }} · racha {{ $rampa['dias_racha'] }} días
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 text-sm">
                    <span class="inline-block w-3 h-3 rounded-full {{ $color }}"></span>
                    <span class="uppercase tracking-wide font-medium">{{ $dia->salud }}</span>
                    <span class="text-slate-500">{{ number_format((float) $dia->tasa_rebote, 2) }}% rebote</span>
                </div>
                @if ($rampa['pausado'])
                    <form method="POST" action="{{ route('envio.reanudar') }}">
                        @csrf
                        <button class="px-5 py-3 text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700">
                            REANUDAR
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('envio.pausar') }}">
                        @csrf
                        <button class="px-5 py-3 text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                            PAUSAR
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mb-2 flex justify-between text-sm">
            <span>Enviados hoy</span>
            <span class="font-medium">{{ $dia->enviados }} / {{ $dia->cuota_planificada }}</span>
        </div>
        <div class="h-3 bg-slate-100 overflow-hidden">
            <div class="h-full bg-slate-800 transition-all" @style(['width' => max(0, min(100, (float) $rampa['progreso'])).'%'])></div>
        </div>
        @if ($dia->nota)
            <p class="mt-3 text-xs text-slate-500">{{ $dia->nota }}</p>
        @endif
    </section>

    {{-- Embudo --}}
    <section class="mb-8">
        <h2 class="text-lg font-semibold mb-3">Embudo</h2>
        <div class="bg-white border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Etapa</th>
                        <th class="px-3 py-2 font-medium text-right">Total</th>
                        <th class="px-3 py-2 font-medium text-right">% vs anterior</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($embudo as $etapa)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $etapa['etiqueta'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($etapa['total']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-500">
                                {{ $etapa['porcentaje'] === null ? '—' : $etapa['porcentaje'].'%' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Sectores --}}
    <section class="mb-8">
        <h2 class="text-lg font-semibold mb-3">Rendimiento por sector</h2>
        <div class="bg-white border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Sector</th>
                        <th class="px-3 py-2 font-medium text-right">Leads</th>
                        <th class="px-3 py-2 font-medium text-right">Auditados</th>
                        <th class="px-3 py-2 font-medium text-right">Puntuación media</th>
                        <th class="px-3 py-2 font-medium text-right">Contactados</th>
                        <th class="px-3 py-2 font-medium text-right">Respondidos</th>
                        <th class="px-3 py-2 font-medium text-right">Tasa respuesta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sectores as $fila)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $fila['etiqueta'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fila['leads'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fila['auditados'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fila['puntuacion_media'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fila['contactados'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fila['respondidos'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium">
                                {{ $fila['tasa_respuesta'] === null ? '—' : $fila['tasa_respuesta'].'%' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Últimas respuestas --}}
    <section>
        <h2 class="text-lg font-semibold mb-3">Últimas 10 respuestas</h2>
        <div class="bg-white border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Fecha</th>
                        <th class="px-3 py-2 font-medium">Dominio</th>
                        <th class="px-3 py-2 font-medium">Extracto</th>
                        <th class="px-3 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($respuestas as $fila)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ optional($fila['evento']->recibido_at)?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-2">{{ $fila['dominio'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 max-w-md truncate">{{ $fila['evento']->extracto }}</td>
                            <td class="px-3 py-2 text-right">
                                @if ($fila['lead'])
                                    <a href="{{ route('leads.ficha', $fila['lead']) }}" class="text-slate-900 underline">Ver lead</a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-slate-500">Sin respuestas todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    setInterval(async () => {
        try {
            const res = await fetch("{{ route('api.estado') }}", { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            // Polling silencioso: la UI se refresca al recargar; el endpoint queda listo.
            window.__panelEstado = await res.json();
        } catch (e) {}
    }, 15000);
</script>
@endpush
