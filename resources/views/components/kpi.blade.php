@props([
    'value',
    'label',
    'tone' => 'neutral',
    'hint' => null,
])

@php
    $accent = [
        'neutral' => 'var(--muni-text)',
        'ok' => 'var(--muni-ok-fg)',
        'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)',
        'info' => 'var(--muni-info-fg)',
    ][$tone] ?? 'var(--muni-text)';
@endphp

<div
    {{ $attributes->merge([
        'style' => 'position:relative;padding:16px 18px;background:var(--muni-surface);'
            .'border:1px solid var(--muni-border);border-radius:var(--muni-radius);'
            .'box-shadow:var(--muni-shadow);min-width:130px;',
    ]) }}
>
    {{-- Franja de acento a la izquierda: encoda el tono sin ser un badge redondo. --}}
    <span aria-hidden="true" style="position:absolute;left:0;top:10px;bottom:10px;width:3px;border-radius:0 2px 2px 0;background:{{ $accent }};"></span>

    <div style="font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;font-size:28px;font-weight:700;line-height:1.05;color:{{ $accent }};padding-left:8px;">
        {{ $value }}
    </div>
    <div style="margin-top:4px;padding-left:8px;font-family:var(--muni-font-sans);font-size:12px;font-weight:500;color:var(--muni-muted);text-transform:uppercase;letter-spacing:.04em;">
        {{ $label }}
    </div>
    @if ($hint)
        <div style="margin-top:2px;padding-left:8px;font-size:11px;color:var(--muni-hint);">{{ $hint }}</div>
    @endif
</div>
