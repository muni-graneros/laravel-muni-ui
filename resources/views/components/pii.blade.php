@props([
    /*
     * Qué dato es. Va en el nombre accesible del control («Mostrar RUT», no
     * cuatro «Mostrar» iguales en la misma ficha, WCAG 2.2 AA 2.4.6) y viaja en
     * el detalle del evento para que el anfitrión sepa QUÉ campo se abrió.
     *
     * OBLIGATORIA, sin valor por defecto: un dato personal que se revela sin
     * decir cuál es no se puede registrar en ninguna bitácora. Por eso va en
     * `propsObligatorias()` del candado de humo de tests/TodosRendericanTest.php:
     *
     *     'pii' => 'label="RUT"',
     */
    'label',
    /*
     * El dato YA ENMASCARADO por el anfitrión: «12.***.***-9», «+56 9 •••• ••12».
     * No lo calcula el componente, y no es pereza: enmascarar un RUT, un correo
     * y un diagnóstico son tres reglas distintas, y la que corresponde la sabe
     * el sistema que tiene el dato, no el paquete. Sin máscara, puntos.
     */
    'masked' => null,
    /*
     * La clave del campo para el anfitrión: `rut`, `domicilio`, `diagnostico`.
     * Es lo que llega en `detail.campo` del evento y de donde sale el id. Si no
     * viene, se deriva de la etiqueta (NUNCA de uniqid(), DESIGN §10).
     */
    'name' => null,
    /*
     * Nace revelado. Es el estado con el que vuelve la vista después de que el
     * anfitrión atendió el evento, registró el acceso y sirvió el valor: sin
     * esto, el dato recién servido aparecería otra vez tapado y el funcionario
     * pulsaría dos veces (dos accesos en la bitácora por una sola mirada).
     * Sin valor que mostrar no abre nada.
     */
    'revealed' => false,
    'showLabel' => 'Mostrar',
    'hideLabel' => 'Ocultar',
    /*
     * Lo que dice la pantalla entre el clic y la respuesta del anfitrión en el
     * modo diferido. Va dentro de la región viva: quien usa lector de pantalla
     * necesita oír que el dato se pidió, no quedarse en silencio.
     */
    'pendingLabel' => 'Solicitando el dato…',
])

@php
    /*
     |--------------------------------------------------------------------------
     | EL DATO PERSONAL QUE NACE TAPADO
     |--------------------------------------------------------------------------
     |
     | Es lo único que la corrección exigida de la ficha `ficha-persona`
     | (docs/GAP-ANALYSIS.md) deja entrar al paquete. El juez prohibió
     | `<x-muni::ficha-persona>` —cuatro sistemas con modelos de datos distintos
     | forkean el componente en el primero que no calce— y sacó la bitácora del
     | alcance: `composer.json` pide solo `illuminate/support` e
     | `illuminate/view`, sin Livewire, sin Eloquent, sin auth y sin activitylog,
     | así que acá no se puede registrar ningún acceso. Textual:
     |
     |   «Lo máximo que corresponde aquí es un <x-muni::pii> que sirva el campo
     |   enmascarado y, al revelarlo, dispare un evento DOM (muni-pii-revelado)
     |   que el host engancha a su propio registro: el paquete ofrece el gesto,
     |   el host prueba el cumplimiento.»
     |
     | LOS DOS MODOS, Y CUÁL ELEGIR. Esto es lo que hay que leer antes de usarlo:
     |
     | · CON VALOR EN EL SLOT: el dato completo va en el HTML servido, tapado con
     |   `display:none`. Tapa la mirada por encima del hombro en el mesón, que es
     |   el riesgo real y cotidiano. NO tapa a quien abra el código fuente de la
     |   página: el dato ya se sirvió. Vale cuando ese usuario ya está autorizado
     |   a ver el campo y lo que se quiere es no exhibirlo de entrada.
     | · SIN VALOR (modo diferido): el componente sirve solo la máscara, y al
     |   pulsar emite el evento y queda a la espera. El anfitrión decide si ese
     |   usuario puede, lo anota en su bitácora y devuelve la vista con el valor
     |   y `revealed`. Es el único modo que cumple minimización de verdad
     |   (Ley 21.719, art. 3 letra e): lo que no se sirvió no está.
     |
     | El paquete no mira `request()`, ni `Auth`, ni `Gate`: quién puede ver qué
     | lo resuelve el anfitrión y acá llegan strings ya redactados, nunca un
     | modelo ni un registro completo.
     */
    $muniPiiEtiqueta = trim((string) $label);
    $muniPiiCampo = trim((string) ($name ?? ''));

    if ($muniPiiCampo === '') {
        $muniPiiCampo = $muniPiiEtiqueta;
    }

    /* El id sale del `name` —o de la etiqueta— con el mismo saneo que
       input.blade.php e input-password.blade.php, y el que pase el anfitrión
       manda sobre todo. Nunca uniqid(): cambiaría en cada render, rompería el
       `aria-controls` del control y ensuciaría el diffing de Livewire. */
    $muniPiiId = trim((string) $attributes->get('id'));

    if ($muniPiiId === '') {
        $muniPiiBase = $muniPiiCampo;

        if ($muniPiiBase === '') {
            $muniPiiBase = 'dato';
        } elseif (! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $muniPiiBase)) {
            $muniPiiBase = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $muniPiiBase), '-')
                .'-'.substr(sha1($muniPiiBase), 0, 6);
        }

        $muniPiiId = 'muni-pii-'.$muniPiiBase;
    }

    $muniPiiValorId = $muniPiiId.'-valor';

    /* El valor va por SLOT y no por prop: tiene que admitir un enlace `tel:`,
       un badge o un botón de copiar el RUT. Una prop `value` se queda corta en
       el primer caso real y la ficha vuelve al <span> escrito a mano. */
    $muniPiiTieneValor = trim((string) $slot) !== '';

    /* `revealed` sin valor no abre nada: no habría qué mostrar y el control
       quedaría anunciándose pulsado sobre un hueco. */
    $muniPiiRevelado = filter_var($revealed, FILTER_VALIDATE_BOOLEAN) && $muniPiiTieneValor;

    $muniPiiMascara = trim((string) ($masked ?? ''));
