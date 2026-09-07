<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LOS DOS CAMPOS CHILENOS
|--------------------------------------------------------------------------
|
| `<x-muni::rut-input>` es el campo que se escribe todo el día en el mesón: la
| búsqueda del titular en la recepción ARCOP, el alta de una patente, el inicio
| de una solicitud de licencia. `<x-muni::date-input>` es la fecha en formato
| chileno: el vencimiento de una licencia clase B, la fecha de la fiscalización
| y el desde–hasta de los reportes.
|
| Los dos nacen con las condiciones escritas como prueba ANTES del código:
|
| 1. SE COMPONEN SOBRE `<x-muni::input>`. Ese componente acaba de repararse
|    (aria-describedby encadenado, ayuda y error conviviendo, región viva, id
|    derivado del `name`). Duplicar su marcado sería duplicar también el
|    próximo defecto.
| 2. EL DÍGITO VERIFICADOR SE COMPRUEBA, y el veredicto es TEXTO, no un borde
|    rojo (WCAG 2.2 AA 1.4.1 y 3.3.1). La comprobación del cliente solo ADELANTA
|    el veredicto: la del servidor manda y si divergen gana PHP.
| 3. LA K CUENTA. El DV puede ser K, mayúscula o minúscula, y se normaliza. Un
|    componente que solo acepta dígitos deja fuera a uno de cada once RUT.
| 4. EL VALOR QUE VIAJA ES PREDECIBLE Y ÚNICO. Un solo campo con `name`: el que
|    se ve es el que se envía. Nada de un oculto que se queda vacío cuando el JS
|    no cargó, ni de `x-model` peleando con `wire:model` sobre dos inputs.
| 5. LA FECHA ES EL CONTROL NATIVO. `<input type="date">` trae calendario,
|    teclado e idioma del sistema; `calendar` de este paquete está roto por
|    teclado y no se repite ese error. El valor nativo viaja en `aaaa-mm-dd` y
|    el funcionario lee `dd-mm-aaaa`: la diferencia se dice en la ayuda y se
|    muestra resuelta en pantalla.
| 6. EL RANGO ES COHERENTE. Si el «hasta» es anterior al «desde», se dice en
|    texto, no se deja salir un reporte vacío que nadie sabe explicar.
| 7. NI `uniqid()` (DESIGN §8) NI UN COLOR LITERAL (DESIGN §1), salvo el
|    `#767676` canónico del respaldo del outline.
|
| Se prueba con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra cadena a buscar, no un mensaje, y la
| aserción se desactiva sin avisar.
*/

/** La fuente Blade cruda de uno de los dos campos chilenos. */
function fuenteCampoChileno(string $componente): string
{
    $ruta = __DIR__.'/../resources/views/components/'.$componente.'.blade.php';

    return is_file($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * La fuente sin comentarios: solo lo que se ejecuta.
 *
 * Estas pruebas buscan defectos por su nombre —`uniqid`, `x-mask`, un color
 * literal— y un componente que EXPLICA en un comentario por qué NO los usa
 * daría un falso rojo.
 */
function codigoCampoChileno(string $componente): string
{
    $fuente = fuenteCampoChileno($componente);
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $fuente);
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
    $fuente = (string) preg_replace('#<!--.*?-->#s', '', $fuente);

    return (string) preg_replace('#^\s*//.*$#m', '', $fuente);
}

/** Renderiza uno de los dos con atributos literales. */
function renderChileno(string $componente, array $atributos = []): string
{
    $attrs = [];

    foreach ($atributos as $nombre => $valor) {
        if (is_bool($valor)) {
            if ($valor) {
                $attrs[] = $nombre;
            }

            continue;
        }

        $attrs[] = $nombre.'="'.$valor.'"';
    }

    return Blade::render('<x-muni::'.$componente.' '.implode(' ', $attrs).' />');
}

/**
 * Todas las etiquetas `<input>` del HTML.
 *
 * @return array<int, string>
 */
function inputsChilenos(string $html): array
{
    preg_match_all('/<input\b[^>]*>/i', $html, $m);

    return $m[0];
}

/** La primera etiqueta `<input>` que lleva ese `name`. */
function inputConName(string $html, string $name): string
{
    foreach (inputsChilenos($html) as $etiqueta) {
        if (str_contains($etiqueta, 'name="'.$name.'"')) {
            return $etiqueta;
        }
    }

    return '';
}

/** El valor de un atributo dentro de una etiqueta ya recortada. */
function attrChileno(string $etiqueta, string $atributo): ?string
{
    if (preg_match('/\s'.preg_quote($atributo, '/').'="([^"]*)"/i', $etiqueta, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    }

    return null;
}

/** El texto del elemento con ese id, o null si el id no existe en el HTML. */
function textoIdChileno(string $html, string $id): ?string
{
    $patron = '/<([a-z]+)\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>(.*?)<\/\1>/is';

    return preg_match($patron, $html, $m) ? trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5)) : null;
}

