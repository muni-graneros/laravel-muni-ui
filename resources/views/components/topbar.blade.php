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
        'class' => 'muni-topbar',
        'style' => 'position:sticky;top:0;z-index:100;height:var(--muni-topbar-h);'
            .'display:flex;align-items:center;gap:12px;padding:0 16px;'
            .'background:var(--muni-surface);border-bottom:1px solid var(--muni-border);',
    ]) }}
>
    {{-- Placa del escudo: el PNG tiene el contorno en petróleo casi negro y sobre
         la superficie oscura se pierde, así que va sobre placa clara. La placa es
         un token con dos ramas (blanca en claro, gris apagado en oscuro) y no el
         recuadro blanco fijo de antes, que encandilaba en una sala a oscuras. El
         respaldo es otro token con dos ramas, para el sistema que todavía tiene
         publicada la hoja anterior. --}}
    <div style="height:38px;display:flex;align-items:center;justify-content:center;padding:4px 10px;background:var(--muni-logo-plate, var(--muni-surface));border-radius:var(--muni-radius-sm);flex-shrink:0;">
        @if ($logo)
            {{ $logo }}
        @else
            <span style="font-family:var(--muni-font-mono);font-weight:700;font-size:13px;color:var(--muni-logo-plate-fg, var(--muni-text));letter-spacing:-.02em;">GRA</span>
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
