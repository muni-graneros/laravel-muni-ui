<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DEL CIERRE DE UN TRÁMITE
|--------------------------------------------------------------------------
|
| `<x-muni::pantalla-resultado>` es la ficha `pantalla-resultado` de
| docs/GAP-ANALYSIS.md: la pantalla que hoy no existe y que hace que el
| funcionario —o el vecino— sepa que el trámite terminó, con qué folio quedó y
| por dónde se sale. Hasta ahora el único cierre del paquete era el paso
| «Enviado» pegado al final de demo/wizard.html, que no es reutilizable.
|
| Lo que esta prueba vigila, y por qué cada cosa:
|
| · **Un solo <h1>, enfocable y enfocado.** Al cargar, el foco va al título
|   (`tabindex="-1"`), que es lo que hace que un lector de pantalla lea el
|   resultado sin que nadie tenga que buscarlo. Es el punto 1 de las teclas
|   obligatorias de la ficha (WCAG 2.2 AA 2.4.3 y 4.1.3).
| · **El folio es texto, en mono tabular** (DESIGN §9: las cifras y los RUT van
|   con `.muni-num`), y se copia con un BOTÓN REAL, no con un icono sobre un
|   <div>. La confirmación es TEXTO —«Folio copiado»— dentro de una región viva
|   que nace vacía: una región que nace con su contenido no se anuncia.
| · **Un solo camino de salida, y es el último.** La ficha lo dice con esas
|   palabras. Si la salida deja de ser lo último enfocable, el vecino mayor tiene
|   que tabular de vuelta para encontrarla.
| · **Nada del anfitrión entra en una expresión de Alpine.** Un folio con
|   apóstrofo dentro de una cadena de comillas simples tumba el Alpine de la
|   página entera (ya pasó en `file-dropzone`). El folio viaja por `@js()`.
| · **Ley 21.719, minimización.** El componente recibe strings ya redactados por
|   el anfitrión; no consulta `request()`, ni `Auth`, ni permisos, y no vuelca un
|   registro completo al DOM. La pantalla de cierre es la que más se comparte por
|   pantalla y por papel.
| · **Riesgo de la ficha:** si el folio sirve para consultar el estado sin
|   autenticación, no puede ser secuencial adivinable. Eso lo decide el
|   anfitrión —el componente solo lo pinta—, y por eso se comprueba que el
|   componente no lo genere ni lo derive de nada suyo.
|
| Todo va con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
| de `toContain()` es otra aguja, no un mensaje, y la aserción se apaga sin
| avisar.
*/

/** El HTML servido del componente. */
function resultadoHtml(string $atributos = '', string $contenido = ''): string
{
    return Blade::render("<x-muni::pantalla-resultado {$atributos}>{$contenido}</x-muni::pantalla-resultado>");
}

/** El ejemplo completo, que es el que se mide en la vitrina. */
function resultadoHtmlCompleto(): string
{
    return resultadoHtml(
        'title="Su solicitud fue recibida" '
        .'message="La Dirección de Tránsito la revisará en un plazo de 5 días hábiles." '
        .'folio="2026-04871" '
        .'print-href="/comprobante/2026-04871" '
        .'exit-href="/" exit-label="Volver al inicio"'
    );
}

/**
 * El fuente crudo del componente. Falla si no existe: si devolviera cadena
 * vacía, las pruebas que buscan lo que el componente NO debe tener darían verde
 * sin componente.
 */
function resultadoFuente(): string
{
    $ruta = __DIR__.'/../resources/views/components/pantalla-resultado.blade.php';

    expect(file_exists($ruta))->toBeTrue('No existe resources/views/components/pantalla-resultado.blade.php.');

    return (string) file_get_contents($ruta);
}

/**
 * El fuente sin comentarios: la prosa explicativa nombra a propósito lo que el
 * componente NO hace («uniqid», «wire:show», «request()»), y buscarlo sobre el
 * fuente entero daría falsos positivos contra el propio comentario.
 */
