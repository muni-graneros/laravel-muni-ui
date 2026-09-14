<?php

use Illuminate\Support\Facades\Blade;

/*
 * EL RÓTULO DEL ATAJO SALE DE LA MISMA PROP QUE LO ESCUCHA.
 *
 * Ficha `kbd` de docs/GAP-ANALYSIS.md, con la corrección del juez: no hay un
 * componente <x-muni::kbd>. Un rótulo escrito a mano en la vista («Ctrl K») se
 * desincroniza el día que alguien pasa `hotkey="p"` a la paleta, y el funcionario
 * del mesón teclea un atajo que no existe. La sincronía vive donde vive el dato:
 * `command-palette` pinta su propio disparador con la tecla que lee de `hotkey`,
 * y la clase `.muni-kbd` viaja en su `@once`, como `.muni-num` en data-table.
 *
 * Las aserciones van con `expect(bool)->toBeTrue('mensaje')`: el segundo argumento
 * de `toContain()` en Pest es otra aguja, no un mensaje.
 */

/** La paleta con los atributos que se le quieran pasar, sin `trigger` propio. */
function paletaSinDisparador(string $atributos = ''): string
{
    return Blade::render("<x-muni::command-palette :items=\"[]\" {$atributos} />");
}

/** El fuente del componente sin comentarios CSS ni de Blade: para grepear reglas. */
function fuenteDeLaPaleta(): string
{
    $fuente = (string) file_get_contents(__DIR__.'/../resources/views/components/command-palette.blade.php');
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** Solo el bloque <style> del componente, sin comentarios. */
function estiloDeLaPaleta(): string
{
    expect((bool) preg_match('#<style>(.*?)</style>#s', fuenteDeLaPaleta(), $m))->toBeTrue(
        'La paleta no tiene bloque <style>.'
    );

    return $m[1];
}

/** El <button> del disparador por defecto, entero (etiqueta de apertura + contenido). */
function disparadorPorDefecto(string $html): string
{
    expect((bool) preg_match('#<button\b[^>]*class="[^"]*muni-cmdk__trigger[^"]*"[^>]*>.*?</button>#s', $html, $m))->toBeTrue(
        'Sin `trigger`, la paleta no pinta ningún disparador: el atajo no existe para quien no lo sabe de antes.'
    );

    return $m[0];
}

/*
|--------------------------------------------------------------------------
| 1. Sin `trigger`, la paleta trae su propio disparador con el atajo rotulado
|--------------------------------------------------------------------------
*/

it('sin trigger pinta un botón real que abre la paleta y rotula el atajo con <kbd>', function () {
    $boton = disparadorPorDefecto(paletaSinDisparador());

    expect((bool) preg_match('/<button\b[^>]*\btype="button"/', $boton))->toBeTrue(
        'El disparador por defecto no es type="button": dentro de un <form> enviaría el formulario.'
    );
    expect((bool) preg_match('/<button\b[^>]*@click="show\(\)"/', $boton))->toBeTrue(
        'El disparador por defecto no llama a show(): el clic no abre la paleta.'
    );
    expect(substr_count($boton, '<kbd class="muni-kbd">'))->toBeGreaterThanOrEqual(2,
        'El atajo no se rotula con <kbd class="muni-kbd">: texto real, no icono ni imagen.'
    );
});

it('el rótulo sale de la prop hotkey, la misma que escucha el manejador', function () {
    $html = paletaSinDisparador('hotkey="p"');
    $boton = disparadorPorDefecto($html);

    expect(str_contains($boton, '<kbd class="muni-kbd">P</kbd>'))->toBeTrue(
        'Con hotkey="p" el rótulo no dice «P»: el rótulo y el manejador no salen de la misma fuente.'
    );
    expect(str_contains($boton, '<kbd class="muni-kbd">K</kbd>'))->toBeFalse(
        'Con hotkey="p" el rótulo sigue diciendo «K»: el funcionario teclea un atajo que no existe.'
    );
    expect(str_contains($html, "==='p'"))->toBeTrue(
        'El manejador no compara contra la misma tecla que se rotula.'
    );
});

it('declara el atajo en aria-keyshortcuts con las dos combinaciones que el manejador acepta', function () {
    $boton = disparadorPorDefecto(paletaSinDisparador('hotkey="p"'));

    expect((bool) preg_match('/<button\b[^>]*aria-keyshortcuts="Control\+P Meta\+P"/', $boton))->toBeTrue(
        'El disparador no declara aria-keyshortcuts="Control+P Meta+P": el manejador acepta Ctrl y ⌘, el ARIA tiene que decir lo mismo.'
    );
});

/*
|--------------------------------------------------------------------------
| 2. Accesibilidad del símbolo: ⌘ oculto, texto real para el lector
|--------------------------------------------------------------------------
*/

/** XPath sobre un fragmento de HTML en UTF-8 (mismo arranque que en CargaAnunciadaTest). */
function xpathDeLaPaleta(string $html): DOMXPath
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

it('el símbolo ⌘ va aria-hidden y el nombre de la tecla se lee como texto', function () {
    $boton = disparadorPorDefecto(paletaSinDisparador());
    $xpath = xpathDeLaPaleta($boton);

    expect((bool) preg_match('/<kbd\b[^>]*aria-label/', $boton))->toBeFalse(
        '<kbd> no tiene rol ARIA: un aria-label encima no se anuncia de forma fiable. El texto va como contenido.'
    );

    // Todo <kbd> del disparador vive bajo un ancestro aria-hidden="true": el lector
    // no deletrea «C-T-R-L» ni se atraganta con el ⌘. Se comprueba por el árbol,
    // no por el orden del texto: el <svg aria-hidden> de más arriba no cuenta.
    $teclas = $xpath->query('//kbd');
    expect($teclas->length)->toBeGreaterThanOrEqual(4, 'El disparador no rotula las dos formas del atajo con <kbd>.');

    $teclasExpuestas = $xpath->query('//kbd[not(ancestor::*[@aria-hidden="true"])]');
    expect($teclasExpuestas->length)->toBe(0,
        'Hay un <kbd> fuera de un contenedor aria-hidden="true": el lector de pantalla lee «C-T-R-L» o se atraganta con el ⌘.'
    );

    $comando = $xpath->query('//kbd[contains(., "⌘")]');
    expect($comando->length)->toBeGreaterThanOrEqual(1, 'El símbolo ⌘ no va dentro de un <kbd>.');

    // Y el texto para el lector NO puede vivir bajo ese mismo aria-hidden, o se
    // esconde también. Los dos nombres van como contenido del <button>, así que
    // forman parte de su nombre accesible («Buscar o ir a… Control K»).
    foreach (['Control K', 'Comando K'] as $nombre) {
        $expuesto = $xpath->query('//button//*[normalize-space(.)="'.$nombre.'" and not(ancestor-or-self::*[@aria-hidden="true"])]');
        expect($expuesto->length)->toBeGreaterThanOrEqual(1,
            "No hay texto «{$nombre}» expuesto al lector de pantalla dentro del disparador (o quedó bajo aria-hidden)."
        );
    }
});

it('las dos formas del atajo vienen del servidor y Alpine solo elige cuál se ve', function () {
    $boton = disparadorPorDefecto(paletaSinDisparador());

    expect(str_contains($boton, 'Ctrl'))->toBeTrue('Falta la forma Ctrl del atajo.');
    expect(str_contains($boton, '⌘'))->toBeTrue('Falta la forma ⌘ del atajo.');

    // Ningún texto dentro de una expresión de Alpine (lección de file-dropzone):
    // el rótulo no se arma con x-text ni con cadenas en comillas simples.
    expect(str_contains($boton, 'x-text'))->toBeFalse(
        'El rótulo se arma con x-text: un apóstrofo o un `+fetch()+` en el texto entra en la expresión de Alpine.'
    );
    expect((bool) preg_match('/x-show="mac"/', $boton))->toBeTrue(
        'La forma ⌘ no se muestra por plataforma (x-show="mac").'
    );
    expect((bool) preg_match('/x-show="!\s*mac"/', $boton))->toBeTrue(
        'La forma Ctrl no se oculta en Mac (x-show="!mac").'
    );
    // La forma ⌘ nace oculta: sin Alpine, o antes de hidratar, se ve solo Ctrl, que
    // el manejador acepta también en Mac. Nunca las dos a la vez.
    expect((bool) preg_match('/<[a-z]+\b[^>]*x-show="mac"[^>]*style="[^"]*display:\s*none/', $boton))->toBeTrue(
        'La forma ⌘ no nace oculta: antes de hidratar (o sin JS) se ven Ctrl y ⌘ a la vez.'
    );
});

it('detecta la plataforma como pide la ficha: userAgentData con respaldo en navigator.platform', function () {
    $html = paletaSinDisparador();

    expect(str_contains($html, 'navigator.userAgentData?.platform ?? navigator.platform'))->toBeTrue(
        'La detección de Mac no usa `navigator.userAgentData?.platform ?? navigator.platform`.'
    );
});

/*
|--------------------------------------------------------------------------
| 3. `.muni-kbd` vive en el @once, plana y sin colores literales
|--------------------------------------------------------------------------
*/

it('define .muni-kbd en su @once y ningún <kbd> lleva estilo en línea', function () {
    $html = paletaSinDisparador();
    $estilo = estiloDeLaPaleta();

    expect((bool) preg_match('/\.muni-kbd\s*\{([^}]*)\}/', $estilo, $m))->toBeTrue(
        'No hay regla `.muni-kbd` en el bloque <style> de la paleta.'
    );
    expect((bool) preg_match('/<kbd\b[^>]*\bstyle=/', $html))->toBeFalse(
        'Queda un <kbd> con estilo en línea: la ficha pide reemplazarlo por la clase.'
    );
    expect(str_contains($html, '<kbd class="muni-kbd" aria-hidden="true">esc</kbd>'))->toBeTrue(
        'La tecla «esc» del cuadro de búsqueda no usa la clase: duplica los valores.'
    );

    $regla = $m[1];

    expect((bool) preg_match('/border\s*:\s*1px\s+solid\s+var\(--muni-border\)/', $regla))->toBeTrue(
        '`.muni-kbd` no lleva borde de 1px var(--muni-border).'
    );
    expect((bool) preg_match('/box-shadow\s*:\s*(?!none)/', $regla))->toBeFalse(
        '`.muni-kbd` arrastra una sombra: la ficha pide tecla plana, sin el relieve de daisyUI.'
    );
    expect(str_contains($regla, 'var(--muni-font-mono)'))->toBeTrue(
        '`.muni-kbd` no usa la mono del sistema: las teclas van en mono como los RUT (DESIGN §9).'
    );
    expect((bool) preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $regla))->toBeFalse(
        '`.muni-kbd` escribe un color literal: solo tokens --muni-*.'
    );
});

