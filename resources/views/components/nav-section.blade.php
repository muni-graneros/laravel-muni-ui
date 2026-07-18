@props([
    'title' => null,
])

<div style="margin:14px 0 4px;">
    @if ($title)
        <div style="padding:0 11px 6px;font-family:var(--muni-font-mono);font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muni-hint);">{{ $title }}</div>
    @endif
    <div style="display:flex;flex-direction:column;gap:2px;">{{ $slot }}</div>
</div>
