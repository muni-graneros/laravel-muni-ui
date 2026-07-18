@props([
    'data' => [],
    'height' => 160,
    'tone' => 'accent',
    'labels' => true,
])

@php
    // $data: array de ['label'=>, 'value'=>, 'tone'=>?] o de números.
    $norm = [];
    foreach ($data as $d) {
        $norm[] = is_array($d) ? $d : ['label' => '', 'value' => $d];
    }
    $max = 0;
    foreach ($norm as $d) { $max = max($max, (float) $d['value']); }
    $max = $max ?: 1;
    $toneColor = [
        'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)', 'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'muni-chartbar']) }} style="display:flex;flex-direction:column;">
    <div style="display:flex;align-items:flex-end;gap:8px;height:{{ $height }}px;padding-top:8px;">
        @foreach ($norm as $d)
            @php
                $h = round((float) $d['value'] / $max * 100, 1);
                $c = $toneColor[$d['tone'] ?? $tone] ?? 'var(--muni-accent)';
            @endphp
            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;min-width:0;" title="{{ $d['label'] }}: {{ $d['value'] }}">
                <span class="muni-chartbar__val">{{ $d['value'] }}</span>
                <div class="muni-chartbar__bar" style="height:{{ $h }}%;background:linear-gradient(180deg,{{ $c }},color-mix(in srgb,{{ $c }} 55%,transparent));"></div>
            </div>
        @endforeach
    </div>
    @if ($labels)
        <div style="display:flex;gap:8px;margin-top:8px;">
            @foreach ($norm as $d)
                <span style="flex:1;text-align:center;font-family:var(--muni-font-sans);font-size:10.5px;color:var(--muni-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;">{{ $d['label'] }}</span>
            @endforeach
        </div>
    @endif
</div>

@once
    <style>
        .muni-chartbar__bar { width:100%; max-width:38px; border-radius:5px 5px 0 0; transition:height .6s var(--muni-ease); }
        .muni-chartbar__val { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; font-size:11px; font-weight:600; color:var(--muni-muted); margin-bottom:5px; }
    </style>
@endonce
