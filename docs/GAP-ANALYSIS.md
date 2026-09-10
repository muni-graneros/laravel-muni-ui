# Análisis de brechas

Qué se comparó: los **60 candidatos** que cinco analistas
propusieron tras cruzar los 53 componentes del paquete contra las 262 piezas y las 122
composiciones catalogadas en las nueve familias de referencias MIT.

Después de fundir los que describían la misma pieza desde dominios distintos quedaron **55
fichas**: 16 reparaciones de lo que ya existe, 27 componentes nuevos y 12 composiciones de
pantalla.

**Este documento se poda a medida que se cierra trabajo.** Las seis fichas de la primera tanda ya
salieron del cuerpo, las siete de la segunda y tres de la tercera también. Están resumidas en la
sección 0 con su commit. **Quedan 34 pendientes.** Si una ficha sigue acá, sigue sin hacerse.

## Antes de leer: dos advertencias sobre este documento

**El panel de jueces no rechazó nada.** Los 45 jueces que alcanzaron a correr dieron el
mismo veredicto, «va, pero corregido», sin una sola exclusión. Un filtro que aprueba el cien por
ciento no está filtrando. Lo que sí produjeron, y es lo valioso, es una objeción concreta y una
corrección exigida por candidato: eso está publicado en cada ficha y conviene leerlo antes de
aceptar la propuesta del analista.

**12 fichas no tienen juez.** Se agotó el límite de sesión antes de evaluarlas. Están
marcadas y su prioridad está vacía, no inventada.

La consecuencia práctica de las dos advertencias es la misma: **este documento propone, no
decide**. El orden de la última sección es una sugerencia.

## 0. Ya está hecho

Estas fichas se retiraron del cuerpo del documento: ya no son trabajo pendiente. Se dejan
acá con su commit para no volver a proponerlas y para poder auditar qué cerró cada una.

| Pieza | Commit | Qué se cerró |
|---|---|---|
| `skip-link` (era `enlace-salto`) | `b47d036` | Nuevo. Los tres armazones lo traen cableado con el destino enfocable. |
| `tabs` | `f98fd3a` | Las flechas mueven el foco, más Home y End, y la relación pestaña–panel en las dos direcciones. |
| `sortable-table` | `c769aed` | El orden sale de un botón real: alcanzable con teclado, con aria-sort y región viva. |
| `segmented` | `62a51a2` | Respaldo de foco fuera de @supports y role=radiogroup con nombre. |
| `drawer + modal` (era `drawer`) | `d83e67d` | Ids deterministas, nombre accesible de respaldo y movimiento reducido respetado. |
| `file-dropzone` | `29bf1a8` | Se cierra la inyección en Alpine, aparece el foco, se anuncia el resultado y se valida el límite. |
| `input + select + switch` (era `input`) | `55d73ca` | El error se ata al campo con aria-describedby, convive con la ayuda, se anuncia por región viva y el id deja de salir de uniqid(). |
| `announcer` (era `anuncios`) | `12d9359` | Región viva compartida de la página, con polite y assertive separadas y presentes desde el primer render. |
| `error-summary` (era `resumen-errores`) | `12d9359` | Resumen tras un envío fallido: recuento en texto, cada error enlazado a su campo, y recibe el foco. |
| `textarea` (era `area-texto`) | `87deb6b` | Campo largo con contador en texto, error atado y anunciado, y crecimiento como mejora sobre el rows. |
| `checkbox` (era `casilla`) | `87deb6b` | Casilla nativa, nunca premarcada, con descripción atada y blanco de pulsación de 24 px. |
| `rut-input` (era `campo-rut`) | `370b625` | Formatea mientras se escribe y valida el dígito verificador con módulo 11; el error se dice en texto. |
| `date-input` (era `campo-fecha`) | `370b625` | Control de fecha nativo, con la ayuda en formato chileno y el rango desde/hasta coherente. |
| `sortable-table (selección en lote)` (era `seleccion-lote`) | `7ccdc3a` | Casillas nativas, selección por clave del registro y no por índice, y «seleccionar todo» que exige el conteo del servidor. |
| `table-header` (era `cabecera-tabla`) | `18bf15e` | La franja entre el título y la tabla, con el contador en texto que distingue el filtrado del total. |
| `empty-state` | `18bf15e` | Separa «no hay datos» de «el filtro no encontró nada», que para el funcionario son problemas opuestos. |
| `data-table` | `5881996` | La región desplazable se alcanza con teclado, las celdas saben su encabezado, y cabecera y primera columna fijas como opt-in. |
| `pagination` | `5881996` | Los extremos inertes dejan de ser enlaces, el apagado sale de un token y la página actual lleva aria-current. |
| `timeline` | `09cbf98` | El estado del hito deja de vivir solo en el color: etiqueta visible, más actor, aria-current=step, datetime ISO y el detalle en <details> nativo. |
| `diff-campos` (era parte de `bitacora-auditoria`) | `4d52ab2` | La tabla antes/después por campo, con agregado/modificado/suprimido legibles en texto. La pantalla completa sale del paquete: va en laravel-arcop-panel. |
| `hoja` (era `doc-imprimible`) | `b12f1db` | El paquete aprende a imprimir: hoja carta con membrete, folio, firma y pie numerado, más las reglas base de @media print en las dos hojas de estilo. Sin plantillas de certificado, acta ni oficio: esas las fija la Ley 19.880 y el municipio. |

Todas llevan prueba que falla si el defecto vuelve, y la suite pasó de 48 a 100 pruebas.

## 1. Lo que hay que reparar antes de agregar nada

Son defectos del paquete tal como está hoy. Van primero por una razón simple: agregar componentes
nuevos sobre un cimiento roto multiplica el defecto por cada sistema que los adopte, y el
ecosistema ya tiene nueve.

| Pieza | Prio | Qué resuelve | Trámite donde se usa | Esfuerzo |
|---|---|---|---|---|
| `dashboard-shell` | 2 | Que el armazón de escritorio se pueda usar de verdad en tablet y con lector de pantalla: hoy e… | El inspector de patentes con tablet abriendo el menú del panel de fiscalización en terreno; ho… | bajo |
| `stat` | 2 | Comparar dos cifras (hoy contra ayer, este mes contra el anterior) sin que el sentido de la co… | El tablero de la central de camaras de Seguridad Ciudadana, donde el turno compara los eventos… | bajo |
| `toast-host` | 2 | Es el canal por el que los seis sistemas confirman que un trámite se guardó o falló. | «Solicitud N.º 2026-4831 ingresada — derivada a Obras» en la recepción de Atención al Vecino; … | medio |
| `ring` | 3 | El avance circular: un porcentaje mostrado como arco. | El porcentaje de cupos ocupados de la agenda diaria de licencias; la proporción de solicitudes… | bajo |
| `tooltip` | 3 | La ayuda breve de un control, sobre todo de los botones de solo icono de las filas. | Los botones de icono de la fila del listado de patentes morosas (ver, anular, imprimir orden d… | bajo |
| `calendar` | 4 | Elegir un día en una rejilla de mes usando solo el teclado. | La agenda de licencias de conducir, donde el funcionario elige el día de la cita y solo alguno… | alto |
| `sidebar` | 4 | Que la barra lateral cerrada deje de recibir el foco y que el estado se comporte igual al rota… | El mesón de Licencias en tablet, y el operador de la central de cámaras que navega el turno co… | medio |
| `breadcrumb` | — | Que las migas sean una lista de verdad y que sus enlaces se distingan sin depender del color. | Cada pantalla del panel de Discapacidad: Inicio › Credenciales › Solicitud 2026-0412. | bajo |

### `dashboard-shell`

**reparación** · prioridad 2 · esfuerzo bajo

**Qué resuelve.** Que el armazón de escritorio se pueda usar de verdad en tablet y con lector de pantalla: hoy el botón de menú no hace nada.
**Qué pasa hoy sin él.** El burger (línea 38) lleva @click="window.dispatchEvent(new CustomEvent('muni-sidebar'))" pero el archivo no contiene un solo x-data: el <body> no abre ningún scope de Alpine, así que el @click nunca se enlaza y el evento jamás se despacha. Bajo 900px la barra lateral es inalcanzable. Además .muni-ds__scrim está estilado (líneas 29-30) y nunca se emite, el <main> no tiene id ni tabindex=-1 (no hay destino para un enlace de salto), el burger no declara aria-expanded ni aria-controls, y el <header> sticky z-index:40 no tiene scroll-margin-top en el contenido. Hoy los sistemas fuerzan ancho de escritorio o se escriben su propio layout en vez de usar el del paquete.
**Lo más parecido que ya existe.** Es el propio dashboard-shell: la estructura está bien, el estado no está cableado.
**Referencia que lo hace mejor.** AdminLTE 4 — refs/ColorlibHQ_AdminLTE/src/ts/push-menu.ts — Resuelve el estado sin depender de que el disparador viva dentro de un scope JS (clic delegado en document, clases en el <body>), lee el punto de quiebre desde el CSS y escucha matchMedia solo en el cruce del breakpoint, no en cada resize: el teclado del móvil o la barra de URL no le mueven el estado. Su ARIA es pobre (<a href="#" role="button"> sin aria-expanded), así que se copia la lógica, no el marcado.
**Patrón.** disclosure (para el botón de menú); ninguno para el armazón
**Teclas obligatorias.** Tab hasta el burger; Enter; Space; Escape (cierra en modo superpuesto); retorno de foco al burger al cerrar
**Alpine.** core
**Riesgo.** En Livewire el layout ES el <body>: hay que verificar que nuestro x-data no choque con el que ya ponen Livewire o Filament. El z-index del velo (140) queda entre el sidebar (150) y el topbar sticky (40), correcto, pero hay que comprobarlo con drawer (210) y command-palette abiertos. Persistir el estado en localStorage produce parpadeo en la primera pintura: si se persiste, va en cookie leída en servidor.
**Dónde se usa.** El inspector de patentes con tablet abriendo el menú del panel de fiscalización en terreno; hoy literalmente no puede.

> **Objeción del juez.** La mejor objeción es que el uso municipal declarado es prospectivo, no real: verifiqué que ningún sistema del ecosistema usa este armazón, y la razón probable es que Filament ya les da un layout responsive que funciona. Si nadie lo adoptó en un año, la alternativa honesta no es repararlo sino borrarlo del paquete y dejar app-shell/auth-shell para lo público, en vez de mantener un tercer armazón que compite con el de Filament. Contra eso pesa que está anunciado en el README línea 219 como el layout de panel: mientras siga ahí es una trampa para el próximo que lo tome, y borrarlo también cuesta decidirlo. Segunda objeción menor: el riesgo de choque de x-data con el <body> de Livewire o Filament que declara el candidato es casi inexistente, porque este componente ES el documento completo y un panel Filament nunca lo usaría; se está cubriendo de un peligro que no corre y no del que sí corre (el foco fuera de pantalla).
>
> **Corrección exigida.** 1) ALCANCE: son dos archivos, no uno. sidebar.blade.php:25 cierra con transform:translateX(-100%), que NO saca los nav-item del orden de tabulación: cerrado en móvil, el teclado tabula hacia enlaces invisibles fuera de pantalla (WCAG 2.4.7 y 2.4.11). Cablear solo el burger deja ese fallo intacto y sería una reparación cosmética; hay que añadir inert/visibility:hidden al estado cerrado. 2) NO poner el x-data en el <body>: dárselo al propio <header>/<button>, y que el sidebar devuelva su estado con un segundo evento (muni-sidebar-state) para que aria-expanded diga la verdad. Así <x-muni::sidebar> sigue sirviendo fuera del shell, que es como está diseñado hoy. 3) BREAKPOINT: los 900px están escritos cinco veces (sidebar CSS 24 y 27, shell CSS 27 y 30, y el JS window.innerWidth>=900 de sidebar:8). Tokenizarlo y leerlo con un solo matchMedia, que es exactamente lo que aporta la referencia de AdminLTE; si no, se corrige la mitad del problema que se dice ir a copiar. 4) MODO SUPERPUESTO: scrim emitido con clic para cerrar, x-trap.inert.noscroll, Escape y retorno de foco al burger. El plugin focus ya viaja en el bundle (modal:25, drawer:26, command-palette:37), así que no hay dependencia nueva. 5) ESFUERZO: bajo -> medio. No es cablear un x-data: son dos componentes, inert, trampa de foco, matchMedia y la verificación obligatoria (4 capturas claro/oscuro escritorio/móvil + recorrido de teclado). Un día, no una tarde. 6) SKIP-LINK: el <main id tabindex="-1"> y el scroll-margin-top van, pero decir en el ticket que la falta de skip-link (WCAG 2.4.1) es de los TRES armazones —dashboard-shell, app-shell y auth-shell, ninguno lo tiene— y que arreglar uno solo deja el 2.4.1 a medias. 7) PERSISTENCIA: no persistir nada, ni cookie. wire:navigate remonta el body y que el menú se cierre al navegar es el comportamiento deseado.

### `stat`

**reparación** · prioridad 2 · esfuerzo bajo

**Qué resuelve.** Comparar dos cifras (hoy contra ayer, este mes contra el anterior) sin que el sentido de la comparacion dependa del color.
**Qué pasa hoy sin él.** deltaDir decide el color y la flecha, y la flecha va aria-hidden="true": si el host pasa delta="12%" sin signo, un lector de pantalla oye «12%» y no sabe si subio o bajo (WCAG 1.4.1, el color como unico portador). Segundo defecto medido: divs sin <dl>, sin role=group ni aria-labelledby que ate valor y rotulo, o sea cifra y etiqueta se leen como dos textos sueltos. Y si la cifra se refresca sola por polling de Livewire, nadie la anuncia.
**Lo más parecido que ya existe.** stat y kpi (este ultimo con las cifras tabulares puestas con font-variant-numeric en linea en vez de la clase .muni-num, que es la firma del sistema); tambien ring, sin role=progressbar ni aria-valuenow: con showValue=false el dato existe solo como color.
**Referencia que lo hace mejor.** refs/justboil_admin-one-vue-tailwind/src/components/CardBoxWidget.vue, donde la variacion siempre lleva el porcentaje en texto ademas de la flecha; para las cifras tabulares, refs/saadeghi_daisyui/packages/daisyui/src/components/stat.css. — Porque es la unica del catalogo de la que se dice explicitamente que el color no carga solo con el significado: las de CoreUI, Sneat y AdminLTE fallan justo ahi.
**Patrón.** ninguno: es contenido. Con role=status si la cifra se refresca sola.
**Teclas obligatorias.** ninguno: no es interactivo (el hover eleva la tarjeta sin equivalente por teclado, pero no hay nada que activar)
**Alpine.** no
**Riesgo.** Envolver valor y rotulo en un <dl> cambia el DOM de un componente ya integrado en cuatro paneles Filament: hay que versionarlo y revisar los CSS de los hosts. El texto alternativo («sube», «baja») debe ir en una clase visualmente oculta que NO use display:none, que lo sacaria del arbol de accesibilidad.
**Dónde se usa.** El tablero de la central de camaras de Seguridad Ciudadana, donde el turno compara los eventos de hoy contra ayer y la pantalla se refresca sola.

> **Objeción del juez.** El uso municipal declarado es aspiracional, no actual. `stat` no está renderizado en licencias, patentes, discapacidad, seguridad ciudadana, atención al vecino ni control de acceso: la única coincidencia fuera del paquete es la página vitrina de los scaffolds. Arreglar un componente que nadie renderiza es pulir la demo, y esa misma tarde puesta en `data-table` o `field` tocaría una pantalla que un funcionario sí abre todos los días. La contraargumentación que lo salva es el costo: son atributos, no lógica. Además, el riesgo declarado («integrado en cuatro paneles Filament, hay que revisar los CSS de los hosts») es falso y sobredimensiona el trabajo.
>
> **Corrección exigida.** 1) Bajar el `<dl>` a atributos: no hace falta reestructurar el DOM. Un `<dl>` con un solo par `<dt>/<dd>` no aporta sobre `role="group"` + `aria-labelledby` apuntando al id del rótulo, y es la única parte que rompía compatibilidad. Sin cambio de DOM, el riesgo de versionado desaparece. 2) Derivar el texto accesible de `deltaDir` en PHP («subió 12 %» / «bajó 12 %»), no confiar en que el host mande el signo; el test actual pasa `delta="+3"` por convención y una convención no es una garantía. Y agrandar la flecha (8px es ilegible), para que el dato tampoco dependa del color en pantalla. 3) Prerrequisito que el candidato no vio: `.muni-num` está definido SOLO dentro del `@once` de `data-table.blade.php`. Si `stat`/`kpi` lo usan, en cualquier página sin tabla las cifras pierden la mono tabular. Hay que promoverlo a `resources/css/muni-ui.css` antes. 4) Igual la clase visualmente oculta: NO existe en el paquete; créala en `muni-ui.css` (clip-path/1px, nunca `display:none`), no en un `@once` de un componente, o repite la misma trampa. 5) Para `ring`, la referencia es interna, no daisyUI: copiar el bloque de `progress.blade.php`, que ya resuelve `role="progressbar"` + `aria-valuenow` + nombre accesible desde el `label` visible. 6) El esfuerzo «bajo» es honesto para los puntos 1-5, pero NO para el `role="status"` del polling: una live region solo anuncia si el nodo persiste y cambia su contenido; si el morph de Livewire reemplaza el contenedor, no se anuncia nada. Eso es un contrato documentado con el host más verificación con lector de pantalla real. Sácalo del alcance o súbelo a esfuerzo medio, en una tarea aparte.

### `toast-host`

**reparación** · prioridad 2 · esfuerzo medio

**Qué resuelve.** Es el canal por el que los seis sistemas confirman que un trámite se guardó o falló.
**Qué pasa hoy sin él.** No falta: está y no cumple. (1) `setTimeout(..., detail.duration \|\| 4500)` autodestruye el aviso a los 4,5 s sin pausa al puntero, sin pausa al foco y sin prórroga → WCAG 2.2.1 salvo que el mensaje quede disponible en otra parte; un folio de solicitud no se alcanza a leer ni a copiar. (2) `role="status"` fijo (línea 43) también para tone=danger: un error se anuncia en modo cortés, en cola. (3) Región viva anidada: el contenedor lleva aria-live=polite (línea 18) y cada hijo role=status, que ya implica región propia → redundante y doble anuncio en varios lectores. (4) Si el foco está en la × cuando vence el temporizador, cae al <body>. Además `$attributes` nunca se renderiza: no se le puede poner wire:ignore, id ni class.
**Lo más parecido que ya existe.** Es el propio componente. Se conserva lo bueno: el contrato por CustomEvent('muni-toast') sobre window, que encaja con $dispatch en Livewire 3 y 4 sin cambios, y los tonos por token dual.
**Referencia que lo hace mejor.** Creative Tim — refs/creativetimofficial_material-dashboard-laravel/src/material-stubs/resources/views/pages/notifications.blade.php:123-131 (trío role=alert + aria-live=assertive + aria-atomic); y CoreUI — refs/coreui_coreui-free-bootstrap-admin-template/src/pug/views/components/toasts.pug — Creative Tim es la única de las nueve familias que declara el trío ARIA correcto; las demás no anuncian nada. Y sus propios defectos (cierre con un <i> no focusable, ocho regiones vivas ya presentes al cargar) dicen exactamente qué no copiar. CoreUI es el único que reconoce por escrito que el autocierre sin pausa incumple el criterio de tiempo ajustable.
**Patrón.** alert (mensaje de estado). No es dialog: nunca debe robar el foco.
**Teclas obligatorias.** Tab; Shift+Tab; Enter; Espacio; Esc
**Alpine.** core
**Riesgo.** Al pasar a role=alert en el tono peligro hay que garantizar que la región nace vacía en el HTML inicial o se anuncia sola al cargar. Pausar por foco en una tablet sin puntero puede dejar avisos colgados: hace falta un tope. La duración debe escalar con el largo del texto, no ser fija.
**Dónde se usa.** «Solicitud N.º 2026-4831 ingresada — derivada a Obras» en la recepción de Atención al Vecino; «No se pudo guardar: el RUT ya tiene licencia clase B vigente» en el mesón de licencias.

