@props(['sector'])

@php
    $mapa = [
        'hosteleria' => ['Ho', 'Hostelería', '#8F4E24', '#F6EBE2'],
        'salud' => ['Sa', 'Salud', '#2F5E96', '#E7EFF8'],
        'retail' => ['Co', 'Comercio', '#6B4FA0', '#EFEAF7'],
        'servicios_profesionales' => ['Sp', 'Serv. profesionales', '#446181', '#E9EEF3'],
        'oficios' => ['Of', 'Oficios', '#7A5E2E', '#F4EEE0'],
        'belleza' => ['Be', 'Belleza y bienestar', '#A04E77', '#F7EAF1'],
        'agencias' => ['Ag', 'Agencias', '#3A7A7A', '#E6F1F1'],
    ];
@endphp

@if (isset($mapa[$sector]))
    @php [$abbr, $nombre, $texto, $fondo] = $mapa[$sector]; @endphp
    <span {{ $attributes->merge([
        'style' => 'display:inline-flex;align-items:center;gap:8px;',
    ]) }}>
        <span @style([
            'display:inline-flex',
            'align-items:center',
            'justify-content:center',
            'width:26px',
            'height:26px',
            'border-radius:6px',
            'font-size:10px',
            'font-weight:800',
            "color:{$texto}",
            "background:{$fondo}",
        ])>{{ $abbr }}</span>
        <span @style(['font-size:13px', 'font-weight:600', 'color:#22312B'])>{{ $nombre }}</span>
    </span>
@else
    <span {{ $attributes->merge([
        'style' => 'display:inline-flex;align-items:center;font-size:13px;font-weight:600;color:#5F6B66;',
    ]) }}>{{ $sector }}</span>
@endif
