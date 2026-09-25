@props([
    'items' => [], // lista de ['title' => , 'content' => ]
    'multiple' => false, // permite varios paneles abiertos a la vez
    'default' => null, // índice (o clave de items) abierto al cargar (null = todos cerrados)
])

@php
    // $items: array de ['title'=>, 'content'=>] (claves numéricas o de texto), o paneles
    // accordion-item en el slot. Los paneles se identifican por su posición (desde 0).
    // El panel abierto al cargar se pinta visible desde el servidor (sin x-cloak).
    $abierto = null;
    if ($default !== null) {
        $claves = array_keys($items);
        $abierto = is_int($default) || ctype_digit((string) $default)
            ? (int) $default
            : array_search($default, $claves, true);
        if ($abierto === false) { $abierto = null; }
    }
@endphp

<div
    x-data="{ open: {{ \Illuminate\Support\Js::from($multiple ? null : $abierto) }}, multiple: {{ $multiple ? 'true' : 'false' }},
        opened: {{ \Illuminate\Support\Js::from($multiple && $abierto !== null ? [$abierto] : []) }}, _n: 0,
        toggle(i){ if(this.multiple){ this.opened = this.opened.includes(i) ? this.opened.filter(x=>x!==i) : [...this.opened, i]; } else { this.open = this.open===i ? null : i; } },
        isOpen(i){ return this.multiple ? this.opened.includes(i) : this.open===i; } }"
    {{ $attributes->merge(['class' => 'muni-acc']) }}
>
    @if (! empty($items))
        @foreach ($items as $item)
            @php $i = $loop->index; $ini = $i === $abierto; @endphp
            <div class="muni-acc__item" x-id="['muni-acc']">
                <button type="button" class="muni-acc__head" @click="toggle({{ $i }})"
                        aria-expanded="{{ $ini ? 'true' : 'false' }}" :aria-expanded="isOpen({{ $i }}) ? 'true' : 'false'"
                        :id="$id('muni-acc') + '-h'" :aria-controls="$id('muni-acc')">
                    <span>{{ $item['title'] ?? '' }}</span>
                    <span class="muni-acc__chevron {{ $ini ? 'muni-acc__chevron--open' : '' }}" :class="{ 'muni-acc__chevron--open': isOpen({{ $i }}) }">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </button>
                <div class="muni-acc__panel" role="region" x-show="isOpen({{ $i }})" @unless ($ini) x-cloak @endunless
                     :id="$id('muni-acc')" :aria-labelledby="$id('muni-acc') + '-h'"
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
        .muni-acc__head:focus-visible { outline:none; box-shadow:inset var(--muni-ring); }
        .muni-acc__chevron { color:var(--muni-muted); transition:transform var(--muni-dur) var(--muni-ease); display:inline-flex; }
        .muni-acc__chevron--open { transform:rotate(180deg); }
        .muni-acc__body { padding:0 18px 16px; font-family:var(--muni-font-sans); font-size:13.5px; color:var(--muni-muted); line-height:1.6; }
        .muni-acc-enter { transition:opacity var(--muni-dur) var(--muni-ease), transform var(--muni-dur) var(--muni-ease); }
        .muni-acc-0 { opacity:0; transform:translateY(-6px); }
        .muni-acc-1 { opacity:1; transform:translateY(0); }
    </style>
@endonce
