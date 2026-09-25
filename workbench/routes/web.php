<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Vitrina de desarrollo — `vendor/bin/testbench serve` → /vitrina
|--------------------------------------------------------------------------
|
| Ficha `vitrina` de docs/GAP-ANALYSIS.md con la corrección del juez: vive en
| workbench/, no en el paquete. MuniUiServiceProvider no registra rutas, así que
| los sistemas que instalan laravel-muni-ui nunca exponen esto.
|
| Las tarjetas se generan RECORRIENDO resources/views/components/. Nadie escribe
| una tarjeta: un componente nuevo aparece el día que se crea, con los datos de
| workbench/ejemplos.php si los tiene y con sus valores por defecto si no.
|
| URLs fijas para las capturas: /vitrina?tema=claro|oscuro&densidad=compacta y
| /vitrina/armazon/{nombre} para los que emiten su propio <html>.
*/

$raiz = dirname(__DIR__, 2);

/** @return list<string> Los nombres de componente, en orden, leídos del directorio. */
$componentes = static function () use ($raiz): array {
    $nombres = array_map(
        static fn (string $ruta) => substr(basename($ruta), 0, -strlen('.blade.php')),
        glob($raiz.'/resources/views/components/*.blade.php') ?: []
    );
    sort($nombres);

    return $nombres;
};

$fuenteDe = static fn (string $nombre): string => (string) file_get_contents($raiz."/resources/views/components/{$nombre}.blade.php");

// Un armazón emite su propio documento y no se puede anidar en la página. Se decide
// por la SALIDA, no por el fuente: plantilla-pantalla no escribe <html>, lo compone.
$esArmazon = static fn (string $html): bool => (bool) preg_match('/<html\b/i', (string) preg_replace('#<(style|script)\b[^>]*>.*?</\1>#s', '', $html));

// El Blade a renderizar: el ejemplo de la vitrina, o la etiqueta desnuda con sus
// props obligatorias (las de @props sin valor por defecto) rellenadas con texto.
$bladeDe = static function (string $nombre) use ($raiz, $fuenteDe): array {
    $ejemplos = require $raiz.'/workbench/ejemplos.php';

    if (isset($ejemplos[$nombre])) {
        return [$ejemplos[$nombre], true];
    }

    $atributos = '';
    if (preg_match('/@props\(\[(.*?)\]\)\s*\n/s', $fuenteDe($nombre), $m)) {
        $cuerpo = (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $m[1]);
        preg_match_all("/^\s*'([A-Za-z_][A-Za-z0-9_]*)'\s*,/m", $cuerpo, $obligatorias);
        foreach ($obligatorias[1] as $prop) {
            $atributos .= ' '.Str::kebab($prop).'="Ejemplo de '.$prop.'"';
        }
    }

    return ["<x-muni::{$nombre}{$atributos}>Contenido de ejemplo</x-muni::{$nombre}>", false];
};

$renderizar = static function (string $blade): array {
    try {
        return [Blade::render($blade, [], deleteCachedView: true), null];
    } catch (Throwable $e) {
        return [null, class_basename($e).': '.Str::limit($e->getMessage(), 400)];
    }
};

// Solo valores de una lista cerrada llegan al HTML y a las expresiones de Alpine.
$temaDe = static fn (Request $r): string => in_array($r->query('tema'), ['claro', 'oscuro'], true) ? (string) $r->query('tema') : 'sistema';
$densidadDe = static fn (Request $r): string => $r->query('densidad') === 'compacta' ? 'compacta' : 'comoda';
$atributoTema = static fn (string $tema): ?string => ['claro' => 'light', 'oscuro' => 'dark'][$tema] ?? null;

$primeraFuente = static function (array $candidatos): ?string {
    foreach ($candidatos as $ruta) {
        if (is_file($ruta)) {
            return (string) file_get_contents($ruta);
        }
    }

    return null;
};

// Alpine 3 core + el plugin Focus (el que viaja en Livewire), plugin ANTES del núcleo.
$scriptsAlpine = static function () use ($raiz, $primeraFuente): array {
    return [
        'focus' => $primeraFuente([(string) getenv('MUNI_ALPINE_FOCUS_JS'), $raiz.'/node_modules/@alpinejs/focus/dist/cdn.min.js']),
        'core' => $primeraFuente([(string) getenv('MUNI_ALPINE_JS'), $raiz.'/node_modules/alpinejs/dist/cdn.min.js']),
    ];
};

