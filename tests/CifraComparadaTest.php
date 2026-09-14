<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| LA CIFRA COMPARADA (`<x-muni::stat>`)
|--------------------------------------------------------------------------
|
| Reparación de la ficha `stat` de docs/GAP-ANALYSIS.md, en los términos de la
| «corrección exigida» por el juez, que manda sobre la ficha:
|
| 1. La cifra y su rótulo se atan por ATRIBUTOS —`role="group"` +
|    `aria-labelledby` al id del rótulo—, no con un <dl>. El DOM no cambia: la
|    raíz sigue siendo `<div class="muni-stat">`, así que nada que un host haya
|    estilizado se rompe y no hay que versionar.
| 2. El sentido de la variación («subió» / «bajó») sale de `deltaDir` en PHP y
|    va en texto, oculto visualmente. Antes dependía de que el host mandara el
|    signo dentro de `delta`, y una convención no es una garantía: con
|    `delta="12 %"` un lector de pantalla oía «12 %» sin saber hacia dónde
|    (WCAG 1.4.1, el color como único portador). La flecha, que en pantalla es
|    el portador que no depende del color, pasa de 8 px —ilegible— a 12 px.
| 3. Las cifras llevan `.muni-num`, la firma del sistema (DESIGN §9), y el
|    componente DECLARA esa clase en su propio bloque de estilos: hasta ahora
|    solo la emitía data-table, y en una página sin tabla la cifra perdía la
|    mono tabular. Lo mismo con el ocultado visual. Van en el `@once` del
|    componente y no en muni-ui.css porque dentro de un panel Filament esa
|    hoja no se carga (DESIGN §7): es la misma trampa que ya se pisó.
| 4. El ocultado visual recorta (`clip-path` + 1 px). `display:none` sacaría el
|    texto del árbol de accesibilidad y la reparación no serviría de nada.
| 5. Fuera de alcance, por decisión del juez: la región viva para la cifra que
|    se refresca por polling. Una live region solo anuncia si el nodo persiste
|    y cambia; si el morph de Livewire reemplaza el contenedor, no anuncia
|    nada. Es un contrato con el host más verificación con lector real, en una
|    tarea aparte. Por eso acá se comprueba que el componente NO emite
|    `aria-live` ni `role="status"` por su cuenta.
| 6. El id del rótulo es determinista (DESIGN §10): `{id}-rotulo` si el
|    consumidor pasa `id`, y si no `muni-stat-{slug del rótulo}-rotulo`. Dos
|    stats con el mismo rótulo y sin `id` colisionan a propósito —no hay
|    uniqid() ni contador que los distinga sin romper el diffing de Livewire—:
|    el README lo advierte y acá queda escrito.
| 7. Las aserciones sobre el CSS leen texto. Lo que importa se mide en Chromium
|    con `tests/navegador/cifra-comparada.py` sobre el banco de
|    `build/cifra-comparada/` (estilos computados y árbol de accesibilidad, con y
|    sin `prefers-reduced-motion`); esa prueba se salta sin `.venv-a11y`.
|
| Las aserciones con mensaje van como `expect(bool)->toBeTrue('...')`: en Pest
| el segundo argumento de `toContain()` es otra aguja, no un mensaje, y usarlo
| como mensaje desactiva la comprobación en silencio.
*/

/**
 * El HTML servido del componente, sin su bloque de estilos: los selectores
 * nombran clases que acá se cuentan sobre el markup, y el `@once` se sirve
 * una sola vez por proceso, así que contarlo dependería del orden de carga.
 */
function cifraComparadaHtml(string $atributos, array $datos = []): string
{
    $html = Blade::render("<x-muni::stat {$atributos} />", $datos);

    return (string) preg_replace('#<style\b.*?</style>#s', '', $html);
}

/** El fuente crudo del componente. */
function cifraComparadaFuente(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/stat.blade.php');
}

/**
 * El fuente sin comentarios: la prosa nombra a propósito lo que el componente
 * NO hace («display:none», «uniqid», «aria-live»), y buscarlo sobre el fuente
 * entero daría falsos positivos contra el propio comentario.
 */
function cifraComparadaFuenteSinComentarios(): string
{
    $fuente = cifraComparadaFuente();
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $fuente);
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', $fuente);

    return (string) preg_replace('#^\s*//.*$#m', '', $fuente);
}

