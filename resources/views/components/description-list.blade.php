@props([
    /*
     * Nombre accesible del bloque, opcional. Una ficha suele traer dos o tres
     * listas seguidas («datos del titular», «datos del vehículo»): sin nombre
     * el lector anuncia listas idénticas y el funcionario no sabe en cuál está.
     * Si no viene, NO se emite un aria-label vacío, que borraría el contenido.
     */
    'label' => null,
    /*
     * Fuerza la etiqueta SOBRE el valor en todos los anchos. El paso a dos
     * columnas va por media query, que mira el viewport: dentro de un drawer de
     * 360 px abierto en una pantalla de 1440 el media query miente y las dos
     * columnas no caben. Esta es la salida, y es una prop y no una consulta de
     * contenedor a propósito.
     */
    'stacked' => false,
])

@php
    $muniDlApilada = filter_var($stacked, FILTER_VALIDATE_BOOLEAN);
    $muniDlNombre = trim((string) ($label ?? ''));

    $muniDlAtributos = ['class' => 'muni-dl'.($muniDlApilada ? '' : ' muni-dl--dos')];

    /* Condicional por merge y no escrito a mano antes del derrame: un
       aria-label del consumidor tiene que ganar, no duplicarse (DESIGN §8). */
    if ($muniDlNombre !== '') {
        $muniDlAtributos['aria-label'] = $muniDlNombre;
    }
@endphp

{{-- LISTA DE PARES DATO-VALOR — el bloque de datos de una ficha con la semántica
     que le corresponde: <dl> con <dt>/<dd>. Antes de esto no había un solo <dl>
     en el paquete y las fichas se armaban con <div> sueltos, que no le dicen al
     lector de pantalla qué valor pertenece a qué etiqueta.

     El slot son los <x-muni::description-item>, y nada más: el <dl> solo admite
     pares dt/dd como HIJOS DIRECTOS, porque la rejilla de dos columnas se monta
     sobre el propio <dl>. Un <div> por par alinearía cada par por su cuenta y
     las etiquetas dejarían de formar columna.

     Lo que este componente NO es: la jerarquía «principales / secundarios /
     adjuntos» de una ficha no cabe en un <dl>. El marco y el pie de acciones los
     da <x-muni::card>, que ya tiene slot `actions`; los adjuntos son otra pieza. --}}
<dl {{ $attributes->merge($muniDlAtributos) }}>{{ $slot }}</dl>

@once
    <style>
        /* Rejilla sobre el propio <dl>: una columna por defecto, dos a partir de
           40em. dt y dd son hijos directos, así que la etiqueta de cada par cae
           siempre en la misma columna y el par sigue siendo un par para el lector. */
        /* `.muni-num` viaja CON el componente y no se hereda del bloque de la
           tabla: aquel se emite una sola vez y, en un drawer de ficha sin tabla
           en pantalla, no se emite nunca — el RUT saldría en sans proporcional
           dentro del panel, que es justo el defecto que este componente existe
           para evitar (DESIGN §7 y §9). La declaración es idéntica a la de la
           tabla, así que el orden de emisión da igual.
           Va aquí y no en el par porque el par se renderiza DENTRO del <dl>, y
           el modelo de contenido de <dl> no admite un <style> suelto entre los
           pares: sería HTML inválido y una violación de la regla
           `definition-list` de axe. */
        .muni-num { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; }
        .muni-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }

        .muni-dl { display:grid; grid-template-columns:1fr; margin:0; font-family:var(--muni-font-sans); }
        .muni-dl > dt { margin:0; padding:10px 0 0; font-size:12px; font-weight:600; line-height:1.35; color:var(--muni-muted); }
        .muni-dl > dd { margin:0; padding:2px 0 10px; font-size:14px; line-height:1.45; color:var(--muni-text); border-bottom:1px solid var(--muni-border); overflow-wrap:break-word; min-width:0; }
        .muni-dl > dd:last-child { border-bottom:0; padding-bottom:0; }

        /* Etiqueta a la izquierda y valor a la derecha. El ancho de la etiqueta
           es elástico entre dos topes: fijo, un rótulo largo se corta; libre, la
           columna baila de ficha en ficha. */
        @media (min-width:40em) {
            .muni-dl--dos { grid-template-columns:minmax(8rem,15rem) 1fr; }
            /* El aire entre columnas va como relleno del <dt> y no como
               `column-gap`: con gap, la línea que separa un par del siguiente se
               corta en el hueco y la ficha sale con la regla partida en dos. */
            .muni-dl--dos > dt { padding:10px 18px 10px 0; border-bottom:1px solid var(--muni-border); }
            .muni-dl--dos > dd { padding:10px 0; }
            .muni-dl--dos > dt:nth-last-child(2) { border-bottom:0; padding-bottom:0; }
            .muni-dl--dos > dd:last-child { padding-bottom:0; }
        }

        /* Jurídica imprime el bloque «datos del titular» del panel ARCOP. Un par
           partido deja la etiqueta en una hoja y el valor en la siguiente, y en
           papel no hay forma de volver a juntarlos. `break-inside` por sí solo no
           basta: el corte que importa cae ENTRE el <dt> y su <dd>, y eso lo
           impide `break-after` sobre la etiqueta. Los alias `page-break-*` van
           porque Firefox todavía no atiende `break-after` en todos los casos.
           Acotado al par: las reglas base del documento son de los dos CSS del
           paquete, no de un componente. */
        @media print {
            .muni-dl > dt { break-inside:avoid; break-after:avoid; page-break-inside:avoid; page-break-after:avoid; }
            .muni-dl > dd { break-inside:avoid; page-break-inside:avoid; }
        }
    </style>
@endonce
