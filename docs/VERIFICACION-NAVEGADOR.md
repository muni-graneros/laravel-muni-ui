# Verificación en navegador — los cinco componentes nuevos o reparados

Fecha de la sesión: **2026-09-10**. Rama: `develop`.
Alcance: `<x-muni::hoja>`, `<x-muni::diff-campos>`, `<x-muni::timeline>`,
`<x-muni::combobox>` y `<x-muni::tooltip>`.

Este documento existe porque los cinco entraron con pruebas PHP y medición de
contraste, y **ninguno se había abierto en un navegador**. Acá está lo que se
abrió, con qué motor, qué se midió y qué salió mal. Nada de «se ve bien»: cada
afirmación lleva el número que la respalda.

**No se corrigió una sola línea de código.** El encargo era diagnosticar.

> **Ampliación del 2026-09-10 (misma fecha, segunda tanda).** Las §§1-6 son el
> informe original, en Chromium 151 y Firefox 153 y con `muni-ui.css` cargado.
> Las dos superficies que ahí quedaron sin medir —**WebKit/Safari** y el
> **contexto del panel Filament**, donde solo se carga `muni-ui-filament.css`—
> están en las **§7** y **§8**, con ocho defectos nuevos (**D9** a **D16**).
> Ojo al leer: `hoja.blade.php` se reescribió entre las dos tandas —el contador
> pasó a la caja de margen de `@page` y el pie a un `table-footer-group`—, así
> que lo que la §2 describe como D1 y D4 **ya no es el estado actual**; la §8.2
> trae la medición de cómo está hoy.

---

## 1. Motores, versiones y banco de pruebas

| Motor | Versión | Cómo se lanzó |
|---|---|---|
| Chromium | **151.0.7922.34** | Playwright (`.venv-a11y`), headless |
| Firefox | **153.0** | Playwright (`.venv-a11y`), headless |

Playwright: paquete Python en `.venv-a11y`, navegadores en
`~/.cache/ms-playwright/` (`chromium-1234`, `firefox-1538`).

Se usaron **dos** sujetos de prueba:

1. **La vitrina del repo** — `vendor/bin/pest --filter=GeneraVitrina` →
   `build/vitrina/claro.html` y `build/vitrina/oscuro.html`. Es el HTML que
   emite el paquete de verdad, y es de donde salen las 20 capturas de pantalla
   (5 componentes × escritorio/móvil × claro/oscuro).

2. **Un banco propio, fuera del repo** — `$TMPDIR/muniui/banco-{claro,oscuro}.html`,
   generado por `$TMPDIR/muniui/render.php` (arranca Blade con
   `Orchestra\Testbench\Foundation\Application` y sólo `MuniUiServiceProvider`).
   Hizo falta porque **la vitrina no puede cubrir dos cosas**:

   - La vitrina renderiza la `hoja` con un párrafo. Para ver paginación real,
     repetición de `<thead>` y el contador de páginas hace falta un documento de
     varias hojas: el banco mete una `data-table` de **60 filas**, con una fila
     `.muni-row--danger` cada nueve, más el cromo de pantalla (topbar pegajosa,
     barra lateral, toast, botón «Imprimir») y un tooltip abierto a propósito.
   - **La vitrina no tiene un solo `<script>`** (verificado: `grep -c script
     build/vitrina/claro.html` → `0`). Sin Alpine, el combobox no abre, el
     tooltip no aparece y el botón de limpiar queda bajo `x-cloak`. El banco
     carga Alpine **3.15.12** local, así que todo lo de teclado se midió ahí.

Las capturas y los PDF quedaron en **`build/capturas/`** (58 archivos, 3,7 MB).
`build/` ya está ignorado por git — `.gitignore:15` dice `/build/`, confirmado
con `git check-ignore -v build/capturas/x.png`. **No hay que agregar nada.**

---

## 2. `hoja` — vista previa de impresión real

Método: `page.emulate_media(media="print")` en los dos motores para leer estilos
computados; `page.pdf()` en Chromium y **`Mozilla Save to PDF`** en Firefox
(preferencias `print.always_print_silent` + `print_to_file`, invocado con
`window.print()`) para ver la paginación de verdad. El PDF se analizó con
`pdftotext -layout` página por página y se rasterizó con `pdftoppm`.

Los gráficos de fondo van **desactivados** (`print_background=False` en Chromium,
`print_bgcolor`/`print_bgimages` en `false` en Firefox), que es el caso que
importa: es lo que hace una impresora del municipio.

### 2.1 Lo que sí funciona

Medido con `getComputedStyle` bajo `media="print"`, **idéntico en los dos motores
y en los dos temas**:

| Comprobación | Pantalla | Impresión | Veredicto |
|---|---|---|---|
| `.muni-hoja__acciones` (botón Imprimir) | `flex` | `none` | ✔ |
| `.muni-topbar` | `flex` | `none` | ✔ |
| `.muni-sb` (barra lateral) | `block` | `none` | ✔ |
| `.muni-toast` | `block` | `none` | ✔ |
| `.muni-skip-link` | `block` | `none` | ✔ |
| `.muni-tt__bubble` **con el tooltip abierto** | `block` / visible | `none` | ✔ |
| `.muni-dt thead` | `table-header-group` | `table-header-group` | ✔ |
| Fondo de `body` | `#f5f6f8` / `#0b0f14` | `#ffffff` | ✔ |
| Color de texto | `#1a1d24` / `#e2e8f4` | `#000000` | ✔ |
| Marco de `.muni-hoja` | `1px` + fondo | `0px`, fondo transparente | ✔ |

**El cromo de pantalla desaparece entero**, incluido el tooltip que quedó
abierto: no se cuela nada. El tema oscuro imprime con la paleta clara forzada en
los dos motores (`--muni-danger-fg` pasa de `rgb(239,68,68)` en pantalla a
`rgb(176,48,48)` en papel).

**La cabecera de la tabla SÍ se repite entre páginas**, en los dos motores.
Verificado en el texto extraído: la fila `RUT TITULAR MONTO ESTADO` aparece en
las páginas 1, 2 y 3 del PDF de Chromium y en las páginas 1, 2, 4, 5 y 6 del de
Firefox (la 3 está en blanco, ver defecto **D5**).

**La sustitución de `.muni-row--danger` funciona con los fondos apagados.**
Medido en modo impresión:

```
pantalla:  box-shadow: rgb(176,48,48) 3px 0 0 inset   border-left: 0px none   ::before: none
impresión: box-shadow: none                            border-left: 3px solid rgb(176,48,48)   ::before: "(*) "
```

En el PDF sin gráficos de fondo la fila morosa sale con **borde sólido a la
izquierda + el prefijo `(*)` + negrita**, y se distingue sin depender del color
(`build/capturas/zoom-chr-pie.png`, `pag-chr-claro-1.png`). Esto es lo que se
pidió y esto cumple.

### 2.2 D1 — GRAVE: el contador de páginas imprime un dato FALSO

`resources/views/components/hoja.blade.php:221`

```css
.muni-hoja__nro::after { content:"Página " counter(page) " de " counter(pages); }
```

Lo que sale impreso, contado sobre el PDF:

| Motor | Páginas | Qué dice el pie |
|---|---|---|
| Chromium 151 | 4 | **`Página 0 de 0`** en las 4 páginas |
| Firefox 153 | 6 | **`Página 0 de 0`** en la página 1, **`Página de`** en las páginas 2 a 6 |

```
$ pdftotext -layout hoja-chromium-claro-sinfondos.pdf - | grep -o "Página [0-9]* de [0-9]*" | sort | uniq -c
      4 Página 0 de 0
```

Aislado con una página mínima (`$TMPDIR/muniui/contador.html`), tres formas de
pedir el contador en un documento de 4 páginas:

| Dónde va el `counter()` | Chromium 151 | Firefox 153 |
|---|---|---|
| En `position: fixed` (lo que hace la hoja) | `FIJO 0 de 0` ×4 | `FIJO 0 de 0`, luego `FIJO de` |
| En el flujo normal | `FLUJO 0 de 0` | `FLUJO 0 de 0` |
| En una caja de margen `@page { @bottom-right { … } }` | **`MB 1 de 4` … `MB 4 de 4`** ✔ | no emite nada |

O sea: **ningún motor resuelve `counter(page)` fuera de las cajas de margen de
`@page`**. Chromium sí las soporta y ahí el contador funciona perfecto; Firefox
no las soporta y ahí el contador simplemente no se dibuja — que es la
degradación buena.

**¿Degrada de verdad, como dice el comentario del componente?** A medias, y esa
mitad es la que importa:

- ✔ El folio y la leyenda de verificación **sí** se imprimen en todas las
  páginas de los dos motores (`Folio A-2026-4821 · Verifica el folio en la
  Oficina de Partes, Manuel Rodríguez 545.` aparece 4 veces en Chromium y 6 en
  Firefox). El pie no se pierde.
- ✘ Pero el contador **no degrada a nada: degrada a texto falso**. «Página 0 de
  0» en un acta de fiscalización no es un hueco, es un dato incorrecto en un
  documento que se cita en un Juzgado de Policía Local. Y «Página de» es una
  frase partida. El comentario del código dice «donde no se resuelve el contador
  queda vacío»; medido, **eso no ocurre en ninguno de los dos motores**.

### 2.3 D4 — MEDIA: el pie fijo no reserva espacio y el contenido se imprime debajo

