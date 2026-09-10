<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA TABLA ANTES/DESPUÉS POR CAMPO
|--------------------------------------------------------------------------
|
| `<x-muni::diff-campos>` es el punto (3) de la corrección exigida al candidato
| `bitacora-auditoria` en docs/GAP-ANALYSIS.md: la pantalla completa se va a
| `laravel-arcop-panel` y de este paquete sale SOLO la pieza reutilizable, la
| que se usa igual en la bitácora de accesos, en el historial de una licencia y
| en la resolución de una rectificación ARCOP.
|
| Lo que esta prueba vigila, y por qué:
|
| · **El estado no puede vivir en el color.** Verde/rojo es lo primero que se
|   pierde con daltonismo, con alto contraste forzado y en papel (los navegadores
|   no imprimen fondos). El portador real es la palabra —«Agregado»,
|   «Modificado», «Suprimido»— más el marcado semántico <ins>/<del>, que además
|   trae subrayado y tachado del navegador. WCAG 2.2 AA 1.4.1.
| · **La tabla tiene que ser tabla de verdad:** <caption>, <th scope="col"> y el
|   nombre del campo como <th scope="row">. Sin scope, una celda leída suelta no
|   dice a qué campo pertenece (1.3.1), y una bitácora es exactamente eso: celdas
|   sueltas leídas fuera de orden.
| · **Ley 21.719, minimización.** El componente recibe strings YA redactados por
|   el host. Nada de modelos Eloquent ni de arrays crudos de los que se saquen
|   todas las claves: la pantalla que más PII concentra no puede volcar el
|   registro entero al DOM ni a la caché del navegador. Un ítem que no sea un
|   array se descarta (fail-closed), y eso se prueba.
| · **Escapado.** Un valor «antes» viene de datos de terceros; si sale sin
|   escapar, la bitácora se convierte en el vector de XSS almacenado del sistema.
|
| Todo va con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo argumento
| de `toContain()` es otra aguja, no un mensaje, y la aserción se desactiva sin
| avisar.
*/

/** El HTML servido del componente. */
function diffCamposHtml(string $atributos = '', array $datos = []): string
{
    return Blade::render("<x-muni::diff-campos {$atributos} />", $datos);
}

