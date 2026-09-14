<?php

use Illuminate\Support\Facades\Blade;

/*
 * Genera la vitrina: una página por tema con los componentes REALES renderizados.
 *
 * Por qué es una prueba y no un script suelto: es el único sitio del paquete donde
 * Blade está arrancado. Y por qué existe: hasta ahora la reja de accesibilidad
 * (`npm run a11y`) solo podía medir las demos de `demo/`, que son HTML escrito a
 * mano y que ya se comprobó que se desviaron de los tokens del paquete. Es decir,
 * medía una copia, no los componentes. Esto cierra esa brecha: lo que se abre en el
 * navegador es lo que el paquete emite de verdad.
 *
 * La salida va a `build/vitrina/`, que está ignorada por git. No es un artefacto
 * que se publique: es el sujeto de la medición.
 *
 * La vitrina CARGA ALPINE 3 y abre a mano los estados que nacen cerrados. Sin eso no
 * emitía un solo <script>: nada interactivo hidrataba, `sortable-table` no llegaba
 * siquiera a tener cabecera ni filas —viven dentro de <template x-for>— y la reja
 * medía 165 textos. Con Alpine e hidratación mide 181. De dónde sale Alpine y qué se
 * abre está en `fuenteDeAlpine()` y `abridorDeVitrina()`, acá abajo.
 *
 *     ./vendor/bin/pest --filter=GeneraVitrina
 *     npm run a11y -- build/vitrina/claro.html build/vitrina/oscuro.html
 */

/**
 * Un ejemplo por componente. La clave es el nombre; el valor, el Blade a renderizar.
 * No están los 55: están los que tienen superficie visible y estado que medir.
 * Los armazones se excluyen porque emiten su propio <html> y no se pueden anidar.
 */
