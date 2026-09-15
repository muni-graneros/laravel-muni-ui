<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL SELECTOR DE TEMA — ficha `conmutador-tema`, tal como la corrigió el juez
|--------------------------------------------------------------------------
|
| Dos partes, y las dos se vigilan acá:
|
| (A) Los tres armazones dejaban `'theme' => 'light'` por defecto y lo escribían
|     como `data-muni-theme="light"` en el `<html>`: el valor por defecto
|     DESACTIVABA la preferencia del sistema operativo, contra lo que el README
|     documenta como «PWA que sigue el OS: no pongas nada». En la central de
|     cámaras, en turno de noche, se trabajaba en claro porque no había dónde
|     cambiarlo. Ahora el default es `null` y el atributo solo sale con valor;
|     un sistema con identidad fija (discapacidad) pasa `theme="light"` explícito.
|
| (B) `<x-muni::selector-tema>`: un envoltorio de `segmented` con
|     `name="muni-tema"` y tres opciones excluyentes (sistema / claro / oscuro),
|     persistido en una COOKIE que escribe una ruta del anfitrión (POST con CSRF)
|     y que el anfitrión lee para pasarle `theme` al armazón. Nunca localStorage:
|     bajo la CSP con nonce del ecosistema el script en línea del `<head>` que
|     exigiría es un costo real, y la cookie leída en el servidor elimina el
|     parpadeo sin una sola línea de JS.
|
| Lo que NO hace: no se usa dentro de un panel Filament (el panel ya trae su
| conmutador con persistencia propia; dos fuentes de verdad se pisan) y no
| consulta request(), Auth ni Gate: recibe `value` y `action` ya resueltos.
|
| `toContain('valor', 'mensaje')` NO sirve: en Pest el segundo argumento es otra
| aguja, no un mensaje, y la aserción se apaga sin avisar. Todo va con
| `expect(bool)->toBeTrue('mensaje')`. Los helpers llevan prefijo `st` porque
| Pest carga todos los archivos en el mismo proceso.
*/

/** El `selector-tema.blade.php` crudo. */
function stFuente(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/selector-tema.blade.php');
}

