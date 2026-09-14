<?php

use Illuminate\Support\Facades\Blade;

/**
 * EL CANDADO DE LA DENSIDAD DE TABLA.
 *
 * La ficha `densidad` pedía un modo compacto GLOBAL del armazón; el juez lo bajó a
 * densidad de tabla y solo de tabla: un atributo `densidad` («comoda» por defecto,
 * «compacta») en `<x-muni::data-table>` y `<x-muni::sortable-table>`, más la
 * lectura de `[data-muni-densidad="compacta"]` que el anfitrión pinta en el
 * `<html>` desde una cookie leída en servidor y que las dos tablas heredan.
 *
 * Lo que este candado vigila, punto por punto de la corrección:
 *
 *   1. El alcance: las dos tablas y nada más. Ningún otro componente lee la
 *      densidad, porque comprimir la navegación no aporta ni una fila y sí come
 *      área táctil.
 *   2. Sin tokenizar el espaciado del paquete: las variables de fila son LOCALES
 *      del bloque de estilos de cada tabla, con prefijo propio y nunca `--muni-`.
 *      `tests/TemaFilamentTest.php` exige que todo `var(--muni-*)` leído por un
 *      componente exista en el tema del panel, y un `--muni-row-py` que solo vive
 *      dentro de un componente lo haría reventar.
 *   3. El piso de objetivo, que es lo que AdminLTE no hace: la fila encoge, el
 *      objetivo no. Todo `a`/`button` dentro de una celda mide al menos 24×24
 *      (WCAG 2.2 AA 2.5.8) y bajo `pointer: coarse` sube a 44×44 para la tablet
 *      de terreno.
 *   4. Nunca `height` fija: solo `padding-block` + `min-height`, para no romper
 *      1.4.12 Text Spacing en cuanto el usuario fuerza line-height 1.5. Y ni el
 *      outline del foco ni la banda de 3px de `.muni-row--danger` se tocan.
 *
 * Se comprueba sobre el HTML servido y sobre el CSS que cada tabla lleva en su
 * bloque de estilos (DESIGN §7: dentro de un panel Filament `muni-ui.css` no se
 * carga, así que lo que no viaja con el componente no existe).
 *
 * `toContain('valor', 'mensaje')` NO sirve acá: en Pest el segundo argumento es
 * otra cadena a buscar, no un mensaje. Por eso todo va con
 * `expect(bool)->toBeTrue('mensaje')`.
 */

/** El HTML servido de la tabla densa de la bandeja, con una acción por fila. */
function tablaDensaHtml(string $extra = ''): string
{
    return Blade::render(
        '<x-muni::data-table :columns="$columns" '.$extra.'>'
        .'<tr data-muni-row class="muni-row--danger">'
        .'<td class="muni-num">4821</td><td>Ana Soto</td><td>Alumbrado</td>'
        .'<td><a href="#">Ver</a> <button type="button">Derivar</button></td></tr>'
        .'</x-muni::data-table>',
        ['columns' => ['Folio', 'Vecino', 'Materia', 'Acciones']],
    );
}

/** El HTML servido de la tabla ordenable del padrón. */
function tablaOrdenableDensaHtml(string $extra = ''): string
{
    return Blade::render(
        '<x-muni::sortable-table :columns="$columns" :rows="$rows" '.$extra.' />',
        [
            'columns' => [['key' => 'rol', 'label' => 'Rol'], ['key' => 'monto', 'label' => 'Monto']],
            'rows' => [['rol' => '4501-2', 'monto' => '1.240.000', '_tone' => 'danger']],
        ],
    );
}

/**
 * La clase de la `<table>` servida.
 *
 * Se mira la ETIQUETA y no el documento entero a propósito: el bloque de estilos
 * del componente nombra `.muni-dt--compact` siempre —tiene que estar ahí para que
 * la herencia desde el documento del anfitrión funcione sin que la vista repita la
 * prop—, así que un `str_contains($html, 'muni-dt--compact')` daría verdadero
 * incluso con la tabla cómoda. Lo que dice si la tabla salió compacta es su clase.
 */
function claseDeTablaDensa(string $html): string
{
    return preg_match('/<table\b[^>]*class="([^"]*)"/', $html, $m) ? $m[1] : '';
}