> **Objeción del juez.** La premisa del candidato es falsa: dice que «es el canal por el que los seis sistemas confirman que un trámite se guardó o falló» y no lo es en ninguno. Dentro de un panel Filament el componente además es REDUNDANTE con las notificaciones propias de Filament, y dos sistemas de aviso apilándose en la misma esquina de la pantalla es peor accesibilidad que uno solo: el lector anuncia dos veces y el funcionario tiene dos pilas de cosas que cerrar. Un municipio con poco personal técnico podría razonablemente decidir que en el back-office manda Filament y que `toast-host` solo tiene sentido en las vistas públicas Blade — en cuyo caso el alcance de la reparación se reduce y conviene documentar explícitamente «no usar dentro de paneles Filament», que hoy el README no dice.
>
> **Corrección exigida.** 1) ARQUITECTURA ARIA — la propuesta del candidato (poner `role="alert"` en el hijo) no basta y arrastra un problema conocido: una región viva inyectada COMPLETA de golpe no se anuncia de forma fiable en NVDA/JAWS. Hacer: dos regiones vivas persistentes y VACÍAS desde la carga (`role="status"` + `aria-live="polite"` y `role="alert"` + `aria-live="assertive"`, ambas `aria-atomic="true"`, visualmente ocultas pero no `display:none`), a las que se inyecta SOLO el texto del aviso según el tono; y la pila visual de toasts SIN ningún rol ni `aria-live` (queda como interfaz, no como anuncio). Eso elimina de raíz el anidamiento redundante, el doble anuncio y el riesgo de «se anuncia sola al cargar» que el candidato declara. El selector de rol por tono se copia de `alert.blade.php:18`, que ya lo hace bien. 2) TIEMPO — no inventar prórroga: los tonos `danger` y `warn` NO autocierran nunca (persisten hasta cerrarlos), y `ok`/`info` autocierran con pausa al `mouseenter`, pausa mientras el toast contenga el foco (`focusin`/`focusout`, no `:focus-within` en JS) y reanudación al salir. El «tope para tablets sin puntero» que propone el riesgo sobra: en tablet no hay hover y el foco solo se queda dentro si el usuario lo puso ahí. 3) ESCALAR DURACIÓN por largo del texto está bien, pero con piso 5 s y techo 20 s, no libre. 4) BUG `\|\|` — cambiar `detail.duration \|\| 4500` por `??` para que `duration: 0` signifique «no autocerrar» (ya documentado en `docs/INVENTORY.md:1521`). 5) FOCO — al destruirse un toast que contiene el foco, devolverlo al toast anterior de la pila o al `document.body` de forma explícita, nunca por caída. 6) `Esc` ACOTADO — solo cierra el toast cuando el foco está DENTRO del toast; `Esc` global choca con modales y drawers del propio paquete. 7) Renderizar `{{ $attributes }}` en el contenedor (hoy se descartan `id`, `class`, `wire:ignore` en silencio) y añadir tope de pila (máx. 4 simultáneos, los nuevos empujan al más viejo). 8) `aria-label="Cerrar"` con `__()` y nombre accesible de la región. 9) README: decir que dentro de paneles Filament se usa `Notification::make()`, no este componente. 10) ESFUERZO — «medio» es optimista con la doble región viva y la pausa por foco: cuéntalo como medio-alto, con pruebas manuales en NVDA y VoiceOver, no solo un test de Blade.

### `ring`

**reparación** · prioridad 3 · esfuerzo bajo

**Qué resuelve.** El avance circular: un porcentaje mostrado como arco.
**Qué pasa hoy sin él.** Está y no expone nada. En ring.blade.php no hay role="progressbar", ni aria-valuenow, ni aria-valuemin, ni aria-valuemax, y el <svg> tampoco lleva aria-hidden: con showValue=false el dato existe únicamente como longitud de arco y color (1.4.1 y 1.3.1). Además anima con transition:stroke-dashoffset .8s literal en vez de var(--muni-dur), que muni-ui.css:245 anula bajo prefers-reduced-motion. El mismo defecto de duración literal está en progress (transition:width .5s) y conviene corregir los dos de una vez: son la misma familia.
**Lo más parecido que ya existe.** El propio ring; progress sí tiene el ARIA correcto y sirve de modelo dentro del mismo paquete.
**Referencia que lo hace mejor.** daisyUI — refs/saadeghi_daisyui/packages/daisyui/src/components/progress.css; y como contraejemplo obligatorio, Creative Tim — refs/creativetimofficial_argon-dashboard-laravel/src/argon-stubs/resources/views/pages/tables.blade.php:341 — daisyUI dibuja el arco con degradado cónico sobre una propiedad registrada (@property --radialprogress) para que sea animable sin JS, y respeta el movimiento reducido en el estado indeterminado. Creative Tim es la lección negativa medida: fija aria-valuemax igual a aria-valuenow en seis filas, así que el lector anuncia 100 % donde la pantalla muestra 30 %. Un ARIA mal calculado es peor que no tenerlo.
**Patrón.** ninguno: es contenido (role=progressbar)
**Teclas obligatorias.** ninguna: no es interactivo; lo obligatorio es que exponga nombre y valor
**Alpine.** no
**Riesgo.** Si el ring se actualiza por polling de Livewire, un aria-valuenow que cambia cada dos segundos dentro de una región viva satura al lector: la región viva queda apagada por defecto y el valor se lee solo cuando el usuario lo consulta.
**Dónde se usa.** El porcentaje de cupos ocupados de la agenda diaria de licencias; la proporción de solicitudes ARCOP respondidas dentro del plazo legal en el panel de privacidad.

> **Objeción del juez.** Dos objeciones. (1) La grave: el candidato copia el patrón de progress sin ver que la geometría es distinta. En progress el número vive FUERA del `div[role=progressbar]`; en ring el número está DENTRO del contenedor. Si se pone el role en el wrapper sin más, con el default `showValue=true` el lector anuncia el valor dos veces: «Cupos ocupados, barra de progreso, 78 por ciento» y después el texto «78%» otra vez. Se arregla un fallo y se introduce ruido. (2) Sustituir `.8s` por `var(--muni-dur)` no es neutro: `--muni-dur` vale 160ms (muni-ui.css:98), o sea cinco veces más rápido. Es una regresión visual disfrazada de fix de accesibilidad, y nadie la va a notar hasta que el arco «salte» en el dashboard.
>
> **Corrección exigida.** 1) Corregir las SC citadas: lo que falla es 4.1.2 (Nombre, Rol, Valor) y 1.1.1 (contenido no textual), no 1.4.1 ni 1.3.1. 1.4.1 (uso del color) no aplica acá y un auditor lo va a rebotar. 2) Poner `role="progressbar"` + `aria-valuenow`/`min`/`max` + `aria-label` (reutilizando `$label`, fallback «Progreso») en el div con `position:relative`; `aria-hidden="true"` en el `<svg>` Y TAMBIÉN en el span del porcentaje central cuando `showValue=true`, para evitar la doble lectura. Añadir `aria-valuetext="{{ round($pct) }} %"` para que se oiga «78 por ciento» y no un número pelado. `aria-valuemax` fijo en 100, nunca igual a valuenow (la lección de Creative Tim está bien traída). 3) No sustituir la duración por `var(--muni-dur)`: crear `--muni-dur-slow` (~600ms) que también baje a 0ms bajo prefers-reduced-motion, y usarlo. 4) Ampliar el alcance: la duración literal no está en 2 archivos sino en 4 — ring.blade.php:27 (.8s), progress.blade.php:35 (.5s), chart-donut.blade.php:38 (.7s), chart-bar.blade.php:47 (.6s). La barrida se hace de una vez en los cuatro. 5) chart-donut tiene EL MISMO hueco de ARIA (svg sin role ni aria-hidden, dato solo en el arco): o entra en este candidato o se abre aparte, pero no se puede seguir diciendo que la familia es «ring + progress». 6) Esfuerzo: «bajo» es honesto para ring solo; con el token nuevo, los 4 archivos y una prueba en ComponentesRenderTest.php que verifique el aria-valuenow, es media jornada, no una hora.

### `calendar`

**reparación** · prioridad 4 · esfuerzo alto

**Qué resuelve.** Elegir un día en una rejilla de mes usando solo el teclado.
**Qué pasa hoy sin él.** Cada día es un <button> enfocable, así que un mes son unas 31 paradas de Tab. No hay role=grid, ni tabindex itinerante, ni flechas, ni Inicio/Fin, ni RePág/AvPág. Ningún día tiene aria-label: el lector dice «5», no «viernes 5 de septiembre de 2026». El cambio de mes no se anuncia. Y los días que min descarta se pintan y se enfocan igual que los válidos: se hace clic y no pasa nada, sin disabled, sin aria-disabled y sin distinción visual (falla 3.3.x y 1.4.1). Aparte, $min se interpola dentro de la cadena de x-data y el escape de Blade no alcanza, porque el navegador decodifica las entidades antes de que Alpine parsee.
**Lo más parecido que ya existe.** Es el propio calendar.
**Referencia que lo hace mejor.** Flowbite Svelte, «selector de fecha» — themesberg_flowbite-svelte/src/lib/datepicker/Datepicker.svelte — Es la única de las cuatro implementaciones del catálogo con la rejilla completa y verificable en el clon: role=grid con columnheader y gridcell, aria-label por día con la fecha completa localizada, aria-selected, aria-disabled y aria-live=polite en el título del mes. La de React de la misma familia no tiene ni un role ni un KeyDown; la de Pines usa <div> con @click, no focalizables; shadcn y daisyUI delegan en librerías externas (react-day-picker, Cally) que en Blade puro no podemos meter.
**Patrón.** grid (la rejilla de fechas del patrón Date Picker Dialog de las APG)
**Teclas obligatorias.** Flechas: día anterior/siguiente y semana anterior/siguiente; Inicio y Fin: primer y último día de la semana; RePág y AvPág: mes anterior y siguiente; Shift+RePág y Shift+AvPág: año anterior y siguiente; Enter y Espacio: elegir el día enfocado; Tab como una sola parada: entra al día enfocado y sale de la rejilla
**Alpine.** core
**Riesgo.** El tabindex itinerante y x-for con :key="i" se pelean: al cambiar de mes Alpine reutiliza nodos y el foco queda en un botón que ahora es otro día, hay que reponerlo a mano. Con Livewire 3 el x-data se reinicia en cada re-render y se pierde el día elegido si no vive en un wire:model. El scrollIntoView y la transición de mes tienen que respetar prefers-reduced-motion (el componente ya está marcado como que lo ignora).
**Dónde se usa.** La agenda de licencias de conducir, donde el funcionario elige el día de la cita y solo algunos días tienen franja libre: justo el caso donde el <input type=date> nativo no sirve, porque no puede mostrar disponibilidad.

> **Objeción del juez.** La mejor objeción es que se está arreglando la pantalla equivocada con el presupuesto más caro. Las dos agendas reales de licencias eligieron deliberadamente no ser un calendario de mes, y la del portal resuelve mejor el problema que el candidato dice atacar: al listar solo días con cupo, la categoría entera de "días deshabilitados que confunden" desaparece: no hay nada que marcar con aria-disabled. Mientras tanto, el widget que sí falta —la grilla día+franja horaria, que el propio INVENTORY identifica como "sin componente equivalente"— sigue sin construirse y se reimplementa a mano en cada sistema. Gastar "esfuerzo alto" en una rejilla APG que ningún consumidor pidió, teniendo cero adopción, es optimizar un componente muerto. A eso se suma que el argumento técnico principal del candidato es falso: el escape de Blade sí funciona para fechas normales, el navegador decodifica `&#039;` a `'` y `new Date('2026-09-05')` evalúa bien; el defecto no es de renderizado sino de seguridad, y presentarlo como "está roto" desvía del riesgo verdadero.
>
> **Corrección exigida.** Partirlo en dos, porque hoy van con prioridades distintas. (1) AHORA, esfuerzo bajo (horas, prioridad real 2): cerrar el sink de `$min`. Dejar de interpolar dentro de la cadena de `x-data`; pasar la fecha por atributo escapado (`data-min="{{ $min }}"` leído con `$el.dataset.min`) y validar en PHP con `Carbon::hasFormat('Y-m-d')` fallando cerrado si no calza. Test que verifique que un `$min` hostil no llega a la expresión Alpine. De paso arreglar `disabled(d)`, que hoy muta `this.min` con `setHours(0,0,0,0)` —devuelve timestamp, no Date— y compara dos veces: normalizar `min` una sola vez al init. (2) DESPUÉS, condicionado a que exista una pantalla que lo consuma: la rejilla APG completa (role=grid con columnheader/gridcell, tabindex itinerante, aria-label con fecha larga localizada, aria-live=polite en el título, RePág/AvPág, Inicio/Fin). Correcciones al plan declarado: cambiar `:key="i"` por `:key="iso(c)"` —una clave estable por fecha elimina sola la mitad del riesgo que el candidato dice que habrá que gestionar a mano, porque Alpine deja de reusar nodos entre meses—; usar `aria-disabled` y no `disabled` en los días fuera de rango, que en un grid deben seguir siendo alcanzables y anunciables, y distinguirlos con algo que no sea solo color (1.4.1); y agregar prop de valor con `wire:model` para que la selección sobreviva al re-render de Livewire 3, que hoy la pierde. Por último, revisar el alcance del frente completo: si hay presupuesto para "esfuerzo alto", el componente que las dos agendas reales reutilizarían es un `slot-picker` de día+franja con cupo, no este calendario de mes.

### `sidebar`

**ampliación + reparación** · prioridad 4 · esfuerzo medio

**Qué resuelve.** Que la barra lateral cerrada deje de recibir el foco y que el estado se comporte igual al rotar la tablet.
**Qué pasa hoy sin él.** Bajo 900px la lateral cerrada es transform:translateX(-100%) (línea 25) pero sigue en el flujo y sigue siendo enfocable: sin inert, sin aria-hidden, sin visibility:hidden. Con Tab el foco entra en un menú que no se ve (WCAG 2.4.3 y 2.4.7). `open` se calcula una sola vez con window.innerWidth y no se recalcula nunca: quien rota la tablet o agranda la ventana se queda con el estado equivocado. No cierra con Escape, no atrapa el foco superpuesta, no lo devuelve al disparador. Es un <aside> (landmark complementary) sin nombre accesible, cuando la navegación principal debería exponer `navigation`. Y usa {{ $attributes }} crudo sobre un tag que ya trae class y style literales: si el consumidor pasa cualquiera, se emite duplicado.
**Referencia que lo hace mejor.** Flowbite Svelte — themesberg_flowbite-svelte/src/lib/sidebar/Sidebar.svelte — Es el único que decide por media query (no por una lectura única de innerWidth) si se comporta como columna fija o como panel modal, y solo en el segundo caso activa trampa de foco con retorno y Escape. Esa condicionalidad es lo que importa: atrapar el foco en escritorio sería peor que el bug actual.
**Patrón.** dialog (modal) mientras está superpuesta en móvil; en escritorio ninguno: es navegación (nav + lista)
**Teclas obligatorias.** Tab (contenido dentro del panel abierto en móvil); Shift+Tab; Escape (cierra y devuelve el foco al disparador); el panel cerrado NO debe recibir Tab
**Alpine.** core + focus
**Riesgo.** x-trap activo en escritorio dejaría al funcionario encerrado en el menú: hay que atarlo a matchMedia, no a la clase CSS. inert no está en navegadores viejos del municipio; el respaldo es visibility:hidden, que sí saca del orden de tabulación (translateX no). Cambiar <aside> por <nav> puede romper CSS de consumidores que seleccionen por elemento: versión menor, no parche.
**Dónde se usa.** El mesón de Licencias en tablet, y el operador de la central de cámaras que navega el turno completo con teclado.

> **Objeción del juez.** La aritmética lo refuta. A 1366px con lateral de 240px: columna de contenido 1126px, bajo el tope, caja útil 1078px. Con riel de 64px: 1302px, que el tope recorta a 1280 → caja útil 1232px. Con la lateral oculta del todo: 1366px, recortado igual a 1280 → caja útil 1232px. Riel y plegado total dan el MISMO ancho, y el riel cuesta cinco veces más. El único argumento que le quedaba al riel —«oculta del todo pierdo la navegación»— ya está cubierto por `command-palette.blade.php`, que existe, tiene Ctrl+K, `x-teleport` y `x-trap`. Y el punto más caro: los sustantivos municipales no tienen iconografía legible. «Giros», «Convenios de pago», «Patentes de alcoholes», «Notificaciones de cobro» no tienen icono que nadie reconozca sin la etiqueta; un riel de iconos solos con la palabra escondida hasta el foco convierte una navegación legible en una adivinanza para todos, no solo para lector de pantalla. El «muro de cámaras de la central» además es relleno: ahí no quieres un riel, quieres cero cromo (pantalla completa), que es otro componente. Y el esfuerzo «medio» es deshonesto para lo propuesto: la reaparición por foco no puede empujar el layout (a escritorio la `<aside>` es hermana en flex, no `fixed`, así que expandir por `:focus-within` reflowea la tabla entera en cada Tab), o sea que necesita un flotante posicionado, en dos temas, con nombre accesible propio porque `tooltip.blade.php` no sirve (la burbuja no tiene `id` ni `aria-describedby`, el `role="tooltip"` no hace nada y es `pointer-events:none`). Eso no es medio, es alto.
>
> **Corrección exigida.** 1) Cambiar el alcance: no es «modo riel», es «plegado con conmutador persistente». Tres estados reales: abierta (240px), plegada (0px, contenido a ancho completo) y el actual sobrepuesto bajo 900px. Se cae el riel, se caen los iconos solos, se cae el flotante por foco y se cae el problema de iconografía. Esfuerzo real: bajo, no medio. 2) Prerrequisito no negociable: primero la reparación del `sidebar`. Hoy el hamburguesa de `dashboard-shell.blade.php` línea 38 lleva `@click` sin ningún `x-data` antecesor en todo el archivo, así que Alpine nunca lo enlaza y el menú móvil NO ABRE; `.muni-ds__scrim` está estilada pero nunca se emite; la `<aside>` fuera de pantalla no lleva `inert`, así que el Tab entra a enlaces invisibles. Eso es prioridad 1 y es otro ticket. 3) Lo que de verdad resuelve las once columnas, y debería ir antes o junto: dar a `dashboard-shell` la prop `maxWidth` que `app-shell` ya tiene (hoy el 1280px está incrustado y ni el plegado ni el riel lo esquivan) y, en `data-table`, primera columna `position:sticky` más `tabindex="0"` y nombre accesible en el contenedor con `overflow-x:auto` —hoy es una región desplazable sin foco, que en Chrome no se recorre con teclado. 4) La cookie la pone la app anfitriona, no el paquete: el componente recibe `:collapsed="$collapsed"` como prop y el shell pinta la clase en el servidor. Así no hay parpadeo, el paquete sigue siendo Blade puro sin tocar `request()` ni `Cookie`, y sobrevive al morph de Livewire 3 y 4. La cookie es legible por JS por definición: solo el estado del panel, `SameSite=Lax`, jamás nada de sesión. 5) Nada de `@keydown.escape.window`: `command-palette` y `dropdown` ya enlazan Escape en window por instancia y se pisan entre sí; si el conmutador necesita Escape, va en la `<aside>`. 6) Conservar del candidato lo único que ninguna referencia trae y que sí vale: el conmutador con `aria-expanded` + `aria-controls` real, visible siempre en la topbar, con área de 44×44 para el funcionario en terreno con tablet.

