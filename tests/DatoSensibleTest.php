<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DEL DATO PERSONAL ENMASCARADO
|--------------------------------------------------------------------------
|
| `<x-muni::pii>` es lo ÚNICO que la corrección exigida de la ficha
| `ficha-persona` (docs/GAP-ANALYSIS.md) deja entrar al paquete de todo lo que
| el analista pedía. Conviene tener presente por qué, porque explica cada
| aserción de este archivo:
|
| · el juez PROHIBIÓ `<x-muni::ficha-persona>`: «los cuatro sistemas tienen
|   modelos de datos distintos y un componente con props para foto/RUN/
|   domicilio/estado se forkea en el primer sistema que no calce». La ficha
|   queda como demo y patrón documentado, no como componente;
| · y sacó la bitácora del alcance del paquete: `composer.json` solo requiere
|   `illuminate/support` e `illuminate/view` —sin Livewire, sin Eloquent, sin
|   auth, sin activitylog—, así que acá no se puede registrar ningún acceso.
|   «Lo máximo que corresponde aquí es un `<x-muni::pii>` que sirva el campo
|   enmascarado y, al revelarlo, dispare un evento DOM (`muni-pii-revelado`)
|   que el host engancha a su propio registro: el paquete ofrece el gesto, el
|   host prueba el cumplimiento.»
|
| De ahí sale el contrato que esta prueba fija:
|
|  1. NACE ENMASCARADO. Sin JavaScript no hay forma de revelar nada: el valor
|     completo sale con `display:none` en línea y el control nace con `x-cloak`.
|     Un componente de PII que se viera abierto mientras Alpine monta sería
|     exactamente la sobreexposición que el juez objetó.
|  2. EL GESTO EMITE EL EVENTO. `muni-pii-revelado` con `bubbles`, para que el
|     anfitrión lo enganche donde le quede cómodo (el `<dl>`, la página o
|     `window`) y lo mande a SU bitácora.
|  3. NINGÚN TEXTO DEL ANFITRIÓN ENTRA EN UNA EXPRESIÓN DE ALPINE. El detalle
|     del evento se lee de `data-*` del propio nodo. Es el defecto que tumbó la
|     página entera en `file-dropzone`: un apóstrofo escapado a `&#039;`
|     descuadra las comillas y un rótulo venido de configuración se ejecuta.
|  4. MODO DIFERIDO. Si el anfitrión NO manda el valor, el componente no lo
|     inventa: emite el evento igual y queda a la espera. Es la única forma de
|     cumplir minimización de verdad (Ley 21.719), porque un valor oculto con
|     CSS sigue estando en el HTML que se sirvió.
|  5. IDS DERIVADOS DE UNA PROP, jamás de `uniqid()` (DESIGN §10).
|  6. IMPRESIÓN. El control no se imprime: en papel un botón «Mostrar» es tinta
|     y ruido. Lo que esté en pantalla es lo que sale.
|
| Todo va con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
| de `toContain()` es OTRA aguja, no un mensaje, y la aserción se desactivaría
| sin avisar.
|
| Lo que un test de Blade no puede ver —el evento disparándose de verdad, el
| foco, el contraste contra el fondo efectivo, el `@media print` computado y el
| comportamiento sin JS— se mide en Chromium y Firefox al final del archivo.
*/

/** El HTML servido del componente. */
function piiHtml(string $atributos = '', string $contenido = '', array $datos = []): string
{
    return Blade::render("<x-muni::pii {$atributos}>{$contenido}</x-muni::pii>", $datos);
}

