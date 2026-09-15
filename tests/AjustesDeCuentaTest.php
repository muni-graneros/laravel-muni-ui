<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA PANTALLA DE MI CUENTA
|--------------------------------------------------------------------------
|
| `<x-muni::ajustes-cuenta>` es la ficha `ajustes-cuenta` de docs/GAP-ANALYSIS.md.
| La ficha no pide obra nueva: pide una REPARACIÓN. La pantalla ya existe como
| `demo/settings.html` y es la demo con más regresiones de accesibilidad del
| paquete —los interruptores son `<div class="sw" @click>` sin `<input>`, así que
| no se alcanzan con Tab ni se operan con Espacio, y las pestañas no tienen
| `role="tablist"`, ni `aria-selected`, ni flechas—, y es justo la que un
| desarrollador copia. Los componentes reales (`switch`, `tabs`) hacen las dos
| cosas bien: el componente existe para que la pantalla los USE en vez de
| reimplementarlos a mano.
|
| Lo que esta prueba vigila, y por qué cada cosa:
|
| · **Las pestañas son las de verdad.** `role="tablist"`, `role="tab"` con
|   `aria-selected` y `aria-controls`, y las flechas ←/→ que trae
|   `<x-muni::tabs>`. Si alguien vuelve a escribir `<div class="tab" @click>`,
|   esta prueba se pone roja.
| · **Ningún interruptor propio.** El componente no dibuja un `.sw`: los
|   conmutadores de la pantalla son `<x-muni::switch>`, que emite un
|   `<input type="checkbox">` real. La regresión que nombra la ficha no puede
|   volver a entrar por acá.
| · **Cada sesión activa se cierra con un `<button>` real dentro de un
|   `<form method="post">` con `@csrf`.** No un `<a href>` (GET no borra nada) ni
|   un `<div @click>`: sin JS el formulario sigue enviando, y el token de sesión
|   lo invalida el servidor. El riesgo que nombra la ficha —«cerrar sesión debe
|   invalidar el token en el servidor de verdad, no solo sacar la fila de la
|   lista»— se cubre no ofreciendo NINGÚN camino que solo toque el DOM.
| · **La sesión actual no se puede cerrar desde acá.** Cerrar la propia sesión
|   desde esta lista es el botón que un funcionario aprieta por error; para eso
|   está «salir», que es otra cosa y vive en la barra.
| · **La confirmación es un modal de verdad.** `role="alertdialog"`,
|   `aria-modal="true"`, foco atrapado con `x-trap` y Escape EN el elemento (lo
|   pone `<x-muni::modal>`). El botón que confirma vive en el diálogo y apunta al
|   formulario con `form=`, porque el modal se teletransporta a `<body>` y
|   quedaría fuera del `<form>`.
| · **La zona de peligro exige escribir la palabra.** Es el punto 6 de las teclas
|   obligatorias de la ficha. Se resuelve con `aria-disabled` y bloqueando el
|   envío, no con `disabled`: un botón `disabled` no recibe foco, no se anuncia y
|   —si Alpine no cargó— dejaría la acción muerta para siempre. La palabra viaja
|   por `@js()`, nunca interpolada en la expresión.
| · **Nada del anfitrión entra en una expresión de Alpine.** «Región de
|   O'Higgins» aparece en la ubicación de una sesión de cualquier municipio de la
|   VI Región: un apóstrofo dentro de una cadena de comillas simples tumba el
|   Alpine de la página ENTERA, no solo el de este componente. Ya pasó en
|   `file-dropzone`.
| · **Ley 21.719, minimización.** El componente recibe strings YA REDACTADOS por
|   el anfitrión (dispositivo, ubicación aproximada, última actividad). No
|   consulta `request()`, ni `Auth`, ni `Gate`, ni permisos, y no vuelca un
|   registro de sesión completo al DOM. La ficha marca el riesgo: este bloque
|   muestra IP y ubicación del propio funcionario.
|
| Todo va con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
| de `toContain()` es otra aguja, no un mensaje, y la aserción se apaga sin
| avisar.
*/

/** El HTML servido del componente. */
function ajustesHtml(string $atributos = '', string $contenido = ''): string
{
    return Blade::render("<x-muni::ajustes-cuenta {$atributos}>{$contenido}</x-muni::ajustes-cuenta>");
}

/** Las sesiones de ejemplo, tal como las entrega el anfitrión: strings ya redactados. */
function ajustesSesionesPhp(): string
{
    return '['
        ."['id' => 's-actual', 'device' => 'Chrome · Windows 11', 'detail' => 'Graneros, Región de O\\'Higgins · ahora mismo', 'current' => true],"
        ."['id' => 's-iphone', 'device' => 'Safari · iPhone', 'detail' => 'Rancagua, Región de O\\'Higgins · hace 2 horas', 'current' => false],"
        .']';
}

/** El ejemplo completo, que es el que se mide en la vitrina. */
function ajustesHtmlCompleto(): string
{
    return ajustesHtml(
        'id="cuenta" '
        .':sessions="'.ajustesSesionesPhp().'" '
        .'sessions-action="/mi-cuenta/sesiones/cerrar" '
        .'danger-action="/mi-cuenta/eliminar" '
        .'danger-word="ELIMINAR" '
        .'danger-description="La cuenta deja de existir y el acceso se revoca en todos los sistemas."',
        '<x-slot:perfil><x-muni::card title="Datos personales">'
        .'<x-muni::input label="Correo institucional" name="correo" /></x-muni::card></x-slot:perfil>'
        .'<x-slot:preferencias><x-muni::card title="Accesibilidad">'
        .'<x-muni::switch label="Reducir animaciones" name="mov" /></x-muni::card></x-slot:preferencias>'
    );
}

/**
 * El fuente crudo del componente. Falla si no existe: si devolviera cadena
 * vacía, las pruebas que buscan lo que el componente NO debe tener darían verde
 * sin componente.
 */
function ajustesFuente(): string
{
    $ruta = __DIR__.'/../resources/views/components/ajustes-cuenta.blade.php';

    expect(file_exists($ruta))->toBeTrue('No existe resources/views/components/ajustes-cuenta.blade.php.');

    return (string) file_get_contents($ruta);
}

