<?php

/*
|--------------------------------------------------------------------------
| Cada archivo de prueba se puede correr SOLO
|--------------------------------------------------------------------------
|
| Once archivos llegaron a llamar funciones declaradas dentro de OTRO archivo de
| prueba (`fuenteDeLaVista()` de HojaImprimibleTest, `fuenteDeAlpine()` de
| GeneraVitrinaTest, `paletasDelPaquete()` de ContrasteAlertaTest…). En la suite
| entera pasaba de casualidad: Pest carga todos los archivos antes de ejecutar el
| primero. En `pest --parallel`, o al correr `pest tests/GuardiaDeSesionTest.php`
| solo, el archivo que declara la función no se cargaba en ese proceso y la
| prueba moría con «Call to undefined function» — un rojo que no dice nada del
| componente.
|
| La regla: una función que usa más de un archivo vive en `tests/Pest.php` o en
| `tests/Helpers/`, que Pest carga siempre y primero. Este candado lee el código
| con el tokenizador de PHP (no con grep: los nombres aparecen a cada rato en
| comentarios y en mensajes) y cae si:
|
|   · un `*Test.php` llama una función que no declara él ni los helpers
|     compartidos, pero sí otro `*Test.php`;
|   · un helper compartido llama una función que solo declara un `*Test.php`
|     (el mismo defecto, un nivel más abajo).
|
| Lo que NO ve: una función pasada como cadena (`array_map('nombre', …)`). No hay
| ninguna en la suite; si aparece una, que sea de un helper compartido.
*/

/**
 * Las funciones con nombre que DECLARA un fuente PHP y las que LLAMA, en
 * minúsculas (PHP no distingue mayúsculas en nombres de función).
 *
 * Los métodos de una clase, trait, interfaz o enum no son funciones globales y
 * no cuentan como declaración. Una función con nombre declarada dentro de un
 * closure SÍ cuenta: al ejecutarse queda global igual.
 *
 * @return array{declara: array<string, string>, llama: array<string, string>}
 */
