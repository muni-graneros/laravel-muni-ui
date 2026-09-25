@props([
    'size' => 'md', // sm | md | lg
    'label' => 'Cargando…', // texto para el lector de pantalla (y visible con showLabel)
    'showLabel' => false, // muestra el texto junto al indicador
    'tone' => 'accent', // accent | neutral | ok | warn | danger | info
])

{{-- Indicador de carga. Con Livewire: wire:loading y wire:target van directo en el
     componente (p. ej. wire:loading wire:target="guardar"). Anuncia su texto como
     role=status. Con movimiento reducido gira más lento en vez de detenerse: un
     indicador quieto parece colgado. --}}
@php
    $px = ['sm' => 14, 'md' => 20, 'lg' => 32][$size] ?? 20;
    $color = \Muni\Ui\Tono::color($tone);
@endphp

<span role="status" {{ $attributes->merge(['class' => 'muni-spinner-wrap', 'style' => 'display:inline-flex;align-items:center;gap:8px;vertical-align:middle;font-family:var(--muni-font-sans);font-size:13px;color:var(--muni-muted);']) }}>
    <svg class="muni-spinner" viewBox="0 0 24 24" width="{{ $px }}" height="{{ $px }}" aria-hidden="true" style="color:{{ $color }};">
        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="3" opacity=".2"/>
        <path d="M21 12a9 9 0 00-9-9" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
    </svg>
    @if ($showLabel)
        <span>{{ $label }}</span>
    @else
        <span style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;border:0;">{{ $label }}</span>
    @endif
</span>

@once
    <style>
        .muni-spinner-wrap { position:relative; }
        .muni-spinner { flex-shrink:0; animation:muni-spin .75s linear infinite; }
        @keyframes muni-spin { to { transform:rotate(360deg); } }
        @media (prefers-reduced-motion:reduce) { .muni-spinner { animation:muni-spin 1.6s linear infinite !important; } }
    </style>
@endonce
