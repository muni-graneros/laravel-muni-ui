<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/**
 * DECIR QUE ALGO ESTÁ EN CURSO, Y DECIR CUÁNDO TERMINÓ (WCAG 2.2 AA 4.1.3).
 *
 * Son dos piezas, y el juez de docs/GAP-ANALYSIS.md («cargando») exige que se
 * documente que una sin la otra no cumple nada:
 *
 *   · `<x-muni::spinner>` es el DIBUJO: decorativo, `aria-hidden`, SVG en línea (la
 *     CSP estricta de los sistemas bloquea imágenes `data:` en `mask-image`), y con la
 *     animación envuelta en `@media (prefers-reduced-motion: no-preference)`: anima
 *     solo si se permite, en vez de animar y apagar después.
 *   · `<x-muni::busy-region>` es el PATRÓN, que es donde está el valor: una región
 *     `role="status"` PERSISTENTE y vacía que existe en el HTML inicial, un
 *     `wire:loading.attr="aria-busy"` sobre el contenedor que Livewire reemplaza, y
 *     el mensaje de RESULTADO que el servidor pinta al terminar («14 personas
 *     encontradas»). Lo que se anuncia es el resultado, nunca el proceso: anunciar
 *     «cargando» en cada tecla de un buscador en vivo satura al lector.
 *
 * Por qué la región vive FUERA del contenedor ocupado, y no adentro:
 * `aria-busy="true"` en un ancestro autoriza al lector a ignorar los cambios de
 * una región viva hasta que termine, y el orden entre «quitar aria-busy» y «morph
 * del resultado» es detalle interno de Livewire, no un contrato. Se mide con el
 * Livewire 4.4.1 del vendor en Chromium y Firefox, en la última prueba de este
 * archivo (`tests/navegador/carga-anunciada-livewire.{php,py}`, reproducible
 * desde el repo): el atributo se quita y el texto aterriza 1-5 ms después
 * (`onMorph` corre tras `onEffect`); en 3.8.7 `respond()` apaga antes de
 * `processEffects` (leído en el vendor de personas-graneros). Hoy sale bien en
 * las dos; afuera no depende de eso, y el nodo role=status es el mismo tras cada
 * morph, que es lo que el lector necesita para seguir observándolo.
 *
 * Y por qué `<span role="status" wire:loading>` no sirve, que es la versión obvia:
 * `wire:loading` alterna `display` entre `none` e `inline-block`. Una región viva
 * que pasa de no renderizada a renderizada no se anuncia de forma fiable en NVDA,
 * JAWS ni VoiceOver. Es el bug clásico de las live regions creadas al momento del
 * cambio, y lo que estas pruebas vigilan que no vuelva.
 *
 * `toContain('valor', 'mensaje')` NO sirve en Pest: el segundo argumento es otra
 * aguja. Por eso todo va con `expect(bool)->toBeTrue('mensaje')`.
 */

