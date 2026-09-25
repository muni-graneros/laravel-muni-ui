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
 *   notas    cómo usarlo bien: Livewire, accesibilidad, errores comunes
 */
return [
    'Identidad institucional' => [
        'gob-bar' => ['desc' => 'Barra municipal que va sobre cada subdominio del ecosistema: escudo, municipio, sistema actual y enlace al sitio madre. Ya incluye la franja de colores.', 'vista' => 'sangrado', 'notas' => ['Va antes que todo, incluso antes de la topbar o del shell. Con `sticky` queda fija al hacer scroll.']],
        'gob-stripe' => ['desc' => 'Franja con los 7 colores oficiales del municipio. Para separar la identidad institucional del contenido.', 'vista' => 'columna'],
        'gob-escudo' => ['desc' => 'Escudo oficial. Se publica con `vendor:publish --tag=muni-ui-images`.'],
        'gob-footer' => ['desc' => 'Pie institucional con contacto, enlaces del ecosistema y aviso de la Ley 21.719. Slot `links` para reemplazar los enlaces.', 'vista' => 'sangrado'],
    ],
    'Estructura y navegación' => [
        'topbar' => ['desc' => 'Barra superior con nombre del sistema y estado del servicio. La usa `app-shell`; el slot va a la derecha.', 'vista' => 'sangrado', 'notas' => ['El estado es informativo: conéctalo a un chequeo real (colas, base de datos) y no lo dejes fijo en `online`.']],
        'page-header' => ['desc' => 'Encabezado de página: rótulo, título, subtítulo y acciones a la derecha.', 'vista' => 'columna', 'notas' => ['Un solo page-header por página: su título es el `h1`.']],
        'breadcrumb' => ['desc' => 'Ruta de navegación. El último ítem es la página actual y no lleva enlace.'],
        'sidebar' => ['desc' => 'Navegación lateral de paneles. En móvil se oculta y se abre con el evento `muni-sidebar`.', 'vista' => 'sangrado', 'incluye' => ['nav-section', 'nav-item'], 'eventos' => ['muni-sidebar (escucha)'], 'notas' => ['En escritorio queda fija; bajo 900 px se oculta y la abre el botón de menú de dashboard-shell. Escape y el fondo la cierran.']],
        'tabs' => ['desc' => 'Pestañas con paneles. Flechas ←/→ mueven entre pestañas.', 'vista' => 'columna', 'incluye' => ['tab-panel'], 'notas' => ['Para secciones de una misma ficha. Si cada pestaña es una URL distinta, usa enlaces y no tabs.', 'Sin JS se ve el panel por defecto.']],
        'stepper' => ['desc' => 'Pasos de un trámite. Los anteriores al actual quedan marcados como hechos.', 'vista' => 'columna'],
        'pagination' => ['desc' => 'Paginación numerada con texto de resumen. `url` recibe el número de página y devuelve el enlace.', 'vista' => 'columna', 'notas' => ['Con el paginador de Laravel: `:current="$filas->currentPage()" :total="$filas->lastPage()" :url="fn ($p) => $filas->url($p)"`.']],
        'accordion' => ['desc' => 'Preguntas o secciones plegables. Uno abierto a la vez, o varios con `multiple`.', 'vista' => 'columna', 'incluye' => ['accordion-item']],
        'card' => ['desc' => 'Contenedor con título y acciones. `flush` quita el padding para meter tablas o listas de borde a borde.', 'vista' => 'lienzo'],
    ],
    'Datos y métricas' => [
        'kpi' => ['desc' => 'Cifra con etiqueta y franja de tono. Para resumir un filtro sobre una tabla.', 'vista' => 'lienzo', 'notas' => ['Para cifras sin tendencia. Si importa si sube o baja, usa stat.']],
        'stat' => ['desc' => 'Cifra con variación y sparkline. Para tableros donde importa la tendencia.', 'vista' => 'lienzo', 'notas' => ['`spark` acepta cualquier serie de números, con o sin claves (p. ej. un pluck por mes).']],
        'chart-bar' => ['desc' => 'Barras en HTML/CSS, sin JS ni librerías. Cada barra puede llevar su propio tono.', 'vista' => 'columna'],
        'chart-donut' => ['desc' => 'Dona SVG con leyenda. Útil para repartir un total en 2–5 partes.'],
        'ring' => ['desc' => 'Avance circular hacia una meta.'],
        'progress' => ['desc' => 'Avance lineal con etiqueta y porcentaje.', 'vista' => 'columna'],
        'data-table' => ['desc' => 'Tabla densa renderizada en servidor. Las filas van en el slot; `muni-row--danger` pinta la franja de libro mayor y `muni-num` alinea cifras.', 'vista' => 'columna', 'notas' => ['Las filas se renderizan en servidor y funcionan sin JS; para cientos de filas, pagina en servidor con pagination.', '`muni-num` alinea RUT y montos con dígitos tabulares.']],
        'sortable-table' => ['desc' => 'Tabla con orden por columna y búsqueda en el navegador. Para listados cortos que ya están en memoria.', 'vista' => 'columna', 'notas' => ['Ordena y filtra en el navegador: úsala para listas cortas ya cargadas. Sin JS se ve como tabla fija, sin orden ni buscador.', 'Entiende montos (`$1.234.567`), RUT y fechas `dd-mm-aaaa`.']],
        'description-list' => ['desc' => 'Ficha de datos etiqueta: valor, como una lista de definición real. Para el detalle de una persona, patente o solicitud.', 'vista' => 'columna', 'notas' => ['`mono` alinea RUT, folios y montos; un valor vacío muestra «—».', 'En móvil siempre es una columna.']],
        'timeline' => ['desc' => 'Bitácora vertical de eventos con tono por hito.', 'vista' => 'columna'],
        'badge' => ['desc' => 'Estado corto dentro de tablas y fichas.'],
        'avatar' => ['desc' => 'Iniciales o foto de una persona.'],
        'rating' => ['desc' => 'Estrellas para evaluar una atención, editable o de solo lectura.', 'notas' => ['`wire:model="nota"` enlaza el valor. Con `readonly` es una imagen con nombre («4 de 5»), sin foco.']],
    ],
    'Formularios' => [
        'button' => ['desc' => 'Botón o enlace con variantes y tamaños. Con `href` renderiza un `<a>`.', 'notas' => ['Con Livewire, `wire:loading.attr="disabled"` lo deshabilita mientras corre la acción; `data-loading` le pone cursor de espera.', 'Con `href` y `disabled` deja de ser un enlace activo (aria-disabled).']],
        'input' => ['desc' => 'Campo de texto con etiqueta, ayuda, error e ícono.', 'vista' => 'grilla', 'notas' => ['`wire:model` y cualquier otro atributo van directo al `<input>`.', 'El `id` por defecto es `muni-{name}`; pasa un `id` propio si el mismo name aparece dos veces en la página.']],
        'select' => ['desc' => 'Lista desplegable nativa con etiqueta, placeholder y error.', 'vista' => 'grilla', 'notas' => ['`wire:model` va directo al `<select>`. Mismo manejo de `id` que input.']],
        'field' => ['desc' => 'Etiqueta compacta para un control nativo. Es la pieza de `filter-bar`.', 'notas' => ['Los `input`/`select` nativos del slot toman el estilo del sistema con especificidad cero: el CSS propio del host gana.']],
        'filter-bar' => ['desc' => 'Formulario GET de filtros con botón de envío. Funciona sin JS.', 'vista' => 'columna', 'notas' => ['Con `method="post"` agrega `@csrf` solo; con put/patch/delete agrega `@method`.']],
        'segmented' => ['desc' => 'Selector de pocas opciones. Con `name` usa radios reales y envía el formulario al cambiar.', 'notas' => ['Con `wire:model` o `x-model` el valor se enlaza en vivo y no envía el form.', 'Sin enlace, al cambiar envía el form GET que lo contiene (filtros). Nunca envía forms POST ni con `wire:submit`; `:autosubmit="false"` lo apaga.']],
        'switch' => ['desc' => 'Interruptor on/off con descripción.', 'vista' => 'columna', 'notas' => ['Es un checkbox real: `wire:model` va directo al input y el aspecto sigue a `:checked`, también sin JS.']],
        'calendar' => ['desc' => 'Calendario de mes que guarda la fecha en un input oculto (YYYY-MM-DD).', 'notas' => ['`wire:model="fecha"` enlaza el valor `YYYY-MM-DD` en ambos sentidos.', '`min` y `value` aceptan string `YYYY-MM-DD` o un Carbon.']],
        'otp-input' => ['desc' => 'Código de verificación dígito a dígito. Acepta pegar el código completo.', 'notas' => ['`wire:model="codigo"` enlaza el código completo. Pegar el código llena todas las casillas.']],
        'textarea' => ['desc' => 'Texto largo con etiqueta, ayuda, error y contador opcional de caracteres.', 'vista' => 'columna', 'notas' => ['`wire:model` va directo al `<textarea>`; el contenido inicial va en el slot.', 'Con `maxlength` muestra «n / máx» mientras se escribe (con Alpine); sin JS, el navegador igual corta en el máximo.']],
        'checkbox' => ['desc' => 'Casilla para aceptar o marcar algo. Para una preferencia on/off, switch.', 'vista' => 'columna', 'notas' => ['`wire:model` va directo al input; la marca se pinta con `:checked`, también sin JS.', 'Varias casillas con el mismo `name` terminado en `[]` envían un arreglo.']],
        'radio-group' => ['desc' => 'Pregunta con una sola respuesta, en un fieldset con legend. Admite una descripción por opción.', 'vista' => 'columna', 'notas' => ['`wire:model` / `x-model` se reenvían a cada radio.', 'Para 2–4 opciones cortas que filtran una vista, segmented.']],
        'file-dropzone' => ['desc' => 'Zona para arrastrar o elegir archivos.', 'vista' => 'columna', 'notas' => ['`wire:model` va al input de archivo: Livewire sube tanto lo elegido como lo arrastrado.']],
    ],
    'Avisos y estados' => [
        'alert' => ['desc' => 'Mensaje destacado en la página: éxito, advertencia, error o información.', 'vista' => 'columna', 'notas' => ['Para mensajes que deben quedarse en la página. Para confirmar una acción que ya ocurrió, usa un toast.']],
        'empty-state' => ['desc' => 'Qué mostrar cuando no hay resultados, con una acción para salir de ahí.', 'vista' => 'columna'],
        'spinner' => ['desc' => 'Indicador de carga para acciones que tardan: guardar, buscar, subir un archivo.', 'notas' => ['Con Livewire, `wire:loading` y `wire:target` van directo en el componente y lo muestran solo mientras corre la acción.', 'Se anuncia como `role=status` con su `label`; con movimiento reducido gira más lento en vez de detenerse.', 'Para reservar el espacio de contenido que aún no llega, skeleton.']],
        'skeleton' => ['desc' => 'Bloques de carga con brillo animado (se detiene con reducción de movimiento).', 'vista' => 'columna'],
        'tooltip' => ['desc' => 'Texto breve al pasar el cursor o enfocar con teclado.', 'vista' => 'aire'],
        'toast-host' => ['desc' => 'Avisos flotantes. Se coloca una vez por layout y se disparan con el evento `muni-toast`.', 'eventos' => ["muni-toast (escucha): { tone, title?, message }"], 'notas' => ['Desde Livewire: `$this->dispatch(\'muni-toast\', tone: \'ok\', message: \'Guardado\');`.', '`duration` en ms (por defecto 4500).']],
    ],
    'Superposiciones' => [
        'modal' => ['desc' => 'Diálogo para confirmar o completar algo sin salir de la página. Escape y clic fuera lo cierran.', 'notas' => ['Dentro de los slots, `open = false` lo cierra (p. ej. `x-on:click="open = false"`).', 'Atrapa el foco mientras está abierto y lo devuelve al botón que lo abrió.']],
        'drawer' => ['desc' => 'Panel lateral para ver una ficha sin perder el listado.', 'notas' => ['Igual que modal: `open = false` lo cierra. Se puede abrir un modal desde un drawer.']],
        'dropdown' => ['desc' => 'Menú de acciones bajo un botón.', 'incluye' => ['dropdown-item'], 'vista' => 'alto', 'notas' => ['Flechas ↑/↓ recorren las opciones y Escape vuelve al botón. Un dropdown-item solo cierra su propio menú.']],
        'command-palette' => ['desc' => 'Buscador de accesos con ⌘K / Ctrl+K. Filtra por texto y navega con flechas y Enter.', 'notas' => ['Los `items` pueden venir de rutas: `[\'label\' => \'Patentes\', \'url\' => route(\'patentes\')]`.']],
    ],
    'Plantillas de página' => [
        'app-shell' => ['desc' => 'Documento completo: topbar y contenido centrado. Para sistemas de una sola vista.', 'vista' => 'iframe', 'incluye' => ['reverb-meta'], 'notas' => ['El CSS y el JS del sistema entran por el slot `head` (p. ej. `@vite`).']],
        'dashboard-shell' => ['desc' => 'Documento completo de panel: sidebar, barra superior con estado y usuario.', 'vista' => 'iframe', 'incluye' => ['reverb-meta'], 'notas' => ['El `<body>` ya tiene `x-data`: las directivas de Alpine sueltas en el contenido funcionan sin envolverlas.']],
        'auth-shell' => ['desc' => 'Documento completo de ingreso o registro a dos columnas.', 'vista' => 'iframe', 'incluye' => ['reverb-meta']],
        'error-page' => ['desc' => 'Página de error 403 · 404 · 500 · 503 con botón de vuelta.', 'vista' => 'iframe'],
    ],
];
