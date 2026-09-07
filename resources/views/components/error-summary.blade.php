@props([
    'errors' => null,
    'title' => null,
    'prefix' => 'muni-',
    'ids' => [],
    'level' => 2,
    'focus' => true,
])

@php
    /*
     * De dónde salen los errores, en este orden:
     *   1. lo que el anfitrión pase en `:errors` (MessageBag, ViewErrorBag o arreglo);
     *   2. si no pasa nada, la bolsa `$errors` que `ShareErrorsFromSession` comparte con
     *      TODAS las vistas —`@props` conserva la variable que ya existe en el ámbito, así
     *      que `<x-muni::error-summary />` a secas ya funciona en un formulario normal.
     */
    $bolsa = $errors;

    // ViewErrorBag: la bolsa por defecto es la del formulario sin nombre.
    if (is_object($bolsa) && method_exists($bolsa, 'getBag')) {
        $bolsa = $bolsa->getBag('default');
    }

    if ($bolsa instanceof \Illuminate\Contracts\Support\MessageProvider) {
        $bolsa = $bolsa->getMessageBag();
    }

    if ($bolsa instanceof \Illuminate\Contracts\Support\MessageBag) {
        $bolsa = $bolsa->messages();
    }

    /*
     * Se listan TODOS los mensajes, no uno por campo: así el recuento que se dice en voz
     * alta y la cantidad de líneas de la lista son el mismo número. Decir «3 errores» y
     * mostrar 2 líneas es peor que no decir nada.
     */
    $lista = [];

    foreach ((array) $bolsa as $clave => $mensajes) {
        foreach ((is_array($mensajes) ? $mensajes : [$mensajes]) as $mensaje) {
            if ($mensaje === null || $mensaje === '') {
                continue;
            }

            $lista[] = [
                // Una lista de mensajes sueltos (claves numéricas) no tiene campo al que ir.
                'clave' => is_int($clave) ? null : (string) $clave,
                'mensaje' => (string) $mensaje,
            ];
        }
    }

    $total = count($lista);

    /*
     * CONTRATO DE ID. `input.blade.php` y `select.blade.php` arman el id del control como
     * `'muni-'.$name` (y sin `name` caen a `uniqid()`, que cambia en cada render). La bolsa
     * `$errors`, en cambio, usa la clave pelada: `rut` → `#muni-rut`. Ese prefijo es el
     * SUPUESTO de este componente, y por eso es una prop: un anfitrión que ponga sus propios
     * ids pasa `prefix=""` o el suyo, y para un caso puntual —una clave anidada que en el
     * HTML es `direccion[calle]`— pasa el mapa `:ids="['direccion.calle' => 'mi-id']"`.
     * NO se toca input/select desde acá.
     */
    $idDe = fn (string $clave): string => (string) ($ids[$clave] ?? $prefix.$clave);

    $nivel = min(6, max(2, (int) $level));

    // El recuento va en TEXTO, no solo en el borde rojo: el color no puede ser el único
    // portador de la información.
    $encabezado = $title ?: ($total === 1
        ? 'Hay 1 error en el formulario'
        : 'Hay '.$total.' errores en el formulario');
@endphp

