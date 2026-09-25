@props([
    /*
     * Id del elemento que contiene las casillas de fila. Sin él, la barra mira el
     * formulario que la contiene y, si no hay ninguno, el documento entero. Con DOS
     * bandejas seleccionables en la misma pantalla es obligatorio: si no, cada barra
     * contaría también las casillas de la otra.
     */
    'for' => null,
    /* Nombre de la región. Es lo que el lector de pantalla anuncia al llegar. */
    'label' => 'Acciones sobre la selección',
    /*
     * El texto de la cuenta, entero y en español, porque el género y el número no
     * se pueden armar pegando trozos: «1 solicitud seleccionada» y «3 registros
     * seleccionados» no salen de la misma plantilla. En el plural, `:n` es el hueco
     * del número.
     */
    'countSingular' => '1 fila seleccionada',
    'countPlural' => ':n filas seleccionadas', // texto de la cuenta en plural; :n es el número
    'clearLabel' => 'Quitar selección', // texto del botón que quita la selección
    /* Lo que se anuncia al quedarse sin nada marcado: sin esto, vaciar la selección
       es silencio absoluto para quien no ve la barra desaparecer. */
    'emptyAnnounce' => 'Sin filas seleccionadas.', // anuncio al quedar sin filas marcadas
])

{{-- BARRA DE ACCIONES EN LOTE.

     Qué es: la franja que aparece encima de la nómina cuando hay filas marcadas,
     dice CUÁNTAS en texto y ofrece las acciones que le pasa el anfitrión por la
     ranura. No dibuja tabla, no decide qué acciones hay y no habla con el servidor.

     De dónde sale la cuenta: de las casillas `input[type=checkbox][data-muni-pick]`
     que estén MARCADAS dentro de su zona. El estado vive en el DOM —en las casillas,
     que son controles reales y viajan solas en el submit del formulario—, no en una
     copia en memoria: así no hay dos verdades que sincronizar y, sin JavaScript, el
     POST del anfitrión recibe igual lo marcado.

     LO QUE ESTA BARRA NO HACE, Y NO ES UN OLVIDO:

     · No ofrece «aplicar a los 340 que coinciden con el filtro». El lote actúa sobre
       lo marcado y visible, y punto. Eso resuelve de paso que en Blade puro la
       selección no sobreviva a la paginación: si no se ve, no se puede marcar, y si
       no está marcado, no entra en la acción.
     · No garantiza ShouldQueue, ni la autorización, ni la bitácora. Son del
       anfitrión: el paquete no consulta request(), Auth ni Gate. La barra puede
       hornear la confirmación con el número exacto (un <x-muni::modal> en la ranura
       que lea `loteN`), pero quién puede derivar veinte requerimientos y qué queda
       escrito lo decide el sistema, no el componente.
     · No emite <input type="hidden"> con los ids. No hace falta: la casilla de cada
       fila lleva su propio `name` y `value` y se envía sola.
     · No es la barra de `sortable-table`. Esa tabla pinta sus filas desde datos en
       Alpine y trae su propia selección y su ranura `bulk`: úsala tal cual. Esta
       barra es para `data-table selectable`, cuyas filas son marcado del servidor.
       Son dos tablas distintas y por eso dos modelos de selección: una no puede
       leer casillas que la otra todavía no dibujó.

     ENTER NO DISPARA UNA ACCIÓN EN LOTE. Una casilla o un campo dentro de un <form>
     hace el envío implícito con Enter, y el botón que el navegador pulsa es el
     PRIMER botón de envío del formulario: el de la primera acción de esta barra
     («Cerrar», «Derivar»), sin confirmación. La barra emite antes de la ranura un
     botón de envío que pasa a ser ese botón por defecto y cuyo envío no va a
     ninguna parte: `formmethod="dialog"` fuera de un <dialog> no navega ni manda
     nada. Vale también sin JavaScript, que es cuando la barra está oculta pero sus
     botones siguen en el formulario. NO sirve un botón deshabilitado: medido,
     Chromium se lo salta cuando el Enter viene de una casilla y pulsa el siguiente.
     Por eso la barra va ANTES de la tabla dentro del formulario. Las acciones se
     siguen enviando con su propio clic, con su `name` y su `value`.

     CONTRATO DE ALPINE PARA LA RANURA. Dentro del slot están disponibles, con
     prefijo para no tapar las variables del Alpine del anfitrión: `loteN` (cuántas
     van marcadas), `loteIds` (sus `value`), `loteTexto` (la cuenta ya escrita) y
     `loteLimpiar()`. Con eso el anfitrión escribe la confirmación con el número
     exacto:  <p>Vas a derivar <span x-text="loteN"></span> solicitudes.</p>

     LIVEWIRE. «Marcar todo», «Quitar selección» y Escape emiten `input` y `change`
     sobre CADA casilla que cambian, como haría un clic: así un `wire:model` (o un
     `x-model`) sobre las casillas se entera, y el siguiente morph no deshace la
     selección a espaldas de la barra.

     Los textos del anfitrión entran por `@js()`, nunca pegados dentro de una cadena
     de comillas simples: un apóstrofo en «Acciones del día» descuadraría la
     expresión y tumbaría el Alpine de la página entera (lo que ya pasó en
     `file-dropzone`). --}}