it('la paleta no abre una hoja de impresión por su cuenta', function () {
    // La corrección del juez: si se quiere cubrir la impresión, la regla @media
    // print corresponde a una hoja de impresión del paquete, no a este parche.
    expect(str_contains(estiloDeLaPaleta(), '@media print'))->toBeFalse(
        'La paleta mete una regla @media print: eso va en la hoja de impresión del paquete.'
    );
});

it('apaga la transición del disparador con movimiento reducido también dentro del panel', function () {
    // DESIGN §6 promete que `--muni-dur` baja a 0 ms bajo prefers-reduced-motion,
    // y es verdad en `muni-ui.css`. Pero dentro de un panel Filament se carga
    // ÚNICAMENTE `muni-ui-filament.css` (DESIGN §7), y esa hoja NO baja el token:
    // su única regla de movimiento reducido apaga animaciones de widgets de
    // Filament. Medido en el banco del panel (`tests/navegador/rotulo-del-atajo.py`),
    // sin esta guardia la transición del disparador seguía en 160 ms con la
    // preferencia activa. Es la misma guardia que llevan stat y file-dropzone:
    // parece redundante leyendo DESIGN §6, y no lo es.
    $estilo = estiloDeLaPaleta();

    expect((bool) preg_match('#@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.muni-cmdk__trigger\s*\{[^}]*transition\s*:\s*none#s', $estilo))->toBeTrue(
        'El disparador no apaga su transición bajo prefers-reduced-motion: dentro del panel `--muni-dur` no baja a 0 ms y la transición sigue viva.'
    );
    // Y sin la preferencia sigue moviéndose con el token, que es el contrato del paquete.
    expect((bool) preg_match('/\.muni-cmdk__trigger\s*\{[^}]*transition\s*:[^;}]*var\(--muni-dur\)/s', $estilo))->toBeTrue(
        'La transición del disparador no usa var(--muni-dur).'
    );
});

