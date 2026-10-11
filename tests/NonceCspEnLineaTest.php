<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;

/*
 * Todo <style> y <script> en línea de un componente lleva el nonce CSP del anfitrión.
 *
 * Por qué: los sistemas con `style-src 'nonce-…'` bloquean los bloques en línea sin
 * nonce, y los componentes llevan su CSS dentro de la vista. Se perdían el anillo de
 * foco del botón, las cajas del OTP y la franja gob. El nonce es el mismo que imprime
 * el layout del anfitrión: `Vite::cspNonce()`.
 */

/** Etiquetas de apertura <style…> / <script…> que hay en un HTML. */
function etiquetasEnLinea(string $html): array
{
    // Los comentarios CSS citan etiquetas en prosa («un <style> suelto…»): no son etiquetas.
    $html = (string) preg_replace('#/\*.*?\*/#s', '', $html);
    preg_match_all('/<(?:style|script)\b[^>]*>/i', $html, $m);

    return $m[0];
}

function renderizarComponente(string $nombre): string
{
    $extra = propsObligatorias()[$nombre] ?? '';
    $etiqueta = trim("<x-muni::{$nombre} {$extra}>");

    return Blade::render("{$etiqueta}Contenido de prueba</x-muni::{$nombre}>");
}

it('imprime el nonce en cada <style> y <script> en línea', function (string $nombre) {
    Vite::useCspNonce('nonce-de-prueba');

    $sinNonce = array_values(array_filter(
        etiquetasEnLinea(renderizarComponente($nombre)),
        fn (string $etiqueta) => ! str_contains($etiqueta, ' nonce="nonce-de-prueba"')
    ));

    expect($sinNonce)->toBe([]);
})->with(componentesDelPaquete());

it('con nonce configurado, button, otp-input y gob-stripe emiten su <style> con él', function (string $nombre) {
    Vite::useCspNonce('nonce-de-prueba');

    $etiquetas = etiquetasEnLinea(renderizarComponente($nombre));

    expect($etiquetas)->not->toBeEmpty();
    expect(implode('', $etiquetas))->toContain('<style nonce="nonce-de-prueba">');
})->with(['button', 'otp-input', 'gob-stripe']);

it('sin nonce no imprime ningún atributo nonce (ni nonce="" vacío)', function (string $nombre) {
    // Vite::useCspNonce(null) GENERA un nonce al azar; sin llamarlo no hay ninguno.
    // El contenedor se recrea en cada prueba, así que no se arrastra el de otra.
    expect(preg_match('/\bnonce\s*=/i', renderizarComponente($nombre)))->toBe(0);
})->with(componentesDelPaquete());

it('ningún <style> ni <script> del fuente queda sin nonce, tampoco en ramas condicionales', function () {
    // El render cubre solo la rama que se ejecuta; esto lee el fuente de todas.
    $sinNonce = [];

    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') ?: [] as $ruta) {
        $fuente = preg_replace('/(?:\{\{--.*?--\}\}|<!--.*?-->)/s', '', (string) file_get_contents($ruta));

        foreach (etiquetasEnLinea((string) $fuente) as $etiqueta) {
            if (! str_contains($etiqueta, 'Nonce::attr()') && ! str_contains($etiqueta, 'nonce=')) {
                $sinNonce[] = basename($ruta).' → '.$etiqueta;
            }
        }
    }

    expect($sinNonce)->toBe([]);
});