/** El fuente crudo del componente, o cadena vacía si todavía no existe. */
function piiFuente(): string
{
    $ruta = __DIR__.'/../resources/views/components/pii.blade.php';

    return file_exists($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * El fuente sin comentarios. La prosa nombra a propósito lo que el componente
 * NO hace («uniqid», «wire:show», «Auth»), y grepear el fuente entero daría
 * falsos positivos contra el propio comentario.
 */
function piiFuenteSinComentarios(): string
{
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', piiFuente());

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El CSS que el componente lleva consigo, ya sin comentarios. */
function piiCss(): string
{
    preg_match_all('#<style>(.*?)</style>#s', piiFuenteSinComentarios(), $bloques);

    return implode("\n", $bloques[1]);
}

/**
 * El cuerpo del `@media print` del componente. Se recorta contando llaves
 * porque el bloque anida reglas y un `[^}]*` cortaría en la primera.
 */
function piiBloquePrint(string $css): string
{
    $inicio = mb_strpos($css, '@media print');

    if ($inicio === false) {
        return '';
    }

    $apertura = mb_strpos($css, '{', $inicio);
    $nivel = 0;

    for ($i = $apertura, $n = mb_strlen($css); $i < $n; $i++) {
        $caracter = mb_substr($css, $i, 1);
        $nivel += $caracter === '{' ? 1 : ($caracter === '}' ? -1 : 0);

        if ($nivel === 0) {
            return mb_substr($css, $apertura, $i - $apertura + 1);
        }
    }

    return mb_substr($css, $apertura);
}

/** Los valores de todos los atributos de Alpine del HTML dado, ya decodificados. */
function piiExpresionesAlpine(string $html): array
{
    preg_match_all('/\s(?:x-[a-z:.-]+|:[a-z-]+|@[a-z:.-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

// ---------------------------------------------------------------------------
// 1. Nace enmascarado
// ---------------------------------------------------------------------------

it('sirve la máscara y deja el valor completo oculto por CSS en línea', function () {
    $html = piiHtml('label="RUT" masked="12.***.***-9"', '12.345.678-9');

    expect(str_contains($html, '12.***.***-9'))->toBeTrue('No sirve la máscara que mandó el anfitrión.');

    // El valor real existe en el DOM (lo puso el anfitrión), pero NO se ve: el
    // `display:none` va en línea para que valga desde el primer pixel pintado,
    // antes de que Alpine monte. Sin eso, cada ficha del mesón parpadea con el
    // RUT completo a la vista en cada render.
    expect((bool) preg_match('/<span[^>]*class="[^"]*muni-pii__real[^"]*"[^>]*style="[^"]*display:none/', $html))->toBeTrue(
        'El valor completo no nace con `display:none` en línea: se ve mientras Alpine monta, y sin JS se ve siempre.'
    );
    expect((bool) preg_match('/muni-pii__real[^>]*>\s*12\.345\.678-9/', $html))->toBeTrue(
        'El valor del slot no queda dentro del contenedor del valor real.'
    );
});

it('sin máscara del anfitrión no inventa una: pone puntos y dice en texto que el dato está oculto', function () {
    $html = piiHtml('label="Diagnóstico"', 'Trastorno del espectro autista');

    // Enmascarar un diagnóstico NO es lo mismo que enmascarar un RUT, y el
    // paquete no sabe la diferencia: el anfitrión manda el string ya redactado
    // (minimización de la Ley 21.719). Sin máscara, puntos.
    expect(str_contains($html, 'Trastorno del espectro autista'))->toBeTrue('El valor del slot no llega al HTML.');
    expect((bool) preg_match('/aria-hidden="true"[^>]*>[•]+</u', $html) || str_contains($html, '•'))->toBeTrue(
        'Sin máscara no emite el relleno de puntos.'
    );
    expect((bool) preg_match('/class="muni-sr"[^>]*>\s*Dato oculto/', $html))->toBeTrue(
        'Los puntos no llevan texto para el lector de pantalla: quien no ve la pantalla no sabe que hay un dato oculto.'
    );
});

it('el control nace oculto con x-cloak y el componente declara la regla que lo oculta', function () {
    $html = piiHtml('label="RUT" masked="12.***.***-9"', '12.345.678-9');

    expect((bool) preg_match('/<button[^>]*x-cloak/', $html))->toBeTrue(
        'El botón no nace con x-cloak: sin Alpine queda un control que no revela nada, y el lector lo anuncia igual.'
    );

    // DESIGN §7: la regla viaja con el componente. Dentro de un panel Filament
    // solo se carga muni-ui-filament.css y una clase declarada nada más en
    // muni-ui.css no existiría ahí.
    expect((bool) preg_match('/\[x-cloak\]\s*\{[^}]*display\s*:\s*none/', piiCss()))->toBeTrue(
        'El componente no declara la regla de [x-cloak] en su propio bloque de estilos (DESIGN §7).'
    );
});

it('el botón es type=button y no envía el formulario de la ficha', function () {
    $html = piiHtml('label="RUT" masked="12.***.***-9"', '12.345.678-9');

    expect((bool) preg_match('/<button[^>]*type="button"/', $html))->toBeTrue(
        'El control no es type="button": dentro de un <form> un botón sin tipo es submit y revelar el RUT enviaría la ficha.'
    );
});

// ---------------------------------------------------------------------------
// 2. El gesto emite el evento
// ---------------------------------------------------------------------------

it('dispara muni-pii-revelado con bubbles para que el anfitrión lo lleve a SU bitácora', function () {
    $fuente = piiFuenteSinComentarios();

    expect(str_contains($fuente, 'muni-pii-revelado'))->toBeTrue(
        'No emite `muni-pii-revelado`: sin el evento el anfitrión no puede registrar el acceso y el componente '
        .'queda siendo justo lo que el juez objetó, una pantalla que muestra PII sin trazabilidad.'
    );
    expect((bool) preg_match('/bubbles\s*:\s*true/', $fuente))->toBeTrue(
        'El evento no burbujea: el anfitrión tendría que engancharlo nodo por nodo en vez de en la página.'
    );
});

it('el detalle del evento sale de data-* del propio nodo, no de una interpolación', function () {
    $html = piiHtml('label="RUT" name="rut" masked="12.***.***-9"', '12.345.678-9');

    expect((bool) preg_match('/data-muni-pii-campo="rut"/', $html))->toBeTrue('El campo no viaja como data-*.');
    expect((bool) preg_match('/data-muni-pii-etiqueta="RUT"/', $html))->toBeTrue('La etiqueta no viaja como data-*.');

    $fuente = piiFuenteSinComentarios();
    expect(str_contains($fuente, 'dataset.muniPiiCampo'))->toBeTrue(
        'El Alpine no lee el campo del dataset: entonces el texto del anfitrión está dentro de la expresión.'
    );
});

it('ningún texto del anfitrión entra en una expresión de Alpine', function () {
    // El defecto de file-dropzone: un apóstrofo escapado a &#039; descuadra las
    // comillas de la expresión y el Alpine de la PÁGINA ENTERA muere; peor, un
    // rótulo venido de configuración con `'+fetch(...)+'` se ejecuta.
    $veneno = "Diagnóstico de O'Higgins'; window.__roto = 1; //";

    $html = piiHtml('label="'.e($veneno).'" name="dx" masked="***"', 'secreto');

    foreach (piiExpresionesAlpine($html) as $expresion) {
        expect(str_contains($expresion, "O'Higgins"))->toBeFalse(
            "Texto del anfitrión dentro de una expresión de Alpine: {$expresion}"
        );
        expect(str_contains($expresion, 'window.__roto'))->toBeFalse(
            "Se inyectó código del anfitrión en una expresión de Alpine: {$expresion}"
        );
    }

    // Y el x-data solo lleva literales del componente.
    preg_match('/x-data="([^"]*)"/', $html, $m);
    $datos = html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5);

    expect((bool) preg_match('/revelado\s*:\s*(true|false)/', $datos))->toBeTrue(
        'El estado inicial del x-data no es un literal booleano del componente.'
    );
});

it('escapa la etiqueta y la máscara que le pasa el anfitrión', function () {
    $html = piiHtml('label="<img src=x onerror=alert(1)>" masked="<b>ups</b>"', 'valor');

    expect(str_contains($html, '<img src=x'))->toBeFalse('La etiqueta sale sin escapar: XSS almacenado en la ficha.');
    expect(str_contains($html, '<b>ups</b>'))->toBeFalse('La máscara sale sin escapar: XSS almacenado en la ficha.');
});

// ---------------------------------------------------------------------------
// 3. El valor por slot, y el modo diferido
// ---------------------------------------------------------------------------

it('el valor va por slot y admite marcado: un badge, un enlace al expediente', function () {
    $html = piiHtml('label="Teléfono" masked="+56 9 •••• ••12"',
        '<a href="tel:+56912345612">+56 9 1234 5612</a>');

    expect(str_contains($html, 'tel:+56912345612'))->toBeTrue(
        'El slot no admite marcado: el primer caso real (un enlace, un badge, un botón de copiar) vuelve al <span> a mano.'
    );

    // Y no hay una prop `value` que reabra la puerta a un string pelado ni,
    // peor, a un modelo completo (Ley 21.719: acá llegan strings ya redactados).
    foreach (['value', 'valor', 'model', 'record'] as $prohibida) {
        expect((bool) preg_match("/'".$prohibida."'\s*=>/", piiFuenteSinComentarios()))->toBeFalse(
            "El componente declara la prop «{$prohibida}»: el valor va por slot, que es lo que deja componer."
        );
    }
});

it('sin valor no inventa nada: queda en modo diferido y el anfitrión lo sirve después', function () {
    $html = piiHtml('label="Domicilio" masked="•••••" name="domicilio"');

    // El único modo que cumple minimización de verdad: si el valor no se sirvió,
    // no está en el HTML y no hay CSS que se pueda desactivar para verlo.
    expect(str_contains($html, 'muni-pii__real'))->toBeFalse(
        'Sin slot emite igual el contenedor del valor: el modo diferido tiene que no servir NADA.'
    );
    expect((bool) preg_match('/tieneValor\s*:\s*false/', $html))->toBeTrue(
        'El x-data no sabe que no tiene valor: el botón revelaría un hueco en vez de pedirlo al anfitrión.'
    );
    expect(str_contains($html, 'muni-pii__pendiente'))->toBeTrue(
        'No hay estado «solicitando»: tras el clic la pantalla no dice nada y el funcionario vuelve a pulsar.'
    );
});

it('con `revealed` nace abierto, que es como vuelve el valor que sirvió el anfitrión', function () {
    $abierto = piiHtml('label="RUT" masked="12.***.***-9" revealed', '12.345.678-9');

    expect((bool) preg_match('/<button[^>]*aria-pressed="true"/', $abierto))->toBeTrue(
        'Con `revealed` el botón no nace pulsado: tras el round-trip del anfitrión el valor se ve y el botón dice lo contrario.'
    );
    expect((bool) preg_match('/muni-pii__real[^>]*style="[^"]*display:none/', $abierto))->toBeFalse(
        'Con `revealed` el valor sigue oculto en línea: el anfitrión sirvió el dato y la pantalla no lo muestra.'
    );

    // Sin slot, `revealed` no puede abrir nada: no hay qué mostrar.
    $vacio = piiHtml('label="RUT" masked="12.***.***-9" revealed');
    expect((bool) preg_match('/<button[^>]*aria-pressed="true"/', $vacio))->toBeFalse(
        '`revealed` sin valor deja el botón pulsado sobre la nada.'
    );
});

// ---------------------------------------------------------------------------
// 4. Accesibilidad del control
// ---------------------------------------------------------------------------

it('el nombre accesible del botón dice QUÉ dato revela, no solo «Mostrar»', function () {
    $html = piiHtml('label="RUT del titular" masked="12.***.***-9"', '12.345.678-9');

    // Una ficha trae RUT, domicilio, teléfono y diagnóstico: cuatro botones
    // llamados «Mostrar» son indistinguibles en la lista de controles del
    // lector de pantalla (WCAG 2.2 AA 2.4.6).
    expect((bool) preg_match('/<button.*?RUT del titular.*?<\/button>/s', $html))->toBeTrue(
        'El botón no nombra el dato que revela: cuatro «Mostrar» iguales en la misma ficha.'
    );
    expect((bool) preg_match('/<button.*?class="muni-sr".*?<\/button>/s', $html))->toBeTrue(
        'La etiqueta del dato no va en un texto solo para el lector: en pantalla el botón se estira y rompe la fila.'
    );
});

it('el botón apunta al valor con aria-controls y anuncia su estado con aria-pressed', function () {
    $html = piiHtml('label="RUT" name="rut" masked="12.***.***-9"', '12.345.678-9');

    preg_match('/aria-controls="([^"]+)"/', $html, $controla);
    expect($controla[1] ?? '')->not->toBe('', 'El botón no declara aria-controls.');
    expect(str_contains($html, 'id="'.($controla[1] ?? '').'"'))->toBeTrue(
        'El aria-controls apunta a un id que no existe en el HTML.'
    );
    expect((bool) preg_match('/<button[^>]*aria-pressed="false"/', $html))->toBeTrue(
        'El botón no emite aria-pressed estático: hasta que Alpine monte, el lector no sabe si el dato está a la vista.'
    );
});

it('el valor vive en una región viva educada: al revelarlo, el lector lo dice', function () {
    $html = piiHtml('label="RUT" masked="12.***.***-9"', '12.345.678-9');

    expect((bool) preg_match('/aria-live="polite"/', $html))->toBeTrue(
        'El contenedor del valor no es región viva: quien usa lector pulsa «Mostrar» y no se entera de nada.'
    );
});

it('Escape va en el elemento y nunca en window', function () {
    $fuente = piiFuenteSinComentarios();

    expect(str_contains($fuente, 'keydown.escape.window'))->toBeFalse(
        'Escape escuchado en window: una ficha con seis datos se queda con seis oyentes globales que se pisan '
        .'con el modal y el drawer que estén abiertos.'
    );
    expect(str_contains($fuente, 'keydown.escape'))->toBeTrue(
        'Escape no cierra el dato revelado: en el mesón es el gesto para taparlo rápido.'
    );
    expect(str_contains($fuente, 'stopPropagation'))->toBeTrue(
        'Escape no detiene la propagación: taparía el dato y además cerraría el drawer que lo contiene.'
    );
});

// ---------------------------------------------------------------------------
// 5. Ids, y lo que el paquete no hace
// ---------------------------------------------------------------------------

it('el id sale de una prop y nunca de uniqid, y el del consumidor manda', function () {
    $fuente = piiFuenteSinComentarios();

    expect(str_contains($fuente, 'uniqid'))->toBeFalse('Usa uniqid(): el id cambia en cada render (DESIGN §10).');

    $a = piiHtml('label="RUT" name="rut" masked="12.***.***-9"', 'x');
    $b = piiHtml('label="RUT" name="rut" masked="12.***.***-9"', 'x');
    preg_match('/id="(muni-pii[^"]*)"/', $a, $ma);
    preg_match('/id="(muni-pii[^"]*)"/', $b, $mb);

    expect($ma[1] ?? 'a')->toBe($mb[1] ?? 'b', 'El id no es determinista entre renders.');

    $otro = piiHtml('label="Domicilio" name="domicilio" masked="•••"', 'x');
    preg_match('/id="(muni-pii[^"]*)"/', $otro, $mo);
    expect($mo[1] ?? '')->not->toBe($ma[1] ?? '', 'Dos datos distintos de la misma ficha comparten id.');

    $propio = piiHtml('label="RUT" id="ficha-4821-rut" masked="12.***.***-9"', 'x');
    expect(str_contains($propio, 'id="ficha-4821-rut"'))->toBeTrue('El id del consumidor no manda.');

    // Una etiqueta con acentos y espacios tiene que dar un id válido igual.
    $acentos = piiHtml('label="Diagnóstico según CIE-11" masked="•••"', 'x');
    preg_match('/id="([^"]+)"/', $acentos, $mc);
    expect((bool) preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $mc[1] ?? ''))->toBeTrue(
        'El id derivado de una etiqueta con acentos no es un id válido: '.($mc[1] ?? '(ninguno)')
    );
});

it('no mira el request, ni la sesión, ni los permisos, ni usa directivas de un major de Livewire', function () {
    $fuente = piiFuenteSinComentarios();

    foreach (['request(', 'Auth::', 'auth()', 'Gate::', 'can(', 'session('] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El componente usa «{$prohibido}»: el paquete recibe todo ya resuelto por el anfitrión."
        );
    }

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El componente usa «{$prohibido}»: no existe en Livewire 3 y personas-graneros sigue ahí."
        );
    }

    expect(str_contains($fuente, '{!!'))->toBeFalse(
        'Hay un `{!! !!}` en un componente que sirve PII: el escapado del valor es del anfitrión, acá no se abre esa puerta.'
    );
});

