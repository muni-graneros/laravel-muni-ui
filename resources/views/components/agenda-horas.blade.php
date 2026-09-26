@props([
    'name' => 'agenda', // name del radio de hora; también {name}_dia y {name}_mes
    'mes' => null, // mes a la vista, 'Y-m'; por defecto el del día elegido
    'franjas' => [], // ['Y-m-d' => [['inicio','fin','estado','valor','detalle']]]
    'dias' => [], // carga ya calculada: ['Y-m-d' => ['total'=>, 'tomados'=>]]
    'value' => null, // día elegido, 'Y-m-d' o DateTimeInterface
    'franja' => null, // valor de la franja elegida; solo vale si está libre
    'label' => 'Agenda de horas', // nombre accesible del grupo
    'accion' => 'Reservar', // texto del botón de acción
    'mesUrl' => null, // callable('Y-m') => url; dibuja ‹ › como enlaces
    'mesEvento' => false, // true: ‹ › emiten muni-agenda:mes (Livewire)
    'operacion' => 'reservar', // reservar | reagendar | bloquear (va en muni-agenda:accion)
    'error' => null, // mensaje de error ya redactado
])

@php
    /*
     * TODO lo que llega del anfitrión se valida acá, en PHP, y FALLA CERRADO,
     * igual que en `calendar`: un mes que no sea `Y-m`, un día que no exista de
     * verdad (2026-02-30 casa con el patrón y PHP lo correría a marzo) o una
     * hora que no sea `HH:MM` se descartan ENTEROS. Nunca «mejor escapados»:
     * media fecha en la rejilla es una hora mal reservada.
     *
     * Y nada de esto entra jamás en una expresión de Alpine. El sumidero que
     * `calendar` pagó caro era `new Date('{{ $min }}')` dentro de `x-data`: el
     * navegador decodifica las entidades ANTES de que Alpine evalúe, así que un
     * valor con `') || fetch(...)` se ejecutaba, y un simple apóstrofo de
     * «O'Higgins» descuadra la cadena y tumba el Alpine de la PÁGINA entera
     * (el mismo defecto que arregló `file-dropzone`). Acá todo viaja por
     * atributos de datos escapados y Alpine los lee con `$el.dataset`, que es
     * texto y no código.
     *
     * La ficha marca la zona horaria como riesgo —el gotcha de APP_TIMEZONE
     * ausente ya mordió en el ecosistema—, así que la rejilla se calcula EN PHP,
     * con la zona del servidor. En el navegador no hay una sola fecha: el teclado
     * se mueve por ÍNDICES de celda (fila y columna), que son enteros.
     */
    $mesesEs = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $diasEs = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    $diasCortos = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

    $diaIso = function ($valor): ?string {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (! is_string($valor) || ! \Illuminate\Support\Carbon::hasFormat($valor, 'Y-m-d')) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $valor)->format('Y-m-d') === $valor ? $valor : null;
        } catch (\Throwable) {
            return null;
        }
    };

    $diaSel = $diaIso($value);

    /* Las franjas del anfitrión, normalizadas día por día. `detalle` es una
       CADENA ya redactada: el paquete no recibe modelos ni registros completos
       (minimización, Ley 21.719), y lo que el anfitrión ponga ahí se publica en
       el HTML de todos los días que mande. */
    $porDia = [];

    foreach ((array) $franjas as $clave => $lista) {
        $iso = $diaIso(is_int($clave) ? null : $clave);

        if ($iso === null || ! is_array($lista)) {
            continue;
        }

        $normal = [];

        foreach ($lista as $f) {
            if (! is_array($f)) {
                continue;
            }

            $ini = is_string($f['inicio'] ?? null) && preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $f['inicio']) ? $f['inicio'] : null;

            if ($ini === null) {
                continue;
            }

            $fin = is_string($f['fin'] ?? null) && preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $f['fin']) ? $f['fin'] : null;
            $estadoCrudo = is_string($f['estado'] ?? null) ? $f['estado'] : 'libre';
            $estado = in_array($estadoCrudo, ['libre', 'tomada', 'bloqueada'], true) ? $estadoCrudo : 'libre';
            $valorF = isset($f['valor']) && (is_string($f['valor']) || is_int($f['valor'])) ? (string) $f['valor'] : $iso.'T'.$ini;

            $normal[] = [
                'inicio' => $ini,
                'fin' => $fin,
                'estado' => $estado,
                'valor' => $valorF,
                'detalle' => is_string($f['detalle'] ?? null) ? $f['detalle'] : '',
            ];
        }

        usort($normal, fn (array $a, array $b) => strcmp($a['inicio'], $b['inicio']));
        $porDia[$iso] = $normal;
    }

    /* El mes a la vista. En orden: el que mandan, el del día elegido, el primero
       que traigan las franjas y, si no hay nada, el del servidor. */
    $mesIso = is_string($mes) && preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $mes) ? $mes : null;
    $mesIso ??= $diaSel !== null ? substr($diaSel, 0, 7) : null;
    $mesIso ??= count($porDia) > 0 ? substr((string) array_key_first($porDia), 0, 7) : null;
    $mesIso ??= \Illuminate\Support\Carbon::now()->format('Y-m');

    /* Un día elegido que no cae en el mes a la vista no se puede ver ni enfocar:
       falla cerrado. Si no, el campo oculto `_dia` viajaría con un día que la
       rejilla no muestra. */
    if ($diaSel !== null && substr($diaSel, 0, 7) !== $mesIso) {
        $diaSel = null;
    }

    $primero = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $mesIso.'-01');
    $anio = (int) $primero->format('Y');
    $mesNum = (int) $primero->format('n');
    $cuantosDias = (int) $primero->format('t');
    $corrimiento = ((int) $primero->format('N')) - 1;
    $mesAnterior = $primero->copy()->subMonthNoOverflow()->format('Y-m');
    $mesSiguiente = $primero->copy()->addMonthNoOverflow()->format('Y-m');
    $tituloMes = $mesesEs[$mesNum - 1].' '.$anio;

    $rotuloMes = function (string $ym) use ($mesesEs): string {
        [$y, $m] = explode('-', $ym);

        return $mesesEs[((int) $m) - 1].' de '.$y;
    };

    /* La carga del día. El anfitrión puede mandarla ya calculada en `dias`
       (porque la agenda real vive en su base y no en lo que publicó acá); si no,
       sale de contar las franjas. */
    $cargaDe = function (string $iso) use ($dias, $porDia): array {
        if (isset($dias[$iso]) && is_array($dias[$iso])) {
            $total = max(0, (int) ($dias[$iso]['total'] ?? 0));
            $tomados = max(0, (int) ($dias[$iso]['tomados'] ?? 0));

            return ['total' => $total, 'libres' => max(0, $total - $tomados)];
        }

        $lista = $porDia[$iso] ?? [];

        return [
            'total' => count($lista),
            'libres' => count(array_filter($lista, fn (array $f) => $f['estado'] === 'libre')),
        ];
    };

    /* Las celdas del mes: huecos reales antes del día 1 y después del último,
       para que la rejilla tenga siete columnas de verdad. Los huecos del inicio
       eran, en `calendar`, un `<template>` con display:none que no ocupaba celda
       y corría el mes entero una columna. */
    $celdas = array_fill(0, $corrimiento, null);

    for ($d = 1; $d <= $cuantosDias; $d++) {
        $celdas[] = $d;
    }

    while (count($celdas) % 7 !== 0) {
        $celdas[] = null;
    }

    $semanas = array_chunk($celdas, 7);

    $delDia = [];

    foreach (range(1, $cuantosDias) as $d) {
        $iso = sprintf('%04d-%02d-%02d', $anio, $mesNum, $d);
        $carga = $cargaDe($iso);
        $nombre = $diasEs[($corrimiento + $d - 1) % 7];
        $etiqueta = $nombre.' '.$d.' de '.$mesesEs[$mesNum - 1].' de '.$anio;

        if ($carga['total'] === 0) {
            $resumen = 'sin horas publicadas';
        } elseif ($carga['libres'] === 0) {
            $resumen = 'completo, 0 de '.$carga['total'].' cupos libres';
        } else {
            $resumen = $carga['libres'].' de '.$carga['total'].' cupos libres';
        }

        $delDia[$iso] = [
            'numero' => $d,
            'fecha' => $etiqueta,
            'etiqueta' => $etiqueta.', '.$resumen,
            'visible' => $carga['total'] === 0 ? '—' : $carga['libres'].'/'.$carga['total'],
            'lleno' => $carga['total'] > 0 && $carga['libres'] === 0,
        ];
    }

    $focoIni = ($diaSel !== null && isset($delDia[$diaSel])) ? $diaSel : (string) array_key_first($delDia);

    /* La hora elegida solo vale si es una franja LIBRE del día elegido. Una hora
       de otro día, tomada o inventada se descarta: el formulario nunca puede salir
       con `_dia` de un día y la hora de otro (la «hora mal reservada»). */
    $franjaSel = isset($franja) && (is_string($franja) || is_int($franja)) ? (string) $franja : '';
    $franjaSel = $franjaSel !== '' && $diaSel !== null
        && count(array_filter($porDia[$diaSel] ?? [], fn (array $f) => $f['valor'] === $franjaSel && $f['estado'] === 'libre')) > 0
        ? $franjaSel : '';

    $operacionOk = in_array($operacion, ['reservar', 'reagendar', 'bloquear'], true) ? $operacion : 'reservar';
    $errorTexto = is_string($error) && trim($error) !== '' ? $error : null;

    $agId = $attributes->get('id')
        ?: 'muni-ag-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $name), '-')
            .'-'.substr(sha1((string) $name), 0, 6);

    $hrefPrev = is_callable($mesUrl) ? $mesUrl($mesAnterior) : null;
    $hrefNext = is_callable($mesUrl) ? $mesUrl($mesSiguiente) : null;
    $hrefPrev = is_string($hrefPrev) && $hrefPrev !== '' ? $hrefPrev : null;
    $hrefNext = is_string($hrefNext) && $hrefNext !== '' ? $hrefNext : null;

    /* El mes lo dibuja el servidor, así que cambiarlo es pedírselo. Hay dos formas
       y el anfitrión elige una de manera EXPLÍCITA: `mesUrl` (enlaces reales) o
       `mesEvento` (el evento `muni-agenda:mes`, que un componente Livewire escucha).
       Sin ninguna de las dos no se dibujan ‹ ›: un botón que no hace nada es peor
       que no tener botón. */
    $navEnlace = $hrefPrev !== null && $hrefNext !== null;
    $navEvento = ! $navEnlace && filter_var($mesEvento, FILTER_VALIDATE_BOOL);

    $textoVacio = $diaSel === null
        ? 'Elige un día del mes para ver sus horas.'
        : 'El '.$delDia[$diaSel]['fecha'].' no tiene horas publicadas.';

    $etiquetaLarga = function (string $iso) use ($diaIso, $diasEs, $mesesEs): string {
        $fecha = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $iso);

        return $diasEs[((int) $fecha->format('N')) - 1].' '.((int) $fecha->format('j'))
            .' de '.$mesesEs[((int) $fecha->format('n')) - 1].' de '.$fecha->format('Y');
    };

    $rotuloEstado = ['libre' => 'Libre', 'tomada' => 'Tomada', 'bloqueada' => 'Bloqueada'];
    $marcaEstado = ['libre' => '✓', 'tomada' => '●', 'bloqueada' => '✕'];
