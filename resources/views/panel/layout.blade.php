<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — Outreach</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
    @auth
        <header class="bg-white border-b border-slate-200 sticky top-0 z-10">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
                <nav class="flex flex-wrap gap-4 text-sm font-medium">
                    <a href="{{ route('panel.resumen') }}" class="{{ request()->routeIs('panel.resumen') ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">Resumen</a>
                    <a href="{{ route('leads.indice') }}" class="{{ request()->routeIs('leads.*') ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">Leads</a>
                    <a href="{{ route('cola.indice') }}" class="{{ request()->routeIs('cola.*', 'mensajes.*') ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">Cola</a>
                    <a href="{{ route('salud.indice') }}" class="{{ request()->routeIs('salud.*', 'envio.*') ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">Salud</a>
                    <a href="{{ route('cosecha.indice') }}" class="{{ request()->routeIs('cosecha.*') ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">Cosecha</a>
                </nav>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-slate-600 hover:text-slate-900">Salir</button>
                </form>
            </div>
        </header>
    @endauth

    <main class="max-w-6xl mx-auto px-4 py-8">
        @if (session('status'))
            <p class="mb-4 text-sm text-emerald-700">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
