# Análisis de brechas

Qué se comparó: los **60 candidatos** que cinco analistas
propusieron tras cruzar los 53 componentes del paquete contra las 262 piezas y las 122
composiciones catalogadas en las nueve familias de referencias MIT.

Después de fundir los que describían la misma pieza desde dominios distintos quedaron **55
fichas**: 16 reparaciones de lo que ya existe, 27 componentes nuevos y 12 composiciones de
pantalla.

**Este documento se poda a medida que se cierra trabajo.** Las seis fichas de la primera tanda ya
salieron del cuerpo, las siete de la segunda y tres de la tercera también. Están resumidas en la
sección 0 con su commit. **Quedan 20 pendientes.** Si una ficha sigue acá, sigue sin hacerse.

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
| `stat` | `3e925f9` | 1) Correcciones #3 y #4 del juez (promover `.muni-num` y la clase oculta a resources/css/muni-ui.css): NO las implementé, y sostengo que el revisor tiene razón en que no es un problema de código sino de dictamen |
| `alert` | `dfe6f4d` | Acepto los cinco puntos del revisor; ninguno está objetivamente mal |
| `calendar` | `d7b7ca2` | Los tres defectos del revisor eran reales y los reproduje antes de tocar el componente: escribí primero las pruebas y fallaron exactamente en los tres (rejilla con `<template x-if>`, última regla `:hover` = `--off:hover` |
| `kbd` | `e9c459b` | Sobre lo refutado |
| `confirmar` | `9ad7807` | Sobre cada punto del revisor: (1) README, bloqueante: tiene razón en el hecho —el README no cambió—, pero el README está en la lista de archivos compartidos que no toco; la vía es `readmeFila`, y la del implementador ant |
| `cargando` | `e22e0fa` | Punto por punto del revisor |
| `conmutador-tema` | `15f156e` | 1) Bloqueante «(A) en muni-ui.css sin hacer»: el problema del revisor choca frontalmente con la regla del encargo «No edites resources/css/**», así que NO lo hice y lo entrego como parche exacto en pendiente |
| `sesion-expira` | `fe49e16` | QUÉ SE CORRIGIÓ Y POR QUÉ, punto por punto del revisor.

(1) TodosRenderican rojo por `expiraEn` sin defecto — NO le puse defecto: un `expiraEn` opcional dejaría una guardia inactiva en silencio (solo un console.warn) cu |
| `nav-menu` + `nav-grupo` | `3e628ea` | El menú sale de un árbol con la regla de activo escrita una vez. El paquete no consulta permisos: los recibe ya evaluados, y esconder un ítem es cosmético, nunca autorización. |
| `dashboard-shell` + `sidebar` | `eb446a0` | El menú móvil no abría —el burger tenía @click sin ningún x-data antecesor— y la lateral cerrada seguía recibiendo el foco. Reproducido en vivo: Tab aterrizaba en un enlace a x=-228px. |
| `field` (prop muerta) | `d79d390` | Medido: cero consumidores pasan `name`. Se documenta como aceptada y sin efecto; emitir un `for` colgando habría convertido un contrato roto e inofensivo en una falla real de 4.1.2. |
| `hoja` (era `doc-imprimible`) | `b12f1db` | El paquete aprende a imprimir: hoja carta con membrete, folio, firma y pie numerado, más las reglas base de @media print en las dos hojas de estilo. Sin plantillas de certificado, acta ni oficio: esas las fija la Ley 19.880 y el municipio. |

Todas llevan prueba que falla si el defecto vuelve, y la suite pasó de 48 a 100 pruebas.

## 1. Lo que hay que reparar antes de agregar nada

Son defectos del paquete tal como está hoy. Van primero por una razón simple: agregar componentes
nuevos sobre un cimiento roto multiplica el defecto por cada sistema que los adopte, y el
ecosistema ya tiene nueve.

| Pieza | Prio | Qué resuelve | Trámite donde se usa | Esfuerzo |
|---|---|---|---|---|
| `toast-host` | 2 | Es el canal por el que los seis sistemas confirman que un trámite se guardó o falló. | «Solicitud N.º 2026-4831 ingresada — derivada a Obras» en la recepción de Atención al Vecino; … | medio |
| `ring` | 3 | El avance circular: un porcentaje mostrado como arco. | El porcentaje de cupos ocupados de la agenda diaria de licencias; la proporción de solicitudes… | bajo |
| `tooltip` | 3 | La ayuda breve de un control, sobre todo de los botones de solo icono de las filas. | Los botones de icono de la fila del listado de patentes morosas (ver, anular, imprimir orden d… | bajo |
| `breadcrumb` | — | Que las migas sean una lista de verdad y que sus enlaces se distingan sin depender del color. | Cada pantalla del panel de Discapacidad: Inicio › Credenciales › Solicitud 2026-0412. | bajo |

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
| `combobox` | 2 | Buscar y elegir un registro dentro de una lista larga escribiendo, en vez de desplegar mil opc… | El buscador de titular en la recepción de solicitudes ARCOP —el campo que hoy tapa el parche d… | alto |
| `popover` | 2 | Un panel flotante anclado a un disparador, con contenido y controles dentro, que no bloquea la… | El panel de filtros por estado y fecha de la bandeja de solicitudes de Atención al Vecino; la … | medio |
| `checkbox-group` | 2 | Un grupo de casillas como un solo campo: fieldset con legend, y el error del grupo dicho una v… | Los requisitos que marca el funcionario al recibir una solicitud de patente y las categorías d… | bajo |
| `campo-clave` | 3 | Escribir una contraseña pudiendo verla, y saber si es suficientemente robusta, con teclado y c… | El cambio de clave del funcionario y el restablecimiento de clave del vecino en Atención al Ve… | bajo |
| `lista-descripcion` | 3 | El bloque de datos de una ficha como pares dato-valor con semantica real: <dl>/<dt>/<dd>. | La ficha del solicitante en la recepcion de licencias (RUT, nombre, domicilio, clase solicitad… | bajo |
| `lista-registros` | 3 | Presentar los mismos registros como lista de fichas cuando la tabla no cabe, sin romper la sem… | El vecino consultando el estado de su solicitud desde el celular en el portal de Atencion al V… | medio |
| `densidad` | 4 | Un modo compacto global que reduce alto de fila y de control para que quepan más registros, si… | La bandeja de solicitudes de Atención al Vecino y el listado de patentes morosas, donde el fun… | alto |
| `gob-escudo` | 4 | Que el escudo municipal se lea en los dos temas sin el parche del recuadro blanco. | La cabecera de la central de cámaras en turno de noche; y el certificado de residencia que se … | bajo |
| `tabs-ruta` | — | Solapas que llevan a rutas distintas, sin cargar todos los paneles ni perder el estado al nave… | La ficha del vecino en Atención al Vecino (Solicitudes · Documentos · Historial de contactos),… | bajo |

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
