@props(['valor' => 0, 'etiqueta' => ''])

@php
    $valor = max(0, min(100, (int) $valor));

    $color = match (true) {
        $valor < 50 => '#B0432F',
        $valor < 80 => '#96660F',
        default => '#2C7A3F',
    };
@endphp

<div {{ $attributes }}>
    @if ($etiqueta !== '' && $etiqueta !== null)
        <div @style(['font-size:11px', 'font-weight:600', 'color:#8B968F', 'margin-bottom:6px'])>{{ $etiqueta }}</div>
    @endif
    <div @style(['display:flex', 'align-items:center', 'gap:10px'])>
        <div @style(['flex:1', 'height:8px', 'border-radius:999px', 'background:#F1F4F1', 'overflow:hidden'])>
            <div @style(["width:{$valor}%", 'height:100%', 'border-radius:999px', "background:{$color}"])></div>
        </div>
        <span @style([
            'font-size:13px',
            'font-weight:800',
            "color:{$color}",
            'font-variant-numeric:tabular-nums',
            'min-width:2.5ch',
            'text-align:right',
        ])>{{ $valor }}</span>
    </div>
</div>
