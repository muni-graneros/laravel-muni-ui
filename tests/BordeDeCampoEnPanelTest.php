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

/*
|--------------------------------------------------------------------------
| LO QUE QUEDÓ ALREDEDOR DEL BORDE: CHEVRON, FOCO BAJO LA BARRA, DESHABILITADO
|--------------------------------------------------------------------------
|
| Revisión a11y por píxel del borde a 3:1 (banco estático con el CSS real de
| Filament 5 y este tema, claro/oscuro, 1440 y 390):
|
| 1. El chevron del `<select>` es un SVG en data-URI de Filament con el trazo
|    #6b7280 FIJO: en oscuro da 2,66:1 sobre el campo en surface-3 y 3,03:1
|    sobre surface (1.4.11 pide 3:1 para el gráfico que dice «esto se despliega»).
|    En claro da 4,83:1 y no puede empeorar.
| 2. Con Shift+Tab el campo enfocado quedaba debajo de la barra superior fija
|    (`.fi-topbar-ctn` es sticky): sin `scroll-padding-top` el navegador lo
|    desplaza hasta el borde del viewport, que es justo donde está la barra
|    (2.4.11 / 2.4.12, foco no oculto). Filament pone `scroll-margin-top` solo en
|    `[data-field-wrapper]` (hasta 5.7.6; en 5.7.8 también en sus descendientes),
|    así que un control fuera de un campo de formulario de Filament —un wrapper
|    suelto, un enlace, una acción de tabla— seguía quedando tapado.
| 3. Con el borde a 3:1, el campo deshabilitado se veía igual de «activo» que
|    uno normal: mismo borde, cursor por defecto.
*/

/**
 * El alto de `.fi-topbar` según el topbar.css de Filament, en rem, o null si no
 * se reconoce: `min-h-16` (≤ 5.7.6, escala de Tailwind: 16 × 0,25rem) o
 * `min-h-(--topbar-height)` con `--topbar-height: Xrem` (≥ 5.7.8).
 */
function bdcAltoDeLaBarraDeFilament(string $topbarCss): ?string
{
    if (preg_match('/\.fi-topbar\s*\{[^}]*\bmin-h-(\d+)\b/', $topbarCss, $m) === 1) {
        return ((int) $m[1] / 4).'rem';
    }

    if (preg_match('/\.fi-topbar\s*\{[^}]*\bmin-h-\(--topbar-height\)/', $topbarCss) === 1
        && preg_match('/--topbar-height\s*:\s*([\d.]+rem)\s*;/', $topbarCss, $m) === 1) {
        return $m[1];
    }

    return null;
}

/** El bloque de tokens del papel (dentro de `@media print`). */
function bdcBloqueImpresion(string $hoja): string
{
    return bloqueTrasAncla($hoja, 'PALETA CLARA FORZADA');
}

/** El color del trazo de un chevron en data-URI (`stroke='%23rrggbb'`). */
function bdcTrazoDelChevron(?string $url): ?string
{
    if ($url !== null && preg_match("/^url\(\"data:image\/svg\+xml,.*stroke='%23([0-9a-fA-F]{6})'/", $url, $m)) {
        return '#'.strtolower($m[1]);
    }

    return null;
}

it('el chevron del select sale de un token que sigue el tema y no del gris fijo de Filament', function () {
    $hoja = bdcHoja();
    $capa = bdcCapaComponents($hoja);

    // Nativo y el botón del select con buscador (JS). Los dos selectores de
    // Filament puntúan 0,1,1 y 0,2,0 y viven en su propia `@layer components`:
    // estos los superan por especificidad, así que ganan aunque esta hoja llegue
    // antes que la de Filament.
    foreach (['.fi-input-wrp select.fi-select-input', '.fi-input-wrp .fi-select-input .fi-select-input-btn'] as $selector) {
        $reglas = array_filter(
            bdcReglas($capa),
            fn (array $r) => in_array($selector, array_map('trim', explode(',', $r[0])), true),
        );

        expect($reglas)->not->toBe([], "Falta «{$selector}» en @layer components.");
        expect(implode(' ', array_column($reglas, 1)))->toContain('background-image:var(--muni-select-chevron)');
        expect(implode(' ', array_column($reglas, 1)))->not->toContain('!important');
    }
});

