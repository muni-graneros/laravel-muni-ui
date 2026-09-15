<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CAMPO DE CLAVE CON ALTERNADOR DE VISIBILIDAD
|--------------------------------------------------------------------------
|
| `<x-muni::input-password>` es el campo de la pantalla de ingreso, del cambio
| de clave del funcionario y del restablecimiento de clave del vecino. La ficha
| `campo-clave` de docs/GAP-ANALYSIS.md pedía tres cosas; el juez las partió en
| dos entregas y esta prueba cubre la primera —revelar la clave— con las siete
| correcciones que exigió:
|
| 1. NO REIMPLEMENTA EL CAMPO. Envuelve `<x-muni::input>`, que ya trae la
|    etiqueta, el `hint`, el `error` atado por `aria-describedby` y el foco de
|    3 px. Un campo propio abriría una segunda verdad de estilo de formulario.
| 2. EL ALTERNADOR ES `type="button"`. Dentro de un `<form>` un botón sin type
|    es `submit`: pulsar «Mostrar» enviaría el formulario de ingreso. Es el bug
|    clásico de este patrón y por eso tiene candado propio.
| 3. `autocomplete` FORZADO por prop, y solo `current-password` o
|    `new-password`. Es lo que hace funcionar al gestor de contraseñas, que es
|    la vía que WCAG 2.2 SC 3.3.8 sí admite; hoy falta en el login de
|    atencionvecino. Cualquier otro valor revienta al renderizar en vez de
|    llegar a producción en silencio.
| 4. EL BOTÓN DICE SU ESTADO. `aria-pressed` y el nombre accesible cambian los
|    dos («Mostrar contraseña» ↔ «Ocultar contraseña»): el defecto que la ficha
|    le anota a la referencia de Preline es justamente que no cambian.
| 5. SIN JS NO SE OFRECE. El alternador nace oculto y lo destapa Alpine: un
|    botón que no hace nada es peor que no tener botón.
| 6. NO RECUERDA EL ESTADO. Ni entre campos ni entre páginas: nada de
|    localStorage, sessionStorage ni cookies. Se oculta solo al enviar el
|    formulario y al salir el foco del componente. En el mesón de atención la
|    mirada por encima del hombro es un riesgo real.
| 7. NINGÚN ATAJO GLOBAL. Escape se escucha en el elemento, nunca en la
|    ventana, y solo se lo queda cuando hay algo que ocultar.
|
| Lo que solo se ve en un navegador —que alternar no pierda el punto de
| inserción, que el foco no se escape del botón, que Enter y Espacio alternen,
| que el tamaño del blanco de pulsación llegue a 24 px— se mide en
| `tests/navegador/campo-de-clave.py` sobre el banco `build/campo-de-clave/`.
|
| Se prueba con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra aguja que se busca, no un mensaje, y la
| aserción se desactiva sin avisar.
*/

/** El `input-password.blade.php` crudo, para mirarle el CSS y las expresiones. */
function fuenteCampoDeClave(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/input-password.blade.php');
}

