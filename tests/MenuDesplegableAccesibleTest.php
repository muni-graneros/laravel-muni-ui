<?php

use Illuminate\Support\Facades\Blade;

/**
 * D15 — `dropdown` colgaba `aria-expanded` y `aria-haspopup="menu"` de un
 * `<div>` sin rol.
 *
 * axe-core lo marca `aria-allowed-attr` con impacto `critical`: esos dos
 * atributos solo son válidos sobre un elemento cuyo rol los admita, y el rol
 * implícito de un `<div>` es `generic`, que no admite ninguno de los dos. Un
 * lector de pantalla no anuncia ni que hay un menú ni si está abierto.
 *
 * Y era peor que un defecto de anuncio: un `<div>` no recibe foco, así que el
 * desplegable solo se abría si el consumidor metía un control enfocable en el
 * slot `trigger`. El teclado dependía de que el host adivinara.
 *
 * Nadie lo veía porque la vitrina no renderiza `dropdown`.
 *
 * El patrón correcto es el de `<x-muni::popover>`, ya verificado en los tres
 * motores: el disparador es un `<button>` de verdad y los atributos van ahí.
 */

/** Renderiza un desplegable completo con el slot `trigger` que se le pase. */
function desplegableRendido(string $trigger = '<x-slot:trigger>Acciones</x-slot:trigger>', string $atributos = ''): string
{
    return Blade::render(
        '<x-muni::dropdown '.$atributos.'>'.$trigger.
        '<x-muni::dropdown-item>Ver ficha</x-muni::dropdown-item>'.
        '</x-muni::dropdown>'
    );
}

/**
 * Las etiquetas de apertura del HTML, como pares [nombre, atributos].
 *
 * @return array<int, array{0: string, 1: string}>
 */
function etiquetasDeApertura(string $html): array
{
    preg_match_all('/<([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/s', $html, $m, PREG_SET_ORDER);

    return array_map(fn (array $t) => [strtolower($t[1]), $t[2]], $m);
}

/*
 * Elementos cuyo rol IMPLÍCITO admite aria-expanded / aria-haspopup. Un <div>
 * y un <span> no están —ese es exactamente el defecto—, y tampoco hace falta
 * que estén: para eso existe un `role` explícito.
 */
const ETIQUETAS_CON_ROL_QUE_ADMITE_EXPANDED = ['button', 'a', 'input', 'select', 'summary', 'td', 'th'];

it('los atributos ARIA del desplegable viven en un elemento con rol válido, no en un <div> pelado', function () {
    $html = desplegableRendido();

    $conAria = array_filter(
        etiquetasDeApertura($html),
        fn (array $etiqueta) => preg_match('/aria-(?:expanded|haspopup)/i', $etiqueta[1]) === 1
    );

    // Si mañana alguien «arregla» el aviso de axe borrando los atributos, el
    // menú deja de anunciarse y la prueba tiene que caerse igual.
    expect($conAria)->not->toBeEmpty(
        'El desplegable no declara aria-expanded ni aria-haspopup en ninguna parte: el lector de '.
        'pantalla no puede anunciar que hay un menú ni si está abierto.'
    );

    foreach ($conAria as [$nombre, $atributos]) {
        $tieneRolExplicito = preg_match('/\brole\s*=/i', $atributos) === 1;

        expect(in_array($nombre, ETIQUETAS_CON_ROL_QUE_ADMITE_EXPANDED, true) || $tieneRolExplicito)->toBeTrue(
            "aria-expanded/aria-haspopup sobre <{$nombre}> sin rol. axe-core lo reprueba como ".
            'aria-allowed-attr con impacto critical: el rol implícito de ese elemento no admite esos atributos.'
        );
    }
});

it('el disparador es un <button type="button"> enfocable, y lo es también sin slot trigger', function () {
    foreach ([desplegableRendido(), desplegableRendido('')] as $html) {
        $disparador = array_values(array_filter(
            etiquetasDeApertura($html),
            fn (array $e) => $e[0] === 'button' && preg_match('/aria-haspopup\s*=\s*"menu"/i', $e[1]) === 1
        ));

        expect($disparador)->toHaveCount(1, 'Tiene que haber exactamente un disparador de menú y ser un <button>.');
        expect($disparador[0][1])->toMatch('/type\s*=\s*"button"/',
            'Sin type="button" el disparador envía el formulario que lo contenga en vez de abrir el menú.'
        );
        expect($disparador[0][1])->toMatch('/aria-expanded\s*=\s*"false"/',
            'El aria-expanded inicial tiene que venir en el HTML del servidor: sin JS el lector lee un botón mudo.'
        );
    }
});

