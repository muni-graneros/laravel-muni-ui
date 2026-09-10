<?php

use Illuminate\Support\Facades\Blade;

/*
 * Genera la vitrina: una página por tema con los componentes REALES renderizados.
 *
 * Por qué es una prueba y no un script suelto: es el único sitio del paquete donde
 * Blade está arrancado. Y por qué existe: hasta ahora la reja de accesibilidad
 * (`npm run a11y`) solo podía medir las demos de `demo/`, que son HTML escrito a
 * mano y que ya se comprobó que se desviaron de los tokens del paquete. Es decir,
 * medía una copia, no los componentes. Esto cierra esa brecha: lo que se abre en el
 * navegador es lo que el paquete emite de verdad.
 *
 * La salida va a `build/vitrina/`, que está ignorada por git. No es un artefacto
 * que se publique: es el sujeto de la medición.
 *
 *     ./vendor/bin/pest --filter=GeneraVitrina
 *     npm run a11y -- build/vitrina/claro.html build/vitrina/oscuro.html
 */

/**
 * Un ejemplo por componente. La clave es el nombre; el valor, el Blade a renderizar.
 * No están los 55: están los que tienen superficie visible y estado que medir.
 * Los armazones se excluyen porque emiten su propio <html> y no se pueden anidar.
 */
