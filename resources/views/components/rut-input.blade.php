@props([
    'label' => 'RUT',
    'name' => null,
    'value' => null,
    'error' => null,
    'hint' => 'Formato 12.345.678-9. El dígito verificador puede ser la letra K.',
    'required' => false,
])

@php
    /*
     |--------------------------------------------------------------------------
     | EL CAMPO DE RUT
     |--------------------------------------------------------------------------
     |
     | Se escribe todo el día en el mesón: buscar al titular en la recepción
     | ARCOP, dar de alta una patente, iniciar una solicitud de licencia.
     |
     | QUÉ VALOR VIAJA AL BACKEND (la decisión, porque de esto depende lo que
     | recibe el servidor): viaja el RUT **formateado**, «12.345.678-5», en el
     | MISMO input que se ve. Un solo `name`, un solo valor.
     |
     |   · Por qué no un input oculto con el valor normalizado: sin JS ese
     |     oculto conserva lo que puso el servidor y lo que el vecino escribió
     |     no llega nunca. El campo tiene que seguir funcionando con el JS
     |     caído —es un portal público— y el formateo es MEJORA, no requisito.
     |   · Por qué un solo campo: con dos, el `x-model` de Alpine y el
     |     `wire:model` del host pelean por el mismo dato. Es el bug clásico de
     |     este patrón. Con uno, el host pone su `wire:model` en el componente,
     |     llega al único input y Alpine solo reescribe su `value` avisando con
     |     un evento `input`.
     |   · Consecuencia para quien lo consume: al servidor puede llegar
     |     «12.345.678-5» (con JS) o «123456785» (sin JS, tal cual se tecleó).
     |     NORMALIZA SIEMPRE en el servidor antes de comparar o guardar.
     |
     | LA COMPROBACIÓN DEL DÍGITO VERIFICADOR NO REEMPLAZA LA DEL SERVIDOR.
     | Acá se calcula módulo 11 dos veces —en PHP al renderizar y en Alpine al
     | salir del campo— y las dos son una COMODIDAD: adelantan el veredicto para
     | que el funcionario no espere un round-trip. La validación que manda es la
     | del servidor (`RutValido` en el FormRequest); si divergen, gana PHP. Un
     | cliente se edita desde la consola del navegador: nada de lo que se decida
     | acá es una garantía.
     |
     | El veredicto es TEXTO en una región viva, no un borde rojo: el color no
     | puede ser el único portador de información (WCAG 2.2 AA 1.4.1) y un campo
     | que se anuncia inválido sin decir por qué incumple 3.3.1.
     */

    /* Solo dígitos y K, en mayúscula: «12.345.670-k» y «12345670K» son el mismo RUT. */
    $muniRutClean = strtoupper((string) preg_replace('/[^0-9kK]/', '', (string) $value));

    $muniRutBody = strlen($muniRutClean) > 1 ? substr($muniRutClean, 0, -1) : '';
    $muniRutDv = strlen($muniRutClean) > 1 ? substr($muniRutClean, -1) : '';

    /* Puntos de miles y guion, que es como se lee y se dicta en Chile. */
    $muniRutDisplay = $muniRutBody === ''
        ? $muniRutClean
        : preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $muniRutBody).'-'.$muniRutDv;

    /*
     * Módulo 11. Se juzga solo con el cuerpo completo (7 dígitos o más): mientras
     * se está escribiendo no hay veredicto que dar, y un campo que grita antes de
     * tiempo enseña a ignorarlo.
     */
    $muniRutDvError = '';

    if (strlen($muniRutBody) >= 7 && ctype_digit($muniRutBody)) {
        $muniRutSum = 0;
        $muniRutFactor = 2;

        for ($i = strlen($muniRutBody) - 1; $i >= 0; $i--) {
            $muniRutSum += (int) $muniRutBody[$i] * $muniRutFactor;
            $muniRutFactor = $muniRutFactor === 7 ? 2 : $muniRutFactor + 1;
        }

        $muniRutRest = 11 - ($muniRutSum % 11);
        $muniRutExpected = $muniRutRest === 11 ? '0' : ($muniRutRest === 10 ? 'K' : (string) $muniRutRest);

        if ($muniRutExpected !== $muniRutDv) {
            $muniRutDvError = 'El dígito verificador no corresponde. Revisa el RUT.';
        }
    }

    /*
     * El id se deriva del `name` igual que en input.blade.php y NUNCA de
     * uniqid() (DESIGN §10): cambia en cada render, rompe el `for` de la etiqueta
     * y ensucia el diffing de Livewire. Acá se calcula además para poder nombrar
     * la región del veredicto y encadenarla en aria-describedby.
     */
    $muniRutId = trim((string) $attributes->get('id'));

    if ($muniRutId === '') {
        $muniRutBase = (string) $name;

        if ($muniRutBase === '') {
            $muniRutBase = 'rut';
        } elseif (! preg_match('/^[A-Za-z0-9_-]+$/', $muniRutBase)) {
            $muniRutBase = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $muniRutBase), '-')
                .'-'.substr(sha1($muniRutBase), 0, 6);
        }

        $muniRutId = 'muni-rut-'.$muniRutBase;
    }

    $muniRutVerdictId = $muniRutId.'-dv';

    /*
     * El borde del input viene de un `style` en línea, así que solo otro `style`
     * en línea lo pisa: una clase perdería siempre. Se arma acá para no emitir
     * el atributo dos veces en la misma etiqueta.
     */
    $muniRutStyle = trim(($muniRutDvError ? 'border-color:var(--muni-field-border-error);' : '')
        .(string) $attributes->get('style'));

    /*
     * Lo que se le pasa al input base. `aria-invalid` se AGREGA a la bolsa en vez
     * de escribirse como atributo con valor nulo: un `:aria-invalid="null"` en la
     * etiqueta no desaparece, gana sobre el que input calcula desde `error` y el
     * campo deja de anunciarse inválido cuando el error viene del servidor.
     *
     * Y la bolsa se derrama abajo como `{{ $attributes->… }}`, con ese nombre:
     * Blade solo reconoce el derrame si la expresión empieza por `$attributes`.
     * Con una variable propia la etiqueta del componente NO se compila y sale
     * impresa como texto en la página, sin un solo error.
     */
    $muniRutExtra = $muniRutDvError ? ['aria-invalid' => 'true'] : [];

    if ($muniRutStyle !== '') {
        $muniRutExtra['style'] = $muniRutStyle;
    }
