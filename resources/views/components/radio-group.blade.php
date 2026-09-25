@props([
    'label' => null, // pregunta del grupo (legend)
    'name' => null, // name compartido de los radios
    'options' => [], // mapa valor => etiqueta, o valor => ['label' => , 'description' => ?]
    'value' => null, // valor marcado al cargar
    'error' => null, // mensaje de error
    'hint' => null, // ayuda bajo la pregunta
    'inline' => false, // opciones en fila en vez de columna
    'required' => false, // exige elegir una opción
])

{{-- Grupo de radios nativos en un fieldset con legend. wire:model / x-model se reenvían
     a cada radio. Para 2–4 opciones cortas que filtran una vista, segmented. --}}
@php
    $prefijo = $attributes->get('id') ?: ($name ?: 'muni-radio-'.uniqid());
    $modelo = $attributes->filter(fn ($v, $k) => str_starts_with($k, 'wire:model') || str_starts_with($k, 'x-model'));
    $attributes = $attributes->filter(fn ($v, $k) => $k !== 'id' && ! str_starts_with($k, 'wire:model') && ! str_starts_with($k, 'x-model'));
    $ayuda = ($error || $hint) ? $prefijo.'-ayuda' : null;
    $describe = trim($attributes->get('aria-describedby', '').' '.$ayuda) ?: null;
    $attributes = $attributes->except('aria-describedby');
@endphp

<fieldset @if ($describe) aria-describedby="{{ $describe }}" @endif
    {{ $attributes->merge(['class' => 'muni-radios', 'style' => 'margin:0;padding:0;border:0;min-width:0;display:flex;flex-direction:column;gap:8px;font-family:var(--muni-font-sans);']) }}>
    @if ($label)
        <legend style="padding:0;margin-bottom:8px;font-size:12.5px;font-weight:600;color:var(--muni-text);">
            {{ $label }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
        </legend>
    @endif
    @if ($hint && ! $error)<span id="{{ $ayuda }}" style="font-size:11.5px;color:var(--muni-hint);margin-top:-6px;">{{ $hint }}</span>@endif
    <div style="display:flex;flex-direction:{{ $inline ? 'row' : 'column' }};flex-wrap:wrap;gap:{{ $inline ? '8px 20px' : '10px' }};">
        @foreach ($options as $val => $opcion)
            @php
                $texto = is_array($opcion) ? ($opcion['label'] ?? $val) : $opcion;
                $detalle = is_array($opcion) ? ($opcion['description'] ?? null) : null;
                $rid = $prefijo.'-'.$loop->index;
            @endphp
            <label for="{{ $rid }}" style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                <input type="radio" id="{{ $rid }}" value="{{ $val }}" @if ($name) name="{{ $name }}" @endif
                       @checked((string) $value === (string) $val) @if ($required) required @endif
                       @if ($error) aria-invalid="true" @endif class="muni-radio" {{ $modelo }}>
                <span style="display:flex;flex-direction:column;gap:2px;">
                    <span style="font-size:13.5px;color:var(--muni-text);line-height:1.35;">{{ $texto }}</span>
                    @if ($detalle)<span style="font-size:12px;color:var(--muni-muted);">{{ $detalle }}</span>@endif
                </span>
            </label>
        @endforeach
    </div>
    @if ($error)<span id="{{ $ayuda }}" style="font-size:11.5px;color:var(--muni-danger-fg);">{{ $error }}</span>@endif
</fieldset>

@once
    <style>
        .muni-radio { appearance:none; flex-shrink:0; width:18px; height:18px; margin:1px 0 0; display:grid; place-content:center; cursor:pointer;
            background:var(--muni-surface); border:1.5px solid var(--muni-border-strong, var(--muni-border-2)); border-radius:50%;
            transition:border-color var(--muni-dur) var(--muni-ease); }
        .muni-radio::after { content:""; width:8px; height:8px; border-radius:50%; background:var(--muni-accent); transform:scale(0); transition:transform var(--muni-dur) var(--muni-ease); }
        .muni-radio:checked { border-color:var(--muni-accent); }
        .muni-radio:checked::after { transform:scale(1); }
        .muni-radio[aria-invalid=true] { border-color:var(--muni-danger-fg); }
        .muni-radio:focus-visible { outline:none; box-shadow:var(--muni-ring); }
        .muni-radio:disabled { opacity:.5; cursor:not-allowed; }
    </style>
@endonce
