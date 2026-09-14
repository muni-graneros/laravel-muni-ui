<?php

use Illuminate\Support\Facades\Blade;

/*
 * LOS MISMOS REGISTROS COMO LISTA DE FICHAS, NO COMO TABLA.
 *
 * `<x-muni::record-list>` + `<x-muni::record-item>`: la presentación de una
 * colección de registros en el portal del vecino y en la ruta de terreno del
 * inspector, donde una tabla de ocho columnas no cabe y hoy se arrastra en
 * horizontal.
 *
 * QUÉ DECIDIÓ EL JUEZ (docs/GAP-ANALYSIS.md, ficha `lista-registros`), porque es
 * lo que estas pruebas vigilan y no la propuesta original:
 *
 *   1. Nombre en inglés: `record-list` + `record-item`. Los 54 componentes del
 *      paquete lo están, salvo el prefijo institucional `gob-*`.
 *   2. NO es el «modo móvil» de `data-table`: es un componente de presentación
 *      propio y la elección la hace el SERVIDOR por contexto de página. De ahí
 *      que no haya doble DOM, ni `aria-hidden` sobre una tabla escondida, ni
 *      container queries: «una sola marca que cambia de tabla a lista según el
 *      ancho» no existe en CSS.
 *   3. API por RANURAS (`title`, `meta`, `actions`), nunca un array de
 *      configuración: el consumidor manda en su marcado y el componente aporta
 *      la firma —banda de estado a la izquierda, `.muni-num` en el folio, área
 *      táctil de 44 px y zona de acciones.
 *   4. `role="list"` en el `<ul>`, no `role="listitem"` en cada hijo: el defecto
 *      de WebKit/VoiceOver lo dispara `list-style:none` en el CONTENEDOR y ahí
 *      se corrige.
 *
 * Todo va con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
 * de `toContain()` es otra aguja que buscar, no un mensaje, y la aserción se
 * desactivaría en silencio.
 */

/** Las tres solicitudes que el vecino ve en el portal, desde el teléfono. */
function fichasDelVecino(): string
{
    return '<x-muni::record-item tone="warn" folio="AV-2026-4821" href="/mis-solicitudes/4821">'
        .'<x-slot:title>Poda de árbol en Manuel Rodríguez 545</x-slot:title>'
        .'<x-slot:meta>Ingresada el 3 de septiembre · Aseo y Ornato</x-slot:meta>'
        .'</x-muni::record-item>'
        .'<x-muni::record-item tone="ok" folio="AV-2026-4712" href="/mis-solicitudes/4712">'
        .'<x-slot:title>Retiro de escombros en Los Aromos 120</x-slot:title>'
        .'<x-slot:meta>Cerrada el 28 de agosto</x-slot:meta>'
        .'</x-muni::record-item>';
}

/** Renderiza la lista con las fichas del vecino. */
function listaDeRegistros(string $atributos = '', ?string $fichas = null): string
{
    return Blade::render(
        '<x-muni::record-list '.$atributos.'>'.($fichas ?? fichasDelVecino()).'</x-muni::record-list>'
    );
}

/** Renderiza una sola ficha. */
function fichaDeRegistro(string $atributos = '', string $ranuras = ''): string
{
    return Blade::render('<x-muni::record-item '.$atributos.'>'.$ranuras.'</x-muni::record-item>');
}

/** El fuente de un componente del par, tal cual está en disco. */
function fuenteDeRegistros(string $componente): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/'.$componente.'.blade.php');
}

/**
 * El fuente sin comentarios —los de Blade `{{-- --}}` y los de CSS `/* *\/`—,
 * para que una regla citada en la prosa no haga pasar ni fallar una aserción.
 */
function fuenteDeRegistrosSinComentarios(string $componente): string
{
    $php = (string) preg_replace('/\{\{--.*?--\}\}/s', '', fuenteDeRegistros($componente));

    return (string) preg_replace('#/\*.*?\*/#s', '', $php);
}

/** El cuerpo de una regla CSS del bloque de estilos de un componente del par. */
function reglaDeRegistros(string $componente, string $selector): string
{
    $css = fuenteDeRegistrosSinComentarios($componente);

    return preg_match('/(?:^|\}|\{)\s*'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m', $css, $m)
        ? trim($m[1])
        : '';
}

/**
 * El texto de todos los elementos cuya clase contiene `$clase`, concatenado.
 * Se resuelve con el árbol y no con un regex porque dentro de esos elementos
 * hay anidamiento (el rótulo del folio para el lector vive dentro del `.muni-num`).
 */
function textoEnClaseDeRegistro(string $html, string $clase): string
{
    $doc = new DOMDocument;
    $previo = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previo);

    $nodos = (new DOMXPath($doc))->query(
        '//*[contains(concat(" ", normalize-space(@class), " "), " '.$clase.' ")]'
    );

    $textos = [];

    foreach ($nodos ?: [] as $nodo) {
        $textos[] = $nodo->textContent;
    }

    return implode(' | ', $textos);
}

