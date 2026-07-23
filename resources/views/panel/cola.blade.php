@extends('panel.layout')

@section('title', 'Cola')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Cola</h1>
            <p class="text-sm text-slate-600 mt-1">Mensajes de hoy y mañana</p>
        </div>
        <p class="text-sm font-medium tabular-nums">
            Enviados {{ $enviados }} / {{ $cuota }} cuota
        </p>
    </div>

    <div class="bg-white border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Hora</th>
                    <th class="px-3 py-2 font-medium">Sector</th>
                    <th class="px-3 py-2 font-medium">Dominio</th>
                    <th class="px-3 py-2 font-medium">Asunto</th>
                    <th class="px-3 py-2 font-medium">Hallazgo</th>
                    <th class="px-3 py-2 font-medium">Estado</th>
                    <th class="px-3 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mensajes as $fila)
                    @php $m = $fila['mensaje']; @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 whitespace-nowrap">
                            <a href="{{ route('mensajes.ver', $m) }}" class="underline">
                                {{ optional($m->programado_para)?->format('Y-m-d H:i') }}
                            </a>
                        </td>
                        <td class="px-3 py-2">{{ $fila['sector'] }}</td>
                        <td class="px-3 py-2">{{ $fila['dominio'] ?? '—' }}</td>
                        <td class="px-3 py-2 max-w-xs truncate">{{ $m->asunto }}</td>
                        <td class="px-3 py-2 max-w-xs truncate text-slate-600">{{ $fila['hallazgo'] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $m->estado }}</td>
                        <td class="px-3 py-2 text-right">
                            @if (in_array($m->estado, ['pendiente', 'enviando'], true))
                                <form method="POST" action="{{ route('mensajes.cancelar', $m) }}" onsubmit="return confirm('¿Cancelar este mensaje?')">
                                    @csrf
                                    <button class="text-red-700 hover:underline">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">No hay mensajes programados para hoy ni mañana.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
