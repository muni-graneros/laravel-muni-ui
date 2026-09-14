<?php

use Illuminate\Support\Facades\Blade;

/*
 * Ficha «alert» de docs/GAP-ANALYSIS.md, mitad aprobada por el juez.
 *
 * Tres defectos del mismo archivo:
 *
 * 1) EL ROL LO DECIDÍA EL TONO. `danger` salía con `role="alert"` y todo lo
 *    demás con `role="status"`, así que una nota permanente («Adjunte
 *    fotocopia de la cédula por ambos lados») quedaba declarada como mensaje
 *    de estado, y tres notas en una página se anunciaban las tres al entrar.
 *    Peor: el `role` iba escrito FUERA de `$attributes`, de modo que el
 *    consumidor que pasaba el suyo obtenía dos atributos `role` y ganaba el
 *    del componente. Ahora `role` es una prop con default `null`: sin ella no
 *    hay región viva, y el consumidor declara `alert`, `status` o `note`
 *    cuando el mensaje de verdad lo amerite.
 *
 * 2) EL MISMO GLIFO PARA LOS CUATRO TONOS. Un círculo con «i» para ok, warn,
 *    danger e info: el estado quedaba comunicado solo por el color, que es
 *    justo lo que 1.4.1 prohíbe. Ahora cada tono trae su propio dibujo.
 *
 * 3) `{!! $icon !!}` IMPRIMÍA HTML CRUDO. Si algún sistema le pasaba un dato
 *    del vecino, era XSS. Ahora `icon` acepta un NOMBRE del catálogo
 *    (ok/warn/danger/info); un SVG propio va por el slot `icon`, que es
 *    marcado escrito por el desarrollador, igual que el cuerpo.
 *
 * El contraste del cuerpo (D12) ya lo vigila ContrasteAlertaTest; acá se mide
 * el del glifo, que es un objeto gráfico y le aplica 1.4.11 (3:1).
 */

/** Renderiza una alerta con las props dadas. */
function alertaRendida(string $props, string $cuerpo = 'Cuerpo de la alerta'): string
{
    return Blade::render("<x-muni::alert {$props}>{$cuerpo}</x-muni::alert>");
}

/** La etiqueta de apertura del recuadro raíz, con todos sus atributos. */
function etiquetaRaizDeAlerta(string $html): string
{
    $html = ltrim($html);

    expect(str_starts_with($html, '<div'))->toBeTrue('La alerta ya no empieza por su <div> raíz.');

    return substr($html, 0, (int) strpos($html, '>') + 1);
}

/** El contenido del contenedor del glifo (lo que hay entre el <span> oculto y su cierre). */
function glifoDeAlerta(string $html): ?string
{
    if (preg_match('/<span class="muni-alert__icono"[^>]*>(.*?)<\/span>/s', $html, $m)) {
        return trim($m[1]);
    }

    return null;
}

/** El nombre del token con el que se pinta el glifo, leído del HTML rendido. */
function tokenDelGlifo(string $html): ?string
{
    if (preg_match('/class="muni-alert__icono"[^>]*color:\s*var\(--muni-([a-z0-9-]+)\)/i', $html, $m)) {
        return $m[1];
    }

    return null;
}

/** La fuente del componente sin comentarios de Blade ni de CSS: lo que se compila. */
function fuenteDeAlertaSinComentarios(): string
{
    $fuente = file_get_contents(__DIR__.'/../resources/views/components/alert.blade.php');
    $fuente = preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return (string) preg_replace('/\/\*.*?\*\//s', '', (string) $fuente);
}

it('sin `role` ninguno de los cinco tonos declara región viva', function () {
    foreach (tonosDeAlerta() as $tono) {
        $raiz = etiquetaRaizDeAlerta(alertaRendida('tone="'.$tono.'" title="Nota"'));

        expect((bool) preg_match('/\brole=/', $raiz))->toBeFalse(
            "La alerta «{$tono}» sale con `role` sin que el consumidor lo pida: una nota fija ".
            'volvería a anunciarse al cargar la página.'
        );
        expect((bool) preg_match('/\baria-live=/', $raiz))->toBeFalse(
            "La alerta «{$tono}» sale con `aria-live` sin que el consumidor lo pida."
        );
    }
});

