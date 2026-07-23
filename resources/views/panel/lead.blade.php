@extends('panel.layout')

@section('title', $lead->nombre)

@section('content')
    <div class="mb-6">
        <a href="{{ route('leads.indice') }}" class="text-sm text-slate-500 hover:underline">← Leads</a>
        <h1 class="text-2xl font-semibold tracking-tight mt-1">{{ $lead->nombre }}</h1>
        <p class="text-sm text-slate-600">{{ $lead->etiquetaEstado() }} · {{ $lead->website_dominio }}</p>
    </div>

    <div class="grid gap-6">
        {{-- 1. Datos --}}
        <section class="bg-white border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Datos</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div><dt class="text-slate-500">Nombre</dt><dd>{{ $lead->nombre }}</dd></div>
                <div><dt class="text-slate-500">Web</dt><dd>@if($lead->website)<a href="{{ $lead->website }}" class="underline" target="_blank" rel="noopener">{{ $lead->website }}</a>@else — @endif</dd></div>
                <div><dt class="text-slate-500">Teléfono</dt><dd>{{ $lead->telefono ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Dirección</dt><dd>{{ $lead->direccion ?? '—' }}{{ $lead->ciudad ? ', '.$lead->ciudad : '' }}</dd></div>
                <div><dt class="text-slate-500">Sector</dt><dd>{{ $lead->etiquetaSector() ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Clasificación</dt><dd>{{ $lead->clasificacion_metodo ?? '—' }} · confianza {{ $lead->clasificacion_confianza ?? '—' }}</dd></div>
            </dl>

            <form method="POST" action="{{ route('leads.estado', $lead) }}" class="mt-4 flex flex-wrap items-end gap-2 text-sm">
                @csrf
                <div>
                    <label class="block text-slate-500 mb-1">Cambiar estado</label>
                    <select name="estado" class="border border-slate-300 px-2 py-1.5">
                        @foreach (\App\Models\Lead::ESTADOS as $clave => $etiqueta)
                            <option value="{{ $clave }}" @selected($lead->estado === $clave)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="bg-slate-900 text-white px-3 py-1.5">Guardar</button>
            </form>
        </section>

        {{-- 2. Auditoría --}}
        <section class="bg-white border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Auditoría</h2>
            @if ($lead->auditoria)
                @php $aud = $lead->auditoria; @endphp
                <p class="text-sm mb-4">Puntuación: <span class="font-semibold tabular-nums">{{ $aud->puntuacion }}</span></p>

                @if ($aud->tienePsi())
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4 text-sm">
                        <div class="bg-slate-50 p-2"><div class="text-slate-500 text-xs">Rendimiento</div><div class="font-medium">{{ $aud->psi_rendimiento }}</div></div>
                        <div class="bg-slate-50 p-2"><div class="text-slate-500 text-xs">SEO</div><div class="font-medium">{{ $aud->psi_seo }}</div></div>
                        <div class="bg-slate-50 p-2"><div class="text-slate-500 text-xs">Accesibilidad</div><div class="font-medium">{{ $aud->psi_accesibilidad }}</div></div>
                        <div class="bg-slate-50 p-2"><div class="text-slate-500 text-xs">Buenas prácticas</div><div class="font-medium">{{ $aud->psi_buenas_practicas }}</div></div>
                        <div class="bg-slate-50 p-2"><div class="text-slate-500 text-xs">LCP</div><div class="font-medium">{{ $aud->segundosLcp() ?? '—' }} s</div></div>
                    </div>
                @endif

                <ul class="space-y-3 text-sm">
                    @forelse ($aud->hallazgosOrdenados() as $h)
                        <li class="border-t border-slate-100 pt-3">
                            <div class="flex justify-between gap-3">
                                <span class="font-medium">{{ $h['titulo'] ?? $h['codigo'] }}</span>
                                <span class="text-slate-500 tabular-nums">peso {{ $h['peso'] ?? 0 }}</span>
                            </div>
                            <p class="text-slate-600 mt-1">{{ $h['detalle'] ?? '' }}</p>
                        </li>
                    @empty
                        <li class="text-slate-500">Sin hallazgos.</li>
                    @endforelse
                </ul>
            @else
                <p class="text-sm text-slate-500">Sin auditoría.</p>
            @endif
        </section>

        {{-- 3. Páginas --}}
        <section class="bg-white border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Páginas capturadas</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-2 py-2 font-medium">Ruta</th>
                            <th class="px-2 py-2 font-medium">Status</th>
                            <th class="px-2 py-2 font-medium text-right">ms</th>
                            <th class="px-2 py-2 font-medium">Title</th>
                            <th class="px-2 py-2 font-medium">Viewport</th>
                            <th class="px-2 py-2 font-medium">JSON-LD</th>
                            <th class="px-2 py-2 font-medium">Más</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lead->paginas as $pagina)
                            <tr class="border-t border-slate-100 align-top">
                                <td class="px-2 py-2 font-mono text-xs">{{ $pagina->ruta ?: '/' }}</td>
                                <td class="px-2 py-2">{{ $pagina->http_status ?? '—' }}</td>
                                <td class="px-2 py-2 text-right tabular-nums">{{ $pagina->respuesta_ms ?? '—' }}</td>
                                <td class="px-2 py-2 max-w-[12rem] truncate">{{ $pagina->title ?? '—' }}</td>
                                <td class="px-2 py-2">{{ $pagina->tiene_viewport ? 'sí' : 'no' }}</td>
                                <td class="px-2 py-2">{{ $pagina->tiene_jsonld ? 'sí' : 'no' }}</td>
                                <td class="px-2 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-slate-600">metadatos</summary>
                                        <div class="mt-2 text-xs bg-slate-50 p-2 max-w-md space-y-1">
                                            <div>url: {{ $pagina->url }}</div>
                                            <div>content_type: {{ $pagina->content_type }}</div>
                                            <div>bytes: {{ $pagina->bytes }}</div>
                                            <div>meta: {{ $pagina->meta_description }}</div>
                                            <div>h1: {{ $pagina->h1_texto }} ({{ $pagina->h1_total }})</div>
                                            <div>idioma: {{ $pagina->idioma }}</div>
                                            <div>canonical: {{ $pagina->canonical }}</div>
                                            <div>generador: {{ $pagina->generador }}</div>
                                            <div>jsonld: {{ is_array($pagina->jsonld_tipos) ? implode(', ', $pagina->jsonld_tipos) : '' }}</div>
                                            <div>imágenes: {{ $pagina->imagenes_total }} / sin alt {{ $pagina->imagenes_sin_alt }}</div>
                                            <div>enlaces: int {{ $pagina->enlaces_internos }} / ext {{ $pagina->enlaces_externos }}</div>
                                            <div>formulario: {{ $pagina->tiene_formulario ? 'sí' : 'no' }} · whatsapp: {{ $pagina->tiene_whatsapp ? 'sí' : 'no' }}</div>
                                            <div>reservas: {{ $pagina->tiene_reservas ? 'sí' : 'no' }} · carrito: {{ $pagina->tiene_carrito ? 'sí' : 'no' }}</div>
                                            <div>aviso legal: {{ $pagina->tiene_aviso_legal ? 'sí' : 'no' }} · cookies: {{ $pagina->tiene_cookies ? 'sí' : 'no' }}</div>
                                            <div>https: {{ $pagina->es_https ? 'sí' : 'no' }} · cert: {{ $pagina->cert_valido ? 'ok' : 'no' }}</div>
                                            <div>error: {{ $pagina->error ?? '—' }}</div>
                                            <div>capturada: {{ optional($pagina->capturada_at)?->toDateTimeString() ?? '—' }}</div>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-2 py-4 text-slate-500">Sin páginas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 4. Correos y mensajes --}}
        <section class="bg-white border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Correos</h2>
            <ul class="text-sm space-y-2 mb-6">
                @forelse ($lead->emails as $email)
                    <li class="flex flex-wrap gap-x-4 gap-y-1 border-b border-slate-100 pb-2">
                        <span class="font-mono">{{ $email->email }}</span>
                        <span class="text-slate-500">{{ $email->tipo }}</span>
                        <span>{{ $email->es_principal ? 'principal' : '' }}</span>
                        <span class="text-slate-600">verificación: {{ $email->estado_verificacion ?? 'pendiente' }}</span>
                        <span class="text-slate-400">{{ optional($email->verificado_at)?->format('Y-m-d') }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">Sin emails.</li>
                @endforelse
            </ul>

            <h2 class="font-semibold mb-3">Mensajes</h2>
            <ul class="text-sm space-y-2 mb-6">
                @forelse ($lead->mensajes as $mensaje)
                    <li>
                        <a href="{{ route('mensajes.ver', $mensaje) }}" class="underline">
                            Paso {{ $mensaje->paso }} · {{ $mensaje->estado }} · {{ $mensaje->asunto }}
                        </a>
                        <span class="text-slate-500">{{ optional($mensaje->programado_para)?->format('Y-m-d H:i') }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">Sin mensajes.</li>
                @endforelse
            </ul>

            <h2 class="font-semibold mb-3">Eventos de bandeja</h2>
            <ul class="text-sm space-y-2">
                @forelse ($eventos as $evento)
                    <li class="border-b border-slate-100 pb-2">
                        <span class="font-medium">{{ $evento->tipo }}</span>
                        <span class="text-slate-500">{{ optional($evento->recibido_at)?->format('Y-m-d H:i') }}</span>
                        <p class="text-slate-600">{{ $evento->extracto }}</p>
                    </li>
                @empty
                    <li class="text-slate-500">Sin eventos.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
