<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA FECHA MÍNIMA DEL CALENDARIO
|--------------------------------------------------------------------------
|
| `<x-muni::calendar>` recibe del anfitrión una fecha mínima (`min`) y, desde
| esta tanda, una selección inicial (`value`). Las dos son texto que viene de
| fuera y las dos se usaban —o se iban a usar— DENTRO de la expresión de
| `x-data`, que Alpine evalúa como código.
|
| 1. EL SUMIDERO DE `$min`. Se interpolaba como `new Date('{{ $min }}')` dentro
|    de la cadena de `x-data`. El juez de docs/GAP-ANALYSIS.md lo dejó claro:
|    el escape de Blade SÍ funciona para una fecha normal —el navegador
|    decodifica `&#039;` a `'` y `new Date('2026-09-05')` evalúa bien—, así que
|    el defecto no es de renderizado sino de SEGURIDAD: un valor con
|    `') || fetch(...) || ('` cierra la cadena y se ejecuta. La corrección
|    exigida: la fecha viaja por un atributo de datos escapado
|    (`data-min="…"`, leído con `$el.dataset.min`) y se valida en PHP con
|    `Carbon::hasFormat('Y-m-d')` fallando cerrado. Acá se comprueba que un
|    `min` hostil no llega a NINGUNA expresión de Alpine ni al HTML.
| 2. `hasFormat` NO basta solo: `2026-02-30` casa con el patrón y PHP lo corre
|    a marzo. Se exige además que la fecha exista, y si no, se descarta.
| 3. `disabled(d)` mutaba `this.min` con `setHours(0,0,0,0)` —que devuelve un
|    timestamp, no un Date— y comparaba dos veces: la primera evaluación
|    deshabilitaba el día del mínimo y la segunda lo habilitaba. El mínimo se
|    normaliza UNA sola vez al iniciar.
| 4. Los días que el mínimo descarta se pintaban y enfocaban igual que los
|    válidos: clic y no pasaba nada, sin `aria-disabled` ni distinción visual
|    (WCAG 2.2 AA 3.3.2 y 1.4.1). Ahora llevan `aria-disabled` —no `disabled`:
|    tienen que seguir siendo alcanzables y anunciables— y una marca que no es
|    solo color.
| 5. LOS HUECOS NO OCUPABAN CELDA. Los días previos al 1 eran iteraciones de
|    `x-for` cuyo único hijo era `<template x-if="c">` con `c = null`: Alpine
|    deja en el DOM un `<template>` (display:none) que no es un ítem de la
|    rejilla CSS, así que el día 1 de CUALQUIER mes caía bajo «L». Medido en
|    Chromium: septiembre de 2026 arrancaba el martes 1 bajo la columna del
|    lunes y el aria-label («martes 1…») contradecía la columna visual en
|    todos los días del mes. Los huecos son ahora elementos reales.
| 6. EL ELEGIDO PERDÍA EL TEXTO BAJO EL MOUSE. `.muni-cal__day:hover` (0,2,0)
|    ganaba a `.muni-cal__day--on` (0,1,0): el fondo pasaba a `--muni-surface-2`
|    y el color seguía en `--muni-on-accent`. Medido: 1,08:1 en claro y 1,15:1
|    en oscuro, en las dos hojas (WCAG 1.4.3). La regla `--on:hover` es la
|    última de las `:hover` y el contraste se calcula acá con los tokens
|    REALES de `muni-ui.css` y `muni-ui-filament.css`, en claro y en oscuro.
| 7. `wire:model` ERA DE UNA SOLA VÍA. Elegir un día escribía en el modelo
|    del anfitrión, pero cuando el servidor cambiaba la propiedad (resetear
|    tras guardar, cargar otra cita) la selección interna no se movía: el
|    morph solo toca el atributo `value` y `init()` no se re-ejecuta. La
|    selección vive ahora en una propiedad `valor` entrelazada con
|    `x-modelable`, que es el patrón que documenta Livewire para controles
|    Alpine con `wire:model`.
|
| Lo que NO cubre esta prueba, a propósito: la rejilla APG (role=grid, tabindex
| itinerante, flechas, RePág/AvPág, Inicio/Fin). El juez la dejó condicionada a
| que exista una pantalla que la consuma, y hoy no existe.
|
| Se prueba sobre el HTML renderizado y sobre el bloque `<style>` del propio
| componente, con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra cadena a buscar, no un mensaje. Lo que un
| test de Blade no puede ver —qué columna ocupa el día 1, qué color computa el
| elegido bajo el mouse, si el modelo externo mueve la selección— lo mide
| Playwright sobre el banco que genera la última prueba en `build/calendar/`.
*/

/** El `calendar.blade.php` crudo, sin comentarios CSS ni de Blade. */
function fuenteCalendario(): string
{
    return (string) preg_replace(
        ['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'],
        '',
        file_get_contents(__DIR__.'/../resources/views/components/calendar.blade.php')
    );
}

