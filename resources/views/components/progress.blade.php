@props([
    'value' => 0,
    'max' => 100,
    'tone' => 'accent',
    'label' => null,
    'showValue' => false,
])

@php
    $pct = $max > 0 ? max(0, min(100, round($value / $max * 100))) : 0;
    $color = [
        'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)',
        'warn' => 'var(--muni-warn-fg)', 'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)',
    ][$tone] ?? 'var(--muni-accent)';
@endphp

<div {{ $attributes }}>
    @if ($label || $showValue)
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;margin-bottom:6px;">
            @if ($label)<span style="font-family:var(--muni-font-sans);font-size:12px;font-weight:600;color:var(--muni-muted);">{{ $label }}</span>@endif
            @if ($showValue)<span style="font-family:var(--muni-font-mono);font-size:12px;font-variant-numeric:tabular-nums;color:var(--muni-text);">{{ $pct }}%</span>@endif
        </div>
    @endif
    <div role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
         style="height:7px;border-radius:999px;background:var(--muni-surface-3);overflow:hidden;">
        <div style="height:100%;width:{{ $pct }}%;border-radius:999px;background:{{ $color }};transition:width .5s var(--muni-ease);"></div>
    </div>
</div>