// ---------------------------------------------------------------------------
// 6. Estilo: tokens, foco e impresión
// ---------------------------------------------------------------------------

it('no escribe un solo color literal y todo token --muni-* que lee existe en las dos hojas', function () {
    $fuente = piiFuenteSinComentarios();

    expect((bool) preg_match('/(?<!&)#[0-9a-fA-F]{3,8}\b/', str_replace('#767676', '', $fuente)))->toBeFalse(
        'Hay un color literal: todo color sale de un token --muni-*, salvo el respaldo de foco #767676.'
    );
    expect((bool) preg_match('/\b(rgba?|hsla?)\s*\(/i', $fuente))->toBeFalse('Los colores salen de tokens, no de rgb()/hsl().');

    expect((bool) preg_match('/--muni-[a-z0-9-]+\s*:/i', $fuente))->toBeFalse(
        'El componente DECLARA un --muni-*: los tokens del sistema se definen en las dos hojas, no en un componente. '
        .'Una variable propia del componente va con prefijo propio.'
    );

    preg_match_all('/var\(\s*(--muni-[a-z0-9-]+)/i', $fuente, $usados);

    $hojas = file_get_contents(__DIR__.'/../resources/css/muni-ui.css')
        .file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');

    foreach (array_unique($usados[1]) as $token) {
        expect(substr_count($hojas, $token.':') >= 2)->toBeTrue(
            "El token {$token} no está declarado en las DOS hojas: dentro de un panel Filament sería un var() vacío "
            .'y la declaración entera se descarta, sin un solo error en consola (DESIGN §7).'
        );
    }
});

