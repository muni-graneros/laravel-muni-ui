@props([
    'items' => [],
    'placeholder' => 'Buscar o ir a…',
    'hotkey' => 'k',
])

@php
    // $items: array de ['label'=>, 'url'=>, 'group'=>?, 'hint'=>?]
    $itemsJson = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);

    /*
     * El rótulo del atajo y el manejador salen de la MISMA prop. Un «Ctrl K» escrito a
     * mano en la vista se desincroniza el día que alguien pasa hotkey="p", y el
     * funcionario del mesón teclea un atajo que no existe. Una tecla de una letra se
     * rotula en mayúscula, como está en el teclado; las de nombre («F1», «/») tal cual.
     * El manejador compara en minúscula: con Bloq Mayús puesto `$event.key` llega «K»
     * y el atajo no debe apagarse. `aria-keyshortcuts` (ARIA 1.2) declara las dos
     * combinaciones que el manejador acepta, con los nombres de modificador de UI Events.
     */
    $hotkeyKey = mb_strtolower((string) $hotkey);
    $hotkeyLabel = mb_strlen($hotkeyKey) === 1 ? mb_strtoupper($hotkeyKey) : (string) $hotkey;
    $hotkeyAria = 'Control+'.$hotkeyLabel.' Meta+'.$hotkeyLabel;
@endphp

{{-- Paleta de comandos (⌘K / Ctrl+K). Búsqueda difusa sobre `items`; Enter navega,
     ↑/↓ mueven, Escape cierra. --}}
<div
    x-data="{
        open:false, trap:false, q:'', active:0,
        items: {{ $itemsJson }},
        init(){ this.$watch('open', v => { if (v) { this.$nextTick(() => { this.trap = this.open; }); } else { this.trap = false; } }); },
        get results(){
            const t=this.q.trim().toLowerCase();
            if(!t) return this.items;
            return this.items.filter(i => (i.label+' '+(i.group||'')).toLowerCase().includes(t));
        },
        show(){ this.open=true; this.q=''; this.active=0; },
        move(d){ const n=this.results.length; if(!n) return; this.active=(this.active+d+n)%n; },
        go(){ const r=this.results[this.active]; if(r && r.url) window.location.href=r.url; },
        mac: /mac|iphone|ipad|ipod/i.test(navigator.userAgentData?.platform ?? navigator.platform ?? '')
    }"
    @keydown.window="if((($event.metaKey||$event.ctrlKey) && $event.key.toLowerCase()===@js($hotkeyKey))){ $event.preventDefault(); show(); }"
    @keydown.escape.window="open=false"
    {{ $attributes }}
