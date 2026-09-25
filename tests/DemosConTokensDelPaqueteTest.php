<?php

/*
|--------------------------------------------------------------------------
| LAS DEMOS NO SON FUENTE DE TOKENS — ficha `vitrina` de docs/GAP-ANALYSIS.md
|--------------------------------------------------------------------------
|
| La corrección del juez: `demo/showcase.html` redefinía la paleta con valores
| propios (en oscuro `--muni-accent:#f5a623` contra `#f59e0b`, `--muni-hint` a
| 2,7:1), y quien copiaba tokens de ahí se llevaba una paleta cuyo contraste
| nunca se midió. «Si sobreviven intactos, la paleta de 2,7:1 sigue en el repo y
| no se resolvió nada.» Se borró, y las otras demos arrastraban la misma
| enfermedad en chico: `--muni-hint` oscuro en #7a8ba8 (el paquete dice
| #7c8daa) y radios de 8/12/6 px que el paquete no tiene.
|
| DemosSinDefectosViejosTest caza valores CONOCIDOS por fallar. Esta caza el
| caso general: toda declaración `--muni-*` de una demo tiene que usar un valor
| que resources/css/muni-ui.css declara para ESE token en alguno de sus bloques
| (claro, oscuro, impresión, movimiento reducido). Un token que la hoja no
| conoce es un invento y también falla: la demo que necesite algo propio usa
| una variable con su propio prefijo.
|
| Helpers con prefijo `dtp` porque Pest carga todos los archivos en el mismo
| proceso.
*/

function dtpNormalizar(string $valor): string
{
    $v = strtolower((string) preg_replace('/\s+/', '', trim($valor)));
    $v = (string) preg_replace('/(?<![\d.])0\.(\d)/', '.$1', $v);          // 0.14 → .14
    $v = (string) preg_replace('/(\.\d*?)0+(?=\D|$)/', '$1', $v);          // .10 → .1
    $v = (string) preg_replace('/(?<=\D)\.(?=\D|$)/', '', $v);             // «1.» suelto
    $v = (string) preg_replace('/#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3\b/', '#$1$2$3', $v); // #ffffff → #fff

    return $v;
}

/** @return array<string, list<string>> token => valores normalizados que declara muni-ui.css */
function dtpValoresDelPaquete(): array
{
    static $valores = null;

    if ($valores !== null) {
        return $valores;
    }

    $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(__DIR__.'/../resources/css/muni-ui.css'));
    $valores = [];

    preg_match_all('/(--muni-[a-z0-9-]+)\s*:\s*([^;{}]+);/', $css, $m, PREG_SET_ORDER);
    foreach ($m as [, $token, $valor]) {
        $valores[$token][] = dtpNormalizar($valor);
    }

    return $valores;
}

/**
 * Las declaraciones `--muni-*` de un HTML que no están en la hoja del paquete.
 *
 * @return list<string>
 */
function dtpDivergencias(string $html): array
{
    // Fuera los <script>: el Alpine horneado no declara tokens, pero su código
    // minificado puede contener cualquier secuencia.
    $sinScripts = (string) preg_replace('#<script\b[^>]*>.*?</script>#si', '', $html);
    $sinComentarios = (string) preg_replace(['#/\*.*?\*/#s', '#<!--.*?-->#s'], '', $sinScripts);

    $paquete = dtpValoresDelPaquete();
    $fallas = [];

    preg_match_all('/(--muni-[a-z0-9-]+)\s*:\s*([^;}"]+)[;}"]/', $sinComentarios, $m, PREG_SET_ORDER);
    foreach ($m as [, $token, $valor]) {
        if (! isset($paquete[$token])) {
            $fallas[] = "{$token} no existe en muni-ui.css (vale «".trim($valor).'»)';

            continue;
        }

        if (! in_array(dtpNormalizar($valor), $paquete[$token], true)) {
            $fallas[] = "{$token}: «".trim($valor).'» no es ninguno de los valores del paquete';
        }
    }

    return array_values(array_unique($fallas));
}

/** @return array<string, array{0: string}> */
function dtpDemos(): array
{
    $casos = [];

    foreach (glob(__DIR__.'/../demo/*.html') ?: [] as $ruta) {
        $casos[basename($ruta)] = [$ruta];
    }

    return $casos;
}

