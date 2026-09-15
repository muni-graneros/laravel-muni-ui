<?php

/*
|--------------------------------------------------------------------------
| EL BORDE DE UNA SUPERFICIE FLOTANTE
|--------------------------------------------------------------------------
|
| Medido en navegador al verificar `popover`: `--muni-border` contra el fondo de
| la página daba 1,19:1 en claro y 1,52:1 en oscuro. Lo que separaba el panel del
| fondo era la sombra (`--muni-shadow-lg`), no el borde, y la sombra no existe con
| las sombras desactivadas, en colores forzados ni en papel.
|
| Decisión: NO se oscurece `--muni-border` para todo el paquete, que endurecería
| cada separador y cada tarjeta. Se crea `--muni-overlay-border`, que SOLO usan
| las superficies que flotan sobre la página: popover, dropdown, modal, drawer,
| paleta de comandos, avisos flotantes, el aviso de sesión y la lateral cuando se
| superpone (solo bajo su punto de quiebre). Mismo precedente que
| `--muni-field-border`: token propio, declarado en las dos hojas y medido.
|
| Se mide contra TODA superficie sobre la que el panel puede quedar flotando
| (`bg`, `surface`, `surface-2`, `surface-3` y `panel` cuando existe), en cada
| rama de tema de cada hoja, y en papel. Las dos hojas tienen paletas distintas:
| medir una no dice nada de la otra.
|
| Los diálogos con velo (modal, drawer, paleta, lateral superpuesta y sesión) NO
| tocan el fondo de la página: por fuera tocan el velo. Eso se mide aparte,
| componiendo el velo que declara cada componente sobre cada superficie.
*/

/** Superficies sobre las que puede flotar un panel. */
function bdsSuperficies(): array
{
    return ['bg', 'surface', 'surface-2', 'surface-3', 'panel'];
}

/** Mide el token en un bloque y devuelve los fallos, o [] si todo llega a 3:1. */
function bdsFallos(string $etiqueta, string $bloque, string $hoja): array
{
    $borde = tokenColor($bloque, $hoja, 'overlay-border');

    if ($borde === null) {
        return ["{$etiqueta}: no declara --muni-overlay-border (o no se puede resolver)"];
    }

    $fallos = [];
    $medidas = 0;

    foreach (bdsSuperficies() as $nombre) {
        $fondo = tokenColor($bloque, $hoja, $nombre);

        if ($fondo === null) {
            continue;
        }

        $medidas++;
        $ratio = ratioContraste($borde, $fondo);

        if ($ratio < 3.0) {
            $fallos[] = sprintf('%s: --muni-overlay-border (%s) sobre --muni-%s (%s) = %.2f:1', $etiqueta, $borde, $nombre, $fondo, $ratio);
        }
    }

    if ($medidas < 3) {
        $fallos[] = "{$etiqueta}: solo se resolvieron {$medidas} superficies; el recorte del bloque está roto";
    }

    return $fallos;
}

/** El cuerpo del bloque `@media print` de tokens: la primera regla con `--muni-text` dentro de él. */
function bdsBloqueDePapel(string $css): string
{
    $inicio = mb_strpos($css, '@media print');

    return $inicio === false ? '' : bloqueTrasAncla(mb_substr($css, $inicio), '{');
}

it('muni-ui.css declara --muni-overlay-border en las cuatro ramas de tema y en papel', function () {
    $css = (string) preg_replace('#/\*.*?\*/#s', '', cssMuniUi());

    preg_match_all('/--muni-overlay-border\s*:\s*([^;]+);/', $css, $m);

    expect(count($m[1]))->toBe(5,
        'Se esperaban 5 declaraciones: :root, preferencia del sistema, activadores de oscuro, '.
        'congelado claro y papel. Una rama sin el token hereda el valor de la otra paleta.'
    );

    // DESIGN §3: las ramas gemelas tienen que ser idénticas byte a byte, o en la
    // raíz ganaría el sistema operativo sobre el activador explícito.
    expect(trim($m[1][0]))->toBe(trim($m[1][3]), ':root y [data-muni-theme="light"] declaran bordes flotantes distintos.');
    expect(trim($m[1][1]))->toBe(trim($m[1][2]), 'La preferencia del sistema y los activadores de oscuro declaran bordes flotantes distintos.');
});

