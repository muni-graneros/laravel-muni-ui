@props([
    'system' => null,
    'home' => 'https://www.municipalidadgraneros.cl/',
    'sticky' => false,
])

{{-- Barra institucional de gobierno. Va ARRIBA de todo en cada subdominio del ecosistema
     para que la página se lea como parte de municipalidadgraneros.cl: escudo, nombre del
     municipio, el sistema actual y el enlace de vuelta al sitio madre. --}}
<div {{ $attributes->merge(['class' => 'muni-gob-bar'.($sticky ? ' muni-gob-bar--sticky' : '')]) }}>
    <div class="muni-gob-bar__in">
        <a href="{{ $home }}" class="muni-gob-bar__brand">
            <x-muni::gob-escudo size="26" />
            <span class="muni-gob-bar__name">Municipalidad de Graneros</span>
        </a>
        @if ($system)
            <span class="muni-gob-bar__sep" aria-hidden="true">/</span>
            <span class="muni-gob-bar__sys">{{ $system }}</span>
        @endif
        <span class="muni-gob-bar__spacer"></span>
        {{ $slot }}
        <a href="{{ $home }}" class="muni-gob-bar__back">Ir al sitio municipal ↗</a>
    </div>
</div>
<x-muni::gob-stripe />

@once
    <style>
        .muni-gob-bar { width:100%; background:var(--muni-gob-petroleo-dark); color:#e8f1f2; font-family:var(--muni-font-sans); }
        .muni-gob-bar--sticky { position:sticky; top:0; z-index:70; }
        .muni-gob-bar__in { display:flex; align-items:center; gap:10px; max-width:1180px; margin:0 auto; padding:7px clamp(16px,3vw,26px); flex-wrap:wrap; }
        .muni-gob-bar__brand { display:inline-flex; align-items:center; gap:9px; text-decoration:none; color:inherit; }
        .muni-gob-bar__name { font-size:12.5px; font-weight:700; letter-spacing:-.01em; }
        .muni-gob-bar__sep { opacity:.4; font-size:12.5px; }
        .muni-gob-bar__sys { font-size:12.5px; font-weight:500; opacity:.85; }
        .muni-gob-bar__spacer { flex:1; }
        .muni-gob-bar__back { font-size:11.5px; font-weight:600; color:#e8f1f2; text-decoration:none; opacity:.8; transition:opacity var(--muni-dur) var(--muni-ease); white-space:nowrap; }
        .muni-gob-bar__back:hover { opacity:1; text-decoration:underline; }
        @media (max-width:560px) { .muni-gob-bar__back { display:none; } }
    </style>
@endonce
