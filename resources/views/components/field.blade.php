@props([
    'label' => null, // etiqueta sobre el control
    'name' => null, // reservado; el control del slot lleva su propio name
])

{{-- Envoltura de campo para la filter-bar: label + control (input/select en el slot).
     Los controles nativos del slot toman el aspecto de muni-input con especificidad
     cero (:where), así que cualquier estilo propio del sistema host gana. --}}
<label {{ $attributes->merge(['class' => 'muni-field', 'style' => 'display:flex;flex-direction:column;gap:4px;']) }}>
    @if ($label)
        <span style="font-family:var(--muni-font-sans);font-size:11px;font-weight:600;color:var(--muni-muted);text-transform:uppercase;letter-spacing:.03em;">{{ $label }}</span>
    @endif
    {{ $slot }}
</label>

@once
    <style>
        :where(.muni-field) > :where(input:not([type=checkbox],[type=radio],[type=hidden]), select, textarea) {
            min-width: 0; padding: 8px 11px; font-family: var(--muni-font-sans); font-size: 13.5px; color: var(--muni-text);
            background: var(--muni-surface); border: 1px solid var(--muni-border); border-radius: var(--muni-radius-sm);
            transition: border-color var(--muni-dur) var(--muni-ease), box-shadow var(--muni-dur) var(--muni-ease);
        }
        :where(.muni-field) > :where(input, select, textarea)::placeholder { color: var(--muni-hint); }
        :where(.muni-field) > :where(input, select, textarea):focus { outline: none; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
    </style>
@endonce