`resources/views/components/hoja.blade.php:219`
(`.muni-hoja__pie { position: fixed; left:0; right:0; bottom:0; }`)

Un elemento `position:fixed` en medios paginados se repite en cada hoja, pero
**no aparta el flujo**: el contenido sigue llegando hasta el borde inferior y se
dibuja por debajo del pie. Se ve en los dos motores.

Evidencia gráfica a 200 ppp:

- `build/capturas/zoom-chr-pie.png` — página 1: los bordes verticales de la
  tabla **atraviesan** el texto del pie (`Folio|A-2026-4821` y `Página 0|de 0`).
- `build/capturas/zoom-chr-solape.png` — página 3: la línea `border-top` del pie
  **cruza por encima** de la fila del timeline `Informativo · Ingresada · 10:04`
  y le parte el punto de estado por la mitad.

En estas páginas el choque es con líneas, no con texto, porque tuvo suerte el
reparto. Con otro largo de nómina la última fila cae encima del pie y se
sobreimprime. En un acta eso es un renglón ilegible.

### 2.4 D5 — MEDIA: en Firefox aparece una página EN BLANCO en medio del documento

Firefox imprime **6 páginas y la número 3 sale completamente vacía**, salvo el
pie fijo (`build/capturas/pag-ff-claro-3.png`). La página 2 termina en la fila
32 y la 4 arranca en la 33: se pierde una hoja entera. Chromium, con el mismo
HTML, imprime 4 páginas sin hueco.

Reproducible en los dos temas (`hoja-firefox-claro-sinfondos.pdf` y
`hoja-firefox-oscuro-sinfondos.pdf`, las dos con 6 páginas y la 3 vacía).

Aislado quitando una cosa por vez antes de imprimir:

| Qué se quitó | Páginas | En blanco |
|---|---|---|
| nada (banco tal cual) | 6 | **[3]** |
| `.muni-topbar` | 6 | [3] |
| `.muni-sb` | 6 | [3] |
| `.muni-toast` | 6 | [3] |
| `break-inside:avoid` de las filas | 6 | [3] |
| **`padding` del envoltorio `<main>`** | **4** | **[]** |

**La causa es el relleno del envoltorio del anfitrión, que sobrevive a la
impresión.** El bloque de impresión de `resources/css/muni-ui.css:376` sólo pone
a cero **una** clase:

```css
.muni-ds__main { padding: 0 !important; }
```

Es decir: el reset está atado al armazón de dashboard del propio paquete.
Cualquier sistema que ponga la hoja dentro de otro contenedor con relleno —un
`<main>` propio, una tarjeta, una página Filament— se lleva en Firefox una
página en blanco por documento, y en los dos motores un pie desalineado con el
cuerpo (medido: la regla del pie va de 51 pt a 545 pt mientras la tabla va de
70 pt a 519 pt; el pie escapa del relleno porque es `fixed`, el cuerpo no).

Chromium tolera lo mismo sin página en blanco, así que probando sólo en Chrome
esto no se ve.

---

## 3. Los cinco en pantalla

### 3.1 Capturas — 20 archivos, 4 por componente

Tomadas sobre `build/vitrina/` con Chromium, `device_scale_factor=2`, recortadas
a la tarjeta de cada componente:

```
build/capturas/{hoja,diff-campos,timeline,combobox,tooltip}-{escritorio,movil}-{claro,oscuro}.png
```

- escritorio = 1440×900 · móvil = 390×844
- **Sin desbordamiento horizontal** en ninguna combinación:
  `scrollWidth == clientWidth` (1440/1440 y 390/390) en los dos temas. También
  se probó a 320 px sin desbordar.

Capturas extra del banco con Alpine: `combobox-abierto-*`,
`combobox-movil-abierto-*`, `tooltip-abierto-*`, `tooltip-respaldo-*`,
`tooltip-movil-*`, `skiplink-*`, `print-{chromium,firefox}-{claro,oscuro}.png`,
y los PDF de la hoja rasterizados página a página (`pag-chr-*`, `pag-ff-*`).

### 3.2 D2 — GRAVE: el tooltip «reparado» es ilegible en los dos motores

`resources/views/components/tooltip.blade.php:226-238`

La burbuja con el texto `Anular el giro de la patente`, sobre un disparador de
55×21 px:

| Motor | Ancho | Alto | `width` computado | `max-width` |
|---|---|---|---|---|
| Chromium 151 | **41 px** | **150 px** | `22.53px` | `184.18px` |
| Firefox 153 | **39 px** | **165 px** | `21.40px` | `184.33px` |

Es una tira vertical de una palabra por línea, con las palabras **partidas a la
mitad** por el `overflow-wrap: anywhere`. Se lee, literalmente:
`Anu / lar / el / giro / de / la / pat / ent / e`.
Captura: **`build/capturas/tooltip-abierto-chromium.png`**.

Causa aislada. Se borró en caliente la regla `@supports (anchor-scope: --muni-tt)`
de la hoja de estilos y se volvió a medir la misma burbuja:

| | Chromium | Firefox |
|---|---|---|
| Con anclaje CSS (`position-area: top center`) | 41×150 | 39×165 |
| **Sin anclaje CSS (respaldo de Alpine)** | **165×26** ✔ | **164×26** ✔ |

`position-area: top center` hace que el bloque contenedor de la burbuja sea la
celda central de la rejilla del ancla, o sea **el ancho del disparador**
(55 px − 7 px de margen a cada lado = 41 px). El `max-width: min(28ch, 90vw)`
computa 184 px y no sirve de nada, porque el que manda es el contenedor. Falta
un `width: max-content` (o `justify-self: anchor-center`) dentro del bloque
`@supports`.

**Por qué no se había visto**: el comentario del propio archivo, líneas 78-80,
dice *«El anchor positioning hace lo mismo mejor y sin JS, pero Safari y Firefox
no lo tienen todavía»*. Eso ya no es cierto. Medido con `CSS.supports()`:

| | `anchor-name` | `anchor-scope` | `position-area` |
|---|---|---|---|
| Chromium 151 | ✔ | ✔ | ✔ |
| **Firefox 153** | **✔** | **✔** | **✔** |

Los dos motores entran hoy por la rama «mejorada», y la rama mejorada es la rota.
El respaldo de Alpine —el camino que el comentario da por vigente— es el único
que dibuja bien, y ya no lo toma nadie.

Idéntico a 390 px (41×150 en Chromium, 39×165 en Firefox, en los dos temas):
no es un problema de ancho de pantalla.

### 3.3 D3 — GRAVE: en `diff-campos` la etiqueta de estado se pisa con la columna «Antes»

`resources/views/components/diff-campos.blade.php` — `.muni-dc__c-estado { width:18% }`
(y `24%` en `@media (max-width:640px)`) contra `.muni-dc__tag { white-space:nowrap }`.

La tabla es `table-layout: fixed`, así que la columna no crece; la píldora sí, y
sale de la celda por la derecha. La celda vecina tiene `overflow: visible`, de
modo que los dos textos se dibujan **encima uno del otro**.

Medido en Chromium sobre la vitrina (px de desbordamiento respecto del borde
derecho de su celda = px que invade la celda «Antes»):

| Etiqueta | Ancho píldora | Ancho celda (1440) | Desborde | Ancho celda (390) | Desborde |
|---|---|---|---|---|---|
| `+ Agregado` | 82 | 80 | **14** | 74 | **16** |
| `− Suprimido` | 85 | 80 | **17** | 74 | **19** |
| `± Modificado` | 90 | 80 | **22** | 74 | **24** |
| `= Sin cambios` | 94 | 80 | **26** | 74 | **28** |

Idéntico en tema oscuro y también a 320 px. Se ve a simple vista en
`build/capturas/diff-campos-movil-claro.png`: «± Modificad**o**» queda tapado por
«Calle Uno 100», y «− Suprimido» por «antiguo@ejemplo.cl».

**No es sólo un problema de móvil.** El umbral es aritmético: con la regla del
18 % hace falta que la tabla mida **≥ 522 px** (94 ÷ 0,18) y con la del 24 %
(≤640 px) hace falta **≥ 392 px**. En un teléfono de 390 px el ancho disponible
para la tabla nunca llega a 392 px, así que **en móvil está roto siempre**; en
escritorio se rompe en cuanto la tabla vive en una columna estrecha (un panel
lateral, una tarjeta, la propia vitrina).

La reja de accesibilidad no lo detecta: `npm run a11y:vitrina` da `166 textos,
0 bajo umbral, 0 axe grave` en las cuatro combinaciones, porque mide contraste y
reglas de axe, no solapamiento geométrico.

### 3.4 Recorrido por teclado

Banco con Alpine, 1440×900, Tab desde el principio del documento. Orden obtenido
(idéntico en Chromium y Firefox):

| # | Elemento | Tamaño | Indicador de foco |
|---|---|---|---|
| 1 | `a.muni-skip-link` «Saltar al contenido principal» | 231×39 | `3px solid rgb(15,118,110)` |
| 2 | enlace de la barra lateral *(del banco, no del paquete)* | 61×22 | por omisión del navegador |
| 3 | `button.muni-hoja__imprimir` «Imprimir» | 93×**44** | `3px solid rgb(15,118,110)` |
| 4 | `div[role=region]` de la tabla («Patentes morosas») | 693×2183 | `3px solid rgb(15,118,110)` |
| 5-6 | botones sueltos *(del banco)* | — | por omisión |
| 7 | `input[role=combobox]` | 340×40 | `3px solid rgb(15,118,110)` |
| 8 | `button.muni-combo__clear` «Limpiar la búsqueda» | **24×24** | `3px solid rgb(15,118,110)` |
| 9 | disparador del popover | 179×36 | `3px solid rgb(15,118,110)` |
| 10 | **`summary.muni-tl__summary`** «Ver el detalle del cambio» | 161×30 | `3px solid rgb(15,118,110)` |