function ejemplosDeVitrina(): array
{
    return [
        'skip-link' => '<x-muni::skip-link />',
        'page-header' => '<x-muni::page-header title="Patentes morosas" subtitle="3.412 contribuyentes con deuda vigente" />',
        'stat' => '<x-muni::stat :value="128" label="Ingresadas hoy" :delta="12" delta-dir="up" />',
        'kpi' => '<x-muni::kpi :value="3412" label="Morosas" tone="danger" hint="al 6 de septiembre" />',
        'badge' => '<x-muni::badge tone="danger">Vencida</x-muni::badge> <x-muni::badge tone="ok">Al día</x-muni::badge> <x-muni::badge tone="warn">Por vencer</x-muni::badge>',
        'button' => '<x-muni::button>Guardar</x-muni::button> <x-muni::button variant="ghost">Cancelar</x-muni::button>',
        // LOS CINCO TONOS, no solo warn. Cada uno pinta su propio par fg/bg, así que
        // con una sola alerta se medía un tono de cinco: los otros cuatro estuvieron
        // entre 4,15 y 4,41 sobre su fondo hasta que se midieron a mano.
        'alert' => '<x-muni::alert tone="ok" title="Solicitud aprobada">Queda en la bitácora.</x-muni::alert>'
            .'<x-muni::alert tone="warn" title="217 patentes por vencer">Vencen dentro de 30 días.</x-muni::alert>'
            .'<x-muni::alert tone="danger" title="Giro rechazado">Revisa el RUT del titular.</x-muni::alert>'
            .'<x-muni::alert tone="info" title="Sistema en mantención">El sábado 12, de 08:00 a 12:00.</x-muni::alert>'
            .'<x-muni::alert title="Tono no declarado">Cae en info, y también se mide.</x-muni::alert>',
        'input' => '<x-muni::input label="RUT del titular" name="rut" hint="Formato 12.345.678-9" error="El dígito verificador no corresponde." />',
        'select' => '<x-muni::select label="Tipo de trámite" name="tramite" :options="[\'a\' => \'Licencia clase B\', \'b\' => \'Renovación\']" hint="Elige el trámite" />',
        'textarea' => '<x-muni::textarea label="Descripción del requerimiento" name="detalle" hint="Cuenta qué pasó" :maxlength="500" />',
        'checkbox' => '<x-muni::checkbox label="Acepto el tratamiento de mis datos" name="consentimiento" description="Ley 21.719, base de licitud: consentimiento." />',
        'switch' => '<x-muni::switch label="Notificarme por correo" name="avisos" description="Cuando cambie el estado" />',
        'segmented' => '<x-muni::segmented name="estado" label="Filtrar por estado" :options="[\'todos\' => \'Todos\', \'pend\' => \'Pendientes\', \'cerr\' => \'Cerrados\']" value="todos" />',
        'file-dropzone' => '<x-muni::file-dropzone name="informe" label="Adjunta el informe médico" />',
        'error-summary' => '<x-muni::error-summary :errors="[\'rut\' => \'El RUT no es válido.\', \'fecha\' => \'La fecha es obligatoria.\']" />',
        'announcer' => '<x-muni::announcer />',
        'empty-state' => '<x-muni::empty-state title="Sin resultados" description="Ningún contribuyente coincide con el filtro." />',
        'progress' => '<x-muni::progress :value="64" label="Avance del trámite" />',
        // Varios tonos a la vez porque la etiqueta de estado es TEXTO visible y hay
        // un par de colores que medir por tono. Un ítem con `current`, otro con
        // `actor` y otro con `detail`: el <summary> es una parada de tabulación
        // nueva, y sin un ítem que lo emita la reja no mide ni su foco ni su
        // contraste. El <details> nace colapsado, pero el abridor de la vitrina lo
        // abre antes de medir: el texto del cambio («Domicilio: … → …») es lo que
        // el funcionario lee cuando despliega, y hasta ahora no se medía nunca.
        'timeline' => '<x-muni::timeline :items="['
            .'[\'title\' => \'Ingresada\', \'time\' => \'10:04\', \'datetime\' => \'2026-09-08T10:04:00-03:00\', \'tone\' => \'info\', \'actor\' => \'Ventanilla Única\'],'
            .'[\'title\' => \'Derivada a Obras\', \'time\' => \'11:20\', \'tone\' => \'ok\', \'description\' => \'Con el certificado de dominio adjunto.\'],'
            .'[\'title\' => \'Observada por Obras\', \'time\' => \'15:47\', \'tone\' => \'warn\', \'actor\' => \'C. Bugueño\','
            .' \'detail\' => \'Domicilio: Calle Uno 100 → Calle Dos 200\'],'
            .'[\'title\' => \'Rechazada por falta de antecedentes\', \'time\' => \'09:12\', \'tone\' => \'danger\'],'
            .'[\'title\' => \'En revisión del director\', \'time\' => \'12:30\', \'tone\' => \'accent\', \'current\' => true],'
            .'[\'title\' => \'Anulada por duplicado\', \'time\' => \'12:45\', \'tone\' => \'muted\'],'
            .']" />',
        'breadcrumb' => '<x-muni::breadcrumb :items="[[\'label\' => \'Inicio\', \'url\' => \'#\'], [\'label\' => \'Patentes\']]" />',
        // Con `url` los números son enlaces de verdad. Sin él salen como texto, y la
        // reja mediría un componente que nadie usa así en producción.
        'pagination' => '<x-muni::pagination :current="3" :total="170" :url="fn (int $p) => \'?pagina=\'.$p" />',
        'skeleton' => '<x-muni::skeleton width="60%" /> <x-muni::skeleton height="80px" />',
        'avatar' => '<x-muni::avatar name="Cesar Bugueño" />',
        'tabs' => '<x-muni::tabs :tabs="[\'Solicitante\', \'Documentos\']" label="Secciones de la solicitud">'
            .'<x-muni::tab-panel :index="0">Datos del solicitante.</x-muni::tab-panel>'
            .'<x-muni::tab-panel :index="1">Documentos adjuntos.</x-muni::tab-panel></x-muni::tabs>',
        'table-header' => '<x-muni::table-header title="Patentes morosas" :total="3412" :filtered="120" unit="patentes" />',
        'rut-input' => '<x-muni::rut-input label="RUT del titular" name="rut" />',
        'date-input' => '<x-muni::date-input label="Vence el" name="vence" />',
        'sortable-table' => '<x-muni::sortable-table searchable caption="Patentes morosas"'
            .' :columns="[[\'key\' => \'rut\', \'label\' => \'RUT\'], [\'key\' => \'monto\', \'label\' => \'Monto\']]"'
            .' :rows="[[\'rut\' => \'12.345.678-9\', \'monto\' => \'520.000\', \'_tone\' => \'danger\'], [\'rut\' => \'9.876.543-2\', \'monto\' => \'80.000\']]" />',
        // Los dos que siguen van AL FINAL a propósito. El reparto par/impar de la
        // rejilla decide qué tarjeta cae sobre --muni-surface-3, y en la posición
        // par está `input`, que es donde el mensaje de error falló contraste.
        // Meterlos en medio correría ese reparto y dejaría de medirse justo el
        // caso por el que se puso la regla. Al final, además, cae uno en cada
        // superficie: diff-campos sobre la superficie normal y hoja sobre la 3.
        //
        // Los tres estados juntos —y el cuarto, «sin cambios»— porque cada uno
        // pinta su propio par fg/bg en la etiqueta y en el <ins>/<del>: con una
        // sola fila se mediría un tono de cuatro. Los valores son strings ya
        // redactados, que es lo único que el componente acepta.
        'diff-campos' => '<x-muni::diff-campos caption="Cambios en la solicitud 4821" :changes="['
            .'[\'field\' => \'telefono\', \'label\' => \'Teléfono\', \'after\' => \'+56 9 8765 4321\', \'mono\' => true],'
            .'[\'field\' => \'domicilio\', \'label\' => \'Domicilio\', \'before\' => \'Calle Uno 100\', \'after\' => \'Calle Dos 200\'],'
            .'[\'field\' => \'correo\', \'label\' => \'Correo\', \'before\' => \'antiguo@ejemplo.cl\'],'
            .'[\'field\' => \'rut\', \'label\' => \'RUT\', \'before\' => \'12.345.678-9\', \'after\' => \'12.345.678-9\', \'mono\' => true],'
            .']">Registro conservado 5 años. La exportación queda en la bitácora.</x-muni::diff-campos>',
        // Con folio, leyenda de verificación y las tres ranuras: sin ellas la
        // mitad del componente —el pie, los recuadros de datos y la firma— no
        // llega al DOM y la reja no mide nada de eso.
        'hoja' => '<x-muni::hoja id="vitrina-hoja" unit="Dirección de Tránsito"'
            .' type="Acta de fiscalización" folio="A-2026-4821" date="8 de septiembre de 2026"'
            .' verification="Verifica el folio en la Oficina de Partes, Manuel Rodríguez 545.">'
            .'<x-slot:emisor><strong>Fiscalizador</strong><br>Juan Pérez · Inspección Municipal</x-slot:emisor>'
            .'<x-slot:titular><strong>Titular</strong><br>Ana Soto · Patente comercial 1.204</x-slot:titular>'
            .'<p>Se constata el funcionamiento del local fuera del horario autorizado '
            .'en el decreto alcaldicio 118/2026. Se levanta acta y se cita al Juzgado de Policía Local.</p>'
            .'<x-slot:firma><span>Firma del fiscalizador</span></x-slot:firma>'
            .'</x-muni::hoja>',
        // Los tres nacen cerrados, y el medidor salta todo nodo de alto 0: hasta que
        // la vitrina cargó Alpine, de estos solo se medía el disparador, el campo,
        // la etiqueta y la ayuda. Ahora el abridor (ver `abridorDeVitrina`) abre la
        // lista del combobox —con la primera opción resaltada—, la burbuja del
        // tooltip y el panel del popover ANTES de que la reja mida, y si alguno se
        // queda de alto 0 la reja FALLA en vez de dar verde midiendo de menos.
        'combobox' => '<x-muni::combobox name="vitrina_titular" label="Titular de la solicitud"'
            .' selectedLabel="Ana Soto Miranda" value="4821"'
            .' hint="Escribe el RUT o el nombre; se muestran hasta 20 resultados."'
            .' :options="[[\'value\' => \'4821\', \'label\' => \'Ana Soto Miranda\', \'hint\' => \'12.345.678-9\']]" />',
        'popover' => '<x-muni::popover label="Filtros de la bandeja">'
            .'<form method="get"><p>Estado y fecha del requerimiento.</p></form>'
            .'</x-muni::popover>',
        // dropdown tenía aria-expanded sobre un <div> sin rol —axe critical— y nadie
        // lo veía porque no estaba acá. Van las dos formas del slot: la que el
        // componente envuelve en un <button> propio y la que trae su control.
        // El menú declarativo y su grupo plegable: sin esto la reja no mide ni el
        // ítem activo ni el rótulo del grupo, que son texto nuevo.
        'nav-menu' => '<x-muni::nav-menu actual="licencias.anular" :items="['
            .'[\'etiqueta\' => \'Escritorio\', \'href\' => \'#\', \'clave\' => \'escritorio\'],'
            .'[\'tipo\' => \'grupo\', \'etiqueta\' => \'Licencias\', \'clave\' => \'licencias\', \'items\' => ['
            .'[\'etiqueta\' => \'Anular licencia\', \'href\' => \'#\', \'clave\' => \'licencias.anular\'],'
            .'[\'etiqueta\' => \'Exámenes médicos\', \'href\' => \'#\', \'clave\' => \'licencias.examenes\'],'
            .']],'
            .'[\'etiqueta\' => \'Patentes morosas\', \'href\' => \'#\', \'clave\' => \'patentes\', \'badge\' => 12, \'badgeLabel\' => \'12 pendientes\'],'
            .']" />',
        'dropdown' => '<x-muni::dropdown label="Acciones del giro">'
            .'<x-muni::dropdown-item href="#">Ver expediente</x-muni::dropdown-item>'
            .'<x-muni::dropdown-item href="#">Imprimir orden</x-muni::dropdown-item>'
            .'</x-muni::dropdown>',
        'tooltip' => '<x-muni::tooltip text="Anular el giro de la patente" id="vitrina-tt">'
            .'<button type="button" class="muni-btn" aria-label="Anular el giro de la patente"'
            .' aria-describedby="vitrina-tt">Anular</button>'
            .'</x-muni::tooltip>',
        // `sidebar` EN MODO COLUMNA, y por eso sí puede ir acá: por encima de su
        // punto de quiebre no atrapa el foco ni aísla nada —atraparlo dejaría al
        // funcionario encerrado en el menú—, así que convive con las otras
        // tarjetas sin taparlas ni volverlas `aria-hidden`. El modo SUPERPUESTO,
        // que es el que sí atrapa, vive en su propia página (ver
        // `ejemplosDeDialogo()`). Va al final para no correr el reparto par/impar
        // de las tarjetas que ya estaban: quién cae sobre `--muni-surface-3`
        // decide qué defecto se mide, y eso no se toca al agregar piezas.
        'sidebar' => '<x-muni::sidebar id="vitrina-sidebar-columna" label="Navegación del sistema">'
            .'<x-muni::nav-section title="Trámites">'
            .'<x-muni::nav-item href="#" active badge="12">Bandeja de entrada</x-muni::nav-item>'
            .'<x-muni::nav-item href="#" badge="3">Observadas</x-muni::nav-item>'
            .'</x-muni::nav-section>'
            .'<x-muni::nav-section title="Administración">'
            .'<x-muni::nav-item href="#">Usuarios</x-muni::nav-item>'
            .'</x-muni::nav-section>'
            .'</x-muni::sidebar>',
        // EL ESTADO DE ERROR de los tres controles que lo pintan desde el servidor.
        // `select` y `textarea` ponen el borde de error con estilo EN LÍNEA, y
        // `switch` ni siquiera cambia la pista: la pasada de no-texto de la reja
        // sondea el error poniendo `aria-invalid` en el navegador, y en estos tres
        // no se mueve nada. Sin una instancia que ya nazca en error, ese estado no
        // se medía en ningún lado —la reja lo decía en su tabla de cobertura— y
        // D11 (el borde en error menos visible que el normal) podía volver en
        // cualquiera de los tres sin que nadie lo viera. Los de arriba se quedan
        // en estado normal: cambiarlos a error borraría la medición del otro estado.
        //
        // Nombres de campo propios: con el mismo `name` que las instancias normales
        // saldrían ids duplicados y axe levantaría un `duplicate-id` del banco.
        // Van al final por lo mismo que `sidebar`: no correr el reparto par/impar.
        // La clave lleva `#error` porque es una VARIANTE de la pieza, no otra
        // pieza: el rótulo la muestra como `<x-muni::select> en error`.
        'select#error' => '<x-muni::select label="Tipo de trámite" name="tramite_observado"'
            .' :options="[\'a\' => \'Licencia clase B\', \'b\' => \'Renovación\']"'
            .' hint="Elige el trámite" :error="\'Elige un trámite de la lista.\'" />',
        'textarea#error' => '<x-muni::textarea label="Descripción del requerimiento" name="detalle_observado"'
            .' hint="Cuenta qué pasó" :maxlength="500" :error="\'La descripción es obligatoria.\'" />',
        'switch#error' => '<x-muni::switch label="Acepto recibir notificaciones" name="avisos_observado"'
            .' description="Obligatorio para seguir el trámite en línea" :error="\'Debes aceptar para continuar.\'" />',
        // Entradas devueltas por los agentes del workflow de cierre del backlog.
        'calendar' => '<x-muni::calendar name="fecha_cita" min="2026-09-05" value="2026-09-12" />',
        'command-palette' => '<x-muni::command-palette :items="['
            .'[\'label\' => \'Bandeja de entrada\', \'url\' => \'#bandeja\', \'group\' => \'Trámites\'],'
            .'[\'label\' => \'Patentes morosas\', \'url\' => \'#patentes\', \'group\' => \'Rentas\'],'
            .'[\'label\' => \'Usuarios del sistema\', \'url\' => \'#usuarios\', \'group\' => \'Administración\'],'
            .']" />',
        'modal#confirmacion' => '<x-muni::modal id="vitrina-confirmacion" title="¿Anular la solicitud 2024-118?" role="alertdialog" :dismissable="false" initial-focus="cancelar">'
    .'<x-slot:trigger><x-muni::button variant="danger">Anular solicitud</x-muni::button></x-slot:trigger>'
    .'<p>La solicitud de <b>Rosa Contreras</b> (folio 2024-118) quedará anulada y se avisará a Obras. '
    .'No se puede deshacer desde el panel.</p>'
    .'<x-slot:footer><x-muni::button variant="danger">Anular solicitud</x-muni::button></x-slot:footer>'
    .'</x-muni::modal>',
        'spinner' => '<p>Generar padrón <x-muni::spinner size="16px" /> <x-muni::spinner /> <x-muni::spinner size="32px" /></p>',
        'busy-region' => '<x-muni::busy-region target="rut" loading="Buscando en el maestro de personas…" status="1 persona encontrada"><p>Ana Soto Miranda · 12.345.678-9</p></x-muni::busy-region> <x-muni::busy-region target="rut" loading="Buscando en el maestro de personas…" :busy="true"><p>Ana Soto Miranda · 12.345.678-9</p></x-muni::busy-region>',
        'selector-tema' => '<x-muni::selector-tema action="/preferencias/tema" value="sistema" />',
        // Fecha LEJANA a propósito: con una real, en cuanto pasara el velo opaco taparía la vitrina
        // entera y la reja mediría solo el bloqueo. Acá se mide el estado de reposo (raíz teleportada,
        // región viva vacía, diálogo oculto); los estados visibles los mide su propio banco
        // (build/sesion-guardia/, tests/navegador/guardia-de-sesion.py) en los dos temas.
        'sesion-guardia' => '<x-muni::sesion-guardia id="vitrina-sesion" expira-en="2030-01-01T12:00:00-03:00" renovar-url="#" salir-url="#" ingresar-url="#" duracion="7200" />',
    ];
}

