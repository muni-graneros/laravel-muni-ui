<?php

use Illuminate\Support\Facades\Blade;

/*
 * El mensaje de error de un campo no puede depender de dónde lo ponga el anfitrión.
 *
 * Medido sobre los tokens reales del paquete, `--muni-danger-fg` como texto suelto
 * sobre la superficie del host da, en modo oscuro:
 *
 *     sobre --muni-surface     #111620   4,81:1   pasa
 *     sobre --muni-surface-2   #181e2a   4,44:1   FALLA
 *     sobre --muni-surface-3   #1c2333   4,17:1   FALLA
 *     sobre --muni-danger-bg   #2d0d0d   4,75:1   pasa
 *
 * Las dos superficies que fallan son justo las de una tarjeta y las de una sección
 * de formulario, o sea el sitio normal de un campo. Por eso el mensaje lleva su
 * propio fondo: así el contraste queda fijado por el componente y no por la
 * decisión del sistema que lo usa. `file-dropzone` ya lo resolvió así; esta prueba
 * evita que los demás vuelvan a quedarse sin fondo.
 */

/** @return array<string, array{0: string}> */
function camposConMensajeDeError(): array
{
    return [
        'input' => ['input'],
        'select' => ['select'],
        'switch' => ['switch'],
    ];
}

/** Contraste WCAG entre dos colores hexadecimales. */
function contraste(string $a, string $b): float
{
    $lum = function (string $hex): float {
        $hex = ltrim($hex, '#');
        $canal = function (float $v): float {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $canal((float) hexdec(substr($hex, 0, 2)))
            + 0.7152 * $canal((float) hexdec(substr($hex, 2, 2)))
            + 0.0722 * $canal((float) hexdec(substr($hex, 4, 2)));
    };

    $la = $lum($a);
    $lb = $lum($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

it('el mensaje de error lleva su propio fondo y no el del anfitrión', function (string $componente) {
    $html = Blade::render(
        '<x-muni::'.$componente.' label="RUT" name="rut" error="El dígito verificador no corresponde." />'
    );

    expect($html)->toContain('El dígito verificador no corresponde.');

    // El bloque que contiene el mensaje tiene que fijar su propio fondo de estado.
    expect($html)->toMatch('/color:var\(--muni-danger-fg\);background:var\(--muni-danger-bg\)/',
        "«{$componente}» pinta el error como texto suelto sobre la superficie del anfitrión. ".
        'En oscuro eso da 4,17:1 sobre --muni-surface-3, bajo el 4,5:1 de WCAG 1.4.3.'
    );
})->with(camposConMensajeDeError());

it('el par de color del error pasa AA en los dos temas', function () {
    // Los valores salen de resources/views/css/muni-ui.css; si alguien los cambia,
    // esta prueba lo detecta antes de que llegue a un panel.
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui.css');

    preg_match_all('/--muni-danger-fg:\s*(#[0-9a-fA-F]{6})/', $css, $fg);
    preg_match_all('/--muni-danger-bg:\s*(#[0-9a-fA-F]{6})/', $css, $bg);

    expect($fg[1])->not->toBeEmpty()->and($bg[1])->not->toBeEmpty();

    // Se comprueban todos los pares declarados: claro, oscuro y los bloques de
    // activación explícita, que repiten los mismos valores.
    foreach (array_unique(array_map(null, $fg[1], $bg[1]), SORT_REGULAR) as [$texto, $fondo]) {
        $r = contraste($texto, $fondo);
        expect(round($r, 2))->toBeGreaterThanOrEqual(
            4.5,
            "El par de error {$texto} sobre {$fondo} da ".round($r, 2).':1, bajo el 4,5:1 de WCAG 1.4.3.'
        );
    }
});
