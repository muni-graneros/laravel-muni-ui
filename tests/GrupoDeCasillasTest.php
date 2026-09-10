<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DEL GRUPO DE CASILLAS (y de la prop muerta de `field`)
|--------------------------------------------------------------------------
|
| `<x-muni::checkbox>` ya resuelve la casilla suelta. Un grupo NO es la suma de
| sus casillas: son los requisitos que marca el funcionario al recibir una
| solicitud de patente y las categorías de discapacidad de la ficha de
| inscripción, y ahí el lector de pantalla tiene que decir de qué grupo forma
| parte cada casilla y dónde vive el error del conjunto.
|
| Las condiciones que se fijan acá:
|
| 1. FIELDSET Y LEGEND NATIVOS, sin `role` de grupo redundante encima: el
|    elemento ya trae la semántica y repetirla solo agrega ruido.
| 2. EL ERROR SE ATA AL FIELDSET, no a cada `<input>`. Atado a los inputs, el
|    lector lo repite tantas veces como casillas haya: con ocho requisitos, ocho
|    veces «elige al menos uno».
| 3. NO ES UN RADIOGROUP. Cada casilla es su propia parada de tabulación: nada
|    de `tabindex` gestionado ni de `role="radio"`.
| 4. NINGUNA CASILLA VIENE PREMARCADA (Ley 21.719). Un consentimiento
|    premarcado no es una manifestación de voluntad libre y el asiento de
|    licitud que guarde el municipio no sirve como prueba.
| 5. LOS IDS SALEN DEL `name` (DESIGN §10), nunca de `uniqid()`, y son estables
|    entre renders.
| 6. SE REUTILIZA la casilla del paquete en vez de reimplementar su marcado: si
|    el blanco de pulsación de 24px o el borde de 3:1 cambian, cambian en un
|    solo archivo.
| 7. LA PROP `name` DE `field` no se queda muerta y en silencio: el paquete es
|    aditivo y quitarla está prohibido, así que queda documentada como aceptada
|    y sin efecto, con el motivo escrito en el componente.
|
| Se prueba con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra cadena a buscar, no un mensaje, y la
| aserción se desactiva sin avisar.
*/

/** La fuente Blade cruda de un componente. */
function fuenteGrupoCasillas(string $componente): string
{
    $ruta = __DIR__.'/../resources/views/components/'.$componente.'.blade.php';

    return is_file($ruta) ? (string) file_get_contents($ruta) : '';
}

/**
 * La fuente sin comentarios de Blade, de PHP ni de HTML: solo lo que se ejecuta.
 *
 * Hace falta porque estas pruebas buscan defectos por su nombre —`uniqid`,
 * `x-data`, un color literal— y un componente que EXPLIQUE en un comentario por
 * qué no los usa daría un falso rojo.
 */
function codigoGrupoCasillas(string $componente): string
{
    $fuente = fuenteGrupoCasillas($componente);
    $fuente = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $fuente);
    $fuente = (string) preg_replace('#/\*.*?\*/#s', '', $fuente);
    $fuente = (string) preg_replace('#<!--.*?-->#s', '', $fuente);

    return (string) preg_replace('#^\s*//.*$#m', '', $fuente);
}

/** El contenido de los bloques `<style>` de un componente, ya sin comentarios. */
function cssGrupoCasillas(string $componente): string
{
    preg_match_all('#<style>(.*?)</style>#s', fuenteGrupoCasillas($componente), $m);

    return (string) preg_replace('#/\*.*?\*/#s', '', implode("\n", $m[1]));
}

/** Un grupo renderizado con los requisitos de una patente comercial. */
function grupoHtml(string $extra = ''): string
{
    return Blade::render(
        '<x-muni::checkbox-group name="requisitos" legend="Requisitos entregados" '.
        ':options="[\'rol\' => \'Rol de avalúo\', \'zona\' => \'Informe de zonificación\', \'sii\' => \'Inicio de actividades\']" '.
        $extra.' />'
    );
}

/**
 * Todas las etiquetas de apertura `<input …>` del HTML.
 *
 * Se quitan antes los bloques `<style>`: el CSS de la casilla EXPLICA en un
 * comentario por qué el input real va con opacity:0, y esa prosa se contaría
 * como una casilla más.
 */
function etiquetasInputGrupo(string $html): array
{
    preg_match_all('/<input\b[^>]*>/i', (string) preg_replace('#<style>.*?</style>#s', '', $html), $m);

    return $m[0];
}

