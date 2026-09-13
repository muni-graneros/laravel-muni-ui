<?php

/*
|--------------------------------------------------------------------------
| EL PAPEL LE GANA AL TEMA, POR CONSTRUCCIÓN
|--------------------------------------------------------------------------
|
| La reja de accesibilidad (`scripts/a11y-check.py`, pasada de impresión) midió
| tres defectos de la misma familia. Los tres imprimían ilegible sin que el
| funcionario tocara nada:
|
| 1. `muni-ui.css` con el sistema operativo en oscuro: 177 textos a 1,23:1 sobre
|    el papel. La regla de la preferencia del sistema,
|    `:root:not([data-muni-theme="light"]):not([data-theme="light"]):not(.light)`,
|    puntúa 0,4,0 y le ganaba al `:root` del bloque de impresión, que puntúa
|    0,1,0. El orden no alcanza cuando la especificidad es distinta.
| 2. El panel en oscuro: 36 textos a 1,11:1. `.dark .fi-header-heading` y
|    `.dark .fi-section-header-heading` escribían `#eaf5f3 !important` en la HOJA
|    del elemento, así que el reset de tokens de la raíz no los alcanzaba nunca.
|    Es la misma causa que D9 (el fondo), que se había arreglado enumerando
|    selectores: el siguiente `.dark …` con un literal volvía a romperlo.
| 3. Botón primario y página actual con «gráficos de fondo»: texto negro sobre
|    `#0f766e` a 3,84:1, en las dos paletas.
|
| Las pruebas NO miran selectores sueltos: miran las dos invariantes que hacen
| que el bloque de impresión gane sin enumerar nada.
|
|   A. Ninguna regla de tema que fije un token que el papel redefine puede
|      puntuar más que el selector con que el papel lo redefine, salvo que viva
|      en un `@media screen` (en papel no existe).
|   B. Toda regla `.dark …` que pinta color, fondo o borde lo toma de un token
|      que el papel redefine. Un literal en la hoja del elemento no lo alcanza
|      ningún reset de la raíz, lleve la especificidad que lleve.
|
| Los helpers llevan prefijo propio y no dependen de otros archivos de prueba:
| Pest los carga todos en el mismo proceso y un nombre repetido mata la suite.
*/

/** Las dos hojas publicables, nombre => CSS. */
function imprHojas(): array
{
    return [
        'muni-ui.css' => (string) file_get_contents(__DIR__.'/../resources/css/muni-ui.css'),
        'muni-ui-filament.css' => (string) file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css'),
    ];
}

/**
 * Las reglas de estilo de una hoja, en orden, con las at-rules que las envuelven.
 *
 * @return list<array{contexto: list<string>, selector: string, cuerpo: string, orden: int}>
 */
function imprReglas(string $css): array
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
    $reglas = [];
    $pila = [];
    $prefijo = '';
    $largo = strlen($css);

    for ($i = 0; $i < $largo; $i++) {
        $c = $css[$i];

        if ($c === '{') {
            $cabecera = trim((string) preg_replace('/\s+/', ' ', $prefijo));
            $prefijo = '';

            if (str_starts_with($cabecera, '@')) {
                $pila[] = $cabecera;

                continue;
            }

            $nivel = 1;
            $j = $i + 1;

            while ($j < $largo && $nivel > 0) {
                $nivel += $css[$j] === '{' ? 1 : ($css[$j] === '}' ? -1 : 0);
                $j++;
            }

            $reglas[] = [
                'contexto' => $pila,
                'selector' => $cabecera,
                'cuerpo' => substr($css, $i + 1, $j - $i - 2),
                'orden' => count($reglas),
            ];
            $i = $j - 1;
        } elseif ($c === '}') {
            array_pop($pila);
            $prefijo = '';
        } elseif ($c === ';') {
            // Declaraciones sueltas de un @page o un @import: no son cabecera de nada.
            $prefijo = '';
        } else {
            $prefijo .= $c;
        }
    }

    return $reglas;
}

function imprEsDeImpresion(array $regla): bool
{
    foreach ($regla['contexto'] as $at) {
        if (preg_match('/^@media\b.*\bprint\b/i', $at)) {
            return true;
        }
    }

    return false;
}

