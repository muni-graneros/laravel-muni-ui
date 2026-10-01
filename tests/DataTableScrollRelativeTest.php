<?php

/**
 * CANDADO: Elemento sr-only absoluto dentro de contenedor scroll.
 *
 * El `<caption class="muni-sr">` de la tabla de datos está dentro de
 * `.muni-dt__scroll`, que tiene `overflow:auto`. Sin `position:relative` en el
 * contenedor, el elemento absoluto escapa del contexto de apilamiento y se
 * renderiza fuera del flujo, alargando la altura total de la página.
 *
 * Hallado en seguridad-graneros, commit ab05589b.
 */

use Illuminate\Support\Facades\Blade;

/** El CSS que la tabla de datos lleva en su `@once`, sin comentarios. */
function cssDataTable(): string
{
    $fuente = file_get_contents(__DIR__.'/../resources/views/components/data-table.blade.php');
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

test('El contenedor de scroll de la tabla tiene position:relative', function () {
    $css = cssDataTable();
    $regla = reglaCss($css, 'muni-dt__scroll');

    expect($regla)->toContain('position:relative', 'El contenedor .muni-dt__scroll debe tener position:relative para que los elementos sr-only absolutos no escapen del flujo');
});

test('El elemento sr-only de la tabla tiene position:absolute', function () {
    $css = cssDataTable();
    $regla = reglaCss($css, 'muni-sr');

    expect($regla)->toContain('position:absolute', 'El elemento .muni-sr debe tener position:absolute para estar oculto visualmente pero presente en el árbol de accesibilidad');
});

test('El contenedor de scroll de la tabla tiene overflow:auto', function () {
    $css = cssDataTable();
    $regla = reglaCss($css, 'muni-dt__scroll');

    expect($regla)->toContain('overflow:auto', 'El contenedor .muni-dt__scroll debe tener overflow:auto para permitir scroll horizontal');
});
