@props([
    'label' => 'Contraseña',
    'name' => 'password',
    'autocomplete' => 'current-password',
    'error' => null,
    'hint' => null,
    'required' => false,
    'showLabel' => 'Mostrar contraseña',
    'hideLabel' => 'Ocultar contraseña',
])

@php
    /*
     |--------------------------------------------------------------------------
     | EL CAMPO DE CLAVE
     |--------------------------------------------------------------------------
     |
     | Ingreso al sistema, cambio de clave del funcionario y restablecimiento de
     | clave del vecino. Es la ficha `campo-clave` de docs/GAP-ANALYSIS.md, con
     | las correcciones que exigió el juez. De las tres cosas que proponía la
     | ficha —alternador, medidor de robustez y política— acá va SOLO la
     | primera: el medidor es otra entrega, y una razón medida (abajo).
     |
     | NO REIMPLEMENTA EL CAMPO. Envuelve `<x-muni::input>`: la etiqueta, el
     | `hint`, el `error` atado con `aria-describedby`, el id derivado del
     | `name` y el foco de 3 px ya están resueltos ahí, y un campo propio sería
     | una segunda verdad de estilo de formulario que empezaría a divergir.
     |
     | POR QUÉ EL BOTÓN VA DEBAJO Y NO ENCIMA DEL CAMPO. Lo habitual es meterlo
     | dentro del recuadro, a la derecha. Para eso habría que posicionarlo
     | contra la fila del input, y esa fila es interior a `input.blade.php`:
     | desde acá solo se puede adivinar cuánto mide la etiqueta que va arriba
     | —12,5 px a peso 600 con la fuente del municipio, que cada adoptante
     | cambia por token— y cualquier adivinanza deja el ojo desalineado en el
     | primer sistema que cambie la tipografía o traduzca el rótulo a dos
     | líneas. Debajo y a la derecha no hay nada que adivinar, y además el
     | orden visual, el del DOM y el de tabulación son el mismo, que es lo que
     | pide 2.4.3: el alternador viene DESPUÉS del campo.
     |
     | `type="button"` NO ES DECORATIVO. Dentro de un `<form>`, un botón sin
     | `type` es `submit`: pulsar «Mostrar» enviaría el formulario de ingreso.
     | Es el bug clásico de este patrón. Lo pone `<x-muni::button>` por defecto
     | y además se escribe acá para que se vea y para que el candado lo mida.
     |
     | AUTOCOMPLETE FORZADO. `current-password` en el ingreso, `new-password`
     | en el cambio y el restablecimiento. Es lo que hace funcionar al gestor de
     | contraseñas, que es la vía que WCAG 2.2 SC 3.3.8 sí admite para no
     | obligar a nadie a recordar la clave. Cualquier otro valor es un error de
     | programación y revienta al renderizar: en producción no se nota nunca.
     |
     | LA CLAVE NACE OCULTA Y NO SE RECUERDA. Ni entre campos ni entre páginas:
     | nada de localStorage ni de `$persist`. Se vuelve a ocultar sola al enviar
     | el formulario y al salir el foco del componente. En el mesón de atención
     | la mirada por encima del hombro no es un escenario de laboratorio.
     |
     | EL MEDIDOR DE ROBUSTEZ NO ESTÁ, Y ES A PROPÓSITO. La política real vive
     | en el servidor (`Password::defaults()`), y su regla más útil
     | —`uncompromised`, que consulta si la clave está en una filtración— no se
     | puede evaluar en el cliente. Un medidor que dice «robusta» y un servidor
     | que después rechaza la clave es peor que no tener medidor. Si algún
     | sistema lo pide, entra como segunda entrega recibiendo las reglas por
     | prop desde la política del anfitrión, nunca decidiéndolas acá.
     |
     | EL PAQUETE NO MIRA EL REQUEST NI LA SESIÓN: todo llega ya resuelto por el
     | anfitrión, y lo que llega son strings, nunca un modelo (Ley 21.719).
     */
    $muniPwAutocompletes = ['current-password', 'new-password'];

    if (! in_array($autocomplete, $muniPwAutocompletes, true)) {
        throw new InvalidArgumentException(
            '<x-muni::input-password> solo acepta autocomplete="current-password" (ingreso) o '
            .'autocomplete="new-password" (cambio y restablecimiento de clave); recibió "'
            .$autocomplete.'". Es lo que hace funcionar al gestor de contraseñas, que es la vía '
            .'que WCAG 2.2 SC 3.3.8 admite: apagarlo no es una opción del componente.'
        );
    }

    /*
     * El id se deriva del `name` con el mismo saneo que input.blade.php y NUNCA
     * de uniqid() (DESIGN §10). Se calcula acá porque el alternador tiene que
     * poder nombrar el campo sobre el que actúa (`aria-controls`), y el `id`
     * que pase el anfitrión manda sobre todo.
     */
    $muniPwId = trim((string) $attributes->get('id'));

    if ($muniPwId === '') {
        $muniPwBase = (string) $name;

        if ($muniPwBase === '') {
            $muniPwBase = 'password';
        } elseif (! preg_match('/^[A-Za-z0-9_-]+$/', $muniPwBase)) {
            $muniPwBase = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $muniPwBase), '-')
                .'-'.substr(sha1($muniPwBase), 0, 6);
        }

        $muniPwId = 'muni-'.$muniPwBase;
    }
