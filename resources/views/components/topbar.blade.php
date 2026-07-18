@props([
    'system',
    'subtitle' => null,
    'status' => 'online',
    'statusLabel' => null,
    'logo' => null,
])

@php
    $statusTone = $status === 'online' ? 'ok' : ($status === 'degraded' ? 'warn' : 'danger');
    $statusText = $statusLabel ?? ['online' => 'En línea', 'degraded' => 'Degradado', 'offline' => 'Caído'][$status] ?? $status;
@endphp

<header
    {{ $attributes->merge([
        'style' => 'position:sticky;top:0;z-index:100;height:var(--muni-topbar-h);'
            .'display:flex;align-items:center;gap:12px;padding:0 16px;'
            .'background:var(--muni-surface);border-bottom:1px solid var(--muni-border);',
    ]) }}
>
    {{-- Logo sobre lienzo blanco: patrón del ecosistema (el escudo municipal necesita
         fondo claro para leerse igual en tema oscuro y claro). --}}
    <div style="height:38px;display:flex;align-items:center;justify-content:center;padding:4px 10px;background:#fff;border-radius:var(--muni-radius-sm);flex-shrink:0;">
        @if ($logo)
            {{ $logo }}
        @else
            <span style="font-family:var(--muni-font-mono);font-weight:700;font-size:13px;color:#0b0f14;letter-spacing:-.02em;">GRA</span>
        @endif
    </div>

    <div style="display:flex;flex-direction:column;line-height:1.2;min-width:0;">
        <span style="font-family:var(--muni-font-sans);font-size:13px;font-weight:700;color:var(--muni-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $system }}</span>
        @if ($subtitle)
            <span style="font-size:11px;color:var(--muni-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $subtitle }}</span>
        @endif
    </div>

    <span style="flex:1;"></span>

    <x-muni::badge :tone="$statusTone">{{ $statusText }}</x-muni::badge>

    {{ $slot }}
</header>
