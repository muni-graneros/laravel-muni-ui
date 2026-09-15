<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;

/*
|--------------------------------------------------------------------------
| `<x-muni::formulario-tramite>` y `<x-muni::formulario-seccion>`
|--------------------------------------------------------------------------
|
| Ficha «formulario-tramite» de docs/GAP-ANALYSIS.md: el trámite que el
| funcionario ingresa con los papeles en la mano. Secciones con <legend>,
| rejilla de dos columnas, resumen de errores arriba con foco y una columna
| lateral con los requisitos que faltan.
|
| Lo que este archivo vigila, y por qué cada cosa:
|
|  1. Estructura: <form> de verdad, method que no degrada a GET, CSRF, y
|     secciones que no emiten un <fieldset> sin <legend>.
|  2. El resumen de errores va ARRIBA, antes del primer campo, y con foco.
|  3. La columna lateral es TEXTO: cero paradas de tabulación entre el último
|     campo y el botón de enviar.
|  4. El estado de cada requisito va en palabras, no solo en el color.
|  5. El contrato del paquete: sin colores literales, tokens declarados en las
|     DOS hojas, foco con outline, movimiento por token, CSS dentro del @once,
|     ids deterministas, nada del anfitrión dentro de una expresión de Alpine,
|     y Livewire 3 y 4.
|  6. Lo que ningún str_contains alcanza, medido en Chromium y Firefox al
|     final del archivo.
*/

function tramiteHtml(string $atributos = '', string $contenido = ''): string
{
    return Blade::render("<x-muni::formulario-tramite {$atributos}>{$contenido}</x-muni::formulario-tramite>");
}

/** Los tres requisitos del ejemplo: uno cumplido, uno manual y uno atado a un campo. */
function tramiteRequisitos(): string
{
    return ':requisitos="['
        .'[\'texto\' => \'Cédula de identidad vigente\', \'campo\' => \'rut\', \'cumplido\' => true],'
        .'[\'texto\' => \'Certificado de dominio\', \'nota\' => \'Emitido hace menos de 60 días\'],'
        .'[\'texto\' => \'Informe sanitario\', \'campo\' => \'informe\'],'
        .']"';
}

function tramiteHtmlCompleto(): string
{
    return tramiteHtml(
        'title="Solicitud de patente comercial" subtitle="Ingreso presencial en el mesón de Rentas" '
        .'action="/patentes" cancel-href="/patentes" '.tramiteRequisitos(),
        '<x-muni::formulario-seccion legend="Identificación del solicitante" description="Los datos del titular, tal como están en la cédula.">'
        .'<x-muni::input label="RUT" name="rut" required />'
        .'<x-muni::input label="Nombre" name="nombre" required />'
        .'</x-muni::formulario-seccion>'
        .'<x-muni::formulario-seccion legend="Antecedentes del local" columns="1">'
        .'<x-muni::input label="Dirección" name="direccion" class="muni-fsec__ancho" />'
        .'</x-muni::formulario-seccion>'
    );
}

/** El HTML del componente SIN su bloque de estilos: lo que ve el árbol del documento. */
function tramiteSinEstilos(string $html): string
{
    return (string) preg_replace('/<style>.*?<\/style>/s', '', $html);
}

function tramiteFuente(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/formulario-tramite.blade.php');
}

function seccionFuente(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/formulario-seccion.blade.php');
}