/** El CSS que un componente lleva en sus bloques de estilos, sin comentarios. */
function cssDeComponenteDenso(string $componente): string
{
    $fuente = file_get_contents(__DIR__."/../resources/views/components/{$componente}.blade.php");

    // Primero los comentarios de Blade y después los de CSS: un `{{-- --}}` puede
    // envolver un `<style>` entero y contarlo sería medir CSS que nunca se emite.
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    preg_match_all('#<style>(.*?)</style>#s', $fuente, $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

/** Cada regla `{selector => cuerpo}` del CSS, con los `@media` desenvueltos. */
function reglasDensas(string $css): array
{
    // Se quitan las envolturas `@media … {` y sus cierres para que las reglas de
    // adentro salgan al mismo nivel; el bloque `@media` se busca aparte.
    $plano = (string) preg_replace('/@media[^{]*\{/', '', $css);

    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $plano, $reglas, PREG_SET_ORDER);

    $salida = [];

    foreach ($reglas as [, $selector, $cuerpo]) {
        $salida[] = [trim((string) preg_replace('/\s+/', ' ', $selector)), trim($cuerpo)];
    }

    return $salida;
}

/** El cuerpo de la primera regla cuyo selector contiene `$aguja`. */
function cuerpoDeReglaDensa(string $css, string $aguja): string
{
    foreach (reglasDensas($css) as [$selector, $cuerpo]) {
        if (str_contains($selector, $aguja)) {
            return $cuerpo;
        }
    }

    return '';
}

/** El contenido del bloque `@media` cuya condición contiene `$condicion`. */
function bloqueMediaDenso(string $css, string $condicion): string
{
    if (preg_match('/@media[^{]*'.preg_quote($condicion, '/').'[^{]*\{((?:[^{}]*\{[^{}]*\})*)/s', $css, $m)) {
        return $m[1];
    }

    return '';
}

/**
 * La causa REAL de lo que reventó al renderizar: `CompilerEngine` envuelve
 * cualquier excepción del componente en una `ViewException`, así que se baja
 * por `getPrevious()` hasta la original. Mismo recurso que `tests/OrdenYLoteTest.php`.
 */
function causaDeRenderDenso(callable $accion): ?Throwable
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
// La prop `densidad` en las dos tablas
// ---------------------------------------------------------------------------

it('sin la prop, data-table sale cómoda: ni una clase de compacto en el HTML', function () {
    $html = tablaDensaHtml();

    expect(str_contains(claseDeTablaDensa($html), 'muni-dt--compact'))->toBeFalse(
        'La tabla sale compacta sin que nadie lo pidiera: cambia cómo se ve una nómina corta que hoy está bien.'
    );
});

it('data-table con densidad="compacta" emite la clase que su bloque de estilos redefine', function () {
    $html = tablaDensaHtml('densidad="compacta"');

    expect((bool) preg_match('/<table\b[^>]*class="[^"]*muni-dt--compact/', $html))->toBeTrue(
        'densidad="compacta" no llega a la <table>: la prop existe pero no cambia nada. HTML: '.$html
    );

    expect(cuerpoDeReglaDensa(cssDeComponenteDenso('data-table'), '.muni-dt--compact'))->not->toBe(
        '',
        'La clase muni-dt--compact se emite pero ninguna regla del bloque de estilos la estila: es una clase fantasma.'
    );
});

it('data-table acepta «cómoda» con tilde y sigue aceptando la prop density heredada', function () {
    expect(str_contains(claseDeTablaDensa(tablaDensaHtml('densidad="cómoda"')), 'muni-dt--compact'))->toBeFalse(
        'densidad="cómoda" (con tilde) se interpreta como compacta o revienta: hay que aceptar las dos grafías.'
    );

    // Aditivo (DESIGN §10): `density="compact"` ya estaba publicada y hay consumidores.
    expect(str_contains(claseDeTablaDensa(tablaDensaHtml('density="compact"')), 'muni-dt--compact'))->toBeTrue(
        'La prop density="compact" heredada dejó de funcionar: se rompe a quien ya la usaba.'
    );
});

it('sortable-table gana la misma prop y emite su propia clase de compacto', function () {
    $porDefecto = tablaOrdenableDensaHtml();

    expect(str_contains(claseDeTablaDensa($porDefecto), 'muni-st--compact'))->toBeFalse(
        'La tabla ordenable sale compacta por defecto.'
    );

    $compacta = tablaOrdenableDensaHtml('densidad="compacta"');

    expect((bool) preg_match('/<table\b[^>]*class="[^"]*muni-st--compact/', $compacta))->toBeTrue(
        'densidad="compacta" no llega a la <table> de sortable-table. HTML: '.$compacta
    );

    expect(cuerpoDeReglaDensa(cssDeComponenteDenso('sortable-table'), '.muni-st--compact'))->not->toBe(
        '',
        'La clase muni-st--compact se emite pero ninguna regla la estila.'
    );
});

it('una densidad que no existe revienta en desarrollo en vez de caer a cómoda en silencio', function () {
    foreach (['data-table' => fn () => tablaDensaHtml('densidad="compacto"'),
        'sortable-table' => fn () => tablaOrdenableDensaHtml('densidad="compacto"')] as $tabla => $render) {
        $causa = causaDeRenderDenso($render);

        expect($causa)->toBeInstanceOf(
            InvalidArgumentException::class,
            "{$tabla}: densidad=\"compacto\" (una errata de «compacta») no revienta: la tabla sale cómoda y nadie ".
            'se entera de que la prop está mal escrita.'
        );

        expect(str_contains($causa?->getMessage() ?? '', 'compacta'))->toBeTrue(
            "{$tabla}: el mensaje no dice cuáles son los valores válidos. Mensaje: ".($causa?->getMessage() ?? 'no reventó')
        );
    }
});

// ---------------------------------------------------------------------------
// La herencia desde el <html> del anfitrión
// ---------------------------------------------------------------------------

it('las dos tablas heredan [data-muni-densidad="compacta"] del <html> del anfitrión', function () {
    foreach (['data-table' => ['.muni-dt', '.muni-dt--compact'], 'sortable-table' => ['.muni-st', '.muni-st--compact']] as $componente => [$tabla, $clase]) {
        $css = cssDeComponenteDenso($componente);

        $heredada = '';

        foreach (reglasDensas($css) as [$selector, $cuerpo]) {
            // La tabla MISMA, no cualquier clase que empiece igual. Sin el corte de
            // `(?![\w-])`, la regla de excepción —`… .muni-dt--comoda`, la que
            // devuelve a cómoda una tabla suelta dentro de un panel compacto— daba
            // por buena la herencia, y se podía borrar justo la regla que la hace
            // sin que este candado dijera nada. Se pilló mutando la copia.
            if (preg_match('/\[data-muni-densidad="compacta"\]\s+'.preg_quote($tabla, '/').'(?![\w-])/', $selector)) {
                $heredada = $cuerpo;

                break;
            }
        }

        expect($heredada)->not->toBe(
            '',
            "{$componente}: ningún selector combina [data-muni-densidad=\"compacta\"] con {$tabla}. El anfitrión ".
            'pinta ese atributo en el <html> desde una cookie leída en servidor y la tabla tiene que heredarlo '.
            'sin que cada vista repita la prop.'
        );

        // Y hereda LO MISMO que la prop: si el atributo del anfitrión redefiniera
        // otras variables, la misma densidad se vería distinta según de dónde venga.
        preg_match_all('/(--[a-z0-9-]+)\s*:/', cuerpoDeReglaDensa($css, $clase), $porProp);
        preg_match_all('/(--[a-z0-9-]+)\s*:/', $heredada, $porHerencia);

        expect(array_values(array_unique($porHerencia[1] ?? [])))->toBe(
            array_values(array_unique($porProp[1] ?? [])),
            "{$componente}: la herencia y la prop no redefinen las mismas variables. Por herencia: ".
            implode(', ', $porHerencia[1] ?? []).' · por prop: '.implode(', ', $porProp[1] ?? [])
        );
    }
});

it('la prop y la herencia redefinen las MISMAS variables locales: un solo lugar donde vive el compacto', function () {
    foreach (['data-table' => ['.muni-dt--compact', '--mdt-'], 'sortable-table' => ['.muni-st--compact', '--mst-']] as $componente => [$clase, $prefijo]) {
        $css = cssDeComponenteDenso($componente);
        $compacto = cuerpoDeReglaDensa($css, $clase);

        preg_match_all('/(--[a-z0-9-]+)\s*:/', $compacto, $m);

        expect($m[1] ?? [])->not->toBeEmpty(
            "{$componente}: la regla de compacto no redefine variables, escribe paddings a mano. Regla: {$compacto}"
        );

        foreach ($m[1] as $variable) {
            expect(str_starts_with($variable, $prefijo))->toBeTrue(
                "{$componente}: la variable «{$variable}» no lleva el prefijo local «{$prefijo}». Las variables de ".
                'fila son LOCALES del bloque de estilos y nunca --muni-: la guarda de TemaFilamentTest exige que todo '.
                '--muni-* leído exista en las dos hojas del paquete.'
            );
        }
    }
});

it('no se tokeniza el espaciado del paquete: ninguna tabla escribe --muni-row-*', function () {
    foreach (['data-table', 'sortable-table'] as $componente) {
        expect((bool) preg_match('/--muni-row-/', cssDeComponenteDenso($componente)))->toBeFalse(
            "{$componente}: usa --muni-row-*, que no existe en muni-ui.css ni en muni-ui-filament.css. Dentro de un ".
            'panel Filament se vería sin padding y sin un solo error en consola (DESIGN §10).'
        );
    }
});

// ---------------------------------------------------------------------------
// El piso de objetivo: la fila encoge, el objetivo no
// ---------------------------------------------------------------------------

it('todo enlace o botón dentro de una celda de data-table mide al menos 24×24', function () {
    $css = cssDeComponenteDenso('data-table');

    $piso = '';

    foreach (reglasDensas($css) as [$selector, $cuerpo]) {
        if (preg_match('/td\s+a\b/', $selector) && preg_match('/td\s+button\b/', $selector)) {
            $piso = $cuerpo;

            break;
        }
    }

    expect($piso)->not->toBe(
        '',
        'No hay una regla para `td a` y `td button` juntos: a 24px de fila, dos botones de acción contiguos en la '.
        'columna final pierden la excepción de espaciado de 2.5.8 y quedan bajo el mínimo.'
    );

    foreach ([
        '/display\s*:\s*inline-flex/' => 'display:inline-flex',
        '/align-items\s*:\s*center/' => 'align-items:center',
        '/min-height\s*:\s*24px/' => 'min-height:24px',
        '/min-width\s*:\s*24px/' => 'min-width:24px',
    ] as $patron => $que) {
        expect((bool) preg_match($patron, $piso))->toBeTrue(
            "El piso de objetivo no declara {$que}. Regla: {$piso}"
        );
    }
});

it('bajo pointer: coarse el objetivo sube a 44×44 para la tablet de terreno', function () {
    $css = cssDeComponenteDenso('data-table');

    $terreno = bloqueMediaDenso($css, 'pointer: coarse');

    expect($terreno)->not->toBe(
        '',
        'No hay bloque @media (pointer: coarse): en la tablet de terreno el objetivo se queda en 24px y el '.
        'criterio del ecosistema pide 44×44.'
    );

    expect((bool) preg_match('/min-height\s*:\s*44px/', $terreno) && (bool) preg_match('/min-width\s*:\s*44px/', $terreno))->toBeTrue(
        'El bloque de pointer: coarse no sube el objetivo a min-height:44px y min-width:44px. Bloque: '.$terreno
    );
});

// ---------------------------------------------------------------------------
// Lo que el compacto NO puede tocar
// ---------------------------------------------------------------------------

it('ninguna tabla fija height: solo padding-block y min-height, por 1.4.12 Text Spacing', function () {
    foreach (['data-table' => '.muni-dt', 'sortable-table' => '.muni-st'] as $componente => $tabla) {
        $css = cssDeComponenteDenso($componente);

        $conAlto = [];

        foreach (reglasDensas($css) as [$selector, $cuerpo]) {
            // Solo las reglas de fila y celda: la utilidad de texto oculto lleva
            // `height:1px` a propósito y la casilla nativa tiene su tamaño.
            if (! preg_match('/\b(tr|td|th)\b/', $selector) && ! str_contains($selector, 'compact') && ! str_contains($selector, 'densidad')) {
                continue;
            }

            if (preg_match('/(?<![a-z-])height\s*:/', $cuerpo)) {
                $conAlto[] = $selector.' { '.$cuerpo.' }';
            }
        }

        expect($conAlto)->toBe(
            [],
            "{$componente}: hay reglas de fila con `height` fija. En cuanto el usuario fuerza line-height 1.5 ".
            '(WCAG 2.2 AA 1.4.12) el texto se recorta. Solo padding-block + min-height: '.implode(' | ', $conAlto)
        );
    }
});

it('el compacto usa las variables en el padding de celda y cabecera, no valores sueltos', function () {
    foreach (['data-table' => ['.muni-dt th', '--mdt-'], 'sortable-table' => ['.muni-st th', '--mst-']] as $componente => [$cabecera, $prefijo]) {
        $css = cssDeComponenteDenso($componente);

        $th = cuerpoDeReglaDensa($css, $cabecera);

        expect((bool) preg_match('/padding[^;]*var\('.preg_quote($prefijo, '/').'/', $th))->toBeTrue(
            "{$componente}: la cabecera no lee su padding de una variable {$prefijo}*: el compacto no la alcanza. Regla: {$th}"
        );

        $td = '';

        foreach (reglasDensas($css) as [$selector, $cuerpo]) {
            if (preg_match('/\btd\b/', $selector) && str_contains($cuerpo, 'padding') && ! str_contains($selector, 'first-child')) {
                $td = $cuerpo;

                break;
            }
        }

        expect((bool) preg_match('/padding[^;]*var\('.preg_quote($prefijo, '/').'/', $td))->toBeTrue(
            "{$componente}: la celda no lee su padding de una variable {$prefijo}*: el compacto no la alcanza. Regla: {$td}"
        );
    }
});

/*
 * Lo que este candado comprueba es que el compacto no se lleve por delante la
 * banda: la REGLA sigue escrita y ninguna regla de densidad toca outline ni
 * box-shadow. No comprueba que la banda se pinte, y en `sortable-table` HOY NO SE
 * PINTA: sus filas salen de un <template x-for>, que queda en el DOM como primer
 * hijo del <tr>, así que `.muni-st__danger td:first-child` no casa con ninguna
 * celda (medido en Chromium sobre el banco: box-shadow «none», color normal).
 * En `data-table`, que sirve las filas desde el servidor, sí se pinta en las tres
 * densidades (3px inset, medido). Es un defecto propio de sortable-table, anterior
 * a la densidad y ajeno a ella: arreglarlo cambia cómo se ve la tabla para todos
 * los consumidores y necesita su propia ficha y su propia prueba.
 */
it('el compacto no toca el outline del foco ni la banda de 3px de la fila con problema', function () {
    foreach (['data-table' => '.muni-row--danger', 'sortable-table' => '.muni-st__danger'] as $componente => $banda) {
        $css = cssDeComponenteDenso($componente);

        expect((bool) preg_match('/'.preg_quote($banda, '/').'[^{]*\{[^}]*box-shadow\s*:\s*inset\s+3px\s+0\s+0\s+var\(--muni-danger-fg\)/', $css))->toBeTrue(
            "{$componente}: la banda de 3px de {$banda} ya no está: es la firma del sistema y su único portador de ".
            'estado no cromático (DESIGN §9).'
        );

        foreach (reglasDensas($css) as [$selector, $cuerpo]) {
            if (str_contains($selector, 'compact') || str_contains($selector, 'densidad')) {
                expect((bool) preg_match('/(outline|box-shadow)\s*:/', $cuerpo))->toBeFalse(
                    "{$componente}: la regla de compacto «{$selector}» toca outline o box-shadow: el modo compacto ".
                    'no puede reducir ni eliminar el indicador de foco ni la banda. Regla: '.$cuerpo
                );
            }
        }
    }
});

it('el compacto no cambia el HTML servido más allá de la clase: cero tokens ni colores nuevos', function () {
    $css = cssDeComponenteDenso('data-table').cssDeComponenteDenso('sortable-table');

    $literales = array_values(array_filter(
        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $m) ? $m[0] : [],
        fn (string $hex) => strtolower($hex) !== '#767676',
    ));

    expect($literales)->toBe(
        [],
        'Las tablas escriben colores literales, que ningún adoptante puede cambiar: '.implode(' | ', $literales)
    );
});

