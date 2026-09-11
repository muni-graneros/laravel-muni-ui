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
 * La vitrina CARGA ALPINE 3 y abre a mano los estados que nacen cerrados. Sin eso no
 * emitía un solo <script>: nada interactivo hidrataba, `sortable-table` no llegaba
 * siquiera a tener cabecera ni filas —viven dentro de <template x-for>— y la reja
 * medía 165 textos. Con Alpine e hidratación mide 181. De dónde sale Alpine y qué se
 * abre está en `fuenteDeAlpine()` y `abridorDeVitrina()`, acá abajo.
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
        // contraste. El <details> nace colapsado, pero el abridor de la vitrina lo
        // abre antes de medir: el texto del cambio («Domicilio: … → …») es lo que
        // el funcionario lee cuando despliega, y hasta ahora no se medía nunca.
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
        // Los tres nacen cerrados, y el medidor salta todo nodo de alto 0: hasta que
        // la vitrina cargó Alpine, de estos solo se medía el disparador, el campo,
        // la etiqueta y la ayuda. Ahora el abridor (ver `abridorDeVitrina`) abre la
        // lista del combobox —con la primera opción resaltada—, la burbuja del
        // tooltip y el panel del popover ANTES de que la reja mida, y si alguno se
        // queda de alto 0 la reja FALLA en vez de dar verde midiendo de menos.
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

/**
 * El código de Alpine 3 que se hornea en la vitrina, o null si no hay copia en disco.
 *
 * Por qué hace falta: los componentes del paquete dan por sentado que Alpine viaja
 * dentro del bundle de Livewire, así que el paquete no publica ninguno. Sin él la
 * vitrina no emite un solo <script>: la lista del combobox, la burbuja del tooltip,
 * el panel del popover y —esto es lo peor— TODA la cabecera y el cuerpo de
 * `sortable-table`, que viven dentro de <template x-for>, nunca llegan al DOM. La
 * reja medía lo que el funcionario ve antes de tocar nada, y solo eso.
 *
 * De dónde sale, en el mismo orden que `scripts/a11y-check.py` resuelve axe-core:
 *   1. $MUNI_ALPINE_JS      (una ruta explícita, como el `--axe` de la reja)
 *   2. node_modules/        (`npm install`; alpinejs es dependencia de desarrollo)
 *   3. scripts/.cache/      (copia cacheada, ignorada por git como la de axe)
 *
 * Nunca de un CDN: la reja corre sin red y una vitrina que dependa de internet deja
 * de ser un candado en cuanto se cae la conexión. Si no hay copia, la vitrina se
 * genera igual pero SIN hidratar y se dice en voz alta: es preferible a un fallo de
 * la suite en una máquina que no corrió `npm install`. Quien pierde de verdad es la
 * reja, y por eso `a11y-check.py` marca FALLA si la página pedía Alpine y no llegó.
 */
function fuenteDeAlpine(): ?string
{
    $candidatos = [];

    if ($ruta = getenv('MUNI_ALPINE_JS')) {
        $candidatos[] = $ruta;
    }

    $candidatos[] = __DIR__.'/../node_modules/alpinejs/dist/cdn.min.js';

    foreach (glob(__DIR__.'/../scripts/.cache/alpine-*.min.js') ?: [] as $cacheada) {
        $candidatos[] = $cacheada;
    }

    foreach ($candidatos as $candidato) {
        if (is_file($candidato)) {
            return (string) file_get_contents($candidato);
        }
    }

    return null;
}

