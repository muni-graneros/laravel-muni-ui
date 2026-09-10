@props([
    /*
     * Base de los ids. Sale de una prop y NUNCA de uniqid() (DESIGN §10): un id
     * que cambia en cada render deja el popovertarget y el aria-controls
     * apuntando a un elemento que ya no existe, y ensucia el diffing de
     * Livewire. Si no se pasa, se deriva de la etiqueta —estable entre renders
     * mientras la prop no cambie—. Dos paneles con la MISMA etiqueta en una
     * página necesitan que el consumidor pase su propio `id`.
     */
    'id' => null,
    'label' => 'Filtros',
    /* Borde del disparador contra el que se alinea el panel: start o end. */
    'align' => 'end',
    'width' => '320px',
    'closeLabel' => 'Cerrar',
])

@php
    $etiqueta = trim((string) $label) !== '' ? trim((string) $label) : 'Abrir panel';
    $alineacion = in_array($align, ['start', 'end'], true) ? $align : 'end';

    /*
     * `id` está declarado como prop, así que Blade lo saca de la bolsa de
     * atributos: hay que leerlo de la variable y no de $attributes->get('id'),
     * que acá vale null siempre. Se mira igual la bolsa por si un consumidor lo
     * cuela por ->merge() desde otro componente.
     */
    $idPedido = trim((string) ($id ?? $attributes->get('id') ?? ''));
    $panelId = $idPedido !== '' ? $idPedido : 'muni-pop-'.substr(sha1($etiqueta), 0, 8);
    $disparadorId = $panelId.'-disparador';

    /*
     * El identificador del ancla es por INSTANCIA: si dos paneles compartieran
     * `anchor-name`, el segundo se colgaría del disparador del primero. Viaja
     * como propiedad personalizada en el envoltorio —que es CSS válido en
     * cualquier navegador, incluso donde `anchor-name` no existe— y la hoja de
     * estilos la consume DENTRO de la guardia de soporte.
     *
     * NO se llama `--muni-*` a propósito. Los `--muni-*` son tokens de tema que
     * el adoptante redefine y que deben estar declarados en los DOS CSS del
     * paquete —hay dos pruebas que lo vigilan y tienen razón—. Esto es
     * fontanería que escribe el propio componente en cada instancia: no es
     * tematizable y no puede vivir en una hoja compartida.
     */
    $ancla = '--mpop-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $panelId), '-');
@endphp

{{-- PANEL FLOTANTE anclado a un disparador, con contenido y controles dentro,
     que NO bloquea la página. Patrón DISCLOSURE, no menú y no diálogo.

     POR QUÉ NO ES UN DROPDOWN. `dropdown` declara aria-haspopup="menu" y
     role="menu": los hijos de un menú deben ser menuitem*, así que meter ahí
     campos, casillas o un calendario es marcado inválido y el lector anuncia un
     menú sin elementos. Y no es un `modal`: bloquear la pantalla entera para
     marcar dos casillas de filtro es desproporcionado.

     LA BASE ES EL ATRIBUTO NATIVO `popover` + `popovertarget`, no una
     reimplementación de Radix ni de Pines. Lo que se gana, y que NO hay que
     escribir:
       · top-layer, así que el panel no se recorta dentro de una tabla con
         overflow y no hace falta teletransportarlo al body —que es lo que hace
         que Livewire huerfane el panel del `modal` al remorfear—;
       · light-dismiss al pulsar fuera;
       · Escape, que cierra Y devuelve el foco al invocador;
       · Enter y Espacio, porque el disparador es un <button> de verdad.

     QUIÉN LLEVA EL ESTADO: EL ATRIBUTO NATIVO. Alpine no abre ni cierra nada y
     no hay x-show: la fuente de verdad es `:popover-open`, y Alpine solo LEE de
     ahí para dos cosas que el navegador no hace: sincronizar el aria-expanded
     del disparador —el mapeo implícito de popovertarget no es parejo entre
     navegadores— y colocar el panel donde todavía no existe CSS Anchor
     Positioning. Por eso el estado se resincroniza desde el DOM al iniciarse:
     tras un remorfeo de Livewire el x-data se rehace, pero el panel sigue
     abierto porque nunca dependió de Alpine.

     EL SLOT POR DEFECTO PUEDE SER UN <form method="get"> ENTERO, y se rinde tal
     cual: el componente no abre un formulario propio. Es la propiedad que
     `filter-bar` cuida —los filtros viven en la URL, así que la descarga
     xlsx/csv de la misma pantalla los arrastra— y que un panel con el estado en
     Alpine perdería.

     SIN SOPORTE DE POPOVER (por debajo de Chrome 116, Firefox 125 o Safari 17)
     el atributo se ignora y el panel se queda visible en el flujo. Se degrada a
     contenido siempre a la vista, que es feo pero usable: el formulario sigue
     enviándose. Lo contrario —esconderlo con una clase— dejaría los filtros
     inalcanzables. --}}