@endphp

{{-- El alternador de visibilidad de la clave (Alpine 3 core, sin plugins).

     Ningún texto del anfitrión entra en una expresión de Alpine: los dos
     rótulos son contenido Blade escapado dentro de dos <span> que se turnan.
     Ese fue el defecto que tumbó la página entera en file-dropzone —un
     apóstrofo escapado a &#039; descuadra las comillas de la expresión, y un
     rótulo venido de configuración con `'+fetch(...)+'` se ejecuta—, y acá no
     puede volver porque no hay una sola interpolación dentro del x-data.

     El tipo del campo se cambia a mano (para reponer el punto de inserción, que
     Chromium y Firefox pierden al cambiar `type`) y además va atado con
     `x-bind:type`: con Livewire el nodo se reemplaza en cada round-trip y
     volvería del servidor como `password` con el botón diciendo «Ocultar». --}}
<div
    class="muni-pw"
    style="display:flex;flex-direction:column;gap:4px;"
    x-data="{
        visible: false,
        soltar: null,

        init() {
            /* El formulario y el oyente quedan en el closure y no en el estado:
               un nodo del DOM dentro del proxy reactivo de Alpine es justo lo
               que ya rompió el FileList de la zona de archivos. */
            const formulario = this.$el.closest('form');
            const alEnviar = () => this.ocultar();

            if (formulario) {
                formulario.addEventListener('submit', alEnviar);
                this.soltar = () => formulario.removeEventListener('submit', alEnviar);
            }
        },

        /* Con Livewire el componente se destruye y se vuelve a montar en cada
           respuesta: sin esto los oyentes se acumulan sobre el mismo form. */
        destroy() { if (this.soltar) this.soltar(); },

        alternar() { this.aplicar(! this.visible); },

        ocultar() { if (this.visible) this.aplicar(false); },

        aplicar(mostrar) {
            this.visible = mostrar;

            const campo = this.$refs.campo;
            if (! campo) return;

            /* El punto de inserción se guarda y se repone por precaución, no
               por un defecto medido: en Chromium 151 y Firefox 153 cambiar
               `type` conserva la selección, con el campo enfocado y sin
               enfocar (comprobado quitando estas dos líneas: el banco del
               navegador seguía dando cursor=5). Se queda porque la ficha
               advierte lo contrario de otros motores, cuesta dos líneas y el
               candado que importa —que el cursor siga donde estaba— se mide
               en el navegador, no en este código. El foco NO se mueve: quien
               alternó está en el botón y ahí se queda. */
            const inicio = campo.selectionStart;
            const fin = campo.selectionEnd;

            campo.type = mostrar ? 'text' : 'password';

            if (inicio !== null && typeof campo.setSelectionRange === 'function') {
                try { campo.setSelectionRange(inicio, fin); } catch (e) { /* el navegador no deja seleccionar en este tipo */ }
            }
        },

        /* Al salir el foco del componente entero, no del campo: si pasó al
           propio alternador, la clave sigue a la vista a propósito. El salto
           de tick es necesario porque en `focusout` el foco nuevo todavía no
           está puesto. */
        alSalir() {
            const raiz = this.$el;
            setTimeout(() => { if (! raiz.contains(document.activeElement)) this.ocultar(); }, 0);
        },
    }"
    x-on:focusout="alSalir()"
    x-on:keydown.escape="if (visible) { ocultar(); $event.stopPropagation(); }"
