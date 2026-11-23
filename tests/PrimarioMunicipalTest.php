<?php

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\View\Components\ButtonComponent;

uses()->group('panel');

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('prueba'));
    Filament::bootCurrentPanel();
});

it('el primario por defecto es la paleta V3 del petróleo municipal', function () {
    expect(Filament::getPanel('prueba')->getColors()['primary'])
        ->toBe(Color::generateV3Palette('#355a63'));
});

it('la tonalidad 500 del primario es el petróleo del escudo, no un cian', function () {
    $primario = Filament::getPanel('prueba')->getColors()['primary'];

    expect($primario[500])->toBe(Color::convertToOklch('rgb(53, 90, 99)'));
});

it('el botón primario cumple AA en claro y en oscuro', function () {
    $primario = Filament::getPanel('prueba')->getColors()['primary'];
    $mapa = ButtonComponent::make()->getColorMap($primario);

    $claro = Color::calculateContrastRatio($primario[$mapa['text']] ?? 'oklch(1 0 0)', $primario[$mapa['bg']]);
    $oscuro = Color::calculateContrastRatio($primario[$mapa['dark:text']] ?? 'oklch(1 0 0)', $primario[$mapa['dark:bg']]);

    expect($claro)->toBeGreaterThanOrEqual(4.5)
        ->and($oscuro)->toBeGreaterThanOrEqual(4.5);
});