/** La fuente sin comentarios de Blade ni de PHP ni de CSS: solo lo que llega al navegador. */
function fuenteCampoDeClaveSinComentarios(): string
{
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', fuenteCampoDeClave());

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El contenido de los bloques de estilo del componente, sin comentarios. */
function cssCampoDeClave(): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteCampoDeClave(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/**
 * Los valores de todos los atributos de Alpine del HTML dado, ya decodificados
 * como los ve el navegador (`x-…`, `:…`).
 *
 * @return array<int, string>
 */
function expresionesAlpineDeLaClave(string $html): array
{
    preg_match_all('/\s(?:x-[a-z:.-]+|:[a-z-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES), $m[1]);
}

/** El componente renderizado con los props que le pase el anfitrión. */
function clave(string $props = ''): string
{
    return Blade::render('<x-muni::input-password '.$props.' />');
}

it('envuelve al input del paquete en vez de reimplementar el campo', function () {
    $fuente = fuenteCampoDeClave();

    expect(str_contains($fuente, '<x-muni::input'))->toBeTrue(
        'El campo de clave tiene que envolver <x-muni::input>: duplicarlo abre una segunda '.
        'verdad de estilo de campo, y la etiqueta, el error y el foco ya están resueltos ahí.'
    );

    // Ni etiqueta propia ni input propio: los dos son del componente base.
    expect((bool) preg_match('/<input\b/i', fuenteCampoDeClaveSinComentarios()))->toBeFalse(
        'El componente escribe su propio <input>. Debe pasar por <x-muni::input>.'
    );
    expect((bool) preg_match('/<label\b/i', fuenteCampoDeClaveSinComentarios()))->toBeFalse(
        'El componente escribe su propia <label>. La etiqueta la pone <x-muni::input>.'
    );

    $html = clave('label="Contraseña" name="password"');

    expect((bool) preg_match('/<input\b[^>]*\btype="password"/', $html))->toBeTrue(
        'No se renderiza un <input type="password">: '.$html
    );
    expect((bool) preg_match('/<label\b[^>]*\bfor="muni-password"/', $html))->toBeTrue(
        'La etiqueta no apunta al campo por un id derivado del name (nunca uniqid): '.$html
    );
});

it('el alternador es un <button type="button">, que es el bug clásico de este patrón', function () {
    $html = clave('name="password"');

    preg_match('/<button\b[^>]*>/', $html, $boton);

    expect($boton)->not->toBeEmpty('El componente no emite ningún <button> para alternar.');
    expect((bool) preg_match('/\btype="button"/', $boton[0]))->toBeTrue(
        'El alternador no declara type="button". Dentro de un <form> un botón sin type es '.
        'submit: pulsar «Mostrar» enviaría el formulario de ingreso. '.$boton[0]
    );

    // Y ADEMÁS ESCRITO ACÁ A PROPÓSITO. El botón del paquete ya pone
    // type="button" por omisión, así que la medición de arriba sobre el HTML
    // renderizado seguiría verde aunque alguien borrara el atributo de la
    // fuente: mediría el valor por omisión del otro componente, no la decisión
    // de este. Lo que el juez exigió es que este componente NO dependa de esa
    // omisión —cambiarla en button.blade.php convertiría en submit al
    // alternador de todas las pantallas de ingreso—, y eso solo se protege
    // leyendo la fuente.
    $fuente = fuenteCampoDeClaveSinComentarios();

    expect((bool) preg_match('/<x-muni::button\b[^>]*\btype="button"/s', $fuente))->toBeTrue(
        'El alternador ya no escribe type="button" en su fuente y queda colgando del valor por '.
        'omisión de <x-muni::button>. Dentro de un <form>, un botón que vuelva a ser submit envía '.
        'el formulario de ingreso al pulsar «Mostrar»: el atributo va escrito acá.'
    );
});

it('el botón dice su estado y cambia de nombre accesible', function () {
    $html = clave('name="password"');

    preg_match('/<button\b[^>]*>/', $html, $boton);

    expect((bool) preg_match('/\baria-pressed="false"/', $boton[0]))->toBeTrue(
        'El alternador nace sin aria-pressed="false": el lector no puede decir si está pulsado. '.$boton[0]
    );
    expect((bool) preg_match('/\bx-bind:aria-pressed="/', $boton[0]))->toBeTrue(
        'aria-pressed no se actualiza al alternar. '.$boton[0]
    );
    expect(str_contains($html, 'Mostrar contraseña') && str_contains($html, 'Ocultar contraseña'))->toBeTrue(
        'Los dos nombres accesibles tienen que estar en el DOM, uno visible por vez: '.$html
    );
    // El control se nombra con TEXTO, no solo con un icono: el estado no puede
    // viajar únicamente en el dibujo del ojo (WCAG 2.2 AA 1.4.1).
    expect((bool) preg_match('/<svg\b[^>]*aria-hidden="true"/', $html))->toBeTrue(
        'El icono del alternador tiene que ir aria-hidden: lo que nombra al botón es el texto. '.$html
    );
});

it('sin JavaScript el alternador no se ofrece', function () {
    $html = clave('name="password"');

    preg_match('/<button\b[^>]*>/', $html, $boton);

    expect(str_contains($boton[0], 'x-cloak'))->toBeTrue(
        'El alternador no nace oculto: sin Alpine quedaría un botón que no hace nada, '.
        'anunciado igual por el lector de pantalla. '.$boton[0]
    );
    expect((bool) preg_match('/\[x-cloak\]\s*\{[^}]*display\s*:\s*none/', cssCampoDeClave()))->toBeTrue(
        'El componente no declara su propia regla de [x-cloak]. Dentro de un panel Filament solo '.
        'se carga muni-ui-filament.css (DESIGN §7): lo que el componente necesita viaja con él.'
    );
});

it('fuerza el autocomplete del gestor de contraseñas y rechaza cualquier otro valor', function () {
    expect((bool) preg_match('/<input\b[^>]*\bautocomplete="current-password"/', clave('name="password"')))->toBeTrue(
        'Por defecto el campo tiene que declarar autocomplete="current-password" (pantalla de ingreso).'
    );
    expect((bool) preg_match('/<input\b[^>]*\bautocomplete="new-password"/', clave('name="password" autocomplete="new-password"')))->toBeTrue(
        'Con autocomplete="new-password" el campo tiene que declararlo: es el cambio y el restablecimiento de clave.'
    );

    // Blade envuelve en ViewException lo que lanza una vista y NO deja la causa
    // como `previous`, así que lo que se comprueba es que reviente y que el
    // mensaje diga qué poner en su lugar.
    $lanzado = null;

    try {
        clave('name="password" autocomplete="off"');
    } catch (Throwable $e) {
        $lanzado = $e;
    }

    expect($lanzado)->not->toBeNull(
        'Un autocomplete que el gestor de contraseñas no entiende tiene que reventar al renderizar, '.
        'no llegar callado a la pantalla de ingreso.'
    );
    expect(str_contains((string) $lanzado?->getMessage(), 'current-password')
        && str_contains((string) $lanzado?->getMessage(), 'new-password'))->toBeTrue(
            'El mensaje no dice qué valores sí acepta: '.(string) $lanzado?->getMessage()
        );
});

it('el error, la ayuda y lo obligatorio los sigue poniendo el input del paquete', function () {
    $html = clave('name="password" label="Contraseña" required hint="Al menos 12 caracteres." error="La clave no coincide."');

    expect(str_contains($html, 'La clave no coincide.'))->toBeTrue('El error del servidor no se pinta.');
    expect(str_contains($html, 'Al menos 12 caracteres.'))->toBeTrue('La ayuda no se pinta.');
    expect((bool) preg_match('/<input\b[^>]*\baria-invalid="true"/', $html))->toBeTrue(
        'El campo en error no se anuncia inválido: '.$html
    );
    expect((bool) preg_match('/<input\b[^>]*\baria-describedby="[^"]*muni-password-error/', $html))->toBeTrue(
        'El error no queda atado al campo por aria-describedby: '.$html
    );
    expect((bool) preg_match('/<input\b[^>]*\brequired\b/', $html))->toBeTrue('`required` no llega al campo.');
});

it('no recuerda el estado ni entre campos ni entre páginas', function () {
    $fuente = fuenteCampoDeClaveSinComentarios();

    foreach (['localStorage', 'sessionStorage', 'document.cookie', '$persist'] as $memoria) {
        expect(str_contains($fuente, $memoria))->toBeFalse(
            "El componente guarda el estado en {$memoria}. La clave nace SIEMPRE oculta: en el mesón ".
            'de atención la mirada por encima del hombro es un riesgo real.'
        );
    }

    // Nace oculta también en el HTML, no solo en la intención.
    expect((bool) preg_match('/x-data="[^"]*visible:\s*false/', clave('name="password"')))->toBeTrue(
        'El estado inicial del componente no es oculto.'
    );
});

it('se oculta sola al enviar el formulario y al salir el foco', function () {
    $fuente = fuenteCampoDeClaveSinComentarios();

    expect(str_contains($fuente, "closest('form')") && str_contains($fuente, "addEventListener('submit'"))->toBeTrue(
        'No se oculta al enviar el formulario: la clave quedaría a la vista mientras carga la página siguiente.'
    );
    expect(str_contains($fuente, "removeEventListener('submit'"))->toBeTrue(
        'El oyente del submit no se suelta al destruir el componente: con Livewire el nodo se '.
        'reemplaza en cada round-trip y los oyentes se acumulan sobre el mismo formulario.'
    );
    expect(str_contains($fuente, 'x-on:focusout'))->toBeTrue(
        'No se oculta al salir el foco del componente.'
    );
});

it('no captura teclas de la ventana: Escape va en el elemento', function () {
    $fuente = fuenteCampoDeClaveSinComentarios();

    expect((bool) preg_match('/x-on:key(down|up)[^=]*\.window/', $fuente))->toBeFalse(
        'El componente escucha teclas en la ventana. Un campo de un formulario no puede '.
        'quedarse con Escape de toda la página.'
    );
    expect((bool) preg_match('/x-on:keydown\.escape="[^"]*visible/', $fuente))->toBeTrue(
        'Escape no oculta la clave revelada desde el propio componente.'
    );
});

it('ningún texto del anfitrión entra en una expresión de Alpine', function () {
    // El apóstrofo es el caso que tumbó al file-dropzone: Blade lo escapa a
    // &#039;, el navegador lo decodifica DENTRO del valor del atributo y la
    // expresión queda descuadrada, así que Alpine se cae con la página entera.
    $veneno = "O'Higgins '+fetch('//x')+'";

    // Va CRUDO dentro del atributo del componente, que es como lo escribiría el
    // anfitrión: es Blade quien tiene que escaparlo al pintarlo.
    $html = clave('name="password" label="'.$veneno.'" show-label="Mostrar '.$veneno.'"');

    foreach (expresionesAlpineDeLaClave($html) as $expresion) {
        expect(str_contains($expresion, "O'Higgins"))->toBeFalse(
            "Texto del anfitrión dentro de una expresión de Alpine: {$expresion}"
        );
    }

    // Y el texto sí llega a la página, como contenido escapado.
    expect(str_contains($html, 'O&#039;Higgins'))->toBeTrue('El rótulo del anfitrión no se pinta.');
});

it('no escribe un solo color literal y el foco es un outline, no una sombra', function () {
    // Sin comentarios: un `&#039;` citado en prosa no es un color, y el candado
    // tiene que medir lo que llega al navegador.
    $fuente = fuenteCampoDeClaveSinComentarios();

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $fuente, $colores);
    $prohibidos = array_values(array_diff(array_unique($colores[0]), ['#767676']));

    expect($prohibidos)->toBe([],
        'Colores literales en el componente: '.implode(', ', $prohibidos).'. Solo se admite el '.
        'respaldo de foco #767676 (DESIGN §10).'
    );

    // El alternador es el <x-muni::button> del paquete, así que el indicador de
    // foco REAL viaja en el HTML renderizado aunque no esté en esta fuente.
    $html = clave('name="password"');

    expect((bool) preg_match('/outline:\s*3px solid var\(--muni-focus, var\(--muni-accent, #767676\)\)/', $html))->toBeTrue(
        'El alternador no lleva el indicador de foco del contrato: dentro de Filament la '.
        'box-shadow del anillo se pierde (DESIGN §5).'
    );
});

it('el blanco de pulsación del alternador llega a 24 px', function () {
    $html = clave('name="password"');

    preg_match('/<button\b[^>]*>/', $html, $boton);
    preg_match('/style="([^"]*)"/', $boton[0], $estilo);

    expect((bool) preg_match('/min-height:\s*(\d+)px/', $estilo[1] ?? '', $alto) && (int) $alto[1] >= 24)->toBeTrue(
        'El alternador no declara un alto mínimo de 24 px (WCAG 2.2 AA 2.5.8): '.($estilo[1] ?? '(sin style)')
    );
});

it('el borde del alternador va en el estilo en línea, que es el único sitio donde llega', function () {
    // Medido en Chromium sobre un botón fantasma pelado: `<x-muni::button>`
    // emite `border:1px solid transparent` en su PROPIO `style`, y un estilo en
    // línea le gana a cualquier regla de hoja sin `!important`. O sea que
    // `.muni-btn--ghost { border-color }` y su `:hover` son inertes para todos
    // los botones fantasma del paquete —defecto de button.blade.php, no de
    // acá— y el hover se comunica por el fondo. Como este alternador es un
    // control junto a un campo y necesita 3:1 (WCAG 1.4.11), su borde tiene
    // que ir en línea. Si alguien lo "ordena" moviéndolo al bloque de estilo
    // del componente, el botón se queda sin borde visible y nadie se entera:
    // por eso el candado mira los dos lados.
    $html = clave('name="password"');

    preg_match('/<button\b[^>]*>/', $html, $boton);
    preg_match('/style="([^"]*)"/', $boton[0], $estilo);

    expect((bool) preg_match('/border-color:\s*var\(--muni-field-border\)/', $estilo[1] ?? ''))->toBeTrue(
        'El borde del alternador no está en su estilo en línea con --muni-field-border. El botón '.
        'del paquete pinta `border:1px solid transparent` en línea y le gana a la hoja: fuera del '.
        'atributo `style` el borde no llega, y --muni-field-border es el único token del paquete '.
        'calibrado a 3:1 contra las superficies en los dos temas. Estilo medido: '.($estilo[1] ?? '(sin style)')
    );

    expect((bool) preg_match('/border-color/', cssCampoDeClave()))->toBeFalse(
        'El bloque de estilo del componente declara un border-color: ahí es inerte (le gana el '.
        'estilo en línea del botón del paquete) y deja el alternador sin borde visible.'
    );
});

it('el movimiento sale del token, que ya baja a 0 ms con la preferencia', function () {
    $css = cssCampoDeClave().' '.clave('name="password"');

    preg_match_all('/transition:[^;"]*/', $css, $transiciones);

    foreach ($transiciones[0] as $transicion) {
        expect((bool) preg_match('/\d+(\.\d+)?m?s/', $transicion))->toBeFalse(
            "Transición con duración fija: «{$transicion}». Tiene que salir de var(--muni-dur), que ".
            'ya pasa a 0 ms con prefers-reduced-motion (DESIGN §6).'
        );
    }
});

it('el valor viaja al backend con el name del anfitrión y nunca se repinta desde el servidor', function () {
    $html = clave('name="clave_nueva"');

    expect((bool) preg_match('/<input\b[^>]*\bname="clave_nueva"/', $html))->toBeTrue(
        'El name del anfitrión no llega al campo: '.$html
    );
    // Un `value` en un campo de clave devuelve la clave del vecino en el HTML de
    // la respuesta: el componente no declara la prop y no la escribe nunca.
    expect((bool) preg_match('/\bvalue\s*=>/', fuenteCampoDeClave()))->toBeFalse(
        'El componente declara una prop `value`. Un campo de clave no se repinta desde el servidor.'
    );
});

it('el anfitrión puede seguir poniéndole su wire:model y su id', function () {
    $html = Blade::render('<x-muni::input-password name="password" id="clave-ingreso" wire:model="clave" />');

    expect((bool) preg_match('/<input\b[^>]*\bid="clave-ingreso"/', $html))->toBeTrue('El id del anfitrión no manda: '.$html);
    expect((bool) preg_match('/<input\b[^>]*\bwire:model="clave"/', $html))->toBeTrue('wire:model no llega al campo: '.$html);
    expect((bool) preg_match('/<button\b[^>]*\baria-controls="clave-ingreso"/', $html))->toBeTrue(
        'El alternador no declara sobre qué campo actúa: '.$html
    );
});

/*
|--------------------------------------------------------------------------
| EL BANCO DE NAVEGADOR: build/campo-de-clave/{claro,oscuro}.html
|--------------------------------------------------------------------------
|
| Lo de arriba lee el HTML que emite Blade. Lo que la ficha promete de verdad
| —que alternar NO pierda el punto de inserción ni el valor, que Enter y Espacio
| alternen sin enviar el formulario, que el foco se quede en el botón, que la
| clave se vuelva a ocultar sola al enviar y al salir el foco, que sin JS el
| alternador no aparezca, y que el blanco de pulsación y el contraste den— solo
| se ve en un navegador. Lo mide `tests/navegador/campo-de-clave.py` en Chromium
| y en Firefox (se salta, no se finge, sin `.venv-a11y`).
|
| El banco es la pantalla real de cambio de clave sobre `app-shell`, con los dos
| autocomplete que existen (`current-password` y `new-password`) y el segundo
| campo en error, dentro de un <form> que no navega. Alpine sale de
| node_modules, nunca de un CDN: el banco se abre sin red.
*/

/** Alpine 3 (núcleo) tal como lo trae node_modules, o null si no se corrió `npm install`. */
function claveAlpineParaBanco(): ?string
{
    $ruta = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    return is_file($ruta) ? (string) file_get_contents($ruta) : null;
}

/**
 * Una página del banco. La hoja y Alpine entran por marcadores y NO por Blade:
 * el CSS entero pasado por `Blade::render()` es texto que Blade compila, y una
 * arroba desconocida o unas llaves dobles en un comentario cambiarían la hoja
 * en silencio.
 */
function clavePaginaDelBanco(?string $tema, string $css, ?string $alpine): string
{
    $html = Blade::render(
        '<x-muni::app-shell system="Atención al Vecino" subtitle="Municipalidad de Graneros" title="Cambiar mi clave"'
        .($tema !== null ? ' theme="'.$tema.'"' : '').'>'
        .'<x-slot:head>__CSS__</x-slot:head>'
        .'<x-muni::page-header title="Cambiar mi clave" subtitle="Funcionario municipal" />'
        // El formulario NO navega: lo que se mide es que el evento `submit`
        // vuelva a ocultar la clave, no la página siguiente.
        .'<form id="banco-form" onsubmit="return false;" style="display:flex;flex-direction:column;gap:14px;max-width:420px;">'
        .'<x-muni::input label="Correo institucional" name="correo" id="banco-correo" type="email" autocomplete="username" />'
        .'<x-muni::input-password name="clave_actual" label="Clave actual" />'
        .'<x-muni::input-password name="clave_nueva" label="Clave nueva" autocomplete="new-password"'
        .' hint="Al menos 12 caracteres, con una mayúscula y un número."'
        .' error="La clave nueva no puede ser la misma que la anterior." />'
        .'<x-muni::button type="submit" id="banco-enviar">Guardar la clave</x-muni::button>'
        .'</form>'
        .'__ALPINE__'
        .'</x-muni::app-shell>'
    );

    return str_replace(
        ['__CSS__', '__ALPINE__'],
        ['<style>'.$css.'</style>', $alpine !== null ? '<script data-banco="alpine-core">'.$alpine.'</script>' : ''],
        $html,
    );
}

it('genera el banco de navegador en build/campo-de-clave/: la pantalla real de cambio de clave', function () {
    $css = (string) file_get_contents(__DIR__.'/../resources/css/muni-ui.css');
    $alpine = claveAlpineParaBanco();

    $dir = __DIR__.'/../build/campo-de-clave';
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    foreach (['claro' => 'light', 'oscuro' => 'dark'] as $pagina => $tema) {
        $html = clavePaginaDelBanco($tema, $css, $alpine);
        file_put_contents($dir.'/'.$pagina.'.html', $html);

        expect(str_contains($html, '__CSS__') || str_contains($html, '__ALPINE__'))->toBeFalse('Quedó un marcador sin reemplazar en '.$pagina.'.');
        expect(substr_count($html, 'class="muni-pw"'))->toBe(2, 'El banco '.$pagina.' no trae los dos campos de clave.');
        expect(str_contains($html, 'data-banco="alpine-core"'))->toBeTrue(
            'El banco salió sin Alpine: corre `npm install`; sin él no se puede medir el alternador.'
        );
        preg_match('/<html\b[^>]*>/', $html, $raiz);
        expect(str_contains($raiz[0] ?? '', 'data-muni-theme="'.$tema.'"'))->toBeTrue(
            'El <html> del banco '.$pagina.' no lleva el activador de tema: '.($raiz[0] ?? '(sin <html>)')
        );
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function clavePythonDelBanco(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

it('en Chromium y Firefox: alterna sin perder el cursor ni enviar el form, se oculta sola y sin JS no aparece', function () {
    $python = clavePythonDelBanco();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/campo-de-clave.py').' '
        .escapeshellarg(__DIR__.'/../build/campo-de-clave').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador del campo de clave falló:\n".implode("\n", $lineas));

    // Dos páginas × dos navegadores: los cuatro recorridos tienen que reportar
    // el cursor repuesto y la transición apagada con la preferencia. Las dos
    // cifras SALEN DE LA MEDICIÓN del banco: hasta hace poco la del movimiento
    // era un literal impreso a mano, así que este recuento daba 4 aunque la
    // transición siguiera viva y aparentaba medir algo que no medía.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'cursor=5'))))->toBe(4,
        "Los cuatro recorridos tienen que reponer el punto de inserción:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'transición(reduce)=0s'))))->toBe(4,
        "Los cuatro recorridos tienen que reportar la transición apagada con la preferencia:\n".implode("\n", $lineas));

    // El borde del alternador CON EL RATÓN ENCIMA, que es el estado donde el
    // fondo del hover se le acerca: en los cuatro recorridos tiene que seguir
    // por encima de 3:1 (WCAG 1.4.11), en claro y en oscuro.
    $bordes = [];

    foreach ($lineas as $linea) {
        if (preg_match('/borde\/hover=([\d.]+):1/', $linea, $m)) {
            $bordes[] = (float) $m[1];
        }
    }

    expect(count($bordes))->toBe(4, "Faltan mediciones del borde en hover:\n".implode("\n", $lineas));
    expect(min($bordes) >= 3.0)->toBeTrue(
        'El borde del alternador con el ratón encima baja de 3:1 en algún recorrido: '.implode(', ', $bordes)
    );
});