it('sin slot trigger el botón igual tiene nombre accesible, y no queda un botón vacío', function () {
    $html = desplegableRendido('');

    // Un <button> sin contenido ni aria-label es «button-name», critical en axe.
    expect($html)->toMatch('/<button[^>]*>\s*(?:<[^>]+>\s*)*[^\s<][^<]*/',
        'El disparador sin slot `trigger` quedó sin texto: eso es un botón sin nombre accesible.'
    );
    expect($html)->toContain('Abrir menú');
});

/*
 * RETROCOMPATIBILIDAD. El slot `trigger` existe desde el primer día y hasta hoy
 * el consumidor TENÍA que meter ahí su propio control: el envoltorio era un
 * `<div>`, así que sin un botón propio el menú no se abría con teclado. Meter
 * ese botón dentro del nuestro sería cambiar un fallo critical de axe
 * (aria-allowed-attr) por otro (nested-interactive), y además rompe el orden de
 * tabulación: el navegador no sabe cuál de los dos botones activar.
 */
it('si el consumidor trae su propio botón no se anida un botón dentro de otro', function () {
    $html = desplegableRendido('<x-slot:trigger><x-muni::button variant="ghost">Acciones</x-muni::button></x-slot:trigger>');

    // El recuento salta el contenido entrecomillado: `<button>` escrito dentro
    // de un atributo —un comentario en el x-data, por ejemplo— no es una
    // etiqueta y contarlo daría un falso positivo.
    preg_match_all('/<(\/?)button\b((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/si', $html, $m, PREG_SET_ORDER);

    $profundidad = 0;
    $anidados = 0;

    foreach ($m as $marca) {
        if ($marca[1] === '/') {
            $profundidad--;

            continue;
        }

        $profundidad++;

        if ($profundidad > 1) {
            $anidados++;
        }
    }

    expect($anidados)->toBe(0,
        'El desplegable metió un <button> dentro del <button> que trae el consumidor en el slot `trigger`. '.
        'axe lo reprueba como nested-interactive, critical.'
    );

    // Y en ese camino los atributos ARIA tampoco pueden caer en un <div>: los
    // pone Alpine sobre el control del consumidor, que sí tiene rol.
    foreach (etiquetasDeApertura($html) as [$nombre, $atributos]) {
        if (preg_match('/aria-(?:expanded|haspopup)/i', $atributos) !== 1) {
            continue;
        }

        expect(in_array($nombre, ETIQUETAS_CON_ROL_QUE_ADMITE_EXPANDED, true) || preg_match('/\brole\s*=/i', $atributos) === 1)
            ->toBeTrue("Con disparador propio, el ARIA cayó en <{$nombre}> sin rol.");
    }
});

it('el desplegable conserva sus props y el scope de Alpine del que cuelga dropdown-item', function () {
    $html = desplegableRendido('<x-slot:trigger>Acciones</x-slot:trigger>', 'align="start" width="300px" data-prueba="1"');

    expect($html)->toContain('min-width:300px');
    expect($html)->toContain('left:0;');
    expect($html)->toContain('data-prueba="1"');
    expect($html)->toContain('role="menu"');
    expect($html)->toContain('x-cloak');

    // `dropdown-item` hace @click="open = false" leyendo el scope del padre.
    expect($html)->toMatch('/open:\s*false/',
        'Se perdió `open` del x-data: dropdown-item truena con «open is not defined».'
    );

    $fin = desplegableRendido('<x-slot:trigger>Acciones</x-slot:trigger>', 'align="end"');
    expect($fin)->toContain('right:0;');
});

/*
 * El barrido del paquete entero. D15 se coló porque la vitrina no renderiza
 * `dropdown`: la prueba de arriba cierra ESE componente, y esta cierra la
 * familia, incluidos los que nadie pinta todavía.
 */
