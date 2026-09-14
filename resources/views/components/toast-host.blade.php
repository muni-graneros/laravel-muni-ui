@props([
    'position' => 'bottom-right',
])

@php
    $pos = [
        'bottom-right' => 'bottom:16px;right:16px;align-items:flex-end;',
        'bottom-left' => 'bottom:16px;left:16px;align-items:flex-start;',
        'top-right' => 'top:16px;right:16px;align-items:flex-end;',
        'top-left' => 'top:16px;left:16px;align-items:flex-start;',
    ][$position] ?? 'bottom:16px;right:16px;align-items:flex-end;';

    /*
     * El rótulo del tono en texto: el estado no puede quedar comunicado solo por
     * el color del borde ni por el dibujo (WCAG 2.2 AA 1.4.1). Viaja codificado
     * por Blade y no dentro de una cadena de comillas simples: una traducción con
     * apóstrofo descuadraría la expresión y tumbaría el Alpine de la página
     * entera. (Aquí no se nombra la directiva: un arroba suelto en un comentario
     * es texto que Blade compila igual.)
     */
    $rotulos = [
        'ok' => __('Confirmación'),
        'warn' => __('Advertencia'),
        'danger' => __('Error'),
        'info' => __('Información'),
    ];
@endphp

{{-- La pila de avisos flotantes de las vistas públicas Blade, con dos regiones vivas
     propias y autocierre ajustable.

     DENTRO DE UN PANEL FILAMENT NO SE USA: ahí manda `Notification::make()`. Dos pilas
     de avisos en la misma esquina son peor accesibilidad que una: el lector anuncia dos
     veces y el funcionario tiene dos pilas de cosas que cerrar.

     Se coloca UNA vez, típicamente dentro de <x-muni::app-shell>, y se dispara desde
     cualquier parte:

       Alpine/Livewire:  $dispatch('muni-toast', { tone: 'ok', title: 'Listo', message: 'Guardado' })
       Livewire (PHP):   $this->dispatch('muni-toast', tone: 'danger', message: 'No se pudo guardar');
       JS plano:         window.dispatchEvent(new CustomEvent('muni-toast', { detail: { … } }))

     El contrato del evento no cambió: `tone` (ok|warn|danger|info), `title`, `message` y
     `duration` en milisegundos. `duration: 0` significa «no autocerrar».

     Siete decisiones que no se pueden deshacer sin romper el aviso:

     1. DOS REGIONES VIVAS PERSISTENTES Y VACÍAS, una cortés y una asertiva, a las que se
        inyecta SOLO el texto. Una región viva que se inserta COMPLETA junto con su
        mensaje no se anuncia de forma fiable en NVDA ni en JAWS: el lector tiene que
        estar observando el nodo antes de que llegue el texto. Hasta 0.19 el contenedor
        llevaba aria-live y cada aviso role="status": región viva anidada, anuncio doble.
     2. LA PILA VISUAL NO ES REGIÓN VIVA de nada: ni el contenedor ni cada aviso llevan
        `role` ni `aria-live`. Es interfaz, y el anuncio va por las dos regiones ocultas.
        Un aviso de tono `danger` va a la asertiva; todo lo demás, a la cortés.
     3. TIEMPO AJUSTABLE (2.2.1). `danger` y `warn` NO se autocierran nunca. `ok` e `info`
        escalan con el largo del texto entre 5 y 20 segundos, se pausan con el puntero
        encima y mientras el aviso contenga el foco, y reanudan al salir. La pausa por
        foco va por `focusin`/`focusout`, no consultando el DOM en cada tick. Antes se
        autodestruían todos a los 4,5 s: un folio de solicitud no se alcanzaba a leer ni
        a copiar.
     4. EL FOCO SE DEVUELVE A MANO. Si el aviso que muere contiene el foco, pasa al aviso
        anterior de la pila; si no queda ninguno, al <body> de forma explícita. Por caída
        el siguiente Tab arrancaba desde el principio del documento.
     5. ESCAPE ACOTADO al aviso que tiene el foco, y con la propagación detenida: un
        Escape de ventana cerraría además el modal o el cajón que estuviera abierto.
     6. TOPE DE CUATRO avisos simultáneos; los nuevos empujan al más viejo. Una ráfaga de
        eventos desbordaba la pantalla.
     7. UN SOLO HOST ANUNCIA. Dos en la misma página leerían el mismo evento y mostrarían
        el aviso duplicado: gana el primero que siga conectado al documento.

     Se oculta RECORTANDO (`clip-path` más 1px): `display:none` y `visibility:hidden`
     sacan el nodo del árbol de accesibilidad y la región queda muda.

     La primera frase de este comentario es la descripción que `npm run registro` escribe
     en registry.json; los tokens los saca por grep del archivo entero, comentarios
     incluidos, así que aquí no se nombra ningún token que el componente no use. --}}
