@props([
    'expiraEn',
    'renovarUrl' => null,
    'salirUrl' => null,
    'ingresarUrl' => null,
    'avisarA' => 300,
    'dialogoA' => 60,
    'ahora' => null,
    'duracion' => null,
])

@php
    /* Un solo componente por página, como toast-host: el id por defecto es fijo y
       el consumidor puede pasar el suyo. Nada de uniqid() (DESIGN §10). */
    $id = $attributes->get('id') ?: 'muni-sesion';
    $avisarA = max(1, (int) $avisarA);
    $dialogoA = max(0, min((int) $dialogoA, $avisarA));
    /* SESSION_LIFETIME en segundos, si el anfitrión la pasa: es lo que concede un
       204 sin cuerpo. Sin ella, lo que restaba al cargar la página (pesimista). */
    $duracion = max(0, (int) $duracion);
@endphp

{{-- Guardia de sesión (Alpine 3 core + plugin Focus, que viaja en el bundle de Livewire).

     Tres fases, decididas SIEMPRE por `expira-en` menos el ahora, nunca acumulando ticks:

       aviso     (restante <= avisar-a)   una tira fija abajo, sin robar el foco: el funcionario
                                          sigue escribiendo. Pulsarla abre el diálogo.
       urgente   (restante <= dialogo-a)  el diálogo se abre solo y atrapa el foco. Si el
                                          funcionario lo descarta (solo se puede sin `renovar-url`:
                                          «Entendido» o Escape), la tira con el contador SIGUE a la
                                          vista hasta T=0: se midió el último minuto sin ningún
                                          aviso visible cuando la tira estaba atada a la fase aviso.
       terminada (restante <= 0)          el mismo diálogo, con el velo OPACO: la ficha con el RUT
                                          y el domicilio del vecino deja de verse desde el mesón
                                          (Ley 21.719). Escape no hace nada; solo «Ingresar de nuevo».

     La tira, entonces, se muestra en toda fase que no sea la activa mientras no haya modal.

     El componente es pasivo: recibe todo resuelto por el anfitrión, nunca inventa una URL, nunca
     hace ping solo y nunca prorroga sin clic. La prórroga es un POST con el token CSRF a
     `renovar-url`; el anfitrión decide ahí (auth + throttle) y responde JSON
     `{ "expiraEn": "<ISO>" }` o 204 sin cuerpo. Con 204 la expiración nueva es ahora + `duracion`
     (SESSION_LIFETIME en segundos, que pasa el anfitrión); sin `duracion`, ahora + lo que restaba
     al cargar la página, que en una carga tardía es poco: pesimista, nunca laxo. Sin
     `renovar-url`, avisa y bloquea; no prorroga. Un 401/403/419 a esa ruta significa que el
     servidor ya cortó: se pasa a T=0 en el acto.

     El rol es `alertdialog` desde el servidor y no se muta a los dos minutos: cambiar `role` en
     caliente no se anuncia de forma fiable en NVDA ni en VoiceOver. Los hitos (aviso, 5 min, 1 min)
     los dice una región viva cortés que nace vacía; el contador visible NO es región viva.

     La trampa de foco (`x-trap.inert`) va en la RAÍZ teleportada, no en el diálogo, y la región
     viva es hija suya: el plugin pone aria-hidden en todos los hermanos hasta el body, y una región
     viva hermana del diálogo queda muda justo cuando más importa (T=0 llega con el diálogo ya
     abierto y sin cambio de rol). El aviso temprano, también hijo, se oculta con x-show en cuanto
     hay modal, así que nunca es una parada de la trampa. `.noreturn` porque el foco lo devuelve el
     componente: recuerda desde dónde venía el funcionario (`retorno`: el campo que estaba
     escribiendo, o el que dejó para pulsar el aviso) y lo enfoca al cerrar; la trampa devolvería al
     elemento enfocado al activarse, que puede ser el propio aviso ya oculto.

     Todo foco pasa por `enfocar()`: Alpine muestra con x-show en un requestAnimationFrame
     posterior y $nextTick corre antes, así que un `.focus()` directo caía sobre un botón todavía
     display:none y el foco se iba al body (medido en Chromium y Firefox). `enfocar()` reintenta por
     rAF hasta que el destino sea el elemento activo.

     Varias pestañas se coordinan por BroadcastChannel —o por el evento `storage` de localStorage
     de respaldo—, todo en try/catch: en modo privado sigue como pestaña única. Una pestaña que
     llega a T=0 o cierra sesión bloquea a las demás; una recién cargada les pasa su expiración
     nueva, y la más tardía gana. Una pestaña ya bloqueada no vuelve a abrirse por mensaje: podría
     estar mostrando la ficha de otra sesión.

     Ningún texto ni URL del anfitrión entra en una expresión de Alpine sin pasar por @js(): un
     apóstrofo dentro de comillas simples tumba el Alpine de la página entera. --}}