<div
    class="muni-pop"
    {{-- El ancho viaja como propiedad personalizada HEREDADA y no como estilo en
         línea del panel: un `style="width:…"` gana a cualquier hoja, y entonces el
         breakpoint móvil no puede convertirlo en una hoja a ancho completo. Medido
         a 390 px, con el ancho en línea el panel salía de 320 px descolgado. --}}
    style="--mpop-ancla: {{ $ancla }}; --mpop-ancho: {{ $width }};"
    x-data="{
        /* El respaldo se apaga donde el anclaje nativo existe: si corrieran los
           dos, se pelearían por las mismas coordenadas. */
        anclajeNativo: !! (window.CSS && CSS.supports('anchor-name: --muni-pop-sonda')),
        abierto: false,

        sincroniza(panel) {
            /* Lee la verdad del DOM, no de una memoria propia: `:popover-open`
               es un selector que el navegador viejo no conoce y lanza. */
            try { this.abierto = panel.matches(':popover-open'); } catch (error) { this.abierto = false; }
            if (this.abierto) { this.anclar(); }
        },

        alAlternar(evento) {
            this.abierto = evento.newState === 'open';
            if (this.abierto) { this.anclar(); }
        },

        anclar() {
            if (this.anclajeNativo || ! this.abierto) { return; }
            const panel = this.$refs.panel, disparador = this.$refs.disparador;
            if (! panel || ! disparador) { return; }
            if (window.matchMedia('(max-width: 640px)').matches) {
                panel.style.removeProperty('top');
                panel.style.removeProperty('left');
                return;
            }
            const caja = disparador.getBoundingClientRect(), margen = 8, hueco = 6;
            const alto = panel.offsetHeight, ancho = panel.offsetWidth;
            let arriba = caja.bottom + hueco;
            if (arriba + alto > window.innerHeight - margen && caja.top - hueco - alto > margen) {
                arriba = caja.top - hueco - alto;
            }
            let izquierda = panel.dataset.muniPopAlign === 'end' ? caja.right - ancho : caja.left;
            izquierda = Math.min(Math.max(margen, izquierda), Math.max(margen, window.innerWidth - ancho - margen));
            panel.style.top = Math.max(margen, arriba) + 'px';
            panel.style.left = izquierda + 'px';
        },

        alPerderElFoco(evento) {
            /* Sin trampa de foco, con contenido largo el usuario sale por Tab y
               deja el panel abierto encima de la tabla. Solo se cierra cuando el
               foco se va a OTRO elemento: sin relatedTarget puede ser un clic en
               una zona no enfocable del propio panel, y ahí cerrar sería un
               salto que nadie pidió. */
            const destino = evento.relatedTarget;
            if (! destino) { return; }
            if (this.$refs.panel.contains(destino) || destino === this.$refs.disparador) { return; }
            this.$refs.panel.hidePopover();
        },
    }"
    {{-- El listener del tamaño de ventana lo administra Alpine, que lo quita al
         destruir el nodo. Pines lo registra a mano sobre window y nunca lo
         elimina: con Livewire, cada respuesta del servidor deja otro midiendo un
         panel que ya no está. --}}
    x-on:resize.window="anclar()"
