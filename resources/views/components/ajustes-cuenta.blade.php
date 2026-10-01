@props([
    /* Identificador del grupo de pestañas. De él cuelgan los id de cada pestaña,
       de cada panel, de cada formulario de sesión y de cada diálogo. NUNCA sale de
       uniqid() (DESIGN §10): se saneá el del consumidor o se deriva de las
       etiquetas, que son estables entre renders. */
    'id' => null, // base de los id de pestañas, paneles y diálogos
    'label' => 'Secciones de mi cuenta', // nombre accesible del grupo de pestañas
    'default' => 0, // índice de la pestaña abierta al cargar
    /* Los rótulos de las cuatro secciones. Cada una aparece SOLO si tiene
       contenido: una pestaña vacía es una parada de teclado que no lleva a nada. */
    'perfilLabel' => 'Perfil', // rótulo de la pestaña Perfil
    'seguridadLabel' => 'Seguridad', // rótulo de la pestaña Seguridad
    'preferenciasLabel' => 'Preferencias', // rótulo de la pestaña Preferencias
    'peligroLabel' => 'Zona de peligro', // rótulo de la pestaña Zona de peligro
    /* Las sesiones abiertas, YA REDACTADAS por el anfitrión: ['id','device',
       'detail','current']. `detail` es la línea que ve el funcionario («Rancagua ·
       hace 2 horas»), no el registro de sesión. */
    'sessions' => [], // [['id','device','detail','current']] ya redactadas
    'sessionsAction' => null, // URL POST para cerrar una sesión; sin ella no hay botón
    'sessionsTitle' => 'Sesiones activas', // título de la tarjeta de sesiones
    'sessionsDescription' => 'Los dispositivos donde tu cuenta está abierta ahora.', // bajada de la tarjeta de sesiones
    'sessionsEmpty' => 'No hay otras sesiones abiertas.', // texto cuando no hay otras sesiones
    'currentLabel' => 'Este equipo', // marca de la sesión actual
    'closeLabel' => 'Cerrar sesión', // texto del botón que cierra una sesión
    'closeExplanation' => 'El dispositivo tendrá que volver a ingresar. La sesión queda invalidada en el servidor.', // explicación en el diálogo de confirmación
    /* La zona de peligro. Sale SOLO con las dos: la acción y la palabra que hay
       que escribir. Una acción destructiva sin puerta no la pone el paquete. */
    'dangerAction' => null, // URL de la acción destructiva; exige dangerWord
    'dangerWord' => null, // palabra que hay que escribir para confirmar
    'dangerTitle' => 'Zona de peligro', // título de la tarjeta de zona de peligro
    'dangerDescription' => null, // bajada de la tarjeta de zona de peligro
    'dangerLabel' => 'Eliminar mi cuenta', // texto del botón destructivo
    'dangerName' => 'confirmacion', // name del campo de confirmación
    'dangerMethod' => 'post', // post | put | patch | delete (nunca GET)
    /* La pista bajo el campo. Por defecto NO promete mayúsculas: la palabra la pone
       el anfitrión y con `danger-word="Eliminar"` una pista que dijera «en
       mayúsculas» sería mentira, y una pista que miente es peor que ninguna. */
    'dangerHint' => null, // pista bajo el campo; por defecto nombra la palabra
])

