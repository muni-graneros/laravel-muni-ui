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
