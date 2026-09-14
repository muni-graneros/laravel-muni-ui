@props([
    'target' => null,
    'loading' => 'Cargando…',
    'status' => null,
    'delay' => 'long',
    'busy' => false,
])

{{-- LA REGIÓN QUE DICE QUE ALGO ESTÁ EN CURSO, EN TEXTO, Y DICE CUÁNDO TERMINÓ.

     Es el patrón, no un dibujo (WCAG 2.2 AA 4.1.3, Mensajes de estado). Tres piezas, y
     las tres son requisito:

     1. Una región `role="status"` PERSISTENTE y vacía que existe en el HTML inicial. Es
        el único nodo que un lector de pantalla anuncia de forma fiable: tiene que estar
        observándolo ANTES de que llegue el texto. Una región que nace junto con su
        mensaje —o que aparece con `wire:loading`, que alterna `display:none`— no se
        anuncia en NVDA, JAWS ni VoiceOver. Por eso este nodo se pinta siempre, aunque
        no haya nada que decir, y NUNCA lleva `wire:ignore`: el morph es justamente lo
        que le trae el mensaje.
     2. `wire:loading.attr="aria-busy"` sobre el contenedor que Livewire reemplaza (el
        slot). Mientras la petición está en vuelo el contenedor queda `aria-busy="true"`.
     3. El mensaje de RESULTADO, que el servidor pinta al terminar («14 personas
        encontradas», «Sin coincidencias para el RUT ingresado»): prop `status` o ranura
        `status`, como string YA REDACTADO por el anfitrión. Nunca un registro ni HTML
        (minimización, Ley 21.719): la prop se escapa; la ranura es Blade del anfitrión,
        como toda ranura, y lo que ponga adentro lo escapa él con `{{ }}`.

     Lo que se anuncia es el RESULTADO, nunca el proceso. Anunciar «cargando» en cada
     tecla de un buscador en vivo satura al lector; el indicador visible de abajo es
     texto para quien mira la pantalla, y a propósito no es región viva.

     Por qué la región vive FUERA del contenedor ocupado, y no adentro: `aria-busy="true"`
     en un ancestro es exactamente la señal que autoriza al lector a ignorar los cambios
     de una región viva hasta que termine. Que el resultado llegue antes o después de que
     Livewire quite el atributo es un detalle interno que no está garantizado: en 3.8.7
     `respond()` apaga la carga y recién después corre el morph; en 4.4.1 el morph vive
     en `onMorph`, que corre después de `onEffect` (donde se apaga). Hoy sale bien en
     las dos; afuera, no depende de eso ni de la versión. Y afuera del slot que se
     reemplaza, el nodo de la región es siempre EL MISMO tras cada morph, que es lo que
     un lector necesita para seguir observándolo. Todo esto se mide con Livewire real en
     `tests/navegador/carga-anunciada-livewire.py` (Chromium y Firefox), que levanta
     `tests/navegador/carga-anunciada-livewire.php` con `php -S`.

     El retardo antiparpadeo es NATIVO de Livewire, no de Alpine: `.delay.long` son 300 ms
     en la tabla `delayModifiers` del propio Livewire (shortest 50, shorter 100, short 150,
     default 200, long 300, longer 500, longest 1000; igual en 3.8.7 y en 4.4.1). Se
     aplica solo al indicador visible; el `aria-busy` se marca en cuanto sale la petición.
     El indicador se muestra por CLASE (`muni-busy--loading`), no por el `display` que
     Livewire pondría en línea: así nace oculto por CSS, no puede quedar una ruedita
     eterna en Blade puro ni antes de hidratar, y la vitrina lo mide con `:busy="true"`.

     Uso, sobre la búsqueda por RUT contra el maestro de personas:

       <x-muni::rut-input name="rut" label="RUT" wire:model.live.debounce.300ms="rut" />

       <x-muni::busy-region target="rut" loading="Buscando en el maestro…" :status="$resumen">
           @forelse ($personas as $persona)
               …
           @empty
               <x-muni::empty-state mode="no-matches" :filter="$rut" />
           @endforelse
       </x-muni::busy-region>

     donde `$resumen` es «1 persona encontrada» o «Sin coincidencias» y lo arma el
     componente Livewire después de buscar. `target` es azúcar sobre `wire:target`: si el
     consumidor escribe `wire:target` en la etiqueta, gana el suyo y llega a los dos nodos.

     `busy` es para una región cuyo PRIMER contenido llega por petición (`wire:init`): la
     hace nacer ocupada, con el indicador visible y `aria-busy` puesto desde el servidor.
     La prop la APAGA EL ANFITRIÓN cuando llega el dato, atada al dato y nunca fija:

       <div wire:init="generar">
           <x-muni::busy-region target="generar" loading="Generando el padrón…"
               :busy="$total === null" :status="$total === null ? '' : $resumen">
               @if ($total !== null) … @endif
           </x-muni::busy-region>
       </div>

     Con `:busy="true"` FIJO la región queda ocupada para siempre, en silencio: al
     terminar la petición Livewire apaga el directive (en 4 quita la clase y el atributo;
     en 3 restaura el estado previo, que era «ocupada») y acto seguido el morph pinta lo
     que diga el HTML del servidor, que sigue diciendo «ocupada». Es exactamente el
     defecto que este componente existe para evitar, y el banco con Livewire real lo mide
     en las dos formas: atada al dato se apaga; fija, queda pegada. La única excepción es
     el `placeholder()` de un componente perezoso (`#[Lazy]`): ahí `:busy="true"` fijo
     está bien, porque el render real reemplaza el placeholder entero y no lleva la prop.

     Cuatro límites, documentados porque son los que muerden:

     · El control que dispara la carga va FUERA de esta región: el slot se reemplaza, y
       el foco no puede desaparecer mientras el funcionario lo tiene. Tampoco se
       deshabilita el disparador con `wire:loading.attr="disabled"`: un botón enfocado
       que pasa a `disabled` pierde el foco en Chromium.
     · No anidarla dentro de un `<template x-for>` en Livewire 3: un `wire:loading`
       ahí adentro queda huérfano, sin componente al que escuchar.
     · No usarla dentro de un panel Filament: `<x-filament::loading-section>` ya trae
       `role="status"`, `aria-busy` y el texto oculto, y `<x-filament::loading-indicator>`
       el dibujo con `motion-safe:animate-spin`. Esta región es para las vistas Blade
       fuera del panel: el mesón, el portal del vecino, el tablero del CAD.
     · Un mensaje IDÉNTICO al anterior no se reanuncia («Sin coincidencias» dos veces
       seguidas): el nodo no cambia. Sin Alpine no hay vaciar → tick → escribir; si el
       caso lo exige, además se despacha `muni-announce` a <x-muni::announcer>. --}}

