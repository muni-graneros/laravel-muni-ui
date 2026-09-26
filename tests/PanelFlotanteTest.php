<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL PANEL FLOTANTE (`<x-muni::popover>`)
|--------------------------------------------------------------------------
|
| Hoy un panel de filtros se arma con `dropdown`, que declara
| `aria-haspopup="menu"` y `role="menu"`. Meter campos, casillas o un
| calendario dentro de un `role=menu` es marcado inválido —los hijos de un menú
| deben ser `menuitem*`— y el lector anuncia un menú sin elementos. La otra
| salida, `modal`, bloquea la pantalla entera para marcar dos casillas.
|
| La corrección exigida de la ficha `popover` (docs/GAP-ANALYSIS.md) cambia la
| BASE TÉCNICA y manda sobre la ficha: se construye sobre el atributo NATIVO
| `popover` + `popovertarget`. El top-layer nativo quita el recorte dentro de
| una tabla con overflow, evita `x-teleport` —que es lo que hace que Livewire
| huerfane el panel del `modal` al remorfear— y regala light-dismiss, Escape y
| retorno de foco al invocador SIN una línea de JS.
|
| Lo que este archivo vigila, punto por punto:
|
|  1. `popover` y `popovertarget` presentes y apuntándose entre sí.
|  2. `aria-expanded` EXPLÍCITO en el disparador: el mapeo implícito de
|     `popovertarget` no es parejo entre navegadores.
|  3. Ni `role="menu"` ni `role="dialog"`: es el patrón disclosure.
|  4. El reset del UA stylesheet de `[popover]`, que trae
|     `position:fixed; inset:0; margin:auto; border:solid; padding:.25em`.
|  5. Un `@supports` de anchor positioning Y una vía que no lo necesita:
|     Firefox no lo tiene y nunca puede ser la única vía.
|  6. Una regla de impresión que lo oculta: estos sistemas imprimen mucho.
|  7. El slot por defecto acepta un `<form method="get">` entero, que es la
|     propiedad que `filter-bar` cuida —los filtros viven en la URL y las
|     descargas xlsx/csv los arrastran— y que un panel con estado en Alpine
|     perdería.
|  8. Cero colores literales, cero `uniqid(`, cero directivas de un solo major.
|
| Ojo con Pest: `expect($x)->toContain($y, 'mensaje')` NO acepta mensaje —el
| segundo argumento es otra aguja y la aserción se apaga sin avisar—, así que
| todo va con `expect(bool)->toBeTrue('mensaje')`.
*/

/** Ruta del componente. */
function rutaPanelFlotante(): string
{
    return __DIR__.'/../resources/views/components/popover.blade.php';
}

/** El fuente crudo, o cadena vacía si todavía no existe. */
function fuentePanelFlotante(): string
{
    $ruta = rutaPanelFlotante();

    return file_exists($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * El fuente SIN comentarios CSS ni comentarios Blade.
 *
 * Sin esto, buscar un patrón matchea la prosa que explica por qué ese patrón
 * está prohibido, y la prueba pasa o falla por el texto de una explicación.
 */
function codigoPanelFlotante(): string
{
    return (string) preg_replace(
        ['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'],
        '',
        fuentePanelFlotante()
    );
}

/** El CSS que el componente lleva dentro de su bloque de estilos. */
function cssPanelFlotante(): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuentePanelFlotante(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1] ?? []));
}

/** El HTML servido del componente. */
function panelFlotanteHtml(string $atributos = '', string $contenido = 'Contenido'): string
{
    return Blade::render("<x-muni::popover {$atributos}>{$contenido}</x-muni::popover>");
}

/**
 * Parte un CSS en lo que está DENTRO de algún `@supports` y lo que está fuera,
 * contando llaves para no cortar en la primera que aparezca.
 *
 * @return array{dentro: string, fuera: string}
 */
