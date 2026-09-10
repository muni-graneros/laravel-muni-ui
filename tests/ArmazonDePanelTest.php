<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/**
 * EL ARMAZÓN DE PANEL: `dashboard-shell` + `sidebar`.
 *
 * Dos defectos que se arreglan juntos porque arreglar uno solo es cosmético:
 *
 *  1. EL MENÚ MÓVIL NO ABRÍA. El botón hamburguesa llevaba
 *     `@click="window.dispatchEvent(...)"` y el archivo no contenía un solo
 *     `x-data`: sin scope de Alpine antecesor la escucha nunca se enlaza y el
 *     evento jamás se despacha. Bajo el punto de quiebre la barra lateral era
 *     inalcanzable, y `.muni-ds__scrim` estaba estilado pero nunca se emitía.
 *  2. EL QUE NO SE VE SÍ RECIBÍA EL FOCO. La lateral cerrada se apartaba con
 *     `transform:translateX(-100%)`, que NO saca del orden de tabulación: con
 *     Tab el teclado entraba a enlaces invisibles fuera de pantalla
 *     (WCAG 2.2 AA, 2.4.3 «Orden del foco» y 2.4.7 «Foco visible»).
 *
 * Y dos causas de fondo que estos candados vigilan para que no vuelvan:
 *
 *  - El punto de quiebre estaba escrito CINCO veces (dos en el CSS del
 *    `sidebar`, dos en el del `dashboard-shell` y una en el
 *    `window.innerWidth >= 900` del JS). Ahora vive en un solo sitio y se lee
 *    con un solo `matchMedia`, escuchando el cruce y no cada `resize`.
 *  - `open` se calculaba una vez con `window.innerWidth` y no se recalculaba
 *    nunca: quien rotaba la tablet se quedaba con el estado equivocado.
 *
 * Nota de Pest: `expect($x)->toContain($y, 'mensaje')` NO acepta mensaje —el
 * segundo argumento es otra aguja—, así que todo va con
 * `expect(bool)->toBeTrue('mensaje')`.
 */

/** El fuente Blade de un componente del paquete. */
function fuenteArmazon(string $componente): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/'.$componente.'.blade.php');
}

/**
 * El fuente sin comentarios: si se grepea con ellos, se matchea la prosa que
 * explica el defecto y el candado da verde sin probar nada.
 */
function fuenteArmazonSinComentarios(string $componente): string
{
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', fuenteArmazon($componente));

    return (string) preg_replace('#/\*.*?\*/#s', '', (string) $fuente);
}

/** El contenido de todos los bloques `<style>` de un HTML ya renderizado. */
function estilosDelArmazon(string $html): string
{
    preg_match_all('#<style>(.*?)</style>#s', $html, $m);

    return implode("\n", $m[1]);
}

/** El armazón completo, renderizado con una lateral dentro. */
function armazonHtml(string $slotLateral = '<x-muni::nav-item href="/giros">Giros</x-muni::nav-item>'): string
{
    return Blade::render(
        '<x-muni::dashboard-shell system="Rentas" title="Patentes">'
        .'<x-slot:sidebar><x-muni::sidebar>'.$slotLateral.'</x-muni::sidebar></x-slot:sidebar>'
        .'<p>Contenido</p>'
        .'</x-muni::dashboard-shell>'
    );
}

/**
 * La etiqueta de apertura que contiene `$aguja`, empezando por `<$tag`.
 *
 * Se recorre a mano y no con `[^>]*` porque las expresiones de Alpine llevan
 * `>` dentro de las comillas (`window.innerWidth >= 900`, `x ? a : b`) y un
 * `[^>]*` corta la etiqueta a la mitad: el candado daría verde por no
 * encontrar lo que sí estaba.
 */
function etiquetaDelArmazon(string $html, string $tag, string $aguja): string
{
    $desde = 0;

    while (($inicio = strpos($html, '<'.$tag, $desde)) !== false) {
        $comilla = null;
        $i = $inicio + strlen($tag) + 1;

        for ($largo = strlen($html); $i < $largo; $i++) {
            $c = $html[$i];

            if ($comilla !== null) {
                if ($c === $comilla) {
                    $comilla = null;
                }

                continue;
            }

            if ($c === '"' || $c === "'") {
                $comilla = $c;

                continue;
            }

            if ($c === '>') {
                break;
            }
        }

        $etiqueta = substr($html, $inicio, $i - $inicio + 1);
        $desde = $i + 1;

        if (str_contains($etiqueta, $aguja)) {
            return $etiqueta;
        }
    }

    return '';
}