/** El fuente de los dos componentes, sin comentarios de Blade ni de PHP. */
function tramiteFuenteSinComentarios(): string
{
    $fuente = tramiteFuente()."\n".seccionFuente();
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** Solo el CSS de los dos bloques de estilo, con los comentarios ya fuera. */
function tramiteCss(): string
{
    preg_match_all('/<style>(.*?)<\/style>/s', tramiteFuente()."\n".seccionFuente(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

// ---------------------------------------------------------------------------
// 1. La estructura del trámite
// ---------------------------------------------------------------------------

it('rinde un <form> real, con su método y su acción', function () {
    $html = tramiteHtml('action="/patentes" title="Solicitud"');

    expect(str_contains($html, '<form'))->toBeTrue('No hay <form>: sin él no hay envío, ni Enter, ni autocompletado del navegador.');
    expect(str_contains($html, 'method="post"'))->toBeTrue('El método por defecto tiene que ser POST.');
    expect(str_contains($html, 'action="/patentes"'))->toBeTrue('La acción del anfitrión no llegó al <form>.');
    expect(str_contains($html, 'type="submit"'))->toBeTrue('No hay botón de envío.');
});

it('un method="put" no degrada el formulario a GET', function () {
    $html = tramiteHtml('method="put" action="/patentes/1"');

    expect(str_contains($html, 'method="post"'))->toBeTrue(
        'HTML solo entiende GET y POST en un <form>: method="put" a secas deja el formulario enviando por GET, '
        .'con los datos del vecino en la barra de direcciones y en los logs del proxy.'
    );
    expect(str_contains($html, 'name="_method" value="PUT"'))->toBeTrue('Falta el campo con que Laravel suplanta el verbo.');
    expect(str_contains($html, 'method="get"'))->toBeFalse('El formulario quedó enviando por GET.');
});

it('emite el campo oculto de CSRF, y lo apaga quien ya lo pone', function () {
    // Driver `array`: el de por defecto lee cookies de una petición que en
    // `Blade::render()` no existe. Lo que se prueba es el componente, no la sesión.
    config(['session.driver' => 'array']);
    session()->start();

    $html = tramiteHtml('action="/patentes"');

    expect(str_contains($html, 'name="_token"'))->toBeTrue(
        'Un formulario que crea una solicitud a nombre de un vecino sin token es el blanco exacto de un CSRF.'
    );
    expect(str_contains($html, csrf_token()))->toBeTrue('El token emitido no es el de la sesión.');

    expect(str_contains(tramiteHtml(':csrf="false" action="/patentes"'), 'name="_token"'))->toBeFalse(
        'Con :csrf="false" el anfitrión lo pone él; emitirlo igual deja dos campos `_token` en el mismo formulario.'
    );

    expect(str_contains(tramiteHtml('method="get" action="/buscar"'), 'name="_token"'))->toBeFalse(
        'Un GET no lleva token: solo lo publicaría en la URL.'
    );
});

it('pone el enctype de los adjuntos solo, y el del anfitrión manda', function () {
    $conArchivo = tramiteHtml(
        'action="/patentes"',
        '<x-muni::formulario-seccion legend="Documentos"><x-muni::file-dropzone name="informe" label="Informe" /></x-muni::formulario-seccion>'
    );

    expect(str_contains($conArchivo, 'enctype="multipart/form-data"'))->toBeTrue(
        'Un formulario con un campo de archivo y sin enctype no sube NADA: el navegador manda el nombre del archivo '
        .'como texto, el servidor recibe una cadena, y no hay error ni aviso. El funcionario cree que adjuntó.'
    );

    expect(str_contains(tramiteHtml('action="/patentes"'), 'enctype='))->toBeFalse(
        'Sin adjuntos el enctype sobra: multipart es más caro de parsear para nada.'
    );

    expect(str_contains(tramiteHtml('action="/patentes" enctype="text/plain"'), 'enctype="text/plain"'))->toBeTrue(
        'El enctype del anfitrión manda siempre.'
    );
});

it('no emite un SEGUNDO campo de CSRF si el anfitrión ya lo puso en la ranura', function () {
    config(['session.driver' => 'array']);
    session()->start();

    /*
     * Escribir la directiva de CSRF dentro de la ranura es el hábito normal en
     * Laravel, y el anfitrión no tiene por qué saber que el componente ya lo pone.
     * Dos `<input name="_token">` en el mismo formulario no rompen nada —PHP se
     * queda con el último y el valor es el mismo— pero es HTML redundante que nadie
     * ve, y el apagado (`:csrf="false"`) solo lo conoce quien lea el fuente. Como la
     * ranura YA está renderizada cuando corre el bloque de este componente —el mismo
     * hecho que permite resolver el enctype—, mirar si ya trae el campo es gratis.
     */
    $html = tramiteHtml('action="/patentes"', '<input type="hidden" name="_token" value="'.csrf_token().'">');

    expect(substr_count($html, 'name="_token"'))->toBe(1,
        'El anfitrión ya puso el campo de CSRF en la ranura y el componente puso otro: dos `_token` en el mismo <form>.'
    );

    // Y si NO lo pone, sigue emitiéndose: el olvido es el que no se ve.
    expect(substr_count(tramiteHtml('action="/patentes"'), 'name="_token"'))->toBe(1,
        'Sin el campo del anfitrión el componente tiene que ponerlo él.'
    );
});

it('el enctype también mira la ranura de acciones', function () {
    $html = tramiteHtml(
        'action="/patentes"',
        'campos<x-slot:acciones><input type="file" name="anexo"></x-slot:acciones>'
    );

    expect(str_contains($html, 'enctype="multipart/form-data"'))->toBeTrue(
        'Un campo de archivo que llega por la ranura `acciones` es un campo de archivo igual: sin enctype el navegador '
        .'manda el NOMBRE del archivo como texto y no hay error ni aviso.'
    );
});

it('sin sesión arrancada no revienta la vista: se calla', function () {
    expect(str_contains(tramiteFuente(), 'catch'))->toBeTrue(
        '`csrf_token()` lanza si no hay sesión: sin guarda, el componente muere en una landing pública sin sesión.'
    );
});

it('dice en TEXTO qué campos son obligatorios', function () {
    $html = tramiteHtml('title="Solicitud"');

    expect(str_contains($html, 'obligatorios'))->toBeTrue(
        'El asterisco solo no lo ve quien no distingue el rojo ni sabe la convención (técnica G184).'
    );

    // Con la CADENA VACÍA, no con `null`: `@props` aplica el valor por defecto con `??`,
    // así que un null explícito vuelve al texto de fábrica en vez de quitarlo. Lo
    // contrario de lo que hace un `:algo="null"` sobre un componente hijo (DESIGN §8).
    expect(str_contains(tramiteSinEstilos(tramiteHtml('required-note=""')), 'muni-ftr__obl'))->toBeFalse(
        'Un formulario sin campos obligatorios no debe afirmar que los tiene.'
    );

    expect(str_contains(tramiteSinEstilos(tramiteHtml(':required-note="null"')), 'muni-ftr__obl'))->toBeTrue(
        'Candado del gotcha: con `null` vuelve el texto de fábrica. Si algún día `@props` dejara de aplicar el '
        .'defecto con `??`, este candado avisa antes de que un formulario se quede sin la leyenda en silencio.'
    );
});

it('cada campo obligatorio dice «obligatorio» en TEXTO, no solo con asterisco', function () {
    /*
     * La ficha lo pide por CAMPO, no solo por formulario: «Cada campo obligatorio
     * dice "obligatorio" en TEXTO, no solo con asterisco». El asterisco es un glifo
     * decorativo —un lector de pantalla lo lee «asterisco», o no lo lee— y quien no
     * conoce la convención no sabe qué significa. La palabra la pone el control, que
     * es quien sabe si es obligatorio; el formulario aporta la leyenda general
     * (técnica G184), que es otra cosa y no la reemplaza.
     */
    $campos = [
        'input' => '<x-muni::input label="RUT del titular" name="rut" required />',
        'select' => '<x-muni::select label="Tipo de trámite" name="tramite" :options="[\'a\' => \'Patente\']" required />',
        'textarea' => '<x-muni::textarea label="Giro solicitado" name="giro" required />',
        'checkbox' => '<x-muni::checkbox label="Declaro bajo juramento" name="jurada" required />',
        'checkbox-group' => '<x-muni::checkbox-group legend="Documentos que adjunta" name="docs" :options="[\'a\' => \'Croquis\']" required />',
        'combobox' => '<x-muni::combobox label="Calle" name="calle" :options="[\'a\' => \'Avenida Argomedo\']" required />',
        // Los que delegan en `input`: heredan la palabra sin tocarlos.
        'rut-input' => '<x-muni::rut-input label="RUT" name="rut2" required />',
        'date-input' => '<x-muni::date-input label="Fecha de inicio" name="inicio" required />',
        'input-password' => '<x-muni::input-password label="Clave" name="clave" required />',
    ];

    foreach ($campos as $nombre => $blade) {
        $html = Blade::render($blade);

        expect(str_contains($html, 'obligatorio'))->toBeTrue(
            "`{$nombre}` marca lo obligatorio SOLO con un asterisco: quien usa un lector de pantalla oye «asterisco» "
            .'o nada, y quien no conoce la convención no sabe qué significa. La ficha pide la palabra en TEXTO.'
        );

        // Y el asterisco deja de hablar: con la palabra al lado, leerlo además sería
        // «RUT asterisco obligatorio».
        expect((bool) preg_match('/<span aria-hidden="true"[^>]*>\*<\/span>/', $html))->toBeTrue(
            "`{$nombre}`: con la palabra al lado, el asterisco es decoración y va con aria-hidden."
        );
    }
});

it('la palabra «obligatorio» se puede ver, y se puede apagar', function () {
    $visible = Blade::render('<x-muni::input label="RUT" name="rut" required required-text-visible />');

    expect(str_contains($visible, 'muni-obl'))->toBeTrue(
        'Con `required-text-visible` la palabra tiene que verse: el asterisco no lo entiende quien no conoce la '
        .'convención, y la leyenda del formulario queda tres pantallas más arriba en un trámite largo.'
    );
    expect(str_contains($visible, 'muni-sr">'))->toBeFalse('Si se ve, no hace falta la copia oculta: se leería dos veces.');

    // Igual que la leyenda del formulario, se apaga con la CADENA VACÍA y no con
    // `null`: la directiva de props aplica el defecto con `??`.
    $sin = Blade::render('<x-muni::input label="RUT" name="rut" required required-text="" />');

    expect(str_contains($sin, 'obligatorio'))->toBeFalse('Con la cadena vacía no se emite la palabra.');
    expect(str_contains($sin, 'aria-hidden="true"'))->toBeFalse(
        'Sin la palabra, el asterisco vuelve a ser el único indicador: taparlo al lector de pantalla lo dejaría sin ninguno.'
    );

    // Y sin `required` no hay marca de ninguna clase.
    expect(str_contains(Blade::render('<x-muni::input label="RUT" name="rut" />'), 'obligatorio'))->toBeFalse(
        'Un campo que no es obligatorio no puede decir que lo es.'
    );
});

it('la sección agrupa con <fieldset> y <legend>', function () {
    $html = Blade::render('<x-muni::formulario-seccion legend="Identificación del solicitante">campo</x-muni::formulario-seccion>');

    expect(str_contains($html, '<fieldset'))->toBeTrue('Sin <fieldset> los campos no forman un grupo para el lector de pantalla.');
    expect(str_contains($html, '<legend class="muni-fsec__legend">Identificación del solicitante</legend>'))->toBeTrue(
        'El <legend> es lo que nombra el grupo; sin él, el <fieldset> se anuncia con la cadena vacía.'
    );
});

it('sin rótulo NO emite un <fieldset> mudo', function () {
    $html = Blade::render('<x-muni::formulario-seccion>campo</x-muni::formulario-seccion>');

    expect(str_contains($html, '<fieldset'))->toBeFalse(
        'Un <fieldset> sin <legend> se anuncia como «grupo» y nada más: el funcionario oye una pausa en vez de un título. '
        .'Peor que no agrupar.'
    );
    expect(str_contains($html, 'muni-fsec'))->toBeTrue('La sección sin rótulo sigue existiendo como caja.');
});

it('la rejilla va en un <div> DENTRO del fieldset, no en el fieldset', function () {
    $html = Blade::render('<x-muni::formulario-seccion legend="Identificación">campo</x-muni::formulario-seccion>');

    expect(str_contains($html, '<div class="muni-fsec__grid"'))->toBeTrue(
        'El <fieldset> es el elemento con más historial de peleas con display:grid (el min-width:min-content que le '
        .'impone el UA): una rejilla puesta ahí se desborda en horizontal, y 1.4.10 no admite scroll horizontal.'
    );

    $css = tramiteCss();
    expect(preg_match('/\.muni-fsec__grid\s*\{[^}]*display:\s*grid/', $css))->toBe(1, 'La rejilla no es una rejilla.');
    expect(str_contains($css, 'repeat(2,minmax(0,1fr))'))->toBeTrue('Faltan las dos columnas de escritorio.');
    expect(preg_match('/@media \(max-width:860px\)[^}]*\{[^{]*\.muni-fsec__grid\[data-muni-cols="2"\]\s*\{\s*grid-template-columns:minmax\(0,1fr\)/s', $css))
        ->toBe(1, 'A 860px las dos columnas tienen que colapsar a una: dos columnas de 160px no son dos columnas.');
});

it('la descripción de la sección se ENCADENA al aria-describedby del anfitrión', function () {
    $html = Blade::render('<x-muni::formulario-seccion legend="Local" description="Con el rol de avalúo." aria-describedby="ayuda-externa">campo</x-muni::formulario-seccion>');

    preg_match('/aria-describedby="([^"]*)"/', $html, $m);

    expect($m[1] ?? '')->toContain('ayuda-externa');
    expect(count(explode(' ', trim($m[1] ?? ''))))->toBe(2,
        '`$attributes->merge()` REEMPLAZA en todo lo que no sea class ni style: sin leer el valor de la bolsa y '
        .'unirlo a mano, uno de los dos ids desaparece sin aviso (DESIGN §8).'
    );
});

// ---------------------------------------------------------------------------
// 2. El resumen de errores
// ---------------------------------------------------------------------------

it('al enviar con errores, el resumen va ARRIBA y se lleva el foco', function () {
    $html = tramiteHtml(
        ':errors="[\'rut\' => \'El dígito verificador no corresponde.\', \'fecha\' => \'La fecha es obligatoria.\']" title="Solicitud"',
        '<x-muni::formulario-seccion legend="Identificación"><x-muni::input label="RUT" name="rut" /></x-muni::formulario-seccion>'
    );

    expect(str_contains($html, 'role="alert"'))->toBeTrue('El resumen de errores no se emitió.');
    expect(str_contains($html, 'Hay 2 errores en el formulario'))->toBeTrue('El recuento va en texto, no solo en el borde rojo.');
    expect(str_contains($html, 'href="#muni-rut"'))->toBeTrue('Cada error tiene que ser un enlace que lleve al campo (técnica G139).');
    expect(str_contains($html, 'tabindex="-1"'))->toBeTrue('El resumen tiene que poder recibir el foco.');

    $posResumen = strpos($html, 'role="alert"');
    $posCampo = strpos($html, 'muni-ftr__campos');

    expect($posResumen)->toBeLessThan($posCampo,
        'El resumen tiene que ir ANTES del primer campo: quien envía treinta campos puede tener el error tres '
        .'pantallas más arriba del botón que acaba de pulsar.'
    );
});

it('acepta la bolsa de errores de Laravel, no solo un arreglo', function () {
    $html = Blade::render(
        '<x-muni::formulario-tramite :errors="$bolsa" />',
        ['bolsa' => new MessageBag(['rut' => ['El RUT no es válido.']])]
    );

    expect(str_contains($html, 'El RUT no es válido.'))->toBeTrue(
        'En un formulario real los errores llegan como MessageBag, no como arreglo.'
    );
});

it('sin errores no emite NADA del resumen', function () {
    $html = tramiteHtml('title="Solicitud"');

    expect(str_contains($html, 'role="alert"'))->toBeFalse(
        '`role="alert"` es assertive: pintarlo en la primera carga interrumpe al lector de pantalla sin que haya pasado nada.'
    );
});

it('el anfitrión puede poner el resumen él mismo, o quitarle el foco', function () {
    $sinResumen = tramiteHtml(':error-summary="false" :errors="[\'rut\' => \'No es válido.\']"');
    expect(str_contains($sinResumen, 'role="alert"'))->toBeFalse('Con :error-summary="false" no debe emitirse.');

    $sinFoco = tramiteHtml(':error-focus="false" :errors="[\'rut\' => \'No es válido.\']"');
    expect(str_contains($sinFoco, '$el.focus()'))->toBeFalse('Con :error-focus="false" el foco no se mueve.');
});

it('pasa los ids verdaderos al resumen cuando el nombre del campo no reconstruye el id', function () {
    $html = tramiteHtml(
        ':errors="[\'direccion.calle\' => \'Falta la calle.\']" '
        .':error-ids="[\'direccion.calle\' => \'muni-direccion-calle-8f2a10\']"'
    );

    expect(str_contains($html, 'href="#muni-direccion-calle-8f2a10"'))->toBeTrue(
        'Un name="direccion[calle]" valida como `direccion.calle` y el id lleva un hash que no se puede reconstruir: '
        .'sin la tabla del anfitrión el enlace sería un enlace muerto, que promete llegar al campo y no llega.'
    );
});

// ---------------------------------------------------------------------------
// 3. La columna lateral de requisitos
// ---------------------------------------------------------------------------

it('la columna lateral es TEXTO: no roba una sola tabulación', function () {
    $html = tramiteHtmlCompleto();

    preg_match('/<aside class="muni-ftr__lateral".*?<\/aside>/s', $html, $m);
    $aside = $m[0] ?? '';

    expect($aside)->not->toBe('', 'No se emitió la columna lateral.');

    foreach (['<a ', '<button', '<input', '<select', '<textarea', 'tabindex="0"'] as $aguja) {
        expect(str_contains($aside, $aguja))->toBeFalse(
            "El resumen lateral emite `{$aguja}`: diez tabulaciones entre el último campo y el botón de enviar son "
            .'peores que no tener resumen. Es texto que dice QUÉ FALTA, no un control.'
        );
    }

    expect(str_contains($aside, 'Certificado de dominio'))->toBeTrue('El requisito no llegó a la lista.');
    expect(str_contains($aside, 'Emitido hace menos de 60 días'))->toBeTrue('La nota del requisito no llegó.');
});

it('dice cuántos requisitos faltan, en texto y desde el primer render', function () {
    $html = tramiteHtmlCompleto();

    expect(str_contains($html, 'Faltan 2 requisitos de 3.'))->toBeTrue(
        'El recuento del servidor es información desde el primer render, no una notificación que llega después.'
    );
    expect(str_contains($html, 'aria-live="polite"'))->toBeTrue('El recuento tiene que anunciarse cuando cambia.');

    $uno = tramiteHtml(':requisitos="[[\'texto\' => \'Cédula\']]"');
    expect(str_contains($uno, 'Falta 1 requisito de 1.'))->toBeTrue('El singular no se resuelve con «1 requisitos».');

    $cero = tramiteHtml(':requisitos="[[\'texto\' => \'Cédula\', \'cumplido\' => true]]"');
    expect(str_contains($cero, 'Todos los requisitos están completos.'))->toBeTrue('Falta el estado «no falta nada».');
});

it('el recuento solo se reescribe cuando el número CAMBIA', function () {
    $fuente = tramiteFuente();

    expect(str_contains($fuente, 'if (texto !== this.resumen)'))->toBeTrue(
        'Reescribir la misma cadena muta el DOM y el lector de pantalla vuelve a hablar: tras cada tecla se oiría '
        .'«faltan 3 requisitos» otra vez.'
    );
    expect(str_contains($fuente, '$el.textContent.trim() !== resumen'))->toBeTrue(
        'Al hidratar, escribir el mismo texto que ya puso el servidor es una mutación redundante de la región viva.'
    );
    expect(str_contains($fuente, 'debounce'))->toBeTrue('Sin debounce el recuento se recalcula en cada pulsación.');
});

it('el estado de cada requisito va en PALABRAS y en forma, no solo en color', function () {
    $html = tramiteHtmlCompleto();

    expect(str_contains($html, '>Listo<'))->toBeTrue('El requisito cumplido no dice «Listo» en ninguna parte.');
    expect(str_contains($html, '>Falta<'))->toBeTrue('El requisito pendiente no dice «Falta» en ninguna parte.');

    $css = tramiteCss();
    expect(str_contains($css, '.muni-ftr__req.es-falta .muni-ftr__req-marca'))->toBeTrue('Falta el estado pendiente de la marca.');
    expect(str_contains($css, '.muni-ftr__req.es-listo .muni-ftr__req-marca'))->toBeTrue('Falta el estado cumplido de la marca.');
    expect(preg_match('/\.muni-ftr__req\.es-listo \.muni-ftr__req-marca\s*\{[^}]*border-radius:999px/', $css))->toBe(1,
        'La marca tiene que distinguirse también por la FORMA: cuadrada cuando falta, redonda cuando está lista (WCAG 1.4.1).'
    );
});

it('el :class de estado RETIRA la clase que puso el servidor', function () {
    /*
     * El `:class` de Alpine en forma de CADENA solo quita lo que él mismo agregó:
     * la clase que el servidor escribió en el atributo `class` no la retira nunca.
     * En el caso que el propio componente declara válido —un requisito con `campo`
     * cuyo control viene precargado, así que `cumplido => true` ES el estado inicial
     * del control— vaciar el campo dejaba `class="muni-ftr__req es-listo es-falta"`:
     * la palabra decía «Falta» y la marca seguía redonda, verde y con el texto
     * tachado. Es exactamente lo que DESIGN §10 prohíbe —el color contradiciendo a
     * la palabra— y encima el sentido directo solo se veía bien por el ORDEN de dos
     * reglas del bloque de estilos: invertirlas volteaba todos los estados.
     *
     * La sintaxis de OBJETO sí retira las claves con valor falso, vengan de donde
     * vengan.
     */
    $html = tramiteHtmlCompleto();

    expect(str_contains($html, ":class=\"{'es-listo': estado[0], 'es-falta': ! estado[0]}\""))->toBeTrue(
        'El `:class` de estado tiene que ir en sintaxis de OBJETO: la de cadena no retira la clase del servidor y el '
        .'<li> termina con `es-listo es-falta` a la vez.'
    );

    expect((bool) preg_match('/:class="estado\[\d+\]\s*\?/', $html))->toBeFalse(
        'Volvió el `:class` de cadena: solo quita lo que él mismo agregó.'
    );

    // Y el estado del servidor sigue en el atributo `class`, que es lo que se ve
    // sin JS. Las dos cosas a la vez son el contrato: servidor primero, Alpine
    // refresca, y ninguna clase sobrevive a la otra.
    expect(str_contains($html, 'class="muni-ftr__req es-listo"'))->toBeTrue(
        'Sin JS hay que ver el estado que calculó el anfitrión.'
    );
});

it('el estado llega resuelto del servidor y Alpine solo lo refresca', function () {
    $html = tramiteHtmlCompleto();

    expect(str_contains($html, 'class="muni-ftr__req es-listo"'))->toBeTrue(
        'Sin JS hay que ver el estado que calculó el anfitrión: es mejora progresiva, no una lista en blanco.'
    );
    expect(str_contains($html, 'estado: JSON.parse'))->toBeTrue('El estado inicial viaja por @js(), no escrito a mano.');
    expect(str_contains(tramiteFuente(), 'this.$el.elements'))->toBeTrue(
        'Un name como `items[0][rut]` es un valor de atributo válido y un selector CSS que hay que escapar a mano: '
        .'`elements` no necesita escape ninguno.'
    );
});

it('en un requisito con `campo` manda el campo, en los dos sentidos', function () {
    $fuente = tramiteFuente();

    expect(str_contains($fuente, 'if (campo) { this.estado[i] = this.tieneValor(campo); }'))->toBeTrue(
        'Un requisito con `campo` tiene que reflejar el control SIEMPRE: si solo pudiera subir a «Listo», vaciar un '
        .'campo que acabas de llenar dejaría el requisito mintiendo.'
    );

    // Y el contrato queda escrito donde lo lee quien consume el componente.
    expect(str_contains($fuente, 'SIN `campo` y con `cumplido => true`'))->toBeTrue(
        'Un requisito que ya se cumplió FUERA del formulario se declara sin `campo`: con `campo` y el control vacío, '
        .'la primera tecla en cualquier otro campo lo pasaba a «Falta» y la columna lateral contradecía al servidor.'
    );
});

it('sin requisitos no hay columna lateral ni una línea de Alpine', function () {
    $html = tramiteSinEstilos(tramiteHtml('title="Solicitud"'));

    expect(str_contains($html, 'muni-ftr__lateral'))->toBeFalse('Una columna lateral vacía es ruido.');
    expect(str_contains($html, 'x-data'))->toBeFalse(
        'Sin requisitos no hay nada que recalcular: el componente no tiene por qué exigir Alpine.'
    );
});

// ---------------------------------------------------------------------------
// 4. Enviar
// ---------------------------------------------------------------------------

it('el botón de enviar NUNCA se deshabilita por requisitos pendientes', function () {
    $html = tramiteHtmlCompleto();

    preg_match('/<button type="submit".*?<\/button>/s', $html, $m);

    expect(str_contains($m[0] ?? '', 'disabled'))->toBeFalse(
        'Un botón apagado desde el cliente deja al funcionario sin saber por qué, y la validación de verdad hay que '
        .'escribirla igual en el servidor.'
    );
    expect(str_contains($html, 'muni-ftr__enviar'))->toBeTrue('No hay botón de envío.');
});

it('el primario va primero en el DOM y primero a la vista', function () {
    $html = tramiteHtmlCompleto();

    $enviar = strpos($html, 'muni-ftr__enviar');
    $cancelar = strpos($html, 'muni-ftr__cancelar');

    expect($enviar)->toBeLessThan($cancelar,
        'El orden de tabulación y el orden visual tienen que ser el mismo (WCAG 1.3.2): invertir uno de los dos con '
        .'`row-reverse` deja el recorrido del teclado contando otra historia que la pantalla.'
    );
    expect(str_contains(tramiteCss(), 'row-reverse'))->toBeFalse('El orden visual no puede desacoplarse del DOM.');
});

it('sin enlace de cancelación no inventa una salida', function () {
    expect(str_contains(tramiteSinEstilos(tramiteHtml('title="Solicitud"')), 'muni-ftr__cancelar'))->toBeFalse(
        'Un control que no lleva a ninguna parte es peor que no ofrecerlo.'
    );
});

// ---------------------------------------------------------------------------
// 5. El contrato del paquete
// ---------------------------------------------------------------------------

it('no escribe un solo color literal', function () {
    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', tramiteCss(), $hex);

    $literales = array_values(array_diff(array_unique($hex[0]), ['#767676']));

    expect($literales)->toBe([], 'Colores literales en el componente: '.implode(', ', $literales));
});

it('usa solo tokens declarados en las DOS hojas', function () {
    $hojas = [
        'muni-ui.css' => cssMuniUi(),
        'muni-ui-filament.css' => cssMuniUiFilament(),
    ];

    preg_match_all('/var\(\s*(--muni-[a-z0-9-]+)/', tramiteFuente()."\n".seccionFuente(), $usados);

    foreach (array_unique($usados[1]) as $token) {
        foreach ($hojas as $nombre => $hoja) {
            expect((bool) preg_match('/'.preg_quote($token, '/').'\s*:/', $hoja))->toBeTrue(
                "El token `{$token}` no está declarado en `{$nombre}`: dentro del panel valdría vacío y la declaración "
                .'entera se descartaría, sin un solo error en consola.'
            );
        }
    }
});

it('el foco se ve con outline y el blanco táctil llega a 44px', function () {
    $css = tramiteCss();

    expect(substr_count($css, 'outline:3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeGreaterThan(0,
        'El indicador real es el outline: dentro de Filament la box-shadow del anillo se computa transparente (DESIGN §5).'
    );
    expect(preg_match('/\.muni-ftr__enviar,\s*\.muni-ftr__cancelar\s*\{[^}]*min-height:44px/', $css))->toBe(1,
        'Mesón y vecino mayor: 44×44 (WCAG 2.2 AA 2.5.8 pide 24, el ecosistema pide 44 en interfaces de terreno).'
    );
});

it('el movimiento sale del token y se apaga solo', function () {
    $css = tramiteCss();

    preg_match_all('/transition:([^;]+);/', $css, $trans);

    foreach ($trans[1] as $valor) {
        if (trim($valor) === 'none') {
            continue;
        }

        expect(str_contains($valor, 'var(--muni-dur)'))->toBeTrue(
            "Transición con duración fija: `{$valor}`. `--muni-dur` ya baja a 0 ms con `prefers-reduced-motion` (DESIGN §6)."
        );
    }

    expect(str_contains($css, '@media (prefers-reduced-motion:reduce)'))->toBeTrue(
        'Dentro del panel el token no alcanza: `muni-ui-filament.css` no baja `--muni-dur`.'
    );
});

it('lleva su propio CSS dentro del componente', function () {
    foreach (['formulario-tramite' => tramiteFuente(), 'formulario-seccion' => seccionFuente()] as $nombre => $fuente) {
        expect(str_contains($fuente, '<style>'))->toBeTrue("`{$nombre}` no trae su bloque de estilos.");
        expect(substr_count($fuente, '@once'))->toBe(1, "`{$nombre}` tiene que emitir su CSS una sola vez por petición.");
    }

    // DESIGN §7: dentro de un panel Filament solo se inyecta `muni-ui-filament.css`.
    // Una clase declarada únicamente en `muni-ui.css` se vería sin estilo y sin un
    // solo error en consola, como ya pasó con los gráficos y con `.muni-num`.
    foreach (['muni-ftr__lateral', 'muni-fsec__grid', 'muni-ftr__enviar'] as $clase) {
        expect(str_contains(tramiteCss(), '.'.$clase))->toBeTrue("`.{$clase}` no está declarada en el @once del componente.");
    }
});

it('no genera ids que cambien en cada render', function () {
    $fuente = tramiteFuenteSinComentarios();

    expect(str_contains($fuente, 'uniqid'))->toBeFalse(
        'Con uniqid() el id cambia en cada render, rompe el aria-labelledby y ensucia el diffing de Livewire (DESIGN §10).'
    );

    $uno = tramiteHtml('title="Solicitud de patente"');
    $dos = tramiteHtml('title="Solicitud de patente"');

    preg_match('/id="(muni-ftr-[^"]+)"/', $uno, $a);
    preg_match('/id="(muni-ftr-[^"]+)"/', $dos, $b);

    expect($a[1] ?? 'a')->toBe($b[1] ?? 'b', 'Dos renders del mismo formulario tienen que dar el mismo id.');

    expect(str_contains(tramiteHtml('id="mi-form" title="X"'), 'id="mi-form"'))->toBeTrue('El id del consumidor manda.');

    /*
     * Y la semilla no es solo el rótulo: dos formularios distintos en la misma página
     * —el caso de la vitrina, que muestra varias variantes del mismo componente— no
     * pueden compartir id, porque con él comparten `-titulo` y `-req` y el
     * `aria-labelledby` de los dos apunta al primero. Un contador de render lo
     * resolvería a costa de romper lo que DESIGN §10 protege: se reinicia en un
     * re-render parcial de Livewire y el id cambia entre dos pinturas de la misma
     * página. Lo que queda son dos formularios idénticos byte a byte, que son el mismo
     * formulario dos veces; para eso está el `id` del consumidor.
     */
    $ids = array_map(function (string $atributos) {
        preg_match('/id="(muni-ftr-[^"]+)"/', tramiteHtml($atributos), $m);

        return $m[1] ?? '';
    }, [
        '',
        'subtitle="Ingreso presencial"',
        'submit-label="Guardar borrador"',
        ':requisitos="[[\'texto\' => \'Cédula\']]"',
        ':requisitos="[[\'texto\' => \'Croquis\']]"',
    ]);

    expect(count(array_unique($ids)))->toBe(count($ids),
        'Dos formularios que solo comparten el título (o que no tienen ninguno) daban el MISMO id constante, y con él '
        .'los mismos `-titulo` y `-req`: ids duplicados y un aria-labelledby que apunta al primero.'
    );
});

it('no consulta al anfitrión: recibe todo resuelto', function () {
    $fuente = tramiteFuenteSinComentarios();

    foreach (['request(', 'Auth::', 'auth(', 'Gate::', 'can(', '->user()'] as $aguja) {
        expect(str_contains($fuente, $aguja))->toBeFalse(
            "El paquete no consulta `{$aguja}`: recibe strings ya redactados por el anfitrión, nunca un modelo ni un "
            .'registro completo (Ley 21.719, minimización).'
        );
    }
});

it('funciona en Livewire 3 y en Livewire 4', function () {
    $fuente = tramiteFuenteSinComentarios();

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "`{$prohibido}` es exclusivo de un major: `personas-graneros` sigue en Livewire 3 (DESIGN §10)."
        );
    }
});

it('escapa lo que viene del anfitrión y nunca lo mete crudo en una expresión de Alpine', function () {
    /*
     * Un apóstrofo dentro de una cadena de comillas simples de una expresión de
     * Alpine descuadra la expresión y tumba el Alpine de la página ENTERA, no solo
     * el de este componente. Ya pasó en `file-dropzone`.
     */
    $html = tramiteHtml(
        ':requisitos="[[\'texto\' => \'Poder del contribuyente (d\\\'Alessandri)\', \'campo\' => \'a\\\'b\']]" '
        .'done-label="Está listo" pending-label="D\'Amico"'
    );

    preg_match_all('/x-(?:data|text|effect)="([^"]*)"/', $html, $expresiones);

    foreach ($expresiones[1] as $expresion) {
        expect(str_contains($expresion, "d'Alessandri"))->toBeFalse('Texto del anfitrión crudo dentro de una expresión de Alpine.');
        expect(str_contains($expresion, "a'b"))->toBeFalse('Nombre de campo del anfitrión crudo dentro de una expresión de Alpine.');
        expect(str_contains($expresion, "D'Amico"))->toBeFalse('Rótulo del anfitrión crudo dentro de una expresión de Alpine.');
    }

    expect(str_contains($html, 'JSON.parse'))->toBeTrue('Lo que necesita JS tiene que viajar por @js().');
    expect(str_contains($html, 'd&#039;Alessandri'))->toBeTrue('El texto del requisito tiene que salir escapado en el HTML.');
});

it('el aria-labelledby del anfitrión no duplica el atributo', function () {
    $html = tramiteHtml('title="Solicitud" aria-labelledby="mi-titulo"');

    preg_match('/<form\b[^>]*>/s', $html, $m);

    expect(substr_count($m[0] ?? '', 'aria-labelledby='))->toBe(1,
        'Dos `aria-labelledby` en el mismo <form> es HTML inválido y el navegador se queda con el primero: el nombre '
        .'del anfitrión se perdería en silencio (DESIGN §8).'
    );
    expect($m[0] ?? '')->toContain('mi-titulo');
});

it('no nombra una directiva dentro de un comentario CSS', function () {
    // Solo los comentarios que viven DENTRO de un bloque <style>: ahí `/* */` no es un
    // comentario de Blade, es texto que Blade compila (DESIGN §8, trampa #5). Un
    // comentario de PHP dentro de un bloque @php ya está fuera del compilador.
    preg_match_all('/<style>(.*?)<\/style>/s', tramiteFuente()."\n".seccionFuente(), $bloques);

    preg_match_all('#/\*(.*?)\*/#s', implode("\n", $bloques[1]), $comentarios);

    foreach ($comentarios[1] as $comentario) {
        expect(preg_match('/(?<!@)@(once|endonce|if|endif|foreach|endforeach|props|js)\b/', $comentario))->toBe(0,
            'Un comentario CSS `/* */` no es un comentario de Blade: es texto que Blade compila, y una directiva '
            .'nombrada ahí abre un bloque que nadie cierra (DESIGN §8, trampa #5). Comentario: '.trim($comentario)
        );
    }
});

/*
 * La entrada que va a `ejemplosDeVitrina()` de tests/GeneraVitrinaTest.php, que es
 * archivo compartido y lo aplica el orquestador. Vive acá para que la reja de la
 * vitrina no se entere tarde: si alguien renombra una prop, esto se pone rojo en la
 * prueba del componente y no dentro de un archivo que este agente no toca.
 *
 * Los `name` llevan prefijo `patente_` A PROPÓSITO: la vitrina pinta los 55
 * componentes en UNA página, y `input`, `textarea` y `file-dropzone` ya traen
 * `name="rut"`, `name="detalle"` y `name="informe"`. Sin prefijo, dos controles
 * distintos compartirían id, el `for` de una etiqueta apuntaría al campo de otra
 * tarjeta y axe lo cazaría como id duplicado.
 */
function tramiteEntradaDeVitrina(): string
{
    return '<x-muni::formulario-tramite title="Solicitud de patente comercial" '
        .'subtitle="Ingreso presencial en el mesón de Rentas" action="/patentes" cancel-href="/patentes" '
        .'requisitos-note="Sin los cuatro requisitos la solicitud queda observada." '
        .':requisitos="[[\'texto\' => \'Cédula de identidad vigente\', \'cumplido\' => true, \'nota\' => \'Ya está en el expediente\'], '
        .'[\'texto\' => \'Certificado de dominio\', \'nota\' => \'Emitido hace menos de 60 días\'], '
        .'[\'texto\' => \'Informe sanitario de la SEREMI\', \'campo\' => \'patente_informe\'], '
        .'[\'texto\' => \'Giro declarado\', \'campo\' => \'patente_giro\']]">'
        .'<x-muni::formulario-seccion legend="Identificación del solicitante" description="Los datos del titular, tal como están en la cédula.">'
        .'<x-muni::input label="RUT del titular" name="patente_rut" hint="Formato 12.345.678-9" required required-text-visible />'
        .'<x-muni::input label="Nombre completo" name="patente_nombre" required />'
        .'<x-muni::input label="Correo de contacto" name="patente_correo" type="email" />'
        .'<x-muni::input label="Teléfono" name="patente_telefono" />'
        .'</x-muni::formulario-seccion>'
        .'<x-muni::formulario-seccion legend="Antecedentes del local" columns="1">'
        .'<x-muni::input label="Dirección del local" name="patente_direccion" hint="Calle, número y comuna" />'
        .'<x-muni::textarea label="Giro solicitado" name="patente_giro" hint="Describe la actividad" :maxlength="500" />'
        .'</x-muni::formulario-seccion>'
        .'<x-muni::formulario-seccion legend="Documentos adjuntos">'
        .'<x-muni::file-dropzone name="patente_informe" label="Informe sanitario" class="muni-fsec__ancho" />'
        .'</x-muni::formulario-seccion>'
        .'</x-muni::formulario-tramite>';
}

it('la entrada de vitrina que se entrega al orquestador rinde de verdad', function () {
    $html = Blade::render(tramiteEntradaDeVitrina());

    expect(str_contains($html, 'class="muni-ftr"'))->toBeTrue('La entrada de vitrina no rinde el formulario.');
    expect(substr_count($html, '<fieldset'))->toBe(3, 'La entrada de vitrina tiene que mostrar las tres secciones con <legend>.');
    expect(str_contains($html, 'Faltan 3 requisitos de 4.'))->toBeTrue('La columna lateral no llegó con su recuento.');
    expect(str_contains($html, 'enctype="multipart/form-data"'))->toBeTrue('La sección de adjuntos tiene que disparar el enctype.');
    expect(str_contains($html, 'muni-obl'))->toBeTrue('La variante visible de «obligatorio» es lo que la reja de la vitrina mide.');

    // Ningún id de esta tarjeta puede chocar con los que ya pinta la vitrina: son
    // los 55 componentes en UNA página.
    preg_match_all('/id="(muni-[^"]+)"/', $html, $ids);

    foreach (['muni-rut', 'muni-detalle', 'muni-informe', 'muni-tramite', 'muni-consentimiento'] as $ajeno) {
        expect(in_array($ajeno, $ids[1], true))->toBeFalse(
            "La entrada de vitrina usa el id `{$ajeno}`, que ya lo pinta otra tarjeta de la misma página: el `for` de "
            .'una etiqueta terminaría apuntando al campo de otro componente.'
        );
    }

    expect(count(array_unique($ids[1])))->toBe(count($ids[1]), 'La propia tarjeta repite un id.');
});

// ---------------------------------------------------------------------------
// 6. Verificación en navegador
// ---------------------------------------------------------------------------

/*
 * El banco. Se genera acá y no en un script suelto porque este es el único sitio
 * del paquete con Blade arrancado (mismo criterio que `GeneraVitrinaTest` y
 * `PantallaDeResultadoTest`). Sale a `build/formulario-tramite/`, ignorado por git.
 *
 * Seis páginas, y cada una existe por algo que ningún str_contains alcanza:
 *
 * · `claro` y `oscuro` — el trámite entero con Alpine: el recorrido de Tab que
 *   salta la columna lateral, el requisito que se marca solo al escribir, el
 *   contraste de los dos estados y el foco de los botones.
 * · `panel-claro` y `panel-oscuro` — el MISMO trámite cargando ÚNICAMENTE
 *   `muni-ui-filament.css` (DESIGN §7): dentro de un panel `muni-ui.css` no se
 *   carga, y es ahí donde una clase declarada fuera del `@once` se queda sin
 *   estilo y sin un solo error en consola.
 * · `errores` — el formulario devuelto con errores: el resumen tiene que
 *   llevarse el foco al cargar y sus enlaces tienen que llegar al campo.
 * · `sin-alpine` — el mismo trámite SIN el script: el estado que calculó el
 *   servidor tiene que seguir a la vista y el formulario tiene que poder enviarse.
 */
function tramiteAlpineDelBanco(): ?string
{
    $ruta = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    return is_file($ruta) ? (string) file_get_contents($ruta) : null;
}

it('genera el banco de navegador en build/formulario-tramite/', function () {
    $dir = __DIR__.'/../build/formulario-tramite';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $alpine = tramiteAlpineDelBanco();

    expect($alpine)->not->toBeNull(
        'No está node_modules/alpinejs: corre `npm install`. Sin Alpine el banco no puede medir ni el recuento vivo '
        .'ni el recorrido del teclado, que es justo lo que ninguna prueba de texto alcanza.'
    );

    $secciones =
        '<x-muni::formulario-seccion legend="Identificación del solicitante" description="Los datos del titular, tal como están en la cédula.">'
        // `required-text-visible`: la palabra «obligatorio» A LA VISTA, que es el
        // canal para quien mira y no conoce la convención del asterisco. Va en el
        // banco para que la reja la mida —color, tamaño y contraste— en los dos
        // temas y en las dos paletas; los demás campos la llevan oculta, que es el
        // valor por defecto, y también se mide que no robe ni tabulación ni ancho.
        .'<x-muni::input label="RUT del titular" name="rut" hint="Formato 12.345.678-9" required required-text-visible />'
        .'<x-muni::input label="Nombre completo" name="nombre" value="Juana Pérez Soto" required />'
        .'<x-muni::input label="Correo de contacto" name="correo" type="email" />'
        .'<x-muni::input label="Teléfono" name="telefono" />'
        .'</x-muni::formulario-seccion>'
        .'<x-muni::formulario-seccion legend="Antecedentes del local" columns="1">'
        .'<x-muni::input label="Dirección del local" name="direccion" hint="Calle, número y comuna" />'
        .'<x-muni::textarea label="Giro solicitado" name="giro" hint="Describe la actividad" :maxlength="500" />'
        .'</x-muni::formulario-seccion>'
        .'<x-muni::formulario-seccion legend="Documentos adjuntos">'
        .'<x-muni::file-dropzone name="informe" label="Informe sanitario" class="muni-fsec__ancho" />'
        .'</x-muni::formulario-seccion>';

    /*
     * Los cuatro estados que hay que medir, y la regla de `campo` respetada:
     * el requisito que ya está cumplido FUERA del formulario va SIN `campo`
     * —si no, la primera tecla en otro campo lo pasaría a «Falta»—, y los dos
     * que sí tienen control nacen pendientes, como el control.
     */
    $requisitos = ':requisitos="['
        ."['texto' => 'Cédula de identidad vigente', 'cumplido' => true, 'nota' => 'Ya está en el expediente'],"
        ."['texto' => 'Certificado de dominio', 'nota' => 'Emitido hace menos de 60 días'],"
        ."['texto' => 'Informe sanitario de la SEREMI', 'campo' => 'informe'],"
        ."['texto' => 'Giro declarado', 'campo' => 'giro'],"
        /*
         * El caso que el `:class` de cadena rompía: requisito CON `campo` cuyo
         * control viene PRECARGADO, así que `cumplido => true` es el estado
         * inicial de ese control —justo lo que la regla del componente exige—.
         * Vaciar el campo tiene que dejar el <li> solo en «Falta»; con el
         * `:class` de cadena quedaba `es-listo es-falta` y la marca seguía
         * verde, redonda y con el texto tachado mientras la palabra decía otra
         * cosa. Sin él, ni el banco ni el Pest veían el defecto.
         */
        ."['texto' => 'Nombre del titular como en la cédula', 'campo' => 'nombre', 'cumplido' => true],"
        .']"';

    $base = '<x-muni::formulario-tramite title="Solicitud de patente comercial" '
        .'subtitle="Ingreso presencial en el mesón de Rentas · Municipalidad de Graneros" '
        .'action="/patentes" cancel-href="/patentes" '
        .'requisitos-note="Sin los cuatro requisitos la solicitud queda en estado observado." '
        .$requisitos;

    $tramite = Blade::render($base.'>'.$secciones.'</x-muni::formulario-tramite>');

    $conErrores = Blade::render(
        $base.' :errors="[\'rut\' => \'El dígito verificador no corresponde.\', '
        .'\'direccion\' => \'La dirección del local es obligatoria.\']">'.$secciones.'</x-muni::formulario-tramite>'
    );

    $paginas = [
        'claro' => ['light', cssMuniUi(), false, $tramite, true],
        'oscuro' => ['dark', cssMuniUi(), false, $tramite, true],
        'panel-claro' => ['light', cssMuniUiFilament(), true, $tramite, true],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true, $tramite, true],
        'errores' => ['light', cssMuniUi(), false, $conErrores, true],
        'sin-alpine' => ['light', cssMuniUi(), false, $tramite, false],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel, $cuerpoTramite, $conAlpine]) {
        $oscuro = $tema === 'dark';
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        /* El armazón no aporta ni un color: fondo y texto salen de los mismos
           tokens que lee el componente. El `--muni-topbar-h` lo define la hoja y
           es lo que el `sticky` de la columna lateral descuenta. */
        $armazon = 'body{margin:0;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'main{padding:24px;max-width:1180px;margin:0 auto}'
            .'.antes{min-height:40vh;padding:0 0 24px}'
            /* El enlace del armazón también sale de los tokens. Sin esto se quedaba
               con el azul de fábrica del navegador (#0000ee), que sobre el papel
               oscuro da 2,05:1 y hacía FALLAR la reja (`scripts/a11y-check.py`) por
               un defecto del banco y no del componente: justo el ruido que impide
               ver el defecto verdadero cuando aparezca. */
            .'.antes a{color:var(--muni-text);text-decoration:underline;text-underline-offset:2px}'
            .'.antes a:focus-visible{outline:3px solid var(--muni-focus, var(--muni-accent, #767676));outline-offset:2px}';

        $script = $conAlpine && $alpine !== null
            ? '<script data-banco="alpine-core">'.$alpine.'</script>'
            : '';

        /* Un enlace ANTES del formulario: así el recorrido de Tab empieza fuera y
           se puede medir dónde entra y por dónde sale. */
        $antes = '<div class="antes"><a href="#fuera">Volver a la bandeja</a>'
            .'<p>Ingreso presencial. El expediente en papel queda en Oficina de Partes.</p></div>';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .$antes.'<section class="fi-section">'.$cuerpoTramite.'</section></main></div>'.$script.'</body>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$antes.$cuerpoTramite.'</main>'.$script.'</body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco formulario-tramite — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);

        expect(str_contains($pagina, 'class="muni-ftr"'))->toBeTrue('El banco '.$nombre.' salió sin el formulario.');
        expect(str_contains($pagina, 'data-banco="alpine-core"'))->toBe($conAlpine,
            'El banco '.$nombre.' no trae el Alpine que le corresponde.'
        );
    }

    // El bloque de estilos se emite UNA vez por petición: si el formulario se
    // hubiera renderizado sin él, lo que se mida arriba serían las medidas del
    // navegador y no las del componente.
    expect(str_contains((string) file_get_contents($dir.'/claro.html'), '.muni-ftr__lateral'))->toBeTrue(
        'El banco salió sin el bloque de estilos del componente.'
    );
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function tramitePythonDeLaReja(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que ningún `str_contains` sobre el fuente puede ver, medido en Chromium y
 * Firefox. El encabezado de `tests/navegador/solapas-de-ruta.py` lo dice con el
 * caso real: leer el TEXTO del CSS «ya engañó una vez en `stat`: la regla pasó
 * el test de texto y en el navegador el defecto seguía vivo».
 */
it('en Chromium y Firefox: el teclado, el recuento vivo, el foco y el contraste', function () {
    $python = tramitePythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/formulario-de-tramite.py').' '
        .escapeshellarg(__DIR__.'/../build/formulario-tramite').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador del formulario de trámite falló:\n".implode("\n", $lineas));

    // Las cuatro páginas completas × dos navegadores tienen que reportar que la
    // columna lateral NO aparece en el recorrido del teclado y que el requisito
    // atado a un campo se marca al escribir. Si el banco se quedara sin páginas,
    // el script diría «todo pasa» sobre nada.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'lateral=0-paradas'))))->toBe(8,
        "La columna lateral no puede robar una sola tabulación en ninguno de los ocho recorridos:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'vivo=2/2'))))->toBe(8,
        "El requisito atado a un campo tiene que marcarse al escribir, y el recuento tiene que bajar:\n".implode("\n", $lineas));

    // Y el sentido INVERSO, que es el que el `:class` de cadena rompía: al vaciar
    // el control de un requisito que nació cumplido, el <li> tiene que quedarse
    // SOLO en «Falta» —marca cuadrada, sin tachado— y no con las dos clases.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'revertir=ok'))))->toBe(8,
        "Vaciar el campo de un requisito cumplido tiene que retirar la clase del servidor:\n".implode("\n", $lineas));
    // Y la palabra «obligatorio» tiene que estar en el NOMBRE ACCESIBLE de todos los
    // campos obligatorios, en las 32 combinaciones: es la tecla de la ficha que ningún
    // str_contains sobre el fuente puede comprobar, porque el navegador saca del nombre
    // los subárboles con aria-hidden —el asterisco entre ellos.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'obl=2/2'))))->toBe(32,
        "Cada campo obligatorio tiene que decir «obligatorio» en su nombre accesible:\n".implode("\n", $lineas));

    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'foco=resumen'))))->toBe(2,
        "En la página con errores el resumen tiene que llevarse el foco en los dos navegadores:\n".implode("\n", $lineas));
});
