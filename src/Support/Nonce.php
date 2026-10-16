<?php

namespace Muni\Ui\Support;

use Illuminate\Support\Facades\Vite;

/**
 * Atributo `nonce` para los <style> y <script> en línea de los componentes.
 *
 * Los sistemas con `style-src 'nonce-…'` bloquean todo bloque en línea sin nonce,
 * y los componentes llevan su CSS dentro de la vista (DESIGN §7). Se usa el mismo
 * nonce que imprime el layout del anfitrión (`Vite::useCspNonce()`). Sin nonce
 * configurado devuelve cadena vacía, nunca `nonce=""`.
 */
final class Nonce
{
    /** ` nonce="…"` (con espacio inicial) o cadena vacía. */
    public static function attr(): string
    {
        $nonce = Vite::cspNonce();

        return is_string($nonce) && $nonce !== '' ? ' nonce="'.e($nonce).'"' : '';
    }
}