/** El valor de un atributo dentro de una etiqueta ya recortada. */
function attrGrupo(string $etiqueta, string $atributo): ?string
{
    if (preg_match('/\b'.preg_quote($atributo, '/').'="([^"]*)"/i', $etiqueta, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    }

    return null;
}

/** El HTML interno del elemento con ese id, o null si el id no existe. */
function textoConIdGrupo(string $html, string $id): ?string
{
    $patron = '/<([a-z]+)\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>(.*?)<\/\1>/is';

    return preg_match($patron, $html, $m) ? trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5)) : null;
}

it('el grupo es un fieldset con legend nativos y sin rol redundante', function () {
    $html = grupoHtml();

    expect((bool) preg_match('/<fieldset\b/i', $html))->toBeTrue(
        'El grupo no emite un <fieldset>: sin él el lector de pantalla no dice de qué grupo forma '.
        'parte cada casilla y el error del conjunto no tiene dónde vivir.'
    );

    expect((bool) preg_match('/<fieldset\b[^>]*>\s*<legend\b[^>]*>(.*?)<\/legend>/is', $html, $m))->toBeTrue(
        'El <legend> no es el primer hijo del <fieldset>: fuera de esa posición el navegador no lo '.
        'usa como nombre accesible del grupo.'
    );

    expect(str_contains(strip_tags($m[1]), 'Requisitos entregados'))->toBeTrue(
        'El <legend> no contiene el texto de la prop `legend`.'
    );

    expect((bool) preg_match('/role="group"/i', codigoGrupoCasillas('checkbox-group')))->toBeFalse(
        'El grupo escribe un rol de grupo encima del <fieldset>, que ya lo trae: es ruido redundante.'
    );
});

it('el error se ata al fieldset y no se repite en cada casilla', function () {
    $mensaje = 'Elige al menos un requisito';
    $html = grupoHtml('error="'.$mensaje.'"');

    preg_match('/<fieldset\b[^>]*>/i', $html, $m);
    $descrito = (string) attrGrupo($m[0] ?? '', 'aria-describedby');

    expect($descrito)->not->toBe('',
        'El <fieldset> no emite aria-describedby: el error del grupo no lo anuncia nadie (WCAG 2.2 AA 3.3.1).'
    );

    $textos = array_map(
        fn (string $id) => (string) textoConIdGrupo($html, $id),
        array_filter(preg_split('/\s+/', trim($descrito)) ?: [])
    );

    expect(str_contains(implode(' | ', $textos), $mensaje))->toBeTrue(sprintf(
        'El aria-describedby="%s" del fieldset no apunta al mensaje de error (apunta a: %s).',
        $descrito, implode(' | ', $textos)
    ));

    // El punto delicado del componente: atado a cada input, el lector lo dice
    // tantas veces como casillas haya.
    foreach (etiquetasInputGrupo($html) as $input) {
        expect(str_contains((string) attrGrupo($input, 'aria-describedby'), 'error'))->toBeFalse(
            'El error del grupo está atado también a cada <input>: el lector lo repite una vez por casilla.'
        );
    }

    expect(substr_count($html, $mensaje))->toBe(1,
        'El mensaje de error del grupo aparece más de una vez en el HTML: se está pintando por casilla.'
    );

    // La región viva existe desde el primer render: con Livewire el error llega
    // en un round-trip y un <span> que nace de la nada no lo lee ningún lector.
    foreach (['con error' => $html, 'sin error' => grupoHtml()] as $caso => $fuente) {
        expect((bool) preg_match('/<[a-z]+\b[^>]*\b(?:role="status"|aria-live="polite")[^>]*>/i', $fuente))->toBeTrue(
            "El grupo ({$caso}) no tiene región viva para el error del conjunto (WCAG 2.2 AA 4.1.3)."
        );
    }
});

it('ninguna casilla del grupo viene premarcada si no se pide', function () {
    // Ley 21.719: un grupo de casillas de consentimiento premarcado no es una
    // manifestación de voluntad libre, específica e inequívoca.
    expect((bool) preg_match('/<input\b[^>]*\bchecked\b/i', grupoHtml()))->toBeFalse(
        'El grupo viene premarcado por defecto. Bajo la Ley 21.719 el consentimiento premarcado no '.
        'es consentimiento: el asiento de licitud queda inservible.'
    );

    expect(str_contains(codigoGrupoCasillas('checkbox-group'), "'selected' => []"))->toBeTrue(
        'La prop de selección no declara el arreglo vacío como valor por defecto: el componente '.
        'permitiría premarcar sin que el anfitrión lo decida.'
    );

    $marcado = grupoHtml(':selected="[\'zona\']"');
    $marcadas = [];

    foreach (etiquetasInputGrupo($marcado) as $input) {
        if (preg_match('/\bchecked\b/i', $input)) {
            $marcadas[] = (string) attrGrupo($input, 'value');
        }
    }

    expect($marcadas)->toBe(['zona'],
        'La selección explícita no marca exactamente las casillas pedidas.'
    );
});