function separaSupportsDelPanel(string $css): array
{
    $dentro = '';
    $fuera = '';
    $i = 0;
    $largo = strlen($css);

    while ($i < $largo) {
        $pos = strpos($css, '@supports', $i);

        if ($pos === false) {
            $fuera .= substr($css, $i);
            break;
        }

        $fuera .= substr($css, $i, $pos - $i);

        $apertura = strpos($css, '{', $pos);

        if ($apertura === false) {
            break;
        }

        $nivel = 0;
        $j = $apertura;

        for (; $j < $largo; $j++) {
            if ($css[$j] === '{') {
                $nivel++;
            } elseif ($css[$j] === '}') {
                $nivel--;

                if ($nivel === 0) {
                    break;
                }
            }
        }

        $dentro .= substr($css, $apertura, $j - $apertura + 1);
        $i = $j + 1;
    }

    return ['dentro' => $dentro, 'fuera' => $fuera];
}

/** El cuerpo de la primera regla cuyo selector contiene $aguja. */
function reglaDelPanel(string $css, string $aguja): string
{
    preg_match_all('/([^{}@]+)\{([^{}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    foreach ($reglas as [, $selector, $cuerpo]) {
        if (str_contains($selector, $aguja)) {
            return $cuerpo;
        }
    }

    return '';
}

/** El bloque completo de una at-rule (`@media print`, por ejemplo). */
function bloqueAtRuleDelPanel(string $css, string $ancla): string
{
    $pos = strpos($css, $ancla);

    if ($pos === false) {
        return '';
    }

    $apertura = strpos($css, '{', $pos);

    if ($apertura === false) {
        return '';
    }

    $nivel = 0;
    $largo = strlen($css);

    for ($j = $apertura; $j < $largo; $j++) {
        if ($css[$j] === '{') {
            $nivel++;
        } elseif ($css[$j] === '}') {
            $nivel--;

            if ($nivel === 0) {
                return substr($css, $apertura, $j - $apertura + 1);
            }
        }
    }

    return '';
}

