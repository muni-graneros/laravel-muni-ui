@props([
    'title' => null, // título del diálogo; también semilla del id
    'maxWidth' => '480px', // ancho máximo del panel (CSS)
    /* Las cuatro que lo endurecen para confirmar algo irreversible (ficha `confirmar`
       de docs/GAP-ANALYSIS.md, tal como la corrigió el juez: es una variante de modal,
       no un componente aparte). Todas con valor por defecto igual a lo de siempre. */
    'role' => 'dialog', // dialog | alertdialog
    'dismissable' => true, // false quita la × y el cierre al clic en el fondo
    'describedby' => null, // id que describe el diálogo (aria-describedby)
    'initialFocus' => null, // 'cancelar' o selector CSS del foco inicial
    'cancelLabel' => 'Cancelar', // texto del botón Cancelar propio
])

@php
    /* El id del título NO puede salir de uniqid(): cambiaba en cada render y bajo
       Livewire eso rehace el <h2> y el aria-labelledby en cada actualización, así
       que el diff reemplaza nodos que no cambiaron y cualquier aria-labelledby
       externo queda apuntando a un id que ya no existe. Se deriva, por este orden:
       1) del `id` que dé el consumidor —lo recomendado cuando hay más de un modal
          con el mismo título en la página, y OBLIGATORIO si se va a abrir por
          evento desde el servidor—, o
       2) del propio título, estable entre renders mientras la prop no cambie. */
    $dialogoId = $attributes->get('id') ?: 'muni-modal-'.substr(sha1((string) $title), 0, 8);
    $tituloId = $dialogoId.'-title';
    $cuerpoId = $dialogoId.'-desc';

    /* Solo dos roles válidos. Cualquier otra cosa cae en dialog: un rol inventado
       llegaría tal cual al HTML y el lector de pantalla no sabría qué anunciar. */
    $rol = $role === 'alertdialog' ? 'alertdialog' : 'dialog';

    /* `dismissable="false"` llega como cadena si el consumidor olvida los dos puntos. */
    $descartable = filter_var($dismissable, FILTER_VALIDATE_BOOL);

    /* aria-describedby: el del consumidor manda (por prop o por atributo suelto, que
       se saca de la bolsa para que no caiga en el panel, que no es el elemento con
       rol). Un alertdialog sin él se anuncia sin la pregunta —APG alertdialog—, así
       que en ese caso apunta al cuerpo. Un dialog corriente no describe nada por
       defecto: describir un formulario entero lo lee de golpe. */
    $describedby = $describedby ?: $attributes->get('aria-describedby');
    $attributes = $attributes->except('aria-describedby');
    $descritoPor = filled($describedby) ? $describedby : ($rol === 'alertdialog' ? $cuerpoId : null);

    /* Foco inicial. `cancelar` pone el botón Cancelar propio del componente y lo
       enfoca; cualquier otra cadena es un selector CSS dentro del diálogo. Sin la
       prop, x-trap enfoca lo primero tabulable, como siempre. */
    $focoInicial = filled($initialFocus) ? trim((string) $initialFocus) : null;
    $botonCancelar = $focoInicial === 'cancelar';
    $selectorFoco = $botonCancelar ? '[data-muni-cancel]' : $focoInicial;

    $etiquetaSinTitulo = $rol === 'alertdialog' ? 'Confirmación requerida' : 'Ventana de diálogo';
    $hayCabecera = filled($title) || $descartable;
    $hayPie = isset($footer) || $botonCancelar;
@endphp

