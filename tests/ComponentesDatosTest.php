<?php

/*
 * Regresiones del lado servidor de los componentes: datos incompletos o raros
 * que antes tronaban al renderizar, estados que se salían de rango y valores
 * que llegaban sin escapar a una expresión de Alpine.
 */

use Illuminate\Support\Facades\Blade;

it('dibuja la sparkline de stat con una serie de claves de texto', function () {
    $html = Blade::render('<x-muni::stat value="10" label="Ventas" :spark="[\'ene\' => 1, \'feb\' => 3, \'mar\' => 2]" />');

    expect($html)->toContain('<path d="M0,26 L50,2 L100,14');
});

it('no dibuja sparkline con un solo punto', function () {
    expect(Blade::render('<x-muni::stat value="1" label="x" :spark="[\'ene\' => 5]" />'))->not->toContain('<svg');
});

it('conserva el class del usuario en card y stat', function () {
    expect(Blade::render('<x-muni::card class="extra">x</x-muni::card>'))->toContain('class="muni-card extra"')
        ->and(Blade::render('<x-muni::stat value="1" label="x" class="extra" />'))->toContain('class="muni-stat extra"');
});

it('chart-bar tolera barras sin etiqueta, negativas o todas en cero', function () {
    $html = Blade::render('<x-muni::chart-bar :data="[[\'value\' => 4], [\'label\' => \'B\', \'value\' => -3], [\'label\' => \'C\']]" style="margin:0" />');

    expect($html)->toContain('height:100%;')
        ->and($html)->toContain('height:0%;')
        ->and($html)->not->toContain('height:-')
        ->and($html)->toContain('title=": 4"')
        ->and($html)->toContain('class="muni-chartbar" style="display:flex;flex-direction:column; margin:0;"');

    expect(Blade::render('<x-muni::chart-bar :data="[0, 0]" />'))->toContain('height:0%;');
});

it('chart-donut no pasa de una vuelta ni se sale del viewBox', function () {
    $html = Blade::render('<x-muni::chart-donut :total="10" :segments="[[\'label\' => \'A\', \'value\' => 8], [\'label\' => \'B\', \'value\' => 8], [\'value\' => -5], [\'label\' => \'D\']]" />');

    // r = 50 - 18/2 = 41 → circunferencia 257.61
    expect($html)->toContain('r="41"')
        ->and($html)->toContain('stroke-dasharray="206.09 51.52"')
        ->and($html)->toContain('stroke-dasharray="51.52 206.09"')
        ->and($html)->not->toContain('stroke-dasharray="-');
});

it('ring y progress acotan el valor a [0, max]', function () {
    expect(Blade::render('<x-muni::progress :value="150" />'))->toContain('aria-valuenow="100"')
        ->and(Blade::render('<x-muni::progress :value="-5" />'))->toContain('aria-valuenow="0"')
        ->and(Blade::render('<x-muni::progress :value="5" :max="0" />'))->toContain('aria-valuenow="0"')
        ->and(Blade::render('<x-muni::ring :value="300" :max="100" />'))->toContain('>100<');
});

it('pagination acota la página actual y no deja enlaces muertos enfocables', function () {
    $html = Blade::render('<x-muni::pagination :current="9" :total="3" :url="fn ($p) => \'/p/\'.$p" />');

    expect($html)->toContain('aria-current="page">3<')
        ->and($html)->toContain('href="/p/2"')
        ->and($html)->toMatch('/<span aria-disabled="true"[^>]*>Siguiente/')
        ->and($html)->not->toContain('href="#"');

    expect(Blade::render('<x-muni::pagination :current="0" :total="0" />'))
        ->toContain('aria-current="page">1<')
        ->toMatch('/<span aria-disabled="true"[^>]*>‹ Anterior/');
});

it('filter-bar emite el token CSRF cuando no es GET', function () {
    expect(Blade::render('<x-muni::filter-bar method="post">x</x-muni::filter-bar>'))->toContain('name="_token"')
        ->and(Blade::render('<x-muni::filter-bar>x</x-muni::filter-bar>'))->not->toContain('_token');

    $put = Blade::render('<x-muni::filter-bar method="put">x</x-muni::filter-bar>');
    expect($put)->toContain('method="post"')->and($put)->toContain('name="_method" value="PUT"');
});

it('accordion funciona con claves de texto y abre el default también en multiple', function () {
    $html = Blade::render('<x-muni::accordion default="b" :items="[\'a\' => [\'title\' => \'A\', \'content\' => \'uno\'], \'b\' => [\'title\' => \'B\', \'content\' => \'dos\']]" />');

    expect($html)->toContain('toggle(0)')->and($html)->toContain('toggle(1)')
        ->and($html)->not->toContain('toggle(a)')
        ->and($html)->toContain('open: 1');

    $multi = Blade::render('<x-muni::accordion multiple :default="1" :items="[[\'title\' => \'A\'], [\'title\' => \'B\']]" />');
    expect($multi)->toContain('opened: JSON.parse(\'[1]\')');
});