@endphp

{{-- Agenda de horas (Alpine 3 core, sin plugins). El mes con la carga de cada día
     y, al elegir un día, sus franjas horarias como radios reales.

     · La rejilla es la rejilla APG que `calendar` dejó pendiente: role="grid",
       una sola parada de tabulación (tabindex itinerante), flechas, Inicio/Fin y
       RePág/AvPág. Con 31 paradas el funcionario del mesón no llega nunca a las
       franjas, y eso es justo lo que hoy hace el paso 3 del asistente.
     · El movimiento del foco es por ÍNDICE de celda, no por aritmética de fechas:
       cada botón trae su fila y su columna. Así el navegador no construye ni una
       fecha y el riesgo de zona horaria de la ficha desaparece entero.
     · Las flechas NO salen del mes: en el borde se quedan quietas. Salir del mes
       es pedirle otro mes al servidor (una navegación con `mesUrl`, una petición
       con `mesEvento`), y eso no puede ocurrir por pasarse de una flecha. Para eso
       están RePág/AvPág y ‹ ›, que son el gesto explícito.
     · UNA SOLA VERDAD: la hora elegida pertenece SIEMPRE al día elegido. Cambiar de
       día suelta la hora; el anfitrión que fija una hora de otro día (por
       `wire:model`) mueve el día a esa hora; y «Reservar» comprueba que el radio
       marcado sea una franja libre del panel visible. Sin esto el formulario salía
       con `_dia=13` y la hora del 12, y el evento igual.
     · Las franjas son `<input type="radio">` REALES dentro de un fieldset: las
       flechas, el grupo y el envío del formulario vienen gratis del navegador, y
       lo tomado o bloqueado va `disabled` porque no se puede elegir. Sin JS se ve
       el día que el servidor eligió, porque los demás nacen con `hidden`.
     · Lo que se ve y lo que no va con enlaces DECLARATIVOS de Alpine (`:hidden`,
       `:class`, `:tabindex`), nunca con `el.hidden = …` a mano: dentro de Livewire
       el morph repinta los atributos del servidor y un cambio imperativo se pierde;
       uno declarado se vuelve a evaluar sobre el HTML nuevo.
     · Si el anfitrión repinta la agenda (Livewire tras `muni-agenda:mes`), la raíz
       cambia `data-mes`/`data-dia`/`data-elegida` y un MutationObserver relee el
       estado: la rejilla nueva no queda con el foco y el día del mes anterior.
     · Esc va EN el panel de franjas, nunca en window: un manejador global se pisa
       con el de cualquier otro diálogo de la pantalla. Cancela la elección y
       devuelve el foco al día de la rejilla, que es de donde vino. Un día SIN
       franjas no mueve el foco a ninguna parte: queda en el día y se anuncia que no
       tiene horas, así no hay de dónde tener que salir.
     · El componente NO reserva: emite `muni-agenda:accion` con la `operacion`
       (reservar, reagendar o bloquear) y deja que el servidor decida. La ficha
       marca la doble reserva como riesgo y la verdad sobre el cupo no la puede
       tener el cliente. Autorización, cola y bitácora son del anfitrión.
     · `x-modelable` ata SOLO la hora elegida a `wire:model`/`x-model`. El día y el
       mes no son modelables: se sincronizan escuchando `muni-agenda:dia` y
       `muni-agenda:mes` en la etiqueta del componente (ver el README).
     · Los métodos buscan con `this.$root`, NUNCA con `this.$el`: llamados desde el
       `@click` de un día, `$el` es ESE botón y no la raíz, así que buscar los paneles
       ahí no encontraba ninguno. Medido en Chromium y Firefox: Enter elegía el día
       pero el foco no llegaba nunca a las franjas.
     · Los textos del anfitrión —el rótulo, el detalle de una franja, el error— son
       contenido Blade escapado o atributos de datos. Ni uno entra en una expresión
       de Alpine; las únicas cadenas de las expresiones son del componente. --}}