@endphp

<div
    class="muni-rut"
    style="display:flex;flex-direction:column;gap:2px;"
    x-data="{
        /* El estado inicial lo pone PHP con la directiva de Blade que escapa a JS.
           El texto que Alpine
           vuelve a emitir sale del data-* de la región —donde ya está escrito para
           quien no tiene JS— y no de una cadena pegada dentro de esta expresión:
           un apóstrofo en el mensaje descuadraría el x-data entero. */
        message: @js($muniRutDvError),
        serverError: @js((bool) $error),
        quiet: false,
        clean(v) { return (v || '').toUpperCase().replace(/[^0-9K]/g, ''); },
        format(v) {
            if (v.length < 2) return v;
            const body = v.slice(0, -1).replace(/\D/g, '');
            return body.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + v.slice(-1);
        },
        expected(body) {
            let sum = 0, factor = 2;
            for (let i = body.length - 1; i >= 0; i--) {
                sum += parseInt(body[i], 10) * factor;
                factor = factor === 7 ? 2 : factor + 1;
            }
            const rest = 11 - (sum % 11);
            return rest === 11 ? '0' : (rest === 10 ? 'K' : String(rest));
        },
        verdict(v) {
            const clean = this.clean(v);
            const body = clean.slice(0, -1);
            if (clean.length < 8 || !/^[0-9]+$/.test(body)) return '';
            return this.expected(body) === clean.slice(-1) ? '' : this.$refs.verdict.dataset.message;
        },
        /* Reescribir el valor en cada tecla mueve el punto de inserción y hace que el
           lector relea el campo entero: solo se reformatea con el cursor al final. */
        onInput(e) {
            if (this.quiet) return;
            this.message = '';
            this.paint();
            const el = e.target;
            if (el.selectionStart !== el.value.length) return;
            this.sync(el, this.format(this.clean(el.value)));
        },
        /* El veredicto se emite al SALIR del campo, no mientras se escribe, y nunca
           impide seguir escribiendo. */
        onBlur(e) {
            const el = e.target;
            this.sync(el, this.format(this.clean(el.value)));
            this.message = this.verdict(el.value);
            this.paint();
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
        /* El evento `input` sintético es lo que hace que `wire:model` vea el valor
           formateado; `quiet` corta la recursión con este mismo manejador. */
        sync(el, v) {
            if (v === el.value) return;
            const atEnd = el.selectionStart === el.value.length;
            el.value = v;
            if (atEnd) el.setSelectionRange(v.length, v.length);
            this.quiet = true;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            this.quiet = false;
        },
        paint() {
            const el = this.$refs.field;
            if (el) el.style.borderColor = (this.message || this.serverError)
                ? 'var(--muni-danger-border)'
                : 'var(--muni-border)';
        },
    }"