- **La parada nueva del `timeline` existe y funciona.** `tabIndex = 0`, el
  `<details>` arranca cerrado (`open: false`), Enter lo abre y aparece el texto
  `Domicilio: Calle Uno 100 -> Calle Dos 200`. Foco visible, 161×30 px.
- **Foco visible en todo lo que emite el paquete**: `outline: 3px solid
  rgb(15,118,110)` (`--muni-focus`). Los elementos sin outline propio de la
  tabla son botones planos de mi banco, no del paquete.
- **El foco no queda tapado por la topbar pegajosa.** El enlace de salto es
  `position:fixed`, `z-index: 1000` contra el `z-index: 100` de la topbar, y
  `document.elementFromPoint()` en sus tres esquinas devuelve el propio enlace.
  Verificado en los dos motores (`build/capturas/skiplink-*.png`).
- **Áreas táctiles**: botón Imprimir 93×**44** ✔ (regla de terreno), opción del
  combobox 330×**46** ✔, `<summary>` 161×**30** ✔, campo del combobox 340×**40** ✔.
  El botón de limpiar del combobox mide **24×24**: cumple WCAG 2.2 AA 2.5.8
  justo en el mínimo, pero **queda por debajo del 44×44 que este repo exige para
  interfaces de terreno** (ver D8).

### 3.5 `combobox` — las seis teclas obligatorias

Medido tecla por tecla leyendo `aria-expanded`, `aria-activedescendant`, el valor
visible y el `<input type="hidden">`. **Resultado idéntico en Chromium 151 y
Firefox 153, todo correcto:**

| Tecla | `aria-expanded` | `aria-activedescendant` | Valor visible | `input` oculto |
|---|---|---|---|---|
| (foco) | `false` | — | `Ana Soto Miranda` | `4821` |
| ↓ | `true` | `…-lista-0` | — | — |
| ↓ | `true` | `…-lista-1` | — | — |
| ↑ | `true` | `…-lista-0` | — | — |
| Fin | `true` | `…-lista-4` | — | — |
| Inicio | `true` | `…-lista-0` | — | — |
| Enter | `false` | `null` | `Ana Soto Miranda` | `4821` |
| Esc (1ª) | `false`, alto de la lista `0` | — | `Ana Soto Miranda` (intacto) | `4821` |
| Esc (2ª) | `false` | — | **`""`** | **`""`** |
| escribir `Ber` | `true` | `null` (resaltado a cero) | `Ber` | — |

- **↓ abre y baja, ↑ sube, Inicio/Fin saltan a los extremos, Enter elige, Esc
  cierra y la segunda pulsación limpia los dos campos, escribir reabre la lista
  y resetea el resaltado.** Las seis, en los dos motores.
- **El foco NUNCA se mueve a las opciones.** Comprobado en cada paso con
  `document.activeElement.getAttribute('role') === 'option'` → **`false`**
  siempre; `document.activeElement` se queda en `input#muni-p_titular` de
  principio a fin. El resaltado viaja sólo por `aria-activedescendant`. ✔
- El refiltrado real es del servidor (`wire:model.live.debounce.300ms`) y no se
  puede probar sin Livewire; lo que sí se verificó es que escribir reabre la
  lista y limpia el resaltado, que es la parte del cliente.
- A 390 px la lista mide 340 px y **cabe entera en el viewport**
  (`left ≥ 0 && right ≤ 390`), las opciones miden 330×46 y no hay desbordamiento
  horizontal. ✔

Aviso de método: una primera medición marcó «la lista sigue visible tras el
primer Esc». Era falso — estaba midiendo la altura durante la transición de
salida de 160 ms. Repetida con 500 ms de espera y leyendo `aria-expanded`, el
comportamiento es el correcto. Se deja anotado porque el error estuvo a punto de
entrar como defecto.

### 3.6 `tooltip` — comportamiento (aparte del tamaño roto)

Todo lo funcional **pasa en los dos motores**:

| Prueba | Resultado |
|---|---|
| En reposo | burbuja oculta (alto 0) ✔ |
| **Aparece con el foco de teclado** | visible ✔ |
| **Cierra con `Esc`** | alto 0 ✔ |
| El foco se va a otro botón | cierra ✔ |
| Vuelve el foco | reaparece ✔ |
| Aparece con el puntero | visible ✔ |
| **El puntero cruza los 7 px de aire y entra en la burbuja** | **sigue abierta** ✔ |

La última se midió moviendo el ratón en 8 pasos desde el centro del disparador
hasta el centro de la burbuja y esperando 500 ms: la gracia de 180 ms de
`cerrar()` hace su trabajo y se cumple WCAG 2.2 AA 1.4.13 («hoverable»).

Lo que falla es la **forma** de la burbuja, no su comportamiento: ver **D2**.

### 3.7 `prefers-reduced-motion: reduce`

Contexto de Playwright con `reduced_motion="reduce"`, los dos motores:

| Propiedad | Normal | Con `reduce` |
|---|---|---|
| `--muni-dur` | `160ms` | **`0ms`** ✔ |
| `.muni-combo__gira` `animation-name` / `duration` | *(giro)* | **`none` / `0s`** ✔ |
| `.muni-tl__summary` `transition-duration` | — | **`0s`** ✔ |
| `.muni-hoja__imprimir` `transition-duration` | — | **`0s`** ✔ |

**El movimiento baja a 0 de verdad**, incluida la ruedita del combobox, que se
apaga por el `@media (prefers-reduced-motion: no-preference)` y no por una
duración a cero. ✔

### 3.8 Contraste

Se corrió la reja del repo, `npm run a11y:vitrina`, sobre la vitrina recién
generada, en las cuatro combinaciones:

```
build/vitrina/claro.html    light  texto=166  contraste↓=0  peor=—  axe grave=0  pasa
build/vitrina/claro.html    dark   texto=166  contraste↓=0  peor=—  axe grave=0  pasa
build/vitrina/oscuro.html   light  texto=166  contraste↓=0  peor=—  axe grave=0  pasa
build/vitrina/oscuro.html   dark   texto=166  contraste↓=0  peor=—  axe grave=0  pasa
```

Corrobora la medición previa. **No lo tomes como aval de los cinco
componentes**: la reja mide contraste y reglas de axe sobre lo *visible en
reposo*, y no vio ninguno de los tres defectos graves de este informe.

---

## 4. Defectos, por gravedad

