@props([
    'width' => '240px',
    /* Punto de quiebre entre columna fija y panel superpuesto. `null` toma el del
       paquete, que está escrito una sola vez en el bloque de abajo. Se acepta
       tanto «960px» como «960». */
    'breakpoint' => null,
    'label' => 'Navegación principal',
])

@php
    /*
     * EL PUNTO DE QUIEBRE, UNA SOLA VEZ.
     *
     * Antes estaba escrito cinco veces —dos consultas de medios acá, dos en
     * `dashboard-shell` y un `window.innerWidth >= 900` en el estado de Alpine—,
     * así que cambiarlo exigía acertar en cinco sitios y el JS y el CSS se
     * desincronizaban en silencio. Ahora sale de acá y viaja a dos destinos: la
     * ÚNICA consulta de medios del componente y el ÚNICO `matchMedia`.
     *
     * El armazón ya no lo conoce: se entera del modo superpuesto por el evento
     * `muni-sidebar-state`, que este componente despacha.
     *
     * SANEADO, no interpolado a lo bruto. El valor entra a una consulta de medios
     * y a una expresión de Alpine: un apóstrofo o un paréntesis sueltos tumban el
     * Alpine de la página entera (es lo que ya pasó en file-dropzone). Si no es un
     * ancho en píxeles, se cae al valor del paquete. Fail closed.
     */
    $bpPx = 900;

    if (preg_match('/^\s*(\d{2,4})(?:px)?\s*$/', (string) $breakpoint, $m)) {
        $bpPx = (int) $m[1];
    }

    /* 0,02px por debajo: el panel superpuesto termina justo antes de que empiece
       la columna fija, sin la franja de un píxel donde valen las dos. */
    $bpMax = ($bpPx - 0.02).'px';
@endphp

{{-- Barra lateral de navegación para dashboards.

     DOS MODOS, y el que manda es la consulta de medios, no una clase CSS:

       · COLUMNA (ancho >= punto de quiebre): hermana en el flex del armazón,
         siempre visible, sin trampa de foco. Atrapar el foco acá dejaría al
         funcionario encerrado en el menú, que es peor que el defecto original.
       · SUPERPUESTA (bajo el punto de quiebre): panel modal sobre el contenido,
         con velo, trampa de foco, Escape y devolución del foco al hamburguesa.

     CERRADA NO RECIBE EL FOCO. `transform:translateX(-100%)` aparta el panel de
     la vista pero NO lo saca del orden de tabulación: con Tab el teclado entraba
     a quince enlaces invisibles fuera de pantalla (WCAG 2.2 AA 2.4.3 y 2.4.7).
     Se cierra con `inert` —que además lo saca del árbol de accesibilidad— y con
     `visibility:hidden` de respaldo para los navegadores viejos del municipio,
     que sí saca del tabulador y sí es universal.

     NO PERSISTE NADA. Ni almacenamiento local (parpadea en la primera pintura)
     ni cookie: `wire:navigate` remonta el body y que el menú se cierre al
     navegar es el comportamiento que se quiere.

     EL ESTADO SE DEVUELVE POR EVENTO. Escucha `muni-sidebar` (alternar),
     `muni-sidebar-close` (cerrar) y `muni-sidebar-ping` (a quién le interese su
     estado ahora mismo), y despacha `muni-sidebar-state` con `{open, overlay,
     id}`. Por eso el hamburguesa del armazón puede decir la verdad en su
     `aria-expanded` sin compartir scope de Alpine, y por eso este componente
     sigue sirviendo suelto, fuera del armazón.

     SIGUE SIENDO `<aside>` A PROPÓSITO. El landmark que corresponde a la
     navegación principal es `navigation`, no `complementary`, pero cambiar el
     elemento a `<nav>` rompería el CSS de los consumidores que seleccionan por
     etiqueta (`aside .algo`) y eso es cambio de versión menor, no un parche.
     Se resuelve de forma aditiva: el elemento no cambia, `role="navigation"`
     pisa el rol implícito y `aria-label` le da el nombre que no tenía. Ambos van
     dentro de la fusión, así que el consumidor puede poner los suyos sin que se
     emitan duplicados.

     El slot son x-muni::nav-item y x-muni::nav-section. Alpine 3 core + el plugin
     Focus, que ya viaja en el bundle de Livewire de los paneles. --}}