function ejemplosDeVitrina(): array
{
    return [
        'skip-link' => '<x-muni::skip-link />',
        'page-header' => '<x-muni::page-header title="Patentes morosas" subtitle="3.412 contribuyentes con deuda vigente" />',
        'stat' => '<x-muni::stat :value="128" label="Ingresadas hoy" :delta="12" delta-dir="up" />',
        'kpi' => '<x-muni::kpi :value="3412" label="Morosas" tone="danger" hint="al 6 de septiembre" />',
        'badge' => '<x-muni::badge tone="danger">Vencida</x-muni::badge> <x-muni::badge tone="ok">Al día</x-muni::badge> <x-muni::badge tone="warn">Por vencer</x-muni::badge>',
        'button' => '<x-muni::button>Guardar</x-muni::button> <x-muni::button variant="ghost">Cancelar</x-muni::button>',
        'alert' => '<x-muni::alert tone="warn" title="217 patentes por vencer">Vencen dentro de 30 días.</x-muni::alert>',
        'input' => '<x-muni::input label="RUT del titular" name="rut" hint="Formato 12.345.678-9" error="El dígito verificador no corresponde." />',
        'select' => '<x-muni::select label="Tipo de trámite" name="tramite" :options="[\'a\' => \'Licencia clase B\', \'b\' => \'Renovación\']" hint="Elige el trámite" />',
        'textarea' => '<x-muni::textarea label="Descripción del requerimiento" name="detalle" hint="Cuenta qué pasó" :maxlength="500" />',
        'checkbox' => '<x-muni::checkbox label="Acepto el tratamiento de mis datos" name="consentimiento" description="Ley 21.719, base de licitud: consentimiento." />',
        'switch' => '<x-muni::switch label="Notificarme por correo" name="avisos" description="Cuando cambie el estado" />',
        'segmented' => '<x-muni::segmented name="estado" label="Filtrar por estado" :options="[\'todos\' => \'Todos\', \'pend\' => \'Pendientes\', \'cerr\' => \'Cerrados\']" value="todos" />',
        'file-dropzone' => '<x-muni::file-dropzone name="informe" label="Adjunta el informe médico" />',
        'error-summary' => '<x-muni::error-summary :errors="[\'rut\' => \'El RUT no es válido.\', \'fecha\' => \'La fecha es obligatoria.\']" />',
        'announcer' => '<x-muni::announcer />',
        'empty-state' => '<x-muni::empty-state title="Sin resultados" description="Ningún contribuyente coincide con el filtro." />',
        'progress' => '<x-muni::progress :value="64" label="Avance del trámite" />',
        // Varios tonos a la vez porque la etiqueta de estado es TEXTO visible y hay
        // un par de colores que medir por tono. Un ítem con `current`, otro con
        // `actor` y otro con `detail`: el <summary> es una parada de tabulación
        // nueva, y sin un ítem que lo emita la reja no mide ni su foco ni su
        // contraste. El <details> arranca colapsado, así que lo de adentro no se
        // mide acá: es lo mismo que ve el funcionario al abrir la pantalla.
        'timeline' => '<x-muni::timeline :items="['
            .'[\'title\' => \'Ingresada\', \'time\' => \'10:04\', \'datetime\' => \'2026-09-08T10:04:00-03:00\', \'tone\' => \'info\', \'actor\' => \'Ventanilla Única\'],'
            .'[\'title\' => \'Derivada a Obras\', \'time\' => \'11:20\', \'tone\' => \'ok\', \'description\' => \'Con el certificado de dominio adjunto.\'],'
            .'[\'title\' => \'Observada por Obras\', \'time\' => \'15:47\', \'tone\' => \'warn\', \'actor\' => \'C. Bugueño\','
            .' \'detail\' => \'Domicilio: Calle Uno 100 → Calle Dos 200\'],'
            .'[\'title\' => \'Rechazada por falta de antecedentes\', \'time\' => \'09:12\', \'tone\' => \'danger\'],'
            .'[\'title\' => \'En revisión del director\', \'time\' => \'12:30\', \'tone\' => \'accent\', \'current\' => true],'
            .'[\'title\' => \'Anulada por duplicado\', \'time\' => \'12:45\', \'tone\' => \'muted\'],'
            .']" />',
        'breadcrumb' => '<x-muni::breadcrumb :items="[[\'label\' => \'Inicio\', \'url\' => \'#\'], [\'label\' => \'Patentes\']]" />',
        // Con `url` los números son enlaces de verdad. Sin él salen como texto, y la
        // reja mediría un componente que nadie usa así en producción.
        'pagination' => '<x-muni::pagination :current="3" :total="170" :url="fn (int $p) => \'?pagina=\'.$p" />',
        'skeleton' => '<x-muni::skeleton width="60%" /> <x-muni::skeleton height="80px" />',
        'avatar' => '<x-muni::avatar name="Cesar Bugueño" />',
        'tabs' => '<x-muni::tabs :tabs="[\'Solicitante\', \'Documentos\']" label="Secciones de la solicitud">'
            .'<x-muni::tab-panel :index="0">Datos del solicitante.</x-muni::tab-panel>'
            .'<x-muni::tab-panel :index="1">Documentos adjuntos.</x-muni::tab-panel></x-muni::tabs>',
        'table-header' => '<x-muni::table-header title="Patentes morosas" :total="3412" :filtered="120" unit="patentes" />',
        'rut-input' => '<x-muni::rut-input label="RUT del titular" name="rut" />',
        'date-input' => '<x-muni::date-input label="Vence el" name="vence" />',
        'sortable-table' => '<x-muni::sortable-table searchable caption="Patentes morosas"'
            .' :columns="[[\'key\' => \'rut\', \'label\' => \'RUT\'], [\'key\' => \'monto\', \'label\' => \'Monto\']]"'
            .' :rows="[[\'rut\' => \'12.345.678-9\', \'monto\' => \'520.000\', \'_tone\' => \'danger\'], [\'rut\' => \'9.876.543-2\', \'monto\' => \'80.000\']]" />',
        // Los dos que siguen van AL FINAL a propósito. El reparto par/impar de la
        // rejilla decide qué tarjeta cae sobre --muni-surface-3, y en la posición
        // par está `input`, que es donde el mensaje de error falló contraste.
        // Meterlos en medio correría ese reparto y dejaría de medirse justo el
        // caso por el que se puso la regla. Al final, además, cae uno en cada
        // superficie: diff-campos sobre la superficie normal y hoja sobre la 3.
        //
        // Los tres estados juntos —y el cuarto, «sin cambios»— porque cada uno
        // pinta su propio par fg/bg en la etiqueta y en el <ins>/<del>: con una
        // sola fila se mediría un tono de cuatro. Los valores son strings ya
        // redactados, que es lo único que el componente acepta.
        'diff-campos' => '<x-muni::diff-campos caption="Cambios en la solicitud 4821" :changes="['
            .'[\'field\' => \'telefono\', \'label\' => \'Teléfono\', \'after\' => \'+56 9 8765 4321\', \'mono\' => true],'
            .'[\'field\' => \'domicilio\', \'label\' => \'Domicilio\', \'before\' => \'Calle Uno 100\', \'after\' => \'Calle Dos 200\'],'
            .'[\'field\' => \'correo\', \'label\' => \'Correo\', \'before\' => \'antiguo@ejemplo.cl\'],'
            .'[\'field\' => \'rut\', \'label\' => \'RUT\', \'before\' => \'12.345.678-9\', \'after\' => \'12.345.678-9\', \'mono\' => true],'
            .']">Registro conservado 5 años. La exportación queda en la bitácora.</x-muni::diff-campos>',
        // Con folio, leyenda de verificación y las tres ranuras: sin ellas la
        // mitad del componente —el pie, los recuadros de datos y la firma— no
        // llega al DOM y la reja no mide nada de eso.
        'hoja' => '<x-muni::hoja id="vitrina-hoja" unit="Dirección de Tránsito"'
            .' type="Acta de fiscalización" folio="A-2026-4821" date="8 de septiembre de 2026"'
            .' verification="Verifica el folio en la Oficina de Partes, Manuel Rodríguez 545.">'
            .'<x-slot:emisor><strong>Fiscalizador</strong><br>Juan Pérez · Inspección Municipal</x-slot:emisor>'
            .'<x-slot:titular><strong>Titular</strong><br>Ana Soto · Patente comercial 1.204</x-slot:titular>'
            .'<p>Se constata el funcionamiento del local fuera del horario autorizado '
            .'en el decreto alcaldicio 118/2026. Se levanta acta y se cita al Juzgado de Policía Local.</p>'
            .'<x-slot:firma><span>Firma del fiscalizador</span></x-slot:firma>'
            .'</x-muni::hoja>',
        // De los tres que siguen, la reja solo alcanza la parte VISIBLE en reposo:
        // el panel del popover, la burbuja del tooltip y la lista del combobox
        // nacen cerrados, y el script salta todo nodo de alto 0. Aun así se miden
        // acá el disparador, el campo, la etiqueta y la ayuda, que es donde vive
        // el texto que el funcionario lee sin abrir nada. Lo cerrado sigue siendo
        // agujero conocido de la reja, igual que el <details> de timeline.
        'combobox' => '<x-muni::combobox name="vitrina_titular" label="Titular de la solicitud"'
            .' selectedLabel="Ana Soto Miranda" value="4821"'
            .' hint="Escribe el RUT o el nombre; se muestran hasta 20 resultados."'
            .' :options="[[\'value\' => \'4821\', \'label\' => \'Ana Soto Miranda\', \'hint\' => \'12.345.678-9\']]" />',
        'popover' => '<x-muni::popover label="Filtros de la bandeja">'
            .'<form method="get"><p>Estado y fecha del requerimiento.</p></form>'
            .'</x-muni::popover>',
        'tooltip' => '<x-muni::tooltip text="Anular el giro de la patente" id="vitrina-tt">'
            .'<button type="button" class="muni-btn" aria-label="Anular el giro de la patente"'
            .' aria-describedby="vitrina-tt">Anular</button>'
            .'</x-muni::tooltip>',
    ];
}

