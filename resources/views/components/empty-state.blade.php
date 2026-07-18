@props([
    'title' => 'Sin resultados',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['style' => 'display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:10px;padding:48px 24px;']) }}>
    <div aria-hidden="true" style="display:grid;place-items:center;width:52px;height:52px;border-radius:14px;background:var(--muni-surface-2);border:1px solid var(--muni-border);color:var(--muni-muted);">
        {!! $icon ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="24" height="24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3" stroke-linecap="round"/></svg>' !!}
    </div>
    <div style="font-family:var(--muni-font-sans);font-size:15px;font-weight:700;color:var(--muni-text);">{{ $title }}</div>
    @if ($description)
        <p style="margin:0;max-width:44ch;font-size:13px;color:var(--muni-muted);line-height:1.5;">{{ $description }}</p>
    @endif
    @isset($actions)<div style="display:flex;gap:10px;margin-top:6px;">{{ $actions }}</div>@endisset
</div>
