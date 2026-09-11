@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'icon' => null,
    'required' => false,
])

@php
    /*
     * Identificador del campo. NUNCA uniqid() (DESIGN §10): cambia en cada render,
     * rompe el `for` de la etiqueta y ensucia el diffing de Livewire. Mismo patrón
     * determinista que ya usan tabs.blade.php y tab-panel.blade.php: manda el `id`
     * que pase el consumidor y, si no lo pasa, se deriva de datos estables.
     *
     * El `name` se SANEA para el id y viaja intacto al backend: `items[0][rut]`
     * —lo normal en un formulario repetido de Livewire— produciría un id que no es
     * un selector válido, y dos filas distintas colisionarían. El sufijo de hash
     * mantiene la unicidad entre filas.
     *
     * Sin `name` el id sale de un hash del contenido: es estable entre renders, que
     * es lo que importa. Dos inputs idénticos y anónimos en la misma página
     * colisionarían: para eso está el `id` del consumidor.
     */
    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        $base = (string) $name;

        if ($base === '') {
            $base = 'input-'.substr(sha1(json_encode([$label, $type, $hint], JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
        } elseif (! preg_match('/^[A-Za-z0-9_-]+$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        $muniId = 'muni-'.$base;
    }

    $muniHintId = $muniId.'-hint';
    $muniErrorId = $muniId.'-error';

    /*
     * `$attributes->merge()` REEMPLAZA, no concatena, en todo lo que no sea class ni
     * style: si el consumidor trae su propio aria-describedby, uno de los dos
     * desaparece sin aviso. Por eso se lee, se encadena a mano y se saca de la bolsa
     * antes del merge. La ayuda va primero y el error después, que es el orden en
     * que conviene oírlos.
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

    <div style="position:relative;display:flex;align-items:center;">
        @if ($icon)
            <span aria-hidden="true" style="position:absolute;left:11px;display:inline-flex;color:var(--muni-muted);pointer-events:none;">{!! $icon !!}</span>
        @endif
        <input
            id="{{ $muniId }}"
            type="{{ $type }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required @endif
            {{ $attributes->merge($muniAria + [
                'class' => 'muni-input',
                'style' => 'width:100%;padding:10px 12px;'.($icon ? 'padding-left:36px;' : '')
                    .'font-family:var(--muni-font-sans);font-size:13.5px;color:var(--muni-text);'
                    .'background:var(--muni-surface);border:1px solid '.($error ? 'var(--muni-field-border-error)' : 'var(--muni-field-border)').';'
                    .'border-radius:var(--muni-radius-sm);transition:border-color var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease);',
            ]) }}
        >
    </div>

    {{-- La ayuda y el error CONVIVEN, cada uno con su id: el `@elseif` de antes
         borraba el «formato 12.345.678-9» justo cuando el vecino lo necesita, que
         es cuando se equivocó.

         La región existe desde el primer render y reserva el alto de una línea:
         con Livewire el error llega en un round-trip sin recargar la página, así
         que un <span> que nace de la nada no lo lee ningún lector de pantalla
         (WCAG 2.2 AA 4.1.3) y además empuja el contenido de abajo. --}}
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
        .muni-input::placeholder { color: var(--muni-hint); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-input:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
        .muni-input:disabled { background: var(--muni-surface-2); color: var(--muni-muted); cursor: not-allowed; }
    </style>
@endonce
