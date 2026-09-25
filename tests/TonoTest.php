<?php

use Muni\Ui\Tono;

it('traduce cada tono a su token y cae al color por defecto que pide el componente', function () {
    expect(Tono::color('danger'))->toBe('var(--muni-danger-fg)')
        ->and(Tono::color('inventado'))->toBe('var(--muni-accent)')
        ->and(Tono::color(null, 'neutral'))->toBe('var(--muni-text)');
});

/*
 * El mapa de tonos se copió en ocho componentes y las copias se separaron (rating
 * conocía tres tonos, kpi no conocía accent). Este candado impide que vuelva a
 * aparecer un mapa propio en un componente.
 */
it('ningún componente define su propio mapa de tonos', function () {
    $conMapa = [];
    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') as $archivo) {
        if (preg_match("/'ok'\s*=>\s*'var\(--muni-ok-fg\)'/", file_get_contents($archivo))) {
            $conMapa[] = basename($archivo);
        }
    }

    expect($conMapa)->toBe([]);
});
