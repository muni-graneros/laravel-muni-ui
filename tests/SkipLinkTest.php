<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/**
 * EL ENLACE DE SALTO AL CONTENIDO (WCAG 2.2 nivel A, 2.4.1 «Evitar bloques»).
 *
 * Hoy, en el panel de Atención al Vecino, quien navega con teclado tabula por los
 * ~15 ítems del menú en CADA página antes de llegar al primer campo. Es criterio
 * de nivel A: por el Decreto N°1/2015 de SEGPRES eso es incumplimiento legal, no
 * deuda de diseño.
 *
 * Lo que estos candados vigilan, además del render:
 *
 *  1. El enlace NO puede ocultarse con `display:none` ni `visibility:hidden`: eso
 *     lo saca del árbol de accesibilidad y deja de ser alcanzable con Tab, con lo
 *     que el componente existe y no sirve para nada. La técnica correcta es el
 *     recorte (`clip-path`) o sacarlo de la pantalla, y traerlo con `:focus`.
 *  2. El componente no controla su destino, así que el cableado en los tres
 *     armazones es parte del trabajo: un ancla a un `<main>` sin `tabindex="-1"`
 *     mueve el scroll pero NO el punto de lectura en varios navegadores, que es
 *     el error clásico del patrón.
 *
 * `toContain('valor', 'mensaje')` NO sirve acá: en Pest el segundo argumento es
 * otra cadena a buscar, no un mensaje. Por eso todo va con
 * `expect(bool)->toBeTrue('mensaje')`.
 */

/** El contenido de los bloques `<style>` de un HTML ya renderizado. */
function estilosDe(string $html): string
{
    preg_match_all('#<style>(.*?)</style>#s', $html, $m);

    return implode("\n", $m[1]);
}

it('rinde un ancla al contenido principal con el texto por defecto en español', function () {
    $html = Blade::render('<x-muni::skip-link />');

    expect(str_contains($html, 'href="#muni-contenido"'))->toBeTrue(
        'El enlace de salto no apunta a #muni-contenido, que es el destino por defecto del paquete.'
    );

    expect((bool) preg_match('/<a\b[^>]*href="#muni-contenido"[^>]*>\s*(.*?)\s*<\/a>/s', $html, $m))->toBeTrue(
        'El componente no emite un <a> real: un salto que no es enlace no lo alcanza el teclado.'
    );

    expect(trim($m[1]))->toBe('Saltar al contenido principal');
});

it('la prop target cambia el href sin tocar nada más', function () {
    $html = Blade::render('<x-muni::skip-link target="#formulario" />');

    expect(str_contains($html, 'href="#formulario"'))->toBeTrue(
        'La prop `target` no cambia el href: el componente queda atado a un id fijo.'
    );
    expect(str_contains($html, 'href="#muni-contenido"'))->toBeFalse(
        'El destino por defecto sigue emitiéndose junto con el que pidió el consumidor.'
    );
});

it('el slot reemplaza el texto visible', function () {
    $html = Blade::render('<x-muni::skip-link>Ir al contenido</x-muni::skip-link>');

    expect((bool) preg_match('/<a\b[^>]*>\s*(.*?)\s*<\/a>/s', $html, $m))->toBeTrue();
    expect(trim($m[1]))->toBe('Ir al contenido');
});

it('el enlace se oculta sin salir del árbol de accesibilidad', function () {
    $css = estilosDe(Blade::render('<x-muni::skip-link />'));
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

    expect($css !== '')->toBeTrue('El componente no trae bloque <style>: no hay regla de ocultamiento que revisar.');

    expect((bool) preg_match('/display\s*:\s*none/i', $css))->toBeFalse(
        '`display:none` saca el enlace del árbol de accesibilidad: deja de ser alcanzable con Tab '.
        'y el componente pasa a ser decorativo.'
    );
    expect((bool) preg_match('/visibility\s*:\s*hidden/i', $css))->toBeFalse(
        '`visibility:hidden` hace exactamente lo mismo que display:none para el teclado y el lector de pantalla.'
    );

    $recortado = (bool) preg_match('/clip-path\s*:/i', $css) || (bool) preg_match('/\bclip\s*:/i', $css);
    $fueraDePantalla = (bool) preg_match('/(left|top)\s*:\s*-\d/i', $css);

    expect($recortado || $fueraDePantalla)->toBeTrue(
        'El enlace no se oculta ni por recorte (clip-path) ni fuera de pantalla: o se ve siempre '.
        'sobre el contenido, o se ocultó de una forma que el teclado no alcanza.'
    );
});

