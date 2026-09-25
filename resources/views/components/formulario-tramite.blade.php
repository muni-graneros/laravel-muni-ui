@props([
    'title' => null, // título del trámite
    'subtitle' => null, // línea secundaria bajo el título
    'action' => null, // URL a la que se envía el formulario
    'method' => 'post', // post | get | put | patch | delete
    'enctype' => null, // null lo deduce (multipart si hay input file)
    'csrf' => true, // agrega el token CSRF si el slot no lo trae
    'level' => 2, // nivel del encabezado del título
    'errors' => null, // MessageBag o array para el resumen de errores
    'errorIds' => [], // mapa clave de error => id del control
    'errorSummary' => true, // muestra el resumen de errores arriba
    'errorFocus' => true, // enfoca el resumen de errores al aparecer
    /*
     * Para APAGAR esta leyenda se pasa la cadena vacía (`required-note=""`), no `null`:
     * la directiva de props de arriba aplica el valor por defecto con `??`, así que un
     * null explícito vuelve al texto de fábrica en vez de quitarlo. Es lo contrario de lo
     * que pasa con un atributo `:algo="null"` sobre un componente hijo, que sí gana
     * (DESIGN §8), y por eso se escribe acá: el mismo null hace dos cosas distintas según
     * dónde caiga. (El nombre de la directiva va en prosa a propósito: nombrarla dentro de
     * un comentario es justo la trampa #5.)
     */
    'requiredNote' => 'Los campos marcados con * son obligatorios.',
    'requisitos' => [], // lista: strings o ['texto', 'campo', 'cumplido']
    'requisitosTitle' => 'Requisitos para ingresar', // título del bloque de requisitos
    'requisitosNote' => null, // nota bajo la lista de requisitos
    'pendingLabel' => 'Falta', // rótulo de un requisito pendiente
    'doneLabel' => 'Listo', // rótulo de un requisito cumplido
    'submitLabel' => 'Ingresar solicitud', // texto del botón de envío
    'cancelHref' => null, // URL de Cancelar; sin ella no hay botón
    'cancelLabel' => 'Cancelar', // texto del enlace Cancelar
])

