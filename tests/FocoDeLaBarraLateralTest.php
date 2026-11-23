<?php

$css = fn (): string => (string) file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');

it('cada control de la barra lateral tiene un indicador de foco propio', function () use ($css) {
    preg_match_all('/([^{}]*:focus-visible[^{}]*)\{([^}]*)\}/', $css(), $reglas, PREG_SET_ORDER);
    $conLima = array_filter($reglas, fn ($r) => preg_match('/outline\s*:\s*3px solid var\(--mg-lima-br\)/', $r[2]));
    $selectores = implode(',', array_map(fn ($r) => $r[1], $conLima));

    foreach (['.fi-sidebar-item-btn', '.fi-sidebar-group-collapse-button', '.fi-icon-btn', '.fi-sidebar-group-btn'] as $control) {
        expect(str_contains($selectores, $control))->toBeTrue("{$control} no tiene foco visible en la barra lateral");
    }
});

it('el indicador de la barra lateral contrasta 3:1 con todo su fondo', function () {
    $lum = function (string $hex): float {
        $c = array_map(fn ($i) => hexdec(substr(ltrim($hex, '#'), $i, 2)) / 255, [0, 2, 4]);
        $c = array_map(fn ($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };
    foreach (['#10424c', '#0b2f37', '#082228'] as $fondo) {
        [$a, $b] = [$lum('#c3e07a'), $lum($fondo)];
        expect((max($a, $b) + 0.05) / (min($a, $b) + 0.05))->toBeGreaterThanOrEqual(3.0);
    }
});
