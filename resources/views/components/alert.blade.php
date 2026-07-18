@props([
    'tone' => 'info',
    'title' => null,
    'icon' => null,
])

@php
    $t = [
        'ok' => ['fg' => 'var(--muni-ok-fg)', 'bg' => 'var(--muni-ok-bg)', 'border' => 'var(--muni-ok-border)'],
        'warn' => ['fg' => 'var(--muni-warn-fg)', 'bg' => 'var(--muni-warn-bg)', 'border' => 'var(--muni-warn-border)'],
        'danger' => ['fg' => 'var(--muni-danger-fg)', 'bg' => 'var(--muni-danger-bg)', 'border' => 'var(--muni-danger-border)'],
        'info' => ['fg' => 'var(--muni-info-fg)', 'bg' => 'var(--muni-info-bg)', 'border' => 'var(--muni-info-border)'],
    ][$tone] ?? null;
    $t ??= ['fg' => 'var(--muni-info-fg)', 'bg' => 'var(--muni-info-bg)', 'border' => 'var(--muni-info-border)'];
@endphp

<div
    role="{{ $tone === 'danger' ? 'alert' : 'status' }}"
    {{ $attributes->merge([
        'style' => "display:flex;gap:11px;padding:12px 14px;border-radius:var(--muni-radius);"
            ."background:{$t['bg']};border:1px solid {$t['border']};"
            ."border-left-width:3px;color:var(--muni-text);font-family:var(--muni-font-sans);font-size:13px;line-height:1.5;",
    ]) }}
>
    <span aria-hidden="true" style="flex-shrink:0;width:16px;height:16px;margin-top:1px;color:{{ $t['fg'] }};">
        {!! $icon ?? '<svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 4a1 1 0 011 1v4a1 1 0 11-2 0V7a1 1 0 011-1zm0 8a1 1 0 100 2 1 1 0 000-2z"/></svg>' !!}
    </span>
    <div style="min-width:0;">
        @if ($title)<div style="font-weight:700;color:{{ $t['fg'] }};margin-bottom:2px;">{{ $title }}</div>@endif
        <div style="color:var(--muni-muted);">{{ $slot }}</div>
    </div>
</div>
