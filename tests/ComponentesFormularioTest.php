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

it('checkbox da ids distintos a valores que un slug confundiría', function () {
    $html = Blade::render('<x-muni::checkbox name="r[]" value="Sí" label="A" /><x-muni::checkbox name="r[]" value="si" label="B" /><x-muni::checkbox name="r[]" value="+" label="C" /><x-muni::checkbox name="r[]" value="-" label="D" />');

    preg_match_all('/<input type="checkbox" id="([^"]+)"/', $html, $m);
    expect(count(array_unique($m[1])))->toBe(4);
});

it('checkbox y radio-group suman el aria-describedby del host al suyo', function () {
    expect(Blade::render('<x-muni::checkbox name="a" label="A" description="Ayuda" aria-describedby="extra" />'))
        ->toMatch('/aria-describedby="extra muni-a-[0-9a-f]{8}-ayuda"/')
        ->and(Blade::render('<x-muni::radio-group name="t" hint="Ayuda" aria-describedby="extra" :options="[\'a\' => \'A\']" />'))
        ->toContain('aria-describedby="extra t-ayuda"');
});

it('spinner deja el display en la clase para que wire:loading lo oculte', function () {
    expect(Blade::render('<x-muni::spinner wire:loading />'))->not->toMatch('/<span role="status"[^>]*style="[^"]*display:/');
});

it('data-table muestra el vacío aunque Livewire deje comentarios de morph en el slot', function () {
    $html = Blade::render('<x-muni::data-table :columns="[\'A\']" empty="Nada por aquí"><!--[if BLOCK]><![endif]--><!--[if ENDBLOCK]><![endif]--></x-muni::data-table>');

    expect($html)->toContain('Nada por aquí');
});

it('command-palette abre solo una paleta por atajo aunque haya dos en la página', function () {
    expect(Blade::render('<x-muni::command-palette :items="[]" />'))->toContain('!$event.defaultPrevented');
});

it('kpi y stat con tone muted escriben la cifra en color de texto, no en gris de borde', function () {
    expect(Blade::render('<x-muni::kpi value="1" label="x" tone="muted" />'))->toContain('color:var(--muni-text)')
        ->and(Blade::render('<x-muni::kpi value="1" label="x" tone="muted" />'))->not->toContain('var(--muni-border-2)');
});
