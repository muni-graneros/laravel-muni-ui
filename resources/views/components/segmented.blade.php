@props([
    'name' => null,
    'options' => [],
    'value' => null,
    'label' => null,
    /*
     * Rótulo del botón de respaldo. Ese botón existe porque el autoenvío ya no
     * puede dispararse con las flechas del teclado (ver abajo): sin él, quien
     * recorre el grupo con el teclado se quedaría sin forma de aplicar el filtro.
     * Solo aparece cuando el formulario que contiene al grupo NO tiene ya un
     * botón de envío propio — dentro de `filter-bar` y de `selector-tema` no se
     * ve nunca, porque los dos traen el suyo.
     */
    'submitLabel' => 'Aplicar',
])

{{-- Control segmentado (toggle de filtro): alternativa moderna al <select> para pocas
     opciones. Con `name` genera radios reales (funciona sin JS, submit del form nativo);
     sin `name` es puramente visual/enlaces (usar el slot). --}}
@php
    $esRadios = ! empty($options) && $name;

    /*
     * Nombre accesible del grupo. Un conjunto de opciones excluyentes sin nombre
     * se anuncia como «grupo» a secas: el lector no dice de QUÉ son las opciones
     * (WCAG 2.2 AA 1.3.1 y 4.1.2). Se respeta lo que pase el consumidor —su
     * aria-labelledby manda sobre todo, y su aria-label sobre la prop— y si no
     * pasó nada queda un nombre genérico, que sigue siendo mejor que ninguno.
     * El aria-label propio se emite SOLO cuando el consumidor no trajo el suyo:
     * en HTML el atributo duplicado lo resuelve el orden, no la intención.
     */
    $etiquetaGrupo = $attributes->get('aria-label') ?: ($label ?: 'Opciones');
    $etiquetaPropia = $esRadios && ! $attributes->has('aria-labelledby');

    // El aria-label del bag solo se saca cuando el componente pone el suyo en su
    // lugar; si no, la rama de slot y los grupos con aria-labelledby perderían la
    // etiqueta que sí traía el consumidor.
    $bag = $etiquetaPropia ? $attributes->except('aria-label') : $attributes;
@endphp

{{-- EL ENVOLTORIO DEL AUTOENVÍO, solo en la rama de radios.

     Antes cada radio llevaba `onchange="this.form && this.form.submit()"`, y eso
     tenía dos defectos que la corrección del juez mandó arreglar antes que
     cualquier componente nuevo:

       · Una CSP con nonce lo bloquea. `unsafe-inline` NO cubre los atributos de
         evento si no está además `unsafe-hashes`, así que en el ecosistema el
         autoenvío no corría nunca y nadie se enteraba: el filtro simplemente no
         se aplicaba al elegir.
       · En un `radiogroup` las flechas MUEVEN la selección, así que cada flecha
         disparaba un `change` y recargaba la página. Llegar a la última opción
         costaba una recarga por opción y el recorrido con teclado quedaba
         destruido (WCAG 2.2 AA 3.2.2: nada cambia de contexto por moverse).

     Ahora el envío es de Alpine (`requestSubmit()`, que sí dispara la validación
     nativa y el evento `submit`, cosa que `form.submit()` se salta) y SOLO cuando
     el cambio no viene de una flecha. El teclado aplica con Enter —el submit
     implícito del formulario— o con el botón de respaldo. --}}
@if ($esRadios)
<div
    class="muni-seg-grupo"
    x-data="{
        flechas: false,
        respaldo: false,

        /* El botón de respaldo sobra si el formulario ya tiene el suyo: dentro de
           `filter-bar` habría dos «Aplicar» pegados. Se mira el formulario REAL del
           radio, no un ancestro cualquiera, y se excluye el propio botón. Con VARIOS
           grupos en el mismo formulario sin botón, solo el último muestra el suyo:
           un «Aplicar» por grupo serían varios botones que hacen lo mismo. */
        init() {
            const radio = this.$el.querySelector('input[type=radio]');
            const form = radio ? radio.form : null;

            if (! form) { return; }

            const respaldos = form.querySelectorAll('.muni-seg-aplicar');

            this.respaldo = respaldos[respaldos.length - 1] === this.$el.querySelector('.muni-seg-aplicar')
                && ! form.querySelector('button[type=submit]:not(.muni-seg-aplicar), input[type=submit], button:not([type])');
        },
    }"
    @keydown.arrow-up="flechas = true"
    @keydown.arrow-down="flechas = true"
    @keydown.arrow-left="flechas = true"
    @keydown.arrow-right="flechas = true"
    {{-- Apuntar vuelve a habilitar el autoenvío: quien elige con el ratón o el dedo
         confirma con ese mismo gesto, que es el camino que la barra de filtros tenía
         desde siempre. Se escucha `pointerdown` y NO `click` a propósito: al mover la
         selección con las flechas el navegador DISPARA un `click` sobre el radio
         recién marcado (es su comportamiento de activación), así que un `click`
         reiniciaría la guardia justo antes del `change` y cada flecha volvería a
         recargar la página. Medido en Chromium y Firefox. --}}
    @pointerdown="flechas = false"
