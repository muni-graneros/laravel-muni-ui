<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL ANILLO DE AVANCE (`<x-muni::ring>`)
|--------------------------------------------------------------------------
|
| Reparación de la ficha `ring` de docs/GAP-ANALYSIS.md, en los términos de la
| «corrección exigida» por el juez, que manda sobre la ficha:
|
| 1. Lo que fallaba es 4.1.2 (Nombre, Rol, Valor) y 1.1.1 (contenido no
|    textual). El anillo no exponía nada: con `showValue=false` el dato existía
|    solo como largo de arco.
| 2. `role="progressbar"` + `aria-valuenow`/`min`/`max` + `aria-valuetext` +
|    `aria-label` van en el div con `position:relative`. `aria-valuemax` es 100
|    FIJO y `aria-valuenow` es el porcentaje ya acotado: nunca el valor crudo
|    contra un máximo igual a él (la lección de Creative Tim, que anunciaba
|    100 % donde la pantalla mostraba 30 %).
| 3. La geometría NO es la de progress: acá la cifra vive DENTRO del contenedor
|    con el rol. El <svg> y la cifra central van `aria-hidden` para que el lector
|    no anuncie «78 por ciento» y después «78%» otra vez. El rótulo visible va
|    debajo, FUERA del contenedor: cuando es el nombre accesible también se
|    oculta al lector, por la misma razón (se oiría dos veces); si el
|    consumidor pasa otro `aria-label`, el rótulo visible queda legible.
| 4. La duración NO pasa a `--muni-dur` (160 ms: cinco veces más rápido, un
|    arco que «salta»). Pasa a `--muni-dur-slow` (600 ms), token nuevo declarado
|    en `:root` de las DOS hojas y bajado a 0 ms bajo `prefers-reduced-motion`
|    en las dos. Además el componente apaga la transición en su propio bloque de
|    estilos: un sistema con la hoja publicada vieja no tiene el token, cae en el
|    respaldo de 600 ms y sin la guardia local ignoraría la preferencia.
| 5. Sin región viva: si el anillo se refresca por polling de Livewire, un
|    `aria-valuenow` anunciado cada dos segundos satura al lector. El valor se
|    lee cuando el usuario llega al anillo.
|
| 6. La familia completa, puntos 4 y 5 de la corrección: progress (.5s),
|    chart-donut (.7s) y chart-bar (.6s) tenían la misma duración literal. Los
|    tres pasan a `--muni-dur-slow` con la misma guardia local, y chart-donut
|    oculta al lector su <svg>: el dato ya está como texto en la leyenda.
|
| Las aserciones con mensaje van como `expect(bool)->toBeTrue('...')`: en Pest
| el segundo argumento de `toContain()` es otra aguja, no un mensaje.
*/

/** El HTML del anillo sin su bloque de estilos. */
function anilloHtml(string $atributos = '', array $datos = []): string
{
    $html = Blade::render("<x-muni::ring {$atributos} />", $datos);

    return (string) preg_replace('#<style\b.*?</style>#s', '', $html);
}

/** El elemento con `role="progressbar"` como DOMElement, o null. */
function anilloBarra(string $html): ?DOMElement
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
    libxml_clear_errors();

    $nodos = (new DOMXPath($dom))->query('//*[@role="progressbar"]');

    return $nodos !== false && $nodos->length === 1 ? $nodos->item(0) : null;
}

function anilloFuente(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/ring.blade.php');
}

/** El fuente sin comentarios Blade, CSS ni PHP de línea. */
function anilloFuenteSinComentarios(): string
{
    $fuente = anilloFuente();
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $fuente);
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', $fuente);

    return (string) preg_replace('#^\s*//.*$#m', '', $fuente);
}

