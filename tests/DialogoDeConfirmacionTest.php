<?php

use Illuminate\Support\Facades\Blade;

/*
 * LA VARIANTE ENDURECIDA DE `<x-muni::modal>` PARA CONFIRMAR ALGO IRREVERSIBLE.
 *
 * La ficha `confirmar` de docs/GAP-ANALYSIS.md pedía un componente nuevo. El juez
 * la corrigió: es `modal` con cuatro props más —`role="alertdialog"`,
 * `:dismissable="false"`, `describedby`, `initial-focus="cancelar"`— y con una
 * forma de abrirse desde el servidor por evento de navegador, como `toast-host`.
 * El motivo obligatorio quedó FUERA del alcance (sería un mini-formulario con
 * validación y binding distinto en Livewire 3 y 4), y dentro de un panel Filament
 * manda `->requiresConfirmation()` con su `schema`, no esto.
 *
 * Todo lo de acá es ADITIVO: la primera prueba congela lo que ya emitía `modal`
 * para que ningún consumidor cambie de comportamiento sin haber pasado una prop.
 *
 * Las aserciones van con `expect(bool)->toBeTrue('mensaje')`: el segundo
 * argumento de `toContain()` en Pest es OTRA aguja, no un mensaje, y desactiva
 * la aserción sin avisar.
 *
 * Qué cubre cada capa, dicho sin adornos: las pruebas de Blade de este archivo
 * leen el HTML emitido y congelan el CONTRATO (qué atributos, qué clases, qué
 * hay en el x-data). El COMPORTAMIENTO —dónde cae el foco de verdad, que Escape
 * cierre solo el diálogo de arriba, que el panel esté centrado, que la guardia
 * de movimiento reducido gane en la cascada— lo mide únicamente la prueba de
 * navegador del final, que se SALTA sin `.venv-a11y`. En CI ese entorno no se
 * instala, así que ahí solo corre la capa de contrato: si algo se rompe en el
 * navegador sin cambiar el HTML, CI no lo ve. Es el criterio del repo, no un
 * descuido, y por eso la de navegador se corre local antes de cerrar la ficha.
 */

/** Render de un modal con los atributos que se quieran pasar y un pie opcional. */
function renderConfirmacion(string $atributos = '', string $pie = ''): string
{
    $footer = $pie !== '' ? '<x-slot:footer>'.$pie.'</x-slot:footer>' : '';

    return Blade::render(
        '<x-muni::modal title="¿Anular la solicitud 2024-118?" '.$atributos.'>'
        .'<p>La solicitud de Rosa Contreras quedará anulada. No se puede deshacer.</p>'
        .$footer
        .'</x-muni::modal>'
    );
}

/** El elemento que declara el rol del diálogo: su etiqueta de apertura completa. */
function etiquetaDelDialogo(string $html): string
{
    preg_match('/<div[^>]*\brole="(?:dialog|alertdialog)"[^>]*>/s', $html, $m);

    return $m[0] ?? '';
}

/** El fondo (velo) del diálogo: la etiqueta de apertura del primer hijo del contenedor. */
function etiquetaDelFondo(string $html): string
{
    preg_match('/<div[^>]*\brole="(?:dialog|alertdialog)"[^>]*>\s*<div([^>]*)>/s', $html, $m);

    return $m[1] ?? '';
}

/** El valor del `x-data` de la raíz del modal. */
function xDataDelModal(string $html): string
{
    preg_match('/x-data="([^"]*)"/s', $html, $m);

    return html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5);
}

it('sin props nuevas emite exactamente lo de siempre: dialog, ×, y cierre al clic en el fondo', function () {
    $html = renderConfirmacion();

    expect(str_contains(etiquetaDelDialogo($html), 'role="dialog"'))->toBeTrue(
        'Sin `role` el modal tiene que seguir siendo un dialog: hay nueve sistemas que lo usan así.'
    );
    expect(str_contains(etiquetaDelDialogo($html), 'aria-describedby'))->toBeFalse(
        'Un dialog corriente no describe su cuerpo entero: se anunciaría todo el formulario de golpe.'
    );
    expect(str_contains($html, 'aria-label="Cerrar"'))->toBeTrue(
        'El botón × desapareció del modal por defecto: eso rompe a los consumidores que hoy lo tienen.'
    );
    expect((bool) preg_match('/@click="open\s*=\s*false"/', etiquetaDelFondo($html)))->toBeTrue(
        'El fondo del modal por defecto dejó de cerrar al clic: comportamiento roto para los consumidores.'
    );
    expect(str_contains($html, 'data-muni-cancel'))->toBeFalse(
        'Sin `initial-focus="cancelar"` el modal no debe inventar un botón Cancelar.'
    );
});

