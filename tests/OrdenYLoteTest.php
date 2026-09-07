<?php

use Illuminate\Support\Facades\Blade;

/**
 * EL CANDADO DEL ORDEN NUMÉRICO Y DE LA SELECCIÓN EN LOTE.
 *
 * Defecto 1 — el orden numérico con formato chileno daba resultados MAL en
 * producción. El comparador limpiaba «todo lo que no fuera dígito, punto o
 * guión» y se lo pasaba a parseFloat, que interpreta el punto como separador
 * DECIMAL. En Chile el punto es separador de MILES:
 *
 *     "1.240.000" -> 1.24      "520.000" -> 520      "80.000" -> 80
 *
 * Con eso, el padrón de patentes morosas de Rentas ordenado por monto
 * ascendente devolvía 1.240.000, 80.000, 520.000. Nadie lo notó porque la
 * columna se ve ordenada: los números están, solo que en el orden equivocado.
 *
 * Defecto 2 — no se podía operar sobre varias filas: derivar veinte
 * requerimientos al mismo departamento eran veinte aperturas de ficha.
 *
 * POR QUÉ ESTAS PRUEBAS CORREN NODE. El comparador es JavaScript y la suite es
 * PHP. Comprobar que la cadena del comparador cambió no prueba NADA: pasaría
 * igual con una implementación equivocada. Así que se extrae el `x-data` REAL
 * del HTML servido —el mismo objeto que Alpine evalúa en el navegador— y se
 * ejecuta bajo node. Lo que estas pruebas ejercitan es el código que se envía.
 *
 * `toContain($x, 'mensaje')` NO sirve acá: en Pest el segundo argumento es otra
 * aguja, no un mensaje. Todo va con `expect(bool)->toBeTrue('mensaje')`.
 */

/** Ruta del binario de node, o null si esta máquina no lo tiene. */
function rutaDeNode(): ?string
{
    $ruta = trim((string) shell_exec('command -v node 2>/dev/null'));

    return $ruta === '' ? null : $ruta;
}

/** El HTML servido de la tabla. */
function tablaHtml(array $columns, array $rows, string $extra = ''): string
{
    return Blade::render(
        '<x-muni::sortable-table :columns="$columns" :rows="$rows" '.$extra.' />',
        ['columns' => $columns, 'rows' => $rows],
    );
}

/**
 * El objeto `x-data` REAL de la tabla, ya sin entidades HTML: es JavaScript
 * literal, listo para evaluarse.
 */
