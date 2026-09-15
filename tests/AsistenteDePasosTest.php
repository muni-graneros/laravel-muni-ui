<?php

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| `<x-muni::asistente>` y la reparación de `<x-muni::stepper>`
|--------------------------------------------------------------------------
|
| Ficha «asistente» de docs/GAP-ANALYSIS.md: partir un trámite largo en pasos
| navegables, con validación por paso, pasos omitibles y anuncio del avance. La
| ficha es doble —componente nuevo MÁS reparación— porque `stepper` era solo un
| dibujo: sin <button>, sin <a>, sin tabindex, sin manejador de eventos, y sin
| los dos estados que un trámite municipal necesita (paso EN ERROR y paso
| OMITIDO).
|
| Lo que este archivo vigila, y por qué cada cosa:
|
|  1. Estructura: <form> real, método que no degrada a GET, CSRF, enctype de los
|     adjuntos y el índice del paso viajando al servidor.
|  2. SOLO EL PASO ACTUAL EXISTE EN EL DOM. Es el riesgo que la ficha nombra: en
|     Livewire 3 el `wire:model` de un paso escondido con `x-show` se sigue
|     enviando, y los campos siguen en el orden de tabulación.
|  3. El foco al cambiar de paso, que es lo que hoy no hace ningún sistema.
|  4. El anuncio del avance: progressbar con `aria-valuetext` dentro de una
|     región viva que no habla dos veces.
|  5. Anterior y Omitir con `formnovalidate`: sin él, un campo obligatorio vacío
|     encierra a la persona en el paso.
|  6. El stepper reparado: estados de error y omitido que no dependen del color,
|     navegación que es HTML, y todo lo viejo intacto.
|  7. El contrato del paquete: sin colores literales, tokens declarados en las
|     DOS hojas, foco con outline, movimiento por token, CSS dentro del bloque
|     de estilos, ids deterministas, Livewire 3 y 4, y nada del anfitrión dentro
|     de una expresión de Alpine.
|  8. Lo que ningún str_contains alcanza, medido en Chromium y Firefox al final.
*/

function asistenteHtml(string $atributos = '', string $contenido = '<p>campos del paso</p>'): string
{
    return Blade::render("<x-muni::asistente {$atributos}>{$contenido}</x-muni::asistente>");
}

/** Los cinco pasos de la solicitud de credencial de discapacidad. */
function asistentePasos(): string
{
    return ':steps="['
        ."'Identificación',"
        ."['label' => 'Antecedentes médicos', 'hint' => 'Informe del médico tratante'],"
        ."'Documentos',"
        ."'Declaración',"
        ."'Revisión',"
        .']"';
}

function asistenteCompleto(string $extra = ''): string
{
    return asistenteHtml(
        asistentePasos().' :current="1" title="Solicitud de credencial de discapacidad" '
        .'subtitle="Municipalidad de Graneros · Dirección de Desarrollo Comunitario" '
        .'action="/credencial" step-subtitle="Los antecedentes que entregó el médico tratante." '
        .$extra
    );
}

/** El HTML sin el bloque de estilos: lo que ve el árbol del documento. */
function asistenteSinEstilos(string $html): string
{
    return (string) preg_replace('/<style>.*?<\/style>/s', '', $html);
}

function asistenteFuente(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/asistente.blade.php');
}

function stepperFuente(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/stepper.blade.php');
}

