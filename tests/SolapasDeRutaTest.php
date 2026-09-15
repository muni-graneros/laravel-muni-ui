<?php

use Illuminate\Support\Facades\Blade;

/*
 * SOLAPAS QUE SON NAVEGACIÓN, NO PESTAÑAS.
 *
 * La regla en una línea: **¿al elegir cambia la URL? entonces `tabs-ruta`**.
 * Si no cambia, es `<x-muni::tabs>`.
 *
 * Por qué existe el componente (docs/GAP-ANALYSIS.md, ficha `tabs-ruta`):
 * `tabs` renderiza TODOS los paneles en el servidor y los alterna con x-show.
 * Para la ficha de un vecino con cinco secciones eso significa consultar y
 * pintar las cinco —con sus adjuntos— para mostrar una; y al volver de un
 * `wire:navigate` la sección elegida se pierde y no se puede mandar a Jurídica
 * el enlace directo a «Documentos».
 *
 * Y por qué NO se resuelve con `tabs`: si al seleccionar cambia la URL, ARIA
 * prohíbe `role="tablist"`. Esto es el patrón de navegación —landmark `nav` con
 * nombre accesible, lista, enlaces reales y `aria-current="page"`—, no el patrón
 * `tabs` del APG: sin flechas, sin roving tabindex, sin `aria-selected` y sin
 * una sola línea de Alpine. Tab entre solapas y Enter para seguirlas, que es lo
 * que ya da el `<a href>` nativo.
 *
 * Todo va con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
 * de `toContain()` es otra aguja que buscar, no un mensaje, y la aserción se
 * desactivaría sin avisar.
 */

/** Las tres secciones de la ficha del vecino en Atención al Vecino. */
function solapasDelVecino(): array
{
    return [
        ['etiqueta' => 'Solicitudes', 'href' => '/vecinos/4821/solicitudes', 'clave' => 'vecino.solicitudes'],
        ['etiqueta' => 'Documentos', 'href' => '/vecinos/4821/documentos', 'clave' => 'vecino.documentos'],
        ['etiqueta' => 'Historial de contactos', 'href' => '/vecinos/4821/contactos', 'clave' => 'vecino.contactos'],
    ];
}

/** Renderiza el componente con un árbol y la clave de la página actual. */
function fichaSolapas(array $items, ?string $actual = null, string $extra = ''): string
{
    return Blade::render(
        '<x-muni::tabs-ruta :items="$items" :actual="$actual" '.$extra.' />',
        ['items' => $items, 'actual' => $actual]
    );
}

/** Las etiquetas de apertura `<a …>` del componente, en orden. */
function enlacesDeSolapa(string $html): array
{
    preg_match_all('/<a\b[^>]*>/i', $html, $m);

    return $m[0];
}

/** El valor de un atributo dentro de una etiqueta de apertura, o null. */
function atributoDeSolapa(string $etiqueta, string $nombre): ?string
{
    return preg_match('/\s'.preg_quote($nombre, '/').'="([^"]*)"/i', $etiqueta, $m) ? $m[1] : null;
}

/** El fuente del componente, tal cual está en disco. */
function fuenteSolapasRuta(): string
{
    return file_get_contents(__DIR__.'/../resources/views/components/tabs-ruta.blade.php');
}

/**
 * El fuente sin comentarios: los de Blade `{{-- --}}` y los de CSS. Sin esto,
 * una regla citada en la prosa («nada de role=tablist») haría pasar o fallar una
 * aserción por el comentario y no por el código.
 */
function fuenteSolapasSinComentarios(): string
{
    $php = (string) preg_replace('/\{\{--.*?--\}\}/s', '', fuenteSolapasRuta());

    return (string) preg_replace('#/\*.*?\*/#s', '', $php);
}