it('los ids salen del name, son estables entre renders y el name llega entero al backend', function () {
    $uno = grupoHtml();
    $dos = grupoHtml();

    preg_match('/<fieldset\b[^>]*\bid="([^"]+)"/i', $uno, $a);
    preg_match('/<fieldset\b[^>]*\bid="([^"]+)"/i', $dos, $b);

    expect($a[1] ?? 'a')->toBe($b[1] ?? 'b',
        'El id del grupo cambia entre dos renders: rompe el aria-describedby tras el primer '.
        'refresco de Livewire y ensucia el diffing.'
    );

    expect($a[1] ?? '')->toBe('muni-requisitos',
        'El id del grupo no se deriva del `name`: el resumen de errores enlaza a #muni-<campo> y '.
        'un enlace muerto es peor que ninguno.'
    );

    expect(str_contains(codigoGrupoCasillas('checkbox-group'), 'uniqid'))->toBeFalse(
        'El grupo usa uniqid() para el id, prohibido por DESIGN §10.'
    );

    // Un grupo envía varios valores: el `name` de las casillas es el del grupo
    // con la notación de arreglo de PHP.
    foreach (etiquetasInputGrupo($uno) as $input) {
        expect(attrGrupo($input, 'name'))->toBe('requisitos[]',
            'Las casillas del grupo no envían el `name` como arreglo: al backend llega un solo valor.'
        );
    }

    // El id que pasa el consumidor manda.
    expect((bool) preg_match('/<fieldset\b[^>]*\bid="mi-grupo"/i', grupoHtml('id="mi-grupo"')))->toBeTrue(
        'El grupo ignora el id que pasa el consumidor.'
    );

    // Y un `name` con notación de arreglo no produce un id inválido.
    $arreglo = Blade::render(
        '<x-muni::checkbox-group name="tipos[]" legend="Tipos" :options="[\'a\' => \'A\']" />'
    );
    preg_match('/<fieldset\b[^>]*\bid="([^"]+)"/i', $arreglo, $c);

    expect((bool) preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $c[1] ?? ''))->toBeTrue(sprintf(
        'Un name con notación de arreglo produce el id inválido "%s".', $c[1] ?? ''
    ));

    expect(substr_count($arreglo, 'name="tipos[]"'))->toBe(1,
        'Con un `name` que ya trae la notación de arreglo, el grupo la duplica o la pierde.'
    );
});

it('escapa el valor y la etiqueta de una opción con HTML', function () {
    // El valor hostil viaja como DATO y no escrito dentro de la etiqueta: una
    // comilla dentro de la expresión de un atributo rompe el compilador de
    // componentes antes de que el componente exista, y no probaría nada.
    $html = Blade::render(
        '<x-muni::checkbox-group name="requisitos" legend="Requisitos" :options="$opciones" />',
        ['opciones' => ['"><img src=x onerror=alert(1)>' => '<b>Rol</b> de avalúo']]
    );

    expect(str_contains($html, '<img src=x'))->toBeFalse(
        'El valor de la opción sale sin escapar: es una inyección de HTML servida por el paquete.'
    );

    expect(str_contains($html, '<b>Rol</b>'))->toBeFalse(
        'La etiqueta de la opción sale sin escapar.'
    );

    expect(str_contains($html, 'onerror=alert(1)'))->toBeTrue(
        'El valor se perdió por completo: tiene que viajar escapado, no desaparecer.'
    );

    foreach (etiquetasInputGrupo($html) as $input) {
        expect((bool) preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', (string) attrGrupo($input, 'id')))->toBeTrue(
            'Un valor con HTML produce un id que no es un selector válido.'
        );
    }
});

