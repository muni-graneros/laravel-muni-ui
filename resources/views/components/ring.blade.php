@props([
    'value' => 0,
    'max' => 100,
    'size' => 96,
    'tone' => 'accent',
    'label' => null,
    'showValue' => true,
])

@php
    $pct = $max > 0 ? max(0, min(100, $value / $max * 100)) : 0;
    $r = 42;
    $circ = 2 * M_PI * $r;
    $offset = $circ * (1 - $pct / 100);
    $color = [
        'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)', 'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)',
    ][$tone] ?? 'var(--muni-accent)';
@endphp

<div {{ $attributes->merge(['style' => "display:inline-flex;flex-direction:column;align-items:center;gap:8px;"]) }}>
    <div style="position:relative;width:{{ $size }}px;height:{{ $size }}px;">
        <svg viewBox="0 0 100 100" style="width:100%;height:100%;transform:rotate(-90deg);">
            <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="var(--muni-surface-3)" stroke-width="8"/>
            <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="{{ $color }}" stroke-width="8" stroke-linecap="round"
                    stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}"
                    style="transition:stroke-dashoffset .8s var(--muni-ease);"/>
        </svg>
        @if ($showValue)
            <div style="position:absolute;inset:0;display:grid;place-items:center;">
                <span style="font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;font-size:{{ round($size / 4.5) }}px;font-weight:700;color:{{ $color }};">{{ round($pct) }}<span style="font-size:.5em;">%</span></span>
            </div>
        @endif
    </div>
    @if ($label)<span style="font-family:var(--muni-font-sans);font-size:12px;font-weight:600;color:var(--muni-muted);text-align:center;">{{ $label }}</span>@endif
</div>