/** Las expresiones que Alpine EVALÚA en el HTML servido. */
function expresionesAlpineDelPanel(string $html): array
{
    preg_match_all('/\s(?:x-[a-z0-9.:-]+|@[a-z0-9.-]+|:[a-z0-9-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

it('el disparador abre el panel con el atributo NATIVO popover, y los dos se apuntan', function () {
    $html = panelFlotanteHtml('label="Filtros"');

    expect((bool) preg_match('/<button[^>]*\spopovertarget="([^"]+)"/', $html, $disparador))->toBeTrue(
        'El disparador no lleva `popovertarget`. Sin el invocador nativo no hay top-layer, '.
        'ni light-dismiss, ni Escape, ni retorno de foco gratis: todo eso habría que '.
        'reimplementarlo a mano y es justo lo que la corrección exigida descarta.'
    );

    $idPanel = $disparador[1];

    expect((bool) preg_match('/<[a-z-]+[^>]*\spopover="(auto|manual)"[^>]*>/', $html))->toBeTrue(
        'El panel no declara el atributo `popover`: sin él no sube al top-layer y se '.
        'recorta dentro de cualquier tabla con overflow.'
    );

    expect((bool) preg_match('/id="'.preg_quote($idPanel, '/').'"[^>]*\spopover=|popover="[^"]*"[^>]*id="'.preg_quote($idPanel, '/').'"/', $html))->toBeTrue(
        "El `popovertarget=\"{$idPanel}\"` no apunta a ningún elemento con `popover`: el ".
        'botón no abre nada.'
    );

    // El invocador declara la acción: `toggle` para que el mismo botón cierre.
    expect(str_contains($html, 'popovertargetaction="toggle"'))->toBeTrue(
        'El disparador no declara `popovertargetaction="toggle"`: el mismo botón tiene que '.
        'cerrar lo que abrió.'
    );
});

it('el disparador declara aria-expanded EXPLÍCITO y aria-controls', function () {
    $html = panelFlotanteHtml('label="Filtros"');

    expect((bool) preg_match('/<button[^>]*\saria-expanded="false"/', $html))->toBeTrue(
        'El disparador nace sin `aria-expanded="false"` en el HTML del servidor. El mapeo '.
        'implícito de `popovertarget` NO es parejo entre navegadores: donde no se mapea, el '.
        'lector anuncia un botón cualquiera y nadie sabe que hay algo que se abre.'
    );

    expect((bool) preg_match('/<button[^>]*\s(?:x-bind)?:aria-expanded=/', $html))->toBeTrue(
        'El `aria-expanded` no se sincroniza al abrir y cerrar: se queda en «false» para '.
        'siempre, que es peor que no ponerlo.'
    );

    preg_match('/<button[^>]*\spopovertarget="([^"]+)"/', $html, $m);

    expect((bool) preg_match('/<button[^>]*\saria-controls="'.preg_quote($m[1] ?? 'x', '/').'"/', $html))->toBeTrue(
        'El disparador no declara `aria-controls` apuntando al panel: es la mitad del '.
        'patrón disclosure.'
    );
});

it('el panel no es un menú ni un diálogo', function () {
    $html = panelFlotanteHtml('label="Filtros"', '<label><input type="checkbox" name="estado"> Pendientes</label>');
    $codigo = codigoPanelFlotante();

    foreach (['role="menu"', 'role="menuitem"', 'aria-haspopup'] as $prohibido) {
        expect(str_contains($html, $prohibido))->toBeFalse(
            "El panel emite `{$prohibido}`. Los hijos de un menú deben ser `menuitem*`: una ".
            'casilla o un campo ahí dentro es marcado inválido y el lector anuncia un menú '.
            'sin elementos. Ese es exactamente el defecto de `dropdown` que este componente '.
            'viene a resolver.'
        );

        expect(str_contains($codigo, $prohibido))->toBeFalse(
            "El fuente escribe `{$prohibido}` en algún camino condicional."
        );
    }

    foreach (['role="dialog"', 'aria-modal'] as $prohibido) {
        expect(str_contains($html, $prohibido))->toBeFalse(
            "El panel emite `{$prohibido}`. Mientras no sea modal —y no lo es: no bloquea la ".
            'página— el patrón es disclosure. Un `dialog` no modal anunciado como diálogo '.
            'promete una trampa de foco que no existe.'
        );
    }
});

it('el bloque de estilos desarma el UA stylesheet de [popover]', function () {
    /*
     * El UA trae `position:fixed; inset:0; margin:auto; border:solid; padding:.25em`,
     * que centra el panel en mitad de la pantalla con un marco negro. Las cuatro
     * declaraciones hay que pisarlas: si falta `inset`, el panel se planta en el
     * centro y el anclaje no se nota hasta que alguien lo abre en producción.
     */
    $cuerpo = reglaDelPanel(cssPanelFlotante(), 'muni-pop__panel');

    expect($cuerpo)->not->toBe('', 'No hay ninguna regla base para `.muni-pop__panel`.');

    $exigidas = [
        'inset' => '/(^|[;{\s])inset\s*:\s*auto/i',
        'margin' => '/(^|[;{\s])margin\s*:\s*0/i',
        'padding' => '/(^|[;{\s])padding\s*:/i',
        'border' => '/(^|[;{\s])border\s*:\s*1px\s+solid\s+var\(--muni-border\)/i',
        'position' => '/(^|[;{\s])position\s*:\s*fixed/i',
    ];

    foreach ($exigidas as $nombre => $patron) {
        expect((bool) preg_match($patron, $cuerpo))->toBeTrue(
            "La regla base del panel no pisa `{$nombre}` del UA stylesheet de `[popover]`. ".
            'Lo que se ve entonces es un recuadro centrado con marco del navegador, y nadie '.
            'lo descubre hasta abrirlo.'
        );
    }
});

it('el anclaje moderno va dentro de @supports y hay una vía que no lo necesita', function () {
    $css = cssPanelFlotante();
    $partes = separaSupportsDelPanel($css);
    $codigo = codigoPanelFlotante();

    expect((bool) preg_match('/@supports\s*\(\s*anchor-name\s*:/i', $css))->toBeTrue(
        'No hay ningún `@supports (anchor-name: …)`: o el anclaje moderno se usa sin guardia '.
        '—prohibido por DESIGN §10— o no se usa.'
    );

    // Lo moderno, DENTRO de la guardia y en ningún otro sitio.
    foreach (['anchor(', 'position-anchor', 'position-try'] as $moderno) {
        if (str_contains($css, $moderno)) {
            expect(str_contains($partes['dentro'], $moderno))->toBeTrue(
                "`{$moderno}` aparece fuera de `@supports`. Firefox no tiene anchor ".
                'positioning: ahí la declaración se descarta y el panel queda sin posición.'
            );

            expect(str_contains($partes['fuera'], $moderno))->toBeFalse(
                "`{$moderno}` está también fuera de `@supports`, que es lo mismo que no ".
                'tener guardia.'
            );
        }
    }

    // Y la vía que no lo necesita: posición fija + medición del disparador.
    expect((bool) preg_match('/(^|[;{\s])position\s*:\s*fixed/i', reglaDelPanel($partes['fuera'], 'muni-pop__panel')))->toBeTrue(
        'Fuera del `@supports` el panel no tiene `position:fixed`: en Firefox no quedaría '.
        'ninguna vía y el panel se pintaría donde el navegador quisiera.'
    );

    expect(str_contains($codigo, 'getBoundingClientRect'))->toBeTrue(
        'No hay medición del disparador con `getBoundingClientRect()`: el respaldo de '.
        'Firefox no existe.'
    );

    expect((bool) preg_match('/CSS\.supports\(\s*[\'"]anchor-name/i', $codigo))->toBeTrue(
        'El respaldo no se apaga donde el anclaje nativo sí existe: los dos pelearían por '.
        'las mismas coordenadas.'
    );
});

it('un panel abierto no se imprime', function () {
    // Estos sistemas imprimen mucho: una nómina con el panel de filtros dibujado
    // encima sale con la mitad de las filas tapadas.
    $bloque = bloqueAtRuleDelPanel(cssPanelFlotante(), '@media print');

    expect($bloque)->not->toBe(
        '',
        'El componente no trae ninguna regla `@media print`. Un panel abierto se imprime '.
        'encima del contenido, y estos sistemas imprimen todos los días.'
    );

    expect((bool) preg_match('/muni-pop__panel[^{}]*\{[^}]*display\s*:\s*none/is', $bloque))->toBeTrue(
        'La regla de impresión no oculta el panel.'
    );

    expect(str_contains($bloque, '!important'))->toBeTrue(
        'Sin `!important` la regla no gana: el UA pone `display:block` en `:popover-open` y '.
        'el panel abierto se imprime igual.'
    );
});

it('en móvil el panel es una hoja a ancho completo anclada abajo', function () {
    /*
     * En tablet y teléfono un panel anclado al borde derecho se «corre a la
     * izquierda» o se sale de la pantalla. Por breakpoint pasa a ocupar el ancho
     * completo pegado abajo, que es lo que hace cualquier hoja de acciones móvil.
     */
    $css = cssPanelFlotante();

    expect((bool) preg_match('/@media[^{]*max-width\s*:\s*\d+px/i', $css))->toBeTrue(
        'No hay ningún breakpoint: el panel se comporta igual en un teléfono que en un '.
        'escritorio de 1440 px.'
    );

    preg_match('/@media[^{]*max-width\s*:\s*\d+px[^{]*/i', $css, $m);
    $bloque = bloqueAtRuleDelPanel($css, $m[0]);
    $cuerpo = reglaDelPanel($bloque, 'muni-pop__panel');

    expect((bool) preg_match('/(^|[;{\s])inset\s*:\s*auto\s+0\s+0\s+0/i', $cuerpo))->toBeTrue(
        'En el breakpoint móvil el panel no se pega abajo a ancho completo (`inset:auto 0 0 0`).'
    );

    expect((bool) preg_match('/(^|[;{\s])(width|max-width)\s*:/i', $cuerpo))->toBeTrue(
        'El ancho fijo de escritorio sigue mandando en el teléfono.'
    );
});

it('el slot por defecto admite un formulario GET completo y lo rinde intacto', function () {
    /*
     * Es la propiedad que `filter-bar` cuida: los filtros viven en la URL, así que
     * la descarga xlsx/csv de la misma pantalla los arrastra. Un panel con el
     * estado en Alpine la perdería. Por eso el slot se rinde tal cual y el
     * componente NO abre un formulario propio: un `<form>` dentro de otro `<form>`
     * es marcado inválido y el navegador se come el interior.
     */
    $formulario = '<form method="get" action="/solicitudes">'
        .'<label for="pf-estado">Estado</label>'
        .'<select id="pf-estado" name="estado"><option value="pend">Pendientes</option></select>'
        .'<button type="submit">Aplicar</button>'
        .'</form>';

    $html = panelFlotanteHtml('label="Filtros"', $formulario);

    expect((bool) preg_match('/<form\s+method="get"\s+action="\/solicitudes">/', $html))->toBeTrue(
        'El formulario GET del slot no se rinde intacto.'
    );

    expect(substr_count($html, '<form'))->toBe(
        1,
        'El componente abre un formulario propio: anidado con el del consumidor, el '.
        'navegador descarta el interior y los filtros dejan de viajar en la URL.'
    );

    expect(str_contains($html, 'name="estado"'))->toBeTrue('El campo del slot desapareció.');
    expect(str_contains($html, 'type="submit"'))->toBeTrue('El botón de enviar del slot desapareció.');
});

it('el ancho no viaja como estilo en línea: el breakpoint móvil no podría ganarle', function () {
    /*
     * Medido en Chromium a 390 px: con `style="width:320px"` en el panel, la
     * regla del breakpoint (`width:auto`) NO se aplica —un estilo en línea gana
     * a cualquier hoja— y la hoja móvil salía de 320 px anclada abajo en vez de
     * a ancho completo. El ancho viaja como propiedad personalizada, que sí se
     * puede pisar desde la media query.
     */
    $html = panelFlotanteHtml('label="Filtros" width="320px"');

    expect((bool) preg_match('/<div[^>]*class="[^"]*muni-pop__panel[^"]*"[^>]*style="[^"]*width\s*:/i', $html))->toBeFalse(
        'El panel lleva el ancho en su atributo `style`: a 390 px el breakpoint no puede '.
        'pisarlo y la hoja móvil sale angosta y descolgada en vez de a ancho completo.'
    );

    expect((bool) preg_match('/--mpop-ancho:\s*320px/', $html))->toBeTrue(
        'El ancho no viaja como propiedad personalizada heredada, que es la única forma de '.
        'que la media query pueda cambiarlo.'
    );

    expect((bool) preg_match('/width\s*:\s*var\(\s*--mpop-ancho/', cssPanelFlotante()))->toBeTrue(
        'La regla base del panel no consume el ancho desde la propiedad personalizada.'
    );
});

it('el estado lo lleva el atributo nativo, no Alpine', function () {
    /*
     * `x-show` contra `showPopover()` es una pelea que se pierde de las dos
     * maneras: Alpine pinta `display:none` sobre un elemento que el navegador
     * acaba de subir al top-layer, o el navegador lo muestra y Alpine lo esconde
     * en el siguiente tick. El estado lo lleva UNO SOLO, y acá es el atributo
     * nativo: la fuente de verdad es `:popover-open`.
     */
    $codigo = codigoPanelFlotante();

    foreach (['x-show', 'x-cloak', 'x-transition'] as $prohibido) {
        expect(str_contains($codigo, $prohibido))->toBeFalse(
            "El componente usa `{$prohibido}`: eso es Alpine llevando la visibilidad, que es ".
            'justo lo que no puede pasar cuando el estado vive en el atributo nativo.'
        );
    }

    expect(str_contains($codigo, 'x-teleport'))->toBeFalse(
        'El componente teletransporta el panel al body. Con el top-layer nativo no hace '.
        'falta, y `x-teleport` es lo que hace que Livewire huerfane el panel del `modal` '.
        'al remorfear.'
    );
});

it('el listener de resize lo administra Alpine y no queda colgado', function () {
    // El bug de Pines: registra un `resize` en `window` que nunca elimina, así que
    // cada render de Livewire deja otro listener midiendo un panel que ya no está.
    $codigo = codigoPanelFlotante();

    expect((bool) preg_match('/(x-on:|@)resize\.window/', $codigo))->toBeTrue(
        'No hay reposicionamiento al cambiar el tamaño de la ventana: en el respaldo sin '.
        'anchor positioning el panel se queda donde estaba al abrirlo.'
    );

    expect(str_contains($codigo, 'addEventListener'))->toBeFalse(
        'El componente registra listeners a mano. Los de Alpine los quita Alpine al '.
        'destruir el nodo; un `addEventListener` sobre `window` escrito a mano sobrevive a '.
        'cada remorfeo de Livewire y se acumula, que es el bug de Pines que la ficha manda '.
        'no copiar.'
    );
});

it('al salir del panel con Tab se cierra, y de Escape se encarga el navegador', function () {
    $codigo = codigoPanelFlotante();

    expect(str_contains($codigo, 'relatedTarget'))->toBeTrue(
        'No se cierra al salir por Tab: con contenido largo el usuario se va del panel sin '.
        'darse cuenta y lo deja abierto encima de la tabla.'
    );

    expect(str_contains($codigo, 'hidePopover'))->toBeTrue(
        'El cierre no pasa por `hidePopover()`, que es la única manera de bajar el panel del '.
        'top-layer sin pelearse con el navegador.'
    );

    expect((bool) preg_match('/keydown\.escape|keydown\.esc\b/i', $codigo))->toBeFalse(
        'El componente reimplementa Escape a mano. `popover="auto"` ya cierra con Escape Y '.
        'devuelve el foco al invocador; una segunda implementación solo puede desincronizarse.'
    );
});

it('no genera ids con uniqid ni usa directivas de un solo major de Livewire', function () {
    $codigo = codigoPanelFlotante();

    expect(str_contains($codigo, 'uniqid('))->toBeFalse(
        'Un id con `uniqid()` cambia en cada render: rompe el `aria-controls`, el '.
        '`popovertarget` y el diffing de Livewire (DESIGN §10).'
    );

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($codigo, $prohibida))->toBeFalse(
            "`{$prohibida}` solo existe en un major de Livewire. El paquete se instala en ".
            'Livewire 4 y en `personas-graneros`, que sigue en Livewire 3.'
        );
    }
});