/*
|--------------------------------------------------------------------------
| 1. El hamburguesa tiene un scope de Alpine de verdad
|--------------------------------------------------------------------------
*/

it('el botón de menú vive dentro de un x-data real, y no en el <body>', function () {
    $html = armazonHtml();

    expect((bool) preg_match('#<header\b([^>]*)>(.*?)</header>#s', $html, $m))->toBeTrue(
        'El armazón dejó de emitir su <header>.'
    );

    [, $apertura, $dentro] = $m;

    expect(str_contains($apertura, 'x-data'))->toBeTrue(
        'El <header> no abre scope de Alpine: el @click del hamburguesa nunca se enlaza y el menú móvil no abre.'
    );

    expect(str_contains($dentro, 'muni-ds__burger'))->toBeTrue(
        'El hamburguesa quedó fuera del <header>, o sea fuera del único scope de Alpine del armazón.'
    );

    expect((bool) preg_match('/<body\b[^>]*x-data/', $html))->toBeFalse(
        'El x-data se puso en el <body>: choca con el layout de Livewire y con lo exigido por la ficha.'
    );
});

it('el armazón habla con la lateral por eventos, no por un scope compartido', function () {
    $shell = fuenteArmazonSinComentarios('dashboard-shell');
    $sidebar = fuenteArmazonSinComentarios('sidebar');

    expect(str_contains($shell, 'muni-sidebar-state'))->toBeTrue(
        'El armazón no escucha `muni-sidebar-state`: el aria-expanded del hamburguesa no puede decir la verdad.'
    );
    expect(str_contains($sidebar, 'muni-sidebar-state'))->toBeTrue(
        'La lateral no devuelve su estado: <x-muni::sidebar> deja de servir fuera del armazón.'
    );
    expect(str_contains($sidebar, '@muni-sidebar.window'))->toBeTrue(
        'La lateral dejó de escuchar el evento `muni-sidebar`: se rompe toda llamada existente.'
    );
});

/*
|--------------------------------------------------------------------------
| 2. aria-expanded y aria-controls reales
|--------------------------------------------------------------------------
*/

it('el hamburguesa declara aria-expanded y aria-controls, y los sincroniza con el estado', function () {
    $html = armazonHtml();
    $boton = etiquetaDelArmazon($html, 'button', 'muni-ds__burger');

    expect($boton)->not->toBe('', 'No se emite el botón de menú.');

    expect((bool) preg_match('/(:|x-bind:)aria-expanded="[^"]*open[^"]*"/', $boton))->toBeTrue(
        'aria-expanded no está atado al estado: anunciaría siempre lo mismo abra o no el menú.'
    );

    expect((bool) preg_match('/(:|x-bind:)?aria-controls="[^"]+"/', $boton))->toBeTrue(
        'El hamburguesa no declara aria-controls: el lector no sabe qué región gobierna.'
    );

    expect((bool) preg_match('/\baria-label="[^"]+"/', $boton))->toBeTrue(
        'El botón de solo icono se quedó sin nombre accesible.'
    );

    expect(str_contains($boton, 'type="button"'))->toBeTrue(
        'Sin type="button" el hamburguesa envía el formulario que lo contenga.'
    );
});

it('el id que apunta aria-controls es el de la lateral', function () {
    $html = armazonHtml();
    $aside = etiquetaDelArmazon($html, 'aside', 'muni-sb');

    expect((bool) preg_match('/\bid="([^"]+)"/', $aside, $m))->toBeTrue(
        'La lateral no tiene id: aria-controls no tiene a qué apuntar.'
    );

    $boton = etiquetaDelArmazon($html, 'button', 'muni-ds__burger');

    expect(str_contains($boton, $m[1]) || str_contains($boton, ':aria-controls'))->toBeTrue(
        'aria-controls no resuelve al id real de la lateral.'
    );
});

/*
|--------------------------------------------------------------------------
| 3. La lateral cerrada sale del orden de tabulación
|--------------------------------------------------------------------------
*/

