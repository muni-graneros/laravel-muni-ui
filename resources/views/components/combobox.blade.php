@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'selectedLabel' => null,
    'search' => null,
    'model' => null,
    'searchModel' => null,
    'placeholder' => 'Escribe para buscar…',
    'listLabel' => null,
    'emptyText' => 'Sin resultados.',
    'clearLabel' => 'Limpiar la búsqueda',
    'hint' => null,
    'error' => null,
    'required' => false,
    'loading' => false,
])

@php
    /*
     * Buscador dentro de una lista larga, para formularios Livewire FUERA de
     * Filament. Fase A: dentro de un panel el campo lo sigue pintando Filament con
     * Select::searchable(), así que este componente NO reemplaza —ni retira— el
     * parche `resources/js/nombre-accesible-select.js`. Eso es la fase B, que es
     * una clase Field de Filament y todavía no existe.
     *
     * CÓMO SOBREVIVE A LIVEWIRE 3
     * En Livewire 3 cada respuesta del servidor vuelve a crear el x-data (el
     * inventario lo documenta con `rating`, que pierde el valor elegido). Por eso:
     *   - el id elegido vive en un <input type="hidden"> con name y wire:model;
     *   - la etiqueta visible la pinta el SERVIDOR desde `selectedLabel`;
     *   - en Alpine solo hay estado efímero de interfaz (abierta, resaltado, si
     *     hay texto), y lo poco que importa se vuelve a deducir del DOM al
     *     arrancar: si el foco sigue en el campo y el texto no es el del registro
     *     ya elegido, la lista se reabre sola.
     *
     * CONTRATO CON EL SERVIDOR
     *   - `options` llega YA acotada. El tope duro es 20 y el componente lo
     *     vuelve a aplicar: una lista de miles de contribuyentes pintada entera
     *     es el INP perdido. Cuando llegan más, se muestran 20 y la región viva
     *     avisa de cuántos hay.
     *   - `searchModel` se enlaza con debounce de 300 ms; el recuento se anuncia
     *     cuando los resultados se asientan, nunca por tecla, porque lo escribe
     *     el servidor y no Alpine.
     *   - SELECCIÓN ESTRICTA, un solo modo: si el valor no resuelve a un id, el
     *     texto libre no vale nada y se descarta al salir del campo.
     */
    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        /*
         * Identificador determinista, NUNCA uniqid() (DESIGN §10): cambia en cada
         * render, rompe el `for` de la etiqueta y ensucia el diffing de Livewire.
         * El `name` es obligatorio justamente para que exista una raíz estable. Se
         * sanea SOLO para el id —`items[0][titular_id]` no es un selector válido y
         * dos filas de un formulario repetido colisionarían—; al backend el `name`
         * viaja intacto.
         */
        $muniBase = (string) $name;

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $muniBase)) {
            $muniBase = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $muniBase), '-')
                .'-'.substr(sha1($muniBase), 0, 6);
        }

        $muniId = 'muni-'.$muniBase;
    }

    $muniListaId = $muniId.'-lista';
    $muniHintId = $muniId.'-hint';
    $muniErrorId = $muniId.'-error';

    /* Resultados normalizados: lista de arreglos, mapa valor => etiqueta o lista simple. */
    $muniOpciones = [];

    foreach ($options as $muniClave => $muniOpcion) {
        if (is_array($muniOpcion)) {
            $muniOpciones[] = [
                'value' => (string) ($muniOpcion['value'] ?? $muniClave),
                'label' => (string) ($muniOpcion['label'] ?? $muniOpcion['value'] ?? $muniClave),
                'hint' => isset($muniOpcion['hint']) ? (string) $muniOpcion['hint'] : null,
            ];
        } elseif (is_int($muniClave)) {
            $muniOpciones[] = ['value' => (string) $muniOpcion, 'label' => (string) $muniOpcion, 'hint' => null];
        } else {
            $muniOpciones[] = ['value' => (string) $muniClave, 'label' => (string) $muniOpcion, 'hint' => null];
        }
    }

    $muniTope = 20;
    $muniTotal = count($muniOpciones);
    $muniVisibles = array_slice($muniOpciones, 0, $muniTope);

    $muniBuscando = trim((string) $search) !== '';
    $muniTexto = $muniBuscando ? (string) $search : (string) ($selectedLabel ?? '');
    $muniElegida = (string) ($selectedLabel ?? '');

    /*
     * El recuento lo escribe el servidor, que llega con el debounce ya aplicado:
     * así la región viva habla cuando los resultados se ASIENTAN y no en cada
     * tecla. Mientras carga se queda muda, para no anunciar el recuento anterior
     * como si fuera el nuevo; y sin búsqueda tampoco dice nada, porque una región
     * viva que habla al cargar la página es ruido.
     */
    $muniAnuncio = '';

    if (! $loading && ($muniTotal > 0 || $muniBuscando)) {
        if ($muniTotal === 0) {
            $muniAnuncio = $emptyText;
        } elseif ($muniTotal > $muniTope) {
            $muniAnuncio = "Se muestran {$muniTope} de {$muniTotal} resultados. Afina la búsqueda.";
        } else {
            $muniAnuncio = $muniTotal === 1 ? '1 resultado.' : "{$muniTotal} resultados.";
        }
    }

    $muniListLabel = (string) ($listLabel ?: ($label ? $label.': resultados' : 'Resultados de la búsqueda'));

    /*
     * merge() REEMPLAZA en todo lo que no sea class ni style: el aria-describedby
     * del consumidor y el del componente no pueden coexistir por merge (DESIGN §8).
     * Se lee, se encadena a mano y se saca de la bolsa.
     */
    $muniDescrito = implode(' ', array_filter([
        trim((string) $attributes->get('aria-describedby')),
        $hint ? $muniHintId : '',
        $error ? $muniErrorId : '',
    ]));

    $attributes = $attributes->except(['id', 'aria-describedby']);