it('el panel no escribe ni un solo color literal fuera del respaldo del foco', function () {
    // Toda regla de color clara necesita contraparte oscura, o sale de un token
    // `--muni-*` que ya trae las dos ramas (DESIGN §1 y §3). El único hex admitido
    // es el gris canónico del tercer respaldo del outline.
    $literales = [];

    preg_match_all(
        '/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/',
        str_replace('#767676', '', codigoPanelFlotante()),
        $m
    );

    foreach ($m[0] as $color) {
        $literales[] = $color;
    }

    expect($literales)->toBe([], sprintf(
        'El panel escribe colores literales, que ningún municipio adoptante puede cambiar y '.
        'que no tienen contraparte en modo oscuro: %s',
        implode(', ', $literales)
    ));
});

it('el foco del disparador y del botón de cerrar se dibuja con outline', function () {
    $css = cssPanelFlotante();

    preg_match_all('/([^{}@]+):focus-visible[^{}]*\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    expect(count($reglas))->toBeGreaterThan(
        0,
        'No hay ninguna regla `:focus-visible`: el recorrido con teclado no muestra dónde está.'
    );

    foreach ($reglas as [, $selector, $cuerpo]) {
        expect((bool) preg_match('/outline\s*:\s*3px\s+solid\s+var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/', $cuerpo))->toBeTrue(
            'La regla de foco de «'.trim($selector).'» no dibuja el outline del sistema con '.
            'su cadena de respaldo: dentro de Filament la box-shadow se computa transparente '.
            'y no queda nada (DESIGN §5).'
        );
    }
});

