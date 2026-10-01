@props([
    'height' => '5px', // alto de la franja (CSS)
])

{{-- Franja institucional de la Municipalidad de Graneros: la barra de 7 colores del
     sitio madre (municipalidadgraneros.cl). Es la firma visual compartida por todos
     los subdominios del ecosistema y va en TODO diseño, claro y oscuro: los armazones
     (topbar, dashboard-shell, auth-shell, error-page) y la hoja imprimible la ponen solos.

     Se dibuja con siete bordes superiores y no con un degradado de fondo: el
     navegador imprime los bordes aunque el diálogo tenga desactivados los gráficos
     de fondo, así la franja sale también en papel sin forzar print-color-adjust
     (prohibido en este paquete, ver muni-ui.css). --}}
<div
    role="presentation"
    aria-hidden="true"
    {{ $attributes->merge([
        'class' => 'muni-gob-stripe',
        'style' => "--franja-alto:{$height};",
    ]) }}
><i style="border-top-color:var(--muni-gob-lima)"></i><i style="border-top-color:var(--muni-gob-petroleo)"></i><i style="border-top-color:var(--muni-gob-oro)"></i><i style="border-top-color:var(--muni-gob-naranja)"></i><i style="border-top-color:var(--muni-gob-celeste)"></i><i style="border-top-color:var(--muni-gob-carmin)"></i><i style="border-top-color:var(--muni-gob-gris)"></i></div>

@once
    <style>
        .muni-gob-stripe { display: flex; width: 100%; height: var(--franja-alto, 5px); flex-shrink: 0; }
        .muni-gob-stripe > i { flex: 1; border-top: var(--franja-alto, 5px) solid; }
    </style>
@endonce