it('accordion deja visible sin JS solo el panel abierto', function () {
    $html = Blade::render('<x-muni::accordion :default="0" :items="[[\'title\' => \'A\', \'content\' => \'uno\'], [\'title\' => \'B\', \'content\' => \'dos\']]" />');

    expect(substr_count($html, 'x-cloak'))->toBe(1)
        ->and($html)->toContain('aria-expanded="true"');
});

it('accordion-item se usa en el slot del accordion', function () {
    $html = Blade::render('<x-muni::accordion><x-muni::accordion-item title="Uno" open>primero</x-muni::accordion-item><x-muni::accordion-item title="Dos">segundo</x-muni::accordion-item></x-muni::accordion>');

    expect($html)->toContain('k = _n++')
        ->and($html)->toContain('primero')
        ->and(substr_count($html, 'x-cloak'))->toBe(1);
});

it('input, select y switch respetan un id explícito', function () {
    $input = Blade::render('<x-muni::input name="rut" id="rut-2" label="RUT" />');
    expect($input)->toContain('for="rut-2"')->and($input)->toContain('id="rut-2"')->and($input)->not->toContain('muni-rut')
        ->and(substr_count($input, 'id="rut-2"'))->toBe(1);

    expect(Blade::render('<x-muni::input name="rut" label="RUT" />'))->toContain('id="muni-rut"');

    $select = Blade::render('<x-muni::select name="c" id="c-2" label="C" :options="[1 => \'uno\']" />');
    expect($select)->toContain('for="c-2"')->and(substr_count($select, 'id="c-2"'))->toBe(1);

    $switch = Blade::render('<x-muni::switch name="s" id="s-2" label="S" />');
    expect($switch)->toContain('for="s-2"')->and(substr_count($switch, 'id="s-2"'))->toBe(1);
});

it('escapa hacia Alpine los valores con apóstrofo', function () {
    $dz = Blade::render('<x-muni::file-dropzone label="Sube l\'archivo" />');
    expect($dz)->toContain('Sube l&#039;archivo')->and($dz)->not->toContain("'Sube l'archivo'");

    $cal = Blade::render('<x-muni::calendar min="2026-09-10" />');
    expect($cal)->toContain("('2026-09-10')")->and($cal)->not->toContain("new Date('2026");

    expect(Blade::render('<x-muni::calendar min="x\'); alert(1); (\'" />'))->toContain('(null)');
});

it('rating pinta las estrellas desde el servidor y en solo lectura no es interactivo', function () {
    $ro = Blade::render('<x-muni::rating :value="3" readonly />');
    expect($ro)->toContain('role="img"')->and($ro)->toContain('aria-label="Calificación: 3 de 5"')
        ->and(substr_count($ro, 'muni-star--ro muni-star--on'))->toBe(3)
        ->and($ro)->not->toContain('<button');

    $rw = Blade::render('<x-muni::rating :value="4" name="nota" />');
    expect(substr_count($rw, 'role="radio"'))->toBe(5)
        ->and(substr_count($rw, 'aria-checked="true"'))->toBe(1)
        ->and($rw)->toContain('value="4"');
});

it('segmented no se auto-envía si se pide y usa el id como prefijo', function () {
    $html = Blade::render('<x-muni::segmented name="p" id="p2" value="a" :autosubmit="false" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');

    expect($html)->toContain('id="p2-0"')->and($html)->toContain('for="p2-1"')->and($html)->not->toContain('onchange');
    expect(Blade::render('<x-muni::segmented name="p" :options="[\'a\' => \'A\']" />'))->toContain('wire:submit')->toContain('id="p-0"');
});

it('data-table marca el tbody con muni-data-body', function () {
    expect(Blade::render('<x-muni::data-table :columns="[\'A\']" />'))->toContain('<tbody class="muni-data-body">');
});

it('accordion abre por clave aunque las claves de items no sean correlativas', function () {
    // Lo típico tras ->filter()->all(): claves 1 y 3. default=3 es la clave, no la posición.
    $html = Blade::render('<x-muni::accordion :default="3" :items="[1 => [\'title\' => \'A\', \'content\' => \'a\'], 3 => [\'title\' => \'B\', \'content\' => \'b\']]" />');

    expect($html)->toContain('open: 1,');
});

it('la red de reduced-motion solo toca clases muni-, no utilidades del host como text-muni-accent', function () {
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui.css');

    expect($css)->not->toContain('[class*="muni-"]')
        ->and($css)->toContain('[class^="muni-"], [class*=" muni-"]');
});
