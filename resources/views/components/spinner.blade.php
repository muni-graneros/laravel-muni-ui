@props([
    'size' => '20px',
])

{{-- EL DIBUJO de una carga en curso. Solo el dibujo.

     Es decorativo: `aria-hidden`, sin rol, sin texto. No cumple NADA por sí solo. Lo que
     cierra WCAG 2.2 AA 4.1.3 (Mensajes de estado) es el patrón de región, que vive en
     <x-muni::busy-region>: una región `role="status"` persistente, `aria-busy` sobre el
     contenedor que se reemplaza y el mensaje de RESULTADO pintado por el servidor al
     terminar. Un `<span role="status" wire:loading>` con esta ruedita adentro no se
     anuncia de forma fiable en NVDA, JAWS ni VoiceOver —una región viva que pasa de
     `display:none` a visible es el bug clásico— y queda de adorno dando falsa
     sensación de cumplimiento. Sin busy-region, spinner no es un componente de
     accesibilidad; es un icono.

     Dos decisiones que no son estéticas:

     1. Se dibuja con SVG EN LÍNEA y no con una máscara `data:` en CSS ni con una fuente
        de iconos: la CSP estricta de los sistemas bloquea `data:` en `mask-image` sin un
        solo error en consola. El trazo es `currentColor`, así que hereda el color del
        texto donde se coloque y no tiene color propio.
     2. La animación va ENVUELTA en `@media (prefers-reduced-motion: no-preference)`: se
        anima solo si la persona lo permite, en vez de animar y apagar después. Bajo
        movimiento reducido queda el arco quieto, que sigue leyéndose como «en curso»
        porque el TEXTO lo pone la región, no el dibujo. La duración es local
        (`--mspin-dur`) y no `--muni-dur`: ese token baja a 0 ms con movimiento reducido
        y a 160 ms no gira, parpadea.

     Uso suelto, dentro de un botón que dispara una acción:

       <x-muni::button wire:click="generar">
           <x-muni::spinner size="16px" wire:loading wire:target="generar" /> Generar padrón
       </x-muni::button>

     Ahí `wire:loading` alterna el `display` de este span, y como es decorativo da igual
     que nazca y muera con el estado. --}}

@php
    // Solo una longitud CSS: el valor va dentro de un `style` y cualquier otra cosa sería
    // inyección de CSS. Un tamaño inválido cae al de defecto en vez de imprimirse.
    $muniSpinnerSize = preg_match('/^\d+(\.\d+)?(px|em|rem|%)$/', (string) $size) ? $size : '20px';
@endphp

{{-- `aria-hidden` va por merge y no escrito a mano antes del derrame: si el consumidor
     lo pasa, saldría dos veces y en HTML gana el primero (DESIGN §8). Se saca de la
     bolsa para que sea siempre "true": decorativo por contrato, no por defecto. --}}
<span
    {{ $attributes->except('aria-hidden')->merge([
        'aria-hidden' => 'true',
        'class' => 'muni-spinner',
        'style' => "width:{$muniSpinnerSize};height:{$muniSpinnerSize};",
    ]) }}
>
    <svg viewBox="0 0 24 24" fill="none" focusable="false" width="100%" height="100%">
        <circle class="muni-spinner__track" cx="12" cy="12" r="10" stroke-width="3"/>
        <circle class="muni-spinner__arc" cx="12" cy="12" r="10" stroke-width="3" stroke-linecap="round" pathLength="100" stroke-dasharray="28 72"/>
    </svg>
</span>

@once
    <style>
        /* Viaja con el componente: dentro de un panel Filament solo se inyecta
           vendor/muni-ui/filament.css y una clase declarada únicamente en
           muni-ui.css se vería sin estilo, sin un solo error en consola. */
        .muni-spinner { --mspin-dur: 800ms; display:inline-block; flex:none; vertical-align:middle; color:inherit; line-height:0; }
        .muni-spinner svg { display:block; width:100%; height:100%; }
        .muni-spinner__track { stroke:currentColor; opacity:.25; }
        .muni-spinner__arc { stroke:currentColor; }
        @keyframes muni-spin { to { transform:rotate(360deg); } }
        /* Anima solo si se permite. Fuera de este bloque no hay ninguna `animation`. */
        @media (prefers-reduced-motion: no-preference) {
            .muni-spinner svg { animation:muni-spin var(--mspin-dur) linear infinite; }
        }
    </style>
@endonce
