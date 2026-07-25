@props(['estado'])

@php
    $colores = [
        'nuevo' => ['#5F6B66', '#EEF1EF'],
        'rastreado' => ['#4C5A54', '#E9EEEA'],
        'auditado' => ['#3E5248', '#E1EAE3'],
        'en_cola' => ['#3E6FA8', '#E9F1F9'],
        'contactado' => ['#2F5E96', '#E1ECF8'],
        'seguimiento' => ['#28517F', '#D8E6F5'],
        'respondido' => ['#2C7A3F', '#E4F3E7'],
        'cliente' => ['#FFFFFF', '#2E8B47'],
        'descartado' => ['#79837D', '#F0F2F0'],
        'baja' => ['#9A5A50', '#F5EAE8'],
        'rebotado' => ['#A34A3B', '#F7E8E5'],
    ];

    [$texto, $fondo] = $colores[$estado] ?? ['#5F6B66', '#EEF1EF'];
    $etiqueta = \App\Models\Lead::ESTADOS[$estado] ?? $estado;
@endphp

<span {{ $attributes->merge([
    'style' => "display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;color:{$texto};background:{$fondo};",
]) }}>{{ $etiqueta }}</span>
