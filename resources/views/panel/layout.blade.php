<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — ONEZ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root{
            --bosque:#0B1F1A; --savia:#0F6E56; --brote:#5DCAA5; --hueso:#FAFAF7;
            --txt:#22312B; --sec:#5F6B66; --mut:#8B968F; --bd:#E4E8E4;
            --ok:#2C7A3F; --ok-bg:#E4F3E7;
            --amb:#96660F; --amb-bg:#FAF0DC;
            --roj:#B0432F; --roj-bg:#F9E9E6;
        }
        *{box-sizing:border-box}
        body{margin:0;background:var(--hueso);color:var(--txt);font-family:'Nunito Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
        a{color:var(--savia);text-decoration:none} a:hover{color:var(--bosque)}
        button{font-family:inherit}
        ::selection{background:var(--brote);color:var(--bosque)}

        /* Utilidades de marca (no dependen del CDN de Tailwind) */
        .bg-bosque{background-color:var(--bosque)!important}
        .bg-savia{background-color:var(--savia)!important}
        .bg-brote{background-color:var(--brote)!important}
        .bg-hueso{background-color:var(--hueso)!important}
        .bg-ok{background-color:var(--ok)!important}
        .bg-ok-bg{background-color:var(--ok-bg)!important}
        .bg-amb-bg{background-color:var(--amb-bg)!important}
        .bg-roj-bg{background-color:var(--roj-bg)!important}
        .hover\:bg-bosque:hover{background-color:var(--bosque)!important}
        .hover\:bg-savia:hover{background-color:var(--savia)!important}
        .hover\:bg-roj-bg:hover{background-color:var(--roj-bg)!important}
        .text-bosque{color:var(--bosque)!important}
        .text-savia{color:var(--savia)!important}
        .text-brote{color:var(--brote)!important}
        .text-hueso{color:var(--hueso)!important}
        .text-marca-txt{color:var(--txt)!important}
        .text-marca-sec{color:var(--sec)!important}
        .text-marca-mut{color:var(--mut)!important}
        .text-ok{color:var(--ok)!important}
        .text-amb{color:var(--amb)!important}
        .text-roj{color:var(--roj)!important}
        .border-marca-bd{border-color:var(--bd)!important}
        .hover\:text-bosque:hover{color:var(--bosque)!important}

        .onez-aside{background:var(--bosque);color:#FAFAF7}
        .onez-nav-a{display:block;width:100%;text-align:left;font-size:13px;font-weight:600;border-radius:8px;padding:8px 12px;transition:background .15s,color .15s;color:#9FB4AC;text-decoration:none}
        .onez-nav-a:hover{background:rgba(250,250,247,.07);color:#FAFAF7}
        .onez-nav-a.is-active{background:var(--savia);color:#FAFAF7}
        .onez-nav-a.is-active:hover{background:var(--savia);color:#FAFAF7}

        .nav-hamburguesa,.nav-cerrar{
            display:inline-flex;align-items:center;justify-content:center;
            width:40px;height:40px;border-radius:8px;border:0;cursor:pointer;padding:0;flex:none;
        }
        .nav-hamburguesa{background:transparent;color:var(--bosque)}
        .nav-hamburguesa:hover{background:rgba(11,31,26,.06)}
        .nav-cerrar{background:rgba(250,250,247,.08);color:#FAFAF7}
        .nav-cerrar:hover{background:rgba(250,250,247,.14)}
        .nav-backdrop{
            display:none;position:fixed;inset:0;z-index:40;
            background:rgba(11,31,26,.45);opacity:0;pointer-events:none;
            transition:opacity .22s ease;
        }

        @media (max-width:767.98px){
            .nav-hamburguesa{display:inline-flex}
            .onez-aside{
                position:fixed;top:0;left:0;bottom:0;z-index:50;
                width:min(280px,86vw);max-width:280px;
                flex-direction:column;align-items:stretch;flex-wrap:nowrap;
                padding:18px 12px 14px;
                transform:translateX(-105%);
                transition:transform .28s cubic-bezier(.22,.8,.28,1);
                box-shadow:none;
            }
            .onez-aside .nav-marca{padding:0 10px 18px;display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
            .onez-aside .nav-marca .nav-subtitulo{display:block;font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#4E6A61;margin-top:3px}
            .onez-aside nav{flex-direction:column;flex-wrap:nowrap;gap:3px;flex:none}
            .onez-aside .nav-spacer{display:block;flex:1}
            .onez-aside .nav-pie{flex-direction:column;width:100%;border-top:1px solid rgba(255,255,255,.09);padding-top:12px}
            html.nav-abierta .onez-aside{
                transform:translateX(0);
                box-shadow:12px 0 40px rgba(11,31,26,.28);
            }
            html.nav-abierta .nav-backdrop{display:block;opacity:1;pointer-events:auto}
            html.nav-abierta{overflow:hidden}
        }
        @media (min-width:768px){
            .nav-hamburguesa,.nav-cerrar,.nav-backdrop{display:none!important}
            html.nav-abierta{overflow:auto}
        }

        .btn-savia{
            border:none;background:var(--savia);color:#FAFAF7;font-size:12.5px;font-weight:800;
            border-radius:8px;padding:8px 16px;cursor:pointer;font-family:inherit;line-height:1.2;
        }
        .btn-savia:hover{background:var(--bosque);color:#FAFAF7}
        .btn-pausar{
            border:1px solid #E7D5D1;background:#FBF4F2;color:var(--roj);font-size:12.5px;font-weight:800;
            border-radius:8px;padding:8px 16px;cursor:pointer;font-family:inherit;line-height:1.2;
        }
        .btn-pausar:hover{background:var(--roj-bg)}
        .pill-campana{
            display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;
            border-radius:999px;padding:5px 12px;white-space:nowrap;
        }
        .pill-campana .dot{width:8px;height:8px;border-radius:50%;flex:none}

        /* Vista previa de correo en el panel (HTML real, no iframe) */
        .email-preview p{margin:0 0 .85em}
        .email-preview p:last-child{margin-bottom:0}
        .email-preview hr{border:0;border-top:1px solid #E4E8E4;margin:1.1em 0}
        .email-preview small{font-size:11.5px;color:#5F6B66;line-height:1.45}
    </style>
</head>
<body class="bg-hueso text-marca-txt font-sans antialiased">
@auth
    @php
        $rampaCabecera = app(\App\Services\Panel\DatosPanel::class)->rampaHoy();
        $pausadoCabecera = (bool) $rampaCabecera['pausado'];
        $saludCabecera = $rampaCabecera['dia']->salud ?? 'verde';
        if ($pausadoCabecera) {
            $campanaLabel = 'Pausada';
            $campanaTexto = '#96660F';
            $campanaFondo = '#FAF0DC';
        } elseif (in_array($saludCabecera, ['parado', 'detenido'], true)) {
            $campanaLabel = 'Detenida';
            $campanaTexto = '#B0432F';
            $campanaFondo = '#F9E9E6';
        } else {
            $campanaLabel = 'Activa';
            $campanaTexto = '#2C7A3F';
            $campanaFondo = '#E4F3E7';
        }
    @endphp

    <div class="flex flex-col md:flex-row h-screen overflow-hidden">
        <div id="nav-backdrop" class="nav-backdrop" aria-hidden="true"></div>

        <aside id="panel-nav" class="onez-aside w-[212px] flex-none flex flex-col items-stretch gap-0 px-3 py-[18px] pb-3.5">
            <div class="nav-marca px-2.5 pb-5 pt-1">
                <div>
                    <div class="flex items-baseline gap-0.5">
                        <span style="font-size:21px;font-weight:800;letter-spacing:.14em;color:#FAFAF7;line-height:1">ONEZ</span>
                        <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#5DCAA5"></span>
                    </div>
                    <div class="nav-subtitulo hidden md:block" style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#4E6A61;margin-top:3px">
                        Panel de captación
                    </div>
                </div>
                <button type="button" id="nav-cerrar" class="nav-cerrar md:hidden" aria-label="Cerrar menú">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>

            <nav class="flex flex-col gap-[3px] flex-none min-w-0">
                <a href="{{ route('panel.resumen') }}"
                   class="onez-nav-a {{ request()->routeIs('panel.resumen') ? 'is-active' : '' }}">Resumen</a>
                <a href="{{ route('leads.indice') }}"
                   class="onez-nav-a {{ request()->routeIs('leads.*') ? 'is-active' : '' }}">Leads</a>
                <a href="{{ route('cola.indice') }}"
                   class="onez-nav-a {{ request()->routeIs('cola.*', 'mensajes.*') ? 'is-active' : '' }}">Cola de hoy</a>
                <a href="{{ route('salud.indice') }}"
                   class="onez-nav-a {{ request()->routeIs('salud.*', 'envio.*') ? 'is-active' : '' }}">Salud</a>
                <a href="{{ route('cosecha.indice') }}"
                   class="onez-nav-a {{ request()->routeIs('cosecha.*') ? 'is-active' : '' }}">Cosecha</a>
            </nav>

            <div class="nav-spacer hidden md:block flex-1"></div>

            <div class="nav-pie flex flex-col gap-[3px] md:border-t md:border-white/[0.09] md:pt-3 w-full mt-auto md:mt-0">
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="onez-nav-a" style="color:#7E948C;width:100%;cursor:pointer;background:transparent;border:0">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 min-h-0">
            <header class="flex-none flex items-center flex-wrap gap-2 md:gap-3.5 px-3 py-2.5 md:px-7 md:min-h-[58px] border-b border-marca-bd bg-hueso">
                <button type="button" id="nav-abrir" class="nav-hamburguesa md:hidden" aria-label="Abrir menú" aria-expanded="false" aria-controls="panel-nav">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>
                <div class="flex-1 min-w-0">
                    <div class="text-[17px] font-extrabold text-bosque leading-tight">@yield('title', 'Panel')</div>
                </div>
                <div id="estado-campana" class="flex items-center gap-2.5 flex-wrap">
                    <span class="pill-campana" style="color:{{ $campanaTexto }};background:{{ $campanaFondo }}">
                        <span class="dot" style="background:{{ $campanaTexto }}"></span>
                        {{ $campanaLabel }}
                    </span>
                    @if ($pausadoCabecera)
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
            </header>

            <main class="flex-1 overflow-auto px-3.5 py-3.5 pb-8 md:px-5 md:py-5 md:pb-9 lg:px-7 lg:py-6 lg:pb-10 bg-hueso">
                @if (session('status'))
                    <p class="mb-4 text-sm font-semibold text-ok bg-ok-bg rounded-lg px-3 py-2">{{ session('status') }}</p>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
@else
    <main class="min-h-screen flex flex-col items-center justify-center px-4 py-10 bg-hueso">
        @if (session('status'))
            <p class="mb-4 text-sm font-semibold text-ok bg-ok-bg rounded-lg px-3 py-2">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
@endauth

@auth
<script src="{{ asset('js/panel-nav.js') }}" defer></script>
@endauth
@stack('scripts')
</body>
</html>
