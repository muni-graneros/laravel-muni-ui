<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL PUNTO DE PARTIDA DE TODA PANTALLA NUEVA
|--------------------------------------------------------------------------
|
| `<x-muni::plantilla-pantalla>` es la ficha `plantilla-pantalla` de
| docs/GAP-ANALYSIS.md: el armazón + el enlace de salto + los landmarks
| nombrados + las migas + UN SOLO <h1> + la región de mensajes + el contenido
| + el pie, todo en una etiqueta, para que las seis aplicaciones no vuelvan a
| armar la misma banda a mano —ni a olvidarse de la mitad— pantalla por
| pantalla. La ficha lo dice así: «es la pieza que garantiza el Decreto
| N°1/2015 desde el primer commit en vez de auditarlo después».
|
| Lo que esta prueba vigila, y por qué cada cosa:
|
| · **El documento sale completo y con el salto primero.** El armazón lo pone
|   `app-shell`/`dashboard-shell` (SKILL.md: «no los rehagas»), así que lo que
|   se comprueba acá es que la plantilla los USE y no los reemplace: `<!DOCTYPE`,
|   el enlace de salto antes de la cabecera y `<main id="muni-contenido"
|   tabindex="-1">`.
| · **Un solo <h1>.** Dos <h1> en una pantalla es la forma más barata de perder
|   a alguien que navega por encabezados. El de la plantilla es el de
|   `page-header`, y es el único.
| · **Las migas van ENCIMA del título, como lista y con `aria-current`.**
|   El orden del DOM es el orden en que se lee la banda.
| · **La región de mensajes existe SIEMPRE y nace vacía.** Es el hueco que la
|   ficha señala: «un error de servidor tras un POST no tiene dónde anunciarse».
|   Una región viva que se inserta junto con su texto no se anuncia.
| · **El aviso se VE y se anuncia una sola vez.** Texto visible (doctrina de
|   `announcer`) + el anuncio del anunciador. Si además el recuadro llevara
|   `role="status"`, el lector lo diría dos veces.
| · **El pie NO es un `contentinfo` anidado.** Va dentro de la sección de la
|   pantalla —que es contenido de sección— así que el HTML lo expone como
|   genérico y no como un segundo landmark de pie DENTRO de `<main>`, que es
|   justo lo que axe marca y lo que confunde al que navega por landmarks.
| · **La región de contenido está NOMBRADA.** Los armazones emiten un `<main>`
|   sin nombre y la plantilla no puede rotularlo desde adentro; lo que sí puede
|   es nombrar su propia sección con el título de la pantalla.
| · **El foco no queda debajo de la cabecera fija.** `scroll-margin-top` con
|   `var(--muni-topbar-h)` para todo lo que reciba el foco dentro de la banda:
|   es la tecla obligatoria de la ficha («el anillo de foco nunca queda oculto
|   tras el header sticky»).
| · **Ley 21.719 y contrato del paquete.** La plantilla recibe strings ya
|   redactados; no consulta `request()`, ni `Auth`, ni `Gate`, ni permisos.
|
| Todo va con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
| de `toContain()` es otra aguja, no un mensaje, y la aserción se apaga sin
| avisar.
*/

/** La ruta del componente. */
function plantillaRuta(): string
{
    return __DIR__.'/../resources/views/components/plantilla-pantalla.blade.php';
}

