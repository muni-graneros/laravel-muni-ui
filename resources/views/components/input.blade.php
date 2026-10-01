@props([
    'label' => null, // etiqueta visible; null no la emite
    'name' => null, // name del input; también semilla del id
    'type' => 'text', // tipo HTML del input: text | email | number | …
    'error' => null, // mensaje de error; activa aria-invalid
    'hint' => null, // texto de ayuda bajo el campo
    'icon' => null, // SVG crudo decorativo a la izquierda
    'required' => false, // marca el campo como obligatorio
    'requiredText' => 'obligatorio', // palabra junto al asterisco; "" la quita
    'requiredTextVisible' => false, // muestra esa palabra en vez de dejarla solo al lector
])

@php
    /*
     * Identificador del campo. NUNCA uniqid() (DESIGN §10): cambia en cada render,
     * rompe el `for` de la etiqueta y ensucia el diffing de Livewire. Mismo patrón
     * determinista que ya usan tabs.blade.php y tab-panel.blade.php: manda el `id`
     * que pase el consumidor y, si no lo pasa, se deriva de datos estables.
     *
     * El `name` se SANEA para el id y viaja intacto al backend: `items[0][rut]`
     * —lo normal en un formulario repetido de Livewire— produciría un id que no es
     * un selector válido, y dos filas distintas colisionarían. El sufijo de hash
     * mantiene la unicidad entre filas.
     *
     * Sin `name` el id sale de un hash del contenido: es estable entre renders, que
     * es lo que importa. Dos inputs idénticos y anónimos en la misma página
     * colisionarían: para eso está el `id` del consumidor.
     */
    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        $base = (string) $name;

        if ($base === '') {
            $base = 'input-'.substr(sha1(json_encode([$label, $type, $hint], JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
        } elseif (! preg_match('/^[A-Za-z0-9_-]+$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        $muniId = 'muni-'.$base;
    }

    $muniHintId = $muniId.'-hint';
    $muniErrorId = $muniId.'-error';

    /*
     * `$attributes->merge()` REEMPLAZA, no concatena, en todo lo que no sea class ni
     * style: si el consumidor trae su propio aria-describedby, uno de los dos
     * desaparece sin aviso. Por eso se lee, se encadena a mano y se saca de la bolsa
     * antes del merge. La ayuda va primero y el error después, que es el orden en
     * que conviene oírlos.
     */
    $muniAria = array_filter([
        'aria-describedby' => implode(' ', array_filter([
            trim((string) $attributes->get('aria-describedby')),
            $hint ? $muniHintId : '',
            $error ? $muniErrorId : '',
        ])),
        'aria-invalid' => $error ? 'true' : '',
    ]);

    $attributes = $attributes->except(['id', 'aria-describedby']);

    /*
     * `icon` se imprime SIN escapar (echo crudo, más abajo): es un SVG decorativo
     * que el desarrollador escribe en su plantilla, como prop o como slot, con la
     * misma confianza que el resto del marcado. JAMÁS recibe datos del vecino ni
     * de ningún registro: si un dato tiene que decidir el dibujo, se elige el SVG
     * en la plantilla y se pasa el resultado, nunca el dato. Es el criterio que
     * cerró el mismo echo en alert.blade.php (ficha «alert» de docs/GAP-ANALYSIS.md,
     * punto 2 del juez); aquí no hay catálogo de nombres que ofrecer, así que se
     * documenta en vez de cerrarse. Va como comentario PHP y no de Blade a
     * propósito: el primer comentario Blade del archivo es la descripción que
     * `npm run registro` escribe en registry.json.
     */

    /*
     * La palabra «obligatorio» va en TEXTO junto a la etiqueta, y no solo el
     * asterisco. El asterisco es un glifo: un lector de pantalla lo lee
     * «asterisco» —o no lo lee—, y quien no conoce la convención no sabe qué
     * significa. Con la palabra al lado, el asterisco pasa a ser decoración y
     * lleva aria-hidden; sin ella (`required-text=""`) vuelve a ser el único
     * indicador y se deja audible, porque taparlo dejaría al campo sin ninguno.
     *
     * Se apaga con la CADENA VACÍA, no con `null`: la directiva de props aplica
     * el valor por defecto con `??`, así que un null explícito vuelve al texto
     * de fábrica. Es lo contrario de lo que hace un `:algo="null"` sobre un
     * componente hijo, que sí gana (DESIGN §8).
     *
     * Oculta a la vista por defecto: el `required` nativo ya la anuncia y el
     * formulario pone la leyenda general (técnica G184), así que repetirla
     * visible en los treinta campos de un trámite es ruido. Con
     * `required-text-visible` se ve, para el formulario corto donde la leyenda
     * queda lejos.
     */
    $muniObl = trim((string) $requiredText);
    $muniOblClase = $requiredTextVisible ? 'muni-obl' : 'muni-sr';
    $muniOblTexto = $requiredTextVisible ? '('.$muniObl.')' : $muniObl;
@endphp

<div style="display:flex;flex-direction:column;gap:6px;">
    @if ($label)
        <label for="{{ $muniId }}" style="font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;color:var(--muni-text);">
            {{ $label }}@if ($required)@if ($muniObl !== '')<span aria-hidden="true" style="color:var(--muni-danger-fg);margin-left:2px;">*</span><span class="{{ $muniOblClase }}"> {{ $muniOblTexto }}</span>@else<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif @endif
        </label>
    @endif

    <div style="position:relative;display:flex;align-items:center;">
        @if ($icon)
            <span aria-hidden="true" style="position:absolute;left:11px;display:inline-flex;color:var(--muni-muted);pointer-events:none;">{!! $icon !!}</span>
        @endif
        <input
            id="{{ $muniId }}"
            type="{{ $type }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required @endif
            {{ $attributes->merge($muniAria + [
                'class' => 'muni-input',
                'style' => 'width:100%;padding:10px 12px;'.($icon ? 'padding-left:36px;' : '')
                    .'font-family:var(--muni-font-sans);font-size:13.5px;color:var(--muni-text);'
                    .'background:var(--muni-surface);border:1px solid '.($error ? 'var(--muni-field-border-error)' : 'var(--muni-field-border)').';'
                    .'border-radius:var(--muni-radius-sm);transition:border-color var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease);',
            ]) }}
        >
    </div>

    {{-- La ayuda y el error CONVIVEN, cada uno con su id: el `@elseif` de antes
         borraba el «formato 12.345.678-9» justo cuando el vecino lo necesita, que
         es cuando se equivocó.

         La región existe desde el primer render y reserva el alto de una línea:
         con Livewire el error llega en un round-trip sin recargar la página, así
         que un <span> que nace de la nada no lo lee ningún lector de pantalla
         (WCAG 2.2 AA 4.1.3) y además empuja el contenido de abajo. --}}
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
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        .muni-obl { font-weight:400; font-size:.92em; color:var(--muni-muted); }
        .muni-input::placeholder { color: var(--muni-hint); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
        .muni-input:disabled { background: var(--muni-surface-2); color: var(--muni-muted); cursor: not-allowed; }
    </style>
@endonce
