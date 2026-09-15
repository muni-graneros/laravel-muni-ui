<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/**
 * LAS MIGAS DE RUTA (`<x-muni::breadcrumb>`).
 *
 * Qué vigila, y por qué cada candado existe:
 *
 *  1. ES UNA LISTA DE VERDAD. Hasta la reparación eran `<span>` y `<a>` sueltos
 *     dentro de un `<nav>`: sin `<ol>`/`<li>` el lector no anuncia ni la
 *     estructura ni la posición («lista de 3, elemento 2»). El `<ol>` lleva
 *     además `role="list"` explícito porque Safari + VoiceOver le quitan la
 *     semántica de lista a cualquier lista con `list-style:none`, que es
 *     justamente lo que necesita el maquetado horizontal.
 *  2. EL ENLACE NO SE DISTINGUE SOLO POR EL COLOR (WCAG 2.2 AA 1.4.1). Antes los
 *     enlaces llevaban `text-decoration:none` y su único diferenciador frente al
 *     texto plano era `--muni-muted` contra `--muni-text`. Ahora van subrayados.
 *  3. EL FOCO SE VE (2.4.7 y 1.4.11). El archivo no tenía una sola regla
 *     `:focus-visible`: el foco quedaba en manos del contorno del navegador, que
 *     dentro de un panel Filament se pierde.
 *  4. EL `aria-label` DEL CONSUMIDOR NO SE DUPLICA. Estaba escrito ANTES de
 *     `$attributes->merge()`, así que un `aria-label` propio del consumidor se
 *     emitía dos veces en la misma etiqueta y ganaba el del paquete.
 *  5. UNA MIGA SIN ETIQUETA NO TUMBA LA PÁGINA. `{{ $item['label'] }}` lanzaba
 *     «undefined array key» en producción, y un enlace sin nombre accesible
 *     tampoco es una salida aceptable (2.4.4): la miga sin texto se descarta.
 *  6. EL SEPARADOR NO SE CUELA DELANTE DE LA PRIMERA MIGA. La condición era
 *     `$i > 0` sobre la CLAVE del arreglo: con `$items` asociativo o filtrado con
 *     `array_filter`, «inicio» > 0 se compara como cadena y da verdadero, así que
 *     aparecía un chevrón suelto antes de la primera miga.
 *
 * `expect($x)->toContain($y, 'mensaje')` NO sirve acá: en Pest el segundo
 * argumento es otra aguja que buscar, no un mensaje, y la aserción se desactiva
 * sin avisar. Todo va con `expect(bool)->toBeTrue('mensaje')`.
 */

