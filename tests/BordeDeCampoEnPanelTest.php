<?php

/*
|--------------------------------------------------------------------------
| EL BORDE DEL CAMPO DENTRO DE UN PANEL FILAMENT
|--------------------------------------------------------------------------
|
| Hallado en seguridad-graneros (commit f723b119): el tema del panel pintaba
| `.fi-input-wrp` con `--mg-borde` (#e2ddce), el beige de los separadores.
| Medido: 1,36:1 sobre el blanco del campo y 1,25:1 sobre el papel de la página,
| contra el 3:1 que exige WCAG 1.4.11 para el límite de un control. El token
| correcto, `--muni-field-border`, ya estaba declarado en este mismo tema (claro y
| `.dark`) y nadie lo usaba en el wrapper de Filament.
|
| Y la regla iba FUERA de toda capa CSS. Filament 5 compila con Tailwind 4, que
| usa capas nativas (`theme, base, components, utilities`): una regla sin capa le
| gana a cualquier capa, así que un sistema no podía corregir el borde con una
| utilidad (`border-danger-600`) y tuvo que ponerlo en `style`. El borde va ahora
| en `@layer components`: le gana al preflight (`base`, que lo pone en 0) y pierde
| ante una utilidad, que es lo que se espera de un estilo de componente.
|
| El foco NO se mueve de capa: sigue sin capa y con `!important`, porque el
| indicador de foco (2.4.7 / 2.4.11) no debe poder apagarlo una utilidad suelta.
*/

/** La hoja del tema sin comentarios, para que la prosa no se cuele en un selector. */
function bdcHoja(): string
{
    return (string) preg_replace('#/\*.*?\*/#s', '', cssMuniUiFilament());
}

/** El contenido de cada bloque `@layer components { … }`, contando llaves. */
function bdcCapaComponents(string $css): string
{
    $contenido = '';
    $desde = 0;

    while (preg_match('/@layer\s+components\s*\{/', $css, $m, PREG_OFFSET_CAPTURE, $desde)) {
        $inicio = $m[0][1] + strlen($m[0][0]);
        $nivel = 1;

        for ($i = $inicio; $i < strlen($css) && $nivel > 0; $i++) {
            $nivel += $css[$i] === '{' ? 1 : ($css[$i] === '}' ? -1 : 0);
        }

        $contenido .= substr($css, $inicio, $i - $inicio - 1)."\n";
        $desde = $i;
    }

    return $contenido;
}

/** La hoja con los bloques `@layer components` quitados: lo que queda sin capa. */
function bdcSinCapa(string $css): string
{
    $capa = bdcCapaComponents($css);

    return $capa === '' ? $css : str_replace(trim($capa), '', $css);
}

/** [selector normalizado, cuerpo] de cada regla simple de `$css`. */
function bdcReglas(string $css): array
{
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    return array_map(
        fn (array $r) => [trim((string) preg_replace('/\s+/', ' ', $r[1])), trim($r[2])],
        $reglas,
    );
}

/** Mezcla `$frente` al `$alfa` sobre `$fondo` (lo que hace `bg-white/5`). */
function bdcCompuesto(string $frente, float $alfa, string $fondo): string
{
    $hex = '#';

    for ($i = 1; $i < 7; $i += 2) {
        $f = hexdec(substr($frente, $i, 2));
        $b = hexdec(substr($fondo, $i, 2));
        $hex .= str_pad(dechex((int) round($f * $alfa + $b * (1 - $alfa))), 2, '0', STR_PAD_LEFT);
    }

    return $hex;
}

it('el campo de Filament se dibuja con el token de borde de campo, no con el beige del separador', function () {
    $conBorde = [];

    foreach (bdcReglas(bdcHoja()) as [$selector, $cuerpo]) {
        if (! str_contains($selector, '.fi-input-wrp') || str_contains($selector, ':focus-within')) {
            continue;
        }

        if (! preg_match('/(^|[;\s])border(-color)?\s*:/', $cuerpo)) {
            continue;
        }

        $conBorde[] = $selector;

        expect(str_contains($cuerpo, '--mg-borde'))->toBeFalse(
            "«{$selector}» pinta el límite del campo con --mg-borde: 1,36:1 sobre el campo, bajo el 3:1 de 1.4.11."
        );
        // `--muni-field-border` o `--muni-field-border-error` (el inválido).
        expect(str_contains($cuerpo, 'var(--muni-field-border'))->toBeTrue(
            "«{$selector}» no usa los tokens de borde de campo para el límite del campo."
        );
    }

    // El wrapper genérico existe y lleva el token normal. El del buscador de la
    // barra superior no declara borde propio: hereda este (antes repetía el beige).
    expect($conBorde)->toContain('.fi-input-wrp');
});

it('el campo inválido pinta su borde con el token de error', function () {
    $invalido = array_filter(
        bdcReglas(bdcHoja()),
        fn (array $r) => str_contains($r[0], '.fi-input-wrp.fi-invalid') && ! str_contains($r[0], ':focus-within'),
    );

    expect($invalido)->not->toBe([], 'No hay regla para el borde de `.fi-input-wrp.fi-invalid`.');
    expect(implode(' ', array_column($invalido, 1)))->toContain('var(--muni-field-border-error)');
});

