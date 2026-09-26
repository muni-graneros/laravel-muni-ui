<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| EL CANDADO DE LA CABECERA DE TABLA Y DE LOS DOS VACÍOS
|--------------------------------------------------------------------------
|
| Dos piezas del mismo problema: una pantalla de listado no es «una tabla».
|
| `<x-muni::table-header>` es la franja que va entre el <h1> de la página y la
| tabla: de qué sección es, cuántos resultados hay EN TEXTO, y dónde van el
| buscador, los filtros y las acciones del consumidor. Hoy cada sistema la
| escribe a mano y sale distinta en cada uno.
|
| El contador es lo único que el componente calcula, y es lo que cierra
| WCAG 2.2 AA 4.1.3: si tras filtrar la tabla pasa de 3.412 filas a 120 y el
| único indicio es que la tabla se acortó, quien usa lector de pantalla no se
| entera. Por eso vive en una región viva PERSISTENTE —presente en el DOM
| antes y después del filtro, vacía si todavía no hay cifras—: una región que
| nace junto a su propio texto no se anuncia de forma fiable en NVDA ni en
| VoiceOver.
|
| Y lo que el componente NO hace es igual de importante: no declara
| `role="toolbar"`. Una franja que contiene un campo de texto no puede serlo
| —dentro de un input las flechas, Home y End le pertenecen al cursor, y APG lo
| advierte explícitamente—, y un rol que promete un widget que no existe es peor
| que ningún rol. Tampoco es un <form>: «Derivar seleccionadas» es POST y
| `filter-bar` ya es un <form method=get>, y los formularios no se anidan.
|
| `<x-muni::empty-state>` se amplía de forma ADITIVA para separar dos vacíos que
| para el funcionario son opuestos: «todavía no hay nada» (crear el primero) y
| «el filtro no encontró nada» (limpiar el filtro, y decir qué filtro estaba
| puesto). Tratarlos igual manda a buscar registros que sí existen.
|
| Todo se comprueba con `expect(bool)->toBeTrue('mensaje')`: en Pest el segundo
| argumento de `toContain()` es otra cadena a buscar, no un mensaje, y la
| aserción se desactiva sin avisar.
*/

/** Ruta de un componente del paquete. */
function rutaComponente(string $nombre): string
{
    return __DIR__.'/../resources/views/components/'.$nombre.'.blade.php';
}

/** El fuente crudo de un componente, o cadena vacía si todavía no existe. */
function fuenteComponente(string $nombre): string
{
    $ruta = rutaComponente($nombre);

    return file_exists($ruta) ? (string) file_get_contents($ruta) : '';
}

/** El HTML servido de la cabecera de tabla. */
function cabeceraHtml(string $atributos = '', string $contenido = ''): string
{
    return Blade::render("<x-muni::table-header {$atributos}>{$contenido}</x-muni::table-header>");
}

/** El HTML servido del estado vacío. */
function vacioHtml(string $atributos = '', string $contenido = ''): string
{
    return Blade::render("<x-muni::empty-state {$atributos}>{$contenido}</x-muni::empty-state>");
}

/** El texto que un ojo humano lee en ese HTML, sin etiquetas ni <style>. */
function textoVisible(string $html): string
{
    $sinEstilos = (string) preg_replace('#<(script|style)\b.*?</\1>#s', ' ', $html);
    $texto = html_entity_decode(strip_tags($sinEstilos), ENT_QUOTES, 'UTF-8');

    return trim((string) preg_replace('/\s+/u', ' ', $texto));
}

/** El texto de la primera región viva `role="status"` del HTML. */
function textoRegionViva(string $html): ?string
{
    if (! preg_match('#<([a-z]+)\b[^>]*role="status"[^>]*>(.*?)</\1>#s', $html, $m)) {
        return null;
    }

    return textoVisible($m[2]);
}

/*
|--------------------------------------------------------------------------
| table-header
|--------------------------------------------------------------------------
*/

it('existe como componente del paquete y se renderiza', function () {
    expect(file_exists(rutaComponente('table-header')))->toBeTrue(
        'No existe resources/views/components/table-header.blade.php: la franja entre el título de '.
        'la pantalla y la tabla la sigue escribiendo a mano cada sistema.'
    );

    expect(trim(cabeceraHtml('title="Patentes"')))->not->toBe(
        '',
        'La cabecera se renderiza vacía.'
    );
});