### `breadcrumb`

**reparación** · **sin evaluar por un juez** · esfuerzo bajo

**Qué resuelve.** Que las migas sean una lista de verdad y que sus enlaces se distingan sin depender del color.
**Qué pasa hoy sin él.** Hoy son <span> y <a> sueltos dentro de un <nav>: sin <ol>/<li> el lector no anuncia la estructura ni la posición. Los enlaces llevan text-decoration:none y su único diferenciador frente al texto plano es el color (--muni-muted contra --muni-text): WCAG 1.4.1. No hay ninguna regla :focus-visible en el archivo. El aria-label="Ruta" se escribe ANTES de $attributes->merge, así que un aria-label del consumidor se emite duplicado y gana el primero. Y un ítem sin clave 'label' lanza undefined array key en producción.
**Referencia que lo hace mejor.** AdminLTE 4 — refs/ColorlibHQ_AdminLTE/src/html/pages/index.astro, entrada «Cabecera de contenido con título único y migas de pan» — No por el marcado (nav + <ol> + aria-current es el estándar) sino por la convención: la banda título+migas se repite idéntica en casi todas sus páginas y el propio proyecto verifica que haya un solo <h1>. Eso es lo que nos falta: page-header (que siempre emite un h1) y breadcrumb son piezas sueltas que cada sistema compone a su manera. La reparación debe aprovechar para darle a page-header un slot `migas`.
**Patrón.** ninguno: es contenido (navegación con nav + lista + aria-current="page")
**Teclas obligatorias.** Tab entre los enlaces; Enter; foco visible en cada enlace
**Alpine.** no
**Riesgo.** Técnico ninguno. El riesgo real es de proceso: si las migas se siguen escribiendo a mano por página van a divergir del menú; conviene derivarlas del mismo árbol que alimente a nav-menu (patrón CoreUI, refs/coreui_coreui-free-react-admin-template/src/components/AppBreadcrumb.jsx). En papel las migas deberían desaparecer.
**Dónde se usa.** Cada pantalla del panel de Discapacidad: Inicio › Credenciales › Solicitud 2026-0412.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

## 2. Componentes que faltan

| Pieza | Prio | Qué resuelve | Trámite donde se usa | Esfuerzo |
|---|---|---|---|---|
| `alert` | 2 | Separar tres cosas que hoy son una sola: la nota fija de instrucciones del trámite, el mensaje… | La franja «Sistema en mantención el sábado 12» del panel de licencias; la nota de requisitos d… | bajo |
| `cargando` | 2 | Decir que algo está en curso, en texto y no solo en movimiento, y decir cuándo terminó. | La búsqueda por RUT contra el maestro de personas en el mesón (tarda alrededor de un segundo);… | bajo |
| `combobox` | 2 | Buscar y elegir un registro dentro de una lista larga escribiendo, en vez de desplegar mil opc… | El buscador de titular en la recepción de solicitudes ARCOP —el campo que hoy tapa el parche d… | alto |
| `popover` | 2 | Un panel flotante anclado a un disparador, con contenido y controles dentro, que no bloquea la… | El panel de filtros por estado y fecha de la bandeja de solicitudes de Atención al Vecino; la … | medio |
| `checkbox-group` | 2 | Un grupo de casillas como un solo campo: fieldset con legend, y el error del grupo dicho una v… | Los requisitos que marca el funcionario al recibir una solicitud de patente y las categorías d… | bajo |
| `field` (prop muerta) | 4 | La envoltura de campo de la filter-bar declara una prop `name` que no usa en ninguna parte. | Cualquier filtro de la bandeja de Atención al Vecino o del listado de patentes morosas. | bajo |
| `campo-clave` | 3 | Escribir una contraseña pudiendo verla, y saber si es suficientemente robusta, con teclado y c… | El cambio de clave del funcionario y el restablecimiento de clave del vecino en Atención al Ve… | bajo |
| `conmutador-tema` | 3 | Que el funcionario elija claro, oscuro o «seguir el sistema», que la elección se recuerde y qu… | El turno de noche de la central de cámaras de seguridad ciudadana; y el mesón de licencias, do… | medio |
| `lista-descripcion` | 3 | El bloque de datos de una ficha como pares dato-valor con semantica real: <dl>/<dt>/<dd>. | La ficha del solicitante en la recepcion de licencias (RUT, nombre, domicilio, clase solicitad… | bajo |
| `lista-registros` | 3 | Presentar los mismos registros como lista de fichas cuando la tabla no cabe, sin romper la sem… | El vecino consultando el estado de su solicitud desde el celular en el portal de Atencion al V… | medio |
| `sesion-expira` | 3 | Avisar antes de que caduque la sesión y ofrecer prorrogarla explícitamente, en vez de perder u… | El formulario de ingreso de solicitud en la recepción de Atención al Vecino y la ficha social … | alto |
| `confirmar` | 4 | Detener una acción irreversible con una pregunta que nombra el registro afectado y, donde corr… | Anular una solicitud ya derivada en Atención al Vecino, con motivo obligatorio que va al log d… | medio |
| `densidad` | 4 | Un modo compacto global que reduce alto de fila y de control para que quepan más registros, si… | La bandeja de solicitudes de Atención al Vecino y el listado de patentes morosas, donde el fun… | alto |
| `gob-escudo` | 4 | Que el escudo municipal se lea en los dos temas sin el parche del recuadro blanco. | La cabecera de la central de cámaras en turno de noche; y el certificado de residencia que se … | bajo |
| `kbd` | 5 | Mostrar en pantalla la tecla o la combinación de un atajo. | El pie de la paleta de comandos del panel de licencias y la ayuda de atajos del mesón de atenc… | bajo |
| `nav-grupo` | — | Agrupar ítems del menú en una sección plegable que declara su estado y se abre sola cuando la … | El panel de Seguridad Ciudadana, donde Cámaras, Rondas y Denuncias cuelgan de «Operaciones» y … | bajo |
| `nav-menu` | — | Pintar el menú lateral completo desde un árbol de configuración, filtrando por permiso y marca… | El menú del panel de Licencias, donde «Exámenes médicos» solo lo ve el rol Médico y «Anular li… | medio |
| `tabs-ruta` | — | Solapas que llevan a rutas distintas, sin cargar todos los paneles ni perder el estado al nave… | La ficha del vecino en Atención al Vecino (Solicitudes · Documentos · Historial de contactos),… | bajo |

### `alert`

**ampliación** · prioridad 2 · esfuerzo bajo

**Qué resuelve.** Separar tres cosas que hoy son una sola: la nota fija de instrucciones del trámite, el mensaje flash tras una acción y la franja de estado de todo el sistema.
**Qué pasa hoy sin él.** alert decide el rol por el tono: danger → role="alert", todo lo demás → role="status". Así una nota permanente («Adjunte fotocopia de la cédula por ambos lados») queda declarada como mensaje de estado y, si hay tres en la página, se anuncian todas de golpe al entrar: el defecto exacto que la auditoría midió en Creative Tim. Tampoco hay forma de cerrarla ni de recordar que se cerró, así que la franja «Sistema en mantención el sábado» hoy se pone a mano en cada layout.
**Lo más parecido que ya existe.** alert
**Referencia que lo hace mejor.** AdminLTE — refs/ColorlibHQ_AdminLTE/src/scss/_callouts.scss (distinción conceptual); TailAdmin — refs/TailAdmin_free-react-tailwind-admin-dashboard/src/components/ui/alert/Alert.tsx (variantes con icono propio); Pines — refs/thedevdojo_pines/elements/banner.html (la franja) — AdminLTE es el único de los nueve que separa explícitamente el llamado persistente de la alerta transitoria, que es justo la distinción que a alert le falta. TailAdmin da un icono distinto por variante, así que el estado no queda solo en el color (1.4.1). Pines documenta el fallo que hay que evitar: los tres de esa familia dejan el foco huérfano al cerrar el bloque que lo contenía.
**Patrón.** ninguno: es contenido. El rol (alert, status o note) debe ser decisión del consumidor, no del tono.
**Teclas obligatorias.** Tab hasta la × (un <button> real con nombre accesible); Enter; Espacio
**Alpine.** no para la nota fija; core para descartar y recordar el descarte
**Riesgo.** (1) El icono se imprime con {!! $icon !!} — HTML crudo: si algún sistema le pasa contenido del vecino es XSS; hay que documentarlo o cerrarlo mientras se toca el archivo. (2) Ya está medido en docs/INVENTORY.md: --muni-muted sobre --muni-ok-bg en oscuro da 4,32:1 a 13 px, bajo el 4,5:1 exigido; al abrir el componente hay que arreglarlo. (3) Recordar el descarte en localStorage es por navegador y por equipo, no por funcionario: en un mesón compartido confunde.
**Dónde se usa.** La franja «Sistema en mantención el sábado 12» del panel de licencias; la nota de requisitos del formulario de patente comercial; el flash «Solicitud derivada a Obras» tras guardar en Atención al Vecino.

> **Objeción del juez.** El candidato empaqueta un arreglo de veinte líneas junto con una feature que no hace falta. Lo que incumple la ley se arregla cambiando un token de color, agregando cuatro SVG y sacando el `role` de fuera del bag: nada de eso necesita franja de sistema, botón de cierre ni memoria de descarte. Y la parte nueva se apoya en una premisa que el propio candidato reconoce falsa — recordar el descarte en `localStorage` es por navegador, no por funcionario, así que en el mesón de atención de patentes el primero que cierre la franja «Sistema en mantención el sábado» se la esconde a todos los turnos siguientes en ese equipo. Además el tercer caso que dice separar, el flash tras guardar, ya lo cubre `toast-host` con su propio evento `muni-toast`: no son tres cosas mezcladas, son dos. Aprobarlo tal cual es pagar una función que confunde para conseguir un arreglo de contraste.
>
> **Corrección exigida.** Partirlo en dos y aprobar solo la primera mitad. (1) Arreglo obligatorio de `alert`: prop `role` con default `null` (sin región viva; el consumidor declara `alert` o `status` cuando el mensaje de verdad lo amerite) y eliminar el `role` hardcodeado de la línea 18 para que deje de duplicarse; un icono propio por tono, cuatro SVG, para que el estado no quede solo en color; el cuerpo del mensaje deja de usar `--muni-muted` y pasa a `--muni-text` o a un token nuevo `--muni-alert-body` que pase 4,5:1 sobre los cuatro fondos en ambos temas; test de contraste en la suite, que hoy solo comprueba el `role` en `ComponentesRenderTest.php:90`. (2) Cerrar el `{!! $icon !!}`: aceptar un nombre del catálogo en vez de HTML crudo, o dejar documentado que jamás recibe datos del vecino — el mismo patrón está en `input.blade.php:22` y conviene propagar el criterio. (3) La franja del sistema: sin `localStorage`. Se apaga sola por fecha en configuración del servidor, así funciona igual en cualquier equipo del mesón y sobrevive a `wire:navigate`. Si se agrega el botón de cierre, que sea `<button type="button">` real que devuelva el foco al elemento anterior — el fallo que el candidato bien identifica en Pines. (4) Sacar del alcance el caso del flash: es de `toast-host`. (5) El esfuerzo «bajo» vale solo para los puntos 1 y 2; cambiar el `role` por defecto es un cambio de comportamiento en los siete sistemas y exige nota de migración.

### `cargando`

**componente nuevo** · prioridad 2 · esfuerzo bajo

**Qué resuelve.** Decir que algo está en curso, en texto y no solo en movimiento, y decir cuándo terminó.
**Qué pasa hoy sin él.** skeleton existe y reserva el espacio —bien para CLS— pero lleva aria-hidden="true", así que la espera es literalmente invisible para un lector de pantalla; y no hay ningún indicador de carga en los 53 componentes. Hoy cada sistema pone un div propio con wire:loading, sin role, sin nombre accesible y sin respetar movimiento reducido.
**Lo más parecido que ya existe.** skeleton, que es el compañero y no el sustituto: uno reserva el espacio, el otro anuncia.
**Referencia que lo hace mejor.** shadcn/ui — refs/shadcn-ui_ui/apps/v4/registry/new-york-v4/ui/spinner.tsx (semántica) y daisyUI — refs/saadeghi_daisyui/packages/daisyui/src/components/loading.css (movimiento y dibujo) — shadcn es el único que declara role=status con nombre accesible, que es el mínimo correcto. daisyUI es el único de las nueve familias que resuelve bien el movimiento reducido —envuelve la animación en @media (prefers-reduced-motion: no-preference), o sea anima solo si se permite en vez de animar y apagar después— y dibuja con máscaras SVG en data URI, sin fuente de iconos ni imágenes externas, que es lo que necesitamos con CSP estricta.
**Patrón.** ninguno: es contenido (role=status y aria-busy en la región que se reemplaza)
**Teclas obligatorias.** ninguna; el requisito es de foco: el control que dispara la carga no puede desaparecer mientras lo tiene
**Alpine.** no (wire:loading funciona igual en Livewire 3 y 4); core solo para el retardo de ~300 ms que evita el parpadeo en respuestas rápidas
**Riesgo.** (1) Anunciar «cargando» en cada tecla de un buscador en vivo satura al lector: hace falta retardo y anunciar el resultado, no el proceso. (2) El patrón correcto es aria-busy en la región que se reemplaza, no una ruedita suelta al lado. (3) En Livewire 3, un wire:loading dentro de un <template x-for> queda huérfano.
**Dónde se usa.** La búsqueda por RUT contra el maestro de personas en el mesón (tarda alrededor de un segundo); la generación del padrón de patentes morosas; la carga del listado de cámaras del CAD.

> **Objeción del juez.** La mejor objeción es que el componente, tal como está planteado, no cumple lo que promete y puede quedar de adorno que da falsa sensación de cumplimiento. Leí la implementación de `wire:loading` en `vendor/livewire/livewire/dist/livewire.esm.js` (v4.4.1): `toggleBooleanStateDirective` (línea 13026) alterna `el.style.display` entre `none` e `inline-block`. Un `<span role="status" wire:loading>Cargando…</span>` es una live region que pasa de no renderizada a renderizada, y ese caso NO se anuncia de forma fiable en NVDA, JAWS ni VoiceOver — es el bug clásico de las live regions creadas u ocultas al momento del cambio. O sea, la versión obvia del componente escribe `role="status"` y sigue sin anunciar nada. Segunda parte de la objeción: lo que de verdad cierra el 4.1.3 no es la ruedita sino anunciar el RESULTADO («14 licencias encontradas») en una región persistente, y eso lo tiene que poner la página, no el paquete. Tercera: los cuatro paneles Filament ya traen `x-filament::loading-section` (que sí lleva `role="status"`, `aria-busy="true"` y texto `.fi-sr-only`) y `x-filament::loading-indicator` con `motion-safe:animate-spin` — o sea, dentro del panel el vacío es menor de lo que declara el candidato; el hueco real está en las vistas Blade fuera de Filament (mesón, portal de atención al vecino, tablero del CAD).
>
> **Corrección exigida.** 1) Alcance: no es un componente, son dos piezas. (a) el dibujo — aria-hidden, decorativo, con la animación envuelta en `@media (prefers-reduced-motion: no-preference)` como daisyUI, dibujado con CSS/SVG en línea por la CSP estricta; (b) el patrón de región, que es donde está el valor: `role="status"` persistente y vacío SIEMPRE presente en el DOM, `wire:loading.attr="aria-busy"` sobre el contenedor que se reemplaza, y un slot para el mensaje de resultado que se renderiza en el servidor al terminar. Documentar que sin (b), (a) no cumple nada. 2) Nombre: `cargando` rompe la convención del paquete, que es inglés salvo el prefijo institucional `gob-*` (skeleton, empty-state, toast-host, sortable-table). Usar `spinner` para el dibujo y `busy-region` (o `status-region`) para la región. 3) Alpine: cero. El retardo antiparpadeo ya es nativo — `wire:loading.delay.long` son exactamente 300 ms, está en la tabla `delayModifiers` del propio Livewire (esm.js línea 16616) y existe igual en Livewire 3. Quitarlo del presupuesto. 4) Esfuerzo: de «bajo» a «bajo el dibujo, medio la región». La parte semántica no se puede declarar lista sin probarla con NVDA y VoiceOver reales sobre el buscador por RUT; con solo tests de Pest esto se entrega roto. 5) Documentar dos límites: no anidarlo dentro de `<template x-for>` en Livewire 3, y no usarlo dentro de paneles Filament donde `x-filament::loading-section` ya resuelve el caso.

### `checkbox-group`

**componente nuevo** · prioridad 2 · esfuerzo bajo · **sin juez**

**Qué resuelve.** Un grupo de casillas tratado como un solo campo: `<fieldset>` con `<legend>`, y el error del grupo dicho una vez para el conjunto, no repetido en cada casilla.
**Qué pasa hoy sin él.** `<x-muni::checkbox>` resuelve la casilla suelta —nativa, nunca premarcada, con descripción atada y blanco de pulsación de 24 px— pero un grupo de casillas no es la suma de sus casillas: sin `fieldset`/`legend` el lector de pantalla no dice de qué grupo forma parte cada una, y el error («elige al menos un requisito») no tiene dónde vivir. Hoy cada sistema lo arma con `<div>` y un `<p>` de error suelto.
**Lo más parecido que ya existe.** `checkbox` (la pieza) y `error-summary` (el resumen del formulario, que enlaza a un campo, no a un grupo).
**Referencia que lo hace mejor.** Pendiente: esta ficha se anotó desde la corrección del panel de jueces sobre `checkbox` y no llegó a evaluarse.
**Patrón.** ninguno especial; `fieldset`/`legend` nativo, sin `role="group"` redundante
**Teclas obligatorias.** Tab entra al grupo y recorre las casillas (no es un radiogroup: cada casilla es su propia parada); Espacio marca
**Alpine.** no
**Riesgo.** Bajo. El único punto real: el error del grupo tiene que atarse con `aria-describedby` al `fieldset`, no a cada `input`, o el lector lo repite tantas veces como casillas haya. Ley 21.719: un grupo de casillas de consentimiento **nunca** viene premarcado — el consentimiento pre-marcado no es consentimiento válido.
**Dónde se usa.** Los requisitos que marca el funcionario al recibir una solicitud de patente comercial, y las categorías de discapacidad en la ficha de inscripción.

> **Por qué está acá y no antes.** El panel de jueces la pidió como ficha aparte al corregir `checkbox`, y no quedó anotada en su momento. Es un error de contabilidad del backlog, no una decisión de descartarla.

### `field` (prop muerta)

**reparación** · prioridad 4 · esfuerzo bajo · **sin juez**

