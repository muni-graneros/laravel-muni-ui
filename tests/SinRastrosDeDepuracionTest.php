<?php

/*
 * Candado contra los rastros de depuración que se quedan pegados.
 *
 * Origen: una revisión automática encontró en `ajustes-cuenta.blade.php`, mientras
 * se escribía, un `file_put_contents(getenv('TMPDIR').'/dbg.txt', ...)` que volcaba
 * `$sessions` y `$attributes->all()` enteros —sesiones activas del funcionario, o
 * sea PII y contexto de seguridad— a un archivo de /tmp legible por cualquiera del
 * sistema, más tres `HOLA` sueltos en el marcado.
 *
 * Nunca llegó a commitearse, y de eso se trata: el andamio de depuración es normal
 * mientras se construye, y el defecto aparece el día que alguien lo olvida. Un grep
 * a mano no lo detecta porque nadie lo corre; una prueba sí, en cada corrida.
 *
 * Qué NO vigila: `Log::debug()` legítimo detrás de `config('app.debug')`. Lo que se
 * prohíbe es escribir a disco desde una vista y los volcados de variables.
 */

function fuentesDeComponentes(): array
{
    $dir = __DIR__.'/../resources/views/components';
    $fuera = [];

    foreach (glob($dir.'/*.blade.php') ?: [] as $ruta) {
        $fuera[basename($ruta)] = file_get_contents($ruta);
    }

    return $fuera;
}

it('ningún componente escribe a disco ni vuelca variables', function () {
    // Escribir a disco desde una vista no tiene un solo uso legítimo en este paquete:
    // los componentes son Blade puro y no persisten nada. var_export/print_r/dd sobre
    // props es el andamio clásico que se olvida.
    // Las agujas van con límite de palabra: sin él, «ray(» coincide dentro de
    // «array(» y la prueba acusa a media docena de componentes sanos.
    $prohibidos = [
        'file_put_contents' => 'escribe a disco desde una vista: si el volcado lleva props, publica datos del vecino en un archivo del sistema',
        'fopen' => 'abre un archivo desde una vista',
        'var_export' => 'vuelca una variable entera; con $sessions o $attributes eso es PII y contexto de seguridad',
        'print_r' => 'vuelca una variable entera',
        'var_dump' => 'vuelca una variable entera',
        'dd' => 'corta la respuesta a medias y muestra el estado interno',
        'dump' => 'imprime el estado interno en la página',
        'ray' => 'manda el estado interno a una herramienta externa',
    ];

    $hallazgos = [];

    foreach (fuentesDeComponentes() as $archivo => $fuente) {
        // Los comentarios explicativos sí pueden nombrar estas funciones —este mismo
        // paquete documenta sus trampas— así que se miran solo fuera de comentarios.
        $sinComentarios = preg_replace(['/\{\{--.*?--\}\}/s', '/\/\*.*?\*\//s'], '', $fuente);

        foreach ($prohibidos as $aguja => $porque) {
            if (preg_match('/(?<![\w_])'.preg_quote($aguja, '/').'\s*\(/', $sinComentarios)) {
                $hallazgos[] = "{$archivo}: «{$aguja}()» — {$porque}";
            }
        }
    }

    expect($hallazgos)->toBeEmpty(
        "Quedaron rastros de depuración en componentes que se publican:\n  ".
        implode("\n  ", $hallazgos).
        "\nEl andamio va fuera antes de cerrar la ficha. Si de verdad hace falta ".
        'telemetría, va con Log::debug() detrás de config(\'app.debug\'), nunca escribiendo '.
        'a /tmp ni serializando props.'
    );
});

it('ningún componente deja marcadores de depuración en el marcado', function () {
    // Marcadores que solo existen para ver si una rama se ejecuta. Se buscan como
    // palabra suelta en mayúsculas para no chocar con texto legítimo en español.
    $marcadores = ['HOLA', 'TEST123', 'XXX', 'FIXME', 'BORRAR', 'TODO:'];
    $hallazgos = [];

    foreach (fuentesDeComponentes() as $archivo => $fuente) {
        $sinComentarios = preg_replace(['/\{\{--.*?--\}\}/s', '/\/\*.*?\*\//s'], '', $fuente);

        foreach ($marcadores as $marcador) {
            if (preg_match('/\b'.preg_quote($marcador, '/').'\b/', $sinComentarios)) {
                $hallazgos[] = "{$archivo}: «{$marcador}»";
            }
        }
    }

    expect($hallazgos)->toBeEmpty(
        "Quedaron marcadores de depuración visibles para el funcionario:\n  ".
        implode("\n  ", $hallazgos)
    );
});
