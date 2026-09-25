# laravel-muni-ui

Sistema de diseño y componentes Blade del **ecosistema municipal de Graneros**.
Centraliza en un paquete lo que hoy está copiado entre sistemas (`feria.css`, `discapacidad.css`):
un contrato de tokens `--muni-*` con dos temas seleccionables y un set de componentes de
panel de datos (topbar, KPIs, tabla densa, filtros, badges).

## Filosofía: maquinaria + presets

El paquete NO impone un look. Empaqueta la **maquinaria** (tokens, componentes) y trae dos
**presets** de arranque para sistemas nuevos:

| Tema | Linaje | Tipografía | Uso |
|------|--------|-----------|-----|
| `light` | discapacidad (`.om`) | DM Sans / DM Mono | institucional sereno, teal |
| `dark` | feria (`.fc`) | IBM Plex Sans / Mono | terminal de datos, alto contraste |

Un sistema existente puede **sobreescribir los tokens** (`--muni-*`) para conservar su
identidad propia sin tocar los componentes.

## Dark mode universal

El sistema activa el tema oscuro con **cualquiera** de estos mecanismos, para convivir con
todo el ecosistema a la vez — no hay que elegir uno:

| Mecanismo | Para qué |
|-----------|----------|
| `<html class="dark">` | **Filament** (su toggle) y Tailwind class-strategy |
| `<html data-muni-theme="dark">` | Nuestro atributo — congela un tema fijo (feria/disc) |
| `<html data-theme="dark">` | Convención de otras UI / PWA-SPA |
| `@media (prefers-color-scheme: dark)` | PWA/SPA que sigue el OS (fallback automático) |

**Jerarquía** (de menor a mayor prioridad): light por defecto → dark por preferencia del OS →
activadores de clase/atributo (ganan sobre el OS) → `data-muni-theme` explícito (override final).

- Dentro de un **panel Filament**: no pongas `data-muni-theme` — los `<x-muni::*>` siguen
  automáticamente el toggle `.dark` de Filament.
- Un **sistema con identidad fija** (discapacidad siempre claro): pon `data-muni-theme="light"`
  y queda inmune al OS y a un `.dark` de un ancestro.
- Una **PWA que sigue el OS**: no pongas nada — `prefers-color-scheme` decide.

### Al agregar una regla de color a `muni-ui-filament.css`

Toda regla que fije `color` para el modo claro necesita su contraparte `.dark`. No es una
recomendación de estilo: las reglas de este archivo llevan `!important` y ganan sobre las
de Filament —que vienen con `:where(.dark, .dark *)`, especificidad CERO a propósito—, así
que **la variante clara se aplica también en oscuro** y el texto queda encima de un fondo
para el que no fue pensado.

Pasó dos veces y las dos se descubrieron midiendo, no mirando: la etiqueta de los KPI
quedaba en 1,22:1 y la pestaña activa en 1,52:1, contra un mínimo AA de 4,5:1. En una
captura se ven «tenues», no ausentes.

Los tonos para fondo oscuro ya existen y conviene reutilizarlos en vez de inventar:
`--mg-sb-txt` para texto principal, `--mg-sb-mut` para atenuado, `--mg-lima-br` para
acento.

## Requisitos

- PHP 8.3+, Laravel 12 o 13
- Tailwind CSS **v4** (usa `@theme`, `@custom-variant`, `@source`)
- Fuentes self-hosted (recomendado): `@fontsource/ibm-plex-sans`, `@fontsource/ibm-plex-mono`,
  `@fontsource/dm-sans`, `@fontsource/dm-mono`. Sin ellas, el sistema degrada a `system-ui`.

## Instalación

```bash
composer require muni-graneros/laravel-muni-ui
```

El repo es privado (SSH). En el `composer.json` del proyecto:

```json
"repositories": {
    "muni-ui": { "type": "vcs", "url": "https://github.com/muni-graneros/laravel-muni-ui.git" }
}
```

En `resources/css/app.css`:

```css
@import "tailwindcss";
@import "../../vendor/muni-graneros/laravel-muni-ui/resources/css/muni-ui.css";
@source "../../vendor/muni-graneros/laravel-muni-ui/resources/views/**/*.blade.php";
```

Para personalizar los tokens por proyecto, publica el CSS y edítalo:

```bash
php artisan vendor:publish --tag=muni-ui-css   # → resources/css/vendor/muni-ui.css
```

### Al ACTUALIZAR el paquete en un sistema

Subir la versión **no aplica nada por sí solo**: el tema de Filament y el escudo son
artefactos ya copiados a `public/vendor/muni-ui/`, y ahí se quedan hasta que se los vuelva
a publicar. Un sistema puede estar en la última versión y seguir sirviendo el CSS viejo,
sin ninguna señal de que algo quedó atrás.

```bash
composer update muni-graneros/laravel-muni-ui
php artisan vendor:publish --tag=muni-ui-filament --force   # el tema; SIN --force no pisa
php artisan vendor:publish --tag=muni-ui-images --force     # solo si cambió el escudo
npm run build                                               # si el sistema tiene tema propio
```

Y reiniciar el servidor de aplicación si corre con Octane: el manifiesto de Vite queda
cacheado en el proceso.

#### Cómo saber si un sistema quedó atrás

El síntoma no se ve: el panel carga, no hay error en consola y los componentes salen con
las variables vacías. La comprobación barata es comparar la copia publicada con la del
paquete instalado:

```bash
diff public/vendor/muni-ui/filament.css vendor/muni-graneros/laravel-muni-ui/resources/css/muni-ui-filament.css
```

Cualquier salida significa que hay que republicar con `--force`. El 2026-09-04, los nueve
sistemas del ecosistema servían una copia anterior a los 19 tokens del puente
`--muni-*`: `--muni-accent-strong`, `--muni-danger-bg` y `--muni-ok-bg` no existían en
ninguna, así que el `:hover` del botón primario y el fondo de los avisos de peligro
quedaban sin valor.