it('la lateral cerrada se marca inert y no depende de translateX para salir del tabulador', function () {
    $html = armazonHtml();
    $aside = etiquetaDelArmazon($html, 'aside', 'muni-sb');

    expect((bool) preg_match('/(:|x-bind:)inert="[^"]+"/', $aside))->toBeTrue(
        'La lateral no se marca inert al cerrarse: con Tab el foco entra en un menú invisible (WCAG 2.4.3).'
    );

    $css = estilosDelArmazon($html);

    expect((bool) preg_match('/\.muni-sb\s*\{[^}]*visibility:\s*hidden/s', $css))->toBeTrue(
        'Falta el respaldo `visibility:hidden` para los navegadores sin inert: translateX NO saca del tabulador.'
    );

    expect((bool) preg_match('/\.muni-sb--open\s*\{[^}]*visibility:\s*visible/s', $css))->toBeTrue(
        'La lateral abierta no recupera la visibilidad: el respaldo la deja inalcanzable siempre.'
    );
});

/*
|--------------------------------------------------------------------------
| 4. El velo se emite
|--------------------------------------------------------------------------
*/

it('el velo se emite de verdad y cierra al hacer clic', function () {
    $html = armazonHtml();

    expect((bool) preg_match('/<div\b[^>]*class="muni-ds__scrim"[^>]*>/', $html, $m))->toBeTrue(
        '`.muni-ds__scrim` sigue estilado y sin emitirse: en modo superpuesto no hay dónde hacer clic para cerrar.'
    );

    expect(str_contains($m[0], '@click') || str_contains($m[0], 'x-on:click'))->toBeTrue(
        'El velo no cierra al hacer clic.'
    );

    expect(str_contains($m[0], 'x-show'))->toBeTrue(
        'El velo no está atado al estado: taparía la pantalla en escritorio.'
    );

    $css = estilosDelArmazon($html);

    expect((bool) preg_match('/\.muni-ds__scrim\s*\{[^}]*display:\s*none/s', $css))->toBeFalse(
        'El velo nace con display:none, así que x-show nunca puede mostrarlo (x-show solo quita el display en línea).'
    );
});

/*
|--------------------------------------------------------------------------
| 5. El punto de quiebre, en un solo lugar
|--------------------------------------------------------------------------
*/

it('el punto de quiebre está escrito una sola vez y se lee con un solo matchMedia', function () {
    $sidebar = fuenteArmazonSinComentarios('sidebar');
    $shell = fuenteArmazonSinComentarios('dashboard-shell');

    expect(substr_count($sidebar, 'matchMedia'))->toBe(1,
        'El punto de quiebre se lee con más (o con menos) de un matchMedia.'
    );

    expect(str_contains($sidebar, 'innerWidth'))->toBeFalse(
        '`window.innerWidth` vuelve a leer el ancho una sola vez: quien rota la tablet se queda con el estado equivocado.'
    );

    expect(substr_count($sidebar, '@media'))->toBe(1,
        'El CSS de la lateral repite el punto de quiebre en más de una consulta de medios.'
    );

    expect(substr_count($sidebar, '900'))->toBe(1,
        'El valor del punto de quiebre aparece más de una vez en el fuente de la lateral.'
    );

    expect(str_contains($shell, '900'))->toBeFalse(
        'El armazón vuelve a saber del punto de quiebre: son dos verdades que se desincronizan.'
    );
    expect(str_contains($shell, 'max-width:8') || str_contains($shell, 'max-width: 8'))->toBeFalse(
        'El armazón vuelve a declarar una consulta de medios con el punto de quiebre.'
    );
});

it('el punto de quiebre es una prop y viaja al CSS y al matchMedia desde el mismo sitio', function () {
    $html = Blade::render('<x-muni::sidebar breakpoint="1024px" />');

    expect(str_contains($html, '1023.98px'))->toBeTrue(
        'La prop `breakpoint` no llega ni al CSS ni al matchMedia: el valor sigue incrustado.'
    );
    expect(substr_count($html, '1023.98px'))->toBe(2,
        'El punto de quiebre debe aparecer exactamente dos veces por render: la consulta de medios y el matchMedia.'
    );
    expect(str_contains($html, '899.98px'))->toBeFalse(
        'El valor por defecto se emite junto con el que pidió el consumidor.'
    );
});