>
    @isset($trigger)
        <div @click="show()" style="display:inline-flex;">{{ $trigger }}</div>
    @else
        {{-- Disparador por defecto: un <button> de verdad, como en dropdown y popover, con el
             atajo rotulado desde `hotkey`. Las DOS formas (Ctrl y ⌘) vienen del servidor y
             Alpine solo elige cuál se ve según la plataforma: ningún texto entra en una
             expresión de Alpine (lección de file-dropzone). La forma ⌘ nace oculta, así que
             sin JS —o antes de hidratar— se ve Ctrl, que el manejador acepta también en Mac.
             <kbd> no tiene rol ARIA y no admite aria-label: el grupo visible va aria-hidden y
             el lector lee «Control K» / «Comando K» desde el texto sr-only. --}}
        <button type="button" class="muni-cmdk__trigger" @click="show()" aria-haspopup="dialog" aria-keyshortcuts="{{ $hotkeyAria }}">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="15" height="15" aria-hidden="true"><circle cx="9" cy="9" r="6"/><path d="M18 18l-4.5-4.5" stroke-linecap="round"/></svg>
            <span>{{ $placeholder }}</span>
            <span class="muni-cmdk__hotkey" aria-hidden="true">
                <span class="muni-cmdk__combo" x-show="!mac"><kbd class="muni-kbd">Ctrl</kbd><kbd class="muni-kbd">{{ $hotkeyLabel }}</kbd></span>
                <span class="muni-cmdk__combo" x-show="mac" style="display:none;"><kbd class="muni-kbd">⌘</kbd><kbd class="muni-kbd">{{ $hotkeyLabel }}</kbd></span>
            </span>
            <span class="muni-cmdk__sr" x-show="!mac">Control {{ $hotkeyLabel }}</span>
            <span class="muni-cmdk__sr" x-show="mac" style="display:none;">Comando {{ $hotkeyLabel }}</span>
        </button>
    @endisset

    <template x-teleport="body">
        <div x-show="open" x-cloak style="position:fixed;inset:0;z-index:250;display:flex;align-items:flex-start;justify-content:center;padding:12vh 20px 20px;">
            <div x-show="open" x-transition:enter="muni-fade" x-transition:enter-start="muni-fade-0" x-transition:enter-end="muni-fade-1" x-transition:leave="muni-fade" x-transition:leave-start="muni-fade-1" x-transition:leave-end="muni-fade-0" @click="open=false" style="position:absolute;inset:0;background:rgba(10,14,20,.5);backdrop-filter:blur(3px);"></div>

            {{-- La trampa se arma con `trap`, no con `open` a secas, y no es manía: x-show
                 pinta el diálogo en el SIGUIENTE requestAnimationFrame, y x-trap activa la
                 trampa con un setTimeout de 15 ms desde que su expresión pasa a true. Si el
                 timer gana al frame —en headless una de cada tres veces; en un navegador a
                 60 Hz cada vez que el próximo frame queda a más de 15 ms—, focus-trap intenta
                 enfocar una caja todavía en display:none, falla en silencio y no reintenta:
                 el diálogo se ve abierto con el foco afuera. `trap` sube en un $nextTick, que
                 Alpine retiene hasta el frame final de la transición (el diálogo ya pintado),
                 y baja al cerrar. Medido en tests/navegador/rotulo-del-atajo.py. --}}
            <div x-show="open" x-trap.inert.noscroll="open && trap" x-transition:enter="muni-pop" x-transition:enter-start="muni-pop-0" x-transition:enter-end="muni-pop-1" role="dialog" aria-modal="true" aria-label="Paleta de comandos"
                 style="position:relative;width:100%;max-width:560px;background:var(--muni-surface);border:1px solid var(--muni-overlay-border, var(--muni-border));border-radius:var(--muni-radius-lg);box-shadow:var(--muni-shadow-lg);overflow:hidden;font-family:var(--muni-font-sans);">
                <div style="display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--muni-border);">
                    <svg viewBox="0 0 20 20" fill="none" stroke="var(--muni-muted)" stroke-width="1.6" width="17" height="17"><circle cx="9" cy="9" r="6"/><path d="M18 18l-4.5-4.5" stroke-linecap="round"/></svg>
                    {{-- `autofocus` es el contrato de x-trap para el foco inicial (como en modal), no
                         el del navegador: la caja nace oculta y dentro de un <template>. Antes
                         `show()` la enfocaba a mano en un $nextTick, y eso rompía la DEVOLUCIÓN del
                         foco: x-trap se activa 15 ms después de abrir y guarda como «dónde volver» lo
                         que esté enfocado en ese instante; si la caja ya tenía el foco, al cerrar
                         con Escape lo devolvía a una caja con display:none y el foco caía al <body>.
                         Medido en Firefox siempre y en Chromium con movimiento reducido. --}}
                    <input x-ref="input" x-model="q" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                           placeholder="{{ $placeholder }}" autocomplete="off" autofocus
                           class="muni-cmdk__input"
                           style="flex:1;border:none;background:transparent;font-family:inherit;font-size:15px;color:var(--muni-text);">
                    {{-- La tecla dibujada va aria-hidden como el ⌘ del disparador: «esc» suelto se lee
                         «e-s-c». El lector recibe la frase entera desde el texto sr-only. --}}
                    <kbd class="muni-kbd" aria-hidden="true">esc</kbd>
                    <span class="muni-cmdk__sr">Escape cierra la paleta</span>
                </div>
                <div style="max-height:52vh;overflow-y:auto;padding:6px;">
                    <template x-for="(item, i) in results" :key="item.url || item.label">
                        <a :href="item.url || '#'" @mouseenter="active=i" @click="open=false"
                           :style="`display:flex;align-items:center;gap:11px;padding:10px 11px;border-radius:var(--muni-radius-sm);text-decoration:none;color:var(--muni-text);${active===i?'background:var(--muni-surface-2);':''}`">
                            <span style="width:6px;height:6px;border-radius:50%;background:var(--muni-accent);flex-shrink:0;box-shadow:var(--muni-glow);"></span>
                            <span style="flex:1;min-width:0;font-size:13.5px;font-weight:500;" x-text="item.label"></span>
                            <template x-if="item.group"><span style="font-size:11px;color:var(--muni-muted);font-family:var(--muni-font-mono);" x-text="item.group"></span></template>
                        </a>
                    </template>
                    <template x-if="results.length===0">
                        <div style="padding:28px 12px;text-align:center;color:var(--muni-muted);font-size:13px;">Sin coincidencias para «<span x-text="q"></span>».</div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    <style>
        .muni-fade{transition:opacity var(--muni-dur) var(--muni-ease);}.muni-fade-0{opacity:0}.muni-fade-1{opacity:1}
        .muni-pop{transition:opacity var(--muni-dur) var(--muni-ease),transform var(--muni-dur) var(--muni-ease);}
        .muni-pop-0{opacity:0;transform:scale(.97) translateY(-8px)}.muni-pop-1{opacity:1;transform:scale(1) translateY(0)}
        /* La caja de búsqueda venía con `outline:none` en el atributo style y sin
           nada que lo reemplazara: no había NINGÚN indicador de foco. El offset es
           negativo porque el diálogo lleva `overflow:hidden` y recortaría el anillo. */
        .muni-cmdk__input:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; }
        /* La tecla de un atajo, PLANA: borde de 1px y sombra cero. Nada del relieve de
           daisyUI, que en el tema del panel sale como una sombra pegada y al imprimir
           como un recuadro gris sucio. Mono tabular como los RUT y las cifras (DESIGN §9).
           Vive acá y no en la hoja porque dentro del panel solo se carga la hoja de
           Filament (DESIGN §7); `command-palette` es su único consumidor. */
        .muni-kbd { display:inline-flex; align-items:center; justify-content:center; box-sizing:border-box; min-width:1.9em; padding:3px 6px;
            font-family:var(--muni-font-mono); font-size:10.5px; font-weight:500; line-height:1.2; font-variant-numeric:tabular-nums;
            color:var(--muni-muted); background:transparent; border:1px solid var(--muni-border); border-radius:5px; box-shadow:none; }
        .muni-cmdk__hotkey, .muni-cmdk__combo { display:inline-flex; align-items:center; gap:3px; }
        /* El disparador por defecto: un botón «fantasma» que se lee como un campo de
           búsqueda. 36px de alto (≥24px, WCAG 2.2 AA 2.5.8). El outline es el indicador
           REAL de foco: el anillo de sombra se pierde dentro de Filament (DESIGN §5). */
        .muni-cmdk__trigger { position:relative; display:inline-flex; align-items:center; gap:8px; min-height:36px; padding:6px 8px 6px 11px; cursor:pointer;
            font-family:var(--muni-font-sans); font-size:13px; font-weight:500; line-height:1.2; text-align:start;
            color:var(--muni-muted); background:transparent; border:1px solid var(--muni-border); border-radius:var(--muni-radius-sm);
            transition:background var(--muni-dur) var(--muni-ease),border-color var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-cmdk__trigger:hover { color:var(--muni-text); background:var(--muni-surface-2); border-color:var(--muni-border-2); }
        .muni-cmdk__trigger:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-cmdk__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        /* Guardia propia de movimiento reducido, y NO es redundante con DESIGN §6:
           dentro del panel solo se carga la hoja de Filament, que no baja --muni-dur
           a 0 ms (DESIGN §7). Medido en tests/navegador/rotulo-del-atajo.py: sin esta
           regla, en panel-* la transición seguía en 160 ms con la preferencia activa.
           Misma guardia que llevan stat y file-dropzone. */
        @media (prefers-reduced-motion:reduce) { .muni-cmdk__trigger { transition:none; } }
    </style>
@endonce
