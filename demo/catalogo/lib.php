<?php

/*
 * Funciones del catálogo, compartidas por build.php y por tests/CatalogoTest.php.
 */

use Illuminate\Contracts\View\Factory;

final class CatalogoHead
{
    public static string $html = '';

    public static string $escudo = '';

    /** Reemplaza la URL publicada del escudo por la imagen embebida. */
    public static function embeber(string $html): string
    {
        return self::$escudo === '' ? $html : str_replace(asset('vendor/muni-ui/logo-graneros.png'), self::$escudo, $html);
    }
}

/**
 * Lee la documentación de un componente desde su propio archivo.
 *
 * @return array{props: list<array{nombre: string, defecto: ?string, doc: ?string}>, slots: list<string>, alpine: bool, nota: ?string}
 */
function componente_meta(string $archivo): array
{
    $src = file_get_contents($archivo);
    $props = [];

    if (preg_match('/@props\(\[(.*?)\n\]\)/s', $src, $m)) {
        // Dos estilos de documentar una prop: comentario en la misma línea
        // (`'tone' => 'ok', // ok | warn`) o un bloque /* … */ o // justo encima,
        // que es como lo hace develop. Del bloque se toma la primera oración.
        $bloque = '';
        foreach (explode("\n", $m[1]) as $linea) {
            $t = trim($linea);
            if (str_starts_with($t, '/*') || str_starts_with($t, '*') || str_starts_with($t, '//')) {
                $bloque .= ' '.trim(preg_replace('#^(/\*+|\*+/?|//)#', '', $t));

                continue;
            }
            if (! preg_match("/^\s*'(\w+)'(?:\s*=>\s*(.*?))?,\s*(?:\/\/\s*(.*))?$/", $linea, $p)) {
                $bloque = '';

                continue;
            }
            $doc = isset($p[3]) && $p[3] !== '' ? $p[3] : null;
            if ($doc === null && trim($bloque) !== '') {
                $texto = trim(preg_replace('/\s+/', ' ', str_replace('*/', '', $bloque)));
                $doc = preg_match('/^(.+?[.:])(\s|$)/u', $texto, $o) ? $o[1] : mb_substr($texto, 0, 160);
            }
            $props[] = [
                'nombre' => $p[1],
                'defecto' => ($p[2] ?? '') === '' ? null : $p[2],
                'doc' => $doc,
            ];
            $bloque = '';
        }
    }

    // Slots: el por defecto y los con nombre que el componente consulta.
    $nombres = array_column($props, 'nombre');
    preg_match_all('/isset\(\$(\w+)\)|\$(\w+)\s*\?\?\s*\'\'/', $src, $s);
    $slots = array_values(array_unique(array_filter(
        array_merge($s[1], $s[2]),
        fn ($n) => $n !== '' && $n !== 'attributes' && ! in_array($n, $nombres, true),
    )));
    if (str_contains($src, '$slot')) {
        array_unshift($slots, 'slot');
    }

    // Primer comentario Blade fuera de @props: la descripción que dejó quien lo escribió.
    $nota = null;
    if (preg_match('/\{\{--\s*(.*?)\s*--\}\}/s', $src, $c)) {
        $nota = preg_replace('/\s+/', ' ', $c[1]);
    }

    return ['props' => $props, 'slots' => $slots, 'alpine' => str_contains($src, 'x-data'), 'nota' => $nota];
}

/** Resaltado mínimo de Blade para los bloques de código. */
function resaltar(string $codigo): string
{
    $h = htmlspecialchars(rtrim($codigo), ENT_QUOTES);

    return preg_replace_callback(
        '/(\{\{--.*?--\}\})|(&lt;\/?x-[\w:.-]+)|(&lt;\/?[a-z][\w-]*)|(@\w+)|(\s)(:?[\w.:-]+)(=)|(&quot;.*?&quot;|&#039;.*?&#039;)/s',
        function ($m) {
            return match (true) {
                $m[1] !== '' => '<span class="tk-c">'.$m[1].'</span>',
                $m[2] !== '' => '<span class="tk-x">'.$m[2].'</span>',
                $m[3] !== '' => '<span class="tk-t">'.$m[3].'</span>',
                $m[4] !== '' => '<span class="tk-d">'.$m[4].'</span>',
                ($m[6] ?? '') !== '' => $m[5].'<span class="tk-a">'.$m[6].'</span>'.$m[7],
                default => '<span class="tk-s">'.$m[8].'</span>',
            };
        },
        $h,
    );
}

