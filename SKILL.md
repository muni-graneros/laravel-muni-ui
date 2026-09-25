---
name: laravel-muni-ui
description: Construir una pantalla con el sistema de diseño municipal de Graneros. Usar al crear o modificar cualquier vista Blade o componente Livewire de un sistema que tenga `muni-graneros/laravel-muni-ui` en su composer.json (licencias, discapacidad, feria, seguridad, credenciales, control de acceso, web, rrhh, atención al vecino), y al portar una pantalla existente a los componentes del paquete.
---

# Construir una pantalla con `laravel-muni-ui`

## Lo primero: no inventes un componente que ya existe

El paquete trae 54 componentes. El error más caro de este ecosistema no es escribir un componente
malo: es escribir a mano un gráfico de barras que ya existía como `<x-muni::chart-bar>`, cosa que
ya pasó porque los tokens no se cargaban en el panel y el componente se veía sin color.

**Antes de escribir markup, lee `registry.json` en la raíz del paquete.** Trae, por componente: el
nombre, sus props con tipo y valor por defecto, sus slots, si necesita Alpine y un ejemplo de uso.
Búscalo ahí primero.

```bash
# ¿existe algo para esto?
python3 -c "import json;[print(c['name'],'—',c['description']) for c in json.load(open('vendor/muni-graneros/laravel-muni-ui/registry.json'))['components']]"
```

Si de verdad no existe, el componente nuevo va **en el paquete**, no en el sistema. Un componente
que sirve en dos sistemas y vive en uno se duplica, y las dos copias divergen.

## La regla dura

**Usa `<x-muni::*>`. No escribas Tailwind para color.**

```blade
{{-- así --}}
<x-muni::stat :value="$morosas" label="Patentes morosas" tone="danger" />

{{-- así no --}}
<div class="rounded-lg bg-white p-5 text-red-600 dark:bg-gray-800">…</div>
```

El motivo no es estético. Una clase `bg-white dark:bg-gray-800` es un color que el municipio que
adopte el sistema no puede cambiar sin editar tu vista. Un `<x-muni::card>` lee tokens, y cambiar
la identidad del municipio es redefinir tokens.

Tailwind sí se usa para **disposición**: `grid`, `flex`, `gap-6`, `p-4`, anchos, breakpoints. Lo
que no se usa es Tailwind para **color, sombra, radio y tipografía**: eso son tokens.

## Cómo se arma una pantalla

### 1. El armazón

Tres, y se elige por el contexto, no por gusto:

| Armazón | Cuándo |
|---|---|
| `<x-muni::app-shell>` | pantalla pública o PWA del vecino |
| `<x-muni::dashboard-shell>` | back-office con barra lateral |
| `<x-muni::auth-shell>` | acceso, MFA, recuperar clave |

Los tres ya traen el `<html>`, el cinturón institucional, el enlace de salto al contenido y el
`<main id="muni-contenido" tabindex="-1">`. No los rehagas.

```blade
<x-muni::dashboard-shell title="Patentes morosas" system="Rentas" subtitle="Municipalidad de Graneros">
    <x-slot:sidebar>
        <x-muni::nav-section title="Rentas">
            <x-muni::nav-item href="{{ route('patentes.index') }}" :active="request()->routeIs('patentes.*')">
                Patentes
            </x-muni::nav-item>
        </x-muni::nav-section>
    </x-slot:sidebar>

    <x-muni::page-header title="Patentes morosas" subtitle="3.412 contribuyentes con deuda vigente" />
    {{-- contenido --}}
</x-muni::dashboard-shell>
```

### 2. La cabecera de la página

`<x-muni::page-header>` da el único `<h1>` de la pantalla. Uno solo, siempre, y es el título de lo
que la persona está mirando.

### 3. El contenido

Franja de indicadores arriba, contenido denso abajo. Los funcionarios municipales usan esto todo el
día: **prioriza densidad sobre aire**. Un dashboard aireado tipo landing les hace desplazarse para
ver lo que necesitan de un vistazo.

```blade
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-muni::stat :value="$hoy" label="Ingresadas hoy" :delta="$variacion" delta-dir="up" />
    <x-muni::stat :value="$vencidas" label="Vencidas" tone="danger" />
</div>
```

### 4. Las tablas

La firma del sistema: una fila con problema lleva la banda roja al borde, y las cifras van en mono
tabular.

```blade
<tr data-muni-row class="muni-row--danger">
    <td><span class="muni-num">{{ $p->rut }}</span></td>
    <td><span class="muni-num">{{ $p->monto }}</span></td>
    <td><x-muni::badge tone="danger">Vencida</x-muni::badge></td>
</tr>
```

