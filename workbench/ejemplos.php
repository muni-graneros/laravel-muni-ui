<?php

/*
|--------------------------------------------------------------------------
| Datos de ejemplo de la vitrina (workbench, nunca se instala en un sistema)
|--------------------------------------------------------------------------
|
| Esto NO es la lista de tarjetas. Las tarjetas salen de recorrer
| resources/views/components/: un componente que no está acá aparece igual,
| renderizado con sus valores por defecto (y sus props obligatorias rellenadas
| con un texto de ejemplo). Este mapa solo le da datos creíbles a los que sin
| ellos no dicen nada, o no se pueden renderizar (props de tipo arreglo o número).
|
| Datos FICTICIOS, escritos a mano en este archivo y nunca sembrados desde el
| maestro de personas (Ley 21.719: una persona real acá sería tratamiento sin
| base de licitud). Los RUT tienen dígito verificador válido para que los
| campos chilenos los acepten, y la prueba lo comprueba.
|
| Cada clave es el nombre de un componente que existe; si se borra el
| componente, tests/VitrinaNavegableTest.php avisa que la clave quedó huérfana.
*/

return [
    'page-header' => '<x-muni::page-header title="Patentes morosas" subtitle="3.412 contribuyentes con deuda vigente" />',
    'stat' => '<x-muni::stat :value="128" label="Solicitudes ingresadas hoy" :delta="12" delta-dir="up" />',
    'kpi' => '<x-muni::kpi :value="3412" label="Patentes morosas" tone="danger" hint="al 6 de septiembre" />',
    'card' => '<x-muni::card title="Solicitud 2026-04871" subtitle="Licencia clase B · ingresada el 18 jul 2026">'
        .'<x-slot:actions><x-muni::button variant="ghost" size="sm">Ver expediente</x-muni::button></x-slot:actions>'
        .'<p style="margin:0;">Examen práctico agendado para el lunes 21 de julio a las 09:00, Dirección de Tránsito.</p>'
        .'</x-muni::card>',
    'accordion' => '<x-muni::accordion :default="0" :items="['
        .'[\'title\' => \'¿Qué documentos necesito para renovar la licencia?\', \'content\' => \'Cédula de identidad vigente, la licencia anterior y el certificado médico del examen.\'],'
        .'[\'title\' => \'¿Cuánto demora el trámite?\', \'content\' => \'Entre 5 y 10 días hábiles desde que se aprueba el examen práctico.\'],'
        .'[\'title\' => \'¿Puedo reagendar mi hora?\', \'content\' => \'Sí, hasta 24 horas antes de la cita, desde la misma solicitud.\'],'
        .']" />',
    'chart-bar' => '<x-muni::chart-bar :data="['
        .'[\'label\' => \'Abr\', \'value\' => 212],'
        .'[\'label\' => \'May\', \'value\' => 248],'
        .'[\'label\' => \'Jun\', \'value\' => 231],'
        .'[\'label\' => \'Jul\', \'value\' => 305],'
        .'[\'label\' => \'Ago\', \'value\' => 342],'
        .'[\'label\' => \'Sep\', \'value\' => 128],'
        .']" />',
    'stepper' => '<x-muni::stepper label="Avance de la solicitud" orientation="vertical" :current="2" :steps="['
        .'[\'label\' => \'Datos del titular\', \'state\' => \'done\'],'
        .'[\'label\' => \'Documentos\', \'hint\' => \'Falta el certificado médico\', \'state\' => \'error\'],'
        .'[\'label\' => \'Examen\', \'hint\' => \'Lun 21 jul, 09:00\'],'
        .'[\'label\' => \'Emisión\'],'
        .']" />',
    'badge' => '<x-muni::badge tone="danger">Vencida</x-muni::badge> <x-muni::badge tone="ok">Al día</x-muni::badge> <x-muni::badge tone="warn">Por vencer</x-muni::badge>',
    'button' => '<x-muni::button>Guardar</x-muni::button> <x-muni::button variant="ghost">Cancelar</x-muni::button> <x-muni::button variant="danger">Anular</x-muni::button>',
    'alert' => '<x-muni::alert tone="warn" title="217 patentes por vencer">Vencen dentro de 30 días.</x-muni::alert>',
    'input' => '<x-muni::input label="Correo de contacto" name="vt_correo" hint="Te avisaremos ahí" error="Falta la arroba." />',
    'rut-input' => '<x-muni::rut-input label="RUT del titular" name="vt_rut" value="12.345.678-5" />',
    'select' => '<x-muni::select label="Tipo de trámite" name="vt_tramite" :options="[\'b\' => \'Licencia clase B\', \'r\' => \'Renovación\']" />',
    'segmented' => '<x-muni::segmented name="vt_estado" label="Filtrar por estado" :options="[\'todos\' => \'Todos\', \'pend\' => \'Pendientes\', \'cerr\' => \'Cerrados\']" value="todos" />',
    'checkbox-group' => '<x-muni::checkbox-group name="vt_requisitos" legend="Requisitos entregados"'
        .' :options="[\'cedula\' => \'Cédula de identidad\', \'domicilio\' => \'Certificado de domicilio\', \'examen\' => \'Examen médico\']"'
        .' :selected="[\'cedula\']" />',
    'combobox' => '<x-muni::combobox name="vt_titular" label="Titular de la solicitud"'
        .' :options="[[\'value\' => \'4821\', \'label\' => \'Ana Soto Miranda\', \'hint\' => \'12.345.678-5\'],'
        .' [\'value\' => \'4822\', \'label\' => \'Luis Pérez Rojas\', \'hint\' => \'9.876.543-3\']]" />',
    'error-summary' => '<x-muni::error-summary :focus="false" :errors="[\'vt_rut\' => \'El RUT no es válido.\', \'vt_fecha\' => \'La fecha es obligatoria.\']" />',
    'description-item' => '<x-muni::description-list label="Datos del titular">'
        .'<x-muni::description-item label="Nombre">Ana Soto Miranda</x-muni::description-item>'
        .'<x-muni::description-item label="RUT" mono>12.345.678-5</x-muni::description-item>'
        .'</x-muni::description-list>',
    'pii' => '<x-muni::pii label="Teléfono" masked="+56 9 •••• 4321" name="vt_telefono" />',
    'progress' => '<x-muni::progress :value="64" label="Avance del trámite" />',
    'pagination' => '<x-muni::pagination :current="3" :total="170" :url="fn (int $p) => \'?pagina=\'.$p" />',
    'breadcrumb' => '<x-muni::breadcrumb :items="[[\'label\' => \'Inicio\', \'url\' => \'#\'], [\'label\' => \'Patentes\']]" />',
    'avatar' => '<x-muni::avatar name="Ana Soto Miranda" />',
    'tabs' => '<x-muni::tabs :tabs="[\'Solicitante\', \'Documentos\']" label="Secciones de la solicitud">'
        .'<x-muni::tab-panel :index="0">Datos del solicitante.</x-muni::tab-panel>'
        .'<x-muni::tab-panel :index="1">Documentos adjuntos.</x-muni::tab-panel></x-muni::tabs>',
    'tab-panel' => '<x-muni::tabs :tabs="[\'Resumen\']" label="Panel suelto"><x-muni::tab-panel :index="0">Contenido del panel.</x-muni::tab-panel></x-muni::tabs>',
    'table-header' => '<x-muni::table-header title="Patentes morosas" :total="3412" :filtered="120" unit="patentes" />',
    'sortable-table' => '<x-muni::sortable-table searchable caption="Patentes morosas"'
        .' :columns="[[\'key\' => \'rut\', \'label\' => \'RUT\'], [\'key\' => \'monto\', \'label\' => \'Monto\']]"'
        .' :rows="[[\'rut\' => \'12.345.678-5\', \'monto\' => \'520.000\', \'_tone\' => \'danger\'], [\'rut\' => \'9.876.543-3\', \'monto\' => \'80.000\']]" />',
    'timeline' => '<x-muni::timeline :items="['
        .'[\'title\' => \'Ingresada\', \'time\' => \'10:04\', \'tone\' => \'info\', \'actor\' => \'Ventanilla Única\'],'
        .'[\'title\' => \'Derivada a Obras\', \'time\' => \'11:20\', \'tone\' => \'ok\'],'
        .'[\'title\' => \'En revisión del director\', \'time\' => \'12:30\', \'tone\' => \'accent\', \'current\' => true],'
        .']" />',
    'nav-menu' => '<x-muni::nav-menu actual="licencias.anular" :items="['
        .'[\'etiqueta\' => \'Escritorio\', \'href\' => \'#\', \'clave\' => \'escritorio\'],'
        .'[\'tipo\' => \'grupo\', \'etiqueta\' => \'Licencias\', \'clave\' => \'licencias\', \'items\' => ['
        .'[\'etiqueta\' => \'Anular licencia\', \'href\' => \'#\', \'clave\' => \'licencias.anular\'],'
        .']],'
        .']" />',
    'nav-grupo' => '<x-muni::nav-grupo titulo="Operaciones"><x-muni::nav-item href="#">Bandeja de entrada</x-muni::nav-item></x-muni::nav-grupo>',
    'dropdown' => '<x-muni::dropdown label="Acciones del giro">'
        .'<x-muni::dropdown-item href="#">Ver expediente</x-muni::dropdown-item>'
        .'<x-muni::dropdown-item href="#">Imprimir orden</x-muni::dropdown-item>'
        .'</x-muni::dropdown>',
    'tooltip' => '<x-muni::tooltip text="Anular el giro de la patente" id="vt-tt">'
        .'<button type="button" class="muni-btn" aria-describedby="vt-tt">Anular</button>'
        .'</x-muni::tooltip>',
    'modal' => '<x-muni::modal id="vt-modal" title="Anular el giro 4821">'
        .'<x-slot:trigger><x-muni::button variant="danger">Anular giro</x-muni::button></x-slot:trigger>'
        .'<p>El giro quedará anulado y se avisará a Rentas.</p>'
        .'</x-muni::modal>',
    'diff-campos' => '<x-muni::diff-campos caption="Cambios en la solicitud 4821" :changes="['
        .'[\'field\' => \'telefono\', \'label\' => \'Teléfono\', \'after\' => \'+56 9 8765 4321\', \'mono\' => true],'
        .'[\'field\' => \'domicilio\', \'label\' => \'Domicilio\', \'before\' => \'Calle Uno 100\', \'after\' => \'Calle Dos 200\'],'
        .']" />',
    'command-palette' => '<x-muni::command-palette :items="['
        .'[\'label\' => \'Bandeja de entrada\', \'url\' => \'#bandeja\', \'group\' => \'Trámites\'],'
        .'[\'label\' => \'Patentes morosas\', \'url\' => \'#patentes\', \'group\' => \'Rentas\'],'
        .']" />',
    'selector-tema' => '<x-muni::selector-tema action="#" value="sistema" />',
    // Fecha LEJANA: con una cercana, el velo de sesión expirada taparía la vitrina entera.
    'sesion-guardia' => '<x-muni::sesion-guardia id="vt-sesion" expira-en="2030-01-01T12:00:00-03:00" renovar-url="#" salir-url="#" ingresar-url="#" duracion="7200" />',
    // Cerrada: abierta atraparía el foco y dejaría la vitrina inerte.
    'pantalla-bloqueo' => '<x-muni::pantalla-bloqueo nombre="Ana Soto Miranda" cargo="Oficina de Partes" accion="#" />',
    'topbar' => '<x-muni::topbar system="Licencias de Conducir" subtitle="Dirección de Tránsito" />',
    'app-shell' => '<x-muni::app-shell system="Atención al Vecino"><p>Contenido de la pantalla.</p></x-muni::app-shell>',
    'plantilla-pantalla' => '<x-muni::plantilla-pantalla title="Patentes morosas"><p>Contenido de la pantalla.</p></x-muni::plantilla-pantalla>',
];