/**
 * El fuente sin comentarios: la prosa explicativa nombra a propósito lo que el
 * componente NO hace («uniqid», «wire:show», «request()»), y buscarlo sobre el
 * fuente crudo daría falsos positivos.
 */
function ajustesFuenteSinComentarios(): string
{
    $sin = (string) preg_replace('/\{\{--.*?--\}\}/s', '', ajustesFuente());
    $sin = (string) preg_replace('#/\*.*?\*/#s', '', $sin);

    return (string) preg_replace('#(?<!:)//[^\n]*#', '', $sin);
}

/**
 * TODOS los bloques de estilos del componente, concatenados. En plural desde que el
 * componente trae un segundo bloque dentro de `<noscript>`: con `preg_match` a secas
 * se medía solo el primero y cualquier regla del segundo quedaba fuera de todos los
 * candados (colores literales, foco, movimiento, opacidad).
 */
function ajustesCss(): string
{
    preg_match_all('#<style>(.*?)</style>#s', ajustesFuente(), $m);

    return implode("\n", $m[1] ?? []);
}

/**
 * La expresión del `x-data` de la zona de peligro, decodificada. Es DONDE vive la
 * comparación de la palabra: buscar `===` sobre el fuente entero era una aserción
 * vacua —hay seis apariciones ajenas en PHP (`$muniAjcId === ''`, la clave de cada
 * sección…)— y degradar la expresión de Alpine a `==` la dejaba en verde.
 */
function ajustesXDataPeligro(string $html): string
{
    preg_match_all('/\sx-data="([^"]*)"/i', $html, $m);

    foreach ($m[1] as $expresion) {
        $expresion = html_entity_decode($expresion, ENT_QUOTES | ENT_HTML5);

        if (str_contains($expresion, 'coincide')) {
            return $expresion;
        }
    }

    return '';
}

it('usa las pestañas de verdad y no una copia hecha a mano', function () {
    $html = ajustesHtmlCompleto();

    expect(str_contains($html, 'role="tablist"'))->toBeTrue(
        'La pantalla no emite un role="tablist". La ficha dice que las pestañas de demo/settings.html '
        .'no lo tienen y que por eso hay que usar <x-muni::tabs>.'
    );

    expect(substr_count($html, 'role="tab"'))->toBeGreaterThanOrEqual(3,
        'Faltan pestañas: con perfil, preferencias y zona de peligro tienen que salir al menos tres.'
    );

    expect(str_contains($html, 'aria-selected='))->toBeTrue('Ninguna pestaña declara aria-selected.');
    expect(str_contains($html, 'aria-controls='))->toBeTrue('Ninguna pestaña apunta a su panel con aria-controls.');
    expect(str_contains($html, 'role="tabpanel"'))->toBeTrue('No hay ningún panel con role="tabpanel".');

    // El teclado lo pone <x-muni::tabs>: si alguien cambia el componente por
    // <div>s propios, estas directivas desaparecen del HTML servido.
    expect(str_contains($html, '@keydown.right.prevent') || str_contains($html, 'x-on:keydown.right.prevent'))->toBeTrue(
        'Las flechas ←/→ no están: el grupo de pestañas no es el del paquete.'
    );
});

it('no dibuja ningún interruptor propio: eso es <x-muni::switch>', function () {
    $fuente = ajustesFuenteSinComentarios();

    foreach (['class="sw"', "class='sw'", '.sw{', '.sw ', 'muni-ajc__sw'] as $rastro) {
        expect(str_contains($fuente, $rastro))->toBeFalse(
            "El componente dibuja su propio interruptor («{$rastro}»). La regresión que nombra la ficha es "
            .'justo esa: un <div> con @click no se alcanza con Tab ni se opera con Espacio.'
        );
    }
});

it('cada sesión que no es la actual se cierra con un POST real y con token', function () {
    $html = ajustesHtml(
        'id="cuenta" :sessions="'.ajustesSesionesPhp().'" '
        .'sessions-action="/mi-cuenta/sesiones/cerrar"'
    );

    expect(str_contains($html, '<form method="post" action="/mi-cuenta/sesiones/cerrar"'))->toBeTrue(
        'El cierre de una sesión no es un <form method="post">. Un <a href> o un @click no invalidan '
        .'nada en el servidor: la ficha marca ese riesgo con esas palabras.'
    );

    expect(str_contains($html, 'name="_token"'))->toBeTrue(
        'El formulario no lleva @csrf. Sin token, el POST que cierra sesiones es un CSRF servido por el paquete.'
    );

    expect(str_contains($html, 'name="sesion" value="s-iphone"'))->toBeTrue(
        'El formulario no dice QUÉ sesión cierra.'
    );

    expect((bool) preg_match('/<button[^>]*type="submit"/', $html))->toBeTrue(
        'No hay un <button type="submit"> real para cerrar la sesión.'
    );

    // Una sola sesión cerrable: la actual no ofrece el botón.
    expect(substr_count($html, '<form method="post"'))->toBe(1,
        'La sesión marcada como actual también ofrece cerrarse. Cerrar la propia sesión desde esta lista '
        .'es el botón que se aprieta por error; para salir está la barra.'
    );

    expect(str_contains($html, 'value="s-actual"'))->toBeFalse(
        'La sesión actual viaja en un formulario: no debería poder cerrarse desde acá.'
    );
});

it('la confirmación abre en un diálogo de alerta con foco atrapado y sin salida por descarte', function () {
    $html = ajustesHtml(
        'id="cuenta" :sessions="'.ajustesSesionesPhp().'" '
        .'sessions-action="/mi-cuenta/sesiones/cerrar"'
    );

    expect(str_contains($html, 'role="alertdialog"'))->toBeTrue(
        'La confirmación no es un alertdialog: un lector de pantalla no anuncia la pregunta.'
    );
    expect(str_contains($html, 'aria-modal="true"'))->toBeTrue('El diálogo no declara aria-modal.');
    expect(str_contains($html, 'x-trap.inert.noscroll'))->toBeTrue(
        'El diálogo no atrapa el foco. La ficha lo pide con esas palabras.'
    );
    expect(str_contains($html, 'keydown.escape.stop'))->toBeTrue('El diálogo no cierra con Escape.');

    // El modal se teletransporta a <body>: el botón que confirma queda FUERA del
    // <form>, así que tiene que apuntarlo con form=. Sin eso, confirmar no envía nada.
    preg_match('/<form method="post"[^>]*id="([^"]+)"/', $html, $m);
    expect($m[1] ?? '')->not->toBe('', 'El formulario de la sesión no tiene id: el botón del diálogo no lo puede apuntar.');

    expect(str_contains($html, 'form="'.$m[1].'"'))->toBeTrue(
        'El botón que confirma no apunta al formulario con form=. El modal vive teletransportado en <body>, '
        .'fuera del <form>, y sin ese atributo el clic no envía nada.'
    );
});

