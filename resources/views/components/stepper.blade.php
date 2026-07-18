@props([
    'steps' => [],
    'current' => 0,
    'orientation' => 'horizontal',
])

@php
    // $steps: array de strings o de ['label'=>, 'hint'=>?]. $current = índice activo (0-based).
    $current = (int) $current;
    $vertical = $orientation === 'vertical';
@endphp

<ol {{ $attributes->merge(['class' => 'muni-stepper '.($vertical ? 'muni-stepper--v' : '')]) }}>
    @foreach ($steps as $i => $step)
        @php
            $label = is_array($step) ? ($step['label'] ?? '') : $step;
            $hint = is_array($step) ? ($step['hint'] ?? null) : null;
            $state = $i < $current ? 'done' : ($i === $current ? 'active' : 'todo');
        @endphp
        <li class="muni-step muni-step--{{ $state }}" @if ($state === 'active') aria-current="step" @endif>
            <span class="muni-step__marker">
                @if ($state === 'done')
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M3.5 8.5l3 3 6-6.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @else
                    <span class="muni-step__num">{{ $i + 1 }}</span>
                @endif
            </span>
            <span class="muni-step__body">
                <span class="muni-step__label">{{ $label }}</span>
                @if ($hint)<span class="muni-step__hint">{{ $hint }}</span>@endif
            </span>
        </li>
    @endforeach
</ol>

@once
    <style>
        .muni-stepper { list-style:none; margin:0; padding:0; display:flex; gap:0; font-family:var(--muni-font-sans); }
        .muni-step { flex:1; display:flex; align-items:center; gap:10px; position:relative; min-width:0; }
        .muni-step:not(:last-child)::after { content:""; position:absolute; left:calc(14px + 26px); right:-10px; top:14px; height:2px; background:var(--muni-border); z-index:0; }
        .muni-step--done:not(:last-child)::after { background:var(--muni-accent); }
        .muni-step__marker { position:relative; z-index:1; flex-shrink:0; width:28px; height:28px; border-radius:50%; display:grid; place-items:center; border:2px solid var(--muni-border); background:var(--muni-surface); color:var(--muni-muted); transition:all var(--muni-dur) var(--muni-ease); }
        .muni-step__num { font-family:var(--muni-font-mono); font-size:12px; font-weight:600; }
        .muni-step__body { display:flex; flex-direction:column; min-width:0; }
        .muni-step__label { font-size:12.5px; font-weight:600; color:var(--muni-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .muni-step__hint { font-size:11px; color:var(--muni-hint); }
        .muni-step--active .muni-step__marker { border-color:var(--muni-accent); color:var(--muni-accent); box-shadow:var(--muni-ring); }
        .muni-step--active .muni-step__label { color:var(--muni-text); }
        .muni-step--done .muni-step__marker { border-color:var(--muni-accent); background:var(--muni-accent); color:var(--muni-on-accent); }
        .muni-step--done .muni-step__label { color:var(--muni-text); }

        .muni-stepper--v { flex-direction:column; gap:4px; }
        .muni-stepper--v .muni-step { flex:none; align-items:flex-start; padding-bottom:14px; }
        .muni-stepper--v .muni-step:not(:last-child)::after { left:13px; right:auto; top:28px; bottom:0; width:2px; height:auto; }
        .muni-stepper--v .muni-step__body { padding-top:4px; }
    </style>
@endonce
