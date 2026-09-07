# El contrato de diseño

Esto es lo que un componente de `laravel-muni-ui` promete y lo que no puede hacer. No es una guía
de estilo: es el contrato que hace posible que nueve sistemas municipales compartan aspecto sin
coordinarse entre ellos, y que un adoptante cambie su identidad sin tocar un solo componente.

Las convenciones de *cómo se escribe* Blade, Livewire y Filament viven en la skill
`blade-livewire-design`, que es la fuente única. Acá va solo lo que es propio de este paquete.

## 1. Los componentes no tienen colores. Tienen tokens.

Un componente se estila con `style` en línea y `var(--muni-*)`, más un bloque `<style>` dentro de
`@once` cuando necesita pseudo-clases. Nunca escribe un color literal.

La razón no es purismo. Es que el paquete se entrega a municipios distintos, y la identidad de cada
uno se cambia redefiniendo tokens en el host. Un `#0f766e` escrito dentro de un componente es un
color que ningún adoptante puede cambiar sin editar el paquete.

**La única excepción son los invariantes institucionales**, y están enumerados: los nueve
`--muni-gob-*`. Son la franja de la bandera y no cambian con el tema ni con el municipio.

## 2. El catálogo de tokens

Son 49. Nueve de identidad y cuarenta de tema.

### Identidad — iguales en claro y en oscuro, a propósito

`--muni-gob-lima` `#adcd60` · `--muni-gob-verde-dark` `#7fa33f` · `--muni-gob-petroleo` `#355a63` ·
`--muni-gob-petroleo-dark` `#00404c` · `--muni-gob-oro` `#eab02c` · `--muni-gob-naranja` `#c76421` ·
`--muni-gob-celeste` `#7ccbe1` · `--muni-gob-carmin` `#ca3048` · `--muni-gob-gris` `#9c9b9b`

Son para franjas, bandas y fondos. **No sirven como color de texto**: medidos sobre el papel del
panel dan lima 1,62 · oro 1,76 · celeste 1,65, muy por debajo del 4,5:1. Si necesitas un lima
legible como texto, es un token de estado, no un token de identidad.

### Tema — cada uno tiene valor claro y valor oscuro

| Familia | Tokens |
|---|---|
| Superficie | `--muni-bg` `--muni-surface` `--muni-surface-2` `--muni-surface-3` `--muni-panel` |
| Texto | `--muni-text` `--muni-muted` `--muni-hint` |
| Borde | `--muni-border` `--muni-border-2` |
| Acento | `--muni-accent` `--muni-accent-strong` `--muni-accent-soft` `--muni-on-accent` |
| Estado | `--muni-{ok,warn,danger,info}-{fg,bg,border}` |
| Foco | `--muni-focus` `--muni-ring` |
| Sombra | `--muni-shadow` `--muni-shadow-md` `--muni-shadow-lg` `--muni-glow` |
| Radio | `--muni-radius-sm` `--muni-radius` `--muni-radius-lg` |
| Movimiento | `--muni-dur` `--muni-ease` |
| Tipografía | `--muni-font-sans` `--muni-font-mono` |
| Layout | `--muni-topbar-h` |

Los radios, el easing y la duración **no cambian con el tema**: heredan de `:root`. Es correcto y
deliberado.

**Los tokens no se renombran ni se borran.** Nueve sistemas los consumen. Agregar es libre; quitar
o renombrar rompe a alguien en silencio, porque un `var()` que no existe no da error: se descarta
la declaración completa y el elemento queda sin color.

## 3. La jerarquía de tema

En orden de aplicación, tal como está escrita en `resources/css/muni-ui.css`:

1. **`:root`** define el claro. Es el punto de partida.
2. **`@media (prefers-color-scheme: dark)`** aplica el oscuro al elemento raíz, salvo que la raíz
   lleve un activador explícito de claro.
