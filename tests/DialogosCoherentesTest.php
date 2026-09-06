<?php

use Illuminate\Support\Facades\Blade;

/*
 * COHERENCIA DE LOS DOS DIÁLOGOS DEL PAQUETE.
 *
 * `<x-muni::modal>` y `<x-muni::drawer>` son la misma pieza con dos formas: un
 * diálogo modal que atrapa el foco, se cierra con Escape y deja inerte el resto
 * de la página. Que uno resuelva algo y el otro no es peor que un defecto
 * suelto, porque el funcionario aprende un comportamiento y el sistema le
 * responde con dos.
 *
 * Estas pruebas se corren SIEMPRE contra los dos, con `dataset`, para que nada
 * se arregle en uno solo. Como en `FocoVisibleTest.php`, las aserciones van con
 * `expect(bool)->toBeTrue('mensaje')`: el segundo argumento de `toContain()` en
 * Pest es otra cadena a buscar, no un mensaje, y desactiva la aserción sin avisar.
 */

/** Los dos diálogos: nombre del componente => ruta de su Blade. */
function dialogos(): array
{
    return [
        'modal' => __DIR__.'/../resources/views/components/modal.blade.php',
        'drawer' => __DIR__.'/../resources/views/components/drawer.blade.php',
    ];
}

/** Render mínimo de un diálogo, con los atributos que se le quieran pasar. */
function renderDialogo(string $componente, string $atributos = ''): string
{
    return Blade::render(
        "<x-muni::{$componente} title=\"Detalle\" {$atributos}><p>Contenido</p></x-muni::{$componente}>"
    );
}

dataset('dialogos', ['modal', 'drawer']);

it('declara role="dialog" y aria-modal="true"', function (string $componente) {
    $html = renderDialogo($componente);

    expect(str_contains($html, 'role="dialog"'))->toBeTrue(
        "«{$componente}» no declara role=\"dialog\": el lector de pantalla no anuncia que se abrió un diálogo."
    );
    expect(str_contains($html, 'aria-modal="true"'))->toBeTrue(
        "«{$componente}» no declara aria-modal=\"true\": el lector sigue leyendo la página de atrás como si fuera alcanzable."
    );
})->with('dialogos');

it('se cierra con Escape y deja inerte el fondo mientras está abierto', function (string $componente) {
    $html = renderDialogo($componente);

    expect((bool) preg_match('/keydown\.escape\.window/', $html))->toBeTrue(
        "«{$componente}» no cierra con Escape: WCAG 2.2 2.1.2, quien navega con teclado queda encerrado."
    );
    expect(str_contains($html, 'x-trap.inert'))->toBeTrue(
        "«{$componente}» no marca inerte el fondo (`x-trap.inert`): Tab y el lector de pantalla se escapan del diálogo."
    );
})->with('dialogos');

it('no genera su id con uniqid(): respeta el id del consumidor', function (string $componente) {
    // Sin comentarios: nombrar uniqid() dentro de la explicación del arreglo no
    // es un defecto (mismo criterio que `FocoVisibleTest.php` con `outline:none`).
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(dialogos()[$componente]));

    expect(str_contains($fuente, 'uniqid('))->toBeFalse(
        "«{$componente}» arma el id del título con uniqid(): cambia en CADA render, así que bajo Livewire ".
        'el diff reemplaza nodos que no cambiaron y cualquier aria-labelledby externo apunta al vacío.'
    );

    $html = renderDialogo($componente, 'id="ficha-solicitud"');

    expect((bool) preg_match('/aria-labelledby="([^"]+)"/', $html, $m))->toBeTrue(
        "«{$componente}» no enlaza su título con aria-labelledby."
    );
    expect(str_starts_with($m[1], 'ficha-solicitud'))->toBeTrue(
        "«{$componente}» ignora el id que le pasó el consumidor («ficha-solicitud») y se inventa otro: ".
        "el id del título quedó «{$m[1]}»."
    );
    expect(str_contains($html, 'id="'.$m[1].'"'))->toBeTrue(
        "«{$componente}»: el aria-labelledby no coincide con el id de su título."
    );
})->with('dialogos');

it('da el mismo id en dos renders idénticos', function (string $componente) {
    preg_match('/aria-labelledby="([^"]+)"/', renderDialogo($componente), $uno);
    preg_match('/aria-labelledby="([^"]+)"/', renderDialogo($componente), $dos);

    expect($uno[1] ?? null)->not->toBeNull();
    expect($uno[1])->toBe(
        $dos[1] ?? null,
        "«{$componente}» cambia el id del título entre dos renders con las mismas props."
    );
})->with('dialogos');

it('mide sus transiciones con var(--muni-dur) y no con una duración fija', function (string $componente) {
    $css = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(dialogos()[$componente]));

    preg_match_all('#<style>(.*?)</style>#s', $css, $bloques);

    $fijas = [];

    foreach ($bloques[1] as $bloque) {
        preg_match_all('/transition\s*:\s*([^;}]+)/i', $bloque, $declaraciones);

        foreach ($declaraciones[1] as $valor) {
            // Una duración literal: `.28s`, `200ms`, `0.3s`. `var(--muni-dur)`
            // —incluso dentro de un calc()— es lo único aceptable, porque
            // muni-ui.css lo baja a 0ms con prefers-reduced-motion.
            if (preg_match('/(^|[\s,(])\d*\.?\d+m?s\b/i', $valor)) {
                $fijas[] = trim($valor);
            }
        }
    }

    expect($fijas)->toBe([], sprintf(
        '«%s» fija la duración de estas transiciones a mano, así que la animación sigue corriendo '.
        'aunque el usuario haya pedido movimiento reducido (muni-ui.css pone --muni-dur:0ms): %s',
        $componente, implode(' | ', $fijas)
    ));
})->with('dialogos');

it('da indicador de foco visible al botón de cerrar', function (string $componente) {
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(dialogos()[$componente]));

    expect((bool) preg_match('/:focus-visible[^{]*\{[^}]*outline\s*:\s*3px solid var\(--muni-focus/', $fuente))->toBeTrue(
        "«{$componente}» no le da un outline propio al botón × en :focus-visible: dentro del panel de ".
        'Filament el anillo de box-shadow se computa transparente y al tabular no aparece nada (WCAG 2.2 AA 2.4.7).'
    );
})->with('dialogos');

it('tiene nombre accesible aunque no le pasen título', function (string $componente) {
    $html = Blade::render("<x-muni::{$componente}><p>Contenido</p></x-muni::{$componente}>");

    // Camino 1: aria-labelledby que apunta a un elemento CON texto.
    $enlazaTitulo = preg_match('/aria-labelledby="([^"]+)"/', $html, $m)
        && preg_match('/id="'.preg_quote($m[1], '/').'"[^>]*>(.*?)</s', $html, $t)
        && trim(strip_tags($t[1])) !== '';

    // Camino 2: un aria-label propio del diálogo (el del botón × es «Cerrar»).
    preg_match_all('/aria-label="([^"]+)"/', $html, $etiquetas);
    $etiquetaPropia = array_values(array_filter($etiquetas[1], fn (string $v) => $v !== 'Cerrar'));

    expect($enlazaTitulo || $etiquetaPropia !== [])->toBeTrue(
        "«{$componente}» sin `title` se queda sin nombre accesible: su aria-labelledby apunta a un <h2> vacío ".
        'y no hay aria-label de respaldo, así que el lector anuncia «diálogo» y nada más.'
    );
})->with('dialogos');