it('reutiliza la casilla del paquete y cada una es su propia parada de tabulación', function () {
    expect(str_contains(codigoGrupoCasillas('checkbox-group'), 'x-muni::checkbox'))->toBeTrue(
        'El grupo reimplementa el marcado de la casilla en vez de reutilizar el componente: el '.
        'blanco de pulsación de 24px y el borde de 3:1 tendrían que arreglarse dos veces.'
    );

    $html = grupoHtml();

    expect(count(etiquetasInputGrupo($html)))->toBe(3,
        'El grupo no emite una casilla por opción.'
    );

    foreach (etiquetasInputGrupo($html) as $input) {
        expect(attrGrupo($input, 'type'))->toBe('checkbox',
            'El grupo no emite casillas nativas.'
        );

        expect(attrGrupo($input, 'tabindex'))->toBeNull(
            'El grupo gestiona el tabindex de las casillas: no es un radiogroup, cada casilla es su '.
            'propia parada de tabulación.'
        );
    }

    foreach (['role="radiogroup"', 'role="radio"', 'role="checkbox"'] as $prohibido) {
        expect(str_contains($html, $prohibido))->toBeFalse(
            "El grupo emite {$prohibido}: la semántica nativa ya está y el rol la pisa."
        );
    }
});

it('el grupo no necesita Alpine, no escribe colores y respeta los tokens', function () {
    $codigo = codigoGrupoCasillas('checkbox-group');

    expect(str_contains($codigo, 'x-data'))->toBeFalse(
        'El grupo monta un componente de Alpine sin necesitarlo: el estado del DOM ya es la verdad.'
    );

    $sinRespaldo = str_replace('#767676', '', $codigo);
    preg_match_all('/#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?)\s*\(/', $sinRespaldo, $m);

    expect($m[0])->toBeEmpty(sprintf(
        'El grupo escribe colores literales (%s): un adoptante no puede cambiarlos sin editar el '.
        'paquete (DESIGN §1).', implode(', ', array_unique($m[0]))
    ));

    expect((bool) preg_match(
        '/:focus(?:-visible)?[^{]*\{[^}]*outline\s*:\s*3px solid var\(--muni-focus/i',
        cssGrupoCasillas('checkbox-group')
    ))->toBeTrue(
        'El grupo no declara el indicador de foco canónico (DESIGN §5): outline de 3px, no '.
        'box-shadow, que dentro de Filament se computa transparente.'
    );

    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($codigo, $prohibida))->toBeFalse(
            "El grupo usa «{$prohibida}», que no existe en los dos majors de Livewire (DESIGN §10)."
        );
    }
});

it('la prop `name` de field queda documentada como aceptada y sin efecto', function () {
    /*
     * Medido con `grep -rn "x-muni::field" ~/Dev` el 2026-09-10: fuera del propio
     * paquete (README, registry.json, INVENTORY.md y el comentario de filter-bar)
     * NO hay un solo consumidor, y ninguno de esos ejemplos pasa `name`.
     *
     * Con cero llamadas que la pasen, emitir un `for` no arregla nada y sí puede
     * romper: el control va DENTRO del <label> —asociación implícita válida— y el
     * HTML dice que si `for` está presente, el control etiquetado es el elemento
     * con ese id y NO el descendiente. Un `for` apuntando a un id que el slot no
     * crea —el ejemplo del README es un <input> crudo sin id— deja al campo sin
     * nombre accesible: convertiría un contrato roto en una falla real de
     * accesibilidad.
     */
    $html = Blade::render('<x-muni::field label="Estado" name="estado"><input name="estado"></x-muni::field>');

    expect((bool) preg_match('/<label\b[^>]*\bfor=/i', $html))->toBeFalse(
        'La envoltura de campo emite un `for` que no puede garantizar: si el slot trae un control '.
        'sin ese id, el <label> se queda SIN control etiquetado y se pierde la asociación implícita '.
        'que hoy funciona.'
    );

    expect((bool) preg_match('/<label\b[^>]*>.*<input\b.*<\/label>/is', $html))->toBeTrue(
        'El control ya no queda anidado dentro del <label>: esa era la única asociación que tenía '.
        'el campo (WCAG 2.2 AA 4.1.2).'
    );

    expect((bool) preg_match('/\bname="estado"/', $html))->toBeTrue(
        'El `name` del control del slot desapareció del HTML.'
    );

    expect(substr_count($html, 'name="estado"'))->toBe(1,
        'La envoltura derrama su prop `name` al HTML: es una prop del componente, no un atributo.'
    );

    // El contrato se documenta en el componente para que nadie la vuelva a
    // «arreglar» a ciegas dentro de seis meses.
    $fuente = mb_strtolower(fuenteGrupoCasillas('field'));

    expect(str_contains($fuente, 'sin efecto'))->toBeTrue(
        'El componente no documenta que la prop `name` se acepta y no hace nada: quien la pase '.
        'seguirá creyendo que ata la etiqueta al control.'
    );

    expect(str_contains($fuente, '21.719') || str_contains($fuente, 'aditivo'))->toBeTrue(
        'Falta el motivo por el que la prop no se quita: el paquete es aditivo y hay nueve '.
        'sistemas consumiendo.'
    );
});
