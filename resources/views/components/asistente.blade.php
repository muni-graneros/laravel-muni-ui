@props([
    'steps' => [], // rótulos de los pasos, o ['label'=>, 'disabled'=>?]
    'current' => 0, // índice (desde 0) del paso actual
    'title' => null, // título del trámite completo
    'subtitle' => null, // bajada bajo el título del trámite
    'stepTitle' => null, // encabezado del paso; por defecto su rótulo
    'stepSubtitle' => null, // bajada bajo el encabezado del paso
    'level' => 2, // nivel del título (2-6); el paso va un nivel abajo
    'action' => null, // URL del <form>
    'method' => 'post', // get | post | put | patch | delete (suplanta con _method)
    'enctype' => null, // enctype del form; multipart si el slot trae un file
    'csrf' => true, // agrega @csrf si el slot no lo trae
    'errors' => null, // bolsa de errores; por defecto la compartida $errors
    'errorIds' => [], // mapa campo => id del input para el resumen de errores
    'errorSummary' => true, // muestra el error-summary sobre el paso
    'errorFocus' => true, // el resumen de errores toma el foco
    'prevLabel' => 'Anterior', // texto del botón Anterior
    'nextLabel' => 'Siguiente', // texto del botón Siguiente
    'skipLabel' => 'Omitir este paso', // texto del botón Omitir
    'submitLabel' => 'Enviar solicitud', // texto del botón del último paso
    'prevHref' => null, // URL de Anterior como enlace en vez de submit
    'skippable' => false, // muestra el botón Omitir (salvo en el último paso)
    'actionName' => '_accion', // name de los botones (anterior|siguiente|omitir|enviar)
    'stepName' => '_paso', // name del campo oculto con el índice del paso
    'navName' => null, // name que vuelve navegable el indicador de pasos
    'showStepper' => true, // muestra el indicador de pasos
    'focusStep' => true, // mueve el foco al encabezado al cambiar de paso
    'prevAttrs' => [], // atributos extra del botón Anterior (wire:click…)
    'nextAttrs' => [], // atributos extra del botón Siguiente (wire:click…)
    'skipAttrs' => [], // atributos extra del botón Omitir (wire:click…)
])

