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
