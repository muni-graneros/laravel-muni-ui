@props([
    'name' => null, // name compartido de los radios
    'label' => null, // pregunta del grupo: va en el <legend>
    'options' => [], // mapa valor => etiqueta, o valor => ['label' => …, 'description' => …]
    'value' => null, // valor marcado al cargar; sin él no hay ninguna opción marcada
    'hint' => null, // ayuda del grupo, atada al fieldset con aria-describedby
    'error' => null, // mensaje de error del grupo
    'inline' => false, // opciones en fila en vez de columna
    'required' => false, // exige elegir una opción
    'requiredText' => 'obligatorio', // palabra junto al asterisco; '' la apaga
    'requiredTextVisible' => false, // muestra esa palabra a la vista
])

@php
    /*
     * Grupo de opciones EXCLUYENTES con radios nativos: la contraparte de
     * checkbox-group. Para dos a cuatro opciones cortas que filtran una vista está
     * segmented; esto es para la pregunta de un formulario («tipo de solicitud»).
     *
     * Identificador del GRUPO. NUNCA uniqid() (DESIGN §10): cambia en cada render,
     * rompe el aria-describedby y ensucia el diffing de Livewire. Mismo patrón
     * determinista que checkbox-group: manda el `id` del consumidor; si no, sale del
     * `name` saneado con sufijo de hash. Cada radio lleva el id del grupo más su
     * posición.
     */
    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        $base = trim((string) $name);

        if ($base === '') {
            $base = 'radio-group-'.substr(sha1((string) json_encode([$label, array_keys((array) $options)], JSON_UNESCAPED_UNICODE)), 0, 8);
        } elseif (! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        $muniId = 'muni-'.$base;
    }

    $muniHintId = $muniId.'-hint';
    $muniErrorId = $muniId.'-error';

    /*
     * `wire:model` y `x-model` van a CADA radio, que es donde vive el valor: sobre el
     * fieldset no enlazaban nada, sin error visible.
     */
    $muniModelo = $attributes->filter(fn ($v, $k) => str_starts_with($k, 'wire:model') || str_starts_with($k, 'x-model'));

    /*
     * `$attributes->merge()` REEMPLAZA, no concatena: el aria-describedby del
     * consumidor se encadena a mano y se saca de la bolsa (DESIGN §8). Va sobre el
     * FIELDSET, como en checkbox-group: atado a cada radio, el lector repetiría la
     * ayuda tantas veces como opciones haya.
     */
    $muniAria = array_filter([
        'aria-describedby' => implode(' ', array_filter([
            trim((string) $attributes->get('aria-describedby')),
            $hint ? $muniHintId : '',
            $error ? $muniErrorId : '',
        ])),
    ]);

    $attributes = $attributes
        ->filter(fn ($v, $k) => ! str_starts_with($k, 'wire:model') && ! str_starts_with($k, 'x-model'))
        ->except(['id', 'aria-describedby']);

    $muniOpciones = [];

    foreach ((array) $options as $clave => $opcion) {
        $muniOpciones[] = [
            'value' => (string) $clave,
            'label' => (string) (is_array($opcion) ? ($opcion['label'] ?? $clave) : $opcion),
            'description' => is_array($opcion) ? ($opcion['description'] ?? null) : null,
        ];
    }

    // Mismo trato de «obligatorio» que checkbox-group e input: texto junto al asterisco.
    $muniObl = trim((string) $requiredText);
    $muniOblClase = $requiredTextVisible ? 'muni-obl' : 'muni-sr';
    $muniOblTexto = $requiredTextVisible ? '('.$muniObl.')' : $muniObl;
@endphp

{{-- Fieldset y legend nativos, sin rol encima: la semántica de grupo ya la dan. Las
     flechas mueven la selección entre radios con el mismo name sin una línea de JS.

     El fieldset lleva tabindex="-1" para que el enlace del resumen de errores aterrice
     en el grupo (mismo trato que checkbox-group); no agrega una parada de tabulación. --}}
<fieldset
    id="{{ $muniId }}"
    tabindex="-1"
    {{ $attributes->merge($muniAria + ['class' => 'muni-radios']) }}
