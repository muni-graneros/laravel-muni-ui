@props([
    'index',
])

{{-- Panel de una pestaña. `index` debe coincidir con la posición de su etiqueta en
     el array `tabs` del <x-muni::tabs> padre. --}}
<div
    x-show="active === {{ (int) $index }}"
    x-cloak
    role="tabpanel"
    x-transition:enter="muni-fade" x-transition:enter-start="muni-fade-0" x-transition:enter-end="muni-fade-1"
    {{ $attributes }}
>
    {{ $slot }}
</div>
