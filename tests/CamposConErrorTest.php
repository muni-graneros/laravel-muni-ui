<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DEL ERROR Y LA AYUDA DE LOS CAMPOS
|--------------------------------------------------------------------------
|
| `<x-muni::input>`, `<x-muni::select>` y `<x-muni::switch>` son los controles
| del formulario público «Ingresa tu solicitud» de Atención al Vecino y de las
| bandejas de los mesones. Llegaron a producción con seis defectos medidos:
|
| 1. NI UNA SOLA aparición de `aria-describedby` en los 54 componentes. `input`
|    emitía `aria-invalid`, o sea anunciaba que hay un error y no decía cuál;
|    `select` ni siquiera emitía `aria-invalid`. El error existía solo como
|    color rojo y proximidad visual: eso es 1.4.1 incumplido y 3.3.1 sin
|    cumplir.
| 2. EL `@elseif ($hint)` BORRABA LA AYUDA cuando había error. El campo que
|    dice «formato 12.345.678-9» perdía justo la instrucción para corregirse.
|    Los dos tienen que convivir, cada uno con su id, y los dos identificadores
|    encadenados en `aria-describedby` (es lo que documenta CoreUI para el modo
|    servidor, que es el de Laravel).
| 3. EL ID SALÍA DE `uniqid()` cuando no había `name`: cambia en cada render,
|    rompe el `for` de la etiqueta y ensucia el diffing de Livewire (DESIGN §10).
| 4. `$attributes->merge()` REEMPLAZA, NO CONCATENA: el `aria-describedby` del
|    consumidor ganaba y se perdían los ids del componente (o al revés). Hay que
|    leerlo y encadenarlo a mano antes del merge.
| 5. UN `name` CON NOTACIÓN DE ARREGLO (`items[0][rut]`, lo normal en un
|    formulario repetido de Livewire) producía ids inválidos y duplicados.
| 6. EL ERROR LLEGA POR LIVEWIRE SIN RECARGA: un `<span>` que aparece de la nada
|    no lo lee ningún lector de pantalla (WCAG 2.2 AA 4.1.3). Necesita
|    `role="alert"` o una región viva presente desde el primer render, con el
|    alto reservado para no producir desplazamiento de contenido.
|
| Se prueba sobre el HTML renderizado con `expect(bool)->toBeTrue('mensaje')`:
| en Pest el segundo argumento de `toContain()` es otra cadena a buscar, no un
| mensaje, y la aserción se desactiva sin avisar.
*/

/** Renderiza un componente del paquete con atributos literales. */
function campoMuni(string $componente, array $atributos = []): string
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

/** La etiqueta del control real (`<input>` o `<select>`) del HTML dado. */
function controlDe(string $html): string
{
    preg_match('/<(?:input|select)\b[^>]*>/', $html, $m);

    return $m[0] ?? '';
}

/** El valor de un atributo dentro de una etiqueta ya recortada. */
function atributoDe(string $etiqueta, string $atributo): ?string
{
    if (preg_match('/\b'.preg_quote($atributo, '/').'="([^"]*)"/i', $etiqueta, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    }

    return null;
}

/** El código de un componente sin sus comentarios: lo que de verdad se ejecuta. */
function codigoDe(string $componente): string
{
    $fuente = (string) file_get_contents(__DIR__.'/../resources/views/components/'.$componente.'.blade.php');

    return (string) preg_replace(['/\{\{--.*?--\}\}/s', '#/\*.*?\*/#s'], '', $fuente);
}

/** El texto del elemento con ese id, o null si el id no existe en el HTML. */
function textoDelId(string $html, string $id): ?string
{
    $patron = '/<([a-z]+)\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>(.*?)<\/\1>/is';

    return preg_match($patron, $html, $m) ? trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5)) : null;
}

/** Los tres controles de formulario del paquete y su prop de ayuda. */
dataset('controles', [
    'input' => ['input'],
    'select' => ['select'],
    'switch' => ['switch'],
]);

/** Los dos que además tienen texto de ayuda propio. */
dataset('controles con ayuda', [
    'input' => ['input'],
    'select' => ['select'],
]);