/** El fuente del componente, sin comentarios de Blade ni de CSS. */
function fuenteDeMigas(): string
{
    $fuente = file_get_contents(__DIR__.'/../resources/views/components/breadcrumb.blade.php');

    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El contenido de los bloques de estilo del componente ya renderizado. */
function estilosDeMigas(string $html): string
{
    preg_match_all('#<style>(.*?)</style>#s', $html, $m);

    return implode("\n", $m[1]);
}

/** El cuerpo de la primera regla CSS cuyo selector contenga `$aguja`. */
function reglaDeMigas(string $css, string $aguja): string
{
    preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    foreach ($reglas as $regla) {
        if (str_contains($regla[1], $aguja)) {
            return $regla[2];
        }
    }

    return '';
}

/** Tres migas realistas del panel de Discapacidad. */
function migasDeEjemplo(): string
{
    return '<x-muni::breadcrumb :items="['
        .'[\'label\' => \'Inicio\', \'url\' => \'/panel\'],'
        .'[\'label\' => \'Credenciales\', \'url\' => \'/panel/credenciales\'],'
        .'[\'label\' => \'Solicitud 2026-0412\'],'
        .']" />';
}

it('emite una lista de verdad: nav con ol y una li por miga', function () {
    $html = Blade::render(migasDeEjemplo());

    expect((bool) preg_match('#<nav\b[^>]*>\s*<ol\b#s', $html))->toBeTrue(
        'El <nav> no contiene un <ol>: sin lista el lector no anuncia la estructura ni la posición de cada miga.'
    );

    expect(substr_count($html, '<li'))->toBe(
        3,
        'No hay una <li> por miga: tres migas tienen que dar tres elementos de lista.'
    );

    expect((bool) preg_match('/<ol\b[^>]*role="list"/', $html))->toBeTrue(
        'El <ol> no declara role="list" y se maqueta con list-style:none: Safari con VoiceOver le quita '.
        'la semántica de lista y vuelve a leerse como texto suelto.'
    );
});

it('la última miga es la página actual, no es enlace, y las anteriores sí lo son', function () {
    $html = Blade::render(migasDeEjemplo());

    expect(substr_count($html, 'aria-current="page"'))->toBe(
        1,
        'La página actual tiene que marcarse una sola vez con aria-current="page".'
    );

    expect((bool) preg_match('#<a\b[^>]*>\s*Solicitud 2026-0412#s', $html))->toBeFalse(
        'La última miga se emitió como enlace: es la página en la que ya estás.'
    );

    expect(str_contains($html, 'href="/panel"') && str_contains($html, 'href="/panel/credenciales"'))->toBeTrue(
        'Las migas anteriores perdieron su enlace: sin ellas la navegación hacia arriba desaparece.'
    );

    expect((bool) preg_match('/aria-current="page"[^>]*>\s*Solicitud 2026-0412/s', $html)
        || (bool) preg_match('/>\s*Solicitud 2026-0412\s*</s', $html))->toBeTrue(
            'La etiqueta de la miga actual no se rinde.'
        );
});

it('el enlace de una miga se distingue sin depender del color', function () {
    $css = estilosDeMigas(Blade::render(migasDeEjemplo()));

    expect($css !== '')->toBeTrue('El componente no emite su bloque de estilos: dentro de un panel Filament no hay otra hoja que lo cubra.');

    $regla = reglaDeMigas($css, 'a.muni-crumb');

    expect(str_contains($regla, 'text-decoration:underline') || str_contains($regla, 'text-decoration: underline'))->toBeTrue(
        'El enlace de la miga no va subrayado: su único diferenciador frente al texto plano vuelve a ser el color (WCAG 1.4.1).'
    );

    expect((bool) preg_match('/text-decoration\s*:\s*none/i', fuenteDeMigas()))->toBeFalse(
        'Alguna regla vuelve a apagar el subrayado de las migas.'
    );
});

it('el enlace declara su propio foco con outline de 3px y la cadena de respaldo', function () {
    $css = estilosDeMigas(Blade::render(migasDeEjemplo()));

    expect(str_contains($css, ':focus-visible'))->toBeTrue(
        'No hay ninguna regla :focus-visible: el teclado se queda sin indicador donde el contorno del navegador se pierde.'
    );

    $regla = reglaDeMigas($css, ':focus-visible');

    expect(str_contains($regla, 'outline:3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeTrue(
        'El foco no usa el outline de 3px con la cadena de respaldo: dentro de Filament la box-shadow del anillo se computa transparente.'
    );
});

it('el área táctil de la miga llega a 24px', function () {
    $css = estilosDeMigas(Blade::render(migasDeEjemplo()));

    $regla = reglaDeMigas($css, '.muni-crumb ');

    expect((bool) preg_match('/min-height\s*:\s*(\d+)px/', $regla, $m) && (int) $m[1] >= 24)->toBeTrue(
        'La miga no reserva 24px de alto: WCAG 2.2 AA 2.5.8 pide ese mínimo de área para un objetivo.'
    );
});

it('una miga sin espacios puede partirse y no desborda la pantalla angosta', function () {
    $css = estilosDeMigas(Blade::render(migasDeEjemplo()));

    $regla = reglaDeMigas($css, '.muni-crumb ');

    expect(str_contains($regla, 'overflow-wrap:anywhere') && str_contains($regla, 'min-width:0'))->toBeTrue(
        'Un folio largo sin espacios («Certificado…20260412Bugueno») no puede partirse: medido en Chromium y '.
        'Firefox a 390px de ancho, la página desbordaba 120px en horizontal.'
    );
});

it('el aria-label del consumidor reemplaza al del paquete y no se duplica', function () {
    $html = Blade::render('<x-muni::breadcrumb aria-label="Dónde estoy" :items="[[\'label\' => \'Inicio\', \'url\' => \'/\'], [\'label\' => \'Fin\']]" />');

    expect(substr_count($html, 'aria-label='))->toBe(
        1,
        'Se emitieron dos aria-label en la misma etiqueta: el del paquete se escribía antes del derrame de atributos.'
    );

    expect(str_contains($html, 'aria-label="Dónde estoy"'))->toBeTrue(
        'El aria-label del consumidor no llegó al <nav>.'
    );
});

it('con un aria-labelledby del consumidor no se cuela además el aria-label del paquete', function () {
    $html = Blade::render('<x-muni::breadcrumb aria-labelledby="titulo-ruta" :items="[[\'label\' => \'Inicio\']]" />');

    expect(str_contains($html, 'aria-label='))->toBeFalse(
        'El <nav> lleva aria-labelledby y además el aria-label por defecto: dos nombres compitiendo en el mismo landmark.'
    );
});

it('sin aria-label propio, la navegación se llama Ruta', function () {
    $html = Blade::render(migasDeEjemplo());

    expect(str_contains($html, 'aria-label="Ruta"'))->toBeTrue(
        'La navegación perdió su nombre: con dos <nav> en la pantalla el lector no puede distinguirlos.'
    );
});

it('una miga sin etiqueta no tumba la página ni deja un enlace sin nombre', function () {
    $html = Blade::render('<x-muni::breadcrumb :items="['
        .'[\'label\' => \'Inicio\', \'url\' => \'/panel\'],'
        .'[\'url\' => \'/panel/huerfana\'],'
        .'[\'label\' => \'   \'],'
        .'[\'label\' => \'Solicitud 2026-0412\'],'
        .']" />');

    expect(str_contains($html, '/panel/huerfana'))->toBeFalse(
        'Una miga sin etiqueta se emitió igual: un enlace sin nombre accesible incumple 2.4.4 y el lector solo lee la URL.'
    );

    expect(substr_count($html, '<li'))->toBe(
        2,
        'Las migas sin texto tienen que descartarse, no dibujarse vacías.'
    );

    expect(substr_count($html, 'aria-current="page"'))->toBe(
        1,
        'Al descartar migas vacías se perdió la marca de página actual sobre la última miga que sí tiene texto.'
    );
});

it('un arreglo asociativo no dibuja un separador delante de la primera miga', function () {
    $html = Blade::render('<x-muni::breadcrumb :items="['
        .'\'inicio\' => [\'label\' => \'Inicio\', \'url\' => \'/panel\'],'
        .'\'actual\' => [\'label\' => \'Credenciales\'],'
        .']" />');

    expect(substr_count($html, '<svg'))->toBe(
        1,
        'Con claves no numéricas aparece un chevrón suelto antes de la primera miga: la condición miraba la clave del arreglo, no la posición.'
    );
});

it('acepta una lista de textos sueltos sin arreglo por miga', function () {
    $html = Blade::render('<x-muni::breadcrumb :items="[\'Inicio\', \'Credenciales\']" />');

    expect(substr_count($html, '<li'))->toBe(
        2,
        'Una miga escrita como texto suelto se descartó: es la forma más corta de escribir una ruta sin enlaces.'
    );
});

it('sin migas no deja un landmark de navegación vacío', function () {
    $html = Blade::render('<x-muni::breadcrumb />');

    expect(str_contains($html, '<nav'))->toBeFalse(
        'Sin migas se emite igual un <nav> vacío: un landmark sin contenido es ruido en la lista de regiones del lector.'
    );
});

it('la etiqueta de la miga se escapa', function () {
    $html = Blade::render('<x-muni::breadcrumb :items="[[\'label\' => \'<b>Obras</b>\', \'url\' => \'/o\'], [\'label\' => \'Fin\']]" />');

    expect(str_contains($html, '<b>Obras</b>'))->toBeFalse(
        'La etiqueta de la miga se emite sin escapar: un nombre de trámite guardado por un vecino inyectaría HTML.'
    );
    expect(str_contains($html, '&lt;b&gt;Obras&lt;/b&gt;'))->toBeTrue(
        'La etiqueta escapada no aparece en la salida.'
    );
});

it('la transición del enlace se apaga con movimiento reducido, también dentro del panel', function () {
    $css = estilosDeMigas(Blade::render(migasDeEjemplo()));

    expect((bool) preg_match('/@media\s*\(prefers-reduced-motion\s*:\s*reduce\)\s*\{[^}]*a\.muni-crumb[^}]*transition\s*:\s*none/s', $css))->toBeTrue(
        'La transición de la miga cuelga solo de var(--muni-dur): medido en Chromium y Firefox, dentro del panel '.
        '(muni-ui-filament.css, que no baja el token) seguía computando 0,16 s con prefers-reduced-motion: reduce.'
    );
});

it('las migas no se imprimen', function () {
    $css = estilosDeMigas(Blade::render(migasDeEjemplo()));

    expect((bool) preg_match('/@media\s+print\s*\{[^}]*\.muni-crumbs[^}]*display\s*:\s*none/s', $css))->toBeTrue(
        'Las migas siguen saliendo en papel: en una hoja impresa la navegación no lleva a ninguna parte y ocupa la cabecera.'
    );
});

it('no escribe un solo color literal fuera de los tokens', function () {
    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', fuenteDeMigas(), $m);

    $literales = array_values(array_filter($m[0], fn (string $c) => strtolower($c) !== '#767676'));

    expect($literales)->toBe(
        [],
        'Hay colores literales en el componente: la identidad de cada municipio se cambia redefiniendo tokens, '.
        'y un color escrito adentro no se puede cambiar sin editar el paquete. '.implode(' | ', $literales)
    );
});

// ---------------------------------------------------------------------------
// La banda de cabecera: título y migas dejan de ser piezas sueltas
// ---------------------------------------------------------------------------

/*
 * La ficha no pide solo reparar las migas: pide que `page-header` —el que
 * siempre emite el único <h1> de la pantalla— gane un slot `migas`, para que la
 * banda «título + ruta» se componga igual en las nueve aplicaciones en vez de
 * que cada pantalla la invente (convención de AdminLTE 4). Sin eso las dos
 * piezas siguen sueltas y las migas divergen del menú pantalla por pantalla.
 *
 * El slot va ANTES del <h1> en el orden del DOM a propósito: es el orden en que
 * un lector de pantalla recorre la banda, y es donde el ojo espera la ruta.
 */
it('page-header gana un slot migas que se rinde antes del h1, y sigue habiendo un solo h1', function () {
    $html = Blade::render(
        '<x-muni::page-header title="Solicitud 2026-0412" subtitle="Credencial de discapacidad">'
        .'<x-slot:migas>'.migasDeEjemplo().'</x-slot:migas>'
        .'</x-muni::page-header>'
    );

    expect(str_contains($html, '<nav'))->toBeTrue(
        'El slot `migas` de page-header no se rindió: la banda título+ruta sigue siendo cosa de cada pantalla.'
    );

    expect(substr_count($html, '<h1'))->toBe(
        1,
        'La cabecera dejó de emitir exactamente un <h1>: la convención que la ficha trae de AdminLTE es justamente que haya uno solo.'
    );

    $posNav = strpos($html, '<nav');
    $posH1 = strpos($html, '<h1');

    expect($posNav !== false && $posH1 !== false && $posNav < $posH1)->toBeTrue(
        'Las migas se emiten después del <h1>: el lector recorre la banda al revés de como se lee.'
    );

    expect(str_contains($html, 'Solicitud 2026-0412'))->toBeTrue('El título de la cabecera no se rinde.');
});

it('page-header sin slot migas no emite ningún contenedor de ruta', function () {
    $html = Blade::render('<x-muni::page-header title="Credenciales" />');

    expect(str_contains($html, 'muni-page-header__migas'))->toBeFalse(
        'Sin slot `migas` la cabecera deja igual el contenedor de la ruta: es un hueco vacío encima del título en toda pantalla que no las use.'
    );

    expect(substr_count($html, '<h1'))->toBe(1, 'La cabecera sin migas dejó de emitir su <h1>.');
});

it('las acciones de la cabecera siguen funcionando junto a las migas', function () {
    $html = Blade::render(
        '<x-muni::page-header title="Credenciales" eyebrow="Discapacidad">'
        .'<x-slot:migas>'.migasDeEjemplo().'</x-slot:migas>'
        .'<x-slot:actions><button type="button">Nueva solicitud</button></x-slot:actions>'
        .'</x-muni::page-header>'
    );

    expect(str_contains($html, 'Nueva solicitud'))->toBeTrue(
        'El slot `actions` dejó de rendirse al añadir `migas`: la cabecera tiene consumidores que ya lo usan.'
    );
    expect(str_contains($html, 'Discapacidad'))->toBeTrue('El eyebrow dejó de rendirse.');
});

// ---------------------------------------------------------------------------
// El contrato de `items` no se estrecha
// ---------------------------------------------------------------------------

/*
 * La versión anterior leía `$item['label']` y `$item['url']`, que funciona con
 * CUALQUIER ArrayAccess —una Collection por miga, por ejemplo—. Normalizar con
 * un `is_array()` pelado descartaba esas migas en silencio, y como ahora sin
 * migas no se emite el <nav>, el anfitrión perdía la navegación ENTERA sin un
 * solo aviso en consola: el modo de falla contra el que advierte DESIGN §8.
 */
it('una miga escrita como Collection o como cualquier ArrayAccess sigue valiendo', function () {
    $html = Blade::render('<x-muni::breadcrumb :items="[
        collect([\'label\' => \'Inicio\', \'url\' => \'/panel\']),
        collect([\'label\' => \'Credenciales\', \'url\' => \'/panel/credenciales\']),
        collect([\'label\' => \'Solicitud 2026-0412\']),
    ]" />');

    expect(substr_count($html, '<li'))->toBe(
        3,
        'Una miga ArrayAccess (Collection) se descartó: el contrato se estrechó y el anfitrión pierde la navegación entera sin aviso.'
    );

    expect(str_contains($html, 'href="/panel/credenciales"'))->toBeTrue(
        'La URL de una miga ArrayAccess no llegó a la salida.'
    );

    expect(substr_count($html, 'aria-current="page"'))->toBe(1, 'La última miga ArrayAccess no quedó marcada como página actual.');
});

it('una miga que sea un objeto Arrayable también vale', function () {
    $html = Blade::render('<x-muni::breadcrumb :items="[
        new Illuminate\Support\Fluent([\'label\' => \'Inicio\', \'url\' => \'/panel\']),
        new Illuminate\Support\Fluent([\'label\' => \'Patentes\']),
    ]" />');

    expect(substr_count($html, '<li'))->toBe(
        2,
        'Una miga Arrayable se descartó: es la forma en que llegan las migas armadas por un servicio del anfitrión.'
    );
});

// ---------------------------------------------------------------------------
// Verificación en navegador
// ---------------------------------------------------------------------------

/*
 * El banco de navegador. Se genera acá, que es el único sitio del paquete con
 * Blade arrancado (mismo criterio que `GeneraVitrinaTest`, `SolapasDeRutaTest` y
 * `GuardiaDeSesionTest`). Sale a `build/migas-de-ruta/`, ignorado por git.
 *
 * Las pruebas de arriba leen el TEXTO del CSS del componente —que el enlace
 * declare `text-decoration:underline`, que la miga declare `overflow-wrap`, que
 * el foco declare su outline—, y eso ya engañó una vez en `stat`: la regla pasó
 * el test de texto y en el navegador el defecto seguía vivo. Declarar una regla
 * no es que la regla GANE: basta una especificidad mayor, un `!important` de la
 * hoja del panel o un token vacío para que lo computado diga otra cosa.
 *
 * Son CUATRO páginas por lo mismo que la vitrina (DESIGN §7): dentro de un panel
 * Filament `muni-ui.css` no se carga, así que `panel-*` carga ÚNICAMENTE
 * `muni-ui-filament.css` y el DOM mínimo que esa hoja estiliza.
 *
 * La segunda banda del banco lleva un folio largo sin espacios a propósito: es
 * el caso que desbordaba 120 px a 390 px de ancho.
 */
it('genera el banco de navegador en build/migas-de-ruta/', function () {
    $dir = __DIR__.'/../build/migas-de-ruta';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $banda = Blade::render(
        '<x-muni::page-header title="Solicitud 2026-0412" subtitle="Credencial de discapacidad · Ana Soto Rivera" eyebrow="Discapacidad">'
        .'<x-slot:migas>'
        .'<x-muni::breadcrumb :items="['
        ."['label' => 'Inicio', 'url' => '#panel'],"
        ."['label' => 'Credenciales', 'url' => '#credenciales'],"
        ."['label' => 'Solicitud 2026-0412'],"
        .']" />'
        .'</x-slot:migas>'
        .'<x-slot:actions><button type="button">Imprimir credencial</button></x-slot:actions>'
        .'</x-muni::page-header>'
        .'<x-muni::breadcrumb aria-label="Ruta del expediente" id="ruta-larga" :items="['
        ."['label' => 'Inicio', 'url' => '#panel'],"
        ."['label' => 'CertificadoDeDiscapacidad20260412BuguenoMellaSolicitudExtensa'],"
        .']" />'
        .'<p>Contenido de la solicitud.</p>'
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

        /* El armazón no aporta ni un color: fondo y texto salen de los mismos
           tokens que lee el componente. */
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<section class="fi-section">'.$banda.'</section></main></div></body>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$banda.'</main></body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco breadcrumb — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDeLaRejaMigas(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que un test de Blade no puede ver, medido en Chromium Y Firefox sobre el
 * banco: el subrayado COMPUTADO del enlace (1.4.1), el recorrido real de Tab con
 * `document.activeElement`, el outline computado con su contraste contra el
 * fondo efectivo (2.4.7 y 1.4.11), el alto real del objetivo (2.5.8), el
 * contraste del texto de cada miga, que a 390 px el documento no se desplace en
 * horizontal con un folio sin espacios (1.4.10), que con `prefers-reduced-motion`
 * la transición compute 0 s, que emulando la impresión las migas computen
 * `display:none`, y que dentro de la cabecera la ruta quede ENCIMA del único
 * `<h1>`. Se SALTA —no se finge— cuando no está `.venv-a11y`, que en CI no se
 * instala.
 */
it('en Chromium y Firefox: el subrayado computa, el foco se ve, la miga larga no desborda a 390px y en papel desaparece', function () {
    $python = pythonDeLaRejaMigas();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/migas-de-ruta.py').' '
        .escapeshellarg(__DIR__.'/../build/migas-de-ruta').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de las migas de ruta falló:\n".implode("\n", $lineas));
});