{{-- El trámite largo partido en pasos navegables: la solicitud de credencial de
     discapacidad (identificación → antecedentes médicos → documentos → declaración
     → revisión), que hoy es una sola pantalla larguísima o una máquina de pasos que
     cada sistema reescribe con su propio manejo —o su ausencia— del foco.

     Ficha «asistente» de docs/GAP-ANALYSIS.md. Nueve decisiones, y ninguna es de
     estilo:

     1. SOLO SE RINDE EL PASO ACTUAL, y eso es el contrato, no una casualidad. En
        Livewire 3 el `wire:model` de un paso escondido con `x-show` SE SIGUE
        ENVIANDO: los campos quedan en el DOM, en el orden de tabulación y en la
        carga útil. Acá no hay nada que esconder porque no hay nada que rendir: el
        anfitrión pasa en la ranura los campos del paso y nada más. Un asistente que
        oculta pasos con CSS es el defecto que esta pieza existe para no repetir.
     2. AL CAMBIAR DE PASO EL FOCO VA AL ENCABEZADO DEL PASO NUEVO, que es un
        `tabindex="-1"` (WCAG 2.2 AA 2.4.3). Sin eso el foco se queda donde estaba
        —o vuelve al principio del documento— y el lector de pantalla no anuncia
        nada: la persona ciega no se entera de que cambió de paso. Se hace de dos
        maneras a la vez, y las dos son mejora progresiva: el atributo `autofocus`
        cubre el envío real de formulario (página nueva, sin JS), y un `x-init` de
        Alpine core cubre el cambio de paso dentro de Livewire, donde no hay carga
        de página que dispare el atributo. Ese `x-init` solo vuelve a correr porque
        el encabezado lleva un `wire:key` que cambia con el paso: sin él el morph
        conserva el nodo y el foco se quedaba en «Siguiente» desde el paso 2.
        AL CARGAR EL PRIMER PASO NO SE MUEVE EL FOCO: robarlo se salta el enlace
        «Saltar al contenido» y desorienta a quien llega con el teclado. Volver a
        él con «Anterior» o con el indicador sí es un cambio de paso, y ahí sí.
        ENTER ENVÍA EL PASO: un botón por defecto invisible, primero en el DOM,
        duplica «Siguiente»; sin él Enter pulsaba el paso 1 del indicador o
        «Anterior», los dos sin validar.
     3. EL AVANCE SE ANUNCIA UNA SOLA VEZ. `role="progressbar"` con `aria-valuetext`
        —«Paso 2 de 5: Antecedentes médicos»— dentro de una región viva `polite` que
        dice «Paso 2 de 5». El nombre del paso NO se repite en la región viva: el
        encabezado que acaba de recibir el foco ya lo dice, y dos anuncios seguidos
        con el mismo texto es ruido, no accesibilidad.
     4. «ANTERIOR» Y «OMITIR» LLEVAN `formnovalidate`. Sin él, el navegador bloquea
        el retroceso porque el paso actual tiene un campo obligatorio vacío: la
        persona queda encerrada en un paso que no puede completar ni abandonar. Es
        una trampa de teclado con otro nombre. «Siguiente» sí valida.
     5. LA NAVEGACIÓN ES HTML. Botones `submit` con `name`/`value`, y el índice del
        paso en un campo oculto: funciona sin una línea de JavaScript, y el servidor
        sabe siempre de qué paso viene y qué se pidió. Para Livewire, cada botón
        acepta atributos extra (`wire:click` y compañía) por prop.
     6. QUIÉN PUEDE SALTAR A QUÉ PASO LO DECIDE EL SERVIDOR. El indicador navegable
        (`nav-name`) ofrece los pasos ANTERIORES al actual; los posteriores salen
        sin control, aunque ya se hayan visitado. El anfitrión que sí quiera
        ofrecerlos (volvió del 4 al 2 y el 3 y el 4 siguen válidos) declara
        `'disabled' => false` en esos pasos. Aun así, el salto que llega por POST se
        autoriza en el servidor: un `value` en el HTML lo edita cualquiera.
        Dos asistentes con el mismo título, acción y rótulos en una página
        comparten id: ahí el anfitrión pasa `id`.
     7. NO SE GUARDA NADA EN EL NAVEGADOR. Ni `localStorage`, ni `sessionStorage`,
        ni una copia en memoria. El borrador de la credencial de discapacidad
        contiene diagnóstico, o sea dato sensible del artículo 2 letra g) de la Ley
        21.719: va al servidor, cifrado (`EncryptedSeguro`), con base de licitud
        explícita y con caducidad. Un asistente que «no pierde lo escrito» guardando
        en el navegador deja el diagnóstico de un vecino en el disco de un equipo
        compartido del mesón.
     8. EL PASO ES UNA SECCIÓN CON NOMBRE, no un <div>: `<section>` apuntando por
        `aria-labelledby` al encabezado del paso, para que se pueda saltar a él.
     9. NO VALIDA, NO CONSULTA PERMISOS Y NO MIRA LA PETICIÓN. Recibe los textos ya
        redactados por el anfitrión —nunca un modelo ni un registro completo—
        (Ley 21.719, minimización). Los errores llegan resueltos y se muestran con
        `<x-muni::error-summary>`, que ya se lleva el foco y enlaza cada error con
        su campo. --}}

