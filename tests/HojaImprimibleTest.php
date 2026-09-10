<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| LA HOJA IMPRIMIBLE
|--------------------------------------------------------------------------
|
| `grep -rn -i "media print"` sobre el repo daba CERO coincidencias: imprimir
| en modo oscuro salía una hoja inservible, la cabecera de una nómina larga no
| se repetía entre páginas y la franja de la fila morosa desaparecía, porque
| los navegadores no imprimen fondos.
|
| Lo que se construye acá es SOLO la primera mitad del candidato `doc-imprimible`
| tal como lo dejó la corrección exigida: una hoja delgada —membrete, folio,
| ranuras de datos y de firma, pie con paginación y verificador— con el CUERPO
| LIBRE. Las plantillas de certificado, acta y oficio quedan explícitamente
| fuera del paquete: la estructura de un oficio o de un decreto la fijan la Ley
| 19.880 y el municipio, no el sistema de diseño, y seis sistemas heredando una
| plantilla inventada es un problema legal, no un ahorro.
|
| El reparto del CSS no es un detalle de gusto, es DESIGN §7: dentro de un panel
| Filament solo se inyecta `muni-ui-filament.css`, así que una regla que viva
| únicamente en `muni-ui.css` no existe en el panel y no da ningún error. Por eso
| las reglas base de impresión van en LOS DOS archivos, el layout de la hoja
| viaja con el componente, y las reglas de impresión de TABLA van dentro del
| bloque de estilos de `data-table`: si la hoja se emitiera después, el bloque de
| `data-table` ya está emitido y gana.
|
| Ojo con Pest: `toContain($aguja, 'mensaje')` NO acepta un mensaje —el segundo
| argumento es otra aguja—, así que todo va con `expect(bool)->toBeTrue('…')`.
*/

/** Ruta de una vista de componente del paquete. */
function rutaDeLaVista(string $nombre): string
{
    return __DIR__.'/../resources/views/components/'.$nombre.'.blade.php';
}

