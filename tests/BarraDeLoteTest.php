<?php

use Illuminate\Support\Facades\Blade;

/*
 * LA SELECCIÓN EN LOTE DE LA COLA DEL FUNCIONARIO, PARTIDA EN PIEZAS PEQUEÑAS.
 *
 * La ficha `bandeja-trabajo` de docs/GAP-ANALYSIS.md pedía una PANTALLA con cinco
 * responsabilidades (pestañas, facetas, búsqueda, selección múltiple, columnas
 * configurables y detalle al lado). La corrección del juez la mató por nombre y por
 * alcance, y dejó escrito qué se construye en su lugar:
 *
 *   1. arreglar primero los dos defectos de una línea que valen más que el
 *      componente: `filter-bar` sin @@csrf en POST (419 en producción) y el
 *      `onchange` en línea de `segmented` —que una CSP con nonce bloquea y que
 *      además recarga la página en CADA flecha del teclado—;
 *   2. `selectable` como atributo OPCIONAL de `data-table` (casilla de cabecera con
 *      estado indeterminado, casilla por fila) y un `<x-muni::bulk-bar>` con
 *      role="region" + aria-live="polite" que solo recibe las acciones por ranura;
 *   3. alcance obligatorio a lo que se ve: el lote actúa sobre las casillas MARCADAS
 *      y visibles, y el paquete nunca emite un «aplicar a los 340 que coinciden con
 *      el filtro»;
 *   4. las facetas y el selector de columnas NO son componentes nuevos (son
 *      `dropdown` + `dropdown-item`), y el detalle al lado tampoco (es `drawer`).
 *
 * Esta prueba es el candado de esas cuatro cosas. Lo que el texto del .blade.php no
 * puede demostrar —que la cuenta sea la de las casillas marcadas, que el estado
 * indeterminado exista de verdad, que Escape deseleccione, que el anillo de foco se
 * vea— se mide en Chromium y en Firefox sobre el banco de `build/barra-de-lote/`.
 *
 * `expect($x)->toContain($y, 'mensaje')` NO sirve acá: en Pest el segundo argumento
 * es otra aguja y la aserción se apaga sin avisar. Todo va con
 * `expect(bool)->toBeTrue('mensaje')`.
 */

/** El fuente de un componente del paquete. */
function loteFuente(string $componente): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/'.$componente.'.blade.php');
}

/** El fuente sin comentarios de Blade ni de CSS: lo que se comprueba es código. */
function loteSinComentarios(string $fuente): string
{
    $sin = preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', (string) $sin);
}

/** El CSS de los bloques de estilos de un componente, sin comentarios. */
function loteCss(string $componente): string
{
    preg_match_all('#<style>(.*?)</style>#s', loteFuente($componente), $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1]));
}

/** El HTML de la barra, con las acciones del anfitrión en la ranura. */
function loteBarraHtml(string $extra = ''): string
{
    return Blade::render(
        '<x-muni::bulk-bar '.$extra.'><button type="button">Derivar a Obras</button></x-muni::bulk-bar>'
    );
}

// ---------------------------------------------------------------------------
// (1) Los dos defectos que el juez mandó arreglar ANTES que el componente
// ---------------------------------------------------------------------------

it('filter-bar emite @csrf en cuanto el método no es GET, y no lo emite en GET', function () {
    $get = Blade::render('<x-muni::filter-bar><input name="q"></x-muni::filter-bar>');

    expect(str_contains($get, '_token'))->toBeFalse(
        'La barra de filtros GET emite un _token: los filtros viven en la URL y un token en la '.
        'cadena de consulta se comparte al copiar el enlace.'
    );

    foreach (['post', 'POST', 'Post'] as $metodo) {
        $html = Blade::render('<x-muni::filter-bar method="'.$metodo.'"><input name="q"></x-muni::filter-bar>');

        expect((bool) preg_match('/<input type="hidden" name="_token"/', $html))->toBeTrue(
            "Con method=\"{$metodo}\" el formulario sale sin @csrf: Laravel responde 419 y el filtro no ".
            'se aplica nunca. La comparación tiene que ser insensible a mayúsculas.'
        );
    }
});

it('segmented no lleva un solo manejador de evento en línea, que la CSP con nonce bloquea', function () {
    $fuente = loteSinComentarios(loteFuente('segmented'));

    expect((bool) preg_match('/\son[a-z]+\s*=\s*"/i', $fuente))->toBeFalse(
        'Queda un atributo de evento en línea (onchange, onclick…): `unsafe-inline` NO cubre los '.
        'atributos de evento sin `unsafe-hashes`, así que bajo la CSP del ecosistema no corre nunca.'
    );

    $html = Blade::render(
        '<x-muni::segmented name="estado" label="Estado" :options="[\'a\' => \'Todos\', \'b\' => \'Cerrados\']" value="a" />'
    );

    expect((bool) preg_match('/\son[a-z]+\s*=\s*"/i', $html))->toBeFalse(
        'El HTML servido trae un manejador en línea.'
    );
});

