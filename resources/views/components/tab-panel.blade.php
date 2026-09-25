@props([
    'index', // índice de la pestaña a la que pertenece (desde 0)
])

@aware(['default' => 0])

{{-- Panel de una pestaña. `index` debe coincidir con la posición de su etiqueta en
     el array `tabs` del componente tabs padre. Sin JS se ve el panel por defecto. --}}
<div
    x-show="active === {{ (int) $index }}"
    @if ((int) $index !== (int) $default) x-cloak @endif
    role="tabpanel"
    :id="$id('muni-tabpanel', {{ (int) $index }})"
    :aria-labelledby="$id('muni-tab', {{ (int) $index }})"
    x-transition:enter="muni-fade" x-transition:enter-start="muni-fade-0" x-transition:enter-end="muni-fade-1"
    {{ $attributes }}
>
    {{ $slot }}
</div>
