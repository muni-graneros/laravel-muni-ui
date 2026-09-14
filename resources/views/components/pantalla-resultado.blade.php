@props([
    'title' => null,
    'message' => null,
    'folio' => null,
    'folioLabel' => 'Folio',
    'copyLabel' => 'Copiar folio',
    'tone' => 'ok',
    'printHref' => null,
    'printLabel' => 'Imprimir comprobante',
    'exitHref' => null,
    'exitLabel' => 'Volver al inicio',
    'autofocus' => true,
])

{{-- El cierre de un trámite: «Su solicitud fue recibida. Folio 2026-04871».

     Hasta ahora el paquete no cerraba ningún flujo: el único «Enviado» vivía pegado
     al final de demo/wizard.html y no se podía reutilizar, así que después de guardar
     el funcionario se quedaba mirando el mismo formulario sin saber si pasó algo.

     Cuatro cosas son el componente, y ninguna es decorativa:

     1. UN SOLO <h1>, enfocable y enfocado. El foco va al título al cargar, que es lo
        que hace que un lector de pantalla lea el resultado sin que nadie lo busque
        (WCAG 2.2 AA 2.4.3 y 4.1.3). Va por el atributo `autofocus` —así funciona sin
        una línea de JS— y Alpine lo repite solo si NADIE tenía el foco todavía: dentro
        de una página que repinta Livewire, robarle el foco al funcionario que está
        escribiendo es peor que no anunciar nada.
     2. EL FOLIO ES TEXTO, en mono tabular (DESIGN §9), y se copia con un botón real que
        confirma CON PALABRAS. Un icono verde no es una confirmación: no lo lee un lector
        de pantalla y no lo distingue quien no ve el verde.
     3. UN SOLO CAMINO DE SALIDA, y es lo último enfocable de la pantalla. Dos botones
        del mismo peso en un cierre es justo lo que manda al vecino a llamar por teléfono.
     4. NADA del anfitrión entra en una expresión de Alpine. El folio viaja por `@js()`:
        un apóstrofo dentro de una cadena de comillas simples descuadra la expresión y
        tumba el Alpine de la página ENTERA, no solo el de este componente. Ya pasó en
        `file-dropzone`.

     Lo que el componente NO hace, a propósito:

     · No genera el folio ni lo deriva de nada suyo: llega ya redactado por el anfitrión
       (Ley 21.719, minimización — acá no entra un modelo ni un registro completo). Y si
       ese folio sirve para consultar el estado sin autenticación, no puede ser secuencial
       adivinable y la pantalla de verificación no puede filtrar datos personales a quien
       tenga el número: las dos cosas se deciden en el anfitrión, no acá.
     · No inventa el enlace a imprimir ni la salida. Sin `print-href` no hay botón de
       imprimir, y sin `exit-href` no hay salida: un control que no lleva a ninguna parte
       es peor que no ofrecerlo.
     · No pone links dentro del texto libre. Lo que va en la ranura por defecto es prosa
       («le llegará un correo cuando cambie el estado»): un enlace ahí dejaría de ser
       cierto que la salida es lo último enfocable.

     El botón de copiar nace con `x-cloak`. Si el anfitrión sirve esta pantalla sin
     Alpine —una landing pública sin Livewire—, el botón no aparece en vez de aparecer
     roto, y el folio sigue ahí, visible y seleccionable de un clic. --}}

@php
    // Cuatro tonos, cada uno con su propio par fg/bg/border y con DIBUJO DISTINTO:
    // el color no puede ser el único portador del resultado (WCAG 2.2 AA 1.4.1), y el
    // portador real es el texto del título.
    $tonos = ['ok', 'warn', 'danger', 'info'];
    $tonoResuelto = in_array($tone, $tonos, true) ? $tone : 'ok';

    $dibujos = [
        'ok' => '<path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/>',
        'warn' => '<path d="M12 4.5L2.8 20h18.4L12 4.5z" stroke-linejoin="round"/><path d="M12 10v4.2M12 17.2v.1" stroke-linecap="round"/>',
        'danger' => '<circle cx="12" cy="12" r="8.6"/><path d="M8.6 8.6l6.8 6.8M15.4 8.6l-6.8 6.8" stroke-linecap="round"/>',
        'info' => '<circle cx="12" cy="12" r="8.6"/><path d="M12 11v5.4M12 7.6v.1" stroke-linecap="round"/>',
    ];

    /*
     * El id sale de las props, NUNCA de uniqid() (DESIGN §10): con uniqid() cambia en
     * cada render, rompe el `aria-describedby` del botón de copiar y ensucia el diffing
     * de Livewire. El que pase el consumidor manda.
     */
    $resId = $attributes->get('id')
        ?: 'muni-res-'.substr(sha1(((string) $folio).'|'.((string) $title)), 0, 8);
