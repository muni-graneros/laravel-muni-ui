# Actualizar a 0.20.0

Versión anterior: **0.19.0**. Lee primero [`UPGRADE-0.19.0.md`](UPGRADE-0.19.0.md) si vienes de
antes: todo lo que dice sigue valiendo y no se repite acá.

Esta versión no renombra ni quita ninguna prop, slot, clase ni evento. Lo que sí hay: una franja
nueva en el panel (que exige republicar el tema) y un comportamiento de `segmented` que antes era
un error y alguien podría estar usando.

## Lo obligatorio

```bash
# 1. El constraint: en versionado 0.x, ^0.19 NO cruza a 0.20
composer require "muni-graneros/laravel-muni-ui:^0.20"

# 2. El tema del panel, que es una copia publicada
php artisan vendor:publish --tag=muni-ui-filament --force
php artisan view:clear
```

**El paso 2 no es opcional en ningún sistema con `MuniPanel`.** La franja institucional de la
barra del panel vive en esa copia: sin republicar no aparece, y no da ningún error. Para
comprobar que llegó:

```bash
grep -c "fi-topbar::before" public/vendor/muni-ui/filament.css   # tiene que ser ≥ 1
```

## Lo que cambia de aspecto sin que hagas nada

| Qué | Antes | Ahora |
|---|---|---|
| Borde superior de `topbar`, `app-shell`, `dashboard-shell`, `auth-shell`, `error-page` y `hoja` | nada | la franja de siete colores del municipio, en claro y en oscuro |
| Barra del panel Filament | nada (solo el login la tenía) | la misma franja (tras republicar el tema) |
| `gob-stripe` al imprimir | desaparecía (era un degradado de fondo) | se imprime (son bordes) |
| Estrellas vacías de `rating` | gris de borde, 1,6:1 | `--muni-hint`, sobre 3:1 |

Si una página ya tiene `<x-muni::gob-bar>` (que trae su franja), la del `topbar` o del
`dashboard-shell` se oculta sola para no pintar dos. Para quitarla a mano en un `topbar`:
`:franja="false"`.

## Comportamiento que cambia

1. **`segmented` ya no envía formularios POST al tocar una píldora.** Antes enviaba cualquier
   formulario que lo contuviera; en un formulario de Livewire eso terminaba en un 419 o en un
   guardado que nadie pidió. Ahora solo envía formularios **GET** (los filtros), y nunca si el
   formulario tiene `wire:submit` o `@submit`. Si un sistema necesitaba que un POST se enviara
   solo, agrega `autosubmit="siempre"`.
2. **`accordion` con `default` sobre `items` busca por CLAVE, no por posición.** Para un arreglo
   de lista (claves 0, 1, 2…) es lo mismo. Solo cambia si `items` viene de un `->filter()` con
   huecos en las claves: antes abría el panel en esa posición, ahora el de esa clave.

## Lo nuevo que conviene usar

- **`wire:model` funciona en todos los controles.** En `segmented`, `file-dropzone`, `calendar`,
  `otp-input` y `rating` antes caía en un `<div>` y no enlazaba nada, sin error. Si algún sistema
  lo resolvió con un input oculto o JS propio, ya puede quitarlo. Está probado de punta a punta
  con Livewire real, en Livewire 4 y en Livewire 3 (personas-graneros).
- **`data-table` ordena en el servidor**: `sortUrl` (enlaces) o `wireSort` (método Livewire), con
  `aria-sort` en el encabezado.
- **`<x-muni::radio-group>`** y **`<x-muni::accordion-item>`** (paneles en el slot en vez de `items`).
- **`tabs` y `accordion` aceptan claves de texto** (`['datos' => 'Datos', …]`); antes Alpine se caía
  entero con ellas.

## Para desarrollar el paquete

`demo/catalogo.html` es el catálogo de los 90 componentes: ejemplo real, código, props y slots,
claro y oscuro, más seis recetas de pantalla completa. Se regenera con `npm run construir` en
`demo/catalogo/`, y CI falla si quedó atrás. Toda prop del paquete lleva ahora su descripción en
`@props`, y `CatalogoTest` lo exige para las que vengan.

## Cómo verificar en tu sistema

- [ ] Una pantalla con `topbar` o `dashboard-shell`: la franja de colores se ve arriba, en claro y en oscuro.
- [ ] El panel Filament (tras republicar el tema): la franja se ve sobre la barra superior.
- [ ] Un listado con filtros: cambiar un `segmented` sigue filtrando (formulario GET).
- [ ] Si hay `segmented` dentro de un formulario POST que debía enviarse solo: agregar `autosubmit="siempre"`.
- [ ] Imprimir una `hoja`: la franja sale en el papel.