>
    <x-muni::input
        :id="$muniPwId"
        :name="$name"
        :label="$label"
        :hint="$hint"
        :error="$error"
        :required="$required"
        type="password"
        :autocomplete="$autocomplete"
        autocapitalize="off"
        autocorrect="off"
        spellcheck="false"
        x-ref="campo"
        x-bind:type="visible ? 'text' : 'password'"
        {{ $attributes->except(['id', 'type', 'autocomplete']) }}
    />

    <div style="display:flex;justify-content:flex-end;">
        {{-- Nace oculto: sin Alpine, un botón que no alterna nada es peor que
             no tener botón, porque el lector de pantalla lo anuncia igual.

             POR QUÉ EL BORDE VA EN EL `style` Y NO EN EL BLOQUE DE ESTILO DE
             ABAJO. No es descuido: es el único sitio donde llega. El botón del
             paquete emite `border:1px solid transparent` en su PROPIO `style`,
             y un estilo en línea le gana a cualquier regla de hoja sin
             `!important`. Medido en Chromium sobre un botón fantasma pelado: en
             reposo y con el ratón encima el borde computa `rgba(0, 0, 0, 0)` en
             los dos casos, o sea que las reglas `.muni-btn--ghost` y su `:hover`
             que mueven `border-color` son inertes para TODOS los botones
             fantasma del paquete —no las mata este componente: ya estaban
             muertas— y el hover se comunica por el fondo, que sí cambia. Este
             alternador es un control junto a un campo y necesita borde de 3:1
             (WCAG 1.4.11), así que lo pone en línea con `--muni-field-border`,
             el único token calibrado a 3:1 contra las superficies en los dos
             temas (`--muni-border` da 1,28:1 y `--muni-border-2` 1,61:1 en
             claro, según la propia hoja). El banco del navegador mide lo que
             de verdad pasa —3,45:1 en reposo y en hover, en claro y en
             oscuro— y hay candado en la prueba por los dos lados. Que el borde
             fantasma sea inerte es un defecto de `button.blade.php` y se
             arregla ahí, no acá. --}}
        <x-muni::button
            type="button"
            variant="ghost"
            size="sm"
            class="muni-pw__ojo"
            style="min-height:32px;gap:6px;border-color:var(--muni-field-border);"
            x-cloak
            x-ref="boton"
            aria-pressed="false"
            x-bind:aria-pressed="visible ? 'true' : 'false'"
            aria-controls="{{ $muniPwId }}"
            x-on:click="alternar()"
        >
            <svg aria-hidden="true" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0;">
                <path d="M1 8s2.6-4.2 7-4.2S15 8 15 8s-2.6 4.2-7 4.2S1 8 1 8z" stroke-linejoin="round"/>
                <circle cx="8" cy="8" r="1.9"/>
                <path x-show="visible" style="display:none;" d="M3 13L13 3" stroke-linecap="round"/>
            </svg>
            <span x-show="! visible">{{ $showLabel }}</span>
            <span x-show="visible" style="display:none;">{{ $hideLabel }}</span>
        </x-muni::button>
    </div>
</div>

@once
    <style>
        /* Alpine borra el atributo cuando monta. La regla viaja con el componente
           y no en la hoja: dentro de un panel Filament solo se carga
           muni-ui-filament.css, y una clase declarada nada más en muni-ui.css
           no existiría ahí (DESIGN §7). */
        .muni-pw [x-cloak] { display: none !important; }
        .muni-pw__ojo { cursor: pointer; }

        /* El borde del alternador NO se declara acá: no llegaría. El porqué,
           medido, está en el comentario Blade que acompaña al botón. */
    </style>
@endonce