it('el identificador del anclaje es por instancia y no finge ser un token de tema', function () {
    /*
     * Dos paneles que compartieran `anchor-name` se colgarían del mismo
     * disparador: el segundo se abriría bajo el primero. Y el nombre NO puede
     * estar en el espacio `--muni-*`: esos son tokens de tema que el adoptante
     * redefine y que tienen que estar declarados en los DOS CSS del paquete
     * —hay dos candados que lo vigilan—. Esto es fontanería por instancia que
     * escribe el propio componente, no algo que se pueda tematizar.
     */
    $uno = panelFlotanteHtml('label="Filtros"');
    $dos = panelFlotanteHtml('label="Descargas"');

    preg_match('/--mpop-ancla:\s*(--[A-Za-z0-9_-]+)/', $uno, $a);
    preg_match('/--mpop-ancla:\s*(--[A-Za-z0-9_-]+)/', $dos, $b);

    expect($a[1] ?? '')->not->toBe('', 'El envoltorio no publica ningún identificador de anclaje.');
    expect($a[1] ?? 'x')->not->toBe(
        $b[1] ?? 'x',
        'Dos paneles de la misma página comparten `anchor-name`: el segundo se cuelga del '.
        'disparador del primero.'
    );

    expect((bool) preg_match('/var\(\s*--muni-[a-z0-9-]*ancla/i', fuentePanelFlotante()))->toBeFalse(
        'El identificador de anclaje se disfraza de token `--muni-*`. Los candados del '.
        'paquete exigen que todo `--muni-*` que lea un componente esté definido en los dos '.
        'CSS publicables, y este no puede estarlo: es distinto en cada instancia.'
    );
});