/** El cuerpo de una regla CSS del bloque de estilos del componente. */
function reglaDeSolapas(string $selector): string
{
    $css = fuenteSolapasSinComentarios();

    return preg_match('/(?:^|\})\s*'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m', $css, $m)
        ? trim($m[1])
        : '';
}

// ---------------------------------------------------------------------------
// El patrón: navegación, no tablist
// ---------------------------------------------------------------------------

it('es un landmark de navegación con nombre accesible, no un tablist', function () {
    $html = fichaSolapas(solapasDelVecino(), 'vecino.documentos', 'label="Secciones del vecino"');

    expect((bool) preg_match('/<nav\b[^>]*\baria-label="Secciones del vecino"/i', $html))->toBeTrue(
        'El componente no emite un <nav> con nombre accesible: en la lista de regiones del lector '.
        'aparecería una «navegación» sin decir de qué. HTML: '.substr($html, 0, 300)
    );

    foreach (['tablist', 'tab', 'tabpanel'] as $rol) {
        expect((bool) preg_match('/\brole="'.$rol.'"/i', $html))->toBeFalse(
            "Emite role=\"$rol\". Si al elegir cambia la URL, ARIA prohíbe el patrón tabs: ".
            'esto es navegación y el rol falso hace que el lector prometa un panel que nunca llega.'
        );
    }

    expect((bool) preg_match('/\baria-selected=/i', $html))->toBeFalse(
        'Emite aria-selected, que es del patrón tabs. Acá el estado se dice con aria-current="page".'
    );
});

it('cada solapa es un enlace real y ninguna es un botón', function () {
    $html = fichaSolapas(solapasDelVecino(), 'vecino.solicitudes');

    $enlaces = enlacesDeSolapa($html);

    expect(count($enlaces))->toBe(3, 'No se emitió un <a> por sección.');

    foreach ($enlaces as $i => $enlace) {
        $href = atributoDeSolapa($enlace, 'href');

        expect($href !== null && $href !== '' && $href !== '#')->toBeTrue(
            "La solapa #$i no lleva href real: sin él no se puede abrir en otra pestaña, ni copiar ".
            'el enlace para mandárselo a Jurídica, que es justo para lo que existe el componente.'
        );
    }

    expect((bool) preg_match('/<button\b/i', $html))->toBeFalse(
        'Hay un <button>: una solapa que navega es un enlace, no un botón (WCAG 2.2 AA 4.1.2).'
    );
});

it('es una lista y conserva su semántica con list-style:none en Safari', function () {
    $html = fichaSolapas(solapasDelVecino(), 'vecino.documentos');

    expect((bool) preg_match('/<ul\b[^>]*\brole="list"/i', $html))->toBeTrue(
        'La lista no declara role="list": Safari con VoiceOver le quita la semántica de lista a '.
        'cualquier lista con list-style:none y las solapas vuelven a ser texto suelto.'
    );

    expect(substr_count($html, '<li'))->toBe(3, 'Cada solapa tiene que ser un <li> de la lista.');
});

// ---------------------------------------------------------------------------
// La solapa actual
// ---------------------------------------------------------------------------

it('marca la sección actual con aria-current="page" y solo una vez', function () {
    $html = fichaSolapas(solapasDelVecino(), 'vecino.documentos');

    expect(substr_count($html, 'aria-current="page"'))->toBe(
        1,
        'Tiene que haber exactamente un aria-current="page": cero deja al lector sin saber dónde '.
        'está, y dos le dicen que está en dos sitios a la vez.'
    );

    $enlaces = enlacesDeSolapa($html);

    expect(atributoDeSolapa($enlaces[1], 'aria-current'))->toBe(
        'page',
        'El aria-current no cayó en la solapa de Documentos, que es la de la clave actual.'
    );
});

it('la solapa actual se distingue sin depender del color', function () {
    $html = fichaSolapas(solapasDelVecino(), 'vecino.documentos');

    $enlaces = enlacesDeSolapa($html);
    $clase = (string) atributoDeSolapa($enlaces[1], 'class');

    expect(str_contains($clase, 'muni-tabr__solapa--on'))->toBeTrue(
        'La solapa actual no emite la clase que su bloque de estilos pinta. Clase: '.$clase
    );

    $barra = reglaDeSolapas('.muni-tabr__solapa--on::after');

    expect($barra !== '')->toBeTrue(
        'No existe la barra inferior de la solapa activa. En `tabs` el indicador que salva el 1.4.1 '.
        'es .muni-tab--on::after, y acá hay que conservarlo: sin él, «activa» sería solo un color.'
    );
    expect((bool) preg_match('/\bcontent\s*:/i', $barra) && (bool) preg_match('/\bheight\s*:/i', $barra))->toBeTrue(
        'La barra de la solapa activa no dibuja nada: le falta content o alto. Regla: '.$barra
    );

    $activa = reglaDeSolapas('.muni-tabr__solapa--on');

    expect((bool) preg_match('/font-weight\s*:\s*[6-9]00/i', $activa))->toBeTrue(
        'La solapa activa no cambia de peso tipográfico: junto con la barra es el segundo portador '.
        'no cromático del estado. Regla: '.$activa
    );
});

it('cuando dos solapas son antepasadas de la ruta actual gana la más específica', function () {
    $items = [
        ['etiqueta' => 'Ficha', 'href' => '/vecinos/4821', 'clave' => 'vecino'],
        ['etiqueta' => 'Documentos', 'href' => '/vecinos/4821/documentos', 'clave' => 'vecino.documentos'],
    ];

    $html = fichaSolapas($items, 'vecino.documentos.7');

    expect(substr_count($html, 'aria-current="page"'))->toBe(
        1,
        'Con dos solapas antepasadas se marcaron las dos: el lector anuncia dos páginas actuales.'
    );

    $enlaces = enlacesDeSolapa($html);

    expect(atributoDeSolapa($enlaces[1], 'aria-current'))->toBe(
        'page',
        'Ganó la solapa genérica «Ficha» sobre «Documentos», que es la que de verdad se está mirando.'
    );
});

it('el descendiente se reconoce en el límite del segmento y nunca a media palabra', function () {
    $conClave = fn (string $clave, string $actual) => fichaSolapas(
        [['etiqueta' => 'Sección', 'href' => '/x', 'clave' => $clave]],
        $actual
    );

    expect(str_contains($conClave('vecino.documentos', 'vecino.documentos'), 'aria-current="page"'))->toBeTrue(
        'La coincidencia exacta no marca la solapa.'
    );
    expect(str_contains($conClave('vecino.documentos', 'vecino.documentos.7'), 'aria-current="page"'))->toBeTrue(
        'Un descendiente con «.» no marca a su antepasado: al abrir el documento 7 la solapa se apaga.'
    );
    expect(str_contains($conClave('/vecinos/4821', '/vecinos/4821/documentos'), 'aria-current="page"'))->toBeTrue(
        'Un descendiente con «/» no marca a su antepasado: la regla tiene que servir con rutas URL.'
    );
    expect(str_contains($conClave('vecino.doc', 'vecino.documentos'), 'aria-current="page"'))->toBeFalse(
        '«vecino.doc» se marcó como antepasado de «vecino.documentos»: la comparación es a media '.
        'palabra y marca solapas que no corresponden.'
    );
    expect(str_contains($conClave('vecino.documentos', null ?? ''), 'aria-current="page"'))->toBeFalse(
        'Sin clave actual no hay página actual: no se puede marcar nada.'
    );
});

it('el anfitrión puede resolver él mismo cuál está activa y su palabra manda', function () {
    $items = [
        ['etiqueta' => 'Solicitudes', 'href' => '/a', 'clave' => 'vecino.solicitudes'],
        ['etiqueta' => 'Documentos', 'href' => '/b', 'clave' => 'vecino.documentos', 'activo' => true],
    ];

    $html = fichaSolapas($items, 'vecino.solicitudes');

    $enlaces = enlacesDeSolapa($html);

    expect(atributoDeSolapa($enlaces[1], 'aria-current'))->toBe(
        'page',
        'El `activo` declarado por el anfitrión no ganó sobre la regla de claves.'
    );
    expect(substr_count($html, 'aria-current="page"'))->toBe(
        1,
        'Con `activo` declarado quedaron dos solapas marcadas.'
    );
});

// ---------------------------------------------------------------------------
// Teclado: lo que NO tiene que haber
// ---------------------------------------------------------------------------

it('no hay flechas, ni roving tabindex, ni una línea de Alpine', function () {
    $html = fichaSolapas(solapasDelVecino(), 'vecino.documentos');

    expect((bool) preg_match('/(?:@|x-on:)keydown/i', $html))->toBeFalse(
        'Hay un manejador de teclado. Son enlaces: las flechas y el roving tabindex del patrón tabs '.
        'confundirían a quien ya sabe que Tab recorre enlaces.'
    );
    expect((bool) preg_match('/\btabindex="-1"/i', $html))->toBeFalse(
        'Hay un tabindex="-1": el roving tabindex saca solapas del orden de tabulación y acá las '.
        'tres tienen que ser paradas de Tab.'
    );
    expect((bool) preg_match('/\bx-(data|show|init|ref|bind|text)\b/i', $html))->toBeFalse(
        'El componente hidrata Alpine. Es navegación pura: tiene que funcionar con JS apagado.'
    );

    $fuente = fuenteSolapasSinComentarios();

    expect((bool) preg_match('/x-data|@click|x-on:|@keydown/i', $fuente))->toBeFalse(
        'El fuente trae Alpine. La ficha lo dice explícito: Alpine — no.'
    );
});

it('un texto del anfitrión con apóstrofo no puede tumbar el Alpine de la página', function () {
    $items = [
        ['etiqueta' => "Solicitudes d'oficio", 'href' => '/a', 'clave' => 'x'],
        ['etiqueta' => "<script>alert('x')</script>", 'href' => '/b', 'clave' => 'y'],
    ];

    $html = fichaSolapas($items, 'x');

    expect(str_contains($html, '&#039;'))->toBeTrue(
        'El apóstrofo no salió escapado: si algún día el texto entrara en una expresión de Alpine, '.
        'el navegador lo decodificaría dentro del valor del atributo y tumbaría la página entera.'
    );
    expect(str_contains($html, '<script>'))->toBeFalse(
        'Una etiqueta del anfitrión llegó sin escapar al HTML: la etiqueta de una solapa es TEXTO.'
    );
});

// ---------------------------------------------------------------------------
// El contrato del paquete
// ---------------------------------------------------------------------------

it('no consulta request(), Auth, Gate ni permisos: el anfitrión resuelve', function () {
    $fuente = fuenteSolapasSinComentarios();

    foreach (['request(', 'Auth::', 'auth()', 'Gate::', '->can(', 'route('] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            // Las comillas angulares van FUERA de la interpolación: PHP admite
            // bytes altos en los identificadores, así que «$prohibido»» se lee
            // como una variable llamada `prohibido»` que no existe.
            'El componente usa «'.$prohibido.'». El paquete no sabe qué es una petición ni un permiso: '.
            'recibe la clave actual y el árbol YA filtrado. Un componente de diseño que resuelve '.
            'permisos invita a «proteger» un endpoint escondiéndolo del menú, y eso no protege nada.'
        );
    }
});