@php
    $nivel = min(6, max(2, (int) $level));

    $pasos = array_values((array) $steps);
    $total = count($pasos);
    $indice = max(0, min((int) $current, max(0, $total - 1)));
    $esUltimo = $total === 0 || $indice === $total - 1;
    $esPrimero = $indice === 0;

    $rotuloDe = function ($paso): string {
        return trim((string) (is_array($paso) ? ($paso['label'] ?? '') : $paso));
    };

    $rotuloActual = $total > 0 ? $rotuloDe($pasos[$indice]) : '';
    $rotuloSiguiente = ! $esUltimo && $total > 0 ? $rotuloDe($pasos[$indice + 1]) : '';
    $rotuloAnterior = ! $esPrimero && $total > 0 ? $rotuloDe($pasos[$indice - 1]) : '';

    $tituloPaso = $stepTitle !== null && trim((string) $stepTitle) !== '' ? (string) $stepTitle : $rotuloActual;

    /*
     * El id sale de las props y NUNCA de uniqid() (DESIGN §10): con uniqid() cambia
     * en cada render, rompe el `aria-labelledby` del formulario y de la sección, y
     * ensucia el diffing de Livewire. Tampoco sale de un contador de render: un
     * re-render parcial lo reiniciaría. El `id` del consumidor manda sobre todo.
     */
    $id = trim((string) $attributes->get('id'));

    if ($id === '') {
        $id = 'muni-asis-'.substr(sha1(implode('|', [
            (string) $title,
            (string) $action,
            (string) $submitLabel,
            implode('¬', array_map($rotuloDe, $pasos)),
        ])), 0, 8);
    }

    /*
     * `method` decide DOS cosas: el atributo del <form>, que en HTML solo entiende
     * GET y POST, y el campo `_method` con que Laravel suplanta PUT/PATCH/DELETE.
     * Escribir method="put" a secas deja un formulario que envía por GET, con el
     * diagnóstico del vecino en la barra de direcciones y en los logs del proxy.
     */
    $metodo = strtolower(trim((string) $method)) ?: 'post';
    $metodoHtml = in_array($metodo, ['get', 'post'], true) ? $metodo : 'post';
    $suplantado = ($metodoHtml === 'post' && $metodo !== 'post') ? strtoupper($metodo) : null;

    // La ranura YA está rendida acá, así que se puede mirar lo que trae.
    $rendido = (string) $slot.(isset($acciones) ? (string) $acciones : '');

    /*
     * Si el paso trae un campo de archivo y el formulario va por POST, el `enctype`
     * no es opcional: sin él la subida falla en silencio —el navegador manda el
     * NOMBRE del archivo como texto— y el vecino cree que adjuntó el informe
     * médico. El del anfitrión manda siempre.
     */
    $enctypeResuelto = trim((string) $enctype);

    if ($enctypeResuelto === '' && $metodoHtml === 'post' && preg_match('/<input[^>]+type=["\']?file/i', $rendido)) {
        $enctypeResuelto = 'multipart/form-data';
    }

    $tokenCsrf = null;
    $csrfEnLaRanura = str_contains($rendido, 'name="_token"') || str_contains($rendido, "name='_token'");

    if ($csrf && ! $csrfEnLaRanura && $metodoHtml === 'post') {
        try {
            $tokenCsrf = csrf_token();
        } catch (\Throwable $e) {
            // Sin sesión arrancada no hay token que emitir. Se calla en vez de
            // reventar la vista: el middleware de Laravel sigue fallando cerrado.
            $tokenCsrf = null;
        }
    }

    $accionNombre = trim((string) $actionName);
    $pasoNombre = trim((string) $stepName);
    $navNombre = trim((string) $navName);

    /*
     * Los pasos que van al indicador. Los POSTERIORES al actual salen sin control:
     * saltar hacia adelante desde el indicador se lleva por delante la validación
     * del paso en curso. Los ya visitados sí, porque volver es un derecho.
     */
    $pasosIndicador = [];

    foreach ($pasos as $i => $paso) {
        $normal = is_array($paso) ? $paso : ['label' => $paso];

        if (! array_key_exists('disabled', $normal)) {
            $normal['disabled'] = $i > $indice;
        }

        $pasosIndicador[] = $normal;
    }

    $contador = $total > 0 ? 'Paso '.($indice + 1).' de '.$total : '';
    $avanceTexto = $rotuloActual !== '' ? $contador.': '.$rotuloActual : $contador;
    $porcentaje = $total > 0 ? (int) round((($indice + 1) / $total) * 100) : 0;

    /*
     * Los atributos extra de cada botón (el hueco para `wire:click`) se rinden con
     * ComponentAttributeBag, que escapa los valores; las CLAVES se filtran, porque
     * un nombre de atributo con comillas cerraría la etiqueta.
     */
    $atributosExtra = function ($extra, array $fuera = []): \Illuminate\View\ComponentAttributeBag {
        $limpios = [];

        /*
         * Fuera, además de las claves mal formadas: los manejadores en línea
         * (`onclick` y compañía, que además la CSP del ecosistema bloquea), y los
         * atributos que el botón YA escribe. Un `type` repetido no pisa al
         * primero —el parser HTML se queda con el primero—, así que el `type` se
         * lee aparte y el botón lo escribe una sola vez.
         */
        $reservadas = array_merge(['type', 'class', 'name', 'value', 'formnovalidate'], $fuera);

        foreach ((array) $extra as $clave => $valor) {
            if (! is_string($clave) || ! preg_match('/^[A-Za-z_:@][A-Za-z0-9_:@.\-]*$/', $clave)) {
                continue;
            }

            if (preg_match('/^on/i', $clave) || in_array(strtolower($clave), $reservadas, true)) {
                continue;
            }

            $limpios[$clave] = $valor;
        }

        return new \Illuminate\View\ComponentAttributeBag($limpios);
    };

    // `type="button"` por los atributos extra es la receta de Livewire con
    // `wire:click`: sin POST nativo. Cualquier otro valor cae en submit.
    $tipoDe = function ($extra): string {
        $tipo = strtolower(trim((string) (((array) $extra)['type'] ?? '')));

        return $tipo === 'button' ? 'button' : 'submit';
    };

    $tipoAnterior = $tipoDe($prevAttrs);
    $tipoSiguiente = $tipoDe($nextAttrs);
    $tipoOmitir = $tipoDe($skipAttrs);

    // Un `href` con esquema ejecutable no es un enlace a un paso: se descarta.
    $destinoAnterior = trim((string) $prevHref);

    if (preg_match('/^[\s\x00-\x1f]*(javascript|vbscript|data):/i', $destinoAnterior)) {
        $destinoAnterior = '';
    }

    /*
     * El nombre del formulario viaja por `->merge()` y NUNCA escrito a mano antes
     * del derrame (DESIGN §8): a mano salían DOS `aria-labelledby` en el mismo
     * <form> y el navegador se queda con el primero, así que el del anfitrión se
     * perdía en silencio.
     */
    if (trim((string) $attributes->get('aria-labelledby')) === '') {
        $attributes = $attributes->except('aria-labelledby');
    }

    $nombreForm = $title ? [$id.'-titulo'] : [];
    $attributes = $attributes->except('id');

    /*
     * ¿Este paso volvió con errores? Se resuelve la bolsa igual que
     * `<x-muni::error-summary>`, porque de eso depende QUIÉN se lleva el foco.
     * `@props` conserva la variable `$errors` que `ShareErrorsFromSession`
     * comparte con todas las vistas, así que un formulario normal ya la trae.
     */
    $bolsaErrores = $errors;

    if (is_object($bolsaErrores) && method_exists($bolsaErrores, 'getBag')) {
        $bolsaErrores = $bolsaErrores->getBag('default');
    }

    if ($bolsaErrores instanceof \Illuminate\Contracts\Support\MessageProvider) {
        $bolsaErrores = $bolsaErrores->getMessageBag();
    }

    if ($bolsaErrores instanceof \Illuminate\Contracts\Support\MessageBag) {
        $bolsaErrores = $bolsaErrores->messages();
    }

    $hayErrores = false;

    foreach ((array) $bolsaErrores as $mensajes) {
        foreach ((is_array($mensajes) ? $mensajes : [$mensajes]) as $mensaje) {
            if ($mensaje !== null && $mensaje !== '') {
                $hayErrores = true;
            }
        }
    }

    /*
     * El foco solo se mueve cuando hubo cambio de paso de verdad. Y CEDE ante el
     * resumen de errores: si el paso vuelve con errores, quien tiene que recibir
     * el foco es el resumen (WCAG 2.2 AA 3.3.1), no el encabezado. Medido en
     * Chromium y Firefox antes de escribir esto: con los dos activos ganaba el
     * encabezado —el `autofocus` del atributo y el `$nextTick` corren después de
     * que el resumen se enfoca—, así que la persona llegaba al paso sin enterarse
     * de que había errores. Dos cosas moviendo el foco es peor que una sola.
     */
    $resumenSeLlevaElFoco = $errorSummary && $errorFocus && $hayErrores;
    $mueveFoco = $focusStep && ! $esPrimero && ! $resumenSeLlevaElFoco;

    /*
     * Qué hace el `x-init` del encabezado, dicho en un `data-*` y NO interpolado en
     * la expresión (la expresión es fija: nada del servidor entra en JS):
     *   · «siempre»: pasos 2 en adelante sin errores. Cubre el cambio de paso de
     *     Livewire, donde no hay carga de página que dispare `autofocus`.
     *   · «navegacion»: el PRIMER paso. Al cargar la pantalla no se mueve (el
     *     enlace «Saltar al contenido»), pero si se llegó ahí desde el paso 3 con
     *     «Anterior» o con el indicador, eso SÍ es un cambio de paso: el botón
     *     pulsado desaparece del DOM y el foco caería al <body>. Lo distingue una
     *     marca en memoria del <form> que pone el clic en un control de
     *     navegación; el <form> es el mismo nodo antes y después del morph.
     *   · «no»: el paso volvió con errores y el foco es del resumen.
     */
    $modoFoco = $resumenSeLlevaElFoco ? 'no' : ($esPrimero ? 'navegacion' : 'siempre');

    // La clave del encabezado cambia con el paso. Sin eso el morph de Livewire
    // (en 4.4.1 la clave es `wire:key`, si no el `id`; `wire:key` existe igual en
    // Livewire 3) CONSERVA el nodo de un paso al siguiente, el `x-init` no vuelve a
    // correr y el foco se queda en «Siguiente». Medido con Livewire real: sin la
    // clave fallan los seis cambios de paso del recorrido.
    $clavePaso = $id.'-paso-'.$indice;

    // Nivel del encabezado del paso: uno bajo el del trámite, sin pasar de <h6>.
    $nivelPaso = min(6, $nivel + 1);