/** El CSS de los bloques <style> de un HTML renderizado, sin comentarios. */
function cargaCss(string $html): string
{
    preg_match_all('#<style>(.*?)</style>#s', $html, $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/** El fuente de un componente sin comentarios Blade ni CSS: para buscar CÓDIGO, no prosa. */
function cargaFuenteSinComentarios(string $componente): string
{
    $fuente = (string) file_get_contents(__DIR__."/../resources/views/components/{$componente}.blade.php");
    $fuente = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $fuente);

    return (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
}

/** El HTML renderizado como DOM navegable; los atributos `wire:*` se conservan. */
function cargaDom(string $html): DOMXPath
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

/** Los bloques `@media (...) { ... }` de un CSS, con llaves anidadas resueltas. */
function cargaBloquesMedia(string $css): array
{
    $bloques = [];
    $offset = 0;

    while (preg_match('/@media\s*([^{]+)\{/', $css, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $inicio = $m[0][1] + strlen($m[0][0]);
        $nivel = 1;
        $i = $inicio;

        while ($i < strlen($css) && $nivel > 0) {
            if ($css[$i] === '{') {
                $nivel++;
            } elseif ($css[$i] === '}') {
                $nivel--;
            }
            $i++;
        }

        $bloques[] = ['condicion' => trim($m[1][0]), 'cuerpo' => substr($css, $inicio, $i - 1 - $inicio)];
        $offset = $i;
    }

    return $bloques;
}

/*
|--------------------------------------------------------------------------
| spinner: el dibujo
|--------------------------------------------------------------------------
*/

it('spinner es decorativo: aria-hidden, sin rol y sin texto', function () {
    $html = Blade::render('<x-muni::spinner />');
    $x = cargaDom($html);

    $raiz = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " muni-spinner ")]')->item(0);

    expect($raiz)->not->toBeNull('No hay raíz .muni-spinner: el componente no rinde nada.');
    expect($raiz->getAttribute('aria-hidden'))->toBe('true');
    expect($raiz->hasAttribute('role'))->toBeFalse(
        'El dibujo lleva `role`: la semántica va en la región (busy-region), no en la ruedita. '.
        'Un role="status" que se muestra con display:none→block no se anuncia de forma fiable.'
    );
    expect(trim($raiz->textContent))->toBe('');
});

it('spinner se dibuja con SVG en línea, sin imágenes externas ni data: (CSP estricta)', function () {
    $html = Blade::render('<x-muni::spinner />');

    expect(str_contains($html, '<svg'))->toBeTrue('No hay <svg> en línea: el dibujo depende de algo externo.');
    expect(str_contains($html, '<img'))->toBeFalse('Una <img> es una petición más y un punto de fallo bajo CSP.');

    $css = cargaCss($html);

    expect((bool) preg_match('/url\s*\(|data:/i', $css))->toBeFalse(
        'El CSS del spinner carga algo por url() o data:: la CSP estricta de los sistemas lo bloquea '.
        'sin error en consola y la ruedita desaparece.'
    );
    expect(str_contains($html, 'currentColor'))->toBeTrue(
        'El trazo no usa currentColor: el dibujo no hereda el color del texto y hay que pintarlo a mano.'
    );
});

it('spinner anima SOLO dentro de prefers-reduced-motion: no-preference', function () {
    $css = cargaCss(Blade::render('<x-muni::spinner />'));

    $medias = cargaBloquesMedia($css);
    $noPreference = array_values(array_filter(
        $medias,
        fn (array $b) => (bool) preg_match('/prefers-reduced-motion\s*:\s*no-preference/', $b['condicion']),
    ));

    expect($noPreference)->not->toBeEmpty(
        'No hay bloque @media (prefers-reduced-motion: no-preference): la animación corre aunque el '.
        'funcionario pidió movimiento reducido. El patrón es animar solo si se permite (daisyUI), '.
        'no animar y apagar después.'
    );

    $dentro = implode(' ', array_column($noPreference, 'cuerpo'));
    expect((bool) preg_match('/animation(-name)?\s*:\s*[^;]*muni-spin/', $dentro))->toBeTrue(
        'La animación muni-spin no se declara dentro del bloque de movimiento permitido.'
    );

    // Fuera de ese bloque no puede quedar NINGUNA propiedad `animation`.
    $fuera = $css;
    foreach ($medias as $b) {
        $fuera = str_replace($b['cuerpo'], '', $fuera);
    }
    expect((bool) preg_match('/animation(-name)?\s*:/', $fuera))->toBeFalse(
        'Hay una propiedad animation fuera del bloque de movimiento permitido: se anima siempre.'
    );
});

it('spinner acepta un tamaño y rechaza uno que no sea una longitud CSS', function () {
    $html = Blade::render('<x-muni::spinner size="32px" />');

    expect((bool) preg_match('/style="[^"]*width:\s*32px;[^"]*height:\s*32px;/', $html))->toBeTrue(
        'La prop `size` no fija width y height en línea.'
    );

    $raro = Blade::render('<x-muni::spinner size="20px;background:url(x)" />');

    expect(str_contains($raro, 'url('))->toBeFalse(
        'Un `size` que no es una longitud CSS se imprime tal cual dentro del style: es inyección de CSS.'
    );
    expect((bool) preg_match('/width:\s*20px;/', $raro))->toBeTrue('Con un `size` inválido no cae al tamaño por defecto.');
});

/*
|--------------------------------------------------------------------------
| busy-region: el patrón
|--------------------------------------------------------------------------
*/

it('busy-region emite UNA región role="status" persistente y vacía aunque no haya mensaje', function () {
    $html = Blade::render('<x-muni::busy-region target="buscar"><p>Resultados</p></x-muni::busy-region>');
    $x = cargaDom($html);

    $regiones = $x->query('//*[@role="status"]');

    expect($regiones->length)->toBe(1, 'Tiene que haber exactamente una región role="status", siempre presente.');

    $region = $regiones->item(0);

    expect(trim($region->textContent))->toBe('', 'Sin mensaje, la región tiene que estar VACÍA: el texto entra después, por el morph.');
    expect($region->hasAttribute('aria-live'))->toBeFalse(
        'role="status" ya implica aria-live="polite": los dos en el mismo nodo duplican el anuncio en algunos lectores.'
    );
    expect($region->hasAttribute('wire:ignore'))->toBeFalse(
        'La región lleva wire:ignore: el morph no la actualizaría y el resultado nunca llegaría.'
    );

    // Y el CSS no puede sacarla del árbol de accesibilidad.
    $css = cargaCss($html);
    preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $reglas, PREG_SET_ORDER);
    foreach ($reglas as $r) {
        if (str_contains($r[1], 'muni-busy__status')) {
            expect((bool) preg_match('/display\s*:\s*none|visibility\s*:\s*hidden/i', $r[2]))->toBeFalse(
                "La regla «{$r[1]}» oculta la región con display:none o visibility:hidden: sale del árbol y queda muda."
            );
        }
    }
});