it('segmented envía con requestSubmit y NO en cada flecha del teclado', function () {
    $fuente = loteSinComentarios(loteFuente('segmented'));

    expect(str_contains($fuente, 'requestSubmit()'))->toBeTrue(
        'El envío no usa requestSubmit(): `form.submit()` se salta la validación nativa y el evento '.
        '`submit`, así que el anfitrión no puede interceptarlo.'
    );

    expect((bool) preg_match('/(?:@|x-on:)change/', $fuente))->toBeTrue(
        'No hay x-on:change: el cambio de opción no envía nada y el filtro dejó de aplicarse.'
    );

    expect((bool) preg_match('/(?:@|x-on:)keydown\.arrow/', $fuente))->toBeTrue(
        'No hay guardia de teclado: en un radiogroup las flechas MUEVEN la selección, así que un '.
        'autoenvío en cada `change` recarga la página una vez por opción y destruye la navegación '.
        'por teclado del grupo (WCAG 2.2 AA 3.2.2).'
    );

    $html = Blade::render(
        '<x-muni::segmented name="estado" label="Estado" :options="[\'a\' => \'Todos\', \'b\' => \'Cerrados\']" value="a" />'
    );

    expect((bool) preg_match('/<button[^>]+type="submit"/', $html))->toBeTrue(
        'No hay botón de respaldo: quien navega con las flechas se queda sin forma de aplicar el filtro.'
    );

    expect(str_contains($html, 'role="radiogroup"'))->toBeTrue('El grupo de radios perdió su rol.');
});

// ---------------------------------------------------------------------------
// (2) La barra de acciones en lote
// ---------------------------------------------------------------------------

it('la barra es una región viva con nombre y las acciones van en la ranura', function () {
    $html = loteBarraHtml();

    expect(str_contains($html, 'role="region"'))->toBeTrue(
        'La barra no es una región: sin rol no se alcanza saltando por puntos de referencia y quien '.
        'acaba de marcar veinte filas tiene que tabular por toda la tabla para llegar a las acciones.'
    );
    expect(str_contains($html, 'aria-live="polite"'))->toBeTrue(
        'La barra no anuncia: aparece en pantalla y el lector de pantalla no dice nada (la ficha '.
        'exige «al aparecer la barra de acciones en lote, se anuncia»).'
    );
    expect((bool) preg_match('/aria-label="[^"]+"/', $html))->toBeTrue(
        'La región no tiene nombre accesible: se anuncia como «región» y nada más.'
    );
    expect(str_contains($html, 'Derivar a Obras'))->toBeTrue('La ranura de acciones no se renderiza.');
    expect(str_contains($html, 'aria-live="off"'))->toBeTrue(
        'Las acciones del anfitrión no están fuera de lo que se anuncia: al aparecer la barra, la '.
        'región viva recitaría también el rótulo de cada botón.'
    );

    expect(str_contains(loteBarraHtml('label="Acciones sobre las solicitudes"'), 'aria-label="Acciones sobre las solicitudes"'))
        ->toBeTrue('La prop `label` no manda sobre el nombre de la región.');
});

it('la barra nace invisible y sin Alpine no muestra una cuenta falsa', function () {
    $html = loteBarraHtml();

    expect((bool) preg_match('/style="[^"]*display:none/', $html))->toBeTrue(
        'El cuerpo de la barra no nace oculto desde el servidor: sin Alpine (o antes de que arranque) '.
        'se ve una barra de acciones en lote con cero filas marcadas.'
    );
    expect((bool) preg_match('/x-show="[^"]*\bloteN\b[^"]*>\s*0"/', $html))->toBeTrue(
        'La barra no se muestra en función de cuántas casillas hay marcadas.'
    );
});

it('la cuenta sale de las casillas MARCADAS y el alcance nunca pasa de lo que se ve', function () {
    $fuente = loteSinComentarios(loteFuente('bulk-bar'));

    expect(str_contains($fuente, 'data-muni-pick'))->toBeTrue(
        'La barra no lee las casillas de fila: la cuenta saldría de un número que le pasa el '.
        'anfitrión y podría no tener nada que ver con lo marcado.'
    );
    expect(str_contains($fuente, 'x.checked'))->toBeTrue('La cuenta no filtra por casilla marcada.');

    /* El peor pie de la referencia de shadcn, y lo que la corrección del juez
       prohíbe: una acción sobre «todos los que coinciden con el filtro». El
       paquete no puede garantizar autorización, cola ni bitácora, así que no
       ofrece ni el botón. */
    foreach (['selectAllMatching', 'todoElFiltro', 'selectionTotal', 'aplicar a todos'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "La barra ofrece «{$prohibido}»: el lote solo actúa sobre lo marcado y visible."
        );
    }

    $html = loteBarraHtml();

    expect((bool) preg_match('/(?:@|x-on:)click="[^"]*loteLimpiar\(\)/', $html))->toBeTrue(
        'No hay «Quitar selección»: la ficha lo exige y sin él la única salida es desmarcar una por una.'
    );
});

