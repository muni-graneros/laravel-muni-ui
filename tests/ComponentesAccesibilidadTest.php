<?php

/*
 * Regresiones de accesibilidad (auditoría axe-core + revisión manual): semántica
 * combobox de la paleta, teclado y nombres del calendario, tamaño de objetivo del
 * rating, cierre del dropdown al salir con Tab, encabezados de tabla, landmark de la
 * página de error y nombres configurables de breadcrumb y paginación.
 */

use Illuminate\Support\Facades\Blade;

it('la paleta es combobox + listbox, atrapa Tab y devuelve el foco', function () {
    $html = Blade::render(<<<'BLADE'
        <x-muni::command-palette :items="[['label' => 'Puestos', 'url' => '/puestos']]">
            <x-slot:trigger>
                <button type="button">Buscar</button>
            </x-slot:trigger>
        </x-muni::command-palette>
        BLADE);

    // La trampa de foco y la devolución al cerrar las pone x-trap (plugin Focus).
    expect($html)->toContain('role="combobox"')
        ->toContain('aria-expanded="true"')
        ->toContain(":aria-controls=\"\$id('muni-cmdk-lista')\"")
        ->toContain(':aria-activedescendant=')
        ->toContain('role="listbox"')
        ->toContain('role="option"')
        ->toContain(':aria-selected=')
        ->toContain('x-trap.inert.noscroll=')
        ->toContain('.muni-cmdk__opcion--activa');
});

it('el calendario nombra cada día con la fecha completa', function () {
    $html = Blade::render('<x-muni::calendar name="f" value="2026-10-12" min="2026-10-01" />');

    expect($html)->toContain(':aria-label="largo(c)"')
        // x-modelable, value y min siguen intactos.
        ->toContain('x-modelable="valor"')
        ->toContain('value="2026-10-12"')
        ->toContain('data-min="2026-10-01"');
});

it('las estrellas interactivas miden al menos 24×24 y sin marcar no bajan de 3:1', function () {
    $html = Blade::render('<x-muni::rating :value="2" />');

    // --muni-border-2 queda en 1,6:1; --muni-hint tiene las dos ramas sobre 4,5:1.
    expect($html)->toContain('min-width:24px')->toContain('min-height:24px')
        ->toContain('color:var(--muni-hint)')->not->toContain('color:var(--muni-border-2)')
        ->toContain('x-modelable="value"');
});

it('el dropdown cierra cuando el foco sale del componente', function () {
    $html = Blade::render(<<<'BLADE'
        <x-muni::dropdown>
            <x-slot:trigger>
                <button type="button">Acciones</button>
            </x-slot:trigger>
            <x-muni::dropdown-item href="/a">A</x-muni::dropdown-item>
        </x-muni::dropdown>
        BLADE);

    expect($html)->toContain('@focusout="alPerderElFoco($event)"');
});

it('los encabezados de data-table tienen scope y el vacío se rotula «Acciones»', function () {
    $html = Blade::render('<x-muni::data-table :columns="[\'RUT\', \'Nombre\', \'\']" />');

    expect(substr_count($html, '<th scope="col"'))->toBe(3)
        ->and($html)->toMatch('/<th scope="col"[^>]*><span class="muni-sr">Acciones<\/span><\/th>/')
        ->and($html)->toMatch('/<th scope="col"[^>]*>RUT<\/th>/');
});

it('la página de error pone el contenido en un <main>', function () {
    $html = Blade::render('<x-muni::error-page />');

    expect($html)->toMatch('/<main class="muni-err__card">.*Volver al inicio.*<\/main>/s');
});

/*
 * Con dos navegaciones iguales en la página, el `aria-label` del consumidor
 * reemplaza el del paquete dentro de merge() y no se duplica. (Los detalles de
 * breadcrumb viven en MigasDeRutaTest.)
 */
it('breadcrumb y paginación aceptan otro nombre accesible', function (string $tag, string $props, string $porDefecto) {
    expect(Blade::render("<x-muni::{$tag} {$props} />"))->toContain("aria-label=\"{$porDefecto}\"");

    $conAria = Blade::render("<x-muni::{$tag} {$props} aria-label=\"Otra\" />");
    expect($conAria)->toContain('aria-label="Otra"')->and(substr_count($conAria, 'aria-label='))->toBe(1);
})->with([
    ['breadcrumb', ':items="[\'Inicio\']"', 'Ruta'],
    ['pagination', '', 'Paginación, página 1 de 1'],
]);

it('el texto legal del footer no pierde legibilidad', function () {
    expect(Blade::render('<x-muni::gob-footer />'))->toMatch('/\.muni-gob-footer__legal \{[^}]*opacity:\.7;/');
});

it('el scroll de data-table contiene sus textos ocultos y no ensancha la página', function () {
    // `.muni-sr` es absoluto: sin un contenedor posicionado sale del scroll y el
    // documento entero gana scroll horizontal a 390 px.
    expect(Blade::render('<x-muni::data-table :columns="[\'RUT\', \'\']" />'))
        ->toMatch('/\.muni-dt__scroll \{ position:relative; overflow:auto;/');
});
