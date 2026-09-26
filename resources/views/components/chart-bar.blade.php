@props([
    'data' => [], // ['label'=>, 'value'=>, 'tone'=>?] o números
    'height' => 160, // alto del área de barras en px
    'tone' => 'accent', // accent | ok | warn | danger | info
    'labels' => true, // muestra las etiquetas bajo las barras
])

@php
    // $data: array de ['label'=>, 'value'=>, 'tone'=>?] o de números.
    // Sin 'label' o 'value' no truena: faltantes valen '' y 0. Un negativo se dibuja
    // en 0 —un alto no puede ser menor— pero la cifra se muestra tal cual.
    $norm = [];
    foreach ($data ?? [] as $d) {
        $d = is_array($d) ? $d : ['value' => $d];
        $d['label'] = $d['label'] ?? '';
        $d['value'] = $d['value'] ?? 0;
        $d['alto'] = max(0, (float) $d['value']);
        $norm[] = $d;
    }
    $max = 0;
    foreach ($norm as $d) { $max = max($max, $d['alto']); }
    $max = $max ?: 1;
    $toneColor = [
        'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)', 'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)',
    ];
@endphp

{{-- El style va DENTRO de merge(): escrito aparte, el style del consumidor salía
     como un segundo atributo y el navegador descartaba uno de los dos. --}}
<div {{ $attributes->merge(['class' => 'muni-chartbar', 'style' => 'display:flex;flex-direction:column;']) }}>
    <div style="display:flex;align-items:flex-end;gap:8px;height:{{ $height }}px;padding-top:8px;">
        @foreach ($norm as $d)
            @php
                $h = round($d['alto'] / $max * 100, 1);
                $c = $toneColor[$d['tone'] ?? $tone] ?? 'var(--muni-accent)';
            @endphp
            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;min-width:0;" title="{{ $d['label'] }}: {{ $d['value'] }}">
                <span class="muni-chartbar__val">{{ $d['value'] }}</span>
                <div class="muni-chartbar__bar" style="height:{{ $h }}%;background:linear-gradient(180deg,{{ $c }},color-mix(in srgb,{{ $c }} 55%,transparent));"></div>
            </div>
        @endforeach
    </div>
    @if ($labels)
        <div style="display:flex;gap:8px;margin-top:8px;">
            @foreach ($norm as $d)
                <span style="flex:1;text-align:center;font-family:var(--muni-font-sans);font-size:10.5px;color:var(--muni-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;">{{ $d['label'] }}</span>
            @endforeach
        </div>
    @endif
</div>

@once
    <style>
        /* La barra crece un trayecto: --muni-dur-slow, con respaldo para una
           hoja publicada vieja. La guardia de abajo cubre el panel Filament,
           donde --muni-dur no baja con movimiento reducido. */
        .muni-chartbar__bar { width:100%; max-width:38px; border-radius:5px 5px 0 0; transition:height var(--muni-dur-slow, 600ms) var(--muni-ease); }
        @media (prefers-reduced-motion:reduce) { .muni-chartbar__bar { transition:none !important; } }
        .muni-chartbar__val { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; font-size:11px; font-weight:600; color:var(--muni-muted); margin-bottom:5px; }
    </style>
@endonce
