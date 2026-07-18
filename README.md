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

## Demo

`demo/index.html` — showcase de los componentes en ambos temas (abrir en el navegador).

## Roadmap

- Capa 2: primitivas BlatUI (button/input/dialog…) re-teñidas con estos tokens (requiere Alpine).
- Tema Filament vía `renderHook(PanelsRenderHook::HEAD_END)` que lee los mismos `--muni-*`.
- Pipeline v0 → Blade para componentes complejos nuevos.
