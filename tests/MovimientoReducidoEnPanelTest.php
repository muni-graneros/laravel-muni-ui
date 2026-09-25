<?php

/*
|--------------------------------------------------------------------------
| EL MOVIMIENTO REDUCIDO TAMBIÉN EXISTE DENTRO DE UN PANEL
|--------------------------------------------------------------------------
|
| `muni-ui.css` baja `--muni-dur` y `--muni-dur-slow` a 0 ms bajo
| `prefers-reduced-motion: reduce` (DESIGN §6). Dentro de un panel Filament esa
| hoja no se carga: solo `muni-ui-filament.css` (DESIGN §7), que declaraba
| `--muni-dur:160ms` y bajaba ÚNICAMENTE `--muni-dur-slow`. Resultado, medido en
| Chromium y Firefox: `<x-muni::button>` computaba `0.16s` con la preferencia
| activa, y lo mismo toda transición de todo componente del paquete que no
| trajera su propia guardia, en los nueve sistemas municipales.
|
| Estas pruebas leen el texto de las dos hojas. La medición en navegador vive en
| `DatoSensibleTest` (el botón del control, en las cuatro páginas del banco y en
| los dos navegadores): ahí se exige 0 s en las ocho combinaciones.
*/

/**
 * Los tokens que baja el bloque de movimiento reducido sobre `:root`, nombre => valor.
 * Devuelve null si la hoja no trae el bloque.
 *
 * @return array<string, string>|null
 */
function mrpTokensReducidos(string $css): ?array
{
    $sinComentarios = (string) preg_replace('#/\*.*?\*/#s', '', $css);

    if (! preg_match('/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{\s*:root\s*\{([^}]*)\}\s*\}/', $sinComentarios, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    preg_match_all('/(--muni-[a-z0-9-]+)\s*:\s*([^;]+);?/', $m[1][0], $d, PREG_SET_ORDER);

    $tokens = [];

    foreach ($d as [, $nombre, $valor]) {
        $tokens[$nombre] = trim($valor);
    }

    ksort($tokens);

    return $tokens;
}

/** Posición en el texto sin comentarios: el bloque base que declara `--muni-dur` con duración, y el de movimiento reducido. */
function mrpPosiciones(string $css): array
{
    $sinComentarios = (string) preg_replace('#/\*.*?\*/#s', '', $css);

    preg_match('/--muni-dur\s*:\s*[1-9]\d*ms/', $sinComentarios, $base, PREG_OFFSET_CAPTURE);
    preg_match('/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{\s*:root\s*\{/', $sinComentarios, $reducido, PREG_OFFSET_CAPTURE);

    return [$base[0][1] ?? null, $reducido[0][1] ?? null];
}

it('la hoja del panel baja --muni-dur a 0 ms con movimiento reducido', function () {
    $tokens = mrpTokensReducidos(cssMuniUiFilament());

    expect($tokens)->not->toBeNull('muni-ui-filament.css no trae el bloque @media (prefers-reduced-motion: reduce) { :root { … } }.');

    expect($tokens['--muni-dur'] ?? null)->toBe('0ms',
        'muni-ui-filament.css —la única hoja que se carga dentro de un panel Filament (DESIGN §7)— deja '.
        '--muni-dur en 160 ms con prefers-reduced-motion: todo componente del paquete anima dentro del panel '.
        'aunque el funcionario pidió que no.'
    );
    expect($tokens['--muni-dur-slow'] ?? null)->toBe('0ms', 'muni-ui-filament.css no baja --muni-dur-slow a 0 ms.');
});

it('las dos hojas apagan exactamente los mismos tokens de movimiento', function () {
    $muni = mrpTokensReducidos(cssMuniUi());
    $panel = mrpTokensReducidos(cssMuniUiFilament());

    expect($muni)->not->toBeNull('muni-ui.css perdió su bloque de movimiento reducido.');

    // Un token de movimiento que se apague en una hoja y no en la otra es
    // exactamente el defecto que esta prueba vino a cerrar, con otro nombre.
    expect($panel)->toBe($muni, 'El bloque de movimiento reducido difiere entre muni-ui.css y muni-ui-filament.css.');
});

it('en las dos hojas el bloque de movimiento reducido viene DESPUÉS del :root que declara la duración', function (string $nombre) {
    $css = $nombre === 'muni-ui.css' ? cssMuniUi() : cssMuniUiFilament();

    [$base, $reducido] = mrpPosiciones($css);

    expect($base)->not->toBeNull("{$nombre}: no se encontró la declaración base de --muni-dur.");
    expect($reducido)->not->toBeNull("{$nombre}: no se encontró el bloque de movimiento reducido.");

    // Los dos son `:root` (0,1,0): a igual especificidad gana el que viene
    // después. Delante del base, el 0 ms perdería contra los 160 ms.
    expect($reducido > $base)->toBeTrue("{$nombre}: el bloque de movimiento reducido va antes que el :root base y pierde la cascada.");
})->with(['muni-ui.css', 'muni-ui-filament.css']);

it('ningún bloque de tema del panel rearma --muni-dur', function () {
    $sinComentarios = (string) preg_replace('#/\*.*?\*/#s', '', cssMuniUiFilament());
    $oscuro = bloqueTrasAncla($sinComentarios, "\n.dark {");

    expect($oscuro)->not->toBe('', 'No se encontró el bloque .dark de muni-ui-filament.css.');

    // Redeclarado en `.dark`, dentro de un contenedor oscuro volvería a valer
    // 160 ms: el bloque de movimiento reducido solo alcanza a `:root`.
    expect((bool) preg_match('/--muni-dur(?:-slow)?\s*:/', $oscuro))->toBeFalse('muni-ui-filament.css redeclara --muni-dur en .dark.');
});
