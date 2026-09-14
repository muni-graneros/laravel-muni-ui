@props([
    /* La URL de la ficha del registro. Con ella el título se emite como enlace
       real: Enter nativo, «abrir en otra pestaña» y un enlace que se puede
       copiar. Sin ella el título es texto. */
    'href' => null,
    /* El estado del registro: ok / warn / danger / info / accent / muted. Son
       los MISMOS seis de timeline, y a propósito: el día que la línea de tiempo
       y la ficha del mismo trámite usen palabras distintas para el mismo estado,
       el funcionario ve dos verdades en una pantalla. */
    'tone' => null,
    /* Reescribe la etiqueta textual del estado («En terreno» en vez de «Con
       observación»). El texto SIEMPRE se emite: el color de la banda no puede
       ser el único portador del estado (WCAG 2.2 AA 1.4.1). */
    'toneLabel' => null,
    /* El identificador del registro tal como lo lee el vecino por teléfono:
       folio, número de orden, rol. Sale en `.muni-num` —mono tabular— para poder
       comparar dos folios de un vistazo, y es texto seleccionable. */
    'folio' => null,
    /* Qué ES ese código, para el lector de pantalla. Sin esto se anuncia
       «AV guion 2026 guion 4821» sin decir de qué se trata. */
    'folioLabel' => 'Folio',
    /* Estampa `wire:navigate` en el enlace del título. Apagado por defecto: el
       paquete se instala también donde no hay Livewire. */
    'navegar' => false,
])

@php
    /* El estado en palabras, por tono. Copiado de timeline a propósito (ver la
       prop `tone`), no derivado: derivarlo ataría un componente al otro. */
    $etiquetasDeTono = [
        'ok' => 'Conforme',
        'warn' => 'Con observación',
        'danger' => 'Rechazado',
        'info' => 'Informativo',
        'accent' => 'En curso',
        'muted' => 'Sin efecto',
    ];

    $coloresDeTono = [
        'ok' => 'var(--muni-ok-fg)',
        'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)',
        'info' => 'var(--muni-info-fg)',
        'accent' => 'var(--muni-accent)',
        'muted' => 'var(--muni-border-2)',
    ];

    /*
     * A texto, o a cadena vacía. Nunca `(string) $loQueSea`: el modelo colado en
     * una prop —`:folio="$solicitud"`— lanzaba Error y tumbaba la página del
     * vecino entera, y el volcado de la excepción publica justo lo que la
     * minimización (Ley 21.719) manda no publicar. Mismo criterio que tabs-ruta.
     */
    $texto = fn ($valor): string => is_string($valor) || is_numeric($valor)
        || (is_object($valor) && method_exists($valor, '__toString'))
            ? trim((string) $valor)
            : '';

    $tono = $texto($tone);

    /*
     * Una errata NO cae en silencio. `tone="rechazado"` —la etiqueta en vez del
     * tono— dejaría la ficha sin estado o con otro, y el vecino leería «En
     * curso» sobre una solicitud rechazada. Es el precedente de `densidad` en
     * data-table, y acá pesa más: ahí se equivocaba el alto de una fila, acá el
     * estado de un expediente.
     */
    if ($tono !== '' && ! array_key_exists($tono, $coloresDeTono)) {
        throw new InvalidArgumentException(
            "El tono «{$tono}» no existe: solo ok, warn, danger, info, accent o muted."
        );
    }

    $etiquetaDeTono = $texto($toneLabel) !== ''
        ? $texto($toneLabel)
        : ($tono !== '' ? $etiquetasDeTono[$tono] : '');

    $bandaDeTono = $tono !== '' ? $coloresDeTono[$tono] : '';

    $numeroDeFolio = $texto($folio);
    $rotuloDeFolio = $texto($folioLabel);

    $destino = $texto($href);
    $hayTitulo = isset($title) && trim((string) $title) !== '';

    /*
     * Sin título no hay enlace. Un `<a>` cuyo único contenido fuera el folio deja
     * al lector con «enlace, AV guion 2026…» o, peor, leyendo la URL (WCAG 2.2 A
     * 2.4.4). Y sin href tampoco hay ancla: un `<a>` sin destino no es una
     * parada de teclado y promete una navegación que no existe.
     */
    $esEnlace = $destino !== '' && $hayTitulo;
@endphp