/*
|--------------------------------------------------------------------------
| 3b. La tecla «esc» del cuadro: oculta al lector, con un texto que sí se entiende
|--------------------------------------------------------------------------
*/

it('la tecla «esc» del cuadro va aria-hidden y el lector recibe «Escape cierra la paleta»', function () {
    // Mismo criterio que el ⌘ del disparador: <kbd> no tiene rol y «esc» suelto
    // se lee «e-s-c» o «esc», que no le dice nada a quien no ve la tecla dibujada.
    $xpath = xpathDeLaPaleta(paletaSinDisparador());

    $esc = $xpath->query('//kbd[normalize-space(.)="esc"]');
    expect($esc->length)->toBe(1, 'El cuadro de búsqueda no rotula «esc» con un <kbd>.');

    $escOculta = $xpath->query('//kbd[normalize-space(.)="esc"][@aria-hidden="true" or ancestor::*[@aria-hidden="true"]]');
    expect($escOculta->length)->toBe(1,
        'La tecla «esc» del cuadro queda expuesta al lector de pantalla: se lee «e-s-c».'
    );

    $texto = $xpath->query('//*[@role="dialog"]//*[contains(concat(" ", normalize-space(@class), " "), " muni-cmdk__sr ") and normalize-space(.)="Escape cierra la paleta" and not(ancestor-or-self::*[@aria-hidden="true"])]');
    expect($texto->length)->toBe(1,
        'No hay un texto sr-only «Escape cierra la paleta» dentro del diálogo (o quedó bajo aria-hidden).'
    );
});