/** El fuente crudo del componente. */
function plantillaFuente(): string
{
    $ruta = plantillaRuta();

    return file_exists($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * El fuente SIN comentarios CSS ni comentarios Blade: si no, cada patrón que se
 * busca matchea la prosa que explica por qué ese patrón está prohibido.
 */
function plantillaFuenteLimpia(): string
{
    return (string) preg_replace(['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'], '', plantillaFuente());
}

/** El HTML servido de la plantilla. */
function plantillaHtml(string $atributos = '', string $contenido = 'Contenido de la pantalla.'): string
{
    return Blade::render("<x-muni::plantilla-pantalla {$atributos}>{$contenido}</x-muni::plantilla-pantalla>");
}

/** El ejemplo completo: el que se copia y el que se mide en el navegador. */
function plantillaHtmlCompleta(string $extra = ''): string
{
    return plantillaHtml(
        'title="Patentes morosas" '
        .'subtitle="3.412 contribuyentes con deuda vigente al 6 de septiembre." '
        .'system="Rentas y Patentes" system-subtitle="Municipalidad de Graneros" '
        .':migas="[[\'label\' => \'Inicio\', \'url\' => \'/\'], [\'label\' => \'Rentas\', \'url\' => \'/rentas\'], [\'label\' => \'Patentes morosas\']]" '
        .'aviso="No se pudo conectar con Tesorería: el listado puede estar desactualizado." '
        .'aviso-tone="warn" '
        .$extra,
        '<p>El listado de patentes va acá.</p>'
    );
}

// ---------------------------------------------------------------------------
// El documento: armazón, salto y destino del salto
// ---------------------------------------------------------------------------

it('rinde el documento completo apoyándose en el armazón, no reemplazándolo', function () {
    $html = plantillaHtmlCompleta();

    expect(str_contains($html, '<!DOCTYPE html>'))->toBeTrue('La plantilla no rindió un documento completo.');
    expect(str_contains($html, 'muni-skip-link'))->toBeTrue(
        'Sin el enlace de salto no hay 2.4.1: es lo primero que la ficha pide.'
    );
    expect(str_contains($html, 'id="muni-contenido"') && str_contains($html, 'tabindex="-1"'))->toBeTrue(
        'El destino del salto tiene que seguir siendo <main id="muni-contenido" tabindex="-1">.'
    );

    // El salto tiene que ganarle en orden de tabulación a la cabecera.
    expect(strpos($html, 'muni-skip-link') < strpos($html, '<main'))->toBeTrue(
        'El enlace de salto quedó DESPUÉS del contenido: el orden de tabulación lo deja detrás de lo que venía a saltarse.'
    );

    // Y el armazón no se reescribe: se usa el que ya existe.
    $fuente = plantillaFuenteLimpia();
    expect(str_contains($fuente, '<!DOCTYPE') || str_contains($fuente, '<!doctype'))->toBeFalse(
        'La plantilla escribe su propio <html>: SKILL.md dice que los armazones no se rehacen.'
    );
});

it('elige el armazón por el prop y cae en el de panel ante un valor desconocido', function () {
    // El armazón con barra lateral trae el hamburguesa; el público, no.
    expect(str_contains(plantillaHtml('title="Panel"'), 'muni-ds__burger'))->toBeTrue(
        'Por defecto la plantilla tiene que rendir el armazón de panel.'
    );

    $publico = plantillaHtml('title="Solicitud de credencial" armazon="app"');
    expect(str_contains($publico, 'muni-ds__burger'))->toBeFalse(
        'Con armazon="app" no puede aparecer el armazón de panel.'
    );
    expect(str_contains($publico, 'muni-topbar') || str_contains($publico, 'muni-tb'))->toBeTrue(
        'Con armazon="app" tiene que aparecer la cabecera del armazón público.'
    );

    expect(str_contains(plantillaHtml('title="X" armazon="lo-que-sea"'), 'muni-ds__burger'))->toBeTrue(
        'Un armazón desconocido tiene que caer en el de panel, no dejar la pantalla sin armazón.'
    );
});

it('el título del documento es el de la pantalla y el del sistema, y se puede pisar', function () {
    $html = plantillaHtmlCompleta();

    expect(str_contains($html, '<title>Patentes morosas · Rentas y Patentes</title>'))->toBeTrue(
        'El <title> del documento tiene que decir qué pantalla es y de qué sistema.'
    );

    expect(str_contains(
        plantillaHtml('title="Patentes morosas" system="Rentas" document-title="Morosas · Rentas · Graneros"'),
        '<title>Morosas · Rentas · Graneros</title>'
    ))->toBeTrue('`document-title` tiene que mandar sobre el título calculado.');
});

// ---------------------------------------------------------------------------
// Un solo <h1>, con la ruta encima
// ---------------------------------------------------------------------------

it('emite UN SOLO <h1> y es el título de la pantalla', function () {
    $html = plantillaHtmlCompleta();

    expect(substr_count($html, '<h1'))->toBe(1, 'La pantalla tiene que tener un solo <h1>.');
    expect((bool) preg_match('/<h1[^>]*>\s*Patentes morosas\s*<\/h1>/', $html))->toBeTrue(
        'El <h1> no es el título de la pantalla.'
    );
});

it('rinde las migas como lista navegable ENCIMA del título y marca la actual', function () {
    $html = plantillaHtmlCompleta();

    expect(str_contains($html, 'aria-label="Ruta"'))->toBeTrue('Las migas tienen que ser un landmark de navegación nombrado.');
    expect(str_contains($html, 'aria-current="page"'))->toBeTrue('La última miga es la página actual.');
    expect(strpos($html, 'aria-label="Ruta"') < strpos($html, '<h1'))->toBeTrue(
        'Las migas van ANTES del <h1> en el orden del DOM: es el orden en que se lee la banda.'
    );

    // Sin migas no se emite el landmark vacío ni el contenedor que lo envuelve.
    expect(str_contains(plantillaHtml('title="Patentes morosas"'), 'aria-label="Ruta"'))->toBeFalse(
        'Sin migas no puede emitirse una región de navegación vacía.'
    );
    expect(str_contains(plantillaHtml('title="Patentes morosas"'), '<div class="muni-page-header__migas"'))->toBeFalse(
        'Sin migas no puede emitirse el contenedor de las migas.'
    );

    // Defecto que encontró la revisión: un arreglo con migas que `breadcrumb`
    // descarta (ninguna trae label) contaba como «hay migas» y dejaba un
    // contenedor vacío con su margen encima del <h1>.
    foreach ([
        ':migas="[[\'x\' => 1]]"' => 'sin label',
        ':migas="[[\'label\' => \'   \']]"' => 'con label en blanco',
    ] as $atributo => $caso) {
        expect(str_contains(plantillaHtml('title="Patentes morosas" '.$atributo), 'muni-page-header__migas" style'))->toBeFalse(
            "Migas {$caso}: breadcrumb no emite nada, así que tampoco puede quedar su contenedor."
        );
    }

    // Una miga legible entre descartadas se sigue rindiendo.
    $mixtas = plantillaHtml('title="Patentes morosas" :migas="[[\'x\' => 1], [\'label\' => \'Inicio\', \'url\' => \'/\']]"');
    expect(str_contains($mixtas, 'aria-label="Ruta"') && str_contains($mixtas, '>Inicio<'))->toBeTrue(
        'Una miga con label entre otras descartadas tiene que seguir apareciendo.'
    );
});

it('un título en blanco no deja una región sin nombre ni un <h1> vacío', function () {
    // Defecto que encontró la revisión: title="" producía aria-label="" y un <h1>
    // vacío. La pantalla cae en el nombre del sistema, que es lo único que sabe.
    foreach (['title=""', 'title="   "'] as $atributo) {
        $html = plantillaHtml($atributo.' system="Rentas y Patentes"');

        expect(str_contains($html, 'aria-label=""'))->toBeFalse("Con {$atributo} la región quedó sin nombre.");
        expect((bool) preg_match('/<h1[^>]*>\s*<\/h1>/', $html))->toBeFalse("Con {$atributo} el <h1> salió vacío.");
        expect((bool) preg_match('/<h1[^>]*>\s*Rentas y Patentes\s*<\/h1>/', $html))->toBeTrue("Con {$atributo} el <h1> tiene que caer en el nombre del sistema.");
        expect(str_contains($html, '<title>Rentas y Patentes</title>'))->toBeTrue("Con {$atributo} el <title> no puede empezar con « · ».");
    }

    // Y con el sistema también en blanco, en el nombre del municipio.
    expect((bool) preg_match('/<h1[^>]*>\s*Municipalidad de Graneros\s*<\/h1>/', plantillaHtml('title="" system=""')))->toBeTrue(
        'Sin título ni sistema el <h1> cae en el nombre del municipio.'
    );
});

it('renderiza con solo el título, que es la línea de TodosRendericanTest', function () {
    $html = plantillaHtml('title="Inicio"', 'Contenido de prueba');

    expect(str_contains($html, 'Contenido de prueba'))->toBeTrue('La plantilla con solo `title` no rindió su contenido.');
});

it('si las migas llegan como ranura se rinden igual, encima del título, en vez de perderse', function () {
    $html = Blade::render(
        '<x-muni::plantilla-pantalla title="Patentes morosas">'
        .'<x-slot:migas><nav aria-label="Ruta propia"><a href="/">Inicio</a></nav></x-slot:migas>'
        .'<p>Contenido.</p></x-muni::plantilla-pantalla>'
    );

    expect(str_contains($html, 'aria-label="Ruta propia"'))->toBeTrue('Las migas pasadas como ranura desaparecieron sin avisar.');
    expect(strpos($html, 'aria-label="Ruta propia"') < strpos($html, '<h1'))->toBeTrue('Las migas de la ranura tienen que ir encima del <h1>.');
});

// ---------------------------------------------------------------------------
// La región de mensajes, que es el hueco que la ficha señala
// ---------------------------------------------------------------------------

it('siempre trae la región de mensajes, y nace vacía', function () {
    $html = plantillaHtml('title="Patentes morosas"');

    expect(substr_count($html, 'aria-live="polite"'))->toBe(1,
        'Tiene que haber UNA región cortés: dos anunciarían dos veces, cero deja el POST sin dónde anunciarse.'
    );
    expect(substr_count($html, 'aria-live="assertive"'))->toBe(1, 'Falta la región asertiva.');
    expect((bool) preg_match('/aria-live="polite"[^>]*>\s*<\//', $html))->toBeTrue(
        'La región viva nace con texto adentro: así no se anuncia, porque el lector tiene que estar observando el nodo antes.'
    );

    expect(str_contains(plantillaHtml('title="X" :announcer="false"'), 'aria-live="polite"'))->toBeFalse(
        'Con :announcer="false" el anfitrión pone la suya en el layout: la plantilla no puede duplicarla.'
    );
});

it('el aviso del servidor se VE y se anuncia UNA vez', function () {
    $html = plantillaHtmlCompleta();
    $texto = 'No se pudo conectar con Tesorería: el listado puede estar desactualizado.';

    expect(str_contains($html, $texto))->toBeTrue('El aviso tiene que verse: un anuncio invisible no le sirve a quien mira la pantalla.');
    expect(str_contains($html, 'data-muni-message="'.e($texto).'"'))->toBeTrue(
        'El aviso tiene que viajar al anunciador: es lo que lo dice tras un POST → redirect.'
    );
    expect(substr_count($html, 'role="status"') + substr_count($html, 'role="alert"'))->toBe(0,
        'El recuadro visible no lleva rol de mensaje: con el anunciador puesto, el lector lo diría dos veces.'
    );

    // El tono grave interrumpe; el resto espera turno.
    expect(str_contains(
        plantillaHtml('title="X" aviso="El giro fue rechazado." aviso-tone="danger"'),
        'data-muni-priority="assertive"'
    ))->toBeTrue('Un aviso grave tiene que anunciarse como asertivo.');

    expect(str_contains(plantillaHtmlCompleta(), 'data-muni-priority="polite"'))->toBeTrue(
        'Un aviso que no es grave espera turno: no interrumpe lo que el lector esté diciendo.'
    );

    // Sin aviso no hay recuadro.
    expect(str_contains(plantillaHtml('title="X"'), 'muni-alert'))->toBeFalse('Sin aviso no puede haber recuadro.');
});

it('con el anunciador apagado el aviso no se queda mudo: el recuadro toma el rol', function () {
    // Defecto que encontró la revisión: con :announcer="false" el único canal de
    // anuncio era el `message` del anunciador que se acababa de apagar, y el
    // recuadro salía sin rol. El error de servidor tras un POST —el caso que la
    // ficha existe para cubrir— se veía, pero el lector no lo decía nunca.
    $cortes = plantillaHtml('title="X" :announcer="false" aviso="La ficha quedó guardada."');

    expect(str_contains($cortes, 'aria-live="polite"'))->toBeFalse('Con el anunciador apagado no puede aparecer su región.');
    expect(substr_count($cortes, 'role="status"'))->toBe(1,
        'Sin anunciador, el recuadro del aviso tiene que llevar role="status": si no, el aviso no se anuncia nunca.'
    );
    expect(str_contains($cortes, 'role="alert"'))->toBeFalse('Un aviso que no es grave no interrumpe.');

    $grave = plantillaHtml('title="X" :announcer="false" aviso="El giro fue rechazado." aviso-tone="danger"');
    expect(substr_count($grave, 'role="alert"'))->toBe(1, 'Sin anunciador, un aviso grave tiene que llevar role="alert".');

    // «false» escrito como atributo de texto también apaga: si no, el anfitrión que
    // escribe announcer="false" obtiene dos anuncios sin darse cuenta.
    $texto = plantillaHtml('title="X" announcer="false" aviso="La ficha quedó guardada."');
    expect(str_contains($texto, 'aria-live="polite"'))->toBeFalse('announcer="false" (texto) tiene que apagar el anunciador igual que :announcer="false".');
    expect(substr_count($texto, 'role="status"'))->toBe(1, 'announcer="false" (texto) deja el aviso sin canal si el recuadro no toma el rol.');

    // Y sin aviso, apagar el anunciador no inventa ningún rol.
    expect(str_contains(plantillaHtml('title="X" :announcer="false"'), 'role="status"'))->toBeFalse('Sin aviso no hay nada que anunciar.');
});

// ---------------------------------------------------------------------------
// Landmarks: nombrados, y sin un pie anidado
// ---------------------------------------------------------------------------

it('nombra su región de contenido con el título de la pantalla', function () {
    $html = plantillaHtmlCompleta();

    expect((bool) preg_match('/<section[^>]*class="[^"]*muni-plantilla[^"]*"[^>]*aria-label="Patentes morosas"/', $html)
        || (bool) preg_match('/<section[^>]*aria-label="Patentes morosas"[^>]*class="[^"]*muni-plantilla/', $html))->toBeTrue(
            'La región de la pantalla tiene que llevar el nombre de la pantalla: el <main> del armazón sale sin nombre.'
        );
});

it('el pie va dentro de la sección y no es un segundo contentinfo adentro del <main>', function () {
    $html = plantillaHtmlCompleta();

    // La clase aparece también en el bloque de estilos: lo que se busca es la ETIQUETA.

    expect(str_contains($html, '<footer class="muni-plantilla__pie">'))->toBeTrue('La plantilla tiene que cerrar con un pie.');
    expect(str_contains($html, 'role="contentinfo"'))->toBeFalse(
        'Un contentinfo dentro de <main> es un landmark anidado: axe lo marca y confunde a quien navega por landmarks.'
    );

    $pie = strpos($html, '<footer class="muni-plantilla__pie">');
    expect($pie !== false && $pie < strpos($html, '</section>'))->toBeTrue(
        'El pie tiene que ir DENTRO de la sección: es lo que lo deja como pie de la sección y no como pie del documento.'
    );

    // El pie por defecto dice de quién es la pantalla.
    expect(str_contains($html, 'Rentas y Patentes') && str_contains($html, (string) date('Y')))->toBeTrue(
        'El pie por defecto nombra el sistema y el año.'
    );
});

it('el pie de fábrica no repite el nombre del municipio cuando el sistema ES el municipio', function () {
    // Defecto que encontró la revisión: con los props por defecto salía
    // «© 2026 Ilustre Municipalidad de Graneros · Municipalidad de Graneros».
    $pieDe = function (string $html): string {
        preg_match('#<footer class="muni-plantilla__pie">(.*?)</footer>#s', $html, $m);

        return trim(strip_tags($m[1] ?? ''));
    };

    $esperado = '© '.date('Y').' Ilustre Municipalidad de Graneros';

    foreach (['', 'system="Municipalidad de Graneros"', 'system="  municipalidad de graneros "', 'system="Ilustre Municipalidad de Graneros"', 'system=""'] as $atributo) {
        expect($pieDe(plantillaHtml('title="Inicio" '.$atributo)))->toBe($esperado, "Con «{$atributo}» el pie repite o ensucia la línea institucional.");
    }

    expect($pieDe(plantillaHtml('title="Inicio" system="Rentas y Patentes"')))->toBe($esperado.' · Rentas y Patentes');
});

it('una ranura de pie vacía apaga el pie, y una con contenido lo reemplaza', function () {
    $apagado = Blade::render(
        '<x-muni::plantilla-pantalla title="Patentes morosas"><x-slot:pie></x-slot:pie><p>Contenido.</p></x-muni::plantilla-pantalla>'
    );

    expect(str_contains($apagado, '<footer class="muni-plantilla__pie">'))->toBeFalse(
        'Una ranura de pie vacía es la forma de apagarlo: si no, no hay ninguna.'
    );

    $propio = Blade::render(
        '<x-muni::plantilla-pantalla title="Patentes morosas"><x-slot:pie>Última actualización: ayer a las 18:40.</x-slot:pie><p>Contenido.</p></x-muni::plantilla-pantalla>'
    );

    expect(str_contains($propio, 'Última actualización: ayer a las 18:40.'))->toBeTrue('El pie propio no se rindió.');
    expect(str_contains($propio, (string) date('Y').' Ilustre'))->toBeFalse('El pie propio tiene que REEMPLAZAR al de fábrica, no sumarse.');
});

// ---------------------------------------------------------------------------
// El contenido y las ranuras que la plantilla reenvía
// ---------------------------------------------------------------------------

it('reenvía las ranuras del armazón y las acciones de la cabecera', function () {
    $html = Blade::render(
        '<x-muni::plantilla-pantalla title="Patentes morosas" system="Rentas">'
        .'<x-slot:sidebar><nav aria-label="Secciones"><a href="/x">Patentes</a></nav></x-slot:sidebar>'
        .'<x-slot:topbar><span data-prueba="topbar">Turno 3</span></x-slot:topbar>'
        .'<x-slot:head><meta name="robots" content="noindex"></x-slot:head>'
        .'<x-slot:actions><button type="button">Exportar</button></x-slot:actions>'
        .'<p data-prueba="cuerpo">El listado.</p>'
        .'</x-muni::plantilla-pantalla>'
    );

    foreach ([
        'aria-label="Secciones"' => 'la barra lateral',
        'data-prueba="topbar"' => 'la cabecera del armazón',
        'name="robots"' => 'el <head>',
        '>Exportar<' => 'las acciones de la cabecera de página',
        'data-prueba="cuerpo"' => 'el contenido',
    ] as $aguja => $que) {
        expect(str_contains($html, $aguja))->toBeTrue('La plantilla no reenvió '.$que.'.');
    }

    // Las acciones van dentro de la banda del título, no sueltas al final.
    expect(strpos($html, '>Exportar<') < strpos($html, 'data-prueba="cuerpo"'))->toBeTrue(
        'Las acciones de la cabecera tienen que ir con el título, antes del contenido.'
    );
});

// ---------------------------------------------------------------------------
// Foco, movimiento y color: el contrato de DESIGN
// ---------------------------------------------------------------------------

it('reserva el alto de la cabecera fija para lo que reciba el foco', function () {
    $fuente = plantillaFuenteLimpia();

    expect((bool) preg_match('/scroll-margin-top:\s*[^;]*var\(--muni-topbar-h/', $fuente))->toBeTrue(
        'Sin scroll-margin-top el anillo de foco aterriza debajo del topbar fijo: es la tecla obligatoria de la ficha.'
    );
});

it('el indicador de foco es un outline con el respaldo canónico, nunca una sombra', function () {
    $fuente = plantillaFuenteLimpia();

    if (! preg_match('/:focus/', $fuente)) {
        expect(true)->toBeTrue('La plantilla no declara foco propio: lo traen los componentes que compone.');

        return;
    }

    expect((bool) preg_match('/outline:\s*3px\s+solid\s+var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/', $fuente))->toBeTrue(
        'El foco se dibuja con outline de 3px y el respaldo canónico (DESIGN §5): la box-shadow se pierde dentro de Filament.'
    );

    expect((bool) preg_match('/:focus[^{]*\{[^}]*box-shadow[^}]*\}/', preg_replace('/outline:[^;]*;/', '', $fuente)))->toBeFalse(
        'Hay un :focus cuyo único indicador es una box-shadow.'
    );
});

it('el anillo de base del contenido no le gana a los componentes ni se enciende en lo enfocado por programa', function () {
    $fuente = plantillaFuenteLimpia();

    // Medido en Chromium y Firefox: sin esta regla, un enlace escrito a mano en la
    // pantalla recibe el anillo del navegador, 1 px automático.
    expect((bool) preg_match('/:where\(\.muni-plantilla\)\s+:where\([^)]*a\[href\][^{]*:focus-visible\s*\{[^}]*outline:\s*3px solid var\(--muni-focus/s', $fuente))->toBeTrue(
        'Falta el anillo de base del contenido, o dejó de ir envuelto en :where().'
    );

    // Con :where() la regla pesa una sola clase (la de :focus-visible): el foco
    // propio de breadcrumb, button o input le gana sin pelear. Si alguien la
    // reescribe como `.muni-plantilla a:focus-visible`, pisa a los componentes.
    expect((bool) preg_match('/(^|[\s,}])\.muni-plantilla\s+[^{]*:focus-visible\s*\{[^}]*outline/m', $fuente))->toBeFalse(
        'Hay un anillo con `.muni-plantilla` fuera de :where(): pisaría el foco propio de los componentes.'
    );

    // Y el color de los enlaces del contenido, con especificidad cero: la reja midió
    // el azul del navegador a 2,05:1 en oscuro dentro del armazón de panel.
    expect((bool) preg_match('/:where\(\.muni-plantilla\)\s+:where\(a\[href\]\)\s*\{[^}]*color:\s*var\(--muni-accent\)/', $fuente))->toBeTrue(
        'Los enlaces del contenido tienen que tomar el color del token, con especificidad cero.'
    );

    expect(str_contains($fuente, ':not([tabindex="-1"])'))->toBeTrue(
        'Lo que se enfoca por programa (tabindex -1: el main del salto, el título de pantalla-resultado) no lleva anillo de base.'
    );
});

it('no escribe un solo color literal ni inventa tokens', function () {
    $fuente = plantillaFuenteLimpia();

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $fuente, $literales);
    expect(array_values(array_diff(array_unique($literales[0]), ['#767676'])))->toBe([],
        'Cero colores literales fuera del respaldo de foco: los tonos son tokens (DESIGN §10).'
    );

    preg_match_all('/rgba?\(|hsla?\(/i', $fuente, $funciones);
    expect($funciones[0])->toBe([], 'Un color por función es un color literal igual.');

    // Todo `--muni-*` usado tiene que existir en LAS DOS hojas: dentro de un panel
    // solo se carga `muni-ui-filament.css` (DESIGN §7).
    preg_match_all('/--muni-[a-z0-9-]+/i', $fuente, $usados);
    $hojas = cssMuniUi().cssMuniUiFilament();

    foreach (array_unique($usados[0]) as $token) {
        expect(substr_count(cssMuniUi(), $token.':') > 0 && substr_count(cssMuniUiFilament(), $token.':') > 0)->toBeTrue(
            "El token {$token} no está declarado en las dos hojas: dentro de un panel se quedaría sin valor."
        );
    }

    expect(strlen($hojas))->toBeGreaterThan(0);
});

it('el movimiento sale del token que se apaga solo', function () {
    $fuente = plantillaFuenteLimpia();

    preg_match_all('/transition:\s*([^;]+);/', $fuente, $transiciones);

    foreach ($transiciones[1] as $t) {
        expect(str_contains($t, 'var(--muni-dur)'))->toBeTrue(
            "La transición «{$t}» usa una duración fija: ignora prefers-reduced-motion (DESIGN §6)."
        );
    }

    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// El contrato del paquete
// ---------------------------------------------------------------------------

it('los textos del anfitrión no pueden inyectar marcado ni romper el Alpine de la página', function () {
    // «O'Higgins» es una calle real de Graneros: un apóstrofo dentro de una cadena
    // de comillas simples de Alpine tumba el Alpine de la página ENTERA (ya pasó en
    // `file-dropzone`). El aviso llega al anunciador, así que se comprueba que viaje
    // por atributo escapado y no dentro de una expresión.
    $malicioso = 'Oficina de Av. O\'Higgins "><script>alert(1)</script>';
    $html = Blade::render(
        '<x-muni::plantilla-pantalla :title="$t" :aviso="$t" :system="$t" :migas="[[\'label\' => $t]]">Contenido</x-muni::plantilla-pantalla>',
        ['t' => $malicioso]
    );

    expect(str_contains($html, '<script>alert(1)</script>'))->toBeFalse('Un texto del anfitrión se imprimió sin escapar.');
    expect(str_contains($html, 'data-muni-message="'.e($malicioso).'"'))->toBeTrue(
        'El aviso tiene que viajar al anunciador por un atributo escapado, no dentro de una expresión de Alpine.'
    );
    expect((bool) preg_match('/x-data="[^"]*O\'Higgins/', $html) || (bool) preg_match('/x-data="[^"]*O&#039;Higgins/', $html))->toBeFalse(
        'Un texto del anfitrión quedó dentro de un x-data.'
    );
});

it('no consulta el request, ni la sesión, ni los permisos', function () {
    $fuente = plantillaFuenteLimpia();

    foreach (['request(', 'Auth::', 'auth()', 'Gate::', 'can(', 'session(', 'uniqid('] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "La plantilla usa «{$prohibido}»: el paquete recibe todo ya resuelto por el anfitrión (Ley 21.719, minimización)."
        );
    }
});

it('no usa directivas exclusivas de un major de Livewire ni plugins de Alpine que no viajen', function () {
    $fuente = plantillaFuenteLimpia();

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]', 'x-trap', 'x-intersect', '@keydown.escape.window'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse("La plantilla usa «{$prohibido}», que rompe en Livewire 3 o depende de un plugin que no viaja.");
    }
});