>
    <x-muni::input
        :id="$muniRutId"
        :name="$name"
        :label="$label"
        :hint="$hint"
        :error="$error"
        :required="$required"
        type="text"
        inputmode="text"
        autocapitalize="characters"
        autocorrect="off"
        spellcheck="false"
        maxlength="12"
        :class="trim('muni-num '.$attributes->get('class'))"
        :value="$muniRutDisplay !== '' ? $muniRutDisplay : null"
        :aria-describedby="$muniRutVerdictId"
        x-ref="field"
        x-on:input="onInput($event)"
        x-on:blur="onBlur($event)"
        x-bind:aria-invalid="(message || serverError) ? 'true' : null"
        {{ $attributes->except(['id', 'class', 'style', 'value', 'type', 'aria-invalid', 'aria-describedby'])->merge($muniRutExtra) }}
    />

    {{-- El veredicto del dígito verificador. La región existe desde el primer
         render y reserva su alto: cuando el juicio lo emite Alpine al salir del
         campo, un <span> que nace de la nada no lo lee ningún lector de pantalla
         (WCAG 2.2 AA 4.1.3) y además empuja el contenido de abajo.

         `inputmode="text"` arriba es a propósito: con "numeric" el teclado de iOS
         deja al vecino sin poder escribir la K, y uno de cada once RUT termina
         en K. --}}
    <div
        id="{{ $muniRutVerdictId }}"
        x-ref="verdict"
        data-message="El dígito verificador no corresponde. Revisa el RUT."
        role="status"
        aria-live="polite"
        style="display:flex;align-items:flex-start;gap:4px;min-height:15px;font-family:var(--muni-font-sans);font-size:11.5px;line-height:15px;font-weight:600;color:var(--muni-danger-fg);"
    >
        {{-- El icono acompaña al color; el texto es el que de verdad informa. --}}
        <svg aria-hidden="true" x-show="message" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" style="flex-shrink:0;margin-top:1px;@if (! $muniRutDvError) display:none;@endif"><path d="M8 2.5L15 14H1L8 2.5z" stroke-linejoin="round"/><path d="M8 6.5v3.2M8 11.8v.2" stroke-linecap="round"/></svg>
        <span x-text="message">{{ $muniRutDvError }}</span>
    </div>
</div>

@once
    <style>
        /* La firma del sistema: los RUT en mono tabular, igual que en data-table.
           Se define también acá porque un formulario puede no tener ninguna tabla
           en pantalla, y entonces la clase no existiría. */
        .muni-num { font-family: var(--muni-font-mono); font-variant-numeric: tabular-nums; }
    </style>
@endonce
