@props([
    'action', // URL a la que el formulario envía la elección
    'value' => null, // sistema | claro | oscuro (acepta system | light | dark)
    'name' => 'muni-tema', // nombre del campo; también base de los ids
    'label' => 'Tema', // rótulo del grupo
    'hint' => 'Con «Sistema» se sigue la preferencia del equipo.', // ayuda bajo el grupo
    'submitLabel' => 'Guardar', // texto del botón de guardar
    'enabled' => true, // false no emite nada (sistema con tema fijo)
])

{{-- Selector de tema: sistema / claro / oscuro, para que el funcionario elija y la
     elección se recuerde. Es un envoltorio de <x-muni::segmented> —radios reales,
     el teclado lo da el navegador— dentro de un <form method="post">.

     CÓMO SE PERSISTE, y por qué así. La elección vive en una COOKIE que escribe una
     ruta del anfitrión, nunca en localStorage: con localStorage haría falta un
     script en línea en el <head> para evitar el parpadeo, y bajo la CSP con nonce
     del ecosistema ese script es un costo real. Con la cookie leída en el servidor
     el tema llega ya resuelto en el `theme` del armazón y no parpadea nada.

     Contrato con el anfitrión (el paquete no consulta request(), Auth ni Gate):

       1. Una ruta POST con CSRF y throttle que valide `muni-tema` en
          {sistema, claro, oscuro}, guarde la cookie (`cookie()->forever(...)`) y
          responda `noContent()` cuando la petición pida JSON, o `back()` si no.
       2. El anfitrión lee la cookie y pasa al armazón
          `theme` = ['claro' => 'light', 'oscuro' => 'dark'][$cookie] ?? null,
          y al selector `value` = la cookie tal cual (o el mismo light/dark: lo entiende).

     Con Alpine, elegir aplica el tema AL INSTANTE sobre el <html> (data-muni-theme,
     que es el activador del paquete) y guarda por fetch sin recargar; el estado se
     dice en texto visible con role="status". Sin JS es un formulario: botón Guardar,
     POST, recarga con el tema leído de la cookie.

     `segmented` envía el formulario al elegir (un `x-on:change` con requestSubmit()
     en cada radio, que ya no se dispara con las flechas). Para que ese envío no le
     gane al camino de Alpine, el formulario escucha `change` en CAPTURA y detiene
     la propagación antes de que llegue al radio. Sin Alpine no hay autoenvío y el
     botón Guardar está siempre; el «Aplicar» de respaldo de `segmented` no aparece,
     porque este formulario ya trae su botón.

     DÓNDE NO VA. Nunca dentro de un panel Filament: el panel ya trae su conmutador
     con persistencia propia, que muni-ui respeta vía `.dark`; dos conmutadores son
     dos verdades que se pisan al recargar. Y se desactiva por sistema con
     `:enabled="false"` (discapacidad tiene identidad fija en claro y le pasa
     theme="light" al armazón).

     Los textos del anfitrión (label, hint, submitLabel, name) van en HTML y jamás
     dentro de una expresión de Alpine: un apóstrofo descuadraría las comillas y
     tumbaría el Alpine de la página entera. --}}
@php
    $opciones = ['sistema' => 'Sistema', 'claro' => 'Claro', 'oscuro' => 'Oscuro'];
    $alias = ['system' => 'sistema', 'light' => 'claro', 'dark' => 'oscuro'];

    $elegido = strtolower(trim((string) $value));
    $elegido = $alias[$elegido] ?? $elegido;

    if (! array_key_exists($elegido, $opciones)) {
        $elegido = 'sistema';
    }

    // Ids deterministas: salen del name (que también es el nombre del campo que
    // recibe la ruta), nunca de un generador al azar. Se limpian para servir de id.
    $nombre = (string) $name;
    $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $nombre), '-') ?: 'muni-tema';
    $idRotulo = $base.'-rotulo';
    $idAyuda = $base.'-ayuda';
    $idEstado = $base.'-estado';
@endphp

@if ($enabled)
<form
    method="post"
    @if ($action) action="{{ $action }}" @endif
    x-data="{
        pendiente: null,
        guardando: false,
        fallo: false,
        estado: '',

        destroy() { clearTimeout(this.pendiente); },

        /* El texto visible de la opción, leído del DOM: así no se duplica en JS. */
        textoDe(valor) {
            const radio = this.$el.querySelector('input[type=radio][value=' + valor + ']');
            const pill = radio ? radio.closest('.muni-seg') : null;
            const txt = pill ? pill.querySelector('.muni-seg__txt') : null;

            return txt ? txt.textContent.trim() : valor;
        },

        elegir(evento) {
            const radio = evento.target;

            if (! radio || radio.type !== 'radio') { return; }

            this.aplicar(radio.value);
            this.estado = 'Tema «' + this.textoDe(radio.value) + '» aplicado. Guardando…';

            /* Con retardo: recorrer las tres opciones con las flechas es un solo POST, no tres. */
            clearTimeout(this.pendiente);
            this.pendiente = setTimeout(() => this.guardar(), 400);
        },

        /* Solo el activador propio del paquete. La clase .dark es de Filament y no se toca. */
        aplicar(valor) {
            const raiz = document.documentElement;

            if (valor === 'claro') { raiz.setAttribute('data-muni-theme', 'light'); }
            else if (valor === 'oscuro') { raiz.setAttribute('data-muni-theme', 'dark'); }
            else { raiz.removeAttribute('data-muni-theme'); }

            this.$el.querySelectorAll('.muni-seg').forEach((pill) => {
                const radio = pill.querySelector('input[type=radio]');
                pill.classList.toggle('muni-seg--on', !! radio && radio.value === valor);
            });
        },

        async guardar() {
            clearTimeout(this.pendiente);

            const form = this.$el;
            const marcado = form.querySelector('input[type=radio]:checked');
            const valor = marcado ? marcado.value : 'sistema';
            const texto = this.textoDe(valor);

            this.guardando = true;

            try {
                /* getAttribute y no form.action: un <input name=action> del anfitrión pisaría la propiedad. */
                const respuesta = await fetch(form.getAttribute('action') || window.location.href, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });

                if (! respuesta.ok) { throw new Error(String(respuesta.status)); }

                this.fallo = false;
                this.estado = 'Tema «' + texto + '» aplicado y guardado.';
            } catch (error) {
                this.fallo = true;
                this.estado = 'Tema «' + texto + '» aplicado en esta pestaña, pero no se pudo guardar. Pulsa Guardar para reintentar.';
            } finally {
                this.guardando = false;
            }
        }
    }"
    @change.capture.stop="elegir($event)"
    @submit.prevent="guardar()"
    {{ $attributes->merge(['class' => 'muni-tema']) }}