**Qué resuelve.** `resources/views/components/field.blade.php` declara `@props(['label' => null, 'name' => null])` y **nunca usa `$name`**: la única aparición de la palabra en el archivo es la propia declaración.
**Qué pasa hoy sin él.** Un consumidor que pase `name="estado"` creyendo que ata la etiqueta al control no obtiene nada, y no hay ningún error. La asociación hoy funciona por otra vía —el componente envuelve el control dentro de un `<label>`, que es asociación implícita válida— así que el defecto es de contrato, no de accesibilidad.
**Lo más parecido que ya existe.** El propio `field`, usado solo por `filter-bar`.
**Referencia que lo hace mejor.** No aplica.
**Patrón.** ninguno
**Teclas obligatorias.** ninguna propia
**Alpine.** no
**Riesgo.** El paquete es **aditivo**: quitar la prop está prohibido. Las dos salidas honestas son usarla (emitir `for` explícito además del anidamiento) o documentarla como aceptada y sin efecto. Elegir sin medir quién la pasa sería adivinar.
**Dónde se usa.** Cualquier filtro de la bandeja de Atención al Vecino o del listado de patentes morosas.

### `borde-de-superficie`

**reparación** · prioridad 3 · esfuerzo bajo · **sin juez**

**Qué resuelve.** El borde que separa una superficie flotante del fondo de la página.
**Qué pasa hoy sin él.** Medido en navegador real al verificar `popover`: `--muni-border` contra el fondo de la página da **1,19:1 en claro y 1,52:1 en oscuro**. La separación real hoy la hace `--muni-shadow-lg`, no el borde, así que quien tenga las sombras desactivadas —o imprima, donde no salen— pierde el límite del panel. Afecta por igual a `popover`, `modal`, `dropdown` y `card`: no es un defecto de un componente, es de los tokens.
**Lo más parecido que ya existe.** El precedente exacto es `--muni-field-border`, que se creó por la misma razón para los controles de formulario y se declaró en los **dos** CSS publicables.
**Referencia que lo hace mejor.** No aplica: es una decisión de tokens del propio paquete.
**Patrón.** ninguno
**Teclas obligatorias.** ninguna
**Alpine.** no
**Riesgo.** WCAG 1.4.11 exige 3:1 para identificar un **componente de interfaz**; un panel delimitado además por sombra es discutible que caiga bajo ese criterio, y por eso esto es prioridad 3 y no 1. Lo que no es discutible es que en impresión la sombra no existe. Antes de tocar nada hay que decidir si el borde sube a 3:1 en todas las superficies —lo que endurece bastante el aspecto— o si se crea un token propio solo para las flotantes.
**Dónde se usa.** Cualquier pantalla con panel flotante o tarjeta; y toda la impresión, donde la sombra no se dibuja.

### `campo-clave`

**componente nuevo** · prioridad 3 · esfuerzo bajo

**Qué resuelve.** Escribir una contraseña pudiendo verla, y saber si es suficientemente robusta, con teclado y con lector de pantalla.
**Qué pasa hoy sin él.** En las pantallas de ingreso, cambio y restablecimiento de clave de Atención al Vecino y del registro de discapacidad no hay alternador: se escribe a ciegas. O peor, se copia el patrón habitual, que es el que documenta la referencia de Sneat: un <i> con un click, que no recibe foco, no responde a Enter ni a Espacio y no tiene nombre accesible. Con teclado la función directamente no existe.
**Lo más parecido que ya existe.** input con type="password" da la etiqueta y el error, pero no tiene el botón, ni el estado, ni el medidor.
**Referencia que lo hace mejor.** Preline, «medidor de robustez de contraseña y mostrar/ocultar» — refs/htmlstreamofficial_preline/src/plugins/strong-password/core.ts y el toggle-password/core.ts de al lado — Es la implementación más completa del comportamiento (reglas configurables, cálculo de nivel, y el panel que decide abrir hacia arriba cuando no cabe abajo) y su ficha enumera con precisión los tres agujeros que hay que tapar: el botón no cambia su aria-pressed ni su nombre accesible, el panel de reglas no es región viva y el nivel se comunica solo por color. Se toma la conducta y se descarta el marcado.
**Patrón.** ninguno: es un <input> más un botón de alternancia con aria-pressed
**Teclas obligatorias.** Tab hasta el botón Mostrar/Ocultar, que va DESPUÉS del campo; Enter y Espacio para alternar sin perder el punto de inserción ni el valor; Escritura nativa del campo; Ningún atajo global: no capturar teclas de la ventana
**Alpine.** core
**Riesgo.** Alternar type entre password y text hace que algunos gestores de contraseñas vuelvan a ofrecer autocompletado y que algunos navegadores pierdan la posición del cursor: hay que reponerla. Revelar la clave en un mesón de atención es un riesgo real de observación por encima del hombro: por defecto oculta, y el estado no se recuerda entre campos ni entre páginas. El medidor no bloquea el envío ni es el único portador de la información: la regla incumplida va en texto. Y la clave no se manda a ninguna parte para medirla: se calcula en el cliente.
**Dónde se usa.** El cambio de clave del funcionario y el restablecimiento de clave del vecino en Atención al Vecino.

> **Objeción del juez.** El candidato son tres cosas cobradas como una y con esfuerzo "bajo" que solo es honesto para la primera. El alternador es media hora; el medidor de robustez es la parte cara y la peligrosa, porque la política real vive en el servidor (`Password::defaults()` con `min`, `mixedCase`, `numbers`, `symbols` y sobre todo `uncompromised`, que consulta HIBP). Un medidor de cliente no puede evaluar `uncompromised`: le va a decir "robusta" al vecino y el servidor le va a rechazar la clave — peor que no tener medidor. Y la segunda objeción es de alcance: seis pantallas en dos repos se resuelven con diez líneas de Alpine inline; el componente solo se paga si es de verdad la fuente única que impide que se vuelva a copiar el `<i>` clickeable de Sneat.
>
> **Corrección exigida.** 1) Partirlo en dos entregas y dejar de declarar "bajo" para el conjunto: (a) revelar la clave — esfuerzo bajo, real; (b) medidor — esfuerzo medio, opcional, y solo si algún sistema lo pide. 2) No reimplementar el campo: envolver `<x-muni::input>`, que ya tiene label, `error`, `hint`, `required` y el foco de 3px; duplicarlo abre una segunda verdad de estilo de campo. 3) Renombrar a `input-password` por consistencia con `otp-input` y `file-dropzone`; `campo-clave` se lee como campo llave. 4) El alternador lleva `type="button"` obligatorio: dentro de un `<form>` un botón sin type es submit y alternar enviaría el formulario de login — ese es el bug clásico y el candidato no lo menciona. 5) Forzar `autocomplete` por prop (`current-password` en ingreso, `new-password` en cambio y restablecimiento): es lo que hace funcionar al gestor de contraseñas, que es la vía que WCAG 2.2 SC 3.3.8 sí exige, y hoy falta en atencionvecino/resources/views/auth/login.blade.php:63-68. 6) El medidor no trae política propia: recibe las reglas por prop desde la política del sistema, nunca decide por su cuenta, jamás usa `role="progressbar"` (el lector cantaría un porcentaje sin significado), anuncia por `aria-live="polite"` con retardo de ~500 ms y no en cada tecla, y el nivel va también en texto además del color. 7) Ocultar automáticamente al enviar y al perder el foco el campo, sin recordar el estado entre campos ni entre páginas: en el mesón de atención el riesgo de mirada por encima del hombro es real.

### `conmutador-tema`

**componente nuevo** · prioridad 3 · esfuerzo medio

**Qué resuelve.** Que el funcionario elija claro, oscuro o «seguir el sistema», que la elección se recuerde y que se aplique antes del primer pintado.
**Qué pasa hoy sin él.** Hoy el tema es un prop de servidor congelado: app-shell declara 'theme' => 'light' y lo escribe como data-muni-theme en el <html>, y el bloque @media (prefers-color-scheme: dark) de muni-ui.css:111 está excluido con :root:not([data-muni-theme="light"]) — o sea el valor por defecto desactiva activamente la preferencia del sistema operativo, y no hay ningún control para cambiarlo. En la central de cámaras, en turno de noche y con la sala a oscuras, se trabaja en claro porque no hay dónde cambiarlo. Segundo agujero del mismo trabajo: `color-scheme` no aparece en ningún CSS del paquete, así que en oscuro los desplegables nativos, los input type=date, las barras de desplazamiento y la vista previa de impresión se siguen pintando en claro.
**Lo más parecido que ya existe.** La cascada de tokens de muni-ui.css:111-200 ya soporta las tres estrategias (SO, atributo, clase) con la precedencia bien pensada. Falta el control, la persistencia y el guion previo al pintado.
**Referencia que lo hace mejor.** AdminLTE 4 — refs/ColorlibHQ_AdminLTE/src/ts/color-mode.ts y refs/ColorlibHQ_AdminLTE/src/html/components/_head.astro; complemento obligatorio: Materio — refs/themeselection_materio-mui-nextjs-admin-template-free/typescript-version/src/@core/utils/serverHelpers.ts; y el control sin JS de daisyUI (theme-controller con :root:has(input[value=X]:checked)). — AdminLTE es el único de los nueve con precedencia explícita (elección guardada → tema declarado por la página → preferencia del sistema), con opt-out real (sigue al SO solo mientras «sistema» sea la elección efectiva), con aria-pressed sincronizado y marcando con un atributo propio lo que calculó él, para no confundir tema autorado con tema resuelto. Materio aporta lo que a AdminLTE le cuesta: la cookie leída en el servidor elimina el parpadeo SIN script en línea, y bajo la CSP con nonce del ecosistema un script inline en <head> es un costo real. daisyUI aporta el control mismo sin una línea de JS, con el checked renderizado por Blade.
**Patrón.** radiogroup (tres opciones excluyentes); la alternativa de tres botones con aria-pressed sería toolbar, pero radiogroup describe mejor «elija uno de tres»
**Teclas obligatorias.** Tab; ArrowLeft; ArrowRight; ArrowUp; ArrowDown; Espacio
**Alpine.** no en la variante recomendada (radios nativos + :has() + cookie renderizada por Blade); core solo si se quiere sincronizar entre pestañas abiertas y seguir en caliente el cambio de preferencia del SO
**Riesgo.** (1) Parpadeo: con cookie leída en servidor no hace falta script; con localStorage sí, y entonces hace falta nonce. (2) :has() va dentro de @supports, con el atributo en <html> como fallback. (3) Discapacidad tiene identidad fija en claro: el conmutador debe poder desactivarse por sistema, y la regla [data-muni-theme="light"] de muni-ui.css:201 ya está preparada. (4) chart-bar y chart-donut leen tokens al renderizar: hay que emitir un evento de repintado, como el MainChart.vue de CoreUI. (5) Ningún referente anuncia el cambio: hay que anunciarlo con `anuncios`.
**Dónde se usa.** El turno de noche de la central de cámaras de seguridad ciudadana; y el mesón de licencias, donde el mismo equipo se usa de día contra un ventanal.

> **Objeción del juez.** La central de cámaras y el mesón de licencias corren sobre paneles Filament (`composer.json:12` declara `filament/filament: ^5.0` con el plugin MuniPanel), y Filament ya trae su conmutador claro/oscuro/sistema con persistencia propia, que `muni-ui.css:157` ya respeta vía `.dark`. O sea que el caso de uso municipal que el candidato declara para justificarse es justamente donde el conmutador ya existe. Meter un segundo conmutador en el paquete crea dos fuentes de verdad que se pisan: el funcionario cambia con el de muni-ui, Filament conserva su valor en localStorage y al recargar el tema vuelve atrás. Eso es peor que no tenerlo. Donde el conmutador sí faltaría —login, error, portales públicos servidos por los shells— lo correcto es seguir al SO, no ofrecer un control. Además dos riesgos del candidato son falsos: `chart-bar` y `chart-donut` no leen tokens por JS (son SVG con `var(--muni-*)` en línea y repintan solos, no hace falta evento de repintado), y el componente `anuncios` del que depende el riesgo 5 no existe en el paquete —el único `aria-live` es el de `toast-host.blade.php:30`.
>
> **Corrección exigida.** Partirlo en dos y quedarse con lo barato. (A) Arreglo de defectos, sin componente, esfuerzo bajo: añadir `color-scheme: light` al `:root` claro y a `[data-muni-theme="light"]` (muni-ui.css:201), y `color-scheme: dark` a los dos bloques oscuros (línea 112 y 157); y cambiar el default `'theme' => 'light'` a `null` en los tres shells, emitiendo el atributo `data-muni-theme` solo cuando venga con valor, que es lo que el propio README ya documenta como «PWA que sigue el OS». Discapacidad conserva su claro fijo pasando `theme="light"` explícito. Esto solo ya resuelve el turno de noche en todo lo que no es Filament y arregla los controles nativos. (B) El conmutador, si después se pide: no es componente nuevo sino un envoltorio de `segmented` con `name="muni-tema"` y tres opciones (`sistema`/`claro`/`oscuro`), persistido en cookie leída por el shell —nunca localStorage, porque bajo la CSP con nonce el script en línea es costo real. Renombrarlo `selector-tema` para no sugerir un interruptor de dos estados cuando son tres. Documentarlo como desactivable por sistema y prohibido dentro de paneles Filament, para no crear la doble fuente de verdad; sobre `segmented`, el teclado que declara ya lo da el navegador. Bajar «medio» a bajo en (A) y subirlo a alto en (B): cookie, ruta con CSRF, no pisar Filament, y tests de contraste en ambos temas no son una tarde.

### `lista-descripcion`

**componente nuevo** · prioridad 3 · esfuerzo bajo

**Qué resuelve.** El bloque de datos de una ficha como pares dato-valor con semantica real: <dl>/<dt>/<dd>.
**Qué pasa hoy sin él.** No existe ni un solo <dl> en todo el paquete (verificado con grep sobre resources/views/). Las fichas se arman con <div> sueltos, etiqueta arriba y valor abajo, sin ninguna relacion semantica entre uno y otro; o peor, con una tabla de dos columnas, que le dice al lector de pantalla que hay una cuadricula de datos donde solo hay una ficha.
**Lo más parecido que ya existe.** card da el marco pero no el contenido; field es para formularios (entrada editable), no para datos de solo lectura; stat y kpi son para una cifra destacada, no para diez pares.
**Referencia que lo hace mejor.** themesberg_flowbite-svelte/src/routes/blocks/application/crud-read-drawers.md, que describe el bloque de datos principales en pares etiqueta/valor mas datos secundarios, adjuntos y pie de acciones; para la primitiva de fila, refs/shadcn-ui_ui/apps/v4/registry/new-york-v4/ui/item.tsx. — Porque es la unica referencia que trata la ficha como una composicion con jerarquia (principales / secundarios / adjuntos) en vez de una lista plana, que es como se lee de verdad un expediente municipal.
**Patrón.** ninguno: es contenido. Lo que importa es no romper la semantica dl/dt/dd al maquetar en dos columnas (grid sobre el <dl> con dt/dd como hijos directos la conserva).
**Teclas obligatorias.** ninguno: no es interactivo, pero el texto debe ser seleccionable y copiable (el funcionario copia el RUT)
**Alpine.** no
**Riesgo.** Al imprimir hay que impedir que un par se parta entre dos hojas (break-inside:avoid). En pantalla estrecha las dos columnas deben colapsar a una, y con container queries eso va dentro de @supports. RUT y montos tienen que llevar .muni-num o las cifras bailan al actualizarse.
**Dónde se usa.** La ficha del solicitante en la recepcion de licencias (RUT, nombre, domicilio, clase solicitada, vencimiento de la anterior) y el bloque «datos del titular» del panel ARCOP, que Juridica revisa e imprime.

> **Objeción del juez.** Es HTML nativo sin una sola línea de comportamiento, y ese es el riesgo: un componente que solo envuelve `<dl>` con estilos en línea agrega indirección y una API para algo que se escribe en tres líneas, y si se queda corto en el primer caso real —un valor que necesita un badge de estado, un enlace al expediente, un botón de copiar el RUT— todos vuelven al div y el componente queda muerto en el paquete, como pasó con las 20 instancias que ya existen. Se mitiga solo si la API es por slots (`<x-muni::description-item label="RUT">` con contenido libre) y no un array de pares. Objeción secundaria: la mitad del valor prometido está en la impresión, y el paquete hoy no tiene ninguna regla `@media print` — si Jurídica de verdad imprime, ese problema es mayor que este componente y no lo resuelve un `break-inside:avoid` suelto.
>
> **Corrección exigida.** 1) Nombre: `description-list` (+ `description-item`), no `lista-descripcion` — los 53 componentes están en inglés salvo los `gob-*` institucionales, y el INVENTORY ya lo bautizó así. 2) Debe emitir su propia definición de `.muni-num` en su `@once`: hoy la clase vive solo dentro del `@once` de `data-table.blade.php:40`, así que en un drawer de ficha sin tabla en pantalla el RUT sale en sans proporcional — exactamente el defecto que el componente dice prevenir. 3) Recortar el alcance a la lista de pares: la jerarquía principales/secundarios/adjuntos de la referencia Flowbite no cabe en un `<dl>`; la da `card` (que ya tiene slot `$actions`) y los adjuntos son otro candidato, listado aparte en el mismo INVENTORY. Sin ese recorte el esfuerzo deja de ser bajo. 4) Sin container queries: `display:grid` sobre el `<dl>` con `dt`/`dd` como hijos directos, una columna por defecto y dos con un media query simple; el `@supports` que propone es complejidad gratuita para un colapso que el CSS de siempre resuelve. 5) La regla de impresión, acotada a `break-inside:avoid` sobre el par propio dentro de `@media print` en su `@once`; no introducir una hoja de impresión global de contrabando en un componente. 6) API por slots, no por array de pares, para que el valor admita badge, enlace o texto mono.

### `lista-registros`

**componente nuevo** · prioridad 3 · esfuerzo medio

**Qué resuelve.** Presentar los mismos registros como lista de fichas cuando la tabla no cabe, sin romper la semantica: celular del vecino, tablet del inspector.
**Qué pasa hoy sin él.** Hoy la tabla se desplaza en horizontal (y, segun la reparacion de data-table, ni siquiera con teclado). En el portal publico, un vecino en un telefono de 390 px arrastra una tabla de ocho columnas.
**Lo más parecido que ya existe.** data-table con su scroll horizontal; timeline no sirve porque no lleva acciones por fila; card obliga a maquetar cada ficha a mano.
**Referencia que lo hace mejor.** refs/shadcn-ui_ui/apps/v4/registry/new-york-v4/ui/item.tsx (fila con medios, titulo, descripcion y acciones, agrupable) y refs/saadeghi_daisyui/packages/daisyui/src/components/list.css. Como contraejemplo deliberado, refs/justboil_admin-one-vue-tailwind/src/css/_table.css. — Por comportamiento: la solucion de justboil es la tentadora (una regla CSS con display y ::before content:attr(data-label)) y es justamente la que no se puede adoptar, porque el catalogo advierte que destruye la relacion fila/columna para lectores de pantalla en la vista movil y que la etiqueta generada no es texto seleccionable. Una lista real ul/li conserva la semantica en ambos tamanos y el vecino puede copiar su numero de folio.
**Patrón.** ninguno: es contenido (lista real; role=listitem explicito si el CSS cambia el display de los <li>, porque eso puede eliminar la semantica de lista)
**Teclas obligatorias.** Tab; Enter (abrir la ficha); Space si la fila es un boton; area tactil 44x44 px, no 24: es terreno
**Alpine.** no
**Riesgo.** Renderizar tabla y lista a la vez duplica el DOM y, si se ocultan con CSS, un lector de pantalla puede leer las dos (hay que usar hidden/aria-hidden, no opacity), ademas de duplicar el trabajo del servidor por fila. Elegir una sola en el servidor no conoce el ancho de pantalla. Recomendacion: una sola marca en el HTML, con container queries dentro de @supports y una consulta de medios como respaldo.
**Dónde se usa.** El vecino consultando el estado de su solicitud desde el celular en el portal de Atencion al Vecino, y el inspector revisando en la tablet sus ordenes de trabajo del dia.

