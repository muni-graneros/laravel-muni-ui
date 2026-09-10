@props([
    'items' => [],
])

{{--
    Línea de tiempo = bitácora auditable.

    `$items` es un array de arrays. La ÚNICA clave que se usaba antes era
    `title`; todas las demás son opcionales y aditivas, así que una llamada
    vieja renderiza exactamente igual que ayer.

    | clave        | tipo   | defecto        | para qué |
    |--------------|--------|----------------|----------|
    | `title`      | string | ''             | qué pasó |
    | `time`       | string | —              | cuándo, ya formateado en es-CL por el sistema anfitrión |
    | `datetime`   | string | —              | el mismo instante en ISO-8601, para la máquina |
    | `description`| string | —              | el cuerpo del hito |
    | `tone`       | string | 'accent'       | ok / warn / danger / info / accent / muted |
    | `toneLabel`  | string | según el tono  | reescribe la etiqueta textual del estado |
    | `actor`      | string | —              | quién hizo la actuación |
    | `current`    | bool   | false          | el hito vigente; solo el primero marcado gana |
    | `detail`     | string | —              | el cambio antes/después, YA REDACTADO por el anfitrión |
    | `detailLabel`| string | 'Ver el detalle del cambio' | el texto del resumen colapsado |

    Cuatro decisiones que no son de gusto:

    1. La etiqueta del tono es VISIBLE, no reservada al lector de pantalla.
       El criterio 1.4.1 de WCAG es sobre la presentación visual: quien no
       distingue el verde del rojo VE la pantalla, así que una etiqueta oculta
       fuera del viewport no le sirve de nada y el color seguiría siendo el
       único portador para él. Por eso el texto va al lado del punto, y el punto
       conserva su color como refuerzo. La etiqueta se pinta con
       `--muni-muted` sobre `--muni-surface-3` —los dos tokens tienen rama clara
       y oscura y ese par ya está medido— en vez de con el color del estado: un
       texto por tono obligaría a auditar seis pares nuevos en dos temas para no
       ganar nada, porque la información ya la lleva la palabra.

    2. La etiqueta solo se emite si el ítem DECLARA estado (`tone` o
       `toneLabel`). Un ítem sin tono no comunica ningún estado —el punto sale
       en el color de acento por defecto, que es decorativo—, así que
       etiquetarlo sería inventar información y además cambiaría lo que hoy
       renderizan las llamadas existentes.

    3. `datetime` NO es una mejora de accesibilidad: ningún lector de pantalla
       anuncia ese atributo. Vale para trazabilidad y para que una exportación
       ordene por fecha real y no por una cadena en español. El texto visible lo
       sigue mandando `time`, sin tocar.

    4. `detail` recibe un STRING ya redactado. No existe —ni se acepta— una
       clave que reciba el registro, el modelo o el array de cambios: la
       minimización de la Ley 21.719 se rompe sola en cuanto el componente
       invita a volcar el objeto entero, y en discapacidad ese objeto trae
       diagnósticos y en licencias, psicotécnicos. Además arranca COLAPSADO:
       lo que se ve por defecto en pantalla es lo mínimo, y abrirlo es un acto
       deliberado de quien atiende.
--}}

@php
    $etiquetasDeTono = [
        'ok' => 'Conforme',
        'warn' => 'Con observación',
        'danger' => 'Rechazado',
        'info' => 'Informativo',
        'accent' => 'En curso',
        'muted' => 'Sin efecto',
    ];

    // El hito vigente es uno solo: dos `aria-current="step"` describen un árbol
    // de accesibilidad que miente sobre en qué paso va el trámite.
    $vigenteYaMarcado = false;
@endphp