it('en muni-ui.css el borde flotante llega a 3:1 sobre toda superficie, en cada rama', function () {
    $hoja = cssMuniUi();

    $ramas = [
        'claro por omisión' => 'Valores LIGHT (default)',
        'oscuro del sistema operativo' => 'Regla 1: preferencia del OS',
        'oscuro explícito' => 'Regla 2: activadores EXPLÍCITOS de dark',
        'claro congelado' => 'Regla 3: override EXPLÍCITO a light',
    ];

    $fallos = [];

    foreach ($ramas as $etiqueta => $ancla) {
        $bloque = bloqueTrasAncla($hoja, $ancla);

        expect($bloque)->not->toBe('', "No se encontró la rama «{$etiqueta}» en muni-ui.css.");

        $fallos = [...$fallos, ...bdsFallos("muni-ui.css {$etiqueta}", $bloque, $hoja)];
    }

    expect($fallos)->toBe([], "Borde flotante bajo 3:1 (WCAG 1.4.11):\n  ".implode("\n  ", $fallos));
});

it('en el tema del panel el borde flotante llega a 3:1 en claro y en oscuro', function () {
    $hoja = cssMuniUiFilament();

    $ramas = [
        'claro (:root)' => bloqueTrasAncla($hoja, ':root{'),
        'oscuro (.dark)' => bloqueTrasAncla($hoja, 'En oscuro mandan los tonos institucionales'),
    ];

    $fallos = [];

    foreach ($ramas as $etiqueta => $bloque) {
        expect($bloque)->not->toBe('', "No se encontró la rama «{$etiqueta}» en muni-ui-filament.css.");

        $fallos = [...$fallos, ...bdsFallos("muni-ui-filament.css {$etiqueta}", $bloque, $hoja)];
    }

    expect($fallos)->toBe([], "Borde flotante bajo 3:1 dentro del panel:\n  ".implode("\n  ", $fallos));

    $sinComentarios = (string) preg_replace('#/\*.*?\*/#s', '', $hoja);

    expect(substr_count($sinComentarios, '--muni-overlay-border:'))->toBe(3,
        'El tema del panel tiene que declarar --muni-overlay-border en :root, en .dark y en papel: '.
        'muni-ui.css no se carga dentro de un panel Filament (DESIGN §7).'
    );
});

it('en papel, donde no hay sombra, el borde flotante se declara y llega a 3:1 en las dos hojas', function () {
    foreach (['muni-ui.css' => cssMuniUi(), 'muni-ui-filament.css' => cssMuniUiFilament()] as $nombre => $hoja) {
        $papel = bdsBloqueDePapel($hoja);

        expect(str_contains($papel, '--muni-text'))->toBeTrue("No se encontró el bloque de tokens de papel en {$nombre}.");

        // Sin declararlo en papel, un panel dentro de `.dark` imprimiría con el
        // valor de la paleta oscura, pensado para un fondo casi negro.
        $fallos = bdsFallos("{$nombre} papel", $papel, $hoja);

        expect($fallos)->toBe([], "Borde flotante en papel:\n  ".implode("\n  ", $fallos));
    }
});

