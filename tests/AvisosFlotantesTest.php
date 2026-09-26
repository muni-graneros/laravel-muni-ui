<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LOS AVISOS FLOTANTES
|--------------------------------------------------------------------------
|
| `<x-muni::toast-host>` es la pila de avisos de las vistas públicas Blade:
| «Solicitud N.º 2026-4831 ingresada — derivada a Obras» en la recepción de
| Atención al Vecino, «No se pudo guardar: el RUT ya tiene licencia clase B
| vigente» en el mesón de licencias. Estaba y no cumplía, con cuatro defectos
| medidos en `docs/GAP-ANALYSIS.md` y la corrección del juez encima:
|
| 1. ARQUITECTURA ARIA. El contenedor llevaba `aria-live="polite"` y cada aviso
|    `role="status"`: una región viva anidada dentro de otra, que en varios
|    lectores anuncia dos veces; y un error de tono `danger` se anunciaba en
|    modo cortés, en cola detrás de otro discurso. Ahora hay DOS regiones vivas
|    persistentes y VACÍAS desde la carga —cortés y asertiva— a las que se
|    inyecta SOLO el texto del aviso según el tono, y la pila visual no es
|    región viva de nada: es interfaz, no anuncio.
| 2. TIEMPO (WCAG 2.2 AA 2.2.1). `setTimeout(…, detail.duration || 4500)`
|    autodestruía el aviso a los 4,5 s sin pausa al puntero ni al foco: un folio
|    no se alcanzaba a leer ni a copiar. Ahora `danger` y `warn` no se autocierran
|    nunca; `ok` e `info` escalan con el largo del texto entre 5 y 20 s, se pausan
|    con el puntero encima y mientras contengan el foco, y `duration: 0` de verdad
|    significa «no autocerrar» (con `||` caía al valor por defecto).
| 3. FOCO. Si el foco estaba en la × cuando vencía el temporizador, caía al
|    <body> por accidente. Ahora, al destruirse un aviso con el foco, se devuelve
|    de forma explícita al aviso anterior de la pila, al elemento de donde vino, o
|    al <body>.
| 4. `$attributes` nunca se renderizaba: `id`, `class` y `wire:ignore` se
|    descartaban en silencio. Y no había tope de pila.
|
| Se prueba sobre el HTML renderizado y sobre el bloque `<style>` del propio
| componente, con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra cadena a buscar, no un mensaje, y la
| aserción se desactiva sin avisar.
*/

/** El `toast-host.blade.php` crudo, sin comentarios Blade ni de CSS. */
function fuenteAvisosFlotantes(): string
{
    $fuente = (string) file_get_contents(__DIR__.'/../resources/views/components/toast-host.blade.php');
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El contenido de los bloques `<style>` del componente, sin comentarios. */
function cssAvisosFlotantes(): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteAvisosFlotantes(), $m);

    return implode("\n", $m[1]);
}

/** El host renderizado con los atributos que se le pasen. */
function avisosFlotantesRendidos(string $atributos = ''): string
{
    return Blade::render('<x-muni::toast-host '.$atributos.' />');
}

/**
 * Los valores de todos los atributos de Alpine del HTML dado, ya decodificados
 * como los ve el navegador (`x-…`, `@…`, `:…`).
 *
 * @return array<int, string>
 */
