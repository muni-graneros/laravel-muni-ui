<?php

use Illuminate\Support\Facades\Blade;

/*
 * EL PATRÓN DE PESTAÑAS (WAI-ARIA APG, «tabs»).
 *
 * `<x-muni::tabs>` traía lo caro y bien hecho —roving tabindex, <button
 * type="button"> reales, role=tablist/tab y flechas ←/→— pero le faltaba el
 * cableado, y con un defecto de fondo medido en docs/INVENTORY.md:1472:
 *
 *   las flechas cambiaban `active` pero NUNCA movían el foco del DOM. Tras
 *   pulsar →, la pestaña seleccionada se pintaba y su tabindex pasaba a 0,
 *   pero el foco físico seguía en el botón anterior, que en ese momento tenía
 *   aria-selected="false" y tabindex="-1": el anillo de foco quedaba sobre una
 *   pestaña NO seleccionada, el lector seguía anunciando la vieja y el
 *   siguiente Tab salía desde la posición equivocada.
 *
 * Junto con eso faltaban Home/End, la relación programática pestaña↔panel
 * (aria-controls / id / aria-labelledby), el tabindex=0 del panel sin
 * controles propios y los valores ESTÁTICOS de aria-selected/tabindex en el
 * HTML del servidor: hasta que Alpine hidrataba, las N pestañas eran todas
 * tabulables y ninguna aparecía seleccionada.
 *
 * Igual que en FocoVisibleTest, todo va con `expect(bool)->toBeTrue('mensaje')`:
 * el segundo argumento de `toContain()` en Pest es otra cadena a buscar, no un
 * mensaje, y la aserción se desactivaría sin avisar.
 */

/** Una ficha de licencia clase B: cuatro pestañas, paneles en el mismo orden. */
function fichaPestanas(string $extra = ''): string
{
    return Blade::render(
        '<x-muni::tabs :tabs="[\'Datos\', \'Exámenes\', \'Documentos\']" '.$extra.'>'
        .'<x-muni::tab-panel :index="0">Antecedentes del solicitante</x-muni::tab-panel>'
        .'<x-muni::tab-panel :index="1">Resultado de los exámenes</x-muni::tab-panel>'
        .'<x-muni::tab-panel :index="2"><a href="#doc">Ver certificado</a></x-muni::tab-panel>'
        .'</x-muni::tabs>'
    );
}

/** Las etiquetas de apertura `<button ... role="tab" ...>`, en orden. */
function etiquetasPestana(string $html): array
{
    preg_match_all('/<button\b[^>]*\brole="tab"[^>]*>/i', $html, $m);

    return $m[0];
}

/** Las etiquetas de apertura `<div ... role="tabpanel" ...>`, en orden. */
function etiquetasPanel(string $html): array
{
    preg_match_all('/<div\b[^>]*\brole="tabpanel"[^>]*>/i', $html, $m);

    return $m[0];
}

/** El valor de un atributo dentro de una etiqueta de apertura, o null. */
function atributo(string $etiqueta, string $nombre): ?string
{
    return preg_match('/\s'.preg_quote($nombre, '/').'="([^"]*)"/i', $etiqueta, $m) ? $m[1] : null;
}

/**
 * Todo el código Alpine del componente: el `x-data` de la raíz más los
 * manejadores de teclado del tablist. Es donde tiene que estar el `.focus()`.
 */
function codigoAlpinePestanas(string $html): string
{
    preg_match_all('/(?:x-data|(?:@|x-on:)keydown[^=]*)="([^"]*)"/i', $html, $m);

    return implode("\n", $m[1]);
}

it('cada pestaña declara aria-controls apuntando al id real de su panel', function () {
    $html = fichaPestanas();

    $pestanas = etiquetasPestana($html);
    $paneles = etiquetasPanel($html);

    expect(count($pestanas))->toBe(3);
    expect(count($paneles))->toBe(3);

    $idsPanel = array_map(fn (string $p) => atributo($p, 'id'), $paneles);

    foreach ($pestanas as $i => $pestana) {
        $controla = atributo($pestana, 'aria-controls');

        expect($controla !== null && $controla !== '')->toBeTrue(
            "La pestaña #$i no declara aria-controls: no hay relación programática con su panel."
        );
        expect(in_array($controla, $idsPanel, true))->toBeTrue(
            "El aria-controls de la pestaña #$i ($controla) no coincide con el id de ningún panel renderizado."
        );
        expect($controla)->toBe(
            $idsPanel[$i],
            "La pestaña #$i controla un panel que no es el suyo (posición $i)."
        );
    }
});

