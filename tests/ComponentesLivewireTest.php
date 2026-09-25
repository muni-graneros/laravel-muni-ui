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

    expect(substr_count($html, 'wire:model.live="vista"'))->toBe(2)
        ->and($html)->not->toMatch('/<div role="group"[^>]*wire:model/')
        ->and($html)->not->toContain('onchange=');
});

it('segmented sin enlace sigue auto-enviando el form GET', function () {
    expect(Blade::render('<x-muni::segmented name="vista" :options="[\'a\' => \'A\']" />'))->toContain('onchange=');
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

it('segmented con autosubmit="siempre" también envía forms POST', function () {
    expect(Blade::render('<x-muni::segmented name="e" autosubmit="siempre" :options="[\'a\' => \'A\']" />'))->not->toContain("method===")
        ->and(Blade::render('<x-muni::segmented name="e" :options="[\'a\' => \'A\']" />'))->toContain("method===");
});

it('textarea, checkbox y radio-group llevan wire:model al control real', function () {
    expect(Blade::render('<x-muni::textarea name="obs" wire:model.blur="obs" />'))->toMatch('/<textarea[^>]*wire:model\.blur="obs"/')
        ->and(Blade::render('<x-muni::checkbox name="acepta" wire:model="acepta" label="Acepto" />'))->toMatch('/<input type="checkbox"[^>]*wire:model="acepta"/');

    $radios = Blade::render('<x-muni::radio-group name="tipo" wire:model="tipo" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');
    expect(substr_count($radios, 'wire:model="tipo"'))->toBe(2)
        ->and($radios)->not->toMatch('/<fieldset[^>]*wire:model/');
});

/*
 * Con Alpine 3.15 (el que trae Livewire 3) un x-for con clave por posición reutiliza
 * los botones al cambiar de mes y no recalcula cuál está marcado: la fecha que
 * llegaba del servidor no se veía seleccionada. La clave tiene que ser la fecha.
 */
it('calendar identifica cada día por su fecha y no por su posición', function () {
    expect(Blade::render('<x-muni::calendar name="f" />'))
        ->toContain(':key="c ? iso(c) : ')
        ->not->toContain('in celdas" :key="i"');
});
