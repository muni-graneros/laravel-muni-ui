@props([
    'title' => null,
    'maxWidth' => '480px',
])

{{-- Modal accesible (Alpine 3). El slot `trigger` abre; Escape / click en el fondo /
     botón × cierran. Bloquea el scroll del body mientras está abierto y devuelve el foco
     al panel al abrir. El slot `footer` es opcional (acciones). --}}
<div
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    x-effect="document.body.style.overflow = open ? 'hidden' : ''"
>
    @isset($trigger)
        <div @click="open = true" style="display:inline-flex;">{{ $trigger }}</div>
    @endisset

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            role="dialog"
            aria-modal="true"
            style="position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:20px;"
        >
            {{-- Fondo --}}
            <div
                x-show="open"
                x-transition:enter="muni-fade" x-transition:enter-start="muni-fade-0" x-transition:enter-end="muni-fade-1"
                x-transition:leave="muni-fade" x-transition:leave-start="muni-fade-1" x-transition:leave-end="muni-fade-0"
                @click="open = false"
                style="position:absolute;inset:0;background:rgba(10,14,20,.55);backdrop-filter:blur(2px);"
            ></div>

            {{-- Panel --}}
            <div
                x-show="open"
                x-transition:enter="muni-pop" x-transition:enter-start="muni-pop-0" x-transition:enter-end="muni-pop-1"
                x-transition:leave="muni-pop" x-transition:leave-start="muni-pop-1" x-transition:leave-end="muni-pop-0"
                {{ $attributes->merge([
                    'style' => "position:relative;width:100%;max-width:{$maxWidth};max-height:calc(100vh - 40px);"
                        ."display:flex;flex-direction:column;background:var(--muni-surface);color:var(--muni-text);"
                        ."border:1px solid var(--muni-border);border-radius:var(--muni-radius-lg);"
                        ."box-shadow:var(--muni-shadow-lg);overflow:hidden;font-family:var(--muni-font-sans);",
                ]) }}
            >
                <header style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid var(--muni-border);">
                    <h2 style="margin:0;font-size:15px;font-weight:700;color:var(--muni-text);">{{ $title }}</h2>
                    <button type="button" @click="open = false" aria-label="Cerrar" class="muni-modal-x">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/></svg>
                    </button>
                </header>

                <div style="padding:18px;overflow-y:auto;font-size:14px;line-height:1.55;color:var(--muni-text);">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <footer style="display:flex;justify-content:flex-end;gap:10px;padding:14px 18px;border-top:1px solid var(--muni-border);background:var(--muni-surface-2);">
                        {{ $footer }}
                    </footer>
                @endisset
            </div>
        </div>
    </template>
</div>

@once
    <style>
        .muni-modal-x { display:inline-flex;padding:6px;border:none;background:transparent;color:var(--muni-muted);border-radius:var(--muni-radius-sm);cursor:pointer;transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-modal-x:hover { background:var(--muni-surface-3);color:var(--muni-text); }
        .muni-modal-x:focus-visible { outline:none;box-shadow:var(--muni-ring); }
        .muni-fade { transition:opacity var(--muni-dur) var(--muni-ease); }
        .muni-fade-0 { opacity:0; } .muni-fade-1 { opacity:1; }
        .muni-pop { transition:opacity var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease); }
        .muni-pop-0 { opacity:0;transform:scale(.96) translateY(8px); }
        .muni-pop-1 { opacity:1;transform:scale(1) translateY(0); }
    </style>
@endonce
