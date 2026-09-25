@props([
    'title' => null,
    'level' => 2,
    'total' => null,
    'filtered' => null,
    'unit' => 'resultados',
    'countLabel' => null,
])

{{-- La franja entre el <h1> de la pantalla y la tabla: de qué sección es, cuántos
     resultados hay EN TEXTO, y dónde van el buscador, los filtros y las acciones
     del consumidor. Es composición pura: no dibuja tabla, así que sirve igual sobre
     <x-muni::sortable-table> que sobre <x-muni::data-table>.

     Lo que NO hace, y es deliberado:

     · No declara role="toolbar". Solo correspondería si implementara el foco móvil
       entre los controles, y no puede: la franja contiene un campo de texto, y
       dentro de un input las flechas, Inicio y Fin le pertenecen al cursor. APG lo
       advierte explícitamente. Un rol que promete un widget que no existe es peor
       que ningún rol: el orden de Tab es el nativo y los controles son <button>
       reales del consumidor.
     · No es un <form>. `filter-bar` ya es un <form method=get> y las acciones en
       lote son POST; los formularios no se anidan. Si hace falta enviar desde acá,
       el botón usa el atributo `form=` apuntando a un <form> hermano.
     · No es dueño de la selección de filas ni del estado del filtro: recibe las
       cifras ya calculadas y las pinta. Sincronizar estado con filas que no
       renderiza se rompe en cada refresco de Livewire. --}}

@php
    // El <h1> es de page-header: dos en una página rompen el esquema de encabezados.
    $headingTag = 'h'.min(6, max(2, (int) $level));

    $formatNumber = fn ($n) => number_format((float) $n, 0, ',', '.');

    $totalCount = $total === null || $total === '' ? null : (int) $total;
    $filteredCount = $filtered === null || $filtered === '' ? null : (int) $filtered;
    $unitLabel = trim((string) $unit);

    $count = $countLabel;

    if ($count === null && ($totalCount !== null || $filteredCount !== null)) {
        $count = $totalCount !== null && $filteredCount !== null && $filteredCount !== $totalCount
            // «120 de 3.412 patentes»: sin el total, el funcionario no sabe si el
            // sistema perdió registros o si es su propio filtro el que estrecha.
            ? $formatNumber($filteredCount).' de '.$formatNumber($totalCount)
            // Y con el filtro puesto en nada, «3.412 de 3.412» es solo ruido.
            : $formatNumber($totalCount ?? $filteredCount);

        $count = trim($count.' '.$unitLabel);
    }
@endphp

<div {{ $attributes->merge(['class' => 'muni-th']) }}>
    <div class="muni-th__lead">
        @if ($title)
            <{{ $headingTag }} class="muni-th__title">{{ $title }}</{{ $headingTag }}>
        @endif

        {{-- La región viva se emite SIEMPRE, vacía mientras no haya cifras. Una
             región que nace en el mismo repintado que trae su texto no se anuncia
             de forma fiable en NVDA, JAWS ni VoiceOver; esta ya está montada desde
             la carga inicial, así que el paso de 3.412 a 120 sí se anuncia
             (WCAG 2.2 AA 4.1.3). --}}
        <p class="muni-th__count" role="status" aria-live="polite">{{ $count }}</p>
    </div>

    @isset($search)
        <div class="muni-th__search">{{ $search }}</div>
    @endisset

    @isset($filters)
        <div class="muni-th__filters">{{ $filters }}</div>
    @endisset

    @isset($actions)
        <div class="muni-th__actions">{{ $actions }}</div>
    @endisset

    {{-- El slot por defecto es la fila de abajo: los chips de «filtros aplicados»
         o la barra de lote. Se rinde de verdad; un slot que se descarta en silencio
         es el defecto que empty-state arrastró hasta hoy. --}}
    @if (trim($slot) !== '')
        <div class="muni-th__extra">{{ $slot }}</div>
    @endif
</div>

@once
    <style>
        /* Viaja con el componente y no en muni-ui.css: dentro de un panel Filament
           solo se inyecta vendor/muni-ui/filament.css, así que una clase declarada
           únicamente allá deja la franja sin estilo y sin un error en consola. */
        .muni-th { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; margin:0 0 14px; padding:0 0 12px; border-bottom:1px solid var(--muni-border); font-family:var(--muni-font-sans); }
        /* min-width:0 en cada ítem flexible: el tamaño mínimo automático de un ítem
           flex es su contenido, y sin esto un buscador largo empuja la franja y
           aparece el desplazamiento horizontal. */
        .muni-th__lead { display:flex; flex-direction:column; gap:2px; flex:1 1 200px; min-width:0; }
        .muni-th__title { margin:0; font-size:15px; font-weight:700; line-height:1.25; color:var(--muni-text); }
        /* El contador es texto, no solo la altura de la tabla: tabular-nums para que
           la cifra no baile mientras se filtra. */
        /* Sin `:empty { display:none }`: display:none sacaría la región viva del
           árbol de accesibilidad justo mientras está vacía, que es cuando tiene que
           estar montada esperando el primer filtro. Vacía no ocupa alto. */
        .muni-th__count { margin:0; font-size:12.5px; color:var(--muni-muted); font-variant-numeric:tabular-nums; }
        .muni-th__search { flex:1 1 240px; min-width:0; max-width:100%; }
        .muni-th__filters { display:flex; flex-wrap:wrap; align-items:center; gap:8px; min-width:0; }
        .muni-th__actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; min-width:0; margin-left:auto; }
        .muni-th__extra { flex:1 1 100%; display:flex; flex-wrap:wrap; align-items:center; gap:8px; min-width:0; }
        /* En teléfono cada bloque toma su propia línea: apilar es preferible a
           encoger los controles por debajo del área táctil. */
        @media (max-width: 640px) {
            .muni-th__search, .muni-th__filters, .muni-th__actions { flex:1 1 100%; margin-left:0; }
        }
    </style>
@endonce