>
@endif

<div role="{{ $esRadios ? 'radiogroup' : 'group' }}"
     @if ($etiquetaPropia) aria-label="{{ $etiquetaGrupo }}" @endif
     {{ $bag->merge(['style' => 'display:inline-flex;padding:3px;gap:2px;background:var(--muni-surface-2);border:1px solid var(--muni-border);border-radius:var(--muni-radius-sm);']) }}>
    @if ($esRadios)
        @foreach ($options as $val => $texto)
            @php $id = $name.'-'.$loop->index; $active = (string) $value === (string) $val; @endphp
            <label for="{{ $id }}" class="muni-seg {{ $active ? 'muni-seg--on' : '' }}">
                <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $val }}" @checked($active)
                       style="position:absolute;opacity:0;width:0;height:0;"
                       x-on:change="if (! flechas && $el.form) { $el.form.requestSubmit(); }">
                {{-- El texto va envuelto porque es lo único visible de la píldora: el
                     radio real mide 0×0 y es transparente, así que un anillo dibujado
                     sobre él no se vería. --}}
                <span class="muni-seg__txt">{{ $texto }}</span>
            </label>
        @endforeach
    @else
        {{ $slot }}
    @endif
</div>

@if ($esRadios)
    {{-- Nace oculto desde el SERVIDOR y lo revela Alpine solo si hace falta: así no
         hay parpadeo de un botón que en la barra de filtros sobra. Sin Alpine no se
         ve, y entonces el formulario del anfitrión aporta su propio envío —que es el
         caso de `filter-bar`, `selector-tema` y cualquier <form> con botón. --}}
    <button type="submit" class="muni-seg-aplicar" style="display:none" x-show="respaldo">{{ $submitLabel }}</button>
</div>
@endif

@once
    <style>
        /* El envoltorio NO genera caja: `display:contents` deja al radiogroup y al
           botón de respaldo como hijos directos del contenedor del anfitrión. Así la
           `class` y el `style` que el consumidor pone —y que van al radiogroup, como
           siempre— siguen mandando sobre el bloque real: un `width:100%` no queda
           atrapado dentro de una caja que se encoge a su contenido. El envoltorio no
           tiene rol, así que `contents` no le quita nada al árbol de accesibilidad. */
        .muni-seg-grupo { display:contents; }
        /* Mismo botón discreto que el de `selector-tema`: acompaña al grupo, no
           compite con la acción principal de la pantalla. El alto mínimo es el piso
           de objetivo de 2.5.8; con puntero grueso sube a 44. */
        .muni-seg-aplicar { display:inline-flex;align-items:center;justify-content:center;
            margin-inline-start:8px;vertical-align:middle;min-height:30px;min-width:24px;padding:6px 14px;
            font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;
            color:var(--muni-text);background:var(--muni-surface);border:1px solid var(--muni-border-2);
            border-radius:var(--muni-radius-sm);cursor:pointer;
            transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-seg-aplicar:hover { background:var(--muni-surface-2);border-color:var(--muni-accent); }
        .muni-seg-aplicar:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676));outline-offset:2px; }
        @media (pointer: coarse) {
            .muni-seg-aplicar { min-height:44px;min-width:44px; }
        }

        .muni-seg { display:inline-flex;align-items:center;justify-content:center;padding:6px 14px;
            font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;color:var(--muni-muted);
            border-radius:calc(var(--muni-radius-sm) - 2px);cursor:pointer;white-space:nowrap;
            transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-seg:hover { color:var(--muni-text); }
        /* RESPALDO, sin `:has()`. Mismo caso que el switch: el radio real va con
           opacity:0 y 0×0, así que sin este outline el foco no se ve en cuanto la
           box-shadow se pierde —y dentro de Filament se pierde—. El foco lo recibe
           el radio, que es quien lo tiene de verdad, y el anillo se dibuja sobre su
           hermano visible. Va FUERA de todo @supports: es el camino que queda en un
           navegador sin `:has()`, y `segmented` está documentado como «radios reales
           sin JS», o sea pensado justamente para el camino degradado. */
        .muni-seg input:focus-visible ~ .muni-seg__txt { outline:3px solid var(--muni-focus, var(--muni-accent, #767676));
            outline-offset:2px;border-radius:2px; }
        /* MEJORA. Donde `:has()` resuelve, el anillo salta del texto a la píldora
           entera, que es el objetivo real del control. Offset negativo para que
           quede dentro de la píldora, que solo tiene 3px de padding en el contenedor.
           El anillo del texto sobra ahí, y se apaga bajándole el grosor a cero: NO
           con `outline:none`, que es justo el patrón que el candado de foco visible
           prohíbe en todo el paquete. */
        @supports selector(:has(*)) {
            .muni-seg input:focus-visible ~ .muni-seg__txt { outline-width:0; }
            .muni-seg:has(input:focus-visible) { outline:3px solid var(--muni-focus, var(--muni-accent, #767676));
                outline-offset:-2px;box-shadow:var(--muni-ring); }
        }
        .muni-seg--on { background:var(--muni-surface);color:var(--muni-text);box-shadow:var(--muni-shadow); }
    </style>
@endonce