/**
 * El rótulo visible de una tarjeta: `<x-muni::select>`, o `<x-muni::select> en
 * error` para la variante `select#error`. Ya escapado para ir dentro de un <h2>.
 */
function rotuloDePieza(string $nombre): string
{
    [$componente, $variante] = array_pad(explode('#', $nombre, 2), 2, null);

    return '&lt;x-muni::'.$componente.'&gt;'.($variante !== null ? ' en '.$variante : '');
}

/**
 * Los CUATRO que atrapan el foco, uno por página y por un motivo medido.
 *
 * `modal`, `drawer`, `command-palette` y `sidebar` (en su modo superpuesto) usan
 * `x-trap.inert` del plugin Focus de Alpine. Ese modificador no es cosmético: al
 * abrirse, el plugin recorre el árbol desde el diálogo hasta <body> y estampa
 * `aria-hidden="true"` en TODO lo que quede a los lados. Consecuencias medidas
 * sobre la propia reja, y por eso estos cuatro NO pueden compartir página con las
 * tarjetas de `ejemplosDeVitrina()`:
 *
 *   · el medidor de contraste trata lo que cuelga de un `aria-hidden="true"` como
 *     decorativo y le baja el umbral de 4,5:1 a 3:1 (es la política del docstring
 *     de `scripts/a11y-check.py`). Abrir un modal sobre la vitrina entera
 *     degradaría de golpe el umbral de todas las demás tarjetas: la reja seguiría
 *     verde midiendo los mismos textos con la vara más blanda. Un retroceso en
 *     silencio, que es justo lo que esta vitrina existe para no permitir.
 *   · axe-core no entra en un subárbol `aria-hidden`: dejaría de revisar todo lo
 *     demás de la página.
 *   · y entre ellos se aíslan mutuamente —los tres se teletransportan a <body> y
 *     son hermanos—, así que dos abiertos a la vez se tapan el uno al otro.
 *
 * Una página por diálogo, entonces. Cada una lleva el disparador real del
 * componente (el `trigger`), que es superficie visible en producción, y el abridor
 * la abre por la API pública y comprueba que la trampa AISLÓ de verdad: si el
 * plugin Focus no llegó, `x-trap` es una directiva desconocida, Alpine la ignora
 * sin decir nada y el diálogo se abre igual pero sin atrapar el foco. Sin esa
 * comprobación la reja daría verde sobre un diálogo que no atrapa nada.
 */
function ejemplosDeDialogo(): array
{
    return [
        'modal' => '<x-muni::modal id="vitrina-modal" title="Anular el giro 4821">'
            .'<x-slot:trigger><x-muni::button>Anular giro</x-muni::button></x-slot:trigger>'
            .'<p>El giro queda sin efecto y la anulación se registra en la bitácora '
            .'con tu nombre y la hora. No se puede deshacer desde el panel.</p>'
            .'<x-slot:footer><x-muni::button variant="ghost">Cancelar</x-muni::button>'
            .'<x-muni::button>Anular el giro</x-muni::button></x-slot:footer>'
            .'</x-muni::modal>',
        'drawer' => '<x-muni::drawer id="vitrina-drawer" title="Solicitud 4821 — Ana Soto">'
            .'<x-slot:trigger><x-muni::button variant="ghost">Ver detalle</x-muni::button></x-slot:trigger>'
            .'<p>Ingresada el 8 de septiembre por Ventanilla Única. Derivada a la '
            .'Dirección de Obras con el certificado de dominio adjunto.</p>'
            .'<x-muni::badge tone="warn">Por vencer</x-muni::badge>'
            .'<x-slot:footer><x-muni::button variant="ghost">Cerrar</x-muni::button>'
            .'<x-muni::button>Derivar</x-muni::button></x-slot:footer>'
            .'</x-muni::drawer>',
        'command-palette' => '<x-muni::command-palette :items="['
            .'[\'label\' => \'Bandeja de entrada\', \'url\' => \'#bandeja\', \'group\' => \'Trámites\'],'
            .'[\'label\' => \'Patentes morosas\', \'url\' => \'#patentes\', \'group\' => \'Rentas\'],'
            .'[\'label\' => \'Usuarios del sistema\', \'url\' => \'#usuarios\', \'group\' => \'Administración\'],'
            .']">'
            .'<x-slot:trigger><x-muni::button variant="ghost">Buscar (Ctrl+K)</x-muni::button></x-slot:trigger>'
            .'</x-muni::command-palette>',
        // La MISMA lateral de la vitrina, pero con el punto de quiebre por encima
        // del ancho del navegador de la reja (1280): así entra en modo superpuesto
        // —velo, trampa de foco, Escape y devolución del foco— que es el modo que
        // nunca se había medido. Se fuerza por la prop pública `breakpoint`, no
        // encogiendo la ventana: el ancho de la reja es suyo y no se toca desde acá.
        'sidebar' => '<x-muni::sidebar id="vitrina-sidebar" breakpoint="2000" label="Navegación principal">'
            .'<x-muni::nav-section title="Trámites">'
            .'<x-muni::nav-item href="#" active badge="12">Bandeja de entrada</x-muni::nav-item>'
            .'<x-muni::nav-item href="#" badge="3">Observadas</x-muni::nav-item>'
            .'</x-muni::nav-section>'
            .'<x-muni::nav-section title="Administración">'
            .'<x-muni::nav-item href="#">Usuarios</x-muni::nav-item>'
            .'</x-muni::nav-section>'
            .'</x-muni::sidebar>',
    ];
}