@endphp

<section
    x-data="{
        folio: @js((string) $folio),
        aviso: '',

        init() {
            const titulo = this.$refs.titulo;
            if (! titulo) return;

            /* Solo si NADIE tiene el foco: si el funcionario ya estaba en un control,
               arrancárselo es peor que no anunciar. Con `autofocus` el navegador ya
               enfocó el título y esto no hace nada. */
            const activo = document.activeElement;
            if (activo && activo !== document.body && activo !== document.documentElement) return;

            titulo.focus();
        },

        async copiar() {
            let copiado = false;

            try {
                if (window.isSecureContext && navigator.clipboard) {
                    await navigator.clipboard.writeText(this.folio);
                    copiado = true;
                }
            } catch (e) {
                copiado = false;
            }

            /* La intranet municipal sirve por http y ahí `navigator.clipboard` no
               existe: sin esta rama el botón no haría absolutamente nada. */
            if (! copiado) copiado = this.copiarALaAntigua();

            if (! copiado) this.seleccionar();

            this.aviso = copiado
                ? 'Folio copiado: ' + this.folio
                : 'No se pudo copiar. El folio quedó seleccionado: cópialo con Control y C.';
        },

        copiarALaAntigua() {
            try {
                const caja = document.createElement('textarea');
                caja.value = this.folio;
                caja.setAttribute('readonly', '');
                caja.style.position = 'fixed';
                caja.style.top = '0';
                caja.style.opacity = '0';
                document.body.appendChild(caja);
                caja.select();
                const hecho = document.execCommand('copy');
                caja.remove();
                /* El foco se fue a la caja temporal y la caja ya no existe: sin esto
                   el teclado se queda en el <body> y hay que tabular de nuevo. */
                if (this.$refs.copiar) this.$refs.copiar.focus();
                return hecho;
            } catch (e) {
                return false;
            }
        },

        seleccionar() {
            try {
                const rango = document.createRange();
                rango.selectNodeContents(this.$refs.valor);
                const seleccion = window.getSelection();
                seleccion.removeAllRanges();
                seleccion.addRange(rango);
            } catch (e) {}
        }
    }"
    aria-labelledby="{{ $resId }}-titulo"
    {{ $attributes->merge(['class' => 'muni-res']) }}
>
    <span class="muni-res__icono" aria-hidden="true"
          style="--mres-fg:var(--muni-{{ $tonoResuelto }}-fg);--mres-bg:var(--muni-{{ $tonoResuelto }}-bg);--mres-br:var(--muni-{{ $tonoResuelto }}-border);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="30" height="30">{!! $dibujos[$tonoResuelto] !!}</svg>
    </span>

    <h1 id="{{ $resId }}-titulo" class="muni-res__titulo" tabindex="-1" x-ref="titulo" @if ($autofocus) autofocus @endif>{{ $title }}</h1>

    @if ($message)
        <p class="muni-res__mensaje">{{ $message }}</p>
    @endif

    @if ($folio)
        <div class="muni-res__folio">
            <span class="muni-res__rotulo">{{ $folioLabel }}</span>
            <span id="{{ $resId }}-folio" class="muni-res__valor muni-num" x-ref="valor">{{ $folio }}</span>
            <button type="button" class="muni-res__copiar" x-ref="copiar" x-cloak
                    @click="copiar()" aria-describedby="{{ $resId }}-folio">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" width="15" height="15" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a1 1 0 01-1-1V4a1 1 0 011-1h10a1 1 0 011 1v1" stroke-linecap="round"/></svg>
                {{ $copyLabel }}
            </button>
        </div>
    @endif

    {{-- Nace VACÍA: una región viva que aparece junto a su texto no se anuncia en NVDA,
         JAWS ni VoiceOver. Y reserva su línea, para que el aviso no empuje los botones
         hacia abajo cuando llega (CLS). --}}
    <p class="muni-res__aviso" role="status" aria-live="polite" x-text="aviso"></p>

    @if (trim($slot) !== '')
        <div class="muni-res__detalle">{{ $slot }}</div>
    @endif

    <div class="muni-res__acciones">
        @if ($printHref)
            <a class="muni-res__accion" href="{{ $printHref }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" width="16" height="16" aria-hidden="true"><path d="M7 9V3.8h10V9" stroke-linejoin="round"/><rect x="3.5" y="9" width="17" height="7.5" rx="2"/><path d="M7 14.5h10v5.7H7z" stroke-linejoin="round"/></svg>
                {{ $printLabel }}
            </a>
        @endif
        @if ($exitHref)
            <a class="muni-res__salida" href="{{ $exitHref }}">{{ $exitLabel }}</a>
        @endif
    </div>