it('el borde del campo vive en @layer components para que una utilidad pueda ganarle', function () {
    $hoja = bdcHoja();
    $capa = bdcCapaComponents($hoja);

    // Sin declarar el orden, si esta hoja llegara antes que la de Filament la capa
    // `components` quedaría POR DEBAJO de `base` y el preflight (border:0) borraría
    // el borde. Se declara el mismo orden que emite Tailwind 4.
    $orden = strpos($hoja, '@layer properties, theme, base, components, utilities;');
    expect($orden)->not->toBeFalse('Falta declarar el orden de capas de Tailwind 4 antes de usar @layer components.');
    expect($orden)->toBeLessThan((int) strpos($hoja, '@layer components'));

    $enCapa = array_filter(
        bdcReglas($capa),
        fn (array $r) => str_contains($r[0], '.fi-input-wrp') && str_contains($r[1], 'var(--muni-field-border'),
    );
    expect($enCapa)->not->toBe([], 'El borde del campo no está dentro de @layer components.');

    // `!important` dentro de una capa le gana a TODA utilidad, también a las
    // `!important`: anularía el motivo de moverlo.
    foreach ($enCapa as [$selector, $cuerpo]) {
        expect(preg_match('/border[^;]*!important/', $cuerpo))->toBe(0, "«{$selector}» lleva !important en el borde.");
    }

    // Y fuera de la capa no queda ningún borde de campo que le gane a la utilidad.
    foreach (bdcReglas(bdcSinCapa($hoja)) as [$selector, $cuerpo]) {
        if (str_contains($selector, '.fi-input-wrp') && ! str_contains($selector, ':focus-within')) {
            expect(preg_match('/(^|[;\s])border(-color)?\s*:/', $cuerpo))->toBe(0,
                "«{$selector}» declara el borde del campo sin capa: ninguna utilidad podrá corregirlo."
            );
        }
    }
});

it('el foco del campo sigue sin capa y con !important', function () {
    $sinCapa = bdcReglas(bdcSinCapa(bdcHoja()));

    $foco = array_filter($sinCapa, fn (array $r) => $r[0] === '.fi-input-wrp:focus-within');
    $focoOscuro = array_filter($sinCapa, fn (array $r) => $r[0] === '.dark .fi-input-wrp:focus-within');

    expect(implode(' ', array_column($foco, 1)))->toContain('border-color:var(--mg-petroleo) !important');
    expect(implode(' ', array_column($focoOscuro, 1)))->toContain('border-color:var(--muni-accent-strong) !important');
});

/*
 * La medida, con los valores del TEMA DEL PANEL (no los de muni-ui.css, que no se
 * carga dentro de Filament). Contra el interior del campo y contra lo que lo
 * rodea: 1.4.11 pide 3:1 contra los colores adyacentes, y son los dos.
 *
 * Interior: Filament pinta el campo `bg-white` en claro y `dark:bg-white/5` en
 * oscuro, que es blanco al 5% sobre lo que haya debajo (sección o página).
 * La barra superior es `color-mix(#fff 82%, papel)` en claro y surface-2 en oscuro.
 */
it('el borde normal y el de error llegan a 3:1 en claro y en oscuro sobre todo fondo del panel', function () {
    $hoja = cssMuniUiFilament();
    $claro = bloqueTrasAncla($hoja, ':root{');
    $oscuro = bloqueTrasAncla($hoja, 'En oscuro mandan los tonos institucionales');

    $fondosClaro = [
        'campo (bg-white)' => '#ffffff',
        'barra superior' => colorResuelto('color-mix(in srgb,#ffffff 82%, var(--mg-papel))', $hoja),
    ];
    foreach (['bg', 'surface', 'surface-2', 'surface-3'] as $n) {
        $fondosClaro["--muni-{$n}"] = tokenColor($claro, $hoja, $n);
    }

    $fondosOscuro = [];
    foreach (['bg', 'surface', 'surface-2', 'surface-3'] as $n) {
        $fondo = tokenHex($oscuro, $n);
        $fondosOscuro["--muni-{$n}"] = $fondo;
        $fondosOscuro["campo (white/5 sobre --muni-{$n})"] = bdcCompuesto('#ffffff', 0.05, $fondo);
    }

    $bordes = [
        'claro · normal' => [tokenColor($claro, $hoja, 'field-border'), $fondosClaro],
        'claro · error' => [colorResuelto(tokenValor($claro, 'danger-fg'), $hoja), $fondosClaro],
        'oscuro · normal' => [tokenHex($oscuro, 'field-border'), $fondosOscuro],
        'oscuro · error' => [colorResuelto(tokenValor($oscuro, 'danger-fg'), $hoja), $fondosOscuro],
    ];

    // El error de cada rama tiene que ser el danger-fg de ESA rama.
    expect(tokenValor($oscuro, 'field-border-error'))->toBe('var(--muni-danger-fg)');

    $fallos = [];
    foreach ($bordes as $caso => [$borde, $fondos]) {
        expect($borde)->not->toBeNull("No se pudo resolver el borde {$caso}.");

        foreach ($fondos as $nombre => $fondo) {
            $ratio = ratioContraste($borde, $fondo);
            if ($ratio < 3.0) {
                $fallos[] = sprintf('%s %s sobre %s %s = %.2f:1', $caso, $borde, $nombre, $fondo, $ratio);
            }
        }
    }

    expect($fallos)->toBe([], implode(' · ', $fallos));
});
