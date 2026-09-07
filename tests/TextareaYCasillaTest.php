<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DEL CAMPO LARGO Y DE LA CASILLA
|--------------------------------------------------------------------------
|
| `<x-muni::textarea>` es la «descripción del requerimiento» del ingreso
| público de Atención al Vecino y la observación de una fiscalización en
| terreno. `<x-muni::checkbox>` es la casilla suelta y la del grupo: la
| aceptación con su fundamento legal al lado, y los tipos de discapacidad del
| registro de la credencial.
|
| Los dos nacen con las condiciones que costaron caro en el resto del paquete
| ya escritas como prueba:
|
| 1. EL ID NO SALE DE `uniqid()` (DESIGN §8). Cambia en cada render, rompe el
|    `for` de la etiqueta y ensucia el diffing de Livewire. Sale del `name`
|    saneado —`items[0][rut]` no es un id válido— y el `id` que pase el
|    consumidor manda.
| 2. EL ERROR SE ATA con `aria-describedby` y se marca con `aria-invalid`, con
|    la ayuda encadenada: un campo que anuncia «inválido» sin decir por qué
|    incumple 3.3.1.
| 3. LA CASILLA NUNCA VIENE MARCADA. Las plantillas comerciales traen la
|    aceptación premarcada; bajo la Ley 21.719 una casilla premarcada no es una
|    manifestación de voluntad y el asiento de licitud queda inservible.
| 4. LA DESCRIPCIÓN DE LA CASILLA VA FUERA DEL `<label>`, por
|    `aria-describedby`: dentro se concatena al nombre accesible y el lector la
|    repite entera cada vez que se enfoca la casilla. Es el defecto que arrastra
|    `switch`.
| 5. EL CONTADOR DEL CAMPO LARGO ESTÁ EN TEXTO y NO se anuncia por carácter:
|    el número visible vive fuera de toda región viva, y la región `polite`
|    habla solo en los umbrales.
| 6. NI UN COLOR LITERAL (DESIGN §1), salvo el `#767676` canónico del respaldo
|    del outline.
|
| Se prueba con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra cadena a buscar, no un mensaje, y la
| aserción se desactiva sin avisar.
*/

/** La fuente Blade cruda de uno de los dos componentes nuevos. */
function fuenteCampoNuevo(string $componente): string
{
    $ruta = __DIR__.'/../resources/views/components/'.$componente.'.blade.php';

    return is_file($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * La fuente sin comentarios de Blade, de PHP ni de HTML: solo lo que se ejecuta.
 *
 * Hace falta porque estas pruebas buscan defectos por su nombre —`uniqid`, `x-data`,
 * un color literal— y un componente que EXPLICA en un comentario por qué no usa
 * `uniqid()` daría un falso rojo. Se mide el código, no la prosa.
 */
function codigoCampoNuevo(string $componente): string
{
    $fuente = fuenteCampoNuevo($componente);
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $fuente);
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
    $fuente = (string) preg_replace('#<!--.*?-->#s', '', $fuente);

    return (string) preg_replace('#^\s*//.*$#m', '', $fuente);
}

/** El contenido de los bloques `<style>` de la fuente, ya sin comentarios. */
function cssCampoNuevo(string $componente): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteCampoNuevo($componente), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/** La primera etiqueta de apertura de ese tipo dentro del HTML. */
function etiquetaDelControl(string $html, string $tag): string
{
    preg_match('/<'.preg_quote($tag, '/').'\b[^>]*>/i', $html, $m);

    return $m[0] ?? '';
}

/** El valor de un atributo dentro de una etiqueta ya recortada. */
function attrEn(string $etiqueta, string $atributo): ?string
{
    if (preg_match('/\b'.preg_quote($atributo, '/').'="([^"]*)"/i', $etiqueta, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    }

    return null;
}

/** El HTML interno del elemento con ese id, o null si el id no existe. */
function contenidoConId(string $html, string $id): ?string
{
    $patron = '/<([a-z]+)\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>(.*?)<\/\1>/is';

    return preg_match($patron, $html, $m) ? trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5)) : null;
}