/** Las etiquetas de apertura `<li …>` de la lista, en orden. */
function fichasDe(string $html): array
{
    preg_match_all('/<li\b[^>]*>/i', $html, $m);

    return $m[0];
}

// ---------------------------------------------------------------------------
// La semántica: una lista de verdad, que sigue siéndolo en Safari
// ---------------------------------------------------------------------------

it('es un <ul> con role="list" y los registros son <li>, no divs con rol inventado', function () {
    $html = listaDeRegistros('label="Mis solicitudes"');

    expect((bool) preg_match('/<ul\b[^>]*\brole="list"/i', $html))->toBeTrue(
        'La lista no declara role="list". Safari con VoiceOver le quita la semántica de lista a '.
        'toda lista con list-style:none —y esta lo lleva—, así que las fichas volverían a ser '.
        'texto suelto y el vecino perdería el «lista de 3 elementos» que le dice cuántas '.
        'solicitudes tiene. HTML: '.substr($html, 0, 200)
    );

    expect(count(fichasDe($html)))->toBe(2, 'Cada registro tiene que ser un <li> de la lista.');

    expect((bool) preg_match('/\brole="listitem"/i', $html))->toBeFalse(
        'Pone role="listitem" en cada hijo. La corrección del juez es explícita: el defecto lo '.
        'dispara list-style:none en el CONTENEDOR y ahí se corrige; repetir el rol en cada ítem '.
        'es ruido que además se olvida en cuanto alguien escribe un <li> a mano.'
    );

    expect((bool) preg_match('/<table\b|<tr\b|<td\b/i', $html))->toBeFalse(
        'Emite marcado de tabla: esto es una lista, y la elección lista-o-tabla la hace el '.
        'servidor por contexto de página, no el CSS por ancho de pantalla.'
    );
});

it('la lista lleva nombre accesible solo si el anfitrión lo da, y nunca dos a la vez', function () {
    $conNombre = listaDeRegistros('label="Mis solicitudes"');

    expect((bool) preg_match('/<ul\b[^>]*\baria-label="Mis solicitudes"/i', $conNombre))->toBeTrue(
        'No respetó el `label`: una lista sin nombre en una página con varias es indistinguible '.
        'para quien navega por regiones.'
    );

    $sinNombre = listaDeRegistros();

    expect((bool) preg_match('/<ul\b[^>]*\baria-label=/i', $sinNombre))->toBeFalse(
        'Inventa un nombre para la lista cuando el anfitrión no dio ninguno.'
    );

    $apuntado = listaDeRegistros('label="Mis solicitudes" aria-labelledby="titulo-bandeja"');

    expect((bool) preg_match('/<ul\b[^>]*\baria-label=/i', $apuntado))->toBeFalse(
        'Con aria-labelledby del consumidor sigue emitiendo su aria-label: dos nombres compitiendo '.
        'en el mismo elemento.'
    );
});

it('el aria-labelledby NULO del anfitrión no borra el nombre por defecto', function () {
    /*
     * DESIGN §8: `:atributo="null"` no desaparece, GANA. El caso natural es
     * `:aria-labelledby="$tituloBandejaId"` con el id todavía en null; si el
     * componente mira la PRESENCIA y no el VALOR, se calla su aria-label, Blade
     * no imprime el atributo nulo y la lista queda sin ningún nombre.
     */
    $html = Blade::render(
        '<x-muni::record-list label="Mis solicitudes" :aria-labelledby="$id">'.fichasDelVecino().'</x-muni::record-list>',
        ['id' => null]
    );

    expect((bool) preg_match('/<ul\b[^>]*\b(aria-label|aria-labelledby)="[^"]+"/i', $html))->toBeTrue(
        'Con :aria-labelledby="null" la lista sale sin nombre accesible: '.substr($html, 0, 300)
    );

    expect((bool) preg_match('/aria-labelledby="\s*"/i', $html))->toBeFalse(
        'Deja puesto un aria-labelledby vacío al lado del aria-label: por la norma el vacío no '.
        'nombra nada, pero no todos los lectores lo resuelven igual.'
    );
});

it('sin fichas no emite la lista vacía', function () {
    $vacia = Blade::render('<x-muni::record-list label="Mis solicitudes">   </x-muni::record-list>');

    expect((bool) preg_match('/<ul\b/i', $vacia))->toBeFalse(
        'Con la ranura vacía emite un <ul> igual: el lector anuncia «lista, 0 elementos» y el '.
        'vecino no sabe si no tiene solicitudes o si la página se rompió. El «no hay nada» lo '.
        'dice <x-muni::empty-state>, que para eso existe.'
    );
});

// ---------------------------------------------------------------------------
// La firma: banda de estado, folio en mono, área táctil de terreno
// ---------------------------------------------------------------------------