it('las superficies flotantes dibujan su límite con el token, con respaldo para CSS sin publicar', function () {
    /*
     * No basta con que la cadena aparezca en algún lugar del archivo: tiene que
     * ser el valor de una declaración de BORDE. Si alguien la mueve a un color
     * de texto o a una variable suelta, el panel vuelve a --muni-border y esta
     * prueba lo tiene que ver.
     */
    $declaracion = '/\bborder(?:-(?:left|right|top|bottom|\{\{[^}]*\}\}))?(?:-color)?\s*:[^;"{}]*var\(--muni-overlay-border, var\(--muni-border\)\)/';

    $flotantes = [
        'popover.blade.php',
        'dropdown.blade.php',
        'modal.blade.php',
        'drawer.blade.php',
        'command-palette.blade.php',
        'toast-host.blade.php',
        'sesion-guardia.blade.php',
    ];

    $vistas = componentesBladeBds();

    foreach ($flotantes as $archivo) {
        expect(array_key_exists($archivo, $vistas))->toBeTrue("No existe el componente «{$archivo}».");

        $codigo = bdsSinComentarios($vistas[$archivo]);

        expect((bool) preg_match($declaracion, $codigo))->toBeTrue(
            "«{$archivo}» flota sobre la página y su borde sigue en --muni-border, que da 1,19:1 en ".
            'claro y 1,52:1 en oscuro: sin sombra el panel no tiene límite.'
        );
    }

    /*
     * La lateral solo flota bajo el punto de quiebre: ahí es un panel fijo con
     * --muni-shadow-lg sobre el contenido, y suelta (sin el armazón) ni siquiera
     * tiene velo. En columna sigue siendo un separador y conserva --muni-border,
     * así que el token va DENTRO de la consulta de medios, no en la regla base.
     */
    $lateral = bdsSinComentarios($vistas['sidebar.blade.php'] ?? '');
    $inicio = strpos($lateral, '@media (max-width:');

    expect($inicio)->not->toBeFalse('No se encontró la consulta de medios de la lateral superpuesta.');

    $superpuesta = bdsCuerpoDeMedios(substr($lateral, (int) $inicio));

    expect(str_contains($superpuesta, '.muni-sb--open'))->toBeTrue('El recorte de la consulta de medios de la lateral quedó corto.');

    expect((bool) preg_match($declaracion, $superpuesta))->toBeTrue(
        'La lateral superpuesta flota con --muni-shadow-lg y su borde derecho sigue en --muni-border.'
    );

    $base = substr($lateral, 0, (int) $inicio);

    expect((bool) preg_match('/\.muni-sb\s*\{[^}]*border-right:1px solid var\(--muni-border\)/', $base))->toBeTrue(
        'La lateral en columna es un separador: su borde base se queda en --muni-border.'
    );
});