it('no necesita Alpine para existir', function () {
    $fuente = plantillaFuenteLimpia();

    expect((bool) preg_match('/\bx-data\b/', $fuente))->toBeFalse(
        'La ficha dice «Alpine: ninguno»: la plantilla es composición y Blade puro.'
    );
});

it('el bloque de estilos viaja dentro del componente', function () {
    $fuente = plantillaFuente();

    expect(substr_count($fuente, '@once'))->toBe(1, 'El bloque de estilos va una sola vez y dentro de su propio @once (DESIGN §7).');
    expect(substr_count($fuente, '@endonce'))->toBe(1, 'Falta cerrar el bloque.');
    expect(str_contains($fuente, '<style>'))->toBeTrue('Lo que el componente necesita para verse bien viaja con el componente.');

    // Y entra por el <head> del armazón: detrás de </html> el navegador lo reubica
    // en el <body> y funciona de casualidad.
    $html = plantillaHtmlCompleta();
    expect(strpos($html, '.muni-plantilla__pie') < strpos($html, '</head>'))->toBeTrue(
        'El bloque de estilos quedó fuera del <head> del documento.'
    );
    expect(strpos($html, '<style>') > strpos($html, '<!DOCTYPE html>'))->toBeTrue('El estilo se emitió antes del documento.');
});

