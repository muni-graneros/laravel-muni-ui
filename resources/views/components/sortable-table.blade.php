@props([
    'columns' => [],
    'rows' => [],
    'empty' => 'Sin resultados.',
    'searchable' => false,
    'caption' => null,
    /*
     * Selección en lote. Aditivo: sin `selectable` la tabla emite exactamente lo
     * mismo que antes, sin columna extra ni casillas.
     */
    'selectable' => false,
    /*
     * Clave de la fila que IDENTIFICA el registro (el rol, el folio, el id). La
     * selección viaja por ese valor y no por el índice: al ordenar o filtrar, los
     * índices se reordenan y la acción en lote caería sobre otras filas. Si no se
     * pasa, se usa la primera columna.
     */
    'rowKey' => null,
    /*
     * Ámbito de «seleccionar todo»: 'page' (lo que esta tabla tiene en la mano) o
     * 'filter' (todo el filtro del servidor). Confundir los dos en una acción
     * destructiva es un incidente, así que 'filter' EXIGE el conteo del servidor.
     */
    'selectionScope' => 'page',
    'selectionTotal' => null,
    /*
     * Si se declara, la barra emite un <input type="hidden"> por fila marcada con
     * ese `name`, para que un formulario POST del anfitrión —con su CSRF— reciba
     * la selección sin JavaScript propio. Por defecto no emite nada: el
     * componente es de presentación.
     */
    'selectionName' => null,
])

