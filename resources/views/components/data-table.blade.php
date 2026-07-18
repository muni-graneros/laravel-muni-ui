@props([
    'columns' => [],
    'empty' => 'Sin resultados para este filtro.',
])

{{-- Tabla densa de datos. El slot son las filas <tr>; usar la clase `muni-row--danger`
     en un <tr> para pintar la franja de estado (la firma: morosidad como banda izquierda,
     no como badge redondo). Envuelta en un contenedor con scroll horizontal propio. --}}
<div style="overflow-x:auto;border:1px solid var(--muni-border);border-radius:var(--muni-radius);background:var(--muni-surface);">
    <table {{ $attributes->merge(['style' => 'width:100%;border-collapse:collapse;font-family:var(--muni-font-sans);font-size:12.5px;']) }}>
        @if (! empty($columns))
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th style="text-align:left;white-space:nowrap;padding:9px 12px;font-family:var(--muni-font-sans);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muni-muted);background:var(--muni-surface-2);border-bottom:1px solid var(--muni-border);">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            @if (trim($slot) !== '')
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}" style="text-align:center;padding:28px 12px;color:var(--muni-muted);">{{ $empty }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

{{-- Estilos de fila: aplican a los <tr>/<td> que el consumidor pone en el slot. --}}
@once
    <style>
        .muni-data-body td, [data-muni-row] td { padding: 8px 12px; border-bottom: 1px solid var(--muni-border); white-space: nowrap; color: var(--muni-text); }
        [data-muni-row]:hover { background: var(--muni-surface-2); }
        [data-muni-row].muni-row--danger { position: relative; }
        [data-muni-row].muni-row--danger td:first-child { box-shadow: inset 3px 0 0 var(--muni-danger-fg); }
        [data-muni-row].muni-row--danger td:first-child { color: var(--muni-danger-fg); font-weight: 600; }
        .muni-num { font-family: var(--muni-font-mono); font-variant-numeric: tabular-nums; }
    </style>
@endonce
