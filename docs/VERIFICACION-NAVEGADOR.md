# Verificación en navegador — los cinco componentes nuevos o reparados

Fecha de la sesión: **2026-09-10**. Rama: `develop`.
Alcance: `<x-muni::hoja>`, `<x-muni::diff-campos>`, `<x-muni::timeline>`,
`<x-muni::combobox>` y `<x-muni::tooltip>`.

Este documento existe porque los cinco entraron con pruebas PHP y medición de
contraste, y **ninguno se había abierto en un navegador**. Acá está lo que se
abrió, con qué motor, qué se midió y qué salió mal. Nada de «se ve bien»: cada
afirmación lleva el número que la respalda.

**No se corrigió una sola línea de código.** El encargo era diagnosticar.

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
| **D2** | **Grave** | `tooltip` | `resources/views/components/tooltip.blade.php:226-238` | Con anclaje CSS, `position-area: top center` encierra la burbuja en el ancho del disparador: 41×150 px (Chromium) y 39×165 px (Firefox), palabras partidas, ilegible. El respaldo de Alpine da 165×26. Firefox 153 **ya soporta** `anchor-scope`, al revés de lo que dice el comentario de las líneas 78-80. |
| **D3** | **Grave** | `diff-campos` | `resources/views/components/diff-campos.blade.php:239` y `:283` (`.muni-dc__c-estado` 18 %/24 %) contra `:254` (`.muni-dc__tag{white-space:nowrap}`) | La píldora de estado (82-94 px) desborda su columna (73-80 px) entre 14 y 28 px y se sobreimprime con la columna «Antes». Roto **siempre** en móvil (necesita ≥392 px de tabla) y en cualquier columna estrecha de escritorio (necesita ≥522 px). |
| **D4** | Media | `hoja` | `resources/views/components/hoja.blade.php:219` | El pie es `position:fixed` y no aparta el flujo: el contenido se imprime por debajo. Verificado a 200 ppp en Chromium (bordes de tabla cruzando el texto del pie; la regla del pie partiendo una fila del timeline). Mismo comportamiento en Firefox. |
| **D5** | Media | `hoja` / CSS base | `resources/css/muni-ui.css:376` | El reset de relleno para impresión sólo conoce `.muni-ds__main`. Con cualquier otro envoltorio con relleno, **Firefox mete una página en blanco por documento** (6 páginas en vez de 4; la 3 vacía) y el pie queda desalineado con el cuerpo (51-545 pt contra 70-519 pt). Chromium no lo acusa: probando sólo en Chrome no se ve. |
| **D6** | Baja | `hoja` | `resources/views/components/gob-escudo.blade.php:11` (vía `hoja.blade.php:92`) | El escudo sale de `asset('vendor/muni-ui/logo-graneros.png')`. En la vitrina eso es `http://localhost/vendor/muni-ui/logo-graneros.png` y **no carga**: el membrete se ve con el icono de imagen rota y su `alt` (`build/capturas/hoja-movil-oscuro.png`). Nadie ha visto nunca el membrete real. En un anfitrión que no corra `vendor:publish --tag=muni-ui-images`, cada acta se imprime igual. |
| **D7** | Baja | vitrina | `tests/GeneraVitrinaTest.php` | La vitrina **no emite un solo `<script>`**: sin Alpine, el combobox no abre, el tooltip no aparece y el botón de limpiar queda bajo `x-cloak`. Todo lo interactivo de estos cinco componentes queda fuera de lo que mide `npm run a11y:vitrina`, y el comentario del test ya lo reconoce como «agujero conocido». Los tres defectos graves de arriba son consecuencia directa de eso. |
| **D8** | Baja | `combobox` | `resources/views/components/combobox.blade.php:402` | `.muni-combo__clear` mide 24×24 px. Cumple WCAG 2.2 AA 2.5.8 exactamente en el mínimo, pero queda por debajo del 44×44 que el repo exige para interfaces de terreno (el botón «Imprimir» de la hoja sí lo cumple: 93×44). |

---

## 5. Lo que NO se pudo comprobar

Se anota para que nadie lo dé por verde.

1. **Safari / WebKit.** Hay `webkit-2336` instalado, pero el encargo pedía
   Chromium y Firefox y no se probó. El anclaje CSS del tooltip (D2) puede
   comportarse distinto ahí.
2. **Impresión en papel de verdad y el diálogo real de impresión.** Todo salió
   de `emulate_media`, `page.pdf()` (Chromium) y `Mozilla Save to PDF`
   (Firefox). Una impresora física puede paginar distinto.
3. **Tamaño de papel comparable.** La preferencia `print_paper_id` no tomó
   efecto en Firefox: **Chromium imprimió Letter (612×792 pt) y Firefox A4
   (596×842 pt)**. Los recuentos de páginas de los dos motores **no son
   comparables entre sí**; lo que sí es válido es la comparación de Firefox
   consigo mismo (6 páginas con relleno contra 4 sin él).
4. **Dentro de un panel Filament.** Todas las mediciones cargaron
   `resources/css/muni-ui.css`. **`resources/css/muni-ui-filament.css` no se
   cargó en ninguna prueba**, así que las reglas de impresión duplicadas ahí
   (`muni-ui-filament.css:419-503`) están **sin verificar**. Es justo el caso que
   DESIGN §7 dice que se rompe en silencio.
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
