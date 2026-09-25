@props([
    'value' => 0, // valor inicial
    'max' => 5, // cantidad de estrellas
    'readonly' => false, // solo lectura
    'name' => null, // name del input oculto
    'tone' => 'accent', // accent | ok | warn
])

@php
    $color = \Muni\Ui\Tono::color($tone);
    $max = max(1, (int) $max);
    $value = max(0, min($max, (float) $value));
    $sel = (int) round($value);
    $texto = rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',').' de '.$max;
@endphp

{{-- Solo lectura: una imagen con nombre («4 de 5»), sin foco ni hover. Interactivo:
     radiogroup de botones con role=radio para el lector de pantalla; flechas ←/→ cambian el valor.
     Las estrellas marcadas se pintan desde el servidor, así se ven sin JS.
     wire:model / x-model en el componente enlazan `value` (x-modelable). --}}
@if ($readonly)
    <div role="img" aria-label="{{ $attributes->get('aria-label', 'Calificación: '.$texto) }}"
         {{ $attributes->except('aria-label')->merge(['style' => 'display:inline-flex;align-items:center;gap:3px;']) }}>
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
        role="radiogroup" aria-label="{{ $attributes->get('aria-label', 'Calificación') }}"
        {{ $attributes->except('aria-label')->merge(['style' => 'display:inline-flex;align-items:center;gap:3px;']) }}
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
        /* Sin marcar: borde fuerte (≥3:1). Los botones miden al menos 24×24 (WCAG 2.5.8); el glifo sigue en 20px. */
        .muni-star { background:none; border:none; padding:0 1px; font-size:20px; line-height:1; color:var(--muni-border-strong, var(--muni-border-2)); cursor:pointer; transition:color var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease); }
        button.muni-star { display:inline-flex; align-items:center; justify-content:center; min-width:24px; min-height:24px; }
        button.muni-star:hover { transform:scale(1.15); }
        .muni-star--ro { cursor:default; }
        .muni-star:focus-visible { outline:none; box-shadow:var(--muni-ring); border-radius:4px; }
        .muni-star--on { color:var(--star); text-shadow:var(--muni-glow); }
    </style>
@endonce
