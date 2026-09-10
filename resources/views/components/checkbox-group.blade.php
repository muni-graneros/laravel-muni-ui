@props([
    'name',
    'legend',
    'options' => [],
    /*
     * NINGUNA CASILLA VIENE MARCADA, y no es una preferencia de estilo.
     *
     * Un grupo de casillas de consentimiento —los requisitos que el vecino
     * declara haber entregado, las categorías de la ficha de inscripción— cae
     * bajo la Ley 21.719: el consentimiento tiene que ser una manifestación de
     * voluntad LIBRE, específica e inequívoca. Un grupo que llega premarcado no
     * es una manifestación de nadie, y el asiento de licitud que el municipio
     * guarde con ese dato no sirve como prueba.
     *
     * Por eso la selección es un arreglo VACÍO por defecto y es la única puerta
     * para marcar: el componente no lee ninguna marca desde `options`, así que
     * premarcar es siempre una decisión explícita del anfitrión, escrita en la
     * llamada y visible en la revisión de código.
     *
     * El componente NO tiene semántica de consentimiento: es un grupo neutro. El
     * texto legal y la base de licitud los pone el host.
     */
    'selected' => [],
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    /*
     * Identificador del GRUPO. NUNCA uniqid() (DESIGN §10): cambia en cada render,
     * rompe el aria-describedby y ensucia el diffing de Livewire. Mismo patrón
     * determinista que input.blade.php y checkbox.blade.php.
     *
     * El `name` de la prop es el del campo —`requisitos`—, sin la notación de
     * arreglo: así el id queda `muni-requisitos` y coincide con el destino al que
     * enlaza error-summary para la clave de validación `requisitos`. La notación
     * de arreglo se la pone el componente a las casillas, que es donde hace falta
     * para que PHP reciba los varios valores.
     */
    $muniNombre = (string) $name;
    $muniBaseNombre = (string) preg_replace('/\[\]$/', '', $muniNombre);
    $muniNombreCasilla = str_ends_with($muniNombre, '[]') ? $muniNombre : $muniNombre.'[]';

    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        $base = trim($muniBaseNombre);

        if ($base === '') {
            $base = 'checkbox-group-'.substr(sha1((string) json_encode([$legend], JSON_UNESCAPED_UNICODE)), 0, 8);
        } elseif (! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        $muniId = 'muni-'.$base;
    }

    $muniHintId = $muniId.'-hint';
    $muniErrorId = $muniId.'-error';

    /*
     * `$attributes->merge()` REEMPLAZA, no concatena: si el consumidor trae su
     * propio aria-describedby, uno de los dos desaparece sin aviso. Se lee, se
     * encadena a mano y se saca de la bolsa antes de fusionar (DESIGN §8).
     *
     * Va sobre el FIELDSET y no sobre cada casilla: atado a los inputs, el lector
     * de pantalla repite «elige al menos un requisito» tantas veces como casillas
     * haya. Es el único punto delicado de este componente.
     *
     * No se emite aria-invalid en el fieldset: su soporte fuera de los roles de
     * control no está garantizado, y el error ya se anuncia por la descripción
     * del grupo y se ve con el icono y el texto de abajo.
     */
    $muniAria = array_filter([
        'aria-describedby' => implode(' ', array_filter([
            trim((string) $attributes->get('aria-describedby')),
            $hint ? $muniHintId : '',
            $error ? $muniErrorId : '',
        ])),
    ]);

    $attributes = $attributes->except(['id', 'aria-describedby']);

    // Selección: siempre cadenas, para comparar contra el `value` de la opción
    // sin que 0 == 'rol' de PHP marque una casilla que nadie pidió.
    $muniSeleccion = array_map(fn ($v) => (string) $v, array_values((array) $selected));

    /*
     * Las opciones aceptan las dos formas que se usan en el ecosistema:
     * un arreglo asociativo valor => etiqueta, o una lista de arreglos con
     * `value`, `label` y `description`. Nada más: una clave `checked` en la
     * opción sería la puerta de atrás para premarcar sin pasar por `selected`.
     */
    $muniOpciones = [];

    foreach ((array) $options as $clave => $opcion) {
        if (is_array($opcion)) {
            $valor = (string) ($opcion['value'] ?? $clave);
            $etiqueta = (string) ($opcion['label'] ?? $valor);
            $descripcion = $opcion['description'] ?? null;
        } else {
            $valor = (string) (is_int($clave) ? $opcion : $clave);
            $etiqueta = (string) $opcion;
            $descripcion = null;
        }

        $muniOpciones[] = [
            'value' => $valor,
            'label' => $etiqueta,
            'description' => $descripcion,
            'checked' => in_array($valor, $muniSeleccion, true),
        ];
    }
@endphp

{{-- Un grupo de casillas NO es la suma de sus casillas: es un solo campo.
     El fieldset y el legend son nativos y no llevan un rol encima, que sería
     ruido redundante sobre la semántica que el elemento ya tiene.

     Tampoco es un radiogroup: cada casilla conserva su parada de tabulación y
     su Espacio nativos, sin una línea de JS que gestione el foco.

     El `required` del grupo se muestra en el legend y NO se emite en las
     casillas: el `required` nativo de una casilla obliga a marcar ESA, así que
     ponerlo en las tres exigiría marcarlas todas. «Al menos una» lo valida el
     servidor y llega por la prop `error`.

     El fieldset lleva tabindex="-1" para que el enlace del resumen de errores
     aterrice en el grupo y el lector anuncie el legend y el error; no agrega una
     parada de tabulación. Mismo trato que en error-summary: el foco llega por
     programa y varios navegadores no lo consideran «visible», así que la regla
     de foco es :focus y no :focus-visible. --}}
<fieldset
    id="{{ $muniId }}"
    tabindex="-1"
    {{ $attributes->merge($muniAria + ['class' => 'muni-cbgroup']) }}
>
    <legend class="muni-cbgroup__legend">
        {{ $legend }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
    </legend>

    {{-- La ayuda va atada al fieldset, no a las casillas: describe al grupo. --}}
    @if ($hint)
        <span id="{{ $muniHintId }}" class="muni-cbgroup__hint">{{ $hint }}</span>
    @endif

    <div class="muni-cbgroup__items">
        {{-- Se reutiliza la casilla del paquete en vez de repetir su marcado: el
             blanco de pulsación de 24px, el borde de 3:1, el foco y el modo de
             alto contraste viven en un solo archivo. Nunca se le pasa `error`:
             el del grupo se dice una vez, abajo. --}}
        @forelse ($muniOpciones as $muniOpcion)
            <x-muni::checkbox
                :name="$muniNombreCasilla"
                :value="$muniOpcion['value']"
                :label="$muniOpcion['label']"
                :description="$muniOpcion['description']"
                :checked="$muniOpcion['checked']"
            />
        @empty
            {{ $slot }}
        @endforelse
    </div>

    {{-- La región existe desde el primer render y reserva el alto de una línea:
         con Livewire el error llega en un round-trip sin recargar la página, así
         que un span que nace de la nada no lo lee ningún lector (WCAG 2.2 AA
         4.1.3) y además empuja el contenido de abajo. --}}
    <div role="status" aria-live="polite" class="muni-cbgroup__live">
        @if ($error)
            {{-- El icono acompaña al color: el estado no se comunica solo con rojo. --}}
            <span id="{{ $muniErrorId }}" class="muni-cbgroup__error">
                <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" style="flex-shrink:0;margin-top:1px;"><path d="M8 2.5L15 14H1L8 2.5z" stroke-linejoin="round"/><path d="M8 6.5v3.2M8 11.8v.2" stroke-linecap="round"/></svg>
                <span>{{ $error }}</span>
            </span>
        @endif
    </div>
</fieldset>

@once
    <style>
        /* El fieldset trae borde, relleno y márgenes laterales de fábrica, y un
           min-inline-size automático que lo desborda dentro de una grilla. */
        .muni-cbgroup { min-inline-size:0; margin:0; padding:0; border:0; font-family:var(--muni-font-sans); }
        /* El foco llega por programa desde el resumen de errores: con
           :focus-visible varios navegadores no lo pintarían y el usuario no vería
           a dónde se fue. El outline es el indicador REAL; la box-shadow del
           anillo se computa transparente dentro de Filament (DESIGN §5). */
        .muni-cbgroup:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        .muni-cbgroup__legend { padding:0; margin:0 0 6px; font-size:12.5px; font-weight:600; line-height:1.35; color:var(--muni-text); }
        /* --muni-muted y no --muni-hint: la ayuda del grupo puede caer sobre
           cualquier superficie del sistema anfitrión, y el atenuado tiene las dos
           ramas de tema medidas por encima de 4,5:1 sobre las cuatro. */
        .muni-cbgroup__hint { display:block; margin:0 0 6px; font-size:11.5px; line-height:15px; color:var(--muni-muted); }
        /* Sin gap: cada casilla ya reserva debajo la línea de su propia región
           viva, y ese espacio hace de separación entre filas. */
        .muni-cbgroup__items { display:flex; flex-direction:column; }
        .muni-cbgroup__live { min-height:15px; }
        .muni-cbgroup__error { display:inline-flex; align-items:flex-start; gap:4px; font-size:11.5px; line-height:15px; font-weight:600; color:var(--muni-danger-fg); background:var(--muni-danger-bg); border-radius:var(--muni-radius-sm); padding:2px 6px; }
    </style>
@endonce
