<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;

/**
 * EL CANDADO DE LA TABLA ANCHA Y DEL PAGINADOR.
 *
 * `<x-muni::data-table>` envolvía la tabla en un `<div style="overflow-x:auto">`
 * sin `tabindex`, sin `role` y sin nombre: en el mesón de licencias —RUT, nombre,
 * clase, tipo de trámite, examen médico, estado— las columnas que se salen de la
 * pantalla eran INALCANZABLES sin ratón (WCAG 2.2 AA 2.1.1, bloqueante). Además
 * ningún `<th>` declaraba `scope` y la tabla no tenía `<caption>`, así que una
 * celda suelta no decía a qué encabezado pertenece (1.3.1).
 *
 * `<x-muni::pagination>` «deshabilitaba» los extremos con
 * `aria-disabled + opacity + pointer-events:none`. `pointer-events` NO bloquea el
 * teclado: el enlace seguía en el orden de tabulación y Enter lo activaba,
 * navegando a `#` y perdiendo la posición de scroll. Es el padrón de patentes
 * morosas de Rentas: 3.400 registros, 170 páginas.
 *
 * Se comprueba sobre el HTML servido y sobre el CSS que el componente lleva en su
 * `@once` (DESIGN §7: lo que el componente necesita viaja con el componente,
 * porque dentro de un panel Filament `muni-ui.css` no se carga).
 *
 * `toContain('valor', 'mensaje')` NO sirve acá: en Pest el segundo argumento es
 * otra cadena a buscar, no un mensaje, y la aserción se desactiva sin avisar. Por
 * eso todo va con `expect(bool)->toBeTrue('mensaje')`.
 */

/** El HTML servido de la tabla ancha del mesón de licencias. */
function tablaAnchaHtml(string $extra = ''): string
{
    return Blade::render(
        '<x-muni::data-table :columns="$columns" '.$extra.'>'
        .'<tr data-muni-row class="muni-row--danger">'
        .'<td class="muni-num">12.345.678-9</td><td>Ana Rojas</td><td>B</td>'
        .'<td>Renovación</td><td>Vencido</td><td>Rechazada</td></tr>'
        .'</x-muni::data-table>',
        ['columns' => ['RUT', 'Nombre', 'Clase', 'Tipo de trámite', 'Examen médico', 'Estado']],
    );
}

