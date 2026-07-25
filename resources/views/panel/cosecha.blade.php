@extends('panel.layout')

@section('title', 'Cosecha')

@section('content')
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\AreaCosecha> $areas */
        /** @var array<string, string> $mapa_estados */

        $nombreACodigo = \App\Support\ProvinciasIne::nombreACodigo();
        $mapaEstados = $mapa_estados ?? \App\Support\ProvinciasIne::statusesDesdeAreas($areas);

        $ordenEstado = ['en_proceso' => 0, 'error' => 1, 'pendiente' => 2, 'hecho' => 3];
        $areasOrdenadas = $areas->sortBy(function ($area) use ($ordenEstado) {
            return ($ordenEstado[$area->estado] ?? 9).'-'.str_pad((string) $area->prioridad, 4, '0', STR_PAD_LEFT).'-'.$area->nombre;
        })->values();

        $conteos = [
            'proceso' => $areas->where('estado', 'en_proceso')->count(),
            'pendiente' => $areas->where('estado', 'pendiente')->count(),
            'hecho' => (int) $hechas,
            'error' => $areas->where('estado', 'error')->count(),
        ];

        $leyenda = [
            ['hecho', 'Hecho', '#0F6E56', $conteos['hecho']],
            ['proceso', 'En proceso', '#5DCAA5', $conteos['proceso']],
            ['error', 'Error', '#C0503F', $conteos['error']],
            ['pendiente', 'Pendiente', '#D0D6D2', $conteos['pendiente']],
        ];

        $avancePct = max(0, min(100, (float) $avance));

        $badges = [
            'hecho' => ['Hecho', '#0F6E56', '#EAF4F0'],
            'en_proceso' => ['En proceso', '#0B1F1A', '#5DCAA5'],
            'error' => ['Error', '#B0432F', '#F9E9E6'],
            'pendiente' => ['Pendiente', '#5F6B66', '#EEF1EF'],
        ];
    @endphp

    <div class="max-w-[1240px]" id="cosecha-panel">

        {{-- Avance global (maqueta) --}}
        <x-marca.tarjeta class="mb-4">
            <div class="flex flex-wrap gap-7 items-center">
                <div class="flex-none">
                    <span class="text-[42px] font-extrabold text-bosque tabular-nums leading-none">{{ number_format($avancePct, 0, ',', '.') }} %</span>
                    <div class="text-xs font-semibold text-marca-mut mt-1">
                        avance global · {{ $hechas }} de {{ $total }} provincias
                    </div>
                </div>
                <div class="flex-1 min-w-[240px]">
                    <div class="h-[9px] rounded-full bg-[#E8ECE8] overflow-hidden">
                        <div class="h-full rounded-full bg-savia transition-all" @style(["width:{$avancePct}%"])></div>
                    </div>
                </div>
                <div class="flex-none flex flex-wrap gap-3.5">
                    @foreach ($leyenda as [$clave, $label, $color, $n])
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#4C5A54]">
                            <span class="w-2.5 h-2.5 rounded-full flex-none" @style(["background:{$color}"])></span>
                            {{ $label }}
                            <span class="text-marca-mut tabular-nums">{{ $n }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
            <p class="text-xs text-marca-mut mt-3 mb-0">
                La cosecha barre España en bucle; los duplicados se omiten y se siguen buscando negocios nuevos.
            </p>
        </x-marca.tarjeta>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">

            {{-- Mapa choropleth --}}
            <x-marca.tarjeta titulo="Mapa por provincia">
                <spain-map
                    id="mapa-cosecha"
                    statuses='@json($mapaEstados)'
                    style="display:block;width:100%;min-height:320px"
                ></spain-map>
            </x-marca.tarjeta>

            {{-- Tabla principal --}}
            <x-marca.tarjeta :flush="true">
                <div class="px-[22px] pt-[18px] pb-2">
                    <h2 class="text-[11px] font-extrabold tracking-[0.08em] uppercase text-marca-mut m-0">
                        Provincias ({{ $areasOrdenadas->count() }})
                    </h2>
                </div>
                @if ($areasOrdenadas->isEmpty())
                    <div class="px-6 py-12 text-center text-sm text-marca-mut">
                        Sin áreas. Ejecuta el seeder de cosecha.
                    </div>
                @else
                    <div id="tabla-provincias" class="max-h-[560px] overflow-auto">
                        <table class="w-full min-w-[520px] border-collapse">
                            <thead>
                                <tr>
                                    <th class="sticky top-0 bg-white text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-3.5 py-2.5 border-b border-marca-bd">Provincia</th>
                                    <th class="sticky top-0 bg-white text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Estado</th>
                                    <th class="sticky top-0 bg-white text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Leads</th>
                                    <th class="sticky top-0 bg-white text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2.5 border-b border-marca-bd">Correos</th>
                                    <th class="sticky top-0 bg-white text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-3.5 py-2.5 border-b border-marca-bd">Actualizado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($areasOrdenadas as $area)
                                    @php
                                        [$eti, $txt, $bg] = $badges[$area->estado] ?? $badges['pendiente'];
                                        $fecha = $area->finalizada_at ?? $area->iniciada_at;
                                        $codigo = $nombreACodigo[$area->nombre] ?? '';
                                    @endphp
                                    <tr
                                        id="prov-{{ $codigo }}"
                                        data-code="{{ $codigo }}"
                                        class="hover:bg-[#F7FAF8] cursor-pointer transition-colors"
                                    >
                                        <td class="px-3.5 py-1.5 border-b border-[#EFF2EF] text-[12.5px] font-semibold text-marca-txt">
                                            {{ $area->nombre }}
                                            @if ($area->estado === 'error' && filled($area->ultimo_error))
                                                <div class="text-[11px] font-normal text-roj mt-0.5 max-w-xs truncate" title="{{ $area->ultimo_error }}">
                                                    {{ \Illuminate\Support\Str::limit($area->ultimo_error, 80) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center px-[9px] py-0.5 rounded-full text-[11px] font-bold"
                                                @style(["color:{$txt}", "background:{$bg}"])
                                            >{{ $eti }}</span>
                                        </td>
                                        <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] text-right text-xs tabular-nums">
                                            {{ number_format((int) $area->leads_encontrados, 0, ',', '.') }}
                                        </td>
                                        <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] text-right text-xs tabular-nums">
                                            {{ number_format((int) $area->emails_encontrados, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3.5 py-1.5 border-b border-[#EFF2EF] text-right text-xs text-marca-mut tabular-nums whitespace-nowrap">
                                            {{ $fecha?->timezone('Europe/Madrid')->translatedFormat('d M Y H:i') ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-marca.tarjeta>

        </div>
    </div>
@endsection

@push('scripts')
<script src="https://unpkg.com/d3@7.9.0/dist/d3.min.js" integrity="sha384-CjloA8y00+1SDAUkjs099PVfnY2KmDC2BZnws9kh8D/lX1s46w6EPhpXdqMfjK6i" crossorigin="anonymous"></script>
<script src="https://unpkg.com/topojson-client@3.1.0/dist/topojson-client.min.js" integrity="sha384-Ukv1p/xTma6P4/2bY5KzWBw+ydSpXmhCMtyciIQVDJ1RmOxtCYNMF1uXT9T63H67" crossorigin="anonymous"></script>
<script src="{{ asset('js/spain-map.js') }}"></script>
<script src="{{ asset('js/cosecha-mapa.js') }}"></script>
@endpush