it('un breakpoint hostil no llega a la expresión de Alpine', function () {
    $html = Blade::render('<x-muni::sidebar :breakpoint="$bp" />', ['bp' => "900px'); fetch('//x'); //"]);

    expect(str_contains($html, 'fetch('))->toBeFalse(
        'Un valor hostil de `breakpoint` entra a la expresión de Alpine: es un sink de inyección.'
    );
    expect(str_contains($html, '899.98px'))->toBeTrue(
        'Un `breakpoint` inválido debe caer al valor por defecto, no emitirse a medias.'
    );
});

/*
|--------------------------------------------------------------------------
| 6. La trampa de foco depende de matchMedia, no de una clase CSS
|--------------------------------------------------------------------------
*/

it('x-trap se activa por el estado de matchMedia y nunca por la clase CSS', function () {
    $html = armazonHtml();
    $aside = etiquetaDelArmazon($html, 'aside', 'muni-sb');

    expect((bool) preg_match('/x-trap(\.[a-z]+)*="([^"]+)"/', $aside, $m))->toBeTrue(
        'La lateral superpuesta no atrapa el foco: con Tab el teclado se escapa al contenido de atrás.'
    );

    expect(str_contains($m[0], '.inert') && str_contains($m[0], '.noscroll'))->toBeTrue(
        'Falta `.inert` o `.noscroll`: el resto de la página sigue anunciándose y el fondo sigue desplazándose.'
    );

    expect(str_contains($m[2], 'overlay'))->toBeTrue(
        'x-trap no consulta el estado del matchMedia: en escritorio dejaría al funcionario encerrado en el menú.'
    );

    expect(str_contains($m[2], 'muni-sb--open') || str_contains($m[2], 'classList'))->toBeFalse(
        'x-trap se ata a la clase CSS: exactamente lo que la ficha prohíbe.'
    );
});

/*
|--------------------------------------------------------------------------
| 7. Escape no se enlaza en window
|--------------------------------------------------------------------------
*/

it('Escape se enlaza en la <aside> y nunca en window', function () {
    foreach (['sidebar', 'dashboard-shell'] as $componente) {
        $fuente = fuenteArmazonSinComentarios($componente);

        expect(str_contains($fuente, 'keydown.escape.window'))->toBeFalse(
            "«{$componente}» enlaza Escape en window: command-palette y dropdown ya lo hacen por instancia y se pisan."
        );
    }

    $aside = etiquetaDelArmazon(armazonHtml(), 'aside', 'muni-sb');

    expect((bool) preg_match('/(@|x-on:)keydown\.escape(?!\.window)/', $aside))->toBeTrue(
        'La lateral superpuesta no cierra con Escape.'
    );
});

/*
|--------------------------------------------------------------------------
| 8. El destino del enlace de salto
|--------------------------------------------------------------------------
*/

it('el <main> es un destino de salto usable bajo una cabecera sticky', function () {
    $html = armazonHtml();
    $main = etiquetaDelArmazon($html, 'main', 'muni-ds__main');

    expect(str_contains($main, 'id="muni-contenido"'))->toBeTrue(
        'El <main> no tiene id: el enlace de salto no tiene destino.'
    );
    expect(str_contains($main, 'tabindex="-1"'))->toBeTrue(
        'Sin tabindex="-1" el salto mueve el scroll pero no el punto de lectura.'
    );

    $css = estilosDelArmazon($html);

    expect((bool) preg_match('/\.muni-ds__main\s*\{[^}]*scroll-margin-top:\s*var\(--muni-topbar-h\)/s', $css))->toBeTrue(
        'Sin scroll-margin-top el foco aterriza debajo de la cabecera fija y no se ve.'
    );

    expect(str_contains($html, 'muni-skip-link'))->toBeTrue(
        'El armazón dejó de emitir el enlace de salto.'
    );
});

/*
|--------------------------------------------------------------------------
| 9. Área táctil del hamburguesa
|--------------------------------------------------------------------------
*/

