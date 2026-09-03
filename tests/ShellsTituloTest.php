<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/**
 * `<title>` es RCDATA: lo que va dentro es TEXTO, no marcado.
 *
 * Los tres shells emitían `<x-muni::reverb-meta />` dentro del `<title>`, así que
 * las `<meta>` de Reverb se volvían texto visible en la pestaña del navegador
 * (WCAG 2.4.2) y, peor, dejaban de existir como elementos: `echo.js` no
 * encontraba la clave y el tiempo real quedaba apagado con solo un aviso en
 * consola. Es el defecto que la memoria del ecosistema registra desde la 0.12.
 */
beforeEach(function () {
    // Sin clave configurada, `reverb-meta` no emite nada y el defecto no se
    // reproduce: el título sale limpio y el test pasa sin probar nada.
    config()->set('broadcasting.connections.reverb.key', 'clave-de-prueba');
});

it('el título de auth-shell es solo texto, y las meta de Reverb quedan fuera', function () {
    $html = Blade::render('<x-muni::auth-shell system="Licencias" title="Ingresar" />');

    expect((bool) preg_match('/<title>(.*?)<\/title>/s', $html, $m))->toBeTrue('auth-shell no emite <title>');

    expect(trim($m[1]))->toBe('Ingresar · Licencias',
        'El <title> lleva marcado adentro: se ve como texto en la pestaña y las <meta> no existen como elementos.'
    );

    expect(str_contains($html, 'name="reverb-key"'))->toBeTrue(
        'La meta de Reverb desapareció del documento: el tiempo real se queda sin clave.'
    );
});

it('el título de dashboard-shell es solo texto', function () {
    $html = Blade::render('<x-muni::dashboard-shell system="Panel" title="Escritorio" />');

    preg_match('/<title>(.*?)<\/title>/s', $html, $m);

    expect(trim($m[1] ?? ''))->toBe('Escritorio');
});

it('el título de app-shell es solo texto', function () {
    $html = Blade::render('<x-muni::app-shell system="Panel" title="Inicio" />');

    preg_match('/<title>(.*?)<\/title>/s', $html, $m);

    expect(trim($m[1] ?? ''))->toBe('Inicio');
});
