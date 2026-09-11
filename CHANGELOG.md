# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Este paquete usa versionado 0.x: mientras el primer número sea 0, un cambio de la
segunda cifra puede romper compatibilidad y hay que leer la nota de la versión.

**Este archivo se creó el 2026-09-06**, tarde: el paquete llevaba 33 versiones sin
registro. Las entradas anteriores a esa fecha se reconstruyeron desde los mensajes de
las etiquetas de git, así que dicen *qué* cambió pero no siempre *qué había que
republicar*. Desde acá en adelante cada versión anota si el sistema que la adopta debe
volver a publicar artefactos, porque subir el `composer.json` no aplica nada por sí solo.

## [Sin publicar]

### Agregado
- `docs/INVENTORY.md`: inventario de los 53 componentes con props, tokens, Alpine,
  colores literales y accesibilidad, verificado contra el disco.
- `docs/THIRD_PARTY_NOTICES.md`: procedencia y licencia de cada fuente que informa al
  paquete, con la reja de licencias aplicada.
- `docs/INSPIRATION.md`: lo aprendido de productos comerciales que no se pueden copiar.
- `docs/GAP-ANALYSIS.md`: 55 fichas de lo que falta, con el trámite municipal donde se usa.
- `scripts/a11y-check.py` y `npm run a11y`: reja de accesibilidad que mide contraste real
  en claro y oscuro y corre axe-core. Falla ante cualquier violación grave o crítica.
- `package.json`: existe solo para correr esa reja; el paquete sigue sin bundle propio.
- `<x-muni::skip-link>`: enlace de salto al contenido, cableado en los tres armazones.
- `DESIGN.md`, `SKILL.md` y `registry.json`: el contrato de diseño en prosa, la guía para construir
  una pantalla, y el catálogo legible por máquina de los 54 componentes.
- Seis componentes de formulario: `<x-muni::textarea>`, `<x-muni::checkbox>`,
  `<x-muni::rut-input>`, `<x-muni::date-input>`, `<x-muni::error-summary>` y
  `<x-muni::announcer>`.
- Una vitrina que renderiza los componentes REALES en los dos temas, generada desde una
  prueba, para que la reja deje de medir solo las demos escritas a mano. Se mide con
  `npm run a11y:vitrina`, y la vitrina entra además en el conjunto por omisión de `npm run a11y`.
- `<x-muni::table-header>`: la franja entre el título y la tabla, con contador que distingue el
  filtrado del total.
- `sortable-table` gana selección en lote (`selectable`, `rowKey`, `selectionScope`,
  `selectionTotal`, `selectionName`) y orden declarado por columna (`'sort'`).
- `data-table` gana cabecera y primera columna fijas, alto máximo y densidad, todo opt-in.
- Props nuevas, todas aditivas: `tabs` gana `id`, `label` y `activation`; `sortable-table` gana
  `caption`; `segmented` gana `label`; `file-dropzone` gana `maxMb`.
- **El paquete aprende a imprimir.** `<x-muni::hoja>` es la hoja carta con membrete, folio,
  bloques de emisor y titular, firma y pie con numeración y leyenda de verificación; el cuerpo
  queda LIBRE a propósito, porque la estructura de un certificado, un acta o un oficio la fijan
  la Ley 19.880 y el municipio, no el sistema de diseño. Con ella llegan las reglas base de
  `@media print` a las dos hojas de estilo y las de tabla al `@once` de `data-table`.
- `<x-muni::diff-campos>`: la tabla antes/después por campo, con agregado, modificado y
  suprimido legibles en texto. Es la única pieza de la bitácora de auditoría que corresponde a
  este paquete; la pantalla completa se implementa en `laravel-arcop-panel`.
- `timeline` deja de comunicar el estado solo con el color del punto, y gana `toneLabel`,
  `actor`, `current` (`aria-current="step"`), `datetime` ISO y `detail` en un `<details>`
  nativo. Todas las claves son opcionales dentro de `$item`.
