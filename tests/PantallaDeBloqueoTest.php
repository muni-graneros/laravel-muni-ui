<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DEL MESÓN
|--------------------------------------------------------------------------
|
| `<x-muni::pantalla-bloqueo>` es la ficha `pantalla-bloqueo` de
| docs/GAP-ANALYSIS.md: bloquear la sesión por inactividad SIN cerrarla, con la
| identidad de quien está conectado a la vista, un solo campo de contraseña para
| volver y una salida explícita para entrar como otra persona.
|
| Por qué existe: en un mesón municipal la sesión abierta frente al público es
| una fuga de datos personales del vecino que está siendo atendido (Ley 21.719),
| y hoy la única defensa del paquete es cerrar sesión y perder el trabajo en
| curso. `sesion-guardia` cubre la sesión que CADUCA; esta cubre la sesión que
| sigue viva mientras el funcionario no está delante de la pantalla.
|
| Lo que esta prueba vigila, y por qué cada cosa:
|
| · **Es un diálogo modal de verdad cuando se muestra como capa.** `role="dialog"`,
|   `aria-modal="true"`, foco atrapado con `x-trap.inert` —«inert de verdad, no
|   solo tapado»: sin `.inert` un lector de pantalla sigue leyendo la ficha del
|   vecino que quedó debajo— y velo OPACO, porque la pantalla está a la vista del
|   público.
| · **Esc NO cierra.** Es la única tecla obligatoria de la ficha que va al revés
|   que en todo el resto del paquete: un bloqueo que se descarta con una tecla no
|   es un bloqueo. El manejador existe igual, en el elemento y no en `window`
|   (DESIGN §8 y el precedente de `modal`), para que Escape no se escape hacia lo
|   que haya debajo.
| · **Tres paradas de teclado y ninguna más:** el campo, «Entrar» y «Entrar como
|   otro usuario». Nada de un alternador de «mostrar contraseña» —que es lo que
|   trae `input-password`— porque agregaría una cuarta parada que la ficha no
|   contempla y dejaría la contraseña del funcionario legible desde el mesón, que
|   es exactamente lo que este componente viene a evitar.
| · **El error se anuncia en texto** (`role="alert"`), cuelga del campo por
|   `aria-describedby` y el campo queda `aria-invalid`, con el foco de vuelta en
|   él.
| · **El gotcha de «Recordarme».** La ficha lo nombra con todas las letras: acá
|   sería fatal. El componente no emite casilla de recordarme ni campo
|   `remember`: el desbloqueo se decide con la contraseña, no con una cookie.
| · **Ley 21.719, minimización.** Recibe strings ya redactados por el anfitrión;
|   no consulta `request()`, ni `Auth`, ni `Gate`, ni permisos, y no vuelca un
|   registro completo al DOM.
| · **Nada del anfitrión entra en una expresión de Alpine.** Un apóstrofo en un
|   nombre chileno —«D'Angelo»— dentro de una cadena de comillas simples tumba el
|   Alpine de la página ENTERA. Ya pasó en `file-dropzone`.
|
| Todo va con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
| de `toContain()` es otra aguja, no un mensaje, y la aserción se apaga sin
| avisar.
*/

/** El HTML servido del componente. */
function bloqueoHtml(string $atributos = ''): string
{
    return Blade::render("<x-muni::pantalla-bloqueo {$atributos} />");
}

/** El ejemplo completo en modo capa, que es el que se mide en el navegador. */
function bloqueoHtmlCapa(): string
{
    return bloqueoHtml(
        'nombre="María Fernanda Soto" cargo="Atención al Vecino · Mesón 2" '
        .'accion="/desbloquear" otro-url="/salir" abierto'
    );
}

/** El mismo componente como página propia. */
function bloqueoHtmlPagina(): string
{
    return bloqueoHtml(
        'modo="pagina" nombre="María Fernanda Soto" cargo="Atención al Vecino · Mesón 2" '
        .'accion="/desbloquear" otro-url="/salir"'
    );
}

/**
 * El fuente crudo del componente. Falla si no existe: si devolviera cadena
 * vacía, las pruebas que buscan lo que el componente NO debe tener darían verde
 * sin componente.
 */
function bloqueoFuente(): string
{
    $ruta = __DIR__.'/../resources/views/components/pantalla-bloqueo.blade.php';

    expect(file_exists($ruta))->toBeTrue('No existe resources/views/components/pantalla-bloqueo.blade.php.');

    return (string) file_get_contents($ruta);
}

/**
 * El fuente sin comentarios: la prosa explicativa nombra a propósito lo que el
 * componente NO hace («uniqid», «wire:show», «Recordarme»), y buscarlo sobre el
 * fuente entero daría falsos positivos contra el propio comentario.
 */