<div
    role="region"
    aria-live="polite"
    aria-label="{{ $label }}"
    x-data="{
        loteN: 0,
        loteIds: [],
        muniBbTocado: false,
        muniBbZona: @js($for ? (string) $for : ''),
        muniBbUno: @js((string) $countSingular),
        muniBbVarias: @js((string) $countPlural),
        muniBbVacio: @js((string) $emptyAnnounce),

        init() { this.muniBbSincronizar(); },

        /* Dónde se buscan las casillas. `for` manda; si no, el formulario que la
           contiene; si tampoco, el documento. */
        muniBbRaiz() {
            return (this.muniBbZona ? document.getElementById(this.muniBbZona) : null)
                || this.$root.closest('form')
                || document;
        },

        muniBbCasillas() {
            return Array.from(this.muniBbRaiz().querySelectorAll('input[type=checkbox][data-muni-pick]'));
        },

        muniBbSincronizar() {
            const marcadas = this.muniBbCasillas().filter(x => x.checked && ! x.disabled);

            this.loteN = marcadas.length;
            this.loteIds = marcadas.map(x => x.value);
        },

        loteLimpiar() {
            /* Si el foco estaba en la barra, al vaciarla se queda en un botón que
               desaparece y cae al <body>: vuelve a la casilla de cabecera de la
               tabla, que es desde donde se sigue trabajando. */
            const volver = this.$root.contains(document.activeElement);

            this.muniBbTocado = true;
            /* Desmarcar por código NO dispara eventos. Se emiten sobre CADA casilla
               que cambia, como un clic: la casilla de cabecera se entera por el
               `change` que burbujea, y un wire:model también. */
            this.muniBbCasillas().forEach(x => {
                if (! x.checked || x.disabled) { return; }
                x.checked = false;
                x.dispatchEvent(new Event('input', { bubbles: true }));
                x.dispatchEvent(new Event('change', { bubbles: true }));
            });
            this.muniBbSincronizar();

            if (volver) {
                const destino = this.muniBbRaiz().querySelector('.muni-dt__pick-all, input[type=checkbox][data-muni-pick]');

                if (destino) { destino.focus(); }
            }
        },

        get loteTexto() {
            return this.loteN === 1 ? this.muniBbUno : this.muniBbVarias.replace(':n', this.loteN);
        },
    }"
    {{-- Escucha en la ventana a propósito: la barra vive FUERA de la tabla —el
         anfitrión decide si va encima de la nómina o pegada a la cabecera— y un
         evento que sube desde una casilla nunca pasaría por ella. Recontar es
         idempotente, así que un `change` ajeno no hace daño. --}}
    @change.window="muniBbSincronizar()"
    {{-- Escape también desde la barra, que es donde queda el foco tras recorrer las
         acciones. NO desde dentro de la ranura: si el anfitrión puso ahí un modal de
         confirmación, Escape cierra el modal y no puede, además, borrar la selección
         que el funcionario estaba confirmando. --}}
    @keydown.escape="if (loteN &amp;&amp; ! $event.target.closest('.muni-bulkbar__acciones')) { $event.stopPropagation(); loteLimpiar(); }"
    {{ $attributes->merge(['class' => 'muni-bulkbar']) }}
