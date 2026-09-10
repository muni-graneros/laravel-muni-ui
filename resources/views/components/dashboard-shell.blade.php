@props([
    'theme' => 'light',
    'title' => null,
    'system' => 'Panel',
    'subtitle' => null,
    'status' => 'online',
    'user' => null,
])

<!DOCTYPE html>
<html lang="es" data-muni-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- La meta va FUERA del <title>: dentro es RCDATA, o sea texto, así que
         el marcado se vería en la pestaña y la <meta> no existiría como
         elemento (echo.js se quedaba sin clave y el tiempo real, apagado). --}}
    <x-muni::reverb-meta />
    <title>{{ $title ?? $system }}</title>
    {{ $head ?? '' }}
    <style>
        *,*::before,*::after{ box-sizing:border-box; }
        body{ margin:0; min-height:100vh; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); display:flex; }
        .muni-ds__col{ flex:1; min-width:0; display:flex; flex-direction:column; }
        .muni-ds__top{ position:sticky; top:0; z-index:40; height:var(--muni-topbar-h); display:flex; align-items:center; gap:12px; padding:0 18px; background:var(--muni-surface); border-bottom:1px solid var(--muni-border); }
        /* 44x44 reales: es el objetivo táctil del inspector con tablet en terreno,
           no el mínimo de 24x24 de escritorio. El icono va centrado, no estirado. */
        .muni-ds__burger{ display:inline-flex; align-items:center; justify-content:center; min-width:44px; min-height:44px; margin-left:-10px; padding:6px; border:none; background:transparent; color:var(--muni-text); cursor:pointer; border-radius:var(--muni-radius-sm); transition:background var(--muni-dur) var(--muni-ease); }
        .muni-ds__burger:hover{ background:var(--muni-surface-2); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-ds__burger:focus-visible{ outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        /* Quién decide si el hamburguesa y el velo se ven es el estado que
           publica la lateral, no una consulta de medios repetida acá: el punto de
           quiebre vive en `sidebar` y llega por el evento `muni-sidebar-state`.
           Antes de que Alpine monte no se muestran —sin JS el botón no haría nada
           igual—, y por eso NO pueden nacer con `display:none`: `x-show` solo
           quita el `display` en línea y el del CSS ganaría para siempre. */
        .muni-ds__burger[x-cloak], .muni-ds__scrim[x-cloak]{ display:none !important; }
        /* `.muni-ds__top` es sticky: sin este margen el destino del salto aterriza debajo de la cabecera. */
        .muni-ds__main{ flex:1; padding:24px; max-width:1280px; width:100%; margin:0 auto; scroll-margin-top:var(--muni-topbar-h); }
        /* El velo queda entre la lateral superpuesta (150) y la cabecera fija (40).
           El mismo negro translúcido de modal y drawer: no aporta color, oscurece,
           así que sirve igual en tema claro y en oscuro. */
        .muni-ds__scrim{ display:block; position:fixed; inset:0; z-index:140; background:rgba(10,14,20,.5); }
    </style>
</head>
<body>
    {{-- Antes del menú lateral: si va después, el teclado tabula los ~15 ítems
         del menú justo antes de encontrar el atajo para saltárselos. --}}
    <x-muni::skip-link />

    {{ $sidebar ?? '' }}

    <div class="muni-ds__col">
        {{-- EL SCOPE DE ALPINE VA ACÁ, no en el <body>.

             El hamburguesa llevaba `@click` sin un solo `x-data` antecesor en
             todo el archivo: Alpine nunca lo enlazaba, el evento jamás se
             despachaba y bajo el punto de quiebre la lateral era inalcanzable.

             Va en el <header> y no en el <body> por dos razones: el <body> es
             territorio del layout de Livewire y de Filament, y la lateral tiene
             que seguir sirviendo suelta —fuera de este armazón— con su propio
             estado. Por eso acá no se guarda la verdad: se ESCUCHA. La lateral
             publica `muni-sidebar-state` y este scope solo refleja lo que llega,
             que es lo que hace que `aria-expanded` no mienta.

             El ping del arranque cubre el caso de que el armazón se monte antes
             que la lateral; la lateral, por su parte, reemite en `$nextTick`
             para cubrir el orden contrario. --}}
        <header class="muni-ds__top"
            x-data="{ open: false, overlay: false, controls: 'muni-sidebar' }"
            x-init="$dispatch('muni-sidebar-ping')"
            @muni-sidebar-state.window="
                open = !! $event.detail.open;
                overlay = !! $event.detail.overlay;
                controls = $event.detail.id || controls;
            "
        >
            <button
                type="button"
                class="muni-ds__burger"
                x-cloak
                x-show="overlay"
                @click="$dispatch('muni-sidebar')"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-controls="controls"
                aria-label="Menú de navegación"
            >
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20" aria-hidden="true"><path d="M3 6h14M3 10h14M3 14h14" stroke-linecap="round"/></svg>
            </button>
            <div style="display:flex;flex-direction:column;line-height:1.2;min-width:0;">
                <span style="font-size:13px;font-weight:700;">{{ $system }}</span>
                @if ($subtitle)<span style="font-size:11px;color:var(--muni-muted);">{{ $subtitle }}</span>@endif
            </div>
            <span style="flex:1;"></span>
            {{ $topbar ?? '' }}
            <x-muni::badge :tone="$status === 'online' ? 'ok' : ($status === 'degraded' ? 'warn' : 'danger')">
                {{ ['online' => 'En línea', 'degraded' => 'Degradado', 'offline' => 'Caído'][$status] ?? $status }}
            </x-muni::badge>
            @if ($user)<x-muni::avatar :name="$user" size="md" />@endif

            {{-- El velo se teletransporta al <body> porque el <header> es sticky
                 con z-index: dentro de él quedaría atrapado en ese contexto de
                 apilamiento y su z-index de 140 no significaría nada. Sigue
                 dentro de este scope de Alpine, que es lo que importa.

                 `x-trap.inert` de la lateral marca `aria-hidden` en lo de
                 alrededor —no el atributo `inert`—, así que el velo se sigue
                 pudiendo clicar para cerrar. Sin transición a propósito: una
                 duración fija ignoraría `prefers-reduced-motion`. --}}
            <template x-teleport="body">
                <div
                    class="muni-ds__scrim"
                    x-cloak
                    x-show="overlay && open"
                    @click="$dispatch('muni-sidebar-close')"
                    aria-hidden="true"
                ></div>
            </template>
        </header>

        {{-- `tabindex="-1"`: sin él el salto mueve el scroll pero no el punto de lectura. --}}
        <main id="muni-contenido" tabindex="-1" class="muni-ds__main">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