{{-- Modal accesible (Alpine 3 + plugin Focus, ya presente en el bundle de Livewire de
     los paneles). El slot `trigger` abre; Escape / click en el fondo / botón × cierran.
     `x-trap.inert.noscroll` atrapa el foco dentro del diálogo (Tab/Shift+Tab no se
     escapan), bloquea el scroll del body y —al cerrar, sin el modificador `.noreturn`—
     devuelve el foco solo a quien lo abrió. El slot `footer` es opcional (acciones).

     Para confirmar algo irreversible (anular una solicitud, dar de baja una patente):
       <x-muni::modal id="anular-4821" title="¿Anular la solicitud 4821?"
           role="alertdialog" :dismissable="false" initial-focus="cancelar">
     Con eso: el lector anuncia la pregunta (aria-describedby al cuerpo), no hay × ni
     cierre al clic en el fondo —se elige entre la acción y Cancelar—, el foco cae en
     Cancelar (Enter cancela, jamás ejecuta) y Escape sigue cancelando. En el pie va
     SOLO la acción: el Cancelar lo pone el componente.

     Servidor-primero: si Livewire decide que una acción necesita confirmación, abre
     por evento —$this->dispatch('muni-modal-open', id: 'anular-4821')— y cierra con
     'muni-modal-close' cuando la acción terminó. Hace falta un `id` propio. No se
     depende de Livewire: es un evento de navegador, como en toast-host.

     Dentro de un panel Filament NO va esto: manda ->requiresConfirmation() con su
     schema, que es donde viven las acciones destructivas de los nueve sistemas.

     Ningún texto del anfitrión entra en una expresión de Alpine: el id y el selector
     van por @js(), y el rótulo del Cancelar es contenido Blade escapado. Un apóstrofo
     dentro de una cadena de x-data tumba el Alpine de la página entera. --}}
<div
    x-data="{
        open: false,
        id: @js($dialogoId),
        foco: @js($selectorFoco),
        destino(dialogo) {
            if (! this.foco) return this.primeroTabulable(dialogo);
            try { return dialogo.querySelector(this.foco); } catch (error) { return null; }
        },
        primeroTabulable(dialogo) {
            for (const el of dialogo.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')) {
                if (el.disabled || el.getAttribute('tabindex') === '-1' || ! el.getClientRects().length) continue;
                return el;
            }
            return null;
        },
        marcarFoco(dialogo) {
            if (! this.foco) return;
            const destino = this.destino(dialogo);
            if (destino) destino.setAttribute('autofocus', '');
        },
        programarFoco(dialogo) {
            dialogo.removeAttribute('aria-hidden');
            setTimeout(() => this.asegurarFoco(dialogo), 40);
        },
        asegurarFoco(dialogo, intento = 0) {
            if (! this.open || dialogo.contains(document.activeElement)) return;
            const destino = this.destino(dialogo);
            if (! destino || ! destino.getClientRects().length) {
                if (intento < 60) requestAnimationFrame(() => this.asegurarFoco(dialogo, intento + 1));
                return;
            }
            destino.focus({ preventScroll: true });
        }
    }"
    @muni-modal-open.window="$event.detail && $event.detail.id === id && (open = true)"
    @muni-modal-close.window="$event.detail && $event.detail.id === id && (open = false)"