| # | Gravedad | Componente | Archivo:línea | Qué pasa |
|---|---|---|---|---|
| **D1** | **Grave** | `hoja` | `resources/views/components/hoja.blade.php:221` | El pie imprime `Página 0 de 0` (Chromium, 4/4 páginas) o `Página de` (Firefox, 5/6 páginas). `counter(page)`/`counter(pages)` no resuelven fuera de las cajas de margen de `@page` en ningún motor. Degrada a **dato falso**, no a hueco. |
| **D2** | ~~Grave~~ **CERRADO 2026-09-10** | `tooltip` | `resources/views/components/tooltip.blade.php` (bloque `@supports` del anclaje) | **Era:** `position-area: <lado> center` convertía la celda central de la rejilla —el ancho del disparador— en el bloque contenedor de la burbuja, así que el `max-width` no podía hacer nada: 41×150 px (Chromium) y 39×165 px (Firefox) sobre un botón de 55 px, palabras partidas letra a letra. **Es:** `position-area: <lado> span-all` + `width: max-content` dentro del mismo `@supports`. Vuelto a medir con `getBoundingClientRect()` en Chromium 151 y Firefox 153: **164,5×25,5 px** y **164,4×25,5 px**, dos líneas, en los cuatro `placement`, a 1440 y a 390 px, y sin recorte dentro de un `overflow-x:auto` desplazado. Se **conserva** la rama de anchor positioning, y el comentario de las líneas 78-80 —que daba a Firefox por sin soporte— quedó corregido con la medición de `CSS.supports()`. Candado en `tests/AyudaBreveTest.php` (§5 bis). El §3.2 de este informe queda como el diagnóstico original y **describe el estado anterior**, no el actual. |
| **D3** | ~~Grave~~ **CERRADO 2026-09-10** | `diff-campos` | `resources/views/components/diff-campos.blade.php` (`.muni-dc__c-estado` :263 y :328/:343, `.muni-dc__tag` :289, `box-sizing` :252, `@supports` de contenedor :335-346) | **Era:** la columna de estado en PORCENTAJE (18 % / 24 %) encogía con el contenedor mientras la píldora, en `white-space:nowrap`, medía siempre lo mismo. Medido: píldora 81,8 / 85,2 / 89,8 / **93,8 px** contra una celda de 61,7-81,4 px, o sea **12,5 a 46,0 px** fuera de la celda y encima del texto de «Antes» (23 pares de cajas de texto solapadas en la vitrina, en los dos motores y en los dos temas). **Es:** la columna se dimensiona AL CONTENIDO en px medidos —122 px normal, 108 px compacta— con `box-sizing:border-box` para que el número no dependa del reset del anfitrión; las dos columnas de valor pasan a `width:auto` y se reparten el resto; y la píldora lleva `max-width:100%` + `white-space:normal`, así que si algún día no cabe **envuelve en vez de sobreimprimir**. Lo compacto se decide además por el ancho de la TABLA, con `@container` dentro de `@supports (container-type: inline-size)` sobre un envoltorio interior (nunca sobre el nodo con los atributos del anfitrión), que es lo que arregla la mitad de escritorio del defecto: la tarjeta estrecha de la propia vitrina. **Vuelto a medir con `getBoundingClientRect()`** en Chromium 151 y Firefox 153, claro y oscuro, a 390 / 768 / 1440 px, los cuatro estados a la vez: píldora 76,8-88,8 px dentro de una celda de 108 px, **desbordamiento de −23,2 a −11,2 px (siempre negativo: la píldora queda DENTRO)** y **0 solapamientos** en las 48 mediciones de la vitrina. En un banco aparte con dirección completa y correo institucional largo, contenedores de 260 a 1408 px: 0 desbordes, 0 solapes, 0 textos fuera del marco. Respaldo probado a la fuerza (fuente del anfitrión en monoespaciada, texto al 140 %, etiqueta de 30 caracteres): la píldora envuelve —alto 21,6 → 53,3 px— y **nunca** sale de la celda. El estado se sigue leyendo en palabra completa —«Agregado», «Modificado», «Suprimido», «Sin cambios»— con el glifo en `aria-hidden`; `npm run a11y:vitrina` sigue en «Todo pasa». Candados nuevos en `tests/DiffCamposTest.php` (6 pruebas; 4 de ellas fallan contra el componente anterior). El §3.3 de este informe queda como el diagnóstico original y **describe el estado anterior**, no el actual. |
| **D4** | Media | `hoja` | `resources/views/components/hoja.blade.php:219` | El pie es `position:fixed` y no aparta el flujo: el contenido se imprime por debajo. Verificado a 200 ppp en Chromium (bordes de tabla cruzando el texto del pie; la regla del pie partiendo una fila del timeline). Mismo comportamiento en Firefox. |
| **D5** | Media | `hoja` / CSS base | `resources/css/muni-ui.css:376` | El reset de relleno para impresión sólo conoce `.muni-ds__main`. Con cualquier otro envoltorio con relleno, **Firefox mete una página en blanco por documento** (6 páginas en vez de 4; la 3 vacía) y el pie queda desalineado con el cuerpo (51-545 pt contra 70-519 pt). Chromium no lo acusa: probando sólo en Chrome no se ve. |
| **D6** | Baja | `hoja` | `resources/views/components/gob-escudo.blade.php:11` (vía `hoja.blade.php:92`) | El escudo sale de `asset('vendor/muni-ui/logo-graneros.png')`. En la vitrina eso es `http://localhost/vendor/muni-ui/logo-graneros.png` y **no carga**: el membrete se ve con el icono de imagen rota y su `alt` (`build/capturas/hoja-movil-oscuro.png`). Nadie ha visto nunca el membrete real. En un anfitrión que no corra `vendor:publish --tag=muni-ui-images`, cada acta se imprime igual. |
| **D7** | Baja | vitrina | `tests/GeneraVitrinaTest.php` | La vitrina **no emite un solo `<script>`**: sin Alpine, el combobox no abre, el tooltip no aparece y el botón de limpiar queda bajo `x-cloak`. Todo lo interactivo de estos cinco componentes queda fuera de lo que mide `npm run a11y:vitrina`, y el comentario del test ya lo reconoce como «agujero conocido». Los tres defectos graves de arriba son consecuencia directa de eso. |
| **D8** | Baja | `combobox` | `resources/views/components/combobox.blade.php:402` | `.muni-combo__clear` mide 24×24 px. Cumple WCAG 2.2 AA 2.5.8 exactamente en el mínimo, pero queda por debajo del 44×44 que el repo exige para interfaces de terreno (el botón «Imprimir» de la hoja sí lo cumple: 93×44). |

---

## 5. Lo que NO se pudo comprobar

Se anota para que nadie lo dé por verde.

1. ~~**Safari / WebKit.**~~ **CERRADO 2026-09-10 — ver §7.** Se midió con
   `webkit-2336` (WebKit **26.5**): soporta anchor positioning, así que entra por
   la rama modificada del tooltip, y ahí la burbuja sale bien (164,5×25,5 px en
   las cuatro colocaciones). Apareció un defecto propio del motor, **D14**.
2. **Impresión en papel de verdad y el diálogo real de impresión.** Todo salió
   de `emulate_media`, `page.pdf()` (Chromium) y `Mozilla Save to PDF`
   (Firefox). Una impresora física puede paginar distinto.
3. **Tamaño de papel comparable.** La preferencia `print_paper_id` no tomó
   efecto en Firefox: **Chromium imprimió Letter (612×792 pt) y Firefox A4
   (596×842 pt)**. Los recuentos de páginas de los dos motores **no son
   comparables entre sí**; lo que sí es válido es la comparación de Firefox
   consigo mismo (6 páginas con relleno contra 4 sin él).
4. ~~**Dentro de un panel Filament.**~~ **CERRADO 2026-09-10 — ver §8.** Se
   armó un banco que carga **solo** `resources/css/muni-ui-filament.css` con los
   componentes renderizados por Blade dentro del DOM del panel. Las reglas de
   impresión duplicadas **no se comportan igual que las de `muni-ui.css`**:
   salieron de ahí **D9**, **D10**, **D11**, **D12**, **D13**, **D15** y **D16**.
5. **Lectores de pantalla reales** (NVDA, VoiceOver, Orca). El
   `aria-activedescendant`, el `role="status"` del recuento y el
   `aria-current="step"` del timeline se comprobaron **en el DOM**, no
   escuchándolos.
6. **El refiltrado del combobox contra el servidor** (`wire:model.live`) y la
   supervivencia del componente al morph de Livewire 3/4: hace falta una
   aplicación Livewire, no existe en este paquete.
7. **Que el foco salga del documento tras la última parada en Firefox.** Firefox
   headless sigue devolviendo el último elemento enfocado indefinidamente al
   pulsar Tab. **No es una trampa de foco**: al agregar un botón después del
   `<summary>`, el foco llegó a él correctamente. Simplemente el paso a la
   interfaz del navegador no es observable en este arnés.
8. **Contraste medido por mí, píxel a píxel.** Me apoyé en la reja del repo
   (`scripts/a11y-check.py`), que da 0 fallos; no volví a medir a mano.

---

## 6. Cómo reproducir

```bash
# vitrina + reja de contraste
vendor/bin/pest --filter=GeneraVitrina
npm run a11y:vitrina

# banco propio con Alpine (fuera del repo)
php "$TMPDIR/muniui/render.php" light "$TMPDIR/muniui/banco-claro.html"
php "$TMPDIR/muniui/render.php" dark  "$TMPDIR/muniui/banco-oscuro.html"

# sondas (todas en $TMPDIR/muniui/, con .venv-a11y/bin/python)
#   impresion.py  estilos computados bajo media=print, los dos motores
#   pdf.py        PDF de Chromium, con y sin gráficos de fondo
#   pdf_ff.py     PDF de Firefox por «Mozilla Save to PDF»
#   contador.py   aísla counter(page) en flujo / fixed / caja de margen
#   teclado.py    recorrido, combobox, tooltip, timeline, reduced-motion
#   afina.py      aísla D2 borrando el bloque @supports en caliente
#   blanca2.py    aísla D5 quitando una cosa por vez antes de imprimir
#   capturas.py   las 20 capturas de la vitrina
#   movil.py      combobox y tooltip abiertos a 390 px

# análisis de los PDF
pdfinfo   build/capturas/hoja-chromium-claro-sinfondos.pdf
pdftotext -layout build/capturas/hoja-firefox-claro-sinfondos.pdf - | grep -o "Página.*"
pdftoppm -png -r 200 -f 1 -l 1 build/capturas/hoja-chromium-claro-sinfondos.pdf salida
```

Los scripts viven en `$TMPDIR` a propósito (no ensucian el repo) y se pierden al
reiniciar; los artefactos que importan quedaron en `build/capturas/`, que no se
versiona.

---

## 7. WebKit / Safari — la superficie que el arnés tenía instalada y no usaba

Fecha de esta tanda: **2026-09-10**, misma rama `develop`. Motor:

| Motor | Versión | Cómo se lanzó |
|---|---|---|
| WebKit | **26.5** (`webkit-2336`) | Playwright 1.62.0 (`.venv-a11y`), headless |

Chromium 151.0.7922.34 y Firefox 153.0 se volvieron a correr **en la misma
tanda y sobre las mismas páginas** para que la comparación sea legítima: donde
abajo dice «los tres motores», son tres corridas del mismo script.

### 7.1 Soporte del motor, medido y no supuesto

`CSS.supports()` en las tres, sobre la página del banco:

| | `anchor-name` | `anchor-scope` | `position-area` | `position-try-fallbacks` | `:has()` | `container-type` | `color-mix` | atributo `popover` |
|---|---|---|---|---|---|---|---|---|
| Chromium 151 | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Firefox 153 | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| **WebKit 26.5** | **✔** | **✔** | **✔** | **✔** | **✔** | **✔** | **✔** | **✔** |