it('la barra se puede acotar a UNA tabla con `for`, para que dos bandejas no se sumen', function () {
    $html = loteBarraHtml('for="tabla-solicitudes"');

    expect(str_contains($html, 'tabla-solicitudes'))->toBeTrue(
        'La prop `for` no llega al componente: con dos tablas seleccionables en la misma pantalla, '.
        'cada barra contaría las casillas de las dos.'
    );
});

// ---------------------------------------------------------------------------
// (3) `selectable` en data-table: aditivo, con estado indeterminado
// ---------------------------------------------------------------------------

it('data-table sin `selectable` emite exactamente lo de antes', function () {
    $html = Blade::render(
        '<x-muni::data-table :columns="[\'Folio\', \'Vecino\']" caption="Bandeja"><tr data-muni-row><td>4821</td><td>Ana Soto</td></tr></x-muni::data-table>'
    );

    expect(str_contains($html, 'data-muni-pick'))->toBeFalse('La tabla emite marcado de selección sin pedirlo.');
    expect(str_contains($html, 'type="checkbox"'))->toBeFalse('La tabla emite una casilla sin pedirlo.');
    expect(str_contains($html, 'x-data'))->toBeFalse('La tabla arrastra Alpine sin pedirlo.');
});

it('data-table con `selectable` trae casilla de cabecera, estado indeterminado y Escape', function () {
    $html = Blade::render(
        '<x-muni::data-table selectable :columns="[\'Folio\', \'Vecino\']" caption="Bandeja">'
        .'<tr data-muni-row><td><input type="checkbox" data-muni-pick name="ids[]" value="4821" aria-label="Seleccionar la solicitud 4821"></td><td>Ana Soto</td></tr>'
        .'</x-muni::data-table>'
    );

    expect(str_contains($html, 'x-effect="$el.indeterminate'))->toBeTrue(
        'La casilla de cabecera no tiene estado indeterminado: con media tabla marcada se ve como si '.
        'no hubiera nada marcado. El estado mixto de un input NATIVO es una propiedad del DOM, no un '.
        'aria-checked="mixed".'
    );
    expect((bool) preg_match('/aria-label="[^"]*[Ss]eleccionar[^"]*"/', $html))->toBeTrue(
        'La casilla de cabecera no tiene nombre accesible: el lector dice «casilla» y no de qué.'
    );
    expect((bool) preg_match('/(?:@|x-on:)keydown\.escape=/', $html))->toBeTrue(
        'Escape no deselecciona: la ficha lo exige como salida del lote.'
    );
    expect((bool) preg_match('/(?:@|x-on:)keydown\.escape\.window/', $html))->toBeFalse(
        'Escape está enganchado a `.window`: dentro de un modal de Filament le robaría el cierre al '.
        'modal (CLAUDE.md).'
    );
    expect(str_contains($html, 'colspan="3"'))->toBeFalse('El cuerpo trae filas: no debería pintar el vacío.');
});

it('data-table seleccionable cuenta la columna de la casilla en el estado vacío', function () {
    $html = Blade::render('<x-muni::data-table selectable :columns="[\'Folio\', \'Vecino\']" caption="Bandeja" />');

    expect(str_contains($html, 'colspan="3"'))->toBeTrue(
        'El colspan del estado vacío no cuenta la columna de selección: el texto «Sin resultados» '.
        'queda desalineado y la tabla pierde una celda.'
    );
});

it('data-table seleccionable exige columnas: sin cabecera no hay dónde poner «marcar todo»', function () {
    /*
     * `->toThrow(InvalidArgumentException::class)` no sirve sobre un componente
     * Blade: `CompilerEngine::handleViewException()` mete todo dentro de una
     * `Illuminate\View\ViewException`, así que la aserción vería siempre esa clase.
     * Se comprueba la causa, igual que en `TablaAnchaYPaginacionTest`.
     */
    $motivo = null;

    try {
        Blade::render('<x-muni::data-table selectable><tr><td>x</td></tr></x-muni::data-table>');
    } catch (Throwable $e) {
        while ($e->getPrevious() !== null) {
            $e = $e->getPrevious();
        }

        $motivo = $e;
    }

    expect($motivo)->toBeInstanceOf(InvalidArgumentException::class,
        'Una tabla seleccionable sin columnas se renderiza en silencio, sin casilla de «marcar todo»: '.
        'hay que marcar de a una y nadie se entera de que la prop está a medias.'
    );
});

// ---------------------------------------------------------------------------
// (3b) Lo que la primera revisión encontró roto
// ---------------------------------------------------------------------------

/** Una tabla seleccionable con una fila, dentro del formulario del lote. */
function loteTablaHtml(string $extra = ''): string
{
    return Blade::render(
        '<x-muni::data-table selectable '.$extra.' :columns="[\'Folio\', \'Vecino\']" caption="Bandeja">'
        .'<tr data-muni-row class="muni-row--danger"><td><input type="checkbox" data-muni-pick name="ids[]" value="4821" aria-label="Seleccionar la solicitud 4821"></td>'
        .'<td class="muni-num">4821</td></tr>'
        .'</x-muni::data-table>'
    );
}

