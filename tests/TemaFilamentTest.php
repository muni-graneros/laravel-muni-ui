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

/*
 * EL PUENTE HACIA LOS COMPONENTES.
 *
 * Los componentes <x-muni::*> leen tokens «--muni-*». El tema del panel solo
 * definía los «--mg-*», así que dentro de Filament esos componentes salían con
 * las variables vacías: la barra de chart-bar se dibujaba con el alto correcto
 * y «background:none», y el esqueleto de carga quedaba invisible. Sin un solo
 * error en consola — por eso los paneles del ecosistema no los usaban.
 */
/**
 * El contenido de un bloque CSS, desde su selector hasta la primera llave que
 * lo cierra.
 *
 * Hace falta porque buscar el token en TODO el archivo daba un falso verde: los
 * mismos nombres se redefinen en el bloque `.dark`, así que quitarlos del
 * `:root` no rompía nada. Comprobado quitando `--muni-text` a mano.
 */
function bloqueDelTema(string $selector): string
{
    $tema = temaFilament();
    $inicio = mb_strpos($tema, $selector);

    if ($inicio === false) {
        return '';
    }

    $fin = mb_strpos($tema, '}', $inicio);

    return mb_substr($tema, $inicio, $fin === false ? null : $fin - $inicio);
}

it('el tema define los tokens que leen los componentes', function () {
    $tema = bloqueDelTema(':root{');

    /*
     * DERIVADO, no a mano. La lista literal que tenía este test vigilaba 18
     * nombres mientras los componentes ya leían 46: agregar un `var(--muni-x)`
     * nuevo a un componente pasaba en verde aunque el tema nunca lo definiera.
     * Se barren TODOS los `resources/views/components/*.blade.php` en vez de
     * confiar en que quien agrega un token también actualice esta lista.
     */
    $vistas = glob(__DIR__.'/../resources/views/components/*.blade.php') ?: [];

    $leidos = [];

    foreach ($vistas as $vista) {
        preg_match_all('/var\(--muni-[a-z0-9-]+/', file_get_contents($vista), $coincidencias);
        $leidos = [...$leidos, ...$coincidencias[0]];
    }

    $obligatorios = array_values(array_unique(array_map(
        fn (string $var) => substr($var, 4), // 'var(--muni-x' → '--muni-x'
        $leidos,
    )));

    foreach ($obligatorios as $token) {
        // `toContain` con un segundo argumento busca OTRA cadena, no muestra un
        // mensaje: hay que preguntar por el booleano para poder explicar el fallo.
        expect(str_contains($tema, $token.':'))->toBeTrue(
            "El tema no define «{$token}»: los componentes que lo leen se verán rotos y sin avisar"
        );
    }
});

it('los tokens de estado cambian en modo oscuro', function () {

    // No es cosmético: como TEXTO sobre el papel del panel, los tonos del
    // cinturón institucional dan 1,62 (lima), 1,76 (oro) y 1,65 (celeste),
    // contra el 4,5:1 que exige WCAG AA. En claro van versiones oscurecidas y
    // en oscuro los institucionales, que ahí rinden 9:1.
    $bloqueOscuro = bloqueDelTema('.dark {');

    foreach (['--muni-ok-fg', '--muni-warn-fg', '--muni-info-fg', '--muni-danger-fg', '--muni-accent'] as $token) {
        expect(str_contains($bloqueOscuro, $token.':'))->toBeTrue(
            "«{$token}» no se redefine en oscuro: quedaría el valor pensado para fondo claro"
        );
    }
});

it('la animación de entrada no arranca invisible (LCP)', function () {
    // `.fi-wi/.fi-section/.fi-ta` cubren casi toda pantalla de los 8 paneles.
    // Animar su `opacity` desde 0 hace que el navegador no cuente el contenido
    // como pintado hasta que la animación llega a 60%: hasta 0,81s de retraso
    // acumulado con el escalonado por `nth-child`. El desplazamiento (transform)
    // se queda: no afecta cuándo el contenido se considera "pintado".
    $keyframe = bloqueDelTema('@keyframes mg-fall{');

    // `toContain` con un segundo argumento busca OTRA cadena, no muestra un
    // mensaje (ver el mismo candado más arriba): hay que preguntar por el
    // booleano para poder explicar el fallo.
    expect(str_contains($keyframe, 'opacity:0'))->toBeFalse(
        'El keyframe mg-fall arranca en opacity:0: el elemento LCP tarda hasta 0,81s en pintarse.'
    );
});
