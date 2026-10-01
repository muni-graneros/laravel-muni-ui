# Actualizar a 0.21.0

Versión anterior: **0.20.0**. Lee primero [`UPGRADE-0.20.0.md`](UPGRADE-0.20.0.md) si vienes de
antes: todo lo que dice sigue valiendo y no se repite acá.

**Al día de hoy ningún sistema del ecosistema está en 0.20.0.** Los siete que consumen el
paquete (discapacidad, licencias, seguridad, web, rrhh, feria y control-acceso) tienen
`^0.19` en el `composer.json` y `v0.19.0` instalada. Para ellos esta nota es la **segunda** de
dos: primero se hace lo de `UPGRADE-0.20.0.md` (la franja institucional del panel y el cambio de
`segmented` con formularios POST) y recién después lo de acá. Las dos exigen republicar el
tema, así que en la práctica se republica una sola vez, al final.

Esta versión no renombra ni quita ninguna prop, slot, clase ni evento. Lo que hay es un cambio
de **cómo se pinta el borde de los campos dentro del panel Filament** —que ahora llega a 3:1 y
que un sistema puede corregir con una utilidad—, cinco tokens nuevos o con valor nuevo, y tres
arreglos de accesibilidad alrededor del campo. Todo vive en la hoja del tema, que es una copia
publicada.

## Por qué es 0.21.0 y no 0.20.1

Porque **cambia el aspecto del panel sin que nadie toque una línea** —el borde de todos los
campos pasa de un beige de 1,4:1 a un marrón/petróleo de 3:1, y el token oscuro
`--muni-field-border` cambia de valor— y porque **cambia la cascada**: una utilidad de Tailwind
del host que antes perdía contra el borde del tema ahora le gana. Un sistema que haya
compensado el borde con un `style` en línea, con un `!important` propio o con capturas de
regresión visual va a notar la diferencia. En versionado 0.x eso es la segunda cifra: `^0.20`
no cruza a 0.21 a propósito, para que nadie lo reciba sin leer esto.

## Lo obligatorio

```bash
# 1. El constraint: en versionado 0.x, ^0.20 NO cruza a 0.21
composer require "muni-graneros/laravel-muni-ui:^0.21"

# 2. El tema del panel, que es una copia publicada
php artisan vendor:publish --tag=muni-ui-filament --force
php artisan view:clear

# 3. La copia publicada se versiona: commitearla
git add public/vendor/muni-ui/filament.css
```

**El paso 2 no es opcional en ningún sistema con `MuniPanel`.** Todo lo de esta versión está en
`public/vendor/muni-ui/filament.css`: sin republicar, el borde sigue en 1,4:1, el chevron del
select sigue en 2,66:1 en oscuro y el foco sigue quedando bajo la barra, y no hay ningún error
que lo delate. Para comprobar que llegó:

```bash
grep -c "@layer components" public/vendor/muni-ui/filament.css      # tiene que ser ≥ 1
grep -c "muni-panel-topbar-h" public/vendor/muni-ui/filament.css    # tiene que ser ≥ 1
grep -c "muni-select-chevron" public/vendor/muni-ui/filament.css    # tiene que ser ≥ 1
```

Y si el sistema sirve el CSS con hash o por CDN, invalida la caché: mismo nombre de archivo,
contenido nuevo. Con Octane, reiniciar el proceso.

## Lo que cambia de aspecto sin que hagas nada

| Qué | Antes | Ahora | Por qué |
|---|---|---|---|
| Borde de todo `.fi-input-wrp` (input, select, textarea, date, el buscador de la barra) | `--mg-borde`, el beige de los separadores: 1,36:1 sobre el campo, 1,25:1 sobre la página | `--muni-field-border`: claro 3,24–3,83:1, oscuro 3,31–4,81:1 | WCAG 1.4.11: en un campo el borde es lo único que dice dónde empieza |
| Borde del campo inválido (`.fi-invalid`) | el mismo beige | `--muni-field-border-error`: claro 6,62–7,82:1, oscuro 6,15–7,86:1 | el estado se veía solo en el texto del error |
| `--muni-field-border` en oscuro | `#467c89` | `#52899a` | Filament pinta el interior del campo `bg-white/5` sobre lo que haya debajo; sobre `--muni-surface-3` daba 2,76:1. Ahora 3,31:1 en el peor caso. `--muni-overlay-border` no cambia |
| Chevron del `<select>` (nativo y el del select con buscador) | SVG de Filament con trazo `#6b7280` fijo: 4,83:1 en claro, **2,66:1** en oscuro sobre `--muni-surface-3` | `--muni-select-chevron`, con el trazo del `--muni-muted` de cada rama: claro 5,48:1, oscuro 5,91–7,69:1, papel con el atenuado de impresión | 1.4.11 también vale para el gráfico que dice «esto se despliega» |
| Campo deshabilitado (`.fi-disabled`) | mismo borde que uno activo, cursor normal | borde atenuado `--muni-field-border-disabled` (2,28:1 frente al activo en claro, 2,46:1 en oscuro) y `cursor: not-allowed` en el envoltorio y en el control | con el borde a 3:1 se veía igual de «activo» que uno normal. El inválido deshabilitado conserva el borde de error |
| Shift+Tab hacia un campo que quedó arriba | el campo enfocado quedaba **debajo de la barra superior** (sticky) | el documento lleva `scroll-padding-top: calc(var(--muni-panel-topbar-h) + 1rem)` cuando hay `.fi-topbar`; en el banco, de 7 de 22 campos tapados a ninguno | WCAG 2.4.11, foco no oculto. Filament solo pone `scroll-margin-top` en sus `[data-field-wrapper]`: un wrapper suelto, un enlace o una acción de tabla seguían tapados |