it('Enter sobre una casilla de la tabla NO envía el formulario: abre el detalle de la fila', function () {
    /*
     * Una casilla dentro de un <form> hace el ENVÍO IMPLÍCITO con Enter, y el botón
     * que se pulsa es el primero de envío del formulario: en una bandeja, la primera
     * acción en lote («Cerrar», «Derivar»). Medido en Chromium y Firefox por la
     * revisión: `?accion=cerrar&ids[]=1`, sin confirmación. La ficha pide lo
     * contrario: «Espacio marca, Enter abre el detalle».
     */
    $html = loteTablaHtml();

    expect((bool) preg_match('/(?:@|x-on:)keydown\.enter="[^"]*"/', $html))->toBeTrue(
        'La tabla seleccionable no intercepta Enter: sobre una casilla dentro del formulario del lote, '.
        'Enter ejecuta la primera acción en lote por envío implícito.'
    );

    $fuente = loteSinComentarios(loteFuente('data-table'));

    expect(str_contains($fuente, 'preventDefault()'))->toBeTrue(
        'Enter se escucha pero no se cancela: el envío implícito sigue ocurriendo.'
    );
    expect(str_contains($fuente, '[data-muni-open]'))->toBeTrue(
        'Enter no abre nada: la ficha pide que Enter sobre la fila abra su detalle, y el contrato es '.
        'el elemento `data-muni-open` que el anfitrión pone en la fila.'
    );
});

it('la barra neutraliza el envío implícito del formulario del lote, incluso sin JavaScript', function () {
    /*
     * Sin JS la barra nace oculta, pero sus botones siguen en el árbol: el primero de
     * envío ES el botón por defecto del formulario y Enter en una casilla o un campo
     * lo pulsa. Un botón de envío con `formmethod="dialog"` puesto antes de la ranura
     * pasa a ser el botón por defecto, y su envío fuera de un <dialog> no hace nada.
     * Uno DESHABILITADO no sirve: Chromium lo salta cuando el Enter viene de una
     * casilla y pulsa la acción siguiente (medido en el banco).
     */
    $html = loteBarraHtml();

    $guarda = strpos($html, 'muni-bulkbar__guarda');
    $accion = strpos($html, 'Derivar a Obras');

    expect($guarda !== false && $accion !== false && $guarda < $accion)->toBeTrue(
        'No hay botón de guarda antes de las acciones: Enter en una casilla o un campo del formulario '.
        'dispara la primera acción en lote.'
    );
    preg_match('/<button[^>]*muni-bulkbar__guarda[^>]*>/', $html, $guardaTag);
    $guardaTag = $guardaTag[0] ?? '';

    expect(str_contains($guardaTag, 'type="submit"') && str_contains($guardaTag, 'formmethod="dialog"'))->toBeTrue(
        'La guarda no es un botón de envío con formmethod="dialog": el envío implícito seguiría '.
        'llegando a la primera acción en lote.'
    );
    expect((bool) preg_match('/\sname=/', $guardaTag))->toBeFalse('La guarda lleva `name`: se colaría en lo que recibe el anfitrión.');
    expect((bool) preg_match('/\sdisabled\b/', $guardaTag))->toBeFalse(
        'La guarda está deshabilitada: Chromium la salta desde una casilla y pulsa la acción siguiente.'
    );
});

it('marcar todo y quitar la selección avisan a CADA casilla, para que wire:model se entere', function () {
    /*
     * `wire:model` (y `x-model`, que es lo que usa por debajo) escucha el `change`
     * de la propia casilla. Un único evento sintético sobre el contenedor no le llega:
     * «marcar todo» dejaba a Livewire con cero ids y el siguiente morph desmarcaba las
     * casillas a espaldas de la barra.
     */
    foreach (['data-table', 'bulk-bar'] as $componente) {
        $fuente = loteSinComentarios(loteFuente($componente));

        expect((bool) preg_match('/\$root\.dispatchEvent\(\s*new Event\(\s*\'change\'/', $fuente))->toBeFalse(
            "«{$componente}» sigue avisando con un solo `change` sobre su raíz: wire:model no se entera."
        );
        expect((bool) preg_match('/x\.dispatchEvent\(\s*new Event\(\s*\'change\'/', $fuente))->toBeTrue(
            "«{$componente}» no emite `change` sobre cada casilla que cambia."
        );
    }
});