>
    @isset($trigger)
        <div @click="open = true" style="display:inline-flex;">{{ $trigger }}</div>
    @endisset

    <template x-teleport="body">
        {{-- x-init va en ESTE elemento y antes de x-trap a propósito: el plugin Focus
             lee `[autofocus]` dentro del diálogo al inicializarse, y en un mismo
             elemento Alpine corre x-init antes que las directivas de plugin. Es la
             única ventana para estampar el atributo cuando el destino es un selector
             del consumidor. El Cancelar propio ya lo trae escrito.

             x-effect es el RESPALDO del foco inicial. x-trap arma la trampa 15 ms
             después de abrir y en ese instante busca el destino; pero el panel se
             muestra recién en el siguiente cuadro de animación (así transiciona
             x-show), y si ese cuadro llega tarde —Firefox con la CPU ocupada, una
             pestaña de fondo, un morph de Livewire— la trampa no encuentra nada
             tabulable y el foco se queda en el disparador. Medido en
             tests/navegador/dialogo-de-confirmacion.py, y le pasa a TODO modal, no
             solo al endurecido: sin initial-focus el destino del respaldo es lo
             primero tabulable visible, que es lo mismo que elige la trampa. Los
             40 ms de programarFoco son para que la trampa tenga su turno primero:
             si el foco ya está dentro no se toca nada, y al cerrar el retorno
             sigue siendo al disparador. programarFoco también se quita el
             aria-hidden que le haya dejado la trampa de OTRO diálogo (abierto
             sobre un drawer, el modal es hermano suyo bajo <body> y x-trap.inert
             lo marcó): medido, sin eso el lector no anuncia el diálogo que tiene
             el foco. Sin flechas `=>` en los atributos de este elemento: las
             pruebas leen la etiqueta con una expresión que se corta en `>`. --}}
        {{-- Escape va EN el diálogo, no en window: en window cerraría de un golpe todos
             los diálogos abiertos de la página (este sobre un drawer: los dos) y se
             tragaría el Escape de lo que viva dentro. Como la trampa mantiene el foco
             adentro, el keydown siempre pasa por acá; `.stop` es para que el de abajo
             no lo reciba. El tabindex="-1" cierra el único hueco: un clic en el velo
             (no descartable) o en texto del panel enfoca al ancestro enfocable más
             cercano, y sin él sería <body>, desde donde este oyente no se oye. --}}
        <div
            x-show="open"
            x-cloak
            x-init="marcarFoco($el)"
            x-effect="open && programarFoco($el)"
            x-trap.inert.noscroll="open"
            @keydown.escape.stop="open = false"
            tabindex="-1"
            role="{{ $rol }}"
            aria-modal="true"
            {{-- Sin `title` el aria-labelledby apuntaría a un <h2> vacío y el diálogo se
                 anunciaría sin nombre: en ese caso se cae a un aria-label genérico. --}}
            @if(filled($title)) aria-labelledby="{{ $tituloId }}" @else aria-label="{{ $etiquetaSinTitulo }}" @endif
            @if($descritoPor) aria-describedby="{{ $descritoPor }}" @endif
            class="muni-modal__capa"
        >
            {{-- Fondo. Con dismissable=false no cierra al clic: un clic fuera no es una decisión.
                 El color sale del token de identidad (DESIGN §10: ni un literal) y la
                 opacidad es propia, así que el fundido va de 0 a esa opacidad con sus
                 propias clases de inicio y fin, no con muni-fade-0/1, que terminarían
                 en opacidad 1 y darían un salto al soltar las clases. --}}
            <div
                class="muni-modal__veil"
                x-show="open"
                x-transition:enter="muni-fade" x-transition:enter-start="muni-modal__veil-0" x-transition:enter-end="muni-modal__veil-1"
                x-transition:leave="muni-fade" x-transition:leave-start="muni-modal__veil-1" x-transition:leave-end="muni-modal__veil-0"
                @if($descartable) @click="open = false" @endif
            ></div>

            {{-- Panel --}}
            <div
                x-show="open"
                x-transition:enter="muni-pop" x-transition:enter-start="muni-pop-0" x-transition:enter-end="muni-pop-1"
                x-transition:leave="muni-pop" x-transition:leave-start="muni-pop-1" x-transition:leave-end="muni-pop-0"
                {{ $attributes->merge([
                    'style' => "position:relative;width:100%;max-width:{$maxWidth};max-height:calc(100vh - 40px);"
                        ."display:flex;flex-direction:column;background:var(--muni-surface);color:var(--muni-text);"
                        ."border:1px solid var(--muni-overlay-border, var(--muni-border));border-radius:var(--muni-radius-lg);"
                        ."box-shadow:var(--muni-shadow-lg);overflow:hidden;font-family:var(--muni-font-sans);",
                ]) }}
            >
                @if($hayCabecera)
                    <header style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid var(--muni-border);">
                        <h2 id="{{ $tituloId }}" style="margin:0;font-size:15px;font-weight:700;color:var(--muni-text);">{{ $title }}</h2>
                        @if($descartable)
                            <button type="button" @click="open = false" aria-label="Cerrar" class="muni-modal-x">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/></svg>
                            </button>
                        @endif
                    </header>
                @endif

                <div id="{{ $cuerpoId }}" style="padding:18px;overflow-y:auto;font-size:14px;line-height:1.55;color:var(--muni-text);">
                    {{ $slot }}
                </div>

                @if($hayPie)
                    <footer style="display:flex;justify-content:flex-end;gap:10px;padding:14px 18px;border-top:1px solid var(--muni-border);background:var(--muni-surface-2);">
                        @if($botonCancelar)
                            {{-- Primero en el DOM y en variante ghost: es la salida segura. `autofocus`
                                 es el contrato de x-trap para el foco inicial, no el del navegador: el
                                 diálogo nace oculto y dentro de un <template>, así que al cargar la
                                 página nadie lo enfoca. --}}
                            <x-muni::button variant="ghost" data-muni-cancel autofocus x-on:click="open = false">{{ $cancelLabel }}</x-muni::button>
                        @endif
                        @isset($footer){{ $footer }}@endisset
                    </footer>
                @endif
            </div>
        </div>
    </template>