- `topbar` gana la clase `muni-topbar`, para que se pueda ocultar al imprimir sin depender de
  su atributo `style` en línea.
- `<x-muni::combobox>`: buscar y elegir en una lista larga, para formularios **fuera de
  Filament**. No retira `nombre-accesible-select.js`: el titular ARCOP lo pinta Filament con
  `Select::searchable()`, y retirar ese parche exige una clase `Field`, que es otra ficha.
- `<x-muni::popover>`: panel flotante sobre el atributo **nativo** `popover`. El top-layer
  resuelve el recorte dentro de una tabla, el light-dismiss y el retorno de foco sin JS, y por
  eso sobrevive al remorfeo de Livewire, que el `modal` actual no puede.
- `<x-muni::checkbox-group>`: grupo de casillas como un solo campo, con el error atado al
  `<fieldset>` y no repetido en cada casilla. Ninguna viene premarcada: bajo la Ley 21.719 el
  consentimiento pre-marcado no es consentimiento válido.
- `npm run a11y:vitrina` mide los componentes reales; `npm run registro` regenera
  `registry.json` con `scripts/gen-registry.py`, que antes vivía fuera del repo.

### Corregido
- **`file-dropzone` tumbaba el Alpine de la página entera** si la etiqueta traía un apóstrofo: el
  texto se interpolaba dentro de una cadena de comillas simples.
- **`sortable-table` no se podía ordenar sin mouse**: el clic iba en un `<th>` no enfocable.
- **`tabs` movía la selección pero no el foco** al usar las flechas, y no tenía `Home` ni `End`.
- **`segmented` se quedaba sin ningún indicador de foco** donde `:has()` no resuelve.
- `modal` y `drawer` generaban el id de su título con `uniqid()`, que rompe el diff de Livewire y
  deja cualquier `aria-labelledby` externo apuntando al vacío.
- El mensaje de error de `file-dropzone` medía 4,17:1 en modo oscuro sobre la superficie del
  anfitrión; ahora lleva fondo propio y no depende de dónde se ponga.
- **`--muni-hint` no llegaba a 4,5:1 en modo claro**: 4,16:1 sobre `--muni-surface-3` y 4,47:1
  sobre `--muni-bg`, que son las superficies donde vive el texto de ayuda de un campo. Pasa de
  `#6b7280` a `#656c79`. La prueba que ya existía cubría ese mismo token **solo en oscuro**.
- El mismo fondo propio se extendió al mensaje de error de `input`, `select` y `switch`.
- `dropdown` lanzaba `Undefined variable $trigger` si se usaba sin ese slot.
- **`--muni-muted` en el oscuro del panel medía 4,33:1 sobre el fondo de un aviso `warn`**
  (4,41 en `ok`, 4,37 en `info`): estaba calibrado contra las superficies del panel y nunca
  contra los cuatro fondos de estado, que aclaran al mezclar un 18% del tono. Pasa de `#94a3b8`
  a `#a3b1c4`: 5,10:1 en el peor caso y sigue por debajo de `--muni-text`. Tres sistemas lo
  parcheaban en su `panel.css`; ya pueden quitar el parche.
- **La pestaña activa del panel no cambiaba de color**: el tema coloreaba `.fi-tabs-item.fi-active`,
  pero Filament 5.7 pinta el `span.fi-tabs-item-label` interior (y el ícono) con
  `primary-700`/`primary-400`, así que en oscuro quedaba en el primario de Filament: 4,33:1 en
  rrhh-graneros. La regla apunta ahora al `span` y al ícono, en claro y en oscuro. La prueba lee
  el marcado real de Filament desde `vendor/` para que un cambio de clase vuelva a caer acá.
- **La barra superior seguía en el gris de Filament**: `.fi-topbar > nav` no alcanza a nada en
  Filament 5.7, donde la barra ES `nav.fi-topbar`. Ni el cristal cálido del claro ni el fondo
  oscuro se aplicaban (seguridad-graneros la medía en `zinc-900`).
