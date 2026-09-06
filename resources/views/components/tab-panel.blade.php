@props([
    'index',
])

{{-- El grupo se lee del padre con @aware. Blade solo expone al hijo los datos que el
     consumidor pasó EXPLÍCITAMENTE al padre —no lo que <x-muni::tabs> calcula dentro de
     su propia vista—, así que el id no se hereda: se REDERIVA acá del mismo dato
     (`id` si se pasó, si no el array `tabs`), con la misma fórmula. --}}
@aware([
    'tabs' => [],
    'id' => null,
])

@php
    // Debe quedar idéntica a la línea equivalente de tabs.blade.php.
    $grupoId = $id ?: 'muni-tabs-'.substr(sha1(json_encode(array_values((array) $tabs), JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
    $posicion = (int) $index;

    /*
     * El APG pide tabindex="0" en el tabpanel SOLO cuando no contiene ningún elemento
     * enfocable propio: si no, el Tab que sale de la pestaña se salta el contenido (una
     * ficha de antecedentes que es puro texto). Cuando el panel ya trae un control, ese
     * tabindex sería una parada de tabulación de más, así que se omite.
     */
    $tieneFocables = (bool) preg_match(
        '/<(?:a\b[^>]*\bhref=|button\b|input\b|select\b|textarea\b|iframe\b|summary\b|[a-z-]+\b[^>]*\b(?:tabindex="0"|contenteditable))/i',
        (string) $slot
    );

    /*
     * Van por ->merge() y NO escritos a mano antes de {{ $attributes }}: ante dos
     * atributos con el mismo nombre en una etiqueta, el parser HTML se queda con el
     * PRIMERO, así que un id= o un class= del consumidor se perdería en silencio.
     * Con merge, `class` se suma a la del componente y el resto lo puede pisar quien
     * consume, que es el comportamiento que tenía este componente.
     */
    $atributosPanel = [
        'id' => $grupoId.'-panel-'.$posicion,
        'aria-labelledby' => $grupoId.'-tab-'.$posicion,
        'class' => 'muni-tabpanel',
    ];

    if (! $tieneFocables) {
        $atributosPanel['tabindex'] = '0';
    }
@endphp

{{-- Panel de una pestaña. `index` debe coincidir con la posición de su etiqueta en
     el array `tabs` del <x-muni::tabs> padre. --}}
<div
    role="tabpanel"
    x-show="active === {{ $posicion }}"
    x-cloak
    x-transition:enter="muni-fade" x-transition:enter-start="muni-fade-0" x-transition:enter-end="muni-fade-1"
    {{ $attributes->merge($atributosPanel) }}
>
    {{ $slot }}
</div>