it('el estado pinta la banda izquierda Y se dice con palabras', function () {
    $html = fichaDeRegistro('tone="danger"', '<x-slot:title>Solicitud 4821</x-slot:title>');

    expect((bool) preg_match('/--mrec-banda:\s*var\(--muni-danger-fg\)/i', $html))->toBeTrue(
        'La ficha con tono danger no pinta la banda de estado con su token. La franja al borde '.
        'de la fila es la firma del sistema (DESIGN §9). HTML: '.substr($html, 0, 300)
    );

    expect(str_contains($html, 'Rechazado'))->toBeTrue(
        'El estado se comunica SOLO con el color de la banda (WCAG 2.2 AA 1.4.1). Quien no '.
        'distingue el rojo VE la pantalla: la etiqueta va en texto visible, como en timeline.'
    );

    $banda = reglaDeRegistros('record-item', '.muni-rec__item');

    expect((bool) preg_match('/border-left\s*:\s*3px/i', $banda))->toBeTrue(
        'La banda no se dibuja con un borde de 3px. Va como BORDE y no como box-shadow inset '.
        'para que sobreviva al papel: los fondos y las sombras no se imprimen, los bordes sí, y '.
        'es la misma corrección que data-table tuvo que escribir en su @media print. Regla: '.$banda
    );
});

it('el tono reescribible no inventa estados y una errata no cae en silencio', function () {
    $propio = fichaDeRegistro('tone="warn" tone-label="En terreno"', '<x-slot:title>Orden 118</x-slot:title>');

    expect(str_contains($propio, 'En terreno'))->toBeTrue('No respetó el `toneLabel` del anfitrión.');
    expect(str_contains($propio, 'Con observación'))->toBeFalse(
        'Pinta la etiqueta por defecto además de la del anfitrión.'
    );

    $sinTono = fichaDeRegistro('', '<x-slot:title>Orden 118</x-slot:title>');

    foreach (['Conforme', 'Con observación', 'Rechazado', 'Informativo', 'En curso', 'Sin efecto'] as $palabra) {
        expect(str_contains($sinTono, $palabra))->toBeFalse(
            "Sin tono declarado la ficha igual dice «{$palabra}»: eso es inventar un estado que el ".
            'anfitrión no declaró, y en un expediente municipal inventar «Conforme» es grave.'
        );
    }

    expect((bool) preg_match('/--mrec-banda:\s*var\(--muni-[a-z-]+\)/i', $sinTono))->toBeFalse(
        'Sin tono igual pinta una banda de color: el registro parece tener un estado que nadie declaró.'
    );

    /*
     * Una errata NO puede caer a «en curso» en silencio: la ficha se vería
     * normal, el vecino leería «En curso» sobre una solicitud rechazada y nadie
     * se enteraría de que la prop está mal escrita. Es el mismo criterio —y el
     * mismo precedente— que `densidad` en data-table, y acá pesa más: ahí se
     * equivocaba el alto de una fila, aquí el estado de un expediente.
     *
     * La excepción llega envuelta por el motor de vistas, así que se busca la
     * causa raíz, igual que en DensidadDeTablaTest.
     */
    $causa = null;

    try {
        fichaDeRegistro('tone="rechazado"', '<x-slot:title>Solicitud 4821</x-slot:title>');
    } catch (Throwable $e) {
        while ($e->getPrevious() !== null) {
            $e = $e->getPrevious();
        }

        $causa = $e;
    }

    expect($causa)->toBeInstanceOf(
        InvalidArgumentException::class,
        'tone="rechazado" (que es la ETIQUETA, no el tono) no revienta: la ficha sale sin estado o '.
        'con otro, y nadie se entera de que la prop está mal escrita.'
    );

    expect(str_contains($causa?->getMessage() ?? '', 'danger'))->toBeTrue(
        'El mensaje no dice cuáles son los tonos válidos. Mensaje: '.($causa?->getMessage() ?? 'no reventó')
    );
});

it('el folio va en mono tabular y se anuncia como folio, no como una sigla suelta', function () {
    $html = fichaDeRegistro('folio="AV-2026-4821"', '<x-slot:title>Poda de árbol</x-slot:title>');

    /* Se mira el ÁRBOL y no una cadena: dentro del `.muni-num` va además el
       rótulo para el lector, y un regex ingenuo se tropieza con ese anidado. */
    expect(str_contains(textoEnClaseDeRegistro($html, 'muni-num'), 'AV-2026-4821'))->toBeTrue(
        'El folio no sale en `.muni-num`. Las cifras y los folios en mono tabular son la segunda '.
        'firma del sistema (DESIGN §9) y es lo que permite comparar dos folios de un vistazo. '.
        'HTML: '.substr($html, 0, 400)
    );

    expect(str_contains($html, 'Folio'))->toBeTrue(
        'El folio se lee como un código suelto: el lector anuncia «AV guion 2026…» sin decir qué es.'
    );
});