// ---------------------------------------------------------------------------
// Verificación en navegador
// ---------------------------------------------------------------------------

/*
 * El banco. Se genera acá porque este es el único sitio del paquete con Blade
 * arrancado (mismo criterio que `PantallaDeResultadoTest` y `MigasDeRutaTest`).
 * Sale a `build/plantilla-pantalla/`, ignorado por git.
 *
 * La plantilla emite su propio documento, así que el banco ES la salida del
 * componente: la hoja de tokens y Alpine entran por la ranura `head`, que es por
 * donde los entra un anfitrión real. Alpine va con `defer` y como `data:` para
 * que corra DESPUÉS de parsear el <body> (un script en línea en el <head>
 * arrancaría Alpine sin <body> que inicializar).
 *
 * Cuatro páginas, y cada una existe por algo que ninguna prueba de texto alcanza:
 *
 * · `claro` y `oscuro` — la pantalla de panel completa, con barra lateral,
 *   migas, acción de cabecera, aviso, contenido largo y pie propio con enlace:
 *   el primer Tab, el salto, el recorrido de ida y vuelta, el foco que no queda
 *   debajo de la cabecera, el anuncio del aviso y los landmarks.
 * · `publica` — el armazón público con el pie de fábrica.
 * · `sin-margen` — `claro` con la reserva de la cabecera anulada. Es la
 *   contraprueba del navegador: ahí el foco TIENE que quedar tapado, o la medida
 *   de las otras páginas no mide nada.
 * · `sin-anunciador` — `claro` con :announcer="false": el aviso tiene que salir
 *   como rol `status` del navegador, no quedarse mudo (defecto de la revisión).
 * · `sin-alpine` — `claro` sin Alpine: la ficha dice «Alpine: ninguno», así que
 *   el salto, el único <h1>, el aviso VISIBLE y el pie tienen que funcionar sin
 *   él. Lo que sí se pierde es el anuncio del anunciador, y se mide que es eso.
 */
