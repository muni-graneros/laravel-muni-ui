# laravel-muni-ui

Sistema de diseño del ecosistema municipal de Graneros: componentes Blade, tema Filament
y tokens CSS (`resources/css/muni-ui.css`) que consumen los sistemas host
(licencias, discapacidad, feria, seguridad, credenciales, control-acceso, web).

**Regla base:** todo token de color, tipografía, radio o sombra que se necesite en más de
un sistema se agrega **aquí**, no en el repo host. Los hosts consumen; no redefinen.

Este paquete no tiene bundle propio: Motion One y el resto del JS se instalan y exponen
en el `resources/js/app.js` de cada aplicación host.

## Diseño, interactividad y animación

**Las convenciones completas viven en la skill global `blade-livewire-design`
(`~/.claude/skills/blade-livewire-design/SKILL.md`) — fuente única.** Tokens,
reglas de dashboard, accesibilidad, patrones Livewire 4 y gotchas del ecosistema.
No duplicar ese contenido acá: si una convención cambia, se cambia en la skill.

### Versiones de ESTE repo (verificado en composer.lock)

Este es un **paquete**, no una aplicación: `composer.json` requiere solo
`illuminate/support` y `illuminate/view` en `^12.0|^13.0`, sin Livewire ni
Filament.

Consecuencia: los componentes de este paquete se instalan tanto en aplicaciones
con **Livewire 4 / Filament 5** (los 9 sistemas municipales) como en
**personas-graneros, que sigue en Livewire 3 / Filament 3**. Por eso deben ser
**Blade puros**, sin depender de directivas exclusivas de un major: nada de
`@island`, `wire:show`, `wire:sort` ni `#[Transition]` dentro del paquete. Si un
componente necesita interactividad, resolverla con Alpine, que existe en ambos.

### Restricciones que no se negocian

- Blade + Livewire + Alpine + Filament. **No Vue, no React, no jQuery, no Inertia.**
- Alpine viene dentro de Livewire: nunca instalarlo ni importarlo aparte en el
  bundle del panel. (Un entrypoint público aislado que no carga Livewire es la
  única excepción, y se declara como `input` propio en `vite.config.js`.)
- Tailwind para todo el estilado; nada de CSS suelto salvo `@layer` en `app.css`.
- Animación: **Motion One** (`motion` ^13). No usar GSAP, anime.js ni AOS.
- Todo componente debe funcionar sin JS antes de animarse.
- CSS moderno (anchor positioning, scroll-driven, container queries) solo como
  mejora progresiva, dentro de `@supports`.
