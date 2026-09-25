@props([
    'columns' => [], // encabezados: strings, o ['label' => , 'sort' => clave?, 'align' => ?]
    'empty' => 'Sin resultados para este filtro.', // texto cuando el slot no trae filas
    'sort' => null, // clave por la que está ordenada la tabla ahora
    'direction' => 'asc', // asc | desc, sentido del orden actual
    'sortUrl' => null, // closure fn(string $clave, string $dir): string, orden por enlace (query string)
    'wireSort' => null, // método Livewire que recibe la clave, orden sin recargar
    'caption' => null, // nombre de la tabla para el lector de pantalla
])

{{-- Tabla densa de datos. El slot son las filas <tr>; usar la clase `muni-row--danger`
     en un <tr> para pintar la franja de estado (la firma: morosidad como banda izquierda,
     no como badge redondo). Envuelta en un contenedor con scroll horizontal propio.
     Un encabezado vacío se rotula «Acciones» solo para el lector de pantalla.
     Orden en el SERVIDOR (tablas grandes y paginadas): una columna con 'sort' se vuelve
     enlace (sortUrl) o botón Livewire (wireSort) con aria-sort. Para listas cortas ya
     cargadas, sortable-table ordena en el navegador. --}}
@php
    $oculto = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;border:0;';
    $direction = $direction === 'desc' ? 'desc' : 'asc';
@endphp
<div style="position:relative;overflow-x:auto;border:1px solid var(--muni-border);border-radius:var(--muni-radius);background:var(--muni-surface);">
    <table {{ $attributes->merge(['style' => 'width:100%;border-collapse:collapse;font-family:var(--muni-font-sans);font-size:12.5px;']) }}>
        @if ($caption)<caption style="{{ $oculto }}">{{ $caption }}</caption>@endif
        @if (! empty($columns))
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        @php
                            $label = is_array($col) ? ($col['label'] ?? '') : (string) $col;
                            $clave = is_array($col) ? ($col['sort'] ?? null) : null;
                            $ordenable = $clave !== null && ($sortUrl || $wireSort);
                            $actual = $ordenable && (string) $sort === (string) $clave;
                            $siguiente = $actual && $direction === 'asc' ? 'desc' : 'asc';
                            $flecha = $actual ? ($direction === 'asc' ? '↑' : '↓') : '↕';
                        @endphp
                        <th scope="col" @if ($ordenable) aria-sort="{{ $actual ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif style="text-align:{{ is_array($col) ? ($col['align'] ?? 'left') : 'left' }};white-space:nowrap;padding:9px 12px;font-family:var(--muni-font-sans);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:{{ $actual ? 'var(--muni-text)' : 'var(--muni-muted)' }};background:var(--muni-surface-2);border-bottom:1px solid var(--muni-border);">@if (trim($label) === '')<span style="{{ $oculto }}">Acciones</span>@elseif ($ordenable && $sortUrl)<a href="{{ $sortUrl($clave, $siguiente) }}" class="muni-dt__sort">{{ $label }}<span aria-hidden="true" class="muni-dt__arrow" @style(['opacity:1' => $actual])>{{ $flecha }}</span></a>@elseif ($ordenable)<button type="button" wire:click="{{ $wireSort }}({{ \Illuminate\Support\Js::from((string) $clave) }})" class="muni-dt__sort">{{ $label }}<span aria-hidden="true" class="muni-dt__arrow" @style(['opacity:1' => $actual])>{{ $flecha }}</span></button>@else{{ $label }}@endif</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="muni-data-body">
            {{-- Livewire rodea cada @foreach/@if con comentarios de morph: un slot sin filas
                 no queda vacío. Se ignoran los comentarios para decidir si hay filas. --}}
            @if (trim(preg_replace('/<!--.*?-->/s', '', (string) $slot)) !== '')
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
        .muni-dt__sort { display:inline-flex; align-items:center; gap:5px; padding:0; border:0; background:none; font:inherit; letter-spacing:inherit; text-transform:inherit; color:inherit; text-decoration:none; cursor:pointer; border-radius:4px; }
        .muni-dt__sort:hover { color:var(--muni-text); }
        .muni-dt__sort:focus-visible { outline:none; box-shadow:var(--muni-ring); }
        .muni-dt__arrow { font-family:var(--muni-font-mono); font-size:11px; opacity:.4; }
    </style>
@endonce
