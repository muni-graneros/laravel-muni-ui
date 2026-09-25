@props([
    'width' => '240px', // ancho en escritorio
])

{{-- Barra lateral de navegación para dashboards. En móvil se colapsa (toggle con el
     evento `muni-sidebar`; con detail.open fija el estado) y Escape la cierra; emite
     `muni-sidebar-state` al cambiar. El slot son nav-item y nav-section de muni. --}}
<aside
    x-data="{ open: window.innerWidth >= 900 }"
    @muni-sidebar.window="open = typeof $event.detail?.open === 'boolean' ? $event.detail.open : !open"
    @keydown.escape.window="if (open && window.matchMedia('(max-width: 899px)').matches) open = false"
    x-effect="window.dispatchEvent(new CustomEvent('muni-sidebar-state', { detail: { open } }))"
    :class="open ? 'muni-sb--open' : ''"
    {{ $attributes->merge(['class' => 'muni-sb', 'style' => "--sb-w:{$width};"]) }}
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
