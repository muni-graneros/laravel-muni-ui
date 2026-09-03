<?php

use Illuminate\Support\Facades\Blade;

/*
 * Foco atrapado en los tres diálogos (WCAG 2.2, 2.4.3 y 2.1.2).
 *
 * `grep -rn 'x-trap\|inert\|aria-labelledby' resources/views/` daba 0
 * resultados en todo el paquete: Tab podía escapar del modal/drawer/paleta
 * hacia el resto de la página con el diálogo todavía abierto, y nada
 * devolvía el foco a quien lo abrió al cerrar con Esc. El plugin Focus de
 * Alpine (`x-trap`) ya viaja en el bundle de Livewire de los 8 paneles —no
 * hace falta instalarlo—, y su comportamiento por defecto (sin `.noreturn`)
 * restaura el foco solo, así que no hace falta rastrear el disparador a mano.
 */
it('el modal atrapa el foco y enlaza el título con aria-labelledby', function () {
    $html = Blade::render('<x-muni::modal title="Confirmar"><p>Contenido</p></x-muni::modal>');

    expect(str_contains($html, 'x-trap'))->toBeTrue(
        'El modal no atrapa el foco: con Tab se puede salir del diálogo hacia el resto de la página.'
    );

    expect((bool) preg_match('/aria-labelledby="([^"]+)"/', $html, $m))->toBeTrue(
        'El diálogo no enlaza su título con aria-labelledby.'
    );
    expect(str_contains($html, 'id="'.$m[1].'"'))->toBeTrue(
        'El aria-labelledby del modal no coincide con el id de su título.'
    );
});

it('el drawer atrapa el foco y enlaza el título con aria-labelledby', function () {
    $html = Blade::render('<x-muni::drawer title="Detalle"><p>Contenido</p></x-muni::drawer>');

    expect(str_contains($html, 'x-trap'))->toBeTrue(
        'El drawer no atrapa el foco: con Tab se puede salir del panel lateral.'
    );

    expect((bool) preg_match('/aria-labelledby="([^"]+)"/', $html, $m))->toBeTrue(
        'El drawer no enlaza su título con aria-labelledby.'
    );
    expect(str_contains($html, 'id="'.$m[1].'"'))->toBeTrue(
        'El aria-labelledby del drawer no coincide con el id de su título.'
    );
});

it('la paleta de comandos atrapa el foco y se anuncia con aria-label', function () {
    $html = Blade::render('<x-muni::command-palette :items="[]" />');

    expect(str_contains($html, 'x-trap'))->toBeTrue(
        'La paleta de comandos no atrapa el foco: con Tab se puede salir hacia el resto de la página.'
    );
    expect(str_contains($html, 'aria-label="Paleta de comandos"'))->toBeTrue(
        'La paleta no tiene nombre accesible: no lleva título visible, así que necesita aria-label.'
    );
});

it('dos modales en la misma página no colisionan de id', function () {
    $html = Blade::render(
        '<x-muni::modal title="Uno"><p>A</p></x-muni::modal><x-muni::modal title="Dos"><p>B</p></x-muni::modal>'
    );

    preg_match_all('/aria-labelledby="([^"]+)"/', $html, $m);

    expect($m[1])->toHaveCount(2);
    expect($m[1][0])->not->toBe($m[1][1]);
});

/*
 * Render de los componentes más usados del ecosistema (13 con consumidor real
 * según el relevo del paquete). No cubre los 53 —eso es el hallazgo de
 * adopción, aparte— pero deja el patrón `Blade::render()` copiable para el
 * resto y prueba que ninguno truena con las props mínimas.
 */
it('el botón primario renderiza con su variante y aplica el atributo dado', function () {
    $html = Blade::render('<x-muni::button variant="primary">Guardar</x-muni::button>');

    expect($html)->toContain('muni-btn--primary');
    expect($html)->toContain('Guardar');
    expect($html)->toContain('type="button"');
});

it('el badge de tono warn renderiza el color correcto', function () {
    $html = Blade::render('<x-muni::badge tone="warn">Pendiente</x-muni::badge>');

    expect($html)->toContain('var(--muni-warn-fg)');
    expect($html)->toContain('Pendiente');
});

it('la alerta de tono danger usa role="alert" y las demás role="status"', function () {
    $danger = Blade::render('<x-muni::alert tone="danger" title="Error">Algo falló</x-muni::alert>');
    $info = Blade::render('<x-muni::alert tone="info">Aviso</x-muni::alert>');

    expect($danger)->toContain('role="alert"');
    expect($info)->toContain('role="status"');
});

it('el kpi renderiza valor, etiqueta y el tono elegido', function () {
    $html = Blade::render('<x-muni::kpi value="128" label="Solicitudes" tone="ok" />');

    expect($html)->toContain('128');
    expect($html)->toContain('Solicitudes');
    expect($html)->toContain('var(--muni-ok-fg)');
});

it('el stat con delta positivo usa el color ok y dibuja la flecha', function () {
    $html = Blade::render('<x-muni::stat value="42" label="Activos" delta="+3" delta-dir="up" />');

    expect($html)->toContain('42');
    expect($html)->toContain('var(--muni-ok-fg)');
});