<div
    x-data="{
        dia: '',
        foco: '',
        franja: '',
        anuncio: '',
        cargando: '',
        fallo: '',
        visto: null,
        observador: null,
        espera: null,

        init() {
            const ds = this.$root.dataset;
            this.dia = ds.dia || '';
            this.foco = ds.foco || '';
            this.franja = ds.elegida || '';
            this.visto = { mes: ds.mes || '', dia: this.dia, elegida: this.franja };
            this.$watch('franja', (v) => this.marcar(v));
            this.observador = new MutationObserver(() => this.releer());
            this.observador.observe(this.$root, { attributes: true, attributeFilter: ['data-mes', 'data-dia', 'data-elegida'] });
        },

        destroy() {
            if (this.observador) this.observador.disconnect();
            clearTimeout(this.espera);
        },

        celdas() { return Array.from(this.$refs.rejilla.querySelectorAll('[data-celda]')); },
        paneles() { return Array.from(this.$root.querySelectorAll('[data-panel]')); },
        panelDe(d) { return this.paneles().find(p => p.dataset.panel === d) || null; },
        radioLibre(panel, v) {
            if (! panel || v === '') return null;
            return Array.from(panel.querySelectorAll('[data-franja]')).find(r => r.value === v && ! r.disabled) || null;
        },
        hayPanel() { return this.dia !== '' && this.panelDe(this.dia) !== null; },
        franjaValida() { return this.radioLibre(this.panelDe(this.dia), this.franja) !== null; },

        textoVacio() {
            if (this.dia === '') return 'Elige un día del mes para ver sus horas.';
            const btn = this.celdas().find(b => b.dataset.celda === this.dia);
            return 'El ' + (btn ? btn.dataset.fecha : this.dia) + ' no tiene horas publicadas.';
        },

        tecla(e) {
            const btn = e.target.closest('[data-celda]');
            if (! btn) return;

            const lista = this.celdas();
            const i = lista.indexOf(btn);
            if (i < 0) return;

            const fila = Number(btn.dataset.fila);
            const col = Number(btn.dataset.col);
            let destino = null;

            if (e.key === 'ArrowLeft') destino = lista[i - 1] || btn;
            else if (e.key === 'ArrowRight') destino = lista[i + 1] || btn;
            else if (e.key === 'ArrowUp') destino = lista.find(b => Number(b.dataset.fila) === fila - 1 && Number(b.dataset.col) === col) || btn;
            else if (e.key === 'ArrowDown') destino = lista.find(b => Number(b.dataset.fila) === fila + 1 && Number(b.dataset.col) === col) || btn;
            else if (e.key === 'Home') destino = lista.find(b => Number(b.dataset.fila) === fila) || btn;
            else if (e.key === 'End') destino = lista.filter(b => Number(b.dataset.fila) === fila).pop() || btn;
            else if (e.key === 'PageUp' || e.key === 'PageDown') { e.preventDefault(); this.cambiarMes(e.key === 'PageUp' ? -1 : 1); return; }
            else return;

            e.preventDefault();
            this.irA(destino);
        },

        irA(btn) { this.foco = btn.dataset.celda; btn.focus(); },

        cambiarMes(n) {
            const ref = n < 0 ? this.$refs.prev : this.$refs.next;
            if (ref) { ref.click(); return; }
            this.anunciar('Esta agenda muestra solo ' + this.$root.dataset.rotuloMes + '.');
        },

        pedirMes(btn) {
            if (this.cargando !== '') return;
            clearTimeout(this.espera);
            this.fallo = '';
            this.cargando = btn.dataset.rotulo;
            this.anunciar('Cargando ' + this.cargando + '.');
            this.espera = setTimeout(() => {
                if (this.cargando === '') return;
                this.fallo = 'No llegó ' + this.cargando + '. Vuelve a intentarlo.';
                this.cargando = '';
                this.anunciar(this.fallo);
            }, 15000);
            this.$root.dispatchEvent(new CustomEvent('muni-agenda:mes', { bubbles: true, detail: { mes: btn.dataset.mes } }));
        },

        releer() {
            const ds = this.$root.dataset;
            const ahora = { mes: ds.mes || '', dia: ds.dia || '', elegida: ds.elegida || '' };
            const nuevoMes = ahora.mes !== this.visto.mes;
            const enRejilla = this.$refs.rejilla.contains(document.activeElement);

            if (nuevoMes || ahora.dia !== this.visto.dia) { this.dia = ahora.dia; this.foco = ds.foco || ''; }
            if (nuevoMes || ahora.elegida !== this.visto.elegida) this.franja = ahora.elegida;
            if (nuevoMes) { clearTimeout(this.espera); this.cargando = ''; this.fallo = ''; }

            this.visto = ahora;
            this.soltarSiAjena();

            if (nuevoMes && enRejilla) this.$nextTick(() => this.volverAlDia());
        },

        elegir(d) {
            this.dia = d;
            this.foco = d;
            this.$refs.campoDia.value = d;
            this.avisar(this.$refs.campoDia);
            this.soltarSiAjena();
            this.$root.dispatchEvent(new CustomEvent('muni-agenda:dia', { bubbles: true, detail: { dia: d } }));
            this.$nextTick(() => this.enfocarFranjas());
        },

        soltarSiAjena() {
            if (this.franja === '' || this.franjaValida()) return;
            this.franja = '';
            this.marcar('');
        },

        enfocarFranjas() {
            const panel = this.panelDe(this.dia);
            if (! panel) { this.anunciar(this.textoVacio()); return; }

            const radios = Array.from(panel.querySelectorAll('[data-franja]'));
            const meta = radios.find(r => r.checked && ! r.disabled) || radios.find(r => ! r.disabled);
            (meta || panel).focus();
        },

        elegirFranja(el) {
            this.franja = el.value;
            this.anunciar(el.dataset.franja + ', elegida');
            this.$root.dispatchEvent(new CustomEvent('muni-agenda:franja', { bubbles: true, detail: { dia: this.dia, franja: el.value } }));
        },

        marcar(v) {
            const radios = Array.from(this.$root.querySelectorAll('[data-franja]'));
            if (v === '') { radios.forEach(r => { r.checked = false; }); return; }

            let radio = this.radioLibre(this.panelDe(this.dia), v);
            if (! radio) {
                radio = radios.find(r => r.value === v && ! r.disabled) || null;
                if (radio) {
                    const d = radio.closest('[data-panel]').dataset.panel;
                    this.dia = d;
                    this.foco = d;
                    this.$refs.campoDia.value = d;
                }
            }

            radios.forEach(r => { r.checked = r === radio; });
        },

        cancelar(e) {
            e.preventDefault();
            e.stopPropagation();
            this.franja = '';
            this.marcar('');
            this.anunciar('Elección de hora cancelada.');
            this.volverAlDia();
        },

        volverAlDia() {
            const lista = this.celdas();
            const btn = lista.find(b => b.dataset.celda === this.dia) || lista.find(b => b.dataset.celda === this.foco) || lista[0];
            if (btn) this.irA(btn);
        },

        accionar() {
            if (! this.franjaValida()) {
                this.soltarSiAjena();
                this.anunciar('Elige una hora libre del día elegido antes de continuar.');
                return;
            }
            this.$root.dispatchEvent(new CustomEvent('muni-agenda:accion', { bubbles: true, detail: { operacion: this.$root.dataset.operacion, dia: this.dia, franja: this.franja } }));
        },

        avisar(campo) {
            campo.dispatchEvent(new Event('input', { bubbles: true }));
            campo.dispatchEvent(new Event('change', { bubbles: true }));
        },

        anunciar(t) { this.anuncio = ''; this.$nextTick(() => { this.anuncio = t; }); }
    }"
    x-modelable="franja"
    data-mes="{{ $mesIso }}"
    data-rotulo-mes="{{ $rotuloMes($mesIso) }}"
    data-dia="{{ $diaSel }}"
    data-foco="{{ $focoIni }}"
    data-elegida="{{ $franjaSel }}"
    data-operacion="{{ $operacionOk }}"
    {{ $attributes->merge(['class' => 'muni-ag', 'id' => $agId, 'role' => 'group', 'aria-label' => $label]) }}
