@props([
    /* Las SOLAPAS, ya resueltas por el anfitrión. Forma de cada solapa, abajo.
       Sin datos no se emite el landmark —una región de navegación vacía es una
       entrada más en la lista de regiones del lector que no lleva a ninguna
       parte—, pero el componente NO revienta: el anfitrión que se olvida de
       `items` ve una ficha sin solapas, no una página caída. */
    'items' => [],
    /* La CLAVE de la página que se está mirando, resuelta por el anfitrión
       (el nombre de la ruta, la ruta URL, lo que el sistema use). El paquete
       nunca la calcula: no sabe qué es una petición. */
    'actual' => null,
    /* Nombre accesible del landmark. Si el consumidor prefiere apuntar a un
       título que ya está en la página, pasa `aria-labelledby` y este no se emite. */
    'label' => 'Secciones',
    /* Estampa `wire:navigate` en cada solapa. Apagado por defecto: el paquete se
       instala también donde no hay Livewire. Una solapa puede desactivarlo con
       'navegar' => false (una descarga, un enlace a otro sistema). */
    'navegar' => false,
])

@php
    /*
     * Visible = el anfitrión no dijo que no. El filtro es COSMÉTICO: la
     * autorización de verdad vive en la policy y en el middleware de la ruta.
     * Esconder una solapa no protege el endpoint que hay detrás.
     */
    $visible = fn (array $solapa): bool => ! array_key_exists('ver', $solapa) || (bool) $solapa['ver'];

    /*
     * Normalización. Se descarta —en vez de emitir a medias— toda solapa que no
     * sea un arreglo, que no traiga texto o que no traiga URL:
     *
     *   · un ítem que no es arreglo suele ser un modelo volcado entero, y eso no
     *     se convierte en navegación (minimización, Ley 21.719: acá solo entran
     *     cadenas ya redactadas por el anfitrión);
     *   · un enlace sin texto deja al lector leyendo la URL (WCAG 2.2 A 2.4.4);
     *   · una solapa sin href no navega a ninguna parte, y este componente existe
     *     justamente porque al elegir CAMBIA la URL.
     */
    $solapas = [];

    /*
     * A texto, o a cadena vacía. Nunca `(string) $loQueSea`: el modelo colado un
     * nivel MÁS ADENTRO —`'etiqueta' => $vecino`— lanzaba Error y tumbaba la
     * ficha entera, que es peor que una solapa de menos y además saca a la
     * página un volcado que nadie quería publicar. Mismo criterio que breadcrumb.
     */
    $texto = fn ($valor): string => is_string($valor) || is_numeric($valor)
        || (is_object($valor) && method_exists($valor, '__toString'))
            ? trim((string) $valor)
            : '';

    foreach ((is_iterable($items) ? (array) $items : []) as $item) {
        if (! is_array($item) || ! $visible($item)) {
            continue;
        }

        $etiqueta = $texto($item['etiqueta'] ?? '');
        $href = $texto($item['href'] ?? '');

        if ($etiqueta === '' || $href === '') {
            continue;
        }

        $badge = $texto($item['badge'] ?? '');

        $solapas[] = [
            'etiqueta' => $etiqueta,
            'href' => $href,
            'clave' => $texto($item['clave'] ?? ''),
            /* null = «el anfitrión no opinó»; true/false = su palabra manda. */
            'declarado' => array_key_exists('activo', $item) ? (bool) $item['activo'] : null,
            'badge' => $badge === '' ? null : $badge,
            'badgeLabel' => $texto($item['badgeLabel'] ?? ''),
            'navegar' => array_key_exists('navegar', $item) ? (bool) $item['navegar'] : (bool) $navegar,
        ];
    }

    /*
     * CUÁL ES LA SOLAPA ACTUAL. Sale UNA, nunca dos: `aria-current="page"`
     * repetido le dice al lector que la persona está en dos páginas a la vez.
     *
     * En orden:
     *   1. la que el anfitrión declaró `'activo' => true` (él resolvió y manda);
     *   2. la de clave exactamente igual a `actual`;
     *   3. la de clave MÁS LARGA que sea antepasada de `actual`. Lo de «más
     *      larga» no es un detalle: en una ficha con una solapa «vecino» y otra
     *      «vecino.documentos», estando en `vecino.documentos.7` las dos son
     *      antepasadas y la que se está mirando es la segunda.
     *
     * El descenso se reconoce en el límite del segmento y con los dos separadores
     * del ecosistema: «.» para nombres de ruta y «/» para rutas URL. A media
     * palabra NO marca: `vecino.doc` no es antepasado de `vecino.documentos`.
     * Es la misma regla que nav-menu, escrita igual a propósito: el día que la
     * solapa y el ítem del menú lateral discrepen, el funcionario ve marcadas dos
     * cosas distintas en la misma pantalla.
     */
    $clave = trim((string) $actual);
    $activa = null;

    foreach ($solapas as $i => $solapa) {
        if ($solapa['declarado'] === true) {
            $activa = $i;
            break;
        }
    }

    if ($activa === null && $clave !== '') {
        $largoAntepasado = -1;

        foreach ($solapas as $i => $solapa) {
            if ($solapa['declarado'] === false || $solapa['clave'] === '') {
                continue;
            }

            if ($solapa['clave'] === $clave) {
                $activa = $i;
                break;
            }

            $raiz = rtrim($solapa['clave'], './');

            if ($raiz !== '' && (str_starts_with($clave, $raiz.'.') || str_starts_with($clave, $raiz.'/'))
                && strlen($raiz) > $largoAntepasado) {
                $largoAntepasado = strlen($raiz);
                $activa = $i;
            }
        }
    }

    /*
     * Con `aria-labelledby` del consumidor, el nombre por defecto estorba: dos
     * nombres compitiendo en el mismo landmark. Pero se mira el VALOR, no la
     * presencia: `:aria-labelledby="$tituloFichaId"` con el id todavía en null
     * cuenta como atributo puesto (DESIGN §8: `:atributo="null"` no desaparece,
     * gana) y Blade luego no lo imprime, así que el `<nav>` se quedaba sin
     * NINGÚN nombre accesible. Nulo o en blanco = el anfitrión no nombró nada y
     * manda el nombre por defecto.
     */
    $apuntado = $texto($attributes->get('aria-labelledby'));

    $porDefecto = $apuntado !== ''
        ? ['class' => 'muni-tabr']
        : ['class' => 'muni-tabr', 'aria-label' => $label];