it('busy-region marca aria-busy con wire:loading.attr sobre el contenedor que se reemplaza, y la región queda FUERA', function () {
    $html = Blade::render('<x-muni::busy-region target="buscar"><p>Resultados</p></x-muni::busy-region>');
    $x = cargaDom($html);

    $ocupados = $x->query('//*[@*[name()="wire:loading.attr"]]');

    expect($ocupados->length)->toBe(1, 'Falta el contenedor con wire:loading.attr.');

    $contenedor = $ocupados->item(0);

    expect($contenedor->getAttribute('wire:loading.attr'))->toBe('aria-busy');
    expect($contenedor->getAttribute('wire:target'))->toBe('buscar', 'El aria-busy no apunta al target: se marcaría ocupado por cualquier petición.');
    expect(str_contains($contenedor->textContent, 'Resultados'))->toBeTrue('El slot no va dentro del contenedor ocupado.');

    $region = $x->query('//*[@role="status"]')->item(0);
    $ancestro = $region->parentNode;
    $dentro = false;
    while ($ancestro !== null) {
        if ($ancestro === $contenedor) {
            $dentro = true;
        }
        $ancestro = $ancestro->parentNode;
    }

    expect($dentro)->toBeFalse(
        'La región role="status" está DENTRO del contenedor aria-busy: un ancestro ocupado autoriza al lector a '.
        'ignorar sus cambios, y el orden entre quitar aria-busy y el morph es detalle interno de Livewire, no un contrato.'
    );
});

it('busy-region muestra el proceso en texto visible, con retardo antiparpadeo nativo y sin región viva', function () {
    $html = Blade::render('<x-muni::busy-region target="buscar" loading="Buscando en el maestro de personas…"><p>Resultados</p></x-muni::busy-region>');
    $x = cargaDom($html);

    $raiz = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " muni-busy ")]')->item(0);

    expect($raiz)->not->toBeNull('No hay raíz .muni-busy.');

    // El retardo es de Livewire, no de Alpine: `.delay.long` son 300 ms en la tabla
    // delayModifiers del propio Livewire, y existe igual en Livewire 3.
    expect($raiz->getAttribute('wire:loading.delay.long.class'))->toBe('muni-busy--loading', 'Falta wire:loading.delay.long.class en la raíz: sin retardo, cada respuesta rápida parpadea.');
    expect($raiz->getAttribute('wire:target'))->toBe('buscar');

    $indicador = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " muni-busy__loading ")]')->item(0);

    expect($indicador)->not->toBeNull('No hay indicador visible de carga.');
    expect(str_contains($indicador->textContent, 'Buscando en el maestro de personas…'))->toBeTrue('El texto de carga no se imprime: la espera queda solo en movimiento.');
    expect($indicador->hasAttribute('role'))->toBeFalse('El indicador de proceso NO es una región viva: se anuncia el resultado, no el proceso.');
    expect($indicador->hasAttribute('aria-live'))->toBeFalse('El indicador de proceso NO es una región viva.');
    expect($x->query('.//*[@aria-hidden="true"]', $indicador)->length)->toBeGreaterThanOrEqual(1, 'El indicador no trae el spinner decorativo.');

    // Nace oculto por CSS y lo muestra la clase que pone Livewire: sin Livewire (Blade
    // puro, o antes de hidratar) no puede quedar una ruedita eterna en pantalla.
    $css = cargaCss($html);
    expect((bool) preg_match('/\.muni-busy__loading\s*\{[^}]*display\s*:\s*none/', $css))->toBeTrue('El indicador no nace oculto por CSS.');
    expect((bool) preg_match('/\.muni-busy--loading\s*>?\s*\.muni-busy__loading\s*\{[^}]*display\s*:\s*(inline-)?flex/', $css))->toBeTrue('La clase de carga no muestra el indicador.');
});

