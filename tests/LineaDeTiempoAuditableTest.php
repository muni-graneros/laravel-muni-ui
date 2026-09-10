<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL HITO AUDITABLE DE LA LÍNEA DE TIEMPO
|--------------------------------------------------------------------------
|
| `<x-muni::timeline>` servía para pintar un historial; no para sostener una
| bitácora. Un hito de bitácora tiene que responder cuatro preguntas —qué pasó,
| en qué estado quedó, quién lo hizo y cuándo— y hoy el estado viajaba SOLO en
| el color del punto. Eso es 1.4.1 «uso del color» incumplido: quien no
| distingue el verde del rojo no tiene ninguna otra pista.
|
| Por eso el orden de estas pruebas es el orden de valor, no el de esfuerzo:
| primero la etiqueta textual del tono, después el actor, el hito vigente, la
| fecha legible por máquina y el detalle colapsable.
|
| Dos cosas que este archivo vigila y no son obvias:
|
| - `datetime` NO es accesibilidad. Ningún lector de pantalla lo anuncia. Vale
|   para trazabilidad y para ordenar una exportación, y por eso lo único que se
|   le exige es no tocar el texto visible que el sistema anfitrión ya formateó
|   en es-CL.
| - El detalle del cambio recibe un STRING ya redactado por el anfitrión, nunca
|   el registro ni el modelo. Ley 21.719, minimización: el componente no puede
|   invitar a volcar diagnósticos ni psicotécnicos en pantalla.
|
| Todas las claves nuevas son opcionales dentro del array `$item`, así que hay
| una prueba de retrocompatibilidad al final: un ítem con solo `title` tiene que
| seguir renderizando, sin avisos de PHP y sin markup nuevo.
|
| Las aserciones con mensaje van como `expect(bool)->toBeTrue('...')`: en Pest
| el segundo argumento de `toContain()` es otra aguja, no un mensaje, y usarlo
| como mensaje desactiva la comprobación en silencio.
*/

/**
 * El MARKUP servido de la línea de tiempo para un juego de ítems.
 *
 * Se le quita el bloque de estilos del componente a propósito: sus selectores
 * mencionan `aria-current="step"` y `muni-tl__tone`, así que contar apariciones
 * sobre el HTML completo mide el CSS y no lo que se emitió. Además ese bloque
 * se sirve una sola vez por proceso, y con eso el resultado dependería del
 * orden en que Pest carga las pruebas.
 */
function lineaDeTiempoHtml(array $items): string
{
    $html = Blade::render('<x-muni::timeline :items="$items" />', ['items' => $items]);

    return (string) preg_replace('#<style\b.*?</style>#s', '', $html);
}

/** El fuente del componente sin comentarios: si no, se matchea la prosa. */
function fuenteLineaDeTiempoSinComentarios(): string
{
    $fuente = (string) file_get_contents(
        __DIR__.'/../resources/views/components/timeline.blade.php'
    );

    $fuente = preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);
    $fuente = preg_replace('#/\*.*?\*/#s', '', $fuente);
    $fuente = preg_replace('#^\s*//.*$#m', '', $fuente);

    return (string) $fuente;
}

it('dice el estado en texto y no solo con el color del punto', function () {
    $html = lineaDeTiempoHtml([
        ['title' => 'Solicitud rechazada', 'tone' => 'danger'],
        ['title' => 'Antecedentes conformes', 'tone' => 'ok'],
        ['title' => 'Observación al expediente', 'tone' => 'warn'],
        ['title' => 'Ingreso por oficina de partes', 'tone' => 'info'],
        ['title' => 'En tramitación', 'tone' => 'accent'],
        ['title' => 'Sin efecto', 'tone' => 'muted'],
    ]);

    // Seis tonos, seis etiquetas: el color deja de ser el único portador de la
    // información de estado.
    expect(substr_count($html, 'class="muni-tl__tone"'))->toBe(6,
        'Falta la etiqueta textual de algún tono.'
    );

    // Y son distintas entre sí: una etiqueta genérica repetida seis veces no
    // comunica nada, y además tienen que ser texto de verdad, visible, no un
    // atributo que solo lea una máquina.
    preg_match_all('/class="muni-tl__tone"[^>]*>([^<]+)</', $html, $m);
    $etiquetas = array_map('trim', $m[1]);

    expect(count(array_unique($etiquetas)))->toBe(6,
        'Los seis tonos deben tener seis etiquetas distintas en español.'
    );

    foreach ($etiquetas as $etiqueta) {
        expect(str_contains(strip_tags($html), $etiqueta))->toBeTrue(
            "La etiqueta «{$etiqueta}» no es texto visible del documento."
        );
    }

    expect(str_contains(strip_tags($html), 'Rechazado'))->toBeTrue(
        'El tono `danger` no llega como texto.'
    );
});