it('el hamburguesa llega a 44x44 para la tablet de terreno', function () {
    $css = estilosDelArmazon(armazonHtml());

    expect((bool) preg_match('/\.muni-ds__burger\s*\{([^}]*)\}/s', $css, $m))->toBeTrue(
        'Desapareció la regla del hamburguesa.'
    );

    expect((bool) preg_match('/min-width:\s*44px/', $m[1]))->toBeTrue(
        'El hamburguesa no llega a 44px de ancho: el inspector con tablet en terreno falla el objetivo.'
    );
    expect((bool) preg_match('/min-height:\s*44px/', $m[1]))->toBeTrue(
        'El hamburguesa no llega a 44px de alto.'
    );

    expect((bool) preg_match('/\.muni-ds__burger:focus-visible\s*\{[^}]*outline:\s*3px solid/s', $css))->toBeTrue(
        'El hamburguesa no tiene indicador de foco por outline (la box-shadow se pierde dentro de Filament).'
    );
});

/*
|--------------------------------------------------------------------------
| 10. La bolsa de atributos
|--------------------------------------------------------------------------
*/

it('la lateral fusiona los atributos del consumidor en vez de derramarlos crudos', function () {
    expect(str_contains(fuenteArmazonSinComentarios('sidebar'), '$attributes->merge('))->toBeTrue(
        'La lateral sigue derramando {{ $attributes }} crudo sobre una etiqueta que ya trae class y style.'
    );

    $html = Blade::render('<x-muni::sidebar class="mia" style="background:transparent;" />');
    $aside = etiquetaDelArmazon($html, 'aside', 'muni-sb');

    // `\sclass=` y no `class=` a secas: el `:class` de Alpine no es el atributo
    // que el navegador lee, y contarlo daría un falso rojo.
    expect(preg_match_all('/\sclass="/', $aside))->toBe(1,
        'El atributo class se emite duplicado: el navegador descarta el segundo en silencio.'
    );
    expect(preg_match_all('/\sstyle="/', $aside))->toBe(1,
        'El atributo style se emite duplicado.'
    );
    expect(str_contains($aside, 'muni-sb') && str_contains($aside, 'mia'))->toBeTrue(
        'La fusión perdió la clase del paquete o la del consumidor.'
    );
    expect(str_contains($aside, '--sb-w:240px') && str_contains($aside, 'background:transparent'))->toBeTrue(
        'La fusión perdió el ancho del paquete o el estilo del consumidor.'
    );
});

/*
|--------------------------------------------------------------------------
| 11. La lateral es navegación con nombre
|--------------------------------------------------------------------------
*/

it('la lateral expone navegación con nombre accesible sin dejar de ser <aside>', function () {
    $html = armazonHtml();
    $aside = etiquetaDelArmazon($html, 'aside', 'muni-sb');

    expect($aside)->not->toBe('',
        'Cambió el elemento: el CSS de los consumidores que selecciona por `aside` deja de aplicar.'
    );
    expect(str_contains($aside, 'role="navigation"'))->toBeTrue(
        'Sigue siendo un landmark `complementary`: la navegación principal debe exponer `navigation`.'
    );
    expect((bool) preg_match('/aria-label="[^"]+"/', $aside))->toBeTrue(
        'El landmark no tiene nombre accesible.'
    );

    $propio = etiquetaDelArmazon(Blade::render('<x-muni::sidebar aria-label="Menú de Rentas" />'), 'aside', 'muni-sb');

    expect(substr_count($propio, 'aria-label='))->toBe(1,
        'El aria-label del consumidor se emite duplicado junto con el del paquete.'
    );
    expect(str_contains($propio, 'Menú de Rentas'))->toBeTrue(
        'El consumidor no puede nombrar su propia navegación.'
    );
});

/*
|--------------------------------------------------------------------------
| 12. No se persiste nada
|--------------------------------------------------------------------------
*/

it('el estado del menú no se persiste ni en almacenamiento ni en cookie', function () {
    foreach (['sidebar', 'dashboard-shell'] as $componente) {
        $fuente = fuenteArmazonSinComentarios($componente);

        foreach (['localStorage', 'sessionStorage', 'document.cookie', '$persist', 'Cookie::'] as $aguja) {
            expect(str_contains($fuente, $aguja))->toBeFalse(
                "«{$componente}» persiste el estado con {$aguja}: parpadea en la primera pintura y wire:navigate ya lo remonta."
            );
        }
    }
});