it('ata el mensaje de error al campo con aria-describedby', function (string $componente) {
    $html = campoMuni($componente, [
        'name' => 'rut',
        'label' => 'RUT',
        'error' => 'Ingresa un RUT valido',
    ]);

    $control = controlDe($html);
    $describedBy = atributoDe($control, 'aria-describedby');

    expect($describedBy)->not->toBeNull(
        "«{$componente}» no emite aria-describedby: el lector de pantalla anuncia que hay un ".
        'error y nunca dice cuál (WCAG 2.2 AA 3.3.1).'
    );

    $encontrado = false;

    foreach (preg_split('/\s+/', trim((string) $describedBy)) as $id) {
        if (str_contains((string) textoDelId($html, $id), 'Ingresa un RUT valido')) {
            $encontrado = true;
        }
    }

    expect($encontrado)->toBeTrue(sprintf(
        '«%s» apunta con aria-describedby="%s" a elementos que no contienen el mensaje de error.',
        $componente, $describedBy
    ));
})->with('controles');

it('marca el campo como inválido para la tecnología asistiva', function (string $componente) {
    $html = campoMuni($componente, ['name' => 'rut', 'label' => 'RUT', 'error' => 'Falta el RUT']);

    expect(atributoDe(controlDe($html), 'aria-invalid'))->toBe('true',
        "«{$componente}» no emite aria-invalid=\"true\" con error: el campo no se anuncia como inválido."
    );

    $sano = campoMuni($componente, ['name' => 'rut', 'label' => 'RUT']);

    expect(atributoDe(controlDe($sano), 'aria-invalid'))->not->toBe('true',
        "«{$componente}» se anuncia como inválido incluso sin error."
    );
})->with('controles');

it('la ayuda y el error conviven, y los dos ids quedan encadenados', function (string $componente) {
    $html = campoMuni($componente, [
        'name' => 'rut',
        'label' => 'RUT',
        'hint' => 'Formato 12.345.678-9',
        'error' => 'Ingresa un RUT valido',
    ]);

    // El `@elseif ($hint)` borraba la instrucción justo cuando el vecino la
    // necesita: cuando se equivocó.
    expect(str_contains($html, 'Formato 12.345.678-9'))->toBeTrue(
        "«{$componente}» borra el texto de ayuda cuando hay error: el usuario pierde la ".
        'instrucción que necesita para corregir.'
    );
    expect(str_contains($html, 'Ingresa un RUT valido'))->toBeTrue(
        "«{$componente}» no pinta el mensaje de error."
    );

    $describedBy = (string) atributoDe(controlDe($html), 'aria-describedby');
    $ids = array_filter(preg_split('/\s+/', trim($describedBy)) ?: []);

    $textos = array_map(fn (string $id) => (string) textoDelId($html, $id), $ids);
    $juntos = implode(' | ', $textos);

    expect(str_contains($juntos, 'Formato 12.345.678-9'))->toBeTrue(sprintf(
        '«%s»: la ayuda no está encadenada en aria-describedby="%s" (apunta a: %s).',
        $componente, $describedBy, $juntos
    ));
    expect(str_contains($juntos, 'Ingresa un RUT valido'))->toBeTrue(sprintf(
        '«%s»: el error no está encadenado en aria-describedby="%s" (apunta a: %s).',
        $componente, $describedBy, $juntos
    ));
    expect(count($ids))->toBeGreaterThanOrEqual(2, sprintf(
        '«%s»: aria-describedby="%s" no encadena los DOS identificadores.',
        $componente, $describedBy
    ));
})->with('controles con ayuda');

it('el mensaje se anuncia cuando el error llega por Livewire, sin recarga', function (string $componente) {
    // Con Livewire el error aparece en un round-trip sin recargar la página: un
    // <span> que se inserta de la nada no lo lee ningún lector de pantalla si no
    // es un role="alert" o una región viva ya presente (WCAG 2.2 AA 4.1.3).
    $html = campoMuni($componente, ['name' => 'rut', 'label' => 'RUT', 'error' => 'Falta el RUT']);

    $anunciado = (bool) preg_match(
        '/<[a-z]+\b[^>]*\b(?:role="alert"|aria-live="(?:polite|assertive)")[^>]*>/i',
        $html
    );

    expect($anunciado)->toBeTrue(
        "«{$componente}» pinta el error sin role=\"alert\" ni región viva: en el panel Filament ".
        'el error llega por Livewire y el lector de pantalla no lo lee nunca.'
    );

    // Y la región tiene que existir ANTES de que haya error, con el alto
    // reservado: si nace con el mensaje, el contenido de abajo salta (CLS).
    $sano = campoMuni($componente, ['name' => 'rut', 'label' => 'RUT']);

    expect((bool) preg_match('/<[a-z]+\b[^>]*\b(?:role="alert"|aria-live=)[^>]*>/i', $sano))->toBeTrue(
        "«{$componente}»: la región del mensaje no existe hasta que hay error."
    );
    expect((bool) preg_match('/min-height:\s*[0-9]/i', $sano))->toBeTrue(
        "«{$componente}»: la región del mensaje no reserva alto y el error empuja el ".
        'contenido de abajo al aparecer.'
    );
})->with('controles');

