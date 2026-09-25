@props([
    'name' => null, // con name genera radios reales que envían el form
    'options' => [], // mapa valor => etiqueta
    'value' => null, // valor seleccionado
    'autosubmit' => true, // true: envía el form GET al cambiar · 'siempre': también POST · false: nunca (nunca con wire:submit/@submit ni wire:model/x-model)
])

{{-- Control segmentado (toggle de filtro): alternativa moderna al <select> para pocas
     opciones. Con `name` genera radios reales (funciona sin JS, submit del form nativo);
     sin `name` es puramente visual/enlaces (usar el slot). El auto-envío solo corre en
     forms GET sin wire:submit ni @submit; un `id` sirve de prefijo para los ids de los radios. --}}
@php
    $prefijo = $attributes->get('id') ?: $name;
    // wire:model / x-model van a cada radio, que es donde vive el valor; con enlace en vivo
    // no hace falta enviar el form.
    $modelo = $attributes->filter(fn ($v, $k) => str_starts_with($k, 'wire:model') || str_starts_with($k, 'x-model'));
    $attributes = $attributes->filter(fn ($v, $k) => ! str_starts_with($k, 'wire:model') && ! str_starts_with($k, 'x-model'));
    $soloGet = $autosubmit !== 'siempre';
    $autosubmit = $autosubmit && $modelo->isEmpty();
    $enviar = "if(this.form".($soloGet ? " && this.form.method==='get'" : '')." && ![...this.form.attributes].some(a => /^(wire:submit|x-on:submit|@submit)/.test(a.name))) this.form.submit()";
@endphp
<div role="group" {{ $attributes->merge(['style' => 'display:inline-flex;padding:3px;gap:2px;background:var(--muni-surface-2);border:1px solid var(--muni-border);border-radius:var(--muni-radius-sm);']) }}>
    @if (! empty($options) && $name)
        @foreach ($options as $val => $label)
            @php $id = $prefijo.'-'.$loop->index; $active = (string) $value === (string) $val; @endphp
            <label for="{{ $id }}" class="muni-seg {{ $active ? 'muni-seg--on' : '' }}">
                <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $val }}" @checked($active) {{ $modelo }}
                       style="position:absolute;opacity:0;width:0;height:0;" @if ($autosubmit) onchange="{{ $enviar }}" @endif>
                {{ $label }}
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
        /* El resaltado sigue al radio marcado aunque el form no se envíe; --on queda para el slot. */
        .muni-seg--on, .muni-seg:has(input:checked) { background:var(--muni-surface);color:var(--muni-text);box-shadow:var(--muni-shadow); }
        .muni-seg--on:has(input:not(:checked)) { background:transparent;color:var(--muni-muted);box-shadow:none; }
        .muni-seg.muni-seg:has(input:focus-visible) { box-shadow:var(--muni-ring); }
    </style>
@endonce