O sea: **WebKit también entra por la rama de anchor positioning del tooltip**, que
era la duda abierta del §5.1. El comentario de `tooltip.blade.php` («WebKit no se
midió en este arnés») ya se puede cerrar con número.

### 7.2 `tooltip` — la rama modificada, medida en WebKit

Banco: una página por colocación, un solo disparador centrado en un viewport de
1440×900 con espacio de sobra por los cuatro lados
(`$TMPDIR/panel/tt1-{top,bottom,left,right}.html`), cargando **solo**
`muni-ui-filament.css`. Foco por teclado, `getBoundingClientRect()`:

| Colocación | Chromium 151 | Firefox 153 | **WebKit 26.5** | Hueco al disparador | Desvío del centro |
|---|---|---|---|---|---|
| `top` | 164,5×25,5 | 164,4×25,5 | **164,5×25,5** | 7 px arriba | dx 0 |
| `bottom` | 164,5×25,5 | 164,4×25,5 | **164,5×25,5** | 7 px abajo | dx 0 |
| `left` | 164,5×25,5 | 164,4×25,5 | **164,5×25,5** | 7 px a la izquierda | dy 0 |
| `right` | 164,5×25,5 | 164,4×25,5 | **164,5×25,5** | 7 px a la derecha | dy 0 |

Dos líneas de texto, `width: max-content` resuelto en `146,5 px` contra un
`max-width` de `184,2 px`, la burbuja entera dentro del viewport en las doce
combinaciones (3 motores × 4 colocaciones). **El arreglo de D2 se sostiene en
WebKit.** Captura: `build/capturas/panel-tooltip-webkit-{claro,oscuro}.png`.

Comportamiento, también en WebKit: aparece con el foco de teclado, `Esc` la cierra
(alto 25,5 → 0), el foco a otro botón la cierra, y el puntero **cruza los 7 px de
aire y entra en la burbuja sin que se cierre** (8 pasos de ratón + 500 ms de
espera; WCAG 2.2 AA 1.4.13). ✔

> Aviso de método, para que nadie lo repita: en una primera pasada `right` salía
> dibujado a la IZQUIERDA en los tres motores. No es un defecto: la rejilla del
> banco ponía ese disparador a 149,3 px del borde derecho y la burbuja necesita
> 171,5 px, así que `position-try-fallbacks: flip-inline` **hizo exactamente lo que
> tiene que hacer**. Con espacio suficiente, `right` va a la derecha. Y una pasada
> anterior midió `49,7×22` en las cuatro colocaciones: el banco tenía el Blade sin
> renderizar y `document.querySelector('#tt-top')` estaba devolviendo el
> `HTMLUnknownElement` `<x-muni::tooltip>`, no la burbuja. Las dos mediciones
> falsas se descartaron.

### 7.3 `popover` — atributo nativo, en los tres motores

Página del banco con el componente real, **solo** `muni-ui-filament.css`, Alpine
3.15.12 local.

| Prueba | Chromium 151 | Firefox 153 | **WebKit 26.5** |
|---|---|---|---|
| `HTMLElement.prototype.popover` existe | ✔ | ✔ | **✔** |
| Clic en el disparador → `:popover-open` | `true` | `true` | **`true`** |
| Panel visible | 320×154 | 320×158 | **320×161** |
| `aria-expanded` del disparador | `true` | `true` | **`true`** |
| **`Esc` cierra** | ✔ | ✔ | **✔** |
| **`Esc` devuelve el foco al invocador** | ✔ | ✔ | **✔** |
| `aria-expanded` vuelve a `false` | ✔ | ✔ | **✔** |
| **Light-dismiss (clic en 5,5)** | ✔ | ✔ | **✔** |
| A 390 px el panel cabe entero | 390 px, sin desborde | ídem | ídem |

Nada que reportar: el popover es lo único de esta tanda que sale limpio en las
tres superficies y en los dos temas. Capturas: `panel-popover-{motor}-{tema}.png`.

### 7.4 `combobox` — las seis teclas en WebKit

Mismo protocolo del §3.5 (leer `aria-expanded`, `aria-activedescendant`, el valor
visible y el `<input type="hidden">` tecla por tecla). **WebKit 26.5 da el mismo
resultado que Chromium y Firefox, correcto en las diez filas:**

| Tecla | `aria-expanded` | `aria-activedescendant` | Valor visible | `input` oculto |
|---|---|---|---|---|
| (foco) | `false` | — | `Ana Soto Miranda` | `4821` |
| ↓ | `true` | `…-lista-0` | — | — |
| ↓ | `true` | `…-lista-1` | — | — |
| ↑ | `true` | `…-lista-0` | — | — |
| Fin | `true` | `…-lista-4` | — | — |
| Inicio | `true` | `…-lista-0` | — | — |
| Enter | `false` | `null` | `Ana Soto Miranda` | `4821` |
| Esc (1ª) | `false` | — | intacto | `4821` |
| Esc (2ª) | `false` | — | **`""`** | **`""`** |
| escribir `Ber` | `true` | `null` | `Ber` | — |

**El foco NUNCA se mueve a las opciones**, tampoco en WebKit: en los diez pasos
`document.activeElement.getAttribute('role') === 'option'` devuelve `false` y el
foco se queda en `input[role=combobox]`. La lista abre a 240 px de alto en los
tres motores. Capturas: `panel-combobox-webkit-{claro,oscuro}.png`.

### 7.5 Recorrido por teclado en WebKit

21 paradas (las mismas que Chromium; Firefox tiene 22 porque además enfoca el
contenedor desplazable de `tabs`). **Todo lo que emite el paquete lleva
indicador de foco propio** en los tres motores: `outline: 3px solid` de
`--muni-focus`, medido **7,52:1** en claro (`#355a63` sobre `#ffffff`) y
**9,16:1** en oscuro (`#7ccbe1` sobre `#0f2025`), contra el mínimo de 3:1.

Eso vale la pena decirlo explícito: **el respaldo
`var(--muni-focus, var(--muni-accent, #767676))` no hace falta en este contexto
porque `--muni-focus` SÍ está declarado en `muni-ui-filament.css`** (líneas 55 y
125). El anillo se dibuja donde tiene que dibujarse, incluidos `switch` y
`checkbox`, cuyo `outline` lo lleva la capa visual hermana
(`input:focus-visible + .muni-switch`) y no el `input` de 0×0.

### 7.6 D14 — MEDIA, y **solo WebKit**: colores rancios al pasar a `media: print`

Único defecto propio del motor que apareció en esta tanda.

Protocolo: la misma página, medida dos veces — (a) con `emulate_media("print")`
aplicado **antes** de cargar y (b) aplicado **después** de cargar, que es lo que
pasa de verdad cuando alguien pulsa Imprimir sobre una pantalla ya abierta. Se
comparan `color`, `background-color`, `border-top-color` y `box-shadow` de los
**829 nodos** de la página.

| Motor | Nodos que difieren (claro) | Nodos que difieren (oscuro) |
|---|---|---|
| Chromium 151 | **0** | **0** |
| Firefox 153 | **0** | **0** |
| **WebKit 26.5** | **9** | **13** |

En WebKit el valor del token en la raíz SÍ se actualiza
(`getComputedStyle(document.documentElement).getPropertyValue('--muni-accent')`
devuelve `#0f766e`, el valor de impresión), pero varios descendientes que lo
consumen se quedan con el color **de pantalla**. Ejemplos medidos:

| Nodo | Debe imprimir | WebKit imprime |
|---|---|---|
| `.muni-btn--primary` fondo | `rgb(15,118,110)` | `rgb(53,90,99)` (el petróleo del panel) |
| `.muni-input` en error, borde | `rgb(176,48,48)` | `rgb(224,163,173)` |
| `.muni-checkbox` fondo (oscuro) | `rgb(255,255,255)` | **`rgb(15,32,37)`** |
| `.muni-switch` fondo (oscuro) | `rgb(255,255,255)` | `rgb(20,42,48)` |

La casilla es la que más cuesta: en un acta impresa desde un panel en modo
oscuro, cada `checkbox` sale como un **cuadrado casi negro**. No es un defecto
del paquete —el CSS está bien escrito y los otros dos motores lo aplican
entero—, pero sí es una superficie donde lo que el paquete promete no se cumple.

**Lo que NO puedo afirmar:** que Safari de verdad haga esto al imprimir. Está
medido a través de `emulate_media` de Playwright sobre WebKit headless, que no
es la tubería de impresión de Safari. Para confirmarlo hace falta un Mac.

### 7.7 Lo que en WebKit quedó sin medir

- **Paginación real de `hoja`.** `page.pdf()` de Playwright es solo Chromium y
  WebKit headless no expone «guardar como PDF». Sí se comprobó que **el armazón
  de impresión de la hoja resuelve idéntico en los tres motores** bajo
  `media=print` (`.muni-hoja__hojas` → `table`, `> tbody` → `table-row-group`,
  `> tfoot` → **`table-footer-group`**, `.muni-hoja__celda` → `table-cell`,
  `.muni-dt thead` → `table-header-group`, `.muni-hoja__pie` → `position: static`
  con `break-inside: avoid`). Que WebKit **repita** ese `tfoot` en cada hoja y
  qué hace con la caja de margen `@bottom-right` **no está verificado**.
- Un falso positivo que se descartó: en una corrida secuencial, `Esc` no cerraba
  el tooltip `top` en WebKit (4/4 veces). Repetido con una página limpia por
  colocación y 700 ms de espera, cierra siempre. Se deja anotado como artefacto
  del arnés, no como defecto.