it('la zona de peligro no deja destruir sin escribir la palabra, y funciona sin JS', function () {
    $html = ajustesHtml('id="cuenta" danger-action="/mi-cuenta/eliminar" danger-word="ELIMINAR"');

    expect((bool) preg_match('#<form\s+method="post"\s+action="/mi-cuenta/eliminar"#', $html))->toBeTrue(
        'La zona de peligro no envía por POST.'
    );
    expect(str_contains($html, 'name="_token"'))->toBeTrue('La zona de peligro no lleva @csrf.');

    expect(str_contains($html, ':aria-disabled') || str_contains($html, 'x-bind:aria-disabled'))->toBeTrue(
        'El botón destructivo no se marca como no disponible mientras falta la palabra.'
    );

    expect((bool) preg_match('/<button[^>]*\sdisabled/', $html))->toBeFalse(
        'El botón destructivo nace `disabled`. Un botón disabled no recibe foco ni se anuncia, y si Alpine '
        .'no cargó deja la acción muerta: la puerta de verdad es la validación del servidor.'
    );

    // Comparación exacta: «eliminar todo» no puede pasar por «ELIMINAR». Se mira la
    // expresión de Alpine REAL, no el fuente entero: sobre el fuente el `===` aparece
    // seis veces en PHP y la aserción daba verde aunque la comparación fuera `==`.
    $xdata = ajustesXDataPeligro($html);

    expect($xdata)->not->toBe('', 'No se encontró el x-data de la zona de peligro: la aserción sería vacua.');

    expect((bool) preg_match('/return\s+this\.escrito\.trim\(\)\s*===\s*this\.palabra/', $xdata))->toBeTrue(
        'La palabra no se compara con === dentro del x-data de la zona de peligro. Una comparación '
        .'laxa deja pasar cualquier cosa parecida, y el `===` del resto del fuente es PHP: no vigila esto. '
        .'Expresión encontrada: '.$xdata
    );

    expect((bool) preg_match('/[^=!<>]==[^=]/', (string) preg_replace('/===/', '', $xdata)))->toBeFalse(
        'Hay una comparación laxa (==) dentro del x-data de la zona de peligro: '.$xdata
    );

    expect(str_contains($html, 'ELIMINAR'))->toBeTrue('La palabra exigida no se le dice a nadie.');
});

it('ni la ubicación de una sesión ni la palabra destructiva entran crudas en una expresión de Alpine', function () {
    $html = ajustesHtmlCompleto();

    preg_match_all('/\s(?:x-data|x-effect|x-init|x-on:[a-z.:-]+|@[a-z][a-z.:-]*|:[a-z-]+)="([^"]*)"/i', $html, $m);

    foreach ($m[1] as $expresion) {
        /*
         * DECODIFICADO, y no crudo: Blade escapa el apóstrofo a `&#039;` en el HTML,
         * pero el navegador lo decodifica ANTES de que Alpine evalúe el atributo. Una
         * prueba que buscara el apóstrofo tal cual daría verde con el defecto puesto,
         * que es exactamente lo que pasó al escribirla.
         */
        $expresion = html_entity_decode($expresion, ENT_QUOTES | ENT_HTML5);

        expect(str_contains($expresion, "O'Higgins"))->toBeFalse(
            'La ubicación de una sesión entra cruda en una expresión de Alpine: «'.$expresion.'». Un apóstrofo '
            .'descuadra la cadena y tumba el Alpine de la página entera (ya pasó en file-dropzone).'
        );
    }

    $conApostrofo = ajustesHtml('id="c2" danger-action="/x" danger-word="BORRA O\'HIGGINS"');

    preg_match_all('/\sx-data="([^"]*)"/i', $conApostrofo, $md);

    $encontrada = false;

    foreach ($md[1] as $expresion) {
        $expresion = html_entity_decode($expresion, ENT_QUOTES | ENT_HTML5);

        expect(str_contains($expresion, "O'HIGGINS"))->toBeFalse(
            'La palabra destructiva entra cruda en el x-data: tiene que viajar por @js(), que la escapa.'
        );

        if (str_contains($expresion, 'u0027')) {
            $encontrada = true;
        }
    }

    expect($encontrada)->toBeTrue(
        'La palabra no aparece escapada en ninguna expresión: revisa que viaje por @js().'
    );
});

it('no consulta al anfitrión: ni request(), ni Auth, ni Gate, ni permisos', function () {
    $fuente = ajustesFuenteSinComentarios();

    foreach (['request(', 'Auth::', 'auth()', 'Gate::', 'can(', '->user()'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El componente usa «{$prohibido}». El paquete recibe todo ya resuelto por el anfitrión: "
            .'quién es el funcionario, qué sesiones tiene y si puede borrarse la cuenta se deciden allá '
            .'(Ley 21.719: minimización y base de licitud explícita).'
        );
    }
});

it('no usa nada que solo exista en un major de Livewire ni atajos globales de teclado', function () {
    $fuente = ajustesFuenteSinComentarios();

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($fuente, $prohibida))->toBeFalse(
            "Usa «{$prohibida}», que no existe en los dos majors de Livewire que el paquete soporta."
        );
    }

    expect(str_contains($fuente, '@keydown.escape.window'))->toBeFalse(
        'Escape se escucha en el elemento, no en la ventana: en la ventana se roba el Escape de '
        .'cualquier diálogo que el anfitrión tenga abierto.'
    );

    expect(str_contains($fuente, 'uniqid('))->toBeFalse(
        'Los ids salen de las props, nunca de uniqid(): con uniqid cambian en cada render, rompen '
        .'aria-controls y ensucian el diffing de Livewire (DESIGN §10).'
    );
});

