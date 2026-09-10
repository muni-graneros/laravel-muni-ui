@props([
    /*
     * Base de los ids que arma el componente. Sale de una prop y NUNCA de
     * uniqid(): un id que cambia en cada render rompe el aria-labelledby y
     * ensucia el diffing de Livewire (DESIGN §10).
     */
    'id' => 'muni-hoja',
    'organization' => 'Municipalidad de Graneros',
    /* Unidad emisora: «Dirección de Tránsito», «Oficina de Partes». */
    'unit' => null,
    /* Tipo de documento: «Acta de fiscalización», «Comprobante de ingreso». */
    'type' => null,
    'folio' => null,
    'folioLabel' => 'Folio',
    /* Fecha ya formateada por el host: el paquete no decide formato ni zona. */
    'date' => null,
    /*
     * Leyenda de verificación. La escribe el sistema anfitrión, porque solo él
     * sabe dónde se comprueba el folio. El paquete no inventa una URL.
     */
    'verification' => null,
    'crest' => true,
    'crestSrc' => null,
    'printable' => true,
    'printLabel' => 'Imprimir',
    /*
     * Nivel del encabezado del tipo de documento. Por defecto 2, como el resto
     * del paquete: el <h1> es de page-header y dos en una página rompen el
     * esquema de encabezados. Una hoja que ES la página entera pasa :level="1".
     */
    'level' => 2,
])

{{-- HOJA TAMAÑO CARTA lista para imprimir o exportar a PDF desde el navegador.

     El CUERPO ES LIBRE: la ranura por defecto se rinde tal cual. El paquete pone
     el membrete, el folio, el pie y el reparto de la página; NO fija la
     estructura interna del documento. La de un oficio o un decreto la fijan la
     Ley 19.880 y el municipio, y seis sistemas heredando una plantilla inventada
     por un sistema de diseño es un problema legal, no un ahorro.

     DÓNDE TERMINA ESTO. Un documento con validez jurídica se emite en el
     servidor y se firma: el navegador no garantiza márgenes, imprime su propio
     encabezado con la URL y la fecha encima del membrete, y cualquiera puede
     editar el DOM antes de imprimir. Esta hoja es para el papel de trabajo
     diario —acta de fiscalización, orden de trabajo, comprobante de ingreso—,
     no para reemplazar un PDF firmado.

     LEY 21.719. En el pie va el folio y la leyenda de verificación, nada más.
     Un dato de salud o cualquier otra categoría sensible no se estampa en un pie
     que se fotocopia: la trazabilidad de la emisión vive en la bitácora. --}}

@php
    $baseId = trim((string) $id) !== '' ? trim((string) $id) : 'muni-hoja';
    $tituloId = $baseId.'-tipo';

    $headingTag = 'h'.min(6, max(1, (int) $level));

    $tipoDoc = trim((string) ($type ?? ''));
    $folioTexto = trim((string) ($folio ?? ''));
    $verificacionTexto = trim((string) ($verification ?? ''));
    $folioTitulo = trim((string) $folioLabel);

    /*
     * Los atributos condicionales van por ->merge() y no escritos a mano antes
     * del derrame (DESIGN §8): así el aria-labelledby del consumidor gana sobre
     * el nuestro en vez de emitirse dos veces.
     */
    $bolsa = $attributes->merge(array_filter([
        'id' => $baseId,
        'class' => 'muni-hoja',
        'aria-labelledby' => $tipoDoc !== '' ? $tituloId : null,
    ]));
@endphp