it('escribe el contador de resultados en texto, no solo en la altura de la tabla', function () {
    $html = cabeceraHtml('title="Patentes" :total="3412" unit="patentes"');
    $texto = textoVisible($html);

    expect(str_contains($texto, '3.412'))->toBeTrue(
        'El total no aparece con separador de miles chileno («3.412»): '.$texto
    );

    expect(str_contains($texto, 'patentes'))->toBeTrue(
        'El contador no nombra la unidad, así que dice «3.412» de qué cosa: '.$texto
    );
});

it('distingue el subconjunto filtrado del total', function () {
    $texto = textoVisible(cabeceraHtml('title="Patentes" :total="3412" :filtered="120" unit="patentes"'));

    expect((bool) preg_match('/\b120\b.*\bde\b.*3\.412/u', $texto))->toBeTrue(
        'Con filtro puesto el contador debe decir «120 de 3.412»: si solo dice 120, el funcionario '.
        'no sabe si el sistema perdió registros o si es su propio filtro. Texto: '.$texto
    );

    // Sin filtro que estreche nada, «3.412 de 3.412» es ruido.
    $sinFiltro = textoVisible(cabeceraHtml('title="Patentes" :total="3412" :filtered="3412" unit="patentes"'));

    expect(str_contains($sinFiltro, 'de 3.412'))->toBeFalse(
        'Cuando el filtro no descarta nada el contador sigue diciendo «3.412 de 3.412»: '.$sinFiltro
    );
});

it('pone el contador en una región viva persistente, presente aunque no haya cifras', function () {
    $conCifras = cabeceraHtml('title="Patentes" :total="3412" :filtered="120" unit="patentes"');

    $region = textoRegionViva($conCifras);

    expect($region)->not->toBeNull(
        'El contador no vive en ningún role="status": el cambio de 3.412 a 120 tras filtrar es '.
        'invisible para un lector de pantalla (WCAG 2.2 AA 4.1.3).'
    );

    expect(str_contains((string) $region, '120'))->toBeTrue(
        'La región viva existe pero el contador está fuera de ella. Contenido de la región: '.$region
    );

    expect((bool) preg_match('/role="status"[^>]*aria-live="polite"|aria-live="polite"[^>]*role="status"/', $conCifras))->toBeTrue(
        'La región del contador no declara aria-live="polite".'
    );

    // Persistente: el nodo tiene que estar en el DOM ANTES de que haya cifras.
    // Una región viva que nace en el mismo repintado que trae su texto no se
    // anuncia de forma fiable en NVDA, JAWS ni VoiceOver.
    expect(str_contains(cabeceraHtml('title="Patentes"'), 'role="status"'))->toBeTrue(
        'Sin cifras la región viva no se emite, así que en la carga inicial no existe y aparece '.
        'recién con el primer filtro: ese es justo el caso que los lectores no anuncian.'
    );
});

it('rinde las ranuras de buscador, filtros y acciones, y también el slot por defecto', function () {
    $html = cabeceraHtml(
        'title="Patentes"',
        '<x-slot:search><input id="q-patentes"></x-slot:search>'
        .'<x-slot:filters><button type="button">Morosas</button></x-slot:filters>'
        .'<x-slot:actions><button type="button">Nueva patente</button></x-slot:actions>'
        .'<span data-chip>Filtro: rol 4501</span>'
    );

    foreach ([
        'q-patentes' => 'la ranura del buscador',
        'Morosas' => 'la ranura de filtros',
        'Nueva patente' => 'la ranura de acciones',
        'data-chip' => 'el slot por defecto (los chips de filtros aplicados)',
    ] as $aguja => $que) {
        expect(str_contains($html, $aguja))->toBeTrue(
            "No se rinde {$que}: el contenido del consumidor se descarta en silencio, que es el ".
            'mismo defecto que empty-state tenía con su slot.'
        );
    }
});

