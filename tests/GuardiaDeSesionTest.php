<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA GUARDIA DE SESIÓN
|--------------------------------------------------------------------------
|
| `<x-muni::sesion-guardia>` avisa antes de que caduque la sesión, deja
| prorrogarla con un clic explícito y, al llegar a T=0, tapa la pantalla con
| una superposición OPACA: el formulario de ingreso de solicitud del mesón de
| Atención al Vecino y la ficha social de la credencial de discapacidad quedan
| con RUT y domicilio a la vista cuando el funcionario se levanta, y un 419 al
| volver perdía todo lo escrito. Lo que la ficha del GAP-ANALYSIS exige, tal
| como la corrigió el juez:
|
| 1. API PASIVA. Recibe `expira-en` (ISO absoluto del servidor), `renovar-url`,
|    `salir-url` y `avisar-a`. Nunca inventa una URL, nunca hace ping solo y
|    nunca prorroga sin clic: la prórroga es un POST con el token CSRF a una
|    ruta que pone el anfitrión. Sin esa ruta, avisa y bloquea; no prorroga.
| 2. `role="alertdialog"` DESDE EL SERVIDOR, sin mutarlo a los dos minutos:
|    cambiar el rol en caliente no se anuncia de forma fiable en NVDA ni en
|    VoiceOver. Los hitos (5 min, 1 min) se dicen por una región viva cortés
|    que nace vacía; el contador visible NO es región viva, o hablaría cada
|    segundo.
| 3. El contador nunca acumula ticks: recalcula `expira - ahora` en cada tick
|    y al volver la pestaña (`visibilitychange`).
| 4. Varias pestañas se coordinan por `BroadcastChannel`, con respaldo en el
|    evento `storage`, y todo dentro de try/catch: en modo privado o con el
|    almacenamiento bloqueado sigue funcionando como pestaña única.
| 5. Mientras el funcionario escribe, el aviso NO roba el foco: la trampa se
|    activa en el tramo final o al pulsar el aviso. Escape va en el elemento
|    (nunca `.window`), equivale a «Seguir conectado» antes de expirar y no
|    hace nada en la superposición de T=0.
| 6. La superposición de T=0 es opaca —`var(--muni-bg)`, sin backdrop-filter—:
|    ahí es donde se sostiene el argumento de la Ley 21.719.
| 7. Sin `renovar-url` el diálogo se puede descartar («Entendido»/Escape), y la
|    tira con el contador tiene que seguir a la vista hasta T=0: el revisor midió
|    el último minuto sin ningún aviso visible.
|
| Varias aserciones son de PRESENCIA en el fuente (BroadcastChannel, localStorage,
| los try, escape()): por sí solas no prueban comportamiento. Lo prueba el banco
| de navegador del final, que se SALTA sin `.venv-a11y` (en CI): ahí el candado
| real es solo textual, y se dice para que nadie lo tome por más.
|
| Las aserciones van con `expect(bool)->toBeTrue('mensaje')`: el segundo
| argumento de `toContain()` en Pest es otra aguja, no un mensaje.
*/

/** El `sesion-guardia.blade.php` crudo. */
function fuenteGuardia(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/sesion-guardia.blade.php');
}

/** La fuente sin comentarios Blade ni CSS: lo que de verdad se compila. */
function fuenteGuardiaSinComentarios(): string
{
    return (string) preg_replace(['#\{\{--.*?--\}\}#s', '#/\*.*?\*/#s'], '', fuenteGuardia());
}

