@props([
    'segments' => [], // ['label'=>, 'value'=>, 'tone'=>? o 'color'=>?]
    'size' => 150, // diámetro del anillo en px
    'thickness' => 18, // grosor del anillo (1-50, unidades del viewBox)
    'total' => null, // total de la vuelta; por defecto la suma de valores
    'centerLabel' => null, // cifra o texto al centro del anillo
])

@php
    // $segments: array de ['label'=>, 'value'=>, 'tone'=>? o 'color'=>?]
    $toneColor = [
        'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)', 'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)', 'muted' => 'var(--muni-border-2)',
    ];
    // Un 'value' ausente cuenta 0 y un negativo se recorta a 0: un arco no mide menos.
    $sum = 0;
    foreach ($segments as $s) { $sum += max(0, (float) ($s['value'] ?? 0)); }
    if ($total !== null) { $sum = (float) $total; }
    $sum = $sum > 0 ? $sum : 1;
    // El radio deja el borde exterior del anillo dentro del viewBox 0..100: con r fijo
    // en 42, un grosor de 18 llegaba a 51 y el borde salía recortado.
    $thickness = max(1, min(50, (float) $thickness));
    $r = 50 - $thickness / 2;
    $circ = 2 * M_PI * $r;
    $offset = 0;
    $arcs = [];
    foreach ($segments as $s) {
        $frac = max(0, (float) ($s['value'] ?? 0)) / $sum;
        // Con un `total` menor que la suma, los arcos se detienen al completar la vuelta.
        $len = min($circ * $frac, $circ - $offset);
        $color = $s['color'] ?? ($toneColor[$s['tone'] ?? 'accent'] ?? 'var(--muni-accent)');
        $arcs[] = ['len' => $len, 'gap' => $circ - $len, 'off' => -$offset, 'color' => $color, 'label' => $s['label'] ?? '', 'value' => $s['value'] ?? 0];
        $offset += $len;
    }
@endphp

<div {{ $attributes->merge(['style' => 'display:inline-flex;align-items:center;gap:20px;flex-wrap:wrap;']) }}>
    <div style="position:relative;width:{{ $size }}px;height:{{ $size }}px;flex-shrink:0;">
        {{--
            El dibujo va oculto al lector: el dato ya está como texto en la
            leyenda (nombre y valor de cada segmento), y un svg sin rol ni
            nombre solo agrega ruido (WCAG 4.1.2 y 1.1.1). La cifra central es
            texto y se lee.
        --}}
        <svg aria-hidden="true" focusable="false" viewBox="0 0 100 100" style="width:100%;height:100%;transform:rotate(-90deg);">
            <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="var(--muni-surface-3)" stroke-width="{{ $thickness }}"/>
            @foreach ($arcs as $a)
                <circle class="muni-donut__arco" cx="50" cy="50" r="{{ $r }}" fill="none" stroke="{{ $a['color'] }}" stroke-width="{{ $thickness }}"
                        stroke-dasharray="{{ round($a['len'], 2) }} {{ round($a['gap'], 2) }}" stroke-dashoffset="{{ round($a['off'], 2) }}"
                        style="transition:stroke-dasharray var(--muni-dur-slow, 600ms) var(--muni-ease);"/>
            @endforeach
        </svg>
        @if ($centerLabel !== null)
            <div style="position:absolute;inset:0;display:grid;place-items:center;text-align:center;">
                <span style="font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;font-size:{{ round($size / 5.5) }}px;font-weight:700;color:var(--muni-text);line-height:1;">{{ $centerLabel }}</span>
            </div>
        @endif
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;">
        @foreach ($arcs as $a)
            <div style="display:flex;align-items:center;gap:9px;font-family:var(--muni-font-sans);font-size:12.5px;">
                <span aria-hidden="true" style="width:10px;height:10px;border-radius:3px;background:{{ $a['color'] }};flex-shrink:0;box-shadow:var(--muni-glow);"></span>
                <span style="color:var(--muni-muted);flex:1;">{{ $a['label'] }}</span>
                <span style="font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;font-weight:600;color:var(--muni-text);">{{ $a['value'] }}</span>
            </div>
        @endforeach
    </div>
</div>

{{--
    Los arcos duran --muni-dur-slow (600 ms), no una duración fija, que ignoraba
    prefers-reduced-motion (DESIGN §6). La guardia local cubre el panel Filament
    y una hoja publicada vieja sin el token.
--}}
@once
    <style>
        /* Con !important: la transición va en el style de cada arco. */
        @media (prefers-reduced-motion:reduce) { .muni-donut__arco { transition:none !important; } }
    </style>
@endonce