it('con role="alertdialog" declara el rol y enlaza la pregunta con aria-describedby', function () {
    $html = renderConfirmacion('role="alertdialog"');
    $dialogo = etiquetaDelDialogo($html);

    expect(str_contains($dialogo, 'role="alertdialog"'))->toBeTrue(
        'No emite role="alertdialog": el lector anuncia un diálogo corriente y no una confirmación.'
    );
    expect(str_contains($dialogo, 'aria-modal="true"'))->toBeTrue('Perdió aria-modal="true".');
    expect((bool) preg_match('/aria-describedby="([^"]+)"/', $dialogo, $m))->toBeTrue(
        'Un alertdialog sin aria-describedby: lo primero que oye el lector no es la pregunta (APG alertdialog).'
    );
    expect(str_contains($html, 'id="'.$m[1].'"'))->toBeTrue(
        "El aria-describedby apunta a «{$m[1]}» y ningún elemento lleva ese id."
    );
    expect((bool) preg_match('/id="'.preg_quote($m[1], '/').'"[^>]*>(.*?)<\/div>/s', $html, $cuerpo))->toBeTrue();
    expect(str_contains($cuerpo[1], 'Rosa Contreras'))->toBeTrue(
        'El aria-describedby no apunta al cuerpo del diálogo, que es donde está la pregunta.'
    );
});

it('un role que no sea alertdialog cae en dialog: nunca un rol inventado', function () {
    $html = renderConfirmacion('role="menu"');

    expect(str_contains(etiquetaDelDialogo($html), 'role="dialog"'))->toBeTrue(
        'Un `role` fuera de {dialog, alertdialog} debe caer en dialog, no salir tal cual al HTML.'
    );
});

it('con describedby explícito respeta el id del consumidor', function () {
    $html = renderConfirmacion('role="alertdialog" describedby="resumen-2024-118 aviso-legal"');

    expect(str_contains(etiquetaDelDialogo($html), 'aria-describedby="resumen-2024-118 aviso-legal"'))->toBeTrue(
        'El `describedby` del consumidor tiene que ganar sobre el que calcula el componente.'
    );
});

it('con :dismissable="false" quita la × y el cierre al clic en el fondo, pero Escape sigue cancelando', function () {
    $html = renderConfirmacion(':dismissable="false"');

    expect(str_contains($html, 'aria-label="Cerrar"'))->toBeFalse(
        'Con dismissable=false no puede haber botón ×: obliga a elegir entre la acción y cancelar.'
    );
    expect((bool) preg_match('/@click/', etiquetaDelFondo($html)))->toBeFalse(
        'Con dismissable=false el fondo no debe cerrar al clic: un clic fuera no es una decisión.'
    );
    expect((bool) preg_match('/keydown\.escape/', $html))->toBeTrue(
        'Escape tiene que seguir cerrando: cancelar es la salida segura (WCAG 2.2 2.1.2).'
    );

    // Escape va EN EL ELEMENTO del diálogo, no en window: un oyente en window
    // cierra a la vez todos los diálogos abiertos de la página (un alertdialog
    // sobre un drawer cerraría los dos) y se traga el Escape de lo que viva
    // dentro. Como el foco está atrapado dentro, el keydown siempre pasa por el
    // elemento; y lleva `.stop` para que el diálogo de abajo no lo reciba.
    $dialogo = etiquetaDelDialogo($html);

    expect((bool) preg_match('/@keydown\.escape(\.\w+)*="open\s*=\s*false"/', $dialogo))->toBeTrue(
        'El elemento con rol de diálogo no escucha Escape: el cierre está en window (o en ningún lado).'
    );
    expect(str_contains($dialogo, '@keydown.escape.stop'))->toBeTrue(
        'El Escape del diálogo no detiene la propagación: un drawer abierto debajo se cerraría a la vez.'
    );
    // Un clic en el velo (no descartable) o en texto del panel mueve el foco al
    // ancestro enfocable más cercano; sin tabindex="-1" en el diálogo iría a
    // <body>, y desde ahí el Escape del elemento no se oye.
    expect(str_contains($dialogo, 'tabindex="-1"'))->toBeTrue(
        'El elemento del diálogo no lleva tabindex="-1": tras un clic en el velo el foco cae en <body> y Escape deja de cerrar.'
    );

    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../resources/views/components/modal.blade.php'));
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    expect(str_contains($fuente, 'keydown.escape.window'))->toBeFalse(
        'modal volvió a escuchar Escape en window.'
    );
});