@endphp

{{-- LA RAÍZ. Es un <span> a propósito: el sitio natural de este componente es
     el <dd> de <x-muni::description-list> o una celda de tabla, y un <div> ahí
     rompe la línea del par.

     El detalle del evento viaja en `data-*` y NO interpolado en la expresión de
     Alpine. Ese fue el defecto que tumbó la página entera en file-dropzone: un
     apóstrofo escapado a &#039; descuadra las comillas de la expresión y Alpine
     muere con toda la página; peor, un rótulo venido de la configuración del
     anfitrión con `'+fetch(…)+'` se ejecuta. Acá no hay una sola interpolación
     de texto del anfitrión dentro del x-data: los únicos valores que Blade
     escribe ahí son dos literales booleanos que calcula este archivo. --}}
<span
    data-muni-pii-campo="{{ $muniPiiCampo }}"
    data-muni-pii-etiqueta="{{ $muniPiiEtiqueta }}"
    {{-- `class` va DENTRO del merge y no escrito antes del derrame: merge()
         CONCATENA la clase del consumidor con la del componente, mientras que
         dos atributos `class` en el mismo nodo dejan ganar al primero y el
         `.muni-num` que el anfitrión pone para que el RUT salga en mono se
         perdería en silencio (DESIGN §8). --}}
    {{ $attributes->merge(['id' => $muniPiiId, 'class' => 'muni-pii']) }}
    x-data="{
        revelado: {{ $muniPiiRevelado ? 'true' : 'false' }},
        pendiente: false,
        tieneValor: {{ $muniPiiTieneValor ? 'true' : 'false' }},

        alternar() { (this.revelado || this.pendiente) ? this.ocultar() : this.revelar(); },

        revelar() {
            /* La raíz se busca desde el nodo, NO con this.$el a secas: dentro
               de un método invocado desde el x-on de un hijo, $el es ESE hijo
               —el botón—, así que `dataset` e `id` venían vacíos y el detalle
               del evento llegaba al anfitrión sin decir qué campo se abrió. El
               HTML pasaba todas las pruebas de Blade igual; se vio midiendo el
               evento en el navegador. */
            const raiz = (this.$el && this.$el.closest('[data-muni-pii-campo]')) || this.$root;

            /* El estado se cambia ANTES de emitir: un oyente del anfitrión que
               mire el DOM al recibir el evento tiene que ver lo que el
               funcionario ya está viendo. */
            if (this.tieneValor) { this.revelado = true; } else { this.pendiente = true; }

            raiz.dispatchEvent(new CustomEvent('muni-pii-revelado', {
                bubbles: true,
                detail: {
                    campo: raiz.dataset.muniPiiCampo || '',
                    etiqueta: raiz.dataset.muniPiiEtiqueta || '',
                    id: raiz.id || '',
                    diferido: ! this.tieneValor,
                },
            }));
        },

        ocultar() { this.revelado = false; this.pendiente = false; },
    }"
    x-on:keydown.escape="if (revelado || pendiente) { ocultar(); $event.stopPropagation(); }"