/** Todo lo que necesita catalogo.blade.php, ya renderizado. */
function catalogo_datos(Factory $view, string $root): array
{
    $dir = __DIR__;
    $grupos = [];
    $vistos = [];

    foreach (require $dir.'/componentes.php' as $grupo => $componentes) {
        foreach ($componentes as $nombre => $info) {
            $ejemplo = $dir.'/ejemplos/'.$nombre.'.blade.php';
            $codigo = file_get_contents($ejemplo);
            $subs = [];
            foreach ($info['incluye'] ?? [] as $sub) {
                $subs[$sub] = componente_meta($root.'/resources/views/components/'.$sub.'.blade.php');
            }
            $grupos[$grupo][$nombre] = $info + [
                'vista' => $info['vista'] ?? 'fila',
                'meta' => componente_meta($root.'/resources/views/components/'.$nombre.'.blade.php'),
                'subs' => $subs,
                'html' => CatalogoHead::embeber($view->file($ejemplo)->render()),
                'codigo' => resaltar($codigo),
                'crudo' => rtrim($codigo),
            ];
            $vistos += [$nombre => true] + array_fill_keys(array_keys($subs), true);
        }
    }

    $recetas = [];
    foreach (is_file($dir.'/recetas.php') ? require $dir.'/recetas.php' : [] as $slug => $info) {
        $archivo = $dir.'/recetas/'.$slug.'.blade.php';
        $codigo = file_get_contents($archivo);
        $recetas[$slug] = $info + [
            'html' => CatalogoHead::embeber($view->file($archivo)->render()),
            'codigo' => resaltar($codigo),
            'crudo' => rtrim($codigo),
        ];
    }

    $total = count($vistos);

    // Armado por partes: Blade compila la etiqueta de componente aunque esté dentro de un string.
    $inicio = resaltar('<'.'x-muni::badge tone="ok">Al día</'.'x-muni::badge>');

    $tokens = tokens_css(file_get_contents($root.'/resources/css/muni-ui.css'));

    return compact('grupos', 'recetas', 'total', 'inicio', 'tokens');
}

/**
 * Los bloques de tokens de muni-ui.css, por tema. El tema oscuro se declara dos
 * veces (preferencia del OS y activadores explícitos) y el claro otras dos (:root y
 * el override data-muni-theme="light"); tests/CatalogoTest.php exige que cada par
 * sea idéntico.
 *
 * @return array{gob: array<string,string>, light: array<string,string>, light_override: array<string,string>, dark_os: array<string,string>, dark: array<string,string>}
 */
function tokens_css(string $css): array
{
    $bloque = function (string $selectorRegex) use ($css): array {
        if (! preg_match('/'.$selectorRegex.'\s*\{(.*?)\n\s*\}/s', $css, $m)) {
            return [];
        }
        preg_match_all('/--(muni-[\w-]+)\s*:\s*([^;]+);/', $m[1], $t);

        return array_combine($t[1], array_map('trim', $t[2]));
    };

    return [
        'gob' => $bloque(':root(?=\s*\{\s*--muni-gob)'),
        'light' => $bloque(':root(?=\s*\{\s*--muni-bg)'),
        'light_override' => $bloque('\n\[data-muni-theme="light"\]'),
        'dark_os' => $bloque(':root:not\(\[data-muni-theme="light"\]\):not\(\[data-theme="light"\]\):not\(\.light\)'),
        'dark' => $bloque('\.dark'),
    ];
}