it('contra el velo, el límite del diálogo se sigue viendo en cada rama de las dos hojas', function () {
    /*
     * En modal, drawer, paleta de comandos, lateral superpuesta y aviso de sesión
     * el borde NO toca el fondo de la página: por fuera toca el velo y por dentro
     * la superficie del panel. El panel se distingue del velo si su RELLENO ya
     * llega a 3:1 contra el velo, o si el borde es una línea a 3:1 contra LOS DOS
     * lados. En claro manda el relleno (panel blanco sobre velo oscuro, 3,2 a
     * 4,8:1): ahí el borde medio se funde con el velo y da igual, porque el límite
     * lo dibuja el blanco; con --muni-border pasaba lo mismo. En oscuro panel y
     * velo son casi el mismo negro (1,0 a 1,35:1) y el límite lo dibuja solo el
     * borde: era justo ahí donde --muni-border daba 1,06:1.
     *
     * El velo se lee del fuente de cada componente, no se copia acá: si alguien
     * lo aclara o lo cambia de color, esto se vuelve a medir solo.
     */
    $vistas = componentesBladeBds();

    $velos = [
        'modal.blade.php' => '/\.muni-modal__veil\s*\{[^}]*background:\s*([^;]+);\s*opacity:\s*([0-9.]+)/',
        'drawer.blade.php' => '/inset:0;background:(rgba\([^)]*\))/',
        'command-palette.blade.php' => '/inset:0;background:(rgba\([^)]*\))/',
        'dashboard-shell.blade.php' => '/\.muni-ds__scrim\s*\{[^}]*background:\s*(rgba\([^)]*\))/',
        'sesion-guardia.blade.php' => '/\.muni-sg__velo\s*\{[^}]*background:\s*([^;]+);\s*opacity:\s*([0-9.]+)/',
    ];

    $muni = cssMuniUi();
    $fila = cssMuniUiFilament();

    $ramas = [
        'muni-ui.css claro por omisión' => [bloqueTrasAncla($muni, 'Valores LIGHT (default)'), $muni],
        'muni-ui.css oscuro del sistema operativo' => [bloqueTrasAncla($muni, 'Regla 1: preferencia del OS'), $muni],
        'muni-ui.css oscuro explícito' => [bloqueTrasAncla($muni, 'Regla 2: activadores EXPLÍCITOS de dark'), $muni],
        'muni-ui.css claro congelado' => [bloqueTrasAncla($muni, 'Regla 3: override EXPLÍCITO a light'), $muni],
        'muni-ui-filament.css claro' => [bloqueTrasAncla($fila, ':root{'), $fila],
        'muni-ui-filament.css oscuro' => [bloqueTrasAncla($fila, 'En oscuro mandan los tonos institucionales'), $fila],
    ];

    /*
     * DEUDA CONOCIDA, con piso. El velo del modal es petróleo institucional y en
     * oscuro ACLARA la página: donde queda sobre --muni-surface-2, --muni-surface-3
     * o --muni-panel el panel y el velo casi no se distinguen (1,24 a 1,35:1) y el
     * borde flotante da de 2,77 a 2,85:1 (ui) y 2,89:1 (panel) contra
     * el velo, aunque 3,59–3,74:1 contra el panel. Con --muni-border daba 1,06:1:
     * mejora mucho, pero no llega. Ningún valor del borde lo arregla sin romper el
     * 3:1 contra el panel: lo que falta es un token de velo (--muni-scrim) casi
     * negro como el de drawer y paleta, que además saque los rgba() literales de
     * esos componentes. No se inventa desde un componente; queda pedido.
     * El piso impide que empeore; si un día cumple, la prueba pide sacarlo.
     */
    $deudaConocida = [
        'modal.blade.php | muni-ui.css oscuro | surface-2' => 2.7,
        'modal.blade.php | muni-ui.css oscuro | surface-3' => 2.7,
        'modal.blade.php | muni-ui.css oscuro | panel' => 2.7,
        'modal.blade.php | muni-ui-filament.css oscuro | surface-3' => 2.8,
    ];
    $vistos = [];

    $fallos = [];

    foreach ($velos as $archivo => $patron) {
        $codigo = bdsSinComentarios($vistas[$archivo] ?? '');

        expect((bool) preg_match($patron, $codigo, $m))->toBeTrue("No se pudo leer el velo de «{$archivo}».");

        foreach ($ramas as $rama => [$bloque, $hoja]) {
            $borde = tokenColor($bloque, $hoja, 'overlay-border');
            $panel = tokenColor($bloque, $hoja, 'surface');
            [$tinte, $alfa] = bdsVelo($m[1], $m[2] ?? null, $bloque, $hoja);

            expect($borde !== null && $panel !== null && $tinte !== null)->toBeTrue("{$archivo} en {$rama}: no se resolvió borde, superficie o velo.");

            // Debajo del velo puede quedar cualquier superficie de la página.
            foreach (bdsSuperficies() as $debajo) {
                $fondo = tokenColor($bloque, $hoja, $debajo);

                if ($fondo === null) {
                    continue;
                }

                $velo = bdsComponer($tinte, $alfa, $fondo);
                $relleno = ratioContraste($panel, $velo);
                $adentro = ratioContraste($borde, $panel);
                $afuera = ratioContraste($borde, $velo);

                if (getenv('BDS_TABLA')) {
                    fwrite(STDERR, sprintf("%-26s %-42s %-10s panel/velo %.2f  borde/panel %.2f  borde/velo %.2f\n", $archivo, $rama, $debajo, $relleno, $adentro, $afuera));
                }

                // O el relleno del panel ya se despega del velo, o el borde es una
                // línea que se ve contra los DOS lados. Un borde que contrasta solo
                // con uno de ellos no dibuja nada si panel y velo son del mismo tono.
                $cumple = $relleno >= 3.0 || min($adentro, $afuera) >= 3.0;
                $caso = "{$archivo} | ".strtok($rama, ' ').' '.(str_contains($rama, 'oscuro') ? 'oscuro' : 'claro')." | {$debajo}";

                if (isset($deudaConocida[$caso])) {
                    $vistos[$caso] = true;

                    if ($cumple) {
                        $fallos[] = "{$caso}: ya llega a 3:1 en {$rama}; sácalo de la deuda conocida.";
                    } elseif ($afuera < $deudaConocida[$caso]) {
                        $fallos[] = sprintf('%s en %s: el borde bajó a %.2f:1 contra el velo, bajo el piso de %.2f:1 de la deuda conocida.', $caso, $rama, $afuera, $deudaConocida[$caso]);
                    }

                    continue;
                }

                if (! $cumple) {
                    $fallos[] = sprintf('%s en %s, velo sobre --muni-%s: panel %s contra velo %s = %.2f:1, y el borde %s da %.2f:1 adentro y %.2f:1 afuera',
                        $archivo, $rama, $debajo, $panel, $velo, $relleno, $borde, $adentro, $afuera);
                }
            }
        }
    }

    expect(array_keys($vistos))->toEqualCanonicalizing(array_keys($deudaConocida), 'Una entrada de la deuda conocida ya no corresponde a ningún caso medido.');
    expect($fallos)->toBe([], "El límite del diálogo se pierde contra el velo (WCAG 1.4.11):\n  ".implode("\n  ", $fallos));
});

