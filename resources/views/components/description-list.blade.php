@props([
    'items' => [], // mapa etiqueta => valor, o lista de ['label' => , 'value' => , 'mono' => ?]
    'columns' => 1, // 1 | 2 | 3 columnas en pantallas anchas (una sola en móvil)
    'empty' => '—', // texto para valores vacíos
])

{{-- Ficha de datos etiqueta: valor (persona, patente, solicitud) como <dl> real, que el
     lector de pantalla anuncia en pares. `mono` alinea RUT, folios y montos. Con el slot
     se pueden escribir los <dt>/<dd> a mano. --}}
@php
    $filas = [];
    foreach ($items as $k => $v) {
        $filas[] = is_array($v) ? $v + ['label' => $k, 'value' => null] : ['label' => $k, 'value' => $v];
    }
    $cols = max(1, min(3, (int) $columns));
@endphp

<dl {{ $attributes->merge(['class' => 'muni-dl', 'style' => '--dl-cols:'.$cols.';']) }}>
    @forelse ($filas as $f)
        <div class="muni-dl__row">
            <dt>{{ $f['label'] }}</dt>
            <dd @class(['muni-num' => ! empty($f['mono'])])>{{ ($f['value'] ?? '') === '' ? $empty : $f['value'] }}</dd>
        </div>
    @empty
        {{ $slot }}
    @endforelse
</dl>

@once
    <style>
        .muni-dl { margin:0; display:grid; grid-template-columns:1fr; gap:0 28px; font-family:var(--muni-font-sans); }
        @media (min-width:640px) { .muni-dl { grid-template-columns:repeat(var(--dl-cols),minmax(0,1fr)); } }
        .muni-dl__row { display:grid; grid-template-columns:minmax(110px,38%) 1fr; gap:12px; padding:9px 0; border-bottom:1px solid var(--muni-border); }
        .muni-dl dt { font-size:12.5px; color:var(--muni-muted); }
        .muni-dl dd { margin:0; font-size:13.5px; color:var(--muni-text); overflow-wrap:anywhere; }
        .muni-dl dd.muni-num { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; }
    </style>
@endonce
