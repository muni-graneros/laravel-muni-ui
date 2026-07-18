@props([
    'width' => '240px',
])

{{-- Barra lateral de navegación para dashboards. En móvil se colapsa (toggle con el
     evento `muni-sidebar`). El slot son <x-muni::nav-item> y <x-muni::nav-section>. --}}
<aside
    x-data="{ open: window.innerWidth >= 900 }"
    @muni-sidebar.window="open = !open"
    :class="open ? 'muni-sb--open' : ''"
    class="muni-sb"
    style="--sb-w:{{ $width }};"
    {{ $attributes }}
>
    <div class="muni-sb__inner">
        {{ $slot }}
    </div>
</aside>

@once
    <style>
        .muni-sb { flex-shrink:0; width:var(--sb-w); background:var(--muni-surface); border-right:1px solid var(--muni-border); }
        .muni-sb__inner { position:sticky; top:0; display:flex; flex-direction:column; gap:2px; height:100vh; overflow-y:auto; padding:16px 12px; }
        @media (max-width:899px) {
            .muni-sb { position:fixed; inset:0 auto 0 0; z-index:150; transform:translateX(-100%); transition:transform var(--muni-dur) var(--muni-ease); box-shadow:var(--muni-shadow-lg); }
            .muni-sb--open { transform:translateX(0); }
        }
    </style>
@endonce
