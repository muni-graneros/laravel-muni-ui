# Actualizar a 0.18.0

Versión anterior: **0.17.1**. Salto de la segunda cifra, que en versionado 0.x significa que
hay que leer esta nota antes de subir el `composer.json`.

Nada de lo que sigue rompe una llamada existente: todas las props nuevas son opcionales y
ningún token ni prop se renombró ni se quitó. Lo que sí cambia es **el aspecto de algunas
cosas** —tres colores y el apagado de la paginación— y **hay un artefacto que hay que volver
a publicar**, sin el cual un panel Filament queda con un defecto invisible.

---

## 1. Lo obligatorio (2 minutos)

```bash
composer update muni-graneros/laravel-muni-ui
php artisan vendor:publish --tag=muni-ui-filament --force
php artisan view:clear
```

**El `vendor:publish` no es opcional en ningún sistema que use `MuniPanel`.** El paquete
inyecta en los paneles únicamente `public/vendor/muni-ui/filament.css`: `muni-ui.css` no se
carga ahí (el porqué está en `DESIGN.md` §7). Ese archivo es una copia publicada, así que
subir la dependencia no lo actualiza solo.

En esta versión ese archivo ganó dos cosas: el token `--muni-field-border` y las reglas de
`@media print`. Si no republicas:

- el borde de los campos dentro del panel se queda sin color y **no hay ningún error
  visible** — la pantalla se ve casi igual y el defecto de contraste sigue ahí;
- lo que imprimas desde el panel sale con la barra lateral, la topbar y el cromo encima.

Para saber si te toca:

```bash
grep -rl "MuniPanel" app/ config/     # si aparece algo, republica
```

Después de republicar, comprueba que llegó:

```bash
grep -c "muni-field-border" public/vendor/muni-ui/filament.css   # tiene que ser > 0
```

Si el sistema sirve el CSS con hash o por CDN, invalida la caché: es el mismo nombre de
archivo con contenido nuevo.

---

## 2. Lo que cambia de aspecto sin que hagas nada

Tres cambios de color y uno de estructura. Ninguno rompe, pero si tienes capturas de
referencia o pruebas de regresión visual, van a diferir.

| Qué | Antes | Ahora | Por qué |
|---|---|---|---|
| `--muni-hint` en tema claro | `#6b7280` | `#656c79` | No llegaba a 4.5:1: medía 4,16:1 sobre `--muni-surface-3` y 4,47:1 sobre `--muni-bg`, que son justo las superficies donde vive el texto de ayuda de un campo |
| Borde de `input`, `select`, `switch`, `textarea` | heredado, 1,28:1 a 1,90:1 | token nuevo `--muni-field-border`, ≥3:1 | WCAG 1.4.11: en un campo el borde es lo único que dice dónde empieza |
| Mensaje de error de campo y de `file-dropzone` | sobre la superficie del anfitrión | fondo propio `--muni-danger-bg` | Medía 4,17:1 en oscuro según dónde se pusiera el componente |
| `timeline` con `tone` | solo el color del punto | el color **más** una etiqueta visible (`Conforme`, `Rechazado`, …) | WCAG 1.4.1: seis puntos de colores distintos son seis puntos iguales para quien no distingue verde de rojo. Se sobreescribe por ítem con `toneLabel` |
| Extremos inertes de `pagination` | `<a>` activable con `opacity` | `<span>` inerte, sin opacidad | Un enlace apagado seguía siendo enfocable y activable, y la opacidad arrastra el contraste del texto |

Si tu sistema redefine `--muni-hint` por su cuenta, revisa que tu valor llegue a 4.5:1 —
el paquete ya no lo garantiza por ti.

Si estilas la paginación desde el host apuntando a `a.muni-page`, «Anterior» y «Siguiente»
en su estado inerte ya no son `<a>`: son `<span class="muni-page muni-page--off"
aria-disabled="true">`, fuera del orden de tabulación.

---

## 3. Componentes nuevos

Diez, todos aditivos. No tienes que adoptarlos para actualizar.