/** El CSS que el componente lleva consigo en su bloque de estilos, sin comentarios. */
function cifraComparadaCss(): string
{
    preg_match_all('#<style>(.*?)</style>#s', cifraComparadaFuente(), $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

/** El interior de las llaves de la primera regla del CSS del componente para `$selector`. */
function cifraComparadaRegla(string $selector): string
{
    $css = cifraComparadaCss();
    $inicio = strpos($css, $selector);

    if ($inicio === false) {
        return '';
    }

    $abre = strpos($css, '{', $inicio);
    $cierra = strpos($css, '}', $abre);

    return $cierra === false ? '' : substr($css, $abre + 1, $cierra - $abre - 1);
}

it('dice «subió» o «bajó» en texto según deltaDir, aunque el delta venga sin signo', function () {
    $sube = cifraComparadaHtml('value="128" label="Ingresadas hoy" delta="12 %" delta-dir="up"');
    $baja = cifraComparadaHtml('value="97" label="Ingresadas hoy" delta="12 %" delta-dir="down"');

    expect(str_contains($sube, 'subió'))->toBeTrue('con delta-dir="up" el sentido tiene que ir en texto: «subió»');
    expect(str_contains($sube, 'bajó'))->toBeFalse('con delta-dir="up" no puede decir «bajó»');
    expect(str_contains($baja, 'bajó'))->toBeTrue('con delta-dir="down" el sentido tiene que ir en texto: «bajó»');
    expect(str_contains($baja, 'subió'))->toBeFalse('con delta-dir="down" no puede decir «subió»');

    // El texto va ANTES de la cifra, para que se oiga «subió 12 %» y no «12 % subió».
    expect(strpos($sube, 'subió') < strpos($sube, '12 %'))->toBeTrue('«subió» tiene que preceder al delta');
    expect(strpos($baja, 'bajó') < strpos($baja, '12 %'))->toBeTrue('«bajó» tiene que preceder al delta');
});

it('no inventa un sentido cuando el host no declara deltaDir', function () {
    $html = cifraComparadaHtml('value="128" label="Ingresadas hoy" delta="12 %"');

    expect(str_contains($html, 'subió'))->toBeFalse('sin delta-dir no hay dirección que anunciar');
    expect(str_contains($html, 'bajó'))->toBeFalse('sin delta-dir no hay dirección que anunciar');
    expect(str_contains($html, '<svg'))->toBeFalse('sin delta-dir no hay flecha que dibujar');
    expect(str_contains($html, '12 %'))->toBeTrue('el delta sí se muestra, como cifra neutra');
});

it('oculta el texto del sentido recortando, nunca con display:none', function () {
    $html = cifraComparadaHtml('value="128" label="Ingresadas hoy" delta="12 %" delta-dir="up"');

    expect(preg_match('#<span class="muni-stat__sr">subió\s*</span>#', $html) === 1)
        ->toBeTrue('el texto del sentido va en un span con la clase de ocultado visual del componente');

    $regla = cifraComparadaRegla('.muni-stat__sr');

    expect($regla !== '')->toBeTrue('el componente declara .muni-stat__sr en su propio bloque de estilos');
    expect(str_contains($regla, 'clip-path:inset(50%)'))->toBeTrue('el ocultado visual recorta con clip-path');
    expect(str_contains($regla, 'width:1px'))->toBeTrue('el ocultado visual deja la caja en 1px');
    expect(str_contains($regla, 'display:none'))->toBeFalse('display:none sacaría el texto del árbol de accesibilidad');
    expect(str_contains($regla, 'visibility:hidden'))->toBeFalse('visibility:hidden sacaría el texto del árbol de accesibilidad');
});

it('ata la cifra al rótulo con role="group" y aria-labelledby, sin cambiar el DOM', function () {
    $html = cifraComparadaHtml('value="128" label="Ingresadas hoy"');

    expect(preg_match('#<div class="muni-stat"[^>]*\brole="group"#', $html) === 1)
        ->toBeTrue('la raíz sigue siendo <div class="muni-stat"> y lleva role="group"');
    expect(preg_match('#<div class="muni-stat"[^>]*\baria-labelledby="muni-stat-ingresadas-hoy-rotulo"#', $html) === 1)
        ->toBeTrue('aria-labelledby apunta al id del rótulo, derivado del rótulo mismo');
    expect(preg_match('#id="muni-stat-ingresadas-hoy-rotulo"[^>]*>\s*Ingresadas hoy\s*<#', $html) === 1)
        ->toBeTrue('el rótulo visible lleva ese id');
    expect(str_contains($html, '<dl'))->toBeFalse('no se reestructura el DOM con un <dl>');
});

it('deriva el id del rótulo de forma determinista, o del id que pase el consumidor', function () {
    $a = cifraComparadaHtml('value="128" label="Ingresadas hoy"');
    $b = cifraComparadaHtml('value="128" label="Ingresadas hoy"');

    expect($a === $b)->toBeTrue('dos renders iguales dan el mismo id: nada de uniqid()');
    expect(str_contains(cifraComparadaFuenteSinComentarios(), 'uniqid'))->toBeFalse('el fuente no usa uniqid()');

    $conId = cifraComparadaHtml('value="128" label="Ingresadas hoy" id="hoy"');

    expect(preg_match('#<div class="muni-stat"[^>]*\bid="hoy"#', $conId) === 1)
        ->toBeTrue('el id del consumidor se conserva en la raíz');
    expect(str_contains($conId, 'aria-labelledby="hoy-rotulo"'))->toBeTrue('el id del rótulo cuelga del id del consumidor');
    expect(str_contains($conId, 'id="hoy-rotulo"'))->toBeTrue('el rótulo lleva el id derivado del consumidor');
    expect(str_contains($conId, 'muni-stat-ingresadas-hoy'))->toBeFalse('con id del consumidor no se usa el derivado del rótulo');
});

it('dos stats con el mismo rótulo repiten el id del rótulo salvo que el consumidor pase `id`', function () {
    // Es el contrato que documenta el README: el id derivado sale del rótulo y
    // de nada más (no hay uniqid() ni contador que lo distinga sin romper el
    // diffing de Livewire), así que dos «Pendientes» en la misma página
    // colisionan y axe lo caza como `duplicate-id-aria`. La salida es `id`.
    $sinId = Blade::render('<x-muni::stat value="12" label="Pendientes" /><x-muni::stat value="3" label="Pendientes" />');

    expect(substr_count($sinId, 'id="muni-stat-pendientes-rotulo"'))->toBe(2,
        'sin `id`, el id del rótulo se deriva solo del rótulo: dos iguales colisionan, y eso es lo que el README advierte');

    $conId = Blade::render('<x-muni::stat value="12" label="Pendientes" id="hoy" /><x-muni::stat value="3" label="Pendientes" id="ayer" />');
    $conId = (string) preg_replace('#<style\b.*?</style>#s', '', $conId);

    preg_match_all('#\bid="([^"]+)"#', $conId, $ids);

    expect(count($ids[1]) === count(array_unique($ids[1])))->toBeTrue('con `id` distinto en cada stat no queda ningún id repetido');
    // Dos miradas hacia delante: `merge()` escribe las defaults primero y el
    // `id` del consumidor al final, así que el orden de atributos no se fija.
    expect(preg_match('#<div class="muni-stat"(?=[^>]*\bid="hoy")(?=[^>]*\baria-labelledby="hoy-rotulo")#', $conId) === 1)
        ->toBeTrue('cada stat apunta a SU rótulo');
    expect(preg_match('#<div class="muni-stat"(?=[^>]*\bid="ayer")(?=[^>]*\baria-labelledby="ayer-rotulo")#', $conId) === 1)
        ->toBeTrue('cada stat apunta a SU rótulo');
    expect(str_contains($conId, 'id="hoy-rotulo"') && str_contains($conId, 'id="ayer-rotulo"'))->toBeTrue('los dos rótulos existen con su id');
});

it('dibuja una flecha legible, decorativa y con forma distinta por sentido', function () {
    $sube = cifraComparadaHtml('value="128" label="Ingresadas hoy" delta="12 %" delta-dir="up"');
    $baja = cifraComparadaHtml('value="97" label="Ingresadas hoy" delta="12 %" delta-dir="down"');

    expect(preg_match('#<svg[^>]*\baria-hidden="true"[^>]*\bwidth="12"[^>]*\bheight="12"#', $sube) === 1)
        ->toBeTrue('la flecha es un SVG de 12px, decorativo (el sentido ya va en texto)');
    expect(str_contains(cifraComparadaFuenteSinComentarios(), 'font-size:8px'))->toBeFalse('la flecha de 8px era ilegible');

    preg_match('#<svg.*?</svg>#s', $sube, $flechaSube);
    preg_match('#<svg.*?</svg>#s', $baja, $flechaBaja);

    expect(($flechaSube[0] ?? '') !== ($flechaBaja[0] ?? ''))->toBeTrue('subir y bajar tienen forma distinta, no solo color');
    expect(str_contains($flechaSube[0] ?? '', 'currentColor'))->toBeTrue('la flecha hereda el color del delta, sin literal');
});

it('pone las cifras en mono tabular con .muni-num y la declara en su propio bloque', function () {
    $html = cifraComparadaHtml('value="1.284" label="Ingresadas hoy" delta="12 %" delta-dir="up"');

    expect(preg_match('#class="muni-num"[^>]*>1\.284<#', $html) === 1)->toBeTrue('el valor lleva .muni-num');
    expect(preg_match('#class="muni-stat__delta muni-num"#', $html) === 1)->toBeTrue('el delta lleva .muni-num');

    $regla = cifraComparadaRegla('.muni-stat .muni-num');

    expect($regla !== '')->toBeTrue('el componente declara .muni-num acotada a .muni-stat en su bloque de estilos, para no depender de data-table');
    expect(str_contains($regla, 'font-variant-numeric:tabular-nums'))->toBeTrue('.muni-num es mono tabular');
    expect(str_contains($regla, 'var(--muni-font-mono)'))->toBeTrue('.muni-num usa la fuente mono del sistema');
});

it('sigue renderizando con solo value y label, y no se declara región viva por su cuenta', function () {
    $html = cifraComparadaHtml('value="42" label="Activos"');

    expect(str_contains($html, '42'))->toBeTrue('renderiza el valor');
    expect(str_contains($html, 'Activos'))->toBeTrue('renderiza el rótulo');
    expect(str_contains($html, 'role="group"'))->toBeTrue('el grupo existe aunque no haya delta');
    expect(str_contains($html, 'aria-live'))->toBeFalse('la región viva del polling es un contrato con el host, fuera de alcance');
    expect(str_contains($html, 'role="status"'))->toBeFalse('la región viva del polling es un contrato con el host, fuera de alcance');
});

it('escapa el rótulo, el valor y el delta', function () {
    $html = cifraComparadaHtml(
        ':value="$valor" :label="$rotulo" :delta="$delta" delta-dir="up"',
        ['valor' => '<b>1</b>', 'rotulo' => '<img src=x onerror=alert(1)>', 'delta' => '<i>2</i>'],
    );

    expect(str_contains($html, '<b>1</b>'))->toBeFalse('el valor sale escapado');
    expect(str_contains($html, '<img'))->toBeFalse('el rótulo sale escapado');
    expect(str_contains($html, '<i>2</i>'))->toBeFalse('el delta sale escapado');
    expect(str_contains($html, '&lt;b&gt;1&lt;/b&gt;'))->toBeTrue('el valor se ve como texto');
});

it('apaga la elevación del hover con movimiento reducido, también dentro del panel', function () {
    // El hover eleva la tarjeta con `transition: … var(--muni-dur)`. En
    // `muni-ui.css` el token baja a 0 ms bajo `prefers-reduced-motion`, pero
    // `muni-ui-filament.css` —la ÚNICA hoja que se carga dentro de un panel—
    // no lo baja: medido en el banco del panel, la transición seguía en 160 ms
    // con la preferencia activa. El componente la apaga por su cuenta, como ya
    // hacen command-palette y file-dropzone, y así no depende de la hoja.
    //
    // Con `!important`, y no es capricho: la transición viaja en el `style`
    // inline de la raíz, y una regla de clase sin `!important` pierde contra
    // él. La primera versión de esta regla pasó este test y el navegador la
    // desmintió: seguía en 160 ms. Por eso se exige el `!important` acá.
    $css = cifraComparadaCss();

    expect(preg_match('#@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.muni-stat\s*\{[^}]*transition\s*:\s*none\s*!important#s', $css) === 1)
        ->toBeTrue('el componente apaga su transición bajo prefers-reduced-motion: reduce con !important, porque la transición va inline en la raíz');
    expect(str_contains(cifraComparadaFuenteSinComentarios(), 'var(--muni-dur)'))
        ->toBeTrue('y sigue usando var(--muni-dur) como duración, que es el contrato del paquete');
});

it('no escribe colores literales', function () {
    $fuente = cifraComparadaFuenteSinComentarios();

    // `(?<!&)` deja pasar entidades HTML como `&#9650;`, que no son colores.
    expect(preg_match('/(?<!&)#[0-9a-fA-F]{3,8}\b/', $fuente) === 0)->toBeTrue('cero hex en el componente: todo sale de tokens --muni-*');
    expect(preg_match('/\b(rgb|hsl|oklch|oklab)a?\(/', $fuente) === 0)->toBeTrue('cero colores funcionales en el componente');
});

/*
 * El banco de navegador. Se genera acá y no en un script suelto porque este es
 * el único sitio del paquete con Blade arrancado (mismo criterio que
 * `GeneraVitrinaTest` y `GuardiaDeSesionTest`). Sale a `build/cifra-comparada/`,
 * ignorado por git, y lo abre Playwright (`.venv-a11y`) para comprobar lo que
 * un test de Blade no puede: que «subió» está en el árbol de accesibilidad y
 * mide 1 px en pantalla, que la flecha mide 12 px, que `.muni-num` cae en mono
 * ADENTRO del panel —donde solo se carga `muni-ui-filament.css`— y que el
 * contraste del delta pasa en las dos paletas y los dos temas.
 *
 * Son CUATRO páginas y no dos por lo mismo que la vitrina (DESIGN §7): dentro
 * de un panel Filament `muni-ui.css` no se carga, y la paleta del panel vale
 * distinto en 30 de 32 tokens. `panel-*` carga ÚNICAMENTE la hoja del panel y
 * el DOM mínimo que la hoja estiliza (`body.fi-body`, `main.fi-main`,
 * `section.fi-section`).
 */
it('genera el banco de navegador en build/cifra-comparada/', function () {
    $dir = __DIR__.'/../build/cifra-comparada';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    // Los cuatro casos del tablero de la central de cámaras: sube, baja, sin
    // sentido declarado (la cifra neutra) y con `id` + `class` del consumidor.
    $tarjetas = Blade::render(
        '<div class="banco-grid">'
        .'<x-muni::stat :value="128" label="Eventos hoy" delta="12 %" delta-dir="up" :spark="[42, 51, 48, 63, 70, 66, 81]" hint="contra el mismo turno de ayer" />'
        .'<x-muni::stat :value="9" label="Alarmas sin cerrar" tone="warn" delta="8 %" delta-dir="down" />'
        .'<x-muni::stat :value="14" label="Cámaras fuera de línea" tone="danger" delta="2" />'
        .'<x-muni::stat :value="1.284" label="Patrullajes del mes" tone="ok" id="patrullajes" class="banco-ancha" />'
        .'</div>'
    );

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
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'h1{font-size:20px;margin:0 0 16px}'
            .'.banco-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}'
            .'.banco-ancha{grid-column:1/-1}';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<h1>Central de cámaras — turno de hoy</h1><section class="fi-section">'.$tarjetas.'</section></main></div></body>'
            : '<body><main id="muni-contenido" tabindex="-1"><h1>Central de cámaras — turno de hoy</h1>'.$tarjetas.'</main></body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco stat — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDeLaReja(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que un test de Blade no puede ver, medido en Chromium sobre el banco. Las
 * pruebas de arriba leen el TEXTO del CSS del componente, y eso ya engañó una
 * vez: la regla de movimiento reducido sin `!important` pasó el test y en el
 * navegador la transición seguía en 160 ms. `tests/navegador/cifra-comparada.py`
 * lee estilos computados y el árbol de accesibilidad en las cuatro páginas, con
 * y sin `prefers-reduced-motion`; acá solo se exige que salga en verde. Se
 * SALTA —no se finge— cuando no está `.venv-a11y`, que en CI no se instala.
 */
it('en Chromium: la transición muere con movimiento reducido también en el panel, .muni-num cae en mono tabular y «subió» está en el árbol ARIA midiendo 1 px', function () {
    $python = pythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay Chromium: la verificación en navegador se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/cifra-comparada.py').' '
        .escapeshellarg(__DIR__.'/../build/cifra-comparada').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de <x-muni::stat> falló:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'transición(reduce)=none 0s'))))->toBe(4,
        "Las cuatro páginas tienen que reportar la transición apagada con la preferencia:\n".implode("\n", $lineas));
});