it('todo uso del borde flotante trae el respaldo al borde general', function () {
    /*
     * El CSS se publica a mano en cada sistema y los componentes llegan con
     * `composer update`. Un `var(--muni-overlay-border)` sin respaldo, en un
     * sistema con la hoja vieja, es inválido en tiempo de cómputo: el borde cae
     * a `currentColor` y el panel sale con un marco del color del texto.
     */
    $sinRespaldo = [];

    foreach (componentesBladeBds() as $archivo => $fuente) {
        $codigo = (string) preg_replace(['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'], '', $fuente);

        $usos = substr_count($codigo, 'var(--muni-overlay-border');
        $conRespaldo = substr_count($codigo, 'var(--muni-overlay-border, var(--muni-border))');

        if ($usos !== $conRespaldo) {
            $sinRespaldo[] = $archivo;
        }
    }

    expect($sinRespaldo)->toBe([], 'Usan --muni-overlay-border sin respaldo: '.implode(', ', $sinRespaldo));
});

/** El fuente sin comentarios CSS ni Blade, para que la prosa no cuente como código. */
function bdsSinComentarios(string $fuente): string
{
    return (string) preg_replace(['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'], '', $fuente);
}

/**
 * El color y la opacidad de un velo: `rgba(r,g,b,a)` literal, o un token con
 * `opacity` aparte. El token se resuelve en la RAMA, no en la hoja entera: el
 * velo de sesión es `--muni-bg` y en oscuro no vale lo mismo que en claro.
 *
 * @return array{0: ?string, 1: float}
 */
function bdsVelo(string $fondo, ?string $opacidad, string $bloque, string $hoja): array
{
    $alfa = $opacidad === null ? 1.0 : (float) $opacidad;
    $fondo = trim($fondo);

    if (preg_match('/^rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([0-9.]+)\s*\)$/', $fondo, $m)) {
        return [sprintf('#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3]), $alfa * (float) $m[4]];
    }

    if (preg_match('/^var\(\s*--muni-([a-z0-9-]+)\s*(?:,[^)]*)?\)$/', $fondo, $m)) {
        return [tokenColor($bloque, $hoja, $m[1]) ?? colorResuelto($fondo, $hoja), $alfa];
    }

    return [colorResuelto($fondo, $hoja), $alfa];
}

/** Un tinte con opacidad compuesto sobre un fondo opaco, en sRGB como pinta el navegador. */
function bdsComponer(string $tinte, float $alfa, string $fondo): string
{
    $mezcla = '#';

    for ($i = 1; $i < 7; $i += 2) {
        $mezcla .= sprintf('%02x', (int) round(hexdec(substr($tinte, $i, 2)) * $alfa + hexdec(substr($fondo, $i, 2)) * (1 - $alfa)));
    }

    return $mezcla;
}

/**
 * El cuerpo entero de una consulta de medios, con sus reglas anidadas. Se abre en
 * la primera llave DESPUÉS del paréntesis de la condición: la de la lateral lleva
 * un eco de Blade con llaves propias dentro del paréntesis.
 */
function bdsCuerpoDeMedios(string $desde): string
{
    $apertura = strpos($desde, '{', (int) strpos($desde, ')'));

    if ($apertura === false) {
        return '';
    }

    $nivel = 0;

    for ($i = $apertura, $largo = strlen($desde); $i < $largo; $i++) {
        $nivel += $desde[$i] === '{' ? 1 : ($desde[$i] === '}' ? -1 : 0);

        if ($nivel === 0) {
            return substr($desde, $apertura + 1, $i - $apertura - 1);
        }
    }

    return '';
}

/** Los componentes, nombre => fuente. Prefijo propio: Pest carga todos los archivos juntos. */
function componentesBladeBds(): array
{
    $vistas = [];

    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') ?: [] as $ruta) {
        $vistas[basename($ruta)] = (string) file_get_contents($ruta);
    }

    return $vistas;
}
