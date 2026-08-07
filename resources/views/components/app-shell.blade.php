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
    <title>    <x-muni::reverb-meta />
{{ $title ?? $system }}</title>
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