>
    {{-- La guarda del envío implícito (ver arriba). Oculta y fuera del orden de
         tabulación: no es un control, es una regla del formulario. Sin `name`, así
         que tampoco añade nada a lo que recibe el anfitrión. --}}
    <button type="submit" formmethod="dialog" hidden tabindex="-1" class="muni-bulkbar__guarda"></button>

    {{-- La región existe desde el primer render y este párrafo también: una región
         viva que NACE con su contenido no la anuncia ningún lector (WCAG 2.2 AA
         4.1.3), y al vaciar la selección el cuerpo desaparece sin dejar nada que
         leer. Esta línea es lo que se anuncia en ese caso, y solo después de haber
         tocado algo: al cargar la página no dice nada. --}}
    <p class="muni-sr" x-text="(loteN === 0 &amp;&amp; muniBbTocado) ? muniBbVacio : ''"></p>

    {{-- Nace oculto DESDE EL SERVIDOR: sin Alpine, o antes de que arranque, nadie ve
         una barra de acciones en lote anunciando cero filas. --}}
    <div class="muni-bulkbar__cuerpo" style="display:none" x-show="loteN > 0">
        <p class="muni-bulkbar__cuenta" x-text="loteTexto"></p>

        {{-- Los botones —los del anfitrión y el de quitar— quedan FUERA de lo que se
             anuncia: al aparecer la barra, la región viva recitaría si no el rótulo
             de cada uno. `aria-live="off"` anidado no lo respetan todos los lectores
             por igual; lo que se garantiza es que la cuenta, que va primero, se lee. --}}
        <div class="muni-bulkbar__botones" aria-live="off">
            <div class="muni-bulkbar__acciones">{{ $slot }}</div>

            <button type="button" class="muni-bulkbar__quitar" @click="loteLimpiar()">{{ $clearLabel }}</button>
        </div>
    </div>
</div>

@once
    <style>
        /* Oculto a la vista, presente en el árbol de accesibilidad. Viaja con el
           componente y no en la hoja: dentro de un panel Filament solo se carga
           muni-ui-filament.css (DESIGN §7). */
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }

        .muni-bulkbar__cuerpo { display:flex; flex-wrap:wrap; align-items:center; gap:10px 12px;
            padding:10px 12px; margin:0 0 12px;
            background:var(--muni-surface-2); border:1px solid var(--muni-border);
            /* La franja al borde, como la fila con problema: es la firma del sistema
               (DESIGN §9) y además le da al bloque una pista que no es solo color. */
            border-left:3px solid var(--muni-accent); border-radius:var(--muni-radius); }
        .muni-bulkbar__cuenta { margin:0; font-family:var(--muni-font-sans); font-size:13px; font-weight:700; color:var(--muni-text); }
        .muni-bulkbar__botones { display:flex; flex-wrap:wrap; align-items:center; gap:8px 12px; flex:1 1 auto; }
        .muni-bulkbar__acciones { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .muni-bulkbar__quitar { display:inline-flex; align-items:center; justify-content:center;
            margin-left:auto; min-height:30px; min-width:24px; padding:6px 14px;
            font-family:var(--muni-font-sans); font-size:12.5px; font-weight:600;
            color:var(--muni-text); background:var(--muni-surface); border:1px solid var(--muni-border-2);
            border-radius:var(--muni-radius-sm); cursor:pointer;
            transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-bulkbar__quitar:hover { background:var(--muni-surface-2); border-color:var(--muni-accent); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro
           de Filament (DESIGN §5). */
        .muni-bulkbar__quitar:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        /* En la tablet de terreno no hay puntero fino (WCAG 2.2 AA 2.5.8). */
        @media (pointer: coarse) {
            .muni-bulkbar__quitar { min-height:44px; min-width:44px; }
        }
        /* `--muni-dur` ya baja a 0 ms con la preferencia en muni-ui.css; la guardia
           propia es para un anfitrión que cargue otra hoja de tokens. */
        @media (prefers-reduced-motion: reduce) {
            .muni-bulkbar__quitar { transition:none; }
        }
        /* Lo que se imprime es un acta: una barra de acciones no va en el papel. */
        @media print {
            .muni-bulkbar__cuerpo { display:none !important; }
        }
    </style>
@endonce