/**
 * El contenido de cada región viva del HTML (`role="status"` o `aria-live`).
 *
 * @return array<int, string>
 */
function regionesVivasDe(string $html): array
{
    preg_match_all('#<(div|span|p)\b[^>]*(?:role="status"|aria-live="(?:polite|assertive)")[^>]*>(.*?)</\1>#is', $html, $m);

    return $m[2];
}

/** Los dos componentes nuevos. */
dataset('campos nuevos', [
    'textarea' => ['textarea'],
    'checkbox' => ['checkbox'],
]);

it('los dos componentes existen y son controles nativos', function () {
    $textarea = Blade::render('<x-muni::textarea name="descripcion" label="Descripción del requerimiento" />');
    $checkbox = Blade::render('<x-muni::checkbox name="acepta" label="Tomo conocimiento" />');

    expect((bool) preg_match('/<textarea\b/i', $textarea))->toBeTrue(
        '«textarea» no emite un <textarea> real: sin él se pierden el redimensionado nativo, el '.
        'dictado y el corrector del navegador.'
    );

    expect((bool) preg_match('/<input\b[^>]*type="checkbox"/i', $checkbox))->toBeTrue(
        '«checkbox» no es un <input type="checkbox"> real. Un <div role="checkbox"> no se envía '.
        'con el formulario, no responde a Espacio sin JS y no lo rellena el navegador.'
    );

    expect((bool) preg_match('/role="checkbox"/i', $checkbox))->toBeFalse(
        '«checkbox» simula la casilla con role="checkbox" en vez de usar el control nativo.'
    );
});

it('la etiqueta queda asociada al control con for e id', function (string $componente) {
    $html = Blade::render('<x-muni::'.$componente.' name="descripcion" label="Descripción del requerimiento" />');

    expect((bool) preg_match('/<label\b[^>]*\bfor="([^"]+)"/i', $html, $m))->toBeTrue(
        "«{$componente}» no emite una <label> con for: el control queda sin nombre accesible (WCAG 2.2 AA 4.1.2)."
    );

    expect(str_contains($html, 'id="'.$m[1].'"'))->toBeTrue(sprintf(
        '«%s»: el for="%s" de la etiqueta no coincide con el id de ningún control.',
        $componente, $m[1]
    ));
})->with('campos nuevos');

it('ata el mensaje de error al control y lo marca como inválido', function (string $componente, string $tag) {
    $html = Blade::render(
        '<x-muni::'.$componente.' name="descripcion" label="Descripción" error="Describe lo que pasó" />'
    );

    $control = etiquetaDelControl($html, $tag);
    $describedBy = attrEn($control, 'aria-describedby');

    expect($describedBy)->not->toBeNull(
        "«{$componente}» no emite aria-describedby: el lector anuncia que hay un error y nunca dice cuál (WCAG 2.2 AA 3.3.1)."
    );

    $encontrado = false;

    foreach (preg_split('/\s+/', trim((string) $describedBy)) ?: [] as $id) {
        if (str_contains((string) contenidoConId($html, $id), 'Describe lo que pasó')) {
            $encontrado = true;
        }
    }

    expect($encontrado)->toBeTrue(sprintf(
        '«%s» apunta con aria-describedby="%s" a elementos que no contienen el mensaje de error.',
        $componente, $describedBy
    ));

    expect(attrEn($control, 'aria-invalid'))->toBe('true',
        "«{$componente}» no emite aria-invalid=\"true\" con error."
    );

    $sano = Blade::render('<x-muni::'.$componente.' name="descripcion" label="Descripción" />');

    expect(attrEn(etiquetaDelControl($sano, $tag), 'aria-invalid'))->not->toBe('true',
        "«{$componente}» se anuncia como inválido incluso sin error."
    );
})->with([
    'textarea' => ['textarea', 'textarea'],
    'checkbox' => ['checkbox', 'input'],
]);

