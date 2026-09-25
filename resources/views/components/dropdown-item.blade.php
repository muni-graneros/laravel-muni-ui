@props([
    'href' => null, // con URL es un <a>; sin ella, un <button>
    'icon' => null, // SVG crudo decorativo (nunca datos de usuario)
    'tone' => 'default', // default | danger
])

@php
    $tag = $href ? 'a' : 'button';
    $color = $tone === 'danger' ? 'var(--muni-danger-fg)' : 'var(--muni-text)';
@endphp

{{-- ROVING TABINDEX: el ítem nace fuera del orden de tabulación y el
     desplegable pasa a 0 solo el activo. Va por ->merge() y no escrito a mano,
     para que un tabindex del consumidor no quede duplicado en la etiqueta.

     Al activarlo, el menú se cierra con `cerrar()` del desplegable, que
     devuelve el foco al disparador: sin eso, Enter sobre una acción deja el
     foco en un ítem oculto y el lector vuelve al principio del documento. Un
     enlace no lo devuelve —la navegación decide adónde va el foco, y un ancla
     de la misma página quedaría pisada por el scroll al disparador—. Si el
     ítem se usa dentro de un x-data propio que solo tiene `open`, se mantiene
     el contrato de siempre. --}}
<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="button" @endif
    role="menuitem"
    @click="typeof cerrar === 'function' ? cerrar({{ $href ? 'false' : 'true' }}) : (open = false)"
    {{ $attributes->merge([
        'tabindex' => '-1',
        'class' => 'muni-dd-item',
        'style' => "display:flex;align-items:center;gap:9px;width:100%;padding:8px 10px;"
            ."font-family:var(--muni-font-sans);font-size:13px;font-weight:500;text-align:left;"
            ."color:{$color};background:transparent;border:none;border-radius:var(--muni-radius-sm);"
            ."cursor:pointer;text-decoration:none;transition:background var(--muni-dur) var(--muni-ease);",
    ]) }}
>
    @if ($icon)<span aria-hidden="true" style="display:inline-flex;width:15px;height:15px;opacity:.75;">{!! $icon !!}</span>@endif
    {{ $slot }}
</{{ $tag }}>

@once
    <style>
        .muni-dd-item:hover { background: var(--muni-surface-2); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-dd-item:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: -2px; box-shadow: var(--muni-ring); }
        .muni-dd-item svg { width: 15px; height: 15px; }
    </style>
@endonce