it('el filtro `ver` es cosmético y esconde la solapa sin dejar rastro', function () {
    $items = solapasDelVecino();
    $items[2]['ver'] = false;

    $html = fichaSolapas($items, 'vecino.solicitudes');

    expect(count(enlacesDeSolapa($html)))->toBe(2, 'La solapa oculta se emitió igual.');
    expect(str_contains($html, 'Historial de contactos'))->toBeFalse(
        'El texto de la solapa oculta sigue en el HTML.'
    );
    expect(str_contains($html, '/vecinos/4821/contactos'))->toBeFalse(
        'La URL de la solapa oculta sigue en el HTML: esconder no es autorizar, pero filtrar a medias '.
        'es lo peor de los dos mundos.'
    );
});

it('sin solapas visibles no emite el landmark', function () {
    /*
     * Se mira el LANDMARK, no la cadena entera: el bloque de estilos del
     * componente vive fuera del condicional a propósito —es lo que pide DESIGN
     * §7, que lo que un componente necesita para verse viaje con él— y sale
     * igual. Lo que no puede salir es un <nav> sin nada dentro.
     */
    $vacio = fichaSolapas([], 'x');

    expect((bool) preg_match('/<nav\b/i', $vacio))->toBeFalse(
        'Con la lista vacía emite un <nav> igual: una región de navegación sin nada dentro es una '.
        'entrada más en la lista de regiones del lector que no lleva a ninguna parte.'
    );
    expect(enlacesDeSolapa($vacio))->toBe([], 'Con la lista vacía emitió enlaces.');

    $soloOcultas = fichaSolapas([['etiqueta' => 'Documentos', 'href' => '/b', 'ver' => false]], 'x');

    expect((bool) preg_match('/<nav\b/i', $soloOcultas))->toBeFalse(
        'Con todas las solapas ocultas por `ver` sigue emitiendo el landmark vacío.'
    );
});