> **Objeción del juez.** Dos objeciones, la segunda es fatal para el diseño propuesto. (1) Sobre-abstracción: el paquete ya tiene `card` + `badge` + `button`, y una ficha de registro son quince líneas de Blade en la vista del consumidor. Un componente genérico que reciba los registros como array termina siendo un mini motor de plantillas (`campos`, `acciones`, `tono`, `media`), cada uno de los seis sistemas necesitará algo que no calza y acabará usando el slot igual — para un municipio con poco personal técnico, aprender otra API puede costar más que copiar una card. Además el caso que describe está mal atribuido: el vecino no ve ocho columnas, ve tres de sus propias solicitudes; quien tiene ocho columnas es el funcionario, y ése está en escritorio donde la tabla cabe. (2) El candidato se contradice: su campo `riesgo` pide «una sola marca en el HTML, con container queries», y eso no existe. Si la marca es `<ul><li>`, ningún CSS la convierte en tabla con encabezados de columna, ordenamiento por columna ni alineación vertical de cifras; si la marca es `<table>`, convertirla en lista exige justamente el `display:block` que él descarta. «Una sola marca» y «cambia de tabla a lista según el ancho» son mutuamente excluyentes en CSS puro.
>
> **Corrección exigida.** Cinco cambios. (1) NOMBRE: `record-list` + `record-item`, en inglés. Los 54 componentes del paquete están en inglés (`data-table`, `empty-state`, `filter-bar`, `sortable-table`, `otp-input`); la única excepción es el prefijo institucional `gob-*`. (2) ALCANCE: no es el «modo móvil» de `data-table`, es un componente de presentación propio. La elección la hace el servidor por contexto de página, no por ancho de pantalla: portal público de Atención al Vecino y ruta de terreno del inspector renderizan siempre lista; el back-office de escritorio renderiza siempre tabla. Eso elimina de raíz el doble DOM, el `aria-hidden`, el doble trabajo del servidor por fila y la necesidad de container queries — bórrese esa parte del riesgo, que es incoherente. (3) API: dos componentes con slot por fila (`<x-muni::record-item>` con slots `title`, `meta`, `actions`), NO un array de configuración; así el consumidor mantiene el control de la marca y el componente aporta solo la firma (banda izquierda de estado, `.muni-num` en folio y RUT, área táctil de 44 px, zona de acciones). (4) SEMÁNTICA: `role="list"` en el `<ul>` contenedor, no `role="listitem"` en cada hijo — el bug de WebKit/VoiceOver lo dispara `list-style:none` en el contenedor y se corrige ahí. De paso, `timeline` ya tiene ese defecto latente (`.muni-timeline { list-style:none }` sobre un `<ol>` sin `role="list"`) y conviene arreglarlo en el mismo lote. (5) ESFUERZO: «medio» solo es honesto porque son dos componentes con dos temas y tests; si se contara uno solo, sería subestimado. Sin container queries ni doble render, el medio se sostiene.

### `sesion-expira`

**componente nuevo** · prioridad 3 · esfuerzo alto

**Qué resuelve.** Avisar antes de que caduque la sesión y ofrecer prorrogarla explícitamente, en vez de perder un formulario largo con un 419.
**Qué pasa hoy sin él.** Hoy no hay nada. El funcionario del mesón empieza a llenar la solicitud, atiende a otro vecino, vuelve, envía y Laravel responde 419 con todo perdido. Y mientras tanto la pantalla queda con el RUT y el domicilio del vecino a la vista del público del mesón: es un problema de minimización y control de accesos de la Ley 21.719, no solo de comodidad.
**Lo más parecido que ya existe.** — (modal sirve de base visual y auth-shell para la pantalla de re-ingreso)
**Referencia que lo hace mejor.** AdminLTE 4 — refs/ColorlibHQ_AdminLTE/src/html/pages/examples/lockscreen.astro («Bloqueo de pantalla») — Es la única de las 122 composiciones de página que se hace cargo del escenario «el funcionario se levanta y deja la sesión abierta frente al público», que es literalmente el mesón de atención. El contador de expiración en sí no lo implementa ninguna de las nueve familias: esa lógica hay que escribirla, y por eso el esfuerzo es alto.
**Patrón.** dialog (modal); role="alertdialog" cuando quedan menos de dos minutos
**Teclas obligatorias.** Tab y Shift+Tab atrapados; Esc equivale a «Seguir conectado», nunca a cerrar sesión; foco inicial en «Seguir conectado»
**Alpine.** core + focus
**Riesgo.** (1) Es UX, no seguridad: el corte real lo hace el servidor y el componente jamás debe extender la sesión por su cuenta — la prórroga pega a una ruta de renovación con CSRF y throttle, y es un acto explícito del funcionario; un ping automático convierte la sesión en eterna, o sea lo contrario de lo que pide la ley. (2) Un setInterval en pestaña de fondo se desincroniza: hay que recalcular contra una marca de tiempo del servidor. (3) Varias pestañas del mismo sistema deben coordinarse. (4) El aviso no puede robar el foco mientras el funcionario escribe.
**Dónde se usa.** El formulario de ingreso de solicitud en la recepción de Atención al Vecino y la ficha social de la credencial de discapacidad: los dos largos y los dos con datos personales en pantalla.

> **Objeción del juez.** La justificación legal está prestada de la referencia equivocada y el componente propuesto no la cumple. El argumento fuerte del candidato es el RUT y el domicilio a la vista del público del mesón (Ley 21.719), pero eso lo resuelve un bloqueo de pantalla —que es lo que hace el `lockscreen.astro` de AdminLTE— no un aviso de expiración: un modal traslúcido sobre el formulario deja los datos igual de visibles, y el botón «Seguir conectado» alarga la sesión, o sea empuja en dirección contraria a la minimización. Peor: cuando el servidor corta, la pestaña sigue mostrando la ficha completa hasta que alguien recargue, así que el momento de mayor exposición queda sin cubrir. Segunda objeción, menor pero cara: esto no es un componente de sistema de diseño puro, necesita una ruta de renovación con CSRF y throttle en cada uno de los 9 sistemas host; para un municipio con poco personal técnico eso son 9 pegas de integración, no una instalación. Y su frecuencia real depende de `SESSION_LIFETIME` en cada host, que no verifiqué: si sigue en los 120 minutos por defecto de Laravel, el aviso se dispara mucho menos de lo que sugiere la historia del mesón.
>
> **Corrección exigida.** 1) Alcance: que el componente cubra el instante T=0, no solo la cuenta regresiva. Al expirar, la superposición pasa a ser OPACA (fondo `var(--muni-bg)`, sin `backdrop-filter` traslúcido como el `modal` actual) y tapa la pantalla con «Sesión terminada · Ingresa de nuevo». Ahí sí se cumple la referencia de AdminLTE y ahí sí hay argumento de Ley 21.719; sin eso, se borra la mención legal de la ficha y el candidato se defiende solo por pérdida de trabajo. 2) Nombre: `sesion-aviso` o `sesion-guardia`. El resto del paquete usa sustantivos (`modal`, `drawer`, `toast-host`, `command-palette`, `error-page`), no frases verbales. 3) API estrictamente pasiva y host-side: props `expiraEn` (ISO absoluto del servidor), `renovarUrl`, `salirUrl`, `avisarA` (segundos). El componente NUNCA inventa la URL, NUNCA hace ping automático y NUNCA renueva sin clic; la prórroga es un POST con `@csrf` a una ruta que pone el host. Documentar en el README que sin esa ruta el componente solo avisa y bloquea, no prorroga. 4) Patrón APG: partir en `role="alertdialog"` desde el principio, no cambiar el rol a los dos minutos — mutar `role` en caliente no se anuncia de forma fiable en NVDA ni en VoiceOver. Para el tramo temprano, `aria-live="polite"` en el contador y el diálogo recién cuando quede poco. 5) Contador: nunca acumular ticks; en cada `setInterval` y en `visibilitychange` recalcular `expiraEn - Date.now()` contra la marca del servidor, y anunciar por `aria-live` solo en hitos (5 min, 1 min), no cada segundo. 6) Multipestaña con `BroadcastChannel` y respaldo a evento `storage` de `localStorage` dentro de un `try/catch`; en modo privado o con almacenamiento bloqueado el componente debe seguir funcionando como pestaña única. 7) Foco: mientras el funcionario escribe, el aviso NO roba el foco — el `x-trap` se activa solo en el tramo final o al pulsar el aviso. `Esc` equivale a «Seguir conectado» únicamente antes de expirar; en la superposición opaca de T=0, `Esc` no debe hacer nada (no hay nada que cerrar). 8) Antes de construirlo, confirmar `SESSION_LIFETIME` en discapacidad y Atención al Vecino: si está en 120, ajustar la expectativa de frecuencia en la ficha en vez de venderlo como pan de cada día.

### `confirmar`

**componente nuevo** · prioridad 4 · esfuerzo medio

**Qué resuelve.** Detener una acción irreversible con una pregunta que nombra el registro afectado y, donde corresponde, exige el motivo antes de dejar seguir.
**Qué pasa hoy sin él.** Hoy se arma a mano con <x-muni::modal> más dos botones, o directamente con el confirm() del navegador. modal está bien construido para lo que es (x-trap.inert.noscroll, Esc, retorno de foco) pero es el componente equivocado para un borrado: declara role="dialog" en vez de alertdialog, trae una × y cierra al clic en el fondo, no asocia la pregunta con aria-describedby, y el foco inicial cae en el botón de cerrar (lo primero que oye el lector es «Cerrar, botón», no la pregunta). Y su estado open es local de Alpine (x-data="{ open: false }"), así que un flujo servidor-primero —Livewire decide que esa anulación necesita confirmación— no puede abrirlo sin pegamento en cada sistema.
**Lo más parecido que ya existe.** modal, del que se reutiliza toda la gestión de foco.
**Referencia que lo hace mejor.** shadcn/ui — refs/shadcn-ui_ui/apps/v4/registry/new-york-v4/ui/alert-dialog.tsx; para la composición, Flowbite — themesberg_flowbite-svelte/src/routes/blocks/application/crud-delete-confirm.md — shadcn es el único de los nueve que quita el clic-fuera y la ×, obliga a elegir entre acción y cancelar, y pone el foco inicial en Cancelar. Flowbite aporta los bloques de la composición —icono de advertencia, pregunta CON el nombre del registro afectado, botón destructivo separado— y su propia ficha ya anota que en un sistema municipal conviene añadir el motivo de la anulación como campo requerido, que es exactamente nuestro caso por trazabilidad.
**Patrón.** dialog (modal), con role="alertdialog"
**Teclas obligatorias.** Esc (cancela, nunca ejecuta); Tab; Shift+Tab; Enter (activa el botón enfocado, jamás el destructivo por defecto)
**Alpine.** core + focus
**Riesgo.** (1) modal genera sus id con uniqid() en cada render: bajo Livewire eso cambia entre diffs y rompe aria-labelledby; el nuevo componente necesita un id estable derivado de una clave. (2) Si el motivo es obligatorio, el botón destructivo deshabilitado no puede comunicarlo solo con el gris: hace falta texto. (3) Doble envío: el botón debe bloquearse al primer clic sin perder el foco. (4) Esc cerrando está bien mientras cancelar sea la salida segura; con un formulario a medio llenar dentro hay que repensarlo.
**Dónde se usa.** Anular una solicitud ya derivada en Atención al Vecino, con motivo obligatorio que va al log de auditoría; dar de baja una patente comercial; revocar una credencial de discapacidad ya emitida.

> **Objeción del juez.** En los nueve sistemas las acciones destructivas viven dentro de acciones de Filament, donde un componente Blade es inerte: no se puede inyectar en el modal que Filament renderiza por su cuenta. Y Filament ya resuelve exactamente lo que el candidato promete —confirmación, motivo obligatorio y decisión servidor-primero— con `->requiresConfirmation()->schema([Textarea::make('motivo')->required()])`, patrón ya desplegado en `seguridad-graneros/app/Filament/Resources/Incidentes/Tables/IncidentesTable.php:216`. La prueba de que esto importa: `grep -rl "x-muni::modal" ~/Dev` fuera del paquete devuelve CERO usos en ocho meses. El paquete ya tiene un modal que nadie adoptó, precisamente porque lo destructivo pasa por Filament; agregar un segundo modal más estricto a la misma familia tiene alta probabilidad de terminar renderizándose solo en la página de demo. Además el riesgo (1) está mal razonado: `uniqid()` NO rompe `aria-labelledby`, porque el `id` del `<h2>` y la referencia salen de la misma variable `$tituloId` en el mismo render, así que el emparejamiento siempre se sostiene.
>
> **Corrección exigida.** 1) No es un componente nuevo: es una variante endurecida de `modal` — props `role="alertdialog"`, `dismissable=false` (sin × y sin cierre al clic en el fondo), `describedby` y `initialFocus="cancelar"`. Eso arregla `modal` para todos y cuesta una fracción; un 55° componente de la misma familia no se justifica con cero adopción del 54°. 2) Sacar el motivo obligatorio del alcance del componente: convierte el diálogo en un mini-formulario con validación, texto de error y compatibilidad de binding entre Livewire 3 y 4. Si se mantiene, el esfuerzo es ALTO, no medio. 3) Si igual se quiere servidor-primero, el patrón correcto ya existe en el repo: host singleton + evento de navegador, como `toast-host.blade.php`, no `x-data` local — el paquete no puede depender de Livewire (`composer.json` solo pide `illuminate/support` y `illuminate/view`). 4) Id estable derivado de una clave: sí, pero por el motivo correcto — evitar parcheo de DOM en cada morph de Livewire y poder referenciar el diálogo desde fuera, no por `aria-labelledby`. 5) Nombre: los 54 componentes son sustantivos en inglés (`modal`, `drawer`, `alert`, `stepper`); `confirmar` es un verbo en español y rompe la convención. 6) Documentar en el README que dentro de paneles Filament manda `->requiresConfirmation()` con su `schema`, para que nadie use el componente Blade donde corresponde una acción de Filament.

### `densidad`

**componente nuevo** · prioridad 4 · esfuerzo alto

**Qué resuelve.** Un modo compacto global que reduce alto de fila y de control para que quepan más registros, sin bajar del área táctil mínima.
**Qué pasa hoy sin él.** Los espaciados están escritos como literales dentro del style en línea de cada componente: padding:9px 11px en nav-item, padding:10px 14px en tabs, padding:24px en el main de dashboard-shell. No hay ninguna palanca. El funcionario que revisa trescientas solicitudes al día ve una docena de filas por pantalla y hoy compensa bajando el zoom del navegador al 80%, lo que degrada el tamaño del texto y de las áreas táctiles a la vez.
**Lo más parecido que ya existe.** — Los tokens --muni-* cubren color, radio, sombra, duración y tipografía; NO cubren espaciado ni alto de control.
**Referencia que lo hace mejor.** AdminLTE 4 — refs/ColorlibHQ_AdminLTE/src/scss/_compact-mode.scss — Es el único del catálogo que lo trata como modo global del armazón y no como variante suelta de tabla, y documenta honestamente su límite: no toca tablas ni formularios y ninguna página de su demo lo usa. Ese es justamente el error a no repetir: un modo compacto que no llega a la tabla no sirve de nada en un back-office.
**Patrón.** ninguno: es contenido
**Teclas obligatorias.** Enter y Space en el conmutador; aria-pressed en el conmutador; el modo compacto no puede reducir ni eliminar el indicador de foco
**Alpine.** no (si se conmuta con un formulario y se persiste en cookie leída en servidor); core si se quiere sin recarga
**Riesgo.** Es el candidato más caro y el de peor relación esfuerzo/valor si se hace mal. Prerrequisito real: tokenizar los espaciados (--muni-space-*, --muni-control-h, --muni-row-py) y hacer que la veintena de componentes afectados los consuman; mientras los paddings sigan en línea, el modo compacto no puede alcanzarlos sin !important. Riesgo de accesibilidad directo: WCAG 2.2 AA exige 24×24 de área táctil y nuestro criterio pide 44×44 en terreno, así que debe reducir el relleno vertical MANTENIENDO el alto mínimo del objetivo, cosa que AdminLTE no hace. Se persiste por usuario en cookie leída en servidor, no en localStorage, o hay parpadeo en cada carga.
**Dónde se usa.** La bandeja de solicitudes de Atención al Vecino y el listado de patentes morosas, donde el funcionario pasa el día paginando.

> **Objeción del juez.** La objeción más fuerte es que el modo compacto ataca el síntoma equivocado. El cuello de botella de revisar 300 solicitudes no es cuántas filas se ven, es cuántas hay que mirar: ver más registros de una vez tiene rendimiento fuertemente decreciente, mientras que filtrar bien elimina el 90% del scroll. Segunda objeción, esta de riesgo legal: el candidato presenta la accesibilidad solo como un límite a respetar, cuando el modo compacto la INTRODUCE como riesgo nuevo. A 24px de fila, dos botones de acción contiguos en la columna final pierden la excepción de espaciado de SC 2.5.8 y quedan bajo el mínimo; y si el compacto se implementa con `height` fija en vez de `padding-block`, rompe SC 1.4.12 Text Spacing en cuanto el usuario fuerza line-height 1.5 — un criterio que el paquete hoy cumple por accidente, porque todo fluye. Tercera: no hay regresión visual en el repo (8 tests: render, contraste, foco) y muni-ui está desplegado en 4 paneles Filament, así que un refactor de espaciado en 39 componentes se estrena a ciegas y a la vez en licencias, discapacidad, seguridad y atención al vecino.
>
> **Corrección exigida.** 1) ALCANCE: dejar de ser "modo compacto global del armazón" y ser densidad de tabla. Un atributo `densidad` ("comoda" por defecto \| "compacta") en `data-table` y `sortable-table`, más lectura opcional de `[data-muni-densidad="compacta"]` puesto en el `<html>` por el host, que esas dos tablas heredan. Sacar del alcance nav-item, tabs, dashboard-shell, button e input: comprimir la navegación no aporta ni una fila y sí come área táctil. 2) PRERREQUISITO: eliminarlo. No se tokeniza el espaciado del paquete. Se definen `--muni-row-py`/`--muni-row-px` como variables LOCALES dentro del `@once` de las dos tablas y se redefinen bajo el selector de densidad. Son ~20 líneas de CSS, cero cambios en los otros 37 componentes. 3) PISO DE OBJETIVO, que es lo que AdminLTE no hace: la fila encoge, el objetivo no. Todo `a`/`button` dentro de una celda lleva `display:inline-flex; align-items:center; min-height:24px; min-width:24px`, y bajo `@media (pointer: coarse)` sube a 44×44 para la tablet de terreno. Si la celda de acciones no cabe, la fila compacta no se aplica a esa columna. 4) NUNCA `height` fija: solo `padding-block` + `min-height`, para no romper SC 1.4.12 Text Spacing. Y no tocar el `outline`/`box-shadow` del foco ni el ancho de 3px de la banda `.muni-row--danger`, que es la firma del sistema y su único portador de estado no cromático. 5) PERSISTENCIA: el paquete no gestiona cookies. El host pinta `data-muni-densidad` en el `<html>` desde una cookie leída en servidor (documentarlo en el README); así no hay parpadeo y el paquete sigue siendo Blade puro compatible con Livewire 3 y 4. 6) ESFUERZO: baja de "alto" a bajo. Dos archivos, un test de render por variante y una captura en claro y oscuro. El "alto" solo reaparece si se insiste en el modo global de armazón, que es justo la parte sin evidencia de demanda. 7) ANTES DE CODEAR: medir. Contar cuántas filas se ven hoy en la bandeja real de Atención al Vecino a 1366×768 y cuántas se verían compacta. Si la diferencia es de 4-5 filas, conviene subir primero las filas por página y afinar `filter-bar`, que cuesta menos y rinde más.