it('la barra no tapa con nombres genéricos el Alpine del anfitrión dentro de la ranura', function () {
    $html = loteBarraHtml();

    preg_match('/x-data="(.*?)"\s/s', $html, $m);
    $datos = $m[1] ?? '';

    expect($datos !== '')->toBeTrue('No se encontró el x-data de la barra.');

    foreach (['n', 'ids', 'texto', 'limpiar', 'zona', 'tocado'] as $generico) {
        expect((bool) preg_match('/(?:^|[\s,{])'.$generico.'\s*[:(]/', $datos))->toBeFalse(
            "La barra expone «{$generico}» en su x-data: dentro de la ranura tapa la variable del mismo "
            .'nombre del Alpine del anfitrión.'
        );
    }

    foreach (['loteN', 'loteIds', 'loteTexto', 'loteLimpiar'] as $publico) {
        expect(str_contains($datos, $publico))->toBeTrue("La barra no expone «{$publico}» a la ranura.");
    }

    expect((bool) preg_match('/(?:@|x-on:)keydown\.escape="/', $html))->toBeTrue(
        'Escape solo deselecciona con el foco en la tabla: desde la barra, que es donde queda el foco '.
        'tras «Quitar selección» o tras una acción, no hace nada.'
    );
});

it('columna fija + selección fija la casilla Y la columna que identifica, y el problema pinta esa', function () {
    $html = loteTablaHtml('sticky-column');

    expect((bool) preg_match('/<table[^>]*class="[^"]*\bmuni-dt--pick\b/', $html))->toBeTrue(
        'La tabla seleccionable no se marca como tal: el CSS no puede distinguir la columna de la '.
        'casilla de la del RUT.'
    );

    $css = loteCss('data-table');

    expect((bool) preg_match('/\.muni-dt--pick\s+td:nth-child\(2\)[^{]*\{[^}]*position:\s*sticky[^}]*left:\s*44px/', $css))->toBeTrue(
        'Con `stickyColumn` + `selectable` solo queda fija la casilla y el RUT se pierde al desplazar.'
    );
    expect((bool) preg_match('/\.muni-dt--pick\s+\[data-muni-row\]\.muni-row--danger\s+td:nth-child\(2\)[^{]*\{[^}]*color:\s*var\(--muni-danger-fg\)/', $css))->toBeTrue(
        'En una tabla seleccionable el color y el peso de la fila con problema caen sobre la celda de '.
        'la casilla y no sobre el dato.'
    );
});

it('segmented no le cambia la caja al consumidor, y dos grupos en un formulario dan UN «Aplicar»', function () {
    $css = loteCss('segmented');

    expect((bool) preg_match('/\.muni-seg-grupo\s*\{[^}]*display:\s*contents/', $css))->toBeTrue(
        'El envoltorio del autoenvío genera caja propia: el `style` o la `class` que el consumidor pone '.
        'van al radiogroup interno y un `width:100%` deja de aplicarse al bloque de fuera.'
    );

    $fuente = loteSinComentarios(loteFuente('segmented'));

    expect(str_contains($fuente, "querySelectorAll('.muni-seg-aplicar')"))->toBeTrue(
        'Cada grupo decide su botón de respaldo por su cuenta: dos segmented en el mismo formulario '.
        'sin botón muestran dos «Aplicar».'
    );
});

// ---------------------------------------------------------------------------
// (4) El contrato del paquete: tokens, foco, movimiento, Livewire 3 y 4
// ---------------------------------------------------------------------------

it('la barra no escribe un color literal y todo token --muni-* existe en las dos hojas', function () {
    $css = loteCss('bulk-bar');

    expect(trim($css) !== '')->toBeTrue('El componente no lleva su CSS consigo (DESIGN §7).');

    $hex = preg_replace('/var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/', '', $css);

    expect((bool) preg_match('/(?<!&)#[0-9a-fA-F]{3,8}\b/', (string) $hex))->toBeFalse(
        'Hay un color literal en el CSS del componente (DESIGN §10). El único hex permitido es el '.
        '#767676 del tercer respaldo del foco.'
    );
    expect((bool) preg_match('/\b(rgba?|hsla?)\s*\(/i', $css))->toBeFalse(
        'Los colores salen de tokens --muni-*, no de rgb()/hsl().'
    );

    $hoja = cssMuniUi();
    $hojaPanel = cssMuniUiFilament();

    foreach ([loteCss('bulk-bar'), loteCss('data-table'), loteCss('segmented')] as $bloque) {
        preg_match_all('/--muni-[a-z0-9-]+/', $bloque, $usados);

        foreach (array_unique($usados[0]) as $token) {
            expect(str_contains($hoja, $token.':'))->toBeTrue("«{$token}» no está declarado en muni-ui.css.");
            expect(str_contains($hojaPanel, $token.':'))->toBeTrue(
                "«{$token}» no está declarado en muni-ui-filament.css. Dentro de un panel Filament solo "
                .'se carga esa hoja (DESIGN §7): el componente se vería sin color y sin un error en consola.'
            );
        }

        expect((bool) preg_match('/--muni-[a-z0-9-]+\s*:/', $bloque))->toBeFalse(
            'Un componente DECLARA un --muni-*: los tokens del sistema se definen en las dos hojas. '.
            'Una variable local va con prefijo propio.'
        );
    }
});