/** El contenido de los bloques `<style>` del componente, sin comentarios. */
function cssCalendario(): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteCalendario(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/**
 * Los valores de todos los atributos de Alpine del HTML dado, ya decodificados
 * como los ve el navegador (`x-…`, `@…`, `:…`).
 *
 * @return array<int, string>
 */
function expresionesAlpineCalendario(string $html): array
{
    preg_match_all('/\s(?:x-[a-z0-9.:-]+|@[a-z0-9.-]+|:[a-z0-9-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

/** El valor del atributo `x-data` de la raíz, decodificado. */
function xDataCalendario(string $html): string
{
    preg_match('/\sx-data="([^"]*)"/', $html, $m);

    return html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5);
}

/**
 * La etiqueta de apertura de la raíz con el `x-data` vaciado, para poder mirar
 * sus OTROS atributos: la expresión de `x-data` lleva `>` (`this.min > hoy`) y
 * un `[^>]*` a secas se cortaría ahí.
 */
function raizCalendario(string $html): string
{
    $sinData = (string) preg_replace('/\sx-data="[^"]*"/', ' x-data=""', $html);

    preg_match('/<div\b[^>]*\sx-data=""[^>]*>/', $sinData, $m);

    return $m[0] ?? '';
}

/** El interior de la rejilla (`.muni-cal__grid`) tal como sale del render. */
function rejillaCalendario(string $html): string
{
    preg_match('#<div class="muni-cal__grid">(.*?)</div>#s', $html, $m);

    return $m[1] ?? '';
}

/**
 * Los cuatro bloques de tokens que un día del calendario puede leer: cada hoja
 * en claro y en oscuro. Las anclas son las mismas que usa
 * `ContrasteTokensTest`; el tema del panel se mide aparte porque DENTRO de un
 * panel Filament `muni-ui.css` no se carga (DESIGN §7) y sus tokens valen otra
 * cosa.
 *
 * @return array<string, array{0: string, 1: string}> nombre => [hoja, bloque]
 */
function bloquesDeTokensCalendario(): array
{
    $muni = cssMuniUi();
    $panel = cssMuniUiFilament();

    return [
        'muni-ui.css claro' => [$muni, bloqueTrasAncla($muni, 'Valores LIGHT (default)')],
        'muni-ui.css oscuro' => [$muni, bloqueTrasAncla($muni, 'Regla 2: activadores EXPLÍCITOS de dark')],
        'muni-ui-filament.css claro' => [$panel, bloqueTrasAncla($panel, ':root{')],
        'muni-ui-filament.css oscuro' => [$panel, bloqueTrasAncla($panel, 'En oscuro mandan los tonos institucionales')],
    ];
}

it('un min hostil no llega a ninguna expresión de Alpine ni al HTML', function () {
    // El caso real del juez: una fecha que cierra la cadena de JS y ejecuta
    // lo que venga después. Con el escape de Blade el navegador la decodifica
    // de vuelta y Alpine la evalúa igual.
    $hostil = "2026-09-05') || fetch('https://x.example/') || ('";

    $html = Blade::render('<x-muni::calendar :min="$min" />', ['min' => $hostil]);

    $culpables = array_values(array_filter(
        expresionesAlpineCalendario($html),
        fn (string $expr) => str_contains($expr, 'fetch(')
    ));

    expect($culpables)->toBe([], sprintf(
        'El mínimo del anfitrión aparece dentro de una expresión de Alpine, que es '.
        'código evaluado: %s',
        implode(' | ', $culpables)
    ));

    // Fallo CERRADO: no se escapa mejor, se descarta. Nada del valor llega al
    // HTML, ni siquiera al atributo de datos.
    expect(str_contains($html, 'fetch('))->toBeFalse(
        'Un mínimo que no es una fecha `Y-m-d` llegó al HTML: la validación no falla cerrado.'
    );
    expect(str_contains($html, 'data-min='))->toBeFalse(
        'Con un mínimo inválido se emite `data-min` igual: la validación no falla cerrado.'
    );
});

it('el mínimo viaja por un atributo de datos escapado, nunca dentro de x-data', function () {
    $html = Blade::render('<x-muni::calendar min="2026-09-05" />');

    expect((bool) preg_match('/\sdata-min="2026-09-05"/', $html))->toBeTrue(
        'El mínimo válido no sale como `data-min="2026-09-05"` en la raíz del componente.'
    );

    $xData = xDataCalendario($html);

    expect(str_contains($xData, '2026-09-05'))->toBeFalse(
        'La fecha sigue interpolada dentro de la expresión de `x-data`: ese es el sumidero.'
    );

    // La forma del sumidero era `new Date('…')`: una cadena literal de JS armada
    // en PHP. No puede volver bajo ninguna prop.
    expect((bool) preg_match('/new Date\(\s*[\'"]/', $xData))->toBeFalse(
        'La expresión de `x-data` construye un Date desde una cadena literal: es la '.
        'forma exacta del sumidero que se cerró.'
    );

    expect(str_contains($xData, '$el.dataset.min'))->toBeTrue(
        'Alpine no lee el mínimo desde `$el.dataset.min`, que es el único canal seguro.'
    );
});

it('una fecha que no existe se descarta en vez de correrse al mes siguiente', function () {
    // `Carbon::hasFormat('2026-02-30', 'Y-m-d')` da true: el patrón calza. Pero
    // PHP y JS la corren al 2 de marzo, y el funcionario vería un mínimo que
    // nadie configuró. Se exige que la fecha exista de verdad.
    $inexistente = Blade::render('<x-muni::calendar min="2026-02-30" />');

    expect(str_contains($inexistente, 'data-min='))->toBeFalse(
        'El 30 de febrero pasó como mínimo: `hasFormat` solo mira el patrón, no que el día exista.'
    );

    // Y el formato es estricto: `2026-9-5` no es `Y-m-d`.
    $laxa = Blade::render('<x-muni::calendar min="2026-9-5" />');

    expect(str_contains($laxa, 'data-min='))->toBeFalse(
        'Un `2026-9-5` sin ceros pasó como mínimo: el formato tiene que ser exactamente `Y-m-d`.'
    );

    // Control positivo, para que esta prueba no pase «sola» con un componente
    // que nunca emita `data-min`: el último día real de febrero sí pasa.
    $real = Blade::render('<x-muni::calendar min="2026-02-28" />');

    expect(str_contains($real, 'data-min="2026-02-28"'))->toBeTrue(
        'El 28 de febrero, que existe, fue rechazado: la validación es más estricta de la cuenta.'
    );
});

it('acepta un DateTimeInterface como mínimo y lo formatea en PHP', function () {
    // El anfitrión suele tener un Carbon a mano (`now()`, `$cita->fecha`):
    // obligarlo a formatear a mano es invitar a que pase la hora o un
    // `toString()` con zona horaria, que la validación rechazaría.
    $html = Blade::render('<x-muni::calendar :min="$min" />', ['min' => Carbon::parse('2026-09-05 13:45:00')]);

    expect((bool) preg_match('/\sdata-min="2026-09-05"/', $html))->toBeTrue(
        'Un Carbon como mínimo no se formatea a `Y-m-d` en PHP.'
    );
});

it('disabled() no muta el mínimo: se normaliza una sola vez al iniciar', function () {
    $xData = xDataCalendario(Blade::render('<x-muni::calendar min="2026-09-05" />'));

    expect((bool) preg_match('/disabled\s*\(\s*d\s*\)\s*\{([^}]*)\}/', $xData, $m))->toBeTrue(
        'No se encontró el método `disabled(d)` en el `x-data` del calendario.'
    );

    $cuerpo = $m[1] ?? '';

    expect(str_contains($cuerpo, 'setHours'))->toBeFalse(
        '`disabled(d)` sigue llamando a `setHours(0,0,0,0)` sobre el mínimo: lo muta en cada '.
        'evaluación y devuelve un timestamp, así que el día del mínimo cambia de estado entre '.
        'una pasada y la siguiente.'
    );

    expect(preg_match_all('/\bd\s*<\s*this\.min\b/', $cuerpo))->toBe(1,
        '`disabled(d)` compara contra el mínimo más de una vez: la normalización va en `init()`, '.
        'no en cada comparación.'
    );

    expect((bool) preg_match('/init\s*\(\s*\)\s*\{[^}]*this\.min\s*=/', $xData))->toBeTrue(
        'El mínimo no se fija en `init()`: es el único lugar donde se normaliza, y una sola vez.'
    );
});

it('los días anteriores al mínimo se anuncian y se ven como no disponibles, no solo por color', function () {
    $html = Blade::render('<x-muni::calendar min="2026-09-05" />');

    expect((bool) preg_match('/<button\b(?=[^>]*muni-cal__day)[^>]*:aria-disabled="/', $html))->toBeTrue(
        'Los días fuera de rango no llevan `aria-disabled`: el lector de pantalla los anuncia '.
        'como elegibles y el clic no hace nada (WCAG 2.2 AA 3.3.2).'
    );

    // `aria-disabled` y no `disabled`: un día fuera de rango tiene que seguir
    // siendo alcanzable con el teclado y anunciable, no desaparecer del recorrido.
    expect((bool) preg_match('/<button\b(?=[^>]*muni-cal__day)[^>]*\s:disabled="/', $html))->toBeFalse(
        'Los días fuera de rango usan `disabled`: salen del orden de tabulación y el lector de '.
        'pantalla no puede ni llegar a ellos para saber por qué no se pueden elegir.'
    );

    $css = cssCalendario();

    expect((bool) preg_match('/\.muni-cal__day--off\s*\{([^}]*)\}/', $css, $m))->toBeTrue(
        'No existe la regla `.muni-cal__day--off`: los días fuera de rango se pintan igual que los válidos.'
    );

    expect((bool) preg_match('/text-decoration\s*:\s*line-through/', $m[1] ?? ''))->toBeTrue(
        'Los días fuera de rango se distinguen solo por color: falta el tachado (WCAG 2.2 AA 1.4.1).'
    );
});

it('value siembra la selección desde el servidor y también falla cerrado', function () {
    // Con Livewire, lo que sobrevive a un re-render es lo que el servidor
    // vuelve a pintar: el anfitrión pasa `:value="$fecha"` y el calendario
    // arranca con ese día elegido, sin esperar a Alpine.
    $html = Blade::render('<x-muni::calendar name="cita" value="2026-09-15" />');

    expect((bool) preg_match('/\sdata-value="2026-09-15"/', $html))->toBeTrue(
        'La selección inicial no sale como `data-value` en la raíz del componente.'
    );

    expect((bool) preg_match('/<input\b(?=[^>]*type="hidden")(?=[^>]*name="cita")[^>]*\svalue="2026-09-15"/', $html))->toBeTrue(
        'El input oculto no lleva el valor inicial desde el servidor: sin Alpine el formulario '.
        'enviaría la fecha vacía.'
    );

    expect(str_contains(xDataCalendario($html), '2026-09-15'))->toBeFalse(
        'La selección inicial está interpolada dentro de `x-data`: es el mismo sumidero que `min`.'
    );

    $hostil = Blade::render('<x-muni::calendar :value="$v" />', ['v' => "2026-09-15') || alert(1) || ('"]);

    expect(str_contains($hostil, 'alert('))->toBeFalse(
        'Un `value` que no es una fecha `Y-m-d` llegó al HTML: la validación no falla cerrado.'
    );
    expect(str_contains($hostil, 'data-value='))->toBeFalse(
        'Con un `value` inválido se emite `data-value` igual.'
    );
});

it('el título del mes es una región viva y cada día lleva su fecha completa', function () {
    $html = Blade::render('<x-muni::calendar />');

    expect((bool) preg_match('/<[a-z]+\b(?=[^>]*muni-cal__title)[^>]*aria-live="polite"/', $html))->toBeTrue(
        'El título del mes no es `aria-live="polite"`: cambiar de mes no se anuncia.'
    );

    expect((bool) preg_match('/<button\b(?=[^>]*muni-cal__day)[^>]*:aria-label="/', $html))->toBeTrue(
        'Los días no llevan `aria-label` con la fecha completa: el lector de pantalla dice «5», '.
        'no «viernes 5 de septiembre de 2026».'
    );

    expect((bool) preg_match('/<button\b(?=[^>]*muni-cal__day)[^>]*:aria-pressed="/', $html))->toBeTrue(
        'El día elegido se marca solo con una clase: falta `aria-pressed` para que el estado '.
        'se oiga y no solo se vea.'
    );
});

it('los botones de mes muestran el foco con el outline del sistema y se mueven con --muni-dur', function () {
    $css = cssCalendario();

    expect((bool) preg_match('/\.muni-cal__nav:focus-visible\s*\{([^}]*)\}/', $css, $m))->toBeTrue(
        'No existe `.muni-cal__nav:focus-visible`: los botones de mes dependen del outline por '.
        'defecto del navegador, que dentro de Filament se apaga.'
    );

    expect((bool) preg_match('/outline\s*:\s*3px\s+solid\s+var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/', $m[1] ?? ''))->toBeTrue(
        'El foco de los botones de mes no dibuja el outline del sistema con su cadena de respaldo.'
    );

    // Toda transición sale de `--muni-dur`, que baja a 0 ms con movimiento
    // reducido. Una duración literal (`.15s`) ignora la preferencia.
    preg_match_all('/transition\s*:([^;]*);/', $css, $t);

    foreach ($t[1] as $valor) {
        expect((bool) preg_match('/\b\d*\.?\d+m?s\b/', $valor))->toBeFalse(sprintf(
            'Hay una transición con duración literal, que ignora prefers-reduced-motion: `%s`',
            trim($valor)
        ));
    }

    // Y la guardia local, como en `stat`: `muni-ui.css` baja `--muni-dur` a 0 ms
    // bajo movimiento reducido, pero `muni-ui-filament.css` —la ÚNICA hoja que
    // se carga dentro de un panel (DESIGN §7)— no lo hace. Medido en el banco
    // del panel con `reduced_motion='reduce'`, Chromium y Firefox: el día y los
    // botones de mes seguían en 0,16 s. El componente apaga sus transiciones
    // él mismo, sin depender de que la hoja del anfitrión lo haga.
    expect((bool) preg_match('/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{([^}]*\{[^}]*\})*?[^}]*\}/s', $css, $rm))->toBeTrue(
        'El calendario no trae su propia guardia `@media (prefers-reduced-motion: reduce)`: dentro '.
        'de un panel Filament la hoja no baja --muni-dur y el movimiento sigue a 160 ms.'
    );

    foreach (['.muni-cal__day', '.muni-cal__nav'] as $selector) {
        expect((bool) preg_match('/'.preg_quote($selector, '/').'[^{]*\{[^}]*transition\s*:\s*none/', $rm[0]))->toBeTrue(
            "La guardia de movimiento reducido no apaga la transición de `{$selector}`."
        );
    }
});

it('el calendario no escribe un color literal fuera del respaldo del foco', function () {
    $literales = [];

    preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/', cssCalendario(), $m);

    foreach ($m[0] as $color) {
        if (strtolower($color) !== '#767676') {
            $literales[] = $color;
        }
    }

    expect($literales)->toBe([], sprintf(
        'El calendario escribe colores literales, que no tienen contraparte en modo oscuro: %s',
        implode(', ', $literales)
    ));
});

it('los huecos previos al día 1 ocupan una celda de verdad y cada día lleva una clave por fecha', function () {
    // Antes los huecos eran iteraciones de `x-for` cuyo único hijo era
    // `<template x-if="c">` con `c = null`. Alpine deja en el DOM un
    // `<template>` —display:none— que NO es un ítem de la rejilla CSS, así que
    // el día 1 de cualquier mes caía bajo «L» y el aria-label («martes 1 de
    // septiembre») contradecía la columna visual en todos los días del mes.
    // Medido en Chromium: 37 hijos visibles = 7 cabeceras + 30 días, cero
    // huecos. Ahora los huecos son elementos reales, en un `x-for` propio, y
    // los días cuelgan DIRECTAMENTE del suyo, sin `x-if` en medio.
    $html = Blade::render('<x-muni::calendar />');
    $rejilla = rejillaCalendario($html);

    expect($rejilla !== '')->toBeTrue('No se encontró la rejilla `.muni-cal__grid` en el render.');

    expect(str_contains($rejilla, '<template x-if'))->toBeFalse(
        'La rejilla lleva un `<template x-if>`: cuando la condición es falsa, Alpine deja un '.
        '`<template>` con display:none que no ocupa celda y corre todo el mes una columna.'
    );

    expect((bool) preg_match('/<template x-for="[a-z]+ in huecos"[^>]*>\s*<span\b[^>]*\baria-hidden="true"[^>]*><\/span>\s*<\/template>/', $rejilla))->toBeTrue(
        'Los huecos previos al día 1 no se pintan como un elemento real (`<span aria-hidden="true">` '.
        'dentro de su propio `x-for` sobre `huecos`): sin celda, el día 1 cae bajo «L».'
    );

    expect((bool) preg_match('/<template x-for="c in fechas" :key="iso\(c\)">\s*<button\b/', $rejilla))->toBeTrue(
        'El `x-for` de los días tiene que iterar solo fechas (`c in fechas`), con `:key="iso(c)"` '.
        'y el `<button>` como hijo directo. Con `:key="i"` Alpine reutiliza el nodo de la '.
        'posición i al cambiar de mes y el botón enfocado pasa a ser OTRO día.'
    );

    // El orden en el fuente es el orden en el DOM: cabeceras, huecos, días.
    // Alpine inserta los clones justo después de cada <template>.
    $dias = strpos($rejilla, 'in dias"');
    $huecos = strpos($rejilla, 'in huecos"');
    $fechas = strpos($rejilla, 'in fechas"');

    expect($dias !== false && $huecos !== false && $fechas !== false && $dias < $huecos && $huecos < $fechas)->toBeTrue(
        'La rejilla tiene que emitir, en este orden, las cabeceras de semana, los huecos y los días.'
    );

    // Y los dos listados salen de la MISMA cuenta del primer día de la semana:
    // `huecos` es un rango de `first` posiciones y `fechas` arranca en el 1.
    $xData = xDataCalendario($html);

    expect((bool) preg_match('/get huecos\s*\(\s*\)/', $xData) && (bool) preg_match('/get fechas\s*\(\s*\)/', $xData))->toBeTrue(
        'Faltan los getters `huecos` y `fechas` en el `x-data`: la rejilla no tiene de dónde pintar.'
    );
});

it('el día elegido conserva su texto bajo el mouse, en las dos hojas y los dos temas', function () {
    // `.muni-cal__day:hover` (0,2,0) ganaba a `.muni-cal__day--on` (0,1,0): el
    // fondo del elegido pasaba a `--muni-surface-2` y el color seguía en
    // `--muni-on-accent`. Medido con getComputedStyle: claro #fff sobre #f5f6f8
    // = 1,08:1; oscuro #0b0f14 sobre #181e2a = 1,15:1. En el tema del panel,
    // lo mismo (surface-2 = --mg-papel, on-accent #ffffff).
    $css = cssCalendario();

    preg_match_all('/(\.muni-cal__day(?:--[a-z]+)?:hover)\s*\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    expect(count($reglas) > 0)->toBeTrue('No hay ninguna regla `:hover` sobre `.muni-cal__day`.');

    // La regla del elegido bajo el mouse existe y es la ÚLTIMA de las `:hover`:
    // todas empatan en especificidad (0,2,0), así que gana la que va después.
    // Va después también de `--off:hover` (fondo transparente): un `value`
    // sembrado por debajo del mínimo es elegido Y fuera de rango a la vez.
    $ultima = end($reglas);

    expect($ultima[1])->toBe('.muni-cal__day--on:hover',
        'La última regla `:hover` de la rejilla es `'.$ultima[1].'`, no `.muni-cal__day--on:hover`: '.
        'cualquier `:hover` posterior de la misma especificidad pisa el fondo del elegido y deja '.
        'el texto en --muni-on-accent sobre una superficie clara.'
    );

    expect((bool) preg_match('/background\s*:\s*var\(--muni-([a-z0-9-]+)\)/', $ultima[2], $fondo))->toBeTrue(
        '`.muni-cal__day--on:hover` no fija un fondo desde un token: el elegido bajo el mouse '.
        'hereda el fondo del `:hover` general.'
    );

    // El color del elegido tiene que ganarle al del tachado: `--on` va DESPUÉS
    // de `--off` en el fuente (misma especificidad, 0,1,0).
    $on = strpos($css, '.muni-cal__day--on {');
    $off = strpos($css, '.muni-cal__day--off {');

    expect($on !== false && $off !== false && $off < $on)->toBeTrue(
        '`.muni-cal__day--on` tiene que ir después de `.muni-cal__day--off`: si no, un día elegido '.
        'por debajo del mínimo se pinta con el color del tachado sobre el fondo de acento.'
    );

    expect((bool) preg_match('/\.muni-cal__day--on\s*\{([^}]*)\}/', $css, $reposo))->toBeTrue('No existe `.muni-cal__day--on`.');
    expect((bool) preg_match('/color\s*:\s*var\(--muni-on-accent\)/', $reposo[1]))->toBeTrue(
        'El elegido no escribe su texto con `--muni-on-accent`.'
    );
    expect((bool) preg_match('/background\s*:\s*var\(--muni-accent\)/', $reposo[1]))->toBeTrue(
        'El elegido no pinta su fondo con `--muni-accent`.'
    );

    // Y el contraste, con los tokens REALES de cada hoja y cada tema: el
    // texto del elegido sobre su fondo en reposo y sobre su fondo bajo el
    // mouse. Se resuelve desde el CSS —`var()` y `color-mix()` incluidos—
    // para que un cambio de paleta caiga acá y no en producción.
    $fallos = [];

    foreach (bloquesDeTokensCalendario() as $nombre => [$hoja, $bloque]) {
        $texto = tokenColor($bloque, $hoja, 'on-accent');
        $enReposo = tokenColor($bloque, $hoja, 'accent');
        $bajoMouse = tokenColor($bloque, $hoja, $fondo[1]);

        expect($texto !== null && $enReposo !== null && $bajoMouse !== null)->toBeTrue(
            "No se pudieron resolver --muni-on-accent, --muni-accent o --muni-{$fondo[1]} en {$nombre}."
        );

        foreach (['en reposo' => $enReposo, 'bajo el mouse' => $bajoMouse] as $estado => $fondoHex) {
            $ratio = ratioContraste($texto, $fondoHex);

            if ($ratio < 4.5) {
                $fallos[] = sprintf('%s, %s: --muni-on-accent (%s) sobre %s = %.2f:1', $nombre, $estado, $texto, $fondoHex, $ratio);
            }
        }
    }

    expect($fallos)->toBe([], "El texto del día elegido no llega a 4,5:1 (WCAG 1.4.3):\n  ".implode("\n  ", $fallos));
});

it('la selección es de ida y vuelta: wire:model y x-model se entrelazan con x-modelable', function () {
    // `wire:model` sobre `<x-muni::calendar>` cae en la raíz, donde Livewire lo
    // convierte en un `x-model`. Antes se alimentaba con el `input` burbujeado
    // del campo oculto: cliente → servidor, y nada más. Cuando el servidor
    // cambiaba la propiedad (resetear tras guardar, cargar otra cita), el
    // morph solo tocaba el atributo `value` —que `:value` pisa— e `init()` no
    // se re-ejecuta: el calendario seguía mostrando la selección vieja.
    // `x-modelable` entrelaza el modelo externo con `valor`, en las dos
    // direcciones; es el patrón que documenta Livewire para controles Alpine.
    $html = Blade::render('<x-muni::calendar value="2026-09-15" />');
    $raiz = raizCalendario($html);

    expect($raiz !== '')->toBeTrue('No se encontró la etiqueta de apertura de la raíz.');

    expect(str_contains($raiz, 'x-modelable="valor"'))->toBeTrue(
        'La raíz no lleva `x-modelable="valor"`: `wire:model` y `x-model` no pueden mover la '.
        'selección desde fuera, y un cambio del servidor deja el calendario en la fecha vieja.'
    );

    $xData = xDataCalendario($html);

    expect((bool) preg_match('/\bvalor\s*:/', $xData))->toBeTrue(
        'El `x-data` no declara la propiedad `valor`, que es la que `x-modelable` entrelaza.'
    );

    // `init()` siembra `valor` desde el atributo de datos ANTES de que
    // `x-modelable` lea el valor inicial (Alpine evalúa `x-data` —con su
    // `init()`— antes que `x-modelable`).
    expect((bool) preg_match('/init\s*\(\s*\)\s*\{[^}]*this\.valor\s*=[^;]*\$el\.dataset\.value/', $xData))->toBeTrue(
        '`init()` no siembra `valor` desde `$el.dataset.value`: sin modelo externo, `value` no '.
        'selecciona nada.'
    );

    // Todo lo derivado (el Date elegido, el mes a la vista) sale de un $watch
    // sobre `valor`: así da igual si lo cambió un clic o el servidor.
    expect((bool) preg_match('/\$watch\(\s*[\'"]valor[\'"]\s*,\s*(?:\([a-z]+\)|[a-z]+)\s*=>\s*\{(.*?)\}\s*\)/s', $xData, $watch))->toBeTrue(
        'No hay un `$watch(\'valor\', …)`: un cambio del modelo externo no mueve la selección.'
    );

    // El watcher NUNCA escribe `valor` de vuelta: normalizar ahí (null → '')
    // sería un cambio que `x-modelable` propaga al modelo, y con
    // `wire:model.live` es una petición al servidor que nadie pidió.
    expect((bool) preg_match('/this\.valor\s*=/', $watch[1]))->toBeFalse(
        'El `$watch` de `valor` escribe `valor`: eso rebota al modelo externo y con '.
        '`wire:model.live` dispara una petición espuria.'
    );

    expect((bool) preg_match('/pick\s*\(\s*d\s*\)\s*\{[^}]*this\.valor\s*=\s*this\.iso\(\s*d\s*\)/', $xData))->toBeTrue(
        '`pick(d)` no escribe `this.valor = this.iso(d)`: elegir un día no llega al modelo.'
    );

    expect((bool) preg_match('/<input\b(?=[^>]*type="hidden")[^>]*:value="valor"/', $html))->toBeTrue(
        'El campo oculto no está ligado a `valor`: el formulario clásico enviaría otra cosa que el modelo.'
    );
});

it('al elegir un día el campo oculto emite `input` y `change` burbujeando, para el anfitrión sin modelo', function () {
    // Con `x-modelable`, `wire:model` y `x-model` ya no escuchan el `input` del
    // campo (Alpine quita ese listener al entrelazar). Los eventos siguen
    // saliendo, y burbujeando, para el anfitrión que NO usa modelo: un
    // `@change` sobre `<x-muni::calendar>` o un listener nativo sobre el
    // formulario. Sin `bubbles: true` morirían en el input oculto.
    $xData = xDataCalendario(Blade::render('<x-muni::calendar />'));

    expect((bool) preg_match('/pick\s*\(\s*d\s*\)\s*\{[^}]*emitir\(\)/', $xData))->toBeTrue(
        '`pick(d)` ya no llama a `emitir()`: elegir un día no avisa a nadie.'
    );

    expect((bool) preg_match('/new Event\(\s*[\'"]input[\'"]\s*,\s*\{[^}]*bubbles\s*:\s*true/', $xData))->toBeTrue(
        'El campo oculto no emite un `input` con `bubbles: true`: un `@input` del anfitrión nunca se entera.'
    );

    expect((bool) preg_match('/new Event\(\s*[\'"]change[\'"]\s*,\s*\{[^}]*bubbles\s*:\s*true/', $xData))->toBeTrue(
        'El campo oculto no emite un `change` con `bubbles: true`: un `@change` del anfitrión nunca se entera.'
    );

    expect((bool) preg_match('/<input\b(?=[^>]*type="hidden")[^>]*x-ref="campo"/', Blade::render('<x-muni::calendar />')))->toBeTrue(
        'El campo oculto perdió el `x-ref="campo"` desde el que se emite el evento.'
    );
});

it('las props públicas no cambiaron de nombre', function () {
    // El componente ya está en los sistemas: el arreglo es aditivo.
    $html = Blade::render('<x-muni::calendar name="fecha_cita" min="2026-09-05" id="agenda-cita" />');

    expect((bool) preg_match('/<input\b[^>]*name="fecha_cita"/', $html))->toBeTrue('La prop `name` dejó de funcionar.');
    expect(str_contains($html, 'data-min="2026-09-05"'))->toBeTrue('La prop `min` dejó de funcionar.');
    expect(str_contains($html, 'class="muni-cal"'))->toBeTrue('La raíz perdió la clase `muni-cal`.');
    expect(str_contains($html, 'id="agenda-cita"'))->toBeTrue('Los atributos del consumidor ya no llegan a la raíz.');
});

/*
 * El banco de navegador. Se genera acá y no en un script suelto porque este es
 * el único sitio del paquete con Blade arrancado (mismo criterio que
 * `GeneraVitrinaTest`, `GuardiaDeSesionTest` y `CifraComparadaTest`). Sale a
 * `build/calendar/`, ignorado por git, y lo abre Playwright (`.venv-a11y`) para
 * medir lo que un test de Blade no puede: bajo qué columna cae el día 1, qué
 * color computa el elegido bajo el mouse, si el modelo externo mueve la
 * selección y si el hostil ejecuta algo.
 *
 * Cuatro páginas por lo mismo que la vitrina (DESIGN §7): dentro de un panel
 * Filament `muni-ui.css` no se carga, y la paleta del panel vale distinto.
 * `panel-*` carga ÚNICAMENTE la hoja del panel y el DOM mínimo que estiliza.
 *
 * Alpine se hornea desde `node_modules` (o `$MUNI_ALPINE_JS`), nunca de un CDN:
 * el banco tiene que medirse sin red. Sin copia, el banco sale sin hidratar y
 * se dice en voz alta; la máquina sin `npm install` no se queda sin suite.
 */
function alpineParaBancoCalendario(): ?string
{
    $candidatos = [];

    if ($ruta = getenv('MUNI_ALPINE_JS')) {
        $candidatos[] = $ruta;
    }

    $candidatos[] = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    foreach ($candidatos as $candidato) {
        if (is_file($candidato)) {
            return (string) file_get_contents($candidato);
        }
    }

    return null;
}

it('genera el banco de navegador en build/calendar/', function () {
    $dir = __DIR__.'/../build/calendar';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $alpine = alpineParaBancoCalendario();

    if ($alpine === null) {
        fwrite(STDERR, "\nAVISO: no hay copia de Alpine 3 en disco, el banco del calendario sale SIN hidratar.\n");
    }

    // Cuatro instancias, cada una con un mes distinto a propósito:
    //   · min: septiembre de 2026 arranca en MARTES (1 hueco); el 12 elegido.
    //   · value: noviembre de 2026 arranca en DOMINGO (6 huecos, el máximo).
    //   · modelo: un x-model REAL en la raíz —lo mismo en que Livewire convierte
    //     `wire:model`— sembrado desde fuera, con dos botones que lo cambian
    //     desde el anfitrión para medir la vuelta (servidor → calendario).
    //   · hostil: min y value que cierran la cadena de JS; `fetch` y `alert`
    //     quedan interceptados en la página para contar si se ejecutan.
    $piezas = Blade::render(
        '<section data-pieza="min"><h2>min</h2>'
        .'<x-muni::calendar id="cal-min" name="fecha_min" min="2026-09-05" value="2026-09-12" /></section>'
        .'<section data-pieza="value"><h2>value</h2>'
        .'<x-muni::calendar id="cal-value" name="fecha_value" value="2026-11-15" /></section>'
        .'<section data-pieza="modelo"><h2>modelo</h2><div x-data="{ fecha: \'2026-09-12\' }">'
        .'<x-muni::calendar id="cal-modelo" name="fecha_modelo" x-model="fecha" min="2026-09-05" />'
        .'<p>Modelo: <output id="eco" x-text="fecha"></output></p>'
        .'<button type="button" id="mover" @click="fecha = \'2026-10-20\'">Mover al 20 de octubre</button> '
        .'<button type="button" id="vaciar" @click="fecha = \'\'">Vaciar</button></div></section>'
        .'<section data-pieza="hostil"><h2>hostil</h2>'
        .'<x-muni::calendar id="cal-hostil" name="fecha_hostil" :min="$m" :value="$v" /></section>',
        ['m' => "2026-09-05') || fetch('https://x.example/') || ('", 'v' => "2026-09-15') || alert(1) || ('"]
    );

    $centinelas = '<script>'
        .'window.__fetches = 0; window.fetch = function () { window.__fetches++; return Promise.reject(new Error("bloqueado")); };'
        .'window.__alertas = 0; window.alert = function () { window.__alertas++; };'
        .'window.__errores = []; window.addEventListener("error", function (e) { window.__errores.push(String(e.message)); });'
        .'</script>';

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
            .'h1{font-size:20px;margin:0 0 16px}.banco{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start}'
            .'section h2{font-size:12px;font-family:var(--muni-font-mono);color:var(--muni-muted);margin:0 0 8px}';

        $cuerpo = '<button id="antes" type="button">antes del calendario</button>'
            .'<div class="banco">'.$piezas.'</div>'
            .'<button id="despues" type="button">después del calendario</button>';

        $body = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<h1>Agenda de licencias — banco del calendario</h1><section class="fi-section">'.$cuerpo.'</section></main></div>'
            : '<body><main id="muni-contenido" tabindex="-1"><h1>Agenda de licencias — banco del calendario</h1>'.$cuerpo.'</main>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco calendar — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style>'.$centinelas.'</head>'
            .$body
            .($alpine !== null ? '<script>'.$alpine.'</script>' : '')
            .'</body></html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);

        // Constancia sobre lo escrito: ni el `fetch(` ni el `alert(1)` hostiles
        // llegan a la página, en ninguna de las cuatro.
        expect(str_contains($pagina, "fetch('https://x.example/')"))->toBeFalse("El min hostil llegó al banco {$nombre}.");
        expect(str_contains($pagina, 'alert(1)'))->toBeFalse("El value hostil llegó al banco {$nombre}.");
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});