function resultadoFuenteSinComentarios(): string
{
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', resultadoFuente());

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El CSS que el componente lleva consigo, sin comentarios. */
function resultadoCss(): string
{
    preg_match_all('#<style>(.*?)</style>#s', resultadoFuente(), $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

it('rinde un solo h1, enfocable, y el foco va ahí al cargar', function () {
    $html = resultadoHtmlCompleto();

    expect(substr_count($html, '<h1'))->toBe(1,
        'La pantalla de cierre tiene que dar exactamente un <h1>: es el resultado del trámite.'
    );

    expect((bool) preg_match('/<h1[^>]*tabindex="-1"/', $html))->toBeTrue(
        'El <h1> no es enfocable: sin tabindex="-1" el foco no puede ir al resultado y el lector '.
        'de pantalla no lo anuncia al cargar (ficha, tecla obligatoria 1).'
    );

    expect((bool) preg_match('/<h1[^>]*\bautofocus\b/', $html))->toBeTrue(
        'El <h1> no lleva autofocus: sin él, y sin JS, el foco se queda en el <body> y el '.
        'resultado no se anuncia.'
    );

    expect(str_contains($html, 'Su solicitud fue recibida'))->toBeTrue(
        'El título del cierre no llegó al HTML.'
    );
});

it('el foco del título no se le roba a nadie que ya estuviera trabajando', function () {
    $fuente = resultadoFuenteSinComentarios();

    expect(str_contains($fuente, 'document.activeElement'))->toBeTrue(
        'El componente enfoca el título sin mirar si alguien ya tenía el foco. Dentro de una '.
        'página que se repinta por Livewire eso arranca el foco de donde estaba el funcionario.'
    );
});

it('el folio va en mono tabular y se copia con un botón real', function () {
    $html = resultadoHtmlCompleto();

    expect((bool) preg_match('/class="[^"]*muni-num[^"]*"[^>]*>\s*2026-04871/', $html))->toBeTrue(
        'El folio no sale con .muni-num. DESIGN §9: las cifras y los folios van en mono tabular.'
    );

    expect((bool) preg_match('/<button[^>]*type="button"[^>]*>(?:(?!<\/button>).)*Copiar folio/s', $html))->toBeTrue(
        'No hay un <button type="button"> con el texto «Copiar folio». Un icono sobre un <div> no '.
        'es un control: no lo alcanza el teclado ni lo anuncia el lector de pantalla.'
    );
});

it('confirma el copiado en TEXTO, en una región viva que nace vacía', function () {
    $html = resultadoHtmlCompleto();

    expect((bool) preg_match('/role="status"[^>]*aria-live="polite"|aria-live="polite"[^>]*role="status"/', $html))->toBeTrue(
        'No hay región viva para el resultado del copiado: la confirmación no se anuncia.'
    );

    // Nace vacía a propósito: una región que aparece junto a su texto no se
    // anuncia en NVDA, JAWS ni VoiceOver.
    expect((bool) preg_match('/role="status"[^>]*>\s*<\/[a-z]+>|<[a-z]+[^>]*aria-live="polite"[^>]*>\s*<\/[a-z]+>/', $html))->toBeTrue(
        'La región viva nace con contenido: así no se anuncia el cambio. Tiene que nacer vacía y '.
        'llenarse al copiar.'
    );

    expect(str_contains(resultadoFuenteSinComentarios(), 'Folio copiado'))->toBeTrue(
        'El componente no confirma el copiado con palabras. La ficha lo pide explícito: «botón '.
        'real que confirma en texto, no solo con un icono verde».'
    );
});

it('cuando el portapapeles no está disponible dice qué hacer, y no miente', function () {
    $fuente = resultadoFuenteSinComentarios();

    expect(str_contains($fuente, 'navigator.clipboard'))->toBeTrue(
        'El componente no usa el portapapeles del navegador.'
    );

    expect(str_contains($fuente, 'execCommand'))->toBeTrue(
        'No hay alternativa al portapapeles moderno. `navigator.clipboard` no existe fuera de un '.
        'contexto seguro, y la intranet municipal sirve por http: ahí el botón no haría nada.'
    );

    expect((bool) preg_match('/No se pudo copiar/i', $fuente))->toBeTrue(
        'Si el copiado falla, el componente no lo dice: el vecino se va creyendo que tiene el folio.'
    );
});

it('imprime el comprobante solo si el anfitrión da el enlace', function () {
    $con = resultadoHtmlCompleto();

    expect((bool) preg_match('/<a[^>]*href="\/comprobante\/2026-04871"[^>]*>(?:(?!<\/a>).)*Imprimir/s', $con))->toBeTrue(
        'Con `print-href` no aparece el enlace a imprimir el comprobante (ficha, tecla obligatoria 3).'
    );

    $sin = resultadoHtml('title="Su solicitud fue recibida" folio="2026-04871" exit-href="/"');

    expect(str_contains($sin, 'Imprimir'))->toBeFalse(
        'Sin `print-href` el componente inventa un enlace a imprimir. Un botón que no lleva a '.
        'ninguna parte es peor que no ofrecerlo.'
    );
});

it('la salida es el ÚLTIMO elemento enfocable de la pantalla', function () {
    $html = resultadoHtmlCompleto();

    preg_match_all('/<(?:a\s[^>]*href=|button\b)/i', $html, $m, PREG_OFFSET_CAPTURE);

    expect(count($m[0]) >= 3)->toBeTrue(
        'Se esperaban al menos tres paradas de tabulación: copiar folio, imprimir y la salida.'
    );

    $ultimo = end($m[0])[1];

    expect(str_contains(substr($html, $ultimo, 400), 'href="/"'))->toBeTrue(
        'La salida no es lo último enfocable de la pantalla. La ficha lo pide con esas palabras: '.
        '«el último elemento es el único camino de salida».'
    );
});

it('ofrece un solo camino de salida', function () {
    $html = resultadoHtmlCompleto();

    expect(substr_count($html, 'href="/"'))->toBe(1,
        'Hay más de una salida. La ficha pide UN solo camino: dos botones de igual peso en una '.
        'pantalla de cierre es justo lo que manda al vecino a llamar por teléfono.'
    );
});

it('escapa lo que viene del anfitrión y nunca lo mete en una expresión de Alpine', function () {
    $html = Blade::render(
        '<x-muni::pantalla-resultado :title="$t" :folio="$f" exit-href="/" />',
        ['t' => "Solicitud de O'Higgins", 'f' => "2026-0487'1<script>alert(1)</script>"]
    );

    expect(str_contains($html, '<script>alert(1)</script>'))->toBeFalse(
        'El folio sale sin escapar: la pantalla de cierre se convierte en el vector de XSS del sistema.'
    );

    // Lo que viaja a Alpine va por @js(), que escapa el apóstrofo, la comilla y
    // el `<`. Un apóstrofo crudo dentro de la expresión la descuadra y tumba el
    // Alpine de la página ENTERA, no solo el de este componente.
    preg_match('/x-data="(.*?)"/s', $html, $m);

    expect(isset($m[1]))->toBeTrue('El componente no declara x-data: no hay dónde vivir el copiado.');

    expect(str_contains($m[1], "'1<script"))->toBeFalse(
        'El folio entra crudo en la expresión de Alpine: un apóstrofo ahí descuadra la expresión '.
        'y tumba el Alpine de toda la página.'
    );

    expect(str_contains($m[1], '\\u0027'))->toBeTrue(
        'El apóstrofo del folio no viene escapado por @js(): entonces no pasó por @js().'
    );
});

it('no consulta al anfitrión ni genera ids que cambien en cada render', function () {
    $fuente = resultadoFuenteSinComentarios();

    foreach (['uniqid(', 'request(', 'Auth::', 'auth(', 'Gate::', 'Eloquent'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El componente usa «{$prohibido}». El paquete recibe todo ya resuelto por el anfitrión ".
            '(y un id con uniqid() cambia en cada render y rompe el diffing de Livewire).'
        );
    }

    // Los ids de dos renders con las mismas props, y no el HTML entero: el
    // bloque `@once` se emite una sola vez por petición, así que el segundo
    // render no lo trae y compararlos completos mediría eso y no los ids.
    preg_match_all('/id="([^"]+)"/', resultadoHtmlCompleto(), $ids);
    preg_match_all('/id="([^"]+)"/', resultadoHtmlCompleto(), $otros);

    expect($ids[1])->not->toBe([], 'El componente no emite ningún id: el título no se puede enlazar.');
    expect($otros[1])->toBe($ids[1], 'Dos renders con las mismas props dan ids distintos: no son estables.');
});

it('funciona en Livewire 3 y en Livewire 4', function () {
    $fuente = resultadoFuenteSinComentarios();

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($fuente, $prohibida))->toBeFalse(
            "Usa «{$prohibida}», que no existe en los dos majors de Livewire que el paquete soporta."
        );
    }

    expect(str_contains($fuente, '@keydown.escape.window'))->toBeFalse(
        'Escape se escucha en el elemento, no en la ventana: en la ventana se roba el Escape de '.
        'cualquier diálogo que el anfitrión tenga abierto.'
    );
});

it('no escribe un solo color literal', function () {
    $css = resultadoCss().' '.(string) preg_replace('#<style>.*?</style>#s', '', resultadoFuenteSinComentarios());

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $m);

    $literales = array_values(array_filter($m[0], fn (string $c) => strtolower($c) !== '#767676'));

    expect($literales)->toBe([],
        'Colores literales en el componente: '.implode(' ', $literales).'. La identidad de cada '.
        'municipio se cambia redefiniendo tokens; un literal no lo puede cambiar nadie.'
    );
});