function expresionesAlpineDeAvisos(string $html): array
{
    preg_match_all('/\s(?:x-[a-z0-9.:-]+|@[a-z0-9.-]+|:[a-z0-9-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

/** La etiqueta de apertura del contenedor del host: la que escucha `muni-toast`. */
function aperturaDelHost(string $html): string
{
    preg_match('/<div\b(?:"[^"]*"|[^>"])*@muni-toast\.window(?:"[^"]*"|[^>"])*>/s', $html, $m);

    return $m[0] ?? '';
}

/** La etiqueta de apertura del aviso: el primer elemento dentro del `<template x-for>`. */
function aperturaDelAviso(string $html): string
{
    preg_match('/<template x-for="item in items"[^>]*>\s*<div\b((?:"[^"]*"|[^>"])*)>/s', $html, $m);

    return $m[0] ?? '';
}

/** Etiqueta de apertura y contenido de la región viva con el rol pedido. */
function regionVivaDeAvisos(string $html, string $rol): ?array
{
    $ok = preg_match(
        '/<div\b((?:"[^"]*"|[^>"])*\brole="'.$rol.'"(?:"[^"]*"|[^>"])*)>(.*?)<\/div>/s',
        $html,
        $m
    );

    return $ok ? ['apertura' => $m[1], 'contenido' => $m[2]] : null;
}

/**
 * La etiqueta de apertura del botón de cerrar TAL COMO SE ESCRIBE en el fuente,
 * sin compilar.
 *
 * Se mira el fuente y no el HTML porque lo que hay que comprobar es que el
 * nombre pase por `__()`, y eso desaparece al renderizar. Y se acota al botón
 * porque buscar `aria-label="{{ __(` en el archivo entero es un falso verde: las
 * dos regiones vivas también lo llevan, así que la aserción seguía en verde con
 * el nombre del botón escrito a mano. Medido: con `aria-label="Cerrar"` literal
 * la prueba pasaba igual.
 */
function aperturaDelBotonDeCerrar(): string
{
    preg_match('/<button\b[^>]*class="muni-toast__x"[^>]*>/s', fuenteAvisosFlotantes(), $m);

    return $m[0] ?? '';
}

// ---------------------------------------------------------------------------
// 1. Arquitectura ARIA
// ---------------------------------------------------------------------------

it('tiene dos regiones vivas persistentes y vacías desde la carga, una cortés y una asertiva', function () {
    $html = avisosFlotantesRendidos();

    foreach (['status' => 'polite', 'alert' => 'assertive'] as $rol => $prioridad) {
        $region = regionVivaDeAvisos($html, $rol);

        expect($region)->not->toBeNull(
            "No hay una región viva `role=\"$rol\"` en el HTML inicial: una región que se ".
            'inyecta COMPLETA junto con su mensaje no se anuncia de forma fiable en NVDA ni JAWS.'
        );

        expect(str_contains($region['apertura'], 'aria-live="'.$prioridad.'"'))->toBeTrue(
            "La región `role=\"$rol\"` no declara `aria-live=\"$prioridad\"` explícito (el trío ".
            'rol + aria-live + aria-atomic es el que leen todos los lectores).'
        );
        expect(str_contains($region['apertura'], 'aria-atomic="true"'))->toBeTrue(
            "La región `role=\"$rol\"` no es atómica: el lector puede leer solo el trozo que cambió."
        );
        expect(trim($region['contenido']))->toBe('',
            "La región `role=\"$rol\"` nace con contenido: se anuncia sola al cargar la página."
        );
        expect((bool) preg_match('/\baria-label="[^"]+"/', $region['apertura']))->toBeTrue(
            "La región `role=\"$rol\"` no tiene nombre accesible."
        );
        expect(str_contains($region['apertura'], 'tabindex'))->toBeFalse(
            "La región `role=\"$rol\"` lleva `tabindex`: una región viva jamás recibe el foco."
        );
        expect((bool) preg_match('/\bclass="[^"]*\bmuni-sr\b/', $region['apertura']))->toBeTrue(
            "La región `role=\"$rol\"` no está visualmente oculta con `.muni-sr`."
        );
    }
});

it('la pila visual de avisos no es región viva: ni el contenedor ni cada aviso llevan role o aria-live', function () {
    $html = avisosFlotantesRendidos();
    $host = aperturaDelHost($html);
    $aviso = aperturaDelAviso($html);

    expect($host)->not->toBe('', 'No se encontró el contenedor que escucha `muni-toast` en `window`.');
    expect($aviso)->not->toBe('', 'No se encontró el aviso dentro del `<template x-for>`.');

    foreach (['contenedor' => $host, 'aviso' => $aviso] as $que => $apertura) {
        expect((bool) preg_match('/\baria-live=/', $apertura))->toBeFalse(
            "El $que lleva `aria-live`: la pila visual es interfaz, no anuncio. Con una región ".
            'viva encima de otra el lector anuncia dos veces.'
        );
        expect((bool) preg_match('/\brole=/', $apertura))->toBeFalse(
            "El $que lleva `role`: el anuncio va por las dos regiones ocultas, no por la pila."
        );
    }
});

it('el texto del aviso va a la región asertiva solo cuando el tono es danger', function () {
    // El selector de prioridad por tono: `danger` interrumpe, todo lo demás espera
    // su turno. Es lo que hacía `alert.blade.php` antes de que el rol pasara al
    // consumidor, y lo que el juez pide copiar.
    $fuente = fuenteAvisosFlotantes();

    expect((bool) preg_match('/prioridadDe\s*\([^)]*\)\s*\{[^}]*\'danger\'[^}]*\'assertive\'[^}]*\'polite\'/s', $fuente))->toBeTrue(
        'No hay un selector `prioridadDe()` que mande el tono `danger` a la región asertiva y '.
        'el resto a la cortés.'
    );
});

it('las regiones vivas se ocultan recortando, nunca con display:none', function () {
    $css = cssAvisosFlotantes();

    expect((bool) preg_match('/display\s*:\s*none/i', $css))->toBeFalse(
        'El componente usa `display:none`, que saca el nodo del árbol de accesibilidad: la '.
        'región viva queda muda.'
    );
    expect((bool) preg_match('/visibility\s*:\s*hidden/i', $css))->toBeFalse(
        'El componente usa `visibility:hidden`, que saca el nodo del árbol de accesibilidad.'
    );
    expect((bool) preg_match('/\.muni-sr\s*\{[^}]*clip-path\s*:\s*inset\(50%\)/', $css))->toBeTrue(
        'La clase `.muni-sr` no viaja en el bloque de estilos del componente con la técnica de '.
        'recorte (`clip-path:inset(50%)` más 1px). Tiene que viajar con él: dentro de un panel '.
        'Filament solo se carga `muni-ui-filament.css`.'
    );
});

// ---------------------------------------------------------------------------
// 2. Tiempo (WCAG 2.2 AA 2.2.1)
// ---------------------------------------------------------------------------

it('duration: 0 significa «no autocerrar»: el valor por defecto entra con ?? y no con ||', function () {
    $fuente = fuenteAvisosFlotantes();

    expect((bool) preg_match('/duration\s*\|\|/', $fuente))->toBeFalse(
        '`detail.duration || …` convierte `duration: 0` en el valor por defecto: un aviso '.
        'persistente se autocierra igual.'
    );
    expect((bool) preg_match('/duration\s*\?\?/', $fuente))->toBeTrue(
        'La duración pedida por el emisor no entra con `??`: `duration: 0` no se respeta.'
    );
});

it('danger y warn no se autocierran nunca; ok e info escalan entre 5 y 20 segundos', function () {
    $fuente = fuenteAvisosFlotantes();

    expect((bool) preg_match('/persistentes\s*:\s*\[\s*\'danger\'\s*,\s*\'warn\'\s*\]/', $fuente))->toBeTrue(
        'Los tonos `danger` y `warn` no están declarados como persistentes: un error se '.
        'autodestruye antes de que el funcionario lo lea.'
    );
    expect((bool) preg_match('/piso\s*:\s*5000\b/', $fuente))->toBeTrue(
        'No hay piso de 5 s para la duración escalada por largo del texto.'
    );
    expect((bool) preg_match('/techo\s*:\s*20000\b/', $fuente))->toBeTrue(
        'No hay techo de 20 s para la duración escalada por largo del texto.'
    );
    expect((bool) preg_match('/setTimeout\([^)]*remove\(/', $fuente))->toBeFalse(
        'Sigue el `setTimeout(() => this.remove(id), …)` fijo del componente viejo.'
    );
});

it('el temporizador se pausa con el puntero encima y mientras el aviso contiene el foco', function () {
    $html = avisosFlotantesRendidos();
    $aviso = aperturaDelAviso($html);

    foreach (['@mouseenter', '@mouseleave', '@focusin', '@focusout'] as $escucha) {
        expect(str_contains($aviso, ' '.$escucha.'='))->toBeTrue(
            "El aviso no escucha `$escucha`: sin pausa al puntero y al foco, el autocierre ".
            'incumple 2.2.1 (tiempo ajustable).'
        );
    }

    // La pausa por foco se decide con `focusin`/`focusout`, no consultando
    // `:focus-within` desde JS en cada tick.
    foreach (expresionesAlpineDeAvisos($html) as $expr) {
        expect(str_contains($expr, 'focus-within'))->toBeFalse(
            'Una expresión de Alpine consulta `:focus-within`: la pausa por foco va por eventos.'
        );
    }
});

// ---------------------------------------------------------------------------
// 3. Foco y teclado
// ---------------------------------------------------------------------------

it('Escape cierra solo el aviso que contiene el foco, nunca desde la ventana', function () {
    $html = avisosFlotantesRendidos();
    $aviso = aperturaDelAviso($html);

    expect((bool) preg_match('/\s@keydown\.escape[.a-z]*="/', $aviso))->toBeTrue(
        'El aviso no escucha `Escape` en su propio elemento.'
    );
    expect(str_contains(fuenteAvisosFlotantes(), '.escape.window'))->toBeFalse(
        'Hay un `@keydown.escape.window`: un Escape global choca con los modales y los '.
        'cajones del propio paquete.'
    );
    expect((bool) preg_match('/\s@keydown\.escape[.a-z]*\.stop[.a-z]*="/', $aviso))->toBeTrue(
        'El Escape del aviso no detiene la propagación: llegaría al modal o al cajón que '.
        'esté abierto debajo y lo cerraría también.'
    );
});

it('al destruirse un aviso que contiene el foco, el foco se devuelve de forma explícita', function () {
    $fuente = fuenteAvisosFlotantes();

    expect((bool) preg_match('/devolverFoco\s*\(/', $fuente))->toBeTrue(
        'No hay una rutina `devolverFoco()`: si el foco está en la × cuando el aviso se '.
        'destruye, cae al <body> por accidente y el siguiente Tab arranca desde el principio.'
    );
    expect(str_contains($fuente, 'document.body.focus()'))->toBeTrue(
        'El respaldo final no es `document.body.focus()` explícito.'
    );
});

it('el host se guarda en init: ni `$el` ni `$root` sirven para buscar el aviso al vuelo', function () {
    // Las dos versiones anteriores de esto se midieron en navegador y las dos fallaban
    // en silencio. `$el` es el elemento sobre el que se evalúa la expresión: desde el
    // botón de cerrar o desde el `keydown` del aviso NO es el host, así que la búsqueda
    // se hacía dentro del propio aviso, no encontraba nada y el foco caía al <body>.
    // `$root` sube por el DOM hasta `[x-data]` y desde un nodo YA DESPRENDIDO devuelve
    // `undefined`, que es exactamente la situación de `devolverFoco()`: corre en el tick
    // siguiente, cuando el aviso que tenía el foco ya salió del documento. Reventaba con
    // «Cannot read properties of undefined» dentro del `$nextTick`, sin nada en pantalla.
    $fuente = fuenteAvisosFlotantes();

    expect((bool) preg_match('/this\.host\s*=\s*this\.\$root/', $fuente))->toBeTrue(
        'El host no se guarda en `init()` con `this.host = this.$root`: resolverlo en cada '.
        'llamada devuelve el elemento equivocado, o nada, según desde dónde entre la llamada.'
    );
    expect((bool) preg_match('/\$(el|root)\.querySelector/', $fuente))->toBeFalse(
        'Se busca un nodo del host con `$el.querySelector` o `$root.querySelector`: desde el '.
        'botón de cerrar apunta al elemento equivocado y desde un aviso ya desprendido no '.
        'apunta a nada.'
    );
});

it('el botón de cerrar tiene nombre traducible, foco visible por outline y área táctil de 24px', function () {
    $html = avisosFlotantesRendidos();
    $css = cssAvisosFlotantes();

    expect((bool) preg_match('/<button\b[^>]*\baria-label="Cerrar"/', $html))->toBeTrue(
        'El botón de cerrar no tiene `aria-label="Cerrar"`.'
    );
    expect((bool) preg_match('/aria-label="\{\{\s*__\(/', aperturaDelBotonDeCerrar()))->toBeTrue(
        'El nombre del botón de cerrar no pasa por `__()`: no se puede traducir. (Se mira la '.
        'etiqueta del botón, no el archivo entero: las regiones vivas también llevan un '.
        '`aria-label` traducido y tapaban el defecto.)'
    );
    expect((bool) preg_match('/<button\b[^>]*\btype="button"/', $html))->toBeTrue(
        'El botón de cerrar no es `type="button"`: dentro de un formulario lo enviaría.'
    );
    expect((bool) preg_match('/<button\b[^>]*:aria-describedby="/', $html))->toBeTrue(
        'El botón de cerrar no se describe con el texto del aviso: con tres avisos apilados '.
        'el lector oye «Cerrar, botón» tres veces sin saber cuál es cuál.'
    );

    $foco = '';
    if (preg_match('/\.muni-toast__x:focus-visible\s*\{([^}]*)\}/', $css, $m)) {
        $foco = preg_replace('/\s+/', '', $m[1]);
    }

    expect(str_contains($foco, 'outline:3pxsolidvar(--muni-focus,var(--muni-accent,#767676))'))->toBeTrue(
        'El foco del botón de cerrar no es `outline: 3px solid var(--muni-focus, '.
        'var(--muni-accent, #767676))`: una sombra se pierde dentro de Filament.'
    );
    expect((bool) preg_match('/\.muni-toast__x[^{]*\{[^}]*outline\s*:\s*(none|0)\b/', $css))->toBeFalse(
        'Una regla del botón de cerrar apaga el outline.'
    );

    $boton = '';
    if (preg_match('/\.muni-toast__x\s*\{([^}]*)\}/', $css, $m)) {
        $boton = preg_replace('/\s+/', '', $m[1]);
    }

    expect(str_contains($boton, 'min-width:24px') && str_contains($boton, 'min-height:24px'))->toBeTrue(
        'El botón de cerrar no garantiza 24×24px (WCAG 2.2 AA 2.5.8): con 3px de relleno y '.
        'un icono de 14px medía 20px.'
    );
});

// ---------------------------------------------------------------------------
// 4. Atributos, tope de pila, tono sin color, inyección
// ---------------------------------------------------------------------------

it('los atributos del consumidor llegan al contenedor y position sigue mandando', function () {
    $html = avisosFlotantesRendidos('id="avisos" class="extra" wire:ignore data-prueba="1" position="top-left"');
    $host = aperturaDelHost($html);

    foreach (['id="avisos"', 'class="extra"', 'wire:ignore', 'data-prueba="1"'] as $atributo) {
        expect(str_contains($host, $atributo))->toBeTrue(
            "`$atributo` no llegó al contenedor: `\$attributes` se descarta en silencio."
        );
    }

    expect(str_contains($host, 'top:16px;left:16px;'))->toBeTrue(
        'La prop `position="top-left"` dejó de aplicarse al fusionar los atributos.'
    );
    expect(str_contains($host, 'position:fixed'))->toBeTrue(
        'El contenedor perdió su `position:fixed`.'
    );
});

it('la pila tiene tope de cuatro avisos simultáneos', function () {
    expect((bool) preg_match('/maximo\s*:\s*4\b/', fuenteAvisosFlotantes()))->toBeTrue(
        'No hay tope de pila: una ráfaga de eventos apila avisos hasta desbordar la pantalla.'
    );
});

it('cada tono lleva su propio glifo y un rótulo oculto: el estado no depende del color', function () {
    $html = avisosFlotantesRendidos();

    foreach (['ok', 'warn', 'danger', 'info'] as $tono) {
        $ok = preg_match(
            '/<template x-if="item\.tone === \''.$tono.'\'">\s*<svg\b[^>]*>/s',
            html_entity_decode($html, ENT_QUOTES | ENT_HTML5)
        );

        expect((bool) $ok)->toBeTrue(
            "El tono `$tono` no tiene un glifo propio: el estado queda comunicado solo con el ".
            'color del borde (WCAG 2.2 AA 1.4.1).'
        );
    }

    expect((bool) preg_match('/<svg\b[^>]*\baria-hidden="true"/', $html))->toBeTrue(
        'Los glifos no van `aria-hidden`: el lector los describe como imagen sin nombre.'
    );
    expect((bool) preg_match('/<span class="muni-sr" x-text="rotulos\[item\.tone\]/', $html))->toBeTrue(
        'El aviso no lleva el rótulo del tono en texto oculto: quien navegue hasta él con el '.
        'lector no sabe si es un error o una confirmación.'
    );
});

it('un id con apóstrofo o con código no descuadra ni ejecuta nada en Alpine', function () {
    // El único texto del anfitrión que entra en una expresión de Alpine es el id
    // del host, y entra por `@js()`, que lo codifica. Un apóstrofo suelto tumba
    // el Alpine de la página ENTERA; un `'+alert(1)+'` se ejecutaría.
    $html = avisosFlotantesRendidos('id="avisos\'+alert(1)+\'x"');

    $rotas = [];
    $ejecutables = [];

    foreach (expresionesAlpineDeAvisos($html) as $expr) {
        $limpia = str_replace("\\'", '', $expr);

        if (substr_count($limpia, "'") % 2 !== 0) {
            $rotas[] = $expr;
        }

        if (str_contains($expr, "'+alert(1)+'")) {
            $ejecutables[] = $expr;
        }
    }

    expect($rotas)->toBe([], sprintf(
        'Estas expresiones de Alpine quedan con las comillas simples descuadradas: %s',
        implode(' | ', $rotas)
    ));
    expect($ejecutables)->toBe([], sprintf(
        'El id del anfitrión entra crudo en una expresión de Alpine, que es código evaluado: %s',
        implode(' | ', $ejecutables)
    ));
});

it('dos hosts en la misma página no muestran ni anuncian el aviso dos veces', function () {
    expect(str_contains(fuenteAvisosFlotantes(), '__muniToastHost'))->toBeTrue(
        'Nada impide que dos hosts en la página reaccionen al mismo evento `muni-toast` y '.
        'muestren el aviso duplicado.'
    );
});

it('no usa colores literales y todo token --muni-* existe en las dos hojas', function () {
    $css = cssAvisosFlotantes();

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $hex);
    $literales = array_values(array_diff(array_unique($hex[0]), ['#767676']));

    expect($literales)->toBe([], 'Colores literales en el componente: '.implode(', ', $literales));

    $hojas = [
        (string) file_get_contents(__DIR__.'/../resources/css/muni-ui.css'),
        (string) file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css'),
    ];

    preg_match_all('/var\(\s*(--muni-[a-z0-9-]+)/', fuenteAvisosFlotantes(), $usados);

    foreach (array_unique($usados[1]) as $token) {
        foreach ($hojas as $i => $hoja) {
            expect((bool) preg_match('/'.preg_quote($token, '/').'\s*:/', $hoja))->toBeTrue(
                "El token `$token` no está declarado en la hoja ".($i === 0 ? 'muni-ui.css' : 'muni-ui-filament.css').
                ': dentro del panel valdría vacío.'
            );
        }
    }
});

// ---------------------------------------------------------------------------
// 5. El comportamiento, EJECUTADO
// ---------------------------------------------------------------------------

/*
 * Las aserciones de arriba miran el texto del componente: sirven para las
 * decisiones que SON texto (qué atributo se emite, qué rol lleva la región, que
 * no haya `.escape.window`). No sirven para lo que el componente HACE, y el
 * revisor lo midió: `persistentes: ['danger','warn']`, `piso: 5000` y
 * `maximo: 4` estaban declarados y aun así un `duration` explícito los saltaba,
 * porque la rama que los usaba no se alcanzaba nunca. Una propiedad declarada no
 * es una regla aplicada.
 *
 * Así que el `x-data` REAL del HTML servido —el mismo objeto que Alpine evalúa
 * en el navegador— se ejecuta bajo node, con un reloj falso y dos regiones de
 * mentira que anotan todo lo que se les escribe. Lo que estas pruebas ejercitan
 * es el código que se envía al navegador, no una copia.
 */

/** Ruta del binario de node, o null si esta máquina no lo tiene. */
function nodeDeAvisos(): ?string
{
    $ruta = trim((string) shell_exec('command -v node 2>/dev/null'));

    return $ruta === '' ? null : $ruta;
}

/**
 * El objeto `x-data` REAL del host, ya sin entidades HTML: es JavaScript
 * literal, listo para evaluarse.
 */
function xDataDeAvisos(): string
{
    $html = avisosFlotantesRendidos('id="avisos"');

    expect((bool) preg_match('/x-data="(.*?)"\s*\n/s', $html, $m))->toBeTrue(
        'No se encontró el `x-data` del host en el HTML servido: el puente que hace ejecutable '.
        'el JavaScript del componente está roto y las pruebas de comportamiento no medirían nada.'
    );

    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

/**
 * Ejecuta `$codigo` con el host vivo en `s` y devuelve lo que retorne.
 *
 * Dentro del script hay, además de `s`:
 *   · `avanzar(ms)`  — adelanta el reloj falso y dispara los temporizadores vencidos;
 *   · `regiones`     — las dos regiones vivas de mentira (`.textContent`);
 *   · `escrito`      — todo lo que se escribió en cada región, en orden;
 *   · `focos`        — las llamadas a `document.body.focus()`.
 */
function ejecutaEnElHost(string $codigo, ?string $xData = null): mixed
{
    $node = nodeDeAvisos();

    expect($node)->not->toBeNull(
        'No hay node en esta máquina y el reloj del aviso es JavaScript: sin ejecutarlo, la '.
        'prueba no comprobaría nada. Instala node (el repo ya lo usa para la reja de a11y).'
    );

    $banco = <<<'JS'
    /* Reloj falso: los temporizadores no esperan, se ejecutan cuando `avanzar()`
       pasa por encima de su vencimiento. `Date.now()` sigue al mismo reloj porque
       el componente calcula con él lo que le resta a un aviso pausado. */
    let ahora = 0;
    let sec = 0;
    let pendientes = [];

    globalThis.setTimeout = (fn, ms) => {
        const id = ++sec;
        pendientes.push({ id, en: ahora + (Number(ms) || 0), fn });
        return id;
    };
    globalThis.clearTimeout = (id) => { pendientes = pendientes.filter(t => t.id !== id); };
    Date.now = () => ahora;

    const avanzar = (ms) => {
        const limite = ahora + ms;

        for (;;) {
            let prox = null;

            for (const t of pendientes) {
                if (t.en <= limite && (prox === null || t.en < prox.en || (t.en === prox.en && t.id < prox.id))) { prox = t; }
            }

            if (! prox) { break; }

            pendientes = pendientes.filter(t => t !== prox);
            ahora = prox.en;
            prox.fn();
        }

        ahora = limite;
    };

    const escrito = { polite: [], assertive: [] };
    const region = (clave) => {
        let valor = '';

        return {
            get textContent() { return valor; },
            set textContent(t) { valor = t; escrito[clave].push(t); },
        };
    };
    const regiones = { polite: region('polite'), assertive: region('assertive') };

    const focos = [];

    globalThis.window = {};
    globalThis.document = { activeElement: null, body: { focus() { focos.push('body'); } } };
    JS;

    $script = $banco."\n\n"
        ."const s = {$xData};\n"
        ."s.\$refs = regiones;\n"
        ."s.\$nextTick = (fn) => setTimeout(fn, 0);\n"
        ."s.\$root = { isConnected: true, querySelectorAll: () => [] };\n"
        ."s.init();\n\n"
        ."const out = (function () { {$codigo} })();\n"
        .'process.stdout.write(JSON.stringify(out === undefined ? null : out));';

    $archivo = tempnam(sys_get_temp_dir(), 'muni-toast-').'.js';
    file_put_contents($archivo, $script);

    $salida = [];
    $codigoSalida = 0;
    exec(escapeshellarg((string) $node).' '.escapeshellarg($archivo).' 2>&1', $salida, $codigoSalida);
    @unlink($archivo);

    $texto = implode("\n", $salida);

    expect($codigoSalida)->toBe(0, "El `x-data` del host no es JavaScript válido o reventó al ejecutarse:\n".$texto);

    return json_decode($texto, true, 512, JSON_THROW_ON_ERROR);
}

/** El host ejecutado con el `vidaDe()` de la 0.19, para comprobar que el candado muerde. */
function xDataDeAvisosConVidaVieja(): string
{
    $viejo = preg_replace(
        '/vidaDe\((\s*tone,\s*texto,\s*pedida\s*)\)\s*\{/',
        'vidaDe($1) { if (pedida !== null) { const n = Number(pedida); return Number.isFinite(n) && n > 0 ? n : 0; }',
        xDataDeAvisos(),
        1,
        $veces
    );

    expect($veces)->toBe(1,
        'La firma de `vidaDe(tone, texto, pedida)` cambió: la contraprueba ya no reintroduce el '.
        'defecto y dejó de medir si el candado muerde.'
    );

    return (string) $viejo;
}

it('un duration explícito no resucita el autocierre de danger ni de warn', function () {
    // EL DEFECTO QUE ESTA FICHA SE ABRIÓ PARA REPARAR, medido: con la rama
    // `if (pedida !== null) return n` por delante, un `danger` con `duration: 3000`
    // se autodestruía a los 3 s. El contrato del evento no cambió —`duration` sigue
    // documentado— así que cualquier consumidor que hoy ya lo pasa mantenía intacto
    // el incumplimiento de 2.2.1: el error se va antes de que el funcionario lo lea.
    $x = xDataDeAvisos();

    $vidas = ejecutaEnElHost(
        "return {
            dangerPedido: s.vidaDe('danger', 'Error. No se pudo guardar.', 3000),
            warnPedido: s.vidaDe('warn', 'Advertencia. Por vencer.', 800),
            dangerSolo: s.vidaDe('danger', 'Error.', null),
            warnSolo: s.vidaDe('warn', 'Advertencia.', null),
        };",
        $x
    );

    foreach ($vidas as $caso => $vida) {
        expect($vida)->toBe(0,
            "`vidaDe()` devuelve $vida ms en el caso `$caso`: los tonos `danger` y `warn` no se ".
            'autocierran NUNCA, pida lo que pida el emisor. Un error que se autodestruye es el '.
            'defecto original con otra ropa.'
        );
    }

    $vivos = ejecutaEnElHost(
        "s.push({ tone: 'danger', title: 'No se pudo guardar', message: 'El RUT ya tiene licencia clase B vigente.', duration: 3000 });
         const alInicio = s.items.length;
         avanzar(600000);
         return [alInicio, s.items.length];",
        $x
    );

    expect($vivos)->toBe([1, 1],
        'Un aviso `danger` con `duration: 3000` desapareció de la pila: el temporizador arrancó '.
        'igual. La persistencia tiene que valer sobre el aviso de verdad, no solo en el cálculo.'
    );
});

it('el candado del duration muerde: con el vidaDe de la 0.19 el danger se autocierra', function () {
    // Contraprueba SOBRE UNA COPIA en memoria del `x-data`, nunca sobre el archivo
    // real: si esta prueba pasara con el defecto reintroducido, la de arriba no
    // estaría midiendo nada.
    $roto = ejecutaEnElHost(
        "s.push({ tone: 'danger', message: 'El RUT ya tiene licencia clase B vigente.', duration: 3000 });
         avanzar(600000);
         return s.items.length;",
        xDataDeAvisosConVidaVieja()
    );

    expect($roto)->toBe(0,
        'Con la rama vieja `if (pedida !== null) return n` por delante, el `danger` con '.
        '`duration` SIGUE en la pila: el banco de node no reproduce el defecto y la prueba de '.
        'arriba está en verde por casualidad.'
    );
});

it('el piso de 5 s manda también sobre la duración que pide el emisor', function () {
    $x = xDataDeAvisos();

    $vidas = ejecutaEnElHost(
        "return {
            unMilisegundo: s.vidaDe('ok', 'Guardado.', 1),
            debajoDelPiso: s.vidaDe('info', 'Guardado.', 800),
            cero: s.vidaDe('ok', 'Guardado.', 0),
            basura: s.vidaDe('ok', 'Guardado.', 'pronto'),
            negativa: s.vidaDe('ok', 'Guardado.', -5),
            porEncimaDelPiso: s.vidaDe('ok', 'Guardado.', 12000),
        };",
        $x
    );

    expect($vidas['unMilisegundo'])->toBe(5000,
        '`duration: 1` deja el aviso 1 ms en pantalla: por debajo del piso de 5 s no se alcanza '.
        'a leer nada (WCAG 2.2 AA 2.2.1).'
    );
    expect($vidas['debajoDelPiso'])->toBe(5000, 'Una duración pedida por debajo del piso no se sube a 5 s.');
    expect($vidas['cero'])->toBe(0, '`duration: 0` dejó de significar «no autocerrar».');
    expect($vidas['basura'])->toBe(0, 'Una duración que no es número tiene que caer del lado seguro: no autocerrar.');
    expect($vidas['negativa'])->toBe(0, 'Una duración negativa tiene que caer del lado seguro: no autocerrar.');
    expect($vidas['porEncimaDelPiso'])->toBe(12000,
        'Una duración pedida por encima del piso ya no se respeta: el contrato del evento cambió '.
        'para los consumidores que hoy pasan `duration`.'
    );

    $ciclo = ejecutaEnElHost(
        "s.push({ tone: 'ok', message: 'Guardado.', duration: 1 });
         avanzar(4999);
         const antes = s.items.length;
         avanzar(2);
         return [antes, s.items.length];",
        $x
    );

    expect($ciclo)->toBe([1, 0], 'El aviso con `duration: 1` no vivió los 5 s del piso en la pila de verdad.');
});