/**
 * El abridor: hidrata no basta, hay que ABRIR.
 *
 * Todo lo interactivo del paquete nace cerrado, y el medidor de contraste salta
 * cualquier nodo de alto 0. Con Alpine y sin esto se ganaría `sortable-table`
 * entero y nada más. Así que después de `alpine:initialized` se abre a mano lo que
 * el funcionario abre con el ratón, y se deja abierto mientras la reja mide.
 *
 * Se abre por la API pública del componente (`Alpine.$data(...)` y los métodos que
 * el propio Blade declara), no manoseando clases ni estilos: si mañana el
 * componente cambia de manera de abrirse, esto se rompe en vez de mentir.
 *
 * Además deja constancia en `window.__vitrinaEstado` de qué quedó abierto y con qué
 * alto, y solo entonces marca `data-vitrina-lista="1"` en el <html>. Ese es el
 * apretón de manos que `a11y-check.py` espera antes de medir: sin él la reja
 * mediría a mitad de la hidratación y el resultado dependería del reloj.
 */
function abridorDeVitrina(): string
{
    return <<<'JS'
    (() => {
        const estado = { alpine: null, xdata: 0, abiertos: [], fallos: [] };

        const anota = (nombre, el) => {
            const alto = el ? Math.round(el.getBoundingClientRect().height) : 0;
            if (alto > 0) { estado.abiertos.push(nombre + ' ' + alto + 'px'); }
            else { estado.fallos.push(nombre + ': quedó en 0px, no se está midiendo'); }
        };

        const conCuidado = (nombre, fn) => {
            try { fn(); } catch (error) { estado.fallos.push(nombre + ': ' + error.message); }
        };

        const abre = () => {
            estado.alpine = (window.Alpine && window.Alpine.version) || null;
            estado.xdata = document.querySelectorAll('[x-data]').length;

            /* La lista del combobox, y con la primera opción resaltada: el fondo del
               resaltado y el aria-activedescendant son estado propio, y nunca se han
               medido porque solo existen con la lista abierta. */
            conCuidado('combobox', () => {
                const combo = document.querySelector('.muni-combo');
                if (! combo) { return; }
                const datos = window.Alpine.$data(combo);
                datos.abrir();
                datos.activo = 0;
            });

            /* La burbuja del tooltip: la que salía como una tira vertical de 25x367
               con las palabras partidas letra a letra. */
            conCuidado('tooltip', () => {
                const tt = document.querySelector('.muni-tt');
                if (tt) { window.Alpine.$data(tt).abrir(); }
            });

            /* El popover lleva el estado en el atributo NATIVO, no en Alpine: se abre
               como lo abre el navegador y Alpine sincroniza el aria-expanded solo. */
            conCuidado('popover', () => {
                const panel = document.querySelector('.muni-pop__panel');
                if (panel && typeof panel.showPopover === 'function') { panel.showPopover(); }
            });

            /* El <details> del timeline: HTML puro, ni siquiera necesita Alpine, y aun
               así nunca se había medido por nacer colapsado. */
            conCuidado('timeline', () => {
                document.querySelectorAll('details.muni-tl__detail').forEach((d) => { d.open = true; });
            });

            /* La cabecera ordenable con una columna YA ordenada. Este estado no se ha
               medido NUNCA: ninguna columna arranca ordenada, así que la flecha
               encendida, el aria-sort y el anuncio del cambio de orden no existían. */
            conCuidado('sortable-table', () => {
                const tabla = document.querySelector('table.muni-st');
                const raiz = tabla && tabla.closest('[x-data]');
                if (raiz) { window.Alpine.$data(raiz).sort('monto'); }
            });
        };

        const mide = () => {
            anota('combobox/lista', document.querySelector('.muni-combo__lista'));
            anota('combobox/opción activa', document.querySelector('.muni-combo__opcion'));
            anota('tooltip/burbuja', document.querySelector('.muni-tt__bubble'));
            anota('popover/panel', document.querySelector('.muni-pop__panel'));
            anota('timeline/detalle', document.querySelector('details.muni-tl__detail[open] .muni-tl__diff'));
            anota('sortable-table/columna ordenada', document.querySelector('table.muni-st th[aria-sort="ascending"]'));
            anota('sortable-table/cuerpo', document.querySelector('table.muni-st tbody tr'));

            window.__vitrinaEstado = estado;
            document.documentElement.setAttribute('data-vitrina-lista', '1');
        };

        const arranca = () => {
            abre();
            /* Un respiro antes de medir: las transiciones de x-show arrancan en
               opacity:0 y el medidor de contraste salta lo que tiene opacidad 0.
               Con prefers-reduced-motion la duración es 0ms, pero la reja también
               se corre a mano en un navegador sin esa preferencia. */
            setTimeout(mide, 250);
        };

        /* OJO con esperar solo el evento: el build de CDN de Alpine llama a start()
           en un microtask del PROPIO <script>, así que `alpine:initialized` ya se
           disparó cuando corre esta línea y un listener a secas no se entera nunca.
           Medido: la vitrina se quedaba sin marcar `data-vitrina-lista` para siempre.
           Se espera primero a que el documento termine de parsearse —Alpine tiene
           MutationObserver, así que hidrata lo que llegue después— y ahí se decide:
           si Alpine ya arrancó, se abre de una; si no, se engancha el evento. */
        const cuandoAlpine = () => {
            if (window.Alpine && window.Alpine.version) { arranca(); }
            else { document.addEventListener('alpine:initialized', arranca, { once: true }); }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', cuandoAlpine, { once: true });
        } else {
            cuandoAlpine();
        }
    })();
    JS;
}

