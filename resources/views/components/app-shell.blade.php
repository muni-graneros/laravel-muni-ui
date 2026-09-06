@props([
    'theme' => 'light',
    'title' => null,
    'system',
    'subtitle' => null,
    'status' => 'online',
    'maxWidth' => '1200px',
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
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: var(--muni-bg); color: var(--muni-text); font-family: var(--muni-font-sans); }
        a { color: var(--muni-accent); }
    </style>
</head>
<body>
    {{-- Primero de todo: el salto tiene que ganarle en orden de tabulación a la
         cabecera. El destino es el <main> de más abajo. --}}
    <x-muni::skip-link />

    <x-muni::topbar :system="$system" :subtitle="$subtitle" :status="$status">
        {{ $topbar ?? '' }}
    </x-muni::topbar>

    {{-- `tabindex="-1"` es lo que hace que el salto mueva el PUNTO DE LECTURA y no
         solo el scroll; `scroll-margin-top` evita que el topbar fijo lo tape. --}}
    <main id="muni-contenido" tabindex="-1" style="max-width:{{ $maxWidth }};margin:0 auto;padding:24px 16px 48px;scroll-margin-top:var(--muni-topbar-h);">
        {{ $slot }}
    </main>
</body>
</html>