it('sin duration, la vida escala con el largo del texto entre 5 y 20 segundos', function () {
    $vidas = ejecutaEnElHost(
        "return {
            corto: s.vidaDe('ok', 'Confirmación. Guardado.', null),
            medio: s.vidaDe('info', 'x'.repeat(200), null),
            larguisimo: s.vidaDe('info', 'x'.repeat(4000), null),
        };",
        xDataDeAvisos()
    );

    expect($vidas['corto'])->toBe(5000, 'Un aviso corto no llega al piso de 5 s.');
    expect($vidas['medio'])->toBe(11000, 'La escala por carácter (55 ms) dejó de aplicarse entre el piso y el techo.');
    expect($vidas['larguisimo'])->toBe(20000, 'Un aviso larguísimo pasa del techo de 20 s.');
});

it('la pila descarta el más viejo al pasar de cuatro avisos: se mide contando, no leyendo', function () {
    // `maximo: 4` declarado no es `maximo: 4` aplicado: borrar el `while` de `push()`
    // dejaba la prueba vieja —un grep del literal— en verde con la pantalla desbordada.
    $mensajes = ejecutaEnElHost(
        "for (let i = 0; i < 7; i++) { s.push({ tone: 'info', message: 'Aviso ' + i }); }
         return s.items.map(i => i.message);",
        xDataDeAvisos()
    );

    expect($mensajes)->toBe(['Aviso 3', 'Aviso 4', 'Aviso 5', 'Aviso 6'],
        'La pila no se queda con los cuatro últimos avisos: o no hay tope, o el tope descarta el '.
        'que no es. Una ráfaga de eventos desborda la pantalla.'
    );
});