{{-- El resumen que aparece tras un envío fallido: cuántos errores hay, cuáles son, y cada uno
     enlazado a su campo (WCAG 2.2 AA 3.3.1 y 2.4.3; técnica G139).

     Cuatro decisiones:

     1. SIN ERRORES NO RENDERIZA NADA, ni el bloque de estilos. `role="alert"` es assertive:
        pintarlo en la primera carga interrumpe al lector de pantalla sin que haya pasado nada.
     2. RECIBE EL FOCO una sola vez. Quien envía un formulario de treinta campos desde el
        teléfono puede tener el error tres pantallas más arriba del botón que acaba de pulsar.
        `autofocus` no sirve: no dispara sobre un nodo insertado en un DOM ya cargado, que es
        exactamente el caso de Livewire. La guarda es un `data-*` en el propio nodo, así que
        vale una vez por nodo: si Livewire re-renderiza sin recrear el resumen, el foco NO se
        le quita al usuario que está escribiendo. Con `:focus="false"` no se mueve nunca.
     3. El foco del enlace se resuelve con `document.getElementById`, JAMÁS con
        `querySelector`: las claves anidadas de Laravel (`direccion.calle`) son ids válidos
        pero selectores CSS rotos —`#muni-direccion.calle` es «id direccion con clase calle»—.
        El `href` se mantiene igual para que sin JS el salto nativo siga funcionando.
     4. La lista es contenido PRIMARIO y va en `--muni-text`, no en `--muni-muted`: sobre
        `--muni-danger-bg` el texto da 14,5:1 en los dos temas, y el título en `--muni-danger-fg`
        da 5,48:1 en claro y 4,75:1 en oscuro. --}}
@if ($total > 0)
    <div
        role="alert"
        tabindex="-1"
        x-data="{
            enfocar(e) {
                const destino = document.getElementById((e.currentTarget.getAttribute('href') || '').slice(1));

                /* Sin destino en la página, el salto nativo del `href` sigue su curso. */
                if (! destino) { return; }

                e.preventDefault();

                if (typeof destino.focus === 'function') { destino.focus(); }
                if (typeof destino.scrollIntoView === 'function') { destino.scrollIntoView({ block: 'center' }); }
            }
        }"
        @if ($focus)
            x-init="if (! $el.dataset.muniEnfocado) { $el.dataset.muniEnfocado = '1'; $el.focus(); }"
        @endif
        {{ $attributes->merge(['class' => 'muni-errsum']) }}
    >
        <h{{ $nivel }} class="muni-errsum__title">{{ $encabezado }}</h{{ $nivel }}>

        <ul class="muni-errsum__list">
            @foreach ($lista as $item)
                <li class="muni-errsum__item">
                    @if ($item['clave'] !== null)
                        <a href="#{{ $idDe($item['clave']) }}" class="muni-errsum__link" @click="enfocar($event)">{{ $item['mensaje'] }}</a>
                    @else
                        <span class="muni-errsum__plain">{{ $item['mensaje'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    @once
        <style>
            .muni-errsum { padding:14px 16px; background:var(--muni-danger-bg); border:1px solid var(--muni-danger-border); border-left-width:3px; border-radius:var(--muni-radius); font-family:var(--muni-font-sans); }
            /* `:focus` y no `:focus-visible`: el foco acá SIEMPRE llega por programa, y varios
               navegadores no consideran «visible» un foco que el usuario no provocó. Sin esta
               regla, el resumen se lleva el foco sin que se vea a dónde se fue.
               El outline es el indicador REAL: la box-shadow del anillo se computa
               transparente dentro de Filament (ver --muni-focus). */
            .muni-errsum:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
            .muni-errsum__title { margin:0 0 8px; font-size:14px; font-weight:700; line-height:1.35; color:var(--muni-danger-fg); }
            .muni-errsum__list { margin:0; padding:0 0 0 18px; list-style:disc; }
            .muni-errsum__item { font-size:13px; line-height:1.5; color:var(--muni-text); }
            .muni-errsum__item + .muni-errsum__item { margin-top:4px; }
            /* Subrayado permanente: el enlace no puede distinguirse solo por el color. */
            .muni-errsum__link { color:var(--muni-text); text-decoration:underline; text-underline-offset:2px; border-radius:var(--muni-radius-sm); transition:color var(--muni-dur) var(--muni-ease); }
            .muni-errsum__link:hover { color:var(--muni-danger-fg); }
            .muni-errsum__link:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; }
            /* Área táctil de 24×24 px en el teléfono, que es donde este resumen más se usa. */
            .muni-errsum__link { display:inline-block; min-height:24px; padding:2px 0; }
        </style>
    @endonce
@endif