@endphp

<div
    x-data="{
        open: false,
        activo: -1,
        texto: '',

        init() {
            const campo = this.$refs.campo;
            this.texto = campo.value;
            /* Livewire 3 vuelve a crear este x-data en cada respuesta del servidor.
               Nada que importe se guarda acá: el id vive en el input oculto y la
               etiqueta la pinta el servidor. Lo único que se recupera es si la
               lista debía seguir abierta, y se deduce del DOM. */
            this.$nextTick(() => {
                if (document.activeElement === campo && campo.value !== (campo.dataset.muniElegido || '')) {
                    this.abrir();
                }
            });
        },

        opciones() {
            return this.$refs.lista ? Array.from(this.$refs.lista.querySelectorAll('[role=option]')) : [];
        },

        abrir() {
            if (this.opciones().length) { this.open = true; }
        },

        cerrar() {
            this.open = false;
            this.activo = -1;
        },

        /* El foco NO se mueve nunca a las opciones: se queda en el campo y el
           resaltado se anuncia por aria-activedescendant. */
        idActivo() {
            const opcion = this.opciones()[this.activo];
            return opcion ? opcion.id : null;
        },

        seguir() {
            this.$nextTick(() => {
                const opcion = this.opciones()[this.activo];
                if (opcion) { opcion.scrollIntoView({ block: 'nearest' }); }
            });
        },

        mover(paso) {
            const total = this.opciones().length;
            if (! total) { return; }
            if (! this.open || this.activo < 0) {
                this.open = true;
                this.activo = paso > 0 ? 0 : total - 1;
            } else {
                this.activo = (this.activo + paso + total) % total;
            }
            this.seguir();
        },

        ir(indice) {
            const total = this.opciones().length;
            if (! total || ! this.open) { return; }
            this.activo = indice < 0 ? total - 1 : Math.min(indice, total - 1);
            this.seguir();
        },

        escribir(elemento, valor) {
            elemento.value = valor;
            elemento.dispatchEvent(new Event('input', { bubbles: true }));
            elemento.dispatchEvent(new Event('change', { bubbles: true }));
        },

        alEscribir(evento) {
            this.texto = evento.target.value;
            this.activo = -1;
            this.abrir();
        },

        elegir(indice) {
            const opcion = this.opciones()[indice];
            if (! opcion) { return; }
            const campo = this.$refs.campo;
            campo.dataset.muniElegido = opcion.dataset.muniLabel || '';
            this.escribir(this.$refs.oculto, opcion.dataset.muniValue || '');
            this.escribir(campo, campo.dataset.muniElegido);
            this.texto = campo.value;
            this.cerrar();
            campo.focus();
        },

        limpiar() {
            const campo = this.$refs.campo;
            campo.dataset.muniElegido = '';
            this.escribir(this.$refs.oculto, '');
            this.escribir(campo, '');
            this.texto = '';
            this.cerrar();
            campo.focus();
        },

        escapar() {
            if (this.open) { this.cerrar(); this.$refs.campo.focus(); return; }
            this.limpiar();
        },

        /* Selección estricta: sin id, el texto libre no vale nada y se descarta. */
        descartar() {
            this.cerrar();
            const campo = this.$refs.campo;
            const elegido = this.$refs.oculto.value !== '' ? (campo.dataset.muniElegido || '') : '';
            if (campo.value !== elegido) { this.escribir(campo, elegido); }
            this.texto = campo.value;
        },
    }"
    @focusout="if (! $el.contains($event.relatedTarget)) { descartar(); }"
    {{ $attributes->merge(['class' => 'muni-combo']) }}
