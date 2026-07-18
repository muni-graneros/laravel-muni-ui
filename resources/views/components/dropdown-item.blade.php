@props([
    'href' => null,
    'icon' => null,
    'tone' => 'default',
])

@php
    $tag = $href ? 'a' : 'button';
    $color = $tone === 'danger' ? 'var(--muni-danger-fg)' : 'var(--muni-text)';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="button" @endif
    role="menuitem"
    @click="open = false"
    {{ $attributes->merge([
        'class' => 'muni-dd-item',
        'style' => "display:flex;align-items:center;gap:9px;width:100%;padding:8px 10px;"
            ."font-family:var(--muni-font-sans);font-size:13px;font-weight:500;text-align:left;"
            ."color:{$color};background:transparent;border:none;border-radius:var(--muni-radius-sm);"
            ."cursor:pointer;text-decoration:none;transition:background var(--muni-dur) var(--muni-ease);",
    ]) }}
>
    @if ($icon)<span aria-hidden="true" style="display:inline-flex;width:15px;height:15px;opacity:.75;">{!! $icon !!}</span>@endif
    {{ $slot }}
</{{ $tag }}>

@once
    <style>
        .muni-dd-item:hover { background: var(--muni-surface-2); }
        .muni-dd-item:focus-visible { outline: none; box-shadow: var(--muni-ring); }
        .muni-dd-item svg { width: 15px; height: 15px; }
    </style>
@endonce