>
    @csrf

    <span id="{{ $idRotulo }}" class="muni-tema__rotulo">{{ $label }}</span>

    <x-muni::segmented
        :name="$nombre"
        :options="$opciones"
        :value="$elegido"
        aria-labelledby="{{ $idRotulo }}"
        aria-describedby="{{ $idAyuda }}"
    />

    <button type="submit" class="muni-tema__guardar" :aria-busy="guardando ? 'true' : null">{{ $submitLabel }}</button>

    <p id="{{ $idAyuda }}" class="muni-tema__ayuda">{{ $hint }}</p>

    {{-- Vacío en el primer render, a propósito: una región viva que nace con texto no
         se anuncia. Visible, no recortada: le sirve igual a quien no usa lector. --}}
    <p id="{{ $idEstado }}" class="muni-tema__estado" role="status" x-text="estado"></p>
</form>

@once
    <style>
        /* `color-scheme` es lo que pinta en oscuro los desplegables nativos, los
           input type=date, las barras de desplazamiento y la vista previa de
           impresión: sin él siguen en claro aunque los tokens ya sean oscuros.
           Un selector cuyo «Oscuro» deja los <select> en claro es un selector
           roto, así que viaja con él (DESIGN §7). Las cuatro declaraciones son
           LAS MISMAS que la corrección (A) del juez pide en muni-ui.css —:root
           claro, bloque del SO, activadores explícitos y congelador de claro—,
           byte a byte, para que cuando la hoja las lleve esto sea un duplicado
           inofensivo y no una segunda cascada. TODO lo oscuro va atado a
           `screen`, no solo la regla del SO: medido con la reja, un
           `[data-muni-theme="dark"]` suelto dejaba `color-scheme: dark` bajo
           media=print y un botón sin color de autor salía con el texto blanco
           del UA sobre el papel (1,00:1). Una hoja de papel no tiene modo
           oscuro, tampoco para los controles nativos (DESIGN §3). */
        :root { color-scheme: light; }
        @media screen and (prefers-color-scheme: dark) {
            :root:not([data-muni-theme="light"]):not([data-theme="light"]):not(.light) { color-scheme: dark; }
        }
        @media screen {
            [data-muni-theme="dark"], [data-theme="dark"], .dark { color-scheme: dark; }
        }
        [data-muni-theme="light"] { color-scheme: light; }

        .muni-tema { display:flex; flex-wrap:wrap; align-items:center; gap:10px 12px; margin:0; }
        .muni-tema__rotulo { font-family:var(--muni-font-sans); font-size:13px; font-weight:600; color:var(--muni-text); }
        .muni-tema__guardar { min-height:30px; padding:6px 14px; font-family:var(--muni-font-sans); font-size:12.5px; font-weight:600;
            color:var(--muni-text); background:var(--muni-surface); border:1px solid var(--muni-border-2);
            border-radius:var(--muni-radius-sm); cursor:pointer;
            transition:background var(--muni-dur) var(--muni-ease), border-color var(--muni-dur) var(--muni-ease); }
        .muni-tema__guardar:hover { background:var(--muni-surface-2); border-color:var(--muni-accent); }
        .muni-tema__guardar:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
        .muni-tema__guardar[aria-busy="true"] { cursor:progress; }
        /* `--muni-dur` ya baja a 0 ms con la preferencia en muni-ui.css; la guardia
           propia es para un anfitrión que cargue otra hoja de tokens (la del panel
           deja 160 ms), igual que en modal y command-palette. */
        @media (prefers-reduced-motion: reduce) {
            .muni-tema__guardar { transition:none; }
        }
        .muni-tema__ayuda, .muni-tema__estado { flex-basis:100%; margin:0; font-family:var(--muni-font-sans); font-size:12.5px; color:var(--muni-muted); }
        /* Reserva su línea desde el primer render: el texto que llega después no desplaza nada. */
        .muni-tema__estado { min-height:1.4em; color:var(--muni-text); }
        /* Sin JS la píldora marcada se mueve al elegir, donde `:has()` resuelve; donde
           no, queda la que renderizó el servidor, que es la elección guardada.
           La segunda regla apaga la que el servidor dejó con `.muni-seg--on` en
           cuanto el radio marcado es otro: sin ella se veían dos activas hasta
           el submit (medido en Chromium y Firefox). Con Alpine no compite: el
           componente mueve la clase al mismo radio que está marcado. */
        @supports selector(:has(*)) {
            .muni-tema .muni-seg:has(input:checked) { background:var(--muni-surface); color:var(--muni-text); box-shadow:var(--muni-shadow); }
            .muni-tema .muni-seg--on:not(:has(input:checked)) { background:transparent; color:var(--muni-muted); box-shadow:none; }
        }
    </style>
@endonce
@endif