it('no escribe un solo color literal', function () {
    $css = ajustesCss().' '.(string) preg_replace('#<style>.*?</style>#s', '', ajustesFuenteSinComentarios());

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $m);

    $literales = array_values(array_filter($m[0], fn (string $c) => strtolower($c) !== '#767676'));

    expect($literales)->toBe([],
        'Colores literales en el componente: '.implode(' ', $literales).'. La identidad de cada '
        .'municipio se cambia redefiniendo tokens; un literal no lo puede cambiar nadie.'
    );
});

it('usa solo tokens declarados en las DOS hojas', function () {
    preg_match_all('/var\(\s*(--muni-[a-z0-9-]+)/i', ajustesFuenteSinComentarios(), $m);

    $faltan = [];

    foreach (array_unique($m[1]) as $token) {
        if (! str_contains(cssMuniUi(), $token.':') || ! str_contains(cssMuniUiFilament(), $token.':')) {
            $faltan[] = $token;
        }
    }

    expect($faltan)->toBe([],
        'Estos tokens no están declarados en las dos hojas: '.implode(' ', $faltan).'. Dentro de un '
        .'panel Filament solo se carga muni-ui-filament.css, y un var() que no existe descarta la '
        .'declaración entera sin un solo error en consola (DESIGN §7).'
    );
});

