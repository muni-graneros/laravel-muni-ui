@props([
    'items' => [],
    'placeholder' => 'Buscar o ir a…',
    'hotkey' => 'k',
])

@php
    // $items: array de ['label'=>, 'url'=>, 'group'=>?, 'hint'=>?]
    $itemsJson = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

{{-- Paleta de comandos (⌘K / Ctrl+K). Búsqueda difusa sobre `items`; Enter navega,
     ↑/↓ mueven, Escape cierra. --}}
<div
    x-data="{
        open:false, q:'', active:0,
        items: {{ $itemsJson }},
        get results(){
            const t=this.q.trim().toLowerCase();
            if(!t) return this.items;
            return this.items.filter(i => (i.label+' '+(i.group||'')).toLowerCase().includes(t));
        },
        show(){ this.open=true; this.q=''; this.active=0; this.$nextTick(()=>this.$refs.input && this.$refs.input.focus()); },
        move(d){ const n=this.results.length; if(!n) return; this.active=(this.active+d+n)%n; },
        go(){ const r=this.results[this.active]; if(r && r.url) window.location.href=r.url; }
    }"
    @keydown.window="if((($event.metaKey||$event.ctrlKey) && $event.key==='{{ $hotkey }}')){ $event.preventDefault(); show(); }"
    @keydown.escape.window="open=false"
    {{ $attributes }}
>
    @isset($trigger)<div @click="show()" style="display:inline-flex;">{{ $trigger }}</div>@endisset

    <template x-teleport="body">
        <div x-show="open" x-cloak style="position:fixed;inset:0;z-index:250;display:flex;align-items:flex-start;justify-content:center;padding:12vh 20px 20px;">
            <div x-show="open" x-transition:enter="muni-fade" x-transition:enter-start="muni-fade-0" x-transition:enter-end="muni-fade-1" x-transition:leave="muni-fade" x-transition:leave-start="muni-fade-1" x-transition:leave-end="muni-fade-0" @click="open=false" style="position:absolute;inset:0;background:rgba(10,14,20,.5);backdrop-filter:blur(3px);"></div>

            <div x-show="open" x-transition:enter="muni-pop" x-transition:enter-start="muni-pop-0" x-transition:enter-end="muni-pop-1" role="dialog" aria-modal="true"
                 style="position:relative;width:100%;max-width:560px;background:var(--muni-surface);border:1px solid var(--muni-border);border-radius:var(--muni-radius-lg);box-shadow:var(--muni-shadow-lg);overflow:hidden;font-family:var(--muni-font-sans);">
                <div style="display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--muni-border);">
                    <svg viewBox="0 0 20 20" fill="none" stroke="var(--muni-muted)" stroke-width="1.6" width="17" height="17"><circle cx="9" cy="9" r="6"/><path d="M18 18l-4.5-4.5" stroke-linecap="round"/></svg>
                    <input x-ref="input" x-model="q" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                           :placeholder="'{{ $placeholder }}'" autocomplete="off"
                           style="flex:1;border:none;outline:none;background:transparent;font-family:inherit;font-size:15px;color:var(--muni-text);">
                    <kbd style="font-family:var(--muni-font-mono);font-size:10.5px;padding:3px 6px;border:1px solid var(--muni-border);border-radius:5px;color:var(--muni-muted);">esc</kbd>
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
    </style>
@endonce
