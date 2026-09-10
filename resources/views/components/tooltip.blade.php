@props([
    'text' => '',
    'placement' => 'top',
    /*
     * Asociación por `aria-describedby`: OPT-IN, y a propósito. Ver la regla de uso
     * de más abajo. `describe` y `as="description"` son la misma cosa escrita de dos
     * formas; la segunda existe porque es como se lee en la APG.
     */
    'describe' => false,
    'as' => null,
])

@php
    /*
     * El respaldo cae a 'top', que es el defecto declarado arriba. Antes caía a
     * 'bottom': un `placement` mal escrito dibujaba la burbuja al lado contrario
     * del que dibuja la llamada sin `placement`, y nadie lo notaba porque no hay
     * error, solo una burbuja en otro sitio.
     */
    $lugar = in_array($placement, ['top', 'bottom', 'left', 'right'], true) ? $placement : 'top';

    $describir = filter_var($describe, FILTER_VALIDATE_BOOLEAN) || $as === 'description';

    /*
     * El id de la burbuja sale del que pase el consumidor —que es quien tiene que
     * escribirlo en su `aria-describedby`— y, si no pasa ninguno, de un hash del
     * texto. NUNCA de uniqid() (DESIGN §10): cambia en cada render, deja el
     * `aria-describedby` del consumidor apuntando a un id que ya no existe y
     * ensucia el diffing de Livewire. Dos ayudas con el MISMO texto en la misma
     * página comparten el hash: cuando eso pase, se pasa un `id` explícito.
     */
    $idPedido = trim((string) ($attributes->get('id') ?? ''));
    $idBurbuja = $idPedido !== ''
        ? $idPedido
        : ($describir ? 'muni-tt-'.substr(sha1((string) $text.'|'.$lugar), 0, 8) : null);
@endphp

{{-- Ayuda breve de un control (Alpine 3 core). Aparece al pasar el puntero Y al
     enfocar con el teclado, se descarta con Esc y el puntero puede entrar en ella.

     LA REGLA DE USO, que es lo que de verdad hay que respetar:

     El tooltip nunca es el único portador de la información. El nombre accesible del
     botón de solo icono va en su `aria-label`, y el tooltip solo lo repite en pantalla
     para quien ve. Por eso la burbuja sale `aria-hidden="true"` por defecto: si el
     botón dice `aria-label="Anular giro"` y además se le apunta un `aria-describedby`
     al mismo texto, el lector anuncia «Anular giro, Anular giro». La APG reserva
     `describedby` para cuando la ayuda AGREGA algo que el nombre no dice.

     El motivo de fondo es la tablet de terreno: ahí no hay puntero ni foco cómodo, y
     una ayuda que solo aparece al pasar el mouse es información que esa persona no
     recibe nunca.

         REPITE EL NOMBRE (lo normal): la burbuja va oculta al lector.
             x-muni::tooltip text="Anular giro"
                 button type="button" aria-label="Anular giro"

         AGREGA INFORMACIÓN: se asocia, y el `aria-describedby` lo escribe el consumidor.
             x-muni::tooltip describe id="tt-anular" text="Deja el giro sin efecto y avisa…"
                 button type="button" aria-label="Anular giro" aria-describedby="tt-anular"

         OJO al escribir estos ejemplos: una etiqueta de componente se compila IGUAL
         dentro de un comentario —las etiquetas se compilan antes de que el comentario
         se borre—, así que el ejemplo va sin los signos de menor y mayor o el
         componente muere con «unexpected end of file, expecting endif».

     El `aria-describedby` lo escribe el consumidor en su propio botón, en el HTML del
     servidor. El componente NO lo inyecta desde Alpine: en Livewire 3 y 4 el morph
     compara con el HTML que vino del servidor y borra en silencio los atributos que
     agregó JS dentro de una fila con `wire:key`; la ayuda funcionaría hasta el primer
     refresco de la fila y después dejaría de existir para el lector, sin ningún error.

     Dos cosas más que no se pueden volver a romper:

     1. La burbuja es `position:fixed` y la coloca Alpine leyendo rectángulos. Con
        `position:absolute` la recortaba el `overflow:auto` de la tabla de datos,
        que es justo donde viven los botones de icono de la fila. El anchor positioning
        hace lo mismo mejor y sin JS, pero Safari y Firefox no lo tienen todavía: entra
        como mejora dentro de un bloque `@supports` y, donde existe, Alpine no coloca
        nada. Ojo: `position:fixed` no escapa de un antepasado con `transform`,
        `filter` o `will-change`, que crea bloque contenedor propio.
     2. Ningún texto del anfitrión entra en una expresión de Alpine (la lección de
        `file-dropzone`): un apóstrofo descuadra las comillas y tumba el Alpine de la
        página ENTERA, y un texto venido de la base de datos con `'+fetch(…)+'` se
        ejecutaría. El texto es contenido Blade escapado; lo único que viaja a JS es la
        colocación, que sale de una lista cerrada. --}}
