<?php

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA AGENDA DE HORAS
|--------------------------------------------------------------------------
|
| `<x-muni::agenda-horas>` es la ficha `agenda-horas` de docs/GAP-ANALYSIS.md:
| el mes con la carga de cada día y, al elegir un día, las franjas horarias
| con cupo. La ficha salió SIN JUEZ, así que lo que se fija acá es el contrato
| que se pactó al implementarla, y cada punto es un defecto que ya se pisó en
| este paquete o que la propia ficha señala como riesgo:
|
| 1. LA REJILLA ES UNA REJILLA DE VERDAD. `calendar` dejó la rejilla APG
|    («role=grid, tabindex itinerante, flechas, RePág/AvPág») para «cuando
|    exista una pantalla que la consuma». Esta es esa pantalla. Sin
|    `role="grid"`, sin tabindex itinerante y sin flechas, el mes se recorre
|    con 31 tabulaciones y el funcionario del mesón no llega nunca.
| 2. LA CARGA SE OYE, NO SOLO SE VE. «12 de mayo, 3 de 8 cupos» es literal de
|    la ficha: la carga del día viaja en el nombre accesible del botón y en una
|    región viva, no solo como un número chico al lado.
| 3. EL ESTADO NUNCA ES SOLO COLOR (DESIGN §10). Día completo, franja tomada y
|    franja bloqueada llevan texto; el color es refuerzo.
| 4. NADA DEL ANFITRIÓN ENTRA EN UNA EXPRESIÓN DE ALPINE. Es el defecto que
|    `calendar` pagó con `new Date('{{ $min }}')` y `file-dropzone` con el
|    `x-text` de comillas simples: un apóstrofo descuadra la expresión y tumba
|    el Alpine de la PÁGINA entera, y un texto hostil se ejecuta. Todo lo que
|    viene de fuera viaja por atributos de datos y se lee con `$el.dataset`.
| 5. LA HORA NO SE INVENTA EN EL NAVEGADOR. La ficha marca el riesgo de zona
|    horaria (el gotcha de APP_TIMEZONE ausente ya mordió en el ecosistema):
|    la rejilla se calcula en PHP, con la zona del servidor, y el JS solo mueve
|    el foco por ÍNDICES de celda. No hay un solo `new Date` en el componente.
| 6. LA DOBLE RESERVA LA IMPIDE EL SERVIDOR. El componente no marca nada como
|    reservado: emite la intención y punto. Lo dice el README y lo comprueba
|    esta prueba.
| 7. MINIMIZACIÓN (Ley 21.719). Las franjas reciben un `detalle` que es una
|    CADENA ya redactada por el anfitrión. El componente no consulta
|    `request()`, `Auth`, `Gate` ni permisos.
| 8. LO INVÁLIDO FALLA CERRADO, como en `calendar`: un mes que no es `Y-m`, una
|    hora que no es `HH:MM` o una franja que no es un arreglo se descartan
|    enteras, nunca «mejor escapadas».
| 9. LA HORA ELEGIDA ES DEL DÍA ELEGIDO. El revisor la cazó en el navegador: se
|    elegía la 09:00 del 12, Enter sobre el 13 y «Reservar» salía con
|    {dia: 13, franja: 12T09:00}, el botón habilitado y el formulario con día y
|    hora incoherentes. Del lado del servidor se fija acá; del lado del cliente,
|    en tests/navegador/agenda-de-horas.py (el único sitio donde el JS CORRE).
| 10. NINGÚN CONTROL MUDO. Sin `mesUrl` ni `mesEvento` no hay ‹ ›: antes había
|    botones que solo emitían un evento que nadie escuchaba y dejaban el campo
|    `_mes` diciendo junio con la rejilla en mayo.
|
| Las pruebas de este archivo leen HTML y fuente: NO ejecutan el JS. El
| comportamiento del teclado lo cubre solo el script de navegador, que se salta
| sin `.venv-a11y`. Donde se grepea el fuente es para fijar un contrato que el
| navegador ya midió, no como sustituto de medirlo.
*/

function agendaFranjasDePrueba(): string
{
    return ':franjas="['
        .'\'2026-05-12\' => ['
        .'[\'inicio\' => \'09:00\', \'fin\' => \'09:30\', \'estado\' => \'libre\'],'
        .'[\'inicio\' => \'09:30\', \'fin\' => \'10:00\', \'estado\' => \'tomada\', \'detalle\' => \'Reservada\'],'
        .'[\'inicio\' => \'10:00\', \'fin\' => \'10:30\', \'estado\' => \'bloqueada\', \'detalle\' => \'Mantención del vehículo\'],'
        .'],'
        .'\'2026-05-13\' => ['
        .'[\'inicio\' => \'09:00\', \'fin\' => \'09:30\', \'estado\' => \'libre\'],'
        .'],'
        .']"';
}

function agendaHtml(string $atributos = ''): string
{
    return Blade::render('<x-muni::agenda-horas '.$atributos.' />');
}

/** La agenda completa que se usa en casi todas las comprobaciones. */
function agendaHtmlCompleta(): string
{
    return agendaHtml('name="cita" mes="2026-05" value="2026-05-12" label="Agenda de exámenes" '.agendaFranjasDePrueba());
}

function agendaFuente(): string
{
    return file_get_contents(__DIR__.'/../resources/views/components/agenda-horas.blade.php');
}

