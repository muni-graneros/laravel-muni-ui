<?php

/*
 * Controles de formulario: el error y la ayuda tienen que llegar al lector de
 * pantalla al entrar al campo (aria-describedby apuntando a un id que existe), y
 * los valores del servidor tienen que verse sin JS.
 */

use Illuminate\Support\Facades\Blade;

it('enlaza la ayuda o el error con aria-describedby en input, select y textarea', function (string $tag) {
    $html = Blade::render($tag);

    preg_match('/aria-describedby="([^"]+)"/', $html, $m);
    expect($m)->not->toBeEmpty()
        ->and($html)->toContain('id="'.$m[1].'"');
})->with([
    ['<x-muni::input name="run" hint="Con guion" />'],
    ['<x-muni::select name="sector" error="Obligatorio" :options="[\'a\' => \'A\']" />'],
    ['<x-muni::textarea name="obs" error="Obligatorio" />'],
]);

it('conserva el aria-describedby que trae el host y le suma la ayuda', function () {
    expect(Blade::render('<x-muni::input name="run" hint="Con guion" aria-describedby="extra" />'))
        ->toContain('aria-describedby="extra muni-run-ayuda"');
});

it('marca aria-invalid en select con error', function () {
    expect(Blade::render('<x-muni::select name="s" error="x" :options="[]" />'))->toContain('aria-invalid="true"');
});

it('radio-group marca la opción de value y usa fieldset con legend', function () {
    $html = Blade::render('<x-muni::radio-group label="Entrega" name="e" value="b" :options="[\'a\' => \'A\', \'b\' => [\'label\' => \'B\', \'description\' => \'Detalle\']]" />');

    expect($html)->toContain('<legend')
        ->and($html)->toMatch('/value="b"\s+name="e"\s+checked/')
        ->and($html)->toContain('Detalle');
});

it('checkbox usa el slot como etiqueta y da ids distintos por valor', function () {
    $html = Blade::render('<x-muni::checkbox name="dias[]" value="lun">Lunes</x-muni::checkbox><x-muni::checkbox name="dias[]" value="mar">Martes</x-muni::checkbox>');

    preg_match_all('/<input type="checkbox" id="([^"]+)"/', $html, $m);
    expect($html)->toContain('Lunes')
        ->and(count(array_unique($m[1])))->toBe(2);
});

it('description-list arma pares dt/dd y muestra un guion en valores vacíos', function () {
    $html = Blade::render('<x-muni::description-list :items="[\'RUT\' => [\'value\' => \'1-9\', \'mono\' => true], \'Teléfono\' => null]" />');

    expect($html)->toContain('<dt>RUT</dt>')
        ->and($html)->toContain('class="muni-num"')
        ->and($html)->toContain('—');
});

it('spinner se anuncia como status y deja wire:loading en su raíz', function () {
    $html = Blade::render('<x-muni::spinner wire:loading wire:target="guardar" label="Guardando" />');

    expect($html)->toMatch('/<span role="status"[^>]*wire:loading/')
        ->and($html)->toContain('Guardando');
});