/** El CSS que la tabla ancha lleva en su `@once`, sin comentarios. */
function cssTablaAncha(): string
{
    $fuente = file_get_contents(__DIR__.'/../resources/views/components/data-table.blade.php');

    preg_match_all('#<style>(.*?)</style>#s', $fuente, $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

/** El CSS que el paginador lleva en su `@once`, sin comentarios. */
function cssPaginador(): string
{
    $fuente = file_get_contents(__DIR__.'/../resources/views/components/pagination.blade.php');

    preg_match_all('#<style>(.*?)</style>#s', $fuente, $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

/** El cuerpo de la primera regla CSS cuyo selector contiene `$aguja`. */
function reglaCss(string $css, string $aguja): string
{
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    foreach ($reglas as [, $selector, $cuerpo]) {
        if (str_contains(preg_replace('/\s+/', ' ', $selector), $aguja)) {
            return trim($cuerpo);
        }
    }

    return '';
}

/** El z-index declarado en la primera regla cuyo selector contiene `$aguja`. */
function zIndexDe(string $css, string $aguja): ?int
{
    if (preg_match('/z-index\s*:\s*(-?\d+)/', reglaCss($css, $aguja), $m)) {
        return (int) $m[1];
    }

    return null;
}

/** El HTML servido del paginador del padrón de patentes morosas. */
function paginadorHtml(string $extra = '', array $datos = []): string
{
    return Blade::render('<x-muni::pagination '.$extra.' />', $datos);
}

/**
 * La causa REAL de lo que reventó al renderizar.
 *
 * `->toThrow(InvalidArgumentException::class)` no sirve sobre un componente Blade:
 * `CompilerEngine::handleViewException()` deja pasar sin envolver solo cuatro clases
 * (HttpException, HttpResponseException, RecordNotFoundException y
 * RecordsNotFoundException) y mete todo lo demás dentro de un
 * `Illuminate\View\ViewException`, que extiende `ErrorException`. Da igual lo que
 * lance el componente: la aserción vería siempre una ViewException y fallaría
 * incluso con el componente correcto. Se comprueba la causa, que es lo que el
 * contrato exige. Mismo recurso que ya usa `tests/OrdenYLoteTest.php`.
 */
function motivoDe(callable $accion): ?Throwable
{
    try {
        $accion();
    } catch (Throwable $e) {
        while ($e->getPrevious() !== null) {
            $e = $e->getPrevious();
        }

        return $e;
    }

    return null;
}

// ---------------------------------------------------------------------------
// data-table — reparación
// ---------------------------------------------------------------------------

it('la región con desplazamiento horizontal se alcanza con el teclado y tiene nombre', function () {
    $html = tablaAnchaHtml();

    preg_match_all('/<div\b[^>]*>/', $html, $divs);

    $marco = array_values(array_filter(
        $divs[0] ?? [],
        fn (string $div) => str_contains($div, 'muni-dt__scroll') || preg_match('/overflow(-x)?\s*:\s*auto/', $div),
    ));

    expect($marco)->not->toBeEmpty('No se encontró el contenedor con desplazamiento de la tabla.');

    $contenedor = $marco[0];

    expect((bool) preg_match('/\btabindex="0"/', $contenedor))->toBeTrue(
        'El contenedor con overflow no lleva tabindex="0": las columnas que se salen de la pantalla '.
        'del mesón son inalcanzables sin ratón (WCAG 2.2 AA 2.1.1). Etiqueta: '.$contenedor
    );

    expect((bool) preg_match('/\brole="region"/', $contenedor))->toBeTrue(
        'El contenedor desplazable no declara role="region": es una parada de tabulación sin rol ni '.
        'sentido para el lector de pantalla. Etiqueta: '.$contenedor
    );

    expect((bool) preg_match('/\baria-label(?:ledby)?="[^"]+"/', $contenedor))->toBeTrue(
        'La región desplazable no tiene nombre accesible: se anuncia como «región» a secas y quien '.
        'tabula no sabe dónde cayó. Etiqueta: '.$contenedor
    );

    // El desplazamiento tiene que seguir existiendo: un tabindex sobre algo que
    // no desplaza no arregla nada.
    $llevaOverflow = preg_match('/overflow(-x)?\s*:\s*auto/', $contenedor)
        || preg_match('/overflow\s*:\s*auto/', reglaCss(cssTablaAncha(), '.muni-dt__scroll'));

    expect((bool) $llevaOverflow)->toBeTrue('El marco de la tabla ya no declara overflow.');
});

it('la región desplazable tiene indicador de foco propio', function () {
    $css = cssTablaAncha();

    $foco = reglaCss($css, '.muni-dt__scroll:focus');

    expect($foco)->not->toBe('', 'La región desplazable es enfocable y no declara ninguna regla de foco.');

    expect((bool) preg_match('/outline\s*:\s*3px solid var\(--muni-focus/', $foco))->toBeTrue(
        'La región desplazable no dibuja el outline de 3px con --muni-focus: se tabula hacia ella y '.
        'no se ve nada (WCAG 2.2 AA 2.4.7). Regla: '.$foco
    );
});

it('cada cabecera declara scope="col" y la tabla lleva caption', function () {
    $html = tablaAnchaHtml('caption="Solicitudes de licencia del mesón"');

    preg_match_all('/<th\b[^>]*>/', $html, $ths);

    expect($ths[0] ?? [])->not->toBeEmpty('No se encontró ningún <th>: el barrido está roto.');

    $sinScope = array_values(array_filter(
        $ths[0],
        fn (string $th) => ! preg_match('/\bscope="col"/', $th),
    ));

    expect($sinScope)->toBe(
        [],
        'Estas cabeceras no declaran scope="col", así que una celda suelta no dice a qué columna '.
        'pertenece (WCAG 2.2 AA 1.3.1): '.implode(' | ', $sinScope)
    );

    expect((bool) preg_match('#<caption\b[^>]*>\s*Solicitudes de licencia del mesón\s*</caption>#u', $html))->toBeTrue(
        'La tabla no emite el <caption> con el texto de la prop: la tabla se queda sin nombre.'
    );

    // Oculta a la vista, pero DENTRO del árbol de accesibilidad.
    $sr = reglaCss(cssTablaAncha(), '.muni-sr');

    expect($sr)->not->toBe('', 'El componente no lleva la utilidad de texto oculto en su @once (DESIGN §7).');

    expect((bool) preg_match('/(display\s*:\s*none|visibility\s*:\s*hidden)/i', $sr))->toBeFalse(
        'La utilidad de texto oculto usa display:none o visibility:hidden, que sacan el <caption> del '.
        'árbol de accesibilidad: la tabla vuelve a quedarse sin nombre.'
    );
});

it('el <tbody> emite la clase que el @once estila: nada de clases fantasma', function () {
    $html = tablaAnchaHtml();
    $css = cssTablaAncha();

    if (str_contains($css, '.muni-data-body')) {
        expect((bool) preg_match('/<tbody\b[^>]*class="[^"]*muni-data-body/', $html))->toBeTrue(
            'El @once estila «.muni-data-body» pero el componente nunca emite esa clase: es CSS muerto '.
            'que hace creer que las celdas del slot están cubiertas.'
        );
    }
});

// ---------------------------------------------------------------------------
// data-table — ampliación (cabecera y primera columna fijas)
// ---------------------------------------------------------------------------

it('la cabecera fija y la columna fija son opt-in y no cambian la tabla corta', function () {
    $porDefecto = tablaAnchaHtml();

    expect(str_contains($porDefecto, 'muni-dt__scroll--head'))->toBeFalse(
        'La cabecera fija se aplica por defecto: cambia cómo se ve una tabla corta que hoy está bien.'
    );

    expect(str_contains($porDefecto, 'muni-dt__scroll--col'))->toBeFalse(
        'La primera columna fija se aplica por defecto.'
    );

    expect((bool) preg_match('/max-height/', $porDefecto))->toBeFalse(
        'El max-height se aplica por defecto: la tabla corta queda recortada sin que nadie lo pidiera.'
    );

    $fijada = tablaAnchaHtml('sticky-header sticky-column max-height="60vh"');

    expect(str_contains($fijada, 'muni-dt__scroll--head'))->toBeTrue('La prop sticky-header no fija la cabecera.');
    expect(str_contains($fijada, 'muni-dt__scroll--col'))->toBeTrue('La prop sticky-column no fija la primera columna.');
    expect((bool) preg_match('/max-height:\s*60vh/', $fijada))->toBeTrue('La prop max-height no llega al contenedor.');
});

it('la cabecera fija repone su borde con una sombra interior y no migra a border-separate', function () {
    $css = cssTablaAncha();

    expect((bool) preg_match('/border-collapse\s*:\s*separate/', $css))->toBeFalse(
        'La tabla migró a border-separate: eso reescribe el @once entero y rompe la firma '.
        '.muni-row--danger, que pinta la franja con inset 3px 0 0 y no con un border.'
    );

    expect((bool) preg_match('/border-collapse\s*:\s*collapse/', $css))->toBeTrue(
        'La tabla ya no declara border-collapse:collapse.'
    );

    $cabecera = reglaCss($css, '.muni-dt__scroll--head');

    expect((bool) preg_match('/position\s*:\s*sticky/', $cabecera))->toBeTrue(
        'La regla de cabecera fija no declara position:sticky. Regla: '.$cabecera
    );

    expect((bool) preg_match('/box-shadow\s*:[^;]*inset\s+0\s+-1px\s+0\s+var\(--muni-border\)/', $cabecera))->toBeTrue(
        'Con border-collapse:collapse la cabecera fija pierde su borde inferior al desplazar: hay que '.
        'reponerlo con box-shadow inset 0 -1px 0 var(--muni-border), que sí viaja con la celda. '.
        'Regla: '.$cabecera
    );
});

it('la cabecera fija deja sitio al foco con scroll-margin sobre --muni-topbar-h', function () {
    $css = cssTablaAncha();

    expect((bool) preg_match('/scroll-margin-top\s*:[^;]*var\(--muni-topbar-h/', $css))->toBeTrue(
        'Ninguna regla declara scroll-margin-top sobre --muni-topbar-h: al tabular, la fila enfocada '.
        'se mete debajo de la cabecera fija y de la barra superior (WCAG 2.2 AA 2.4.11).'
    );
});

it('la primera columna fija lleva fondo opaco, conserva el hover y no le come el borde a la fila morosa', function () {
    $css = cssTablaAncha();

    $columna = reglaCss($css, '.muni-dt__scroll--col .muni-dt td:first-child');

    expect($columna)->not->toBe('', 'No hay regla para la primera columna fija.');

    expect((bool) preg_match('/position\s*:\s*sticky/', $columna))->toBeTrue('La primera columna no es sticky.');
    expect((bool) preg_match('/left\s*:\s*0/', $columna))->toBeTrue('La primera columna sticky no ancla en left:0.');

    expect((bool) preg_match('/background\s*:\s*var\(--muni-surface\)/', $columna))->toBeTrue(
        'La primera columna fija no tiene fondo opaco: el texto de las demás columnas se le pasa por '.
        'debajo al desplazar. Regla: '.$columna
    );

    $hover = reglaCss($css, '[data-muni-row]:hover td:first-child');

    expect((bool) preg_match('/background\s*:\s*var\(--muni-surface-2\)/', $hover))->toBeTrue(
        'El fondo opaco de la celda fija tapa el hover de la fila: la fila se ilumina entera salvo su '.
        'primera celda. Falta [data-muni-row]:hover td:first-child { background: var(--muni-surface-2) }.'
    );

    $morosa = reglaCss($css, '.muni-dt__scroll--col [data-muni-row].muni-row--danger td:first-child');

    expect($morosa)->not->toBe(
        '',
        'No hay regla que combine la banda de morosidad con el separador de la columna fija: como '.
        '.muni-row--danger es más específica, las filas rojas se quedan sin borde derecho.'
    );

    expect((bool) preg_match(
        '/box-shadow\s*:\s*inset\s+3px\s+0\s+0\s+var\(--muni-danger-fg\)\s*,\s*inset\s+-1px\s+0\s+0\s+var\(--muni-border\)/',
        $morosa
    ))->toBeTrue(
        'La banda de morosidad y el separador de la columna fija tienen que ir en UNA sola declaración '.
        'de box-shadow: dos reglas no se suman, la última gana. Regla: '.$morosa
    );
});

it('el z-index está escalonado: esquina sobre cabecera, cabecera sobre columna fija', function () {
    $css = cssTablaAncha();

    $esquina = zIndexDe($css, '.muni-dt__scroll--col .muni-dt thead th:first-child');
    $cabecera = zIndexDe($css, '.muni-dt__scroll--head .muni-dt thead th');
    $columna = zIndexDe($css, '.muni-dt__scroll--col .muni-dt td:first-child');

    expect($esquina)->not->toBeNull('La celda de esquina no declara z-index.');
    expect($cabecera)->not->toBeNull('La cabecera fija no declara z-index.');
    expect($columna)->not->toBeNull('La columna fija no declara z-index.');

    expect($esquina > $cabecera)->toBeTrue(
        "La celda de esquina (z-index {$esquina}) no queda sobre la cabecera (z-index {$cabecera}): al ".
        'desplazar en los dos ejes, la esquina se pierde debajo.'
    );

    expect($cabecera > $columna)->toBeTrue(
        "La cabecera fija (z-index {$cabecera}) no queda sobre la columna fija (z-index {$columna}): ".
        'las celdas de la primera columna pasan por encima de los encabezados.'
    );
});

it('la nómina impresa no sale recortada y repite la cabecera en cada página', function () {
    $css = cssTablaAncha();

    expect(str_contains($css, '@media print'))->toBeTrue(
        'No hay bloque @media print: con max-height y overflow, la nómina impresa sale recortada a las '.
        'filas que caben en el contenedor.'
    );

    preg_match('/@media print\s*\{(.*)\}/s', $css, $m);
    $impresion = $m[1] ?? '';

    foreach ([
        '/overflow\s*:\s*visible/' => 'overflow:visible',
        '/max-height\s*:\s*none/' => 'max-height:none',
        '/position\s*:\s*static/' => 'position:static',
        '/display\s*:\s*table-header-group/' => 'thead { display:table-header-group } (repite la cabecera en cada hoja del acta)',
    ] as $patron => $que) {
        expect((bool) preg_match($patron, $impresion))->toBeTrue(
            "El bloque @media print no declara {$que}."
        );
    }
});

it('la tabla ancha no escribe un solo color literal', function () {
    $css = cssTablaAncha();

    // El único hex permitido es el respaldo neutro del outline (DESIGN §5).
    $literales = array_values(array_filter(
        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $m) ? $m[0] : [],
        fn (string $hex) => strtolower($hex) !== '#767676',
    ));

    expect($literales)->toBe(
        [],
        'La tabla escribe colores literales, que ningún adoptante puede cambiar: '.implode(' | ', $literales)
    );
});

// ---------------------------------------------------------------------------
// pagination
// ---------------------------------------------------------------------------

it('en la página 1 el «Anterior» inerte no es un enlace ni se puede activar', function () {
    $html = paginadorHtml(':current="1" :total="170" :url="fn ($p) => \'?page=\'.$p"');

    preg_match_all('/<a\b[^>]*>/', $html, $enlaces);

    $inertes = array_values(array_filter(
        $enlaces[0] ?? [],
        fn (string $a) => str_contains($a, 'aria-disabled'),
    ));

    expect($inertes)->toBe(
        [],
        'El extremo inerte sigue siendo un <a aria-disabled>: pointer-events NO bloquea el teclado, así '.
        'que sigue en el orden de tabulación y Enter lo activa, navegando a «#» y perdiendo la posición '.
        'de scroll: '.implode(' | ', $inertes)
    );

    expect((bool) preg_match('/<span\b[^>]*aria-disabled="true"[^>]*>[^<]*Anterior/u', $html))->toBeTrue(
        'El «‹ Anterior» de la página 1 no es un <span aria-disabled>: NVDA y JAWS lo siguen leyendo en '.
        'modo exploración, pero deja de estar en el orden de tabulación. HTML: '.$html
    );

    expect((bool) preg_match('/tabindex/', $html))->toBeFalse(
        'El paginador mete tabindex a mano: un <span> ya está fuera del orden de tabulación sin ayuda.'
    );
});

it('en la última página el «Siguiente» inerte tampoco es un enlace', function () {
    $html = paginadorHtml(':current="170" :total="170" :url="fn ($p) => \'?page=\'.$p"');

    expect((bool) preg_match('/<span\b[^>]*aria-disabled="true"[^>]*>[^<]*Siguiente/u', $html))->toBeTrue(
        'El «Siguiente ›» de la última página sigue siendo activable: el funcionario que llega al final '.
        'con Tab queda atrapado en un enlace que no lleva a ninguna parte. HTML: '.$html
    );
});

it('el estado apagado se dibuja con un token de color, no con opacity ni pointer-events', function () {
    $html = paginadorHtml(':current="1" :total="170"');
    $css = cssPaginador();

    expect((bool) preg_match('/opacity\s*:/', $html.$css))->toBeFalse(
        'El extremo apagado usa opacity: no tiene contraparte oscura medible y, peor, atenúa al 40% el '.
        'propio anillo de foco (WCAG 2.2 AA 1.4.11).'
    );

    expect((bool) preg_match('/pointer-events\s*:\s*none/', $html.$css))->toBeFalse(
        'Sigue habiendo pointer-events:none: bloquea el ratón y deja pasar el teclado, que es justo el '.
        'defecto que se está reparando.'
    );

    $apagado = reglaCss($css, '.muni-page--off');

    expect((bool) preg_match('/color\s*:\s*var\(--muni-hint\)/', $apagado))->toBeTrue(
        'El estado apagado no sale de var(--muni-hint), que es el único token con rama clara y oscura '.
        'para esto. Regla: '.$apagado
    );
});

it('la página actual se marca con aria-current y con texto, no solo con color', function () {
    $html = paginadorHtml(':current="3" :total="170" :url="fn ($p) => \'?page=\'.$p"');

    expect((bool) preg_match('/aria-current="page"/', $html))->toBeTrue(
        'La página actual solo se distingue por el color de fondo, que es exactamente lo que DESIGN '.
        'prohíbe: falta aria-current="page".'
    );

    // El nombre accesible de la navegación dice dónde está el funcionario. Es lo
    // que reemplaza a la región viva: con enlace de carga completa o con
    // wire:navigate, el nodo se reemplaza y una región viva recién insertada no
    // dispara nunca.
    preg_match('/<nav\b[^>]*>/', $html, $nav);

    expect((bool) preg_match('/aria-label="[^"]*3[^"]*170[^"]*"/', $nav[0] ?? ''))->toBeTrue(
        'El <nav> no lleva la posición en su nombre accesible («Paginación, página 3 de 170»): al llegar '.
        'a la paginación no hay forma de saber en qué página se está. Etiqueta: '.($nav[0] ?? 'sin <nav>')
    );
});

it('no se cuela una región viva dentro del <nav>', function () {
    $html = paginadorHtml(':current="3" :total="170"');

    expect((bool) preg_match('/(role="status"|aria-live=)/', $html))->toBeFalse(
        'Hay una región viva dentro del paginador: con carga completa el navegador ya anuncia el '.
        'documento nuevo y con wire:navigate el nodo se reemplaza, así que nunca dispara. Sería una '.
        'casilla marcada en falso en el checklist WCAG. Si algún día hay paginación en sitio, el '.
        'anuncio va en una región persistente del app-shell.'
    );
});

it('acepta un LengthAwarePaginator y arma solo el rango y los enlaces', function () {
    $paginator = new LengthAwarePaginator(range(21, 40), 387, 20, 2, ['path' => 'https://muni.cl/padron']);

    $html = paginadorHtml(':paginator="$paginator"', ['paginator' => $paginator]);

    expect(str_contains($html, 'Mostrando 21–40 de 387'))->toBeTrue(
        'El paginador no arma el rango «Mostrando 21–40 de 387» desde el LengthAwarePaginator: hay que '.
        'seguir escribiéndolo a mano en la prop info. HTML: '.$html
    );

    expect((bool) preg_match('/aria-label="[^"]*2[^"]*20[^"]*"/', $html))->toBeTrue(
        'El paginador no toma la página actual ni la última del LengthAwarePaginator.'
    );

    expect(str_contains($html, 'https://muni.cl/padron?page=3'))->toBeTrue(
        'El paginador no construye los href con $paginator->url(): sigue exigiendo un closure. HTML: '.$html
    );
});

it('rechaza el paginador de simplePaginate() en vez de dibujar una última página inventada', function () {
    $simple = new Paginator(range(21, 40), 20, 2, ['path' => 'https://muni.cl/padron']);

    $motivo = motivoDe(fn () => paginadorHtml(':paginator="$paginator"', ['paginator' => $simple]));

    expect($motivo)->toBeInstanceOf(
        InvalidArgumentException::class,
        'El paginador de simplePaginate() no sabe cuántas páginas hay: dibujarle una última página '.
        'sería inventarle un final al padrón y dejar al funcionario convencido de que lo vio entero.'
    );

    expect(str_contains($motivo?->getMessage() ?? '', 'simplePaginate'))->toBeTrue(
        'El mensaje no nombra simplePaginate(), que es lo único que le dice a quien lo integra qué '.
        'cambiar. Mensaje: '.($motivo?->getMessage() ?? 'no reventó')
    );
});

it('un $url que no es callable revienta en desarrollo en vez de degradar a «#» en silencio', function () {
    expect(motivoDe(fn () => paginadorHtml(':current="3" :total="170" url="/padron"')))->toBeInstanceOf(
        InvalidArgumentException::class,
        'Un `url` que no es invocable degrada a «#» en silencio: el paginador se ve entero y ninguno '.
        'de sus enlaces cambia de página, solo salta al principio del documento.'
    );
});

it('sin forma de construir la URL no emite enlaces a «#»', function () {
    $html = paginadorHtml(':current="3" :total="170"');

    expect((bool) preg_match('/href="#"/', $html))->toBeFalse(
        'El paginador emite enlaces a «#»: activarlos con Enter salta al principio del documento y '.
        'pierde la posición de scroll, sin ir a ninguna página. HTML: '.$html
    );
});
