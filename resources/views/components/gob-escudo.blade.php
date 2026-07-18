@props([
    'size' => 40,
])

{{-- Escudo de la Municipalidad de Graneros en SVG (interpretación del escudo oficial:
     ave en vuelo sobre cerros, campos y sol, en escudo con borde petróleo). Se dibuja
     inline para no depender del PNG remoto ni sufrir bloqueos de CSP. --}}
<svg
    viewBox="0 0 100 106" width="{{ $size }}" height="{{ round($size * 1.06) }}"
    role="img" aria-label="Escudo de la Municipalidad de Graneros"
    {{ $attributes->merge(['style' => 'flex-shrink:0;display:block;']) }}
>
    <defs>
        <clipPath id="muni-esc-clip">
            <path d="M6 12c0-3 2-5 5-5h78c3 0 5 2 5 5v52c0 18-16 29-31 35-8 3-13 5-13 5s-5-2-13-5C22 93 6 82 6 64V12z"/>
        </clipPath>
    </defs>

    {{-- Cielo --}}
    <g clip-path="url(#muni-esc-clip)">
        <rect x="0" y="0" width="100" height="106" fill="#dff1f7"/>
        {{-- Sol --}}
        <circle cx="72" cy="26" r="11" fill="none" stroke="var(--muni-gob-oro)" stroke-width="3.4"/>
        {{-- Estrella --}}
        <path d="M45 20l1.7 4.2 4.3 1.7-4.3 1.7L45 32l-1.7-4.4-4.3-1.7 4.3-1.7z" fill="var(--muni-gob-petroleo-dark)"/>
        {{-- Cerros --}}
        <path d="M0 62c14-12 22-16 33-9s16 10 25 4 24-8 42 3v46H0z" fill="var(--muni-gob-lima)"/>
        <path d="M0 74c16-8 28-9 40-4s22 6 33 1 20-5 27-2v37H0z" fill="var(--muni-gob-verde-dark)" opacity=".55"/>
        {{-- Surcos de campo --}}
        <g stroke="#ffffff" stroke-width="2.2" opacity=".55" fill="none" stroke-linecap="round">
            <path d="M4 86c18-6 34-6 46-2s28 4 46-2"/>
            <path d="M6 94c18-6 34-6 46-2s26 4 44-2"/>
        </g>
        {{-- Ave en vuelo --}}
        <g fill="var(--muni-gob-petroleo)">
            <path d="M20 44c9-7 19-11 28-9 4 1 7 3 10 6 4 4 9 6 15 6-6 4-13 4-19 1-3-2-6-4-9-5-8-3-17 0-25 1z"/>
            <path d="M31 41c6 3 12 8 16 14 2 3 2 6 1 9-3-4-6-8-10-11-3-3-6-6-7-12z"/>
        </g>
        <path d="M48 52c5 1 9 3 12 6-4 1-9 0-12-2z" fill="var(--muni-gob-carmin)"/>
    </g>

    {{-- Borde del escudo --}}
    <path d="M6 12c0-3 2-5 5-5h78c3 0 5 2 5 5v52c0 18-16 29-31 35-8 3-13 5-13 5s-5-2-13-5C22 93 6 82 6 64V12z"
          fill="none" stroke="var(--muni-gob-petroleo)" stroke-width="5"/>
</svg>
