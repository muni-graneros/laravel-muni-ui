@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $tag = $href ? 'a' : 'button';
    $sizes = [
        'sm' => 'padding:6px 12px;font-size:12.5px;',
        'md' => 'padding:9px 16px;font-size:13.5px;',
        'lg' => 'padding:11px 20px;font-size:15px;',
    ];
    $base = 'display:inline-flex;align-items:center;justify-content:center;gap:7px;'
        .'font-family:var(--muni-font-sans);font-weight:600;line-height:1;cursor:pointer;'
        .'border-radius:var(--muni-radius-sm);border:1px solid transparent;text-decoration:none;'
        .'transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease);'
        .($sizes[$size] ?? $sizes['md']);
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => 'muni-btn muni-btn--'.$variant, 'style' => $base]) }}
>
    @if ($icon)<span class="muni-btn__icon" aria-hidden="true">{!! $icon !!}</span>@endif
    {{ $slot }}
</{{ $tag }}>

@once
    <style>
        .muni-btn:focus-visible { outline: none; box-shadow: var(--muni-ring); }
        .muni-btn:active { transform: translateY(1px); }
        .muni-btn__icon { display: inline-flex; }
        .muni-btn__icon svg { width: 15px; height: 15px; }

        .muni-btn--primary { background: var(--muni-accent); color: var(--muni-on-accent); }
        .muni-btn--primary:hover { background: var(--muni-accent-strong); box-shadow: var(--muni-shadow); }

        .muni-btn--ghost { background: transparent; color: var(--muni-text); border-color: var(--muni-border); }
        .muni-btn--ghost:hover { background: var(--muni-surface-2); border-color: var(--muni-border-2); }

        .muni-btn--subtle { background: var(--muni-surface-2); color: var(--muni-text); }
        .muni-btn--subtle:hover { background: var(--muni-surface-3); }

        .muni-btn--danger { background: var(--muni-danger-bg); color: var(--muni-danger-fg); border-color: var(--muni-danger-border); }
        .muni-btn--danger:hover { background: var(--muni-danger-fg); color: #fff; }
    </style>
@endonce
