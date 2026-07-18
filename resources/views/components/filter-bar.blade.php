@props([
    'action' => null,
    'method' => 'get',
])

{{-- Barra de filtros GET: los valores quedan en la URL para que las descargas
     (xlsx/csv) los arrastren. El slot son los campos (usar <x-muni::field>). --}}
<form
    method="{{ $method }}"
    @if ($action) action="{{ $action }}" @endif
    {{ $attributes->merge([
        'style' => 'display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;'
            .'padding:14px 16px;margin:0 0 16px;background:var(--muni-surface-2);'
            .'border:1px solid var(--muni-border);border-radius:var(--muni-radius);',
    ]) }}
>
    {{ $slot }}

    <div style="display:flex;gap:8px;margin-left:auto;">
        <button type="submit" style="font-family:var(--muni-font-sans);font-size:13px;font-weight:600;padding:9px 18px;border:none;border-radius:var(--muni-radius-sm);background:var(--muni-accent);color:var(--muni-on-accent);cursor:pointer;">
            {{ $submitLabel ?? 'Aplicar' }}
        </button>
        {{ $actions ?? '' }}
    </div>
</form>