/** El fuente crudo del componente, o cadena vacía si todavía no existe. */
function diffCamposFuente(): string
{
    $ruta = __DIR__.'/../resources/views/components/diff-campos.blade.php';

    return file_exists($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * El fuente sin comentarios: la prosa explicativa nombra a propósito lo que el
 * componente NO hace («uniqid», «wire:show», «Eloquent»), y buscarlo sobre el
 * fuente entero daría falsos positivos contra el propio comentario.
 */
function diffCamposFuenteSinComentarios(): string
{
    $fuente = diffCamposFuente();
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El CSS que el componente lleva consigo, sin comentarios. */
function diffCamposCss(): string
{
    preg_match_all('#<style>(.*?)</style>#s', diffCamposFuente(), $bloques);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $bloques[1] ?? []));
}

/** La primera fila `<tr>` del HTML que contiene `$aguja`. */
function diffCamposFila(string $html, string $aguja): string
{
    preg_match_all('#<tr\b.*?</tr>#s', $html, $filas);

    foreach ($filas[0] as $fila) {
        if (str_contains($fila, $aguja)) {
            return $fila;
        }
    }

    return '';
}

/** Tres cambios, uno de cada estado derivado. */
function diffCamposTresEstados(): array
{
    return [
        ['field' => 'telefono', 'label' => 'Teléfono', 'after' => '+56 9 1111 1111'],
        ['field' => 'domicilio', 'label' => 'Domicilio', 'before' => 'Calle Uno 100', 'after' => 'Calle Dos 200'],
        ['field' => 'correo', 'label' => 'Correo', 'before' => 'ana@ejemplo.cl'],
    ];
}

it('deriva agregado, modificado y suprimido cuando el host no manda el estado', function () {
    $html = diffCamposHtml(':changes="$cambios"', ['cambios' => diffCamposTresEstados()]);

    $agregado = diffCamposFila($html, 'Teléfono');
    $modificado = diffCamposFila($html, 'Domicilio');
    $suprimido = diffCamposFila($html, 'Correo');

    expect($agregado !== '')->toBeTrue('No se emitió la fila del campo agregado.');
    expect(str_contains($agregado, 'Agregado'))->toBeTrue(
        'Sin «before» y con «after» el estado es AGREGADO, y tiene que decirlo en texto.'
    );
    expect(str_contains($agregado, '<ins'))->toBeTrue('El valor nuevo va en un <ins> real.');
    expect(str_contains($agregado, '<del'))->toBeFalse('Un campo agregado no tiene valor suprimido.');

    expect(str_contains($modificado, 'Modificado'))->toBeTrue(
        'Con «before» y «after» el estado es MODIFICADO, y tiene que decirlo en texto.'
    );
    expect(str_contains($modificado, '<del') && str_contains($modificado, '<ins'))->toBeTrue(
        'Un campo modificado lleva <del> con el valor anterior e <ins> con el nuevo.'
    );

    expect(str_contains($suprimido, 'Suprimido'))->toBeTrue(
        'Con «before» y sin «after» el estado es SUPRIMIDO, y tiene que decirlo en texto.'
    );
    expect(str_contains($suprimido, '<del'))->toBeTrue('El valor borrado va en un <del> real.');
    expect(str_contains($suprimido, '<ins'))->toBeFalse('Un campo suprimido no tiene valor nuevo.');
});

it('el estado que manda el host gana sobre el derivado', function () {
    $html = diffCamposHtml(':changes="$cambios"', ['cambios' => [
        ['field' => 'diagnostico', 'label' => 'Diagnóstico', 'before' => 'A', 'after' => 'B', 'state' => 'suprimido'],
    ]]);

    $fila = diffCamposFila($html, 'Diagnóstico');

    expect(str_contains($fila, 'Suprimido'))->toBeTrue(
        'El host sabe cosas que el componente no puede derivar de dos strings; su «state» manda.'
    );
    expect(str_contains($fila, 'Modificado'))->toBeFalse('No puede quedar el estado derivado además del declarado.');
});

it('cada estado se distingue por TEXTO aunque se borre todo el color', function () {
    $html = diffCamposHtml(':changes="$cambios"', ['cambios' => diffCamposTresEstados()]);

    // Sin `class` ni `style` no queda ni un color en pie: lo que sobreviva es el
    // portador no cromático, que es lo que exige 1.4.1.
    $sinColor = (string) preg_replace('/\s(class|style)="[^"]*"/i', '', $html);

    foreach (['Agregado', 'Modificado', 'Suprimido'] as $palabra) {
        expect(str_contains($sinColor, $palabra))->toBeTrue(
            "«{$palabra}» desapareció al quitar las clases: el estado dependía del color."
        );
    }

    expect(str_contains($sinColor, '<ins') && str_contains($sinColor, '<del'))->toBeTrue(
        'El marcado semántico <ins>/<del> tiene que estar en el HTML, no en una clase de color.'
    );

    $css = diffCamposCss();

    expect(str_contains($css, 'line-through'))->toBeTrue(
        'El tachado del <del> se refuerza en el CSS: un reset del sistema anfitrión puede quitarlo '.
        'y dejar el rojo como única señal.'
    );
    expect(str_contains($css, 'underline'))->toBeTrue(
        'El subrayado del <ins> se refuerza en el CSS por el mismo motivo.'
    );
});

it('es una tabla real con caption y scope en las dos direcciones', function () {
    $html = diffCamposHtml('caption="Cambios de la ficha 1042" :changes="$cambios"', [
        'cambios' => diffCamposTresEstados(),
    ]);

    expect(str_contains($html, '<caption'))->toBeTrue('La tabla necesita <caption>: es su nombre accesible.');
    expect(str_contains($html, 'Cambios de la ficha 1042'))->toBeTrue('La prop «caption» tiene que salir en el <caption>.');
    expect(str_contains($html, 'scope="col"'))->toBeTrue('Las cabeceras de columna llevan scope="col" (WCAG 1.3.1).');
    expect(str_contains($html, 'scope="row"'))->toBeTrue(
        'El nombre del campo es la cabecera de su fila: <th scope="row">, no <td>.'
    );

    // El nombre del campo va DENTRO del <th scope="row">, no suelto en una celda.
    expect((bool) preg_match('#<th[^>]*scope="row"[^>]*>\s*Domicilio\s*</th>#', $html))->toBeTrue(
        'El campo «Domicilio» tiene que ser la cabecera de su propia fila.'
    );
});

it('sin cambios muestra su texto en español, y el host puede cambiarlo', function () {
    $vacio = diffCamposHtml();

    expect(trim($vacio) !== '')->toBeTrue('Una lista vacía no puede renderizar la nada.');
    expect((bool) preg_match('/[Ss]in cambios/', $vacio))->toBeTrue(
        'La lista vacía necesita un texto por defecto en español; una tabla en blanco no es un estado.'
    );
    expect(str_contains($vacio, '<caption'))->toBeTrue('Vacía o no, la tabla conserva su nombre accesible.');

    $propio = diffCamposHtml('empty="Esta ficha no se ha tocado desde el ingreso."');

    expect(str_contains($propio, 'Esta ficha no se ha tocado desde el ingreso.'))->toBeTrue(
        'El host tiene que poder decir qué significa «vacío» en su trámite.'
    );
});

it('un valor que no cambió no revienta y se marca como sin cambios', function () {
    $html = diffCamposHtml(':changes="$cambios"', ['cambios' => [
        ['field' => 'rut', 'label' => 'RUT', 'before' => '12.345.678-9', 'after' => '12.345.678-9'],
    ]]);

    $fila = diffCamposFila($html, 'RUT');

    expect($fila !== '')->toBeTrue('Un valor sin cambio no debería llegar, pero si llega no se descarta en silencio.');
    expect(str_contains($fila, '<ins') || str_contains($fila, '<del'))->toBeFalse(
        'Nada cambió: marcarlo como inserción o supresión sería mentirle al lector de pantalla.'
    );
    expect((bool) preg_match('/[Ss]in cambios/', $fila))->toBeTrue('El estado «sin cambios» también se dice en texto.');
});

it('escapa el valor: la bitácora no es un vector de XSS almacenado', function () {
    $html = diffCamposHtml(':changes="$cambios"', ['cambios' => [
        [
            'field' => 'observacion',
            'label' => '<script>alert(1)</script>',
            'before' => '<script>alert("antes")</script>',
            'after' => '<img src=x onerror=alert(2)>',
        ],
    ]]);

    expect(str_contains($html, '<script>'))->toBeFalse(
        'Un <script> del dato salió sin escapar: XSS almacenado en la pantalla que más PII concentra.'
    );
    // Ojo: buscar «onerror=alert» a secas NO prueba nada, porque el texto escapado
    // lo sigue conteniendo tal cual. Lo que hay que comprobar es que no quede una
    // ETIQUETA abierta: sin `<`, el atributo es prosa.
    expect((bool) preg_match('/<img\b/i', $html))->toBeFalse('La etiqueta del dato salió sin escapar.');
    expect(str_contains($html, '&lt;script&gt;'))->toBeTrue('El valor tiene que verse como texto, escapado.');
    expect(str_contains($html, '&lt;img src=x onerror=alert(2)&gt;'))->toBeTrue(
        'El vector completo tiene que verse como texto plano en la celda.'
    );
});

it('un ítem que no es un array se descarta: fail-closed contra el volcado de un modelo', function () {
    $modelo = (object) ['field' => 'rut', 'before' => '12.345.678-9', 'domicilio_secreto' => 'Calle Falsa 123'];

    $html = diffCamposHtml(':changes="$cambios"', ['cambios' => [
        $modelo,
        ['field' => 'estado', 'label' => 'Estado', 'before' => 'Pendiente', 'after' => 'Aprobada'],
    ]]);

    expect(str_contains($html, 'Calle Falsa 123'))->toBeFalse(
        'Un objeto se convirtió en fila y volcó atributos que nadie pidió (Ley 21.719, minimización).'
    );
    expect(str_contains($html, 'Aprobada'))->toBeTrue('Descartar el ítem malo no puede tumbar los buenos.');
});

it('la regla de minimización queda escrita en el propio componente', function () {
    $fuente = diffCamposFuente();

    expect($fuente !== '')->toBeTrue('El componente no existe.');
    expect(str_contains($fuente, '21.719'))->toBeTrue(
        'El comentario del componente tiene que decir por qué recibe strings ya redactados: Ley 21.719.'
    );
    expect(str_contains($fuente, 'Eloquent'))->toBeTrue(
        'Y tiene que decir explícitamente que NO acepta un modelo Eloquent ni el registro completo.'
    );
});

it('el mono tabular es opt-in por ítem, como pide la firma de la casa', function () {
    $conMono = diffCamposHtml(':changes="$cambios"', ['cambios' => [
        ['field' => 'monto', 'label' => 'Monto', 'before' => '520.000', 'after' => '80.000', 'mono' => true],
    ]]);

    $sinMono = diffCamposHtml(':changes="$cambios"', ['cambios' => [
        ['field' => 'glosa', 'label' => 'Glosa', 'before' => 'Uno', 'after' => 'Dos'],
    ]]);

    expect(str_contains(diffCamposFila($conMono, 'Monto'), 'muni-num'))->toBeTrue(
        'Con «mono» el valor va en mono tabular: una columna de montos que no alinea unidades no se lee.'
    );
    expect(str_contains(diffCamposFila($sinMono, 'Glosa'), 'muni-num'))->toBeFalse(
        'Sin pedirlo, un texto libre no debe salir en mono tabular.'
    );
});

it('el CSS viaja con el componente y no escribe un solo color literal', function () {
    $css = diffCamposCss();

    expect($css !== '')->toBeTrue(
        'El componente tiene que traer su bloque de estilos consigo: dentro de un panel Filament '.
        'muni-ui.css NO se carga (DESIGN §7).'
    );
    expect(str_contains(diffCamposFuente(), '@once'))->toBeTrue('El bloque de estilos va envuelto para emitirse una sola vez.');

    expect((bool) preg_match('/#[0-9a-fA-F]{3,8}\b/', $css))->toBeFalse(
        'Hay un color literal en el CSS del componente. La identidad de cada municipio se cambia '.
        'redefiniendo tokens: un hex escrito acá no lo puede cambiar nadie (DESIGN §1).'
    );
    expect((bool) preg_match('/\b(rgba?|hsla?)\s*\(/i', $css))->toBeFalse('Los colores salen de tokens --muni-*, no de rgb()/hsl().');

    expect(str_contains($css, 'overflow-wrap'))->toBeTrue(
        'Un valor largo —una glosa, una URL, un JSON— no puede romper el layout de la tabla.'
    );

    // Lo que el componente usa lo declara él mismo, incluidos `.muni-num` y el
    // ocultado visual: los de `data-table` viven en OTRO bloque que puede no
    // haberse emitido en esta página.
    expect(str_contains($css, '.muni-num'))->toBeTrue('El mono tabular se declara acá, no se hereda de data-table.');
});

it('no usa Alpine, ni uniqid, ni directivas exclusivas de un major de Livewire', function () {
    $fuente = diffCamposFuenteSinComentarios();

    foreach (['x-data', 'x-show', 'uniqid(', '@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibido) {
        expect(str_contains($fuente, $prohibido))->toBeFalse(
            "El componente usa «{$prohibido}». Es contenido de solo lectura y debe funcionar igual en ".
            'Livewire 3 (personas-graneros) y en Livewire 4.'
        );
    }
});
