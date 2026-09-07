@props([
    'label' => 'Fecha',
    'name' => null,
    'value' => null,
    'min' => null,
    'max' => null,
    'error' => null,
    'hint' => null,
    'required' => false,

    /* Modo rango: los dos extremos de un periodo, coherentes entre sí. */
    'range' => false,
    'legend' => 'Periodo',
    'fromName' => null,
    'toName' => null,
    'fromLabel' => 'Desde',
    'toLabel' => 'Hasta',
    'fromValue' => null,
    'toValue' => null,
    'fromError' => null,
    'toError' => null,

    /*
     * Un solo attribute bag no puede repartir dos `wire:model` entre dos inputs,
     * así que en modo rango el binding va por props explícitas. El modificador
     * (`live`, `blur`, `debounce.500ms`) se pasa aparte y sirve para los dos.
     */
    'fromModel' => null,
    'toModel' => null,
    'modelModifier' => null,
])

@php
    /*
     |--------------------------------------------------------------------------
     | EL CAMPO DE FECHA
     |--------------------------------------------------------------------------
     |
     | Vencimiento de una licencia clase B, fecha de la fiscalización, desde–hasta
     | de un reporte.
     |
     | SE APOYA EN `<input type="date">` NATIVO, a propósito. El navegador ya trae
     | el calendario accesible, el teclado por segmentos (Tab entre día, mes y
     | año; flechas para incrementar cada uno) y el idioma del sistema, y funciona
     | en la tablet de terreno sin una línea de JS. Acá NO se dibuja una rejilla
     | propia: `<x-muni::calendar>` ya lo intentó y está documentado como roto por
     | teclado. No se repite ese error, y por eso tampoco se interceptan teclas.
     |
     | QUÉ VALOR VIAJA AL BACKEND (la decisión): el ISO nativo, `aaaa-mm-dd`,
     | intacto. Es el único formato que `<input type="date">` emite y el único que
     | `Carbon::createFromFormat('Y-m-d', …)` lee sin ambigüedad. El componente no
     | lo reescribe ni lo duplica en un campo oculto.
     |
     | Y ACÁ ESTÁ LA DIFERENCIA QUE HABÍA QUE RESOLVER: el funcionario lee y dicta
     | «31-12-2026», pero lo que se ve en pantalla NO lo decide el sitio, lo decide
     | la configuración regional del equipo —un puesto del municipio en inglés
     | muestra 12/31/2026—. Se resuelve por partida doble:
     |
     |   1. La ayuda dice siempre el formato esperado, con un ejemplo real.
     |   2. Debajo del campo se imprime la fecha elegida en dd-mm-aaaa, calculada
     |      en PHP al renderizar y en Alpine al cambiarla. Es un eco de LECTURA
     |      (`aria-hidden`): el control nativo ya le anuncia la fecha al lector de
     |      pantalla en el idioma del usuario, y repetirla en una región viva
     |      hablaría en cada tecla del año.
     |
     | EL COMPONENTE NO VALIDA NADA EN EL SERVIDOR. `min`/`max` nativos no impiden
     | teclear una fecha fuera de rango en todos los navegadores, y el mensaje de
     | orden del rango es una comodidad del cliente: la regla `after_or_equal` en
     | el FormRequest sigue siendo obligatoria, igual que blindar el `Carbon::parse`
     | del reporte contra una cadena mal formada.
     */

    /* dd-mm-aaaa a partir del ISO nativo. Sin Carbon: el paquete no lo requiere. */
    $muniDateHuman = function (?string $iso): string {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim((string) $iso), $m)
            ? $m[3].'-'.$m[2].'-'.$m[1]
            : '';
    };

    /*
     * El id se deriva del `name` igual que en input.blade.php y NUNCA de uniqid()
     * (DESIGN §8): cambia en cada render, rompe el `for` de la etiqueta y ensucia
     * el diffing de Livewire.
     */
    $muniDateId = function (?string $name, string $fallback): string {
        $base = (string) $name;

        if ($base === '') {
            $base = $fallback;
        } elseif (! preg_match('/^[A-Za-z0-9_-]+$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        return 'muni-date-'.$base;
    };

    $muniDateHint = $hint ?? 'Formato dd-mm-aaaa. Por ejemplo, 31-12-2026.';

    $muniDateFromName = $fromName ?? (($name ? $name.'_' : '').'from');
    $muniDateToName = $toName ?? (($name ? $name.'_' : '').'to');

    $muniDateSingleId = trim((string) $attributes->get('id')) ?: $muniDateId($name, 'date');
    $muniDateFromId = $muniDateId($muniDateFromName, 'from');
    $muniDateToId = $muniDateId($muniDateToName, 'to');

    $muniDateEchoId = $muniDateSingleId.'-echo';
    $muniDateOrderId = $muniDateToId.'-order';

    /*
     * El orden se juzga con los ISO tal cual: `aaaa-mm-dd` ordena igual como
     * cadena que como fecha, así que no hace falta parsear nada.
     */
    $muniDateOrderError = '';

    if ($range && $muniDateHuman($fromValue) !== '' && $muniDateHuman($toValue) !== '' && $toValue < $fromValue) {
        $muniDateOrderError = 'El «hasta» es anterior al «desde»: revisa el periodo.';
    }

    /*
     * Los atributos que dependen de una condición se arman acá y se derraman con
     * `{{ $attributes->only([])->merge(…) }}`: un `:aria-invalid="null"` escrito
     * en la etiqueta NO desaparece, gana sobre el que input calcula desde `error`
     * y el campo dejaría de anunciarse inválido cuando el error viene del
     * servidor. El derrame tiene que empezar por `$attributes` —con ese nombre—
     * o Blade no compila la etiqueta y la imprime como texto en la página.
     */
    $muniDateModel = $modelModifier ? 'wire:model.'.$modelModifier : 'wire:model';

    /*
     * Los extremos del rango se estrechan entre sí en el propio control nativo.
     * El respaldo va como literal de JS armado en PHP y no con `@js(...)`: dentro
     * de la etiqueta de un componente Blade NO compila las directivas y la
     * expresión de Alpine llegaría al navegador con el `@js` escrito tal cual.
     * Solo se acepta un ISO completo, así que no hay nada que escapar.
     */
    $muniDateJs = fn (?string $iso) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $iso) ? "'".$iso."'" : 'null';

    $muniDateFromExtra = array_filter([$muniDateModel => $fromModel]);
    $muniDateToExtra = array_filter([
        $muniDateModel => $toModel,
        'aria-invalid' => $muniDateOrderError ? 'true' : null,
    ]);
@endphp

@if ($range)
    {{-- Los dos extremos son UN dato: van agrupados y rotulados como tal. Sin el
         fieldset, un lector de pantalla anuncia dos campos sueltos llamados
         «Desde» y «Hasta» sin decir nunca de qué periodo se trata. --}}
    <fieldset
        x-data="{
            from: @js((string) $fromValue),
            to: @js((string) $toValue),
            message: @js($muniDateOrderError),
            /* Se juzga en `change`, que el control nativo dispara con la fecha
               completa: así el mensaje no salta a mitad de escribir el año.
               El texto que Alpine vuelve a emitir sale del data-* de la región
               —donde ya está escrito para quien no tiene JS— y no de una cadena
               pegada acá: un apóstrofo descuadraría el x-data entero. */
            check() {
                this.message = (this.from && this.to && this.to < this.from)
                    ? this.$refs.order.dataset.message
                    : '';
            },
        }"
        {{ $attributes->except(['id', 'aria-describedby'])->merge([
            'class' => 'muni-date-range',
            'style' => 'margin:0;padding:0;border:0;min-width:0;',
        ]) }}
    >
        <legend style="padding:0;margin-bottom:6px;font-family:var(--muni-font-sans);font-size:12.5px;font-weight:700;color:var(--muni-text);">{{ $legend }}</legend>

        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <div style="flex:1 1 170px;min-width:0;">
                {{-- Nada de `x-model` acá: sobre el mismo input pelearía con el
                     `wire:model` del host, que es el bug clásico de este patrón.
                     El estado de Alpine se alimenta del evento `change`. --}}
                <x-muni::date-input
                    :id="$muniDateFromId"
                    :name="$muniDateFromName"
                    :label="$fromLabel"
                    :value="$fromValue"
                    :min="$min"
                    :max="$max"
                    :error="$fromError"
                    :hint="$muniDateHint"
                    :required="$required"
                    x-on:change="from = $event.target.value; check()"
                    x-bind:max="to || {{ $muniDateJs($max) }}"
                    {{ $attributes->only([])->merge($muniDateFromExtra) }}
                />
            </div>

            <div style="flex:1 1 170px;min-width:0;">
                <x-muni::date-input
                    :id="$muniDateToId"
                    :name="$muniDateToName"
                    :label="$toLabel"
                    :value="$toValue"
                    :min="$min"
                    :max="$max"
                    :error="$toError"
                    :hint="$muniDateHint"
                    :required="$required"
                    :aria-describedby="$muniDateOrderId"
                    x-on:change="to = $event.target.value; check()"
                    x-bind:min="from || {{ $muniDateJs($min) }}"
                    {{ $attributes->only([])->merge($muniDateToExtra) }}
                />
            </div>
        </div>

        {{-- El mensaje de orden existe desde el primer render y reserva su alto:
             cuando lo emite Alpine, un <span> que nace de la nada no lo lee ningún
             lector de pantalla (WCAG 2.2 AA 4.1.3) y empuja el contenido de abajo. --}}
        <div
            id="{{ $muniDateOrderId }}"
            x-ref="order"
            data-message="El «hasta» es anterior al «desde»: revisa el periodo."
            role="status"
            aria-live="polite"
            style="display:flex;align-items:flex-start;gap:4px;min-height:15px;margin-top:4px;font-family:var(--muni-font-sans);font-size:11.5px;line-height:15px;font-weight:600;color:var(--muni-danger-fg);"
        >
            {{-- El icono acompaña al color; el texto es el que de verdad informa. --}}
            <svg aria-hidden="true" x-show="message" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" style="flex-shrink:0;margin-top:1px;@if (! $muniDateOrderError) display:none;@endif"><path d="M8 2.5L15 14H1L8 2.5z" stroke-linejoin="round"/><path d="M8 6.5v3.2M8 11.8v.2" stroke-linecap="round"/></svg>
            <span x-text="message">{{ $muniDateOrderError }}</span>
        </div>
    </fieldset>