it('el foco se ve con outline y el blanco táctil de los controles llega a 44px', function () {
    $css = ajustesCss();

    preg_match_all('/([^{}]*:focus[^{}]*)\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    expect($reglas)->not->toBe([], 'El componente no declara ninguna regla de foco.');

    foreach ($reglas as [, $selector, $cuerpo]) {
        expect((bool) preg_match('/outline\s*:\s*3px solid var\(--muni-focus, var\(--muni-accent, #767676\)\)/', $cuerpo))->toBeTrue(
            'La regla de foco «'.trim($selector).'» no usa el outline canónico del paquete. Dentro '
            .'de Filament la box-shadow del anillo se computa transparente (DESIGN §5).'
        );

        expect(str_contains($cuerpo, 'box-shadow') && ! str_contains($cuerpo, 'outline'))->toBeFalse(
            'La regla de foco «'.trim($selector).'» se apoya solo en una sombra.'
        );
    }

    expect(substr_count($css, 'min-height:44px'))->toBeGreaterThanOrEqual(1,
        'Los controles de esta pantalla no llegan a 44px de alto. Cerrar una sesión y borrar una cuenta '
        .'son acciones destructivas: el blanco chico es el que se aprieta por error.'
    );
});

it('el estado «en espera» del botón destructivo no se dibuja bajando la opacidad', function () {
    // Medido con la reja sobre el banco: `opacity:.55` sobre el botón danger dejaba
    // «Eliminar mi cuenta» en 2,46:1 en claro y 2,31:1 en oscuro (WCAG 1.4.3 pide
    // 4,5:1). El estado va en el borde, y además en texto y en aria-disabled.
    // Sin comentarios: la explicación de acá arriba NOMBRA `opacity` a propósito.
    $css = (string) preg_replace('#/\*.*?\*/#s', '', ajustesCss());

    expect((bool) preg_match('/opacity\s*:/i', $css))->toBeFalse(
        'El componente baja la opacidad de algo. Un texto a opacidad parcial pierde contraste contra '
        .'su fondo y la reja lo marca: el estado se dibuja con forma, no apagando el color.'
    );
});

it('el movimiento sale del token y respeta prefers-reduced-motion', function () {
    $css = ajustesCss();

    preg_match_all('/transition\s*:\s*([^;}]+)/i', $css, $m);

    foreach ($m[1] as $transicion) {
        // `none` apaga el movimiento; `none !important` es lo que hace falta dentro
        // del panel, donde `button` escribe su transición en el atributo style.
        if (in_array(trim(str_replace('!important', '', $transicion)), ['none'], true)) {
            continue;
        }

        expect(str_contains($transicion, 'var(--muni-dur)'))->toBeTrue(
            "La transición «{$transicion}» usa una duración fija: ignora prefers-reduced-motion, que el "
            .'token ya baja a 0 ms (DESIGN §6).'
        );
    }
});

it('una sección sin contenido no monta su pestaña', function () {
    // Solo la ranura por defecto y la zona de peligro: seguridad y preferencias no
    // existen, así que no pueden aparecer como pestañas vacías.
    $html = ajustesHtml('id="dos" danger-action="/baja" danger-word="ELIMINAR"', 'Solo los datos personales.');

    expect(str_contains($html, 'Solo los datos personales.'))->toBeTrue('El contenido no se rinde.');

    expect(substr_count($html, 'role="tab"'))->toBe(2,
        'Salen pestañas que nadie llenó. Una pestaña vacía es una parada de teclado que no lleva a nada.'
    );

    expect(str_contains($html, 'Preferencias'))->toBeFalse('Monta la pestaña de preferencias sin contenido.');
    expect(str_contains($html, 'Sesiones activas'))->toBeFalse('Monta el bloque de sesiones sin sesiones.');
});

it('el HTML es determinista entre renders', function () {
    expect(ajustesHtmlCompleto())->toBe(ajustesHtmlCompleto(),
        'Dos renders del mismo componente no dan el mismo HTML: hay un id que cambia y eso rompe '
        .'aria-controls y el diffing de Livewire.'
    );
});

it('el panel de cada pestaña lleva el nombre de su sección y un solo encabezado por tarjeta', function () {
    $html = ajustesHtmlCompleto();

    expect(str_contains($html, 'aria-labelledby='))->toBeTrue(
        'Los paneles no se enlazan con su pestaña: sin aria-labelledby el lector no dice en qué sección está.'
    );

    expect(str_contains($html, '<h1'))->toBeFalse(
        'La pantalla emite un <h1>. El único <h1> de la página lo pone <x-muni::page-header>: dos compiten.'
    );
});

/*
|--------------------------------------------------------------------------
| Lo que las aserciones de cadena no alcanzaban a ver
|--------------------------------------------------------------------------
|
| Las pruebas de arriba miran presencia y ausencia de texto. Eso ya dejó pasar
| tres defectos reales, y estas son las que los cierran: el mensaje de vacío que
| mentía, la normalización de `current`, el verbo suplantado, y —el peor— una
| regla de clase que el navegador nunca aplicaba porque `button` escribe la
| propiedad en el atributo `style`.
*/

it('el mensaje de «no hay otras sesiones» solo sale cuando de verdad no hay otras', function () {
    /*
     * El defecto: la condición contaba SESIONES, no sesiones ajenas. Con una sola
     * sesión ajena salía el formulario para cerrarla y, debajo, «No hay otras
     * sesiones abiertas.». Al funcionario se le decía que no tenía otras sesiones
     * mientras la pantalla le estaba listando una, y este bloque es justo el que la
     * ficha marca como el de riesgo.
     */
    $unaAjena = ajustesHtml(
        'id="c" sessions-action="/cerrar" :sessions="['
        ."['id' => 's-1', 'device' => 'Safari · iPhone', 'detail' => 'Rancagua · hace 2 horas', 'current' => false],"
        .']"'
    );

    expect(str_contains($unaAjena, 'Safari · iPhone'))->toBeTrue('La sesión ajena no se lista.');
    expect(str_contains($unaAjena, 'No hay otras sesiones abiertas.'))->toBeFalse(
        'Con UNA sesión ajena listada, la pantalla dice igual que no hay otras sesiones abiertas. '
        .'La condición cuenta sesiones, no sesiones ajenas.'
    );

    $soloLaActual = ajustesHtml(
        'id="c" sessions-action="/cerrar" :sessions="['
        ."['id' => 's-actual', 'device' => 'Chrome · Windows 11', 'detail' => 'Graneros · ahora', 'current' => true],"
        .']"'
    );

    expect(str_contains($soloLaActual, 'No hay otras sesiones abiertas.'))->toBeTrue(
        'Con solo la sesión actual no se dice que no hay otras: el funcionario se queda sin saber si '
        .'su cuenta está abierta en otra parte.'
    );
    expect(str_contains($soloLaActual, '<form method="post"'))->toBeFalse(
        'La sesión actual ofrece cerrarse.'
    );

    $dosAjenas = ajustesHtml(
        'id="c" sessions-action="/cerrar" :sessions="['
        ."['id' => 's-1', 'device' => 'Safari · iPhone', 'current' => false],"
        ."['id' => 's-2', 'device' => 'Firefox · Ubuntu', 'current' => false],"
        .']"'
    );

    expect(str_contains($dosAjenas, 'No hay otras sesiones abiertas.'))->toBeFalse(
        'Con dos sesiones ajenas sigue saliendo el mensaje de vacío.'
    );
    expect(substr_count($dosAjenas, '<form method="post"'))->toBe(2, 'No sale un formulario por sesión ajena.');
});

it('reconoce la sesión actual aunque `current` venga como texto', function () {
    /*
     * En el Blade del anfitrión, `'current' => 'false'` es una cadena, y en PHP toda
     * cadena no vacía es verdadera: sin filter_var la sesión propia habría quedado
     * marcada como ajena —ofreciendo cerrarse, que es el clic que se da por error— y
     * la ajena marcada como propia.
     */
    $html = ajustesHtml(
        'id="c" sessions-action="/cerrar" :sessions="['
        ."['id' => 's-actual', 'device' => 'Chrome · Windows 11', 'current' => 'true'],"
        ."['id' => 's-otra', 'device' => 'Safari · iPhone', 'current' => 'false'],"
        .']"'
    );

    expect(str_contains($html, 'value="s-actual"'))->toBeFalse(
        'Con `current => \'true\'` (cadena) la sesión propia ofrece cerrarse: falta normalizar con filter_var.'
    );
    expect(str_contains($html, 'value="s-otra"'))->toBeTrue(
        'Con `current => \'false\'` (cadena) la sesión ajena quedó marcada como la actual y no se puede cerrar: '
        .'en PHP la cadena "false" es verdadera.'
    );
    expect(substr_count($html, 'Este equipo'))->toBe(1, 'Hay más de una sesión marcada como «este equipo».');

    // Sin la clave, la sesión es ajena: es lo seguro. Marcarla como propia la dejaría
    // sin forma de cerrarse desde ninguna parte.
    $sinClave = ajustesHtml(
        'id="c" sessions-action="/cerrar" :sessions="[[\'id\' => \'s-x\', \'device\' => \'Equipo sin marca\']]"'
    );

    expect(str_contains($sinClave, 'value="s-x"'))->toBeTrue('Una sesión sin `current` tiene que poder cerrarse.');
});

it('el verbo de la zona de peligro se suplanta con @method y nunca cae en GET', function () {
    foreach (['delete', 'put', 'patch'] as $verbo) {
        $html = ajustesHtml('id="c" danger-action="/baja" danger-word="ELIMINAR" danger-method="'.$verbo.'"');

        expect((bool) preg_match('/name="_method"\s+value="'.strtoupper($verbo).'"/i', $html))->toBeTrue(
            "Con danger-method=\"{$verbo}\" no se emite el campo _method: Laravel recibiría un POST y la "
            .'ruta declarada con Route::'.$verbo.'() no la atendería nadie.'
        );
        expect((bool) preg_match('/<form\s+method="post"\s+action="\/baja"/', $html))->toBeTrue(
            'El formulario tiene que seguir siendo method="post" en el HTML: los verbos suplantados viajan en el campo.'
        );
    }

    // GET jamás: un prefetch del navegador o un rastreador dispararían la baja.
    foreach (['get', 'GET', 'inventado', ''] as $malo) {
        $html = ajustesHtml('id="c" danger-action="/baja" danger-word="ELIMINAR" danger-method="'.$malo.'"');

        expect((bool) preg_match('/<form\s+method="post"\s+action="\/baja"/', $html))->toBeTrue(
            "Con danger-method=\"{$malo}\" el formulario no cae en POST."
        );
        expect(str_contains($html, 'name="_method"'))->toBeFalse(
            "Con danger-method=\"{$malo}\" se suplanta un verbo que nadie pidió."
        );
    }
});

it('el estado «en espera» del botón le gana al atributo style que escribe <x-muni::button>', function () {
    /*
     * EL DEFECTO QUE ESTA PRUEBA CIERRA, y que la de la opacidad no veía: el estado
     * en espera se dibujaba con `border-style:dashed` y `cursor:not-allowed` en
     * reglas de CLASE, y `<x-muni::button>` escribe `border:1px solid transparent;`
     * y `cursor:pointer;` en el atributo `style`, que gana a cualquier regla de
     * autor sin `!important`. Medido en Chromium sobre el banco: el botón computaba
     * `border-top-style:solid` y `cursor:pointer` en espera, o sea se veía idéntico
     * al habilitado. La prueba de la opacidad pasaba igual porque solo busca la
     * cadena `opacity:` en el fuente.
     *
     * La lista de propiedades NO está escrita a mano: se lee del `$base` del propio
     * `button.blade.php`. Si mañana ese componente escribe otra propiedad en línea,
     * este candado la empieza a vigilar solo.
     */
    $fuenteBoton = (string) file_get_contents(__DIR__.'/../resources/views/components/button.blade.php');

    preg_match('/\$base\s*=\s*(.*?);\s*\n\s*@endphp/s', $fuenteBoton, $mb);

    expect($mb[1] ?? '')->not->toBe('', 'No se pudo leer el $base de button.blade.php: la comprobación sería vacía.');

    preg_match_all('/([a-z-]+)\s*:/', (string) preg_replace('/var\([^)]*\)/', '', $mb[1]), $mp);

    $enLinea = array_values(array_unique($mp[1]));

    expect(in_array('border', $enLinea, true) && in_array('cursor', $enLinea, true))->toBeTrue(
        'El $base de button ya no escribe `border` ni `cursor`: revisa esta prueba antes de aflojarla.'
    );

    $css = (string) preg_replace('#/\*.*?\*/#s', '', ajustesCss());

    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    $flojas = [];

    foreach ($reglas as [, $selector, $cuerpo]) {
        if (! str_contains($selector, 'muni-ajc__accion') && ! str_contains($selector, 'muni-ajc__destruir')) {
            continue;
        }

        foreach (explode(';', $cuerpo) as $declaracion) {
            if (! str_contains($declaracion, ':')) {
                continue;
            }

            [$propiedad] = explode(':', $declaracion, 2);
            $propiedad = trim($propiedad);

            // `border-style` y `border-color` son longhands del `border` en línea:
            // el shorthand las fija a las dos, así que también hay que ganarle.
            $pisada = false;

            foreach ($enLinea as $suya) {
                if ($propiedad === $suya || str_starts_with($propiedad, $suya.'-')) {
                    $pisada = true;
                    break;
                }
            }

            if ($pisada && ! str_contains($declaracion, '!important')) {
                $flojas[] = trim($selector).' { '.trim($declaracion).' }';
            }
        }
    }

    expect($flojas)->toBe([],
        'Estas reglas no se dibujan nunca: <x-muni::button> escribe esa misma propiedad en el atributo '
        ."`style`, que gana sin `!important`.\n  ".implode("\n  ", $flojas)
    );
});

it('sin JavaScript los paneles se revelan: el POST y la zona de peligro siguen alcanzables', function () {
    /*
     * «Sin JS el formulario envía igual» era falso y medible: todo el contenido
     * cuelga de un `<x-muni::tab-panel>`, que lleva `x-cloak`, y LAS DOS hojas
     * declaran `[x-cloak] { display:none !important }` (muni-ui.css:24,
     * muni-ui-filament.css:303). Sin Alpine no se veía ni se alcanzaba ningún panel:
     * ni el POST que cierra la sesión ni la zona de peligro.
     */
    $fuente = ajustesFuente();

    expect(str_contains($fuente, '<noscript>'))->toBeTrue(
        'No hay bloque <noscript>: con el JS apagado, el x-cloak de tab-panel esconde los cuatro paneles '
        .'y la promesa de «sin JS el formulario envía igual» es falsa.'
    );

    preg_match('#<noscript>(.*?)</noscript>#s', $fuente, $m);

    $sinJs = $m[1] ?? '';

    expect((bool) preg_match('/\[role="tabpanel"\]\[x-cloak\][^{]*\{[^}]*display\s*:\s*block\s*!important/', $sinJs))->toBeTrue(
        'El <noscript> no revela los paneles. Hace falta ganarle a `[x-cloak] { display:none !important }` '
        .'de las dos hojas: la especificidad de `.muni-ajc [role="tabpanel"][x-cloak]` (0,3,1) le gana a '
        .'`[x-cloak]` (0,1,0), pero el !important es obligatorio.'
    );

    expect(str_contains($sinJs, '.muni-ajc '))->toBeTrue(
        'La regla del <noscript> no está acotada a .muni-ajc: revelaría el x-cloak de cualquier otro '
        .'componente de la página.'
    );

    // Y el marcado que tiene que quedar alcanzable existe de verdad en el HTML servido.
    $html = ajustesHtmlCompleto();

    expect(preg_match_all('/<form\s+method="post"/', $html))->toBeGreaterThanOrEqual(2,
        'Sin los dos formularios reales (cerrar sesión y zona de peligro) no hay nada que revelar.'
    );
});

it('la pista de la zona de peligro no promete una forma de palabra que el anfitrión no eligió', function () {
    // El defecto: la pista decía «Tal cual, en mayúsculas», pero la palabra la pone
    // el anfitrión. Con danger-word="Eliminar" la pista mentía y no había prop para
    // cambiarla, mientras todo el resto de los rótulos sí es prop.
    $html = ajustesHtml('id="c" danger-action="/baja" danger-word="Eliminar"');

    expect(str_contains($html, 'en mayúsculas'))->toBeFalse(
        'La pista por defecto promete mayúsculas con una palabra que no las tiene: es una instrucción '
        .'que, seguida al pie de la letra, no habilita el botón.'
    );

    $propia = ajustesHtml('id="c" danger-action="/baja" danger-word="Eliminar" '
        .'danger-hint="Escríbela con la E mayúscula, igual que arriba."');

    expect(str_contains($propia, 'Escríbela con la E mayúscula, igual que arriba.'))->toBeTrue(
        'La prop danger-hint no reemplaza la pista.'
    );
});

/*
|--------------------------------------------------------------------------
| El banco de navegador
|--------------------------------------------------------------------------
|
| Se genera acá y no en un script suelto porque este es el único sitio del
| paquete donde Blade está arrancado (mismo patrón que `PantallaDeBloqueoTest` y
| `FormularioDeTramiteTest`). Sale a `build/ajustes-cuenta/`, que git ignora.
|
| POR QUÉ EXISTE, con nombre y apellido: este componente es TODO interacción
| —pestañas, diálogo de confirmación, puerta de la zona de peligro— y las pruebas
| de arriba leen TEXTO. Eso ya dejó pasar el defecto más caro de la ficha: el
| estado «en espera» del botón destructivo no se dibujaba, porque
| `border-style:dashed` y `cursor:not-allowed` son reglas de clase y
| `<x-muni::button>` escribe `border:…` y `cursor:pointer` en el atributo `style`.
| La prueba que lo vigilaba pasaba igual, porque solo buscaba la cadena
| `opacity:` en el fuente. Es exactamente la trampa que ya documenta
| `tests/navegador/pantalla-bloqueo.py`: «la regla pasó el test de texto y en el
| navegador el defecto seguía vivo».
|
| El banco trae un oyente de `submit` en `window` que anota si el envío llegó ya
| con `defaultPrevented` —o sea, si el componente lo bloqueó— y luego lo detiene
| para que la página no navegue. El oyente está en la fase de burbuja y en
| `window`, así que corre DESPUÉS del `x-on:submit` del componente: lo que mide es
| la decisión del componente, no la suya.
*/
it('genera el banco de navegador en build/ajustes-cuenta/', function () {
    $dir = __DIR__.'/../build/ajustes-cuenta';

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
        'Falta node_modules/alpinejs o @alpinejs/focus: corre `npm install`. Sin el plugin Focus no hay '
        .'x-trap y el banco mediría un diálogo que no atrapa nada.'
    );

    $pantalla = ajustesHtmlCompleto();

    /* UNA SOLA SESIÓN AJENA: es la página del defecto del mensaje de vacío. Con la
       condición contando el total, acá salía el formulario para cerrarla Y, debajo,
       «No hay otras sesiones abiertas.». */
    $unaSesion = ajustesHtml(
        'id="una" sessions-action="/mi-cuenta/sesiones/cerrar" :sessions="['
        ."['id' => 's-iphone', 'device' => 'Safari · iPhone', 'detail' => 'Rancagua, Región de O\\'Higgins · hace 2 horas', 'current' => false],"
        .']"'
    );

    $paginas = [
        'claro' => ['light', cssMuniUi(), false, $pantalla],
        'oscuro' => ['dark', cssMuniUi(), false, $pantalla],
        'panel-claro' => ['light', cssMuniUiFilament(), true, $pantalla],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true, $pantalla],
        'una-sesion' => ['light', cssMuniUi(), false, $unaSesion],
    ];

    /* El oyente del banco: anota el envío CON la decisión del componente y lo
       detiene. Sin esto, cada clic en «Eliminar mi cuenta» navegaría a un POST
       contra un file:// y la medición terminaría en la primera comprobación. */
    $sonda = '<script data-banco="sonda">window.__envios=[];'
        ."window.addEventListener('submit',function(e){window.__envios.push({"
        ."accion:e.target.getAttribute('action'),metodo:e.target.getAttribute('method'),"
        .'bloqueado:e.defaultPrevented});e.preventDefault();},false);</script>';

    foreach ($paginas as $nombre => [$tema, $css, $panel, $cuerpoPantalla]) {
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        $armazon = 'body{margin:0;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'.relleno{padding:24px;max-width:70ch}';

        $script = $sonda.'<script data-banco="alpine-focus">'.$focus.'</script>'
            .'<script data-banco="alpine-core">'.$alpine.'</script>';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<section class="fi-section"><div class="relleno">'.$cuerpoPantalla.'</div></section></main></div>'.$script.'</body>'
            : '<body><main id="muni-contenido" tabindex="-1"><div class="relleno">'.$cuerpoPantalla.'</div></main>'.$script.'</body>';

        $documento = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($tema === 'dark' ? ' class="dark"' : '')
            .' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco ajustes-cuenta — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $documento);

        expect(str_contains($documento, 'muni-ajc'))->toBeTrue('El banco '.$nombre.' salió sin la pantalla.');
    }

    expect(str_contains((string) file_get_contents($dir.'/claro.html'), '.muni-ajc__destruir--espera'))->toBeTrue(
        'El banco salió sin el bloque de estilos del componente: nada de lo que se mida sería suyo.'
    );

    /* LA TARJETA DE LA VITRINA, en su contexto real y en los dos temas.
       `tests/GeneraVitrinaTest.php` es un archivo compartido que este componente no
       toca: la entrada se entrega como dato. Para no entregarla a ciegas —la reja de
       la vitrina mide sobre DOS superficies, y el reparto par/impar de la rejilla
       decide cuál le toca a cada pieza— se reproduce acá el mismo envoltorio y se
       emite DOS veces: una cae sobre `--muni-surface` y la otra sobre
       `--muni-surface-3`. */
    /* LA ENTRADA DE VITRINA, tal como se entrega: DOS componentes en el mismo valor,
       uno abierto en «Seguridad» y otro en «Zona de peligro». Un grupo de pestañas
       muestra un panel a la vez, así que con una sola tarjeta la reja mediría el
       panel por defecto y nunca vería ni la lista de sesiones ni el botón
       destructivo, que es justo donde vive el color propio del componente. */
    $entradaVitrina = ajustesVitrinaBlade();
    $tarjeta = Blade::render($entradaVitrina);

    foreach (['vitrina-claro' => 'light', 'vitrina-oscuro' => 'dark'] as $nombre => $tema) {
        $piezas = '';

        foreach (['ajustes-cuenta', 'ajustes-cuenta (sobre la otra superficie)'] as $rotulo) {
            $piezas .= '<section class="v-pieza" data-pieza="'.$rotulo.'">'
                .'<h2 class="v-nombre">&lt;x-muni::ajustes-cuenta&gt;</h2>'
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
            .' data-vitrina-hoja="muni-ui.css" data-vitrina-espera-alpine="1"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Tarjeta de vitrina — ajustes-cuenta, tema '.($tema === 'dark' ? 'oscuro' : 'claro').'</title>'
            .'<style>'.cssMuniUi().'</style><style>'.$marco.'</style></head>'
            .'<body><main id="muni-contenido" tabindex="-1"><div class="v-grid">'.$piezas.'</div></main>'
            .$sonda
            /* El marcador que la reja espera cuando la página declara
               `data-vitrina-espera-alpine`. Sin él mide «lo visible en reposo», y en
               reposo los paneles siguen bajo x-cloak: no mediría nada de este
               componente y daría verde por vacío. El abridor de la vitrina general
               vive en tests/GeneraVitrinaTest.php, que es compartido: acá se marca lo
               mínimo, que es que Alpine montó.

               VA ANTES DE ALPINE, no después: el build de CDN arranca en un
               microtask de su propio <script>, y los microtasks se drenan al terminar
               de evaluarlo, o sea ANTES de que corra la etiqueta siguiente. Registrado
               después, el oyente llegaba tarde y la reja medía cuatro páginas «sin
               hidratar». */
            .'<script data-banco="listo">document.addEventListener(\'alpine:initialized\','
            .'function(){requestAnimationFrame(function(){requestAnimationFrame(function(){'
            .'document.documentElement.setAttribute(\'data-vitrina-lista\',\'1\');});});});</script>'
            .'<script data-banco="alpine-focus">'.$focus.'</script>'
            .'<script data-banco="alpine-core">'.$alpine.'</script>'
            .'</body></html>'
        );
    }

    expect(str_contains((string) file_get_contents($dir.'/vitrina-oscuro.html'), 'role="tablist"'))->toBeTrue(
        'La tarjeta de vitrina salió sin las pestañas: no habría nada que medir con la reja.'
    );
});