it('acepta dismissable="false" como cadena, que es como llega sin los dos puntos', function () {
    $html = renderConfirmacion('dismissable="false"');

    expect(str_contains($html, 'aria-label="Cerrar"'))->toBeFalse(
        'dismissable="false" (cadena) tiene que valer lo mismo que :dismissable="false".'
    );
});

it('con initial-focus="cancelar" pone su propio botón Cancelar, marcado para el foco inicial', function () {
    $html = renderConfirmacion('role="alertdialog" initial-focus="cancelar"', '<button type="button">Anular solicitud</button>');

    expect((bool) preg_match('/<button[^>]*\bdata-muni-cancel\b[^>]*>(.*?)<\/button>/s', $html, $m))->toBeTrue(
        'No aparece el botón Cancelar propio del componente (`data-muni-cancel`).'
    );
    expect(trim(strip_tags($m[1])))->toBe('Cancelar');
    expect(str_contains($m[0], 'autofocus'))->toBeTrue(
        'El botón Cancelar no lleva `autofocus`: es lo que x-trap lee para elegir el foco inicial, '
        .'y sin eso lo primero que oye el lector es el botón destructivo o la ×.'
    );
    expect(str_contains($m[0], 'type="button"'))->toBeTrue('El Cancelar tiene que ser type="button": dentro de un <form> no puede enviar.');
    expect(str_contains($m[0], 'muni-btn--ghost'))->toBeTrue('El Cancelar es la salida segura: va en variante ghost, nunca con el color de la acción.');
    expect((bool) preg_match('/x-on:click="open\s*=\s*false"|@click="open\s*=\s*false"/', $m[0]))->toBeTrue(
        'El Cancelar propio no cierra el diálogo.'
    );

    // El Cancelar va ANTES de la acción en el DOM: Tab desde él llega a la acción,
    // y Enter sobre el foco inicial cancela, jamás ejecuta.
    expect(strpos($html, 'data-muni-cancel') < strpos($html, 'Anular solicitud'))->toBeTrue(
        'El Cancelar tiene que ir antes que la acción destructiva en el orden del DOM.'
    );
});

it('el rótulo del Cancelar se cambia con cancel-label y viaja como texto escapado', function () {
    $html = renderConfirmacion('initial-focus="cancelar" cancel-label="No, mantener la solicitud d\'Ana"');

    expect(str_contains($html, 'No, mantener la solicitud d&#039;Ana'))->toBeTrue(
        'El rótulo del Cancelar no sale escapado como contenido Blade.'
    );
    expect(str_contains(xDataDelModal($html), 'Ana'))->toBeFalse(
        'El rótulo del Cancelar entró en el x-data: un apóstrofo ahí tumba el Alpine de la página entera.'
    );
});

