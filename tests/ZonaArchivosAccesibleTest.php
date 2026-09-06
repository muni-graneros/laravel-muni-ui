<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA ZONA DE CARGA DE ARCHIVOS
|--------------------------------------------------------------------------
|
| `<x-muni::file-dropzone>` es el adjunto del informe médico y del certificado
| en el registro de la credencial de discapacidad, y el respaldo del giro en la
| solicitud de patente comercial. Llegó a producción con cuatro defectos
| medidos, en orden de gravedad:
|
| 1. INYECCIÓN EN ALPINE. `{{ $label }}` se interpolaba dentro de una cadena de
|    comillas simples de un `x-text`. Blade escapa el apóstrofo a `&#039;`, el
|    navegador lo decodifica de vuelta DENTRO del valor del atributo y la
|    expresión queda con las comillas descuadradas: Alpine lanza un error de
|    sintaxis y se cae la página ENTERA, no solo el componente. Y no es solo el
|    apóstrofo: una etiqueta que venga de configuración o de la base de datos
|    con `'+fetch(...)+'` se EJECUTA. Por eso ninguna expresión de Alpine puede
|    volver a llevar texto del anfitrión dentro.
| 2. FOCO INVISIBLE (WCAG 2.2 AA 2.4.7). El `<input type="file">` real está con
|    `opacity:0` estirado sobre toda la zona: sigue en el orden de tabulación,
|    pero `.muni-dz` no tenía ninguna regla `:focus-within`, así que al llegar
|    con Tab no aparecía absolutamente nada.
| 3. RESULTADO MUDO (WCAG 2.2 AA 4.1.3). Elegir o soltar archivos actualizaba el
|    estado de Alpine sin ninguna región viva: un lector de pantalla no se
|    enteraba de que se adjuntó algo.
| 4. SIN FORMA DE QUITAR y con un `hint` que promete «hasta 10 MB» que nada
|    validaba.
|
| Se prueba sobre el HTML renderizado y sobre el bloque `<style>` del propio
| componente, con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra cadena a buscar, no un mensaje, y la
| aserción se desactiva sin avisar.
*/

/** El `file-dropzone.blade.php` crudo, para mirarle el CSS y las expresiones. */
function fuenteZonaArchivos(): string
{
    return file_get_contents(__DIR__.'/../resources/views/components/file-dropzone.blade.php');
}

/** El contenido de los bloques `<style>` del componente, sin comentarios. */
function cssZonaArchivos(): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteZonaArchivos(), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/**
 * Los valores de todos los atributos de Alpine del HTML dado, ya decodificados
 * como los ve el navegador (`x-…`, `@…`, `:…`).
 *
 * @return array<int, string>
 */
