@props([
    /*
     * La lista de cambios, YA REDACTADA POR EL HOST. Cada ítem es un array:
     *
     *   ['field'  => 'domicilio',        // nombre de máquina, obligatorio
     *    'label'  => 'Domicilio',        // opcional; si falta se usa `field`
     *    'before' => 'Calle Uno 100',    // opcional, string ya redactado
     *    'after'  => 'Calle Dos 200',    // opcional, string ya redactado
     *    'state'  => 'modificado',       // opcional; se deriva si no viene
     *    'mono'   => true]               // opcional; cifras, RUT y fechas
     */
    'changes' => [],
    /* Nombre accesible de la tabla. Va en el <caption> y se ve. */
    'caption' => 'Cambios por campo',
    /* Qué decir cuando no hay ni un cambio que mostrar. */
    'empty' => 'Sin cambios registrados.',
    'fieldHeading' => 'Campo',
    'stateHeading' => 'Estado',
    'beforeHeading' => 'Antes',
    'afterHeading' => 'Después',
    /* Texto del hueco: lo que se lee cuando un lado del cambio no tiene valor. */
    'nilLabel' => 'sin valor',
])

{{-- Tabla antes/después por campo: qué cambió, de qué a qué, y si fue un alta,
     una modificación o una supresión.

     LO QUE ESTE COMPONENTE NO ACEPTA, Y ES LA REGLA MÁS IMPORTANTE DE TODAS.
     `changes` son STRINGS YA REDACTADOS POR EL SISTEMA ANFITRIÓN. No se le pasa
     un modelo Eloquent, ni el registro completo, ni un array asociativo crudo
     del que el componente saque todas las claves con un bucle.

     El porqué es la Ley 21.719 (minimización): esta pieza se usa en la bitácora
     de accesos a la ficha de una persona con discapacidad —dato de salud—, en el
     historial de una licencia y en la resolución de una rectificación ARCOP. Si
     recibiera el registro entero, el componente decidiría qué campos se publican
     y publicaría TODOS: RUN, domicilio, teléfono y diagnóstico viajarían al DOM y
     a la caché del navegador de cualquiera que abra la pantalla, aunque el
     cambio auditado fuese el número de teléfono. Quién puede ver qué campo, qué
     se pseudonimiza y qué se enmascara es una decisión con base de licitud, y esa
     decisión vive en el host, que tiene el permiso, el usuario y el registro de
     accesos. El paquete solo sabe pintar. Por eso un ítem que no sea un array se
     DESCARTA en vez de convertirse a array: fallar cerrado es preferible a volcar
     atributos que nadie pidió.

     EL ESTADO NO SE COMUNICA CON COLOR (WCAG 2.2 AA 1.4.1). Va por tres vías a la
     vez: la palabra en la columna «Estado», el marcado semántico <ins>/<del>
     —con su subrayado y su tachado, que el bloque de estilos refuerza para que un
     reset del anfitrión no los borre— y un glifo decorativo oculto al lector. El
     color acompaña; no porta. Es lo que sostiene la pantalla en alto contraste
     forzado, en daltonismo y en papel, donde los navegadores no imprimen fondos.

     Sin Alpine y sin nada interactivo: es contenido de solo lectura y no tiene
     una sola parada de tabulación propia. --}}