>
    {{-- LA REGIÓN VIVA. Educada, no asertiva: el cambio lo pidió la persona con
         un clic, así que no hay por qué interrumpirla. Sin esto, quien usa
         lector de pantalla pulsa «Mostrar» y no se entera de nada, porque el
         valor aparece lejos del foco, que se queda en el botón. --}}
    <span
        class="muni-pii__valor"
        id="{{ $muniPiiValorId }}"
        aria-live="polite"
        x-bind:aria-busy="pendiente ? 'true' : 'false'"
    >
        <span class="muni-pii__mascara" x-show="! revelado && ! pendiente" @if ($muniPiiRevelado) style="display:none;" @endif>@if ($muniPiiMascara !== ''){{ $muniPiiMascara }}@else<span class="muni-pii__puntos" aria-hidden="true">••••••••</span><span class="muni-sr">Dato oculto</span>@endif</span>

        {{-- El estado del modo diferido: entre el clic y la respuesta del
             anfitrión la pantalla dice qué está pasando, y como está dentro de
             la región viva, también lo dice el lector. --}}
        <span class="muni-pii__pendiente" x-show="pendiente" style="display:none;">{{ $pendingLabel }}</span>

        @if ($muniPiiTieneValor)
            {{-- El valor completo. Nace con `display:none` EN LÍNEA y no solo con
                 x-show: el estilo en línea vale desde el primer pixel pintado,
                 antes de que Alpine monte y también si no monta nunca. Sin eso,
                 cada ficha del mesón parpadearía con el RUT completo a la vista
                 en cada render. El slot llega crudo al DOM —es Htmlable, como en
                 description-item—, así que escapar el valor es del anfitrión al
                 interpolarlo con {{ }} en SU vista; acá dentro no hay un solo
                 {!! !!} y hay una prueba que lo fija. --}}
            <span class="muni-pii__real" x-show="revelado" @if (! $muniPiiRevelado) style="display:none;" @endif>{{ $slot }}</span>
        @endif
    </span>

    {{-- EL CONTROL. `type="button"` no es decorativo: dentro de un <form> un
         botón sin tipo es submit, y revelar el RUT enviaría la ficha. Nace con
         x-cloak porque sin JavaScript no puede revelar nada, y un control que
         no hace nada es peor que ninguno: el lector de pantalla lo anuncia
         igual. El outline de 3 px lo pone <x-muni::button>; acá solo se fija la
         altura mínima, porque el tamaño `sm` queda al filo de los 24 px que
         pide 2.5.8, y el borde con --muni-field-border, que es el único token
         calibrado a 3:1 contra las superficies en los dos temas (1.4.11). --}}
    <x-muni::button
        type="button"
        variant="ghost"
        size="sm"
        class="muni-pii__boton"
        style="min-height:28px;gap:6px;border-color:var(--muni-field-border);"
        x-cloak
        aria-pressed="{{ $muniPiiRevelado ? 'true' : 'false' }}"
        x-bind:aria-pressed="revelado ? 'true' : 'false'"
        aria-controls="{{ $muniPiiValorId }}"
        x-on:click="alternar()"
    >
        <svg aria-hidden="true" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0;">
            <path d="M1 8s2.6-4.2 7-4.2S15 8 15 8s-2.6 4.2-7 4.2S1 8 1 8z" stroke-linejoin="round"/>
            <circle cx="8" cy="8" r="1.9"/>
            <path x-show="revelado" @if (! $muniPiiRevelado) style="display:none;" @endif d="M3 13L13 3" stroke-linecap="round"/>
        </svg>
        <span x-show="! revelado" @if ($muniPiiRevelado) style="display:none;" @endif>{{ $showLabel }} <span class="muni-sr">{{ $label }}</span></span>
        <span x-show="revelado" @if (! $muniPiiRevelado) style="display:none;" @endif>{{ $hideLabel }} <span class="muni-sr">{{ $label }}</span></span>
    </x-muni::button>
</span>

@once
    <style>
        /* Todo lo que este componente necesita para verse bien viaja con él:
           dentro de un panel Filament solo se carga muni-ui-filament.css, y una
           clase declarada nada más en muni-ui.css no existiría ahí, sin un solo
           error en consola (DESIGN §7). */
        .muni-pii { display:inline-flex; align-items:baseline; flex-wrap:wrap; gap:8px; font-family:var(--muni-font-sans); }
        .muni-pii [x-cloak] { display:none !important; }
        .muni-pii__valor { min-width:0; overflow-wrap:break-word; color:var(--muni-text); }
        .muni-pii__puntos { letter-spacing:.14em; }
        .muni-pii__pendiente { color:var(--muni-muted); font-size:12.5px; }
        .muni-pii__boton { cursor:pointer; }

        /* La mono y el texto solo para el lector viajan acá por el mismo motivo
           que en description-list: hoy `.muni-num` vive dentro del bloque de una
           sola emisión de data-table, y una ficha abierta en un drawer sin tabla
           en pantalla no lo emite nunca: el RUT saldría en sans proporcional
           justo donde se compara de un vistazo. Las declaraciones son idénticas
           a las de allá, así que el orden de emisión da igual. */
        .muni-num { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; }
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }

        /* En papel el control es tinta y ruido: no hay nada que pulsar en una
           hoja. Lo que esté en pantalla al imprimir es lo que sale, que es la
           decisión del funcionario que emite el acta y no del paquete.
           Acotado al control: las reglas base del documento son de los dos CSS
           del paquete, no de un componente. */
        @media print {
            .muni-pii__boton { display:none !important; }
            .muni-pii { gap:0; }
        }
    </style>
@endonce
