@props([
    'value' => 0,
    'max' => 100,
    'tone' => 'accent',
    'label' => null,
    'showValue' => false,
])

@php
    $pct = $max > 0 ? max(0, min(100, round($value / $max * 100))) : 0;
    $color = [
        'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)',
        'warn' => 'var(--muni-warn-fg)', 'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)',
    ][$tone] ?? 'var(--muni-accent)';
@endphp

<div {{ $attributes }}>
    @if ($label || $showValue)
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;margin-bottom:6px;">
            @if ($label)<span style="font-family:var(--muni-font-sans);font-size:12px;font-weight:600;color:var(--muni-muted);">{{ $label }}</span>@endif
            @if ($showValue)<span style="font-family:var(--muni-font-mono);font-size:12px;font-variant-numeric:tabular-nums;color:var(--muni-text);">{{ $pct }}%</span>@endif
        </div>
    @endif
    {{--
        El nombre accesible es obligatorio: sin él, un lector de pantalla
        anuncia «barra de progreso, 64 %» sin decir progreso DE QUÉ, y en una
        pantalla con varias no hay forma de distinguirlas. Se reutiliza la
        etiqueta visible para que lo que se oye y lo que se ve coincidan; si no
        se pasó ninguna, queda un nombre genérico, que sigue siendo mejor que
        ninguno. Quien necesite otro texto puede pasar aria-label.
    --}}
    <div role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
         aria-label="{{ $attributes->get('aria-label', $label ?: 'Progreso') }}"
         style="height:7px;border-radius:999px;background:var(--muni-surface-3);overflow:hidden;">
        <div class="muni-progress__relleno" style="height:100%;width:{{ $pct }}%;border-radius:999px;background:{{ $color }};transition:width var(--muni-dur-slow, 600ms) var(--muni-ease);"></div>
    </div>
</div>

{{--
    La barra recorre un trayecto: dura --muni-dur-slow (600 ms), no --muni-dur
    (160 ms), y no una duración fija, que ignoraba prefers-reduced-motion
    (DESIGN §6). La guardia local cubre el panel Filament, donde --muni-dur no
    baja, y una hoja publicada vieja sin el token, que cae en el respaldo.
--}}
@once
    <style>
        /* Con !important: la transición va en el style del relleno. */
        @media (prefers-reduced-motion:reduce) { .muni-progress__relleno { transition:none !important; } }
    </style>
@endonce