/** El fuente sin comentarios: lo que de verdad llega al navegador. */
function agendaFuenteSinComentarios(): string
{
    $fuente = preg_replace('/\{\{--.*?--\}\}/s', '', agendaFuente());

    return preg_replace('!/\*.*?\*/!s', '', (string) $fuente);
}

/** Los `value` de los radios que salen marcados del servidor. */
function agendaRadiosMarcados(string $html): array
{
    preg_match_all('/<input type="radio"[^>]*>/s', $html, $m);

    $marcados = [];

    foreach ($m[0] as $radio) {
        if (preg_match('/\schecked(\s|>|=)/', $radio) && preg_match('/value="([^"]*)"/', $radio, $v)) {
            $marcados[] = $v[1];
        }
    }

    return $marcados;
}

/** Solo el bloque de estilos del componente. */
function agendaCss(): string
{
    preg_match('/<style>(.*?)<\/style>/s', agendaFuente(), $m);

    return $m[1] ?? '';
}

it('dibuja el mes como una rejilla de verdad, con sus siete columnas', function () {
    $html = agendaHtmlCompleta();

    expect(str_contains($html, 'role="grid"'))->toBeTrue(
        'El mes no es una rejilla: sin role="grid" el lector no anuncia fila ni columna y las flechas no significan nada.'
    );
    expect(substr_count($html, 'role="columnheader"'))->toBe(7, 'Faltan cabeceras de columna: una por día de la semana.');
    expect(substr_count($html, 'data-celda="2026-05-'))->toBe(31, 'Mayo de 2026 tiene 31 días y la rejilla tiene que dibujarlos todos.');
    expect(substr_count($html, 'role="row"'))->toBeGreaterThanOrEqual(6, 'Las semanas del mes tienen que ser filas.');
});

it('deja UNA sola parada de tabulación en el mes: tabindex itinerante', function () {
    $html = agendaHtmlCompleta();

    expect(substr_count($html, 'tabindex="0"'))->toBe(1,
        'La rejilla tiene que tener exactamente un día con tabindex="0" (el elegido, o el primero del mes). '.
        'Con 31 paradas el funcionario del mesón no llega nunca a las franjas.'
    );
    expect(str_contains($html, 'tabindex="-1"'))->toBeTrue('Los demás días tienen que salir del orden de tabulación.');
    expect(str_contains(agendaFuenteSinComentarios(), ':tabindex="foco === $el.dataset.celda ? 0 : -1"'))->toBeTrue(
        'El tabindex itinerante tiene que seguir al foco, y el día se lee del dataset, nunca interpolado en la expresión.'
    );
});

it('las flechas, Inicio/Fin y RePág/AvPág mueven el foco por la rejilla', function () {
    $fuente = agendaFuenteSinComentarios();

    foreach (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown'] as $tecla) {
        expect(str_contains($fuente, $tecla))->toBeTrue("La rejilla no atiende {$tecla}, que la ficha exige.");
    }

    expect(str_contains($fuente, '@keydown="tecla($event)"'))->toBeTrue(
        'El teclado se atiende EN la rejilla, no en window: un manejador global se pisa con el de cualquier otra agenda de la pantalla.'
    );
    expect(str_contains($fuente, '.window'))->toBeFalse(
        'Ningún manejador del componente puede colgarse de window (Esc y las flechas van en el elemento).'
    );
});

it('cada día dice su carga en el nombre accesible y en la región viva', function () {
    $html = agendaHtmlCompleta();

    expect(str_contains($html, '12 de mayo de 2026, 1 de 3 cupos libres'))->toBeTrue(
        'El día tiene que anunciar su carga («12 de mayo, 3 de 8 cupos» en la ficha), no solo pintarla.'
    );
    expect(str_contains($html, 'sin horas publicadas'))->toBeTrue(
        'Un día sin franjas tiene que decirlo: «vacío» y «completo» son dos cosas distintas.'
    );
    expect(str_contains($html, 'aria-live="polite"'))->toBeTrue('Falta la región viva que anuncia el día enfocado.');
});

it('marca el día elegido con texto, no solo con color', function () {
    $html = agendaHtmlCompleta();

    expect(str_contains($html, '12 de mayo de 2026, 1 de 3 cupos libres, elegido'))->toBeTrue(
        'El día elegido se distingue solo por el fondo de acento: DESIGN §10 prohíbe comunicar estado solo con color.'
    );
    expect(str_contains($html, 'aria-selected="true"'))->toBeTrue('La celda elegida de una rejilla lleva aria-selected.');
});

it('un día completo lo dice con palabras', function () {
    $html = agendaHtml('mes="2026-05" :franjas="[\'2026-05-04\' => [[\'inicio\' => \'09:00\', \'estado\' => \'tomada\']]]"');

    expect(str_contains($html, 'completo'))->toBeTrue(
        'Un día sin cupo tiene que decir «completo»: el color rojo de la carga es refuerzo, no el mensaje.'
    );
});

it('las franjas son radios reales, con nombre de formulario y estado en texto', function () {
    $html = agendaHtmlCompleta();

    expect(substr_count($html, 'type="radio"'))->toBe(4, 'Las cuatro franjas de las dos jornadas tienen que ser radios reales.');
    expect(str_contains($html, 'name="cita"'))->toBeTrue('Los radios tienen que llevar el name del anfitrión y viajar en el submit.');
    expect(substr_count($html, 'disabled'))->toBeGreaterThanOrEqual(2, 'La franja tomada y la bloqueada no se pueden elegir.');

    foreach (['Libre', 'Tomada', 'Bloqueada'] as $palabra) {
        expect(str_contains($html, $palabra))->toBeTrue("El estado «{$palabra}» tiene que estar en texto, no solo en color.");
    }
});