/**
 * Las tarjetas, ya renderizadas por Blade, envueltas por quien llame.
 *
 * El envoltorio cambia según el contexto (tarjeta suelta o `section.fi-section`
 * del panel) pero el ORDEN y el conjunto no: las dos vitrinas miden exactamente
 * las mismas piezas, que es lo único que hace comparable una paleta con la otra.
 */
function piezasDeVitrina(callable $envoltorio): string
{
    $piezas = '';

    foreach (ejemplosDeVitrina() as $nombre => $blade) {
        $piezas .= $envoltorio($nombre, Blade::render($blade));
    }

    return $piezas;
}

/**
 * El <head> común: charset, viewport y el título. La hoja la pone cada contexto.
 */
function cabezaDeVitrina(string $titulo): string
{
    return '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>'.$titulo.'</title>';
}

/**
 * El <script> de Alpine + el abridor, o nada si no hay copia en disco.
 * Va INCRUSTADO, no enlazado: la vitrina se abre por file:// y tiene que seguir
 * midiéndose igual si se copia el .html a otra parte.
 */
function colaDeVitrina(?string $alpine): string
{
    return $alpine !== null
        ? '<script>'.$alpine.'</script><script>'.abridorDeVitrina().'</script>'
        : '';
}

function armaVitrina(string $tema, ?string $alpine): string
{
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui.css');

    $piezas = piezasDeVitrina(fn (string $nombre, string $html) => '<section class="v-pieza">'
        .'<h2 class="v-nombre">&lt;x-muni::'.$nombre.'&gt;</h2>'
        .'<div class="v-cuerpo">'.$html.'</div></section>');

    // El contenedor fija el tema y las superficies con los MISMOS tokens que usan
    // los componentes: medir sobre un fondo inventado no diría nada.
    // `data-vitrina-espera-alpine` es el contrato con la reja, y se declara SIEMPRE
    // —haya copia de Alpine o no—: si la página lo declara y nunca aparece
    // `data-vitrina-lista`, `a11y-check.py` FALLA. Ponerlo solo cuando Alpine existe
    // dejaría el peor caso en silencio: sin Alpine la reja volvería a dar verde
    // midiendo 165 textos en vez de 181 y nadie se enteraría de que mide menos.
    // `data-vitrina-hoja` dice QUÉ paleta se cargó: la reja lo lee y lo imprime en
    // la tabla, para que no haya que adivinarlo por el nombre del archivo.
    return '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($tema === 'dark' ? ' class="dark"' : '').' data-vitrina-espera-alpine="1" data-vitrina-hoja="muni-ui.css">'
        .cabezaDeVitrina('Vitrina de laravel-muni-ui — tema '.($tema === 'dark' ? 'oscuro' : 'claro'))
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
        .colaDeVitrina($alpine)
        .'</body></html>';
}