/**
 * El código de Alpine 3 que se hornea en la vitrina, o null si no hay copia en disco.
 *
 * Por qué hace falta: los componentes del paquete dan por sentado que Alpine viaja
 * dentro del bundle de Livewire, así que el paquete no publica ninguno. Sin él la
 * vitrina no emite un solo <script>: la lista del combobox, la burbuja del tooltip,
 * el panel del popover y —esto es lo peor— TODA la cabecera y el cuerpo de
 * `sortable-table`, que viven dentro de <template x-for>, nunca llegan al DOM. La
 * reja medía lo que el funcionario ve antes de tocar nada, y solo eso.
 *
 * De dónde sale, en el mismo orden que `scripts/a11y-check.py` resuelve axe-core:
 *   1. $MUNI_ALPINE_JS      (una ruta explícita, como el `--axe` de la reja)
 *   2. node_modules/        (`npm install`; alpinejs es dependencia de desarrollo)
 *   3. scripts/.cache/      (copia cacheada, ignorada por git como la de axe)
 *
 * Nunca de un CDN: la reja corre sin red y una vitrina que dependa de internet deja
 * de ser un candado en cuanto se cae la conexión. Si no hay copia, la vitrina se
 * genera igual pero SIN hidratar y se dice en voz alta: es preferible a un fallo de
 * la suite en una máquina que no corrió `npm install`. Quien pierde de verdad es la
 * reja, y por eso `a11y-check.py` marca FALLA si la página pedía Alpine y no llegó.
 */
function fuenteDeAlpine(): ?string
{
    $candidatos = [];

    if ($ruta = getenv('MUNI_ALPINE_JS')) {
        $candidatos[] = $ruta;
    }

    $candidatos[] = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    // El glob del núcleo NO puede tragarse la copia del plugin: los dos archivos
    // viven en la misma caché y `alpine-*.min.js` casa también con
    // `alpine-focus-3.17.2.min.js`. Cargar el plugin donde va el núcleo deja la
    // página sin Alpine y con un «Alpine is not defined» que nadie lee.
    foreach (glob(__DIR__.'/../scripts/.cache/alpine-*.min.js') ?: [] as $cacheada) {
        if (! str_contains(basename($cacheada), 'alpine-focus-')) {
            $candidatos[] = $cacheada;
        }
    }

    return primeraFuenteEnDisco($candidatos);
}

/**
 * El plugin Focus (`@alpinejs/focus`) horneado, o null si no hay copia en disco.
 *
 * Por qué hace falta: `modal`, `drawer`, `command-palette` y `sidebar` usan
 * `x-trap.inert.noscroll`, que es una directiva del PLUGIN, no del núcleo. Y acá
 * está el detalle que obliga a comprobarlo en el navegador: si el plugin no está,
 * Alpine no se queja —una directiva desconocida se ignora en silencio—, el
 * diálogo se abre igual porque el `x-show` es del núcleo, y la reja mediría un
 * diálogo que NO atrapa el foco dando verde. Por eso el abridor no se conforma
 * con que el panel tenga alto: exige la prueba de que la trampa aisló el resto
 * de la página (ver `abridorDeVitrina`).
 *
 * Se resuelve igual que el núcleo —$MUNI_ALPINE_FOCUS_JS, node_modules,
 * scripts/.cache— y NUNCA de un CDN, por lo mismo: la reja corre sin red.
 *
 * Va SIEMPRE ANTES del núcleo. Es como lo pide Alpine: los plugins se registran
 * sobre `window.Alpine` antes de que el núcleo llame a `start()`, y el build de
 * CDN del núcleo arranca solo en un microtask de su propio <script>. Al revés, el
 * plugin llegaría tarde y `x-trap` no existiría al hidratar.
 */
function fuenteDeFocus(): ?string
{
    $candidatos = [];

    if ($ruta = getenv('MUNI_ALPINE_FOCUS_JS')) {
        $candidatos[] = $ruta;
    }

    $candidatos[] = __DIR__.'/../node_modules/@alpinejs/focus/dist/cdn.min.js';

    foreach (glob(__DIR__.'/../scripts/.cache/alpine-focus-*.min.js') ?: [] as $cacheada) {
        $candidatos[] = $cacheada;
    }

    return primeraFuenteEnDisco($candidatos);
}

function primeraFuenteEnDisco(array $candidatos): ?string
{
    foreach ($candidatos as $candidato) {
        if (is_file($candidato)) {
            return (string) file_get_contents($candidato);
        }
    }

    return null;
}

/**
 * El abridor: hidrata no basta, hay que ABRIR.
 *
 * Todo lo interactivo del paquete nace cerrado, y el medidor de contraste salta
 * cualquier nodo de alto 0. Con Alpine y sin esto se ganaría `sortable-table`
 * entero y nada más. Así que después de `alpine:initialized` se abre a mano lo que
 * el funcionario abre con el ratón, y se deja abierto mientras la reja mide.
 *
 * Se abre por la API pública del componente (`Alpine.$data(...)` y los métodos que
 * el propio Blade declara), no manoseando clases ni estilos: si mañana el
 * componente cambia de manera de abrirse, esto se rompe en vez de mentir.
 *
 * Además deja constancia en `window.__vitrinaEstado` de qué quedó abierto y con qué
 * alto, y solo entonces marca `data-vitrina-lista="1"` en el <html>. Ese es el
 * apretón de manos que `a11y-check.py` espera antes de medir: sin él la reja
 * mediría a mitad de la hidratación y el resultado dependería del reloj.
 */