it('solo muestra las franjas del día elegido, y las esconde con el atributo hidden', function () {
    $html = agendaHtmlCompleta();

    expect(str_contains($html, 'data-panel="2026-05-13" tabindex="-1" hidden'))->toBeTrue(
        'El día que no está elegido tiene que nacer con el atributo hidden: así sale del orden de tabulación Y del árbol de accesibilidad.'
    );
    expect(preg_match('/data-panel="2026-05-12" tabindex="-1" hidden/', $html))->toBe(0,
        'El día elegido tiene que nacer visible, incluso sin Alpine: es la única salida cuando no hay JS.'
    );
    expect(preg_match('/\.muni-ag__franjas\[hidden\][^{]*\{[^}]*display:\s*none/', agendaCss()))->toBe(1,
        'Sin `display:none` explícito, cualquier hoja del anfitrión que le dé display a un fieldset le gana a la regla '
        .'del navegador para `hidden` (especificidad mínima) y se ven todos los días a la vez.'
    );
});

it('Escape cancela la elección EN el elemento, nunca en window', function () {
    $fuente = agendaFuenteSinComentarios();

    expect(str_contains($fuente, '@keydown.escape="cancelar($event)"'))->toBeTrue(
        'Escape tiene que ir en el panel de franjas. `@keydown.escape.window` secuestra el Esc de toda la página.'
    );
    expect(str_contains($fuente, '@keydown.escape.window'))->toBeFalse('Prohibido: Escape global.');
});

it('al elegir un día el foco pasa a las franjas y vuelve al día con Escape', function () {
    $fuente = agendaFuenteSinComentarios();

    expect(str_contains($fuente, 'enfocarFranjas'))->toBeTrue('Falta el salto de foco del día a la lista de franjas que pide la ficha.');
    expect(str_contains($fuente, 'volverAlDia'))->toBeTrue('Escape tiene que devolver el foco al día de la rejilla, no dejarlo en el body.');
});

it('los métodos buscan desde la raíz, no desde el elemento que disparó el evento', function () {
    $fuente = agendaFuenteSinComentarios();

    // Medido en Chromium y Firefox: llamado desde el @click de un día, `this.$el`
    // es ESE botón. Buscar ahí los paneles no encontraba ninguno y Enter elegía el
    // día sin llevar nunca el foco a las franjas.
    foreach (['this.$el.querySelector', 'this.$el.dispatchEvent'] as $trampa) {
        expect(str_contains($fuente, $trampa))->toBeFalse(
            "«{$trampa}» dentro de un método busca desde el botón que disparó el evento, no desde la agenda: usa this.\$root."
        );
    }
});

it('nunca mete texto del anfitrión dentro de una expresión de Alpine', function () {
    $hostil = "O'Higgins ' + fetch('/x') + '";

    $html = Blade::render(
        '<x-muni::agenda-horas name="cita" mes="2026-05" value="2026-05-12" :label="$rotulo" :accion="$rotulo" '
        .':franjas="[\'2026-05-12\' => [[\'inicio\' => \'09:00\', \'estado\' => \'libre\', \'detalle\' => $rotulo]]]" />',
        ['rotulo' => $hostil]
    );

    expect(str_contains($html, "fetch('/x')"))->toBeFalse('El texto del anfitrión llegó SIN escapar al HTML.');
    expect(str_contains($html, '&#039;Higgins'))->toBeTrue('El apóstrofo tiene que llegar escapado, como contenido o como atributo.');

    // Y nada de lo que viene de fuera puede vivir dentro de una expresión de Alpine.
    foreach (['x-data', 'x-on:click', '@click', 'x-text', 'x-init'] as $trozo) {
        foreach (explode($trozo.'="', $html) as $i => $parte) {
            if ($i === 0) {
                continue;
            }
            $expresion = explode('"', $parte)[0];
            expect(str_contains($expresion, 'Higgins'))->toBeFalse(
                "El texto del anfitrión entró en la expresión de Alpine de «{$trozo}»: un apóstrofo ahí tumba el Alpine de la página entera."
            );
        }
    }
});

it('no calcula fechas en el navegador: la zona horaria es la del servidor', function () {
    $fuente = agendaFuenteSinComentarios();

    expect(str_contains($fuente, 'new Date'))->toBeFalse(
        'La ficha marca la zona horaria como riesgo: la rejilla se calcula en PHP y el JS solo mueve el foco por índices de celda.'
    );
    expect(str_contains($fuente, 'toLocaleDateString'))->toBeFalse('El nombre del día lo escribe PHP, no el navegador.');
});

it('descarta entero lo que no es válido, sin inventar', function () {
    $html = agendaHtml('mes="2026-13" :franjas="[\'no-es-fecha\' => [[\'inicio\' => \'25:00\']], \'2026-05-12\' => [\'texto suelto\', [\'inicio\' => \'09:00\']]]"');

    expect(str_contains($html, '2026-13'))->toBeFalse('Un mes imposible tiene que descartarse, no imprimirse.');
    expect(str_contains($html, '25:00'))->toBeFalse('Una hora imposible tiene que descartarse.');
    expect(substr_count($html, 'type="radio"'))->toBe(1, 'De las franjas inválidas no queda ninguna; de la válida, una.');
});

