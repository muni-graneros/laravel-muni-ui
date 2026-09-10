<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA AYUDA BREVE
|--------------------------------------------------------------------------
|
| `<x-muni::tooltip>` llegó al paquete decorativo y sin una sola prueba. Lo que
| se le midió, en orden de gravedad:
|
| 1. PATRÓN EQUIVOCADO POR DEFECTO. La burbuja llevaba `role="tooltip"` sin id
|    ni asociación: para un lector de pantalla el texto no existía. Pero copiar
|    el `aria-describedby` de Radix tampoco era la respuesta: en un botón de
|    solo icono el texto del tooltip ES el nombre del control, y con
|    `aria-label="Anular giro"` más un `aria-describedby` al MISMO texto el
|    lector anuncia «Anular giro, Anular giro». La APG reserva `describedby`
|    para cuando el tooltip AGREGA información que el nombre no tiene. Por eso
|    el defecto es `aria-hidden="true"` —la burbuja repite en pantalla lo que ya
|    dice el `aria-label` del disparador— y la asociación es opt-in.
| 2. LA ASOCIACIÓN NO SE INYECTA DESDE JS. En Livewire 3 y 4 el morph compara
|    con el HTML del servidor y BORRA en silencio los atributos que JS agregó
|    dentro de una fila con `wire:key`. El componente expone un `id` y el
|    consumidor escribe el `aria-describedby` en su botón, en el servidor.
| 3. WCAG 2.2 AA 1.4.13 (contenido al pasar el puntero o al enfocar): fallaba
|    los dos primeros requisitos. No cerraba con Esc (descartable) y llevaba
|    `pointer-events:none`, así que el puntero no podía entrar en la burbuja
|    para leerla o seleccionarla (hovereable).
| 4. ATRIBUTO DUPLICADO EN SILENCIO. Imprimía `style="…"` y después
|    `{{ $attributes }}` crudo: un `style` del consumidor salía como segundo
|    atributo `style` y el navegador descarta el duplicado sin decir nada.
| 5. RECORTE Y DESBORDAMIENTO. `white-space:nowrap` sin tope de ancho se sale de
|    la pantalla del celular del vecino, y dentro de `<x-muni::data-table>` el
|    `overflow:auto` del envoltorio recortaba la burbuja.
| 6. El respaldo de `placement` caía a `bottom` aunque el defecto de `@props`
|    fuera `top`: un valor inválido movía la burbuja al lado contrario.
|
| Se comprueba sobre el HTML renderizado y sobre el fuente del componente, con
| `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento de
| `toContain()` es otra cadena a buscar, no un mensaje, y la aserción se
| desactiva sin avisar.
*/

/** El `tooltip.blade.php` crudo. */
function fuenteAyudaBreve(): string
{
    return file_get_contents(__DIR__.'/../resources/views/components/tooltip.blade.php');
}

/** El fuente sin comentarios de Blade ni de CSS/JS: solo lo que se emite. */
function fuenteAyudaBreveSinComentarios(): string
{
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', fuenteAyudaBreve());

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El contenido de los bloques `<style>` del componente, sin comentarios. */
function cssAyudaBreve(): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteAyudaBreve(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/**
 * El contenido del bloque `@supports` del anclaje CSS, sin comentarios.
 *
 * Se recorta contando llaves y no con una expresión regular: el bloque tiene
 * reglas anidadas, y un `.*` codicioso se comería también el bloque de impresión
 * que viene después.
 */
function bloqueAnclaAyudaBreve(): string
{
    $css = cssAyudaBreve();
    $inicio = strpos($css, '@supports');

    if ($inicio === false) {
        return '';
    }

    $abre = strpos($css, '{', $inicio);
    $nivel = 0;

    for ($i = $abre; $i < strlen($css); $i++) {
        $nivel += $css[$i] === '{' ? 1 : ($css[$i] === '}' ? -1 : 0);

        if ($nivel === 0) {
            return substr($css, $abre + 1, $i - $abre - 1);
        }
    }

    return '';
}

/** El CSS del componente SIN el bloque del anclaje: lo que ve la rama de respaldo. */
function cssAyudaBreveSinAncla(): string
{
    $ancla = bloqueAnclaAyudaBreve();

    return $ancla === '' ? cssAyudaBreve() : str_replace($ancla, '', cssAyudaBreve());
}

/** El HTML del componente con los atributos y el disparador dados. */
function ayudaBreveHtml(string $atributos = 'text="Anular giro"', string $slot = '<button type="button" aria-label="Anular giro">X</button>'): string
{
    return Blade::render("<x-muni::tooltip {$atributos}>{$slot}</x-muni::tooltip>");
}

/** La etiqueta de apertura de la burbuja dentro del HTML dado. */
function burbujaAyudaBreve(string $html): string
{
    preg_match('/<span[^>]*muni-tt__bubble[^>]*>/s', $html, $m);

    return $m[0] ?? '';
}

/**
 * Los valores de todos los atributos de Alpine del HTML dado, ya decodificados
 * como los ve el navegador.
 *
 * @return array<int, string>
 */
function expresionesAlpineAyudaBreve(string $html): array
{
    preg_match_all('/\s(?:x-[a-z0-9.:-]+|@[a-z0-9.:-]+|:[a-z0-9-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

/*
|--------------------------------------------------------------------------
| 1. El patrón por defecto: la burbuja no habla
|--------------------------------------------------------------------------
*/

it('por defecto la burbuja sale aria-hidden y sin rol', function () {
    $burbuja = burbujaAyudaBreve(ayudaBreveHtml());

    expect($burbuja)->not->toBe('', 'No se encontró la burbuja: el barrido está roto.');

    expect(str_contains($burbuja, 'aria-hidden="true"'))->toBeTrue(
        'La burbuja no va aria-hidden por defecto: repetir en `aria-describedby` el mismo texto '.
        'que el `aria-label` del botón hace que el lector anuncie «Anular giro, Anular giro».'
    );

    expect(str_contains($burbuja, 'role="tooltip"'))->toBeFalse(
        'La burbuja oculta al árbol de accesibilidad no debe además declarar un rol: '.
        'el rol solo tiene sentido cuando el texto agrega información y se asocia.'
    );

    expect(str_contains(ayudaBreveHtml(), 'Anular giro'))->toBeTrue(
        'El texto no está en el HTML del servidor: la ayuda nace muda hasta que arranca Alpine.'
    );
});

it('la regla de uso queda escrita en el componente', function () {
    $fuente = fuenteAyudaBreve();

    expect(str_contains($fuente, 'nunca es el único portador'))->toBeTrue(
        'Falta la regla escrita: si se arregla la asociación sin decir que el nombre accesible '.
        'va en el `aria-label` del disparador, se seguirá usando mal en la tablet de terreno, '.
        'donde no hay puntero ni foco cómodo.'
    );

    expect(str_contains($fuente, 'aria-label'))->toBeTrue(
        'La regla no nombra dónde va el nombre accesible del botón de icono.'
    );

    expect(str_contains($fuente, 'aria-describedby'))->toBeTrue(
        'El componente no documenta que el `aria-describedby` lo escribe el consumidor en su botón.'
    );
});

/*
|--------------------------------------------------------------------------
| 2. La asociación es opt-in y viaja por el id, no por JS
|--------------------------------------------------------------------------
*/

it('el opt-in de descripción emite el id y el rol', function () {
    foreach (['describe' => 'describe', 'as="description"' => 'as="description"'] as $etiqueta => $atributo) {
        $burbuja = burbujaAyudaBreve(ayudaBreveHtml($atributo.' id="tt-anular" text="Anula el giro y avisa al contribuyente"'));

        expect(str_contains($burbuja, 'id="tt-anular"'))->toBeTrue(
            "Con «{$etiqueta}» la burbuja no lleva el id que el consumidor apuntará con aria-describedby."
        );

        expect(str_contains($burbuja, 'role="tooltip"'))->toBeTrue(
            "Con «{$etiqueta}» la burbuja no declara role=tooltip."
        );

        expect(str_contains($burbuja, 'aria-hidden'))->toBeFalse(
            "Con «{$etiqueta}» la burbuja sigue oculta al árbol: el aria-describedby del botón ".
            'apuntaría a un texto que el lector no usa como descripción.'
        );
    }
});

it('el id de la burbuja es determinista y no sale de uniqid', function () {
    // Sin comentarios: el propio componente NOMBRA uniqid() para explicar por qué
    // no lo usa, y con la prosa dentro la aserción daría un falso rojo.
    expect(str_contains(fuenteAyudaBreveSinComentarios(), 'uniqid'))->toBeFalse(
        'Un id con uniqid() cambia en cada render, rompe el aria-describedby del consumidor y '.
        'ensucia el diffing de Livewire (DESIGN §10).'
    );

    expect(ayudaBreveHtml('describe text="Anula el giro"'))
        ->toBe(ayudaBreveHtml('describe text="Anula el giro"'),
            'Dos renders con las mismas props dan HTML distinto: hay un id que no es determinista.'
        );
});

it('el componente no inyecta el aria-describedby desde JS sobre el slot', function () {
    $fuente = fuenteAyudaBreveSinComentarios();

    foreach (['setAttribute', 'removeAttribute', ':aria-describedby', 'x-bind:aria-describedby'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El componente usa «{$prohibido}»: en Livewire 3 y 4 el morph compara con el HTML del ".
            'servidor y borra en silencio los atributos que JS agregó dentro de una fila con wire:key.'
        );
    }

    // Y tampoco lo emite él: el atributo lo escribe el consumidor en su botón.
    expect((bool) preg_match('/\saria-describedby\s*=/', ayudaBreveHtml('describe id="tt-anular"')))->toBeFalse(
        'El componente emite aria-describedby por su cuenta: no puede saber cuál de los elementos '.
        'del slot es el disparador, y el que elija será el equivocado.'
    );
});

/*
|--------------------------------------------------------------------------
| 3. WCAG 2.2 AA 1.4.13: descartable y hovereable
|--------------------------------------------------------------------------
*/

it('la ayuda se cierra con Esc', function () {
    expect((bool) preg_match('/@keydown\.window\.escape="[^"]*show\s*=\s*false/', fuenteAyudaBreve()))->toBeTrue(
        'Sin Esc la burbuja tapa el contenido de abajo y no hay forma de descartarla sin mover el '.
        'puntero (WCAG 2.2 AA 1.4.13, requisito «descartable»).'
    );
});

it('el puntero puede entrar en la burbuja', function () {
    expect(str_contains(fuenteAyudaBreveSinComentarios(), 'pointer-events:none'))->toBeFalse(
        'La burbuja lleva pointer-events:none: el puntero no puede entrar a leerla ni a seleccionar '.
        'su texto (WCAG 2.2 AA 1.4.13, requisito «hovereable»).'
    );

    expect((bool) preg_match('/<span[^>]*muni-tt__bubble[^>]*@mouseenter=/s', ayudaBreveHtml()))->toBeTrue(
        'La burbuja no escucha @mouseenter: al pasar el puntero del disparador a la burbuja se '.
        'cierra en el camino.'
    );
});

/*
|--------------------------------------------------------------------------
| 4. Nada de atributos duplicados
|--------------------------------------------------------------------------
*/

it('el style del consumidor se fusiona en vez de duplicarse', function () {
    $fuente = fuenteAyudaBreveSinComentarios();

    expect(str_contains($fuente, "'style' => 'position:relative;display:inline-flex;'"))->toBeTrue(
        'El estilo base del envoltorio no va por ->merge(): escrito como atributo suelto antes del '.
        'derrame produce dos `style` y el navegador descarta el segundo en silencio.'
    );

    expect((bool) preg_match('/\{\{\s*\$attributes\s*\}\}/', $fuente))->toBeFalse(
        'Queda un derrame crudo `{{ $attributes }}`: junto al style base vuelve el atributo duplicado.'
    );

    $html = ayudaBreveHtml('text="Anular giro" style="margin-left:6px;" class="mi-clase"');

    // Se cuenta sobre el HTML entero: el envoltorio es el único que lleva
    // `style`, así que dos apariciones son el atributo duplicado de siempre.
    expect(substr_count($html, 'style='))->toBe(1,
        'Sale más de un atributo style: el navegador se queda con el primero y descarta el resto '.
        'sin avisar.'
    );

    preg_match('/style="([^"]*)"/', $html, $m);
    $estilo = $m[1] ?? '';

    expect(str_contains($estilo, 'margin-left:6px;'))->toBeTrue(
        'El style del consumidor se perdió al fusionar.'
    );

    expect(str_contains($estilo, 'position:relative'))->toBeTrue(
        'El style base del componente se perdió al fusionar: `merge()` reemplaza salvo en class y style.'
    );

    expect(str_contains($html, 'mi-clase'))->toBeTrue(
        'La clase del consumidor se perdió al fusionar.'
    );
});

/*
|--------------------------------------------------------------------------
| 5. Recorte y desbordamiento
|--------------------------------------------------------------------------
*/

it('el texto largo envuelve y tiene tope de ancho', function () {
    $css = cssAyudaBreve();

    expect((bool) preg_match('/white-space\s*:\s*nowrap/i', fuenteAyudaBreveSinComentarios()))->toBeFalse(
        'La burbuja sigue en `nowrap`: un texto largo se sale de la pantalla del celular del vecino.'
    );

    expect((bool) preg_match('/max-width\s*:\s*min\(\s*28ch\s*,\s*90vw\s*\)/i', $css))->toBeTrue(
        'Falta `max-width:min(28ch,90vw)`: sin tope, la burbuja crece hasta donde le dé el texto.'
    );

    expect((bool) preg_match('/white-space\s*:\s*normal/i', $css))->toBeTrue(
        'Falta `white-space:normal`: con el tope de ancho pero sin envolver, el texto se recorta.'
    );
});

it('la burbuja escapa del contenedor con scroll', function () {
    $css = cssAyudaBreve();

    expect((bool) preg_match('/\.muni-tt__bubble\s*\{[^}]*position\s*:\s*fixed/s', $css))->toBeTrue(
        'La burbuja sigue en `position:absolute`: dentro de <x-muni::data-table> el overflow:auto '.
        'del envoltorio la recorta y solo se ve el trozo que cabe en el marco.'
    );

    // El anchor positioning es MEJORA, no el mecanismo: Safari y Firefox no lo
    // tienen y ahí la burbuja quedaría pegada arriba a la izquierda (DESIGN §10).
    $sinSupports = (string) preg_replace('/@supports[^{]*\{.*\}/s', '', $css);

    foreach (['anchor-name', 'position-anchor', 'position-area', 'position-try'] as $moderno) {
        expect(str_contains($sinSupports, $moderno))->toBeFalse(
            "«{$moderno}» está fuera de un @supports: el CSS moderno va como mejora progresiva."
        );
    }
});

/*
|--------------------------------------------------------------------------
| 5 bis. D2: el anclaje CSS no puede encerrar la burbuja en el ancho del botón
|--------------------------------------------------------------------------
|
| Medido en Chromium 151 y Firefox 153 el 2026-09-10: con
| `position-area: top center` la región elegida pasa a ser el bloque contenedor
| de la burbuja, o sea el ancho exacto del disparador, y el `max-width` de la
| regla base queda sin efecto. Sobre un botón de icono de 25 px la burbuja salía
| de 25x367 px, una letra por línea. Los dos motores entran hoy por esta rama.
|
| Lo que sigue fija las dos declaraciones que sueltan la caja. La medición viva
| vive en docs/VERIFICACION-NAVEGADOR.md, fila D2; esto es el candado de PHP.
*/

it('el anclaje CSS no encierra la burbuja en el ancho del disparador', function () {
    $ancla = bloqueAnclaAyudaBreve();

    expect($ancla)->not->toBe('',
        'No se encontró el bloque @supports del anclaje: el barrido está roto o la mejora desapareció.'
    );

    preg_match_all('/position-area\s*:\s*([^;}]+)/i', $ancla, $m);

    expect(count($m[1]))->toBe(4,
        'No hay exactamente cuatro reglas `position-area`, una por cada `placement` de la lista cerrada.'
    );

    foreach ($m[1] as $valor) {
        $valor = trim($valor);

        expect((bool) preg_match('/\bcenter\b/i', $valor))->toBeFalse(
            "«position-area: {$valor}» usa `center` en el eje cruzado: esa celda de la rejilla ES el ".
            'disparador, así que pasa a ser el bloque contenedor de la burbuja y el `max-width` no '.
            'puede hacer nada. Medido: 25x367 px con las palabras partidas letra a letra (D2).'
        );

        expect((bool) preg_match('/\bspan-all\b/i', $valor))->toBeTrue(
            "«position-area: {$valor}» no abre el eje cruzado con `span-all`: sin él la burbuja no ".
            'se centra sobre el ancla (`anchor-center`) ni escapa de su ancho.'
        );
    }

    foreach (['top', 'bottom', 'left', 'right'] as $lado) {
        expect((bool) preg_match(
            '/muni-tt__bubble--'.$lado.'\s*\{[^}]*position-area\s*:\s*'.$lado.'\s+span-all/i', $ancla
        ))->toBeTrue("El lado «{$lado}» perdió su `position-area: {$lado} span-all`.");
    }

    expect((bool) preg_match('/width\s*:\s*max-content/i', $ancla))->toBeTrue(
        'Falta `width: max-content` dentro del @supports: `span-all` suelta el eje cruzado, pero en '.
        '`left` y `right` la franja lateral junto al borde de la pantalla mide unos 60 px y ahí la '.
        'burbuja se vuelve a encoger. Con la caja suelta, `position-try-fallbacks` sí detecta el '.
        'desbordamiento y voltea al lado opuesto.'
    );

    expect((bool) preg_match('/position-try-fallbacks\s*:[^;}]*flip-inline/i', $ancla))->toBeTrue(
        'Sin `flip-inline` una ayuda `right` pegada al borde derecho no tiene a dónde voltear.'
    );
});

it('el ancho suelto no se filtra fuera del anclaje y el tope sigue valiendo en el respaldo', function () {
    $fuera = cssAyudaBreveSinAncla();

    expect((bool) preg_match('/width\s*:\s*max-content/i', $fuera))->toBeFalse(
        '`width: max-content` está fuera del @supports: en la rama de respaldo Alpine mide la '.
        'burbuja y la recoloca, y un ancho intrínseco sin bloque contenedor que lo frene se sale '.
        'de la pantalla antes de que el JS llegue a medirla (DESIGN §10).'
    );

    expect((bool) preg_match('/max-width\s*:\s*min\(\s*28ch\s*,\s*90vw\s*\)/i', $fuera))->toBeTrue(
        'El tope de ancho salió de la regla base: tiene que valer en las DOS ramas, porque es lo '.
        'único que hace envolver el texto largo cuando la caja ya está suelta.'
    );
});

it('el componente ya no afirma que Firefox no sabe anclar', function () {
    $fuente = fuenteAyudaBreve();

    expect((bool) preg_match('/Safari y Firefox no lo tienen/i', $fuente))->toBeFalse(
        'Vuelve a estar la afirmación falsa: Firefox 153 devuelve `true` en `CSS.supports()` para '.
        '`anchor-name`, `anchor-scope` y `position-area`. Creerla es lo que dejó la rama moderna '.
        'rota en producción, porque nadie pensaba que se usara.'
    );

    expect(str_contains($fuente, 'CSS.supports'))->toBeTrue(
        'El componente no dice cómo se comprueba qué motor entra por la rama moderna: la próxima '.
        'afirmación sobre soporte de navegadores se escribirá otra vez de memoria.'
    );

    expect(str_contains($fuente, 'Firefox 153'))->toBeTrue(
        'Falta la medición fechada del soporte real. Sin versión medida, el comentario es una '.
        'creencia, no un dato.'
    );
});

/*
|--------------------------------------------------------------------------
| 6. El respaldo de `placement`
|--------------------------------------------------------------------------
*/

it('un placement inválido cae al mismo lado que el defecto de props', function () {
    expect(ayudaBreveHtml('text="Anular giro" placement="arriba-del-todo"'))
        ->toBe(ayudaBreveHtml('text="Anular giro" placement="top"'),
            'Un `placement` que no existe cae a un lado distinto del defecto de @props («top»): '.
            'el mismo componente se dibuja en dos sitios según cómo lo llamen.'
        );

    foreach (['top', 'bottom', 'left', 'right'] as $lado) {
        expect(str_contains(ayudaBreveHtml('text="x" placement="'.$lado.'"'), 'muni-tt__bubble--'.$lado))->toBeTrue(
            "El lado «{$lado}» no llega al HTML."
        );
    }
});

/*
|--------------------------------------------------------------------------
| 7. Impresión
|--------------------------------------------------------------------------
*/

it('una ayuda abierta no se imprime', function () {
    $css = cssAyudaBreve();

    expect((bool) preg_match('/@media\s+print\s*\{(.*)\}/s', $css, $m))->toBeTrue(
        'El componente no tiene reglas de impresión: una burbuja abierta al mandar a imprimir sale '.
        'estampada sobre el acta.'
    );

    $print = $m[1] ?? '';

    expect((bool) preg_match('/muni-tt__bubble[^}]*display\s*:\s*none/s', $print))->toBeTrue(
        'La regla de impresión no oculta la burbuja: en papel una ayuda flotante tapa el texto de abajo.'
    );
});

/*
|--------------------------------------------------------------------------
| 8. Inyección en las expresiones de Alpine
|--------------------------------------------------------------------------
*/

it('un texto con apóstrofo no descuadra ninguna expresión de Alpine', function () {
    // El defecto que ya tumbó a `file-dropzone`: Blade escapa el apóstrofo a
    // `&#039;`, el navegador lo decodifica DENTRO del valor del atributo y la
    // expresión queda con las comillas descuadradas. Alpine no se cae solo: se
    // lleva por delante el de la página ENTERA.
    $html = Blade::render(
        '<x-muni::tooltip :text="$t"><button type="button">X</button></x-muni::tooltip>',
        ['t' => "Anula el giro de la señora O'Higgins"]
    );

    $rotas = [];

    foreach (expresionesAlpineAyudaBreve($html) as $expr) {
        if (substr_count(str_replace("\\'", '', $expr), "'") % 2 !== 0) {
            $rotas[] = $expr;
        }
    }

    expect($rotas)->toBe([], sprintf(
        'Estas expresiones de Alpine quedan con las comillas simples descuadradas y tumban el '.
        'Alpine de la página entera: %s', implode(' | ', $rotas)
    ));

    // La defensa de fondo: el texto del anfitrión no puede estar DENTRO de una
    // expresión que Alpine evalúa, porque un texto con `'+fetch(…)+'` se ejecuta.
    $marca = 'ZZ-AYUDA-DE-PRUEBA-ZZ';
    $conMarca = Blade::render(
        '<x-muni::tooltip :text="$t"><button type="button">X</button></x-muni::tooltip>',
        ['t' => $marca]
    );

    $culpables = array_values(array_filter(
        expresionesAlpineAyudaBreve($conMarca),
        fn (string $expr) => str_contains($expr, $marca)
    ));

    expect($culpables)->toBe([], sprintf(
        'El texto del anfitrión aparece dentro de una expresión de Alpine, que es código '.
        'evaluado: %s', implode(' | ', $culpables)
    ));
});

