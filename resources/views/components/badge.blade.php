@props([
    'tone' => 'neutral',
    'dot' => true,
])

@php
    $tokens = [
        'neutral' => ['fg' => 'var(--muni-muted)', 'bg' => 'var(--muni-surface-3)', 'border' => 'var(--muni-border)'],
        'ok' => ['fg' => 'var(--muni-ok-fg)', 'bg' => 'var(--muni-ok-bg)', 'border' => 'var(--muni-ok-border)'],
        'warn' => ['fg' => 'var(--muni-warn-fg)', 'bg' => 'var(--muni-warn-bg)', 'border' => 'var(--muni-warn-border)'],
        'danger' => ['fg' => 'var(--muni-danger-fg)', 'bg' => 'var(--muni-danger-bg)', 'border' => 'var(--muni-danger-border)'],
        'info' => ['fg' => 'var(--muni-info-fg)', 'bg' => 'var(--muni-info-bg)', 'border' => 'var(--muni-info-border)'],
    ];
    $t = $tokens[$tone] ?? $tokens['neutral'];
@endphp

<span
    {{ $attributes->merge([
        'style' => "display:inline-flex;align-items:center;gap:6px;padding:3px 9px;"
            ."font-family:var(--muni-font-mono);font-size:11px;font-weight:600;line-height:1;"
            ."letter-spacing:.02em;border-radius:999px;"
            ."color:{$t['fg']};background:{$t['bg']};border:1px solid {$t['border']};",
    ]) }}
>
    @if ($dot)
        <span aria-hidden="true" style="width:6px;height:6px;border-radius:50%;background:{{ $t['fg'] }};box-shadow:var(--muni-glow);"></span>
    @endif
    {{ $slot }}
</span>