3. **`[data-muni-theme="dark"]`, `[data-theme="dark"]`, `.dark`** activan el oscuro en cualquier
   elemento. Los tres existen para convivir con todo el ecosistema a la vez: Filament pone `.dark`,
   las PWA usan `data-theme`, y el paquete usa el suyo. No hay que elegir uno.
4. **`[data-muni-theme="light"]`** congela el claro, y es el único que lo congela de verdad.
   `.light` y `[data-theme="light"]` solo excluyen la regla del sistema operativo; no traen valores.

**Trampa conocida:** sobre `:root`, la regla del sistema operativo tiene más especificidad que los
activadores explícitos. Hoy no se nota porque los valores son idénticos byte a byte, pero si algún
día divergen, en la raíz ganaría el sistema operativo y no el activador. Si tocas esos bloques,
mantenlos idénticos o arregla la especificidad primero.

**Dentro de un panel Filament no pongas `data-muni-theme`.** El panel ya pone `.dark` en el `<html>`
y el tema del paquete define ahí los `--muni-*` a partir de su propia paleta.

## 4. La regla del `.dark`, que es la que más se incumple

> Toda regla que fije un color para el modo claro necesita su contraparte para el oscuro.

No es una recomendación de prolijidad. En `muni-ui-filament.css` las reglas llevan `!important` y
ganan sobre las de Filament, que vienen con `:where(.dark, .dark *)` y especificidad cero a
propósito. Sin la contraparte, **la variante clara se aplica también en oscuro** y el texto queda
sobre un fondo para el que no fue pensado.

Una regla cumple el contrato si su color sale de un token que tiene ambas ramas. Solo hace falta
escribir una contraparte explícita cuando el valor es un literal.

Para superficies oscuras, reutiliza lo que ya existe en vez de inventar tonos: `--mg-sb-txt` para
texto principal, `--mg-sb-mut` para atenuado, `--mg-lima-br` para acento.

Hay una prueba que vigila esto (`tests/TemaFilamentTest.php`) y la reja de accesibilidad lo mide en
un navegador real. Las dos son más confiables que mirar una captura: los dos defectos que costaron
caro en este paquete —la etiqueta de KPI en 1,22:1 y la pestaña activa en 1,52:1— pasaron todas las
revisiones visuales que se les hicieron, porque «tenue» y «ausente» se parecen mucho en una imagen.

## 5. El indicador de foco es un `outline`, no una sombra

`--muni-ring` es una `box-shadow` y **se pierde dentro de Filament**: medido en el panel de
licencias, el foco de un input se computaba `oklab(0 0 0 / 0) 0px 0px 0px 0px`, porque la cadena de
sombras de Tailwind y Filament la sobrescribe.

El indicador real es:

```css
outline: 3px solid var(--muni-focus, var(--muni-accent, #767676));
outline-offset: 2px;
```

El `#767676` es el tercer respaldo y solo aparece si la hoja de tokens no se cargó. Es el gris
canónico de doble tema: 4,54:1 sobre blanco y 4,62:1 sobre negro, por encima del 3:1 que exige
1.4.11 en cualquiera de los dos modos.

`--muni-ring` sigue valiendo como halo decorativo encima del outline. No como único indicador.

## 6. El movimiento se apaga solo

Toda transición usa `var(--muni-dur)` y `var(--muni-ease)`. `--muni-dur` pasa a `0 ms` bajo
`prefers-reduced-motion: reduce`, así que un componente que respeta el token respeta la preferencia
sin escribir una sola media query.

Un componente que anima con una duración fija —`transition: width .5s`— **ignora la preferencia**.
Eso es un defecto, no un detalle: hay cuatro componentes de gráficos que hoy lo hacen.

Corolario del ecosistema: los números de un KPI cuentan desde cero, la tarjeta no. Lo que empieza
invisible no cuenta como pintado y el LCP se va con la animación; medido, el desvanecido costaba
536 ms contra 240 ms del desplazamiento.

## 7. Por qué los componentes repiten su CSS, y por qué no hay que «arreglarlo»