it('el consumidor declara el rol y sale una sola vez, en el recuadro raíz', function () {
    foreach (['alert', 'status', 'note'] as $rol) {
        $raiz = etiquetaRaizDeAlerta(alertaRendida('tone="danger" role="'.$rol.'"'));

        expect(str_contains($raiz, 'role="'.$rol.'"'))->toBeTrue(
            "El `role=\"{$rol}\"` que pasó el consumidor no llegó al recuadro raíz."
        );
        expect(substr_count($raiz, 'role='))->toBe(1,
            'El recuadro raíz lleva más de un atributo `role`: el fijo del componente sigue duplicando '.
            'al del consumidor y en HTML gana el primero.'
        );
    }
});

it('los atributos del consumidor siguen llegando al recuadro raíz', function () {
    $raiz = etiquetaRaizDeAlerta(alertaRendida('tone="warn" class="mfa-simulador" id="aviso-mfa"'));

    expect(str_contains($raiz, 'class="mfa-simulador"'))->toBeTrue('La `class` del consumidor se perdió.');
    expect(str_contains($raiz, 'id="aviso-mfa"'))->toBeTrue('El `id` del consumidor se perdió.');
});

it('cada tono dibuja un glifo distinto, oculto al lector y pintado con el fg del tono', function () {
    $glifos = [];

    foreach (['ok', 'warn', 'danger', 'info'] as $tono) {
        $html = alertaRendida('tone="'.$tono.'"');
        $glifo = glifoDeAlerta($html);

        expect($glifo)->not->toBeNull("La alerta «{$tono}» no marca su glifo con la clase de anclaje.");
        expect(str_starts_with((string) $glifo, '<svg'))->toBeTrue("El glifo de «{$tono}» no es un SVG.");
        expect(str_contains((string) $glifo, 'currentColor'))->toBeTrue(
            "El glifo de «{$tono}» no hereda el color del tono: se pintaría con un color propio."
        );
        expect((bool) preg_match('/<span class="muni-alert__icono"[^>]*\baria-hidden="true"/', $html))->toBeTrue(
            "El glifo de «{$tono}» no está oculto al lector de pantalla: es decorativo, el texto ya lo dice."
        );
        expect(tokenDelGlifo($html))->toBe("{$tono}-fg",
            "El glifo de «{$tono}» no se pinta con --muni-{$tono}-fg, que es el par medido contra su fondo."
        );

        $glifos[$tono] = $glifo;
    }

    expect(count(array_unique($glifos)))->toBe(4,
        'Dos tonos comparten el mismo dibujo: el estado vuelve a quedar comunicado solo por el color (1.4.1).'
    );

    // Un tono desconocido cae en `info` también en el dibujo, no solo en el color.
    expect(glifoDeAlerta(alertaRendida('tone="tono-inexistente"')))->toBe($glifos['info']);
});

it('el glifo de cada tono llega a 3:1 sobre su fondo en los dos temas y en las DOS hojas', function () {
    $fallos = [];

    foreach (['ok', 'warn', 'danger', 'info'] as $tono) {
        $html = alertaRendida('tone="'.$tono.'"');
        $tokenGlifo = tokenDelGlifo($html);

        preg_match('/background:\s*var\(--muni-([a-z0-9-]+)\)/i', $html, $m);
        $tokenFondo = $m[1] ?? null;

        expect($tokenGlifo)->not->toBeNull("No se pudo leer el token del glifo de «{$tono}».");
        expect($tokenFondo)->not->toBeNull("No se pudo leer el token del fondo de «{$tono}».");

        foreach (paletasDelPaquete() as $paleta => [$hoja, $ancla]) {
            $bloque = bloqueTrasAncla($hoja, $ancla);
            $fg = tokenColor($bloque, $hoja, $tokenGlifo);
            $bg = tokenColor($bloque, $hoja, $tokenFondo);

            expect($fg)->not->toBeNull("«{$paleta}» no declara --muni-{$tokenGlifo}.");
            expect($bg)->not->toBeNull("«{$paleta}» no declara --muni-{$tokenFondo}.");

            $ratio = ratioContraste($fg, $bg);

            if ($ratio < 3) {
                $fallos[] = sprintf('%s · %s: --muni-%s (%s) sobre --muni-%s (%s) = %.2f:1', $paleta, $tono, $tokenGlifo, $fg, $tokenFondo, $bg, $ratio);
            }
        }
    }

    expect($fallos)->toBe([], "Glifo de alerta bajo 3:1 (WCAG 2.2 AA, 1.4.11):\n  ".implode("\n  ", $fallos));
});

