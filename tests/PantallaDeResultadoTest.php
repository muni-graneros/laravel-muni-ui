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