{{-- La pantalla de «Mi cuenta»: perfil, seguridad, preferencias y zona de peligro,
     en pestañas de verdad, con las sesiones activas y una zona de peligro con puerta.

     Es la ficha `ajustes-cuenta` de docs/GAP-ANALYSIS.md, y la ficha no pide obra
     nueva: pide una REPARACIÓN. La pantalla ya existía como demo/settings.html y es
     la demo con más regresiones de accesibilidad del paquete —los interruptores son
     `<div class="sw" @@click>` sin `<input type="checkbox">`, así que no se alcanzan
     con Tab ni se operan con Espacio, y las pestañas no tienen `role="tablist"`, ni
     `aria-selected`, ni flechas—. Los componentes reales hacen las dos cosas bien:
     este existe para que la pantalla los USE en vez de reimplementarlos.

     Qué pone el componente y qué pone el anfitrión:

     · El componente pone la ESTRUCTURA (las pestañas con su teclado, el nombre de
       cada panel) y las DOS interacciones que la demo hacía mal o no tenía: el
       cierre de una sesión ajena con confirmación en diálogo, y la zona de peligro
       que exige escribir una palabra.
     · El anfitrión pone las TARJETAS de cada sección, cada una con su propio
       guardar: `<x-muni::card>` + su `<form method="post">` + `@@csrf`. Es lo que la
       ficha toma de la referencia de Flowbite y es lo que evita perder el trabajo de
       las otras tarjetas cuando una falla. El paquete no las hornea porque los
       campos son del sistema, no del catálogo.

     Cuatro decisiones que no son de gusto:

     1. CERRAR UNA SESIÓN ES UN POST CON TOKEN, no un enlace ni un `@@click`. La ficha
        marca el riesgo con todas las letras: «cerrar sesión debe invalidar el token
        en el servidor de verdad, no solo sacar la fila de la lista». Por eso acá no
        existe ningún camino que solo toque el DOM. Con el JS apagado el botón envía
        el formulario directo, y para que eso sea CIERTO y no una frase, el bloque de
        estilos trae un `<noscript>` que revela los paneles: `tab-panel` emite
        `x-cloak` y las dos hojas lo esconden con `display:none !important`, así que
        sin esa regla no se alcanzaba ni el POST ni la zona de peligro.
        La confirmación es un `<x-muni::modal role="alertdialog">`: foco
        atrapado, Escape en el elemento y sin cierre al clic en el fondo. Como el
        modal se teletransporta a `<body>`, el botón que confirma queda FUERA del
        formulario y lo apunta con `form=`, que es HTML nativo.
     2. LA SESIÓN ACTUAL NO SE CIERRA DESDE ACÁ. Es el botón que se aprieta por error;
        salir de la propia sesión es otra cosa y vive en la barra.
     3. LA ZONA DE PELIGRO USA `aria-disabled`, NO `disabled`. Un botón `disabled` no
        recibe foco, no lo anuncia un lector y —si el JS está activo pero Alpine no
        llegó a montar, que es el caso que ningún `<noscript>` puede distinguir— deja
        la acción muerta para siempre. Acá el botón se alcanza siempre, dice en texto
        por qué no procede, y el envío se bloquea mientras la palabra no coincida
        EXACTA. La puerta de verdad sigue siendo la validación del servidor: el
        cliente solo evita el clic sin querer.
     4. NADA DEL ANFITRIÓN ENTRA EN UNA EXPRESIÓN DE ALPINE. «Región de O'Higgins»
        está en la ubicación de cualquier sesión de la VI Región, y un apóstrofo
        dentro de una cadena de comillas simples tumba el Alpine de la página ENTERA
        (ya pasó en `file-dropzone`). Los textos viajan como contenido Blade escapado;
        la palabra destructiva, por `@@js()`; y los ids que sí entran en una expresión
        se saneán a `[A-Za-z0-9_-]` antes.

     Lo que el componente NO hace, a propósito:

     · No consulta `request()`, ni `Auth`, ni `Gate`, ni permisos: quién es el
       funcionario, qué sesiones tiene abiertas y si puede borrarse la cuenta se
       deciden en el anfitrión (Ley 21.719: minimización y base de licitud explícita).
       Recibe strings ya redactados, nunca un modelo ni un registro completo.
     · No decide si el bloque de sesiones se muestra. Ese bloque enseña al propio
       funcionario su IP y su ubicación aproximada: el anfitrión decide si eso se
       publica y con qué grano (ciudad, no calle).
     · No emite `<h1>`: el único de la página lo pone `<x-muni::page-header>`. Las
       tarjetas emiten `<h3>`, que es el nivel que corresponde debajo de él.
     · No guarda nada en `localStorage` ni `sessionStorage`. --}}

