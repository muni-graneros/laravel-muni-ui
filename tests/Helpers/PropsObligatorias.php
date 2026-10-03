<?php

/** Props mínimas de los componentes que declaran una prop sin valor por defecto. */
function propsObligatorias(): array
{
    return [
        'app-shell' => ':system="\'Licencias\'"',
        'topbar' => ':system="\'Licencias\'"',
        'checkbox-group' => 'name="requisitos" legend="Requisitos entregados"',
        'combobox' => 'name="titular_id"',
        'page-header' => 'title="Patentes morosas"',
        'kpi' => ':value="42" label="Solicitudes"',
        // nav-menu recibe el árbol ya filtrado por el anfitrión y nav-grupo el
        // rótulo del que salen sus ids: sin ellos no hay contrato que probar.
        'nav-menu' => ':items="[[\'etiqueta\' => \'Inicio\', \'href\' => \'/\']]"',
        'nav-grupo' => 'titulo="Operaciones"',
        'stat' => ':value="42" label="Solicitudes"',
        // tab-panel lee `active` del x-data de su padre: fuera de <x-muni::tabs>
        // renderiza igual, que es justo lo que hay que comprobar acá.
        'tab-panel' => ':index="0"',
        'selector-tema' => 'action="/preferencias/tema"',
        'sesion-guardia' => 'expira-en="2026-09-13T15:30:00-03:00"',
        // Componentes de la tanda de cierre del backlog con props sin defecto.
        'description-item' => 'label="RUT"',
        'pantalla-bloqueo' => 'nombre="María Fernanda Soto"',
        'pii' => 'label="RUT"',
        'plantilla-pantalla' => 'title="Inicio"',
    ];
}

function componentesDelPaquete(): array
{
    $dir = __DIR__.'/../../resources/views/components';
    $nombres = [];

    foreach (glob($dir.'/*.blade.php') ?: [] as $ruta) {
        $nombres[] = substr(basename($ruta), 0, -strlen('.blade.php'));
    }

    sort($nombres);

    return array_combine($nombres, array_map(fn ($n) => [$n], $nombres));
}