it('una ráfaga de la misma prioridad anuncia TODOS los avisos, no solo el último', function () {
    // Dos errores de validación del mismo submit salen con microsegundos de
    // diferencia. Con el ciclo vaciar → esperar → escribir de un solo hueco, el
    // segundo pisaba al primero antes de que llegara a la región: el primer error
    // quedaba a la vista y no se anunciaba nunca. `announcer` tiene el mismo ciclo
    // pero atiende un mensaje a la vez; aquí se apilan hasta cuatro por diseño.
    $anunciado = ejecutaEnElHost(
        "s.push({ tone: 'danger', message: 'La fecha de nacimiento es obligatoria.' });
         s.push({ tone: 'danger', message: 'El RUT no es válido.' });
         s.push({ tone: 'ok', message: 'Borrador guardado.' });
         avanzar(60000);
         return { assertive: escrito.assertive.filter(t => t !== ''), polite: escrito.polite.filter(t => t !== '') };",
        xDataDeAvisos()
    );

    expect($anunciado['assertive'])->toBe(
        ['Error. La fecha de nacimiento es obligatoria.', 'Error. El RUT no es válido.'],
        'La región asertiva no recibió los dos errores en orden: una ráfaga de la misma prioridad '.
        'pierde anuncios y el lector se entera de uno solo.'
    );
    expect($anunciado['polite'])->toBe(['Confirmación. Borrador guardado.'],
        'La región cortés no recibió el aviso `ok` de la misma ráfaga.'
    );
});

