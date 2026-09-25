@props([
    /* Rótulo del grupo. Obligatorio: es el nombre accesible del disclosure y la
       semilla del id, así que sin él el componente no tiene contrato. */
    'titulo', // rótulo del grupo, obligatorio
    /* Estado INICIAL, decidido en el servidor por quien sabe qué página se está
       mirando (en `nav-menu`, el propio menú). Ver la nota de abajo. */
    'abierto' => false, // estado inicial del grupo, decidido en el servidor
    /* SVG en crudo, igual que `nav-item`: viene del layout del anfitrión, nunca
       de datos de usuario. */
    'icono' => null, // SVG crudo del layout, nunca datos de usuario
])

@php
    /*
     * LOS IDS SALEN DEL TÍTULO, NUNCA DE uniqid().
     *
     * Un id que cambia en cada render rompe el `aria-controls` en cuanto
     * Livewire repinta un trozo y ensucia el diffing. Se sanea a [A-Za-z0-9_-]
     * y se le pega un trozo de sha1 del título completo para que dos grupos con
     * rótulos que se sanean igual («Cámaras / Rondas» y «Cámaras - Rondas») no
     * colisionen. El consumidor puede imponer el suyo con `id=...`, y `nav-menu`
     * lo hace para que dos menús en la misma página no se pisen.
     */
    $base = $attributes->get('id')
        ?: 'muni-navg-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $titulo), '-')
            .'-'.substr(sha1((string) $titulo), 0, 6);

    $btnId = $base.'-btn';
    $panelId = $base.'-panel';
    $estaAbierto = (bool) $abierto;
@endphp

{{-- Grupo plegable de ítems de menú. Patrón DISCLOSURE, no `menu`: son enlaces,
     así que no hay flechas ni roving tabindex. Tab llega al disparador, Enter y
     Espacio lo activan —gratis, porque es un <button> de verdad— y el contenido
     plegado NO recibe Tab.

     EL ESTADO INICIAL SE DECIDE EN EL SERVIDOR. Si el grupo que contiene la
     página actual se abriera recién cuando Alpine monta, en la primera pintura
     el ítem activo está oculto y con wire:navigate se ve el salto. Por eso
     `abierto` es una prop de PHP: el HTML llega ya decidido y Alpine solo
     alterna después.

     UN SOLO DATO MANDA: `abierto`. De él salen a la vez el `aria-expanded` del
     botón y la clase con la que el CSS abre el panel, en el servidor y en Alpine,
     así que no se pueden desincronizar. La clase va en SINTAXIS DE OBJETO
     (`:class="{ … }"`) y no en cadena: la forma de cadena no sabe quitar una
     clase que ya venía puesta desde el servidor y el grupo se quedaba abierto
     para siempre. (El CSS no puede seleccionar por `[aria-expanded]` acá: el
     candado de `MenuDesplegableAccesibleTest` lee el fuente en crudo y un
     selector así se le parece a un atributo sobre el `<style>`.)

     PLEGADO NO ES SOLO INVISIBLE. `grid-template-rows:0fr` esconde pero no saca
     del orden de tabulación: con Tab el teclado entraba a enlaces invisibles
     (WCAG 2.2 AA 2.4.3 y 2.4.7). Se cierra con `inert` —que además lo saca del
     árbol de accesibilidad— y con `visibility:hidden` de respaldo para los
     navegadores viejos del municipio. El atributo estático es el que vale sin
     JS; la atadura de Alpine lo mantiene después.

     NINGÚN TEXTO DEL ANFITRIÓN ENTRA EN UNA EXPRESIÓN DE ALPINE: el `x-data`
     solo lleva un booleano. Un apóstrofo en «Cámaras del Río O'Higgins» dentro
     de una expresión tumba el Alpine de la página entera, que es lo que ya pasó
     en file-dropzone. --}}
<div
    x-data="{ abierto: @js($estaAbierto) }"
    :class="{ 'muni-navg--abierto': abierto }"
    {{ $attributes->merge(['class' => 'muni-navg'.($estaAbierto ? ' muni-navg--abierto' : ''), 'id' => $base]) }}
>
    <button
        type="button"
        id="{{ $btnId }}"
        class="muni-navg__btn"
        aria-controls="{{ $panelId }}"
        aria-expanded="{{ $estaAbierto ? 'true' : 'false' }}"
        :aria-expanded="abierto ? 'true' : 'false'"
        @click="abierto = ! abierto"
    >
        @if ($icono)<span aria-hidden="true" class="muni-navg__icono">{!! $icono !!}</span>@endif
        <span class="muni-navg__rotulo">{{ $titulo }}</span>
        <svg class="muni-navg__chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14" aria-hidden="true"><path d="M7 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <div
        id="{{ $panelId }}"
        class="muni-navg__panel"
        role="group"
        aria-labelledby="{{ $btnId }}"
        @if (! $estaAbierto) inert @endif
        :inert="! abierto"
    >
        <div class="muni-navg__inner">{{ $slot }}</div>
    </div>
</div>

@once
    <style>
        .muni-navg { display:flex; flex-direction:column; }
        .muni-navg__btn { display:flex; align-items:center; gap:11px; width:100%; min-height:36px; padding:9px 11px; border:none;
            border-radius:var(--muni-radius-sm); background:transparent; cursor:pointer; text-align:left;
            font-family:var(--muni-font-sans); font-size:13.5px; font-weight:500; color:var(--muni-muted);
            transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-navg__btn:hover { background:var(--muni-surface-2); color:var(--muni-text); }
        {{-- El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). --}}
        .muni-navg__btn:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; box-shadow:var(--muni-ring); }
        .muni-navg--abierto > .muni-navg__btn { color:var(--muni-text); }
        .muni-navg__icono { display:inline-flex; flex-shrink:0; width:18px; height:18px; }
        .muni-navg__icono svg { width:18px; height:18px; }
        .muni-navg__rotulo { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .muni-navg__chevron { flex-shrink:0; transition:transform var(--muni-dur) var(--muni-ease); }
        .muni-navg--abierto .muni-navg__chevron { transform:rotate(90deg); }
        {{-- 0fr → 1fr anima sin medir alturas en JS (cuatro reflujos por apertura
             serían inaceptables para el INP). `visibility` es el respaldo de
             `inert` en navegadores viejos, y se retrasa hasta el final del
             deslizamiento con --muni-dur, que ya baja a 0 ms con movimiento
             reducido. --}}
        .muni-navg__panel { display:grid; grid-template-rows:0fr; visibility:hidden;
            transition:grid-template-rows var(--muni-dur) var(--muni-ease),visibility 0s linear var(--muni-dur); }
        .muni-navg--abierto > .muni-navg__panel { grid-template-rows:1fr; visibility:visible;
            transition:grid-template-rows var(--muni-dur) var(--muni-ease),visibility 0s; }
        {{-- `overflow:hidden` acá y no en el panel: sin él, enfocar un enlace a
             medio desplegar provoca scroll fantasma. `min-height:0` es lo que
             deja al hijo encogerse dentro del grid. --}}
        .muni-navg__inner { overflow:hidden; min-height:0; display:flex; flex-direction:column; gap:2px; }
    </style>
@endonce