it('con initial-focus como selector, lo marca por x-init antes de que x-trap se inicialice', function () {
    $html = renderConfirmacion('initial-focus="[data-x=\'motivo\']"');

    expect(str_contains($html, 'data-muni-cancel'))->toBeFalse(
        'Con un selector propio no debe aparecer el Cancelar del componente.'
    );

    $xData = xDataDelModal($html);

    expect(str_contains($xData, 'motivo'))->toBeTrue('El selector de initial-focus no llegó al x-data.');
    expect(str_contains($xData, "'motivo'"))->toBeFalse(
        'El selector entró al x-data con el apóstrofo crudo: tiene que ir por @js(), que lo escapa como \\u0027.'
    );
    expect((bool) preg_match('/x-init="[^"]*marcarFoco\(\$el\)[^"]*"/', etiquetaDelDialogo($html)))->toBeTrue(
        'El elemento del diálogo no llama a marcarFoco($el) en x-init: x-trap lee [autofocus] al inicializarse, '
        .'y x-init corre antes en el mismo elemento; es la única ventana para estampar el atributo.'
    );
    expect(str_contains($xData, "setAttribute('autofocus'"))->toBeTrue(
        'marcarFoco no estampa `autofocus` en el destino.'
    );
});

it('respalda el foco inicial si la trampa se armó antes de que el panel se mostrara', function () {
    // x-trap arma la trampa 15 ms después de abrir y busca el destino en ese
    // instante; x-show muestra el panel en el siguiente cuadro de animación. Si el
    // cuadro llega tarde, sin respaldo el foco se queda en el disparador. Medido
    // en Firefox y forzado en tests/navegador/dialogo-de-confirmacion.py.
    $html = renderConfirmacion('role="alertdialog" initial-focus="cancelar"');

    expect((bool) preg_match('/x-effect="open\s*&&\s*programarFoco\(\$el\)"/', etiquetaDelDialogo($html)))->toBeTrue(
        'El elemento del diálogo no programa el respaldo del foco (x-effect con programarFoco($el)) al abrirse.'
    );

    $xData = xDataDelModal($html);

    expect(str_contains($xData, 'dialogo.contains(document.activeElement)'))->toBeTrue(
        'El respaldo no comprueba si el foco ya está dentro: pisaría el que puso x-trap y rompería el retorno al cerrar.'
    );
    expect(str_contains($xData, 'getClientRects().length'))->toBeTrue(
        'El respaldo no espera a que el destino sea visible: focus() sobre un display:none no hace nada.'
    );
    expect((bool) preg_match('/setTimeout\([^,]+,\s*(\d+)\)/', $xData, $m) && (int) $m[1] > 15)->toBeTrue(
        'El respaldo tiene que correr DESPUÉS de los 15 ms de x-trap; si se adelanta, la trampa toma el Cancelar como '
        .'«lo que estaba enfocado antes» y al cerrar devuelve el foco a un botón oculto.'
    );

    // Y vale para TODO modal, no solo para el endurecido: el juez pidió que la
    // variante «arregle modal para todos». Sin initial-focus, el destino del
    // respaldo es lo primero tabulable visible del diálogo (lo mismo que elige
    // la trampa), así que el clásico también sale de la carrera con el foco
    // dentro. Medido en el banco, con la carrera forzada sobre los tres modales.
    $clasico = xDataDelModal(renderConfirmacion());

    expect(str_contains($clasico, 'primeroTabulable('))->toBeTrue(
        'Sin initial-focus el respaldo no tiene destino: el modal clásico sigue dejando el foco en el disparador '
        .'cuando la trampa se arma antes de que el panel se muestre.'
    );
    expect((bool) preg_match('/x-effect="open\s*&&\s*programarFoco\(\$el\)"/', etiquetaDelDialogo(renderConfirmacion())))->toBeTrue(
        'El modal clásico no programa el respaldo del foco al abrirse.'
    );
});

it('se abre y se cierra por evento de navegador con su id, para el flujo servidor-primero', function () {
    $html = renderConfirmacion('id="anular-2024-118"');

    expect(str_contains($html, '@muni-modal-open.window='))->toBeTrue(
        'No escucha `muni-modal-open` en window: Livewire no puede abrirlo con $this->dispatch().'
    );
    expect(str_contains($html, '@muni-modal-close.window='))->toBeTrue(
        'No escucha `muni-modal-close` en window: el servidor no puede cerrarlo tras completar la acción.'
    );

    $xData = xDataDelModal($html);

    expect(str_contains($xData, 'anular-2024-118'))->toBeTrue(
        'El id del consumidor no está en el x-data: el evento no tiene contra qué comparar.'
    );
    expect((bool) preg_match('/@muni-modal-open\.window="[^"]*detail[^"]*\.id\s*===\s*id[^"]*"/', $html))->toBeTrue(
        'El evento de apertura no compara `$event.detail.id` con el id propio: abriría TODOS los modales de la página.'
    );
});

