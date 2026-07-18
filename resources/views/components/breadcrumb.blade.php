@props([
    'items' => [],
])

{{-- $items: array de ['label'=>, 'url'=>?]. El último es la página actual (sin url). --}}
<nav aria-label="Ruta" {{ $attributes->merge(['style' => 'display:flex;align-items:center;gap:7px;flex-wrap:wrap;font-family:var(--muni-font-sans);font-size:12.5px;']) }}>
    @foreach ($items as $i => $item)
        @if ($i > 0)
            <span aria-hidden="true" style="color:var(--muni-hint);">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" width="13" height="13"><path d="M6 4l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
        @endif
        @if (! empty($item['url']) && ! $loop->last)
            <a href="{{ $item['url'] }}" style="color:var(--muni-muted);text-decoration:none;">{{ $item['label'] }}</a>
        @else
            <span @if ($loop->last) aria-current="page" @endif style="color:{{ $loop->last ? 'var(--muni-text)' : 'var(--muni-muted)' }};font-weight:{{ $loop->last ? '600' : '400' }};">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
