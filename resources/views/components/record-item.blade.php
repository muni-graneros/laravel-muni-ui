@props([
    /* La URL de la ficha del registro. Con ella el título se emite como enlace
       real: Enter nativo, «abrir en otra pestaña» y un enlace que se puede
       copiar. Sin ella el título es texto. */
    'href' => null, // URL de la ficha; con ella el título es enlace
    /* El estado del registro: ok / warn / danger / info / accent / muted. Son
       los MISMOS seis de timeline, y a propósito: el día que la línea de tiempo
       y la ficha del mismo trámite usen palabras distintas para el mismo estado,
       el funcionario ve dos verdades en una pantalla. */
    'tone' => null, // ok | warn | danger | info | accent | muted
    /* Reescribe la etiqueta textual del estado («En terreno» en vez de «Con
       observación»). El texto SIEMPRE se emite: el color de la banda no puede
       ser el único portador del estado (WCAG 2.2 AA 1.4.1). */
    'toneLabel' => null, // texto propio para el estado (siempre se emite)
    /* El identificador del registro tal como lo lee el vecino por teléfono:
       folio, número de orden, rol. Sale en `.muni-num` —mono tabular— para poder
       comparar dos folios de un vistazo, y es texto seleccionable. */
    'folio' => null, // identificador del registro, en mono tabular
    /* Qué ES ese código, para el lector de pantalla. Sin esto se anuncia
       «AV guion 2026 guion 4821» sin decir de qué se trata. */
    'folioLabel' => 'Folio', // qué es el folio, para el lector de pantalla
    /* Estampa `wire:navigate` en el enlace del título. Apagado por defecto: el
       paquete se instala también donde no hay Livewire. */
    'navegar' => false, // agrega wire:navigate al enlace del título
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
     clase la declara el bloque de estilos de `record-list` —el único padre
     válido de esta ficha, ver el comentario del final—, así que funciona también
     en el portal del vecino, donde no hay ninguna tabla en la página que la traiga.

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

{{-- ESTE COMPONENTE NO LLEVA BLOQUE DE ESTILOS, y no es un olvido: la ficha se
     renderiza DENTRO del <ul> de <x-muni::record-list>, y el modelo de contenido
     de <ul> solo admite <li> y elementos de soporte de script (script y
     template). Un <style> ahí es HTML inválido —y el Decreto N°1/2015 SEGPRES
     obliga a los sitios del Estado a los estándares del W3C—, sin que ninguna
     reja lo delate: axe salta todo hijo que el lector de pantalla no ve, y un
     <style> es display:none.

     Las clases que esta ficha usa —`.muni-rec__*`, `.muni-num` y `.muni-sr`— las
     declara el bloque de una sola emisión de <x-muni::record-list>, que es su
     único padre válido. Ahí está también el comentario que explica por qué la
     banda va como borde y no como sombra, y por qué la mono viaja con el
     componente y no se hereda de la tabla (DESIGN §7).

     Es el mismo arreglo, por el mismo motivo, que la ficha hermana
     `description-item` ya documentó para el <dl>. --}}