/** El CSS que el anillo lleva en su bloque de estilos, sin comentarios. */
function anilloCss(): string
{
    preg_match_all('#<style>(.*?)</style>#s', anilloFuente(), $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

/** Una hoja sin comentarios. */
function anilloHojaSinComentarios(string $css): string
{
    return (string) preg_replace('#/\*.*?\*/#s', '', $css);
}

/** El interior del primer bloque `{…}` que empieza en `$ancla` (con llaves anidadas). */
function anilloBloque(string $css, string $ancla, int $desde = 0): string
{
    $inicio = strpos($css, $ancla, $desde);

    if ($inicio === false) {
        return '';
    }

    $abre = strpos($css, '{', $inicio);
    $nivel = 0;

    for ($i = $abre, $n = strlen($css); $i < $n; $i++) {
        if ($css[$i] === '{') {
            $nivel++;
        } elseif ($css[$i] === '}') {
            $nivel--;

            if ($nivel === 0) {
                return substr($css, $abre + 1, $i - $abre - 1);
            }
        }
    }

    return '';
}

it('expone rol, nombre y valor en el contenedor del arco (4.1.2)', function () {
    $barra = anilloBarra(anilloHtml('value="78" label="Cupos ocupados"'));

    expect($barra)->not->toBeNull('hay exactamente un role="progressbar"');
    expect($barra->getAttribute('aria-valuenow'))->toBe('78');
    expect($barra->getAttribute('aria-valuemin'))->toBe('0');
    expect($barra->getAttribute('aria-valuemax'))->toBe('100');
    expect($barra->getAttribute('aria-valuetext'))->toBe('78 %');
    expect($barra->getAttribute('aria-label'))->toBe('Cupos ocupados');
    expect(str_contains($barra->getAttribute('style'), 'position:relative'))
        ->toBeTrue('el rol va en el div con position:relative, el que contiene el arco y la cifra');
});

it('anuncia el porcentaje acotado contra un máximo de 100 fijo, nunca el valor crudo contra sí mismo', function () {
    // 30 de 40 cupos: la pantalla dice 75 %. Con aria-valuemax="40" y
    // aria-valuenow="30" un lector también diría 75 %, pero el contrato del
    // paquete (progress) es el porcentaje; y un valuemax igual al valuenow es
    // el defecto de Creative Tim: 100 % donde la pantalla muestra 30 %.
    $barra = anilloBarra(anilloHtml(':value="30" :max="40" label="Cupos"'));
    expect($barra->getAttribute('aria-valuenow'))->toBe('75');
    expect($barra->getAttribute('aria-valuemax'))->toBe('100');
    expect($barra->getAttribute('aria-valuetext'))->toBe('75 %');

    $pasado = anilloBarra(anilloHtml(':value="130" :max="100"'));
    expect($pasado->getAttribute('aria-valuenow'))->toBe('100');

    $negativo = anilloBarra(anilloHtml(':value="-5"'));
    expect($negativo->getAttribute('aria-valuenow'))->toBe('0');

    $sinMaximo = anilloBarra(anilloHtml(':value="12" :max="0"'));
    expect($sinMaximo->getAttribute('aria-valuenow'))->toBe('0');
    expect($sinMaximo->getAttribute('aria-valuetext'))->toBe('0 %');

    $decimal = anilloBarra(anilloHtml(':value="2" :max="3"'));
    expect($decimal->getAttribute('aria-valuenow'))->toBe('67');
});

it('sin rótulo cae en el nombre «Progreso», y un aria-label del consumidor gana sin duplicarse en la raíz', function () {
    expect(anilloBarra(anilloHtml('value="10"'))->getAttribute('aria-label'))->toBe('Progreso');

    $html = anilloHtml('value="64" label="ARCOP" aria-label="Solicitudes ARCOP respondidas en plazo"');
    expect(anilloBarra($html)->getAttribute('aria-label'))->toBe('Solicitudes ARCOP respondidas en plazo');
    expect(substr_count($html, 'aria-label='))->toBe(1, 'el aria-label no se derrama también en la raíz, un div sin rol donde no significa nada');
});

it('oculta al lector el svg y la cifra central para que el valor no se oiga dos veces', function () {
    $html = anilloHtml('value="78" label="Cupos ocupados"');

    expect(preg_match('#<svg\b[^>]*aria-hidden="true"#', $html) === 1)->toBeTrue('el <svg> va aria-hidden (1.1.1: el nombre y el valor ya están en el rol)');
    expect(preg_match('#<div\b[^>]*aria-hidden="true"[^>]*>\s*<span\b[^>]*>78<span#s', $html) === 1)
        ->toBeTrue('la cifra central «78%» va dentro de un contenedor aria-hidden');

    // Con showValue=false no hay cifra visible, pero el valor sigue en el rol:
    // era el caso en que el dato vivía SOLO en el largo del arco.
    $sinCifra = anilloHtml('value="78" label="Cupos ocupados" :show-value="false"');
    expect(str_contains($sinCifra, '78<span'))->toBeFalse('sin showValue no se pinta la cifra');
    expect(anilloBarra($sinCifra)->getAttribute('aria-valuenow'))->toBe('78');
});

it('el rótulo visible no se lee dos veces cuando ya es el nombre, y sí se lee cuando el nombre es otro', function () {
    $html = anilloHtml('value="78" label="Cupos ocupados"');
    expect(preg_match('#<span\b[^>]*aria-hidden="true"[^>]*>Cupos ocupados</span>#', $html) === 1)
        ->toBeTrue('el rótulo visible, que es el mismo texto del aria-label, va aria-hidden');

    $otro = anilloHtml('value="64" label="ARCOP" aria-label="Solicitudes ARCOP respondidas en plazo"');
    expect(preg_match('#<span\b[^>]*aria-hidden="true"[^>]*>ARCOP</span>#', $otro) === 0)
        ->toBeTrue('si el nombre accesible es otro, el rótulo visible es información extra y queda legible');
});

it('no declara región viva: un polling de Livewire no debe anunciar el valor cada dos segundos', function () {
    $fuente = anilloFuenteSinComentarios();

    expect(str_contains($fuente, 'aria-live'))->toBeFalse('sin aria-live');
    expect(str_contains($fuente, 'role="status"'))->toBeFalse('sin role="status"');
    expect(str_contains($fuente, 'role="alert"'))->toBeFalse('sin role="alert"');
});

it('anima con --muni-dur-slow y no con una duración literal ni con --muni-dur', function () {
    $fuente = anilloFuenteSinComentarios();

    preg_match_all('/transition\s*:\s*([^;"]+)/', $fuente, $m);
    expect($m[1])->not->toBeEmpty('el arco sigue animando su avance');

    foreach ($m[1] as $transicion) {
        if (trim($transicion) === 'none !important') {
            continue;
        }

        expect(preg_match('/(?<![\w-])\.?\d+(\.\d+)?m?s\b/', (string) preg_replace('/var\([^)]*\)/', '', $transicion)) === 0)
            ->toBeTrue("duración literal en «{$transicion}»: ignora prefers-reduced-motion");
        expect(str_contains($transicion, 'var(--muni-dur-slow'))
            ->toBeTrue("«{$transicion}» no usa --muni-dur-slow (--muni-dur son 160 ms: el arco saltaría)");
    }
});

it('apaga la transición del arco con movimiento reducido en su propio bloque de estilos, con !important', function () {
    // Guardia local, como stat: la transición viaja inline y una regla de clase
    // sin !important pierde contra el style. Y un sistema con la hoja publicada
    // vieja no tiene --muni-dur-slow: cae en el respaldo de 600 ms.
    expect(preg_match('#@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.muni-ring__arco\s*\{[^}]*transition\s*:\s*none\s*!important#s', anilloCss()) === 1)
        ->toBeTrue('el bloque de estilos apaga .muni-ring__arco bajo prefers-reduced-motion con !important');
    expect(preg_match('#class="muni-ring__arco"#', anilloHtml('value="40"')) === 1)
        ->toBeTrue('el círculo de avance lleva la clase que la guardia apunta');
    preg_match_all('#<style>(.*?)</style>#s', anilloFuente(), $bloques);
    preg_match_all('#/\*.*?\*/#s', implode("\n", $bloques[1] ?? []), $comentarios);
    expect(preg_match('/@(once|endonce|if|endif)\b/', implode("\n", $comentarios[0])) === 0)
        ->toBeTrue('DESIGN §8 trampa 5: ninguna directiva nombrada dentro de un comentario CSS');
});

it('declara --muni-dur-slow en :root de las dos hojas, y las dos lo bajan a 0 ms con movimiento reducido', function (string $hoja) {
    $css = anilloHojaSinComentarios((string) file_get_contents(__DIR__.'/../resources/css/'.$hoja));

    // El bloque de base es el que ya declara --muni-dur (160 ms): el token
    // lento va a su lado, heredado desde la raíz igual que el rápido.
    $raiz = '';
    $desde = 0;

    while (($pos = strpos($css, ':root', $desde)) !== false) {
        $bloque = anilloBloque($css, substr($css, $pos, 5), $pos);

        if (preg_match('/--muni-dur\s*:\s*[1-9]\d*ms/', $bloque) === 1) {
            $raiz = $bloque;

            break;
        }

        $desde = $pos + 5;
    }

    expect($raiz)->not->toBe('', "{$hoja}: no se encontró el :root que declara --muni-dur");
    expect(preg_match('/--muni-dur-slow\s*:\s*(\d+)ms\s*;/', $raiz, $valor) === 1)
        ->toBeTrue("{$hoja} no declara --muni-dur-slow en el :root de base, junto a --muni-dur");
    expect((int) $valor[1])->toBeGreaterThanOrEqual(400)->toBeLessThanOrEqual(800);

    expect(preg_match('/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{\s*:root\s*\{[^}]*--muni-dur-slow\s*:\s*0m?s/s', $css) === 1)
        ->toBeTrue("{$hoja} no baja --muni-dur-slow a 0 ms bajo prefers-reduced-motion");

    // Es un token de movimiento: no cambia con el tema (DESIGN §2). Redeclararlo
    // en un bloque de tema lo REARMARÍA en 600 ms dentro de un contenedor
    // `.dark` o `[data-muni-theme]` anidado, donde el `:root` del bloque de
    // movimiento reducido ya no alcanza.
    foreach (['.dark {', '.dark{', '[data-muni-theme="light"] {'] as $tema) {
        expect(str_contains(anilloBloque($css, $tema), '--muni-dur-slow'))
            ->toBeFalse("{$hoja} redeclara --muni-dur-slow en «{$tema}»: rearmaría el movimiento en un tema anidado");
    }
})->with(['muni-ui.css', 'muni-ui-filament.css']);

it('no escribe colores literales ni genera ids con uniqid', function () {
    $fuente = anilloFuenteSinComentarios();

    expect(preg_match('/(?<!&)#[0-9a-fA-F]{3,8}\b/', $fuente) === 0)->toBeTrue('cero hex en el componente');
    expect(preg_match('/\b(rgb|hsl|oklch|oklab)a?\(/', $fuente) === 0)->toBeTrue('cero colores funcionales');
    expect(str_contains($fuente, 'uniqid'))->toBeFalse('sin uniqid()');
});

it('escapa el rótulo y el aria-label', function () {
    $html = anilloHtml('value="5" :label="$l"', ['l' => '<b>Cupos</b> "del día"']);

    expect(str_contains($html, '<b>Cupos</b>'))->toBeFalse('el rótulo se escapa');
    expect(str_contains($html, '&lt;b&gt;Cupos&lt;/b&gt;'))->toBeTrue('el rótulo aparece escapado');
    expect(anilloBarra($html)->getAttribute('aria-label'))->toBe('<b>Cupos</b> "del día"');
    expect(str_contains($html, 'aria-label="&lt;b&gt;Cupos&lt;/b&gt; &quot;del día&quot;"'))
        ->toBeTrue('el nombre accesible sale escapado: una comilla no cierra el atributo');
});

it('conserva class, id y style del consumidor en la raíz', function () {
    $html = anilloHtml('value="50" id="cupos-hoy" class="mi-anillo" style="margin:4px"');

    expect(preg_match('#^\s*<div\b[^>]*id="cupos-hoy"#', $html) === 1)->toBeTrue('el id va en la raíz');
    expect(preg_match('#^\s*<div\b[^>]*class="[^"]*mi-anillo#', $html) === 1)->toBeTrue('la class se concatena en la raíz');
    expect(preg_match('#^\s*<div\b[^>]*style="[^"]*display:inline-flex[^"]*margin:4px#', $html) === 1)->toBeTrue('el style se concatena');
});

it('un aria-label vacío no deja al progressbar sin nombre', function () {
    expect(anilloBarra(anilloHtml('value="10" aria-label=""'))->getAttribute('aria-label'))->toBe('Progreso');
    expect(anilloBarra(anilloHtml('value="10" label="Cupos" aria-label=""'))->getAttribute('aria-label'))->toBe('Cupos');
});

it('un aria-labelledby del consumidor va al progressbar y no a la raíz', function () {
    $html = anilloHtml('value="40" label="Cupos" aria-labelledby="titulo-agenda"');
    $barra = anilloBarra($html);

    expect($barra->getAttribute('aria-labelledby'))->toBe('titulo-agenda');
    expect(substr_count($html, 'aria-labelledby='))->toBe(1, 'no se derrama en la raíz, un div sin rol');
    expect(preg_match('#<span\b[^>]*aria-hidden="true"[^>]*>Cupos</span>#', $html) === 0)
        ->toBeTrue('con el nombre en otro elemento, el rótulo visible es información extra y queda legible');
});

/*
 * La familia de la corrección del juez (puntos 4 y 5): la misma duración literal
 * estaba en cuatro archivos, no en dos. La barrida se hace de una vez.
 */
function familiaFuente(string $componente): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/'.$componente.'.blade.php');
}

function familiaSinComentarios(string $componente): string
{
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', familiaFuente($componente));
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', $fuente);

    return (string) preg_replace('#^\s*//.*$#m', '', $fuente);
}

it('anima con --muni-dur-slow y sin duración literal', function (string $componente) {
    preg_match_all('/transition\s*:\s*([^;"}]+)/', familiaSinComentarios($componente), $m);
    $vivas = array_values(array_filter($m[1], fn ($t) => ! str_starts_with(trim($t), 'none')));

    expect($vivas)->not->toBeEmpty("{$componente} sigue animando su avance");

    foreach ($vivas as $transicion) {
        expect(preg_match('/(?<![\w-])\.?\d+(\.\d+)?m?s\b/', (string) preg_replace('/var\([^)]*\)/', '', $transicion)) === 0)
            ->toBeTrue("{$componente}: duración literal en «{$transicion}», ignora prefers-reduced-motion (DESIGN §6)");
        expect(str_contains($transicion, 'var(--muni-dur-slow'))
            ->toBeTrue("{$componente}: «{$transicion}» no usa --muni-dur-slow");
    }
})->with(['progress', 'chart-donut', 'chart-bar']);

it('apaga la transición con movimiento reducido en su propio bloque de estilos', function (string $componente, string $clase, string $html) {
    $fuente = familiaFuente($componente);

    expect(preg_match('/@once\s*<style>/s', $fuente) === 1)
        ->toBeTrue("{$componente}: la guardia viaja en su @once (DESIGN §7: el panel no carga muni-ui.css, y ahí --muni-dur no baja)");

    preg_match_all('#<style>(.*?)</style>#s', $fuente, $bloques);
    $estilos = implode("\n", $bloques[1] ?? []);
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $estilos);

    expect(preg_match('#@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.'.preg_quote($clase, '#').'\s*\{[^}]*transition\s*:\s*none\s*!important#s', $css) === 1)
        ->toBeTrue("{$componente}: falta la guardia de movimiento reducido sobre .{$clase}");
    expect(str_contains(Blade::render($html), 'class="'.$clase.'"') || preg_match('#class="[^"]*\b'.preg_quote($clase, '#').'\b#', Blade::render($html)) === 1)
        ->toBeTrue("{$componente}: el elemento que anima no lleva la clase .{$clase}");

    preg_match_all('#/\*.*?\*/#s', $estilos, $comentarios);
    expect(preg_match('/@(once|endonce|if|endif)\b/', implode("\n", $comentarios[0])) === 0)
        ->toBeTrue("{$componente}: DESIGN §8 trampa 5, directiva nombrada dentro de un comentario CSS");
})->with([
    'progress' => ['progress', 'muni-progress__relleno', '<x-muni::progress :value="64" label="Avance del trámite" />'],
    'chart-donut' => ['chart-donut', 'muni-donut__arco', '<x-muni::chart-donut :segments="[[\'label\' => \'Aprobadas\', \'value\' => 42, \'tone\' => \'ok\']]" />'],
    'chart-bar' => ['chart-bar', 'muni-chartbar__bar', '<x-muni::chart-bar :data="[[\'label\' => \'Lun\', \'value\' => 12]]" />'],
]);