>
    <input type="hidden" name="{{ $name }}_dia" value="{{ $diaSel }}" x-ref="campoDia">
    <input type="hidden" name="{{ $name }}_mes" value="{{ $mesIso }}">

    <div class="muni-ag__mes">
        @if ($navEnlace)
            <a href="{{ $hrefPrev }}" class="muni-ag__nav" x-ref="prev" aria-label="Mes anterior, {{ $rotuloMes($mesAnterior) }}"><span aria-hidden="true">‹</span></a>
        @elseif ($navEvento)
            <button type="button" class="muni-ag__nav" x-ref="prev" data-mes="{{ $mesAnterior }}" data-rotulo="{{ $rotuloMes($mesAnterior) }}"
                    aria-label="Mes anterior, {{ $rotuloMes($mesAnterior) }}"
                    :aria-disabled="cargando !== '' ? 'true' : null"
                    @click="pedirMes($el)"><span aria-hidden="true">‹</span></button>
        @endif

        <p class="muni-ag__titulo" id="{{ $agId }}-titulo" aria-live="polite" aria-atomic="true">{{ $tituloMes }}</p>

        @if ($navEnlace)
            <a href="{{ $hrefNext }}" class="muni-ag__nav" x-ref="next" aria-label="Mes siguiente, {{ $rotuloMes($mesSiguiente) }}"><span aria-hidden="true">›</span></a>
        @elseif ($navEvento)
            <button type="button" class="muni-ag__nav" x-ref="next" data-mes="{{ $mesSiguiente }}" data-rotulo="{{ $rotuloMes($mesSiguiente) }}"
                    aria-label="Mes siguiente, {{ $rotuloMes($mesSiguiente) }}"
                    :aria-disabled="cargando !== '' ? 'true' : null"
                    @click="pedirMes($el)"><span aria-hidden="true">›</span></button>
        @endif
    </div>

    @if ($errorTexto !== null)
        <p class="muni-ag__error" role="alert"><span aria-hidden="true">✕</span> {{ $errorTexto }}</p>
    @endif
    <p class="muni-ag__error" hidden :hidden="fallo === ''"><span aria-hidden="true">✕</span> <span x-text="fallo"></span></p>
    <p class="muni-ag__cargando" hidden :hidden="cargando === ''" x-text="cargando !== '' ? 'Cargando ' + cargando + '…' : ''"></p>

    <table class="muni-ag__grid" role="grid" aria-labelledby="{{ $agId }}-titulo" x-ref="rejilla"
           :aria-busy="cargando !== '' ? 'true' : null" @keydown="tecla($event)">
        <thead>
            <tr role="row">
                @foreach ($diasCortos as $i => $corto)
                    <th scope="col" role="columnheader" class="muni-ag__dow">
                        <span aria-hidden="true">{{ $corto }}</span><span class="muni-ag__sr">{{ $diasEs[$i] }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($semanas as $f => $semana)
                <tr role="row">
                    @foreach ($semana as $c => $numero)
                        @if ($numero === null)
                            <td role="gridcell" class="muni-ag__celda"></td>
                        @else
                            @php $iso = sprintf('%04d-%02d-%02d', $anio, $mesNum, $numero); $info = $delDia[$iso]; @endphp
                            <td role="gridcell" class="muni-ag__celda" data-celda-dia="{{ $iso }}"
                                aria-selected="{{ $iso === $diaSel ? 'true' : 'false' }}"
                                :aria-selected="dia === $el.dataset.celdaDia ? 'true' : 'false'">
                                <button type="button" class="muni-ag__dia{{ $iso === $diaSel ? ' muni-ag__dia--on' : '' }}"
                                        data-celda="{{ $iso }}" data-fila="{{ $f }}" data-col="{{ $c }}"
                                        data-fecha="{{ $info['fecha'] }}"
                                        data-label="{{ $info['etiqueta'] }}"
                                        tabindex="{{ $iso === $focoIni ? '0' : '-1' }}"
                                        aria-label="{{ $info['etiqueta'] }}{{ $iso === $diaSel ? ', elegido' : '' }}"
                                        :tabindex="foco === $el.dataset.celda ? 0 : -1"
                                        :aria-label="$el.dataset.label + (dia === $el.dataset.celda ? ', elegido' : '')"
                                        :class="{ 'muni-ag__dia--on': dia === $el.dataset.celda }"
                                        @focus="foco = $el.dataset.celda"
                                        @click="elegir($el.dataset.celda)">
                                    <span class="muni-ag__num" aria-hidden="true">{{ $info['numero'] }}</span>
                                    <span class="muni-ag__carga{{ $info['lleno'] ? ' muni-ag__carga--lleno' : '' }}" aria-hidden="true">{{ $info['visible'] }}</span>
                                </button>
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="muni-ag__sr" aria-live="polite" aria-atomic="true" x-text="anuncio"></p>

    @foreach ($porDia as $isoDia => $lista)
        <fieldset class="muni-ag__franjas" data-panel="{{ $isoDia }}" tabindex="-1"@if ($isoDia !== $diaSel) hidden @endif
                  :hidden="dia !== $el.dataset.panel" @keydown.escape="cancelar($event)">
            <legend class="muni-ag__legend">Horas del {{ $etiquetaLarga($isoDia) }}</legend>
            @if (count($lista) === 0)
                <p class="muni-ag__ayuda">Este día no tiene horas publicadas.</p>
            @else
                <ul class="muni-ag__lista">
                    @foreach ($lista as $f)
                        @php
                            $hora = $f['fin'] !== null ? $f['inicio'].' a '.$f['fin'] : $f['inicio'];
                            $anuncioF = $hora.', '.mb_strtolower($rotuloEstado[$f['estado']]);
                        @endphp
                        <li>
                            <label class="muni-ag__franja muni-ag__franja--{{ $f['estado'] }}">
                                <input type="radio" class="muni-ag__radio" name="{{ $name }}" value="{{ $f['valor'] }}"
                                       data-franja="{{ $anuncioF }}"
                                       @checked($isoDia === $diaSel && $f['valor'] === $franjaSel && $f['estado'] === 'libre')
                                       @disabled($f['estado'] !== 'libre')
                                       @change="elegirFranja($event.target)">
                                <span class="muni-ag__hora muni-num">{{ $hora }}</span>
                                <span class="muni-ag__estado"><span aria-hidden="true">{{ $marcaEstado[$f['estado']] }}</span> {{ $rotuloEstado[$f['estado']] }}</span>
                                @if ($f['detalle'] !== '')
                                    <span class="muni-ag__detalle">{{ $f['detalle'] }}</span>
                                @endif
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif
        </fieldset>
    @endforeach

    <p class="muni-ag__vacio" x-ref="vacio"@if ($diaSel !== null && isset($porDia[$diaSel])) hidden @endif
       :hidden="hayPanel()" x-text="textoVacio()">{{ $textoVacio }}</p>

    <div class="muni-ag__pie">
        @isset($acciones)
            {{ $acciones }}
        @else
            <button type="button" class="muni-ag__accion" aria-describedby="{{ $agId }}-ayuda"
                    @if ($franjaSel === '') aria-disabled="true" @endif
                    :aria-disabled="franjaValida() ? null : 'true'"
                    @click="accionar()">{{ $accion }}</button>
            <p class="muni-ag__ayuda" id="{{ $agId }}-ayuda">Elige un día y una hora libre para continuar. La reserva la confirma el servidor.</p>
        @endisset
    </div>
</div>

@once
    <style>
        .muni-ag { display:block; padding:14px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius); box-shadow:var(--muni-shadow); font-family:var(--muni-font-sans); color:var(--muni-text); }
        .muni-ag__mes { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; }
        .muni-ag__titulo { margin:0; font-size:14px; font-weight:700; text-transform:capitalize; color:var(--muni-text); }
        .muni-ag__nav { display:inline-flex; align-items:center; justify-content:center; min-width:44px; min-height:44px; border:1px solid var(--muni-border); background:var(--muni-surface); color:var(--muni-text); border-radius:var(--muni-radius-sm); font-size:18px; line-height:1; cursor:pointer; text-decoration:none; transition:border-color var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-ag__nav:hover { border-color:var(--muni-accent); color:var(--muni-accent); }
        .muni-ag__nav[aria-disabled="true"] { color:var(--muni-muted); cursor:progress; }
        .muni-ag__nav:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-ag__grid { width:100%; border-collapse:collapse; table-layout:fixed; }
        .muni-ag__dow { padding:0 0 6px; font-family:var(--muni-font-mono); font-size:11px; font-weight:600; color:var(--muni-hint); text-align:center; }
        .muni-ag__celda { padding:1px; }
        .muni-ag__dia { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; width:100%; min-height:44px; padding:4px 2px; border:1px solid transparent; background:var(--muni-surface-2); color:var(--muni-text); border-radius:var(--muni-radius-sm); font-family:var(--muni-font-mono); cursor:pointer; transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-ag__num { font-size:13px; font-weight:700; }
        .muni-ag__carga { font-size:10.5px; color:var(--muni-hint); }
        .muni-ag__carga--lleno { color:var(--muni-danger-fg); background:var(--muni-danger-bg); border-radius:var(--muni-radius-sm); padding:0 4px; }
        .muni-ag__dia:hover { background:var(--muni-surface-3); }
        .muni-ag__dia:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        {{-- `.muni-ag__dia:hover` (0,2,0) le gana a `--on` (0,1,0) por especificidad,
             sin importar el orden: es lo que en calendar dejaba --muni-on-accent sobre
             --muni-surface-2 (1,08:1 medido). Lo que lo impide es `--on:hover`, que
             tiene la MISMA especificidad (0,2,0) que `:hover` y gana por ir DESPUÉS.
             Si se reordena, vuelve el 1,08:1. --}}
        .muni-ag__dia--on { background:var(--muni-accent); color:var(--muni-on-accent); }
        .muni-ag__dia--on .muni-ag__carga, .muni-ag__dia--on .muni-ag__carga--lleno { color:var(--muni-on-accent); background:transparent; }
        .muni-ag__dia--on:hover { background:var(--muni-accent-strong); }
        .muni-ag__franjas { margin:12px 0 0; padding:0; border:0; }
        {{-- Guarda, no arreglo: hoy el fieldset no declara display y el atributo
             `hidden` le basta. Pero la regla del navegador para `hidden` tiene la
             especificidad más baja posible, así que cualquier hoja del anfitrión que
             le dé display a un fieldset o a un párrafo haría visibles TODOS los días
             a la vez. Lo mide tests/navegador/agenda-de-horas.py inyectando esa hoja. --}}
        .muni-ag__franjas[hidden] { display:none; }
        .muni-ag__franjas:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        .muni-ag__legend { padding:0; font-size:12.5px; font-weight:700; color:var(--muni-text); }
        .muni-ag__lista { list-style:none; margin:8px 0 0; padding:0; display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:6px; }
        .muni-ag__franja { display:flex; flex-wrap:wrap; align-items:center; gap:8px; min-height:44px; padding:6px 10px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-sm); cursor:pointer; }
        .muni-ag__franja:focus-within { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-ag__radio { flex:none; width:16px; height:16px; margin:0; accent-color:var(--muni-accent); }
        .muni-ag__hora { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; font-size:12.5px; color:var(--muni-text); }
        .muni-ag__estado { margin-left:auto; padding:2px 6px; border:1px solid transparent; border-radius:var(--muni-radius-sm); font-size:11px; white-space:nowrap; }
        .muni-ag__detalle { flex-basis:100%; min-width:0; font-size:11px; color:var(--muni-hint); overflow-wrap:anywhere; }
        .muni-ag__franja--libre .muni-ag__estado { background:var(--muni-ok-bg); color:var(--muni-ok-fg); border-color:var(--muni-ok-border); }
        .muni-ag__franja--tomada { background:var(--muni-surface-2); cursor:not-allowed; }
        .muni-ag__franja--tomada .muni-ag__estado { background:var(--muni-warn-bg); color:var(--muni-warn-fg); border-color:var(--muni-warn-border); }
        .muni-ag__franja--bloqueada { background:var(--muni-surface-2); cursor:not-allowed; }
        .muni-ag__franja--bloqueada .muni-ag__estado { background:var(--muni-danger-bg); color:var(--muni-danger-fg); border-color:var(--muni-danger-border); }
        .muni-ag__vacio { margin:12px 0 0; font-size:12.5px; color:var(--muni-muted); }
        .muni-ag__vacio[hidden], .muni-ag__cargando[hidden], .muni-ag__error[hidden] { display:none; }
        .muni-ag__cargando { margin:0 0 8px; font-size:12.5px; color:var(--muni-muted); }
        .muni-ag__error { margin:0 0 8px; padding:8px 10px; font-size:12.5px; line-height:1.45; color:var(--muni-danger-fg); background:var(--muni-danger-bg); border:1px solid var(--muni-danger-border); border-radius:var(--muni-radius-sm); overflow-wrap:anywhere; }
                .muni-ag__pie { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:12px; }
        .muni-ag__accion { min-height:44px; padding:0 16px; border:1px solid var(--muni-accent); background:var(--muni-accent); color:var(--muni-on-accent); border-radius:var(--muni-radius-sm); font-family:var(--muni-font-sans); font-size:13px; font-weight:600; cursor:pointer; transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-ag__accion:hover { background:var(--muni-accent-strong); border-color:var(--muni-accent-strong); }
        .muni-ag__accion:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-ag__accion[aria-disabled="true"] { background:var(--muni-surface-2); color:var(--muni-muted); border-color:var(--muni-border-2); cursor:not-allowed; }
        .muni-ag__ayuda { margin:0; font-size:11.5px; color:var(--muni-hint); }
        .muni-ag__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        {{-- Guardia local, como en calendar: muni-ui.css baja --muni-dur a 0 ms con
             movimiento reducido, pero la hoja del panel —la única que se carga
             dentro de Filament— no lo hace. --}}
        @media (prefers-reduced-motion: reduce) { .muni-ag__nav, .muni-ag__dia, .muni-ag__accion { transition:none; } }
        @media (max-width: 460px) { .muni-ag__lista { grid-template-columns:1fr; } }
    </style>
@endonce