it('el error se anuncia cuando llega por Livewire, sin recarga', function (string $componente) {
    // El error aparece en un round-trip sin recargar: un <span> que nace de la
    // nada no lo lee ningún lector si no hay región viva presente desde el
    // primer render (WCAG 2.2 AA 4.1.3), y además empuja el contenido de abajo.
    $conError = Blade::render('<x-muni::'.$componente.' name="descripcion" label="Descripción" error="Falta el detalle" />');
    $sano = Blade::render('<x-muni::'.$componente.' name="descripcion" label="Descripción" />');

    foreach (['con error' => $conError, 'sin error' => $sano] as $caso => $html) {
        expect((bool) preg_match('/<[a-z]+\b[^>]*\b(?:role="status"|aria-live="polite")[^>]*>/i', $html))->toBeTrue(
            "«{$componente}» ({$caso}) no tiene región viva para el error: el mensaje que llega por Livewire no se lee nunca."
        );
    }
})->with('campos nuevos');

it('el id es estable entre dos renders y no sale de uniqid()', function (string $componente) {
    $uno = Blade::render('<x-muni::'.$componente.' name="descripcion" label="Descripción" />');
    $dos = Blade::render('<x-muni::'.$componente.' name="descripcion" label="Descripción" />');

    preg_match('/<label\b[^>]*\bfor="([^"]+)"/i', $uno, $a);
    preg_match('/<label\b[^>]*\bfor="([^"]+)"/i', $dos, $b);

    expect($a[1] ?? 'a')->toBe($b[1] ?? 'b',
        "«{$componente}» genera un id distinto en cada render: rompe el `for` de la etiqueta tras el primer refresco de Livewire."
    );

    expect(str_contains(codigoCampoNuevo($componente), 'uniqid'))->toBeFalse(
        "«{$componente}» usa uniqid() para el id, prohibido por DESIGN §8."
    );

    // El id que pasa el consumidor manda.
    $propio = Blade::render('<x-muni::'.$componente.' name="descripcion" label="Descripción" id="mi-campo" />');

    expect((bool) preg_match('/<label\b[^>]*\bfor="mi-campo"/i', $propio))->toBeTrue(
        "«{$componente}» ignora el id que pasa el consumidor."
    );

    // Un `name` con notación de arreglo produciría un id inválido y repetido.
    $arreglo = Blade::render('<x-muni::'.$componente.' name="items[0][detalle]" label="Detalle" />');
    preg_match('/<label\b[^>]*\bfor="([^"]+)"/i', $arreglo, $c);

    expect((bool) preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $c[1] ?? ''))->toBeTrue(sprintf(
        '«%s»: un name con notación de arreglo produce el id inválido "%s".',
        $componente, $c[1] ?? ''
    ));
    expect(str_contains($arreglo, 'name="items[0][detalle]"'))->toBeTrue(
        "«{$componente}» altera el `name`: al backend tiene que llegar intacto."
    );
})->with('campos nuevos');

it('la casilla nunca viene marcada si no se pide', function () {
    $suelta = Blade::render('<x-muni::checkbox name="acepta" label="Tomo conocimiento del tratamiento de mis datos" />');

    expect((bool) preg_match('/<input\b[^>]*\bchecked\b/i', $suelta))->toBeFalse(
        'La casilla viene premarcada por defecto. Bajo la Ley 21.719 una casilla premarcada no es '.
        'una manifestación de voluntad: el asiento de licitud queda inservible.'
    );

    expect(str_contains(codigoCampoNuevo('checkbox'), "'checked' => false"))->toBeTrue(
        'La prop `checked` de la casilla no declara `false` como valor por defecto.'
    );

    $marcada = Blade::render('<x-muni::checkbox name="acepta" label="Tomo conocimiento" :checked="true" />');

    expect((bool) preg_match('/<input\b[^>]*\bchecked\b/i', $marcada))->toBeTrue(
        'La prop `checked` no marca la casilla cuando se pide explícitamente.'
    );
});