>
    <button
        type="button"
        id="{{ $disparadorId }}"
        class="muni-pop__disparador"
        x-ref="disparador"
        popovertarget="{{ $panelId }}"
        popovertargetaction="toggle"
        aria-controls="{{ $panelId }}"
        {{-- EXPLÍCITO y en el HTML del servidor: el mapeo implícito de
             popovertarget no es parejo entre navegadores, y donde no se mapea el
             lector anuncia un botón cualquiera. Alpine solo lo mantiene al día. --}}
        aria-expanded="false"
        x-bind:aria-expanded="abierto"
        x-bind:data-abierto="abierto"
    >
        @isset($trigger)
            {{ $trigger }}
        @else
            <span>{{ $etiqueta }}</span>
        @endisset

        <svg class="muni-pop__flecha" viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
            <path d="M5 8l5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div
        popover="auto"
        x-ref="panel"
        x-init="sincroniza($el)"
        x-on:toggle="alAlternar($event)"
        x-on:focusout="alPerderElFoco($event)"
        {{-- `group` y no `dialog`: mientras no sea modal —y no lo es, no bloquea
             la página— anunciarlo como diálogo promete una trampa de foco que no
             existe. El nombre lo presta el disparador. --}}
        role="group"
        aria-labelledby="{{ $disparadorId }}"
        data-muni-pop-align="{{ $alineacion }}"
        {{ $attributes->merge([
            'id' => $panelId,
            'class' => 'muni-pop__panel',
        ]) }}
    >
        <div class="muni-pop__barra">
            {{-- Cierre declarativo: el propio invocador nativo, sin una línea de
                 JS. Va PRIMERO, como la × de `modal`, para que el orden visual y
                 el de tabulación sean el mismo. --}}
            <button
                type="button"
                class="muni-pop__cerrar"
                popovertarget="{{ $panelId }}"
                popovertargetaction="hide"
                aria-label="{{ $closeLabel }}"
            >
                <svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                    <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        {{ $slot }}
    </div>
</div>

