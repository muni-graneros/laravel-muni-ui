@props([
    /* Nombre accesible de la lista. Opcional y sin valor por defecto: una página
       con UNA sola lista no necesita nombrarla, y bautizarla por nuestra cuenta
       («Registros») le mete al lector una palabra que no está en la pantalla.
       Si el consumidor prefiere apuntar a un título que ya existe, pasa
       `aria-labelledby` y este no se emite. */
    'label' => null,
])

@php
    /*
     * A texto, o a cadena vacía. Nunca `(string) $loQueSea`: un modelo colado en
     * una prop —`:label="$vecino"`— lanzaría Error y tumbaría la página entera,
     * que es peor que una lista sin nombre y además saca a la pantalla un
     * volcado que nadie quería publicar. Mismo criterio que tabs-ruta.
     */
    $texto = fn ($valor): string => is_string($valor) || is_numeric($valor)
        || (is_object($valor) && method_exists($valor, '__toString'))
            ? trim((string) $valor)
            : '';

    /*
     * DESIGN §8: `:atributo="null"` no desaparece, GANA. El caso natural del
     * consumidor es `:aria-labelledby="$tituloBandejaId"` con el id todavía en
     * null; mirando la PRESENCIA y no el VALOR, la lista se callaría su
     * `aria-label` y Blade luego no imprimiría el atributo nulo: quedaría sin
     * NINGÚN nombre. Y el vacío se saca de la bolsa, porque un
     * `aria-labelledby=""` al lado de un `aria-label` no nombra nada por la
     * norma pero no todos los lectores lo resuelven igual.
     */
    $apuntado = $texto($attributes->get('aria-labelledby'));

    if ($apuntado === '') {
        /* Se reasigna `$attributes` y no otra variable: el derrame solo se
           compila si la expresión empieza literalmente por `$attributes`. */
        $attributes = $attributes->except('aria-labelledby');
    }

    $nombre = $texto($label);

    $porDefecto = ['role' => 'list', 'class' => 'muni-rec'];

    if ($apuntado === '' && $nombre !== '') {
        $porDefecto['aria-label'] = $nombre;
    }

    /* Sin fichas no se emite la lista: ver el comentario de abajo. */
    $hayFichas = trim((string) $slot) !== '';
@endphp

{{-- LOS MISMOS REGISTROS COMO LISTA DE FICHAS, cuando la tabla no cabe: el
     teléfono del vecino en el portal de Atención al Vecino y la tablet del
     inspector en terreno.

         <x-muni::record-list label="Mis solicitudes">
             <x-muni::record-item tone="warn" folio="AV-2026-4821" href="{{ $url }}">
                 <x-slot:title>Poda de árbol en Manuel Rodríguez 545</x-slot:title>
                 <x-slot:meta>Ingresada el 3 de septiembre · Aseo y Ornato</x-slot:meta>
                 <x-slot:actions><x-muni::button variant="ghost" href="{{ $url }}">Ver detalle</x-muni::button></x-slot:actions>
             </x-muni::record-item>
         </x-muni::record-list>

     NO ES EL «MODO MÓVIL» DE `data-table`. La elección la hace el SERVIDOR por
     contexto de página: el portal público y la ruta de terreno renderizan
     siempre lista; el back-office de escritorio renderiza siempre tabla. Por eso
     acá no hay doble DOM, ni una tabla escondida con `aria-hidden`, ni container
     queries: «una sola marca que cambia de tabla a lista según el ancho» no
     existe en CSS —si la marca es `<ul><li>` ningún CSS le pone encabezados de
     columna, y si es `<table>` convertirla en lista exige el `display:block` que
     destruye la relación fila/columna para el lector.

     Y por eso tampoco se copia el truco de `::before { content: attr(data-label) }`
     que usa medio catálogo para «responsivizar» tablas: además de romper esa
     relación, la etiqueta generada NO es texto seleccionable, y el vecino tiene
     que poder copiar su número de folio para pegarlo en un correo.

     `role="list"` EN EL CONTENEDOR, no `role="listitem"` en cada hijo: el
     defecto de WebKit/VoiceOver —que le quita la semántica de lista a toda lista
     con `list-style:none`— lo dispara el contenedor y ahí se corrige. Sin eso el
     vecino pierde el «lista de 3 elementos» que le dice cuántas solicitudes
     tiene antes de recorrerlas.

     SIN FICHAS NO SE EMITE NADA. Un `<ul>` vacío se anuncia «lista, 0 elementos»
     y deja al vecino sin saber si no tiene solicitudes o si la página se rompió.
     El «no hay nada» lo dice `<x-muni::empty-state>`, que además distingue «no
     hay datos» de «el filtro no encontró nada». --}}
@if ($hayFichas)
    <ul {{ $attributes->merge($porDefecto) }}>{{ $slot }}</ul>
@endif

{{-- Lo que el componente necesita para verse bien viaja con el componente
     (DESIGN §7): dentro de un panel Filament `muni-ui.css` no se carga. --}}
@once
    <style>
        .muni-rec { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px;
            font-family:var(--muni-font-sans); }
    </style>
@endonce
