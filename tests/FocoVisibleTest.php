<?php

/**
 * EL CANDADO DEL FOCO VISIBLE.
 *
 * Los componentes del paquete apagaban el contorno del navegador (`outline:none`)
 * y dejaban el indicador de foco en manos de una `box-shadow` (`--muni-ring`).
 * Medido con getComputedStyle dentro del panel de Filament de licencias-graneros,
 * esa sombra se computaba `oklab(0 0 0 / 0) 0px 0px 0px 0px`: al tabular no
 * aparecía NADA. Es WCAG 2.2 AA 2.4.7 (foco visible) y 1.4.11 (3:1 para
 * indicadores no textuales), obligatorio en sistemas del Estado chileno por el
 * Decreto N°1/2015 SEGPRES.
 *
 * Se comprueba la REGLA GENERAL, no los componentes uno por uno: toda regla de
 * foco declara su propio `outline`. Así el defecto no puede volver por un
 * componente nuevo — que fue justo como entraron `switch` y `segmented`, que
 * nunca escribieron `outline:none` pero dependían solo de la sombra.
 *
 * `toContain('valor', 'mensaje')` NO sirve para esto: en Pest el segundo
 * argumento es otra cadena a buscar, no un mensaje, y la aserción se desactiva
 * sin avisar. Por eso todo va con `expect(bool)->toBeTrue('mensaje')`.
 */

/** Los `*.blade.php` de componentes, ruta => contenido. */
function componentesBlade(): array
{
    $vistas = [];

    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') ?: [] as $ruta) {
        $vistas[basename($ruta)] = file_get_contents($ruta);
    }

    return $vistas;
}

it('ningún componente apaga el contorno del navegador', function () {
    $culpables = [];

    foreach (componentesBlade() as $archivo => $html) {
        // Sin comentarios: un `outline:none` citado dentro de una explicación no
        // es un defecto, y si no se quitan el mensaje sale con prosa pegada.
        $html = (string) preg_replace('#/\*.*?\*/#s', '', $html);

        // Cubre el `<style>` y también el atributo `style` inline, que fue donde
        // se escondió el de la paleta de comandos.
        if (preg_match_all('/outline\s*:\s*(none|0)\b/i', $html, $m)) {
            $culpables[] = $archivo.' ('.count($m[0]).')';
        }
    }

    expect($culpables)->toBe(
        [],
        'Estos componentes apagan el contorno del navegador. Si la box-shadow del '.
        'anillo se pierde —y dentro de Filament se pierde— el teclado se queda sin '.
        'indicador de foco: '.implode(' | ', $culpables)
    );
});

it('toda regla de foco declara su propio outline visible', function () {
    $sinOutline = [];

    foreach (componentesBlade() as $archivo => $html) {
        preg_match_all('#<style>(.*?)</style>#s', $html, $bloques);

        foreach ($bloques[1] as $css) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

            preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);

            foreach ($reglas as [, $selector, $cuerpo]) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $selector));

                if (! str_contains($selector, ':focus')) {
                    continue;
                }

                // Un outline REAL: `outline:` con algo que no sea none/0.
                $tieneOutline = (bool) preg_match('/(^|[;{\s])outline\s*:\s*(?!none|0\b)[^;]+/i', $cuerpo);

                if (! $tieneOutline) {
                    $sinOutline[] = $archivo.' → '.$selector;
                }
            }
        }
    }

    expect($sinOutline)->toBe(
        [],
        'Estas reglas de foco no declaran outline y confían el indicador a la '.
        'box-shadow del anillo, que dentro del panel de Filament se computa '.
        'transparente: '.implode(' | ', $sinOutline)
    );
});