it('el foco del control es un outline de 3px, nunca una sombra sola', function () {
    // El botón lo pone <x-muni::button>, que ya declara el outline. Lo que este
    // componente no puede hacer es apagarlo ni sustituirlo por una box-shadow,
    // que dentro de Filament se pierde (DESIGN §5).
    $css = piiCss();

    expect((bool) preg_match('/outline\s*:\s*(none|0)\b/i', $css))->toBeFalse('El componente apaga el contorno del foco.');

    $html = piiHtml('label="RUT" masked="12.***.***-9"', '12.345.678-9');
    expect((bool) preg_match('/<button[^>]*class="[^"]*muni-btn/', $html))->toBeTrue(
        'El control no es <x-muni::button>: se pierde el outline de 3px que el paquete ya resolvió una vez.'
    );
});

it('el blanco de pulsación del control llega a 24px por el lado corto', function () {
    $html = piiHtml('label="RUT" masked="12.***.***-9"', '12.345.678-9');

    preg_match('/<button[^>]*style="([^"]*)"/', $html, $estilo);

    expect((bool) preg_match('/min-height\s*:\s*(\d+)px/', $estilo[1] ?? '', $alto))->toBeTrue(
        'El botón no fija una altura mínima: WCAG 2.2 AA 2.5.8 pide 24×24 y el tamaño `sm` queda al filo.'
    );
    expect((int) ($alto[1] ?? 0) >= 24)->toBeTrue('El blanco de pulsación del control queda bajo 24px.');
});

