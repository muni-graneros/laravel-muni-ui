@props([
    'columns' => [], // encabezados de columna
    'empty' => 'Sin resultados para este filtro.', // texto cuando el slot no trae filas
])

{{-- Tabla densa de datos. El slot son las filas <tr>; usar la clase `muni-row--danger`
     en un <tr> para pintar la franja de estado (la firma: morosidad como banda izquierda,
     no como badge redondo). Envuelta en un contenedor con scroll horizontal propio.
     Un encabezado vacío se rotula «Acciones» solo para el lector de pantalla. --}}
<div style="position:relative;overflow-x:auto;border:1px solid var(--muni-border);border-radius:var(--muni-radius);background:var(--muni-surface);">
    <table {{ $attributes->merge(['style' => 'width:100%;border-collapse:collapse;font-family:var(--muni-font-sans);font-size:12.5px;']) }}>
        @if (! empty($columns))
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th scope="col" style="text-align:left;white-space:nowrap;padding:9px 12px;font-family:var(--muni-font-sans);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muni-muted);background:var(--muni-surface-2);border-bottom:1px solid var(--muni-border);">@if (trim((string) $col) !== ''){{ $col }}@else<span style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;border:0;">Acciones</span>@endif</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="muni-data-body">
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