it('no declara un rol de widget que no implementa', function () {
    $html = cabeceraHtml(
        'title="Patentes" :total="3412"',
        '<x-slot:search><input id="q"></x-slot:search><x-slot:filters><button type="button">Todas</button></x-slot:filters>'
    );

    foreach (['toolbar', 'menubar', 'tablist', 'grid', 'listbox', 'radiogroup', 'group'] as $rol) {
        expect((bool) preg_match('/role="'.$rol.'"/', $html))->toBeFalse(
            "La franja declara role=\"{$rol}\" sin implementar su teclado. El caso de toolbar es el ".
            'peor: contiene un campo de texto, y dentro de un input las flechas, Home y End le '.
            'pertenecen al cursor. Un rol que promete un widget que no existe es peor que ninguno.'
        );
    }

    expect((bool) preg_match('/tabindex="-1"/', $html))->toBeFalse(
        'Hay un tabindex="-1" en la franja: eso es el tabindex móvil de un toolbar, que aquí no se '.
        'implementa. El orden de Tab es el nativo.'
    );

    $fuente = fuenteComponente('table-header');

    expect((bool) preg_match('/(keydown|ArrowRight|ArrowLeft)/i', $fuente))->toBeFalse(
        'El componente maneja teclas por su cuenta: o implementa el patrón completo y declara el '.
        'rol, o no hace ninguna de las dos cosas. A medias es lo único que no vale.'
    );
});

it('es composición: no emite tabla propia ni un <form> que choque con el del host', function () {
    $html = cabeceraHtml('title="Patentes" :total="3412"', '<x-slot:actions><button type="button">Derivar</button></x-slot:actions>');

    foreach (['<table', '<thead', '<tbody'] as $etiqueta) {
        expect(str_contains($html, $etiqueta))->toBeFalse(
            "La cabecera emite «{$etiqueta}»: se acopló a una tabla concreta y deja de servir ".
            'indistintamente sobre sortable-table y sobre data-table.'
        );
    }

    expect(str_contains($html, '<form'))->toBeFalse(
        'La cabecera es un <form>. filter-bar ya es un <form method=get> y las acciones en lote son '.
        'POST: los formularios no se anidan, así que la franja no puede traer el suyo.'
    );

    // Se apila sobre cualquiera de las dos tablas sin romper el HTML de ninguna.
    $conDataTable = Blade::render(
        '<x-muni::table-header title="Patentes" :total="2" /><x-muni::data-table :columns="[\'Rol\']"><tr data-muni-row><td>4501-2</td></tr></x-muni::data-table>'
    );

    expect(str_contains($conDataTable, '4501-2') && str_contains($conDataTable, 'Patentes'))->toBeTrue(
        'La cabecera y data-table no conviven en la misma página.'
    );

    $conSortable = Blade::render(
        '<x-muni::table-header title="Patentes" :total="1" /><x-muni::sortable-table :columns="$c" :rows="[]" searchable />',
        ['c' => [['key' => 'rol', 'label' => 'Rol']]]
    );

    expect(str_contains($conSortable, 'muni-st'))->toBeTrue(
        'La cabecera y sortable-table no conviven en la misma página.'
    );
});

it('no se roba el <h1> de la página', function () {
    $html = cabeceraHtml('title="Patentes morosas"');

    expect((bool) preg_match('/<h1\b/', $html))->toBeFalse(
        'La cabecera emite un <h1>. El <h1> es de page-header: dos en una página rompen el esquema '.
        'de encabezados con el que se navega por teclado y con lector.'
    );

    expect((bool) preg_match('/<h2\b[^>]*>\s*Patentes morosas/u', $html))->toBeTrue(
        'El título de la sección no sale como <h2>: sin encabezado, la franja no se puede saltar '.
        'con el navegador de encabezados del lector.'
    );

    expect((bool) preg_match('/<h3\b[^>]*>\s*Patentes morosas/u', cabeceraHtml('title="Patentes morosas" :level="3"')))->toBeTrue(
        'La prop level no manda sobre el nivel del encabezado: en una página con secciones anidadas '.
        'el nivel correcto no siempre es 2.'
    );
});

it('apila los controles en pantalla angosta sin desbordar en horizontal', function () {
    $html = cabeceraHtml('title="Patentes" :total="3412"', '<x-slot:search><input id="q"></x-slot:search>');
    $fuente = fuenteComponente('table-header');
    $todo = $html.$fuente;

    expect((bool) preg_match('/flex-wrap\s*:\s*wrap/', $todo))->toBeTrue(
        'La franja no envuelve: en un teléfono el buscador y las acciones se salen por el costado.'
    );

    expect((bool) preg_match('/min-width\s*:\s*0/', $todo))->toBeTrue(
        'Ningún elemento flexible declara min-width:0. El tamaño mínimo automático de un ítem flex '.
        'es su contenido, así que un buscador largo empuja la franja y aparece el scroll horizontal.'
    );
});

