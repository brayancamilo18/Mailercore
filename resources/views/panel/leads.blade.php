@extends('panel.layout')

@section('title', 'Leads')

@section('content')
    <div class="max-w-[1240px] space-y-3.5">

        {{-- A) Filtros --}}
        <x-marca.tarjeta>
            <form method="GET" action="{{ route('leads.indice') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[140px]">
                    <label for="filtro-estado" class="block text-[11px] font-bold text-marca-mut mb-1">Estado</label>
                    <select id="filtro-estado" name="estado"
                            class="w-full text-xs font-semibold text-marca-txt border border-[#D6DDD8] rounded-lg px-2 py-1.5 bg-hueso focus:outline-none focus:ring-2 focus:ring-brote/40 focus:border-savia">
                        <option value="">Todos</option>
                        @foreach ($estados as $clave => $etiqueta)
                            <option value="{{ $clave }}" @selected($filtros['estado'] === $clave)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[160px]">
                    <label for="filtro-sector" class="block text-[11px] font-bold text-marca-mut mb-1">Sector</label>
                    <select id="filtro-sector" name="sector"
                            class="w-full text-xs font-semibold text-marca-txt border border-[#D6DDD8] rounded-lg px-2 py-1.5 bg-hueso focus:outline-none focus:ring-2 focus:ring-brote/40 focus:border-savia">
                        <option value="">Todos</option>
                        @foreach ($sectores as $clave => $cfg)
                            <option value="{{ $clave }}" @selected($filtros['sector'] === $clave)>{{ $cfg['etiqueta'] ?? $clave }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[120px]">
                    <label for="filtro-puntuacion" class="block text-[11px] font-bold text-marca-mut mb-1">Puntuación mínima</label>
                    <input id="filtro-puntuacion" type="number" name="puntuacion_min" min="0" max="100"
                           value="{{ $filtros['puntuacion_min'] }}"
                           class="w-full text-xs font-semibold text-marca-txt border border-[#D6DDD8] rounded-lg px-2 py-1.5 bg-hueso focus:outline-none focus:ring-2 focus:ring-brote/40 focus:border-savia">
                </div>

                <label class="inline-flex items-center gap-2 pb-2 cursor-pointer select-none">
                    <input type="checkbox" name="email_verificado" value="1" @checked($filtros['email_verificado'])
                           class="rounded border-[#D6DDD8] text-savia focus:ring-brote/40">
                    <span class="text-xs font-semibold text-marca-sec">Solo con email verificado</span>
                </label>

                <div class="flex items-center gap-2 ml-auto">
                    <button type="submit"
                            class="bg-savia hover:bg-bosque text-hueso text-xs font-extrabold rounded-lg px-4 py-2 transition-colors">
                        Filtrar
                    </button>
                    <a href="{{ route('leads.indice') }}"
                       class="text-xs font-bold text-savia hover:text-bosque px-2 py-2">
                        Limpiar
                    </a>
                    <span class="text-xs text-marca-mut tabular-nums whitespace-nowrap">
                        {{ number_format($leads->total()) }} empresas
                    </span>
                </div>
            </form>
        </x-marca.tarjeta>

        {{-- B) Tabla --}}
        <x-marca.tarjeta :flush="true">
            @if ($leads->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-sm text-marca-sec mb-3">No hay leads que coincidan con el filtro.</p>
                    <a href="{{ route('leads.indice') }}" class="text-sm font-bold text-savia hover:text-bosque">Limpiar filtros</a>
                </div>
            @else
                <div class="overflow-x-auto max-h-[calc(100vh-300px)] overflow-y-auto">
                    <table class="w-full min-w-[900px] border-collapse">
                        <thead>
                            <tr class="bg-[#F7FAF8]">
                                <th class="sticky top-0 z-[1] bg-[#F7FAF8] text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-3.5 py-2.5 border-b border-marca-bd">Empresa</th>
                                <th class="sticky top-0 z-[1] bg-[#F7FAF8] text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Sector</th>
                                <th class="sticky top-0 z-[1] bg-[#F7FAF8] text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Puntuación</th>
                                <th class="sticky top-0 z-[1] bg-[#F7FAF8] text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Hallazgo</th>
                                <th class="sticky top-0 z-[1] bg-[#F7FAF8] text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Estado</th>
                                <th class="sticky top-0 z-[1] bg-[#F7FAF8] text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-3.5 py-2.5 border-b border-marca-bd">Contacto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leads as $lead)
                                @php
                                    $puntuacion = $lead->auditoria?->puntuacion;
                                    $colorPuntos = match (true) {
                                        $puntuacion === null => null,
                                        (int) $puntuacion < 50 => '#B0432F',
                                        (int) $puntuacion < 80 => '#96660F',
                                        default => '#2C7A3F',
                                    };
                                    $url = route('leads.ficha', $lead);
                                    $pctPuntos = max(0, min(100, (int) $puntuacion));
                                @endphp
                                <tr
                                    class="cursor-pointer hover:bg-[#F7FAF8] transition-colors"
                                    onclick="window.location='{{ $url }}'"
                                >
                                    <td class="px-3.5 py-[7px] border-b border-[#EFF2EF] whitespace-nowrap">
                                        <a href="{{ $url }}" class="block text-[12.5px] font-bold text-bosque hover:text-bosque" onclick="event.stopPropagation()">
                                            {{ $lead->nombre }}
                                        </a>
                                        <div class="text-xs text-marca-mut mt-0.5">{{ $lead->website_dominio ?? '—' }}</div>
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] whitespace-nowrap">
                                        @if ($lead->sector)
                                            <x-marca.sector :sector="$lead->sector" />
                                        @else
                                            <span class="text-xs text-marca-mut">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] text-right whitespace-nowrap">
                                        @if ($puntuacion !== null)
                                            <span class="inline-flex items-center gap-1.5 justify-end">
                                                <span class="inline-block w-11 h-[5px] rounded-full bg-[#EDF0ED] overflow-hidden">
                                                    <span class="block h-full rounded-full" @style(["width:{$pctPuntos}%", "background:{$colorPuntos}"])></span>
                                                </span>
                                                <span class="text-[12.5px] font-extrabold tabular-nums" @style(["color:{$colorPuntos}"])>{{ (int) $puntuacion }}</span>
                                            </span>
                                        @else
                                            <span class="text-[12.5px] text-marca-mut">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] text-xs text-marca-sec max-w-[260px] truncate">
                                        {{ \Illuminate\Support\Str::limit($lead->auditoria?->hallazgo_principal ?? '—', 80) }}
                                    </td>
                                    <td class="px-2.5 py-[7px] border-b border-[#EFF2EF] whitespace-nowrap">
                                        <x-marca.estado-lead :estado="$lead->estado" />
                                    </td>
                                    <td class="px-3.5 py-[7px] border-b border-[#EFF2EF] text-right text-xs text-marca-mut tabular-nums whitespace-nowrap">
                                        {{ $lead->contactado_at?->timezone('Europe/Madrid')->translatedFormat('d M Y') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center gap-3 px-4 py-2.5 border-t border-marca-bd text-xs text-marca-mut">
                    <span class="tabular-nums">
                        Mostrando {{ $leads->firstItem() }}–{{ $leads->lastItem() }} de {{ number_format($leads->total()) }}
                    </span>
                    <div class="flex-1"></div>
                    <div class="paginacion-marca [&_nav]:flex [&_nav]:gap-1 [&_a]:text-savia [&_a]:font-bold [&_a]:px-2.5 [&_a]:py-1 [&_a]:rounded-md [&_a]:border [&_a]:border-[#D6DDD8] [&_a]:bg-white hover:[&_a]:bg-[#F7FAF8] [&_span]:px-2.5 [&_span]:py-1 [&_span]:rounded-md [&_span]:font-bold [&_.cursor-default]:text-marca-mut [&_.cursor-default]:border-[#D6DDD8]">
                        {{ $leads->links() }}
                    </div>
                </div>
            @endif
        </x-marca.tarjeta>

    </div>
@endsection