@endphp

{{-- SOLAPAS DE RUTA — la regla en una línea: **¿al elegir cambia la URL? entonces
     `tabs-ruta`**. Si no cambia, es `<x-muni::tabs>`.

         <x-muni::tabs-ruta
             label="Secciones del vecino"
             actual="vecino.documentos"
             navegar
             :items="[
                 ['etiqueta' => 'Solicitudes', 'href' => $urlSolicitudes, 'clave' => 'vecino.solicitudes', 'badge' => 3, 'badgeLabel' => 'solicitudes abiertas'],
                 ['etiqueta' => 'Documentos', 'href' => $urlDocumentos, 'clave' => 'vecino.documentos'],
                 ['etiqueta' => 'Historial de contactos', 'href' => $urlContactos, 'clave' => 'vecino.contactos'],
             ]" />

     POR QUÉ NO ES `tabs`. `tabs` pinta TODOS los paneles en el servidor y los
     alterna con x-show: para una ficha con cinco secciones consulta y renderiza
     las cinco —con sus adjuntos— para mostrar una, la sección elegida se pierde
     al volver de una navegación, y no hay enlace que mandarle a Jurídica para
     abrir «Documentos». Acá cada solapa es una URL: se carga solo lo que se mira,
     el enlace se comparte y el botón Atrás del navegador funciona.

     Y no es cosmética: si al seleccionar cambia la URL, ARIA **prohíbe**
     `role="tablist"`. Esto es el patrón de navegación —landmark `nav` con nombre
     accesible, lista, enlaces reales y `aria-current="page"`—, no el patrón
     `tabs` del APG. De ahí que no haya flechas, ni roving tabindex, ni
     `aria-selected`, ni una línea de Alpine: Tab recorre las solapas y Enter las
     sigue, que es lo que ya da el `<a href>` nativo. Poner flechas encima
     confundiría a quien ya sabe cómo se recorren los enlaces.

     FORMA DE UNA SOLAPA (obligatorias `etiqueta` y `href`):

       [
         'etiqueta'   => 'Documentos',                 // texto visible, ya redactado
         'href'       => $urlDocumentos,               // la URL de la sección
         'clave'      => 'vecino.documentos',          // identidad para la regla de activo
         'activo'     => true,                         // atajo: lo resolvió el anfitrión
         'badge'      => 4,                            // contador YA calculado
         'badgeLabel' => 'documentos adjuntos',        // QUÉ son cuatro (ver abajo)
         'ver'        => $puedeVer,                    // false la esconde (cosmético)
         'navegar'    => false,                        // sin wire:navigate en esta
       ]

     EL CONTADOR LLEGA CALCULADO. `badge` es un valor, nunca una consulta. Y
     `badgeLabel` arregla que el lector anuncie «Documentos 4» sin decir qué son
     cuatro: el número va aria-hidden y el texto real va recortado al lado. Sin
     `badgeLabel` no se inventa un significado y el número se lee tal cual.

     EL ANFITRIÓN RESUELVE, EL PAQUETE PINTA. Acá no hay `request()`, ni `Auth`,
     ni `Gate`: llega la clave de la página y el árbol ya filtrado. Y llegan
     cadenas ya redactadas, nunca un modelo ni un registro completo.

     EL ESTADO NO SE DICE SOLO CON COLOR (WCAG 2.2 AA 1.4.1): la solapa actual
     lleva `aria-current="page"`, la barra inferior de `--on::after` y peso
     tipográfico propio. Con `wire:navigate` el `aria-current` lo vuelve a
     calcular el servidor en cada respuesta, que es donde tiene que calcularse.

     El contenido de la sección lo pinta la ruta, no este componente. Si el
     consumidor igual pone algo en la ranura, se emite debajo de las solapas en
     vez de desaparecer en silencio. --}}
