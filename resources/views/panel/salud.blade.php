@extends('panel.layout')

@section('title', 'Salud')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Salud</h1>
        @if ($pausado)
            <form method="POST" action="{{ route('envio.reanudar') }}">
                @csrf
                <button class="px-4 py-2 text-sm font-semibold bg-emerald-600 text-white">REANUDAR</button>
            </form>
        @else
            <form method="POST" action="{{ route('envio.pausar') }}">
                @csrf
                <button class="px-4 py-2 text-sm font-semibold bg-red-600 text-white">PAUSAR</button>
            </form>
        @endif
    </div>

    <section class="mb-8">
        <h2 class="text-lg font-semibold mb-3">Enviados por día (30 días)</h2>
        <div class="bg-white border border-slate-200 p-4">
            <div class="flex items-end gap-1 h-40">
                @forelse ($dias as $dia)
                    @php
                        $alto = (int) round(((int) $dia->enviados / $maxEnviados) * 100);
                        $altoBarra = max($alto, $dia->enviados > 0 ? 4 : 0);
                    @endphp
                    <div class="flex-1 flex flex-col justify-end items-center gap-1 min-w-0" title="{{ $dia->fecha->format('Y-m-d') }}: {{ $dia->enviados }}">
                        <div class="w-full bg-slate-800" @style(['height' => $altoBarra.'%'])></div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Sin datos de envío.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="text-lg font-semibold mb-3">Últimos 30 días — dias_envio</h2>
        <div class="bg-white border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Fecha</th>
                        <th class="px-3 py-2 font-medium text-right">Escalón</th>
                        <th class="px-3 py-2 font-medium text-right">Cuota</th>
                        <th class="px-3 py-2 font-medium text-right">Enviados</th>
                        <th class="px-3 py-2 font-medium text-right">Fallidos</th>
                        <th class="px-3 py-2 font-medium text-right">Rebotes</th>
                        <th class="px-3 py-2 font-medium text-right">Respuestas</th>
                        <th class="px-3 py-2 font-medium">Salud</th>
                        <th class="px-3 py-2 font-medium text-right">Tasa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dias->sortByDesc('fecha') as $dia)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $dia->fecha->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-right">{{ $dia->escalon }}</td>
                            <td class="px-3 py-2 text-right">{{ $dia->cuota_planificada }}</td>
                            <td class="px-3 py-2 text-right">{{ $dia->enviados }}</td>
                            <td class="px-3 py-2 text-right">{{ $dia->fallidos }}</td>
                            <td class="px-3 py-2 text-right">{{ $dia->rebotes_duros }}</td>
                            <td class="px-3 py-2 text-right">{{ $dia->respuestas }}</td>
                            <td class="px-3 py-2">{{ $dia->salud }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $dia->tasa_rebote, 2) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-6 text-center text-slate-500">Sin días registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="text-lg font-semibold mb-3">Eventos de bandeja (30 días)</h2>
        <div class="bg-white border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Tipo</th>
                        <th class="px-3 py-2 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($conteosEventos as $tipo => $total)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $tipo }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="text-lg font-semibold mb-3">Latidos</h2>
        <div class="bg-white border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Proceso</th>
                        <th class="px-3 py-2 font-medium">Estado</th>
                        <th class="px-3 py-2 font-medium text-right">Edad</th>
                        <th class="px-3 py-2 font-medium text-right">TTL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($latidos as $proceso => $info)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 font-medium">{{ $proceso }}</td>
                            <td class="px-3 py-2">
                                @if ($info['edad'] === null)
                                    <span class="text-slate-400">sin latido</span>
                                @elseif ($info['vivo'])
                                    <span class="text-emerald-700">vivo</span>
                                @else
                                    <span class="text-red-700">caducado</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ $info['edad'] === null ? '—' : $info['edad'].' s' }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $info['ttl'] }} s</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
