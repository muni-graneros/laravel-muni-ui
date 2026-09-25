@props([
    'title' => '', // título del panel (texto del botón)
    'open' => false, // abierto al cargar
])

{{-- Panel de acordeón para usar en el slot del accordion padre, en lugar de `items`.
     Toma su posición al iniciar (contador `_n` del padre), así que el `default` del
     padre y `open` funcionan igual que con `items`. Sin JS solo se ve el panel `open`. --}}
<div {{ $attributes->merge(['class' => 'muni-acc__item']) }} x-id="['muni-acc']"
     x-data="{ k: null }"
     x-init="k = _n++; if ({{ $open ? 'true' : 'false' }}) { if (multiple) { if (! opened.includes(k)) opened.push(k); } else { open = k; } }">
    <button type="button" class="muni-acc__head" @click="toggle(k)"
            aria-expanded="{{ $open ? 'true' : 'false' }}" :aria-expanded="isOpen(k) ? 'true' : 'false'"
            :id="$id('muni-acc') + '-h'" :aria-controls="$id('muni-acc')">
        <span>{{ $title }}</span>
        <span class="muni-acc__chevron {{ $open ? 'muni-acc__chevron--open' : '' }}" :class="{ 'muni-acc__chevron--open': isOpen(k) }">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    </button>
    <div class="muni-acc__panel" role="region" x-show="isOpen(k)" @unless ($open) x-cloak @endunless
         :id="$id('muni-acc')" :aria-labelledby="$id('muni-acc') + '-h'"
         x-transition:enter="muni-acc-enter" x-transition:enter-start="muni-acc-0" x-transition:enter-end="muni-acc-1">
        <div class="muni-acc__body">{{ $slot }}</div>
    </div>
</div>
