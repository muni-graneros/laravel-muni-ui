@props([
    'align' => 'end',
    'width' => '220px',
    /*
     * Nombre accesible del disparador cuando el slot `trigger` no trae texto
     * propio. Un <button> vacío es «button-name» en axe, impacto critical.
     */
    'label' => 'Abrir menú',
])

@php
    $origin = $align === 'start' ? 'left:0;' : 'right:0;';

    /*
     * El id del menú NO se inventa acá: nada de uniqid() (DESIGN §10), que en
     * cada render devuelve otro y deja el aria-controls apuntando a un
     * elemento que ya no existe. Si el consumidor pasa un `id`, cae en el menú
     * por la bolsa de atributos y el disparador lo apunta; si no, se omite
     * aria-controls, que en el patrón de botón-menú es opcional.
     */
    $muniMenuId = trim((string) $attributes->get('id', ''));

    /*
     * ¿El slot `trigger` ya trae su propio control? Hasta ahora TENÍA que
     * traerlo: el envoltorio era un <div>, que no recibe foco, así que sin un
     * botón propio el menú no se abría con teclado. Envolver ese botón en el
     * nuestro cambiaría un fallo critical de axe (aria-allowed-attr) por otro
     * (nested-interactive) y dejaría el orden de tabulación sin dueño.
     */
    $muniMarcaDisparador = isset($trigger) ? trim((string) $trigger) : '';
    $muniDisparadorPropio = $muniMarcaDisparador !== ''
        && preg_match('/<(?:button|a|input|select|textarea|summary|label)\b/i', $muniMarcaDisparador) === 1;
@endphp

{{-- Menú desplegable (Alpine 3). El slot `trigger` es el contenido del botón
     —texto y, si acaso, un icono—; el slot por defecto son los ítems (usar
     <x-muni::dropdown-item>). Cierra al hacer click fuera o con Escape.

     EL DISPARADOR ES UN <button> DE VERDAD, como en <x-muni::popover>.
     `aria-expanded` y `aria-haspopup` solo son válidos sobre un elemento cuyo
     rol los admita, y el rol implícito de un <div> es `generic`, que no admite
     ninguno de los dos: axe lo reprueba como aria-allowed-attr con impacto
     critical. Un <button> trae además foco, Enter y Espacio sin una línea de
     JS; el <div> de antes no recibía foco y el teclado dependía de que el host
     pusiera su propio control en el slot.

     RETROCOMPATIBILIDAD: si ese control propio viene igual, no se envuelve.
     Ahí los atributos no pueden salir del servidor —el componente no puede
     escribir dentro del slot—, así que los pone Alpine sobre el control REAL,
     que sí tiene un rol que los admite, y nunca sobre el envoltorio, que no lo
     tiene. `sincronizaAria` se explica acá y no dentro del x-data a propósito:
     este comentario lo borra el compilador de Blade, mientras que todo lo que
     se escriba dentro del atributo viaja al navegador en cada render. --}}
<div
    x-data="{
        open: false,

        /* Ver el comentario de arriba: pone el ARIA sobre el control del slot. */
        sincronizaAria(envoltorio) {
            const control = envoltorio && envoltorio.querySelector('button, [role=\'button\'], a[href], summary');
            if (! control) { return; }
            control.setAttribute('aria-haspopup', 'menu');
            control.setAttribute('aria-expanded', this.open ? 'true' : 'false');
        },
    }"
    @keydown.escape.window="open = false"
    style="position:relative;display:inline-block;"
>
    @if ($muniDisparadorPropio)
        <span class="muni-dd__envoltorio" @click="open = ! open" x-effect="sincronizaAria($el)">
            {{ $trigger }}
        </span>
    @else
        <button
            type="button"
            class="muni-dd__disparador"
            @click="open = ! open"
            aria-haspopup="menu"
            {{-- El valor inicial viaja en el HTML del servidor y Alpine solo lo
                 mantiene al día: sin JS el lector igual anuncia un menú cerrado. --}}
            aria-expanded="false"
            x-bind:aria-expanded="open"
            @if ($muniMenuId !== '') aria-controls="{{ $muniMenuId }}" @endif
        >
            @if ($muniMarcaDisparador !== '')
                {{ $trigger }}
            @else
                {{-- Sin slot `trigger` el botón sigue existiendo, enfocable y con
                     nombre: un desplegable sin etiqueta es un hueco visual, no un
                     error 500 ni un botón mudo. --}}
                <span>{{ $label }}</span>
            @endif
        </button>
    @endif

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        x-transition:enter="muni-dd-enter"
        x-transition:enter-start="muni-dd-enter-start"
        x-transition:enter-end="muni-dd-enter-end"
        role="menu"
        {{ $attributes->merge([
            'style' => "position:absolute;top:calc(100% + 6px);{$origin}z-index:50;min-width:{$width};"
                ."padding:5px;background:var(--muni-surface);border:1px solid var(--muni-border);"
                ."border-radius:var(--muni-radius);box-shadow:var(--muni-shadow-lg);"
                ."transform-origin:top ".($align === 'start' ? 'left' : 'right').";",
        ]) }}
    >
        {{ $slot }}
    </div>
</div>

@once
    <style>
        .muni-dd-enter { transition: opacity var(--muni-dur) var(--muni-ease), transform var(--muni-dur) var(--muni-ease); }
        .muni-dd-enter-start { opacity: 0; transform: scale(0.96) translateY(-4px); }
        .muni-dd-enter-end { opacity: 1; transform: scale(1) translateY(0); }

        /* El disparador propio del componente. Se le quita el cromo del agente
           de usuario para que el slot siga mandando en el aspecto, pero NO el
           foco ni la semántica, que son la razón de que sea un botón.
           Área táctil por encima del mínimo de 24x24 (WCAG 2.2 AA, 2.5.8). */
        .muni-dd__disparador {
            display: inline-flex; align-items: center; gap: 6px;
            min-height: 24px; min-width: 24px;
            margin: 0; padding: 0;
            font: inherit; font-family: var(--muni-font-sans);
            color: var(--muni-text); background: transparent;
            border: 0; border-radius: var(--muni-radius-sm);
            text-align: left; cursor: pointer;
        }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-dd__disparador:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; }

        /* Envoltorio del disparador que trae el consumidor: no pinta nada y no
           lleva rol ni atributos ARIA a propósito. Solo escucha el click. */
        .muni-dd__envoltorio { display: inline-flex; }
    </style>
@endonce