/** El fuente de los dos componentes, sin comentarios de Blade ni de PHP. */
function asistenteFuenteSinComentarios(): string
{
    $fuente = asistenteFuente()."\n".stepperFuente();
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** Solo el CSS de los dos bloques de estilo, con los comentarios ya fuera. */
function asistenteCss(): string
{
    preg_match_all('/<style>(.*?)<\/style>/s', asistenteFuente()."\n".stepperFuente(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

function stepperHtml(string $atributos): string
{
    return Blade::render("<x-muni::stepper {$atributos} />");
}

// ---------------------------------------------------------------------------
// 1. La estructura del trámite por pasos
// ---------------------------------------------------------------------------

it('rinde un <form> real, con su método, su acción y su botón', function () {
    $html = asistenteCompleto();

    expect(str_contains($html, '<form'))->toBeTrue('Sin <form> no hay envío, ni Enter, ni autocompletado del navegador.');
    expect(str_contains($html, 'method="post"'))->toBeTrue('El método por defecto tiene que ser POST.');
    expect(str_contains($html, 'action="/credencial"'))->toBeTrue('La acción del anfitrión no llegó al <form>.');
    expect(str_contains($html, 'type="submit"'))->toBeTrue('Sin botón de envío el paso no avanza con Enter ni con clic.');
});

it('un method="put" no degrada el formulario a GET', function () {
    $html = asistenteHtml(asistentePasos().' method="put" action="/credencial/1"');

    expect(str_contains($html, 'method="post"'))->toBeTrue(
        'HTML solo entiende GET y POST en un <form>: method="put" a secas deja el paso enviándose por GET, con el '
        .'diagnóstico del vecino en la barra de direcciones y en los logs del proxy.'
    );
    expect(str_contains($html, 'name="_method" value="PUT"'))->toBeTrue('Falta el campo con que Laravel suplanta el verbo.');
    expect(str_contains($html, 'method="get"'))->toBeFalse('El formulario quedó enviando por GET.');
});

it('dice al servidor de qué paso viene el envío', function () {
    $html = asistenteCompleto();

    expect(str_contains($html, '<input type="hidden" name="_paso" value="1">'))->toBeTrue(
        'El servidor no puede deducir de qué paso viene el envío mirando el contenido, y confiar solo en la sesión '
        .'rompe con dos pestañas abiertas del mismo trámite.'
    );

    expect(str_contains(asistenteHtml(asistentePasos().' :current="1" step-name=""'), 'name="_paso"'))->toBeFalse(
        'Con `step-name=""` el anfitrión lo resuelve por su cuenta y el campo sobra.'
    );
});

it('emite el campo de CSRF, lo apaga quien ya lo pone y no lo duplica', function () {
    // Driver `array`: el de por defecto lee cookies de una petición que en
    // `Blade::render()` no existe. Lo que se prueba es el componente, no la sesión.
    config(['session.driver' => 'array']);
    session()->start();

    $html = asistenteCompleto();

    expect(str_contains($html, 'name="_token"'))->toBeTrue(
        'Un formulario que crea una solicitud de credencial a nombre de un vecino sin token es el blanco exacto de un CSRF.'
    );
    expect(str_contains($html, csrf_token()))->toBeTrue('El token emitido no es el de la sesión.');

    expect(str_contains(asistenteHtml(asistentePasos().' :csrf="false" action="/credencial"'), 'name="_token"'))->toBeFalse(
        'Con :csrf="false" el anfitrión lo pone él; emitirlo igual deja dos campos `_token` en el mismo formulario.'
    );

    expect(substr_count(
        asistenteHtml(asistentePasos().' action="/credencial"', '<input type="hidden" name="_token" value="'.csrf_token().'">'),
        'name="_token"'
    ))->toBe(1, 'El anfitrión ya puso el campo de CSRF en la ranura y el componente puso otro.');

    expect(str_contains(asistenteHtml(asistentePasos().' method="get" action="/buscar"'), 'name="_token"'))->toBeFalse(
        'Un GET no lleva token: solo lo publicaría en la URL.'
    );
});

it('pone el enctype de los adjuntos solo, y el del anfitrión manda', function () {
    $conArchivo = asistenteHtml(
        asistentePasos().' :current="2" action="/credencial"',
        '<x-muni::file-dropzone name="informe" label="Informe médico" />'
    );

    expect(str_contains($conArchivo, 'enctype="multipart/form-data"'))->toBeTrue(
        'Un paso con campo de archivo y sin enctype no sube NADA: el navegador manda el nombre del archivo como texto, '
        .'no hay error ni aviso, y el vecino cree que adjuntó el informe médico.'
    );

    expect(str_contains(asistenteCompleto(), 'enctype='))->toBeFalse('Sin adjuntos el enctype sobra.');

    expect(str_contains(asistenteHtml(asistentePasos().' enctype="text/plain"'), 'enctype="text/plain"'))->toBeTrue(
        'El enctype del anfitrión manda siempre.'
    );
});

it('sin sesión arrancada no revienta la vista: se calla', function () {
    expect(str_contains(asistenteFuente(), 'catch'))->toBeTrue(
        '`csrf_token()` lanza si no hay sesión: sin guarda, el componente muere en un trámite público sin sesión.'
    );
});

// ---------------------------------------------------------------------------
// 2. Solo el paso actual existe. Es el riesgo que nombra la ficha.
// ---------------------------------------------------------------------------

it('no esconde pasos: el que no se rinde no está en el DOM', function () {
    $fuente = asistenteFuenteSinComentarios();

    /*
     * En Livewire 3 el `wire:model` de un campo escondido con `x-show` SE SIGUE
     * ENVIANDO: el campo queda en el DOM, en la carga útil y en el orden de
     * tabulación. Un asistente que oculta pasos con CSS es exactamente el defecto
     * que esta pieza existe para no repetir, así que el componente no tiene
     * mecanismo de ocultar: rinde la ranura, que trae un paso y nada más.
     */
    // El bloque de estilos queda fuera: ahí `display:none` es legítimo (en papel
    // no hay nada que enviar). Lo que se vigila es el MARCADO.
    $marcado = (string) preg_replace('/<style>.*?<\/style>/s', '', $fuente);

    foreach (['x-show', 'x-cloak', 'display:none', 'hidden="hidden"'] as $aguja) {
        expect(str_contains($marcado, $aguja))->toBeFalse(
            "El componente usa `{$aguja}`: un paso escondido sigue enviando sus campos y sigue tabulándose."
        );
    }

    // Y la ranura sale UNA vez, sin envoltorios por paso que insinúen lo contrario.
    expect(substr_count($fuente, '{{ $slot }}'))->toBe(1,
        'La ranura se rinde una sola vez: el paso actual. Varios <div> por paso es la puerta de atrás al mismo defecto.'
    );
});

it('no guarda el borrador en el navegador (Ley 21.719)', function () {
    $fuente = asistenteFuenteSinComentarios();

    foreach (['localStorage', 'sessionStorage', 'indexedDB', 'document.cookie'] as $aguja) {
        expect(str_contains($fuente, $aguja))->toBeFalse(
            "El componente escribe en `{$aguja}`: el borrador de la credencial de discapacidad contiene diagnóstico —dato "
            .'sensible del artículo 2 letra g) de la Ley 21.719— y quedaría en el disco de un equipo compartido del mesón. '
            .'Va al servidor, cifrado y con base de licitud explícita.'
        );
    }
});

it('no consulta la petición, ni la sesión de nadie, ni permisos', function () {
    $fuente = asistenteFuenteSinComentarios();

    foreach (['request(', 'Auth::', 'auth()', 'Gate::', 'can(', '->user()'] as $aguja) {
        expect(str_contains($fuente, $aguja))->toBeFalse(
            "El componente llama a `{$aguja}`: el paquete recibe todo resuelto por el anfitrión y no decide quién puede qué."
        );
    }
});

// ---------------------------------------------------------------------------
// 3. El foco al cambiar de paso
// ---------------------------------------------------------------------------

it('el encabezado del paso es el destino del foco', function () {
    $html = asistenteSinEstilos(asistenteCompleto());

    expect(preg_match('/<h3[^>]*id="[^"]*-paso-titulo"[^>]*tabindex="-1"/', $html))->toBe(1,
        'El encabezado del paso tiene que ser enfocable por programa (tabindex="-1") para poder recibir el foco (WCAG 2.2 AA 2.4.3).'
    );
    expect(preg_match('/<section[^>]*aria-labelledby="[^"]*-paso-titulo"/', $html))->toBe(1,
        'El paso es una sección CON NOMBRE: así se puede saltar a ella y el lector dice a qué región entró.'
    );
});

it('mueve el foco al cambiar de paso, y NO al cargar el primero', function () {
    $paso2 = asistenteCompleto();
    $paso1 = asistenteHtml(asistentePasos().' :current="0" title="Solicitud"');

    expect(str_contains($paso2, 'autofocus'))->toBeTrue(
        'Sin envío real de formulario (página nueva, sin JS) el foco no llega solo al paso nuevo: `autofocus` es esa mitad.'
    );
    expect(str_contains($paso2, 'x-init'))->toBeTrue(
        'Dentro de Livewire no hay carga de página que dispare `autofocus`: el `x-init` de Alpine core es la otra mitad.'
    );

    expect(str_contains($paso1, 'autofocus'))->toBeFalse(
        'Al CARGAR el primer paso el foco no se mueve: robarlo se salta el enlace «Saltar al contenido» y desorienta '
        .'a quien llega con el teclado. Solo se mueve cuando hubo cambio de paso de verdad.'
    );
    /*
     * Por el camino de Alpine el primer paso SÍ lleva `x-init`, en modo «navegacion»:
     * volver a él con «Anterior» o con el indicador es un cambio de paso, el botón
     * pulsado desaparece del DOM y, sin nadie que lo reciba, el foco cae al <body>.
     * Lo que distingue la carga de la vuelta es la marca en memoria del <form> que
     * pone el clic en un control; eso se mide con Livewire real al final del archivo.
     */
    expect(str_contains($paso1, 'data-muni-asis-foco="navegacion"'))->toBeTrue(
        'El primer paso tiene que mover el foco solo si se llegó a él navegando, no al cargar.'
    );
    expect(str_contains($paso2, 'data-muni-asis-foco="siempre"'))->toBeTrue(
        'Del segundo paso en adelante el `x-init` mueve el foco siempre: es la mitad de Livewire.'
    );

    $apagado = asistenteCompleto(':focus-step="false"');
    expect(str_contains($apagado, 'autofocus'))->toBeFalse(
        'El anfitrión que mueve el foco por su cuenta tiene que poder apagarlo: dos cosas moviendo el foco es peor que ninguna.'
    );
    expect(str_contains($apagado, 'data-muni-asis-foco'))->toBeFalse('Apagado también por el camino de Alpine.');
});

it('en Livewire el encabezado cambia de clave con el paso, o el morph no vuelve a mover el foco', function () {
    /*
     * Hallazgo del revisor, medido con el morph de Livewire 4.4.1: el `x-init` está
     * en el encabezado, y si el encabezado conserva id, clase y atributos entre dos
     * pasos el morph CONSERVA el nodo y no vuelve a ejecutar `x-init`. Del paso 1 al
     * 2 funcionaba (ahí el atributo se agrega); del 2 al 3 el foco se quedaba en
     * «Siguiente». La clave del morph en Livewire 3 y 4 es `wire:key` y, si no, el
     * `id`: una clave por paso obliga a reemplazar el nodo.
     */
    $clave = function (int $paso): ?string {
        preg_match('/<h3[^>]*wire:key="([^"]+)"[^>]*muni-asis__paso-titulo/s', asistenteHtml(asistentePasos().' :current="'.$paso.'" title="Solicitud"'), $m);

        return $m[1] ?? null;
    };

    expect($clave(1))->not->toBeNull();
    expect($clave(1) !== $clave(2) && $clave(2) !== $clave(3) && $clave(0) !== $clave(1))->toBeTrue(
        'El `wire:key` del encabezado tiene que ser distinto en cada paso.'
    );
    expect($clave(2))->toBe($clave(2),
        'Y estable entre dos renders del mismo paso: si no, cada re-render de Livewire le quita el foco a quien escribe.'
    );
});

it('Enter en un campo envía el paso: el primer botón submit del formulario es «Siguiente»', function () {
    /*
     * Hallazgo del revisor, medido en Chromium y Firefox: Enter en un campo hace clic
     * en el PRIMER botón submit del <form> en orden del DOM. Era el paso 1 del
     * indicador (con nav-name) o «Anterior» (sin él), los dos con `formnovalidate`:
     * `_ir_a_paso=0` o `_accion=anterior`, sin validar, cada vez.
     */
    foreach (['nav-name="_ir_a_paso" skippable', 'skippable', ''] as $extra) {
        $html = asistenteSinEstilos(asistenteCompleto($extra));
        preg_match('/<form.*?<\/form>/s', $html, $form);
        preg_match_all('/<button\b[^>]*>/', $form[0] ?? '', $botones);

        $primerSubmit = null;
        foreach ($botones[0] as $b) {
            if (! preg_match('/type="button"/', $b)) {
                $primerSubmit = $b;
                break;
            }
        }

        expect($primerSubmit)->not->toBeNull();
        expect(str_contains((string) $primerSubmit, 'value="siguiente"'))->toBeTrue(
            "Con «{$extra}», el primer submit del formulario no es «Siguiente»: Enter ejecuta otro botón. Fue: {$primerSubmit}"
        );
        expect(str_contains((string) $primerSubmit, 'formnovalidate'))->toBeFalse('Enter tiene que validar el paso, como «Siguiente».');
        expect(str_contains((string) $primerSubmit, 'tabindex="-1"') && str_contains((string) $primerSubmit, 'aria-hidden="true"'))->toBeTrue(
            'El botón por defecto duplica «Siguiente»: fuera del orden de tabulación y del árbol de accesibilidad.'
        );
    }

    // En el último paso Enter envía la solicitud.
    $ultimo = asistenteSinEstilos(asistenteHtml(asistentePasos().' :current="4"'));
    preg_match('/<button\b[^>]*>/', $ultimo, $primero);
    expect(str_contains($primero[0] ?? '', 'value="enviar"'))->toBeTrue('En el último paso Enter envía la solicitud.');

    // Oculto por recorte, no con display:none ni `hidden`.
    expect(preg_match('/\.muni-asis__defecto\s*\{[^}]*clip-path:inset\(50%\)/', asistenteCss()))->toBe(1,
        'El botón por defecto se oculta por recorte: sigue renderizado y sigue siendo el de por defecto.'
    );
    expect(preg_match('/\.muni-asis__defecto\s*\{[^}]*display:\s*none/', asistenteCss()))->toBe(0);
});

it('el encabezado enfocado no queda tapado por la barra superior', function () {
    expect(preg_match('/\.muni-asis__paso-titulo\s*\{[^}]*scroll-margin-top:calc\(var\(--muni-topbar-h\)/', asistenteCss()))->toBe(1,
        'La barra superior es sticky con z-index 100: sin `scroll-margin-top` el encabezado recién enfocado queda debajo '
        .'y el anillo de foco no se ve (WCAG 2.2 AA 2.4.11).'
    );
    expect(preg_match('/\.muni-asis__paso-titulo:focus-visible\s*\{[^}]*outline:3px solid/', asistenteCss()))->toBe(1,
        'Un destino de foco sin indicador visible es un foco perdido.'
    );
});

it('cuando el paso vuelve con errores, el foco es del resumen y no del encabezado', function () {
    $conErrores = asistenteCompleto(':errors="[\'medico\' => \'Falta el nombre del médico tratante.\']"');

    expect(preg_match('/<h3[^>]*muni-asis__paso-titulo[^>]*autofocus/s', $conErrores))->toBe(0,
        'Con los dos activos gana el encabezado —el `autofocus` y el `$nextTick` corren después de que el resumen se '
        .'enfoca— y la persona llega al paso sin enterarse de que había errores. El resumen manda (WCAG 2.2 AA 3.3.1).'
    );
    /*
     * Y SIN JS el foco tiene que ir a alguna parte. Hallazgo del revisor: el resumen
     * lo mueve con `x-init`, así que en un envío real sin Alpine nadie se lo llevaba.
     * El `autofocus` va en el MISMO nodo que el `x-init` del resumen.
     */
    expect(preg_match('/<div[^>]*role="alert"[^>]*autofocus/s', $conErrores))->toBe(1,
        'Sin Alpine el resumen de errores no recibía el foco y el encabezado cedía: no se lo llevaba nadie.'
    );
    expect(str_contains($conErrores, 'data-muni-asis-foco="no"'))->toBeTrue('El `x-init` del encabezado tampoco lo disputa.');
    // El `x-init` que queda es el del resumen de errores, que es justamente quien
    // se lleva el foco: lo que no puede haber es uno en el encabezado del paso.

    // Y si el anfitrión apagó el resumen, el encabezado vuelve a llevarse el foco:
    // lo que no puede pasar es que no se lo lleve NADIE.
    expect(str_contains(
        asistenteCompleto(':error-summary="false" :errors="[\'medico\' => \'Falta el nombre.\']"'),
        'autofocus'
    ))->toBeTrue('Sin resumen que se lleve el foco, el encabezado del paso vuelve a ser el destino.');

    // Sin errores, el resumen no existe y el encabezado manda.
    expect(str_contains(asistenteCompleto(), 'autofocus'))->toBeTrue('Sin errores nada le disputa el foco al encabezado.');
});

// ---------------------------------------------------------------------------
// 4. El anuncio del avance
// ---------------------------------------------------------------------------

it('anuncia el avance con progressbar y región viva, sin hablar dos veces', function () {
    $html = asistenteSinEstilos(asistenteCompleto());

    expect(preg_match('/role="progressbar"/', $html))->toBe(1, 'Falta el progressbar del avance.');
    expect(str_contains($html, 'aria-valuenow="2"'))->toBeTrue('El paso actual no llegó al progressbar.');
    expect(str_contains($html, 'aria-valuemax="5"'))->toBeTrue('El total de pasos no llegó al progressbar.');
    expect(str_contains($html, 'aria-valuetext="Paso 2 de 5: Antecedentes médicos"'))->toBeTrue(
        'Sin `aria-valuetext` el lector dice «2», que no significa nada: el texto es lo que convierte el número en avance.'
    );
    expect(preg_match('/role="status"[^>]*aria-live="polite"/', $html))->toBe(1,
        'El avance se anuncia solo en una región viva `polite` (WCAG 2.2 AA 4.1.3).'
    );

    /*
     * Y no dos veces: el nombre del paso lo dice el encabezado que acaba de recibir
     * el foco. Repetirlo en la región viva hace que el lector diga «Antecedentes
     * médicos» dos veces seguidas, que es ruido y no accesibilidad.
     */
    preg_match('/<div class="muni-asis__avance"[^>]*>(.*?)<\/div>\s*<\/div>/s', $html, $m);
    expect(str_contains($m[1] ?? '', 'Paso 2 de 5</p>'))->toBeTrue('La región viva tiene que decir el contador.');
    expect(substr_count($m[1] ?? '', 'Antecedentes médicos'))->toBe(1,
        'El nombre del paso aparece en la región viva más de una vez: solo puede estar en `aria-valuetext`, que no es '
        .'lo que se locuta al cambiar el contenido.'
    );
});

it('el ancho de la barra no depende de un número mágico', function () {
    expect(str_contains(asistenteCompleto(), 'style="width:40%"'))->toBeTrue(
        'Paso 2 de 5 es 40%: el ancho sale del avance real, no de una clase fija.'
    );
    expect(str_contains(asistenteHtml(asistentePasos().' :current="4"'), 'style="width:100%"'))->toBeTrue(
        'En el último paso la barra tiene que estar llena.'
    );
});

it('un asistente sin pasos declarados no emite un progressbar vacío', function () {
    $html = asistenteHtml('title="Solicitud" action="/x"');

    expect(str_contains($html, 'role="progressbar"'))->toBeFalse(
        'Un progressbar de «paso 1 de 0» es peor que ninguno: anuncia un avance que no existe.'
    );
    expect(str_contains($html, '<form'))->toBeTrue('Sin pasos el formulario sigue siendo un formulario.');
});

// ---------------------------------------------------------------------------
// 5. Los botones: anterior, omitir, siguiente, enviar
// ---------------------------------------------------------------------------

it('Anterior y Omitir llevan formnovalidate; Siguiente no', function () {
    $html = asistenteSinEstilos(asistenteCompleto('skippable'));

    preg_match_all('/<button[^>]*>/', $html, $botones);
    $porValor = [];

    foreach ($botones[0] as $etiqueta) {
        if (preg_match('/value="(anterior|omitir|siguiente|enviar)"/', $etiqueta, $m)) {
            $porValor[$m[1]] = $etiqueta;
        }
    }

    expect(array_keys($porValor))->toEqualCanonicalizing(['anterior', 'omitir', 'siguiente'],
        'Faltan botones: en un paso intermedio y omitible tienen que estar los tres.'
    );

    expect(str_contains($porValor['anterior'], 'formnovalidate'))->toBeTrue(
        'Sin `formnovalidate`, el navegador bloquea el retroceso porque ESTE paso tiene un campo obligatorio vacío: la '
        .'persona queda encerrada en un paso que no puede completar ni abandonar. Es una trampa de teclado con otro nombre.'
    );
    expect(str_contains($porValor['omitir'], 'formnovalidate'))->toBeTrue(
        'Omitir un paso con campos obligatorios vacíos es justamente omitirlo: validar ahí lo hace imposible.'
    );
    expect(str_contains($porValor['siguiente'], 'formnovalidate'))->toBeFalse(
        'Avanzar sí valida: es el único momento en que la validación del navegador ayuda.'
    );
});

it('en el primer paso no hay Anterior, y en el último el botón envía', function () {
    $primero = asistenteSinEstilos(asistenteHtml(asistentePasos().' :current="0" action="/credencial"'));
    $ultimo = asistenteSinEstilos(asistenteHtml(asistentePasos().' :current="4" action="/credencial"'));

    expect(str_contains($primero, 'value="anterior"'))->toBeFalse('No hay paso anterior al primero.');
    expect(str_contains($primero, 'value="siguiente"'))->toBeTrue('Del primer paso se sigue avanzando.');

    expect(str_contains($ultimo, 'value="enviar"'))->toBeTrue(
        'En el último paso el botón no dice «Siguiente»: envía la solicitud, y el servidor tiene que poder distinguirlo.'
    );
    expect(str_contains($ultimo, 'Enviar solicitud'))->toBeTrue('El rótulo del último paso tiene que decir qué hace.');
    expect(str_contains($ultimo, 'value="siguiente"'))->toBeFalse('Después del último paso no hay «siguiente».');

    // Y con `prev-href` el retroceso es un enlace de verdad, no un submit.
    $conEnlace = asistenteSinEstilos(asistenteHtml(asistentePasos().' :current="2" prev-href="/credencial/paso/2"'));
    expect(preg_match('/<a class="muni-asis__btn[^"]*" href="\/credencial\/paso\/2"/', $conEnlace))->toBe(1,
        'Cuando el paso anterior es una URL, el control es un <a>: se abre en otra pestaña, se copia y se comparte.'
    );
});

it('el orden del DOM es el orden visual', function () {
    // Solo el pie: el botón por defecto que atiende Enter va antes a propósito y
    // está fuera del orden de tabulación.
    $html = (string) strstr(asistenteSinEstilos(asistenteCompleto('skippable')), '<footer');

    $anterior = strpos($html, 'value="anterior"');
    $omitir = strpos($html, 'value="omitir"');
    $siguiente = strpos($html, 'value="siguiente"');

    expect($anterior < $omitir && $omitir < $siguiente)->toBeTrue(
        'Anterior · Omitir · Siguiente es el orden visual, y el de tabulación tiene que ser el mismo (WCAG 2.2 AA 1.3.2).'
    );

    expect(preg_match('/justify-content:space-between/', asistenteCss()))->toBe(1,
        'El retroceso a la izquierda y el avance a la derecha: invertirlos con `row-reverse` rompería el orden de tabulación.'
    );
});

it('Anterior y Siguiente dicen a qué paso llevan', function () {
    $html = asistenteCompleto();

    expect(str_contains($html, 'Anterior<span class="muni-asis__sr">: Identificación</span>'))->toBeTrue(
        '«Anterior» a secas obliga a recordar qué había atrás. El nombre accesible completo lo dice; el rótulo visible '
        .'sigue siendo «Anterior», que es lo que pide 2.5.3 (el nombre visible contenido en el accesible).'
    );
    expect(str_contains($html, 'Siguiente<span class="muni-asis__sr">: Documentos</span>'))->toBeTrue(
        'Lo mismo hacia adelante: saber a qué paso se va antes de pulsar.'
    );
});

it('los botones aceptan atributos de Livewire y filtran las claves peligrosas', function () {
    $html = asistenteHtml(
        asistentePasos().' :current="1" :next-attrs="[\'wire:click\' => \'siguiente\', \'type\' => \'button\']" '
        .':prev-attrs="[\'onclick=x onmouseover\' => \'alert(1)\']"'
    );

    expect(str_contains($html, 'wire:click="siguiente"'))->toBeTrue(
        'Sin esto el componente no sirve en Livewire: los `name`/`value` de un botón no viajan en un `wire:submit`.'
    );
    expect(str_contains($html, 'onmouseover'))->toBeFalse(
        'Una clave de atributo con espacios cierra la etiqueta y deja inyectar marcado. Viene del anfitrión y no del '
        .'vecino, pero un componente del paquete no confía en eso.'
    );

    /*
     * Hallazgo del revisor: el botón llevaba `type="submit"` fijo y la bolsa agregaba
     * un segundo `type="button"`; el parser se queda con el primero, así que
     * `wire:click` disparaba la acción Y un POST nativo del formulario.
     */
    $sinEstilos = asistenteSinEstilos($html);
    preg_match('/<button[^>]*class="muni-asis__btn muni-asis__btn--pri"[^>]*>/s', $sinEstilos, $siguiente);
    expect(substr_count($siguiente[0] ?? '', 'type='))->toBe(1, 'El botón tiene que escribir `type` una sola vez.');
    expect(str_contains($siguiente[0] ?? '', 'type="button"'))->toBeTrue('`type` por next-attrs tiene que llegar al botón.');

    // El botón por defecto sigue siendo submit (si no, no atiende Enter), lleva el
    // mismo wire:click y anula el envío nativo.
    preg_match('/<button[^>]*class="muni-asis__defecto"[^>]*>/s', $sinEstilos, $defecto);
    expect(str_contains($defecto[0] ?? '', 'type="submit"') && str_contains($defecto[0] ?? '', 'wire:click="siguiente"'))->toBeTrue(
        'Enter tiene que ejecutar lo mismo que «Siguiente».'
    );
    expect(str_contains($defecto[0] ?? '', 'x-on:click="$event.preventDefault()"'))->toBeTrue(
        'Con `type="button"` en «Siguiente» el anfitrión no quiere POST nativo: tampoco por Enter.'
    );

    // Manejadores en línea y esquemas ejecutables, fuera.
    $peligroso = asistenteHtml(
        asistentePasos().' :current="1" prev-href="javascript:alert(1)" :next-attrs="[\'onclick\' => \'alert(1)\']" '
        .':steps="[[\'label\' => \'Uno\', \'href\' => \' javascript:alert(1)\'], \'Dos\']"'
    );
    expect(str_contains($peligroso, 'javascript:'))->toBeFalse('Un href con esquema javascript: no llega al HTML.');
    expect(str_contains($peligroso, 'onclick'))->toBeFalse('Los manejadores en línea no pasan por los atributos extra.');
});

it('el encabezado del paso no pasa de h6', function () {
    $html = asistenteHtml(asistentePasos().' :current="1" level="6" title="Solicitud" :errors="[\'x\' => \'Falta.\']"');

    expect(str_contains($html, '<h7'))->toBeFalse('Con level=6 salía un <h7>, que no existe.');
    expect(preg_match('/<h6[^>]*muni-asis__paso-titulo/s', $html))->toBe(1);
});

it('compone el resumen de errores en vez de reimplementarlo', function () {
    $html = asistenteHtml(
        asistentePasos().' :current="1" :errors="[\'informe\' => \'Adjunta el informe del médico tratante.\']"'
    );

    expect(str_contains($html, 'role="alert"'))->toBeTrue(
        'El resumen de errores del paso tiene que anunciarse (WCAG 2.2 AA 3.3.1): lo resuelve <x-muni::error-summary>.'
    );
    expect(str_contains($html, 'Adjunta el informe del médico tratante.'))->toBeTrue('El error no llegó al resumen.');

    expect(str_contains(
        asistenteHtml(asistentePasos().' :error-summary="false" :errors="[\'informe\' => \'Falta el informe.\']"'),
        'Falta el informe.'
    ))->toBeFalse('El anfitrión que pone el resumen en otro sitio tiene que poder apagar este.');
});

// ---------------------------------------------------------------------------
// 6. El stepper reparado
// ---------------------------------------------------------------------------

it('el estado de cada paso va en PALABRAS, no solo en el color', function () {
    $html = stepperHtml(':steps="[[\'label\' => \'Identificación\'], [\'label\' => \'Documentos\', \'state\' => \'error\'], '
        .'[\'label\' => \'Declaración\', \'state\' => \'skipped\'], [\'label\' => \'Revisión\']]" :current="3"');

    // Los dos estados que son EXCEPCIÓN se ven: quien no distingue el rojo del
    // gris tiene que poder ver qué paso quedó mal (WCAG 2.2 AA 1.4.1).
    expect(str_contains($html, '<span class="muni-step__estado">Con errores</span>'))->toBeTrue(
        'El paso con errores lo dice en texto VISIBLE: el aspa y el rojo no alcanzan.'
    );
    expect(str_contains($html, '<span class="muni-step__estado">Omitido</span>'))->toBeTrue(
        'El paso omitido lo dice en texto VISIBLE.'
    );

    // Y los tres normales lo dicen igual, en el árbol de accesibilidad.
    foreach (['Completado', 'Paso actual'] as $palabra) {
        expect(str_contains($html, 'muni-step__estado muni-step__sr">'.$palabra.'</span>'))->toBeTrue(
            "El estado «{$palabra}» tiene que existir para el lector de pantalla aunque no se vea: el color y la marca "
            .'no llegan al árbol de accesibilidad.'
        );
    }

    // La forma también distingue, no solo el color: aspa, guion y marca.
    expect(str_contains($html, 'M4.5 4.5l7 7M11.5 4.5l-7 7'))->toBeTrue('Falta el aspa del paso con errores.');
    expect(str_contains($html, 'M4 8h8'))->toBeTrue('Falta el guion del paso omitido.');
});

it('el paso navegable es un <button> o un <a> de verdad', function () {
    $conBoton = stepperHtml(':steps="[\'Identificación\', \'Documentos\', \'Revisión\']" :current="1" nav-name="_ir_a_paso"');

    expect(preg_match('/<button type="submit" class="muni-step__ctrl" formnovalidate\s+name="_ir_a_paso" value="0"/', $conBoton))->toBe(1,
        'Sin control no se puede volver al paso 1 desde el indicador: era el defecto que la ficha describe («no hay '
        .'<button>, ni <a>, ni tabindex, ni manejador de eventos»).'
    );
    expect(str_contains($conBoton, 'value="2"'))->toBeTrue('Los pasos posteriores también pueden ofrecerse: lo decide el anfitrión.');

    // El paso ACTUAL nunca: un control que lleva a donde ya estás es una parada
    // de tabulación que no hace nada.
    expect(preg_match('/aria-current="step">\s*<button/', $conBoton))->toBe(0,
        'El paso actual no puede ser un control.'
    );

    $conEnlace = stepperHtml(':steps="[[\'label\' => \'Identificación\', \'href\' => \'/paso/1\'], \'Documentos\']" :current="1"');
    expect(preg_match('/<a href="\/paso\/1" class="muni-step__ctrl"/', $conEnlace))->toBe(1,
        'Un paso con `href` es un enlace: se abre en otra pestaña y se copia.'
    );

    $bloqueado = stepperHtml(':steps="[\'Identificación\', \'Documentos\', [\'label\' => \'Revisión\', \'disabled\' => true]]" '
        .':current="1" nav-name="_ir_a_paso"');
    expect(str_contains($bloqueado, 'value="2"'))->toBeFalse(
        'Un paso marcado `disabled` no ofrece control: quién puede saltar a qué paso lo decide el servidor.'
    );
});

it('el asistente no ofrece saltar hacia adelante desde el indicador', function () {
    $html = asistenteCompleto('nav-name="_ir_a_paso"');

    expect(str_contains($html, 'name="_ir_a_paso" value="0"'))->toBeTrue('Volver a un paso ya visitado es un derecho.');

    foreach (['2', '3', '4'] as $futuro) {
        expect(str_contains($html, 'name="_ir_a_paso" value="'.$futuro.'"'))->toBeFalse(
            'Saltar hacia adelante desde el indicador se lleva por delante la validación del paso en curso.'
        );
    }

    // Sin `nav-name` el indicador vuelve a ser lo que era: un indicador.
    expect(str_contains(asistenteSinEstilos(asistenteCompleto()), 'class="muni-step__ctrl"'))->toBeFalse(
        'La navegación es opt-in: un asistente cuyo flujo no permite volver no debe emitir controles que no hacen nada.'
    );
});

it('el número visible de un paso navegable está en su nombre accesible (2.5.3)', function () {
    $html = stepperHtml(':steps="[\'Identificación\', [\'label\' => \'Documentos\', \'state\' => \'todo\'], \'Revisión\']" :current="2" nav-name="_ir_a_paso"');

    preg_match('/<button[^>]*value="1"[^>]*>(.*?)<\/button>/s', $html, $control);
    expect(preg_match('/<span class="muni-step__marker"\s*>\s*<span class="muni-step__num">2<\/span>/', $control[1] ?? ''))->toBe(1,
        'Dentro de un control el número del marcador es texto visible —en el teléfono del asistente, el único— y el '
        .'nombre accesible tiene que contenerlo para el control por voz.'
    );

    // Fuera de un control nada cambia: el marcador sigue siendo decorativo.
    expect(str_contains(stepperHtml(':steps="[\'Uno\', \'Dos\']" :current="1"'), 'muni-step__marker" aria-hidden="true"'))->toBeTrue(
        'El indicador de siempre conserva el marcador fuera del árbol de accesibilidad.'
    );
});

it('el stepper de siempre sigue rindiendo igual', function () {
    $html = asistenteSinEstilos(stepperHtml(':steps="[\'Uno\', \'Dos\', \'Tres\']" :current="1"'));

    expect(str_contains($html, '<ol class="muni-stepper"'))->toBeTrue('La lista ordenada es la semántica del indicador.');
    expect(str_contains($html, 'aria-current="step"'))->toBeTrue('El paso actual se marca con aria-current (DESIGN §10).');
    expect(str_contains($html, 'muni-step--done'))->toBeTrue('El paso cumplido conserva su estado.');
    expect(str_contains($html, 'M3.5 8.5l3 3 6-6.5'))->toBeTrue('La marca de verificación del paso cumplido no se toca.');
    expect(str_contains($html, 'muni-stepper--v'))->toBeFalse('La variante vertical solo con `orientation="vertical"`.');
    expect(str_contains(asistenteSinEstilos(stepperHtml(':steps="[\'Uno\']" orientation="vertical"')), 'muni-stepper--v'))->toBeTrue(
        'La variante vertical sigue existiendo: nueve sistemas la usan.'
    );
    // Nada nuevo se activa solo: sin `nav-name` ni `href` no hay controles.
    expect(str_contains($html, '<button'))->toBeFalse('El indicador de siempre no emite controles.');
});

it('la línea que une los pasos no tacha el rótulo', function () {
    preg_match('/\.muni-step:not\(:last-child\)::after\s*\{([^}]*)\}/', asistenteCss(), $regla);

    expect(str_contains($regla[1] ?? '', 'position:absolute'))->toBeFalse(
        'La línea horizontal era absoluta y arrancaba a 40 px, justo donde empieza el rótulo: todo paso de una sola '
        .'línea salía TACHADO. Visto en captura, no en un test de texto: por eso el navegador lo mide por geometría.'
    );
    expect(str_contains($regla[1] ?? '', 'flex:1 1 16px'))->toBeTrue(
        'Como ítem flexible la línea vive DESPUÉS del texto y no puede pisarlo.'
    );
});

// ---------------------------------------------------------------------------
// 7. El contrato del paquete
// ---------------------------------------------------------------------------

it('no escribe un solo color literal', function () {
    $css = asistenteCss();
    preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/', $css, $literales);

    $sobrantes = array_values(array_filter($literales[0], fn (string $c) => strtolower($c) !== '#767676'));

    expect($sobrantes)->toBe([],
        'Cero colores literales fuera del respaldo de foco #767676 (DESIGN §10): un tono escrito a mano no cambia cuando '
        .'el municipio cambia su identidad, y casi siempre se olvida su contraparte oscura.'
    );
});

it('usa solo tokens declarados en las DOS hojas', function () {
    $hojas = [
        'muni-ui.css' => cssMuniUi(),
        'muni-ui-filament.css' => cssMuniUiFilament(),
    ];

    preg_match_all('/var\(\s*(--muni-[a-z0-9-]+)/', asistenteFuente()."\n".stepperFuente(), $usados);

    expect(count(array_unique($usados[1])))->toBeGreaterThan(5, 'El componente no está leyendo tokens: algo se escribió a mano.');

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
    $css = asistenteCss();

    expect(substr_count($css, 'outline:3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeGreaterThan(2,
        'El indicador real es el outline: dentro de Filament la box-shadow del anillo se computa transparente (DESIGN §5). '
        .'Lo necesitan los botones del asistente, el encabezado del paso y el paso navegable.'
    );
    expect(preg_match('/\.muni-asis__btn\s*\{[^}]*min-height:44px/', $css))->toBe(1,
        'Mesón y vecino mayor: 44×44 (WCAG 2.2 AA 2.5.8 pide 24, el ecosistema pide 44 en interfaces de terreno).'
    );
    expect(preg_match('/\.muni-step__ctrl\s*\{[^}]*min-height:44px/', $css))->toBe(1,
        'El paso navegable es un control y se pulsa con el dedo igual que un botón.'
    );
});

it('el movimiento sale del token y se apaga solo', function () {
    $css = asistenteCss();

    preg_match_all('/transition:([^;}]+)/', $css, $transiciones);

    foreach ($transiciones[1] as $valor) {
        expect((bool) preg_match('/var\(--muni-dur\)|none/', $valor))->toBeTrue(
            "La transición `{$valor}` usa una duración fija: ignora `prefers-reduced-motion` (DESIGN §6), que es lo que "
            .'hace hoy la barra de `progress`.'
        );
    }

    expect(str_contains($css, '@media (prefers-reduced-motion:reduce)'))->toBeTrue(
        'Aunque el token ya baje a 0 ms, el bloque explícito es el candado de las transiciones que se agreguen después.'
    );
});

it('el CSS viaja dentro del bloque de estilos del componente', function () {
    foreach (['asistente' => asistenteFuente(), 'stepper' => stepperFuente()] as $cual => $fuente) {
        expect(preg_match('/@once\s*<style>/s', $fuente))->toBe(1,
            "El CSS de `{$cual}` tiene que viajar con el componente: dentro de un panel Filament solo se inyecta "
            .'muni-ui-filament.css, y una clase declarada en muni-ui.css se vería sin estilo y sin un solo error (DESIGN §7).'
        );
    }

    // DESIGN §8, trampa #5: una directiva nombrada dentro de un comentario CSS
    // /* */ se compila igual y abre un bloque que nadie cierra.
    preg_match_all('#/\*(.*?)\*/#s', asistenteCss().implode('', array_map(
        fn (string $f) => $f,
        [asistenteFuente(), stepperFuente()]
    )), $comentarios);

    foreach ($comentarios[1] as $comentario) {
        expect((bool) preg_match('/@(once|if|endif|foreach|php)\b/', $comentario))->toBeFalse(
            'Hay una directiva de Blade nombrada dentro de un comentario CSS: Blade la compila igual y el componente '
            .'muere con «unexpected end of file» apuntando al archivo compilado (DESIGN §8, trampa #5).'
        );
    }
});

it('el id no sale de uniqid() y no cambia entre dos renders', function () {
    $fuente = asistenteFuenteSinComentarios();

    expect(str_contains($fuente, 'uniqid('))->toBeFalse(
        'Un id con uniqid() cambia en cada render, rompe el aria-labelledby y ensucia el diffing de Livewire (DESIGN §10).'
    );

    preg_match('/id="(muni-asis-[a-f0-9]+)"/', asistenteCompleto(), $uno);
    preg_match('/id="(muni-asis-[a-f0-9]+)"/', asistenteCompleto(), $dos);

    expect($uno[1] ?? 'a')->toBe($dos[1] ?? 'b', 'Dos renders del mismo asistente tienen que dar el mismo id.');

    // Y el del consumidor manda siempre.
    expect(str_contains(asistenteHtml(asistentePasos().' id="solicitud-credencial"'), 'id="solicitud-credencial"'))->toBeTrue(
        'El id que pasa el anfitrión manda sobre el derivado.'
    );
    expect(str_contains(asistenteHtml(asistentePasos().' id="solicitud-credencial"'), 'id="solicitud-credencial-paso-titulo"'))->toBeTrue(
        'Los ids internos cuelgan del id del formulario: si no, dos asistentes en la misma página comparten encabezado.'
    );
});

it('funciona en Livewire 3 y en Livewire 4', function () {
    $fuente = asistenteFuenteSinComentarios();

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $exclusiva) {
        expect(str_contains($fuente, $exclusiva))->toBeFalse(
            "`{$exclusiva}` no existe en Livewire 3, y `personas-graneros` sigue ahí."
        );
    }

    expect(str_contains($fuente, '@keydown.escape.window'))->toBeFalse(
        'Escape se escucha en el elemento, no en la ventana: en la ventana, un asistente dentro de un modal cierra las dos cosas.'
    );
});

it('nada del anfitrión entra crudo en una expresión de Alpine', function () {
    $fuente = asistenteFuenteSinComentarios();

    preg_match_all('/\sx-(init|data|text|effect|show|model|on:[a-z.]+)="([^"]*)"/', $fuente, $expresiones, PREG_SET_ORDER);

    foreach ($expresiones as [$todo, $directiva, $expresion]) {
        expect((bool) preg_match('/\{\{(?!--)/', $expresion))->toBeFalse(
            "La expresión de `x-{$directiva}` interpola Blade sin pasar por @js(): un apóstrofo en un texto del anfitrión "
            .'descuadra la expresión y tumba el Alpine de la página ENTERA, no solo el de este componente. Ya pasó en '
            .'`file-dropzone`.'
        );
    }

    // Y el texto del anfitrión se rinde como contenido escapado, no como JS.
    $expresionDe = function (string $html): string {
        preg_match('/<h3[^>]*x-init="([^"]*)"/s', $html, $m);

        return $m[1] ?? '';
    };

    $una = $expresionDe(asistenteCompleto());
    $otra = $expresionDe(asistenteHtml(':steps="[\'Datos del vecino\', \'Otro\']" :current="1" title="Trámite de O\'Higgins"'));

    expect($una !== '' && str_contains($una, '$nextTick(() => $el.focus())'))->toBeTrue('Falta la expresión que mueve el foco.');
    expect($una)->toBe($otra, 'La expresión de Alpine del encabezado es fija: no la construye ninguna prop, lo que varía va en un data-*.');
});

it('el texto del anfitrión se escapa', function () {
    $html = asistenteHtml(
        ':steps="[\'Paso <script>alert(1)</script>\']" title="Trámite del vecino O\'Higgins" '
        .'step-subtitle="Adjunta el informe del <b>médico</b>"'
    );

    expect(str_contains($html, '<script>alert(1)</script>'))->toBeFalse('El rótulo de un paso se escapa.');
    expect(str_contains($html, '<b>médico</b>'))->toBeFalse('La bajada del paso se escapa.');
    expect(str_contains($html, 'O&#039;Higgins'))->toBeTrue('El apóstrofo se escapa y no rompe nada.');
});

// ---------------------------------------------------------------------------
// 8. Verificación en navegador
// ---------------------------------------------------------------------------

/*
 * El banco. Se genera acá y no en un script suelto porque este es el único sitio
 * del paquete con Blade arrancado (mismo criterio que `GeneraVitrinaTest` y
 * `FormularioDeTramiteTest`). Sale a `build/asistente/`, ignorado por git.
 *
 * Siete páginas, y cada una existe por algo que ningún str_contains alcanza:
 *
 * · `claro` y `oscuro` — el paso 2 de 5 con Alpine: el foco que se va al
 *   encabezado del paso nuevo, el recorrido de Tab, el contraste de los estados
 *   de error y omitido, y el foco visible de cada control.
 * · `panel-claro` y `panel-oscuro` — el MISMO asistente cargando ÚNICAMENTE
 *   `muni-ui-filament.css` (DESIGN §7): dentro de un panel `muni-ui.css` no se
 *   carga, y es ahí donde una clase declarada fuera del bloque de estilos se
 *   queda sin estilo y sin un solo error en consola.
 * · `primer-paso` — el paso 1 con Alpine: el foco NO se puede mover al cargar,
 *   porque eso se salta el enlace «Saltar al contenido».
 * · `errores` — el paso devuelto con errores: el resumen se lleva el foco.
 * · `sin-alpine` — el mismo paso SIN el script: el trámite tiene que poder
 *   enviarse igual, y el `autofocus` es lo que mueve el foco cuando el paso
 *   llega por una carga de página de verdad.
 */
function asistenteAlpineDelBanco(): ?string
{
    $ruta = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    return is_file($ruta) ? (string) file_get_contents($ruta) : null;
}

it('genera el banco de navegador en build/asistente/', function () {
    $dir = __DIR__.'/../build/asistente';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $alpine = asistenteAlpineDelBanco();

    expect($alpine)->not->toBeNull(
        'No está node_modules/alpinejs: corre `npm install`. Sin Alpine el banco no puede medir el foco que se mueve al '
        .'cambiar de paso, que es justo lo que ninguna prueba de texto alcanza.'
    );

    /*
     * Los cinco pasos con los dos estados nuevos puestos donde duelen: un paso ya
     * visitado que quedó CON ERRORES y uno que se OMITIÓ. Es el caso real de la
     * credencial de discapacidad, donde «Documentos» se puede completar después.
     */
    $pasos = ':steps="['
        ."['label' => 'Identificación'],"
        ."['label' => 'Antecedentes médicos', 'hint' => 'Informe del médico tratante'],"
        ."['label' => 'Documentos', 'state' => 'error'],"
        ."['label' => 'Declaración', 'state' => 'skipped'],"
        ."['label' => 'Revisión'],"
        .']"';

    /*
     * Campos reales del paso. SIN `<x-muni::textarea>` a propósito: su regla base
     * lleva `width:100%` con `padding` y `border` y sin `box-sizing:border-box`,
     * así que a 390 px desborda el documento 2 px y la reja marcaba 1.4.10 contra
     * ESTE banco por un defecto que no es del asistente. Es el mismo criterio con
     * que `formulario-tramite` saca del banco el azul de fábrica del navegador:
     * el ruido ajeno impide ver el defecto propio cuando aparezca. Queda anotado
     * como hallazgo para la ficha de `textarea`.
     */
    $campos = '<x-muni::input label="Nombre del médico tratante" name="medico" hint="Como aparece en el informe" required />'
        .'<x-muni::input label="Fecha del informe" name="fecha_informe" type="date" required />'
        .'<x-muni::input label="Diagnóstico declarado" name="diagnostico" hint="Tal como está escrito en el informe" />';

    $base = '<x-muni::asistente '.$pasos.' :current="1" '
        .'title="Solicitud de credencial de discapacidad" '
        .'subtitle="Municipalidad de Graneros · Dirección de Desarrollo Comunitario" '
        .'action="/credencial" nav-name="_ir_a_paso" skippable '
        .'step-subtitle="Los antecedentes que entregó el médico tratante. El borrador se guarda en el servidor, cifrado."';

    $asistente = Blade::render($base.'>'.$campos.'</x-muni::asistente>');

    $primerPaso = Blade::render(str_replace(':current="1"', ':current="0"', $base).'>'.$campos.'</x-muni::asistente>');

    $conErrores = Blade::render(
        $base.' :errors="[\'medico\' => \'Falta el nombre del médico tratante.\', '
        .'\'fecha_informe\' => \'La fecha del informe no puede ser futura.\']">'.$campos.'</x-muni::asistente>'
    );

    $paginas = [
        'claro' => ['light', cssMuniUi(), false, $asistente, true],
        'oscuro' => ['dark', cssMuniUi(), false, $asistente, true],
        'panel-claro' => ['light', cssMuniUiFilament(), true, $asistente, true],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true, $asistente, true],
        'primer-paso' => ['light', cssMuniUi(), false, $primerPaso, true],
        'errores' => ['light', cssMuniUi(), false, $conErrores, true],
        'sin-alpine' => ['light', cssMuniUi(), false, $asistente, false],
        'errores-sin-alpine' => ['light', cssMuniUi(), false, $conErrores, false],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel, $cuerpoAsistente, $conAlpine]) {
        $oscuro = $tema === 'dark';
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        /* El armazón no aporta ni un color: fondo y texto salen de los mismos
           tokens que lee el componente. */
        $armazon = 'body{margin:0;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'main{padding:24px;max-width:960px;margin:0 auto}'
            .'.antes{padding:0 0 24px}'
            .'.antes a{color:var(--muni-text);text-decoration:underline;text-underline-offset:2px}'
            .'.antes a:focus-visible{outline:3px solid var(--muni-focus, var(--muni-accent, #767676));outline-offset:2px}';

        $script = $conAlpine && $alpine !== null
            ? '<script data-banco="alpine-core">'.$alpine.'</script>'
            : '';

        /* Un enlace ANTES del asistente: así se puede medir dónde empieza el
           recorrido de Tab y si el componente robó el foco al cargar. */
        $antes = '<div class="antes"><a href="#fuera" id="antes">Volver a mis trámites</a></div>';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .$antes.'<section class="fi-section">'.$cuerpoAsistente.'</section></main></div>'.$script.'</body>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$antes.$cuerpoAsistente.'</main>'.$script.'</body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco asistente — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);

        expect(str_contains($pagina, 'class="muni-asis"'))->toBeTrue('El banco '.$nombre.' salió sin el asistente.');
        expect(str_contains($pagina, 'data-banco="alpine-core"'))->toBe($conAlpine,
            'El banco '.$nombre.' no trae el Alpine que le corresponde.'
        );
    }

    // El bloque de estilos se emite UNA vez por petición: si el asistente se
    // hubiera renderizado sin él, lo que se mida serían las medidas del navegador.
    $claro = (string) file_get_contents($dir.'/claro.html');
    expect(str_contains($claro, '.muni-asis__barra-fill'))->toBeTrue('El banco salió sin el bloque de estilos del asistente.');
    expect(str_contains($claro, '.muni-step__ctrl'))->toBeTrue('El banco salió sin el bloque de estilos del stepper.');
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function asistentePythonDeLaReja(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que ningún `str_contains` sobre el fuente puede ver, medido en Chromium y
 * Firefox: adónde va el foco de verdad, qué recorre Tab, qué se computa de color
 * y de movimiento, y cuánto mide cada blanco táctil.
 */
it('en Chromium y Firefox: el foco, el teclado, el contraste y el movimiento', function () {
    $python = asistentePythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/asistente.py').' '
        .escapeshellarg(__DIR__.'/../build/asistente').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador del asistente falló:\n".implode("\n", $lineas));

    // Las cuatro páginas completas × dos navegadores tienen que reportar que el
    // foco aterrizó en el encabezado del paso. Si el banco se quedara sin páginas,
    // el script diría «todo pasa» sobre nada.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'foco=paso'))))->toBe(8,
        "Al cargar un paso que no es el primero, el foco tiene que estar en el encabezado del paso:\n".implode("\n", $lineas));

    // Y en el PRIMER paso, en los dos navegadores, NO se mueve.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'foco=intacto'))))->toBe(2,
        "En el primer paso el foco no se puede mover: robarlo se salta el enlace «Saltar al contenido»:\n".implode("\n", $lineas));

    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'orden=ok'))))->toBe(8,
        "El orden de tabulación tiene que ser Anterior · Omitir · Siguiente, y el avance no puede robar paradas:\n".implode("\n", $lineas));

    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'foco=resumen'))))->toBe(4,
        "En la página con errores el resumen tiene que llevarse el foco en los dos navegadores, con Alpine y SIN él:\n".implode("\n", $lineas));

    // Enter en un campo envía «siguiente», con Alpine (claro) y sin él, en los dos navegadores.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'enter=siguiente'))))->toBe(4,
        "Enter en un campo tiene que enviar el paso, no retroceder:\n".implode("\n", $lineas));

    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'sinjs=ok'))))->toBe(2,
        "Sin Alpine el asistente tiene que seguir siendo un formulario que se envía:\n".implode("\n", $lineas));
});