it('deja al sistema anfitrión reescribir la etiqueta del tono por ítem', function () {
    $html = lineaDeTiempoHtml([
        ['title' => 'Licencia entregada', 'tone' => 'ok', 'toneLabel' => 'Entregada'],
    ]);

    expect(str_contains($html, 'Entregada'))->toBeTrue(
        '`toneLabel` debe reemplazar la etiqueta por defecto del tono.'
    );
});

it('renderiza el actor de la actuación', function () {
    $html = lineaDeTiempoHtml([
        ['title' => 'Resolución firmada', 'actor' => 'Marta Riquelme', 'tone' => 'ok'],
    ]);

    expect(str_contains($html, 'Marta Riquelme'))->toBeTrue(
        'El `actor` no aparece en el HTML: sin quién, no hay bitácora.'
    );
    expect(str_contains($html, 'muni-tl__actor'))->toBeTrue(
        'El actor necesita su propio gancho de estilo.'
    );
});

it('marca el hito vigente con aria-current="step" y solo uno', function () {
    $html = lineaDeTiempoHtml([
        ['title' => 'Ingresada'],
        ['title' => 'En revisión', 'current' => true],
        ['title' => 'Resuelta'],
    ]);

    expect(substr_count($html, 'aria-current="step"'))->toBe(1,
        'El hito vigente se marca con aria-current="step" exactamente una vez.'
    );

    // Aunque el anfitrión marque dos por error, el componente no puede emitir
    // dos «pasos actuales»: sería un árbol de accesibilidad mentiroso.
    $dosVigentes = lineaDeTiempoHtml([
        ['title' => 'Ingresada', 'current' => true],
        ['title' => 'En revisión', 'current' => true],
    ]);

    expect(substr_count($dosVigentes, 'aria-current="step"'))->toBe(1,
        'Con dos ítems marcados, solo el primero puede quedar como vigente.'
    );
});

it('emite datetime ISO sin tocar el texto legible que ya formateó el anfitrión', function () {
    $html = lineaDeTiempoHtml([
        [
            'title' => 'Ingreso por oficina de partes',
            'time' => '14 ago 2026, 09:12',
            'datetime' => '2026-08-14T09:12:00-04:00',
        ],
    ]);

    expect(preg_match(
        '/<time[^>]*datetime="2026-08-14T09:12:00-04:00"[^>]*>\s*14 ago 2026, 09:12\s*<\/time>/',
        $html
    ))->toBe(1, 'El <time> debe llevar el ISO en el atributo y el texto es-CL como contenido.');

    expect(str_contains(strip_tags($html), '2026-08-14T09:12:00-04:00'))->toBeFalse(
        'El ISO es para la máquina: no puede aparecer como texto visible.'
    );
});

it('sin datetime deja el <time> tal como estaba', function () {
    $html = lineaDeTiempoHtml([
        ['title' => 'Ingreso', 'time' => '14 ago 2026, 09:12'],
    ]);

    expect(str_contains($html, 'datetime='))->toBeFalse(
        'Sin la clave `datetime` no se inventa un atributo vacío.'
    );
    expect(str_contains($html, '14 ago 2026, 09:12'))->toBeTrue(
        'El texto de la hora sigue saliendo igual que siempre.'
    );
});