it('mantiene el id estable y derivado de la clave, con o sin id del consumidor', function () {
    $sinId = renderConfirmacion('role="alertdialog"');
    $otraVez = renderConfirmacion('role="alertdialog"');

    preg_match('/aria-describedby="([^"]+)"/', $sinId, $a);
    preg_match('/aria-describedby="([^"]+)"/', $otraVez, $b);

    expect($a[1] ?? null)->not->toBeNull();
    expect($a[1])->toBe($b[1] ?? null, 'El id del cuerpo cambia entre dos renders idénticos: parchea el DOM en cada morph de Livewire.');

    $conId = renderConfirmacion('role="alertdialog" id="anular-2024-118"');

    expect(str_contains(etiquetaDelDialogo($conId), 'aria-describedby="anular-2024-118-desc"'))->toBeTrue(
        'Con id del consumidor, el id del cuerpo tiene que derivar de él (`<id>-desc`) para poder referenciarlo desde fuera.'
    );

    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../resources/views/components/modal.blade.php'));
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    expect(str_contains($fuente, 'uniqid('))->toBeFalse('modal volvió a generar ids con uniqid().');
});

it('sin título, el alertdialog tiene un nombre accesible propio', function () {
    $html = Blade::render('<x-muni::modal role="alertdialog" :dismissable="false"><p>¿Seguro?</p></x-muni::modal>');
    $dialogo = etiquetaDelDialogo($html);

    expect((bool) preg_match('/aria-label="([^"]+)"/', $dialogo, $m))->toBeTrue(
        'Sin título ni aria-label el lector anuncia «diálogo de alerta» y nada más.'
    );
    expect(trim($m[1]))->not->toBe('');
});

/*
 * EL BANCO DE NAVEGADOR: `build/banco-confirmacion/*.html` (build/ está en .gitignore).
 *
 * Lo que las pruebas de arriba no pueden medir se mide ahí con Playwright, en
 * Chromium y Firefox (`tests/navegador/dialogo-de-confirmacion.py`): que el foco
 * cae de verdad en Cancelar (x-trap lee `[autofocus]` al inicializarse: eso solo se
 * ve con el plugin corriendo), que Tab no se escapa, que Escape devuelve el foco al
 * disparador, que el clic en el fondo NO cierra el alertdialog, que el evento de
 * navegador abre solo el modal con ese id, y que al cargar la página nadie roba el
 * foco por el `autofocus`. Es el mismo criterio que la vitrina: se mide lo que el
 * paquete emite, no una copia escrita a mano. Alpine y el plugin Focus salen de
 * node_modules, nunca de un CDN: el banco tiene que abrirse sin red.
 */
function alpineParaBanco(string $paquete): ?string
{
    $ruta = __DIR__.'/../node_modules/'.$paquete.'/dist/cdn.min.js';

    return is_file($ruta) ? (string) file_get_contents($ruta) : null;
}