<section {{ $bolsa }}>
    @if (filter_var($printable, FILTER_VALIDATE_BOOLEAN))
        {{-- Cromo de pantalla: desaparece al imprimir. --}}
        <div class="muni-hoja__acciones">
            {{-- Alpine core y nada más. `onclick="window.print()"` en línea lo
                 bloquea una CSP estricta sin «unsafe-inline», y el botón se queda
                 sin hacer nada sin un solo error visible para el funcionario. --}}
            <button type="button" class="muni-hoja__imprimir" x-data @click="window.print()">
                {{ $printLabel }}
            </button>
            {{ $actions ?? '' }}
        </div>
    @endif

    {{-- ARMAZÓN DE PAGINACIÓN: una tabla de una sola columna cuyo <tfoot> es el
         pie del documento.

         No es maquetación con tablas por nostalgia. Un pie `position:fixed` se
         repite en cada hoja pero NO aparta el flujo: el contenido sigue llegando
         hasta el borde inferior y se imprime POR DEBAJO del pie (medido a 200 ppp:
         los bordes de la tabla atravesando el folio). Un grupo de pie de tabla es
         lo único que los dos motores repiten en cada página RESERVÁNDOLE el alto,
         que es lo que hacía falta.

         En pantalla la tabla no existe: `display:contents` la disuelve y el
         membrete, los datos, el cuerpo, la firma y el pie vuelven a ser hijos
         directos de `.muni-hoja`, con el mismo reparto de siempre. `role` de
         presentación porque no hay datos tabulares que anunciar: es armazón de
         impresión, y sin él un lector de pantalla leería «tabla, 2 filas». --}}
    <table class="muni-hoja__hojas" role="presentation">
        <tbody>
            <tr>
                <td class="muni-hoja__celda">
                    <div class="muni-hoja__lienzo">
                        <header class="muni-hoja__membrete">
                            @if (filter_var($crest, FILTER_VALIDATE_BOOLEAN))
                                <x-muni::gob-escudo :size="56" :src="$crestSrc" class="muni-hoja__escudo" />
                            @endif

                            <div class="muni-hoja__organismo">
                                <p class="muni-hoja__org">{{ $organization }}</p>
                                @if (filled($unit))
                                    <p class="muni-hoja__unidad">{{ $unit }}</p>
                                @endif
                            </div>

                            <div class="muni-hoja__doc">
                                @if ($tipoDoc !== '')
                                    <{{ $headingTag }} id="{{ $tituloId }}" class="muni-hoja__tipo">{{ $tipoDoc }}</{{ $headingTag }}>
                                @endif

                                @if ($folioTexto !== '')
                                    <p class="muni-hoja__folio">{{ $folioTitulo }} <span class="muni-hoja__cifra">{{ $folioTexto }}</span></p>
                                @endif

                                @if (filled($date))
                                    <p class="muni-hoja__fecha">{{ $date }}</p>
                                @endif
                            </div>
                        </header>

                        @if (isset($emisor) || isset($titular))
                            <div class="muni-hoja__datos">
                                @isset($emisor)
                                    <div class="muni-hoja__dato">{{ $emisor }}</div>
                                @endisset

                                @isset($titular)
                                    <div class="muni-hoja__dato">{{ $titular }}</div>
                                @endisset
                            </div>
                        @endif

                        <div class="muni-hoja__cuerpo">{{ $slot }}</div>

                        @isset($firma)
                            <div class="muni-hoja__firma">{{ $firma }}</div>
                        @endisset
                    </div>
                </td>
            </tr>
        </tbody>

        {{-- El pie va DESPUÉS del cuerpo en el documento —es el orden que lee un
             lector de pantalla y el que ve quien no imprime— y aun así el motor lo
             repite al pie de cada hoja: el grupo de pie de tabla se coloca al final
             de cada página, no donde esté escrito.

             Acá NO se numera. Ningún motor resuelve `counter(page)` fuera de las
             cajas de margen, y lo que salía impreso no era un hueco sino un dato
             falso: «Página 0 de 0» en las cuatro hojas de un acta que se cita en un
             Juzgado de Policía Local. La numeración vive en la caja de margen del
             bloque de estilos, donde el motor que sabe numerar numera y el que no
             sabe no dibuja nada. Lo que identifica el documento —el folio y la
             leyenda de verificación— se imprime igual en las dos. --}}
        <tfoot>
            <tr>
                <td class="muni-hoja__celda">
                    <footer class="muni-hoja__pie">
                        @if ($folioTexto !== '' || $verificacionTexto !== '')
                            <p class="muni-hoja__verificar">
                                @if ($folioTexto !== ''){{ $folioTitulo }} <span class="muni-hoja__cifra">{{ $folioTexto }}</span>@endif
                                @if ($folioTexto !== '' && $verificacionTexto !== '') · @endif
                                @if ($verificacionTexto !== ''){{ $verificacionTexto }}@endif
                            </p>
                        @endif
                    </footer>
                </td>
            </tr>
        </tfoot>
    </table>