/** La regla solo existe en pantalla: en papel no compite con nada. */
function imprSoloPantalla(array $regla): bool
{
    foreach ($regla['contexto'] as $at) {
        if (preg_match('/^@media\s+(only\s+)?screen\b/i', $at)) {
            return true;
        }
    }

    return false;
}

/** Separa una lista de selectores por las comas de primer nivel. */
function imprSelectores(string $lista): array
{
    $partes = [];
    $actual = '';
    $nivel = 0;

    foreach (str_split($lista) as $c) {
        $nivel += $c === '(' ? 1 : ($c === ')' ? -1 : 0);

        if ($c === ',' && $nivel === 0) {
            $partes[] = trim($actual);
            $actual = '';

            continue;
        }

        $actual .= $c;
    }

    $partes[] = trim($actual);

    return array_values(array_filter($partes, fn ($p) => $p !== ''));
}

/** Especificidad [ids, clases/atributos/pseudoclases, elementos] de UN selector. */
function imprEspecificidad(string $selector): array
{
    $a = $b = $c = 0;

    // Los atributos primero: su valor puede traer «:» o «.» entre comillas.
    $selector = (string) preg_replace('/\[[^\]]*\]/', ' ', $selector, -1, $atributos);
    $b += $atributos;

    // :where() vale cero; :not(), :is() y :has() valen lo que su argumento más alto.
    $selector = (string) preg_replace('/:where\((?:[^()]|\([^()]*\))*\)/', ' ', $selector);

    while (preg_match('/:(?:not|is|has)\(((?:[^()]|\([^()]*\))*)\)/', $selector, $m)) {
        $maximo = [0, 0, 0];

        foreach (imprSelectores($m[1]) as $argumento) {
            $maximo = max($maximo, imprEspecificidad($argumento));
        }

        [$a, $b, $c] = [$a + $maximo[0], $b + $maximo[1], $c + $maximo[2]];
        $selector = str_replace($m[0], ' ', $selector);
    }

    $selector = (string) preg_replace('/::[\w-]+(\([^)]*\))?/', ' ', $selector, -1, $pseudoElementos);
    $c += $pseudoElementos;
    $selector = (string) preg_replace('/:[\w-]+(\([^)]*\))?/', ' ', $selector, -1, $pseudoClases);
    $b += $pseudoClases;
    $selector = (string) preg_replace('/#[\w-]+/', ' ', $selector, -1, $ids);
    $a += $ids;
    $selector = (string) preg_replace('/\.[\w-]+/', ' ', $selector, -1, $clases);
    $b += $clases;
    $c += preg_match_all('/(?:^|[\s>+~])[a-zA-Z][\w-]*/', $selector);

    return [$a, $b, $c];
}

/** Las propiedades personalizadas que un cuerpo de regla declara. */
function imprTokensDeclarados(string $cuerpo): array
{
    preg_match_all('/(?:^|[;{\s])(--[\w-]+)\s*:/', $cuerpo, $m);

    return array_values(array_unique($m[1]));
}

/** Tokens que el papel redefine y los selectores con que lo hace, por hoja. */
function imprResetDelPapel(string $css): array
{
    $tokens = [];
    $selectores = [];
    $ultimoOrden = -1;

    foreach (imprReglas($css) as $regla) {
        if (! imprEsDeImpresion($regla)) {
            continue;
        }

        $declarados = imprTokensDeclarados($regla['cuerpo']);

        if ($declarados === []) {
            continue;
        }

        $tokens = array_merge($tokens, $declarados);
        $selectores = array_merge($selectores, imprSelectores($regla['selector']));
        $ultimoOrden = max($ultimoOrden, $regla['orden']);
    }

    return ['tokens' => array_values(array_unique($tokens)), 'selectores' => $selectores, 'orden' => $ultimoOrden];
}

function imprFormato(array $e): string
{
    return implode(',', $e);
}