it('el mes se cambia con enlaces reales cuando el anfitrión los da', function () {
    $conEnlace = agendaHtml('mes="2026-05" :mes-url="fn (string $m) => \'/agenda?mes=\'.$m"');

    expect(str_contains($conEnlace, 'href="/agenda?mes=2026-04"'))->toBeTrue('Con `mesUrl` el mes anterior tiene que ser un enlace real, que funciona sin JS.');
    expect(str_contains($conEnlace, 'href="/agenda?mes=2026-06"'))->toBeTrue('Con `mesUrl` el mes siguiente tiene que ser un enlace real.');

    $conEvento = agendaHtml('mes="2026-05" mes-evento');
    expect(str_contains($conEvento, 'data-mes="2026-04"'))->toBeTrue('Con `mesEvento` el mes pedido viaja por un atributo de datos, nunca dentro de la expresión de Alpine.');
    expect(str_contains($conEvento, 'data-rotulo="junio de 2026"'))->toBeTrue('El botón tiene que traer el nombre del mes que pide, para decir «Cargando junio de 2026…».');
});

it('sin mesUrl ni mesEvento no dibuja botones de mes que no hacen nada', function () {
    $html = agendaHtml('name="cita" mes="2026-05"');

    expect(str_contains($html, 'class="muni-ag__nav"'))->toBeFalse(
        'Sin `mesUrl` ni `mesEvento` nadie atiende el cambio de mes: un ‹ › que solo emite un evento que nadie escucha es un control mudo.'
    );
    expect(str_contains($html, 'data-rotulo-mes="mayo de 2026"'))->toBeTrue(
        'RePág/AvPág tienen que poder decir «esta agenda muestra solo mayo de 2026» en vez de no hacer nada.'
    );
    expect(str_contains(agendaFuenteSinComentarios(), '$refs.campoMes'))->toBeFalse(
        'El campo `_mes` no se toca en el cliente: quedaba en 2026-06 con la rejilla mostrando mayo y el submit iba incoherente.'
    );
    expect(str_contains($html, 'name="cita_mes" value="2026-05"'))->toBeTrue('El campo `_mes` dice el mes que se ve.');
});

it('una hora de otro día nunca nace elegida', function () {
    // El anfitrión devuelve la hora vieja (del 12) con el día nuevo (el 13).
    $html = agendaHtml('name="cita" mes="2026-05" value="2026-05-13" franja="2026-05-12T09:00" '.agendaFranjasDePrueba());

    expect(agendaRadiosMarcados($html))->toBe([], 'La hora del 12 salió marcada con el 13 elegido: el formulario enviaría día y hora incoherentes.');
    expect(str_contains($html, 'data-elegida=""'))->toBeTrue('Alpine arrancaría con la hora de otro día.');
    expect(str_contains($html, 'aria-disabled="true"'))->toBeTrue('Sin una hora del día elegido la acción no puede nacer habilitada.');

    $buena = agendaHtml('name="cita" mes="2026-05" value="2026-05-13" franja="2026-05-13T09:00" '.agendaFranjasDePrueba());
    expect(agendaRadiosMarcados($buena))->toBe(['2026-05-13T09:00'], 'La hora libre del día elegido sí tiene que nacer marcada.');
    expect(str_contains($buena, 'data-elegida="2026-05-13T09:00"'))->toBeTrue();

    $tomada = agendaHtml('name="cita" mes="2026-05" value="2026-05-12" franja="2026-05-12T09:30" '.agendaFranjasDePrueba());
    expect(str_contains($tomada, 'data-elegida=""'))->toBeTrue('Una hora TOMADA no puede nacer elegida.');
});

it('un día elegido fuera del mes a la vista se descarta', function () {
    $html = agendaHtml('name="cita" mes="2026-06" value="2026-05-12"');

    expect(str_contains($html, 'name="cita_dia" value=""'))->toBeTrue('El campo `_dia` viajaría con un día que la rejilla no muestra.');
    expect(str_contains($html, 'aria-selected="true"'))->toBeFalse();
});

it('un día sin horas publicadas lo dice con su fecha, no «elige un día»', function () {
    $html = agendaHtml('name="cita" mes="2026-05" value="2026-05-20" '.agendaFranjasDePrueba());

    expect(str_contains($html, 'El miércoles 20 de mayo de 2026 no tiene horas publicadas.'))->toBeTrue(
        'Con el 20 elegido y sin franjas, el aviso decía «Elige un día del mes para ver sus horas.»: el día YA estaba elegido.'
    );
    expect(preg_match('/class="muni-ag__vacio"[^>]*\shidden[\s>]/', $html))->toBe(0, 'El aviso del día sin horas tiene que nacer visible.');
    expect(str_contains($html, 'data-fecha="miércoles 20 de mayo de 2026"'))->toBeTrue(
        'El cliente arma el mismo aviso con la fecha del botón, leída del dataset.'
    );

    $sinDia = agendaHtml('name="cita" mes="2026-05" '.agendaFranjasDePrueba());
    expect(str_contains($sinDia, 'Elige un día del mes para ver sus horas.'))->toBeTrue();

    // El aviso ya no es una parada de foco: un día sin horas deja el foco en el
    // día (medido en el navegador), así que no hay un párrafo del que no se sale.
    expect(preg_match('/class="muni-ag__vacio"[^>]*tabindex/', $html))->toBe(0,
        'El aviso no puede ser enfocable: el foco caía ahí y Esc no lo sacaba.'
    );
});