<div
    id="{{ $id }}"
    x-data="{
        expiraMs: 0,
        desfase: 0,
        duracionMs: 0,
        avisarA: @js($avisarA),
        dialogoA: @js($dialogoA),
        duracion: @js($duracion),
        renovarUrl: @js((string) $renovarUrl),
        ingresarUrl: @js((string) $ingresarUrl),
        id: @js($id),
        restante: 0,
        fase: 'activa',
        dialogo: false,
        descartado: false,
        ocupado: false,
        error: '',
        anuncio: '',
        anunciados: [],
        reloj: null,
        canal: null,
        clave: 'muni-sesion',
        escuchas: [],
        retorno: null,

        get modal() { return this.dialogo || this.fase === 'terminada'; },

        get tiempo() {
            const m = Math.floor(this.restante / 60), s = this.restante % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        },

        init() {
            const expira = Date.parse(@js((string) $expiraEn));
            const servidor = Date.parse(@js((string) $ahora));
            const carga = Date.now();

            if (isNaN(expira)) {
                console.warn('muni-ui sesion-guardia: expira-en no es una fecha ISO válida; la guardia queda inactiva.');
                return;
            }

            this.desfase = isNaN(servidor) ? 0 : servidor - carga;
            this.expiraMs = expira;
            this.duracionMs = Math.max(0, expira - (carga + this.desfase));

            this.conectarPestanas();
            this.emitir({ tipo: 'expira', expiraEn: new Date(this.expiraMs).toISOString() });

            this.tick();
            this.reloj = setInterval(() => this.tick(), 1000);

            const alVolver = () => this.tick();
            document.addEventListener('visibilitychange', alVolver);
            window.addEventListener('focus', alVolver);
            this.escuchas.push(() => document.removeEventListener('visibilitychange', alVolver));
            this.escuchas.push(() => window.removeEventListener('focus', alVolver));
        },

        destroy() {
            clearInterval(this.reloj);
            this.escuchas.forEach((quitar) => quitar());
            try { if (this.canal) this.canal.close(); } catch (e) {}
        },

        ahoraMs() { return Date.now() + this.desfase; },

        tick() {
            if (this.fase === 'terminada') return;
            this.restante = Math.max(0, Math.ceil((this.expiraMs - this.ahoraMs()) / 1000));

            if (this.restante <= 0) { this.terminar(true); return; }

            const fase = this.restante <= this.dialogoA ? 'urgente' : (this.restante <= this.avisarA ? 'aviso' : 'activa');

            if (fase !== this.fase) {
                this.fase = fase;
                if (fase === 'urgente' && ! this.descartado) this.abrir();
            }

            this.anunciarHito();
        },

        anunciarHito() {
            if (this.fase === 'activa') return;
            const hitos = [this.avisarA, 300, 60].filter((h) => h > 0 && h <= this.avisarA);
            const cruzados = hitos.filter((h) => this.restante <= h && ! this.anunciados.includes(h));
            if (! cruzados.length) return;
            cruzados.forEach((h) => this.anunciados.push(h));
            this.anuncio = 'Tu sesión termina en ' + this.enPalabras(this.restante) + '.';
        },

        enPalabras(s) {
            if (s >= 60) { const m = Math.round(s / 60); return m + (m === 1 ? ' minuto' : ' minutos'); }
            return s + (s === 1 ? ' segundo' : ' segundos');
        },

        capa() {
            const dialogo = document.getElementById(this.id + '-dialogo');
            return dialogo ? dialogo.parentElement : null;
        },

        boton(nombre) {
            const capa = this.capa();
            return capa ? capa.querySelector('[data-muni-foco=' + nombre + ']') : null;
        },

        enCapa(el) {
            const capa = this.capa();
            return !! (el && capa && capa.contains(el));
        },

        recordar(el) {
            if (el && el !== document.body && ! this.enCapa(el)) this.retorno = el;
        },

        devolverFoco() {
            this.$nextTick(() => {
                const activo = document.activeElement;
                if (activo && activo !== document.body && ! this.enCapa(activo)) return;
                if (this.retorno && this.retorno.isConnected) this.retorno.focus();
            });
        },

        enfocar(nombre) {
            let intentos = 10;
            const intentar = () => {
                const el = this.boton(nombre);
                if (! el) return;
                el.focus();
                if (document.activeElement === el || --intentos <= 0) return;
                requestAnimationFrame(intentar);
            };
            this.$nextTick(intentar);
        },

        abrir() {
            if (this.fase === 'terminada') return;
            this.recordar(document.activeElement);
            this.dialogo = true;
            this.enfocar(this.renovarUrl ? 'seguir' : 'entendido');
        },

        cerrar() {
            if (this.fase === 'terminada') return;
            const devolver = this.enCapa(document.activeElement);
            this.dialogo = false;
            this.error = '';
            if (this.fase === 'urgente') this.descartado = true;
            if (devolver) this.devolverFoco();
        },

        escape() { if (this.fase === 'terminada') return; this.renovarUrl ? this.renovar() : this.cerrar(); },

        adoptar(iso, forzar) {
            if (this.fase === 'terminada') return;
            const ms = Date.parse(iso || '');
            if (isNaN(ms) || (! forzar && ms <= this.expiraMs)) return;
            const devolver = this.enCapa(document.activeElement);
            this.expiraMs = ms;
            this.anunciados = [];
            this.descartado = false;
            this.dialogo = false;
            this.error = '';
            this.tick();
            if (devolver) this.devolverFoco();
        },

        token() {
            const capa = this.capa();
            const campo = capa ? capa.querySelector('input[name=_token]') : null;
            const meta = document.querySelector('meta[name=csrf-token]');
            return (campo && campo.value) || (meta && meta.getAttribute('content')) || '';
        },

        async renovar() {
            if (! this.renovarUrl || this.ocupado || this.fase === 'terminada') return;
            this.ocupado = true;
            this.error = '';

            try {
                const r = await fetch(this.renovarUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.token() },
                    body: new URLSearchParams({ _token: this.token() }),
                });

                if (r.status === 401 || r.status === 403 || r.status === 419) { this.terminar(true); return; }
                if (r.status === 429) { this.fallo('Demasiados intentos. Espera un momento y vuelve a intentarlo.'); return; }
                if (! r.ok) { this.fallo('No se pudo prorrogar la sesión (' + r.status + '). Vuelve a intentarlo.'); return; }

                let datos = null;
                try { datos = await r.json(); } catch (e) {}

                const nuevo = datos && datos.expiraEn ? Date.parse(datos.expiraEn) : NaN;
                const concedidoMs = this.duracion > 0 ? this.duracion * 1000 : this.duracionMs;
                this.adoptar(new Date(isNaN(nuevo) ? this.ahoraMs() + concedidoMs : nuevo).toISOString(), true);
                this.emitir({ tipo: 'expira', expiraEn: new Date(this.expiraMs).toISOString() });
                this.anuncio = 'Sesión prorrogada.';
            } catch (e) {
                this.fallo('Sin conexión con el servidor. Vuelve a intentarlo.');
            } finally {
                this.ocupado = false;
            }
        },

        fallo(mensaje) {
            this.error = mensaje;
            this.anuncio = mensaje;
            if (! this.dialogo) this.abrir();
        },

        terminar(propagar) {
            if (this.fase === 'terminada') return;
            this.fase = 'terminada';
            this.restante = 0;
            this.dialogo = true;
            this.error = '';
            this.anuncio = 'Tu sesión terminó. Ingresa de nuevo para seguir.';
            clearInterval(this.reloj);
            if (propagar) this.emitir({ tipo: 'terminada' });
            this.enfocar('ingresar');
        },

        ingresar() {
            if (this.ingresarUrl) { window.location.assign(this.ingresarUrl); return; }
            window.location.replace(window.location.pathname + window.location.search);
        },

        conectarPestanas() {
            try {
                if ('BroadcastChannel' in window) {
                    this.canal = new BroadcastChannel(this.clave);
                    this.canal.onmessage = (e) => this.recibir(e.data);
                    return;
                }
            } catch (e) { this.canal = null; }

            try {
                const alCambiar = (e) => {
                    if (e.key !== this.clave || ! e.newValue) return;
                    try { this.recibir(JSON.parse(e.newValue)); } catch (err) {}
                };
                window.addEventListener('storage', alCambiar);
                this.escuchas.push(() => window.removeEventListener('storage', alCambiar));
            } catch (e) {}
        },

        emitir(mensaje) {
            const m = Object.assign({ t: Date.now() }, mensaje);
            try {
                if (this.canal) { this.canal.postMessage(m); return; }
                window.localStorage.setItem(this.clave, JSON.stringify(m));
            } catch (e) {}
        },

        recibir(m) {
            if (! m || typeof m !== 'object') return;
            if (m.tipo === 'terminada') { this.terminar(false); return; }
            if (m.tipo === 'expira') this.adoptar(m.expiraEn, false);
        }
    }"
    @muni-sesion.window="adoptar($event.detail && $event.detail.expiraEn, true)"
    {{ $attributes->except('id') }}
