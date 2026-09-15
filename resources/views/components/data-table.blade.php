@props([
    'columns' => [],
    'empty' => 'Sin resultados para este filtro.',
    /*
     * Nombre de la tabla. Va como <caption> oculto a la vista pero presente en el
     * árbol de accesibilidad, y es también el nombre de la región desplazable.
     */
    'caption' => null,
    /* Nombre distinto para la región desplazable, si el del <caption> no sirve. */
    'label' => null,
    /*
     * Cabecera y primera columna fijas: OPT-IN las dos. Por defecto la tabla emite
     * exactamente lo mismo que antes, para no cambiar cómo se ve una nómina corta
     * que hoy está bien.
     */
    'stickyHeader' => false,
    'stickyColumn' => false,
    /* Alto máximo del marco (p. ej. `60vh`). También opt-in: sin él no hay recorte. */
    'maxHeight' => null,
    /* Densidad: 'normal' o 'compact'. Dos, no cinco: nueve sistemas que deben verse
       igual no necesitan cinco densidades, necesitan un default bueno.
       Se conserva porque ya está publicada y hay vistas que la usan; lo nuevo se
       pide con `densidad`, que es la que además se hereda del anfitrión. */
    'density' => 'normal',
    /*
     * Densidad de la fila: 'comoda' (la de siempre) o 'compacta'. Sin la prop, la
     * tabla hereda lo que el anfitrión haya pintado en el <html> como
     * `data-muni-densidad="compacta"` desde una cookie leída EN SERVIDOR — el
     * paquete no gestiona cookies ni lee request(), y así no hay parpadeo.
     * Pasarla a mano gana sobre esa herencia, en los dos sentidos.
     */
    'densidad' => null,
    /*
     * SELECCIÓN EN LOTE. Aditivo y opt-in: sin `selectable` la tabla emite byte a
     * byte lo de antes, sin columna extra, sin casillas y sin una línea de Alpine.
     *
     * Con él aparece una primera columna con la casilla de «marcar todo» (con
     * estado indeterminado cuando va media tabla) y la tabla pasa a llevar la
     * cuenta de las casillas de fila que el anfitrión ponga: cada <tr> lleva su
     * propia casilla marcada con `data-muni-pick`, con su `name`, su `value` y su
     * nombre accesible. Se hace así, y no generando las filas, porque el slot de
     * esta tabla son los <tr> del anfitrión: la tabla no sabe qué identifica a cada
     * registro y no puede inventarlo.
     *
     *     <td><input type="checkbox" data-muni-pick name="ids[]" value="{{ $s->folio }}"
     *                aria-label="Seleccionar la solicitud {{ $s->folio }}"></td>
     *
     * La casilla va en la PRIMERA celda de la fila: la columna fija y la franja de
     * la fila con problema cuentan con eso. Espacio marca; Enter abre el detalle,
     * que es el elemento de la fila con `data-muni-open` (el enlace o el botón que
     * abre el `drawer`). Si la fila no trae ninguno, Enter no hace nada: lo que
     * NUNCA hace es enviar el formulario del lote.
     *
     * El ámbito es SIEMPRE lo que se ve: la casilla de cabecera marca las filas de
     * esta página y nada más. El paquete no emite «aplicar a los 340 que coinciden
     * con el filtro»: sin autorización, cola ni bitácora —que son del anfitrión— esa
     * es una acción destructiva sobre registros que nadie miró.
     */
    'selectable' => false,
    /* Nombre accesible de la casilla de cabecera. Sin él el lector dice «casilla». */
    'selectionLabel' => 'Seleccionar todas las filas de esta página',
])