{{-- El trámite que el funcionario ingresa con los papeles en la mano: secciones con
     <legend>, rejilla de dos columnas, resumen de errores arriba y una columna lateral
     con los requisitos que faltan para poder ingresar.

     No es un asistente por pasos. El asistente es para el vecino que llena su solicitud
     desde el teléfono; obligar al funcionario del mesón a cuatro pasos por cada ingreso
     es más lento que la ventanilla de papel. Acá está todo a la vista y se envía una vez.

     Siete decisiones, y ninguna es de estilo:

     1. EL RESUMEN DE ERRORES VA ARRIBA Y SE LLEVA EL FOCO. Quien envía un formulario de
        treinta campos puede tener el error tres pantallas más arriba del botón que acaba
        de pulsar (WCAG 2.2 AA 3.3.1 y 2.4.3, técnica G139). No se reimplementa: se compone
        `<x-muni::error-summary>`, que ya resuelve el recuento en texto, el enlace a cada
        campo y el foco una sola vez por nodo. Con `:error-summary="false"` no se emite,
        para el anfitrión que lo pone él mismo en otro sitio.
     2. LA COLUMNA LATERAL ES TEXTO, NO CONTROLES. Ni un <button>, ni un <a>, ni un
        tabindex: un resumen que roba diez tabulaciones entre el último campo y el botón
        de enviar es peor que no tenerlo. Dice QUÉ FALTA, que es lo que convierte una
        lista de campos en un trámite.
     3. EL ESTADO DE CADA REQUISITO LLEGA RESUELTO DEL SERVIDOR y Alpine solo lo REFRESCA.
        Sin JS se ve el estado que el anfitrión calculó; con JS, un requisito que declara
        `campo` se marca solo en cuanto ese control tiene valor. Es mejora progresiva: si
        Alpine no carga, no falta nada.
        LA REGLA, que no admite excepción: en un requisito CON `campo` manda el campo, en
        los dos sentidos —se marca al llenarlo y se desmarca al vaciarlo—, así que
        `cumplido` tiene que ser el estado INICIAL de ese control. Un requisito que ya se
        cumplió FUERA del formulario —un documento que ya está en el expediente— se declara
        SIN `campo` y con `cumplido => true`: así nada lo toca. Medido en navegador antes
        de escribirlo: con `campo` y `cumplido => true` sobre un control vacío, la primera
        tecla en cualquier otro campo pasaba el requisito a «Falta» y la columna lateral
        contradecía al servidor sin que nadie hubiera tocado ese campo.
     4. EL RECUENTO SE ANUNCIA, PERO SOLO CUANDO CAMBIA. Va en una región viva `polite`
        con `x-effect` y una comparación previa: reescribir la misma cadena muta el DOM y
        el lector de pantalla vuelve a hablar, así que tras cada tecla se oiría «faltan 3
        requisitos» otra vez. Se escribe únicamente si el número cambió de verdad.
     5. EL BOTÓN DE ENVIAR NUNCA SE DESHABILITA por requisitos pendientes. Quién puede
        ingresar lo decide el servidor: un botón apagado desde el cliente deja al
        funcionario sin saber por qué, y la validación de verdad hay que escribirla igual.
     6. NADA DEL ANFITRIÓN ENTRA CRUDO EN UNA EXPRESIÓN DE ALPINE. Los textos de los
        requisitos se rinden como HTML escapado y lo poco que necesita JS —el nombre del
        campo asociado, el estado inicial, los dos rótulos— viaja por `@js()`. Un apóstrofo
        dentro de una cadena de comillas simples descuadra la expresión y tumba el Alpine
        de la página ENTERA, no solo el de este componente. Ya pasó en `file-dropzone`.
     7. EL `enctype` DE LOS ADJUNTOS SE PONE SOLO. Un formulario con un campo de archivo y
        sin `enctype="multipart/form-data"` no sube nada: el navegador envía el NOMBRE del
        archivo como si fuera texto y el servidor recibe una cadena. No hay error, no hay
        aviso y el funcionario cree que adjuntó el informe. Como la ranura ya está
        renderizada cuando corre esto, el componente mira si trae un campo de archivo y
        pone el `enctype` correcto; el anfitrión puede fijarlo a mano y entonces manda él.
     8. EL CAMPO OCULTO DE CSRF SE EMITE SOLO, y se apaga con `:csrf="false"`. Un
        formulario de trámite que crea una solicitud a nombre de un vecino es el blanco
        exacto de un CSRF, y el olvido no se ve: si el anfitrión ya lo pone, o si el envío
        es de Livewire, la prop lo quita. El token sale de la sesión con guarda; cuando no
        hay sesión arrancada no se emite nada en vez de reventar la vista.

     Lo que el componente NO hace, a propósito:

     · No valida, no consulta permisos y no mira la petición. Recibe todo resuelto por el
       anfitrión, y los textos llegan ya redactados —nunca un modelo ni un registro
       completo— (Ley 21.719, minimización).
     · No guarda borradores. Un borrador de un trámite municipal lleva datos personales y,
       en el caso de la credencial de discapacidad, diagnóstico: eso no puede quedar en
       localStorage ni en sessionStorage. Va al servidor, cifrado (EncryptedSeguro) y con
       caducidad.
     · No decide qué adjuntos se aceptan. El `accept` de un campo de archivo es una
       sugerencia del navegador, no una defensa: tipo, tamaño y contenido se validan en el
       servidor. Los adjuntos van en una sección más, con `<x-muni::file-dropzone>`.
     · No marca cada campo obligatorio: eso lo emite el control. `<x-muni::input required>`
       —y select, textarea, checkbox, checkbox-group y combobox— pone el asterisco con
       aria-hidden y la palabra «obligatorio» en TEXTO al lado, oculta a la vista por
       defecto y visible con `required-text-visible`. Lo que aporta este contenedor es
       la leyenda al principio del formulario (técnica G184), que es el canal para quien
       mira; las dos cosas juntas son lo que pide la ficha, y ninguna reemplaza a la otra. --}}

