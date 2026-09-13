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

    /*
     * Milisegundos durante los que un cierre no suelta la trampa de foco en el
     * acto. Alpine la activa 15 ms después de abrir; el margen cubre la
     * imprecisión de los temporizadores con la pestaña cargada.
     */
    $muniEsperaTrampa = 60;
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
     se escriba dentro del atributo viaja al navegador en cada render.

     PATRÓN MENU BUTTON DE LA APG, no disclosure: `role="menu"` promete un
     teclado concreto y el componente lo cumple. Abrir (click, Enter, Espacio
     o ↓) deja el foco en el primer ítem; ↑ lo deja en el último. Dentro del
     menú ↑/↓ recorren en círculo, Home/End saltan a los extremos, y Escape y
     Tab cierran y devuelven el foco al disparador. Tab no avanza al siguiente
     control de la página: la trampa de foco ya consumió la tecla, así que el
     foco vuelve al disparador y el Tab siguiente sigue desde ahí.

     ROVING TABINDEX. Los ítems nacen con tabindex="-1" desde el servidor y solo
     el activo pasa a 0: un menú es UNA parada de Tab, no diez. Al cerrar todos
     vuelven a -1, así la trampa del próximo despliegue no encuentra un ítem
     viejo con 0 y no lo enfoca antes que el que toca.

     ESCAPE VA EN EL ELEMENTO Y NO EN WINDOW. `command-palette` y `modal` lo
     escuchan en window; enlazado también acá por instancia, un Escape cerraba
     todos los desplegables y el modal de fondo a la vez. Se escucha en la raíz
     del componente —cubre el menú y también el disparador, por si Escape llega
     antes de que el foco alcance el primer ítem— y solo con el menú abierto
     detiene la propagación: dentro de un modal, Escape cierra primero el menú y
     deja el modal abierto; con el menú cerrado, la tecla sigue su camino.

     LA TRAMPA DE FOCO Y CUÁNDO SE SUELTA. `x-trap` (plugin Focus, viaja en
     Livewire) intercepta cualquier foco que salga del menú mientras está
     activa, así que el foco NUNCA se devuelve en el mismo instante en que se
     cierra: primero se suelta la trampa y recién en el $nextTick siguiente se
     enfoca el destino; enfocarlo antes hace que la trampa lo devuelva al ítem
     ya oculto y el foco cae al <body>.
     La trampa va atada a `atrapar` y no a `open` por una carrera medida en
     Chromium y Firefox: Alpine activa la trampa 15 ms DESPUÉS de que la
     expresión pasa a verdadero, y no cancela ese temporizador si vuelve a
     falso. Un Escape entre los 14 y los 28 ms de abrir dejaba el foco en el
     <body>. Por eso, si el menú se cierra antes de `muniEsperaTrampa` ms, la
     trampa se suelta cuando ya se activó y el foco se devuelve después.
     `.noreturn` es deliberado: la devolución la decide el componente (Escape,
     Tab, activar un ítem y volver a pulsar el disparador sí; click fuera no,
     ahí el foco va adonde se hizo click). Por la misma razón el menú se cierra
     en `focusout` cuando el foco se va a otro control de la página: si no, la
     trampa lo retiene y el campo donde el usuario hizo click no recibe el foco.
     Cuando ese foco lo mueve un script (`.focus()` desde el host) la trampa
     alcanza a devolverlo al ítem antes de soltarse, así que el destino se
     vuelve a enfocar al soltarla. --}}
<div
    x-data="{
        open: false,
        atrapar: false,
        abiertoDesde: 0,
        activo: 0,

        /* Ver el comentario de arriba: pone el ARIA sobre el control del slot. */
        sincronizaAria(envoltorio) {
            const control = envoltorio && envoltorio.querySelector('button, [role=\'button\'], a[href], summary');
            if (! control) { return; }
            control.setAttribute('aria-haspopup', 'menu');
            control.setAttribute('aria-expanded', this.open ? 'true' : 'false');
        },

        disparador() {
            if (this.$refs.disparador) { return this.$refs.disparador; }
            const envoltorio = this.$refs.envoltorio;
            return envoltorio ? envoltorio.querySelector('button, [role=\'button\'], a[href], summary') : null;
        },

        items() {
            const menu = this.$refs.menu;
            return menu ? Array.from(menu.querySelectorAll('[role=menuitem]:not([disabled]):not([aria-disabled=true])')) : [];
        },

        marcar(indice) {
            const items = this.items();
            if (! items.length) { return null; }
            const i = ((indice % items.length) + items.length) % items.length;
            items.forEach((item, n) => item.setAttribute('tabindex', n === i ? '0' : '-1'));
            this.activo = i;
            return items[i];
        },

        enfocar(indice) {
            const item = this.marcar(indice);
            if (item) { item.focus(); }
        },

        mover(paso) {
            const actual = this.items().indexOf(document.activeElement);
            this.enfocar((actual < 0 ? this.activo : actual) + paso);
        },

        abrir(indice) {
            const item = this.marcar(indice);
            this.open = true;
            this.atrapar = true;
            this.abiertoDesde = Date.now();
            this.$nextTick(() => { if (item && this.open) { item.focus(); } });
        },

        cerrar(destino) {
            if (! this.open) { return; }
            this.open = false;
            this.items().forEach((item) => item.setAttribute('tabindex', '-1'));
            const control = destino === true ? this.disparador() : destino;
            const soltar = () => {
                if (this.open) { return; }
                this.atrapar = false;
                if (! control) { return; }
                this.$nextTick(() => { if (control.isConnected && document.activeElement !== control) { control.focus(); } });
            };
            const espera = {{ $muniEsperaTrampa }} - (Date.now() - this.abiertoDesde);
            if (espera > 0) { setTimeout(soltar, espera); } else { soltar(); }
        },

        alternar() {
            if (this.open) { this.cerrar(true); } else { this.abrir(0); }
        },

        alPerderElFoco(evento) {
            const destino = evento.relatedTarget;
            if (! this.open || ! destino) { return; }
            const origen = this.$refs.disparador || this.$refs.envoltorio;
            if (this.$refs.menu.contains(destino) || (origen && origen.contains(destino))) { return; }
            this.cerrar(destino);
        },

        alEscape(evento) {
            if (! this.open) { return; }
            evento.preventDefault();
            evento.stopPropagation();
            this.cerrar(true);
        },
    }"
    @keydown.escape="alEscape($event)"
    style="position:relative;display:inline-block;"
>
    @if ($muniDisparadorPropio)
        <span
            class="muni-dd__envoltorio"
            x-ref="envoltorio"
            @click="alternar()"
            @keydown.down.prevent="abrir(0)"
            @keydown.up.prevent="abrir(-1)"
            x-effect="sincronizaAria($el)"
        >
            {{ $trigger }}
        </span>
    @else
        <button
            type="button"
            class="muni-dd__disparador"
            x-ref="disparador"
            @click="alternar()"
            @keydown.down.prevent="abrir(0)"
            @keydown.up.prevent="abrir(-1)"
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
        x-ref="menu"
        x-trap.noreturn="atrapar"
        @click.outside="cerrar(false)"
        @focusout="alPerderElFoco($event)"
        @keydown.down.prevent="mover(1)"
        @keydown.up.prevent="mover(-1)"
        @keydown.home.prevent="enfocar(0)"
        @keydown.end.prevent="enfocar(-1)"
        @keydown.tab.prevent="cerrar(true)"
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