it('define `.muni-num` en su propio @once, porque en el portal no hay ninguna tabla en la página', function () {
    /*
     * `.muni-num` vive HOY únicamente dentro del @once de `data-table`. Este
     * componente existe justamente para las pantallas donde NO hay tabla —el
     * teléfono del vecino, la tablet del inspector—, así que ahí la clase no
     * estaría declarada por nadie y el folio saldría en sans proporcional: el
     * defecto exacto que el componente promete evitar. Es DESIGN §7: lo que un
     * componente necesita para verse bien viaja con el componente.
     */
    $num = reglaDeRegistros('record-item', '.muni-num');

    expect($num !== '')->toBeTrue(
        'El componente no declara `.muni-num` en su bloque de estilos. En una página sin '.
        '<x-muni::data-table> —que es el caso de uso entero de este componente— la clase no existe '.
        'y el folio se pinta en la tipografía del cuerpo, sin aviso ninguno.'
    );

    expect(str_contains($num, 'var(--muni-font-mono)') && str_contains($num, 'tabular-nums'))->toBeTrue(
        'La declaración de `.muni-num` no coincide con la de data-table (mono + tabular-nums): '.
        'dos definiciones distintas de la misma clase es peor que ninguna. Regla: '.$num
    );
});

it('el área táctil es la de terreno: 44 px, no 24', function () {
    $enlace = reglaDeRegistros('record-item', 'a.muni-rec__titulo');

    expect((bool) preg_match('/min-height\s*:\s*(\d+)px/', $enlace, $m) && (int) $m[1] >= 44)->toBeTrue(
        'El enlace de la ficha no llega a 44px de alto. La ficha del componente lo dice explícito: '.
        '«área táctil 44x44 px, no 24: es terreno». El inspector la usa de pie, con guantes y con '.
        'el sol encima. Regla: '.$enlace
    );

    $acciones = reglaDeRegistros('record-item', '.muni-rec__acciones a, .muni-rec__acciones button');

    expect((bool) preg_match('/min-height\s*:\s*(\d+)px/', $acciones, $m) && (int) $m[1] >= 44)->toBeTrue(
        'Los controles de la zona de acciones no llegan a 44px de alto: '.$acciones
    );
    expect((bool) preg_match('/min-width\s*:\s*(\d+)px/', $acciones, $m) && (int) $m[1] >= 44)->toBeTrue(
        'Los controles de la zona de acciones no llegan a 44px de ancho —un botón de solo icono '.
        'queda en 24— : '.$acciones
    );
});

// ---------------------------------------------------------------------------
// Teclado y enlaces
// ---------------------------------------------------------------------------

it('el título es un enlace real cuando hay href, y nunca un enlace sin nombre', function () {
    $conHref = fichaDeRegistro(
        'href="/mis-solicitudes/4821"',
        '<x-slot:title>Poda de árbol en Manuel Rodríguez 545</x-slot:title>'
    );

    expect((bool) preg_match('/<a\b[^>]*\bhref="\/mis-solicitudes\/4821"/i', $conHref))->toBeTrue(
        'El título no es un <a href> real: sin él no hay Enter nativo, ni «abrir en otra pestaña», '.
        'ni enlace que copiar. HTML: '.substr($conHref, 0, 300)
    );

    $sinTitulo = fichaDeRegistro('href="/mis-solicitudes/4821" folio="AV-2026-4821"');

    expect((bool) preg_match('/<a\b/i', $sinTitulo))->toBeFalse(
        'Con href pero sin título emite un enlace igual: un enlace sin nombre accesible deja al '.
        'lector leyendo la URL (WCAG 2.2 A 2.4.4). Sin título no hay enlace.'
    );

    $sinHref = fichaDeRegistro('', '<x-slot:title>Poda de árbol</x-slot:title>');

    expect((bool) preg_match('/<a\b/i', $sinHref))->toBeFalse(
        'Sin href emite un ancla sin destino: un <a> sin href no es una parada de teclado y '.
        'promete una navegación que no existe.'
    );
    expect(str_contains($sinHref, 'Poda de árbol'))->toBeTrue('Sin href se perdió el título.');
});

it('no emite botones ni una línea de Alpine: la interacción es la del enlace nativo', function () {
    $html = listaDeRegistros();

    expect((bool) preg_match('/<button\b/i', $html))->toBeFalse(
        'El componente emite un <button> propio. La ficha lo dice: «Alpine — no». Lo que abre la '.
        'ficha es un enlace; si el anfitrión necesita un botón (con su Space nativo), lo pone él '.
        'en la ranura `actions`, que para eso está.'
    );

    foreach (['record-list', 'record-item'] as $componente) {
        $fuente = fuenteDeRegistrosSinComentarios($componente);

        expect((bool) preg_match('/x-data|x-show|x-init|@click|x-on:|@keydown/i', $fuente))->toBeFalse(
            "«{$componente}» trae Alpine. Tiene que funcionar con el JS apagado: el portal del ".
            'vecino se abre desde teléfonos viejos y con la red de la plaza.'
        );

        foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $exclusiva) {
            expect(str_contains($fuente, $exclusiva))->toBeFalse(
                "«{$componente}» usa «{$exclusiva}», que es exclusiva de un major de Livewire: ".
                'personas-graneros sigue en Livewire 3.'
            );
        }
    }
});