## Uso

```blade
<x-muni::app-shell theme="dark" system="Patentes Comerciales" subtitle="Municipalidad de Graneros" status="online">
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
        <x-muni::kpi :value="number_format($total, 0, ',', '.')" label="Resultado del filtro" />
        <x-muni::kpi :value="$morosos" label="Morosas" tone="danger" />
        <x-muni::kpi :value="$total - $morosos" label="Al día" tone="ok" />
    </div>

    <x-muni::filter-bar :action="route('patentes')">
        <x-muni::field label="Buscar"><input name="buscar" value="{{ $filtros['buscar'] ?? '' }}"></x-muni::field>
        <x-muni::field label="Morosidad">
            <select name="morosa"><option value="">Todas</option><option value="SI">Solo morosas</option></select>
        </x-muni::field>
    </x-muni::filter-bar>

    <x-muni::data-table :columns="['Razón social', 'RUT', 'Tipo', 'Estado']">
        @foreach ($filas as $fila)
            <tr data-muni-row @class(['muni-row--danger' => $fila['morosa'] === 'SI'])>
                <td>{{ $fila['razon_social'] }}</td>
                <td class="muni-num">{{ $fila['rut'] }}</td>
                <td>{{ $fila['tipo'] }}</td>
                <td><x-muni::badge :tone="$fila['morosa'] === 'SI' ? 'danger' : 'ok'">{{ $fila['morosa'] === 'SI' ? 'Morosa' : 'Al día' }}</x-muni::badge></td>
            </tr>
        @endforeach
    </x-muni::data-table>
</x-muni::app-shell>
```

## Componentes

| Componente | Props principales |
|-----------|-------------------|
| `<x-muni::app-shell>` | `theme` (light/dark), `system`, `subtitle`, `status`, `title`, `maxWidth` |
| `<x-muni::topbar>` | `system`, `subtitle`, `status` (online/degraded/offline), `logo` (slot) |
| `<x-muni::page-header>` | `title`, `subtitle`, `eyebrow`; slot `actions` |
| `<x-muni::stat>` | `value`, `label`, `tone`, `delta`, `deltaDir` (up/down), `spark` (array→sparkline), `hint` |
| `<x-muni::kpi>` | `value`, `label`, `tone` (neutral/ok/warn/danger/info), `hint` |
| `<x-muni::badge>` | `tone`, `dot` |
| `<x-muni::alert>` | `tone` (ok/warn/danger/info), `title`, `icon` (slot HTML) |
| `<x-muni::card>` | `title`, `subtitle`, `flush`; slot `actions` |
| `<x-muni::button>` | `variant` (primary/ghost/subtle/danger), `size` (sm/md/lg), `href`, `icon`, `type` |
| `<x-muni::segmented>` | `name`, `options` (array), `value`, `label` — radios reales sin JS; o slot |
| `<x-muni::filter-bar>` | `action`, `method`; slots `submitLabel`, `actions` |
| `<x-muni::field>` | `label`; el control (input/select) va en el slot |
| `<x-muni::data-table>` | `columns`, `empty`, `caption`, `label`, `stickyHeader`, `stickyColumn`, `maxHeight`, `density`; el slot son los `<tr data-muni-row>` |
| `<x-muni::pagination>` | `current`, `total`, `url` (closure fn(\$p)), `info`, `paginator` (LengthAwarePaginator) |

### Interactivos (requieren Alpine 3)

| Componente | Props principales |
|-----------|-------------------|
| `<x-muni::dropdown>` | `align` (start/end), `width`, `label`; ítems `<x-muni::dropdown-item>`. La ranura `trigger` es el **contenido** del botón, no el botón: si le pasas un `<button>` o un `<a>` propio, el componente no lo envuelve y le estampa el ARIA encima |
| `<x-muni::dropdown-item>` | `href`, `icon`, `tone` (default/danger) |
| `<x-muni::modal>` | `title`, `maxWidth`; slots `trigger`, `footer` |
| `<x-muni::tabs>` | `tabs` (array de labels), `default`, `id`, `label`, `activation` (auto\|manual); paneles `<x-muni::tab-panel :index>` |
| `<x-muni::tab-panel>` | `index` — panel de `<x-muni::tabs>`; se enlaza solo con su pestaña |
| `<x-muni::skip-link>` | `target` (por defecto `#muni-contenido`) — va de primero en el `<body>`; los tres armazones ya lo traen |
| `<x-muni::sortable-table>` | `columns`, `rows`, `empty`, `searchable`, `caption`, `selectable`, `rowKey`, `selectionScope`, `selectionTotal`, `selectionName` — orden por teclado con formato chileno, `aria-sort` y selección en lote |
| `<x-muni::file-dropzone>` | `name`, `accept`, `label`, `hint`, `multiple`, `maxMb` — arrastrar y soltar con quitar y validación |
| `<x-muni::toast-host>` | `position`; colocar UNA vez. Disparar: `$dispatch('muni-toast', {tone, title, message})` |

```blade
{{-- Modal --}}
<x-muni::modal title="Dar de baja la patente">
    <x-slot:trigger><x-muni::button variant="danger">Dar de baja</x-muni::button></x-slot:trigger>
    Se marcará <b>{{ $patente->razon_social }}</b> como cesada.
    <x-slot:footer>
        <x-muni::button variant="ghost" x-on:click="open=false">Cancelar</x-muni::button>
        <x-muni::button x-on:click="open=false; $dispatch('muni-toast',{tone:'ok',message:'Patente dada de baja'})">Confirmar</x-muni::button>
    </x-slot:footer>
</x-muni::modal>

{{-- Toast: colocar el host una vez, disparar desde cualquier parte --}}
<x-muni::toast-host />
<button x-on:click="$dispatch('muni-toast',{tone:'ok',title:'Guardado',message:'Cambios guardados.'})">Guardar</button>
```