>
    @if ($label)
        <label for="{{ $muniId }}" style="font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;color:var(--muni-text);">
            {{ $label }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
        </label>
    @endif

    {{-- El id elegido: campo real del formulario, no memoria de Alpine. Con `name`
         viaja en un submit normal; con `wire:model.live` el servidor se entera de
         la elección y devuelve la etiqueta ya resuelta, que es lo que hace que el
         texto visible sobreviva a la respuesta en Livewire 3. --}}
    <input
        type="hidden"
        x-ref="oculto"
        name="{{ $name }}"
        value="{{ $value }}"
        @if ($model) wire:model.live="{{ $model }}" @endif
    >

    <div class="muni-combo__campo" style="anchor-name:--{{ $muniId }};">
        <input
            role="combobox"
            id="{{ $muniId }}"
            type="text"
            class="muni-combo__input"
            x-ref="campo"
            value="{{ $muniTexto }}"
            data-muni-elegido="{{ $muniElegida }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="{{ $muniListaId }}"
            @if ($muniDescrito !== '') aria-describedby="{{ $muniDescrito }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($required) required @endif
            @if ($searchModel) wire:model.live.debounce.300ms="{{ $searchModel }}" @endif
            :aria-expanded="open ? 'true' : 'false'"
            :aria-activedescendant="idActivo()"
            @input="alEscribir($event)"
            @keydown.arrow-down.prevent="mover(1)"
            @keydown.arrow-up.prevent="mover(-1)"
            @keydown.enter="if (open && activo >= 0) { $event.preventDefault(); elegir(activo); }"
            @keydown.escape.prevent.stop="escapar()"
            @keydown.home="if (open) { $event.preventDefault(); ir(0); }"
            @keydown.end="if (open) { $event.preventDefault(); ir(-1); }"
            @click="abrir()"
        >

        {{-- Aparece solo cuando hay texto que limpiar. --}}
        <button
            type="button"
            class="muni-combo__clear"
            x-cloak
            x-show="texto !== ''"
            aria-label="{{ $clearLabel }}"
            @mousedown.prevent
            @click="limpiar()"
        >
            <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" width="12" height="12"><path d="M4 4l8 8M12 4l-8 8" stroke-linecap="round"/></svg>
        </button>

        {{-- Estado de carga: dibujo decorativo. Lo semántico es el aria-busy de la
             lista y el recuento de la región viva, no esta ruedita. --}}
        <span
            class="muni-combo__spin"
            aria-hidden="true"
            style="{{ $loading ? '' : 'display:none;' }}"
            @if ($searchModel) wire:loading.delay wire:target="{{ $searchModel }}" @endif
        >
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14" class="muni-combo__gira"><path d="M8 1.6a6.4 6.4 0 1 1-4.53 1.87" stroke-linecap="round"/></svg>
        </span>

        <ul
            id="{{ $muniListaId }}"
            role="listbox"
            aria-label="{{ $muniListLabel }}"
            @if ($loading) aria-busy="true" @endif
            class="muni-combo__lista"
            x-ref="lista"
            x-show="open"
            x-transition:enter="muni-combo-t"
            x-transition:enter-start="muni-combo-t0"
            x-transition:enter-end="muni-combo-t1"
            x-transition:leave="muni-combo-t"
            x-transition:leave-start="muni-combo-t1"
            x-transition:leave-end="muni-combo-t0"
            style="display:none;position-anchor:--{{ $muniId }};"
        >
            @foreach ($muniVisibles as $muniIndice => $muniOpcion)
                <li
                    role="option"
                    id="{{ $muniListaId }}-{{ $muniIndice }}"
                    class="muni-combo__opcion"
                    data-muni-value="{{ $muniOpcion['value'] }}"
                    data-muni-label="{{ $muniOpcion['label'] }}"
                    aria-selected="false"
                    :aria-selected="activo === {{ $muniIndice }} ? 'true' : 'false'"
                    @mousemove="activo = {{ $muniIndice }}"
                    @mousedown.prevent="elegir({{ $muniIndice }})"
                >
                    @if ($value !== null && (string) $value === $muniOpcion['value'])
                        <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="var(--muni-accent)" stroke-width="2" width="13" height="13" style="flex-shrink:0;"><path d="M3 8.5l3.2 3.2L13 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="muni-combo__sr">Elegido:</span>
                    @endif
                    <span>{{ $muniOpcion['label'] }}</span>
                    @if ($muniOpcion['hint'])
                        <span class="muni-combo__pista">{{ $muniOpcion['hint'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    {{-- El recuento. Región viva persistente y visible: existe desde el primer
         render con su alto reservado, así que no empuja el contenido de abajo ni
         nace de la nada, que es el caso que ningún lector de pantalla anuncia. --}}
    <p id="{{ $muniId }}-resultados" class="muni-combo__estado" role="status" aria-live="polite">{{ $muniAnuncio }}</p>

    {{-- La ayuda y el error CONVIVEN, cada uno con su id, encadenados los dos en
         aria-describedby: mismo contrato que input y select. --}}
    <div role="status" aria-live="polite" style="display:flex;flex-direction:column;gap:2px;min-height:15px;">
        @if ($hint)
            <span id="{{ $muniHintId }}" style="font-size:11.5px;line-height:15px;color:var(--muni-hint);">{{ $hint }}</span>
        @endif
        @if ($error)
            {{-- El icono acompaña al color: el estado no se comunica solo con rojo. --}}
            <span id="{{ $muniErrorId }}" style="display:flex;align-items:flex-start;gap:4px;font-size:11.5px;line-height:15px;font-weight:600;color:var(--muni-danger-fg);background:var(--muni-danger-bg);border-radius:var(--muni-radius-sm);padding:2px 6px;">
                <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" style="flex-shrink:0;margin-top:1px;"><path d="M8 2.5L15 14H1L8 2.5z" stroke-linejoin="round"/><path d="M8 6.5v3.2M8 11.8v.2" stroke-linecap="round"/></svg>
                <span>{{ $error }}</span>
            </span>
        @endif
    </div>
</div>

@once
    <style>
        /* Todo lo que el componente necesita para verse bien viaja con él (DESIGN §7):
           dentro de un panel Filament solo se carga muni-ui-filament.css. */
        .muni-combo { position: relative; display: flex; flex-direction: column; gap: 6px; font-family: var(--muni-font-sans); }
        .muni-combo [x-cloak] { display: none !important; }
        .muni-combo__sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; border: 0; }

        .muni-combo__campo { position: relative; display: flex; align-items: center; }
        .muni-combo__input { width: 100%; padding: 10px 66px 10px 12px; font-family: var(--muni-font-sans); font-size: 13.5px; color: var(--muni-text); background: var(--muni-surface); border: 1px solid var(--muni-field-border); border-radius: var(--muni-radius-sm); transition: border-color var(--muni-dur) var(--muni-ease); }
        .muni-combo__input::placeholder { color: var(--muni-hint); }
        /* El outline es el indicador REAL: la box-shadow del anillo se computa
           transparente dentro de Filament y no queda nada (DESIGN §5). */
        .muni-combo__input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
        .muni-combo__input[aria-invalid="true"] { border-color: var(--muni-danger-border); }
        .muni-combo__input:disabled { background: var(--muni-surface-2); color: var(--muni-muted); cursor: not-allowed; }

        .muni-combo__clear { position: absolute; right: 34px; min-width: 24px; min-height: 24px; display: inline-flex; align-items: center; justify-content: center; padding: 0; background: transparent; color: var(--muni-muted); border: 1px solid transparent; border-radius: var(--muni-radius-sm); cursor: pointer; transition: color var(--muni-dur) var(--muni-ease); }
        .muni-combo__clear:hover { color: var(--muni-text); border-color: var(--muni-border); }
        .muni-combo__clear:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 1px; }

        .muni-combo__spin { position: absolute; right: 10px; display: inline-flex; color: var(--muni-muted); pointer-events: none; }
        /* El giro solo donde el movimiento es bienvenido; sin la consulta queda quieto. */
        @media (prefers-reduced-motion: no-preference) {
            .muni-combo__gira { animation: muni-combo-giro 900ms linear infinite; }
        }
        @keyframes muni-combo-giro { to { transform: rotate(360deg); } }

        /* Base: posición absoluta, que funciona en todas partes. La mejora que
           escapa de un modal o de una tabla con overflow oculto va más abajo,
           dentro del bloque de soporte condicional. */
        .muni-combo__lista { position: absolute; top: 100%; left: 0; right: 0; z-index: 40; margin: 4px 0 0; padding: 4px; max-height: 264px; overflow-y: auto; list-style: none; background: var(--muni-surface); border: 1px solid var(--muni-border); border-radius: var(--muni-radius-sm); box-shadow: var(--muni-shadow-lg); }
        .muni-combo__opcion { display: flex; align-items: center; gap: 8px; min-height: 34px; padding: 6px 10px; border-radius: var(--muni-radius-sm); border-left: 3px solid transparent; font-size: 13.5px; line-height: 18px; color: var(--muni-text); cursor: pointer; }
        /* El resaltado no se comunica solo con color: banda al borde y negrita,
           más aria-selected para quien no ve ninguna de las dos. */
        .muni-combo__opcion[aria-selected="true"] { background: var(--muni-accent-soft); border-left-color: var(--muni-accent); font-weight: 600; }
        .muni-combo__pista { margin-left: auto; padding-left: 8px; font-family: var(--muni-font-mono); font-size: 11.5px; color: var(--muni-muted); }
        .muni-combo__estado { margin: 0; min-height: 15px; font-size: 11.5px; line-height: 15px; color: var(--muni-muted); }

        .muni-combo-t { transition: opacity var(--muni-dur) var(--muni-ease); }
        .muni-combo-t0 { opacity: 0; }
        .muni-combo-t1 { opacity: 1; }

        /* Mejora progresiva (DESIGN §10): con anclaje CSS el desplegable sale del
           flujo y deja de recortarse dentro de un modal o de una tabla con el
           desbordamiento oculto. Sin soporte, manda la regla absoluta de arriba. */
        @supports (anchor-name: --muni-combo-ancla) {
            .muni-combo__lista { position: fixed; top: anchor(bottom); left: anchor(left); right: anchor(right); margin-top: 4px; position-try-fallbacks: flip-block; }
        }
    </style>
@endonce