it('el alcance es solo tabla: ningún otro componente lee la densidad', function () {
    $lectores = [];

    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') ?: [] as $vista) {
        $nombre = basename($vista, '.blade.php');

        if (in_array($nombre, ['data-table', 'sortable-table'], true)) {
            continue;
        }

        $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($vista));
        $fuente = (string) preg_replace('#/\*.*?\*/#s', '', $fuente);

        if (str_contains($fuente, 'data-muni-densidad') || preg_match('/\$densidad\b/', $fuente)) {
            $lectores[] = $nombre;
        }
    }

    expect($lectores)->toBe(
        [],
        'Estos componentes leen la densidad y no son tablas: comprimir la navegación no aporta ni una fila '.
        'y sí come área táctil (corrección del juez): '.implode(', ', $lectores)
    );
});

// ---------------------------------------------------------------------------
// La firma del sistema: la banda de 3px tiene que PINTARSE, no solo estar escrita
// ---------------------------------------------------------------------------

/*
 * El candado anterior daba por buena la banda de `sortable-table` con solo
 * encontrar la regla escrita en el CSS, y en el navegador esa regla no pintaba
 * nada: las filas salen de un `<template x-for>`, que el navegador deja en el DOM
 * como PRIMER hijo del `<tr>`, así que `td:first-child` no casa con ninguna celda
 * y `.muni-st__pick + td` tampoco (el hermano siguiente de la casilla es el
 * `<template>`, no la celda). Medido en Chromium: `box-shadow: none`.
 *
 * Una aserción sobre la presencia de una cadena no es un candado; esto comprueba
 * que el selector que lleva la banda sea uno que el marcado servido PUEDE casar.
 * La medición de verdad está en `tests/navegador/densidad-de-tabla.py`.
 */