it('el chevron del select llega a 3:1 en claro y en oscuro sobre todo fondo del campo, y en claro no empeora', function () {
    $hoja = cssMuniUiFilament();
    $claro = bloqueTrasAncla($hoja, ':root{');
    $oscuro = bloqueTrasAncla($hoja, 'En oscuro mandan los tonos institucionales');
    $papel = bdcBloqueImpresion($hoja);

    // El SVG de un data-URI no puede leer una variable CSS: el trazo se escribe
    // con el valor de `--muni-muted` de CADA rama, y este candado los ata.
    $ramas = [
        'claro' => [$claro, ['campo (bg-white)' => '#ffffff', 'deshabilitado (gray-50)' => '#fafafa']],
        'oscuro' => [$oscuro, []],
        'papel' => [$papel, ['papel' => '#ffffff']],
    ];
    foreach (['bg', 'surface', 'surface-2', 'surface-3'] as $n) {
        $ramas['claro'][1]["--muni-{$n}"] = tokenColor($claro, $hoja, $n);
        $fondo = tokenHex($oscuro, $n);
        $ramas['oscuro'][1]["--muni-{$n} (deshabilitado: transparente)"] = $fondo;
        $ramas['oscuro'][1]["campo (white/5 sobre --muni-{$n})"] = bdcCompuesto('#ffffff', 0.05, $fondo);
    }

    $fallos = [];
    foreach ($ramas as $rama => [$bloque, $fondos]) {
        $trazo = bdcTrazoDelChevron(tokenValor($bloque, 'select-chevron'));
        expect($trazo)->not->toBeNull("La rama {$rama} no declara --muni-select-chevron como SVG en data-URI con trazo hex.");
        expect($trazo)->toBe(colorResuelto(tokenValor($bloque, 'muted'), $hoja), "El chevron {$rama} no usa el --muni-muted de su rama.");

        foreach ($fondos as $nombre => $fondo) {
            $ratio = ratioContraste($trazo, $fondo);
            if ($ratio < 3.0) {
                $fallos[] = sprintf('%s %s sobre %s %s = %.2f:1', $rama, $trazo, $nombre, $fondo, $ratio);
            }
        }
    }

    expect($fallos)->toBe([], implode(' · ', $fallos));

    // Claro hoy (gris de Filament #6b7280 sobre el campo blanco): 4,83:1.
    $trazoClaro = bdcTrazoDelChevron(tokenValor($claro, 'select-chevron'));
    expect(ratioContraste($trazoClaro, '#ffffff'))->toBeGreaterThanOrEqual(ratioContraste('#6b7280', '#ffffff'));
});

it('el foco no queda bajo la barra superior fija: scroll-padding-top con el alto real de la barra', function () {
    $hoja = bdcHoja();
    $claro = bloqueTrasAncla(cssMuniUiFilament(), ':root{');

    // El token del tema tiene que valer lo mismo que el alto real de la barra de
    // Filament; si Filament lo cambia, esto se pone rojo. Hasta 5.7.6 lo fija con
    // `min-h-16` en topbar.css (y la barra lateral repite `top-[4rem]`); desde
    // 5.7.8 es `min-h-(--topbar-height)` con `.fi-body { --topbar-height: 4rem }`.
    // Esa variable vive en `.fi-body`, no en la raíz, así que `:root` no puede
    // leerla y el token sigue siendo un valor fijo: se compara con las dos formas.
    $topbar = __DIR__.'/../vendor/filament/filament/resources/css/components/topbar.css';
    if (! is_file($topbar)) {
        $this->markTestSkipped('Sin vendor/filament: no se puede comparar con el alto real de la barra.');
    }
    expect(bdcAltoDeLaBarraDeFilament((string) file_get_contents($topbar)))->toBe(tokenValor($claro, 'panel-topbar-h'));

    // El que hace scroll en un panel Filament 5 es el documento (`html.fi` lleva
    // `min-h-dvh` y ningún contenedor intermedio tiene overflow vertical). Solo
    // cuando hay barra: en el login no hay nada que la tape.
    $reglas = array_filter(
        bdcReglas($hoja),
        fn (array $r) => preg_match('/^(html|:root):has\(/', $r[0]) === 1
            && str_contains($r[0], '.fi-topbar')
            && str_contains($r[1], 'scroll-padding-top'),
    );
    expect($reglas)->not->toBe([], 'Falta scroll-padding-top en el documento cuando hay .fi-topbar.');
    expect(implode(' ', array_column($reglas, 1)))->toMatch('/scroll-padding-top\s*:\s*calc\(\s*var\(--muni-panel-topbar-h\)\s*\+/');
});

it('el campo deshabilitado se distingue del activo: borde atenuado propio y cursor not-allowed', function () {
    $hoja = cssMuniUiFilament();
    $capa = bdcCapaComponents(bdcHoja());

    $deshabilitado = array_filter(bdcReglas($capa), fn (array $r) => str_contains($r[0], '.fi-input-wrp.fi-disabled'));
    $cuerpos = implode(' ', array_column($deshabilitado, 1));

    expect($cuerpos)->toContain('border-color:var(--muni-field-border-disabled)');
    expect($cuerpos)->toContain('cursor:not-allowed');

    // El cursor va también en el control: input, select y textarea fijan el suyo
    // y no lo heredan del envoltorio.
    $conCursorEnControl = array_filter(
        $deshabilitado,
        fn (array $r) => str_contains($r[1], 'cursor:not-allowed') && preg_match('/\.fi-input-wrp\.fi-disabled\s+:?\S*(input|select|textarea|disabled)/', $r[0]),
    );
    expect($conCursorEnControl)->not->toBe([], 'El cursor not-allowed no alcanza al control deshabilitado.');

    // El inválido deshabilitado conserva el borde de error.
    $borde = array_filter($deshabilitado, fn (array $r) => str_contains($r[1], 'field-border-disabled'));
    expect(implode(' ', array_column($borde, 0)))->toContain(':not(.fi-invalid)');

    // Atenuado: exento de 1.4.11 (control inactivo), pero tiene que verse distinto.
    foreach (['claro' => ':root{', 'oscuro' => 'En oscuro mandan los tonos institucionales'] as $rama => $ancla) {
        $bloque = bloqueTrasAncla($hoja, $ancla);
        $normal = tokenColor($bloque, $hoja, 'field-border');
        $atenuado = tokenColor($bloque, $hoja, 'field-border-disabled');

        expect($atenuado)->not->toBeNull("La rama {$rama} no declara --muni-field-border-disabled.");
        expect(ratioContraste($normal, $atenuado))->toBeGreaterThanOrEqual(1.5,
            "En {$rama} el borde deshabilitado {$atenuado} casi no se distingue del activo {$normal}."
        );
    }
});