<span
    x-data="{
        show: false,
        lugar: @js($lugar),
        anclaCss: false,
        cierre: null,

        init() {
            this.anclaCss = typeof CSS !== 'undefined' && !! CSS.supports && CSS.supports('anchor-scope: --muni-tt');
        },

        abrir() {
            clearTimeout(this.cierre);
            this.show = true;
            this.$nextTick(() => this.colocar());
        },

        /* Con retardo: entre el disparador y la burbuja hay 7px de aire, y sin esta
           gracia el puntero cierra la ayuda a mitad de camino (WCAG 2.2 AA 1.4.13,
           requisito «hovereable»). Esc y el foco cierran de inmediato. */
        cerrar(ms = 180) {
            clearTimeout(this.cierre);
            this.cierre = setTimeout(() => { this.show = false; }, ms);
        },

        /* Se sale antes de medir nada si la ayuda está cerrada o si el navegador ya
           sabe anclar: esto corre en cada scroll de la nómina y dos
           getBoundingClientRect() por evento se notan en el INP de la tablet. */
        colocar() {
            if (! this.show || this.anclaCss) return;

            const b = this.$refs.burbuja;
            if (! b) return;

            const d = this.$el.getBoundingClientRect();
            const r = b.getBoundingClientRect();
            const aire = 7;
            const margen = 4;

            let arriba = d.top - r.height - aire;
            let izq = d.left + (d.width - r.width) / 2;

            if (this.lugar === 'bottom') { arriba = d.bottom + aire; }
            if (this.lugar === 'left') { arriba = d.top + (d.height - r.height) / 2; izq = d.left - r.width - aire; }
            if (this.lugar === 'right') { arriba = d.top + (d.height - r.height) / 2; izq = d.right + aire; }

            /* Si del lado pedido no cabe, se voltea al opuesto antes de recortar
               contra el borde: una ayuda pegada al techo tapa el propio botón. */
            if (this.lugar === 'top' && arriba < margen) { arriba = d.bottom + aire; }
            if (this.lugar === 'bottom' && arriba + r.height > window.innerHeight - margen) { arriba = d.top - r.height - aire; }

            b.style.left = Math.max(margen, Math.min(izq, window.innerWidth - r.width - margen)) + 'px';
            b.style.top = Math.max(margen, Math.min(arriba, window.innerHeight - r.height - margen)) + 'px';
        },
    }"
    @mouseenter="abrir()"
    @mouseleave="cerrar()"
    @focusin="abrir()"
    @focusout="cerrar(0)"
    @keydown.window.escape="show = false"
    @scroll.window.capture="colocar()"
    @resize.window="colocar()"
    {{ $attributes->except(['id'])->merge(['class' => 'muni-tt', 'style' => 'position:relative;display:inline-flex;']) }}
>
    {{ $slot }}

    <span
        x-ref="burbuja"
        x-show="show"
        x-cloak
        x-transition:enter="muni-tt__fade"
        x-transition:enter-start="muni-tt__fade--0"
        x-transition:enter-end="muni-tt__fade--1"
        x-transition:leave="muni-tt__fade"
        x-transition:leave-start="muni-tt__fade--1"
        x-transition:leave-end="muni-tt__fade--0"
        class="muni-tt__bubble muni-tt__bubble--{{ $lugar }}"
        @mouseenter="abrir()"
        @mouseleave="cerrar()"
        @if ($idBurbuja !== null) id="{{ $idBurbuja }}" @endif
        @if ($describir) role="tooltip" @else aria-hidden="true" @endif
    >{{ $text }}</span>