/*
 * Con LIVEWIRE REAL (4.4.1 del vendor, llega con filament/filament): el banco
 * estático mide la carga de una página, donde el foco lo pone `autofocus`. El
 * cambio de paso dentro de Livewire es otra cosa —un morph que conserva el nodo
 * del encabezado si su clave no cambia— y ahí el revisor midió que el foco solo
 * llegaba del paso 1 al 2. `tests/navegador/asistente-livewire.{php,py}` recorre
 * el trámite entero con la receta documentada (`wire:click` + `type="button"`):
 * Enter en un campo, error del servidor, Siguiente, Omitir, Anterior y el
 * indicador de vuelta al primer paso, y lee `document.activeElement` en cada uno.
 * Contraprueba hecha sobre una copia de las vistas: sin el `wire:key` del
 * encabezado fallan los seis cambios de paso en los dos navegadores; sin el
 * `x-on:click` del botón por defecto, Enter hace un POST nativo (405).
 *
 * Se SALTA —no se finge— sin `.venv-a11y` o sin Livewire en vendor.
 */
it('con Livewire real en Chromium y Firefox: el foco va al encabezado en cada cambio de paso y Enter avanza sin POST nativo', function () {
    $python = asistentePythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: el banco con Livewire real se salta, no se da por hecho.');
    }

    if (! class_exists(Livewire::class)) {
        $this->markTestSkipped('Sin livewire/livewire en vendor no hay banco con Livewire real.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        'PHP_BINARY='.escapeshellarg(PHP_BINARY).' '
        .escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/asistente-livewire.py').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "El recorrido con Livewire real falló:\n".implode("\n", $lineas));

    // Seis cambios de paso × dos navegadores con el foco en el encabezado nuevo, el
    // error del servidor con el foco en el resumen, y la carga sin robar el foco.
    expect(count(array_filter($lineas, fn (string $l) => str_ends_with($l, 'foco=paso'))))->toBe(12,
        "Cada cambio de paso tiene que dejar el foco en el encabezado del paso nuevo:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_ends_with($l, 'foco=resumen'))))->toBe(2,
        "Con error del servidor el foco es del resumen:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_ends_with($l, 'foco=intacto'))))->toBe(2,
        "Al cargar el primer paso el foco no se mueve:\n".implode("\n", $lineas));
});