it('la banda de sortable-table no puede colgar de :first-child ni de `+ td`: entre el <tr> y sus celdas hay un <template x-for>', function () {
    $html = tablaOrdenableDensaHtml();

    expect((bool) preg_match('/<tr\b[^>]*>\s*<template\b/', $html))->toBeTrue(
        'El <tr> de sortable-table ya no empieza por un <template x-for>. Si el marcado cambió, este candado '.
        'sobra y hay que revisarlo; mientras empiece por el template, `td:first-child` no casa con nada. HTML: '.$html
    );

    $css = cssDeComponenteDenso('sortable-table');

    foreach (reglasDensas($css) as [$selector, $cuerpo]) {
        if (! str_contains($selector, 'muni-st__danger')) {
            continue;
        }

        expect((bool) preg_match('/td:first-child|\+\s*td\b/', $selector))->toBeFalse(
            "La regla «{$selector}» de la fila morosa cuelga de un selector que el marcado no produce: entre el ".
            '<tr> y sus celdas hay un <template x-for>, así que :first-child y `+ td` no casan con ninguna celda '.
            'y la banda no se pinta (DESIGN §9: es la firma del sistema y su único portador de estado no cromático).'
        );
    }
});

it('en sortable-table la banda va en la primera celda de la fila y el rojo del dato en la primera de datos', function () {
    $css = cssDeComponenteDenso('sortable-table');

    $banda = '';
    $tinta = '';

    foreach (reglasDensas($css) as [$selector, $cuerpo]) {
        if (! str_contains($selector, 'muni-st__danger')) {
            continue;
        }

        if ($banda === '' && preg_match('/box-shadow\s*:\s*inset\s+3px\s+0\s+0\s+var\(--muni-danger-fg\)/', $cuerpo)) {
            $banda = $selector;
        }

        if ($tinta === '' && preg_match('/color\s*:/', $cuerpo)) {
            $tinta = $selector;
        }
    }

    // `:first-of-type` sí ignora el <template> y es lo que de verdad selecciona la
    // primera celda de la fila: la casilla cuando hay selección, el primer dato
    // cuando no la hay. Es el borde izquierdo de la fila, que es donde va la banda.
    expect((bool) preg_match('/td:first-of-type/', $banda))->toBeTrue(
        'La banda de 3px no cuelga de `td:first-of-type`, que es el único selector que salta el <template x-for> '.
        "y cae en la primera celda real de la fila. Selector actual: «{$banda}»"
    );

    // El rojo y la negrita son del DATO: con la columna de selección puesta, la
    // primera celda es la casilla y pintarla dejaría el control en rojo y el dato
    // en negro (ficha seleccion-lote, punto 4).
    expect(str_contains($tinta, 'muni-st__first') && ! str_contains($tinta, 'first-of-type'))->toBeTrue(
        'El color del dato no cae en la primera celda de DATOS (`.muni-st__first`), que es la que la fila marca '.
        "por índice de columna. Selector actual: «{$tinta}»"
    );

    // …y esa clase la tiene que poner el marcado, o la regla es otra clase fantasma.
    foreach (['' => 'sin selección', 'selectable row-key="rol"' => 'con columna de selección'] as $extra => $caso) {
        expect((bool) preg_match('/<td[^>]*:class="[^"]*muni-st__first/', tablaOrdenableDensaHtml($extra)))->toBeTrue(
            "{$caso}: ninguna celda recibe la clase muni-st__first, así que la regla del color no alcanza a nadie."
        );
    }
});