@once
    <style>
        /* El CSS viaja DENTRO del componente y no en muni-ui.css (DESIGN §7):
           en un panel Filament solo se inyecta filament.css, así que una clase
           declarada únicamente en la otra hoja dejaría el panel sin estilo y sin
           un solo error en consola. */
        .muni-pop { display: inline-block; }

        .muni-pop__disparador {
            display: inline-flex; align-items: center; gap: 8px;
            /* Área táctil por encima del mínimo de 24x24. */
            min-height: 36px; padding: 8px 12px;
            font-family: var(--muni-font-sans); font-size: 13px; font-weight: 600;
            color: var(--muni-text); background: var(--muni-surface);
            border: 1px solid var(--muni-border); border-radius: var(--muni-radius-sm);
            cursor: pointer;
            transition: background var(--muni-dur) var(--muni-ease), border-color var(--muni-dur) var(--muni-ease);
        }
        .muni-pop__disparador:hover { background: var(--muni-surface-3); border-color: var(--muni-border-2); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-pop__disparador:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; }

        /* El estado abierto NO se comunica solo con color: la flecha gira y el
           aria-expanded lo dice. --muni-dur baja a 0 ms con movimiento reducido,
           así que la preferencia se respeta sin escribir la media query. */
        .muni-pop__flecha { flex: none; transition: transform var(--muni-dur) var(--muni-ease); }
        .muni-pop__disparador[data-abierto="true"] .muni-pop__flecha { transform: rotate(180deg); }

        /* EL RESET DEL UA STYLESHEET. El navegador trae, para todo [popover]:
           position fija, inset a cero, margen automático, un borde sólido negro
           y un relleno de .25em. Las cinco declaraciones hay que pisarlas: sin
           la del inset el panel se planta centrado en mitad de la pantalla y el
           anclaje no se nota hasta que alguien lo abre en producción. */
        .muni-pop__panel {
            position: fixed;
            inset: auto;
            margin: 0;
            border: 1px solid var(--muni-border);
            padding: 12px;
            /* Y lo propio del componente. Sin anclaje nativo, el sitio exacto lo
               escribe el respaldo medido; estos valores son el punto de partida
               para el primer cuadro y para quien abra la página sin JS. */
            top: 0; left: 0;
            box-sizing: border-box;
            width: var(--mpop-ancho, 320px);
            max-width: calc(100vw - 24px); max-height: 60vh; overflow-y: auto;
            background: var(--muni-surface); color: var(--muni-text);
            border-radius: var(--muni-radius-lg); box-shadow: var(--muni-shadow-lg);
            font-family: var(--muni-font-sans); font-size: 13px; line-height: 1.5;
        }
        .muni-pop__panel *, .muni-pop__panel *::before, .muni-pop__panel *::after { box-sizing: border-box; }

        .muni-pop__barra { display: flex; justify-content: flex-end; margin: -4px -4px 4px; }
        .muni-pop__cerrar {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 32px; min-width: 32px; padding: 6px;
            color: var(--muni-muted); background: transparent; border: none;
            border-radius: var(--muni-radius-sm); cursor: pointer;
            transition: background var(--muni-dur) var(--muni-ease), color var(--muni-dur) var(--muni-ease);
        }
        .muni-pop__cerrar:hover { background: var(--muni-surface-3); color: var(--muni-text); }
        .muni-pop__cerrar:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; }

        /* TABLET Y TELÉFONO. Un panel de 320px anclado al borde derecho se sale
           de la pantalla o se «corre a la izquierda» hasta quedar bajo otra
           cosa. Por debajo del breakpoint deja de estar anclado: es una hoja a
           ancho completo pegada abajo, como cualquier hoja de acciones móvil.
           El respaldo medido borra su top/left en este ancho para no pisarlo. */
        @media (max-width: 640px) {
            .muni-pop__panel {
                inset: auto 0 0 0;
                width: auto; max-width: none; max-height: 80vh;
                border-radius: var(--muni-radius-lg) var(--muni-radius-lg) 0 0;
                border-inline: 0; border-bottom: 0;
                padding: 12px 14px calc(12px + env(safe-area-inset-bottom, 0px));
            }
        }

        /* ANCLAJE NATIVO, SOLO DONDE EXISTE. Chrome y Safari sí, Firefox no, y
           por eso NUNCA es la única vía: sin esta guardia el panel se quedaría
           sin posición en Firefox. Donde falta, el respaldo de ~20 líneas mide
           el disparador y escribe top y left. */
        @supports (anchor-name: --muni-pop-sonda) {
            @media (min-width: 641px) {
                .muni-pop__disparador { anchor-name: var(--mpop-ancla); }

                .muni-pop__panel {
                    position-anchor: var(--mpop-ancla);
                    top: anchor(bottom); bottom: auto;
                    margin-block: 6px;
                    /* Volteo y recolocación los decide el navegador: es la parte
                       que el respaldo hace a mano y peor. */
                    position-try-fallbacks: flip-block, flip-inline;
                }
                .muni-pop__panel[data-muni-pop-align="end"] { right: anchor(right); left: auto; }
                .muni-pop__panel[data-muni-pop-align="start"] { left: anchor(left); right: auto; }
            }
        }

        /* IMPRESIÓN. Estos sistemas imprimen todos los días: un panel abierto
           sale dibujado encima de la nómina y tapa la mitad de las filas. El
           !important es obligatorio, porque el UA pone display:block en
           :popover-open. El disparador es cromo de pantalla y tampoco va. */
        @media print {
            .muni-pop__disparador,
            .muni-pop__panel { display: none !important; }
        }
    </style>
@endonce