/*
|--------------------------------------------------------------------------
| empty-state: los dos vacíos
|--------------------------------------------------------------------------
*/

it('empty-state distingue «todavía no hay nada» de «el filtro no encontró nada»', function () {
    $sinDatos = textoVisible(vacioHtml('mode="no-data"'));
    $sinCoincidencias = textoVisible(vacioHtml('mode="no-matches"'));

    expect($sinDatos)->not->toBe(
        $sinCoincidencias,
        'Los dos modos dicen exactamente lo mismo. Para el funcionario son problemas opuestos: uno '.
        'se resuelve creando el primer registro y el otro limpiando el filtro.'
    );

    // El ícono es aria-hidden y el color no puede ser el único portador: la
    // diferencia tiene que estar en el TEXTO (WCAG 2.2 AA 1.4.1).
    $iconoSinDatos = (string) (preg_match('#<svg.*?</svg>#s', vacioHtml('mode="no-data"'), $a) ? $a[0] : '');
    $iconoSinCoincidencias = (string) (preg_match('#<svg.*?</svg>#s', vacioHtml('mode="no-matches"'), $b) ? $b[0] : '');

    expect($iconoSinDatos)->not->toBe($iconoSinCoincidencias, 'Los dos modos usan el mismo dibujo por defecto.');

    expect(trim($sinDatos) !== '' && trim($sinCoincidencias) !== '')->toBeTrue('Algún modo salió sin texto.');
});

it('empty-state dice qué filtro estaba puesto cuando no hubo coincidencias', function () {
    $texto = textoVisible(vacioHtml('mode="no-matches" filter="RUT 12.345.678-9"'));

    expect(str_contains($texto, '12.345.678-9'))->toBeTrue(
        'El criterio del filtro no aparece por ninguna parte. «No hay solicitudes ARCOP» y «no hay '.
        'solicitudes ARCOP para el RUT 12.345.678-9» son dos mensajes distintos, y solo el segundo '.
        'le dice al funcionario qué tiene que borrar. Texto: '.$texto
    );

    // El filtro es dato del vecino: va escapado, nunca como HTML crudo.
    $inyeccion = vacioHtml('mode="no-matches" filter="<script>alert(1)</script>"');

    expect(str_contains($inyeccion, '<script>alert(1)</script>'))->toBeFalse(
        'El criterio del filtro se imprime sin escapar: lo que el vecino escribió en el buscador '.
        'termina ejecutándose.'
    );
});

it('empty-state ofrece la acción correcta en cada caso', function () {
    $crear = vacioHtml('mode="no-data" action-href="/patentes/nueva"');
    $limpiar = vacioHtml('mode="no-matches" action-href="/patentes"');

    expect((bool) preg_match('/<a\b[^>]*href="\/patentes\/nueva"[^>]*>\s*Crear/u', $crear))->toBeTrue(
        'Sin datos la salida es crear el primer registro, y no hay ningún enlace que lo ofrezca: '.$crear
    );

    expect((bool) preg_match('/<a\b[^>]*href="\/patentes"[^>]*>\s*Limpiar/u', $limpiar))->toBeTrue(
        'Sin coincidencias la salida es limpiar el filtro, y no hay ningún enlace que lo ofrezca: '.$limpiar
    );

    expect(str_contains(textoVisible(vacioHtml('mode="no-data" action-href="/x" action-label="Ingresar patente"')), 'Ingresar patente'))
        ->toBeTrue('La etiqueta de la acción no se puede cambiar: el texto por defecto manda siempre.');

    // Sin destino no se dibuja un botón muerto.
    expect((bool) preg_match('/<a\b/', vacioHtml('mode="no-data"')))->toBeFalse(
        'Se dibuja la acción aunque el consumidor no haya dado a dónde va: es un enlace que no lleva '.
        'a ninguna parte.'
    );

    expect((bool) preg_match('/outline\s*:\s*3px solid var\(--muni-focus, var\(--muni-accent, #767676\)\)/', fuenteComponente('empty-state')))->toBeTrue(
        'La acción no declara el indicador de foco del contrato (outline de 3px, no box-shadow: el '.
        'anillo se pierde dentro de Filament).'
    );
});

