@props([
    'tabs' => [],
    'default' => 0,
    'id' => null,
    'label' => null,
    'activacion' => 'auto',
])

@php
    /*
     * Identificador del GRUPO. Tiene que salir igual acá y en <x-muni::tab-panel>,
     * porque de él cuelgan los id de cada pestaña y de cada panel (aria-controls /
     * aria-labelledby). Por eso NO se usa uniqid(): bajo Livewire 3 cada refresco
     * generaría ids nuevos y rompería la relación —el mismo defecto que ya arrastran
     * modal, drawer, input y select—. Se deriva del MISMO dato que el hijo puede leer
     * con @aware: la prop `id` si el consumidor la pasó, y si no un slug determinista
     * del array `tabs`. Si esta línea cambia, hay que cambiarla igual en
     * tab-panel.blade.php.
     *
     * Dos grupos con etiquetas idénticas en la misma página colisionarían: para eso
     * está la prop `id`.
     */
    $grupoId = $id ?: 'muni-tabs-'.substr(sha1(json_encode(array_values((array) $tabs), JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
    $activo = (int) $default;
    $manual = $activacion === 'manual';
@endphp

{{-- Pestañas (Alpine 3 core, patrón «tabs» de las WAI-ARIA APG). `tabs` es un array
     de etiquetas; los paneles van en el slot como <x-muni::tab-panel> en el mismo
     orden. Teclado: ←/→ circulares, Home, End y —con activacion="manual"— Enter o
     Espacio, que resuelve el <button> nativo sin manejador propio.

     `focused` va aparte de `active` para permitir la activación manual: con
     wire:model, activar en cada flecha dispararía una petición Livewire por pulsación.
     Con activacion="auto" (el default, retrocompatible) `focused` sigue a `active`. --}}
<div
    id="{{ $grupoId }}"
    x-data="{
        active: {{ $activo }},
        focused: {{ $activo }},
        count: {{ count($tabs) }},
        manual: {{ $manual ? 'true' : 'false' }},
        mover(i) {
            if (! this.count) return;
            this.focused = ((i % this.count) + this.count) % this.count;
            if (! this.manual) { this.active = this.focused; }
            this.$nextTick(() => { const b = this.$refs['tab' + this.focused]; if (b) b.focus(); });
        },
        seleccionar(i) { this.focused = i; this.active = i; },
    }"
    {{ $attributes }}
>
    <div
        role="tablist"
        @if ($label) aria-label="{{ $label }}" @endif
        style="display:flex;gap:2px;border-bottom:1px solid var(--muni-border);margin-bottom:16px;overflow-x:auto;"
        @keydown.right.prevent="mover(focused + 1)"
        @keydown.left.prevent="mover(focused - 1)"
        @keydown.home.prevent="mover(0)"
        @keydown.end.prevent="mover(count - 1)"
    >
        @foreach ($tabs as $i => $etiqueta)
            <button
                type="button"
                role="tab"
                id="{{ $grupoId }}-tab-{{ $i }}"
                aria-controls="{{ $grupoId }}-panel-{{ $i }}"
                {{-- Estáticos además de enlazados: hasta que Alpine hidrate, si no
                     estuvieran, las N pestañas serían tabulables y ninguna seleccionada. --}}
                aria-selected="{{ $i === $activo ? 'true' : 'false' }}"
                tabindex="{{ $i === $activo ? '0' : '-1' }}"
                x-ref="tab{{ $i }}"
                :aria-selected="active === {{ $i }}"
                :tabindex="focused === {{ $i }} ? 0 : -1"
                @click="seleccionar({{ $i }})"
                class="muni-tab"
                :class="active === {{ $i }} && 'muni-tab--on'"
            >{{ $etiqueta }}</button>
        @endforeach
    </div>

    {{ $slot }}
</div>

@once
    <style>
        .muni-tab { position:relative;padding:10px 14px;font-family:var(--muni-font-sans);font-size:13.5px;font-weight:600;
            color:var(--muni-muted);background:transparent;border:none;cursor:pointer;white-space:nowrap;
            transition:color var(--muni-dur) var(--muni-ease); }
        .muni-tab:hover { color:var(--muni-text); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-tab:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; box-shadow:var(--muni-ring); border-radius:var(--muni-radius-sm); }
        .muni-tab--on { color:var(--muni-accent); }
        .muni-tab--on::after { content:"";position:absolute;left:8px;right:8px;bottom:-1px;height:2px;
            background:var(--muni-accent);border-radius:2px 2px 0 0; }
        /* El panel enfocable (tabindex=0, APG) también necesita indicador de foco propio. */
        .muni-tabpanel:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); border-radius:var(--muni-radius-sm); }
    </style>
@endonce