it('busy-region: la prop delay elige el modificador de Livewire y cae a long si no existe', function () {
    $largo = Blade::render('<x-muni::busy-region target="padron" delay="longest">x</x-muni::busy-region>');
    expect(str_contains($largo, 'wire:loading.delay.longest.class="muni-busy--loading"'))->toBeTrue();

    $sinRetardo = Blade::render('<x-muni::busy-region target="padron" delay="none">x</x-muni::busy-region>');
    expect(str_contains($sinRetardo, 'wire:loading.class="muni-busy--loading"'))->toBeTrue('delay="none" no quita el retardo.');
    expect(str_contains($sinRetardo, '.delay'))->toBeFalse();

    $invalido = Blade::render('<x-muni::busy-region target="padron" delay="rapidito">x</x-muni::busy-region>');
    expect(str_contains($invalido, 'wire:loading.delay.long.class="muni-busy--loading"'))->toBeTrue(
        'Un delay que Livewire no conoce se imprime igual: el directive queda sin retardo y nadie avisa.'
    );

    // Los SIETE de la tabla delayModifiers (4.4.1 y 3.8.7), `default` incluido: con
    // la lista incompleta, `delay="default"` (200 ms) caía a `long` (300 ms) sin aviso.
    foreach (['shortest', 'shorter', 'short', 'default', 'long', 'longer', 'longest'] as $modificador) {
        $html = Blade::render('<x-muni::busy-region target="padron" delay="'.$modificador.'">x</x-muni::busy-region>');
        expect(str_contains($html, 'wire:loading.delay.'.$modificador.'.class="muni-busy--loading"'))->toBeTrue(
            "delay=\"{$modificador}\" existe en la tabla delayModifiers de Livewire y el componente no lo imprime."
        );
    }
});

it('busy-region y spinner no duplican un atributo que el consumidor también pasa: wire:target y aria-hidden van por merge', function () {
    // DESIGN §8: un atributo escrito a mano después del derrame sale DOS veces si el
    // consumidor lo pasó, y en HTML gana el primero (el del consumidor). Con `wire:target`
    // eso dejaba la raíz escuchando un target y el contenedor otro.
    $directo = Blade::render('<x-muni::busy-region wire:target="buscar">x</x-muni::busy-region>');

    expect(substr_count($directo, 'wire:target="'))->toBe(2, 'Con wire:target directo tiene que salir UNA vez en la raíz y UNA en el contenedor.');
    expect(substr_count($directo, 'wire:target="buscar"'))->toBe(2, 'El wire:target del consumidor tiene que llegar a los dos nodos.');

    $ambos = Blade::render('<x-muni::busy-region target="viejo" wire:target="nuevo">x</x-muni::busy-region>');

    expect(substr_count($ambos, 'wire:target="'))->toBe(2, 'Con `target` y `wire:target` a la vez la raíz sale con el atributo duplicado.');
    expect(str_contains($ambos, 'wire:target="viejo"'))->toBeFalse('Si el consumidor escribe wire:target en la etiqueta, ese gana en los DOS nodos.');
    expect(substr_count($ambos, 'wire:target="nuevo"'))->toBe(2);

    $ocultoDosVeces = Blade::render('<x-muni::spinner aria-hidden="false" />');

    expect(substr_count($ocultoDosVeces, 'aria-hidden="'))->toBe(1, 'spinner imprime aria-hidden dos veces cuando el consumidor lo pasa.');
    expect(str_contains($ocultoDosVeces, 'aria-hidden="true"'))->toBeTrue('El dibujo es decorativo por contrato: aria-hidden="true" siempre, aunque el consumidor intente cambiarlo.');
});