<div
    x-data="{
        items: [],
        secuencia: 0,
        inerte: false,
        host: null,
        rotulos: @js($rotulos),

        maximo: 4,
        piso: 5000,
        techo: 20000,
        persistentes: ['danger', 'warn'],
        porCaracter: 55,
        espera: 60,
        retencion: 1200,
        olvido: 5000,
        colas: { polite: [], assertive: [] },
        emitiendo: { polite: false, assertive: false },
        escrituras: { polite: null, assertive: null },
        limpiezas: { polite: null, assertive: null },

        init() {
            this.host = this.$root;

            const previo = window.__muniToastHost;

            if (previo && previo !== this.host && previo.isConnected) { this.inerte = true; return; }

            window.__muniToastHost = this.host;
        },

        destroy() {
            if (window.__muniToastHost === this.host) { window.__muniToastHost = null; }

            this.items.forEach(i => clearTimeout(i.timer));

            ['polite', 'assertive'].forEach(clave => {
                clearTimeout(this.escrituras[clave]);
                clearTimeout(this.limpiezas[clave]);
                this.colas[clave] = [];
                this.emitiendo[clave] = false;
            });
        },

        prioridadDe(tone) { return tone === 'danger' ? 'assertive' : 'polite'; },

        buscar(id) { return this.items.find(i => i.id === id) || null; },

        idMensaje(id) { return 'muni-toast-msg-' + id; },

        /* EL HOST SE GUARDA EN `init()`, y no se resuelve con `$el` ni con `$root` cada vez.
           Las dos formas fallan aquí, y las dos fallan en silencio:

           - `$el` es el elemento sobre el que se evalúa la expresión. Desde el botón de
             cerrar o desde el `keydown` del aviso es ESE elemento, no el host, así que la
             búsqueda se hacía dentro del propio aviso y no encontraba nada.
           - `$root` sube por el DOM buscando `[x-data]`, y desde un nodo YA DESPRENDIDO
             devuelve `undefined`. Es justo la situación de `devolverFoco()`: corre en el
             tick siguiente, cuando el aviso que tenía el foco ya no está en el documento.

           Las dos versiones se midieron en navegador (Chromium y Firefox): con `$el` el
           foco caía al <body>; con `$root` reventaba con «Cannot read properties of
           undefined» dentro del `$nextTick`, que ni siquiera se ve si nadie mira la
           consola. La prueba de Blade no puede cazar ninguna de las dos. */
        nodoDe(id) {
            if (! this.host) { return null; }

            return Array.from(this.host.querySelectorAll('[data-muni-toast]'))
                .find(n => n.dataset.muniToast === String(id)) || null;
        },

        /* EL ORDEN DE ESTAS TRES RAMAS ES LA REGLA, no una preferencia de estilo. Hasta la
           0.19.1 la duración pedida por el emisor entraba PRIMERO y anulaba a las otras dos:
           medido, `{ tone: 'danger', duration: 3000 }` autodestruía el error a los 3 s y
           `{ tone: 'ok', duration: 1 }` dejaba el aviso 1 ms en pantalla. Como el contrato del
           evento no cambió —`duration` sigue documentado—, cualquier consumidor que ya lo
           pasaba conservaba intacto el incumplimiento de 2.2.1 que esta pieza vino a cerrar.

           1. Un tono persistente NO autocierra, pida lo que pida el emisor: un error que se
              autodestruye es el defecto original con otra ropa.
           2. Una duración pedida se respeta, pero nunca por debajo del piso de 5 s.
           3. Lo que no sea un número positivo cae del lado seguro y no autocierra. Ahí entra
              `duration: 0`, que el contrato define justamente como «no autocerrar».

           El techo NO se le aplica a la duración pedida: 2.2.1 castiga el tiempo CORTO, y
           recortarle a un consumidor los 30 s que pidió le rompe el contrato sin ganar nada. */
        vidaDe(tone, texto, pedida) {
            if (this.persistentes.includes(tone)) { return 0; }

            if (pedida !== null) {
                const n = Number(pedida);

                return Number.isFinite(n) && n > 0 ? Math.max(this.piso, n) : 0;
            }

            return Math.min(this.techo, Math.max(this.piso, texto.length * this.porCaracter));
        },

        push(detail) {
            if (this.inerte) { return; }

            const d = detail || {};
            const tone = this.rotulos[d.tone] ? d.tone : 'info';
            const message = String(d.message == null ? '' : d.message).trim();
            const title = String(d.title == null ? '' : d.title).trim();

            if (! message && ! title) { return; }

            const texto = (this.rotulos[tone] + '. ' + (title ? title + '. ' : '') + message).trim();
            const pedida = d.duration ?? null;
            const vida = this.vidaDe(tone, texto, pedida);
            const id = ++this.secuencia;

            this.items.push({ id, tone, title: title || null, message, vida, resto: vida, desde: 0, timer: null, pausas: 0 });

            while (this.items.length > this.maximo) { this.descartar(this.items[0].id); }

            this.anunciar(tone, texto);
            this.arrancar(id);
        },

        /* CADA REGIÓN TIENE COLA, no un solo hueco. El ciclo vaciar → esperar un tick →
           escribir es el que fuerza el reanuncio de un texto repetido (sin el hueco el nodo
           no cambia y el lector no lo relee), pero con un hueco único el segundo aviso pisa
           al primero antes de que llegue a la región. Medido: dos errores de validación del
           mismo submit —que salen con microsegundos de diferencia— dejaban en la región
           asertiva solo el segundo; el primero quedaba a la vista y no se anunciaba NUNCA.
           `announcer` puede vivir con el hueco único porque atiende un mensaje a la vez;
           aquí se apilan hasta cuatro por diseño, y la ráfaga es el escenario por el que
           existe el tope de pila.

           Cada texto se queda `retencion` ms puesto antes de que entre el siguiente, y
           `olvido` ms después del último la región se vacía: la limpieza tardía que
           `announcer` documenta en su doctrina, para que quien recorra el DOM con el cursor
           virtual media hora después no encuentre pegado el aviso de entonces. */
        anunciar(tone, texto) {
            const clave = this.prioridadDe(tone);

            this.colas[clave].push(texto);

            if (! this.emitiendo[clave]) { this.emitirSiguiente(clave); }
        },

        emitirSiguiente(clave) {
            const region = this.$refs[clave];

            if (! region) { this.colas[clave] = []; this.emitiendo[clave] = false; return; }

            const texto = this.colas[clave].shift();

            clearTimeout(this.escrituras[clave]);
            clearTimeout(this.limpiezas[clave]);

            if (texto === undefined) {
                this.emitiendo[clave] = false;

                this.limpiezas[clave] = setTimeout(() => {
                    if (! this.emitiendo[clave]) { region.textContent = ''; }
                }, this.olvido);

                return;
            }

            this.emitiendo[clave] = true;
            region.textContent = '';

            this.escrituras[clave] = setTimeout(() => {
                region.textContent = texto;
                this.escrituras[clave] = setTimeout(() => this.emitirSiguiente(clave), this.retencion);
            }, this.espera);
        },

        arrancar(id) {
            const it = this.buscar(id);

            if (! it || it.vida === 0 || it.pausas > 0) { return; }

            clearTimeout(it.timer);
            it.desde = Date.now();
            it.timer = setTimeout(() => this.descartar(id), Math.max(0, it.resto));
        },

        pausar(id) {
            const it = this.buscar(id);

            if (! it) { return; }

            it.pausas++;

            if (it.vida === 0 || it.timer === null) { return; }

            clearTimeout(it.timer);
            it.timer = null;
            it.resto = Math.max(0, it.resto - (Date.now() - it.desde));
        },

        reanudar(id) {
            const it = this.buscar(id);

            if (! it) { return; }

            it.pausas = Math.max(0, it.pausas - 1);

            if (it.pausas === 0) { this.arrancar(id); }
        },

        descartar(id) {
            const it = this.buscar(id);

            if (! it) { return; }

            clearTimeout(it.timer);

            const indice = this.items.indexOf(it);
            const nodo = this.nodoDe(id);
            const teniaFoco = nodo !== null && nodo.contains(document.activeElement);
            const anterior = indice > 0 ? this.items[indice - 1] : null;

            this.items.splice(indice, 1);

            if (teniaFoco) { this.$nextTick(() => this.devolverFoco(anterior)); }
        },

        devolverFoco(anterior) {
            const vivo = anterior ? this.nodoDe(anterior.id) : null;
            const ultimo = this.items.length ? this.nodoDe(this.items[this.items.length - 1].id) : null;
            const boton = (vivo || ultimo || { querySelector: () => null }).querySelector('.muni-toast__x');

            if (boton) { boton.focus(); return; }

            document.body.focus();
        }
    }"
    @muni-toast.window="push($event.detail || {})"
    {{ $attributes->merge([
        'style' => 'position:fixed;z-index:300;display:flex;flex-direction:column;gap:10px;max-width:360px;'.$pos,
    ]) }}