/** El host ejecutado con el `anunciar()` de un solo hueco, el de la 0.19. */
function xDataDeAvisosConAnuncioViejo(): string
{
    $viejo = preg_replace(
        '/anunciar\(tone, texto\) \{.*?\n        \},/s',
        "anunciar(tone, texto) {\n".
        "            const clave = this.prioridadDe(tone);\n".
        "            const region = this.\$refs[clave];\n".
        "            if (! region) { return; }\n".
        "            clearTimeout(this.escrituras[clave]);\n".
        "            region.textContent = '';\n".
        "            this.escrituras[clave] = setTimeout(() => { region.textContent = texto; }, this.espera);\n".
        '        },',
        xDataDeAvisos(),
        1,
        $veces
    );

    expect($veces)->toBe(1,
        'La firma de `anunciar(tone, texto)` cambió: la contraprueba ya no reintroduce el defecto '.
        'y dejó de medir si el candado muerde.'
    );

    return (string) $viejo;
}

it('el candado de la ráfaga muerde: con el anuncio de un solo hueco el primer error se pierde', function () {
    // Contraprueba SOBRE UNA COPIA en memoria del `x-data`, nunca sobre el archivo
    // real. Mide las dos cosas de una vez: con el hueco único el primer error nunca
    // llega a la región, y el último se queda pegado ahí para siempre.
    $roto = ejecutaEnElHost(
        "s.push({ tone: 'danger', message: 'La fecha de nacimiento es obligatoria.' });
         s.push({ tone: 'danger', message: 'El RUT no es válido.' });
         avanzar(600000);
         return { anunciados: escrito.assertive.filter(t => t !== ''), pegado: regiones.assertive.textContent };",
        xDataDeAvisosConAnuncioViejo()
    );

    expect($roto['anunciados'])->toBe(['Error. El RUT no es válido.'],
        'Con el `anunciar()` de un solo hueco la región asertiva recibió los dos errores igual: el '.
        'banco de node no reproduce el defecto y la prueba de la ráfaga está en verde por casualidad.'
    );
    expect($roto['pegado'])->toBe('Error. El RUT no es válido.',
        'Con el `anunciar()` viejo la región se limpió sola: la prueba de la limpieza tardía no mide nada.'
    );
});

