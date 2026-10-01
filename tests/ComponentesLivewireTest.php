<?php

/*
 * Los controles de formulario tienen que aceptar wire:model, porque el ecosistema
 * es Livewire. Donde el valor vive en un <input> real (radios, archivo) el
 * atributo se reenvía a ese input; donde vive en estado de Alpine (calendario,
 * código, estrellas) el componente declara x-modelable. Sin esto, wire:model
 * caía en el <div> envolvente y no enlazaba nada, sin error visible.
 */

use Illuminate\Support\Facades\Blade;

it('segmented pone wire:model en cada radio y no en el contenedor', function () {
    $html = Blade::render('<x-muni::segmented name="vista" wire:model.live="vista" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');

    // Con enlace no hay autoenvío ni botón de respaldo: el modelo ya recibe cada cambio.
    expect(substr_count($html, 'wire:model.live="vista"'))->toBe(2)
        ->and($html)->not->toMatch('/<div role="radiogroup"[^>]*wire:model/')
        ->and($html)->not->toContain('requestSubmit')
        ->and($html)->not->toContain('muni-seg-aplicar"');
});

it('segmented sin enlace sigue auto-enviando el formulario', function () {
    expect(Blade::render('<x-muni::segmented name="vista" :options="[\'a\' => \'A\']" />'))->toContain('requestSubmit()');
});

it('file-dropzone pone wire:model en el input de archivo', function () {
    $html = Blade::render('<x-muni::file-dropzone name="cedula" wire:model="cedula" />');

    expect($html)->toMatch('/<input[^>]*type="file"[^>]*wire:model="cedula"/')
        ->and($html)->toContain("dispatchEvent(new Event('change'");
});

it('calendar, otp-input y rating se enlazan con x-modelable', function (string $tag, string $propiedad) {
    expect(Blade::render($tag))->toContain('x-modelable="'.$propiedad.'"');
})->with([
    ['<x-muni::calendar wire:model="fecha" />', 'valor'],
    ['<x-muni::otp-input wire:model="codigo" />', 'value'],
    ['<x-muni::rating wire:model="nota" />', 'value'],
]);

it('calendar parte con la fecha de value y descarta formatos inválidos', function () {
    expect(Blade::render('<x-muni::calendar name="f" value="2026-10-05" />'))->toContain('value="2026-10-05"')
        ->and(Blade::render('<x-muni::calendar name="f" value="05/10/2026" />'))->toContain('value=""');
});

/*
 * requestSubmit() en un POST es un guardado que nadie pidió, y en un formulario con
 * wire:submit dispara la acción de Livewire en cada clic: de fábrica solo se
 * autoenvían los GET sin manejador de submit propio.
 */
it('segmented solo autoenvía formularios GET, salvo autosubmit="siempre"', function () {
    $deFabrica = Blade::render('<x-muni::segmented name="e" :options="[\'a\' => \'A\']" />');

    expect($deFabrica)->toContain('siempre: false')
        ->and($deFabrica)->toContain("form.method === 'get'")
        ->and($deFabrica)->toContain('wire:submit')
        ->and(Blade::render('<x-muni::segmented name="e" autosubmit="siempre" :options="[\'a\' => \'A\']" />'))->toContain('siempre: true')
        ->and(Blade::render('<x-muni::segmented name="e" :autosubmit="false" :options="[\'a\' => \'A\']" />'))->not->toContain('requestSubmit');
});

it('textarea, checkbox y radio-group llevan wire:model al control real', function () {
    expect(Blade::render('<x-muni::textarea name="obs" wire:model.blur="obs" />'))->toMatch('/<textarea[^>]*wire:model\.blur="obs"/')
        ->and(Blade::render('<x-muni::checkbox name="acepta" wire:model="acepta" label="Acepto" />'))->toMatch('/<input\s+type="checkbox"[^>]*wire:model="acepta"/');

    $radios = Blade::render('<x-muni::radio-group name="tipo" wire:model="tipo" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');
    expect(substr_count($radios, 'wire:model="tipo"'))->toBe(2)
        ->and($radios)->not->toMatch('/<fieldset[^>]*wire:model/');
});

/*
 * El contador del textarea se movía solo con el evento `input`: cuando el servidor
 * reiniciaba el valor enlazado con wire:model, seguía contando lo de antes.
 */
it('textarea recuenta cuando el modelo cambia desde afuera', function () {
    expect(Blade::render('<x-muni::textarea name="obs" wire:model.live="obs" maxlength="50" />'))
        ->toContain('x-effect="if ($el._x_model)');
});

/*
 * switch llevaba su propio x-model sobre el mismo input que el wire:model del
 * anfitrión: el estado inicial de Alpine pisaba el del servidor y el clic no llegaba.
 */
it('switch deja el checkbox al wire:model del anfitrión, sin x-model propio', function () {
    $html = Blade::render('<x-muni::switch name="n" wire:model.live="n" label="N" />');

    expect($html)->toMatch('/<input type="checkbox"[^>]*wire:model\.live="n"/')
        ->and($html)->not->toContain('x-model=')
        ->and($html)->toContain('input:checked + .muni-switch');
});