</section>

@once
    <style>
        /* El layout viaja con el componente y no en muni-ui.css (DESIGN §7):
           dentro de un panel Filament solo se inyecta filament.css, así que una
           clase declarada únicamente en la otra hoja dejaría el documento sin
           estilo y sin un solo error en consola. Lo que sí vive en los dos CSS
           del paquete son las reglas BASE de impresión (paleta clara forzada,
           cromo oculto, márgenes de página), porque valen para toda la
           aplicación y no solo para esta pieza. */
        .muni-hoja { box-sizing:border-box; width:216mm; max-width:100%; margin:0 auto; padding:14mm 16mm; display:flex; flex-direction:column; gap:14px; background:var(--muni-surface); color:var(--muni-text); border:1px solid var(--muni-border); border-radius:var(--muni-radius); box-shadow:var(--muni-shadow); font-family:var(--muni-font-sans); font-size:13px; line-height:1.5; }
        .muni-hoja *, .muni-hoja *::before, .muni-hoja *::after { box-sizing:border-box; }

        /* EN PANTALLA LA TABLA DE PAGINACIÓN NO EXISTE. `display:contents` la
           disuelve entera —tabla, grupos, filas y celdas— y sus nietos vuelven a
           ser hijos directos del flex de `.muni-hoja`: el mismo reparto, el mismo
           hueco de 14px y el mismo `margin-top:auto` del pie que antes de que el
           armazón existiera. Solo en papel se convierte en tabla de verdad. */
        .muni-hoja__hojas,
        .muni-hoja__hojas > tbody,
        .muni-hoja__hojas > tfoot,
        .muni-hoja__hojas > tbody > tr,
        .muni-hoja__hojas > tfoot > tr,
        .muni-hoja__celda,
        .muni-hoja__lienzo { display:contents; }

        .muni-hoja__acciones { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:8px; }
        /* 44x44 mínimo: esto se pulsa desde la tablet del inspector en terreno. */
        .muni-hoja__imprimir { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:44px; min-width:44px; padding:10px 16px; font-family:var(--muni-font-sans); font-size:13px; font-weight:600; color:var(--muni-on-accent); background:var(--muni-accent); border:1px solid var(--muni-accent-strong); border-radius:var(--muni-radius-sm); cursor:pointer; transition:background var(--muni-dur) var(--muni-ease); }
        .muni-hoja__imprimir:hover { background:var(--muni-accent-strong); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-hoja__imprimir:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; }

        .muni-hoja__membrete { display:flex; align-items:flex-start; gap:14px; padding-bottom:10px; border-bottom:2px solid var(--muni-border-2); }
        /* min-width:0 en cada ítem flexible: el tamaño mínimo automático de un
           ítem flex es su contenido, y sin esto un nombre de unidad largo empuja
           el membrete y aparece el desplazamiento horizontal. */
        .muni-hoja__organismo { flex:1 1 200px; min-width:0; }
        .muni-hoja__org { margin:0; font-size:15px; font-weight:700; letter-spacing:-.01em; color:var(--muni-text); }
        .muni-hoja__unidad { margin:2px 0 0; font-size:12px; color:var(--muni-muted); }
        .muni-hoja__doc { flex:0 1 auto; min-width:0; text-align:right; }
        .muni-hoja__tipo { margin:0; font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; line-height:1.25; color:var(--muni-text); }
        .muni-hoja__folio, .muni-hoja__fecha { margin:2px 0 0; font-size:12px; color:var(--muni-muted); }
        /* Cifras y folios en mono tabular, la firma del sistema. Clase propia y
           no la de la tabla: el bloque de estilos de data-table se emite una sola
           vez y una hoja sin nómina no lo tendría. */
        .muni-hoja__cifra { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; white-space:nowrap; color:var(--muni-text); }

        .muni-hoja__datos { display:flex; flex-wrap:wrap; gap:12px; }
        .muni-hoja__dato { flex:1 1 220px; min-width:0; padding:10px 12px; background:var(--muni-surface-2); border:1px solid var(--muni-border); border-radius:var(--muni-radius-sm); }
        .muni-hoja__cuerpo { flex:1 1 auto; min-width:0; }
        .muni-hoja__firma { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:24px; margin-top:18px; break-inside:avoid; }

        .muni-hoja__pie { display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:8px; margin-top:auto; padding-top:10px; border-top:1px solid var(--muni-border); font-size:11px; color:var(--muni-muted); }
        .muni-hoja__verificar { flex:1 1 240px; min-width:0; margin:0; }

        @media (max-width: 640px) {
            .muni-hoja { padding:16px 14px; }
            .muni-hoja__membrete { flex-wrap:wrap; }
            .muni-hoja__doc { text-align:left; }
        }

        /* Repetido a propósito de los dos CSS del paquete: si el sistema
           anfitrión todavía no publicó la hoja de estilos, el documento tiene que
           salir con márgenes igual. Mismos valores, para que no diverjan. */
        @page { margin: 16mm 18mm; }

        /* LA NUMERACIÓN, Y SOLO ACÁ.

           Medido en Chromium 151 y Firefox 153: ningún motor resuelve
           `counter(page)` fuera de las cajas de margen. Pedido desde el pie salía
           «Página 0 de 0» en Chromium y «Página de» en Firefox, o sea un dato
           FALSO impreso en un acta, que es peor que una hoja sin numerar. Pedido
           desde la caja de margen, Chromium numera bien y Firefox —que no las
           implementa— no dibuja absolutamente nada. Esa es la degradación que se
           quiere: o el número correcto, o ningún número.

           Regla `@page` aparte de la de los márgenes a propósito: un motor que se
           atragante con la caja de margen descarta ESTA regla, y los márgenes de
           arriba siguen en pie. Sin color: la caja de margen hereda del elemento
           raíz y el papel ya se fuerza a la paleta clara. */
        @page {
            @bottom-right { content: "Página " counter(page) " de " counter(pages); font-family: var(--muni-font-sans); font-size: 9pt; }
        }

        @media print {
            /* En papel la hoja NO es una tarjeta: el marco, la sombra y el ancho
               fijo los pone @page. Nada de print-color-adjust: los fondos no se
               imprimen y no se pelea con eso, se prescinde de ellos. */
            .muni-hoja { width:auto; max-width:none; margin:0; padding:0; background:transparent; border:0; border-radius:0; box-shadow:none; }
            .muni-hoja__dato { background:transparent; }
            /* El botón es cromo de pantalla: dibujado en el papel de un acta es
               ruido que además invita a pulsarlo. */
            .muni-hoja__acciones { display:none; }
            .muni-hoja__membrete { break-after:avoid; }

            /* ACÁ SÍ ES UNA TABLA. Es el único armazón que los dos motores
               repiten en cada hoja RESERVÁNDOLE el alto: con el pie
               `position:fixed` de antes el contenido seguía bajando hasta el
               borde y se imprimía por debajo del folio. La celda no lleva
               relleno —los márgenes los pone `@page`— y el lienzo repone el
               hueco de 14px que en pantalla da el flex de `.muni-hoja`. */
            .muni-hoja__hojas { display:table; width:100%; border-collapse:collapse; }
            .muni-hoja__hojas > tbody { display:table-row-group; }
            .muni-hoja__hojas > tfoot { display:table-footer-group; }
            .muni-hoja__hojas > tbody > tr, .muni-hoja__hojas > tfoot > tr { display:table-row; }
            .muni-hoja__celda { display:table-cell; padding:0; }
            .muni-hoja__lienzo { display:flex; flex-direction:column; gap:14px; }

            /* El pie ya no se posiciona: lo coloca el grupo de pie de tabla al
               final de cada hoja. Numerar no numera —eso vive en la caja de
               margen—, pero el folio y la leyenda de verificación, que son lo que
               permite rastrear el papel contra la bitácora, salen en todas. */
            .muni-hoja__pie { margin:0; background:transparent; break-inside:avoid; }
        }
    </style>
@endonce
