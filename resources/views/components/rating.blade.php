@props([
    'value' => 0,
    'max' => 5,
    'readonly' => false,
    'name' => null,
    'tone' => 'accent',
])

@php
    $color = ['accent' => 'var(--muni-accent)', 'warn' => 'var(--muni-warn-fg)', 'ok' => 'var(--muni-ok-fg)'][$tone] ?? 'var(--muni-accent)';
@endphp

<div
    x-data="{ value: {{ (float) $value }}, hover: 0, readonly: {{ $readonly ? 'true' : 'false' }} }"
    {{ $attributes->merge(['style' => 'display:inline-flex;align-items:center;gap:3px;']) }}
    role="radiogroup"
>
    @if ($name)<input type="hidden" name="{{ $name }}" :value="value">@endif
    @for ($i = 1; $i <= (int) $max; $i++)
        <button
            type="button"
            @if (! $readonly)
                @click="value = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0"
            @endif
            :aria-checked="value >= {{ $i }}"
            class="muni-star"
            :class="(hover || value) >= {{ $i }} && 'muni-star--on'"
            style="--star:{{ $color }};{{ $readonly ? 'cursor:default;' : '' }}"
            aria-label="{{ $i }} de {{ $max }}"
        >★</button>
    @endfor
</div>

@once
    <style>
        .muni-star { background:none; border:none; padding:0 1px; font-size:20px; line-height:1; color:var(--muni-border-2); cursor:pointer; transition:color var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease); }
        .muni-star:hover { transform:scale(1.15); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-star:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); border-radius:4px; }
        .muni-star--on { color:var(--star); text-shadow:var(--muni-glow); }
    </style>
@endonce