it('el id del campo es el mismo en dos renders seguidos', function (string $componente) {
    // Sin `name` el id salía de uniqid(): cambiaba en cada render, rompía el
    // `for` de la etiqueta y ensuciaba el diffing de Livewire (DESIGN §10).
    $primero = atributoDe(controlDe(campoMuni($componente, ['label' => 'RUT'])), 'id');
    $segundo = atributoDe(controlDe(campoMuni($componente, ['label' => 'RUT'])), 'id');

    expect($primero)->not->toBeNull("«{$componente}» no emite id.");
    expect($primero)->toBe($segundo, sprintf(
        '«%s» genera un id distinto en cada render (%s vs %s): rompe el `for` de la etiqueta '.
        'y el diffing de Livewire.',
        $componente, $primero, $segundo
    ));

    expect(str_contains(codigoDe($componente), 'uniqid('))->toBeFalse(
        "«{$componente}.blade.php» sigue generando ids con uniqid(), que DESIGN §8 prohíbe."
    );
})->with('controles');

it('el id explícito del consumidor manda sobre el generado', function (string $componente) {
    $html = campoMuni($componente, ['name' => 'rut', 'label' => 'RUT', 'id' => 'rut-del-anfitrion']);

    $control = controlDe($html);

    expect(atributoDe($control, 'id'))->toBe('rut-del-anfitrion',
        "«{$componente}» ignora el id que pasa el consumidor."
    );

    // Y no puede quedar el atributo dos veces: gana el primero y el `for` de la
    // etiqueta apunta al que perdió.
    expect(substr_count($control, ' id='))->toBe(1,
        "«{$componente}» imprime el atributo id dos veces."
    );

    expect((bool) preg_match('/<label\b[^>]*for="rut-del-anfitrion"/', $html))->toBeTrue(
        "«{$componente}»: la etiqueta no apunta al id del consumidor."
    );
})->with('controles');

it('no se pierde el aria-describedby que pasa el consumidor', function (string $componente) {
    // $attributes->merge() REEMPLAZA: si el componente imprime el suyo, uno de
    // los dos desaparece sin aviso.
    $html = campoMuni($componente, [
        'name' => 'rut',
        'label' => 'RUT',
        'error' => 'Falta el RUT',
        'aria-describedby' => 'ayuda-del-anfitrion',
    ]);

    $control = controlDe($html);
    $describedBy = (string) atributoDe($control, 'aria-describedby');
    $ids = array_filter(preg_split('/\s+/', trim($describedBy)) ?: []);

    expect(substr_count($control, 'aria-describedby='))->toBe(1, sprintf(
        '«%s» imprime aria-describedby dos veces: el navegador se queda con el primero.',
        $componente
    ));

    expect(in_array('ayuda-del-anfitrion', $ids, true))->toBeTrue(sprintf(
        '«%s» se come el aria-describedby del consumidor (quedó "%s").',
        $componente, $describedBy
    ));

    $textos = implode(' | ', array_map(fn (string $id) => (string) textoDelId($html, $id), $ids));

    expect(str_contains($textos, 'Falta el RUT'))->toBeTrue(sprintf(
        '«%s»: al respetar el aria-describedby del consumidor se perdió el del error (quedó "%s").',
        $componente, $describedBy
    ));
})->with('controles');

it('un name con notación de arreglo produce un id válido y único', function (string $componente) {
    // `items[0][rut]` es lo normal en un formulario repetido de Livewire.
    $primera = campoMuni($componente, ['name' => 'items[0][rut]', 'label' => 'RUT']);
    $segunda = campoMuni($componente, ['name' => 'items[1][rut]', 'label' => 'RUT']);

    $idPrimera = (string) atributoDe(controlDe($primera), 'id');
    $idSegunda = (string) atributoDe(controlDe($segunda), 'id');

    foreach (['items[0][rut]' => $idPrimera, 'items[1][rut]' => $idSegunda] as $name => $id) {
        expect((bool) preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $id))->toBeTrue(sprintf(
            '«%s»: el name «%s» produce el id «%s», que no es un selector válido.',
            $componente, $name, $id
        ));
    }

    expect($idPrimera)->not->toBe($idSegunda, sprintf(
        '«%s»: dos filas del mismo formulario repetido comparten el id «%s».',
        $componente, $idPrimera
    ));

    // El name original viaja intacto al servidor: solo se sanea el id.
    expect(str_contains($primera, 'name="items[0][rut]"'))->toBeTrue(
        "«{$componente}» alteró el name, que es lo que recibe el backend."
    );

    expect((bool) preg_match('/<label\b[^>]*for="'.preg_quote($idPrimera, '/').'"/', $primera))->toBeTrue(
        "«{$componente}»: la etiqueta no apunta al id saneado."
    );
})->with('controles');