it('empty-state sigue siendo el de antes: ninguna prop se renombró y ahora el slot se rinde', function () {
    $html = vacioHtml(
        'title="Sin patentes" description="Todavía no se ingresa ninguna." icon="<svg data-mio></svg>"',
        '<p data-slot>Detalle adicional</p><x-slot:actions><button type="button">Crear</button></x-slot:actions>'
    );

    foreach (['Sin patentes', 'Todavía no se ingresa ninguna.', 'data-mio', 'Crear'] as $aguja) {
        expect(str_contains($html, $aguja))->toBeTrue("Se perdió «{$aguja}»: la ampliación no fue aditiva.");
    }

    expect(str_contains($html, 'data-slot'))->toBeTrue(
        'El contenido del slot por defecto se sigue descartando en silencio. Es retrocompatible '.
        'rendirlo porque hoy nadie puede depender de un contenido que nunca se imprimió.'
    );

    // Sin modo declarado, EXACTAMENTE lo de antes. La aserción es contra el texto
    // literal que el componente rendía el día que se amplió, no contra uno de los
    // modos nuevos: si mañana se cambia el título por defecto de «no-data», esto
    // tiene que seguir diciendo «Sin resultados».
    expect(textoVisible(vacioHtml()))->toBe(
        'Sin resultados',
        'El modo por defecto cambió de comportamiento: los siete sistemas que ya usan empty-state sin '.
        'modo verían otro texto sin haber tocado nada.'
    );

    expect(textoVisible(vacioHtml('mode="no-data"')))->not->toBe(
        'Sin resultados',
        '«no-data» rinde el mismo título genérico de siempre, así que declarar el modo no cambia nada.'
    );
});

it('empty-state no finge una región viva que no funciona', function () {
    expect(str_contains(vacioHtml('mode="no-matches" filter="RUT 1-9"'), 'role="status"'))->toBeFalse(
        'empty-state declara role="status" por defecto. Es un arreglo de mentira: Livewire inserta '.
        'este nodo en el mismo repintado que trae su texto, y una región viva que nace junto a su '.
        'contenido no se anuncia en NVDA, JAWS ni VoiceOver. El anuncio del cambio va en un nodo '.
        'que ya estaba montado, como el contador de table-header.'
    );

    expect(str_contains(vacioHtml('mode="no-matches" announce'), 'role="status"'))->toBeTrue(
        'No hay forma de pedir la región viva para el host que SÍ mantiene el nodo montado: hace '.
        'falta la prop announce, apagada por defecto y documentada con su trampa.'
    );
});

/*
|--------------------------------------------------------------------------
| El contrato del paquete, en las dos piezas
|--------------------------------------------------------------------------
*/

it('respetan el contrato: sin colores literales, sin uniqid, con el <style> en @once', function (string $componente) {
    $fuente = fuenteComponente($componente);

    expect($fuente)->not->toBe('', "No existe el componente «{$componente}».");

    // Sin comentarios: nombrar uniqid() al explicar por qué no se usa no es un defecto.
    $codigo = (string) preg_replace(['#/\*.*?\*/#s', '#\{\{--.*?--\}\}#s'], '', $fuente);

    expect(str_contains($codigo, 'uniqid('))->toBeFalse(
        'Arma un id con uniqid(): cambia en cada render, rompe el <label for> y ensucia el diffing '.
        'de Livewire.'
    );

    // El único literal admitido es el tercer respaldo del foco, que es del contrato.
    $sinRespaldoDeFoco = str_replace('#767676', '', $codigo);

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $sinRespaldoDeFoco, $colores);

    expect($colores[0] ?? [])->toBe(
        [],
        'Escribe colores literales, así que ningún municipio puede cambiarlos redefiniendo tokens: '
        .implode(' | ', $colores[0] ?? [])
    );

    if (str_contains($codigo, '<style>')) {
        expect((bool) preg_match('#@once\s*(?:\R|\s)*<style>#', $codigo))->toBeTrue(
            'El bloque <style> no está dentro de @once: se repite una vez por cada instancia en la página.'
        );
    }

    // Livewire 3 y Livewire 4 a la vez: nada exclusivo de un major.
    foreach (['@island', 'wire:show', 'wire:sort', '#[Transition]'] as $prohibida) {
        expect(str_contains($codigo, $prohibida))->toBeFalse(
            "Usa «{$prohibida}», que no existe en los dos majors de Livewire que el paquete soporta."
        );
    }
})->with(['table-header', 'empty-state']);
