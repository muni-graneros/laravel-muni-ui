@props([
    'items' => [], // ['label'=>, 'url'=>?] o texto; la última es la actual
])

{{-- MIGAS DE RUTA (WCAG 2.2 AA · patrón «breadcrumb» de la APG).

     `items`: cada miga es `['label' => string, 'url' => ?string]`, o el texto
     suelto cuando no lleva enlace. La ÚLTIMA es la página actual: nunca se emite
     como enlace aunque traiga `url`, y es la que lleva `aria-current="page"`.

     La miga puede venir como arreglo, como cualquier `ArrayAccess` (una
     Collection, por ejemplo) o como un objeto `Arrayable`: todos se leen igual.
     Lo que no traiga un `label` legible se descarta, y si NINGUNA miga queda en
     pie el componente no emite nada —ni el landmark vacío—, así que una lista
     mal armada se nota como ausencia total de la ruta, no como una ruta a
     medias. En papel las migas no se imprimen.

     La banda «título + ruta» no se arma a mano en cada pantalla: `page-header`
     tiene un slot llamado `migas` que rinde esto ENCIMA de su único `<h1>`.

         :items="[
             ['label' => 'Inicio', 'url' => route('panel')],
             ['label' => 'Credenciales', 'url' => route('credenciales.index')],
             ['label' => 'Solicitud 2026-0412'],
         ]"

     (La etiqueta completa del componente NO aparece en este comentario a
     propósito: `scripts/gen-registry.py` busca las directivas de Alpine con una
     expresión que barre el archivo entero, y el prefijo del paquete la engaña.
     Un ejemplo de uso dentro del comentario deja anotado en `registry.json` que
     el componente necesita Alpine, y es Blade puro.)

     Tres cosas que este componente hace y que no son decorativas:

       1. ES UNA LISTA. `<nav>` + `<ol>` + `<li>`, para que el lector anuncie la
          estructura y la posición («lista de 3, elemento 2»). El `role="list"`
          del `<ol>` no sobra: Safari con VoiceOver le quita la semántica de
          lista a cualquier lista con `list-style:none`, y sin ella volvemos al
          texto suelto que se venía emitiendo.
       2. EL ENLACE VA SUBRAYADO. Si el único diferenciador entre una miga
          enlazada y la actual fuera el color, sería 1.4.1 (uso del color).
       3. UNA MIGA SIN TEXTO SE DESCARTA. Antes `$item['label']` lanzaba
          «undefined array key» y tumbaba la página entera por una miga mal
          armada; emitir el enlace igual, sin nombre accesible, tampoco es
          salida (2.4.4): el lector leería la URL.

     El nombre de la navegación («Ruta») es el valor por defecto y viaja DENTRO
     de `merge`, así que el `aria-label` del consumidor lo reemplaza en vez de
     duplicarse. Si el consumidor prefiere `aria-labelledby`, el nombre por
     defecto no se emite: dos nombres compitiendo en el mismo landmark. --}}

