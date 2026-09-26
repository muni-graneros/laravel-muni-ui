<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA REGIÓN VIVA Y DEL RESUMEN DE ERRORES
|--------------------------------------------------------------------------
|
| Dos piezas que cierran WCAG 2.2 AA 4.1.3 (Mensajes de estado) y 3.3.1 desde
| lados opuestos:
|
| `<x-muni::announcer>` es la región viva COMPARTIDA de la página: se coloca una
| vez en el armazón y cualquier componente —o cualquier código Livewire— anuncia
| por ella sin mover el foco. Se escribe mirando los dos defectos ya medidos de
| `toast-host` (`docs/INVENTORY.md`): (a) una región viva anidada dentro de otra
| región viva duplica el anuncio en algunos lectores, y (b) un mensaje de tono
| `danger` anunciado `polite` puede quedar en cola detrás de otro discurso. De
| ahí que acá haya DOS regiones fijas —una cortés y una asertiva— y no una sola
| que conmuta de prioridad: cambiar `aria-live` sobre la marcha es frágil y
| varios lectores no lo respetan.
|
| `<x-muni::error-summary>` es lo que aparece tras un envío fallido: cuántos
| errores hay EN TEXTO, la lista, y cada línea enlazada al id de su campo. Recibe
| el foco una sola vez para que quien envía un formulario de treinta campos no
| tenga que buscar el borde rojo a ojo.
|
| Se prueba sobre el HTML renderizado y sobre el bloque `<style>` de cada
| componente, con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra cadena a buscar, no un mensaje, y la
| aserción se desactiva sin avisar.
*/

/** El `announcer.blade.php` crudo. */
function fuenteAnunciador(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/announcer.blade.php');
}

/** El `error-summary.blade.php` crudo. */
function fuenteResumenErrores(): string
{
    return (string) file_get_contents(__DIR__.'/../resources/views/components/error-summary.blade.php');
}

