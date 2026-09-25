<?php

/*
 * Recetas: pantallas completas armadas solo con componentes del paquete.
 * Cada una es recetas/{slug}.blade.php; el catálogo la renderiza y muestra su código.
 */
return [
    'listado' => [
        'titulo' => 'Listado operativo',
        'para' => 'Patentes, feria, licencias: la pantalla que más se repite en el ecosistema.',
        'usa' => ['page-header', 'breadcrumb', 'kpi', 'filter-bar', 'field', 'segmented', 'data-table', 'badge', 'dropdown', 'pagination'],
    ],
    'ficha' => [
        'titulo' => 'Ficha sin salir del listado',
        'para' => 'Abrir el detalle de una fila al costado y confirmar acciones que no se pueden deshacer.',
        'usa' => ['drawer', 'avatar', 'badge', 'tabs', 'timeline', 'modal', 'toast-host'],
    ],
    'tablero' => [
        'titulo' => 'Tablero de resumen',
        'para' => 'Dirección y jefaturas: pocas cifras, la tendencia a la vista y la actividad reciente.',
        'usa' => ['stat', 'card', 'chart-bar', 'chart-donut', 'ring', 'timeline', 'segmented'],
    ],
    'tramite' => [
        'titulo' => 'Trámite en pasos',
        'para' => 'Solicitudes ciudadanas: credencial de discapacidad, ARCOP, licencias.',
        'usa' => ['stepper', 'card', 'alert', 'input', 'select', 'calendar', 'file-dropzone', 'progress', 'accordion', 'button'],
    ],
    'acceso' => [
        'titulo' => 'Verificación en dos pasos',
        'para' => 'El segundo paso del ingreso, dentro de auth-shell.',
        'usa' => ['alert', 'otp-input', 'switch', 'button'],
    ],
    'marco' => [
        'titulo' => 'Marco institucional',
        'para' => 'Lo que hace que cada subdominio se lea como parte del sitio municipal.',
        'usa' => ['gob-bar', 'topbar', 'command-palette', 'empty-state', 'gob-footer'],
    ],
];
