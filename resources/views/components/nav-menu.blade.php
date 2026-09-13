@props([
    /* El ÁRBOL, ya filtrado por el anfitrión. Obligatorio: sin datos no hay menú
       y un <nav> vacío es ruido para el lector de pantalla. Forma de cada nodo en
       el comentario de abajo. */
    'items',
    /* La CLAVE de la página que se está mirando, resuelta por el anfitrión
       (`request()->route()->getName()`, `request()->path()`, lo que el sistema
       use). El paquete nunca la calcula. */
    'actual' => null,
    'label' => 'Navegación principal',
    /* Uso interno de la recursión: el nivel y el prefijo de ids. Un consumidor no
       los pasa nunca. */
    'nivel' => 0,
    'prefijo' => null,
])

@php
    /*
     * LA REGLA DE «ACTIVO», ESCRITA UNA SOLA VEZ.
     *
     * Es el punto entero del componente: hoy está copiada ítem por ítem en seis
     * sistemas y ya diverge (routeIs / request()->is() / la URL entera), así que
     * el ítem marcado y las migas no siempre coinciden.
     *
     * Un nodo está activo si:
     *   1. trae `activo` declarado — el anfitrión resolvió él mismo y manda; o
     *   2. su `clave` es exactamente `actual`; o
     *   3. `actual` DESCIENDE de su clave. El descenso se reconoce en un límite
     *      de segmento y con los dos separadores del ecosistema: «.» para nombres
     *      de ruta (licencias.solicitudes → licencias.solicitudes.42) y «/» para
     *      rutas URL (/camaras → /camaras/7/editar). A media palabra NO marca:
     *      `licencias.an` no es antepasado de `licencias.anular`.
     *
     * Nada acá consulta `request()`, `Auth` ni `Gate`. No es purismo: un
     * componente de diseño que resuelve permisos por su cuenta invita a
     * «proteger» un endpoint escondiéndolo del menú, y eso no protege nada.
     */
    $nodoActivo = function (array $nodo) use ($actual): bool {
        if (array_key_exists('activo', $nodo)) {
            return (bool) $nodo['activo'];
        }

        $clave = (string) ($nodo['clave'] ?? '');

        if ($clave === '' || $actual === null || $actual === '') {
            return false;
        }

        if ($actual === $clave) {
            return true;
        }

        $raiz = rtrim($clave, './');

        return str_starts_with($actual, $raiz.'.') || str_starts_with($actual, $raiz.'/');
    };

    /* Visible = el anfitrión no dijo que no. El filtro es COSMÉTICO: la
       autorización de verdad sigue en la policy y en el middleware de la ruta. */
    $visible = fn (array $nodo): bool => ! array_key_exists('ver', $nodo) || (bool) $nodo['ver'];

    /* ¿Hay una página activa dentro de este subárbol? Se calcula en PHP, en el
       servidor, porque de eso depende que un grupo llegue abierto en la primera
       pintura. */
    $llevaActivo = function (array $nodos) use (&$llevaActivo, $nodoActivo, $visible): bool {
        foreach ($nodos as $nodo) {
            if (! is_array($nodo) || ! $visible($nodo)) {
                continue;
            }

            if ($nodoActivo($nodo)) {
                return true;
            }

            if (! empty($nodo['items']) && is_array($nodo['items']) && $llevaActivo($nodo['items'])) {
                return true;
            }
        }

        return false;
    };

    $tipoDe = function (array $nodo): string {
        if (! empty($nodo['tipo'])) {
            return (string) $nodo['tipo'];
        }

        return ! empty($nodo['items']) && is_array($nodo['items']) ? 'grupo' : 'enlace';
    };

    /* La raíz de los ids. Del atributo `id` del consumidor si lo hay, y si no del
       rótulo del landmark: determinista, sin uniqid(), y distinta para dos menús
       distintos en la misma página. */
    $base = $prefijo ?: ($attributes->get('id') ?: 'muni-navm-'.substr(sha1((string) $label), 0, 6));

    $nodos = array_values(array_filter(
        is_iterable($items) ? (array) $items : [],
        fn ($nodo) => is_array($nodo) && $visible($nodo)
    ));
