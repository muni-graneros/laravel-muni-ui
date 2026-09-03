@props([
    'title' => null,
    'side' => 'right',
    'width' => '400px',
])

@php
    $isRight = $side !== 'left';
    $tituloId = 'muni-drawer-title-'.uniqid();
@endphp

{{-- Panel lateral deslizante (Alpine 3 + plugin Focus, ya presente en el bundle de
     Livewire de los paneles). El slot `trigger` abre; Escape / click en el fondo cierran.
     `x-trap.inert.noscroll` atrapa el foco (Tab/Shift+Tab no se escapan), bloquea el
     scroll del body y devuelve el foco a quien lo abrió al cerrar. --}}
<div x-data="{ open:false }" @keydown.escape.window="open=false" {{ $attributes }}>
    @isset($trigger)<div @click="open=true" style="display:inline-flex;">{{ $trigger }}</div>@endisset

    <template x-teleport="body">
        <div x-show="open" x-cloak style="position:fixed;inset:0;z-index:210;">
            <div x-show="open" @click="open=false"
                 x-transition:enter="muni-fade" x-transition:enter-start="muni-fade-0" x-transition:enter-end="muni-fade-1"
                 x-transition:leave="muni-fade" x-transition:leave-start="muni-fade-1" x-transition:leave-end="muni-fade-0"
                 style="position:absolute;inset:0;background:rgba(10,14,20,.5);backdrop-filter:blur(2px);"></div>

            <div x-show="open" x-trap.inert.noscroll="open" role="dialog" aria-modal="true" aria-labelledby="{{ $tituloId }}"
                 x-transition:enter="muni-drawer" x-transition:enter-start="{{ $isRight ? 'muni-drawer-r0' : 'muni-drawer-l0' }}" x-transition:enter-end="muni-drawer-1"
                 x-transition:leave="muni-drawer" x-transition:leave-start="muni-drawer-1" x-transition:leave-end="{{ $isRight ? 'muni-drawer-r0' : 'muni-drawer-l0' }}"
                 style="position:absolute;top:0;bottom:0;{{ $isRight ? 'right:0;' : 'left:0;' }}width:{{ $width }};max-width:92vw;display:flex;flex-direction:column;background:var(--muni-surface);border-{{ $isRight ? 'left' : 'right' }}:1px solid var(--muni-border);box-shadow:var(--muni-shadow-lg);">
                <header style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid var(--muni-border);">
                    <h2 id="{{ $tituloId }}" style="margin:0;font-family:var(--muni-font-sans);font-size:15px;font-weight:700;color:var(--muni-text);">{{ $title }}</h2>
                    <button type="button" @click="open=false" aria-label="Cerrar" class="muni-drawer__x">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/></svg>
                    </button>
                </header>
                <div style="flex:1;overflow-y:auto;padding:20px;font-family:var(--muni-font-sans);font-size:14px;color:var(--muni-text);line-height:1.6;">{{ $slot }}</div>
                @isset($footer)<footer style="padding:14px 20px;border-top:1px solid var(--muni-border);background:var(--muni-surface-2);display:flex;justify-content:flex-end;gap:10px;">{{ $footer }}</footer>@endisset
            </div>
        </div>
    </template>
</div>

@once
    <style>
        .muni-drawer__x { display:inline-flex; padding:6px; border:none; background:transparent; color:var(--muni-muted); border-radius:var(--muni-radius-sm); cursor:pointer; transition:background var(--muni-dur) var(--muni-ease); }
        .muni-drawer__x:hover { background:var(--muni-surface-3); color:var(--muni-text); }
        .muni-fade { transition:opacity var(--muni-dur) var(--muni-ease); } .muni-fade-0 { opacity:0; } .muni-fade-1 { opacity:1; }
        .muni-drawer { transition:transform .28s var(--muni-ease); }
        .muni-drawer-r0 { transform:translateX(100%); } .muni-drawer-l0 { transform:translateX(-100%); } .muni-drawer-1 { transform:translateX(0); }
    </style>
@endonce
