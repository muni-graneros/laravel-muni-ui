@props([
    'current' => 1,
    'total' => 1,
    /*
     * Constructor de URL: `fn (int $pagina) => string`. Si no es invocable, el
     * componente NO degrada a «#» en silencio: revienta. Un «#» activable salta al
     * principio del documento, pierde la posición de desplazamiento y no lleva a
     * ninguna página, que es el defecto más caro de este componente.
     */
    'url' => null,
    /* Texto del rango. Con `paginator` se arma solo; a mano sigue mandando. */
    'info' => null,
    /*
     * Aditivo: un LengthAwarePaginator de `paginate()` completa página actual,
     * última página, rango y enlaces. Las props sueltas siguen funcionando igual.
     */
    'paginator' => null,
])

@php
    $current = (int) $current;
    $total = max((int) $total, 1);
    $link = null;

    if ($paginator !== null) {
        /*
         * `simplePaginate()` no cuenta los registros a propósito: no sabe cuántas
         * páginas hay. Dibujar «página 2 de 2» con él sería inventarle un final al
         * padrón y dejar al funcionario convencido de que ya lo vio entero.
         */
        if (! $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            throw new InvalidArgumentException(
                'Este paginador viene de simplePaginate() y no conoce el total de páginas: '.
                'la última página que se dibujara sería inventada. Usa paginate() o pasa '.
                '`current` y `total` a mano.'
            );
        }

        $current = (int) $paginator->currentPage();
        $total = max((int) $paginator->lastPage(), 1);
        $link = fn (int $p) => $paginator->url($p);

        if ($info === null) {
            $numero = fn ($n) => number_format((int) $n, 0, ',', '.');

            $info = $paginator->total() > 0
                ? 'Mostrando '.$numero($paginator->firstItem()).'–'.$numero($paginator->lastItem())
                    .' de '.$numero($paginator->total())
                : 'Sin registros para este filtro.';
        }
    }

    if ($url !== null) {
        if (! is_callable($url)) {
            throw new InvalidArgumentException(
                'La prop `url` de <x-muni::pagination> tiene que ser invocable, '.
                'fn ($pagina) => "?page=".$pagina. Degradar a «#» en silencio dejaría '.
                'enlaces que saltan al principio del documento sin cambiar de página.'
            );
        }

        $link = $url;
    }

    /* Ventana de páginas: 1 … (actual-1, actual, actual+1) … total */
    $pages = collect(range(1, $total))
        ->filter(fn ($p) => $p === 1 || $p === $total || abs($p - $current) <= 1)
        ->values();

    $prevOk = $link !== null && $current > 1;
    $nextOk = $link !== null && $current < $total;

    /*
     * La posición va en el NOMBRE de la navegación y no en una región viva: con
     * enlace de carga completa el navegador ya anuncia el documento nuevo, y con
     * navegación en sitio el nodo se reemplaza entero, así que una región viva
     * recién insertada no dispara nunca (WCAG 2.2 AA 4.1.3).
     */
    $navLabel = "Paginación, página {$current} de {$total}";
@endphp

<nav {{ $attributes->merge([
    'aria-label' => $navLabel,
    'style' => 'display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:16px;font-family:var(--muni-font-sans);font-size:13px;',
]) }}>
    {{-- Un extremo inerte NO es un enlace. `pointer-events:none` bloquea el ratón y
         deja pasar el teclado: el enlace seguía en el orden de tabulación y Enter lo
         activaba. Un <span aria-disabled="true"> sale del orden de tabulación sin
         ayuda, y NVDA y JAWS lo siguen leyendo en modo exploración. --}}
    @if ($prevOk)
        <a href="{{ $link($current - 1) }}" class="muni-page muni-page--nav">‹ Anterior</a>
    @else
        <span class="muni-page muni-page--nav muni-page--off" aria-disabled="true">‹ Anterior</span>
    @endif

    @php $prev = 0; @endphp
    @foreach ($pages as $p)
        @if ($p - $prev > 1)<span class="muni-page__gap" aria-hidden="true">…</span>@endif
        @if ($p === $current)
            {{-- La página actual no se distingue solo por el color de fondo: lleva
                 aria-current y el número en negrita (DESIGN §10). --}}
            <span class="muni-page muni-page--current" aria-current="page">{{ $p }}</span>
        @elseif ($link !== null)
            <a href="{{ $link($p) }}" class="muni-page">{{ $p }}</a>
        @else
            <span class="muni-page">{{ $p }}</span>
        @endif
        @php $prev = $p; @endphp
    @endforeach

    @if ($nextOk)
        <a href="{{ $link($current + 1) }}" class="muni-page muni-page--nav">Siguiente ›</a>
    @else
        <span class="muni-page muni-page--nav muni-page--off" aria-disabled="true">Siguiente ›</span>
    @endif

    @if (filled($info))<span class="muni-page__info">{{ $info }}</span>@endif
</nav>

{{-- Lo que el componente necesita para verse bien viaja con el componente (DESIGN §7):
     dentro de un panel Filament `muni-ui.css` no se carga. --}}
@once
    <style>
        .muni-page { display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 9px;
            border-radius:var(--muni-radius-sm);border:1px solid transparent;color:var(--muni-muted);text-decoration:none;
            font-variant-numeric:tabular-nums;transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-page:hover { background:var(--muni-surface-2);color:var(--muni-text); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-page:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-page--current { background:var(--muni-accent);color:var(--muni-on-accent);font-weight:600; }
        .muni-page--nav { color:var(--muni-text);font-weight:500; }
        /* El extremo apagado se dibuja con un TOKEN de color, no bajando la opacidad:
           atenuar arrastra el texto y el propio anillo de foco por debajo del mínimo
           de contraste, y no tiene contraparte oscura medible (DESIGN §4 y §5).
           --muni-hint es el único token con las dos ramas para esto. */
        .muni-page--off { color:var(--muni-hint);cursor:default; }
        /* Sin realce al pasar el ratón: lo apagado no reacciona. */
        .muni-page--off:hover { background:transparent;color:var(--muni-hint); }
        .muni-page__gap { color:var(--muni-hint);padding:0 2px; }
        .muni-page__info { margin-left:auto;color:var(--muni-muted);font-size:12px; }
    </style>
@endonce