it('la descripción de la casilla va fuera del label y atada por aria-describedby', function () {
    $html = Blade::render(
        '<x-muni::checkbox name="acepta" label="Tomo conocimiento" '.
        'description="Ley 21.719, artículo 13: el municipio trata estos datos para resolver tu solicitud." />'
    );

    preg_match('/<label\b[^>]*>(.*?)<\/label>/is', $html, $m);

    expect(str_contains($m[1] ?? '', 'Ley 21.719'))->toBeFalse(
        'La descripción se pinta DENTRO del <label>: se concatena al nombre accesible y el lector '.
        'la repite entera cada vez que se enfoca la casilla (el defecto de switch).'
    );

    $describedBy = (string) attrEn(etiquetaDelControl($html, 'input'), 'aria-describedby');
    $textos = array_map(
        fn (string $id) => (string) contenidoConId($html, $id),
        array_filter(preg_split('/\s+/', trim($describedBy)) ?: [])
    );

    expect(str_contains(implode(' | ', $textos), 'Ley 21.719'))->toBeTrue(sprintf(
        'La descripción no está atada con aria-describedby="%s" (apunta a: %s).',
        $describedBy, implode(' | ', $textos)
    ));
});

it('la casilla ofrece un blanco de pulsación de al menos 24x24', function () {
    // Se miden las DOS piezas que forman el blanco: el contenedor que lo reserva y
    // el <input> transparente que recibe el clic. Si cualquiera de las dos se
    // encoge, el objetivo real se encoge, aunque la otra siga midiendo 24.
    $css = cssCampoNuevo('checkbox');

    foreach (['muni-checkbox-hit', 'muni-checkbox-input'] as $clase) {
        expect((bool) preg_match('/\.'.$clase.'\s*\{([^}]*)\}/i', $css, $regla))->toBeTrue(
            "El CSS de la casilla no declara la regla .{$clase}, que es la que reserva el blanco de pulsación."
        );

        foreach (['width', 'height'] as $medida) {
            expect((bool) preg_match('/(?<![a-z-])'.$medida.'\s*:\s*(\d+(?:\.\d+)?)px/i', $regla[1], $valor))->toBeTrue(
                "La regla .{$clase} no declara «{$medida}»: el blanco de pulsación queda al azar del contenido."
            );

            expect((float) $valor[1])->toBeGreaterThanOrEqual(24.0, sprintf(
                '.%s declara %s:%spx. WCAG 2.2 AA 2.5.8 exige 24x24 (es el defecto del riel de switch, 38x22).',
                $clase, $medida, $valor[1]
            ));
        }
    }
});

it('la casilla no necesita Alpine para funcionar', function () {
    // La ficha lo declara: es un control nativo. El estado paralelo de Alpine
    // (`x-model` sobre un input que ya lleva `checked`) es doble verdad.
    expect(str_contains(codigoCampoNuevo('checkbox'), 'x-data'))->toBeFalse(
        'La casilla monta un componente de Alpine sin necesitarlo: el estado del DOM ya es la verdad.'
    );
});

it('el contador del campo largo está en texto y no se anuncia por tecla', function () {
    $html = Blade::render(
        '<x-muni::textarea name="descripcion" label="Descripción del requerimiento" :maxlength="500" />'
    );

    // El maxlength real viaja al HTML: el objetivo es que el vecino no pueda
    // pasarse, no que se entere después de perder el formulario.
    expect(str_contains(etiquetaDelControl($html, 'textarea'), 'maxlength="500"'))->toBeTrue(
        'El <textarea> no emite maxlength: el texto se corta en el servidor y el vecino pierde lo escrito.'
    );

    expect((bool) preg_match('/500/', strip_tags($html)))->toBeTrue(
        'El contador no está en TEXTO: sin JS, o con un lector de pantalla, no hay forma de saber cuánto queda.'
    );

    expect((bool) preg_match('/caracteres/iu', strip_tags($html)))->toBeTrue(
        'El contador no dice en palabras de qué son los números que muestra.'
    );

    $regiones = regionesVivasDe($html);

    expect($regiones)->not->toBeEmpty(
        'El campo largo no tiene ninguna región viva: el aviso de límite no se anuncia (WCAG 2.2 AA 4.1.3).'
    );

    foreach ($regiones as $contenido) {
        expect(str_contains($contenido, '500'))->toBeFalse(
            'El contador visible vive DENTRO de una región viva: el lector de pantalla lo relee en cada tecla.'
        );
    }

    // La región habla por umbrales, no por carácter.
    expect(str_contains(codigoCampoNuevo('textarea'), 'umbral'))->toBeTrue(
        'El aviso del contador no está guardado por umbrales: se actualiza letra a letra.'
    );

    // Y sin `maxlength` no hay contador que anunciar.
    $sinTope = Blade::render('<x-muni::textarea name="descripcion" label="Descripción" />');

    expect(str_contains(strip_tags($sinTope), 'caracteres'))->toBeFalse(
        'Sin maxlength el campo largo pinta un contador igual.'
    );
});