| Componente | Para qué |
|---|---|
| `<x-muni::skip-link>` | Enlace de salto al contenido (WCAG 2.4.1). Ya viene cableado en los tres armazones: si usas `app-shell`, `dashboard-shell` o `auth-shell` lo tienes gratis |
| `<x-muni::announcer>` | Región `aria-live` para anunciar resultados de una acción (WCAG 4.1.3) |
| `<x-muni::error-summary>` | El resumen de errores arriba del formulario, con enlaces a cada campo |
| `<x-muni::textarea>` | Campo de texto largo con contador y las mismas reglas de error que `input` |
| `<x-muni::checkbox>` | Casilla con área táctil ≥24px y error descrito en texto |
| `<x-muni::rut-input>` | RUT chileno con formato y validación de dígito verificador |
| `<x-muni::date-input>` | Fecha en formato chileno sobre `<input type="date">` nativo |
| `<x-muni::table-header>` | La franja entre el título y la tabla: contador que distingue el filtrado del total, y ranuras de búsqueda, filtros y acciones |
| `<x-muni::hoja>` | La hoja carta que sale por impresora o PDF: membrete, folio, firma y pie con numeración. El cuerpo queda libre a propósito — el paquete **no** trae plantillas de certificado, acta ni oficio |
| `<x-muni::diff-campos>` | Tabla antes/después por campo. Agregado, modificado y suprimido se leen en texto, no en el color |

El catálogo completo y legible por máquina está en `registry.json`; cómo se arma una
pantalla con ellos, en `SKILL.md`.

---

## 4. Props nuevas en componentes que ya usabas

Todas opcionales, todas con el comportamiento anterior como defecto.

- `tabs`: `id`, `label`, `activation` (`'automatic'` | `'manual'`).
- `sortable-table`: `caption`, `selectable`, `rowKey`, `selectionScope`, `selectionTotal`,
  `selectionName`, y orden declarado por columna con la clave `'sort'`.
- `data-table`: cabecera y primera columna fijas, alto máximo y densidad — todo opt-in.
- `segmented`: `label`.
- `file-dropzone`: `maxMb`.

---

## 5. Defectos que se corrigieron y que quizá estés sufriendo hoy

Vale la pena mirarlos porque varios se manifiestan en producción sin dar error:

- **`file-dropzone` tumbaba el Alpine de la página entera** si la etiqueta traía un
  apóstrofo (`'Sube la resolución del alcalde'` no, pero `'Acta del día'` con apóstrofo sí):
  el texto se interpolaba dentro de una cadena de comillas simples y la rompía. Si tienes
  una pantalla donde "Alpine no anda y no se sabe por qué", mira si hay un dropzone.
- **El orden numérico de `sortable-table` estaba mal con formato chileno**: `parseFloat`
  sobre `1.240.000` devuelve `1,24`. El padrón de patentes morosas se ordenaba al revés.
- **`sortable-table` no se podía ordenar sin mouse**: el clic iba en un `<th>` no enfocable.
- **`tabs` movía la selección pero no el foco** con las flechas, y no tenía `Home` ni `End`.
- **La región desplazable de `data-table` no se alcanzaba con teclado.**
- `modal` y `drawer` generaban el id de su título con `uniqid()`, que rompe el diff de
  Livewire y deja cualquier `aria-labelledby` externo apuntando al vacío. Cayeron los siete
  `uniqid()` del paquete: ahora los ids se derivan del `name`.

---

## 6. Qué NO cambia

- Los 9 invariantes institucionales `--muni-gob-*`.
- La firma del sistema: `<tr data-muni-row>`, `.muni-row--danger`, `.muni-num`.
- La compatibilidad doble: los componentes siguen funcionando en **Livewire 3 y 4**
  (`personas-graneros` sigue en 3). Nada de `@island`, `wire:show`, `wire:sort` ni
  `#[Transition]` dentro del paquete.
- `resources/css/vendor/muni-ui.css` (tag `muni-ui-css`): republícalo solo si lo tenías
  publicado y quieres los tokens nuevos fuera del panel.
- El escudo (tag `muni-ui-images`): sin cambios.

---

## 7. Cómo comprobar que quedó bien

```bash
php artisan view:clear
grep -c "muni-field-border" public/vendor/muni-ui/filament.css   # > 0
```

Y en el navegador, sobre una pantalla con formulario y tabla:

1. Modo claro y modo oscuro.
2. Recorrido completo con Tab: el foco tiene que verse siempre, incluido el primer Tab de
   la página, que ahora cae en el enlace de salto.
3. El borde de un campo vacío tiene que distinguirse del fondo.

Si tienes el paquete clonado, `npm run a11y:vitrina` mide contraste real y corre axe-core sobre las
demos en los dos temas.

---

## 8. Deuda conocida que esta versión NO resuelve

Se dice acá para que nadie la descubra en producción:

- **Las 3 landings públicas y las 2 demos de intranet siguen con textos bajo 4.5:1**
  (106 medidos). Tienen paleta propia que no deriva de los tokens; están en la lista.
- `<x-muni::announcer>` está verificado con pruebas y con axe, pero **no con un lector de
  pantalla real**.
- Los componentes nuevos no tienen aún las cuatro capturas (escritorio y móvil × claro y
  oscuro) que exige el flujo de verificación.