it('al imprimir desaparece el control y queda lo que está en pantalla', function () {
    $print = piiBloquePrint(piiCss());

    expect($print)->not->toBe('', 'El componente no trae reglas de impresión: en papel sale un botón «Mostrar» que no hace nada.');
    expect((bool) preg_match('/muni-pii__boton[^{]*\{[^}]*display\s*:\s*none/s', $print))->toBeTrue(
        'El control se imprime: en el acta que emite el mesón un botón es tinta y ruido (corrección del juez: al imprimir, sin controles).'
    );
});

it('el componente trae consigo .muni-num y .muni-sr, que no puede heredar de la tabla', function () {
    $css = piiCss();

    // Mismo motivo que description-list: la mono vive hoy dentro del @once de
    // data-table, y una ficha en un drawer sin tabla en pantalla no lo emite
    // nunca. El RUT saldría en sans proporcional justo donde más se compara.
    expect((bool) preg_match('/\.muni-num\s*\{[^}]*--muni-font-mono/', $css))->toBeTrue(
        'El componente no declara `.muni-num` con la fuente mono en su propio bloque de estilos (DESIGN §7).'
    );
    expect((bool) preg_match('/\.muni-sr\s*\{[^}]*clip-path/', $css))->toBeTrue(
        'El componente no declara `.muni-sr`: el texto para el lector de pantalla se vería en la ficha.'
    );
});