/**
 * LA ENTRADA DE VITRINA, literal. `tests/GeneraVitrinaTest.php` es un archivo
 * compartido que este componente no toca: la entrada se entrega como dato para que
 * la pegue quien integre. Vive acá para que el banco mida EXACTAMENTE lo que se
 * entrega, y para que una prueba compruebe que rinde.
 */
function ajustesVitrinaBlade(): string
{
    $sesiones = '['
        ."['id' => 's-actual', 'device' => 'Chrome · Windows 11', 'detail' => 'Graneros, Región de O\'Higgins · ahora mismo', 'current' => true],"
        ."['id' => 's-iphone', 'device' => 'Safari · iPhone', 'detail' => 'Rancagua, Región de O\'Higgins · hace 2 horas', 'current' => false],"
        .']';

    $comunes = ':sessions="'.$sesiones.'" sessions-action="/mi-cuenta/sesiones/cerrar" '
        .'danger-action="/mi-cuenta/eliminar" danger-word="ELIMINAR" danger-method="delete" '
        .'danger-description="La cuenta deja de existir y el acceso se revoca en todos los sistemas."';

    $ranuras = '<x-slot:perfil><x-muni::card title="Datos personales">'
        .'<x-muni::input label="Correo institucional" name="correo" value="c.bugueno@graneros.cl" /></x-muni::card></x-slot:perfil>'
        .'<x-slot:preferencias><x-muni::card title="Accesibilidad">'
        .'<x-muni::switch label="Reducir animaciones" name="mov" /></x-muni::card></x-slot:preferencias>';

    return '<x-muni::ajustes-cuenta id="vitrina-ajc-seguridad" default="1" '.$comunes.'>'.$ranuras.'</x-muni::ajustes-cuenta>'
        .'<x-muni::ajustes-cuenta id="vitrina-ajc-peligro" default="3" '.$comunes.'>'.$ranuras.'</x-muni::ajustes-cuenta>';
}