function piezasDelBanco(): array
{
    return [
        // La variante endurecida, como se usa para anular una solicitud derivada.
        'alertdialog' => '<x-muni::modal id="banco-anular" title="¿Anular la solicitud 2024-118?"'
            .' role="alertdialog" :dismissable="false" initial-focus="cancelar">'
            .'<x-slot:trigger><x-muni::button variant="danger" id="banco-anular-abrir">Anular solicitud</x-muni::button></x-slot:trigger>'
            .'<p>La solicitud de <b>Rosa Contreras</b> (folio 2024-118) quedará anulada y se avisará a Obras. '
            .'No se puede deshacer desde el panel.</p>'
            .'<x-slot:footer><x-muni::button variant="danger" id="banco-anular-si">Anular solicitud</x-muni::button></x-slot:footer>'
            .'</x-muni::modal>',
        // El modal de siempre: sigue con ×, cierre al clic en el fondo y foco en lo primero tabulable.
        'clasico' => '<x-muni::modal id="banco-clasico" title="Detalle del giro 4821">'
            .'<x-slot:trigger><x-muni::button variant="ghost" id="banco-clasico-abrir">Ver detalle</x-muni::button></x-slot:trigger>'
            .'<p>Giro emitido el 8 de septiembre. Sin observaciones.</p>'
            .'<x-slot:footer><x-muni::button variant="ghost" id="banco-clasico-cerrar" x-on:click="open = false">Cerrar</x-muni::button></x-slot:footer>'
            .'</x-muni::modal>',
        // Foco inicial en un selector del consumidor (un campo del propio diálogo).
        'selector' => '<x-muni::modal id="banco-motivo" title="Observar la solicitud" initial-focus="#banco-motivo-campo">'
            .'<x-slot:trigger><x-muni::button variant="ghost" id="banco-motivo-abrir">Observar</x-muni::button></x-slot:trigger>'
            .'<x-muni::textarea label="Motivo de la observación" name="motivo" id="banco-motivo-campo" />'
            .'<x-slot:footer><x-muni::button id="banco-motivo-guardar">Guardar</x-muni::button></x-slot:footer>'
            .'</x-muni::modal>',
        // Los otros dos diálogos del paquete, DESPUÉS de los modales a propósito:
        // drawer y command-palette redefinen `.muni-fade` / `.muni-pop` en su propio
        // bloque de estilos de una sola vez y sin guardia de movimiento reducido. Al
        // ir después en la página, a igual especificidad ganan en la cascada, y la
        // guardia del modal tiene que seguir apagando la transición igual. El drawer
        // sirve además para apilar: un alertdialog abierto sobre él y Escape debe
        // cerrar solo el de arriba.
        'vecinos' => '<x-muni::drawer id="banco-lateral" title="Solicitud 2024-118 — Rosa Contreras">'
            .'<x-slot:trigger><x-muni::button variant="ghost" id="banco-lateral-abrir">Ver solicitud</x-muni::button></x-slot:trigger>'
            .'<p>Ingresada el 8 de septiembre por Ventanilla Única. Derivada a Obras.</p>'
            .'</x-muni::drawer>'
            .'<x-muni::command-palette :items="[[\'label\' => \'Bandeja de entrada\', \'url\' => \'#bandeja\', \'group\' => \'Trámites\']]">'
            .'<x-slot:trigger><x-muni::button variant="ghost" id="banco-paleta-abrir">Buscar (Ctrl+K)</x-muni::button></x-slot:trigger>'
            .'</x-muni::command-palette>',
    ];
}

/*
 * Son CUATRO páginas y no una, por lo mismo que la vitrina y el banco de `stat`
 * (DESIGN §7): dentro de un panel Filament `muni-ui.css` no se carga, la paleta
 * del panel vale distinto en 30 de 32 tokens, y esa hoja NO baja `--muni-dur` con
 * movimiento reducido. `panel-*` carga ÚNICAMENTE `muni-ui-filament.css` y el DOM
 * mínimo que esa hoja estiliza (`body.fi-body`, `main.fi-main`, `section.fi-section`).
 */
