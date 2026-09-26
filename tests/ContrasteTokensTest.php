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

/*
 * WCAG 2.2 AA 1.4.11 pide 3:1 para la información visual que identifica un
 * componente de interfaz. En un campo de formulario ese componente es el borde:
 * es lo único que dice dónde empieza y dónde termina.
 *
 * Los bordes generales del paquete no dan, y no tienen por qué: --muni-border y
 * --muni-border-2 son separadores, donde 1.4.11 no aplica. Medidos daban 1,28:1 y
 * 1,61:1 en claro; 1,43:1 y 1,90:1 en oscuro. Por eso los controles usan un token
 * propio, --muni-field-border, y por eso esta prueba existe: si alguien lo aclara
 * para que «se vea más suave», el candado cae acá.
 */
it('--muni-field-border pasa 1.4.11 (3:1) sobre las superficies de los dos temas', function () {
    $bloques = [
        'Valores LIGHT (default)' => ['surface', 'bg', 'surface-3'],
        'Regla 2: activadores EXPLÍCITOS de dark' => ['surface', 'surface-2', 'surface-3', 'bg'],
    ];

    foreach ($bloques as $ancla => $superficies) {
        $bloque = bloqueTrasAncla(cssMuniUi(), $ancla);
        $borde = tokenHex($bloque, 'field-border');

        foreach ($superficies as $nombre) {
            $superficie = tokenHex($bloque, $nombre);

            expect(ratioContraste($borde, $superficie))->toBeGreaterThanOrEqual(3.0,
                "--muni-field-border ({$borde}) sobre --muni-{$nombre} ({$superficie}) no llega a 3:1. ".
                'El borde es lo único que delimita el campo.'
            );
        }
    }
});

it('los controles de formulario usan el token de borde de campo, no el de separador', function () {
    $controles = ['input', 'select', 'textarea', 'checkbox', 'switch'];

    foreach ($controles as $nombre) {
        $fuente = file_get_contents(__DIR__."/../resources/views/components/{$nombre}.blade.php");

        // Ojo: `toContain($x, 'mensaje')` NO acepta mensaje — el segundo argumento
        // es otra aguja que se busca en el sujeto. Por eso va con str_contains.
        expect(str_contains($fuente, '--muni-field-border'))->toBeTrue(
            "«{$nombre}» dibuja su límite con un token de separador. Medido, eso da entre 1,1:1 y ".
            '1,9:1, muy por debajo del 3:1 que exige 1.4.11 para identificar un control.'
        );
    }
});

it('el tema del panel también declara el token, porque muni-ui.css no se carga ahí', function () {
    // Ver DESIGN §7: MuniPanel solo inyecta filament.css. Un token declarado
    // únicamente en muni-ui.css deja el borde del campo sin color dentro del panel,
    // y sin un solo error en consola.
    $tema = file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');

    expect(substr_count($tema, '--muni-field-border:'))->toBe(2,
        'El puente del tema Filament tiene que declarar --muni-field-border en claro Y en .dark.'
    );
});

/*
 * `--muni-muted` en el OSCURO DEL PANEL: 4,33:1 sobre el fondo del aviso `warn`.
 *
 * El token se había calibrado contra las superficies del panel (6,5:1 sobre
 * `--muni-surface`) y nunca contra los cuatro fondos de estado, que son más
 * claros porque mezclan un 18% del tono institucional. Medido con axe en
 * scaffold-laravel-filament-pwa sobre `<x-muni::alert tone="warn">` en oscuro:
 * #94a3b8 sobre #383e2a = 4,33:1; ok 4,41; info 4,37. Tres sistemas lo
 * parcheaban en su `panel.css` con `html.dark { --muni-muted: #a3b1c4 }`.
 *
 * Los fondos de estado se resuelven desde el `color-mix()` REAL de la hoja, no
 * desde un hex copiado a mano: si alguien sube el 18% a 25%, el fondo aclara,
 * el ratio baja y el candado cae acá.
 */
it('--muni-muted en el oscuro del panel llega a 4,5:1 sobre los cuatro fondos de estado y las superficies', function () {
    $hoja = cssMuniUiFilament();
    $oscuro = bloqueTrasAncla($hoja, 'En oscuro mandan los tonos institucionales');

    $muted = tokenColor($oscuro, $hoja, 'muted');
    $texto = tokenColor($oscuro, $hoja, 'text');

    expect($muted)->not->toBeNull('El bloque .dark del tema no declara --muni-muted.');
    expect($texto)->not->toBeNull('El bloque .dark del tema no declara --muni-text.');

    $fallos = [];

    foreach (['ok-bg', 'warn-bg', 'info-bg', 'danger-bg', 'surface', 'surface-2', 'surface-3', 'bg'] as $nombre) {
        $fondo = tokenColor($oscuro, $hoja, $nombre);

        expect($fondo)->not->toBeNull("No se pudo resolver --muni-{$nombre} en el bloque .dark del tema.");

        $ratio = ratioContraste($muted, $fondo);

        if ($ratio < 4.5) {
            $fallos[] = sprintf('--muni-muted (%s) sobre --muni-%s (%s) = %.2f:1', $muted, $nombre, $fondo, $ratio);
        }
    }

    expect($fallos)->toBe([], "Texto secundario bajo 4,5:1 en el oscuro del panel:\n  ".implode("\n  ", $fallos));

    // Sigue siendo texto SECUNDARIO: si lo aclaran hasta igualar al principal,
    // la jerarquía visual desaparece aunque el contraste pase.
    expect(luminanciaRelativa($muted))->toBeLessThan(luminanciaRelativa($texto),
        "--muni-muted ({$muted}) quedó tan claro o más que --muni-text ({$texto}): ya no es secundario."
    );
});