/*
|--------------------------------------------------------------------------
| 4. Aditivo: el `trigger` del consumidor sigue mandando
|--------------------------------------------------------------------------
*/

it('con un trigger propio no pinta el disparador por defecto', function () {
    $html = Blade::render(
        '<x-muni::command-palette :items="[]"><x-slot:trigger><button type="button">Mi botón</button></x-slot:trigger></x-muni::command-palette>'
    );

    expect(str_contains($html, 'Mi botón'))->toBeTrue('El trigger del consumidor no se renderiza.');
    // La CLASE sigue en el bloque <style> (viaja siempre en el @once); lo que no
    // puede haber es el <button> que la usa.
    expect((bool) preg_match('#<button\b[^>]*class="[^"]*muni-cmdk__trigger#', $html))->toBeFalse(
        'Con un trigger propio la paleta pinta además el suyo: dos disparadores para la misma paleta.'
    );
});

it('deja el foco inicial a x-trap para que Escape devuelva el foco al disparador', function () {
    // x-trap se activa 15 ms después de abrir y guarda como «dónde volver» lo que
    // esté enfocado en ese instante. Si show() enfoca la caja a mano antes, al
    // cerrar el foco vuelve a una caja con display:none y cae al <body>: medido en
    // Firefox y en Chromium con movimiento reducido. El contrato de x-trap para el
    // foco inicial es `autofocus` en el destino, como ya hace modal.
    $fuente = fuenteDeLaPaleta();

    expect((bool) preg_match('/show\(\)\s*\{[^}]*\.focus\(\)/', $fuente))->toBeFalse(
        'show() enfoca la caja a mano: x-trap guarda la caja como «dónde volver» y Escape deja el foco en el <body>.'
    );
    expect((bool) preg_match('/<input\b[^>]*\bx-ref="input"[^>]*\bautofocus\b/', $fuente))->toBeTrue(
        'La caja de búsqueda no lleva `autofocus`: sin él x-trap no sabe qué enfocar al abrir.'
    );
});

