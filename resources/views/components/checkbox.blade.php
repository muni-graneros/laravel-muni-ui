@props([
    'label' => null, // texto junto a la casilla
    'name' => null, // name del input
    'value' => '1', // valor que se envía marcada
    'checked' => false, // estado inicial
    'description' => null, // texto de ayuda bajo la etiqueta
    'error' => null, // mensaje de error (p. ej. «Debes aceptar los términos»)
])

{{-- Casilla nativa con la marca pintada en CSS (:checked), así se ve igual sin JS y
     con wire:model, que va directo al input. Para on/off de una preferencia, switch. --}}
@php
    // Hash del valor y no slug: «Sí» y «si», o «+» y «-», darían el mismo id y un label
    // marcaría la casilla de otro.
    $id = $attributes->get('id') ?: ($name ? 'muni-'.$name.'-'.substr(md5((string) $value), 0, 8) : 'muni-'.uniqid());
    $attributes = $attributes->except('id');
    $ayuda = ($error || $description) ? $id.'-ayuda' : null;
    $describe = trim($attributes->get('aria-describedby', '').' '.$ayuda) ?: null;
    $attributes = $attributes->except('aria-describedby');
@endphp

<div style="display:flex;flex-direction:column;gap:4px;">
    <label for="{{ $id }}" style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-family:var(--muni-font-sans);">
        <input type="checkbox" id="{{ $id }}" value="{{ $value }}" @if ($name) name="{{ $name }}" @endif @checked($checked)
               @if ($error) aria-invalid="true" @endif @if ($describe) aria-describedby="{{ $describe }}" @endif
               {{ $attributes->merge(['class' => 'muni-check']) }}>
        <span style="display:flex;flex-direction:column;gap:2px;min-width:0;">
            <span style="font-size:13.5px;color:var(--muni-text);line-height:1.35;">{{ $label ?? $slot }}</span>
            @if ($description && ! $error)<span id="{{ $ayuda }}" style="font-size:12px;color:var(--muni-muted);">{{ $description }}</span>@endif
            @if ($error)<span id="{{ $ayuda }}" style="font-size:11.5px;color:var(--muni-danger-fg);">{{ $error }}</span>@endif
        </span>
    </label>
</div>

@once
    <style>
        .muni-check { appearance:none; flex-shrink:0; width:18px; height:18px; margin:1px 0 0; display:grid; place-content:center; cursor:pointer;
            background:var(--muni-surface); border:1.5px solid var(--muni-border-strong, var(--muni-border-2)); border-radius:5px;
            transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-check::after { content:""; width:10px; height:10px; transform:scale(0); transition:transform var(--muni-dur) var(--muni-ease);
            background:var(--muni-on-accent); clip-path:polygon(14% 44%,0 65%,50% 100%,100% 16%,80% 0%,43% 62%); }
        .muni-check:checked { background:var(--muni-accent); border-color:var(--muni-accent); }
        .muni-check:checked::after { transform:scale(1); }
        .muni-check[aria-invalid=true] { border-color:var(--muni-danger-fg); }
        .muni-check:focus-visible { outline:none; box-shadow:var(--muni-ring); }
        .muni-check:disabled { opacity:.5; cursor:not-allowed; }
    </style>
@endonce