it('descarta un ítem que no es un arreglo o que no tiene texto', function () {
    $items = [
        ['etiqueta' => 'Solicitudes', 'href' => '/a'],
        'una cadena suelta',
        ['href' => '/sin-texto'],
        ['etiqueta' => '   ', 'href' => '/en-blanco'],
        ['etiqueta' => 'Sin URL'],
    ];

    $html = fichaSolapas($items, null);

    expect(count(enlacesDeSolapa($html)))->toBe(
        1,
        'Un ítem sin etiqueta, sin href o que no es un arreglo tiene que descartarse: emitir el '.
        'enlace sin nombre accesible deja al lector leyendo la URL (WCAG 2.2 A 2.4.4), una solapa '.
        'sin URL no navega a ninguna parte —que es todo el punto del componente—, y un volcado de '.
        'modelo entero no puede convertirse en solapas.'
    );
    expect(str_contains($html, '/sin-texto'))->toBeFalse('Se emitió un enlace sin nombre accesible.');
    expect(str_contains($html, 'Sin URL'))->toBeFalse('Se emitió una solapa que no lleva a ninguna parte.');
});

it('el contador llega calculado y dice QUÉ son cuatro', function () {
    $items = [
        ['etiqueta' => 'Documentos', 'href' => '/b', 'clave' => 'd', 'badge' => 4, 'badgeLabel' => 'documentos adjuntos'],
    ];

    $html = fichaSolapas($items, 'd');

    expect((bool) preg_match('/<span[^>]*\baria-hidden="true"[^>]*>\s*4\s*<\/span>/i', $html))->toBeTrue(
        'El número no va aria-hidden: el lector anuncia «Documentos 4» sin decir qué son cuatro.'
    );
    expect(str_contains($html, '4 documentos adjuntos'))->toBeTrue(
        'Falta el texto solo para lector que dice qué cuenta el contador.'
    );

    $sr = reglaDeSolapas('.muni-tabr__sr');

    expect(str_contains($sr, 'clip-path') && ! str_contains($sr, 'display:none'))->toBeTrue(
        'El texto solo para lector se oculta con display:none o no se oculta: tiene que recortarse '.
        'para seguir en el árbol de accesibilidad (DESIGN §7: la utilidad viaja en el propio @once). '.
        'Regla: '.$sr
    );
});

