@props([
    'tone' => 'info', // info | ok | warn | danger
    'title' => null, // título opcional en negrita
    'icon' => null, // nombre de glifo del catálogo (no HTML); o slot icon
    'role' => null, // status | alert | note; sin él, no anuncia
])

{{-- Recuadro de aviso fijo en uno de cuatro tonos (ok, warn, danger, info), con título
     opcional y un glifo propio por tono. Es contenido: por sí solo no anuncia nada.

     EL ROL LO DECIDE EL CONSUMIDOR, NO EL TONO. Hasta 0.18 `danger` salía con
     role="alert" y todo lo demás con role="status", y el atributo iba escrito
     fuera de `$attributes`: una nota permanente («Adjunte fotocopia de la cédula
     por ambos lados») quedaba declarada como mensaje de estado, tres notas en una
     página se anunciaban las tres al entrar, y el consumidor que pasaba su propio
     `role` obtenía dos atributos y perdía.

     Sin `role` el recuadro es contenido y nada más. Para un mensaje que de verdad
     hay que anunciar, el consumidor declara `role="status"` (cortés) o
     `role="alert"` (interrumpe), y el navegador ya implica el aria-live y el
     aria-atomic de cada uno. `role="note"` vale para una nota aparte.

     Es un cambio de comportamiento respecto de 0.18: el sistema que dependía del
     anuncio implícito tiene que pasar el `role` a mano.

     La primera frase de este comentario es la descripción que `npm run registro`
     escribe en registry.json; los tokens los saca por grep del archivo entero,
     comentarios incluidos, así que aquí no se nombra ningún token que el
     componente no use. --}}

@php
    $tonos = [
        'ok' => ['fg' => 'var(--muni-ok-fg)', 'bg' => 'var(--muni-ok-bg)', 'border' => 'var(--muni-ok-border)'],
        'warn' => ['fg' => 'var(--muni-warn-fg)', 'bg' => 'var(--muni-warn-bg)', 'border' => 'var(--muni-warn-border)'],
        'danger' => ['fg' => 'var(--muni-danger-fg)', 'bg' => 'var(--muni-danger-bg)', 'border' => 'var(--muni-danger-border)'],
        'info' => ['fg' => 'var(--muni-info-fg)', 'bg' => 'var(--muni-info-bg)', 'border' => 'var(--muni-info-border)'],
    ];
    $tone = is_string($tone) ? $tone : 'info';
    $t = $tonos[$tone] ?? $tonos['info'];

    /*
     * UN DIBUJO POR TONO, para que el estado no quede comunicado solo con el
     * color (WCAG 2.2 AA 1.4.1): círculo con visto (ok), triángulo (warn),
     * octógono con cruz (danger) y círculo con «i» (info). Todos a trazo, con
     * `currentColor`, así heredan el `fg` del tono que ya está medido contra su
     * fondo. El contenedor va aria-hidden: el título y el cuerpo ya dicen lo que
     * el dibujo repite.
     *
     * `icon` acepta un NOMBRE de este catálogo, nunca HTML: lo que llegue como
     * texto y no sea un nombre cae en el glifo del tono, sin imprimirse. Un SVG
     * propio va por el slot `icon`, que es marcado escrito por el desarrollador
     * en su plantilla, con la misma confianza que el cuerpo de la alerta. Por
     * eso el paquete jamás recibe por aquí un dato del vecino: la prop no se
     * imprime y el slot no admite datos, solo plantilla.
     */
    $glifos = [
        'ok' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><circle cx="10" cy="10" r="7.5"/><path d="M6.6 10.3l2.3 2.3 4.6-4.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'warn' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><path d="M10 3.2L18 17H2L10 3.2z" stroke-linejoin="round"/><path d="M10 8.2v4M10 14.4v.2" stroke-linecap="round"/></svg>',
        'danger' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><path d="M6.7 2.5h6.6l4.2 4.2v6.6l-4.2 4.2H6.7l-4.2-4.2V6.7z" stroke-linejoin="round"/><path d="M7.5 7.5l5 5M12.5 7.5l-5 5" stroke-linecap="round"/></svg>',
        'info' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><circle cx="10" cy="10" r="7.5"/><path d="M10 9.2v4.6M10 6.4v.2" stroke-linecap="round"/></svg>',
    ];

    // Sin `icon`, el dibujo es el del tono; un tono desconocido cae en info
    // también en el dibujo, no solo en el color.
    $glifo = $glifos[$tone] ?? $glifos['info'];

    if (isset($icon)) {
        if ($icon instanceof \Illuminate\View\ComponentSlot) {
            // Slot: marcado del desarrollador. En blanco (una condición que no se
            // cumplió) cae en el glifo del tono: sin dibujo, el estado vuelve a
            // quedar comunicado solo por el color.
            $glifo = $icon->isNotEmpty() ? $icon->toHtml() : $glifo;
        } elseif (is_string($icon)) {
            // Prop de texto: solo un nombre del catálogo. Cualquier otra cosa,
            // HTML incluido, cae en el glifo del tono sin imprimirse jamás.
            $glifo = $glifos[$icon] ?? $glifo;
        }
    }
@endphp

<div
    {{ $attributes->merge(array_filter([
        'role' => is_string($role) && $role !== '' ? $role : null,
        'style' => "display:flex;gap:11px;padding:12px 14px;border-radius:var(--muni-radius);"
            ."background:{$t['bg']};border:1px solid {$t['border']};"
            ."border-left-width:3px;color:var(--muni-text);font-family:var(--muni-font-sans);font-size:13px;line-height:1.5;",
    ])) }}
>
    {{-- El glifo viene del catálogo de arriba o del slot `icon`: nunca de una prop de texto. --}}
    <span class="muni-alert__icono" aria-hidden="true" style="flex-shrink:0;width:16px;height:16px;margin-top:1px;color:{{ $t['fg'] }};">{!! $glifo !!}</span>
    {{-- EL CUERPO VA CON EL `fg` DEL TONO, NO CON EL GRIS APAGADO DEL TEMA.

         El token `muted` está calibrado contra las SUPERFICIES del tema
         (surface / bg / surface-3), no contra los fondos de estado. Medido
         sobre ellos daba 4,15 a 4,53:1 según el tono, la hoja y el tema: por
         debajo del 4,5:1 que 1.4.3 exige para texto normal. El par
         `{tono}-fg` sobre `{tono}-bg` sí está medido, en las dos hojas y en
         los dos temas, y es el que ya usaba el título. Se reutiliza ese en vez
         de inventar un token nuevo para el cuerpo: cumpliría lo mismo y
         obligaría a tocar las dos hojas.

         El tono NO queda comunicado solo por ese color: lo dicen también el
         glifo propio de cada tono y el borde izquierdo de 3px.

         Las clases son el punto de anclaje de las pruebas que miden el
         contraste de los cinco tonos y el glifo: no llevan reglas propias
         porque el color depende del tono y tiene que viajar en línea. --}}
    <div style="min-width:0;">
        @if ($title)<div class="muni-alert__titulo" style="font-weight:700;color:{{ $t['fg'] }};margin-bottom:2px;">{{ $title }}</div>@endif
        <div class="muni-alert__cuerpo" style="color:{{ $t['fg'] }};">{{ $slot }}</div>
    </div>
</div>
