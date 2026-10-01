@props([
    'legend' => null, // leyenda del <fieldset>; sin ella no hay fieldset
    'description' => null, // texto explicativo bajo la leyenda
    'columns' => 2, // 1 | 2 columnas en escritorio
])

{{-- Una sección de `<x-muni::formulario-tramite>`: un grupo de campos con su rótulo
     en un <legend> y una rejilla de dos columnas para escritorio ancho, que es donde
     vive el mesón.

     Tres decisiones, y ninguna es de estilo:

     1. SIN `legend` NO HAY <fieldset>. Un <fieldset> sin <legend> es un grupo
        anunciado como «grupo» y nada más: el lector de pantalla lo nombra con la
        cadena vacía y el funcionario oye una pausa en vez de un título. Peor que no
        agrupar. Sin rótulo esta sección es un <div> y se comporta igual visualmente.
     2. LA REJILLA VA EN UN <div> DENTRO del <fieldset>, no en el <fieldset>. El
        <fieldset> es el elemento con el historial más largo de peleas con
        `display:grid` y `display:flex` en los motores (el `min-width:min-content`
        que le impone el UA y el renderizador propio de su caja): una rejilla puesta
        ahí se desborda en horizontal en cuanto una celda trae una tabla o un
        <input> ancho, y 1.4.10 no admite desplazamiento horizontal del documento.
        El <div> interior no arrastra nada de eso.
     3. LA DESCRIPCIÓN SE ENLAZA con `aria-describedby` al propio <fieldset>, así
        que se oye AL ENTRAR al grupo y no después de tabular por tres campos. El
        id sale del rótulo, nunca de uniqid() (DESIGN §10): con uniqid() cambia en
        cada render, rompe el enlace y ensucia el diffing de Livewire.

     Un campo que necesite el ancho completo de la rejilla —una dirección, un textarea,
     una zona de adjuntos— lleva `class="muni-fsec__ancho"`. --}}

@php
    $columnas = (int) $columns === 1 ? 1 : 2;

    /*
     * El id sale del rótulo y de la descripción, nunca de uniqid() ni de un contador
     * de render: los dos cambian entre pinturas y romperían el `aria-describedby` y el
     * diffing de Livewire. Dos secciones con EL MISMO rótulo y la misma descripción en
     * la misma página comparten id a propósito —son la misma sección dos veces— y para
     * eso el `id` del consumidor manda siempre.
     */
    $secId = trim((string) $attributes->get('id'));

    if ($secId === '') {
        $secId = 'muni-fsec-'.substr(sha1(implode('|', [
            (string) $legend,
            (string) $description,
            (string) $columnas,
        ])), 0, 8);
    }

    /*
     * El `aria-describedby` se ENCADENA al del consumidor, no lo reemplaza:
     * `$attributes->merge()` reemplaza en todo lo que no sea class ni style
     * (DESIGN §8), así que el valor de la bolsa se lee, se une a mano y se saca
     * de la bolsa antes de fusionar. Se reasigna `$attributes` y no otra
     * variable porque el derrame solo se compila si la expresión empieza
     * literalmente por `$attributes`.
     */
    $descrito = implode(' ', array_filter([
        trim((string) $attributes->get('aria-describedby')),
        $description ? $secId.'-desc' : '',
    ]));

    $attributes = $attributes->except(['id', 'aria-describedby']);

    $extra = $descrito !== '' ? ['aria-describedby' => $descrito] : [];
@endphp

@if ($legend)
    <fieldset id="{{ $secId }}" {{ $attributes->merge($extra + ['class' => 'muni-fsec']) }}>
        <legend class="muni-fsec__legend">{{ $legend }}</legend>
        @if ($description)
            <p id="{{ $secId }}-desc" class="muni-fsec__desc">{{ $description }}</p>
        @endif
        <div class="muni-fsec__grid" data-muni-cols="{{ $columnas }}">{{ $slot }}</div>
    </fieldset>
@else
    <div id="{{ $secId }}" {{ $attributes->merge($extra + ['class' => 'muni-fsec muni-fsec--sin-rotulo']) }}>
        @if ($description)
            <p id="{{ $secId }}-desc" class="muni-fsec__desc">{{ $description }}</p>
        @endif
        <div class="muni-fsec__grid" data-muni-cols="{{ $columnas }}">{{ $slot }}</div>
    </div>
@endif

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css, y una clase declarada únicamente en
           muni-ui.css se vería sin estilo y sin un solo error en consola
           (DESIGN §7). */
        .muni-fsec { margin:0; padding:16px 18px 18px; border:1px solid var(--muni-border);
            border-radius:var(--muni-radius); background:var(--muni-surface);
            font-family:var(--muni-font-sans); color:var(--muni-text); min-width:0; }
        .muni-fsec--sin-rotulo { padding-top:18px; }
        /* El rótulo del grupo es lo primero que se lee al entrar: pesa como un
           título, no como una etiqueta de campo. `padding` a los lados para que la
           línea del borde no lo toque. */
        .muni-fsec__legend { padding:0 6px; margin-left:-6px; font-size:13.5px; font-weight:700;
            letter-spacing:-.01em; color:var(--muni-text); }
        .muni-fsec__desc { margin:6px 0 0; font-size:12px; line-height:1.5; color:var(--muni-muted); max-width:70ch; }
        .muni-fsec__desc + .muni-fsec__grid { margin-top:14px; }
        .muni-fsec__grid { display:grid; gap:14px 18px; margin-top:12px; min-width:0; }
        .muni-fsec__grid > * { min-width:0; }
        .muni-fsec__grid[data-muni-cols="1"] { grid-template-columns:minmax(0,1fr); }
        .muni-fsec__grid[data-muni-cols="2"] { grid-template-columns:repeat(2,minmax(0,1fr)); }
        /* Un campo que necesita la fila entera: dirección, textarea, adjuntos. */
        .muni-fsec__grid > .muni-fsec__ancho { grid-column:1 / -1; }
        /* Densidad de escritorio arriba del quiebre y una sola columna abajo: en un
           teléfono dos columnas de 160 px no son dos columnas, son dos campos
           ilegibles. */
        @media (max-width:860px) {
            .muni-fsec__grid[data-muni-cols="2"] { grid-template-columns:minmax(0,1fr); }
            .muni-fsec { padding:14px 14px 16px; }
        }
    </style>
@endonce
