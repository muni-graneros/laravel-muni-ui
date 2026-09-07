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

/*
 * Añadida al medir la vitrina en un navegador real: la prueba de arriba solo
 * cubría `--muni-hint` en OSCURO, y por eso el fallo en CLARO sobrevivió a todo.
 * axe-core lo encontró sobre los componentes de verdad, no sobre las demos:
 * #6b7280 daba 4,16:1 sobre --muni-surface-3 y 4,47:1 sobre --muni-bg. El texto
 * de ayuda de un campo cae justo ahí, dentro de una tarjeta o de una sección.
 *
 * Es el mismo token, el mismo criterio y el tema contrario: si una prueba de
 * contraste cubre un solo modo, el defecto simplemente se muda al otro.
 */
it('--muni-hint en modo claro pasa WCAG AA (4,5:1) sobre las tres superficies', function () {
    $claro = bloqueTrasAncla(cssMuniUi(), 'Valores LIGHT (default)');

    $hint = tokenHex($claro, 'hint');

    foreach (['surface', 'bg', 'surface-3'] as $nombreSuperficie) {
        $superficie = tokenHex($claro, $nombreSuperficie);

        expect(ratioContraste($hint, $superficie))->toBeGreaterThanOrEqual(4.5,
            "--muni-hint ({$hint}) sobre --muni-{$nombreSuperficie} ({$superficie}) no llega a 4,5:1. ".
            'El texto de ayuda de un campo se pinta justo sobre esas superficies.'
        );
    }
});

it('el bloque que congela el tema claro declara el mismo hint que :root', function () {
    // [data-muni-theme="light"] repite los valores del claro. Si alguien corrige
    // uno y olvida el otro, un sistema con tema fijo se queda con el defecto.
    $css = cssMuniUi();

    preg_match_all('/--muni-hint:\s*(#[0-9a-fA-F]{6})/', $css, $m);

    expect(count($m[1]))->toBe(4, 'Cambió el número de declaraciones de --muni-hint; revisa el archivo.');
    expect($m[1][0])->toBe($m[1][3], 'El :root y el bloque [data-muni-theme="light"] declaran hints distintos.');
    expect($m[1][1])->toBe($m[1][2], 'El media query y los activadores explícitos declaran hints distintos.');
});