it('el foco es el outline canónico y el movimiento respeta la preferencia', function () {
    foreach (['bulk-bar', 'data-table', 'segmented'] as $componente) {
        $css = loteCss($componente);

        expect(substr_count($css, 'outline:3px solid var(--muni-focus, var(--muni-accent, #767676))'))
            ->toBeGreaterThanOrEqual(1,
                "«{$componente}» no pinta el outline canónico de 3 px: dentro de Filament la box-shadow ".
                'del anillo se computa transparente (DESIGN §5).'
            );

        foreach (preg_split('/\n/', $css) ?: [] as $linea) {
            if (! str_contains($linea, 'transition:') || str_contains($linea, 'transition:none')) {
                continue;
            }

            expect(str_contains($linea, 'var(--muni-dur)'))->toBeTrue(
                "«{$componente}»: una transición no usa var(--muni-dur), que es lo que baja a 0 ms con ".
                "movimiento reducido (DESIGN §6):\n".trim($linea)
            );
        }
    }
});

it('nada de uniqid, ni de directivas de un solo major de Livewire', function () {
    foreach (['bulk-bar', 'data-table', 'segmented', 'filter-bar'] as $componente) {
        $fuente = loteSinComentarios(loteFuente($componente));

        expect(str_contains($fuente, 'uniqid('))->toBeFalse(
            "«{$componente}» genera ids con uniqid(): cambian en cada render y ensucian el diffing (DESIGN §10)."
        );

        foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibido) {
            expect(str_contains($fuente, $prohibido))->toBeFalse(
                "«{$componente}» usa «{$prohibido}», que no existe en los dos majors de Livewire con los ".
                'que convive el paquete (CLAUDE.md).'
            );
        }
    }
});

it('el área táctil de los controles del lote llega a 44 px con puntero grueso', function () {
    $css = loteCss('bulk-bar').loteCss('data-table');

    expect(str_contains($css, '@media (pointer: coarse)'))->toBeTrue(
        'No hay regla para puntero grueso: en la tablet de terreno la casilla de fila y el botón de '.
        'quitar la selección se quedan en 24 px (WCAG 2.2 AA 2.5.8 pide 44×44 en ese contexto).'
    );
});

// ---------------------------------------------------------------------------
// El banco de navegador: lo único que demuestra que la selección funciona
// ---------------------------------------------------------------------------

/** Alpine para el banco: sin él no hay ni cuenta, ni estado indeterminado, ni barra. */
function loteAlpine(): ?string
{
    $candidatos = array_merge(
        [__DIR__.'/../node_modules/alpinejs/dist/cdn.min.js'],
        array_filter(
            glob(__DIR__.'/../scripts/.cache/alpine-*.min.js') ?: [],
            fn (string $ruta) => ! str_contains(basename($ruta), 'alpine-focus-'),
        ),
    );

    foreach ($candidatos as $candidato) {
        if (is_file($candidato)) {
            return (string) file_get_contents($candidato);
        }
    }

    return null;
}

/*
 * El banco se genera acá y no en un script suelto porque este es el único sitio del
 * paquete con Blade arrancado (mismo criterio que `GeneraVitrinaTest` y
 * `DensidadDeTablaTest`). Sale a `build/barra-de-lote/`, ignorado por git.
 *
 * Existe porque TODO lo de arriba es texto del .blade.php, y eso ya engañó en este
 * repo: una regla escrita y el candado en verde mientras en el navegador computaba
 * `box-shadow: none`. Lo que solo se ve en un navegador:
 *
 *   · que la cuenta sea la de las casillas MARCADAS y cambie al marcar;
 *   · que el estado indeterminado de la cabecera exista de verdad (es una propiedad
 *     del DOM: en el HTML no se puede escribir);
 *   · que «Quitar selección» desmarque la tabla y la cabecera se entere, que es la
 *     sincronía entre dos componentes distintos que no comparten estado;
 *   · que Escape deseleccione y que Espacio marque;
 *   · que la barra acotada con `for` NO cuente las filas de la otra tabla;
 *   · que las flechas del teclado ya no recarguen la página y el clic sí envíe.
 */