it('la región viva se limpia tarde: el texto no se queda pegado al cursor virtual', function () {
    // `announcer` lo documenta en su doctrina: la limpieza tardía evita que el nodo
    // se quede con una verdad vieja pegada. Quien recorra el DOM con el cursor
    // virtual media hora después no tiene por qué encontrar el aviso de entonces.
    $ciclo = ejecutaEnElHost(
        "s.push({ tone: 'ok', message: 'Guardado.' });
         avanzar(500);
         const conTexto = regiones.polite.textContent;
         avanzar(600000);
         return [conTexto, regiones.polite.textContent];",
        xDataDeAvisos()
    );

    expect($ciclo[0])->toBe('Confirmación. Guardado.', 'El texto del aviso no llegó a la región cortés.');
    expect($ciclo[1])->toBe('', 'La región viva se quedó con el texto pegado para siempre.');
});

it('el temporizador se pausa de verdad mientras el aviso está pausado y reanuda donde iba', function () {
    $x = xDataDeAvisos();

    $pausado = ejecutaEnElHost(
        "s.push({ tone: 'ok', message: 'Guardado.', duration: 6000 });
         const id = s.items[0].id;
         avanzar(2000);
         s.pausar(id);
         avanzar(600000);
         const durantePausa = s.items.length;
         s.reanudar(id);
         avanzar(3900);
         const casi = s.items.length;
         avanzar(200);
         return [durantePausa, casi, s.items.length];",
        $x
    );

    expect($pausado)->toBe([1, 1, 0],
        'El aviso no sobrevive a la pausa, o al reanudar no le quedan los 4 s que le faltaban: '.
        'sin pausa efectiva el autocierre incumple 2.2.1 (tiempo ajustable).'
    );

    // Dos pausas (puntero encima Y foco dentro) se sueltan de a una: reanudar una
    // sola no puede arrancar el reloj mientras la otra sigue.
    $doble = ejecutaEnElHost(
        "s.push({ tone: 'ok', message: 'Guardado.', duration: 6000 });
         const id = s.items[0].id;
         s.pausar(id); s.pausar(id);
         s.reanudar(id);
         avanzar(600000);
         const conUnaPausa = s.items.length;
         s.reanudar(id);
         avanzar(6001);
         return [conUnaPausa, s.items.length];",
        $x
    );

    expect($doble)->toBe([1, 0],
        'Soltar una de las dos pausas (puntero y foco) arrancó el reloj igual: al sacar el '.
        'puntero, un aviso que todavía tiene el foco dentro se autocierra bajo los dedos.'
    );
});

