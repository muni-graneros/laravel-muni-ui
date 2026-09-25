@props([
    'value' => 0, // valor actual
    'max' => 100, // valor que equivale al 100 %
    'size' => 96, // diámetro en px
    'tone' => 'accent', // accent | ok | warn | danger | info
    'label' => null, // rótulo bajo el anillo y nombre accesible
    'showValue' => true, // muestra el porcentaje al centro
])

@php
    $pct = $max > 0 ? max(0, min(100, $value / $max * 100)) : 0;
    $pctRedondo = (int) round($pct);
    $r = 42;
    $circ = 2 * M_PI * $r;
    $offset = $circ * (1 - $pct / 100);
    $color = [
        'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)', 'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)',
    ][$tone] ?? 'var(--muni-accent)';

    // El nombre accesible reutiliza el rótulo visible, con «Progreso» de
    // respaldo, y un aria-label del consumidor gana. Uno vacío no cuenta: dejaría
    // al progressbar sin nombre. aria-label y aria-labelledby se sacan de la
    // bolsa y van al elemento con el rol, no a la raíz, un div sin rol.
    $nombrePropio = trim((string) $attributes->get('aria-label', ''));
    $nombre = $nombrePropio !== '' ? $nombrePropio : ((string) $label !== '' ? (string) $label : 'Progreso');
    $etiquetadoPor = trim((string) $attributes->get('aria-labelledby', ''));
    $rotuloEsElNombre = $etiquetadoPor === '' && (string) $label !== '' && (string) $label === $nombre;
@endphp

{{--
    El rol va en el contenedor del arco y NO en la raíz, y la geometría no es la
    de progress: acá la cifra vive DENTRO del elemento con el rol. Por eso el svg
    y la cifra central van aria-hidden —si no, el lector anuncia «Cupos
    ocupados, barra de progreso, 78 %» y después lee «78%» otra vez—, y el
    rótulo de abajo también, cuando es el mismo texto del nombre (WCAG 4.1.2 y
    1.1.1). aria-valuemax es 100 fijo y aria-valuenow el porcentaje ya acotado:
    un máximo igual al valor anuncia 100 % donde la pantalla muestra 30 %.

    Sin región viva a propósito: si el anillo se refresca por polling de
    Livewire, anunciar el valor cada dos segundos satura al lector. Se lee
    cuando el usuario llega a él.

    La duración sale de --muni-dur-slow (600 ms), no de --muni-dur (160 ms), que
    haría saltar el arco. El respaldo de 600 ms cubre un sistema con la hoja
    publicada vieja, y para ese caso el bloque de estilos apaga la transición
    con movimiento reducido por su cuenta.
--}}
<div {{ $attributes->except(['aria-label', 'aria-labelledby'])->merge(['class' => 'muni-ring', 'style' => 'display:inline-flex;flex-direction:column;align-items:center;gap:8px;']) }}>
    <div role="progressbar" aria-valuenow="{{ $pctRedondo }}" aria-valuemin="0" aria-valuemax="100"
         aria-valuetext="{{ $pctRedondo }} %" aria-label="{{ $nombre }}" @if ($etiquetadoPor !== '') aria-labelledby="{{ $etiquetadoPor }}" @endif
         style="position:relative;width:{{ $size }}px;height:{{ $size }}px;">
        <svg aria-hidden="true" focusable="false" viewBox="0 0 100 100" style="width:100%;height:100%;transform:rotate(-90deg);">
            <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="var(--muni-surface-3)" stroke-width="8"/>
            <circle class="muni-ring__arco" cx="50" cy="50" r="{{ $r }}" fill="none" stroke="{{ $color }}" stroke-width="8" stroke-linecap="round"
                    stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}"
                    style="transition:stroke-dashoffset var(--muni-dur-slow, 600ms) var(--muni-ease);"/>
        </svg>
        @if ($showValue)
            <div aria-hidden="true" style="position:absolute;inset:0;display:grid;place-items:center;">
                <span style="font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;font-size:{{ round($size / 4.5) }}px;font-weight:700;color:{{ $color }};">{{ $pctRedondo }}<span style="font-size:.5em;">%</span></span>
            </div>
        @endif
    </div>
    @if ($label)<span @if ($rotuloEsElNombre) aria-hidden="true" @endif style="font-family:var(--muni-font-sans);font-size:12px;font-weight:600;color:var(--muni-muted);text-align:center;">{{ $label }}</span>@endif
</div>

@once
    <style>
        /* Guardia local de movimiento reducido. La hoja del panel y una hoja
           publicada vieja pueden no bajar la duración a 0 ms; el arco se apaga
           igual. Con !important porque la transición va en el style del
           círculo y una regla de clase sola pierde contra él. */
        @media (prefers-reduced-motion:reduce) { .muni-ring__arco { transition:none !important; } }
    </style>
@endonce
