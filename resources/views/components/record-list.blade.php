@props([
    /* Nombre accesible de la lista. Opcional y sin valor por defecto: una página
       con UNA sola lista no necesita nombrarla, y bautizarla por nuestra cuenta
       («Registros») le mete al lector una palabra que no está en la pantalla.
       Si el consumidor prefiere apuntar a un título que ya existe, pasa
       `aria-labelledby` y este no se emite. */
    'label' => null, // nombre accesible de la lista (opcional)
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

{{-- Lo que el par necesita para verse bien viaja con el par (DESIGN §7): dentro
     de un panel Filament `muni-ui.css` no se carga y una clase declarada solo
     ahí no existiría. Todo color sale de un token con rama clara y rama oscura;
     el único literal es el tercer respaldo del foco.

     EL BLOQUE VIVE AQUÍ Y NO EN LA FICHA, y eso no es comodidad: la ficha se
     renderiza DENTRO de este <ul>, cuyo modelo de contenido solo admite <li> y
     elementos de soporte de script (script y template). Un <style> entre las
     fichas es HTML inválido (Decreto N°1/2015 SEGPRES) y no lo caza ninguna
     reja, porque axe salta los hijos invisibles y un <style> es display:none.
     Mismo arreglo, y mismo motivo, que `description-list` con su <dl>.

     Se emite fuera del `@if` de arriba a propósito: si la primera lista de la
     página viene vacía, el bloque igual sale y la siguiente lista con fichas lo
     encuentra puesto. --}}
@once
    <style>
        .muni-rec { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px;
            font-family:var(--muni-font-sans); }
        .muni-rec__item { display:flex; flex-wrap:wrap; align-items:flex-start; gap:12px;
            padding:12px 14px; background:var(--muni-surface);
            border:1px solid var(--muni-border);
            {{-- La banda de estado va como BORDE y no como `box-shadow: inset`
                 —que es como la pinta data-table— porque esto se imprime: las
                 sombras y los fondos no salen por impresora y los bordes sí. Es
                 la misma conversión que data-table tuvo que escribir dentro de
                 su bloque de impresión, hecha desde el principio.
                 SIN TONO el respaldo es `--muni-border`, el mismo color del
                 resto del recuadro: la ficha queda alineada con las demás (que
                 es el motivo del ancho de reserva) y además CIERRA. Con
                 `transparent` los otros tres lados llevaban línea de 1px y el
                 cuarto ninguna: la tarjeta salía abierta por la izquierda. --}}
            border-left:3px solid var(--mrec-banda, var(--muni-border));
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
