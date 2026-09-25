<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DEL BUSCADOR EN LISTA LARGA (<x-muni::combobox>)
|--------------------------------------------------------------------------
|
| Fase A del componente: buscar y elegir UN registro dentro de una lista larga
| escribiendo, para formularios Livewire FUERA de Filament. La fase B —la clase
| `Field` de Filament que envolvería el mismo marcado— es otra ficha y no está
| construida, así que este componente NO retira
| `resources/js/nombre-accesible-select.js`: el titular ARCOP lo pinta Filament
| con `Select::searchable()`, no Blade.
|
| Lo que estas pruebas vigilan, en el orden en que duele si se rompe:
|
| 1. SUPERVIVENCIA A LIVEWIRE 3. El valor elegido vive en un <input type="hidden">
|    con `wire:model`, y la etiqueta visible la pinta el SERVIDOR desde
|    `selectedLabel`. En Livewire 3 cada respuesta vuelve a crear el `x-data`
|    (INVENTORY lo documenta con `rating`, que pierde el valor elegido): todo lo
|    que se guarde en Alpine se evapora.
| 2. ID DETERMINISTA. Prohibido `uniqid()` (DESIGN §10): cambia en cada render,
|    rompe la relación label/for y ensucia el diffing. Sale del `name`, que es
|    obligatorio.
| 3. FOCO QUE NO SE MUEVE. `aria-activedescendant` obliga a que el foco se quede
|    en el campo. Es el punto más fácil de romper y no se ve en una captura.
| 4. RENDIMIENTO. Tope duro de 20 resultados, debounce de 300 ms, y el «N
|    resultados» anunciado AL ASENTARSE —lo escribe el servidor, no una tecla—.
| 5. INYECCIÓN EN ALPINE. Un apóstrofo en una etiqueta interpolada dentro de una
|    cadena de comillas simples tumba el Alpine de la página ENTERA. Ya pasó con
|    `file-dropzone`.
|
| Se prueba con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
| de `toContain()` es otra aguja, no un mensaje, y la aserción se desactiva sin
| avisar.
*/

/** El `combobox.blade.php` crudo. */
function fuenteBuscadorLista(): string
{
    return file_get_contents(__DIR__.'/../resources/views/components/combobox.blade.php');
}

/** El fuente sin comentarios de Blade ni de CSS, para no matchear la prosa. */
function fuenteBuscadorListaSinComentarios(): string
{
    $fuente = preg_replace('#\{\{--.*?--\}\}#s', '', fuenteBuscadorLista());

    return (string) preg_replace('#/\*.*?\*/#s', '', (string) $fuente);
}

