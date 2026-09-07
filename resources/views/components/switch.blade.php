@props([
    'label' => null,
    'name' => null,
    'checked' => false,
    'description' => null,
    'error' => null,
])

@php
    /*
     * Mismo bloque que input.blade.php y select.blade.php: si cambia acá, cambia allá.
     *
     * Identificador determinista, NUNCA uniqid() (DESIGN §8): cambia en cada render,
     * rompe el `for` de la etiqueta y ensucia el diffing de Livewire. Manda el `id`
     * del consumidor; si no lo pasa, sale del `name` saneado —`items[0][activo]` no es
     * un selector válido y dos filas de un formulario repetido colisionarían—, con
     * sufijo de hash para conservar la unicidad. El `name` viaja intacto al backend.
     */
    $muniId = trim((string) $attributes->get('id'));

    if ($muniId === '') {
        $base = (string) $name;

        if ($base === '') {
            $base = 'switch-'.substr(sha1(json_encode([$label, $description], JSON_UNESCAPED_UNICODE) ?: ''), 0, 8);
        } elseif (! preg_match('/^[A-Za-z0-9_-]+$/', $base)) {
            $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-').'-'.substr(sha1($base), 0, 6);
        }

        $muniId = 'muni-'.$base;
    }

    $muniErrorId = $muniId.'-error';

    /*
     * `$attributes->merge()` REEMPLAZA, no concatena: el aria-describedby del
     * consumidor se encadena a mano y se saca de la bolsa antes del merge.
     * `description` NO se encadena acá: vive dentro del <label> que envuelve al
     * control, así que ya forma parte de su nombre accesible; describirla además
     * la haría sonar dos veces.
     */
    $muniAria = array_filter([
        'aria-describedby' => implode(' ', array_filter([
            trim((string) $attributes->get('aria-describedby')),
            $error ? $muniErrorId : '',
        ])),
        'aria-invalid' => $error ? 'true' : '',
    ]);

    $attributes = $attributes->except(['id', 'aria-describedby']);
@endphp

<div style="display:flex;flex-direction:column;gap:4px;">
    <label for="{{ $muniId }}" style="display:flex;align-items:flex-start;gap:11px;cursor:pointer;">
        <span x-data="{ on: {{ $checked ? 'true' : 'false' }} }" style="position:relative;flex-shrink:0;margin-top:1px;">
            <input type="checkbox" id="{{ $muniId }}" @if ($name) name="{{ $name }}" @endif @checked($checked)
                   x-model="on"
                   {{ $attributes->merge($muniAria + ['style' => 'position:absolute;opacity:0;width:0;height:0;']) }}>
            <span class="muni-switch" :class="on && 'muni-switch--on'">
                <span class="muni-switch__thumb"></span>
            </span>
        </span>
        @if ($label || $description)
            <span style="display:flex;flex-direction:column;gap:1px;">
                @if ($label)<span style="font-family:var(--muni-font-sans);font-size:13.5px;font-weight:600;color:var(--muni-text);">{{ $label }}</span>@endif
                @if ($description)<span style="font-size:12px;color:var(--muni-muted);line-height:1.4;">{{ $description }}</span>@endif
            </span>
        @endif
    </label>

    {{-- El mensaje va FUERA del <label>: dentro, su texto se le pegaría al nombre
         accesible del control. La región existe desde el primer render y reserva el
         alto de una línea: con Livewire el error llega sin recargar la página, así
         que un <span> que nace de la nada no lo lee ningún lector de pantalla
         (WCAG 2.2 AA 4.1.3) y encima empuja el contenido de abajo. --}}
    <div role="status" aria-live="polite" style="min-height:15px;">
        @if ($error)
            {{-- El icono acompaña al color: el estado no se comunica solo con rojo. --}}
            <span id="{{ $muniErrorId }}" style="display:flex;align-items:flex-start;gap:4px;font-size:11.5px;line-height:15px;font-weight:600;color:var(--muni-danger-fg);">
                <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" style="flex-shrink:0;margin-top:1px;"><path d="M8 2.5L15 14H1L8 2.5z" stroke-linejoin="round"/><path d="M8 6.5v3.2M8 11.8v.2" stroke-linecap="round"/></svg>
                <span>{{ $error }}</span>
            </span>
        @endif
    </div>
</div>

@once
    <style>
        .muni-switch { display:inline-block; width:38px; height:22px; border-radius:999px; background:var(--muni-surface-3); border:1px solid var(--muni-border); transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-switch__thumb { display:block; width:16px; height:16px; margin:2px; border-radius:50%; background:var(--muni-surface); box-shadow:0 1px 3px rgba(0,0,0,.25); transition:transform var(--muni-dur) var(--muni-ease); }
        .muni-switch--on { background:var(--muni-accent); border-color:var(--muni-accent); }
        .muni-switch--on .muni-switch__thumb { transform:translateX(16px); }
        /* El <input> real va con opacity:0 y 0×0, así que el contorno nativo del
           navegador no se ve: el ÚNICO indicador de foco era esta box-shadow, y la
           box-shadow se pierde dentro del panel de Filament (ver --muni-focus). */
        input:focus-visible + .muni-switch { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
    </style>
@endonce