it('ningún componente del paquete cuelga aria-expanded o aria-haspopup de un elemento sin rol', function () {
    $fallos = [];

    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') as $ruta) {
        $fuente = file_get_contents($ruta);

        // Fuera los comentarios, o se matchea la prosa que DESCRIBE el defecto.
        // (El `{{-- --}}` de Blade no llega al HTML; el `/* */` de un bloque
        // <style> sí, pero tampoco es marcado.)
        $fuente = preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);
        $fuente = preg_replace('#/\*.*?\*/#s', '', $fuente);

        $nombre = basename($ruta);
        $desplazamiento = 0;

        while (preg_match('/(?::|x-bind:)?aria-(?:expanded|haspopup)/i', $fuente, $m, PREG_OFFSET_CAPTURE, $desplazamiento)) {
            $posicion = $m[0][1];
            $desplazamiento = $posicion + strlen($m[0][0]);

            $apertura = strrpos(substr($fuente, 0, $posicion), '<');

            if ($apertura === false) {
                continue;
            }

            // Un `aria-…` que aparece dentro de una cadena de JavaScript
            // (setAttribute) no es marcado: lo escribe el componente sobre un
            // elemento que ya existe, y ese elemento lo cubre el resto del
            // archivo.
            $trozo = substr($fuente, $apertura, $posicion - $apertura);

            if (str_contains($trozo, 'setAttribute')) {
                continue;
            }

            if (! preg_match('/^<([a-zA-Z][a-zA-Z0-9-]*)/', substr($fuente, $apertura), $etiqueta)) {
                continue;
            }

            $tag = strtolower($etiqueta[1]);
            $siguiente = strpos($fuente, '<', $apertura + 1);
            $cuerpoEtiqueta = substr($fuente, $apertura, ($siguiente === false ? strlen($fuente) : $siguiente) - $apertura);

            if (in_array($tag, ETIQUETAS_CON_ROL_QUE_ADMITE_EXPANDED, true) || preg_match('/\brole\s*=/i', $cuerpoEtiqueta) === 1) {
                continue;
            }

            $fallos[] = "{$nombre}: <{$tag}> lleva ".trim($m[0][0]);
        }
    }

    expect($fallos)->toBe([],
        "aria-expanded/aria-haspopup sobre elementos sin rol que los admita (axe: aria-allowed-attr, critical):\n  ".
        implode("\n  ", $fallos)
    );
});

/*
|--------------------------------------------------------------------------
| D16 — `role="menu"` sin el patrón de la APG
|--------------------------------------------------------------------------
|
| `dropdown` declaraba `role="menu"` y sus ítems `role="menuitem"`, pero no
| cumplía una sola línea del contrato: no se recorría con flechas, no tenía
| roving tabindex —los ítems caían uno a uno en el orden de tabulación, que no
| es como se navega un menú—, Escape no devolvía el foco al disparador y el
| foco no quedaba contenido mientras el menú estaba abierto.
|
| Declarar un rol y no cumplirlo es PEOR que no declararlo: el lector de
| pantalla anuncia «menú, 4 elementos» y después el teclado no responde como el
| usuario espera.
|
| La salida elegida es implementar el patrón, no bajarlo a `disclosure`:
| `<x-muni::popover>` YA es el panel flotante genérico —su propia cabecera dice
| «Patrón DISCLOSURE, no menú»— y degradar `dropdown` dejaría dos componentes
| con el mismo contrato. Además `dropdown-item` emite `<button type="button">`
| cuando no hay `href` y trae `tone="danger"`: es un menú de ACCIONES.
|
| Estas pruebas son de fuente y solo cierran la puerta a la regresión. El
| recorrido real —quién tiene el foco en cada tecla— se mide en Chromium y en
| Firefox, porque el teclado no se verifica leyendo HTML.
*/

/** El HTML sin sus comentarios de bloque: dentro de un `x-data` esos viajan al navegador. */
function menuSinComentarios(string $html): string
{
    return (string) preg_replace('#/\*.*?\*/#s', '', $html);
}

/** Un desplegable con `$cuantos` ítems, que es donde el roving tabindex significa algo. */
function desplegableConItems(int $cuantos = 3): string
{
    $items = '';

    for ($i = 1; $i <= $cuantos; $i++) {
        $items .= '<x-muni::dropdown-item href="#i'.$i.'">Acción '.$i.'</x-muni::dropdown-item>';
    }

    return Blade::render('<x-muni::dropdown><x-slot:trigger>Acciones</x-slot:trigger>'.$items.'</x-muni::dropdown>');
}

/** Los atributos del elemento que lleva `role="menu"`, ya sin comentarios. */
function marcaDelMenu(string $html): string
{
    foreach (etiquetasDeApertura(menuSinComentarios($html)) as [, $atributos]) {
        if (preg_match('/\brole\s*=\s*"menu"/i', $atributos) === 1) {
            return $atributos;
        }
    }

    return '';
}

it('el menú se recorre con las flechas y con Home/End, y el recorrido es circular', function () {
    $menu = marcaDelMenu(desplegableConItems());

    expect($menu)->not->toBe('', 'No se encontró el elemento con role="menu".');

    foreach (['keydown.down', 'keydown.up', 'keydown.home', 'keydown.end'] as $enlace) {
        expect(str_contains($menu, $enlace))->toBeTrue(
            "El elemento con role=\"menu\" no enlaza `{$enlace}`. Un menú que declara el rol y no se ".
            'recorre con flechas miente: el lector anuncia «menú» y el teclado no responde.'
        );
    }

    // El salto circular (del último al primero) solo se consigue normalizando el
    // índice con módulo. La prueba de verdad es la del navegador; esta evita que
    // alguien cambie el módulo por un `Math.min/max` y tope en los extremos.
    expect(preg_match('/%\s*items\.length/', menuSinComentarios(desplegableConItems())))->toBe(1,
        'El recorrido no normaliza el índice con módulo: las flechas topan en el primer y último ítem '.
        'en vez de dar la vuelta, como pide el patrón menu button de la APG.'
    );
});