it('sin badgeLabel no se inventa un significado', function () {
    $items = [['etiqueta' => 'Documentos', 'href' => '/b', 'clave' => 'd', 'badge' => 4]];

    $html = fichaSolapas($items, 'd');

    expect(str_contains($html, '4'))->toBeTrue('El contador no se pintó.');
    expect((bool) preg_match('/aria-hidden="true"[^>]*>\s*4/i', $html))->toBeFalse(
        'Sin badgeLabel el número se ocultó del lector y no se puso nada en su lugar: el contador '.
        'desaparece para quien no ve la pantalla.'
    );
});

it('wire:navigate solo aparece cuando el anfitrión lo pide', function () {
    $sin = fichaSolapas(solapasDelVecino(), 'vecino.solicitudes');

    expect(str_contains($sin, 'wire:navigate'))->toBeFalse(
        'Estampa wire:navigate sin que se lo pidan: el paquete se instala también en sistemas sin '.
        'Livewire y en páginas donde la navegación SPA no se quiere.'
    );

    $con = fichaSolapas(solapasDelVecino(), 'vecino.solicitudes', 'navegar');

    expect(substr_count($con, 'wire:navigate'))->toBe(
        3,
        'Con `navegar` las tres solapas tienen que llevar wire:navigate: es lo que evita repintar la '.
        'ficha entera al cambiar de sección. Con él, aria-current lo recalcula el servidor.'
    );
});

// ---------------------------------------------------------------------------
// Contrato visual del paquete
// ---------------------------------------------------------------------------

it('los ids y el HTML son estables entre renders: nada de uniqid()', function () {
    $primero = fichaSolapas(solapasDelVecino(), 'vecino.documentos');
    $segundo = fichaSolapas(solapasDelVecino(), 'vecino.documentos');

    expect($primero)->toBe(
        $segundo,
        'Dos renders idénticos dan HTML distinto: con ids que cambian, Livewire reemplaza nodos que '.
        'no cambiaron y se pierde el foco al refrescar (DESIGN §10).'
    );

    expect(str_contains(fuenteSolapasRuta(), 'uniqid('))->toBeFalse('El componente usa uniqid().');
});

it('el foco se dibuja con outline y la cadena de respaldo completa', function () {
    $foco = reglaDeSolapas('.muni-tabr__solapa:focus-visible');

    expect($foco !== '')->toBeTrue('La solapa no declara ninguna regla de foco.');

    expect(str_contains($foco, 'outline:3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeTrue(
        'El foco no usa el outline de 3px con la cadena de respaldo del paquete. Dentro de Filament '.
        'la box-shadow del anillo se computa transparente y el teclado se queda sin indicador '.
        '(WCAG 2.2 AA 2.4.7). Regla: '.$foco
    );
});

it('el área táctil de la solapa llega al mínimo y el movimiento se apaga solo', function () {
    $solapa = reglaDeSolapas('.muni-tabr__solapa');

    expect((bool) preg_match('/min-height\s*:\s*(\d+)px/', $solapa, $m) && (int) $m[1] >= 24)->toBeTrue(
        'La solapa no declara un alto mínimo de 24px (WCAG 2.2 AA 2.5.8). Regla: '.$solapa
    );

    $css = fuenteSolapasSinComentarios();

    expect((bool) preg_match('/transition\s*:[^;]*\b\d*\.?\d+m?s\b/i', $css))->toBeFalse(
        'Hay una transición con duración fija: ignora prefers-reduced-motion. El movimiento va con '.
        'var(--muni-dur), que ya baja a 0 ms con movimiento reducido (DESIGN §6).'
    );

    /*
     * Y con var(--muni-dur) NO alcanza dentro de un panel: `muni-ui.css` baja el
     * token con la preferencia, pero ahí solo se carga `muni-ui-filament.css`
     * (DESIGN §7), que no lo baja. Medido en Chromium y Firefox sobre el banco,
     * la transición seguía en 160 ms en panel-claro y panel-oscuro hasta que el
     * componente puso su propia regla. Va con !important por el mismo motivo que
     * en `stat`: la declaración de la regla de clase sola pierde.
     */
    expect((bool) preg_match(
        '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{\s*\.muni-tabr__solapa\s*\{[^}]*transition\s*:\s*none\s*!important/i',
        $css
    ))->toBeTrue(
        'Falta la regla propia de movimiento reducido. Dentro de un panel Filament --muni-dur no '.
        'baja a 0 ms y la solapa sigue animándose contra la preferencia del sistema. CSS: '.$css
    );
});

it('no escribe un solo color literal fuera del respaldo del foco', function () {
    $css = fuenteSolapasSinComentarios();

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $m);

    expect(array_values(array_unique(array_diff($m[0], ['#767676']))))->toBe(
        [],
        'Hay colores literales en el componente. Todo color sale de un token --muni-*, que es lo que '.
        'permite a otro municipio cambiar su identidad sin editar el paquete (DESIGN §1).'
    );
});