it('wire:navigate solo cuando el anfitrión lo pide', function () {
    $sin = fichaDeRegistro('href="/x"', '<x-slot:title>Poda</x-slot:title>');

    expect(str_contains($sin, 'wire:navigate'))->toBeFalse(
        'Estampa wire:navigate sin que se lo pidan: el paquete se instala también donde no hay Livewire.'
    );

    $con = fichaDeRegistro('href="/x" navegar', '<x-slot:title>Poda</x-slot:title>');

    expect(str_contains($con, 'wire:navigate'))->toBeTrue('Con `navegar` falta el wire:navigate.');
});

// ---------------------------------------------------------------------------
// Las tres ranuras
// ---------------------------------------------------------------------------

it('emite las tres ranuras y el cuerpo libre, y el consumidor manda en su marcado', function () {
    $html = fichaDeRegistro(
        'tone="info" folio="OT-2026-118" href="/ordenes/118"',
        '<x-slot:title>Revisión de luminaria</x-slot:title>'
        .'<x-slot:meta>Calle Uno 100 · <span class="muni-num">12.345.678-9</span></x-slot:meta>'
        .'<x-slot:actions><a href="/ordenes/118/cerrar">Cerrar orden</a></x-slot:actions>'
        .'<p>Observación del inspector.</p>'
    );

    foreach ([
        'Revisión de luminaria' => 'el título',
        'Calle Uno 100' => 'la ranura meta',
        'Cerrar orden' => 'la ranura actions',
        'Observación del inspector' => 'el cuerpo libre de la ranura por defecto',
    ] as $texto => $que) {
        expect(str_contains($html, $texto))->toBeTrue(
            "Se perdió {$que}. La API es por ranuras a propósito (corrección del juez): un array de "
            .'configuración («campos», «acciones», «tono», «media») termina siendo un mini motor de '
            .'plantillas que ninguno de los seis sistemas usa como viene.'
        );
    }

    expect(str_contains($html, '<span class="muni-num">12.345.678-9</span>'))->toBeTrue(
        'El RUT que el anfitrión escribió en `.muni-num` no llegó intacto: la ranura es marcado '.
        'del desarrollador y el componente no lo reescribe.'
    );

    expect((bool) preg_match('/class="[^"]*muni-rec__acciones/i', $html))->toBeTrue(
        'Las acciones no van en su propia zona: sin ella quedan pegadas al texto y se pierde la '.
        'separación entre «leer» y «actuar».'
    );
});

// ---------------------------------------------------------------------------
// Lo que la corrección del juez PROHÍBE
// ---------------------------------------------------------------------------

it('no hay doble DOM, ni container queries, ni una tabla escondida', function () {
    foreach (['record-list', 'record-item'] as $componente) {
        $fuente = fuenteDeRegistrosSinComentarios($componente);

        expect((bool) preg_match('/@container|container-type\s*:/i', $fuente))->toBeFalse(
            "«{$componente}» usa container queries. La corrección del juez las borra de raíz: ".
            'la elección lista-o-tabla la hace el SERVIDOR por contexto de página —portal y '.
            'terreno renderizan lista, back-office renderiza tabla—, así que no hay nada que '.
            'conmutar por ancho.'
        );

        expect((bool) preg_match('/aria-hidden/i', $fuente))->toBeFalse(
            "«{$componente}» esconde algo con aria-hidden: eso solo hace falta cuando se renderizan ".
            'la tabla y la lista a la vez, que es justo lo que este diseño elimina.'
        );

        expect((bool) preg_match('/content\s*:\s*attr\(\s*data-label/i', $fuente))->toBeFalse(
            "«{$componente}» copia el truco de `::before { content: attr(data-label) }`. El catálogo ".
            'advierte por qué no: destruye la relación fila/columna para el lector y la etiqueta '.
            'generada no es texto seleccionable, así que el vecino no puede copiar su folio.'
        );
    }
});

// ---------------------------------------------------------------------------
// El contrato del paquete
// ---------------------------------------------------------------------------

it('no consulta request(), Auth, Gate ni permisos, y no acepta un modelo', function () {
    foreach (['record-list', 'record-item'] as $componente) {
        $fuente = fuenteDeRegistrosSinComentarios($componente);

        foreach (['request(', 'Auth::', 'auth()', 'Gate::', '->can(', 'route('] as $prohibido) {
            expect(str_contains($fuente, $prohibido))->toBeFalse(
                'El componente «'.$componente.'» usa «'.$prohibido.'». El paquete no sabe qué es una '.
                'petición ni un permiso: recibe cadenas YA redactadas por el anfitrión '.
                '(minimización, Ley 21.719). En discapacidad el registro trae diagnósticos y en '.
                'licencias, psicotécnicos: un componente que invite a volcar el objeto entero '.
                'rompe la minimización solo.'
            );
        }
    }
});