>
    <template x-teleport="body">
        {{-- La raíz teleportada: es la que atrapa, sin x-show, para que la región viva
             siga en el árbol en todas las fases y nunca quede aria-hidden. Nada de x-ref
             acá dentro: Alpine cachea $refs en el primer acceso, y si la página carga ya
             en el último minuto ese acceso ocurre en init(), antes de que el clon exista,
             y $refs queda vacío para toda la sesión. Se busca por el DOM, desde el id. --}}
        <div x-trap.inert.noscroll.noreturn="modal">
            {{-- Región viva de los hitos: presente y VACÍA desde el primer render. --}}
            <p class="muni-sg__sr" role="status" aria-live="polite" aria-atomic="true" x-text="anuncio"></p>

            {{-- Aviso temprano: fuera del diálogo, no atrapa nada, no roba el foco. Al recibir
                 el foco recuerda de dónde venía el funcionario para devolvérselo después. Se ve
                 en toda fase que no sea la activa mientras no haya modal: también en el tramo
                 urgente, si el funcionario descartó el diálogo. --}}
            <div id="{{ $id }}-aviso" class="muni-sg__aviso" x-show="fase !== 'activa' && ! modal" x-cloak style="display:none;" @focusin="recordar($event.relatedTarget)">
                <button type="button" class="muni-sg__aviso-btn" @click="abrir()" aria-haspopup="dialog">
                    <svg class="muni-sg__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Tu sesión termina en <span class="muni-num" x-text="tiempo"></span></span>
                </button>
                @if (filled($renovarUrl))
                    <button type="button" class="muni-sg__btn muni-sg__btn--primario" @click="renovar()" :aria-disabled="ocupado">Seguir conectado</button>
                @endif
            </div>

            {{-- El diálogo. Un solo elemento, un solo rol; lo que cambia en T=0 es el contenido
                 y el velo, que pasa a opaco. --}}
            <div
                id="{{ $id }}-dialogo"
                class="muni-sg__capa"
                :class="fase === 'terminada' && 'muni-sg__capa--opaca'"
                x-show="modal"
                x-cloak
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="{{ $id }}-titulo"
                aria-describedby="{{ $id }}-desc"
                @keydown.escape.prevent.stop="escape()"
                style="display:none;"
            >
                <div class="muni-sg__velo" aria-hidden="true"></div>

                <div class="muni-sg__panel">
                    <div class="muni-sg__cabeza">
                        <svg class="muni-sg__ico" x-show="fase !== 'terminada'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="28" height="28" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <svg class="muni-sg__ico muni-sg__ico--fin" x-show="fase === 'terminada'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="28" height="28" aria-hidden="true" style="display:none;"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4" stroke-linecap="round"/></svg>
                        <h2 id="{{ $id }}-titulo" class="muni-sg__titulo">
                            <span x-show="fase !== 'terminada'">Tu sesión está por terminar</span>
                            <span x-show="fase === 'terminada'" style="display:none;">Sesión terminada</span>
                        </h2>
                    </div>

                    <p id="{{ $id }}-desc" class="muni-sg__desc">
                        <span x-show="fase !== 'terminada'">Si no la prorrogas, lo que no hayas guardado se perderá.</span>
                        <span x-show="fase === 'terminada'" style="display:none;">Por seguridad la pantalla quedó bloqueada. Ingresa de nuevo para seguir; lo que no alcanzaste a guardar se perdió.</span>
                    </p>

                    <p class="muni-sg__reloj muni-num" x-show="fase !== 'terminada'" x-text="tiempo"></p>

                    <p class="muni-sg__error" x-show="error" x-text="error" style="display:none;"></p>

                    <div class="muni-sg__acciones" x-show="fase !== 'terminada'">
                        @if (filled($renovarUrl))
                            {{-- Un <form> real: es el POST con @csrf que pide la ficha. Con JS se
                                 envía por fetch para no perder el formulario largo de atrás; sin él
                                 no hay guardia (nada de esto se muestra) y el servidor corta igual. --}}
                            <form method="post" action="{{ $renovarUrl }}" @submit.prevent="renovar()">
                                @csrf
                                <button type="submit" class="muni-sg__btn muni-sg__btn--primario" data-muni-foco="seguir" :aria-disabled="ocupado">Seguir conectado</button>
                            </form>
                        @else
                            <button type="button" class="muni-sg__btn muni-sg__btn--primario" data-muni-foco="entendido" @click="cerrar()">Entendido</button>
                        @endif

                        @if (filled($salirUrl))
                            <form method="post" action="{{ $salirUrl }}" @submit="emitir({ tipo: 'terminada' })">
                                @csrf
                                <button type="submit" class="muni-sg__btn">Cerrar sesión</button>
                            </form>
                        @endif
                    </div>

                    <div class="muni-sg__acciones" x-show="fase === 'terminada'" style="display:none;">
                        @if (filled($ingresarUrl))
                            <a class="muni-sg__btn muni-sg__btn--primario" href="{{ $ingresarUrl }}" data-muni-foco="ingresar">Ingresar de nuevo</a>
                        @else
                            <button type="button" class="muni-sg__btn muni-sg__btn--primario" data-muni-foco="ingresar" @click="ingresar()">Ingresar de nuevo</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    <style>
        .muni-sg__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        .muni-sg__aviso { position:fixed; left:50%; bottom:16px; transform:translateX(-50%); z-index:290; display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:6px 10px;
            max-width:calc(100vw - 32px); padding:6px 8px 6px 10px; background:var(--muni-surface); color:var(--muni-text);
            border:1px solid var(--muni-border); border-left:3px solid var(--muni-warn-fg); border-radius:var(--muni-radius); box-shadow:var(--muni-shadow-lg);
            font-family:var(--muni-font-sans); font-size:14px; }
        .muni-sg__aviso-btn { display:inline-flex; align-items:center; gap:8px; min-height:44px; padding:0 8px; border:0; background:transparent; color:var(--muni-text);
            font:inherit; font-weight:600; cursor:pointer; border-radius:var(--muni-radius-sm); transition:background var(--muni-dur) var(--muni-ease); }
        .muni-sg__aviso-btn:hover { background:var(--muni-surface-2); }
        .muni-sg__ico { flex-shrink:0; color:var(--muni-warn-fg); }
        .muni-sg__ico--fin { color:var(--muni-danger-fg); }
        /* Por encima de toast-host (300), modal y drawer (200): el bloqueo de T=0 tiene que
           tapar también un aviso que nombre al vecino. */
        .muni-sg__capa { position:fixed; inset:0; z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; font-family:var(--muni-font-sans); }
        /* El velo es el papel del tema, no un tinte ni un desenfoque: antes de T=0 deja
           entrever la página; en T=0 pasa a opaco y la ficha desaparece del mesón. */
        .muni-sg__velo { position:absolute; inset:0; background:var(--muni-bg); opacity:.85; transition:opacity var(--muni-dur) var(--muni-ease); }
        .muni-sg__capa--opaca .muni-sg__velo { opacity:1; }
        .muni-sg__panel { position:relative; width:100%; max-width:440px; padding:20px 22px 18px; background:var(--muni-surface); color:var(--muni-text);
            border:1px solid var(--muni-border); border-left:4px solid var(--muni-warn-fg); border-radius:var(--muni-radius-lg); box-shadow:var(--muni-shadow-lg); }
        .muni-sg__capa--opaca .muni-sg__panel { border-left-color:var(--muni-danger-fg); }
        .muni-sg__cabeza { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
        .muni-sg__titulo { margin:0; font-size:17px; font-weight:700; color:var(--muni-text); }
        .muni-sg__desc { margin:0; font-size:14px; line-height:1.5; color:var(--muni-muted); }
        .muni-sg__reloj { margin:14px 0 0; font-size:32px; font-weight:700; letter-spacing:.02em; color:var(--muni-text); }
        .muni-num { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; }
        .muni-sg__acciones { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
        .muni-sg__acciones form { margin:0; }
        /* 44x44: el mesón se usa de pie y la ficha social también se llena en la tablet de terreno. */
        .muni-sg__btn { display:inline-flex; align-items:center; justify-content:center; min-height:44px; min-width:44px; padding:0 16px;
            border:1px solid var(--muni-border-2); border-radius:var(--muni-radius); background:var(--muni-surface); color:var(--muni-text);
            font:inherit; font-size:14px; font-weight:600; line-height:1.2; cursor:pointer; text-decoration:none;
            transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-sg__btn:hover { background:var(--muni-surface-2); }
        .muni-sg__btn--primario { background:var(--muni-accent); border-color:var(--muni-accent); color:var(--muni-on-accent); }
        .muni-sg__btn--primario:hover { background:var(--muni-accent-strong); border-color:var(--muni-accent-strong); }
        /* aria-disabled y no disabled: un botón disabled enfocado suelta el foco al body en Chromium,
           y con eso ni la prórroga sabe a dónde devolverlo ni Escape vuelve a llegar al diálogo. */
        .muni-sg__btn[aria-disabled="true"] { opacity:.6; cursor:progress; }
        .muni-sg__btn:focus-visible, .muni-sg__aviso-btn:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-sg__error { margin:12px 0 0; padding:6px 10px; border-radius:var(--muni-radius-sm); background:var(--muni-danger-bg); border:1px solid var(--muni-danger-border);
            font-size:13px; font-weight:600; color:var(--muni-danger-fg); }
        @media (prefers-reduced-motion:reduce) { .muni-sg__btn, .muni-sg__aviso-btn, .muni-sg__velo { transition:none; } }
        @media print { .muni-sg__aviso { display:none !important; } }
    </style>
@endonce