/** El fuente crudo de un componente, o cadena vacía si todavía no existe. */
function fuenteDeLaVista(string $nombre): string
{
    $ruta = rutaDeLaVista($nombre);

    return file_exists($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * El fuente SIN comentarios CSS ni comentarios Blade.
 *
 * Sin esto, buscar un patrón en el fuente de un componente matchea la prosa que
 * explica por qué ese patrón está prohibido, y la prueba pasa o falla por el
 * texto de una explicación.
 */
function sinComentarios(string $fuente): string
{
    return (string) preg_replace(['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'], '', $fuente);
}

/** El HTML servido de la hoja. */
function hojaHtml(string $atributos = '', string $contenido = ''): string
{
    return Blade::render("<x-muni::hoja {$atributos}>{$contenido}</x-muni::hoja>");
}

/** El texto que un ojo humano lee, sin etiquetas ni bloques de estilo. */
function textoImpreso(string $html): string
{
    $sinEstilos = (string) preg_replace('#<(script|style)\b.*?</\1>#s', ' ', $html);
    $texto = html_entity_decode(strip_tags($sinEstilos), ENT_QUOTES, 'UTF-8');

    return trim((string) preg_replace('/\s+/u', ' ', $texto));
}

/** Los dos CSS publicables del paquete, ruta relativa => contenido. */
function hojasCssDelPaquete(): array
{
    return [
        'resources/css/muni-ui.css' => (string) file_get_contents(__DIR__.'/../resources/css/muni-ui.css'),
        'resources/css/muni-ui-filament.css' => (string) file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css'),
    ];
}

/**
 * El contenido de TODOS los bloques de impresión de una fuente, concatenado.
 *
 * Cuenta llaves en vez de usar una expresión regular: dentro de un bloque de
 * impresión hay reglas anidadas, y `[^}]*` cortaría en la primera de ellas.
 */
function bloquesDeImpresion(string $fuente): string
{
    $fuente = sinComentarios($fuente);
    $salida = '';
    $desde = 0;

    while (preg_match('/@media[^{;]*\bprint\b[^{;]*\{/i', $fuente, $m, PREG_OFFSET_CAPTURE, $desde)) {
        $inicio = $m[0][1] + strlen($m[0][0]);
        $nivel = 1;
        $i = $inicio;
        $largo = strlen($fuente);

        while ($i < $largo && $nivel > 0) {
            if ($fuente[$i] === '{') {
                $nivel++;
            } elseif ($fuente[$i] === '}') {
                $nivel--;
            }

            $i++;
        }

        $salida .= substr($fuente, $inicio, max(0, $i - $inicio - 1))."\n";
        $desde = $i;
    }

    return $salida;
}

/** Lo que hay en una fuente ANTES del primer bloque de impresión. */
function antesDeImpresion(string $css): string
{
    $corte = mb_stripos($css, '@media print');

    return $corte === false ? $css : mb_substr($css, 0, $corte);
}

/*
|--------------------------------------------------------------------------
| El componente
|--------------------------------------------------------------------------
*/

it('existe la hoja y sale con su folio y su leyenda de verificación', function () {
    expect(file_exists(rutaDeLaVista('hoja')))->toBeTrue(
        'No existe resources/views/components/hoja.blade.php: no hay ninguna hoja tamaño carta '.
        'lista para imprimir o exportar a PDF desde el navegador.'
    );

    $html = hojaHtml(
        'type="Acta de fiscalización" folio="2026-4831" '.
        'verification="Verifique este documento en municipalidadgraneros.cl/verificar"',
        '<p data-cuerpo>Fiscalización de patente comercial.</p>'
    );

    $texto = textoImpreso($html);

    expect(str_contains($texto, '2026-4831'))->toBeTrue(
        'El folio no aparece en la hoja. Sin folio impreso el documento no se puede rastrear '.
        'contra la bitácora de emisión. Texto: '.$texto
    );

    expect(str_contains($texto, 'municipalidadgraneros.cl/verificar'))->toBeTrue(
        'La leyenda de verificación que pasa el host no se imprime: el pie es lo único que '.
        'permite comprobar que el papel corresponde a un documento emitido. Texto: '.$texto
    );

    expect(str_contains($texto, 'Acta de fiscalización'))->toBeTrue(
        'El tipo de documento no aparece: la hoja no dice qué es. Texto: '.$texto
    );

    expect(str_contains($html, 'data-cuerpo'))->toBeTrue(
        'El slot por defecto se descarta en silencio, así que el cuerpo del documento no se rinde.'
    );
});

it('trae el membrete institucional y reutiliza el escudo del paquete', function () {
    $html = hojaHtml('organization="Municipalidad de Graneros" unit="Dirección de Tránsito"');

    expect(str_contains($html, 'Escudo de la Municipalidad de Graneros'))->toBeTrue(
        'La hoja no emite <x-muni::gob-escudo>: se dibujó un escudo propio en vez de reutilizar el '.
        'componente que ya resuelve la ruta publicada y el texto alternativo.'
    );

    $texto = textoImpreso($html);

    foreach (['Municipalidad de Graneros' => 'el organismo', 'Dirección de Tránsito' => 'la unidad emisora'] as $aguja => $que) {
        expect(str_contains($texto, $aguja))->toBeTrue(
            "El membrete no nombra {$que}. Texto: ".$texto
        );
    }
});

it('tiene ranuras con nombre para los datos y la firma, y deja el cuerpo LIBRE', function () {
    $html = hojaHtml(
        'folio="2026-0001"',
        '<x-slot:emisor><p data-emisor>Unidad de Rentas</p></x-slot:emisor>'
        .'<x-slot:titular><p data-titular>Juana Pérez</p></x-slot:titular>'
        .'<x-slot:firma><p data-firma>Directora de Tránsito</p></x-slot:firma>'
        .'<p data-cuerpo>Lo que el sistema decida escribir.</p>'
    );

    foreach ([
        'data-emisor' => 'la ranura del emisor',
        'data-titular' => 'la ranura del titular',
        'data-firma' => 'la ranura de la firma',
        'data-cuerpo' => 'el cuerpo libre',
    ] as $aguja => $que) {
        expect(str_contains($html, $aguja))->toBeTrue(
            "No se rinde {$que}: el contenido del consumidor se descarta en silencio."
        );
    }

    /*
     * La segunda mitad del candidato quedó FUERA del paquete a propósito. Si
     * alguien la vuelve a meter, el paquete empieza a fijar la estructura de un
     * acto administrativo, que fijan la Ley 19.880 y el municipio.
     */
    $codigo = mb_strtoupper(sinComentarios(fuenteDeLaVista('hoja')));

    foreach (['VISTOS', 'CONSIDERANDO', 'RESUELVO', 'POR TANTO', 'CERTIFICA'] as $palabra) {
        expect(str_contains($codigo, $palabra))->toBeFalse(
            "La hoja escribe «{$palabra}»: el paquete se puso a fijar la estructura interna de un ".
            'oficio o un decreto, que fijan la Ley 19.880 y el municipio, no el sistema de diseño.'
        );
    }
});

it('el botón de imprimir no usa onclick inline, que una CSP estricta bloquea', function () {
    $html = hojaHtml('folio="2026-0001"');

    expect(str_contains($html, 'onclick'))->toBeFalse(
        'El botón dispara con onclick inline: una CSP estricta sin «unsafe-inline» lo bloquea y el '.
        'botón deja de hacer nada, sin ningún error visible para el funcionario.'
    );

    expect(str_contains($html, 'window.print()'))->toBeTrue(
        'No hay ningún botón que abra el diálogo de impresión.'
    );

    expect((bool) preg_match('/<button\b[^>]*type="button"[^>]*>/', $html))->toBeTrue(
        'El disparador no es un <button type="button"> real: dentro de un formulario un <button> sin '.
        'type envía el formulario, y un <div> con @click no se alcanza con teclado.'
    );

    expect((bool) preg_match('/x-data[^>]*@click="window\.print\(\)"/s', $html))->toBeTrue(
        'El @click no cuelga de ningún x-data: sin un scope de Alpine que lo enlace el clic nunca se '.
        'despacha (el mismo defecto que dejó muerto el burger de dashboard-shell).'
    );

    $codigo = sinComentarios(fuenteDeLaVista('hoja'));

    expect((bool) preg_match('/outline\s*:\s*3px solid var\(--muni-focus, var\(--muni-accent, #767676\)\)/', $codigo))->toBeTrue(
        'El botón no declara el indicador de foco del contrato: outline de 3px con la cadena de '.
        'respaldo, nunca box-shadow, que dentro de Filament se pierde.'
    );

    $impresion = bloquesDeImpresion(fuenteDeLaVista('hoja'));

    expect((bool) preg_match('/\.muni-hoja__acciones[^{]*\{[^}]*display\s*:\s*none/', $impresion))->toBeTrue(
        'El botón «Imprimir» no se oculta al imprimir: sale dibujado en el papel del documento.'
    );
});

it('numera las páginas y no rehace las reglas de tabla que ya emite data-table', function () {
    $hoja = sinComentarios(fuenteDeLaVista('hoja'));
    $impresion = bloquesDeImpresion($hoja);

    // El contador SOLO puede vivir en una caja de margen de @page. Pedido desde el
    // pie del cuerpo no resuelve en ningún motor y degrada a «Página 0 de 0»
    // (Chromium) o «Página de» (Firefox), medido con page.pdf() el 2026-09-10.
    // Un acta municipal con una numeración inventada es peor que una sin numerar:
    // dice algo, y lo que dice es mentira.
    // Ojo: las reglas @page viven FUERA del @media print, así que hay que mirar
    // la hoja entera, no solo los bloques de impresión.
    $cajaDeMargen = (bool) preg_match('/@bottom-[a-z]+\s*\{[^}]*counter\(page\)/', $hoja);
    expect($cajaDeMargen)->toBeTrue(
        'El pie no numera las páginas: una nómina de tres hojas sueltas no se puede recomponer.'
    );

    $fueraDeLaCaja = preg_replace('/@bottom-[a-z]+\s*\{[^}]*\}/', '', $hoja);
    expect(str_contains($fueraDeLaCaja, 'counter(page)'))->toBeFalse(
        'Hay un counter(page) fuera de una caja de margen de @page. Ahí no resuelve y se '.
        'imprime «Página 0 de 0»: el documento sale con un dato falso en vez de sin dato.'
    );

    expect(str_contains($hoja, 'table-footer-group'))->toBeTrue(
        'El pie no se repite reservando su alto: con position:fixed el contenido se imprime '.
        'por debajo y los bordes de la tabla cruzan el folio.'
    );

    foreach (['table-header-group', 'muni-row--danger', 'muni-num'] as $ajeno) {
        expect(str_contains($hoja, $ajeno))->toBeFalse(
            "La hoja declara «{$ajeno}», que es de la tabla. El bloque de estilos de data-table se ".
            'emite una sola vez y, si la tabla va antes que la hoja —que es lo normal—, el suyo ya '.
            'está emitido y gana: la copia de la hoja no se aplicaría nunca.'
        );
    }

    expect(str_contains($hoja, '@page'))->toBeTrue(
        'La hoja no declara márgenes de página propios: si el host no publicó el CSS del paquete, '.
        'el documento sale con los márgenes por defecto del navegador.'
    );

    expect(file_exists(__DIR__.'/../resources/css/muni-print.css'))->toBeFalse(
        'Se creó un tercer CSS suelto. Nadie lo publica —MuniPanel inyecta filament.css y el Vite '.
        'del host compila muni-ui.css—, así que no llegaría a ninguna parte.'
    );
});

it('la hoja respeta el contrato del paquete', function () {
    $codigo = sinComentarios(fuenteDeLaVista('hoja'));

    expect($codigo)->not->toBe('', 'No existe el componente «hoja».');

    expect(str_contains($codigo, 'uniqid('))->toBeFalse(
        'Arma ids con uniqid(): cambian en cada render y ensucian el diffing de Livewire. El id sale '.
        'de una prop.'
    );

    $sinRespaldoDeFoco = str_replace('#767676', '', $codigo);

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $sinRespaldoDeFoco, $colores);

    expect($colores[0] ?? [])->toBe(
        [],
        'Escribe colores literales, así que ningún municipio puede cambiarlos redefiniendo tokens: '
        .implode(' | ', $colores[0] ?? [])
    );

    expect((bool) preg_match('#@once\s*(?:\R|\s)*<style>#', $codigo))->toBeTrue(
        'El bloque <style> no está dentro de @once: se repite una vez por cada hoja de la página.'
    );

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($codigo, $prohibida))->toBeFalse(
            "Usa «{$prohibida}», que no existe en los dos majors de Livewire que el paquete soporta."
        );
    }

    // Los ids salen de una prop, así que dos renders seguidos son idénticos.
    expect(hojaHtml('id="hoja-acta" type="Acta"'))->toBe(
        hojaHtml('id="hoja-acta" type="Acta"'),
        'Dos renders seguidos con las mismas props dan HTML distinto: hay un id que no es determinista.'
    );

    expect(str_contains(hojaHtml('id="hoja-acta" type="Acta"'), 'hoja-acta'))->toBeTrue(
        'La prop id no manda sobre los ids que arma el componente.'
    );
});

