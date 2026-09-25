@props([
    'label' => null, // etiqueta
    'name' => null, // name e id del textarea
    'rows' => 4, // alto inicial en líneas
    'error' => null, // mensaje de error (pinta el borde en rojo)
    'hint' => null, // ayuda bajo el campo
    'required' => false, // marca el campo como obligatorio
    'maxlength' => null, // largo máximo; muestra un contador «n / max» (con Alpine)
])

{{-- Texto largo: observaciones, fundamentos de una resolución, descripción de un trámite.
     wire:model y demás atributos van al <textarea>; el contenido inicial va en el slot. --}}
@php
    $id = $attributes->get('id') ?: ($name ? 'muni-'.$name : 'muni-'.uniqid());
    $attributes = $attributes->except('id');
    $ayuda = ($error || $hint) ? $id.'-ayuda' : null;
    $describe = trim($attributes->get('aria-describedby', '').' '.$ayuda) ?: null;
    $attributes = $attributes->except('aria-describedby');
    $max = $maxlength ? (int) $maxlength : null;
@endphp

<div style="display:flex;flex-direction:column;gap:6px;" @if ($max) x-data="{ n: 0 }" x-init="n = $refs.campo.value.length" @endif>
    @if ($label)
        <label for="{{ $id }}" style="font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;color:var(--muni-text);">
            {{ $label }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
        </label>
    @endif

    <textarea
        id="{{ $id }}"
        rows="{{ (int) $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($required) required @endif
        @if ($max) maxlength="{{ $max }}" x-ref="campo" @input="n = $el.value.length" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describe) aria-describedby="{{ $describe }}" @endif
        {{ $attributes->merge([
            'class' => 'muni-textarea',
            'style' => 'width:100%;padding:10px 12px;resize:vertical;min-height:72px;line-height:1.5;'
                .'font-family:var(--muni-font-sans);font-size:13.5px;color:var(--muni-text);'
                .'background:var(--muni-surface);border:1px solid '.($error ? 'var(--muni-danger-border)' : 'var(--muni-border-strong, var(--muni-border))').';'
                .'border-radius:var(--muni-radius-sm);transition:border-color var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease);',
        ]) }}
    >{{ $slot }}</textarea>

    @if ($error || $hint || $max)
        <div style="display:flex;justify-content:space-between;gap:12px;font-size:11.5px;">
            @if ($error)
                <span id="{{ $ayuda }}" style="color:var(--muni-danger-fg);">{{ $error }}</span>
            @elseif ($hint)
                <span id="{{ $ayuda }}" style="color:var(--muni-hint);">{{ $hint }}</span>
            @else
                <span></span>
            @endif
            @if ($max)<span x-cloak x-text="n + ' / {{ $max }}'" aria-hidden="true" style="font-family:var(--muni-font-mono);color:var(--muni-hint);font-variant-numeric:tabular-nums;"></span>@endif
        </div>
    @endif
</div>

@once
    <style>
        .muni-textarea::placeholder { color: var(--muni-hint); }
        .muni-textarea:focus { outline: none; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
        .muni-textarea:disabled { background: var(--muni-surface-2); color: var(--muni-muted); cursor: not-allowed; }
    </style>
@endonce