/**
 * La MISMA vitrina, pero en el contexto del panel: la otra paleta y el otro DOM.
 *
 * Por qué existe (DESIGN §7): `MuniPanel` inyecta `muni-ui-filament.css` y
 * `muni-ui.css` NO se carga dentro de un panel. Son dos identidades a propósito
 * —petróleo, lima y celeste contra teal y ámbar—, y en la rama oscura 30 de los
 * 32 tokens comparables valen distinto. Hasta ahora la reja medía UNA sola de las
 * dos: todo lo que el repo afirmaba sobre contraste valía para las aplicaciones
 * sueltas y no decía absolutamente nada de los nueve sistemas municipales, que
 * corren sobre paneles Filament. La verificación manual del panel
 * (`docs/VERIFICACION-NAVEGADOR.md` §8) encontró ahí cuatro defectos que solo
 * existen en esa paleta, incluido D13, donde la reja aprobaba `.muni-tl__tone` y
 * axe lo reprobaba EN LA MISMA CORRIDA porque cada uno medía contra otra hoja.
 *
 * El banco es el de esa verificación (§8.1), reproducido aquí:
 *   · se carga ÚNICAMENTE `muni-ui-filament.css`, nunca `muni-ui.css`;
 *   · el DOM es el del panel — `body.fi-body`, `aside.fi-sidebar`,
 *     `div.fi-topbar`, `div.fi-main-ctn > main.fi-main`, y cada componente
 *     dentro de un `section.fi-section` con su `h2.fi-section-header-heading`;
 *   · del armazón se reponen SOLO dos cosas que pone Filament y no el paquete:
 *     la geometría (anchos y relleno) y `color:var(--muni-text)` en el <body>
 *     —Filament emite ahí `text-gray-950 dark:text-white`—, para no medir texto
 *     negro sobre fondo oscuro, que sería un defecto del banco. Ni un color más.
 *
 * Dos diferencias declaradas respecto del banco de §8.1, y el porqué de cada una:
 *   · las tarjetas son las 31 de `ejemplosDeVitrina()`, no las 25 del banco: la
 *     tarea es medir LAS MISMAS piezas en las dos paletas. Consecuencia: lo que
 *     el banco tenía y esto no (`dropdown`, `modal`, `drawer`, `accordion`,
 *     `command-palette`) sigue sin cubrirse por la reja — D10 y D15 no los caza.
 *   · se conserva el reparto par/impar sobre `--muni-surface-3` de la vitrina
 *     base. El banco dejaba `.v-cuerpo` vacío y por eso D16 quedó como «par de
 *     riesgo sin instancia observada»; con el reparto, la instancia existe y se
 *     mide, que es exactamente lo que D16 decía que pasaría en un anfitrión que
 *     pintara una tarjeta con `surface-3` dentro del panel.
 *
 * El <header class="fi-header"> con su <h1> es DOM real del panel —la propia hoja
 * lo estiliza en `.fi-main > .fi-header` y `.fi-header-heading`— y se incluye para
 * que las dos vitrinas tengan la misma estructura de encabezados y la comparación
 * axe-contra-axe no se ensucie con un `page-has-heading-one` de más.
 */
