# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Este paquete usa versionado 0.x: mientras el primer número sea 0, un cambio de la
segunda cifra puede romper compatibilidad y hay que leer la nota de la versión.

**Este archivo se creó el 2026-09-06**, tarde: el paquete llevaba 33 versiones sin
registro. Las entradas anteriores a esa fecha se reconstruyeron desde los mensajes de
las etiquetas de git, así que dicen *qué* cambió pero no siempre *qué había que
republicar*. Desde acá en adelante cada versión anota si el sistema que la adopta debe
volver a publicar artefactos, porque subir el `composer.json` no aplica nada por sí solo.

## [Sin publicar]

### Agregado
- `docs/INVENTORY.md`: inventario de los 53 componentes con props, tokens, Alpine,
  colores literales y accesibilidad, verificado contra el disco.
- `docs/THIRD_PARTY_NOTICES.md`: procedencia y licencia de cada fuente que informa al
  paquete, con la reja de licencias aplicada.
- `docs/INSPIRATION.md`: lo aprendido de productos comerciales que no se pueden copiar.
- `docs/GAP-ANALYSIS.md`: 55 fichas de lo que falta, con el trámite municipal donde se usa.
- `scripts/a11y-check.py` y `npm run a11y`: reja de accesibilidad que mide contraste real
  en claro y oscuro y corre axe-core. Falla ante cualquier violación grave o crítica.
- `package.json`: existe solo para correr esa reja; el paquete sigue sin bundle propio.

## [0.17.1] — 2026-09-05

El foco se ve dentro de Filament: el indicador pasa a ser un `outline`, porque la
cadena de sombras de Tailwind y Filament se comía el anillo de `box-shadow`.

## [0.17.0] — 2026-09-04

Trampa de foco en modal/drawer/paleta, contraste AA 4.5:1 en oscuro, eliminación de animación opacity:0, 19 tokens CSS, meta de Reverb fuera del título, PHPStan y Pint en CI

## [0.16.4] — 2026-08-30

La barra de progreso dice de qué es, y el texto tenue se lee

## [0.16.3] — 2026-08-30

Los componentes del paquete dejan de verse rotos dentro del panel

## [0.16.2] — 2026-08-30

Los selects con buscador anuncian la etiqueta del campo (WCAG 4.1.2)

## [0.16.1] — 2026-08-29

Corrige el contraste en modo oscuro de la etiqueta de los KPI y de la pestaña activa.

## [0.16.0] — 2026-08-25

El botón del expediente pregunta al módulo

## [0.15.0] — 2026-08-24

El panel ARCOP delega las reglas legales en el núcleo del módulo

## [0.14.0] — 2026-08-24

Panel ARCOP (Ley 21.719)

## [0.13.0] — 2026-08-23

Completa la mudanza: el paquete también enciende la tarjeta de video

## [0.12.2] — 2026-08-20

Paneles laterales con el boton de guardar fuera de la pantalla

## [0.12.1] — 2026-08-18

Cuatro huecos del modo oscuro

## [0.12.0] — 2026-07-18

Infra JS compartida del ecosistema (echo, leaflet, mapa-personas)

## [0.11.0] — 2026-07-18

Plugin MuniPanel — viste cualquier panel con la identidad municipal

## [0.10.8] — 2026-07-18

Quitar overflow:hidden del modal — rompía el scroll interno

## [0.10.7] — 2026-07-18

Alinear las tabs de relation managers con el contenido

## [0.10.6] — 2026-07-18

Rediseño bold 'Sala de Gobierno' — sidebar oscuro petróleo + acentos oro/lima

## [0.10.5] — 2026-07-18

V7 — riqueza visual (cabecera con regla, stat cards con glow, footer sidebar, secciones con tick)

## [0.10.4] — 2026-07-18

V6 — caída escalonada, formularios/botones/modales/tabs, responsive, defensa de iconos

## [0.10.3] — 2026-07-18

Tema maximizado 'instrumento de gobierno' (verificado en vivo)

## [0.10.2] — 2026-07-18

Tema mucho más marcado — sidebar papel, tarjetas y acentos institucionales

## [0.10.1] — 2026-07-18

Selector correcto del ítem activo (fi-sidebar-item-btn)

## [0.10.0] — 2026-07-18

Tema Filament municipal (CSS plano) para que los paneles no se vean genéricos

## [0.9.0] — 2026-07-18

Set final informativo, 3 públicas + 2 intranet

## [0.8.0] — 2026-07-18

Usar el escudo OFICIAL del municipio (PNG) en vez del SVG reconstruido

## [0.7.0] — 2026-07-18

Cinturón institucional en las 5 landings del ecosistema

## [0.6.0] — 2026-07-18

Pantalla de seguimiento de solicitud (timeline + documentos + cita)

## [0.5.0] — 2026-07-18

+3 componentes (otp-input MFA, rating, calendar)

## [0.4.0] — 2026-07-18

+4 componentes de datos (chart-bar, chart-donut, accordion, drawer)

## [0.3.0] — 2026-07-18

Galería de plantillas (landing, login, portal vecino, paneles por rol, error 404)

## [0.2.0] — 2026-07-18

+5 componentes de máximo nivel (command-palette, avatar, progress, skeleton, empty-state)

## [0.1.0] — 2026-07-17

Refactor(css): separar puente Tailwind v4 → paquete universal TW3/TW4