function abridorDeVitrina(): string
{
    return <<<'JS'
    (() => {
        const estado = { alpine: null, xdata: 0, dialogo: null, abiertos: [], fallos: [] };

        /* Qué diálogo espera ESTA página, o null si es la vitrina general. Lo
           declara el <html>, así que una página de diálogo que se quede sin su
           componente no pasa de largo: abajo se exige que abra. */
        const dialogo = document.documentElement.getAttribute('data-vitrina-dialogo');
        estado.dialogo = dialogo;

        /* No basta con «tiene alto»: la lateral cerrada mide 720px de alto y está
           fuera de la pantalla por translateX(-100%), y un panel con
           visibility:hidden u opacidad 0 tampoco lo mide el medidor de contraste.
           Se exige lo mismo que exige la reja para contar un texto: visible, con
           caja, y dentro del ancho de la ventana. */
        const anota = (nombre, el) => {
            if (! el) { estado.fallos.push(nombre + ': no existe en el DOM, no se está midiendo'); return; }
            const cs = getComputedStyle(el);
            const caja = el.getBoundingClientRect();
            const alto = Math.round(caja.height);
            if (cs.visibility === 'hidden' || cs.display === 'none' || parseFloat(cs.opacity) === 0) {
                estado.fallos.push(nombre + ': está en el DOM pero oculto (' + cs.visibility + '/' + cs.display
                    + '/opacidad ' + cs.opacity + '), no se está midiendo');
                return;
            }
            if (caja.right <= 0 || caja.left >= window.innerWidth) {
                estado.fallos.push(nombre + ': quedó fuera de la pantalla (izquierda ' + Math.round(caja.left)
                    + 'px), no se está midiendo');
                return;
            }
            if (alto <= 0) { estado.fallos.push(nombre + ': quedó en 0px, no se está midiendo'); return; }
            estado.abiertos.push(nombre + ' ' + alto + 'px');
        };

        const conCuidado = (nombre, fn) => {
            try { fn(); } catch (error) { estado.fallos.push(nombre + ': ' + error.message); }
        };

        /* La raíz Alpine de una tarjeta, por su nombre de pieza. Se abre por la API
           pública del componente (`Alpine.$data(raiz)` y los métodos que declara su
           propio Blade), nunca tocando clases ni estilos: si el componente cambia
           de forma de abrirse, esto se rompe en vez de mentir. */
        const raizDe = (pieza) => {
            const el = document.querySelector('[data-pieza="' + pieza + '"] [x-data]');
            if (! el) { throw new Error('no hay raíz [x-data] en la tarjeta'); }
            return window.Alpine.$data(el);
        };

        /* Los cuatro que atrapan el foco: cómo se abre cada uno y qué tiene que
           quedar midiéndose. Uno por página: ver `ejemplosDeDialogo()`. */
        const DIALOGOS = {
            'modal': {
                abre: () => { raizDe('modal').open = true; },
                mide: () => {
                    anota('modal/panel', document.querySelector('#vitrina-modal'));
                    anota('modal/título', document.querySelector('#vitrina-modal-title'));
                    anota('modal/pie', document.querySelector('#vitrina-modal footer'));
                },
            },
            'drawer': {
                abre: () => { raizDe('drawer').open = true; },
                mide: () => {
                    anota('drawer/panel', document.querySelector('[role="dialog"][aria-labelledby="vitrina-drawer-title"]'));
                    anota('drawer/título', document.querySelector('#vitrina-drawer-title'));
                    anota('drawer/pie', document.querySelector('[role="dialog"][aria-labelledby="vitrina-drawer-title"] footer'));
                },
            },
            'command-palette': {
                /* `show()` es el método que el propio componente expone y el que
                   llama su disparador y el atajo Ctrl+K. */
                abre: () => { raizDe('command-palette').show(); },
                mide: () => {
                    anota('command-palette/panel', document.querySelector('[role="dialog"][aria-label="Paleta de comandos"]'));
                    /* Las filas viven dentro de un <template x-for>: sin Alpine no
                       llegan al DOM y sin abrir no se pintan. */
                    anota('command-palette/resultado', document.querySelector('a[href="#bandeja"]'));
                    anota('command-palette/grupo del resultado', document.querySelector('a[href="#patentes"] span:last-child'));
                },
            },
            'sidebar': {
                /* Por el evento `muni-sidebar`, que es el contrato que el propio
                   componente documenta para el hamburguesa del armazón. */
                abre: () => {
                    const aside = document.querySelector('#vitrina-sidebar');
                    if (! aside) { throw new Error('no está la lateral en la página'); }
                    const datos = window.Alpine.$data(aside);
                    if (! datos.overlay) { throw new Error('no entró en modo superpuesto: el punto de quiebre no se aplicó'); }
                    window.dispatchEvent(new CustomEvent('muni-sidebar'));
                },
                mide: () => {
                    anota('sidebar/panel superpuesto', document.querySelector('#vitrina-sidebar'));
                    anota('sidebar/ítem activo', document.querySelector('#vitrina-sidebar [aria-current="page"]'));
                    anota('sidebar/rótulo de sección', document.querySelector('#vitrina-sidebar .muni-sb__inner > div > div'));
                },
            },
        };

        /* Cuántos nodos hay aislados por una trampa de foco. `x-trap.inert` del
           plugin Focus recorre el árbol desde el diálogo hasta <body> y estampa
           `aria-hidden="true"` en todo lo que queda a los lados: ese recuento es
           la PRUEBA observable de que el plugin llegó y de que la trampa se armó.
           Sin plugin, `x-trap` es una directiva desconocida —Alpine las ignora sin
           decir nada—, el diálogo se abre igual porque el x-show es del núcleo, y
           la reja daría verde sobre un diálogo que no atrapa el foco. */
        const aislados = () => document.querySelectorAll('[aria-hidden="true"]').length;
        let aisladosAntes = 0;

        const abre = () => {
            estado.alpine = (window.Alpine && window.Alpine.version) || null;
            estado.xdata = document.querySelectorAll('[x-data]').length;
            aisladosAntes = aislados();

            if (dialogo) {
                const caso = DIALOGOS[dialogo];
                if (! caso) { estado.fallos.push('la página declara el diálogo «' + dialogo + '» y el abridor no sabe abrirlo'); return; }
                conCuidado(dialogo, caso.abre);
                return;
            }

            /* La lista del combobox, y con la primera opción resaltada: el fondo del
               resaltado y el aria-activedescendant son estado propio, y nunca se han
               medido porque solo existen con la lista abierta. */
            conCuidado('combobox', () => {
                const combo = document.querySelector('.muni-combo');
                if (! combo) { return; }
                const datos = window.Alpine.$data(combo);
                datos.abrir();
                datos.activo = 0;
            });

            /* La burbuja del tooltip: la que salía como una tira vertical de 25x367
               con las palabras partidas letra a letra. */
            conCuidado('tooltip', () => {
                const tt = document.querySelector('.muni-tt');
                if (tt) { window.Alpine.$data(tt).abrir(); }
            });

            /* El popover lleva el estado en el atributo NATIVO, no en Alpine: se abre
               como lo abre el navegador y Alpine sincroniza el aria-expanded solo. */
            conCuidado('popover', () => {
                const panel = document.querySelector('.muni-pop__panel');
                if (panel && typeof panel.showPopover === 'function') { panel.showPopover(); }
            });

            /* El <details> del timeline: HTML puro, ni siquiera necesita Alpine, y aun
               así nunca se había medido por nacer colapsado. */
            conCuidado('timeline', () => {
                document.querySelectorAll('details.muni-tl__detail').forEach((d) => { d.open = true; });
            });

            /* La cabecera ordenable con una columna YA ordenada. Este estado no se ha
               medido NUNCA: ninguna columna arranca ordenada, así que la flecha
               encendida, el aria-sort y el anuncio del cambio de orden no existían. */
            conCuidado('sortable-table', () => {
                const tabla = document.querySelector('table.muni-st');
                const raiz = tabla && tabla.closest('[x-data]');
                if (raiz) { window.Alpine.$data(raiz).sort('monto'); }
            });
        };

        const mide = () => {
            if (dialogo) {
                const caso = DIALOGOS[dialogo];
                if (caso) { conCuidado(dialogo, caso.mide); }

                /* La trampa TIENE que haber aislado algo. Si no, o falta el plugin
                   Focus o el componente dejó de usar `x-trap.inert`: en los dos
                   casos lo que se está midiendo es un diálogo que no atrapa el
                   foco, y eso la reja lo tiene que poner en rojo, no medir de menos. */
                const delta = aislados() - aisladosAntes;
                if (delta > 0) { estado.abiertos.push('trampa de foco/nodos aislados por x-trap.inert ' + delta); }
                else {
                    estado.fallos.push('trampa de foco: al abrir el diálogo no se aisló ni un nodo '
                        + '(`x-trap.inert` no corrió). Falta el plugin Focus de Alpine (@alpinejs/focus) '
                        + 'o el componente dejó de atrapar el foco: el diálogo se abre, pero el foco se escapa.');
                }
            } else {
                anota('combobox/lista', document.querySelector('.muni-combo__lista'));
                anota('combobox/opción activa', document.querySelector('.muni-combo__opcion'));
                anota('tooltip/burbuja', document.querySelector('.muni-tt__bubble'));
                anota('popover/panel', document.querySelector('.muni-pop__panel'));
                anota('timeline/detalle', document.querySelector('details.muni-tl__detail[open] .muni-tl__diff'));
                anota('sortable-table/columna ordenada', document.querySelector('table.muni-st th[aria-sort="ascending"]'));
                anota('sortable-table/cuerpo', document.querySelector('table.muni-st tbody tr'));
                anota('sidebar/columna', document.querySelector('#vitrina-sidebar-columna'));
                anota('sidebar/ítem activo', document.querySelector('#vitrina-sidebar-columna [aria-current="page"]'));

                /* La lateral en modo columna NO puede atrapar el foco: atraparlo
                   dejaría al funcionario encerrado en el menú, y de paso volvería
                   `aria-hidden` a todas las demás tarjetas, que el medidor trata
                   entonces como decorativas y mide con la vara de 3:1 en vez de
                   4,5:1. Es decir: la reja seguiría verde midiendo más blando. */
                conCuidado('sidebar/sin trampa en columna', () => {
                    const aside = document.querySelector('#vitrina-sidebar-columna');
                    if (! aside) { throw new Error('no está la lateral en la página'); }
                    const datos = window.Alpine.$data(aside);
                    if (datos.overlay) { throw new Error('quedó en modo superpuesto con la ventana a '
                        + window.innerWidth + 'px: taparía las demás tarjetas'); }
                    const tapadas = Array.from(document.querySelectorAll('[data-pieza]'))
                        .filter((t) => t.closest('[aria-hidden="true"]'))
                        .map((t) => t.getAttribute('data-pieza'));
                    if (tapadas.length) { throw new Error('hay ' + tapadas.length + ' tarjetas dentro de un '
                        + 'aria-hidden (' + tapadas.slice(0, 3).join(', ') + '…): se estarían midiendo como '
                        + 'decorativas, con la vara de 3:1 en vez de 4,5:1'); }
                });
            }

            window.__vitrinaEstado = estado;
            document.documentElement.setAttribute('data-vitrina-lista', '1');
        };

        /* ¿Llegó el plugin Focus? Se pregunta en TODAS las páginas, no solo en las
           de diálogo: `dropdown`, que va en la vitrina general, también usa
           `x-trap`, y sin el plugin esas cuatro páginas daban verde con un menú
           que no atrapa el foco (medido: 8 de 40 combinaciones en «ok»). Y
           «hidratado» quiere decir lo que corre en producción —el bundle de
           Livewire trae núcleo Y plugin—, no medio entorno.
           Se pregunta por la API pública: el plugin registra la mágica `$focus`
           y `Alpine.evaluate` la resuelve. Una directiva desconocida no se puede
           sondear —Alpine la ignora sin decir nada—, una mágica sí. */
        const hayFocus = () => {
            try {
                const el = document.querySelector('[x-data]') || document.body;
                const focus = window.Alpine.evaluate(el, '$focus');
                return !! focus && typeof focus.focus === 'function';
            } catch (error) { return false; }
        };

        const arranca = () => {
            if (! hayFocus()) {
                /* Sin abrir nada y SIN marcar `data-vitrina-lista`: la reja espera
                   el apretón de manos, no llega y pone la página en rojo como SIN
                   HIDRATAR, que es exactamente lo que queda —lo visible en reposo—. */
                estado.fallos.push('falta el plugin Focus de Alpine (@alpinejs/focus): x-trap no existe');
                window.__vitrinaEstado = estado;
                console.error('Vitrina: falta el plugin Focus de Alpine (@alpinejs/focus). No se abre nada y la página queda sin marcar como lista.');
                return;
            }
            abre();
            /* Un respiro antes de medir: las transiciones de x-show arrancan en
               opacity:0 y el medidor de contraste salta lo que tiene opacidad 0.
               Con prefers-reduced-motion la duración es 0ms, pero la reja también
               se corre a mano en un navegador sin esa preferencia. */
            setTimeout(mide, 250);
        };

        /* OJO con esperar solo el evento: el build de CDN de Alpine llama a start()
           en un microtask del PROPIO <script>, así que `alpine:initialized` ya se
           disparó cuando corre esta línea y un listener a secas no se entera nunca.
           Medido: la vitrina se quedaba sin marcar `data-vitrina-lista` para siempre.
           Se espera primero a que el documento termine de parsearse —Alpine tiene
           MutationObserver, así que hidrata lo que llegue después— y ahí se decide:
           si Alpine ya arrancó, se abre de una; si no, se engancha el evento. */
        const cuandoAlpine = () => {
            if (window.Alpine && window.Alpine.version) { arranca(); }
            else { document.addEventListener('alpine:initialized', arranca, { once: true }); }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', cuandoAlpine, { once: true });
        } else {
            cuandoAlpine();
        }
    })();
    JS;
}