it('no declara un token --muni-* con un valor que la hoja del paquete no tiene', function (string $ruta) {
    $fallas = dtpDivergencias((string) file_get_contents($ruta));

    expect($fallas)->toBe([], '«'.basename($ruta).'» redefine la paleta: '.implode(' · ', $fallas)
        .'. Los tokens se copian de resources/css/muni-ui.css, no se inventan en una demo.');
})->with(dtpDemos());

it('el candado ve una paleta divergente (la de showcase.html) y deja pasar la del paquete', function () {
    // Candado del candado: si la normalización se aflojara hasta aceptarlo todo,
    // la prueba de arriba pasaría en verde sin mirar nada.
    $showcase = '<style>.dark{--muni-accent:#f5a623;--muni-hint:#7a8ba8}:root{--muni-radius:8px}</style>';
    expect(dtpDivergencias($showcase))->toHaveCount(3);

    $paquete = '<style>:root{--muni-accent:#0f766e;--muni-shadow-lg:0 12px 32px rgba(16,24,40,.14)}'
        .'.dark{--muni-accent:#f59e0b;--muni-hint:#7c8daa}@media print{:root{--muni-hint:var(--muni-muted)}}</style>';
    expect(dtpDivergencias($paquete))->toBe([]);

    expect(dtpDivergencias('<style>:root{--muni-inventado:#123456}</style>'))->toHaveCount(1);
});

it('showcase.html no vuelve: la vitrina de verdad es la del workbench', function () {
    expect(is_file(__DIR__.'/../demo/showcase.html'))->toBeFalse(
        'demo/showcase.html volvió. La corrección del juez la sacó por su paleta propia; '
        .'la referencia de componentes es `vendor/bin/testbench serve` → /vitrina.'
    );
});

it('la ficha de persona está generada, en español, y el RUT solo aparece tapado', function () {
    $ruta = __DIR__.'/../demo/persona.html';
    expect(is_file($ruta))->toBeTrue('Falta demo/persona.html: php demo/persona.php');

    $html = (string) file_get_contents($ruta);

    expect(str_contains($html, '<html lang="es">'))->toBeTrue('persona.html sin lang="es"')
        // Como declaración, no como palabra: la hoja del paquete la cita en un comentario para prohibirla.
        ->and((bool) preg_match('/print-color-adjust\s*:/i', (string) preg_replace('#/\*.*?\*/#s', '', $html)))
        ->toBeFalse('persona.html no puede declarar print-color-adjust')
        // Marcado de los componentes reales, no una imitación escrita a mano.
        ->and(str_contains($html, 'data-muni-pii-campo="rut"'))->toBeTrue('No hay un <x-muni::pii> real')
        ->and(str_contains($html, '<dl class="muni-dl'))->toBeTrue('No hay un <x-muni::description-list> real')
        ->and(str_contains($html, 'role="tabpanel"'))->toBeTrue('No hay <x-muni::tab-panel> real')
        // Sin hojas ni scripts: los comentarios de muni-ui.css citan etiquetas <x-muni::…>.
        ->and(str_contains((string) preg_replace('#<(style|script)\b[^>]*>.*?</\1>#s', '', $html), '<x-muni::'))
        ->toBeFalse('Hay una etiqueta <x-muni::…> sin compilar');

    // Ley 21.719: el RUT completo existe UNA vez, dentro del valor tapado de pii
    // (display:none en línea hasta el gesto). Nunca suelto en la cabecera o la tabla.
    expect(substr_count($html, '12.345.678-5'))->toBe(1, 'El RUT completo aparece fuera de <x-muni::pii>');
    expect((bool) preg_match('#<span class="muni-pii__real"[^>]*style="display:none;"[^>]*>12\.345\.678-5</span>#', $html))
        ->toBeTrue('El RUT no nace tapado dentro de muni-pii__real');

    // La hoja propia de la demo no trae colores literales.
    preg_match('#<style data-demo="propia">(.*?)</style>#s', $html, $propia);
    expect($propia)->not->toBe([], 'Falta la hoja propia de persona.html');
    expect((bool) preg_match('/#[0-9a-fA-F]{3,8}\b/', (string) preg_replace('#/\*.*?\*/#s', '', $propia[1])))
        ->toBeFalse('Color literal en la hoja propia de persona.html');
});