@endphp

<form
    id="{{ $id }}"
    method="{{ $metodoHtml }}"
    @if ($action) action="{{ $action }}" @endif
    @if ($enctypeResuelto !== '') enctype="{{ $enctypeResuelto }}" @endif
    {{ $attributes->merge(array_filter(['class' => 'muni-asis', 'aria-labelledby' => implode(' ', $nombreForm)])) }}
>
    @if ($tokenCsrf !== null)
        <input type="hidden" name="_token" value="{{ $tokenCsrf }}" autocomplete="off">
    @endif
    @if ($suplantado !== null)
        <input type="hidden" name="_method" value="{{ $suplantado }}">
    @endif
    {{-- De qué paso viene el envío. El servidor no puede deducirlo del contenido, y
         confiar en la sesión sola rompe con dos pestañas abiertas. Se valida allá:
         esto es un dato del cliente. --}}
    @if ($pasoNombre !== '')
        <input type="hidden" name="{{ $pasoNombre }}" value="{{ $indice }}">
    @endif

    {{-- EL BOTÓN POR DEFECTO. Enter en un campo hace clic en el PRIMER botón submit
         del formulario en orden del DOM (HTML, «implicit submission»). Sin este, ese
         botón era el paso 1 del indicador o «Anterior», los dos con `formnovalidate`:
         Enter mandaba a la persona hacia atrás, sin validar, cada vez. Medido en
         Chromium y Firefox. Este duplica «Siguiente» (o «Enviar» en el último paso)
         y va primero; está fuera del orden de tabulación y del árbol de
         accesibilidad porque el visible ya existe, y oculto por recorte y no con
         `display:none`, que no se sabe si todos los motores lo siguen usando como
         botón por defecto. Con `type="button"` en `next-attrs` (receta de
         Livewire) sigue siendo submit —si no, no sería el de por defecto— y el
         `x-on:click` anula el envío nativo: queda solo el `wire:click`. --}}
    <button type="submit" class="muni-asis__defecto" tabindex="-1" aria-hidden="true"
        @if ($accionNombre !== '') name="{{ $accionNombre }}" value="{{ $esUltimo ? 'enviar' : 'siguiente' }}" @endif
        @if ($tipoSiguiente === 'button') x-data x-on:click="$event.preventDefault()" @endif
        {{ $atributosExtra($nextAttrs, $tipoSiguiente === 'button' ? ['id', 'tabindex', 'aria-hidden', 'x-data', 'x-on:click', '@click'] : ['id', 'tabindex', 'aria-hidden']) }}>{{ $esUltimo ? $submitLabel : $nextLabel }}</button>

    <header class="muni-asis__cab">
        @if ($title)
            <h{{ $nivel }} id="{{ $id }}-titulo" class="muni-asis__flujo">{{ $title }}</h{{ $nivel }}>
        @endif
        @if ($subtitle)
            <p class="muni-asis__flujo-sub">{{ $subtitle }}</p>
        @endif

        @if ($total > 0)
            {{-- La región viva dice el CONTADOR; el nombre del paso lo dice el
                 encabezado que recibe el foco. Repetirlo sería hablar dos veces. --}}
            <div class="muni-asis__avance" role="status" aria-live="polite">
                <p class="muni-asis__contador">{{ $contador }}</p>
                <div class="muni-asis__barra"
                     role="progressbar"
                     aria-label="Avance del trámite"
                     aria-valuemin="1"
                     aria-valuemax="{{ $total }}"
                     aria-valuenow="{{ $indice + 1 }}"
                     aria-valuetext="{{ $avanceTexto }}">
                    <span class="muni-asis__barra-fill" style="width:{{ $porcentaje }}%"></span>
                </div>
            </div>

            @if ($showStepper)
                <x-muni::stepper
                    :steps="$pasosIndicador"
                    :current="$indice"
                    :nav-name="$navNombre !== '' ? $navNombre : null"
                    label="Pasos del trámite"
                    class="muni-asis__pasos" />
            @endif
        @endif
    </header>

    @if ($errorSummary)
        {{-- `autofocus` es la mitad SIN JS: el resumen mueve el foco con un `x-init`, y
             en un envío real sin Alpine nadie se lo llevaba —el encabezado cede ante
             el resumen—, así que el paso volvía con errores y el foco al principio
             del documento. En el mismo nodo que el `x-init`: no hay dos destinos. --}}
        <x-muni::error-summary :errors="$errors" :ids="$errorIds" :focus="$errorFocus" :level="$nivelPaso" :autofocus="$resumenSeLlevaElFoco" class="muni-asis__errores" />
    @endif

    <section class="muni-asis__paso" aria-labelledby="{{ $id }}-paso-titulo">
        {{-- El destino del foco al cambiar de paso. `autofocus` cubre el envío real
             de formulario; el `x-init` cubre el cambio de paso dentro de Livewire,
             donde no hay carga de página, y solo corre porque `wire:key` cambia con
             el paso (ver $clavePaso). La expresión es FIJA: lo que varía viaja en
             `data-muni-asis-foco`. Sin Alpine y sin recarga no pasa nada malo: el
             encabezado sigue siendo alcanzable y la región viva anuncia. --}}
        <h{{ $nivelPaso }}
            id="{{ $id }}-paso-titulo"
            wire:key="{{ $clavePaso }}"
            class="muni-asis__paso-titulo"
            tabindex="-1"
            @if ($mueveFoco) autofocus @endif
            @if ($focusStep)
                data-muni-asis-foco="{{ $modoFoco }}"
                x-data
                x-init="const f = $el.closest('form'), modo = $el.dataset.muniAsisFoco, pedido = !! (f && f._muniAsisNav);
                    if (f) {
                        f._muniAsisNav = false;
                        if (! f._muniAsisEscucha) {
                            f._muniAsisEscucha = true;
                            f.addEventListener('click', (e) => { if (e.target instanceof Element && e.target.closest('.muni-asis__btn, .muni-asis__defecto, .muni-step__ctrl')) { f._muniAsisNav = true; } }, true);
                        }
                    }
                    if (modo === 'siempre' || (modo === 'navegacion' && pedido)) { $nextTick(() => $el.focus()); }"
            @endif
        >{{ $tituloPaso }}</h{{ $nivelPaso }}>

        @if ($stepSubtitle)
            <p class="muni-asis__paso-sub">{{ $stepSubtitle }}</p>
        @endif

        <div class="muni-asis__campos">{{ $slot }}</div>
    </section>

    {{-- El orden del DOM es el orden visual (WCAG 2.2 AA 1.3.2): Anterior a la
         izquierda, Siguiente a la derecha, y Tab los recorre en ese mismo orden. --}}
    <footer class="muni-asis__acciones">
        <div class="muni-asis__atras">
            @if ($destinoAnterior !== '')
                <a class="muni-asis__btn muni-asis__btn--sec" href="{{ $destinoAnterior }}" {{ $atributosExtra($prevAttrs) }}>
                    {{ $prevLabel }}@if ($rotuloAnterior !== '')<span class="muni-asis__sr">: {{ $rotuloAnterior }}</span>@endif
                </a>
            @elseif (! $esPrimero)
                {{-- `formnovalidate`: volver no puede quedar bloqueado porque este
                     paso tenga un campo obligatorio vacío. --}}
                <button type="{{ $tipoAnterior }}" class="muni-asis__btn muni-asis__btn--sec" formnovalidate
                    @if ($accionNombre !== '') name="{{ $accionNombre }}" value="anterior" @endif
                    {{ $atributosExtra($prevAttrs) }}>
                    {{ $prevLabel }}@if ($rotuloAnterior !== '')<span class="muni-asis__sr">: {{ $rotuloAnterior }}</span>@endif
                </button>
            @endif
        </div>

        <div class="muni-asis__adelante">
            @if ($skippable && ! $esUltimo)
                <button type="{{ $tipoOmitir }}" class="muni-asis__btn muni-asis__btn--sec" formnovalidate
                    @if ($accionNombre !== '') name="{{ $accionNombre }}" value="omitir" @endif
                    {{ $atributosExtra($skipAttrs) }}>{{ $skipLabel }}</button>
            @endif

            <button type="{{ $tipoSiguiente }}" class="muni-asis__btn muni-asis__btn--pri"
                @if ($accionNombre !== '') name="{{ $accionNombre }}" value="{{ $esUltimo ? 'enviar' : 'siguiente' }}" @endif
                {{ $atributosExtra($nextAttrs) }}>
                @if ($esUltimo)
                    {{ $submitLabel }}
                @else
                    {{ $nextLabel }}@if ($rotuloSiguiente !== '')<span class="muni-asis__sr">: {{ $rotuloSiguiente }}</span>@endif
                @endif
            </button>

            @isset($acciones)
                <span class="muni-asis__extra">{{ $acciones }}</span>
            @endisset
        </div>
    </footer>
