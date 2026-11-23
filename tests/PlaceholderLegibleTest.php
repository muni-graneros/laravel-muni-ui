<?php

$css = fn (): string => (string) file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');

$lum = function (string $hex): float {
    $c = array_map(fn ($i) => hexdec(substr(ltrim($hex, '#'), $i, 2)) / 255, [0, 2, 4]);
    $c = array_map(fn ($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);

    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
};

$contraste = function (string $a, string $b) use ($lum): float {
    [$x, $y] = [$lum($a), $lum($b)];

    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
};

it('en claro el placeholder de los campos usa gray-600', function () use ($css) {
    expect(preg_match('/(^|\})\s*[^{}]*(?<!\.dark )input\.fi-input::placeholder[^{}]*\{[^}]*color\s*:\s*var\(--gray-600\)/', $css()))->toBe(1);
});

it('en oscuro el placeholder de los campos usa gray-400', function () use ($css) {
    expect(preg_match('/\.dark\s+input\.fi-input::placeholder[^{}]*\{[^}]*color\s*:\s*var\(--gray-400\)/', $css()))->toBe(1);
});

it('el placeholder cubre también textarea dentro del envoltorio del campo', function () use ($css) {
    expect(str_contains($css(), '.fi-input-wrp textarea::placeholder'))->toBeTrue()
        ->and(str_contains($css(), '.dark .fi-input-wrp textarea::placeholder'))->toBeTrue();
});

it('los grises elegidos alcanzan 4,5:1 sobre los fondos de campo del tema', function () use ($contraste) {
    expect($contraste('#52525c', '#ffffff'))->toBeGreaterThanOrEqual(4.5);
    foreach (['#0f2025', '#081418'] as $fondo) {
        expect($contraste('#9f9fa9', $fondo))->toBeGreaterThanOrEqual(4.5);
    }
});
