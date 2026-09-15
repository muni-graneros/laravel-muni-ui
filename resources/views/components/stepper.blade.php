@props([
    'steps' => [],
    'current' => 0,
    'orientation' => 'horizontal',
    'navName' => null,
    'label' => null,
])

{{-- El indicador de pasos de un trámite. Nació como ilustración —una lista y nada
     más— y la ficha «asistente» de docs/GAP-ANALYSIS.md pedía tres cosas que le
     faltaban: estado de paso EN ERROR y OMITIDO como estados de primera clase,
     navegación de verdad, y que ninguno de los dos dependa del color.

     Lo que se conserva intacto, porque nueve sistemas ya lo usan: las props
     `steps`, `current` y `orientation`, el `<ol>`/`<li>`, el `aria-current="step"`
     y la marca de verificación del paso cumplido. Todo lo nuevo es aditivo.

     Cuatro decisiones:

     1. EL ESTADO VA EN PALABRAS SIEMPRE, y a la VISTA en los dos estados que son
        una excepción —«Con errores» y «Omitido»—. En los otros tres la palabra
        existe igual, oculta a la vista: el color no puede ser el único portador
        (WCAG 2.2 AA 1.4.1), y un funcionario que no distingue el rojo del gris
        tiene que poder ver qué paso quedó mal. La forma también distingue: círculo
        relleno con marca cuando está cumplido, aspa cuando hay error, borde
        discontinuo y guion cuando se omitió.
     2. LA NAVEGACIÓN ES HTML, NO JS. Un paso con `href` es un `<a>`; con `navName`
        es un `<button type="submit">` con el índice en `value`, que funciona sin
        una línea de JavaScript y también dentro de Livewire. Lleva `formnovalidate`
        a propósito: volver al paso 1 no puede quedar bloqueado porque el paso 3
        tenga un campo obligatorio vacío —sería una trampa de teclado con otro
        nombre—. QUIÉN PUEDE SALTAR A QUÉ PASO LO DECIDE EL SERVIDOR: el componente
        solo ofrece el control, y el anfitrión marca `'disabled' => true` en los
        pasos a los que todavía no se puede ir.
     3. EL PASO ACTUAL NUNCA ES UN CONTROL. Un botón que lleva a donde ya estás es
        una parada de tabulación que no hace nada.
     4. EL ÁREA TÁCTIL del control llega a 44 px de alto (mesón municipal y vecino
        mayor, no 24). Y la línea que une los marcadores deja de ser absoluta: era
        un defecto que venía de antes —arrancaba justo donde empieza el rótulo y
        tachaba todo paso de una sola línea—, y con el alto variable del control
        empeoraba. Ahora es un ítem flexible que vive DESPUÉS del texto. --}}

@php
    // $steps: array de strings o de ['label'=>, 'hint'=>?, 'state'=>?, 'href'=>?,
    // 'attrs'=>?, 'disabled'=>?]. $current = índice activo (0-based).
    $current = (int) $current;
    $vertical = $orientation === 'vertical';
    $navNombre = trim((string) $navName);

    $textoDeEstado = [
        'done' => 'Completado',
        'active' => 'Paso actual',
        'todo' => 'Pendiente',
        'error' => 'Con errores',
        'skipped' => 'Omitido',
    ];

    /*
     * Los atributos extra de un paso (el hueco para `wire:click` y compañía) se
     * rinden con ComponentAttributeBag, que escapa los valores. Las CLAVES se
     * filtran acá: un nombre de atributo con comillas o espacios cerraría la
     * etiqueta y dejaría inyectar marcado. Vienen del anfitrión y no del vecino,
     * pero un componente del paquete no confía en eso.
     */
    $atributosDePaso = function (array $extra): \Illuminate\View\ComponentAttributeBag {
        $limpios = [];

        foreach ($extra as $clave => $valor) {
            if (! is_string($clave) || ! preg_match('/^[A-Za-z_:@][A-Za-z0-9_:@.\-]*$/', $clave)) {
                continue;
            }

            // Fuera los manejadores en línea (`onclick`…) y lo que el control ya
            // escribe: un `type` repetido no pisa al primero, el parser HTML se
            // queda con el primero. El `type` se lee aparte.
            if (preg_match('/^on/i', $clave) || in_array(strtolower($clave), ['type', 'class', 'name', 'value', 'formnovalidate', 'href'], true)) {
                continue;
            }

            $limpios[$clave] = $valor;
        }

        return new \Illuminate\View\ComponentAttributeBag($limpios);
    };
