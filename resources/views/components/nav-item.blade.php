@props([
    'href' => '#',
    'icon' => null,
    'active' => false,
    'badge' => null,
])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge([
        'class' => 'muni-nav-item '.($active ? 'muni-nav-item--active' : ''),
    ]) }}
>
    @if ($icon)<span aria-hidden="true" class="muni-nav-item__icon">{!! $icon !!}</span>@endif
    <span style="flex:1;min-width:0;">{{ $slot }}</span>
    @if ($badge !== null)<span class="muni-nav-item__badge">{{ $badge }}</span>@endif
</a>

@once
    <style>
        .muni-nav-item { display:flex; align-items:center; gap:11px; padding:9px 11px; border-radius:var(--muni-radius-sm);
            font-family:var(--muni-font-sans); font-size:13.5px; font-weight:500; color:var(--muni-muted); text-decoration:none;
            transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-nav-item:hover { background:var(--muni-surface-2); color:var(--muni-text); }
        .muni-nav-item:focus-visible { outline:none; box-shadow:var(--muni-ring); }
        .muni-nav-item--active { background:var(--muni-accent-soft); color:var(--muni-accent); font-weight:600; }
        .muni-nav-item__icon { display:inline-flex; flex-shrink:0; width:18px; height:18px; }
        .muni-nav-item__icon svg { width:18px; height:18px; }
        .muni-nav-item__badge { font-family:var(--muni-font-mono); font-size:10.5px; font-weight:600; padding:1px 7px; border-radius:999px;
            background:var(--muni-surface-3); color:var(--muni-muted); }
        .muni-nav-item--active .muni-nav-item__badge { background:var(--muni-accent); color:var(--muni-on-accent); }
    </style>
@endonce