/*
|--------------------------------------------------------------------------
| El reparto de las reglas de impresión
|--------------------------------------------------------------------------
*/

it('data-table repite la cabecera entre páginas y no parte las filas', function () {
    $dt = sinComentarios(fuenteDeLaVista('data-table'));

    expect((bool) preg_match('/@media\s+print/', $dt))->toBeTrue(
        'data-table no tiene reglas de impresión: la nómina sale recortada al alto del marco.'
    );

    $compacto = (string) preg_replace('/\s+/', '', $dt);

    expect(str_contains($compacto, 'display:table-header-group'))->toBeTrue(
        'La cabecera de la tabla no se repite entre páginas: a partir de la segunda hoja las columnas '.
        'de un acta de fiscalización no dicen qué son.'
    );

    expect(str_contains($compacto, 'break-inside:avoid'))->toBeTrue(
        'Las filas se parten entre dos páginas.'
    );

    $impresion = bloquesDeImpresion($dt);

    expect(str_contains($impresion, 'muni-row--danger'))->toBeTrue(
        'La franja de la fila con problema no se sustituye en impresión. La banda es una box-shadow '.
        'y los navegadores no imprimen fondos: en papel la fila morosa queda igual que las demás.'
    );

    expect((bool) preg_match('/border-left\s*:\s*[^;}]*solid/', $impresion))->toBeTrue(
        'La banda no se sustituye por un borde SÓLIDO, que es lo único de esa familia que el '.
        'navegador sí imprime.'
    );

    expect((bool) preg_match('/content\s*:\s*"[^"]+"/', $impresion))->toBeTrue(
        'El estado de la fila queda solo en el color y en el borde. En papel el color tampoco puede '.
        'ser el único portador (WCAG 2.2 AA 1.4.1): hace falta una marca de texto.'
    );

    expect(str_contains($impresion, 'muni-num'))->toBeTrue(
        'Las cifras y los RUT pierden la mono tabular al imprimir: una columna de montos que no '.
        'alinea las unidades no se lee de un vistazo, y en papel menos.'
    );
});