{{-- UNA FICHA DE LA LISTA. Va dentro de `<x-muni::record-list>`, que es la que
     pone `role="list"`.

     LA API ES POR RANURAS, nunca un array de configuración («campos»,
     «acciones», «tono», «media»): eso termina siendo un mini motor de plantillas
     que ninguno de los seis sistemas usa como viene, y cada uno acaba
     escribiendo el marcado igual por la ranura. Acá el consumidor manda en su
     marcado y el componente aporta solo la firma del sistema: la banda de estado
     al borde izquierdo, el folio en `.muni-num`, el área táctil de terreno y una
     zona propia para las acciones.

       <x-muni::record-item tone="warn" folio="AV-2026-4821" href="{{ $url }}">
           <x-slot:title>Poda de árbol en Manuel Rodríguez 545</x-slot:title>
           <x-slot:meta>Ingresada el 3 de septiembre · Aseo y Ornato</x-slot:meta>
           <x-slot:actions>…</x-slot:actions>
           Cuerpo libre, si hace falta.
       </x-muni::record-item>

     EL ANFITRIÓN RESUELVE, EL PAQUETE PINTA. Acá no hay `request()`, ni `Auth`,
     ni `Gate`: llegan cadenas YA redactadas. En discapacidad el registro trae
     diagnósticos y en licencias, psicotécnicos; un componente que aceptara el
     modelo entero rompería la minimización de la Ley 21.719 él solo.

     EL RUT Y LAS CIFRAS DE LA RANURA `meta` van en `<span class="muni-num">`: la
     clase la declara este componente en su propio bloque de estilos, así que
     funciona también en el portal del vecino, donde no hay ninguna tabla en la
     página que la traiga.

     NO HAY «TARJETA ENTERA PULSABLE». El truco de estirar el enlace con un
     `::after` que tapa la ficha impide seleccionar el texto que hay debajo, y
     poder copiar el folio es media razón de ser de este componente. Se pulsa el
     título, que es un enlace de verdad, o los controles de `actions`.

     SI LA ACCIÓN ES UN BOTÓN (con su Space nativo), lo pone el consumidor en
     `actions`: el componente no emite botones propios ni una línea de Alpine.

     ÁREA TÁCTIL DE 44 px, no de 24: esto se usa de pie en la calle y con
     guantes. Es más de lo que exige 2.5.8 y es deliberado. --}}
<li {{ $attributes->merge($bandaDeTono !== ''
    ? ['class' => 'muni-rec__item', 'style' => '--mrec-banda:'.$bandaDeTono.';']
    : ['class' => 'muni-rec__item']) }}>
    <div class="muni-rec__cuerpo">
        @if ($etiquetaDeTono !== '' || $numeroDeFolio !== '')
            <div class="muni-rec__cabeza">
                @if ($etiquetaDeTono !== '')<span class="muni-rec__tono">{{ $etiquetaDeTono }}</span>@endif
                @if ($numeroDeFolio !== '')
                    <span class="muni-rec__folio muni-num">@if ($rotuloDeFolio !== '')<span class="muni-rec__sr">{{ $rotuloDeFolio }} </span>@endif{{ $numeroDeFolio }}</span>
                @endif
            </div>
        @endif

        @if ($hayTitulo)
            @if ($esEnlace)
                <a class="muni-rec__titulo" href="{{ $destino }}" @if ($navegar) wire:navigate @endif>{{ $title }}</a>
            @else
                <span class="muni-rec__titulo">{{ $title }}</span>
            @endif
        @endif

        @isset($meta)<div class="muni-rec__meta">{{ $meta }}</div>@endisset
        @if (trim((string) $slot) !== '')<div class="muni-rec__extra">{{ $slot }}</div>@endif
    </div>

    @isset($actions)<div class="muni-rec__acciones">{{ $actions }}</div>@endisset
</li>

{{-- Lo que el componente necesita para verse bien viaja con el componente
     (DESIGN §7): dentro de un panel Filament `muni-ui.css` no se carga y una
     clase declarada solo ahí no existiría. Todo color sale de un token con rama
     clara y rama oscura; el único literal es el tercer respaldo del foco. --}}