function armaVitrina(string $tema): string
{
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui.css');
    $piezas = '';

    foreach (ejemplosDeVitrina() as $nombre => $blade) {
        $html = Blade::render($blade);
        $piezas .= '<section class="v-pieza"><h2 class="v-nombre">&lt;x-muni::'.$nombre.'&gt;</h2>'
            .'<div class="v-cuerpo">'.$html.'</div></section>';
    }

    // El contenedor fija el tema y las superficies con los MISMOS tokens que usan
    // los componentes: medir sobre un fondo inventado no diría nada.
    return '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($tema === 'dark' ? ' class="dark"' : '').'>'
        .'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>Vitrina de laravel-muni-ui — tema '.($tema === 'dark' ? 'oscuro' : 'claro').'</title>'
        .'<style>'.$css.'
            body { margin:0; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); }
            .v-cab { padding:24px; border-bottom:1px solid var(--muni-border); }
            .v-cab h1 { margin:0; font-size:20px; }
            .v-grid { display:grid; gap:20px; padding:24px; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); }
            .v-pieza { background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-lg); padding:16px; }
            .v-nombre { margin:0 0 12px; font-family:var(--muni-font-mono); font-size:12px; color:var(--muni-muted); font-weight:600; }
            /* Se mide también sobre la superficie secundaria, que es donde el
               mensaje de error fallaba el contraste antes de llevar fondo propio. */
            .v-pieza:nth-child(even) .v-cuerpo { background:var(--muni-surface-3); padding:12px; border-radius:var(--muni-radius); }
        </style></head><body>'
        .'<header class="v-cab"><h1>Vitrina de componentes — tema '.($tema === 'dark' ? 'oscuro' : 'claro').'</h1></header>'
        .'<main id="muni-contenido" tabindex="-1"><div class="v-grid">'.$piezas.'</div></main>'
        .'</body></html>';
}

it('genera la vitrina con los componentes reales, en los dos temas', function () {
    $destino = __DIR__.'/../build/vitrina';

    if (! is_dir($destino)) {
        mkdir($destino, 0o775, true);
    }

    foreach (['light' => 'claro', 'dark' => 'oscuro'] as $tema => $archivo) {
        $html = armaVitrina($tema);

        expect($html)->toContain('x-muni::stat')
            ->and(strlen($html))->toBeGreaterThan(20000, 'La vitrina salió sospechosamente corta.');

        file_put_contents("{$destino}/{$archivo}.html", $html);
    }

    expect(file_exists($destino.'/claro.html'))->toBeTrue()
        ->and(file_exists($destino.'/oscuro.html'))->toBeTrue();
});