/** El fuente sin comentarios de Blade ni de CSS: una cita en prosa no es un defecto. */
function stSinComentarios(string $fuente): string
{
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El CSS de los bloques `<style>` del componente, sin comentarios. */
function stCss(): string
{
    preg_match_all('#<style>(.*?)</style>#s', stFuente(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/** El componente renderizado con una acción realista y lo que se le agregue. */
function stHtml(string $extra = ''): string
{
    return Blade::render(trim('<x-muni::selector-tema action="/preferencias/tema" '.$extra).' />');
}

/**
 * La etiqueta de apertura del <form>, entera. Un `<form\b[^>]*>` no sirve: el
 * `x-data` lleva flechas `=>` y el `>` de la flecha cortaba la etiqueta a la
 * mitad, así que lo que venía después (el `@change`, el `@submit`) parecía no
 * estar. Se recorre atributo por atributo respetando las comillas.
 */
function stEtiquetaForm(string $html): string
{
    preg_match('/<form\b(?:[^>"]|"[^"]*")*>/s', $html, $m);

    return $m[0] ?? '';
}

/*
|--------------------------------------------------------------------------
| (A) Los armazones siguen al sistema operativo por defecto
|--------------------------------------------------------------------------
*/

it('los tres armazones no fijan tema por defecto y solo escriben el atributo cuando se les pasa', function () {
    $armazones = [
        'app-shell' => 'system="Licencias"',
        'auth-shell' => '',
        'dashboard-shell' => '',
    ];

    foreach ($armazones as $nombre => $props) {
        $porDefecto = Blade::render("<x-muni::{$nombre} {$props}>Contenido</x-muni::{$nombre}>");

        expect(str_contains($porDefecto, 'data-muni-theme'))->toBeFalse(
            "«{$nombre}» escribe data-muni-theme sin que nadie se lo pida: el valor por defecto ".
            'apaga la preferencia del sistema operativo, y con el equipo en oscuro la pantalla sigue en claro.'
        );

        expect((bool) preg_match('/<html\b[^>]*\blang="es"/', $porDefecto))->toBeTrue(
            "«{$nombre}» perdió el lang del <html> al dejar de emitir el tema."
        );

        foreach (['light', 'dark'] as $tema) {
            $fijo = Blade::render("<x-muni::{$nombre} {$props} theme=\"{$tema}\">Contenido</x-muni::{$nombre}>");

            expect((bool) preg_match('/<html\b[^>]*\bdata-muni-theme="'.$tema.'"/', $fijo))->toBeTrue(
                "«{$nombre}» con theme=\"{$tema}\" no congela el tema: un sistema con identidad fija ".
                '(discapacidad) o el que persiste la elección del funcionario se queda sin efecto.'
            );
        }
    }
});

it('los tres armazones declaran color-scheme en el <head> según el tema que reciben', function () {
    /*
     * Es el segundo agujero de la ficha: sin `color-scheme` los desplegables
     * nativos, los input type=date, las barras de desplazamiento y la vista
     * previa de impresión se pintan en claro aunque los tokens ya sean oscuros.
     * La <meta> lo declara sin CSS y sin JS —no depende de que la hoja del
     * paquete llegue— y sigue al prop: sin valor, «light dark» (el navegador
     * elige por la preferencia del equipo, igual que los tokens); con
     * theme="light", solo claro (discapacidad no puede oscurecer ni sus
     * selects); con theme="dark", solo oscuro.
     */
    $armazones = [
        'app-shell' => 'system="Licencias"',
        'auth-shell' => '',
        'dashboard-shell' => '',
    ];

    $esperado = ['' => 'light dark', 'theme="light"' => 'light', 'theme="dark"' => 'dark'];

    foreach ($armazones as $nombre => $props) {
        foreach ($esperado as $tema => $contenido) {
            $html = Blade::render("<x-muni::{$nombre} {$props} {$tema}>Contenido</x-muni::{$nombre}>");

            preg_match_all('/<meta\b[^>]*\bname="color-scheme"[^>]*\bcontent="([^"]*)"/', $html, $m);

            expect(count($m[1]))->toBe(1, "«{$nombre}» ".($tema ?: 'sin tema').': hay '.count($m[1]).' <meta name="color-scheme">, tendría que haber exactamente una.');
            expect($m[1][0])->toBe($contenido, "«{$nombre}» ".($tema ?: 'sin tema').": la <meta name=\"color-scheme\"> dice «{$m[1][0]}» y tendría que decir «{$contenido}».");

            expect((bool) preg_match('/<head>.*<meta\b[^>]*\bname="color-scheme".*<\/head>/s', $html))->toBeTrue(
                "«{$nombre}»: la <meta name=\"color-scheme\"> no está dentro del <head>."
            );
        }
    }
});

/*
|--------------------------------------------------------------------------
| (B) El selector
|--------------------------------------------------------------------------
*/

it('es un formulario POST real con token CSRF y botón de envío: funciona sin JS', function () {
    $html = stHtml();

    $form = stEtiquetaForm($html);

    expect((bool) preg_match('/\bmethod="post"/i', $form))->toBeTrue(
        'No hay un <form method="post">: sin JS la elección no llega a ninguna parte.'
    );
    expect((bool) preg_match('/\baction="\/preferencias\/tema"/', $form))->toBeTrue(
        'La prop `action` no llega al formulario: el POST cae en la página actual.'
    );
    expect(str_contains($html, 'name="_token"'))->toBeTrue(
        'El formulario no lleva el token CSRF: la ruta protegida responde 419 y la preferencia no se guarda.'
    );
    expect((bool) preg_match('/<button\b[^>]*\btype="submit"/', $html))->toBeTrue(
        'No hay botón de envío: sin JS segmented no autoenvía, así que no hay forma de guardar.'
    );
});

it('ofrece tres radios reales con name muni-tema y marca la elegida', function () {
    $html = stHtml('value="oscuro"');

    foreach (['sistema', 'claro', 'oscuro'] as $valor) {
        expect((bool) preg_match('/<input\b[^>]*\btype="radio"[^>]*\bname="muni-tema"[^>]*\bvalue="'.$valor.'"/', $html))->toBeTrue(
            "Falta la opción «{$valor}» como radio real con name=\"muni-tema\"."
        );
    }

    expect((bool) preg_match('/<input\b[^>]*\bvalue="oscuro"[^>]*\bchecked\b/', $html))->toBeTrue(
        'value="oscuro" no marca el radio de oscuro: el funcionario ve una elección que no es la suya.'
    );
    expect(substr_count($html, ' checked'))->toBe(1, 'Hay más de un radio marcado, o ninguno.');

    // Sin valor, se sigue al sistema: es el default que el README documenta.
    expect((bool) preg_match('/<input\b[^>]*\bvalue="sistema"[^>]*\bchecked\b/', stHtml()))->toBeTrue(
        'Sin `value` no queda marcado «sistema».'
    );
});

it('acepta el valor del armazón (light/dark/null) además del de la cookie', function () {
    // El anfitrión guarda `claro`/`oscuro` en la cookie pero le pasa `light`/`dark`
    // al armazón; que el selector entienda los dos evita que cada sistema mapee.
    $casos = ['light' => 'claro', 'dark' => 'oscuro', 'system' => 'sistema'];

    foreach ($casos as $entrada => $marcado) {
        $html = stHtml('value="'.$entrada.'"');

        expect((bool) preg_match('/<input\b[^>]*\bvalue="'.$marcado.'"[^>]*\bchecked\b/', $html))->toBeTrue(
            "value=\"{$entrada}\" tendría que marcar «{$marcado}»."
        );
    }
});

it('un valor hostil o desconocido cae en «sistema» y no llega al HTML', function () {
    $html = stHtml('value="<script>alert(1)</script>"');

    expect(str_contains($html, '<script>alert'))->toBeFalse('El value se imprime crudo en el HTML.');
    expect((bool) preg_match('/<input\b[^>]*\bvalue="sistema"[^>]*\bchecked\b/', $html))->toBeTrue(
        'Un valor desconocido debería dejar marcado «sistema», no dejar el grupo sin selección.'
    );
});

it('el grupo es un radiogroup con nombre visible y ayuda atada, con ids que salen del name', function () {
    $html = stHtml();

    expect(str_contains($html, 'role="radiogroup"'))->toBeTrue('El grupo no se anuncia como radiogroup.');
    expect((bool) preg_match('/role="radiogroup"[^>]*\baria-labelledby="muni-tema-rotulo"/', $html))->toBeTrue(
        'El grupo no apunta con aria-labelledby a su rótulo visible.'
    );
    expect((bool) preg_match('/id="muni-tema-rotulo"[^>]*>\s*Tema\s*</', $html))->toBeTrue(
        'No existe el rótulo visible «Tema» con el id que el grupo referencia.'
    );
    expect((bool) preg_match('/role="radiogroup"[^>]*\baria-describedby="muni-tema-ayuda"/', $html))->toBeTrue(
        'La ayuda no queda atada al grupo con aria-describedby.'
    );
    expect(str_contains($html, 'id="muni-tema-ayuda"'))->toBeTrue('No existe el nodo de ayuda referenciado.');

    // Ids deterministas: salen del name, nunca de uniqid().
    expect(str_contains(stSinComentarios(stFuente()), 'uniqid'))->toBeFalse('Los ids salen de uniqid(): cambian en cada render.');

    $otro = stHtml('name="tema-mesa"');

    expect(str_contains($otro, 'id="tema-mesa-rotulo"'))->toBeTrue('El id del rótulo no sigue al name.');
    expect(str_contains($otro, 'id="tema-mesa-0"'))->toBeTrue('Los ids de los radios no siguen al name.');
});

it('el estado se dice en texto visible con role=status, presente y vacío desde el primer render', function () {
    $html = stHtml();

    expect((bool) preg_match('/<p\b[^>]*\brole="status"[^>]*>\s*<\/p>/', $html))->toBeTrue(
        'No hay una región role="status" vacía en el HTML inicial: una región viva que nace con '.
        'texto no se anuncia, y sin ella el cambio de tema queda mudo para el lector de pantalla.'
    );
});

it('no persiste en el navegador: ni localStorage ni document.cookie ni $persist', function () {
    $fuente = stSinComentarios(stFuente());

    foreach (['localStorage', 'sessionStorage', 'document.cookie', '$persist'] as $aguja) {
        expect(str_contains($fuente, $aguja))->toBeFalse(
            "El selector persiste con {$aguja}: la elección tiene que vivir en la cookie que escribe la ruta ".
            'del anfitrión, que es lo único que el servidor puede leer antes del primer pintado.'
        );
    }

    expect(str_contains(stHtml(), '<script'))->toBeFalse(
        'El componente emite un <script>: bajo la CSP con nonce del ecosistema es un costo real, y no hace falta.'
    );
});

it('aplica el tema con data-muni-theme en la raíz y nunca toca la clase .dark de Filament', function () {
    $fuente = stSinComentarios(stFuente());

    expect(str_contains($fuente, "setAttribute('data-muni-theme'"))->toBeTrue(
        'El cambio no se aplica al instante sobre el <html>: hay que esperar la recarga para verlo.'
    );
    expect(str_contains($fuente, "removeAttribute('data-muni-theme')"))->toBeTrue(
        '«Sistema» no quita el atributo: sin quitarlo el media query del SO nunca vuelve a decidir.'
    );
    expect((bool) preg_match('/classList\.[a-z]+\(\s*[\'"]dark[\'"]/', $fuente))->toBeFalse(
        'El selector escribe la clase .dark, que es de Filament: dos fuentes de verdad que se pisan al recargar.'
    );
});

it('intercepta el cambio en fase de captura para que el autoenvío de segmented no recargue', function () {
    /*
     * `segmented` envía el formulario con `x-on:change` + requestSubmit() al
     * elegir una opción: dentro de un <form>, eso recarga la página. Con
     * Alpine el cambio se aplica al instante y se guarda por fetch; para que
     * ese envío no gane la carrera, el formulario escucha `change` en CAPTURA
     * y detiene la propagación antes de que llegue al radio. Sin Alpine no hay
     * autoenvío y el botón Guardar sigue ahí.
     */
    $form = stEtiquetaForm(stHtml());

    expect($form)->not->toBe('', 'No se encontró la etiqueta <form> en el HTML.');

    expect((bool) preg_match('/\s(?:@|x-on:)change\.capture\.stop=/', $form))->toBeTrue(
        'El formulario no escucha `change` en captura con `.stop`: el autoenvío de segmented '.
        'envía el formulario y cada flecha del teclado recarga la página (WCAG 2.2 AA 3.2.2).'
    );

    expect((bool) preg_match('/\s(?:@|x-on:)submit\.prevent=/', $form))->toBeTrue(
        'El botón Guardar no se intercepta con Alpine: con JS tendría que guardar por fetch, no recargar.'
    );
});

it('la petición pide JSON, para que un rechazo del servidor no se disfrace de redirección', function () {
    $fuente = stSinComentarios(stFuente());

    expect(str_contains($fuente, "'Accept': 'application/json'"))->toBeTrue(
        'Sin `Accept: application/json` un 422 o un 419 vuelven como redirección a la página, '.
        'fetch la sigue y `ok` queda en true: el selector diría «guardado» sin haber guardado.'
    );
    expect(str_contains($fuente, "credentials: 'same-origin'"))->toBeTrue(
        'Sin credenciales la sesión no viaja y la ruta con CSRF responde 419.'
    );
});

it('se desactiva por sistema y entonces no emite nada', function () {
    $html = Blade::render('<x-muni::selector-tema action="/preferencias/tema" :enabled="false" />');

    expect(trim($html))->toBe('',
        'Con enabled=false el selector sigue emitiendo marcado: discapacidad, que tiene identidad fija '.
        'en claro, no puede desactivarlo sin envolverlo en un @if propio.'
    );
});

it('declara color-scheme para los dos temas, y el del sistema operativo solo en pantalla', function () {
    /*
     * El segundo agujero de la ficha: `color-scheme` no aparecía en ningún CSS
     * del paquete, así que en oscuro los desplegables nativos, los input
     * type=date y las barras de desplazamiento se seguían pintando en claro.
     * Va atado a `screen` por la misma razón que la regla del SO en
     * muni-ui.css: el navegador informa la preferencia también al imprimir y
     * una hoja de papel no tiene modo oscuro.
     */
    $css = stCss();

    expect((bool) preg_match('/\[data-muni-theme="dark"\][^{]*\{[^}]*color-scheme\s*:\s*dark/', $css))->toBeTrue(
        'El activador explícito de oscuro no declara color-scheme: dark.'
    );

    // Y ese activador va DENTRO de `@media screen`, igual que la regla del SO:
    // medido con la reja sobre la vitrina, con `[data-muni-theme="dark"]` suelto
    // el `color-scheme` computaba `dark` también bajo media=print y un botón sin
    // color de autor salía con el texto blanco del UA sobre el papel (1,00:1).
    // Una hoja de papel no tiene modo oscuro (DESIGN §3), tampoco para el UA.
    expect((bool) preg_match('/@media\s+screen\s*\{[^{}]*\[data-muni-theme="dark"\][^{]*\{[^}]*color-scheme\s*:\s*dark/s', $css))->toBeTrue(
        'El color-scheme: dark del activador explícito no está dentro de `@media screen`: en papel los '.
        'controles nativos y los botones sin color de autor se pintan con la paleta oscura del navegador.'
    );

    $fueraDePantalla = (string) preg_replace('/@media\s+screen[^{]*\{(?:[^{}]*\{[^}]*\})*[^}]*\}/s', '', $css);

    expect((bool) preg_match('/color-scheme\s*:\s*dark/', $fueraDePantalla))->toBeFalse(
        'Hay un `color-scheme: dark` fuera de un `@media screen`: se aplicaría también al imprimir.'
    );
    expect((bool) preg_match('/\[data-muni-theme="light"\]\s*\{[^}]*color-scheme\s*:\s*light/', $css))->toBeTrue(
        'El congelador de claro no declara color-scheme: light.'
    );
    expect((bool) preg_match('/@media\s+screen\s+and\s*\(prefers-color-scheme:\s*dark\)\s*\{[^}]*:root:not\(\[data-muni-theme="light"\]\)[^{]*\{[^}]*color-scheme\s*:\s*dark/', $css))->toBeTrue(
        'La regla del sistema operativo no está bajo `@media screen and (prefers-color-scheme: dark)` '.
        'con el :not() que respeta al claro forzado: o falta, o se aplica también al papel.'
    );
});

it('no escribe colores literales, respeta el foco del contrato y anima con el token de duración', function () {
    $fuente = stSinComentarios(stFuente());
    $sinRespaldo = str_replace('var(--muni-focus, var(--muni-accent, #767676))', '', $fuente);

    expect((bool) preg_match('/#[0-9a-fA-F]{3,8}\b/', $sinRespaldo))->toBeFalse(
        'El selector escribe un color literal: el adoptante no puede cambiarlo sin editar el paquete.'
    );
    expect((bool) preg_match('/\b(rgba?|hsla?|oklch)\(/', $sinRespaldo))->toBeFalse('Color literal en función de color.');

    $css = stCss();

    expect((bool) preg_match('/:focus-visible\s*\{[^}]*outline\s*:\s*3px solid var\(--muni-focus, var\(--muni-accent, #767676\)\)/', $css))->toBeTrue(
        'El botón Guardar no declara el outline del contrato: dentro de Filament la box-shadow se pierde.'
    );
    expect((bool) preg_match('/transition\s*:[^;]*\d+m?s/', $css))->toBeFalse(
        'Hay una transición con duración fija: ignora prefers-reduced-motion. Usa var(--muni-dur).'
    );
    expect(str_contains($css, 'var(--muni-dur)'))->toBeTrue('La transición no usa var(--muni-dur).');

    // La guardia propia, como en modal y command-palette: muni-ui.css baja
    // --muni-dur a 0 ms con la preferencia, pero otra hoja de tokens (la del
    // panel) lo deja en 160 ms y el botón seguiría animando.
    expect((bool) preg_match('/@media\s*\(prefers-reduced-motion\s*:\s*reduce\)\s*\{[^}]*\.muni-tema__guardar[^{]*\{[^}]*transition\s*:\s*none/s', $css))->toBeTrue(
        'El selector no trae su propia regla de prefers-reduced-motion para el botón Guardar.'
    );

    if (preg_match('/\.muni-tema__guardar\s*\{[^}]*min-height\s*:\s*(\d+)px/', $css, $m)) {
        expect((int) $m[1])->toBeGreaterThanOrEqual(24, 'El botón Guardar no llega a 24 px de alto.');
    } else {
        expect(false)->toBeTrue('El botón Guardar no declara min-height: el blanco de pulsación no está garantizado.');
    }
});

it('la píldora activa se ve sin JS donde :has() resuelve, y el :has() vive dentro de @supports', function () {
    $css = stCss();

    expect((bool) preg_match('/@supports\s+selector\(\s*:has\([^)]*\)\s*\)\s*\{[^}]*\.muni-seg:has\(input:checked\)/s', $css))->toBeTrue(
        'Sin JS la píldora marcada no se mueve al elegir: falta `.muni-seg:has(input:checked)` dentro de '.
        '`@supports selector(:has(*))`. Fuera de @supports, un navegador viejo descarta la regla entera.'
    );

    // La contraparte: la píldora que el servidor dejó marcada con `.muni-seg--on`
    // tiene que APAGARSE cuando el radio marcado ya es otro. Sin esto, sin JS se
    // ven dos activas hasta el submit (medido en Chromium y Firefox).
    expect((bool) preg_match('/@supports\s+selector\(\s*:has\([^)]*\)\s*\)\s*\{.*?\.muni-seg--on:not\(:has\(input:checked\)\)\s*\{[^}]*background\s*:\s*transparent/s', $css))->toBeTrue(
        'Sin JS, al marcar otra opción la píldora renderizada por el servidor sigue encendida: falta '.
        '`.muni-seg--on:not(:has(input:checked))` con `background:transparent` dentro del @supports.'
    );

    $fuera = (string) preg_replace('/@supports[^{]*\{(?:[^{}]*\{[^}]*\})*[^}]*\}/s', '', $css);

    expect(str_contains($fuera, ':has('))->toBeFalse('Hay `:has()` fuera de un @supports.');
});

it('no aparece un texto del anfitrión dentro de una expresión de Alpine', function () {
    /*
     * La lección de file-dropzone: `{{ $label }}` dentro de un x-data o un
     * x-text con comillas simples descuadra la expresión con un apóstrofo y
     * tumba el Alpine de la página entera. El rótulo, la ayuda y el botón van
     * en HTML; el nombre del campo nunca entra a un selector de JS.
     */
    $html = stHtml('label="Tema de O\'Higgins" hint="Ayuda con \'comillas\'" submit-label="Guardar \'ya\'" name="tema\'x"');

    preg_match_all('/\s(?:x-data|x-text|x-init|@[a-z.:-]+|x-on:[a-z.:-]+)="([^"]*)"/', $html, $m);

    foreach ($m[1] as $expresion) {
        expect(str_contains(html_entity_decode($expresion, ENT_QUOTES), "O'Higgins"))->toBeFalse('El rótulo entró a una expresión de Alpine.');
        expect(str_contains(html_entity_decode($expresion, ENT_QUOTES), 'comillas'))->toBeFalse('La ayuda entró a una expresión de Alpine.');
        expect(str_contains(html_entity_decode($expresion, ENT_QUOTES), "tema'x"))->toBeFalse('El name entró a una expresión de Alpine.');
    }

    expect(str_contains($html, 'Tema de O&#039;Higgins'))->toBeTrue('El rótulo con apóstrofo no se escapó como HTML.');
});

/*
|--------------------------------------------------------------------------
| EL BANCO DE NAVEGADOR: build/banco-selector-tema/{sistema,claro,oscuro}.html
|--------------------------------------------------------------------------
|
| Lo de arriba lee el HTML que emite Blade y el texto del componente. Lo que la
| ficha promete de verdad —que elegir con las flechas aplica el tema AL INSTANTE
| sobre el <html> sin recargar, que las tres flechas seguidas son UN solo POST,
| que un 500 se dice en texto y Guardar reintenta, que `destroy()` cancela el
| envío si Livewire quita el nodo, que sin JS `:has()` mueve la píldora y el
| botón envía el formulario, y que cada texto pasa 4,5:1 en los tres estados—
| solo se ve en un navegador. Se mide en Chromium y Firefox con
| `tests/navegador/selector-tema.py` (se salta, no se finge, sin `.venv-a11y`).
|
| El banco NO es un HTML escrito a mano: son tres páginas sobre `app-shell`, el
| armazón real, con su <meta name="color-scheme"> y su atributo derivados del
| mismo prop que el anfitrión pasaría tras leer la cookie. Alpine sale de
| node_modules, nunca de un CDN: el banco se abre sin red.
*/

/** Alpine 3 (núcleo) tal como lo trae node_modules, o null si no se corrió `npm install`. */
function stAlpineParaBanco(): ?string
{
    $ruta = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    return is_file($ruta) ? (string) file_get_contents($ruta) : null;
}

/**
 * Una página del banco: el armazón real con el selector en el estado guardado
 * `$valor` y el `theme` que el anfitrión derivaría de esa misma cookie.
 *
 * La hoja y Alpine entran por marcadores y NO por Blade: el CSS entero pasado
 * por `Blade::render()` es texto que Blade compila, y una arroba desconocida
 * o unas llaves dobles en un comentario cambiarían la hoja en silencio.
 */
function stPaginaDelBanco(string $valor, ?string $tema, string $css, ?string $alpine): string
{
    $html = Blade::render(
        '<x-muni::app-shell system="Licencias" subtitle="Municipalidad de Graneros" title="Preferencias · Licencias"'
        .($tema !== null ? ' theme="'.$tema.'"' : '').'>'
        .'<x-slot:head>__CSS__</x-slot:head>'
        .'<x-muni::page-header title="Preferencias" subtitle="Cómo se ve el sistema en este equipo" />'
        .'<p><label for="banco-primero">Campo de referencia</label> <input id="banco-primero"></p>'
        .'<x-muni::selector-tema action="/preferencias/tema" value="'.$valor.'" />'
        // Controles nativos, que son lo que `color-scheme` pinta y los tokens no alcanzan.
        .'<p><label for="banco-fecha">Vence el</label> <input type="date" id="banco-fecha" name="vence"> '
        .'<label for="banco-clase">Clase</label> <select id="banco-clase" name="clase"><option>Clase B</option><option>Clase C</option></select></p>'
        // El botón del paquete y no uno pelado: el pelado salía blanco sobre blanco
        // en la pasada de impresión de la reja, y eso sería un defecto del banco.
        .'<x-muni::button variant="ghost" id="banco-ultimo">Siguiente</x-muni::button>'
        .'__ALPINE__'
        .'</x-muni::app-shell>'
    );

    return str_replace(
        ['__CSS__', '__ALPINE__'],
        ['<style>'.$css.'</style>', $alpine !== null ? '<script data-banco="alpine-core">'.$alpine.'</script>' : ''],
        $html,
    );
}

it('genera el banco de navegador en build/banco-selector-tema/: los tres estados sobre el armazón real', function () {
    $css = (string) file_get_contents(__DIR__.'/../resources/css/muni-ui.css');
    $alpine = stAlpineParaBanco();

    $dir = __DIR__.'/../build/banco-selector-tema';
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    // valor guardado en la cookie => theme que el anfitrión deriva de ella.
    $paginas = ['sistema' => null, 'claro' => 'light', 'oscuro' => 'dark'];

    foreach ($paginas as $valor => $tema) {
        $html = stPaginaDelBanco($valor, $tema, $css, $alpine);
        file_put_contents($dir.'/'.$valor.'.html', $html);

        expect(is_file($dir.'/'.$valor.'.html'))->toBeTrue('No se escribió el banco '.$valor.'.');
        expect(str_contains($html, '__CSS__') || str_contains($html, '__ALPINE__'))->toBeFalse('Quedó un marcador sin reemplazar en '.$valor.'.');
        expect((bool) preg_match('/<input\b[^>]*\bvalue="'.$valor.'"[^>]*\bchecked\b/', $html))->toBeTrue('El banco '.$valor.' no nace con esa opción marcada.');
        expect((bool) preg_match('/<meta\b[^>]*\bname="color-scheme"[^>]*\bcontent="'.($tema ?? 'light dark').'"/', $html))->toBeTrue(
            'El armazón del banco '.$valor.' no declara la <meta name="color-scheme"> que le corresponde.'
        );
        // Solo la etiqueta <html>: el x-data del selector nombra el atributo en su código.
        preg_match('/<html\b[^>]*>/', $html, $raiz);
        expect($tema === null ? ! str_contains($raiz[0] ?? '', 'data-muni-theme') : str_contains($raiz[0] ?? '', 'data-muni-theme="'.$tema.'"'))->toBeTrue(
            'El <html> del banco '.$valor.' no lleva el activador que le corresponde: '.($raiz[0] ?? '(sin <html>)')
        );
        expect(str_contains($html, 'data-banco="alpine-core"'))->toBeTrue(
            'El banco salió sin Alpine: corre `npm install`; sin él no se puede medir la aplicación en caliente.'
        );
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function stPythonDelBanco(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

it('en Chromium y Firefox: aplica al instante con las flechas, un solo POST, el 500 se dice y Guardar reintenta, sin JS :has() y el form envían', function () {
    $python = stPythonDelBanco();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/selector-tema.py').' '
        .escapeshellarg(__DIR__.'/../build/banco-selector-tema').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador del selector de tema falló:\n".implode("\n", $lineas));

    // Tres páginas × dos preferencias del SO × dos navegadores, cada una con la
    // transición apagada por la preferencia de movimiento.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'transición(reduce)=0s'))))->toBe(12,
        "Los doce recorridos tienen que reportar la transición apagada con la preferencia:\n".implode("\n", $lineas));
});
