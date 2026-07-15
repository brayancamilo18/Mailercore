@extends('layout')

@section('title', 'Dashboard')

@section('content')
    {{-- Indicador de jobs en curso --}}
    <div
        id="job-banner"
        class="mb-6 {{ ($jobStatus['search_running'] || $jobStatus['send_running']) ? '' : 'hidden' }} rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sky-900"
        data-search-running="{{ $jobStatus['search_running'] ? '1' : '0' }}"
        data-send-running="{{ $jobStatus['send_running'] ? '1' : '0' }}"
        data-leads-total="{{ $stats['total'] }}"
        data-status-url="{{ route('actions.status') }}"
    >
        <div class="flex items-center gap-3">
            <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-sky-600 border-t-transparent"></span>
            <div>
                <p class="font-medium" id="job-banner-title">
                    @if ($jobStatus['search_running'])
                        Recopilando datos…
                    @elseif ($jobStatus['send_running'])
                        Enviando correos…
                    @endif
                </p>
                <p class="text-sm text-sky-800" id="job-banner-detail">
                    Overpass + scraping de webs puede tardar varios minutos. Esta página se refresca sola cuando termine.
                </p>
            </div>
        </div>
    </div>

    {{-- Cosecha: vivacidad y avance España --}}
    <section
        id="harvest-panel"
        class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
        data-harvest-url="{{ route('harvest.status') }}"
        data-status-url="{{ route('actions.status') }}"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Cosecha</h2>
                <p class="mt-0.5 text-sm text-slate-500">Recorrido Overpass por provincias · auto-refresh 10s</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span
                    id="harvest-enabled-badge"
                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $harvest['enabled'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}"
                >
                    {{ $harvest['enabled'] ? 'Activo' : 'Pausado' }}
                </span>
                <span
                    id="harvest-heartbeat-badge"
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $harvest['heartbeat_ok'] ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}"
                    title="{{ $harvest['heartbeat_at'] ?? 'Sin latido' }}"
                >
                    <span id="harvest-heartbeat-dot" class="inline-block h-2 w-2 rounded-full {{ $harvest['heartbeat_ok'] ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                    <span id="harvest-heartbeat-label">
                        @if ($harvest['heartbeat_age_seconds'] === null)
                            Sin señal de vida
                        @else
                            Última señal hace {{ $harvest['heartbeat_age_seconds'] }} s
                        @endif
                    </span>
                </span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Área en proceso</p>
                <p id="harvest-area-proceso" class="mt-1 truncate text-sm font-semibold text-slate-900">
                    {{ $harvest['area_en_proceso']['name'] ?? '—' }}
                </p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Áreas hechas</p>
                <p id="harvest-areas-frac" class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $harvest['areas_hechas'] }} / {{ $harvest['areas_total'] }}
                </p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Leads</p>
                <p id="harvest-leads-total" class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $harvest['leads_total'] }}
                </p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Leads hoy</p>
                <p id="harvest-emails-hoy" class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $harvest['emails_hoy'] }}
                </p>
            </div>
        </div>

        <div class="mt-4">
            <div class="mb-1 flex justify-between text-xs text-slate-600">
                <span>Avance España (áreas hechas)</span>
                <span id="harvest-progress-label">{{ $harvest['progress_percent'] }}%</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                <div
                    id="harvest-progress-bar"
                    class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                    style="width: {{ min(100, $harvest['progress_percent']) }}%"
                ></div>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs sm:text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-2 pr-3 font-medium">Últimas áreas</th>
                        <th class="py-2 pr-3 font-medium">Estado</th>
                        <th class="py-2 font-medium">Leads</th>
                    </tr>
                </thead>
                <tbody id="harvest-ultimas-body" class="divide-y divide-slate-50">
                    @forelse ($harvest['ultimas_areas'] as $areaRow)
                        <tr>
                            <td class="py-2 pr-3 font-medium text-slate-800">{{ $areaRow['name'] }}</td>
                            <td class="py-2 pr-3 text-slate-600">{{ \App\Models\HarvestArea::STATUSES[$areaRow['status']] ?? $areaRow['status'] }}</td>
                            <td class="py-2">{{ $areaRow['leads_found'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-3 text-slate-400">Todavía no hay áreas procesadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Estadísticas --}}
    <section class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7">
        @foreach ([
            ['label' => 'Total', 'value' => $stats['total'], 'color' => 'text-slate-900'],
            ['label' => 'Con email', 'value' => $stats['con_email'], 'color' => 'text-blue-700'],
            ['label' => 'Nuevos', 'value' => $stats['nuevo'], 'color' => 'text-blue-600'],
            ['label' => 'Contactados', 'value' => $stats['contactado'], 'color' => 'text-amber-600'],
            ['label' => 'Hoy', 'value' => $stats['contactado_hoy'], 'color' => 'text-amber-700'],
            ['label' => 'Respondidos', 'value' => $stats['respondido'], 'color' => 'text-green-600'],
            ['label' => 'Clientes', 'value' => $stats['cliente'], 'color' => 'text-emerald-700'],
        ] as $stat)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-bold {{ $stat['color'] }}">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </section>

    {{-- Acciones --}}
    <section class="mb-8 grid gap-4 lg:grid-cols-3">
        {{-- Buscar agencias --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Buscar agencias</h2>
            <p class="mt-1 text-sm text-slate-500">Consulta Overpass y captura leads nuevos en segundo plano.</p>
            <form method="POST" action="{{ route('actions.search') }}" class="mt-4 js-loading-form" data-loading-text="Recopilando datos…">
                @csrf
                <button
                    type="submit"
                    class="js-submit-btn w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @disabled($jobStatus['search_running'])
                >
                    <span class="js-btn-label">{{ $jobStatus['search_running'] ? 'Búsqueda en curso…' : 'Lanzar búsqueda' }}</span>
                </button>
            </form>
        </div>

        {{-- Enviar correos --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Enviar correos</h2>
            <p class="mt-1 text-sm text-slate-500">Encola el envío respetando warm-up y cupo diario.</p>
            <form method="POST" action="{{ route('actions.send') }}" class="mt-4 space-y-3 js-loading-form" data-loading-text="Enviando correos…">
                @csrf
                <div>
                    <label for="limit" class="block text-xs font-medium text-slate-600">Límite opcional</label>
                    <input
                        type="number"
                        name="limit"
                        id="limit"
                        min="1"
                        placeholder="Automático"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        @disabled($jobStatus['send_running'])
                    >
                </div>
                <button
                    type="submit"
                    class="js-submit-btn w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @disabled($jobStatus['send_running'])
                >
                    <span class="js-btn-label">{{ $jobStatus['send_running'] ? 'Envío en curso…' : 'Lanzar envío' }}</span>
                </button>
            </form>
        </div>

        {{-- Alta manual --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Alta manual</h2>
            <p class="mt-1 text-sm text-slate-500">Añade un lead que no venga de OpenStreetMap.</p>
            <form method="POST" action="{{ route('leads.store') }}" class="mt-4 space-y-3">
                @csrf
                <input
                    type="text"
                    name="name"
                    required
                    placeholder="Nombre de la agencia *"
                    value="{{ old('name') }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                <input
                    type="email"
                    name="email"
                    required
                    placeholder="Email *"
                    value="{{ old('email') }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                <input
                    type="text"
                    name="website"
                    placeholder="Web"
                    value="{{ old('website') }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                <input
                    type="text"
                    name="phone"
                    placeholder="Teléfono"
                    value="{{ old('phone') }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                <textarea
                    name="notes"
                    rows="2"
                    placeholder="Notas"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >{{ old('notes') }}</textarea>
                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Crear lead
                </button>
            </form>
        </div>
    </section>

    {{-- Filtros por estado y segmento --}}
    <section class="mb-6 space-y-3">
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('dashboard', array_filter(['segmento' => request('segmento')])) }}"
                class="rounded-full px-3 py-1 text-sm font-medium {{ ! request('status') ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100' }}"
            >
                Todos
            </a>
            @foreach (\App\Models\Lead::ESTADOS as $key => $label)
                <a
                    href="{{ route('dashboard', array_filter(['status' => $key, 'segmento' => request('segmento')])) }}"
                    class="rounded-full px-3 py-1 text-sm font-medium {{ request('status') === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Segmento</span>
            <a
                href="{{ route('dashboard', array_filter(['status' => request('status')])) }}"
                class="rounded-full px-3 py-1 text-sm font-medium {{ ! request('segmento') ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100' }}"
            >
                Todos
            </a>
            @foreach (\App\Models\Lead::SEGMENTOS as $key => $label)
                <a
                    href="{{ route('dashboard', array_filter(['status' => request('status'), 'segmento' => $key])) }}"
                    class="rounded-full px-3 py-1 text-sm font-medium {{ request('segmento') === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </section>

    {{-- Tabla de leads --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nombre</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Segmento</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Email</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Teléfono</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Estado</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Contactado</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Cambiar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($leads as $lead)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $lead->name }}</div>
                                @if ($lead->website)
                                    <a href="{{ $lead->website }}" target="_blank" rel="noopener noreferrer" class="text-xs text-blue-600 hover:underline">
                                        {{ $lead->website }}
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">Sin web</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $lead->segmento === 'negocio' ? 'bg-violet-100 text-violet-800' : 'bg-sky-100 text-sky-800' }}">
                                    {{ \App\Models\Lead::SEGMENTOS[$lead->segmento] ?? $lead->segmento }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($lead->email)
                                    <a href="mailto:{{ $lead->email }}" class="text-blue-600 hover:underline">{{ $lead->email }}</a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $lead->phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $lead->statusColor() }}">
                                    {{ \App\Models\Lead::ESTADOS[$lead->status] ?? $lead->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $lead->contacted_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('leads.status', $lead) }}">
                                    @csrf
                                    <select
                                        name="status"
                                        onchange="this.form.submit()"
                                        class="rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    >
                                        @foreach (\App\Models\Lead::ESTADOS as $key => $label)
                                            <option value="{{ $key }}" @selected($lead->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                                No hay leads todavía. Lanza una búsqueda o crea uno manualmente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($leads->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $leads->links() }}
            </div>
        @endif
    </section>
@endsection