### `gob-escudo`

**ampliación** · prioridad 4 · esfuerzo bajo

**Qué resuelve.** Que el escudo municipal se lea en los dos temas sin el parche del recuadro blanco.
**Qué pasa hoy sin él.** Hoy hay un solo PNG y el parche está escrito y comentado dentro de topbar: un contenedor con background:#fff fijo «porque el escudo municipal necesita fondo claro para leerse igual en tema oscuro y claro». En auth-shell la misma placa blanca con texto #0b0f14 es un literal sin contraparte oscura, registrado así en docs/INVENTORY.md. El resultado es un rectángulo blanco brillante en la cabecera de una sala de cámaras a oscuras.
**Lo más parecido que ya existe.** gob-escudo (una sola imagen), topbar (el recuadro blanco) y auth-shell (la placa).
**Referencia que lo hace mejor.** Windmill — refs/estevanmaito_windmill-dashboard/public/pages/login.html («Intercambio de imagen por tema»); contrato: Materio — refs/themeselection_materio-mui-nextjs-admin-template-free/typescript-version/src/@core/hooks/useImageVariant.ts — Windmill es la única solución del conjunto que lo hace sin JavaScript y sin depender del ciclo de vida de ningún framework: dos archivos y utilidades de visibilidad por tema. El hook de Materio define bien el contrato (modo resuelto + dos rutas + memorización) pero en Blade eso se resuelve mejor en CSS.
**Patrón.** ninguno: es contenido
**Teclas obligatorias.** ninguna
**Alpine.** no
**Riesgo.** (1) <picture> con media="(prefers-color-scheme: dark)" sigue al sistema operativo y NO al atributo data-muni-theme, así que se rompe en cuanto un sistema fija el tema: hay que hacerlo con dos <img> y reglas por atributo, y por eso este candidato va después del conmutador de tema. (2) Las dos imágenes llevan el mismo alt y la oculta va con display:none, no visibility, o el lector anuncia el escudo dos veces. (3) Ambas se descargan. (4) Al imprimir un certificado hay que forzar la variante clara: el papel es blanco.
**Dónde se usa.** La cabecera de la central de cámaras en turno de noche; y el certificado de residencia que se imprime en Atención al Vecino, que hoy sale con el escudo dentro de una caja blanca sobre fondo blanco.

> **Objeción del juez.** El uso municipal de impresión es relleno y delata que el candidato no verificó nada. Dice que el certificado de residencia de Atención al Vecino «sale con el escudo dentro de una caja blanca sobre fondo blanco»: una caja blanca sobre papel blanco es invisible, es justamente el único caso donde el parche no molesta, y encima no hay un solo @media print en todo el paquete, así que el riesgo 4 que él mismo declara no tiene dónde apoyarse. Segunda objeción, la de fondo: el esfuerzo «bajo» es falso porque el costo no es de código. No existe la segunda imagen: hay un único PNG de 180×180 con transparencia y detalle oscuro, y producir un escudo en negativo no es una tarde de Blade sino fabricar y hacer autorizar una variante de un símbolo institucional oficial ante Comunicaciones o Alcaldía. Súmale republicar con vendor:publish --force en los cuatro sistemas Filament, porque el README ya advierte que subir la versión por Composer no republica nada y el escudo queda congelado.
>
> **Corrección exigida.** Cambiar el alcance y el nombre: no es «gob-escudo extendido», es «placa del escudo tematizable», y el trabajo real está en topbar y auth-shell, no en gob-escudo. (1) Definir --muni-logo-plate y --muni-logo-plate-fg en muni-ui.css con par claro/oscuro: en claro #fff/#0b0f14, y en oscuro una placa apagada del tipo color-mix(in srgb, #fff 80%, var(--muni-bg)) o el petróleo institucional --muni-gob-*, verificando el contraste del texto GRA de respaldo en ambos temas. (2) Reemplazar los dos literales de topbar:23/27 y los dos de auth-shell:36-37 por esos tokens. Eso solo cierra la molestia de la sala de cámaras y la deuda de contrato, sin imagen nueva, sin Alpine y sin tocar gob-escudo. (3) Dejar la variante de imagen como prop opcional srcDark en gob-escudo, apagada por defecto y resuelta con dos <img> y reglas por [data-muni-theme] (nunca con <picture media="(prefers-color-scheme: dark)">, que sigue al sistema operativo y no al atributo), aria-hidden y display:none en la oculta, y solo cuando exista un escudo en negativo autorizado por el municipio. (4) Borrar el argumento de impresión del expediente o convertirlo en su propio candidato honesto: una hoja @media print para certificados, actas y órdenes de trabajo, que hoy el paquete no tiene en absoluto. (5) Reetiquetar el esfuerzo: bajo para los pasos 1 y 2, bloqueado por terceros para el 3.

### `kbd`

**componente nuevo** · prioridad 5 · esfuerzo bajo

**Qué resuelve.** Mostrar en pantalla la tecla o la combinación de un atajo.
**Qué pasa hoy sin él.** command-palette ya existe y se abre con Ctrl+K o ⌘K, pero no hay nada con qué escribirlo en la interfaz: hoy se pone texto suelto entre paréntesis, o no se pone y el atajo no existe para quien no lo sabe de antes. Es el candidato menos importante de la lista y podría vivir como una clase CSS en vez de un componente; va incluido porque documentar los atajos es parte de que el back-office sea operable sin ratón, y eso es obligación legal.
**Lo más parecido que ya existe.** — (lo necesitan command-palette, otp-input y segmented)
**Referencia que lo hace mejor.** daisyUI — refs/saadeghi_daisyui/packages/daisyui/src/components/kbd.css; shadcn/ui — refs/shadcn-ui_ui/apps/v4/registry/new-york-v4/ui/kbd.tsx — Los dos usan el elemento nativo <kbd> con texto real —no un icono, no una imagen—, que es lo único que importa para que se lea y se pueda buscar en la página. daisyUI además calcula el relieve con una variable, así que en un tema plano la tecla queda plana en vez de arrastrar una sombra pegada.
**Patrón.** ninguno: es contenido
**Teclas obligatorias.** ninguna: no es interactivo
**Alpine.** no
**Riesgo.** En macOS y Windows la combinación se escribe distinto: rotular «Ctrl» fijo muestra un atajo falso en un Mac, y command-palette ya compara $event.key de forma sensible a mayúsculas, así que el rótulo y el manejador tienen que salir de la misma fuente. El símbolo ⌘ suelto lo lee mal un lector de pantalla: hace falta texto además del símbolo. Al imprimir, un <kbd> con sombra queda como un recuadro gris sucio.
**Dónde se usa.** El pie de la paleta de comandos del panel de licencias y la ayuda de atajos del mesón de atención, donde el funcionario atiende sin soltar el teclado.

> **Objeción del juez.** Un rótulo desincronizado es peor que ningún rótulo, y un componente independiente no puede evitarlo. Si la vista escribe a mano `<x-muni::kbd>Ctrl K</x-muni::kbd>` y alguien pasa `hotkey="p"` a la paleta, el funcionario del mesón teclea un atajo que no existe y concluye que el sistema está malo. El propio candidato identifica que «el rótulo y el manejador tienen que salir de la misma fuente», pero eso es una responsabilidad de `command-palette` (que ya recibe `hotkey` como prop), no de un `<span>` sin lógica: crear el componente #55 da la ilusión de haber resuelto el problema sin tocar la causa.
>
> **Corrección exigida.** 1) Bajar el alcance: no crear un componente Blade nuevo. Definir `.muni-kbd` en el `@once <style>` de `command-palette.blade.php` —mismo patrón que `.muni-num` en `data-table.blade.php:40`— y reemplazar con ella el `style` en línea de la línea 45, que hoy duplica esos valores. 2) Mover la sincronía a donde vive el dato: que `command-palette` renderice el rótulo del atajo a partir de su propia prop `hotkey` en un `trigger` por defecto, para que rótulo y manejador salgan de la misma fuente. 3) Accesibilidad del símbolo: `<kbd>` no tiene rol ARIA implícito, así que nada de `aria-label` encima; el símbolo va con `aria-hidden="true"` y el texto («Command K», «Control K») como contenido visible o en un `sr-only`. 4) Sin relieve: sombra cero, borde 1px `var(--muni-border)`, como ya está — no copiar la variable de relieve de daisyUI; y si se quiere cubrir la impresión, la regla `@media print` corresponde a una hoja de impresión del paquete (hoy inexistente), no a este parche. 5) Coherencia de esfuerzo: `alpine: no` solo es cierto si se rotula una convención institucional fija. Detectar Mac vs Windows obliga a un `x-data` con `navigator.userAgentData?.platform ?? navigator.platform` y `x-text`, y entonces el esfuerzo declarado «bajo» sube a medio; decidirlo antes de estimar, no después. 6) Corregir `ya_existe_parcial`: `otp-input` y `segmented` no rotulan atajos; el único consumidor real es `command-palette`.

### `nav-grupo`

**componente nuevo** · **sin evaluar por un juez** · esfuerzo bajo

**Qué resuelve.** Agrupar ítems del menú en una sección plegable que declara su estado y se abre sola cuando la página actual está dentro.
**Qué pasa hoy sin él.** nav-section pinta un rótulo y nada más: no pliega. Con Licencias, Patentes, Discapacidad, Seguridad, Control de Acceso y Transparencia conviviendo en el mismo panel la lista no cabe en pantalla, y hoy se resuelve dejándola toda abierta con scroll, o partiendo el menú en varios layouts.
**Lo más parecido que ya existe.** accordion es lo más cercano pero es de contenido (recibe items como array), no envuelve nav-item, y arrastra sus propios defectos: cabecera y panel sin id/aria-controls, panel sin role=region, chevron sin aria-hidden.
**Referencia que lo hace mejor.** AdminLTE 4 — refs/ColorlibHQ_AdminLTE/src/ts/treeview.ts (estado) y Sneat Vuetify — themeselection_sneat-vuetify-vuejs-admin-template-free/typescript-version/src/@layouts/components/VerticalNavGroup.vue (animación) — AdminLTE es el único del lote que estampa aria-expanded y lo mantiene sincronizado, y su modo acordeón cierra los hermanos excluyendo correctamente el propio elemento y sus descendientes (el bug clásico). De Sneat se toma grid-template-rows: 0fr → 1fr, que anima sin medir alturas: la variante Next mide en JS y fuerza cuatro reflujos por apertura, inaceptable para el INP que exigimos.
**Patrón.** disclosure
**Teclas obligatorias.** Tab hasta el disparador; Enter; Space; el contenido plegado no recibe Tab
**Alpine.** core
**Riesgo.** El grupo tiene que abrirse EN SERVIDOR cuando contiene la ruta activa: si se deja a Alpine, en la primera pintura el ítem activo está oculto y con wire:navigate se ve el salto. grid-template-rows animado necesita overflow:hidden en el hijo o enfocar un enlace todavía oculto provoca scroll fantasma. La transición debe usar var(--muni-dur), no un literal, o repetimos el defecto de drawer y progress.
**Dónde se usa.** El panel de Seguridad Ciudadana, donde Cámaras, Rondas y Denuncias cuelgan de «Operaciones» y el operador entra directo a la cámara sin ver el resto.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

### `nav-menu`

**componente nuevo** · **sin evaluar por un juez** · esfuerzo medio

**Qué resuelve.** Pintar el menú lateral completo desde un árbol de configuración, filtrando por permiso y marcando el activo con una regla única y explícita.
**Qué pasa hoy sin él.** Hoy cada sistema escribe a mano su lista de <x-muni::nav-item> en el layout, con @can(...) alrededor de cada uno y :active="request()->routeIs('licencias.*')" copiado ítem por ítem. Seis sistemas por ~15 ítems: la regla de «activo» se escribe casi cien veces y ya diverge (unos usan routeIs, otros request()->is(), otros la URL completa), así que el ítem marcado y las migas no siempre coinciden. El contador de pendientes se resuelve con una consulta suelta en el layout, una por ítem, en cada página.
**Lo más parecido que ya existe.** nav-item y nav-section cubren el ítem y el rótulo, y nav-item lo hace bien (aria-current=page, :focus-visible con outline-offset:-2px, activo por color+peso+ARIA). No hay nada por encima: ni fuente de datos, ni filtro por permiso, ni <nav> con nombre accesible. Y nav-section pinta el rótulo como un <div> suelto, sin role=group ni aria-labelledby: la sección no existe para el lector.
**Referencia que lo hace mejor.** CoreUI — refs/coreui_coreui-free-bootstrap-admin-template/src/pug/_partials/sidebar-nav.pug (modelo de datos con cuatro tipos de nodo) y Sneat — themeselection_sneat-bootstrap-html-laravel-admin-template-free/resources/views/layouts/sections/menu/verticalMenu.blade.php (resolución del activo en servidor) — Son las dos únicas del catálogo que resuelven el árbol entero EN EL SERVIDOR y llegan al navegador con el HTML ya decidido: sin JS que marque el activo, sin parpadeo y compatible con wire:navigate. Sneat además es Blade sobre Laravel, con el árbol compartido desde un service provider en boot(). Su capa accesible es inservible (cero aria-current, disparadores con javascript:void(0)), pero eso ya lo aporta nuestro nav-item.
**Patrón.** ninguno: es navegación (landmark nav + lista); los grupos plegables son disclosure. NO es el patrón menu
**Teclas obligatorias.** Tab entre enlaces; Enter; Enter/Space en el disparador del grupo; ninguna flecha (no es role=menu)
**Alpine.** no (el activo se resuelve en PHP); core solo si se incluyen grupos plegables
**Riesgo.** Poner el permiso en la vista tienta a usarlo como control de acceso: el filtro del menú es COSMÉTICO, la autorización sigue en la policy y el middleware de la ruta, y hay que decirlo en la doc o alguien va a «proteger» un endpoint escondiéndolo del menú. El contador debe recibirse ya calculado o cacheado en Redis, nunca resolverse dentro del componente, o se paga una consulta por ítem en cada página. El número del contador necesita texto accesible: hoy el lector anuncia «Solicitudes 12» sin decir qué son doce.
**Dónde se usa.** El menú del panel de Licencias, donde «Exámenes médicos» solo lo ve el rol Médico y «Anular licencia» solo el Jefe de Tránsito, y el ítem «Solicitudes» lleva el contador de las que esperan en el mesón.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

### `tabs-ruta`

**componente nuevo** · **sin evaluar por un juez** · esfuerzo bajo

**Qué resuelve.** Solapas que llevan a rutas distintas, sin cargar todos los paneles ni perder el estado al navegar.
**Qué pasa hoy sin él.** tabs renderiza TODOS los paneles en el servidor y los alterna con x-show. Para la ficha de un expediente con cinco secciones eso significa consultar y pintar las cinco, con sus adjuntos, para mostrar una; y al volver de un wire:navigate la pestaña seleccionada se pierde y no se puede compartir el enlace de «la pestaña de Documentos». Hoy los sistemas usan tabs mal (todo cargado) o inventan una fila de nav-item con estilo distinto en cada sistema.
**Lo más parecido que ya existe.** tabs, que es el patrón equivocado para esto: si al seleccionar cambia la URL, ARIA prohíbe role=tablist. Y nav-item, que es un ítem de menú lateral, no una solapa.
**Referencia que lo hace mejor.** daisyUI — saadeghi_daisyui/packages/daisyui/src/components/tabs.css — Su implementación es puramente CSS sobre elementos cualesquiera: sirve igual sobre <a> que sobre <button> y no arrastra ninguna máquina de estado que nos obligue a fingir un tablist. Se copia la forma; la semántica (nav con nombre accesible + aria-current=page) la ponemos nosotros, que es justo donde daisyUI falla.
**Patrón.** ninguno: es navegación (nav con nombre accesible + aria-current="page"). NO es tabs
**Teclas obligatorias.** Tab entre solapas; Enter; sin flechas (son enlaces; el roving tabindex confundiría)
**Alpine.** no
**Riesgo.** Se ve idéntico a tabs y por eso se van a confundir: la doc necesita la regla en una línea («¿cambia la URL? entonces tabs-ruta»). El indicador de solapa activa no puede depender solo del color: en tabs hoy lo salva la barra inferior de .muni-tab--on::after y eso hay que conservarlo. Con wire:navigate, aria-current se recalcula en servidor.
**Dónde se usa.** La ficha del vecino en Atención al Vecino (Solicitudes · Documentos · Historial de contactos), cuando hay que mandarle a Jurídica el enlace directo a la pestaña de Documentos.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.


## 3. Composiciones de pantalla que faltan

