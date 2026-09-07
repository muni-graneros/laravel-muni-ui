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
       igual no necesitan cinco densidades, necesitan un default bueno. */
    'density' => 'normal',
])

@php
    $stickyHeader = filter_var($stickyHeader, FILTER_VALIDATE_BOOLEAN);
    $stickyColumn = filter_var($stickyColumn, FILTER_VALIDATE_BOOLEAN);

    /* Sin nombre, un `role="region"` es una parada de tabulación anónima: el lector
       anuncia «región» y nada más. Si el consumidor no da ninguno, al menos dice
       que es una tabla. */
    $tableName = trim((string) ($caption ?? ''));
    $regionLabel = trim((string) ($label ?? $tableName)) ?: 'Tabla de datos';

    $scrollClass = 'muni-dt__scroll'
        .($stickyHeader ? ' muni-dt__scroll--head' : '')
        .($stickyColumn ? ' muni-dt__scroll--col' : '');

    $tableClass = 'muni-dt'.($density === 'compact' ? ' muni-dt--compact' : '');
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
                    <td colspan="{{ max(count($columns), 1) }}" style="text-align:center;padding:28px 12px;color:var(--muni-muted);">{{ $empty }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

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

        .muni-dt { width:100%; border-collapse:collapse; font-family:var(--muni-font-sans); font-size:12.5px; }
        .muni-dt th { text-align:left; white-space:nowrap; padding:9px 12px; font-family:var(--muni-font-sans); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--muni-muted); background:var(--muni-surface-2); border-bottom:1px solid var(--muni-border); }
        .muni-data-body td, [data-muni-row] td { padding: 8px 12px; border-bottom: 1px solid var(--muni-border); white-space: nowrap; color: var(--muni-text); }
        .muni-dt--compact th { padding:5px 8px; }
        .muni-dt--compact .muni-data-body td, .muni-dt--compact [data-muni-row] td { padding:4px 8px; }
        [data-muni-row]:hover { background: var(--muni-surface-2); }
        [data-muni-row].muni-row--danger { position: relative; }
        [data-muni-row].muni-row--danger td:first-child { box-shadow: inset 3px 0 0 var(--muni-danger-fg); }
        [data-muni-row].muni-row--danger td:first-child { color: var(--muni-danger-fg); font-weight: 600; }
        .muni-num { font-family: var(--muni-font-mono); font-variant-numeric: tabular-nums; }

        /* Lo que se imprime es un acta: el marco no puede recortar la nómina y la
           cabecera se repite en cada hoja, que es lo que se quiere en un documento
           municipal. */
        @media print {
            .muni-dt__scroll { overflow:visible !important; border-radius:0; }
            .muni-dt thead { display:table-header-group; }
            [data-muni-row] { break-inside:avoid; }
        }
    </style>
@endonce

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
