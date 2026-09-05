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