### Formularios, navegación y plantillas de página

| Componente | Props principales |
|-----------|-------------------|
| `<x-muni::input>` | `label`, `name`, `type`, `error`, `hint`, `icon`, `required` |
| `<x-muni::select>` | `label`, `name`, `options`, `selected`, `placeholder`, `error` |
| `<x-muni::switch>` | `label`, `name`, `checked`, `description` (Alpine) |
| `<x-muni::textarea>` | `label`, `name`, `error`, `hint`, `required`, `rows`, `maxlength` — contador en texto |
| `<x-muni::checkbox>` | `label`, `name`, `checked`, `description`, `error`, `required`, `value` — nunca premarcada |
| `<x-muni::checkbox-group>` | `name` y `legend` (obligatorias), `options`, `selected`, `hint`, `error`, `required` — fieldset con legend; el error del grupo se dice una vez, no una por casilla |
| `<x-muni::rut-input>` | props de `input` — formatea 12.345.678-9 y valida el dígito verificador |
| `<x-muni::date-input>` | props de `input`, más `min`/`max` — control nativo, ayuda en dd-mm-aaaa |
| `<x-muni::error-summary>` | `errors` — resumen tras un envío fallido; recibe el foco y enlaza cada campo |
| `<x-muni::announcer>` | — región viva compartida; va una vez por página, en el armazón |
| `<x-muni::table-header>` | `title`, `level`, `total`, `filtered`, `unit`, `countLabel`; ranuras `search`, `filters`, `actions` |
| `<x-muni::diff-campos>` | `changes`, `caption`, `empty`, `nilLabel` y los cuatro encabezados — tabla antes/después por campo; el estado se lee en texto, no en el color |
| `<x-muni::sidebar>` | `width`; slot con `<x-muni::nav-section>` + `<x-muni::nav-item>` (colapsa en móvil) |
| `<x-muni::nav-item>` | `href`, `icon`, `active`, `badge` |
| `<x-muni::nav-section>` | `title` |
| `<x-muni::nav-menu>` | `items` (obligatoria), `actual`, `label` — pinta el menú desde un árbol. El anfitrión pasa el árbol **ya filtrado** y el permiso **ya evaluado**: el paquete no consulta `Gate` ni `request()` |
| `<x-muni::nav-grupo>` | `titulo` (obligatoria), `abierto`, `icono` — sección plegable con `aria-expanded` real; el plegado va `inert` |
| `<x-muni::breadcrumb>` | `items` (array de `['label','url'?]`) |
| `<x-muni::tooltip>` | `text`, `placement`, `describe`/`as` (Alpine) — la burbuja va `aria-hidden` por defecto: el nombre del botón va en su `aria-label`, no acá |
| `<x-muni::popover>` | `id`, `label`, `align`, `width`, `closeLabel`; ranuras `trigger` y contenido — panel flotante sobre el atributo nativo `popover`; el slot admite un `<form method="get">` entero |
| `<x-muni::avatar>` | `name` (iniciales), `src`, `size`, `tone` |
| `<x-muni::progress>` | `value`, `max`, `tone`, `label`, `showValue` |
| `<x-muni::skeleton>` | `width`, `height`, `rounded` (shimmer) |
| `<x-muni::empty-state>` | `title`, `description`, `icon`; slot `actions` |
| `<x-muni::timeline>` | `items` — claves `title`, `time`, `description`, `tone`, `toneLabel`, `actor`, `current`, `datetime`, `detail`; hito auditable, sin Alpine |
| `<x-muni::command-palette>` | `items`, `placeholder`, `hotkey` — ⌘K/Ctrl+K (Alpine) |
| `<x-muni::combobox>` | `name` (obligatoria), `options`, `value`, `selectedLabel`, `search`, `model`, `searchModel`, `hint`, `error` — buscar y elegir en una lista larga; el valor vive en un input oculto, no en Alpine |
| `<x-muni::auth-shell>` | `theme`, `title`, `system`, `subtitle`, `logo`; slots `aside`, `head` — layout login/registro |
| `<x-muni::dashboard-shell>` | `theme`, `system`, `subtitle`, `status`, `user`; slots `sidebar`, `topbar` — layout de panel |
| `<x-muni::error-page>` | `code`, `title`, `message`, `home`, `theme` — 403/404/500/503 |
| `<x-muni::hoja>` | `organization`, `unit`, `type`, `folio`, `date`, `verification`, `crest`, `printable`, `level`; ranuras `emisor`, `titular`, `firma`, `actions` y cuerpo libre — la hoja carta que sale por impresora |

