@props([
    'current' => 1,
    'total' => 1,
    'url' => null,
    'info' => null,
])

@php
    // $url debe ser un closure/callable fn($pagina) => string, o null (solo muestra estado).
    $link = is_callable($url) ? $url : fn ($p) => '#';
    $current = (int) $current; $total = max((int) $total, 1);
    // Ventana de páginas: 1 … (actual-1, actual, actual+1) … total
    $pages = collect(range(1, $total))
        ->filter(fn ($p) => $p === 1 || $p === $total || abs($p - $current) <= 1)
        ->values();
@endphp

<nav aria-label="Paginación" {{ $attributes->merge(['style' => 'display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:16px;font-family:var(--muni-font-sans);font-size:13px;']) }}>
    <a href="{{ $current > 1 ? $link($current - 1) : '#' }}" @if($current <= 1) aria-disabled="true" @endif
       class="muni-page muni-page--nav" style="{{ $current <= 1 ? 'opacity:.4;pointer-events:none;' : '' }}">‹ Anterior</a>

    @php $prev = 0; @endphp
    @foreach ($pages as $p)
        @if ($p - $prev > 1)<span style="color:var(--muni-hint);padding:0 2px;">…</span>@endif
        @if ($p === $current)
            <span class="muni-page muni-page--current" aria-current="page">{{ $p }}</span>
        @else
            <a href="{{ $link($p) }}" class="muni-page">{{ $p }}</a>
        @endif
        @php $prev = $p; @endphp
    @endforeach

    <a href="{{ $current < $total ? $link($current + 1) : '#' }}" @if($current >= $total) aria-disabled="true" @endif
       class="muni-page muni-page--nav" style="{{ $current >= $total ? 'opacity:.4;pointer-events:none;' : '' }}">Siguiente ›</a>

    @if ($info)<span style="margin-left:auto;color:var(--muni-muted);font-size:12px;">{{ $info }}</span>@endif
</nav>

@once
    <style>
        .muni-page { display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 9px;
            border-radius:var(--muni-radius-sm);border:1px solid transparent;color:var(--muni-muted);text-decoration:none;
            font-variant-numeric:tabular-nums;transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-page:hover { background:var(--muni-surface-2);color:var(--muni-text); }
        .muni-page:focus-visible { outline:none;box-shadow:var(--muni-ring); }
        .muni-page--current { background:var(--muni-accent);color:var(--muni-on-accent);font-weight:600; }
        .muni-page--nav { color:var(--muni-text);font-weight:500; }
    </style>
@endonce