it('el disparador abre el menú con ↓ y con ↑, no solo con Enter y Espacio', function () {
    $html = menuSinComentarios(desplegableConItems());

    $disparador = array_values(array_filter(
        etiquetasDeApertura($html),
        fn (array $e) => $e[0] === 'button' && preg_match('/aria-haspopup\s*=\s*"menu"/i', $e[1]) === 1
    ));

    expect($disparador)->toHaveCount(1);
    expect(str_contains($disparador[0][1], 'keydown.down'))->toBeTrue(
        '↓ sobre el disparador tiene que abrir el menú y dejar el foco en el primer ítem (APG, menu button).'
    );
    expect(str_contains($disparador[0][1], 'keydown.up'))->toBeTrue(
        '↑ sobre el disparador tiene que abrir el menú y dejar el foco en el ÚLTIMO ítem (APG, menu button).'
    );
});

it('Escape se enlaza en el elemento y nunca en window, y Tab cierra el menú', function () {
    $html = menuSinComentarios(desplegableConItems());

    // Regla del repo: `command-palette` y `dropdown` enlazaban Escape en window
    // por instancia y se pisaban entre sí. Con el foco contenido, la tecla llega
    // igual por burbujeo.
    expect(str_contains($html, 'keydown.escape.window'))->toBeFalse(
        'Escape sigue enlazado en window: dos desplegables montados en la misma página se pisan, '.
        'y uno cierra al otro.'
    );
    expect(preg_match('/@keydown\.escape(?!\.window)/', $html))->toBe(1,
        'Nadie escucha Escape en el elemento: el menú no se cierra con teclado.'
    );

    expect(str_contains(marcaDelMenu($html), 'keydown.tab'))->toBeTrue(
        'Tab no cierra el menú. Con el foco contenido, tabular dentro de un menú abierto deja al '.
        'usuario dando vueltas sin salida.'
    );
});

it('al cerrar con teclado el foco vuelve al disparador, y mientras está abierto no se escapa', function () {
    $html = menuSinComentarios(desplegableConItems());

    expect(str_contains($html, 'x-ref="disparador"'))->toBeTrue(
        'El disparador propio no tiene referencia: el componente no sabe a quién devolverle el foco al cerrar.'
    );
    expect(preg_match('/\.focus\(\)/', $html))->toBe(1,
        'Nadie mueve el foco en el componente: ni al abrir (primer ítem) ni al cerrar (disparador). '.
        'Escape deja el foco en el vacío y el lector vuelve al principio del documento.'
    );

    // `x-trap` viaja en el bundle de Livewire y lo usan modal, drawer,
    // command-palette y sidebar. `.noreturn` es deliberado: la devolución del
    // foco la decide el componente (Escape y Tab sí, click fuera no).
    expect(preg_match('/x-trap[\w.]*\s*=/', marcaDelMenu($html)))->toBe(1,
        'El menú abierto no contiene el foco (DESIGN y CLAUDE.md: todo lo que se abre atrapa el foco).'
    );
});

it('los ítems nacen con tabindex="-1": un menú se recorre con flechas, no con Tab', function () {
    $html = desplegableConItems(4);

    $items = array_values(array_filter(
        etiquetasDeApertura($html),
        fn (array $e) => preg_match('/\brole\s*=\s*"menuitem"/i', $e[1]) === 1
    ));

    expect($items)->toHaveCount(4, 'Se esperaban cuatro menuitem renderizados.');

    foreach ($items as [$nombre, $atributos]) {
        expect(preg_match('/\btabindex\s*=\s*"-1"/', $atributos))->toBe(1,
            "<{$nombre} role=\"menuitem\"> sale del servidor sin tabindex=\"-1\". Sin roving tabindex ".
            'cada ítem es una parada de Tab: un menú de diez acciones son diez tabulaciones, y esa no '.
            'es la promesa de role="menu".'
        );
    }

    // Y el roving se mantiene: al moverse, el activo pasa a 0 y el resto a -1.
    expect(str_contains(menuSinComentarios($html), "setAttribute('tabindex'"))->toBeTrue(
        'Nadie mueve el tabindex al ítem activo: el foco no puede llegar a un elemento con tabindex=-1 '.
        'por sí solo, así que el menú quedaría inalcanzable.'
    );
});