@php
    $muniDcEstados = [
        'agregado' => 'Agregado',
        'modificado' => 'Modificado',
        'suprimido' => 'Suprimido',
        'sin-cambios' => 'Sin cambios',
    ];

    /* Alias tolerados: los hosts vienen de activitylog, de un enum propio o del
       inglés. Traducir acá sale más barato que forkear el componente. */
    $muniDcAlias = [
        'added' => 'agregado', 'create' => 'agregado', 'created' => 'agregado', 'creado' => 'agregado',
        'alta' => 'agregado', 'nuevo' => 'agregado',
        'modified' => 'modificado', 'updated' => 'modificado', 'changed' => 'modificado',
        'update' => 'modificado', 'cambiado' => 'modificado', 'editado' => 'modificado',
        'removed' => 'suprimido', 'deleted' => 'suprimido', 'delete' => 'suprimido',
        'eliminado' => 'suprimido', 'borrado' => 'suprimido', 'baja' => 'suprimido',
        'unchanged' => 'sin-cambios', 'igual' => 'sin-cambios', 'sin_cambios' => 'sin-cambios',
    ];

    /* Decorativo y oculto al lector: el glifo es refuerzo visual para quien lee
       la tabla de un vistazo, no un portador de información. */
    $muniDcGlifos = [
        'agregado' => '+',
        'modificado' => '±',
        'suprimido' => '−',
        'sin-cambios' => '=',
    ];

    /* Un valor ausente y un valor en blanco son lo mismo para una bitácora: el
       campo no tiene contenido de ese lado del cambio. */
    $muniDcValor = function ($crudo): ?string {
        /* Un array o un objeto acá significa que el host mandó estructura cruda en
           vez de un string redactado: se trata como ausente, nunca se aplana. */
        if ($crudo === null || is_array($crudo) || is_object($crudo)) {
            return null;
        }

        if (is_bool($crudo)) {
            return $crudo ? 'Sí' : 'No';
        }

        $texto = (string) $crudo;

        return trim($texto) === '' ? null : $texto;
    };

    $muniDcFilas = [];

    foreach (is_iterable($changes) ? $changes : [] as $muniDcCambio) {
        /* Fail-closed: un modelo, un DTO o cualquier objeto se descarta entero.
           Convertirlo a array es justo lo que la minimización prohíbe. */
        if (! is_array($muniDcCambio)) {
            continue;
        }

        $muniDcCampo = trim((string) ($muniDcCambio['field'] ?? ''));
        $muniDcEtiqueta = trim((string) ($muniDcCambio['label'] ?? '')) ?: $muniDcCampo;

        if ($muniDcEtiqueta === '') {
            continue;
        }

        $muniDcAntes = $muniDcValor($muniDcCambio['before'] ?? null);
        $muniDcDespues = $muniDcValor($muniDcCambio['after'] ?? null);

        $muniDcEstado = strtolower(trim((string) ($muniDcCambio['state'] ?? '')));
        $muniDcEstado = $muniDcAlias[$muniDcEstado] ?? $muniDcEstado;

        if (! isset($muniDcEstados[$muniDcEstado])) {
            $muniDcEstado = match (true) {
                $muniDcAntes === null && $muniDcDespues === null => 'sin-cambios',
                $muniDcAntes === null => 'agregado',
                $muniDcDespues === null => 'suprimido',
                /* Un valor que no cambió no debería estar en la lista; si llega,
                   se dice y no se marca como inserción ni como supresión. */
                $muniDcAntes === $muniDcDespues => 'sin-cambios',
                default => 'modificado',
            };
        }

        $muniDcFilas[] = [
            'campo' => $muniDcCampo,
            'etiqueta' => $muniDcEtiqueta,
            'antes' => $muniDcAntes,
            'despues' => $muniDcDespues,
            'estado' => $muniDcEstado,
            'mono' => filter_var($muniDcCambio['mono'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    $muniDcCaption = trim((string) $caption) !== '' ? trim((string) $caption) : 'Cambios por campo';
    $muniDcHueco = trim((string) $nilLabel) !== '' ? trim((string) $nilLabel) : 'sin valor';
@endphp

<div {{ $attributes->merge(['class' => 'muni-dc__marco']) }}>
    {{-- El envoltorio interior existe SOLO para ser el contenedor de consulta del
         bloque de estilos: la tabla vive tanto a ancho completo como dentro de una
         tarjeta de 306 px, y una consulta de viewport no distingue esos dos casos.
         Va acá dentro y no en `.muni-dc__marco` a propósito: `container-type`
         implica contención de tamaño en línea, y el marco es el nodo que recibe los
         atributos del anfitrión —si alguien lo pone como ítem de un flex, un marco
         contenido mediría cero. Este div nunca se dimensiona por su contenido. --}}
    <div class="muni-dc__cq">
    <table class="muni-dc">
        <caption class="muni-dc__caption">{{ $muniDcCaption }}</caption>
        <thead>
            <tr>
                {{-- `scope="col"` no es decorativo: sin él una celda leída suelta no
                     dice a qué encabezado pertenece (WCAG 2.2 AA 1.3.1), y una
                     bitácora se lee justamente celda por celda. --}}
                <th scope="col" class="muni-dc__c-campo">{{ $fieldHeading }}</th>
                <th scope="col" class="muni-dc__c-estado">{{ $stateHeading }}</th>
                <th scope="col" class="muni-dc__c-valor">{{ $beforeHeading }}</th>
                <th scope="col" class="muni-dc__c-valor">{{ $afterHeading }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($muniDcFilas as $muniDcFila)
                @php
                    $muniDcClaseValor = 'muni-dc__val'.($muniDcFila['mono'] ? ' muni-num' : '');
                @endphp
                <tr class="muni-dc__fila muni-dc__fila--{{ $muniDcFila['estado'] }}" @if ($muniDcFila['campo'] !== '') data-muni-field="{{ $muniDcFila['campo'] }}" @endif>
                    {{-- El nombre del campo es la cabecera de su fila: sin esto,
                         «Calle Dos 200» se lee sin decir de qué campo es. --}}
                    <th scope="row" class="muni-dc__campo">{{ $muniDcFila['etiqueta'] }}</th>

                    <td class="muni-dc__estado">
                        <span class="muni-dc__tag muni-dc__tag--{{ $muniDcFila['estado'] }}">
                            <span class="muni-dc__glifo" aria-hidden="true">{{ $muniDcGlifos[$muniDcFila['estado']] }}</span>{{ $muniDcEstados[$muniDcFila['estado']] }}
                        </span>
                    </td>

                    <td class="muni-dc__antes">
                        @if ($muniDcFila['antes'] === null)
                            <span class="muni-dc__nil"><span aria-hidden="true">—</span><span class="muni-dc__sr">{{ $muniDcHueco }}</span></span>
                        @elseif ($muniDcFila['estado'] === 'suprimido' || $muniDcFila['estado'] === 'modificado')
                            <del class="{{ $muniDcClaseValor }}">{{ $muniDcFila['antes'] }}</del>
                        @else
                            <span class="{{ $muniDcClaseValor }}">{{ $muniDcFila['antes'] }}</span>
                        @endif
                    </td>

                    <td class="muni-dc__despues">
                        @if ($muniDcFila['despues'] === null)
                            <span class="muni-dc__nil"><span aria-hidden="true">—</span><span class="muni-dc__sr">{{ $muniDcHueco }}</span></span>
                        @elseif ($muniDcFila['estado'] === 'agregado' || $muniDcFila['estado'] === 'modificado')
                            <ins class="{{ $muniDcClaseValor }}">{{ $muniDcFila['despues'] }}</ins>
                        @else
                            <span class="{{ $muniDcClaseValor }}">{{ $muniDcFila['despues'] }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="muni-dc__vacio" colspan="4">{{ $empty }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- El slot es la nota al pie: plazo de retención, origen del registro, aviso
         de que la exportación queda registrada. Se rinde de verdad; un slot que se
         descarta en silencio es un defecto que este paquete ya arrastró una vez. --}}
    @if (trim($slot) !== '')
        <p class="muni-dc__nota">{{ $slot }}</p>
    @endif
    </div>
</div>

@once
    <style>
        /* Todo lo que el componente necesita viaja con él y NO en muni-ui.css
           (DESIGN §7): dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css, así que una clase declarada únicamente en
           la hoja general deja la tabla sin estilo y sin un error en consola.
           Por eso se repiten acá el ocultado visual y `.muni-num`, que también
           existen en el bloque de data-table: puede no haberse emitido nunca en
           esta página. */
        .muni-dc__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }

        .muni-dc__marco { border:1px solid var(--muni-border); border-radius:var(--muni-radius); background:var(--muni-surface); overflow:hidden; }

        /* `table-layout:fixed` + `overflow-wrap` es lo que impide que una glosa de
           400 caracteres o una URL sin espacios reviente el ancho de la pantalla:
           con layout automático la celda crece hasta el token más largo. */
        .muni-dc { width:100%; border-collapse:collapse; table-layout:fixed; font-family:var(--muni-font-sans); font-size:12.5px; color:var(--muni-text); }

        .muni-dc__caption { caption-side:top; text-align:left; padding:10px 12px; font-size:12.5px; font-weight:700; line-height:1.3; color:var(--muni-text); background:var(--muni-surface-2); border-bottom:1px solid var(--muni-border); }

        /* `box-sizing:border-box` acá no es cosmético: sin él, un `width` sobre una
           celda de cabecera es el ancho del CONTENIDO y el relleno se suma encima,
           así que la misma regla da 122 px de columna en un anfitrión con el reset
           de Tailwind y 146 px en uno sin él. Con el ancho de la columna de estado
           en píxeles medidos, esos 24 px de diferencia deciden si la píldora cabe:
           se fija el modelo de caja y el número deja de depender del host. */
        .muni-dc thead th { box-sizing:border-box; text-align:left; padding:7px 12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--muni-muted); background:var(--muni-surface-2); border-bottom:1px solid var(--muni-border); }
        .muni-dc__c-campo { width:24%; }
        /* La columna de estado se dimensiona AL CONTENIDO, en px, y no en
           porcentaje. Medido con getBoundingClientRect en Chromium 151 y Firefox
           153, en claro y en oscuro, la píldora más ancha —«= Sin cambios»— mide
           93,8 px; con los 24 px de relleno de la celda hacen falta 117,8, y se
           redondea a 122 (caja de borde) para dejar holgura de fuente al anfitrión
           que redefina --muni-font-sans. Un porcentaje ataba el ancho al del contenedor —18 %
           son 80 px en una tarjeta de 440 px y 60 px en una de 306— mientras la
           píldora medía siempre lo mismo: de ahí salía el desborde de 14 a 46 px
           sobre la columna «Antes». */
        .muni-dc__c-estado { width:122px; }
        /* Las dos columnas de valor se reparten lo que sobra, sea cual sea el
           ancho de la tabla. Con `auto` no hay que cuadrar porcentajes a mano
           cada vez que cambia el ancho de otra columna. */
        .muni-dc__c-valor { width:auto; }

        /* Se gana en especificidad a `[data-muni-row] td` de data-table a
           propósito: aquel fija `white-space:nowrap`, y acá el valor TIENE que
           poder envolverse. El orden en que se emiten los dos bloques de estilos
           no es predecible, así que no se puede depender de llegar después. */
        .muni-dc tbody th, .muni-dc tbody td { padding:8px 12px; border-bottom:1px solid var(--muni-border); vertical-align:top; white-space:normal; overflow-wrap:anywhere; word-break:break-word; color:var(--muni-text); }
        .muni-dc tbody tr:last-child th, .muni-dc tbody tr:last-child td { border-bottom:0; }
        .muni-dc tbody th { text-align:left; font-weight:600; }

        /* La firma de la casa: cifras, RUT y fechas en mono tabular, para que las
           unidades alineen entre «antes» y «después». Opt-in por ítem. */
        .muni-dc .muni-num { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; }

        /* Dos garantias, y la segunda es la que importa: `max-width:100%` impide
           que la píldora salga de su celda pase lo que pase —fuente del anfitrión
           más ancha, zoom de solo texto, una traducción más larga—, y
           `white-space:normal` hace que en ese caso ENVUELVA en vez de dibujarse
           encima de la celda vecina. Antes decía `nowrap`, y una caja que no
           encoge dentro de una columna que sí lo hace es exactamente el defecto
           D3. El corte natural es el espacio: `overflow-wrap:anywhere`, heredado
           de la celda, sólo parte una palabra cuando esa palabra sola no cabe. */
        .muni-dc__tag { display:inline-flex; align-items:center; gap:4px; max-width:100%; padding:1px 8px; border-radius:999px; border:1px solid var(--muni-border-2); font-size:11px; font-weight:700; line-height:1.6; white-space:normal; }
        .muni-dc__glifo { font-family:var(--muni-font-mono); font-weight:700; }
        /* Los cuatro estados salen de tokens que tienen rama clara y rama oscura,
           así que la contraparte del modo oscuro viene con el token (DESIGN §4).
           Medido: fg sobre bg da 4,69:1 / 6,55:1 en «agregado», 5,45:1 / 4,75:1 en
           «modificado» y 5,48:1 / 4,75:1 en «suprimido». «Sin cambios» usa el
           texto normal sobre la superficie 3 —14,5:1 y 12,8:1— y no un atenuado:
           --muni-muted sobre --muni-surface-3 se queda en 4,55:1 en oscuro, que
           pasa por un pelo y no vale la pena arriesgar. */
        .muni-dc__tag--agregado { color:var(--muni-ok-fg); background:var(--muni-ok-bg); border-color:var(--muni-ok-border); }
        .muni-dc__tag--modificado { color:var(--muni-info-fg); background:var(--muni-info-bg); border-color:var(--muni-info-border); }
        .muni-dc__tag--suprimido { color:var(--muni-danger-fg); background:var(--muni-danger-bg); border-color:var(--muni-danger-border); }
        .muni-dc__tag--sin-cambios { color:var(--muni-text); background:var(--muni-surface-3); border-color:var(--muni-border-2); }

        /* El navegador ya subraya el <ins> y tacha el <del>; se re-declara para
           que un reset del sistema anfitrión (Tailwind Preflight no los toca, pero
           `all:unset` y varios resets sí) no deje el color como única señal. */
        .muni-dc ins, .muni-dc del { display:inline; padding:0 3px; border-radius:var(--muni-radius-sm); text-decoration-thickness:1px; text-underline-offset:2px; }
        .muni-dc ins { text-decoration-line:underline; color:var(--muni-ok-fg); background:var(--muni-ok-bg); }
        .muni-dc del { text-decoration-line:line-through; color:var(--muni-danger-fg); background:var(--muni-danger-bg); }

        .muni-dc__nil { color:var(--muni-muted); }
        .muni-dc__vacio { text-align:center; padding:26px 12px; color:var(--muni-muted); }
        .muni-dc__nota { margin:0; padding:8px 12px; border-top:1px solid var(--muni-border); font-family:var(--muni-font-sans); font-size:11.5px; line-height:1.45; color:var(--muni-muted); background:var(--muni-surface-2); }

        {{-- Las reglas compactas están DOS veces a propósito, y no es duplicación
             que limpiar: la de viewport es el camino base y funciona en todos
             lados; la de contenedor, más abajo, es la que acierta cuando la tabla
             vive en una tarjeta estrecha de una pantalla ancha —el caso que la
             consulta de viewport no puede ver y que dejaba la columna de estado en
             60 px dentro de un escritorio de 1440. Si algún día se editan los
             valores, se editan los dos bloques. --}}
        @media (max-width: 640px) {
            .muni-dc { font-size:12px; }
            .muni-dc thead th, .muni-dc tbody th, .muni-dc tbody td { padding:6px 8px; }
            .muni-dc__c-campo { width:26%; }
            /* Relleno de celda 16 px en vez de 24 y píldora compacta (88,8 px
               medidos), así que la columna baja de 122 a 108 y los 14 px que se
               liberan se van a las dos columnas de valor, que es donde se lee. */
            .muni-dc__c-estado { width:108px; }
            .muni-dc__tag { gap:3px; padding:1px 6px; }
        }

        /* Mejora progresiva (DESIGN §10): donde haya consultas de contenedor, lo
           compacto se decide por el ancho de la TABLA y no por el de la ventana.
           Va después del bloque de viewport para ganarle por orden, con la misma
           especificidad. Donde no exista, queda el camino de arriba entero. */
        @supports (container-type: inline-size) {
            .muni-dc__cq { container-type:inline-size; container-name:muni-dc; }

            @container muni-dc (max-width: 640px) {
                .muni-dc { font-size:12px; }
                .muni-dc thead th, .muni-dc tbody th, .muni-dc tbody td { padding:6px 8px; }
                .muni-dc__c-campo { width:26%; }
                .muni-dc__c-estado { width:108px; }
                .muni-dc__tag { gap:3px; padding:1px 6px; }
            }
        }

        /* En papel el fondo no se imprime: sin esto el <ins> y el <del> quedarían
           con el color de texto de su estado sobre blanco, que es legible, pero el
           relleno daría a entender un resalte que no está. Se quita el fondo y se
           deja el subrayado, el tachado y la palabra del estado, que es lo que de
           verdad porta la información. */
        @media print {
            .muni-dc__fila { break-inside:avoid; }
            .muni-dc ins, .muni-dc del { background:transparent; padding:0; }
            .muni-dc__tag { background:transparent; }
        }
    </style>
@endonce