function simbolosDelFuentePhp(string $codigo): array
{
    $tokens = array_values(array_filter(
        token_get_all($codigo),
        fn ($t) => ! is_array($t) || ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
    ));

    $tipo = fn ($t) => is_array($t) ? $t[0] : $t;
    $declara = [];
    $llama = [];
    $pila = [];
    $abreClase = false;

    foreach ($tokens as $i => $token) {
        $actual = $tipo($token);
        $previo = $i > 0 ? $tipo($tokens[$i - 1]) : null;

        if (in_array($actual, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $previo !== T_DOUBLE_COLON) {
            $abreClase = true;

            continue;
        }

        if (in_array($actual, ['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            $pila[] = $abreClase && $actual === '{' ? 'clase' : 'bloque';
            $abreClase = $abreClase && $actual !== '{';

            continue;
        }

        if ($actual === '}') {
            array_pop($pila);

            continue;
        }

        if (! in_array($actual, [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }

        $nombre = strtolower(ltrim($token[1], '\\'));
        $esDeclaracion = $previo === T_FUNCTION
            || (in_array($previo, ['&', T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG], true) && $i > 1 && $tipo($tokens[$i - 2]) === T_FUNCTION);

        if ($esDeclaracion) {
            if (! in_array('clase', $pila, true)) {
                $declara[$nombre] = $token[1];
            }

            continue;
        }

        $siguiente = $tokens[$i + 1] ?? null;
        $noEsLlamada = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_CONST];

        if ($siguiente === '(' && ! in_array($previo, $noEsLlamada, true)) {
            $llama[$nombre] = ltrim($token[1], '\\');
        }
    }

    return ['declara' => $declara, 'llama' => $llama];
}

/**
 * Cada llamada de un archivo de prueba (o de un helper compartido) a una función
 * que solo declara OTRO archivo de prueba, como frase legible.
 *
 * @return array{cruces: list<string>, pruebas: int}
 */
function dependenciasCruzadasEntrePruebas(string $dirPruebas): array
{
    $dirPruebas = rtrim($dirPruebas, '/');
    $pruebas = [];
    $compartidos = [];

    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPruebas, FilesystemIterator::SKIP_DOTS));

    foreach ($iterador as $archivo) {
        $ruta = $archivo->getPathname();
        $relativa = substr($ruta, strlen($dirPruebas) + 1);

        if (str_ends_with($relativa, 'Test.php')) {
            $pruebas[$relativa] = simbolosDelFuentePhp((string) file_get_contents($ruta));
        } elseif ($relativa === 'Pest.php' || (str_starts_with($relativa, 'Helpers/') && str_ends_with($relativa, '.php'))) {
            $compartidos[$relativa] = simbolosDelFuentePhp((string) file_get_contents($ruta));
        }
    }

    ksort($pruebas);
    ksort($compartidos);

    $declaradaEnCompartidos = array_merge([], ...array_values(array_column($compartidos, 'declara')));
    $cruces = [];

    $declarantesDe = function (string $nombre, string $excepto) use ($pruebas): array {
        $declarantes = [];

        foreach ($pruebas as $archivo => $simbolos) {
            if ($archivo !== $excepto && isset($simbolos['declara'][$nombre])) {
                $declarantes[] = $archivo;
            }
        }

        return $declarantes;
    };

    foreach ($pruebas as $archivo => $simbolos) {
        foreach ($simbolos['llama'] as $nombre => $comoSeEscribe) {
            if (isset($simbolos['declara'][$nombre]) || isset($declaradaEnCompartidos[$nombre])) {
                continue;
            }

            if ($declarantes = $declarantesDe($nombre, $archivo)) {
                $cruces[] = "{$archivo} llama a {$comoSeEscribe}(), que está declarada en ".implode(', ', $declarantes);
            }
        }
    }

    foreach ($compartidos as $archivo => $simbolos) {
        foreach ($simbolos['llama'] as $nombre => $comoSeEscribe) {
            if (isset($declaradaEnCompartidos[$nombre])) {
                continue;
            }

            if ($declarantes = $declarantesDe($nombre, $archivo)) {
                $cruces[] = "{$archivo} (compartido) llama a {$comoSeEscribe}(), que solo declara ".implode(', ', $declarantes);
            }
        }
    }

    return ['cruces' => $cruces, 'pruebas' => count($pruebas)];
}

it('ningún archivo de prueba llama funciones declaradas en otro archivo de prueba', function () {
    $resultado = dependenciasCruzadasEntrePruebas(__DIR__);

    // Un glob que no encuentra nada también daría «cero cruces». Hay más de
    // sesenta archivos de prueba: si el recorrido ve menos, el candado está ciego.
    expect($resultado['pruebas'])->toBeGreaterThan(50, 'El candado no encontró los archivos *Test.php: está mirando otro directorio.');

    expect($resultado['cruces'])->toBe([],
        "Hay archivos de prueba que dependen de funciones declaradas en OTRO archivo de prueba.\n"
        ."Pasa en la suite entera por casualidad y revienta en `pest --parallel` o al correr el archivo solo.\n"
        ."Mueve la función a tests/Helpers/ (o a tests/Pest.php) y deja un comentario donde estaba:\n  · "
        .implode("\n  · ", $resultado['cruces'])
    );
});

it('el lector distingue declaraciones, métodos, llamadas y comentarios', function () {
    $simbolos = simbolosDelFuentePhp(<<<'PHP'
    <?php
    // compartida() en un comentario no es una llamada
    function Propia(): string { return compartida(); }
    function &porReferencia(): array { static $a = []; return $a; }
    $x = new class { public function metodo() { return \global_calificada(); } };
    $x->metodo();
    Algo::estatico();
    it('mensaje con prestada() adentro', function () {
        function dentroDeClosure() {}
        return prestada('a');
    });
    enum Tono { case Ok; public function etiqueta(): string { return 'ok'; } }
    PHP);

    expect(array_keys($simbolos['declara']))->toEqualCanonicalizing(['propia', 'porreferencia', 'dentrodeclosure']);
    expect(array_keys($simbolos['llama']))->toEqualCanonicalizing(['compartida', 'global_calificada', 'it', 'prestada']);
});

it('el candado caza una dependencia cruzada entre dos archivos y la de un helper', function () {
    $dir = sys_get_temp_dir().'/muni-ui-candado-'.bin2hex(random_bytes(6));
    mkdir($dir.'/Helpers', 0777, true);

    file_put_contents($dir.'/Pest.php', "<?php\nfunction deVerdadCompartida() {}\n");
    file_put_contents($dir.'/Helpers/Uno.php', "<?php\nfunction ayuda() { return soloDelDueño(); }\n");
    file_put_contents($dir.'/DueñoTest.php', "<?php\nfunction soloDelDueño() {}\nfunction tambienSuya() {}\nit('x', fn () => tambienSuya());\n");
    file_put_contents($dir.'/PrestadoTest.php', "<?php\nit('y', fn () => [deVerdadCompartida(), ayuda(), tambienSuya()]);\n");

    try {
        $resultado = dependenciasCruzadasEntrePruebas($dir);
    } finally {
        foreach (['Pest.php', 'Helpers/Uno.php', 'DueñoTest.php', 'PrestadoTest.php'] as $archivo) {
            @unlink($dir.'/'.$archivo);
        }
        @rmdir($dir.'/Helpers');
        @rmdir($dir);
    }

    expect($resultado['pruebas'])->toBe(2);
    expect($resultado['cruces'])->toBe([
        'PrestadoTest.php llama a tambienSuya(), que está declarada en DueñoTest.php',
        'Helpers/Uno.php (compartido) llama a soloDelDueño(), que solo declara DueñoTest.php',
    ]);
});
