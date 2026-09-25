@props([
    'label' => null, // etiqueta
    'name' => null, // name del input
    'checked' => false, // estado inicial
    'description' => null, // texto de ayuda bajo la etiqueta
])

@php
    // Un `id` explícito gana: dos campos con el mismo name en la página no chocan.
    $id = $attributes->get('id') ?: ($name ? 'muni-'.$name : 'muni-'.uniqid());
    $attributes = $attributes->except('id');
    // El aspecto sale de input:checked en CSS: se ve bien sin JS y no se desincroniza
    // cuando Livewire o un reset del form cambian el checkbox.
@endphp

<label for="{{ $id }}" style="display:flex;align-items:flex-start;gap:11px;cursor:pointer;">
    <span style="position:relative;flex-shrink:0;margin-top:1px;">
        <input type="checkbox" role="switch" id="{{ $id }}" @if ($name) name="{{ $name }}" @endif @checked($checked)
               {{ $attributes }}
               style="position:absolute;opacity:0;width:0;height:0;">
        <span class="muni-switch" aria-hidden="true">
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
        .muni-switch { display:inline-block; width:38px; height:22px; border-radius:999px; background:var(--muni-surface-3); border:1px solid var(--muni-border-strong, var(--muni-border)); transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-switch__thumb { display:block; width:16px; height:16px; margin:2px; border-radius:50%; background:var(--muni-surface); box-shadow:0 1px 3px rgba(0,0,0,.25); transition:transform var(--muni-dur) var(--muni-ease); }
        .muni-switch--on, input:checked + .muni-switch { background:var(--muni-accent); border-color:var(--muni-accent); }
        .muni-switch--on .muni-switch__thumb, input:checked + .muni-switch .muni-switch__thumb { transform:translateX(16px); }
        input:disabled + .muni-switch { opacity:.5; cursor:not-allowed; }
        input:focus-visible + .muni-switch { box-shadow:var(--muni-ring); }
    </style>
@endonce
