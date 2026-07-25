@props(['salud'])

@php
    $clave = $salud === 'detenido' ? 'parado' : $salud;

    $mapa = [
        'verde' => ['✓', 'Verde', '#2C7A3F', '#E4F3E7'],
        'ambar' => ['!', 'Ámbar', '#96660F', '#FAF0DC'],
        'rojo' => ['✕', 'Rojo', '#B0432F', '#F9E9E6'],
        'parado' => ['⏸', 'Detenido', '#5F6B66', '#EEF1EF'],
    ];

    [$icono, $etiqueta, $texto, $fondo] = $mapa[$clave] ?? ['·', $salud, '#5F6B66', '#EEF1EF'];
@endphp

<span {{ $attributes->merge([
    'style' => "display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;color:{$texto};background:{$fondo};",
]) }}>
    <span aria-hidden="true">{{ $icono }}</span>
    <span>{{ $etiqueta }}</span>
</span>