function tablaXData(array $columns, array $rows, string $extra = ''): string
{
    $html = tablaHtml($columns, $rows, $extra);

    expect((bool) preg_match('/x-data="(.*?)"\s*\n/s', $html, $m))->toBeTrue(
        'No se encontró el x-data de la tabla: el barrido está roto.'
    );

    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

/**
 * Ejecuta `$codigo` con el estado de la tabla en `s` y devuelve lo que retorne,
 * decodificado. Es el puente que hace ejecutable el JavaScript del componente.
 */
function ejecutaEnLaTabla(string $xData, string $codigo): mixed
{
    $node = rutaDeNode();

    expect($node)->not->toBeNull(
        'No hay node en esta máquina y el comparador de la tabla es JavaScript: sin ejecutarlo, '.
        'la prueba no comprobaría nada. Instala node (el repo ya lo usa para la reja de a11y).'
    );

    $script = "const s = {$xData};\n"
        ."const out = (function(){ {$codigo} })();\n"
        .'process.stdout.write(JSON.stringify(out === undefined ? null : out));';

    $archivo = tempnam(sys_get_temp_dir(), 'muni-st-').'.js';
    file_put_contents($archivo, $script);

    $salida = [];
    $codigoSalida = 0;
    exec(escapeshellarg((string) $node).' '.escapeshellarg($archivo).' 2>&1', $salida, $codigoSalida);
    @unlink($archivo);

    $texto = implode("\n", $salida);

    expect($codigoSalida)->toBe(0, "El x-data de la tabla no es JavaScript válido o reventó al ejecutarse:\n".$texto);

    return json_decode($texto, true, 512, JSON_THROW_ON_ERROR);
}

/** Columnas y filas de un padrón de patentes morosas de verdad. */
function columnasPadron(array $extraMonto = []): array
{
    return [
        ['key' => 'rol', 'label' => 'Rol'],
        array_merge(['key' => 'monto', 'label' => 'Monto', 'align' => 'right', 'mono' => true], $extraMonto),
    ];
}

/** Ordena la columna `$key` con el comparador REAL y devuelve los valores en orden. */
function ordenaPorColumna(array $columns, array $rows, string $key, int $dir = 1): array
{
    $xData = tablaXData($columns, $rows);

    return ejecutaEnLaTabla($xData, "s.sortKey = '{$key}'; s.sortDir = {$dir}; return s.view.map(r => r['{$key}']);");
}

it('ordena los montos en formato chileno por su valor y no por parseFloat', function () {
    // El defecto real: el punto es separador de MILES. Con el comparador viejo
    // esto devolvía 1.240.000, 80.000, 520.000 — el listado de morosos al revés.
    $orden = ordenaPorColumna(
        columnasPadron(),
        [
            ['rol' => '4501-2', 'monto' => '1.240.000'],
            ['rol' => '4502-9', 'monto' => '80.000'],
            ['rol' => '4503-7', 'monto' => '520.000'],
        ],
        'monto',
    );

    expect($orden)->toBe(
        ['80.000', '520.000', '1.240.000'],
        'El orden ascendente por monto no es numérico: el punto se está leyendo como separador '.
        'decimal, así que 1.240.000 vale 1,24 y queda antes que 80.000.'
    );
});

it('el orden descendente por monto es el inverso exacto', function () {
    $orden = ordenaPorColumna(
        columnasPadron(),
        [
            ['rol' => '4501-2', 'monto' => '$ 80.000'],
            ['rol' => '4502-9', 'monto' => '$ 1.240.000'],
            ['rol' => '4503-7', 'monto' => '$ 520.000'],
        ],
        'monto',
        -1,
    );

    expect($orden)->toBe(
        ['$ 1.240.000', '$ 520.000', '$ 80.000'],
        'El signo peso delante del monto rompe el orden: hay que ignorarlo, no dejar que anule el número.'
    );
});

it('no rompe el decimal escrito con coma ni el número ya normalizado del backend', function () {
    // La solución fácil —borrar todos los puntos— rompe estos dos casos.
    $porcentajes = ordenaPorColumna(
        [['key' => 'p', 'label' => 'Avance']],
        [['p' => '12,5 %'], ['p' => '9,75 %'], ['p' => '100 %']],
        'p',
    );

    expect($porcentajes)->toBe(
        ['9,75 %', '12,5 %', '100 %'],
        'Con coma decimal el orden se fue a texto o se leyó el número mal: 9,75 < 12,5 < 100.'
    );

    $normalizados = ordenaPorColumna(
        [['key' => 'n', 'label' => 'Monto']],
        [['n' => '1240000.50'], ['n' => '999999.99'], ['n' => '1240000.05']],
        'n',
    );

    expect($normalizados)->toBe(
        ['999999.99', '1240000.05', '1240000.50'],
        'Un número ya normalizado por el backend (punto decimal, sin agrupar) se está leyendo como '.
        'si el punto fuera separador de miles.'
    );
});

it('las fechas dd-mm-aaaa se ordenan como fechas y no como texto ni como el número del día', function () {
    // Hoy «09-01-2026» se convierte en 9 y «10-12-2025» en 10, así que enero de
    // 2026 aparece ANTES que diciembre de 2025.
    $orden = ordenaPorColumna(
        [['key' => 'f', 'label' => 'Vencimiento']],
        [['f' => '09-01-2026'], ['f' => '10-12-2025'], ['f' => '31-12-2025']],
        'f',
    );

    expect($orden)->toBe(
        ['10-12-2025', '31-12-2025', '09-01-2026'],
        'Las fechas dd-mm-aaaa no se ordenan cronológicamente: se están comparando como texto o '.
        'por el número del día.'
    );
});

it('un RUT con .muni-num se ordena por su número y no por su primera cifra', function () {
    $orden = ordenaPorColumna(
        [['key' => 'rut', 'label' => 'RUT', 'mono' => true]],
        [['rut' => '12.345.678-9'], ['rut' => '9.876.543-2'], ['rut' => '1.234.567-K'], ['rut' => '999.999-9']],
        'rut',
    );

    expect($orden)->toBe(
        ['999.999-9', '1.234.567-K', '9.876.543-2', '12.345.678-9'],
        'El padrón ordenado por RUT pone 12.345.678-9 antes que 9.876.543-2: se está comparando '.
        'como texto, o el dígito verificador está entrando en el número.'
    );
});

it('lo que no es un número reconocible cae a orden de texto en vez de inventar una cifra', function () {
    // «4501-2» es un rol de patente, no una cantidad: el comparador viejo le
    // sacaba 4501 y lo trataba como número.
    $orden = ordenaPorColumna(
        [['key' => 'obs', 'label' => 'Observación']],
        [['obs' => 'Sin domicilio'], ['obs' => 'Con convenio'], ['obs' => 'En cobranza']],
        'obs',
    );

    expect($orden)->toBe(
        ['Con convenio', 'En cobranza', 'Sin domicilio'],
        'El texto dejó de ordenarse alfabéticamente en español.'
    );

    $xData = tablaXData([['key' => 'x', 'label' => 'X']], [['x' => 'a']]);

    $noNumeros = ejecutaEnLaTabla($xData, "return ['4501-2', 'UF 3,5', 'Sin monto', '12.34.56'].map(v => s.toNumber(v));");

    expect($noNumeros)->toBe(
        [null, null, null, null],
        'El comparador sigue extrayendo un número de cadenas que no son una cantidad: eso es '.
        'exactamente lo que ordenaba mal el padrón.'
    );
});

it('una columna puede declarar cómo se ordena y su declaración manda sobre el heurístico', function () {
    $comoTexto = ordenaPorColumna(
        columnasPadron(['sort' => 'texto']),
        [['rol' => 'a', 'monto' => '1.240.000'], ['rol' => 'b', 'monto' => '80.000']],
        'monto',
    );

    expect($comoTexto)->toBe(
        ['1.240.000', '80.000'],
        "Una columna con 'sort' => 'texto' se sigue ordenando como número: la salida explícita del ".
        'consumidor no existe o no manda.'
    );

    $comoNumero = ordenaPorColumna(
        [['key' => 'n', 'label' => 'N', 'sort' => 'numero']],
        [['n' => '10'], ['n' => '9'], ['n' => '1.000']],
        'n',
    );

    expect($comoNumero)->toBe(
        ['9', '10', '1.000'],
        "Una columna con 'sort' => 'numero' no se ordena numéricamente."
    );

    $comoFecha = ordenaPorColumna(
        [['key' => 'f', 'label' => 'F', 'sort' => 'fecha']],
        [['f' => '2026-01-09'], ['f' => '2025-12-10']],
        'f',
    );

    expect($comoFecha)->toBe(
        ['2025-12-10', '2026-01-09'],
        "Una columna con 'sort' => 'fecha' no se ordena cronológicamente."
    );
});

it('el orden sigue funcionando en las tablas que ya existen, sin declarar nada', function () {
    // Aditivo: el consumidor que no pasa `sort` no tiene que cambiar nada.
    $orden = ordenaPorColumna(
        columnasPadron(),
        [['rol' => '4502-9', 'monto' => '80.000'], ['rol' => '4501-2', 'monto' => '1.240.000']],
        'rol',
    );

    expect($orden)->toBe(['4501-2', '4502-9'], 'El orden por una columna sin «sort» dejó de funcionar.');
});

/*
 * ---------------------------------------------------------------------------
 * Selección en lote
 * ---------------------------------------------------------------------------
 */

/** Las mismas filas para todas las pruebas de selección. */
function filasSeleccion(): array
{
    return [
        ['rol' => '4501-2', 'monto' => '1.240.000', '_tone' => 'danger'],
        ['rol' => '4502-9', 'monto' => '80.000'],
        ['rol' => '4503-7', 'monto' => '520.000'],
    ];
}

/** El HTML de una tabla seleccionable. */
function tablaConLote(string $extra = ''): string
{
    return tablaHtml(columnasPadron(), filasSeleccion(), 'selectable row-key="rol" '.$extra);
}

it('sin selectable la tabla no cambia en nada: ni casilla, ni columna, ni barra', function () {
    $html = tablaHtml(columnasPadron(), filasSeleccion());

    expect((bool) preg_match('/type="checkbox"/', $html))->toBeFalse(
        'Una tabla que no pidió selección trae casillas: el cambio no es aditivo y rompe a los '.
        'nueve sistemas que ya la usan.'
    );
});

it('cada fila trae una casilla NATIVA con nombre accesible propio', function () {
    $html = tablaConLote();

    expect((bool) preg_match('/role="(checkbox|grid)"/', $html))->toBeFalse(
        'Hay un role="checkbox" o un role="grid": la ficha exige casillas nativas dentro de una '.
        '<table> normal, no divisiones con rol.'
    );

    preg_match('#<tbody>(.*?)</tbody>#s', $html, $m);
    $tbody = $m[1] ?? '';

    expect((bool) preg_match('/<input\b[^>]*type="checkbox"/', $tbody))->toBeTrue(
        'Las filas no tienen casilla de selección.'
    );

    preg_match('/<input\b[^>]*type="checkbox"[^>]*>/', $tbody, $casilla);

    expect((bool) preg_match('/(:aria-label|x-bind:aria-label)=/', $casilla[0] ?? ''))->toBeTrue(
        'La casilla de la fila no tiene nombre accesible propio: un lector de pantalla oiría '.
        '«casilla, casilla, casilla» sin saber cuál fila es cuál. Ficha seleccion-lote.'
    );
});

it('la casilla de la cabecera selecciona lo VISIBLE tras el filtro, no lo invisible', function () {
    $xData = tablaXData(columnasPadron(), filasSeleccion(), 'selectable row-key="rol"');

    // Filtro activo: solo queda una fila a la vista.
    $seleccion = ejecutaEnLaTabla($xData, "s.q = '4502'; s.toggleAll(true); return s.selected;");

    expect($seleccion)->toBe(
        ['4502-9'],
        'La casilla de cabecera marcó filas que el filtro esconde: es el riesgo de datos de la '.
        'ficha, porque la acción en lote caería sobre registros que el usuario no vio.'
    );

    $todo = ejecutaEnLaTabla($xData, 's.toggleAll(true); return s.selected;');

    expect($todo)->toBe(['4501-2', '4502-9', '4503-7'], 'Sin filtro, la cabecera debe marcar las tres filas.');
});

it('el recuento distingue lo seleccionado de lo visible cuando hay filtro', function () {
    $xData = tablaXData(columnasPadron(), filasSeleccion(), 'selectable row-key="rol"');

    $texto = ejecutaEnLaTabla($xData, "s.toggleAll(true); s.q = '4502'; return s.selectionText;");

    expect(is_string($texto) && str_contains($texto, '3'))->toBeTrue(
        'El recuento no dice cuántas filas van seleccionadas.'
    );

    expect(str_contains((string) $texto, '1'))->toBeTrue(
        'Con un filtro activo el recuento no dice cuántas de las seleccionadas están a la vista: '.
        'quien pulsa «aplicar» no sabe sobre cuántas cae la acción. Texto: '.$texto
    );
});

it('el estado indeterminado va por la propiedad del DOM, nunca por aria-checked="mixed"', function () {
    $html = tablaConLote();

    expect((bool) preg_match('/aria-checked="mixed"/', $html))->toBeFalse(
        'aria-checked es para elementos con role="checkbox": sobre un input nativo el estado mixto '.
        'se expone SOLO con la propiedad indeterminate.'
    );

    expect((bool) preg_match('/x-effect="[^"]*indeterminate/', $html))->toBeTrue(
        'Nadie fija la propiedad `indeterminate` de la casilla de cabecera: con selección parcial '.
        'la cabecera se ve vacía o llena, y las dos mienten.'
    );

    $xData = tablaXData(columnasPadron(), filasSeleccion(), 'selectable row-key="rol"');

    $estados = ejecutaEnLaTabla($xData, <<<'JS'
        const r = {};
        s.toggleRow('4502-9', true);
        r.parcial = [s.allVisibleSelected, s.someVisibleSelected];
        s.toggleAll(true);
        r.todas = [s.allVisibleSelected, s.someVisibleSelected];
        s.clearSelection();
        r.ninguna = [s.allVisibleSelected, s.someVisibleSelected];
        return r;
    JS);

    expect($estados)->toBe(
        ['parcial' => [false, true], 'todas' => [true, true], 'ninguna' => [false, false]],
        'Los tres estados de la cabecera (vacía, mixta, llena) no se distinguen.'
    );
});

it('el recuento se anuncia en una región viva que existe desde el primer render', function () {
    $html = tablaConLote();

    preg_match_all('/<div\b[^>]*role="status"[^>]*>/', $html, $regiones);

    $anuncio = array_values(array_filter(
        $regiones[0] ?? [],
        fn (string $div) => (bool) preg_match('/x-text="[^"]*(selection|anuncioSeleccion)/i', $div),
    ));

    expect($anuncio)->not->toBeEmpty(
        'El recuento de seleccionadas no se anuncia: una región viva que nace junto con su '.
        'contenido no la lee ningún lector (WCAG 2.2 AA 4.1.3), así que tiene que estar desde '.
        'el primer render.'
    );

    expect((bool) preg_match('/aria-live="polite"/', $anuncio[0]))->toBeTrue(
        'La región del recuento no es aria-live="polite".'
    );
});

it('la barra de acciones aparece solo con selección, y no tapa contenido ni roba el foco', function () {
    $html = tablaConLote();

    expect((bool) preg_match('/muni-st__bulk/', $html))->toBeTrue('No existe la barra de acciones en lote.');

    preg_match('/<div\b[^>]*muni-st__bulk[^>]*>/', $html, $barra);

    expect((bool) preg_match('/x-show="[^"]*selected/', $barra[0] ?? ''))->toBeTrue(
        'La barra no está condicionada a que haya selección.'
    );

    $componente = file_get_contents(__DIR__.'/../resources/views/components/sortable-table.blade.php');
    preg_match('#<style>(.*?)</style>#s', $componente, $style);
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $style[1] ?? '');

    preg_match('/\.muni-st__bulk\s*\{([^}]*)\}/', $css, $regla);

    expect((bool) preg_match('/position\s*:\s*(fixed|absolute)/', $regla[1] ?? ''))->toBeFalse(
        'La barra flota sobre la página: tapa contenido y en móvil se come la última fila. '.
        'Va en el flujo del documento.'
    );

    expect((bool) preg_match('/(autofocus|\$refs?\.[A-Za-z]+\.focus\(\)|\.focus\(\))/', $html))->toBeFalse(
        'Algo mueve el foco al aparecer la barra: quien marca una casilla pierde su sitio en la tabla.'
    );
});