>
    @if ($label)
        <legend class="muni-radios__legend">
            {{ $label }}@if ($required)@if ($muniObl !== '')<span aria-hidden="true" style="color:var(--muni-danger-fg);margin-left:2px;">*</span><span class="{{ $muniOblClase }}"> {{ $muniOblTexto }}</span>@else<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif @endif
        </legend>
    @endif

    @if ($hint)
        <span id="{{ $muniHintId }}" class="muni-radios__hint">{{ $hint }}</span>
    @endif

    <div class="muni-radios__items {{ $inline ? 'muni-radios__items--fila' : '' }}">
        @foreach ($muniOpciones as $muniOpcion)
            @php $muniRid = $muniId.'-'.$loop->index; @endphp
            <label for="{{ $muniRid }}" class="muni-radios__opcion">
                <input type="radio" id="{{ $muniRid }}" class="muni-radio"
                       value="{{ $muniOpcion['value'] }}" @if ($name) name="{{ $name }}" @endif @checked($value !== null && (string) $value === $muniOpcion['value'])
                       @if ($required) required @endif
                       @if ($error) aria-invalid="true" @endif
                       {{ $muniModelo }}>
                <span class="muni-radios__texto">
                    <span>{{ $muniOpcion['label'] }}</span>
                    @if ($muniOpcion['description'])<span class="muni-radios__detalle">{{ $muniOpcion['description'] }}</span>@endif
                </span>
            </label>
        @endforeach
    </div>

    {{-- La región existe desde el primer render: con Livewire el error llega sin recargar,
         y un span que nace de la nada no lo lee ningún lector (WCAG 2.2 AA 4.1.3). --}}
    <div role="status" aria-live="polite" class="muni-radios__live">
        @if ($error)
            {{-- El icono acompaña al color: el estado no se comunica solo con rojo. --}}
            <span id="{{ $muniErrorId }}" class="muni-radios__error">
                <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" style="flex-shrink:0;margin-top:1px;"><path d="M8 2.5L15 14H1L8 2.5z" stroke-linejoin="round"/><path d="M8 6.5v3.2M8 11.8v.2" stroke-linecap="round"/></svg>
                <span>{{ $error }}</span>
            </span>
        @endif
    </div>
</fieldset>

@once
    <style>
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        .muni-obl { font-weight:400; font-size:.92em; color:var(--muni-muted); }
        .muni-radios { min-inline-size:0; margin:0; padding:0; border:0; font-family:var(--muni-font-sans); }
        /* El foco llega por programa desde el resumen de errores: :focus y no :focus-visible. */
        .muni-radios:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        .muni-radios__legend { padding:0; margin:0 0 6px; font-size:12.5px; font-weight:600; line-height:1.35; color:var(--muni-text); }
        .muni-radios__hint { display:block; margin:0 0 6px; font-size:11.5px; line-height:15px; color:var(--muni-muted); }
        .muni-radios__items { display:flex; flex-direction:column; gap:10px; }
        .muni-radios__items--fila { flex-direction:row; flex-wrap:wrap; gap:8px 20px; }
        /* 24px de alto mínimo de fila: blanco de pulsación de WCAG 2.2 AA 2.5.8. */
        .muni-radios__opcion { display:flex; align-items:flex-start; gap:10px; min-height:24px; cursor:pointer; }
        .muni-radios__texto { display:flex; flex-direction:column; gap:2px; font-size:13.5px; line-height:1.35; color:var(--muni-text); }
        .muni-radios__detalle { font-size:12px; color:var(--muni-muted); }
        /* El borde sale de --muni-field-border (3:1, WCAG 2.2 AA 1.4.11): un radio sin
           marcar no tiene más pista visual que ese borde. */
        .muni-radio { appearance:none; flex-shrink:0; width:18px; height:18px; margin:1px 0 0; display:grid; place-content:center; cursor:pointer;
            background:var(--muni-surface); border:1.5px solid var(--muni-field-border); border-radius:50%;
            transition:border-color var(--muni-dur) var(--muni-ease); }
        .muni-radio::after { content:""; width:8px; height:8px; border-radius:50%; background:var(--muni-accent); transform:scale(0); transition:transform var(--muni-dur) var(--muni-ease); }
        .muni-radio:checked { border-color:var(--muni-accent); }
        .muni-radio:checked::after { transform:scale(1); }
        .muni-radio[aria-invalid="true"] { border-color:var(--muni-field-border-error); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (DESIGN §5). */
        .muni-radio:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-radio:disabled { opacity:.5; cursor:not-allowed; }
        .muni-radios__live { min-height:15px; }
        .muni-radios__error { display:inline-flex; align-items:flex-start; gap:4px; margin-top:4px; font-size:11.5px; line-height:15px; font-weight:600; color:var(--muni-danger-fg); background:var(--muni-danger-bg); border-radius:var(--muni-radius-sm); padding:2px 6px; }
        @media (prefers-reduced-motion: reduce) { .muni-radio, .muni-radio::after { transition:none; } }
        /* En alto contraste forzado el fondo pintado desaparece: el punto usa el color del sistema. */
        @media (forced-colors: active) {
            .muni-radio { border-color:ButtonBorder; }
            .muni-radio::after { background:Highlight; }
        }
    </style>
@endonce
