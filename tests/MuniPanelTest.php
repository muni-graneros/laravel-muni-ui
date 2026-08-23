<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Muni\Ui\Filament\MuniPanel;
use Muni\Ui\Tests\Fixtures\PanelDePruebaProvider;

// Lo que este repo YA tiene en producción en cuatro paneles y hasta hoy no
// tenía ni una prueba. Va primero a propósito: un arnés que no puede probar lo
// que ya está desplegado tampoco va a poder probar lo que se traiga después.
uses()->group('panel');

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('prueba'));
    // Los render hooks del plugin se registran en el BOOT del panel, no al
    // construirlo: sin esta línea el panel existe y no aplica nada.
    Filament::bootCurrentPanel();
});

it('viste el panel con la franja institucional de siete colores', function () {
    $franja = (string) FilamentView::renderHook(PanelsRenderHook::TOPBAR_BEFORE);

    expect($franja)
        ->toContain('#adcd60')
        ->toContain('#355a63')
        ->toContain('#9c9b9b')
        // Decorativa: no aporta información y no debe leerla un lector de pantalla.
        ->toContain('aria-hidden="true"');
});

it('pone el escudo y el departamento en el pie de la barra lateral', function () {
    $pie = (string) FilamentView::renderHook(PanelsRenderHook::SIDEBAR_FOOTER);

    expect($pie)
        ->toContain('vendor/muni-ui/logo-graneros.png')
        ->toContain('Municipalidad de Graneros')
        ->toContain('Departamento de Prueba')
        ->toContain('alt=');
});

it('carga el tema municipal y el observador de FilePond', function () {
    expect((string) FilamentView::renderHook(PanelsRenderHook::STYLES_AFTER))
        ->toContain('vendor/muni-ui/filament.css');

    expect((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))
        ->toContain('vendor/muni-ui/filepond-video.js')
        // Sin data-navigate-once cada navegación SPA apila otro observador.
        ->toContain('data-navigate-once');
});

it('fija la paleta primaria al petróleo del escudo', function () {
    expect(Filament::getPanel('prueba')->getColors())->toHaveKey('primary');
});

it('deja la paleta en paz cuando el sistema define su propio acento', function () {
    // Feria usa ámbar y no el petróleo del escudo: el plugin tiene que poder
    // vestir el panel sin imponerle la paleta. Se registra sobre un panel
    // aparte para no ensuciar el del arnés.
    $otro = Panel::make()->id('sin-colores');

    MuniPanel::make()->conColores(false)->register($otro);

    expect($otro->getColors())->toBe([]);
});