| `<x-muni::stat>` | `value`, `label`, `tone` (neutral/ok/warn/danger/info), `delta`, `deltaDir` (up/down), `spark` (array→sparkline), `hint`, `id` — cifra atada a su rótulo con `role="group"` + `aria-labelledby`; `deltaDir` pone «subió»/«bajó» en texto oculto delante del delta y la flecha de 12 px (sin `deltaDir` el delta es neutro, sin sentido inventado). El rótulo recibe `{id}-rotulo`; sin `id` se deriva `muni-stat-{slug del rótulo}-rotulo`, así que dos stats con el mismo rótulo en una página necesitan `id` propio o repiten id. No emite región viva: si la cifra se refresca por polling, el anuncio es contrato del host |
| `<x-muni::alert>` | `tone` (ok/warn/danger/info), `title`, `icon` (nombre del catálogo: ok/warn/danger/info; un SVG propio va en `<x-slot:icon>`, que es marcado del desarrollador y jamás datos del vecino), `role` (sin valor por defecto: el recuadro es contenido; el consumidor pone `status` o `alert` solo cuando el mensaje de verdad se anuncia) — recuadro de aviso fijo con glifo propio por tono, cuerpo a 4,5:1 en las cuatro paletas |
| `<x-muni::calendar>` | `name`, `min`, `value` (cadena `Y-m-d` o `DateTimeInterface`; lo inválido se descarta entero) — calendario de mes en Alpine: elige un día, escribe la fecha ISO en un campo oculto y se entrelaza con `wire:model`/`x-model` en las dos direcciones (`x-modelable`); los días previos a `min` llevan `aria-disabled` y tachado, cada día su fecha larga en `aria-label` y el título del mes es `aria-live` |
| `<x-muni::command-palette>` | `items`, `placeholder`, `hotkey` — ⌘K/Ctrl+K (Alpine core + focus). Sin `trigger` pinta su propio botón con el atajo rotulado desde `hotkey` en `<kbd class="muni-kbd">` (Ctrl o ⌘ según la plataforma; el lector oye «Control K» / «Comando K») |
| `<x-muni::modal>` | `title`, `maxWidth`, `role` (`dialog` \| `alertdialog`), `dismissable` (por defecto `true`; en `false` no hay × ni cierre al clic en el fondo: se elige entre la acción y Cancelar), `describedby` (id propio; un `alertdialog` sin él apunta al cuerpo), `initialFocus` (`cancelar` pone un botón Cancelar propio, primero en el pie, y lo enfoca; o un selector CSS dentro del diálogo), `cancelLabel`; slots `trigger`, `footer`. Escape siempre cancela y solo cierra el diálogo que tiene el foco (un consumidor puede retenerlo con `@keydown.escape.stop` en su campo). Servidor-primero: con un `id` propio se abre y cierra por evento de navegador —`$this->dispatch('muni-modal-open', id: 'anular-4821')` / `muni-modal-close`—, sin depender de Livewire. **Dentro de un panel Filament no va este componente: una acción destructiva se confirma con `->requiresConfirmation()` y su `schema` (motivo obligatorio incluido), que es donde viven las acciones de los nueve sistemas.** |
| `<x-muni::spinner>` | `size` — el dibujo de una carga, decorativo (`aria-hidden`, SVG en línea por la CSP estricta, gira solo sin movimiento reducido); solo no cumple nada: va dentro de `busy-region` o en un botón con `wire:loading` |
| `<x-muni::busy-region>` | `target`, `loading`, `status`, `delay`, `busy`; ranura `status` — región `role="status"` persistente y vacía más `aria-busy` sobre el contenido que Livewire reemplaza; anuncia el RESULTADO ya redactado por el anfitrión, nunca el proceso; retardo nativo `.delay.long` (300 ms), cero Alpine; `busy` va atado al dato (`:busy="$total === null"`), nunca fijo; fuera de los paneles Filament (`x-filament::loading-section` ya lo resuelve) |
| `<x-muni::selector-tema>` | `action` (obligatoria: ruta POST del anfitrión con CSRF y throttle que guarda la cookie), `value` (sistema/claro/oscuro, o light/dark/null), `name`, `label`, `hint`, `submitLabel`, `enabled` — selector de tema sistema/claro/oscuro sobre `segmented`, persistido en una cookie que lee el anfitrión y pasa como `theme` al armazón (nunca localStorage); con Alpine aplica al instante y guarda por fetch, sin JS es un POST con botón Guardar. Desactivable por sistema con `:enabled="false"` (discapacidad, tema fijo). **Prohibido dentro de paneles Filament**: el panel ya trae su conmutador con persistencia propia y serían dos verdades que se pisan al recargar |
| `<x-muni::sesion-guardia>` | `expiraEn` (obligatoria, ISO absoluto del servidor), `renovarUrl`, `salirUrl`, `ingresarUrl`, `avisarA` (s, 300), `dialogoA` (s, 60), `duracion` (SESSION_LIFETIME en s), `ahora`, `id` — avisa antes de que caduque la sesión, prorroga solo con un clic (POST con `@csrf` a `renovarUrl`) y en T=0 tapa la pantalla con un velo opaco. **Sin `renovarUrl` solo avisa y bloquea, no prorroga.** Alpine + plugin Focus |
| `<x-muni::toast-host>` | `position` (bottom-right / bottom-left / top-right / top-left); colocar UNA vez, y solo en vistas públicas Blade: **dentro de un panel Filament no se usa, ahí manda `Notification::make()`** (dos pilas de avisos en la misma esquina son peor accesibilidad que una). Disparar: `$dispatch('muni-toast', {tone, title, message, duration})`. `danger` y `warn` no se autocierran nunca; `ok` e `info` viven entre 5 y 20 s, con pausa al puntero y mientras contengan el foco (WCAG 2.2.1); `duration: 0` significa «no autocerrar» y una `duration` por debajo de 5 s se sube al piso |
| `<x-muni::data-table>` | `columns`, `empty`, `caption`, `label`, `stickyHeader`, `stickyColumn`, `maxHeight`, `density`, `densidad` (`comoda` por defecto o `compacta`) — el slot son los `<tr data-muni-row>`; la fila compacta también se hereda de `<html data-muni-densidad="compacta">`, y `densidad` se pasa con un valor literal de la vista: una grafía inválida revienta, así que el valor del usuario va por el atributo del `<html>`, nunca enchufado a la prop |
| `<x-muni::breadcrumb>` | `items` (arreglo de `['label','url'?]`; también texto suelto, `Collection` o `Arrayable` por miga) — pinta la ruta como `nav` > `ol[role=list]` > `li`: la última miga nunca es enlace aunque traiga `url` y es la que lleva `aria-current="page"`, el enlace va subrayado y no solo coloreado (1.4.1), las migas sin etiqueta se descartan y si no queda ninguna no se emite el landmark, y en papel la ruta no se imprime. Se compone dentro del slot `migas` de `<x-muni::page-header>` |
| `<x-muni::tabs-ruta>` | `items` (solapas ya resueltas: `etiqueta`, `href`, `clave?`, `activo?`, `badge?`, `badgeLabel?`, `ver?`, `navegar?`), `actual`, `label`, `navegar` — solapas que SON navegación: `<nav>` con nombre + `<ul role="list">` + `<a href>` reales + un solo `aria-current="page"`. La regla en una línea: **¿al elegir cambia la URL? entonces `tabs-ruta`**; si no cambia, es `<x-muni::tabs>` (con URL, ARIA prohíbe `role="tablist"`). Sin flechas, sin roving tabindex, sin Alpine. El anfitrión resuelve: el paquete no consulta `request()`, `Auth`, `Gate` ni `route()`, y `ver` es filtro cosmético |
| `<x-muni::description-list>` + `<x-muni::description-item>` | lista: `label` (nombre accesible, opcional), `stacked` (fuerza una columna); par: `label` (obligatoria), `mono` (`.muni-num` para RUT, folios y montos), `empty` (texto del dato ausente) — la ficha como pares dato-valor con semántica real `<dl>/<dt>/<dd>`: dos columnas en escritorio y una en el teléfono (o con `stacked`, para el drawer angosto), el valor por slot (admite badge, enlace o botón), `.muni-num` declarada por el propio componente y `@media print` que no parte el par entre dos hojas |
| `<x-muni::record-list>` | `label`; dentro van `<x-muni::record-item>` (`href`, `tone`, `toneLabel`, `folio`, `folioLabel`, `navegar`; ranuras `title`, `meta`, `actions` y cuerpo libre) — los mismos registros como lista de fichas cuando la tabla no cabe: `role="list"`, banda de estado con su palabra, folio en `.muni-num`, objetivo táctil de 44 px; sin Alpine |
| `<x-muni::input-password>` | props de `input`, más `autocomplete` (solo `current-password` o `new-password`; cualquier otro valor revienta al renderizar), `showLabel`, `hideLabel` — envuelve `input` y le agrega el alternador Mostrar/Ocultar con `aria-pressed` y nombre accesible que cambia; nace oculta, se vuelve a ocultar al enviar el formulario y al salir el foco, y no recuerda el estado |
| `<x-muni::pantalla-resultado>` | `title`, `message`, `folio`, `folioLabel`, `copyLabel`, `tone` (`ok`/`warn`/`danger`/`info`), `printHref`, `printLabel`, `exitHref`, `exitLabel`, `autofocus`; ranura de prosa — el cierre de un trámite: foco al `<h1>`, folio copiable con confirmación EN TEXTO (portapapeles → `execCommand` → selección) y un solo camino de salida, que es lo último enfocable. Sin `print-href` no hay botón de imprimir y sin `exit-href` no hay salida. `autofocus="false"` cuando la pantalla es una tarjeta dentro de otra página |
| `<x-muni::pantalla-bloqueo>` | `nombre` (obligatoria), `cargo`, `avatar`, `modo` (`capa` \| `pagina`), `abierto`, `inactividad` (s, 0 = apagado), `accion`, `campo`, `etiqueta`, `error`, `titulo`, `mensaje`, `entrar`, `otroUrl`, `otroRotulo`, `otroMetodo` (`post` \| `get`), `autofoco`, `id` — bloquea la sesión SIN cerrarla: velo OPACO, quién está conectado y un solo campo de contraseña para volver, con salida a «entrar como otro usuario». **Escape NO cierra** (el manejador existe igual, en el diálogo, y la región viva lo explica). En `capa` es un diálogo modal teleportado con `x-trap.inert` (Alpine + plugin Focus) que se bloquea solo por inactividad o por evento de navegador (`muni-bloqueo-abrir`/`muni-bloqueo-cerrar`, con `detail.id` opcional); en `pagina` es la misma tarjeta con `<h1>`, sin Alpine y sin velo, para meter dentro de `auth-shell`. El desbloqueo es un POST con `@csrf` que funciona sin JavaScript, y la salida también lo es (con `otro-metodo="get"` pasa a enlace). **No emite «Recordarme» ni ningún `name="remember"`: la contraseña la valida el anfitrión contra el usuario de la sesión en curso, con throttle, nunca por cookie.** Conviviendo con `sesion-guardia` en la misma pantalla cede el frente —pintura y árbol de accesibilidad— a la capa que tiene el foco. Bloquear en el navegador protege de la mirada del público, no de quien tiene el teclado: para datos que no pueden quedar ni en el DOM, cerrar sesión |
| `<x-muni::pii>` | `label` (obligatoria), `masked`, `name`, `revealed`, `showLabel`, `hideLabel`, `pendingLabel`; el valor va por slot (admite badge, enlace `tel:`, botón de copiar) — el dato personal se sirve ENMASCARADO y solo se abre con un gesto; al revelarlo dispara el evento DOM `muni-pii-revelado` (burbujea; `detail`: `campo`, `etiqueta`, `id`, `diferido`) para que el anfitrión lo lleve a SU bitácora: el paquete ofrece el gesto, el host prueba el cumplimiento (acá no hay Livewire, Eloquent ni auth con que registrar nada). Sin slot queda en **modo diferido** —el valor no viaja en el HTML, que es la única minimización real de la Ley 21.719— y el host responde al evento devolviendo la vista con el valor y `revealed`. Nace tapado también sin JavaScript (el valor lleva `display:none` en línea y el control `x-cloak`); Escape lo vuelve a tapar sin cerrar el drawer que lo contiene; al imprimir, el control desaparece y sale lo que esté en pantalla |
| `<x-muni::formulario-tramite>` | `title`, `subtitle`, `action`, `method`, `requisitos`, `errors`, `submit-label`, `cancel-href` — el trámite de mesón: resumen de errores arriba con foco, columna lateral de requisitos que se refresca sola, y CSRF y `enctype` emitidos solos |
| `<x-muni::formulario-seccion>` | `legend`, `description`, `columns` — un grupo de campos: `<fieldset>`/`<legend>` (o `<div>` si no hay rótulo) y rejilla de dos columnas que colapsa a una en el teléfono |
| `<x-muni::ajustes-cuenta>` | `id`, `label`, `default`, `perfilLabel`/`seguridadLabel`/`preferenciasLabel`/`peligroLabel`, `sessions` (array ya redactado: `id`/`device`/`detail`/`current`), `sessionsAction`, `sessionsTitle`, `sessionsDescription`, `sessionsEmpty`, `currentLabel`, `closeLabel`, `closeExplanation`, `dangerAction`, `dangerWord`, `dangerTitle`, `dangerDescription`, `dangerLabel`, `dangerName`, `dangerMethod` (post/put/patch/delete), `dangerHint`; ranuras `perfil`, `seguridad`, `preferencias`, `peligro` — La pantalla de «Mi cuenta» en pestañas de verdad (`<x-muni::tabs>`, con ←/→): las tarjetas de cada sección las pone el anfitrión, cada una con su propio guardar; el componente aporta el cierre de una sesión ajena por POST con `@csrf` y confirmación en `alertdialog`, y la zona de peligro con puerta (`aria-disabled`, nunca `disabled`). Sin JS los paneles se revelan por `<noscript>` y los dos formularios siguen alcanzables. |
| `<x-muni::bulk-bar>` | `for` (id de la tabla), `label`, `countSingular`, `countPlural` (`:n` = número), `clearLabel`, `emptyAnnounce`; slot = acciones — barra de acciones en lote para `data-table selectable`. Es `role=region` + `aria-live=polite`, cuenta las casillas `data-muni-pick` marcadas y expone a la ranura `loteN`, `loteIds`, `loteTexto` y `loteLimpiar()`. Escape deselecciona. Enter nunca envía una acción: emite una guarda de envío implícito, así que va ANTES de la tabla dentro del form. Solo actúa sobre lo marcado y visible, nunca «todo el filtro». **ShouldQueue, autorización y bitácora son del anfitrión: el paquete no puede garantizarlas.** Para `sortable-table` se usa su ranura `bulk`, no esta barra |
| `<x-muni::agenda-horas>` | `name`, `mes` (`Y-m`), `value` (día `Y-m-d`), `franjas` (`['Y-m-d' => [['inicio'=>'HH:MM','fin','estado'=>'libre\|tomada\|bloqueada','valor','detalle']]]`), `dias` (carga ya calculada), `franja`, `label`, `accion`, `operacion` (`reservar\|reagendar\|bloquear`), `error`, `mesUrl` (callable → enlaces) o `mesEvento` (bool), slot `acciones` — mes como rejilla APG (flechas, Inicio/Fin, RePág/AvPág; las flechas no salen del mes) con la carga de cada día en su nombre accesible y, al elegir un día, sus franjas como radios reales. La hora elegida es siempre del día elegido: cambiar de día la suelta. No reserva: emite `muni-agenda:accion` `{operacion, dia, franja}` y el servidor decide (doble reserva). Livewire: `wire:model` ata SOLO la hora; el día y el mes van por `x-on:muni-agenda:dia="$wire.set('dia', $event.detail.dia)"` y `mes-evento x-on:muni-agenda:mes="$wire.set('mes', $event.detail.mes)"` (mientras llega el mes la rejilla queda `aria-busy` con «Cargando junio de 2026…»; a los 15 s sin respuesta, error en texto). Sin `mesUrl` ni `mesEvento` no hay ‹ ›. **Ley 21.719:** el `detalle` de TODOS los días enviados se publica en el HTML: manda solo el mes a la vista y textos ya redactados (nunca nombre ni RUN de quien reservó). |
| `<x-muni::asistente>` | steps, current, title, action, method, nav-name, skippable, errors, next-attrs/prev-attrs/skip-attrs, focus-step — trámite largo por pasos en un <form> real: solo el paso actual en el DOM, Enter envía el paso (valida), el foco va al encabezado del paso nuevo también dentro de Livewire, avance con progressbar y región viva, resumen de errores con foco con y sin JS, y el stepper con estados de error y omitido |
| `<x-muni::plantilla-pantalla>` | `title` (obligatorio), `armazon` (dashboard/app), `system`, `system-subtitle`, `document-title`, `subtitle`, `eyebrow`, `:migas`, `aviso`, `aviso-tone`, `aviso-title`, `theme`, `status`, `user`, `:announcer`; ranuras `sidebar`, `topbar`, `head`, `actions`, `pie` — el punto de partida de toda pantalla nueva: arma dashboard-shell o app-shell con el enlace de salto, las migas sobre un único `<h1>`, la región de mensajes (el aviso del servidor se ve y se anuncia una sola vez), la región nombrada con el título, el contenido y el pie de la pantalla, y reserva el alto del topbar sticky para que el foco no quede tapado |
| `--muni-overlay-border` (token) | sin props — el borde de toda superficie que flota (popover, dropdown, modal, drawer, command-palette, toast-host, sesion-guardia, sidebar superpuesta). Llega a 3:1 sobre bg/surface/surface-2/surface-3/panel en las dos hojas y en papel; los componentes lo leen con respaldo a `--muni-border` |
| `<x-muni::ring>` | value, max=100, size=96, tone, label, showValue=true — anillo de avance con role=progressbar en el contenedor del arco: aria-valuenow es el porcentaje acotado, aria-valuemax vale 100 fijo y aria-valuetext dice «N %». Toma el nombre de label, o «Progreso» si no hay (gana un aria-label o aria-labelledby del consumidor). El svg y la cifra van aria-hidden, no hay región viva y el arco anima con --muni-dur-slow |
| `<x-muni::gob-escudo>` | `size`, `src` — escudo oficial de Graneros como una sola imagen; dentro de `topbar` y `auth-shell` va sobre la placa `--muni-logo-plate`, blanca en claro y gris apagado en oscuro |
Casi todos respetan `prefers-reduced-motion` y tienen `:focus-visible`. **Casi, no todos:**
`docs/GAP-ANALYSIS.md` lista los que no, medidos uno por uno. El indicador de foco real es un
`outline` con `var(--muni-focus)`; el anillo `--muni-ring` va encima como halo, porque dentro de
Filament la cadena de sombras se come la `box-shadow`.
Los interactivos usan **Alpine 3 core** (sin plugins): en feria/discapacidad/licencias ya viene
con Filament; en apps sin Filament, `npm i alpinejs` y `Alpine.start()`. El CSS del paquete trae
la regla `[x-cloak]` para evitar el flash inicial.