function plantillaScriptDelBanco(string $ruta): ?string
{
    $absoluta = __DIR__.'/../node_modules/'.$ruta;

    return is_file($absoluta)
        ? '<script defer src="data:text/javascript;base64,'.base64_encode((string) file_get_contents($absoluta)).'"></script>'
        : null;
}

function plantillaPaginaDelBanco(string $tema, string $armazon, string $extraHead = '', bool $pieDeFabrica = false, bool $conAlpine = true, string $extraAtributos = ''): string
{
    // El plugin Focus antes del núcleo: se registra en `alpine:init`, y la barra
    // lateral lo usa para atrapar el foco en modo superpuesto.
    $head = '<style>'.cssMuniUi().'</style>'
        .'<style>body{font-family:var(--muni-font-sans)}.banco-alto{min-height:1600px;padding-top:24px}</style>'
        .$extraHead
        .($conAlpine
            ? plantillaScriptDelBanco('@alpinejs/focus/dist/cdn.min.js').plantillaScriptDelBanco('alpinejs/dist/cdn.min.js')
            : '');

    $pie = $pieDeFabrica ? '' : '<x-slot:pie><p>Datos al 6 de septiembre de 2026 · <a href="#ayuda" id="banco-ayuda">Ayuda</a></p></x-slot:pie>';

    return Blade::render(
        '<x-muni::plantilla-pantalla :theme="$tema" :armazon="$armazon" '
        .'title="Patentes morosas" subtitle="3.412 contribuyentes con deuda vigente al 6 de septiembre." '
        .'system="Rentas y Patentes" system-subtitle="Municipalidad de Graneros" '
        .':migas="[[\'label\' => \'Inicio\', \'url\' => \'#inicio\'], [\'label\' => \'Rentas\', \'url\' => \'#rentas\'], [\'label\' => \'Patentes morosas\']]" '
        .'aviso="No se pudo conectar con Tesorería: el listado puede estar desactualizado." aviso-tone="warn" '.$extraAtributos.'>'
        .'<x-slot:head>{!! $head !!}</x-slot:head>'
        .'<x-slot:sidebar><x-muni::sidebar><x-muni::nav-item href="#giros">Giros</x-muni::nav-item><x-muni::nav-item href="#patentes" :active="true">Patentes</x-muni::nav-item></x-muni::sidebar></x-slot:sidebar>'
        .'<x-slot:actions><x-muni::button id="banco-exportar">Exportar</x-muni::button></x-slot:actions>'
        .$pie
        .' <p><a href="#rol-4-118" id="banco-primero">Ver patente ROL 4-118</a></p>'
        .'<div class="banco-alto"><p>El listado de patentes ocupa más de una pantalla de alto.</p></div>'
        .'<p><a href="#rol-4-907" id="banco-ultimo">Ver patente ROL 4-907</a></p>'
        .'</x-muni::plantilla-pantalla>',
        ['tema' => $tema, 'armazon' => $armazon, 'head' => $head]
    );
}

