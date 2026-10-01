@props([
    'title', // título de la página (h1)
    'subtitle' => null, // texto bajo el título
    'eyebrow' => null, // rótulo chico sobre el título
])

{{-- CABECERA DE PÁGINA — el único `<h1>` de la pantalla, con la ruta encima y las acciones a la derecha.

     Slots: `migas` (la ruta, encima del título) y `actions` (los botones de la
     derecha). El de la ruta va ANTES del `<h1>` en el orden del DOM porque es el
     orden en que se lee la banda, y existe para que las nueve aplicaciones
     compongan «título + ruta» igual en vez de inventarla pantalla por pantalla;
     el componente de migas de ruta es lo que se mete adentro. Sin ese slot no se
     emite contenedor alguno: la cabecera queda byte a byte como estaba. --}}
<div {{ $attributes->merge(['style' => 'display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px;']) }}>
    <div style="min-width:0;">
        @isset($migas)<div class="muni-page-header__migas" style="margin-bottom:10px;min-width:0;">{{ $migas }}</div>@endisset
        @if ($eyebrow)
            <div style="font-family:var(--muni-font-mono);font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muni-accent);margin-bottom:6px;">{{ $eyebrow }}</div>
        @endif
        <h1 style="margin:0;font-family:var(--muni-font-sans);font-size:24px;font-weight:800;line-height:1.15;letter-spacing:-.02em;color:var(--muni-text);text-wrap:balance;">{{ $title }}</h1>
        @if ($subtitle)<p style="margin:6px 0 0;font-size:14px;color:var(--muni-muted);max-width:60ch;line-height:1.5;">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div style="display:flex;gap:10px;flex-shrink:0;">{{ $actions }}</div>@endisset
</div>