it('las solapas caben en el ancho del teléfono sin desplazamiento horizontal', function () {
    $lista = reglaDeSolapas('.muni-tabr__lista');

    expect((bool) preg_match('/flex-wrap\s*:\s*wrap/i', $lista))->toBeTrue(
        'La fila de solapas no envuelve: a 320px de ancho obligaría a desplazarse en horizontal '.
        '(WCAG 2.2 AA 1.4.10) o crearía una región desplazable que hay que hacer alcanzable por '.
        'teclado. Regla: '.$lista
    );
    expect((bool) preg_match('/overflow(-x)?\s*:\s*(auto|scroll)/i', $lista))->toBeFalse(
        'La lista crea una región con desplazamiento: además del 1.4.10, recortaría la barra '.
        'inferior de la solapa activa. Regla: '.$lista
    );
});

it('renderiza con una sola solapa y nada más', function () {
    /*
     * El caso mínimo con datos: una solapa y contenido en la ranura.
     */
    $extra = ':items="[[\'etiqueta\' => \'Solicitudes\', \'href\' => \'/vecinos/4821/solicitudes\']]"';
    $html = Blade::render('<x-muni::tabs-ruta '.$extra.'>Contenido de prueba</x-muni::tabs-ruta>');

    expect(trim($html))->not->toBe('', 'El componente sale vacío con sus props mínimas.');
    expect(str_contains($html, 'Solicitudes'))->toBeTrue('No pintó la única solapa.');
    expect(str_contains($html, 'Contenido de prueba'))->toBeTrue(
        'La ranura se tragó el contenido del consumidor. La sección la pinta la RUTA, pero lo que '.
        'igual le pasen se emite debajo de las solapas en vez de desaparecer en silencio.'
    );
});

it('la entrada de la vitrina renderiza la ficha del vecino con datos reales', function () {
    /*
     * La misma cadena que va en `ejemplosDeVitrina()` de GeneraVitrinaTest, que
     * es lo que abre la reja de accesibilidad en los dos temas y las dos paletas.
     * Se comprueba acá porque ese arreglo vive en un archivo compartido: si la
     * entrada se copia mal, la reja mediría una tarjeta vacía y no lo diría.
     */
    $ejemplo = '<x-muni::tabs-ruta label="Secciones del vecino" actual="vecino.documentos" :items="['
        .'[\'etiqueta\' => \'Solicitudes\', \'href\' => \'#solicitudes\', \'clave\' => \'vecino.solicitudes\', \'badge\' => 3, \'badgeLabel\' => \'solicitudes abiertas\'],'
        .'[\'etiqueta\' => \'Documentos\', \'href\' => \'#documentos\', \'clave\' => \'vecino.documentos\', \'badge\' => 2, \'badgeLabel\' => \'documentos adjuntos\'],'
        .'[\'etiqueta\' => \'Historial de contactos\', \'href\' => \'#contactos\', \'clave\' => \'vecino.contactos\'],'
        .']" />';

    $html = Blade::render($ejemplo);

    expect(count(enlacesDeSolapa($html)))->toBe(3, 'La entrada de la vitrina no pinta las tres secciones.');
    expect(substr_count($html, 'aria-current="page"'))->toBe(1, 'La entrada de la vitrina no marca la sección actual.');
    expect(str_contains($html, '3 solicitudes abiertas'))->toBeTrue('La entrada de la vitrina pierde el contador.');
    /* El contador va también en la solapa ACTIVA: es la única regla que invierte los
       colores (fondo --muni-accent, texto --muni-on-accent) y sin eso la reja de la
       vitrina no la mediría en ninguna de las cuatro paletas. */
    expect(str_contains($html, '2 documentos adjuntos'))->toBeTrue(
        'La entrada de la vitrina no lleva contador en la solapa activa: la regla que invierte '.
        'los colores se queda sin medir.'
    );
});

it('se renderiza SIN UNA SOLA PROP, que es como lo llama el candado de humo', function () {
    /*
     * Esta es la llamada exacta que arma `TodosRendericanTest` para todo
     * componente que NO tiene entrada en `propsObligatorias()`: la etiqueta
     * pelada y contenido en la ranura.
     *
     * Aquí murió la primera versión: `items` se declaró sin valor por defecto
     * («sin datos no hay navegación») y el candado global del repo quedó en rojo
     * con «Undefined variable $items». El contrato no cambia por darle `[]`: el
     * componente YA trataba la lista vacía —no emite el landmark— porque una
     * región de navegación sin enlaces es una entrada más en la lista de
     * regiones del lector que no lleva a ninguna parte. Lo que cambia es que el
     * anfitrión que se olvida de `items` ve una ficha sin solapas en vez de una
     * página caída.
     */
    $html = Blade::render('<x-muni::tabs-ruta>Contenido de prueba</x-muni::tabs-ruta>');

    expect(str_contains($html, 'Contenido de prueba'))->toBeTrue(
        'Sin `items` el componente no renderiza: el candado de humo del repo queda en rojo.'
    );
    expect(str_contains($html, '<nav'))->toBeFalse(
        'Sin solapas emite igual el landmark: una región de navegación vacía es ruido para el lector.'
    );
});

