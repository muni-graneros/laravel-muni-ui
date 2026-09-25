@props([
    'value' => 0, // valor actual
    'max' => 100, // valor máximo
    'tone' => 'accent', // accent | ok | warn | danger | info
    'label' => null, // etiqueta sobre la barra
    'showValue' => false, // muestra el porcentaje
])

@php
    $value = (float) $value; $max = (float) $max;
    $pct = $max > 0 ? (int) max(0, min(100, round($value / $max * 100))) : 0;
    $color = \Muni\Ui\Tono::color($tone);
@endphp

<div {{ $attributes }}>
    @if ($label || $showValue)
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;margin-bottom:6px;">
            @if ($label)<span style="font-family:var(--muni-font-sans);font-size:12px;font-weight:600;color:var(--muni-muted);">{{ $label }}</span>@endif
            @if ($showValue)<span style="font-family:var(--muni-font-mono);font-size:12px;font-variant-numeric:tabular-nums;color:var(--muni-text);">{{ $pct }}%</span>@endif
        </div>
    @endif
    {{--
        El nombre accesible es obligatorio: sin él, un lector de pantalla
        anuncia «barra de progreso, 64 %» sin decir progreso DE QUÉ, y en una
        pantalla con varias no hay forma de distinguirlas. Se reutiliza la
        etiqueta visible para que lo que se oye y lo que se ve coincidan; si no
        se pasó ninguna, queda un nombre genérico, que sigue siendo mejor que
        ninguno. Quien necesite otro texto puede pasar aria-label.
    --}}
    <div role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
         aria-label="{{ $attributes->get('aria-label', $label ?: 'Progreso') }}"
         style="height:7px;border-radius:999px;background:var(--muni-surface-3);overflow:hidden;">
        <div style="height:100%;width:{{ $pct }}%;border-radius:999px;background:{{ $color }};transition:width calc(var(--muni-dur) * 3) var(--muni-ease);"></div>
    </div>
</div>