**Firma del sistema:** la morosidad no es un badge redondo suelto — una fila
`<tr data-muni-row class="muni-row--danger">` pinta una franja de estado en el borde
izquierdo (banda de libro mayor), y los RUT/cifras usan `.muni-num` (mono tabular).

## Ficha de persona: composición, no componente

La pantalla del sujeto —el vecino en Atención al Vecino, el titular en Licencias, la persona
inscrita en Discapacidad, el contribuyente en Patentes— **no tiene componente propio, y no lo va a
tener.** Esos cuatro sistemas guardan a la persona con modelos de datos distintos: un
`<x-muni::ficha-persona>` con props para foto, RUN, domicilio y estado se forkea en el primer
sistema que no calce, y a partir de ahí hay cuatro copias que divergen. Lo que viaja en el paquete
son las piezas; la ficha la compone cada sistema con sus propios datos:

| Pieza | Qué pone en la ficha |
|---|---|
| `page-header` (+ `breadcrumb` en el slot `migas`) | el nombre como único `<h1>`, la ruta encima y las acciones a la derecha |
| `avatar` + `badge` | la identidad de un vistazo y el estado en palabras, no solo en color |
| `description-list` + `description-item` | los pares dato-valor con `<dl>/<dt>/<dd>` real |
| `pii` | todo dato personal que no hace falta ver para atender, **tapado por omisión** |
| `tabs` + `tab-panel` | lo que la persona tiene en el municipio, sin cargar todo a la vista |
| `data-table` y `timeline` | los trámites y la bitácora de lo que pasó |

