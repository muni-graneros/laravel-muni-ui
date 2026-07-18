@props([
    'items' => [],
])

@php
    // $items: array de ['title'=>, 'time'=>?, 'description'=>?, 'tone'=>? (ok/warn/danger/info/accent)]
@endphp

<ol {{ $attributes->merge(['class' => 'muni-timeline']) }}>
    @foreach ($items as $item)
        @php
            $tone = $item['tone'] ?? 'accent';
            $color = [
                'accent' => 'var(--muni-accent)', 'ok' => 'var(--muni-ok-fg)', 'warn' => 'var(--muni-warn-fg)',
                'danger' => 'var(--muni-danger-fg)', 'info' => 'var(--muni-info-fg)', 'muted' => 'var(--muni-border-2)',
            ][$tone] ?? 'var(--muni-accent)';
        @endphp
        <li class="muni-tl__item">
            <span class="muni-tl__dot" style="--dot:{{ $color }};"></span>
            <div class="muni-tl__content">
                <div class="muni-tl__head">
                    <span class="muni-tl__title">{{ $item['title'] ?? '' }}</span>
                    @if (! empty($item['time']))<time class="muni-tl__time">{{ $item['time'] }}</time>@endif
                </div>
                @if (! empty($item['description']))<p class="muni-tl__desc">{{ $item['description'] }}</p>@endif
            </div>
        </li>
    @endforeach
</ol>

@once
    <style>
        .muni-timeline { list-style:none; margin:0; padding:0; font-family:var(--muni-font-sans); }
        .muni-tl__item { position:relative; display:flex; gap:14px; padding-bottom:18px; }
        .muni-tl__item:not(:last-child)::before { content:""; position:absolute; left:6px; top:16px; bottom:0; width:2px; background:var(--muni-border); }
        .muni-tl__dot { flex-shrink:0; width:14px; height:14px; margin-top:3px; border-radius:50%; background:var(--muni-surface); border:2px solid var(--dot); box-shadow:0 0 0 3px color-mix(in srgb,var(--dot) 15%,transparent),var(--muni-glow); z-index:1; }
        .muni-tl__content { min-width:0; padding-bottom:2px; }
        .muni-tl__head { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
        .muni-tl__title { font-size:13.5px; font-weight:600; color:var(--muni-text); }
        .muni-tl__time { font-family:var(--muni-font-mono); font-size:11px; color:var(--muni-hint); }
        .muni-tl__desc { margin:3px 0 0; font-size:12.5px; color:var(--muni-muted); line-height:1.5; }
    </style>
@endonce