it('`icon` acepta un nombre del catálogo y lo pinta con el color del tono', function () {
    $html = alertaRendida('tone="info" icon="warn"');

    expect(glifoDeAlerta($html))->toBe(glifoDeAlerta(alertaRendida('tone="warn"')),
        '`icon="warn"` sobre una alerta info no dibuja el triángulo del catálogo.'
    );
    expect(tokenDelGlifo($html))->toBe('info-fg', 'El glifo elegido a mano dejó de pintarse con el fg del tono.');
});

it('`icon` con HTML crudo no se imprime: ni como prop ni como texto', function () {
    $intentos = [
        ':icon="\'<svg onload=alert(1)></svg>\'"' => 'onload=alert(1)',
        'icon="<img src=x onerror=alert(1)>"' => 'onerror=alert(1)',
    ];

    foreach ($intentos as $props => $huella) {
        $html = alertaRendida('tone="ok" '.$props);

        expect(str_contains($html, $huella))->toBeFalse(
            "`{$props}` llegó al HTML: `icon` vuelve a imprimir lo que le pasan. Si algún sistema le ".
            'da un dato del vecino, es XSS.'
        );
        expect(glifoDeAlerta($html))->toBe(glifoDeAlerta(alertaRendida('tone="ok"')),
            'Un nombre que no está en el catálogo debe caer en el glifo del tono, no en nada.'
        );
    }
});

it('el slot `icon` sigue aceptando un SVG propio del desarrollador', function () {
    // El espacio tras `</x-slot:icon>` no es cosmético: pegado al texto, Blade
    // lee `@endslotCuerpo` como una directiva que no existe y el slot se derrama
    // dentro del cuerpo.
    $html = Blade::render(
        '<x-muni::alert tone="info"><x-slot:icon><svg data-propio="1" viewBox="0 0 20 20"></svg></x-slot:icon> Cuerpo</x-muni::alert>'
    );

    expect(str_contains((string) glifoDeAlerta($html), 'data-propio="1"'))->toBeTrue(
        'El slot `icon` dejó de rendir el SVG propio: la ampliación tenía que ser aditiva.'
    );
});

it('un slot `icon` vacío cae en el glifo del tono, no deja el recuadro sin dibujo', function () {
    // Pasa cuando una plantilla deja el slot preparado y lo rellena por condición:
    // `<x-slot:icon>@if ($x)…@endif</x-slot:icon>`. Sin dibujo, el tono vuelve a
    // quedar comunicado solo por el color.
    $html = Blade::render('<x-muni::alert tone="danger"><x-slot:icon>   </x-slot:icon> Cuerpo</x-muni::alert>');
    $glifo = glifoDeAlerta($html);

    // Se exige el SVG y no solo la igualdad: dos `null` también son iguales, y
    // así la prueba pasaba contra el archivo viejo, que no marca el glifo.
    expect(str_starts_with((string) $glifo, '<svg'))->toBeTrue(
        'Un `<x-slot:icon>` en blanco dejó la alerta sin glifo en vez de caer en el del tono.'
    );
    expect($glifo)->toBe(glifoDeAlerta(alertaRendida('tone="danger"')),
        'El respaldo del slot en blanco no es el glifo del tono.'
    );
});