- **El anillo de foco de la barra lateral tardaba 160 ms en aparecer**: `.fi-sidebar-item-btn`
  transicionaba `all`, que arrastra `outline-color` y `box-shadow`, y axe/CDP lo medían a mitad
  de camino. Ahora transiciona solo `background-color` y `color`; una prueba prohíbe `all`,
  `outline` y `box-shadow` en cualquier `transition` del tema.
- Cayó el último `uniqid()` del paquete: los siete componentes que generaban ids así ahora los
  derivan del `name`.
- **El orden numérico de `sortable-table` estaba mal con formato chileno**: `1.240.000` se
  convertía en `1,24`, así que el padrón de patentes morosas se ordenaba al revés.
- **La región desplazable de `data-table` no se alcanzaba con teclado** y sus celdas no declaraban
  a qué encabezado pertenecían.
- **En `pagination` los extremos inertes seguían siendo enlaces activables**, el apagado se dibujaba
  con opacidad —que arrastra el contraste— y la página actual solo se marcaba con color.
- Las demos mostraban el ámbar de aviso corregido en agosto con su valor viejo (3,11:1), el gris de
  ayuda a 3,19:1 y 2,59:1, y ninguna declaraba `<html lang>`.
- **La hoja imprimía «Página 0 de 0»**: `counter(page)` solo resuelve dentro de una caja de
  margen de `@page`. Degradaba a un dato falso, no a un hueco. Verificado con `page.pdf()` en
  Chromium 151 y Firefox 153.
- **La burbuja de `tooltip` salía como una tira vertical con las palabras partidas** (25×367 px
  en vez de 165×26): `position-area` convierte la región elegida en el bloque contenedor. La
  causa de fondo era un comentario que afirmaba que Firefox no soportaba anchor positioning —
  Firefox 153 sí lo soporta, así que los dos motores entraban por la rama rota.
- **La píldora de estado de `diff-campos` se sobreimprimía con la columna vecina**: en un
  teléfono de 390 px estaba rota siempre.
- **El menú del panel no abría**: el burger de `dashboard-shell` llevaba `@click` sin ningún
  `x-data` antecesor, y la lateral cerrada seguía recibiendo el foco con `Tab`.
- **La flecha de orden de `sortable-table` medía ~1,4:1** (`opacity:.3`), y el separador de
  `gob-bar` 2,89:1. Los dos van `aria-hidden`, y hasta ahora la reja no medía los subárboles
  decorativos.
- Las 14 demos pasan la reja: eran 17 combinaciones en rojo, con 9 campos sin etiqueta asociada.
- **El borde de los controles de formulario fallaba 1.4.11 en los dos temas**: 1,28:1 y 1,61:1 en
  claro, 1,43:1 y 1,90:1 en oscuro, contra el 3:1 que exige la norma para identificar un
  componente. En un campo el borde es lo único que dice dónde empieza. Token nuevo
  `--muni-field-border`, declarado también en el tema del panel.

### Hay que republicar
**Sí, esta vez.** Los pasos completos están en [`docs/UPGRADE-0.18.0.md`](docs/UPGRADE-0.18.0.md),
que además lista lo que cambia de aspecto sin que el sistema host haga nada.

El token `--muni-field-border` y las reglas de impresión se
declaran también en `muni-ui-filament.css`, porque `muni-ui.css` no se carga dentro de un
panel. Todo sistema con `MuniPanel` tiene que correr
`vendor:publish --tag=muni-ui-filament --force` o el borde de sus campos se quedará sin color
dentro del panel —sin ningún error visible— y lo que imprima desde el panel saldrá con la barra
lateral y la topbar encima.

Los cuatro arreglos del tema del panel (`--muni-muted` oscuro, pestaña activa, barra superior y
transición de la barra lateral) viajan en esa misma hoja: sin republicar no llegan. Al republicar,
scaffold-laravel-filament-pwa, rrhh-graneros y seguridad-graneros pueden retirar los parches
locales equivalentes (`panel.css`, `theme.blade.php` y `theme.css`).

