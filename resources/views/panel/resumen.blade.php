@extends('panel.layout')

@section('title', 'Resumen')

@section('content')
    @php
        $dia = $rampa['dia'];
        $progreso = max(0, min(100, (float) $rampa['progreso']));
        $escalon = (int) $dia->escalon;
        $salud = $dia->salud ?? 'verde';
        $tasa = number_format((float) $dia->tasa_rebote, 1, ',', '.');

        $saludMapa = [
            'verde' => ['icono' => '✓', 'titulo' => 'Campaña sana', 'texto' => '#2C7A3F', 'fondo' => '#E4F3E7'],
            'ambar' => ['icono' => '!', 'titulo' => 'Atención a los rebotes', 'texto' => '#96660F', 'fondo' => '#FAF0DC'],
            'rojo' => ['icono' => '✕', 'titulo' => 'Rebotes altos', 'texto' => '#B0432F', 'fondo' => '#F9E9E6'],
            'parado' => ['icono' => '⏸', 'titulo' => 'Campaña detenida', 'texto' => '#5F6B66', 'fondo' => '#EEF1EF'],
            'detenido' => ['icono' => '⏸', 'titulo' => 'Campaña detenida', 'texto' => '#5F6B66', 'fondo' => '#EEF1EF'],
        ];

        // Pausada no cambia el semáforo (sigue por rebotes). Solo 'parado'/'detenido' = campaña detenida.
        $saludUi = $saludMapa[$salud] ?? $saludMapa['verde'];

        $saludDetalle = in_array($salud, ['parado', 'detenido'], true)
            ? 'Los envíos están detenidos. Nadie recibirá correos hasta reanudar.'
            : $tasa.' % de rebotes · umbral de atención en 2 %';

        $baseEmbudo = max(1, (int) (($embudo[0]['total'] ?? 0)));
        $totalEtapas = count($embudo);
        $cuotasEscalon = [10, 15, 22, 30, 40];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 lg:gap-4 max-w-[1240px]">

        {{-- A) Rampa — padding como en maqueta --}}
        <x-marca.tarjeta :flush="true" class="lg:col-span-12">
            <div class="px-[26px] py-[22px]">
            <div class="flex flex-wrap gap-8 items-stretch">
                <div class="flex-[1.4] min-w-[300px]">
                    <div class="flex items-center gap-2.5 mb-2.5 flex-wrap">
                        <span class="text-[11px] font-extrabold tracking-[0.08em] uppercase text-marca-mut">Rampa de envío</span>
                        <span class="text-[11px] font-bold text-savia bg-[#EAF4F0] rounded-full px-[9px] py-0.5">
                            Escalón {{ $escalon }} de 5 · {{ $dia->cuota_planificada }} correos/día
                        </span>
                    </div>

                    <div class="flex items-baseline gap-2">
                        <span class="text-[44px] font-extrabold text-bosque leading-none tabular-nums">{{ $dia->enviados }}</span>
                        <span class="text-[17px] font-semibold text-marca-mut">de {{ $dia->cuota_planificada }} enviados hoy</span>
                    </div>

                    <div class="h-[9px] rounded-full bg-[#E8ECE8] my-3.5 overflow-hidden">
                        <div class="h-full rounded-full bg-savia transition-all" @style(["width:{$progreso}%"])></div>
                    </div>

                    <div class="flex items-center gap-3.5 flex-wrap">
                        <div class="flex items-center gap-[5px]">
                            @for ($i = 1; $i <= 5; $i++)
                                @php
                                    $colorDot = $i < $escalon ? '#5DCAA5' : ($i === $escalon ? '#0F6E56' : '#E2E8E3');
                                    $outline = $i === $escalon ? '2px solid #C8E8DC' : 'none';
                                    $cuotaTitulo = $cuotasEscalon[$i - 1] ?? '—';
                                @endphp
                                <span
                                    class="inline-block rounded-full"
                                    @style([
                                        'width:9px',
                                        'height:9px',
                                        "background:{$colorDot}",
                                        "outline:{$outline}",
                                    ])
                                    title="Escalón {{ $i }} · {{ $cuotaTitulo }}/día"
                                ></span>
                            @endfor
                            <span class="text-[11px] text-marca-mut ml-1">escalones</span>
                        </div>
                        <span class="text-[12.5px] font-bold text-marca-txt">{{ $rampa['dias_racha'] }} días seguidos enviando</span>
                    </div>
                </div>

                <div class="hidden xl:block w-px self-stretch bg-[#EFF2EF]"></div>

                <div class="flex-1 min-w-[260px] flex items-center gap-[18px]">
                    <div
                        class="flex-none w-16 h-16 rounded-full flex items-center justify-center text-[26px] font-extrabold border-2"
                        @style([
                            "background:{$saludUi['fondo']}",
                            "color:{$saludUi['texto']}",
                            "border-color:{$saludUi['texto']}",
                        ])
                        aria-hidden="true"
                    >{{ $saludUi['icono'] }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="text-lg font-extrabold text-bosque">{{ $saludUi['titulo'] }}</div>
                        <div class="text-[12.5px] text-marca-sec mt-[3px] mb-3">{{ $saludDetalle }}</div>
                        @if ($rampa['pausado'])
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
                </div>
            </div>
            </div>
        </x-marca.tarjeta>

        {{-- B) Embudo --}}
        <x-marca.tarjeta titulo="Embudo de conversión" class="lg:col-span-5">
            <div class="flex flex-col gap-[9px]">
                @foreach ($embudo as $etapa)
                    @php
                        $ancho = min(100, round(((int) $etapa['total'] / $baseEmbudo) * 100, 1));
                        $relleno = $loop->index >= max(0, $totalEtapas - 3) ? '#0F6E56' : '#7FA99A';
                    @endphp
                    <div class="grid grid-cols-[118px_1fr_54px_44px] gap-2.5 items-center max-sm:grid-cols-[minmax(72px,90px)_1fr_48px_40px]">
                        <span class="text-xs font-semibold text-[#4C5A54] text-right leading-tight">{{ $etapa['etiqueta'] }}</span>
                        <div class="h-4 rounded bg-[#F1F4F1] overflow-hidden">
                            <div class="h-full rounded" @style(["width:{$ancho}%", "background:{$relleno}"])></div>
                        </div>
                        <span class="text-[12.5px] font-extrabold text-bosque text-right tabular-nums">{{ number_format($etapa['total'], 0, ',', '.') }}</span>
                        <span class="text-[10.5px] font-semibold text-marca-mut text-right tabular-nums">
                            {{ $etapa['porcentaje'] === null ? '' : number_format((float) $etapa['porcentaje'], 1, ',', '.').' %' }}
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="text-[10.5px] text-marca-mut mt-3">El porcentaje es respecto a los leads totales.</div>
        </x-marca.tarjeta>

        {{-- C) Sectores --}}
        <x-marca.tarjeta titulo="Rendimiento por sector" class="lg:col-span-7 overflow-x-auto">
            <table class="w-full min-w-[640px] border-collapse">
                <thead>
                    <tr>
                        @foreach (['Sector', 'Leads', 'Auditados', 'Punt. media', 'Contactados', 'Respondidos', 'Tasa resp.'] as $i => $col)
                            <th class="text-[10px] font-extrabold tracking-[0.06em] uppercase text-marca-mut px-2 py-1.5 border-b border-marca-bd {{ $i === 0 ? 'text-left' : 'text-right' }}">
                                {{ $col }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sectores as $fila)
                        <tr @class(['bg-[#F2F8F4]' => $loop->first])>
                            <td class="px-2 py-[7px] border-b border-[#EFF2EF]">
                                <span class="inline-flex items-center gap-[7px] flex-wrap">
                                    <x-marca.sector :sector="$fila['sector']" />
                                    @if ($loop->first && $fila['tasa_respuesta'] !== null)
                                        <span class="text-[9.5px] font-bold text-ok bg-ok-bg rounded-full px-[7px] py-px">mejor tasa</span>
                                    @endif
                                </span>
                            </td>
                            <td class="px-2 py-[7px] border-b border-[#EFF2EF] text-right text-[12.5px] tabular-nums">{{ number_format($fila['leads'], 0, ',', '.') }}</td>
                            <td class="px-2 py-[7px] border-b border-[#EFF2EF] text-right text-[12.5px] tabular-nums">{{ number_format($fila['auditados'], 0, ',', '.') }}</td>
                            <td class="px-2 py-[7px] border-b border-[#EFF2EF] text-right text-[12.5px] tabular-nums">
                                {{ $fila['puntuacion_media'] === null ? '—' : number_format((float) $fila['puntuacion_media'], 1, ',', '.') }}
                            </td>
                            <td class="px-2 py-[7px] border-b border-[#EFF2EF] text-right text-[12.5px] tabular-nums">{{ number_format($fila['contactados'], 0, ',', '.') }}</td>
                            <td class="px-2 py-[7px] border-b border-[#EFF2EF] text-right text-[12.5px] tabular-nums">{{ number_format($fila['respondidos'], 0, ',', '.') }}</td>
                            <td class="px-2 py-[7px] border-b border-[#EFF2EF] text-right text-[12.5px] font-extrabold text-bosque tabular-nums">
                                {{ $fila['tasa_respuesta'] === null ? '—' : number_format((float) $fila['tasa_respuesta'], 1, ',', '.').' %' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-6 text-center text-sm text-marca-mut">Sin datos de sectores.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-marca.tarjeta>

        {{-- D) Últimas respuestas — rejilla 2 cols como maqueta --}}
        <x-marca.tarjeta titulo="Últimas respuestas recibidas" class="lg:col-span-12">
            @if ($respuestas->isEmpty())
                <p class="text-sm text-marca-mut m-0">Aún no hay respuestas.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1">
                    @foreach ($respuestas as $item)
                        <div class="flex gap-2.5 items-baseline py-[7px] border-b border-[#F1F4F1]">
                            @if ($item['lead'])
                                <a href="{{ route('leads.ficha', $item['lead']) }}"
                                   class="text-[12.5px] font-bold flex-none text-savia hover:text-bosque hover:underline">
                                    {{ $item['dominio'] ?? $item['lead']->nombre }}
                                </a>
                            @else
                                <span class="text-[12.5px] font-bold flex-none text-bosque">{{ $item['dominio'] ?? '—' }}</span>
                            @endif
                            <span class="text-xs text-marca-sec flex-1 min-w-0 whitespace-nowrap overflow-hidden text-ellipsis">
                                “{{ \Illuminate\Support\Str::limit($item['evento']->extracto ?? '', 120) }}”
                            </span>
                            <span class="text-[11px] text-marca-mut flex-none tabular-nums">
                                {{ $item['evento']->recibido_at?->timezone('Europe/Madrid')->translatedFormat('d M') ?? '—' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-marca.tarjeta>

    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/panel-estado.js') }}" id="panel-estado-poll" data-url="{{ route('api.estado') }}"></script>
@endpush