it('pone el detalle del cambio en un <details> nativo cerrado por defecto', function () {
    $html = lineaDeTiempoHtml([
        [
            'title' => 'Cambio de estado',
            'detail' => 'Estado: «En revisión» → «Aprobada»',
        ],
    ]);

    expect(preg_match('/<details\b([^>]*)>/', $html, $m))->toBe(1,
        'El detalle del cambio va en un <details> nativo, sin JS.'
    );
    expect(str_contains($m[1], 'open'))->toBeFalse(
        'El detalle arranca colapsado: un diff abierto expone datos que nadie pidió ver.'
    );
    expect(str_contains($html, '<summary'))->toBeTrue(
        'Un <details> sin <summary> no se puede abrir con teclado.'
    );
    expect(str_contains($html, 'En revisión'))->toBeTrue(
        'El detalle redactado por el anfitrión debe renderizarse.'
    );
});

it('no acepta el registro completo: el detalle es un string ya redactado', function () {
    $fuente = fuenteLineaDeTiempoSinComentarios();

    foreach (['->toArray()', '->getChanges()', '->getAttributes()', 'print_r(', 'var_export('] as $volcado) {
        expect(str_contains($fuente, $volcado))->toBeFalse(
            "El componente no puede volcar un modelo ({$volcado}): Ley 21.719, minimización."
        );
    }
});

it('el foco del summary es un outline de 3px y no una sombra', function () {
    $fuente = fuenteLineaDeTiempoSinComentarios();

    expect(preg_match('/summary:focus-visible\s*\{[^}]*outline:\s*3px solid var\(--muni-focus/', $fuente))->toBe(1,
        'Dentro de Filament la cadena de sombras pisa el foco: el indicador es un outline.'
    );
});

it('no escribe un solo color literal', function () {
    // La única excepción es el tercer respaldo del outline de foco, que el
    // paquete exige escrito así en todos los componentes: si el CSS publicado
    // en el sistema anfitrión está viejo y el token no existe, sin respaldo la
    // declaración queda inválida y el foco desaparece.
    $fuente = str_replace(
        'var(--muni-focus, var(--muni-accent, #767676))',
        'var(--muni-focus)',
        fuenteLineaDeTiempoSinComentarios()
    );

    expect(preg_match('/#[0-9a-fA-F]{3,8}\b/', $fuente))->toBe(0,
        'Los colores salen de tokens --muni-*, nunca literales: el adoptante los redefine.'
    );
    expect(preg_match('/\b(rgb|hsl)a?\(/', $fuente))->toBe(0,
        'Los colores salen de tokens --muni-*, nunca literales.'
    );
});

it('sigue renderizando un ítem que solo trae title, sin avisos ni markup nuevo', function () {
    set_error_handler(function (int $nivel, string $mensaje, string $archivo = '', int $linea = 0) {
        throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
    });

    try {
        $html = lineaDeTiempoHtml([['title' => 'Ingreso por oficina de partes']]);
    } finally {
        restore_error_handler();
    }

    expect(str_contains($html, 'Ingreso por oficina de partes'))->toBeTrue(
        'La llamada de siempre tiene que seguir funcionando.'
    );
    expect(str_contains($html, 'muni-tl__dot'))->toBeTrue('El punto sigue estando.');

    // Retrocompatibilidad visual: sin tono declarado no hay estado que
    // comunicar, así que tampoco aparece una etiqueta de tono que antes no
    // estaba. Igual con el resto de las claves nuevas.
    expect(str_contains($html, 'muni-tl__tone'))->toBeFalse(
        'Un ítem sin `tone` no declara estado: no se le inventa una etiqueta.'
    );
    expect(str_contains($html, 'aria-current'))->toBeFalse('Sin `current` no hay hito vigente.');
    expect(str_contains($html, '<details'))->toBeFalse('Sin `detail` no hay bloque colapsable.');
    expect(str_contains($html, 'muni-tl__actor'))->toBeFalse('Sin `actor` no hay línea de actor.');
});
