<?php

/*
 * Índice del catálogo: grupo → componente → metadatos de presentación.
 * Las props y slots NO van aquí: se leen del propio componente (su @props
 * comentado), para que la documentación no pueda quedar atrás del código.
 *
 *   desc     qué es y cuándo usarlo (una o dos frases)
 *   vista    fila (default) · columna · lienzo (fondo de página) · sangrado (sin padding) · iframe (documento completo)
 *   incluye  subcomponentes que se documentan dentro de este
 *   eventos  eventos de ventana que escucha o emite
 */
return [
    'Identidad institucional' => [
        'gob-bar' => ['desc' => 'Barra municipal que va sobre cada subdominio del ecosistema: escudo, municipio, sistema actual y enlace al sitio madre. Ya incluye la franja de colores.', 'vista' => 'sangrado'],
        'gob-stripe' => ['desc' => 'Franja con los 7 colores oficiales del municipio. Para separar la identidad institucional del contenido.', 'vista' => 'columna'],
        'gob-escudo' => ['desc' => 'Escudo oficial. Se publica con `vendor:publish --tag=muni-ui-images`.'],
        'gob-footer' => ['desc' => 'Pie institucional con contacto, enlaces del ecosistema y aviso de la Ley 21.719. Slot `links` para reemplazar los enlaces.', 'vista' => 'sangrado'],
    ],
    'Estructura y navegación' => [
        'topbar' => ['desc' => 'Barra superior con nombre del sistema y estado del servicio. La usa `app-shell`; el slot va a la derecha.', 'vista' => 'sangrado'],
        'page-header' => ['desc' => 'Encabezado de página: rótulo, título, subtítulo y acciones a la derecha.', 'vista' => 'columna'],
        'breadcrumb' => ['desc' => 'Ruta de navegación. El último ítem es la página actual y no lleva enlace.'],
        'sidebar' => ['desc' => 'Navegación lateral de paneles. En móvil se oculta y se abre con el evento `muni-sidebar`.', 'vista' => 'sangrado', 'incluye' => ['nav-section', 'nav-item'], 'eventos' => ['muni-sidebar (escucha)']],
        'tabs' => ['desc' => 'Pestañas con paneles. Flechas ←/→ mueven entre pestañas.', 'vista' => 'columna', 'incluye' => ['tab-panel']],
        'stepper' => ['desc' => 'Pasos de un trámite. Los anteriores al actual quedan marcados como hechos.', 'vista' => 'columna'],
        'pagination' => ['desc' => 'Paginación numerada con texto de resumen. `url` recibe el número de página y devuelve el enlace.', 'vista' => 'columna'],
        'accordion' => ['desc' => 'Preguntas o secciones plegables. Uno abierto a la vez, o varios con `multiple`.', 'vista' => 'columna'],
        'card' => ['desc' => 'Contenedor con título y acciones. `flush` quita el padding para meter tablas o listas de borde a borde.', 'vista' => 'lienzo'],
    ],
    'Datos y métricas' => [
        'kpi' => ['desc' => 'Cifra con etiqueta y franja de tono. Para resumir un filtro sobre una tabla.', 'vista' => 'lienzo'],
        'stat' => ['desc' => 'Cifra con variación y sparkline. Para tableros donde importa la tendencia.', 'vista' => 'lienzo'],
        'chart-bar' => ['desc' => 'Barras en HTML/CSS, sin JS ni librerías. Cada barra puede llevar su propio tono.', 'vista' => 'columna'],
        'chart-donut' => ['desc' => 'Dona SVG con leyenda. Útil para repartir un total en 2–5 partes.'],
        'ring' => ['desc' => 'Avance circular hacia una meta.'],
        'progress' => ['desc' => 'Avance lineal con etiqueta y porcentaje.', 'vista' => 'columna'],
        'data-table' => ['desc' => 'Tabla densa renderizada en servidor. Las filas van en el slot; `muni-row--danger` pinta la franja de libro mayor y `muni-num` alinea cifras.', 'vista' => 'columna'],
        'sortable-table' => ['desc' => 'Tabla con orden por columna y búsqueda en el navegador. Para listados cortos que ya están en memoria.', 'vista' => 'columna'],
        'timeline' => ['desc' => 'Bitácora vertical de eventos con tono por hito.', 'vista' => 'columna'],
        'badge' => ['desc' => 'Estado corto dentro de tablas y fichas.'],
        'avatar' => ['desc' => 'Iniciales o foto de una persona.'],
        'rating' => ['desc' => 'Estrellas para evaluar una atención, editable o de solo lectura.'],
    ],
    'Formularios' => [
        'button' => ['desc' => 'Botón o enlace con variantes y tamaños. Con `href` renderiza un `<a>`.'],
        'input' => ['desc' => 'Campo de texto con etiqueta, ayuda, error e ícono.', 'vista' => 'grilla'],
        'select' => ['desc' => 'Lista desplegable nativa con etiqueta, placeholder y error.', 'vista' => 'grilla'],
        'field' => ['desc' => 'Etiqueta compacta para un control nativo. Es la pieza de `filter-bar`.'],
        'filter-bar' => ['desc' => 'Formulario GET de filtros con botón de envío. Funciona sin JS.', 'vista' => 'columna'],
        'segmented' => ['desc' => 'Selector de pocas opciones. Con `name` usa radios reales y envía el formulario al cambiar.'],
        'switch' => ['desc' => 'Interruptor on/off con descripción.', 'vista' => 'columna'],
        'calendar' => ['desc' => 'Calendario de mes que guarda la fecha en un input oculto (YYYY-MM-DD).'],
        'otp-input' => ['desc' => 'Código de verificación dígito a dígito. Acepta pegar el código completo.'],
        'file-dropzone' => ['desc' => 'Zona para arrastrar o elegir archivos.', 'vista' => 'columna'],
    ],
    'Avisos y estados' => [
        'alert' => ['desc' => 'Mensaje destacado en la página: éxito, advertencia, error o información.', 'vista' => 'columna'],
        'empty-state' => ['desc' => 'Qué mostrar cuando no hay resultados, con una acción para salir de ahí.', 'vista' => 'columna'],
        'skeleton' => ['desc' => 'Bloques de carga con brillo animado (se detiene con reducción de movimiento).', 'vista' => 'columna'],
        'tooltip' => ['desc' => 'Texto breve al pasar el cursor o enfocar con teclado.', 'vista' => 'aire'],
        'toast-host' => ['desc' => 'Avisos flotantes. Se coloca una vez por layout y se disparan con el evento `muni-toast`.', 'eventos' => ["muni-toast (escucha): { tone, title?, message }"]],
    ],
    'Superposiciones' => [
        'modal' => ['desc' => 'Diálogo para confirmar o completar algo sin salir de la página. Escape y clic fuera lo cierran.'],
        'drawer' => ['desc' => 'Panel lateral para ver una ficha sin perder el listado.'],
        'dropdown' => ['desc' => 'Menú de acciones bajo un botón.', 'incluye' => ['dropdown-item'], 'vista' => 'alto'],
        'command-palette' => ['desc' => 'Buscador de accesos con ⌘K / Ctrl+K. Filtra por texto y navega con flechas y Enter.'],
    ],
    'Plantillas de página' => [
        'app-shell' => ['desc' => 'Documento completo: topbar y contenido centrado. Para sistemas de una sola vista.', 'vista' => 'iframe', 'incluye' => ['reverb-meta']],
        'dashboard-shell' => ['desc' => 'Documento completo de panel: sidebar, barra superior con estado y usuario.', 'vista' => 'iframe', 'incluye' => ['reverb-meta']],
        'auth-shell' => ['desc' => 'Documento completo de ingreso o registro a dos columnas.', 'vista' => 'iframe', 'incluye' => ['reverb-meta']],
        'error-page' => ['desc' => 'Página de error 403 · 404 · 500 · 503 con botón de vuelta.', 'vista' => 'iframe'],
    ],
];