Route::get('/vitrina', function (Request $request) use ($raiz, $componentes, $esArmazon, $bladeDe, $renderizar, $temaDe, $densidadDe, $atributoTema, $scriptsAlpine) {
    $registro = [];
    if (is_file($raiz.'/registry.json')) {
        foreach (json_decode((string) file_get_contents($raiz.'/registry.json'), true)['components'] ?? [] as $entrada) {
            $registro[$entrada['name']] = $entrada;
        }
    }

    $piezas = [];
    foreach ($componentes() as $nombre) {
        [$blade, $conEjemplo] = $bladeDe($nombre);
        [$html, $error] = $renderizar($blade);
        $armazon = $html !== null && $esArmazon($html);
        if ($armazon) {
            $html = null;
        }

        $piezas[] = [
            'nombre' => $nombre,
            'armazon' => $armazon,
            'conEjemplo' => $conEjemplo,
            'blade' => $blade,
            'html' => $html,
            'error' => $error,
            'descripcion' => $registro[$nombre]['description'] ?? null,
            'enRegistro' => isset($registro[$nombre]),
            'alpine' => (bool) ($registro[$nombre]['alpine']['required'] ?? false),
        ];
    }

    $tema = $temaDe($request);

    return View::file(dirname(__DIR__).'/resources/views/vitrina.blade.php', [
        'piezas' => $piezas,
        'tema' => $tema,
        'atributoTema' => $atributoTema($tema),
        'densidad' => $densidadDe($request),
        'css' => (string) file_get_contents($raiz.'/resources/css/muni-ui.css'),
        'alpine' => $scriptsAlpine(),
    ]);
});

Route::get('/vitrina/armazon/{nombre}', function (Request $request, string $nombre) use ($raiz, $componentes, $esArmazon, $bladeDe, $renderizar, $temaDe, $atributoTema, $scriptsAlpine) {
    abort_unless(in_array($nombre, $componentes(), true), 404);

    [$blade] = $bladeDe($nombre);
    [$html, $error] = $renderizar($blade);
    abort_if($html === null, 500, (string) $error);
    abort_unless($esArmazon($html), 404);

    $hoja = '<style data-vitrina="muni-ui.css">'.file_get_contents($raiz.'/resources/css/muni-ui.css').'</style>';
    $html = preg_match('/<head\b[^>]*>/i', $html)
        ? (string) preg_replace('/<head\b[^>]*>/i', '$0'.str_replace(['\\', '$'], ['\\\\', '\\$'], $hoja), $html, 1)
        : $hoja.$html;

    $atributo = $atributoTema($temaDe($request));
    if ($atributo !== null) {
        // Solo en la etiqueta <html>: el tema que el armazón traiga se reemplaza por el de la URL.
        $html = (string) preg_replace_callback('/<html\b[^>]*>/i', static fn (array $m) => '<html data-muni-theme="'.$atributo.'"'
            .substr((string) preg_replace('/\sdata-muni-theme="[^"]*"/', '', $m[0]), 5), $html, 1);
    }

    $alpine = array_filter($scriptsAlpine());
    $scripts = implode('', array_map(static fn (string $js, string $k) => '<script data-vitrina="alpine-'.$k.'">'.$js.'</script>', $alpine, array_keys($alpine)));

    return response(str_ireplace('</body>', $scripts.'</body>', $html));
})->where('nombre', '[a-z0-9-]+');

// El escudo que `gob-bar` y `gob-escudo` piden a public/vendor/muni-ui/. Un sistema lo publica
// con vendor:publish --tag=muni-ui-images; acá se sirve directo desde resources/images, y
// solo lo que está en ese directorio.
Route::get('/vendor/muni-ui/{archivo}', function (string $archivo) use ($raiz) {
    $imagenes = array_map('basename', glob($raiz.'/resources/images/*') ?: []);
    abort_unless(in_array($archivo, $imagenes, true), 404);

    return response()->file($raiz.'/resources/images/'.$archivo);
})->where('archivo', '[A-Za-z0-9._-]+');