// ---------------------------------------------------------------------------
// La clase del consumidor
// ---------------------------------------------------------------------------

it('la clase del consumidor se suma a la del componente, no la pisa', function () {
    // `class` va dentro del merge: dos atributos `class` en el mismo nodo dejan
    // ganar al primero, y el `.muni-num` con el que el anfitrión pide la mono
    // para el RUT se perdería sin un solo error (DESIGN §8).
    $html = piiHtml('label="RUT" masked="12.***.***-9" class="muni-num"', '12.345.678-9');

    expect(substr_count(explode('>', $html)[0], 'class='))->toBe(1, 'El nodo raíz sale con dos atributos class.');
    expect((bool) preg_match('/class="[^"]*muni-pii[^"]*"/', $html))->toBeTrue('Se perdió la clase del componente.');
    expect((bool) preg_match('/class="[^"]*muni-num[^"]*"/', $html))->toBeTrue('Se perdió la clase del consumidor.');
});

// ---------------------------------------------------------------------------
// Verificación en navegador
// ---------------------------------------------------------------------------

/*
| El banco. Se genera acá y no en un script suelto porque este es el único
| sitio del paquete con Blade arrancado (mismo criterio que `GeneraVitrinaTest`,
| `CampoDeClaveTest` y `ParesDatoValorTest`). Sale a `build/dato-sensible/`,
| ignorado por git.
|
| Son CUATRO páginas: dos temas × dos paletas. Dentro de un panel Filament solo
| se carga `muni-ui-filament.css` (DESIGN §7), así que las páginas `panel-*`
| cargan ÚNICAMENTE esa hoja: es donde se vería que un token que solo existe en
| `muni-ui.css` deja la declaración entera descartada, sin error en consola.
|
| El banco es una ficha de vecino de verdad, con los cuatro casos que existen:
| el RUT en mono fuera de toda tabla, el domicilio y el teléfono dentro del
| `<dl>` de `<x-muni::description-list>` (que es la composición que el juez dejó
| como patrón en vez del componente `ficha-persona`), el diagnóstico en modo
| diferido —sin valor servido— y el correo naciendo revelado, que es como vuelve
| la vista después de que el anfitrión atendió el evento y registró el acceso.
|
| Alpine sale de node_modules, nunca de un CDN: el banco se abre sin red. Y la
| página trae un oyente de `muni-pii-revelado` en `document` —el papel del
| anfitrión— para que el navegador pueda comprobar que el evento burbujea de
| verdad y con qué detalle llega.
*/