<aside
    x-data="{
        open: false,
        overlay: false,
        mq: null,

        init() {
            this.mq = window.matchMedia(@js('(max-width: '.$bpMax.')'));
            this.leerMedia();
            /* El CRUCE del punto de quiebre, no cada `resize`: el teclado del
               móvil y la barra de URL cambian el alto y no deben mover nada. */
            this.mq.addEventListener('change', () => this.leerMedia());
            /* El armazón se inicializa después que esta lateral, así que su
               escucha todavía no existe cuando corre este init: se reemite en
               cuanto Alpine termina de montar el árbol. */
            this.$nextTick(() => this.emitir());
        },

        leerMedia() {
            this.overlay = this.mq.matches;
            /* En columna está siempre a la vista; al pasar a superpuesta se
               cierra. Esto es lo que arregla la tablet que se rota: antes `open`
               se calculaba una vez con innerWidth y no se recalculaba nunca. */
            this.open = ! this.overlay;
            this.emitir();
        },

        alternar() {
            if (! this.overlay) return;
            this.open = ! this.open;
            this.emitir();
        },

        cerrar() {
            if (! this.overlay || ! this.open) return;
            this.open = false;
            this.emitir();
        },

        emitir() {
            this.$dispatch('muni-sidebar-state', { open: this.open, overlay: this.overlay, id: this.$el.id });
        },
    }"
    @muni-sidebar.window="alternar()"
    @muni-sidebar-close.window="cerrar()"
    @muni-sidebar-ping.window="emitir()"
    {{-- Escape acá y NUNCA en window: command-palette y dropdown ya lo enlazan
         en window por instancia y se pisan entre sí. Con el foco atrapado
         dentro, la tecla llega igual por burbujeo. --}}
    @keydown.escape="cerrar()"
    {{-- Atado al matchMedia, jamás a la clase CSS. --}}
    x-trap.inert.noscroll="overlay && open"
    :inert="overlay && ! open"
    :class="open ? 'muni-sb--open' : ''"
    {{ $attributes->merge([
        'id' => 'muni-sidebar',
        'role' => 'navigation',
        'aria-label' => $label,
        /* Destino de respaldo de la trampa de foco: si la navegación llegara
           vacía, el foco aterriza en el panel y no se escapa al fondo. */
        'tabindex' => '-1',
        'class' => 'muni-sb',
        'style' => "--sb-w:{$width};",
    ]) }}
>
    <div class="muni-sb__inner">
        {{ $slot }}
    </div>
</aside>

@once
    <style>
        .muni-sb { flex-shrink:0; width:var(--sb-w); background:var(--muni-surface); border-right:1px solid var(--muni-border); }
        .muni-sb:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-3px; }
        .muni-sb__inner { position:sticky; top:0; display:flex; flex-direction:column; gap:2px; height:100vh; overflow-y:auto; padding:16px 12px; }
        /* La ÚNICA consulta de medios del componente, y el mismo valor que lee el
           matchMedia. El bloque de estilos se emite una sola vez por página: si
           dos laterales pidieran puntos de quiebre distintos, manda la primera.
           `visibility` es el respaldo de `inert` para los navegadores viejos:
           translateX no saca del orden de tabulación, visibility sí. Se retrasa
           el ocultamiento hasta el final del deslizamiento con `--muni-dur`, que
           ya baja a 0 ms con movimiento reducido. */
        @media (max-width: {{ $bpMax }}) {
            .muni-sb { position:fixed; inset:0 auto 0 0; z-index:150; transform:translateX(-100%); visibility:hidden;
                transition:transform var(--muni-dur) var(--muni-ease), visibility 0s linear var(--muni-dur);
                box-shadow:var(--muni-shadow-lg); }
            .muni-sb--open { transform:translateX(0); visibility:visible; transition:transform var(--muni-dur) var(--muni-ease), visibility 0s; }
        }
    </style>
@endonce