---

## 8. El contexto del panel: qué pasa cuando solo se carga `muni-ui-filament.css`

Este es el caso que DESIGN §7 dice que se rompe en silencio, y hasta ahora
**ninguna medición del repo lo había cargado**.

### 8.1 El banco

No hace falta un Filament de verdad; hace falta la **condición**. El banco está
en `$TMPDIR/panel/` y se genera con `render-panel.php`, que arranca Blade con
`Orchestra\Testbench\Foundation\Application` y solo `MuniUiServiceProvider`, y
emite una página que:

- carga **únicamente** `resources/css/muni-ui-filament.css` en un `<style>` —
  nunca `muni-ui.css`;
- reproduce el DOM del panel: `<html class="dark">` en oscuro, `body.fi-body`,
  `div.fi-main-ctn > main.fi-main`, cada componente dentro de un `section.fi-section`,
  más `aside.fi-sidebar` y `div.fi-topbar` con sus clases reales;
- renderiza **25 componentes reales** por Blade —entre ellos los seis nuevos:
  `hoja` (con una `data-table` de 60 filas), `diff-campos`, `checkbox-group`,
  `combobox`, `popover`, `timeline`— más `input`/`select`/`textarea`/`switch`/
  `checkbox` para los bordes, y `modal`/`drawer`/`dropdown`/`accordion`/
  `tab-panel`/`command-palette` para `x-cloak`;
- carga Alpine 3.15.12 local (hay una variante sin JS para el peor caso).

Del armazón solo se reponen dos cosas que las pone Filament y no el paquete: la
geometría del layout (anchos, `padding`) y `color: var(--muni-text)` en el
`<body>` —Filament emite ahí `text-gray-950 dark:text-white`—, para no medir
texto negro sobre fondo oscuro que sería un defecto del banco. **Ni un color más.**

Copia de trabajo dentro del repo, para poder correrle la reja: `build/banco-panel/`
(`panel-{claro,oscuro}.html`, `alertas-{claro,oscuro}.html` y sus controles
`base-*` con `muni-ui.css`). `build/` no se versiona.

### 8.2 Lo que sí está bien

Antes de la lista de defectos, lo que se midió y pasa — que es la mayor parte:

- **Ningún `var(--muni-*)` que use un componente cae al valor de respaldo.** Se
  cruzaron los 51 tokens `--muni-*` que aparecen en `resources/views/` contra los
  48 que declara `muni-ui-filament.css` más los 3 que declaran los propios
  componentes: **0 huecos**. Los únicos dos que están en `muni-ui.css` y no en
  `muni-ui-filament.css` —`--muni-panel` y `--muni-gob-verde-dark`— **no los usa
  ningún componente** (`grep` sobre `resources/views/`), así que no hay nada que
  arreglar ahí.
- **El indicador de foco funciona** (§7.5): 7,52:1 en claro y 9,16:1 en oscuro,
  en los tres motores y en las 21 paradas del recorrido.
- **El borde de `input`, `select`, `textarea`, `switch`, `checkbox` y `combobox`
  en estado normal cumple 1.4.11**: `--muni-field-border` da **3,83:1** en claro
  (`#90815b` sobre `#ffffff`) y **3,59:1** en oscuro (`#467c89` sobre `#0f2025`);
  sobre `--muni-surface-3`, 3,24:1 y 3,21:1. El mínimo es 3:1. El token declarado
  a propósito en las dos hojas hace su trabajo. *(El estado de ERROR es otra
  historia: ver D11.)*
- **Las reglas de impresión ocultan el cromo del panel.** Bajo `media=print`, en
  los tres motores y en los dos temas: `.fi-sidebar` `block` → `none`,
  `.fi-topbar` `block` → `none`, `.muni-hoja__acciones` `flex` → `none`. En el
  PDF no aparece ni «Barra superior del panel» ni «Bandeja»
  (`pdftotext | grep -c` → **0** en Chromium y en Firefox).
- **El reset de relleno `:has(.muni-hoja)` funciona y cierra D5 en este
  contexto.** Los cuatro ancestros de la hoja —`div`, `main.fi-main`,
  `div.fi-main-ctn`, `body.fi-body`— quedan en `padding: 0px` al imprimir, en los
  tres motores. Y el síntoma que D5 describía **ya no aparece**: el PDF de
  Firefox del banco del panel sale con **9 páginas y ninguna en blanco**
  (caracteres por página: 374 · 307 · 679 · 161 · 282 · 1278 · 1292 · 1292 ·
  1075), el mismo recuento que Chromium.
- **El contador de páginas ya no imprime un dato falso** (cierra D1 en este
  contexto): Chromium imprime `Página 1 de 9` … `Página 9 de 9`, **9 de 9
  correctas**; Firefox no dibuja **ninguna** (`grep -c 'Página N de M'` → 0), que
  es la degradación buena. El folio y la leyenda sí salen en todas las hojas que
  ocupa el acta (5 en A4 de Firefox, 6 en Letter de Chromium).
- **La sustitución de `.muni-row--danger` sigue en pie**: en impresión,
  `box-shadow: none`, `border-left: 3px solid` y `::before: "(*) "`.
- El armazón de paginación de la hoja resuelve igual en los tres motores (§7.7).

### 8.3 D9 — GRAVE: el papel sale con el fondo del panel y el texto en negro encima

`resources/css/muni-ui-filament.css:467-471` contra `:276-277`

El bloque de impresión fuerza la paleta clara con

```css
    html,
    body{ background:#ffffff !important; color:var(--muni-text) !important; }
```

y `--muni-text` pasa a `#000000`. Pero el mismo archivo, 190 líneas más arriba,
dice:

```css
.dark .fi-body, .dark .fi-main-ctn, .dark .fi-main { background-color:#081418 !important; }
.dark .fi-section, .dark .fi-wi, .dark .fi-wi-stats-overview-stat { background:#0f2025 !important; … }
```

Las dos reglas llevan `!important`, así que **decide la especificidad**:
`.dark .fi-body` (0,2,0) le gana a `body` (0,0,1). El `@media print` no cambia
eso. Resultado: **el texto se va a negro y el fondo se queda oscuro.**

Medido con `media=print`, contando el contraste de cada nodo de texto visible de
la página del banco:

| Motor | Panel, tema claro | **Panel, tema oscuro** | Mismo DOM con `muni-ui.css` |
|---|---|---|---|
| Chromium 151 | 1 de 386 bajo umbral | **65 de 386** | 1 de 388 |
| Firefox 153 | 1 de 386 | **65 de 386** | 1 de 388 |
| WebKit 26.5 | 1 de 386 | **62 de 386** | 1 de 388 |

Peor caso, idéntico en los tres motores: **1,12:1** — `rgb(0,0,0)` sobre
`rgb(8,20,24)`. Cae ahí el membrete entero del acta: «Municipalidad de
Graneros», «Acta de fiscalización», el folio `A-2026-4821`, «Fiscalizador»,
«Juan Pérez · Inspección Municipal», «Titular»…

En papel de verdad, con los gráficos de fondo **activados** en el diálogo de
impresión, medido sobre el PDF rasterizado a 40 ppp (página 4):

| PDF | Píxeles del fondo del panel | Total de la página | % de la hoja en tinta |
|---|---|---|---|
| `panel-hoja-chr-oscuro-confondos.pdf` | 52.805 (`#0f2025`) + 30.929 (`#081418`) | 149.600 | **56,0 %** |
| `panel-hoja-ff-oscuro-confondos.pdf` | 38.202 (`#0f2025`) + 43.956 (`#081418`) | 155.376 | **52,9 %** |

Con los gráficos de fondo **desactivados** el papel sale blanco y el texto negro
se lee: la página 4 del PDF sin fondos da 131.984 px blancos de 149.600. O sea
que el defecto **depende de una casilla del diálogo de impresión que el paquete
no controla**, y el comentario del propio archivo dice —con razón— que no se
puede confiar en ella. Lo que no puede pasar es que, marcada, el acta salga
ilegible.

Capturas: `build/capturas/panel-print-{chromium,firefox,webkit}-oscuro.png`
(vista `media=print` del banco) y los cuatro PDF `panel-hoja-{chr,ff}-oscuro-*.pdf`.

### 8.4 D10 — GRAVE: `[x-cloak]` no existe en el panel, y se ve el contenido que debería estar oculto

`resources/css/muni-ui.css:23` — y **nada equivalente en `muni-ui-filament.css`**

La regla `[x-cloak] { display: none !important; }` vive solo en `muni-ui.css`.
Cuatro componentes la declaran además en su propio `@once` —`tooltip.blade.php:190`,
`combobox.blade.php:390`, `sortable-table.blade.php:376` y
`dashboard-shell.blade.php:38` (este último, acotado a dos clases suyas)— pero
**seis la usan sin declararla**: `modal`, `drawer`, `dropdown`, `accordion`,
`tab-panel` y `command-palette`.

Mientras en la página haya un `tooltip`, un `combobox` o una `sortable-table`, la
regla llega por la puerta de atrás y no se nota. **En una pantalla del panel que
solo tenga un menú, un acordeón o unas pestañas, no llega.**

Banco mínimo (`$TMPDIR/panel/solo-panel.html`): esos seis componentes, nada más,
con **solo** `muni-ui-filament.css`. Se inyecta un `<div x-cloak>` de sonda y se
lee su `display`:

