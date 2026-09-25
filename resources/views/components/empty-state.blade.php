@props([
    'title' => null,
    'description' => null,
    'icon' => null,
    'mode' => null,
    'filter' => null,
    'actionHref' => null,
    'actionLabel' => null,
    'announce' => false,
])

{{-- Dos vacíos que para el funcionario son opuestos:

     · `mode="no-data"` — todavía no se ha ingresado nada. La salida es crear el primero.
     · `mode="no-matches"` — el filtro no encontró nada. La salida es limpiar el filtro,
       y hay que decir QUÉ filtro estaba puesto.

     Tratarlos igual manda a buscar registros que sí existen. El componente no puede
     adivinar en cuál está —inferirlo de «hay parámetros en la URL» falla con los filtros
     por defecto—, así que el modo lo declara el host, siempre.

     Sin `mode` el componente rinde exactamente lo de antes: la ampliación es aditiva y
     los sistemas que ya lo usan no cambian de texto sin haber tocado nada. --}}

@php
    // La diferencia REAL va en el texto: el dibujo es aria-hidden y el color no puede
    // ser el único portador de información (WCAG 2.2 AA 1.4.1).
    $defaultTitles = [
        'no-data' => 'Aún no hay registros',
        'no-matches' => 'Sin coincidencias',
    ];

    $magnifier = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="24" height="24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3" stroke-linecap="round"/></svg>';
    $tray = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="24" height="24"><path d="M3 13h4l1.6 2.6h6.8L17 13h4" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.6 5h12.8l2.6 8v3.4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V13z" stroke-linejoin="round"/></svg>';

    $resolvedTitle = $title ?? ($defaultTitles[$mode] ?? 'Sin resultados');
    $resolvedIcon = $icon ?? ($mode === 'no-data' ? $tray : $magnifier);
    $resolvedActionLabel = $actionLabel ?? ($mode === 'no-data' ? 'Crear el primero' : 'Limpiar filtros');

    // Región viva SOLO si el host la pide: por defecto no se emite porque Livewire
    // inserta este nodo en el mismo repintado que trae su texto, y una región que nace
    // junto a su contenido no se anuncia en NVDA, JAWS ni VoiceOver. `announce` sirve
    // únicamente cuando el host mantiene el nodo montado entre estados; si no, el
    // anuncio va en un contador persistente (ver <x-muni::table-header>).
    $liveRegion = $announce ? ['role' => 'status', 'aria-live' => 'polite'] : [];
@endphp

<div {{ $attributes->merge(array_merge([
    'style' => 'display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:10px;padding:48px 24px;',
], $liveRegion)) }}>
    <div aria-hidden="true" style="display:grid;place-items:center;width:52px;height:52px;border-radius:14px;background:var(--muni-surface-2);border:1px solid var(--muni-border);color:var(--muni-muted);">
        {!! $resolvedIcon !!}
    </div>
    <div style="font-family:var(--muni-font-sans);font-size:15px;font-weight:700;color:var(--muni-text);">{{ $resolvedTitle }}</div>
    @if ($description)
        <p style="margin:0;max-width:44ch;font-size:13px;color:var(--muni-muted);line-height:1.5;">{{ $description }}</p>
    @endif
    @if ($filter)
        {{-- El criterio va escapado: es lo que el vecino escribió en el buscador. --}}
        <p style="margin:0;max-width:44ch;font-family:var(--muni-font-sans);font-size:13px;color:var(--muni-text);line-height:1.5;">
            Filtro aplicado: <span style="font-variant-numeric:tabular-nums;font-weight:600;">{{ $filter }}</span>
        </p>
    @endif
    {{-- El slot por defecto se rinde. Antes se descartaba en silencio; rendirlo es
         retrocompatible porque nadie puede depender de un contenido que nunca se imprimió. --}}
    @if (trim($slot) !== '')
        <div style="max-width:60ch;font-size:13px;color:var(--muni-muted);line-height:1.5;">{{ $slot }}</div>
    @endif
    @if ($actionHref || isset($actions))
        <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:6px;">
            {{-- Sin destino no se dibuja: un botón que no lleva a ninguna parte es peor
                 que no ofrecer salida. --}}
            @if ($actionHref)
                <a href="{{ $actionHref }}" class="muni-empty__action">{{ $resolvedActionLabel }}</a>
            @endif
            {{ $actions ?? '' }}
        </div>
    @endif
</div>

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css y una clase declarada únicamente en
           muni-ui.css se vería sin estilo, sin un solo error en consola. */
        .muni-empty__action { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 16px; font-family:var(--muni-font-sans); font-size:13px; font-weight:600; color:var(--muni-text); text-decoration:none; background:var(--muni-surface); border:1px solid var(--muni-field-border); border-radius:var(--muni-radius-sm); transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-empty__action:hover { background:var(--muni-surface-2); border-color:var(--muni-accent); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-empty__action:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
    </style>
@endonce
