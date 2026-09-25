<?php

use Illuminate\Support\Facades\Blade;

/**
 * EL RESPALDO DEL FOCO DE `segmented`, Y EL NOMBRE DEL GRUPO.
 *
 * `segmented` se documenta como «radios reales sin JS» (README:169): está
 * pensado justamente para el camino degradado. Pero su ÚNICO indicador de foco
 * era `.muni-seg:has(input:focus-visible)`, y en todo el paquete no había una
 * sola aparición de `@supports`, contra lo que exige CLAUDE.md («CSS moderno
 * solo como mejora progresiva, dentro de `@supports`»). Donde `:has()` no
 * resuelva, el control se quedaba SIN anillo de foco: WCAG 2.2 AA 2.4.7, que
 * en un sistema del Estado chileno es el Decreto N°1/2015 SEGPRES.
 *
 * El respaldo tiene que colgar del elemento que recibe el foco de verdad —el
 * radio— y pintarse sobre algo visible, porque el radio va `opacity:0` y 0×0.
 * `:has()` queda como lo que es: una mejora que traslada el anillo a la
 * píldora entera.
 *
 * Segundo candado: un grupo de opciones excluyentes sin nombre accesible
 * incumple 1.3.1 y 4.1.2 — el lector anuncia «grupo» y no dice de qué.
 *
 * `toContain('valor', 'mensaje')` NO sirve acá: en Pest el segundo argumento
 * es otra cadena a buscar y la aserción se apaga sin avisar. Todo va con
 * `expect(bool)->toBeTrue('mensaje')`.
 */

/** El CSS del `<style>` de `segmented`, sin comentarios. */
function cssSegmented(): string
{
    $blade = file_get_contents(__DIR__.'/../resources/views/components/segmented.blade.php');

    preg_match_all('#<style>(.*?)</style>#s', $blade, $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1]));
}

/**
 * Parte el CSS en lo que vive fuera de todo `@supports` y los bloques que
 * viven dentro, contando llaves (una regexp no sabe anidar).
 *
 * @return array{fuera: string, dentro: array<int, array{condicion: string, css: string}>}
 */
function partirPorSupports(string $css): array
{
    $fuera = '';
    $dentro = [];
    $i = 0;
    $largo = strlen($css);

    while ($i < $largo) {
        $inicio = strpos($css, '@supports', $i);

        if ($inicio === false) {
            $fuera .= substr($css, $i);
            break;
        }

        $fuera .= substr($css, $i, $inicio - $i);

        $apertura = strpos($css, '{', $inicio);

        if ($apertura === false) {
            break;
        }

        $condicion = trim(substr($css, $inicio + strlen('@supports'), $apertura - $inicio - strlen('@supports')));

        $nivel = 0;
        $j = $apertura;

        for (; $j < $largo; $j++) {
            if ($css[$j] === '{') {
                $nivel++;
            } elseif ($css[$j] === '}') {
                $nivel--;

                if ($nivel === 0) {
                    break;
                }
            }
        }

        $dentro[] = [
            'condicion' => $condicion,
            'css' => substr($css, $apertura + 1, $j - $apertura - 1),
        ];

        $i = $j + 1;
    }

    return ['fuera' => $fuera, 'dentro' => $dentro];
}

/**
 * Las reglas `selector { cuerpo }` de un CSS ya plano.
 *
 * @return array<int, array{selector: string, cuerpo: string}>
 */
function reglasCss(string $css): array
{
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);

    return array_map(fn (array $r) => [
        'selector' => trim((string) preg_replace('/\s+/', ' ', $r[1])),
        'cuerpo' => $r[2],
    ], $m);
}

/** ¿El cuerpo declara un `outline` de verdad (ni `none` ni `0`)? */
function declaraOutlineVisible(string $cuerpo): bool
{
    return (bool) preg_match('/(^|[;{\s])outline\s*:\s*(?!none|0\b)[^;]+/i', $cuerpo);
}

it('el anillo de foco tiene un respaldo que no depende de :has()', function () {
    $partes = partirPorSupports(cssSegmented());

    $respaldos = array_values(array_filter(
        reglasCss($partes['fuera']),
        fn (array $regla) => str_contains($regla['selector'], 'input:focus-visible')
            && ! str_contains($regla['selector'], ':has(')
            && declaraOutlineVisible($regla['cuerpo'])
    ));

    expect($respaldos)->not->toBeEmpty(
        'Fuera de todo @supports no hay una sola regla de foco colgada de `input:focus-visible` '.
        'con outline propio. Donde :has() no resuelva, `segmented` —documentado como «radios '.
        'reales sin JS»— se queda sin indicador de foco: WCAG 2.2 AA 2.4.7. Reglas de foco '.
        'encontradas fuera de @supports: '.
        implode(' | ', array_map(fn (array $r) => $r['selector'], array_filter(
            reglasCss($partes['fuera']),
            fn (array $r) => str_contains($r['selector'], ':focus')
        )))
    );

    foreach ($respaldos as $respaldo) {
        expect(str_contains($respaldo['cuerpo'], 'var(--muni-focus, var(--muni-accent, #767676))'))->toBeTrue(
            "«{$respaldo['selector']}» pinta el outline sin la cadena de respaldo del paquete: ".
            'con el CSS publicado desactualizado la declaración queda inválida y el anillo desaparece'
        );
    }
});

