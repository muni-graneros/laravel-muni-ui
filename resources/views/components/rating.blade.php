@props([
    'value' => 0, // calificación actual (admite decimales en lectura)
    'max' => 5, // cantidad de estrellas
    'readonly' => false, // solo lectura: imagen sin controles
    'name' => null, // campo oculto que envía el valor en el formulario
    'tone' => 'accent', // accent | warn | ok
])

@php
    $color = ['accent' => 'var(--muni-accent)', 'warn' => 'var(--muni-warn-fg)', 'ok' => 'var(--muni-ok-fg)'][$tone] ?? 'var(--muni-accent)';
    $max = max(1, (int) $max);
    $value = max(0, min($max, (float) $value));
    $sel = (int) round($value);
    $texto = rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',').' de '.$max;
@endphp

{{-- Solo lectura: una imagen con nombre («Calificación: 4 de 5»), sin botones que
     reciban foco ni reaccionen al ratón. Interactivo: radiogroup con role="radio"
     en cada estrella (aria-checked en un <button> a secas no se anuncia) y
     flechas para cambiar el valor.

     Las estrellas marcadas, el aria-checked y el valor del input oculto salen del
     SERVIDOR: sin JS se ven y el formulario envía el valor. `x-modelable` enlaza
     `value` con el wire:model / x-model que el consumidor ponga en el componente. --}}
@if ($readonly)
    <div role="img" {{ $attributes->merge(['aria-label' => 'Calificación: '.$texto, 'style' => 'display:inline-flex;align-items:center;gap:3px;']) }}>
        @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @for ($i = 1; $i <= $max; $i++)
            <span class="muni-star muni-star--ro {{ $value >= $i ? 'muni-star--on' : '' }}" style="--star:{{ $color }};" aria-hidden="true">★</span>
        @endfor
    </div>
@else
    <div
        x-data="{ value: {{ \Illuminate\Support\Js::from($value) }}, hover: 0, max: {{ $max }}, readonly: false,
            set(v, root){ this.value = Math.max(1, Math.min(this.max, v)); this.$nextTick(() => root.querySelectorAll('[role=radio]')[this.value - 1].focus()); } }"
        @keydown.right.prevent="set(Math.round(value) + 1, $el)" @keydown.up.prevent="set(Math.round(value) + 1, $el)"
        @keydown.left.prevent="set(Math.round(value) - 1, $el)" @keydown.down.prevent="set(Math.round(value) - 1, $el)"
        x-modelable="value"
        role="radiogroup"
        {{ $attributes->merge(['aria-label' => 'Calificación', 'style' => 'display:inline-flex;align-items:center;gap:3px;']) }}
    >
        @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="value">@endif
        @for ($i = 1; $i <= $max; $i++)
            <button
                type="button"
                role="radio"
                @click="value = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0"
                aria-checked="{{ $sel === $i ? 'true' : 'false' }}" :aria-checked="Math.round(value) === {{ $i }} ? 'true' : 'false'"
                tabindex="{{ ($sel ? $sel === $i : $i === 1) ? '0' : '-1' }}" :tabindex="(Math.round(value) ? Math.round(value) === {{ $i }} : {{ $i }} === 1) ? 0 : -1"
                class="muni-star {{ $value >= $i ? 'muni-star--on' : '' }}"
                :class="{ 'muni-star--on': (hover || value) >= {{ $i }} }"
                style="--star:{{ $color }};"
                aria-label="{{ $i }} de {{ $max }}"
            >★</button>
        @endfor
    </div>
@endif

@once
    <style>
        /* Sin marcar va en --muni-hint y no en --muni-border-2: el borde queda en
           1,6:1 y la estrella vacía es lo único que dice cuántas faltan (1.4.11). */
        .muni-star { background:none; border:none; padding:0 1px; font-size:20px; line-height:1; color:var(--muni-hint); cursor:pointer; transition:color var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease); }
        /* 24×24 de objetivo (WCAG 2.2 AA 2.5.8); el glifo sigue en 20px. */
        button.muni-star { display:inline-flex; align-items:center; justify-content:center; min-width:24px; min-height:24px; }
        button.muni-star:hover { transform:scale(1.15); }
        .muni-star--ro { cursor:default; }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-star:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); border-radius:4px; }
        .muni-star--on { color:var(--star); text-shadow:var(--muni-glow); }
    </style>
@endonce