>
    {{-- Las dos regiones vivas: persistentes, vacías desde la carga, sin `tabindex` (una
         región viva jamás recibe el foco) y con nombre propio. El trío rol + aria-live +
         aria-atomic es el que leen todas las familias de lector. --}}
    <div class="muni-sr" x-ref="polite" role="status" aria-live="polite" aria-atomic="true" aria-label="{{ __('Avisos') }}"></div>
    <div class="muni-sr" x-ref="assertive" role="alert" aria-live="assertive" aria-atomic="true" aria-label="{{ __('Avisos urgentes') }}"></div>

    <template x-for="item in items" :key="item.id">
        <div
            class="muni-toast"
            :class="'muni-toast--' + item.tone"
            :data-muni-toast="item.id"
            @mouseenter="pausar(item.id)"
            @mouseleave="reanudar(item.id)"
            @focusin="if (! $el.contains($event.relatedTarget)) { pausar(item.id) }"
            @focusout="if (! $el.contains($event.relatedTarget)) { reanudar(item.id) }"
            @keydown.escape.stop="descartar(item.id)"
            x-transition:enter="muni-toast-enter"
            x-transition:enter-start="muni-toast-enter-start"
            x-transition:enter-end="muni-toast-enter-end"
            x-transition:leave="muni-toast-leave"
            x-transition:leave-start="muni-toast-leave-start"
            x-transition:leave-end="muni-toast-leave-end"
        >
            {{-- Un dibujo por tono, con el mismo trazo que `alert`: círculo con visto (ok),
                 triángulo (warn), octógono con cruz (danger) y círculo con «i» (info). Van
                 aria-hidden porque el rótulo de al lado ya dice en texto lo que repiten. --}}
            <template x-if="item.tone === 'ok'"><svg class="muni-toast__glifo" aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><circle cx="10" cy="10" r="7.5"/><path d="M6.6 10.3l2.3 2.3 4.6-4.9" stroke-linecap="round" stroke-linejoin="round"/></svg></template>
            <template x-if="item.tone === 'warn'"><svg class="muni-toast__glifo" aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><path d="M10 3.2L18 17H2L10 3.2z" stroke-linejoin="round"/><path d="M10 8.2v4M10 14.4v.2" stroke-linecap="round"/></svg></template>
            <template x-if="item.tone === 'danger'"><svg class="muni-toast__glifo" aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><path d="M6.7 2.5h6.6l4.2 4.2v6.6l-4.2 4.2H6.7l-4.2-4.2V6.7z" stroke-linejoin="round"/><path d="M7.5 7.5l5 5M12.5 7.5l-5 5" stroke-linecap="round"/></svg></template>
            <template x-if="item.tone === 'info'"><svg class="muni-toast__glifo" aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><circle cx="10" cy="10" r="7.5"/><path d="M10 9.2v4.6M10 6.4v.2" stroke-linecap="round"/></svg></template>

            <div style="min-width:0;flex:1;">
                {{-- El tono en texto, para quien llegue al aviso con el lector: la pila no
                     anuncia nada por sí sola y el borde de color no le dice qué es. --}}
                <span class="muni-sr" x-text="rotulos[item.tone] ?? rotulos.info"></span>
                <template x-if="item.title"><div class="muni-toast__title" x-text="item.title"></div></template>
                <div class="muni-toast__msg" :id="idMensaje(item.id)" x-text="item.message"></div>
            </div>

            <button type="button" aria-label="{{ __('Cerrar') }}" :aria-describedby="idMensaje(item.id)" @click="descartar(item.id)" class="muni-toast__x">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/></svg>
            </button>
        </div>
    </template>