it('el aria-labelledby NULO del anfitrión no deja el landmark sin nombre', function () {
    /*
     * DESIGN §8: `:atributo="null"` no desaparece, GANA. El caso natural del
     * consumidor es `:aria-labelledby="$tituloFichaId"`, y ese id es null hasta
     * que la ficha tiene título. Con la comprobación por `has()` el componente
     * se callaba su `aria-label` por defecto y Blade no imprimía el atributo
     * nulo: quedaba un `<nav>` sin NINGÚN nombre accesible, que es justo lo que
     * la ficha del componente exige que haya.
     */
    $html = Blade::render(
        '<x-muni::tabs-ruta :items="$items" :aria-labelledby="$id" />',
        ['items' => solapasDelVecino(), 'id' => null]
    );

    expect((bool) preg_match('/<nav\b[^>]*\b(aria-label|aria-labelledby)="[^"]+"/i', $html))->toBeTrue(
        'Con :aria-labelledby="null" el landmark sale sin nombre accesible: '.$html
    );

    /* Lo mismo con la cadena vacía, que es lo que deja un `?? \'\'` del anfitrión. */
    $vacio = Blade::render(
        '<x-muni::tabs-ruta :items="$items" aria-labelledby="" />',
        ['items' => solapasDelVecino()]
    );

    expect((bool) preg_match('/<nav\b[^>]*\baria-label="[^"]+"/i', $vacio))->toBeTrue(
        'Con aria-labelledby="" tampoco recupera su nombre por defecto: '.$vacio
    );
    expect((bool) preg_match('/aria-labelledby="\s*"/i', $vacio))->toBeFalse(
        'Deja puesto un aria-labelledby vacío al lado del aria-label: por la norma el vacío no '.
        'nombra nada y gana el aria-label, pero no todos los lectores lo resuelven igual y el '.
        'landmark queda a merced de la implementación. Se saca de la bolsa: '.$vacio
    );
});

it('un objeto sin __toString dentro de una solapa se descarta en vez de tumbar la página', function () {
    /*
     * La minimización (Ley 21.719) ya descartaba el ítem que no es arreglo —el
     * modelo volcado entero—, pero el modelo colado UN NIVEL MÁS ADENTRO
     * (`'etiqueta' => $vecino`) reventaba con Error en el `(string)`. Una ficha
     * caída por eso es peor que una solapa de menos, y además el volcado de la
     * excepción es justo lo que no puede salir a la página.
     */
    $items = [
        ['etiqueta' => new stdClass, 'href' => '/vecinos/4821/solicitudes'],
        ['etiqueta' => 'Documentos', 'href' => new stdClass],
        ['etiqueta' => 'Historial de contactos', 'href' => '/vecinos/4821/contactos', 'badge' => new stdClass],
        ['etiqueta' => 'Domicilios', 'href' => '/vecinos/4821/domicilios', 'clave' => new stdClass],
    ];

    $html = fichaSolapas($items, 'vecino.contactos');
    $enlaces = enlacesDeSolapa($html);

    expect(count($enlaces))->toBe(2, 'Se esperaban solo las dos solapas sanas: '.$html);
    expect(str_contains($html, 'Historial de contactos'))->toBeTrue('Descartó la solapa sana por el badge inválido.');
    expect(str_contains($html, 'Domicilios'))->toBeTrue('Descartó la solapa sana por la clave inválida.');
    /* Se busca el ELEMENTO, no la clase: la clase también sale en el bloque de estilos. */
    expect((bool) preg_match('/<span class="muni-tabr__badge"/', $html))->toBeFalse(
        'Pintó un contador con un valor que no es texto.'
    );
});

it('el rótulo parte las palabras largas en vez de desbordar la fila', function () {
    /*
     * La fila envuelve (flex-wrap) justo para no desplazarse en horizontal a
     * 320px, pero una etiqueta de UNA sola palabra larga —«Regularizaciones» y
     * compañía— no tiene dónde envolver: sin esto empuja la fila más allá del
     * viewport y vuelve el 1.4.10 por la puerta de atrás.
     */
    $rotulo = reglaDeSolapas('.muni-tabr__rotulo');

    expect((bool) preg_match('/overflow-wrap\s*:\s*(anywhere|break-word)/i', $rotulo))->toBeTrue(
        'El rótulo no declara overflow-wrap: una palabra larga desborda en horizontal. Regla: '.$rotulo
    );
});

