<?php

/*
 * Regresiones de los componentes de navegación y diálogo: shells, sidebar,
 * modal, drawer, dropdown, tabs, tooltip, paleta de comandos, toasts y página de
 * error. El comportamiento en navegador se revisa con el catálogo; aquí se cuida
 * el HTML que lo hace posible.
 */

use Illuminate\Support\Facades\Blade;

function tituloDe(string $html): string
{
    preg_match('/<title>(.*?)<\/title>/s', $html, $m);

    return $m[1] ?? '';
}

it('deja la meta de Reverb fuera del <title> en los shells', function (string $shell) {
    config()->set('broadcasting.connections.reverb.key', 'clave-de-prueba');

    $html = Blade::render("<x-muni::{$shell} system=\"Licencias\" title=\"Inicio\">Hola</x-muni::{$shell}>");

    expect(tituloDe($html))->not->toContain('<meta')->not->toContain('reverb')
        ->and($html)->toMatch('/<meta name="reverb-key" content="clave-de-prueba">\s*<title>/');
})->with(['dashboard-shell', 'app-shell', 'auth-shell']);

it('el dashboard-shell tiene estado para el burger, velo y Escape', function () {
    $html = Blade::render('<x-muni::dashboard-shell system="Panel">Hola</x-muni::dashboard-shell>');

    expect($html)->toMatch('/<body\s[^>]*x-data=/')
        ->toContain('class="muni-ds__scrim"')
        ->toContain('@keydown.escape.window=')
        ->toContain("new CustomEvent('muni-sidebar')");
});

it('la página de error no recorta el contenido en pantallas bajas', function () {
    $html = Blade::render('<x-muni::error-page />');

    expect($html)->not->toContain('overflow:hidden');
});

it('el sidebar combina las clases del host con la suya', function () {
    $html = Blade::render('<x-muni::sidebar class="extra" style="color:red;">x</x-muni::sidebar>');

    preg_match('/<aside.*?\n>/s', $html, $m);

    expect(substr_count($m[0], ' class="'))->toBe(1)
        ->and($m[0])->toContain('class="muni-sb extra"')
        ->and(substr_count($m[0], ' style="'))->toBe(1)
        ->and($m[0])->toContain('--sb-w:240px;')->toContain('color:red;')
        ->and($m[0])->toContain('@keydown.escape.window=');
});

it('modal y drawer son diálogos etiquetados que no pisan el overflow en cada cierre', function (string $componente) {
    $html = Blade::render("<x-muni::{$componente} title=\"Ficha\">Contenido</x-muni::{$componente}>");

    expect($html)->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain(":aria-labelledby=\"\$id('muni-{$componente}')\"")
        ->toContain(":id=\"\$id('muni-{$componente}')\"")
        ->toContain('destroy()')
        ->not->toContain('x-effect="document.body.style.overflow');
})->with(['modal', 'drawer']);

it('el drawer usa la duración del token (movimiento reducido)', function () {
    $html = Blade::render('<x-muni::drawer>x</x-muni::drawer>');

    expect($html)->not->toContain('.28s')->toContain('var(--muni-dur)');
});

it('el ítem del dropdown no cierra cualquier `open` ancestro', function () {
    $html = Blade::render('<x-muni::dropdown-item>Ver</x-muni::dropdown-item>');

    expect($html)->not->toContain('open = false')
        ->toContain("\$dispatch('muni-dropdown-close')");
});

it('el dropdown escucha su evento de cierre y no pone ARIA en el envoltorio', function () {
    $html = Blade::render(<<<'BLADE'
        <x-muni::dropdown>
            <x-slot:trigger><button>Acciones</button></x-slot:trigger>
            x
        </x-muni::dropdown>
        BLADE);

    expect($html)->toContain('@muni-dropdown-close=')
        ->not->toContain(':aria-expanded="open"')
        ->toContain("setAttribute('aria-expanded', open)");
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
        ->and($html)->toContain(":aria-controls=\"\$id('muni-tabpanel', 1)\"");
});

it('las pestañas sin etiquetas no dividen por cero', function () {
    $html = Blade::render('<x-muni::tabs :tabs="[]" />');

    expect($html)->toContain('if (! this.count) return;')
        ->not->toContain('% count"');
});

it('el tooltip queda referenciado por aria-describedby', function () {
    $html = Blade::render('<x-muni::tooltip text="Ayuda"><button>?</button></x-muni::tooltip>');

    expect($html)->toContain(":id=\"\$id('muni-tip')\"")
        ->toContain('aria-describedby')
        ->toContain('@keydown.escape.window="show=false"');
});

it('la paleta escapa hotkey y placeholder y rotula el buscador', function () {
    $html = Blade::render(<<<'BLADE'
        <x-muni::command-palette hotkey="'" placeholder="Busca 'algo' &quot;aquí&quot;" :items="[
            ['label' => 'A', 'url' => '/x'],
            ['label' => 'B', 'url' => '/x'],
        ]" />
        BLADE);

    expect($html)->not->toContain("==='''")
        ->toContain("\$event.key==='\\u0027'")
        ->not->toContain(':placeholder=')
        ->toContain('aria-label="Busca &#039;algo&#039;')
        ->toContain(':key="i"')
        ->toContain('@input="active=0"')
        ->toContain('aria-label="Paleta de comandos"');
});

it('los toasts se muestran con x-show para que la transición ocurra', function () {
    $html = Blade::render('<x-muni::toast-host />');

    expect($html)->toContain('x-show="item.shown"')
        ->toContain('--muni-dur');
});