function bloqueoFuenteSinComentarios(): string
{
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', bloqueoFuente());

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El CSS que el componente lleva consigo, sin comentarios. */
function bloqueoCss(): string
{
    preg_match_all('#<style>(.*?)</style>#s', bloqueoFuente(), $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

it('como capa es un diálogo modal con el foco atrapado y el fondo inerte', function () {
    $html = bloqueoHtmlCapa();

    expect((bool) preg_match('/role="dialog"[^>]*aria-modal="true"|aria-modal="true"[^>]*role="dialog"/', $html))->toBeTrue(
        'La capa no es un diálogo modal: sin role="dialog" y aria-modal="true" el lector de '.
        'pantalla sigue leyendo la ficha del vecino que quedó debajo.'
    );

    expect(str_contains($html, 'x-trap.inert'))->toBeTrue(
        'Falta x-trap.inert: «nada de atrás es alcanzable: inert de verdad, no solo tapado». '.
        'Sin .inert el contenido de atrás sigue en el árbol de accesibilidad.'
    );

    expect(str_contains($html, 'x-teleport="body"'))->toBeTrue(
        'La capa no se teleporta al <body>: dentro de un contenedor con overflow o transform, '.
        'un position:fixed se recorta y el bloqueo deja de tapar la pantalla.'
    );

    expect((bool) preg_match('/aria-labelledby="[^"]+"/', $html))->toBeTrue(
        'El diálogo no tiene nombre accesible: aria-labelledby tiene que apuntar a su título.'
    );
});

it('Esc no cierra, y el manejador vive en el elemento, no en window', function () {
    $fuente = bloqueoFuenteSinComentarios();

    expect(str_contains($fuente, '@keydown.escape.window'))->toBeFalse(
        'Escape en window cerraría de un golpe todo lo abierto en la página y se tragaría el '.
        'Escape de lo que viva dentro (DESIGN §8 y el precedente de `modal`).'
    );

    expect((bool) preg_match('/keydown\.escape[^"]*="[^"]*(bloqueado\s*=\s*false|cerrar\(\))/', $fuente))->toBeFalse(
        'Escape cierra el bloqueo. La ficha lo dice sin rodeos: «Esc NO cierra». Un bloqueo que '.
        'se descarta con una tecla no protege nada.'
    );

    expect((bool) preg_match('/keydown\.escape\.prevent\.stop/', $fuente))->toBeTrue(
        'Falta el manejador de Escape en el propio diálogo: sin él la tecla sigue hacia lo que '.
        'haya debajo (un drawer, un modal del anfitrión) y cierra otra cosa.'
    );
});

it('el teclado alcanza exactamente tres paradas: campo, entrar y entrar como otro usuario', function () {
    foreach (['capa' => bloqueoHtmlCapa(), 'pagina' => bloqueoHtmlPagina()] as $modo => $html) {
        preg_match_all('/<(?:a\s[^>]*href=|button|input|select|textarea)[^>]*>/', $html, $crudos);

        $paradas = array_values(array_filter($crudos[0], function (string $etiqueta) {
            if (str_contains($etiqueta, 'type="hidden"')) {
                return false;   // el token CSRF no es una parada
            }

            return ! (bool) preg_match('/tabindex="-1"/', $etiqueta);
        }));

        expect(count($paradas))->toBe(3,
            "En modo {$modo} el teclado alcanza ".count($paradas).' paradas y la ficha permite tres: '.
            "el campo, «Entrar» y «Entrar como otro usuario». Paradas:\n".implode("\n", $paradas)
        );
    }
});

it('el campo es de contraseña, obligatorio, y se enfoca al aparecer', function () {
    $html = bloqueoHtmlCapa();

    expect((bool) preg_match('/<input[^>]*type="password"[^>]*>/', $html))->toBeTrue(
        'No hay un campo de contraseña.'
    );

    expect((bool) preg_match('/<input[^>]*autocomplete="current-password"/', $html))->toBeTrue(
        'El campo no declara autocomplete="current-password": el gestor de claves del funcionario '.
        'no sabe qué ofrecer y el lector de pantalla pierde el propósito (WCAG 2.2 AA 1.3.5).'
    );

    expect((bool) preg_match('/<input[^>]*\brequired\b/', $html))->toBeTrue(
        'El campo no es obligatorio: un envío vacío viaja al servidor sin necesidad.'
    );

    expect((bool) preg_match('/<input[^>]*\bautofocus\b/', $html))->toBeTrue(
        'El campo no lleva autofocus: es el contrato de x-trap para el foco inicial y lo que hace '.
        'que el funcionario pueda escribir sin tocar el ratón (ficha, tecla obligatoria 1).'
    );

    expect((bool) preg_match('/<label[^>]*for="([^"]+)"[^>]*>.*?<input[^>]*id="\1"/s', $html))->toBeTrue(
        'La etiqueta no está atada al campo por for/id.'
    );
});

it('el error se anuncia en texto, cuelga del campo y lo marca inválido', function () {
    $html = bloqueoHtml(
        'nombre="María Fernanda Soto" accion="/desbloquear" abierto '
        .'error="La contraseña no coincide. Vuelve a intentarlo."'
    );

    expect((bool) preg_match('/<p[^>]*role="alert"[^>]*>\s*La contraseña no coincide/', $html))->toBeTrue(
        'El error no va en un role="alert": el funcionario que no ve la pantalla no se entera de '.
        'que la contraseña falló.'
    );

    expect((bool) preg_match('/<input[^>]*aria-invalid="true"/', $html))->toBeTrue(
        'El campo no queda aria-invalid con el error del servidor.'
    );

    expect((bool) preg_match('/<input[^>]*aria-describedby="([^"]*)"/', $html, $m))->toBeTrue(
        'El campo no describe el error: sin aria-describedby el mensaje no se lee al enfocar.'
    );

    expect(str_contains($html, 'id="'.trim($m[1]).'"'))->toBeTrue(
        'El aria-describedby del campo apunta a un id que no existe en la página.'
    );

    // Sin error no hay región de alerta vacía: una que nace con contenido no se anuncia,
    // y una vacía y permanente acá sobraría porque el error llega del servidor con la página.
    expect(str_contains(bloqueoHtmlCapa(), 'role="alert"'))->toBeFalse(
        'Sin error del servidor no tiene que haber ningún role="alert" en la página.'
    );
});

it('no ofrece «Recordarme» ni ninguna forma de saltarse la contraseña', function () {
    $html = bloqueoHtmlCapa();

    expect((bool) preg_match('/name="(remember|recordar|recuerdame|remember_me)"/i', $html))->toBeFalse(
        'El componente emite un campo de «Recordarme». La ficha lo marca como fatal acá: el '.
        'desbloqueo se decide con la contraseña, nunca con una cookie recordada.'
    );

    expect((bool) preg_match('/type="checkbox"/', $html))->toBeFalse(
        'Hay una casilla en la pantalla de bloqueo. La única decisión posible es escribir la '.
        'contraseña o entrar como otra persona.'
    );
});

it('el desbloqueo y la salida son POST con token CSRF, y la salida por enlace es opcional', function () {
    $html = bloqueoHtmlCapa();

    expect(substr_count($html, 'method="post"'))->toBe(2,
        'Tienen que ser dos formularios POST: el desbloqueo y la salida. Un GET que cierra '.
        'sesión se dispara con una imagen remota (CSRF).'
    );

    expect(substr_count($html, 'name="_token"'))->toBe(2,
        'Falta el token CSRF en alguno de los dos formularios.'
    );

    expect((bool) preg_match('/<form[^>]*>(?:(?!<\/form>).)*<form/s', $html))->toBeFalse(
        'Hay un <form> dentro de otro: HTML inválido, y el navegador descarta el interior.'
    );

    $conEnlace = bloqueoHtml('nombre="María Fernanda Soto" otro-url="/ingresar" otro-metodo="get" abierto');

    expect((bool) preg_match('/<a[^>]*href="\/ingresar"/', $conEnlace))->toBeTrue(
        'Con otro-metodo="get" la salida tiene que ser un enlace: es el caso del anfitrión que '.
        'manda a una pantalla de ingreso, sin cerrar nada por el camino.'
    );

    $sinSalida = bloqueoHtml('nombre="María Fernanda Soto" accion="/desbloquear" abierto');

    expect(str_contains($sinSalida, 'Entrar como otro usuario'))->toBeFalse(
        'Sin otro-url no puede haber botón de salida: un control que no lleva a ninguna parte es '.
        'peor que no ofrecerlo.'
    );
});

it('muestra quién está bloqueado, con un solo encabezado según el modo', function () {
    $capa = bloqueoHtmlCapa();

    expect(str_contains($capa, 'María Fernanda Soto'))->toBeTrue('No se ve quién está conectado.');
    expect(str_contains($capa, 'Atención al Vecino · Mesón 2'))->toBeTrue('No se ve el cargo.');

    expect(substr_count($capa, '<h2'))->toBe(1,
        'La capa tiene que dar exactamente un <h2>: el <h1> es el de la página que quedó debajo.'
    );
    expect(substr_count($capa, '<h1'))->toBe(0,
        'La capa no puede dar un <h1>: la página de abajo ya tiene el suyo y quedarían dos.'
    );

    $pagina = bloqueoHtmlPagina();

    expect(substr_count($pagina, '<h1'))->toBe(1,
        'Como página propia el título es el <h1> de la pantalla.'
    );
    expect(str_contains($pagina, 'x-teleport'))->toBeFalse(
        'La página propia no teleporta nada: no es una capa.'
    );
    expect(str_contains($pagina, 'x-trap'))->toBeFalse(
        'La página propia no atrapa el foco: no hay nada detrás de lo que atraparlo (la ficha: '.
        '«ninguno si es página»).'
    );
});

it('ningún texto del anfitrión entra en una expresión de Alpine', function () {
    $html = bloqueoHtml('nombre="Ana D\'Angelo" cargo="Tránsito\'s" accion="/desbloquear" abierto');

    expect((bool) preg_match('/x-data="([^"]*)"/', $html, $m))->toBeTrue('No hay bloque x-data que revisar.');

    foreach (["D'Angelo", 'Angelo', 'Tránsito'] as $texto) {
        expect(str_contains($m[1], $texto))->toBeFalse(
            "El texto del anfitrión («{$texto}») llegó DENTRO de la expresión de Alpine. Un ".
            'apóstrofo ahí descuadra la cadena y tumba el Alpine de la página entera (ya pasó '.
            'en `file-dropzone`).'
        );
    }

    // Y la expresión queda con las comillas simples cuadradas, que es la forma de
    // comprobarlo sin depender de qué letra lleve el apellido de turno.
    expect(substr_count($m[1], "'") % 2)->toBe(0,
        'La expresión de Alpine quedó con un número impar de apóstrofos: hay una cadena abierta.'
    );

    /* Y NINGUNA COMILLA DOBLE dentro de la expresión, ni siquiera en un comentario de
       JavaScript: el x-data es el valor de un atributo HTML entre comillas dobles, así que
       la primera que aparezca cierra el atributo ahí mismo. Alpine queda con «Invalid or
       unexpected token», el componente entero no hidrata y no hay ni un error en rojo que lo
       explique. Se mira el FUENTE y no el HTML rendido: sobre el HTML, el propio regex se
       corta en esa comilla y no vería nada. */
    $fuente = (string) file_get_contents(__DIR__.'/../resources/views/components/pantalla-bloqueo.blade.php');
    $desde = strpos($fuente, 'x-data="');
    $hasta = strpos($fuente, "\n        }\"", (int) $desde);
    $expresion = substr($fuente, (int) $desde + 8, (int) $hasta - (int) $desde - 8);

    expect($expresion === '' ? null : substr_count($expresion, '"'))->toBe(0,
        'Hay una comilla doble dentro de la expresión x-data (en el código o en un comentario '.
        'de JavaScript): cierra el atributo HTML y el componente no hidrata.'
    );

    // Y el apóstrofo sí llega al texto visible, escapado por Blade.
    expect(str_contains($html, 'D&#039;Angelo') || str_contains($html, 'D&apos;Angelo'))->toBeTrue(
        'El apóstrofo del nombre no se escapó en el HTML.'
    );
});

it('la bolsa del anfitrión llega al diálogo, que es lo que se ve, y no a la raíz vacía', function () {
    /* En modo capa, la raíz con el x-data se queda VACÍA en su sitio: todo lo visible se
       va al <body> con el x-teleport. Derramar ahí la bolsa dejaba la clase, el estilo y
       el aria-labelledby del anfitrión sin llegar a ninguna parte, en silencio. */
    $html = bloqueoHtml(
        'nombre="María Fernanda Soto" class="mi-capa" aria-labelledby="titulo-del-anfitrion" '
        .'data-prueba="si" abierto'
    );

    expect((bool) preg_match('/<div\b[^>]*role="dialog"[^>]*>/', $html, $m))->toBeTrue('No hay diálogo.');
    $dialogo = $m[0];

    foreach (['mi-capa', 'muni-pb__capa', 'data-prueba="si"', 'titulo-del-anfitrion'] as $esperado) {
        expect(str_contains($dialogo, $esperado))->toBeTrue(
            "La bolsa del anfitrión no llegó al diálogo: falta «{$esperado}»."
        );
    }

    // UN solo aria-labelledby: con dos, el navegador se queda con el primero y el nombre
    // que puso el anfitrión se pierde sin un error (DESIGN §8).
    expect(substr_count($dialogo, 'aria-labelledby='))->toBe(1,
        'El diálogo emite dos aria-labelledby: el del anfitrión se pierde en silencio.'
    );
    expect(str_contains($dialogo, 'muni-bloqueo-'))->toBeTrue('El diálogo perdió su propio id de título.');

    // Y el x-cloak sigue siendo efectivo: `display:none` viaja DENTRO del merge, así que
    // un `style` del anfitrión no puede dejar el atributo repetido.
    expect(substr_count($dialogo, 'style='))->toBe(1, 'El diálogo emite dos atributos style.');
    expect(str_contains($dialogo, 'display:none'))->toBeTrue(
        'Sin display:none la capa da un destello a pantalla completa antes de que Alpine hidrate.'
    );

    // La raíz con el x-data se queda solo con lo suyo.
    $raiz = substr($html, 0, (int) strpos($html, '<template'));
    foreach (['mi-capa', 'data-prueba', 'titulo-del-anfitrion'] as $noEsperado) {
        expect(str_contains($raiz, $noEsperado))->toBeFalse(
            "«{$noEsperado}» quedó en la raíz teleportadora, que después del x-teleport no se ve."
        );
    }
});

it('al ceder queda por debajo del velo de `sesion-guardia`, y por encima de todo lo demás', function () {
    /* La convivencia de las dos capas se mide en el navegador (páginas `con-guardia` y
       `con-guardia-antes` del banco). Acá se ata la regla de pintura contra el FUENTE del
       otro componente, que es de donde salió el número: mientras manda, este bloqueo está a
       la misma altura que el velo de T=0 de la guardia; cuando cede, baja —para que quien
       manda pinte arriba SIEMPRE, y no según en qué orden escribió el anfitrión los dos
       componentes— pero se queda por encima de toast-host (300), modal y drawer (200), que
       es lo que mantiene tapada la ficha del vecino. */
    $guardia = (string) file_get_contents(__DIR__.'/../resources/views/components/sesion-guardia.blade.php');
    $bloqueo = (string) file_get_contents(__DIR__.'/../resources/views/components/pantalla-bloqueo.blade.php');

    expect((bool) preg_match('/\.muni-sg__capa\s*\{[^}]*z-index:\s*(\d+)/', $guardia, $sg))->toBeTrue(
        'No se pudo leer el z-index del velo de `sesion-guardia`.'
    );
    expect((bool) preg_match('/\.muni-pb__capa\s*\{[^}]*z-index:\s*(\d+)/', $bloqueo, $pb))->toBeTrue(
        'No se pudo leer el z-index de la capa del bloqueo.'
    );
    expect((bool) preg_match('/\.muni-pb__capa--cedida\s*\{[^}]*z-index:\s*(\d+)/', $bloqueo, $ce))->toBeTrue(
        'No hay regla para la capa cedida: sin ella, con el mismo z-index decide el orden del marcado.'
    );

    expect((int) $pb[1])->toBe((int) $sg[1],
        'Mientras manda, el bloqueo tiene que estar a la misma altura que el velo de T=0 de la guardia.'
    );
    expect((int) $ce[1])->toBeLessThan((int) $sg[1],
        'La capa cedida tiene que quedar por DEBAJO de la guardia: si no, tapa al diálogo que manda.'
    );
    expect((int) $ce[1])->toBeGreaterThan(300,
        'La capa cedida tiene que seguir por encima de toast-host (300), modal y drawer (200): '.
        'el velo opaco es lo que mantiene la ficha del vecino fuera de la vista del público.'
    );
});

it('no consulta al anfitrión ni usa directivas de un solo major de Livewire', function () {
    $fuente = bloqueoFuenteSinComentarios();

    foreach (['request()', 'Auth::', 'auth()', 'Gate::', 'can(', 'uniqid('] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El componente usa «{$prohibido}». El paquete recibe todo ya resuelto por el anfitrión ".
            '(Ley 21.719, minimización) y los ids salen de las props, nunca de uniqid() (DESIGN §10).'
        );
    }

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El componente usa «{$prohibido}», que no existe en los dos majors de Livewire con los ".
            'que convive el paquete (CLAUDE.md).'
        );
    }
});