it('arma la trampa de foco después de que x-show pinta el diálogo', function () {
    // x-show muestra el diálogo en el siguiente requestAnimationFrame y x-trap activa
    // la trampa con un setTimeout de 15 ms desde que su expresión pasa a true. Si el
    // timer gana al frame —en headless una de cada tres veces; en un navegador a
    // 60 Hz cada vez que el próximo frame queda a más de 15 ms—, focus-trap intenta
    // enfocar una caja todavía en display:none, falla en silencio y no reintenta: el
    // diálogo queda abierto con el foco afuera, en el disparador. Medido en
    // tests/navegador/rotulo-del-atajo.py (seis aperturas seguidas). Por eso la
    // trampa se arma con `trap`, que sube en un $nextTick —Alpine lo retiene hasta el
    // frame final de la transición, con el diálogo ya pintado— y baja al cerrar. Va
    // en un $watch y no dentro de show(): así cubre también a quien abra con
    // `open = true` directo, como hace la vitrina con modal.
    $fuente = fuenteDeLaPaleta();

    expect((bool) preg_match('/x-trap\.inert\.noscroll="open\s*&&\s*trap"/', $fuente))->toBeTrue(
        'La trampa se arma con `open` a secas: compite con el frame de x-show y el foco puede quedar afuera del diálogo.'
    );
    expect((bool) preg_match('/\$watch\(\s*\'open\'.*?\$nextTick\(/s', $fuente))->toBeTrue(
        'No hay un $watch de `open` que suba `trap` en un $nextTick: la trampa no espera a que el diálogo esté pintado.'
    );
});

it('el placeholder no entra en una expresión de Alpine', function () {
    // `:placeholder="'{{ $placeholder }}'"` era la misma trampa de file-dropzone: un
    // apóstrofo en el texto descuadra la expresión y Alpine tumba la página entera.
    $html = paletaSinDisparador('placeholder="Buscar por RUT o N\' de folio"');

    expect((bool) preg_match('/:placeholder="/', $html))->toBeFalse(
        'El placeholder se enlaza con :placeholder: el texto del anfitrión entra en una expresión de Alpine.'
    );
    expect(substr_count($html, 'Buscar por RUT o N&#039; de folio'))->toBeGreaterThanOrEqual(2,
        'El placeholder no llega escapado a la caja y al disparador.'
    );
});

it('no se rompe con un hotkey que lleve apóstrofo', function () {
    // El texto del anfitrión no entra crudo en la expresión de Alpine: Blade escapa
    // el apóstrofo a &#039;, el navegador lo decodifica dentro del atributo y la
    // expresión queda descuadrada, así que Alpine tumbaba la página entera.
    $html = paletaSinDisparador('hotkey="\'"');

    expect(str_contains($html, "==='&#039;'"))->toBeFalse(
        'El hotkey entra crudo en la expresión de Alpine: un apóstrofo tumba el Alpine de la página.'
    );
});

/*
|--------------------------------------------------------------------------
| 5. Lo que un test de Blade no puede ver: el banco de navegador
|--------------------------------------------------------------------------
|
| Todo lo de arriba lee HTML y CSS como texto. Lo que importa de un disparador
| —que el navegador calcule el nombre «Buscar o ir a… Control K» y no lea el ⌘,
| que se vea UNA sola combinación, que Escape devuelva el foco al botón, que
| Ctrl+K abra la paleta k y Ctrl+P la p, que Bloq Mayús no apague el atajo, que
| el contraste pase a 4,5:1 en reposo y que la transición muera con movimiento
| reducido TAMBIÉN bajo la hoja del panel— solo se mide con estilos computados y
| el árbol de accesibilidad. El banco sale a `build/rotulo-del-atajo/` (ignorado
| por git) y lo recorre `tests/navegador/rotulo-del-atajo.py` en Chromium.
|
| Son CUATRO páginas por lo mismo que la vitrina (DESIGN §7): dentro de un panel
| Filament `muni-ui.css` no se carga, y `panel-*` lleva ÚNICAMENTE la hoja del
| panel con el DOM mínimo que esa hoja estiliza.
*/

