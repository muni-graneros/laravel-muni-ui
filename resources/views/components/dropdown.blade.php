@props([
    'align' => 'end', // start | end, respecto del disparador
    'width' => '220px', // ancho del menú
])

@php
    $origin = $align === 'start' ? 'left:0;' : 'right:0;';
@endphp

{{-- Menú desplegable (Alpine 3). El slot `trigger` es el botón; el slot por defecto son
     los ítems (usar dropdown-item de muni). Cierra al hacer click fuera, con Escape o al
     elegir un ítem (evento `muni-dropdown-close`). ↑/↓ recorren los ítems. --}}
<div
    x-data="{
        open: false,
        boton() { return this.$refs.trigger.querySelector('button,a[href],[tabindex]'); },
        items() { return [...this.$refs.menu.querySelectorAll('[role=menuitem]')].filter(e => ! e.disabled); },
        mover(d) {
            const it = this.items(); if (! it.length) return;
            const i = it.indexOf(document.activeElement);
            it[i < 0 ? (d > 0 ? 0 : it.length - 1) : (i + d + it.length) % it.length].focus();
        },
        flecha(d) {
            if (this.open) return this.mover(d);
            this.open = true; this.$nextTick(() => this.mover(d));
        },
        cerrar(foco) { this.open = false; if (foco) this.boton()?.focus(); }
    }"
    x-id="['muni-dd']"
    @keydown.escape.window="if (open) cerrar($el.contains(document.activeElement))"
    {{-- Las flechas se dejan en paz dentro de campos: en un input o select del menú mueven el cursor o la opción. --}}
    @keydown.down="if (! $event.target.closest('input,select,textarea,[contenteditable]')) { $event.preventDefault(); flecha(1) }"
    @keydown.up="if (! $event.target.closest('input,select,textarea,[contenteditable]')) { $event.preventDefault(); flecha(-1) }"
    @muni-dropdown-close="cerrar(true)"
    style="position:relative;display:inline-block;"
>
    <div
        x-ref="trigger"
        @click="open = ! open"
        x-effect="const b = boton(); if (b) { b.setAttribute('aria-haspopup', 'menu'); b.setAttribute('aria-expanded', open); b.setAttribute('aria-controls', $id('muni-dd')); }"
        style="display:inline-flex;"
    >
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-ref="menu"
        :id="$id('muni-dd')"
        @click.outside="open = false"
        x-transition:enter="muni-dd-enter"
        x-transition:enter-start="muni-dd-enter-start"
        x-transition:enter-end="muni-dd-enter-end"
        role="menu"
        {{ $attributes->merge([
            'style' => "position:absolute;top:calc(100% + 6px);{$origin}z-index:50;min-width:{$width};"
                ."padding:5px;background:var(--muni-surface);border:1px solid var(--muni-border);"
                ."border-radius:var(--muni-radius);box-shadow:var(--muni-shadow-lg);"
                ."transform-origin:top ".($align === 'start' ? 'left' : 'right').";",
        ]) }}
    >
        {{ $slot }}
    </div>
</div>

@once
    <style>
        .muni-dd-enter { transition: opacity var(--muni-dur) var(--muni-ease), transform var(--muni-dur) var(--muni-ease); }
        .muni-dd-enter-start { opacity: 0; transform: scale(0.96) translateY(-4px); }
        .muni-dd-enter-end { opacity: 1; transform: scale(1) translateY(0); }
    </style>
@endonce