it('no escribe un solo color literal y todo token --muni-* existe en las dos hojas', function () {
    $css = bloqueoCss();

    expect(trim($css) !== '')->toBeTrue('El componente no lleva su CSS consigo (DESIGN §7).');

    $hex = preg_replace('/var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/', '', $css);

    expect((bool) preg_match('/(?<!&)#[0-9a-fA-F]{3,8}\b/', (string) $hex))->toBeFalse(
        'Hay un color literal en el CSS del componente: todo sale de tokens --muni-* (DESIGN §10). '.
        'El único hex permitido es el #767676 del tercer respaldo del foco.'
    );

    expect((bool) preg_match('/\b(rgba?|hsla?)\s*\(/i', $css))->toBeFalse(
        'Los colores salen de tokens --muni-*, no de rgb()/hsl().'
    );

    preg_match_all('/--muni-[a-z0-9-]+/', $css, $usados);
    $hoja = cssMuniUi();
    $hojaPanel = cssMuniUiFilament();

    foreach (array_unique($usados[0]) as $token) {
        expect(str_contains($hoja, $token.':'))->toBeTrue("«{$token}» no está declarado en muni-ui.css.");
        expect(str_contains($hojaPanel, $token.':'))->toBeTrue(
            "«{$token}» no está declarado en muni-ui-filament.css. Dentro de un panel Filament solo ".
            'se carga esa hoja (DESIGN §7): el componente se vería sin color y sin un error en consola.'
        );
    }

    // Las variables propias del componente NO se disfrazan de token del sistema.
    expect((bool) preg_match('/--muni-[a-z0-9-]+\s*:/', $css))->toBeFalse(
        'El componente DECLARA un --muni-*: los tokens del sistema se definen en las dos hojas, no '.
        'en un componente. Una variable local va con prefijo propio (--mpb-).'
    );
});