@php
    // $columns: array de ['key'=>, 'label'=>, 'align'=>?, 'mono'=>?, 'sortable'=>? (default true),
    //           'sort'=>? ('numero'|'fecha'|'texto'; por defecto el heurístico)]
    // $rows: array de arrays asociativos por key. Cada fila puede traer '_tone'=>'danger' para la franja.
    $colsJson = json_encode(array_values($columns), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    $rowsJson = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    /* El id del buscador NO puede salir de uniqid(): cambiaría en cada render y bajo
       Livewire rompería la relación <label for> ↔ <input id> justo después del primer
       refresco, además de ensuciar el diff. Se deriva de las columnas, que son lo que
       identifica a esta tabla, y el consumidor puede imponer el suyo con `id`. */
    $buscadorId = ($attributes->get('id') ?: 'muni-st-'.substr(sha1($colsJson ?: ''), 0, 8)).'-q';

    $selectKey = '';

    if ($selectable) {
        if (! in_array($selectionScope, ['page', 'filter'], true)) {
            throw new InvalidArgumentException(
                "El ámbito de selección «{$selectionScope}» no existe: solo 'page' o 'filter'."
            );
        }

        /* El componente NO puede inventar el conteo del filtro completo: solo ve las
           filas que le pasaron. Sin el número del servidor, «seleccionar todo» diría
           «todo» sin decir de cuántos, que es justo el riesgo de datos de la ficha. */
        if ($selectionScope === 'filter' && $selectionTotal === null) {
            throw new InvalidArgumentException(
                'Con selection-scope="filter" hay que pasar selection-total con el conteo del '.
                'SERVIDOR: el componente solo conoce las filas de esta página y no puede decir '.
                'sobre cuántos registros caería la acción en lote.'
            );
        }

        $selectKey = trim((string) ($rowKey ?? (array_values($columns)[0]['key'] ?? '')));

        if ($selectKey === '') {
            throw new InvalidArgumentException(
                'Una tabla seleccionable necesita `row-key`: la clave de la fila que identifica el '.
                'registro. Sin ella la selección viajaría por el índice y al ordenar apuntaría a '.
                'otras filas.'
            );
        }

        $identificadores = array_map(fn (array $fila) => (string) ($fila[$selectKey] ?? ''), array_values($rows));

        if (in_array('', $identificadores, true) || count(array_unique($identificadores)) !== count($identificadores)) {
            throw new InvalidArgumentException(
                "La columna «{$selectKey}» no identifica cada fila: hay valores vacíos o repetidos. ".
                'Una acción en lote sobre esa selección caería sobre el registro equivocado.'
            );
        }
    }

    $pickJson = json_encode(
        ['on' => (bool) $selectable, 'key' => $selectKey],
        JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT,
    );
@endphp

<div
    x-data="{
        cols: {{ $colsJson }},
        rows: {{ $rowsJson }},
        pick: {{ $pickJson }},
        sortKey:null, sortDir:1, q:'', selected:[], touched:false,
        sort(k){ if(this.sortKey===k){ this.sortDir*=-1; } else { this.sortKey=k; this.sortDir=1; } },
        /* Lo que lee el lector de pantalla al cambiar el orden: `aria-sort` solo
           se anuncia al recorrer la tabla, y quien pulsa el botón se queda sin
           confirmación de que la lista cambió. */
        get anuncioOrden(){
            if(!this.sortKey){ return ''; }
            const c = this.cols.find(c => c.key === this.sortKey);
            return `Tabla ordenada por ${(c && c.label) || this.sortKey}, ${this.sortDir===1 ? 'ascendente' : 'descendente'}.`;
        },

        /* <<< COMPARADOR — lo extrae y lo ejecuta bajo node tests/OrdenYLoteTest.php.
           Si mueves estos marcadores, arregla también la prueba.

           QUÉ ORDENA COMO NÚMERO. En Chile el punto es separador de MILES y la coma
           es el decimal, así que `parseFloat('1.240.000')` da 1,24 y el padrón de
           morosos sale al revés. Pero borrar todos los puntos rompe el decimal con
           coma y el número ya normalizado que manda el backend. Por eso el
           reconocimiento es por patrón COMPLETO y no por limpieza a la brava:

             1.240.000   1.240.000,50   → agrupado a la chilena: el punto es miles
             80000       12,5           → coma decimal
             1240000.50                 → normalizado del backend: el punto es decimal
             $ 1.240.000  12,5 %        → el signo peso, el porcentaje y los espacios se ignoran
             09-01-2026   15/06/2026    → fecha dd-mm-aaaa (con hora opcional)
             2026-01-09                 → fecha ISO
             12.345.678-9               → RUT: ordena por el número, sin el verificador

           QUÉ NO ORDENA COMO NÚMERO, a propósito: cualquier otra cosa —«4501-2»
           (rol de patente), «UF 3,5», «12.34.56», un monto con texto al lado—.
           Esas caen a orden de texto en español en vez de que se les invente una
           cifra, que es exactamente lo que ordenaba mal el padrón.

           AMBIGÜEDAD CONOCIDA: «1.500» puede ser mil quinientos (chileno) o uno coma
           cinco (normalizado). Gana el chileno, porque es el formato con el que estos
           sistemas muestran los montos. Si tu columna es del otro tipo, decláralo con
           'sort' => 'numero' y manda el valor con coma decimal, o con 'sort' => 'texto'
           si no debe ordenarse como cantidad. */
        sortType(c){
            const t = String((c && c.sort) || 'auto').toLowerCase();
            return ({numero:'number', number:'number', fecha:'date', date:'date', texto:'text', text:'text'})[t] || 'auto';
        },
        toNumber(v){
            if(typeof v === 'number'){ return isFinite(v) ? v : null; }
            let s = String(v == null ? '' : v).trim().replace(/[\s $%]/g, '');
            if(s === ''){ return null; }
            let sign = 1;
            if(s[0] === '-'){ sign = -1; s = s.slice(1); } else if(s[0] === '+'){ s = s.slice(1); }
            let n = null;
            if(/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)){ n = parseFloat(s.replace(/\./g,'').replace(',', '.')); }
            else if(/^\d+(,\d+)?$/.test(s)){ n = parseFloat(s.replace(',', '.')); }
            else if(/^\d+(\.\d+)?$/.test(s)){ n = parseFloat(s); }
            return (n === null || isNaN(n)) ? null : sign * n;
        },
        toDate(v){
            const s = String(v == null ? '' : v).trim();
            let m = s.match(/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})(?:[ T](\d{1,2}):(\d{2}))?$/);
            if(m){ return Number(m[3])*1e8 + Number(m[2])*1e6 + Number(m[1])*1e4 + Number(m[4]||0)*100 + Number(m[5]||0); }
            m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{1,2}):(\d{2}))?$/);
            if(m){ return Number(m[1])*1e8 + Number(m[2])*1e6 + Number(m[3])*1e4 + Number(m[4]||0)*100 + Number(m[5]||0); }
            return null;
        },
        /* El RUT es identificador, no cantidad: se ordena por el cuerpo, sin el
           dígito verificador, que no aporta magnitud. */
        toRut(v){
            const s = String(v == null ? '' : v).trim();
            return /^\d{1,3}(\.\d{3})+-[\dkK]$/.test(s) ? Number(s.split('-')[0].replace(/\./g,'')) : null;
        },
        sortValue(v, type){
            if(type === 'text'){ return null; }
            if(type === 'number'){ return this.toNumber(v); }
            if(type === 'date'){ return this.toDate(v); }
            const d = this.toDate(v); if(d !== null){ return d; }
            const r = this.toRut(v); if(r !== null){ return r; }
            return this.toNumber(v);
        },
        /* Solo compara como número cuando los DOS lados lo son. Mezclar «80.000»
           con «Sin monto» por un número inventado es cómo se ordena mal sin que
           se note; con tipos distintos manda el texto, que siempre es honesto. */
        compareValues(x, y, type){
            const a = this.sortValue(x, type), b = this.sortValue(y, type);
            if(a !== null && b !== null){ return a < b ? -1 : (a > b ? 1 : 0); }
            return String(x == null ? '' : x).localeCompare(String(y == null ? '' : y), 'es');
        },
        /* COMPARADOR >>> */

        get view(){
            let r = this.rows;
            /* Las claves que empiezan con `_` son metadatos de la fila (`_tone`
               pinta la franja roja): no se ven en ninguna columna, así que
               filtrar por ellas esconde filas por un dato invisible. */
            if(this.q.trim()){ const t=this.q.toLowerCase(); r = r.filter(row => Object.entries(row).some(([k,v]) => !k.startsWith('_') && String(v).toLowerCase().includes(t))); }
            if(this.sortKey){
                const k=this.sortKey, d=this.sortDir, t=this.sortType(this.cols.find(c => c.key === k));
                r = [...r].sort((a,b) => this.compareValues(a[k], b[k], t) * d);
            }
            return r;
        },

        /* --- Selección en lote ------------------------------------------------
           La selección viaja por el valor de `pick.key`, nunca por el índice: la
           fila 3 de hoy es otra en cuanto se ordena o se filtra. */
        colCount(){ return this.cols.length + (this.pick.on ? 1 : 0); },
        rowId(row){ return String(row[this.pick.key] == null ? '' : row[this.pick.key]); },
        isSelected(row){ return this.selected.includes(this.rowId(row)); },
        get visibleIds(){ return this.view.map(r => this.rowId(r)); },
        get visibleSelectedCount(){ return this.visibleIds.filter(id => this.selected.includes(id)).length; },
        get allVisibleSelected(){ return this.view.length > 0 && this.visibleSelectedCount === this.view.length; },
        get someVisibleSelected(){ return this.visibleSelectedCount > 0; },
        toggleRow(id, on){
            this.touched = true;
            this.selected = on ? (this.selected.includes(id) ? this.selected : [...this.selected, id])
                               : this.selected.filter(x => x !== id);
        },
        /* La cabecera marca lo VISIBLE tras el filtro y nada más. Marcar filas que
           el usuario no tiene delante es el riesgo de datos de la ficha: la acción
           en lote caería sobre registros que nadie miró. */
        toggleAll(on){
            this.touched = true;
            const ids = this.visibleIds;
            this.selected = on ? [...this.selected.filter(id => !ids.includes(id)), ...ids]
                               : this.selected.filter(id => !ids.includes(id));
        },
        clearSelection(){ this.touched = true; this.selected = []; },
        /* En texto, siempre: cuántas van marcadas y —si hay filtro— cuántas de
           ellas se están viendo. Un «seleccionadas: 20» a secas con un filtro
           puesto no dice sobre qué cae la acción. */
        get selectionText(){
            const n = this.selected.length;
            let t = n === 1 ? '1 fila seleccionada' : `${n} filas seleccionadas`;
            if(this.q.trim()){ t += ` · ${this.visibleSelectedCount} de las ${this.view.length} que muestra el filtro`; }
            return t;
        },
        get selectionAnnounce(){ return (this.pick.on && this.touched) ? this.selectionText + '.' : ''; }
    }"
    @if ($selectable)
        {{-- Acotado al contenedor y NUNCA en `.window`: dentro de un modal de
             Filament, un Escape global le robaría el cierre al modal. Solo detiene
             la propagación cuando de verdad había algo que limpiar. --}}
        @keydown.escape="if(selected.length){ $event.stopPropagation(); clearSelection(); }"
    @endif
    {{ $attributes }}
