@extends('panel.layout')

@section('title', 'Salud')

@section('content')
    @php
        $maxBarra = max(1, (int) $maxEnviados);

        $coloresSalud = [
            'verde' => '#2C7A3F',
            'ambar' => '#96660F',
            'rojo' => '#B0432F',
            'parado' => '#5F6B66',
            'detenido' => '#5F6B66',
        ];

        $eventosUi = [
            'respuesta' => ['Respuestas', '↩', '#2C7A3F', '#E4F3E7'],
            'rebote_duro' => ['Rebotes duros', '✕', '#B0432F', '#F9E9E6'],
            'rebote_blando' => ['Rebotes blandos', '~', '#96660F', '#FAF0DC'],
            'baja' => ['Bajas', '↓', '#9A5A50', '#F5EAE8'],
            'queja' => ['Quejas', '!', '#8B2E22', '#F4D9D4'],
            'ignorado' => ['Ignorados', '·', '#5F6B66', '#EEF1EF'],
        ];

        $nombresProceso = [
            'cosecha' => 'Cosecha',
            'scrape' => 'Scrape',
            'planificador' => 'Planificador',
            'despachador' => 'Despachador',
            'bandeja' => 'Bandeja',
            'vigilante' => 'Vigilante',
        ];

        $descripcionesProceso = [
            'cosecha' => 'Descubre negocios por provincia',
            'scrape' => 'Rastrea webs y emails',
            'planificador' => 'Prepara la cola diaria',
            'despachador' => 'Envía los correos',
            'bandeja' => 'Lee respuestas y rebotes',
            'vigilante' => 'Vigila que todo siga vivo',
        ];

        $formatearEdad = function (?int $edad): string {
            if ($edad === null) {
                return 'sin señal';
            }
            if ($edad < 60) {
                return 'hace '.$edad.' s';
            }
            if ($edad < 3600) {
                return 'hace '.max(1, (int) round($edad / 60)).' min';
            }
            if ($edad < 86400) {
                $h = max(1, (int) round($edad / 3600));

                return 'hace '.$h.' h';
            }

            return 'hace '.max(1, (int) round($edad / 86400)).' d';
        };

        $diasOrdenados = $dias->sortBy('fecha')->values();
        $diasTabla = $dias->sortByDesc('fecha')->values();
        $primerDia = $diasOrdenados->first();
    @endphp

    <div class="max-w-[1240px] flex flex-col gap-4">

        <div class="flex flex-wrap items-center justify-end gap-2">
            @if ($pausado)
                <form method="POST" action="{{ route('envio.reanudar') }}">
                    @csrf
                    <button type="submit" class="btn-savia">Reanudar envíos</button>
                </form>
            @else
                <form method="POST" action="{{ route('envio.pausar') }}">
                    @csrf
                    <button type="submit" class="btn-pausar">Pausar envíos</button>
                </form>
            @endif
        </div>

        {{-- A) Historial 30 días --}}
        <x-marca.tarjeta titulo="Correos enviados · últimos 30 días">
            @if ($diasOrdenados->isEmpty())
                <p class="text-sm text-marca-mut m-0 mb-4">Sin datos de envío.</p>
            @else
                <div class="flex items-end gap-[3px] h-[130px] mb-1.5">
                    @foreach ($diasOrdenados as $dia)
                        @php
                            $enviados = (int) $dia->enviados;
                            $pct = (int) round(($enviados / $maxBarra) * 100);
                            $alto = $enviados > 0 ? max(8, $pct) : 2;
                            $color = $coloresSalud[$dia->salud] ?? '#D0D6D2';
                        @endphp
                        <div
                            class="flex-1 h-full flex flex-col justify-end min-w-0"
                            title="{{ $dia->fecha->format('Y-m-d') }}: {{ $enviados }} enviados · salud {{ $dia->salud }}"
                        >
                            <div
                                class="w-full rounded-t-[2px] mx-auto"
                                @style(["height:{$alto}%", "background:{$color}", 'max-width:18px'])
                            ></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[10.5px] text-marca-mut mb-4">
                    <span>{{ $primerDia?->fecha->translatedFormat('d M') ?? '' }}</span>
                    <span>hoy</span>
                </div>
            @endif

            <div class="max-h-[280px] overflow-auto border-t border-marca-bd -mx-[22px] px-[22px]">
                <table class="w-full min-w-[640px] border-collapse">
                    <thead>
                        <tr>
                            <th class="sticky top-0 bg-white text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2 border-b border-marca-bd">Fecha</th>
                            <th class="sticky top-0 bg-white text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2 border-b border-marca-bd">Escalón</th>
                            <th class="sticky top-0 bg-white text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2 border-b border-marca-bd">Cuota</th>
                            <th class="sticky top-0 bg-white text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2 border-b border-marca-bd">Enviados</th>
                            <th class="sticky top-0 bg-white text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2 border-b border-marca-bd">Rebotes duros</th>
                            <th class="sticky top-0 bg-white text-right text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2 border-b border-marca-bd">Tasa rebote</th>
                            <th class="sticky top-0 bg-white text-left text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2.5 py-2 border-b border-marca-bd">Salud</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diasTabla as $dia)
                            <tr class="hover:bg-[#F7FAF8]">
                                <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] text-xs font-semibold text-marca-txt tabular-nums">
                                    {{ $dia->fecha->timezone('Europe/Madrid')->format('Y-m-d') }}
                                </td>
                                <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] text-right text-xs tabular-nums">{{ $dia->escalon }}</td>
                                <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] text-right text-xs tabular-nums">{{ $dia->cuota_planificada }}</td>
                                <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] text-right text-xs font-bold tabular-nums">{{ $dia->enviados }}</td>
                                <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] text-right text-xs tabular-nums">{{ $dia->rebotes_duros }}</td>
                                <td class="px-2.5 py-1.5 border-b border-[#EFF2EF] text-right text-xs tabular-nums">
                                    {{ number_format((float) $dia->tasa_rebote, 2) }}%
                                </td>
                                <td class="px-2.5 py-1.5 border-b border-[#EFF2EF]">
                                    <x-marca.semaforo :salud="$dia->salud" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-2.5 py-6 text-center text-sm text-marca-mut">Sin días registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-marca.tarjeta>

        {{-- B) Eventos de bandeja --}}
        <x-marca.tarjeta titulo="Eventos de la bandeja · últimos 30 días">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach ($eventosUi as $tipo => [$etiqueta, $icono, $texto, $fondo])
                    @php $total = (int) ($conteosEventos[$tipo] ?? 0); @endphp
                    <div
                        class="rounded-xl px-3.5 py-3.5 border"
                        @style(["background:{$fondo}", "border-color:{$fondo}"])
                    >
                        <span
                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-sm font-extrabold"
                            @style(["color:{$texto}", 'background:rgba(255,255,255,0.55)'])
                            aria-hidden="true"
                        >{{ $icono }}</span>
                        <div class="text-2xl font-extrabold text-bosque tabular-nums mt-2 mb-0.5">{{ $total }}</div>
                        <div class="text-[11.5px] font-semibold text-marca-sec">{{ $etiqueta }}</div>
                    </div>
                @endforeach
            </div>
        </x-marca.tarjeta>

        {{-- C) Procesos --}}
        <x-marca.tarjeta titulo="Procesos internos">
            <div class="flex flex-col">
                @foreach ($latidos as $proceso => $info)
                    @php
                        $vivo = (bool) ($info['vivo'] ?? false);
                        $edad = $info['edad'] ?? null;
                        $colorDot = $vivo ? '#2C7A3F' : '#B0432F';
                        $colorEstado = $vivo ? '#2C7A3F' : '#B0432F';
                        $fondoEstado = $vivo ? '#E4F3E7' : '#F9E9E6';
                    @endphp
                    <div class="flex items-center gap-3 py-2.5 border-b border-[#F1F4F1]">
                        <span
                            class="w-2.5 h-2.5 rounded-full flex-none"
                            @style(["background:{$colorDot}"])
                            title="{{ $vivo ? 'Vivo' : 'Caído' }}"
                        ></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-bold text-marca-txt">
                                {{ $nombresProceso[$proceso] ?? ucfirst($proceso) }}
                            </div>
                            <div class="text-[11.5px] text-marca-mut">
                                {{ $descripcionesProceso[$proceso] ?? '' }}
                            </div>
                        </div>
                        <span
                            class="text-[11px] font-bold px-2 py-0.5 rounded-full"
                            @style(["color:{$colorEstado}", "background:{$fondoEstado}"])
                        >
                            {{ $vivo ? 'Vivo' : 'Caído' }}
                        </span>
                        <span class="text-xs text-marca-mut w-[110px] text-right tabular-nums flex-none">
                            {{ $formatearEdad(is_int($edad) ? $edad : null) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-marca.tarjeta>

    </div>
@endsection