/**
 * Las tarjetas, ya renderizadas por Blade, envueltas por quien llame.
 *
 * El envoltorio cambia según el contexto (tarjeta suelta o `section.fi-section`
 * del panel) pero el ORDEN y el conjunto no: las dos vitrinas miden exactamente
 * las mismas piezas, que es lo único que hace comparable una paleta con la otra.
 */
function piezasDeVitrina(callable $envoltorio, ?string $dialogo = null): string
{
    // Una página de diálogo lleva UNA sola pieza, y a propósito: los cuatro que
    // atrapan el foco vuelven `aria-hidden` todo lo que no es el diálogo, así que
    // compartir página con las otras tarjetas las degradaría a decorativas.
    $ejemplos = $dialogo === null
        ? ejemplosDeVitrina()
        : [$dialogo => ejemplosDeDialogo()[$dialogo]];

    $piezas = '';

    foreach ($ejemplos as $nombre => $blade) {
        $piezas .= $envoltorio($nombre, Blade::render($blade));
    }

    return $piezas;
}

/**
 * El <head> común: charset, viewport y el título. La hoja la pone cada contexto.
 */
function cabezaDeVitrina(string $titulo): string
{
    return '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>'.$titulo.'</title>';
}

/**
 * El <script> de Alpine + el abridor, o nada si no hay copia en disco.
 * Va INCRUSTADO, no enlazado: la vitrina se abre por file:// y tiene que seguir
 * midiéndose igual si se copia el .html a otra parte.
 */
function colaDeVitrina(?string $alpine, ?string $focus): string
{
    if ($alpine === null) {
        return '';
    }

    // El plugin PRIMERO. Los plugins de Alpine se registran sobre `window.Alpine`
    // antes de que el núcleo arranque, y el build de CDN del núcleo llama a
    // `start()` en un microtask de su propio <script>: al revés, `x-trap` no
    // existiría cuando la página hidrata. Cada <script> va rotulado para que el
    // orden sea comprobable desde la prueba y desde el HTML generado.
    $cola = $focus !== null ? '<script data-vitrina="alpine-focus">'.$focus.'</script>' : '';

    return $cola
        .'<script data-vitrina="alpine-core">'.$alpine.'</script>'
        .'<script data-vitrina="abridor">'.abridorDeVitrina().'</script>';
}

/**
 * El atributo que declara QUÉ diálogo espera la página, o cadena vacía.
 * El abridor lo lee para saber qué abrir y qué exigir; sin él, la página es la
 * vitrina general.
 */
function marcaDeDialogo(?string $dialogo): string
{
    return $dialogo === null ? '' : ' data-vitrina-dialogo="'.$dialogo.'"';
}

function armaVitrina(string $tema, ?string $alpine, ?string $focus = null, ?string $dialogo = null): string
{
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui.css');

    $piezas = piezasDeVitrina(fn (string $nombre, string $html) => '<section class="v-pieza" data-pieza="'.$nombre.'">'
        .'<h2 class="v-nombre">'.rotuloDePieza($nombre).'</h2>'
        .'<div class="v-cuerpo">'.$html.'</div></section>', $dialogo);

    // El contenedor fija el tema y las superficies con los MISMOS tokens que usan
    // los componentes: medir sobre un fondo inventado no diría nada.
    // `data-vitrina-espera-alpine` es el contrato con la reja, y se declara SIEMPRE
    // —haya copia de Alpine o no—: si la página lo declara y nunca aparece
    // `data-vitrina-lista`, `a11y-check.py` FALLA. Ponerlo solo cuando Alpine existe
    // dejaría el peor caso en silencio: sin Alpine la reja volvería a dar verde
    // midiendo 165 textos en vez de 181 y nadie se enteraría de que mide menos.
    // `data-vitrina-hoja` dice QUÉ paleta se cargó: la reja lo lee y lo imprime en
    // la tabla, para que no haya que adivinarlo por el nombre del archivo.
    return '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($tema === 'dark' ? ' class="dark"' : '').' data-vitrina-espera-alpine="1" data-vitrina-hoja="muni-ui.css"'.marcaDeDialogo($dialogo).'>'
        .cabezaDeVitrina('Vitrina de laravel-muni-ui — '.($dialogo !== null ? $dialogo.' abierto, ' : '').'tema '.($tema === 'dark' ? 'oscuro' : 'claro'))
        .'<style>'.$css.'
            body { margin:0; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); }
            .v-cab { padding:24px; border-bottom:1px solid var(--muni-border); }
            .v-cab h1 { margin:0; font-size:20px; }
            .v-grid { display:grid; gap:20px; padding:24px; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); }
            .v-pieza { background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-lg); padding:16px; }
            .v-nombre { margin:0 0 12px; font-family:var(--muni-font-mono); font-size:12px; color:var(--muni-muted); font-weight:600; }
            /* Se mide también sobre la superficie secundaria, que es donde el
               mensaje de error fallaba el contraste antes de llevar fondo propio. */
            .v-pieza:nth-child(even) .v-cuerpo { background:var(--muni-surface-3); padding:12px; border-radius:var(--muni-radius); }
        </style></head><body>'
        .'<header class="v-cab"><h1>Vitrina de componentes — '.($dialogo !== null ? '&lt;x-muni::'.$dialogo.'&gt; abierto, ' : '').'tema '.($tema === 'dark' ? 'oscuro' : 'claro').'</h1></header>'
        .'<main id="muni-contenido" tabindex="-1"><div class="v-grid">'.$piezas.'</div></main>'
        .colaDeVitrina($alpine, $focus)
        .'</body></html>';
}

