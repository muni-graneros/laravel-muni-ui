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
    <title>{{ $title ?? $system }}</title>
    {{ $head ?? '' }}
    <style>
        *,*::before,*::after{ box-sizing:border-box; }
        body{ margin:0; min-height:100vh; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); display:flex; }
        .muni-ds__col{ flex:1; min-width:0; display:flex; flex-direction:column; }
        .muni-ds__top{ position:sticky; top:0; z-index:40; height:var(--muni-topbar-h); display:flex; align-items:center; gap:12px; padding:0 18px; background:var(--muni-surface); border-bottom:1px solid var(--muni-border); }
        .muni-ds__burger{ display:none; padding:6px; border:none; background:transparent; color:var(--muni-text); cursor:pointer; border-radius:var(--muni-radius-sm); }
        @media (max-width:899px){ .muni-ds__burger{ display:inline-flex; } }
        .muni-ds__main{ flex:1; padding:24px; max-width:1280px; width:100%; margin:0 auto; }
        .muni-ds__scrim{ display:none; position:fixed; inset:0; z-index:140; background:rgba(10,14,20,.5); }
        @media (max-width:899px){ .muni-ds__scrim.on{ display:block; } }
    </style>
</head>
<body>
    {{ $sidebar ?? '' }}

    <div class="muni-ds__col">
        <header class="muni-ds__top">
            <button class="muni-ds__burger" @click="window.dispatchEvent(new CustomEvent('muni-sidebar'))" aria-label="Menú">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20"><path d="M3 6h14M3 10h14M3 14h14" stroke-linecap="round"/></svg>
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
        </header>

        <main class="muni-ds__main">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