it('los dos CSS del paquete traen las reglas base de impresión', function () {
    foreach (hojasCssDelPaquete() as $ruta => $css) {
        expect((bool) preg_match('/@media\s+print/', $css))->toBeTrue(
            "«{$ruta}» no tiene ninguna regla de impresión. Los dos hacen falta: MuniPanel inyecta ".
            'solo filament.css dentro del panel, y muni-ui.css lo compila el Vite del host para sus '.
            'vistas públicas (DESIGN §7).'
        );

        expect((bool) preg_match('/@page\s*\{[^}]*margin/', $css))->toBeTrue(
            "«{$ruta}» no declara @page con márgenes: el documento sale con los del navegador."
        );

        $impresion = bloquesDeImpresion($css);

        foreach ([
            'muni-skip-link' => 'el enlace de salto',
            'muni-sb' => 'la barra lateral',
            'muni-ds__top' => 'la barra superior del armazón de escritorio',
            'muni-toast' => 'los avisos',
            'role="dialog"' => 'el modal y el drawer',
            'muni-no-print' => 'la clase con la que el host marca su propio cromo',
        ] as $aguja => $que) {
            expect(str_contains($impresion, $aguja))->toBeTrue(
                "«{$ruta}» no oculta en impresión {$que}."
            );
        }
    }
});