</span>

{{-- El bloque de estilos viaja DENTRO del componente y no en muni-ui.css a propósito
     (DESIGN §7): dentro de un panel Filament solo se inyecta filament.css. --}}
@once
    <style>
        /* Antes de que arranque Alpine no hay `x-show` que valga: sin esta regla la
           burbuja se ve pintada sobre la fila en el primer render. Viaja acá por lo
           mismo que el resto del bloque. */
        [x-cloak] { display: none !important; }

        /* `fixed` y no `absolute`: dentro de la tabla de datos el `overflow:auto`
           del envoltorio recortaba la burbuja y solo se veía el trozo que cabía en el
           marco. Sin `top`/`left` acá: los pone Alpine, o el anchor positioning donde
           exista.

           `max-width` + `white-space:normal` en vez de `nowrap`: en el teléfono del
           vecino un texto largo se salía de la pantalla, y no había nada que lo
           frenara. `28ch` es lo que se lee de un vistazo sin volverse un párrafo.

           Sin `pointer-events:none`: el puntero tiene que poder entrar en la burbuja
           para leerla y para seleccionar su texto (WCAG 2.2 AA 1.4.13). */
        .muni-tt__bubble {
            position: fixed;
            z-index: 60;
            max-width: min(28ch, 90vw);
            white-space: normal;
            overflow-wrap: anywhere;
            padding: 5px 9px;
            font-family: var(--muni-font-sans);
            font-size: 11.5px;
            line-height: 1.35;
            font-weight: 500;
            text-align: left;
            color: var(--muni-on-accent);
            background: var(--muni-text);
            border-radius: var(--muni-radius-sm);
            box-shadow: var(--muni-shadow-md);
        }

        /* La transición va por clases y no por `x-transition.opacity`, que trae una
           duración fija de 150ms cosida en el JS de Alpine: `--muni-dur` pasa a 0ms
           con prefers-reduced-motion y Alpine lee la duración computada, así que el
           movimiento se apaga solo (DESIGN §6). */
        .muni-tt__fade { transition: opacity var(--muni-dur) var(--muni-ease); }
        .muni-tt__fade--0 { opacity: 0; }
        .muni-tt__fade--1 { opacity: 1; }

        /* MEJORA PROGRESIVA (DESIGN §10). Donde el navegador sabe anclar, el propio
           CSS coloca la burbuja y Alpine no toca nada: sobrevive al scroll de
           cualquier contenedor sin escuchar un solo evento.

           Se pide `anchor-scope` y no `anchor-name` a propósito: sin scope, varias
           ayudas de la misma tabla comparten el nombre del ancla y el navegador se
           queda con la última del documento, o sea que todas las burbujas se dibujan
           sobre el último botón de la nómina. Con scope, el nombre solo vale dentro
           del envoltorio que lo declara. */
        @supports (anchor-scope: --muni-tt) {
            .muni-tt { anchor-name: --muni-tt; anchor-scope: --muni-tt; }

            .muni-tt__bubble {
                position-anchor: --muni-tt;
                margin: 7px;
                position-try-fallbacks: flip-block, flip-inline;
            }

            .muni-tt__bubble--top { position-area: top center; }
            .muni-tt__bubble--bottom { position-area: bottom center; }
            .muni-tt__bubble--left { position-area: left center; }
            .muni-tt__bubble--right { position-area: right center; }
        }

        /* Lo que se imprime es un acta. Una ayuda que quedó abierta al mandar a
           imprimir sale estampada encima del documento, y su fondo oscuro además no
           se imprime: quedaría un rectángulo de texto blanco sobre blanco tapando
           una línea del acta. */
        @media print {
            .muni-tt__bubble { display: none !important; }
        }
    </style>
@endonce