| Página | regla `[x-cloak]` | Nodos `[x-cloak]` | **Con contenido visible** |
|---|---|---|---|
| solo `muni-ui-filament.css` | **`display: block`** | 4 | **3** |
| solo `muni-ui.css` (control) | `display: none` | 4 | **0** |

Idéntico en Chromium 151, Firefox 153 y WebKit 26.5. Lo que queda a la vista,
leído con `document.body.innerText`:

- el **menú del `dropdown`** desplegado, 232×46 px;
- el **panel del `accordion`**, 1390×38 px, con el texto que debería estar
  colapsado;
- **los dos `tab-panel` a la vez** — el de la pestaña activa y el de la inactiva.

Con Alpine cargado el destello dura hasta que hidrata; sin Alpine (o si el bundle
falla) queda así para siempre. Y no hay red debajo: **los cuatro CSS de
`vendor/filament/*/dist/` de licencias-graneros (Filament v5.7.6) tienen cero
apariciones de `x-cloak`** (`grep -o x-cloak | wc -l` → 0 en `support/dist/index.css`,
`filament/dist/theme.css`, `forms/dist/index.css` y `filament/dist/fonts/inter/index.css`).
Que hoy no se vea en algún sistema depende de que su `app.css` compilado la
traiga —en licencias-graneros aparece 1 vez—, o sea de suerte del anfitrión.

### 8.5 D11 — GRAVE (1.4.11): el borde de un campo EN ERROR es MENOS visible que el de un campo normal

`resources/css/muni-ui-filament.css:68` (claro) y `:138` (oscuro)

`input`, `select`, `textarea`, `combobox`, `checkbox` y `rut-input` cambian el
borde a `var(--muni-danger-border)` cuando el campo es inválido
(`input.blade.php:83`, `select.blade.php:74`, `textarea.blade.php:138`,
`combobox.blade.php:399`, `checkbox.blade.php:155`, `rut-input.blade.php:112` y `:199`).
Ese token vale distinto en los dos archivos:

| | `muni-ui.css` | `muni-ui-filament.css` |
|---|---|---|
| claro | `#b03030` | **`#e0a3ad`** |
| oscuro | `#991b1b` | **`#6b2430`** |

Contraste del borde contra `--muni-surface`, medido con `getComputedStyle` sobre
el campo real del banco (mínimo WCAG 2.2 AA 1.4.11: **3:1**):

| Borde | Panel claro | Panel oscuro | Base claro | Base oscuro |
|---|---|---|---|---|
| `--muni-field-border` (campo normal) | 3,83 ✔ | 3,59 ✔ | 3,73 ✔ | 3,74 ✔ |
| **`--muni-danger-border` (campo en error)** | **2,10 ✘** | **1,53 ✘** | 6,34 ✔ | **2,18 ✘** |

Idéntico en los tres motores. Dos cosas, y la segunda es la grave:

1. En el panel **no cumple 1.4.11 en ninguno de los dos temas**.
2. **Marcar un campo como inválido lo hace MENOS visible que dejarlo normal**
   (2,10 contra 3,83 en claro; 1,53 contra 3,59 en oscuro). El borde es lo único
   que dice dónde empieza y termina un campo, y el estado de error lo borra.

En oscuro el defecto **también existe con `muni-ui.css`** (2,18:1): ahí no es del
tema del panel, es del token base. Sobre `--muni-surface-3` el panel da 1,77:1
(claro) y 1,37:1 (oscuro).

El comentario de `muni-ui-filament.css:40-43` dice que `--muni-field-border` se
declara ahí «TAMBIÉN porque muni-ui.css no se carga dentro de un panel». Correcto
—y el número lo confirma—, pero el razonamiento no se extendió al token que ese
mismo borde usa cuando el campo falla.

*(No confundir con `--muni-border`, que da 1,36:1 y 1,28:1: ese es el borde
decorativo de tarjetas y separadores, donde 1.4.11 no aplica, y `muni-ui.css:57`
ya lo dice.)*

### 8.6 D12 — MEDIA: el cuerpo de `alert` no llega a 4,5:1 sobre su propio fondo de estado

`resources/views/components/alert.blade.php:30` — `<div style="color:var(--muni-muted);">`

El título de la alerta va con el `fg` del tono; **el cuerpo va con
`--muni-muted`**, que no está calibrado contra los fondos de estado. Página del
banco con las cuatro alertas (`ok`, `warn`, `info`, `danger`), medida con la reja
del repo (`scripts/a11y-check.py`) y con axe-core 4.10.2:

| Tono | Panel claro | Panel oscuro | Base claro | Base oscuro |
|---|---|---|---|---|
| `ok` | 4,52 ✔ | **4,41 ✘** | 5,54 ✔ | **4,32 ✘** |
| `warn` | 4,53 ✔ | **4,33 ✘** | 5,71 ✔ | 4,73 ✔ |
| `info` | **4,26 ✘** | **4,37 ✘** | 5,42 ✔ | 5,06 ✔ |
| `danger` | **4,15 ✘** | 4,88 ✔ | 5,43 ✔ | 5,18 ✔ |

Veredicto de la reja sobre esas páginas:

```
build/banco-panel/alertas-claro.html        light  texto=8  contraste↓=2  peor=4.15  axe grave=1  FALLA
build/banco-panel/alertas-claro.html        dark   texto=8  contraste↓=0  peor=—     axe grave=1  FALLA
build/banco-panel/alertas-base-claro.html   light  texto=8  contraste↓=0  peor=—     axe grave=0  pasa
build/banco-panel/alertas-base-claro.html   dark   texto=8  contraste↓=1  peor=4.32  axe grave=1  FALLA
```

Por qué nadie lo vio: **la vitrina renderiza `alert` con un solo tono, `warn`**, y
`warn` es justamente el que pasa en claro con `muni-ui.css` (5,71:1). Los otros
tres tonos nunca se midieron.

Captura: `build/capturas/panel-alertas-{claro,oscuro}.png`.

### 8.7 D13 — MEDIA: la etiqueta de tono de `timeline` queda en 4,49:1 en el panel

`resources/views/components/timeline.blade.php:124`

```css
.muni-tl__tone { … color:var(--muni-muted); background:var(--muni-surface-3); … }
```

En el panel ese par vale `#5c6f6d` sobre `#efece1` (`--mg-papel-2`). axe-core
4.10.2 lo reprueba en los **seis** ítems del componente:

```
4.49:1 (exigido 4.5:1)  fg=#5c6f6d bg=#efece1  10.5px normal
  «Informativo» «Conforme» «Con observación» «Rechazado» «En curso» «Sin efecto»
```

Con `muni-ui.css` el mismo par es `#5a6070` sobre `#eceef2` = **5,41:1** y pasa.
Con la fórmula de luminancia W3C redondeada a dos decimales el valor del panel da
**4,50:1**: está exactamente en el filo, y **la reja del repo lo deja pasar
mientras axe lo reprueba** (`contraste↓=0` y `axe grave=1` en la misma corrida).
Esa discrepancia importa: significa que `npm run a11y` sola no basta para este par.

El comentario de `timeline.blade.php:32-35` dice que la etiqueta se pinta con
«`--muni-muted` sobre `--muni-surface-3` —los dos tokens tienen rama clara y
oscura y **ese par ya está medido**». Estaba medido, sí: contra `muni-ui.css`.

### 8.8 D15 — BAJA: `dropdown` pone atributos ARIA en un `<div>` sin rol

`resources/views/components/dropdown.blade.php:17`

```blade
<div @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" style="display:inline-flex;">
```

axe-core lo marca **`aria-allowed-attr`, impacto `critical`**, en las cuatro
combinaciones página×tema del banco: `aria-expanded` y `aria-haspopup` no están
permitidos en un elemento sin rol (`generic`). Es un defecto de marcado, no de
CSS: **no depende de qué hoja se cargue**. Aparece acá porque **la vitrina no
renderiza `dropdown`** y ninguna corrida anterior lo tenía en pantalla.

### 8.9 D16 — BAJA: `--muni-hint` sobre `--muni-surface-3` en oscuro, 4,46:1

Par de tokens del panel: `#7e8ea0` sobre `#142a30` = **4,46:1**, bajo el 4,5:1 de
texto normal. **No se observó ningún nodo real** en este banco porque ninguna
tarjeta del panel usa `--muni-surface-3` de fondo; se reporta como riesgo de par,
no como instancia. La vitrina sí pinta tarjetas sobre `surface-3`
(`tests/GeneraVitrinaTest.php`, `.v-pieza:nth-child(even) .v-cuerpo`), así que un
anfitrión que haga lo mismo dentro del panel lo activa.

### 8.10 La tabla de pares, completa

Medida en el navegador **pintando cada token en un canvas y leyendo los píxeles**
(no `getComputedStyle().color`: Chromium serializa `color-mix()` como
`color(srgb …)` y la expresión `rgb()` no existe, así que la lectura directa se
rompe). Columna «base» = el mismo DOM con `muni-ui.css`. `X` = no llega al mínimo.