@endphp

{{-- El menú lateral completo, pintado desde un árbol de configuración.

     QUÉ RESUELVE. Hoy cada sistema escribe a mano sus quince `<x-muni::nav-item>`
     con un `@@can` alrededor de cada uno y la regla de «activo» repetida ítem por
     ítem. Acá el árbol se declara una vez, el filtro por permiso lo trae ya
     resuelto el anfitrión y la regla de activo está escrita en un solo lugar.

     EL CONTRATO, QUE ES LO IMPORTANTE. El paquete NO consulta `Gate`, `Auth` ni
     `request()`. Recibe:

       · `items`  — el árbol YA FILTRADO. Cada nodo puede además traer `ver` como
                    booleano ya evaluado por el host (`'ver' => $u->can('...')`).
                    El filtro del menú es COSMÉTICO: la autorización vive en la
                    policy y en el middleware de la ruta. Esconder un ítem no
                    protege el endpoint, y quien crea lo contrario deja un agujero.
       · `actual` — la clave de la página que se está mirando, resuelta por el
                    host. El paquete no sabe qué es una petición.

     FORMA DE UN NODO (todo opcional salvo `etiqueta`):

       [
         'etiqueta'   => 'Solicitudes',              // texto visible
         'href'       => route('licencias.index'),   // enlace
         'clave'      => 'licencias.solicitudes',    // identidad para la regla de activo
         'activo'     => true,                       // atajo: lo resolvió el host
         'icono'      => '<svg …>',                  // SVG en crudo, como nav-item
         'badge'      => 12,                         // contador YA calculado
         'badgeLabel' => 'solicitudes esperando',    // QUÉ son doce (ver abajo)
         'ver'        => $puedeVer,                  // false lo esconde
         'tipo'       => 'titulo' | 'grupo' | 'enlace',
         'abierto'    => true,                       // fuerza el grupo abierto
         'items'      => [ … ],                      // hijos: implica 'grupo'
       ]

     EL CONTADOR LLEGA CALCULADO. `badge` es un valor, nunca una consulta: si el
     componente la resolviera, serían quince consultas por página. Se calcula una
     vez, cacheado en Redis si pesa, y se pasa. Y `badgeLabel` es lo que arregla
     que el lector anuncie «Solicitudes 12» sin decir qué son doce: el número va
     `aria-hidden` y el texto real va en el sr-only.

     LA SECCIÓN ES UN GRUPO DE VERDAD. `nav-section` pinta el rótulo como un
     `<div>` suelto: sin nombre, la sección no existe para el lector. Acá el
     rótulo tiene id y la lista de la sección lo referencia con `aria-labelledby`.
     El `<ul>` conserva su rol de lista a propósito: ponerle `role="group"` deja a
     sus `<li>` sin padre válido (axe: `aria-required-parent`).

     NO ES EL PATRÓN `menu`. Es navegación: landmark `nav` + listas + enlaces. Sin
     flechas, sin roving tabindex, sin `role="menuitem"`. Tab entre enlaces, Enter
     para seguirlos, y Enter o Espacio en el disparador de un grupo.

     TEXTO DEL ANFITRIÓN, NUNCA DENTRO DE UNA EXPRESIÓN DE ALPINE: acá no hay una
     sola. Lo único que Alpine toca es el booleano de `nav-grupo`. --}}
@if ($nivel === 0)
<nav {{ $attributes->merge(['class' => 'muni-navm', 'aria-label' => $label, 'id' => $base]) }}>
    <ul class="muni-navm__lista">
@endif

