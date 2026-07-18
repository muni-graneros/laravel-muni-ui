@props([
    'text' => '',
    'placement' => 'top',
])

@php
    $pos = [
        'top' => 'bottom:calc(100% + 7px);left:50%;transform:translateX(-50%);',
        'bottom' => 'top:calc(100% + 7px);left:50%;transform:translateX(-50%);',
        'left' => 'right:calc(100% + 7px);top:50%;transform:translateY(-50%);',
        'right' => 'left:calc(100% + 7px);top:50%;transform:translateY(-50%);',
    ][$placement] ?? 'bottom:calc(100% + 7px);left:50%;transform:translateX(-50%);';
@endphp

<span
    x-data="{ show:false }"
    @mouseenter="show=true" @mouseleave="show=false" @focusin="show=true" @focusout="show=false"
    style="position:relative;display:inline-flex;"
    {{ $attributes }}
>
    {{ $slot }}
    <span x-show="show" x-cloak x-transition.opacity role="tooltip"
          style="position:absolute;{{ $pos }}z-index:60;white-space:nowrap;padding:5px 9px;font-family:var(--muni-font-sans);font-size:11.5px;font-weight:500;color:var(--muni-on-accent);background:var(--muni-text);border-radius:var(--muni-radius-sm);box-shadow:var(--muni-shadow-md);pointer-events:none;">{{ $text }}</span>
</span>