## Lo que cambia en la cascada (lee esto si tu sistema tocó el borde de un campo)

El borde vive ahora en **`@layer components`** con el orden de capas de Tailwind 4 declarado
(`properties, theme, base, components, utilities`). Consecuencias:

- **Una utilidad del host le gana.** `border-danger-600`, `border-2`, `scroll-pt-*`… cualquier
  utilidad de Tailwind sobre `.fi-input-wrp` ahora se aplica. Antes la regla del tema iba sin
  capa y le ganaba a todas.
- **El foco no se mueve**: sigue sin capa y con `!important`, para que una utilidad suelta no
  pueda apagar el indicador de foco (2.4.7).
- Si el sistema **redefine `--muni-field-border`** por su cuenta, su valor es el que se pinta
  en todos los campos del panel. Que llegue a 3:1 sobre `--muni-surface-3` en las dos hojas pasa
  a ser responsabilidad del sistema.
- Si un sistema compensó el beige con un `style` en línea, ya puede quitarlo (abajo el caso
  concreto).

## El paso propio de seguridad-graneros

`resources/views/filament/pages/reporte-operacion.blade.php` (commit `f723b119`) puso el borde
de los dos campos de fecha en `style` porque era la única forma de ganarle al beige:

```blade
<x-filament::input.wrapper
    :valid="! $invalido"
    class="mt-1"
    :style="'border-color: var('.($invalido ? '--muni-field-border-error' : '--muni-field-border').')'"
>
```

Después de republicar el tema, **quitar el `:style`** y el párrafo del comentario que lo
justifica («va en `style` porque … pinta `.fi-input-wrp` con un beige de 1,4:1 fuera de toda
capa»). El wrapper ya recibe `--muni-field-border` del tema, y `:valid="! $invalido"` le pone
`.fi-invalid`, que es lo que ahora pinta `--muni-field-border-error`. Si alguna vez hiciera
falta un borde distinto al del tema, el camino es una utilidad en `class` (por ejemplo
`border-danger-600`), que desde esta versión sí gana. Verificarlo como se verificó el `style`:
por píxel, en claro y en oscuro, con y sin error (los valores de referencia están en la tabla
de arriba). `C13ReporteOperacionFechasTest` tiene que seguir en verde.

## Tokens nuevos o con valor nuevo

Los cinco están documentados en el [README](../README.md#componentes), tabla de tokens.

| Token | Claro | Oscuro | Para qué |
|---|---|---|---|
| `--muni-field-border` | `#90815b` (igual) | `#52899a` (antes `#467c89`) | borde del campo |
| `--muni-field-border-error` | `var(--muni-danger-fg)` (igual) | `var(--muni-danger-fg)` (igual) | borde del campo inválido; ahora sí se usa en el panel |
| `--muni-field-border-disabled` | `var(--muni-border-2)` | `var(--muni-border-2)` | **nuevo**, borde del campo deshabilitado |
| `--muni-select-chevron` | SVG, trazo `#5a6d6b` | SVG, trazo `#a3b1c4` (papel: `#3a3f4b`) | **nuevo**, chevron del select |
| `--muni-panel-topbar-h` | `4rem` | `4rem` | **nuevo**, alto de la barra de Filament, para el `scroll-padding` del documento |

`--muni-panel-topbar-h` no es `--muni-topbar-h` (56px, la de `<x-muni::topbar>`). Vale lo que
mide la barra de Filament: `min-h-16` hasta 5.7.6 y `--topbar-height: 4rem` desde 5.7.8 (ahí
Filament la declara en `.fi-body`, donde `:root` no puede leerla, por eso el token sigue siendo
un valor fijo). El candado del paquete lo compara con el vendor en cada corrida.

## Cómo verificar en tu sistema

- [ ] `diff public/vendor/muni-ui/filament.css vendor/muni-graneros/laravel-muni-ui/resources/css/muni-ui-filament.css` no imprime nada.
- [ ] Un formulario del panel, en claro y en oscuro: el borde de un campo vacío se distingue del fondo a simple vista.
- [ ] Un campo con error de validación: el borde se ve rojo, no solo el mensaje.
- [ ] Un campo deshabilitado: borde más tenue que el de al lado y cursor `not-allowed` también sobre el control.
- [ ] Un `<select>` en oscuro: el chevron se ve.
- [ ] Un formulario largo: bajar con Tab hasta abajo y volver con Shift+Tab; ningún campo enfocado queda debajo de la barra superior.
- [ ] Si tu sistema tenía capturas de regresión visual del panel, regenerarlas: el borde de todos los campos cambió.
