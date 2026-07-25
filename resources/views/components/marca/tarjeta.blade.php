@props(['titulo' => null, 'flush' => false])

<section {{ $attributes->merge([
    'style' => 'background:#fff;border:1px solid var(--bd,#E4E8E4);border-radius:12px;box-shadow:0 1px 2px rgba(11,31,26,.04);min-width:0;'.($flush ? 'padding:0;overflow:hidden;' : 'padding:18px 22px;'),
]) }}>
    @if ($titulo)
        <h2 style="font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#8B968F;margin:0 0 16px;">{{ $titulo }}</h2>
    @endif
    {{ $slot }}
</section>