## Los tres estados que nadie diseña y siempre hacen falta

Cada bloque que carga datos necesita los tres. Una tarjeta en blanco no es un estado.

```blade
<div wire:loading.flex wire:target="buscar" class="flex-col gap-3">
    <x-muni::skeleton height="18px" width="40%" />
    <x-muni::skeleton height="120px" />
</div>

<div wire:loading.remove wire:target="buscar">
    @forelse ($filas as $fila)
        {{-- … --}}
    @empty
        <x-muni::empty-state
            title="Sin resultados"
            description="Ningún contribuyente coincide con el filtro." />
    @endforelse
</div>
```

Distingue «no hay datos» de «el filtro no encontró nada»: son dos problemas distintos y el segundo
tiene solución a un clic.

## Lo que se rompe si no lo sabes

- **Dentro de un panel Filament no pongas `data-muni-theme`.** El panel ya pone `.dark` en el
  `<html>` y el tema del paquete define ahí los tokens. Ponerlo lo pelea.
- **El paquete no tiene bundle.** Motion One y el resto del JS se instalan en el `app.js` del
  sistema. El paquete solo aporta los módulos en `resources/js/` para que los importes.
- **`vendor:publish` no se aplica solo.** Subir el constraint en `composer.json` y correr
  `composer update` no republica nada. Si cambió el tema o los scripts del panel, hay que correr
  `vendor:publish --tag=muni-ui-filament --force`, y si cambió el escudo,
  `--tag=muni-ui-images`. Sin eso el sistema sirve la versión vieja y no hay ningún error que lo
  delate.
- **Los componentes deben funcionar en Livewire 3 y 4.** `personas-graneros`, el maestro de
  personas, sigue en Livewire 3. No uses `@island`, `wire:show`, `wire:sort` ni `#[Transition]`
  dentro de una vista que vaya a ese sistema.
- **Pasa siempre `name` a los campos.** Sin él, el id se genera con `uniqid()`, cambia en cada
  render y rompe la relación de la etiqueta con el campo.

## Antes de dar por terminada la pantalla

```bash
npm run a11y:vitrina  # el candado: mide los componentes reales en claro y oscuro
npm run a11y          # además las demos, que hoy arrastran deuda conocida
```

La reja abre cada demo en claro y en oscuro, mide el contraste resolviendo el fondo efectivo capa
por capa, y falla ante cualquier violación grave o crítica de axe. **Un commit que la deja en rojo
no debería salir.**

Si trabajas en un sistema y no en el paquete, la verificación equivalente es:

- Captura en 1440 px y en 390 px de ancho.
- Modo claro y modo oscuro, comprobados con `getComputedStyle` y no con la vista: los dos defectos
  de contraste que costaron caro acá pasaron todas las revisiones visuales.
- Recorrido completo con teclado, con el foco visible en cada parada y no tapado por el encabezado
  fijo.
- `prefers-reduced-motion` activado.
- Los cuatro estados interactivos de cada control.

## Cuando toques el paquete y no el sistema

1. El componente nuevo va en `resources/views/components/`, con su bloque `<style>` en `@once`.
2. Cero colores literales. Cero clases de Tailwind para color.
3. Toda regla clara con contraparte oscura, o construida sobre un token que tenga las dos ramas.
4. Foco con `outline: 3px solid var(--muni-focus, var(--muni-accent, #767676))`, nunca solo
   `box-shadow`: dentro de Filament la sombra se pierde.
5. Movimiento con `var(--muni-dur)`, que ya baja a 0 ms con movimiento reducido.
6. Una prueba en `tests/` que falle si el defecto vuelve.
7. Fila en el README, sección en una demo y entrada en `registry.json`.
8. Nota en `CHANGELOG.md` diciendo si el adoptante tiene que republicar algo.

El contrato completo está en `DESIGN.md`. Lo que falta y en qué orden, en `docs/GAP-ANALYSIS.md`.
Lo que ya existe con todo el detalle, en `docs/INVENTORY.md`.

## Después de subir la versión en un sistema

```bash
composer update muni-graneros/laravel-muni-ui
php artisan vendor:publish --tag=muni-ui-filament --force   # si cambió el tema o el JS del panel
php artisan vendor:publish --tag=muni-ui-images             # si cambió el escudo
npm run build                                               # si el sistema compila el CSS del paquete
```

Comprueba la deriva mirando **todo** lo que publica el tag, no solo el CSS: el mismo tag copia
`filament.css`, `filepond-video.js` y `nombre-accesible-select.js`, y un JS viejo pasa
desapercibido si solo comparas la hoja de estilos.