/*
|--------------------------------------------------------------------------
| 9. El contrato del paquete
|--------------------------------------------------------------------------
*/

it('no escribe un solo color literal', function () {
    $codigo = str_replace('#767676', '', fuenteAyudaBreveSinComentarios());

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $codigo, $colores);

    expect($colores[0] ?? [])->toBe([],
        'Escribe colores literales, así que ningún municipio puede cambiarlos redefiniendo '.
        'tokens (DESIGN §1): '.implode(' | ', $colores[0] ?? [])
    );
});

it('el bloque de estilos viaja dentro del componente y el movimiento usa el token', function () {
    expect((bool) preg_match('#@once\s*(?:\R|\s)*<style>#', fuenteAyudaBreve()))->toBeTrue(
        'El bloque <style> no está dentro de @once: se repite una vez por cada ayuda de la página.'
    );

    expect(str_contains(cssAyudaBreve(), 'var(--muni-dur)'))->toBeTrue(
        'La transición no usa var(--muni-dur), que baja a 0ms con prefers-reduced-motion: una '.
        'duración fija ignora la preferencia (DESIGN §6).'
    );

    expect((bool) preg_match('/transition[^;]*\b\d+(?:\.\d+)?m?s\b/', cssAyudaBreve()))->toBeFalse(
        'Hay una duración literal en una transición: con movimiento reducido sigue animando.'
    );

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains(fuenteAyudaBreve(), $prohibida))->toBeFalse(
            "Usa «{$prohibida}», que no existe en los dos majors de Livewire que el paquete soporta."
        );
    }
});

/*
|--------------------------------------------------------------------------
| 10. Retrocompatibilidad
|--------------------------------------------------------------------------
*/

it('las llamadas que ya existen siguen funcionando igual', function () {
    // La firma vieja: text + placement + un disparador en el slot. Hay
    // consumidores escritos así y el cambio es ADITIVO.
    $html = ayudaBreveHtml('text="Imprimir orden de giro" placement="left"');

    expect(str_contains($html, 'Imprimir orden de giro'))->toBeTrue('El texto ya no se pinta.');
    expect(str_contains($html, '<button type="button" aria-label="Anular giro">X</button>'))->toBeTrue(
        'El disparador del slot ya no se emite tal cual: el consumidor perdió su botón.'
    );
    expect(str_contains($html, 'muni-tt__bubble--left'))->toBeTrue('La colocación vieja dejó de aplicarse.');

    // Sin texto y sin slot no revienta: `<x-muni::tooltip />` está en el registro.
    expect(trim(Blade::render('<x-muni::tooltip />')))->not->toBe('',
        'La llamada mínima del registro dejó de emitir nada.'
    );
});