/** El contenido de los bloques `<style>` de un componente, sin comentarios. */
function cssDelComponente(string $fuente): string
{
    preg_match_all('#<style>(.*?)</style>#s', $fuente, $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/**
 * Los valores de todos los atributos de Alpine del HTML dado, ya decodificados
 * como los ve el navegador (`x-…`, `@…`, `:…`).
 *
 * @return array<int, string>
 */
function expresionesAlpineDeAnuncio(string $html): array
{
    preg_match_all('/\s(?:x-[a-z0-9.:-]+|@[a-z0-9.-]+|:[a-z0-9-]+)="([^"]*)"/i', $html, $m);

    return array_map(fn (string $v) => html_entity_decode($v, ENT_QUOTES | ENT_HTML5), $m[1]);
}

/**
 * El contenido de la región viva de la prioridad pedida, tal como sale del
 * servidor.
 */
function contenidoRegionViva(string $html, string $prioridad): ?string
{
    $ok = preg_match(
        '/<([a-z]+)\b[^>]*\baria-live="'.$prioridad.'"[^>]*>(.*?)<\/\1>/s',
        $html,
        $m
    );

    return $ok ? $m[2] : null;
}

/** La etiqueta de apertura de la región viva de la prioridad pedida. */
function aperturaRegionViva(string $html, string $prioridad): ?string
{
    $ok = preg_match('/<[a-z]+\b[^>]*\baria-live="'.$prioridad.'"[^>]*>/', $html, $m);

    return $ok ? $m[0] : null;
}

// ---------------------------------------------------------------------------
// announcer
// ---------------------------------------------------------------------------

it('el anunciador emite dos regiones fijas, una cortés y una asertiva', function () {
    $html = Blade::render('<x-muni::announcer />');

    expect(aperturaRegionViva($html, 'polite'))->not->toBeNull(
        'No hay región `aria-live="polite"`: no hay dónde anunciar un cambio sin interrumpir.'
    );

    expect(aperturaRegionViva($html, 'assertive'))->not->toBeNull(
        'No hay región `aria-live="assertive"` fija. Con una sola región que conmuta de '.
        'prioridad, varios lectores no respetan el cambio y un error urgente queda en cola '.
        'detrás de otro discurso: es el defecto ya medido de `toast-host`.'
    );

    // `aria-atomic="true"`: el mensaje se lee entero cada vez, no solo el trozo
    // que cambió respecto del anuncio anterior.
    foreach (['polite', 'assertive'] as $prioridad) {
        expect(str_contains((string) aperturaRegionViva($html, $prioridad), 'aria-atomic="true"'))->toBeTrue(
            "La región `$prioridad` no declara `aria-atomic=\"true\"`: el lector puede leer solo ".
            'la diferencia con el mensaje anterior y decir una frase a medias.'
        );
    }
});

it('las regiones vivas existen en el HTML inicial y nacen vacías', function () {
    // Una región viva insertada JUNTO con su mensaje no se anuncia: el lector
    // tiene que estar observando el nodo antes de que el texto llegue.
    $html = Blade::render('<x-muni::announcer />');

    foreach (['polite', 'assertive'] as $prioridad) {
        $contenido = contenidoRegionViva($html, $prioridad);

        expect($contenido)->not->toBeNull("La región `$prioridad` no está en el HTML inicial.");
        expect(trim((string) $contenido))->toBe('', sprintf(
            'La región `%s` nace con texto dentro (%s): un mensaje presente en el primer '.
            'pintado no se anuncia, y encima queda ahí como verdad vieja.',
            $prioridad, trim((string) $contenido)
        ));
    }

    // Ni siquiera cuando el anfitrión pasa un mensaje por Blade: el texto tiene
    // que entrar DESPUÉS, o no se anuncia.
    $conMensaje = Blade::render('<x-muni::announcer message="Se guardaron los cambios" />');

    foreach (['polite', 'assertive'] as $prioridad) {
        expect(trim((string) contenidoRegionViva($conMensaje, $prioridad)))->toBe('', sprintf(
            'Con `message` la región `%s` sale del servidor ya escrita: el lector no la '.
            'estaba observando y el anuncio se pierde.',
            $prioridad
        ));
    }
});

it('el anuncio desde Blade llega a la región sin que el anfitrión escriba JS', function () {
    $html = Blade::render('<x-muni::announcer message="Se guardaron los cambios" />');

    expect(str_contains($html, 'Se guardaron los cambios'))->toBeTrue(
        'La prop `message` no aparece por ninguna parte: no hay forma de anunciar desde '.
        'Blade y el componente obliga a escribir JS en cada sistema anfitrión.'
    );
});

it('el anunciador escucha un evento de window para que cualquiera pueda anunciar', function () {
    $html = Blade::render('<x-muni::announcer />');

    expect((bool) preg_match('/@muni-announce\.window="/', $html))->toBeTrue(
        'El anunciador no escucha `muni-announce` en `window`: ningún otro componente ni '.
        'código Livewire puede anunciar por él.'
    );
});

it('la región viva jamás es enfocable ni duplica su rol', function () {
    $html = Blade::render('<x-muni::announcer />');

    foreach (['polite', 'assertive'] as $prioridad) {
        $apertura = (string) aperturaRegionViva($html, $prioridad);

        expect(str_contains($apertura, 'tabindex'))->toBeFalse(
            "La región `$prioridad` lleva `tabindex`: una región viva no debe recibir el foco ".
            'jamás, el usuario de teclado se encontraría con una parada vacía.'
        );

        // `role="status"` YA implica `aria-live="polite"` y `role="alert"` implica
        // `assertive`. Declarar los dos en el mismo nodo es la redundancia que
        // `docs/INVENTORY.md` marca en `toast-host`.
        expect((bool) preg_match('/\brole="/', $apertura))->toBeFalse(
            "La región `$prioridad` lleva `role` Y `aria-live` en el mismo nodo: el rol ya ".
            'implica la prioridad y algunos lectores anuncian dos veces.'
        );
    }
});

it('el anunciador se oculta recortando, nunca con display:none', function () {
    // `display:none` y `visibility:hidden` sacan el nodo del árbol de
    // accesibilidad: la región deja de existir para el lector de pantalla.
    $css = cssDelComponente(fuenteAnunciador());

    expect((bool) preg_match('/display\s*:\s*none/i', $css))->toBeFalse(
        'El anunciador usa `display:none`, que lo saca del árbol de accesibilidad: la '.
        'región viva queda muda.'
    );
    expect((bool) preg_match('/visibility\s*:\s*hidden/i', $css))->toBeFalse(
        'El anunciador usa `visibility:hidden`, que lo saca del árbol de accesibilidad.'
    );
    expect((bool) preg_match('/clip-path\s*:\s*inset\(50%\)/', $css))->toBeTrue(
        'El anunciador no se oculta con la técnica de recorte (`clip-path:inset(50%)` más '.
        '1px), que es la única que lo deja invisible y presente a la vez.'
    );
});

it('el texto del anfitrión nunca descuadra una expresión de Alpine', function () {
    // «Se guardó tu' ficha» es el caso real: cualquier texto traducido o venido
    // de la base de datos puede traer un apóstrofo, y una expresión de Alpine
    // descuadrada tumba el Alpine de la página ENTERA.
    $html = Blade::render(
        '<x-muni::announcer :message="$m" />',
        ['m' => "Se guardó tu' ficha"]
    );

    $rotas = [];

    foreach (expresionesAlpineDeAnuncio($html) as $expr) {
        $limpia = str_replace("\\'", '', $expr);

        if (substr_count($limpia, "'") % 2 !== 0) {
            $rotas[] = $expr;
        }
    }

    expect($rotas)->toBe([], sprintf(
        'Estas expresiones de Alpine quedan con las comillas simples descuadradas: %s',
        implode(' | ', $rotas)
    ));
});

it('dos anunciadores en la misma página no anuncian el mensaje dos veces', function () {
    // `toast-host` tiene este defecto documentado: dos hosts en la página
    // reaccionan al mismo evento y muestran el toast duplicado.
    $html = Blade::render('<x-muni::announcer />');

    expect(str_contains($html, '__muniAnnouncer'))->toBeTrue(
        'Nada impide que dos anunciadores en la misma página lean el mismo evento y el '.
        'lector de pantalla oiga el mensaje dos veces.'
    );
});

// ---------------------------------------------------------------------------
// error-summary
// ---------------------------------------------------------------------------

it('sin errores el resumen no renderiza absolutamente nada', function () {
    // `role="alert"` es assertive: si se pinta en la primera carga interrumpe al
    // lector sin que haya pasado nada.
    expect(trim(Blade::render('<x-muni::error-summary />')))->toBe('',
        'El resumen renderiza algo sin errores: un `role="alert"` en el primer pintado '.
        'interrumpe al lector de pantalla sin motivo.'
    );

    expect(trim(Blade::render('<x-muni::error-summary :errors="$e" />', ['e' => []])))->toBe('',
        'El resumen renderiza algo con un arreglo de errores vacío.'
    );

    expect(trim(Blade::render('<x-muni::error-summary :errors="$e" />', ['e' => new MessageBag])))->toBe('',
        'El resumen renderiza algo con un MessageBag vacío.'
    );
});

it('el resumen dice cuántos errores hay en texto, no solo en color', function () {
    $bag = new MessageBag([
        'rut' => ['El RUT es obligatorio.'],
        'email' => ['El correo no es válido.'],
        'direccion' => ['La dirección es obligatoria.'],
    ]);

    $html = Blade::render('<x-muni::error-summary :errors="$e" />', ['e' => $bag]);

    expect((bool) preg_match('/\b3 errores\b/u', strip_tags($html)))->toBeTrue(
        'El resumen no dice «3 errores» en texto: el recuento queda solo en el borde rojo, '.
        'que no existe para quien no ve el color.'
    );

    // Singular: «1 errores» delata que el recuento es un contador pegado.
    $uno = Blade::render('<x-muni::error-summary :errors="$e" />', [
        'e' => new MessageBag(['rut' => ['El RUT es obligatorio.']]),
    ]);

    expect((bool) preg_match('/\b1 error\b/u', strip_tags($uno)))->toBeTrue(
        'Con un solo error el resumen no dice «1 error» en singular.'
    );
    expect((bool) preg_match('/\b1 errores\b/u', strip_tags($uno)))->toBeFalse(
        'Con un solo error el resumen dice «1 errores».'
    );
});

it('el resumen se anuncia y recibe el foco al aparecer', function () {
    $html = Blade::render('<x-muni::error-summary :errors="$e" />', [
        'e' => new MessageBag(['rut' => ['El RUT es obligatorio.']]),
    ]);

    expect((bool) preg_match('/\brole="alert"/', $html))->toBeTrue(
        'El resumen no es una región viva: tras el envío fallido el lector de pantalla no '.
        'se entera de nada (WCAG 2.2 AA 4.1.3).'
    );

    expect((bool) preg_match('/\btabindex="-1"/', $html))->toBeTrue(
        'El resumen no es enfocable con `tabindex="-1"`: no se le puede mover el foco y '.
        'quien envía un formulario de treinta campos tiene que buscar el error a ojo.'
    );

    // Con Livewire el nodo se inserta en un DOM ya cargado, donde `autofocus` no
    // dispara: hace falta un `x-init` con guarda de una sola vez por envío, o el
    // foco se le quita al usuario en cada re-render mientras escribe.
    expect((bool) preg_match('/x-init="[^"]*focus\(\)/', $html))->toBeTrue(
        'Nada mueve el foco al resumen: `autofocus` no dispara sobre un nodo insertado en '.
        'un DOM ya cargado, que es exactamente el caso de Livewire.'
    );
});

it('cada error es un enlace al id que arma input a partir del name', function () {
    /*
     * Contrato de id: `input.blade.php` y `select.blade.php` arman el id como
     * `'muni-'.$name` mientras la bolsa `$errors` usa la clave pelada, así que
     * `rut` tiene que enlazar a `#muni-rut`. El prefijo es una prop por si el
     * anfitrión pone sus propios ids.
     */
    $bag = new MessageBag([
        'rut' => ['El RUT es obligatorio.'],
        'email' => ['El correo no es válido.'],
    ]);

    $html = Blade::render('<x-muni::error-summary :errors="$e" />', ['e' => $bag]);

    expect((bool) preg_match('/<a\b[^>]*href="#muni-rut"[^>]*>\s*El RUT es obligatorio\./u', $html))->toBeTrue(
        'El error de `rut` no es un enlace a `#muni-rut`: hay que buscar el campo a mano.'
    );
    expect((bool) preg_match('/<a\b[^>]*href="#muni-email"/', $html))->toBeTrue(
        'El error de `email` no enlaza a `#muni-email`.'
    );

    $propio = Blade::render('<x-muni::error-summary :errors="$e" prefix="form_" />', ['e' => $bag]);

    expect((bool) preg_match('/<a\b[^>]*href="#form_rut"/', $propio))->toBeTrue(
        'La prop `prefix` no cambia el prefijo del id: el resumen solo sirve con los ids '.
        'que arma este paquete.'
    );
});

it('el foco se resuelve con getElementById, jamás con querySelector', function () {
    // Un id con punto o corchete es un id VÁLIDO pero un selector CSS ROTO:
    // `#muni-direccion.calle` es «id direccion con clase calle» y no encuentra
    // nada. Se mira el HTML renderizado y no la fuente, que menciona
    // `querySelector` en un comentario justamente para prohibirlo.
    $html = Blade::render('<x-muni::error-summary :errors="$e" />', [
        'e' => new MessageBag(['rut' => ['El RUT es obligatorio.']]),
    ]);

    expect(str_contains($html, 'getElementById'))->toBeTrue(
        'El resumen no resuelve el destino con `document.getElementById`.'
    );
    expect(str_contains($html, 'querySelector'))->toBeFalse(
        'El resumen usa `querySelector`: un id con punto no se puede buscar con un '.
        'selector CSS y el enlace no haría nada.'
    );
});

it('una clave anidada se lista sin enlace mientras nadie declare su id', function () {
    /*
     * `name="direccion[calle]"` valida como `direccion.calle`, y el id que le pone
     * `input.blade.php` lleva un trozo de `sha1('direccion[calle]')` que NO se puede
     * reconstruir desde la clave de la bolsa. Un enlace a un id inventado es peor que
     * ninguno: promete llegar al campo, no llega, y el usuario de teclado se queda con
     * una parada muerta.
     */
    $bag = new MessageBag(['direccion.calle' => ['La calle es obligatoria.']]);

    $html = Blade::render('<x-muni::error-summary :errors="$e" />', ['e' => $bag]);

    expect((bool) preg_match('/<a\b/', $html))->toBeFalse(
        'El resumen inventa un enlace para `direccion.calle`: ese id no existe en la '.
        'página, así que el enlace no lleva a ninguna parte.'
    );
    expect(str_contains($html, 'La calle es obligatoria.'))->toBeTrue(
        'El error de la clave anidada desapareció del resumen: sin enlace se lista igual, '.
        'porque el mensaje sigue siendo información.'
    );
    expect((bool) preg_match('/\b1 error\b/u', strip_tags($html)))->toBeTrue(
        'El error sin enlace no entra en el recuento.'
    );

    // Declarado por el anfitrión, sí se enlaza.
    $conId = Blade::render(
        '<x-muni::error-summary :errors="$e" :ids="[\'direccion.calle\' => \'muni-direccion-calle-8f2a10\']" />',
        ['e' => $bag]
    );

    expect((bool) preg_match('/href="#muni-direccion-calle-8f2a10"/', $conId))->toBeTrue(
        'El mapa `ids` no permite declarar el id real de un campo anidado.'
    );
});

it('el resumen acepta un arreglo simple además de un MessageBag', function () {
    $html = Blade::render('<x-muni::error-summary :errors="$e" />', [
        'e' => ['rut' => 'El RUT es obligatorio.', 'email' => ['El correo no es válido.']],
    ]);

    expect((bool) preg_match('/\b2 errores\b/u', strip_tags($html)))->toBeTrue(
        'El resumen no cuenta bien un arreglo asociativo de errores.'
    );
    expect((bool) preg_match('/href="#muni-rut"/', $html))->toBeTrue(
        'Con un arreglo simple los errores no enlazan a su campo.'
    );
});

it('el resumen toma la bolsa compartida de Laravel cuando no se le pasa nada', function () {
    // `ShareErrorsFromSession` hace exactamente esto —`View::share('errors', …)`—
    // en cada request de la web. Si el componente no lo aprovecha, cada uno de
    // los nueve sistemas tiene que pasar la bolsa a mano en cada formulario.
    View::share('errors', new MessageBag(['rut' => ['El RUT es obligatorio.']]));

    $html = Blade::render('<x-muni::error-summary />');

    expect((bool) preg_match('/\b1 error\b/u', strip_tags($html)))->toBeTrue(
        'El resumen ignora la bolsa `$errors` compartida por Laravel y obliga a pasarla a mano.'
    );
});

it('el enlace del error muestra el foco con el outline del sistema', function () {
    $css = cssDelComponente(fuenteResumenErrores());

    expect((bool) preg_match(
        '/outline\s*:\s*3px\s+solid\s+var\(--muni-focus,\s*var\(--muni-accent,\s*#767676\)\)/',
        $css
    ))->toBeTrue(
        'El foco del resumen no dibuja el outline del sistema con su cadena de respaldo: '.
        'dentro de Filament la box-shadow se computa transparente y no queda nada visible.'
    );
});

it('ninguno de los dos escribe un color literal fuera del respaldo del foco', function () {
    foreach (['announcer' => fuenteAnunciador(), 'error-summary' => fuenteResumenErrores()] as $nombre => $fuente) {
        $literales = [];

        preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/', cssDelComponente($fuente), $m);

        foreach ($m[0] as $color) {
            if (strtolower($color) !== '#767676') {
                $literales[] = $color;
            }
        }

        expect($literales)->toBe([], sprintf(
            '`%s` escribe colores literales, que no tienen contraparte en modo oscuro: %s',
            $nombre, implode(', ', $literales)
        ));
    }
});

it('ninguno de los dos depende de una directiva exclusiva de un major de Livewire', function () {
    // El paquete se instala en Livewire 4 y en `personas-graneros`, que sigue en 3.
    $prohibidas = ['@island', 'wire:show', 'wire:sort', '#[Transition]'];

    foreach (['announcer' => fuenteAnunciador(), 'error-summary' => fuenteResumenErrores()] as $nombre => $fuente) {
        foreach ($prohibidas as $directiva) {
            expect(str_contains($fuente, $directiva))->toBeFalse(
                "`$nombre` usa `$directiva`, que no existe en los dos majors de Livewire que ".
                'consumen este paquete.'
            );
        }
    }
});

it('la lista de errores se lee sobre el fondo del propio resumen', function () {
    /*
     * El resumen NO reusa `alert`: `alert` pinta el cuerpo en `--muni-muted`, y la
     * lista de errores es contenido PRIMARIO. Sobre `--muni-danger-bg` medido desde
     * los tokens: el texto da 14,56:1 en claro y 14,54:1 en oscuro, y el título en
     * `--muni-danger-fg` da 5,48:1 y 4,75:1. Los cuatro pasan el 4,5:1 de WCAG 2.2
     * AA 1.4.3, que es lo que se vigila acá por si alguien mueve un token.
     */
    $css = cssDelComponente(fuenteResumenErrores());

    expect(str_contains($css, 'color:var(--muni-muted)'))->toBeFalse(
        'El resumen pinta algo en `--muni-muted`: la lista de errores es contenido primario '.
        'y en oscuro el atenuado queda al filo del 4,5:1 sobre el fondo de peligro.'
    );

    $bloques = [
        'claro' => bloqueTrasAncla(cssMuniUi(), 'Valores LIGHT (default)'),
        'oscuro' => bloqueTrasAncla(cssMuniUi(), 'Regla 2: activadores EXPLÍCITOS de dark'),
    ];

    foreach ($bloques as $tema => $bloque) {
        $bg = tokenHex($bloque, 'danger-bg');

        foreach (['text', 'danger-fg'] as $frente) {
            $fg = tokenHex($bloque, $frente);

            if ($fg === null || $bg === null) {
                continue;
            }

            expect(ratioContraste($fg, $bg))->toBeGreaterThanOrEqual(4.5, sprintf(
                '%s: `--muni-%s` (%s) sobre `--muni-danger-bg` (%s) da %.2f:1, bajo el 4,5:1 '.
                'de WCAG 2.2 AA 1.4.3',
                $tema, $frente, $fg, $bg, ratioContraste($fg, $bg)
            ));
        }
    }
});

it('el enlace del resumen aterriza en el id que input pone de verdad', function () {
    /*
     * La prueba de integración del contrato. Todo lo demás compara contra la cadena
     * `muni-rut` escrita a mano; acá se compara contra el id que `input.blade.php`
     * emite REALMENTE. Si alguien cambia cómo se deriva el id del control, este
     * candado cae y no se descubre en producción con un enlace muerto.
     */
    $campo = Blade::render('<x-muni::input name="rut" label="RUT" error="El RUT es obligatorio." />');

    expect((bool) preg_match('/<input\b[^>]*\bid="([^"]+)"/', $campo, $m))->toBeTrue(
        'No se pudo leer el id que `<x-muni::input name="rut">` le pone al control.'
    );

    $resumen = Blade::render('<x-muni::error-summary :errors="$e" />', [
        'e' => new MessageBag(['rut' => ['El RUT es obligatorio.']]),
    ]);

    expect(str_contains($resumen, 'href="#'.$m[1].'"'))->toBeTrue(sprintf(
        'El resumen enlaza a otro id que el que `input` emite (%s): el enlace no lleva al '.
        'campo. El contrato es `prefijo + clave de la bolsa`, y si `input` lo cambia hay '.
        'que mover la prop `prefix` o el mapa `ids`, no adivinar.',
        $m[1]
    ));
});
