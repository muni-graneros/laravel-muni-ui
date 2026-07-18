@props([
    'size' => 40,
    'src' => null,
])

@php
    // Escudo OFICIAL de la Municipalidad de Graneros (PNG del sitio institucional).
    // Se publica con `php artisan vendor:publish --tag=muni-ui-images` y queda en
    // public/vendor/muni-ui/. `src` permite apuntar a otra ruta (CDN, R2, etc.).
    $url = $src ?? asset('vendor/muni-ui/logo-graneros.png');
@endphp

<img
    src="{{ $url }}"
    width="{{ $size }}"
    height="{{ $size }}"
    alt="Escudo de la Municipalidad de Graneros"
    {{ $attributes->merge(['style' => 'flex-shrink:0;display:block;object-fit:contain;']) }}
>
