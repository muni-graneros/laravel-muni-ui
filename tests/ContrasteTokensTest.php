<?php

/**
 * WCAG AA (1.4.3): texto normal necesita 4,5:1 contra su superficie. Este archivo
 * mide la fórmula de contraste relativo W3C sobre los hex EXACTOS de
 * `resources/css/muni-ui.css`, no una estimación visual: así el defecto no puede
 * volver a colarse en una captura donde un token «se ve» legible.
 *
 * Alcance: solo los dos tokens que hoy están bajo el mínimo (`--muni-warn-fg` en
 * claro, `--muni-hint` en oscuro). El resto de la paleta se deja fuera a
 * propósito — tocar un token no auditado es un cambio de valor sin evidencia.
 */
function cssMuniUi(): string
{
    return file_get_contents(__DIR__.'/../resources/css/muni-ui.css');
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

it('--muni-warn-fg en modo claro pasa WCAG AA (4,5:1) sobre su superficie y su propio fondo', function () {
    $claro = bloqueTrasAncla(cssMuniUi(), 'Valores LIGHT (default)');

    $warnFg = tokenHex($claro, 'warn-fg');
    $warnBg = tokenHex($claro, 'warn-bg');
    $surface = tokenHex($claro, 'surface');

    expect(ratioContraste($warnFg, $surface))->toBeGreaterThanOrEqual(4.5,
        "--muni-warn-fg ({$warnFg}) sobre --muni-surface ({$surface}) no llega a 4,5:1"
    );
    expect(ratioContraste($warnFg, $warnBg))->toBeGreaterThanOrEqual(4.5,
        "--muni-warn-fg ({$warnFg}) sobre --muni-warn-bg ({$warnBg}) no llega a 4,5:1"
    );
});

it('--muni-hint en modo oscuro pasa WCAG AA (4,5:1) sobre las tres superficies del panel', function () {
    $oscuro = bloqueTrasAncla(cssMuniUi(), 'Regla 2: activadores EXPLÍCITOS de dark');

    $hint = tokenHex($oscuro, 'hint');

    foreach (['surface', 'surface-2', 'surface-3'] as $nombreSuperficie) {
        $superficie = tokenHex($oscuro, $nombreSuperficie);

        expect(ratioContraste($hint, $superficie))->toBeGreaterThanOrEqual(4.5,
            "--muni-hint ({$hint}) sobre --muni-{$nombreSuperficie} ({$superficie}) no llega a 4,5:1"
        );
    }
});
