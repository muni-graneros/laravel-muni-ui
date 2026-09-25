# Inventario de `laravel-muni-ui`

Generado el 2026-09-05 sobre `develop` (HEAD `647dd03`, último tag `v0.17.1`). Fase 0 del plan de
modernización: **nada se creó ni se modificó en el paquete** para producir este documento.

> **Es una foto, y nueve componentes ya cambiaron desde entonces.** Las fichas de abajo describen
> defectos que en varios casos ya están corregidos, así que no sirven para decidir qué reparar: para
> eso está `docs/GAP-ANALYSIS.md`, que sí se poda. Lo que cambió, y dónde mirar el estado real:
>
> | Componente | Commit | Qué dejó de ser cierto en su ficha |
> |---|---|---|
> | `skip-link` | `b47d036` | No existía. Es nuevo. |
> | `app-shell`, `dashboard-shell`, `auth-shell` | `b47d036` | Ahora abren con el enlace de salto y su `<main>` es enfocable. |
> | `tabs`, `tab-panel` | `f98fd3a` | El foco ya se mueve con las flechas; hay `Home`/`End`, `aria-controls` y `aria-labelledby`. Props nuevas: `id`, `label`, `activation`. |
> | `sortable-table` | `c769aed` | Ya se ordena con teclado; hay `aria-sort`, `scope`, buscador etiquetado y región viva. Prop nueva: `caption`. |
> | `segmented` | `62a51a2` | El foco ya no depende de `:has()`; es `role="radiogroup"`. Prop nueva: `label`. |
> | `drawer`, `modal` | `d83e67d` | Ids deterministas y nombre accesible de respaldo. |
> | `file-dropzone` | `29bf1a8` | Se cerró la inyección en Alpine; hay foco, región viva, quitar archivo y validación. Prop nueva: `maxMb`. |
>
> Para el estado vigente de props, slots, tokens y Alpine, la fuente es `registry.json`, que se
> genera leyendo los archivos.

Método: cuatro lectores de base (CSS/tokens, PHP y pruebas, demos, documentación y JS), trece
lectores de componentes y **un verificador adversarial por componente** que releyó cada archivo y
corrigió el registro; después, este renderizador cruzó cada registro con `grep` sobre el disco
(tokens), con el README (fila documentada) y con `tests/` (cobertura). Las diferencias que
sobrevivieron están en «Discrepancias residuales».

## Resumen

| Métrica | Valor |
|---|---|
| Componentes Blade (`resources/views/components`) | 53 |
| Con Alpine | 18 |
| Que requieren un **plugin** de Alpine (no core) | 3 |
| Con algún color literal **no seguro en oscuro** | 13 |
| Veredicto `dark_ok = false` | 10 |
| Sin regla `:focus-visible` propia | 36 |
| Que animan sin respetar `--muni-dur` (reduced motion) | 16 |
| Sin fila en el README | 18 |
| Sin prueba en `tests/` | 41 |
| Tokens `--muni-*` definidos en `muni-ui.css` | 49 |
| Tokens `--muni-*` usados por algún componente | 47 |
| Usados y **no definidos** en `muni-ui.css` | ninguno |
| Definidos y **no usados** por ningún componente | `--muni-gob-verde-dark`, `--muni-panel` |
| Correcciones aplicadas por los verificadores | 75 |
| Componentes sin verificar | ninguno |

## Tabla maestra

Alpine: «core» = Alpine 3 sin plugins; en negrita el plugin que exige. `.dark`: ✅ = todos los
colores salen de tokens definidos en ambos temas (o literales con contraparte); ❌ = hay al menos un
color literal o un token solo claro. Focus/RM: regla `:focus-visible` propia / transiciones sobre
`--muni-dur` (que baja a 0 ms con `prefers-reduced-motion`).

| Componente | Líneas | Props | Slots | Tokens | Alpine | `.dark` | Focus | RM | README | Test |
|---|---|---|---|---|---|---|---|---|---|---|
| `accordion` | 52 | items, multiple, default | slot | 12 | core | ✅ | ✅ | ✅ | ❌ | — |
| `alert` | 32 | tone, title, icon | slot | 16 | — | ✅ | — | ✅ | ✅ | ✅ |
| `app-shell` | 36 | theme, title, system, subtitle, status, maxWidth | slot, head, topbar | 4 | — | ✅ | — | ✅ | ✅ | ✅ |
| `auth-shell` | 62 | theme, title, system, subtitle, logo | slot, head, aside, logo | 10 | — | ❌ | — | ✅ | ✅ | ✅ |
| `avatar` | 32 | name, src, size, tone | — | 6 | — | ✅ | — | — | ✅ | — |
| `badge` | 29 | tone, dot | slot | 17 | — | ✅ | — | — | ✅ | ✅ |
| `breadcrumb` | 19 | items | — | 4 | — | ✅ | — | — | ✅ | — |
| `button` | 51 | variant, size, href, icon, type | slot | 18 | — | ❌ | ✅ | ✅ | ✅ | ✅ |
| `calendar` | 62 | name, min | — | 16 | core | ✅ | ✅ | — | ❌ | — |
| `card` | 29 | title, subtitle, flush | slot, actions | 10 | — | ✅ | — | ✅ | ✅ | — |
| `chart-bar` | 50 | data, height, tone, labels | — | 9 | — | ✅ | — | — | ❌ | — |
| `chart-donut` | 56 | segments, size, thickness, total, centerLabel | — | 13 | — | ✅ | — | — | ❌ | — |
| `command-palette` | 75 | items, placeholder, hotkey | trigger | 15 | core + **focus** | ❌ | ✅ | ✅ | ✅ | ✅ |
| `dashboard-shell` | 58 | theme, title, system, subtitle, status, user | slot, head, sidebar, topbar | 8 | core | ✅ | — | ✅ | ✅ | ✅ |
| `data-table` | 42 | columns, empty | slot | 9 | — | ✅ | — | ✅ | ✅ | — |
| `drawer` | 51 | title, side, width | slot, trigger, footer | 11 | core + **focus** | ✅ | — | — | ❌ | ✅ |
| `dropdown` | 42 | align, width | trigger, slot | 6 | core | ✅ | — | ✅ | ✅ | — |
| `dropdown-item` | 35 | href, icon, tone | slot | 10 | core | ✅ | ✅ | ✅ | ✅ | — |
| `empty-state` | 16 | title, description, icon | actions | 5 | — | ✅ | — | ✅ | ✅ | — |
| `error-page` | 42 | theme, code, title, message, home, system | head, actions | 8 | — | ✅ | — | ✅ | ✅ | — |
| `field` | 12 | label, name | $slot | 2 | — | ✅ | — | ✅ | ✅ | — |
| `file-dropzone` | 47 | name, accept, label, hint, multiple | — | 14 | core | ✅ | — | ✅ | ❌ | — |
| `filter-bar` | 25 | action, method | $slot, $submitLabel, $actions | 7 | — | ✅ | — | ✅ | ✅ | — |
| `gob-bar` | 41 | system, home, sticky | $slot | 4 | — | ❌ | — | ✅ | ❌ | — |
| `gob-escudo` | 19 | size, src | — | 0 | — | ✅ | — | — | ❌ | — |
| `gob-footer` | 66 | system, home, address, phone, email | links | 4 | — | ❌ | — | — | ❌ | — |
| `gob-stripe` | 32 | height | — | 7 | — | ✅ | — | — | ❌ | — |
| `input` | 54 | label, name, type, error, hint, icon, required | — | 15 | — | ❌ | ✅ | ✅ | ✅ | — |
| `kpi` | 37 | value, label, tone, hint | — | 13 | — | ✅ | — | ✅ | ✅ | ✅ |
| `modal` | 85 | title, maxWidth | trigger, slot, footer | 15 | core + **focus** | ✅ | ✅ | ✅ | ✅ | ✅ |
| `nav-item` | 35 | href, icon, active, badge | slot | 14 | — | ✅ | ✅ | ✅ | ✅ | — |
| `nav-section` | 10 | title | slot | 2 | — | ✅ | — | ✅ | ✅ | — |
| `otp-input` | 49 | length, name | — | 10 | core | ✅ | ✅ | ✅ | ❌ | — |
| `page-header` | 16 | title, subtitle, eyebrow | actions | 5 | — | ✅ | — | ✅ | ✅ | — |
| `pagination` | 50 | current, total, url, info | — | 12 | — | ✅ | ✅ | ✅ | ✅ | — |
| `progress` | 37 | value, max, tone, label, showValue | — | 11 | — | ✅ | — | — | ✅ | — |
| `rating` | 42 | value, max, readonly, name, tone | — | 9 | core | ❌ | ✅ | ✅ | ❌ | — |
| `reverb-meta` | 28 | — | — | 0 | — | ✅ | — | — | ❌ | ✅ |
| `ring` | 36 | value, max, size, tone, label, showValue | — | 10 | — | ✅ | — | — | ❌ | — |
| `segmented` | 39 | name, options, value | slot | 13 | — | ❌ | ✅ | ✅ | ✅ | — |
| `select` | 57 | label, name, options, selected, placeholder, error, hint, required | slot | 14 | — | ❌ | ✅ | ✅ | ✅ | — |
| `sidebar` | 29 | width | default | 5 | core | ✅ | — | ✅ | ✅ | — |
| `skeleton` | 24 | width, height, rounded | — | 3 | — | ✅ | — | ✅ | ✅ | — |
| `sortable-table` | 83 | columns, rows, empty, searchable | — | 15 | core | ✅ | ✅ | ✅ | ❌ | — |
| `stat` | 59 | value, label, tone, delta, deltaDir, spark, hint | — | 15 | — | ✅ | — | ✅ | ✅ | ✅ |
| `stepper` | 57 | steps, current, orientation | default | 12 | — | ✅ | — | ✅ | ❌ | — |
| `switch` | 38 | label, name, checked, description | default | 11 | core | ✅ | ✅ | ✅ | ✅ | — |
| `tab-panel` | 15 | index | default | 0 | core | ✅ | — | ✅ | ❌ | — |
| `tabs` | 43 | tabs, default | default | 10 | core | ✅ | ✅ | ✅ | ✅ | — |
| `timeline` | 43 | items | — | 14 | — | ✅ | — | — | ❌ | — |
| `toast-host` | 78 | position | — | 16 | core | ✅ | — | ✅ | ✅ | — |
| `tooltip` | 24 | text, placement | default | 5 | core | ✅ | ✅ | — | ✅ | — |
| `topbar` | 43 | system, subtitle, status, statusLabel, logo | default, logo | 8 | — | ❌ | — | — | ✅ | — |

## Detalle por componente

### `<x-muni::accordion>`

Archivo: `resources/views/components/accordion.blade.php` (52 líneas) · verificado con 5 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `items` | `[]` | array | Rows of ['title'=>…,'content'=>…]; rendered only when `! empty($items)` (line 17), each row a <button class="muni-acc__head"> plus a .muni-acc__panel. |
| `multiple` | `false` | bool | Printed into x-data as `multiple: true\|false`; switches toggle()/isOpen() between the `opened` array and the single `open` index. |
| `default` | `null` | mixed | Index open on first render; interpolated as `open: {{ $default !== null ? (int) $default : 'null' }}`, i.e. cast to (int) or the literal JS `null`. |

**Slots:** `slot` — Fallback body rendered ONLY in the @else branch, when $items is empty (line 33). The @php comment on line 8 points to <x-muni::accordion-item> for this path, but no such component ships in the package.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-muted`, `--muni-radius`, `--muni-ring`, `--muni-surface`, `--muni-surface-2`, `--muni-text`.
**Alpine:** `x-data`, `@click`, `x-show`, `x-cloak`, `x-transition:enter`, `x-transition:enter-start`, `x-transition:enter-end`, `:aria-expanded`, `:class`. Solo core.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | line 44, `.muni-acc__head:focus-visible` inside the @once <style>: third fallback of `outline:3px solid var(--muni-focus, var(--muni-accent, #767676))` | border | sí | Reachable only if BOTH --muni-focus and --muni-accent are undefined, i.e. muni-ui.css is not loaded at all (in which case --muni-surface is undefined too and the ground is the host page). Neutral grey: 4.54:1 over #ffffff and ~4.2:1 over the dark --muni-bg #0b0f14 / ~4.0:1 over the dark --muni-surface #111620, all above the 3:1 that WCAG 2.2 AA 1.4.11 asks of a focus indicator, so it reads on either ground. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-acc { border: 1px solid var(--muni-border) }`; `.muni-acc { background: var(--muni-surface) }`; `.muni-acc__item + .muni-acc__item { border-top: 1px solid var(--muni-border) }`; `.muni-acc__head { background: transparent }`; `.muni-acc__head { border: none }`; `.muni-acc__head { color: var(--muni-text) }`; `.muni-acc__head:hover { background: var(--muni-surface-2) }`; `.muni-acc__head:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-acc__head:focus-visible { box-shadow: inset var(--muni-ring) }`; `.muni-acc__chevron { color: var(--muni-muted) }`; `.muni-acc__body { color: var(--muni-muted) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `:aria-expanded` · teclado: Enter (native <button type="button">), Space (native <button type="button">) · focus-visible: sí · reduced-motion: sí · etiqueta: Button label is the escaped {{ $item['title'] ?? '' }} span; no aria-label, no aria-controls, no accessible name on the panel, and the chevron <svg> has no aria-hidden so it may be announced..
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** @once ships the CSS once per response. $default only seeds `open`, never `opened`, so with multiple=true it is silently ignored. Header button and panel are not tied by id/aria-controls and the panel has no role=region; the chevron <svg> lacks aria-hidden. No `role=` attribute appears anywhere in the file — `:aria-expanded` is the only ARIA. No wire: directives, no uniqid, no named slots, no @isset guards. The line-8 comment advertises <x-muni::accordion-item> but that component does not exist in resources/views/components/ (only accordion.blade.php), so the empty-$items slot path has no companion component in this package. `@click="toggle({{ $i }})"` and `:aria-expanded="isOpen({{ $i }})"` interpolate the array KEY unquoted, so an associative $items array emits `toggle(clave)` and throws a JS ReferenceError — only list-shaped arrays work. x-show declares enter transitions only (no x-transition:leave), so closing is instant while opening animates. x-cloak relies on the `[x-cloak] { display: none !important; }` rule that lives in muni-ui.css line 23, not in this file. Reduced motion is honoured indirectly: every transition uses var(--muni-dur), which muni-ui.css drops to 0ms under @media (prefers-reduced-motion: reduce).

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the component has no dark-mode rule of its own; the whole @once block (lines 38-51) is theme-agnostic and grep for `\.dark\|data-muni-theme\|prefers-color-scheme` in the file returns nothing.» (Line 44: `.muni-acc__head:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-3px; box-shadow:inset var(--muni-ring); }` — the only near-dark-related rule, and it is a token reference, not a `.dark` counterpart. dark_ok stays true because those tokens are redefined in muni-ui.css (`[data-muni-theme="dark"], [data-theme="dark"], .dark { … --muni-focus: #f59e0b; … }`, lines 157-198).)
- `notes / slots`: el lector dijo «"the comment points to <x-muni::accordion-item> for this path" — recorded as if that component existed.»; el archivo dice «`<x-muni::accordion-item>` does not exist: `ls resources/views/components/` lists accordion.blade.php and no accordion-item.blade.php, and the string appears exactly once in the whole repo — in this comment.» (Line 8: `// $items: array de ['title'=>, 'content'=>] o usar el slot con <x-muni::accordion-item>.`)
- `notes`: el lector dijo «No mention of the array-key interpolation.»; el archivo dice «The Alpine calls interpolate the raw PHP key, unquoted, so an associative $items array produces invalid JS identifiers (`toggle(faq1)`) and every click throws ReferenceError; only list-shaped arrays are safe.» (Line 20: `<button type="button" class="muni-acc__head" @click="toggle({{ $i }})" :aria-expanded="isOpen({{ $i }})">` inside `@foreach ($items as $i => $item)` (line 18).)
- `notes / alpine`: el lector dijo «Lists the three enter directives without noting the missing leave transition, and does not record that x-cloak depends on an external rule.»; el archivo dice «Only enter transitions are declared, so panels animate open and vanish instantly on close; and the `[x-cloak]` hiding rule lives in muni-ui.css, not in this component's @once <style>.» (Lines 26-27: `<div class="muni-acc__panel" x-show="isOpen({{ $i }})" x-cloak x-transition:enter="muni-acc-enter" x-transition:enter-start="muni-acc-0" x-transition:enter-end="muni-acc-1">` — no `x-transition:leave*`. muni-ui.css line 23: `[x-cloak] { display: none !important; }`.)
- `hardcoded_colors[0].reason`: el lector dijo «"neutral grey clears 4:1 over both #ffffff and #0b0f14 surfaces"»; el archivo dice «Verdict theme_safe:true stands, but the figure is overstated for the surface the accordion actually paints: the dark `--muni-surface` is #111620 (not #0b0f14), where #767676 measures ≈3.99:1 — above the 3:1 required of a focus indicator, below 4:1.» (muni-ui.css line 115/161: `--muni-surface: #111620;` while `--muni-bg: #0b0f14;` (lines 112/160); the component paints `background:var(--muni-surface)` on line 39.)

</details>

### `<x-muni::alert>`

Archivo: `resources/views/components/alert.blade.php` (32 líneas) · verificado con 3 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `tone` | `info` | string | Selects the fg/bg/border token triple: ok, warn, danger or info. Also drives the ARIA role (danger => alert, anything else => status). |
| `title` | `null` | string | Optional heading, escaped with {{ }}, rendered font-weight:700 in the tone's foreground token ($t['fg']); guarded by @if ($title). |
| `icon` | `null` | mixed | Raw SVG markup replacing the default info glyph; echoed unescaped with {!! !!}. A <x-slot:icon> ComponentSlot fills it too, hence mixed. |

**Slots:** `slot` (obligatorio) — Alert message body, printed with {{ }} inside a div forced to color:var(--muni-muted). There is no @isset / isNotEmpty guard, so the div is emitted even when the slot is empty, and $title renders independently of it (line 29).
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-danger-bg`, `--muni-danger-border`, `--muni-danger-fg`, `--muni-font-sans`, `--muni-info-bg`, `--muni-info-border`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-bg`, `--muni-ok-border`, `--muni-ok-fg`, `--muni-radius`, `--muni-text`, `--muni-warn-bg`, `--muni-warn-border`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `role="alert" (when tone is danger)`, `role="status" (every other tone)`, `aria-hidden="true" (icon span)` · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**README:** documentado · **Tests:** cubierto
**Notas:** $icon prints through {!! !!} — raw HTML, never pass user input ($title is escaped with {{ }}). Being props, <x-slot:icon> and <x-slot:title> also fill $icon/$title. Unknown $tone degrades to info twice (?? null then ??=). All paint is inline via $attributes->merge; ComponentAttributeBag::merge treats only class and style as appendable and does implode(' ', [$defaultsValue, $value]), so the caller's style is appended last and wins. role is printed on line 18 OUTSIDE the bag, so a caller-supplied role is emitted as a duplicate role attribute and cannot override the component's (first attribute wins in HTML parsing). The default glyph is the same info circle for every tone and is aria-hidden, so ok/warn/info are conveyed to assistive tech by colour alone — only danger changes anything (role="alert"). No <style>, no Alpine, no wire:.

<details><summary>Correcciones del verificador</summary>

- `slots[0].description`: el lector dijo «Alert message body, rendered in var(--muni-muted); the component shows only an icon without it.»; el archivo dice «The muted body div is emitted unconditionally (no @isset / $slot->isNotEmpty() guard), and $title is rendered by its own @if, independently of the slot — so an alert with a title and no slot shows icon + title + an empty body div, not "only an icon".» (line 29: @if ($title)<div style="font-weight:700;color:{{ $t['fg'] }};margin-bottom:2px;">{{ $title }}</div>@endif — line 30: <div style="color:var(--muni-muted);">{{ $slot }}</div>)
- `dark_notes`: el lector dijo «—»; el archivo dice «There is a dark-mode finding worth recording: the body text token pair fails WCAG 2.2 AA in dark for tone="ok". --muni-muted (#7a8ba8 dark) on --muni-ok-bg (#0d2d1a dark) = 4.32:1 at font-size:13px (normal text, needs 4.5:1). All other tone/theme pairs pass (4.69-7.60). dark_ok stays true because every colour is still a theme-safe token.» (alert.blade.php line 30: <div style="color:var(--muni-muted);">{{ $slot }}</div>; line 22: font-size:13px; muni-ui.css line 119: --muni-muted: #7a8ba8; line 128: --muni-ok-bg: #0d2d1a;)
- `notes`: el lector dijo «…All paint is inline via $attributes->merge, and Laravel concatenates style, so a caller's style wins by coming last. No <style>, no Alpine, no wire:.»; el archivo dice «Correct as far as it goes (vendor ComponentAttributeBag::merge line 266 does implode(' ', array_unique(array_filter([$defaultsValue, $value]))) and only class/style are appendable), but it omits two facts from the file: role is hardcoded on line 18 BEFORE the bag, so a caller-passed role is printed a second time and the hardcoded one wins in HTML parsing; and the default glyph is identical for all four tones and is aria-hidden, so ok/warn/info differ only by colour for assistive tech.» (line 18: role="{{ $tone === 'danger' ? 'alert' : 'status' }}" immediately followed by line 19: {{ $attributes->merge([ ; line 25: <span aria-hidden="true" style="…color:{{ $t['fg'] }};">)

</details>

### `<x-muni::app-shell>`

Archivo: `resources/views/components/app-shell.blade.php` (36 líneas) · verificado con 3 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `theme` | `light` | string | Interpolated into data-muni-theme on <html> (line 11: data-muni-theme="{{ $theme }}"). With the default 'light' the CSS rule [data-muni-theme="light"] freezes the light palette and the prefers-color-scheme media query is excluded by :root:not([data-muni-theme="light"]). |
| `title` | `null` | string | Document <title>; falls back to $system when null ({{ $title ?? $system }}). |
| `system` | `REQUIRED` | string | No default in @props, so it is required; passed to <x-muni::topbar> as :system and used as the <title> fallback. |
| `subtitle` | `null` | string | Secondary label forwarded verbatim to <x-muni::topbar> as :subtitle. |
| `status` | `online` | string | Connection/state indicator forwarded verbatim to <x-muni::topbar> as :status. |
| `maxWidth` | `1200px` | string | CSS length echoed with {{ }} INSIDE the quoted inline style of <main>: style="max-width:{{ $maxWidth }};margin:0 auto;padding:24px 16px 48px;". Blade escaping prevents breaking out of the attribute but not ';', so untrusted input could append extra CSS declarations; it is meant to be a developer-supplied literal. |

**Slots:** `slot` (obligatorio) — Page body inside <main>; rendered unguarded as {{ $slot }} (line 33), unlike $head and $topbar which are ?? '' guarded.; `head` — Extra <head> markup (Vite tags, meta) injected after <title> via {{ $head ?? '' }} (line 20).; `topbar` — Content passed through as the default slot of <x-muni::topbar> via {{ $topbar ?? '' }} (line 29) — actions, user menu.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-bg`, `--muni-font-sans`, `--muni-text`.
**Alpine:** no (Blade puro).

**Bloque `<style>` propio:** sí; reglas de color: `body { background: var(--muni-bg) }`; `body { color: var(--muni-text) }`; `a { color: var(--muni-accent) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: No role=, aria-* or label attribute anywhere in the file. The only accessible naming is at document level: <title>{{ $title ?? $system }}</title> names the page, and <html lang="es"> declares the language (hard-coded, no prop to change it). <main> supplies the implicit main landmark, with no skip link before it..
**Depende de:** x-muni::reverb-meta; x-muni::topbar.
**README:** documentado · **Tests:** cubierto
**Notas:** Full <html> document — a layout, not a page fragment; 36 lines. $attributes is never referenced, so attributes put on <x-muni::app-shell> are silently dropped. The Blade comment records the fix: <x-muni::reverb-meta /> must stay OUTSIDE <title> (RCDATA would turn the markup into tab text and the <meta> would not exist as an element, leaving echo.js without its key). No Alpine, no wire:, no @if/@isset, no animation or transition (hence nothing to gate on prefers-reduced-motion; the media query lives in muni-ui.css, not here). <main> is the only landmark and there is no skip link. lang="es" is hard-coded. The viewport meta is width=device-width, initial-scale=1 with no user-scalable=no, so zoom is allowed. The <style> block also carries non-colour rules: * { box-sizing: border-box } and body { margin: 0; font-family: var(--muni-font-sans) }.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the component's <style> block contains no dark counterpart rule at all: no `.dark`, no `[data-muni-theme="dark"]`, no `@media (prefers-color-scheme: dark)`. `grep -n 'dark' app-shell.blade.php` returns only line 11 (`data-muni-theme`). None is needed (all three colours are theme-safe tokens, so dark_ok stays true), but the field is factually false.» (Lines 21-25: `<style>` / `*, *::before, *::after { box-sizing: border-box; }` / `body { margin: 0; background: var(--muni-bg); color: var(--muni-text); font-family: var(--muni-font-sans); }` / `a { color: var(--muni-accent); }` / `</style>` — the whole block, no dark selector.)
- `a11y.labels`: el lector dijo «none»; el archivo dice «There are no aria-* / role= attributes, but the component does emit the document-level accessible naming: a <title> built from $title ?? $system, plus a hard-coded lang="es" on <html> (no prop overrides it). Reporting "none" hides that the shell is what names every page in the ecosystem and fixes their language.» (Line 19: `<title>{{ $title ?? $system }}</title>` and line 11: `<html lang="es" data-muni-theme="{{ $theme }}">`)
- `props[maxWidth].notes`: el lector dijo «Inline max-width of <main>; interpolated unquoted into the style attribute.»; el archivo dice «It IS interpolated inside a double-quoted style attribute and escaped by {{ }} (so it cannot break out of the attribute); the real caveat is the opposite one — Blade escaping does not neutralise ';', so a host passing untrusted text could append further CSS declarations inside the style attribute.» (Line 32: `<main style="max-width:{{ $maxWidth }};margin:0 auto;padding:24px 16px 48px;">`)

</details>

### `<x-muni::auth-shell>`

Archivo: `resources/views/components/auth-shell.blade.php` (62 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `theme` | `light` | string | Written into data-muni-theme on <html> (line 10); the default 'light' matches the [data-muni-theme="light"] freeze block in muni-ui.css, so the document stays light even if the OS is dark. |
| `title` | `Ingresar` | string | <h1> of the card (line 55) and first half of the document title (line 18). |
| `system` | `Municipalidad de Graneros` | string | Brand name in the aside (line 39), the document title (line 18) and the © line (line 49). |
| `subtitle` | `null` | string | Optional muted paragraph under the <h1>, rendered only inside @if ($subtitle) (line 56). |
| `logo` | `null` | mixed | Logo markup on the white plate; @if ($logo){{ $logo }}@else falls back to a 'GRA' wordmark (line 37). |

**Slots:** `slot` (obligatorio) — The login form / card body inside .muni-auth-card (line 58); the page is empty without it.; `head` — Extra <head> markup injected after <title> via {{ $head ?? '' }} (line 19).; `aside` — Replaces the default marketing copy (ACCESO SEGURO / Ley 21.719) in the left panel; printed by {{ $aside ?? '' }} (line 42) and the default is gated by @unless (isset($aside)) (line 43).; `logo` — Also usable as a slot — ComponentSlot is Htmlable, so {{ $logo }} emits its markup unescaped.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-bg`, `--muni-border`, `--muni-font-mono`, `--muni-font-sans`, `--muni-hint`, `--muni-muted`, `--muni-radius-sm`, `--muni-surface`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#fff` | inline style of the logo plate <div> in .muni-auth-aside (line 36): background:#fff | background | **NO** | Literal white, not a --muni-gob-* invariant and with no dark override rule anywhere in the file (the <style> block has no dark rules at all); stays a bright plate on the dark aside in dark mode. |
| `#0b0f14` | inline style of the fallback 'GRA' <span> (line 37): color:#0b0f14 | text | **NO** | Literal near-black text pinned to the white plate; legible in pairing with #fff but it is a literal with no token and no dark override rule, so it is not theme-safe by definition. |
| `#000` | .muni-auth-aside::before (line 28): mask-image:radial-gradient(ellipse 70% 60% at 30% 40%,#000,transparent) | decorative | sí | Mask channel only — used for alpha, never painted as text/background/border/fill/stroke, so it is theme-independent. |

**Bloque `<style>` propio:** sí; reglas de color: `body { background: var(--muni-bg) }`; `body { color: var(--muni-text) }`; `.muni-auth-aside { background: var(--muni-surface) }`; `.muni-auth-aside { border-right: 1px solid var(--muni-border) }`; `.muni-auth-aside::before { background-image: linear-gradient(color-mix(in srgb,var(--muni-accent) 6%,transparent) 1px,transparent 1px),linear-gradient(90deg,color-mix(in srgb,var(--muni-accent) 6%,transparent) 1px,transparent 1px) }`; `.muni-auth-aside::before { mask-image: radial-gradient(ellipse 70% 60% at 30% 40%,#000,transparent) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ The logo plate is hardcoded #fff with #0b0f14 text: self-consistent as a pair but a hard white block against the dark aside, and unreachable by any dark rule since the <style> block contains no .dark / [data-muni-theme="dark"] / prefers-color-scheme rules (only two @media (min-width:900px) queries). Everything else is theme-safe tokens. Also, theme defaults to 'light', which matches [data-muni-theme="light"] in muni-ui.css and freezes the whole document light.
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**Depende de:** x-muni::reverb-meta.
**README:** documentado · **Tests:** cubierto
**Notas:** Full <html> document. $attributes never appears in the file, so caller attributes are dropped. Same reverb-meta-outside-<title> comment as app-shell (lines 14-17). The aside is display:none by default and only display:flex at min-width:900px, so its copy and © line vanish on mobile. Aside default text is gated by isset($aside) while {{ $aside ?? '' }} prints it — both branches are safe. No aria-*, no role=, no tabindex, no focus styling, no transition/animation, no Alpine and no wire:. The <style> block declares no dark-mode rules of its own; dark mode reaches it only through the --muni-* tokens, which the 'light' default of $theme then freezes.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the component's <style> block contains no dark-mode counterpart rule of any kind (no `.dark`, no `[data-muni-theme="dark"]`, no `[data-theme="dark"]`, no `@media (prefers-color-scheme: dark)`). The only two at-rules in the block are width breakpoints.» (The complete set of at-rules inside the <style> block is line 23 `@media (min-width:900px){ body{ grid-template-columns:1.05fr .95fr; } }` and line 25 `@media (min-width:900px){ .muni-auth-aside{ display:flex; } }`; `grep -n -E 'prefers-color-scheme\|\.dark\|data-muni-theme\|role=\|aria' auth-shell.blade.php` returns no line inside the style block, and the only `data-muni-theme` in the file is the attribute on line 10 `<html lang="es" data-muni-theme="{{ $theme }}">`. This is also why the literals `#fff` (line 36) and `#0b0f14` (line 37) can never be overridden in dark.)

</details>

### `<x-muni::avatar>`

Archivo: `resources/views/components/avatar.blade.php` (32 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `name` | `` | string | Full name; produces the initials, the title/aria-label pair and the <img> alt. Falsy value ("" or "0") suppresses title/aria-label. |
| `src` | `null` | string | Image URL; when truthy renders <img src alt> and the inline style drops background, color and border (line 23: .($src ? '' : "background:{$bg};color:{$fg};border:1px solid var(--muni-border);")). |
| `size` | `md` | string | sm\|md\|lg → 26/32/40px box and 10.5/12.5/15px font; any other value falls back to the md values (32 / 12.5) via ?? . |
| `tone` | `accent` | string | Strict === 'accent' uses var(--muni-accent-soft) background and var(--muni-accent) text; any other value uses var(--muni-surface-3) and var(--muni-text). |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-accent-soft`, `--muni-border`, `--muni-font-sans`, `--muni-surface-3`, `--muni-text`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: title="{{ $name }}" and aria-label="{{ $name }}" on the wrapper <span>, emitted only when $name is truthy (line 25); the <img> carries alt="{{ $name }}" (line 28). No role attribute is set, so aria-label on a generic <span> is unreliably exposed; with $src and $name both set the name is duplicated between the labelled span and the img alt; with $name empty and no $src the circle renders empty and unlabelled..
**README:** documentado · **Tests:** sin prueba
**Notas:** $attributes->merge puts the component style first and appends the caller's — verified in vendor/laravel/framework/src/Illuminate/View/ComponentAttributeBag.php::merge, which partitions 'style' as appendable and does `$value = Str::finish($value, ';')` before `implode(' ', ...[$defaultsValue, $value])` — so a caller's style= wins on duplicated declarations; both branches of the inline style already end in ';', so the concatenation stays valid CSS. Any slot content is silently discarded: the body renders only $initials or the <img>. Initials take the first letter of the first and last whitespace-separated token, uppercased with mb_*. Unknown size/tone fall back silently. No <style> block, no animation, no Alpine, no wire: directives, no nested x-muni:: components.

<details><summary>Correcciones del verificador</summary>

- `a11y.roles`: el lector dijo «["aria-label"]»; el archivo dice «[] — the file contains no role= attribute at all; aria-label is a labelling attribute, not a role, and it is already reported under a11y.labels» (Line 25 is the only ARIA-bearing line: `    @if ($name) title="{{ $name }}" aria-label="{{ $name }}" @endif` — grep for 'role=' over the 32-line file returns nothing.)
- `a11y.labels`: el lector dijo «"aria-label"»; el archivo dice «title + aria-label on the wrapper (both conditional on $name being truthy) AND alt on the <img>; three labelling mechanisms, not one — and none of them fire when $name is empty» (Line 25: `@if ($name) title="{{ $name }}" aria-label="{{ $name }}" @endif` and line 28: `<img src="{{ $src }}" alt="{{ $name }}" style="width:100%;height:100%;object-fit:cover;">`)

</details>

### `<x-muni::badge>`

Archivo: `resources/views/components/badge.blade.php` (29 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `tone` | `neutral` | string | neutral\|ok\|warn\|danger\|info; picks the fg/bg/border token triplet, unknown falls back to neutral. |
| `dot` | `true` | bool | Renders the small aria-hidden colour dot before the slot content. |

**Slots:** `slot` (obligatorio) — Badge label text; without it only the 6px dot renders.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-danger-bg`, `--muni-danger-border`, `--muni-danger-fg`, `--muni-font-mono`, `--muni-glow`, `--muni-info-bg`, `--muni-info-border`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-bg`, `--muni-ok-border`, `--muni-ok-fg`, `--muni-surface-3`, `--muni-warn-bg`, `--muni-warn-border`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `aria-hidden` · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: none.
**README:** documentado · **Tests:** cubierto
**Notas:** The dot takes the tone's fg colour plus box-shadow:var(--muni-glow), which is 'none' in light and '0 0 6px currentColor' in dark — the intentional feria glow. Unknown $tone falls back to the neutral triplet. Meaning is carried by colour plus the slot text only. No <style> block, no animation, no Alpine.

### `<x-muni::breadcrumb>`

Archivo: `resources/views/components/breadcrumb.blade.php` (19 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `items` | `[]` | array | List of ['label'=>string,'url'=>?string]; the last entry is the current page. The Blade comment on line 5 states it: "array de ['label'=>, 'url'=>?]. El último es la página actual (sin url)." |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-font-sans`, `--muni-hint`, `--muni-muted`, `--muni-text`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `aria-label`, `aria-hidden`, `aria-current` · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: aria-label.
**README:** documentado · **Tests:** sin prueba
**Notas:** aria-label="Ruta" is hardcoded Spanish and written BEFORE $attributes->merge, so a caller-supplied aria-label emits a duplicate attribute (the first wins). The last item is never a link even when it carries a url. A missing 'label' key raises an undefined-array-key error. Chevron SVG paints with currentColor from color:var(--muni-hint). Links lose their underline (text-decoration:none) with no focus style of their own. The separator is gated on the array KEY, not on the loop position: `@if ($i > 0)` instead of `! $loop->first`, so an associative, filtered or non-zero-indexed $items renders a leading chevron before the first crumb (under PHP 8, "home" > 0 compares as strings and is true).

<details><summary>Correcciones del verificador</summary>

- `notes`: el lector dijo «Lists the aria-label duplication, the never-linked last item, the missing-'label' error, the currentColor chevron and the underline/focus loss, but says nothing about how the separator is gated.»; el archivo dice «The chevron separator is gated on the foreach KEY ($i), not on $loop->first, so any $items whose first key is not 0 (associative, array_filter result, collection keyed by id) renders a stray leading chevron before the first crumb — under PHP 8 a non-numeric string key compared to 0 is compared as a string, and "home" > "0" is true.» (line 7-8: `@foreach ($items as $i => $item)` / `        @if ($i > 0)` — while the sibling conditions on lines 13 and 16 do use `$loop->last`.)

</details>

### `<x-muni::button>`

Archivo: `resources/views/components/button.blade.php` (51 líneas) · verificado con 5 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `variant` | `primary` | string | Interpolated into the class as 'muni-btn muni-btn--'.$variant. Only primary\|ghost\|subtle\|danger have rules in the @once block; unlike $size there is NO fallback, so an unknown variant renders with no background/colour and only 'border:1px solid transparent' from $base. |
| `size` | `md` | string | sm\|md\|lg padding and font-size; unknown value falls back to md. |
| `href` | `null` | string | When set the tag becomes <a href> instead of <button>. |
| `icon` | `null` | string | Raw HTML/SVG echoed unescaped with {!! !!} inside an aria-hidden span. |
| `type` | `button` | string | Native button type attribute; ignored entirely when href is set. |

**Slots:** `slot` (obligatorio) — Button label text; the only accessible name, since the icon is aria-hidden. Rendered unguarded at line 28 (no @isset), so an empty slot with only $icon produces a control with no accessible name.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-accent-strong`, `--muni-border`, `--muni-border-2`, `--muni-danger-bg`, `--muni-danger-border`, `--muni-danger-fg`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-on-accent`, `--muni-radius-sm`, `--muni-ring`, `--muni-shadow`, `--muni-surface-2`, `--muni-surface-3`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | .muni-btn:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) } | border | **NO** | Literal grey outline fallback with no dark override; reached only if both tokens are undefined. |
| `#fff` | .muni-btn--danger:hover { color: #fff } | text | **NO** | Literal white text over var(--muni-danger-fg); no dark counterpart rule. 6.34:1 in light (#b03030) but 3.76:1 on muni-ui.css dark (#ef4444) and 2.38:1 inside the Filament panel .dark bridge (#ff8195). |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-btn:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-btn:focus-visible { box-shadow: var(--muni-ring) }`; `.muni-btn--primary { background: var(--muni-accent) }`; `.muni-btn--primary { color: var(--muni-on-accent) }`; `.muni-btn--primary:hover { background: var(--muni-accent-strong) }`; `.muni-btn--primary:hover { box-shadow: var(--muni-shadow) }`; `.muni-btn--ghost { background: transparent }`; `.muni-btn--ghost { color: var(--muni-text) }`; `.muni-btn--ghost { border-color: var(--muni-border) }`; `.muni-btn--ghost:hover { background: var(--muni-surface-2) }`; `.muni-btn--ghost:hover { border-color: var(--muni-border-2) }`; `.muni-btn--subtle { background: var(--muni-surface-2) }`; `.muni-btn--subtle { color: var(--muni-text) }`; `.muni-btn--subtle:hover { background: var(--muni-surface-3) }`; `.muni-btn--danger { background: var(--muni-danger-bg) }`; `.muni-btn--danger { color: var(--muni-danger-fg) }`; `.muni-btn--danger { border-color: var(--muni-danger-border) }`; `.muni-btn--danger:hover { background: var(--muni-danger-fg) }`; `.muni-btn--danger:hover { color: #fff }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ Every colour except one is a --muni-* token defined in BOTH the :root light block and the .dark / [data-theme="dark"] / [data-muni-theme="dark"] block of muni-ui.css, so primary, ghost and subtle are dark-safe as written. The single failure is `.muni-btn--danger:hover { background: var(--muni-danger-fg); color: #fff; }`: white over that token measures 6.34:1 in light (#b03030), 3.76:1 on muni-ui.css dark (#ef4444) and 2.38:1 inside the Filament panel bridge dark (--muni-danger-fg: #ff8195, muni-ui-filament.css:131) — both dark cases below the 4.5:1 required for button text. The #767676 outline fallback is a literal with no dark override and so is not theme-safe by the strict definition, though it only bites if muni-ui.css never loads (it happens to measure 4.54:1 on #ffffff and 4.23:1 on #0b0f14).
**A11y:** roles/aria: `aria-hidden` · teclado: ninguno · focus-visible: sí · reduced-motion: sí · etiqueta: none.
**README:** documentado · **Tests:** cubierto
**Notas:** $icon is printed with {!! !!} — unescaped, so only trusted SVG markup may be passed (XSS otherwise). $href is echoed with {{ }} (HTML-escaped) but its scheme is never validated, so a caller-supplied `javascript:` or `data:` URL is a live XSS sink on the <a> branch — a second untrusted-input path the record's security note omits. Dynamic tag <{{ $tag }}>. href and type are declared in @props, so Laravel strips them from $attributes before line 25: a caller cannot duplicate them at all (they are bound to the variables instead), which is why the hand-written attributes on line 24 are the only ones emitted. $attributes->merge concatenates for class and style, so caller styles append after $base and win on conflicting declarations. The @once <style> emits only once per response — risky if the first render happens inside a Livewire partial update. Transitions use var(--muni-dur), which muni-ui.css zeroes under prefers-reduced-motion (line 245) — but muni-ui-filament.css re-declares `--muni-dur: 160ms` on :root (line 89) with no reduced-motion override of its own, so inside a Filament panel the button's 160ms transitions (including the transform used by `.muni-btn:active { transform: translateY(1px) }`) keep running for users who asked for reduced motion. The component has no prefers-reduced-motion rule of its own.

<details><summary>Correcciones del verificador</summary>

- `notes`: el lector dijo «"href/type are emitted before $attributes->merge, so caller duplicates are ignored."»; el archivo dice «Duplicates are impossible, not "ignored": 'href' and 'type' are declared in the @props block, so Laravel removes them from $attributes and binds them to $href/$type. A caller passing href="…" sets the prop; it never reaches $attributes->merge.» (Lines 1-7: `@props([` … `    'href' => null,` … `    'type' => 'button',` `])` — and line 25 `{{ $attributes->merge(['class' => 'muni-btn muni-btn--'.$variant, 'style' => $base]) }}` carries no href/type.)
- `notes`: el lector dijo «"Transitions use var(--muni-dur), which reduced motion zeroes."»; el archivo dice «True only outside Filament. muni-ui.css zeroes the token, but the panel theme re-declares it at 160ms on :root and its own reduced-motion block only kills animations on .fi-wi/.fi-section/.fi-ta — it never restores --muni-dur: 0ms. Inside a panel the button still animates under prefers-reduced-motion.» (muni-ui.css:245 `:root { --muni-dur: 0ms; }` vs muni-ui-filament.css:89 `    --muni-dur:         160ms;` (in the `:root{` bridge opened at line 6) and muni-ui-filament.css:260 `@media (prefers-reduced-motion:reduce){ .fi-wi,.fi-section,.fi-ta{animation:none !important} }`.)
- `notes`: el lector dijo «Security note flags only the `{!! $icon !!}` sink.»; el archivo dice «$href is a second untrusted-input path: it is escaped by {{ }} but its scheme is never validated, so a caller-supplied `javascript:`/`data:` URL executes on click via the <a> branch.» (Line 24: `    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif`)
- `dark_notes`: el lector dijo «"in dark that token is #ef4444, giving about 3.8:1"»; el archivo dice «Correct for muni-ui.css dark (measured 3.76:1) but incomplete: the Filament panel bridge redefines --muni-danger-fg to #ff8195 under .dark, where white text measures 2.38:1 — the real worst case. Light is fine at 6.34:1 (#b03030).» (muni-ui-filament.css:131 `    --muni-danger-fg:   #ff8195;` (inside the `.dark {` block) against button.blade.php:49 `        .muni-btn--danger:hover { background: var(--muni-danger-fg); color: #fff; }`)
- `props[0].notes (variant)`: el lector dijo «"Appends class muni-btn--<variant>: primary\|ghost\|subtle\|danger styled in the @once block."»; el archivo dice «Omits that, unlike $size, $variant has no fallback. $sizes[$size] ?? $sizes['md'] guards the size, but $variant is concatenated raw, so an unknown variant yields a class with no matching rule and the button renders with no background or colour — only the transparent border from $base.» (Line 20 `        .($sizes[$size] ?? $sizes['md']);` versus line 25 `{{ $attributes->merge(['class' => 'muni-btn muni-btn--'.$variant, 'style' => $base]) }}`)

</details>

### `<x-muni::calendar>`

Archivo: `resources/views/components/calendar.blade.php` (62 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `name` | `fecha` | string | name of the hidden input that carries the ISO date; printed with {{ $name }} on line 33 |
| `min` | `null` | string|null | earliest selectable date; interpolated into the x-data JS string as new Date('…') on line 12 — escaped by Blade's e(), but that escape is inert in a JS-string context |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-mono`, `--muni-font-sans`, `--muni-hint`, `--muni-on-accent`, `--muni-radius`, `--muni-radius-sm`, `--muni-ring`, `--muni-shadow`, `--muni-surface`, `--muni-surface-2`, `--muni-text`.
**Alpine:** `x-data`, `x-text`, `x-for`, `x-if`, `:key`, `:value`, `:class`, `@click`. Solo core.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | .muni-cal__day:focus-visible outline, third level of the var() fallback chain in the @once <style> block (line 59) | border | sí | Last-resort var() fallback behind --muni-focus and --muni-accent, both of which have light and dark definitions in muni-ui.css, so it is unreachable when the stylesheet loads; the mid-grey itself measures 4.54:1 on light --muni-surface #ffffff and 3.99:1 on dark --muni-surface #111620, clearing the 3:1 UI minimum on both themes. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-cal { background: var(--muni-surface) }`; `.muni-cal { border: 1px solid var(--muni-border) }`; `.muni-cal { box-shadow: var(--muni-shadow) }`; `.muni-cal__nav { border: 1px solid var(--muni-border) }`; `.muni-cal__nav { background: var(--muni-surface) }`; `.muni-cal__nav { color: var(--muni-text) }`; `.muni-cal__nav:hover { border-color: var(--muni-accent) }`; `.muni-cal__nav:hover { color: var(--muni-accent) }`; `.muni-cal__dow { color: var(--muni-hint) }`; `.muni-cal__day { background: transparent }`; `.muni-cal__day { color: var(--muni-text) }`; `.muni-cal__day:hover { background: var(--muni-surface-2) }`; `.muni-cal__day:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-cal__day:focus-visible { box-shadow: var(--muni-ring) }`; `.muni-cal__day--on { background: var(--muni-accent) }`; `.muni-cal__day--on { color: var(--muni-on-accent) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `aria-label="Mes anterior"`, `aria-label="Mes siguiente"` · teclado: Tab (native buttons), Enter/Space (native button activation) · focus-visible: sí · reduced-motion: no · etiqueta: aria-label (nav buttons only; day cells rely on their number text; the hidden input has no label).
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Line 12 interpolates $min into the x-data JS string with {{ }}, so Blade DOES escape it through e() — but e() only turns ' into &#039;, and the browser decodes HTML entities in the attribute value before Alpine parses it, so a crafted value still breaks out of the JS string literal: never pass user data to :min. @once <style> block with no .dark / [data-muni-theme="dark"] rules of its own. No wire: binding — it posts through a hidden input bound with :value="iso(sel)". disabled(d) calls this.min.setHours(0,0,0,0), which mutates the min Date in place and returns a timestamp, so the redundant second clause d < this.min compares against the already-normalised date. No arrow-key grid navigation, no role="grid" and no aria-pressed/aria-selected on the day cells; the selected day is marked only by .muni-cal__day--on (background + font-weight:700, so not colour-only). .muni-cal__nav animates with a literal transition:.15s that ignores prefers-reduced-motion, while .muni-cal__day uses var(--muni-dur), which muni-ui.css zeroes under that media query.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block has no .dark, [data-muni-theme="dark"], [data-theme="dark"] or @media (prefers-color-scheme: dark) rule anywhere; grep -n -E '\.dark\|data-muni-theme\|data-theme\|prefers-' on the file returns nothing. Dark mode comes only from --muni-* token indirection (dark_ok stays true).» (Last rule of the block, line 60: ".muni-cal__day--on { background:var(--muni-accent); color:var(--muni-on-accent); font-weight:700; }" followed immediately by line 61 "</style>" and line 62 "@endonce".)
- `notes (and props[min].notes)`: el lector dijo «"$min is interpolated unescaped into the x-data JS string" / "interpolated raw into JS new Date()"»; el archivo dice «It is interpolated with {{ }}, which DOES escape through Blade's e(). The injection is real but the mechanism is the inverse of the claim: e() converts ' to &#039;, and the browser decodes HTML entities in the attribute value before Alpine parses it, so the escaping is inert in a JS-string context. Calling it 'unescaped'/'raw' misstates what the file does.» (Line 12: "        min: {{ $min ? \"new Date('\".$min.\"')\" : 'null' }},")

</details>

### `<x-muni::card>`

Archivo: `resources/views/components/card.blade.php` (29 líneas) · verificado con 3 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `title` | `null` | string | heading text; presence also triggers the header block |
| `subtitle` | `null` | string | small muted line rendered under the title |
| `flush` | `false` | bool | drops the 18px body padding when true |

**Slots:** `slot` (obligatorio) — card body, wrapped in the padded (or flush) div; `actions` — header right side; checked with isset($actions) and rendered inside @isset($actions)
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-font-sans`, `--muni-muted`, `--muni-radius-lg`, `--muni-shadow`, `--muni-shadow-md`, `--muni-surface`, `--muni-text`.
**Alpine:** no (Blade puro).

**Bloque `<style>` propio:** sí; reglas de color: `.muni-card:hover { box-shadow: var(--muni-shadow-md); }`. Contraparte `.dark`: no aplica.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**README:** documentado · **Tests:** sin prueba
**Notas:** All styling is inline and merged via $attributes->merge(['style' => ...]); Laravel treats `style` as appendable and emits [defaultsValue, value] in that order, so a caller's style string is appended AFTER the defaults and wins on conflicting properties (verified in vendor/laravel/framework/src/Illuminate/View/ComponentAttributeBag.php). BUT the <section> also carries a literal class="muni-card" BEFORE {{ $attributes->merge(...) }}, and `class` is appendable in the same merge(), so <x-muni::card class="..."> renders TWO class attributes (class="muni-card" class="..."); HTML keeps the first, so a caller's classes are silently dropped — classes cannot be extended from outside. Header renders only when $title or the $actions slot exists, so a card given only `subtitle` (no title, no actions) never renders the subtitle at all. The <section> gets no accessible name (no aria-labelledby pointing at the h3, no aria-label), so it is not exposed as a region landmark. @once hover rule; the hover transition honours prefers-reduced-motion only indirectly, via --muni-dur being forced to 0ms in muni-ui.css, not by any rule in this file.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «null — the @once <style> block contains a single rule and no `.dark`, `[data-theme="dark"]` or `[data-muni-theme="dark"]` counterpart anywhere in the file; none is needed because --muni-shadow-md is itself theme-aware (defined at muni-ui.css:79 light and :187 dark), but no counterpart rule exists, so `true` is false.» (Lines 27-29 are the entire style block: `@once` / `    <style>.muni-card:hover { box-shadow: var(--muni-shadow-md); }</style>` / `@endonce` — nothing else.)
- `notes`: el lector dijo «"All styling is inline and merged via $attributes->merge(['style' => ...]), so a caller's style string is appended and wins on conflicting properties. Header renders only when $title or the $actions slot exists. The <section> gets no accessible name (no aria-labelledby pointing at the h3), so it is not a useful landmark. @once hover rule."»; el archivo dice «The style-merge claim is right, but the notes omit that the same merge() makes `class` appendable while the element already hardcodes class="muni-card" as a separate literal attribute placed before the merge — so a caller's class is emitted as a SECOND class attribute and dropped by the browser (first attribute wins). Also omitted: `subtitle` is rendered inside the header, which is gated on `$title \|\| isset($actions)`, so a card passed only `subtitle` renders no subtitle at all.» (Lines 7-9: `<section` / `    class="muni-card"` / `    {{ $attributes->merge([` — and line 15: `@if ($title \|\| isset($actions))`, with the subtitle only at line 19 inside that header. ComponentAttributeBag::merge partitions on `$key === 'class' \|\| $key === 'style'`, so a caller `class` is re-emitted by the merge.)
- `style_block.color_rules`: el lector dijo «".muni-card:hover { box-shadow: var(--muni-shadow-md) }"»; el archivo dice «".muni-card:hover { box-shadow: var(--muni-shadow-md); }" — the declaration is terminated with a semicolon in the file; the quote must be verbatim.» (Line 28: `    <style>.muni-card:hover { box-shadow: var(--muni-shadow-md); }</style>`)

</details>

### `<x-muni::chart-bar>`

Archivo: `resources/views/components/chart-bar.blade.php` (50 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `data` | `[]` | array | list of ['label','value','tone'] arrays or plain numbers |
| `height` | `160` | int | plot area height in px, written into an inline style |
| `tone` | `accent` | string | default tone key for bars without their own tone |
| `labels` | `true` | bool | renders the label row underneath the bars |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-danger-fg`, `--muni-ease`, `--muni-font-mono`, `--muni-font-sans`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-fg`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).

**Bloque `<style>` propio:** sí; reglas de color: `.muni-chartbar__val { color: var(--muni-muted) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: none (each bar wrapper carries a native title="label: value"; values and labels are also plain visible text).
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Pure PHP/Blade, no JS. $toneColor maps accent/ok/warn/danger/info to tokens; a per-item 'tone' overrides $tone and an unknown key falls back to var(--muni-accent). Bar gradient is linear-gradient(180deg, $c, color-mix(in srgb, $c 55%, transparent)) — token-based, so theme-safe. Heights are percentages of the local max, not of a fixed scale. Bars transition with a literal .6s (`transition:height .6s var(--muni-ease)`), so the global `--muni-dur: 0ms` under prefers-reduced-motion does NOT shorten it and the component has no reduced-motion rule of its own. The only hit of the literal-colour grep is `white-space:nowrap` on line 39, a property name, not a colour.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the `@once` `<style>` block (lines 45-50) contains exactly two rules, `.muni-chartbar__bar` and `.muni-chartbar__val`, and there is no `.dark`, `[data-theme="dark"]`, `[data-muni-theme="dark"]` or `@media (prefers-color-scheme: dark)` selector anywhere in the file (`grep -n -E '\.dark\|data-muni-theme\|prefers-color-scheme\|@media' chart-bar.blade.php` returns nothing). None is needed — the single colour declaration uses `var(--muni-muted)`, which has both a light (`#5a6070`) and a dark (`#7a8ba8`) definition in muni-ui.css — so dark_ok stays true, but the counterpart rule does not exist.» (Line 48: `.muni-chartbar__val { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; font-size:11px; font-weight:600; color:var(--muni-muted); margin-bottom:5px; }` — followed directly by line 49 `</style>` and line 50 `@endonce`, with no dark-mode rule in between.)

</details>

### `<x-muni::chart-donut>`

Archivo: `resources/views/components/chart-donut.blade.php` (56 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `segments` | `[]` | array | items of ['label','value'] plus optional 'tone' or raw 'color' |
| `size` | `150` | int | outer box width/height in px for the ring |
| `thickness` | `18` | int | SVG stroke-width in user units, not px |
| `total` | `null` | mixed | denominator; when null the segment values are summed |
| `centerLabel` | `null` | string | text centred inside the ring; null renders nothing |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border-2`, `--muni-danger-fg`, `--muni-ease`, `--muni-font-mono`, `--muni-font-sans`, `--muni-glow`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-fg`, `--muni-surface-3`, `--muni-text`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: none (the SVG has no role, title or aria-label; the legend supplies label and value as visible text).
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Server-rendered SVG, no JS. Arcs are stroke-dasharray/stroke-dashoffset on r=42 circles in a viewBox 0 0 100 100 rotated -90deg, so `thickness` is in user units while `size` is px. $total lets the ring stay partial. Segment 'color' bypasses $toneColor. Passed as center-label in markup. Arcs transition with a literal .7s.

### `<x-muni::command-palette>`

Archivo: `resources/views/components/command-palette.blade.php` (75 líneas) · verificado con 3 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `items` | `[]` | array | Array of ['label'=>, 'url'=>, 'group'=>?, 'hint'=>?]; passed through array_values() and json_encode(..., JSON_UNESCAPED_UNICODE \| JSON_HEX_APOS \| JSON_HEX_QUOT) into $itemsJson, echoed inside x-data. 'hint' is documented in the @php comment but never rendered. |
| `placeholder` | `Buscar o ir a…` | string | Placeholder of the search input, bound via :placeholder="'{{ $placeholder }}'" (Alpine expression, not a plain attribute) |
| `hotkey` | `k` | string | Key compared with $event.key (case-sensitive) combined with Meta/Ctrl to open the palette |

**Slots:** `trigger` — Optional clickable element, guarded by @isset($trigger) on line 31 and wrapped in a <div @click="show()" style="display:inline-flex;">; the palette also opens with Meta/Ctrl+hotkey, so it works without it. There is no {{ $slot }} anywhere in the file: any default slot content passed by the consumer is silently discarded.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-mono`, `--muni-font-sans`, `--muni-glow`, `--muni-muted`, `--muni-radius-lg`, `--muni-radius-sm`, `--muni-shadow-lg`, `--muni-surface`, `--muni-surface-2`, `--muni-text`.
**Alpine:** `x-data`, `@keydown.window`, `@keydown.escape.window`, `@click`, `x-teleport`, `x-show`, `x-cloak`, `x-transition:enter`, `x-transition:enter-start`, `x-transition:enter-end`, `x-transition:leave`, `x-transition:leave-start`, `x-transition:leave-end`, `x-trap.inert.noscroll`, `x-ref`, `x-model`, `@keydown.down.prevent`, `@keydown.up.prevent`, `@keydown.enter.prevent`, `:placeholder`, `x-for`, `:key`, `:href`, `@mouseenter`, `:style`, `x-text`, `x-if`. **Plugins requeridos: focus.**

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `rgba(10,14,20,.5)` | backdrop div inline style, line 35: background:rgba(10,14,20,.5);backdrop-filter:blur(3px) | overlay | sí | Half-opaque near-black scrim over page content; explicitly exempt as an overlay scrim that reads correctly in both themes. |
| `#767676` | @once <style>, line 73: .muni-cmdk__input:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; } | border (focus outline colour, last-resort fallback) | **NO** | Literal grey used as a border/outline colour. It is not a dual-theme token, not currentColor/transparent/inherit, not a --muni-gob-* invariant, not a scrim/shadow, and has no `.dark`/`[data-muni-theme="dark"]` override in this file (the file contains zero occurrences of the string 'dark'). It is only reached if both --muni-focus and --muni-accent are undefined, but the definition classifies the literal itself, not its reachability. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-cmdk__input:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ Every surface, text, border, accent and glow in the markup resolves through --muni-* tokens that muni-ui.css defines in both the light :root block and the dark blocks (@media prefers-color-scheme, [data-muni-theme="dark"], [data-theme="dark"], .dark), so in practice the component renders correctly in both themes and the near-black scrim is a legitimate overlay. The verdict is nevertheless false because the file carries one non-theme-safe literal, #767676 on line 73, with no dark override rule anywhere in the file — the definition forces dark_ok=false for any non-theme-safe literal. Practically the fallback is unreachable while muni-ui.css defines --muni-focus (#0f766e light / #f59e0b dark). Note also that --muni-radius-lg, --muni-radius-sm, --muni-dur and --muni-ease are declared only in the light :root and are not redefined in the dark blocks; they are non-colour tokens, so they do not affect theme safety.
**A11y:** roles/aria: `role="dialog"`, `aria-modal="true"`, `aria-label="Paleta de comandos"` · teclado: Meta+k / Ctrl+k (hotkey prop, default 'k', compared case-sensitively against $event.key), Escape (@keydown.escape.window on the non-teleported root), ArrowDown (@keydown.down.prevent → move(1)), ArrowUp (@keydown.up.prevent → move(-1)), Enter (@keydown.enter.prevent → go()), Tab (trapped by x-trap.inert.noscroll) · focus-visible: sí · reduced-motion: sí · etiqueta: aria-label="Paleta de comandos" on the dialog; the search input has only a placeholder — no <label>, no aria-label, and no combobox semantics (no role="combobox", aria-controls, aria-expanded or aria-activedescendant), so the `active` row is not announced to assistive technology..
**README:** documentado · **Tests:** cubierto
**Notas:** The @once <style> defines .muni-fade/.muni-fade-0/.muni-fade-1 and .muni-pop/.muni-pop-0/.muni-pop-1 — the SAME class names that drawer.blade.php (line 47) and modal.blade.php (lines 79-83) each redefine in their own @once, and modal's values differ: .muni-pop-0 is `scale(.96) translateY(8px)` there vs `scale(.97) translateY(-8px)` here, so on a page rendering both components the last @once block emitted wins and the palette's pop direction flips. tab-panel.blade.php also consumes .muni-fade without defining it. x-teleport="body" moves the dialog out of the component (and out of any Livewire root); the Escape and hotkey listeners survive because they are bound with the .window modifier. The dialog panel (line 37) has enter transitions but NO x-transition:leave, so it vanishes instantly while the backdrop fades out. go() uses window.location.href — a full page reload that bypasses wire:navigate and follows whatever url is in $items; the anchors use :href="item.url || '#'" with no escaping of the url either. $itemsJson is protected against attribute/JS-string breakout by JSON_HEX_APOS|JSON_HEX_QUOT, but $placeholder and $hotkey are interpolated raw inside single-quoted Alpine expressions (:placeholder="'{{ $placeholder }}'" and $event.key==='{{ $hotkey }}'): Blade escapes an apostrophe to &#039;, the HTML parser decodes it back to ' inside the attribute, and the Alpine expression breaks or is injected. The active-row highlight is conveyed only by background:var(--muni-surface-2) with no text/border cue. The 'hint' key documented in the @php comment is never rendered.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the component file contains no dark-mode rule of any kind; `grep -c -E 'dark\|prefers-color-scheme\|prefers-reduced-motion\|data-theme'` over the file returns 0» (The entire @once block is lines 66-74 and its only colour rule is line 73: `.muni-cmdk__input:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; }` — there is no `.dark .muni-cmdk__input`, no `[data-muni-theme="dark"] …` and no @media (prefers-color-scheme: dark) counterpart anywhere in the file.)
- `dark_ok`: el lector dijo «true»; el archivo dice «false — the record's own hardcoded_colors entry marks #767676 as theme_safe:false, and the rule is that a component with any non-theme-safe literal is dark_ok=false; the reader's verdict contradicts its own colour classification» (Line 73: `.muni-cmdk__input:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; }` — a literal grey used as an outline (border) colour, with no dark override rule in the same file. (Reachability is irrelevant to the classification; the practical mitigation is that muni-ui.css defines --muni-focus in both themes: `--muni-focus: #0f766e;` line 96 and `--muni-focus: #f59e0b;` lines 146/193.))
- `notes`: el lector dijo «"@once <style> defines .muni-fade/.muni-pop, the same class names drawer redefines in its own @once."»; el archivo dice «modal.blade.php also redefines those class names in its own @once, and with DIFFERENT values — so the collision is not only a duplicate but a behavioural conflict; tab-panel.blade.php consumes .muni-fade without defining it. The notes also omit that the dialog panel has no x-transition:leave and that $hotkey is interpolated into a single-quoted Alpine expression exactly like $placeholder.» (command-palette.blade.php line 69: `.muni-pop-0{opacity:0;transform:scale(.97) translateY(-8px)}` vs modal.blade.php line 82: `.muni-pop-0 { opacity:0;transform:scale(.96) translateY(8px); }`; command-palette line 37 carries only `x-transition:enter="muni-pop" x-transition:enter-start="muni-pop-0" x-transition:enter-end="muni-pop-1"` with no leave triplet; line 27: `@keydown.window="if(((\$event.metaKey\|\|\$event.ctrlKey) && \$event.key==='{{ $hotkey }}')){ \$event.preventDefault(); show(); }"`.)

</details>

### `<x-muni::dashboard-shell>`

Archivo: `resources/views/components/dashboard-shell.blade.php` (58 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `theme` | `light` | string | Written into <html data-muni-theme="{{ $theme }}">; "light" freezes the light palette (muni-ui.css rule 3 plus the :not([data-muni-theme="light"]) guard on the prefers-color-scheme block) |
| `title` | `null` | string | <title>{{ $title ?? $system }}</title>; falls back to $system when null |
| `system` | `Panel` | string | System name shown in the topbar (13px/700) and used as the title fallback |
| `subtitle` | `null` | string | Optional second line under the system name, 11px in color:var(--muni-muted); rendered only under @if ($subtitle) |
| `status` | `online` | string | online\|degraded\|offline; maps to badge tone ok\|warn\|danger and to the Spanish label En línea\|Degradado\|Caído, falling back to the raw $status for any other value |
| `user` | `null` | string | When truthy, renders <x-muni::avatar :name="$user" size="md" /> |

**Slots:** `slot` (obligatorio) — Page content rendered inside <main class="muni-ds__main">; the shell is an empty layout without it.; `head` — Extra <head> markup ({{ $head ?? '' }}), for stylesheets and Vite tags; emitted after <title>.; `sidebar` — First child of <body> ({{ $sidebar ?? '' }}), normally <x-muni::sidebar>.; `topbar` — Extra topbar controls ({{ $topbar ?? '' }}), inserted after the flex spacer and before the status badge.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-bg`, `--muni-border`, `--muni-font-sans`, `--muni-muted`, `--muni-radius-sm`, `--muni-surface`, `--muni-text`, `--muni-topbar-h`.
**Alpine:** `@click`. Solo core. Eventos: `muni-sidebar`.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `rgba(10,14,20,.5)` | <style> rule .muni-ds__scrim { … background:rgba(10,14,20,.5); }, line 29 | overlay | sí | Mobile scrim behind the sidebar; a half-opaque near-black veil darkens the content behind it correctly in both themes. Dead CSS in this file, though: no element carrying class "muni-ds__scrim" is ever emitted here. |

**Bloque `<style>` propio:** sí; reglas de color: `body { background: var(--muni-bg) }`; `body { color: var(--muni-text) }`; `.muni-ds__top { background: var(--muni-surface) }`; `.muni-ds__top { border-bottom: 1px solid var(--muni-border) }`; `.muni-ds__burger { background: transparent }`; `.muni-ds__burger { color: var(--muni-text) }`; `.muni-ds__scrim { background: rgba(10,14,20,.5) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `banner (implicit, <header class="muni-ds__top"> — not nested in a sectioning element)`, `main (implicit, <main class="muni-ds__main">)`, `button (implicit, <button class="muni-ds__burger">)` · teclado: Tab reaches the burger <button>; Enter/Space fire its native click — but the @click handler is inert (see notes), so nothing happens · focus-visible: no · reduced-motion: sí · etiqueta: lang="es" on <html>; aria-label="Menú" on the burger button. No explicit role= attribute anywhere in the file, no aria-label on the header/main landmarks, and the burger's inline <svg> has no aria-hidden="true" (harmless in practice, the button already carries an accessible name)..
**Depende de:** x-muni::reverb-meta; x-muni::badge; x-muni::avatar.
**README:** documentado · **Tests:** cubierto
**Notas:** Full HTML document (DOCTYPE/html/head/body), not an inline component; its <style> is not wrapped in @once (unnecessary here, the document is rendered once). <x-muni::reverb-meta /> is deliberately placed before <title> — the Blade comment on lines 15-17 explains that inside <title> the markup is RCDATA, so the <meta> would render as visible text and echo.js would lose its key. Gotcha: the burger's @click="window.dispatchEvent(new CustomEvent('muni-sidebar'))" sits outside any x-data (the file contains no x-data at all), so Alpine never binds it and the mobile menu never opens. .muni-ds__scrim / .muni-ds__scrim.on are styled but never emitted here. The burger has no type="button" (irrelevant outside a form). No animation, transition or prefers-reduced-motion rule in the file; nothing sets outline:none either, so the UA default focus ring survives even though the component defines no focus style of its own.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the <style> block contains no dark-mode counterpart rule; the file has zero .dark, [data-theme="dark"], [data-muni-theme="dark"] or prefers-color-scheme selectors» (grep -n -E '\.dark\|data-muni-theme\|data-theme\|prefers-color-scheme' returns exactly one hit, and it is an attribute on <html>, not a rule: line 11 `<html lang="es" data-muni-theme="{{ $theme }}">`. Every rule in the <style> block (lines 21-31) is theme-agnostic: `body{ margin:0; min-height:100vh; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); display:flex; }`. dark_ok stays true regardless — a component whose only colours are dual-theme tokens plus a scrim needs no .dark rule — but the field records the rule's existence, and it does not exist.)
- `a11y.roles`: el lector dijo «["aria-label=\"Menú\""]»; el archivo dice «No `role=` attribute exists anywhere in the file; aria-label is a label, not a role. The roles actually exposed are the implicit ones: banner (<header class="muni-ds__top">), main (<main class="muni-ds__main">), button (<button class="muni-ds__burger">). The reader also omitted lang="es" from the a11y record.» (grep -n -E 'aria-\|role=\|tabindex\|lang=' returns only two lines — line 11 `<html lang="es" data-muni-theme="{{ $theme }}">` and line 38 `<button class="muni-ds__burger" @click="window.dispatchEvent(new CustomEvent('muni-sidebar'))" aria-label="Menú">` — with no `role=` match at all. The landmarks come from the bare elements on lines 37 (`<header class="muni-ds__top">`) and 53 (`<main class="muni-ds__main">`).)

</details>

### `<x-muni::data-table>`

Archivo: `resources/views/components/data-table.blade.php` (42 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `columns` | `[]` | array | Header labels rendered as <th>. `@if (! empty($columns))` guards the whole <thead>, so with the default no header row exists; also drives the colspan of the empty-state cell via max(count($columns), 1). |
| `empty` | `Sin resultados para este filtro.` | string | Message printed (escaped) in the centred fallback <td> when trim($slot) === ''. |

**Slots:** `slot` — The <tr> rows of <tbody>; when trim($slot) is empty the component renders a centred empty-state row spanning max(count($columns),1) columns.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-danger-fg`, `--muni-font-mono`, `--muni-font-sans`, `--muni-muted`, `--muni-radius`, `--muni-surface`, `--muni-surface-2`, `--muni-text`.
**Alpine:** no (Blade puro).

**Bloque `<style>` propio:** sí; reglas de color: `.muni-data-body td, [data-muni-row] td { border-bottom: 1px solid var(--muni-border) }`; `.muni-data-body td, [data-muni-row] td { color: var(--muni-text) }`; `[data-muni-row]:hover { background: var(--muni-surface-2) }`; `[data-muni-row].muni-row--danger td:first-child { box-shadow: inset 3px 0 0 var(--muni-danger-fg) }`; `[data-muni-row].muni-row--danger td:first-child { color: var(--muni-danger-fg) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**Firma del sistema:** `data-muni-row`, `.muni-num`.
**README:** documentado · **Tests:** sin prueba
**Notas:** The wrapper never emits data-muni-row: the consumer must put it on each <tr>, and .muni-row--danger on top for the left danger stripe. .muni-data-body is styled but never emitted by this file. $attributes->merge() lands on the inner <table>, not on the overflow-x:auto wrapper <div>, so consumer classes/ids/wire: attributes cannot reach the scroll container. <th> has no scope="col", the <table> has no <caption> or aria-label, and the empty-state row carries no status/live semantics. The overflow-x:auto wrapper has no tabindex="0", so the horizontal scroll is not keyboard-reachable. @once <style> block; no transition, no animation, no prefers-reduced-motion rule (nothing animates).

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block contains no dark-mode counterpart at all: no `.dark`, `[data-theme="dark"]`, `[data-muni-theme="dark"]` selector and no `@media (prefers-color-scheme: dark)` anywhere in the file. Dark mode works purely because every colour is a token; the block itself has no counterpart. (dark_ok stays true, per the rule that a component whose only colours are theme-safe tokens is dark_ok even with no .dark rule of its own.)» (The whole style block is lines 34-41 and its colour rules are token-only, e.g. line 36: `[data-muni-row]:hover { background: var(--muni-surface-2); }` and line 39: `[data-muni-row].muni-row--danger td:first-child { color: var(--muni-danger-fg); font-weight: 600; }`. `grep -n -E '\.dark\|data-muni-theme\|data-theme\|prefers-' resources/views/components/data-table.blade.php` returns no match (its only hits in the file are @once/@endonce/.muni-data-body/.muni-num from the same combined grep).)

</details>

### `<x-muni::drawer>`

Archivo: `resources/views/components/drawer.blade.php` (51 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `title` | `null` | string | Escaped into the <h2> that aria-labelledby points at; when null the dialog's accessible name is empty |
| `side` | `right` | string | $isRight = $side !== 'left'; anything but 'left' means right — picks the pinned edge, the border side and the slide direction classes |
| `width` | `400px` | string | Interpolated raw into the inline style as width:{{ $width }}, capped by max-width:92vw |

**Slots:** `slot` (obligatorio) — Drawer body, rendered in the scrollable padded area between header and footer; the panel is empty without it.; `trigger` — @isset($trigger): wrapped in a display:inline-flex div with @click="open=true"; without it nothing inside the component can open the drawer.; `footer` — @isset($footer): optional <footer> on var(--muni-surface-2) with a top border and right-aligned actions.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-font-sans`, `--muni-muted`, `--muni-radius-sm`, `--muni-shadow-lg`, `--muni-surface`, `--muni-surface-2`, `--muni-surface-3`, `--muni-text`.
**Alpine:** `x-data`, `@keydown.escape.window`, `@click`, `x-teleport`, `x-show`, `x-cloak`, `x-transition:enter`, `x-transition:enter-start`, `x-transition:enter-end`, `x-transition:leave`, `x-transition:leave-start`, `x-transition:leave-end`, `x-trap.inert.noscroll`. **Plugins requeridos: focus.**

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `rgba(10,14,20,.5)` | backdrop div inline style, line 24: background:rgba(10,14,20,.5);backdrop-filter:blur(2px) | overlay | sí | Half-opaque near-black scrim behind the panel; it is an overlay scrim that reads correctly on both themes. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-drawer__x { background: transparent }`; `.muni-drawer__x { color: var(--muni-muted) }`; `.muni-drawer__x:hover { background: var(--muni-surface-3) }`; `.muni-drawer__x:hover { color: var(--muni-text) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `role="dialog"`, `aria-modal="true"`, `aria-labelledby="muni-drawer-title-<uniqid>"`, `aria-label="Cerrar"` · teclado: Escape, Tab (trap) · focus-visible: no · reduced-motion: no · etiqueta: aria-labelledby on the dialog pointing at the <h2>; aria-label="Cerrar" on the close button.
**README:** **sin fila** · **Tests:** cubierto
**Notas:** $tituloId uses uniqid(), so the id changes on every render (cache / Livewire-diff hazard), and with title=null aria-labelledby resolves to an empty accessible name. .muni-drawer transitions transform with a literal .28s, so the slide ignores prefers-reduced-motion, while .muni-fade uses --muni-dur, which muni-ui.css zeroes under @media (prefers-reduced-motion: reduce). .muni-fade / .muni-fade-0 / .muni-fade-1 are redefined identically in modal.blade.php (lines 79-80) AND command-palette.blade.php (line 67), each inside its own @once, so all three copies can ship on the same page. $width is interpolated into the inline style attribute, so a caller-controlled value can smuggle extra CSS declarations. x-teleport="body" moves the panel out of any Livewire root. The trigger wrapper is a plain <div @click="open=true"> with no role or tabindex, so it is keyboard-operable only when the slot content is itself a focusable control. {{ $attributes }} is emitted raw (no ->merge()/->class()). @keydown.escape.window is registered on window, so every drawer instance on the page reacts to Escape. .muni-drawer__x sets border:none but not outline:none, so the UA default focus ring survives even though the component authors no :focus-visible style.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block (lines 44-50) contains no .dark, [data-theme="dark"] or [data-muni-theme="dark"] rule; grep -i for 'focus\|outline\|prefers-reduced\|\.dark\|data-muni-theme\|data-theme' over the file matches only line 12, a comment mentioning the Alpine 'Focus' plugin. The component is dark-correct purely through dual-defined tokens, not through a dark counterpart rule.» (Line 46 is the last colour rule in the block and is theme-agnostic: ".muni-drawer__x:hover { background:var(--muni-surface-3); color:var(--muni-text); }" — nothing after it up to "@endonce" (line 51) scopes a dark override.)
- `notes`: el lector dijo «".muni-fade is also defined in command-palette's @once."»; el archivo dice «Incomplete: .muni-fade is redefined in TWO other components' @once blocks — modal.blade.php:79-80 as well as command-palette.blade.php:67 — so three identical definitions can be emitted on one page (each @once has its own key).» (modal.blade.php:79: ".muni-fade { transition:opacity var(--muni-dur) var(--muni-ease); }" and modal.blade.php:80: ".muni-fade-0 { opacity:0; } .muni-fade-1 { opacity:1; }")

</details>

### `<x-muni::dropdown>`

Archivo: `resources/views/components/dropdown.blade.php` (42 líneas) · verificado con 3 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `align` | `end` | string | Line 7: $origin = $align === 'start' ? 'left:0;' : 'right:0;' — 'start' anchors the panel left:0, any other value right:0; the same comparison on line 29 sets transform-origin to 'top left' / 'top right'. |
| `width` | `220px` | string | Raw CSS length interpolated into min-width:{$width} of the panel's style string (line 26); never validated. |

**Slots:** `trigger` (obligatorio) — The clickable element that toggles the menu; rendered on line 14 as {{ $trigger }} with no @isset/isset() guard, so omitting it throws an undefined-variable error.; `slot` — Default slot rendered on line 32 as {{ $slot }}: the menu items, expected to be <x-muni::dropdown-item> elements inside role="menu". Not guarded, but Blade always defines $slot for an anonymous component, so omitting it renders an empty (useless) menu rather than erroring.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-radius`, `--muni-shadow-lg`, `--muni-surface`.
**Alpine:** `x-data`, `@keydown.escape.window`, `@click`, `:aria-expanded`, `x-show`, `x-cloak`, `@click.outside`, `x-transition:enter`, `x-transition:enter-start`, `x-transition:enter-end`. Solo core.

**Bloque `<style>` propio:** sí; reglas de color: ninguna. Contraparte `.dark`: no aplica.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `aria-haspopup="menu"`, `:aria-expanded="open"`, `role="menu"` · teclado: Escape · focus-visible: no · reduced-motion: sí · etiqueta: none — no aria-label/aria-labelledby/aria-controls in the file; the trigger's accessible name comes entirely from consumer content and role="menu" is unnamed.
**Depende de:** `<x-muni::dropdown-item>`.
**README:** documentado · **Tests:** sin prueba
**Notas:** @once <style> (lines 37-41) defines .muni-dd-enter / -start / -end with only transition/opacity/transform — no colour rules, so no .dark counterpart exists or is needed. Only ENTER transition classes exist (no x-transition:leave), so closing is instant. reduced_motion holds only indirectly: both durations are var(--muni-dur), which muni-ui.css collapses to 0ms under @media (prefers-reduced-motion: reduce) (lines 244-246); the component has no media query of its own. The trigger wrapper is a plain <div @click> with aria-haspopup/:aria-expanded but no role="button", no tabindex and no aria-controls, so keyboard activation and the accessible name depend entirely on what the consumer puts in $trigger. role="menu" without menuitem roving tabindex / ArrowUp-Down handling is an incomplete ARIA menu; Escape closes it but focus is not returned to the trigger and there is no x-trap, so the menu is not focus-contained (fails the ecosystem rule "todo lo que se abre atrapa el foco"). @keydown.escape.window is bound per instance on window, so one Escape closes every mounted dropdown. x-cloak depends on `[x-cloak] { display: none !important; }` at muni-ui.css line 23. No x-teleport: inside an overflow:hidden ancestor the absolutely-positioned panel clips. $attributes->merge() treats `style` as appendable (ComponentAttributeBag::merge partitions on $key === 'class' || $key === 'style'), so a consumer's own style is appended after these defaults and wins on conflicting properties (position, z-index, min-width). $width and the $align-derived $origin are interpolated raw into that style string; merge() HTML-escapes the value so it cannot break out of the attribute, but an untrusted $width can still inject extra CSS declarations — pass only trusted lengths.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «null — the @once <style> contains no colour declaration at all and the file contains no `.dark`, `[data-theme="dark"]` or `[data-muni-theme="dark"]` rule; `grep -i -E 'dark\|data-theme\|prefers\|media'` over the file returns nothing (exit 1). A dark counterpart neither exists nor is needed.» (Line 38: `.muni-dd-enter { transition: opacity var(--muni-dur) var(--muni-ease), transform var(--muni-dur) var(--muni-ease); }` — lines 39-40 declare only opacity/transform; there is no fourth rule.)
- `slots[1].required (default slot)`: el lector dijo «true — "Default slot: the menu items…" marked required»; el archivo dice «false — unlike $trigger, the default slot cannot throw: Blade always defines $slot for an anonymous component, so omitting the content renders an empty <div role="menu"> with the padding/border still applied. Only $trigger is hard-required (line 14, unguarded).» (Line 32: `        {{ $slot }}` (no @isset guard, and no undefined-variable risk, in contrast to line 14 `        {{ $trigger }}`).)
- `depends_on`: el lector dijo «[]»; el archivo dice «["dropdown-item"] — the component's own header comment names the companion component that the default slot is meant to contain, and resources/views/components/dropdown-item.blade.php exists in the package; role="menu" here is only valid when its children carry role="menuitem", which this file never emits.» (Lines 10-11: `{{-- Menú desplegable (Alpine 3). El slot `trigger` es el botón; el slot por defecto son\n     los ítems (usar <x-muni::dropdown-item>). Cierra al hacer click fuera o con Escape. --}}`)

</details>

### `<x-muni::dropdown-item>`

Archivo: `resources/views/components/dropdown-item.blade.php` (35 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `href` | `null` | string | If set renders <a href>, otherwise <button type="button"> |
| `icon` | `null` | string | Raw SVG/HTML printed unescaped with {!! !!} inside an aria-hidden span |
| `tone` | `default` | string | 'danger' switches text colour to var(--muni-danger-fg); any other value is default (var(--muni-text)) |

**Slots:** `slot` (obligatorio) — Default slot: the item label text, printed at line 25 with no @isset/isset() guard; without it the item renders as an empty clickable row.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-danger-fg`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-radius-sm`, `--muni-ring`, `--muni-surface-2`, `--muni-text`.
**Alpine:** `@click`. Solo core.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | .muni-dd-item:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: -2px; box-shadow: var(--muni-ring); } | border | sí | Unreachable last-resort fallback: --muni-focus is defined in the light :root (#0f766e), in the prefers-color-scheme media block, under [data-muni-theme="dark"], [data-theme="dark"], .dark (#f59e0b) and under [data-muni-theme="light"], so the var() never falls through; and #767676 itself clears 3:1 against both white (4.54:1) and black (4.63:1). |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-dd-item:hover { background: var(--muni-surface-2); }`; `.muni-dd-item:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: -2px; box-shadow: var(--muni-ring); }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `role="menuitem"`, `aria-hidden="true"` · teclado: ninguno · focus-visible: sí · reduced-motion: sí · etiqueta: none.
**Depende de:** `<x-muni::dropdown>`.
**README:** documentado · **Tests:** sin prueba
**Notas:** @once <style> holds the hover, focus-visible and svg-sizing rules. Dynamic tag via <{{ $tag }}> — Blade compiles it, but editors/linters may flag it. `@click="open = false"` (line 15) reads the PARENT dropdown's Alpine scope, declared in dropdown.blade.php line 12 as `<div x-data="{ open: false }" ...>`; used outside <x-muni::dropdown> Alpine throws "open is not defined", so `dropdown` is a hard runtime dependency. `icon` is printed with {!! !!} — never pass user input. In-file comment (line 31): the outline is the real focus indicator because Filament kills the box-shadow ring. Reduced motion is honoured indirectly: the only transition uses var(--muni-dur), which muni-ui.css sets to 0ms under @media (prefers-reduced-motion: reduce). `role="menuitem"`, `href`/`type` are emitted BEFORE $attributes->merge(), so a consumer passing `role` produces a duplicate attribute and the hardcoded one wins. The file binds no keys: role="menuitem" inside the parent's role="menu" gets no arrow-key roving-tabindex navigation from either this component or dropdown.blade.php (which only handles @keydown.escape.window and @click.outside).

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block (lines 28-35) contains no `.dark`, `[data-muni-theme="dark"]`, `[data-theme="dark"]` or `@media (prefers-color-scheme: dark)` counterpart; none is needed, since every colour in it is a token defined in both themes (this does not change dark_ok, which stays true)» (The entire style block is three rules: `.muni-dd-item:hover { background: var(--muni-surface-2); }` (line 30), `.muni-dd-item:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: -2px; box-shadow: var(--muni-ring); }` (line 32) and `.muni-dd-item svg { width: 15px; height: 15px; }` (line 33). `grep -n -i -E 'dark\|prefers-\|data-muni-theme\|data-theme'` over the file returns zero matches.)
- `depends_on`: el lector dijo «[]»; el archivo dice «["dropdown"] — the component's own @click reads a variable that only the dropdown component declares, which the reader's `notes` already describe but did not record in this field» (dropdown-item.blade.php line 15: `@click="open = false"`; dropdown.blade.php line 12: `<div x-data="{ open: false }" @keydown.escape.window="open = false" style="position:relative;display:inline-block;">`, and dropdown.blade.php line 11 states «los ítems (usar <x-muni::dropdown-item>)».)

</details>

### `<x-muni::empty-state>`

Archivo: `resources/views/components/empty-state.blade.php` (16 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `title` | `Sin resultados` | string | Bold headline text, escaped |
| `description` | `null` | string | Optional paragraph, max-width 44ch, only rendered when truthy |
| `icon` | `null` | string | Raw SVG printed unescaped; falls back to a built-in magnifier glyph |

**Slots:** `actions` — Named slot rendered via @isset($actions) in a flex row below the text; typically buttons.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-font-sans`, `--muni-muted`, `--muni-surface-2`, `--muni-text`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `aria-hidden="true"` · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**README:** documentado · **Tests:** sin prueba
**Notas:** GOTCHA: {{ $slot }} is NEVER rendered — anything passed as default slot content is silently dropped; use the `actions` slot or the `description` prop. Fallback SVG is inline with stroke="currentColor" and inherits var(--muni-muted). `icon` is printed with {!! !!} — never pass user input. No <style>, no Alpine, no motion; purely static markup.

### `<x-muni::error-page>`

Archivo: `resources/views/components/error-page.blade.php` (42 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `theme` | `dark` | string | Interpolated into data-muni-theme on <html> (line 11); with the default 'dark' it matches the [data-muni-theme="dark"] selector of muni-ui.css and freezes the whole document dark |
| `code` | `404` | string | Big mono numeral (line 29, var(--muni-accent) + text-shadow:var(--muni-glow)) and first half of the document <title> (line 15) |
| `title` | `Página no encontrada` | string | <h1> text (line 30) and second half of the document <title> (line 15) |
| `message` | `La página que buscas no existe o fue movida.` | string | Explanatory paragraph in var(--muni-muted) (line 31) |
| `home` | `/` | string | Passed as :href to the fallback <x-muni::button> when no actions slot is given (line 36) |
| `system` | `Municipalidad de Graneros` | string | Small mono footer line in var(--muni-hint) (line 39) |

**Slots:** `head` — Rendered as {{ $head ?? '' }} inside <head> (line 16); the ONLY place to inject the muni-ui.css stylesheet, fonts or a CSP nonce. Guarded with ?? (not @isset).; `actions` — Rendered via @isset($actions) (lines 33-37); replaces the default <x-muni::button :href="$home">Volver al inicio</x-muni::button> call-to-action. There is no default $slot in this component.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-bg`, `--muni-font-mono`, `--muni-font-sans`, `--muni-glow`, `--muni-hint`, `--muni-muted`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#000` | line 22 — .muni-err__grid { mask-image:radial-gradient(ellipse 60% 50% at 50% 45%,#000,transparent) } | decorative | sí | Mask colour stop, alpha-only: #000 is fully opaque so it reveals the grid; the colour channel is never painted, so it reads identically in light and dark |

**Bloque `<style>` propio:** sí; reglas de color: `body { background:var(--muni-bg) }`; `body { color:var(--muni-text) }`; `.muni-err__grid { background-image:linear-gradient(color-mix(in srgb,var(--muni-accent) 5%,transparent) 1px,transparent 1px),linear-gradient(90deg,color-mix(in srgb,var(--muni-accent) 5%,transparent) 1px,transparent 1px) }`; `.muni-err__grid { mask-image:radial-gradient(ellipse 60% 50% at 50% 45%,#000,transparent) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**Depende de:** `<x-muni::button>`.
**README:** documentado · **Tests:** sin prueba
**Notas:** Full <!DOCTYPE html> document (line 10), not a fragment: nesting it inside a layout produces invalid HTML. Its <style> is NOT wrapped in @once (correct here, one document per response) and carries no nonce attribute — a strict style-src CSP without 'unsafe-inline' will block it. muni-ui.css is not linked: without a stylesheet injected through the $head slot every var() resolves empty and body loses background, colour and fonts. $attributes is never referenced, so any attribute passed at the call site is silently dropped. A11y caveat read off line 19: `body{ ... overflow:hidden; }` clips anything taller than the viewport — at 400% zoom or on a short screen the actions and footer become unreachable (WCAG 2.2 AA 1.4.10 reflow). reduced_motion=true is vacuous: the component declares no transition, animation or @media (prefers-reduced-motion) at all. focus_visible=false is likewise about this file only — the focus indicator comes from the nested <x-muni::button>.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the <style> block contains no dark counterpart rule; the string "dark" appears exactly once in the whole file, on the @props default, and there is no .dark, [data-muni-theme="dark"], [data-theme="dark"] selector nor any @media at all» (grep -n -i -E 'dark\|@media\|prefers-' on the file returns a single line: "2:    'theme' => 'dark',". The complete <style> block (lines 17-24) is: "*,*::before,*::after{ box-sizing:border-box; }" / "body{ margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); text-align:center; overflow:hidden; }" / ".muni-err__grid{ ... }" / ".muni-err__card{ position:relative; z-index:1; max-width:440px; }" — no dark rule anywhere. Dark correctness comes entirely from the tokens, which is why dark_ok stays true.)
- `notes`: el lector dijo «Notes omit the reflow/zoom consequence of the body rule and present reduced_motion/focus_visible without qualification»; el archivo dice «body sets overflow:hidden, so content taller than the viewport is clipped with no scrollbar — at 400% zoom or on a short viewport the action button and the system footer become unreachable (WCAG 2.2 AA 1.4.10). Also, the file declares no transition/animation and no prefers-reduced-motion query, so a11y.reduced_motion is vacuously true rather than actively handled.» (Line 19: "body{ margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); text-align:center; overflow:hidden; }")

</details>

### `<x-muni::field>`

Archivo: `resources/views/components/field.blade.php` (12 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `label` | `null` | string | Uppercase 11px caption rendered above the slot control; skipped when null. |
| `name` | `null` | string | Declared but never referenced in the markup; sets no for/id. |

**Slots:** `$slot` (obligatorio) — The actual control (input/select). Without it the component renders only a caption inside an empty <label>.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-font-sans`, `--muni-muted`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: implicit <label> wrap around the slot control (no for= attribute; the `name` prop is unused).
**README:** documentado · **Tests:** sin prueba
**Notas:** Twelve lines, no @once <style>, no $attributes spread: any class/id/style the host passes to <x-muni::field> is silently dropped. The `name` prop is dead code — no for/id wiring, labelling depends entirely on the <label> wrapping the slot. Nothing animates, so reduced motion is moot.

### `<x-muni::file-dropzone>`

Archivo: `resources/views/components/file-dropzone.blade.php` (47 líneas) · verificado con 4 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `name` | `file` | string | Input name; gets "[]" appended when multiple is true (`name="{{ $name }}{{ $multiple ? '[]' : '' }}"`). |
| `accept` | `image/*,application/pdf` | string | Value of the input's accept attribute; client-side picker hint only, never validation, and the drop path bypasses it entirely. |
| `label` | `Arrastra un archivo o haz clic para subir` | string | Idle prompt; interpolated into an Alpine x-text JS string literal, so it never appears in the server-rendered HTML. |
| `hint` | `PDF o imagen, hasta 10 MB` | string | Secondary line, server-rendered as real text inside the <label> and hidden by x-show once files are picked; it is the only text present before Alpine boots. |
| `multiple` | `false` | bool | Adds the multiple attribute and the "[]" suffix to the input name; not enforced on the drag-and-drop path. |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-accent-soft`, `--muni-border`, `--muni-border-2`, `--muni-dur`, `--muni-ease`, `--muni-font-mono`, `--muni-font-sans`, `--muni-hint`, `--muni-muted`, `--muni-radius`, `--muni-surface`, `--muni-surface-2`, `--muni-text`.
**Alpine:** `x-data`, `@dragover.prevent`, `@dragleave.prevent`, `@drop.prevent`, `:class`, `x-ref`, `@change`, `x-text`, `x-show`, `x-for`, `:key`. Solo core.

**Bloque `<style>` propio:** sí; reglas de color: `.muni-dz { border: 1.5px dashed var(--muni-border-2) }`; `.muni-dz { background: var(--muni-surface-2) }`; `.muni-dz:hover, .muni-dz--over { border-color: var(--muni-accent) }`; `.muni-dz:hover, .muni-dz--over { background: var(--muni-accent-soft) }`; `.muni-dz__icon { color: var(--muni-accent) }`; `.muni-dz__label { color: var(--muni-text) }`; `.muni-dz__hint { color: var(--muni-hint) }`; `.muni-dz__file { background: var(--muni-surface) }`; `.muni-dz__file { border: 1px solid var(--muni-border) }`; `.muni-dz__file { color: var(--muni-text) }`; `.muni-dz__size { color: var(--muni-muted) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `aria-hidden="true"` · teclado: Tab (focuses the real <input type="file">, which is opacity:0 and absolutely positioned over the whole dropzone), Enter (opens the native file picker), Space (opens the native file picker) · focus-visible: no · reduced-motion: sí · etiqueta: Implicit <label class="muni-dz"> wrapping the file input, so the input's accessible name is computed from the label's text content. Before Alpine boots that content is only the server-rendered `{{ $hint }}` in `.muni-dz__hint` (the `.muni-dz__label` span is empty and filled by x-text) — so the input DOES have an accessible name, but it is the hint, not the label prop. After Alpine boots the name becomes label + hint concatenated; once files are picked the label span is set to '' and the hint is hidden by x-show, so the name silently becomes the file names and sizes. There is no for=/id= pairing and no aria-label, so the prompt text is not controllable independently of the visible copy..
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** @once <style> shared by every instance; the block also defines a bare global `.mono { font-family:var(--muni-font-mono) }` (line 45), a very generic class name that can collide with host styles. Gotchas: (1) `{{ $label }}` is interpolated inside a single-quoted JS string in x-text (line 26) — Blade's `e()` escapes an apostrophe to `&#039;`, the browser decodes it back inside the attribute value, and the Alpine expression breaks with a JS syntax error; (2) the input is hidden with `opacity:0` and has no focus ring and the label has no `:focus-within` rule, so keyboard focus is completely invisible (WCAG 2.4.7 / 2.4.11 failure); (3) `.muni-dz__label` is empty until Alpine boots, so the prompt text is not in the server HTML; (4) `drop()` assigns the dropped FileList straight to `this.$refs.input.files` without honouring `accept` or `multiple`, so files the native picker would have filtered out reach the form — server-side validation and MIME checking are mandatory; (5) `@dragleave.prevent="over=false"` sits on the outer div, so dragging over child elements fires dragleave and makes the highlight flicker; (6) `{{ $attributes }}` is rendered plainly, not via `->merge()`, so a host-supplied `class` lands on the outer wrapper div and never on `.muni-dz`; (7) no `wire:` directive anywhere — Livewire binding is the host's job; (8) no size limit is enforced despite the default hint saying "hasta 10 MB"; the size shown is only `(f.size/1024/1024).toFixed(2)` for display.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block contains no dark rule at all; `grep -n -E '\.dark\|data-muni-theme\|data-theme\|prefers-color-scheme' file-dropzone.blade.php` returns nothing. Every selector in the block is `.muni-dz*` or `.mono`. (dark_ok stays true, because all colours are dual-defined `--muni-*` tokens.)» (Lines 35-46 hold the entire style block, whose last two rules are `.muni-dz__size { font-size:11px; color:var(--muni-muted); }` and `.mono { font-family:var(--muni-font-mono); }` — the block ends at line 46 `</style>` with no `.dark` or `[data-muni-theme="dark"]` selector anywhere.)
- `a11y.labels`: el lector dijo «"implicit <label> wrap around the file input; the visible text lives in a span filled by Alpine x-text, so the server-rendered HTML has no accessible name"»; el archivo dice «The claim of "no accessible name" is wrong. `{{ $hint }}` is server-rendered as real text inside the SAME `<label>` (line 27), so the wrapped input's accessible name is computed from it even before Alpine boots — the name is the hint, not nothing. Line 26's `x-text` span only adds the label prop after Alpine boots, and once files are picked the label span is set to '' while the hint is hidden by x-show, so the accessible name silently becomes the file names/sizes.» (Line 27: `<span class="muni-dz__hint" x-show="!files.length">{{ $hint }}</span>` — inside `<label class="muni-dz" :class="over && 'muni-dz--over'">` opened on line 20 and closed on line 31.)
- `a11y.keyboard`: el lector dijo «["Enter (native file input)", "Space (native file input)"]»; el archivo dice «Incomplete: the entry point is Tab. The input is a real focusable `<input type="file">` rendered with `opacity:0` (not `display:none`, not `hidden`, no `tabindex="-1"`), stretched over the whole dropzone with `position:absolute;inset:0`, so it sits in the tab order — which is exactly why focus_visible=false is a real WCAG 2.4.7 failure rather than a cosmetic one.» (Line 22: `@change="pick($event.target.files)" style="position:absolute;inset:0;opacity:0;cursor:pointer;">`)
- `notes`: el lector dijo «Lists four gotchas: @once style, the apostrophe-in-x-text break, invisible focus, empty label span, no wire:»; el archivo dice «Correct as far as it goes (the apostrophe gotcha is real: Blade `e()` escapes `'` to `&#039;`, the browser decodes it inside the attribute value and the Alpine expression throws), but it misses four material gotchas: (a) `drop()` writes the dropped FileList straight into the input without honouring `accept` or `multiple`, so the client-side filter is fully bypassable and server-side validation is mandatory; (b) `@dragleave.prevent` on the outer div fires on every child boundary, making the `muni-dz--over` highlight flicker; (c) `{{ $attributes }}` is a plain render, not `->merge()`, so a host `class` lands on the wrapper div and never on `.muni-dz`; (d) the @once block ships a bare global `.mono` class into every host page.» (Line 15: `drop(e){ this.over=false; const dt=e.dataTransfer; if(dt && dt.files.length){ this.$refs.input.files = dt.files; this.pick(dt.files); } }` · Line 17: `@dragover.prevent="over=true" @dragleave.prevent="over=false" @drop.prevent="drop($event)"` · Line 18: `{{ $attributes }}` · Line 45: `.mono { font-family:var(--muni-font-mono); }`)

</details>

### `<x-muni::filter-bar>`

Archivo: `resources/views/components/filter-bar.blade.php` (25 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `action` | `null` | string | Form action URL; the attribute is omitted entirely when null. |
| `method` | `get` | string | Form method; GET keeps filters in the URL for xlsx/csv downloads. |

**Slots:** `$slot` (obligatorio) — The filter fields, expected to be <x-muni::field> wrappers; without it only the Aplicar button renders.; `$submitLabel` — Overrides the submit button caption; falls back to the literal 'Aplicar'.; `$actions` — Extra buttons rendered to the right of the submit button; defaults to an empty string.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-font-sans`, `--muni-on-accent`, `--muni-radius`, `--muni-radius-sm`, `--muni-surface-2`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: Enter (native form submit) · focus-visible: no · reduced-motion: sí · etiqueta: none (the form has no aria-label; each field labels itself).
**README:** documentado · **Tests:** sin prueba
**Notas:** $attributes->merge() carries a `style` default; on Illuminate 12/13 style is an appendable attribute, so a host-passed style is concatenated after and wins. No @once block, no CSRF field: a host passing method="post" gets a 419 unless it adds @csrf into the slot. $submitLabel/$actions are optional named slots via ??.

### `<x-muni::gob-bar>`

Archivo: `resources/views/components/gob-bar.blade.php` (41 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `system` | `null` | string | Name of the current system, rendered after an aria-hidden "/" separator. The guard is `@if ($system)` (truthiness), so it is omitted for ANY falsy value — null, '', '0', 0, false — not only null. |
| `home` | `https://www.municipalidadgraneros.cl/` | string | Href of both the brand link and the back link. Printed with Blade's escaped echo `{{ $home }}`, so it is HTML-escaped (no attribute-breakout injection); it is NOT scheme-validated, so a host passing `javascript:` would still produce a live javascript: href. |
| `sticky` | `false` | bool | Adds .muni-gob-bar--sticky (position:sticky; top:0; z-index:70). |

**Slots:** `$slot` — Optional extra content placed between the flex spacer (.muni-gob-bar__spacer) and the 'Ir al sitio municipal' back link (e.g. a user menu). Printed unguarded — no @isset/isset() check.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-dur`, `--muni-ease`, `--muni-font-sans`, `--muni-gob-petroleo-dark`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#e8f1f2` | line 29 — .muni-gob-bar { … color:#e8f1f2; … } (@once style block) | text | **NO** | Untokenised literal hex used as foreground; no `.dark` / `[data-muni-theme="dark"]` counterpart rule anywhere in the file. It only reads correctly because the band background is the institutional invariant var(--muni-gob-petroleo-dark) (#00404c, defined once in the invariant :root block and never redefined for dark). |
| `#e8f1f2` | line 37 — .muni-gob-bar__back { … color:#e8f1f2; … } (@once style block) | text | **NO** | Same literal repeated for the back link; same absence of any dark override rule. Not theme-safe by definition even though it is visually stable on the invariant band. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-gob-bar { background: var(--muni-gob-petroleo-dark) }`; `.muni-gob-bar { color: #e8f1f2 }`; `.muni-gob-bar__brand { color: inherit }`; `.muni-gob-bar__back { color: #e8f1f2 }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ Nothing visibly breaks: #e8f1f2 sits on --muni-gob-petroleo-dark (#00404c), an institutional invariant with a single definition in the :root invariant block and no dark redefinition, so the band and its foreground are identical in both themes. But the foreground is an untokenised literal with no dark override rule, which fails the theme-safe definition: a host restyling the band would strand the text at that fixed near-white. Everything else in the block is theme-safe (--muni-font-sans has light and dark definitions; --muni-dur/--muni-ease are non-colour tokens).
**A11y:** roles/aria: `aria-hidden="true"` · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none — no aria-label/aria-labelledby anywhere; both links are labelled by their own visible text ('Municipalidad de Graneros' and 'Ir al sitio municipal ↗'). The escudo carries no text alternative of its own here..
**Depende de:** x-muni::gob-escudo; x-muni::gob-stripe.
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Emits TWO root elements: the bar <div> plus a sibling <x-muni::gob-stripe /> on line 25, outside the element that receives $attributes — wrappers assuming a single root will misplace the stripe, and merged classes/attributes never reach it. The <style> is wrapped in @once, so it is emitted for the first instance only and shared by all later ones (a second instance with different needs cannot restyle itself). The only transition (opacity on .muni-gob-bar__back) uses var(--muni-dur), which muni-ui.css sets to 0ms under prefers-reduced-motion, so motion preference is honoured through the token, not through a local media query. Below 560px .muni-gob-bar__back is display:none (line 39), removing it from the tab order and leaving the brand link (escudo + name) as the only way back to the mother site. `home` is printed with the escaped `{{ }}` echo, so it cannot break out of the attribute, but it is not scheme-checked — hosts must still pass a trusted http(s) URL. No focus styling of its own: the two links fall back to the UA focus ring (the component does not set outline:none, so the ring survives).

<details><summary>Correcciones del verificador</summary>

- `props[home].notes / notes`: el lector dijo «"Href of the brand link and the back link; injected raw into href." and in notes: "`home` is injected raw into href, so hosts must pass a trusted URL."»; el archivo dice «`home` is printed with Blade's ESCAPED echo `{{ $home }}`, not raw output (`{!! !!}`), so it is passed through htmlspecialchars and cannot break out of the attribute. The residual risk is scheme-based (a `javascript:` URL is not filtered), not raw HTML injection.» (line 12: `<a href="{{ $home }}" class="muni-gob-bar__brand">` — and line 22: `<a href="{{ $home }}" class="muni-gob-bar__back">Ir al sitio municipal ↗</a>`)
- `props[system].notes`: el lector dijo «"Name of the current system shown after a \"/\" separator; hidden when null."»; el archivo dice «The guard is a truthiness test, so the separator and the name are omitted for any falsy value (null, '', '0', 0, false), not only for null.» (line 16: `@if ($system)`)

</details>

### `<x-muni::gob-escudo>`

Archivo: `resources/views/components/gob-escudo.blade.php` (19 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `size` | `40` | int | Written verbatim to both the width and height HTML attributes: width="{{ $size }}" height="{{ $size }}". It sets no CSS size. |
| `src` | `null` | string|null | Overrides the published PNG path (CDN, R2, etc.) via $url = $src ?? asset('vendor/muni-ui/logo-graneros.png'). |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** ninguno.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: alt="Escudo de la Municipalidad de Graneros" (hard-coded, not overridable via props; and because the literal alt is emitted on line 17 BEFORE {{ $attributes->merge(...) }} on line 18, an alt passed by the consumer only produces a duplicate attribute that the HTML parser discards — the hard-coded one wins). The same ordering applies to width/height, which are not props..
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Image published with `php artisan vendor:publish --tag=muni-ui-images` into public/vendor/muni-ui/ (confirmed in src/MuniUiServiceProvider.php: __DIR__.'/../resources/images' => public_path('vendor/muni-ui'), tag 'muni-ui-images'; the source file resources/images/logo-graneros.png exists). Line 10: $url = $src ?? asset('vendor/muni-ui/logo-graneros.png'). width/height attributes come from $size so no CLS, but $size does not set CSS size (object-fit:contain only). No loading/decoding attributes. No @once block, no Alpine, no wire:, no <style> block, no PHP class counterpart in src/. It is a leaf: it depends on nothing, and is itself consumed by gob-bar.blade.php and gob-footer.blade.php.

### `<x-muni::gob-footer>`

Archivo: `resources/views/components/gob-footer.blade.php` (66 líneas) · verificado con 3 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `system` | `null` | string | System name shown under the brand (line 19, guarded by `@if ($system)`) and prefixed to the domain in the legal line (line 47: `{{ $system ? $system.' · ' : '' }}`) |
| `home` | `https://www.municipalidadgraneros.cl/` | string | Href of the default 'Sitio municipal' link (line 34); only rendered when the `links` slot is absent |
| `address` | `Av. Bernardo O'Higgins 630, Graneros, Región de O'Higgins` | string | Street address printed in the Contacto column (line 26) |
| `phone` | `+56 72 249 1000` | string | Phone text; whitespace stripped for the tel: href via `preg_replace('/\s+/', '', $phone)` (line 27) — the leading + is preserved |
| `email` | `contacto@municipalidadgraneros.cl` | string | Email text and mailto: href (line 28) |

**Slots:** `links` — Optional named slot rendered as `{{ $links ?? '' }}` (line 32) inside the 'Ecosistema' column; when `isset($links)` the three default links (Sitio municipal, Trámites en línea, Transparencia) are suppressed by the `@unless (isset($links))` on line 33. The default `$slot` variable never appears in the template, so any content passed between the component tags is silently discarded.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-font-mono`, `--muni-font-sans`, `--muni-gob-lima`, `--muni-gob-petroleo-dark`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#d9e6e8` | .muni-gob-footer { color: … } (style block, line 53) | text | **NO** | Literal text colour with no dark counterpart rule; in practice sits on the invariant petróleo band (--muni-gob-petroleo-dark #00404c), 8.94:1 at full opacity |
| `#fff` | .muni-gob-footer__name { color: … } (style block, line 56) | text | **NO** | Literal white for the municipality name; no dark override, relies on the band staying dark |
| `#d9e6e8` | .muni-gob-footer__col a { color: … } (style block, line 61) | text | **NO** | Literal link colour, no dark counterpart; safe only because the footer background is theme-invariant |
| `#fff` | .muni-gob-footer__col a:hover { color: … } (style block, line 62) | text | **NO** | Literal hover colour, no dark counterpart rule in the file |
| `rgba(255,255,255,.12)` | .muni-gob-footer__legal { border-top: 1px solid … } (style block, line 63) | border | **NO** | Literal translucent white hairline used as border, not an overlay/shadow; identical in both themes over the fixed band |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-gob-footer { background: var(--muni-gob-petroleo-dark); color: #d9e6e8 }`; `.muni-gob-footer__name { color: #fff }`; `.muni-gob-footer__col h4 { color: var(--muni-gob-lima) }`; `.muni-gob-footer__col a { color: #d9e6e8 }`; `.muni-gob-footer__col a:hover { color: #fff }`; `.muni-gob-footer__legal { border-top: 1px solid rgba(255,255,255,.12) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ Six literal colour declarations across five style rules have no dark counterpart: #d9e6e8 twice (lines 53 and 61), #fff twice (lines 56 and 62) and rgba(255,255,255,.12) once (line 63) — five of them colour text, one a border. Visually nothing breaks today: the ground is the invariant --muni-gob-petroleo-dark (#00404c), identical in light and dark, so #d9e6e8 reads 8.94:1 and #fff 12.6:1 on it. The risk is structural, not visual: a host that redefines --muni-gob-petroleo-dark (or a future dark value for it) moves the ground while the literals stay put, and contrast collapses with nothing in this file to catch it.
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: none.
**Depende de:** `<x-muni::gob-stripe>`; `<x-muni::gob-escudo>`.
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** @once <style> (lines 51-66) emits the block once per response. Links carry text-decoration:none with an underline only on :hover — there is no :focus or :focus-visible rule anywhere in the file, so keyboard focus falls back to the UA default ring over a dark band (WCAG 2.2 AA 2.4.7 / 2.4.11 risk). CONTRAST DEFECT: `.muni-gob-footer__legal` (line 64) sets `font-size:11.5px; opacity:.6` on inherited #d9e6e8; composited over #00404c that is rgb(130,164,170) ≈ 4.25:1 — below the 4.5:1 required for normal-size text (WCAG 1.4.3). The other dimmed rules pass: `__col p` opacity .85 ≈ 6.9:1, `__sys` opacity .75 ≈ 5.7:1. Two separate `.muni-gob-footer__legal` rules (line 63 border-top, line 64 layout+opacity) both apply. Placeholder `href="#"` on 'Trámites en línea' and 'Transparencia' (lines 35-36) — links with no destination. `<footer>` carries the implicit contentinfo landmark; no explicit role, aria-* or alt attribute exists in the file. The default `$slot` is never echoed, so children passed to the component vanish. `home` is interpolated straight into href (line 34) — a developer-supplied value, not user input, but a javascript: URL would be rendered as-is. No wire:, no Alpine, no transition/animation and no prefers-reduced-motion rule in this file; the two dependencies (gob-stripe, gob-escudo) contain no Alpine either.

<details><summary>Correcciones del verificador</summary>

- `notes`: el lector dijo «Notes list the missing :focus rule, the duplicated .muni-gob-footer__legal selector and the href="#" placeholders, but say nothing about opacity-driven contrast.»; el archivo dice «The legal line dims the inherited #d9e6e8 to 60% at 11.5px, which composites to rgb(130,164,170) over the invariant band #00404c ≈ 4.25:1 — a real WCAG 2.2 AA 1.4.3 failure for normal-size text, and the only actual contrast defect in the component.» (line 64: `.muni-gob-footer__legal { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; max-width:1180px; margin:0 auto; padding:14px clamp(16px,3vw,26px); font-size:11.5px; opacity:.6; }` over line 53 `background:var(--muni-gob-petroleo-dark); color:#d9e6e8`, with `--muni-gob-petroleo-dark: #00404c;` (muni-ui.css line 35))
- `dark_notes`: el lector dijo «"Five literals (#d9e6e8, #fff ×2, rgba white border) have no dark rule."»; el archivo dice «The enumeration adds up to four while the count says five: #d9e6e8 appears TWICE (background rule and link rule), so the correct tally is #d9e6e8 ×2, #fff ×2, rgba(255,255,255,.12) ×1 = five declarations. (The hardcoded_colors array itself is complete and matches the grep; only the prose miscounts.)» (line 53: `.muni-gob-footer { background:var(--muni-gob-petroleo-dark); color:#d9e6e8; …}` and line 61: `.muni-gob-footer__col a { color:#d9e6e8; text-decoration:none; }`)
- `notes`: el lector dijo «Slot behaviour is described only for the named `links` slot; nothing about the default slot.»; el archivo dice «`$slot` never appears in the template, so content passed between `<x-muni::gob-footer>` tags is silently dropped — a caller-visible behaviour worth recording.» (grep for `$slot` over the file returns no match; the only slot variable is line 32 `{{ $links ?? '' }}`)

</details>

### `<x-muni::gob-stripe>`

Archivo: `resources/views/components/gob-stripe.blade.php` (32 líneas) · verificado con 4 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `height` | `5px` | string | Only prop. Interpolated into the merged inline style as "height:{$height};" — the value is HTML-escaped by merge()/e(), but it is not validated as a CSS length, so a host-supplied value can append further declarations. |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-gob-carmin`, `--muni-gob-celeste`, `--muni-gob-gris`, `--muni-gob-lima`, `--muni-gob-naranja`, `--muni-gob-oro`, `--muni-gob-petroleo`.
**Alpine:** no (Blade puro).

**Bloque `<style>` propio:** sí; reglas de color: `.muni-gob-stripe { width: 100%; background: linear-gradient(90deg, var(--muni-gob-lima) 0 14.28%, var(--muni-gob-petroleo) 14.28% 28.57%, var(--muni-gob-oro) 28.57% 42.85%, var(--muni-gob-naranja) 42.85% 57.14%, var(--muni-gob-celeste) 57.14% 71.42%, var(--muni-gob-carmin) 71.42% 85.71%, var(--muni-gob-gris) 85.71% 100%); }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `role="presentation"`, `aria-hidden="true"` · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: none.
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Purely decorative 7-colour institutional band; no slot, no Alpine, no wire:, no dependency on other x-muni:: components. All seven colours are --muni-gob-* invariants (muni-ui.css :root lines 32-40, no dark override by design). In muni-ui-filament.css the bridge re-points six of them to --mg-* inside :root only (lines 69-75); --muni-gob-gris is set to the literal #9c9b9b (line 76, same value as :root), and the .dark block (lines 100-139) does not redefine any --muni-gob-*, so panels inherit the light values — which is the intended invariance. `$height` is interpolated straight into the style string: merge() escapes it for HTML, but nothing constrains it to a CSS length, so a caller passing e.g. '1px;position:fixed;inset:0' injects extra declarations. Laravel's merge() treats 'style' as appendable, so a caller's style is appended after the default and wins the cascade. role="presentation" and aria-hidden="true" are printed BEFORE {{ $attributes }}, so a caller cannot override them (a duplicate attribute is ignored by the parser).

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once block has a single rule, `.muni-gob-stripe`, and the file contains no dark-scoped selector at all (grep -n -E '\.dark\|data-theme\|data-muni-theme\|prefers-color-scheme' on the file exits 1 with no output).» (Lines 17-32: `@once` / `<style>` / `.muni-gob-stripe { width: 100%; background: linear-gradient(90deg, ... var(--muni-gob-gris) 85.71% 100% ); }` / `</style>` / `@endonce` — no `.dark .muni-gob-stripe` or `[data-muni-theme="dark"] .muni-gob-stripe` rule follows.)
- `dark_notes`: el lector dijo «—»; el archivo dice «There is something to record: the component is dark_ok because its colours are --muni-gob-* invariants declared once on :root, not because it ships a dark rule (it ships none).» (muni-ui.css:29-40 — `usan para que cada subdominio se lea como parte del sitio municipal. */` `:root {` `--muni-gob-lima: #adcd60;` … `--muni-gob-gris: #9c9b9b;` — these are absent from the `@media (prefers-color-scheme: dark)` block (line 111) and from the `[data-muni-theme="dark"], [data-theme="dark"], .dark` block (lines 157-198).)
- `notes`: el lector dijo «"the filament theme re-bridges them from --mg-*" (stated for all seven colours)»; el archivo dice «Only six of the seven are bridged to --mg-* tokens, and only inside `:root`; `--muni-gob-gris` is a hard literal, and the `.dark` block never redefines any --muni-gob-*.» (muni-ui-filament.css:69-76 — `--muni-gob-lima:           var(--mg-lima);` … `--muni-gob-carmin:         var(--mg-carmin);` then `--muni-gob-gris:           #9c9b9b;` (line 76), with the `.dark {` block starting only at line 100 and containing no `--muni-gob-*`.)
- `style_block.color_rules[0]`: el lector dijo «Rule quoted without its `width: 100%;` declaration»; el archivo dice «The rule body is `width: 100%;` followed by the gradient `background`.» (Lines 19-21: `.muni-gob-stripe {` / `width: 100%;` / `background: linear-gradient(90deg,`)

</details>

### `<x-muni::input>`

Archivo: `resources/views/components/input.blade.php` (54 líneas) · verificado con 3 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `label` | `null` | string | Renders a <label for="{{ $id }}"> above the field; when absent the input has no accessible name unless the host passes aria-label/aria-labelledby through the attribute bag |
| `name` | `null` | string | Emitted as name="…" only when truthy; also seeds the deterministic id 'muni-'.$name |
| `type` | `text` | string | Printed straight into type="{{ $type }}", unvalidated |
| `error` | `null` | string | Sets aria-invalid="true", swaps the border to var(--muni-danger-border) and replaces the hint (@if/@elseif) |
| `hint` | `null` | string | Helper text in var(--muni-hint); rendered only when $error is falsy |
| `icon` | `null` | string | Raw HTML/SVG printed with {!! $icon !!} inside an aria-hidden span; also adds padding-left:36px to the input |
| `required` | `false` | bool | Adds the bare required attribute and a var(--muni-danger-fg) asterisk inside the label |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-danger-border`, `--muni-danger-fg`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-hint`, `--muni-muted`, `--muni-radius-sm`, `--muni-ring`, `--muni-surface`, `--muni-surface-2`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | line 51, @once style block: ".muni-input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }" | border (focus outline colour, third-level fallback) | **NO** | Literal hex used as an outline (border-class) colour with no .dark / [data-muni-theme="dark"] override anywhere in the file; it is only a last-resort fallback reachable when neither --muni-focus nor --muni-accent is defined (i.e. muni-ui.css absent), but by the stated definition a literal colour used for a border with no dark rule is not theme-safe |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-input::placeholder { color: var(--muni-hint); }`; `.muni-input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }`; `.muni-input:disabled { background: var(--muni-surface-2); color: var(--muni-muted); cursor: not-allowed; }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ The only offender is the #767676 fallback nested in var(--muni-focus, var(--muni-accent, #767676)) at line 51, reachable only if neither token is defined. Even then it holds up: 4.54:1 on #ffffff (--muni-surface light), 4.20:1 on #f5f6f8 (--muni-bg light) and 3.99:1 on #111620 (--muni-surface dark), all above the 3:1 required for a non-text UI indicator. Every other colour in the component is a --muni-* token defined in both the :root light block and the .dark / [data-muni-theme="dark"] / [data-theme="dark"] block of muni-ui.css (accent, border, danger-border, danger-fg, focus, hint, muted, ring, surface, surface-2, text). No --muni-gob-* invariants are used.
**A11y:** roles/aria: `aria-invalid="true" (line 29, only when $error)`, `aria-hidden="true" (line 22, on the icon span)` · teclado: ninguno · focus-visible: sí · reduced-motion: sí · etiqueta: <label for="{{ $id }}"> (only when the label prop is passed; otherwise the field has no accessible name of its own).
**README:** documentado · **Tests:** sin prueba
**Notas:** Line 11: without `name` the id is 'muni-'.uniqid() — a fresh id on every render, which breaks Livewire DOM diffing and label association across re-renders; always pass name. `id="{{ $id }}"` is printed at line 25 BEFORE {{ $attributes->merge([...]) }} at line 30, and `id` is not one of the @props, so a host that passes id="foo" gets two id attributes on the same <input>; the browser keeps the first (the component's), so the host id is silently discarded and <label for> still points at the generated id. type/name/required do not suffer this because they are declared props and are stripped from the bag. `{!! $icon !!}` at line 22 prints unescaped HTML — XSS if the icon string is host- or user-supplied. The error and hint spans (lines 41/43) have no id and there is no aria-describedby, and the error has no role="alert" or aria-live, so screen readers do not announce it and it is not tied to the field. Focus styling uses :focus, not :focus-visible, so the 3px outline also appears on mouse click. Motion is limited to `transition:border-color/box-shadow var(--muni-dur) var(--muni-ease)`; --muni-dur is forced to 0ms by the @media (prefers-reduced-motion: reduce) block in muni-ui.css, so reduced motion is honoured via the token rather than a rule of its own. The style block is wrapped in @once, so it is emitted a single time per response. No wire:, no Alpine, no $slot, no @aware, no nested <x-muni::…> components.

<details><summary>Correcciones del verificador</summary>

- `style_block.color_rules`: el lector dijo «".muni-input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); border-color: var(--muni-accent); box-shadow: var(--muni-ring) }" and ".muni-input:disabled { background: var(--muni-surface-2); color: var(--muni-muted) }"»; el archivo dice «The :focus rule also declares outline-offset: 2px (between the outline and border-color declarations) and the :disabled rule also declares cursor: not-allowed; both quoted rules are abridged rewrites, not the text in the file» (line 51: ".muni-input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }" — line 52: ".muni-input:disabled { background: var(--muni-surface-2); color: var(--muni-muted); cursor: not-allowed; }")
- `hardcoded_colors[0].where`: el lector dijo «".muni-input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) } (style block)"»; el archivo dice «The quote invents a closing brace immediately after the outline declaration; the actual rule continues with outline-offset, border-color and box-shadow, and it lives on line 51 of the @once block» (line 51: ".muni-input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }")
- `notes`: el lector dijo «Lists the uniqid id, the unescaped {!! $icon !!}, the missing aria-describedby/role=alert and the :focus vs :focus-visible issue, but says nothing about attribute-bag collisions»; el archivo dice «A substantive defect is missing: id is hardcoded before the merged attribute bag and is not a declared prop, so a host-supplied id renders a second id attribute on the same element and is silently ignored (first one wins), leaving <label for> bound to the component-generated id» (line 25: "            id=\"{{ $id }}\"" precedes line 30: "            {{ $attributes->merge([" — and lines 1-9 (@props) declare label, name, type, error, hint, icon, required, but not id)

</details>

### `<x-muni::kpi>`

Archivo: `resources/views/components/kpi.blade.php` (37 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `value` | `REQUIRED` | mixed | Big tabular-nums figure rendered in the accent colour. |
| `label` | `REQUIRED` | string | Uppercase caption under the value. |
| `tone` | `neutral` | string | Key into the $accent map: neutral\|ok\|warn\|danger\|info. |
| `hint` | `null` | string | Optional third line of small hint text. |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-danger-fg`, `--muni-font-mono`, `--muni-font-sans`, `--muni-hint`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-fg`, `--muni-radius`, `--muni-shadow`, `--muni-surface`, `--muni-text`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `aria-hidden="true"` · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**README:** documentado · **Tests:** cubierto
**Notas:** @php map covers 5 tones; `?? 'var(--muni-text)'` catches unknown tones. `value`/`label` are required (no default) — omitting them throws. No $slot at all. Tabular numbers come from an inline `font-variant-numeric`, not from `.muni-num`. Caller `style` is appended after the merged default, so it wins.

### `<x-muni::modal>`

Archivo: `resources/views/components/modal.blade.php` (85 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `title` | `null` | string | Header <h2> text; also the aria-labelledby target. Not escaped-guarded: if null the dialog gets an empty accessible name. |
| `maxWidth` | `480px` | string | Interpolated raw into the panel's inline max-width: "max-width:{$maxWidth};" inside the $attributes->merge style string. |

**Slots:** `trigger` — Guarded by @isset($trigger); wrapped in <div @click="open = true" style="display:inline-flex;">. Without it nothing opens the modal from inside the component. The wrapper is a plain div (no role/tabindex), so keyboard opening depends on the consumer putting a focusable element (e.g. a <button>) inside.; `slot` (obligatorio) — Modal body, rendered in the scrollable padded area (padding:18px;overflow-y:auto;color:var(--muni-text)). Not guarded by @isset.; `footer` — Guarded by @isset($footer): right-aligned action row (justify-content:flex-end) on background:var(--muni-surface-2) with border-top:1px solid var(--muni-border); rendered only when set.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-muted`, `--muni-radius-lg`, `--muni-radius-sm`, `--muni-ring`, `--muni-shadow-lg`, `--muni-surface`, `--muni-surface-2`, `--muni-surface-3`, `--muni-text`.
**Alpine:** `x-data`, `@keydown.escape.window`, `@click`, `x-teleport`, `x-show`, `x-cloak`, `x-trap.inert.noscroll`, `x-transition:enter`, `x-transition:enter-start`, `x-transition:enter-end`, `x-transition:leave`, `x-transition:leave-start`, `x-transition:leave-end`. **Plugins requeridos: focus.**

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `rgba(10,14,20,.55)` | inline style of the backdrop div marked {{-- Fondo --}} (line 37): style="position:absolute;inset:0;background:rgba(10,14,20,.55);backdrop-filter:blur(2px);" | overlay | sí | Overlay scrim: a dark translucent veil, identical in both themes on purpose, reads correctly over both light and dark page content. |
| `#767676` | .muni-modal-x:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); } inside the @once <style> (line 78) | border | sí | Last-resort fallback reached only if BOTH --muni-focus and --muni-accent are undefined (i.e. muni-ui.css not loaded at all). Neutral grey: 4.54:1 over the light surface #ffffff and 3.93:1 over the dark surface #111620, so it clears the 3:1 UI minimum either way. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-modal-x { border: none }`; `.muni-modal-x { background: transparent }`; `.muni-modal-x { color: var(--muni-muted) }`; `.muni-modal-x:hover { background: var(--muni-surface-3) }`; `.muni-modal-x:hover { color: var(--muni-text) }`; `.muni-modal-x:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-modal-x:focus-visible { box-shadow: var(--muni-ring) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `role="dialog"`, `aria-modal="true"`, `aria-labelledby="{{ $tituloId }}"`, `aria-label="Cerrar"` · teclado: Escape, Tab (trap), Shift+Tab (trap) · focus-visible: sí · reduced-motion: sí · etiqueta: aria-labelledby on the dialog points at the <h2 id="{{ $tituloId }}">; the close button carries aria-label="Cerrar"..
**README:** documentado · **Tests:** cubierto
**Notas:** `uniqid()` regenerates $tituloId on every render — unstable across Livewire re-renders. `$attributes` merges onto the PANEL div (line 45), not the root x-data div, so an external x-on/@click landing on the component styles the panel instead of the trigger. Needs Alpine's focus plugin (x-trap) and the `[x-cloak] { display: none !important; }` rule from muni-ui.css (line 23) — without it the dialog flashes before Alpine boots. @once <style> may be skipped if the component is first rendered inside a Livewire update; if that happens the × button loses its hover/focus styling and the muni-fade/muni-pop transitions become instant (no colour breaks). If `title` is null the dialog has an empty accessible name. `$maxWidth` lands raw in the CSS of the merged style attribute. reduced_motion is satisfied indirectly: the component has no prefers-reduced-motion rule of its own, but every transition duration is var(--muni-dur), which muni-ui.css collapses to 0ms under `@media (prefers-reduced-motion: reduce)` (line 244-246). The trigger wrapper is a bare `<div @click>` with no role/tabindex, so opening by keyboard only works if the slot content is itself focusable. `@keydown.escape.window` lives on the root div, outside the teleport, so it is always bound (harmless when closed).

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block (lines 74-84) contains no dark-mode counterpart rule whatsoever: no `.dark …`, no `[data-muni-theme="dark"] …`, no `[data-theme="dark"] …`, no `@media (prefers-color-scheme: dark)`. `grep -n -E '\.dark\|data-muni-theme\|data-theme\|prefers-color-scheme\|@media' modal.blade.php` returns nothing. Dark mode works purely through the --muni-* tokens, which is why dark_ok stays true.» (The whole style block, verbatim: line 75 `.muni-modal-x { display:inline-flex;padding:6px;border:none;background:transparent;color:var(--muni-muted);border-radius:var(--muni-radius-sm);cursor:pointer;transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }` · line 76 `.muni-modal-x:hover { background:var(--muni-surface-3);color:var(--muni-text); }` · line 78 `.muni-modal-x:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }` · lines 79-83 `.muni-fade` / `.muni-fade-0` / `.muni-fade-1` / `.muni-pop` / `.muni-pop-0` / `.muni-pop-1` — none of them scoped to a dark selector.)

</details>

### `<x-muni::nav-item>`

Archivo: `resources/views/components/nav-item.blade.php` (35 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `href` | `#` | string | Anchor destination; defaults to a no-op hash. Printed with {{ }} into href, so HTML is escaped but a `javascript:` URL would survive. |
| `icon` | `null` | string | Raw HTML/SVG printed unescaped with {!! !!} inside the icon span; guarded by a truthy @if ($icon), so '0' and '' are dropped. |
| `active` | `false` | bool | Adds aria-current="page" and the --active modifier class. |
| `badge` | `null` | mixed | Pill counter; rendered whenever not strictly null (0 shows). |

**Slots:** `slot` (obligatorio) — The visible link label, wrapped in a flex:1 span; without it the link has no accessible name (the icon span is aria-hidden). No @isset guard: the span always renders.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-accent-soft`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-mono`, `--muni-font-sans`, `--muni-muted`, `--muni-on-accent`, `--muni-radius-sm`, `--muni-ring`, `--muni-surface-2`, `--muni-surface-3`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | .muni-nav-item:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)) } inside the @once <style> (line 27) | outline (focus indicator) | sí | Last-resort fallback in a var() chain: reached only if BOTH --muni-focus and --muni-accent are undefined, i.e. muni-ui.css is not loaded at all. The grey itself reads on both themes (#767676 is ~4.5:1 on #ffffff and ~4.6:1 on #0b0f14, above the 3:1 required for a non-text indicator). |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-nav-item { color: var(--muni-muted) }`; `.muni-nav-item:hover { background: var(--muni-surface-2) }`; `.muni-nav-item:hover { color: var(--muni-text) }`; `.muni-nav-item:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-nav-item:focus-visible { box-shadow: var(--muni-ring) }`; `.muni-nav-item--active { background: var(--muni-accent-soft) }`; `.muni-nav-item--active { color: var(--muni-accent) }`; `.muni-nav-item__badge { background: var(--muni-surface-3) }`; `.muni-nav-item__badge { color: var(--muni-muted) }`; `.muni-nav-item--active .muni-nav-item__badge { background: var(--muni-accent) }`; `.muni-nav-item--active .muni-nav-item__badge { color: var(--muni-on-accent) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `aria-current="page"`, `aria-hidden="true"` · teclado: Tab (native anchor focus), Enter (native anchor activation) · focus-visible: sí · reduced-motion: sí · etiqueta: none — no aria-label/aria-labelledby/<label>; the accessible name comes from the slot text, and the icon span carries aria-hidden="true" so it contributes nothing..
**README:** documentado · **Tests:** sin prueba
**Notas:** `{!! $icon !!}` prints unescaped — never pass user-controlled HTML: XSS. `$href` is escaped by {{ }} but not scheme-checked: a caller-supplied `javascript:` URL would still execute on click. `$badge !== null` means 0 and '' still render a pill, while `@if ($icon)` is truthy-based, so '0' silently drops the icon — the two guards are inconsistent on purpose or by accident. `merge(['class' => ...])` leaves a trailing space when inactive. focus-visible uses outline-offset:-2px (inset) so it stays visible inside clipped sidebars. Active state is carried by colour AND font-weight (500 → 600) AND aria-current. Reduced motion is honoured indirectly: the only transition uses `var(--muni-dur)`, which muni-ui.css sets to 0ms under `@media (prefers-reduced-motion: reduce)`; the component declares no such media query itself.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block contains no dark-mode counterpart rule at all; dark adaptation comes exclusively from the --muni-* tokens, not from any rule in this file» (`grep -n -E 'dark\|prefers-color-scheme\|data-muni-theme\|data-theme' resources/views/components/nav-item.blade.php` returns no match (exit 1). The style block runs from line 21 `<style>` to line 34 `</style>`, and its last rule is line 33: `.muni-nav-item--active .muni-nav-item__badge { background:var(--muni-accent); color:var(--muni-on-accent); }` — nothing dark-scoped follows.)
- `hardcoded_colors[0].purpose`: el lector dijo «border»; el archivo dice «outline (focus indicator) — the literal sits in the `outline` shorthand, not in any border property; the component declares no border at all» (Line 27: `.muni-nav-item:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; box-shadow:var(--muni-ring); }` — and the in-file comment on line 26 states `El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus).`)

</details>

### `<x-muni::nav-section>`

Archivo: `resources/views/components/nav-section.blade.php` (10 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `title` | `null` | string | Optional uppercase mono group heading above the items. |

**Slots:** `slot` (obligatorio) — The nav items, laid out in a 2px-gap flex column; the component renders an empty box without it.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-font-mono`, `--muni-hint`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**README:** documentado · **Tests:** sin prueba
**Notas:** No `$attributes` anywhere: any class/id/data-* passed to <x-muni::nav-section> is silently dropped. The title is a plain <div>, not a heading and not role="group"/aria-labelledby, so the group has no programmatic semantics — the host must supply <nav>/landmark structure. No style block, no JS, no wire: directives; safe on Livewire 3 and 4.

### `<x-muni::otp-input>`

Archivo: `resources/views/components/otp-input.blade.php` (49 líneas) · verificado con 2 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `length` | `6` | int | Number of digit boxes; interpolated as (int) $length in the Alpine x-data (Array(6).fill('')), in the auto-advance bound ((int) $length - 1), in paste() slice/clamp, and in the @for loop condition |
| `name` | `code` | string | name attribute of the hidden input that carries the joined code: <input type="hidden" name="{{ $name }}" :value="code"> |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-mono`, `--muni-radius-sm`, `--muni-ring`, `--muni-surface`, `--muni-text`.
**Alpine:** `x-data`, `x-ref`, `x-model`, `:value`, `@input`, `@keydown`, `@paste.prevent`. Solo core.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | line 46: .muni-otp:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; ... } | outline (focus indicator), i.e. border-like | sí | Third-level var() fallback: the painted value is var(--muni-focus), a token defined light (#0f766e, muni-ui.css:96) and dark (#f59e0b, muni-ui.css:146 and :193). The literal is reached only if muni-ui.css never loads, and the neutral grey still clears 3:1 against both surfaces (~4.5:1 on #ffffff, ~4.0:1 on #111620) |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-otp { color:var(--muni-text) }`; `.muni-otp { background:var(--muni-surface) }`; `.muni-otp { border:1px solid var(--muni-border) }`; `.muni-otp:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-otp:focus { border-color:var(--muni-accent) }`; `.muni-otp:focus { box-shadow:var(--muni-ring) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: Backspace on an empty box moves focus to the previous box (onKey), 0-9 typed in a box auto-advances to the next box (onInput, non-digits stripped with /\D/g), Paste (Ctrl/Cmd+V) is intercepted with @paste.prevent, digits-only, fills up to `length` boxes and focuses the last filled one, Tab/Shift+Tab between boxes is native (no arrow-key handling) · focus-visible: sí · reduced-motion: sí · etiqueta: aria-label="Dígito {{ $i + 1 }}" on every box (line 35); the hidden input carries no label, and the group has no fieldset/legend or role="group".
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** @once <style> with a global .muni-otp class and no uniqid; several instances on one page share the same rules (x-refs stay per x-data root, so focus movement does not leak between instances). Value lives in a hidden input bound with :value="code" — Alpine-only, so wire:model on it will not track; the code reaches the server only on a plain form submit. No wire: directives, so Livewire 3 and 4 safe. Uses :focus, not :focus-visible, so mouse clicks also show the 3px outline (outline-offset:2px), the real indicator, with var(--muni-ring) only as a decorative halo — matching the rationale documented at muni-ui.css:83-95. Reduced motion is honoured indirectly: the only transition uses var(--muni-dur), which muni-ui.css zeroes under @media (prefers-reduced-motion: reduce) (line 245); the component has no reduced-motion rule of its own. The only @media inside the @once block is (max-width:420px), a responsive breakpoint that shrinks the boxes to 40x48 — at 46x54 (and 40x48 on small screens) the boxes clear the 24x24 minimum target.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block contains no dark rule at all; grep -i 'dark' over the whole file returns zero matches, and the single @media in the block is a width breakpoint» (line 47: `@media (max-width:420px){ .muni-otp { width:40px; height:48px; font-size:19px; } }` — the only @media in the block; lines 42-46 are the sole other rules and none is scoped to .dark / [data-theme="dark"] / [data-muni-theme="dark"]. dark_ok stays true because every painted colour is a dual-defined --muni-* token, not because a dark counterpart exists.)
- `a11y.roles`: el lector dijo «["aria-label"]»; el archivo dice «[] — the component declares no role attribute anywhere; aria-label is the only ARIA attribute present and it is already reported under a11y.labels» (line 35: `aria-label="Dígito {{ $i + 1 }}"` is the only aria-*/role match in the file (`grep -n -o -E 'role="[^"]*"\|aria-[a-z]+="[^"]*"'` returns just that line). The digit boxes are not wrapped in any role="group"/fieldset either.)

</details>

### `<x-muni::page-header>`

Archivo: `resources/views/components/page-header.blade.php` (16 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `title` | `REQUIRED` | string | Rendered as the page <h1>; declared without a default |
| `subtitle` | `null` | string | Optional paragraph under the title, capped at 60ch |
| `eyebrow` | `null` | string | Optional uppercase mono kicker above the title, in accent colour |

**Slots:** `actions` — Right-aligned flex row of buttons/links, rendered via @isset($actions)
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-font-mono`, `--muni-font-sans`, `--muni-muted`, `--muni-text`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: sí · etiqueta: none.
**README:** documentado · **Tests:** sin prueba
**Notas:** Pure inline styles, no @once block, no JS. One of the smallest components but NOT the smallest: nav-section (10), field (12) and tab-panel (15) are shorter, and empty-state ties at 16. Always emits an <h1>, so two on one page produce two h1s. The default $slot is NOT rendered — content must go through the named $actions slot. $eyebrow and $subtitle are guarded with @if (truthiness), not @isset, so a literal "0" would be suppressed. No wire: directives, Livewire 3/4 safe.

<details><summary>Correcciones del verificador</summary>

- `notes`: el lector dijo «Smallest component in the set: pure inline styles, no @once block, no JS.»; el archivo dice «page-header is 16 lines; nav-section.blade.php (10), field.blade.php (12) and tab-panel.blade.php (15) are all smaller, and empty-state.blade.php ties at 16 — so it is not the smallest component in the set. The rest of the notes (inline styles, no @once, no JS, always an <h1>, default $slot unrendered, no wire: directives) checks out against the file.» (wc -l over resources/views/components/*.blade.php: "10 nav-section.blade.php", "12 field.blade.php", "15 tab-panel.blade.php", "16 empty-state.blade.php", "16 page-header.blade.php")

</details>

### `<x-muni::pagination>`

Archivo: `resources/views/components/pagination.blade.php` (50 líneas) · verificado con 3 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `current` | `1` | int | Current page; re-cast with `$current = (int) $current;` in the @php block |
| `total` | `1` | int | Total pages; floored at 1 with `$total = max((int) $total, 1);` |
| `url` | `null` | closure | callable fn($pagina)=>string; `$link = is_callable($url) ? $url : fn ($p) => '#';` so a non-callable silently degrades every href to '#' |
| `info` | `null` | string | Optional status text rendered in a right-pushed span (margin-left:auto); shown only under a truthiness check `@if ($info)` |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-hint`, `--muni-muted`, `--muni-on-accent`, `--muni-radius-sm`, `--muni-ring`, `--muni-surface-2`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | line 46: `.muni-page:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }` | border | sí | Third-level var() fallback, unreachable while muni-ui.css or muni-ui-filament.css is loaded (both define --muni-focus in light and dark). Even when reached it is the canonical neutral grey that clears 3:1 against both themes: ~4.1:1 on light --muni-bg #f5f6f8 and ~4.3:1 on dark --muni-bg #0b0f14, above the 3:1 required for a focus indicator (WCAG 2.2 AA 1.4.11). |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-page { border:1px solid transparent }`; `.muni-page { color:var(--muni-muted) }`; `.muni-page:hover { background:var(--muni-surface-2) }`; `.muni-page:hover { color:var(--muni-text) }`; `.muni-page:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-page:focus-visible { box-shadow:var(--muni-ring) }`; `.muni-page--current { background:var(--muni-accent) }`; `.muni-page--current { color:var(--muni-on-accent) }`; `.muni-page--nav { color:var(--muni-text) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `navigation (implicit landmark, <nav aria-label="Paginación">)`, `aria-label`, `aria-disabled`, `aria-current="page"` · teclado: Tab (native links; the aria-disabled prev/next links stay in the tab order), Enter (native link activation, including on the aria-disabled links) · focus-visible: sí · reduced-motion: sí · etiqueta: aria-label.
**README:** documentado · **Tests:** sin prueba
**Notas:** $url is a PHP closure prop; the file comment reads `// $url debe ser un closure/callable fn($pagina) => string, o null (solo muestra estado).` and a non-callable degrades every href to '#'. Page window is 1 … current±1 … total, built with `collect(range(1, $total))->filter(...)`; gaps are filled by a decorative `<span style="color:var(--muni-hint);padding:0 2px;">…</span>` that carries no aria-hidden. Disabled prev/next keep href="#", gain aria-disabled="true" and the inline style `opacity:.4;pointer-events:none;` — pointer-events blocks the mouse only, so the link remains focusable and Enter still activates href="#" (an aria-disabled control that is still operable by keyboard), and the .4 opacity can push the label under 4.5:1. `info` is gated by a truthiness check, so '0' or '' renders nothing. Reduced motion is respected indirectly: the only transition uses var(--muni-dur), which muni-ui.css zeroes under @media (prefers-reduced-motion: reduce). @once <style> with unscoped global classes .muni-page / .muni-page--current / .muni-page--nav, no uniqid, so a host stylesheet can collide. No wire: directives and no Alpine.

<details><summary>Correcciones del verificador</summary>

- `style_block.dark_counterpart`: el lector dijo «true»; el archivo dice «false — the @once <style> block (lines 39-50) contains only five rules, none of them scoped to a dark activator; there is no `.dark`, `[data-theme="dark"]`, `[data-muni-theme="dark"]` selector nor any `@media (prefers-color-scheme: dark)` anywhere in the component. `grep -n -E '\.dark\|data-muni-theme\|data-theme\|prefers-color-scheme' pagination.blade.php` returns no match. dark_ok stays true, but by the token mechanism, not by a dark counterpart of its own.» (Lines 41-48 are the whole block: `.muni-page { … color:var(--muni-muted); … }` / `.muni-page:hover { background:var(--muni-surface-2);color:var(--muni-text); }` / `.muni-page:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }` / `.muni-page--current { background:var(--muni-accent);color:var(--muni-on-accent);font-weight:600; }` / `.muni-page--nav { color:var(--muni-text);font-weight:500; }` — no dark-scoped rule follows before `</style>` on line 49.)
- `a11y.roles`: el lector dijo «["aria-label", "aria-disabled", "aria-current"] — only ARIA attribute names, omitting the landmark the component actually creates»; el archivo dice «The root element is `<nav>` with an accessible name, i.e. a named `navigation` landmark — the single most load-bearing a11y fact of the component — plus aria-disabled on the two nav links and aria-current="page" (value quoted, it is the page-specific value) on the current chip.» (Line 18: `<nav aria-label="Paginación" {{ $attributes->merge(['style' => 'display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:16px;font-family:var(--muni-font-sans);font-size:13px;']) }}>` and line 26: `<span class="muni-page muni-page--current" aria-current="page">{{ $p }}</span>`)
- `notes (disabled prev/next behaviour)`: el lector dijo «"Disabled prev/next keep href=\"#\" plus aria-disabled and opacity:.4 (that .4 can drop text under 4.5:1)." — omits `pointer-events:none` and therefore misses the keyboard consequence»; el archivo dice «The inline style is `opacity:.4;pointer-events:none;`. `pointer-events:none` suppresses mouse activation only; the anchor keeps its href, stays in the tab order and is still activated by Enter, so an element announced as aria-disabled="true" remains operable by keyboard and navigates to '#'. This is a real WCAG 2.2 defect (a11y.keyboard corrected accordingly), not a cosmetic detail.» (Line 20: `class="muni-page muni-page--nav" style="{{ $current <= 1 ? 'opacity:.4;pointer-events:none;' : '' }}">‹ Anterior</a>` (identical construct on line 34 for `Siguiente ›`))

</details>

### `<x-muni::progress>`

Archivo: `resources/views/components/progress.blade.php` (37 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `value` | `0` | int | Current amount; converted to a 0-100 percentage against max |
| `max` | `100` | int | Denominator; if <= 0 the percentage is forced to 0 |
| `tone` | `accent` | string | Key into the colour map: accent\|ok\|warn\|danger\|info, unknown falls back to accent |
| `label` | `null` | string | Visible caption, also reused as the accessible name |
| `showValue` | `false` | bool | Shows the percentage in mono tabular figures on the right |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-danger-fg`, `--muni-ease`, `--muni-font-mono`, `--muni-font-sans`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-fg`, `--muni-surface-3`, `--muni-text`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `role="progressbar"`, `aria-valuenow`, `aria-valuemin`, `aria-valuemax`, `aria-label` · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: aria-label.
**README:** documentado · **Tests:** sin prueba
**Notas:** Tone map in @php lists five tokens (--muni-accent, --muni-ok-fg, --muni-warn-fg, --muni-danger-fg, --muni-info-fg), all dual-theme. Two gotchas: the bar animates with a literal transition:width .5s, so it ignores prefers-reduced-motion (--muni-dur is not used); and $attributes is spread on the wrapper while the inner bar reads $attributes->get('aria-label'), so a caller-supplied aria-label lands on both elements. In-file comment explains why the accessible name is mandatory. No wire: directives.

### `<x-muni::rating>`

Archivo: `resources/views/components/rating.blade.php` (42 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `value` | `0` | int | Valor inicial; se castea a (float) dentro de x-data. |
| `max` | `5` | int | Cantidad de estrellas; castea a (int) en el @for. |
| `readonly` | `false` | bool | Quita @click/@mouseenter/@mouseleave y pone cursor:default. |
| `name` | `null` | string | Si viene, emite input hidden con :value para envío por formulario. |
| `tone` | `'accent'` | string | accent\|warn\|ok; mapea a token de color en --star; desconocido cae a accent. |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border-2`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-glow`, `--muni-ok-fg`, `--muni-ring`, `--muni-warn-fg`.
**Alpine:** `x-data`, `:value`, `@click`, `@mouseenter`, `@mouseleave`, `:aria-checked`, `:class`. Solo core.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | .muni-star:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)) } | border | **NO** | Literal sin regla .dark; solo pinta si faltan --muni-focus y --muni-accent, es decir sin muni-ui.css cargado. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-star { background: none }`; `.muni-star { border: none }`; `.muni-star { color: var(--muni-border-2) }`; `.muni-star:focus-visible { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-star:focus-visible { box-shadow: var(--muni-ring) }`; `.muni-star--on { color: var(--star) }`; `.muni-star--on { text-shadow: var(--muni-glow) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ Todo el color real sale de tokens con rama clara y oscura. El único literal, #767676, es doble fallback del outline: aparece solo si muni-ui.css no está cargado, y entonces el foco se ve gris neutro en ambos temas.
**A11y:** roles/aria: `role="radiogroup"`, `:aria-checked="value >= {{ $i }}"`, `aria-label="{{ $i }} de {{ $max }}"` · teclado: ninguno · focus-visible: sí · reduced-motion: sí · etiqueta: aria-label.
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Bloque <style> en @once. Los hijos del role=radiogroup son <button>, no role=radio: aria-checked es inválido ahí y no hay navegación con flechas ni tabindex móvil. En readonly los botones siguen siendo enfocables. Cada botón define --star en línea. Con Livewire 3 el x-data se reinicializa al re-renderizar y pierde el valor elegido.

<details><summary>Correcciones del verificador</summary>

- `dark_ok`: el lector dijo «True»; el archivo dice «False» (segunda lectura independiente)

</details>

### `<x-muni::reverb-meta>`

Archivo: `resources/views/components/reverb-meta.blade.php` (28 líneas) · verificado sin discrepancias

Sin props (`@props` vacío o ausente).

**Slots:** ninguno.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** ninguno.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: ninguna.
**README:** **sin fila** · **Tests:** cubierto
**Notas:** Sin @props ni marcado visible: emite <meta reverb-key|host|port|scheme> y va en <head>. Lee config('broadcasting.connections.reverb.*') con claves propias host_publico/puerto_publico/esquema_publico. El comentario de cabecera documenta el gotcha: VITE_REVERB_APP_KEY no existe al construir la imagen, por eso la clave va en runtime. Sin clave no emite nada, a propósito.

### `<x-muni::ring>`

Archivo: `resources/views/components/ring.blade.php` (36 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `value` | `0` | int | Valor actual; el porcentaje se calcula y se acota entre 0 y 100. |
| `max` | `100` | int | Máximo; si es <= 0 el porcentaje queda en 0. |
| `size` | `96` | int | Lado en px del contenedor; también escala la tipografía del valor. |
| `tone` | `'accent'` | string | accent\|ok\|warn\|danger\|info; color del trazo y del número. |
| `label` | `null` | string | Texto opcional bajo el anillo, en --muni-muted. |
| `showValue` | `true` | bool | Muestra el porcentaje centrado; se pasa como :show-value. |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-danger-fg`, `--muni-ease`, `--muni-font-mono`, `--muni-font-sans`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-fg`, `--muni-surface-3`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: ninguno · teclado: ninguno · focus-visible: no · reduced-motion: no · etiqueta: ninguna.
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Sin role="progressbar" ni aria-valuenow/valuemin/valuemax, y el <svg> tampoco lleva aria-hidden: con showValue=false el dato solo existe como color. La transición fija .8s en vez de var(--muni-dur), así que ignora prefers-reduced-motion. Radio 42 y stroke-width 8 están cableados sobre viewBox 0 0 100 100. Tono desconocido cae a accent.

### `<x-muni::segmented>`

Archivo: `resources/views/components/segmented.blade.php` (39 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `name` | `null` | string | Radio group name; without it the component renders only the slot |
| `options` | `[]` | array | value => label map turned into radio pills |
| `value` | `null` | mixed | Currently selected option, compared with a string cast |

**Slots:** `slot` — Default slot, rendered only when `options` is empty or `name` is falsy; the div is empty otherwise. Meant for links/visual-only segments.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-muted`, `--muni-radius-sm`, `--muni-ring`, `--muni-shadow`, `--muni-surface`, `--muni-surface-2`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | .muni-seg:has(input:focus-visible) { outline } — third-level fallback inside var(--muni-focus, var(--muni-accent, #767676)) | border | **NO** | Literal grey with no dark override rule in the file; only painted if both token layers are undefined (stylesheet absent) |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-seg { color: var(--muni-muted) }`; `.muni-seg:hover { color: var(--muni-text) }`; `.muni-seg:has(input:focus-visible) { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-seg:has(input:focus-visible) { box-shadow: var(--muni-ring) }`; `.muni-seg--on { background: var(--muni-surface) }`; `.muni-seg--on { color: var(--muni-text) }`; `.muni-seg--on { box-shadow: var(--muni-shadow) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ Nothing breaks when muni-ui.css is loaded: container, pills, hover and active state all use dual-theme tokens. The only literal, #767676, is the last-resort focus fallback behind --muni-focus and --muni-accent.
**A11y:** roles/aria: `role="group"` · teclado: Tab (native; enters the group at the checked radio), ArrowLeft/ArrowRight/ArrowUp/ArrowDown (native radio group, but each move fires change and submits the form), Space (native selection, also fires change and submits) · focus-visible: sí · reduced-motion: sí · etiqueta: <label for>.
**README:** documentado · **Tests:** sin prueba
**Notas:** @once <style>. Inline onchange="this.form && this.form.submit()" auto-submits the host form: blocked by a strict CSP without 'unsafe-inline', and it is a full reload — no wire: support. Inside a form this also makes native arrow-key traversal submit on every move (WCAG 2.2 3.2.2 On Input), and form.submit() bypasses HTML5 validation and any submit listener. Ids are "{name}-{loop->index}", so two groups sharing a name collide. Focus ring depends on :has(). role="group", not radiogroup, and the group is unlabelled. The slot branch is unstyled. No prefers-reduced-motion rule of its own: the transition honours it only through --muni-dur, which muni-ui.css sets to 0ms.

<details><summary>Correcciones del verificador</summary>

- `a11y.keyboard`: el lector dijo «"ArrowLeft/ArrowRight/ArrowUp/ArrowDown (native radio group)" and "Space (native selection)" listed as plain native keyboard navigation»; el archivo dice «Every radio carries an inline onchange that submits the host form, so arrow keys and Space do not merely move/select: each keystroke changes the checked radio, fires `change`, and reloads the page. Arrow traversal of the options is impossible for keyboard users (WCAG 2.2 3.2.2 On Input).» (line 16: <input type="radio" ... style="position:absolute;opacity:0;width:0;height:0;" onchange="this.form && this.form.submit()">)

</details>

### `<x-muni::select>`

Archivo: `resources/views/components/select.blade.php` (57 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `label` | `null` | string | Texto del <label for>; sin él no hay etiqueta accesible alguna. |
| `name` | `null` | string | Atributo name y base del id ('muni-'.$name). |
| `options` | `[]` | array | Mapa valor => texto; si no está vacío, el slot se ignora. |
| `selected` | `null` | mixed | Valor marcado; se compara como cadena con cada clave. |
| `placeholder` | `null` | string | Primera <option> con value vacío, sin disabled. |
| `error` | `null` | string | Pinta borde danger y muestra el mensaje bajo el campo. |
| `hint` | `null` | string | Ayuda bajo el campo; solo se muestra si no hay error. |
| `required` | `false` | bool | Añade atributo required y asterisco rojo junto al label. |

**Slots:** `slot` — Opciones <option> propias; solo se renderiza en la rama @else, cuando options está vacío.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-danger-border`, `--muni-danger-fg`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-hint`, `--muni-muted`, `--muni-radius-sm`, `--muni-ring`, `--muni-surface`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | .muni-select:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) } | border | **NO** | Literal sin regla .dark; solo pinta si faltan --muni-focus y --muni-accent, es decir sin muni-ui.css cargado. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-select:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)) }`; `.muni-select:focus { border-color: var(--muni-accent) }`; `.muni-select:focus { box-shadow: var(--muni-ring) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ❌ Fondo, texto, bordes, error y hint salen de tokens con rama clara y oscura. El único literal, #767676, es doble fallback del outline y solo aparece si muni-ui.css no está cargado; ahí el foco queda gris neutro en ambos temas.
**A11y:** roles/aria: `aria-hidden="true"` · teclado: ninguno · focus-visible: sí · reduced-motion: sí · etiqueta: <label for>.
**README:** documentado · **Tests:** sin prueba
**Notas:** Bloque <style> en @once. Sin name el id usa uniqid(): cambia en cada render y con Livewire 3 rompe el diffing y la relación label/for. El error no se ata con aria-describedby ni marca aria-invalid. Sin prop label no queda etiqueta accesible. El slot se descarta si options trae datos. La regla de foco es :focus, no :focus-visible.

<details><summary>Correcciones del verificador</summary>

- `dark_ok`: el lector dijo «True»; el archivo dice «False» (segunda lectura independiente)

</details>

### `<x-muni::sidebar>`

Archivo: `resources/views/components/sidebar.blade.php` (29 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `width` | `240px` | string | Ancho de la barra; se inyecta como custom property --sb-w en style inline. |

**Slots:** `default` (obligatorio) — Contenido de navegación dentro de .muni-sb__inner; se esperan <x-muni::nav-item> y <x-muni::nav-section> (indicado solo en un comentario Blade).
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-shadow-lg`, `--muni-surface`.
**Alpine:** `x-data`, `@muni-sidebar.window`, `:class`. Solo core. Eventos: `muni-sidebar (escucha en window; alterna open = !open)`.

**Bloque `<style>` propio:** sí; reglas de color: `3`. Contraparte `.dark`: no aplica.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `complementary (implícito por <aside>, sin nombre accesible propio)` · teclado: No hay disparador ni cierre interno: el toggle llega por evento window externo. Sin Esc, sin x-trap, sin retorno de foco. En <900px la barra cerrada queda translateX(-100%) pero sigue en el flujo, sin inert ni aria-hidden: los enlaces se tabulan fuera de pantalla. · focus-visible: no · reduced-motion: sí · etiqueta: ninguna.
**README:** documentado · **Tests:** sin prueba
**Notas:** Off-canvas bajo 900px. `open` se calcula una sola vez con window.innerWidth y nunca se recalcula al redimensionar. Usa `{{ $attributes }}` sin merge en un tag que ya trae class="muni-sb" y style literales: si el consumidor pasa class o style se emite el atributo duplicado y el navegador conserva el primero, descartando silenciosamente el del consumidor.

<details><summary>Correcciones del verificador</summary>

- `attributes_merge`: el lector dijo «True»; el archivo dice «False» (segunda lectura independiente)

</details>

### `<x-muni::skeleton>`

Archivo: `resources/views/components/skeleton.blade.php` (24 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `width` | `100%` | string | Ancho CSS interpolado en el style del merge. |
| `height` | `14px` | string | Alto CSS fijo; evita CLS al reservar espacio. |
| `rounded` | `var(--muni-radius-sm)` | string | border-radius; el valor por defecto ya es un token muni. |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-radius-sm`, `--muni-surface-3`, `--muni-text`.
**Alpine:** no (Blade puro).

**Bloque `<style>` propio:** sí; reglas de color: `2`. Contraparte `.dark`: no aplica.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `ninguno (span decorativo con aria-hidden="true")` · teclado: No focusable ni interactivo. · focus-visible: no · reduced-motion: sí · etiqueta: ninguna.
**README:** documentado · **Tests:** sin prueba
**Notas:** Placeholder de carga con shimmer 1.4s, anulado bajo prefers-reduced-motion. aria-hidden deja el estado de carga sin anunciar: el consumidor debe aportar aria-busy o una región live. Los tres props se concatenan crudos dentro del atributo style vía merge; no hay escape de válido CSS, así que con datos de usuario permite inyectar declaraciones extra.

### `<x-muni::sortable-table>`

Archivo: `resources/views/components/sortable-table.blade.php` (83 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `columns` | `[]` | array | Cada columna: key, label, align?, mono?, sortable? (por defecto true). |
| `rows` | `[]` | array | Arreglos asociativos por key; '_tone'=>'danger' pinta la franja roja. |
| `empty` | `Sin resultados.` | string | Texto del estado vacío, escapado con {{ }}. |
| `searchable` | `false` | bool | Muestra el input de filtrado en vivo sobre todas las columnas. |

**Slots:** ninguno.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-danger-fg`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-mono`, `--muni-font-sans`, `--muni-muted`, `--muni-radius`, `--muni-radius-sm`, `--muni-ring`, `--muni-surface`, `--muni-surface-2`, `--muni-text`.
**Alpine:** `x-data`, `x-model`, `x-for`, `x-if`, `x-text`, `:key`, `:style`, `:class`, `@click`, `getter view()`. Solo core.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#767676` | línea 81, .muni-st__search:focus → outline:3px solid var(--muni-focus, var(--muni-accent, #767676)) | border | **NO** | Literal sin par oscuro; solo se alcanza si falta la hoja de tokens. Gris medio: 4,5:1 sobre blanco y sobre negro. |

**Bloque `<style>` propio:** sí; reglas de color: `8`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `table, rowgroup, row, columnheader, cell (implícitos)`, `sin aria-sort en ningún <th>`, `sin <caption> ni scope="col"` · teclado: El orden se dispara con @click sobre el <th>, que no es focusable: sin tabindex, sin role="button" y sin @keydown, ordenar es inalcanzable por teclado (WCAG 2.1.1). Solo el input de búsqueda entra en el recorrido de tabulación. · focus-visible: sí · reduced-motion: sí · etiqueta: El input de búsqueda solo tiene placeholder "Buscar…": no hay <label>, aria-label ni aria-labelledby (WCAG 3.3.2 / 4.1.2)..
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Orden en cliente: primer clic asc (sortDir=1), reclic invierte el signo; nunca vuelve a sin orden. Compara con parseFloat tras quitar todo salvo dígitos, punto y guion, y si ambos son NaN cae a localeCompare('es'). El filtro recorre Object.values(row), así que también busca dentro de _tone. Indicador ↑/↓/↕ por opacidad, sin aria-sort ni anuncio.

<details><summary>Correcciones del verificador</summary>

- `attributes_merge`: el lector dijo «True»; el archivo dice «False» (segunda lectura independiente)

</details>

### `<x-muni::stat>`

Archivo: `resources/views/components/stat.blade.php` (59 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `value` | `REQUIRED` | mixed | Cifra principal, 26px mono tabular; se colorea según tone. |
| `label` | `REQUIRED` | string | Rótulo en versalitas bajo el valor, color --muni-muted. |
| `tone` | `neutral` | string | neutral\|ok\|warn\|danger\|info; mapea a token de color, con fallback a --muni-text. |
| `delta` | `null` | mixed | Variación mostrada a la derecha; null oculta el bloque entero. |
| `deltaDir` | `null` | string | up\|down\|null; define flecha ▲/▼ y color ok/danger/muted. |
| `spark` | `null` | array | Serie numérica; con más de un punto dibuja el minigráfico SVG. |
| `hint` | `null` | string | Nota al pie de 11px con color --muni-hint. |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-danger-fg`, `--muni-dur`, `--muni-ease`, `--muni-font-mono`, `--muni-hint`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-fg`, `--muni-radius`, `--muni-shadow`, `--muni-shadow-md`, `--muni-surface`, `--muni-text`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).

**Bloque `<style>` propio:** sí; reglas de color: `1`. Contraparte `.dark`: no aplica.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `ninguno explícito: divs sin <dl>, sin role="group" ni aria-labelledby que ate valor y rótulo` · teclado: No focusable ni interactivo; el hover eleva la tarjeta sin equivalente por teclado. · focus-visible: no · reduced-motion: sí · etiqueta: Flecha ▲/▼ con aria-hidden: para lectores de pantalla el delta queda sin dirección, solo con el número.; SVG con aria-hidden y sin <title>/<desc>..
**README:** documentado · **Tests:** cubierto
**Notas:** Sparkline en PHP: exige array con más de 1 punto, normaliza a viewBox 0 0 100 28, x = i/(n-1)*100, y = 26 - ((v-min)/range)*24 con range ?: 1 (una serie plana cae al borde inferior, no al centro). Dos paths: relleno cerrado opacity .08 y trazo 1.5 non-scaling. aria-hidden y SIN alternativa textual: la serie no se expone a lectores de pantalla.

### `<x-muni::stepper>`

Archivo: `resources/views/components/stepper.blade.php` (57 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `steps` | `[]` | array | Array de strings o de ['label'=>, 'hint'=>]; un <li> por elemento. |
| `current` | `0` | int | Índice 0-based del paso activo; casteado con (int). Sin validación de rango. |
| `orientation` | `horizontal` | string | Solo 'vertical' cambia algo (clase muni-stepper--v); cualquier otro valor es horizontal. |

**Slots:** `default` — NO se usa: el componente nunca imprime {{ $slot }}. Todo el contenido que se le pase entre las etiquetas se descarta silenciosamente; el marcado se genera solo desde el array $steps.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-font-mono`, `--muni-font-sans`, `--muni-hint`, `--muni-muted`, `--muni-on-accent`, `--muni-ring`, `--muni-surface`, `--muni-text`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `white` | línea 45, .muni-step__label { … white-space:nowrap; … } | Ninguno: falso positivo del grep. Es la propiedad CSS `white-space`, no un color. | sí | No es un valor de color; no pinta texto, fondo, borde, relleno ni trazo. El componente no tiene ni un solo literal de color real. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-step:not(:last-child)::after → background:var(--muni-border) (línea conectora)`; `.muni-step--done:not(:last-child)::after → background:var(--muni-accent)`; `.muni-step__marker → border:2px solid var(--muni-border); background:var(--muni-surface); color:var(--muni-muted)`; `.muni-step__label → color:var(--muni-muted)`; `.muni-step__hint → color:var(--muni-hint)`; `.muni-step--active .muni-step__marker → border-color/color:var(--muni-accent); box-shadow:var(--muni-ring)`; `.muni-step--active .muni-step__label → color:var(--muni-text)`; `.muni-step--done .muni-step__marker → border-color/background:var(--muni-accent); color:var(--muni-on-accent)`; `.muni-step--done .muni-step__label → color:var(--muni-text)`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `<ol> + <li> nativos (lista ordenada semántica, sin role explícito)`, `aria-current="step" solo en el <li> del paso activo`, `SIN role="list"/"listitem" explícito (innecesario salvo que list-style:none los quite en Safari/VoiceOver: .muni-stepper pone list-style:none, así que VoiceOver puede dejar de anunciar la lista)` · teclado: NINGUNO. Es SOLO UN INDICADOR VISUAL, no es navegable ni accionable por teclado: no hay <button>, <a>, tabindex, x-data ni ningún manejador de eventos. No se puede enfocar, no se puede cambiar de paso desde el componente. Si se necesita un stepper navegable hay que envolver cada paso en un control propio fuera del componente. · focus-visible: no · reduced-motion: sí · etiqueta: Etiqueta y hint son texto plano escapado con {{ }}. GAPS: (1) el <svg> del estado 'done' no tiene aria-hidden="true" ni <title>, es un gráfico sin nombre accesible; (2) en 'done' el número se REEMPLAZA por el check, así que el lector de pantalla no oye ni el número ni la palabra 'completado' — el estado done/todo se transmite solo por forma y color, sin texto alternativo (roza el criterio de no usar el color como único portador); (3) solo el paso activo tiene marca programática (aria-current); los pasos 'done' y 'todo' son indistinguibles para asistencia técnica..
**Depende de:** resources/css/muni-ui.css (tokens --muni-*; y --muni-dur=0ms para reduced-motion).
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** Componente puramente presentacional, sin JS. $attributes->merge(['class'=>...]) sobre el <ol>, así que una clase del consumidor se suma a 'muni-stepper' (única fusión real de clases de los cuatro). Cuidado: la clase interna es .muni-step__num, NO la firma .muni-num de data-table. La línea conectora horizontal usa offsets fijos (left:calc(14px + 26px); right:-10px) calculados para un marcador de 28px: si el consumidor cambia tamaños, se desalinea. El bloque <style> va dentro de @once, así que solo se emite una vez por respuesta.

### `<x-muni::switch>`

Archivo: `resources/views/components/switch.blade.php` (38 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `label` | `null` | string | Texto visible; es el ÚNICO nombre accesible del control. Si es null queda sin nombre. |
| `name` | `null` | string | name del input y base del id ('muni-'.$name); si falta se usa uniqid(). |
| `checked` | `false` | bool | Estado inicial: alimenta @checked() y el x-data de Alpine a la vez. |
| `description` | `null` | string | Texto secundario bajo el label; visual, no queda asociado como aria-describedby. |

**Slots:** `default` — NO se usa: el componente no imprime {{ $slot }}. El texto se pasa por las props `label` y `description`, no como contenido.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-muted`, `--muni-ring`, `--muni-surface`, `--muni-surface-3`, `--muni-text`.
**Alpine:** `x-data="{ on: true|false }" (en el <span> envoltorio del control, no en la raíz)`, `x-model="on" (en el <input type=checkbox>)`, `:class="on && 'muni-switch--on'" (x-bind:class en el <span class=muni-switch>)`. Solo core.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `rgba(0,0,0,.25)` | línea 30, .muni-switch__thumb { box-shadow:0 1px 3px rgba(0,0,0,.25) } | Sombra de elevación de la perilla (thumb) del interruptor. | sí | Es una sombra, no texto/fondo/borde/relleno/trazo: entra en la excepción de la definición. Negro al 25% se lee bien en ambos temas (en claro separa la perilla blanca del riel; en oscuro es prácticamente invisible pero no daña, la perilla ya contrasta con --muni-surface-3/--muni-accent). |
| `#767676` | línea 36, input:focus-visible + .muni-switch { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)) } | Tercer nivel de fallback del color del OUTLINE de foco, si no existieran ni --muni-focus ni --muni-accent (o sea, si muni-ui.css no se cargó). | **NO** | Literal usado como color de trazo/borde (outline) y NO tiene regla .dark/[data-muni-theme=dark] propia en el archivo: por la definición estricta NO es seguro. Atenuantes reales: (a) solo se aplica si la hoja de tokens está ausente, escenario en que el componente ya no se ve bien de todas formas; (b) #767676 es el gris canónico de doble tema — contraste calculado 4,54:1 sobre #ffffff y 4,62:1 sobre negro, por encima del 3:1 exigido para indicador de foco en ambos modos. Está puesto a propósito como red de seguridad, no por descuido. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-switch → background:var(--muni-surface-3); border:1px solid var(--muni-border)`; `.muni-switch__thumb → background:var(--muni-surface); box-shadow:0 1px 3px rgba(0,0,0,.25)`; `.muni-switch--on → background:var(--muni-accent); border-color:var(--muni-accent)`; `input:focus-visible + .muni-switch → outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); box-shadow:var(--muni-ring)`; `inline línea 21: color:var(--muni-text) (label)`; `inline línea 22: color:var(--muni-muted) (description)`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `ES UN <input type="checkbox"> REAL (línea 12), no un div con role=switch: semántica y estado nativos, sin JS de accesibilidad que mantener.`, `NO lleva role="switch": los lectores de pantalla lo anuncian como 'casilla de verificación / marcada-desmarcada', no como 'interruptor / activado-desactivado'. Es una decisión defendible (el checkbox nativo es más robusto), pero conviene saberlo: el nombre del componente promete 'switch' y la semántica entregada es checkbox.`, `El input está oculto visualmente con position:absolute;opacity:0;width:0;height:0 (técnica correcta: sigue en el árbol de accesibilidad y sigue siendo enfocable; NO usa display:none ni visibility:hidden).`, `Envuelto en <label for="$id"> con id explícito, así que la asociación label↔control es programática, no solo por anidamiento.` · teclado: COMPLETO Y NATIVO por ser un checkbox real: llega con Tab, alterna con Espacio, participa del envío del formulario y del estado del formulario. No hay manejadores de teclado propios ni que puedan romperse. x-model mantiene sincronizado el estado visual de Alpine con el checkbox nativo en ambos sentidos. · focus-visible: sí · reduced-motion: sí · etiqueta: GAPS: (1) si no se pasa `label` ni un aria-label por $attributes, el checkbox queda SIN NOMBRE ACCESIBLE — el <label> existe pero está vacío; nada obliga a pasarlo. (2) `description` se pinta como texto suelto dentro del <label>; al estar dentro del label sí se concatena al nombre accesible, pero no hay aria-describedby, así que se lee como parte del nombre en vez de como descripción. (3) ÁREA TÁCTIL: sin label ni description el único blanco es el riel de 38×22px, bajo el mínimo de 24×24 de WCAG 2.2 AA 2.5.8 (falla por 2px de alto); con label o description el <label> completo es clickeable y el problema desaparece..
**Depende de:** Alpine 3 (x-data / x-model / :class) — sin Alpine el checkbox sigue funcionando pero el riel NUNCA cambia de color: el estado visual depende 100% de la clase muni-switch--on que pone Alpine. Degradación NO grácil.; resources/css/muni-ui.css (tokens --muni-*).
**README:** documentado · **Tests:** sin prueba
**Notas:** $attributes se vuelca CRUDO (sin ->merge()) sobre el <input> de la línea 13, no sobre la raíz: sirve para pasar wire:model, x-on, aria-label, required o disabled directo al control, que es lo correcto — pero como el input ya trae un atributo style= en línea, pasarle style= al componente emite DOS atributos style en el mismo tag (el navegador se queda con el primero, el del componente) y pasarle class= no se fusiona con nada. Ojo con el id: si no se pasa `name`, se genera con uniqid(), que cambia en cada render; dentro de Livewire eso rompe cualquier aria-describedby/for externo que apunte a él y ensucia el diffing (recomendable pasar siempre `name`). $checked se duplica a propósito en @checked($checked) y en el x-data para que el estado sea correcto antes de que Alpine arranque. El <style> va en @once.

<details><summary>Correcciones del verificador</summary>

- `attributes_merge`: el lector dijo «True»; el archivo dice «False» (segunda lectura independiente)

</details>

### `<x-muni::tab-panel>`

Archivo: `resources/views/components/tab-panel.blade.php` (15 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `index` | `REQUIRED` | int | Declarado sin default en @props(['index']); debe coincidir con la posición en `tabs`. |

**Slots:** `default` (obligatorio) — El contenido del panel. Es la única razón de ser del componente: se imprime tal cual dentro del <div role="tabpanel"> (línea 14).
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** ninguno.
**Alpine:** `x-show="active === N" (N interpolado desde PHP en tiempo de render)`, `x-cloak`, `x-transition:enter="muni-fade"`, `x-transition:enter-start="muni-fade-0"`, `x-transition:enter-end="muni-fade-1"`. Solo core.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `role="tabpanel" PRESENTE (línea 10).`, `SIN id: no puede ser destino de aria-controls desde su pestaña.`, `SIN aria-labelledby: el panel no está asociado a la pestaña que lo etiqueta, así que el lector de pantalla no anuncia a qué pestaña pertenece.`, `SIN aria-hidden explícito: lo resuelve x-show poniendo display:none, que sí lo saca del árbol de accesibilidad. Correcto.` · teclado: SIN tabindex="0". El patrón ARIA APG exige que el tabpanel sea enfocable (tabindex=0) cuando no contiene ningún elemento enfocable propio; acá nunca lo es. Si el panel es solo texto, tras la pestaña el Tab salta directo al contenido siguiente y el usuario de teclado no puede llevar el foco al contenido del panel. · focus-visible: no · reduced-motion: sí · etiqueta: Ninguna etiqueta propia; todo depende del slot..
**Depende de:** <x-muni::tabs> — el x-show lee la variable `active` del x-data del PADRE. Fuera de un <x-muni::tabs> el componente lanza error de Alpine ('active is not defined') y el panel no se muestra nunca.; resources/css/muni-ui.css → regla [x-cloak]{display:none!important} (línea 23). Sin esa hoja, TODOS los paneles se ven apilados hasta que Alpine arranque.; Clases .muni-fade / .muni-fade-0 / .muni-fade-1: NO están definidas en muni-ui.css ni en este archivo. Solo existen dentro de los bloques @once de modal.blade.php (79-80), drawer.blade.php (47) y command-palette.blade.php (67). En una página con pestañas pero sin modal, drawer ni command-palette, la transición de entrada es un no-op silencioso (el panel aparece de golpe). No rompe nada, pero el fade no ocurre.; Alpine 3.
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** 15 líneas, sin PHP más allá del cast (int) $index. $index NO tiene default: si se omite, `(int) $index` opera sobre variable indefinida (warning en PHP 8, evalúa 0) y el panel se comporta como el índice 0 — o sea, dos paneles visibles a la vez. Vale tratarlo como obligatorio. El acoplamiento con el padre es POSICIONAL y frágil: el índice se escribe a mano y nada verifica que exista una pestaña con ese índice ni que no haya duplicados. $attributes se vuelca crudo (sin ->merge()) en el <div>, así que un class= del consumidor entra limpio (el div no tiene clase propia) pero un role= lo pisaría el role="tabpanel"... en realidad NO: $attributes va DESPUÉS de role en la línea 12, así que un role= pasado por el consumidor gana y destruye la semántica de tabpanel.

<details><summary>Correcciones del verificador</summary>

- `attributes_merge`: el lector dijo «True»; el archivo dice «False» (segunda lectura independiente)

</details>

### `<x-muni::tabs>`

Archivo: `resources/views/components/tabs.blade.php` (43 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `tabs` | `[]` | array | Array de etiquetas (strings). Su count() alimenta el wrap circular de las flechas. |
| `default` | `0` | int | Índice 0-based de la pestaña inicial; casteado con (int). Sin validación de rango. |

**Slots:** `default` (obligatorio) — Los <x-muni::tab-panel :index="N"> en el mismo orden que el array `tabs`. Se imprime dentro del x-data (línea 28), que es lo que permite a los paneles leer `active`.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-dur`, `--muni-ease`, `--muni-focus`, `--muni-font-sans`, `--muni-muted`, `--muni-radius-sm`, `--muni-ring`, `--muni-text`.
**Alpine:** `x-data="{ active: N, count: M }" (raíz)`, `@keydown.right.prevent="active = (active + 1) % count" (en el tablist)`, `@keydown.left.prevent="active = (active - 1 + count) % count" (en el tablist)`, `@click="active = i" (cada botón)`, `:aria-selected="active === i"`, `:tabindex="active === i ? 0 : -1"`, `:class="active === i && 'muni-tab--on'"`. Solo core. Eventos: `keydown.right (delegado por burbujeo desde los botones al tablist, con preventDefault)`, `keydown.left (idem)`, `click (por botón)`.

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `transparent` | línea 34, .muni-tab { background:transparent } | Quitar el fondo por defecto del <button> para que la pestaña se funda con el contenedor. | sí | `transparent` está listado explícitamente como seguro en la definición: no aporta color propio en ningún tema. |
| `white` | línea 34, .muni-tab { … white-space:nowrap; … } | Ninguno: falso positivo del grep. Es la propiedad CSS `white-space`, no un color. | sí | No es un valor de color; no pinta texto, fondo, borde, relleno ni trazo. |
| `#767676` | línea 38, .muni-tab:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)) } | Tercer nivel de fallback del color del OUTLINE de foco, si faltaran --muni-focus y --muni-accent (o sea, si muni-ui.css no se cargó). | **NO** | Literal usado como color de trazo/borde (outline) sin regla .dark/[data-muni-theme=dark] propia: por la definición estricta NO es seguro. Mismos atenuantes que en switch: solo aplica sin la hoja de tokens, y #767676 da 4,54:1 sobre blanco y 4,62:1 sobre negro, por encima del 3:1 exigido a un indicador de foco en cualquiera de los dos temas. |

**Bloque `<style>` propio:** sí; reglas de color: `.muni-tab → color:var(--muni-muted); background:transparent; border:none`; `.muni-tab:hover → color:var(--muni-text)`; `.muni-tab:focus-visible → outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); box-shadow:var(--muni-ring)`; `.muni-tab--on → color:var(--muni-accent)`; `.muni-tab--on::after → background:var(--muni-accent) (subrayado de 2px de la pestaña activa)`; `inline línea 11: border-bottom:1px solid var(--muni-border) (línea base del tablist)`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `role="tablist" PRESENTE (línea 10, en el <div> contenedor de los botones).`, `role="tab" PRESENTE en cada control (línea 17), y son <button type="button"> reales, no <div>.`, `aria-selected PRESENTE, enlazado con :aria-selected="active === i" (línea 19). Funciona correctamente: Alpine 3 trata aria-selected como excepción y, con valor false, escribe aria-selected="false" en vez de borrar el atributo.`, `role="tabpanel" está en el componente hermano tab-panel, PRESENTE.`, `aria-controls AUSENTE: los <button> no lo declaran y los paneles no tienen id. NO existe relación programática entre pestaña y panel en ninguna de las dos direcciones (tampoco aria-labelledby en el panel). Esto es lo que más falta del patrón.`, `aria-orientation AUSENTE en el tablist: correcto por omisión, el default es horizontal y coincide con las flechas ←/→ implementadas.` · teclado: PARCIAL, con un defecto de fondo. LO QUE HAY: tabindex móvil (roving) real, :tabindex="active === i ? 0 : -1" (línea 20), así que el tablist entra y sale con UN solo Tab; flechas ← y → en el tablist (líneas 12-13) con recorrido circular vía módulo y con .prevent para no hacer scroll de la página. LO QUE FALTA, explícitamente: (1) Home y End NO están implementados (el APG los exige para ir a la primera/última pestaña); (2) tampoco hay ↑/↓ ni Delete, pero esos no aplican a un tablist horizontal no eliminable; (3) EL DEFECTO PRINCIPAL: las flechas cambian `active` pero NUNCA mueven el foco del DOM — no hay $refs, no hay .focus(), no hay $nextTick. Resultado: tras pulsar →, la pestaña seleccionada se pinta y su tabindex pasa a 0, pero el foco físico se queda en el botón ANTERIOR, que ahora tiene aria-selected="false" y tabindex="-1". El anillo de foco visible queda sobre una pestaña que no está seleccionada, el lector de pantalla sigue anunciando la vieja, y el modelo de selección deja de ser ni 'automático' ni 'manual' del APG: es un híbrido roto. Además el roving tabindex queda inconsistente (hay foco en un elemento con tabindex=-1) y el siguiente Tab sale desde la posición vieja. Es el hallazgo más importante de este componente. · focus-visible: sí · reduced-motion: sí · etiqueta: Las etiquetas son texto escapado con {{ $label }} dentro del <button>: nombre accesible correcto y suficiente. El tablist NO tiene aria-label ni aria-labelledby, así que con varios grupos de pestañas en la misma página el lector de pantalla no puede distinguirlos. Área táctil de la pestaña: padding 10px 14px + fuente 13.5px ≈ 40px de alto, cumple el mínimo 24×24 (y también el 44×44 de terreno en alto, no necesariamente en ancho para etiquetas muy cortas)..
**Depende de:** <x-muni::tab-panel> — pareja obligatoria: tabs solo renderiza los botones; los paneles y su x-show viven en el otro componente y leen `active` de este x-data.; Alpine 3 (x-data, x-bind, x-on). Sin Alpine no hay pestañas: los botones no hacen nada, ningún atributo enlazado se escribe y, como los paneles usan x-show + x-cloak, TODO el contenido queda oculto de forma permanente. Degradación no grácil: sin JS la página pierde el contenido, no solo la interacción.; resources/css/muni-ui.css (tokens --muni-* y la regla [x-cloak]).
**README:** documentado · **Tests:** sin prueba
**Notas:** Antes de que Alpine hidrate, los botones se sirven SIN aria-selected y SIN tabindex (son atributos enlazados, no se renderizan en el HTML del servidor): en ese instante las N pestañas son todas tabulables y ninguna aparece seleccionada. Convendría emitir los valores estáticos en Blade además de enlazarlos. $attributes se vuelca crudo (sin ->merge()) en el <div> raíz de la línea 8, DESPUÉS del x-data: un x-data pasado por el consumidor pisaría el del componente y rompería todo. `count` se congela en tiempo de render con count($tabs); es correcto porque `tabs` es un array de PHP, no reactivo. No hay validación de que `default` esté dentro de rango: un default fuera de rango deja todos los paneles ocultos hasta que el usuario pulse algo. El bloque <style> va en @once.

<details><summary>Correcciones del verificador</summary>

- `attributes_merge`: el lector dijo «True»; el archivo dice «False» (segunda lectura independiente)

</details>

### `<x-muni::timeline>`

Archivo: `resources/views/components/timeline.blade.php` (43 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `items` | `[]` | array<int, array{title?: string, time?: string, description?: string, tone?: string}> | Cada item admite tone: accent\|ok\|warn\|danger\|info\|muted; fallback accent. |

**Slots:** ninguno.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-accent`, `--muni-border`, `--muni-border-2`, `--muni-danger-fg`, `--muni-font-mono`, `--muni-font-sans`, `--muni-glow`, `--muni-hint`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-fg`, `--muni-surface`, `--muni-text`, `--muni-warn-fg`.
**Alpine:** no (Blade puro).

**Bloque `<style>` propio:** sí; reglas de color: `.muni-tl__item:not(:last-child)::before { background:var(--muni-border) }`; `.muni-tl__dot { background:var(--muni-surface); border:2px solid var(--dot); box-shadow:0 0 0 3px color-mix(in srgb,var(--dot) 15%,transparent),var(--muni-glow) }`; `.muni-tl__title { color:var(--muni-text) }`; `.muni-tl__time { color:var(--muni-hint) }`; `.muni-tl__desc { color:var(--muni-muted) }`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `Lista semántica `<ol>`/`<li>` implícita. `<time>` para la marca temporal, PERO sin atributo `datetime`, así que no es legible por máquina. El punto `.muni-tl__dot` es un `<span>` vacío sin `aria-hidden`, inocuo por no tener nombre accesible.` · teclado: No aplica: el componente es puramente de presentación, sin elementos interactivos ni tabindex. · focus-visible: no · reduced-motion: no · etiqueta: FALTA: el `<ol>` no tiene `aria-label` ni `aria-labelledby`, así que un lector de pantalla anuncia una lista sin nombre. FALLA WCAG 1.4.1 (uso del color): el `tone` (ok/warn/danger/info/muted) se comunica ÚNICAMENTE por el color del borde del punto; no hay icono, texto ni `aria-label` equivalente, por lo que un usuario con daltonismo no distingue un hito correcto de uno con error..
**Depende de:** resources/css/muni-ui.css (tokens --muni-*).
**README:** **sin fila** · **Tests:** sin prueba
**Notas:** El grep de colores literales no devuelve NINGUNA coincidencia en este archivo. Inconsistencia menor: el comentario PHP de la línea 6 documenta los tonos ok/warn/danger/info/accent pero el mapa de la línea 15 también acepta 'muted' => var(--muni-border-2), sin documentar. Todo el contenido de usuario ($item['title'], ['time'], ['description']) sale con `{{ }}` escapado: sin riesgo XSS. El bloque `<style>` está protegido con `@once`, así que N timelines en una página emiten el CSS una sola vez. `attributes->merge(['class' => 'muni-timeline'])` conserva la clase base si el consumidor pasa clases propias.

### `<x-muni::toast-host>`

Archivo: `resources/views/components/toast-host.blade.php` (78 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `position` | `'bottom-right'` | string | Acepta bottom-right\|bottom-left\|top-right\|top-left; cualquier otro valor cae a bottom-right. |

**Slots:** ninguno.
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-border`, `--muni-danger-fg`, `--muni-dur`, `--muni-ease`, `--muni-font-sans`, `--muni-glow`, `--muni-hint`, `--muni-info-fg`, `--muni-muted`, `--muni-ok-fg`, `--muni-radius`, `--muni-radius-sm`, `--muni-shadow-lg`, `--muni-surface`, `--muni-text`, `--muni-warn-fg`.
**Alpine:** `x-data (estado: items[], push(detail), remove(id))`, `@muni-toast.window`, `x-for + :key sobre <template>`, `x-if sobre <template> (título opcional)`, `x-text (item.title, item.message)`, `:class ('muni-toast--' + item.tone)`, `x-transition:enter / enter-start / enter-end`, `x-transition:leave / leave-start / leave-end`, `@click (remove(item.id))`. Solo core. Eventos: `ESCUCHA: `muni-toast` en `window` (línea 28, `@muni-toast.window="push($event.detail || {})"`). Payload `detail`: { message: string, tone?: 'ok'|'warn'|'danger'|'info' (default 'info'), title?: string|null, duration?: number ms (default 4500) }. Se dispara con `$dispatch('muni-toast', {...})` desde Alpine/Livewire o con `window.dispatchEvent(new CustomEvent('muni-toast', { detail: {...} }))` desde JS, tal como documenta el comentario Blade de las líneas 14-17.`, `EMITE: ninguno. El componente no despacha eventos propios; cerrar un toast solo muta el array local.`.

**Bloque `<style>` propio:** sí; reglas de color: `.muni-toast { background:var(--muni-surface); border:1px solid var(--muni-border); border-left-width:3px; box-shadow:var(--muni-shadow-lg) }`; `.muni-toast__dot { box-shadow:var(--muni-glow) }`; `.muni-toast__title { color:var(--muni-text) }`; `.muni-toast__msg { color:var(--muni-muted) }`; `.muni-toast__x { background:transparent; color:var(--muni-hint) }`; `.muni-toast__x:hover { color:var(--muni-text) }`; `.muni-toast--ok { border-left-color:var(--muni-ok-fg) } / .muni-toast--ok .muni-toast__dot { background+color:var(--muni-ok-fg) }`; `.muni-toast--warn { border-left-color:var(--muni-warn-fg) } / dot background+color:var(--muni-warn-fg)`; `.muni-toast--danger { border-left-color:var(--muni-danger-fg) } / dot background+color:var(--muni-danger-fg)`; `.muni-toast--info { border-left-color:var(--muni-info-fg) } / dot background+color:var(--muni-info-fg)`. Contraparte `.dark`: **NO**.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `El CONTENEDOR fijo (línea 18) lleva `aria-live="polite"` y `aria-atomic="false"`, y CADA toast lleva `role="status"` (línea 43). El punto de tono lleva `aria-hidden="true"`. Hay REDUNDANCIA: `role="status"` implica `aria-live="polite"`, y está anidado dentro de otra región viva; el anuncio lo hace la región externa y en algunos lectores la región anidada puede duplicar el anuncio. FALTA: un toast de tono `danger` se sigue anunciando como `polite`; no hay `role="alert"` ni `aria-live="assertive"` para errores, así que un mensaje de error puede quedar en cola detrás de otro discurso.` · teclado: SÍ se puede cerrar con teclado: cada toast trae un `<button type="button">` real (línea 50) con `@click="remove(item.id)"`, alcanzable con Tab y activable con Enter y Espacio. PERO: (1) no hay manejador de Escape en ninguna parte del componente; (2) el foco NO se mueve al toast al aparecer, así que el usuario de teclado debe tabular hasta el final del documento para llegar; (3) el autocierre por `setTimeout` a los 4500 ms elimina el `<button>` aunque lo tenga enfocado, y el foco se pierde al `<body>`. Ese autocierre incumple WCAG 2.2.1 (Timing Adjustable): no hay pausa al hover, ni pausa al foco, ni forma de detener o extender el temporizador desde la interfaz — solo el emisor del evento puede cambiar `detail.duration`. · focus-visible: no · reduced-motion: sí · etiqueta: El botón de cierre tiene `aria-label="Cerrar"`, pero está en español literal en la plantilla, sin `__()`, así que no es traducible ni localizable. La región viva no tiene nombre accesible (`aria-label`), por lo que no se anuncia como 'notificaciones'. El mensaje SÍ se anuncia: la región viva existe en el DOM desde la carga (el host se coloca una vez en el shell) y los toasts se insertan después, que es la condición necesaria para que el lector de pantalla lo lea..
**Depende de:** Alpine 3 (x-data, x-for, x-if, x-transition, x-text); resources/css/muni-ui.css (tokens --muni-*); Emisores externos que despachen el evento window `muni-toast`; Debe colocarse UNA sola vez por página (típicamente dentro de <x-muni::app-shell>).
**README:** documentado · **Tests:** sin prueba
**Notas:** El grep de colores literales no devuelve NINGUNA coincidencia. `$attributes` NUNCA se renderiza: cualquier atributo que el consumidor pase a `<x-muni::toast-host>` (id, class, style, data-*) se descarta en silencio; el `style` del contenedor está codificado en la línea 29 y solo es configurable vía la prop `position`. El `@once` protege el `<style>`, pero NO evita que dos instancias del host reaccionen ambas al mismo evento `muni-toast` y muestren el toast duplicado. Sin riesgo XSS: título y mensaje se pintan con `x-text`, no con `x-html`, así que un payload con HTML se muestra como texto. Bug menor: `detail.duration || 4500` hace que `duration: 0` (toast persistente) caiga al valor por defecto de 4500 ms; para un toast que no se autocierre hay que pasar un número muy grande. El id se genera con `Date.now() + Math.random()`, suficiente para evitar colisiones de `:key`. No hay límite de toasts simultáneos: una ráfaga de eventos apila indefinidamente hacia arriba y puede desbordar la pantalla.

### `<x-muni::tooltip>`

Archivo: `resources/views/components/tooltip.blade.php` (24 líneas) · verificado con 1 corrección(es)

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `text` | `''` | string | Contenido de la burbuja; escapado con {{ }}. Vacío renderiza burbuja sin texto. |
| `placement` | `'top'` | string | top\|bottom\|left\|right; valor desconocido cae a la posición 'bottom', no a 'top'. |

**Slots:** `default` (obligatorio) — El elemento disparador que se envuelve (botón, icono, enlace). Es obligatorio en la práctica: sin contenido no hay nada que apuntar ni sobre lo que posar el cursor, y para que el tooltip aparezca con FOCO el slot debe contener un elemento realmente enfocable (los @focusin/@focusout viven en el span contenedor y dependen del burbujeo desde dentro).
**`$attributes` en la raíz:** no.
**Tokens `--muni-*`:** `--muni-font-sans`, `--muni-on-accent`, `--muni-radius-sm`, `--muni-shadow-md`, `--muni-text`.
**Alpine:** `x-data (estado: { show:false })`, `@mouseenter / @mouseleave`, `@focusin / @focusout`, `x-show`, `x-cloak`, `x-transition.opacity`. Solo core. Eventos: `ESCUCHA: eventos DOM nativos del propio elemento — mouseenter, mouseleave, focusin, focusout. No escucha eventos de window ni de Livewire.`, `EMITE: ninguno.`.
**Modo oscuro:** ✅ todo por tokens
**A11y:** roles/aria: `Tiene `role="tooltip"` en la burbuja (línea 22). FALTA lo esencial: NO hay `aria-describedby` ni `id`. La burbuja no está asociada programáticamente al disparador, y `role="tooltip"` por sí solo no hace nada sin una referencia desde el elemento que describe. Consecuencia real: un lector de pantalla NO anuncia el texto del tooltip; el contenido de `$text` es invisible para tecnología asistiva. Además la burbuja aparece con `x-show` (display:none/block), así que ni siquiera queda en el árbol de accesibilidad cuando está oculta.` · teclado: SÍ aparece con foco además de con hover: los manejadores `@focusin`/`@focusout` de la línea 17 conviven con `@mouseenter`/`@mouseleave`, y focusin/focusout burbujean, así que enfocar el disparador dentro del slot muestra la burbuja. NO se cierra con Escape: no existe `@keydown.escape` ni `@keydown.window.escape` en ninguna parte del archivo. Eso incumple WCAG 2.2 1.4.13 (Content on Hover or Focus), que exige que el contenido sea descartable sin mover el puntero ni el foco. · focus-visible: sí · reduced-motion: no · etiqueta: El tooltip no aporta nombre accesible al disparador. Si el patrón de uso es 'icono sin texto + tooltip', ese botón queda SIN nombre accesible para el lector de pantalla, porque el texto solo existe visualmente. Falta el par `aria-describedby`/`id` o, para el caso de icono desnudo, un `aria-label` en el disparador..
**Depende de:** Alpine 3 (x-data, x-show, x-cloak, x-transition); resources/css/muni-ui.css (tokens --muni-* y la regla [x-cloak]{display:none!important} de la línea 23, imprescindible para que la burbuja no parpadee antes de que arranque Alpine).
**README:** documentado · **Tests:** sin prueba
**Notas:** El grep de colores literales SÍ marca la línea 23, pero es FALSO POSITIVO: coincide con `white` dentro de `white-space:nowrap`, no es un color. No hay literales de color reales. Sobre atributos: `{{ $attributes }}` (línea 19) se imprime CRUDO, sin `->merge()`, y además va DESPUÉS del `style="position:relative;display:inline-flex;"` codificado; como en HTML gana el primer atributo duplicado, un `style` que pase el consumidor se IGNORA por completo, y un `class` se aplica sin fusionarse con nada (aquí no hay clase base, así que no rompe). Sin `x-anchor`: la burbuja se posiciona con `position:absolute` respecto al envoltorio, de modo que (a) cualquier ancestro con `overflow:hidden` la recorta, y (b) no hay volteo automático al llegar al borde del viewport, así que un tooltip `top` en la primera fila de una tabla se sale por arriba. `white-space:nowrap` impide que el texto largo haga saltos de línea: un `text` largo se convierte en una tira horizontal que desborda. `pointer-events:none` en la burbuja significa que NO se puede llevar el cursor sobre ella, lo que incumple la parte 'hoverable' de WCAG 1.4.13 (a cambio, nunca bloquea clics de lo que hay debajo). El `placement` inválido cae a 'bottom' (línea 12) aunque el valor por defecto de la prop sea 'top': incoherencia menor entre el default de @props y el fallback del mapa.

<details><summary>Correcciones del verificador</summary>

- `attributes_merge`: el lector dijo «True»; el archivo dice «False» (segunda lectura independiente)

</details>

### `<x-muni::topbar>`

Archivo: `resources/views/components/topbar.blade.php` (43 líneas) · verificado sin discrepancias

| Prop | Default | Tipo | Qué hace |
|---|---|---|---|
| `system` | `(sin default — obligatoria)` | string | Declarada sin valor: si no se pasa, PHP lanza Undefined variable al renderizar. |
| `subtitle` | `null` | string|null | Segunda línea bajo el nombre del sistema; se omite el span si es null. |
| `status` | `'online'` | string | online\|degraded\|offline; mapea a tono ok\|warn\|danger del badge. |
| `statusLabel` | `null` | string|null | Sobrescribe el texto; sin él usa En línea\|Degradado\|Caído según status. |
| `logo` | `null` | string|Illuminate\View\ComponentSlot|null | Marca o img del escudo; si es null muestra el texto GRA. |

**Slots:** `default` — Se renderiza al final del <header> (línea 42), después del badge de estado: es la zona de acciones de la derecha (menú de usuario, botón de tema, notificaciones).; `logo` — Declarada en @props con default null, pero al estar declarada también se puede pasar como slot con nombre (<x-slot:logo>…</x-slot:logo>) para inyectar el <x-muni::gob-escudo> o un <img>. Va dentro del plato blanco fijo de la línea 23.
**`$attributes` en la raíz:** sí.
**Tokens `--muni-*`:** `--muni-border`, `--muni-font-mono`, `--muni-font-sans`, `--muni-muted`, `--muni-radius-sm`, `--muni-surface`, `--muni-text`, `--muni-topbar-h`.
**Alpine:** no (Blade puro).

| Color literal | Dónde | Uso | Seguro en oscuro | Motivo |
|---|---|---|---|---|
| `#fff` | Línea 23, atributo style del <div> contenedor del logo: `background:#fff` | Plato/lienzo blanco fijo de 38px de alto sobre el que se apoya el escudo municipal, para que el escudo se lea igual en tema claro y oscuro. El comentario Blade de las líneas 21-22 lo declara como patrón deliberado del ecosistema. | **NO** | Es un literal de color usado como FONDO y no cumple ninguna de las salidas de la definición: no es `var(--muni-*)` ni `var(--mg-*)`, no es currentColor/transparent/inherit, NO es un `--muni-gob-*` (aunque cumpla función de lienzo de logo, la paleta institucional invariante son los nueve `--muni-gob-*` de muni-ui.css líneas 32-40, y #fff no está entre ellos), y no existe ninguna regla `.dark` ni `[data-muni-theme="dark"]` en este archivo — de hecho topbar.blade.php no tiene bloque <style> en absoluto. No es velo ni sombra. Por tanto queda fuera del contrato de tematización: nadie puede re-tematizarlo sin editar la plantilla. |
| `#0b0f14` | Línea 27, atributo style del <span> de respaldo: `color:#0b0f14` en el texto 'GRA' | Color del texto de respaldo 'GRA' que se muestra cuando no se pasa la prop/slot `logo`. Se eligió para contrastar contra el plato blanco de la línea 23. El valor coincide con `--muni-bg` del tema oscuro (muni-ui.css líneas 113 y 160). | **NO** | Es un literal de color usado como TEXTO. Aunque el valor sea idéntico al del token `--muni-bg` en oscuro, está escrito a mano y no como `var(--muni-bg)` — y si lo estuviera sería peor, porque en tema claro `--muni-bg` es #f5f6f8 y el texto desaparecería sobre el plato blanco. No hay regla `.dark`/`[data-muni-theme="dark"]` en este archivo que lo acompañe. Fuera del contrato. |
**Modo oscuro:** ❌ Todo el cromo del header (fondo, borde, nombre del sistema, subtítulo, altura vía --muni-topbar-h que pasa de 56px a 52px en oscuro) usa tokens con par claro/oscuro y se invierte correctamente; el badge de estado delega en <x-muni::badge>, que también es 100% tokens. El componente FALLA el contrato en un solo punto, el plato del logo: el par `background:#fff` (línea 23) + `color:#0b0f14` (línea 27) son dos literales sin token ni regla `.dark`. Matiz importante: visualmente NO se rompe — al fijar fondo y texto a la vez, el par es autoconsistente y da ~18:1 en cualquier tema, y el comentario de las líneas 21-22 lo justifica como decisión de identidad (el escudo municipal necesita fondo claro). Pero por la definición estricta no es seguro: en modo oscuro queda una isla blanca de 38px de alto brillando contra `--muni-bg` #0b0f14, no se puede re-tematizar sin editar la plantilla, y si mañana se decide un plato distinto (p. ej. `--muni-gob-petroleo-dark` para el escudo en negativo) hay que tocar el HTML en vez de un token. Arreglo natural del ecosistema: promover el par a tokens (p. ej. `--muni-logo-plate` / `--muni-logo-plate-fg`) o usar los `--muni-gob-*` existentes, que sí son invariantes institucionales aceptados por el contrato.
**A11y:** roles/aria: `El `<header>` expone el landmark `banner` (siempre que no esté anidado dentro de <main>, <article>, <aside>, <nav> o <section>; si el consumidor lo mete dentro de <main> el landmark se pierde en silencio). No hay roles ARIA explícitos. El badge de estado es un <span> decorado, sin `role="status"` ni `aria-live`, así que un cambio de estado del sistema (online → offline) NO se anuncia a tecnología asistiva.` · teclado: El componente no aporta elementos interactivos propios: todo lo enfocable llega por el slot por defecto o por el slot logo. RIESGO REAL: `position:sticky; top:0; z-index:100` (línea 16) hace que el header se quede fijo sobre el contenido; nada en este componente (ni un `scroll-margin-top: var(--muni-topbar-h)` global, ni `scroll-padding-top` en el shell) compensa esa altura, así que al tabular hacia un elemento que queda justo bajo el borde superior el anillo de foco puede quedar TAPADO por el header. Es exactamente el caso que la regla del proyecto prohíbe ('foco visible siempre y no oculto tras headers fijos') y lo que WCAG 2.2 2.4.11 (Focus Not Obscured) cubre. · focus-visible: no · reduced-motion: no · etiqueta: Sin `aria-label` en el `<header>`: si la página tiene más de un banner o el consumidor necesita distinguirlos, no hay nombre. El texto 'GRA' de respaldo (línea 27) es una abreviatura sin `<abbr>` ni `aria-label`, y algunos lectores la deletrean. Sobre el uso del color: el badge de estado SÍ lleva texto ('En línea' / 'Degradado' / 'Caído'), así que el color NO es el único portador de información — esto está bien resuelto. Punto flojo: `{{ $system }}` y `{{ $subtitle }}` se truncan con `overflow:hidden; text-overflow:ellipsis` (líneas 32 y 34) sin atributo `title`, de modo que un nombre largo queda cortado y sin forma de leerlo completo con el ratón. El slot `logo`, al ser contenido arbitrario del consumidor, no tiene garantía de `alt`: si se pasa un <img> sin alt, el escudo queda sin texto alternativo y este componente no lo obliga ni lo advierte..
**Depende de:** <x-muni::badge> del mismo paquete (línea 40, con :tone) — no es autónomo; resources/css/muni-ui.css (tokens --muni-*, en particular --muni-topbar-h); Opcionalmente <x-muni::gob-escudo> como contenido del slot logo; Laravel 12/13 para el merge inteligente de `style` en ComponentAttributeBag (composer.json exige illuminate/support ^12.0\|^13.0, así que está cubierto).
**README:** documentado · **Tests:** sin prueba
**Notas:** El grep de colores literales devuelve cuatro líneas, pero solo DOS son colores reales: las líneas 32 y 34 son FALSOS POSITIVOS (coinciden con `white` dentro de `white-space:nowrap`). Los literales verdaderos son #fff (línea 23) y #0b0f14 (línea 27). `$attributes->merge(['style' => ...])` fusiona de verdad en Laravel 12/13: los estilos por defecto se anteponen y el `style` del consumidor gana en caso de conflicto, así que se puede cambiar el z-index o quitar el sticky desde fuera. Todo el contenido de usuario ($system, $subtitle, $statusText) sale escapado con `{{ }}`; el slot `logo` y el slot por defecto son HTML crudo del consumidor, responsabilidad de quien los pasa. `$statusText` cae al valor literal de `$status` si se pasa un estado desconocido (línea 11), mientras que `$statusTone` cae a 'danger' en ese mismo caso: un estado no previsto se pinta en rojo mostrando la cadena cruda. No hay `@once` ni bloque <style>, coherente con que todo el estilado sea inline.


## Discrepancias residuales (verificador vs `grep`)

Cuando el verificador y `grep` no coincidieron en la lista de tokens, **manda `grep`** y la tabla de
arriba ya lo refleja; se deja constancia acá.

- Ninguna: los 52 registros coinciden con `grep`.

---

# F1 — Contrato de tokens y auditoría de reglas de color (laravel-muni-ui)

Fuentes leídas línea por línea (sin README ni memoria):

- `/home/cesar/Dev/laravel-muni-ui/resources/css/muni-ui.css` (246 líneas)
- `/home/cesar/Dev/laravel-muni-ui/resources/css/muni-ui-tailwind.css` (41 líneas)
- `/home/cesar/Dev/laravel-muni-ui/resources/css/muni-ui-filament.css` (398 líneas)

Contexto de carga verificado en el código: `src/Filament/MuniPanel.php:73-75` inyecta **solo** `vendor/muni-ui/filament.css` en `PanelsRenderHook::STYLES_AFTER`; `src/MuniUiServiceProvider.php:24` publica `muni-ui.css` a `resources/css/vendor/muni-ui.css` para que el host lo compile en su Vite. Dentro de un panel, por tanto, un token `--muni-*` que el puente Filament no defina resuelve a nada (salvo que el host lo haya compilado por su cuenta).

Los contrastes citados se calcularon con la fórmula de luminancia relativa W3C (sRGB) a partir de los hex de los archivos.

---

## Tokens `--muni-*` (muni-ui.css)

Bloques comparados:

- **light `:root`**: identidad `:root` (l. 31-41) + valores por defecto `:root` (l. 44-105).
- **media**: `@media (prefers-color-scheme: dark) { :root:not([data-muni-theme="light"]):not([data-theme="light"]):not(.light) {…} }` (l. 111-152).
- **dark**: `[data-muni-theme="dark"], [data-theme="dark"], .dark {…}` (l. 157-198).
- **dm-light**: `[data-muni-theme="light"] {…}` (l. 203-241).

Total: **49 tokens** (9 `--muni-gob-*` + 40 de tema). Los tres bloques dark tienen 35 tokens cada uno, idénticos entre sí; `[data-muni-theme="light"]` tiene los mismos 35 con los valores exactos de `:root`. Los 14 restantes (9 `gob`, 3 `radius`, `ease`, `dur`) no aparecen en ningún bloque dark ni en el override light: son invariantes de tema y heredan de `:root`, lo cual es coherente.

**Tokens con los cuatro bloques inconsistentes: ninguno.** (Verificado por diff programático, no a ojo.)

| nombre | categoría | valor light (`:root`) | valor dark (`.dark`) | ¿idéntico en `@media` y `[data-muni-theme="dark"]`? | ¿`[data-muni-theme="light"]` = `:root`? |
|---|---|---|---|---|---|
| `--muni-gob-lima` | identidad | `#adcd60` | — (no se redefine; hereda `:root`) | n/a (ausente en los 3 bloques dark) | n/a (ausente, hereda `:root`) |
| `--muni-gob-verde-dark` | identidad | `#7fa33f` | — | n/a | n/a |
| `--muni-gob-petroleo` | identidad | `#355a63` | — | n/a | n/a |
| `--muni-gob-petroleo-dark` | identidad | `#00404c` | — | n/a | n/a |
| `--muni-gob-oro` | identidad | `#eab02c` | — | n/a | n/a |
| `--muni-gob-naranja` | identidad | `#c76421` | — | n/a | n/a |
| `--muni-gob-celeste` | identidad | `#7ccbe1` | — | n/a | n/a |
| `--muni-gob-carmin` | identidad | `#ca3048` | — | n/a | n/a |
| `--muni-gob-gris` | identidad | `#9c9b9b` | — | n/a | n/a |
| `--muni-bg` | superficie | `#f5f6f8` | `#0b0f14` | sí | sí |
| `--muni-surface` | superficie | `#ffffff` | `#111620` | sí | sí |
| `--muni-surface-2` | superficie | `#f5f6f8` | `#181e2a` | sí | sí |
| `--muni-surface-3` | superficie | `#eceef2` | `#1c2333` | sí | sí |
| `--muni-panel` | superficie | `#ffffff` | `#1c2333` | sí | sí |
| `--muni-text` | texto | `#1a1d24` | `#e2e8f4` | sí | sí |
| `--muni-muted` | texto | `#5a6070` | `#7a8ba8` | sí | sí |
| `--muni-hint` | texto | `#6b7280` | `#7a8ba8` | sí | sí |
| `--muni-border` | borde | `#e0e3ea` | `#2a3347` | sí | sí |
| `--muni-border-2` | borde | `#c8ccd6` | `#3a4560` | sí | sí |
| `--muni-accent` | acento | `#0f766e` | `#f59e0b` | sí | sí |
| `--muni-accent-strong` | acento | `#115e59` | `#d97706` | sí | sí |
| `--muni-accent-soft` | acento | `#f0fdfa` | `#2d1d00` | sí | sí |
| `--muni-on-accent` | acento | `#ffffff` | `#0b0f14` | sí | sí |
| `--muni-ok-fg` | estado | `#0f7a5a` | `#22c55e` | sí | sí |
| `--muni-ok-bg` | estado | `#e2f5ee` | `#0d2d1a` | sí | sí |
| `--muni-ok-border` | estado | `#0f7a5a` | `#166534` | sí | sí |
| `--muni-warn-fg` | estado | `#8a5a00` | `#f59e0b` | sí | sí |
| `--muni-warn-bg` | estado | `#fef3dc` | `#2d1d00` | sí | sí |
| `--muni-warn-border` | estado | `#8a5a00` | `#92400e` | sí | sí |
| `--muni-danger-fg` | estado | `#b03030` | `#ef4444` | sí | sí |
| `--muni-danger-bg` | estado | `#fdeaea` | `#2d0d0d` | sí | sí |
| `--muni-danger-border` | estado | `#b03030` | `#991b1b` | sí | sí |
| `--muni-info-fg` | estado | `#1f5fb0` | `#3b82f6` | sí | sí |
| `--muni-info-bg` | estado | `#e6effb` | `#0d1a2d` | sí | sí |
| `--muni-info-border` | estado | `#1f5fb0` | `#1e3a5f` | sí | sí |
| `--muni-radius` | radio | `10px` | — (invariante, hereda `:root`) | n/a | n/a |
| `--muni-radius-lg` | radio | `14px` | — | n/a | n/a |
| `--muni-radius-sm` | radio | `7px` | — | n/a | n/a |
| `--muni-shadow` | sombra | `0 1px 2px rgba(16, 24, 40, 0.04), 0 2px 8px rgba(16, 24, 40, 0.06)` | `0 1px 2px rgba(0, 0, 0, 0.3), 0 2px 8px rgba(0, 0, 0, 0.35)` | sí | sí |
| `--muni-shadow-md` | sombra | `0 4px 16px rgba(16, 24, 40, 0.10)` | `0 8px 24px rgba(0, 0, 0, 0.45)` | sí | sí |
| `--muni-shadow-lg` | sombra | `0 12px 32px rgba(16, 24, 40, 0.14)` | `0 16px 40px rgba(0, 0, 0, 0.55)` | sí | sí |
| `--muni-ring` | foco | `0 0 0 3px color-mix(in srgb, var(--muni-accent) 30%, transparent)` | `0 0 0 3px color-mix(in srgb, var(--muni-accent) 35%, transparent)` | sí | sí |
| `--muni-focus` | foco | `#0f766e` | `#f59e0b` | sí | sí |
| `--muni-ease` | movimiento | `cubic-bezier(0.22, 1, 0.36, 1)` | — (invariante) | n/a | n/a |
| `--muni-dur` | movimiento | `160ms` (`0ms` bajo `prefers-reduced-motion: reduce`, l. 245) | — (invariante) | n/a | n/a |
| `--muni-font-sans` | tipografía | `'DM Sans', system-ui, sans-serif` | `'IBM Plex Sans', system-ui, sans-serif` | sí | sí |
| `--muni-font-mono` | tipografía | `'DM Mono', ui-monospace, monospace` | `'IBM Plex Mono', ui-monospace, monospace` | sí | sí |
| `--muni-topbar-h` | layout | `56px` | `52px` | sí | sí |
| `--muni-glow` | sombra | `none` | `0 0 6px currentColor` | sí | sí |

Notas de contraste medidas sobre este archivo (ver Hallazgos 13 y 12):

- `--muni-hint #6b7280` sobre `--muni-surface #ffffff` = 4,83:1 (AA); sobre `--muni-bg #f5f6f8` = **4,47:1**; sobre `--muni-surface-3 #eceef2` = **4,16:1** (ambos bajo 4,5:1).
- `--muni-muted #5a6070` sobre `#eceef2` = 5,41:1. Dark: `--muni-muted #7a8ba8` sobre `--muni-surface-3 #1c2333` = 4,55:1 (justo).
- Pares fg/bg de estado: light ok 4,69 · warn 5,38 · danger 5,48 · info 5,45; dark ok 6,55 · warn 7,60 · danger 4,75 · info 4,75. Todos ≥ 4,5:1.
- `--muni-on-accent` sobre `--muni-accent`: light 5,47:1, dark 8,95:1.
- `--muni-border` / `--muni-border-2` sobre `--muni-surface`: light 1,28 / 1,61; dark 1,43 / 1,90. Están muy por debajo de 3:1; si algún componente los usa como **único** límite de un control, cae 1.4.11 (a verificar en la auditoría de componentes, no es defecto del token en sí).

---

## Jerarquía de tema

Orden real de la cascada tal como está escrita en `muni-ui.css`:

1. **Light por defecto** — `:root { … }` (l. 31-41 identidad, l. 44-105 tema). Especificidad (0,1,0).
2. **Regla 1 — preferencia del SO** (l. 111-152):
   `@media (prefers-color-scheme: dark) { :root:not([data-muni-theme="light"]):not([data-theme="light"]):not(.light) { … } }`.
   Especificidad (0,4,0): `:root` + tres `:not()` con un selector simple cada uno. Solo aplica al elemento raíz; los tres `:not()` la desactivan cuando la raíz lleva cualquiera de los activadores explícitos de light (`data-muni-theme="light"`, `data-theme="light"`, clase `light`).
3. **Regla 2 — activadores explícitos de dark** (l. 157-198):
   `[data-muni-theme="dark"], [data-theme="dark"], .dark { … }`. Especificidad (0,1,0) cada uno; aplican en cualquier elemento, no solo en `:root`. El comentario (l. 154-156) dice que "ganan sobre la preferencia del SO por venir después"; en rigor la regla del SO tiene mayor especificidad (0,4,0) cuando ambas caen sobre `:root`, y "gana" la del SO. No se nota porque los 35 valores son byte a byte idénticos (verificado en la tabla anterior). Ver Hallazgo 18.
4. **Regla 3 — override explícito a light** (l. 203-241):
   `[data-muni-theme="light"] { … }`. Especificidad (0,1,0), **última en el archivo**: a igual especificidad gana por orden sobre `.dark`/`[data-theme="dark"]`/`[data-muni-theme="dark"]` puestos en el **mismo** elemento. Sobre un ancestro `.dark` gana por herencia (el valor declarado en el elemento más cercano manda). Si `.dark` está en un **descendiente** del elemento con `data-muni-theme="light"`, gana `.dark` (proximidad), lo cual es coherente con el comentario de l. 200-202 ("aunque Filament ponga `.dark` en un ancestro").
   Asimetría: `.light` y `[data-theme="light"]` **no traen valores**; solo excluyen la regla del SO en `:root`. Puestos en un descendiente de `.dark` no hacen nada. El único congelador real es `[data-muni-theme="light"]`.
5. **`prefers-reduced-motion`** (l. 244-246): `@media (prefers-reduced-motion: reduce) { :root { --muni-dur: 0ms; } }`. Solo anula `--muni-dur`; `--muni-ease` queda intacto (irrelevante con duración 0). Vive únicamente en este archivo: `muni-ui-filament.css` fija `--muni-dur: 160ms` en su propio `:root` (l. 89) y su `@media (prefers-reduced-motion:reduce)` (l. 260) solo apaga la animación `mg-fall` de `.fi-wi, .fi-section, .fi-ta`. Consecuencia en Hallazgo 9.

Extra: `[x-cloak] { display: none !important; }` (l. 23) vive en este archivo, por lo que un panel que solo cargue el tema Filament depende de que Alpine/Filament ya lo aporten.

---

## Puente `--muni-*` dentro del tema Filament (muni-ui-filament.css)

El puente vive en `:root { … }` (l. 6-96) y `.dark { … }` (l. 100-139). **47** tokens `--muni-*` en `:root`; **30** redefinidos en `.dark`. Ningún `--mg-*` se redefine en `.dark`, así que todo `var(--mg-*)` que aparezca en la columna light es un valor fijo en ambos modos.

| token | valor light (`:root`) | valor `.dark` | línea light |
|---|---|---|---|
| `--muni-text` | `var(--mg-tinta)` (= `#20302f`) | `#eaf5f3` | 28 |
| `--muni-muted` | `#5c6f6d` | `#94a3b8` | 29 |
| `--muni-hint` | `#5f6e6c` | `#7e8ea0` | 30 |
| `--muni-bg` | `#f8f5ec` | `#081418` | 32 |
| `--muni-surface` | `#ffffff` | `#0f2025` | 33 |
| `--muni-surface-2` | `var(--mg-papel)` (= `#f6f3ea`) | `#0d1c20` | 34 |
| `--muni-surface-3` | `var(--mg-papel-2)` (= `#efece1`) | `#142a30` | 35 |
| `--muni-border` | `var(--mg-borde)` (= `#e2ddce`) | `#1c343a` | 37 |
| `--muni-border-2` | `#cfc7b3` | `#2a4a52` | 38 |
| `--muni-accent` | `var(--mg-petroleo)` (= `#355a63`) | `var(--mg-celeste)` (= `#7ccbe1`) | 40 |
| `--muni-accent-strong` | `var(--mg-petroleo-d)` (= `#0d3a44`) | `var(--mg-lima-br)` (= `#c3e07a`) | 41 |
| `--muni-accent-soft` | `color-mix(in srgb, var(--mg-petroleo) 10%, #fff)` | `color-mix(in srgb, var(--mg-celeste) 14%, transparent)` | 42 |
| `--muni-on-accent` | `#ffffff` | `#08252b` | 43 |
| `--muni-focus` | `var(--mg-petroleo)` | `var(--mg-celeste)` | 51 |
| `--muni-ok-fg` | `#4a6b1f` | `var(--mg-lima)` (= `#adcd60`) | 53 |
| `--muni-ok-bg` | `#e6f0da` | `color-mix(in srgb, var(--mg-lima) 18%, #0f2025)` | 54 |
| `--muni-ok-border` | `#4a6b1f` | `var(--mg-lima)` | 55 |
| `--muni-warn-fg` | `#8a5a00` | `var(--mg-oro-br)` (= `#f5c542`) | 56 |
| `--muni-warn-bg` | `#f7ecd2` | `color-mix(in srgb, var(--mg-oro-br) 18%, #0f2025)` | 57 |
| `--muni-warn-border` | `#8a5a00` | `var(--mg-oro-br)` | 58 |
| `--muni-info-fg` | `#1d5f75` | `var(--mg-celeste)` | 59 |
| `--muni-info-bg` | `#dce8f0` | `color-mix(in srgb, var(--mg-celeste) 18%, #0f2025)` | 60 |
| `--muni-info-border` | `#1d5f75` | `var(--mg-celeste)` | 61 |
| `--muni-danger-fg` | `#a3132a` | `#ff8195` | 62 |
| `--muni-danger-bg` | `#f7dde1` | `color-mix(in srgb, #ff8195 18%, #0f2025)` | 63 |
| `--muni-danger-border` | `#e0a3ad` | `#6b2430` | 64 |
| `--muni-gob-lima` | `var(--mg-lima)` (= `#adcd60`) | — (no redefinido; hereda light, invariante) | 69 |
| `--muni-gob-petroleo` | `var(--mg-petroleo)` (= `#355a63`) | — | 70 |
| `--muni-gob-petroleo-dark` | `var(--mg-petroleo-d)` (= **`#0d3a44`**, en muni-ui.css es `#00404c`) | — | 71 |
| `--muni-gob-oro` | `var(--mg-oro)` (= `#eab02c`) | — | 72 |
| `--muni-gob-naranja` | `var(--mg-naranja)` (= `#c76421`) | — | 73 |
| `--muni-gob-celeste` | `var(--mg-celeste)` (= `#7ccbe1`) | — | 74 |
| `--muni-gob-carmin` | `var(--mg-carmin)` (= `#ca3048`) | — | 75 |
| `--muni-gob-gris` | `#9c9b9b` (literal; no existe `--mg-gris`) | — | 76 |
| `--muni-radius` | `10px` | — (invariante) | 78 |
| `--muni-radius-sm` | `7px` | — | 79 |
| `--muni-radius-lg` | `14px` | — | 80 |
| `--muni-shadow` | `0 1px 2px rgba(16,24,40,.04), 0 2px 8px rgba(16,24,40,.06)` | `0 1px 2px rgba(0,0,0,.30), 0 2px 8px rgba(0,0,0,.35)` | 82 |
| `--muni-shadow-md` | `0 4px 16px rgba(16,24,40,.10)` | `0 4px 16px rgba(0,0,0,.40)` | 83 |
| `--muni-shadow-lg` | `0 12px 32px rgba(16,24,40,.14)` | `0 12px 32px rgba(0,0,0,.50)` | 84 |
| `--muni-ring` | `0 0 0 3px color-mix(in srgb, var(--muni-accent) 30%, transparent)` | — (no redefinido; re-resuelve a celeste solo porque `.dark` va en `<html>`, ver Hallazgo 23) | 85 |
| `--muni-glow` | `none` | — (no redefinido: sigue `none`; en muni-ui.css dark es `0 0 6px currentColor`) | 86 |
| `--muni-ease` | `cubic-bezier(.22, 1, .36, 1)` | — (invariante) | 88 |
| `--muni-dur` | `160ms` | — (invariante; sin `prefers-reduced-motion`) | 89 |
| `--muni-topbar-h` | `56px` | `52px` | 90 |
| `--muni-font-sans` | `inherit` | — (intencional: manda el panel) | 94 |
| `--muni-font-mono` | `ui-monospace, SFMono-Regular, Menlo, monospace` | — | 95 |

### Tokens de muni-ui.css NO puenteados en light (`:root` del tema Filament)

- `--muni-panel` (muni-ui.css l. 49) — ausente por completo. Dentro del panel resuelve a nada. Hoy ningún componente en `resources/views/components` lo consume (grep), así que el hueco es latente.
- `--muni-gob-verde-dark` (muni-ui.css l. 33) — ausente; no existe `--mg-*` equivalente. Sin consumidores hoy.

### Tokens de muni-ui.css NO redefinidos en `.dark` del tema Filament

Ausentes en ambos bloques (huecos reales): `--muni-panel`, `--muni-gob-verde-dark`.

Presentes solo en `:root` (dentro de `.dark` heredan el valor light):

- Invariantes por diseño, coherentes con muni-ui.css: `--muni-gob-lima`, `--muni-gob-petroleo`, `--muni-gob-petroleo-dark`, `--muni-gob-oro`, `--muni-gob-naranja`, `--muni-gob-celeste`, `--muni-gob-carmin`, `--muni-gob-gris`, `--muni-radius`, `--muni-radius-lg`, `--muni-radius-sm`, `--muni-ease`, `--muni-dur`.
- Intencionales del tema (documentados en l. 92-93): `--muni-font-sans`, `--muni-font-mono`.
- `--muni-ring`: cuenta como con contraparte indirecta (referencia `var(--muni-accent)`, que sí cambia en `.dark`), con la salvedad del Hallazgo 23.
- **Divergente respecto de muni-ui.css**: `--muni-glow` queda `none` en oscuro; en muni-ui.css dark vale `0 0 6px currentColor`. Consumidores: `badge.blade.php:26`, `timeline.blade.php:36`, `toast-host.blade.php:62`, `command-palette.blade.php:51`, `rating.blade.php:40`, `chart-donut.blade.php:50`, `error-page.blade.php:29`.

### Divergencias de valor entre muni-ui.css y el puente (mismo token, otro valor)

- `--muni-gob-petroleo-dark`: `#00404c` (muni-ui.css:35) vs `#0d3a44` (filament:71 → `--mg-petroleo-d`, l. 7). Es un token declarado "invariante institucional". Consumidores: `gob-bar.blade.php:29` y `gob-footer.blade.php:53` (`background:var(--muni-gob-petroleo-dark)`).
- `--muni-shadow-md` dark: `0 8px 24px rgba(0,0,0,.45)` vs `0 4px 16px rgba(0,0,0,.40)`; `--muni-shadow-lg` dark: `0 16px 40px rgba(0,0,0,.55)` vs `0 12px 32px rgba(0,0,0,.50)`.
- Los demás (texto, superficies, acento, estados) divergen a propósito: es el tema del panel.

---

## Tokens `--mg-*`

**16 tokens**, todos en `:root` (l. 7-11). **Ninguno** se redefine en `.dark`: la columna "valor dark" es el mismo valor heredado. Las líneas de "uso" excluyen menciones en comentarios.

| nombre | valor light | valor dark | uso (líneas del mismo archivo) |
|---|---|---|---|
| `--mg-lima` | `#adcd60` | idem (no se redefine) | 69, 122, 123, 124, 160, 206, 218, 233, 267 |
| `--mg-lima-br` | `#c3e07a` | idem | 114, 169, 179, 180, 248, 293, 323, 324 |
| `--mg-petroleo` | `#355a63` | idem | 40, 42, 51, 70, 197, 198, 206, 213, 214, 218, 226, 227, 228, 233, 267 |
| `--mg-petroleo-d` | `#0d3a44` | idem | 41, 71, 204, 215, 217, 235 |
| `--mg-tinta-d` | `#08252b` | idem | **ninguno** (el literal `#08252b` se repite a mano en l. 116) |
| `--mg-oro` | `#eab02c` | idem | 72, 180, 182, 206, 233, 267 |
| `--mg-oro-br` | `#f5c542` | idem | 125, 126, 127, 157 |
| `--mg-naranja` | `#c76421` | idem | 73, 267 |
| `--mg-celeste` | `#7ccbe1` | idem | 74, 113, 115, 120, 128, 129, 130, 267 |
| `--mg-carmin` | `#ca3048` | idem | 75, 267 |
| `--mg-papel` | `#f6f3ea` | idem | 34, 195, 227 |
| `--mg-papel-2` | `#efece1` | idem | 35 |
| `--mg-borde` | `#e2ddce` | idem | 37, 195, 196, 210, 225, 234, 268 |
| `--mg-tinta` | `#20302f` | idem | 28, 216 |
| `--mg-sb-txt` | `#cfe3e1` | idem | 165 |
| `--mg-sb-mut` | `#7ea3a3` | idem | 161, 166, 185, 191, 292 |

Contrastes de apoyo: `--mg-sb-txt` sobre el sidebar (`#0b2f37`) = 10,66:1; `--mg-sb-mut` = 5,19:1; `--mg-oro-br` = 8,77:1; badge `#3a2a05` sobre `--mg-oro` = 7,10:1; `--mg-sb-mut` sobre tarjeta oscura `#0f2025` = 6,11:1 (coincide con la nota de l. 285-287).

---

## Auditoría de contraparte `.dark` en muni-ui-filament.css

Criterio: regla que fija `color`, `background`, `background-color`, `background-image`, `border`, `border-color`, `fill`, `stroke`, `box-shadow` o `text-shadow` con literal o con token light-only (todo `--mg-*` lo es; `--muni-*` puenteado que cambia en `.dark` cuenta como contraparte). `filter: drop-shadow(...)` (l. 153, 189) queda fuera del alcance de propiedades pero es invariante. No hay `fill`, `stroke` ni `text-shadow` en el archivo.

Recordatorio de cascada aplicado: las reglas de este archivo van sin `@layer` y se cargan en `STYLES_AFTER`; según la nota de l. 310-312 las oscuras de Filament viven en `@layer components`, así que cualquier regla de aquí sin contraparte pisa la oscura de Filament aunque tenga menor especificidad. Un `!important` gana a una declaración normal sin importar la especificidad.

### A. Sin contraparte `.dark` y con efecto visible en oscuro

| selector | propiedad | valor | línea | ¿contraparte `.dark`? | riesgo |
|---|---|---|---|---|---|
| `.fi-input-wrp` | `border` | `1px solid var(--mg-borde)` (`#e2ddce`) | 225 | **No** en reposo (solo `:focus-within` en l. 322) | **borde**: todo input en oscuro queda con borde beige 12,3:1 sobre `#0f2025`, encima del ring de Filament |
| `.fi-topbar .fi-input-wrp, .fi-global-search-field .fi-input-wrp` | `border`; `box-shadow` | `1px solid var(--mg-borde)`; `inset 0 1px 2px rgba(13,58,68,.05)` | 196 | **No** | **borde** (buscador global sobre topbar oscura `#0d1c20`) |
| `.fi-dropdown-panel` | `border`; `box-shadow` | `1px solid var(--mg-borde)`; `0 24px 54px -26px rgba(13,58,68,.45) !important` | 234 | **No** (el fondo lo oscurece Filament; el borde no) | **borde** (menús desplegables) |
| `.fi-simple-main` | `border`; `box-shadow` | `1px solid var(--mg-borde) !important`; `0 34px 80px -38px rgba(13,58,68,.55) !important` | 268 | **No** (l. 321 solo cubre `.fi-simple-layout`) | **borde** (tarjeta de login en oscuro, con `!important`) |
| `.filepond--root.has-video-preview .filepond--file-info` | `border-bottom` | `1px solid #e2e8f0` | 381 | **No** (l. 380 solo cubre `.filepond--item-panel`) | **borde**: línea clara 14,4:1 sobre `#18181b` |
| `.fi-ta-row:hover` | `background` | `color-mix(in srgb,var(--mg-petroleo) 4%,transparent)` | 228 | **No**; pisa el hover oscuro de Filament (capa) | **superficie**: feedback de hover imperceptible en oscuro |
| `.fi-wi-stats-overview-stat` | `border-top` | `3px solid var(--mg-petroleo) !important` | 213 | **Pisada**, no complementada: `.dark .fi-wi-stats-overview-stat { border-color:#1c343a !important }` (l. 272, mayor especificidad) recolorea también el borde superior | decorativo: el acento petróleo desaparece en oscuro |
| `.fi-topbar .fi-avatar` | `box-shadow` | `0 0 0 2px #fff, 0 0 0 3px color-mix(in srgb,var(--mg-petroleo) 55%,transparent)` | 198 | **No** | decorativo (halo blanco sobre topbar oscura) |
| `.fi-wi-stats-overview-stat::after` | `background` | `radial-gradient(… color-mix(in srgb,var(--mg-petroleo) 12%,transparent), transparent 70%)` | 214 | **No** | decorativo (invisible en oscuro; inocuo) |
| `.fi-btn.fi-color-primary, .fi-btn-color-primary` | `box-shadow`; `background-image` | `0 10px 22px -10px rgba(13,58,68,.55)`; `linear-gradient(180deg,rgba(255,255,255,.1),transparent)` | 222 | **No** | decorativo |
| `.fi-modal-window` | `box-shadow` | `0 44px 90px -32px rgba(13,58,68,.6) !important` | 232 | **No** | decorativo |
| `.fi-section, .fi-wi-stats-overview-stat, .fi-wi` | `box-shadow` | `0 1px 2px rgba(13,58,68,.04), 0 14px 34px -20px rgba(13,58,68,.28) !important` | 211 | **No** (border y background sí, l. 272) | decorativo |
| `.fi-topbar > nav` | `box-shadow` | `0 4px 20px -12px rgba(13,58,68,.25)` | 195 | **No** (background y border sí, l. 273) | decorativo |

### B. Sin contraparte `.dark` porque son invariantes por diseño

Sidebar "oscuro petróleo" en ambos modos (l. 141-192) y decoración institucional. No necesitan contraparte: texto claro sobre fondo oscuro en los dos temas.

| selector | propiedad | valor | línea | riesgo |
|---|---|---|---|---|
| `.fi-sidebar, .fi-sidebar-nav` | `background`; `border-inline-end`; `box-shadow` | `linear-gradient(185deg,#10424c 0%, #0b2f37 55%, #082228 100%) !important`; `1px solid #0a2930 !important`; `inset -18px 0 40px -30px #000` | 142-146 | superficie (invariante) |
| `.fi-sidebar-header` | `background`; `border-bottom`; `box-shadow` | `linear-gradient(180deg,#0e3b45,#0a2c34) !important`; `1px solid #0a2930 !important`; `0 3px 0 0 rgba(173,205,96,.55), 0 10px 24px -12px #000` | 147-151 | superficie (invariante) |
| `.fi-sidebar-group-label` | `color` | `var(--mg-oro-br) !important` | 157 | texto (8,77:1 sobre `#0b2f37`) |
| `.fi-sidebar-group-label::before` | `background`; `box-shadow` | `var(--mg-lima)`; `0 0 6px var(--mg-lima)` | 160 | decorativo |
| `.fi-sidebar-group .fi-icon-btn` | `color` | `var(--mg-sb-mut) !important` | 161 | texto/icono (5,19:1) |
| `.fi-sidebar-item-label` | `color` | `var(--mg-sb-txt) !important` | 165 | texto (10,66:1) |
| `.fi-sidebar-item-icon` | `color` | `var(--mg-sb-mut) !important` | 166 | icono |
| `.fi-sidebar-item-btn:hover` | `background` | `rgba(255,255,255,.06) !important` | 167 | superficie |
| `.fi-sidebar-item-btn:hover .fi-sidebar-item-label` | `color` | `#fff !important` | 168 | texto |
| `.fi-sidebar-item-btn:hover .fi-sidebar-item-icon` | `color` | `var(--mg-lima-br) !important` | 169 | icono |
| `.fi-sidebar-item.fi-active .fi-sidebar-item-btn` | `background`; `box-shadow` | `linear-gradient(120deg, rgba(173,205,96,.22), rgba(234,176,44,.16)) !important`; `inset 0 0 0 1px rgba(173,205,96,.45), 0 8px 20px -8px rgba(0,0,0,.6)` | 173-174 | superficie |
| `.fi-sidebar-item.fi-active .fi-sidebar-item-btn .fi-sidebar-item-label, .fi-sidebar-item.fi-active .fi-sidebar-item-btn` | `color` | `#fff !important` | 177-178 | texto |
| `.fi-sidebar-item.fi-active .fi-sidebar-item-btn .fi-sidebar-item-icon` | `color` | `var(--mg-lima-br) !important` | 179 | icono |
| `.fi-sidebar-item.fi-active .fi-sidebar-item-btn::before` | `background`; `box-shadow` | `linear-gradient(180deg,var(--mg-lima-br),var(--mg-oro))`; `0 0 10px rgba(173,205,96,.7)` | 180 | decorativo |
| `.fi-sidebar-item-badge` | `background`; `color` | `var(--mg-oro) !important`; `#3a2a05 !important` | 182 | texto (7,10:1) |
| `.fi-sidebar-group-collapse-button` | `color` | `var(--mg-sb-mut) !important` | 185 | icono |
| `.mg-sidebar-foot` | `border-top`; `background` | `1px solid rgba(255,255,255,.08)`; `linear-gradient(180deg,transparent,rgba(0,0,0,.25))` | 188 | decorativo |
| `.mg-sidebar-foot b` | `color` | `#fff` | 190 | texto |
| `.mg-sidebar-foot span` | `color` | `var(--mg-sb-mut)` | 191 | texto |
| `.fi-main > .fi-header::after, .fi-page > .fi-header::after` | `background` | `linear-gradient(90deg,var(--mg-petroleo),var(--mg-lima),var(--mg-oro))` | 206 | decorativo (cinturón) |
| `.fi-section-header-heading::before` | `background` | `linear-gradient(180deg,var(--mg-petroleo),var(--mg-lima))` | 218 | decorativo |
| `.fi-modal-window::before` | `background` | `linear-gradient(90deg,var(--mg-lima),var(--mg-petroleo) 45%,var(--mg-oro))` | 233 | decorativo |
| `.fi-simple-layout::before` | `background` | cinturón de 7 colores con literal `#9c9b9b` | 267 | decorativo |
| `.filepond--root.has-video-preview video` | `background` | `#000` | 391 | superficie (letterbox, invariante) |
| `.filepond--root.has-video-preview .filepond--file-action-button` | `background`; `color` | `rgba(0,0,0,.55)`; `#fff` | 396 | texto sobre video (invariante) |

Reglas neutras (valores `transparent`/`none`, no requieren contraparte): `.filepond--root.has-video-preview` (l. 359), `… .filepond--panel-root` (l. 364), `… .filepond--media-preview-wrapper, … .filepond--media-preview` (l. 386).

### C. Con contraparte `.dark`

| selector | propiedad | valor light | línea | contraparte | riesgo cubierto |
|---|---|---|---|---|---|
| `.fi-topbar > nav` | `background`; `border-bottom` | `color-mix(in srgb,#fff 82%, var(--mg-papel)) !important`; `1px solid var(--mg-borde)` | 195 | `.dark .fi-topbar > nav { background:#0d1c20 !important; border-color:#1c343a; }` (l. 273) | superficie, borde |
| `.fi-global-search-field .fi-input-wrp:focus-within` | `border-color`; `box-shadow` | `var(--mg-petroleo)`; `0 0 0 3px color-mix(… 18%,transparent)` (sin `!important`) | 197 | Indirecta: `.dark .fi-input-wrp:focus-within` (l. 322-325) tiene igual especificidad (0,3,0) pero va después **y** lleva `!important`, así que gana | foco |
| `.fi-body, .fi-main-ctn, .fi-main` | `background-color` | `#f8f5ec !important` | 201 | l. 271 `#081418 !important` | superficie |
| `.fi-header-heading` | `color` | `var(--mg-petroleo-d) !important` | 204 | l. 274 `#eaf5f3 !important` | texto |
| `.fi-section, .fi-wi-stats-overview-stat, .fi-wi` | `border`; `background` | `1px solid var(--mg-borde) !important`; `#fff` | 210 | l. 272 `background:#0f2025 !important; border-color:#1c343a !important` | superficie, borde |
| `.fi-wi-stats-overview-stat` | `background` | `linear-gradient(180deg,color-mix(in srgb,var(--mg-petroleo) 5%,#fff),#fff) !important` | 213 | l. 272 | superficie |
| `.fi-wi-stats-overview-stat-value` | `color` | `var(--mg-petroleo-d) !important` | 215 | l. 274 | texto |
| `.fi-wi-stats-overview-stat-label` | `color` | `var(--mg-tinta)` | 216 | l. 292 `var(--mg-sb-mut) !important` (6,11:1) | texto |
| `.fi-section-header-heading` | `color` | `var(--mg-petroleo-d)` | 217 | l. 299 `#eaf5f3 !important` | texto |
| `.fi-input-wrp:focus-within` | `border-color`; `box-shadow` | `var(--mg-petroleo) !important`; `0 0 0 3px color-mix(… 18%,transparent) !important` | 226 | l. 322-325 `var(--mg-lima-br)` / 35 % | foco |
| `.fi-ta-header-cell` | `background`; `color` | `color-mix(in srgb,var(--mg-papel) 40%,#fff)`; `var(--mg-petroleo)` | 227 | l. 293 `#0f2025` / `var(--mg-lima-br)` | superficie, texto |
| `.fi-tabs-item.fi-active` | `color` | `var(--mg-petroleo-d) !important` | 235 | l. 248 `var(--mg-lima-br) !important` | texto |
| `.fi-simple-layout` | `background` | `#f8f5ec !important` | 266 | l. 321 `#081418 !important` | superficie |
| `.filepond--root.has-video-preview .filepond--item-panel` | `background`; `border`; `box-shadow` | `#f8fafc`; `1px solid #e2e8f0`; `0 1px 3px rgb(0 0 0 / .06)` | 379 | l. 380 `background:#18181b; border-color:#27272a` (la sombra no, decorativo) | superficie, borde |
| `.filepond--root.has-video-preview .filepond--file-info-main` | `color` | `#0f172a` | 382 | l. 384 `#f8fafc` | texto |
| `.filepond--root.has-video-preview .filepond--file-info-sub` | `color` | `#64748b` | 383 | l. 385 `#a1a1aa` | texto |
| `.filepond--root.has-video-preview .filepond--open-icon, … .filepond--download-icon` | `color` | `#334155` | 397 | l. 398 `#cbd5e1` | icono |

Regla oscura sin regla clara propia (la clara la aporta Filament): `.dark .fi-ta-ctn { background:#0f2025 !important; border-color:#1c343a; }` (l. 320).

---

## muni-ui-tailwind.css

- **Qué es**: puente **opcional** (encabezado l. 2-9: "Puente OPCIONAL a Tailwind v4 … Las apps en Tailwind v3 NO deben importar este archivo"). Debe importarse después de `@import "tailwindcss"` y de `muni-ui.css`.
- **Versión requerida**: Tailwind **v4** — usa `@custom-variant` (l. 14) y `@theme inline` (l. 20), ambas directivas exclusivas de v4.
- **`@custom-variant dark`** (l. 14-18): `(&:where([data-muni-theme=dark], [data-muni-theme=dark] *, [data-theme=dark], [data-theme=dark] *, .dark, .dark *))`. Cubre los tres activadores explícitos de muni-ui.css con especificidad cero. **No** incluye `@media (prefers-color-scheme: dark)` y **no** excluye descendientes de `[data-muni-theme=light]` (ver Hallazgo 16).
- **`@theme inline`** (l. 20-41), 18 variables → utilidades:
  - Colores (14): `--color-muni-bg`, `--color-muni-surface`, `--color-muni-surface-2`, `--color-muni-surface-3`, `--color-muni-panel`, `--color-muni-text`, `--color-muni-muted`, `--color-muni-hint`, `--color-muni-border`, `--color-muni-accent`, `--color-muni-ok` (→ `--muni-ok-fg`), `--color-muni-warn` (→ `--muni-warn-fg`), `--color-muni-danger` (→ `--muni-danger-fg`), `--color-muni-info` (→ `--muni-info-fg`). En v4 cada `--color-*` genera `bg-muni-*`, `text-muni-*`, `border-muni-*`, `ring-muni-*`, `fill-muni-*`, etc.
  - Fuentes (2): `--font-muni-sans`, `--font-muni-mono` → `font-muni-sans`, `font-muni-mono`.
  - Radios (2): `--radius-muni`, `--radius-muni-lg` → `rounded-muni`, `rounded-muni-lg`.
  - El modificador `inline` hace que la utilidad emita `var(--muni-*)` directamente, por lo que sí siguen el cambio de tema en tiempo de ejecución.
- **No expone**: `--muni-border-2`, `--muni-accent-strong`, `--muni-accent-soft`, `--muni-on-accent`, `--muni-focus`, los `*-bg` y `*-border` de estado, los 9 `--muni-gob-*`, `--muni-radius-sm`, `--muni-shadow*`, `--muni-ring`, `--muni-glow`, `--muni-topbar-h`.

---

## Hallazgos

1. `--muni-gob-petroleo-dark` vale `#00404c` en `muni-ui.css:35` pero el puente lo redefine como `var(--mg-petroleo-d)` = `#0d3a44` (`muni-ui-filament.css:71`, definido en `:7`). Rompe el contrato "invariante institucional": `<x-muni::gob-bar>` (`resources/views/components/gob-bar.blade.php:29`) y `<x-muni::gob-footer>` (`gob-footer.blade.php:53`) cambian de color dentro de un panel respecto del resto del ecosistema.
2. `--muni-panel` (`muni-ui.css:49`) y `--muni-gob-verde-dark` (`muni-ui.css:33`) no existen en el puente Filament ni en `:root` ni en `.dark` (`muni-ui-filament.css:6-139`): dentro del panel resuelven a nada. Hoy ningún componente los consume (grep en `resources/views/components`), así que el hueco es latente, no visible.
3. Ningún `--mg-*` se redefine en `.dark` (`muni-ui-filament.css:7-11` es la única definición). Todo `var(--mg-*)` dentro de una regla es light-only por construcción, y por eso el bloque oscuro (l. 271-325) debe escribir literales selector por selector.
4. `--mg-tinta-d` (`muni-ui-filament.css:8`) se define y nunca se referencia con `var()`; su literal `#08252b` se repite a mano en `--muni-on-accent` del bloque `.dark` (l. 116).
5. Bordes beige `var(--mg-borde)` (`#e2ddce`) sin contraparte `.dark`: `.fi-input-wrp` en reposo (`muni-ui-filament.css:225`), `.fi-topbar .fi-input-wrp, .fi-global-search-field .fi-input-wrp` (l. 196), `.fi-dropdown-panel` (l. 234) y `.fi-simple-main` (l. 268, con `!important`). En oscuro quedan bordes claros de 12,3:1 sobre `#0f2025`: visualmente incorrectos, no ilegibles.
6. `.filepond--root.has-video-preview .filepond--file-info { border-bottom:1px solid #e2e8f0 }` (`muni-ui-filament.css:381`) no tiene contraparte `.dark` (l. 380 solo cubre `.filepond--item-panel`): línea clara de 14,4:1 sobre `#18181b`.
7. `.fi-wi-stats-overview-stat { border-top:3px solid var(--mg-petroleo) !important }` (`muni-ui-filament.css:213`) queda pisado en oscuro por `.dark .fi-wi-stats-overview-stat { border-color:#1c343a !important }` (l. 272), de mayor especificidad: el acento petróleo superior desaparece en oscuro (3px del mismo color que el resto del borde).
8. `.fi-ta-row:hover { background:color-mix(in srgb,var(--mg-petroleo) 4%,transparent) }` (`muni-ui-filament.css:228`) sin contraparte: al ser regla sin capa le gana al hover oscuro de Filament (`@layer components`, según la nota de l. 310-312) y el feedback de hover de filas en oscuro es imperceptible.
9. Movimiento reducido no llega al panel: `prefers-reduced-motion` solo pone `--muni-dur: 0ms` en `muni-ui.css:244-246`; `muni-ui-filament.css:89` fija `--muni-dur: 160ms` sin override y su `@media (prefers-reduced-motion:reduce)` (l. 260) solo apaga la animación `mg-fall`. `src/Filament/MuniPanel.php:73-75` carga únicamente `vendor/muni-ui/filament.css`, así que los 35 usos de `var(--muni-dur)` en componentes siguen animando dentro del panel; y si el host además compila `muni-ui.css` en su tema, el `:root{--muni-dur:160ms}` de filament.css (cargado después, igual especificidad) pisa el `0ms`.
10. `--muni-glow`: `muni-ui.css` dark = `0 0 6px currentColor` (l. 150 y 197); puente Filament = `none` (l. 86) sin redefinir en `.dark`. Dentro del panel en oscuro pierden el resplandor `badge.blade.php:26`, `timeline.blade.php:36`, `toast-host.blade.php:62`, `command-palette.blade.php:51`, `rating.blade.php:40`, `chart-donut.blade.php:50` y `error-page.blade.php:29`. Divergencia no documentada en el archivo.
11. Sombras oscuras divergentes entre archivos: `--muni-shadow-md` `0 8px 24px rgba(0,0,0,.45)` y `--muni-shadow-lg` `0 16px 40px rgba(0,0,0,.55)` (`muni-ui.css:140-141`, `:187-188`) vs `0 4px 16px rgba(0,0,0,.40)` y `0 12px 32px rgba(0,0,0,.50)` (`muni-ui-filament.css:136-137`).
12. `--muni-danger-border` rompe el patrón `border = fg` que siguen ok/warn/info en el puente: light `#e0a3ad` (`muni-ui-filament.css:64`) = 2,10:1 sobre blanco y 1,64:1 sobre `--muni-danger-bg`; dark `#6b2430` (l. 133) = 1,53:1 sobre `#0f2025`. Si un componente usa `*-border` como único límite del aviso, queda bajo el 3:1 de WCAG 1.4.11. En `muni-ui.css` los cuatro estados sí cumplen `border = fg` en light.
13. Contraste de texto "hint": `--muni-hint #6b7280` (`muni-ui.css:53`) da 4,16:1 sobre `--muni-surface-3 #eceef2` y 4,47:1 sobre `--muni-bg #f5f6f8` (bajo 4,5:1 AA; 4,83:1 sobre blanco). En el puente dark `--muni-hint #7e8ea0` (`muni-ui-filament.css:103`) da 4,46:1 sobre `--muni-surface-3 #142a30`. El texto de ayuda sobre superficies secundarias está al borde o por debajo de AA.
14. `muni-ui-filament.css` no deriva el modo oscuro de tokens: `#f8f5ec` (l. 32/201/266), `#081418` (l. 105/271/321), `#0f2025` (l. 106/123/126/129/132/272/293/320), `#1c343a` (l. 110/272/273/320) y `#eaf5f3` (l. 101/274/299) se repiten como literales en vez de `var(--muni-*)`; la propia nota de l. 303-305 lo reconoce. Cualquier clase de Filament no enumerada en el bloque oscuro se queda con su valor claro.
15. La tarjeta FilePond (`muni-ui-filament.css:379-398`) usa la paleta slate/zinc de Tailwind (`#f8fafc`, `#e2e8f0`, `#0f172a`, `#64748b`, `#18181b`, `#27272a`, `#a1a1aa`, `#334155`, `#cbd5e1`) y no los tokens `--mg-*`/`--muni-*`: en oscuro es una tarjeta zinc neutra (`#18181b`) dentro de un panel petróleo (`#0f2025`).
16. `muni-ui-tailwind.css:14-18`: `@custom-variant dark` no incluye `@media (prefers-color-scheme: dark)` ni excluye `[data-muni-theme=light]`, mientras que los tokens de `muni-ui.css` sí siguen al SO (l. 111) y sí se congelan (l. 203). En una PWA que sigue al SO los tokens quedan oscuros y las utilidades `dark:` no se aplican; con `.dark` en `<html>` y `data-muni-theme="light"` en `<body>` ocurre lo inverso.
17. `muni-ui-tailwind.css:20-41`: `@theme inline` expone 14 colores, 2 fuentes y 2 radios y deja fuera `--muni-border-2`, `--muni-accent-strong`, `--muni-accent-soft`, `--muni-on-accent`, `--muni-focus`, los `*-bg`/`*-border` de estado, los `--muni-gob-*`, `--muni-radius-sm` y las sombras. `--color-muni-ok/warn/danger/info` mapean solo a `*-fg`, perdiendo la tríada fg/bg/border.
18. Jerarquía frágil en `muni-ui.css`: la regla del SO `:root:not([data-muni-theme="light"]):not([data-theme="light"]):not(.light)` (l. 112) tiene especificidad (0,4,0) frente a (0,1,0) de `.dark`, `[data-theme="dark"]` y `[data-muni-theme="dark"]` (l. 157-159). Hoy no se nota porque los 35 valores son idénticos, pero si un bloque divergiera, en `:root` ganaría el SO y no el activador explícito, al revés de lo documentado en l. 15-17. Además `.light` y `[data-theme="light"]` solo excluyen la regla del SO y no traen valores: en un descendiente de `.dark` no hacen nada; solo `[data-muni-theme="light"]` congela de verdad.
19. `color-mix()` sin fallback en `--muni-ring` (`muni-ui.css:82/142/189/233`) y en numerosas reglas de `muni-ui-filament.css` (l. 42, 115, 123, 126, 129, 132, 195, 197, 213, 214, 226, 227, 228, 324): en navegadores sin soporte la declaración entera se descarta (desaparecen anillo de foco y varios fondos). El encabezado de `muni-ui.css:19` promete "CSS PLANO" sin acotar el soporte mínimo de navegador.
20. `--muni-gob-gris` en el puente es literal `#9c9b9b` (`muni-ui-filament.css:76`) y el cinturón del login también (l. 267) porque no existe `--mg-gris`; el resto de los `--muni-gob-*` sí apuntan a un `--mg-*`.
21. `--muni-accent-strong` en el puente dark es `var(--mg-lima-br)` (lima, `muni-ui-filament.css:114`) mientras `--muni-accent` es `var(--mg-celeste)` (l. 113): el estado "strong"/hover cambia de tono en lugar de intensificar el acento, a diferencia de light (petróleo → petróleo oscuro) y de `muni-ui.css` (ámbar → ámbar oscuro).
22. `--muni-ring` no se redefine en el `.dark` del puente (`muni-ui-filament.css:85` es la única definición). Re-resuelve a celeste solo porque Filament pone `.dark` en `<html>`: la sustitución de `var(--muni-accent)` ocurre en el elemento donde se declara `--muni-ring` (`:root`), y el valor ya sustituido es el que se hereda. Con `.dark` en un descendiente, el anillo seguiría petróleo.
23. `.fi-sidebar, .fi-sidebar-nav` (`muni-ui-filament.css:142-146`) pintan el mismo degradado en el contenedor y en el `nav` anidado dentro de él (doble pintado). Inocuo, pero redundante.


---

# F2 — Superficie PHP, pruebas y puertas de calidad de `laravel-muni-ui`

Todo lo que sigue está verificado leyendo los archivos indicados (no memoria, no README salvo donde se cita explícitamente como README). Se corrieron también `./vendor/bin/pest`, `./vendor/bin/phpstan analyse` y `./vendor/bin/pint --test` como evidencia (resultados en la sección 6).

## Registro y publicación

`src/MuniUiServiceProvider.php:19` registra los componentes Blade anónimos:

```php
Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'muni');
```

Namespace/prefijo: **`muni`** → se consumen como `<x-muni::NOMBRE>`. No hace falta publicar los `.blade.php`: se resuelven directo desde `vendor/`.

Tags de `vendor:publish` declarados en `boot()` (`src/MuniUiServiceProvider.php:23-44`):

| tag | origen | destino | qué activa | comando exacto |
|---|---|---|---|---|
| `muni-ui-css` | `resources/css/muni-ui.css` | `resource_path('css/vendor/muni-ui.css')` | Copia el CSS de tokens para que un sistema lo personalice por proyecto. No es obligatorio: la app también puede hacer `@import` directo desde `vendor/muni-graneros/laravel-muni-ui/resources/css/muni-ui.css` (así lo documenta README.md:82-83). | `php artisan vendor:publish --tag=muni-ui-css` (README.md:90, sin `--force`) |
| `muni-ui-images` | `resources/images` (directorio completo) | `public_path('vendor/muni-ui')` | Sirve el escudo institucional (`logo-graneros.png`) que consumen `<x-muni::gob-escudo>` (`resources/views/components/gob-escudo.blade.php:10`, `asset('vendor/muni-ui/logo-graneros.png')`) y el pie de sidebar que inyecta `MuniPanel`. | `php artisan vendor:publish --tag=muni-ui-images --force` (README.md:103; el `--force` es necesario si cambió el escudo) |
| `muni-ui-filament` | `resources/css/muni-ui-filament.css`, `resources/js/filepond-video.js`, `resources/js/nombre-accesible-select.js` | `public_path('vendor/muni-ui/filament.css')`, `.../filepond-video.js`, `.../nombre-accesible-select.js` | El tema de Filament (CSS) + el observador que enciende la clase `.has-video-preview` para el recorte de video de FilePond + el script que le devuelve nombre accesible a los selects con buscador de Filament. Los tres van en el MISMO tag a propósito: el comentario del provider dice literalmente «quien publica el tema publica también lo que lo enciende, sin comando aparte» (`src/MuniUiServiceProvider.php:34-39`). | `php artisan vendor:publish --tag=muni-ui-filament --force` (README.md:102; **sin** `--force` Laravel no pisa un archivo ya publicado) |

Nota operativa que documenta el propio README (líneas 96-123, sección «Al ACTUALIZAR el paquete en un sistema»): subir la versión del paquete por Composer **no** vuelve a publicar nada — el tema y el escudo quedan congelados en `public/vendor/muni-ui/` hasta que se corran de nuevo los comandos con `--force`. El README da además el chequeo de deriva: `diff public/vendor/muni-ui/filament.css vendor/muni-graneros/laravel-muni-ui/resources/css/muni-ui-filament.css`.

## `MuniPanel` (plugin Filament)

`src/Filament/MuniPanel.php`, implementa `Filament\Contracts\Plugin`, `getId()` → `'muni-panel'`.

**Métodos públicos fluidos:**

| Método | Parámetros / default | Efecto |
|---|---|---|
| `static make(): static` | — | `app(static::class)` (línea 39) |
| `departamento(?string $departamento): static` | `$departamento` (sin default, nullable) | Guarda el texto que va bajo «Municipalidad de Graneros» en el pie del sidebar (línea 48-53) |
| `conColores(bool $aplicar = true): static` | `$aplicar = true` | Activa/desactiva `$this->aplicarColores` (línea 59-64) |
| `register(Panel $panel): void` | — | Registra 5 render hooks y, condicionalmente, la paleta (línea 66-113) |
| `boot(Panel $panel): void` | — | No-op (línea 116-119) |

**Qué inyecta `register()`, con el hardcodeo exacto:**

1. `PanelsRenderHook::STYLES_AFTER` → `<link rel="stylesheet" href="{asset('vendor/muni-ui/filament.css')}">` (líneas 72-75).
2. `PanelsRenderHook::BODY_END` → `<script data-navigate-once src="{asset('vendor/muni-ui/filepond-video.js')}"></script>` (líneas 83-86). El comentario en código (78-82) es explícito: **`data-navigate-once` es obligatorio en la etiqueta**, no en el JS — sin él, cada navegación `wire:navigate` vuelve a ejecutar el script y apila un observador nuevo.
3. `PanelsRenderHook::BODY_END` (segundo hook) → `<script data-navigate-once src="{asset('vendor/muni-ui/nombre-accesible-select.js')}"></script>` (líneas 87-95). Mismo motivo que el anterior.
4. `PanelsRenderHook::TOPBAR_BEFORE` → `<div role="presentation" aria-hidden="true" style="height:4px;background:{FRANJA}"></div>` (líneas 96-100), donde `FRANJA` (constante privada, líneas 29-31) es el `linear-gradient(90deg, ...)` de 7 colores institucionales hardcodeados: `#adcd60`, `#355a63`, `#eab02c`, `#c76421`, `#7ccbe1`, `#ca3048`, `#9c9b9b`, cada uno con su tramo de 14,28-14,29 puntos porcentuales.
5. `PanelsRenderHook::SIDEBAR_FOOTER` → `<div class="mg-sidebar-foot"><img src="{asset('vendor/muni-ui/logo-graneros.png')}" alt="Escudo de Graneros"><span><b>Municipalidad de Graneros</b>{span opcional con el departamento escapado con e()}</span></div>` (líneas 101-109).

**Colores.** Solo si `$this->aplicarColores` (default `true`): `$panel->colors(['primary' => Color::hex('#355a63')])` (líneas 111-113).

**Qué desactiva `conColores(false)`:** ÚNICAMENTE la asignación de la paleta primaria. Los cinco render hooks (tema CSS, franja institucional, pie de sidebar, observador de FilePond, script de accesibilidad del select) siguen registrándose igual. Confirmado por `tests/MuniPanelTest.php:56-64`, que monta un panel aparte, llama `conColores(false)` y solo comprueba `getColors()` → `[]`; no toca los render hooks.

**Strings/paths hardcodeados de los que depende (todos literales en el archivo, ninguno viene de config):**
- `'muni-panel'` (id del plugin)
- `'vendor/muni-ui/filament.css'`, `'vendor/muni-ui/filepond-video.js'`, `'vendor/muni-ui/nombre-accesible-select.js'`, `'vendor/muni-ui/logo-graneros.png'` — coinciden exactamente con los destinos de publicación del `MuniUiServiceProvider`, así que un sistema que publique a otra ruta rompe el plugin en silencio.
- Los 7 hex de `FRANJA` y el `#355a63` del color primario («petróleo del escudo»).
- `'Municipalidad de Graneros'` y `'Escudo de Graneros'` — literales, no parametrizables por el adoptante (no hay método para cambiar el nombre del municipio ni el `alt`).
- `asset(string $path)` privado (líneas 121-128): usa `asset_versionado($path)` si la función existe (cache-busting propio del ecosistema), si no cae a `asset()` estándar de Laravel — ninguna de las dos está definida dentro de este paquete.

## Panel ARCOP (`PanelArcopPlugin`)

`src/Filament/Privacidad/PanelArcopPlugin.php`, implementa `Filament\Contracts\Plugin`, `const ID = 'panel-arcop'`.

**API pública:**

| Método | Firma | Nota |
|---|---|---|
| `static make(): static` | — | `app(static::class)` |
| `static actual(): static` | — | Resuelve el plugin del panel Filament en curso (`Filament::getCurrentOrDefaultPanel()`); lanza `PanelArcopNoRegistrado` si el panel no tiene el plugin `'panel-arcop'` registrado, o si lo registrado no es instancia de `PanelArcopPlugin` |
| `titulares(BuscaTitulares\|string $buscador): static` | **obligatorio** | Instancia o `class-string` de quien busca/encuentra titulares. Sin llamarlo, `buscador()` lanza `PanelArcopNoRegistrado` |
| `permisos(?string $ver=null, ?string $recibir=null, ?string $resolver=null): static` | opcional, cada uno con default `null` (no pisa el valor previo) | Defaults internos: `'view_any_solicitud'`, `'create_solicitud'` (nombres que genera Shield), `'resolver_solicitud_arcop'` (NO lo genera Shield, a propósito, para separar recepción de resolución) |
| `credencial(string $etiqueta, ?string $ayuda=null, ?string $comoSeAcredita=null): static` | `$etiqueta` obligatorio, default de instancia `'Credencial que presenta'` | Textos del mesón, sin lógica: el panel no sabe si es cédula, padrón o licencia |
| `alcanceDelCese(?string $texto): static` | default `null` | Qué deja de hacer el sistema cuando un bloqueo queda vigente; sin declarar, el aviso al funcionario dice explícitamente que no se declaró (ver `AlcanceDelCese::texto()` en `laravel-muni-shared`) |
| `buscador(): BuscaTitulares` | — | Resuelve `$this->titulares` (instancia o `app($class)`); lanza si es `null` |
| `permisoVer()/permisoRecibir()/permisoResolver(): string` | getters | |
| `etiquetaCredencial()/ayudaCredencial()/comoSeAcredita(): ?string` | getters | |
| `alcance(): AlcanceDelCese` | — | Envuelve el texto declarado en el value object de `laravel-muni-shared` |
| `textoDelCese(): string` | — | `alcance()->texto()` |
| `register(Panel $panel): void` | — | `$panel->resources([SolicitudResource::class])` — es lo único que registra |
| `boot(Panel $panel): void` | — | No-op |

**Contratos que el adoptante tiene que implementar:**
- `Muni\Shared\Privacidad\Contratos\BuscaTitulares` (paquete `laravel-muni-shared`, fuera de este repo): `buscar(string $termino): array` (clave→etiqueta) y `encontrar(int|string $clave): (Model&TitularDeDatos)|null`. Verificado leyendo la interfaz en `vendor/muni-graneros/laravel-muni-shared/src/Privacidad/Contratos/BuscaTitulares.php`: **solo declara esos dos métodos**.
- Existe además `src/Filament/Privacidad/Contratos/BuscaTitulares.php`, marcada `@deprecated`, que extiende la interfaz del módulo — se mantiene «para no romper a los adoptantes que ya la implementan», se saca en la próxima mayor.
- `Muni\Shared\Privacidad\Contratos\TitularDeDatos` — el modelo del titular.
- `Muni\Shared\Privacidad\Contratos\VerificadorIdentidad` — bindeado en el contenedor por el adoptante; el panel solo llama `verificar($contexto)` con `titular`, `credencial` y `funcionario_id` (`src/Filament/Privacidad/SolicitudResource/Pages/CreateSolicitud.php:71-75`).
- `Muni\Shared\Privacidad\Contratos\PropagaSupresion` (usado indirectamente por el módulo, referenciado en `SolicitudResource.php:812-820` para explicar por qué no se propagó una supresión).

**Permisos por defecto:** `view_any_solicitud`, `create_solicitud` (Shield), `resolver_solicitud_arcop` (propio). `SolicitudResource` autoriza con **overrides estáticos** (`canViewAny()`, `canCreate()`, `canResolver()`), no con una Policy — el propio código documenta por qué (`SolicitudResource.php:106-121`): `Solicitud` vive en `Muni\Shared\Privacidad\Modelos`, fuera de `App\Models`, así que el auto-discovery de policies de Laravel nunca la encuentra; se probó con `Gate::getPolicyFor()` y se confirmó que no resolvía nada.

**Config/env que lee (todo dentro de `SolicitudResource.php`, del lado de este paquete):**
- `config('privacidad.sistema')` — filtra `getEloquentQuery()` por sistema (`SolicitudResource.php:99-103`): `privacidad_solicitudes` es una tabla **compartida** por todo el ecosistema.
- `config('privacidad.disco_evidencia')` — disco del `FileUpload` de `acreditacion_path`; si está en blanco o solo espacios, lanza `DiscoEvidenciaNoConfigurado` ANTES de armar el formulario (`SolicitudResource.php:257-271`), para no dejar caer un dato personal al disco por defecto de Filament.
- README documenta que el adoptante debe poner `PRIVACIDAD_SISTEMA` y `PRIVACIDAD_DISCO_EVIDENCIA` en su `.env` (README.md:280-282).

**Páginas y recurso registrados:** solo `SolicitudResource` (`navigationGroup = 'Privacidad'`, `navigationLabel = 'Solicitudes ARCOP'`, `slug = 'solicitudes-arcop'`, icono `heroicon-o-shield-check`). Páginas: `index` → `ListSolicitudes::route('/')`, `create` → `CreateSolicitud::route('/create')`. **No hay `edit` ni `view` ni `delete`**: una solicitud recibida solo avanza de estado por las acciones de tabla (`tomar`, `resolver`, `rectificar`, `suprimir`, `exportar`), nunca por un `Model::update()` del panel.

**UI y tokens de diseño — lo que le importa a quien mantiene el sistema de diseño, sin re-auditar la lógica legal:**
- `SolicitudResource`, `CreateSolicitud` y `ListSolicitudes` **no usan ningún `<x-muni::*>`**: toda la pantalla ARCOP se arma con componentes nativos de Filament (`Schema`, `Section`, `Select`, `TextInput`, `Textarea`, `FileUpload`, `Radio`, `Table`, `TextColumn`, `SelectFilter`, `Action`). Su identidad visual no viene de `resources/views/components/`, sino de que el panel que la hospeda trae `MuniPanel` (inyecta `muni-ui-filament.css`) — es ese CSS el que re-tiñe los componentes Filament, no el paquete de componentes Blade.
- Los badges de `tipo`/`estado`/`vence_en` en la tabla usan los colores semánticos propios de Filament (`'gray'`, `'info'`, `'success'`, `'danger'`, `'warning'`), que a su vez Filament resuelve contra la paleta que `MuniPanel` haya fijado (`primary` → `#355a63` si `conColores()` no se desactivó) y contra el CSS del tema.
- Las cuatro acciones con formulario (`resolver`, `rectificar`, `suprimir`) usan `->slideOver()` — paneles laterales de Filament, no el `<x-muni::drawer>` del paquete de componentes — con el motivo documentado en código: un modal centrado con varios campos queda alto y recortado.

## Dependencias y compatibilidad

`composer.json`:
- `require`: `"php": "^8.3"`, `"illuminate/support": "^12.0|^13.0"`, `"illuminate/view": "^12.0|^13.0"`. **Sin Livewire ni Filament en `require`** — son solo dependencias de desarrollo.
- `require-dev`: `"filament/filament": "^5.0"`, `"muni-graneros/laravel-muni-shared": "^1.16"`, `"orchestra/testbench": "^9.0|^10.0|^11.0"`, `"pestphp/pest": "^3.0|^4.0"`, `"pestphp/pest-plugin-livewire": "^3.0|^4.0"`, `"laravel/pint": "^1.13"`, `"larastan/larastan": "^3.10"`.
- Versiones efectivamente instaladas (`composer.lock`, verificado con el paquete vendorizado en este entorno): `filament/filament v5.7.6`, `livewire/livewire v4.4.1` (entra transitivamente vía Filament, no está en `require`), `orchestra/testbench v11.2.0`, `pestphp/pest v4.7.8`, `larastan/larastan v3.11.0`, `laravel/pint v1.30.5`, `muni-graneros/laravel-muni-shared v1.16.0`, `laravel/framework v13.26.1`. PHP del entorno: `8.4.25`.

**Regla de compatibilidad Livewire 3 vs 4 (`CLAUDE.md` del repo, cita textual):**

> «Consecuencia: los componentes de este paquete se instalan tanto en aplicaciones con **Livewire 4 / Filament 5** (los 9 sistemas municipales) como en **personas-graneros, que sigue en Livewire 3 / Filament 3**. Por eso deben ser **Blade puros**, sin depender de directivas exclusivas de un major: nada de `@island`, `wire:show`, `wire:sort` ni `#[Transition]` dentro del paquete. Si un componente necesita interactividad, resolverla con Alpine, que existe en ambos.»

**Plugins de Alpine permitidos — discrepancia entre README y código:**
- `CLAUDE.md` del repo no menciona plugins de Alpine (solo dice que la interactividad se resuelve con Alpine porque «existe en ambos» Livewire 3 y 4).
- `README.md:224-225` afirma textualmente: «Los interactivos usan **Alpine 3 core** (sin plugins): en feria/discapacidad/licencias ya viene con Filament; en apps sin Filament, `npm i alpinejs` y `Alpine.start()`.»
- El código contradice esa afirmación: `resources/views/components/modal.blade.php:25`, `drawer.blade.php:26` y `command-palette.blade.php:37` usan `x-trap.inert.noscroll`, que es la directiva del **plugin Focus de Alpine** (`@alpinejs/focus`), no parte del core. El propio `tests/ComponentesRenderTest.php:6-14` lo dice sin rodeos: «El plugin Focus de Alpine (`x-trap`) ya viaja en el bundle de Livewire de los 8 paneles —no hace falta instalarlo—». Ver hallazgo 3.

## Pruebas

| archivo | qué garantiza | herramientas |
|---|---|---|
| `tests/ComponentesRenderTest.php` (9 tests) | Foco atrapado (`x-trap`) + `aria-labelledby` enlazado al `id` del título en `<x-muni::modal>` y `<x-muni::drawer>`; `aria-label="Paleta de comandos"` en `<x-muni::command-palette>`; dos `<x-muni::modal>` en la misma página no colisionan de `id`; render mínimo sin errores de `<x-muni::button>` (clase `muni-btn--primary`, `type="button"`), `<x-muni::badge>` (`var(--muni-warn-fg)`), `<x-muni::alert>` (`role="alert"` en `tone="danger"` vs `role="status"` en las demás), `<x-muni::kpi>` (`var(--muni-ok-fg)`) y `<x-muni::stat>` (delta positivo → `var(--muni-ok-fg)`). | `Blade::render()` puro, aserciones Pest con `str_contains`/regex; **sin** Testbench/HTTP. |
| `tests/ContrasteTokensTest.php` (2 tests) | WCAG AA 4,5:1 para `--muni-warn-fg` en modo claro (contra `--muni-surface` y su propio `--muni-warn-bg`) y para `--muni-hint` en modo oscuro (contra `--muni-surface`, `-surface-2`, `-surface-3`), leyendo los hex EXACTOS de `resources/css/muni-ui.css`. | Fórmula de luminancia/contraste W3C en PHP puro (helpers compartidos en `tests/Pest.php`), lectura de archivo + regex; sin Testbench. |
| `tests/FilepondVideoJsTest.php` (3 tests) | `resources/js/filepond-video.js` existe; documenta por qué usa `data-navigate-once`; se reengancha en cada `livewire:navigated`. | `file_get_contents` + `toContain`; sin Testbench. |
| `tests/FocoVisibleTest.php` (5 tests) | Ningún componente de los 53 apaga `outline:none`/`outline:0`; toda regla `:focus` de los componentes declara su propio `outline` visible; `--muni-focus` cumple 3:1 contra `bg`/`surface`/`surface-2`/`surface-3` en `muni-ui.css` (claro, oscuro-Regla-2, `[data-muni-theme="light"]`) y en `muni-ui-filament.css` (`:root`, `.dark`); el respaldo `#767676` de `var(--muni-focus, var(--muni-accent, #767676))` cumple 3:1 sobre 4 fondos extremos y todo componente que use `--muni-focus` lo hace con esa cadena exacta; todo token `--muni-*` leído por los 53 `*.blade.php` está definido en LOS DOS CSS del paquete. | Barrido regex sobre los 53 `*.blade.php` y sobre `muni-ui.css`/`muni-ui-filament.css`; fórmula WCAG de `tests/Pest.php`; sin Testbench. |
| `tests/MuniPanelTest.php` (5 tests, grupo `panel`) | Montando un panel Filament real: `MuniPanel` inyecta la franja de 7 colores en `TOPBAR_BEFORE` con `aria-hidden="true"`; el pie de sidebar con escudo + `Departamento de Prueba` en `SIDEBAR_FOOTER`; el CSS del tema y el script de FilePond (con `data-navigate-once`) en `STYLES_AFTER`/`BODY_END`; fija `colors['primary']` por defecto; `conColores(false)` sobre un panel aparte deja `getColors()` en `[]`. | Orchestra Testbench + `Filament::setCurrentPanel()`/`bootCurrentPanel()` + `FilamentView::renderHook()`. |
| `tests/PanelArcopTest.php` (15 tests) | Contra un titular deliberadamente NO-Persona (`Vecino`, identificado por padrón, sin RUT ni `nombres`/`apellidos`): el listado se filtra por `sistema` (aislamiento entre organismos sobre `privacidad_solicitudes`, tabla compartida); el buscador de titulares lo aporta el adoptante; recibir/tomar/resolver pasan por `Solicitudes::registrar()/tomar()/acoger()/rechazar()` del módulo y NO por un `update()` directo del panel; los nombres de permiso son configurables (`permisos()`) y no vienen horneados; sin `create_solicitud` el formulario da `assertForbidden()`; resolver sin fundamento no cambia el estado y muestra la exigencia del módulo; el aviso de cese no promete nada si `alcanceDelCese()` no se declaró y repite el texto exacto cuando sí; no arma el formulario si `privacidad.disco_evidencia` está en blanco o solo espacios (`DiscoEvidenciaNoConfigurado`); el campo `acreditacion_path` usa el disco declarado, `visibility('private')`, tipos aceptados sin SVG (`application/pdf`, `image/jpeg`, `image/png`, `image/webp`) y solo descargable (`isOpenable()` falso). | Orchestra Testbench + `Pest\Livewire\livewire()` + `Filament\Actions\Testing\TestAction`; `Gate::before()` con permisos propios en la fila (sin Shield); `RefreshDatabase` con fixtures propias (`Vecino`, `UsuarioDePrueba`, `BuscadorDeVecinos`, `VerificadorDePadron`). |
| `tests/ShellsTituloTest.php` (3 tests) | `<x-muni::auth-shell>`, `<x-muni::dashboard-shell>` y `<x-muni::app-shell>` emiten un `<title>` que es SOLO texto —sin el marcado de `<x-muni::reverb-meta>` filtrándose dentro de `<title>`, que es RCDATA— y que las `<meta name="reverb-key">` de `auth-shell` siguen existiendo como elementos aparte. | `Blade::render()` + regex sobre `<title>`; sin Testbench. |
| `tests/TemaFilamentTest.php` (6 tests) | Barriendo `resources/css/muni-ui-filament.css`: existe `.dark .fi-section-header-heading` (corrige contraste del título de tarjeta); existe la clase `has-video-preview`; TODA regla que fija `color:` en modo claro tiene su contraparte `.dark` (salvo `fi-sidebar`/`mg-sidebar`, siempre oscuras, y reglas que declaran su propio `background`); TODOS los tokens `--muni-*` leídos por los 53 componentes están definidos en `:root` del tema (derivado del barrido de los `.blade.php`, no de una lista a mano); los tokens de estado (`--muni-ok-fg`, `-warn-fg`, `-info-fg`, `-danger-fg`, `--muni-accent`) se redefinen en `.dark`; el `@keyframes mg-fall` no arranca en `opacity:0` (regresión de LCP). | Barrido regex sobre el CSS del tema y sobre los 53 `*.blade.php`; sin Testbench. |

**Cobertura de `ComponentesRenderTest.php`, listada del archivo (llamadas `Blade::render()` reales):**

Cubiertos (8 de 53): `<x-muni::modal>`, `<x-muni::drawer>`, `<x-muni::command-palette>`, `<x-muni::button>`, `<x-muni::badge>`, `<x-muni::alert>`, `<x-muni::kpi>`, `<x-muni::stat>`.

El propio archivo lo admite en su comentario (líneas 68-73): «Render de los componentes más usados del ecosistema (13 con consumidor real según el relevo del paquete). No cubre los 53 —eso es el hallazgo de adopción, aparte—…» — es decir, el propio repo documenta que faltan casos, aunque cita «13» sin que el archivo llegue a ese número de `Blade::render()` distintos.

Otros 4 componentes se renderizan de verdad, pero en `tests/ShellsTituloTest.php`, no en `ComponentesRenderTest.php`: `<x-muni::auth-shell>`, `<x-muni::dashboard-shell>`, `<x-muni::app-shell>` (y, anidado dentro de esos tres, `<x-muni::reverb-meta>`).

Sumando ambos archivos, **12 de los 53 componentes** (`.blade.php` en `resources/views/components/`) tienen al menos un `Blade::render()` en toda la suite. Los **41 restantes no se renderizan nunca** (solo pasan por el barrido regex estático de `FocoVisibleTest.php`/`TemaFilamentTest.php`, que lee el texto fuente pero no ejecuta el motor Blade):

`accordion`, `avatar`, `breadcrumb`, `calendar`, `card`, `chart-bar`, `chart-donut`, `data-table`, `dropdown`, `dropdown-item`, `empty-state`, `error-page`, `field`, `file-dropzone`, `filter-bar`, `gob-bar`, `gob-escudo`, `gob-footer`, `gob-stripe`, `input`, `nav-item`, `nav-section`, `otp-input`, `page-header`, `pagination`, `progress`, `rating`, `ring`, `segmented`, `select`, `sidebar`, `skeleton`, `sortable-table`, `stepper`, `switch`, `tab-panel`, `tabs`, `timeline`, `toast-host`, `tooltip`, `topbar`.

**Evidencia de ejecución (corrida en este entorno, `2026-09-05`):**
- `./vendor/bin/pest --compact` → **48 passed (333 assertions)**, 10,19 s.
- `./vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → **[OK] No errors** (nivel 8, con el baseline de 1 error ignorado).
- `./vendor/bin/pint --test` → **passed**.

## Puertas de calidad

**CI (`.github/workflows/ci.yml`):** dispara en `push`/`pull_request` a `main` y `develop`. Job único `test` en `ubuntu-latest`: PHP 8.4 vía `shivammathur/setup-php@v2` (`coverage: none`); configura credenciales de Composer (`github-oauth.github.com` con `secrets.COMPOSER_GH_TOKEN`, condicional a que exista) porque `muni-graneros/laravel-muni-shared` es un paquete privado de **desarrollo** — el panel ARCOP se prueba contra el módulo real; sin el token, `composer install` muere con un 404 que el propio comentario del workflow dice que «despista: parece que el paquete no existe» (`ci.yml:21-24`). Luego `composer install --no-progress --prefer-dist`, `pint --test`, `phpstan analyse --memory-limit=1G --no-progress` y `pest`, en ese orden — mismo orden que el hook `pre-commit`.

No hay en `ci.yml`, `CLAUDE.md` ni `pint.json`/`phpstan.neon` una nota sobre cuota de GitHub Actions. La única mención a cuota agotada de Actions está en el propio hook `pre-commit` como justificación de por qué el análisis estático también corre en local:

> «Por qué existe: el CI corre PHPStan con baseline en cero (nivel 8). Un error que entra a develop rompe la rama para todos, y se descubre tarde porque la cuota de Actions está agotada. Acá corta en el origen.» (`.githooks/pre-commit:57-59`)

**Hooks (`.githooks/`), activados con `git config core.hooksPath .githooks` — confirmado configurado en este clon local):**

- `pre-commit`: (1) corre `gitleaks` sobre lo staged ANTES que nada — compatible con `gitleaks git --pre-commit --staged` (≥8.19) o `gitleaks protect --staged` (8.18), usa `.gitleaks.toml` de la raíz **si existe** (no existe en este repo — se aplican las reglas por defecto de gitleaks); si `gitleaks` no está instalado, solo avisa por stderr y sigue (no bloquea). (2) Formatea con Pint únicamente los `.php` staged en modo `Added/Copied/Modified/Renamed`, y re-agrega al índice lo que Pint haya reformateado. (3) Corre PHPStan (`--memory-limit=1G`) solo sobre los archivos staged que caen bajo los `paths:` que el propio `phpstan.neon` declara (los parsea con `sed`), y bloquea el commit si hay errores.
- `commit-msg`: rechaza cualquier mensaje que matchee `co-authored-by:.*claude` o `noreply@anthropic\.com` (case-insensitive) — es la barrera técnica que hace cumplir la regla de «César es el único autor» del `CLAUDE.md` global.
- `pre-push`: (1) rechaza **sin excepción por variable de entorno** cualquier push a `refs/heads/main` o `refs/heads/master` — solo `git push --no-verify` lo saltea; el flujo obligado es `develop → main` por PR. (2) Si el push es a `develop`/`main` (y no se pidió `SKIP_PREPUSH_TESTS=1`), intenta correr `make test` **solo si existe un `Makefile` con target `test:` y hay un contenedor `app` corriendo en Docker Compose**; si no hay contenedores arriba, avisa y no bloquea.

Todos los hooks se saltan con `git commit --no-verify` / `git push --no-verify` puntualmente.

## Hallazgos

1. **`ComponentesRenderTest.php` deja 41 de los 53 componentes del paquete sin un solo `Blade::render()` en toda la suite.** `tests/ComponentesRenderTest.php` renderiza 8 (modal, drawer, command-palette, button, badge, alert, kpi, stat) y `tests/ShellsTituloTest.php` otros 4 (auth-shell, dashboard-shell, app-shell, reverb-meta anidado). El propio archivo lo admite («No cubre los 53 —eso es el hallazgo de adopción, aparte—», `tests/ComponentesRenderTest.php:70-71`), pero eso significa que componentes con lógica condicional real —`file-dropzone`, `sortable-table`, `data-table`, `otp-input`, `segmented`, `switch`, `dropdown`, `tooltip`, `command-palette` (ya cubierto) — pueden romperse en un `composer update` sin que la suite lo detecte; solo `FocoVisibleTest.php`/`TemaFilamentTest.php` los tocan, y con barrido de texto estático, no ejecución.

2. **`SolicitudResource.php` llama a un método que el contrato `BuscaTitulares` de `laravel-muni-shared` no declara.** `src/Filament/Privacidad/SolicitudResource.php:172-173` hace `self::plugin()->buscador()->comoSeBusca()`, y `buscador()` devuelve `Muni\Shared\Privacidad\Contratos\BuscaTitulares` (`src/Filament/Privacidad/PanelArcopPlugin.php:160-171`), cuya interfaz —confirmado leyendo `vendor/muni-graneros/laravel-muni-shared/src/Privacidad/Contratos/BuscaTitulares.php`— **solo declara `buscar()` y `encontrar()`**, no `comoSeBusca()`. El propio `phpstan-baseline.neon` documenta el mismo hueco como el ÚNICO error ignorado del repo, con la nota de que «desaparece sola en cuanto se corrija el contrato en el repo dueño» (`phpstan.neon:3-9`). Hoy funciona solo porque las implementaciones concretas (`BuscadorDeVecinos` en los fixtures del propio test) agregan el método por su cuenta, sin que el contrato lo exija — un adoptante que implemente literalmente la interfaz publicada (sin `comoSeBusca()`) rompe el formulario de recepción con un `Error` de método indefinido, no detectable por PHPStan porque queda en el baseline.

3. **El README afirma «Alpine 3 core (sin plugins)» y el código usa el plugin Focus.** `README.md:224-225` dice textualmente que los componentes interactivos usan «Alpine 3 core (sin plugins)». `resources/views/components/modal.blade.php:25`, `drawer.blade.php:26` y `command-palette.blade.php:37` usan `x-trap.inert.noscroll`, que pertenece al plugin Focus de Alpine (`@alpinejs/focus`) y no al core — lo confirma el propio comentario de `tests/ComponentesRenderTest.php:11-14` («El plugin Focus de Alpine (`x-trap`) ya viaja en el bundle de Livewire de los 8 paneles»). Un sistema sin Filament que siga literalmente la instrucción del README («`npm i alpinejs` y `Alpine.start()`», sin más) instala solo el core y el foco atrapado de modal/drawer/command-palette deja de funcionar, sin ningún error visible.

4. **`composer.json` no tiene el `post-install-cmd` que el propio hook dice que existe.** El encabezado de `.githooks/pre-commit:5-7` afirma: «Se instala solo: `composer install` ejecuta `git config core.hooksPath .githooks` (ver post-install-cmd en composer.json)». `composer.json:54-58` solo declara `"scripts": {"test": "pest", "stan": "phpstan analyse --memory-limit=1G", "lint": "pint --test"}` — **no hay `post-install-cmd`**. En este clon local `core.hooksPath` sí está configurado (`git config --get core.hooksPath` → `.githooks`), probablemente a mano o de una versión anterior del script, pero un `git clone` + `composer install` limpio de otro colaborador **no** activa los hooks pese a lo que el comentario promete, y nadie lo va a notar hasta que un commit con un secreto o un mensaje con atribución a la IA pase sin que nada lo frene.

5. **El gate de tests de `pre-push` es un no-op en este repo.** `.githooks/pre-push:54` solo corre la suite si `[ -f Makefile ] && grep -qE '^test:' Makefile`; este repositorio **no tiene `Makefile`** (confirmado: no existe en la raíz), así que la condición es siempre falsa y el bloque completo se salta en silencio — el push a `develop`/`main` nunca ejecuta `./vendor/bin/pest` desde este hook, pese a que el encabezado del archivo (`.githooks/pre-push:2-3`) promete «corre la suite antes de publicar a una rama compartida». El hook está escrito de forma genérica para repos con Docker/Makefile del ecosistema, pero no tiene una rama que corra `pest` directamente para un paquete Composer puro como este.

6. **`ContrasteTokensTest.php` y `FocoVisibleTest.php` nunca miden el bloque `@media (prefers-color-scheme: dark)`.** `resources/css/muni-ui.css` tiene dos bloques oscuros distintos: la «Regla 1» (`@media (prefers-color-scheme: dark)`, línea 111, PWA/SPA sin activador explícito) y la «Regla 2» (selectores explícitos `.dark`, `[data-theme="dark"]`, `[data-muni-theme="dark"]`, línea 154-159). El helper `bloqueTrasAncla()` de `tests/Pest.php:52-65`, usado por ambos archivos de prueba, ancla con el texto `'Regla 2: activadores EXPLÍCITOS de dark'` — nunca con `'Regla 1'` ni con `'@media (prefers-color-scheme: dark)'`. Si los valores de la Regla 1 llegaran a divergir de los de la Regla 2 (hoy son iguales por construcción, pero nada lo obliga), ni el contraste ni el foco visible en modo oscuro «heredado del sistema operativo, sin panel Filament de por medio» quedarían cubiertos por ninguna prueba.

7. **La cadena de respaldo de `--muni-focus` está hardcodeada a `#767676` en cada componente, no en el CSS.** `tests/FocoVisibleTest.php:169-206` prueba que cada uso de `var(--muni-focus` en los 53 componentes lleva exactamente `var(--muni-focus, var(--muni-accent, #767676))`, un patrón repetido componente por componente en vez de vivir en un único punto (una variable CSS con fallback definida una vez). Cualquier componente nuevo que copie el patrón a mano y cambie un espacio o el valor del gris queda fuera de lo que el test acepta como «string exacto» — es una prueba de texto literal, no de comportamiento, y ya lo advierte el propio comentario del archivo sobre la causa (los 9 sistemas sirven una copia vieja de `filament.css` en producción).


---

# Demos

# Auditoría de demos — laravel-muni-ui (F3a, primera mitad)

Alcance: `demo/index.html`, `demo/interactive.html`, `demo/showcase.html`,
`demo/templates.html`, `demo/app.html`, `demo/settings.html`, `demo/wizard.html`,
comparados contra `resources/css/muni-ui.css` (valores verbatim leídos del archivo) y
contra el markup real de los 52 componentes en `resources/views/components/`.

Todos los archivos son HTML estático autocontenido: reproducen a mano el markup y el
CSS inline de los componentes, con Alpine 3 embebido inline (sin CDN) salvo
`index.html`, que no usa Alpine.

---

### demo/index.html

- **Título de la página:** `laravel-muni-ui · Sistema de diseño municipal`.
- **Propósito:** portada/landing del paquete. Renderiza el MISMO panel de "Registro de
  patentes" dos veces, una al lado de la otra, para comparar visualmente los dos temas.
- **Tema:** los dos temas lado a lado. Cada panel es un `<div class="theme-panel"
  data-muni-theme="light|dark">` independiente; no hay toggle ni JS de tema. El
  contenedor exterior de la página (fuera de los paneles) usa colores fijos
  (`background:#0a0a0c`) que no siguen ningún tema — es el "marco" de la galería, no
  un componente.
- **Componentes mostrados** (mapeo a `<x-muni::NAME>`):
  - Barra superior con logo + nombre/subtítulo + badge "En línea" → `topbar` + `badge`.
  - Encabezado de página (eyebrow + `<h2>` + botones) → `page-header` + `button`.
  - 3 tarjetas con valor, label, delta y sparkline SVG → `stat`.
  - Aviso de morosidad con icono → `alert` (tono warn).
  - Toggle "Todas/Morosas/Al día" + input de búsqueda → `segmented` + un `<input>`
    suelto (no hay `<form>`, así que **no** hay `filter-bar` real, ver Hallazgos).
  - Tabla con franja izquierda roja en filas morosas → `data-table`.
  - Paginación con `‹ Anterior`/números/`Siguiente ›` + info → `pagination`.
  - El texto de la página afirma mostrar también `app-shell`, `kpi` y `field`, pero
    ninguno de los tres aparece realmente en el markup (ver Hallazgos).
- **Widgets sin componente equivalente:** ninguno nuevo; todo lo mostrado tiene
  equivalente directo en el paquete.
- **Alpine inline:** no. Toda la generación de HTML es un `<script>` con template
  literals de JS vanilla (`document.getElementById('panels').innerHTML = ...`).
- **Bloque de tokens inline:** sí, dos bloques `[data-muni-theme="light"]` y
  `[data-muni-theme="dark"]`. Drift encontrado (comparado con `muni-ui.css`):

  | Token | Valor demo | Valor paquete | Tema |
  |---|---|---|---|
  | `--muni-warn-fg` | `#c47a10` | `#8a5a00` | light |
  | `--muni-warn-border` | `#c47a10` | `#8a5a00` | light |
  | `--muni-hint` | `#4a5a74` | `#7a8ba8` | dark |
  | `--muni-radius` | `8px` | `10px` (invariante, no se redefine en dark) | dark |
  | `--muni-radius-lg` | `12px` | `14px` (invariante) | dark |
  | `--muni-radius-sm` | `6px` | `7px` (invariante) | dark |

  Tokens ausentes del bloque (definidos en el paquete y no reproducidos aquí):
  `--muni-shadow-lg`, `--muni-focus`, `--muni-accent-soft`, `--muni-panel`.
- **Recursos externos:** ninguno.
- **Accesibilidad visible:** sparklines con `aria-hidden="true"`; nada de skip-link;
  sin `aria-live`; `@media (prefers-reduced-motion: reduce)` presente (fuerza
  `--muni-dur:0ms`). Al no tener Alpine no hay foco atrapado ni modales que auditar.
- **Notas:** el rótulo "14 componentes · ... kpi · ... filter-bar · field ·
  data-table · pagination" sobre-declara: `kpi`, `field` y `filter-bar` (como
  `<form>`) no están realmente distinguibles en el HTML renderizado.

---

### demo/interactive.html

- **Título de la página:** `laravel-muni-ui · Componentes interactivos (Alpine)`.
- **Propósito:** galería de 4 tarjetas que muestran comportamientos de Alpine:
  modal, menú desplegable, pestañas y notificaciones toast.
- **Tema:** toggle en vivo. `<body x-data="{ theme: 'dark' }"
  :data-muni-theme="theme">`, con un botón que alterna `theme`. Arranca en `dark`.
- **Componentes mostrados:**
  - Tarjeta "Modal" → `modal` (fondo + panel, header con título y botón × cierre,
    footer con Cancelar/Confirmar, dispara `muni-toast` al confirmar).
  - Tarjeta "Menú desplegable" → `dropdown` + `dropdown-item` (incluye un ítem con
    tono `danger`, separador visual).
  - Tarjeta "Pestañas" → `tabs` + `tab-panel` (navegación con flechas, `role="tablist"`
    /`"tab"`, `aria-selected`, `tabindex` rotativo — reproduce fielmente el patrón
    accesible del componente real).
  - Tarjeta "Notificaciones (toast)" + host fijo abajo-derecha → `toast-host`
    (`aria-live="polite"`, apila y auto-descarta a 4,5s, igual que el componente).
- **Widgets sin componente equivalente:** ninguno.
- **Alpine inline:** sí. Modal (`x-teleport`, `x-show`, transiciones `fade`/`pop`,
  `@keydown.escape.window`), dropdown (`@click.outside`, transición de escala),
  tabs (flechas ←/→), toast-host (evento `muni-toast`, `x-for`, transiciones).
  **No usa `x-trap`** en ningún punto (el modal real, `modal.blade.php`, sí usa
  `x-trap.inert.noscroll` para atrapar el foco — ver Hallazgos).
- **Bloque de tokens inline:** sí. Drift encontrado:

  | Token | Valor demo | Valor paquete | Tema |
  |---|---|---|---|
  | `--muni-warn-fg` | `#c47a10` | `#8a5a00` | light |
  | `--muni-warn-border` | `#c47a10` | `#8a5a00` | light |
  | `--muni-hint` | `#4a5a74` | `#7a8ba8` | dark |
  | `--muni-radius` | `8px` | `10px` (invariante) | dark |
  | `--muni-radius-lg` | `12px` | `14px` (invariante) | dark |
  | `--muni-radius-sm` | `6px` | `7px` (invariante) | dark |

  A diferencia de `index.html`, sí define `--muni-shadow-lg` correctamente en ambos
  temas. Sigue sin definir `--muni-focus`, `--muni-accent-soft`, `--muni-panel`.
- **Recursos externos:** ninguno (Alpine 3.14.1 embebido inline, sin CDN).
- **Accesibilidad visible:** el modal SÍ trae `role="dialog"` `aria-modal="true"`
  (con `aria-labelledby` implícito vía el `<h2>` — en realidad el demo no le pone
  `id`/`aria-labelledby` explícito, a diferencia de `modal.blade.php` que sí genera un
  `id` único y lo referencia). Toast host con `aria-live="polite"`. El botón "×" del
  modal tiene `aria-label="Cerrar"`. El dropdown expone `:aria-expanded`. **Pero**:
  `.btn:focus-visible{outline:none;box-shadow:var(--muni-ring)}` y
  `.tab:focus-visible{outline:none;...}` — quitan el `outline` real y dependen solo
  de una `box-shadow`, exactamente el patrón que los comentarios de `muni-ui.css`
  (y de `modal.blade.php`/`button.blade.php`/`tabs.blade.php`) dicen haber
  corregido porque la box-shadow se pierde dentro de Filament. `@media
  (prefers-reduced-motion: reduce)` presente; `[x-cloak]{display:none!important}`
  presente.
- **Notas:** buena fidelidad funcional a los componentes reales salvo por el foco
  atrapado (sin `x-trap`) y el anti-patrón de foco descrito arriba.

---

### demo/showcase.html

- **Título de la página:** `laravel-muni-ui — Sala de control cívica`.
- **Propósito:** landing "hero" de marketing del sistema de diseño (consola viva con
  contadores animados, grilla de fondo, scanline) para presentar el paquete.
- **Tema:** toggle en vivo. `<body x-data="civic()" :data-muni-theme="theme"
  x-init="boot()">`; además responde a la tecla `T` (`keydown` global). Arranca en
  `dark`.
- **Componentes mostrados:**
  - Nav superior con marca "μ" + reloj en vivo (`pill`) + toggle de tema → similar a
    `topbar`, pero con markup propio (no reutiliza literalmente sus clases).
  - "Consola" con 4 tarjetas de estadística (valor con count-up, delta, sparkline
    animada con `stroke-dashoffset`) → `stat`.
  - Toolbar con segmentado + búsqueda + botón "Descargar Excel" → `segmented` +
    `input` + `button`.
  - Tabla con franja roja en morosas → `data-table`.
  - Dos "idcard" mostrando las dos identidades del sistema con swatches de color →
    sin componente directo (comparación editorial, no un widget del paquete).
  - Grilla de 4 métricas del propio sistema de diseño → parecido a `kpi` pero sin la
    franja lateral de color; markup propio.
- **Widgets sin componente equivalente:** el reloj en vivo (`pill` con hora
  actualizada cada segundo) y el conteo ascendente (`countUp`, animación JS de 0 al
  valor objetivo) no tienen respaldo en ningún `<x-muni::>` — ver también
  `app.html`, que reimplementa lo mismo de forma distinta.
- **Alpine inline:** sí (`civic()`: `x-data`, `x-init`, `x-for`, `x-html` para las
  sparklines, `x-model` en el buscador, reloj con `setInterval`). Sin modal, drawer
  ni palette — no aplica `x-trap` aquí.
- **Bloque de tokens inline:** sí, pero es la que MÁS se aparta del contrato: define
  su propia paleta "sala de control", no los valores de `muni-ui.css`, y agrega dos
  tokens propios fuera del contrato (`--grid-line`, `--scan`).

  | Token | Valor demo | Valor paquete | Tema |
  |---|---|---|---|
  | `--muni-hint` | `#8a90a0` | `#6b7280` | light |
  | `--muni-border` | `#e2e4ea` | `#e0e3ea` | light |
  | `--muni-warn-fg` | `#c47a10` | `#8a5a00` | light |
  | `--muni-bg` | `#080b10` | `#0b0f14` | dark |
  | `--muni-surface` | `#0f141c` | `#111620` | dark |
  | `--muni-surface-2` | `#151b26` | `#181e2a` | dark |
  | `--muni-surface-3` | `#1c2432` | `#1c2333` | dark |
  | `--muni-text` | `#e6ecf7` | `#e2e8f4` | dark |
  | `--muni-muted` | `#7d8da8` | `#7a8ba8` | dark |
  | `--muni-hint` | `#4a5a74` | `#7a8ba8` | dark |
  | `--muni-border` | `#212a3a` | `#2a3347` | dark |
  | `--muni-border-2` | `#2f3b52` | `#3a4560` | dark |
  | `--muni-accent` | `#f5a623` | `#f59e0b` | dark |
  | `--muni-accent-strong` | `#ffb845` | `#d97706` | dark |
  | `--muni-on-accent` | `#0a0d12` | `#0b0f14` | dark |
  | `--muni-ok-fg` | `#2fd968` | `#22c55e` | dark |
  | `--muni-ok-bg` | `#0c2a19` | `#0d2d1a` | dark |
  | `--muni-danger-fg` | `#ff5247` | `#ef4444` | dark |
  | `--muni-danger-bg` | `#2a0d0c` | `#2d0d0d` | dark |
  | `--muni-warn-fg` | `#f5a623` | `#f59e0b` | dark |
  | `--muni-warn-bg` | `#2a1e05` | `#2d1d00` | dark |
  | `--muni-info-fg` | `#4d9bff` | `#3b82f6` | dark |
  | `--muni-info-bg` | `#0b1a2e` | `#0d1a2d` | dark |
  | `--muni-glow` | `0 0 8px currentColor` | `0 0 6px currentColor` | dark |

  Tokens del contrato que NO define en absoluto: `--muni-radius*`, `--muni-shadow*`,
  `--muni-ring`, `--muni-font-sans`/`--muni-font-mono` (usa fuentes literales
  `ui-monospace`/`system-ui` propias), `--muni-ease`/`--muni-dur` (usa `.5s`/`.16s`
  literales), `--muni-topbar-h`, `--muni-ok-border`/`--muni-warn-border`/
  `--muni-danger-border`/`--muni-info-border`, `--muni-accent-soft`, `--muni-focus`,
  `--muni-on-accent` en light (sí lo define), `--muni-panel`.
- **Recursos externos:** ninguno (Alpine 3.14.1 embebido inline).
- **Accesibilidad visible:** `@media (prefers-reduced-motion: reduce){ *{
  animation-duration:.001ms!important; transition-duration:.001ms!important; } }` —
  cubre la animación CSS del scanline y del pulso del `pill`, pero el count-up
  (`requestAnimationFrame`, ~1,1s) es JS puro y no respeta la preferencia. Sin
  skip-link, sin `aria-live`, sin `role`/`aria-*` en los elementos interactivos del
  toolbar. `.field:focus{outline:none;...box-shadow:...}` — mismo anti-patrón de
  foco que el resto de las demos.
- **Notas:** el pie declara "20 componentes Blade bajo `<x-muni::>`" y "0 plugins de
  Alpine — solo el core"; el paquete real tiene 52 componentes, y al menos
  `modal.blade.php`, `drawer.blade.php` y `command-palette.blade.php` documentan
  explícitamente que dependen del plugin Focus de Alpine (`x-trap`) — ver Hallazgos.

---

### demo/templates.html

- **Título de la página:** `laravel-muni-ui — Plantillas del ecosistema`.
- **Propósito:** switcher de 6 "plantillas" de página completa (Landing, Login,
  Portal vecino, Panel funcionario, Panel director, Error 404) para mostrar cómo se
  ven páginas reales armadas con el sistema de diseño.
- **Tema:** toggle en vivo, `<body x-data="gallery()" :data-muni-theme="theme">`,
  botón ☀/☾ en la barra `switcher` sticky. Arranca en `light`.
- **Componentes mostrados:**
  - Landing (nav + hero + 3 tarjetas de feature) → sin shell equivalente en el
    paquete (ver Hallazgos: no existe un `landing-shell`).
  - Login → calca la estructura de `auth-shell` (aside con grilla de puntos +
    texto institucional, main con card de formulario, botón "Continuar con
    ClaveÚnica").
  - Panel funcionario / Panel director (mismo frame, rol conmutable con
    `x-for="rol in ['funcionario','director']"`) → calca `dashboard-shell`
    (sidebar con `nav-i`/`nav-s`, topbar con badge + avatar, `stat-row`, tabla).
  - Portal ciudadano → dashboard sin sidebar (tarjeta de alerta de vencimiento +
    progreso de trámite) → sin shell equivalente exacto; más cercano a `app-shell`.
  - Error 404 → calca `error-page` (código gigante, grilla de fondo, CTA "Volver
    al inicio").
- **Widgets sin componente equivalente:** el switcher superior de "vistas" (pills
  que cambian qué plantilla se muestra) es meta-demo, no un widget del paquete. La
  plantilla "Landing" en sí (nav + hero + grilla de features) no tiene respaldo en
  ningún componente de layout del paquete.
- **Alpine inline:** sí (`gallery()`: `x-data`, `x-show`/`x-cloak` por vista,
  `x-for` para el switcher y para los dos roles del dashboard). Sin modal/drawer.
- **Bloque de tokens inline:** sí, en `:root` (light) + selector combinado
  `[data-muni-theme="dark"],.dark,[data-theme="dark"]` (dark). Drift:

  | Token | Valor demo | Valor paquete | Tema |
  |---|---|---|---|
  | `--muni-warn-fg` | `#c47a10` | `#8a5a00` | light |
  | `--muni-hint` | `#4a5a74` | `#7a8ba8` | dark |
  | `--muni-radius` | `8px` | `10px` (invariante) | dark |
  | `--muni-radius-lg` | `12px` | `14px` (invariante) | dark |
  | `--muni-radius-sm` | `6px` | `7px` (invariante) | dark |

  Sí define `--muni-accent-soft` y `--muni-shadow-lg` correctamente en ambos temas
  (mejor cobertura que `index.html`/`interactive.html` en ese punto). No define
  `--muni-topbar-h`, `--muni-focus`, `--muni-panel`, ni las variantes `*-border`.
- **Recursos externos:** ninguno.
- **Accesibilidad visible:** `@media (prefers-reduced-motion: reduce){:root{
  --muni-dur:0ms}}`; `[x-cloak]{display:none!important}`. Sin skip-link. El error
  404 no lleva ningún `role`. `.field:focus{outline:none;...}` — mismo anti-patrón
  de foco en todos los inputs (login, etc.).
- **Notas:** de las 6 plantillas, 4 (Login, Panel funcionario, Panel director,
  Error 404) tienen un shell real al que corresponden (`auth-shell`,
  `dashboard-shell`, `error-page`); 2 (Landing, Portal ciudadano) no.

---

### demo/app.html

- **Título de la página:** `Panel de Patentes — muni-ui (demo funcional)`.
- **Propósito:** el demo más denso: un panel operativo completo de "Registro de
  patentes" con sidebar, topbar, stats, gráfico de barras, donut, tabla ordenable,
  drawer de detalle, modal de confirmación, paleta de comandos (⌘K) y toasts.
- **Tema:** toggle en vivo vía clase, `<body x-data="panel()" x-init="init()"
  :class="dark && 'dark'" ...>` (usa `.dark`, no `data-muni-theme`). Arranca en
  `light` (`dark:false`).
- **Componentes mostrados:**
  - Sidebar con secciones y badges de conteo → `sidebar` + `nav-item` (prop
    `badge`) + `nav-section`.
  - Topbar con botón ⌘K, toggle de tema, badge "En línea", avatar → `topbar`-like +
    `badge` + `avatar`.
  - 4 tarjetas de estadística con count-up y sparkline → `stat`.
  - Gráfico de barras mensual → `chart-bar`.
  - Donut de estado con leyenda → `chart-donut`.
  - Tabla con columnas ordenables por clic + franja roja en morosas → muy cercano a
    `sortable-table` (misma idea: `sort(k)`, flechas `↑↓/↕`), aunque con su propio
    `x-data` (`panel()`) en vez del `x-data` inline que genera el componente.
  - Drawer de detalle de fila → `drawer` (header con título/×, cuerpo con `kv`,
    footer con acciones).
  - Modal de confirmación ("Notificar cobranza") → `modal`.
  - Paleta de comandos (⌘K, `Ctrl/Cmd+K` global, flechas + Enter) → `command-palette`.
  - Toasts apilados abajo-derecha → `toast-host`.
- **Widgets sin componente equivalente:** ninguno nuevo (todo tiene equivalente).
- **Alpine inline:** sí, extenso: modal, drawer, command-palette (⌘K global vía
  `@keydown.window`), toasts, tabla ordenable, sidebar responsive (`sbOpen`), stats
  con count-up (`requestAnimationFrame`). **No usa `x-trap` en ningún overlay**
  (drawer/modal/palette), y a diferencia de `interactive.html`, **tampoco** pone
  `role="dialog"` ni `aria-modal="true"` en ninguno de los tres overlays (drawer,
  modal, palette) — verificado por grep, 0 ocurrencias de `aria-modal` en el
  archivo, contra `role="dialog" aria-modal="true"` obligatorio en
  `modal.blade.php`/`drawer.blade.php`/`command-palette.blade.php`.
- **Bloque de tokens inline:** sí, `:root` (light) + `.dark,[data-muni-theme=dark]`
  (dark). Drift:

  | Token | Valor demo | Valor paquete | Tema |
  |---|---|---|---|
  | `--muni-hint` | `#8a90a0` | `#6b7280` | light |
  | `--muni-warn-fg` | `#c47a10` | `#8a5a00` | light |
  | `--muni-shadow-lg` | `0 16px 40px rgba(16,24,40,.16)` | `0 12px 32px rgba(16,24,40,.14)` | light |
  | `--muni-hint` | `#4a5a74` | `#7a8ba8` | dark |

  `--muni-radius*` correctamente NO se redefine en `.dark` (coincide con el
  paquete: es invariante). `--muni-shadow-lg` en dark sí coincide. **Falta por
  completo** `--muni-info-bg` (ni light ni dark), y también `--muni-focus`,
  `--muni-panel`, las variantes `*-border` de ok/warn/danger/info.
- **Recursos externos:** ninguno.
- **Accesibilidad visible:** `@media (prefers-reduced-motion:reduce){*{
  animation:none!important;transition:none!important}}` (más agresivo que otras
  demos: mata toda animación/transición, incluida la del gráfico de barras y el
  count-up vía CSS, aunque el count-up en sí es JS puro vía
  `requestAnimationFrame` y no se detiene con esta regla). `[x-cloak]` presente.
  `.search:focus{outline:none;...box-shadow:...}` — mismo anti-patrón de foco. Sin
  skip-link. Sin `aria-modal` en ningún overlay (ver arriba).
- **Notas:** es el demo más "productizado" (más cercano a una pantalla real), y por
  eso mismo el que más pesa la ausencia de `x-trap`/`aria-modal` — es exactamente
  el tipo de pantalla (drawer + modal + command palette) donde CLAUDE.md exige que
  "todo lo que se abre atrapa el foco y cierra con Esc".

---

### demo/settings.html

- **Título de la página:** `Mi cuenta — muni-ui`.
- **Propósito:** página de cuenta de usuario con 4 pestañas (Perfil, Seguridad,
  Notificaciones, Preferencias): datos personales, contraseña/2FA/sesiones activas,
  preferencias de notificación por canal, apariencia/idioma y zona de riesgo
  (eliminar cuenta).
- **Tema:** toggle en vivo vía clase `.dark` en `<body x-data="acc()"
  :class="dark && 'dark'">` (botón ☀/☾ en la topbar; también hay un switch
  redundante "Tema oscuro" en la pestaña Preferencias que hace lo mismo). Arranca
  en `light`.
- **Componentes mostrados:**
  - Topbar simple con marca, toggle de tema, avatar → similar a `topbar` + `avatar`.
  - Cabecera de perfil con avatar grande, chips de estado → `avatar` (tamaño
    grande) + chips visualmente parecidos a `badge` pero con clase propia `.chip`.
  - Tabs Perfil/Seguridad/Notificaciones/Preferencias → visualmente calca `tabs`,
    pero la implementación es propia y **sin** ninguna semántica de pestañas (ver
    Hallazgos).
  - Formularios con label + input → `input`/`field`.
  - Filas de switch (2FA por correo, app de autenticación, canales de
    notificación, tema oscuro, reducir animación, texto grande) → visualmente
    calcan `switch`, pero **sin** `<input type="checkbox">` real (ver Hallazgos).
  - Lista de sesiones activas (icono, dispositivo, ubicación/hora, badge "ESTE
    EQUIPO" o botón "Cerrar") → sin componente equivalente en el paquete.
  - Toast de confirmación inferior centrado ("✓ Perfil guardado", etc.) → parecido
    a `toast-host` pero con markup y posicionamiento propios (centrado abajo, no
    esquina), sin `aria-live`.
- **Widgets sin componente equivalente:**
  1. Avatar grande con botón de cámara superpuesto para cambiar la foto — no existe
     en `avatar.blade.php` (candidato: prop/slot `edit` o componente
     `avatar-upload`).
  2. Lista de "sesiones activas" (dispositivo + ubicación + acción) — candidato a
     un nuevo componente tipo lista de filas con acción.
- **Alpine inline:** sí (`acc()`: `x-data`, `tab`, `dark`, `fa`, `prefs`, `notifs`
  con `x-for`, `save()` con `setTimeout` para el toast). Sin modal/drawer/palette.
- **Bloque de tokens inline:** sí, `:root` (light) + `.dark{...}` en una sola línea
  (dark; nótese que **no** incluye `[data-muni-theme="dark"]` ni
  `[data-theme="dark"]` como activador, solo `.dark`). Drift:

  | Token | Valor demo | Valor paquete | Tema |
  |---|---|---|---|
  | `--muni-hint` | `#8a90a0` | `#6b7280` | light |
  | `--muni-warn-fg` | `#c47a10` | `#8a5a00` | light |
  | `--muni-shadow-lg` | `0 16px 40px rgba(16,24,40,.16)` | `0 12px 32px rgba(16,24,40,.14)` | light |
  | `--muni-hint` | `#4a5a74` | `#7a8ba8` | dark |

  `--muni-radius*` no se redefine en `.dark` (correcto, invariante). `--muni-shadow-lg`
  en dark coincide con el paquete. No define `--muni-shadow-md`, `--muni-info-bg`
  (ninguno de los dos temas), `--muni-focus`, `--muni-panel`, ni las variantes
  `*-border`.
- **Recursos externos:** ninguno.
- **Accesibilidad visible:** `@media(prefers-reduced-motion:reduce){*{
  animation:none!important;transition:none!important}}`; `[x-cloak]` presente. Sin
  skip-link, sin `aria-live` en el toast. `.field:focus{outline:none;...}` — mismo
  anti-patrón de foco. **Regresión de accesibilidad concreta:** los switches son
  `<div class="sw" @click="...">` sin `<input>` subyacente ni `<label for>`: no son
  alcanzables ni operables por teclado (un `<div>` no recibe foco de forma nativa),
  a diferencia de `switch.blade.php`, que envuelve un `<input type="checkbox">`
  real. Las pestañas tampoco llevan `role="tablist"`/`"tab"`, `aria-selected` ni
  navegación por flechas, a diferencia de `tabs.blade.php` (y de cómo sí lo hace
  `interactive.html`).
- **Notas:** es la demo con más regresiones de accesibilidad respecto a sus propios
  componentes de referencia (switch sin `<input>`, tabs sin ARIA/teclado).

---

### demo/wizard.html

- **Título de la página:** `Nueva solicitud de licencia — muni-ui`.
- **Propósito:** wizard de 4 pasos para solicitar una licencia de conducir (Datos →
  Documentos → Agenda → Confirmar), con autocompletado simulado por RUT, subida de
  documentos por drag&drop y selección de horario de examen.
- **Tema:** toggle en vivo vía clase `.dark` en `<body x-data="wiz()" :class="dark
  && 'dark'">` (botón ☀/☾ en la topbar). Arranca en `light`.
- **Componentes mostrados:**
  - Topbar simple con marca "L" y contexto del trámite → similar a `topbar`.
  - Stepper horizontal (4 pasos, estados done/active/todo, checkmark en done) →
    `stepper` (con drift de accesibilidad, ver Hallazgos).
  - Paso 1 (Datos): inputs con autocompletado simulado por RUT → `input`.
  - Paso 2 (Documentos): dos zonas de drag&drop (cédula, selfie) → `file-dropzone`
    (clases `.dz`/`.dz__*` calcan `.muni-dz`/`.muni-dz__*` del componente real).
  - Paso 3 (Agenda): grilla de franjas horarias seleccionables → **sin**
    componente equivalente (ver Hallazgos; el paquete sí tiene `calendar` pero no
    se usa aquí, ver más abajo).
  - Paso 4 (Confirmar/Enviado): lista `kv` de resumen + estado de éxito con
    círculo de check → el círculo de éxito recuerda a `ring`/`empty-state` pero no
    reutiliza ninguno literalmente.
- **Widgets sin componente equivalente:** la grilla de franjas horarias
  seleccionables (día + hora, selección única) — candidato a un nuevo componente
  tipo "selector de horario" (`slot-picker` o similar); ni `segmented` (pensado
  para pocas opciones tipo filtro) ni `calendar` (pensado para elegir un día de
  mes) cubren bien este patrón de grilla de franjas.
- **Alpine inline:** sí (`wiz()`: `step`, `sent`, `filled`, `over1`/`over2` para
  drag&drop, `d` con los datos del formulario, `autofill()`, `slotLabel()`).
  Transiciones `fade` entre pasos. Sin modal/drawer/palette.
- **Bloque de tokens inline:** sí, `:root` (light) + `.dark{...}` en una línea
  (dark; mismo patrón que `settings.html`, solo `.dark` como activador). Drift:

  | Token | Valor demo | Valor paquete | Tema |
  |---|---|---|---|
  | `--muni-hint` | `#8a90a0` | `#6b7280` | light |
  | `--muni-warn-fg` | `#c47a10` | `#8a5a00` | light |
  | `--muni-shadow-lg` | `0 16px 40px rgba(16,24,40,.16)` | `0 12px 32px rgba(16,24,40,.14)` | light |
  | `--muni-hint` | `#4a5a74` | `#7a8ba8` | dark |

  Mismos huecos que `settings.html`: sin `--muni-shadow-md`, sin `--muni-info-bg`
  (ningún tema), sin `--muni-focus`, sin `--muni-panel`, sin variantes `*-border`.
  `--muni-radius*` correctamente invariante.
- **Recursos externos:** ninguno.
- **Accesibilidad visible:** `@media(prefers-reduced-motion:reduce){*{
  animation:none!important;transition:none!important}}`; `[x-cloak]` presente. Sin
  skip-link. `.field:focus{outline:none;...}` — mismo anti-patrón de foco. El
  `<li class="step">` del stepper **no** lleva `aria-current="step"` en el paso
  activo, a diferencia de `stepper.blade.php`, que sí lo agrega
  (`@if ($state === 'active') aria-current="step" @endif`).
- **Notas:** el paquete incluye `calendar.blade.php` (calendario de mes navegable,
  Alpine puro, pensado exactamente para "elegir una fecha/franja") pero este wizard
  —cuyo paso 3 es literalmente "Agenda tu examen"— no lo usa ni lo referencia;
  construye desde cero una grilla de franjas distinta, no reutilizable.

---

## Hallazgos (demos a)

1. **Drift sistémico de `--muni-hint` en modo oscuro, 7/7 archivos.** Los 7 demos
   usan `#4a5a74` para `--muni-hint` en dark; el paquete define `#7a8ba8` (idéntico
   a `--muni-muted` en dark). Es el mismo valor incorrecto, byte a byte, repetido en
   `index.html`, `interactive.html`, `showcase.html`, `templates.html`, `app.html`,
   `settings.html` y `wizard.html` — evidencia de que todos derivan de una misma
   plantilla desactualizada.
2. **Drift sistémico de `--muni-warn-fg`/`--muni-warn-border` en modo claro, 7/7
   archivos.** Todos usan `#c47a10`; el paquete usa `#8a5a00`. Dado que
   `muni-ui.css` documenta extensamente que sus colores de foco/acento se eligieron
   por contraste WCAG, este es el tipo de drift que más importa: un texto/borde de
   advertencia que en el paquete real es más oscuro (mejor contraste sobre fondos
   claros) aparece en todas las demos con un tono más claro y potencialmente peor
   contraste.
3. **`--muni-radius`/`--muni-radius-lg`/`--muni-radius-sm` redefinidos por tema en
   3 archivos** (`index.html`, `interactive.html`, `templates.html`: 10/14/7px →
   8/12/6px en dark), cuando el paquete los deja invariantes entre temas (no se
   tocan en ningún bloque dark de `muni-ui.css`). `app.html`, `settings.html` y
   `wizard.html` sí respetan la invariancia.
4. **`--muni-shadow-lg` con valor incorrecto en modo claro en 3 archivos**
   (`app.html`, `settings.html`, `wizard.html`: `0 16px 40px rgba(16,24,40,.16)` en
   vez de `0 12px 32px rgba(16,24,40,.14)`); el valor dark de esos mismos 3
   archivos sí es correcto. Parece que el valor dark fue copiado también al slot
   light por error.
5. **`showcase.html` no reproduce el contrato de tokens del paquete: inventa su
   propia paleta.** Salvo un puñado de valores en claro, prácticamente todos los
   colores del tema oscuro (`bg`, `surface*`, `text`, `muted`, `border*`, `accent`,
   `accent-strong`, `on-accent`, `ok/danger/warn/info-fg/bg`, `glow`) difieren del
   paquete, y agrega 2 tokens propios fuera de contrato (`--grid-line`, `--scan`).
   Omite por completo `--muni-radius*`, `--muni-shadow*`, `--muni-ring`,
   `--muni-font-*`, `--muni-ease`/`--muni-dur`, `--muni-topbar-h` y las variantes
   `*-border`.
6. **Anti-patrón de foco (`outline:none` + solo `box-shadow`) repetido en el chrome
   propio de los 7 demos** (`.btn`, `.field`, `.search`, `.m-search`, `.tab`, según
   archivo). Es exactamente el problema que los comentarios de `muni-ui.css` y de
   varios componentes (`input.blade.php`, `button.blade.php`, `tabs.blade.php`,
   `switch.blade.php`, etc.) dicen haber corregido: dentro de Filament la
   `box-shadow` del anillo se pierde, por eso los componentes reales usan
   `outline:3px solid var(--muni-focus,...)` como indicador real. Ninguna de las 7
   demos reproduce ese `outline` en su propio chrome (los `<x-muni::>` reales que sí
   se copian literalmente, como el botón dentro de `modal.blade.php`, si se
   copiaran completos sí lo traerían — pero el chrome hecho a mano de las demos no).
7. **Cero uso de `x-trap` (plugin Focus de Alpine) en las 6 demos con overlays**,
   pese a que `modal.blade.php`, `drawer.blade.php` y `command-palette.blade.php`
   documentan explícitamente `x-trap.inert.noscroll` para atrapar el foco. Los 6
   archivos con Alpine embeben únicamente el core de Alpine 3.14.1 (bundle
   idéntico byte a byte, verificado por hash, en los 6), sin el plugin Focus, así
   que ni siquiera podrían usar `x-trap` sin agregar ese plugin.
8. **`app.html` va más lejos: sus 3 overlays (drawer, modal, command-palette) no
   llevan `role="dialog"` ni `aria-modal="true"`** (0 ocurrencias de `aria-modal`
   en el archivo, verificado por grep), a diferencia de `interactive.html`, que sí
   pone `role="dialog" aria-modal="true"` en su modal. Es la demo más "de producto"
   de las 7 y la que más pesa este hueco.
9. **`settings.html`: los switches (2FA, notificaciones, tema oscuro, reducir
   animación, texto grande) son `<div class="sw" @click="...">` sin `<input
   type="checkbox">` real ni `<label for>`.** No son alcanzables ni operables por
   teclado. `switch.blade.php` sí envuelve un `<input type="checkbox">` real,
   etiquetado. Regresión de accesibilidad concreta y verificable.
10. **`settings.html`: las 4 pestañas no tienen ninguna semántica de pestañas**
    (sin `role="tablist"`/`"tab"`, sin `aria-selected`, sin `tabindex` rotativo, sin
    navegación por flechas), a diferencia de `tabs.blade.php` y de cómo sí lo
    implementa correctamente `interactive.html` en su propia tarjeta de pestañas.
11. **`wizard.html`: el `<li>` del paso activo del stepper no lleva
    `aria-current="step"`**, presente en `stepper.blade.php`.
12. **Ningún demo incluye un skip-link real** (verificado por grep; los 2 "hits" de
    "skip" en 6 archivos son falsos positivos dentro del bundle minificado de
    Alpine, no texto de la página). Dado que son 7 páginas completas y varias con
    cabecera sticky, es un hueco de accesibilidad transversal.
13. **Conteos de componentes desactualizados en el propio texto de las demos.**
    `index.html` dice "14 componentes" y `showcase.html` dice "20 componentes
    Blade bajo `<x-muni::>`"; el paquete tiene 52 componentes reales en
    `resources/views/components/`. Además, `index.html` lista `kpi`, `field` y
    `filter-bar` como mostrados sin que sean realmente distinguibles en su markup.
14. **`showcase.html` afirma "0 plugins de Alpine — solo el core"**, pero
    `modal.blade.php`, `drawer.blade.php` y `command-palette.blade.php` (del mismo
    paquete que la frase describe) documentan una dependencia real del plugin
    Focus de Alpine vía `x-trap`. La afirmación es inexacta para el ecosistema en
    su conjunto, aunque sea cierta para lo que `showcase.html` en particular usa.
15. **Widgets sin componente equivalente en el paquete** (candidatos concretos a
    nuevo componente, todos verificados contra el listado de 52 componentes):
    - Selector de franjas horarias en grilla (día + hora, selección única) —
      `wizard.html`, paso "Agenda".
    - Avatar con botón de edición/cámara superpuesto — `settings.html`.
    - Lista de sesiones activas (icono + nombre + ubicación/hora + acción/badge) —
      `settings.html`.
    - Contador ascendente animado (count-up) para valores de `stat`/`kpi` —
      reimplementado por separado y de forma distinta en `showcase.html`
      (`countUp`) y `app.html` (`cup`); ningún componente del paquete lo ofrece.
    - Página "landing" pública (nav + hero + grilla de features) como tipo de
      shell — `templates.html` la trata como plantilla de primer nivel junto a
      `auth-shell`/`dashboard-shell`/`error-page`, pero no existe un
      `landing-shell` (ni similar) en el paquete.
16. **`wizard.html` no usa `calendar.blade.php` para su paso de agenda**, pese a
    que el componente existe específicamente para seleccionar una fecha y el paso
    se llama literalmente "Agenda tu examen". Construye una grilla de franjas
    aparte, no reutilizable, en vez de mostrar (y probar) el componente real.
17. **`app.html` fija el tema con `.dark` mientras `showcase.html`/`templates.html`/
    `index.html`/`interactive.html` usan `data-muni-theme`, y `settings.html`/
    `wizard.html` también usan solo `.dark`.** Ambos mecanismos son válidos según
    `muni-ui.css` (ambos son activadores explícitos soportados), pero la mezcla sin
    ningún criterio documentado entre demos dificulta usarlas como referencia
    consistente de "cuál es el mecanismo recomendado".


# F3b — Auditoría de páginas demo (segunda mitad)

Fuente: lectura completa de los 7 archivos HTML estáticos y de `demo/.gemini/config.json`,
comparados contra `resources/css/muni-ui.css` y contra los componentes Blade en
`resources/views/components/*.blade.php` (53 archivos encontrados con `ls`, no 52).
Ningún archivo del repo fue modificado.

---

### demo/login-mfa.html

**Título de la página:** `Ingreso seguro — muni-ui` (`<title>`, línea 1).

**Propósito:** pantalla de login de dos pasos (credenciales + MFA por código de 6 dígitos)
para el portal de trámites municipales, con pantalla final de éxito. Reproduce a mano el
layout de `auth-shell.blade.php` (aside con degradado de puntos + card centrada), pero sin
usarlo — no hay `<x-muni::auth-shell>`, es HTML/CSS/Alpine propio.

**Tema:** toggle manual. `<body x-data="auth()" :class="dark && 'dark'">` con un botón
`☀/☾` (línea 80) que alterna `dark` (booleano Alpine, arranca en `false`). Activa el bloque
`.dark{...}` del propio `<style>` — **no** usa `data-muni-theme`. No lee `prefers-color-scheme`
ni persiste el estado (recargar la página siempre vuelve a claro).

**Componentes mostrados (equivalentes en el paquete):**
| Widget en el demo | Equivalente `<x-muni::…>` |
|---|---|
| `.aside` + `.card` (layout split-screen) | `auth-shell` (reproducido a mano, casi 1:1 en el CSS del punteado de fondo) |
| `.otp input` × 6 (código MFA) | `otp-input` (misma lógica: autofoco, backspace, pegar, `aria-label="Dígito N"`, `autocomplete="one-time-code"`) |
| `.field` (Correo/RUT, Contraseña) | `input` |
| `.btn.btn-p` / `.btn.btn-g` | `button` (variant `primary` / `ghost`) |
| `.lock` (pastilla "🔒 Conexión cifrada…") | `badge` (tono `ok`) |
| `.icnbtn` (toggle de tema) | sin equivalente exacto (ver widgets nuevos) |
| `.done .ring` (check de éxito animado) | sin equivalente exacto (ver widgets nuevos) |

**Widgets sin componente equivalente (candidatos):**
- **icon-button** / botón solo-ícono cuadrado (`.icnbtn`): patrón repetido en casi todos los
  demos para el toggle de tema; no existe una variante de `button` para "solo ícono, borde,
  sin label".
- **success-ring / result-badge**: círculo grande con ✓ y `box-shadow:var(--muni-glow)` y
  animación `pop` para pantallas de confirmación (paso 3). No hay componente `x-muni::` para
  un estado de éxito a pantalla completa (más grande que `empty-state`, con acento de color).

**Alpine inline:** sí. Función `auth()` (líneas 129–145): estado `step` (1-3), `digits[6]`,
`masked` (getter que enmascara el correo/RUT), `toStep2()`, `onInput/onKey/onPaste` para el
OTP, `verify()` (compara contra el código fijo `123456`), `resend()` + `startCooldown()`
(30s con `setInterval`), `reset()`. Se detecta Alpine **3.14.1** (`version:"3.14.1"` dentro
del bundle minificado inline en `<script>` líneas 147 y 151).

**Bloque de tokens inline:** sí (`<style>` líneas 3–13). Diferencias contra
`resources/css/muni-ui.css`:

*Claro (`:root`)*
| Token | Valor demo | Valor paquete |
|---|---|---|
| `--muni-hint` | `#8a90a0` | `#6b7280` |
| `--muni-warn-fg` | `#c47a10` | `#8a5a00` |
| `--muni-shadow-lg` | `0 24px 60px -24px rgba(16,24,40,.3)` | `0 12px 32px rgba(16,24,40,.14)` |

*Oscuro (`.dark`)*
| Token | Valor demo | Valor paquete |
|---|---|---|
| `--muni-hint` | `#4a5a74` | `#7a8ba8` |
| `--muni-shadow-lg` | `0 24px 60px -24px rgba(0,0,0,.7)` | `0 16px 40px rgba(0,0,0,.55)` |

Además, **no define `--muni-focus`** en ningún tema (ni claro ni oscuro), ni `--muni-panel`,
`--muni-ok-border`, `--muni-warn-bg`, `--muni-warn-border`, `--muni-danger-bg`,
`--muni-danger-border`, `--muni-info-*`, `--muni-topbar-h`. Ver Hallazgo 1.

**Recursos externos:** ninguno cargado por red — solo texto plano `https://alpinejs.dev/plugins/${r}`
dentro del bundle de Alpine minificado (es el mensaje de error interno de Alpine cuando se
llama a un plugin no registrado, no una petición real). Sin CDNs, sin Google Fonts, sin
`<link>` externos.

**Accesibilidad visible:**
- Sin skip link.
- `aria-label` dinámico en cada dígito OTP (`:aria-label="'Dígito '+i"`, línea 104).
- `@media (prefers-reduced-motion: reduce)` presente (línea 14), anula animaciones/transiciones.
- **`.field:focus{outline:none; border-color:...; box-shadow:var(--muni-ring)}`** (línea 41) y
  **`.otp input:focus{outline:none; ...}`** (línea 55): quitan el `outline` nativo y, al no
  definirse `--muni-focus`, no hay ningún indicador de foco real — solo el `box-shadow` del
  anillo, exactamente el patrón que el comentario de `muni-ui.css` (línea ~90) documenta como
  el bug medido en producción (Filament pisaba la box-shadow del anillo y dejaba el foco
  invisible). Ver Hallazgo 1.
- `<main>` presente; sin `<nav>` ni `role="navigation"`, esperable en una pantalla de login.

**Notas:** el pie de la tarjeta izquierda dice "Tus datos no salen del municipio — Ley 21.719".
El código de la demo está expuesto en pantalla ("Pista de la demo: el código es **123456**"),
correcto para una demo pero a vigilar si el archivo se reutiliza como base de un flujo real.

---

### demo/solicitud.html

**Título de la página:** `Seguimiento de solicitud — muni-ui` (línea 1).

**Propósito:** vista de seguimiento de un trámite ya ingresado (ficha de la Solicitud N.º
2026-04871, licencia de conducir Clase B): stepper de 4 etapas, historial (timeline),
documentos adjuntos y próxima cita.

**Tema:** toggle manual idéntico al de `login-mfa.html` — `<body x-data="{dark:false}"
:class="dark && 'dark'">` (línea 83) con botón `☀/☾` (línea 84). Mismo mecanismo `.dark`,
sin `data-muni-theme`, sin detección de OS, sin persistencia.

**Componentes mostrados (equivalentes en el paquete):**
| Widget en el demo | Equivalente `<x-muni::…>` |
|---|---|
| `.crumb` (Mis solicitudes › T-01…) | `breadcrumb` |
| `.badge.wa` ("En examen") | `badge` (tono `warn`) |
| `.stepper` / `.step` (4 etapas) | `stepper` |
| `.card` + `.card__h` + `.card__b` | `card` (title + body, sin `flush`) |
| `.tl` (historial con puntos de color) | `timeline` (mismo patrón `--dot`, línea + tono por color) |
| `.avatar-sm` ("JS") | `avatar` |
| `.btn.btn-p` / `.btn.btn-g` | `button` |
| `.kv` (Trámite / Solicitante / RUN…) | sin equivalente exacto (ver widgets nuevos) |
| `.doc` (fila de documento con ícono + estado) | sin equivalente exacto (ver widgets nuevos) |
| `.next` (aviso de próxima cita) | `alert` (tono `info`), aunque `.next` fusiona ícono+título+texto en una sola línea en vez de la estructura `title`/slot de `alert` |

**Widgets sin componente equivalente (candidatos):**
- **description-list / kv-row**: filas `span` (etiqueta) + `b` (valor) con borde inferior,
  usadas para "Trámite · Solicitante · RUN · Ingresada · Funcionario". Aparece también en
  otros demos del paquete (fuera de este lote) — sería un componente `x-muni::description-list`
  reutilizable.
- **attachment/doc-list-item**: fila con ícono redondeado + nombre + metadato mono (peso del
  archivo) + estado "✓ Validado" a la derecha. No hay nada parecido entre los 53 componentes;
  candidato natural para trámites con documentos adjuntos (aparece en licencias, discapacidad).

**Alpine inline:** solo el `x-data="{dark:false}"` del toggle de tema — sin más lógica
interactiva (la página es estática/de solo lectura). Mismo bundle Alpine **3.14.1** inlineado
(líneas 156 y 160).

**Bloque de tokens inline:** sí (`<style>` líneas 3–13). Diferencias contra el paquete:

*Claro (`:root`)*
| Token | Valor demo | Valor paquete |
|---|---|---|
| `--muni-hint` | `#8a90a0` | `#6b7280` |
| `--muni-warn-fg` | `#c47a10` | `#8a5a00` |
| `--muni-shadow-lg` | `0 16px 40px rgba(16,24,40,.16)` | `0 12px 32px rgba(16,24,40,.14)` |

(`--muni-warn-bg`/`--muni-info-fg`/`--muni-info-bg` sí están presentes y coinciden con el
paquete: `#fef3dc`, `#1f5fb0`, `#e6effb`.)

*Oscuro (`.dark`)*
| Token | Valor demo | Valor paquete |
|---|---|---|
| `--muni-hint` | `#4a5a74` | `#7a8ba8` |

(`--muni-shadow-lg` oscuro sí coincide: `0 16px 40px rgba(0,0,0,.55)`.)

Al igual que `login-mfa.html`, **no define `--muni-focus`** en ningún tema, ni `--muni-panel`,
`--muni-ok-border`, `--muni-warn-border`, `--muni-danger-bg`, `--muni-danger-border`,
`--muni-info-border`, `--muni-topbar-h`.

**Recursos externos:** ninguno real; mismo string inerte `https://alpinejs.dev/plugins/${r}`
dentro del bundle de Alpine.

**Accesibilidad visible:**
- Sin skip link, sin landmarks `<main>`/`<nav>` explícitos (todo vive en `.top` + `.shell` +
  `<div class="grid">`, sin etiquetas semánticas).
- `@media (prefers-reduced-motion: reduce)` presente.
- No hay ningún campo de formulario real en la página (es de solo lectura), así que el bug de
  `outline:none` de `login-mfa.html` no se repite aquí en inputs — pero el `<style>` tampoco
  define `--muni-focus`, así que si se le agregara un campo interactivo reutilizando estos
  tokens, heredaría el mismo hueco.
- El botón "Agregar al calendario" / "Reagendar" / "Contactar a la oficina" no tienen
  `aria-label` adicional más allá del texto visible (correcto, el texto ya es descriptivo).

**Notas:** el badge "En examen" usa clase `.badge.wa` con `color:var(--muni-warn-fg);
background:var(--muni-warn-bg)` — mapea 1:1 al patrón de tono de `badge.blade.php`, aunque
sin el punto (`dot`) que trae el componente real.

---

### demo/intranet-hub.html

**Título de la página:** `Intranet Municipal — Ilustre Municipalidad de Graneros` (línea 1).

**Propósito:** portal de entrada ("hub") a los 4 sistemas municipales internos (Ferias,
Licencias, Inclusión, Control de Acceso), con formulario de acceso de funcionarios y estado
de disponibilidad de cada servicio.

**Tema:** **fijo, un solo tema claro**. No hay `.dark`, `[data-theme]`, `[data-muni-theme]`
ni `@media (prefers-color-scheme: dark)` en todo el archivo — confirmado por grep, cero
coincidencias. Paleta hardcodeada en `:root` (líneas 4–13) sin mecanismo de cambio.

**Componentes mostrados (equivalentes en el paquete):**
| Widget en el demo | Equivalente `<x-muni::…>` |
|---|---|
| `.gob-bar` + `.gob-bar__in` | `gob-bar` |
| `.gob-stripe` (franja de 7 colores) | `gob-stripe` |
| `.gob-escudo` (span con imagen de fondo) | `gob-escudo` (pero como `<span>`, no `<img>`; ver Hallazgo 9) |
| `.restrict` (aviso "Uso interno") | `alert` (tono `warn`), aunque hecho a mano con clase propia |
| `.login` (formulario de acceso) | `input` + `button` (campos y botones sueltos, sin `field`/`auth-shell`) |
| `.s` (tarjetas de sistema: Ferias/Licencias/Inclusión/Acceso) | sin equivalente exacto (ver widgets nuevos) |
| `.srow` (fila de estado de servicio con punto de color) | sin equivalente exacto (ver widgets nuevos) — parecido a `stat`/`badge` pero no es ninguno |
| `.u` (tarjetas de "Utilidades") | `card` (uso simple, sin header) |
| `.gob-footer` + columnas | `gob-footer` |

**Widgets sin componente equivalente (candidatos):**
- **system-link-card**: tarjeta grande con ícono coloreado por categoría, título, subtítulo
  ("dirección"), descripción, y una fila de `tag`s + "Abrir →" a la derecha (clase `.s`). Muy
  parecido a lo que podría ser un `x-muni::link-card` genérico para portales/hubs.
- **status-row**: fila `punto de color + nombre + dominio (mono) + estado en mayúsculas` para
  monitoreo de disponibilidad de servicios (clase `.srow`). Aparece también en
  `intranet-control-acceso.html`. Candidato a `x-muni::status-row` o extensión de `badge`.

**Alpine inline:** no. Página 100% estática, sin `x-data` en ningún elemento.

**Bloque de tokens inline:** sí, pero **no usa el contrato `--muni-*` del paquete en absoluto**
(0 ocurrencias de `--muni-` en todo el archivo, confirmado por grep). Define su propio set de
variables locales sin el prefijo `muni-`: `--gob-lima`, `--gob-verde-d`, `--gob-petroleo`,
`--gob-petroleo-d`, `--gob-oro`, `--gob-naranja`, `--gob-celeste`, `--gob-carmin`, `--gob-gris`,
más `--bg`, `--sup`, `--sup2`, `--linea`, `--linea2`, `--tinta`, `--tinta2`, `--gris`, `--acc`,
`--acc-d`, `--acc-l`, `--ok`, `--warn`, `--err`, `--sans`, `--mono` (líneas 4–13). Los valores
`--gob-*` **coinciden en valor hexadecimal** con `--muni-gob-*` del paquete (mismo lima
`#adcd60`, petroleo `#355a63`, oro `#eab02c`, etc.) pero bajo un nombre de variable distinto
— ver Hallazgo 6. No aplica tabla de "token que difiere en valor" porque la página no declara
ningún `--muni-*`; la comparación relevante es de *nombre*, no de valor.

**Recursos externos:** 2 hrefs a `https://www.municipalidadgraneros.cl/` (logo del gob-bar y
enlace "Ir al sitio municipal ↗", líneas 121 y 123) — enlaces normales, no recursos cargados.
Ningún script, hoja de estilos ni fuente externa. Imagen del escudo es un `data:image/png;base64,…`
inline (~25 KB decodificado, línea 116).

**Accesibilidad visible:**
- `role="img"` + `aria-label="Escudo de la Municipalidad de Graneros"` en el `.gob-escudo`
  (línea 121).
- `aria-hidden="true"` en el separador `/` del gob-bar (línea 122).
- `@media (prefers-reduced-motion: reduce)` presente (línea 15).
- Sin skip link. Sin `aria-live`. Los inputs de login (`.field`) no tienen `<label>` asociado
  visible por `for`/`id` — solo `<label class="lbl">` de texto seguido del `<input>` sin
  atributo `for` ni `id` (líneas 140–141): la asociación label↔input es puramente visual, no
  programática. Ver Hallazgo 13.
- Foco: `.field:focus{outline:none; border-color:var(--acc); box-shadow:0 0 0 3px #355a6330}`
  (línea 47) — mismo patrón de `outline:none` sin indicador real que en `login-mfa.html`, y
  aquí ni siquiera existe el concepto `--muni-focus` porque el archivo no usa tokens `--muni-*`.

**Notas:** el aviso `.restrict` declara explícitamente "El acceso a cada uno requiere cuenta
de funcionario y los permisos del rol correspondiente" — coherente con el rol de "hub" interno.
La tarjeta de Control de Acceso indica "Solo interno" + "Biométrico" como tags.

---

### demo/intranet-control-acceso.html

**Título de la página:** `Control de Acceso — Intranet Municipal de Graneros` (línea 1).

**Propósito:** landing interna del sistema de Control de Acceso y Asistencia (terminales
Dahua de reconocimiento facial): qué hace el sistema, terminales conectados y su estado,
accesos rápidos tras iniciar sesión.

**Tema:** **fijo, un solo tema — y es oscuro por defecto** (no hay ningún claro ni toggle).
Paleta propia "terminal de seguridad": `--bg:#0d1418`, `--sup:#132027`, `--tinta:#dfe9ec`,
`--acc:#7ccbe1` (líneas 4–12). No hay `.dark`, `[data-theme]`, `[data-muni-theme]` ni
`@media (prefers-color-scheme)`. El acento `#7ccbe1` (celeste, tomado de `--gob-celeste`) **no
corresponde a ningún** `--muni-accent` real del paquete (ni el teal claro `#0f766e` ni el
ámbar oscuro `#f59e0b`) — es una tercera paleta de acento inventada solo para esta página.

**Componentes mostrados (equivalentes en el paquete):**
| Widget en el demo | Equivalente `<x-muni::…>` |
|---|---|
| `.gob-bar`, `.gob-stripe`, `.gob-escudo`, `.gob-footer` | igual que `intranet-hub.html` |
| `.restrict` (aviso ámbar "Sistema interno") | `alert` (tono `warn`) |
| `.who` (recuadro "¿Para quién es?") | `alert` (tono `info`, con borde izquierdo) |
| `.login` (formulario de acceso) | `input` + `button` |
| `.status` / `.st` (3 indicadores: Servicio/Terminales/Sincronización) | sin equivalente exacto — parecido a `stat`/`kpi` pero en fila horizontal compacta con punto de color, no tarjeta |
| `.fn__c` (4 tarjetas "Qué hace el sistema") | `card` (uso simple sin header, con ícono+título propios) |
| `.term__r` (fila de terminal con badge EN LÍNEA/MANTENCIÓN) | sin equivalente exacto (ver widgets nuevos) |
| `.qk` (accesos rápidos) | sin equivalente exacto — mismo patrón `system-link-card` simplificado que en `intranet-hub.html` |

**Widgets sin componente equivalente (candidatos):**
- **device/terminal-row**: fila `número + nombre + IP/modelo (mono) + badge de estado`
  (`.term__r`), muy similar en forma a `.doc` de `solicitud.html` y a `.srow` de
  `intranet-hub.html` — los tres son variaciones del mismo patrón "fila con badge de estado a
  la derecha" que hoy no tiene componente único; candidato fuerte a un `x-muni::status-row`
  parametrizable (ícono/número a la izquierda, texto central, badge a la derecha).
- **inline-status-strip**: la franja `.status__in` con 3 pares punto+etiqueta+valor en línea
  más un timestamp "Actualizado hace 2 min" a la derecha — no es ni `kpi` ni `stat` (ambos son
  tarjetas independientes, no una tira horizontal compartiendo fondo).

**Alpine inline:** no. Página estática.

**Bloque de tokens inline:** sí, mismo problema que `intranet-hub.html`: **0 ocurrencias de
`--muni-`**. Variables propias `--gob-*` (mismos valores hex que `--muni-gob-*` del paquete)
más `--bg`, `--sup`, `--sup2`, `--linea`, `--linea2`, `--tinta`, `--tinta2`, `--gris`, `--ok`,
`--warn`, `--err`, `--acc` — pero aquí los valores de fondo/texto/acento son los de un tema
oscuro inventado, sin relación con los valores dark de `--muni-*` (comparar `--acc:#7ccbe1`
local vs `--muni-accent:#f59e0b` del paquete en modo oscuro).

**Recursos externos:** 2 hrefs a `https://www.municipalidadgraneros.cl/` (líneas 125 y 127).
Sin scripts ni fuentes externas. Escudo embebido como `data:image/png;base64,…` (mismo blob
que en `intranet-hub.html`, línea 120).

**Accesibilidad visible:**
- `role="img"` + `aria-label` en el escudo; `aria-hidden="true"` en el separador del gob-bar.
- `@media (prefers-reduced-motion: reduce)` presente.
- Indicadores de estado (`.st .dot.ok`/`.dot.warn`) usan **solo color** (verde/ámbar) sin texto
  de estado adjunto que no dependa del color — aunque en este caso sí hay texto explícito al
  lado ("Operativo", "3 de 4 en línea", "1 equipo en mantención"), así que el color no es el
  único portador de información; cumple el principio aunque no use un ícono/forma adicional.
- Mismo patrón `.field:focus{outline:none;...}` sin indicador de foco real (línea 51).
- Labels del formulario de acceso sin `for`/`id` (mismo problema que `intranet-hub.html`).

**Notas:** menciona explícitamente el modelo de terminal `Dahua DHI-ASI3214A-W` y 4 IPs
internas (`10.0.12.41–44`) como datos de ejemplo — coherente con que es contenido de demo,
pero si estas IPs fueran reales convendría no publicarlas en un HTML público.

---

### demo/landing-feria.html

**Título de la página:** `Ferias Libres — Ilustre Municipalidad de Graneros` (línea 1).

**Propósito:** landing pública del sistema de Ferias Libres: consulta de estado de puesto por
RUT/número, cómo funciona (feriante/vecino), listado de ferias de la comuna, rubros
autorizados, estados del permiso y qué revisa la fiscalización en terreno.

**Tema:** **fijo, un solo tema claro**. Cero coincidencias de `.dark`/`[data-theme]`/
`[data-muni-theme]`/`prefers-color-scheme`. Paleta propia derivada de la institucional, con
acento verde (`--verde:#5c8a33`, línea 11) en vez del teal/ámbar de `--muni-accent`.

**Componentes mostrados (equivalentes en el paquete):**
| Widget en el demo | Equivalente `<x-muni::…>` |
|---|---|
| `.gob-bar`, `.gob-stripe`, `.gob-escudo`, `.gob-footer` | igual que los demos de intranet |
| `nav` (barra del sistema, sticky) | `topbar` (concepto similar: logo + nombre + acciones a la derecha), aunque sin el badge de estado que trae `topbar.blade.php` |
| `.lookup` (consulta de puesto por RUT) | `input` + `button` |
| `.lookup__res` (resultado con badge de estado) | `alert`/`badge` combinados a mano |
| `.aud button` (tabs "Soy feriante" / "Soy vecino") | `tabs` + `tab-panel` (mismo concepto: `x-show` condicional, botón activo con `.on`), pero reimplementado sin `role="tablist"`/`role="tab"` ni navegación por flechas ← → que sí tiene `tabs.blade.php` |
| `.step` (pasos numerados 1-4) | sin equivalente exacto — parecido a `stepper` pero en tarjetas independientes sin conector, no lista horizontal conectada |
| `.feria` (tarjeta de feria con metadatos) | `card` |
| `.rubro` (chip de rubro con emoji) | sin equivalente exacto — cercano a `badge` pero sin tono semántico, es un chip decorativo |
| `.estado` (3 tarjetas: Al día/Por vencer/Moroso) | `alert` (tonos ok/warn/danger), con borde izquierdo grueso en vez del borde fino de `alert.blade.php` |
| `.qr-demo` / `.qr-box` (QR simulado con CSS) | sin equivalente — no existe componente `qr`/`qr-code` en el paquete |

**Widgets sin componente equivalente (candidatos):**
- **numbered-step-card**: tarjeta independiente con número en cuadrado + título + texto
  (`.step`), repetido también en `landing-discapacidad.html` y `landing-licencias.html` con
  clases idénticas — es un patrón consistente en los 3 landings públicos, candidato fuerte a
  `x-muni::step-card` (distinto del `stepper` de navegación de trámite, que es horizontal/
  conectado).
- **chip** genérico (rubro/tag con emoji, sin tono ok/warn/danger): los 3 landings públicos lo
  repiten (`.rubro`, `.chip`) para taxonomías (rubros de feria, tipos de discapacidad, ayudas
  técnicas). No es un `badge` semántico, es más bien un `x-muni::chip` decorativo.
- **fake QR placeholder**: bloque CSS a rayas (`repeating-linear-gradient`) que simula un QR
  sin serlo. Es deliberadamente decorativo en la demo — no amerita componente real, pero vale
  la pena que quede anotado por si alguien lo confunde con un QR funcional real.

**Alpine inline:** sí. Función `feria()` (líneas 303–315): `aud` (audiencia activa,
feriante/vecino), `q` (input de búsqueda), `res` (resultado), `buscar()` con datos de ejemplo
fijos (`042`→Rosa Muñoz al día, `017`→Luis Tapia moroso). Alpine **3.14.1** inlineado.

**Bloque de tokens inline:** sí, **0 ocurrencias de `--muni-`**. Mismo patrón que los demos de
intranet: variables `--gob-*` (valores coincidentes con `--muni-gob-*`) más un set propio
`--bg`, `--sup`, `--sup2`, `--linea`, `--linea2`, `--tinta`, `--tinta2`, `--gris`, `--verde`,
`--verde-d`, `--verde-l`, `--naranja`, `--rojo`, `--oro` (líneas 6–14).

**Recursos externos:** 3 hrefs a `https://www.municipalidadgraneros.cl/` (líneas 147, 149,
295). String inerte `alpinejs.dev/plugins/${r}` dentro del bundle Alpine (línea 321, mensaje
de error interno, no una petición real). Sin CDNs ni fuentes externas.

**Accesibilidad visible:**
- `role="img"` + `aria-label` en el escudo; `aria-hidden` en el separador del gob-bar.
- `@media (prefers-reduced-motion: reduce)` presente.
- `html{scroll-behavior:smooth}` (línea 16) — no respeta `prefers-reduced-motion` por sí solo
  (el scroll suave seguiría activo incluso con la preferencia reducida, porque la regla que
  anula `animation`/`transition` no cubre `scroll-behavior`). Ver Hallazgo 14.
- El campo de búsqueda (`.field`, línea 174) no tiene `<label>` visible ni oculto (solo
  `placeholder`) — un lector de pantalla anuncia el campo sin nombre accesible más allá del
  placeholder, que no es un sustituto válido de label según WCAG.
- Mismo patrón `.field:focus{outline:none;...}` sin indicador de foco real (línea 61).

**Notas:** el hero explica el flujo con datos concretos y verificables por el usuario
("Prueba con 042 o 017"), buena práctica de demo autoexplicativa.

---

### demo/landing-discapacidad.html

**Título de la página:** `Oficina de Inclusión — Ilustre Municipalidad de Graneros` (línea 1).

**Propósito:** landing pública de la Oficina de Inclusión y Discapacidad: registro,
atención con profesional, ayudas técnicas y programas sociales, con una barra de
accesibilidad funcional (tamaño de texto, alto contraste, espaciado).

**Tema:** **fijo, un solo tema claro** — sin `.dark`/`[data-theme]`/`prefers-color-scheme`.
Pero es la única página del lote que sí implementa **controles de accesibilidad reales**
sobre variables CSS propias: `--fs` (factor de tamaño de fuente) y `--ls` (letter-spacing
extra), más una clase `body.hc` (alto contraste) que redefine localmente `--bg`, `--sup`,
`--tinta`, `--linea`, `--acc`, `--terra` a valores de máximo contraste (línea 18). Esto es un
mecanismo de tema **paralelo y distinto** al de claro/oscuro del resto del ecosistema — no
interopera con `data-muni-theme`/`.dark`.

**Componentes mostrados (equivalentes en el paquete):**
| Widget en el demo | Equivalente `<x-muni::…>` |
|---|---|
| `.gob-bar`, `.gob-stripe`, `.gob-escudo`, `.gob-footer` | igual que los otros landings |
| `.a11y` (barra de accesibilidad: A−/A/A+, Alto contraste, Más espaciado) | sin equivalente — ver widgets nuevos |
| `nav` (barra del sistema) | `topbar` (concepto) |
| `.card` (Atención de público, con `.kv`) | `card` + patrón `.kv` (mismo "sin equivalente" señalado en `solicitud.html`) |
| `.step` (3 pasos del registro) | mismo `numbered-step-card` señalado en `landing-feria.html` |
| `.srv` (6 servicios con ícono) | sin equivalente exacto — parecido a `.s` de `intranet-hub.html` pero más simple (sin tags ni "Abrir →") |
| `.chip` (tipos de discapacidad, ayudas técnicas, programas) | mismo `chip` genérico señalado en `landing-feria.html` |
| `.aviso` (2 avisos con borde izquierdo) | `alert` (tono info/warn vía borde de color) |
| `.ic` (Dónde/Cuándo/Contacto) | sin equivalente exacto — tarjeta simple de datos de contacto |

**Widgets sin componente equivalente (candidatos):**
- **accessibility-toolbar real**: botones `A−/A/A+`, "Alto contraste" y "Más espaciado" con
  `aria-pressed` correctamente actualizado por JS vanilla (`toggleHc()`, `toggleLs()`,
  `setFs()`, líneas 267–271) y `:focus-visible{outline:3px solid var(--gob-oro)}` (línea 42).
  Es el único demo del lote con una funcionalidad de accesibilidad end-to-end genuina; sería
  un candidato natural para convertir en `x-muni::a11y-bar`, reutilizable en los 3 landings
  públicos (hoy cada uno tendría que reimplementarlo).
- **contact-info-card** (`.ic`): bloque simple etiqueta+contenido para dirección/horario/
  teléfono, repetido con variaciones menores en los 3 landings.

**Alpine inline:** **no**. Es el único de los 7 archivos sin `x-data` en absoluto — la
interactividad (tamaño de texto, alto contraste, espaciado) está resuelta con JS vanilla
(`<script>` final, líneas 266–271) que manipula `document.documentElement.style` y
`classList.toggle` directamente, sin Alpine.

**Bloque de tokens inline:** sí, **0 ocurrencias de `--muni-`**. Mismo patrón `--gob-*`
(valores coincidentes) + variables propias `--bg`, `--sup`, `--sup2`, `--linea`, `--linea2`,
`--tinta`, `--tinta2`, `--gris`, `--acc`, `--acc-d`, `--acc-l`, `--terra`, `--terra-l`, `--ok`,
más `--fs:1` y `--ls:0` (líneas 4–14) para su sistema de accesibilidad local.

**Recursos externos:** 3 hrefs a `https://www.municipalidadgraneros.cl/` (líneas 129, 131,
259). Sin scripts ni fuentes externas; sin bundle de Alpine (no lo necesita, no hay `x-data`).
Escudo embebido igual que los demás, como `data:image/png;base64,…`.

**Accesibilidad visible (la más completa del lote):**
- `role="region" aria-label="Herramientas de accesibilidad"` en la barra `.a11y` (línea 134).
- `role="group" aria-label="Tamaño de texto"` agrupando A−/A/A+ (línea 136).
- `aria-label` individual en cada botón de tamaño ("Reducir texto", "Texto normal", "Aumentar
  texto", líneas 137–139).
- `aria-pressed` real y actualizado dinámicamente en "Alto contraste" y "Más espaciado"
  (líneas 141–142, JS líneas 270–271) — no decorativo, refleja el estado real.
- `min-height:48px`/`min-height:40px`/`min-height:34px` en botones (líneas 40, 51, 56) —
  objetivo táctil ≥24px cumplido, y el `.btn` principal incluso supera el mínimo de 44px de
  interfaces de terreno mencionado en las convenciones del usuario.
- `:focus-visible{outline:3px solid var(--terra)/var(--gob-oro);outline-offset:2-3px}`
  (líneas 42, 52) — **este es el único demo del lote con un indicador de foco real y visible**
  en vez del patrón `outline:none` + solo box-shadow que aparece en el resto. Ver Hallazgo 15
  (contraste positivo).
- `@media (prefers-reduced-motion: reduce)` presente (línea 17).
- El texto del footer se autodeclara "Esta página cumple pautas WCAG 2.1 AA" (línea 260) —
  nota: el estándar vigente que exige el Decreto N°1/2015 SEGPRES y las convenciones del
  usuario es **WCAG 2.2 AA**, no 2.1; es una discrepancia de versión en el texto, no en el
  código.

**Notas:** de los 7 archivos, es el que mejor demuestra los principios de accesibilidad reales
(barra funcional, foco visible, aria-pressed dinámico) — vale la pena usarlo como referencia
al construir el resto de las demos o al documentar el patrón `a11y-bar`.

---

### demo/landing-licencias.html

**Título de la página:** `Licencias de Conducir en línea — Ilustre Municipalidad de Graneros`
(línea 1).

**Propósito:** landing pública del sistema de Licencias de Conducir: verificación de
comprobante por folio, cómo funciona el trámite 100% en línea, los 7 trámites disponibles
(T-01 a T-07), requisitos y contacto del Departamento de Tránsito.

**Tema:** **fijo, un solo tema claro** — sin `.dark`/`[data-theme]`/`prefers-color-scheme`,
igual que `landing-feria.html`. Acento propio `--acc:#355a63` (petróleo institucional) en vez
del teal de `--muni-accent`.

**Componentes mostrados (equivalentes en el paquete):**
| Widget en el demo | Equivalente `<x-muni::…>` |
|---|---|
| `.gob-bar`, `.gob-stripe`, `.gob-escudo`, `.gob-footer` | igual que los otros landings |
| `nav` (sticky, `position:sticky;top:0;z-index:40`, línea 32) | `topbar` (concepto) |
| `.lic` (verificar licencia por folio) | `input` + `button` |
| `.lic__res` (resultado válido/no encontrado) | `alert` (tono ok/danger) armado a mano |
| `.fact` (7 trámites / 1 visita / 0 papeles) | sin equivalente exacto — cercano a `stat`/`kpi` pero sin tarjeta, solo número grande + etiqueta en línea |
| `.step` (4 pasos) | mismo `numbered-step-card` de los otros 2 landings |
| `.tr` (7 trámites T-01…T-07) | sin equivalente exacto (ver widgets nuevos) |
| `.aviso` (nota "Importante") | `alert` (tono warn) |
| `.ic` (Dirección/Atención/Consultas) | mismo `contact-info-card` de `landing-discapacidad.html` |

**Widgets sin componente equivalente (candidatos):**
- **code-labeled-list-item**: fila con "código" en píldora rectangular (`.tr__code`, ej.
  "T-01") + título + descripción + tag de requisito (`.tr__req`) — patrón específico de
  catálogo de trámites, sin equivalente en el paquete.
- **inline-fact** (`.fact`): número grande en mono + etiqueta pequeña debajo, sin caja/borde,
  alineados en fila — versión "desnuda" de `stat`/`kpi` (que sí llevan tarjeta con fondo y
  borde). Candidato a un modificador `flush`/`bare` de `stat` en vez de un componente nuevo.

**Alpine inline:** sí. Función `lic()` (líneas 240–250): `folio` (input), `res` (resultado),
`verificar()` compara contra el folio fijo `2026-04871` y devuelve válido/no encontrado.
Alpine **3.14.1** inlineado.

**Bloque de tokens inline:** sí, **0 ocurrencias de `--muni-`**. Mismo patrón `--gob-*`
(valores coincidentes) + variables propias `--bg`, `--sup`, `--sup2`, `--linea`, `--linea2`,
`--tinta`, `--tinta2`, `--gris`, `--acc`, `--acc-d`, `--acc-l`, `--vial` (amarillo vial),
`--ok`, `--err` (líneas 7–10).

**Recursos externos:** 3 hrefs a `https://www.municipalidadgraneros.cl/` (líneas 126, 128,
232). String inerte `alpinejs.dev/plugins/${r}` en el bundle Alpine (línea 256). Sin CDNs ni
fuentes externas.

**Accesibilidad visible:**
- `role="img"` + `aria-label` en el escudo; `aria-hidden` en el separador.
- `html{scroll-behavior:smooth}` (línea 14), mismo comentario que en `landing-feria.html`
  sobre no respetar `prefers-reduced-motion` para el scroll (Hallazgo 14).
- `@media (prefers-reduced-motion: reduce)` presente (línea 16).
- El campo de folio (`.field`, línea 158) no tiene `<label>` asociado (mismo patrón que
  `landing-feria.html`: solo `placeholder`).
- Mismo `.field:focus{outline:none;...}` sin indicador de foco real (línea 63).

**Notas:** el resultado de verificación de folio simula un caso de uso de alto valor legal
(comprobar autenticidad de una licencia) — en producción este tipo de verificación pública
debería estar protegida contra fuerza bruta de folios (throttle), algo que obviamente esta
demo estática no implementa ni necesita implementar, pero conviene no perderlo de vista si el
patrón migra a un sistema real.

---

### demo/.gemini/config.json

**Qué es:** archivo de configuración de **Gemini CLI** (herramienta de terceros, no de
`laravel-muni-ui` ni de Claude Code) para este directorio. Registra un servidor MCP:

```json
{
  "mcpServers": {
    "kraftdo-tools": { "command": "/home/cesar/Dev/tools/venv/bin/python3", "args": ["/home/cesar/Dev/tools/mcp_server.py"] }
  }
}
```

Apunta a un intérprete Python y un script (`mcp_server.py`) fuera del repo, en
`~/Dev/tools/`, con el nombre `kraftdo-tools`. Está **versionado en git**, no ignorado
(confirmado con `git ls-files demo/.gemini` y `git check-ignore`: el archivo figura en el
listado de git, no en `.gitignore`). No tiene relación funcional con los componentes Blade,
los tokens CSS ni las páginas demo del paquete — es configuración de herramienta de
desarrollo local. Ver Hallazgo 11 sobre la mezcla de nombres KraftDo/municipal.

---

## Hallazgos (demos b)

1. **`login-mfa.html` y `solicitud.html` reintroducen el bug de foco invisible que
   `muni-ui.css` documenta como corregido.** Ambos definen `.field:focus{outline:none; ...;
   box-shadow:var(--muni-ring)}` (y `login-mfa.html` además en `.otp input:focus`) y **ninguno
   de los dos declara el token `--muni-focus`** en su bloque `<style>` (ni en `:root` ni en
   `.dark`). El propio `resources/css/muni-ui.css` explica en un comentario extenso (líneas
   ~90–98) que el `outline` es "el indicador REAL" precisamente porque la `box-shadow` del
   anillo se pierde dentro del panel de Filament, y que sin él "al tabular no aparecía ningún
   indicador". Estas dos demos vuelven a ese estado exacto: el único indicador de foco que les
   queda es un `box-shadow` que, según la propia documentación del paquete, es la forma que ya
   se demostró insuficiente. Riesgo WCAG 2.2 AA 2.4.7 / 1.4.11, no negociable por el Decreto
   N°1/2015 SEGPRES.

2. **`--muni-hint` diverge del paquete en `login-mfa.html` y `solicitud.html`, en ambos
   temas.** Claro: demo `#8a90a0` vs paquete `#6b7280`. Oscuro: demo `#4a5a74` vs paquete
   `#7a8ba8`. Es el token con más drift del lote (cambia en las dos páginas y en los dos
   temas), usado para metadatos secundarios (`.tl time`, `.aside__foot`).

3. **`--muni-warn-fg` diverge en modo claro en `login-mfa.html` y `solicitud.html`**: demo
   `#c47a10` vs paquete `#8a5a00`. Afecta directamente el contraste del badge "En examen" en
   `solicitud.html` — con el valor del paquete el contraste ya está documentado como ajustado;
   con este valor más claro habría que remedir antes de asumir que sigue sobre 4.5:1.

4. **`--muni-shadow-lg` diverge en ambas demos y en varios temas**: `login-mfa.html` usa
   `0 24px 60px -24px rgba(16,24,40,.3)` (claro) y `0 24px 60px -24px rgba(0,0,0,.7)` (oscuro),
   ninguno coincide con el paquete (`0 12px 32px rgba(16,24,40,.14)` claro / `0 16px 40px
   rgba(0,0,0,.55)` oscuro); `solicitud.html` diverge solo en claro (`0 16px 40px
   rgba(16,24,40,.16)`) pero coincide en oscuro.

5. **`login-mfa.html` y `solicitud.html` omiten tokens que el paquete sí define**:
   `--muni-panel`, `--muni-ok-border`, `--muni-warn-border`, `--muni-danger-bg`,
   `--muni-danger-border`, `--muni-info-border`, `--muni-topbar-h`, y (`login-mfa.html`
   además) `--muni-info-fg`/`--muni-info-bg`/`--muni-warn-bg`. Ninguno de los dos archivos usa
   esas propiedades en su propio CSS, así que hoy no rompen nada visualmente, pero cualquier
   fragmento de componente real que se copie desde estas demos (p. ej. un `<x-muni::alert
   tone="danger">` que sí depende de `--muni-danger-border`) quedaría sin ese valor.

6. **Los 5 archivos "institucionales" (`intranet-hub.html`, `intranet-control-acceso.html`,
   `landing-feria.html`, `landing-discapacidad.html`, `landing-licencias.html`) no usan el
   contrato `--muni-*` en absoluto — 0 ocurrencias confirmadas por grep en cada uno.** En vez
   de `var(--muni-gob-lima)`, `var(--muni-accent)`, etc., declaran su propio juego de
   variables locales sin el prefijo `muni-` (`--gob-lima`, `--acc`, `--tinta`, `--bg`, `--sup`,
   etc.). Los valores `--gob-*` coinciden en hexadecimal con `--muni-gob-*` (identidad
   institucional invariante), pero el nombre de la variable es distinto, así que estas 5
   páginas no están realmente "conectadas" al sistema de diseño — son una copia visual
   congelada. Un cambio futuro en `muni-ui.css` (por ejemplo, ajustar el petróleo institucional
   por un problema de contraste) no se propagaría a estas 5 páginas sin editarlas a mano una
   por una.

7. **Esas mismas 5 páginas no tienen modo oscuro** (ni `.dark`, ni `[data-theme]`, ni
   `[data-muni-theme]`, ni `@media (prefers-color-scheme: dark)`): cada una embebe un único
   tema fijo. 4 de ellas (`intranet-hub`, `landing-feria`, `landing-discapacidad`,
   `landing-licencias`) son permanentemente claras; `intranet-control-acceso.html` es
   permanentemente oscura, con una paleta "terminal de seguridad" propia cuyo acento
   (`#7ccbe1`, celeste) no corresponde a `--muni-accent` en ningún tema del paquete (ni el
   teal claro `#0f766e` ni el ámbar oscuro `#f59e0b`) — es una tercera identidad de color sin
   relación declarada con el resto del ecosistema.

8. **El escudo municipal embebido en las 5 páginas institucionales NO es el mismo archivo que
   sirve `<x-muni::gob-escudo>`.** Se comparó byte a byte el PNG base64 inline
   (`--gob-escudo`, 120×120 px, ~25 KB decodificado) contra
   `resources/images/logo-graneros.png` (180×180 px, ~62 KB), que es el archivo que
   `gob-escudo.blade.php` sirve vía `asset('vendor/muni-ui/logo-graneros.png')` — son
   imágenes distintas (dimensiones y contenido binario no coinciden). Al tratarse del escudo
   oficial de un organismo del Estado, conviene una verificación visual manual antes de asumir
   que ambos representan el mismo blasón con distinta resolución.

9. **El escudo se renderiza como `<span role="img" aria-label="…">` con
   `background-image` CSS, no como el `<img>` que emite `gob-escudo.blade.php`.** Es
   accesible (el `role="img"` + `aria-label` compensan la ausencia de `alt`), pero la forma del
   marcado no es la que produciría el componente real — quien reutilice el HTML de estas demos
   como referencia para integrar el componente terminaría copiando una estructura que el
   componente no genera.

10. **Los 7 archivos vendorizan una copia minificada de Alpine.js 3.14.1 inline** (detectada
    por el string interno `version:"3.14.1"`), en vez de cargarlo desde
    `resources/js/app.js` del host como exige el `CLAUDE.md` del propio repo ("Alpine viene
    dentro de Livewire: nunca instalarlo aparte"). Es correcto y necesario para demos
    autocontenidas sin build, pero conviene que quien las use como plantilla no asuma que ese
    patrón de carga es válido para una aplicación host real.

11. **`demo/.gemini/config.json` está versionado en git y referencia una ruta y un nombre de
    herramienta (`kraftdo-tools`, `~/Dev/tools/mcp_server.py`) ajenos a este repo municipal.**
    No es un riesgo de seguridad per se (es configuración local de Gemini CLI, no credenciales
    ni datos), pero cruza el nombre "KraftDo" dentro de un repositorio de la Municipalidad de
    Graneros, contrario a la regla de no mezclar los tres frentes (Muni/KraftDo/muni-kit) ni
    siquiera en configuración incidental. Vale la pena decidir si debería vivir en
    `.gitignore` o moverse fuera del repo.

12. **No hay skip links en ninguno de los 7 archivos** — el único "skip" que aparecía en la
    búsqueda por texto era parte del bundle minificado de Alpine (nombres de función internos),
    no un enlace real "Saltar al contenido". Ninguna de las 7 páginas ofrece esa forma de
    navegación por teclado, tampoco los 3 landings públicos que sí tienen `<nav>` + secciones
    largas donde un skip link aportaría valor real.

13. **Los formularios de acceso de funcionarios (`intranet-hub.html`,
    `intranet-control-acceso.html`) tienen `<label class="lbl">` sin `for`/`id` que los
    asocie al `<input>` correspondiente** — la asociación es solo visual (proximidad en el
    DOM), no programática; un lector de pantalla no anuncia el nombre del campo al enfocarlo.
    Mismo problema en los campos de búsqueda sin `<label>` alguno de `landing-feria.html` y
    `landing-licencias.html` (solo `placeholder`).

14. **`html{scroll-behavior:smooth}` en `landing-feria.html` y `landing-licencias.html` no
    está cubierto por su propio `@media (prefers-reduced-motion: reduce)`**, que solo anula
    `animation`/`transition` en `*`. Alguien con esa preferencia activada seguiría viendo el
    scroll suave al navegar a `#consulta`/`#tramites`/`#ferias`, en contra del punto de las
    convenciones de accesibilidad del usuario ("Respetar `prefers-reduced-motion: reduce`").

15. **`landing-discapacidad.html` es la única página del lote con un indicador de foco visible
    real** (`outline:3px solid var(--terra)` / `var(--gob-oro)`, con offset) y con una barra de
    accesibilidad funcional (`aria-pressed` dinámico, `aria-label` por control, `role="group"`/
    `role="region"`). Se documenta aquí como contraste positivo frente a los Hallazgos 1 y 13:
    confirma que el patrón correcto ya existe en el propio repositorio de demos y podría
    llevarse al resto de los archivos en vez de reinventarse.

16. **Discrepancia menor de conteo**: la tarea describe "52 componentes"; `ls
    resources/views/components/` devuelve **53** archivos `.blade.php`. No cambia ninguna
    conclusión de este documento, pero se deja registrado por si la cifra de referencia debe
    actualizarse en otros documentos de la Fase 0.


---

# laravel-muni-ui — Documentación contra la realidad, y la capa JS

Auditoría de solo lectura sobre `/home/cesar/Dev/laravel-muni-ui` (2026-09-05). No se modificó
ningún archivo del repo.

**Corrección de partida:** el encargo hablaba de 52 componentes. `ls resources/views/components`
devuelve **53** archivos `.blade.php`. Todas las cuentas de abajo usan 53.

---

## README contra los componentes reales

El README documenta componentes en **tres tablas** (`README.md:158-173`, `README.md:177-183`,
`README.md:203-220`), con **35 filas** en total. Los otros 18 componentes no aparecen en ninguna.

Leyenda de la última columna: «—» significa que el README coincide exactamente con el bloque
`@props([...])` del archivo.

| Componente | ¿Fila en README? | Props reales (`@props`) | El README omite o inventa |
|---|---|---|---|
| `accordion` | **No** | `items`, `multiple`, `default` | sin documentar |
| `alert` | Sí (`:166`) | `tone`, `title`, `icon` | — |
| `app-shell` | Sí (`:160`) | `theme`, `title`, `system`, `subtitle`, `status`, `maxWidth` | — |
| `auth-shell` | Sí (`:218`) | `theme`, `title`, `system`, `subtitle`, `logo` | — (slots `aside` y `head` existen de verdad: `auth-shell.blade.php:19,42`) |
| `avatar` | Sí (`:213`) | `name`, `src`, `size`, `tone` | — |
| `badge` | Sí (`:165`) | `tone`, `dot` | — |
| `breadcrumb` | Sí (`:211`) | `items` | — |
| `button` | Sí (`:168`) | `variant`, `size`, `href`, `icon`, `type` | — |
| `calendar` | **No** | `name`, `min` | sin documentar |
| `card` | Sí (`:167`) | `title`, `subtitle`, `flush` | — |
| `chart-bar` | **No** | `data`, `height`, `tone`, `labels` | sin documentar |
| `chart-donut` | **No** | `segments`, `size`, `thickness`, `total`, `centerLabel` | sin documentar |
| `command-palette` | Sí (`:217`) | `items`, `placeholder`, `hotkey` | — |
| `dashboard-shell` | Sí (`:219`) | `theme`, `title`, `system`, `subtitle`, `status`, `user` | **omite `title`** |
| `data-table` | Sí (`:172`) | `columns`, `empty` | — |
| `drawer` | **No** | `title`, `side`, `width` | sin documentar |
| `dropdown` | Sí (`:179`) | `align`, `width` | — |
| `dropdown-item` | Sí (`:180`) | `href`, `icon`, `tone` | — |
| `empty-state` | Sí (`:216`) | `title`, `description`, `icon` | — |
| `error-page` | Sí (`:220`) | `theme`, `code`, `title`, `message`, `home`, `system` | **omite `system`** |
| `field` | Sí (`:171`) | `label`, `name` | **omite `name`** |
| `file-dropzone` | **No** | `name`, `accept`, `label`, `hint`, `multiple` | sin documentar |
| `filter-bar` | Sí (`:170`) | `action`, `method` | — |
| `gob-bar` | **No** | `system`, `home`, `sticky` | sin documentar |
| `gob-escudo` | **No** | `size`, `src` | sin documentar (solo se lo nombra en `MuniUiServiceProvider.php:27-29`) |
| `gob-footer` | **No** | `system`, `home`, `address`, `phone`, `email` | sin documentar |
| `gob-stripe` | **No** | `height` | sin documentar |
| `input` | Sí (`:205`) | `label`, `name`, `type`, `error`, `hint`, `icon`, `required` | — |
| `kpi` | Sí (`:164`) | `value`, `label`, `tone`, `hint` | — |
| `modal` | Sí (`:181`) | `title`, `maxWidth` | — |
| `nav-item` | Sí (`:209`) | `href`, `icon`, `active`, `badge` | — |
| `nav-section` | Sí (`:210`) | `title` | — |
| `otp-input` | **No** | `length`, `name` | sin documentar |
| `page-header` | Sí (`:162`) | `title`, `subtitle`, `eyebrow` | — |
| `pagination` | Sí (`:173`) | `current`, `total`, `url`, `info` | — |
| `progress` | Sí (`:214`) | `value`, `max`, `tone`, `label`, `showValue` | — |
| `rating` | **No** | `value`, `max`, `readonly`, `name`, `tone` | sin documentar |
| `reverb-meta` | **No** | *(ninguna: no tiene bloque `@props`)* | sin documentar |
| `ring` | **No** | `value`, `max`, `size`, `tone`, `label`, `showValue` | sin documentar |
| `segmented` | Sí (`:169`) | `name`, `options`, `value` | — |
| `select` | Sí (`:206`) | `label`, `name`, `options`, `selected`, `placeholder`, `error`, `hint`, `required` | **omite `hint` y `required`** |
| `sidebar` | Sí (`:208`) | `width` | — |
| `skeleton` | Sí (`:215`) | `width`, `height`, `rounded` | — |
| `sortable-table` | **No** | `columns`, `rows`, `empty`, `searchable` | sin documentar |
| `stat` | Sí (`:163`) | `value`, `label`, `tone`, `delta`, `deltaDir`, `spark`, `hint` | — |
| `stepper` | **No** | `steps`, `current`, `orientation` | sin documentar |
| `switch` | Sí (`:207`) | `label`, `name`, `checked`, `description` | — |
| `tab-panel` | **No** (solo citado dentro de la fila de `tabs`, `README.md:182`) | `index` | sin fila propia |
| `tabs` | Sí (`:182`) | `tabs`, `default` | — |
| `timeline` | **No** | `items` | sin documentar |
| `toast-host` | Sí (`:183`) | `position` | — |
| `tooltip` | Sí (`:212`) | `text`, `placement` | — |
| `topbar` | Sí (`:161`) | `system`, `subtitle`, `status`, `statusLabel`, `logo` | **omite `statusLabel`** |

### Componentes sin fila en el README (18 de 53)

`accordion`, `calendar`, `chart-bar`, `chart-donut`, `drawer`, `file-dropzone`, `gob-bar`,
`gob-escudo`, `gob-footer`, `gob-stripe`, `otp-input`, `rating`, `reverb-meta`, `ring`,
`sortable-table`, `stepper`, `tab-panel`, `timeline`.

Dos matices que conviene no perder:

- `tab-panel` sí se nombra, pero dentro de la celda de `tabs` (`README.md:182`), no como fila.
- La familia `gob-*` (4 componentes: barra, escudo, footer, franja institucional) es la
  identidad visual del Estado y está **entera** sin documentar. `gob-escudo` además depende de
  `vendor:publish --tag=muni-ui-images`, cosa que el README solo menciona en el runbook de
  actualización (`README.md:103`) y nunca junto al componente.

### Filas del README que no corresponden a ningún archivo

**Ninguna.** Las 35 filas apuntan a componentes que existen. El README peca por omisión, no por
invención: no documenta el 34 % del paquete, pero no promete nada que no esté.

La única referencia a un componente inexistente está en el propio código, no en el README:
`accordion.blade.php:8` dice «*o usar el slot con `<x-muni::accordion-item>`*» y ese archivo no
existe en `resources/views/components/`.

---

## README contra las demos reales

El README (`README.md:232-246`) nombra **10** archivos; `ls demo` devuelve **14**.

### Mencionados en el README que NO existen (3)

| Mencionado | Línea | Realidad |
|---|---|---|
| `landing-hub.html` | `README.md:242` | no existe; el archivo real es **`intranet-hub.html`** |
| `landing-control-acceso.html` | `README.md:245` | no existe; el archivo real es **`intranet-control-acceso.html`** |
| `landing-patentes.html` | `README.md:246` | **no existe con ningún nombre** |

Los dos primeros son un renombrado `landing-*` → `intranet-*` que nunca se propagó al README. El
tercero es contenido prometido que no se entregó, y es llamativo porque el ejemplo de uso central
del README (`README.md:129-153`) es justamente el dashboard de patentes.

### Existen en `demo/` y el README no menciona (7)

`intranet-control-acceso.html`, `intranet-hub.html`, `landing-feria.html`, `login-mfa.html`,
`settings.html`, `solicitud.html`, `wizard.html`.

`solicitud.html` y `login-mfa.html` son las que más se echan de menos: cubren el mesón ARCOP y el
ingreso con MFA, los dos flujos que el README documenta en prosa (`README.md:248-303`) sin
enlazar su demo.

### Correctas (7)

`index.html`, `interactive.html`, `showcase.html`, `templates.html`, `app.html`,
`landing-licencias.html`, `landing-discapacidad.html`.

---

## Capa JS (`resources/js`)

Cinco módulos. `resources/js/README.md` documenta **tres**; los otros dos no aparecen en ninguna
documentación del repo (ni en ese README, ni en el `README.md` raíz, que no menciona la capa JS
en absoluto).

| Archivo | Propósito | Dependencias | Cómo se consume | Qué sistemas lo usan (según comentarios) | Riesgos |
|---|---|---|---|---|---|
| `echo.js` | Configura Laravel Echo sobre Reverb leyendo host/puerto/esquema/clave de meta-tags en runtime, con fallback a `window.location`. No instancia nada si falta la clave. | npm: `laravel-echo`, `pusher-js` (del `node_modules` del host). Globales que **escribe**: `window.Pusher` (`:4`), `window.Echo` (`:38`). | `import` desde `vendor/…/resources/js/echo.js` en el `app.js` del host (`resources/js/README.md:28`). Vite lo bundlea. | feria, discapacidad («sistemas que exponen Reverb por meta-tags», `resources/js/README.md:18`). **Licencias queda fuera a propósito**: usa proxy Caddy, otra topología (`:11-12`). | Ensucia dos globales. Depende de que el host renderice `<x-muni::reverb-meta>` en el `<head>`; si no, cae a `window.location` en silencio. Conserva el fallback `import.meta.env.VITE_REVERB_APP_KEY` (`:32`), que es exactamente el valor que se hornea vacío al construir la imagen — solo vale para `npm run dev`. Sin clave, `window.Echo` queda `undefined`: todo consumidor debe comprobarlo antes de usarlo. |
| `_leaflet-global.js` | Expone `window.L` **antes** de que carguen los plugins de Leaflet, que esperan el global. | npm: `leaflet`. Global que escribe: `window.L` (`:6`). | `import` interno, solo desde `mapa-personas.js:4`. No se importa suelto. | El mapa de personas (página Filament). | Global obligatorio por diseño (los plugins de Leaflet no son ESM puros). El orden de import es una precondición no verificable por el bundler: si alguien reordena los `import` de `mapa-personas.js`, rompe en runtime sin aviso de build. |
| `mapa-personas.js` | Mapa de personas: encadena Leaflet + MarkerCluster + Heat en el orden correcto, con sus CSS. | npm: `leaflet.markercluster`, `leaflet.heat`, más los CSS de ambos. | `import` desde `vendor/…` en el `app.js` del host (`resources/js/README.md:31`). | personas-graneros / la página Filament de mapa de personas. | **Antes venía por CDN (unpkg)** y se internalizó (`mapa-personas.js:2`) — el riesgo de CDN ya está cerrado. Queda la dependencia de orden descrita arriba. No fija versiones: las resuelve el `node_modules` del host, así que dos sistemas pueden correr Leaflet distintos con el mismo módulo. |
| `filepond-video.js` | `MutationObserver` que marca con `.has-video-preview` los `.filepond--root` que ya contienen un `<video>`; esa clase es la que enciende el bloque «FILEPOND: TARJETA DE VIDEO» de `muni-ui-filament.css`. | Ninguna (IIFE, JS plano ES5). Sin imports ni globales. | **Publicado**, no importado: `vendor:publish --tag=muni-ui-filament` lo copia a `public/vendor/muni-ui/filepond-video.js` (`MuniUiServiceProvider.php:42`) y `MuniPanel.php:85` lo inyecta en `PanelsRenderHook::BODY_END` como `<script data-navigate-once src=…>`. | Todo panel Filament que monte `MuniPanel`. | `data-navigate-once` en la etiqueta es **imprescindible**: sin él cada `wire:navigate` apila un observador nuevo (documentado en `filepond-video.js:13-20` y `MuniPanel.php:78-82`). Es un `MutationObserver` sobre todo el `<body>` con `subtree:true` que en cada mutación recorre todos los `.filepond--root` — coste en INP en formularios grandes. Re-observa bien el `<body>` nuevo tras `livewire:navigated` (`:48`). **No está documentado en `resources/js/README.md`.** |
| `nombre-accesible-select.js` | Le devuelve el nombre accesible a los selects `searchable()` de Filament, que se pintan como `<button>` y quedan sin nombre (WCAG 4.1.2). Pone id a la etiqueta y `aria-labelledby` al botón. | Ninguna (IIFE, JS plano ES5). Sin imports ni globales. | **Publicado** igual que el anterior: `MuniUiServiceProvider.php:43` → `public/vendor/muni-ui/nombre-accesible-select.js`, inyectado por `MuniPanel.php:94` en `BODY_END` con `data-navigate-once`. | Todos los paneles del ecosistema («se corrige acá, una vez, para TODOS los paneles», `:16`). Crítico en la recepción de solicitudes ARCOP: el campo del titular de los datos. | **Defecto real: no re-observa el `<body>` tras `livewire:navigated`** (ver Hallazgo 1). Mismo coste de `MutationObserver` global que el anterior. Acopla a clases internas de Filament (`.fi-fo-field`, `:32`) y asume que «el control es un `<button>`» ⇒ es un select con buscador (`:39-44`): cualquier campo cuyo control sea un botón recibirá el `aria-labelledby`. **No está documentado en `resources/js/README.md`.** |

### Riesgos transversales de la capa

- **Dos vías de consumo incompatibles y solo una documentada.** `resources/js/README.md:22-36`
  explica únicamente la vía Vite (`import` desde `vendor/`). Los dos módulos del panel no se
  importan: se publican como archivos sueltos a `public/`. Un desarrollador que siga ese README
  al pie de la letra no encuentra `filepond-video.js` ni `nombre-accesible-select.js`.
- **Deriva silenciosa de los dos publicados.** Van en el tag `muni-ui-filament`
  (`MuniUiServiceProvider.php:38-44`), que el runbook sí manda republicar con `--force`
  (`README.md:102`). Pero la comprobación de deriva que el propio README recomienda
  (`README.md:117`) hace `diff` **solo de `filament.css`**: un `nombre-accesible-select.js`
  viejo pasa la comprobación y sigue sirviéndose. Es exactamente el fallo que el README describe
  en `:110-114` («el síntoma no se ve»), aplicado a los archivos que el propio README no mira.
- **Sin `package.json`, no hay contrato de versiones.** El paquete declara dependencias npm en
  prosa (`resources/js/README.md:5-7`) y confía en el `node_modules` del host. Nada impide que un
  sistema tenga `laravel-echo` v1 y otro v2 con el mismo `echo.js`.
- **CDN: ya no queda ninguno.** El único que había (unpkg, para Leaflet) se internalizó
  (`mapa-personas.js:2`). Las demos también son self-contained (`README.md:234`).

---

## Estado de tooling

### Lo que NO existe (verificado con `ls`)

| Buscado | Resultado |
|---|---|
| `package.json` | **no existe** — `ls: no se puede acceder a 'package.json'` |
| `CHANGELOG.md` | **no existe** — `ls: no se puede acceder a 'CHANGELOG.md'` |
| `docs/` más allá de superpowers | **no hay nada más**: `find docs -type f` devuelve un solo archivo, `docs/superpowers/plans/2026-08-18-accesibilidad-warn-y-title.md` |

### Lo que sí existe

- **Raíz:** `composer.json`, `composer.lock` (presente en disco pero **no versionado**: lo excluye
  `.gitignore`), `phpunit.xml`, `pint.json`, `phpstan.neon`, `phpstan-baseline.neon`,
  `README.md`, `CLAUDE.md`, `.mcp.json`, `.envrc`.
- **Suite real (Pest, no PHPUnit pelado):** `tests/Pest.php`, `TestCase.php`, `Fixtures/`,
  `ComponentesRenderTest.php`, `ContrasteTokensTest.php`, `FilepondVideoJsTest.php`,
  `FocoVisibleTest.php`, `MuniPanelTest.php`, `PanelArcopTest.php`, `ShellsTituloTest.php`,
  `TemaFilamentTest.php`.
- **Automatización:** `.github/`, `.githooks/`, `.claude/`.
- **Ledger SDD:** `.superpowers/sdd/` con su propio `.gitignore` y
  `plan-laravel-muni-ui/task-1-brief.md` (un único hallazgo, MEDIA: `SolicitudResource.php:198-208`
  acepta SVG con `openable()` ⇒ XSS almacenado en el panel).

### Qué excluye `.gitignore` (8 líneas)

```
/vendor/
composer.lock
/.phpunit.cache/
demo/vendor/

# direnv (tokens por frente; NUNCA versionar)
.envrc
.direnv/
```

Nota de seguridad sobre `.mcp.json` (**sí versionado**, `git ls-files` lo confirma): configura un
único servidor MCP, `github`, que corre `ghcr.io/github/github-mcp-server:v1.11.0` vía `docker run`
en modo solo lectura (`GITHUB_READ_ONLY=1`). El `GITHUB_PERSONAL_ACCESS_TOKEN` **no está en claro**:
el valor es un marcador de sustitución de entorno (14 caracteres, con forma `${…}`), que resuelve
direnv desde `.envrc` — y `.envrc` sí está ignorado. La higiene es correcta.

### Los dos defectos del plan del 2026-08-18: **ambos corregidos**

**Defecto 1 — el ámbar de aviso (`--muni-warn-fg`): CORREGIDO, con un valor distinto al que
eligió el plan.**

| Bloque | Línea real | `--muni-warn-fg` | `--muni-warn-bg` | Ratio medido | Veredicto |
|---|---|---|---|---|---|
| `:root` (claro por omisión) | `muni-ui.css:65-66` | `#8a5a00` | `#fef3dc` | **5.38:1** | pasa AA |
| `@media (prefers-color-scheme: dark)` | `muni-ui.css:130-131` | `#f59e0b` | `#2d1d00` | 7.60:1 | pasa AA |
| `.dark` | `muni-ui.css:177-178` | `#f59e0b` | `#2d1d00` | 7.60:1 | pasa AA |
| `[data-muni-theme="light"]` | `muni-ui.css:221-222` | `#8a5a00` | `#fef3dc` | **5.38:1** | pasa AA |

El plan (`:40`, `:251`) había elegido `#96590a` (5.11:1). El valor real en disco es **`#8a5a00`**,
que da **5.38:1** — más margen todavía, y sigue leyéndose como ámbar/tierra, así que respeta la
restricción del plan (`:17`). El defecto original (`#c47a10`, 3.11:1) desapareció de los cuatro
bloques. Los números de línea del plan (65 y 199) ya no valen: el bloque claro explícito se corrió
a 221.

**Defecto 2 — el `<title>` de `auth-shell`: CORREGIDO.**

`auth-shell.blade.php:17-18` hoy dice:

```blade
    <x-muni::reverb-meta />
    <title>{{ $title }} · {{ $system }}</title>
```

`reverb-meta` es ahora **hermano** del `<title>`, no está dentro. El `<title>` contiene solo texto
interpolado. Además quedó un comentario explicativo en `:14-16` que documenta el porqué («dentro
es RCDATA, o sea texto, así que el marcado se vería en la pestaña y la `<meta>` no existiría como
elemento (echo.js se quedaba sin clave y el tiempo real, apagado)») — que es precisamente el
encadenamiento con `echo.js` que el plan no había detectado.

**Divergencias del plan respecto de lo ejecutado** (el plan quedó como documento histórico, no
como descripción del estado actual):

- Los archivos de prueba se llaman distinto: el plan pedía `tests/Contraste.php`,
  `tests/ContrasteTest.php` y `tests/AuthShellTest.php`; en disco están `ContrasteTokensTest.php`
  y `ShellsTituloTest.php`, y la suite migró a **Pest** (`tests/Pest.php`), no PHPUnit plano.
- La Tarea 3 (`:378`) preveía tocar `CHANGELOG.md` «si existe» — no existe, y sigue sin existir.
- Los tres checkboxes de las tres tareas siguen **todos sin marcar** (`- [ ]`) pese a que el
  trabajo está hecho.

---

## Reglas escritas que un modernizador debe respetar

Citas verbatim, con su origen.

- **Blade puro compatible con Livewire 3 y 4** — `CLAUDE.md:28-31`: «los componentes de este
  paquete se instalan tanto en aplicaciones con **Livewire 4 / Filament 5** (los 9 sistemas
  municipales) como en **personas-graneros, que sigue en Livewire 3 / Filament 3**. Por eso deben
  ser **Blade puros**, sin depender de directivas exclusivas de un major: nada de `@island`,
  `wire:show`, `wire:sort` ni `#[Transition]` dentro del paquete.»
- **Interactividad solo con Alpine** — `CLAUDE.md:30-31`: «Si un componente necesita
  interactividad, resolverla con Alpine, que existe en ambos.»
- **Frameworks vetados** — `CLAUDE.md:35`: «Blade + Livewire + Alpine + Filament. **No Vue, no
  React, no jQuery, no Inertia.**»
- **Política de plugins de Alpine** — `README.md:225`: «Los interactivos usan **Alpine 3 core**
  (sin plugins)». Y `CLAUDE.md:36-38`: «Alpine viene dentro de Livewire: nunca instalarlo ni
  importarlo aparte en el bundle del panel. (Un entrypoint público aislado que no carga Livewire
  es la única excepción, y se declara como `input` propio en `vite.config.js`.)»
- **Política de Tailwind** — `CLAUDE.md:39`: «Tailwind para todo el estilado; nada de CSS suelto
  salvo `@layer` en `app.css`.» Y la versión, `README.md:61`: «Tailwind CSS **v4** (usa `@theme`,
  `@custom-variant`, `@source`)».
- **Motion One** — `CLAUDE.md:40`: «Animación: **Motion One** (`motion` ^13). No usar GSAP,
  anime.js ni AOS.»
- **Sin JS primero** — `CLAUDE.md:41`: «Todo componente debe funcionar sin JS antes de animarse.»
- **CSS moderno dentro de `@supports`** — `CLAUDE.md:42-43`: «CSS moderno (anchor positioning,
  scroll-driven, container queries) solo como mejora progresiva, dentro de `@supports`.»
- **La contraparte `.dark` es obligatoria** — `README.md:44-48`: «Toda regla que fije `color` para
  el modo claro necesita su contraparte `.dark`. No es una recomendación de estilo: las reglas de
  este archivo llevan `!important` y ganan sobre las de Filament —que vienen con
  `:where(.dark, .dark *)`, especificidad CERO a propósito—, así que **la variante clara se aplica
  también en oscuro** y el texto queda encima de un fondo para el que no fue pensado.»
- **Reutilizar los tonos oscuros existentes** — `README.md:54-56`: «Los tonos para fondo oscuro ya
  existen y conviene reutilizarlos en vez de inventar: `--mg-sb-txt` para texto principal,
  `--mg-sb-mut` para atenuado, `--mg-lima-br` para acento.»
- **Firma del sistema (`data-muni-row` / `.muni-num`)** — `README.md:228-230`: «**Firma del
  sistema:** la morosidad no es un badge redondo suelto — una fila
  `<tr data-muni-row class="muni-row--danger">` pinta una franja de estado en el borde izquierdo
  (banda de libro mayor), y los RUT/cifras usan `.muni-num` (mono tabular).»
- **Los tokens se agregan aquí, no en el host** — `CLAUDE.md:7-8`: «**Regla base:** todo token de
  color, tipografía, radio o sombra que se necesite en más de un sistema se agrega **aquí**, no en
  el repo host. Los hosts consumen; no redefinen.»
- **El host puede sobreescribir, no renombrar** — `README.md:18-19`: «Un sistema existente puede
  **sobreescribir los tokens** (`--muni-*`) para conservar su identidad propia sin tocar los
  componentes.»
- **El paquete no impone look** — `README.md:10-11`: «El paquete NO impone un look. Empaqueta la
  **maquinaria** (tokens, componentes) y trae dos **presets** de arranque para sistemas nuevos.»
- **Sin bundle propio** — `CLAUDE.md:10-11`: «Este paquete no tiene bundle propio: Motion One y el
  resto del JS se instalan y exponen en el `resources/js/app.js` de cada aplicación host.»
- **Accesibilidad y movimiento de los componentes** — `README.md:222-226`: «Todos respetan
  `prefers-reduced-motion`, tienen estados `:focus-visible` con anillo de foco accesible
  (`--muni-ring`) […] El CSS del paquete trae la regla `[x-cloak]` para evitar el flash inicial.»
- **Dark mode: no elegir un mecanismo** — `README.md:23-24`: «El sistema activa el tema oscuro con
  **cualquiera** de estos mecanismos, para convivir con todo el ecosistema a la vez — no hay que
  elegir uno.» Con la jerarquía de `README.md:33-34` y el aviso de `:36-37`: «Dentro de un **panel
  Filament**: no pongas `data-muni-theme`».
- **Las demos son self-contained** — `README.md:234`: «Todas self-contained (Alpine inline, sin
  CDN).»
- **`data-navigate-once` en los scripts del panel** — `MuniPanel.php:78-82`: «`data-navigate-once`
  es obligatorio en ESTA etiqueta (no en el archivo JS): sin él, cada navegación por
  `wire:navigate` vuelve a ejecutar el script y apila un observador nuevo encima del anterior en
  vez de reemplazarlo.»
- **Solo se comparte lo genuinamente común** — `resources/js/README.md:9-12`: «Solo se comparte lo
  que es genuinamente común. `tour.js` y `calendario-global.js` **no** están aquí a propósito:
  divergen entre sistemas por diseño […] `echo.js` de licencias tampoco: usa un proxy Caddy en vez
  de meta-tags de Reverb, otra topología.»
- **Las convenciones de diseño no se duplican acá** — `CLAUDE.md:15-18`: «**Las convenciones
  completas viven en la skill global `blade-livewire-design` […] — fuente única.** […] No duplicar
  ese contenido acá: si una convención cambia, se cambia en la skill.»
- **El plugin ARCOP no hace cumplir por sí solo** — `README.md:291-293`: «**Heredar el panel da la
  superficie para recibir y resolver solicitudes; no hace que el sistema cumpla.**»

---

## Hallazgos

1. **`nombre-accesible-select.js:77` no re-observa el `<body>` tras una navegación SPA, y por eso
   deja de corregir el nombre accesible después del primer `wire:navigate`.** El módulo registra
   `observador.observe(document.body, …)` una sola vez, dentro de `arrancar()` (`:65-68`), y en
   `livewire:navigated` solo vuelve a llamar a `nombrarSelectsConBuscador` (`:77`), sin
   re-observar. Su gemelo `filepond-video.js` sí lo hace: su `observar()` desconecta y vuelve a
   observar el `<body>` nuevo (`:31-40`, invocado desde `:48`), y documenta el motivo en `:44-47`
   («Livewire reemplaza el `<body>` entero al navegar por `wire:navigate`: un observador que sigue
   apuntando al `<body>` viejo queda vigilando un nodo ya desechado»). Consecuencia: tras navegar,
   el `MutationObserver` queda atado a un `<body>` desechado, así que los redibujados de Livewire
   *dentro* de esa pantalla —elegir una opción, un error de validación— ya no reaplican el
   `aria-labelledby`. Es justo el escenario que el propio archivo dice cubrir en `:59-60` («El
   formulario se redibuja con cada respuesta de Livewire —al elegir una opción, al validar—, así
   que no alcanza con recorrerlo una vez al cargar»). Impacto: WCAG 4.1.2 en el campo del titular
   de datos de la recepción ARCOP, que es el caso que el archivo declara proteger (`:11-13`).

2. **`README.md:242,245,246` nombran tres demos que no existen.** `landing-hub.html` y
   `landing-control-acceso.html` fueron renombradas a `intranet-hub.html` e
   `intranet-control-acceso.html` sin actualizar el README; `landing-patentes.html` no existe con
   ningún nombre, pese a que patentes es el ejemplo central del README (`:129-153`).

3. **`resources/js/README.md:16-20` documenta 3 de los 5 módulos.** Faltan `filepond-video.js` y
   `nombre-accesible-select.js`, que además se consumen por una vía distinta (publicación a
   `public/vendor/muni-ui/` + render hook) que ese README no describe: su sección «Uso» (`:22-36`)
   solo explica el `import` desde `vendor/` en el pipeline de Vite.

4. **La comprobación de deriva del README solo mira el CSS, pero el tag publica también JS.**
   `README.md:117` propone `diff public/vendor/muni-ui/filament.css …`, mientras que el tag
   `muni-ui-filament` copia además `filepond-video.js` y `nombre-accesible-select.js`
   (`MuniUiServiceProvider.php:38-44`). Un sistema puede tener el CSS al día y servir el JS de
   accesibilidad viejo, pasando la comprobación que el propio README recomienda — el mismo fallo
   silencioso que el README describe en `:110-114`.

5. **`README.md:222` afirma «Todos respetan `prefers-reduced-motion`» y cuatro componentes no lo
   hacen.** El interruptor global está en `muni-ui.css:244-246` y solo pone `--muni-dur: 0ms`, así
   que únicamente alcanza a quien anima con `var(--muni-dur)`. Animan con duración fija y nunca
   referencian ese token: `chart-donut.blade.php:38` (`transition:stroke-dasharray .7s`),
   `ring.blade.php:27` (`stroke-dashoffset .8s`), `progress.blade.php:35` (`width .5s`) y
   `chart-bar.blade.php:47` (`height .6s`). Son los cuatro componentes de visualización de datos.
   (`skeleton.blade.php:22` sí trae su propia guarda y está bien.)

6. **Cero `@supports` en todo el paquete, contra la regla de `CLAUDE.md:42-43`.** `grep -c
   "@supports"` sobre los 53 componentes no devuelve ninguna coincidencia, pero se usa CSS moderno
   sin guarda: `color-mix()` en `auth-shell`, `error-page`, `timeline`, `skeleton` y `chart-bar`, y
   `:has()` en `segmented.blade.php:36`. El caso de `segmented` es el que importa: ese `:has()` es
   el **único** indicador de foco del control (`.muni-seg:has(input:focus-visible) { outline:3px … }`),
   así que donde `:has()` no resuelva no queda anillo de foco alguno — y `segmented` se documenta
   como «radios reales sin JS» (`README.md:169`), o sea, pensado para el camino degradado.

7. **`CLAUDE.md:39` («Tailwind para todo el estilado; nada de CSS suelto salvo `@layer` en
   `app.css`») está contradicho por el propio paquete:** 35 de los 53 componentes embeben un
   bloque `<style>` con CSS escrito a mano (31 de ellos dentro de `@once`). Los 4 sin `@once`
   —`app-shell`, `dashboard-shell`, `auth-shell`, `error-page`— son shells de página completa y no
   necesitan la guarda, así que ahí no hay defecto; el problema es que la regla escrita no
   describe la arquitectura real y un modernizador que la aplique al pie de la letra reescribiría
   el paquete entero.

8. **`accordion.blade.php:8` documenta un componente que no existe:** «*o usar el slot con
   `<x-muni::accordion-item>`*». No hay `accordion-item.blade.php` en
   `resources/views/components/`. Quien siga el comentario obtiene un error de Blade.

9. **18 de 53 componentes (34 %) no tienen fila en el README.** Entre ellos la familia `gob-*`
   completa (`gob-bar`, `gob-escudo`, `gob-footer`, `gob-stripe`), que es la identidad
   institucional del Estado, y componentes con superficie de API grande como `sortable-table`
   (4 props), `file-dropzone` (5), `chart-donut` (5), `ring` (6) y `rating` (5).

10. **Seis filas del README omiten props que el componente sí acepta:** `topbar` no documenta
    `statusLabel` (`README.md:161`), `field` no documenta `name` (`:171`), `select` no documenta
    `hint` ni `required` (`:206`), `dashboard-shell` no documenta `title` (`:219`) y `error-page`
    no documenta `system` (`:220`). No hay props inventadas: el README omite, no fabula.

11. **El plan `docs/superpowers/plans/2026-08-18-accesibilidad-warn-y-title.md` quedó como
    documento histórico que ya no describe el repo.** Los dos defectos están corregidos, pero: el
    color elegido en disco es `#8a5a00` (5.38:1) y no el `#96590a` (5.11:1) del plan (`:40`,
    `:251`); los archivos de prueba se llaman `ContrasteTokensTest.php` y `ShellsTituloTest.php`,
    no `ContrasteTest.php` / `AuthShellTest.php` (`:60-61`); la suite migró a Pest
    (`tests/Pest.php`); las líneas 65/199 que cita (`:62`) ya son 65/221; y los checkboxes de las
    tres tareas siguen todos sin marcar pese a estar hechas.

12. **`CLAUDE.md:10-11` afirma que «Este paquete no tiene bundle propio: Motion One y el resto del
    JS se instalan y exponen en el `resources/js/app.js` de cada aplicación host», pero el paquete
    sí distribuye JS propio:** cinco módulos en `resources/js/`, dos de los cuales se publican como
    archivos servidos desde `public/` e inyectados por render hook, sin pasar por el `app.js` de
    nadie (`MuniUiServiceProvider.php:42-43`, `MuniPanel.php:85,94`).

13. **El `README.md` raíz no menciona la capa JS en absoluto.** `grep` de `resources/js`,
    `echo.js`, `filepond`, `leaflet`, `nombre-accesible` y `Motion` sobre el README no devuelve
    ninguna coincidencia. La única documentación es `resources/js/README.md`, que a su vez cubre
    3 de 5 módulos (Hallazgo 3). El «Roadmap» (`:305-309`) tampoco la nombra.

14. **No hay `CHANGELOG.md`.** El propio plan lo daba por opcional (`:378`), y `README.md:93-108`
    advierte que actualizar la versión no aplica nada por sí solo; sin changelog, un consumidor no
    tiene forma de saber qué cambió entre dos etiquetas ni si le toca republicar artefactos.

15. **No hay `package.json`,** así que las dependencias npm de `resources/js/` (`laravel-echo`,
    `pusher-js`, `leaflet`, `leaflet.markercluster`, `leaflet.heat`) viven solo como prosa en
    `resources/js/README.md:5-7` y se resuelven contra el `node_modules` de cada host, sin ningún
    rango de versión declarado ni verificable.

16. **`reverb-meta.blade.php` no tiene bloque `@props` y no está documentado**, pese a ser una
    pieza de infraestructura de la que depende `echo.js` para obtener la clave en runtime
    (`echo.js:31-32`, `reverb-meta.blade.php:16-18`). Su contrato real son tres claves de
    `config('broadcasting.connections.reverb.options.*')` —`host_publico`, `puerto_publico`,
    `esquema_publico`— que ningún README nombra.


---

## Hallazgos consolidados

- **CSS y tokens:** `--muni-gob-petroleo-dark` vale `#00404c` en `muni-ui.css:35` pero el puente lo redefine como `var(--mg-petroleo-d)` = `#0d3a44` (`muni-ui-filament.css:71`, definido en `:7`): rompe el contrato de invariante institucional para `gob-bar.blade.php:29` y `gob-footer.blade.php:53` dentro de un panel.
- **CSS y tokens:** `--muni-panel` (`muni-ui.css:49`) y `--muni-gob-verde-dark` (`muni-ui.css:33`) no existen en el puente Filament (`muni-ui-filament.css:6-139`), ni en `:root` ni en `.dark`: dentro del panel resuelven a nada. Sin consumidores hoy.
- **CSS y tokens:** Ningún `--mg-*` se redefine en `.dark` (`muni-ui-filament.css:7-11`): todo `var(--mg-*)` es light-only por construcción, forzando al bloque oscuro (l. 271-325) a repetir literales selector por selector.
- **CSS y tokens:** `--mg-tinta-d` (`muni-ui-filament.css:8`) se define y nunca se referencia con `var()`; su literal `#08252b` se repite a mano en l. 116.
- **CSS y tokens:** Bordes `var(--mg-borde)` sin contraparte `.dark`: `.fi-input-wrp` en reposo (l. 225), `.fi-topbar .fi-input-wrp, .fi-global-search-field .fi-input-wrp` (l. 196), `.fi-dropdown-panel` (l. 234) y `.fi-simple-main` (l. 268, con `!important`).
- **CSS y tokens:** `.filepond--root.has-video-preview .filepond--file-info { border-bottom:1px solid #e2e8f0 }` (`muni-ui-filament.css:381`) sin contraparte `.dark` (l. 380 solo cubre `.filepond--item-panel`): línea clara de 14,4:1 sobre `#18181b`.
- **CSS y tokens:** `.fi-wi-stats-overview-stat { border-top:3px solid var(--mg-petroleo) !important }` (`muni-ui-filament.css:213`) queda pisado en oscuro por `.dark .fi-wi-stats-overview-stat { border-color:#1c343a !important }` (l. 272, mayor especificidad): el acento superior desaparece en oscuro.
- **CSS y tokens:** `.fi-ta-row:hover` (`muni-ui-filament.css:228`) sin contraparte: por ir sin `@layer`, pisa el hover oscuro de Filament y el feedback de hover de filas es imperceptible en oscuro.
- **CSS y tokens:** `prefers-reduced-motion` solo pone `--muni-dur:0ms` en `muni-ui.css:244-246`; `muni-ui-filament.css:89` fija `--muni-dur:160ms` sin ese override y su propio `@media (prefers-reduced-motion:reduce)` (l. 260) solo apaga `mg-fall`. `src/Filament/MuniPanel.php:73-75` carga únicamente `filament.css`, así que los 35 usos de `var(--muni-dur)` en componentes siguen animando dentro del panel para usuarios con movimiento reducido.
- **CSS y tokens:** `--muni-glow`: `muni-ui.css` dark = `0 0 6px currentColor` (l. 150, 197); puente Filament = `none` (l. 86) sin redefinir en `.dark`. En un panel oscuro pierden el resplandor `badge.blade.php:26`, `timeline.blade.php:36`, `toast-host.blade.php:62`, `command-palette.blade.php:51`, `rating.blade.php:40`, `chart-donut.blade.php:50` y `error-page.blade.php:29`.
- **CSS y tokens:** Sombras oscuras divergentes: `--muni-shadow-md`/`-lg` en `muni-ui.css` dark (l. 140-141, 187-188) no coinciden con las del puente (`muni-ui-filament.css:136-137`).
- **CSS y tokens:** `--muni-danger-border` rompe el patrón `border = fg` que sí siguen ok/warn/info en el puente: light `#e0a3ad` (`muni-ui-filament.css:64`) = 2,10:1 sobre blanco y 1,64:1 sobre `--muni-danger-bg`; dark `#6b2430` (l. 133) = 1,53:1 sobre `#0f2025` — bajo el 3:1 de WCAG 1.4.11 si algún componente lo usa como único límite del aviso.
- **CSS y tokens:** `--muni-hint #6b7280` (`muni-ui.css:53`) da 4,16:1 sobre `--muni-surface-3 #eceef2` y 4,47:1 sobre `--muni-bg` (bajo 4,5:1 AA); en el puente dark `--muni-hint #7e8ea0` (`muni-ui-filament.css:103`) da 4,46:1 sobre `--muni-surface-3 #142a30`. El texto de ayuda sobre superficies secundarias está al borde o por debajo de AA.
- **CSS y tokens:** `muni-ui-filament.css` no deriva el modo oscuro de tokens: colores como `#f8f5ec`, `#081418`, `#0f2025`, `#1c343a`, `#eaf5f3` se repiten como literales en vez de `var(--muni-*)` (nota propia del archivo en l. 303-305): cualquier clase de Filament no enumerada en el bloque oscuro se queda con su valor claro.
- **CSS y tokens:** La tarjeta de video FilePond (`muni-ui-filament.css:379-398`) usa paleta slate/zinc de Tailwind y no los tokens `--mg-*`/`--muni-*`: en oscuro es una tarjeta zinc (`#18181b`) dentro de un panel petróleo (`#0f2025`).
- **CSS y tokens:** `muni-ui-tailwind.css:14-18` (`@custom-variant dark`) no incluye `@media (prefers-color-scheme: dark)` ni excluye `[data-muni-theme=light]`, mientras que `muni-ui.css` sí sigue al SO (l. 111) y sí se congela (l. 203): en una PWA que sigue al SO, los tokens quedan oscuros pero las utilidades `dark:` no se aplican (y viceversa con `.dark` + `data-muni-theme="light"` combinados).
- **CSS y tokens:** `muni-ui-tailwind.css:20-41` (`@theme inline`) expone 14 colores/2 fuentes/2 radios y deja fuera `--muni-border-2`, `--muni-accent-strong/soft`, `--muni-on-accent`, `--muni-focus`, los `*-bg`/`*-border` de estado, los `--muni-gob-*`, `--muni-radius-sm` y las sombras; `--color-muni-ok/warn/danger/info` mapean solo a `*-fg`.
- **CSS y tokens:** Jerarquía frágil en `muni-ui.css`: la regla del SO (l. 112) tiene especificidad (0,4,0) sobre `:root` frente a (0,1,0) de `.dark`/`[data-theme="dark"]`/`[data-muni-theme="dark"]` (l. 157-159); hoy no se nota porque los 35 valores dark coinciden, pero contradice el orden documentado en l. 15-17 si algún día divergen. Además, `.light` y `[data-theme="light"]` no traen valores propios: solo `[data-muni-theme="light"]` congela de verdad.
- **CSS y tokens:** `color-mix()` sin fallback en `--muni-ring` (`muni-ui.css:82/142/189/233`) y en numerosas reglas de `muni-ui-filament.css` (l. 42, 115, 123, 126, 129, 132, 195, 197, 213, 214, 226, 227, 228, 324): sin soporte del navegador la declaración entera se descarta.
- **CSS y tokens:** `--muni-gob-gris` en el puente es literal `#9c9b9b` (`muni-ui-filament.css:76`, también en l. 267) porque no existe `--mg-gris`; el resto de `--muni-gob-*` sí apunta a un `--mg-*`.
- **CSS y tokens:** `--muni-accent-strong` en el puente dark es `var(--mg-lima-br)` (lima, l. 114) mientras `--muni-accent` es `var(--mg-celeste)` (l. 113): el estado "strong"/hover cambia de tono en vez de intensificar el mismo acento, a diferencia de light y de `muni-ui.css`.
- **CSS y tokens:** `--muni-ring` no se redefine en `.dark` del puente (`muni-ui-filament.css:85` única definición): re-resuelve a celeste solo porque `.dark` va en `<html>` en Filament (el `var(--muni-accent)` se sustituye donde se declara `--muni-ring`, en `:root`); con `.dark` en un descendiente, el anillo seguiría petróleo.
- **CSS y tokens:** `.fi-sidebar, .fi-sidebar-nav` (`muni-ui-filament.css:142-146`) pintan el mismo degradado en el contenedor y en el `nav` anidado dentro de él (doble pintado, inocuo pero redundante).
- **Documentación y JS:** nombre-accesible-select.js:77 no re-observa el <body> tras livewire:navigated (solo re-ejecuta la pasada), a diferencia de filepond-video.js:31-48 que sí desconecta y re-observa: tras la primera navegación wire:navigate el MutationObserver queda atado a un <body> desechado y los redibujados de Livewire dentro de la pantalla ya no reaplican aria-labelledby. WCAG 4.1.2 en el campo del titular ARCOP, que es justo lo que el archivo declara proteger (:11-13, :59-60).
- **Documentación y JS:** README.md:242,245,246 nombran tres demos inexistentes: landing-hub.html y landing-control-acceso.html se renombraron a intranet-hub.html e intranet-control-acceso.html sin actualizar el README, y landing-patentes.html no existe con ningún nombre pese a que patentes es el ejemplo central del README (:129-153).
- **Documentación y JS:** resources/js/README.md:16-20 documenta 3 de los 5 módulos: faltan filepond-video.js y nombre-accesible-select.js, que además se consumen por una vía (publicación a public/vendor/muni-ui/ + render hook) que la sección «Uso» (:22-36) no describe, pues solo explica el import desde vendor/ en Vite.
- **Documentación y JS:** README.md:117 propone comprobar la deriva con un diff de solo filament.css, pero el tag muni-ui-filament publica además filepond-video.js y nombre-accesible-select.js (MuniUiServiceProvider.php:38-44): un sistema puede tener el CSS al día y servir el JS de accesibilidad viejo pasando la comprobación que el propio README recomienda.
- **Documentación y JS:** README.md:222 afirma «Todos respetan prefers-reduced-motion» pero el interruptor global (muni-ui.css:244-246) solo pone --muni-dur: 0ms, y cuatro componentes animan con duración fija sin referenciar ese token: chart-donut.blade.php:38 (.7s), ring.blade.php:27 (.8s), progress.blade.php:35 (.5s) y chart-bar.blade.php:47 (.6s).
- **Documentación y JS:** Cero @supports en los 53 componentes, contra CLAUDE.md:42-43, pese a usar color-mix() en auth-shell, error-page, timeline, skeleton y chart-bar, y :has() en segmented.blade.php:36 — donde ese :has() es el ÚNICO indicador de foco del control, en un componente documentado como «radios reales sin JS» (README.md:169).
- **Documentación y JS:** CLAUDE.md:39 («Tailwind para todo el estilado; nada de CSS suelto salvo @layer en app.css») está contradicho por el propio paquete: 35 de los 53 componentes embeben un bloque <style> con CSS escrito a mano.
- **Documentación y JS:** accordion.blade.php:8 documenta «usar el slot con <x-muni::accordion-item>» y ese componente no existe en resources/views/components/: quien siga el comentario obtiene un error de Blade.
- **Documentación y JS:** 18 de 53 componentes (34 %) no tienen fila en el README, incluida la familia gob-* completa (gob-bar, gob-escudo, gob-footer, gob-stripe) y componentes con API grande como sortable-table, file-dropzone, chart-donut, ring y rating.
- **Documentación y JS:** Seis filas del README omiten props reales: topbar sin statusLabel (README.md:161), field sin name (:171), select sin hint ni required (:206), dashboard-shell sin title (:219) y error-page sin system (:220). No hay props inventadas: el README omite, no fabula.
- **Documentación y JS:** El plan docs/superpowers/plans/2026-08-18-accesibilidad-warn-y-title.md ya no describe el repo: el color en disco es #8a5a00 (5.38:1) y no el #96590a (5.11:1) del plan (:40,:251); los tests se llaman ContrasteTokensTest.php y ShellsTituloTest.php, no ContrasteTest.php/AuthShellTest.php (:60-61); la suite migró a Pest; las líneas 65/199 (:62) hoy son 65/221; y los checkboxes de las tres tareas siguen sin marcar pese a estar hechas.
- **Documentación y JS:** CLAUDE.md:10-11 afirma que «este paquete no tiene bundle propio» y que el JS se expone desde el app.js de cada host, pero el paquete distribuye cinco módulos en resources/js/, dos de ellos servidos desde public/ e inyectados por render hook sin pasar por ningún app.js (MuniUiServiceProvider.php:42-43, MuniPanel.php:85,94).
- **Documentación y JS:** El README.md raíz no menciona la capa JS en absoluto: grep de resources/js, echo.js, filepond, leaflet, nombre-accesible y Motion no devuelve ninguna coincidencia, y el Roadmap (:305-309) tampoco la nombra.
- **Documentación y JS:** No existe CHANGELOG.md, así que un consumidor no tiene forma de saber qué cambió entre dos etiquetas ni si le toca republicar artefactos — precisamente el riesgo que README.md:93-108 advierte.
- **Documentación y JS:** No existe package.json: las dependencias npm de resources/js/ (laravel-echo, pusher-js, leaflet, leaflet.markercluster, leaflet.heat) viven solo como prosa en resources/js/README.md:5-7 y se resuelven contra el node_modules de cada host, sin rango de versión declarado ni verificable.
- **Documentación y JS:** reverb-meta.blade.php no tiene bloque @props y no está documentado en ningún README, pese a ser la pieza de la que depende echo.js:31-32 para obtener la clave en runtime; su contrato real son tres claves de config('broadcasting.connections.reverb.options.*') (host_publico, puerto_publico, esquema_publico) que nadie nombra.

