@props([
    'name' => null,
    'options' => [],
    'value' => null,
    'label' => null,
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

<div role="{{ $esRadios ? 'radiogroup' : 'group' }}"
     @if ($etiquetaPropia) aria-label="{{ $etiquetaGrupo }}" @endif
     {{ $bag->merge(['style' => 'display:inline-flex;padding:3px;gap:2px;background:var(--muni-surface-2);border:1px solid var(--muni-border);border-radius:var(--muni-radius-sm);']) }}>
    @if ($esRadios)
        @foreach ($options as $val => $texto)
            @php $id = $name.'-'.$loop->index; $active = (string) $value === (string) $val; @endphp
            <label for="{{ $id }}" class="muni-seg {{ $active ? 'muni-seg--on' : '' }}">
                <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $val }}" @checked($active)
                       style="position:absolute;opacity:0;width:0;height:0;" onchange="this.form && this.form.submit()">
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

@once
    <style>
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
