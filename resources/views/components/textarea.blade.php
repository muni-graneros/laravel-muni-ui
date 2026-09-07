@props([
    'label' => null,
    'name' => null,
    'error' => null,
    'hint' => null,
    'required' => false,
    'rows' => 4,
    'maxlength' => null,
])

@php
    /*
     * Campo de texto largo: la «descripción del requerimiento» del ingreso público de
     * Atención al Vecino y el relato del procedimiento en terreno de seguridad.
     *
     * Identificador del campo. NUNCA uniqid() (DESIGN §8): cambia en cada render, rompe
     * el `for` de la etiqueta y ensucia el diffing de Livewire. Mismo patrón determinista
     * que ya usan tabs.blade.php, sortable-table.blade.php e input.blade.php: manda el
     * `id` que pase el consumidor y, si no lo pasa, se deriva de datos estables.
     *
     * El `name` se SANEA para el id y viaja intacto al backend: `items[0][detalle]`
     * —lo normal en un formulario repetido de Livewire— produciría un id que no es un
     * selector válido, y dos filas distintas colisionarían. El sufijo de hash mantiene
     * la unicidad entre filas.
     */
    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        $base = (string) $name;

        if ($base === '') {
            $base = 'textarea-'.substr(sha1(json_encode([$label, $hint, $rows], JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
        } elseif (! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        $muniId = 'muni-'.$base;
    }

    $muniHintId = $muniId.'-hint';
    $muniErrorId = $muniId.'-error';
    $muniContadorId = $muniId.'-contador';

    $muniRows = max(1, (int) $rows);
    $muniMax = $maxlength === null ? 0 : max(0, (int) $maxlength);
    $muniValor = (string) $slot;
    $muniUsados = mb_strlen(trim($muniValor));

    /*
     * `$attributes->merge()` REEMPLAZA, no concatena, en todo lo que no sea class ni
     * style: si el consumidor trae su propio aria-describedby, uno de los dos desaparece
     * sin aviso. Por eso se lee, se encadena a mano y se saca de la bolsa antes del
     * merge. Orden: ayuda, contador y error, que es el orden en que conviene oírlos.
     */
    $muniAria = array_filter([
        'aria-describedby' => implode(' ', array_filter([
            trim((string) $attributes->get('aria-describedby')),
            $hint ? $muniHintId : '',
            $muniMax > 0 ? $muniContadorId : '',
            $error ? $muniErrorId : '',
        ])),
        'aria-invalid' => $error ? 'true' : '',
    ]);

    $attributes = $attributes->except(['id', 'aria-describedby', 'rows', 'maxlength']);
@endphp

{{-- El crecimiento con el contenido es MEJORA, no requisito: sin una línea de JS el
     campo ya sale con el alto de `rows`, que es el contrato mínimo. El camino
     principal es `field-sizing: content` (cero JS y cero reflow, ver el @supports de
     abajo); el x-data solo existe para los navegadores que aún no lo traen y para el
     contador. Ninguna expresión de Alpine lleva texto del anfitrión dentro: un
     apóstrofo en $label descuadraría la expresión y tumbaría el Alpine de la página
     entera (la lección de file-dropzone). --}}
<div
    style="display:flex;flex-direction:column;gap:6px;"
    x-data="{
        max: {{ $muniMax }},
        usados: {{ $muniUsados }},
        umbral: null,
        aviso: '',
        pendiente: false,
        /* Si el navegador redimensiona por CSS no se toca el alto desde JS. */
        autosize: ! (window.CSS && window.CSS.supports && window.CSS.supports('field-sizing', 'content')),
        get restante() { return Math.max(this.max - this.usados, 0) },
        get contador() { return this.restante + ' de ' + this.max + ' caracteres disponibles' },
        /* Medir en cada tecla provoca reflow y castiga el INP de un formulario largo:
           se agrupa en un requestAnimationFrame y se sale si ya hay uno pedido. */
        medir(el) {
            if (! this.autosize || this.pendiente) { return }
            this.pendiente = true
            requestAnimationFrame(() => {
                this.pendiente = false
                el.style.height = 'auto'
                el.style.height = el.scrollHeight + 'px'
            })
        },
        /* El contador visible cambia con cada tecla; el AVISO no. Solo habla al cruzar
           un umbral, o el lector de pantalla relee el número letra a letra. */
        sincronizar(el) {
            this.usados = el.value.length
            this.medir(el)
            if (this.max <= 0) { return }
            const r = this.restante
            const u = r <= 0 ? 0 : (r <= 10 ? 10 : (r <= 25 ? 25 : null))
            if (u === this.umbral) { return }
            this.umbral = u
            this.aviso = u === null ? '' : (u === 0
                ? 'Alcanzaste el límite de ' + this.max + ' caracteres.'
                : 'Quedan ' + r + ' caracteres.')
        },
    }"
    x-init="$nextTick(() => sincronizar($refs.campo))"
    {{-- Se escribe `x-on:` y no `@livewire:…`: `@livewire` ES una directiva de Blade en
         los hosts que tienen Livewire instalado, y el compilador se la comería. Con
         wire:navigate la página no se recarga y el campo hay que volver a medirlo. --}}
    x-on:livewire:navigated.window="$nextTick(() => sincronizar($refs.campo))"
>
    @if ($label)
        <label for="{{ $muniId }}" style="font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;color:var(--muni-text);">
            {{ $label }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
        </label>
    @endif

    <textarea
        id="{{ $muniId }}"
        x-ref="campo"
        rows="{{ $muniRows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($required) required @endif
        @if ($muniMax > 0) maxlength="{{ $muniMax }}" @endif
        @input="sincronizar($event.target)"
        {{ $attributes->merge($muniAria + [
            'class' => 'muni-textarea',
            'style' => 'width:100%;padding:10px 12px;resize:vertical;'
                .'min-height:calc('.$muniRows.' * 1.5em + 22px);max-height:40vh;overflow-y:auto;'
                .'font-family:var(--muni-font-sans);font-size:13.5px;line-height:1.5;color:var(--muni-text);'
                .'background:var(--muni-surface);border:1px solid '.($error ? 'var(--muni-danger-border)' : 'var(--muni-border)').';'
                .'border-radius:var(--muni-radius-sm);transition:border-color var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease);',
        ]) }}
    >{{ $slot }}</textarea>

    {{-- En un teléfono el contador y la ayuda no caben en la misma línea: sin el
         wrap, «Cuenta qué pasó, dónde y cuándo» se parte en cuatro líneas de dos
         palabras. Con `flex-wrap` el contador baja entero a la suya. --}}
    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:2px 12px;">
        {{-- La ayuda y el error CONVIVEN, cada uno con su id: perder el «cuenta qué
             pasó, dónde y cuándo» justo cuando el vecino se equivocó es dejarlo sin la
             instrucción para corregir.

             La región existe desde el primer render y reserva el alto de una línea: con
             Livewire el error llega en un round-trip sin recargar, así que un <span> que
             nace de la nada no lo lee ningún lector (WCAG 2.2 AA 4.1.3) y además empuja
             el contenido de abajo. --}}
        <div role="status" aria-live="polite" style="display:flex;flex-direction:column;gap:2px;min-height:15px;flex:1 1 12rem;">
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

        @if ($muniMax > 0)
            {{-- El contador va FUERA de la región viva: está encadenado en
                 aria-describedby, así que el lector lo dice al enfocar el campo, pero no
                 lo relee en cada tecla. El texto se renderiza en el servidor para que
                 exista sin JS; Alpine solo lo actualiza. --}}
            <span id="{{ $muniContadorId }}" class="muni-textarea__contador" x-text="contador"
                  style="flex:0 0 auto;font-size:11.5px;line-height:15px;color:var(--muni-hint);font-family:var(--muni-font-mono);white-space:nowrap;">{{ max($muniMax - $muniUsados, 0) }} de {{ $muniMax }} caracteres disponibles</span>
        @endif
    </div>

    @if ($muniMax > 0)
        {{-- El aviso por umbrales, solo para tecnología asistiva. --}}
        <span class="muni-sr" role="status" aria-live="polite" x-text="aviso"></span>
    @endif
</div>

@once
    <style>
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        .muni-textarea::placeholder { color: var(--muni-hint); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-textarea:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
        .muni-textarea:disabled { background: var(--muni-surface-2); color: var(--muni-muted); cursor: not-allowed; }

        /* Camino PRINCIPAL del crecimiento: cero JS, cero reflow y —al ser CSS— sobrevive
           al morph de Livewire, que es justo donde el truco de medir scrollHeight en
           x-init se pierde y el alto vuelve al de fábrica tras cada round-trip.
           Va dentro de @supports como mejora progresiva (DESIGN §8); donde no exista,
           el x-data de arriba se enciende solo y mide. El `resize` se conserva para que
           el funcionario pueda agrandar a mano. */
        @supports (field-sizing: content) {
            .muni-textarea { field-sizing: content; }
        }
    </style>
@endonce