<ol {{ $attributes->merge(['class' => 'muni-timeline']) }}>
    @foreach ($items as $item)
        @php
            $tone = $item['tone'] ?? 'accent';
            $color = [
                'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)', 'warn' => 'var(--muni-warn-fg)',
                'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)', 'muted' => 'var(--muni-border-2)',
            ][$tone] ?? 'var(--muni-accent)';

            $declaraEstado = array_key_exists('tone', $item) || array_key_exists('toneLabel', $item);
            $etiquetaTono = $item['toneLabel'] ?? ($etiquetasDeTono[$tone] ?? null);

            $esVigente = ! $vigenteYaMarcado && ! empty($item['current']);
            $vigenteYaMarcado = $vigenteYaMarcado || $esVigente;

            $textoHora = $item['time'] ?? $item['datetime'] ?? null;
        @endphp
        <li class="muni-tl__item" @if ($esVigente) aria-current="step" @endif>
            <span class="muni-tl__dot" aria-hidden="true" style="--dot:{{ $color }};"></span>
            <div class="muni-tl__content">
                <div class="muni-tl__head">
                    @if ($declaraEstado && filled($etiquetaTono))<span class="muni-tl__tone">{{ $etiquetaTono }}</span>@endif
                    <span class="muni-tl__title">{{ $item['title'] ?? '' }}</span>
                    @if (filled($textoHora))<time class="muni-tl__time" @if (! empty($item['datetime'])) datetime="{{ $item['datetime'] }}" @endif>{{ $textoHora }}</time>@endif
                </div>
                @if (! empty($item['description']))<p class="muni-tl__desc">{{ $item['description'] }}</p>@endif
                @if (! empty($item['actor']))<p class="muni-tl__actor">Registrado por {{ $item['actor'] }}</p>@endif
                @if (! empty($item['detail']))
                    <details class="muni-tl__detail">
                        <summary class="muni-tl__summary">{{ $item['detailLabel'] ?? 'Ver el detalle del cambio' }}</summary>
                        <p class="muni-tl__diff">{{ $item['detail'] }}</p>
                    </details>
                @endif
            </div>
        </li>
    @endforeach
</ol>

@once
    <style>
        .muni-timeline { list-style:none; margin:0; padding:0; font-family:var(--muni-font-sans); }
        .muni-tl__item { position:relative; display:flex; gap:14px; padding-bottom:18px; }
        .muni-tl__item:not(:last-child)::before { content:""; position:absolute; left:6px; top:16px; bottom:0; width:2px; background:var(--muni-border); }
        .muni-tl__dot { flex-shrink:0; width:14px; height:14px; margin-top:3px; border-radius:50%; background:var(--muni-surface); border:2px solid var(--dot); box-shadow:0 0 0 3px color-mix(in srgb,var(--dot) 15%,transparent),var(--muni-glow); z-index:1; }
        .muni-tl__content { min-width:0; padding-bottom:2px; }
        .muni-tl__head { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
        .muni-tl__title { font-size:13.5px; font-weight:600; color:var(--muni-text); }
        .muni-tl__time { font-family:var(--muni-font-mono); font-size:11px; color:var(--muni-hint); }
        .muni-tl__desc { margin:3px 0 0; font-size:12.5px; color:var(--muni-muted); line-height:1.5; }
        /* El estado en palabras. Va antes del título para leerse junto al punto,
           y su color sale de tokens con las dos ramas de tema. */
        .muni-tl__tone { flex-shrink:0; padding:1px 7px; border-radius:999px; font-family:var(--muni-font-mono); font-size:10.5px; font-weight:600; letter-spacing:.02em; line-height:1.5; color:var(--muni-muted); background:var(--muni-surface-3); border:1px solid var(--muni-border); }
        .muni-tl__actor { margin:3px 0 0; font-size:12px; color:var(--muni-hint); }
        /* El hito vigente ya se anuncia con aria-current; acá solo se hace ver,
           y con peso y anillo, no con un color nuevo. */
        .muni-tl__item[aria-current="step"] .muni-tl__title { font-weight:700; }
        .muni-tl__item[aria-current="step"] .muni-tl__dot { background:var(--dot); box-shadow:0 0 0 4px color-mix(in srgb,var(--dot) 28%,transparent),var(--muni-glow); }
        .muni-tl__detail { margin:5px 0 0; }
        .muni-tl__summary { display:inline-block; min-height:24px; padding:3px 7px; margin-left:-7px; border-radius:var(--muni-radius-sm); font-size:12px; font-weight:600; color:var(--muni-accent-strong); cursor:pointer; transition:background var(--muni-dur) var(--muni-ease); }
        .muni-tl__summary:hover { background:var(--muni-surface-3); }
        /* Dentro de Filament la cadena de sombras de Tailwind se come cualquier
           box-shadow: el indicador de foco es un outline y nada más. */
        .muni-tl__summary:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        .muni-tl__diff { margin:5px 0 0; padding:7px 9px; border-radius:var(--muni-radius-sm); background:var(--muni-surface-2); border:1px solid var(--muni-border); font-family:var(--muni-font-mono); font-size:11.5px; color:var(--muni-muted); line-height:1.55; white-space:pre-wrap; overflow-wrap:anywhere; }
    </style>
@endonce