it('las props públicas no cambiaron de nombre', function () {
    // Los tres ya están desplegados en nueve sistemas: el arreglo es aditivo.
    $input = campoMuni('input', ['name' => 'rut', 'label' => 'RUT', 'type' => 'tel', 'hint' => 'Con guion', 'required' => true]);
    expect(str_contains($input, 'name="rut"'))->toBeTrue('input: la prop `name` dejó de funcionar.');
    expect(str_contains($input, 'type="tel"'))->toBeTrue('input: la prop `type` dejó de funcionar.');
    expect(str_contains($input, 'Con guion'))->toBeTrue('input: la prop `hint` dejó de funcionar.');
    expect((bool) preg_match('/<input\b[^>]*\brequired\b/', $input))->toBeTrue('input: la prop `required` dejó de funcionar.');

    $select = campoMuni('select', ['name' => 'comuna', 'label' => 'Comuna', 'placeholder' => 'Elige una']);
    expect(str_contains($select, 'Elige una'))->toBeTrue('select: la prop `placeholder` dejó de funcionar.');

    $conOpciones = Blade::render(
        '<x-muni::select name="comuna" :options="$o" selected="2" />',
        ['o' => [1 => 'Graneros', 2 => 'Rancagua']]
    );
    expect((bool) preg_match('/<option value="2"[^>]*selected/', $conOpciones))->toBeTrue(
        'select: las props `options` y `selected` dejaron de funcionar.'
    );

    $switch = campoMuni('switch', ['name' => 'activo', 'label' => 'Activo', 'description' => 'Recibe avisos', 'checked' => true]);
    expect(str_contains($switch, 'Recibe avisos'))->toBeTrue('switch: la prop `description` dejó de funcionar.');
    expect((bool) preg_match('/<input\b[^>]*\bchecked\b/', $switch))->toBeTrue('switch: la prop `checked` dejó de funcionar.');
});

it('el borde del campo en error se ve MÁS que el de un campo normal, y en los dos CSS', function () {
    // Medido en el banco del panel: el borde de error daba 2,10:1 en claro y 1,53:1
    // en oscuro, o sea por debajo del 3:1 de 1.4.11 y MENOS visible que un campo
    // normal. El error es justo el estado que tiene que saltar a la vista.
    foreach (['input', 'select', 'textarea', 'rut-input'] as $componente) {
        $fuente = fuenteDeLaVista($componente);

        expect(str_contains($fuente, '--muni-field-border-error'))->toBeTrue(
            "«{$componente}» no usa el token del borde de error: si vuelve a --muni-danger-border, ".
            'el campo equivocado se marca con un borde más tenue que el de un campo correcto.'
        );
    }

    // El token viaja en las DOS hojas: dentro de un panel Filament solo se carga
    // la del tema, y un var() sin declarar cae al respaldo sin dar ningún error.
    foreach (['muni-ui.css', 'muni-ui-filament.css'] as $hoja) {
        $css = file_get_contents(__DIR__.'/../resources/css/'.$hoja);

        expect(str_contains($css, '--muni-field-border-error'))->toBeTrue(
            "«{$hoja}» no declara --muni-field-border-error. Dentro de un panel el borde de un ".
            'campo con error se quedaría sin color y no habría ningún error visible.'
        );
    }
});

it('el tema del panel esconde lo que Alpine no ha montado', function () {
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');

    // Diez componentes del paquete usan [x-cloak]. La regla que lo hace invisible
    // vivía solo en muni-ui.css, que dentro de un panel NO se carga: en el primer
    // pintado se veían el menú del dropdown abierto y los dos tab-panel a la vez.
    expect((bool) preg_match('/\[x-cloak\][^{]*\{[^}]*display\s*:\s*none/', $css))->toBeTrue(
        'El tema del panel no declara [x-cloak]: diez componentes muestran su contenido en '.
        'crudo hasta que Alpine monta, y Filament tampoco aporta esa regla.'
    );
});
