@props([
    'system' => null,
    'home' => 'https://www.municipalidadgraneros.cl/',
    'address' => 'Av. Bernardo O\'Higgins 630, Graneros, Región de O\'Higgins',
    'phone' => '+56 72 249 1000',
    'email' => 'contacto@municipalidadgraneros.cl',
])

{{-- Footer institucional compartido por los subdominios del ecosistema. Cierra la página
     con la misma identidad del sitio madre: franja de 7 colores, escudo, datos de contacto
     y el aviso de protección de datos. --}}
<footer {{ $attributes->merge(['class' => 'muni-gob-footer']) }}>
    <x-muni::gob-stripe />
    <div class="muni-gob-footer__in">
        <div class="muni-gob-footer__brand">
            <x-muni::gob-escudo size="52" />
            <div>
                <div class="muni-gob-footer__name">Municipalidad de Graneros</div>
                @if ($system)<div class="muni-gob-footer__sys">{{ $system }}</div>@endif
            </div>
        </div>

        <div class="muni-gob-footer__cols">
            <div class="muni-gob-footer__col">
                <h4>Contacto</h4>
                <p>{{ $address }}</p>
                <p><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></p>
                <p><a href="mailto:{{ $email }}">{{ $email }}</a></p>
            </div>
            <div class="muni-gob-footer__col">
                <h4>Ecosistema</h4>
                {{ $links ?? '' }}
                @unless (isset($links))
                    <p><a href="{{ $home }}">Sitio municipal</a></p>
                    <p><a href="#">Trámites en línea</a></p>
                    <p><a href="#">Transparencia</a></p>
                @endunless
            </div>
            <div class="muni-gob-footer__col">
                <h4>Tus datos</h4>
                <p>Tratamos tus datos personales conforme a la <b>Ley 21.719</b>. La información no sale de los sistemas del municipio.</p>
            </div>
        </div>
    </div>
    <div class="muni-gob-footer__legal">
        <span>© {{ date('Y') }} Ilustre Municipalidad de Graneros</span>
        <span>{{ $system ? $system.' · ' : '' }}municipalidadgraneros.cl</span>
    </div>
</footer>

@once
    <style>
        .muni-gob-footer { background:var(--muni-gob-petroleo-dark); color:#d9e6e8; font-family:var(--muni-font-sans); }
        .muni-gob-footer__in { display:flex; gap:clamp(24px,5vw,60px); max-width:1180px; margin:0 auto; padding:36px clamp(16px,3vw,26px) 28px; flex-wrap:wrap; }
        .muni-gob-footer__brand { display:flex; align-items:center; gap:14px; }
        .muni-gob-footer__name { font-size:15px; font-weight:800; color:#fff; letter-spacing:-.01em; }
        .muni-gob-footer__sys { font-size:12.5px; opacity:.75; margin-top:2px; }
        .muni-gob-footer__cols { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:26px; flex:1; min-width:260px; }
        .muni-gob-footer__col h4 { font-family:var(--muni-font-mono); font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muni-gob-lima); margin:0 0 10px; }
        .muni-gob-footer__col p { font-size:12.5px; line-height:1.55; margin:0 0 6px; opacity:.85; }
        .muni-gob-footer__col a { color:#d9e6e8; text-decoration:none; }
        .muni-gob-footer__col a:hover { color:#fff; text-decoration:underline; }
        .muni-gob-footer__legal { border-top:1px solid rgba(255,255,255,.12); }
        .muni-gob-footer__legal { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; max-width:1180px; margin:0 auto; padding:14px clamp(16px,3vw,26px); font-size:11.5px; opacity:.6; }
    </style>
@endonce