Varios componentes declaran la misma utilidad en su propio bloque `@once`: la de ocultar algo
visualmente sin sacarlo del árbol de accesibilidad. Parece duplicación que pide limpieza. **No lo
es, y moverla a `muni-ui.css` rompería el paquete dentro de los paneles.**

El motivo es el modelo de distribución. `MuniPanel` inyecta en un panel Filament **solo**
`vendor/muni-ui/filament.css`; `muni-ui.css` no se carga ahí, lo compila el Vite del sistema
anfitrión para sus vistas públicas. Un componente que dependiera de una clase declarada únicamente
en `muni-ui.css` se vería sin esa clase dentro del panel, sin un solo error en consola.

Ese error ya ocurrió, y salió caro: los componentes leían `--muni-*`, esos tokens solo existían en
`muni-ui.css`, y dentro del panel renderizaban con el tamaño correcto y sin color. La barra de un
gráfico salía con `background:none` porque un degradado con una variable vacía es inválido y se
descarta. Nadie lo vio hasta que alguien escribió a mano un gráfico de barras que ya existía.

La regla, entonces: **lo que un componente necesita para verse bien viaja con el componente**, en su
`@once`. Los tokens sí van al CSS, porque el tema del panel los puentea a propósito.

## 8. La firma del sistema

Tres cosas hacen que una pantalla se reconozca como de este ecosistema y no como un panel genérico:

- **La franja de estado en el borde de la fila.** Una fila con problema es
  `<tr data-muni-row class="muni-row--danger">`, que pinta una banda vertical a la izquierda, como
  un libro mayor. No es una insignia redonda suelta.
- **Las cifras y los RUT en mono tabular**, con `.muni-num`. Una columna de montos que no alinea
  las unidades es una columna que no se puede leer de un vistazo.
- **El cinturón institucional** de siete colores en las franjas, no en el texto.

## 9. Lo que no se hace

- **No escribir un color literal** en un componente, salvo `transparent`, `currentColor` e
  `inherit`. Si necesitas un tono nuevo, se agrega como token en el paquete, no en el host.
- **No usar un nombre de variable que no existe.** Como se escribe `var(--x, respaldo)`, un nombre
  mal escrito funciona de casualidad en claro y da 1,1:1 en oscuro. Comprueba contra la lista real,
  no de memoria.
- **No usar CSS moderno sin `@supports`.** `:has()`, `color-mix()`, anchor positioning y las
  animaciones por scroll van como mejora progresiva. Hoy hay un componente cuyo único indicador de
  foco depende de `:has()` sin respaldo, y es el que está documentado como «radios reales sin JS».
- **No depender de un plugin de Alpine que no viaje en el bundle de Livewire.** El de Focus
  (`x-trap`) sí viaja. Cualquier otro es una dependencia nueva para nueve sistemas.
- **No usar directivas exclusivas de un major de Livewire.** Los componentes se instalan tanto en
  Livewire 4 como en `personas-graneros`, que sigue en Livewire 3. Nada de `@island`, `wire:show`,
  `wire:sort` ni `#[Transition]` dentro del paquete.
- **No generar ids con `uniqid()`.** Cambian en cada render, rompen el `for` de la etiqueta y
  ensucian el diffing de Livewire. El id sale del `name`, y el consumidor puede pasar el suyo.
- **No comunicar un estado solo con color.** Ni en una insignia, ni en un gráfico, ni en el ítem
  activo de un menú. Siempre hay texto, forma o `aria-current` acompañando.
- **No renombrar props ni tokens.** Todo cambio es aditivo mientras la versión sea 0.x y haya nueve
  sistemas consumiendo.

## 10. Antes de dar por terminado un componente

- La reja pasa: `npm run a11y`.
- Hay una prueba que falla si el defecto vuelve.
- Se ve en las dos combinaciones de tema y en ancho de escritorio y de teléfono.
- Se recorre entero con el teclado, y el foco se ve en cada parada.
- Los estados existen: carga, vacío, error, con datos, y con datos extremos.
- Tiene fila en el README y sección en una demo.