it('chart-donut oculta al lector el svg y deja el dato como texto en la leyenda (4.1.2 y 1.1.1)', function () {
    $html = Blade::render('<x-muni::chart-donut :segments="$s" center-label="128" />', ['s' => [
        ['label' => 'Aprobadas', 'value' => 86, 'tone' => 'ok'],
        ['label' => 'En revisión', 'value' => 30, 'tone' => 'warn'],
        ['label' => 'Rechazadas', 'value' => 12, 'tone' => 'danger'],
    ]]);
    $html = (string) preg_replace('#<style\b.*?</style>#s', '', $html);

    expect(preg_match('#<svg\b[^>]*aria-hidden="true"#', $html) === 1)->toBeTrue('el <svg> del donut va aria-hidden');
    expect(preg_match('#<svg\b[^>]*focusable="false"#', $html) === 1)->toBeTrue('el <svg> no es parada de foco en navegadores viejos');
    expect(substr_count($html, '<circle'))->toBe(4);

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    foreach (['Aprobadas' => '86', 'En revisión' => '30', 'Rechazadas' => '12'] as $rotulo => $valor) {
        $nodo = $xp->query('//span[normalize-space(.)="'.$rotulo.'"]')->item(0);
        expect($nodo)->not->toBeNull("la leyenda nombra «{$rotulo}» como texto");
        expect($xp->query('ancestor-or-self::*[@aria-hidden="true"]', $nodo)->length)->toBe(0, "«{$rotulo}» no queda oculto");
        expect($xp->query('following-sibling::span[normalize-space(.)="'.$valor.'"]', $nodo)->length)->toBe(1, "el valor {$valor} va junto a «{$rotulo}»");
    }

    // La muestra de color es decoración: el nombre del segmento ya está al lado.
    $muestras = $xp->query('//span[contains(@style,"width:10px")]');
    expect($muestras->length)->toBe(3);
    foreach ($muestras as $m) {
        expect($m->getAttribute('aria-hidden'))->toBe('true');
    }
});