El resto de los cambios son componentes Blade, que viajan dentro del paquete y no necesitan
republicación.

## [0.17.1] — 2026-09-05

El foco se ve dentro de Filament: el indicador pasa a ser un `outline`, porque la
cadena de sombras de Tailwind y Filament se comía el anillo de `box-shadow`.

## [0.17.0] — 2026-09-04

Trampa de foco en modal/drawer/paleta, contraste AA 4.5:1 en oscuro, eliminación de animación opacity:0, 19 tokens CSS, meta de Reverb fuera del título, PHPStan y Pint en CI

## [0.16.4] — 2026-08-30

La barra de progreso dice de qué es, y el texto tenue se lee

## [0.16.3] — 2026-08-30

Los componentes del paquete dejan de verse rotos dentro del panel

## [0.16.2] — 2026-08-30

Los selects con buscador anuncian la etiqueta del campo (WCAG 4.1.2)

## [0.16.1] — 2026-08-29

Corrige el contraste en modo oscuro de la etiqueta de los KPI y de la pestaña activa.

## [0.16.0] — 2026-08-25

El botón del expediente pregunta al módulo

## [0.15.0] — 2026-08-24

El panel ARCOP delega las reglas legales en el núcleo del módulo

## [0.14.0] — 2026-08-24

Panel ARCOP (Ley 21.719)

## [0.13.0] — 2026-08-23

Completa la mudanza: el paquete también enciende la tarjeta de video

## [0.12.2] — 2026-08-20

Paneles laterales con el boton de guardar fuera de la pantalla

## [0.12.1] — 2026-08-18

Cuatro huecos del modo oscuro

## [0.12.0] — 2026-07-18

Infra JS compartida del ecosistema (echo, leaflet, mapa-personas)

## [0.11.0] — 2026-07-18

Plugin MuniPanel — viste cualquier panel con la identidad municipal

## [0.10.8] — 2026-07-18

Quitar overflow:hidden del modal — rompía el scroll interno

## [0.10.7] — 2026-07-18

Alinear las tabs de relation managers con el contenido

## [0.10.6] — 2026-07-18

Rediseño bold 'Sala de Gobierno' — sidebar oscuro petróleo + acentos oro/lima

## [0.10.5] — 2026-07-18

V7 — riqueza visual (cabecera con regla, stat cards con glow, footer sidebar, secciones con tick)

## [0.10.4] — 2026-07-18

V6 — caída escalonada, formularios/botones/modales/tabs, responsive, defensa de iconos

## [0.10.3] — 2026-07-18

Tema maximizado 'instrumento de gobierno' (verificado en vivo)

## [0.10.2] — 2026-07-18

Tema mucho más marcado — sidebar papel, tarjetas y acentos institucionales

## [0.10.1] — 2026-07-18

Selector correcto del ítem activo (fi-sidebar-item-btn)

## [0.10.0] — 2026-07-18

Tema Filament municipal (CSS plano) para que los paneles no se vean genéricos

## [0.9.0] — 2026-07-18

Set final informativo, 3 públicas + 2 intranet

## [0.8.0] — 2026-07-18

Usar el escudo OFICIAL del municipio (PNG) en vez del SVG reconstruido

## [0.7.0] — 2026-07-18

Cinturón institucional en las 5 landings del ecosistema

## [0.6.0] — 2026-07-18

Pantalla de seguimiento de solicitud (timeline + documentos + cita)

## [0.5.0] — 2026-07-18

+3 componentes (otp-input MFA, rating, calendar)

## [0.4.0] — 2026-07-18

+4 componentes de datos (chart-bar, chart-donut, accordion, drawer)

## [0.3.0] — 2026-07-18

Galería de plantillas (landing, login, portal vecino, paneles por rol, error 404)

## [0.2.0] — 2026-07-18

+5 componentes de máximo nivel (command-palette, avatar, progress, skeleton, empty-state)

## [0.1.0] — 2026-07-17

Refactor(css): separar puente Tailwind v4 → paquete universal TW3/TW4