@else
    <div
        class="muni-date"
        style="display:flex;flex-direction:column;gap:2px;"
        x-data="{
            human: @js($muniDateHuman($value)),
            /* El eco se arma con las partes del ISO, no con toLocaleDateString: esa
               función devolvería otra vez el formato del equipo, que es el problema. */
            echo(v) {
                const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec((v || '').trim());
                this.human = m ? m[3] + '-' + m[2] + '-' + m[1] : '';
            },
        }"
    >
        <x-muni::input
            :id="$muniDateSingleId"
            :name="$name"
            :label="$label"
            :hint="$muniDateHint"
            :error="$error"
            :required="$required"
            type="date"
            :value="$value"
            :min="$min"
            :max="$max"
            x-on:change="echo($event.target.value)"
            {{ $attributes->except(['id']) }}
        />

        {{-- El eco de lectura: lo que el funcionario dicta por teléfono, siempre en
             dd-mm-aaaa aunque el equipo esté en inglés. Va `aria-hidden` porque el
             control nativo YA le anuncia la fecha al lector de pantalla en el idioma
             del usuario; repetirlo sería hablar dos veces. Ocupa su alto desde el
             primer render para que no salte nada al elegir la fecha. --}}
        <p
            id="{{ $muniDateEchoId }}"
            aria-hidden="true"
            class="muni-num"
            style="margin:0;min-height:15px;font-size:11.5px;line-height:15px;color:var(--muni-muted);"
        ><span x-text="human">{{ $muniDateHuman($value) }}</span></p>
    </div>
@endif

@once
    <style>
        /* La firma del sistema: cifras y fechas en mono tabular, igual que en
           data-table. Se define también acá porque un formulario puede no tener
           ninguna tabla en pantalla, y entonces la clase no existiría. */
        .muni-num { font-family: var(--muni-font-mono); font-variant-numeric: tabular-nums; }
    </style>
@endonce