it('Escape limpia la selección y va acotado al contenedor, nunca a .window', function () {
    $html = tablaConLote();

    expect((bool) preg_match('/(@|x-on:)keydown\.escape\.window/', $html))->toBeFalse(
        'El Escape está en .window: dentro de un modal de Filament le roba el cierre al modal '.
        '(ficha seleccion-lote, punto 8).'
    );

    expect((bool) preg_match('/(@|x-on:)keydown\.escape=/', $html))->toBeTrue(
        'Escape no limpia la selección.'
    );
});

it('la columna de selección y la barra no se imprimen', function () {
    $componente = file_get_contents(__DIR__.'/../resources/views/components/sortable-table.blade.php');

    preg_match('/@media print\s*\{(.*?)\n\s*\}/s', $componente, $print);

    expect($print[1] ?? '')->not->toBeEmpty(
        'No hay ninguna regla @media print: estos sistemas imprimen actas y órdenes de cobro, y '.
        'una columna de casillas en papel no significa nada.'
    );

    foreach (['muni-st__pick', 'muni-st__bulk'] as $clase) {
        expect(str_contains($print[1], $clase))->toBeTrue(
            "La regla de impresión no oculta «{$clase}»."
        );
    }
});

it('la franja de morosidad cae sobre el dato y no sobre la casilla', function () {
    $componente = file_get_contents(__DIR__.'/../resources/views/components/sortable-table.blade.php');
    preg_match('#<style>(.*?)</style>#s', $componente, $style);
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $style[1] ?? '');

    preg_match('/\.muni-st__danger td:first-child\s*\{([^}]*)\}/', $css, $regla);

    expect($regla[1] ?? '')->not->toBeEmpty('Desapareció la franja de la fila con problema (DESIGN §9).');

    expect((bool) preg_match('/color\s*:/', $regla[1]))->toBeFalse(
        'El rojo y la negrita siguen aplicándose a la PRIMERA celda: con la columna de selección '.
        'activa, esa celda es la casilla, así que el color del dato cae sobre el control y el dato '.
        'queda en negro (ficha seleccion-lote, punto 4).'
    );

    expect((bool) preg_match('/box-shadow\s*:\s*inset/', $regla[1]))->toBeTrue(
        'La banda vertical del borde izquierdo de la fila desapareció.'
    );
});

