@props([
    'value',
    'label',
    'tone' => 'neutral',
    'delta' => null,
    'deltaDir' => null,
    'spark' => null,
    'hint' => null,
])

@php
    $accent = [
        'neutral' => 'var(--muni-text)', 'ok' => 'var(--muni-ok-fg)',
        'warn' => 'var(--muni-warn-fg)', 'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)',
    ][$tone] ?? 'var(--muni-text)';

    // Sparkline: array de números → path SVG normalizado en un viewbox 100x28.
    $sparkPath = null;
    if (is_array($spark) && count($spark) > 1) {
        $min = min($spark); $max = max($spark); $range = ($max - $min) ?: 1;
        $n = count($spark) - 1;
        $pts = [];
        foreach ($spark as $i => $v) {
            $x = round($i / $n * 100, 2);
            $y = round(26 - (($v - $min) / $range) * 24, 2);
            $pts[] = "$x,$y";
        }
        $sparkPath = 'M'.implode(' L', $pts);
    }

    $deltaColor = $deltaDir === 'up' ? 'var(--muni-ok-fg)' : ($deltaDir === 'down' ? 'var(--muni-danger-fg)' : 'var(--muni-muted)');
    $arrow = $deltaDir === 'up' ? '&#9650;' : ($deltaDir === 'down' ? '&#9660;' : '');
@endphp

<div class="muni-stat" {{ $attributes->merge(['style' => 'position:relative;padding:16px 18px;background:var(--muni-surface);border:1px solid var(--muni-border);border-radius:var(--muni-radius);box-shadow:var(--muni-shadow);min-width:170px;transition:box-shadow var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease);']) }}>
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
        <div style="min-width:0;">
            <div style="font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;font-size:26px;font-weight:700;line-height:1.05;color:{{ $accent }};">{{ $value }}</div>
            <div style="margin-top:5px;font-size:11px;font-weight:600;color:var(--muni-muted);text-transform:uppercase;letter-spacing:.04em;">{{ $label }}</div>
        </div>
        @if ($delta !== null)
            <span style="display:inline-flex;align-items:center;gap:3px;font-family:var(--muni-font-mono);font-size:11.5px;font-weight:600;color:{{ $deltaColor }};white-space:nowrap;">
                <span aria-hidden="true" style="font-size:8px;">{!! $arrow !!}</span>{{ $delta }}
            </span>
        @endif
    </div>

    @if ($sparkPath)
        <svg viewBox="0 0 100 28" preserveAspectRatio="none" style="width:100%;height:26px;margin-top:10px;overflow:visible;" aria-hidden="true">
            <path d="{{ $sparkPath }} L100,28 L0,28 Z" fill="{{ $accent }}" opacity="0.08" />
            <path d="{{ $sparkPath }}" fill="none" stroke="{{ $accent }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
        </svg>
    @endif
    @if ($hint)<div style="margin-top:6px;font-size:11px;color:var(--muni-hint);">{{ $hint }}</div>@endif
</div>

@once
    <style>.muni-stat:hover { box-shadow: var(--muni-shadow-md); transform: translateY(-1px); }</style>
@endonce
