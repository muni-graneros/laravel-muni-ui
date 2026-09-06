<?php

use Illuminate\Support\Facades\Blade;

/**
 * EL CANDADO DE LA TABLA ORDENABLE.
 *
 * `<x-muni::sortable-table>` disparaba el orden con `@click` sobre el `<th>`.
 * Un `<th>` no es enfocable: sin `tabindex`, sin `role="button"` y sin
 * `@keydown`, ordenar por columna era INALCANZABLE con el teclado (WCAG 2.2 AA
 * 2.1.1, bloqueante). Además ningún `<th>` declaraba `aria-sort`, así que el
 * estado de orden no existía para un lector de pantalla —el único indicador era
 * la flecha ↑/↓/↕ por opacidad, o sea color y forma, nada anunciado (1.4.1)— y
 * el buscador solo tenía `placeholder`, que no es nombre accesible (3.3.2 y
 * 4.1.2). Es un listado de patentes morosas en Rentas: quien no usa mouse no
 * podía ordenarlo por monto.
 *
 * Se comprueba sobre el HTML servido, no sobre el DOM ya hidratado: el `<th>`
 * vive dentro de un `<template x-for>`, así que lo que se verifica son los
 * BINDINGS de Alpine que producirán esos atributos.
 *
 * `toContain('valor', 'mensaje')` NO sirve acá: en Pest el segundo argumento es
 * otra cadena a buscar, no un mensaje. Por eso todo va con
 * `expect(bool)->toBeTrue('mensaje')`.
 */

/** El HTML servido de la tabla, con columnas y filas de verdad. */
function tablaOrdenableHtml(bool $searchable = true): string
{
    return Blade::render(
        '<x-muni::sortable-table :columns="$columns" :rows="$rows" :searchable="$searchable" />',
        [
            'searchable' => $searchable,
            'columns' => [
                ['key' => 'rol', 'label' => 'Rol'],
                ['key' => 'monto', 'label' => 'Monto', 'align' => 'right', 'mono' => true],
                ['key' => 'acciones', 'label' => 'Acciones', 'sortable' => false],
            ],
            'rows' => [
                ['rol' => '4501-2', 'monto' => '1.240.000', 'acciones' => 'Ver', '_tone' => 'danger'],
                ['rol' => '4502-9', 'monto' => '80.000', 'acciones' => 'Ver'],
            ],
        ],
    );
}

/** El contenido del `<thead>` de la tabla. */
function tablaOrdenableThead(): string
{
    preg_match('#<thead>(.*?)</thead>#s', tablaOrdenableHtml(), $m);

    return $m[1] ?? '';
}

it('cada cabecera ordenable dispara el orden desde un <button type="button"> real', function () {
    $thead = tablaOrdenableThead();

    expect($thead !== '')->toBeTrue('No se encontró el <thead> de la tabla: el barrido está roto.');

    expect((bool) preg_match('/<button\b[^>]*\btype="button"/', $thead))->toBeTrue(
        'La cabecera no contiene ningún <button type="button">: el orden se dispara sobre el <th>, '.
        'que no es enfocable, y con el teclado no hay forma de ordenar (WCAG 2.2 AA 2.1.1).'
    );

    // El disparador tiene que ser el botón, no el <th>: si el @click sigue en el
    // <th> el ratón funciona y el teclado no, y la prueba de arriba pasaría igual.
    preg_match_all('/<th\b[^>]*>/', $thead, $ths);

    $thConClick = array_values(array_filter(
        $ths[0] ?? [],
        fn (string $th) => (bool) preg_match('/(@click|x-on:click)/', $th),
    ));

    expect($thConClick)->toBe(
        [],
        'El <th> sigue llevando el manejador de clic. El orden debe dispararse desde el <button> '.
        'de adentro, que hereda foco, Enter y Espacio del navegador: '.implode(' | ', $thConClick)
    );
});

it('el <th> declara aria-sort con ascending, descending y none', function () {
    $thead = tablaOrdenableThead();

    preg_match_all('/<th\b[^>]*>/', $thead, $ths);

    $conAriaSort = array_values(array_filter(
        $ths[0] ?? [],
        fn (string $th) => (bool) preg_match('/(:aria-sort|x-bind:aria-sort|\baria-sort)=/', $th),
    ));

    expect($conAriaSort)->not->toBeEmpty(
        'Ningún <th> declara aria-sort: el estado de orden no existe para un lector de pantalla, '.
        'que solo tiene la flecha ↑/↓/↕ dibujada por opacidad.'
    );

    $binding = $conAriaSort[0];

    foreach (['ascending', 'descending', 'none'] as $estado) {
        expect(str_contains($binding, $estado))->toBeTrue(
            "El aria-sort del <th> nunca vale «{$estado}»: los tres valores son obligatorios para ".
            'que el lector distinga la columna ordenada de las que no lo están.'
        );
    }
});

it('las cabeceras declaran scope="col"', function () {
    $thead = tablaOrdenableThead();

    preg_match_all('/<th\b[^>]*>/', $thead, $ths);

    expect($ths[0] ?? [])->not->toBeEmpty('No se encontró ningún <th>: el barrido está roto.');

    $sinScope = array_values(array_filter(
        $ths[0],
        fn (string $th) => ! preg_match('/\bscope="col"/', $th),
    ));

    expect($sinScope)->toBe(
        [],
        'Estas cabeceras no declaran scope="col", así que el lector de pantalla no puede asociar '.
        'cada celda con su columna: '.implode(' | ', $sinScope)
    );
});

