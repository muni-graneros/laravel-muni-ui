# Actualizar a 0.19.0

Versión anterior efectiva: **0.17.1**. Lee primero [`UPGRADE-0.18.0.md`](UPGRADE-0.18.0.md):
todo lo que dice sigue valiendo y no se repite acá.

## Por qué se salta la 0.18.0

La etiqueta `v0.18.0` está publicada apuntando a un commit del 13 de septiembre: la subió un
proceso automático de la máquina de desarrollo, no la persona que preparaba la versión. Una
etiqueta publicada no se mueve, así que el trabajo de los días 14 y 15 sale como **0.19.0**.
Si ya instalaste `0.18.0`, tienes una versión incompleta; pasa directo a `^0.19`.

## Lo obligatorio

```bash
# 1. El constraint: en versionado 0.x, ^0.18 NO cruza a 0.19
composer require "muni-graneros/laravel-muni-ui:^0.19"

# 2. El tema del panel, que es una copia publicada
php artisan vendor:publish --tag=muni-ui-filament --force
php artisan view:clear
```

**El paso 2 no es opcional en ningún sistema con `MuniPanel`.** Esta versión arregla un defecto
que vive exactamente ahí:

> `muni-ui-filament.css` bajaba `--muni-dur-slow` a 0 ms con `prefers-reduced-motion: reduce`,
> pero **no `--muni-dur`**. Como dentro de un panel se carga solo esa hoja, la preferencia de
> movimiento reducido **no llegaba a ningún componente del paquete**. Medido en Chromium y
> Firefox: un `<x-muni::button>` seguía animando 0,16 s con la preferencia activada.

Sin republicar, el defecto sigue ahí y no da ningún error. Para comprobar que llegó:

```bash
grep -c "prefers-reduced-motion" public/vendor/muni-ui/filament.css   # tiene que ser ≥ 1
grep -c "muni-scrim" public/vendor/muni-ui/filament.css               # tiene que ser ≥ 1
```

## Lo que cambia de aspecto sin que hagas nada

| Qué | Antes | Ahora | Por qué |
|---|---|---|---|
| Velo de `modal` | petróleo institucional | casi negro, como `drawer` y `command-palette` | el borde del diálogo contra el velo medía 2,77:1 en oscuro; WCAG 1.4.11 pide 3:1. Ahora 3,70–4,20:1 |
| Transiciones dentro del panel con movimiento reducido | 0,16 s | 0 s | ver arriba |
| Borde de la lista de `combobox` y del panel de `pantalla-bloqueo` | borde de superficie normal | borde de superficie flotante | flotan sobre la página |

## Componentes nuevos

Veinte, todos aditivos. El detalle está en el [CHANGELOG](../CHANGELOG.md#0190--2026-09-15); los
que más cambian el día a día:

- `<x-muni::pii>` — el dato personal oculto por omisión, revelado con un acto explícito que emite
  un evento para que el sistema lo registre. Es la pieza que la Ley 21.719 pide para la
  trazabilidad de accesos, y **la bitácora la escribe el sistema, no el paquete**.
- `<x-muni::pantalla-bloqueo>` — bloquear la sesión del mesón sin perder el trabajo en curso.
- `<x-muni::formulario-tramite>`, `<x-muni::asistente>`, `<x-muni::agenda-horas>`,
  `<x-muni::bandeja de trabajo>` (`bulk-bar`) — las composiciones de pantalla completa.
- `<x-muni::plantilla-pantalla>` — el punto de partida con enlace de salto, landmarks nombrados
  y región de mensajes, para que una pantalla nueva no nazca con las omisiones de siempre.

## Para desarrollar el paquete

`vendor/bin/testbench serve` levanta una vitrina navegable en `/vitrina` con todos los
componentes, generada recorriendo el directorio: un componente nuevo aparece el día que se crea.
Vive en `workbench/` y **ningún sistema la expone**: el service provider no registra rutas.

## Deuda que esta versión NO cierra

- `<x-muni::busy-region>` está verificada con árbol de accesibilidad, pero **no con NVDA ni
  VoiceOver reales**: no hay ninguno en la máquina de desarrollo.
- En tema claro, el borde de un diálogo contra su velo no llega a 3:1 y no puede llegar: con
  panel blanco y velo oscuro es geométricamente imposible. Ahí el límite lo dibuja el relleno
  (3,60–5,13:1), que es el criterio que la prueba exige.