@php
    $nivel = min(6, max(2, (int) $level));

    /*
     * El id sale de las props, NUNCA de uniqid() (DESIGN §10): con uniqid() cambia en
     * cada render, rompe el `aria-labelledby` y ensucia el diffing de Livewire. Por la
     * misma razón tampoco sale de un contador de render: un re-render parcial de
     * Livewire lo reiniciaría y el id cambiaría entre dos pinturas de la misma página.
     *
     * La semilla es TODO lo barato y estable que distingue a un formulario de otro, no
     * solo el rótulo: dos trámites distintos casi nunca comparten título, acción,
     * bajada, rótulo del botón y lista de requisitos a la vez. Lo que queda —dos
     * formularios idénticos byte a byte en la misma página— colisiona a propósito: son
     * el mismo formulario dos veces, y para eso está el `id` del consumidor, que manda
     * siempre.
     */
    $formId = trim((string) $attributes->get('id'));

    if ($formId === '') {
        $formId = 'muni-ftr-'.substr(sha1(implode('|', [
            (string) $title,
            (string) $action,
            (string) $subtitle,
            (string) $submitLabel,
            (string) $requisitosTitle,
            json_encode($requisitos, JSON_UNESCAPED_UNICODE) ?: '',
        ])), 0, 8);
    }

    /*
     * `method` decide DOS cosas: el atributo del <form>, que en HTML solo entiende GET y
     * POST, y el campo `_method` con que Laravel suplanta PUT/PATCH/DELETE. Escribir
     * method="put" a secas deja un formulario que envía por GET —con todos los datos del
     * vecino en la barra de direcciones y en los logs del proxy— sin un solo aviso.
     */
    $metodo = strtolower(trim((string) $method)) ?: 'post';
    $metodoHtml = in_array($metodo, ['get', 'post'], true) ? $metodo : 'post';
    $suplantado = ($metodoHtml === 'post' && $metodo !== 'post') ? strtoupper($metodo) : null;

    /*
     * `$slot` YA está renderizado cuando corre este bloque, así que se puede mirar lo que
     * trae. Se miran las DOS ranuras: un campo de archivo que llegue por `acciones` —o un
     * campo de CSRF que el anfitrión escriba ahí— es igual de real que uno del cuerpo.
     */
    $rendido = (string) $slot.(isset($acciones) ? (string) $acciones : '');

    /*
     * Si hay un campo de archivo y el formulario va por POST, el `enctype` no es
     * opcional: sin él la subida falla en silencio. El del anfitrión manda siempre.
     */
    $enctypeResuelto = trim((string) $enctype);

    if ($enctypeResuelto === '' && $metodoHtml === 'post' && preg_match('/<input[^>]+type=["\']?file/i', $rendido)) {
        $enctypeResuelto = 'multipart/form-data';
    }

    $tokenCsrf = null;

    /*
     * Y si el anfitrión ya escribió el campo de CSRF dentro de la ranura —el hábito
     * normal en Laravel, y no tiene por qué saber que el componente también lo pone—,
     * no se emite un segundo. Dos `<input name="_token">` no rompen nada, pero son HTML
     * redundante que nadie ve, y el apagado explícito solo lo conoce quien lea el fuente.
     */
    $csrfEnLaRanura = str_contains($rendido, 'name="_token"') || str_contains($rendido, "name='_token'");

    if ($csrf && ! $csrfEnLaRanura && $metodoHtml === 'post') {
        try {
            $tokenCsrf = csrf_token();
        } catch (\Throwable $e) {
            // Sin sesión arrancada no hay token que emitir. Se calla en vez de reventar
            // la vista: el middleware de Laravel sigue fallando cerrado si falta.
            $tokenCsrf = null;
        }
    }

    /*
     * Los requisitos se normalizan acá y no en la expresión de Alpine. `campo` es el
     * `name` de un control del formulario: si lo trae, JS lo vuelve a evaluar al escribir;
     * si no, manda el `cumplido` del servidor y no se toca nunca.
     */
    $reqs = [];

    foreach ((array) $requisitos as $i => $req) {
        $req = is_array($req) ? $req : ['texto' => $req];
        $texto = trim((string) ($req['texto'] ?? ''));

        if ($texto === '') {
            continue;
        }

        $reqs[] = [
            'texto' => $texto,
            'nota' => isset($req['nota']) ? trim((string) $req['nota']) : '',
            'campo' => isset($req['campo']) && $req['campo'] !== '' ? (string) $req['campo'] : null,
            'cumplido' => (bool) ($req['cumplido'] ?? false),
        ];
    }

    $estadoInicial = array_map(fn (array $r) => $r['cumplido'], $reqs);
    $camposDeReq = array_map(fn (array $r) => $r['campo'], $reqs);
    $totalReqs = count($reqs);
    $faltanInicial = count(array_filter($estadoInicial, fn (bool $c) => ! $c));

    $resumenReqs = $totalReqs === 0
        ? ''
        : ($faltanInicial === 0
            ? 'Todos los requisitos están completos.'
            : ($faltanInicial === 1
                ? 'Falta 1 requisito de '.$totalReqs.'.'
                : 'Faltan '.$faltanInicial.' requisitos de '.$totalReqs.'.'));

    /*
     * El nombre del formulario viaja por `->merge()` y NUNCA escrito a mano antes del
     * derrame (DESIGN §8): a mano salían DOS `aria-labelledby` en el mismo <form> —HTML
     * inválido— y el del anfitrión se perdía en silencio, porque el navegador se queda
     * con el primero. Uno vacío se saca de la bolsa para no dejar el formulario sin nombre.
     */
    if (trim((string) $attributes->get('aria-labelledby')) === '') {
        $attributes = $attributes->except('aria-labelledby');
    }

    $nombreForm = $title ? [$formId.'-titulo'] : [];

    $attributes = $attributes->except('id');