it('la fuente ya no imprime `$icon` sin escapar ni fija el rol fuera de la bolsa', function () {
    $fuente = fuenteDeAlertaSinComentarios();

    expect(str_contains($fuente, '!! $icon'))->toBeFalse(
        'alert.blade.php volvió a imprimir `$icon` con `{!! !!}`: el catálogo por nombre existe para no hacerlo.'
    );
    expect((bool) preg_match('/\brole="\{\{/', $fuente))->toBeFalse(
        'alert.blade.php volvió a escribir `role` a mano fuera de `$attributes`: se duplica con el del consumidor.'
    );
});

/** Ruta del binario de python3, o null si esta máquina no lo tiene. */
function rutaDePython(): ?string
{
    $ruta = trim((string) shell_exec('command -v python3 2>/dev/null'));

    return $ruta === '' ? null : $ruta;
}

/**
 * La entrada de `alert` tal como la escribiría `npm run registro` HOY, leyendo el
 * componente del disco. No se lee `registry.json`: ese archivo se regenera y lo
 * que importa es que la fuente le dé al generador los datos correctos.
 *
 * @return array<string, mixed>
 */
function entradaDeAlertEnElRegistro(): array
{
    $python = rutaDePython();

    expect($python)->not->toBeNull(
        'No hay python3 en esta máquina y el registro se genera con scripts/gen-registry.py: sin '.
        'ejecutarlo, la prueba no comprobaría nada. El repo ya lo exige para `npm run registro` y la reja de a11y.'
    );

    $salida = tempnam(sys_get_temp_dir(), 'muni-registro-').'.json';
    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/../scripts/gen-registry.py').' '
        .escapeshellarg(__DIR__.'/..').' '.escapeshellarg($salida).' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "scripts/gen-registry.py reventó:\n".implode("\n", $lineas));

    $registro = json_decode((string) file_get_contents($salida), true, 512, JSON_THROW_ON_ERROR);
    @unlink($salida);

    foreach ($registro['components'] as $componente) {
        if ($componente['name'] === 'alert') {
            return $componente;
        }
    }

    throw new RuntimeException('El generador del registro no encontró `alert`.');
}

it('lo que el generador del registro lee del disco describe la alerta que hay, no la de antes', function () {
    // registry.json describía el componente viejo: sin `role`, con el token del
    // gris apagado que ya no usa y con un slot fantasma `t` (venía de `$t ??=`).
    // El generador saca todo por grep de la fuente, así que la fuente tiene que
    // dárselo bien: un comentario que nombre un token cuenta como uso, y una
    // ranura solo se detecta por `isset($x)` o `$x ??`.
    $alert = entradaDeAlertEnElRegistro();

    $props = array_column($alert['props'], null, 'name');
    $slots = array_column($alert['slots'], 'name');

    expect(isset($props['role']))->toBeTrue('La prop `role` no llega al registro: el consumidor no sabría que existe.');
    expect($props['role']['default'] ?? null)->toBe('null', 'El registro no dice que `role` viene sin valor (sin región viva).');
    expect(isset($props['icon']))->toBeTrue('La prop `icon` desapareció del registro.');

    expect(in_array('icon', $slots, true))->toBeTrue(
        'El slot `icon` no aparece en el registro: el generador solo lo detecta por `isset($icon)` o `$icon ??`, '.
        'y es la única vía documentada para un SVG propio.'
    );
    expect(in_array('t', $slots, true))->toBeFalse('El slot fantasma `t` sigue en el registro.');
    expect(count(array_filter($slots, fn (string $s) => ! in_array($s, ['default', 'icon'], true))))->toBe(0,
        'El registro inventa slots que no existen: '.implode(', ', $slots).'. Cada `$x ??` de la fuente cuenta como ranura.'
    );

    expect(in_array('--muni-muted', $alert['tokens'], true))->toBeFalse(
        'El registro sigue listando el token del gris apagado: la alerta ya no lo usa, pero el generador lo '.
        'encuentra por grep aunque solo esté en un comentario.'
    );

    $descripcion = mb_strtolower($alert['description']);

    expect(str_contains($descripcion, 'aviso') && str_contains($descripcion, 'tono'))->toBeTrue(
        'La descripción del registro no dice qué es el componente; sale de la primera frase del primer '.
        'comentario Blade, que hoy es: «'.$alert['description'].'».'
    );
});