</section>

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css, y una clase declarada únicamente en muni-ui.css
           se vería sin estilo y sin un solo error en consola (DESIGN §7). */
        .muni-res { display:flex; flex-direction:column; align-items:center; text-align:center; gap:14px;
            padding:48px 24px; font-family:var(--muni-font-sans); color:var(--muni-text); }
        .muni-res [x-cloak] { display:none !important; }
        .muni-res__icono { display:grid; place-items:center; width:64px; height:64px; border-radius:999px;
            color:var(--mres-fg); background:var(--mres-bg); border:1px solid var(--mres-br); }
        /* El título recibe el foco por programa, así que el indicador va en :focus y no
           en :focus-visible, que en varios navegadores no se activa sin teclado. */
        .muni-res__titulo { margin:0; font-size:23px; font-weight:800; letter-spacing:-.02em; color:var(--muni-text); max-width:24ch; }
        .muni-res__titulo:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:6px; border-radius:var(--muni-radius-sm); }
        .muni-res__mensaje { margin:0; max-width:52ch; font-size:14.5px; line-height:1.6; color:var(--muni-muted); }
        .muni-res__folio { display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:12px;
            padding:12px 14px; background:var(--muni-surface-2); border:1px solid var(--muni-border); border-radius:var(--muni-radius); }
        .muni-res__rotulo { font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muni-muted); }
        /* DESIGN §9: el folio es una cifra y va en mono tabular. `user-select:all` para
           que un clic lo seleccione entero cuando el portapapeles no está disponible. */
        .muni-res__valor { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; font-size:20px;
            font-weight:700; letter-spacing:.02em; color:var(--muni-text); user-select:all; }
        .muni-res__copiar { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px;
            padding:0 14px; font-family:var(--muni-font-sans); font-size:13px; font-weight:600; color:var(--muni-text);
            background:var(--muni-surface); border:1px solid var(--muni-field-border); border-radius:var(--muni-radius-sm);
            cursor:pointer; transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-res__copiar:hover { background:var(--muni-surface-2); border-color:var(--muni-accent); }
        /* El outline es el indicador REAL: la box-shadow del anillo se computa
           transparente dentro de Filament (DESIGN §5). */
        .muni-res__copiar:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        /* Reserva su línea aunque esté vacía: cuando llega el aviso no empuja los botones. */
        .muni-res__aviso { margin:0; min-height:1.5em; font-size:13px; font-weight:600; color:var(--muni-text); }
        .muni-res__detalle { max-width:52ch; font-size:13.5px; line-height:1.6; color:var(--muni-muted); }
        .muni-res__acciones { display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:12px; margin-top:6px; }
        .muni-res__accion, .muni-res__salida { display:inline-flex; align-items:center; justify-content:center; gap:8px;
            min-height:44px; padding:0 20px; font-family:var(--muni-font-sans); font-size:14px; font-weight:600;
            text-decoration:none; border-radius:var(--muni-radius-sm); border:1px solid transparent;
            transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease); }
        .muni-res__accion { color:var(--muni-text); background:var(--muni-surface); border-color:var(--muni-field-border); }
        .muni-res__accion:hover { background:var(--muni-surface-2); border-color:var(--muni-accent); }
        .muni-res__salida { color:var(--muni-on-accent); background:var(--muni-accent); font-weight:700; }
        .muni-res__salida:hover { background:var(--muni-accent-strong); }
        .muni-res__accion:focus-visible, .muni-res__salida:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        @media (max-width:480px) {
            .muni-res { padding:32px 16px; }
            .muni-res__acciones { flex-direction:column; align-items:stretch; align-self:stretch; }
        }
        /* En papel el folio y el resultado son lo único que importa: los controles no
           hacen nada impresos y el aviso del portapapeles tampoco. */
        @media print {
            .muni-res__acciones, .muni-res__copiar, .muni-res__aviso { display:none !important; }
        }
        @media (prefers-reduced-motion:reduce) {
            .muni-res__copiar, .muni-res__accion, .muni-res__salida { transition:none; }
        }
    </style>
@endonce
