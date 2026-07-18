@props([
    'tabs' => [],
    'default' => 0,
])

{{-- Pestañas (Alpine 3). `tabs` es un array de etiquetas; los paneles van en el slot como
     <x-muni::tab-panel> en el mismo orden. Navegación con flechas ←/→. --}}
<div x-data="{ active: {{ (int) $default }}, count: {{ count($tabs) }} }" {{ $attributes }}>
    <div
        role="tablist"
        style="display:flex;gap:2px;border-bottom:1px solid var(--muni-border);margin-bottom:16px;overflow-x:auto;"
        @keydown.right.prevent="active = (active + 1) % count"
        @keydown.left.prevent="active = (active - 1 + count) % count"
    >
        @foreach ($tabs as $i => $label)
            <button
                type="button"
                role="tab"
                :aria-selected="active === {{ $i }}"
                :tabindex="active === {{ $i }} ? 0 : -1"
                @click="active = {{ $i }}"
                class="muni-tab"
                :class="active === {{ $i }} && 'muni-tab--on'"
            >{{ $label }}</button>
        @endforeach
    </div>

    {{ $slot }}
</div>

@once
    <style>
        .muni-tab { position:relative;padding:10px 14px;font-family:var(--muni-font-sans);font-size:13.5px;font-weight:600;
            color:var(--muni-muted);background:transparent;border:none;cursor:pointer;white-space:nowrap;
            transition:color var(--muni-dur) var(--muni-ease); }
        .muni-tab:hover { color:var(--muni-text); }
        .muni-tab:focus-visible { outline:none;box-shadow:var(--muni-ring);border-radius:var(--muni-radius-sm); }
        .muni-tab--on { color:var(--muni-accent); }
        .muni-tab--on::after { content:"";position:absolute;left:8px;right:8px;bottom:-1px;height:2px;
            background:var(--muni-accent);border-radius:2px 2px 0 0; }
    </style>
@endonce