@if ($solapas !== [])
    <nav {{ $attributes->merge($porDefecto) }}>
        <ul role="list" class="muni-tabr__lista">
            @foreach ($solapas as $i => $solapa)
                <li class="muni-tabr__item">
                    <a
                        href="{{ $solapa['href'] }}"
                        class="muni-tabr__solapa{{ $i === $activa ? ' muni-tabr__solapa--on' : '' }}"
                        @if ($i === $activa) aria-current="page" @endif
                        @if ($solapa['navegar']) wire:navigate @endif
                    >
                        <span class="muni-tabr__rotulo">{{ $solapa['etiqueta'] }}</span>

                        @if ($solapa['badge'] !== null)
                            @if ($solapa['badgeLabel'] !== '')
                                {{-- El número, sin voz; el texto de al lado dice qué son cuatro. --}}
                                <span class="muni-tabr__badge" aria-hidden="true">{{ $solapa['badge'] }}</span>
                                <span class="muni-tabr__sr">{{ $solapa['badge'] }} {{ $solapa['badgeLabel'] }}</span>
                            @else
                                <span class="muni-tabr__badge">{{ $solapa['badge'] }}</span>
                            @endif
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif

{{ $slot }}

{{-- Lo que el componente necesita para verse bien viaja con el componente
     (DESIGN §7): dentro de un panel Filament `muni-ui.css` no se carga. Todo
     color sale de un token con rama clara y rama oscura; el único literal es el
     tercer respaldo del foco. --}}
@once
    <style>
        .muni-tabr { display:block;border-bottom:1px solid var(--muni-border);margin-bottom:16px; }
        {{-- La fila ENVUELVE en vez de desplazarse en horizontal: a 320px una fila
             desplazable rompe 1.4.10 y, de paso, recortaría la barra inferior de la
             solapa activa contra el borde del contenedor. --}}
        .muni-tabr__lista { display:flex;flex-wrap:wrap;align-items:stretch;gap:2px;list-style:none;margin:0;padding:0; }
        .muni-tabr__item { display:flex;min-width:0; }
        .muni-tabr__solapa { position:relative;display:inline-flex;align-items:center;gap:7px;min-width:0;
            min-height:40px;padding:9px 13px;font-family:var(--muni-font-sans);font-size:13.5px;font-weight:500;
            line-height:1.25;color:var(--muni-muted);text-decoration:none;
            border-radius:var(--muni-radius-sm) var(--muni-radius-sm) 0 0;
            {{-- Que el foco no quede tapado por la barra superior fija del armazón. --}}
            scroll-margin-top:calc(var(--muni-topbar-h) + 8px);
            transition:color var(--muni-dur) var(--muni-ease),background var(--muni-dur) var(--muni-ease); }
        .muni-tabr__solapa:hover { color:var(--muni-text);background:var(--muni-surface-2); }
        {{-- El outline es el indicador REAL: la box-shadow del anillo se pierde
             dentro de Filament (ver --muni-focus). Desplazado hacia dentro para que
             no lo recorte el borde inferior del landmark. --}}
        .muni-tabr__solapa:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-3px; box-shadow:var(--muni-ring); color:var(--muni-text); }
        {{-- Tres portadores del estado, y solo uno es el color: aria-current en el
             marcado, el peso tipográfico y la barra inferior. --}}
        .muni-tabr__solapa--on { color:var(--muni-accent);font-weight:600; }
        .muni-tabr__solapa--on::after { content:"";position:absolute;left:8px;right:8px;bottom:0;height:2px;
            background:var(--muni-accent);border-radius:2px 2px 0 0; }
        {{-- La fila envuelve, pero una etiqueta de UNA palabra larga no tiene dónde
             envolver: sin esto empuja la fila más allá del viewport a 320px y el
             1.4.10 vuelve por la puerta de atrás. --}}
        .muni-tabr__rotulo { min-width:0;overflow-wrap:anywhere; }
        .muni-tabr__badge { flex-shrink:0;font-family:var(--muni-font-mono);font-size:10.5px;font-weight:600;
            padding:1px 7px;border-radius:999px;background:var(--muni-surface-3);color:var(--muni-muted); }
        .muni-tabr__solapa--on .muni-tabr__badge { background:var(--muni-accent);color:var(--muni-on-accent); }
        .muni-tabr__sr { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0; }
        {{-- --muni-dur ya baja a 0 ms con la preferencia... en muni-ui.css. Dentro
             de un panel Filament esa hoja no se carga (DESIGN §7) y la del panel NO
             la baja: medido en Chromium y Firefox sobre panel-claro y panel-oscuro,
             la transición seguía en 160 ms con movimiento reducido. La regla propia
             lo cierra, y va con !important por lo mismo que en stat. --}}
        @media (prefers-reduced-motion:reduce) { .muni-tabr__solapa { transition:none !important; } }
        {{-- En papel las solapas no llevan a ninguna parte. --}}
        @media print { .muni-tabr { display:none !important; } }
    </style>
@endonce