/** Los textos a los que apunta el `aria-describedby` de una etiqueta. */
function descripcionesDe(string $html, string $etiqueta): string
{
    $ids = array_filter(preg_split('/\s+/', trim((string) attrChileno($etiqueta, 'aria-describedby'))) ?: []);

    return implode(' | ', array_map(fn (string $id) => (string) textoIdChileno($html, $id), $ids));
}

/** El contenido de cada región viva del HTML (`role="status"` o `aria-live`). */
function regionesChilenas(string $html): array
{
    preg_match_all('#<(div|span|p)\b[^>]*(?:role="status"|role="alert"|aria-live="(?:polite|assertive)")[^>]*>(.*?)</\1>#is', $html, $m);

    return $m[2];
}

/** Los dos campos chilenos. */
dataset('campos chilenos', [
    'rut-input' => ['rut-input'],
    'date-input' => ['date-input'],
]);

it('los dos componentes existen y se renderizan', function (string $componente) {
    expect(fuenteCampoChileno($componente))->not->toBe('',
        "«{$componente}.blade.php» no existe en resources/views/components."
    );

    expect(trim(renderChileno($componente, ['name' => 'campo', 'label' => 'Campo'])))->not->toBe('',
        "«{$componente}» se renderiza vacío."
    );
})->with('campos chilenos');

it('los dos se componen sobre <x-muni::input> en vez de duplicar su marcado', function (string $componente) {
    // input acaba de repararse: aria-describedby encadenado, ayuda y error
    // conviviendo, región viva y el id derivado del `name`. Un componente que
    // copia su marcado copia también el próximo defecto.
    $codigo = codigoCampoChileno($componente);

    expect(str_contains($codigo, '<x-muni::input'))->toBeTrue(
        "«{$componente}» no compone <x-muni::input>: duplica el marcado del campo base."
    );

    expect((bool) preg_match('/\.muni-input\s*\{/', $codigo))->toBeFalse(
        "«{$componente}» redefine el estilo base `.muni-input`, que es de input.blade.php."
    );
})->with('campos chilenos');

it('rut-input rechaza en texto un dígito verificador que no cuadra', function () {
    // 12.345.678 tiene DV 5, no 9: el «12.345.678-9» del placeholder de media
    // municipalidad es, literalmente, un RUT inválido.
    $html = renderChileno('rut-input', ['name' => 'rut', 'label' => 'RUT', 'value' => '12.345.678-9']);

    $control = inputConName($html, 'rut');

    expect($control)->not->toBe('', 'rut-input no emite un input con el name que se le pasó.');

    expect(attrChileno($control, 'aria-invalid'))->toBe('true',
        'rut-input no marca aria-invalid con un dígito verificador que no cuadra.'
    );

    $descripciones = descripcionesDe($html, $control);

    // «no corresponde» es del mensaje de error; la ayuda del campo también habla
    // del dígito verificador y haría pasar la prueba sin que el error exista.
    expect((bool) preg_match('/no corresponde/iu', $descripciones))->toBeTrue(sprintf(
        'rut-input no ata el veredicto del dígito verificador con aria-describedby (apunta a: %s). '.
        'El error existe solo como borde rojo: 1.4.1 y 3.3.1 incumplidos.',
        $descripciones
    ));

    expect((bool) preg_match('/no corresponde/iu', implode(' | ', regionesChilenas($html))))->toBeTrue(
        'rut-input pinta el veredicto fuera de toda región viva: cuando el juicio lo emite '.
        'Alpine al salir del campo, el lector de pantalla no lo lee nunca (WCAG 2.2 AA 4.1.3).'
    );
});

it('rut-input acepta el dígito verificador correcto, incluida la K', function (string $valor) {
    $html = renderChileno('rut-input', ['name' => 'rut', 'label' => 'RUT', 'value' => $valor]);

    $control = inputConName($html, 'rut');

    expect(attrChileno($control, 'aria-invalid'))->not->toBe('true', sprintf(
        'rut-input da por inválido el RUT «%s», que sí cuadra.', $valor
    ));

    // El texto del mensaje vive siempre en el data-* de la región, para que Alpine
    // lo lea sin llevárselo dentro de una expresión: lo que se mide es lo que se
    // VE, o sea el contenido de las regiones vivas.
    expect((bool) preg_match('/no corresponde/iu', implode(' | ', regionesChilenas($html))))->toBeFalse(sprintf(
        'rut-input pinta el error del dígito verificador con el RUT válido «%s».', $valor
    ));
})->with([
    'cuerpo con DV numérico' => ['12.345.678-5'],
    'sin puntos ni guion' => ['123456785'],
    'DV K mayúscula' => ['12.345.670-K'],
    'DV k minúscula' => ['12345670k'],
]);