it('el error del anfitrión se dice en texto y con role="alert"', function () {
    $html = Blade::render('<x-muni::agenda-horas mes="2026-05" :error="$e" />', ['e' => "No se pudo leer la agenda de O'Higgins."]);

    expect(preg_match('/<p class="muni-ag__error" role="alert">.*O&#039;Higgins/s', $html))->toBe(1,
        'El error tiene que llegar escapado, en un role="alert", no solo en color.'
    );
    expect(str_contains(agendaHtml('mes="2026-05" error="  "'), 'role="alert"'))->toBeFalse('Un error en blanco no es un error.');
});

it('la acción dice qué operación pide: reservar, reagendar o bloquear', function () {
    expect(str_contains(agendaHtml('mes="2026-05" operacion="reagendar"'), 'data-operacion="reagendar"'))->toBeTrue();
    expect(str_contains(agendaHtml('mes="2026-05" operacion="borrar-todo"'), 'data-operacion="reservar"'))->toBeTrue(
        'Una operación desconocida falla cerrado a «reservar», nunca se publica tal cual.'
    );
    expect(str_contains(agendaFuenteSinComentarios(), 'operacion: this.$root.dataset.operacion'))->toBeTrue(
        'El evento muni-agenda:accion tiene que llevar la operación, leída del dataset.'
    );
});

it('el ejemplo de Livewire del README compila tal cual', function () {
    $html = Blade::render(
        '<x-muni::agenda-horas name="hora" mes="2026-05" mes-evento wire:model.live="hora" '
        .'x-on:muni-agenda:mes="$wire.set(\'mes\', $event.detail.mes)" x-on:muni-agenda:dia="$wire.set(\'dia\', $event.detail.dia)" />'
    );

    expect(str_contains($html, 'x-on:muni-agenda:mes="$wire.set(&#039;mes&#039;, $event.detail.mes)"')
        || str_contains($html, 'x-on:muni-agenda:mes="$wire.set(\'mes\', $event.detail.mes)"'))->toBeTrue(
            "El oyente del mes no llegó a la raíz del componente:\n".$html
        );
    expect(str_contains($html, 'wire:model.live="hora"'))->toBeTrue('wire:model tiene que llegar a la raíz, donde está x-modelable.');
});

it('lo que se ve y lo que no va declarado, para sobrevivir al morph de Livewire', function () {
    $fuente = agendaFuenteSinComentarios();

    expect(str_contains($fuente, ':hidden="dia !== $el.dataset.panel"'))->toBeTrue(
        'Los paneles se muestran con un enlace declarado: un `p.hidden = …` imperativo lo repinta el morph con lo que diga el servidor.'
    );
    expect(preg_match('/\.hidden\s*=/', $fuente))->toBe(0, 'Quedó un `.hidden =` imperativo.');
    expect(str_contains($fuente, 'MutationObserver'))->toBeTrue('Sin releer data-mes/data-dia tras un repintado, la rejilla nueva queda con el estado del mes anterior.');
    expect(str_contains($fuente, '@focus="foco = $el.dataset.celda"'))->toBeTrue(
        'El foco en un día NO anuncia: el aria-label ya dice la carga y el lector la leía dos veces.'
    );
});

it('no consulta al anfitrión ni genera ids que cambien en cada render', function () {
    $fuente = agendaFuente();

    foreach (['uniqid(', 'request(', 'Auth::', 'auth()', 'Gate::', 'can('] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El paquete no consulta al anfitrión ni inventa ids: «{$prohibido}» no puede aparecer."
        );
    }

    $uno = agendaHtml('name="cita" mes="2026-05"');
    $dos = agendaHtml('name="cita" mes="2026-05"');
    expect($uno)->toBe($dos, 'Dos renders del mismo componente tienen que dar el mismo HTML: si no, el diffing de Livewire reemplaza nodos que no cambiaron.');
});

it('no marca nada como reservado: la verdad la tiene el servidor', function () {
    $fuente = agendaFuenteSinComentarios();

    expect(str_contains($fuente, 'muni-agenda:accion'))->toBeTrue(
        'El componente emite la intención de reservar; quien decide si hay cupo es el servidor (riesgo de doble reserva de la ficha).'
    );
    foreach (['fetch(', 'XMLHttpRequest', 'navigator.sendBeacon'] as $salida) {
        expect(str_contains($fuente, $salida))->toBeFalse(
            "El componente no habla con el servidor por su cuenta («{$salida}»): emite el evento y el anfitrión decide."
        );
    }
});

it('funciona en Livewire 3 y en Livewire 4', function () {
    $fuente = agendaFuente();

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($fuente, $prohibida))->toBeFalse("«{$prohibida}» solo existe en un major de Livewire y este paquete vive en los dos.");
    }

    expect(str_contains($fuente, 'x-modelable="franja"'))->toBeTrue(
        'Sin x-modelable, `wire:model` sobre el componente no ata nada y la hora elegida se pierde en cada respuesta del servidor.'
    );
});

it('no escribe un solo color literal', function () {
    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', agendaCss(), $m);

    expect(array_values(array_unique(array_diff($m[0], ['#767676']))))->toBe([],
        'Cero colores literales: el único permitido es el respaldo #767676 del foco (DESIGN §1 y §5).'
    );
});