/** El contenido de los bloques `<style>` del componente, sin comentarios. */
function cssBuscadorLista(): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteBuscadorLista(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/**
 * Los valores de todos los atributos de Alpine del HTML dado, ya decodificados
 * como los ve el navegador.
 *
 * @return array<int, string>
 */
function alpineBuscadorLista(string $html): array
{
    preg_match_all('/\s(?:x-[a-z0-9.:-]+|@[a-z0-9.-]+|:[a-z0-9-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

/** Un puñado de calles, que es el caso real del mesón de Atención al Vecino. */
function opcionesBuscadorLista(int $cuantas = 3): array
{
    $opciones = [];

    for ($i = 1; $i <= $cuantas; $i++) {
        $opciones[] = ['value' => "c{$i}", 'label' => "Calle {$i}", 'hint' => "Sector {$i}"];
    }

    return $opciones;
}

/** Render del componente con las props que interesan a cada prueba. */
function buscadorLista(array $datos = [], string $atributos = ''): string
{
    $datos = array_merge([
        'name' => 'titular_id',
        'label' => 'Titular de los datos',
        'options' => opcionesBuscadorLista(),
    ], $datos);

    $props = ':name="$name" :label="$label" :options="$options"';

    foreach (array_keys($datos) as $clave) {
        if (in_array($clave, ['name', 'label', 'options'], true)) {
            continue;
        }

        $props .= ' :'.preg_replace('/([a-z])([A-Z])/', '$1-$2', $clave).'="$'.$clave.'"';
    }

    return Blade::render("<x-muni::combobox {$props} {$atributos} />", $datos);
}

it('guarda el valor en un input oculto con wire:model y no en Alpine', function () {
    // La regla de fondo: en Livewire 3 el x-data se vuelve a crear en cada
    // respuesta. Si el id elegido vive en Alpine, se pierde igual que en
    // `rating`. Tiene que viajar en un campo real del formulario.
    $html = buscadorLista(['value' => 'c2', 'model' => 'titular_id']);

    expect((bool) preg_match('/<input[^>]*type="hidden"[^>]*>/', $html, $m))->toBeTrue(
        'No hay ningún <input type="hidden">: el id elegido no tiene dónde vivir y '.
        'se pierde en cada respuesta de Livewire 3.'
    );

    $oculto = $m[0];

    expect(str_contains($oculto, 'name="titular_id"'))->toBeTrue(
        'El input oculto no lleva `name`: sin él no viaja en un submit normal.'
    );
    expect(str_contains($oculto, 'value="c2"'))->toBeTrue(
        'El input oculto no trae el valor que pintó el servidor.'
    );
    expect(str_contains($oculto, 'wire:model'))->toBeTrue(
        'El input oculto no lleva `wire:model`: Livewire nunca se entera de lo elegido.'
    );
});

it('pinta la etiqueta del registro elegido desde el servidor', function () {
    $html = buscadorLista([
        'value' => 'c2',
        'selectedLabel' => 'ZZ-ETIQUETA-DEL-SERVIDOR-ZZ',
    ]);

    expect((bool) preg_match('/<input[^>]*role="combobox"[^>]*>/', $html, $m))->toBeTrue(
        'No hay ningún elemento con role="combobox".'
    );

    expect(str_contains($m[0], 'value="ZZ-ETIQUETA-DEL-SERVIDOR-ZZ"'))->toBeTrue(
        'La etiqueta visible del registro elegido no la pinta el servidor: si la '.
        'pintara Alpine, en Livewire 3 el campo aparecería vacío tras cada respuesta.'
    );
});

it('deriva todos los ids del name y nunca usa uniqid', function () {
    expect(str_contains(fuenteBuscadorListaSinComentarios(), 'uniqid('))->toBeFalse(
        'El componente usa uniqid(): el id cambia en cada render, rompe el `for` de la '.
        'etiqueta y ensucia el diffing de Livewire (DESIGN §10).'
    );

    $uno = buscadorLista();
    $dos = buscadorLista();

    preg_match('/<input[^>]*role="combobox"[^>]*id="([^"]+)"/', $uno, $a);
    preg_match('/<input[^>]*role="combobox"[^>]*id="([^"]+)"/', $dos, $b);

    expect($a[1] ?? 'a')->toBe($b[1] ?? 'b', 'El id del campo cambia entre dos renders idénticos.');
    expect(str_contains($a[1] ?? '', 'titular_id'))->toBeTrue(
        'El id no sale del `name`.'
    );

    // Un name con notación de arreglo no puede producir un id que no sea un
    // selector válido: en un formulario repetido de Livewire es lo normal.
    $repetido = buscadorLista(['name' => 'items[0][titular_id]']);

    preg_match('/<input[^>]*role="combobox"[^>]*id="([^"]+)"/', $repetido, $c);

    expect((bool) preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $c[1] ?? ''))->toBeTrue(
        'Un `name` con corchetes produce un id que no es un selector válido: '.
        'lo que se sanea es el id, el `name` viaja intacto.'
    );
    expect(str_contains($repetido, 'name="items[0][titular_id]"'))->toBeTrue(
        'El `name` se saneó también en el input oculto: al backend tiene que llegar intacto.'
    );
});

it('la etiqueta apunta al campo con for', function () {
    $html = buscadorLista();

    preg_match('/<label[^>]*for="([^"]+)"/', $html, $l);
    preg_match('/<input[^>]*role="combobox"[^>]*id="([^"]+)"/', $html, $i);

    expect($l[1] ?? 'l')->toBe($i[1] ?? 'i', 'El `for` de la etiqueta no apunta al campo.');
});

it('declara el contrato ARIA completo del patrón combobox', function () {
    $html = buscadorLista();

    preg_match('/<input[^>]*role="combobox"[^>]*>/', $html, $m);
    $campo = $m[0] ?? '';

    foreach (['aria-expanded="false"', 'aria-controls=', 'aria-autocomplete="list"', 'autocomplete="off"'] as $pieza) {
        expect(str_contains($campo, $pieza))->toBeTrue(
            "Al campo role=combobox le falta `{$pieza}`."
        );
    }

    // aria-expanded y aria-activedescendant son dinámicos: nacen en el HTML del
    // servidor Y los mantiene Alpine con un enlace.
    expect(str_contains($campo, ':aria-expanded='))->toBeTrue(
        'Nadie mantiene `aria-expanded` al abrir y cerrar la lista: nace en false y se queda ahí.'
    );
    expect(str_contains($campo, ':aria-activedescendant='))->toBeTrue(
        'No hay `aria-activedescendant`: el resaltado no se anuncia.'
    );

    // aria-controls tiene que apuntar a un listbox que exista.
    preg_match('/aria-controls="([^"]+)"/', $campo, $c);

    expect((bool) preg_match('/<ul[^>]*id="'.preg_quote($c[1] ?? 'x', '/').'"[^>]*role="listbox"/', $html))->toBeTrue(
        'El `aria-controls` del campo no apunta a ningún elemento con role="listbox".'
    );

    // Y cada resultado es una opción con id propio, que es lo que puede recibir
    // el aria-activedescendant.
    preg_match_all('/<li[^>]*role="option"[^>]*id="([^"]+)"/', $html, $o);

    expect(count($o[1]))->toBe(3, 'Los resultados no son <li role="option"> con id propio.');
});

it('el foco NUNCA se mueve a las opciones', function () {
    /*
     * El riesgo declarado en la ficha. Con aria-activedescendant el foco se queda
     * en el campo: una opción enfocable rompe el anuncio y deja al lector de
     * pantalla leyendo un elemento que el combobox cree que no está activo.
     */
    $html = buscadorLista();

    preg_match_all('/<li[^>]*role="option"[^>]*>/', $html, $m);

    foreach ($m[0] as $opcion) {
        expect(str_contains($opcion, 'tabindex'))->toBeFalse(
            "Una opción lleva `tabindex` y entra al orden de tabulación: {$opcion}"
        );
    }

    // Ninguna llamada a .focus() puede apuntar a otra cosa que al campo.
    preg_match_all('/([A-Za-z0-9_$.\[\]]+)\.focus\(\)/', fuenteBuscadorListaSinComentarios(), $f);

    $ajenas = array_values(array_filter(
        $f[1] ?? [],
        fn (string $destino) => ! str_ends_with($destino, 'campo')
    ));

    expect($ajenas)->toBe([], sprintf(
        'Hay llamadas a .focus() que no apuntan al campo (%s): con aria-activedescendant '.
        'el foco se queda SIEMPRE en el campo.',
        implode(', ', $ajenas)
    ));
});

it('mueve el resaltado con las teclas obligatorias y lo sigue con scrollIntoView', function () {
    $html = buscadorLista();
    $alpine = implode(' | ', alpineBuscadorLista($html));

    $teclas = [
        'keydown.arrow-down' => 'Flecha abajo (abre y baja)',
        'keydown.arrow-up' => 'Flecha arriba',
        'keydown.enter' => 'Enter (elige el resaltado)',
        'keydown.escape' => 'Escape (cierra, y limpia a la segunda)',
        'keydown.home' => 'Inicio (primer resultado)',
        'keydown.end' => 'Fin (último resultado)',
    ];

    foreach ($teclas as $tecla => $para) {
        expect(str_contains($html, $tecla))->toBeTrue("Falta el manejador de {$para} (@{$tecla}).");
    }

    expect(str_contains($alpine, "scrollIntoView({ block: 'nearest' })"))->toBeTrue(
        'El resaltado no se sigue con scrollIntoView({block:"nearest"}): al bajar por una '.
        'lista de 20 el resultado activo se sale de la caja.'
    );

    // Escribir y Retroceso vuelven a filtrar y reabren.
    expect(str_contains($html, '@input='))->toBeTrue(
        'Escribir no reabre la lista: tras un Escape el campo queda mudo hasta hacer clic.'
    );
});

it('acota los resultados a 20 y lo dice en la región viva', function () {
    // El tope es contrato con el servidor Y candado en el componente: una lista
    // de miles de contribuyentes pintada entera es el INP perdido.
    $html = buscadorLista(['options' => opcionesBuscadorLista(50), 'search' => 'calle']);

    expect(substr_count($html, 'role="option"'))->toBe(20,
        'El componente pintó más (o menos) de 20 opciones: el tope duro de 20 es lo que '.
        'protege el INP de una lista larga.'
    );

    preg_match('/<[a-z]+[^>]*role="status"[^>]*aria-live="polite"[^>]*>(.*?)<\//s', $html, $m);
    $anuncio = trim(strip_tags($m[1] ?? ''));

    expect(str_contains($anuncio, '20'))->toBeTrue(
        "La región viva no dice cuántos resultados se muestran; dice: «{$anuncio}»."
    );
    expect(str_contains($anuncio, '50'))->toBeTrue(
        'La región viva no avisa de que hay más resultados de los que se muestran: la '.
        'persona cree que su registro no existe.'
    );
});

it('anuncia el recuento desde el servidor, jamás por tecla', function () {
    $html = buscadorLista(['options' => opcionesBuscadorLista(3), 'search' => 'calle']);

    expect((bool) preg_match(
        '/<([a-z]+)[^>]*role="status"[^>]*aria-live="polite"[^>]*>(.*?)<\/\1>/s',
        $html,
        $m
    ))->toBeTrue('No hay ninguna región `role="status" aria-live="polite"` con el recuento.');

    $region = $m[0];

    expect(str_contains($region, 'x-text'))->toBeFalse(
        'El recuento lo escribe Alpine: entonces se reescribe en CADA tecla y el lector '.
        'de pantalla parlotea. Lo pinta el servidor, que llega ya con el debounce aplicado.'
    );
    expect(str_contains($m[2], '3 resultados'))->toBeTrue(
        'El recuento del servidor no aparece en la región viva.'
    );

    // Sin búsqueda no se anuncia nada: una región viva que habla al cargar la
    // página es ruido.
    $enBlanco = buscadorLista(['options' => [], 'search' => null]);

    preg_match('/<([a-z]+)[^>]*role="status"[^>]*aria-live="polite"[^>]*>(.*?)<\/\1>/s', $enBlanco, $b);

    expect(trim(strip_tags($b[2] ?? '')))->toBe('',
        'La región viva del recuento nace con texto: se anuncia sola al cargar la página.'
    );

    // Y mientras carga no anuncia un recuento viejo como si fuera el nuevo.
    $cargando = buscadorLista(['options' => opcionesBuscadorLista(3), 'search' => 'cal', 'loading' => true]);

    preg_match('/<([a-z]+)[^>]*role="status"[^>]*aria-live="polite"[^>]*>(.*?)<\/\1>/s', $cargando, $c);

    expect(trim(strip_tags($c[2] ?? 'x')))->toBe('',
        'Mientras el servidor busca, la región viva sigue anunciando el recuento anterior.'
    );
});

it('pide el filtrado al servidor con debounce de 300 ms', function () {
    $html = buscadorLista(['searchModel' => 'busqueda']);

    expect(str_contains($html, 'wire:model.live.debounce.300ms="busqueda"'))->toBeTrue(
        'El campo no filtra con `wire:model.live.debounce.300ms`: sin debounce cada tecla '.
        'es un viaje al servidor y una consulta a la tabla de personas.'
    );
});

it('trae el botón de limpiar y el estado de carga', function () {
    $html = buscadorLista(['loading' => true]);

    expect((bool) preg_match('/<button[^>]*class="[^"]*muni-combo__clear[^"]*"[^>]*>/', $html, $m))->toBeTrue(
        'No hay botón de limpiar.'
    );

    $boton = $m[0];

    expect(str_contains($boton, 'type="button"'))->toBeTrue(
        'El botón de limpiar no declara type="button": dentro de un formulario lo envía.'
    );
    expect((bool) preg_match('/aria-label="[^"]+"/', $boton))->toBeTrue(
        'El botón de limpiar no tiene nombre accesible.'
    );
    expect(str_contains(implode(' ', alpineBuscadorLista($html)), 'texto'))->toBeTrue(
        'El botón de limpiar no depende de que haya texto: la ficha pide que aparezca solo entonces.'
    );

    // El dibujo de carga es decorativo; lo semántico es aria-busy sobre la lista.
    expect((bool) preg_match('/class="[^"]*muni-combo__spin[^"]*"[^>]*aria-hidden="true"/', $html))->toBeTrue(
        'El indicador de carga no es decorativo: un dibujo que no está oculto para el '.
        'lector se lee como contenido.'
    );
    expect(str_contains($html, 'aria-busy="true"'))->toBeTrue(
        'Mientras carga, la lista no declara aria-busy.'
    );
});

it('el desplegable no se corta dentro de un modal ni de una tabla', function () {
    $css = cssBuscadorLista();

    expect((bool) preg_match('/\.muni-combo__lista\s*\{[^}]*position:\s*absolute/', $css))->toBeTrue(
        'La base del desplegable no es `position:absolute`: sin base no hay a qué volver '.
        'cuando el navegador no soporta anchor positioning.'
    );

    expect((bool) preg_match('/@supports\s*\([^)]*anchor-name[^)]*\)\s*\{/', $css))->toBeTrue(
        'El anchor positioning no está dentro de un @supports: DESIGN §10 exige que el CSS '.
        'moderno vaya como mejora progresiva.'
    );

    expect(str_contains(fuenteBuscadorListaSinComentarios(), 'anchor-name:--'))->toBeTrue(
        'Cada instancia necesita su propio `anchor-name` en línea: un nombre compartido en '.
        'el bloque de estilos ancla todos los desplegables de la página al mismo campo.'
    );
});

it('un apóstrofo en cualquier texto del anfitrión no tumba el Alpine de la página', function () {
    $html = buscadorLista([
        'label' => "Calle o pasaje 'Los Aromos'",
        'placeholder' => "Escribe la calle' del vecino",
        'selectedLabel' => "Villa O'Higgins",
        'options' => [['value' => 'a', 'label' => "Pasaje O'Higgins", 'hint' => "Sector 'norte'"]],
        'search' => "O'H",
    ]);

    $rotas = [];

    foreach (alpineBuscadorLista($html) as $expr) {
        $limpia = str_replace("\\'", '', $expr);

        if (substr_count($limpia, "'") % 2 !== 0) {
            $rotas[] = $expr;
        }
    }

    expect($rotas)->toBe([], sprintf(
        'Estas expresiones de Alpine quedan con las comillas simples descuadradas y tumban '.
        'el Alpine de la página ENTERA: %s',
        implode(' | ', $rotas)
    ));
});

it('ningún texto del anfitrión viaja dentro de una expresión de Alpine', function () {
    // La defensa de fondo: una expresión de Alpine es código que se EVALÚA. Una
    // etiqueta venida de la base de datos con `'+fetch(...)+'` se ejecutaría.
    $marca = 'ZZ-TEXTO-DEL-ANFITRION-ZZ';

    $html = buscadorLista([
        'label' => $marca.'-LABEL',
        'placeholder' => $marca.'-PLACEHOLDER',
        'selectedLabel' => $marca.'-ELEGIDA',
        'hint' => $marca.'-AYUDA',
        'error' => $marca.'-ERROR',
        'options' => [['value' => 'a', 'label' => $marca.'-OPCION']],
    ]);

    $culpables = array_values(array_filter(
        alpineBuscadorLista($html),
        fn (string $expr) => str_contains($expr, $marca)
    ));

    expect($culpables)->toBe([], sprintf(
        'Texto del anfitrión dentro de una expresión de Alpine, que es código evaluado: %s',
        implode(' | ', $culpables)
    ));

    expect(str_contains($html, e($marca.'-OPCION')))->toBeTrue(
        'La etiqueta de la opción no está en el HTML del servidor.'
    );
});

it('encadena la ayuda y el error como el resto de los campos de la casa', function () {
    $html = buscadorLista([
        'hint' => 'Escribe el RUT o el nombre',
        'error' => 'Elige un titular de la lista',
    ], 'aria-describedby="ajeno"');

    preg_match('/<input[^>]*role="combobox"[^>]*>/', $html, $m);
    $campo = $m[0] ?? '';

    expect(str_contains($campo, 'aria-invalid="true"'))->toBeTrue(
        'Con error, el campo no marca aria-invalid.'
    );

    preg_match('/aria-describedby="([^"]+)"/', $campo, $d);
    $descrito = $d[1] ?? '';

    expect(str_contains($descrito, 'ajeno'))->toBeTrue(
        'El `aria-describedby` del consumidor desapareció: merge() REEMPLAZA, hay que '.
        'encadenar a mano y sacarlo de la bolsa (DESIGN §8).'
    );
    expect(substr_count($descrito, '-hint'))->toBe(1, 'La ayuda no está encadenada en aria-describedby.');
    expect(substr_count($descrito, '-error'))->toBe(1, 'El error no está encadenado en aria-describedby.');
});

it('muestra el foco con el outline del sistema y no con una sombra', function () {
    $css = cssBuscadorLista();

    foreach (['.muni-combo__input:focus', '.muni-combo__clear:focus'] as $selector) {
        expect((bool) preg_match(
            '/'.preg_quote($selector, '/').'[^{]*\{([^}]*)\}/',
            $css,
            $m
        ))->toBeTrue("No hay regla de foco para `{$selector}`: al tabular no aparece nada (WCAG 2.2 AA 2.4.7).");

        expect((bool) preg_match(
            '/outline\s*:\s*3px\s+solid\s+var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/',
            $m[1] ?? ''
        ))->toBeTrue(
            "`{$selector}` no dibuja el outline del sistema con su cadena de respaldo: dentro ".
            'de Filament la box-shadow se computa transparente y no queda ningún indicador.'
        );
    }
});

it('no escribe un solo color literal y respeta el movimiento reducido', function () {
    $fuente = fuenteBuscadorListaSinComentarios();

    // #767676 es el tercer respaldo canónico del outline y es la única excepción.
    $literales = [];

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $fuente, $m);

    foreach ($m[0] as $color) {
        if (strtolower($color) !== '#767676') {
            $literales[] = $color;
        }
    }

    preg_match_all('/\brgba?\(/', $fuente, $rgb);

    expect($literales)->toBe([], 'Colores literales en el componente (DESIGN §10): '.implode(', ', $literales));
    expect($rgb[0])->toBe([], 'El componente usa rgb()/rgba(): los colores salen de tokens --muni-*.');

    // Toda transición con la duración del token, que ya baja a 0 ms bajo
    // prefers-reduced-motion. Una duración fija ignora la preferencia (DESIGN §6).
    // El lookbehind deja fuera `x-transition:enter`, que es un atributo de Alpine
    // y no una declaración de CSS.
    preg_match_all('/(?<![-a-zA-Z])transition\s*:\s*([^;"\']+)/', $fuente, $t);

    foreach ($t[1] as $transicion) {
        expect(str_contains($transicion, 'var(--muni-dur)'))->toBeTrue(
            'Esta transición no usa var(--muni-dur) y por lo tanto ignora '.
            "`prefers-reduced-motion` (DESIGN §6): «{$transicion}»."
        );
    }
});

it('las opciones y el botón de limpiar llegan al área táctil mínima', function () {
    $css = cssBuscadorLista();

    preg_match('/\.muni-combo__opcion\s*\{([^}]*)\}/', $css, $o);

    expect((bool) preg_match('/min-height\s*:\s*(\d+)px/', $o[1] ?? '', $mo))->toBeTrue(
        'La opción no declara una altura mínima: con una etiqueta corta queda bajo los 24 px.'
    );
    expect((int) ($mo[1] ?? 0))->toBeGreaterThanOrEqual(24, 'La opción no llega a 24 px de alto.');

    preg_match('/\.muni-combo__clear\s*\{([^}]*)\}/', $css, $b);

    foreach (['min-width', 'min-height'] as $lado) {
        expect((bool) preg_match('/'.$lado.'\s*:\s*(\d+)px/', $b[1] ?? '', $mb))->toBeTrue(
            "El botón de limpiar no declara `{$lado}`."
        );
        expect((int) ($mb[1] ?? 0))->toBeGreaterThanOrEqual(24, "El botón de limpiar no llega a 24 px de {$lado}.");
    }
});

it('no usa ninguna directiva exclusiva de un major de Livewire', function () {
    // El paquete se instala en Livewire 4 (nueve sistemas) y en Livewire 3
    // (personas-graneros). Una directiva de un solo major muere en silencio.
    $fuente = fuenteBuscadorListaSinComentarios();

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($fuente, $prohibida))->toBeFalse(
            "El componente usa `{$prohibida}`, que no existe en los dos majors de Livewire."
        );
    }

    // Y no depende de ningún plugin de Alpine que no viaje en el bundle.
    foreach (['x-mask', 'x-intersect', 'x-collapse', 'x-anchor'] as $plugin) {
        expect(str_contains($fuente, $plugin))->toBeFalse(
            "El componente usa el plugin de Alpine `{$plugin}`, que no viaja con Livewire."
        );
    }
});