it('un objeto sin __toString en una prop se descarta en vez de tumbar la página', function () {
    $html = Blade::render(
        '<x-muni::record-item :folio="$folio" :href="$href" tone="ok">'
        .'<x-slot:title>Solicitud del vecino</x-slot:title></x-muni::record-item>',
        ['folio' => new stdClass, 'href' => new stdClass]
    );

    expect(str_contains($html, 'Solicitud del vecino'))->toBeTrue(
        'Un modelo colado en una prop tumbó la ficha entera. Es peor que un dato de menos: la '.
        'página del vecino se cae y el volcado de la excepción publica justo lo que nadie quería '.
        'publicar.'
    );
    expect((bool) preg_match('/<a\b/i', $html))->toBeFalse('Emitió un enlace con un href que no es texto.');
});

it('escapa el apóstrofo y el marcado del anfitrión', function () {
    $html = fichaDeRegistro(
        'folio="AV-2026-4821" tone-label="Solicitud d\'oficio"',
        '<x-slot:title>{{ \'<script>alert(1)</script>\' }}</x-slot:title>'
    );

    expect(str_contains($html, '&#039;'))->toBeTrue(
        'El apóstrofo no salió escapado: si el texto entrara alguna vez en una expresión de Alpine '.
        'el navegador lo decodificaría dentro del valor del atributo y tumbaría el Alpine de la '.
        'página entera (es como lo cerró file-dropzone).'
    );
    expect(str_contains($html, '<script>alert(1)</script>'))->toBeFalse(
        'El marcado del anfitrión llegó sin escapar: el título de una ficha es TEXTO.'
    );
});

it('los ids y el HTML son estables entre renders: nada de uniqid()', function () {
    expect(listaDeRegistros('label="Mis solicitudes"'))->toBe(
        listaDeRegistros('label="Mis solicitudes"'),
        'Dos renders idénticos dan HTML distinto: con ids que cambian, Livewire reemplaza nodos '.
        'que no cambiaron y se pierde el foco al refrescar (DESIGN §10).'
    );

    foreach (['record-list', 'record-item'] as $componente) {
        expect(str_contains(fuenteDeRegistros($componente), 'uniqid('))->toBeFalse(
            "«{$componente}» usa uniqid()."
        );
    }
});

// ---------------------------------------------------------------------------
// Contrato visual
// ---------------------------------------------------------------------------

