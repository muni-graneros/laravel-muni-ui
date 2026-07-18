@props([
    'label' => null,
    'name' => null,
])

{{-- Envoltura de campo para la filter-bar: label + control (input/select en el slot). --}}
<label style="display:flex;flex-direction:column;gap:4px;">
    @if ($label)
        <span style="font-family:var(--muni-font-sans);font-size:11px;font-weight:600;color:var(--muni-muted);text-transform:uppercase;letter-spacing:.03em;">{{ $label }}</span>
    @endif
    {{ $slot }}
</label>
