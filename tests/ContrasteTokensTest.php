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
 *
 * Los helpers de medición (`cssMuniUi`, `bloqueTrasAncla`, `tokenHex`,
 * `luminanciaRelativa`, `ratioContraste`) viven ahora en `tests/Pest.php`: los
 * comparte `FocoVisibleTest.php` y allí no dependen del orden de carga.
 */
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
