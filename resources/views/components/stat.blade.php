@props([
    'value', // la cifra
    'label', // qué mide la cifra
    'tone' => 'neutral', // neutral | ok | warn | danger | info
    'delta' => null, // variación que acompaña a la cifra
    'deltaDir' => null, // up | down | null (color de la variación)
    'spark' => null, // serie de números para la minilínea
    'hint' => null, // nota bajo la cifra
])

@php
    $accent = [
        'neutral' => 'var(--muni-text)', 'ok' => 'var(--muni-ok-fg)',
        'warn' => 'var(--muni-warn-fg)', 'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)',
    ][$tone] ?? 'var(--muni-text)';

    // Sparkline: array de números → path SVG normalizado en un viewbox 100x28.
    $sparkPath = null;
    // Se reindexa: una serie con claves (['ene' => 3, ...]) dividía una cadena y tronaba.
    if ($spark instanceof \Illuminate\Contracts\Support\Arrayable) { $spark = $spark->toArray(); }
    if (is_array($spark)) { $spark = array_values(array_map(fn ($v) => (float) $v, $spark)); }
    if (is_array($spark) && count($spark) > 1) {
        $min = min($spark); $max = max($spark); $range = ($max - $min) ?: 1;
        $n = count($spark) - 1;
        $pts = [];
        foreach ($spark as $i => $v) {
            $x = round($i / $n * 100, 2);
            $y = round(26 - (($v - $min) / $range) * 24, 2);
            $pts[] = "$x,$y";
        }
        $sparkPath = 'M'.implode(' L', $pts);
    }

    $deltaColor = $deltaDir === 'up' ? 'var(--muni-ok-fg)' : ($deltaDir === 'down' ? 'var(--muni-danger-fg)' : 'var(--muni-muted)');

    // El sentido de la variación sale de `deltaDir`, no del signo que el host
    // haya puesto (o no) dentro de `delta`: con `delta="12 %"` un lector de
    // pantalla oía «12 %» y no sabía hacia dónde. Va como texto oculto delante
    // de la cifra («subió 12 %»); la flecha queda decorativa y en pantalla es
    // el portador que no depende del color (WCAG 1.4.1). Sin `deltaDir` no se
    // inventa nada: el delta se muestra como cifra neutra.
    $deltaTexto = ['up' => 'subió', 'down' => 'bajó'][$deltaDir] ?? null;

    // Id del rótulo, al que apunta `aria-labelledby` de la raíz. Determinista
    // —nada de uniqid(), que cambia en cada render y ensucia el diffing de
    // Livewire—: cuelga del `id` que pase el consumidor, y si no, de un slug
    // del rótulo. Dos stats con el mismo rótulo en la misma página
    // colisionarían: para eso está `id`.
    $statId = $attributes->get('id') ?: 'muni-stat-'.(\Illuminate\Support\Str::slug((string) $label) ?: substr(sha1((string) $label), 0, 8));
    $rotuloId = $statId.'-rotulo';
@endphp

{{-- `role="group"` + `aria-labelledby` atan la cifra a su rótulo sin tocar el DOM
     (un <dl> con un solo par no aporta más y rompía la compatibilidad de los
     hosts que ya estilizan `.muni-stat`). El grupo se anuncia como «Ingresadas
     hoy, grupo» y dentro se oye «128, subió 12 %», en ese orden. Van por
     merge() para que `class` del consumidor se concatene (antes se perdía) y
     para que un host que asuma el contrato de región viva pueda pasar su
     propio `role`. --}}
<div {{ $attributes->merge(['class' => 'muni-stat', 'role' => 'group', 'aria-labelledby' => $rotuloId, 'style' => 'position:relative;padding:16px 18px;background:var(--muni-surface);border:1px solid var(--muni-border);border-radius:var(--muni-radius);box-shadow:var(--muni-shadow);min-width:170px;transition:box-shadow var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease);']) }}>
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
        <div style="min-width:0;">
            <div class="muni-num" style="font-size:26px;font-weight:700;line-height:1.05;color:{{ $accent }};">{{ $value }}</div>
            <div id="{{ $rotuloId }}" style="margin-top:5px;font-size:11px;font-weight:600;color:var(--muni-muted);text-transform:uppercase;letter-spacing:.04em;">{{ $label }}</div>
        </div>
        @if ($delta !== null)
            <span class="muni-stat__delta muni-num" style="color:{{ $deltaColor }};">
                @if ($deltaDir === 'up')
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 12 12"><path d="M6 1.5 11 10.5H1Z" fill="currentColor"/></svg>
                @elseif ($deltaDir === 'down')
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 12 12"><path d="M6 10.5 1 1.5h10Z" fill="currentColor"/></svg>
                @endif
                @if ($deltaTexto)<span class="muni-stat__sr">{{ $deltaTexto }} </span>@endif{{ $delta }}
            </span>
        @endif
    </div>

    @if ($sparkPath)
        <svg viewBox="0 0 100 28" preserveAspectRatio="none" style="width:100%;height:26px;margin-top:10px;overflow:visible;" aria-hidden="true">
            <path d="{{ $sparkPath }} L100,28 L0,28 Z" fill="{{ $accent }}" opacity="0.08" />
            <path d="{{ $sparkPath }}" fill="none" stroke="{{ $accent }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
        </svg>
    @endif
    @if ($hint)<div style="margin-top:6px;font-size:11px;color:var(--muni-hint);">{{ $hint }}</div>@endif
</div>

@once
    <style>
        /* Lo que el componente necesita viaja con él y NO en muni-ui.css
           (DESIGN §7): dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css. Por eso `.muni-num` —la firma del
           sistema— se declara acá acotada a la tarjeta, aunque data-table ya
           la emita: en una página sin tabla la cifra perdía la mono tabular. */
        .muni-stat .muni-num { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; }
        .muni-stat__delta { display:inline-flex; align-items:center; gap:3px; font-size:11.5px; font-weight:600; white-space:nowrap; }
        .muni-stat__delta svg { flex:none; }
        /* Oculto visualmente pero presente en el árbol de accesibilidad: se
           recorta con clip-path y 1px. display:none o visibility:hidden lo
           sacarían del árbol y el sentido volvería a vivir solo en el color. */
        .muni-stat__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        .muni-stat:hover { box-shadow: var(--muni-shadow-md); transform: translateY(-1px); }
        /* La duración sale de --muni-dur, que muni-ui.css baja a 0 ms con
           movimiento reducido; la hoja del panel (la única que se carga ahí)
           no lo baja, así que la tarjeta apaga su transición por su cuenta.
           Con !important porque la transición va en el style de la raíz y una
           regla de clase sola pierde contra él: medido, seguía en 160 ms. */
        @media (prefers-reduced-motion:reduce) { .muni-stat { transition:none !important; } }
    </style>
@endonce