it('genera el banco de navegador en build/plantilla-pantalla/', function () {
    $dir = __DIR__.'/../build/plantilla-pantalla';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    expect(plantillaScriptDelBanco('alpinejs/dist/cdn.min.js'))->not->toBeNull(
        'No está node_modules/alpinejs: corre `npm install`. Sin Alpine el anunciador no anuncia y la barra lateral no arranca.'
    );

    $paginas = [
        'claro' => plantillaPaginaDelBanco('light', 'dashboard'),
        'oscuro' => plantillaPaginaDelBanco('dark', 'dashboard'),
        'publica' => plantillaPaginaDelBanco('light', 'app', '', true),
        'sin-margen' => plantillaPaginaDelBanco('light', 'dashboard', '<style>.muni-plantilla *{scroll-margin-top:0 !important}</style>'),
        'sin-anunciador' => plantillaPaginaDelBanco('light', 'dashboard', '', false, true, ':announcer="false"'),
        'sin-alpine' => plantillaPaginaDelBanco('light', 'dashboard', '', false, false),
    ];

    foreach ($paginas as $nombre => $html) {
        file_put_contents($dir.'/'.$nombre.'.html', $html);

        expect(str_contains($html, 'class="muni-plantilla"'))->toBeTrue('El banco '.$nombre.' salió sin la plantilla.');
        expect(substr_count($html, 'src="data:text/javascript'))->toBe($nombre === 'sin-alpine' ? 0 : 2, 'El banco '.$nombre.' salió con Alpine donde no va, o sin Alpine donde va.');
        expect(strpos($html, '.muni-plantilla__pie') < strpos($html, '</head>'))->toBeTrue(
            'El banco '.$nombre.' salió sin el bloque de estilos en el <head>: nada de lo que se mida sería del componente.'
        );
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function plantillaPythonDeLaReja(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

it('en Chromium y Firefox: salto, recorrido, foco bajo la cabecera, anuncio y landmarks', function () {
    $python = plantillaPythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/plantilla-pantalla.py').' '
        .escapeshellarg(__DIR__.'/../build/plantilla-pantalla').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de la plantilla falló:\n".implode("\n", $lineas));

    // Tres páginas × dos navegadores tienen que reportar el recorrido completo, y
    // la contraprueba tiene que ver el foco TAPADO en los dos. Si el banco se
    // quedara sin páginas, el script diría «todo pasa» sobre nada.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'recorrido=ok'))))->toBe(6,
        "Las seis combinaciones tienen que recorrer la pantalla entera con el teclado:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'contraprueba=tapado'))))->toBe(2,
        "Sin la reserva de la cabecera el foco tiene que quedar tapado en los dos navegadores:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'sin-anunciador=status'))))->toBe(2,
        "Con el anunciador apagado el aviso tiene que exponerse como status en los dos navegadores:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'sin-alpine=ok'))))->toBe(2,
        "Sin Alpine el salto, el <h1>, el aviso visible y el pie tienen que seguir en los dos navegadores:\n".implode("\n", $lineas));
});