</form>

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css, y una clase declarada únicamente en
           muni-ui.css se vería sin estilo y sin un solo error en consola (DESIGN §7). */
        .muni-asis { display:flex; flex-direction:column; gap:18px; font-family:var(--muni-font-sans);
            color:var(--muni-text); min-width:0; }
        .muni-asis__cab { display:flex; flex-direction:column; gap:10px; min-width:0; }
        .muni-asis__flujo { margin:0; font-size:19px; font-weight:800; letter-spacing:-.02em; color:var(--muni-text); }
        .muni-asis__flujo-sub { margin:0; font-size:13.5px; line-height:1.55; color:var(--muni-muted); max-width:70ch; }

        .muni-asis__avance { display:flex; flex-direction:column; gap:6px; }
        .muni-asis__contador { margin:0; font-family:var(--muni-font-mono); font-size:12px; font-weight:700;
            letter-spacing:.02em; color:var(--muni-text); }
        .muni-asis__barra { height:7px; border-radius:999px; background:var(--muni-surface-3);
            border:1px solid var(--muni-border); overflow:hidden; }
        /* El movimiento sale del token, que baja a 0 ms con `prefers-reduced-motion`
           (DESIGN §6): una duración fija acá ignoraría la preferencia. */
        .muni-asis__barra-fill { display:block; height:100%; border-radius:999px; background:var(--muni-accent);
            transition:width var(--muni-dur) var(--muni-ease); }
        .muni-asis__pasos { margin-top:2px; }

        .muni-asis__paso { display:flex; flex-direction:column; gap:12px; min-width:0; }
        /* El encabezado recibe el foco al cambiar de paso: el desplazamiento tiene
           que dejarlo POR DEBAJO de la barra superior, que es sticky con z-index 100.
           Sin esto el anillo de foco queda tapado (WCAG 2.2 AA 2.4.11). */
        .muni-asis__paso-titulo { margin:0; font-size:16px; font-weight:700; color:var(--muni-text);
            scroll-margin-top:calc(var(--muni-topbar-h) + 12px); }
        .muni-asis__paso-titulo:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:3px; }
        .muni-asis__paso-sub { margin:0; font-size:13px; line-height:1.55; color:var(--muni-muted); max-width:70ch; }
        .muni-asis__campos { display:flex; flex-direction:column; gap:16px; min-width:0; }

        .muni-asis__acciones { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;
            padding-top:14px; border-top:1px solid var(--muni-border); }
        .muni-asis__atras, .muni-asis__adelante { display:flex; flex-wrap:wrap; align-items:center; gap:12px; }
        .muni-asis__btn { box-sizing:border-box; display:inline-flex; align-items:center; justify-content:center;
            min-height:44px; padding:0 20px; font-family:var(--muni-font-sans); font-size:14px; font-weight:600;
            text-decoration:none; border-radius:var(--muni-radius-sm); border:1px solid transparent; cursor:pointer;
            transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-asis__btn--pri { color:var(--muni-on-accent); background:var(--muni-accent); font-weight:700; }
        .muni-asis__btn--pri:hover { background:var(--muni-accent-strong); }
        .muni-asis__btn--sec { color:var(--muni-text); background:var(--muni-surface); border-color:var(--muni-field-border); }
        .muni-asis__btn--sec:hover { background:var(--muni-surface-2); border-color:var(--muni-accent); }
        /* El outline es el indicador REAL: la box-shadow del anillo se computa
           transparente dentro de Filament (DESIGN §5). */
        .muni-asis__btn:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }

        .muni-asis__defecto { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        .muni-asis__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }

        @media (max-width:560px) {
            /* Cinco rótulos en 390 px no se leen: salían «Id…», «A…» y un «CON
               ERRORES» montado sobre el paso siguiente. En teléfono el indicador
               queda en marcadores —la marca de error y la de omitido son FORMA, así
               que el color sigue sin ser el único portador— y los rótulos siguen
               en el árbol de accesibilidad. Dónde se está lo dicen a la vista el
               contador y el encabezado del paso, que están justo encima y debajo. */
            .muni-asis__pasos .muni-step__body { position:absolute; width:1px; height:1px; padding:0; margin:-1px;
                overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
            .muni-asis__pasos .muni-step__ctrl { min-width:44px; justify-content:center; }
            .muni-asis__acciones { flex-direction:column; align-items:stretch; }
            .muni-asis__atras, .muni-asis__adelante { flex-direction:column; align-items:stretch; }
            .muni-asis__btn { width:100%; }
        }
        /* En papel no hay nada que enviar ni ningún paso al que ir: lo que se
           imprime es el paso que se estaba llenando. */
        @media print {
            .muni-asis__acciones { display:none !important; }
        }
        @media (prefers-reduced-motion:reduce) {
            .muni-asis__btn, .muni-asis__barra-fill { transition:none; }
        }
    </style>
@endonce
