<?php

/*
|--------------------------------------------------------------------------
| Lectura de reglas CSS de los componentes
|--------------------------------------------------------------------------
|
| `reglaCss()` la usan varios archivos de prueba; declarada dentro de uno
| revienta con «Cannot redeclare function» en la corrida completa (dos archivos
| la declaraban) y con «undefined function» al correr un archivo suelto.
*/

/** El cuerpo de la primera regla CSS cuyo selector contiene `$aguja`. */
function reglaCss(string $css, string $aguja): string
{
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $reglas, PREG_SET_ORDER);

    foreach ($reglas as [, $selector, $cuerpo]) {
        if (str_contains(preg_replace('/\s+/', ' ', $selector), $aguja)) {
            return trim($cuerpo);
        }
    }

    return '';
}