>
    @if ($searchable)
        <div style="margin-bottom:12px;position:relative;max-width:280px;">
            {{-- El placeholder no es nombre accesible: desaparece al escribir y no
                 todos los lectores lo anuncian (WCAG 2.2 AA 3.3.2 y 4.1.2). --}}
            <label for="{{ $buscadorId }}" class="muni-sr">Buscar en la tabla</label>
            <input id="{{ $buscadorId }}" x-model="q" placeholder="Buscar…" class="muni-st__search">
        </div>
    @endif

    <div class="muni-sr" role="status" aria-live="polite" x-text="anuncioOrden"></div>
    {{-- La región del recuento existe desde el primer render: una región viva que
         nace junto con su contenido no la lee ningún lector (WCAG 2.2 AA 4.1.3). --}}
    <div class="muni-sr" role="status" aria-live="polite" x-text="selectionAnnounce"></div>

    @if ($selectable)
        {{-- Va en el FLUJO del documento, encima de la tabla: una barra flotante
             tapa contenido y en el teléfono se come la última fila. Tampoco mueve
             el foco: quien acaba de marcar una casilla se queda donde estaba.
             Sin animación a propósito — una duración fija ignoraría
             `prefers-reduced-motion` (DESIGN §6), y el token no aplica a un
             x-show que solo alterna `display`. --}}
        <div class="muni-st__bulk" role="group" aria-label="Acciones sobre las filas seleccionadas" x-show="selected.length > 0" x-cloak>
            <p class="muni-st__bulk-count" x-text="selectionText"></p>

            @if ($selectionScope === 'filter')
                {{-- El conteo lo pone el SERVIDOR: el componente solo ve esta página. --}}
                <p class="muni-st__bulk-scope">
                    La selección abarca solo las filas de esta página. El filtro completo tiene
                    {{ number_format((int) $selectionTotal, 0, ',', '.') }} registros.
                </p>
            @endif

            @isset($bulk)
                {{ $bulk }}
            @endisset

            <button type="button" class="muni-st__bulk-clear" @click="clearSelection()">Quitar selección</button>

            @if ($selectionName)
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="{{ $selectionName }}" :value="id">
                </template>
            @endif
        </div>
    @endif

    <div style="overflow-x:auto;border:1px solid var(--muni-border);border-radius:var(--muni-radius);background:var(--muni-surface);">
        <table class="muni-st">
            @isset($caption)
                <caption class="muni-sr">{{ $caption }}</caption>
            @endisset
            <thead>
                <tr>
                    @if ($selectable)
                        {{-- Casilla NATIVA, visible, estilada con accent-color: el patrón
                             de input invisible con foco por box-shadow deja el foco sin
                             indicador dentro de Filament (DESIGN §5). --}}
                        <th scope="col" class="muni-st__pick">
                            <label class="muni-st__pick-hit">
                                <input
                                    type="checkbox"
                                    class="muni-st__pick-box"
                                    aria-label="Seleccionar todas las filas que muestra la tabla"
                                    :checked="allVisibleSelected"
                                    {{-- El estado mixto de un input nativo es una PROPIEDAD del DOM.
                                         `aria-checked="mixed"` es para role="checkbox" y acá solo
                                         confundiría al lector. --}}
                                    x-effect="$el.indeterminate = someVisibleSelected && !allVisibleSelected"
                                    @change="toggleAll($event.target.checked)"
                                >
                            </label>
                        </th>
                    @endif
                    <template x-for="c in cols" :key="c.key">
                        {{-- `aria-sort` va en el <th>, nunca en el botón: el rol
                             columnheader es el que lo lleva. `null` en una columna
                             no ordenable hace que Alpine borre el atributo. --}}
                        <th
                            scope="col"
                            :style="`text-align:${c.align||'left'}`"
                            :class="(c.sortable!==false) && 'muni-st__sortable'"
                            :aria-sort="c.sortable===false ? null : (sortKey===c.key ? (sortDir===1 ? 'ascending' : 'descending') : 'none')"
                        >
                            {{-- Un <button> real, no el <th> con tabindex: así el
                                 foco, Enter y Espacio los pone el navegador y no
                                 hace falta escribir manejadores de teclado. --}}
                            <template x-if="c.sortable!==false">
                                <button type="button" class="muni-st__sort" @click="sort(c.key)">
                                    <span x-text="c.label"></span>
                                    <span class="muni-st__arrow" aria-hidden="true" :style="sortKey===c.key ? 'opacity:1' : 'opacity:.3'" x-text="sortKey===c.key ? (sortDir===1?'↑':'↓') : '↕'"></span>
                                </button>
                            </template>
                            <template x-if="c.sortable===false">
                                <span x-text="c.label"></span>
                            </template>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row,ri) in view" :key="ri">
                    <tr :class="row._tone==='danger' && 'muni-st__danger'">
                        @if ($selectable)
                            <td class="muni-st__pick">
                                <label class="muni-st__pick-hit">
                                    {{-- Nombre accesible propio por fila: sin él, un lector
                                         de pantalla recita «casilla, casilla, casilla». --}}
                                    <input
                                        type="checkbox"
                                        class="muni-st__pick-box"
                                        :aria-label="`Seleccionar la fila ${rowId(row)}`"
                                        :checked="isSelected(row)"
                                        @change="toggleRow(rowId(row), $event.target.checked)"
                                    >
                                </label>
                            </td>
                        @endif
                        <template x-for="c in cols" :key="c.key">
                            <td :style="`text-align:${c.align||'left'};${c.mono?'font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;':''}`" x-text="row[c.key]"></td>
                        </template>
                    </tr>
                </template>
                <template x-if="view.length===0">
                    <tr><td :colspan="colCount()" style="text-align:center;padding:28px;color:var(--muni-muted);">{{ $empty }}</td></tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

