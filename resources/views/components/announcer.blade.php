@props([
    'message' => null,
    'assertive' => false,
    'clearAfter' => 5000,
])

{{-- La región viva COMPARTIDA de la página (WCAG 2.2 AA 4.1.3, Mensajes de estado).
     Se coloca UNA sola vez, típicamente dentro de <x-muni::app-shell>, y desde ahí
     cualquier componente o cualquier código Livewire anuncia un cambio SIN mover el foco:

       Alpine/Livewire:  $dispatch('muni-announce', { message: '3 resultados' })
       Livewire (PHP):   $this->dispatch('muni-announce', message: 'Ficha guardada');
       JS plano:         window.dispatchEvent(new CustomEvent('muni-announce', { detail: { message: '…' } }))
       Blade sin JS:     <x-muni::announcer message="Ficha guardada" />

     Prioridad: `polite` por defecto; `assertive` con `{ assertive: true }`, `{ priority:
     'assertive' }` o `{ tone: 'danger' }`.

     DOCTRINA, y es lo que más importa de este archivo: por defecto un conteo o un estado va
     en texto VISIBLE envuelto en `role="status"` —«3 resultados», «sin resultados»—, que le
     sirve igual al funcionario ciego, al de terreno con la tablet al sol y al que revisa 200
     patentes con la vista cansada, y que se comprueba a ojo en el ciclo escribir → captura →
     corregir. Esta región oculta es la EXCEPCIÓN: es para lo que no tiene representación
     visual posible. Si se convierte en el mecanismo por defecto, el sistema termina con dos
     verdades y la que nadie ve se pudre sin que nadie lo note.

     Cinco decisiones que no se pueden deshacer sin romper el anuncio:

     1. DOS regiones fijas, una cortés y una asertiva, en vez de una sola que conmute de
        prioridad. Cambiar `aria-live` sobre la marcha es frágil: varios lectores no releen el
        atributo. Es el defecto que `docs/INVENTORY.md` ya registra en `toast-host`, donde un
        toast de tono `danger` se anuncia `polite` y puede quedar en cola detrás de otro
        discurso.
     2. Las regiones EXISTEN VACÍAS en el HTML inicial. Una región viva insertada junto con su
        mensaje no se anuncia: el lector tiene que estar observando el nodo antes de que llegue
        el texto. Por eso `message` tampoco se pinta en el servidor: entra después.
     3. `aria-live` SIN `role`. `role="status"` ya implica `polite` y `role="alert"` implica
        `assertive`: los dos en el mismo nodo son la redundancia que en algunos lectores
        duplica el anuncio.
     4. El ciclo es vaciar → esperar un tick → escribir. Sin ese hueco, un mensaje idéntico al
        anterior no cambia el nodo y no se relee («sin resultados» dos veces seguidas). La
        limpieza tardía de `clearAfter` es otra cosa: evita que el nodo se quede con una
        verdad vieja pegada.
     5. Se oculta RECORTANDO (`clip-path` + 1px). `display:none` y `visibility:hidden` sacan el
        nodo del árbol de accesibilidad y la región queda muda.

     Livewire: el `message` de Blade es para la carga completa de página (POST → redirect). Bajo
     Livewire se anuncia SIEMPRE con el evento, porque un morph no vuelve a disparar `init()`.
     El `wire:ignore` protege la región de los morphs; con `wire:navigate`, que reemplaza el
     `<body>` entero, el anunciador tiene que vivir en el layout, no dentro de la página. --}}
<div
    x-data="{
        inerte: false,
        escrituras: { polite: null, assertive: null },
        limpiezas: { polite: null, assertive: null },
        espera: 60,
        clearAfter: {{ max(0, (int) $clearAfter) }},

        init() {
            /* Dos anunciadores en la misma página leerían el mismo evento y el lector oiría
               el mensaje dos veces: gana el primero que siga conectado al documento. La
               comprobación de `isConnected` es la que deja que el anunciador de una página
               nueva releve al de la anterior tras `wire:navigate`. */
            const previo = window.__muniAnnouncer;

            if (previo && previo !== this.$el && previo.isConnected) { this.inerte = true; return; }

            window.__muniAnnouncer = this.$el;

            /* El texto del anfitrión viaja en un `data-*`, JAMÁS dentro de una expresión de
               Alpine: una etiqueta con apóstrofo descuadra las comillas y tumba el Alpine de
               la página entera, y una con `'+fetch(...)+'` se ejecutaría. */
            const inicial = (this.$el.dataset.muniMessage || '').trim();

            if (inicial) { this.anunciar({ message: inicial, priority: this.$el.dataset.muniPriority }); }
        },

        destroy() {
            if (window.__muniAnnouncer === this.$el) { window.__muniAnnouncer = null; }
            Object.keys(this.escrituras).forEach(k => { clearTimeout(this.escrituras[k]); clearTimeout(this.limpiezas[k]); });
        },

        anunciar(detalle) {
            if (this.inerte) { return; }

            const d = detalle || {};
            const texto = String(d.message == null ? '' : d.message).trim();

            if (! texto) { return; }

            const urgente = d.assertive === true || d.priority === 'assertive' || d.tone === 'danger';
            const clave = urgente ? 'assertive' : 'polite';
            const region = this.$refs[clave];

            if (! region) { return; }

            clearTimeout(this.escrituras[clave]);
            clearTimeout(this.limpiezas[clave]);

            /* Vaciar → tick → escribir: es lo que fuerza el reanuncio de un mensaje repetido. */
            region.textContent = '';

            this.escrituras[clave] = setTimeout(() => {
                region.textContent = texto;

                if (this.clearAfter > 0) {
                    this.limpiezas[clave] = setTimeout(() => {
                        if (region.textContent === texto) { region.textContent = ''; }
                    }, this.clearAfter);
                }
            }, this.espera);
        }
    }"
    @muni-announce.window="anunciar($event.detail || {})"
    wire:ignore
    data-muni-message="{{ $message }}"
    data-muni-priority="{{ $assertive ? 'assertive' : 'polite' }}"
    {{ $attributes }}
>
    {{-- Sin `role`, sin `tabindex` y sin texto: los tres son requisito, no estilo. --}}
    <div class="muni-sr" x-ref="polite" aria-live="polite" aria-atomic="true"></div>
    <div class="muni-sr" x-ref="assertive" aria-live="assertive" aria-atomic="true"></div>
</div>

@once
    <style>
        /* Oculto a la vista, presente en el árbol de accesibilidad: `display:none` y
           `visibility:hidden` lo sacarían del árbol y la región dejaría de anunciar.
           Se repite la misma declaración que publica `sortable-table` a propósito: el
           anunciador tiene que funcionar en una página que no incluya esa tabla. */
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
    </style>
@endonce