it('el nombre por defecto no pelea con el aria-labelledby del consumidor', function () {
    $html = fichaSolapas(solapasDelVecino(), 'vecino.documentos', 'aria-labelledby="titulo-ficha"');

    expect((bool) preg_match('/<nav\b[^>]*\baria-label="/i', $html))->toBeFalse(
        'Con aria-labelledby del consumidor sigue emitiendo su aria-label por defecto: dos nombres '.
        'compitiendo en el mismo landmark.'
    );
    expect((bool) preg_match('/<nav\b[^>]*\baria-labelledby="titulo-ficha"/i', $html))->toBeTrue(
        'No respetó el aria-labelledby del consumidor.'
    );
});

// ---------------------------------------------------------------------------
// Verificación en navegador
// ---------------------------------------------------------------------------

/*
 * El banco de navegador. Se genera acá y no en un script suelto porque este es
 * el único sitio del paquete con Blade arrancado (mismo criterio que
 * `GeneraVitrinaTest`, `CifraComparadaTest` y `GuardiaDeSesionTest`). Sale a
 * `build/solapas-de-ruta/`, ignorado por git.
 *
 * Son CUATRO páginas por lo mismo que la vitrina (DESIGN §7): dentro de un panel
 * Filament `muni-ui.css` no se carga y la paleta del panel vale distinto, así que
 * `panel-*` carga ÚNICAMENTE `muni-ui-filament.css` y el DOM mínimo que esa hoja
 * estiliza.
 *
 * Los href son anclas para que pulsar Enter active la solapa sin sacar a
 * Playwright de la página: lo que se comprueba es que el enlace se sigue con el
 * teclado, no a dónde lleva.
 */
it('genera el banco de navegador en build/solapas-de-ruta/', function () {
    $dir = __DIR__.'/../build/solapas-de-ruta';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $solapas = Blade::render(
        '<h1 id="titulo-ficha">Ana Soto Rivera</h1>'
        .'<x-muni::tabs-ruta label="Secciones del vecino" actual="vecino.documentos" :items="['
        ."['etiqueta' => 'Solicitudes', 'href' => '#solicitudes', 'clave' => 'vecino.solicitudes', 'badge' => 3, 'badgeLabel' => 'solicitudes abiertas'],"
        /* El contador va también en la solapa ACTIVA: es la única regla del componente
           que INVIERTE los colores (fondo --muni-accent sobre texto --muni-on-accent) y
           sin una solapa activa con contador en el banco esa regla no la medía nadie. */
        ."['etiqueta' => 'Documentos', 'href' => '#documentos', 'clave' => 'vecino.documentos', 'badge' => 2, 'badgeLabel' => 'documentos adjuntos'],"
        ."['etiqueta' => 'Historial de contactos', 'href' => '#contactos', 'clave' => 'vecino.contactos'],"
        ."['etiqueta' => 'Domicilios', 'href' => '#domicilios', 'clave' => 'vecino.domicilios'],"
        .']" />'
        .'<p>Sección: documentos adjuntos de la ficha.</p>'
    );

    $paginas = [
        'claro' => ['light', cssMuniUi(), false],
        'oscuro' => ['dark', cssMuniUi(), false],
        'panel-claro' => ['light', cssMuniUiFilament(), true],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel]) {
        $oscuro = $tema === 'dark';
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        /* El armazón no aporta ni un color: fondo y texto salen de los mismos
           tokens que lee el componente. */
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'h1{font-size:20px;margin:0 0 16px}';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<section class="fi-section">'.$solapas.'</section></main></div></body>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$solapas.'</main></body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco tabs-ruta — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDeLaRejaSolapas(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que un test de Blade no puede ver, medido en Chromium Y Firefox sobre el
 * banco. Las pruebas de arriba leen el TEXTO del CSS del componente, y eso ya
 * engañó una vez en `stat`: la regla de movimiento reducido pasó el test de
 * texto y en el navegador la transición seguía viva. Acá se mide el recorrido
 * real de Tab con `document.activeElement`, el outline computado, la barra del
 * `::after`, el contraste contra el fondo efectivo y el ancho de teléfono.
 * Se SALTA —no se finge— cuando no está `.venv-a11y`, que en CI no se instala.
 */
it('en Chromium y Firefox: Tab recorre las solapas, el foco se ve, la barra de la activa se pinta y a 390px no hay desplazamiento horizontal', function () {
    $python = pythonDeLaRejaSolapas();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/solapas-de-ruta.py').' '
        .escapeshellarg(__DIR__.'/../build/solapas-de-ruta').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de <x-muni::tabs-ruta> falló:\n".implode("\n", $lineas));
});
