<?php

/*
|--------------------------------------------------------------------------
| LA FRANJA INSTITUCIONAL VA EN TODO DISEÑO, CLARO Y OSCURO
|--------------------------------------------------------------------------
|
| La barra de 7 colores del sitio madre es la firma que hace que cada subdominio
| se lea como municipal. Antes solo aparecía si el sistema ponía a mano gob-bar o
| gob-footer: los armazones no la traían y había pantallas enteras sin ella.
| Ahora la ponen los propios armazones y la hoja imprimible, y este candado lo
| exige. Helpers con prefijo `franja`.
*/

use Illuminate\Support\Facades\Blade;

function franjaCuenta(string $html): int
{
    return substr_count($html, 'class="muni-gob-stripe');
}

it('cada armazón y la hoja traen la franja institucional', function (string $blade) {
    $this->withoutVite();

    expect(franjaCuenta(Blade::render($blade)))->toBe(1);
})->with([
    'app-shell' => ['<x-muni::app-shell system="Patentes">x</x-muni::app-shell>'],
    'dashboard-shell' => ['<x-muni::dashboard-shell system="Registro">x</x-muni::dashboard-shell>'],
    'auth-shell' => ['<x-muni::auth-shell>x</x-muni::auth-shell>'],
    'error-page' => ['<x-muni::error-page />'],
    'topbar' => ['<x-muni::topbar system="Feria" />'],
    'hoja' => ['<x-muni::hoja unit="Dirección de Tránsito">x</x-muni::hoja>'],
]);

it('la franja de la barra se puede apagar en una pantalla excepcional', function () {
    expect(franjaCuenta(Blade::render('<x-muni::topbar system="Feria" :franja="false" />')))->toBe(0);
});

it('no pinta dos franjas cuando la página ya trae gob-bar', function () {
    foreach (['topbar' => 'muni-topbar__franja', 'dashboard-shell' => 'muni-ds__franja'] as $componente => $clase) {
        expect(fuenteDeLaVista($componente))->toContain("body:has(.muni-gob-bar) .{$clase}");
    }
});

it('la franja se dibuja con bordes para salir también en papel, sin forzar el color de impresión', function () {
    $fuente = fuenteDeLaVista('gob-stripe');

    expect($fuente)->toContain('border-top')
        ->and($fuente)->not->toContain('linear-gradient')
        ->and((bool) preg_match('/print-color-adjust\s*:/i', $fuente))->toBeFalse();
});

it('el panel Filament lleva la franja arriba de la barra, igual que el login', function () {
    $css = (string) file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');

    expect($css)->toMatch('/\.fi-topbar::before\{[^}]*var\(--mg-lima\)[^}]*var\(--mg-carmin\)/')
        ->and($css)->toMatch('/\.fi-simple-layout::before\{[^}]*var\(--mg-lima\)/');
});