it('en papel, ninguna regla de tema le gana al reset de tokens por especificidad', function () {
    foreach (imprHojas() as $hoja => $css) {
        $papel = imprResetDelPapel($css);

        expect($papel['tokens'])->toContain('--muni-text');

        // El reset gana por orden solo si nadie lo supera en especificidad: se
        // compara contra el MÁS DÉBIL de sus selectores, que es el que tiene que
        // alcanzar a la raíz.
        $minimo = null;

        foreach ($papel['selectores'] as $s) {
            $e = imprEspecificidad($s);
            $minimo = $minimo === null ? $e : min($minimo, $e);
        }

        $infractores = [];
        $revisadas = 0;

        foreach (imprReglas($css) as $regla) {
            if (imprEsDeImpresion($regla)) {
                continue;
            }

            $pisa = array_intersect(imprTokensDeclarados($regla['cuerpo']), $papel['tokens']);

            if ($pisa === []) {
                continue;
            }

            $revisadas++;

            if (imprSoloPantalla($regla)) {
                continue;
            }

            foreach (imprSelectores($regla['selector']) as $s) {
                if (imprEspecificidad($s) > $minimo || $regla['orden'] > $papel['orden']) {
                    $infractores[] = sprintf('%s (%s)', $s, imprFormato(imprEspecificidad($s)));
                }
            }
        }

        // Sin reglas de tema revisadas la prueba pasaría sola.
        expect($revisadas)->toBeGreaterThan(0);

        expect($infractores)->toBe([], sprintf(
            '«%s»: estas reglas fijan tokens que el bloque de impresión redefine, pero puntúan más '.
            'que su selector más débil (%s) o vienen después, así que en papel gana la pantalla. '.
            'Con el sistema operativo en oscuro salían 177 textos a 1,23:1. Si es un tema de '.
            'pantalla, va dentro de `@media screen and (…)`: %s',
            $hoja, imprFormato((array) $minimo), implode(' | ', $infractores)
        ));
    }
});

it('la preferencia oscura del sistema operativo es de pantalla, no de papel', function () {
    $fuentes = imprHojas();

    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') ?: [] as $vista) {
        $fuentes[basename($vista)] = (string) file_get_contents($vista);
    }

    $vistas = 0;

    foreach ($fuentes as $nombre => $fuente) {
        $fuente = (string) preg_replace(['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'], '', $fuente);

        preg_match_all('/@media[^{;]*prefers-color-scheme\s*:\s*dark[^{;]*\{/i', $fuente, $m);

        foreach ($m[0] as $consulta) {
            $vistas++;

            expect((bool) preg_match('/@media\s+(only\s+)?screen\b/i', $consulta))->toBeTrue(
                "«{$nombre}»: `{$consulta}` no está atada a `screen`. Una impresora no tiene modo ".
                'oscuro, pero el navegador SÍ reporta la preferencia del sistema al imprimir: la '.
                'paleta oscura entra en la hoja y el texto casi blanco sale sobre papel blanco.'
            );
        }
    }

    // El tema oscuro por preferencia del sistema existe en muni-ui.css: si la
    // búsqueda no lo encuentra, la prueba no está mirando nada.
    expect($vistas)->toBeGreaterThan(0);
});

it('en el tema, toda regla .dark toma color, fondo y borde de un token que el papel redefine', function () {
    $propiedades = '/^(color|background|background-color|border|border-color|border-(?:top|right|bottom|left|inline|block)(?:-(?:start|end))?(?:-color)?|outline-color)$/i';
    $literal = '/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\(/i';

    foreach (imprHojas() as $hoja => $css) {
        $papel = imprResetDelPapel($css);
        $infractores = [];
        $revisadas = 0;

        foreach (imprReglas($css) as $regla) {
            if (imprEsDeImpresion($regla) || ! preg_match('/\.dark\b\s*\S/', $regla['selector'])) {
                continue;
            }

            foreach (explode(';', $regla['cuerpo']) as $declaracion) {
                if (! str_contains($declaracion, ':')) {
                    continue;
                }

                [$propiedad, $valor] = array_map('trim', explode(':', $declaracion, 2));

                if (! preg_match($propiedades, $propiedad)) {
                    continue;
                }

                $revisadas++;
                $selector = trim(preg_replace('/\s+/', ' ', $regla['selector']));

                if (preg_match($literal, $valor)) {
                    $infractores[] = "{$selector} { {$propiedad}: {$valor} } — color literal";

                    continue;
                }

                preg_match_all('/var\(\s*(--[\w-]+)/', $valor, $vars);

                foreach ($vars[1] as $var) {
                    if (! in_array($var, $papel['tokens'], true)) {
                        $infractores[] = "{$selector} { {$propiedad}: {$valor} } — {$var} no se redefine en impresión";
                    }
                }
            }
        }

        if ($hoja === 'muni-ui-filament.css') {
            // El tema del panel escribe su modo oscuro selector por selector: si
            // no encuentra ninguno, el patrón dejó de mirar el archivo.
            expect($revisadas)->toBeGreaterThan(0);
        }

        expect($infractores)->toBe([], sprintf(
            '«%s»: estas reglas del modo oscuro escriben su color en la hoja del elemento. El bloque '.
            'de impresión redefine los tokens en la raíz, así que no las alcanza —y con `!important` '.
            'y 0,2,0 tampoco las alcanzaría un reset de `body`—: en papel sale el tono oscuro '.
            '(#eaf5f3 sobre blanco dio 1,11:1). Usa el token equivalente: %s',
            $hoja, implode(' | ', $infractores)
        ));
    }
});