@foreach ($nodos as $i => $nodo)
    @php
        $tipo = $tipoDe($nodo);
        $etiqueta = (string) ($nodo['etiqueta'] ?? '');
        $hijos = ! empty($nodo['items']) && is_array($nodo['items']) ? $nodo['items'] : [];
        $idNodo = $base.'-'.$i;
    @endphp

    @if ($tipo === 'titulo')
        <li class="muni-navm__seccion">
            <span class="muni-navm__titulo" id="{{ $idNodo }}-t">{{ $etiqueta }}</span>
            @if ($hijos)
                <ul class="muni-navm__sub" aria-labelledby="{{ $idNodo }}-t">
                    <x-muni::nav-menu :items="$hijos" :actual="$actual" :nivel="$nivel + 1" :prefijo="$idNodo" />
                </ul>
            @endif
        </li>
    @elseif ($tipo === 'grupo')
        <li>
            <x-muni::nav-grupo
                :titulo="$etiqueta"
                :icono="$nodo['icono'] ?? null"
                :abierto="($nodo['abierto'] ?? false) || $llevaActivo($hijos)"
                :id="$idNodo"
            >
                <ul class="muni-navm__sub muni-navm__sub--grupo">
                    <x-muni::nav-menu :items="$hijos" :actual="$actual" :nivel="$nivel + 1" :prefijo="$idNodo" />
                </ul>
            </x-muni::nav-grupo>
        </li>
    @else
        <li>
            <x-muni::nav-item
                :href="$nodo['href'] ?? '#'"
                :icon="$nodo['icono'] ?? null"
                :active="$nodoActivo($nodo)"
            >
                <span class="muni-navm__fila">
                    <span class="muni-navm__rotulo">{{ $etiqueta }}</span>
                    @if (isset($nodo['badge']) && $nodo['badge'] !== null && $nodo['badge'] !== '')
                        @if (! empty($nodo['badgeLabel']))
                            {{-- El número, sin voz; el texto de al lado dice qué son doce. --}}
                            <span class="muni-navm__badge" aria-hidden="true">{{ $nodo['badge'] }}</span>
                            <span class="muni-navm__sr">{{ $nodo['badge'] }} {{ $nodo['badgeLabel'] }}</span>
                        @else
                            {{-- Sin `badgeLabel` no se inventa un significado: el lector
                                 anuncia el número tal cual, como hace hoy nav-item. --}}
                            <span class="muni-navm__badge">{{ $nodo['badge'] }}</span>
                        @endif
                    @endif
                </span>
            </x-muni::nav-item>
        </li>
    @endif
@endforeach

@if ($nivel === 0)
    </ul>
</nav>
@endif

@once
    <style>
        .muni-navm { display:block; }
        .muni-navm__lista, .muni-navm__sub { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:2px; }
        .muni-navm__seccion { margin:14px 0 4px; display:block; }
        .muni-navm__seccion:first-child { margin-top:0; }
        .muni-navm__titulo { display:block; padding:0 11px 6px; font-family:var(--muni-font-mono); font-size:10px; font-weight:600;
            letter-spacing:.08em; text-transform:uppercase; color:var(--muni-hint); }
        {{-- La sangría del grupo se marca también con una guía vertical: el color
             no puede ser el único portador de la jerarquía. --}}
        .muni-navm__sub--grupo { margin:2px 0 2px 18px; padding-left:10px; border-left:1px solid var(--muni-border); }
        .muni-navm__fila { display:flex; align-items:center; gap:8px; min-width:0; }
        .muni-navm__rotulo { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .muni-navm__badge { margin-left:auto; flex-shrink:0; font-family:var(--muni-font-mono); font-size:10.5px; font-weight:600;
            padding:1px 7px; border-radius:999px; background:var(--muni-surface-3); color:var(--muni-muted); }
        .muni-nav-item--active .muni-navm__badge { background:var(--muni-accent); color:var(--muni-on-accent); }
        {{-- El foco visible lo pone nav-item en el <a>; esta regla es para el caso
             en que el consumidor enfoque la lista entera con un tabindex propio,
             y para no dejar el landmark sin indicador dentro de Filament, donde la
             box-shadow del anillo se pierde (ver --muni-focus). --}}
        .muni-navm:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-3px; }
        .muni-navm__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
    </style>
@endonce