/**
 * La MISMA vitrina, pero en el contexto del panel: la otra paleta y el otro DOM.
 *
 * Por qué existe (DESIGN §7): `MuniPanel` inyecta `muni-ui-filament.css` y
 * `muni-ui.css` NO se carga dentro de un panel. Son dos identidades a propósito
 * —petróleo, lima y celeste contra teal y ámbar—, y en la rama oscura 30 de los
 * 32 tokens comparables valen distinto. Hasta ahora la reja medía UNA sola de las
 * dos: todo lo que el repo afirmaba sobre contraste valía para las aplicaciones
 * sueltas y no decía absolutamente nada de los nueve sistemas municipales, que
 * corren sobre paneles Filament. La verificación manual del panel
 * (`docs/VERIFICACION-NAVEGADOR.md` §8) encontró ahí cuatro defectos que solo
 * existen en esa paleta, incluido D13, donde la reja aprobaba `.muni-tl__tone` y
 * axe lo reprobaba EN LA MISMA CORRIDA porque cada uno medía contra otra hoja.
 *
 * El banco es el de esa verificación (§8.1), reproducido aquí:
 *   · se carga ÚNICAMENTE `muni-ui-filament.css`, nunca `muni-ui.css`;
 *   · el DOM es el del panel — `body.fi-body`, `aside.fi-sidebar`,
 *     `div.fi-topbar`, `div.fi-main-ctn > main.fi-main`, y cada componente
 *     dentro de un `section.fi-section` con su `h2.fi-section-header-heading`;
 *   · del armazón se reponen SOLO dos cosas que pone Filament y no el paquete:
 *     la geometría (anchos y relleno) y `color:var(--muni-text)` en el <body>
 *     —Filament emite ahí `text-gray-950 dark:text-white`—, para no medir texto
 *     negro sobre fondo oscuro, que sería un defecto del banco. Ni un color más.
 *
 * Dos diferencias declaradas respecto del banco de §8.1, y el porqué de cada una:
 *   · las tarjetas son TODAS las de `ejemplosDeVitrina()`, no las 25 del banco: la
 *     tarea es medir LAS MISMAS piezas en las dos paletas. De lo que el banco
 *     tenía, `dropdown` ya va en la vitrina y `modal`, `drawer` y
 *     `command-palette` en su página propia (ver `ejemplosDeDialogo()`); sigue
 *     fuera `accordion`.
 *   · se conserva el reparto par/impar sobre `--muni-surface-3` de la vitrina
 *     base. El banco dejaba `.v-cuerpo` vacío y por eso D16 quedó como «par de
 *     riesgo sin instancia observada»; con el reparto, la instancia existe y se
 *     mide, que es exactamente lo que D16 decía que pasaría en un anfitrión que
 *     pintara una tarjeta con `surface-3` dentro del panel.
 *
 * El <header class="fi-header"> con su <h1> es DOM real del panel —la propia hoja
 * lo estiliza en `.fi-main > .fi-header` y `.fi-header-heading`— y se incluye para
 * que las dos vitrinas tengan la misma estructura de encabezados y la comparación
 * axe-contra-axe no se ensucie con un `page-has-heading-one` de más.
 */
function armaVitrinaPanel(string $tema, ?string $alpine, ?string $focus = null, ?string $dialogo = null): string
{
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');
    $oscuro = $tema === 'dark';

    $piezas = piezasDeVitrina(fn (string $nombre, string $html) => '<section class="fi-section" data-pieza="'.$nombre.'">'
        .'<h2 class="fi-section-header-heading">'.rotuloDePieza($nombre).'</h2>'
        .'<div class="v-cuerpo">'.$html.'</div></section>', $dialogo);

    return '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-espera-alpine="1" data-vitrina-hoja="muni-ui-filament.css"'.marcaDeDialogo($dialogo).'>'
        .cabezaDeVitrina('Vitrina de laravel-muni-ui en panel Filament — '.($dialogo !== null ? $dialogo.' abierto, ' : '').'tema '.($oscuro ? 'oscuro' : 'claro'))
        .'<style>'.$css.'</style>'
        // Segunda hoja, a propósito separada de la del paquete: es el armazón del
        // banco, y tiene que verse de un vistazo que no aporta ni un color.
        .'<style>
            body { margin:0; font-family:system-ui, sans-serif; color:var(--muni-text); }
            .fi-main-ctn { margin-inline-start:180px; }
            .fi-main { padding:24px; max-width:none; }
            .fi-sidebar { position:fixed; inset-block:0; inset-inline-start:0; width:180px; padding:12px; z-index:30; }
            .fi-topbar { position:sticky; top:0; z-index:20; }
            .fi-topbar > nav { padding:12px 24px; }
            .v-grid { display:grid; gap:20px; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); }
            .fi-section { padding:16px; }
            .fi-section-header-heading { font-size:12px; margin:0 0 12px; font-family:var(--muni-font-mono); }
            /* Mismo reparto que la vitrina base: una de cada dos tarjetas mide
               sobre --muni-surface-3. Ver el comentario de arriba. */
            .v-grid > .fi-section:nth-child(even) .v-cuerpo { background:var(--muni-surface-3); padding:12px; border-radius:var(--muni-radius); }
        </style></head>'
        .'<body class="fi-body">'
        // Los dos `aria-label` son del banco, no del paquete: sin ellos los tres
        // <nav> de la página (barra lateral, barra superior y el `breadcrumb` del
        // propio componente) quedan indistinguibles y axe levanta un
        // `landmark-unique` que es culpa del armazón. Filament rotula los suyos.
        .'<aside class="fi-sidebar"><nav class="fi-sidebar-nav" aria-label="Menú del panel"><div class="fi-sidebar-item">'
        .'<a class="fi-sidebar-item-btn" href="#"><span class="fi-sidebar-item-label">Bandeja</span></a>'
        .'</div></nav></aside>'
        .'<div class="fi-topbar"><nav aria-label="Barra superior"><span>Barra superior del panel</span></nav></div>'
        .'<div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
        .'<header class="fi-header"><h1 class="fi-header-heading">Vitrina de componentes en el panel — '.($dialogo !== null ? '&lt;x-muni::'.$dialogo.'&gt; abierto, ' : '').'tema '.($oscuro ? 'oscuro' : 'claro').'</h1></header>'
        .'<div class="v-grid">'.$piezas.'</div></main></div>'
        .colaDeVitrina($alpine, $focus)
        .'</body></html>';
}

/**
 * Las cuatro páginas de la vitrina: archivo => cómo se arma.
 *
 * Cuatro y no dos porque son DOS paletas × DOS temas. La reja las mide todas por
 * omisión (`scripts/a11y-check.py` sin argumentos glob-ea `build/vitrina/*.html`).
 */
function paginasDeVitrina(): array
{
    $paginas = [
        'claro' => ['light', 'armaVitrina', null],
        'oscuro' => ['dark', 'armaVitrina', null],
        'panel-claro' => ['light', 'armaVitrinaPanel', null],
        'panel-oscuro' => ['dark', 'armaVitrinaPanel', null],
    ];

    // Y una página por diálogo, tambien en las dos paletas y los dos temas. No es
    // manía de simetría: los nueve sistemas municipales corren dentro de paneles
    // Filament, donde se carga `muni-ui-filament.css` y NO `muni-ui.css`, y en la
    // rama oscura 30 de los 32 tokens comparables valen distinto. Medir el modal
    // solo en la paleta suelta no diría nada del modal que ve el funcionario.
    foreach (array_keys(ejemplosDeDialogo()) as $dialogo) {
        $paginas[$dialogo.'-claro'] = ['light', 'armaVitrina', $dialogo];
        $paginas[$dialogo.'-oscuro'] = ['dark', 'armaVitrina', $dialogo];
        $paginas['panel-'.$dialogo.'-claro'] = ['light', 'armaVitrinaPanel', $dialogo];
        $paginas['panel-'.$dialogo.'-oscuro'] = ['dark', 'armaVitrinaPanel', $dialogo];
    }

    return $paginas;
}