it('la entrada de vitrina que se entrega rinde, y abre los dos paneles que traen color propio', function () {
    $html = Blade::render(ajustesVitrinaBlade());

    expect(str_contains($html, 'muni-ajc'))->toBeTrue('La entrada de vitrina no rinde el componente.');
    // Por el aria-label y no por `role="tablist"`: el bloque <noscript> del componente
    // nombra ese selector en una regla CSS y el conteo crudo daba tres.
    expect(substr_count($html, 'aria-label="Secciones de mi cuenta"'))->toBe(2,
        'La entrada de vitrina no trae las dos tarjetas.'
    );
    expect(str_contains($html, 'vitrina-ajc-seguridad-panel-1'))->toBeTrue('Falta el panel de Seguridad.');
    expect(str_contains($html, 'vitrina-ajc-peligro-panel-3'))->toBeTrue('Falta el panel de la zona de peligro.');

    // Ids distintos: dos grupos con las mismas etiquetas en la misma página colisionarían
    // en aria-controls y el lector anunciaría el panel equivocado.
    expect(str_contains($html, 'id="vitrina-ajc-seguridad"') && str_contains($html, 'id="vitrina-ajc-peligro"'))
        ->toBeTrue('Las dos tarjetas comparten id.');
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function ajustesPythonDeLaReja(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

it('en Chromium y Firefox: las pestañas, la puerta del peligro, el diálogo y el modo sin JS', function () {
    $python = ajustesPythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/ajustes-cuenta.py').' '
        .escapeshellarg(__DIR__.'/../build/ajustes-cuenta').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de ajustes-cuenta falló:\n".implode("\n", $lineas));

    // Cuatro páginas × dos navegadores: el estado en espera TIENE que dibujarse. Es
    // el defecto que las pruebas de texto no veían.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'espera=dashed'))))->toBe(8,
        "El botón destructivo tiene que verse distinto en espera en los ocho recorridos:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'puerta=bloquea'))))->toBe(8,
        "La puerta de la zona de peligro tiene que bloquear el envío en los ocho recorridos:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'dialogo=confirma'))))->toBe(8,
        "El diálogo tiene que confirmar el POST en los ocho recorridos:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'sin-js=alcanzable'))))->toBe(2,
        "Con el JS apagado los formularios tienen que quedar alcanzables en los dos navegadores:\n".implode("\n", $lineas));
});
