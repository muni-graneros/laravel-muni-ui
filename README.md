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
| `<x-muni::segmented>` | `name`, `options` (array), `value` — radios reales sin JS; o slot |
| `<x-muni::filter-bar>` | `action`, `method`; slots `submitLabel`, `actions` |
| `<x-muni::field>` | `label`; el control (input/select) va en el slot |
| `<x-muni::data-table>` | `columns` (array), `empty`; el slot son los `<tr data-muni-row>` |
| `<x-muni::pagination>` | `current`, `total`, `url` (closure fn(\$p)), `info` |

### Interactivos (requieren Alpine 3)

| Componente | Props principales |
|-----------|-------------------|
| `<x-muni::dropdown>` | `align` (start/end), `width`; slot `trigger` + ítems `<x-muni::dropdown-item>` |
| `<x-muni::dropdown-item>` | `href`, `icon`, `tone` (default/danger) |
| `<x-muni::modal>` | `title`, `maxWidth`; slots `trigger`, `footer` |
| `<x-muni::tabs>` | `tabs` (array de labels), `default`; paneles `<x-muni::tab-panel :index>` |
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
| `<x-muni::sidebar>` | `width`; slot con `<x-muni::nav-section>` + `<x-muni::nav-item>` (colapsa en móvil) |
| `<x-muni::nav-item>` | `href`, `icon`, `active`, `badge` |
| `<x-muni::nav-section>` | `title` |
| `<x-muni::breadcrumb>` | `items` (array de `['label','url'?]`) |
| `<x-muni::tooltip>` | `text`, `placement` (top/bottom/left/right) (Alpine) |
| `<x-muni::avatar>` | `name` (iniciales), `src`, `size`, `tone` |
| `<x-muni::progress>` | `value`, `max`, `tone`, `label`, `showValue` |
| `<x-muni::skeleton>` | `width`, `height`, `rounded` (shimmer) |
| `<x-muni::empty-state>` | `title`, `description`, `icon`; slot `actions` |
| `<x-muni::command-palette>` | `items`, `placeholder`, `hotkey` — ⌘K/Ctrl+K (Alpine) |
| `<x-muni::auth-shell>` | `theme`, `title`, `system`, `subtitle`, `logo`; slots `aside`, `head` — layout login/registro |
| `<x-muni::dashboard-shell>` | `theme`, `system`, `subtitle`, `status`, `user`; slots `sidebar`, `topbar` — layout de panel |
| `<x-muni::error-page>` | `code`, `title`, `message`, `home`, `theme` — 403/404/500/503 |

Todos respetan `prefers-reduced-motion`, tienen estados `:focus-visible` con anillo de foco
accesible (`--muni-ring`), y micro-interacciones de hover/active con transiciones tokenizadas.
Los interactivos usan **Alpine 3 core** (sin plugins): en feria/discapacidad/licencias ya viene
con Filament; en apps sin Filament, `npm i alpinejs` y `Alpine.start()`. El CSS del paquete trae
la regla `[x-cloak]` para evitar el flash inicial.

**Firma del sistema:** la morosidad no es un badge redondo suelto — una fila
`<tr data-muni-row class="muni-row--danger">` pinta una franja de estado en el borde
izquierdo (banda de libro mayor), y los RUT/cifras usan `.muni-num` (mono tabular).

## Demos (`demo/`)

Todas self-contained (Alpine inline, sin CDN).

**Componentes y sistema**
- `index.html` — panel de datos en ambos temas · `interactive.html` — modal/dropdown/tabs/toasts
- `showcase.html` — sala de control cívica con consola viva · `templates.html` — galería de pantallas (landing, login, paneles por rol, error)
- `app.html` — **dashboard de patentes funcional completo** (command palette ⌘K, charts, tabla sortable, drawer, modal, toasts)

**Landings novedosas por sistema** — cada una con identidad propia anclada a su mundo
- `landing-hub.html` — hub del ecosistema (dark mode universal en vivo)
- `landing-licencias.html` — "la ruta" (carretera en perspectiva, señalética vial)
- `landing-discapacidad.html` — "accesibilidad como belleza" (controles reales de a11y)
- `landing-control-acceso.html` — "terminal de vigilancia" (feed biométrico en vivo)
- `landing-patentes.html` — "el libro de rentas" (sello municipal, cifras que respiran)

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