it('genera la vitrina con los componentes reales, en las dos paletas y los dos temas', function () {
    $destino = __DIR__.'/../build/vitrina';

    if (! is_dir($destino)) {
        mkdir($destino, 0o775, true);
    }

    $alpine = fuenteDeAlpine();
    $focus = fuenteDeFocus();

    if ($alpine === null) {
        // Sin Alpine la vitrina se genera igual, pero mide la mitad. No se falla la
        // suite —una máquina sin `npm install` no tiene por qué quedarse sin correr
        // las pruebas— y se avisa donde se ve: la reja lo convierte en FALLA.
        fwrite(STDERR, "\nAVISO: no hay copia de Alpine 3 en disco, la vitrina sale SIN hidratar.\n"
            ."       Todo lo que abre (combobox, tooltip, popover, sortable-table) queda sin medir.\n"
            ."       Solución: `npm install` en el repo, o copiar cdn.min.js de alpinejs a\n"
            ."       scripts/.cache/alpine-<version>.min.js, o apuntar \$MUNI_ALPINE_JS a un archivo.\n\n");
    }

    if ($focus === null) {
        // Mismo criterio que con el núcleo, y el mismo desenlace: las páginas se
        // generan igual, el abridor comprueba en el navegador que falta el plugin,
        // no abre nada ni marca la página lista, y la reja las pone en ROJO. Es lo
        // que tiene que pasar: un diálogo que no atrapa el foco no es accesible.
        fwrite(STDERR, "\nAVISO: no hay copia del plugin Focus de Alpine en disco.\n"
            ."       modal, drawer, command-palette, sidebar y dropdown no atraparían el foco, así que\n"
            ."       la vitrina no abre nada y la reja pone TODAS las páginas en rojo como SIN HIDRATAR.\n"
            ."       Solución: `npm install` en el repo, o copiar cdn.min.js de @alpinejs/focus a\n"
            ."       scripts/.cache/alpine-focus-<version>.min.js, o apuntar \$MUNI_ALPINE_FOCUS_JS a un archivo.\n\n");
    }

    foreach (paginasDeVitrina() as $archivo => [$tema, $constructor, $dialogo]) {
        $html = $constructor($tema, $alpine, $focus, $dialogo);
        $panel = str_starts_with($archivo, 'panel-');

        if ($dialogo === null) {
            expect($html)->toContain('x-muni::stat')
                ->and(strlen($html))->toBeGreaterThan(20000, 'La vitrina salió sospechosamente corta.');
        } else {
            // Una página de diálogo lleva UNA pieza y nada más: si un día se cuela
            // el resto de la vitrina, la trampa de foco la volvería `aria-hidden`
            // entera y el medidor la mediría con la vara de 3:1. Por eso se
            // comprueba las dos cosas: que está el diálogo y que NO está lo demás.
            expect($html)->toContain('&lt;x-muni::'.$dialogo.'&gt;')
                ->and($html)->toContain('data-vitrina-dialogo="'.$dialogo.'"')
                ->and($html)->not->toContain('x-muni::stat')
                ->and(substr_count($html, ' data-pieza="'))->toBe(1, 'La página del diálogo trae más de una tarjeta.')
                ->and(strlen($html))->toBeGreaterThan(15000, 'La página del diálogo salió sospechosamente corta.');
        }

        expect($html)->toContain('data-vitrina-espera-alpine="1"');

        if ($panel) {
            // La condición que DESIGN §7 describe: SOLO la hoja del panel. Si un día
            // alguien mete `muni-ui.css` aquí «para que se vea bien», la página deja
            // de medir la paleta del panel y esto se pone rojo.
            expect($html)->toContain('data-vitrina-hoja="muni-ui-filament.css"')
                ->and($html)->toContain('Sala de Gobierno')
                ->and($html)->not->toContain('Sistema de diseño del ecosistema municipal de Graneros');

            // Y el DOM del panel, la cadena entera: sin ella los selectores de la
            // hoja (`.fi-body`, `.dark .fi-section`, …) no enganchan y se mediría
            // la paleta correcta sobre fondos que el panel nunca pinta.
            expect($html)->toContain('<body class="fi-body">')
                ->and($html)->toContain('<div class="fi-main-ctn">')
                ->and($html)->toContain('<main class="fi-main"')
                ->and($html)->toContain('<section class="fi-section"')
                ->and($html)->toContain('class="fi-sidebar"')
                ->and($html)->toContain('class="fi-topbar"');
        } else {
            expect($html)->toContain('data-vitrina-hoja="muni-ui.css"')
                ->and($html)->toContain('Sistema de diseño del ecosistema municipal de Graneros');
        }

        if ($alpine !== null) {
            // Que el <script> esté no basta: si el abridor deja de abrir algo, la
            // vitrina vuelve a medir solo lo visible en reposo sin que nadie se entere.
            expect($html)->toContain('alpine:initialized')
                ->and($html)->toContain('data-vitrina-lista')
                ->and($html)->toContain("querySelector('.muni-combo')")
                ->and($html)->toContain("querySelector('.muni-tt')")
                ->and($html)->toContain("querySelector('.muni-pop__panel')")
                ->and($html)->toContain('details.muni-tl__detail')
                ->and($html)->toContain("querySelector('table.muni-st')");

            // Y que el abridor sepa abrir los cuatro que atrapan el foco, y que
            // exija la prueba de la trampa. Sin esto se volvería a lo de antes:
            // medir el diálogo cerrado, o abierto pero sin atrapar nada.
            expect($html)->toContain("dispatchEvent(new CustomEvent('muni-sidebar'))")
                ->and($html)->toContain('x-trap.inert');

            // Y que en CUALQUIER página, sin el plugin Focus, no se abra nada ni se
            // marque lista: `dropdown` vive en la vitrina general y también usa
            // `x-trap`, así que la falta del plugin no puede quedar solo en las
            // páginas de diálogo.
            expect($html)->toContain("window.Alpine.evaluate(el, '\$focus')")
                ->and($html)->toContain('if (! hayFocus())');
        }

        if ($alpine !== null && $focus !== null) {
            // El plugin va ANTES del núcleo, que es como Alpine lo pide: los
            // plugins se registran sobre `window.Alpine` antes de que el núcleo
            // llame a start(), y el build de CDN arranca solo. Al revés `x-trap`
            // no existiría al hidratar y los cuatro diálogos no atraparían nada.
            expect($html)->toContain('data-vitrina="alpine-focus"')
                ->and(strpos($html, 'data-vitrina="alpine-focus"'))
                ->toBeLessThan(strpos($html, 'data-vitrina="alpine-core"'));
        }

        file_put_contents("{$destino}/{$archivo}.html", $html);
    }

    foreach (array_keys(paginasDeVitrina()) as $archivo) {
        expect(file_exists($destino."/{$archivo}.html"))->toBeTrue();
    }
});

it('mide las mismas piezas en las dos paletas', function () {
    // El valor de la variante del panel es la COMPARACIÓN: si un día las dos
    // vitrinas dejan de renderizar el mismo conjunto, una diferencia de contraste
    // entre paletas deja de ser atribuible a la paleta.
    $base = armaVitrina('dark', null, null);
    $panel = armaVitrinaPanel('dark', null, null);

    foreach (array_keys(ejemplosDeVitrina()) as $nombre) {
        expect($base)->toContain('>'.rotuloDePieza($nombre).'</h2>')
            ->and($panel)->toContain('>'.rotuloDePieza($nombre).'</h2>');
    }

    expect(substr_count($panel, 'class="fi-section"'))->toBe(count(ejemplosDeVitrina()));
});

it('trae en error cada control que pinta el error desde el servidor, en las dos paletas', function () {
    // `select`, `textarea` y `switch` pintan el borde de error con estilo EN LÍNEA
    // desde el servidor. La pasada de no-texto de la reja sondea el error poniendo
    // `aria-invalid` en el navegador, y en estos tres no cambia nada: si la página
    // no trae ya una instancia en error, ese estado no se mide nunca y D11 (el
    // borde en error menos visible que el normal) puede volver sin que nadie lo vea.
    // Se exige el control de verdad con `aria-invalid="true"`, no solo el mensaje.
    $enError = [
        'select' => '/<select\b[^>]*\baria-invalid="true"/',
        'textarea' => '/<textarea\b[^>]*\baria-invalid="true"/',
        // El <input> real del switch es invisible (0×0): la reja mide la pista
        // `.muni-switch` que va justo después y lee el error de su hermano previo.
        'switch' => '/<input\b[^>]*\baria-invalid="true"[^>]*>\s*<span class="muni-switch"/',
    ];

    foreach (['armaVitrina', 'armaVitrinaPanel'] as $constructor) {
        foreach (['light', 'dark'] as $tema) {
            $html = $constructor($tema, null, null);

            foreach ($enError as $control => $patron) {
                expect(preg_match($patron, $html))
                    ->toBe(1, "{$constructor}/{$tema}: no hay ningún {$control} en error, y la reja no puede provocarlo.");
            }
        }
    }
});