it('el estado vacío abarca también la columna de selección', function () {
    $html = tablaHtml(columnasPadron(), [], 'selectable row-key="rol"');

    expect((bool) preg_match('/:colspan="cols\.length"/', $html))->toBeFalse(
        'El colspan del estado vacío no cuenta la columna de selección: la celda «Sin resultados» '.
        'deja una columna suelta y descuadra la tabla.'
    );
});

it('no se puede declarar el ámbito «todo el filtro» sin el conteo del servidor', function () {
    // El riesgo grave de la ficha no es de foco, es de datos: «seleccionar todo»
    // tiene que decir si son las 20 filas de la página o los 3.400 del filtro, y
    // ese número solo lo sabe el servidor.
    $motivo = null;

    try {
        tablaHtml(columnasPadron(), filasSeleccion(), 'selectable row-key="rol" selection-scope="filter"');
    } catch (Throwable $e) {
        // Blade envuelve lo que revienta al renderizar en una ViewException, y a
        // veces en más de una: hay que bajar hasta el fondo de la cadena.
        $motivo = $e;

        while ($motivo->getPrevious() !== null) {
            $motivo = $motivo->getPrevious();
        }
    }

    expect($motivo)->toBeInstanceOf(
        InvalidArgumentException::class,
        'Una tabla con ámbito «todo el filtro» se renderizó sin el conteo del servidor: la barra '.
        'diría «seleccionar todo» sin decir de cuántos registros habla.'
    );

    $html = tablaHtml(
        columnasPadron(),
        filasSeleccion(),
        'selectable row-key="rol" selection-scope="filter" :selection-total="3400"',
    );

    expect(str_contains($html, '3.400') || str_contains($html, '3400'))->toBeTrue(
        'Con ámbito «todo el filtro» la tabla no muestra el conteo del servidor.'
    );
});
