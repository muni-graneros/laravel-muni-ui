<?php

use Illuminate\Support\Facades\Blade;

/*
 * Navegación declarativa: `nav-menu` y `nav-grupo`.
 *
 * El defecto que cierran: hoy cada sistema escribe a mano su lista de
 * `<x-muni::nav-item>` en el layout, con un `@can` alrededor de cada uno y la
 * regla de «activo» copiada ítem por ítem. Seis sistemas por ~15 ítems: la regla
 * se escribe casi cien veces y ya diverge —unos con `routeIs`, otros con
 * `request()->is()`, otros con la URL entera—, así que el ítem marcado y las
 * migas no siempre coinciden.
 *
 * El contrato que arregla eso tiene dos mitades y las dos las decide el
 * ANFITRIÓN, no el paquete:
 *
 *   · PERMISO. El árbol llega ya filtrado, o cada nodo trae `ver` como booleano
 *     YA EVALUADO por el host. El paquete no llama a `Gate`, ni a `Auth`, ni a
 *     `request()`: un componente de diseño que consulta permisos por su cuenta es
 *     una trampa de seguridad —invita a «proteger» un endpoint escondiéndolo del
 *     menú— y además ata el paquete al contenedor de la aplicación.
 *   · ACTIVO. El host pasa `actual` (la clave de la página que se está mirando) y
 *     cada nodo declara su `clave`. La regla es UNA y está escrita una sola vez
 *     en el componente.
 *
 * Estas pruebas vigilan las dos mitades, el estado plegado declarado en servidor
 * y las trampas del repo (Alpine, tokens, foco, duración).
 */

/** Renderiza el menú completo con el árbol y la clave activa que se le pasen. */
function menuRendido(array $items, ?string $actual = null, string $atributos = ''): string
{
    return Blade::render(
        '<x-muni::nav-menu :items="$items" :actual="$actual" '.$atributos.' />',
        ['items' => $items, 'actual' => $actual]
    );
}