it('el valor que envía rut-input es el RUT formateado, en un único campo', function () {
    /*
     * LA DECISIÓN, y está documentada en el componente: viaja el valor
     * FORMATEADO —`12.345.678-5`— en el mismo input que se ve. Un solo `name`.
     *
     * Por qué no un oculto normalizado: sin JS ese oculto se queda con lo que
     * puso el servidor y lo que el vecino escribió no llega nunca. Un solo
     * campo también evita que `x-model` pelee con el `wire:model` del host.
     */
    $html = renderChileno('rut-input', ['name' => 'rut', 'label' => 'RUT', 'value' => '123456785']);

    expect(substr_count($html, 'name="rut"'))->toBe(1,
        'rut-input emite el name «rut» más de una vez: el backend recibe el último y nadie sabe cuál.'
    );

    expect(str_contains($html, 'type="hidden"'))->toBeFalse(
        'rut-input usa un input oculto: sin JS se envía vacío y el vecino pierde lo que escribió.'
    );

    expect(attrChileno(inputConName($html, 'rut'), 'value'))->toBe('12.345.678-5',
        'rut-input no envía el RUT formateado que documenta enviar.'
    );

    // Y las cifras usan la firma del sistema: mono tabular.
    expect(str_contains((string) attrChileno(inputConName($html, 'rut'), 'class'), 'muni-num'))->toBeTrue(
        'rut-input no marca el campo con `.muni-num`: el RUT sale en sans proporcional y baila al escribirlo.'
    );
});

it('date-input emite el control de fecha nativo y no un calendario propio', function () {
    $html = renderChileno('date-input', ['name' => 'vence', 'label' => 'Vence']);

    expect(attrChileno(inputConName($html, 'vence'), 'type'))->toBe('date',
        'date-input no emite <input type="date">: pierde el calendario, el teclado y el idioma del sistema.'
    );

    // `calendar` de este paquete está documentado como roto por teclado; no se
    // repite el error dibujando otra rejilla.
    expect((bool) preg_match('/role="(grid|gridcell)"/', $html))->toBeFalse(
        'date-input dibuja una rejilla de calendario propia en vez de apoyarse en el control nativo.'
    );
});

it('date-input dice en la ayuda el formato chileno y muestra la fecha en dd-mm-aaaa', function () {
    $html = renderChileno('date-input', ['name' => 'vence', 'label' => 'Vence', 'value' => '2026-12-31']);

    expect(str_contains($html, 'dd-mm-aaaa'))->toBeTrue(
        'date-input no dice el formato esperado en la ayuda: el formato en pantalla lo decide la '.
        'configuración regional del equipo, y un puesto en inglés muestra mm/dd/aaaa.'
    );

    expect(str_contains($html, '31-12-2026'))->toBeTrue(
        'date-input no muestra la fecha elegida en dd-mm-aaaa: el funcionario lee y dicta en ese '.
        'formato y el control nativo puede estar mostrándole otro.'
    );

    // El valor que viaja es el ISO nativo, intacto: es lo que documenta.
    expect(attrChileno(inputConName($html, 'vence'), 'value'))->toBe('2026-12-31',
        'date-input altera el valor nativo: al backend tiene que llegar aaaa-mm-dd sin ambigüedad.'
    );
});

it('date-input acepta min y max y no deja el hasta antes del desde', function () {
    $simple = renderChileno('date-input', [
        'name' => 'vence', 'label' => 'Vence', 'min' => '2026-01-01', 'max' => '2026-12-31',
    ]);

    $control = inputConName($simple, 'vence');

    expect(attrChileno($control, 'min'))->toBe('2026-01-01', 'date-input ignora la prop `min`.');
    expect(attrChileno($control, 'max'))->toBe('2026-12-31', 'date-input ignora la prop `max`.');

    $rango = renderChileno('date-input', [
        'range' => true,
        'legend' => 'Periodo del reporte',
        'from-name' => 'desde',
        'to-name' => 'hasta',
        'from-value' => '2026-03-10',
        'to-value' => '2026-01-05',
    ]);

    expect((bool) preg_match('/<fieldset\b/i', $rango))->toBeTrue(
        'date-input en modo rango no agrupa los dos extremos en un <fieldset> con <legend>.'
    );
    expect((bool) preg_match('/<legend\b[^>]*>\s*Periodo del reporte/i', $rango))->toBeTrue(
        'date-input en modo rango no rotula el grupo con la prop `legend`.'
    );

    $mensajes = implode(' | ', regionesChilenas($rango));

    expect((bool) preg_match('/anterior/iu', $mensajes))->toBeTrue(
        'date-input no dice EN TEXTO, y en una región viva, que el hasta es anterior al desde: '.
        'el reporte sale vacío y nadie sabe por qué.'
    );

    $hasta = inputConName($rango, 'hasta');

    expect(attrChileno($hasta, 'aria-invalid'))->toBe('true',
        'date-input no marca como inválido el extremo que rompe el orden del periodo.'
    );
    expect((bool) preg_match('/anterior/iu', descripcionesDe($rango, $hasta)))->toBeTrue(
        'date-input no ata el mensaje de orden al campo «hasta» con aria-describedby.'
    );
});