```blade
<x-muni::page-header :title="$persona->nombre" eyebrow="Ficha de persona">
    <x-slot:migas><x-muni::breadcrumb :items="$migas" /></x-slot:migas>
    <x-slot:actions>
        <x-muni::button variant="ghost" class="muni-no-print" :href="route('vecinos.edit', $persona)">Editar datos</x-muni::button>
    </x-slot:actions>
</x-muni::page-header>

<x-muni::avatar :name="$persona->nombre" size="lg" />
<x-muni::badge tone="ok">Registro vigente</x-muni::badge>

<x-muni::description-list label="Datos de la persona">
    {{-- Con valor en el slot: viaja en el HTML, pero nace tapado (la mirada por encima del hombro). --}}
    <x-muni::description-item label="RUT">
        <x-muni::pii label="RUT" name="rut" :masked="$persona->rutEnmascarado()" class="muni-num">{{ $persona->rut }}</x-muni::pii>
    </x-muni::description-item>
    {{-- Modo diferido: sin slot, el valor NO está en la página hasta que el sistema lo sirve. --}}
    <x-muni::description-item label="Teléfono">
        <x-muni::pii label="Teléfono" name="telefono" :masked="$persona->telefonoEnmascarado()" />
    </x-muni::description-item>
</x-muni::description-list>

<x-muni::tabs :tabs="['Trámites', 'Historial']" id="ficha-pestanas" label="Lo que la persona tiene en el municipio">
    <x-muni::tab-panel :index="0">
        <x-muni::data-table caption="Trámites de la persona" :columns="['Folio', 'Trámite', 'Estado']">
            @foreach ($tramites as $t)
                <tr data-muni-row><td class="muni-num">{{ $t->folio }}</td><td>{{ $t->nombre }}</td><td><x-muni::badge :tone="$t->tono">{{ $t->estado }}</x-muni::badge></td></tr>
            @endforeach
        </x-muni::data-table>
    </x-muni::tab-panel>
    <x-muni::tab-panel :index="1">
        <x-muni::timeline :items="$historial" />
    </x-muni::tab-panel>
</x-muni::tabs>
```