it('el piso de objetivo está en las DOS tablas y en las dos dimensiones', function () {
    foreach (['data-table' => '.muni-dt', 'sortable-table' => '.muni-st'] as $componente => $tabla) {
        $css = cssDeComponenteDenso($componente);

        $piso = '';

        foreach (reglasDensas($css) as [$selector, $cuerpo]) {
            if (preg_match('/td\s+a\b/', $selector) && preg_match('/td\s+button\b/', $selector)) {
                $piso = $cuerpo;

                break;
            }
        }

        expect($piso)->not->toBe(
            '',
            "{$componente}: no hay regla para `td a` y `td button` juntos. La corrección del juez pide el piso de ".
            'objetivo en las DOS tablas, no en una.'
        );

        foreach (['/min-height\s*:\s*24px/' => 'min-height:24px', '/min-width\s*:\s*24px/' => 'min-width:24px'] as $patron => $que) {
            expect((bool) preg_match($patron, $piso))->toBeTrue("{$componente}: el piso no declara {$que}. Regla: {$piso}");
        }

        $terreno = bloqueMediaDenso($css, 'pointer: coarse');

        expect((bool) preg_match('/min-width\s*:\s*44px/', $terreno))->toBeTrue(
            "{$componente}: bajo `pointer: coarse` el objetivo no sube a min-width:44px, solo de alto. El criterio ".
            'del ecosistema pide 44×44 en terreno, que son dos dimensiones. Bloque: '.$terreno
        );
    }

    // El único control que vive en una celda de sortable-table es el botón de
    // orden de la cabecera: tiene que cumplir el mismo piso, y con las dos medidas.
    // El selector EXACTO: `cuerpoDeReglaDensa()` busca por subcadena y
    // `.muni-st__sortable` —el <th>— empieza igual que `.muni-st__sort`.
    $sort = '';

    foreach (reglasDensas(cssDeComponenteDenso('sortable-table')) as [$selector, $cuerpo]) {
        if ($selector === '.muni-st__sort') {
            $sort = $cuerpo;

            break;
        }
    }

    expect((bool) preg_match('/min-width\s*:\s*24px/', $sort))->toBeTrue(
        'El botón de orden declara min-height pero no min-width: el piso de 2.5.8 es de 24×24, no de 24 de alto. '.
        'Regla: '.$sort
    );
});