it('el indicador superpuesto no intercepta el puntero: pointer-events none', function () {
    // Va `position:absolute` sobre la esquina del contenido que se reemplaza. Sin esto,
    // durante la búsqueda por RUT (≈1 s) un clic o un tap sobre esa esquina cae en el
    // rótulo «Buscando…» y no en la fila que hay debajo.
    $css = cargaCss(Blade::render('<x-muni::busy-region target="buscar">x</x-muni::busy-region>'));

    expect((bool) preg_match('/\.muni-busy__loading\s*\{[^}]*pointer-events\s*:\s*none/', $css))->toBeTrue(
        'El indicador superpuesto no lleva pointer-events:none: intercepta clics sobre el contenido mientras está visible.'
    );
});

it('busy-region documenta que el anfitrión APAGA busy cuando llega el dato, y el banco con Livewire real lo mide', function () {
    // La trampa: con `:busy="true"` fijo, al terminar la petición Livewire quita la clase y
    // el aria-busy y el morph los vuelve a pintar desde el HTML del servidor, que sigue
    // diciendo «ocupada»: ruedita y aria-busy eternos, en silencio. El fuente tiene que
    // decirlo con el ejemplo atado al dato, y el banco de abajo lo mide con Livewire real.
    $region = (string) file_get_contents(__DIR__.'/../resources/views/components/busy-region.blade.php');

    expect(str_contains($region, ':busy="$total === null"'))->toBeTrue(
        'busy-region no muestra el ejemplo de `busy` atado al dato (`:busy="$total === null"`): con `:busy="true"` fijo la región queda ocupada para siempre.'
    );
    expect(str_contains($region, 'placeholder()'))->toBeTrue(
        'busy-region no documenta la única excepción (el placeholder() de un componente perezoso, que el render real reemplaza entero).'
    );
});

it('busy-region sin target escucha cualquier petición del componente', function () {
    $html = Blade::render('<x-muni::busy-region>x</x-muni::busy-region>');

    expect(str_contains($html, 'wire:target'))->toBeFalse('Sin `target` no debe emitir un wire:target vacío.');
    expect(str_contains($html, 'wire:loading.attr="aria-busy"'))->toBeTrue();
});

it('busy-region pinta el mensaje de resultado dentro de la región, escapado, por prop o por ranura', function () {
    $porProp = Blade::render('<x-muni::busy-region target="buscar" status="14 personas encontradas">x</x-muni::busy-region>');
    $x = cargaDom($porProp);
    expect(trim($x->query('//*[@role="status"]')->item(0)->textContent))->toBe('14 personas encontradas');

    $porRanura = Blade::render('<x-muni::busy-region target="buscar">x<x-slot:status>Sin coincidencias para el RUT ingresado</x-slot:status></x-muni::busy-region>');
    $x = cargaDom($porRanura);
    expect(trim($x->query('//*[@role="status"]')->item(0)->textContent))->toBe('Sin coincidencias para el RUT ingresado');

    // Minimización (Ley 21.719) y XSS: recibe un string ya redactado, nunca HTML ni un registro.
    $inyectado = Blade::render('<x-muni::busy-region target="buscar" :status="\'<b>1</b> persona\'">x</x-muni::busy-region>');
    expect(str_contains($inyectado, '<b>1</b>'))->toBeFalse('El mensaje de estado se imprime sin escapar.');
    expect(str_contains($inyectado, '&lt;b&gt;1&lt;/b&gt; persona'))->toBeTrue();
});