it('el foco se dibuja con outline y la cadena de respaldo completa', function () {
    $foco = reglaDeRegistros('record-item', '.muni-rec__titulo:focus-visible');

    expect($foco !== '')->toBeTrue('El enlace de la ficha no declara ninguna regla de foco.');

    expect(str_contains($foco, 'outline:3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeTrue(
        'El foco no usa el outline de 3px con la cadena de respaldo del paquete. Dentro de Filament '.
        'la box-shadow del anillo se computa transparente y el teclado se queda sin indicador '.
        '(WCAG 2.2 AA 2.4.7). Regla: '.$foco
    );
});

it('el movimiento se apaga solo, también dentro de un panel Filament', function () {
    foreach (['record-list', 'record-item'] as $componente) {
        $css = fuenteDeRegistrosSinComentarios($componente);

        expect((bool) preg_match('/transition\s*:[^;]*\b\d*\.?\d+m?s\b/i', $css))->toBeFalse(
            "«{$componente}» tiene una transición con duración fija: ignora prefers-reduced-motion. ".
            'El movimiento va con var(--muni-dur), que baja a 0 ms con la preferencia (DESIGN §6).'
        );
    }

    expect((bool) preg_match(
        '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{\s*\.muni-rec__item\s*\{[^}]*transition\s*:\s*none\s*!important/i',
        fuenteDeRegistrosSinComentarios('record-item')
    ))->toBeTrue(
        'Falta la regla propia de movimiento reducido. Dentro de un panel Filament `muni-ui.css` no '.
        'se carga (DESIGN §7) y la hoja del panel NO baja --muni-dur: medido en tabs-ruta, la '.
        'transición seguía en 160 ms con la preferencia activa.'
    );
});

it('no escribe un solo color literal fuera del respaldo del foco', function () {
    foreach (['record-list', 'record-item'] as $componente) {
        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', fuenteDeRegistrosSinComentarios($componente), $m);

        expect(array_values(array_unique(array_diff($m[0], ['#767676']))))->toBe(
            [],
            "«{$componente}» escribe colores literales. Todo color sale de un token --muni-*, que es ".
            'lo que permite a otro municipio cambiar su identidad sin editar el paquete (DESIGN §1).'
        );
    }
});

it('la variable local de la banda no usurpa el espacio de nombres --muni-', function () {
    /*
     * Hay una guarda que exige que todo `--muni-*` que un componente LEA esté
     * declarado en las DOS hojas publicables. Una variable local del componente
     * no es un token del sistema: va con prefijo propio.
     */
    $fuente = fuenteDeRegistrosSinComentarios('record-item');

    expect((bool) preg_match('/--muni-[a-z0-9-]+\s*:/i', $fuente))->toBeFalse(
        'El componente DECLARA un --muni-*: los tokens del sistema se definen en las dos hojas del '.
        'paquete, no dentro de un componente. La variable de la banda es local y se llama --mrec-banda.'
    );
});

it('en el papel la ficha no se parte y los botones no se imprimen', function () {
    $impresion = fuenteDeRegistrosSinComentarios('record-item');

    expect((bool) preg_match('/@media\s+print\s*\{[^}]*\.muni-rec__item\s*\{[^}]*break-inside\s*:\s*avoid/is', $impresion))->toBeTrue(
        'La ficha se parte entre dos páginas al imprimir la nómina: el folio en una hoja y el '.
        'estado en la otra.'
    );

    expect((bool) preg_match('/prefers-color-scheme/i', $impresion))->toBeFalse(
        'El componente mira la preferencia de color del sistema operativo. Si hiciera falta, iría '.
        'atada a `screen` (DESIGN §3): el navegador informa la preferencia también al imprimir.'
    );
});

// ---------------------------------------------------------------------------
// Los candados compartidos del repo
// ---------------------------------------------------------------------------

it('los dos se renderizan SIN UNA SOLA PROP, que es como los llama el candado de humo', function () {
    foreach (['record-list', 'record-item'] as $componente) {
        $html = Blade::render("<x-muni::{$componente}>Contenido de prueba</x-muni::{$componente}>");

        expect(str_contains($html, 'Contenido de prueba'))->toBeTrue(
            "«{$componente}» no renderiza sin props y deja en rojo el candado global del repo ".
            '(TodosRendericanTest). Ninguna prop es obligatoria a propósito: el anfitrión que se '.
            'olvida de una ve una ficha más pobre, no una página caída.'
        );
    }
});

it('la entrada de la vitrina renderiza la bandeja del vecino con datos reales', function () {
    /*
     * La misma cadena que va en `ejemplosDeVitrina()` de GeneraVitrinaTest, que
     * es lo que la reja de accesibilidad abre en los dos temas y las dos paletas.
     * Se comprueba acá porque ese arreglo vive en un archivo compartido: si la
     * entrada se copia mal, la reja mediría una tarjeta vacía y no lo diría.
     */
    $html = Blade::render(entradaDeVitrinaDeRegistros());

    expect(count(fichasDe($html)))->toBe(3, 'La entrada de la vitrina no pinta las tres solicitudes.');
    expect(substr_count($html, 'muni-rec__tono'))->toBeGreaterThanOrEqual(
        3,
        'La entrada de la vitrina no trae los tres tonos: cada uno pinta su propia banda y sin '.
        'ellos la reja mide un tono de tres.'
    );
    expect(str_contains($html, 'AV-2026-4821'))->toBeTrue('La entrada de la vitrina perdió el folio.');
});

/** El ejemplo de la vitrina, en un solo sitio para no copiarlo mal. */
function entradaDeVitrinaDeRegistros(): string
{
    return '<x-muni::record-list label="Mis solicitudes">'
        .'<x-muni::record-item tone="warn" folio="AV-2026-4821" href="#solicitud-4821">'
        .'<x-slot:title>Poda de árbol en Manuel Rodríguez 545</x-slot:title>'
        .'<x-slot:meta>Ingresada el 3 de septiembre · Aseo y Ornato</x-slot:meta>'
        .'<x-slot:actions><x-muni::button variant="ghost" href="#solicitud-4821">Ver detalle</x-muni::button></x-slot:actions>'
        .'</x-muni::record-item>'
        .'<x-muni::record-item tone="ok" folio="AV-2026-4712" href="#solicitud-4712">'
        .'<x-slot:title>Retiro de escombros en Los Aromos 120</x-slot:title>'
        .'<x-slot:meta>Cerrada el 28 de agosto · Aseo y Ornato</x-slot:meta>'
        .'</x-muni::record-item>'
        .'<x-muni::record-item tone="danger" folio="AV-2026-4655" href="#solicitud-4655">'
        .'<x-slot:title>Permiso de ocupación de vereda</x-slot:title>'
        .'<x-slot:meta>Rechazada el 12 de agosto · Rentas y Patentes</x-slot:meta>'
        .'</x-muni::record-item>'
        .'</x-muni::record-list>';
}

// ---------------------------------------------------------------------------
// Verificación en navegador
// ---------------------------------------------------------------------------

/*
 * El banco de navegador. Se genera acá y no en un script suelto porque este es
 * el único sitio del paquete con Blade arrancado (mismo criterio que
 * `GeneraVitrinaTest`, `SolapasDeRutaTest` y `GuardiaDeSesionTest`). Sale a
 * `build/lista-de-registros/`, ignorado por git.
 *
 * Son CUATRO páginas por lo que dice DESIGN §7: dentro de un panel Filament
 * `muni-ui.css` no se carga y la paleta del panel vale distinto, así que
 * `panel-*` carga ÚNICAMENTE `muni-ui-filament.css`.
 *
 * Los href son anclas para que pulsar Enter active el enlace sin sacar a
 * Playwright de la página: lo que se comprueba es que el enlace se sigue con el
 * teclado, no a dónde lleva.
 */
it('genera el banco de navegador en build/lista-de-registros/', function () {
    $dir = __DIR__.'/../build/lista-de-registros';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $lista = Blade::render(
        '<h1 id="titulo-bandeja">Mis solicitudes</h1>'
        .'<x-muni::record-list aria-labelledby="titulo-bandeja">'
        .'<x-muni::record-item tone="warn" folio="AV-2026-4821" href="#solicitud-4821">'
        .'<x-slot:title>Poda de árbol en Manuel Rodríguez 545</x-slot:title>'
        .'<x-slot:meta>Ingresada el 3 de septiembre · Aseo y Ornato · '
        .'RUT <span class="muni-num">12.345.678-9</span></x-slot:meta>'
        .'<x-slot:actions><x-muni::button variant="ghost" href="#detalle-4821">Ver detalle</x-muni::button></x-slot:actions>'
        .'</x-muni::record-item>'
        .'<x-muni::record-item tone="ok" folio="AV-2026-4712" href="#solicitud-4712">'
        .'<x-slot:title>Retiro de escombros en Los Aromos 120</x-slot:title>'
        .'<x-slot:meta>Cerrada el 28 de agosto · Aseo y Ornato</x-slot:meta>'
        .'</x-muni::record-item>'
        .'<x-muni::record-item tone="danger" folio="AV-2026-4655" href="#solicitud-4655">'
        .'<x-slot:title>Permiso de ocupación de vereda</x-slot:title>'
        .'<x-slot:meta>Rechazada el 12 de agosto · Rentas y Patentes</x-slot:meta>'
        .'</x-muni::record-item>'
        /* Una ficha SIN tono: comprueba en el navegador que no se pinta ninguna
           banda de color donde el anfitrión no declaró estado, y que igual queda
           alineada con las demás. */
        .'<x-muni::record-item folio="AV-2026-4501" href="#solicitud-4501">'
        .'<x-slot:title>Consulta por corte de agua</x-slot:title>'
        .'<x-slot:meta>Respondida el 2 de agosto</x-slot:meta>'
        .'</x-muni::record-item>'
        .'</x-muni::record-list>'
    );

    $paginas = [
        'claro' => ['light', cssMuniUi(), false],
        'oscuro' => ['dark', cssMuniUi(), false],
        'panel-claro' => ['light', cssMuniUiFilament(), true],
        'panel-oscuro' => ['dark', cssMuniUiFilament(), true],
    ];

    foreach ($paginas as $nombre => [$tema, $css, $panel]) {
        $oscuro = $tema === 'dark';
        $hoja = $panel ? 'muni-ui-filament.css' : 'muni-ui.css';

        /* El armazón no aporta ni un color: fondo y texto salen de los mismos
           tokens que lee el componente. */
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'h1{font-size:20px;margin:0 0 16px}';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<section class="fi-section">'.$lista.'</section></main></div></body>'
            : '<body><main id="muni-contenido" tabindex="-1">'.$lista.'</main></body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco record-list — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});

/** El Python del entorno de la reja (`npm run a11y:instalar`), o null si no está. */
function pythonDeLaRejaRegistros(): ?string
{
    $ruta = __DIR__.'/../.venv-a11y/bin/python';

    return is_executable($ruta) ? $ruta : null;
}

/*
 * Lo que un test de Blade no puede ver, medido en Chromium Y Firefox sobre el
 * banco. Las pruebas de arriba leen el TEXTO del CSS del componente, y eso ya
 * engañó una vez en `stat`: la regla de movimiento reducido pasó el test de
 * texto y en el navegador la transición seguía viva. Acá se mide el recorrido
 * real de Tab con `document.activeElement`, el outline computado, el alto real
 * del objetivo táctil, la banda de estado pintada, el contraste contra el fondo
 * efectivo y el ancho de teléfono.
 * Se SALTA —no se finge— cuando no está `.venv-a11y`, que en CI no se instala.
 */
it('en Chromium y Firefox: Tab recorre las fichas, el foco se ve, la banda se pinta, el objetivo mide 44px y a 320px no hay desplazamiento horizontal', function () {
    $python = pythonDeLaRejaRegistros();

    if ($python === null) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay navegador: la verificación se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg((string) $python).' '.escapeshellarg(__DIR__.'/navegador/lista-de-registros.py').' '
        .escapeshellarg(__DIR__.'/../build/lista-de-registros').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de <x-muni::record-list> falló:\n".implode("\n", $lineas));
});