it('el campo largo funciona sin JS: manda el rows y el crecimiento es mejora', function () {
    $html = Blade::render('<x-muni::textarea name="relato" label="Relato del procedimiento" :rows="6" />');

    expect(str_contains(etiquetaDelControl($html, 'textarea'), 'rows="6"'))->toBeTrue(
        'El <textarea> no respeta la prop `rows`: sin JS el campo queda del alto de fábrica.'
    );

    $css = cssCampoNuevo('textarea');

    expect(str_contains($css, 'field-sizing'))->toBeTrue(
        'El crecimiento no usa `field-sizing: content`: el camino sin JS y sin reflow es el principal, '.
        'y el truco de medir scrollHeight queda solo de respaldo.'
    );

    expect((bool) preg_match('/@supports[^{]*field-sizing/i', $css))->toBeTrue(
        'Se usa `field-sizing` sin `@supports`: CSS moderno va como mejora progresiva (DESIGN §8).'
    );
});

it('los dos tienen foco visible por outline, no solo por sombra', function (string $componente) {
    $css = cssCampoNuevo($componente);

    expect((bool) preg_match('/:focus(?:-visible)?[^{]*\{[^}]*outline\s*:\s*3px solid var\(--muni-focus/i', $css))->toBeTrue(
        "«{$componente}» no declara el indicador de foco canónico (DESIGN §5): outline de 3px, no box-shadow, ".
        'que dentro de Filament se computa transparente.'
    );
})->with('campos nuevos');

it('ninguno de los dos escribe un color literal', function (string $componente) {
    // DESIGN §1: los componentes no tienen colores, tienen tokens. La única
    // excepción es el #767676 del tercer respaldo del outline, que es el gris
    // canónico de doble tema y solo aparece si la hoja de tokens no cargó.
    $fuente = str_replace('#767676', '', codigoCampoNuevo($componente));

    preg_match_all('/#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?)\s*\(/', $fuente, $m);

    expect($m[0])->toBeEmpty(sprintf(
        '«%s» escribe colores literales (%s): un adoptante no puede cambiarlos sin editar el paquete (DESIGN §1).',
        $componente, implode(', ', array_unique($m[0]))
    ));
})->with('campos nuevos');

it('el movimiento sale de los tokens y ninguno usa directivas de un solo major', function (string $componente) {
    $fuente = codigoCampoNuevo($componente);

    preg_match_all('/transition[^;{}]*/i', $fuente, $m);

    foreach ($m[0] as $transicion) {
        expect((bool) preg_match('/\d+(?:\.\d+)?m?s/i', $transicion))->toBeFalse(sprintf(
            '«%s» anima con una duración fija («%s»): ignora prefers-reduced-motion, que viaja en --muni-dur (DESIGN §6).',
            $componente, trim($transicion)
        ));
    }

    // El paquete se instala en Livewire 3 (personas-graneros) y en Livewire 4.
    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($fuente, $prohibida))->toBeFalse(sprintf(
            '«%s» usa «%s», que no existe en los dos majors de Livewire (DESIGN §8).',
            $componente, $prohibida
        ));
    }
})->with('campos nuevos');