it('el color del foco cumple 3:1 contra toda superficie, en claro y en oscuro', function () {
    /*
     * WCAG 2.2 AA 1.4.11: un indicador no textual necesita 3:1 contra lo que
     * tiene al lado. El outline se dibuja FUERA del control, así que lo que
     * tiene al lado es la superficie de la página o de la tarjeta, no el borde
     * del control. Por eso se mide contra las cuatro superficies de cada tema.
     */
    $superficies = ['bg', 'surface', 'surface-2', 'surface-3'];

    // Los `--mg-*` del tema de Filament, para poder resolver `var(--mg-x)`.
    preg_match_all('/(--mg-[a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})/i', cssMuniUiFilament(), $mg, PREG_SET_ORDER);
    $paleta = [];
    foreach ($mg as [, $nombre, $hex]) {
        $paleta[$nombre] = strtolower($hex);
    }

    /** Resuelve un valor de token que puede ser hex o `var(--mg-x)`. */
    $resolver = function (?string $valor) use ($paleta): ?string {
        if ($valor === null) {
            return null;
        }

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) {
            return strtolower($valor);
        }

        if (preg_match('/var\(\s*(--mg-[a-z0-9-]+)\s*\)/i', $valor, $m)) {
            return $paleta[$m[1]] ?? null;
        }

        return null;
    };

    /** El valor crudo de `--muni-{$nombre}` en un bloque (hex o `var(...)`). */
    $crudo = function (string $bloque, string $nombre): ?string {
        if (preg_match('/--muni-'.preg_quote($nombre, '/').':\s*([^;]+);/', $bloque, $m)) {
            return trim($m[1]);
        }

        return null;
    };

    $bloques = [
        'muni-ui.css · claro' => bloqueTrasAncla(cssMuniUi(), 'Valores LIGHT (default)'),
        'muni-ui.css · oscuro' => bloqueTrasAncla(cssMuniUi(), 'Regla 2: activadores EXPLÍCITOS de dark'),
        'muni-ui.css · light forzado' => bloqueTrasAncla(cssMuniUi(), '[data-muni-theme="light"]'),
        'filament · claro' => bloqueTrasAncla(cssMuniUiFilament(), ':root{'),
        'filament · oscuro' => bloqueTrasAncla(cssMuniUiFilament(), '.dark {'),
    ];

    foreach ($bloques as $nombre => $bloque) {
        $foco = $resolver($crudo($bloque, 'focus'));

        expect($foco !== null)->toBeTrue(
            "El bloque «{$nombre}» no define --muni-focus: los componentes se quedan con el respaldo neutro"
        );

        foreach ($superficies as $superficie) {
            $fondo = $resolver($crudo($bloque, $superficie));

            if ($fondo === null) {
                continue; // El tema de Filament no enumera --muni-panel; no se inventa un valor.
            }

            $ratio = ratioContraste($foco, $fondo);

            expect($ratio)->toBeGreaterThanOrEqual(3.0, sprintf(
                '%s: --muni-focus (%s) sobre --muni-%s (%s) da %.2f:1, bajo el 3:1 de WCAG 2.2 AA 1.4.11',
                $nombre, $foco, $superficie, $fondo, $ratio
            ));
        }
    }
});

it('el respaldo neutro del outline se ve aunque no exista ningún token', function () {
    /*
     * Los nueve sistemas anfitriones tienen publicada en public/vendor/muni-ui/
     * una copia VIEJA de filament.css. Por eso el color del outline se escribe
     * como `var(--muni-focus, var(--muni-accent, #767676))`: si el token no
     * existe no puede quedar en una declaración inválida —que el navegador
     * descarta y deja el outline en su valor inicial, o sea ninguno—, sino en un
     * gris que se ve sobre papel y sobre fondo oscuro.
     */
    $respaldo = '#767676';

    $extremos = [
        '#ffffff' => 'papel claro',
        '#f8f5ec' => 'papel del panel',
        '#0b0f14' => 'fondo oscuro base',
        '#1c2333' => 'la superficie oscura más clara',
    ];

    foreach ($extremos as $fondo => $etiqueta) {
        $ratio = ratioContraste($respaldo, $fondo);

        expect($ratio)->toBeGreaterThanOrEqual(3.0, sprintf(
            'El respaldo %s sobre %s (%s) da %.2f:1, bajo el 3:1 exigido',
            $respaldo, $fondo, $etiqueta, $ratio
        ));
    }

    foreach (componentesBlade() as $archivo => $html) {
        if (! str_contains($html, 'var(--muni-focus')) {
            continue;
        }

        expect(str_contains($html, 'var(--muni-focus, var(--muni-accent, '.$respaldo.'))'))->toBeTrue(
            "«{$archivo}» usa --muni-focus sin la cadena de respaldo: en un sistema con el CSS ".
            'publicado desactualizado la declaración queda inválida y el outline desaparece'
        );
    }
});

it('todo token que leen los componentes está definido en los DOS CSS del paquete', function () {
    /*
     * El hueco que este candado cierra: los componentes se resuelven desde
     * vendor/ y llegan con `composer update`, pero el CSS hay que publicarlo a
     * mano en cada sistema. Un `var(--muni-x)` nuevo en un componente que solo
     * exista en uno de los dos CSS se ve bien en la mitad del ecosistema y
     * vacío en la otra, sin un solo error en consola. Ya pasó: el tema de
     * Filament llegó a producción sin 19 tokens que los componentes leían.
     *
     * Se exigen los DOS archivos porque una aplicación puede cargar solo uno:
     * el panel de Filament carga `filament.css` y la parte pública `muni-ui.css`.
     */
    $definidos = function (string $css): array {
        preg_match_all('/(--muni-[a-z0-9-]+)\s*:/i', $css, $m);

        return array_unique($m[1]);
    };

    $hojas = [
        'resources/css/muni-ui.css' => $definidos(cssMuniUi()),
        'resources/css/muni-ui-filament.css' => $definidos(cssMuniUiFilament()),
    ];

    $leidos = [];

    foreach (componentesBlade() as $archivo => $html) {
        preg_match_all('/var\(\s*(--muni-[a-z0-9-]+)/i', $html, $m);

        foreach ($m[1] as $token) {
            $leidos[$token][$archivo] = true;
        }
    }

    expect($leidos)->not->toBeEmpty('No se encontró ningún token en los componentes: el barrido está roto');

    foreach ($leidos as $token => $usuarios) {
        foreach ($hojas as $hoja => $tokens) {
            expect(in_array($token, $tokens, true))->toBeTrue(sprintf(
                '«%s» no está definido en %s y lo leen: %s. Quedará vacío y sin error en consola.',
                $token, $hoja, implode(', ', array_keys($usuarios))
            ));
        }
    }
});