Tres cosas que la composición tiene que resolver y que el paquete **no** puede resolver por ella:

- **La bitácora de accesos es del sistema.** Este paquete no tiene Livewire, Eloquent ni auth con
  que registrar nada. `pii` ofrece el gesto: al revelar, dispara `muni-pii-revelado` con
  `detail.campo`. El sistema lo engancha a su registro (quién vio qué y cuándo, Ley 21.719) y, en
  modo diferido, recién ahí sirve el valor devolviendo la vista con el slot y `revealed`. El
  enmascarado (`rutEnmascarado()` arriba) también es del sistema: la regla para un RUT, un correo
  o un diagnóstico es distinta.
- **Minimización.** Lo que no hace falta para atender no se pasa a la vista. Entre los dos modos
  de `pii`, el diferido es el único donde el dato de verdad no salió del servidor.
- **La impresión.** Una ficha con pestañas imprime solo el panel activo, y el mesón emite
  certificados. La página que compone la ficha agrega su regla: todos los paneles visibles y sin
  controles.

  ```css
  @media print {
      #ficha-pestanas [role="tablist"] { display: none !important; }
      #ficha-pestanas [role="tabpanel"] { display: block !important; }
  }
  ```

Armada entera, con datos ficticios, en `demo/persona.html`.

## Vitrina de desarrollo (`workbench/`)

