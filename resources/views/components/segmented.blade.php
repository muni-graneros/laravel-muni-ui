@props([
    'name' => null,
    'options' => [],
    'value' => null,
])

{{-- Control segmentado (toggle de filtro): alternativa moderna al <select> para pocas
     opciones. Con `name` genera radios reales (funciona sin JS, submit del form nativo);
     sin `name` es puramente visual/enlaces (usar el slot). --}}
<div role="group" {{ $attributes->merge(['style' => 'display:inline-flex;padding:3px;gap:2px;background:var(--muni-surface-2);border:1px solid var(--muni-border);border-radius:var(--muni-radius-sm);']) }}>
    @if (! empty($options) && $name)
        @foreach ($options as $val => $label)
            @php $id = $name.'-'.$loop->index; $active = (string) $value === (string) $val; @endphp
            <label for="{{ $id }}" class="muni-seg {{ $active ? 'muni-seg--on' : '' }}">
                <input type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $val }}" @checked($active)
                       style="position:absolute;opacity:0;width:0;height:0;" onchange="this.form && this.form.submit()">
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
        .muni-seg:has(input:focus-visible) { box-shadow:var(--muni-ring); }
        .muni-seg--on { background:var(--muni-surface);color:var(--muni-text);box-shadow:var(--muni-shadow); }
    </style>
@endonce
