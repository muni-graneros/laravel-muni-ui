@props([
    'label' => null,
    'name' => null,
    /*
     * NUNCA marcada por defecto, y no es una preferencia de estilo.
     *
     * La casilla que más se usa en el ecosistema es la de aceptación del tratamiento
     * de datos del ingreso público de Atención al Vecino. Bajo la Ley 21.719 el
     * consentimiento tiene que ser una manifestación de voluntad LIBRE, específica e
     * inequívoca: una casilla que llega premarcada —como la traen las plantillas
     * comerciales, Creative Tim entre ellas— no es una manifestación de nadie, y el
     * asiento de licitud que el municipio guarde con ese dato no sirve como prueba.
     * Marcarla es siempre una decisión explícita del anfitrión.
     *
     * El componente NO tiene semántica de consentimiento: es una casilla neutra. El
     * texto legal y la base de licitud los pone el host («tomo conocimiento» donde la
     * base es el ejercicio de una función pública), y la versión vigente del texto la
     * sella el backend desde configuración: un hidden con la versión sería un dato del
     * cliente decidiendo el contenido de un registro legal.
     */
    'checked' => false,
    'description' => null,
    'error' => null,
    'required' => false,
    'value' => '1',
])

@php
    /*
     * Identificador del control. NUNCA uniqid() (DESIGN §8): cambia en cada render,
     * rompe el `for` de la etiqueta y ensucia el diffing de Livewire. Mismo patrón
     * determinista que tabs.blade.php, sortable-table.blade.php e input.blade.php.
     *
     * En un grupo el `name` se repite (`tipos[]`) y lo que distingue a cada casilla es
     * el `value`: por eso el id se deriva de los dos. El `name` viaja intacto al
     * backend; lo que se sanea es solo el id.
     */
    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        $base = trim((string) $name.($name !== null && (string) $value !== '1' ? '-'.$value : ''));

        if ($base === '') {
            $base = 'checkbox-'.substr(sha1(json_encode([$label, $description], JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
        } elseif (! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        $muniId = 'muni-'.$base;
    }

    $muniDescId = $muniId.'-desc';
    $muniErrorId = $muniId.'-error';

    /*
     * `$attributes->merge()` REEMPLAZA, no concatena: si el consumidor trae su propio
     * aria-describedby, uno de los dos desaparece sin aviso. Se lee, se encadena a mano
     * y se saca de la bolsa antes del merge.
     */
    $muniAria = array_filter([
        'aria-describedby' => implode(' ', array_filter([
            trim((string) $attributes->get('aria-describedby')),
            $description ? $muniDescId : '',
            $error ? $muniErrorId : '',
        ])),
        'aria-invalid' => $error ? 'true' : '',
    ]);

    $attributes = $attributes->except(['id', 'aria-describedby', 'value']);
@endphp

{{-- Es un <input type="checkbox"> REAL, no un <div role="checkbox">: así se envía con
     el formulario, responde a Espacio sin una línea de JS, lo rellena el navegador y
     hereda el modo de alto contraste del sistema operativo. Tampoco lleva x-data: el
     estado del DOM ya es la verdad, y el `x-model` sobre un input que además trae
     `checked` es el estado doble que arrastra switch. --}}
<div style="display:flex;flex-direction:column;gap:2px;">
    <label for="{{ $muniId }}" class="muni-checkbox-row">
        {{-- El blanco de pulsación se agranda con un contenedor de 24x24 y NO con un
             pseudo-elemento desbordado: entre dos casillas contiguas de un grupo los
             pseudo-elementos se solapan y se roban los clics. Así se cumple WCAG 2.2 AA
             2.5.8 incluso sin etiqueta al lado. --}}
        <span class="muni-checkbox-hit">
            <input
                type="checkbox"
                id="{{ $muniId }}"
                class="muni-checkbox-input"
                @if ($name) name="{{ $name }}" @endif
                value="{{ $value }}"
                @checked($checked)
                @if ($required) required @endif
                {{ $attributes->merge($muniAria) }}
            >
            {{-- La capa visual: el input real está encima con opacity:0 para conservar
                 el clic y el arrastre nativos. --}}
            <span class="muni-checkbox" aria-hidden="true">
                <svg class="muni-checkbox__mark" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" width="12" height="12">
                    <path d="M3 8.5l3.2 3.2L13 4.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </span>

        @if ($label)
            <span style="font-family:var(--muni-font-sans);font-size:13.5px;line-height:1.45;color:var(--muni-text);">
                {{ $label }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
            </span>
        @endif
    </label>

    {{-- La descripción va FUERA del <label> y atada por aria-describedby. Dentro se
         concatenaría al nombre accesible y el lector la repetiría entera cada vez que la
         casilla recibe el foco: es el defecto que arrastra switch. Acá vive el
         fundamento legal que acompaña a la aceptación. --}}
    @if ($description)
        <span id="{{ $muniDescId }}" class="muni-checkbox-aside" style="font-size:12px;line-height:1.45;color:var(--muni-muted);">{{ $description }}</span>
    @endif

    {{-- La región existe desde el primer render y reserva el alto de una línea: con
         Livewire el error llega en un round-trip sin recargar la página, así que un
         <span> que nace de la nada no lo lee ningún lector (WCAG 2.2 AA 4.1.3) y además
         empuja el contenido de abajo. --}}
    <div role="status" aria-live="polite" class="muni-checkbox-aside" style="min-height:15px;">
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
        .muni-checkbox-row { display:flex; align-items:flex-start; gap:8px; cursor:pointer; }
        /* 24x24 de blanco de pulsación (WCAG 2.2 AA 2.5.8), con el dibujo de 18px
           centrado dentro. El riel de switch mide 38x22 y no llega. */
        .muni-checkbox-hit { position:relative; flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; }
        .muni-checkbox-input { position:absolute; inset:0; width:24px; height:24px; margin:0; opacity:0; cursor:pointer; }
        .muni-checkbox { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border:1px solid var(--muni-muted); border-radius:calc(var(--muni-radius-sm) / 2); background:var(--muni-surface); color:var(--muni-on-accent); transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        /* El borde sale de --muni-muted y NO de --muni-border: medido en el navegador,
           --muni-border-2 sobre la superficie da 1,61:1 en claro y 1,90:1 en oscuro, y una
           casilla SIN marcar no tiene más pista visual que ese borde. WCAG 2.2 AA 1.4.11
           exige 3:1 para el límite de un control de formulario. Con --muni-muted son 6:1. */
        /* La marca se dibuja siempre y se revela con :checked: sin JS y sin dos verdades. */
        .muni-checkbox__mark { opacity:0; transition:opacity var(--muni-dur) var(--muni-ease); }
        .muni-checkbox-input:checked + .muni-checkbox { background:var(--muni-accent); border-color:var(--muni-accent); }
        .muni-checkbox-input:checked + .muni-checkbox .muni-checkbox__mark { opacity:1; }
        /* El <input> real va con opacity:0, así que el contorno nativo no se ve: el
           indicador es este outline sobre la capa visual. Nunca solo box-shadow, que
           dentro de Filament se computa transparente (DESIGN §5). */
        .muni-checkbox-input:focus-visible + .muni-checkbox { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-checkbox-input:disabled + .muni-checkbox { background:var(--muni-surface-2); border-color:var(--muni-border-2); }
        .muni-checkbox-input:disabled { cursor:not-allowed; }
        .muni-checkbox-input[aria-invalid="true"] + .muni-checkbox { border-color:var(--muni-danger-border); }
        /* Alinea la descripción y el error con el texto de la etiqueta: 24 del blanco
           de pulsación más los 8 del gap. */
        .muni-checkbox-aside { display:block; padding-left:32px; }
        /* En modo de alto contraste forzado el fondo pintado desaparece: la marca tiene
           que quedarse visible con el color del sistema. */
        @media (forced-colors: active) {
            .muni-checkbox { border-color:ButtonBorder; }
            .muni-checkbox__mark { color:ButtonText; }
            .muni-checkbox-input:checked + .muni-checkbox .muni-checkbox__mark { color:Highlight; }
        }
    </style>
@endonce