/** Alpine 3 (núcleo) tal como lo trae node_modules, o null si no se corrió `npm install`. */
function piiAlpineParaBanco(): ?string
{
    $ruta = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    return is_file($ruta) ? (string) file_get_contents($ruta) : null;
}

/** La ficha del banco, una sola vez: las cuatro páginas sirven el mismo DOM. */
function piiFichaDelBanco(): string
{
    return Blade::render(
        '<h1 id="titulo">Ana Soto Miranda</h1>'
        // El RUT va FUERA del <dl> a propósito: así el bloque de estilos de
        // una sola emisión del componente cae en el cuerpo y no dentro de un
        // <dd>, y de paso se mide la mono en una página sin una sola tabla.
        .'<p class="banco-identidad">RUT <x-muni::pii label="RUT" name="rut" masked="12.***.***-9" class="muni-num">12.345.678-9</x-muni::pii></p>'
        .'<x-muni::description-list label="Datos de contacto">'
        .'<x-muni::description-item label="Domicilio">'
        .'<x-muni::pii label="Domicilio" name="domicilio" masked="Manuel Rodríguez ***, Graneros">'
        .'Manuel Rodríguez 545, Graneros</x-muni::pii></x-muni::description-item>'
        .'<x-muni::description-item label="Teléfono">'
        .'<x-muni::pii label="Teléfono" name="telefono" masked="+56 9 •••• ••12">'
        .'<a href="tel:+56912345612">+56 9 1234 5612</a></x-muni::pii></x-muni::description-item>'
        // Modo diferido: el diagnóstico NO se sirve. El anfitrión decide si
        // este funcionario puede verlo cuando le llegue el evento.
        .'<x-muni::description-item label="Diagnóstico">'
        .'<x-muni::pii label="Diagnóstico" name="diagnostico" /></x-muni::description-item>'
        // Nace revelado: es el estado con el que vuelve la vista servida por el
        // anfitrión después de registrar el acceso.
        .'<x-muni::description-item label="Correo">'
        .'<x-muni::pii label="Correo" name="correo" masked="a****@example.cl" revealed>'
        .'ana.soto@example.cl</x-muni::pii></x-muni::description-item>'
        .'</x-muni::description-list>'
    );
}