it('busy-region con busy=true nace ocupada desde el servidor (carga perezosa)', function () {
    $html = Blade::render('<x-muni::busy-region target="cargar" :busy="true"></x-muni::busy-region>');
    $x = cargaDom($html);

    $contenedor = $x->query('//*[@*[name()="wire:loading.attr"]]')->item(0);
    expect($contenedor->getAttribute('aria-busy'))->toBe('true', 'Con busy=true el contenedor no nace aria-busy.');

    $raiz = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " muni-busy ")]')->item(0);
    expect(str_contains(' '.$raiz->getAttribute('class').' ', ' muni-busy--loading '))->toBeTrue('Con busy=true el indicador no nace visible.');

    $normal = Blade::render('<x-muni::busy-region target="cargar">x</x-muni::busy-region>');
    expect(str_contains($normal, 'aria-busy="true"'))->toBeFalse('Sin busy, el contenedor no puede nacer ocupado.');

    // La cadena `muni-busy--loading` aparece legítimamente en el wire:loading…class y en
    // el CSS: lo que no puede traer es el atributo class de la raíz.
    $x = cargaDom($normal);
    $raiz = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " muni-busy ")]')->item(0);
    expect(str_contains(' '.$raiz->getAttribute('class').' ', ' muni-busy--loading '))->toBeFalse('Sin busy, el indicador no puede nacer visible.');
});

it('busy-region no usa Alpine ni JS propio: el retardo y el estado son de Livewire', function () {
    $fuente = cargaFuenteSinComentarios('busy-region');

    expect((bool) preg_match('/\bx-data\b|\bx-init\b|<script/', $fuente))->toBeFalse(
        'busy-region trae Alpine o un <script>: el juez lo dejó en cero, el retardo antiparpadeo ya es nativo (.delay.long).'
    );
    expect(str_contains(cargaFuenteSinComentarios('spinner'), 'x-data'))->toBeFalse();
});

it('las dos piezas solo leen tokens --muni-* declarados en las DOS hojas', function () {
    $hojas = [
        'muni-ui.css' => (string) file_get_contents(__DIR__.'/../resources/css/muni-ui.css'),
        'muni-ui-filament.css' => (string) file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css'),
    ];

    foreach (['spinner', 'busy-region'] as $componente) {
        $fuente = cargaFuenteSinComentarios($componente);

        preg_match_all('/var\((--muni-[a-z0-9-]+)/', $fuente, $m);

        foreach (array_unique($m[1]) as $token) {
            foreach ($hojas as $hoja => $css) {
                expect(str_contains($css, $token.':'))->toBeTrue(
                    "«{$componente}» lee {$token} y {$hoja} no lo declara: se vería roto y sin avisar."
                );
            }
        }

        // Cero colores literales fuera del respaldo de foco.
        $sinFoco = str_replace('#767676', '', $fuente);
        expect((bool) preg_match('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/', $sinFoco))->toBeFalse(
            "«{$componente}» tiene un color literal: va en un token, no en el componente."
        );
    }
});

it('los límites del patrón quedan documentados en el fuente: x-for en Livewire 3 y los paneles Filament', function () {
    $region = (string) file_get_contents(__DIR__.'/../resources/views/components/busy-region.blade.php');
    $spinner = (string) file_get_contents(__DIR__.'/../resources/views/components/spinner.blade.php');

    expect(str_contains($region, 'template x-for'))->toBeTrue(
        'busy-region no documenta el límite de Livewire 3: un wire:loading dentro de <template x-for> queda huérfano.'
    );
    expect(str_contains($region, 'loading-section'))->toBeTrue(
        'busy-region no documenta que dentro de un panel Filament el caso lo resuelve x-filament::loading-section.'
    );
    expect(str_contains($spinner, 'busy-region'))->toBeTrue(
        'spinner no dice que sin busy-region no cumple nada: quedaría de adorno con falsa sensación de cumplimiento.'
    );
});

/*
|--------------------------------------------------------------------------
| El banco de navegador
|--------------------------------------------------------------------------
|
| Se genera acá y no en un script suelto porque este es el único sitio del
| paquete con Blade arrancado (mismo criterio que `GeneraVitrinaTest` y
| `CifraComparadaTest`). Sale a `build/carga-anunciada/`, ignorado por git, y lo
| abre Playwright (`.venv-a11y`) para comprobar lo que un test de Blade no puede:
| que el arco gira SOLO sin movimiento reducido, que el indicador nace oculto y
| se muestra con la clase que pone Livewire, que la región role=status sigue en
| el árbol de accesibilidad estando vacía, y que el texto de carga y el
| resultado pasan contraste en las dos paletas y los dos temas.
|
| Cuatro páginas por lo mismo que la vitrina (DESIGN §7): dentro de un panel
| Filament `muni-ui.css` no se carga, y `panel-*` carga ÚNICAMENTE la hoja del
| panel con el DOM mínimo que esa hoja estiliza.
*/