`vendor/bin/testbench serve` → `http://127.0.0.1:8000/vitrina`. Una tarjeta por cada componente
de `resources/views/components/`, renderizada con el `<x-muni::…>` real y la hoja del paquete, en
los dos temas. Vive en `workbench/` y no la exponen los sistemas: el service provider no registra
rutas y `workbench/` no viaja en el paquete. Los datos de ejemplo están en
`workbench/ejemplos.php`, siempre ficticios.

## Demos (`demo/`)

Todas self-contained (Alpine inline, sin CDN). **Son maquetas, no la fuente de los tokens:** la
paleta está en `resources/css/muni-ui.css` y se copia de ahí. `tests/DemosConTokensDelPaqueteTest.php`
falla si una demo declara un `--muni-*` con un valor que la hoja del paquete no tiene.

**Componentes y sistema**
- `index.html` — panel de datos en ambos temas · `interactive.html` — modal/dropdown/tabs/toasts
- `templates.html` — galería de pantallas (landing, login, paneles por rol, error)
- `app.html` — **dashboard de patentes funcional completo** (command palette ⌘K, charts, tabla sortable, drawer, modal, toasts)
- `persona.html` — ficha de persona compuesta con los componentes reales; se **genera** con
  `php demo/persona.php` desde `demo/persona.blade.php`, no se edita a mano
- `solicitud.html` — seguimiento de una solicitud · `wizard.html` — solicitud por pasos
- `login-mfa.html` — ingreso con segundo factor · `settings.html` — «Mi cuenta»

**Landings e intranet por sistema** — cada una con identidad propia anclada a su mundo
- `landing-licencias.html` — Licencias de Conducir · `landing-discapacidad.html` — Oficina de Inclusión
- `landing-feria.html` — Ferias Libres
- `intranet-hub.html` — intranet municipal · `intranet-control-acceso.html` — Control de Acceso

## Panel ARCOP (Ley 21.719)

El ciclo de solicitudes ARCOP —recepción en el mesón y resolución fundada, con
sus plazos, su bitácora y su semáforo de vencimiento— viene como plugin de
panel, para que los sistemas del ecosistema lo hereden en vez de escribirlo cada
uno. La maquinaria legal vive en `muni-graneros/laravel-muni-shared`
(`Muni\Shared\Privacidad`); acá vive la pantalla.

```php
use Muni\Ui\Filament\Privacidad\PanelArcopPlugin;

->plugin(
    PanelArcopPlugin::make()
        // Obligatorio: quién es el titular y cómo se lo busca en ESTE sistema.
        ->titulares(BuscadorDePersonas::class)
        // Opcional: por defecto usa los nombres que genera Shield para el
        // recurso (view_any_solicitud, create_solicitud) más
        // resolver_solicitud_arcop.
        ->permisos(resolver: Permisos::RESOLVER_SOLICITUD_ARCOP)
        // Cómo se llama, en el mesón de este sistema, lo que el solicitante
        // presenta para acreditar su identidad.
        ->credencial(
            etiqueta: 'RUN leído de la cédula',
            ayuda: 'El de la cédula que el solicitante tiene en la mano, no el que dicta.',
            comoSeAcredita: 'La identidad se acredita con la cédula en el mesón.',
        )
        // Qué deja de hacer este sistema cuando un bloqueo queda vigente.
        ->alcanceDelCese(CeseDeTratamiento::queCesa())
)
```

Lo que el sistema adoptante tiene que poner de su lado:

1. `PRIVACIDAD_SISTEMA` en el `.env` (aísla sus solicitudes de las de los demás
   sistemas: `privacidad_solicitudes` es una tabla compartida por el ecosistema).
2. Un modelo que implemente `Muni\Shared\Privacidad\Contratos\TitularDeDatos`.
3. Un buscador que implemente `Muni\Ui\Filament\Privacidad\Contratos\BuscaTitulares`.
4. Un `VerificadorIdentidad` enlazado en el contenedor: el panel no decide cómo
   se acredita la identidad, solo le entrega el contexto del mostrador.
5. Sus permisos, si no usa los nombres por defecto.

### Lo que este plugin NO hace, y hay que leerlo antes de montarlo

**Heredar el panel da la superficie para recibir y resolver solicitudes; no hace
que el sistema cumpla.** El candado que hace cesar de verdad un tratamiento —qué
pantalla, qué CSV, qué correo y qué job dejan de tocar a esa persona— depende del
mapeo tratamiento→finalidad de cada sistema, y este paquete no lo conoce ni lo
puede ejecutar. En `discapacidad-graneros` ese candado es una clase propia
(`App\Privacidad\CeseDeTratamiento`) y se escribió después del panel: hasta
entonces el panel prometía un cese que no ocurría.

Un sistema que monte este plugin y no escriba su candado va a certificarle por
escrito a un vecino un cese que no ocurre. Por eso `alcanceDelCese()` arranca sin
declarar, y mientras no se declare el aviso al funcionario dice exactamente eso:
que este sistema no declaró qué deja de hacer y que hay que confirmarlo antes de
decirle al titular que su tratamiento cesó.

## Roadmap

- Capa 2: primitivas BlatUI (button/input/dialog…) re-teñidas con estos tokens (requiere Alpine).
- Tema Filament vía `renderHook(PanelsRenderHook::HEAD_END)` que lee los mismos `--muni-*`.
- Pipeline v0 → Blade para componentes complejos nuevos.