@php
    /*
     * Normalización. Se rearma la lista con claves consecutivas a propósito: la
     * condición del separador miraba la CLAVE del arreglo ($i > 0), así que un
     * $items asociativo o filtrado con array_filter dibujaba un chevrón suelto
     * delante de la primera miga («inicio» > 0 se compara como cadena y da
     * verdadero en PHP 8).
     */
    $migas = [];

    /*
     * Lee una clave de la miga venga como arreglo o como ArrayAccess, y devuelve
     * cadena vacía para todo lo que no sepa convertirse en texto (un arreglo
     * anidado, un modelo). Se escribe con isset() y no con el operador de
     * coalescencia a propósito: `scripts/gen-registry.py` toma una variable
     * seguida de ese operador por un slot con nombre y lo anota en el registro.
     */
    $leerClave = function ($fuente, string $clave): string {
        $valor = isset($fuente[$clave]) ? $fuente[$clave] : '';

        if (is_string($valor) || is_numeric($valor) || (is_object($valor) && method_exists($valor, '__toString'))) {
            return trim((string) $valor);
        }

        return '';
    };

    foreach ($items as $miga) {
        if (is_string($miga) || is_numeric($miga)) {
            $miga = ['label' => (string) $miga];
        }

        if ($miga instanceof \Illuminate\Contracts\Support\Arrayable) {
            $miga = $miga->toArray();
        }

        /*
         * Se acepta cualquier ArrayAccess (una Collection por miga, por
         * ejemplo): la versión anterior leía $item['label'] a secas y eso
         * funcionaba. Estrecharlo a is_array() descartaba esas migas en
         * silencio, y como sin migas ya no se emite el <nav>, el anfitrión
         * perdía la navegación entera sin un aviso en consola (DESIGN §8).
         */
        if (! is_array($miga) && ! $miga instanceof \ArrayAccess) {
            continue;
        }

        $texto = $leerClave($miga, 'label');

        if ($texto === '') {
            continue;
        }

        $migas[] = [
            'label' => $texto,
            'url' => $leerClave($miga, 'url'),
        ];
    }

    /* Con `aria-labelledby` del consumidor, el nombre por defecto estorba. */
    $porDefecto = $attributes->has('aria-labelledby')
        ? ['class' => 'muni-crumbs']
        : ['class' => 'muni-crumbs', 'aria-label' => 'Ruta'];
@endphp

{{-- Sin migas no se emite el landmark: una región de navegación vacía es una
     entrada más en la lista de regiones del lector que no lleva a ninguna parte. --}}
@if ($migas !== [])
    <nav {{ $attributes->merge($porDefecto) }}>
        <ol role="list" class="muni-crumbs__list">
            @foreach ($migas as $miga)
                <li class="muni-crumbs__item">
                    @if (! $loop->first)
                        <span class="muni-crumbs__sep" aria-hidden="true">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" width="13" height="13"><path d="M6 4l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    @endif

                    @if ($miga['url'] !== '' && ! $loop->last)
                        <a href="{{ $miga['url'] }}" class="muni-crumb">{{ $miga['label'] }}</a>
                    @elseif ($loop->last)
                        <span class="muni-crumb muni-crumb--actual" aria-current="page">{{ $miga['label'] }}</span>
                    @else
                        <span class="muni-crumb">{{ $miga['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif

{{-- Lo que el componente necesita para verse bien viaja con el componente
     (DESIGN §7): dentro de un panel Filament `muni-ui.css` no se carga. Todo
     color sale de tokens con rama clara y rama oscura; el único literal es el
     tercer respaldo del foco. --}}
@once
    <style>
        .muni-crumbs { font-family:var(--muni-font-sans);font-size:12.5px;line-height:1.2; }
        .muni-crumbs__list { display:flex;align-items:center;flex-wrap:wrap;gap:2px 7px;list-style:none;margin:0;padding:0; }
        .muni-crumbs__item { display:flex;align-items:center;gap:7px;min-width:0; }
        .muni-crumbs__sep { display:inline-flex;align-items:center;color:var(--muni-hint);flex-shrink:0; }
        .muni-crumb { display:inline-flex;align-items:center;min-height:24px;padding:0 2px;border-radius:var(--muni-radius-sm);color:var(--muni-muted);
            min-width:0;overflow-wrap:anywhere; }
        .muni-crumb--actual { color:var(--muni-text);font-weight:600; }
        a.muni-crumb { text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px;
            transition:color var(--muni-dur) var(--muni-ease),background var(--muni-dur) var(--muni-ease); }
        a.muni-crumb:hover { color:var(--muni-text);background:var(--muni-surface-2);text-decoration-thickness:2px; }
        a.muni-crumb:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); color:var(--muni-text); }
        @media (prefers-reduced-motion:reduce) { a.muni-crumb { transition:none !important; } }
        @media print { .muni-crumbs { display:none !important; } }
    </style>
@endonce