it('el foco es un outline de 3 px y el movimiento respeta la preferencia', function () {
    $css = bloqueoCss();

    expect(substr_count($css, 'outline:3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeGreaterThanOrEqual(1,
        'El indicador de foco no es el outline canónico de 3 px: dentro de Filament la box-shadow '.
        'del anillo se computa transparente (DESIGN §5).'
    );

    foreach (preg_split('/\n/', $css) ?: [] as $linea) {
        // `transition:none` es la guardia de movimiento reducido, no una transición.
        if (! str_contains($linea, 'transition:') || str_contains($linea, 'transition:none')) {
            continue;
        }

        expect(str_contains($linea, 'var(--muni-dur)'))->toBeTrue(
            'Una transición no usa var(--muni-dur), que es lo que baja a 0 ms con movimiento '.
            "reducido (DESIGN §6):\n".trim($linea)
        );
    }

    expect(str_contains($css, '@media (prefers-reduced-motion:reduce)'))->toBeTrue(
        'Falta la guardia de movimiento reducido: dentro de un panel Filament --muni-dur se queda '.
        'en 160 ms (DESIGN §7), así que la guardia tiene que viajar con el componente.'
    );
});

it('los controles llegan al área táctil del mesón', function () {
    $css = bloqueoCss();

    expect(substr_count($css, 'min-height:44px'))->toBeGreaterThanOrEqual(2,
        'Los controles no llegan a 44 px de alto. El mesón se atiende de pie y la ventanilla de '.
        'Licencias también: 44×44 es el mínimo de terreno.'
    );
});

it('el velo tapa de verdad: la ficha del vecino no se ve desde el mesón', function () {
    $css = bloqueoCss();

    expect((bool) preg_match('/\.muni-pb__velo\s*\{[^}]*background:var\(--muni-bg\)/', $css))->toBeTrue(
        'El velo no es el papel del tema: un tinte translúcido deja entrever el RUT y el domicilio '.
        'del vecino desde el otro lado del mesón (Ley 21.719).'
    );

    expect((bool) preg_match('/\.muni-pb__velo\s*\{[^}]*opacity:1/', $css))->toBeTrue(
        'El velo no es opaco. En `sesion-guardia` el velo de T=0 lo es por el mismo motivo.'
    );
});

/*
|--------------------------------------------------------------------------
| El banco de navegador
|--------------------------------------------------------------------------
|
| Se genera acá y no en un script suelto porque este es el único sitio del
| paquete donde Blade está arrancado (mismo patrón que `PantallaDeResultadoTest`
| y `GuardiaDeSesionTest`). Sale a `build/pantalla-bloqueo/`, que git ignora.
*/
it('genera el banco de navegador en build/pantalla-bloqueo/', function () {
    $dir = __DIR__.'/../build/pantalla-bloqueo';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $alpine = is_file(__DIR__.'/../node_modules/alpinejs/dist/cdn.min.js')
        ? (string) file_get_contents(__DIR__.'/../node_modules/alpinejs/dist/cdn.min.js')
        : null;
    $focus = is_file(__DIR__.'/../node_modules/@alpinejs/focus/dist/cdn.min.js')
        ? (string) file_get_contents(__DIR__.'/../node_modules/@alpinejs/focus/dist/cdn.min.js')
        : null;

    expect($alpine !== null && $focus !== null)->toBeTrue(
        'Falta node_modules/alpinejs o @alpinejs/focus: corre `npm install`. Sin el plugin Focus '.
        'no hay x-trap y el banco mediría una capa que no atrapa nada.'
    );

    $capa = Blade::render(
        '<x-muni::pantalla-bloqueo nombre="María Fernanda Soto" cargo="Atención al Vecino · Mesón 2" '
        .'accion="#desbloquear" otro-url="#salir" abierto />'
    );

    $capaConError = Blade::render(
        '<x-muni::pantalla-bloqueo nombre="María Fernanda Soto" cargo="Atención al Vecino · Mesón 2" '
        .'accion="#desbloquear" otro-url="#salir" abierto '
        .'error="La contraseña no coincide. Vuelve a intentarlo." />'
    );

    $inactividad = Blade::render(
        '<x-muni::pantalla-bloqueo nombre="María Fernanda Soto" cargo="Atención al Vecino · Mesón 2" '
        .'accion="#desbloquear" otro-url="#salir" :inactividad="1" />'
    );

    $pagina = Blade::render(
        '<x-muni::pantalla-bloqueo modo="pagina" nombre="María Fernanda Soto" '
        .'cargo="Atención al Vecino · Mesón 2" accion="#desbloquear" otro-url="#salir" />'
    );

    /* LA CONVIVENCIA CON `sesion-guardia`, que es el componente del mismo dominio y
       la misma pantalla: el mesón lleva los dos. Los dos teletransportan al <body>,
       los dos atrapan el foco con `x-trap.inert` y los dos pintan un velo fijo. La
       guardia va SEGUNDA y con la expiración ya pasada a propósito: así entra en T=0
       dentro de su propio `init()`, arma su trampa DESPUÉS de la del bloqueo y su
       raíz teleportada queda detrás en el <body>. Es el peor orden posible, y es el
       que hay que medir: con el mismo z-index ganaba el orden de inserción, y el
       `aria-hidden` que la guardia estampa en sus hermanos dejaba al diálogo del
       bloqueo visible pero mudo para el lector de pantalla. */
    $bloqueoParaConvivir = '<x-muni::pantalla-bloqueo nombre="María Fernanda Soto" '
        .'cargo="Atención al Vecino · Mesón 2" accion="#desbloquear" otro-url="#salir" abierto />';
    $guardiaTerminada = '<x-muni::sesion-guardia id="banco-guardia" expira-en="2020-01-01T12:00:00-03:00" '
        .'ingresar-url="#ingresar" salir-url="#salir" />';

    $conGuardia = Blade::render($bloqueoParaConvivir.$guardiaTerminada);

    /* EL MISMO PAR, AL REVÉS. Lo que se mide con las dos páginas es que el resultado NO
       dependa de en qué orden escribió el anfitrión los dos componentes: con el mismo
       z-index mandaba el orden de inserción en el <body>, que es el orden del marcado. */
    $conGuardiaAntes = Blade::render($guardiaTerminada.$bloqueoParaConvivir);

    $paginas = [
        'claro' => ['light', cssMuniUi(), false, $capa],
        'oscuro' => ['dark', cssMuniUi(), false, $capa],
        'panel-claro' => ['light', cssMuniUiFilament(), true, $capa],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true, $capa],
        'error' => ['light', cssMuniUi(), false, $capaConError],
        'inactividad' => ['light', cssMuniUi(), false, $inactividad],
        'pagina' => ['light', cssMuniUi(), false, $pagina],
        'con-guardia' => ['light', cssMuniUi(), false, $conGuardia],
        'con-guardia-antes' => ['light', cssMuniUi(), false, $conGuardiaAntes],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel, $cuerpoPantalla]) {
        $oscuro = $tema === 'dark';
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        /* El armazón no aporta ni un color propio: fondo, texto y el enlace de la
           ficha de atrás salen de los mismos tokens que lee el componente. Con el
           azul por defecto del navegador, ese enlace del andamiaje daba 2,05:1 en
           tema oscuro y la reja culpaba a la página entera por algo que no es del
           componente. */
        $armazon = 'body{margin:0;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'.relleno{padding:24px;max-width:60ch}.relleno a{color:var(--muni-text)}';

        /* Lo que queda DEBAJO del bloqueo: es lo que tiene que volverse inerte, y
           lleva datos del vecino a propósito, que es lo que no puede leerse ni con
           el teclado ni con un lector de pantalla mientras la capa está arriba. */
        $detras = '<div class="relleno"><h1>Ficha del vecino</h1>'
            .'<p id="pii">Juan Pérez Soto · 12.345.678-9 · Av. Bernardo O\'Higgins 456</p>'
            .'<button type="button" id="detras">Guardar ficha</button>'
            .'<a href="#otro" id="detras-enlace">Ver historial</a></div>';

        $script = '<script data-banco="alpine-focus">'.$focus.'</script>'
            .'<script data-banco="alpine-core">'.$alpine.'</script>';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .$detras.'<section class="fi-section">'.$cuerpoPantalla.'</section></main></div>'.$script.'</body>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$detras.$cuerpoPantalla.'</main>'.$script.'</body>';

        $documento = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco pantalla-bloqueo — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $documento);

        expect(str_contains($documento, 'muni-pb__panel'))->toBeTrue('El banco '.$nombre.' salió sin la pantalla.');
    }

    expect(str_contains((string) file_get_contents($dir.'/claro.html'), '.muni-pb__velo'))->toBeTrue(
        'El banco salió sin el bloque de estilos del componente: nada de lo que se mida sería suyo.'
    );

    /* LA TARJETA DE LA VITRINA, en su contexto real y en los dos temas.
       `tests/GeneraVitrinaTest.php` es un archivo compartido que este componente no toca:
       la entrada se entrega como dato para que la pegue quien integre. Para no entregarla a
       ciegas —la reja de la vitrina mide sobre DOS superficies, y el reparto par/impar de la
       rejilla decide cuál le toca a cada pieza— se reproduce aquí el mismo envoltorio, con
       la misma hoja y las mismas clases, y se emite DOS veces: una cae sobre
       `--muni-surface` y la otra sobre `--muni-surface-3`.
       Va en modo PÁGINA y sin autofoco, que es como se entrega: en capa, el velo opaco
       taparía la vitrina entera y la reja mediría un solo componente de cincuenta. No
       declara `data-vitrina-espera-alpine` porque la entrada no lleva ni una directiva de
       Alpine (la ficha: «ninguno si es página»). */
    $tarjeta = Blade::render(
        '<x-muni::pantalla-bloqueo modo="pagina" :autofoco="false" nombre="María Fernanda Soto" '
        .'cargo="Atención al Vecino · Mesón 2" accion="#desbloquear" otro-url="#salir" '
        .'error="La contraseña no coincide. Vuelve a intentarlo." />'
    );

    foreach (['vitrina-claro' => 'light', 'vitrina-oscuro' => 'dark'] as $nombre => $tema) {
        $piezas = '';
        foreach (['pantalla-bloqueo', 'pantalla-bloqueo (sobre la otra superficie)'] as $rotulo) {
            $piezas .= '<section class="v-pieza" data-pieza="'.$rotulo.'">'
                .'<h2 class="v-nombre">&lt;x-muni::pantalla-bloqueo&gt;</h2>'
                .'<div class="v-cuerpo">'.$tarjeta.'</div></section>';
        }

        $marco = 'body{margin:0;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'.v-grid{display:grid;gap:20px;padding:24px;grid-template-columns:repeat(auto-fill,minmax(340px,1fr))}'
            .'.v-pieza{background:var(--muni-surface);border:1px solid var(--muni-border);'
            .'border-radius:var(--muni-radius-lg);padding:16px}'
            .'.v-nombre{margin:0 0 12px;font-family:var(--muni-font-mono);font-size:12px;'
            .'color:var(--muni-muted);font-weight:600}'
            .'.v-pieza:nth-child(even) .v-cuerpo{background:var(--muni-surface-3);padding:12px;'
            .'border-radius:var(--muni-radius)}';

        file_put_contents($dir.'/'.$nombre.'.html',
            '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($tema === 'dark' ? ' class="dark"' : '')
            .' data-vitrina-hoja="muni-ui.css"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Tarjeta de vitrina — pantalla-bloqueo, tema '.($tema === 'dark' ? 'oscuro' : 'claro').'</title>'
            .'<style>'.cssMuniUi().'</style><style>'.$marco.'</style></head>'
            .'<body><main id="muni-contenido" tabindex="-1"><div class="v-grid">'.$piezas.'</div></main></body></html>'
        );
    }

    expect(str_contains((string) file_get_contents($dir.'/vitrina-oscuro.html'), 'muni-pb__panel'))->toBeTrue(
        'La tarjeta de vitrina salió sin el panel: no habría nada que medir con la reja.'
    );
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function bloqueoPythonDeLaReja(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

it('en Chromium y Firefox: el foco, la trampa, el fondo inerte y Escape', function () {
    $python = bloqueoPythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/pantalla-bloqueo.py').' '
        .escapeshellarg(__DIR__.'/../build/pantalla-bloqueo').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador del bloqueo falló:\n".implode("\n", $lineas));

    // Cuatro páginas de capa × dos navegadores: el foco tiene que caer en el campo.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'foco=clave'))))->toBe(8,
        "Los ocho recorridos de la capa tienen que enfocar el campo de contraseña:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'inerte=si'))))->toBe(8,
        "El contenido de atrás tiene que quedar inerte en los ocho recorridos:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'escape=sigue-bloqueado'))))->toBe(8,
        "Escape no puede cerrar el bloqueo en ninguno de los ocho recorridos:\n".implode("\n", $lineas));
});