@endphp

<ol {{ $attributes->merge(array_filter(['class' => trim('muni-stepper '.($vertical ? 'muni-stepper--v' : '')), 'aria-label' => trim((string) $label)])) }}>
    @foreach ($steps as $i => $step)
        @php
            $paso = is_array($step) ? $step : ['label' => $step];
            $etiqueta = (string) ($paso['label'] ?? '');
            $ayuda = $paso['hint'] ?? null;

            // El estado declarado manda; si no viene, se deduce del índice como
            // siempre. Así un paso que quedó con errores lo dice aunque esté atrás.
            $estado = (string) ($paso['state'] ?? '');

            if (! array_key_exists($estado, $textoDeEstado)) {
                $estado = $i < $current ? 'done' : ($i === $current ? 'active' : 'todo');
            }

            $esActual = $i === $current;
            $destino = trim((string) ($paso['href'] ?? ''));

            // Un `href` con esquema ejecutable no lleva a ningún paso.
            if (preg_match('/^[\s\x00-\x1f]*(javascript|vbscript|data):/i', $destino)) {
                $destino = '';
            }
            $bloqueado = (bool) ($paso['disabled'] ?? false);
            $extra = is_array($paso['attrs'] ?? null) ? $paso['attrs'] : [];
            // `'type' => 'button'` es la receta de Livewire con `wire:click`.
            $tipo = strtolower(trim((string) ($extra['type'] ?? ''))) === 'button' ? 'button' : 'submit';

            // El paso actual no es control, y un paso bloqueado tampoco: el salto
            // lo autoriza el servidor, no el indicador.
            $control = null;

            if (! $esActual && ! $bloqueado) {
                $control = $destino !== '' ? 'a' : ($navNombre !== '' || $extra !== [] ? 'button' : null);
            }

            // El estado se ve en los dos que son excepción; en los demás va en el
            // árbol de accesibilidad y no a la vista.
            $estadoVisible = in_array($estado, ['error', 'skipped'], true);
        @endphp
        <li class="muni-step muni-step--{{ $estado }}"@if ($esActual) aria-current="step"@endif>
            @if ($control === 'a')
                <a href="{{ $destino }}" class="muni-step__ctrl" {{ $atributosDePaso($extra) }}>
            @elseif ($control === 'button')
                <button type="{{ $tipo }}" class="muni-step__ctrl" formnovalidate
                    @if ($navNombre !== '') name="{{ $navNombre }}" value="{{ $i }}" @endif
                    {{ $atributosDePaso($extra) }}>
            @endif

            {{-- El marcador es refuerzo visual: el número y la marca no aportan
                 nada que el rótulo y la palabra de estado no digan ya. SALVO dentro
                 de un control: ahí el número es texto VISIBLE del control —y en el
                 teléfono del asistente es el ÚNICO visible, porque el rótulo queda
                 oculto—, y el nombre accesible tiene que contenerlo para que el
                 control por voz lo encuentre (WCAG 2.2 AA 2.5.3). Las marcas
                 dibujadas siguen fuera del árbol en los dos casos. --}}
            <span class="muni-step__marker"@if ($control === null) aria-hidden="true"@endif>
                @if ($estado === 'done')
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M3.5 8.5l3 3 6-6.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @elseif ($estado === 'error')
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M4.5 4.5l7 7M11.5 4.5l-7 7" stroke-linecap="round"/></svg>
                @elseif ($estado === 'skipped')
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M4 8h8" stroke-linecap="round"/></svg>
                @else
                    <span class="muni-step__num">{{ $i + 1 }}</span>
                @endif
            </span>
            <span class="muni-step__body">
                <span class="muni-step__label">{{ $etiqueta }}</span>
                <span class="{{ $estadoVisible ? 'muni-step__estado' : 'muni-step__estado muni-step__sr' }}">{{ $textoDeEstado[$estado] }}</span>
                @if ($ayuda)<span class="muni-step__hint">{{ $ayuda }}</span>@endif
            </span>

            @if ($control === 'a')
                </a>
            @elseif ($control === 'button')
                </button>
            @endif
        </li>
    @endforeach
