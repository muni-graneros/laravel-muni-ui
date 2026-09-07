<?php

use Illuminate\Support\Facades\Blade;

/*
 * Candado de render para TODOS los componentes del paquete.
 *
 * Por qué existe: el inventario del 2026-09-05 midió que 41 de 53 componentes no
 * tenían ninguna prueba. Un componente que revienta al renderizarse —una variable
 * indefinida, un @props mal cerrado, un slot obligatorio no documentado— no lo
 * descubría nadie hasta que un sistema lo usaba en producción.
 *
 * La lista NO está escrita a mano: se lee del directorio. Un componente nuevo
 * queda cubierto el día que se crea, sin que nadie se acuerde de agregarlo acá.
 * Ese es todo el valor de la prueba; si se congelara la lista, dejaría de servir.
 *
 * Esto es un candado de humo, no una prueba de accesibilidad: comprueba que el
 * componente se renderiza. Lo que cada uno debe cumplir vive en su propio archivo
 * de prueba.
 */

/** Props mínimas de los componentes que declaran una prop sin valor por defecto. */
function propsObligatorias(): array
{
    return [
        'app-shell' => ':system="\'Licencias\'"',
        'topbar' => ':system="\'Licencias\'"',
        'page-header' => 'title="Patentes morosas"',
        'kpi' => ':value="42" label="Solicitudes"',
        'stat' => ':value="42" label="Solicitudes"',
        // tab-panel lee `active` del x-data de su padre: fuera de <x-muni::tabs>
        // renderiza igual, que es justo lo que hay que comprobar acá.
        'tab-panel' => ':index="0"',
    ];
}

/**
 * Componentes que pueden rendir vacío sin que sea un defecto, con el motivo.
 * Cualquier otro que salga vacío es un error y la prueba lo dice.
 */
function puedenSalirVacios(): array
{
    return [
        // Sin clave de Reverb configurada no emite ninguna <meta>, a propósito:
        // así el host no publica etiquetas a medias.
        'reverb-meta' => 'no hay configuración de Reverb en el entorno de prueba',
        // Sin errores no hay nada que resumir: renderizar un contenedor vacío sería
        // peor, porque una región viva vacía se anuncia igual en algunos lectores.
        'error-summary' => 'sin errores no debe emitir nada',
    ];
}

function componentesDelPaquete(): array
{
    $dir = __DIR__.'/../resources/views/components';
    $nombres = [];

    foreach (glob($dir.'/*.blade.php') ?: [] as $ruta) {
        $nombres[] = substr(basename($ruta), 0, -strlen('.blade.php'));
    }

    sort($nombres);

    return array_combine($nombres, array_map(fn ($n) => [$n], $nombres));
}

it('se renderiza sin reventar', function (string $nombre) {
    $extra = propsObligatorias()[$nombre] ?? '';
    $etiqueta = trim("<x-muni::{$nombre} {$extra}>");

    $html = Blade::render("{$etiqueta}Contenido de prueba</x-muni::{$nombre}>");

    expect($html)->toBeString();

    if (! array_key_exists($nombre, puedenSalirVacios())) {
        expect(trim($html))->not->toBe(
            '',
            "«{$nombre}» se renderiza vacío. Si es a propósito, agrégalo a puedenSalirVacios() con el motivo."
        );
    }
})->with(componentesDelPaquete());

it('cubre de verdad todos los archivos del directorio', function () {
    // Candado del candado: si alguien cambia la convención de nombres y el glob
    // deja de encontrar los componentes, la prueba de arriba pasaría con cero
    // casos y nadie se enteraría.
    $encontrados = count(componentesDelPaquete());

    expect($encontrados)->toBeGreaterThanOrEqual(
        54,
        "El descubrimiento encontró {$encontrados} componentes; el paquete tenía 54 al escribir esto. ".
        'Si de verdad se quitaron componentes, baja el número; si no, el glob se rompió.'
    );
});
