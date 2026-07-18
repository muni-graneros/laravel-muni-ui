@props([
    'name' => '',
    'src' => null,
    'size' => 'md',
    'tone' => 'accent',
])

@php
    $px = ['sm' => 26, 'md' => 32, 'lg' => 40][$size] ?? 32;
    $font = ['sm' => 10.5, 'md' => 12.5, 'lg' => 15][$size] ?? 12.5;
    // Iniciales: primera letra del primer y último token.
    $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    $bg = $tone === 'accent' ? 'var(--muni-accent-soft)' : 'var(--muni-surface-3)';
    $fg = $tone === 'accent' ? 'var(--muni-accent)' : 'var(--muni-text)';
@endphp

<span
    {{ $attributes->merge([
        'style' => "display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"
            ."width:{$px}px;height:{$px}px;border-radius:50%;overflow:hidden;"
            ."font-family:var(--muni-font-sans);font-size:{$font}px;font-weight:700;"
            .($src ? '' : "background:{$bg};color:{$fg};border:1px solid var(--muni-border);"),
    ]) }}
    @if ($name) title="{{ $name }}" aria-label="{{ $name }}" @endif
>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name }}" style="width:100%;height:100%;object-fit:cover;">
    @else
        {{ $initials }}
    @endif
</span>