it('cada panel declara aria-labelledby apuntando al id de su pestaña', function () {
    $html = fichaPestanas();

    $pestanas = etiquetasPestana($html);
    $paneles = etiquetasPanel($html);

    $idsPestana = array_map(fn (string $p) => atributo($p, 'id'), $pestanas);

    foreach ($paneles as $i => $panel) {
        $etiquetadoPor = atributo($panel, 'aria-labelledby');

        expect($etiquetadoPor !== null && $etiquetadoPor !== '')->toBeTrue(
            "El panel #$i no declara aria-labelledby: el lector no anuncia a qué pestaña pertenece."
        );
        expect($etiquetadoPor)->toBe(
            $idsPestana[$i],
            "El aria-labelledby del panel #$i no coincide con el id de su pestaña."
        );
    }
});

it('el HTML del servidor ya trae aria-selected y tabindex correctos antes de Alpine', function () {
    $html = fichaPestanas('default="1"');

    $pestanas = etiquetasPestana($html);

    foreach ($pestanas as $i => $pestana) {
        $seleccionada = $i === 1;

        expect(atributo($pestana, 'aria-selected'))->toBe(
            $seleccionada ? 'true' : 'false',
            "La pestaña #$i no trae aria-selected estático: hasta que Alpine hidrate ninguna aparece seleccionada."
        );
        expect(atributo($pestana, 'tabindex'))->toBe(
            $seleccionada ? '0' : '-1',
            "La pestaña #$i no trae tabindex estático: hasta que Alpine hidrate las N pestañas son todas tabulables."
        );
    }
});

it('el manejador de flechas mueve el foco del DOM, no solo la selección', function () {
    $html = fichaPestanas();

    $pestanas = etiquetasPestana($html);

    foreach ($pestanas as $i => $pestana) {
        $ref = atributo($pestana, 'x-ref');

        expect($ref !== null && $ref !== '')->toBeTrue(
            "La pestaña #$i no tiene x-ref: sin referencia no hay a qué botón mover el foco."
        );
    }

    $codigo = codigoAlpinePestanas($html);

    expect(str_contains($codigo, '$refs'))->toBeTrue(
        'El código de las pestañas no usa $refs: las flechas cambian `active` pero nunca mueven el foco.'
    );
    expect(str_contains($codigo, '.focus()'))->toBeTrue(
        'El código de las pestañas nunca llama a .focus(): tras pulsar → el anillo de foco '.
        'se queda sobre una pestaña con aria-selected="false" y tabindex="-1".'
    );

    expect((bool) preg_match('/(?:@|x-on:)keydown\.right/i', $html))->toBeTrue(
        'Falta el manejador de ArrowRight.'
    );
    expect((bool) preg_match('/(?:@|x-on:)keydown\.left/i', $html))->toBeTrue(
        'Falta el manejador de ArrowLeft.'
    );
});

it('existen Home y End para ir a la primera y a la última pestaña', function () {
    $html = fichaPestanas();

    expect((bool) preg_match('/(?:@|x-on:)keydown\.home\b/i', $html))->toBeTrue(
        'Falta la tecla Home: el APG la exige para saltar a la primera pestaña.'
    );
    expect((bool) preg_match('/(?:@|x-on:)keydown\.end\b/i', $html))->toBeTrue(
        'Falta la tecla End: el APG la exige para saltar a la última pestaña.'
    );
});

it('el panel sin controles propios es enfocable y el que ya los tiene no roba un tabulador', function () {
    $html = fichaPestanas();

    $paneles = etiquetasPanel($html);

    expect(atributo($paneles[0], 'tabindex'))->toBe(
        '0',
        'El panel de solo texto no es enfocable: tras la pestaña el Tab se salta el contenido.'
    );
    expect(atributo($paneles[2], 'tabindex'))->toBeNull(
        'El panel que ya tiene un enlace propio no debe añadir una parada de tabulación extra.'
    );
});

it('los ids son estables entre renders: nada de uniqid bajo Livewire 3', function () {
    $ids = fn (string $html) => array_map(fn (string $p) => atributo($p, 'id'), etiquetasPanel($html));

    $primero = $ids(fichaPestanas());
    $segundo = $ids(fichaPestanas());

    expect($primero)->toHaveCount(3);
    expect(array_filter($primero, fn (?string $id) => $id === null || $id === ''))->toBe(
        [],
        'Hay paneles sin id: no pueden ser destino de aria-controls.'
    );
    expect($primero)->toBe(
        $segundo,
        'Los ids del panel cambian entre renders: con uniqid() Livewire 3 rompe la relación aria-controls al refrescar.'
    );
});

it('el tablist acepta un nombre accesible para distinguir dos grupos en la misma ficha', function () {
    $html = fichaPestanas('label="Antecedentes de la solicitud"');

    expect((bool) preg_match('/<div\b[^>]*\brole="tablist"[^>]*\baria-label="Antecedentes de la solicitud"/i', $html))
        ->toBeTrue('El tablist no expone la prop `label` como aria-label.');
});
