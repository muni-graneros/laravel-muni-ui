<?php

/*
|--------------------------------------------------------------------------
| Fuentes que las pruebas leen de disco
|--------------------------------------------------------------------------
|
| Funciones que usa MÁS DE UN archivo de prueba. Vivían dentro de
| `HojaImprimibleTest.php` y `GeneraVitrinaTest.php`, y los demás las tomaban
| prestadas: con la suite entera pasaba porque Pest carga todos los archivos antes
| de ejecutar, pero `pest --parallel` o `pest tests/GuardiaDeSesionTest.php` solo
| reventaban con «Call to undefined function». Pest carga `tests/Helpers/` en el
| arranque, siempre, y `tests/Pest.php` lo pide además de forma explícita.
|
| Las rutas suben DOS niveles (`tests/Helpers/` → raíz del paquete).
*/

/** Ruta de una vista de componente del paquete. */
function rutaDeLaVista(string $nombre): string
{
    return __DIR__.'/../../resources/views/components/'.$nombre.'.blade.php';
}

/** El fuente crudo de un componente, o cadena vacía si todavía no existe. */
function fuenteDeLaVista(string $nombre): string
{
    $ruta = rutaDeLaVista($nombre);

    return file_exists($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * El código de Alpine 3 que se hornea en la vitrina, o null si no hay copia en disco.
 *
 * Por qué hace falta: los componentes del paquete dan por sentado que Alpine viaja
 * dentro del bundle de Livewire, así que el paquete no publica ninguno. Sin él la
 * vitrina no emite un solo <script>: la lista del combobox, la burbuja del tooltip,
 * el panel del popover y —esto es lo peor— TODA la cabecera y el cuerpo de
 * `sortable-table`, que viven dentro de <template x-for>, nunca llegan al DOM. La
 * reja medía lo que el funcionario ve antes de tocar nada, y solo eso.
 *
 * De dónde sale, en el mismo orden que `scripts/a11y-check.py` resuelve axe-core:
 *   1. $MUNI_ALPINE_JS      (una ruta explícita, como el `--axe` de la reja)
 *   2. node_modules/        (`npm install`; alpinejs es dependencia de desarrollo)
 *   3. scripts/.cache/      (copia cacheada, ignorada por git como la de axe)
 *
 * Nunca de un CDN: la reja corre sin red y una vitrina que dependa de internet deja
 * de ser un candado en cuanto se cae la conexión. Si no hay copia, la vitrina se
 * genera igual pero SIN hidratar y se dice en voz alta: es preferible a un fallo de
 * la suite en una máquina que no corrió `npm install`. Quien pierde de verdad es la
 * reja, y por eso `a11y-check.py` marca FALLA si la página pedía Alpine y no llegó.
 */
function fuenteDeAlpine(): ?string
{
    $candidatos = [];

    if ($ruta = getenv('MUNI_ALPINE_JS')) {
        $candidatos[] = $ruta;
    }

    $candidatos[] = __DIR__.'/../../node_modules/alpinejs/dist/cdn.min.js';

    // El glob del núcleo NO puede tragarse la copia del plugin: los dos archivos
    // viven en la misma caché y `alpine-*.min.js` casa también con
    // `alpine-focus-3.17.2.min.js`. Cargar el plugin donde va el núcleo deja la
    // página sin Alpine y con un «Alpine is not defined» que nadie lee.
    foreach (glob(__DIR__.'/../../scripts/.cache/alpine-*.min.js') ?: [] as $cacheada) {
        if (! str_contains(basename($cacheada), 'alpine-focus-')) {
            $candidatos[] = $cacheada;
        }
    }

    return primeraFuenteEnDisco($candidatos);
}

/**
 * El plugin Focus (`@alpinejs/focus`) horneado, o null si no hay copia en disco.
 *
 * Por qué hace falta: `modal`, `drawer`, `command-palette` y `sidebar` usan
 * `x-trap.inert.noscroll`, que es una directiva del PLUGIN, no del núcleo. Y acá
 * está el detalle que obliga a comprobarlo en el navegador: si el plugin no está,
 * Alpine no se queja —una directiva desconocida se ignora en silencio—, el
 * diálogo se abre igual porque el `x-show` es del núcleo, y la reja mediría un
 * diálogo que NO atrapa el foco dando verde. Por eso el abridor no se conforma
 * con que el panel tenga alto: exige la prueba de que la trampa aisló el resto
 * de la página (ver `abridorDeVitrina`).
 *
 * Se resuelve igual que el núcleo —$MUNI_ALPINE_FOCUS_JS, node_modules,
 * scripts/.cache— y NUNCA de un CDN, por lo mismo: la reja corre sin red.
 *
 * Va SIEMPRE ANTES del núcleo. Es como lo pide Alpine: los plugins se registran
 * sobre `window.Alpine` antes de que el núcleo llame a `start()`, y el build de
 * CDN del núcleo arranca solo en un microtask de su propio <script>. Al revés, el
 * plugin llegaría tarde y `x-trap` no existiría al hidratar.
 */
function fuenteDeFocus(): ?string
{
    $candidatos = [];

    if ($ruta = getenv('MUNI_ALPINE_FOCUS_JS')) {
        $candidatos[] = $ruta;
    }

    $candidatos[] = __DIR__.'/../../node_modules/@alpinejs/focus/dist/cdn.min.js';

    foreach (glob(__DIR__.'/../../scripts/.cache/alpine-focus-*.min.js') ?: [] as $cacheada) {
        $candidatos[] = $cacheada;
    }

    return primeraFuenteEnDisco($candidatos);
}

function primeraFuenteEnDisco(array $candidatos): ?string
{
    foreach ($candidatos as $candidato) {
        if (is_file($candidato)) {
            return (string) file_get_contents($candidato);
        }
    }

    return null;
}