it('el id es estable entre renders y el del consumidor manda', function () {
    $uno = panelFlotanteHtml('label="Filtros"');
    $dos = panelFlotanteHtml('label="Filtros"');

    preg_match('/popovertarget="([^"]+)"/', $uno, $a);
    preg_match('/popovertarget="([^"]+)"/', $dos, $b);

    expect($a[1] ?? 'a')->toBe($b[1] ?? 'b', 'El id del panel cambia entre renders idénticos.');

    $otro = panelFlotanteHtml('label="Descargas"');
    preg_match('/popovertarget="([^"]+)"/', $otro, $c);

    expect($c[1] ?? '')->not->toBe(
        $a[1] ?? '',
        'Dos paneles distintos en la misma página comparten id: el primero se lleva los dos '.
        'disparadores.'
    );

    $propio = panelFlotanteHtml('label="Filtros" id="filtros-bandeja"');

    expect(str_contains($propio, 'popovertarget="filtros-bandeja"'))->toBeTrue(
        'El `id` del consumidor no manda, y es la única salida cuando dos paneles tienen la '.
        'misma etiqueta.'
    );
    expect(str_contains($propio, 'id="filtros-bandeja"'))->toBeTrue(
        'El panel no lleva el id que pidió el consumidor.'
    );
});

