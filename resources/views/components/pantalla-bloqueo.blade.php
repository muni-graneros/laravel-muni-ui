@props([
    'nombre',
    'cargo' => null,
    'avatar' => null,
    'modo' => 'capa',
    'abierto' => false,
    'inactividad' => null,
    'accion' => null,
    'campo' => 'password',
    'etiqueta' => 'Contraseña',
    'error' => null,
    'titulo' => 'Pantalla bloqueada',
    'mensaje' => null,
    'entrar' => 'Entrar',
    'otroUrl' => null,
    'otroRotulo' => 'Entrar como otro usuario',
    'otroMetodo' => 'post',
    'autofoco' => true,
])

@php
    /*
     |--------------------------------------------------------------------------
     | EL CANDADO DEL MESÓN
     |--------------------------------------------------------------------------
     |
     | Ficha `pantalla-bloqueo` de docs/GAP-ANALYSIS.md. Bloquea la sesión SIN
     | cerrarla: se ve quién está conectado, se pide su contraseña para volver y
     | hay una salida explícita para entrar como otra persona.
     |
     | `sesion-guardia` cubre la sesión que CADUCA; esta cubre la que sigue viva
     | mientras el funcionario no está delante de la pantalla. En un mesón
     | municipal esa pantalla abierta es una fuga de datos personales del vecino
     | que está siendo atendido (Ley 21.719), y hasta ahora la única defensa del
     | paquete era cerrar sesión y perder el trabajo en curso.
     |
     | DOS MODOS, y la ficha los distingue:
     |
     |   modo="capa"   (por defecto) diálogo modal encima de lo que estaba: rol
     |                 dialog, aria-modal, x-trap.inert —«inert de verdad, no
     |                 solo tapado»— y velo OPACO. Alpine 3 core + plugin Focus,
     |                 que viaja en el bundle de Livewire.
     |   modo="pagina" la misma tarjeta como pantalla propia, dentro de
     |                 `<x-muni::auth-shell>`. SIN Alpine y sin trampa: no hay
     |                 nada detrás de lo que atrapar el foco.
     |
     | LO QUE ESTE COMPONENTE NO ES: una defensa del servidor. Bloquear en el
     | navegador impide leer la pantalla desde el otro lado del mesón, que es el
     | riesgo real de la ficha; NO impide nada a quien tenga el teclado y las
     | herramientas del navegador, porque el HTML de atrás sigue en el documento.
     | La contraseña la valida el anfitrión, en el servidor, contra el usuario de
     | la sesión en curso, con `throttle` por intentos —el bloqueo por fuerza
     | bruta vive ahí— y sin mirar la cookie de «recordarme»: ese es el gotcha
     | que la ficha marca como fatal acá. Por eso el componente no emite casilla
     | de recordarme ni ningún otro camino: se escribe la contraseña, o se entra
     | como otra persona. Para datos que no pueden quedar ni en el DOM, lo que
     | corresponde es cerrar sesión, no bloquear.
     |
     | El desbloqueo es un POST con token CSRF a `accion` y funciona SIN
     | JavaScript: recarga la página y el anfitrión decide. La salida también es
     | POST por defecto, porque cerrar sesión cambia el estado del servidor y un
     | GET se dispara con una imagen remota; con `otro-metodo="get"` es un
     | enlace, para el anfitrión que solo manda a una pantalla de ingreso.
     |
     | ESCAPE NO CIERRA. Es la única tecla que va al revés que en el resto del
     | paquete —un bloqueo que se descarta con una tecla no es un bloqueo—, pero
     | el manejador existe igual, EN el diálogo y no en `window` (DESIGN §8): sin
     | él la tecla sigue hacia lo que haya debajo y cierra otra cosa.
     |
     | Ningún texto del anfitrión entra en una expresión de Alpine: el nombre, el
     | cargo y los rótulos son contenido Blade escapado, y lo único que viaja por
     | la interpolación segura es el id. Un apóstrofo de un apellido —«D'Angelo»—
     | dentro de una cadena de comillas simples tumba el Alpine de la página
     | ENTERA; ya pasó en `file-dropzone`.
     |
     | POR QUÉ LA TARJETA SE ABRE Y SE CIERRA EN RAMAS DISTINTAS más abajo: es el
     | mismo marcado en los dos modos y lo único que cambia es lo que lo envuelve.
     | Sacarlo a una vista parcial no se puede sin efectos: en este paquete
     | `resources/views/components/` está registrado entero como componentes
     | anónimos (`Blade::anonymousComponentPath`), así que un parcial ahí dentro
     | nace como un `<x-muni::…>` público que las pruebas del paquete intentan
     | renderizar suelto. Y duplicar la tarjeta dejaría dos verdades del mismo
     | formulario, que es como divergen los componentes.
     */

    $esCapa = $modo !== 'pagina';

    /* El id sale de las props, NUNCA de uniqid() (DESIGN §10): con uniqid() cambia en
       cada render, rompe el for/id de la etiqueta y ensucia el diffing de Livewire.
       El que pase el consumidor manda. */
    $id = $attributes->get('id') ?: 'muni-bloqueo-'.substr(sha1(((string) $nombre).'|'.((string) $campo)), 0, 8);
    $campoId = $id.'-clave';
    $errorId = $id.'-error';
    $tituloId = $id.'-titulo';
    $descId = $id.'-desc';

    /* Props escritas sin dos puntos llegan como cadena, y "false" en PHP es truthy. */
    $abierto = filter_var($abierto, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $abierto;
    $autofoco = filter_var($autofoco, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $autofoco;

    /* Bloqueo por inactividad: segundos. 0 o nulo lo apaga, y entonces el bloqueo
       llega del anfitrión (prop `abierto` desde el servidor, o el evento de navegador
       `muni-bloqueo-abrir`). Solo aplica al modo capa. */
    $inactividadSegundos = max(0, (int) $inactividad);

    $mensaje = $mensaje ?: 'Tu sesión sigue abierta. Escribe tu contraseña para volver a donde estabas.';

    /* En la capa el título es un <h2>: el <h1> es el de la página que quedó debajo, y
       dos <h1> en el mismo documento dejan la pantalla sin un encabezado de primer
       nivel que signifique algo. Como página propia, el <h1> es este. */
    $encabezado = $esCapa ? 'h2' : 'h1';

    $salidaPorEnlace = strtolower((string) $otroMetodo) === 'get';

    /* En la capa el `autofocus` es el contrato de x-trap para el foco inicial, no el
       del navegador: el diálogo nace dentro de un <template>, así que al cargar la
       página nadie lo enfoca. Como página propia sí es el del navegador, y por eso se
       puede apagar: el anfitrión que muestre esto como una tarjeta dentro de otra
       pantalla no quiere que le arranque el scroll. */
    $conAutofocus = $esCapa || $autofoco;

    /* El nombre accesible de la sección va por ->merge() y NUNCA escrito a mano antes
       del derrame (DESIGN §8): a mano salen DOS aria-labelledby en el mismo elemento y
       el navegador se queda con el primero, así que el del anfitrión se pierde en
       silencio. Un aria-labelledby vacío se saca de la bolsa. */
    if (trim((string) $attributes->get('aria-labelledby')) === '') {
        $attributes = $attributes->except('aria-labelledby');
    }
@endphp

@if ($esCapa)
    <div
        id="{{ $id }}"
        x-data="{
            bloqueado: {{ $abierto ? 'true' : 'false' }},
            inactividad: {{ $inactividadSegundos }},
            id: @js($id),
            campoId: @js($campoId),
            anuncio: '',
            reloj: null,
            escuchas: [],
            /* `cedido` = otra capa de pantalla completa se puso encima DESPUÉS que
               nosotros (ver `vigilarSuperposicion`). Mientras dure, este bloqueo deja de
               pintar arriba y destapa a quien manda; sigue tapando la ficha del vecino. */
            cedido: false,
            raiz: null,
            destapados: [],

            init() {
                if (this.bloqueado) this.enfocarClave();
                if (this.inactividad > 0) this.vigilar();
            },

            destroy() {
                clearTimeout(this.reloj);
                this.escuchas.forEach((quitar) => quitar());
            },

            paraMi(evento) {
                return ! (evento && evento.detail && evento.detail.id) || evento.detail.id === this.id;
            },

            vigilar() {
                const reiniciar = () => this.reiniciar();
                for (const nombre of ['pointerdown', 'keydown', 'wheel', 'touchstart', 'focusin']) {
                    document.addEventListener(nombre, reiniciar, { capture: true, passive: true });
                    this.escuchas.push(() => document.removeEventListener(nombre, reiniciar, { capture: true }));
                }
                this.reiniciar();
            },

            reiniciar() {
                if (this.inactividad <= 0) return;
                clearTimeout(this.reloj);
                if (this.bloqueado) return;
                this.reloj = setTimeout(() => this.bloquear('inactividad'), this.inactividad * 1000);
            },

            bloquear(motivo) {
                if (this.bloqueado) return;
                clearTimeout(this.reloj);
                this.bloqueado = true;
                this.anuncio = motivo === 'inactividad'
                    ? 'Pantalla bloqueada por inactividad. Escribe tu contraseña para volver.'
                    : 'Pantalla bloqueada. Escribe tu contraseña para volver.';
                this.enfocarClave();
            },

            desbloquear() {
                if (! this.bloqueado) return;
                this.bloqueado = false;
                this.anuncio = '';
                this.reiniciar();
            },

            sigueBloqueado() {
                this.anuncio = '';
                this.$nextTick(() => { this.anuncio = 'La pantalla sigue bloqueada. Escribe tu contraseña para volver.'; });
            },

            /* LA CONVIVENCIA CON OTRA CAPA QUE ATRAPA EL FOCO —en el mesón, `sesion-guardia`
               cuando la sesión llega a T=0 con la pantalla ya bloqueada, que no es un caso
               raro sino LA secuencia normal: si nadie toca el teclado salta el bloqueo por
               inactividad y el reloj de la sesión sigue corriendo hasta el final.
               Medido en Chromium y en Firefox (tests/navegador/pantalla-bloqueo.py, páginas
               `con-guardia` y `con-guardia-antes`): las dos capas se teletransportan al
               <body>, las dos arman su trampa, y el `inert` de cada una estampa un
               aria-hidden sobre la raíz de la otra. Resultado ANTES de esto: NINGUNO de los
               dos diálogos quedaba en el árbol de accesibilidad —el funcionario veía un
               diálogo con el foco dentro que su lector de pantalla no anunciaba— y cuál de
               los dos velos tapaba al otro lo decidía el orden en que el anfitrión escribió
               los componentes.
               Quién manda NO lo decide este componente: lo decide el plugin. `focus-trap`
               pausa la trampa anterior cuando se activa una nueva, así que manda quien tiene
               el foco. Lo único que hace esto es que las otras dos señales —qué se pinta
               arriba y qué puede anunciar el lector— digan LO MISMO que el foco:

                 · si el foco está fuera de esta capa, cede: baja un peldaño de pintura, se
                   calla y le devuelve el árbol a la capa que manda quitándole el aria-hidden
                   que le puso la trampa de este componente;
                 · si el foco es suyo, manda: vuelve a tapar lo que destapó y se quita de
                   encima el aria-hidden ajeno que quedó obsoleto.

               Lo que NUNCA hace es soltar su propia trampa: el `inert` de Alpine deshace con
               una caché booleana, así que soltarla destaparía la ficha del vecino que quedó
               debajo aunque la otra capa siga viva (medido). Y tampoco desbloquea nada: el
               velo opaco sigue puesto en los dos casos.
               Al anfitrión que monte los dos en la misma pantalla le sigue tocando lo suyo:
               cuando la sesión muere, la contraseña de este bloqueo ya no sirve para nada,
               así que lo correcto es cerrarlo (`muni-bloqueo-cerrar`) y dejar sola a la
               guardia. Esto es la red de seguridad para cuando no lo haga. */
            vigilarSuperposicion(raiz) {
                this.raiz = raiz;

                const revisar = () => {
                    const activo = document.activeElement;
                    const ajeno = this.bloqueado && activo && activo !== document.body && ! raiz.contains(activo);
                    if (ajeno) this.ceder(); else this.mandar();
                };

                /* El aria-hidden puede llegar ANTES que el foco (la otra capa reintenta
                   enfocar por frames), así que se mira por los dos lados. */
                const observador = new MutationObserver(revisar);
                observador.observe(raiz, { attributes: true, attributeFilter: ['aria-hidden'] });
                this.escuchas.push(() => observador.disconnect());

                document.addEventListener('focusin', revisar, { capture: true, passive: true });
                this.escuchas.push(() => document.removeEventListener('focusin', revisar, { capture: true }));

                revisar();
            },

            ceder() {
                if (! this.raiz) return;

                if (! this.cedido) {
                    this.cedido = true;
                    this.raiz.setAttribute('aria-hidden', 'true');
                }

                /* Fuera del `if`: cuando el aria-hidden ajeno llega ANTES que el foco, la
                   primera pasada cede sin nadie a quien destapar, y es la segunda —la del
                   focusin— la que sabe quién manda. Con esto dentro del `if` no se destapaba
                   a nadie nunca, y el diálogo de la guardia se quedaba mudo (medido). */
                this.destaparAlQueManda();
            },

            /* Destapa SOLO la capa que tiene el foco dentro, y solo si es hermana nuestra en
               el <body>, que es donde teletransportan las capas del paquete: el aria-hidden
               que lleva se lo puso la trampa de este componente. Todo lo demás —la ficha del
               vecino incluida— sigue fuera del árbol de accesibilidad. */
            destaparAlQueManda() {
                const activo = document.activeElement;
                if (! activo || activo === document.body) return;

                for (const hermano of Array.from(document.body.children)) {
                    if (hermano === this.raiz) continue;
                    if (hermano.getAttribute('aria-hidden') !== 'true') continue;
                    if (! hermano.contains(activo)) continue;
                    hermano.removeAttribute('aria-hidden');
                    if (! this.destapados.includes(hermano)) this.destapados.push(hermano);
                }
            },

            mandar() {
                if (this.cedido) {
                    this.cedido = false;
                    this.destapados.forEach((el) => { if (el.isConnected) el.setAttribute('aria-hidden', 'true'); });
                    this.destapados = [];
                }

                /* Si otra trampa nos marcó y el foco terminó siendo nuestro, esa marca quedó
                   obsoleta: sin quitarla, este diálogo tiene el foco y el lector no lo lee. */
                if (this.bloqueado && this.raiz && this.raiz.getAttribute('aria-hidden') === 'true') {
                    this.raiz.removeAttribute('aria-hidden');
                }
            },

            enfocarClave() {
                let intentos = 20;
                const intentar = () => {
                    const destino = document.getElementById(this.campoId);
                    if (! destino || ! this.bloqueado) return;
                    destino.focus({ preventScroll: true });
                    if (document.activeElement === destino || --intentos <= 0) return;
                    requestAnimationFrame(intentar);
                };
                this.$nextTick(intentar);
            }
        }"
        @muni-bloqueo-abrir.window="paraMi($event) && bloquear('anfitrion')"
        @muni-bloqueo-cerrar.window="paraMi($event) && desbloquear()"
    >
        {{-- La raíz teleportada es la que atrapa, y no el diálogo: dentro de un contenedor
             con overflow o transform, un position:fixed se recorta y el bloqueo dejaría de
             tapar la pantalla. Que atrape la RAÍZ es lo que deja a la región viva DENTRO
             del elemento atrapado —es hermana del diálogo, pero las dos cuelgan de la
             raíz—: x-trap.inert pone aria-hidden en todo lo que queda a los lados hasta el
             <body>, así que una región viva por FUERA de la raíz quedaría muda justo cuando
             hay algo que anunciar (medido en `sesion-guardia`).
             El `x-init` no abre nada: engancha el vigilante de superposición (ver
             `vigilarSuperposicion`). --}}
        <template x-teleport="body">
            <div x-trap.inert.noscroll="bloqueado" x-init="vigilarSuperposicion($el)">
                <p class="muni-pb__sr" role="status" aria-live="polite" aria-atomic="true" x-text="anuncio"></p>

                <div
                    x-show="bloqueado"
                    x-cloak
                    :class="{ 'muni-pb__capa--cedida': cedido }"
                    x-transition:enter="muni-pb__fade" x-transition:enter-start="muni-pb__fade-0" x-transition:enter-end="muni-pb__fade-1"
                    x-transition:leave="muni-pb__fade" x-transition:leave-start="muni-pb__fade-1" x-transition:leave-end="muni-pb__fade-0"
                    role="dialog"
                    aria-modal="true"
                    aria-describedby="{{ $descId }}"
                    {{-- La bolsa del anfitrión va AQUÍ y no en la raíz con el x-data: después
                         del `x-teleport` esa raíz queda vacía en su sitio, así que una clase,
                         un estilo o un `aria-labelledby` puestos ahí no llegarían nunca ni al
                         diálogo ni al panel; se perderían en silencio, que es justo el fallo
                         mudo que el ->merge() existe para evitar. Escrito a mano saldrían DOS
                         aria-labelledby en el mismo elemento y el navegador se queda con el
                         primero (DESIGN §8). --}}
                    {{-- `display:none` va DENTRO del merge y no como un `style` aparte: con los
                         dos, un anfitrión que pasara `style` dejaría el atributo repetido y el
                         navegador se queda con el primero, así que el x-cloak se perdería y la
                         capa daría un destello antes de que Alpine hidrate. --}}
                    {{ $attributes->except('id')->merge([
                        'class' => 'muni-pb__capa',
                        'aria-labelledby' => $tituloId,
                        'style' => 'display:none;',
                    ]) }}
                    {{-- Escape NO cierra (ficha). `.prevent.stop` para que la tecla no siga
                         hacia un drawer o un modal del anfitrión que sí cierra con ella. El
                         plugin Focus arranca con `escapeDeactivates: false`, así que la
                         trampa tampoco se suelta sola. --}}
                    @keydown.escape.prevent.stop="sigueBloqueado()"
                >
                    <div class="muni-pb__velo" aria-hidden="true"></div>
                    <div class="muni-pb__panel">
@else
    <section {{ $attributes->merge(['class' => 'muni-pb', 'aria-labelledby' => $tituloId]) }}>
        <div class="muni-pb__panel">
@endif

                        <div class="muni-pb__identidad">
                            {{-- Decorativo: el nombre ya va en texto debajo, y `avatar` emite
                                 su propio aria-label, así que sin esto el lector lo diría dos
                                 veces. Sin `avatar` pinta las iniciales, que es lo que hace
                                 reconocible la sesión de un vistazo desde el mesón. --}}
                            <x-muni::avatar :name="$nombre" :src="$avatar" size="lg" aria-hidden="true" />
                            <p class="muni-pb__nombre">{{ $nombre }}</p>
                            @if ($cargo)
                                <p class="muni-pb__cargo">{{ $cargo }}</p>
                            @endif
                        </div>

                        <{{ $encabezado }} id="{{ $tituloId }}" class="muni-pb__titulo">
                            <svg class="muni-pb__candado" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7.5a4 4 0 018 0V11" stroke-linecap="round"/></svg>
                            {{ $titulo }}
                        </{{ $encabezado }}>

                        <p id="{{ $descId }}" class="muni-pb__desc">{{ $mensaje }}</p>

                        {{-- Sin `accion` el POST va a la misma URL: es lo correcto cuando el
                             anfitrión resuelve el desbloqueo en la ruta donde ya está. --}}
                        <form class="muni-pb__form" method="post" @if ($accion) action="{{ $accion }}" @endif>
                            @csrf
                            <label class="muni-pb__etiqueta" for="{{ $campoId }}">{{ $etiqueta }}</label>
                            <input
                                class="muni-pb__clave"
                                id="{{ $campoId }}"
                                name="{{ $campo }}"
                                type="password"
                                autocomplete="current-password"
                                required
                                @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                                @if ($conAutofocus) autofocus @endif
                            >
                            {{-- El error llega del servidor con la página, así que va como
                                 `role="alert"` y NO como una región viva vacía: una región que
                                 nace con su contenido no se anuncia, pero un role="alert" que
                                 aparece en el render inicial sí lo lee NVDA y VoiceOver. El
                                 portador es el TEXTO; el rojo solo acompaña (WCAG 1.4.1). --}}
                            @if ($error)
                                <p id="{{ $errorId }}" class="muni-pb__error" role="alert">{{ $error }}</p>
                            @endif
                            <button type="submit" class="muni-pb__entrar">{{ $entrar }}</button>
                        </form>

                        {{-- Sin `otro-url` no hay salida: un control que no lleva a ninguna
                             parte es peor que no ofrecerlo. Por defecto es un POST con token
                             CSRF, porque cerrar sesión cambia el estado del servidor. --}}
                        @if ($otroUrl)
                            @if ($salidaPorEnlace)
                                <p class="muni-pb__salida-form"><a class="muni-pb__salida" href="{{ $otroUrl }}">{{ $otroRotulo }}</a></p>
                            @else
                                <form class="muni-pb__salida-form" method="post" action="{{ $otroUrl }}">
                                    @csrf
                                    <button type="submit" class="muni-pb__salida">{{ $otroRotulo }}</button>
                                </form>
                            @endif
                        @endif

@if ($esCapa)
                    </div>
                </div>
            </div>
        </template>
    </div>
@else
        </div>
    </section>
@endif

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css, y una clase declarada únicamente en muni-ui.css
           se vería sin estilo y sin un solo error en consola (DESIGN §7). */
        .muni-pb__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        /* Por encima de toast-host (300), modal y drawer (200), a la altura del velo de
           T=0 de `sesion-guardia`: el bloqueo tiene que tapar también un aviso flotante
           que nombre al vecino. */
        .muni-pb__capa { position:fixed; inset:0; z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; font-family:var(--muni-font-sans); }
        /* El velo es el papel del tema y es OPACO: un tinte translúcido deja entrever el
           RUT y el domicilio del vecino desde el otro lado del mesón (Ley 21.719). Es la
           misma decisión que el velo de T=0 de `sesion-guardia`. */
        .muni-pb__velo { background:var(--muni-bg); opacity:1; position:absolute; inset:0; }
        /* Capa CEDIDA: otra capa de pantalla completa se puso encima después (ver
           `vigilarSuperposicion`). Baja dos peldaños para que esa otra pinte arriba SIEMPRE
           y no según quién se teletransportó primero al <body> —con el mismo z-index
           decidía el orden de inserción, o sea el orden en que el anfitrión escribió los
           dos componentes—, y se queda igual de opaca y por encima de todo lo demás del
           paquete (toast-host 300, modal y drawer 200): la ficha del vecino sigue tapada. */
        .muni-pb__capa--cedida { z-index:998; }
        .muni-pb__fade { transition:opacity var(--muni-dur) var(--muni-ease); }
        .muni-pb__fade-0 { opacity:0; }
        .muni-pb__fade-1 { opacity:1; }
        .muni-pb { display:flex; align-items:center; justify-content:center; padding:24px 20px; font-family:var(--muni-font-sans); }
        .muni-pb__panel { position:relative; width:100%; max-width:380px; padding:24px 24px 20px; text-align:center;
            background:var(--muni-surface); color:var(--muni-text); border:1px solid var(--muni-overlay-border, var(--muni-border));
            border-radius:var(--muni-radius-lg); box-shadow:var(--muni-shadow-lg); font-family:var(--muni-font-sans); }
        .muni-pb__identidad { display:flex; flex-direction:column; align-items:center; gap:8px; }
        .muni-pb__nombre { margin:0; font-size:16px; font-weight:700; color:var(--muni-text); }
        .muni-pb__cargo { margin:0; font-size:12.5px; color:var(--muni-muted); }
        .muni-pb__titulo { display:flex; align-items:center; justify-content:center; gap:7px; margin:16px 0 0; font-size:17px; font-weight:700; color:var(--muni-text); }
        .muni-pb__candado { flex-shrink:0; color:var(--muni-accent); }
        .muni-pb__desc { margin:6px 0 0; font-size:13.5px; line-height:1.55; color:var(--muni-muted); }
        .muni-pb__form { margin:18px 0 0; text-align:left; }
        .muni-pb__etiqueta { display:block; margin-bottom:6px; font-size:12.5px; font-weight:600; color:var(--muni-text); }
        /* 44 px de alto: el mesón se atiende de pie y la ventanilla de Licencias también. */
        .muni-pb__clave { display:block; width:100%; min-height:44px; padding:10px 12px; font-family:var(--muni-font-sans);
            font-size:14px; color:var(--muni-text); background:var(--muni-surface); border:1px solid var(--muni-field-border);
            border-radius:var(--muni-radius-sm); transition:border-color var(--muni-dur) var(--muni-ease); }
        .muni-pb__clave[aria-invalid="true"] { border-color:var(--muni-field-border-error); }
        .muni-pb__error { margin:8px 0 0; font-size:13px; line-height:1.45; color:var(--muni-danger-fg); }
        .muni-pb__entrar { display:inline-flex; align-items:center; justify-content:center; width:100%; min-height:44px;
            margin-top:14px; padding:0 16px; font-family:var(--muni-font-sans); font-size:14px; font-weight:700; line-height:1.2;
            color:var(--muni-on-accent); background:var(--muni-accent); border:1px solid transparent;
            border-radius:var(--muni-radius-sm); cursor:pointer; transition:background var(--muni-dur) var(--muni-ease); }
        .muni-pb__entrar:hover { background:var(--muni-accent-strong); }
        .muni-pb__salida-form { margin:14px 0 0; }
        .muni-pb__salida { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:0 14px;
            font-family:var(--muni-font-sans); font-size:13px; font-weight:600; line-height:1.2; color:var(--muni-text);
            text-decoration:underline; background:transparent; border:1px solid transparent; border-radius:var(--muni-radius-sm);
            cursor:pointer; transition:background var(--muni-dur) var(--muni-ease); }
        .muni-pb__salida:hover { background:var(--muni-surface-2); }
        /* El outline es el indicador REAL: la box-shadow del anillo se computa
           transparente dentro de Filament (DESIGN §5). */
        .muni-pb__clave:focus-visible, .muni-pb__entrar:focus-visible, .muni-pb__salida:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        @media (max-width:480px) {
            .muni-pb__capa { padding:12px; }
            .muni-pb__panel { padding:20px 16px 16px; }
        }
        /* Dentro de un panel Filament solo se carga muni-ui-filament.css, y esa hoja deja
           --muni-dur en 160 ms con movimiento reducido (DESIGN §7): la guardia tiene que
           viajar con el componente. La clase va repetida (especificidad 0,2,0) por el
           mismo motivo que en `modal`: para que un vecino que redefina el fundido sin
           guardia no le gane a igual especificidad. */
        @media (prefers-reduced-motion:reduce) {
            .muni-pb__fade.muni-pb__fade { transition:none; }
            .muni-pb__clave, .muni-pb__entrar, .muni-pb__salida { transition:none; }
        }
    </style>
@endonce