/** El CSS del bloque de estilos del componente, sin comentarios. */
function cssGuardia(): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteGuardia(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/** Render con las props realistas del mesón, más lo que se quiera agregar. */
function htmlGuardia(string $atributos = ''): string
{
    return Blade::render(
        '<x-muni::sesion-guardia expira-en="2026-09-13T15:30:00-03:00" '
        .'renovar-url="/sesion/renovar" salir-url="/salir" '.$atributos.' />'
    );
}

/**
 * Los valores de los atributos de Alpine, decodificados como los ve el navegador.
 *
 * @return array<int, string>
 */
function expresionesAlpineGuardia(string $html): array
{
    preg_match_all('/\s(?:x-[a-z0-9.:-]+|@[a-z0-9.-]+|:[a-z0-9-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

it('declara role="alertdialog" desde el servidor y nunca lo muta en caliente', function () {
    $html = htmlGuardia();

    expect((bool) preg_match('/<div\b[^>]*\srole="alertdialog"[^>]*>/', $html))->toBeTrue(
        'El diálogo no nace con role="alertdialog" escrito en el HTML: un rol que se '.
        'cambia en caliente a los dos minutos no se anuncia de forma fiable en NVDA ni en VoiceOver.'
    );
    expect((bool) preg_match('/\s(?::role|x-bind:role)=/', $html))->toBeFalse(
        'El rol está atado con Alpine (`:role`): mutarlo en caliente es justo lo que el juez prohibió.'
    );
    expect(str_contains($html, 'aria-modal="true"'))->toBeTrue(
        'El diálogo no declara aria-modal="true".'
    );

    preg_match('/<div\b[^>]*\srole="alertdialog"[^>]*>/', $html, $dialogo);

    expect((bool) preg_match('/aria-labelledby="([^"]+)"/', $dialogo[0], $lab))->toBeTrue('El diálogo no tiene aria-labelledby.');
    expect((bool) preg_match('/aria-describedby="([^"]+)"/', $dialogo[0], $desc))->toBeTrue('El diálogo no tiene aria-describedby.');
    expect(str_contains($html, 'id="'.$lab[1].'"'))->toBeTrue("El aria-labelledby apunta a «{$lab[1]}», que no existe.");
    expect(str_contains($html, 'id="'.$desc[1].'"'))->toBeTrue("El aria-describedby apunta a «{$desc[1]}», que no existe.");
});

it('los ids salen de la prop id, no de uniqid(), y respetan el del consumidor', function () {
    expect(str_contains(fuenteGuardiaSinComentarios(), 'uniqid('))->toBeFalse(
        'La guardia arma sus ids con uniqid(): cambian en cada render y bajo Livewire el '.
        'aria-labelledby queda apuntando a un id que ya no existe.'
    );

    expect(htmlGuardia())->toBe(htmlGuardia(), 'Dos renders con las mismas props dan HTML distinto.');

    $html = htmlGuardia('id="guardia-mesón"');

    preg_match('/<div\b[^>]*\srole="alertdialog"[^>]*aria-labelledby="([^"]+)"/', $html, $m);

    expect(str_starts_with($m[1] ?? '', 'guardia-mesón'))->toBeTrue(
        'La guardia ignora el id que le pasó el consumidor y se inventa otro: el título quedó «'.($m[1] ?? '').'».'
    );
});

it('los hitos se anuncian por una región viva cortés que nace vacía; el contador visible no es región viva', function () {
    $html = htmlGuardia();

    expect((bool) preg_match('/<p\b[^>]*\brole="status"[^>]*\baria-live="polite"[^>]*\baria-atomic="true"[^>]*>\s*<\/p>/', $html)
        || (bool) preg_match('/<p\b[^>]*\baria-live="polite"[^>]*\brole="status"[^>]*\baria-atomic="true"[^>]*>\s*<\/p>/', $html))->toBeTrue(
            'No hay una región viva cortés, atómica y VACÍA desde el primer render: una región que '.
            'nace con texto se anuncia sola al cargar, y una que se inyecta completa no se anuncia en NVDA.'
        );

    // El contador que cambia cada segundo NO puede estar dentro de una región viva:
    // el lector hablaría sesenta veces por minuto.
    preg_match_all('/<[a-z]+\b[^>]*x-text="tiempo"[^>]*>/', $html, $contadores);
    expect(count($contadores[0]))->toBeGreaterThan(0, 'No hay ningún contador visible (`x-text="tiempo"`).');

    foreach ($contadores[0] as $contador) {
        expect(str_contains($contador, 'aria-live'))->toBeFalse(
            'El contador visible lleva aria-live: se anunciaría cada segundo en vez de solo en los hitos.'
        );
    }

    // Y los hitos son los que pidió el juez: cinco minutos y un minuto.
    $fuente = fuenteGuardiaSinComentarios();
    expect((bool) preg_match('/\b300\b/', $fuente) && (bool) preg_match('/\b60\b/', $fuente))->toBeTrue(
        'Los hitos de 5 minutos (300 s) y 1 minuto (60 s) no están en el componente.'
    );
});

it('la prórroga es un POST explícito con token CSRF a la ruta del anfitrión; sin ruta, solo avisa y bloquea', function () {
    $html = htmlGuardia();

    expect((bool) preg_match('/<form\b[^>]*\bmethod="post"[^>]*\baction="\/sesion\/renovar"[^>]*>/i', $html))->toBeTrue(
        'No hay un <form method="post"> apuntando a renovar-url: la prórroga tiene que ser un POST '.
        'a la ruta que pone el anfitrión, nunca una URL inventada por el paquete.'
    );
    expect(str_contains($html, 'name="_token"'))->toBeTrue(
        'El formulario de prórroga no lleva el campo CSRF (@csrf).'
    );
    expect((bool) preg_match('/<form\b[^>]*\bmethod="post"[^>]*\baction="\/salir"[^>]*>/i', $html))->toBeTrue(
        'No hay un <form method="post"> apuntando a salir-url: cerrar sesión desde el aviso es el '.
        'acto explícito de minimización que pide la Ley 21.719.'
    );

    // Sin renovar-url NO aparece «Seguir conectado» ni ningún action que el
    // paquete se haya inventado.
    $sinRuta = Blade::render('<x-muni::sesion-guardia expira-en="2026-09-13T15:30:00-03:00" />');

    expect(str_contains($sinRuta, 'Seguir conectado'))->toBeFalse(
        'Sin renovar-url el componente sigue ofreciendo «Seguir conectado»: no hay ruta a la que pegar.'
    );
    expect((bool) preg_match('/\baction="[^"]+"/', $sinRuta))->toBeFalse(
        'Sin renovar-url ni salir-url el componente emite un <form action=…>: se inventó una URL.'
    );
    expect(str_contains($sinRuta, 'role="alertdialog"'))->toBeTrue(
        'Sin renovar-url el componente dejó de avisar y bloquear: tiene que hacerlo igual, solo que sin prorrogar.'
    );

    // Y el fetch de la prórroga vive SOLO dentro del método que dispara el clic.
    $fuente = fuenteGuardiaSinComentarios();
    expect(substr_count($fuente, 'fetch('))->toBe(1, 'Hay más de un fetch(): alguno no nace de un clic.');
    expect((bool) preg_match('/setInterval\([^)]*fetch/s', $fuente))->toBeFalse(
        'Hay un fetch dentro de un setInterval: un ping automático convierte la sesión en eterna.'
    );
});

it('el contador recalcula contra la marca del servidor y no acumula ticks', function () {
    $fuente = fuenteGuardiaSinComentarios();

    expect(str_contains($fuente, 'visibilitychange'))->toBeTrue(
        'No escucha `visibilitychange`: un setInterval en pestaña de fondo se desincroniza y el '.
        'aviso llega tarde o no llega.'
    );
    expect((bool) preg_match('/restante\s*(?:--|-=|=\s*(?:this\.)?restante\s*-)/', $fuente))->toBeFalse(
        'El contador se decrementa a mano (`restante--`): acumula el error de cada tick en vez de '.
        'recalcular contra la marca de tiempo del servidor.'
    );
    expect((bool) preg_match('/expiraMs\s*-\s*(?:this\.)?ahoraMs\(\)/', $fuente))->toBeTrue(
        'No hay un `expiraMs - ahoraMs()`: el tiempo restante tiene que salir siempre de la marca '.
        'absoluta del servidor menos el ahora.'
    );
});

it('coordina varias pestañas con BroadcastChannel y respaldo en storage, y sobrevive sin almacenamiento', function () {
    $fuente = fuenteGuardiaSinComentarios();

    expect(str_contains($fuente, 'BroadcastChannel'))->toBeTrue('No usa BroadcastChannel para coordinar pestañas.');
    expect(str_contains($fuente, "'storage'"))->toBeTrue('No tiene el respaldo por evento `storage` de localStorage.');
    expect(str_contains($fuente, 'localStorage'))->toBeTrue('El respaldo no escribe en localStorage.');

    // Cada acceso al canal o al almacenamiento va dentro de un try: en modo
    // privado `localStorage.setItem` lanza, y el componente tiene que seguir
    // como pestaña única en vez de morir.
    expect(substr_count($fuente, 'try {'))->toBeGreaterThanOrEqual(3,
        'Hay menos de tres bloques try: el canal, la escritura y la lectura del almacenamiento tienen '.
        'que ir cada uno dentro de uno, o en modo privado el componente muere.'
    );
});

it('Escape va en el elemento, equivale a seguir conectado y no hace nada en T=0', function () {
    $html = htmlGuardia();
    $fuente = fuenteGuardiaSinComentarios();

    expect(str_contains($html, 'keydown.escape.window'))->toBeFalse(
        'Escape se escucha en window: choca con modales y drawers del propio paquete. Va en el diálogo.'
    );
    expect((bool) preg_match('/<div\b[^>]*\srole="alertdialog"[^>]*@keydown\.escape[^=]*="[^"]*escape\(\)/', $html))->toBeTrue(
        'El diálogo no maneja Escape en su propio elemento.'
    );

    // escape(): primero descarta T=0, después renueva si hay ruta, si no cierra.
    expect((bool) preg_match('/escape\(\)\s*\{\s*if\s*\(this\.fase\s*===\s*\'terminada\'\)\s*return;\s*this\.renovarUrl\s*\?\s*this\.renovar\(\)\s*:\s*this\.cerrar\(\);/s', $fuente))->toBeTrue(
        'escape() no hace lo pactado: nada en la superposición de T=0, «Seguir conectado» si hay ruta, cerrar si no.'
    );
});

it('el aviso temprano no atrapa el foco; la trampa se arma en el tramo final o al pulsar el aviso', function () {
    $html = htmlGuardia();

    // La trampa depende del estado `modal`, que es diálogo abierto o T=0; el
    // aviso temprano se oculta con x-show en cuanto hay modal, así que nunca
    // es una parada dentro de la trampa.
    expect((bool) preg_match('/x-trap\.inert\.noscroll\.noreturn="modal"/', $html))->toBeTrue(
        'No hay `x-trap.inert.noscroll.noreturn` atado al estado modal.'
    );

    preg_match('/<div\b[^>]*\srole="alertdialog"[^>]*>/', $html, $m, PREG_OFFSET_CAPTURE);
    $inicioDialogo = $m[0][1];
    $posAviso = strpos($html, 'muni-sg__aviso"');

    expect($posAviso !== false && $posAviso < $inicioDialogo)->toBeTrue(
        'El aviso temprano no existe o vive DENTRO del diálogo atrapado: robaría el foco mientras el '.
        'funcionario escribe.'
    );

    // Y el aviso es pulsable: abrir el diálogo desde ahí es lo que arma la trampa a voluntad.
    expect((bool) preg_match('/<button\b[^>]*\bmuni-sg__aviso-btn\b[^>]*@click="abrir\(\)"/', $html))->toBeTrue(
        'El aviso no se puede pulsar para abrir el diálogo.'
    );
});

it('la región viva vive DENTRO del elemento atrapado: x-trap.inert no la puede esconder', function () {
    // El plugin Focus con `.inert` pone aria-hidden="true" en TODOS los hermanos
    // del elemento atrapado, subiendo hasta <body>. Si la región viva queda fuera
    // (en el div de x-data, hermano del contenido teleportado), el hito de un
    // minuto, «Sesión prorrogada» y sobre todo «Tu sesión terminó» —que llega con
    // el diálogo YA abierto y sin cambio de rol— se dicen dentro de un subárbol
    // aria-hidden, o sea no se dicen. Por eso la trampa va en la raíz teleportada
    // y la región viva es hija suya; el aviso temprano, también hijo, se oculta
    // con x-show en cuanto hay modal y no es una parada de la trampa.
    $html = htmlGuardia();

    expect((bool) preg_match('/<template x-teleport="body">\s*<div\b([^>]*)>/', $html, $raiz))->toBeTrue(
        'No hay una raíz teleportada al body.'
    );
    expect(str_contains($raiz[1], 'x-trap.inert.noscroll.noreturn="modal"'))->toBeTrue(
        'La trampa no está en la raíz teleportada: está más adentro y deja a la región viva como hermana, que es lo que .inert esconde.'
    );

    $inicioRaiz = strpos($html, $raiz[0]);
    $region = strpos($html, 'role="status"');

    expect($region !== false && $region > $inicioRaiz)->toBeTrue(
        'La región viva no está dentro de la raíz teleportada: con el diálogo abierto queda aria-hidden.'
    );

    // Y la raíz teleportada es una envoltura sin x-show: si se ocultara, la región
    // viva desaparecería del árbol de accesibilidad en el tramo del aviso.
    expect(str_contains($raiz[1], 'x-show'))->toBeFalse('La raíz teleportada lleva x-show: la región viva se iría del árbol con ella.');

    // `.noreturn` porque el componente devuelve el foco por su cuenta: la trampa
    // devolvería al elemento enfocado al activarse, y si se abrió desde el aviso
    // ese elemento ya no está visible al cerrar. El componente recuerda de dónde
    // venía el funcionario (`retorno`) y lo enfoca al cerrar.
    $fuente = fuenteGuardiaSinComentarios();
    expect(str_contains($fuente, 'retorno'))->toBeTrue('El componente no recuerda desde dónde se abrió (`retorno`) para devolver el foco.');

    // Y ningún foco se pide en un $nextTick desnudo: Alpine MUESTRA con x-show en
    // un requestAnimationFrame posterior, y $nextTick corre antes (setTimeout 0),
    // así que `$refs.ingresar.focus()` en T=0 caía sobre un botón aún display:none
    // y el foco se iba al body. Se midió en Chromium y en Firefox. El foco va por
    // `enfocar()`, que reintenta por rAF hasta que el destino sea el activo.
    expect((bool) preg_match('/\$nextTick\([^;]*\.focus\(\)/s', $fuente))->toBeFalse(
        'Hay un .focus() dentro de $nextTick: llega antes de que x-show muestre el botón y el foco se pierde en el body.'
    );
    expect((bool) preg_match('/enfocar\([^)]*\)\s*\{.*requestAnimationFrame/s', $fuente))->toBeTrue(
        'No hay un enfocar() que reintente por requestAnimationFrame hasta que el destino sea enfocable.'
    );
});

it('no usa $refs: Alpine los cachea en el primer acceso y una carga en el último minuto los dejaría vacíos', function () {
    // init() corre antes de que exista el clon teleportado. Si la página carga ya
    // en el tramo urgente, tick() → abrir() ocurre en init(); un `$refs` leído ahí
    // se cachea sin ninguna referencia y así queda para toda la sesión: sin retorno
    // de foco y sin token del formulario. Los destinos se buscan por el DOM desde
    // el id (`data-muni-foco`). Lo cubre el escenario «carga tardía» del navegador.
    $fuente = fuenteGuardiaSinComentarios();

    expect(str_contains($fuente, '$refs'))->toBeFalse('El componente lee $refs.');
    expect(str_contains($fuente, 'x-ref='))->toBeFalse('El componente declara x-ref.');
    expect(substr_count(htmlGuardia(), 'data-muni-foco="'))->toBeGreaterThanOrEqual(2,
        'Los destinos de foco («Seguir conectado», «Ingresar de nuevo») no llevan data-muni-foco.'
    );
});

it('mientras prorroga, el botón queda aria-disabled y no disabled: un botón disabled suelta el foco', function () {
    // Medido: Chromium mueve el foco al body cuando el botón enfocado pasa a
    // `disabled` (Firefox lo conserva). Con eso, tras la prórroga nadie sabe a
    // dónde devolver el foco, y tras un error el funcionario queda en el body:
    // Escape ya no llega al diálogo. `aria-disabled` mantiene el botón enfocable
    // y la guarda de `ocupado` en renovar() evita el doble envío.
    $html = htmlGuardia();

    expect((bool) preg_match('/:disabled="ocupado"/', $html))->toBeFalse(
        'El botón de prórroga usa :disabled="ocupado": en Chromium suelta el foco al body.'
    );
    expect(substr_count($html, ':aria-disabled="ocupado"'))->toBeGreaterThanOrEqual(2,
        'Los botones «Seguir conectado» (tira y diálogo) no marcan aria-disabled mientras prorrogan.'
    );
    expect((bool) preg_match('/renovar\(\)\s*\{\s*if\s*\([^)]*this\.ocupado/s', fuenteGuardiaSinComentarios()))->toBeTrue(
        'renovar() no corta cuando ya hay una prórroga en curso: sin disabled, la guarda es lo único que evita el doble envío.'
    );
});

it('en T=0 la superposición es opaca, con el fondo del tema y sin backdrop-filter', function () {
    $css = cssGuardia();

    expect((bool) preg_match('/\.muni-sg__velo\s*\{([^}]*)\}/', $css, $velo))->toBeTrue('No existe la regla `.muni-sg__velo`.');
    expect(str_contains($velo[1], 'background:var(--muni-bg)'))->toBeTrue(
        'El velo no usa `var(--muni-bg)`: sobre el formulario tiene que quedar el papel del tema, no un tinte.'
    );
    expect((bool) preg_match('/\.muni-sg__capa--opaca\s+\.muni-sg__velo\s*\{([^}]*)\}/', $css, $opaco))->toBeTrue(
        'No existe la regla del velo opaco de T=0.'
    );
    expect((bool) preg_match('/opacity\s*:\s*1\b/', $opaco[1]))->toBeTrue(
        'En T=0 el velo no llega a opacidad 1: el RUT y el domicilio siguen a la vista del público del mesón.'
    );
    expect(str_contains($css, 'backdrop-filter'))->toBeFalse(
        'El velo usa backdrop-filter: un desenfoque traslúcido deja los datos igual de visibles.'
    );

    // El texto de T=0 está en el HTML del servidor, no lo pinta Alpine.
    expect(str_contains(htmlGuardia(), 'Sesión terminada'))->toBeTrue('Falta el texto «Sesión terminada» del bloqueo.');
    expect(str_contains(htmlGuardia(), 'Ingresar de nuevo'))->toBeTrue('Falta la salida «Ingresar de nuevo» del bloqueo.');
});

it('ninguna URL del anfitrión entra en una expresión de Alpine sin pasar por @js', function () {
    // Una URL con apóstrofo dentro de una cadena de comillas simples tumba el
    // Alpine de la página entera (el caso que cerró file-dropzone).
    $html = Blade::render(
        '<x-muni::sesion-guardia :expira-en="$e" :renovar-url="$r" :salir-url="$s" :ingresar-url="$i" />',
        ['e' => '2026-09-13T15:30:00-03:00', 'r' => "/sesion/renovar?x=o'brien", 's' => "/salir?x=o'brien", 'i' => "/ingresar?x=o'brien"]
    );

    $rotas = [];

    foreach (expresionesAlpineGuardia($html) as $expr) {
        $limpia = str_replace("\\'", '', $expr);

        if (substr_count($limpia, "'") % 2 !== 0) {
            $rotas[] = $expr;
        }
    }

    expect($rotas)->toBe([], 'Estas expresiones de Alpine quedan descuadradas: '.implode(' | ', $rotas));
    expect(str_contains(fuenteGuardiaSinComentarios(), '@js('))->toBeTrue('Los valores del anfitrión no viajan por @js().');

    // Y ninguna expresión se corta por una comilla doble escrita dentro del
    // atributo: pasó con un selector `[data-muni-foco="…"]` dentro de x-data, el
    // atributo terminó ahí y Alpine no pudo leer ni una propiedad. Con el
    // atributo cortado las llaves quedan desparejas.
    $truncadas = array_values(array_filter(
        expresionesAlpineGuardia($html),
        fn (string $expr) => substr_count($expr, '{') !== substr_count($expr, '}')
    ));

    expect($truncadas)->toBe([], 'Estas expresiones de Alpine quedan cortadas (llaves desparejas): '.implode(' | ', array_map(fn ($e) => substr($e, 0, 60), $truncadas)));
});

it('no consulta request(), Auth, Gate ni permisos: recibe todo resuelto por el anfitrión', function () {
    $fuente = fuenteGuardiaSinComentarios();

    expect((bool) preg_match('/\b(?:request\(|Auth::|auth\(|Gate::|can\(|session\()/', $fuente))->toBeFalse(
        'El componente consulta el request, la sesión o los permisos por su cuenta: todo llega ya resuelto por props.'
    );
});

it('no escribe un color literal, respeta el movimiento reducido y el foco es un outline', function () {
    $css = cssGuardia();

    preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/', $css, $m);
    $literales = array_values(array_filter($m[0], fn (string $c) => strtolower($c) !== '#767676'));

    expect($literales)->toBe([], 'La guardia escribe colores literales sin contraparte oscura: '.implode(', ', $literales));

    expect((bool) preg_match('/:focus-visible\s*\{[^}]*outline\s*:\s*3px\s+solid\s+var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/', $css))->toBeTrue(
        'El foco no dibuja el outline del sistema con su cadena de respaldo: dentro de Filament la sombra se pierde.'
    );
    expect((bool) preg_match('/transition\s*:[^;]*\d+m?s/', $css))->toBeFalse(
        'Hay una transición con duración literal: ignora prefers-reduced-motion. Va var(--muni-dur).'
    );

    // Cada var(--muni-*) que lee existe en LAS DOS hojas: dentro de un panel solo
    // se carga muni-ui-filament.css (DESIGN §7).
    preg_match_all('/var\((--muni-[a-z0-9-]+)/', fuenteGuardia(), $tokens);

    foreach (array_unique($tokens[1]) as $token) {
        expect(str_contains(cssMuniUi(), $token.':'))->toBeTrue("«{$token}» no está declarado en muni-ui.css.");
        expect(str_contains(cssMuniUiFilament(), $token.':'))->toBeTrue("«{$token}» no está declarado en muni-ui-filament.css.");
    }
});

it('los botones tienen 44 px de área táctil: el mesón y la tablet de terreno', function () {
    $css = cssGuardia();

    expect((bool) preg_match('/\.muni-sg__btn\s*\{([^}]*)\}/', $css, $btn))->toBeTrue('No existe la regla `.muni-sg__btn`.');
    expect((bool) preg_match('/min-height\s*:\s*44px/', $btn[1]))->toBeTrue('Los botones no llegan a 44 px de alto.');
    expect((bool) preg_match('/\.muni-sg__aviso-btn\s*\{([^}]*)\}/', $css, $aviso))->toBeTrue('No existe la regla `.muni-sg__aviso-btn`.');
    expect((bool) preg_match('/min-height\s*:\s*44px/', $aviso[1]))->toBeTrue('El aviso pulsable no llega a 44 px de alto.');
});

it('las props públicas son las que pactó el juez, más los umbrales con valor por defecto', function () {
    $html = htmlGuardia('ingresar-url="/ingreso" :avisar-a="600" :dialogo-a="90" ahora="2026-09-13T15:00:00-03:00" :duracion="7200"');
    $plano = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

    expect(str_contains($plano, '2026-09-13T15:30:00-03:00'))->toBeTrue('La prop `expira-en` no llega al componente.');
    expect(str_contains($plano, '2026-09-13T15:00:00-03:00'))->toBeTrue('La prop `ahora` no llega al componente.');
    expect((bool) preg_match('/avisarA\s*:\s*600\b/', $plano))->toBeTrue('La prop `avisar-a` no llega al componente.');
    expect((bool) preg_match('/dialogoA\s*:\s*90\b/', $plano))->toBeTrue('La prop `dialogo-a` no llega al componente.');
    expect((bool) preg_match('/duracion\s*:\s*7200\b/', $plano))->toBeTrue('La prop `duracion` (SESSION_LIFETIME en segundos) no llega al componente.');
    expect(str_contains($html, 'href="/ingreso"'))->toBeTrue('La prop `ingresar-url` no sale como enlace del bloqueo.');

    // Por defecto: aviso a 5 minutos, diálogo al minuto y sin duración conocida.
    $porDefecto = html_entity_decode(htmlGuardia(), ENT_QUOTES | ENT_HTML5);
    expect((bool) preg_match('/avisarA\s*:\s*300\b/', $porDefecto))->toBeTrue('El aviso por defecto no es a 300 s.');
    expect((bool) preg_match('/dialogoA\s*:\s*60\b/', $porDefecto))->toBeTrue('El diálogo por defecto no es a 60 s.');
    expect((bool) preg_match('/duracion\s*:\s*0\b/', $porDefecto))->toBeTrue('Sin la prop `duracion` el componente no parte con 0 (lo que restaba al cargar).');
});

it('un 204 sin cuerpo concede ahora + duracion si el anfitrión la pasó; si no, lo que restaba al cargar', function () {
    // El contrato dice «204 o {expiraEn}», y no son equivalentes sin esto: con
    // una carga tardía a T-60 s, un 204 solo «concedía» 60 s más y el diálogo
    // volvía a abrirse enseguida. Lo mide el escenario «204» del navegador.
    $fuente = fuenteGuardiaSinComentarios();

    expect((bool) preg_match('/this\.duracion\s*>\s*0\s*\?\s*this\.duracion\s*\*\s*1000\s*:\s*this\.duracionMs/', $fuente))->toBeTrue(
        'renovar() no usa `duracion` (segundos) para el 204 sin cuerpo, con `duracionMs` (lo que restaba al cargar) de respaldo.'
    );
});

it('la tira sigue a la vista si el funcionario descarta el diálogo en el tramo urgente', function () {
    // Reproducido en Chromium por el revisor: sin renovar-url, «Entendido» o
    // Escape llaman a cerrar() y dejan descartado=true con la fase en 'urgente';
    // la tira solo se mostraba con `fase === 'aviso'`, así que el último minuto
    // transcurría sin contador ni aviso visible hasta el velo opaco. Lo mide el
    // escenario «sin ruta» del navegador; acá se vigila la condición.
    $html = htmlGuardia();

    expect((bool) preg_match('/<div\b[^>]*\bmuni-sg__aviso\b[^>]*\bx-show="([^"]*)"/', $html, $m))->toBeTrue('La tira no tiene x-show.');

    $condicion = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);

    expect(str_contains($condicion, "=== 'aviso'"))->toBeFalse(
        'La tira solo se muestra en la fase aviso: descartado el diálogo en urgente, el último minuto queda sin aviso.'
    );
    expect(str_contains($condicion, "fase !== 'activa'") && (bool) preg_match('/!\s*modal\b/', $condicion))->toBeTrue(
        "La tira tiene que mostrarse en toda fase que no sea activa mientras no haya modal; hoy: «{$condicion}»."
    );
});

/*
 * El banco de navegador. Se genera acá y no en un script suelto porque este es
 * el único sitio del paquete con Blade arrancado (mismo criterio que
 * `GeneraVitrinaTest`). Sale a `build/sesion-guardia/`, ignorado por git, y lo
 * abre Playwright (`.venv-a11y`) servido por HTTP —BroadcastChannel no cruza
 * orígenes `file://`— para recorrer las tres fases con el reloj falso.
 */
it('genera el banco de navegador en build/sesion-guardia/', function () {
    $alpine = fuenteDeAlpine();
    $focus = fuenteDeFocus();
    $dir = __DIR__.'/../build/sesion-guardia';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $css = cssMuniUi();

    // `duracion` es SESSION_LIFETIME en segundos (120 min en los .env.example de
    // discapacidad y atencionvecino): con ella un 204 sin cuerpo concede ahora + 2 h.
    // `sin-ruta.html` no lleva renovar-url: es el único camino por el que el
    // funcionario puede descartar el diálogo, y donde estaba el defecto del tramo
    // urgente sin aviso.
    $paginas = [
        'claro' => ['light', '<x-muni::sesion-guardia id="banco" expira-en="2030-01-01T12:00:00Z" renovar-url="/renovar" salir-url="/salir" duracion="7200" />'],
        'oscuro' => ['dark', '<x-muni::sesion-guardia id="banco" expira-en="2030-01-01T12:00:00Z" renovar-url="/renovar" salir-url="/salir" duracion="7200" />'],
        'sin-ruta' => ['light', '<x-muni::sesion-guardia id="banco" expira-en="2030-01-01T12:00:00Z" salir-url="/salir" />'],
    ];

    foreach ($paginas as $nombre => [$tema, $blade]) {
        $componente = Blade::render($blade);

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($tema === 'dark' ? ' class="dark"' : '').'>'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco sesion-guardia — '.$nombre.'</title>'
            .'<style>'.$css.' body{margin:0;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans);padding:24px}'
            .'label{display:block;margin:12px 0 4px} input{padding:8px;font:inherit}</style></head>'
            .'<body><main id="muni-contenido" tabindex="-1"><h1>Ficha social — Ana Soto Miranda</h1>'
            .'<form><label for="rut">RUT</label><input id="rut" value="12.345.678-9">'
            .'<label for="dom">Domicilio</label><input id="dom" value="Calle Uno 100, Graneros"></form></main>'
            .$componente
            .($focus !== null ? '<script>'.$focus.'</script>' : '')
            .($alpine !== null ? '<script>'.$alpine.'</script>' : '')
            .'</body></html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    expect(is_file($dir.'/claro.html') && is_file($dir.'/oscuro.html') && is_file($dir.'/sin-ruta.html'))->toBeTrue('No se escribió el banco.');
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDeLaRejaGuardia(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que un test de Blade no puede ver, medido en Chromium y en Firefox sobre el
 * banco con el reloj falso de Playwright: que la tira aparece a T-5 min sin
 * robar el foco y tras UN solo tick, que el diálogo atrapa Tab/Shift+Tab, que
 * Escape prorroga y el foco VUELVE al campo, que el error de un 500 se ve y se
 * dice, que T=0 tapa la ficha con el color de --muni-bg, que dos pestañas se
 * bloquean entre sí por BroadcastChannel y por `storage`, y que sin ninguno de
 * los dos la guardia sigue sola. Tres de esas cosas fallaron en la primera
 * versión y ningún test de texto lo habría dicho. Se SALTA —no se finge— cuando
 * no está `.venv-a11y`, que en CI no se instala.
 */
it('en Chromium y Firefox: las tres fases con reloj falso, el foco vuelve al campo, T=0 tapa la ficha y las pestañas se coordinan', function () {
    $python = pythonDeLaRejaGuardia();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/guardia-de-sesion.py').' '
        .escapeshellarg(__DIR__.'/../build/sesion-guardia').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de <x-muni::sesion-guardia> falló:\n".implode("\n", $lineas));

    foreach (['chromium claro', 'chromium oscuro', 'firefox claro', 'firefox oscuro'] as $pagina) {
        expect(count(array_filter($lineas, fn (string $l) => str_starts_with($l, $pagina) && str_contains($l, 'T=0='))))->toBe(1,
            "Falta el resumen de {$pagina}:\n".implode("\n", $lineas));
    }
});