</div>

@once
    <style>
        /* Oculto a la vista, presente en el árbol de accesibilidad. Se repite la misma
           declaración que publican el anunciador y la tabla ordenable a propósito: lo que
           un componente necesita para funcionar viaja con él, porque dentro de un panel
           Filament solo se carga muni-ui-filament.css. */
        .muni-sr { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0; }
        .muni-toast { display:flex;align-items:flex-start;gap:10px;padding:12px 14px;
            background:var(--muni-surface);border:1px solid var(--muni-border);border-left-width:3px;
            border-radius:var(--muni-radius);box-shadow:var(--muni-shadow-lg);font-family:var(--muni-font-sans); }
        .muni-toast__glifo { flex-shrink:0;margin-top:1px; }
        .muni-toast__title { font-size:13px;font-weight:700;color:var(--muni-text);margin-bottom:1px; }
        .muni-toast__msg { font-size:12.5px;color:var(--muni-muted);line-height:1.45; }
        /* 24x24 de área táctil (2.5.8): con 3px de relleno y un icono de 14px medía 20. */
        .muni-toast__x { flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;
            min-width:24px;min-height:24px;padding:3px;border:none;background:transparent;color:var(--muni-hint);
            border-radius:var(--muni-radius-sm);cursor:pointer;transition:color var(--muni-dur) var(--muni-ease); }
        .muni-toast__x:hover { color:var(--muni-text); }
        /* Outline, nunca sombra: la sombra se pierde dentro de Filament. */
        .muni-toast__x:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676));outline-offset:2px; }
        .muni-toast--ok { border-left-color:var(--muni-ok-fg); } .muni-toast--ok .muni-toast__glifo { color:var(--muni-ok-fg); }
        .muni-toast--warn { border-left-color:var(--muni-warn-fg); } .muni-toast--warn .muni-toast__glifo { color:var(--muni-warn-fg); }
        .muni-toast--danger { border-left-color:var(--muni-danger-fg); } .muni-toast--danger .muni-toast__glifo { color:var(--muni-danger-fg); }
        .muni-toast--info { border-left-color:var(--muni-info-fg); } .muni-toast--info .muni-toast__glifo { color:var(--muni-info-fg); }
        .muni-toast-enter { transition:opacity var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease); }
        .muni-toast-enter-start { opacity:0;transform:translateY(8px) scale(.98); }
        .muni-toast-enter-end { opacity:1;transform:translateY(0) scale(1); }
        .muni-toast-leave { transition:opacity var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease); }
        .muni-toast-leave-start { opacity:1;transform:translateX(0); }
        .muni-toast-leave-end { opacity:0;transform:translateX(12px); }
    </style>
@endonce