// ---------------------------------------------------------------------------
// El banco de navegador: lo único que mide si la fila ENCOGE de verdad
// ---------------------------------------------------------------------------

/** Alpine local para el banco: sin él la tabla ordenable no pinta ni una fila. */
function alpineParaBancoDenso(): ?string
{
    $candidatos = array_merge(
        [__DIR__.'/../node_modules/alpinejs/dist/cdn.min.js'],
        array_filter(
            glob(__DIR__.'/../scripts/.cache/alpine-*.min.js') ?: [],
            fn (string $ruta) => ! str_contains(basename($ruta), 'alpine-focus-'),
        ),
    );

    foreach ($candidatos as $candidato) {
        if (is_file($candidato)) {
            return (string) file_get_contents($candidato);
        }
    }

    return null;
}

/*
 * El banco de navegador. Se genera acá y no en un script suelto porque este es el
 * único sitio del paquete con Blade arrancado (mismo criterio que
 * `GeneraVitrinaTest`, `CifraComparadaTest` y `GuardiaDeSesionTest`). Sale a
 * `build/densidad-de-tabla/`, ignorado por git.
 *
 * Existe porque TODO lo que este archivo comprueba arriba es texto del .blade.php,
 * y eso ya engañó una vez en esta misma ficha: la banda de 3px de `sortable-table`
 * estaba escrita en el CSS —y el candado en verde— mientras en el navegador
 * computaba `box-shadow: none`, porque el selector no casaba con ninguna celda. Lo
 * que de verdad importa de la densidad solo se ve con estilos computados:
 *
 *   · que la fila ENCOJA (alto medido, no padding declarado);
 *   · que la herencia desde `<html data-muni-densidad="compacta">` alcance a la
 *     tabla y que `densidad="comoda"` le gane;
 *   · que con el relleno en 4px el enlace de la celda de acciones siga midiendo
 *     24×24 (WCAG 2.2 AA 2.5.8);
 *   · que la banda de la fila morosa se pinte en las dos tablas, con y sin columna
 *     de selección, y en los dos temas;
 *   · que con `line-height: 1.5` forzado (1.4.12 Text Spacing) no se recorte
 *     ninguna celda, que es lo que pasaría con una `height` fija;
 *   · y cuántas filas más caben de verdad a 1366×768, que es la medición que la
 *     corrección del juez pedía ANTES de codear y que no era reproducible desde
 *     el repo.
 */
