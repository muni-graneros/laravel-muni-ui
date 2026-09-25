@props([
    'segments' => [], // lista de ['label' => , 'value' => , 'tone' => ? | 'color' => ?]
    'size' => 150, // diámetro en px
    'thickness' => 18, // grosor del anillo (unidades del viewBox 100)
    'total' => null, // total de referencia; por defecto la suma
    'centerLabel' => null, // texto grande al centro
])

@php
    // $segments: array de ['label'=>, 'value'=>, 'tone'=>? o 'color'=>?]
    $toneColor = [
        'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)', 'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)', 'muted' => 'var(--muni-border-2)',
    ];
    // Valores ausentes cuentan 0 y los negativos se recortan a 0 (un arco no puede medir menos).
    $sum = 0;
    foreach ($segments as $s) { $sum += max(0, (float) ($s['value'] ?? 0)); }
    if ($total !== null) { $sum = (float) $total; }
    $sum = $sum > 0 ? $sum : 1;
    // El radio deja el borde exterior del anillo dentro del viewBox 0..100.
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
        <svg viewBox="0 0 100 100" style="width:100%;height:100%;transform:rotate(-90deg);">
            <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="var(--muni-surface-3)" stroke-width="{{ $thickness }}"/>
            @foreach ($arcs as $a)
                <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="{{ $a['color'] }}" stroke-width="{{ $thickness }}"
                        stroke-dasharray="{{ round($a['len'], 2) }} {{ round($a['gap'], 2) }}" stroke-dashoffset="{{ round($a['off'], 2) }}"
                        style="transition:stroke-dasharray calc(var(--muni-dur) * 4) var(--muni-ease);"/>
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
                <span style="width:10px;height:10px;border-radius:3px;background:{{ $a['color'] }};flex-shrink:0;box-shadow:var(--muni-glow);"></span>
                <span style="color:var(--muni-muted);flex:1;">{{ $a['label'] }}</span>
                <span style="font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;font-weight:600;color:var(--muni-text);">{{ $a['value'] }}</span>
            </div>
        @endforeach
    </div>
</div>
