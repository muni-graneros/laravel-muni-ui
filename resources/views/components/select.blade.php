@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'error' => null,
    'hint' => null,
    'required' => false,
])

@php $id = $name ? 'muni-'.$name : 'muni-'.uniqid(); @endphp

<div style="display:flex;flex-direction:column;gap:6px;">
    @if ($label)
        <label for="{{ $id }}" style="font-family:var(--muni-font-sans);font-size:12.5px;font-weight:600;color:var(--muni-text);">
            {{ $label }}@if ($required)<span style="color:var(--muni-danger-fg);margin-left:2px;">*</span>@endif
        </label>
    @endif

    <div style="position:relative;">
        <select
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required @endif
            {{ $attributes->merge([
                'class' => 'muni-select',
                'style' => 'width:100%;padding:10px 34px 10px 12px;appearance:none;'
                    .'font-family:var(--muni-font-sans);font-size:13.5px;color:var(--muni-text);'
                    .'background:var(--muni-surface);border:1px solid '.($error ? 'var(--muni-danger-border)' : 'var(--muni-border)').';'
                    .'border-radius:var(--muni-radius-sm);cursor:pointer;transition:border-color var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease);',
            ]) }}
        >
            @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
            @if (! empty($options))
                @foreach ($options as $val => $text)
                    <option value="{{ $val }}" @selected((string) $selected === (string) $val)>{{ $text }}</option>
                @endforeach
            @else
                {{ $slot }}
            @endif
        </select>
        <span aria-hidden="true" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--muni-muted);">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="14" height="14"><path d="M4 6l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    </div>

    @if ($error)<span style="font-size:11.5px;color:var(--muni-danger-fg);">{{ $error }}</span>
    @elseif ($hint)<span style="font-size:11.5px;color:var(--muni-hint);">{{ $hint }}</span>@endif
</div>

@once
    <style>
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-select:focus { outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px; border-color: var(--muni-accent); box-shadow: var(--muni-ring); }
    </style>
@endonce