function armaVitrinaPanel(string $tema, ?string $alpine): string
{
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');
    $oscuro = $tema === 'dark';

    $piezas = piezasDeVitrina(fn (string $nombre, string $html) => '<section class="fi-section" data-pieza="'.$nombre.'">'
        .'<h2 class="fi-section-header-heading">&lt;x-muni::'.$nombre.'&gt;</h2>'
        .'<div class="v-cuerpo">'.$html.'</div></section>');

    return '<!doctype html><html lang="es" data-muni-theme="'.$tema.'"'.($oscuro ? ' class="dark"' : '').' data-vitrina-espera-alpine="1" data-vitrina-hoja="muni-ui-filament.css">'
        .cabezaDeVitrina('Vitrina de laravel-muni-ui en panel Filament — tema '.($oscuro ? 'oscuro' : 'claro'))
        .'<style>'.$css.'</style>'
        // Segunda hoja, a propósito separada de la del paquete: es el armazón del
        // banco, y tiene que verse de un vistazo que no aporta ni un color.
        .'<style>
            body { margin:0; font-family:system-ui, sans-serif; color:var(--muni-text); }
            .fi-main-ctn { margin-inline-start:180px; }
            .fi-main { padding:24px; max-width:none; }
            .fi-sidebar { position:fixed; inset-block:0; inset-inline-start:0; width:180px; padding:12px; z-index:30; }
            .fi-topbar { position:sticky; top:0; z-index:20; }
            .fi-topbar > nav { padding:12px 24px; }
            .v-grid { display:grid; gap:20px; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); }
            .fi-section { padding:16px; }
            .fi-section-header-heading { font-size:12px; margin:0 0 12px; font-family:var(--muni-font-mono); }
            /* Mismo reparto que la vitrina base: una de cada dos tarjetas mide
               sobre --muni-surface-3. Ver el comentario de arriba. */
            .v-grid > .fi-section:nth-child(even) .v-cuerpo { background:var(--muni-surface-3); padding:12px; border-radius:var(--muni-radius); }
        </style></head>'
        .'<body class="fi-body">'
        // Los dos `aria-label` son del banco, no del paquete: sin ellos los tres
        // <nav> de la página (barra lateral, barra superior y el `breadcrumb` del
        // propio componente) quedan indistinguibles y axe levanta un
        // `landmark-unique` que es culpa del armazón. Filament rotula los suyos.
        .'<aside class="fi-sidebar"><nav class="fi-sidebar-nav" aria-label="Menú del panel"><div class="fi-sidebar-item">'
        .'<a class="fi-sidebar-item-btn" href="#"><span class="fi-sidebar-item-label">Bandeja</span></a>'
        .'</div></nav></aside>'
        .'<div class="fi-topbar"><nav aria-label="Barra superior"><span>Barra superior del panel</span></nav></div>'
        .'<div class="fi-main-ctn"><main class="fi-main" id="muni-contenido" tabindex="-1">'
        .'<header class="fi-header"><h1 class="fi-header-heading">Vitrina de componentes en el panel — tema '.($oscuro ? 'oscuro' : 'claro').'</h1></header>'
        .'<div class="v-grid">'.$piezas.'</div></main></div>'
        .colaDeVitrina($alpine)
        .'</body></html>';
}

/**
 * Las cuatro páginas de la vitrina: archivo => cómo se arma.
 *
 * Cuatro y no dos porque son DOS paletas × DOS temas. La reja las mide todas por
 * omisión (`scripts/a11y-check.py` sin argumentos glob-ea `build/vitrina/*.html`).
 */
function paginasDeVitrina(): array
{
    return [
        'claro' => ['light', 'armaVitrina'],
        'oscuro' => ['dark', 'armaVitrina'],
        'panel-claro' => ['light', 'armaVitrinaPanel'],
        'panel-oscuro' => ['dark', 'armaVitrinaPanel'],
    ];
}