@once
    <style>
        .muni-rec__item { display:flex; flex-wrap:wrap; align-items:flex-start; gap:12px;
            padding:12px 14px; background:var(--muni-surface);
            border:1px solid var(--muni-border);
            {{-- La banda de estado va como BORDE y no como `box-shadow: inset`
                 —que es como la pinta data-table— porque esto se imprime: las
                 sombras y los fondos no salen por impresora y los bordes sí. Es
                 la misma conversión que data-table tuvo que escribir dentro de
                 su bloque de impresión, hecha desde el principio.
                 Transparente cuando no hay tono declarado, para que las fichas
                 con y sin estado sigan alineadas al mismo margen. --}}
            border-left:3px solid var(--mrec-banda, transparent);
            border-radius:var(--muni-radius);
            box-shadow:var(--muni-shadow);
            transition:box-shadow var(--muni-dur) var(--muni-ease),background var(--muni-dur) var(--muni-ease); }
        .muni-rec__item:hover { background:var(--muni-surface-2); box-shadow:var(--muni-shadow-md); }
        {{-- 16rem de base: la ficha ocupa una columna y las acciones bajan solas
             cuando no caben, sin una sola consulta de medios ni de contenedor. --}}
        .muni-rec__cuerpo { flex:1 1 16rem; min-width:0; }
        .muni-rec__cabeza { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
        {{-- El estado en palabras, con los mismos tokens que timeline: --muni-muted
             sobre --muni-surface-3, un par ya medido en las cuatro paletas. Un
             color de texto por tono obligaría a auditar seis pares nuevos en dos
             temas para no ganar nada, porque la información ya la lleva la palabra. --}}
        .muni-rec__tono { flex-shrink:0; padding:1px 7px; border-radius:999px;
            font-family:var(--muni-font-mono); font-size:10.5px; font-weight:600; line-height:1.5;
            letter-spacing:.02em; color:var(--muni-muted); background:var(--muni-surface-3);
            border:1px solid var(--muni-border); }
        .muni-rec__folio { font-size:11.5px; color:var(--muni-hint); overflow-wrap:anywhere; }
        .muni-rec__titulo { display:block; margin:3px 0 0; font-size:14px; font-weight:600;
            line-height:1.35; color:var(--muni-text); overflow-wrap:anywhere; }
        {{-- El área táctil de TERRENO, 44px, y solo sobre lo que se pulsa: un
             título que no es enlace no es un objetivo y estirarlo a 44px sería
             aire muerto en una lista que vive de la densidad. --}}
        a.muni-rec__titulo { display:inline-flex; align-items:center; min-height:44px; min-width:44px;
            color:var(--muni-accent-strong); text-decoration:underline; text-underline-offset:3px; }
        a.muni-rec__titulo:hover { color:var(--muni-accent); }
        {{-- El outline es el indicador REAL: dentro de Filament la cadena de
             sombras de Tailwind se come cualquier box-shadow (DESIGN §5). --}}
        .muni-rec__titulo:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676));
            outline-offset:2px; border-radius:var(--muni-radius-sm);
            {{-- Que el foco no quede tapado por la barra superior fija del armazón. --}}
            scroll-margin-top:calc(var(--muni-topbar-h) + 8px); }
        .muni-rec__meta { margin:3px 0 0; font-size:12.5px; line-height:1.5; color:var(--muni-muted);
            overflow-wrap:anywhere; }
        .muni-rec__extra { margin:6px 0 0; font-size:12.5px; line-height:1.5; color:var(--muni-muted); }
        .muni-rec__acciones { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-left:auto; }
        {{-- El piso del objetivo táctil, para el botón de solo icono que el
             consumidor meta acá: 44×44 en terreno, no 24×24. --}}
        .muni-rec__acciones a, .muni-rec__acciones button { display:inline-flex; align-items:center;
            justify-content:center; min-height:44px; min-width:44px; }
        .muni-rec__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden;
            clip-path:inset(50%); white-space:nowrap; border:0; }
        {{-- `.muni-num` vive hoy SOLO dentro del bloque de estilos de data-table,
             y este componente existe justamente para las pantallas donde no hay
             ninguna tabla: sin esta declaración el folio saldría en sans
             proporcional, que es el defecto que el componente promete evitar.
             Misma definición, byte a byte, que la de data-table. --}}
        .muni-num { font-family: var(--muni-font-mono); font-variant-numeric: tabular-nums; }
        {{-- --muni-dur ya baja a 0 ms con la preferencia... en muni-ui.css. Dentro
             de un panel Filament esa hoja no se carga (DESIGN §7) y la del panel
             NO la baja: medido en tabs-ruta, la transición seguía en 160 ms con
             movimiento reducido. La regla propia lo cierra. --}}
        @media (prefers-reduced-motion:reduce) { .muni-rec__item { transition:none !important; } }
        {{-- En papel: la ficha no se parte entre dos hojas —el folio en una y el
             estado en la otra— y los controles no se imprimen, porque un botón
             sobre papel no hace nada. El estado no se pierde: es texto. --}}
        @media print {
            .muni-rec__item { break-inside:avoid; box-shadow:none; }
            .muni-rec__acciones { display:none; }
        }
    </style>
@endonce
