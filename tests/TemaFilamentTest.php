<?php

/** El CSS del tema, una sola vez. */
function temaFilament(): string
{
    return file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');
}

it('el tema corrige el contraste del título de tarjeta en oscuro', function () {
    expect(temaFilament())->toContain('.dark .fi-section-header-heading');
});

it('el tema acota el preview de video de FilePond', function () {
    expect(temaFilament())->toContain('has-video-preview');
});

/*
 * EL CANDADO QUE IMPORTA.
 *
 * Este archivo pisa a Filament: sus reglas llevan `!important` y las de Filament
 * vienen con `:where(.dark, .dark *)`, que tiene especificidad CERO a propósito.
 * Consecuencia: una regla de color pensada para el modo claro se aplica TAMBIÉN
 * en oscuro, y el texto queda sobre un fondo para el que no fue elegido.
 *
 * Ya pasó dos veces —la etiqueta de los KPI en 1,22:1 y la pestaña activa en
 * 1,52:1, contra un mínimo AA de 4,5:1— y las dos se descubrieron midiendo en un
 * sistema que consume el paquete, no acá. En una captura se ven «tenues», no
 * ausentes.
 *
 * En vez de comprobar una por una las reglas ya arregladas, se comprueba la
 * REGLA GENERAL: toda declaración de color para el modo claro necesita su
 * contraparte. Así el defecto no puede volver por una regla nueva.
 */
it('toda regla de color tiene su contraparte en modo oscuro', function () {
    $css = temaFilament();

    /*
     * Superficies que son OSCURAS EN LOS DOS MODOS: la barra lateral y su pie.
     * Sus colores están elegidos para fondo oscuro desde el principio, así que
     * exigirles una contraparte sería pedirles que cambien cuando no deben.
     * La lista es explícita a propósito: una clase nueva cae del lado de «hay
     * que mirarla», no del de «se asume bien».
     */
    $siempreOscuras = ['fi-sidebar', 'mg-sidebar'];

    // Fuera los comentarios ANTES de partir en reglas: si no, el bloque que
    // precede a un selector entra dentro de él y el mensaje de error sale con
    // un párrafo de prosa pegado al nombre de la clase.
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

    preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    $conColorClaro = [];
    $conContraparte = '';

    foreach ($reglas as [, $selector, $cuerpo]) {
        $selector = trim(preg_replace('/\s+/', ' ', $selector));

        if (! preg_match('/(^|[;{\s])color\s*:/', $cuerpo)) {
            continue;
        }

        if (str_contains($selector, '.dark')) {
            $conContraparte .= ' '.$selector;

            continue;
        }

        /*
         * Una regla que declara SU PROPIO fondo junto al color se basta a sí
         * misma: define su superficie y no depende de la de la página, así que
         * se ve igual en los dos modos. Es el caso del botón de quitar del
         * preview de video, que va en blanco sobre su propia pastilla negra
         * translúcida. Exigirle contraparte sería pedirle que cambie sin motivo.
         */
        if (preg_match('/(^|[;{\s])background(-color)?\s*:/', $cuerpo)) {
            continue;
        }

        $conColorClaro[] = $selector;
    }

    $sinContraparte = [];

    foreach ($conColorClaro as $selector) {
        preg_match_all('/\.[a-zA-Z0-9_-]+/', $selector, $clases);

        if ($clases[0] === []) {
            continue;
        }

        // Se mira el selector COMPLETO y no solo la última clase: en
        // `.fi-sidebar-group .fi-icon-btn` la que recibe el color es un icono
        // genérico, pero lo que decide si el fondo es oscuro es el ancestro.
        foreach ($siempreOscuras as $prefijo) {
            if (str_contains($selector, '.'.$prefijo)) {
                continue 2;
            }
        }

        // La última clase del selector es la que recibe el color.
        $clase = ltrim(end($clases[0]), '.');

        if (! str_contains($conContraparte, $clase)) {
            $sinContraparte[] = $selector;
        }
    }

    expect(array_values(array_unique($sinContraparte)))->toBe(
        [],
        'Estas reglas fijan color para el modo claro y no tienen contraparte `.dark`, '.
        'así que su color se aplicará también sobre el fondo oscuro: '.
        implode(' | ', array_unique($sinContraparte)),
    );
});