it('genera el banco de navegador en build/densidad-de-tabla/', function () {
    $dir = __DIR__.'/../build/densidad-de-tabla';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $columnas = ['Folio', 'Vecino', 'Materia', 'Acciones'];

    $fila = function (int $i) {
        $morosa = $i % 3 === 0;

        return '<tr data-muni-row'.($morosa ? ' class="muni-row--danger"' : '').'>'
            .'<td class="muni-num">'.(4800 + $i).'</td>'
            .'<td>Ana Soto Miranda</td>'
            .'<td>Alumbrado público</td>'
            .'<td><a href="#ver">Ver</a> <button type="button">Derivar</button></td>'
            .'</tr>';
    };

    $filas = implode('', array_map($fila, range(1, 3)));
    $nomina = implode('', array_map($fila, range(1, 40)));

    $columnasSt = [
        ['key' => 'rol', 'label' => 'Rol', 'mono' => true],
        ['key' => 'vecino', 'label' => 'Contribuyente'],
        ['key' => 'monto', 'label' => 'Monto', 'align' => 'right'],
    ];

    $filasSt = [
        ['rol' => '4501-2', 'vecino' => 'Ana Soto Miranda', 'monto' => '1.240.000', '_tone' => 'danger'],
        ['rol' => '4502-9', 'vecino' => 'Luis Pérez Vidal', 'monto' => '320.500'],
        ['rol' => '4503-7', 'vecino' => 'Rosa Díaz Núñez', 'monto' => '98.000'],
    ];

    // Cada caso va envuelto en un `[data-caso]` y el guion lo mide por ese nombre.
    $caso = fn (string $nombre, string $blade) => '<section data-caso="'.$nombre.'"><h2>'.$nombre.'</h2>'.$blade.'</section>';

    $cuerpo = Blade::render(
        $caso('dt-defecto', '<x-muni::data-table :columns="$columnas" caption="Bandeja">'.$filas.'</x-muni::data-table>')
        .$caso('dt-compacta', '<x-muni::data-table :columns="$columnas" densidad="compacta" caption="Bandeja compacta">'.$filas.'</x-muni::data-table>')
        .$caso('dt-comoda', '<x-muni::data-table :columns="$columnas" densidad="comoda" caption="Bandeja cómoda">'.$filas.'</x-muni::data-table>')
        .$caso('st-defecto', '<x-muni::sortable-table :columns="$columnasSt" :rows="$filasSt" caption="Padrón" />')
        .$caso('st-compacta', '<x-muni::sortable-table :columns="$columnasSt" :rows="$filasSt" densidad="compacta" caption="Padrón compacto" />')
        .$caso('st-comoda', '<x-muni::sortable-table :columns="$columnasSt" :rows="$filasSt" densidad="comoda" caption="Padrón cómodo" />')
        .$caso('st-seleccion', '<x-muni::sortable-table :columns="$columnasSt" :rows="$filasSt" densidad="compacta" selectable row-key="rol" caption="Padrón en lote" />')
        .$caso('nomina-comoda', '<x-muni::data-table :columns="$columnas" densidad="comoda" caption="Nómina cómoda">'.$nomina.'</x-muni::data-table>')
        .$caso('nomina-compacta', '<x-muni::data-table :columns="$columnas" densidad="compacta" caption="Nómina compacta">'.$nomina.'</x-muni::data-table>'),
        ['columnas' => $columnas, 'columnasSt' => $columnasSt, 'filasSt' => $filasSt],
    );

    $alpine = alpineParaBancoDenso();

    expect($alpine)->not->toBeNull(
        'Sin Alpine el banco no sirve: `sortable-table` no pinta ni una fila y la banda de la fila morosa no se '.
        'podría medir. Falta node_modules/alpinejs (npm install).'
    );

    /* El enlace de la celda va SIN estilar a propósito —es el peor caso del piso
       de objetivo: un <a> pelado no trae ni min-height ni min-width propios, así
       que lo que lo sostiene en 24x24 es la regla del componente y nada más—, pero
       el banco le pone el color del texto: el azul por defecto del navegador da
       1,93:1 sobre la superficie oscura y ensuciaría la medición de contraste con
       un defecto que es del banco y no del componente. (En la vitrina la celda de
       acciones lleva <x-muni::button>, que es lo que usan los sistemas.) */
    $armazon = 'body{margin:0;padding:16px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
        .'h2{font-size:12px;font-weight:700;margin:16px 0 6px;color:var(--muni-muted)}'
        .'section:first-child h2{margin-top:0}'
        .'[data-caso] td a, [data-caso] td button{color:var(--muni-text);background:none;border:0;font:inherit;text-decoration:underline;cursor:pointer}';

    // Las cuatro páginas: los dos temas × con y sin el modo compacto del
    // anfitrión pintado en el <html>, que es la única vía de persistencia que el
    // diseño contempla (la cookie la lee el host EN SERVIDOR; el paquete no).
    $paginas = [
        'claro' => ['light', false],
        'oscuro' => ['dark', false],
        'anfitrion-claro' => ['light', true],
        'anfitrion-oscuro' => ['dark', true],
    ];

    foreach ($paginas as $nombre => [$tema, $anfitrionCompacto]) {
        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'
            .($tema === 'dark' ? ' class="dark"' : '')
            .($anfitrionCompacto ? ' data-muni-densidad="compacta"' : '').'>'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco densidad — '.$nombre.'</title>'
            .'<style>'.cssMuniUi().'</style><style>'.$armazon.'</style></head>'
            .'<body><main id="muni-contenido" tabindex="-1">'.$cuerpo.'</main>'
            .'<script>'.$alpine.'</script></body></html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDeLaRejaDensa(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que ningún test de Blade puede ver, medido en Chromium y en Firefox sobre el
 * banco. Se SALTA —no se finge— cuando no está `.venv-a11y`, que en CI no se
 * instala.
 */
it('en Chromium y Firefox: la fila encoge, el objetivo no baja de 24×24, la herencia del anfitrión llega y la banda se pinta', function () {
    $python = pythonDeLaRejaDensa();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/densidad-de-tabla.py').' '
        .escapeshellarg(__DIR__.'/../build/densidad-de-tabla').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de la densidad de tabla falló:\n".implode("\n", $lineas));

    // La medición que la corrección del juez pedía ANTES de codear, ahora
    // reproducible desde el repo y no desde un banco en /tmp.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'filas por pantallada'))))->toBeGreaterThan(0,
        "El guion no reportó cuántas filas caben a 1366×768 en cada densidad:\n".implode("\n", $lineas));
});

/*
 * La reja oficial de accesibilidad (`scripts/a11y-check.py`, la misma de
 * `npm run a11y:vitrina`) sobre el banco: contraste real de todo el texto y
 * axe-core, en los DOS temas. Se corre sobre una sola de las cuatro páginas
 * porque el contenido es idéntico —lo que cambia entre ellas es el atributo del
 * anfitrión y el tema del documento, y la reja fuerza los dos temas ella misma—,
 * y una página son ya 543 textos medidos: las tres densidades, la fila morosa y
 * la columna de selección.
 *
 * Hacía falta porque la reja nunca había medido estas dos tablas: `data-table` no
 * está en `ejemplosDeVitrina()` (hay que agregarlo; la entrada va en el informe de
 * la ficha) y la densidad compacta no existía cuando se midió `sortable-table`.
 */
it('la reja de accesibilidad pasa sobre el banco denso en los dos temas', function () {
    if (pythonDeLaRejaDensa() === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la reja se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        'cd '.escapeshellarg(dirname(__DIR__)).' && python3 '.escapeshellarg(__DIR__.'/../scripts/a11y-check.py').' '
        .escapeshellarg(__DIR__.'/../build/densidad-de-tabla/claro.html').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La reja de accesibilidad falla sobre la tabla densa:\n".implode("\n", array_slice($lineas, -40)));
});
