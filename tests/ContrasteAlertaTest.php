<?php

use Illuminate\Support\Facades\Blade;

/**
 * D12 — el cuerpo de `<x-muni::alert>` no llegaba a 4,5:1 sobre el fondo de su
 * propio estado.
 *
 * El título de la alerta siempre se pintó con el `fg` del tono —un par medido—,
 * pero el cuerpo iba con `--muni-muted`, que está calibrado contra las
 * SUPERFICIES (`surface`, `bg`, `surface-3`) y no contra los fondos de estado.
 * Medido en Chromium 151, Firefox 153 y WebKit 26.5: `info` 4,26 y `danger`
 * 4,15 en el panel claro; `ok` 4,41, `warn` 4,33 e `info` 4,37 en el panel
 * oscuro; `ok` 4,32 en la base oscura. Todos bajo el 4,5:1 de 1.4.3.
 *
 * Por qué sobrevivió: la vitrina renderiza `alert` con UN solo tono, `warn`, y
 * `warn` era justamente el que pasaba en claro. Los otros cuatro nunca se
 * midieron. Por eso esta prueba no elige tonos a mano: recorre los CINCO que
 * el componente sabe rendir —los cuatro del mapa más el camino de respaldo de
 * un tono desconocido— en los DOS temas y en las DOS hojas.
 *
 * Y no mide tokens elegidos por el autor de la prueba: LEE del HTML rendido qué
 * par fg/bg usa el componente y mide ESE. Si alguien vuelve a pintar el cuerpo
 * con un token no calibrado, el candado cae acá aunque el nombre del token
 * cambie.
 */

/**
 * Las cuatro paletas del paquete: dos hojas × dos temas. Hay que medir las dos
 * hojas porque dentro de un panel Filament solo se carga la segunda (DESIGN §7)
 * y declara valores DISTINTOS para los mismos tokens.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function paletasDelPaquete(): array
{
    return [
        'muni-ui.css · claro' => [cssMuniUi(), 'Valores LIGHT (default)'],
        'muni-ui.css · oscuro' => [cssMuniUi(), 'Regla 2: activadores EXPLÍCITOS de dark'],
        'muni-ui-filament.css · claro' => [cssMuniUiFilament(), 'Tema Filament municipal'],
        'muni-ui-filament.css · oscuro' => [cssMuniUiFilament(), 'En oscuro mandan los tonos institucionales'],
    ];
}

/** Los cinco tonos que `alert` sabe rendir: los cuatro del mapa y el respaldo. */
function tonosDeAlerta(): array
{
    return ['ok', 'warn', 'danger', 'info', 'tono-inexistente'];
}

/**
 * Renderiza una alerta y devuelve los NOMBRES de token que usa de verdad:
 * el fondo del recuadro, el color del título y el color del cuerpo.
 *
 * @return array{fondo: ?string, titulo: ?string, cuerpo: ?string}
 */
function tokensDeLaAlerta(string $tono): array
{
    $html = Blade::render(
        '<x-muni::alert tone="'.$tono.'" title="Título">Cuerpo de la alerta</x-muni::alert>'
    );

    $lee = function (string $patron) use ($html): ?string {
        return preg_match($patron, $html, $m) ? $m[1] : null;
    };

    return [
        'fondo' => $lee('/background:\s*var\(--muni-([a-z0-9-]+)\)/i'),
        'titulo' => $lee('/class="muni-alert__titulo"[^>]*color:\s*var\(--muni-([a-z0-9-]+)\)/i'),
        'cuerpo' => $lee('/class="muni-alert__cuerpo"[^>]*color:\s*var\(--muni-([a-z0-9-]+)\)/i'),
    ];
}