it('genera el banco de navegador en build/banco-confirmacion/, en las dos paletas y los dos temas', function () {
    $focus = alpineParaBanco('@alpinejs/focus');
    $alpine = alpineParaBanco('alpinejs');

    $piezas = '';
    foreach (piezasDelBanco() as $nombre => $blade) {
        $piezas .= '<section class="fi-section" data-pieza="'.$nombre.'">'.Blade::render($blade).'</section>';
    }

    $paginas = [
        'claro' => ['light', 'muni-ui.css', false],
        'oscuro' => ['dark', 'muni-ui.css', false],
        'panel-claro' => ['light', 'muni-ui-filament.css', true],
        'panel-oscuro' => ['dark', 'muni-ui-filament.css', true],
    ];

    $dir = __DIR__.'/../build/banco-confirmacion';
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    foreach ($paginas as $nombre => [$tema, $hoja, $panel]) {
        $css = file_get_contents(__DIR__.'/../resources/css/'.$hoja);
        $oscuro = $tema === 'dark';

        // El armazón no aporta ni un color: todo sale de los mismos tokens que lee
        // el componente. En el panel, `color:var(--muni-text)` repone lo que
        // Filament pone en el <body> y el paquete no.
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'h1{font-size:18px;margin:0 0 16px}'
            .'.fi-section{padding:16px;margin-bottom:16px;border:1px solid var(--muni-border);border-radius:var(--muni-radius-lg);background:var(--muni-surface)}'
            .'[x-cloak]{display:none!important}';

        $contenido = '<h1>Banco: &lt;x-muni::modal&gt; endurecido — '.$nombre.'</h1>'
            .'<input id="banco-primero" aria-label="Campo de referencia" style="max-width:240px;">'
            .$piezas;

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'.$contenido.'</main></div>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$contenido.'</main>';

        $html = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco — modal de confirmación — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo
            .($focus !== null ? '<script data-banco="alpine-focus">'.$focus.'</script>' : '')
            .($alpine !== null ? '<script data-banco="alpine-core">'.$alpine.'</script>' : '')
            .'</body></html>';

        file_put_contents($dir.'/'.$nombre.'.html', $html);

        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
        expect(str_contains($html, 'role="alertdialog"'))->toBeTrue('El banco '.$nombre.' no trae la variante endurecida.');
        expect(str_contains($html, 'data-banco="alpine-focus"') && str_contains($html, 'data-banco="alpine-core"'))->toBeTrue(
            'El banco salió sin Alpine o sin el plugin Focus: corre `npm install`; sin ellos no se puede medir el foco.'
        );
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDelBancoDeConfirmacion(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que un test de Blade no puede ver, medido en Chromium Y Firefox sobre el
 * banco: que el foco cae de verdad en Cancelar, que Tab no se escapa, que Enter
 * sobre Cancelar no ejecuta la acción, que el clic en el fondo NO cierra el
 * alertdialog, que Escape sí y devuelve el foco al disparador, que el evento de
 * navegador abre solo el modal con ese id, que nadie roba el foco al cargar la
 * página, que el outline de foco mide 3 px y que con movimiento reducido las
 * transiciones computan a 0 s también en `panel-*`. Se SALTA —no se finge—
 * cuando no está `.venv-a11y`, que en CI no se instala.
 */
it('en Chromium y Firefox: foco en Cancelar, trampa, Escape cancela, el fondo no cierra y el evento abre solo su id', function () {
    $python = pythonDelBancoDeConfirmacion();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/dialogo-de-confirmacion.py').' '
        .escapeshellarg(__DIR__.'/../build/banco-confirmacion').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador del modal de confirmación falló:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'transición(reduce)=0s'))))->toBe(8,
        "Las cuatro páginas × dos navegadores tienen que reportar la transición apagada con la preferencia:\n".implode("\n", $lineas));
});

it('apaga el fundido y el pop con movimiento reducido también dentro del panel, donde --muni-dur no baja a 0', function () {
    // Dentro de un panel Filament solo se carga muni-ui-filament.css, y esa hoja
    // deja --muni-dur en 160 ms bajo prefers-reduced-motion (DESIGN §7). Con
    // `var(--muni-dur)` solo no basta: hace falta la guardia propia, como en
    // command-palette y stat. Se lee el bloque de estilos SIN comentarios.
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../resources/views/components/modal.blade.php'));
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    expect((bool) preg_match('/@media\s*\(prefers-reduced-motion\s*:\s*reduce\)\s*\{([^}]*\{[^}]*\})+\s*\}/s', $fuente, $m))->toBeTrue(
        'El modal no trae su propia regla de prefers-reduced-motion: dentro del panel el fundido sigue en 160 ms.'
    );
    foreach (['.muni-fade', '.muni-pop'] as $clase) {
        expect((bool) preg_match('/'.preg_quote($clase, '/').'[^{]*\{[^}]*transition\s*:\s*none/s', $m[0]))->toBeTrue(
            "La regla de movimiento reducido del modal no apaga la transición de `{$clase}`."
        );
        // drawer y command-palette redefinen la misma clase en su propio bloque de
        // estilos SIN guardia; si van después en la página, a igual especificidad
        // ganan ellos. La clase repetida (0,2,0) gana sea cual sea el orden. Medido
        // en el banco con los dos vecinos detrás de los modales, sobre panel-*.
        expect(str_contains($m[0], $clase.$clase))->toBeTrue(
            "La guardia de movimiento reducido de `{$clase}` tiene la especificidad de una sola clase: "
            .'el bloque de drawer o command-palette, si se emite después, la pisa y el fundido vuelve a 160 ms en el panel.'
        );
    }
});

