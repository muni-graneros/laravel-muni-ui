@props([
    'height' => '5px',
])

{{-- Franja institucional de la Municipalidad de Graneros: la barra de 7 colores del
     sitio madre (municipalidadgraneros.cl), reproducida en CSS con topes duros.
     Es la firma visual compartida por todos los subdominios del ecosistema. --}}
<div
    role="presentation"
    aria-hidden="true"
    {{ $attributes->merge([
        'class' => 'muni-gob-stripe',
        'style' => "height:{$height};",
    ]) }}
></div>

@once
    <style>
        .muni-gob-stripe {
            width: 100%;
            background: linear-gradient(90deg,
                var(--muni-gob-lima)    0 14.28%,
                var(--muni-gob-petroleo) 14.28% 28.57%,
                var(--muni-gob-oro)      28.57% 42.85%,
                var(--muni-gob-naranja)  42.85% 57.14%,
                var(--muni-gob-celeste)  57.14% 71.42%,
                var(--muni-gob-carmin)   71.42% 85.71%,
                var(--muni-gob-gris)     85.71% 100%
            );
        }
    </style>
@endonce