it('la regla con :has() vive dentro de un @supports y no fuera', function () {
    $partes = partirPorSupports(cssSegmented());

    expect(str_contains($partes['fuera'], ':has('))->toBeFalse(
        'Hay `:has()` fuera de un @supports. CLAUDE.md exige que el CSS moderno vaya como '.
        'mejora progresiva dentro de @supports, para que el navegador que no lo entiende se '.
        'quede con el respaldo en vez de con nada.'
    );

    $conHas = array_values(array_filter(
        $partes['dentro'],
        fn (array $bloque) => str_contains($bloque['css'], ':has(')
    ));

    expect($conHas)->not->toBeEmpty(
        'Ningún bloque @supports contiene la mejora con `:has()`: el anillo nunca se traslada '.
        'a la píldora visible.'
    );

    foreach ($conHas as $bloque) {
        expect((bool) preg_match('/selector\(\s*:has\(/i', $bloque['condicion']))->toBeTrue(
            "El @supports que envuelve `:has()` pregunta «{$bloque['condicion']}», que no es una ".
            'consulta de selector: `@supports selector(:has(*))` es la única forma de preguntar '.
            'por el soporte de un selector.'
        );
    }
});

it('el respaldo se pinta sobre un elemento visible, no sobre el radio 0×0', function () {
    $html = Blade::render(
        '<x-muni::segmented name="estado" :options="[\'todos\' => \'Todos\', \'cerrados\' => \'Cerrados\']" value="todos" label="Filtrar por estado" />'
    );

    $partes = partirPorSupports(cssSegmented());

    $objetivos = [];

    foreach (reglasCss($partes['fuera']) as $regla) {
        if (! str_contains($regla['selector'], 'input:focus-visible') || ! declaraOutlineVisible($regla['cuerpo'])) {
            continue;
        }

        // La última clase del selector es lo que se pinta.
        if (preg_match('/\.([a-z0-9_-]+)\s*$/i', $regla['selector'], $m)) {
            $objetivos[] = $m[1];
        }
    }

    expect($objetivos)->not->toBeEmpty(
        'El respaldo no apunta a ninguna clase: si el outline cae sobre el propio radio '.
        '(position:absolute;opacity:0;width:0;height:0) no se ve absolutamente nada.'
    );

    foreach ($objetivos as $clase) {
        expect(str_contains($html, 'class="'.$clase.'"'))->toBeTrue(
            "El respaldo pinta «.{$clase}», que el componente no renderiza: el anillo no existe en el DOM."
        );
    }
});

it('el grupo de radios se anuncia como radiogroup con nombre accesible', function () {
    $html = Blade::render(
        '<x-muni::segmented name="estado" :options="[\'todos\' => \'Todos\', \'cerrados\' => \'Cerrados\']" value="todos" label="Filtrar por estado" />'
    );

    expect(str_contains($html, 'role="radiogroup"'))->toBeTrue(
        'El contenedor de radios excluyentes sigue siendo un `role="group"` genérico: '.
        'el lector no anuncia «grupo de botones de opción» ni cuántas opciones hay.'
    );

    expect(str_contains($html, 'aria-label="Filtrar por estado"'))->toBeTrue(
        'La etiqueta pasada por el consumidor no llega al grupo: queda sin nombre accesible '.
        '(WCAG 2.2 AA 1.3.1 y 4.1.2).'
    );

    // Sin etiqueta explícita el grupo tampoco puede quedar mudo.
    $mudo = Blade::render(
        '<x-muni::segmented name="estado" :options="[\'todos\' => \'Todos\']" value="todos" />'
    );

    expect((bool) preg_match('/aria-label(?:ledby)?="[^"]+"/', $mudo))->toBeTrue(
        'Sin prop `label` el grupo se renderiza sin nombre accesible alguno.'
    );

    // El aria-label del consumidor manda y no se duplica: en HTML gana el
    // primero, así que dos atributos iguales dejan el resultado al azar del orden.
    $propio = Blade::render(
        '<x-muni::segmented name="estado" :options="[\'todos\' => \'Todos\']" value="todos" aria-label="Estado del trámite" />'
    );

    expect(substr_count($propio, 'aria-label='))->toBe(1,
        'El componente emite un aria-label además del que pasó el consumidor: atributo duplicado.'
    );
    expect(str_contains($propio, 'aria-label="Estado del trámite"'))->toBeTrue(
        'El aria-label del consumidor no sobrevive al del componente.'
    );
});

it('nunca se queda con la etiqueta del consumidor sin devolverla', function () {
    /*
     * El componente quita el `aria-label` del bag para no emitirlo dos veces,
     * así que solo puede quitarlo cuando pone el suyo. Si no —la rama de slot,
     * o un grupo etiquetado con aria-labelledby— se lo estaría comiendo, y el
     * control terminaría con MENOS nombre accesible que antes del arreglo.
     */
    $slot = Blade::render(
        '<x-muni::segmented aria-label="Vista del listado"><a href="#">Tarjetas</a></x-muni::segmented>'
    );

    expect(str_contains($slot, 'aria-label="Vista del listado"'))->toBeTrue(
        'La rama de slot se comió el aria-label del consumidor.'
    );

    $ambas = Blade::render(
        '<x-muni::segmented name="estado" :options="[\'todos\' => \'Todos\']" value="todos" aria-labelledby="rotulo" aria-label="Estado" />'
    );

    expect(str_contains($ambas, 'aria-label="Estado"'))->toBeTrue(
        'Con aria-labelledby presente el componente descarta el aria-label del consumidor en vez de dejarlo pasar.'
    );
});