@endphp

<form
    id="{{ $formId }}"
    method="{{ $metodoHtml }}"
    @if ($action) action="{{ $action }}" @endif
    @if ($enctypeResuelto !== '') enctype="{{ $enctypeResuelto }}" @endif
    @if ($totalReqs > 0)
        x-data="{
            campos: @js($camposDeReq),
            estado: @js($estadoInicial),
            total: @js($totalReqs),
            resumen: @js($resumenReqs),
            listo: @js((string) $doneLabel),
            falta: @js((string) $pendingLabel),

            recalcular() {
                let faltan = 0;

                this.campos.forEach((campo, i) => {
                    if (campo) { this.estado[i] = this.tieneValor(campo); }
                    if (! this.estado[i]) { faltan++; }
                });

                /* Solo si el NÚMERO cambió: reescribir la misma cadena muta el DOM y el
                   lector de pantalla vuelve a hablar tras cada tecla. */
                const texto = faltan === 0
                    ? 'Todos los requisitos están completos.'
                    : (faltan === 1
                        ? 'Falta 1 requisito de ' + this.total + '.'
                        : 'Faltan ' + faltan + ' requisitos de ' + this.total + '.');

                if (texto !== this.resumen) { this.resumen = texto; }
            },

            tieneValor(campo) {
                /* `elements` y no `querySelector`: un name como `items[0][rut]` —lo normal
                   en un formulario repetido de Livewire— es un valor de atributo válido
                   pero deja un selector que hay que escapar a mano, y un escape mal hecho
                   devuelve el campo equivocado o ninguno. */
                const nodos = Array.from(this.$el.elements || []).filter((el) => el.name === campo);

                if (! nodos.length) { return false; }

                return nodos.some((el) => {
                    if (el.type === 'checkbox' || el.type === 'radio') { return el.checked; }
                    if (el.type === 'file') { return !!(el.files && el.files.length); }
                    return String(el.value == null ? '' : el.value).trim() !== '';
                });
            }
        }"
        x-on:input.debounce.400ms="recalcular()"
        x-on:change="recalcular()"
    @endif
    {{ $attributes->merge(array_filter(['class' => 'muni-ftr', 'aria-labelledby' => implode(' ', $nombreForm)])) }}
