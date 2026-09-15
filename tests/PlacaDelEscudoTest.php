<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| LA PLACA DEL ESCUDO SE TEMATIZA
|--------------------------------------------------------------------------
|
| `topbar` y `auth-shell` pintaban el recuadro del escudo con dos literales,
| `background:#fff` y `color:#0b0f14`, sin token ni contraparte oscura. En la
| central de cámaras, de noche y con el tema oscuro, eso es un rectángulo blanco
| brillante en la cabecera: la única superficie clara de toda la pantalla.
|
| La placa sigue siendo clara también en oscuro, y no por gusto: el escudo es un
| PNG con el contorno en petróleo casi negro (#00404c) y sobre la superficie
| oscura ese contorno desaparece. Lo que cambia es que en oscuro pasa a un gris
| apagado, que ya no encandila y conserva el contorno por encima de 3:1.
|
| Se mide en las DOS hojas y en todas sus ramas: 30 de los 32 tokens de color
| difieren entre `muni-ui.css` y `muni-ui-filament.css`, y dentro de un panel
| Filament solo se carga la segunda (DESIGN §7).
|
| gob-escudo NO gana una variante en negativo (`srcDark`). La corrección del juez
| la deja bloqueada por terceros: no existe un escudo en negativo autorizado por el
| municipio, y en 0.x una prop publicada ya no se puede quitar (DESIGN §10). Además,
| mientras la placa siga clara en oscuro, un escudo en negativo quedaría encima de
| ella, justo el caso donde se pierde: las dos cosas se diseñan juntas cuando exista
| la imagen.
*/

/** El contorno del escudo: el petróleo institucional oscuro, que es el de su PNG. */
function placaContornoDelEscudo(): string
{
    return (string) tokenHex(bloqueTrasAncla(cssMuniUi(), 'Identidad institucional'), 'gob-petroleo-dark');
}

/** Hoja => [rama => bloque recortado]. */
function placaRamas(): array
{
    $ui = cssMuniUi();
    $fil = cssMuniUiFilament();

    return [
        'muni-ui.css' => [
            'claro (:root)' => bloqueTrasAncla($ui, 'Valores LIGHT (default)'),
            'oscuro del sistema operativo' => bloqueTrasAncla($ui, 'Regla 1: preferencia del OS'),
            'oscuro explícito' => bloqueTrasAncla($ui, 'Regla 2: activadores EXPLÍCITOS de dark'),
            'claro congelado' => bloqueTrasAncla($ui, 'Regla 3: override EXPLÍCITO a light'),
            'impresión' => bloqueTrasAncla($ui, '@media print {'),
        ],
        'muni-ui-filament.css' => [
            'claro (:root)' => bloqueTrasAncla($fil, 'Tema Filament municipal'),
            'oscuro (.dark)' => bloqueTrasAncla($fil, 'En oscuro mandan los tonos institucionales'),
            'impresión' => bloqueTrasAncla($fil, '@media print{'),
        ],
    ];
}

/** El fuente de una vista sin comentarios de Blade ni de CSS. */
function placaFuente(string $componente): string
{
    $fuente = (string) file_get_contents(__DIR__."/../resources/views/components/{$componente}.blade.php");

    return (string) preg_replace(['#\{\{--.*?--\}\}#s', '#/\*.*?\*/#s'], '', $fuente);
}

it('las dos hojas declaran la placa y su texto en todas las ramas de tema', function () {
    expect(placaContornoDelEscudo())->toBe('#00404c');

    foreach (placaRamas() as $hoja => $ramas) {
        foreach ($ramas as $rama => $bloque) {
            expect($bloque)->not->toBe('', "«{$hoja}»: no se encontró la rama {$rama}; la prueba no mira nada.");

            foreach (['logo-plate', 'logo-plate-fg'] as $token) {
                expect(tokenHex($bloque, $token))->not->toBeNull(
                    "«{$hoja}», rama {$rama}: falta --muni-{$token} como hex. Sin él la placa hereda el valor ".
                    'de otra rama (o ninguno) y el texto de respaldo queda sobre un fondo que no le corresponde.'
                );
            }
        }
    }
});

it('el texto de respaldo pasa 4,5:1 sobre la placa y el contorno del escudo 3:1, en cada rama', function () {
    $contorno = placaContornoDelEscudo();
    $fallos = [];

    foreach (placaRamas() as $hoja => $ramas) {
        foreach ($ramas as $rama => $bloque) {
            $placa = (string) tokenHex($bloque, 'logo-plate');
            $texto = (string) tokenHex($bloque, 'logo-plate-fg');

            if (ratioContraste($texto, $placa) < 4.5) {
                $fallos[] = sprintf('%s / %s: texto %s sobre placa %s = %.2f:1 (mínimo 4,5)', $hoja, $rama, $texto, $placa, ratioContraste($texto, $placa));
            }

            // 1.4.11: el contorno es lo que dibuja la silueta del escudo.
            if (ratioContraste($contorno, $placa) < 3.0) {
                $fallos[] = sprintf('%s / %s: contorno %s sobre placa %s = %.2f:1 (mínimo 3)', $hoja, $rama, $contorno, $placa, ratioContraste($contorno, $placa));
            }
        }
    }

    expect($fallos)->toBe([], "Placa del escudo bajo el mínimo:\n  ".implode("\n  ", $fallos));
});

it('en oscuro la placa deja de ser blanca, y en papel lo es', function () {
    foreach (placaRamas() as $hoja => $ramas) {
        foreach ($ramas as $rama => $bloque) {
            $placa = (string) tokenHex($bloque, 'logo-plate');
            $oscura = str_contains($rama, 'oscuro');

            if ($oscura) {
                // Más apagada que el blanco, pero clara: el escudo no tiene versión en negativo.
                expect(luminanciaRelativa($placa))->toBeLessThan(0.75,
                    "«{$hoja}», {$rama}: la placa ({$placa}) sigue encandilando como un recuadro blanco."
                );
                expect(luminanciaRelativa($placa))->toBeGreaterThan(0.4,
                    "«{$hoja}», {$rama}: la placa ({$placa}) se oscureció tanto que el contorno del escudo se pierde."
                );
            } else {
                expect($placa)->toBe('#ffffff', "«{$hoja}», {$rama}: en claro y en papel la placa es blanca.");
            }
        }
    }
});

it('las ramas gemelas de muni-ui.css declaran los mismos valores (DESIGN §3)', function () {
    $ramas = placaRamas()['muni-ui.css'];

    foreach (['logo-plate', 'logo-plate-fg'] as $token) {
        expect(tokenHex($ramas['claro (:root)'], $token))->toBe(tokenHex($ramas['claro congelado'], $token),
            "--muni-{$token}: :root y [data-muni-theme=\"light\"] divergen; un sistema con tema fijo verá otro escudo."
        );
        expect(tokenHex($ramas['oscuro del sistema operativo'], $token))->toBe(tokenHex($ramas['oscuro explícito'], $token),
            "--muni-{$token}: el oscuro del sistema operativo y el explícito divergen; en la raíz ganaría el del sistema."
        );
    }
});

it('topbar y auth-shell pintan la placa con los tokens, sin literales', function () {
    foreach (['topbar', 'auth-shell'] as $componente) {
        $fuente = placaFuente($componente);

        foreach (['#fff', '#0b0f14'] as $literal) {
            expect(str_contains($fuente, $literal))->toBeFalse(
                "«{$componente}» sigue escribiendo {$literal} en la placa del escudo: no tiene contraparte oscura."
            );
        }

        expect(str_contains($fuente, 'background:var(--muni-logo-plate,'))->toBeTrue(
            "«{$componente}» no lee --muni-logo-plate para el fondo de la placa."
        );
        expect(str_contains($fuente, 'color:var(--muni-logo-plate-fg,'))->toBeTrue(
            "«{$componente}» no lee --muni-logo-plate-fg para el texto de respaldo."
        );
    }

    $html = Blade::render('<x-muni::topbar system="Central de cámaras" />');
    expect(str_contains($html, 'background:var(--muni-logo-plate, var(--muni-surface))'))->toBeTrue(
        'El respaldo de la placa tiene que ser un token con dos ramas: un sistema con la hoja vieja publicada no la tiene.'
    );
    expect(str_contains($html, '>GRA</span>'))->toBeTrue('Sin logo, el topbar dejó de emitir el texto de respaldo.');

    $auth = Blade::render('<x-muni::auth-shell system="Atención al Vecino" />');
    expect(str_contains($auth, 'color:var(--muni-logo-plate-fg, var(--muni-text))'))->toBeTrue(
        'auth-shell no emite el texto de respaldo con el token de la placa.'
    );
});

it('gob-escudo sigue siendo una sola imagen, sin variante en negativo mientras no exista una autorizada', function () {
    $html = Blade::render('<x-muni::gob-escudo size="26" />');

    expect(substr_count($html, '<img'))->toBe(1);
    expect(str_contains($html, 'alt="Escudo de la Municipalidad de Graneros"'))->toBeTrue();
    expect(str_contains($html, '<style'))->toBeFalse('gob-escudo no necesita ningún estilo propio.');

    preg_match('/@props\s*\((.*?)\)\s*$/ms', placaFuente('gob-escudo'), $m);
    $props = (string) ($m[1] ?? '');

    expect($props)->not->toBe('', 'No se encontró el @props de gob-escudo; la prueba no mira nada.');
    expect((bool) preg_match('/[\'"](srcDark|src-dark|src_dark|darkSrc)[\'"]/', $props))->toBeFalse(
        'gob-escudo declara una variante en negativo. Está bloqueada por terceros (GAP-ANALYSIS, corrección '.
        'del juez, paso 3): no hay escudo en negativo autorizado, en 0.x la prop ya no se puede quitar, y '.
        'topbar/auth-shell la pondrían sobre la placa clara en oscuro.'
    );
});

it('el escudo dentro del topbar va sobre la placa tematizada, una sola vez', function () {
    $html = Blade::render(
        '<x-muni::topbar system="Central de cámaras"><x-slot:logo><x-muni::gob-escudo size="26" /></x-slot:logo></x-muni::topbar>'
    );

    expect(substr_count($html, 'alt="Escudo de la Municipalidad de Graneros"'))->toBe(1);
    expect((bool) preg_match('#background:var\(--muni-logo-plate, var\(--muni-surface\)\);[^"]*"\s*>\s*<img#', $html))->toBeTrue(
        'El escudo tiene que quedar directamente dentro de la placa que lee --muni-logo-plate.'
    );
    expect(str_contains($html, '>GRA</span>'))->toBeFalse('Con logo, el texto de respaldo no se emite.');
});
