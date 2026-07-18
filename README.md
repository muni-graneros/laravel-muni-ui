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
| `<x-muni::kpi>` | `value`, `label`, `tone` (neutral/ok/warn/danger/info), `hint` |
| `<x-muni::badge>` | `tone`, `dot` |
| `<x-muni::data-table>` | `columns` (array), `empty`; el slot son los `<tr data-muni-row>` |
| `<x-muni::filter-bar>` | `action`, `method`; slots `submitLabel`, `actions` |
| `<x-muni::field>` | `label`; el control (input/select) va en el slot |

**Firma del sistema:** la morosidad no es un badge redondo suelto — una fila
`<tr data-muni-row class="muni-row--danger">` pinta una franja de estado en el borde
izquierdo (banda de libro mayor), y los RUT/cifras usan `.muni-num` (mono tabular).

## Demo

`demo/index.html` — showcase de los componentes en ambos temas (abrir en el navegador).

## Roadmap

- Capa 2: primitivas BlatUI (button/input/dialog…) re-teñidas con estos tokens (requiere Alpine).
- Tema Filament vía `renderHook(PanelsRenderHook::HEAD_END)` que lee los mismos `--muni-*`.
- Pipeline v0 → Blade para componentes complejos nuevos.