it('usa solo tokens declarados en las DOS hojas', function () {
    preg_match_all('/var\(\s*(--muni-[a-z0-9-]+)/i', resultadoFuenteSinComentarios(), $m);

    $faltan = [];

    foreach (array_unique($m[1]) as $token) {
        if (! str_contains(cssMuniUi(), $token.':') || ! str_contains(cssMuniUiFilament(), $token.':')) {
            $faltan[] = $token;
        }
    }

    expect($faltan)->toBe([],
        'Estos tokens no están declarados en las dos hojas: '.implode(' ', $faltan).'. Dentro de un '.
        'panel Filament solo se carga muni-ui-filament.css, y un var() que no existe descarta la '.
        'declaración entera sin un solo error en consola (DESIGN §7).'
    );
});

it('el foco se ve con outline y el blanco táctil llega a 44px', function () {
    $css = resultadoCss();

    preg_match_all('/([^{}]*:focus[^{}]*)\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    expect($reglas)->not->toBe([], 'El componente no declara ninguna regla de foco.');

    foreach ($reglas as [, $selector, $cuerpo]) {
        expect((bool) preg_match('/outline\s*:\s*3px solid var\(--muni-focus, var\(--muni-accent, #767676\)\)/', $cuerpo))->toBeTrue(
            'La regla de foco «'.trim($selector).'» no usa el outline canónico del paquete. Dentro '.
            'de Filament la box-shadow del anillo se computa transparente (DESIGN §5).'
        );
    }

    expect(substr_count($css, 'min-height:44px'))->toBeGreaterThanOrEqual(2,
        'Los controles de la pantalla de cierre no llegan a 44px de alto. Es la pantalla que usan '.
        'un funcionario de mesón y un vecino mayor: 24px es el mínimo de WCAG 2.2 AA 2.5.8, y acá '.
        'corresponde el blanco de terreno.'
    );
});

it('el movimiento sale del token y se apaga solo', function () {
    $css = resultadoCss();

    preg_match_all('/transition\s*:\s*([^;}]+)/', $css, $m);

    foreach ($m[1] as $transicion) {
        // `transition:none` es la contraparte de movimiento reducido, no una animación.
        if (trim($transicion) === 'none') {
            continue;
        }

        expect(str_contains($transicion, 'var(--muni-dur)'))->toBeTrue(
            "La transición «{$transicion}» usa una duración fija: ignora prefers-reduced-motion, ".
            'que el token ya baja a 0 ms (DESIGN §6).'
        );
    }

    expect(str_contains($css, 'prefers-reduced-motion'))->toBeTrue(
        'Falta la contraparte explícita de movimiento reducido.'
    );
});

it('lleva su propio CSS dentro del componente', function () {
    $fuente = resultadoFuente();

    expect(str_contains($fuente, '@once'))->toBeTrue(
        'El CSS del componente no viaja dentro de un bloque `@once`: dentro de un panel Filament '.
        'solo se inyecta muni-ui-filament.css y la clase se quedaría sin estilo (DESIGN §7).'
    );

    expect(str_contains($fuente, '<style>'))->toBeTrue('El componente no lleva su bloque de estilos.');
});

it('el estado no se comunica solo con color', function () {
    $html = resultadoHtml('title="Su solicitud fue rechazada" tone="danger" folio="2026-04871" exit-href="/"');

    expect(str_contains($html, 'Su solicitud fue rechazada'))->toBeTrue(
        'El título es el portador real del resultado y tiene que verse en texto.'
    );

    expect((bool) preg_match('/<svg[^>]*(?:(?!<\/svg>).)*<\/svg>/s', $html))->toBeTrue(
        'No hay dibujo de estado.'
    );

    expect((bool) preg_match('/aria-hidden="true"[^>]*>\s*<svg|<svg[^>]*aria-hidden="true"/', $html))->toBeTrue(
        'El dibujo de estado no está oculto al lector de pantalla: lo leería como contenido y el '.
        'texto ya lo dice.'
    );

    // El par del tono viaja en el `style` del propio dibujo, no en una clase por
    // tono: así el CSS del `@once` no crece con cada tono y lo que se mide acá es
    // el tono REAL que se pidió, no una regla que existe siempre.
    expect((bool) preg_match('/style="[^"]*--muni-danger-fg/', $html))->toBeTrue(
        'El tono `danger` no pinta con su propio par de tokens de estado.'
    );

    $ok = resultadoHtml('title="Su solicitud fue recibida" folio="2026-04871" exit-href="/"');

    expect((bool) preg_match('/style="[^"]*--muni-ok-fg/', $ok))->toBeTrue(
        'Sin `tone` el cierre no cae en el tono de éxito, que es el caso del 95% de las pantallas.'
    );
});

/*
|--------------------------------------------------------------------------
| LO QUE EL REVISOR DEMOSTRÓ QUE NO SE ESTABA MIDIENDO
|--------------------------------------------------------------------------
|
| Las pruebas de arriba miraban el TEXTO del fuente («document.activeElement»,
| «execCommand», un `role="status"` vacío) y daban verde aunque el
| comportamiento estuviera roto: borrar `titulo.focus()` entero, o el
| `x-text="aviso"` de la región viva, las dejaba pasar igual. Acá se cierra cada
| hueco con la aserción que sí detecta el defecto, y la capa Alpine —que ningún
| `str_contains` puede medir— se verifica en navegador más abajo.
*/

it('con autofocus="false" NO enfoca el título: ni el atributo ni la rama de Alpine', function () {
    $html = Blade::render(
        '<x-muni::pantalla-resultado :autofocus="false" title="Su solicitud fue recibida" folio="2026-04871" exit-href="/" />'
    );

    expect((bool) preg_match('/<h1[^>]*\bautofocus\b/', $html))->toBeFalse(
        'Con `autofocus="false"` el <h1> sigue trayendo el atributo: el navegador enfoca igual.'
    );

    // El defecto que el revisor encontró: la prop apagaba el ATRIBUTO pero el
    // init() de Alpine seguía llamando a focus() siempre que nadie tuviera el
    // foco — y justo después de cargar una página, `activeElement` ES el body.
    // Con varias tarjetas en la vitrina todas peleaban por el foco y jalaban el
    // scroll. La bandera tiene que viajar DENTRO del x-data.
    preg_match('/x-data="(.*?)"/s', $html, $m);

    expect(isset($m[1]))->toBeTrue('El componente no declara x-data.');

    expect((bool) preg_match('/autofoco:\s*false/', $m[1]))->toBeTrue(
        'La prop `autofocus` no llega al x-data: Alpine enfoca el <h1> igual y le arranca el '.
        'scroll a la página que hospeda la pantalla.'
    );

    $conFoco = resultadoHtmlCompleto();
    preg_match('/x-data="(.*?)"/s', $conFoco, $m2);

    expect((bool) preg_match('/autofoco:\s*true/', $m2[1] ?? ''))->toBeTrue(
        'Por defecto la pantalla es la página entera y el foco SÍ va al título: la bandera tiene '.
        'que nacer en true.'
    );
});

it('Alpine pone el foco en el título de verdad, no solo lo menciona', function () {
    $fuente = resultadoFuenteSinComentarios();

    // Con `wire:navigate` el atributo `autofocus` no vuelve a dispararse: el
    // único que anuncia el resultado es Alpine. Si `titulo.focus()` desaparece,
    // la prueba vieja («contiene document.activeElement») seguía en verde.
    expect((bool) preg_match('/\btitulo\.focus\(\)/', $fuente))->toBeTrue(
        'Nadie llama a focus() sobre el título: con wire:navigate el atributo `autofocus` no se '.
        'vuelve a disparar y el resultado no se anuncia.'
    );

    expect((bool) preg_match('/if\s*\(\s*!\s*this\.autofoco\s*\)\s*return/', $fuente))->toBeTrue(
        'El init() de Alpine no respeta la bandera `autofoco`: la prop `autofocus` sería una mentira.'
    );

    expect(str_contains($fuente, 'document.activeElement'))->toBeTrue(
        'El componente enfoca el título sin mirar si alguien ya tenía el foco.'
    );
});

it('la región viva se LLENA: nace vacía pero está enlazada al aviso', function () {
    $html = resultadoHtmlCompleto();

    // El regex viejo casaba con una región vacía PARA SIEMPRE: si se borraba el
    // x-text, el aviso no llegaba nunca y la prueba seguía verde.
    expect((bool) preg_match('/<p[^>]*role="status"[^>]*x-text="aviso"[^>]*>\s*<\/p>|<p[^>]*x-text="aviso"[^>]*role="status"[^>]*>\s*<\/p>/', $html))->toBeTrue(
        'La región viva no está enlazada al aviso (`x-text="aviso"`) o no nace vacía: o no se '.
        'anuncia nunca, o se anuncia sola al cargar.'
    );
});

it('el segundo copiado también se anuncia: el aviso se vacía antes de volver a escribirse', function () {
    $fuente = resultadoFuenteSinComentarios();

    // Si el texto es idéntico al que ya estaba, no hay mutación de DOM y el
    // lector de pantalla NO vuelve a hablar: la segunda confirmación es muda.
    expect((bool) preg_match('/this\.aviso\s*=\s*\x27\x27/', $fuente))->toBeTrue(
        'El aviso se reescribe con la misma cadena: al pulsar «Copiar folio» dos veces seguidas no '.
        'hay mutación y el lector de pantalla se queda callado.'
    );

    expect(str_contains($fuente, '$nextTick'))->toBeTrue(
        'El aviso se vacía y se rellena en el mismo ciclo: Alpine junta las dos escrituras en una '.
        'sola mutación y el lector no vuelve a anunciar.'
    );
});

it('el aria-labelledby del anfitrión no duplica el atributo', function () {
    $propio = resultadoHtmlCompleto();

    expect(substr_count($propio, 'aria-labelledby='))->toBe(1,
        'El componente emite más de un aria-labelledby.'
    );

    expect((bool) preg_match('/aria-labelledby="muni-res-[0-9a-f]{8}-titulo"/', $propio))->toBeTrue(
        'La sección no queda nombrada por su propio <h1>.'
    );

    // DESIGN §8: los atributos condicionales van por ->merge(), nunca escritos a
    // mano antes del derrame. Escrito a mano salían DOS aria-labelledby en el
    // mismo <section>: HTML inválido (Decreto N°1/2015) y el valor del anfitrión
    // perdido en silencio, porque el navegador se queda con el primero.
    $ajeno = resultadoHtml('title="Su solicitud fue recibida" folio="2026-04871" exit-href="/" aria-labelledby="titulo-del-anfitrion"');

    expect(substr_count($ajeno, 'aria-labelledby='))->toBe(1,
        'Con un aria-labelledby del anfitrión el <section> sale con DOS: HTML inválido y el valor '.
        'del anfitrión se pierde, porque el navegador se queda con el primero.'
    );

    expect(str_contains($ajeno, 'aria-labelledby="titulo-del-anfitrion"'))->toBeTrue(
        'El aria-labelledby del anfitrión no gana: el que manda el consumidor tiene que ser el que '.
        'queda (DESIGN §8, `->merge()` reemplaza).'
    );
});

it('un folio "0" sigue siendo un folio', function () {
    $html = resultadoHtml('title="Su solicitud fue recibida" folio="0" exit-href="/"');

    // Se busca la CLASE EN EL ATRIBUTO y no la cadena suelta: el bloque de
    // estilos del `@once` también nombra `.muni-res__folio`, así que buscarla a
    // secas daría verde con el bloque del folio borrado.
    expect(str_contains($html, 'class="muni-res__folio"'))->toBeTrue(
        'Con folio="0" desaparece el bloque del folio, el botón de copiar y el aria-describedby: '.
        '`@if ($folio)` descarta valores falsy válidos.'
    );

    expect(str_contains($html, 'Copiar folio'))->toBeTrue(
        'Con folio="0" no hay botón de copiar.'
    );

    $sin = resultadoHtml('title="Su solicitud fue recibida" exit-href="/"');

    expect(str_contains($sin, 'class="muni-res__folio"'))->toBeFalse(
        'Sin folio el componente dibuja el bloque vacío.'
    );
});

it('la salida sigue siendo lo último enfocable cuando hay texto libre en la ranura', function () {
    $html = resultadoHtml(
        'title="Su solicitud fue recibida" folio="2026-04871" '
        .'print-href="/comprobante/2026-04871" exit-href="/"',
        'Le llegará un correo cuando cambie el estado de su solicitud.'
    );

    preg_match_all('/<(?:a\s[^>]*href=|button\b)/i', $html, $m, PREG_OFFSET_CAPTURE);

    $ultimo = end($m[0])[1];

    expect(str_contains(substr($html, $ultimo, 400), 'href="/"'))->toBeTrue(
        'Con la ranura llena la salida deja de ser lo último enfocable. Es justo el caso que el '.
        'comentario del componente declara riesgoso: prosa sí, enlaces no.'
    );
});

it('lleva consigo la declaración de .muni-num y el prefijo de WebKit', function () {
    $css = resultadoCss();

    // DESIGN §7: dentro de un panel Filament solo se inyecta
    // muni-ui-filament.css. `.muni-num` declarada solo en muni-ui.css es letra
    // muerta ahí dentro, como ya pasó en `stat` y en `description-list`.
    expect((bool) preg_match('/\.muni-num\s*\{[^}]*font-variant-numeric\s*:\s*tabular-nums/', $css))->toBeTrue(
        'El componente aplica .muni-num pero no la declara en su bloque de estilos: dentro de un '.
        'panel Filament la clase no existe (DESIGN §7 y §9).'
    );

    // El folio seleccionable de un clic es la ÚLTIMA alternativa de la cadena de
    // copiado: si en WebKit no aplica, el usuario de Safari se queda sin ninguna.
    expect((bool) preg_match('/-webkit-user-select\s*:\s*all/', $css))->toBeTrue(
        'Falta `-webkit-user-select:all`: en Safari el folio no se selecciona de un clic y ahí se '.
        'acaba la última alternativa al portapapeles.'
    );
});

// ---------------------------------------------------------------------------
// Verificación en navegador
// ---------------------------------------------------------------------------

/*
 * El banco. Se genera acá y no en un script suelto porque este es el único sitio
 * del paquete con Blade arrancado (mismo criterio que `GeneraVitrinaTest`,
 * `CampoDeClaveTest` y `SolapasDeRutaTest`). Sale a `build/pantalla-resultado/`,
 * ignorado por git.
 *
 * Seis páginas, y cada una existe por algo que ninguna prueba de texto alcanza:
 *
 * · `claro` y `oscuro` — la pantalla completa con Alpine: el foco al <h1>, la
 *   cadena de copiado, el llenado de la región viva y el recorrido de Tab.
 * · `panel-claro` y `panel-oscuro` — la MISMA pantalla cargando ÚNICAMENTE
 *   `muni-ui-filament.css` (DESIGN §7): dentro de un panel `muni-ui.css` no se
 *   carga, y es ahí donde una clase declarada fuera del `@once` —`.muni-num`—
 *   se queda sin estilo y sin un solo error en consola.
 * · `tarjeta` — el componente con `autofocus="false"` DEBAJO de contenido largo:
 *   si la prop miente, el foco salta al título y la página se desplaza sola.
 *   Es el defecto exacto que el revisor encontró y que ninguna prueba de Blade
 *   veía, porque la prop apagaba el atributo pero no la rama de Alpine.
 * · `sin-alpine` — la misma pantalla SIN el script: el botón de copiar no puede
 *   aparecer (x-cloak) y el folio tiene que seguir visible y seleccionable.
 */
function resultadoAlpineDelBanco(): ?string
{
    $ruta = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    return is_file($ruta) ? (string) file_get_contents($ruta) : null;
}

it('genera el banco de navegador en build/pantalla-resultado/', function () {
    $dir = __DIR__.'/../build/pantalla-resultado';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $alpine = resultadoAlpineDelBanco();

    expect($alpine)->not->toBeNull(
        'No está node_modules/alpinejs: corre `npm install`. Sin Alpine el banco no puede medir '.
        'ni el copiado ni el foco, que es justo lo que ninguna prueba de texto alcanza.'
    );

    $pantalla = Blade::render(
        '<x-muni::pantalla-resultado title="Su solicitud fue recibida" '
        .'message="La Dirección de Tránsito la revisará en un plazo de 5 días hábiles." '
        .'folio="2026-04871" print-href="#comprobante" exit-href="#inicio" exit-label="Volver al inicio">'
        .'Le llegará un correo cuando cambie el estado de su solicitud.'
        .'</x-muni::pantalla-resultado>'
    );

    /* La MISMA pantalla, pero como tarjeta dentro de otra página: es el caso que
       entrega la entrada de vitrina y el que el revisor demostró que fallaba. */
    $tarjeta = Blade::render(
        '<x-muni::pantalla-resultado :autofocus="false" title="Su solicitud fue recibida" '
        .'message="La Dirección de Tránsito la revisará en un plazo de 5 días hábiles." '
        .'folio="2026-04871" print-href="#comprobante" exit-href="#inicio" />'
    );

    $paginas = [
        'claro' => ['light', cssMuniUi(), false, $pantalla, true],
        'oscuro' => ['dark', cssMuniUi(), false, $pantalla, true],
        'panel-claro' => ['light', cssMuniUiFilament(), true, $pantalla, true],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true, $pantalla, true],
        'tarjeta' => ['light', cssMuniUi(), false, $tarjeta, true],
        'sin-alpine' => ['light', cssMuniUi(), false, $pantalla, false],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel, $cuerpoPantalla, $conAlpine]) {
        $oscuro = $tema === 'dark';
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        /* El armazón no aporta ni un color: fondo y texto salen de los mismos
           tokens que lee el componente. */
        $armazon = 'body{margin:0;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'.relleno{padding:24px;max-width:60ch}';

        /* En `tarjeta` la pantalla va DEBAJO de una pantalla de alto: si el
           componente enfoca el título igual, el navegador desplaza la página
           para mostrarlo y `scrollY` deja de ser 0. Así el robo de foco se mide
           con un número, no con una impresión. */
        $antes = $nombre === 'tarjeta'
            ? '<div class="relleno" style="min-height:140vh"><h1>Solicitudes de la Dirección de Tránsito</h1>'
                .'<p>El detalle de la solicitud sigue abajo.</p></div>'
            : '';

        $script = $conAlpine && $alpine !== null
            ? '<script data-banco="alpine-core">'.$alpine.'</script>'
            : '';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .$antes.'<section class="fi-section">'.$cuerpoPantalla.'</section></main></div>'.$script.'</body>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$antes.$cuerpoPantalla.'</main>'.$script.'</body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco pantalla-resultado — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);

        expect(str_contains($pagina, 'class="muni-res"'))->toBeTrue('El banco '.$nombre.' salió sin la pantalla.');
        expect(str_contains($pagina, 'data-banco="alpine-core"'))->toBe($conAlpine,
            'El banco '.$nombre.' no trae el Alpine que le corresponde.'
        );
    }

    // El bloque `@once` se emite UNA vez por petición: si la pantalla de la
    // tarjeta se hubiera renderizado sin estilos, las medidas del navegador
    // serían las del navegador y no las del componente.
    expect(str_contains((string) file_get_contents($dir.'/claro.html'), '.muni-res__valor'))->toBeTrue(
        'El banco salió sin el bloque de estilos del componente: nada de lo que se mida arriba sería suyo.'
    );
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function resultadoPythonDeLaReja(): ?string
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
it('en Chromium y Firefox: el foco, la cadena de copiado, la región viva y el x-cloak', function () {
    $python = resultadoPythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/pantalla-resultado.py').' '
        .escapeshellarg(__DIR__.'/../build/pantalla-resultado').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de la pantalla de cierre falló:\n".implode("\n", $lineas));

    // Las cuatro páginas completas × dos navegadores tienen que reportar el foco
    // en el <h1> y el aviso lleno tras el copiado. Si el banco se quedara sin
    // páginas, el script diría «todo pasa» sobre nada.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'foco=h1'))))->toBe(8,
        "Los ocho recorridos de la pantalla completa tienen que poner el foco en el <h1>:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'cadena=3/3'))))->toBe(8,
        "Los tres eslabones del copiado (portapapeles, execCommand y selección) tienen que responder:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'no-roba-foco'))))->toBe(2,
        "La tarjeta con autofocus=\"false\" tiene que dejar el foco donde estaba en los dos navegadores:\n".implode("\n", $lineas));
});