it('genera el banco de navegador en build/carga-anunciada/', function () {
    $dir = __DIR__.'/../build/carga-anunciada';

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    // Los estados del buscador por RUT del mesón: el dibujo suelto en tres
    // tamaños, la región en reposo, ocupada (como nace una carga perezosa) y con
    // el resultado pintado. Los dos últimos se repiten sobre `--muni-surface-3`,
    // que es donde el texto atenuado del resultado se acerca más al umbral.
    $casos = Blade::render(
        '<div class="banco-grid">'
        .'<section class="banco-tarjeta" data-caso="spinner"><h2>spinner</h2>'
        .'<p>Generar padrón <x-muni::spinner size="16px" /> <x-muni::spinner /> <x-muni::spinner size="32px" /></p></section>'
        .'<section class="banco-tarjeta" data-caso="reposo"><h2>en reposo</h2>'
        .'<x-muni::busy-region target="rut" loading="Buscando en el maestro de personas…"><p>Escribe un RUT para buscar.</p></x-muni::busy-region></section>'
        .'<section class="banco-tarjeta" data-caso="ocupada"><h2>ocupada</h2>'
        .'<x-muni::busy-region target="rut" loading="Buscando en el maestro de personas…" :busy="true"><p>Ana Soto Miranda · 12.345.678-9</p></x-muni::busy-region></section>'
        .'<section class="banco-tarjeta" data-caso="resultado"><h2>con resultado</h2>'
        .'<x-muni::busy-region target="rut" loading="Buscando en el maestro de personas…" status="1 persona encontrada"><p>Ana Soto Miranda · 12.345.678-9</p></x-muni::busy-region></section>'
        .'<section class="banco-tarjeta banco-tarjeta--3" data-caso="ocupada-3"><h2>ocupada sobre surface-3</h2>'
        .'<x-muni::busy-region target="rut" loading="Buscando en el maestro de personas…" :busy="true"><p>Ana Soto Miranda · 12.345.678-9</p></x-muni::busy-region></section>'
        .'<section class="banco-tarjeta banco-tarjeta--3" data-caso="resultado-3"><h2>con resultado sobre surface-3</h2>'
        .'<x-muni::busy-region target="rut" loading="Buscando en el maestro de personas…" status="1 persona encontrada"><p>Ana Soto Miranda · 12.345.678-9</p></x-muni::busy-region></section>'
        .'</div>'
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

        // El armazón no aporta ni un color: fondo y texto salen de los mismos
        // tokens que leen los componentes. En el panel, `color:var(--muni-text)`
        // repone lo que Filament pone en el <body> y el paquete no.
        $armazon = 'body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
            .'h1{font-size:20px;margin:0 0 16px}h2{font-size:12px;margin:0 0 12px;color:var(--muni-muted)}'
            .'.banco-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}'
            .'.banco-tarjeta{background:var(--muni-surface);border:1px solid var(--muni-border);border-radius:var(--muni-radius-lg);padding:16px}'
            .'.banco-tarjeta--3{background:var(--muni-surface-3)}';

        $cuerpo = $panel
            ? '<body class="fi-body"><div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
                .'<h1>Búsqueda por RUT en el mesón</h1><section class="fi-section">'.$casos.'</section></main></div></body>'
            : '<body><main id="muni-contenido" tabindex="-1"><h1>Búsqueda por RUT en el mesón</h1>'.$casos.'</main></body>';

        $pagina = '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-hoja="'.$hoja.'">'
            .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Banco carga anunciada — '.$nombre.'</title>'
            .'<style>'.$css.'</style><style>'.$armazon.'</style></head>'
            .$cuerpo.'</html>';

        file_put_contents($dir.'/'.$nombre.'.html', $pagina);
    }

    foreach (array_keys($paginas) as $nombre) {
        expect(is_file($dir.'/'.$nombre.'.html'))->toBeTrue('No se escribió el banco '.$nombre.'.');
    }
});

