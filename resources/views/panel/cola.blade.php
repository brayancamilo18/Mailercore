@extends('panel.layout')

@section('title', 'Cola')

@section('content')
    @php
        $cuotaSegura = max(0, (int) $cuota);
        $enviadosSeguros = max(0, (int) $enviados);
        $progreso = $cuotaSegura > 0
            ? min(100, round(($enviadosSeguros / $cuotaSegura) * 100, 1))
            : 0;
    @endphp

    <div class="max-w-[1240px] space-y-4">

        <x-marca.tarjeta>
            <div class="text-[11px] font-extrabold tracking-[0.08em] uppercase text-marca-mut mb-2">
                Enviados de la cuota de hoy
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-[30px] font-extrabold text-bosque tabular-nums leading-none">{{ $enviadosSeguros }}</span>
                <span class="text-sm font-semibold text-marca-mut">de {{ $cuotaSegura }} enviados hoy</span>
            </div>
            <div class="h-[7px] rounded-full bg-[#E8ECE8] mt-2.5 overflow-hidden">
                <div class="h-full rounded-full bg-savia transition-all" @style(["width:{$progreso}%"])></div>
            </div>
        </x-marca.tarjeta>

        <x-marca.tarjeta :flush="true">
            @if ($mensajes->isEmpty())
                <div class="px-6 py-14 text-center">
                    <p class="text-sm text-marca-sec m-0">No hay correos programados para hoy ni mañana.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] border-collapse">
                        <thead>
                            <tr class="bg-[#F7FAF8]">
                                <th class="text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-3.5 py-2.5 border-b border-marca-bd">Hora</th>
                                <th class="text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Sector</th>
                                <th class="text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Destino</th>
                                <th class="text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Asunto</th>
                                <th class="text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Hallazgo</th>
                                <th class="text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Estado</th>
                                <th class="text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-3.5 py-2.5 border-b border-marca-bd">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mensajes as $item)
                                @php
                                    $m = $item['mensaje'];
                                    $enviado = $m->estado === 'enviado';
                                    $cancelable = in_array($m->estado, ['pendiente', 'enviando'], true);
                                @endphp
                                <tr class="hover:bg-[#F7FAF8] transition-colors {{ $enviado ? 'opacity-60' : '' }}">
                                    <td class="px-3.5 py-[7px] border-b border-[#EFF2EF] text-[12.5px] font-bold text-bosque tabular-nums whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5">
                                            @if ($enviado)
                                                <span class="text-ok text-[11px]" title="Enviado" aria-hidden="true">✓</span>
                                            @endif
                                            {{ $m->programado_para?->timezone('Europe/Madrid')->format('H:i') ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] text-[12.5px] text-marca-sec whitespace-nowrap">
                                        {{ $item['sector'] ?? '—' }}
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] text-[12.5px] font-semibold text-savia whitespace-nowrap">
                                        {{ $item['dominio'] ?? '—' }}
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] text-[12.5px] text-marca-txt max-w-[280px] truncate">
                                        {{ $m->asunto }}
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] text-xs text-marca-mut max-w-[200px] truncate">
                                        {{ \Illuminate\Support\Str::limit($item['hallazgo'] ?? '—', 60) }}
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] whitespace-nowrap">
                                        <x-marca.estado-correo :estado="$m->estado" />
                                    </td>
                                    <td class="px-3.5 py-[7px] border-b border-[#EFF2EF] text-right whitespace-nowrap">
                                        <a href="{{ route('mensajes.ver', $m) }}"
                                           class="text-xs font-bold text-savia hover:text-bosque px-1.5 py-0.5">
                                            Ver
                                        </a>
                                        @if ($cancelable)
                                            <form method="POST" action="{{ route('mensajes.cancelar', $m) }}"
                                                  class="inline"
                                                  onsubmit="return confirm('¿Cancelar este mensaje?')">
                                                @csrf
                                                <button type="submit"
                                                        class="text-xs font-bold text-roj hover:underline px-1.5 py-0.5 bg-transparent border-0 cursor-pointer">
                                                    Cancelar
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-marca.tarjeta>

    </div>
@endsection
