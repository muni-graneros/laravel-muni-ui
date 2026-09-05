@props([
    'label' => null,
    'name' => null,
    'checked' => false,
    'description' => null,
])

@php $id = $name ? 'muni-'.$name : 'muni-'.uniqid(); @endphp

<label for="{{ $id }}" style="display:flex;align-items:flex-start;gap:11px;cursor:pointer;">
    <span x-data="{ on: {{ $checked ? 'true' : 'false' }} }" style="position:relative;flex-shrink:0;margin-top:1px;">
        <input type="checkbox" id="{{ $id }}" @if ($name) name="{{ $name }}" @endif @checked($checked)
               x-model="on" {{ $attributes }}
               style="position:absolute;opacity:0;width:0;height:0;">
        <span class="muni-switch" :class="on && 'muni-switch--on'">
            <span class="muni-switch__thumb"></span>
        </span>
    </span>
    @if ($label || $description)
        <span style="display:flex;flex-direction:column;gap:1px;">
            @if ($label)<span style="font-family:var(--muni-font-sans);font-size:13.5px;font-weight:600;color:var(--muni-text);">{{ $label }}</span>@endif
            @if ($description)<span style="font-size:12px;color:var(--muni-muted);line-height:1.4;">{{ $description }}</span>@endif
        </span>
    @endif
</label>

@once
    <style>
        .muni-switch { display:inline-block; width:38px; height:22px; border-radius:999px; background:var(--muni-surface-3); border:1px solid var(--muni-border); transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-switch__thumb { display:block; width:16px; height:16px; margin:2px; border-radius:50%; background:var(--muni-surface); box-shadow:0 1px 3px rgba(0,0,0,.25); transition:transform var(--muni-dur) var(--muni-ease); }
        .muni-switch--on { background:var(--muni-accent); border-color:var(--muni-accent); }
        .muni-switch--on .muni-switch__thumb { transform:translateX(16px); }
        /* El <input> real va con opacity:0 y 0×0, así que el contorno nativo del
           navegador no se ve: el ÚNICO indicador de foco era esta box-shadow, y la
           box-shadow se pierde dentro del panel de Filament (ver --muni-focus). */
        input:focus-visible + .muni-switch { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
    </style>
@endonce