it('fuerza la paleta clara dentro del media query, sin pisar la pantalla', function () {
    foreach (hojasCssDelPaquete() as $ruta => $css) {
        $impresion = bloquesDeImpresion($css);

        expect(str_contains($impresion, '--muni-surface:'))->toBeTrue(
            "«{$ruta}» no fuerza la paleta clara al imprimir: en modo oscuro sale una hoja negra."
        );

        expect(str_contains($impresion, '.dark'))->toBeTrue(
            "«{$ruta}» fuerza la paleta pero no neutraliza los activadores explícitos de oscuro ".
            '(.dark de Filament, [data-theme=dark] de las PWA), que ganan por cascada.'
        );
    }

    // Y no al revés: la pantalla sigue siendo la de antes.
    $pantalla = antesDeImpresion(hojasCssDelPaquete()['resources/css/muni-ui.css']);

    expect(str_contains($pantalla, '--muni-surface: #111620'))->toBeTrue(
        'El bloque oscuro de PANTALLA se perdió: las reglas de impresión se escribieron fuera del '.
        '@media print y ahora se aplican también en el monitor del funcionario.'
    );
});

it('ningún CSS del paquete fuerza la impresión de fondos', function () {
    $fuentes = hojasCssDelPaquete();

    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') ?: [] as $vista) {
        $fuentes['resources/views/components/'.basename($vista)] = (string) file_get_contents($vista);
    }

    foreach ($fuentes as $ruta => $contenido) {
        expect((bool) preg_match('/-?(?:webkit-)?print-color-adjust\s*:\s*exact/i', sinComentarios($contenido)))->toBeFalse(
            "«{$ruta}» usa print-color-adjust: exact. El usuario puede desactivar los gráficos de ".
            'fondo igual, así que no garantiza nada, y donde sí se respeta el tóner lo paga el '.
            'municipio. Lo que se imprime tiene que leerse sin fondos.'
        );
    }
});