/** Las copias de Alpine 3 y del plugin Focus, o null si no corrió `npm install`. */
function alpineParaElBancoDelAtajo(): ?array
{
    $core = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';
    $focus = __DIR__.'/../node_modules/@alpinejs/focus/dist/cdn.min.js';

    if (! is_file($core) || ! is_file($focus)) {
        return null;
    }

    return [(string) file_get_contents($focus), (string) file_get_contents($core)];
}

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDelBancoDelAtajo(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

it('genera el banco de navegador en build/rotulo-del-atajo/', function () {
    $dir = __DIR__.'/../build/rotulo-del-atajo';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $items = "[['label' => 'Bandeja de entrada', 'url' => '#bandeja', 'group' => 'Trámites'],"
        ."['label' => 'Patentes morosas', 'url' => '#patentes', 'group' => 'Rentas'],"
        ."['label' => 'Usuarios del sistema', 'url' => '#usuarios', 'group' => 'Administración']]";

    // Dos paletas con DISTINTA tecla en la misma página: la prueba de que el
    // rótulo sale de `hotkey` es que Ctrl+P abre la p y no la k, y que la p
    // rotula «P». Los dos enlaces son las paradas de teclado de antes y después.
    $piezas = Blade::render(
        '<p><a href="#antes" id="antes">Enlace antes</a></p>'
        .'<x-muni::command-palette id="paleta-k" :items="'.$items.'" />'
        .'<p><x-muni::command-palette id="paleta-p" hotkey="p" placeholder="Ir a un trámite…" :items="'.$items.'" /></p>'
        .'<p><a href="#despues" id="despues">Enlace después</a></p>'
    );

    $alpine = alpineParaElBancoDelAtajo();
    $cola = $alpine === null ? '' : '<script>'.$alpine[0].'</script><script>'.$alpine[1].'</script>';

    $paginas = [
        'claro' => ['light', cssMuniUi(), false],
        'oscuro' => ['dark', cssMuniUi(), false],
        'panel-claro' => ['light', cssMuniUiFilament(), true],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel]) {
        $oscuro = $tema === 'dark';
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        // El armazón no aporta ni un color: fondo y texto salen de los mismos
        // tokens que lee el componente. En el panel, `color:var(--muni-text)`
        // repone lo que Filament pone en el <body> y el paquete no.
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<section class="fi-section">'.$piezas.'</section></main></div>'.$cola.'</body>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$piezas.'</main>'.$cola.'</body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco del atajo — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});

it('en Chromium: nombre accesible, una sola combinación, Escape devuelve el foco, Ctrl+K/Ctrl+P/Bloq Mayús, contraste y movimiento reducido también en el panel', function () {
    $python = pythonDelBancoDelAtajo();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay Chromium: la verificación en navegador se salta, no se da por hecha.');
    }

    if (alpineParaElBancoDelAtajo() === null) {
        $this->markTestSkipped('Sin node_modules (npm install) el banco sale sin Alpine y no hay nada que abrir: se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/rotulo-del-atajo.py').' '
        .escapeshellarg(__DIR__.'/../build/rotulo-del-atajo').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador del disparador de <x-muni::command-palette> falló:\n".implode("\n", $lineas));
    // Las CUATRO páginas —las dos del panel incluidas— tienen que reportar la
    // transición apagada con la preferencia. Es la comprobación que desmiente
    // «la guardia es redundante»: sin ella, `panel-*` sale en 160 ms.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'transición(reduce)=none 0s'))))->toBe(4,
        "Las cuatro páginas tienen que reportar la transición del disparador apagada con la preferencia:\n".implode("\n", $lineas));
});
