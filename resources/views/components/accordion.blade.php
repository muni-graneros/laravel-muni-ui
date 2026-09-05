@props([
    'items' => [],
    'multiple' => false,
    'default' => null,
])

@php
    // $items: array de ['title'=>, 'content'=>] o usar el slot con <x-muni::accordion-item>.
@endphp

<div
    x-data="{ open: {{ $default !== null ? (int) $default : 'null' }}, multiple: {{ $multiple ? 'true' : 'false' }}, opened: [],
        toggle(i){ if(this.multiple){ this.opened = this.opened.includes(i) ? this.opened.filter(x=>x!==i) : [...this.opened, i]; } else { this.open = this.open===i ? null : i; } },
        isOpen(i){ return this.multiple ? this.opened.includes(i) : this.open===i; } }"
    {{ $attributes->merge(['class' => 'muni-acc']) }}
>
    @if (! empty($items))
        @foreach ($items as $i => $item)
            <div class="muni-acc__item">
                <button type="button" class="muni-acc__head" @click="toggle({{ $i }})" :aria-expanded="isOpen({{ $i }})">
                    <span>{{ $item['title'] ?? '' }}</span>
                    <span class="muni-acc__chevron" :class="isOpen({{ $i }}) && 'muni-acc__chevron--open'">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15"><path d="M4 6l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </button>
                <div class="muni-acc__panel" x-show="isOpen({{ $i }})" x-cloak
                     x-transition:enter="muni-acc-enter" x-transition:enter-start="muni-acc-0" x-transition:enter-end="muni-acc-1">
                    <div class="muni-acc__body">{{ $item['content'] ?? '' }}</div>
                </div>
            </div>
        @endforeach
    @else
        {{ $slot }}
    @endif
</div>

@once
    <style>
        .muni-acc { border:1px solid var(--muni-border); border-radius:var(--muni-radius); overflow:hidden; background:var(--muni-surface); }
        .muni-acc__item + .muni-acc__item { border-top:1px solid var(--muni-border); }
        .muni-acc__head { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; padding:15px 18px; background:transparent; border:none; cursor:pointer; font-family:var(--muni-font-sans); font-size:14px; font-weight:600; color:var(--muni-text); text-align:left; transition:background var(--muni-dur) var(--muni-ease); }
        .muni-acc__head:hover { background:var(--muni-surface-2); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-acc__head:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-3px; box-shadow:inset var(--muni-ring); }
        .muni-acc__chevron { color:var(--muni-muted); transition:transform var(--muni-dur) var(--muni-ease); display:inline-flex; }
        .muni-acc__chevron--open { transform:rotate(180deg); }
        .muni-acc__body { padding:0 18px 16px; font-family:var(--muni-font-sans); font-size:13.5px; color:var(--muni-muted); line-height:1.6; }
        .muni-acc-enter { transition:opacity var(--muni-dur) var(--muni-ease), transform var(--muni-dur) var(--muni-ease); }
        .muni-acc-0 { opacity:0; transform:translateY(-6px); }
        .muni-acc-1 { opacity:1; transform:translateY(0); }
    </style>
@endonce