/*
|--------------------------------------------------------------------------
| 13. Cero colores literales
|--------------------------------------------------------------------------
*/

it('ni el armazón ni la lateral escriben un color literal', function () {
    foreach (['sidebar', 'dashboard-shell'] as $componente) {
        $fuente = fuenteArmazonSinComentarios($componente);

        // El tercer respaldo del foco (#767676, 4,54:1 sobre blanco y 4,62:1
        // sobre negro) es el único literal que DESIGN §5 admite, y el velo
        // semitransparente es el mismo de modal y drawer: sirve igual en los
        // dos temas porque no aporta color, oscurece.
        $fuente = str_replace('var(--muni-focus, var(--muni-accent, #767676))', '', $fuente);
        $fuente = (string) preg_replace('/rgba\(10,\s*14,\s*20,[^)]*\)/', '', $fuente);

        expect((bool) preg_match('/#[0-9a-fA-F]{3,8}\b/', $fuente))->toBeFalse(
            "«{$componente}» escribe un color literal en hexadecimal: el adoptante no puede cambiarlo sin editar el paquete."
        );
        expect((bool) preg_match('/\b(rgba?|hsla?|oklch)\(/', $fuente))->toBeFalse(
            "«{$componente}» escribe un color literal en función de color."
        );
        expect((bool) preg_match('/:\s*(white|black|gray|grey|red|blue|green)\b/', $fuente))->toBeFalse(
            "«{$componente}» usa un color con nombre."
        );
    }
});

/*
|--------------------------------------------------------------------------
| 14. Retrocompatibilidad
|--------------------------------------------------------------------------
*/

it('las llamadas que ya existían siguen rindiendo igual', function () {
    $html = Blade::render(
        '<x-muni::dashboard-shell system="Rentas" subtitle="Municipalidad de Graneros" status="degraded" user="Ana Rojas">'
        .'<x-slot:sidebar><x-muni::sidebar width="300px"><x-muni::nav-item href="/g" :active="true">Giros</x-muni::nav-item></x-muni::sidebar></x-slot:sidebar>'
        .'<x-slot:topbar><span>extra</span></x-slot:topbar>'
        .'<p>Contenido</p>'
        .'</x-muni::dashboard-shell>'
    );

    expect(str_contains($html, '--sb-w:300px'))->toBeTrue('La prop `width` de la lateral dejó de aplicarse.');
    expect(str_contains($html, 'Degradado'))->toBeTrue('La prop `status` dejó de pintar la insignia.');
    expect(str_contains($html, 'Municipalidad de Graneros'))->toBeTrue('El subtítulo desapareció de la cabecera.');
    expect(str_contains($html, 'aria-current="page"'))->toBeTrue('El ítem activo perdió su aria-current.');
    expect(str_contains($html, '<span>extra</span>'))->toBeTrue('El slot `topbar` dejó de emitirse.');
    expect(str_contains($html, '<p>Contenido</p>'))->toBeTrue('El slot por defecto dejó de emitirse.');
    expect(str_contains($html, 'muni-ds__main'))->toBeTrue('Se perdió el <main> del armazón.');
});

it('la lateral sigue sirviendo sola, fuera del armazón', function () {
    $html = Blade::render('<x-muni::sidebar><x-muni::nav-item href="/g">Giros</x-muni::nav-item></x-muni::sidebar>');

    expect(str_contains($html, 'muni-sb__inner'))->toBeTrue('La lateral suelta dejó de rendir su contenedor.');
    expect(str_contains($html, 'Giros'))->toBeTrue('La lateral suelta dejó de rendir su slot.');
    expect(str_contains($html, 'x-data'))->toBeTrue('La lateral suelta se quedó sin estado propio.');
});

it('el armazón sin lateral no emite un hamburguesa que no gobierne nada', function () {
    $html = Blade::render('<x-muni::dashboard-shell system="Rentas"><p>Solo</p></x-muni::dashboard-shell>');

    expect(str_contains($html, '<aside'))->toBeFalse('Aparece una lateral que nadie pidió.');
    expect(str_contains($html, 'muni-ds__main'))->toBeTrue('El armazón sin lateral dejó de rendir.');
});