| Pieza | Prio | Qué resuelve | Trámite donde se usa | Esfuerzo |
|---|---|---|---|---|
| `ficha-persona` | 2 | La pantalla del sujeto: identidad arriba (foto, nombre, RUN en .muni-num, edad, domicilio, est… | Ficha del vecino en Atención al Vecino, ficha del titular en Licencias de Conducir, ficha de l… | medio |
| `vitrina` | 2 | Una vitrina navegable, servida por el propio paquete, que muestra cada componente con sus vari… | No es un trámite: es la referencia que consultan los seis sistemas antes de inventar un compon… | medio |
| `bandeja-trabajo` | 3 | La cola del funcionario: pestañas por estado, filtros facetados con contador, búsqueda, selecc… | Bandeja de solicitudes de Atención al Vecino (derivar a la unidad responsable), cola de solici… | alto |
| `agenda-horas` | — | Mes con la carga por día y, al elegir un día, las franjas horarias con cupo, para reservar, re… | Agenda de exámenes de licencia de conducir (una persona por franja), horas con el profesional … | alto (lo caro es la navegación po… |
| `ajustes-cuenta` | — | El perfil del funcionario: datos, contraseña, segundo factor, sesiones activas, preferencias d… | Perfil del funcionario en los seis sistemas; es donde vive el enrolamiento del segundo factor … | bajo-medio (reparación, no obra n… |
| `asistente` | — | Partir un trámite largo en pasos navegables, con validación por paso, pasos omitibles y anunci… | La solicitud de credencial de discapacidad: identificación → antecedentes médicos → documentos… | alto |
| `formulario-tramite` | — | El trámite que el funcionario ingresa con los papeles en la mano: secciones con <legend>, reji… | Ingreso de una solicitud de patente comercial, ficha de caracterización en Atención al Vecino,… | medio |
| `pantalla-bloqueo` | — | Bloquear la sesión por inactividad sin cerrarla: se ve quién está conectado y se pide su contr… | Mesón de Atención al Vecino, ventanilla de Licencias y sala de la central de cámaras de seguri… | bajo |
| `pantalla-resultado` | — | El cierre de un flujo: «Su solicitud fue recibida. Folio 2026-04871», con un solo camino de sa… | Fin del ingreso de cualquier solicitud (licencias, patentes, discapacidad, atención al vecino)… | bajo |
| `plantilla-pantalla` | — | El punto de partida idéntico para toda pantalla nueva: shell + enlace de salto al contenido + … | Toda pantalla nueva de los seis sistemas; es la pieza que garantiza el Decreto N°1/2015 desde … | bajo (un archivo y una sección de… |

### `ficha-persona`

**componente nuevo** · prioridad 2 · esfuerzo medio

**Qué resuelve.** La pantalla del sujeto: identidad arriba (foto, nombre, RUN en .muni-num, edad, domicilio, estado) y debajo, en pestañas, todo lo que esa persona tiene en el municipio: trámites, documentos, citas, historial; acciones a la derecha.
**Qué pasa hoy sin él.** El paquete tiene todas las piezas (avatar, badge, tabs, timeline, data-table) y ninguna demo que las componga alrededor de una PERSONA; demo/solicitud.html es la ficha de una solicitud. Falta además el bloque más repetido del corpus y de las propias demos: la lista de pares dato/valor (.kv en solicitud.html, kv en app.html, «lista de pares dato-valor» en Creative Tim y Flowbite), reimplementada a mano en cada demo y sin existir como componente ni patrón documentado.
**Lo más parecido que ya existe.** demo/solicitud.html y el drawer de detalle de demo/app.html.
**Referencia que lo hace mejor.** refs/ColorlibHQ_AdminLTE/src/html/pages/pages/profile.astro — Es la única que combina las tres capas que el mesón necesita —tarjeta de identidad, pestañas de actividad/historial y formulario de edición embebido en una pestaña— sin meter el formulario en un modal. La de Creative Tim aporta el bloque dato-valor, pero su cabecera panorámica gasta media pantalla y muestra personas distintas en cabecera y biografía: maqueta sin modelo.
**Patrón.** Tabs (ya en tabs/tab-panel, con dos reparaciones pendientes: el panel no lleva id, ni aria-labelledby, ni tabindex="0")
**Teclas obligatorias.** Tab recorre las acciones de identidad (Editar, Emitir certificado); UN solo Tab entra al tablist; ←/→ cambian de pestaña; El Tab siguiente cae DENTRO del panel activo (hoy no: sin tabindex="0" el foco salta el panel de solo texto); Dentro del panel: tabla con orden por teclado y paginación; Si la edición abre en drawer: foco atrapado, Esc devuelve al botón que lo abrió
**Alpine.** core
**Riesgo.** medio-alto: muestra PII completa (RUN, domicilio, teléfono y, en discapacidad, diagnóstico). Debe ser la primera consumidora de la bitácora (cada apertura se registra) y aplicar minimización: campos sensibles plegados o enmascarados, revelados con una acción trazada.
**Dónde se usa.** Ficha del vecino en Atención al Vecino, ficha del titular en Licencias de Conducir, ficha de la persona inscrita en el Registro de Discapacidad, ficha del contribuyente en Patentes.

> **Objeción del juez.** La mitigación que el propio candidato declara es imposible de construir aquí: `composer.json` solo requiere `illuminate/support` e `illuminate/view` — sin Livewire, sin Eloquent, sin auth, sin activitylog —, así que este paquete no puede «ser la primera consumidora de la bitácora» ni registrar aperturas; la trazabilidad de accesos de la Ley 21.719 vive en el host, no en un componente Blade. Y sin ese registro, lo que el paquete estaría distribuyendo a nueve sistemas es una pantalla que pone RUN, domicilio, teléfono y diagnóstico juntos por defecto: normaliza la sobreexposición y le da apariencia de estándar institucional. Objeción secundaria, no menor para este destino: no hay una sola regla `@media print` en todo `resources/`, y una ficha con pestañas imprime solo el panel activo — en un mesón que emite certificados y actas eso es un defecto funcional, no cosmético.
>
> **Corrección exigida.** Partirlo en tres ítems que no se esperen entre sí. (1) **Reparación APG de `tabs`/`tab-panel`** — ítem propio, prioridad 1, no puede viajar dentro de una composición: `id` en el panel + `aria-controls`/`aria-labelledby` en ambas direcciones, `tabindex="0"` en el panel, Home/End, `$refs` + `.focus()` en las flechas, `aria-label` en el tablist y emitir `aria-selected`/`tabindex` estáticos desde Blade para el instante previo a Alpine. Esfuerzo: bajo, y desbloquea a todo consumidor de pestañas. (2) **`<x-muni::description-list>`** — ese sí es el componente que falta, con semántica `<dl>/<dt>/<dd>` (no el `div`+`span`+`b` de las demos, que no asocia etiqueta con valor para el lector de pantalla), valores mono opcionales vía `.muni-num`, y colapso a una columna bajo container query. Esfuerzo: bajo, no «medio». (3) **La ficha como demo y patrón documentado, no como componente**: `demo/persona.html` + una sección en el README que componga `page-header` + `avatar` + `badge` + `description-list` + `tabs` + `timeline` + `data-table`. No crear `<x-muni::ficha-persona>`: los cuatro sistemas tienen modelos de datos distintos y un componente con props para foto/RUN/domicilio/estado se forkea en el primer sistema que no calce. Sacar la bitácora del alcance del paquete: lo máximo que corresponde aquí es un `<x-muni::pii>` que sirva el campo enmascarado y, al revelarlo, dispare un evento DOM (`muni-pii-revelado`) que el host engancha a su propio registro — el paquete ofrece el gesto, el host prueba el cumplimiento. Añadir `@media print` a la ficha: al imprimir, todos los paneles visibles y sin controles.

### `vitrina`

**componente nuevo** · prioridad 2 · esfuerzo medio
*Absorbe los candidatos propuestos por separado: `vitrina-componentes`.*

**Qué resuelve.** Una vitrina navegable, servida por el propio paquete, que muestra cada componente con sus variantes y estados en los dos temas.
**Qué pasa hoy sin él.** La única vitrina es demo/showcase.html: un HTML estático de 276 líneas escrito a mano que no usa ni un solo componente Blade. Y no está solo desactualizado: REDEFINE los tokens con valores propios distintos de muni-ui.css (--muni-bg:#f4f5f7 contra #f5f6f8; en oscuro --muni-accent:#f5a623 contra #f59e0b; --muni-hint:#8a90a0 contra #6b7280), así que quien copie de ahí se lleva una paleta cuyo contraste nunca se verificó. Con 53 componentes y seis sistemas consumidores, hoy la única forma de saber qué existe es leer docs/INVENTORY.md o listar el directorio de vistas.
**Lo más parecido que ya existe.** demo/*.html (estático y divergente) y docs/INVENTORY.md (texto: no se puede mirar ni fotografiar).
**Referencia que lo hace mejor.** Sneat — themeselection_sneat-bootstrap-html-laravel-admin-template-free/resources/views/content/user-interface/ui-alerts.blade.php (una ruta y un controlador por familia); TailAdmin — TailAdmin_free-react-tailwind-admin-dashboard/src/components/common/ComponentCard.tsx (tarjeta contenedora) — Sneat porque es Blade sobre Laravel: una ruta por familia, publicable desde el service provider detrás de una bandera de configuración, sin inventar infraestructura. TailAdmin porque su tarjeta contenedora es el envoltorio reutilizable que hace comparables todas las demostraciones sin escribir marcado a mano en cada una.
**Patrón.** ninguno: es contenido
**Teclas obligatorias.** Tab por todo lo interactivo de cada ficha; Enter y Space en el conmutador de tema; cada componente demostrado debe ser recorrible con teclado en la propia vitrina
**Alpine.** core (solo el conmutador de tema y de densidad de la propia vitrina)
**Riesgo.** Si la ruta se registra siempre, los seis sistemas exponen la vitrina en producción: tiene que ir detrás de config('muni-ui.vitrina'), desactivada por defecto, y NUNCA mostrar datos reales (RUT ficticios; un ejemplo con datos de una persona real sería tratamiento de datos personales sin base de licitud). Además pasa a ser la superficie de las capturas de verificación (escritorio y móvil × claro y oscuro × cuatro estados), así que su URL tiene que quedar fija o Playwright persigue rutas que cambian.
**Dónde se usa.** No es un trámite: es la referencia que consultan los seis sistemas antes de inventar un componente, y el tablero contra el que se corren la regresión visual y las mediciones de contraste en ambos temas.

> **Objeción del juez.** Es infraestructura de documentación compitiendo contra defectos reales: el propio `docs/INVENTORY.md` cuenta 13 componentes con color no seguro en oscuro, 36 sin `:focus-visible` propio y 41 sin prueba. La vitrina no arregla ni uno; solo los hace mirables. Un municipio con poco personal técnico obtiene más de cerrar esos 13 en oscuro que de una galería nueva. Y el modo de falla que se está repitiendo es justamente el de `showcase.html`: una vitrina escrita a mano que nadie actualiza y que termina divergiendo. La única defensa válida es que una vitrina Blade que consume `<x-muni::…>` no puede divergir por construcción — pero eso solo se cumple si las fichas se generan recorriendo el directorio de componentes, no si se escriben una por una; si se escriben a mano, la objeción se sostiene entera y el candidato no vale la deuda.
>
> **Corrección exigida.** 1) Nada de ruta en el service provider ni de `config('muni-ui.vitrina')`: `orchestra/testbench v11.2.0` y `orchestra/workbench` YA están en `vendor/` como dev-deps. La vitrina va en `workbench/` (+ `testbench.yaml`), se sirve con `vendor/bin/testbench serve` y jamás se instala en los seis sistemas. Eso elimina de raíz el riesgo de exposición en producción que el propio candidato declara — no lo mitiga: lo borra. 2) El alcance debe incluir el destino de `demo/*.html`: son 13 archivos divergentes, no uno, ya auditados en `docs/INVENTORY.md:2108-2406`. La tarea cierra borrando `showcase.html` o dejándolos con un aviso de «no copiar tokens de aquí»; si sobreviven intactos, la paleta de 2,7:1 sigue en el repo y no se resolvió nada. 3) Corregir la justificación: el contraste de tokens ya se mide en PHP con los hex exactos (`tests/ContrasteTokensTest.php`), sin navegador y mejor. Lo que la vitrina aporta es el contraste COMPUESTO (texto sobre la superficie real del componente) y el anillo de foco renderizado — no repetir lo que ya está cubierto. 4) El esfuerzo «medio» solo es honesto para la vitrina navegable: no hay `package.json` ni Playwright en el repo, así que el arnés de regresión visual es una tarea aparte y no cabe en esta estimación. 5) Ni la vitrina ni la tarjeta contenedora tipo `ComponentCard` entran en `resources/views/components/` ni en el namespace `<x-muni::>`: son andamiaje de desarrollo, y si entran pasan a contarse como componentes 54 y 55 del inventario. 6) Los RUT de ejemplo, con dígito verificador válido pero inventados, escritos en el propio archivo de la vitrina: nunca sembrados desde el maestro de personas.

### `bandeja-trabajo`

**componente nuevo** · prioridad 3 · esfuerzo alto

**Qué resuelve.** La cola del funcionario: pestañas por estado, filtros facetados con contador, búsqueda, selección múltiple con acciones en lote (derivar, asignar, cerrar), columnas visibles configurables y el detalle de la fila al lado sin perder el listado ni la posición.
**Qué pasa hoy sin él.** demo/app.html llega hasta tabla ordenable + drawer y ahí se detiene: no tiene una sola casilla de selección, ni acciones en lote, ni facetas, ni contador de seleccionadas. Las piezas de filtro que existen están cojas: filter-bar es un <form> de recarga completa sin @csrf (419 con method="post") y segmented autoenvía con un onchange inline que una CSP estricta bloquea, recargando la página en cada flecha.
**Lo más parecido que ya existe.** demo/app.html (tabla ordenable + drawer de detalle + paleta de comandos).
**Referencia que lo hace mejor.** refs/shadcn-ui_ui/apps/v4/app/(app)/examples/tasks/components/data-table-toolbar.tsx — El catálogo la marca como «la composición más reutilizable de todo el repositorio»; es la única que reúne las cinco cosas juntas: facetas con contador por opción, botón de limpiar, selector de columnas, cabeceras ordenables y pie con «N de M seleccionadas». La bandeja de AdminLTE aporta la mitad y arrastra un cliente de correo que no necesitamos.
**Patrón.** ninguno como pantalla; Menu/Listbox para facetas y columnas, aria-sort para el orden
**Teclas obligatorias.** Tab llega al buscador; Enter filtra y aria-live anuncia «31 resultados»; Facetas: Enter abre, ↑/↓, Espacio marca, Esc cierra devolviendo el foco; Tab a «Limpiar filtros»; Casilla de cabecera: Espacio selecciona todo lo visible y se anuncia cuántas; Por fila: Espacio selecciona, Enter abre el detalle; Al aparecer la barra de acciones en lote, se anuncia y es alcanzable sin perder la fila; Esc deselecciona todo; El detalle devuelve el foco a la fila que lo abrió
**Alpine.** core; focus (x-trap) SOLO si el detalle es overlay. Para un mesón denso conviene la columna fija (split): no necesita trampa de foco y no tapa el listado.
**Riesgo.** medio: las acciones en lote son destructivas por definición. Exigen confirmación con el número exacto de registros afectados, motivo obligatorio en texto, ejecución en cola (ShouldQueue) y nunca «aplicar a todo lo que coincide con el filtro» sin decir cuántos son. Cada acción alimenta la bitácora.
**Dónde se usa.** Bandeja de solicitudes de Atención al Vecino (derivar a la unidad responsable), cola de solicitudes de licencia por estado en Tránsito, patentes vencidas para notificar cobranza.

> **Objeción del juez.** Filament Tables v5.7.6 ya trae exactamente estas cinco cosas de fábrica y lo comprobé en el vendor del propio repo: `HasBulkActions.php` y `TestsBulkActions.php` para las acciones en lote, `CanBeToggled::toggleable()` para las columnas visibles, y selección múltiple con casilla de cabecera indeterminada en `vendor/filament/tables/resources/views/index.blade.php:808-941` (`selectAllRecords`, `deselectAllRecords`, `$el.indeterminate`). Las tres colas que el candidato nombra —licencias por estado en Tránsito, solicitudes de Atención al Vecino, patentes vencidas a cobranza— viven en paneles Filament de los sistemas host. Construir una bandeja Blade paralela crea una segunda implementación de la misma pantalla, con menos accesibilidad, sin autorización por Action y sin los tests que Filament ya trae, y habrá que mantenerla contra la nativa; encima personas-graneros sigue en Filament 3, así que competiría con DOS versiones distintas del widget nativo. Hay una segunda objeción estructural: una bandeja con acciones en lote es intrínsecamente stateful de servidor, y este paquete requiere solo `illuminate/support` e `illuminate/view` —sin Livewire—, así que en Blade+Alpine puro la selección se pierde en cada cambio de página y el componente jamás puede garantizar ShouldQueue, autorización ni bitácora.
>
> **Corrección exigida.** 1) ARREGLAR PRIMERO los dos bugs, que valen más que el componente y son de una línea cada uno: en `filter-bar.blade.php` emitir `@csrf` cuando `strtolower($method) !== 'get'`; en `segmented.blade.php:15` sacar el `onchange` inline —una CSP con nonce lo bloquea porque `unsafe-inline` no cubre atributos de evento sin `unsafe-hashes`— y además hoy autoenvía en CADA flecha, así que llegar a la última opción recarga la página una vez por opción y destruye la navegación por teclado del grupo de radios; reemplazar por `x-on:change` con `requestSubmit()` y un botón «Aplicar» de respaldo. 2) MATAR EL NOMBRE Y EL ALCANCE. «bandeja-trabajo» es una pantalla con cinco responsabilidades: eso no lo mantiene un municipio con poco personal técnico. Partirlo en piezas pequeñas: `selectable` como atributo opcional de `data-table` (casilla de cabecera con estado indeterminado, casilla por fila, Espacio marca / Enter abre) y un `<x-muni::bulk-bar>` con `role="region"` + `aria-live="polite"` que solo recibe las acciones por slot. Las facetas y el selector de columnas NO son componentes nuevos: son un `dropdown-item` con casilla y contador sobre el `dropdown` que ya existe. El detalle al lado no es componente: `drawer` ya existe, y la columna fija es layout del host. 3) ALCANCE OBLIGATORIO A LA PÁGINA VISIBLE. El lote actúa solo sobre ids marcados y visibles; el paquete nunca emite un «aplicar a los 340 que coinciden con el filtro». Eso resuelve de paso que la selección no sobreviva a la paginación en Blade puro, y elimina el peor pie de la referencia de shadcn. 4) DOCUMENTAR EL LÍMITE en el README: el componente puede hornear la confirmación con el número exacto y el motivo obligatorio (usando el `modal` que ya existe), pero ShouldQueue, la autorización y la bitácora son del host y el paquete no puede garantizarlas. 5) ANTES DE ESCRIBIR CÓDIGO, exigir una pantalla real y nombrada que esté FUERA de un panel Filament. Si las tres colas citadas están todas dentro del panel, el candidato se cae solo y basta con `->toggleable()` y un `BulkAction`. 6) ESFUERZO: «alto» es honesto pero mal repartido. Monolítico es una semana y queda sin mantenedor; partido en las dos piezas de arriba es media jornada cada una con tests, que además hacen falta porque `data-table` y `segmented` hoy no tienen un solo `Blade::render()` en la suite (docs/INVENTORY.md:2089).

### `agenda-horas`

**componente nuevo** · **sin evaluar por un juez** · esfuerzo alto (lo caro es la navegación por grilla, no el dibujo)

**Qué resuelve.** Mes con la carga por día y, al elegir un día, las franjas horarias con cupo, para reservar, reagendar o bloquear una franja.
**Qué pasa hoy sin él.** El paquete tiene calendar (elige una fecha del mes) pero ninguna composición de agenda, y la única demo que agenda —el paso 3 del wizard— se construyó su propia grilla sin usarlo. No existe la vista de «el día por dentro», que es lo que el mesón consulta.
**Lo más parecido que ya existe.** El componente calendar y la grilla de franjas del paso 3 de demo/wizard.html.
**Referencia que lo hace mejor.** refs/ColorlibHQ_AdminLTE/src/html/pages/pages/calendar.astro — Es la única página de agenda del corpus y sirve sobre todo como advertencia: mueve eventos con arrastre y sin alternativa por teclado, inadmisible bajo el Decreto N°1/2015. Se copia la disposición (lista lateral + mes) y se descarta el mecanismo: reagendar es un select de franja o un botón «Mover a…», no un arrastre.
**Patrón.** Grid: el calendario de mes es una grilla navegable con flechas, Inicio/Fin, RePág/AvPág y aria-selected. El calendar actual NO lo implementa: solo botones de mes anterior/siguiente, y los días no forman grilla.
**Teclas obligatorias.** Tab llega al mes; ←/→ mueven un día; ↑/↓ una semana; Inicio/Fin al principio y fin de semana; RePág/AvPág cambian de mes; aria-live anuncia el día enfocado con su carga («12 de mayo, 3 de 8 cupos»); Enter selecciona el día y el foco pasa a la lista de franjas; Las franjas son radios recorribles con flechas; «Reservar» cierra el ciclo; Esc cancela el modal de confirmación
**Alpine.** core. Nada de FullCalendar: entra con su propio CSS, su propio modelo de foco y su propia paleta, y habría que pelearle los dos temas.
**Riesgo.** medio: doble reserva (la verdad la tiene el servidor, no el cliente) y zona horaria: el gotcha de APP_TIMEZONE ausente ya mordió en el ecosistema.
**Dónde se usa.** Agenda de exámenes de licencia de conducir (una persona por franja), horas con el profesional de la Oficina de Inclusión, programación de fiscalizaciones de patentes en terreno.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

### `ajustes-cuenta`

**reparación** · **sin evaluar por un juez** · esfuerzo bajo-medio (reparación, no obra nueva)

**Qué resuelve.** El perfil del funcionario: datos, contraseña, segundo factor, sesiones activas, preferencias de accesibilidad y zona de peligro, cada tarjeta con su propio guardar.
**Qué pasa hoy sin él.** La pantalla existe y es la demo con más regresiones de accesibilidad del paquete: los switches son <div class="sw" @click> sin <input type="checkbox"> (no se alcanzan con Tab ni se operan con Espacio) y las pestañas no tienen role="tablist", ni aria-selected, ni flechas. Los componentes reales switch y tabs sí hacen ambas cosas bien: hoy la demo enseña a hacerlo mal, y es la que un desarrollador copia.
**Lo más parecido que ya existe.** demo/settings.html (Perfil, Seguridad, Notificaciones, Preferencias) y demo/login-mfa.html (OTP de seis dígitos).
**Referencia que lo hace mejor.** themesberg_flowbite-svelte/src/routes/admin-dashboard/(sidebar)/settings/+page.svelte — Es la única del corpus donde cada tarjeta guarda por separado (no se pierde trabajo cuando una falla) y la única con el bloque de sesiones activas con dispositivo y «cerrar sesión», que es literalmente lo que pide la demo de TOTP y sesiones de Licencias. De AdminLTE se toma solo la «zona de peligro» final, que también falta.
**Patrón.** Tabs (usando el componente real, no una copia)
**Teclas obligatorias.** Un Tab entra al tablist; ←/→ cambian de pestaña; el siguiente Tab entra al panel; Cada switch se alcanza con Tab y alterna con Espacio, anunciando su estado; Cada sesión activa tiene un <button> «Cerrar sesión» real; La confirmación abre en modal con foco atrapado y cierra con Esc; La zona de peligro exige escribir una palabra antes de habilitar el botón destructivo
**Alpine.** core + focus (x-trap) para el modal de confirmación
**Riesgo.** medio: el bloque de sesiones muestra IP y ubicación aproximada del propio funcionario, y «cerrar sesión» debe invalidar el token en el servidor de verdad, no solo sacar la fila de la lista.
**Dónde se usa.** Perfil del funcionario en los seis sistemas; es donde vive el enrolamiento del segundo factor TOTP y el cierre remoto de sesiones del mesón.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

### `asistente`

**componente nuevo + reparación** · **sin evaluar por un juez** · esfuerzo alto
*Absorbe los candidatos propuestos por separado: `wizard-tramite`.*

**Qué resuelve.** Partir un trámite largo en pasos navegables, con validación por paso, pasos omitibles y anuncio del avance.
**Qué pasa hoy sin él.** stepper es SOLO un indicador: no hay <button>, ni <a>, ni tabindex, ni x-data, ni un manejador de eventos. No se puede cambiar de paso desde él. Hoy la solicitud de credencial de discapacidad y el ingreso de licencia son una sola pantalla larguísima, o cada sistema escribe su propia máquina de pasos en Livewire con su propio manejo —o su ausencia— de foco: al pasar de paso el foco se queda donde estaba y el lector no anuncia nada.
**Lo más parecido que ya existe.** stepper como indicador está bien: <ol>/<li> semántico, aria-current=step, y el estado no depende solo del color porque hay marca de verificación. progress aporta un role=progressbar correcto. Falta navegación, estado de paso en error y omitido, foco y anuncio.
**Referencia que lo hace mejor.** shadcn/ui — shadcn-ui_ui/packages/react/src/questionnaire/components.tsx; máquina de estados de Preline — htmlstreamofficial_preline/src/plugins/stepper/core.ts — El de shadcn renderiza un <form> nativo que se lee con FormData (funciona sin JS y es portable a Livewire tal cual) y es la pieza con mejor accesibilidad de todo el catálogo: role=progressbar con aria-valuetext, aria-live=polite para el avance, aria-invalid en el campo y role=alert en el error. Preline aporta lo único que le falta para un trámite municipal —paso EN ERROR y paso OMITIDO como estados de primera clase— y nada más: sus 929 líneas no emiten un solo atributo ARIA ni un manejador de teclado.
**Patrón.** ninguno: es un formulario de varios pasos; el indicador es una lista con aria-current="step"
**Teclas obligatorias.** Tab dentro del paso; Enter (envía el paso); Anterior / Siguiente / Omitir alcanzables y con nombre; al cambiar de paso el foco va al encabezado del paso nuevo (tabindex=-1); errores con role=alert y aria-describedby
**Alpine.** core (o ninguno si los pasos son rutas o componentes Livewire con envío real, que es preferible)
**Riesgo.** En Livewire 3 el wire:model de un paso oculto SE SIGUE ENVIANDO: si los pasos se ocultan con x-show los campos siguen en el DOM y en el orden de tabulación; hay que quitarlos, no esconderlos. Mover el foco al cambiar de paso pelea con la restauración de scroll de wire:navigate. Riesgo legal: el borrador de la credencial de discapacidad contiene diagnóstico, o sea dato sensible bajo la Ley 21.719; no puede quedar en localStorage ni sessionStorage, va al servidor cifrado y con base de licitud explícita.
**Dónde se usa.** La solicitud de credencial de discapacidad: identificación → antecedentes médicos → documentos → declaración → revisión. Hoy es una sola pantalla larga.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

### `formulario-tramite`

**componente nuevo** · **sin evaluar por un juez** · esfuerzo medio

**Qué resuelve.** El trámite que el funcionario ingresa con los papeles en la mano: secciones con <legend>, rejilla de dos columnas, adjuntos y una columna lateral fija con el resumen de requisitos pendientes; al enviar con errores, resumen de errores al inicio con foco.
**Qué pasa hoy sin él.** El único formulario largo del paquete es el wizard, por pasos y pensado para el vecino; obligar al funcionario a cuatro pasos por cada ingreso es más lento que la ventanilla de papel. Además el paquete no tiene resumen de errores: input y select muestran el error por campo pero sin aria-describedby (select tampoco marca aria-invalid), y el prop name de field es código muerto: no cablea for/id.
**Lo más parecido que ya existe.** demo/wizard.html (mismo dominio, otra forma) y el componente field.
**Referencia que lo hace mejor.** refs/creativetimofficial_material-tailwind/docs-content/html/form/checkout-form.tsx (resumen fijo a un lado) + themesberg_flowbite-svelte/src/routes/blocks/application/crud-create-forms.md (secciones + dos columnas) — El resumen lateral fijo es lo que convierte una lista de campos en un trámite: dice QUÉ FALTA para poder ingresar. Flowbite aporta la densidad correcta de dos columnas para escritorio ancho, que es donde vive el mesón.
**Patrón.** ninguno: es composición. Lo que hay que respetar es HTML nativo: fieldset/legend, for/id, aria-describedby para ayuda y error, aria-invalid
**Teclas obligatorias.** Tab recorre los campos en orden visual, sin trampas; Cada campo obligatorio dice «obligatorio» en TEXTO, no solo con asterisco; Al enviar con errores, el foco salta al resumen (role="alert", tabindex="-1"); Cada error del resumen es un enlace que lleva al campo; El resumen lateral de requisitos es texto, no un control: no roba tabulaciones
**Alpine.** core (contador de requisitos, secciones condicionales)
**Riesgo.** medio: subida de archivos (validar tipo y tamaño en el servidor; accept es una sugerencia, no una defensa) y borradores: un borrador con PII se cifra (EncryptedSeguro) y caduca.
**Dónde se usa.** Ingreso de una solicitud de patente comercial, ficha de caracterización en Atención al Vecino, ingreso presencial de una solicitud de licencia en el mesón de Tránsito.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

### `pantalla-bloqueo`

**componente nuevo** · **sin evaluar por un juez** · esfuerzo bajo

**Qué resuelve.** Bloquear la sesión por inactividad sin cerrarla: se ve quién está conectado y se pide su contraseña para volver, con salida a «entrar como otro usuario».
**Qué pasa hoy sin él.** No existe nada. demo/login-mfa.html cubre el ingreso, no el retorno. En un mesón municipal la sesión abierta frente al público es una fuga de datos personales del vecino que está siendo atendido, y hoy la única defensa es cerrar sesión y perder el trabajo en curso.
**Lo más parecido que ya existe.** auth-shell y demo/login-mfa.html.
**Referencia que lo hace mejor.** refs/ColorlibHQ_AdminLTE/src/html/pages/examples/lockscreen.astro (variante: themesberg_flowbite-svelte/src/routes/admin-dashboard/authentication/profile-lock.svelte) — Son las dos únicas del corpus que resuelven este caso y coinciden en lo esencial: avatar y nombre de quien está bloqueado, un solo campo de contraseña y una salida explícita para entrar como otra persona. El catálogo lo dice sin rodeos: «un mesón municipal deja la sesión abierta y necesita bloqueo por inactividad con reingreso de contraseña sin cerrar sesión».
**Patrón.** ninguno si es página propia; si se muestra como capa es diálogo modal: role="dialog", aria-modal="true", foco atrapado y SIN Esc
**Teclas obligatorias.** Al aparecer, el foco va al campo de contraseña; Tab solo alcanza el campo, «Entrar» y «Entrar como otro usuario»; Nada de atrás es alcanzable: inert de verdad, no solo tapado (un lector de pantalla leería el contenido oculto); Esc NO cierra; Enter envía; el error se anuncia en texto con role="alert" y el foco vuelve al campo
**Alpine.** core + focus (x-trap.inert) si es capa; ninguno si es página
**Riesgo.** medio: es una pantalla de autenticación, así que intentos y bloqueo por fuerza bruta viven en el servidor. Ojo con el gotcha ya documentado de «Recordarme» saltándose la contraseña: aquí sería fatal.
**Dónde se usa.** Mesón de Atención al Vecino, ventanilla de Licencias y sala de la central de cámaras de seguridad ciudadana, donde la pantalla está a la vista del público.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

### `pantalla-resultado`

**componente nuevo** · **sin evaluar por un juez** · esfuerzo bajo

**Qué resuelve.** El cierre de un flujo: «Su solicitud fue recibida. Folio 2026-04871», con un solo camino de salida, el folio copiable y el enlace a imprimir el comprobante.
**Qué pasa hoy sin él.** El paso 4 del wizard tiene un estado «Enviado» pegado al final del asistente, pero no existe como plantilla reutilizable. Ningún otro flujo del paquete cierra: después de guardar, el funcionario se queda mirando el mismo formulario sin saber si pasó algo.
**Lo más parecido que ya existe.** El paso «Enviado» de demo/wizard.html y empty-state, que es para «no hay nada», no para «terminaste».
**Referencia que lo hace mejor.** refs/coreui_coreui-free-vue-admin-template/src/views/authentication/PasswordChanged.vue (y su hermana CheckEmail.vue) — El catálogo lo dice con precisión: «un solo camino de salida es exactamente lo correcto para un funcionario de mesón y para un vecino mayor». Es la pantalla más barata del corpus y la que más reduce llamadas al soporte.
**Patrón.** ninguno: es composición
**Teclas obligatorias.** Al cargar, el foco va al <h1> (tabindex="-1") y el resultado se anuncia; Tab llega a «Copiar folio»: botón real que confirma en texto, no solo con un icono verde; Tab llega a «Imprimir comprobante»; El último elemento es el único camino de salida
**Alpine.** core, solo para el portapapeles, con alternativa si falla
**Riesgo.** bajo, con un cuidado: si el folio sirve para consultar el estado sin autenticación, no puede ser secuencial adivinable, y la página de verificación no debe filtrar PII a quien tenga el número.
**Dónde se usa.** Fin del ingreso de cualquier solicitud (licencias, patentes, discapacidad, atención al vecino) y confirmación de hora agendada.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.

### `plantilla-pantalla`

**componente nuevo** · **sin evaluar por un juez** · esfuerzo bajo (un archivo y una sección del README)

**Qué resuelve.** El punto de partida idéntico para toda pantalla nueva: shell + enlace de salto al contenido + landmarks nombrados + migas + un solo <h1> + región de mensajes aria-live + contenedor de contenido + pie.
**Qué pasa hoy sin él.** Ninguna de las 14 demos tiene skip-link (la auditoría lo repite archivo por archivo) y ni app-shell ni dashboard-shell lo emiten. Tampoco hay región de mensajes estándar: los avisos van por toast-host, pero un error de servidor tras un POST no tiene dónde anunciarse. En diez sistemas esto se multiplica por diez.
**Lo más parecido que ya existe.** dashboard-shell y app-shell como componentes; demo/app.html como pantalla armada, sin skip-link ni landmarks nombrados.
**Referencia que lo hace mejor.** refs/coreui_coreui-free-bootstrap-admin-template/src/pug/views/blank.pug y refs/estevanmaito_windmill-dashboard/public/pages/blank.html — Son las dos únicas del corpus que existen explícitamente para ser copiadas. Windmill además muestra su 404 dentro del layout completo: el funcionario nunca pierde la navegación.
**Patrón.** ninguno: es composición (el skip-link es HTML plano)
**Teclas obligatorias.** El primer Tab de la página revela «Saltar al contenido» VISIBLE; Enter mueve el foco a <main tabindex="-1">; Tab recorre migas, acciones de cabecera y contenido; Shift+Tab vuelve por el mismo camino; El anillo de foco nunca queda oculto tras el header sticky (topbar es position:sticky con z-index:100)
**Alpine.** ninguno
**Riesgo.** bajo
**Dónde se usa.** Toda pantalla nueva de los seis sistemas; es la pieza que garantiza el Decreto N°1/2015 desde el primer commit en vez de auditarlo después.

> **Sin juez.** El evaluador de este candidato no alcanzó a correr. La ficha es la propuesta del analista, sin contraste.


## 4. Orden propuesto

Ocho tandas. La agrupación no es por tamaño sino por lo que comparten: piezas que se resuelven con
el mismo trabajo de foco, o que forman parte de la misma pantalla, van juntas porque separarlas
obliga a rehacer dos veces lo mismo.

### Tanda 1 — lo que hoy deja a alguien fuera

Son los defectos que impiden operar sin mouse o que dejan a un funcionario sin indicador de foco. No son mejoras: son cosas que hoy no se pueden hacer con el teclado. Van juntas porque las cinco se resuelven con el mismo trabajo de foco y de roles, y porque conviene medirlas de una sola pasada con la reja de accesibilidad.

- ~~`enlace-salto`~~ — hecho en `b47d036`
- ~~`sortable-table`~~ — hecho en `c769aed`
- ~~`tabs`~~ — hecho en `f98fd3a`
- ~~`file-dropzone`~~ — hecho en `29bf1a8`
- ~~`segmented`~~ — hecho en `62a51a2`
- ~~`drawer`~~ — hecho en `d83e67d`

### Tanda 2 — el cimiento de los formularios

Todo esto comparte una sola pieza: atar el error al campo y anunciarlo cuando llega por Livewire. Hacerlo una vez y aplicarlo a los siete es mucho más barato que repetirlo. El campo de RUT y el de fecha van acá porque son los dos formatos que el municipio escribe todos los días y hoy se resuelven a mano en cada sistema.

- ~~`input`~~ — hecho en `55d73ca`
- ~~`resumen-errores`~~ — hecho en `12d9359`
- ~~`anuncios`~~ — hecho en `12d9359`
- ~~`area-texto`~~ — hecho en `87deb6b`
- ~~`casilla`~~ — hecho en `87deb6b`
- ~~`campo-rut`~~ — hecho en `370b625`
- ~~`campo-fecha`~~ — hecho en `370b625`

### Tanda 3 — la tabla, que es la pantalla más usada del ecosistema

La tabla es donde el funcionario pasa el día. Van juntas porque la cabecera, la paginación y la selección en lote son piezas de la misma pantalla, y separarlas obliga a rehacer el mismo encabezado tres veces.

- ~~`data-table`~~ — hecho en `5881996`
- ~~`cabecera-tabla`~~ — hecho en `18bf15e`
- ~~`pagination`~~ — hecho en `5881996`
- ~~`seleccion-lote`~~ — hecho en `7ccdc3a`
- ~~`empty-state`~~ — hecho en `18bf15e`
- `lista-registros`

### Tanda 4 — lo que el municipio imprime y lo que la ley exige registrar

El paquete no tiene una sola regla de impresión y el municipio emite certificados, actas y oficios todos los días. La bitácora va en la misma tanda porque la Ley 21.719 pide trazabilidad de accesos y porque comparte con la línea de tiempo la forma de presentar sucesos fechados.

- ~~`doc-imprimible`~~ — hecho a medias a propósito en `b12f1db`: va la hoja, no las plantillas
- ~~`bitacora-auditoria`~~ — la pantalla sale del paquete a `laravel-arcop-panel`; de las tres piezas que dejó, `sortable-table` se cerró en `c769aed` y `diff-campos` en `4d52ab2`
- ~~`timeline`~~ — hecho en `09cbf98`

### Tanda 5 — buscar dentro de listas largas

El selector con búsqueda es el componente más caro de la lista y el que más se pide: hoy elegir a un vecino entre miles se hace con un `select` nativo. Va con el panel flotante y la ayuda breve porque los tres comparten el mismo problema de posicionamiento y de foco.

- ~~`combobox`~~ — hecho en `f16359e` (solo la fase A: formularios fuera de Filament)
- ~~`popover`~~ — hecho en `fab8085`
- ~~`tooltip`~~ — hecho en `789d2a6`

### Tanda 6 — armazón, navegación y densidad

El menú alimentado por configuración con permiso por ítem es lo que evita que cada sistema reescriba su navegación. La plantilla de pantalla va al final de la tanda porque solo tiene sentido cuando las piezas que compone ya existen.

- `nav-menu`
- `nav-grupo`
- `sidebar`
- `dashboard-shell`
- `breadcrumb`
- `tabs-ruta`
- `densidad`
- `plantilla-pantalla`

### Tanda 7 — pantallas completas

Composiciones que se arman con lo anterior. Ninguna se puede hacer bien antes de tener sus piezas, así que van al final. La vitrina cierra el ciclo: es lo que permite a otro equipo ver qué existe antes de inventar un componente nuevo.

- `ficha-persona`
- `formulario-tramite`
- `bandeja-trabajo`
- `ajustes-cuenta`
- `asistente`
- `pantalla-resultado`
- `vitrina`

### Tanda 8 — lo que puede esperar

Mejoras reales pero que no bloquean a nadie hoy, más dos piezas caras (el calendario y la agenda de horas) que conviene mirar recién cuando el resto esté firme.

- `conmutador-tema`
- `campo-clave`
- `lista-descripcion`
- `cargando`
- `confirmar`
- `sesion-expira`
- `pantalla-bloqueo`
- `ring`
- `stat`
- `alert`
- `toast-host`
- `gob-escudo`
- `calendar`
- `agenda-horas`
- `kbd`


## 5. Lo que se descartó

Ninguno. Es un resultado que hay que mirar con desconfianza, no un elogio de los candidatos: como
dice la advertencia inicial, ningún juez rechazó nada. Los analistas sí descartaron opciones dentro
de sus propios informes de dominio, que están en la carpeta de trabajo de la fase.

Si algo de esta lista no debe construirse, la decisión es tuya y este documento no la tomó por ti.