it('una etiqueta con apóstrofo no descuadra ninguna expresión de Alpine', function () {
    // Ya pasó con `file-dropzone`: Blade escapa el apóstrofo, el navegador lo
    // decodifica DENTRO del atributo y la expresión queda descuadrada. No tumba el
    // componente: tumba el Alpine de la página entera.
    $html = Blade::render(
        '<x-muni::popover :label="$label">Contenido</x-muni::popover>',
        ['label' => "Filtros de la Muni' de Graneros"]
    );

    $rotas = [];

    foreach (expresionesAlpineDelPanel($html) as $expr) {
        $limpia = str_replace("\\'", '', $expr);

        if (substr_count($limpia, "'") % 2 !== 0) {
            $rotas[] = $expr;
        }
    }

    expect($rotas)->toBe([], sprintf(
        'Estas expresiones de Alpine quedan con las comillas simples descuadradas y tumban '.
        'el Alpine de la página entera: %s',
        implode(' | ', $rotas)
    ));
});

it('el texto del anfitrión nunca viaja dentro de una expresión de Alpine', function () {
    $marca = 'ZZ-ETIQUETA-DE-PRUEBA-ZZ';

    $html = Blade::render(
        '<x-muni::popover :label="$label">Contenido</x-muni::popover>',
        ['label' => $marca]
    );

    $culpables = array_values(array_filter(
        expresionesAlpineDelPanel($html),
        fn (string $expr) => str_contains($expr, $marca)
    ));

    expect($culpables)->toBe([], sprintf(
        'El texto del anfitrión aparece dentro de una expresión de Alpine, que es código '.
        'evaluado: un label venido de configuración con `%s` se ejecutaría. %s',
        "'+fetch(…)+'",
        implode(' | ', $culpables)
    ));

    expect(str_contains($html, e($marca)))->toBeTrue(
        'La etiqueta no está en el HTML del servidor: el disparador nace mudo hasta que '.
        'arranca Alpine.'
    );
});
