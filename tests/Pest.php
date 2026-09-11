<?php

use Muni\Ui\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Medición de contraste WCAG, compartida
|--------------------------------------------------------------------------
|
| Vivían dentro de `ContrasteTokensTest.php`. Se suben acá porque ahora las usa
| también `FocoVisibleTest.php`, y depender de que un archivo de test declare
| funciones para otro deja el resultado atado al orden alfabético con que Pest
| carga los archivos: un renombre y la suite muere con «undefined function».
| `Pest.php` se carga siempre y primero.
*/

/** Luminancia relativa W3C (WCAG 2.x, fórmula de sRGB). */
function luminanciaRelativa(string $hex): float
{
    $hex = ltrim($hex, '#');
    [$r, $g, $b] = array_map(fn (string $h) => hexdec($h) / 255, str_split($hex, 2));

    $canal = fn (float $c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

    return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);
}

/** Ratio de contraste W3C entre dos colores hex, siempre ≥ 1. */
function ratioContraste(string $hex1, string $hex2): float
{
    $l1 = luminanciaRelativa($hex1);
    $l2 = luminanciaRelativa($hex2);
    [$claro, $oscuro] = $l1 > $l2 ? [$l1, $l2] : [$l2, $l1];

    return ($claro + 0.05) / ($oscuro + 0.05);
}

/** El CSS de tokens del paquete. */
function cssMuniUi(): string
{
    return file_get_contents(__DIR__.'/../resources/css/muni-ui.css');
}

/** El CSS del tema de Filament. */
function cssMuniUiFilament(): string
{
    return file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');
}

/** El primer bloque `{...}` que aparece después de `$ancla` en `$css`. */
function bloqueTrasAncla(string $css, string $ancla): string
{
    $inicio = mb_strpos($css, $ancla);

    if ($inicio === false) {
        return '';
    }

    $apertura = mb_strpos($css, '{', $inicio);
    $cierre = mb_strpos($css, '}', $apertura);

    return mb_substr($css, $apertura, $cierre === false ? null : $cierre - $apertura);
}

/** El valor hex de `--muni-{$nombre}` dentro de un bloque ya recortado. */
function tokenHex(string $bloque, string $nombre): ?string
{
    if (preg_match('/--muni-'.preg_quote($nombre, '/').':\s*(#[0-9a-fA-F]{6})/', $bloque, $m)) {
        return strtolower($m[1]);
    }

    return null;
}

/*
| Resolución de colores que NO son un hex literal
|--------------------------------------------------------------------------
|
| `tokenHex()` solo sabe leer `#rrggbb`, y con eso alcanzaba mientras la única
| hoja medida fuera `muni-ui.css`. El tema del panel no es así: en su rama
| oscura los fondos de estado se escriben `color-mix(in srgb, var(--mg-lima)
| 18%, #0f2025)`. Medir solo lo que es hex deja esa mitad de la paleta —la que
| ve un funcionario dentro de un panel Filament— sin una sola comprobación.
|
| La mezcla se calcula sobre sRGB con gamma (que es lo que significa «in srgb»
| en CSS Color 4, frente a «srgb-linear»), y el resultado coincide al segundo
| decimal con los valores leídos píxel a píxel en Chromium 151, Firefox 153 y
| WebKit 26.5 que están en docs/VERIFICACION-NAVEGADOR.md §8.10.
*/

/** El valor CRUDO de `--muni-{$nombre}` en un bloque ya recortado, sin resolver. */
function tokenValor(string $bloque, string $nombre): ?string
{
    if (preg_match('/--muni-'.preg_quote($nombre, '/').'\s*:\s*([^;}]+)/', $bloque, $m)) {
        return trim($m[1]);
    }

    return null;
}

/**
 * Resuelve un valor de color del paquete a `#rrggbb`: sigue las cadenas de
 * `var(--…)` buscando la declaración dentro de `$hoja` y calcula
 * `color-mix(in srgb, A P%, B)`.
 */
function colorResuelto(?string $valor, string $hoja, int $saltos = 0): ?string
{
    $valor = trim((string) $valor);

    if ($valor === '' || $saltos > 8) {
        return null;
    }

    if (preg_match('/^#([0-9a-fA-F]{6})$/', $valor, $m)) {
        return '#'.strtolower($m[1]);
    }

    if (preg_match('/^var\(\s*(--[A-Za-z0-9_-]+)\s*(?:,[^)]*)?\)$/', $valor, $m)) {
        if (preg_match('/'.preg_quote($m[1], '/').'\s*:\s*([^;}]+)/', $hoja, $d)) {
            return colorResuelto($d[1], $hoja, $saltos + 1);
        }

        return null;
    }

    if (preg_match('/^color-mix\(\s*in\s+srgb\s*,\s*(.+?)\s+([0-9.]+)%\s*,\s*(.+?)\s*\)$/i', $valor, $m)) {
        $a = colorResuelto($m[1], $hoja, $saltos + 1);
        $b = colorResuelto($m[3], $hoja, $saltos + 1);

        if ($a === null || $b === null) {
            return null;
        }

        $peso = ((float) $m[2]) / 100;
        $mezcla = '#';

        for ($i = 1; $i < 7; $i += 2) {
            $ca = hexdec(substr($a, $i, 2));
            $cb = hexdec(substr($b, $i, 2));
            $mezcla .= str_pad(dechex((int) round($ca * $peso + $cb * (1 - $peso))), 2, '0', STR_PAD_LEFT);
        }

        return $mezcla;
    }

    return null;
}

/** Como `tokenHex()`, pero resolviendo `var()` y `color-mix()` contra la hoja entera. */
function tokenColor(string $bloque, string $hoja, string $nombre): ?string
{
    return colorResuelto(tokenValor($bloque, $nombre), $hoja);
}