>
    @if ($tokenCsrf !== null)
        <input type="hidden" name="_token" value="{{ $tokenCsrf }}" autocomplete="off">
    @endif
    @if ($suplantado !== null)
        <input type="hidden" name="_method" value="{{ $suplantado }}">
    @endif

    @if ($title || $subtitle || $requiredNote)
        <header class="muni-ftr__cab">
            @if ($title)
                <h{{ $nivel }} id="{{ $formId }}-titulo" class="muni-ftr__titulo">{{ $title }}</h{{ $nivel }}>
            @endif
            @if ($subtitle)
                <p class="muni-ftr__sub">{{ $subtitle }}</p>
            @endif
            @if ($requiredNote)
                <p class="muni-ftr__obl">{{ $requiredNote }}</p>
            @endif
        </header>
    @endif

    @if ($errorSummary)
        <x-muni::error-summary :errors="$errors" :ids="$errorIds" :focus="$errorFocus" :level="$nivel + 1" class="muni-ftr__errores" />
    @endif

    <div class="muni-ftr__cuerpo">
        <div class="muni-ftr__campos">{{ $slot }}</div>

        @if ($totalReqs > 0)
            <aside class="muni-ftr__lateral" aria-labelledby="{{ $formId }}-req">
                <h{{ $nivel + 1 }} id="{{ $formId }}-req" class="muni-ftr__req-titulo">{{ $requisitosTitle }}</h{{ $nivel + 1 }}>

                {{-- Nace CON su texto, no vacía: acá el recuento del servidor es
                     información desde el primer render, no una notificación. `x-effect`
                     con la comparación evita la mutación redundante al hidratar. --}}
                <p class="muni-ftr__req-resumen" role="status" aria-live="polite"
                   x-effect="if ($el.textContent.trim() !== resumen) { $el.textContent = resumen }">{{ $resumenReqs }}</p>

                <ul class="muni-ftr__req-lista">
                    @foreach ($reqs as $i => $req)
                        {{-- El `:class` va en sintaxis de OBJETO y no de cadena. El de
                             cadena solo retira lo que él mismo agregó: la clase que
                             puso el servidor —arriba, en el atributo `class`, que es
                             lo que se ve sin JS— no la quita nunca, y al vaciar un
                             campo el <li> terminaba con `es-listo es-falta` a la vez:
                             la palabra decía «Falta» y la marca seguía redonda, verde
                             y con el texto tachado (DESIGN §10: el color no puede
                             contradecir a la palabra). Con objeto, las claves en
                             falso se retiran vengan de donde vengan, y el resultado
                             deja de depender del ORDEN de las reglas del bloque de
                             estilos. --}}
                        <li class="muni-ftr__req {{ $req['cumplido'] ? 'es-listo' : 'es-falta' }}"
                            :class="{'es-listo': estado[{{ $i }}], 'es-falta': ! estado[{{ $i }}]}">
                            {{-- El estado va en PALABRAS, no solo en el color del punto:
                                 el color no puede ser el único portador (WCAG 2.2 AA 1.4.1). --}}
                            <span class="muni-ftr__req-marca" aria-hidden="true"></span>
                            <span class="muni-ftr__req-texto">
                                <span class="muni-ftr__req-estado" x-text="estado[{{ $i }}] ? listo : falta">{{ $req['cumplido'] ? $doneLabel : $pendingLabel }}</span>
                                <span class="muni-ftr__req-que">{{ $req['texto'] }}</span>
                                @if ($req['nota'])
                                    <span class="muni-ftr__req-nota">{{ $req['nota'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                @if ($requisitosNote)
                    <p class="muni-ftr__req-pie">{{ $requisitosNote }}</p>
                @endif
            </aside>
        @endif
    </div>

    <footer class="muni-ftr__acciones">
        {{-- El primario PRIMERO en el DOM y primero a la vista: el orden de tabulación
             y el orden visual son el mismo, que es lo que pide 1.3.2. --}}
        <button type="submit" class="muni-ftr__enviar">{{ $submitLabel }}</button>
        @if ($cancelHref)
            <a class="muni-ftr__cancelar" href="{{ $cancelHref }}">{{ $cancelLabel }}</a>
        @endif
        @isset($acciones)
            <span class="muni-ftr__extra">{{ $acciones }}</span>
        @endisset
    </footer>
</form>

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css, y una clase declarada únicamente en muni-ui.css
           se vería sin estilo y sin un solo error en consola (DESIGN §7). */
        .muni-ftr { display:flex; flex-direction:column; gap:18px; font-family:var(--muni-font-sans);
            color:var(--muni-text); min-width:0; }
        .muni-ftr__cab { display:flex; flex-direction:column; gap:4px; }
        .muni-ftr__titulo { margin:0; font-size:19px; font-weight:800; letter-spacing:-.02em; color:var(--muni-text); }
        .muni-ftr__sub { margin:0; font-size:13.5px; line-height:1.55; color:var(--muni-muted); max-width:70ch; }
        .muni-ftr__obl { margin:2px 0 0; font-size:12px; line-height:1.5; color:var(--muni-hint); }
        .muni-ftr__cuerpo { display:grid; grid-template-columns:minmax(0,1fr) 288px; gap:18px; align-items:start; min-width:0; }
        .muni-ftr__campos { display:flex; flex-direction:column; gap:16px; min-width:0; }

        /* La columna lateral acompaña al funcionario mientras baja por el formulario.
           `top` cuenta con la barra superior, que es sticky con z-index 100: sin el
           desplazamiento, el anillo de foco de lo que quede debajo se pierde tras ella. */
        .muni-ftr__lateral { box-sizing:border-box; position:sticky; top:calc(var(--muni-topbar-h) + 12px); display:flex;
            flex-direction:column; gap:10px; padding:14px 16px; background:var(--muni-surface-2);
            border:1px solid var(--muni-border); border-radius:var(--muni-radius); min-width:0; }
        .muni-ftr__req-titulo { margin:0; font-size:12.5px; font-weight:700; letter-spacing:.02em; color:var(--muni-text); }
        .muni-ftr__req-resumen { margin:0; font-size:12.5px; font-weight:700; line-height:1.45; color:var(--muni-text); }
        .muni-ftr__req-lista { margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:9px; }
        .muni-ftr__req { display:flex; align-items:flex-start; gap:9px; font-size:12.5px; line-height:1.45; color:var(--muni-text); }
        /* El punto es SOLO refuerzo: la palabra de al lado es la que informa. Cuadrado
           cuando falta y redondo cuando está listo, para que la forma también distinga. */
        .muni-ftr__req-marca { flex-shrink:0; width:11px; height:11px; margin-top:3px; border:2px solid currentColor; }
        .muni-ftr__req.es-falta .muni-ftr__req-marca { color:var(--muni-warn-fg); border-radius:2px; }
        .muni-ftr__req.es-listo .muni-ftr__req-marca { color:var(--muni-ok-fg); border-radius:999px; background:currentColor; }
        .muni-ftr__req-texto { display:flex; flex-direction:column; gap:1px; min-width:0; }
        .muni-ftr__req-estado { font-size:10.5px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
        .muni-ftr__req.es-falta .muni-ftr__req-estado { color:var(--muni-warn-fg); }
        .muni-ftr__req.es-listo .muni-ftr__req-estado { color:var(--muni-ok-fg); }
        .muni-ftr__req-que { color:var(--muni-text); }
        .muni-ftr__req.es-listo .muni-ftr__req-que { text-decoration:line-through; text-decoration-thickness:1px; }
        .muni-ftr__req-nota { font-size:11.5px; color:var(--muni-muted); }
        .muni-ftr__req-pie { margin:0; font-size:11.5px; line-height:1.5; color:var(--muni-muted); }

        .muni-ftr__acciones { display:flex; flex-wrap:wrap; align-items:center; gap:12px;
            padding-top:14px; border-top:1px solid var(--muni-border); }
        .muni-ftr__enviar, .muni-ftr__cancelar { box-sizing:border-box; display:inline-flex; align-items:center; justify-content:center;
            min-height:44px; padding:0 20px; font-family:var(--muni-font-sans); font-size:14px; font-weight:600;
            text-decoration:none; border-radius:var(--muni-radius-sm); border:1px solid transparent; cursor:pointer;
            transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-ftr__enviar { color:var(--muni-on-accent); background:var(--muni-accent); font-weight:700; }
        .muni-ftr__enviar:hover { background:var(--muni-accent-strong); }
        .muni-ftr__cancelar { color:var(--muni-text); background:var(--muni-surface); border-color:var(--muni-field-border); }
        .muni-ftr__cancelar:hover { background:var(--muni-surface-2); border-color:var(--muni-accent); }
        /* El outline es el indicador REAL: la box-shadow del anillo se computa
           transparente dentro de Filament (DESIGN §5). */
        .muni-ftr__enviar:focus-visible, .muni-ftr__cancelar:focus-visible {
            outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }

        @media (max-width:1100px) {
            .muni-ftr__cuerpo { grid-template-columns:minmax(0,1fr); }
            .muni-ftr__lateral { position:static; }
        }
        @media (max-width:480px) {
            .muni-ftr__acciones { flex-direction:column; align-items:stretch; }
            .muni-ftr__enviar, .muni-ftr__cancelar { width:100%; }
        }
        /* En papel no hay nada que enviar ni que cancelar, y el recuento de requisitos
           ya no puede cambiar: lo que se imprime es lo que se llenó. */
        @media print {
            .muni-ftr__acciones { display:none !important; }
            .muni-ftr__lateral { position:static; }
        }
        @media (prefers-reduced-motion:reduce) {
            .muni-ftr__enviar, .muni-ftr__cancelar { transition:none; }
        }
    </style>
@endonce