it('genera el banco de navegador en build/barra-de-lote/', function () {
    $dir = __DIR__.'/../build/barra-de-lote';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $columnas = ['Folio', 'Vecino', 'Materia', 'Detalle'];

    /* RUT y folios inventados: el banco nunca se siembra del maestro de personas.
       Cada fila trae su «Ver» con `data-muni-open`, que es lo que Enter abre, y
       `$extra` deja poner `x-model` en la casilla para imitar a wire:model. */
    $fila = function (int $folio, string $vecino, string $materia, bool $marcada = false, bool $problema = false, string $extra = '') {
        return '<tr data-muni-row'.($problema ? ' class="muni-row--danger"' : '').'>'
            .'<td><input type="checkbox" data-muni-pick name="ids[]" value="'.$folio.'"'
            .($marcada ? ' checked' : '')
            .' '.$extra
            .' aria-label="Seleccionar la solicitud '.$folio.'"></td>'
            .'<td class="muni-num">'.$folio.'</td>'
            .'<td>'.$vecino.'</td>'
            .'<td>'.$materia.'</td>'
            .'<td><a class="muni-accion" href="#detalle-'.$folio.'" data-muni-open aria-label="Ver la solicitud '.$folio.'">Ver</a></td>'
            .'</tr>';
    };

    $filasA = $fila(4821, 'Ana Soto Miranda', 'Alumbrado público', true)
        .$fila(4822, 'Luis Pérez Vidal', 'Retiro de escombros')
        .$fila(4823, 'Rosa Díaz Núñez', 'Tapa de cámara suelta', false, true);

    $filasB = $fila(5101, 'Juan Vera Rojas', 'Poda de árbol')
        .$fila(5102, 'Marta Lillo Soto', 'Semáforo apagado');

    $filasC = $fila(6001, 'Pedro Gálvez Ortiz', 'Microbasural', false, false, 'x-model="sel"')
        .$fila(6002, 'Carla Muñoz Reyes', 'Ruidos molestos', false, false, 'x-model="sel"');

    $filasD = $fila(7001, 'Sergio Castillo Fuenzalida', 'Solicitud de reparación de luminaria en pasaje sin salida')
        .$fila(7002, 'Daniela Herrera Contreras', 'Denuncia por perro vago en la plaza de armas de la comuna', false, true);

    $cuerpo = Blade::render(
        '<section data-caso="lote">'
        .'<h2>Bandeja de solicitudes</h2>'
        .'<form id="form-lote" method="get" action="">'
        .'<x-muni::bulk-bar for="tabla-a" count-singular="1 solicitud seleccionada" count-plural=":n solicitudes seleccionadas">'
        /* Una acción de ENVÍO de verdad, con name y value: es la que el envío
           implícito de Enter disparaba sin confirmación. */
        .'<button type="submit" name="accion" value="cerrar" id="accion-cerrar" class="muni-accion">Cerrar</button>'
        .'<button type="button" id="accion-derivar" class="muni-accion">Derivar a Obras</button>'
        .'</x-muni::bulk-bar>'
        .'<x-muni::data-table id="tabla-a" selectable :columns="$columnas" caption="Solicitudes por derivar">'
        .$filasA
        .'</x-muni::data-table>'
        .'</form></section>'

        .'<section data-caso="segunda">'
        .'<h2>Segunda bandeja, para probar el acotado</h2>'
        .'<form id="form-b" method="post" action="?enviado=b">'
        .'<x-muni::bulk-bar for="tabla-b" label="Acciones sobre las patentes">'
        .'<button type="button" class="muni-accion">Notificar cobranza</button>'
        .'</x-muni::bulk-bar>'
        .'<x-muni::data-table id="tabla-b" selectable :columns="$columnas" caption="Patentes vencidas">'
        .$filasB
        .'</x-muni::data-table>'
        .'</form></section>'

        .'<section data-caso="espejo">'
        .'<h2>Casillas con x-model, como las enlaza wire:model</h2>'
        .'<div x-data="{ sel: [] }">'
        .'<x-muni::bulk-bar for="tabla-c" label="Acciones sobre el espejo">'
        .'<button type="button" class="muni-accion">Derivar</button>'
        .'</x-muni::bulk-bar>'
        .'<x-muni::data-table id="tabla-c" selectable :columns="$columnas" caption="Solicitudes con estado del anfitrión">'
        .$filasC
        .'</x-muni::data-table>'
        .'<p>Estado del anfitrión: <output id="espejo" x-text="sel.join(\',\') || \'ninguna\'">ninguna</output></p>'
        .'</div></section>'

        .'<section data-caso="fija">'
        .'<h2>Columna fija con selección</h2>'
        .'<div style="max-width:340px">'
        .'<x-muni::data-table id="tabla-d" selectable sticky-column :columns="$columnas" caption="Nómina ancha">'
        .$filasD
        .'</x-muni::data-table>'
        .'</div></section>'

        .'<section data-caso="filtro">'
        .'<h2>Filtro con su propio botón</h2>'
        .'<x-muni::filter-bar action="?enviado=filtro">'
        .'<x-muni::segmented name="estado" label="Filtrar por estado" :options="$estados" value="todos" />'
        .'</x-muni::filter-bar></section>'

        .'<section data-caso="segmented-solo">'
        .'<h2>Grupo sin botón en el formulario</h2>'
        .'<form id="form-seg" method="get" action="">'
        .'<x-muni::segmented name="tramite" label="Filtrar por trámite" :options="$tramites" value="todos" />'
        .'</form></section>'

        .'<section data-caso="dos-grupos">'
        .'<h2>Dos grupos en un formulario sin botón</h2>'
        .'<form id="form-dos" method="get" action="">'
        .'<x-muni::segmented name="zona" label="Filtrar por zona" :options="$zonas" value="todas" />'
        .'<x-muni::segmented name="plazo" label="Filtrar por plazo" :options="$plazos" value="todos" />'
        .'</form>'
        .'<div style="width:300px;margin-top:12px">'
        .'<form id="form-ancho" method="get" action="">'
        .'<x-muni::segmented name="ancho" label="Grupo a todo el ancho" style="width:100%" :options="$plazos" value="todos" />'
        .'<button type="submit" class="muni-accion">Filtrar</button>'
        .'</form></div></section>'

        .'<section data-caso="vacia">'
        .'<h2>Bandeja vacía</h2>'
        .'<x-muni::data-table selectable :columns="$columnas" caption="Sin resultados" />'
        .'</section>',
        [
            'columnas' => $columnas,
            'estados' => ['todos' => 'Todos', 'pend' => 'Pendientes', 'cerr' => 'Cerrados'],
            'tramites' => ['todos' => 'Todos', 'lic' => 'Licencias', 'pat' => 'Patentes'],
            'zonas' => ['todas' => 'Todas', 'urb' => 'Urbana', 'rur' => 'Rural'],
            'plazos' => ['todos' => 'Todos', 'venc' => 'Vencidos'],
            'filasC' => $filasC,
            'filasD' => $filasD,
        ],
    );

    $alpine = loteAlpine();

    expect($alpine)->not->toBeNull(
        'Sin Alpine el banco no sirve: la barra nace oculta, la cuenta no existe y el estado '.
        'indeterminado tampoco. Falta node_modules/alpinejs (npm install).'
    );

    /* Los botones de acción del anfitrión llevan color propio: un <button> pelado
       hereda el gris del navegador y ensuciaría la medición de contraste con un
       defecto del banco y no del componente. */
    $armazon = 'body{margin:0;padding:16px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
        .'h2{font-size:12px;font-weight:700;margin:20px 0 6px;color:var(--muni-muted)}'
        .'section:first-child h2{margin-top:0}'
        .'.muni-accion{min-height:30px;padding:6px 14px;font:inherit;font-size:12.5px;font-weight:600;'
        .'color:var(--muni-on-accent);background:var(--muni-accent);border:0;'
        .'border-radius:var(--muni-radius-sm);cursor:pointer}';

    $paginas = [
        'claro' => ['light', false],
        'oscuro' => ['dark', false],
        'panel-claro' => ['light', true],
        'panel-oscuro' => ['dark', true],
    ];

    foreach ($paginas as $nombre => [$tema, $panel]) {
        /* Las dos páginas de panel cargan ÚNICAMENTE muni-ui-filament.css, que es lo
           que el plugin inyecta dentro de Filament (DESIGN §7): ahí es donde una
           clase declarada fuera del bloque de estilos del componente se quedaría sin
           una sola regla. */
        $hoja = $panel ? cssMuniUiFilament() : cssMuniUi();

        $cuerpoPagina = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<section class="fi-section">'.$cuerpo.'</section></main></div>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$cuerpo.'</main>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($tema === 'dark' ? ' class="dark"' : '').'>'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco del lote — '.$nombre.'</title>'
            .'<style>'.$hoja.'</style><style>'.$armazon.'</style></head>'
            .$cuerpoPagina.'<script data-banco="alpine-core">'.$alpine.'</script></body></html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }

    /* El bloque de estilos de cada componente se emite UNA vez por render: si el
       banco hubiera salido sin él, lo que se mide sería el navegador y no el paquete. */
    $claro = (string) file_get_contents($dir.'/claro.html');

    foreach (['.muni-bulkbar__cuerpo', '.muni-dt__pick', '.muni-seg-aplicar'] as $clase) {
        expect(str_contains($claro, $clase.' '))->toBeTrue("El banco salió sin el CSS de «{$clase}».");
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function lotePython(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

it('en Chromium y Firefox: la cuenta, el estado indeterminado, Escape, el foco y el filtro que ya no recarga', function () {
    $python = lotePython();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/barra-de-lote.py').' '
        .escapeshellarg(__DIR__.'/../build/barra-de-lote').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador del lote falló:\n".implode("\n", $lineas));
});

/*
 * La reja oficial (`scripts/a11y-check.py`, la misma de `npm run a11y:vitrina`) sobre
 * el banco: contraste real de todo el texto —la cuenta de la barra sobre su
 * superficie, el botón de quitar, las píldoras del filtro— y axe-core, en los DOS
 * temas y con las dos hojas. La barra se mide VISIBLE porque el banco trae una
 * solicitud marcada de fábrica: con cero marcadas nace oculta y la reja no mediría
 * nada del componente.
 */
it('la reja de accesibilidad pasa sobre el banco del lote en los dos temas y las dos hojas', function () {
    $python = lotePython();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $paginas = array_map(
        fn (string $p) => escapeshellarg(__DIR__.'/../build/barra-de-lote/'.$p.'.html'),
        ['claro', 'oscuro', 'panel-claro', 'panel-oscuro'],
    );

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/../scripts/a11y-check.py').' '
        .implode(' ', $paginas).' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La reja de accesibilidad falló sobre el banco del lote:\n".implode("\n", $lineas));
});