@php
    /*
     * El id del grupo. Manda el del consumidor, SANEADO: entra en una expresión de
     * Alpine (el `$dispatch` que abre el diálogo), y un apóstrofo ahí tumba el Alpine
     * de la página entera. Sin `id`, se deriva de los rótulos: estable entre renders,
     * que es lo que pide el `aria-controls` y el diffing de Livewire.
     */
    $muniAjcId = trim((string) $id);

    if ($muniAjcId !== '') {
        $muniAjcId = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $muniAjcId), '-');
    }

    if ($muniAjcId === '') {
        $muniAjcId = 'muni-ajc-'.substr(sha1(json_encode([
            $perfilLabel, $seguridadLabel, $preferenciasLabel, $peligroLabel,
        ], JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
    }

    /*
     * Las sesiones, normalizadas. Cada campo se lee con `??` porque el anfitrión
     * arma el arreglo a mano y una clave que falta no puede reventar la pantalla de
     * la cuenta. `current` se normaliza con filter_var: escrito como texto en el
     * Blade del anfitrión, "false" es truthy en PHP y la sesión propia habría
     * ofrecido cerrarse.
     */
    $muniAjcSesiones = [];

    foreach ((array) $sessions as $sesion) {
        $sesion = (array) $sesion;

        $muniAjcSesiones[] = [
            'id' => (string) ($sesion['id'] ?? ''),
            'device' => (string) ($sesion['device'] ?? ''),
            'detail' => (string) ($sesion['detail'] ?? ''),
            'current' => filter_var($sesion['current'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    $muniAjcHaySesiones = $muniAjcSesiones !== [];

    /* Las AJENAS, que son las únicas que se pueden cerrar desde acá. El mensaje de
       vacío cuelga de este número y no del total: contando el total, una lista con
       una sola sesión ajena imprimía el formulario para cerrarla Y, debajo, «No hay
       otras sesiones abiertas.». Al funcionario se le decía que no tenía otras
       sesiones mientras la pantalla le estaba listando una, y este bloque es
       justamente el que la ficha marca como el de riesgo. */
    $muniAjcAjenas = count(array_filter($muniAjcSesiones, fn (array $s) => ! $s['current']));

    /* La palabra de la zona de peligro. Sin ella no hay bloque: una acción
       irreversible sin puerta no la sirve el paquete. */
    $muniAjcPalabra = trim((string) $dangerWord);
    $muniAjcHayPeligro = filled($dangerAction) && $muniAjcPalabra !== '';

    /* El método del formulario destructivo. `delete` es lo correcto para una baja,
       y en HTML se escribe como POST más el campo que Laravel lee (@method). Un
       método desconocido cae en POST: nunca en GET, que un prefetch dispararía. */
    $muniAjcMetodo = strtolower(trim((string) $dangerMethod));
    $muniAjcSuplantar = in_array($muniAjcMetodo, ['put', 'patch', 'delete'], true) ? $muniAjcMetodo : null;

    /*
     * Las secciones presentes, en orden fijo. Una pestaña sin contenido no se
     * monta: sería una parada de tabulación que no lleva a ninguna parte.
     */
    $muniAjcSecciones = [];

    if (isset($perfil) || trim((string) $slot) !== '') {
        $muniAjcSecciones[] = ['clave' => 'perfil', 'label' => $perfilLabel];
    }

    if (isset($seguridad) || $muniAjcHaySesiones) {
        $muniAjcSecciones[] = ['clave' => 'seguridad', 'label' => $seguridadLabel];
    }

    if (isset($preferencias)) {
        $muniAjcSecciones[] = ['clave' => 'preferencias', 'label' => $preferenciasLabel];
    }

    if (isset($peligro) || $muniAjcHayPeligro) {
        $muniAjcSecciones[] = ['clave' => 'peligro', 'label' => $peligroLabel];
    }

    /* Sin ninguna sección no hay pantalla: se monta la primera igual, para que el
       contenido de la ranura por defecto —o el hueco— tenga dónde caer y el
       componente nunca renderice un tablist vacío. */
    if ($muniAjcSecciones === []) {
        $muniAjcSecciones[] = ['clave' => 'perfil', 'label' => $perfilLabel];
    }

    $muniAjcEtiquetas = array_column($muniAjcSecciones, 'label');

    $muniAjcActivo = (int) $default;
    $muniAjcActivo = max(0, min($muniAjcActivo, max(0, count($muniAjcSecciones) - 1)));

    /* El aviso de la zona de peligro se arma en PHP y viaja por @js: así el texto
       —que lleva la palabra del anfitrión— nunca se concatena dentro de la
       expresión de Alpine. */
    $muniAjcRotuloCampo = 'Escribe «'.$muniAjcPalabra.'» para confirmar';
    /* La pista por defecto describe la comparación que de verdad hace el componente
       —exacta, carácter por carácter— sin afirmar nada sobre la forma de la palabra,
       que la elige el anfitrión. Con `danger-hint` se reemplaza entera. */
    $muniAjcPistaCampo = filled($dangerHint)
        ? (string) $dangerHint
        : 'Tal cual, respetando mayúsculas y minúsculas. Es el único modo de habilitar el botón.';
    $muniAjcAvisoFalta = 'Todavía no coincide: escribe «'.$muniAjcPalabra.'» tal cual para continuar.';
@endphp

<div {{ $attributes->merge(['class' => 'muni-ajc']) }}>
    {{-- SEXTA TRAMPA DE BLADE, y cuesta una tarde encontrarla: la etiqueta de
         apertura de un componente compila a `if ($component->shouldRender()):` SIN su
         `endif`, que lo emite la etiqueta de cierre. Abrir la etiqueta dentro de un
         `@if` y cerrarla dentro de otro deja ese `if` abierto, el `@endif` propio
         cierra el ajeno, y a partir de ahí la vista entera queda dentro de una
         condición que nadie escribió: se renderiza el contenedor y NADA adentro, sin
         un solo error. Por eso las pestañas se montan siempre, también cuando hay una
         sola sección: la alternativa era duplicar el cuerpo de cada sección en las
         dos ramas. --}}
    <x-muni::tabs :tabs="$muniAjcEtiquetas" :id="$muniAjcId" :label="$label" :default="$muniAjcActivo">
    @foreach ($muniAjcSecciones as $muniAjcPos => $muniAjcSeccion)
        <x-muni::tab-panel :index="$muniAjcPos">

        @if ($muniAjcSeccion['clave'] === 'perfil')
            @if (trim((string) $slot) !== ''){{ $slot }}@endif
            @isset($perfil){{ $perfil }}@endisset
        @endif

        @if ($muniAjcSeccion['clave'] === 'seguridad')
            @isset($seguridad){{ $seguridad }}@endisset

            @if ($muniAjcHaySesiones)
                {{-- El envoltorio existe porque `card` escribe su `class="muni-card"` ANTES
                     del derrame de atributos: ante dos `class` en la misma etiqueta el parser
                     se queda con el PRIMERO, así que una clase pasada a la tarjeta se pierde
                     en silencio (DESIGN §8). El aire entre bloques va acá. --}}
                <div class="muni-ajc__bloque">
                <x-muni::card :title="$sessionsTitle" :subtitle="$sessionsDescription">
                    <ul class="muni-ajc__sesiones">
                        @foreach ($muniAjcSesiones as $muniAjcI => $muniAjcSesion)
                            @php
                                /* Los ids salen de la POSICIÓN, no del identificador de sesión: el del
                                   anfitrión puede traer cualquier cosa y de acá sale un id de DOM y una
                                   cadena dentro de una expresión de Alpine. */
                                $muniAjcFormId = $muniAjcId.'-sesion-'.$muniAjcI;
                                $muniAjcModalId = $muniAjcId.'-cerrar-'.$muniAjcI;
                                $muniAjcPregunta = trim('¿Cerrar la sesión de '.$muniAjcSesion['device'].'?');
                            @endphp

                            <li class="muni-ajc__sesion">
                                <span class="muni-ajc__icono" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><rect x="2.8" y="4.5" width="18.4" height="12" rx="2"/><path d="M8.5 20h7" stroke-linecap="round"/></svg>
                                </span>

                                <span class="muni-ajc__datos">
                                    <span class="muni-ajc__disp">{{ $muniAjcSesion['device'] }}</span>
                                    @if ($muniAjcSesion['detail'] !== '')
                                        <span class="muni-ajc__meta">{{ $muniAjcSesion['detail'] }}</span>
                                    @endif
                                </span>

                                @if ($muniAjcSesion['current'])
                                    {{-- Texto, no un punto de color: el estado nunca se comunica solo con color. --}}
                                    <span class="muni-ajc__marca">{{ $currentLabel }}</span>
                                @elseif (filled($sessionsAction))
                                    {{-- `x-data` vacío: le da ámbito propio de Alpine al formulario, para
                                         que el `$dispatch` del botón no dependa de que haya un x-data por
                                         encima (Alpine solo inicializa lo que cuelga de uno). Sin Alpine,
                                         el botón envía directo: el POST sigue siendo el único camino, y el
                                         servidor es quien invalida el token. --}}
                                    <form method="post" action="{{ $sessionsAction }}" id="{{ $muniAjcFormId }}" class="muni-ajc__form" x-data>
                                        @csrf
                                        <input type="hidden" name="sesion" value="{{ $muniAjcSesion['id'] }}">
                                        <x-muni::button
                                            type="submit"
                                            variant="danger"
                                            size="sm"
                                            class="muni-ajc__accion"
                                            x-on:click.prevent="$dispatch('muni-modal-open', { id: '{{ $muniAjcModalId }}' })"
                                        >{{ $closeLabel }}</x-muni::button>
                                    </form>

                                    <x-muni::modal
                                        :id="$muniAjcModalId"
                                        :title="$muniAjcPregunta"
                                        role="alertdialog"
                                        :dismissable="false"
                                        initial-focus="cancelar"
                                    >
                                        <p class="muni-ajc__parrafo">{{ $closeExplanation }}</p>
                                        @if ($muniAjcSesion['detail'] !== '')
                                            <p class="muni-ajc__meta">{{ $muniAjcSesion['detail'] }}</p>
                                        @endif

                                        <x-slot:footer>
                                            {{-- El diálogo vive teletransportado en <body>, fuera del <form>:
                                                 `form=` es HTML nativo y es lo que hace que confirmar envíe. --}}
                                            <x-muni::button type="submit" variant="danger" form="{{ $muniAjcFormId }}" class="muni-ajc__accion">{{ $closeLabel }}</x-muni::button>
                                        </x-slot:footer>
                                    </x-muni::modal>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if ($muniAjcAjenas === 0)
                        <p class="muni-ajc__meta muni-ajc__vacio">{{ $sessionsEmpty }}</p>
                    @endif
                </x-muni::card>
                </div>
            @endif
        @endif

        @if ($muniAjcSeccion['clave'] === 'preferencias')
            @isset($preferencias){{ $preferencias }}@endisset
        @endif

        @if ($muniAjcSeccion['clave'] === 'peligro')
            @isset($peligro){{ $peligro }}@endisset

            @if ($muniAjcHayPeligro)
                <div class="muni-ajc__bloque">
                <x-muni::card :title="$dangerTitle" :subtitle="$dangerDescription">
                    {{-- La banda al borde es la firma del sistema (DESIGN §9), y el portador
                         del estado es el texto: el título dice «Zona de peligro» y el botón
                         dice qué destruye. --}}
                    <div class="muni-ajc__peligro">
                        <form
                            method="post"
                            action="{{ $dangerAction }}"
                            class="muni-ajc__forma"
                            x-data="{
                                escrito: '',
                                palabra: @js($muniAjcPalabra),
                                aviso: '',
                                get coincide() { return this.escrito.trim() === this.palabra; },
                                intentar(evento) {
                                    if (this.coincide) return;

                                    evento.preventDefault();

                                    /* Vaciar y reescribir en el ciclo siguiente: si el texto es
                                       idéntico al que ya estaba no hay mutación de DOM y el lector
                                       de pantalla no vuelve a hablar. */
                                    this.aviso = '';
                                    this.$nextTick(() => { this.aviso = @js($muniAjcAvisoFalta); });

                                    if (this.$refs.campo) this.$refs.campo.focus();
                                }
                            }"
                            x-on:submit="intentar($event)"
                        >
                            @csrf
                            @if ($muniAjcSuplantar)
                                @method($muniAjcSuplantar)
                            @endif

                            <x-muni::input
                                :label="$muniAjcRotuloCampo"
                                :name="$dangerName"
                                :hint="$muniAjcPistaCampo"
                                :id="$muniAjcId.'-palabra'"
                                autocomplete="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                x-model="escrito"
                                x-ref="campo"
                            />

                            {{-- Nace vacía: una región viva que aparece junto a su texto no se
                                 anuncia en NVDA, JAWS ni VoiceOver. Y reserva su línea, para que
                                 el aviso no empuje el botón hacia abajo cuando llega (CLS). --}}
                            <p class="muni-ajc__aviso" role="status" aria-live="polite" x-text="aviso"></p>

                            <x-muni::button
                                type="submit"
                                variant="danger"
                                class="muni-ajc__accion muni-ajc__destruir"
                                {{-- Cadena y no booleano: con `false` Alpine QUITA el atributo en vez
                                     de escribir aria-disabled="false" (solo preserva aria-pressed,
                                     -checked, -expanded y -selected). Ausente significa lo mismo, pero
                                     un atributo que aparece y desaparece es más difícil de probar y de
                                     depurar que uno que siempre está y cambia de valor. --}}
                                x-bind:aria-disabled="coincide ? 'false' : 'true'"
                                x-bind:class="! coincide && 'muni-ajc__destruir--espera'"
                            >{{ $dangerLabel }}</x-muni::button>
                        </form>
                    </div>
                </x-muni::card>
                </div>
            @endif
        @endif

        </x-muni::tab-panel>
    @endforeach
    </x-muni::tabs>
</div>

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css, y una clase declarada únicamente en muni-ui.css
           se vería sin estilo y sin un solo error en consola (DESIGN §7). */
        .muni-ajc { font-family:var(--muni-font-sans); color:var(--muni-text); }
        .muni-ajc__bloque { margin-bottom:14px; }
        .muni-ajc__bloque:last-child { margin-bottom:0; }
        .muni-ajc__sesiones { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; }
        .muni-ajc__sesion { display:flex; align-items:center; gap:12px; flex-wrap:wrap;
            padding:10px 0; border-bottom:1px solid var(--muni-border); }
        .muni-ajc__sesion:last-child { border-bottom:none; }
        .muni-ajc__icono { display:inline-flex; flex-shrink:0; color:var(--muni-muted); }
        .muni-ajc__datos { display:flex; flex-direction:column; gap:2px; min-width:0; flex:1 1 200px; }
        .muni-ajc__disp { font-size:13.5px; font-weight:700; color:var(--muni-text); }
        .muni-ajc__meta { margin:0; font-size:12px; line-height:1.45; color:var(--muni-muted); }
        .muni-ajc__vacio { margin-top:10px; }
        .muni-ajc__parrafo { margin:0 0 6px; font-size:13.5px; line-height:1.55; color:var(--muni-text); }
        /* El estado de «este equipo» es TEXTO: el color solo acompaña. */
        .muni-ajc__marca { flex-shrink:0; padding:4px 10px; border-radius:999px;
            font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
            color:var(--muni-ok-fg); background:var(--muni-ok-bg); border:1px solid var(--muni-ok-border); }
        .muni-ajc__form { margin:0; flex-shrink:0; }
        .muni-ajc__forma { display:flex; flex-direction:column; gap:10px; align-items:flex-start; }
        /* Blanco de terreno: cerrar una sesión y borrar una cuenta son destructivas,
           y 24px es justo el tamaño que se aprieta por error (WCAG 2.2 AA 2.5.8). */
        .muni-ajc__accion { min-height:44px; }
        /* La banda al borde, como en una fila con problema (DESIGN §9). */
        .muni-ajc__peligro { border-left:3px solid var(--muni-danger-fg); padding-left:14px; }
        .muni-ajc__aviso { margin:0; min-height:1.4em; font-size:12.5px; font-weight:600; color:var(--muni-danger-fg); }
        /* Mientras la palabra no coincide el botón sigue enfocable y anunciado
           (aria-disabled), solo se ve en espera: `disabled` lo sacaría del orden de
           tabulación y lo dejaría mudo.

           El estado en espera se dibuja con el BORDE, no bajando la opacidad: medido
           con la reja sobre el banco, `opacity:.55` hundía «Eliminar mi cuenta» a
           2,46:1 en claro y 2,31:1 en oscuro sobre su propio fondo (WCAG 1.4.3 pide
           4,5:1). Y el estado no lo comunica el dibujo solo: está el aria-disabled, la
           pista bajo el campo y el aviso en texto al intentar.

           Los DOS `!important` son obligatorios, y por el mismo mecanismo que el
           bloque de movimiento reducido de más abajo: `button` escribe
           `border:1px solid transparent;` y `cursor:pointer;` en el atributo `style`,
           que gana a cualquier regla de autor que no lo lleve. Sin ellos, medido en
           Chromium sobre el banco, el botón computaba `border-top-style:solid` y
           `cursor:pointer` en espera: se veía IDÉNTICO al habilitado y el reemplazo
           de la opacidad no existía. El `border-color` va explícito porque el borde
           en línea es transparente: dibujar un borde discontinuo invisible no es
           dibujar nada. */
        .muni-ajc__destruir--espera { border-style:dashed !important; border-color:var(--muni-danger-fg) !important; }
        .muni-ajc__destruir[aria-disabled="true"] { cursor:not-allowed !important; }
        /* El outline es el indicador REAL: la box-shadow del anillo se computa
           transparente dentro de Filament (DESIGN §5). */
        .muni-ajc__destruir:focus-visible, .muni-ajc__accion:focus-visible {
            outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        @media (max-width:480px) {
            .muni-ajc__sesion { align-items:flex-start; }
            .muni-ajc__form, .muni-ajc__form .muni-ajc__accion { width:100%; }
        }
        /* El `!important` no es pereza: `button` escribe su `transition` en el atributo
           `style`, que gana a cualquier clase, y `muni-ui-filament.css` —la única hoja
           que se carga dentro de un panel— NO baja `--muni-dur` con movimiento
           reducido. Sin esto, los dos botones destructivos de esta pantalla siguen
           animando 160 ms dentro del panel con la preferencia puesta; medido en
           Chromium y Firefox. */
        @media (prefers-reduced-motion:reduce) {
            .muni-ajc__destruir, .muni-ajc__accion { transition:none !important; }
        }
        /* En papel no hay nada que apretar y la lista de sesiones es dato personal
           que no debería quedar impreso sobre un escritorio. */
        @media print {
            .muni-ajc__accion, .muni-ajc__aviso, .muni-ajc__peligro { display:none !important; }
        }
    </style>

    {{-- SIN JAVASCRIPT, LOS PANELES SE REVELAN. Sin esto la frase «sin JS el POST
         envía igual» era falsa y medible: `tab-panel` emite `x-cloak`, y las DOS
         hojas del paquete declaran `[x-cloak] { display:none !important }`, así que
         con el JS apagado no se veía —ni se alcanzaba— ni el formulario que cierra
         una sesión ni la zona de peligro. La regla de acá gana por especificidad
         (0,3,1 contra 0,1,0) y deja las cuatro secciones apiladas, cada una con el
         título de su tarjeta.

         El grupo de pestañas se oculta porque sin Alpine sus botones no hacen nada:
         serían cuatro paradas de tabulación mudas. Siguen sirviendo de nombre a cada
         panel: el cálculo del nombre accesible SÍ recorre lo referenciado por
         `aria-labelledby` aunque esté oculto.

         Lo que esto NO cubre, y conviene decirlo: si el JS está activo pero Alpine
         no llega a montar, `<noscript>` no aplica y no hay forma de distinguir ese
         caso desde CSS. La pantalla queda entonces como cualquier `tabs` del
         paquete. Por eso la puerta de la zona de peligro no puede ser `disabled`:
         el servidor es el que valida, y el botón tiene que llegar vivo hasta él. --}}
    <noscript>
        <style>
            .muni-ajc [role="tabpanel"][x-cloak] { display:block !important; }
            .muni-ajc [role="tabpanel"] + [role="tabpanel"] {
                margin-top:18px; padding-top:18px; border-top:1px solid var(--muni-border); }
            .muni-ajc [role="tablist"] { display:none !important; }
        </style>
    </noscript>
@endonce