it('la alerta marca su título y su cuerpo, y ninguno de los dos se pinta con --muni-muted', function () {
    foreach (tonosDeAlerta() as $tono) {
        $tokens = tokensDeLaAlerta($tono);

        foreach (['fondo', 'titulo', 'cuerpo'] as $parte) {
            expect($tokens[$parte])->not->toBeNull(
                "No se pudo leer el token del {$parte} de la alerta «{$tono}». La prueba mide el HTML ".
                'rendido: si se le quitó la clase de anclaje, deja de haber candado.'
            );
        }

        // `--muni-muted` está calibrado contra las superficies, no contra los
        // fondos de estado. Sobre ok/warn/info/danger cae entre 4,15 y 4,53.
        expect($tokens['cuerpo'])->not->toBe('muted',
            "El cuerpo de la alerta «{$tono}» volvió a --muni-muted: ese token no está medido contra ".
            'los fondos de estado y en cinco de las dieciséis combinaciones no llega a 4,5:1.'
        );
    }
});

it('el cuerpo y el título de los cinco tonos llegan a 4,5:1 en los dos temas y en las DOS hojas', function () {
    $fallos = [];

    foreach (tonosDeAlerta() as $tono) {
        $tokens = tokensDeLaAlerta($tono);

        foreach (paletasDelPaquete() as $paleta => [$hoja, $ancla]) {
            $bloque = bloqueTrasAncla($hoja, $ancla);

            expect($bloque)->not->toBe('', "No se encontró el bloque «{$ancla}» en la hoja de «{$paleta}».");

            $fondo = tokenColor($bloque, $hoja, $tokens['fondo']);

            expect($fondo)->not->toBeNull(
                "«{$paleta}» no declara --muni-{$tokens['fondo']}: el fondo del tono «{$tono}» queda sin color."
            );

            foreach (['titulo', 'cuerpo'] as $parte) {
                $texto = tokenColor($bloque, $hoja, $tokens[$parte]);

                expect($texto)->not->toBeNull(
                    "«{$paleta}» no declara --muni-{$tokens[$parte]}, que es con lo que se pinta el ".
                    "{$parte} del tono «{$tono}»."
                );

                $ratio = ratioContraste($texto, $fondo);

                if ($ratio < 4.5) {
                    $fallos[] = sprintf(
                        '%s · %s · %s: --muni-%s (%s) sobre --muni-%s (%s) = %.2f:1',
                        $paleta, $tono, $parte, $tokens[$parte], $texto, $tokens['fondo'], $fondo, $ratio
                    );
                }
            }
        }
    }

    expect($fallos)->toBe([],
        "Texto de alerta bajo 4,5:1 (WCAG 2.2 AA, 1.4.3):\n  ".implode("\n  ", $fallos)
    );
});

/*
 * El candado de arriba mide lo que el componente usa HOY. Este mide el par
 * fg/bg de los cuatro tonos aunque el componente deje de usarlo: es el par que
 * el paquete publica como «texto sobre fondo de estado» y que cualquier host
 * puede usar directo. Si alguien aclara un `fg` para que «se vea más suave»,
 * cae acá antes de llegar a una pantalla.
 */
it('el par fg/bg de cada tono es texto legible en las cuatro paletas', function () {
    $fallos = [];

    foreach (paletasDelPaquete() as $paleta => [$hoja, $ancla]) {
        $bloque = bloqueTrasAncla($hoja, $ancla);

        foreach (['ok', 'warn', 'danger', 'info'] as $tono) {
            $fg = tokenColor($bloque, $hoja, "{$tono}-fg");
            $bg = tokenColor($bloque, $hoja, "{$tono}-bg");

            expect($fg)->not->toBeNull("«{$paleta}» no declara --muni-{$tono}-fg.");
            expect($bg)->not->toBeNull("«{$paleta}» no declara --muni-{$tono}-bg.");

            $ratio = ratioContraste($fg, $bg);

            if ($ratio < 4.5) {
                $fallos[] = sprintf('%s · %s-fg (%s) sobre %s-bg (%s) = %.2f:1', $paleta, $tono, $fg, $tono, $bg, $ratio);
            }
        }
    }

    expect($fallos)->toBe([], "Par fg/bg bajo 4,5:1:\n  ".implode("\n  ", $fallos));
});
