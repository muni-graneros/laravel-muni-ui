@props([
    'theme' => 'light', // light | dark
    'title' => null, // <title> del documento; por defecto usa system
    'system', // nombre del sistema en la topbar
    'subtitle' => null, // línea secundaria bajo el nombre
    'status' => 'online', // online | degraded | offline
    'maxWidth' => '1200px', // ancho máximo del contenido
])

<!DOCTYPE html>
<html lang="es" data-muni-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    <x-muni::topbar :system="$system" :subtitle="$subtitle" :status="$status">
        {{ $topbar ?? '' }}
    </x-muni::topbar>

    <main style="max-width:{{ $maxWidth }};margin:0 auto;padding:24px 16px 48px;">
        {{ $slot }}
    </main>
</body>
</html>
