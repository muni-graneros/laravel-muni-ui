@props([
    'width' => '100%',
    'height' => '14px',
    'rounded' => 'var(--muni-radius-sm)',
])

<span
    aria-hidden="true"
    {{ $attributes->merge([
        'class' => 'muni-skel',
        'style' => "display:block;width:{$width};height:{$height};border-radius:{$rounded};",
    ]) }}
></span>

@once
    <style>
        .muni-skel { position:relative; overflow:hidden; background:var(--muni-surface-3); }
        .muni-skel::after { content:""; position:absolute; inset:0;
            background:linear-gradient(90deg,transparent,color-mix(in srgb,var(--muni-text) 6%,transparent),transparent);
            transform:translateX(-100%); animation:muni-shimmer 1.4s infinite; }
        @keyframes muni-shimmer { 100% { transform:translateX(100%); } }
        @media (prefers-reduced-motion:reduce) { .muni-skel::after { animation:none; } }
    </style>
@endonce