/*
 * El banco de navegador, en `build/anillo-de-avance/` (ignorado por git). Cuatro
 * páginas, como la vitrina (DESIGN §7): dentro de un panel Filament solo se
 * carga `muni-ui-filament.css`, que vale distinto en 30 de 32 tokens.
 */
it('genera el banco de navegador en build/anillo-de-avance/', function () {
    $dir = __DIR__.'/../build/anillo-de-avance';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $anillos = Blade::render(
        '<div class="banco-grid">'
        .'<x-muni::ring :value="31" :max="40" label="Cupos ocupados hoy" id="anillo-cupos" />'
        .'<x-muni::ring :value="92" label="ARCOP respondidas en plazo" tone="ok" aria-label="Solicitudes ARCOP respondidas dentro del plazo legal" />'
        .'<x-muni::ring :value="18" label="Patentes por vencer" tone="warn" />'
        .'<x-muni::ring :value="7" label="Giros rechazados" tone="danger" :show-value="false" />'
        .'<x-muni::ring :value="55" tone="info" :size="64" />'
        .'</div>'
    );

    // La familia de la barrida: los otros tres que animaban con duración fija.
    $familia = Blade::render(
        '<div class="banco-familia">'
        .'<x-muni::progress :value="64" label="Avance del trámite" show-value />'
        .'<x-muni::chart-donut :segments="$segmentos" center-label="128" />'
        .'<x-muni::chart-bar :data="$barras" />'
        .'</div>',
        [
            'segmentos' => [
                ['label' => 'Aprobadas', 'value' => 86, 'tone' => 'ok'],
                ['label' => 'En revisión', 'value' => 30, 'tone' => 'warn'],
                ['label' => 'Rechazadas', 'value' => 12, 'tone' => 'danger'],
            ],
            'barras' => [
                ['label' => 'Lun', 'value' => 12], ['label' => 'Mar', 'value' => 19],
                ['label' => 'Mié', 'value' => 8], ['label' => 'Jue', 'value' => 23],
            ],
        ]
    );
    $anillos .= $familia;

    $paginas = [
        'claro' => ['light', cssMuniUi(), false],
        'oscuro' => ['dark', cssMuniUi(), false],
        'panel-claro' => ['light', cssMuniUiFilament(), true],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel]) {
        $oscuro = $tema === 'dark';
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'h1{font-size:20px;margin:0 0 16px}'
            .'.banco-grid,.banco-familia{display:flex;flex-wrap:wrap;gap:24px;padding:16px;background:var(--muni-surface);border-radius:var(--muni-radius)}'
            .'.banco-familia{margin-top:16px}.banco-familia>*{flex:1 1 220px;min-width:0}';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<h1>Agenda de licencias — hoy</h1><section class="fi-section">'.$anillos.'</section></main></div></body>'
            : '<body><main id="muni-contenido" tabindex="-1"><h1>Agenda de licencias — hoy</h1>'.$anillos.'</main></body>';

        // En el panel no se pone data-muni-theme (DESIGN §3): manda `.dark`.
        $raiz = $panel
            ? '<html lang="es"'.($oscuro ? ' class="dark"' : '').'>'
            : '<html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').'>';

        file_put_contents($dir.'/'.$nombre.'.html', '<!doctype html>'.$raiz
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco ring — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>');
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});

/*
 * Lo que un test de Blade no ve, medido en Chromium y Firefox: el árbol ARIA
 * (un solo anuncio del valor), la transición computada con y sin movimiento
 * reducido —también en el panel— y que al cambiar el avance el arco anima en
 * 600 ms y no salta. Se SALTA, no se finge, sin `.venv-a11y`.
 */
it('en Chromium y Firefox: progressbar con un solo anuncio del valor y arco que anima 600 ms salvo con movimiento reducido', function () {
    $python = __DIR__.'/../.venv-a11y/bin/python';

    if (! is_executable($python)) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg($python).' '.escapeshellarg(__DIR__.'/navegador/anillo-de-avance.py').' '
        .escapeshellarg(__DIR__.'/../build/anillo-de-avance').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de <x-muni::ring> falló:\n".implode("\n", $lineas));
});