</div>

@once
    <style>
        /* La capa que centra el panel. El display:flex va en una clase y NO en el
           style inline a propósito: x-show, al mostrar, borra la propiedad display
           del style inline entera (removeProperty), así que un display:flex inline
           se pierde al abrir y el panel caía arriba a la izquierda. Medido en
           tests/navegador/dialogo-de-confirmacion.py (centro del panel contra el
           centro de la ventana). El outline no aplica: se enfoca solo con el ratón
           (tabindex="-1") y cubre la ventana entera. */
        .muni-modal__capa { position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:20px; }
        .muni-modal-x { display:inline-flex;padding:6px;border:none;background:transparent;color:var(--muni-muted);border-radius:var(--muni-radius-sm);cursor:pointer;transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-modal-x:hover { background:var(--muni-surface-3);color:var(--muni-text); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-modal-x:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        /* El velo sale de --muni-scrim, declarado en cada rama de tema de las dos
           hojas, con opacidad propia en vez de un rgba() literal. Antes era el
           petróleo oscuro institucional, que en oscuro ACLARABA la página y dejaba
           el borde del panel a 2,77:1 contra el velo; ese petróleo queda de
           respaldo para el sistema que aún no republica la hoja. Las clases -0/-1
           son las de inicio y fin del fundido; van después de la base para ganarle. */
        .muni-modal__veil { position:absolute;inset:0;background:var(--muni-scrim, var(--muni-gob-petroleo-dark));opacity:.55;backdrop-filter:blur(2px); }
        .muni-modal__veil-0 { opacity:0; } .muni-modal__veil-1 { opacity:.55; }
        /* .muni-fade* las definen también drawer y command-palette, cada uno en su propio bloque de estilos
           de una sola vez: este bloque tiene que bastarse solo, porque una página puede
           traer el modal y ninguno de los otros dos. */
        .muni-fade { transition:opacity var(--muni-dur) var(--muni-ease); }
        .muni-fade-0 { opacity:0; } .muni-fade-1 { opacity:1; }
        .muni-pop { transition:opacity var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease); }
        .muni-pop-0 { opacity:0;transform:scale(.96) translateY(8px); }
        .muni-pop-1 { opacity:1;transform:scale(1) translateY(0); }
        /* Dentro de un panel Filament solo se carga muni-ui-filament.css, y esa hoja
           deja --muni-dur en 160 ms con movimiento reducido (DESIGN §7): la guardia
           tiene que viajar con el componente, como en command-palette y stat.
           La clase va REPETIDA (especificidad 0,2,0) porque drawer y command-palette
           redefinen .muni-fade y .muni-pop en sus propios bloques sin guardia, y si
           en la página se emiten después de este, a igual especificidad ganarían
           ellos y el fundido volvería a 160 ms. Medido con los dos vecinos en el
           banco de tests/navegador/dialogo-de-confirmacion.py sobre panel-*. */
        @media (prefers-reduced-motion:reduce) { .muni-fade.muni-fade, .muni-pop.muni-pop { transition:none; } }
    </style>
@endonce
