<?php

/*
 * El catálogo (demo/catalogo.html) es la documentación del paquete. Estas
 * pruebas cuidan que no se quede atrás: todo componente tiene ejemplo, toda prop
 * está descrita, y todo ejemplo y receta renderiza en un Laravel real, con el
 * Blade y los componentes tal como los recibe un sistema host.
 */

require_once __DIR__.'/../demo/catalogo/lib.php';

function catalogoDir(): string
{
    return __DIR__.'/../demo/catalogo';
}

function catalogoComponentesDelPaquete(): array
{
    return array_map(
        fn ($f) => basename($f, '.blade.php'),
        glob(__DIR__.'/../resources/views/components/*.blade.php'),
    );
}

it('documenta todos los componentes del paquete en el catálogo', function () {
    $documentados = [];
    foreach (require catalogoDir().'/componentes.php' as $componentes) {
        foreach ($componentes as $nombre => $info) {
            $documentados[] = $nombre;
            array_push($documentados, ...($info['incluye'] ?? []));
        }
    }

    expect(array_diff(catalogoComponentesDelPaquete(), $documentados))->toBe([]);
});

it('tiene un ejemplo por cada componente del índice', function () {
    foreach (require catalogoDir().'/componentes.php' as $componentes) {
        foreach (array_keys($componentes) as $nombre) {
            expect(catalogoDir()."/ejemplos/{$nombre}.blade.php")->toBeFile();
        }
    }
});

it('describe cada prop con un comentario en su @props', function () {
    $sinDoc = [];
    foreach (catalogoComponentesDelPaquete() as $nombre) {
        foreach (componente_meta(__DIR__."/../resources/views/components/{$nombre}.blade.php")['props'] as $p) {
            if ($p['doc'] === null) {
                $sinDoc[] = "{$nombre}.{$p['nombre']}";
            }
        }
    }

    expect($sinDoc)->toBe([]);
});

it('renderiza cada ejemplo del catálogo sin errores', function (string $archivo) {
    $this->withoutVite();

    $html = view()->file($archivo)->render();

    expect($html)->not->toBeEmpty()
        ->and($html)->not->toContain('<x-muni::');
})->with(fn () => glob(catalogoDir().'/ejemplos/*.blade.php'));

it('renderiza cada receta del catálogo sin errores', function (string $archivo) {
    $this->withoutVite();

    expect(view()->file($archivo)->render())->not->toContain('<x-muni::');
})->with(fn () => glob(catalogoDir().'/recetas/*.blade.php'));

/*
 * muni-ui.css declara cada tema más de una vez: el oscuro para la preferencia del
 * OS y para los activadores explícitos (.dark, data-theme, data-muni-theme), y el
 * claro en :root y en el override data-muni-theme="light". Si una copia cambia y la
 * otra no, el mismo sistema se ve distinto según cómo se activó el tema, y nadie lo
 * nota en la máquina donde se hizo el cambio.
 */
it('mantiene idénticas las copias de cada tema en muni-ui.css', function () {
    $t = tokens_css(file_get_contents(__DIR__.'/../resources/css/muni-ui.css'));
    // Los que solo declara :root (radios, duraciones) no cambian con el tema.
    $invariantes = array_diff_key($t['light'], $t['dark']);

    expect($t['dark_os'])->toBe($t['dark'])
        ->and($t['light_override'])->toBe(array_diff_key($t['light'], $invariantes))
        ->and(array_keys($t['dark']))->toEqualCanonicalizing(array_keys($t['light_override']));
});