/*
| El tercer caso, que las dos invariantes de arriba NO cubren y no pueden cubrir:
| el contenido propio de Filament. `.fi-callout .fi-callout-heading:where(.dark,
| .dark *)` escribe `var(--color-white)` y la descripción `var(--gray-400)`
| (#9f9fa9): tokens de Filament, no `--muni-*`. Redefinir la paleta municipal en
| la raíz no los alcanza, y son 218 clases: enumerarlas es una lista que caduca
| con cada versión del panel.
|
| Medido con el CSS real del panel de licencias, en oscuro y con `media=print`:
| el título del aviso salía a 1,00:1 y la descripción a 2,62:1 sobre el papel.
|
| La única regla que los alcanza sin enumerar nada fuerza el color de TODO lo
| que cuelga de `.dark`. Y eso cuesta: el texto de Filament pierde su color de
| estado en papel. Por eso va detrás de un activador explícito del sistema
| anfitrión y apagado por omisión — y por eso esta prueba vigila las dos mitades:
| que la regla exista, y que NINGUNA versión suya quede sin activador.
*/

/** Reglas de impresión que fuerzan `color` sobre cualquier descendiente de `.dark`. */
function imprAplanadoDeColor(string $css): array
{
    $encontradas = [];

    foreach (imprReglas($css) as $regla) {
        if (! imprEsDeImpresion($regla) || ! preg_match('/(?:^|[;{\s])color\s*:/', $regla['cuerpo'])) {
            continue;
        }

        foreach (imprSelectores($regla['selector']) as $s) {
            // El `*` final es lo que lo vuelve indiscriminado: alcanza al
            // contenido de Filament sin nombrar una sola de sus clases.
            if (preg_match('/\.dark\b[^,]*\*\s*$/', $s)) {
                $encontradas[] = ['selector' => $s, 'cuerpo' => $regla['cuerpo']];
            }
        }
    }

    return $encontradas;
}

it('el aplanado de color en papel no existe sin el activador del sistema anfitrión', function () {
    $activador = 'data-muni-print-plain';

    $revisadas = 0;

    foreach (imprHojas() as $hoja => $css) {
        foreach (imprAplanadoDeColor($css) as $regla) {
            $revisadas++;

            // `toContain` con mensaje no existe en Pest: el segundo argumento es
            // otra aguja que buscar, así que la prueba fallaba por el mensaje.
            expect(str_contains($regla['selector'], "[{$activador}]"))->toBeTrue(sprintf(
                '«%s»: `%s` fuerza el color de todo lo que cuelga de `.dark` al imprimir, sin el '.
                'activador. Eso le quita el color de estado al texto de Filament en los nueve '.
                'sistemas sin que ninguno lo haya pedido. La regla existe, pero solo detrás de '.
                '`[%s]` en el <html> del panel.',
                $hoja, $regla['selector'], $activador
            ));
        }
    }

    // Sin ninguna regla de aplanado la prueba pasaría sola, y se iría en silencio
    // junto con lo que vigila.
    expect($revisadas)->toBeGreaterThan(0,
        'No hay ninguna regla de aplanado que revisar: el patrón dejó de mirar el archivo.'
    );
});

