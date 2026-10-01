<?php

/**
 * CANDADO: elemento sr-only absoluto dentro de un contenedor con scroll.
 *
 * El `<caption class="muni-sr">` de una tabla está dentro de un contenedor con
 * `overflow`. Sin `position:relative` en ese contenedor, el elemento absoluto
 * se ancla a un ancestro lejano y alarga el documento entero.
 *
 * Hallado en seguridad-graneros, commit ab05589b.
 */

/** El CSS de un componente en su `<style>`, sin comentarios. */
function cssDeComponente(string $nombre): string
{
    $fuente = file_get_contents(__DIR__.'/../resources/views/components/'.$nombre.'.blade.php');
    preg_match_all('#<style>(.*?)</style>#s', $fuente, $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

test('El contenedor de scroll de data-table tiene position:relative', function () {
    $regla = reglaCss(cssDeComponente('data-table'), '.muni-dt__scroll');

    expect($regla)->toContain('overflow:auto')
        ->and($regla)->toContain('position:relative');
});

test('El contenedor con overflow de sortable-table tiene position:relative', function () {
    $fuente = file_get_contents(__DIR__.'/../resources/views/components/sortable-table.blade.php');

    expect(preg_match('/<div style="([^"]*overflow-x:auto[^"]*)">\s*<table/', $fuente, $m))->toBe(1)
        ->and($m[1])->toContain('position:relative');
});

test('El marco de diff-campos, que contiene .muni-dc__sr, tiene position:relative', function () {
    $regla = reglaCss(cssDeComponente('diff-campos'), '.muni-dc__marco');

    expect($regla)->toContain('overflow:hidden')
        ->and($regla)->toContain('position:relative');
});

test('.muni-sr sigue siendo absoluto: lo que se ancla es el contenedor, no el texto', function () {
    expect(reglaCss(cssDeComponente('data-table'), '.muni-sr'))->toContain('position:absolute');
});
