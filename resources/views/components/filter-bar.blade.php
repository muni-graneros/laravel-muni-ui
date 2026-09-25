@props([
    'action' => null,
    'method' => 'get',
])

{{-- Barra de filtros GET: los valores quedan en la URL para que las descargas
     (xlsx/csv) los arrastren. El slot son los campos (usar <x-muni::field>). --}}
@php
    /* El token va SOLO si el método no es GET, y la comparación es insensible a
       mayúsculas porque `method="POST"` es igual de válido en HTML. En GET sería
       peor que inútil: los filtros viven en la URL —esa es toda la gracia del
       componente— y el token acabaría en la cadena de consulta que el funcionario
       copia y pega en un correo. En POST, sin token, Laravel responde 419 y el
       filtro no se aplica jamás: es el defecto que el juez mandó arreglar antes
       que cualquier componente nuevo. */
    $muniFbProtegido = strtolower(trim((string) $method)) !== 'get';
@endphp

<form
    method="{{ $method }}"
    @if ($action) action="{{ $action }}" @endif
    {{ $attributes->merge([
        'style' => 'display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;'
            .'padding:14px 16px;margin:0 0 16px;background:var(--muni-surface-2);'
            .'border:1px solid var(--muni-border);border-radius:var(--muni-radius);',
    ]) }}
>
    @if ($muniFbProtegido)
        @csrf
    @endif

    {{ $slot }}

    <div style="display:flex;gap:8px;margin-left:auto;">
        <button type="submit" style="font-family:var(--muni-font-sans);font-size:13px;font-weight:600;padding:9px 18px;border:none;border-radius:var(--muni-radius-sm);background:var(--muni-accent);color:var(--muni-on-accent);cursor:pointer;">
            {{ $submitLabel ?? 'Aplicar' }}
        </button>
        {{ $actions ?? '' }}
    </div>
</form>