it('con el activador, el aplanado alcanza a las clases de Filament y toma el token del papel', function () {
    $activador = 'data-muni-print-plain';
    $reglas = imprAplanadoDeColor(imprHojas()['muni-ui-filament.css']);

    expect($reglas)->not->toBe([], sprintf(
        '«muni-ui-filament.css»: no hay ninguna regla de impresión que fuerce el color bajo `.dark *`. '.
        'Sin ella, `[%s]` no hace nada y el título de un aviso de Filament sigue saliendo a 1,00:1 '.
        '(blanco sobre el papel), porque lee `--color-white` y no un token `--muni-*`.',
        $activador
    ));

    // Las dos formas tienen que estar: el panel pone `.dark` y el activador en el
    // MISMO <html>, así que `[activador] .dark *` sola no lo alcanzaría nunca.
    $selectores = array_column($reglas, 'selector');

    expect(array_filter($selectores, fn ($s) => str_starts_with($s, ".dark[{$activador}]")))->not->toBe([],
        "Falta la forma `.dark[{$activador}] *`: el panel pone `.dark` y el activador en el mismo ".
        '<html>, y un descendiente no es su propio ancestro.'
    );

    expect(array_filter($selectores, fn ($s) => str_starts_with($s, "[{$activador}] .dark")))->not->toBe([],
        "Falta la forma `[{$activador}] .dark *`: hay hosts que ponen `.dark` en el <body> y el ".
        'activador en el <html>.'
    );

    $papel = imprResetDelPapel(imprHojas()['muni-ui-filament.css']);

    foreach ($reglas as $regla) {
        preg_match_all('/(?:^|[;{\s])color\s*:\s*([^;}]+)/', $regla['cuerpo'], $m);

        foreach ($m[1] as $valor) {
            expect(str_contains($valor, '!important'))->toBeTrue(
                "«{$regla['selector']}»: sin `!important` no le gana a `:where(.dark, .dark *)` de ".
                'Filament, que puntúa 0,2,0 igual que esta regla.'
            );

            preg_match_all('/var\(\s*(--[\w-]+)/', $valor, $vars);

            expect($vars[1])->not->toBe([], "«{$regla['selector']}»: el color sale de un token, no de un literal.");

            foreach ($vars[1] as $var) {
                expect(in_array($var, $papel['tokens'], true))->toBeTrue(sprintf(
                    '«%s»: `%s` no se redefine en el bloque de impresión, así que en papel valdría el '.
                    'tono del tema oscuro y el aplanado dejaría el documento peor que antes.',
                    $regla['selector'], $var
                ));
            }
        }
    }
});

it('en papel, el texto sobre el acento se lee con y sin gráficos de fondo', function () {
    foreach (imprHojas() as $hoja => $css) {
        $papel = '';

        foreach (imprReglas($css) as $regla) {
            if (imprEsDeImpresion($regla) && str_contains($regla['cuerpo'], '--muni-accent:')) {
                $papel = $regla['cuerpo'];
            }
        }

        $acento = tokenHex($papel, 'accent');
        $sobreAcento = tokenHex($papel, 'on-accent');
        $blanco = tokenHex($papel, 'surface');

        expect([$acento, $sobreAcento, $blanco])->not->toContain(null);

        // Con «gráficos de fondo» el relleno del botón primario sale impreso.
        expect(ratioContraste($sobreAcento, $acento))->toBeGreaterThanOrEqual(4.5,
            "«{$hoja}»: --muni-on-accent ({$sobreAcento}) sobre --muni-accent ({$acento}) en papel no llega a ".
            '4,5:1. Es el botón primario y la página actual con los fondos activados (medido: 3,84:1).'
        );

        // Sin fondos (lo que hace la impresora por omisión) el texto cae sobre el papel.
        expect(ratioContraste($sobreAcento, $blanco))->toBeGreaterThanOrEqual(4.5,
            "«{$hoja}»: --muni-on-accent ({$sobreAcento}) sobre el papel ({$blanco}) no llega a 4,5:1: ".
            'sin gráficos de fondo el relleno desaparece y el texto queda solo sobre la hoja.'
        );

        // Y el acento también es color de texto (enlaces, cifras destacadas).
        expect(ratioContraste($acento, $blanco))->toBeGreaterThanOrEqual(4.5,
            "«{$hoja}»: --muni-accent ({$acento}) como texto sobre el papel no llega a 4,5:1."
        );
    }
});