it('genera la vitrina con los componentes reales, en las dos paletas y los dos temas', function () {
    $destino = __DIR__.'/../build/vitrina';

    if (! is_dir($destino)) {
        mkdir($destino, 0o775, true);
    }

    $alpine = fuenteDeAlpine();

    if ($alpine === null) {
        // Sin Alpine la vitrina se genera igual, pero mide la mitad. No se falla la
        // suite —una máquina sin `npm install` no tiene por qué quedarse sin correr
        // las pruebas— y se avisa donde se ve: la reja lo convierte en FALLA.
        fwrite(STDERR, "\nAVISO: no hay copia de Alpine 3 en disco, la vitrina sale SIN hidratar.\n"
            ."       Todo lo que abre (combobox, tooltip, popover, sortable-table) queda sin medir.\n"
            ."       Solución: `npm install` en el repo, o copiar cdn.min.js de alpinejs a\n"
            ."       scripts/.cache/alpine-<version>.min.js, o apuntar \$MUNI_ALPINE_JS a un archivo.\n\n");
    }

    foreach (paginasDeVitrina() as $archivo => [$tema, $constructor]) {
        $html = $constructor($tema, $alpine);
        $panel = str_starts_with($archivo, 'panel-');

        expect($html)->toContain('x-muni::stat')
            ->and(strlen($html))->toBeGreaterThan(20000, 'La vitrina salió sospechosamente corta.');

        expect($html)->toContain('data-vitrina-espera-alpine="1"');

        if ($panel) {
            // La condición que DESIGN §7 describe: SOLO la hoja del panel. Si un día
            // alguien mete `muni-ui.css` aquí «para que se vea bien», la página deja
            // de medir la paleta del panel y esto se pone rojo.
            expect($html)->toContain('data-vitrina-hoja="muni-ui-filament.css"')
                ->and($html)->toContain('Sala de Gobierno')
                ->and($html)->not->toContain('Sistema de diseño del ecosistema municipal de Graneros');

            // Y el DOM del panel, la cadena entera: sin ella los selectores de la
            // hoja (`.fi-body`, `.dark .fi-section`, …) no enganchan y se mediría
            // la paleta correcta sobre fondos que el panel nunca pinta.
            expect($html)->toContain('<body class="fi-body">')
                ->and($html)->toContain('<div class="fi-main-ctn">')
                ->and($html)->toContain('<main class="fi-main"')
                ->and($html)->toContain('<section class="fi-section"')
                ->and($html)->toContain('class="fi-sidebar"')
                ->and($html)->toContain('class="fi-topbar"');
        } else {
            expect($html)->toContain('data-vitrina-hoja="muni-ui.css"')
                ->and($html)->toContain('Sistema de diseño del ecosistema municipal de Graneros');
        }

        if ($alpine !== null) {
            // Que el <script> esté no basta: si el abridor deja de abrir algo, la
            // vitrina vuelve a medir solo lo visible en reposo sin que nadie se entere.
            expect($html)->toContain('alpine:initialized')
                ->and($html)->toContain('data-vitrina-lista')
                ->and($html)->toContain("querySelector('.muni-combo')")
                ->and($html)->toContain("querySelector('.muni-tt')")
                ->and($html)->toContain("querySelector('.muni-pop__panel')")
                ->and($html)->toContain('details.muni-tl__detail')
                ->and($html)->toContain("querySelector('table.muni-st')");
        }

        file_put_contents("{$destino}/{$archivo}.html", $html);
    }

    foreach (array_keys(paginasDeVitrina()) as $archivo) {
        expect(file_exists($destino."/{$archivo}.html"))->toBeTrue();
    }
});

it('mide las mismas piezas en las dos paletas', function () {
    // El valor de la variante del panel es la COMPARACIÓN: si un día las dos
    // vitrinas dejan de renderizar el mismo conjunto, una diferencia de contraste
    // entre paletas deja de ser atribuible a la paleta.
    $base = armaVitrina('dark', null);
    $panel = armaVitrinaPanel('dark', null);

    foreach (array_keys(ejemplosDeVitrina()) as $nombre) {
        expect($base)->toContain('&lt;x-muni::'.$nombre.'&gt;')
            ->and($panel)->toContain('&lt;x-muni::'.$nombre.'&gt;');
    }

    expect(substr_count($panel, 'class="fi-section"'))->toBe(count(ejemplosDeVitrina()));
});