/*
 * La entrada que se pega en `tests/GeneraVitrinaTest.php` y la línea de
 * `propsObligatorias()` de `tests/TodosRendericanTest.php` son archivos
 * compartidos que este componente no toca: se entregan como dato. Acá se
 * comprueba que lo entregado RENDERIZA, para que no llegue roto al que lo pega.
 */
it('la entrada de vitrina y la prop obligatoria rinden tal como se entregan', function () {
    $vitrina = '<x-muni::pantalla-bloqueo modo="pagina" :autofoco="false" nombre="María Fernanda Soto" '
        .'cargo="Atención al Vecino · Mesón 2" accion="#desbloquear" otro-url="#salir" '
        .'error="La contraseña no coincide. Vuelve a intentarlo." />';

    $html = Blade::render($vitrina);

    expect(str_contains($html, 'muni-pb__panel'))->toBeTrue('La entrada de vitrina no rinde el panel.');
    expect(str_contains($html, 'autofocus'))->toBeFalse(
        'La entrada de vitrina enfoca el campo: en una página con 50 componentes, el que enfoca '.
        'gana el foco y arrastra el scroll de la medición.'
    );

    /* `propsObligatorias()`: 'pantalla-bloqueo' => 'nombre="María Fernanda Soto"'.
       Se renderiza EXACTAMENTE como lo hace `TodosRendericanTest` —etiqueta de apertura con
       la prop entregada, contenido dentro y etiqueta de cierre—, que es el caso que revienta
       si la línea no se pega: `nombre` no tiene valor por defecto y el candado descubre los
       componentes por el glob del directorio, así que el archivo entra en la prueba el día
       que se crea. */
    $comoEnElCandado = Blade::render(
        '<x-muni::pantalla-bloqueo nombre="María Fernanda Soto">Contenido de prueba</x-muni::pantalla-bloqueo>'
    );

    expect(trim($comoEnElCandado))->not->toBe('', 'El componente rinde vacío con la sola prop obligatoria.');
    expect(str_contains($comoEnElCandado, 'muni-pb__panel'))
        ->toBeTrue('El componente no rinde con la sola prop obligatoria.');
});