it('el foco lo devuelve a la pantalla con un outline visible', function () {
    $css = estilosDe(Blade::render('<x-muni::skip-link />'));
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

    preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    $conFoco = array_values(array_filter(
        $reglas,
        fn (array $r) => str_contains($r[1], ':focus')
    ));

    expect($conFoco)->not->toBeEmpty(
        'No hay ninguna regla :focus: el enlace nunca se hace visible al tabular.'
    );

    $cuerpo = implode(' ', array_column($conFoco, 2));

    // El indicador REAL es el outline: dentro de Filament la box-shadow se computa
    // transparente (ver --muni-focus en muni-ui.css).
    expect(str_contains($cuerpo, 'outline: 3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeTrue(
        'La regla de foco no declara el outline con la cadena de respaldo del paquete.'
    );

    // Volver a la pantalla: deshacer el recorte y/o la posición negativa.
    $vuelve = (bool) preg_match('/clip-path\s*:\s*none/i', $cuerpo)
        || (bool) preg_match('/(left|top)\s*:\s*\d/i', $cuerpo)
        || (bool) preg_match('/transform\s*:/i', $cuerpo);

    expect($vuelve)->toBeTrue(
        'La regla de foco no devuelve el enlace a la pantalla: recibe el foco invisible, '.
        'que es peor que no tenerlo (el usuario de teclado queda perdido).'
    );
});

it('los tres armazones emiten el enlace como primer elemento del body', function () {
    $shells = [
        'app-shell' => '<x-muni::app-shell system="Panel" />',
        'dashboard-shell' => '<x-muni::dashboard-shell system="Panel" />',
        'auth-shell' => '<x-muni::auth-shell system="Panel" />',
    ];

    foreach ($shells as $nombre => $plantilla) {
        $html = Blade::render($plantilla);

        expect((bool) preg_match('/<body[^>]*>\s*(<[^>]+>)/s', $html, $m))->toBeTrue(
            "«{$nombre}» no emite un <body> reconocible."
        );

        expect((bool) preg_match('/^<a\b[^>]*href="#muni-contenido"/', $m[1]))->toBeTrue(
            "«{$nombre}» no abre el <body> con el enlace de salto (abre con «{$m[1]}»). Si va después de ".
            'la barra institucional o del menú lateral, el orden de tabulación lo deja inútil.'
        );
    }
});

it('el <main> de los tres armazones es un destino de foco de verdad', function () {
    $shells = [
        'app-shell' => '<x-muni::app-shell system="Panel" />',
        'dashboard-shell' => '<x-muni::dashboard-shell system="Panel" />',
        'auth-shell' => '<x-muni::auth-shell system="Panel" />',
    ];

    foreach ($shells as $nombre => $plantilla) {
        $html = Blade::render($plantilla);

        expect((bool) preg_match('/<main\b[^>]*>/', $html, $m))->toBeTrue("«{$nombre}» no emite <main>.");

        expect(str_contains($m[0], 'id="muni-contenido"'))->toBeTrue(
            "El <main> de «{$nombre}» no lleva id=\"muni-contenido\": el enlace de salto apunta a la nada."
        );
        expect(str_contains($m[0], 'tabindex="-1"'))->toBeTrue(
            "El <main> de «{$nombre}» no lleva tabindex=\"-1\": el salto mueve el scroll pero no el ".
            'punto de lectura, así que el siguiente Tab vuelve al menú.'
        );
    }
});

it('el destino no queda tapado por la cabecera fija', function () {
    // `.muni-ds__top` (dashboard-shell) y el topbar de app-shell son sticky: sin
    // scroll-margin-top el foco aterriza DEBAJO de la cabecera y no se ve.
    foreach (['app-shell', 'dashboard-shell'] as $nombre) {
        $fuente = file_get_contents(__DIR__."/../resources/views/components/{$nombre}.blade.php");

        expect((bool) preg_match('/scroll-margin-top\s*:\s*var\(--muni-topbar-h\)/', (string) $fuente))->toBeTrue(
            "«{$nombre}» no reserva scroll-margin-top: var(--muni-topbar-h) para el destino del salto."
        );
    }
});