it('usa solo tokens declarados en las DOS hojas', function () {
    preg_match_all('/var\(\s*(--muni-[a-z0-9-]+)/i', agendaFuenteSinComentarios(), $m);

    $faltan = [];

    foreach (array_unique($m[1]) as $token) {
        if (! str_contains(cssMuniUi(), $token.':') || ! str_contains(cssMuniUiFilament(), $token.':')) {
            $faltan[] = $token;
        }
    }

    expect($faltan)->toBe([],
        'Estos tokens no están en las dos hojas: '.implode(' ', $faltan).'. Dentro de un panel Filament solo se carga '.
        'muni-ui-filament.css, y un var() que no existe descarta la declaración entera sin un error en consola (DESIGN §7).'
    );
});

it('el foco se ve con outline y el blanco táctil llega a 44px', function () {
    $css = agendaCss();

    preg_match_all('/([^{}]*:focus[^{}]*)\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    expect($reglas)->not->toBe([], 'El componente no declara ninguna regla de foco.');

    foreach ($reglas as [, $selector, $cuerpo]) {
        expect(str_contains($cuerpo, 'outline:3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeTrue(
            'La regla de foco «'.trim($selector).'» no usa el outline canónico: dentro de Filament la box-shadow se pierde (DESIGN §5).'
        );
    }

    expect(substr_count($css, 'min-height:44px'))->toBeGreaterThanOrEqual(3,
        'Día, franja y acción tienen que llegar a 44 px: es un mesón municipal y hay pantallas táctiles.'
    );
});

it('el movimiento sale del token y se apaga solo', function () {
    $css = agendaCss();

    preg_match_all('/transition:([^;}]+)/', $css, $m);

    foreach ($m[1] as $valor) {
        if (trim($valor) === 'none') {
            continue;
        }
        expect(str_contains($valor, 'var(--muni-dur)'))->toBeTrue(
            'La transición «'.trim($valor).'» usa una duración fija y se salta prefers-reduced-motion (DESIGN §6).'
        );
    }

    expect(str_contains($css, '@media (prefers-reduced-motion: reduce)'))->toBeTrue(
        'Falta la guardia local: la hoja del panel —la única que se carga dentro de Filament— no baja --muni-dur a 0 ms.'
    );
});

it('lleva su propio CSS dentro del componente', function () {
    $fuente = agendaFuente();

    expect(str_contains($fuente, '@once'))->toBeTrue('El CSS del componente viaja dentro de su bloque único (DESIGN §7).');
    expect(str_contains(agendaCss(), '.muni-ag__hora'))->toBeTrue(
        'La hora en mono tabular tiene que declararse acá: una clase que solo existe en muni-ui.css se queda sin estilo dentro del panel.'
    );
});

/*
| Contraste de los pares que usa el componente, en las dos hojas y en los dos
| temas. Se mide con los tokens REALES, no con una captura: los dos defectos que
| costaron caro en este paquete —la etiqueta de KPI en 1,22:1 y la pestaña activa
| en 1,52:1— pasaron todas las revisiones visuales (DESIGN §4).
*/
dataset('temas de la agenda', function () {
    $muni = cssMuniUi();
    $panel = cssMuniUiFilament();

    return [
        'muni-ui.css claro' => [$muni, bloqueTrasAncla($muni, 'Valores LIGHT (default)')],
        'muni-ui.css oscuro' => [$muni, bloqueTrasAncla($muni, 'Regla 2: activadores EXPLÍCITOS de dark')],
        'muni-ui-filament.css claro' => [$panel, bloqueTrasAncla($panel, ':root{')],
        'muni-ui-filament.css oscuro' => [$panel, bloqueTrasAncla($panel, 'En oscuro mandan los tonos institucionales')],
    ];
});

it('todo lo que pinta la agenda pasa 4,5:1', function (string $hoja, string $bloque) {
    $pares = [
        ['text', 'surface', 'el título del mes sobre la tarjeta'],
        ['text', 'surface-2', 'el número del día sobre la celda'],
        ['hint', 'surface-2', 'la carga «3/8» de la celda'],
        ['on-accent', 'accent', 'el día elegido'],
        ['on-accent', 'accent-strong', 'el día elegido bajo el mouse'],
        ['ok-fg', 'ok-bg', 'la franja libre'],
        ['warn-fg', 'warn-bg', 'la franja tomada'],
        ['danger-fg', 'danger-bg', 'la franja bloqueada'],
        ['muted', 'surface-2', 'la acción sin hora elegida'],
        ['hint', 'surface', 'la ayuda del pie'],
        ['danger-fg', 'danger-bg', 'el error de la agenda'],
        ['muted', 'surface', '«Cargando junio de 2026…»'],
    ];

    foreach ($pares as [$frente, $fondo, $que]) {
        $a = tokenColor($bloque, $hoja, $frente);
        $b = tokenColor($bloque, $hoja, $fondo);

        expect($a)->not->toBeNull("No se pudo resolver --muni-{$frente}.");
        expect($b)->not->toBeNull("No se pudo resolver --muni-{$fondo}.");

        $ratio = ratioContraste($a, $b);
        expect($ratio)->toBeGreaterThanOrEqual(4.5,
            "{$que}: --muni-{$frente} sobre --muni-{$fondo} da ".number_format($ratio, 2).':1, bajo el 4,5:1 de WCAG 1.4.3.'
        );
    }
})->with('temas de la agenda');

it('no arrastra rastros de depuración', function () {
    foreach (['console.log', 'dd(', 'dump(', 'var_dump'] as $rastro) {
        expect(str_contains(agendaFuente(), $rastro))->toBeFalse("Quedó «{$rastro}» en el componente.");
    }
});

// ---------------------------------------------------------------------------
// Verificación en navegador
// ---------------------------------------------------------------------------

/*
 * El banco. Se genera acá y no en un script suelto porque este es el único sitio
 * del paquete con Blade arrancado (mismo criterio que `GeneraVitrinaTest` y
 * `PantallaDeResultadoTest`). Sale a `build/agenda-horas/`, ignorado por git.
 *
 * Cinco páginas, y cada una existe por algo que ninguna prueba de texto alcanza:
 *
 * · `claro` y `oscuro` — la agenda con Alpine: el recorrido REAL del teclado por
 *   la rejilla con `document.activeElement`, el salto del día a las franjas, el
 *   Esc que vuelve, el outline computado y el área táctil.
 * · `panel-claro` y `panel-oscuro` — la MISMA agenda cargando ÚNICAMENTE
 *   `muni-ui-filament.css` (DESIGN §7): dentro de un panel `muni-ui.css` no se
 *   carga, y es ahí donde una clase declarada fuera del bloque único —`.muni-num`
 *   entre ellas— se queda sin estilo y sin un solo error en consola.
 * · `sin-alpine` — la misma agenda SIN el script: el día que eligió el servidor
 *   tiene que verse igual, con sus radios operables, porque el formulario sigue
 *   siendo un formulario.
 */
function agendaAlpineDelBanco(): ?string
{
    $ruta = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    return is_file($ruta) ? (string) file_get_contents($ruta) : null;
}

/**
 * La agenda del banco. `$modo` elige cómo se cambia de mes, que es lo que el
 * revisor encontró mudo: `evento` (el anfitrión escucha `muni-agenda:mes`),
 * `enlaces` (`mesUrl`, navegación real) o `nada` (sin ‹ ›).
 *
 * Datos del mesón de Licencias, con los casos que rompieron: el 12 con una libre,
 * una tomada con un detalle LARGO sin espacios (datos extremos a 320 px) y una
 * bloqueada; el 13 con la 09:00 tomada y la 09:30 libre; el 14 completo; y el 20
 * SIN franjas, que llevaba el foco a un aviso con el texto equivocado.
 */
function agendaDelBanco(string $modo = 'evento', ?string $error = null): string
{
    $extra = match ($modo) {
        'evento' => 'mes-evento ',
        'enlaces' => ':mes-url="fn (string $m) => \'enlaces.html?mes=\'.$m" ',
        default => '',
    };

    return Blade::render(
        '<x-muni::agenda-horas name="hora_examen" mes="2026-05" value="2026-05-12" '.$extra
        .'label="Agenda de exámenes de licencia de conducir" accion="Reservar la hora" :error="$error" '
        .':franjas="['
        ."'2026-05-12' => ["
        ."['inicio' => '09:00', 'fin' => '09:30', 'estado' => 'libre'],"
        ."['inicio' => '09:30', 'fin' => '10:00', 'estado' => 'tomada', 'detalle' => 'Reservada por otra persona · Expediente LIC-2026-004871-DIRECCIONDETRANSITOYTRANSPORTEPUBLICO'],"
        ."['inicio' => '10:00', 'fin' => '10:30', 'estado' => 'libre'],"
        ."['inicio' => '10:30', 'fin' => '11:00', 'estado' => 'bloqueada', 'detalle' => 'Mantención del vehículo de examen'],"
        .'],'
        ."'2026-05-13' => ["
        ."['inicio' => '09:00', 'fin' => '09:30', 'estado' => 'tomada'],"
        ."['inicio' => '09:30', 'fin' => '10:00', 'estado' => 'libre'],"
        .'],'
        ."'2026-05-14' => ["
        ."['inicio' => '09:00', 'fin' => '09:30', 'estado' => 'tomada'],"
        .'],'
        .']" />',
        ['error' => $error]
    );
}

it('genera el banco de navegador en build/agenda-horas/', function () {
    $dir = __DIR__.'/../build/agenda-horas';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $alpine = agendaAlpineDelBanco();

    expect($alpine)->not->toBeNull(
        'No está node_modules/alpinejs: corre `npm install`. Sin Alpine el banco no puede medir el recorrido '
        .'del teclado por la rejilla, que es justo lo que ninguna prueba de texto alcanza.'
    );

    // Las cuatro principales llevan además el error del anfitrión, para medir su
    // contraste compuesto en los dos temas y en las dos hojas.
    $error = 'No se pudo leer la agenda de la sala de exámenes. Intenta de nuevo en unos minutos.';

    $paginas = [
        'claro' => ['light', cssMuniUi(), false, true, agendaDelBanco('evento', $error)],
        'oscuro' => ['dark', cssMuniUi(), false, true, agendaDelBanco('evento', $error)],
        'panel-claro' => ['light', cssMuniUiFilament(), true, true, agendaDelBanco('evento', $error)],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true, true, agendaDelBanco('evento', $error)],
        'sin-alpine' => ['light', cssMuniUi(), false, false, agendaDelBanco('evento')],
        'enlaces' => ['light', cssMuniUi(), false, true, agendaDelBanco('enlaces')],
        'sin-nav' => ['light', cssMuniUi(), false, true, agendaDelBanco('nada')],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel, $conAlpine, $agenda]) {
        $oscuro = $tema === 'dark';
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        // El armazón no aporta ni un color: fondo y texto salen de los mismos
        // tokens que lee el componente.
        $armazon = 'body{margin:0;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'.relleno{padding:24px;max-width:720px}';

        $script = $conAlpine && $alpine !== null
            ? '<script data-banco="alpine-core">'.$alpine.'</script>'
            : '';

        // Dentro de un <form>: lo que el revisor midió es lo que el FORMULARIO envía.
        $formulario = '<form id="banco-form" action="#" onsubmit="return false">'.$agenda.'</form>';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<section class="fi-section"><div class="relleno">'.$formulario.'</div></section></main></div>'.$script.'</body>'
            : '<body><main id="muni-contenido" tabindex="-1"><div class="relleno">'.$formulario.'</div></main>'.$script.'</body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco agenda-horas — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);

        expect(str_contains($pagina, 'class="muni-ag"'))->toBeTrue('El banco '.$nombre.' salió sin la agenda.');
        expect(str_contains($pagina, 'data-banco="alpine-core"'))->toBe($conAlpine,
            'El banco '.$nombre.' no trae el Alpine que le corresponde.'
        );
    }

    expect(str_contains((string) file_get_contents($dir.'/claro.html'), '.muni-ag__dia--on'))->toBeTrue(
        'El banco salió sin el bloque de estilos del componente: nada de lo que se mida en el navegador sería suyo.'
    );
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function agendaPythonDeLaReja(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que ningún `str_contains` sobre el fuente puede ver, medido en Chromium y
 * Firefox: que las flechas MUEVAN el foco de verdad, que el tabindex itinerante
 * lo siga, que Enter salte a las franjas y Esc vuelva, que el outline compute
 * 3 px sólidos y que nada desborde a 320 px. Leer el texto del CSS «ya engañó
 * una vez en `stat`: la regla pasó el test de texto y en el navegador el defecto
 * seguía vivo» (tests/navegador/solapas-de-ruta.py).
 */
it('en Chromium y Firefox: el teclado de la rejilla, el salto a las franjas y el foco visible', function () {
    $python = agendaPythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/agenda-de-horas.py').' '
        .escapeshellarg(__DIR__.'/../build/agenda-horas').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de la agenda falló:\n".implode("\n", $lineas));

    // Las cuatro páginas con Alpine × dos navegadores tienen que reportar el
    // recorrido completo. Si el banco se quedara sin páginas, el script diría
    // «todo pasa» sobre nada.
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'teclado=8/8'))))->toBe(8,
        "Las ocho pasadas tienen que mover el foco con las ocho teclas de la ficha:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'salto=ok'))))->toBe(8,
        "Enter tiene que llevar el foco a la primera hora libre y Esc devolverlo al día:\n".implode("\n", $lineas));

    // Los defectos que el revisor midió en el navegador, uno por línea y por página.
    foreach ([
        'cambio-de-dia=ok' => 'cambiar de día tiene que soltar la hora del día anterior (formulario y evento coherentes)',
        'dia-sin-horas=ok' => 'un día sin horas deja el foco en el día y dice que no tiene horas',
        'modelo-externo=ok' => 'una hora de otro día fijada desde wire:model mueve el día a esa hora',
    ] as $linea => $que) {
        expect(count(array_filter($lineas, fn (string $l) => str_contains($l, $linea))))->toBe(8, "{$que}:\n".implode("\n", $lineas));
    }
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'enlaces=ok'))))->toBe(2,
        "Con mesUrl las flechas del borde no recargan y RePág va al enlace:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'sin-nav=ok'))))->toBe(2,
        "Sin forma de cambiar de mes no hay botones mudos y RePág lo dice:\n".implode("\n", $lineas));
});