</ol>

@once
    <style>
        .muni-stepper { list-style:none; margin:0; padding:0; display:flex; gap:0; font-family:var(--muni-font-sans); }
        .muni-step { flex:1; display:flex; align-items:center; gap:10px; position:relative; min-width:0; }
        /* La línea es un ítem flexible MÁS del paso, que ocupa el espacio que queda
           DESPUÉS del rótulo. Antes era absoluta y arrancaba a 40 px, justo donde
           empieza el rótulo: todo paso de una sola línea salía tachado por ella, y
           con el control de 44 px o dos líneas de ayuda cruzaba además la ayuda.
           Como ítem flexible no puede pisar el texto: si falta ancho, el rótulo se
           recorta con puntos suspensivos antes que la línea baje de 16 px. */
        .muni-step:not(:last-child)::after { content:""; flex:1 1 16px; min-width:16px; height:2px; margin-right:10px; background:var(--muni-border); }
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

        /* Los dos estados que la ficha pedía como ciudadanos de primera clase. El
           color no los distingue solo: el aspa, el guion y el borde discontinuo son
           forma, y la palabra de al lado está A LA VISTA en ambos. */
        .muni-step--error .muni-step__marker { border-color:var(--muni-danger-fg); background:var(--muni-danger-bg); color:var(--muni-danger-fg); }
        .muni-step--error .muni-step__label { color:var(--muni-text); }
        .muni-step--skipped .muni-step__marker { border-style:dashed; border-color:var(--muni-border-2); color:var(--muni-muted); }

        .muni-step__estado { font-size:10.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--muni-muted); }
        .muni-step--error .muni-step__estado { color:var(--muni-danger-fg); }
        /* Oculto a la vista, presente en el árbol de accesibilidad. Viaja DENTRO
           del bloque del componente y no en la hoja: dentro de un panel Filament
           solo se inyecta muni-ui-filament.css (DESIGN §7). */
        .muni-step__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }

        /* El paso navegable: un <a> o un <button> de verdad, con área táctil de
           mesón y el outline como indicador real de foco (DESIGN §5: la sombra se
           computa transparente dentro de Filament). */
        .muni-step__ctrl { box-sizing:border-box; flex:0 1 auto; display:flex; align-items:center; gap:10px; min-width:0; min-height:44px;
            padding:2px 6px; margin:0 -6px; background:none; border:0; border-radius:var(--muni-radius-sm);
            font-family:inherit; font-size:inherit; color:inherit; text-align:left; text-decoration:none; cursor:pointer;
            transition:background var(--muni-dur) var(--muni-ease); }
        .muni-step__ctrl:hover { background:var(--muni-surface-2); }
        .muni-step__ctrl:hover .muni-step__label { color:var(--muni-text); }
        .muni-step__ctrl:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }

        .muni-stepper--v { flex-direction:column; gap:4px; }
        .muni-stepper--v .muni-step { flex:none; align-items:flex-start; padding-bottom:14px; }
        .muni-stepper--v .muni-step:not(:last-child)::after { position:absolute; left:13px; top:28px; bottom:0; width:2px; height:auto; min-width:0; margin:0; }
        .muni-stepper--v .muni-step__body { padding-top:4px; }
        .muni-stepper--v .muni-step__ctrl { align-items:flex-start; }

        @media (prefers-reduced-motion:reduce) {
            .muni-step__marker, .muni-step__ctrl { transition:none; }
        }
    </style>
@endonce
