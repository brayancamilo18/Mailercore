@props(['estado'])

@php
    $mapa = [
        'pendiente' => ['Pendiente', '#5F6B66', '#EEF1EF'],
        'enviando' => ['Enviando…', '#2F5E96', '#E1ECF8'],
        'enviado' => ['Enviado', '#2C7A3F', '#E4F3E7'],
        'fallido' => ['Fallido', '#B0432F', '#F9E9E6'],
        'cancelado' => ['Cancelado', '#8B968F', '#F0F2F0'],
    ];

    [$etiqueta, $texto, $fondo] = $mapa[$estado] ?? [$estado, '#5F6B66', '#EEF1EF'];
@endphp

<span {{ $attributes->merge([
    'style' => "display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;color:{$texto};background:{$fondo};",
]) }}>{{ $etiqueta }}</span>
