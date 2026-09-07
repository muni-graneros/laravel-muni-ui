@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'error' => null,
    'hint' => null,
    'required' => false,
])

@php
    /*
     * Mismo bloque que input.blade.php: si cambia acá, cambia allá (y en switch).
     *
     * Identificador determinista, NUNCA uniqid() (DESIGN §8): cambia en cada render,
     * rompe el `for` de la etiqueta y ensucia el diffing de Livewire. Manda el `id`
     * que pase el consumidor; si no lo pasa, sale del `name` saneado —`items[0][rut]`
     * no es un selector válido y dos filas de un formulario repetido colisionarían—,
     * con sufijo de hash para conservar la unicidad. El `name` viaja intacto al
     * backend: lo que se sanea es el id.
     */
    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        $base = (string) $name;

        if ($base === '') {
            $base = 'select-'.substr(sha1(json_encode([$label, $placeholder, $hint], JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
        } elseif (! preg_match('/^[A-Za-z0-9_-]+$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        $muniId = 'muni-'.$base;
    }

    $muniHintId = $muniId.'-hint';
    $muniErrorId = $muniId.'-error';

    /*
     * `$attributes->merge()` REEMPLAZA, no concatena, en todo lo que no sea class ni
     * style: el aria-describedby del consumidor y el del componente no pueden
     * coexistir por merge. Se lee, se encadena a mano y se saca de la bolsa.
     * `aria-invalid` es nuevo en select: antes el error era solo color rojo.
     */
    $muniAria = array_filter([
        'aria-describedby' => implode(' ', array_filter([
            trim((string) $attributes->get('aria-describedby')),
            $hint ? $muniHintId : '',
            $error ? $muniErrorId : '',
        ])),
        'aria-invalid' => $error ? 'true' : '',
    ]);

    $attributes = $attributes->except(['id', 'aria-describedby']);
@endphp

<div style="display:flex;flex-direction:column;gap:6px;">
    @if ($label)
        <label for="{{ $muniId }}" style="font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;color:var(--muni-text);">
            {{ $label }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
        </label>
    @endif

    <div style="position:relative;">
        <select
            id="{{ $muniId }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required @endif
            {{ $attributes->merge($muniAria + [
                'class' => 'muni-select',
                'style' => 'width:100%;padding:10px 34px 10px 12px;appearance:none;'
                    .'font-family:var(--muni-font-sans);font-size:13.5px;color:var(--muni-text);'
                    .'background:var(--muni-surface);border:1px solid '.($error ? 'var(--muni-danger-border)' : 'var(--muni-field-border)').';'
                    .'border-radius:var(--muni-radius-sm);cursor:pointer;transition:border-color var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease);',
            ]) }}
        >
            @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
            @if (! empty($options))
                @foreach ($options as $val => $text)
                    <option value="{{ $val }}" @selected((string) $selected === (string) $val)>{{ $text }}</option>
                @endforeach
            @else
                {{ $slot }}
            @endif
        </select>
        <span aria-hidden="true" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--muni-muted);">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="14" height="14"><path d="M4 6l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    </div>

    {{-- La ayuda y el error CONVIVEN, cada uno con su id, y los dos van encadenados
         en aria-describedby. La región existe desde el primer render y reserva el
         alto de una línea: con Livewire el error llega sin recargar la página, así
         que un <span> que nace de la nada no lo lee ningún lector de pantalla
         (WCAG 2.2 AA 4.1.3) y encima empuja el contenido de abajo. --}}
    <div role="status" aria-live="polite" style="display:flex;flex-direction:column;gap:2px;min-height:15px;">
        @if ($hint)
            <span id="{{ $muniHintId }}" style="font-size:11.5px;line-height:15px;color:var(--muni-hint);">{{ $hint }}</span>
        @endif
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
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-select:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
    </style>
@endonce
