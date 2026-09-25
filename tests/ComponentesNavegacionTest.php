<?php

/*
 * Regresiones de los componentes de navegación y diálogo: shells, sidebar,
 * modal, drawer, dropdown, tabs, tooltip, paleta de comandos, toasts y página de
 * error. El comportamiento en navegador se revisa con el catálogo; aquí se cuida
 * el HTML que lo hace posible.
 */

use Illuminate\Support\Facades\Blade;

function navegacionTituloDe(string $html): string
{
    preg_match('/<title>(.*?)<\/title>/s', $html, $m);

    return $m[1] ?? '';
}

it('deja la meta de Reverb fuera del <title> en los shells', function (string $shell) {
    config()->set('broadcasting.connections.reverb.key', 'clave-de-prueba');

    $html = Blade::render("<x-muni::{$shell} system=\"Licencias\" title=\"Inicio\">Hola</x-muni::{$shell}>");

    expect(navegacionTituloDe($html))->not->toContain('<meta')->not->toContain('reverb')
        ->and($html)->toMatch('/<meta name="reverb-key" content="clave-de-prueba">\s*<title>/');
})->with(['dashboard-shell', 'app-shell', 'auth-shell']);

it('la página de error no recorta el contenido en pantallas bajas', function () {
    $html = Blade::render('<x-muni::error-page />');

    expect($html)->not->toContain('overflow:hidden');
});

it('modal y drawer bloquean el scroll con la trampa de foco y no pisan el overflow a mano', function (string $componente) {
    // role, aria-modal y aria-labelledby → DialogosCoherentesTest. Acá, solo el
    // scroll: lo gobierna `x-trap.noscroll`, que al cerrar devuelve el overflow
    // que había; un x-effect sobre body.style.overflow lo pisaba en cada cierre.
    $html = Blade::render("<x-muni::{$componente} title=\"Ficha\">Contenido</x-muni::{$componente}>");

    expect($html)->toContain('x-trap.inert.noscroll="open"')
        ->not->toContain('body.style.overflow');
})->with(['modal', 'drawer']);

it('el drawer usa la duración del token (movimiento reducido)', function () {
    $html = Blade::render('<x-muni::drawer>x</x-muni::drawer>');

    expect($html)->not->toContain('.28s')->toContain('var(--muni-dur)');
});

it('el ítem del dropdown cierra su desplegable y no el `open` de un modal ancestro', function () {
    // Dentro de un modal, el `cerrar()` más cercano es el del desplegable: el modal
    // no tiene `cerrar` y su `open` solo se toca en un x-data propio sin desplegable
    // (contrato de siempre). Medido en Chromium: Enter en el ítem cierra el menú y el
    // modal sigue abierto.
    $html = Blade::render(<<<'BLADE'
        <x-muni::modal title="Ficha">
            <x-muni::dropdown><x-muni::dropdown-item>Ver</x-muni::dropdown-item></x-muni::dropdown>
        </x-muni::modal>
        BLADE);

    preg_match('/<button[^>]*role="menuitem"[^>]*>/s', $html, $item);
    preg_match_all('/\sx-data="([^"]*)"/', $html, $scopes);

    // El primer scope es el del modal; el segundo, el del desplegable.
    expect($item[0] ?? '')->toContain("typeof cerrar === 'function' ? cerrar(true)")
        ->and($scopes[1][0])->toContain('open: false')->not->toContain('cerrar(')
        ->and($scopes[1][1])->toContain('cerrar(destino)');
});

it('sin JS se ve el panel por defecto de las pestañas', function () {
    $html = Blade::render(<<<'BLADE'
        <x-muni::tabs :tabs="['A', 'B', 'C']" :default="1">
            <x-muni::tab-panel :index="0">uno</x-muni::tab-panel>
            <x-muni::tab-panel :index="1">dos</x-muni::tab-panel>
            <x-muni::tab-panel :index="2">tres</x-muni::tab-panel>
        </x-muni::tabs>
        BLADE);

    preg_match_all('/<div[^>]*role="tabpanel"[^>]*>/s', $html, $m);

    expect($m[0])->toHaveCount(3)
        ->and($m[0][0])->toContain('x-cloak')
        ->and($m[0][1])->not->toContain('x-cloak')
        ->and($m[0][2])->toContain('x-cloak')
        ->and($m[0][1])->toMatch('/id="(muni-tabs-[0-9a-f]{8})-panel-1"/')
        ->and($html)->toMatch('/<button[^>]*aria-controls="muni-tabs-[0-9a-f]{8}-panel-1"[^>]*aria-selected="true"/s');
});

it('las pestañas sin etiquetas no dividen por cero', function () {
    $html = Blade::render('<x-muni::tabs :tabs="[]" />');

    expect($html)->toContain('if (! this.count) return;')
        ->not->toContain('% count"');
});

it('la paleta rotula el buscador, reinicia el activo al filtrar y no repite claves del x-for', function () {
    // hotkey y placeholder con apóstrofo → RotuloDelAtajoTest. Dos ítems con la
    // misma url daban la misma clave y Alpine no pintaba ningún resultado (Chromium).
    $html = Blade::render(<<<'BLADE'
        <x-muni::command-palette placeholder="Busca 'algo'" :items="[
            ['label' => 'A', 'url' => '/x'],
            ['label' => 'B', 'url' => '/x'],
        ]" />
        BLADE);

    expect($html)->toContain('aria-label="Busca &#039;algo&#039;"')
        ->toContain('@input="active=0"')
        ->toContain(':key="item.clave"')
        ->toContain('&quot;clave&quot;:0')
        ->toContain('&quot;clave&quot;:1')
        ->toContain('aria-label="Paleta de comandos"');
});

it('los toasts se muestran con x-show para que la transición ocurra', function () {
    $html = Blade::render('<x-muni::toast-host />');

    expect($html)->toContain('x-show="item.shown"')
        ->toContain('--muni-dur');
});

it('tabs acepta etiquetas con claves de texto sin escribir la clave en JS', function () {
    $html = Blade::render(<<<'BLADE'
        <x-muni::tabs id="ficha" :tabs="['datos' => 'Datos', 'docs' => 'Documentos']" default="docs">
            <x-muni::tab-panel :index="0">a</x-muni::tab-panel>
            <x-muni::tab-panel :index="1">b</x-muni::tab-panel>
        </x-muni::tabs>
        BLADE);

    // A Alpine y a los id viaja la posición; `default` acepta la clave.
    expect($html)->toContain('@click="seleccionar(1)"')
        ->toMatch('/id="ficha-tab-1"\s+aria-controls="ficha-panel-1"/')
        ->toContain('active: 1,')
        ->not->toContain('seleccionar(docs)')
        ->not->toContain('=== datos')
        ->and((bool) preg_match('/<div[^>]*id="ficha-panel-1"[^>]*>/s', $html, $panel))->toBeTrue()
        ->and($panel[0])->not->toContain('x-cloak');
});