@php
    $stickyHeader = filter_var($stickyHeader, FILTER_VALIDATE_BOOLEAN);
    $stickyColumn = filter_var($stickyColumn, FILTER_VALIDATE_BOOLEAN);
    $selectable = filter_var($selectable, FILTER_VALIDATE_BOOLEAN);

    /* Sin cabecera no hay dónde poner «marcar todo», y una tabla seleccionable sin
       esa casilla obliga a marcar de a una: es el defecto que la ficha describe. La
       errata se dice en voz alta en vez de renderizar media función en silencio. */
    if ($selectable && empty($columns)) {
        throw new InvalidArgumentException(
            'Una tabla `selectable` necesita `columns`: la casilla de «marcar todo» vive en la '.
            'cabecera, y sin cabecera no hay dónde ponerla.'
        );
    }

    /* La prop nueva manda; si no viene, la heredada `density="compact"` se traduce.
       Sin ninguna de las dos no se emite clase alguna: la tabla queda a merced de
       lo que el anfitrión haya puesto en el <html>, que es el caso normal. */
    $densidadPedida = filled($densidad) ? trim((string) $densidad) : ($density === 'compact' ? 'compacta' : null);

    /* «cómoda» con tilde es como se escribe en español y es lo que va a salir de
       un select del anfitrión: se acepta igual que «comoda». */
    $densidadFila = $densidadPedida === null
        ? null
        : strtr(mb_strtolower($densidadPedida), ['ó' => 'o', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ú' => 'u']);

    /* Una errata («compacto», «densa») no puede caer a cómoda en silencio: la vista
       se vería normal y nadie se enteraría de que la prop está mal escrita. */
    if ($densidadFila !== null && ! in_array($densidadFila, ['comoda', 'compacta'], true)) {
        throw new InvalidArgumentException(
            "La densidad «{$densidadPedida}» no existe: solo «comoda» (la de siempre) o «compacta»."
        );
    }

    /* Sin nombre, un `role="region"` es una parada de tabulación anónima: el lector
       anuncia «región» y nada más. Si el consumidor no da ninguno, al menos dice
       que es una tabla. */
    $tableName = trim((string) ($caption ?? ''));
    $regionLabel = trim((string) ($label ?? $tableName)) ?: 'Tabla de datos';

    $scrollClass = 'muni-dt__scroll'
        .($stickyHeader ? ' muni-dt__scroll--head' : '')
        .($stickyColumn ? ' muni-dt__scroll--col' : '');

    $tableClass = 'muni-dt'
        .($selectable ? ' muni-dt--pick' : '')
        .($densidadFila === 'compacta' ? ' muni-dt--compact' : '')
        .($densidadFila === 'comoda' ? ' muni-dt--comoda' : '');
@endphp

{{-- Tabla densa de datos. El slot son las filas <tr>; usar la clase `muni-row--danger`
     en un <tr> para pintar la franja de estado (la firma: morosidad como banda izquierda,
     no como badge redondo). Envuelta en un contenedor con scroll horizontal propio.

     El contenedor lleva tabindex="0" + role="region" + aria-label a propósito: un div
     con overflow NO es enfocable por sí solo, así que sin el tabindex las columnas que
     se salen de la pantalla del mesón son inalcanzables sin ratón (WCAG 2.2 AA 2.1.1).
     El precio es una parada de tabulación de más cuando la tabla cabe entera; se acepta,
     porque condicionarla comparando scrollWidth con clientWidth ya es JS y se rompe al
     redimensionar la ventana. --}}
@if ($selectable)
{{-- El envoltorio del lote: es quien lleva la cuenta y quien escucha Escape.
     Escape va acá y NUNCA en `.window`: dentro de un modal de Filament un Escape
     global le robaría el cierre al modal. Solo detiene la propagación cuando de
     verdad había algo que limpiar.

     La cuenta se recalcula leyendo las casillas del DOM, que son la única verdad:
     con Livewire el `x-data` se reinicia en cada respuesta del servidor, y una copia
     en memoria de lo marcado se perdería justo cuando el funcionario acaba de marcar
     veinte filas. `@change.window` porque la barra de acciones vive FUERA de la
     tabla y su «Quitar selección» no burbujea por acá. --}}
<div
    class="muni-dt__lote"
    x-data="{
        muniLoteN: 0,
        muniLoteTotal: 0,

        init() { this.muniLoteContar(); },

        muniLoteCasillas() {
            return Array.from(this.$root.querySelectorAll('input[type=checkbox][data-muni-pick]'))
                .filter(x => ! x.disabled);
        },

        muniLoteContar() {
            const casillas = this.muniLoteCasillas();

            this.muniLoteTotal = casillas.length;
            this.muniLoteN = casillas.filter(x => x.checked).length;
        },

        /* Marca o desmarca lo que se VE. Nunca «todo lo que coincide con el filtro»:
           la tabla solo conoce las filas que le pasaron. */
        muniLoteTodo(marcar) {
            /* Marcar por código no dispara eventos. Se emiten sobre CADA casilla que
               cambia, como un clic: la barra de acciones en lote se entera por el
               `change` que burbujea hasta la ventana, y un `wire:model` o `x-model`
               sobre la casilla también. Uno solo sobre el contenedor no le llegaba a
               Livewire, y el siguiente morph deshacía la selección. */
            this.muniLoteCasillas().forEach(x => {
                if (x.checked === marcar) { return; }
                x.checked = marcar;
                x.dispatchEvent(new Event('input', { bubbles: true }));
                x.dispatchEvent(new Event('change', { bubbles: true }));
            });
            this.muniLoteContar();
        },

        /* Enter sobre una casilla: dentro de un <form> haría el ENVÍO IMPLÍCITO, y el
           botón que pulsa el navegador es el primero de envío del formulario —la
           primera acción en lote, sin confirmación—. Se cancela siempre, y sobre la
           casilla de una fila se abre su detalle. */
        muniLoteEnter(e) {
            const t = e.target;

            if (! t || t.type !== 'checkbox' || ! (t.hasAttribute('data-muni-pick') || t.classList.contains('muni-dt__pick-all'))) {
                return;
            }

            e.preventDefault();

            const fila = t.hasAttribute('data-muni-pick') ? t.closest('tr') : null;
            const abre = fila ? fila.querySelector('[data-muni-open]') : null;

            if (abre) { abre.click(); }
        },
    }"
    @change="muniLoteContar()"
    @change.window="muniLoteContar()"
    @keydown.escape="if (muniLoteN) { $event.stopPropagation(); muniLoteTodo(false); }"
    @keydown.enter="muniLoteEnter($event)"
>
@endif

<div
    class="{{ $scrollClass }}"
    @if (filled($maxHeight)) style="max-height:{{ $maxHeight }};" @endif
    tabindex="0"
    role="region"
    aria-label="{{ $regionLabel }}"
>
    <table {{ $attributes->merge(['class' => $tableClass]) }}>
        <caption class="muni-sr">{{ $tableName !== '' ? $tableName : $regionLabel }}</caption>
        @if (! empty($columns))
            <thead>
                <tr>
                    @if ($selectable)
                        {{-- Casilla NATIVA y visible, estilada con accent-color: el patrón de
                             input invisible con el anillo por box-shadow deja el foco sin
                             indicador dentro de Filament (DESIGN §5).

                             El estado mixto de un input nativo es una PROPIEDAD del DOM, no un
                             `aria-checked="mixed"`, que es para role="checkbox" y acá solo
                             confundiría al lector. --}}
                        <th scope="col" class="muni-dt__pick">
                            <input
                                type="checkbox"
                                class="muni-dt__pick-all"
                                aria-label="{{ $selectionLabel }}"
                                :checked="muniLoteTotal > 0 &amp;&amp; muniLoteN === muniLoteTotal"
                                x-effect="$el.indeterminate = muniLoteN > 0 &amp;&amp; muniLoteN < muniLoteTotal"
                                @change="muniLoteTodo($event.target.checked)"
                            >
                        </th>
                    @endif
                    {{-- `scope="col"` no es decorativo: sin él, una celda leída suelta no
                         dice a qué encabezado pertenece (WCAG 2.2 AA 1.3.1). --}}
                    @foreach ($columns as $col)
                        <th scope="col">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="muni-data-body">
            @if (trim($slot) !== '')
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ max(count($columns), 1) + ($selectable ? 1 : 0) }}" style="text-align:center;padding:28px 12px;color:var(--muni-muted);">{{ $empty }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