/*
 * Lo que un test de Blade no puede ver, medido en Chromium y Firefox sobre el
 * banco: `tests/navegador/carga-anunciada.py` lee estilos computados y el árbol
 * de accesibilidad en las cuatro páginas, con y sin `prefers-reduced-motion`.
 * Acá solo se exige que salga en verde. Se SALTA —no se finge— cuando no está
 * `.venv-a11y`, que en CI no se instala.
 */
it('en Chromium y Firefox: el arco gira solo sin movimiento reducido, el indicador nace oculto, la región vacía sigue en el árbol y los textos pasan contraste en las dos paletas', function () {
    $python = __DIR__.'/../.venv-a11y/bin/python';

    if (! is_executable($python)) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay Chromium: la verificación en navegador se salta, no se da por hecha.');
    }

    $lineas = [];
    $codigo = 0;

    exec(
        escapeshellarg($python).' '.escapeshellarg(__DIR__.'/navegador/carga-anunciada.py').' '
        .escapeshellarg(__DIR__.'/../build/carga-anunciada').' 2>&1',
        $lineas,
        $codigo,
    );

    expect($codigo)->toBe(0, "La verificación en navegador de spinner y busy-region falló:\n".implode("\n", $lineas));
    expect(count(array_filter($lineas, fn (string $l) => str_contains($l, '[reduce       ] spinner=none'))))->toBe(8,
        "Las cuatro páginas en los dos navegadores tienen que reportar el arco quieto con la preferencia:\n".implode("\n", $lineas));
});

/*
|--------------------------------------------------------------------------
| El banco con Livewire REAL
|--------------------------------------------------------------------------
|
| El banco estático de arriba no ejercita `wire:loading` ni el morph: lo que
| sostiene las decisiones del componente —que `aria-busy` se pone al salir la
| petición, que el indicador aparece a los 300 ms y no antes, que el resultado
| aterriza en el MISMO nodo role=status, que `busy` atado al dato se apaga y
| `:busy="true"` fijo NO— se mide acá con el Livewire 4 del vendor (viene con
| `filament/filament`, dependencia de desarrollo) servido por `php -S`:
| `tests/navegador/carga-anunciada-livewire.php` es la aplicación y
| `tests/navegador/carga-anunciada-livewire.py` la levanta, la mide en Chromium
| y Firefox y la apaga. Es el mismo banco que se sirve a mano para la prueba
| con NVDA y VoiceOver (punto 4 del juez), que sigue siendo manual.
|
| Se SALTA —no se finge— sin `.venv-a11y` o sin Livewire en vendor.
*/
it('con Livewire 4 real en Chromium y Firefox: aria-busy al salir la petición, indicador a los 300 ms, resultado en el mismo nodo role=status, busy atado al dato se apaga y busy fijo queda pegado', function () {
    $python = __DIR__.'/../.venv-a11y/bin/python';

    if (! is_executable($python)) {
        $this->markTestSkipped('Sin .venv-a11y (npm run a11y:instalar) no hay Chromium: el banco con Livewire real se salta, no se da por hecho.');
    }

    if (! class_exists(Livewire::class)) {
        $this->markTestSkipped('Sin livewire/livewire en vendor (llega con filament/filament, dependencia de desarrollo) no hay banco con Livewire real.');
    }

    $lineas = [];
    $codigo = 0;

    // El mismo php que corre la suite levanta el servidor: sin esto, el `php` del
    // PATH podría ser otro binario, con otras extensiones.
    exec(
        'PHP_BINARY='.escapeshellarg(PHP_BINARY).' '
        .escapeshellarg($python).' '.escapeshellarg(__DIR__.'/navegador/carga-anunciada-livewire.py').' 2>&1',
        $lineas,
        $codigo,
    );

    $salida = implode("\n", $lineas);

    expect($codigo)->toBe(0, "El banco con Livewire real falló:\n".$salida);

    // Las dos afirmaciones del fuente que este banco existe para sostener, en los dos navegadores.
    expect(substr_count($salida, 'busy atado al dato: se apagó'))->toBe(2, "Falta la medición de `busy` atado al dato en algún navegador:\n".$salida);
    expect(substr_count($salida, 'busy fijo: quedó pegado (la trampa documentada sigue vigente)'))->toBe(2, "Falta la medición de `:busy=\"true\"` fijo en algún navegador:\n".$salida);
});