// ---------------------------------------------------------------------------
// 6. El banco de navegador
// ---------------------------------------------------------------------------

it('genera el banco de navegador en build/avisos-flotantes/banco.html', function () {
    // El comportamiento que de verdad importa —pausa al puntero y al foco,
    // persistencia de danger, Escape acotado, devolución del foco, texto en la
    // región viva— solo se puede medir con Alpine corriendo. Este banco es el
    // sujeto de esa medición (Playwright, Chromium y Firefox); `build/` está
    // ignorado por git. Alpine sale de `node_modules` o de `scripts/.cache`,
    // nunca de un CDN: la reja corre sin red.
    $alpine = null;

    foreach (array_merge(
        [__DIR__.'/../node_modules/alpinejs/dist/cdn.min.js'],
        array_filter(glob(__DIR__.'/../scripts/.cache/alpine-*.min.js') ?: [], fn (string $r) => ! str_contains(basename($r), 'alpine-focus-'))
    ) as $candidato) {
        if (is_file($candidato)) {
            $alpine = (string) file_get_contents($candidato);
            break;
        }
    }

    $css = (string) file_get_contents(__DIR__.'/../resources/css/muni-ui.css');
    $host = avisosFlotantesRendidos('id="avisos"');

    $disparos = [
        ['ok', 'Solicitud ingresada', 'Solicitud N.º 2026-4831 ingresada — derivada a Obras.', null],
        ['info', null, 'La agenda del sábado 12 está cerrada por mantención.', null],
        ['warn', 'Documento por vencer', 'La licencia clase B vence el 30 de septiembre.', null],
        ['danger', 'No se pudo guardar', 'El RUT ya tiene licencia clase B vigente.', null],
        ['ok', null, 'Guardado.', 0],
    ];

    $botones = '';

    foreach ($disparos as $i => [$tone, $title, $message, $duration]) {
        $detalle = json_encode(array_filter(
            ['tone' => $tone, 'title' => $title, 'message' => $message, 'duration' => $duration],
            fn ($v) => $v !== null
        ), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);

        $botones .= '<button type="button" id="disparo-'.$i.'" data-detalle=\''.$detalle.'\''
            .' onclick="window.dispatchEvent(new CustomEvent(\'muni-toast\', { detail: JSON.parse(this.dataset.detalle) }))">'
            .e($tone.($title ? ' · '.$title : '')).'</button> ';
    }

    $pagina = '<!doctype html><html lang="es" data-muni-theme="light"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>Banco · toast-host</title><style>'.$css.'</style>'
        .($alpine !== null ? '<script>'.$alpine.'</script>' : '<!-- SIN ALPINE -->')
        .'</head><body style="background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans);padding:24px;">'
        .'<h1>Banco de avisos flotantes</h1>'
        .'<label for="rut">RUT del titular</label> <input id="rut" name="rut" type="text"> '
        .$botones
        .$host
        .'</body></html>';

    $dir = __DIR__.'/../build/avisos-flotantes';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents($dir.'/banco.html', $pagina);

    expect(is_file($dir.'/banco.html'))->toBeTrue('No se pudo escribir el banco.');
    expect($alpine)->not->toBeNull(
        'No hay copia de Alpine en disco (`npm install` o `scripts/.cache`): el banco se '.
        'escribió sin hidratar y no sirve para medir la interacción.'
    );
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDeAvisos(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

it('en Chromium y Firefox: anuncia por la región que toca, no pierde la ráfaga, persiste danger, pausa por puntero y foco, y devuelve el foco', function () {
    // Lo de arriba lee el HTML que emite Blade y ejecuta el `x-data` bajo node con
    // un reloj falso. Ninguna de las dos cosas dice nada del funcionario: que el
    // texto ENTRE de verdad en la región viva, que el puntero encima pare el reloj,
    // que Escape no se escape a la ventana, que el foco aterrice en el × del aviso
    // que sigue vivo. Eso solo se ve con Alpine corriendo, y por eso existe
    // `tests/navegador/avisos-flotantes.py`: el resto del repo tiene su script de
    // navegador por componente interactivo y este no lo tenía.
    $python = pythonDeAvisos();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/avisos-flotantes.py').' '
        .escapeshellarg(__DIR__.'/../build/avisos-flotantes').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de los avisos flotantes falló:\n".implode("\n", $lineas));

    // Dos navegadores × dos temas: con la preferencia de movimiento la transición
    // del aviso computa 0 s, y sin ella existe (si no, la comprobación sería vacía).
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'transición(reduce)=0s'))))->toBe(4,
        "Los cuatro recorridos tienen que reportar la transición apagada con la preferencia:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, 'transición(no-preference)=0.16s'))))->toBe(4,
        "Sin la preferencia la transición tiene que existir en los cuatro recorridos:\n".implode("\n", $lineas));
});