/*
 * Con Livewire REAL (4.x del vendor): el montaje que documenta el README —
 * `wire:model` para la hora, `muni-agenda:dia` y `muni-agenda:mes` para el día y el
 * mes— sobrevive al morph. El revisor refutó que `_mes` sirviera para wire:model
 * (era falso) y el banco estático no puede ver un repintado del servidor. Sin el
 * MutationObserver, junio llegaba sin parada de tabulación y con «Cargando» pegado;
 * con los paneles mostrados a mano, el morph devolvía el panel del servidor y la
 * hora quedaba marcada en un panel oculto. Las dos cosas se midieron revirtiendo
 * el arreglo en una copia.
 */
it('con Livewire real: la hora, el día y el mes sobreviven al morph', function () {
    $python = agendaPythonDeLaReja();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: el banco con Livewire real se salta, no se da por hecho.');
    }

    if (! class_exists(Livewire::class)) {
        $this->markTestSkipped('Sin livewire/livewire en vendor (llega con filament/filament, dependencia de desarrollo) no hay banco con Livewire real.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/agenda-de-horas-livewire.py').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La agenda falló con Livewire real:\n".implode("\n", $lineas));

    foreach (['hora por wire:model.live: ok', 'cambio de día sincronizado: ok', 'cambio de mes por muni-agenda:mes: ok', 'día del mes nuevo: ok', 'morph sin sincronizar el día: ok'] as $paso) {
        expect(count(array_filter($lineas, fn (string $l) => str_contains($l, $paso))))->toBe(2,
            "«{$paso}» tiene que pasar en Chromium y Firefox:\n".implode("\n", $lineas));
    }
});
