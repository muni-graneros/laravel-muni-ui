# Actualizar un sistema a esta versión de laravel-muni-ui

Esta versión no renombra ni elimina ninguna prop, slot, clase CSS ni evento: el
código Blade que ya existe en cada sistema sigue funcionando. Lo que cambia es cómo
se ven algunos detalles y tres comportamientos que antes eran errores. Esta guía
dice qué revisar.

## 1. Pasos

```bash
composer update muni-graneros/laravel-muni-ui
php artisan vendor:publish --tag=muni-ui-filament --force   # el tema del panel; sin --force no se pisa
npm run build                                                # si el sistema compila su CSS con Vite
```

Si el sistema corre con Octane, reinícialo: el manifiesto de Vite queda en memoria.

### Si publicaste `muni-ui.css` (`--tag=muni-ui-css`)

Tu copia no se actualiza sola. Los componentes nuevos usan dos tokens que tu copia no
tiene; tienen un valor de respaldo, así que nada se rompe, pero no vas a tener las
mejoras de contraste hasta que los agregues. Compara tu copia con
`vendor/muni-graneros/laravel-muni-ui/resources/css/muni-ui.css` y trae, en cada bloque
de tema (claro, oscuro por preferencia del sistema, oscuro explícito y override claro):

| Token | Claro | Oscuro | Para qué |
|---|---|---|---|
| `--muni-border-strong` | `#7d88a0` | `#566891` | Borde de input, select, textarea, casillas: 3:1 contra el fondo |
| `--muni-ring` | `0 0 0 2px var(--muni-surface), 0 0 0 4px var(--muni-accent)` | igual | Anillo de foco visible (el anterior quedaba en 1,5:1) |
| `--muni-ring-inset` | `inset 0 0 0 2px var(--muni-accent)` | igual | Foco por dentro (acordeón) |
| `--muni-hint` | `#656c79` | `#788ba9` | Texto tenue legible (4,5:1) |
| `--muni-warn-fg` / `--muni-warn-border` | `#9c610d` | sin cambio | «Por vencer» legible en claro |
| `--muni-muted` | sin cambio | `#7e8fac` | Texto secundario en oscuro |
| `--muni-danger-fg` | sin cambio | `#ef4949` | Rojo legible sobre superficies oscuras |

Si tu sistema cambió estos tokens a propósito (identidad propia), mantén tus valores y
revisa el contraste en la sección «Fundamentos» del catálogo.

## 2. Lo que se ve distinto

- **Foco con teclado:** un anillo sólido del color de acento en vez de un halo tenue.
- **Bordes de campos:** input, select, otp-input y switch tienen un borde más marcado.
- **Texto de advertencia** en tema claro, un tono más oscuro.
- **Botones deshabilitados** (también con `wire:loading.attr="disabled"`) se ven atenuados.
  Antes eran iguales a los activos.
- **La dona** (`chart-donut`) ya no se recorta en los bordes.
- **stepper** en móvil muestra solo la etiqueta del paso actual.
- **Tonos:** todos los componentes comparten el mismo mapa (`Muni\Ui\Tono`). Un tono que
  antes un componente no conocía y pintaba con su color por defecto ahora se pinta como
  corresponde: `tone="neutral"` en chart-bar, progress, ring y timeline sale en color de
  texto (antes, acento); `tone="accent"` en kpi y stat sale en acento (antes, texto).
- **Dentro de paneles Filament**, badge, alert, el acento suave y los `gob-*` ahora tienen
  sus colores: el tema del panel no definía esos tokens y se veían sin fondo. Hay que
  volver a publicar el tema (`--tag=muni-ui-filament --force`).

## 3. Comportamiento que cambia

Estos tres cambios corrigen errores, pero un sistema podría haberse apoyado en ellos:

1. **`segmented` ya no envía formularios POST.** Antes enviaba cualquier formulario que
   lo contuviera (con Livewire daba 419). Ahora solo envía formularios GET (filtros).
   Si un sistema necesita enviar un POST al cambiar, usar `autosubmit="siempre"`. Con
   `wire:model` o `x-model` no envía nada: el valor se enlaza en vivo.
2. **`dropdown-item` solo cierra su propio menú.** Antes ejecutaba `open = false` sobre
   el `x-data` más cercano, lo que cerraba el modal o drawer que lo contenía.
3. **`dashboard-shell` pone `x-data` en el `<body>`.** Las directivas de Alpine sueltas
   en el contenido, que antes no hacían nada, ahora se ejecutan.

## 4. Lo nuevo que conviene usar

- **`wire:model` funciona en todos los controles.** En `segmented`, `file-dropzone`,
  `calendar`, `otp-input` y `rating` antes caía en un `<div>` y no enlazaba nada. Si
  algún sistema lo resolvió con un input oculto o JS propio, ya puede quitarlo.
- **Componentes nuevos:** `textarea`, `checkbox`, `radio-group`, `description-list`,
  `spinner`, `accordion-item`.
- **`data-table` ordena en el servidor** con `sortUrl` o `wireSort`; `sortable-table`
  queda para listas cortas ya cargadas.
- **`calendar`** acepta `value` (fecha inicial) y un Carbon en `min`.
- **`breadcrumb` y `pagination`** aceptan `label` cuando hay dos en la misma página.

## 5. Cómo verificar en tu sistema

- [ ] Recorrer un formulario solo con teclado: el foco se ve en cada campo y botón.
- [ ] Un listado con filtros: cambiar un `segmented` sigue filtrando (formulario GET).
- [ ] Si hay `segmented` dentro de un formulario POST que debía enviarse solo: agregar `autosubmit="siempre"`.
- [ ] Un modal o drawer: abre, Tab no se escapa, Escape cierra y el foco vuelve al botón.
- [ ] En el panel (`dashboard-shell`) a ancho de teléfono: el menú abre la barra lateral.
- [ ] Tema oscuro: los textos tenues y las advertencias se leen.

La referencia de cada componente, con ejemplos y props, es `demo/catalogo.html`.