function expresionesAlpine(string $html): array
{
    preg_match_all('/\s(?:x-[a-z0-9.:-]+|@[a-z0-9.-]+|:[a-z0-9-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

it('una etiqueta con apóstrofo no descuadra ninguna expresión de Alpine', function () {
    // «Sube tu' documento» es el caso real: cualquier texto traducido o venido
    // de la base de datos puede traer un apóstrofo.
    $html = Blade::render(
        '<x-muni::file-dropzone :label="$label" />',
        ['label' => "Sube tu' documento"]
    );

    $rotas = [];

    foreach (expresionesAlpine($html) as $expr) {
        // Se quitan los apóstrofos ya escapados en JS (\') antes de contar: lo
        // que rompe la expresión es un apóstrofo suelto.
        $limpia = str_replace("\\'", '', $expr);

        if (substr_count($limpia, "'") % 2 !== 0) {
            $rotas[] = $expr;
        }
    }

    expect($rotas)->toBe([], sprintf(
        'Estas expresiones de Alpine quedan con las comillas simples descuadradas y '.
        'tumban el Alpine de la página entera: %s',
        implode(' | ', $rotas)
    ));
});

it('el texto de la etiqueta nunca viaja dentro de una expresión de Alpine', function () {
    // La defensa de fondo: no basta con que el apóstrofo no rompa hoy; el texto
    // del anfitrión no puede estar dentro de una expresión que Alpine EVALÚA,
    // porque un label con `'+fetch(...)+'` se ejecutaría.
    $marca = 'ZZ-ETIQUETA-DE-PRUEBA-ZZ';

    $html = Blade::render(
        '<x-muni::file-dropzone :label="$label" :hint="$hint" />',
        ['label' => $marca, 'hint' => $marca.'-HINT']
    );

    $culpables = array_values(array_filter(
        expresionesAlpine($html),
        fn (string $expr) => str_contains($expr, $marca)
    ));

    expect($culpables)->toBe([], sprintf(
        'El texto del anfitrión aparece dentro de una expresión de Alpine, que es '.
        'código evaluado: %s',
        implode(' | ', $culpables)
    ));

    // Y tiene que estar en el HTML del servidor: si el texto solo lo pinta
    // Alpine, antes de que arranque la zona no dice qué hay que subir.
    expect(str_contains($html, e($marca)))->toBeTrue(
        'La etiqueta no está en el HTML del servidor: la zona nace muda hasta que arranca Alpine.'
    );
});

it('la zona muestra el foco cuando el input invisible lo recibe', function () {
    /*
     * `:focus-within` y no `:has(input:focus-visible)`: `:focus-within` es
     * universal desde 2017 y no necesita `@supports`. `outline-offset` POSITIVO
     * porque la zona es grande y el borde punteado se come un offset negativo.
     */
    $css = cssZonaArchivos();

    expect((bool) preg_match('/\.muni-dz:focus-within\s*\{([^}]*)\}/', $css, $m))->toBeTrue(
        'No existe ninguna regla `.muni-dz:focus-within`: al tabular hasta la zona no '.
        'aparece ningún indicador de foco (WCAG 2.2 AA 2.4.7).'
    );

    $cuerpo = $m[1] ?? '';

    expect((bool) preg_match('/outline\s*:\s*3px\s+solid\s+var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/', $cuerpo))->toBeTrue(
        'La regla de foco de la zona no dibuja el outline del sistema con su cadena de '.
        'respaldo: dentro de Filament la box-shadow se computa transparente y no queda nada.'
    );

    expect((bool) preg_match('/outline-offset\s*:\s*[0-9]/', $cuerpo))->toBeTrue(
        'El outline de la zona necesita `outline-offset` positivo: la zona es grande y '.
        'con offset negativo el anillo se pierde dentro del borde punteado.'
    );
});

it('la zona tiene una región viva que reporta los archivos elegidos', function () {
    $html = Blade::render('<x-muni::file-dropzone />');

    expect((bool) preg_match('/<([a-z]+)\b[^>]*\brole="status"[^>]*>/i', $html))->toBeTrue(
        'No hay ninguna región viva: elegir un archivo no le dice nada al lector de '.
        'pantalla (WCAG 2.2 AA 4.1.3).'
    );

    expect((bool) preg_match('/aria-live="polite"/i', $html))->toBeTrue(
        'La región del resultado no es `aria-live="polite"`.'
    );

    // La región tiene que estar FUERA del <label>: si vive dentro, su texto pasa
    // a formar parte del nombre accesible del input y el control termina
    // llamándose «informe.pdf 1,2 MB».
    expect((bool) preg_match('/<label\b[^>]*class="muni-dz"[^>]*>(.*?)<\/label>/s', $html, $m))->toBeTrue(
        'No se encontró el <label class="muni-dz"> de la zona.'
    );

    expect(str_contains($m[1] ?? '', 'role="status"'))->toBeFalse(
        'La región viva está DENTRO del <label>: su texto se le pega al nombre accesible '.
        'del input y el control deja de llamarse como su etiqueta.'
    );
});

it('el anuncio se dispara en change y en drop, jamás en dragover', function () {
    $html = Blade::render('<x-muni::file-dropzone />');

    expect((bool) preg_match('/@change="([^"]*)"/', $html, $change))->toBeTrue(
        'El input no reacciona al evento `change`.'
    );
    expect((bool) preg_match('/@drop[^=]*="([^"]*)"/', $html, $drop))->toBeTrue(
        'La zona no reacciona al evento `drop`.'
    );
    expect((bool) preg_match('/@dragover[^=]*="([^"]*)"/', $html, $over))->toBeTrue(
        'La zona no reacciona al evento `dragover`.'
    );

    // `dragover` se dispara decenas de veces por segundo mientras el puntero se
    // mueve: si tocara el anuncio, el lector de pantalla quedaría inservible.
    expect(str_contains(html_entity_decode($over[1], ENT_QUOTES | ENT_HTML5), 'anuncio'))->toBeFalse(
        'El manejador de `dragover` toca el anuncio: la región viva hablaría en cada '.
        'píxel de movimiento del puntero.'
    );
});

it('cada archivo elegido se puede quitar con el teclado, sin reabrir el diálogo', function () {
    $html = Blade::render('<x-muni::file-dropzone />');

    expect((bool) preg_match('/<button\b[^>]*\bmuni-dz__quitar\b[^>]*>/', $html))->toBeTrue(
        'No hay botón para quitar un archivo ya elegido: hay que reabrir el diálogo del '.
        'sistema y volver a elegirlos todos.'
    );

    expect((bool) preg_match('/<button\b(?=[^>]*muni-dz__quitar)[^>]*\btype="button"/', $html))->toBeTrue(
        'El botón Quitar no declara `type="button"`: dentro de un formulario lo enviaría.'
    );

    // Quitar UN archivo de un FileList obliga a reconstruir un DataTransfer y
    // reasignarlo, o el input sigue mandando al servidor el archivo que la
    // pantalla ya no muestra.
    expect(str_contains($html, 'DataTransfer'))->toBeTrue(
        'Quitar no reconstruye el FileList del input con un `DataTransfer`: la pantalla '.
        'diría una cosa y el formulario enviaría otra.'
    );

    // El botón vive fuera del <label>, o al pulsarlo se abriría el diálogo de
    // archivos del sistema en vez de quitar el archivo.
    preg_match('/<label\b[^>]*class="muni-dz"[^>]*>(.*?)<\/label>/s', $html, $m);
    expect(str_contains($m[1] ?? '', 'muni-dz__quitar'))->toBeFalse(
        'El botón Quitar está dentro del <label>: pulsarlo activa la etiqueta y abre el '.
        'diálogo de archivos en vez de quitar el archivo.'
    );
});

it('el límite de tamaño que promete el hint se valida de verdad', function () {
    $html = Blade::render('<x-muni::file-dropzone />');

    expect((bool) preg_match('/maxMb\s*:\s*10\b/', html_entity_decode($html, ENT_QUOTES | ENT_HTML5)))->toBeTrue(
        'El hint por defecto promete «hasta 10 MB» y nada valida el tamaño.'
    );

    $conLimite = Blade::render('<x-muni::file-dropzone :max-mb="2" />');

    expect((bool) preg_match('/maxMb\s*:\s*2\b/', html_entity_decode($conLimite, ENT_QUOTES | ENT_HTML5)))->toBeTrue(
        'El límite de tamaño no es configurable por el sistema anfitrión.'
    );
});

it('el bloque de estilos no ensucia el espacio global de clases del anfitrión', function () {
    $css = cssZonaArchivos();

    expect((bool) preg_match('/(^|[\s,}])\.mono\s*[,{]/m', $css))->toBeFalse(
        'El componente publica una clase global `.mono` en toda página que lo use: '.
        'choca con cualquier `.mono` de la aplicación anfitriona. La firma declarada del '.
        'sistema es `.muni-num`, que ya define data-table.blade.php.'
    );
});

it('la zona no escribe ni un solo color literal fuera del respaldo del foco', function () {
    // Toda regla de color clara necesita contraparte oscura, o sale de un token
    // `--muni-*` que ya trae las dos ramas. El único hex admitido es el gris de
    // respaldo del outline, que existe justo para los sistemas con el CSS del
    // paquete desactualizado.
    $literales = [];

    preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgb a?\(|\bhsla?\(/', cssZonaArchivos(), $m);

    foreach ($m[0] as $color) {
        if (strtolower($color) !== '#767676') {
            $literales[] = $color;
        }
    }

    expect($literales)->toBe([], sprintf(
        'La zona escribe colores literales, que no tienen contraparte en modo oscuro: %s',
        implode(', ', $literales)
    ));
});

it('el mensaje de error se lee sobre su propio fondo, no sobre el del anfitrión', function () {
    /*
     * Medido en Chromium sobre el arnés del componente: `--muni-danger-fg` sobre la
     * superficie de la página da 4,17:1 en el tema oscuro —bajo el 4,5:1 de WCAG 2.2 AA
     * 1.4.3 para texto normal, y el mensaje son 12px—. Sobre `--muni-danger-bg` sube a
     * 4,75:1 en oscuro y 5,48:1 en claro. Por eso el error lleva fondo y borde propios:
     * así el contraste no depende de dónde lo ponga el sistema anfitrión.
     */
    $css = cssZonaArchivos();

    expect((bool) preg_match('/\.muni-dz__error\s*\{([^}]*)\}/', $css, $m))->toBeTrue(
        'No existe la regla `.muni-dz__error`.'
    );

    expect(str_contains($m[1], 'background:var(--muni-danger-bg)'))->toBeTrue(
        'El mensaje de error no tiene fondo propio: sobre la superficie del anfitrión el '.
        'rojo del tema oscuro se queda en 4,17:1 y no llega al 4,5:1 de WCAG 2.2 AA.'
    );

    $bloques = [
        'muni-ui · claro' => bloqueTrasAncla(cssMuniUi(), 'Valores LIGHT (default)'),
        'muni-ui · oscuro' => bloqueTrasAncla(cssMuniUi(), 'Regla 2: activadores EXPLÍCITOS de dark'),
        'filament · claro' => bloqueTrasAncla(cssMuniUiFilament(), ':root{'),
    ];

    foreach ($bloques as $nombre => $bloque) {
        $fg = tokenHex($bloque, 'danger-fg');
        $bg = tokenHex($bloque, 'danger-bg');

        // El bloque oscuro de Filament define `--muni-danger-bg` con `color-mix()`, que no
        // se puede resolver leyendo el archivo. Ese par se midió en el navegador: 5,23:1.
        if ($fg === null || $bg === null) {
            continue;
        }

        expect(ratioContraste($fg, $bg))->toBeGreaterThanOrEqual(4.5, sprintf(
            '%s: el error (%s) sobre su fondo (%s) da %.2f:1, bajo el 4,5:1 de WCAG 2.2 AA 1.4.3',
            $nombre, $fg, $bg, ratioContraste($fg, $bg)
        ));
    }
});

it('las props públicas no cambiaron de nombre', function () {
    // El componente ya está desplegado en los paneles: el arreglo es aditivo.
    $html = Blade::render(
        '<x-muni::file-dropzone name="informe" accept="application/pdf" label="Informe médico" hint="Solo PDF" multiple />'
    );

    expect(str_contains($html, 'name="informe[]"'))->toBeTrue('La prop `name` (con `multiple`) dejó de funcionar.');
    expect(str_contains($html, 'accept="application/pdf"'))->toBeTrue('La prop `accept` dejó de funcionar.');
    expect(str_contains($html, 'Informe médico'))->toBeTrue('La prop `label` dejó de funcionar.');
    expect(str_contains($html, 'Solo PDF'))->toBeTrue('La prop `hint` dejó de funcionar.');
    expect((bool) preg_match('/<input\b[^>]*\bmultiple\b/', $html))->toBeTrue('La prop `multiple` dejó de funcionar.');
    expect((bool) preg_match('/<input\b[^>]*type="file"/', $html))->toBeTrue(
        'El control dejó de ser un `<input type="file">` real: sin él se pierden el '.
        'arrastrar y soltar nativo y la activación por teclado.'
    );
});