it('el buscador tiene nombre accesible, no solo placeholder', function () {
    $html = tablaOrdenableHtml();

    preg_match_all('/<input\b[^>]*>/', $html, $inputs);

    $buscador = array_values(array_filter(
        $inputs[0] ?? [],
        fn (string $input) => str_contains($input, 'x-model="q"'),
    ));

    expect($buscador)->not->toBeEmpty('No se encontró el input de búsqueda de la tabla.');

    $input = $buscador[0];

    $tieneAriaLabel = (bool) preg_match('/\baria-label(?:ledby)?="[^"]+"/', $input);

    $tieneLabelPropio = false;

    if (preg_match('/\bid="([^"]+)"/', $input, $m)) {
        $tieneLabelPropio = (bool) preg_match(
            '/<label\b[^>]*\bfor="'.preg_quote($m[1], '/').'"[^>]*>\s*\S/',
            $html,
        );
    }

    expect($tieneAriaLabel || $tieneLabelPropio)->toBeTrue(
        'El buscador solo se anuncia por su placeholder, que desaparece al escribir y no es nombre '.
        'accesible: necesita un <label for> con texto o un aria-label (WCAG 2.2 AA 3.3.2 y 4.1.2). '.
        'Etiqueta encontrada: '.$input
    );

    // Si la etiqueta va oculta, que se oculte SIN sacarla del árbol de accesibilidad.
    if ($tieneLabelPropio) {
        $componente = file_get_contents(__DIR__.'/../resources/views/components/sortable-table.blade.php');

        preg_match('#<style>(.*?)</style>#s', $componente, $style);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $style[1] ?? '');

        if (preg_match('/\.muni-sr\s*(?:,[^{]*)?\{([^}]*)\}/', $css, $regla)) {
            expect((bool) preg_match('/(display\s*:\s*none|visibility\s*:\s*hidden)/i', $regla[1]))->toBeFalse(
                'La utilidad de texto oculto usa display:none o visibility:hidden, que sacan la '.
                'etiqueta del árbol de accesibilidad: el buscador se queda otra vez sin nombre.'
            );
        }
    }
});

it('el filtro no busca dentro de las claves internas que empiezan con _', function () {
    $componente = file_get_contents(__DIR__.'/../resources/views/components/sortable-table.blade.php');

    preg_match('/get view\(\)\s*\{(.*?)\n\s*\},/s', $componente, $m);
    $vista = $m[1] ?? $componente;

    expect((bool) preg_match('/Object\.values\(\s*row\s*\)/', $vista))->toBeFalse(
        'El filtro recorre Object.values(row), así que también busca dentro de «_tone», la clave '.
        'interna que pinta la franja roja: escribir «danger» en el buscador filtra filas por un '.
        'dato que el usuario no ve en ninguna columna.'
    );

    expect((bool) preg_match("/startsWith\(\s*'_'\s*\)|charAt\(0\)\s*(?:!==|===)\s*'_'|\\[0\\]\s*(?:!==|===)\s*'_'/", $vista))->toBeTrue(
        'El filtro no excluye explícitamente las claves que empiezan con «_»: cualquier metadato '.
        'que se agregue a la fila volverá a filtrarse como si fuera contenido visible.'
    );
});

/*
 * Añadida al integrar: el agente que reparó este componente resolvió el id del
 * buscador con uniqid(), que es justamente el antipatrón que DESIGN.md prohíbe y
 * que en la misma tanda se quitó de modal, drawer y tabs. Bajo Livewire el id
 * cambia en cada render y la relación <label for> ↔ <input id> se rompe después
 * del primer refresco, así que el campo se queda sin nombre accesible.
 */
it('el id del buscador es estable entre renders y respeta el del consumidor', function () {
    $columnas = [['key' => 'rut', 'label' => 'RUT'], ['key' => 'monto', 'label' => 'Monto']];

    $render = fn (string $extra = '') => Blade::render(
        '<x-muni::sortable-table :columns="$columns" :rows="[]" searchable '.$extra.' />',
        ['columns' => $columnas]
    );

    // Sin comentarios: nombrar uniqid() al explicar por qué no se usa no es un defecto.
    $fuente = (string) preg_replace(
        ['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'],
        '',
        file_get_contents(__DIR__.'/../resources/views/components/sortable-table.blade.php')
    );

    expect(str_contains($fuente, 'uniqid('))
        ->toBeFalse('El buscador arma su id con uniqid(): cambia en cada render y rompe el <label for> bajo Livewire.');

    preg_match('/<label for="([^"]+)"/', $render(), $a);
    preg_match('/<label for="([^"]+)"/', $render(), $b);

    expect($a[1] ?? 'a')->not->toBeEmpty()
        ->and($a[1] ?? 'a')->toBe($b[1] ?? 'b', 'Dos renders de la misma tabla dan ids distintos.');

    preg_match('/<label for="([^"]+)"/', $render('id="patentes-morosas"'), $c);
    expect($c[1] ?? '')->toStartWith('patentes-morosas', 'El id del consumidor no manda sobre el generado.');
});