```
par (fg sobre bg)                          panel claro  base claro  panel osc  base osc  mín
text / surface                                  13.77       16.87      15.04     14.73   4.5
muted / surface                                  5.32        6.28       6.53      5.25   4.5
hint / surface                                   5.34        5.28       5.00      5.25   4.5
text / surface-3                                11.64       14.52      13.43     12.77   4.5
muted / surface-3                                4.50 X      5.41       5.84      4.55   4.5
hint / surface-3                                 4.51        4.55       4.46 X    4.55   4.5
muted / ok-bg                                    4.52        5.54       4.41 X    4.32 X 4.5
muted / warn-bg                                  4.53        5.71       4.33 X    4.73   4.5
muted / info-bg                                  4.26 X      5.42       4.37 X    5.06   4.5
muted / danger-bg                                4.15 X      5.43       4.88      5.18   4.5
ok-fg / ok-bg                                    5.23        4.69       6.29      6.55   4.5
warn-fg / warn-bg                                5.05        5.38       6.85      7.60   4.5
info-fg / info-bg                                5.72        5.45       6.13      4.75   4.5
danger-fg / danger-bg                            6.10        5.48       5.26      4.75   4.5
field-border / surface                           3.83        3.73       3.59      3.74   3
danger-border / surface                          2.10 X      6.34       1.53 X    2.18 X 3
focus / surface                                  7.52        5.47       9.16      8.43   3
accent / surface                                 7.52        5.47       9.16      8.43   4.5
on-accent / accent                               7.52        5.47       8.77      8.95   4.5
field-border / surface-3                         3.24        3.22       3.21      3.24   3
danger-border / surface-3                        1.77 X      5.46       1.37 X    1.89 X 3
focus / surface-3                                6.35        4.71       8.18      7.31   3
```

`--muni-border` sobre las superficies da 1,15-1,43:1 en los cuatro contextos. **No
está en la tabla como falla**: es el borde decorativo de tarjetas y separadores,
donde 1.4.11 no aplica, tal como dice `muni-ui.css:57`.

### 8.11 Defectos de estas dos secciones, por gravedad

| # | Gravedad | Dónde | Archivo:línea | Qué pasa |
|---|---|---|---|---|
| **D9** | **Grave** | panel + impresión | `resources/css/muni-ui-filament.css:467-471` contra `:276-277` | `html, body{background:#fff !important}` pierde por especificidad contra `.dark .fi-body{…!important}` del mismo archivo. En oscuro el papel conserva el fondo del panel y el texto se fuerza a negro encima: **65 de 386 textos bajo umbral** en Chromium 151 y Firefox 153, 62 en WebKit 26.5, peor **1,12:1**. Con `muni-ui.css` en el mismo DOM: 1. Con gráficos de fondo activados, el 52-56 % de la hoja sale en tinta. |
| **D10** | **Grave** | panel | `resources/css/muni-ui.css:23` (y ausente en `muni-ui-filament.css`) | La regla `[x-cloak]{display:none!important}` no existe en el panel. `modal`, `drawer`, `dropdown`, `accordion`, `tab-panel` y `command-palette` la usan sin declararla. En una pantalla que solo tenga esos, quedan visibles el menú del dropdown, el panel del acordeón y **los dos tab-panel a la vez**. Los tres motores. Filament no la aporta: 0 apariciones en sus cuatro CSS de `dist`. |
| **D11** | **Grave** | panel (y base en oscuro) | `resources/css/muni-ui-filament.css:68` y `:138` | El borde de un campo EN ERROR (`--muni-danger-border`) da **2,10:1** en claro y **1,53:1** en oscuro contra un mínimo de 3:1, y es **menos visible que el de un campo normal** (3,83 / 3,59). Afecta a `input`, `select`, `textarea`, `combobox`, `checkbox` y `rut-input`. En oscuro el defecto también existe con `muni-ui.css` (2,18:1). |
| **D12** | Media | panel (y base en oscuro) | `resources/views/components/alert.blade.php:30` | El cuerpo de la alerta va con `--muni-muted` sobre el fondo del estado. Panel: falla `info` (4,26) y `danger` (4,15) en claro, y `ok` (4,41), `warn` (4,33) e `info` (4,37) en oscuro. Base: falla `ok` en oscuro (4,32). La vitrina solo renderiza el tono `warn`, que es el único que pasa en claro. |
| **D13** | Media | panel | `resources/views/components/timeline.blade.php:124` | `.muni-tl__tone` queda en **4,49:1** (axe-core 4.10.2) / 4,50:1 (fórmula W3C) en los seis ítems. Con `muni-ui.css`, 5,41:1. La reja del repo lo deja pasar y axe lo reprueba en la misma corrida. El comentario de `timeline.blade.php:32-35` daba el par por medido: lo estaba, contra la otra hoja. |
| **D14** | Media | WebKit | — (defecto del motor, no del paquete) | Al pasar a `media:print` sobre un documento ya cargado, WebKit 26.5 deja **9 nodos (claro) / 13 (oscuro) de 829** con el color de pantalla: la casilla de `checkbox` imprime `rgb(15,32,37)` en vez de blanco y el borde de un campo en error imprime `rgb(224,163,173)` en vez de `rgb(176,48,48)`. Chromium y Firefox: 0 nodos. Falta confirmarlo en un Safari real. |
| **D15** | Baja | marcado (cualquier hoja) | `resources/views/components/dropdown.blade.php:17` | `aria-expanded` y `aria-haspopup="menu"` sobre un `<div>` sin rol → axe `aria-allowed-attr`, **critical**. No lo veía nadie porque la vitrina no renderiza `dropdown`. |
| **D16** | Baja | panel | `resources/css/muni-ui-filament.css:107` + `:112` | `--muni-hint` sobre `--muni-surface-3` en oscuro = **4,46:1**. Sin instancia observada en este banco; es un par de riesgo para cualquier anfitrión que pinte una tarjeta con `surface-3` dentro del panel. |

### 8.12 Lo que NO se pudo comprobar en el panel

1. **Un Filament de verdad.** El banco reproduce el DOM y la condición de carga,
   pero no la hoja de estilos de Filament ni su bundle. Una regla de Filament
   podría tapar o empeorar cualquiera de estos números. Lo único que se verificó
   contra el paquete real es que sus cuatro CSS de `dist` no traen `[x-cloak]`.
2. **La cascada real.** `MuniPanel::register()` registra la hoja en
   `PanelsRenderHook::STYLES_AFTER` (`src/Filament/MuniPanel.php:73-74`); el banco
   la mete en un `<style>` al final del `<head>`. Es equivalente en orden, pero no
   es lo mismo que competir con `@layer components` de Filament.
3. **Paginación de la hoja en WebKit** (ver §7.7) y en una impresora física.
4. **Livewire.** Ningún morfeo, ningún `wire:model.live`: este paquete no trae
   Livewire y el banco tampoco.
5. **Lectores de pantalla reales.** Sigue vigente el §5.5.
6. Los tamaños de papel siguen sin ser comparables entre motores: Chromium
   imprimió Letter (612×792 pt) y Firefox A4 (596×842 pt). Los recuentos de
   página solo valen contra sí mismos.

### 8.13 Cómo reproducir estas dos secciones

```bash
# banco del panel (fuera del repo; la copia medible va a build/banco-panel/)
php "$TMPDIR/panel/render-panel.php" light  build/banco-panel/panel-claro.html  --alpine
php "$TMPDIR/panel/render-panel.php" dark   build/banco-panel/panel-oscuro.html --alpine
php "$TMPDIR/panel/render-panel.php" light  build/banco-panel/base-claro.html   --alpine --solobase
php "$TMPDIR/panel/render-solo.php"  light  "$TMPDIR/panel/solo-panel.html"        # solo los 6 de x-cloak
php "$TMPDIR/panel/render-tt.php"    light  "$TMPDIR/panel/tt-claro.html"          # tooltip/popover/combobox
php "$TMPDIR/panel/render-tt1.php"   top    > "$TMPDIR/panel/tt1-top.html"         # una colocación por página
php "$TMPDIR/panel/render-alertas.php" light build/banco-panel/alertas-claro.html

# sondas (todas con .venv-a11y/bin/python, en $TMPDIR/panel/)
#   sonda.py           tokens vacíos + soporte del motor, 3 motores
#   sonda2.py          bordes de control y foco, panel vs base
#   foco.py            recorrido por teclado completo, 3 motores
#   cloak.py cloak2.py aísla D10
#   impresion.py       estilos computados bajo media=print en el panel
#   print-contraste.py contraste de CADA texto bajo media=print  → D9
#   pares.py           tabla de pares token/token por píxeles de canvas
#   axe.py             axe-core 4.10.2 con el detalle de cada nodo
#   tt2.py tt3.py tt4.py  geometría del tooltip (la buena es tt4.py)
#   interactivo.py     popover y combobox, 3 motores × 2 temas × 2 anchos
#   wk-print.py wk-stale.py  aísla D14
#   hoja-print.py      armazón de impresión de la hoja, 3 motores
#   pdf.py pdf_ff.py   PDF del banco del panel (Chromium y «Mozilla Save to PDF»)
#   capturas.py        las 38 capturas panel-*

# reja del repo sobre el banco del panel
python3 scripts/a11y-check.py build/banco-panel/panel-claro.html build/banco-panel/panel-oscuro.html
python3 scripts/a11y-check.py build/banco-panel/alertas-*.html
```

Artefactos: **38 archivos `panel-*`** en `build/capturas/` (capturas de los tres
motores en los dos temas, vistas `media=print`, y 8 PDF), más las páginas del
banco en `build/banco-panel/`. `build/` sigue ignorado por git
(`.gitignore:15` → `/build/`, confirmado con `git check-ignore -v`).