@once
    <style>
        /* Oculto a la vista, presente en el árbol de accesibilidad: `display:none`
           y `visibility:hidden` lo sacarían y la etiqueta dejaría de contar. */
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        [x-cloak] { display:none !important; }
        .muni-st { width:100%; border-collapse:collapse; font-family:var(--muni-font-sans); font-size:12.5px; }
        .muni-st th { text-align:left; white-space:nowrap; padding:9px 12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--muni-muted); background:var(--muni-surface-2); border-bottom:1px solid var(--muni-border); }
        .muni-st__sortable { user-select:none; }
        .muni-st__sort { display:inline-flex; align-items:center; gap:5px; min-height:24px; padding:0 4px; margin:0 -4px; font:inherit; color:inherit; background:none; border:0; border-radius:var(--muni-radius-sm); cursor:pointer; transition:color var(--muni-dur) var(--muni-ease); }
        .muni-st__sort:hover { color:var(--muni-text); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-st__sort:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        .muni-st__arrow { font-family:var(--muni-font-mono); font-size:11px; }
        .muni-st td { padding:9px 12px; border-bottom:1px solid var(--muni-border); white-space:nowrap; color:var(--muni-text); }
        .muni-st tbody tr { transition:background var(--muni-dur) var(--muni-ease); }
        .muni-st tbody tr:hover { background:var(--muni-surface-2); }
        /* La banda vertical va en la primera celda porque es el borde izquierdo de la
           fila; el rojo y la negrita son del DATO. Con la columna de selección puesta,
           la primera celda es la casilla: pintarla dejaría el control en rojo y el dato
           en negro. Por eso el color se declara aparte y salta la celda de selección. */
        .muni-st__danger td:first-child { box-shadow:inset 3px 0 0 var(--muni-danger-fg); }
        .muni-st__danger td:first-child:not(.muni-st__pick),
        .muni-st__danger .muni-st__pick + td { color:var(--muni-danger-fg); font-weight:600; }
        /* El borde de un CONTROL sale de --muni-field-border y no del token de separador:
           medido, --muni-border da 1,28:1 sobre la superficie y 1.4.11 pide 3:1. */
        .muni-st__search { width:100%; padding:9px 12px; font-family:var(--muni-font-sans); font-size:13px; color:var(--muni-text); background:var(--muni-surface); border:1px solid var(--muni-field-border); border-radius:var(--muni-radius-sm); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-st__search:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; border-color:var(--muni-accent); box-shadow:var(--muni-ring); }
        /* La columna de selección se queda con el ancho de la casilla. */
        .muni-st__pick { width:1%; }
        /* 24x24 de blanco de pulsación (WCAG 2.2 AA 2.5.8) alrededor de la casilla
           nativa de 18px, igual que en checkbox.blade.php. */
        .muni-st__pick-hit { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; cursor:pointer; }
        /* Nativa y visible: `accent-color` la tiñe sin `appearance:none`, así conserva
           el dibujo del sistema operativo y el modo de alto contraste forzado. */
        .muni-st__pick-box { width:18px; height:18px; margin:0; accent-color:var(--muni-accent); cursor:pointer; }
        .muni-st__pick-box:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        .muni-st__bulk { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:12px; padding:8px 12px; background:var(--muni-surface-2); border:1px solid var(--muni-border); border-radius:var(--muni-radius-sm); }
        .muni-st__bulk-count { margin:0; font-family:var(--muni-font-sans); font-size:12.5px; font-weight:600; color:var(--muni-text); }
        .muni-st__bulk-scope { margin:0; flex:1 1 100%; font-family:var(--muni-font-sans); font-size:11.5px; line-height:1.45; color:var(--muni-muted); }
        .muni-st__bulk-clear { min-height:24px; padding:3px 9px; font-family:var(--muni-font-sans); font-size:12px; color:var(--muni-text); background:var(--muni-surface); border:1px solid var(--muni-field-border); border-radius:var(--muni-radius-sm); cursor:pointer; transition:background var(--muni-dur) var(--muni-ease); }
        .muni-st__bulk-clear:hover { background:var(--muni-surface-3); }
        .muni-st__bulk-clear:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        /* Estos sistemas imprimen actas y órdenes de cobro: una columna de casillas
           y una barra de acciones en papel no significan nada. */
        @media print {
            .muni-st__pick, .muni-st__bulk { display:none !important; }
        }
        /* En alto contraste forzado el color de acento desaparece: la casilla se
           queda con el dibujo del sistema, que sigue siendo visible. */
        @media (forced-colors: active) {
            .muni-st__pick-box { accent-color:auto; }
        }
    </style>
@endonce