it('centra el panel con una clase, no con un display inline que x-show borra al mostrar', function () {
    // Defecto preexistente del modal, medido en el banco: el `display:flex` iba en
    // el style inline del elemento con x-show, y x-show al mostrar quita la
    // propiedad display inline entera (removeProperty), así que el contenedor
    // quedaba en block y el panel caía arriba a la izquierda (-459/-318 px del
    // centro en 1440×900). El centrado tiene que vivir en una clase del @once.
    $dialogo = etiquetaDelDialogo(renderConfirmacion());

    expect((bool) preg_match('/style="[^"]*display\s*:/', $dialogo))->toBeFalse(
        'El elemento con x-show lleva `display:` en su style inline: x-show lo borra al mostrar y el panel deja de centrarse.'
    );
    expect(str_contains($dialogo, 'class="muni-modal__capa"'))->toBeTrue('El elemento del diálogo no lleva la clase que lo centra.');

    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../resources/views/components/modal.blade.php'));

    expect((bool) preg_match('/\.muni-modal__capa\s*\{[^}]*display\s*:\s*flex[^}]*align-items\s*:\s*center[^}]*justify-content\s*:\s*center/s', $fuente))->toBeTrue(
        'La clase .muni-modal__capa no centra con flex.'
    );
});

it('no escribe un color literal: el velo sale de un token de identidad y el único respaldo es el de foco', function () {
    // DESIGN §10: ni un color literal en un componente. El velo era
    // `rgba(10,14,20,.55)` de siempre; después, el petróleo oscuro institucional
    // (`--muni-gob-petroleo-dark`) con opacidad propia, que en oscuro dejaba el
    // borde del panel a 2,77:1 contra el velo. Ahora sale de `--muni-scrim`,
    // declarado en cada rama de tema de las dos hojas, con ese petróleo de
    // respaldo y la misma opacidad propia (tests/BordeDeSuperficieTest.php mide
    // el contraste). El `#767676` es el tercer respaldo del outline de foco y
    // DESIGN §5 lo permite explícitamente.
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../resources/views/components/modal.blade.php'));
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    preg_match_all('/#[0-9a-f]{3,8}\b/i', $fuente, $hex);
    $literales = array_values(array_filter($hex[0], fn (string $c) => strtolower($c) !== '#767676'));

    expect($literales)->toBe([], 'modal escribe colores hex literales: '.implode(', ', $literales));
    expect((bool) preg_match('/\b(rgba?|hsla?|oklch|oklab|color-mix)\s*\(/i', $fuente))->toBeFalse(
        'modal escribe un color literal (rgb/hsl/oklch/color-mix): el velo tiene que salir de un token.'
    );
    expect((bool) preg_match('/\.muni-modal__veil\s*\{[^}]*background\s*:\s*var\(--muni-scrim,\s*var\(--muni-gob-petroleo-dark\)\)[^}]*opacity\s*:\s*\.\d+/s', $fuente))->toBeTrue(
        'El velo no se construye con --muni-scrim (respaldo: el token de identidad) y una opacidad propia.'
    );
});

it('no ensucia el x-data con texto del anfitrión: solo id y selector, y ambos por @js', function () {
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../resources/views/components/modal.blade.php'));
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    preg_match('/x-data="(.*?)"\s*\n/s', $fuente, $m);
    $xData = $m[1] ?? '';

    expect($xData)->not->toBe('');
    expect((bool) preg_match('/\{\{\s*\$/', $xData))->toBeFalse(
        'Hay un `{{ $… }}` dentro del x-data del modal: un apóstrofo en esa prop tumba Alpine. Todo lo que entra al x-data va por @js().'
    );
});
