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

    expect($html)->toContain('role="combobox"')
        ->toContain(':aria-expanded=')
        ->toContain(":aria-controls=\"\$id('muni-cp')\"")
        ->toContain(':aria-activedescendant=')
        ->toContain('role="listbox"')
        ->toContain('role="option"')
        ->toContain(':aria-selected=')
        ->toContain('@keydown="atrapar($event)"')
        ->toContain('devolverFoco()')
        ->toContain('muni-cp__opt--on{background:var(--muni-accent-soft)');
});

it('el calendario nombra cada día con la fecha completa y tiene teclado de grilla', function () {
    $html = Blade::render('<x-muni::calendar name="f" value="2026-10-12" min="2026-10-01" />');

    expect($html)->toContain(':aria-label="c ? nombre(c) : null"')
        ->toContain(':aria-pressed=')
        ->toContain(':tabindex="c && same(c,objetivo) ? 0 : -1"')
        ->toContain('@keydown="tecla($event)"')
        ->toContain('PageUp')->toContain('Home')
        // x-modelable, value y min siguen intactos.
        ->toContain('x-modelable="valor"')
        ->toContain('value="2026-10-12"')
        ->toContain("('2026-10-01')");
});

it('las estrellas interactivas miden al menos 24×24 y sin marcar usan el borde fuerte', function () {
    $html = Blade::render('<x-muni::rating :value="2" />');

    expect($html)->toContain('min-width:24px')->toContain('min-height:24px')
        ->toContain('var(--muni-border-strong')
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

    expect($html)->toContain('@focusout="if (open && $event.relatedTarget && ! $el.contains($event.relatedTarget)) open = false"');
});

it('los encabezados de data-table tienen scope y el vacío se rotula «Acciones»', function () {
    $html = Blade::render('<x-muni::data-table :columns="[\'RUT\', \'Nombre\', \'\']" />');

    expect(substr_count($html, '<th scope="col"'))->toBe(3)
        ->and($html)->toMatch('/<th scope="col"[^>]*><span style="position:absolute;[^"]*">Acciones<\/span><\/th>/')
        ->and($html)->toMatch('/<th scope="col"[^>]*>RUT<\/th>/');
});

it('la página de error pone el contenido en un <main>', function () {
    $html = Blade::render('<x-muni::error-page />');

    expect($html)->toMatch('/<main class="muni-err__card">.*Volver al inicio.*<\/main>/s');
});

it('breadcrumb y paginación aceptan otro nombre accesible', function (string $tag, string $porDefecto) {
    expect(Blade::render("<x-muni::{$tag} />"))->toContain("aria-label=\"{$porDefecto}\"");

    $conLabel = Blade::render("<x-muni::{$tag} label=\"Secundaria\" />");
    expect($conLabel)->toContain('aria-label="Secundaria"')->not->toContain("aria-label=\"{$porDefecto}\"");

    $conAria = Blade::render("<x-muni::{$tag} aria-label=\"Otra\" />");
    expect($conAria)->toContain('aria-label="Otra"')->and(substr_count($conAria, 'aria-label='))->toBe(1);
})->with([
    ['breadcrumb', 'Ruta'],
    ['pagination', 'Paginación'],
]);

it('el texto legal del footer y las etiquetas del stepper no pierden legibilidad', function () {
    $footer = Blade::render('<x-muni::gob-footer />');
    expect($footer)->toMatch('/\.muni-gob-footer__legal \{[^}]*opacity:\.7;/');

    $stepper = Blade::render('<x-muni::stepper :steps="[\'Datos\', \'Documentos\']" />');
    expect($stepper)->toMatch('/\.muni-step__label \{[^}]*\}/')
        ->and(preg_match('/\.muni-step__label \{([^}]*)\}/', $stepper, $m))->toBe(1)
        ->and($m[1])->not->toContain('ellipsis')->not->toContain('nowrap');
});