@php
    // Solo modificadores que Livewire conoce (la tabla delayModifiers entera): uno
    // inventado se imprimiría igual y el directive quedaría SIN retardo, sin que nadie
    // avise. Un valor desconocido cae a `long`; `none` quita el retardo.
    $muniBusyDelays = ['shortest', 'shorter', 'short', 'default', 'long', 'longer', 'longest'];
    $muniBusyDelay = in_array($delay, $muniBusyDelays, true) ? '.delay.'.$delay : ($delay === 'none' ? '' : '.delay.long');

    // El `wire:target` escrito en la etiqueta gana sobre `target`, y se saca de la bolsa
    // antes del derrame: si quedara, la raíz lo imprimiría dos veces y en HTML gana el
    // primero (DESIGN §8). Los dos nodos tienen que escuchar el MISMO target.
    $muniBusyTarget = trim((string) ($attributes->get('wire:target') ?? $target));
    $muniBusyBusy = filter_var($busy, FILTER_VALIDATE_BOOL);

    $muniBusyRaiz = array_filter([
        'class' => 'muni-busy'.($muniBusyBusy ? ' muni-busy--loading' : ''),
        'wire:loading'.$muniBusyDelay.'.class' => 'muni-busy--loading',
        'wire:target' => $muniBusyTarget,
    ], fn (string $valor) => $valor !== '');
@endphp

<div {{ $attributes->except('wire:target')->merge($muniBusyRaiz) }}>
    <div
        class="muni-busy__content"
        wire:loading.attr="aria-busy"
        @if ($muniBusyTarget !== '') wire:target="{{ $muniBusyTarget }}" @endif
        @if ($muniBusyBusy) aria-busy="true" @endif
    >
        {{ $slot }}
    </div>

    {{-- El proceso, en texto visible. Sin rol ni aria-live a propósito. --}}
    <div class="muni-busy__loading">
        <x-muni::spinner size="16px" />
        <span>{{ $loading }}</span>
    </div>

    {{-- El resultado. Siempre presente, vacío hasta que el servidor lo pinta. --}}
    <div class="muni-busy__status" role="status">{{ $status }}</div>
</div>

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css y una clase declarada únicamente en
           muni-ui.css se vería sin estilo, sin un solo error en consola. */
        .muni-busy { position:relative; }
        /* Con el contenido vacío (primera carga perezosa) la región tendría 0 px de alto
           y el indicador, que va superpuesto, no tendría dónde caer. */
        .muni-busy--loading { min-height:40px; }
        /* Superpuesto y no en flujo: si entrara en el flujo empujaría el contenido al
           aparecer y al irse —dos saltos de diseño por búsqueda—, y el segundo,
           lejos de la interacción, cuenta para CLS. Con el texto principal y el
           borde de campo, que es el único borde del paquete que pasa 3:1. Y sin
           puntero: cae sobre la esquina del contenido que se reemplaza, y durante la
           búsqueda por RUT (≈1 s) el clic o el tap que va a esa fila tiene que llegar. */
        .muni-busy__loading { display:none; position:absolute; top:0; left:0; z-index:1; pointer-events:none; align-items:center; gap:8px; margin:0; padding:6px 10px; font-family:var(--muni-font-sans); font-size:13px; line-height:1.2; color:var(--muni-text); background:var(--muni-surface); border:1px solid var(--muni-field-border); border-radius:var(--muni-radius-sm); box-shadow:var(--muni-shadow-md); }
        .muni-busy--loading > .muni-busy__loading { display:flex; }
        /* La región: texto visible cuando hay mensaje, y sin sacarla nunca del árbol de
           accesibilidad (ni display:none ni visibility:hidden). Vacía no ocupa alto. */
        .muni-busy__status { margin:8px 0 0; font-family:var(--muni-font-sans); font-size:13px; line-height:1.5; color:var(--muni-muted); }
        .muni-busy__status:empty { margin:0; }
    </style>
@endonce
