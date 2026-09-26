# Análisis de brechas

Qué se comparó: los **60 candidatos** que cinco analistas
propusieron tras cruzar los 53 componentes del paquete contra las 262 piezas y las 122
composiciones catalogadas en las nueve familias de referencias MIT.

Después de fundir los que describían la misma pieza desde dominios distintos quedaron **55
fichas**: 16 reparaciones de lo que ya existe, 27 componentes nuevos y 12 composiciones de
pantalla.

**Este documento se poda a medida que se cierra trabajo.** Las seis fichas de la primera tanda ya
salieron del cuerpo, las siete de la segunda y tres de la tercera también. Están resumidas en la
sección 0 con su commit. **Quedan 3 pendientes.** Si una ficha sigue acá, sigue sin hacerse.

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
| `toast-host` | `f99a10e` | BLOQUEANTE (duration anula la persistencia y el piso): confirmado y arreglado |
| `densidad` | `c9e84e0` | QUÉ ARREGLÉ DEL REVISOR

1) BLOQUEANTE de la banda muerta (importante en su lista, pero es el defecto real): `.muni-st__danger td:first-child` no casaba con ninguna celda porque las filas salen de un `<template x-for>` q |
| `breadcrumb` | `95a1ee2` | BLOQUEANTE (slot `migas` en page-header) — aceptado y hecho |
| `tabs-ruta` | `95a1ee2` | Acepté cinco de los siete hallazgos, uno lo cerré de forma distinta a como lo pedía el revisor y otro lo cerré más allá de lo que pedía |
| `lista-descripcion` | `00e3930` | Los seis hallazgos del revisor son ciertos; ninguno estaba objetivamente mal, así que no descarté ninguno |
| `lista-registros` | `f147f4d` | CORREGIDO EL BLOQUEANTE (punto 4 de la corrección exigida, la parte de `timeline`) |
| `campo-clave` | `d4be1e2` | 1) MENOR #5 (borde en línea que «mata» el hover del botón base): el hallazgo está OBJETIVAMENTE MAL en su premisa y no lo «arreglé» como pedía |
| `pantalla-resultado` | `5262836` | El revisor tenía razón en todo lo que pude tocar |
| `pantalla-bloqueo` | `fcee017` | CÓMO CIERRO CADA PUNTO DEL REVISOR.

1) BLOQUEANTE (suite roja por `nombre` sin defecto y sin entrada en `propsObligatorias()`) |
| `formulario-tramite` | `5d078f2` | Acepto los cinco hallazgos menos uno, que explico abajo |
| `ajustes-cuenta` | `94b8b3a` | CORREGIDO EL BLOQUEANTE, Y ES EXACTAMENTE COMO LO DESCRIBIÓ EL REVISOR |
| `bandeja-trabajo` | `c9e84e0` | Hallazgo por hallazgo del revisor:

(1) Punto 5 del juez, «no hay pantalla fuera de Filament»: el REVISOR SE EQUIVOCA |
| `agenda-horas` | `58d6a93` | Cómo resolví cada punto del revisor:

1) BLOQUEANTE (una hora de otro día queda elegida) |
| `asistente` | `8f4c0b5` | Los bloqueantes e importantes del revisor eran correctos y los reproduje antes de arreglarlos.

1 |
| `plantilla-pantalla` | `75a5027` | Qué cambió respecto de la revisión:

1) **Bloqueante TodosRendericanTest.** No puedo editar ese archivo |
| `ring` | `e2986c1` | Cierre de lo que refutó el revisor.

BLOQUEANTE 1, barrida de las duraciones (punto 4 del juez):
- progress (.5s), chart-donut (.7s) y chart-bar (.6s) pasan a var(--muni-dur-slow, 600ms), igual que ring |
| `gob-escudo` | `e2986c1` | 1) Bloqueante (paso 3 entregado aunque estaba bloqueado): el revisor tiene razón |
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
| `tooltip` | 3 | La ayuda breve de un control, sobre todo de los botones de solo icono de las filas. | Los botones de icono de la fila del listado de patentes morosas (ver, anular, imprimir orden d… | bajo |

## 2. Componentes que faltan

| Pieza | Prio | Qué resuelve | Trámite donde se usa | Esfuerzo |
|---|---|---|---|---|
| `combobox` | 2 | Buscar y elegir un registro dentro de una lista larga escribiendo, en vez de desplegar mil opc… | El buscador de titular en la recepción de solicitudes ARCOP —el campo que hoy tapa el parche d… | alto |
| `popover` | 2 | Un panel flotante anclado a un disparador, con contenido y controles dentro, que no bloquea la… | El panel de filtros por estado y fecha de la bandeja de solicitudes de Atención al Vecino; la … | medio |
| `checkbox-group` | 2 | Un grupo de casillas como un solo campo: fieldset con legend, y el error del grupo dicho una v… | Los requisitos que marca el funcionario al recibir una solicitud de patente y las categorías d… | bajo |

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

## 3. Composiciones de pantalla que faltan

| Pieza | Prio | Qué resuelve | Trámite donde se usa | Esfuerzo |
|---|---|---|---|---|
| `ficha-persona` | 2 | La pantalla del sujeto: identidad arriba (foto, nombre, RUN en .muni-num, edad, domicilio, est… | Ficha del vecino en Atención al Vecino, ficha del titular en Licencias de Conducir, ficha de l… | medio |
| `vitrina` | 2 | Una vitrina navegable, servida por el propio paquete, que muestra cada componente con sus vari… | No es un trámite: es la referencia que consultan los seis sistemas antes de inventar un compon… | medio |

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