it('los dos atan el error del servidor al campo con aria-describedby', function (string $componente) {
    $html = renderChileno($componente, [
        'name' => 'campo',
        'label' => 'Campo',
        'error' => 'Este dato es obligatorio',
    ]);

    $control = inputConName($html, 'campo');

    expect(attrChileno($control, 'aria-invalid'))->toBe('true',
        "«{$componente}» no se anuncia como inválido con el error del servidor."
    );

    expect(str_contains(descripcionesDe($html, $control), 'Este dato es obligatorio'))->toBeTrue(sprintf(
        '«%s» no ata el error del servidor con aria-describedby (apunta a: %s).',
        $componente, descripcionesDe($html, $control)
    ));

    // Y sin error no se anuncia inválido.
    $sano = renderChileno($componente, ['name' => 'campo', 'label' => 'Campo']);

    expect(attrChileno(inputConName($sano, 'campo'), 'aria-invalid'))->not->toBe('true',
        "«{$componente}» se anuncia como inválido incluso sin error."
    );
})->with('campos chilenos');

it('el id de los dos es el mismo en dos renders seguidos y no sale de uniqid()', function (string $componente) {
    $primero = attrChileno(inputConName(renderChileno($componente, ['name' => 'campo']), 'campo'), 'id');
    $segundo = attrChileno(inputConName(renderChileno($componente, ['name' => 'campo']), 'campo'), 'id');

    expect($primero)->not->toBeNull("«{$componente}» no emite id.");
    expect($primero)->toBe($segundo, sprintf(
        '«%s» genera un id distinto en cada render (%s vs %s): rompe el `for` de la etiqueta '.
        'y el diffing de Livewire.',
        $componente, $primero, $segundo
    ));

    expect(str_contains(codigoCampoChileno($componente), 'uniqid('))->toBeFalse(
        "«{$componente}.blade.php» genera ids con uniqid(), que DESIGN §8 prohíbe."
    );
})->with('campos chilenos');

it('ninguno de los dos escribe un color literal', function (string $componente) {
    // DESIGN §1: los componentes no tienen colores, tienen tokens. La única
    // excepción es el #767676 del tercer respaldo del outline.
    $fuente = str_replace('#767676', '', codigoCampoChileno($componente));

    preg_match_all(
        '/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(|:\s*(?:white|black|red|green|blue|gray|grey|orange|yellow)\b/i',
        $fuente, $m
    );

    expect($m[0])->toBe([], sprintf(
        '«%s» escribe colores literales (%s): un adoptante no puede cambiarlos sin editar el paquete (DESIGN §1).',
        $componente, implode(', ', $m[0])
    ));
})->with('campos chilenos');

it('ninguno depende de un plugin de Alpine ni de un major de Livewire', function (string $componente) {
    $codigo = codigoCampoChileno($componente);

    $prohibidos = [];

    foreach (['x-mask', '@island', 'wire:show', 'wire:sort', '#[Transition]', 'x-intersect', 'x-collapse'] as $aguja) {
        if (str_contains($codigo, $aguja)) {
            $prohibidos[] = $aguja;
        }
    }

    expect($prohibidos)->toBe([], sprintf(
        '«%s» usa %s. El plugin Mask de Alpine NO viaja en el bundle de Livewire y las directivas '.
        'de un major rompen personas-graneros, que sigue en Livewire 3.',
        $componente, implode(', ', $prohibidos)
    ));
})->with('campos chilenos');

it('el movimiento de los dos sale de los tokens, no de una duración fija', function (string $componente) {
    preg_match_all('/transition[^;"]*:\s*([^;"]+)/i', codigoCampoChileno($componente), $m);

    $fijas = array_values(array_filter($m[1], fn (string $v) => (bool) preg_match('/\d+(\.\d+)?m?s/', $v)));

    expect($fijas)->toBe([], sprintf(
        '«%s» anima con una duración fija (%s): ignora prefers-reduced-motion, que --muni-dur ya respeta.',
        $componente, implode(' | ', $fijas)
    ));
})->with('campos chilenos');