/** El fuente de un componente SIN comentarios: así no se matchea la prosa explicativa. */
function fuenteSinComentarios(string $componente): string
{
    $fuente = file_get_contents(__DIR__.'/../resources/views/components/'.$componente.'.blade.php');

    $fuente = preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** Un árbol de ejemplo con las cuatro formas de nodo del contrato. */
function arbolDeEjemplo(): array
{
    return [
        ['etiqueta' => 'Escritorio', 'href' => '/panel', 'clave' => 'panel'],
        [
            'tipo' => 'titulo',
            'etiqueta' => 'Operaciones',
            'items' => [
                [
                    'tipo' => 'grupo',
                    'etiqueta' => 'Terreno',
                    'items' => [
                        ['etiqueta' => 'Cámaras', 'href' => '/camaras', 'clave' => 'seguridad.camaras'],
                        ['etiqueta' => 'Rondas', 'href' => '/rondas', 'clave' => 'seguridad.rondas'],
                    ],
                ],
                [
                    'etiqueta' => 'Solicitudes',
                    'href' => '/solicitudes',
                    'clave' => 'licencias.solicitudes',
                    'badge' => 12,
                    'badgeLabel' => 'solicitudes esperando en el mesón',
                ],
            ],
        ],
        ['etiqueta' => 'Anular licencia', 'href' => '/anular', 'clave' => 'licencias.anular', 'ver' => false],
    ];
}

it('pinta el árbol como un landmark de navegación con nombre y una lista', function () {
    $html = menuRendido(arbolDeEjemplo());

    expect(str_contains($html, '<nav'))->toBeTrue('el menú debe ser un landmark <nav>');
    expect(preg_match('/<nav[^>]*aria-label="[^"]+"/', $html))->toBe(1, 'el <nav> necesita nombre accesible');
    expect(str_contains($html, '<ul'))->toBeTrue('los ítems van en una lista');
    expect(str_contains($html, 'href="/panel"'))->toBeTrue('el enlace del nodo tiene que salir');
    expect(str_contains($html, 'Cámaras'))->toBeTrue('los hijos del grupo tienen que salir');
});

it('el filtro por permiso es del anfitrión: `ver` a false esconde el nodo', function () {
    $html = menuRendido(arbolDeEjemplo());

    expect(str_contains($html, 'Anular licencia'))->toBeFalse('un nodo con ver=false no se pinta');
    expect(str_contains($html, '/anular'))->toBeFalse('ni su href');
    expect(str_contains($html, 'Solicitudes'))->toBeTrue('un nodo sin ver declarado se pinta');
});

it('el paquete no consulta permisos ni la petición por su cuenta', function () {
    foreach (['nav-menu', 'nav-grupo'] as $componente) {
        $fuente = fuenteSinComentarios($componente);

        foreach (['Gate::', 'auth()', 'Auth::', 'request()', '@can', '@cannot', 'Route::current'] as $prohibido) {
            expect(str_contains($fuente, $prohibido))->toBeFalse(
                "«{$componente}» usa {$prohibido}: la autorización y la petición son del anfitrión, no del paquete"
            );
        }
    }
});

it('marca el activo con una regla única: la clave de la página y el descenso por . o /', function () {
    // La página es el detalle de una solicitud: desciende de `licencias.solicitudes`.
    $html = menuRendido(arbolDeEjemplo(), 'licencias.solicitudes.42');

    expect(preg_match('/<a[^>]*href="\/solicitudes"[^>]*aria-current="page"/', $html))
        ->toBe(1, 'el ítem que contiene la página actual va con aria-current="page"');
    expect(preg_match('/<a[^>]*href="\/panel"[^>]*aria-current/', $html))
        ->toBe(0, 'ningún otro ítem queda marcado');

    // Y la misma regla con rutas URL, que es el otro separador del ecosistema.
    $porUrl = menuRendido([
        ['etiqueta' => 'Cámaras', 'href' => '/camaras', 'clave' => '/camaras'],
        ['etiqueta' => 'Rondas', 'href' => '/rondas', 'clave' => '/rondas'],
    ], '/camaras/7/editar');

    expect(preg_match('/<a[^>]*href="\/camaras"[^>]*aria-current="page"/', $porUrl))->toBe(1, 'el descenso por / también marca');
    expect(preg_match('/<a[^>]*href="\/rondas"[^>]*aria-current/', $porUrl))->toBe(0, 'y no marca al hermano');

    // Un prefijo que no cae en un límite de segmento NO marca: `licencias.anular`
    // no descende de `licencias.an`.
    $falsoPrefijo = menuRendido([
        ['etiqueta' => 'Anular', 'href' => '/anular', 'clave' => 'licencias.an'],
    ], 'licencias.anular');

    expect(str_contains($falsoPrefijo, 'aria-current'))->toBeFalse('el prefijo a media palabra no marca');
});

it('el anfitrión puede resolver el activo él mismo con `activo`', function () {
    $html = menuRendido([
        ['etiqueta' => 'Escritorio', 'href' => '/panel', 'activo' => true],
        ['etiqueta' => 'Solicitudes', 'href' => '/solicitudes', 'clave' => 'licencias.solicitudes', 'activo' => false],
    ], 'licencias.solicitudes');

    expect(preg_match('/<a[^>]*href="\/panel"[^>]*aria-current="page"/', $html))->toBe(1, '`activo` explícito manda');
    expect(preg_match('/<a[^>]*href="\/solicitudes"[^>]*aria-current/', $html))->toBe(0, '`activo` a false gana sobre la clave');
});

it('el grupo que contiene la página actual llega ABIERTO desde el servidor', function () {
    $abierto = menuRendido(arbolDeEjemplo(), 'seguridad.camaras');
    $cerrado = menuRendido(arbolDeEjemplo(), 'panel');

    expect(preg_match('/<button[^>]*aria-expanded="true"/', $abierto))
        ->toBe(1, 'con la página adentro, el grupo se pinta abierto en servidor (si lo decide Alpine, el ítem activo nace oculto)');
    expect(preg_match('/<button[^>]*aria-expanded="false"/', $cerrado))
        ->toBe(1, 'sin la página adentro, el grupo se pinta cerrado');

    // Y cerrado no debe recibir el foco: `grid-template-rows:0fr` esconde pero NO
    // saca del orden de tabulación (el mismo defecto que ya se cerró en sidebar).
    expect(preg_match('/<div[^>]*\sinert\b/', $cerrado))->toBe(1, 'el panel plegado va inert');
    expect(preg_match('/<div[^>]*\sinert\b/', $abierto))->toBe(0, 'el panel abierto no va inert');

    // La clase con la que abre el CSS y el `aria-expanded` del botón salen del
    // MISMO dato en las dos pinturas: en servidor de la prop, en cliente del
    // `abierto` de Alpine. Si se desincronizaran, el menú se vería abierto y se
    // anunciaría cerrado.
    $claseServida = '/<div[^>]*\sclass="[^"]*muni-navg--abierto/';
    expect(preg_match($claseServida, $abierto))->toBe(1, 'abierto en servidor también en la clase del CSS');
    expect(preg_match($claseServida, $cerrado))->toBe(0, 'cerrado no trae la clase');

    $fuente = fuenteSinComentarios('nav-grupo');
    expect(str_contains($fuente, ":class=\"{ 'muni-navg--abierto': abierto }\""))
        ->toBeTrue('la clase va en sintaxis de objeto: la de cadena no sabe quitar una clase que ya venía del servidor');
    expect(str_contains($fuente, ':aria-expanded="abierto'))->toBeTrue('y el estado anunciado sale del mismo booleano');
});

it('el grupo es un disclosure de verdad: botón, aria-expanded y aria-controls atados', function () {
    $html = Blade::render('<x-muni::nav-grupo titulo="Operaciones">contenido</x-muni::nav-grupo>');

    expect(preg_match('/<button[^>]*type="button"/', $html))->toBe(1, 'el disparador es un <button> real: Enter y Espacio salen gratis');

    preg_match('/<button[^>]*id="([^"]+)"[^>]*aria-controls="([^"]+)"/', $html, $m);
    expect($m)->not->toBeEmpty('el botón necesita id y aria-controls');

    [, $btnId, $panelId] = $m;

    expect(preg_match('/<div[^>]*id="'.preg_quote($panelId, '/').'"/', $html))->toBe(1, 'aria-controls apunta a un elemento que existe');
    expect(preg_match('/<div[^>]*id="'.preg_quote($panelId, '/').'"[^>]*role="group"/', $html))->toBe(1, 'el panel se anuncia como grupo');
    expect(str_contains($html, 'aria-labelledby="'.$btnId.'"'))->toBeTrue('y toma su nombre del disparador');
    expect(preg_match('/aria-expanded="(true|false)"/', $html))->toBe(1, 'el estado se declara siempre');
    expect(preg_match('/<svg[^>]*aria-hidden="true"/', $html))->toBe(1, 'el chevrón es decorativo');
});

it('los ids salen de una prop y no cambian entre renders', function () {
    $uno = Blade::render('<x-muni::nav-grupo titulo="Operaciones">x</x-muni::nav-grupo>');
    $dos = Blade::render('<x-muni::nav-grupo titulo="Operaciones">x</x-muni::nav-grupo>');

    expect($uno)->toBe($dos, 'el mismo título rinde el mismo HTML: nada de uniqid()');

    foreach (['nav-menu', 'nav-grupo'] as $componente) {
        expect(str_contains(fuenteSinComentarios($componente), 'uniqid'))->toBeFalse("«{$componente}» no puede usar uniqid()");
    }

    $propio = Blade::render('<x-muni::nav-grupo titulo="Operaciones" id="menu-seguridad">x</x-muni::nav-grupo>');
    expect(str_contains($propio, 'menu-seguridad-btn'))->toBeTrue('el consumidor puede imponer el suyo');
});

it('el contador dice QUÉ son doce', function () {
    $html = menuRendido(arbolDeEjemplo());

    expect(preg_match('/aria-hidden="true"[^>]*>12</', $html))->toBe(1, 'el número suelto no se anuncia dos veces');
    expect(str_contains($html, '12 solicitudes esperando en el mesón'))->toBeTrue('el lector tiene que oír de qué son doce');
});

it('ningún texto del anfitrión entra en una expresión de Alpine', function () {
    // El apóstrofo escapado a &#039; lo decodifica el navegador DENTRO del valor
    // del atributo y descuadra la expresión: Alpine tumba la página entera.
    $etiqueta = "Cámaras del Río O'Higgins";
    $html = menuRendido([
        ['tipo' => 'grupo', 'etiqueta' => $etiqueta, 'items' => [
            ['etiqueta' => "Ronda O'Higgins", 'href' => '/ronda'],
        ]],
    ]);

    preg_match_all('/\s(x-[a-z-]+|:[a-z-]+|@[a-z.-]+)="([^"]*)"/i', $html, $m, PREG_SET_ORDER);

    foreach ($m as [, $atributo, $valor]) {
        expect(str_contains($valor, '&#039;') || str_contains($valor, 'Higgins'))->toBeFalse(
            "«{$atributo}» lleva texto del anfitrión dentro de una expresión de Alpine"
        );
    }

    expect(str_contains($html, 'Higgins'))->toBeTrue('el rótulo sí se pinta, como contenido escapado');
});

it('respeta el contrato de color, foco y movimiento del paquete', function () {
    foreach (['nav-menu', 'nav-grupo'] as $componente) {
        $fuente = fuenteSinComentarios($componente);

        preg_match_all('/<style>(.*?)<\/style>/s', $fuente, $bloques);
        $css = implode("\n", $bloques[1] ?? []);

        // Cero colores literales: con tokens de doble rama, la contraparte oscura
        // viene gratis (DESIGN §3 y §10).
        expect(preg_match('/#[0-9a-fA-F]{3,8}\b(?![^(]*\))/', $css))
            ->toBe(0, "«{$componente}» escribe un color literal fuera de los respaldos de var()");
        expect(preg_match('/\b(rgba?|hsla?)\(/', $css))->toBe(0, "«{$componente}» escribe un color literal");

        // El foco es un outline; la box-shadow se pierde dentro de Filament.
        expect(preg_match('/:focus-visible\s*\{[^}]*outline:\s*3px solid var\(--muni-focus, var\(--muni-accent, #767676\)\)/', $css))
            ->toBe(1, "«{$componente}» no declara el indicador de foco canónico");

        // Toda transición sale del token: es lo que apaga el movimiento bajo
        // prefers-reduced-motion sin escribir una media query.
        preg_match_all('/transition:[^;]+;/', $css, $transiciones);
        foreach ($transiciones[0] ?? [] as $t) {
            expect(preg_match('/\b\d+(\.\d+)?m?s\b/', str_replace('0s', '', $t)))
                ->toBe(0, "«{$componente}» anima con una duración fija: «{$t}»");
            expect(str_contains($t, 'var(--muni-dur)'))->toBeTrue("«{$componente}» tiene una transición sin var(--muni-dur)");
        }
    }
});

it('el disparador del grupo cumple el área táctil mínima', function () {
    $css = fuenteSinComentarios('nav-grupo');

    expect(preg_match('/\.muni-navg__btn\s*\{[^}]*min-height:\s*(\d+)px/', $css, $m))->toBe(1, 'el botón declara alto mínimo');
    expect((int) $m[1])->toBeGreaterThanOrEqual(24, 'WCAG 2.2 AA 2.5.8: 24x24 como piso');
});

it('no usa directivas exclusivas de un major de Livewire', function () {
    foreach (['nav-menu', 'nav-grupo'] as $componente) {
        $fuente = fuenteSinComentarios($componente);

        foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibido) {
            expect(str_contains($fuente, $prohibido))->toBeFalse(
                "«{$componente}» usa {$prohibido}: el paquete se instala en Livewire 3 y en Livewire 4"
            );
        }
    }
});
