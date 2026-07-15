<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — SilgoDev Outreach</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <div>
                <h1 class="text-xl font-semibold">SilgoDev Outreach</h1>
                <p class="text-sm text-slate-500">CRM de leads y envío de correos</p>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('ok'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                {{ session('ok') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Overlay de carga al lanzar acciones --}}
    <div id="loading-overlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="mx-4 max-w-sm rounded-2xl bg-white p-8 text-center shadow-xl">
            <span class="mx-auto mb-4 block h-10 w-10 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"></span>
            <p id="loading-overlay-text" class="text-lg font-semibold text-slate-900">Procesando…</p>
            <p class="mt-2 text-sm text-slate-500">La tarea corre en segundo plano. No cierres la pestaña.</p>
        </div>
    </div>

    <script>
        (function () {
            // Spinner inmediato al enviar formularios de acción.
            document.querySelectorAll('.js-loading-form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    var overlay = document.getElementById('loading-overlay');
                    var label = form.getAttribute('data-loading-text') || 'Procesando…';
                    var text = document.getElementById('loading-overlay-text');
                    var btn = form.querySelector('.js-submit-btn');

                    if (text) {
                        text.textContent = label;
                    }

                    if (btn) {
                        btn.disabled = true;
                        var btnLabel = btn.querySelector('.js-btn-label');
                        if (btnLabel) {
                            btnLabel.textContent = label;
                        }
                    }

                    if (overlay) {
                        overlay.classList.remove('hidden');
                        overlay.classList.add('flex');
                    }
                });
            });

            var STATUS_LABELS = {
                pendiente: 'Pendiente',
                en_proceso: 'En proceso',
                hecho: 'Hecho',
                error: 'Error'
            };

            function applyHarvest(data) {
                var enabledBadge = document.getElementById('harvest-enabled-badge');
                if (enabledBadge) {
                    enabledBadge.textContent = data.enabled ? 'Activo' : 'Pausado';
                    enabledBadge.className = 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ' +
                        (data.enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900');
                }

                var hbBadge = document.getElementById('harvest-heartbeat-badge');
                var hbDot = document.getElementById('harvest-heartbeat-dot');
                var hbLabel = document.getElementById('harvest-heartbeat-label');
                if (hbBadge && hbLabel) {
                    var ok = !!data.heartbeat_ok;
                    hbBadge.className = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ' +
                        (ok ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800');
                    if (hbDot) {
                        hbDot.className = 'inline-block h-2 w-2 rounded-full ' + (ok ? 'bg-emerald-500' : 'bg-red-500');
                    }
                    if (data.heartbeat_age_seconds === null || data.heartbeat_age_seconds === undefined) {
                        hbLabel.textContent = 'Sin señal de vida';
                    } else {
                        hbLabel.textContent = 'Última señal hace ' + data.heartbeat_age_seconds + ' s';
                    }
                    hbBadge.title = data.heartbeat_at || 'Sin latido';
                }

                var areaEl = document.getElementById('harvest-area-proceso');
                if (areaEl) {
                    areaEl.textContent = (data.area_en_proceso && data.area_en_proceso.name) ? data.area_en_proceso.name : '—';
                }

                var frac = document.getElementById('harvest-areas-frac');
                if (frac) {
                    frac.textContent = data.areas_hechas + ' / ' + data.areas_total;
                }

                var leads = document.getElementById('harvest-leads-total');
                if (leads) {
                    leads.textContent = data.leads_total;
                }
                var emailsHoy = document.getElementById('harvest-emails-hoy');
                if (emailsHoy) {
                    emailsHoy.textContent = data.emails_hoy;
                }

                var progLabel = document.getElementById('harvest-progress-label');
                var progBar = document.getElementById('harvest-progress-bar');
                if (progLabel) {
                    progLabel.textContent = data.progress_percent + '%';
                }
                if (progBar) {
                    progBar.style.width = Math.min(100, data.progress_percent) + '%';
                }

                var tbody = document.getElementById('harvest-ultimas-body');
                if (tbody && Array.isArray(data.ultimas_areas)) {
                    if (data.ultimas_areas.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="3" class="py-3 text-slate-400">Todavía no hay áreas procesadas.</td></tr>';
                    } else {
                        tbody.innerHTML = data.ultimas_areas.map(function (row) {
                            var st = STATUS_LABELS[row.status] || row.status;
                            return '<tr>' +
                                '<td class="py-2 pr-3 font-medium text-slate-800">' + escapeHtml(row.name) + '</td>' +
                                '<td class="py-2 pr-3 text-slate-600">' + escapeHtml(st) + '</td>' +
                                '<td class="py-2">' + row.leads_found + '</td>' +
                                '</tr>';
                        }).join('');
                    }
                }
            }

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            // Polling cosecha siempre (cada 10s) vía /harvest/status o actions.status.
            var harvestPanel = document.getElementById('harvest-panel');
            if (harvestPanel) {
                var harvestUrl = harvestPanel.dataset.harvestUrl;
                setInterval(async function () {
                    try {
                        var response = await fetch(harvestUrl, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        var data = await response.json();
                        applyHarvest(data);
                    } catch (e) {
                        // Reintento en el siguiente tick.
                    }
                }, 10000);
            }

            // Polling jobs: si hay job en curso, refresca el panel al terminar.
            var banner = document.getElementById('job-banner');
            if (!banner) {
                return;
            }

            var searchRunning = banner.dataset.searchRunning === '1';
            var sendRunning = banner.dataset.sendRunning === '1';
            var initialLeads = parseInt(banner.dataset.leadsTotal || '0', 10);
            var statusUrl = banner.dataset.statusUrl;

            if (!searchRunning && !sendRunning) {
                return;
            }

            var ticks = 0;
            var maxTicks = 180; // ~15 minutos a 5s

            var timer = setInterval(async function () {
                ticks += 1;

                try {
                    var response = await fetch(statusUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    var data = await response.json();

                    if (data.harvest) {
                        applyHarvest(data.harvest);
                    }

                    var stillRunning = data.search_running || data.send_running;
                    var leadsGrew = data.leads_total > initialLeads;

                    if (!stillRunning || leadsGrew) {
                        clearInterval(timer);
                        window.location.reload();
                    }
                } catch (e) {
                    // Silenciar errores de red; se reintenta en el siguiente tick.
                }

                if (ticks >= maxTicks) {
                    clearInterval(timer);
                }
            }, 5000);
        })();
    </script>
</body>
</html>
