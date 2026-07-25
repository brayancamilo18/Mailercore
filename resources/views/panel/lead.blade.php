@extends('panel.layout')

@section('title', $lead->nombre)

@section('content')
    @php
        $aud = $lead->auditoria;
        $puntuacion = $aud?->puntuacion;
        $colorPuntos = match (true) {
            $puntuacion === null => '#8B968F',
            (int) $puntuacion < 50 => '#B0432F',
            (int) $puntuacion < 80 => '#96660F',
            default => '#2C7A3F',
        };

        $timeline = collect();
        foreach ($lead->mensajes as $mensaje) {
            $timeline->push([
                'clase' => 'mensaje',
                'fecha' => $mensaje->enviado_at ?? $mensaje->programado_para,
                'mensaje' => $mensaje,
            ]);
        }
        foreach ($eventos as $evento) {
            $timeline->push([
                'clase' => 'evento',
                'fecha' => $evento->recibido_at,
                'evento' => $evento,
            ]);
        }
        $timeline = $timeline
            ->sortByDesc(fn (array $item) => $item['fecha']?->getTimestamp() ?? 0)
            ->values();

        $verifMapa = [
            'valido' => ['Válido', '#2C7A3F', '#E4F3E7'],
            'riesgo' => ['Riesgo', '#96660F', '#FAF0DC'],
            'invalido' => ['Inválido', '#B0432F', '#F9E9E6'],
        ];
    @endphp

    <div class="max-w-[1240px]">
        <a href="{{ route('leads.indice') }}"
           class="inline-block text-[12.5px] font-bold text-savia hover:text-bosque mb-3.5">
            ‹ Volver a leads
        </a>

        {{-- Cabecera --}}
        <x-marca.tarjeta class="mb-4">
            <div class="flex flex-wrap gap-6 items-start justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-[22px] font-extrabold text-bosque m-0 leading-tight">{{ $lead->nombre }}</h1>
                        <x-marca.estado-lead :estado="$lead->estado" />
                    </div>
                    <div class="mt-1.5">
                        @if ($lead->website)
                            <a href="{{ $lead->website }}" target="_blank" rel="noopener"
                               class="text-[13px] font-semibold text-savia hover:text-bosque">
                                {{ $lead->website_dominio ?? $lead->website }}
                            </a>
                        @else
                            <span class="text-[13px] text-marca-mut">{{ $lead->website_dominio ?? 'Sin web' }}</span>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('leads.estado', $lead) }}"
                      class="flex flex-col gap-1.5 items-stretch sm:items-end flex-none">
                    @csrf
                    <label for="lead-estado" class="text-[10.5px] font-extrabold tracking-[0.06em] uppercase text-marca-mut">
                        Estado del lead
                    </label>
                    <div class="flex flex-wrap gap-2 items-center">
                        <select id="lead-estado" name="estado"
                                class="text-[12.5px] font-bold text-marca-txt border border-[#D6DDD8] rounded-lg px-2.5 py-1.5 bg-hueso focus:outline-none focus:ring-2 focus:ring-brote/40 focus:border-savia">
                            @foreach (\App\Models\Lead::ESTADOS as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected($lead->estado === $clave)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        <button type="submit"
                                class="bg-savia hover:bg-bosque text-hueso text-xs font-extrabold rounded-lg px-3.5 py-2 transition-colors">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </x-marca.tarjeta>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            {{-- A) Datos de la empresa --}}
            <x-marca.tarjeta titulo="Datos de la empresa" class="lg:col-span-12">
                <dl class="grid grid-cols-[auto_1fr] sm:grid-cols-[auto_1fr_auto_1fr] gap-x-4 gap-y-2 text-[12.5px]">
                    <dt class="font-semibold text-marca-mut">Teléfono</dt>
                    <dd class="m-0 tabular-nums text-marca-txt">{{ $lead->telefono ?? '—' }}</dd>

                    <dt class="font-semibold text-marca-mut">Dirección</dt>
                    <dd class="m-0 text-marca-txt">{{ $lead->direccion ?? '—' }}</dd>

                    <dt class="font-semibold text-marca-mut">Ciudad</dt>
                    <dd class="m-0 text-marca-txt">{{ $lead->ciudad ?? '—' }}</dd>

                    <dt class="font-semibold text-marca-mut">Provincia</dt>
                    <dd class="m-0 text-marca-txt">{{ $lead->provincia ?? '—' }}</dd>

                    <dt class="font-semibold text-marca-mut">Web</dt>
                    <dd class="m-0 sm:col-span-1">
                        @if ($lead->website)
                            <a href="{{ $lead->website }}" target="_blank" rel="noopener" class="font-semibold">{{ $lead->website }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </dl>

                <div class="mt-4 pt-3 border-t border-[#F1F4F1] flex flex-wrap items-center gap-x-3 gap-y-2">
                    @if ($lead->sector)
                        <x-marca.sector :sector="$lead->sector" />
                    @else
                        <span class="text-sm text-marca-mut">Sin sector</span>
                    @endif
                    @if ($lead->subsector)
                        <span class="text-[12.5px] text-marca-sec">{{ $lead->subsector }}</span>
                    @endif
                    <span class="text-xs text-marca-mut">
                        clasificado por {{ $lead->clasificacion_metodo ?? '—' }}
                        ({{ $lead->clasificacion_confianza !== null ? $lead->clasificacion_confianza.'%' : '—' }} de confianza)
                    </span>
                </div>
            </x-marca.tarjeta>

            {{-- B) Auditoría --}}
            <x-marca.tarjeta titulo="Auditoría" class="lg:col-span-7">
                @if ($aud)
                    <div class="flex flex-wrap gap-6 items-center mb-5">
                        <div
                            class="flex-none w-[86px] h-[86px] rounded-full border-4 flex flex-col items-center justify-center tabular-nums"
                            @style(["border-color:{$colorPuntos}", "color:{$colorPuntos}"])
                        >
                            <span class="text-[26px] font-extrabold leading-none">{{ (int) $aud->puntuacion }}</span>
                            <span class="text-[9px] font-bold tracking-[0.06em] uppercase mt-0.5">de 100</span>
                        </div>

                        @if ($aud->tienePsi())
                            <div class="flex-1 min-w-[220px] grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-2.5">
                                <x-marca.medidor :valor="(int) $aud->psi_rendimiento" etiqueta="Rendimiento" />
                                <x-marca.medidor :valor="(int) $aud->psi_seo" etiqueta="SEO" />
                                <x-marca.medidor :valor="(int) $aud->psi_accesibilidad" etiqueta="Accesibilidad" />
                                <x-marca.medidor :valor="(int) $aud->psi_buenas_practicas" etiqueta="Buenas prácticas" />
                            </div>
                            <div class="flex-none text-center">
                                <div class="text-xl font-extrabold text-bosque tabular-nums">
                                    {{ $aud->segundosLcp() ?? '—' }} s
                                </div>
                                <div class="text-[10.5px] font-semibold text-marca-mut">Carga</div>
                            </div>
                        @endif
                    </div>

                    @php $hallazgos = $aud->hallazgosOrdenados(); @endphp
                    <div class="text-[10.5px] font-extrabold tracking-[0.06em] uppercase text-marca-mut mb-2">
                        Hallazgos ({{ count($hallazgos) }})
                    </div>
                    <div class="flex flex-col">
                        @forelse ($hallazgos as $h)
                            @php
                                $peso = (int) ($h['peso'] ?? 0);
                                [$sevEtiqueta, $sevTexto, $sevFondo] = match (true) {
                                    $peso >= 25 => ['Alta', '#B0432F', '#F9E9E6'],
                                    $peso >= 12 => ['Media', '#96660F', '#FAF0DC'],
                                    default => ['Baja', '#5F6B66', '#EEF1EF'],
                                };
                            @endphp
                            <div class="flex gap-3 py-2.5 border-b border-[#F1F4F1] items-baseline">
                                <span
                                    class="flex-none inline-flex items-center px-[9px] py-0.5 rounded-full text-[11px] font-bold"
                                    @style(["color:{$sevTexto}", "background:{$sevFondo}"])
                                >{{ $sevEtiqueta }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[13px] font-bold text-marca-txt">{{ $h['titulo'] ?? $h['codigo'] ?? 'Hallazgo' }}</div>
                                    @if (! empty($h['detalle']))
                                        <div class="text-xs text-marca-sec mt-0.5">{{ $h['detalle'] }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-marca-mut m-0">Sin hallazgos.</p>
                        @endforelse
                    </div>
                @else
                    <p class="text-sm text-marca-mut m-0">Sin auditar todavía.</p>
                @endif
            </x-marca.tarjeta>

            <div class="lg:col-span-5 flex flex-col gap-4 min-w-0">

                {{-- C) Páginas capturadas --}}
                <x-marca.tarjeta>
                    @if ($lead->paginas->isEmpty())
                        <h2 class="text-[11px] font-extrabold tracking-[0.08em] uppercase text-marca-mut m-0 mb-2">Páginas capturadas</h2>
                        <p class="text-sm text-marca-mut m-0">Sin páginas capturadas.</p>
                    @else
                        <details open>
                            <summary class="text-[11px] font-extrabold tracking-[0.08em] uppercase text-marca-mut cursor-pointer list-none flex justify-between items-center">
                                <span>Páginas capturadas ({{ $lead->paginas->count() }})</span>
                                <span class="text-savia text-[11px] font-bold normal-case tracking-normal">mostrar ▾</span>
                            </summary>
                            <div class="overflow-x-auto mt-3">
                                <table class="w-full min-w-[480px] border-collapse">
                                    <thead>
                                        <tr>
                                            <th class="text-left text-[10px] font-extrabold tracking-[0.05em] uppercase text-marca-mut px-1.5 py-1.5 border-b border-marca-bd">Ruta</th>
                                            <th class="text-right text-[10px] font-extrabold tracking-[0.05em] uppercase text-marca-mut px-1.5 py-1.5 border-b border-marca-bd">HTTP</th>
                                            <th class="text-right text-[10px] font-extrabold tracking-[0.05em] uppercase text-marca-mut px-1.5 py-1.5 border-b border-marca-bd">ms</th>
                                            <th class="text-left text-[10px] font-extrabold tracking-[0.05em] uppercase text-marca-mut px-1.5 py-1.5 border-b border-marca-bd">Title</th>
                                            <th class="text-center text-[10px] font-extrabold tracking-[0.05em] uppercase text-marca-mut px-1.5 py-1.5 border-b border-marca-bd">Móvil</th>
                                            <th class="text-center text-[10px] font-extrabold tracking-[0.05em] uppercase text-marca-mut px-1.5 py-1.5 border-b border-marca-bd">Datos estr.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($lead->paginas as $pagina)
                                            @php
                                                $okHttp = (int) $pagina->http_status === 200;
                                                $colorHttp = $okHttp ? '#2C7A3F' : '#B0432F';
                                                $fondoHttp = $okHttp ? '#E4F3E7' : '#F9E9E6';
                                                $colorViewport = $pagina->tiene_viewport ? '#2C7A3F' : '#D0D6D2';
                                                $colorJsonld = $pagina->tiene_jsonld ? '#2C7A3F' : '#D0D6D2';
                                            @endphp
                                            <tr>
                                                <td class="px-1.5 py-1.5 border-b border-[#F1F4F1] text-xs font-mono text-marca-txt whitespace-nowrap" title="{{ $pagina->title }}">
                                                    {{ $pagina->ruta ?: '/' }}
                                                </td>
                                                <td class="px-1.5 py-1.5 border-b border-[#F1F4F1] text-right">
                                                    @if ($pagina->http_status !== null)
                                                        <span
                                                            class="inline-flex text-[11px] font-bold px-1.5 py-0.5 rounded"
                                                            @style(["color:{$colorHttp}", "background:{$fondoHttp}"])
                                                        >{{ $pagina->http_status }}</span>
                                                    @else
                                                        <span class="text-xs text-marca-mut">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-1.5 py-1.5 border-b border-[#F1F4F1] text-right text-xs tabular-nums text-marca-sec">
                                                    {{ $pagina->respuesta_ms ?? '—' }}
                                                </td>
                                                <td class="px-1.5 py-1.5 border-b border-[#F1F4F1] text-xs text-marca-sec max-w-[140px] truncate">
                                                    {{ \Illuminate\Support\Str::limit($pagina->title ?? '—', 40) }}
                                                </td>
                                                <td class="px-1.5 py-1.5 border-b border-[#F1F4F1] text-center">
                                                    <span
                                                        class="inline-block w-2 h-2 rounded-full"
                                                        @style(["background:{$colorViewport}"])
                                                        title="{{ $pagina->tiene_viewport ? 'Con viewport' : 'Sin viewport' }}"
                                                    ></span>
                                                </td>
                                                <td class="px-1.5 py-1.5 border-b border-[#F1F4F1] text-center">
                                                    <span
                                                        class="inline-block w-2 h-2 rounded-full"
                                                        @style(["background:{$colorJsonld}"])
                                                        title="{{ $pagina->tiene_jsonld ? 'Con JSON-LD' : 'Sin JSON-LD' }}"
                                                    ></span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endif
                </x-marca.tarjeta>

                {{-- D) Correos y mensajes --}}
                <x-marca.tarjeta titulo="Correos y mensajes">
                    <div class="flex flex-col gap-1.5 mb-4">
                        @forelse ($lead->emails as $email)
                            @php
                                $verif = $email->estado_verificacion;
                                [$verifEtiqueta, $verifTexto, $verifFondo] = $verifMapa[$verif]
                                    ?? ['Sin verificar', '#5F6B66', '#EEF1EF'];
                            @endphp
                            <div class="flex flex-wrap items-center gap-2 py-1">
                                <span class="text-[12.5px] font-mono text-marca-txt">{{ $email->email }}</span>
                                @if ($email->es_principal)
                                    <span class="text-[10px] font-extrabold uppercase tracking-wide text-savia bg-[#EAF4F0] rounded-full px-1.5 py-0.5">★ principal</span>
                                @endif
                                @if ($email->tipo)
                                    <span class="text-[11px] font-semibold text-marca-mut">{{ $email->tipo }}</span>
                                @endif
                                <span
                                    class="inline-flex items-center px-[9px] py-0.5 rounded-full text-[11px] font-bold"
                                    @style(["color:{$verifTexto}", "background:{$verifFondo}"])
                                >{{ $verifEtiqueta }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-marca-mut m-0">Sin emails.</p>
                        @endforelse
                    </div>

                    <div class="text-[10.5px] font-extrabold tracking-[0.06em] uppercase text-marca-mut mb-2.5">Historial</div>

                    @if ($timeline->isEmpty())
                        <p class="text-sm text-marca-mut m-0">Sin mensajes ni eventos.</p>
                    @else
                        <div class="flex flex-col">
                            @foreach ($timeline as $item)
                                @if ($item['clase'] === 'mensaje')
                                    @php $mensaje = $item['mensaje']; @endphp
                                    <div class="flex gap-3">
                                        <div class="flex flex-col items-center">
                                            <span class="w-2.5 h-2.5 rounded-full bg-savia flex-none mt-1"></span>
                                            @if (! $loop->last)
                                                <span class="w-px flex-1 bg-[#E4E8E4] min-h-[14px]"></span>
                                            @endif
                                        </div>
                                        <div class="pb-3.5 flex-1 min-w-0">
                                            <div class="text-[12.5px] font-bold text-marca-txt">
                                                <a href="{{ route('mensajes.ver', $mensaje) }}" class="hover:text-bosque">
                                                    {{ $mensaje->plantilla }} · paso {{ $mensaje->paso }}
                                                </a>
                                                <span class="font-normal text-marca-mut text-[11.5px]">
                                                    · {{ $item['fecha']?->timezone('Europe/Madrid')->format('d M Y H:i') ?? '—' }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-marca-sec mt-0.5 flex flex-wrap items-center gap-2">
                                                <span class="truncate">{{ $mensaje->asunto }}</span>
                                                <x-marca.estado-correo :estado="$mensaje->estado" />
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    @php $evento = $item['evento']; @endphp
                                    <div class="flex gap-3">
                                        <div class="flex flex-col items-center">
                                            <span class="w-2.5 h-2.5 rounded-full bg-info flex-none mt-1"></span>
                                            @if (! $loop->last)
                                                <span class="w-px flex-1 bg-[#E4E8E4] min-h-[14px]"></span>
                                            @endif
                                        </div>
                                        <div class="pb-3.5 flex-1 min-w-0 pl-1 border-l-2 border-info-bg -ml-px">
                                            <div class="text-[12.5px] font-bold text-info">
                                                {{ ucfirst($evento->tipo) }}
                                                <span class="font-normal text-marca-mut text-[11.5px]">
                                                    · {{ $item['fecha']?->timezone('Europe/Madrid')->format('d M Y H:i') ?? '—' }}
                                                </span>
                                            </div>
                                            @if ($evento->extracto)
                                                <div class="text-xs text-marca-sec mt-0.5">“{{ \Illuminate\Support\Str::limit($evento->extracto, 160) }}”</div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </x-marca.tarjeta>

            </div>
        </div>
    </div>
@endsection
