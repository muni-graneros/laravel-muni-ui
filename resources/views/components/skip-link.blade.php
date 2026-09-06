@props([
    'destino' => '#muni-contenido',
])

{{-- ENLACE DE SALTO AL CONTENIDO (WCAG 2.2 nivel A, 2.4.1 «Evitar bloques»).

     CONTRATO CON EL ARMAZÓN. Este componente NO controla su destino, y sin
     destino no sirve. Quien lo use debe cumplir tres cosas:

       1. Emitirlo como PRIMER hijo del `<body>`, antes de la barra
          institucional, del menú lateral y de la cabecera. Si va después, el
          orden de tabulación lo deja detrás de lo que venía a saltarse.
       2. Darle al `<main>` (o al contenedor que reciba el salto) el mismo id
          del destino MÁS `tabindex="-1"`. Un ancla a un `<main>` sin
          `tabindex="-1"` mueve el scroll pero NO el punto de lectura en varios
          navegadores: el siguiente Tab vuelve al menú y el salto no sirvió.
       3. Reservarle `scroll-margin-top: var(--muni-topbar-h)` al destino, o con
          la cabecera fija el foco aterriza debajo de ella y no se ve.

     Los tres armazones del paquete (`app-shell`, `dashboard-shell`,
     `auth-shell`) ya lo traen cableado así.

     OCULTAMIENTO. Nunca con `display:none` ni `visibility:hidden`: eso lo saca
     del árbol de accesibilidad y deja de ser alcanzable con Tab, con lo que el
     enlace existe y no sirve para nada. Se recorta con `clip-path` y vuelve con
     `:focus`.

     El `<a>` va PLANO, sin `wire:navigate`: Livewire interceptaría el clic e
     intentaría navegar a la página en vez de mover el foco dentro de esta. --}}

<a
    href="{{ $destino }}"
    {{ $attributes->merge(['class' => 'muni-skip-link']) }}
>
    @if ($slot->isEmpty())
        Saltar al contenido principal
    @else
        {{ $slot }}
    @endif
</a>

@once
    <style>
        .muni-skip-link {
            position: fixed; top: 0; left: 0; z-index: 1000;
            width: 1px; height: 1px; overflow: hidden;
            clip-path: inset(50%); white-space: nowrap;
            /* Sin padding mientras está recortado: así la caja fija es de 1x1 y no
               queda un rectángulo invisible sobre la esquina de la cabecera. */
            margin: 0; padding: 0;
            background: var(--muni-surface); color: var(--muni-text);
            border: 1px solid var(--muni-border); border-radius: var(--muni-radius-sm);
            font-family: var(--muni-font-sans); font-size: 14px; font-weight: 600; line-height: 1.2;
            text-decoration: underline; text-underline-offset: 3px;
            transform: translateY(-8px);
            transition: transform var(--muni-dur) var(--muni-ease);
        }

        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-skip-link:focus,
        .muni-skip-link:focus-visible {
            top: 8px; left: 8px;
            width: auto; height: auto; overflow: visible;
            padding: 10px 16px;
            clip-path: none;
            transform: translateY(0);
            box-shadow: var(--muni-shadow-md);
            outline: 3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset: 2px;
        }
    </style>
@endonce