@if ($selectable)
</div>
@endif

{{-- Estilos de fila: aplican a los <tr>/<td> que el consumidor pone en el slot.
     Viajan en el bloque de estilos del componente y no en muni-ui.css a propósito (DESIGN §7):
     dentro de un panel Filament solo se inyecta filament.css. --}}
@once
    <style>
        /* Oculto a la vista, presente en el árbol de accesibilidad: `display:none`
           y `visibility:hidden` sacarían el <caption> del árbol y la tabla se
           quedaría sin nombre. */
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }

        .muni-dt__scroll { overflow:auto; border:1px solid var(--muni-border); border-radius:var(--muni-radius); background:var(--muni-surface); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-dt__scroll:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }

        /* Las medidas de la fila viven en variables LOCALES del componente, con
           prefijo propio. NO son tokens --muni-*: el espaciado del paquete no se
           tokeniza (los otros 37 componentes no cambian), y un --muni-* que solo
           existiera acá reventaría la guarda que exige que todo --muni-* leído por
           un componente esté declarado en las dos hojas. Cada var() lleva su
           respaldo por si la regla de la tabla no alcanzó a una fila suelta. */
        .muni-dt { --mdt-cell-py:8px; --mdt-cell-px:12px; --mdt-head-py:9px; --mdt-head-px:12px; width:100%; border-collapse:collapse; font-family:var(--muni-font-sans); font-size:12.5px; }
        .muni-dt th { text-align:left; white-space:nowrap; padding:var(--mdt-head-py, 9px) var(--mdt-head-px, 12px); font-family:var(--muni-font-sans); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--muni-muted); background:var(--muni-surface-2); border-bottom:1px solid var(--muni-border); }
        .muni-data-body td, [data-muni-row] td { padding:var(--mdt-cell-py, 8px) var(--mdt-cell-px, 12px); border-bottom: 1px solid var(--muni-border); white-space: nowrap; color: var(--muni-text); }

        /* UN solo lugar donde vive el compacto: la prop densidad="compacta" y la
           herencia de [data-muni-densidad="compacta"], que el anfitrión pinta en la
           raíz del documento, redefinen las MISMAS variables. Solo relleno: nunca
           una `height` fija, que recortaría el texto en cuanto el usuario fuerza
           line-height 1.5 (WCAG 2.2 AA 1.4.12 Text Spacing). */
        .muni-dt--compact,
        [data-muni-densidad="compacta"] .muni-dt { --mdt-cell-py:4px; --mdt-cell-px:8px; --mdt-head-py:5px; --mdt-head-px:8px; }
        /* La tabla que pide «comoda» a mano gana sobre el modo compacto del
           anfitrión: misma especificidad y va después. Una nómina de tres filas en
           un panel compacto no tiene por qué encogerse. */
        [data-muni-densidad="compacta"] .muni-dt--comoda { --mdt-cell-py:8px; --mdt-cell-px:12px; --mdt-head-py:9px; --mdt-head-px:12px; }

        /* PISO DE OBJETIVO: la fila encoge, el objetivo no. Con el relleno en 4px,
           dos botones contiguos en la columna de acciones quedarían bajo el mínimo
           de 24×24 de 2.5.8; con este piso la celda de acciones crece y deja de
           encoger, que es exactamente lo que AdminLTE no hace. */
        .muni-dt td a, .muni-dt td button { display:inline-flex; align-items:center; justify-content:center; min-height:24px; min-width:24px; }
        /* En la tablet de terreno no hay puntero fino: el objetivo sube a 44×44
           aunque eso deshaga el compacto justo en esa columna. */
        @media (pointer: coarse) {
            .muni-dt td a, .muni-dt td button { min-height:44px; min-width:44px; }
        }
        [data-muni-row]:hover { background: var(--muni-surface-2); }
        [data-muni-row].muni-row--danger { position: relative; }
        [data-muni-row].muni-row--danger td:first-child { box-shadow: inset 3px 0 0 var(--muni-danger-fg); }
        [data-muni-row].muni-row--danger td:first-child { color: var(--muni-danger-fg); font-weight: 600; }
        .muni-num { font-family: var(--muni-font-mono); font-variant-numeric: tabular-nums; }

        /* Lo que se imprime es un acta: el marco no puede recortar la nómina y la
           cabecera se repite en cada hoja, que es lo que se quiere en un documento
           municipal.

           TODAS las reglas de impresión de la tabla viven acá y no en la hoja
           imprimible. El bloque de estilos de un componente se emite UNA sola vez,
           y en una hoja con nómina la tabla va antes que el pie: el de data-table
           ya está emitido y gana, así que una copia en la hoja no se aplicaría
           nunca. */
        @media print {
            .muni-dt__scroll { overflow:visible !important; border-radius:0; }
            .muni-dt thead { display:table-header-group; }
            [data-muni-row] { break-inside:avoid; }

            /* En pantalla las celdas van `nowrap` y lo que no cabe se alcanza
               desplazando el marco. En papel no hay desplazamiento: un domicilio
               largo se saldría de la hoja. Se deja envolver todo MENOS las cifras
               y los RUT, que partidos a la mitad se leen mal. */
            .muni-dt td { white-space:normal; }
            .muni-num { white-space:nowrap; }

            /* La franja de la fila con problema es una `box-shadow` interior, y
               los navegadores no imprimen fondos: en papel desaparecía y la fila
               morosa quedaba igual que las demás. Se sustituye por un borde
               SÓLIDO, que sí se imprime, más una marca de texto, porque el color
               tampoco puede ser el único portador sobre papel (WCAG 2.2 AA 1.4.1)
               y una impresora en blanco y negro lo deja en gris. */
            [data-muni-row].muni-row--danger td:first-child { box-shadow:none; border-left:3px solid var(--muni-danger-fg); padding-left:9px; }
            [data-muni-row].muni-row--danger td:first-child::before { content:"(*) "; font-family:var(--muni-font-mono); font-weight:700; }
        }
    </style>
