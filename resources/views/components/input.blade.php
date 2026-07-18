@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'icon' => null,
    'required' => false,
])

@php $id = $name ? 'muni-'.$name : 'muni-'.uniqid(); @endphp

<div style="display:flex;flex-direction:column;gap:6px;">
    @if ($label)
        <label for="{{ $id }}" style="font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;color:var(--muni-text);">
            {{ $label }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
        </label>
    @endif

    <div style="position:relative;display:flex;align-items:center;">
        @if ($icon)
            <span aria-hidden="true" style="position:absolute;left:11px;display:inline-flex;color:var(--muni-muted);pointer-events:none;">{!! $icon !!}</span>
        @endif
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->merge([
                'class' => 'muni-input',
                'style' => 'width:100%;padding:10px 12px;'.($icon ? 'padding-left:36px;' : '')
                    .'font-family:var(--muni-font-sans);font-size:13.5px;color:var(--muni-text);'
                    .'background:var(--muni-surface);border:1px solid '.($error ? 'var(--muni-danger-border)' : 'var(--muni-border)').';'
                    .'border-radius:var(--muni-radius-sm);transition:border-color var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease);',
            ]) }}
        >
    </div>

    @if ($error)
        <span style="font-size:11.5px;color:var(--muni-danger-fg);">{{ $error }}</span>
    @elseif ($hint)
        <span style="font-size:11.5px;color:var(--muni-hint);">{{ $hint }}</span>
    @endif
</div>

@once
    <style>
        .muni-input::placeholder { color: var(--muni-hint); }
        .muni-input:focus { outline: none; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
        .muni-input:disabled { background: var(--muni-surface-2); color: var(--muni-muted); cursor: not-allowed; }
    </style>
@endonce