it('genera el banco de navegador en build/dato-sensible/: la ficha del vecino con los cuatro casos', function () {
    $dir = __DIR__.'/../build/dato-sensible';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ficha = piiFichaDelBanco();
    $alpine = piiAlpineParaBanco();

    expect($alpine)->not->toBeNull('El banco saldría sin Alpine: corre `npm install`; sin él no se puede medir el gesto.');

    $paginas = [
        'claro' => ['light', cssMuniUi(), false],
        'oscuro' => ['dark', cssMuniUi(), false],
        'panel-claro' => ['light', cssMuniUiFilament(), true],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel]) {
        $oscuro = $tema === 'dark';

        /* El armazón no aporta ni un color: fondo y texto salen de los mismos
           tokens que lee el componente. */
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'h1{font-size:20px;margin:0 0 12px}.banco-identidad{margin:0 0 20px;font-size:14px}';

        /* El anfitrión de mentira: engancha el evento en `document` y anota lo
           que llega. Es lo que en un sistema real sería la bitácora. */
        $oyente = '<script data-banco="oyente">window.__muniPii=[];'
            .'document.addEventListener("muni-pii-revelado",function(e){window.__muniPii.push(e.detail);});'
            .'</script>';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<section class="fi-section">'.$ficha.'</section></main></div>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$ficha.'</main>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').'>'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco x-muni::pii — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.$oyente.'<script data-banco="alpine-core">'.$alpine.'</script></body></html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);

        expect(substr_count($pagina, 'data-muni-pii-campo='))->toBe(5, 'El banco '.$nombre.' no trae los cinco datos.');
        expect(str_contains($pagina, 'data-banco="alpine-core"'))->toBeTrue('El banco '.$nombre.' salió sin Alpine.');
        expect(str_contains($pagina, '<table'))->toBeFalse(
            'El banco trae una tabla: con una en la página `.muni-num` podría venir del bloque de data-table '
            .'y la medición de la mono no probaría que la clase viaja con este componente.'
        );
    }

    /* La entrada que este componente entrega para `ejemplosDeVitrina()` de
       tests/GeneraVitrinaTest.php —el archivo compartido que un agente no
       toca—, renderizada tal cual se entrega: al menos queda fijo que la línea
       es Blade válido y que pinta el gesto completo. */
    $vitrina = Blade::render(
        '<x-muni::pii label="RUT" name="rut" masked="12.***.***-9" class="muni-num">12.345.678-9</x-muni::pii> '
        .'<x-muni::pii label="Diagnóstico" name="diagnostico" /> '
        .'<x-muni::pii label="Correo" name="correo" masked="a****@example.cl" revealed>ana.soto@example.cl</x-muni::pii>'
    );

    expect(substr_count($vitrina, 'data-muni-pii-campo='))->toBe(3,
        'La entrada de vitrina no pinta los tres casos: servido, diferido y nacido revelado.');
    expect(str_contains($vitrina, 'Mostrar <span class="muni-sr">RUT</span>'))->toBeTrue(
        'La entrada de vitrina no deja el control medible por la reja.'
    );
    expect(str_contains($vitrina, 'muni-num'))->toBeTrue(
        'La entrada de vitrina no incluye un valor mono: la reja no mediría la firma del sistema.'
    );
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function piiPythonDeLaReja(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que un test de Blade no puede ver, medido en Chromium Y Firefox sobre las
 * cuatro páginas: que el evento salga y burbuje hasta `document` con el detalle
 * correcto, que el teclado llegue al control y lo accione, que Escape tape el
 * dato, que el modo diferido no sirva nada, que el foco se vea como un outline
 * de 3 px, el contraste contra el fondo efectivo, el `@media print` COMPUTADO,
 * el movimiento apagado con la preferencia y que SIN JavaScript el control no
 * se ofrezca y el dato siga tapado.
 * Se SALTA —no se finge— cuando no está `.venv-a11y`, que en CI no se instala.
 */
it('en Chromium y Firefox: el evento burbujea, el teclado revela y tapa, sin JS no se ofrece y el control no se imprime', function () {
    $python = piiPythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/dato-sensible.py').' '
        .escapeshellarg(__DIR__.'/../build/dato-sensible').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de <x-muni::pii> falló:\n".implode("\n", $lineas));

    // Cuatro páginas × dos navegadores: los ocho recorridos tienen que haber
    // registrado el evento en `document`. La cifra SALE DE LA MEDICIÓN, no de
    // un literal impreso a mano.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'eventos=ok'))))->toBe(8,
        "Los ocho recorridos tienen que registrar el evento en el oyente del anfitrión:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'sinJS=tapado'))))->toBe(8,
        "Los ocho recorridos tienen que dejar el dato tapado y el control fuera sin JavaScript:\n".implode("\n", $lineas));

    /* El movimiento apagado con la preferencia, medido y no supuesto. Son al
       menos CUATRO y no ocho a propósito: las dos páginas que cargan
       `muni-ui.css` en los dos navegadores dan 0 s, y las dos de panel NO,
       porque `muni-ui-filament.css` declara `--muni-dur:160ms` y no trae el
       bloque `prefers-reduced-motion` que sí trae la otra hoja. Eso no es de
       este componente —ningún componente puede redefinir un token del sistema,
       y las hojas son archivo compartido— y afecta a TODO el paquete dentro de
       un panel. Queda medido acá y el umbral es «al menos 4» para que el día
       que la hoja del panel se arregle esta prueba no se ponga roja por mejorar. */
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'transición(reduce)=0s'))) >= 4)->toBeTrue(
        "Con prefers-reduced-motion la transición del control tiene que computar 0 s donde el token la apaga:\n".implode("\n", $lineas));
});