@endonce

{{-- El CSS de la selección se emite SOLO si la tabla es seleccionable. Opt-in de
     verdad: una nómina que hoy está bien no recibe ni una regla nueva, ni siquiera
     apagada, y el marcado de `data-muni-pick` no aparece en el HTML de nadie que no
     lo haya pedido. --}}
@if ($selectable)
    @once
    <style>
        /* LA COLUMNA DE SELECCIÓN. El ancho lo fija la celda, no la casilla: así la
           columna no baila entre la cabecera y el cuerpo. */
        .muni-dt__pick { width:1%; white-space:nowrap; text-align:center; }
        /* Ancho FIJO de 44px para la columna de la casilla, sin relleno lateral: 44 es
           lo que mide la casilla con puntero grueso (24 + 2×10), así que no cambia con
           la densidad ni con el dispositivo. La columna fija de al lado se ancla a ese
           número. La celda con colspan (el estado vacío) queda fuera. */
        .muni-dt--pick th.muni-dt__pick, .muni-dt--pick td:first-child:not([colspan]) {
            width:44px; min-width:44px; padding-left:0; padding-right:0; text-align:center; box-sizing:border-box; }
        /* LA FIRMA en una tabla seleccionable. La franja sigue en el borde de la fila
           —la primera celda—, pero el color y el peso del dato con problema van a la
           celda que lo identifica, la segunda: sobre la casilla no significan nada. */
        .muni-dt--pick [data-muni-row].muni-row--danger td:nth-child(2) { color: var(--muni-danger-fg); font-weight: 600; }
        /* 18px de dibujo + 3px de margen = 24×24 de objetivo (WCAG 2.2 AA 2.5.8), y
           la separación entre dos casillas de filas contiguas nunca baja de ahí.
           `accent-color` es lo que pinta la casilla nativa con la identidad del
           municipio sin reimplementarla: una casilla propia pierde el modo de alto
           contraste del sistema operativo y el relleno del navegador. */
        .muni-dt input[type="checkbox"][data-muni-pick], .muni-dt__pick-all {
            width:18px; height:18px; margin:3px; accent-color:var(--muni-accent); cursor:pointer; }
        .muni-dt input[type="checkbox"][data-muni-pick]:focus-visible, .muni-dt__pick-all:focus-visible {
            outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        /* En la tablet de terreno no hay puntero fino: 24 + 2×10 = 44. */
        @media (pointer: coarse) {
            .muni-dt input[type="checkbox"][data-muni-pick], .muni-dt__pick-all { width:24px; height:24px; margin:10px; }
        }
        /* En el papel la columna se queda: esconder solo el <th> —el <td> es marcado
           del anfitrión y no lleva esta clase— descuadraría la nómina entera, y
           `:has()` para alcanzar la celda sería CSS moderno sin respaldo (DESIGN §10).
           Una casilla impresa en blanco encima sirve de lista de verificación. */
    </style>
    @endonce
@endif

{{-- Lo de la cabecera y la columna fijas se emite SOLO si alguna de las dos está
     pedida. Es opt-in de verdad: una nómina corta que hoy está bien no recibe ni una
     regla nueva, ni siquiera apagada. --}}
@if ($stickyHeader || $stickyColumn || filled($maxHeight))
    @once
    <style>
        /* CABECERA FIJA (opt-in).
           Con `border-collapse:collapse` el borde de una celda fija se pierde al
           desplazar, porque el borde colapsado pertenece a la tabla y no a la celda.
           Se repone con una sombra INTERIOR, que sí viaja con la celda. No se migra a
           `border-separate`: eso reescribiría el bloque de estilos entero y rompería la firma
           `.muni-row--danger`, que ya pinta la franja con `inset 3px 0 0`. */
        .muni-dt__scroll--head .muni-dt thead th { position:sticky; top:0; z-index:3; box-shadow: inset 0 -1px 0 var(--muni-border); }
        /* 2.4.11: al tabular por las filas, la que recibe el foco no puede quedar
           debajo de la cabecera fija ni de la barra superior del sistema. */
        .muni-dt__scroll--head .muni-dt td, .muni-dt__scroll--head .muni-dt th { scroll-margin-top: calc(var(--muni-topbar-h, 56px) + 40px); }

        /* PRIMERA COLUMNA FIJA (opt-in).
           El RUT se pierde al desplazarse a la derecha con 25 filas igual que con 200:
           no depende del largo de la tabla. Necesita fondo OPACO o el texto de las
           demás columnas se le pasa por debajo. */
        .muni-dt__scroll--col .muni-dt td:first-child { position:sticky; left:0; z-index:1; background:var(--muni-surface); box-shadow: inset -1px 0 0 var(--muni-border); }
        .muni-dt__scroll--col .muni-dt thead th:first-child { position:sticky; left:0; z-index:4; background:var(--muni-surface-2); box-shadow: inset -1px 0 0 var(--muni-border); }
        /* El fondo opaco tapaba el hover: la fila se iluminaba entera salvo su primera celda. */
        .muni-dt__scroll--col [data-muni-row]:hover td:first-child { background: var(--muni-surface-2); }
        /* En UNA sola declaración: `box-shadow` no se acumula entre reglas y
           `.muni-row--danger` es más específica, así que separadas dejarían sin borde
           derecho justo a las filas morosas. */
        .muni-dt__scroll--col [data-muni-row].muni-row--danger td:first-child { box-shadow: inset 3px 0 0 var(--muni-danger-fg), inset -1px 0 0 var(--muni-border); }
        /* La esquina (cabecera + primera columna) lleva las dos líneas y va encima de todo. */
        .muni-dt__scroll--head.muni-dt__scroll--col .muni-dt thead th:first-child { box-shadow: inset 0 -1px 0 var(--muni-border), inset -1px 0 0 var(--muni-border); }

        /* En papel no hay desplazamiento: sin esto la nómina sale recortada a las
           filas que caben en el marco y las celdas fijas se apilan sobre el resto. */
        @media print {
            .muni-dt__scroll { max-height:none !important; overflow:visible !important; }
            .muni-dt thead th, .muni-dt td:first-child { position:static !important; }
            .muni-dt thead { display:table-header-group; }
        }
    </style>
    @endonce
@endif

{{-- COLUMNA FIJA + SELECCIÓN. Sin esto, `stickyColumn` fijaba la casilla y el RUT
     se perdía al desplazar, que es justo lo que la columna fija viene a evitar. Se
     fijan las DOS primeras: la casilla a 0 y la que identifica a 44px, el ancho fijo
     de la columna de la casilla. Va después del bloque de la columna fija para ganarle
     por orden a igual especificidad. --}}
@if ($selectable && $stickyColumn)
    @once
    <style>
        .muni-dt__scroll--col .muni-dt--pick td:nth-child(2):not([colspan]) { position:sticky; left:44px; z-index:1; background:var(--muni-surface); box-shadow: inset -1px 0 0 var(--muni-border); }
        .muni-dt__scroll--col .muni-dt--pick thead th:nth-child(2) { position:sticky; left:44px; z-index:4; background:var(--muni-surface-2); box-shadow: inset -1px 0 0 var(--muni-border); }
        /* La línea divisoria pasa de la casilla a la segunda columna. */
        .muni-dt__scroll--col .muni-dt--pick td:first-child, .muni-dt__scroll--col .muni-dt--pick thead th:first-child { box-shadow:none; }
        .muni-dt__scroll--col .muni-dt--pick [data-muni-row].muni-row--danger td:first-child { box-shadow: inset 3px 0 0 var(--muni-danger-fg); }
        .muni-dt__scroll--col .muni-dt--pick [data-muni-row]:hover td:nth-child(2) { background: var(--muni-surface-2); }
        .muni-dt__scroll--head.muni-dt__scroll--col .muni-dt--pick thead th:first-child { box-shadow: inset 0 -1px 0 var(--muni-border); }
        .muni-dt__scroll--head.muni-dt__scroll--col .muni-dt--pick thead th:nth-child(2) { box-shadow: inset 0 -1px 0 var(--muni-border), inset -1px 0 0 var(--muni-border); }
        @media print {
            .muni-dt--pick thead th:nth-child(2), .muni-dt--pick td:nth-child(2) { position:static !important; }
        }
    </style>
    @endonce
@endif
