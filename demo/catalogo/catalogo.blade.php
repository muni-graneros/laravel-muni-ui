@php
    $id = fn ($s) => 'c-'.$s;
    $contarProps = fn ($c) => count($c['meta']['props']) + array_sum(array_map(fn ($s) => count($s['props']), $c['subs']));
@endphp
<!DOCTYPE html>
<html lang="es" data-muni-theme="light"
      x-data="{
          tema: (document.documentElement.getAttribute('data-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')),
          q: '',
          ve(t){ const s = this.q.trim().toLowerCase(); return !s || t.includes(s); },
          async copiar(el, btn){
              const txt = el.textContent;
              try { await navigator.clipboard.writeText(txt); }
              catch(e){ const r = document.createRange(); r.selectNodeContents(el); const s = getSelection(); s.removeAllRanges(); s.addRange(r); }
              btn.textContent = 'Copiado'; setTimeout(() => btn.textContent = 'Copiar', 1400);
          },
          leer(v){ return getComputedStyle(document.documentElement).getPropertyValue(v).trim(); },
          contraste(a, b){
              const lum = h => { h = h.replace('#',''); if (h.length === 3) h = [...h].map(c => c + c).join('');
                  return [0, 2, 4].map(i => parseInt(h.substr(i, 2), 16) / 255).map(c => c <= .03928 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4)
                      .reduce((s, c, i) => s + c * [.2126, .7152, .0722][i], 0); };
              if (!/^#[0-9a-f]{3,6}$/i.test(a) || !/^#[0-9a-f]{3,6}$/i.test(b)) return null;
              const [x, y] = [lum(a), lum(b)].sort((m, n) => n - m); return (x + .05) / (y + .05);
          },
          temaIframes(){ document.querySelectorAll('iframe[data-shell]').forEach(f => { try { f.contentDocument.documentElement.setAttribute('data-muni-theme', this.tema); } catch(e){} }); }
      }"
      :data-muni-theme="tema" x-effect="tema; temaIframes()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Catálogo muni-ui</title>
    {!! $head !!}
    <style>
        *,*::before,*::after{ box-sizing:border-box; }
        html{ scroll-padding-top:72px; }
        body{ margin:0; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); font-size:14px; line-height:1.5; }
        code,kbd,pre{ font-family:var(--muni-font-mono); }
        .cat{ display:grid; grid-template-columns:232px minmax(0,1fr); max-width:1360px; margin:0 auto; }
        .cat-toc{ position:sticky; top:0; align-self:start; height:100vh; overflow-y:auto; padding:22px 14px 40px; border-right:1px solid var(--muni-border); font-size:12.5px; }
        .cat-toc__brand{ display:flex; align-items:center; gap:10px; margin:0 6px 18px; font-weight:700; font-size:14px; }
        .cat-toc h4{ margin:18px 8px 4px; font-size:10.5px; letter-spacing:.08em; text-transform:uppercase; color:var(--muni-hint); font-weight:600; }
        .cat-toc a{ display:flex; justify-content:space-between; padding:3px 8px; border-radius:6px; color:var(--muni-muted); text-decoration:none; font-family:var(--muni-font-mono); }
        .cat-toc a:hover{ background:var(--muni-surface-2); color:var(--muni-text); }
        .cat-toc a:focus-visible{ outline:none; box-shadow:var(--muni-ring); }
        .cat-main{ min-width:0; padding:0 32px 96px; }
        .cat-bar{ position:sticky; top:env(safe-area-inset-top,0px); z-index:60; display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:12px 0; background:color-mix(in srgb,var(--muni-bg) 88%,transparent); backdrop-filter:blur(8px); border-bottom:1px solid var(--muni-border); }
        .cat-search{ flex:1; min-width:180px; max-width:420px; padding:9px 12px 9px 34px; border:1px solid var(--muni-border); border-radius:var(--muni-radius-sm); background:var(--muni-surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%23888' stroke-width='1.6'%3E%3Ccircle cx='9' cy='9' r='6'/%3E%3Cpath d='M18 18l-4.5-4.5' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat 11px center / 15px; color:var(--muni-text); font:inherit; }
        .cat-search:focus{ outline:none; box-shadow:var(--muni-ring); border-color:var(--muni-accent); }
        .cat-seg{ border:0; background:transparent; font:inherit; }
        .cat-hero{ padding:40px 0 12px; display:grid; grid-template-columns:minmax(0,1fr); gap:18px; }
        .cat-hero h1{ margin:0; font-size:clamp(26px,4vw,34px); letter-spacing:-.015em; line-height:1.1; text-wrap:balance; }
        .cat-hero p{ margin:0; color:var(--muni-muted); font-size:15px; max-width:68ch; }
        .cat-facts{ display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px 22px; max-width:760px; font-size:12.5px; color:var(--muni-muted); }
        .cat-facts b{ display:block; font-family:var(--muni-font-mono); font-size:22px; color:var(--muni-text); font-variant-numeric:tabular-nums; }
        .cat-start{ display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px; }
        .cat-start > div{ display:grid; gap:6px; align-content:start; min-width:0; }
        .cat-start .cat-code{ border:1px solid var(--muni-border); border-radius:var(--muni-radius); min-width:0; }
        .cat-start span{ font-size:12px; font-weight:600; color:var(--muni-muted); }
        .cat-group{ display:flex; align-items:baseline; gap:12px; margin:56px 0 16px; padding-bottom:8px; border-bottom:1px solid var(--muni-border); }
        .cat-group h2{ margin:0; font-size:19px; letter-spacing:-.01em; }
        .cat-group span{ font-family:var(--muni-font-mono); font-size:12px; color:var(--muni-hint); }
        .cat-item{ display:grid; grid-template-columns:minmax(0,1fr); gap:10px; margin:0 0 36px; }
        .cat-item__head{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .cat-item__head h3{ margin:0; font-family:var(--muni-font-mono); font-size:15px; font-weight:600; }
        .cat-item__head a{ color:inherit; text-decoration:none; }
        .cat-item__head a:hover::after{ content:" #"; color:var(--muni-hint); }
        .cat-tag{ font-size:10.5px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; padding:2px 7px; border-radius:999px; border:1px solid var(--muni-border); color:var(--muni-muted); }
        .cat-tag--js{ color:var(--muni-info-fg); border-color:color-mix(in srgb,var(--muni-info-fg) 35%,transparent); }
        .cat-desc{ margin:0; color:var(--muni-muted); max-width:78ch; }
        .cat-desc code, .cat-note code, .cat-props code{ font-size:12px; background:var(--muni-surface-2); padding:1px 5px; border-radius:4px; }
        .cat-demo{ padding:24px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-lg); display:flex; flex-wrap:wrap; gap:14px; align-items:center; }
        .cat-demo > *{ min-width:0; max-width:100%; }
        .cat-demo--columna{ flex-direction:column; align-items:stretch; }
        .cat-demo--grilla{ display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); align-items:start; }
        .cat-demo--lienzo{ background:var(--muni-bg); align-items:stretch; }
        .cat-demo--lienzo > *{ flex:1 1 200px; }
        .cat-demo--sangrado{ padding:0; overflow:hidden; display:block; }
        .cat-demo--sangrado .muni-sb{ transform:none !important; position:static !important; box-shadow:none !important; }
        .cat-demo--sangrado .muni-sb__inner{ height:auto; position:static; }
        .cat-demo--aire{ padding:56px 24px 24px; }
        .cat-demo--alto{ min-height:200px; align-items:flex-start; }
        .cat-demo--iframe{ padding:0; display:grid; grid-template-columns:1fr; overflow:hidden; background:var(--muni-surface-2); }
        .cat-demo--iframe iframe{ width:100%; height:480px; border:0; display:block; background:var(--muni-bg); }
        .cat-more{ display:flex; gap:8px; flex-wrap:wrap; align-items:flex-start; }
        .cat-more details{ flex:1 1 100%; min-width:0; border:1px solid var(--muni-border); border-radius:var(--muni-radius); background:var(--muni-surface); overflow:hidden; }
        .cat-more summary{ cursor:pointer; list-style:none; display:flex; align-items:center; gap:8px; padding:9px 14px; font-size:12.5px; font-weight:600; color:var(--muni-muted); user-select:none; }
        .cat-more summary::-webkit-details-marker{ display:none; }
        .cat-more summary::before{ content:"›"; font-size:15px; line-height:1; transition:transform var(--muni-dur) var(--muni-ease); }
        .cat-more details[open] summary::before{ transform:rotate(90deg); }
        .cat-more summary:hover{ color:var(--muni-text); }
        .cat-more summary:focus-visible{ outline:none; box-shadow:inset var(--muni-ring); }
        .cat-code{ position:relative; border-top:1px solid var(--muni-border); background:var(--muni-surface-2); }
        .cat-code pre{ margin:0; padding:16px 18px; overflow-x:auto; font-size:12.5px; line-height:1.6; tab-size:4; }
        .cat-copy{ position:absolute; top:8px; right:8px; padding:4px 10px; font:600 11.5px var(--muni-font-sans); color:var(--muni-muted); background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:6px; cursor:pointer; }
        .cat-copy:hover{ color:var(--muni-text); border-color:var(--muni-border-2); }
        .cat-copy:focus-visible{ outline:none; box-shadow:var(--muni-ring); }
        .tk-x{ color:var(--muni-accent); font-weight:600; } .tk-t{ color:var(--muni-muted); } .tk-a{ color:var(--muni-info-fg); }
        .tk-s{ color:var(--muni-ok-fg); } .tk-d{ color:var(--muni-warn-fg); } .tk-c{ color:var(--muni-hint); font-style:italic; }
        .cat-props{ border-top:1px solid var(--muni-border); overflow-x:auto; }
        .cat-props table{ width:100%; border-collapse:collapse; font-size:12.5px; }
        .cat-props caption{ text-align:left; padding:10px 14px 4px; font-family:var(--muni-font-mono); font-weight:600; font-size:12px; color:var(--muni-text); }
        .cat-props th{ text-align:left; font-size:10.5px; letter-spacing:.06em; text-transform:uppercase; color:var(--muni-hint); font-weight:600; padding:8px 14px; border-bottom:1px solid var(--muni-border); }
        .cat-props td{ padding:7px 14px; border-bottom:1px solid var(--muni-border); vertical-align:top; }
        .cat-props tr:last-child td{ border-bottom:0; }
        .cat-props td:first-child{ font-family:var(--muni-font-mono); font-weight:600; white-space:nowrap; }
        .cat-props td:nth-child(2){ font-family:var(--muni-font-mono); color:var(--muni-muted); white-space:nowrap; }
        .cat-req{ color:var(--muni-danger-fg); font-family:var(--muni-font-sans); font-weight:600; font-size:11px; }
        .cat-slots{ display:flex; gap:6px; flex-wrap:wrap; align-items:center; padding:10px 14px; font-size:12px; color:var(--muni-muted); border-top:1px solid var(--muni-border); }
        .cat-slots code{ font-size:11.5px; }
        .cat-note{ margin:0; padding:10px 14px; font-size:12.5px; color:var(--muni-muted); border-top:1px solid var(--muni-border); }
        .cat-recipe{ display:grid; grid-template-columns:minmax(0,1fr); gap:10px; margin:0 0 44px; }
        .cat-recipe h3{ margin:0; font-size:16px; }
        .cat-recipe .cat-demo{ display:grid; grid-template-columns:minmax(0,1fr); gap:16px; background:var(--muni-bg); padding:22px; }
        .cat-recipe .cat-demo--flush{ padding:0; gap:0; overflow:hidden; }
        .cat-uses{ display:flex; gap:6px; flex-wrap:wrap; }
        .cat-uses a{ font-family:var(--muni-font-mono); font-size:11.5px; color:var(--muni-accent); text-decoration:none; padding:2px 7px; border:1px solid color-mix(in srgb,var(--muni-accent) 30%,transparent); border-radius:999px; }
        .cat-uses a:hover{ background:var(--muni-accent-soft); }
        .cat-mix{ display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; }
        .cat-mix article{ padding:16px 18px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius); }
        .cat-mix h3{ margin:0 0 6px; font-size:14px; font-family:var(--muni-font-mono); }
        .cat-mix p{ margin:0; font-size:13px; color:var(--muni-muted); }
        .cat-fund{ display:grid; gap:28px; }
        .cat-fund h3{ margin:0 0 10px; font-size:14px; }
        .cat-fund p{ margin:0 0 12px; color:var(--muni-muted); font-size:13px; max-width:72ch; }
        .cat-sw{ display:grid; grid-template-columns:repeat(auto-fill,minmax(168px,1fr)); gap:10px; }
        .cat-sw > div{ display:grid; grid-template-rows:56px auto; border:1px solid var(--muni-border); border-radius:var(--muni-radius); overflow:hidden; background:var(--muni-surface); }
        .cat-sw__chip{ display:grid; place-items:center; font-weight:700; font-size:15px; border-bottom:1px solid var(--muni-border); }
        .cat-sw__meta{ padding:8px 10px; display:grid; gap:1px; font-size:11.5px; }
        .cat-sw__meta code{ font-weight:600; font-size:11.5px; color:var(--muni-text); }
        .cat-sw__meta span{ font-family:var(--muni-font-mono); color:var(--muni-muted); font-variant-numeric:tabular-nums; }
        .cat-sw__meta .cat-bad{ color:var(--muni-danger-fg); font-weight:600; }
        .cat-type{ display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; }
        .cat-type > div{ padding:16px 18px; border:1px solid var(--muni-border); border-radius:var(--muni-radius); background:var(--muni-surface); display:grid; gap:6px; }
        .cat-type small{ font-family:var(--muni-font-mono); color:var(--muni-muted); font-size:11.5px; }
        .cat-shape{ display:flex; gap:12px; flex-wrap:wrap; }
        .cat-shape > div{ width:132px; height:84px; display:grid; place-items:center; text-align:center; font-family:var(--muni-font-mono); font-size:11px; color:var(--muni-muted); background:var(--muni-surface); border:1px solid var(--muni-border); }
        .cat-empty{ padding:40px 0; color:var(--muni-muted); }
        @media (max-width:900px){
            .cat{ grid-template-columns:1fr; }
            .cat-toc{ display:none; }
            .cat-main{ padding:0 16px 72px; }
            .cat-demo{ padding:18px; }
        }
        @media (prefers-reduced-motion:reduce){ html{ scroll-behavior:auto; } }
    </style>
</head>
<body>
<x-muni::toast-host />
<div class="cat">
    <nav class="cat-toc" aria-label="Componentes">
        <div class="cat-toc__brand"><x-muni::gob-escudo size="26" /> muni-ui</div>
        @foreach ($grupos as $grupo => $items)
            <h4>{{ $grupo }}</h4>
            @foreach ($items as $nombre => $c)
                <a href="#{{ $id($nombre) }}" x-show="ve(@js(strtolower($nombre.' '.implode(' ', array_keys($c['subs'])).' '.$c['desc'])))">{{ $nombre }}</a>
            @endforeach
        @endforeach
        <h4>Guías</h4>
        <a href="#fundamentos">fundamentos</a>
        <a href="#recetas">recetas de pantalla</a>
        <a href="#solapes">solapamientos</a>
    </nav>

    <main class="cat-main">
        <div class="cat-bar">
            <input class="cat-search" type="search" id="buscar" x-model="q" placeholder="Buscar componente, prop o uso…" aria-label="Buscar componente">
            <x-muni::segmented aria-label="Tema">
                <button type="button" class="muni-seg cat-seg" :class="tema === 'light' && 'muni-seg--on'" @click="tema = 'light'">Claro</button>
                <button type="button" class="muni-seg cat-seg" :class="tema === 'dark' && 'muni-seg--on'" @click="tema = 'dark'">Oscuro</button>
            </x-muni::segmented>
        </div>

        <header class="cat-hero" x-show="!q">
            <h1>Componentes de la Municipalidad de Graneros</h1>
            <p>Lo que comparten licencias, discapacidad, feria, seguridad, credenciales, control de acceso y el sitio web. Cada ejemplo está renderizado con el Blade del paquete, y el código que se muestra es el archivo exacto que lo produce.</p>
            <div class="cat-facts">
                <div><b>{{ $total }}</b>componentes</div>
                <div><b>{{ count($recetas) }}</b>recetas de pantalla</div>
                <div><b>2</b>temas: claro (discapacidad) y oscuro (feria)</div>
                <div><b>0</b>dependencias JS además de Alpine</div>
            </div>
            <div class="cat-start">
                <div><span>1 · Instalar</span><div class="cat-code"><pre>composer require muni-graneros/laravel-muni-ui</pre></div></div>
                <div><span>2 · Importar en resources/css/app.css</span><div class="cat-code"><pre>@import "tailwindcss";
@import "../../vendor/muni-graneros/laravel-muni-ui/resources/css/muni-ui.css";
@source "../../vendor/muni-graneros/laravel-muni-ui/resources/views/**/*.blade.php";</pre></div></div>
                <div><span>3 · Usar</span><div class="cat-code"><pre>{!! $inicio !!}</pre></div></div>
            </div>
        </header>

        @php
            $familias = [
                'Superficies' => ['muni-bg', 'muni-surface', 'muni-surface-2', 'muni-surface-3', 'muni-panel', 'muni-border', 'muni-border-2'],
                'Texto' => ['muni-text', 'muni-muted', 'muni-hint'],
                'Acento' => ['muni-accent', 'muni-accent-strong', 'muni-accent-soft', 'muni-on-accent'],
            ];
            $esTexto = ['muni-text', 'muni-muted', 'muni-hint', 'muni-accent', 'muni-accent-strong'];
        @endphp
        <section x-show="!q" id="fundamentos">
            <div class="cat-group"><h2>Fundamentos</h2><span>tokens de muni-ui.css</span></div>
            <div class="cat-fund">
                <div>
                    <h3>Identidad institucional</h3>
                    <p>Los 7 colores del escudo y la franja municipal. No cambian con el tema: el municipio es el mismo en claro y en oscuro.</p>
                    <div class="cat-sw">
                        @foreach ($tokens['gob'] as $nombre => $valor)
                            <div><span class="cat-sw__chip" style="background:var(--{{ $nombre }});"></span>
                                <span class="cat-sw__meta"><code>--{{ $nombre }}</code><span>{{ $valor }}</span></span></div>
                        @endforeach
                    </div>
                </div>
                @foreach ($familias as $familia => $nombres)
                    <div>
                        <h3>{{ $familia }}</h3>
                        @if ($familia === 'Texto')<p>El número es el contraste contra <code>--muni-surface</code> en el tema visible. Texto normal pide 4,5 y texto grande o bordes de control, 3.</p>@endif
                        <div class="cat-sw">
                            @foreach ($nombres as $nombre)
                                <div x-data="{ v: '', c: null }" x-effect="tema; $nextTick(() => { v = leer('--{{ $nombre }}'); c = contraste(v, leer('--muni-surface')); })">
                                    @if (in_array($nombre, $esTexto))
                                        <span class="cat-sw__chip" style="background:var(--muni-surface);color:var(--{{ $nombre }});">Aa</span>
                                    @elseif ($nombre === 'muni-on-accent')
                                        <span class="cat-sw__chip" style="background:var(--muni-accent);color:var(--{{ $nombre }});">Aa</span>
                                    @else
                                        <span class="cat-sw__chip" style="background:var(--{{ $nombre }});"></span>
                                    @endif
                                    <span class="cat-sw__meta"><code>--{{ $nombre }}</code>
                                        <span><span x-text="v"></span>@if (in_array($nombre, $esTexto)) · <b x-text="c ? c.toFixed(2) + ':1' : ''" :class="c && c < 4.5 && 'cat-bad'"></b>@endif</span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div>
                    <h3>Estados</h3>
                    <p>Cada tono trae tres tokens: <code>-fg</code> para texto e íconos, <code>-bg</code> para el fondo y <code>-border</code> para el contorno. Los usan badge, alert, kpi, stat, las franjas de fila y los gráficos.</p>
                    <div class="cat-sw">
                        @foreach (['ok' => 'Al día', 'warn' => 'Por vencer', 'danger' => 'Morosa', 'info' => 'En revisión'] as $tono => $texto)
                            <div x-data="{ c: null }" x-effect="tema; $nextTick(() => c = contraste(leer('--muni-{{ $tono }}-fg'), leer('--muni-{{ $tono }}-bg')))">
                                <span class="cat-sw__chip" style="background:var(--muni-{{ $tono }}-bg);color:var(--muni-{{ $tono }}-fg);border-bottom-color:var(--muni-{{ $tono }}-border);font-size:13px;">{{ $texto }}</span>
                                <span class="cat-sw__meta"><code>--muni-{{ $tono }}-*</code><span>fg sobre bg · <b x-text="c ? c.toFixed(2) + ':1' : ''" :class="c && c < 4.5 && 'cat-bad'"></b></span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h3>Tipografía</h3>
                    <p>Cada tema trae su par: DM Sans y DM Mono en claro, IBM Plex Sans y Plex Mono en oscuro. La mono se usa para cifras, RUT y códigos (<code>.muni-num</code>, con dígitos tabulares).</p>
                    <div class="cat-type">
                        <div><small>--muni-font-sans</small><span style="font-size:26px;font-weight:700;letter-spacing:-.01em;">Patentes comerciales</span><span style="color:var(--muni-muted);">Solicitud ingresada en el mesón de atención.</span></div>
                        <div><small>--muni-font-mono · .muni-num</small><span class="muni-num" style="font-family:var(--muni-font-mono);font-size:26px;font-weight:600;font-variant-numeric:tabular-nums;">76.123.456-7</span><span style="font-family:var(--muni-font-mono);color:var(--muni-muted);">$ 1.284.500 · 25-09-2026</span></div>
                    </div>
                </div>
                <div>
                    <h3>Forma, profundidad y movimiento</h3>
                    <div class="cat-shape">
                        @foreach (['radius-sm', 'radius', 'radius-lg'] as $r)<div style="border-radius:var(--muni-{{ $r }});">--muni-{{ $r }}<br>{{ $tokens['light']['muni-'.$r] }}</div>@endforeach
                        @foreach (['shadow', 'shadow-md', 'shadow-lg'] as $sh)<div style="border-radius:var(--muni-radius);box-shadow:var(--muni-{{ $sh }});border-color:transparent;">--muni-{{ $sh }}</div>@endforeach
                        <div style="border-radius:var(--muni-radius);box-shadow:var(--muni-ring);">--muni-ring<br>foco visible</div>
                        <div style="border-radius:var(--muni-radius);">--muni-dur<br>{{ $tokens['light']['muni-dur'] }} · 0 con movimiento reducido</div>
                    </div>
                </div>
            </div>
        </section>

        @foreach ($grupos as $grupo => $items)
            <div x-show="{{ collect($items)->map(fn ($c, $n) => 've('.\Illuminate\Support\Js::from(strtolower($n.' '.implode(' ', array_keys($c['subs'])).' '.$c['desc'].' '.implode(' ', array_column($c['meta']['props'], 'nombre')))).')')->implode(' || ') }}">
                <div class="cat-group"><h2>{{ $grupo }}</h2><span>{{ count($items) }}</span></div>
                @foreach ($items as $nombre => $c)
                    @php
                        $tieneJs = $c['meta']['alpine'] || collect($c['subs'])->contains(fn ($s) => $s['alpine']);
                        $buscable = strtolower($nombre.' '.implode(' ', array_keys($c['subs'])).' '.$c['desc'].' '.implode(' ', array_column($c['meta']['props'], 'nombre')));
                    @endphp
                    <section class="cat-item" id="{{ $id($nombre) }}" x-show="ve(@js($buscable))">
                        <div class="cat-item__head">
                            <h3><a href="#{{ $id($nombre) }}">{{ $nombre }}</a></h3>
                            @foreach (array_keys($c['subs']) as $sub)<code class="cat-tag" id="{{ $id($sub) }}">+ {{ $sub }}</code>@endforeach
                            @if ($tieneJs)<span class="cat-tag cat-tag--js" title="Necesita Alpine 3 (viene con Livewire)">Alpine</span>@else<span class="cat-tag" title="Blade puro: funciona sin JavaScript">Sin JS</span>@endif
                        </div>
                        <p class="cat-desc">{!! preg_replace('/`([^`]+)`/', '<code>$1</code>', e($c['desc'])) !!}</p>

                        @if ($c['vista'] === 'iframe')
                            <div class="cat-demo cat-demo--iframe">
                                <iframe data-shell title="Vista de {{ $nombre }}" loading="lazy" srcdoc="{{ $c['html'] }}" @load="$el.contentDocument.documentElement.setAttribute('data-muni-theme', tema)"></iframe>
                            </div>
                        @else
                            <div class="cat-demo cat-demo--{{ $c['vista'] }}" data-shot="{{ $nombre }}">{!! $c['html'] !!}</div>
                        @endif

                        <div class="cat-more">
                            <details>
                                <summary>Código</summary>
                                <div class="cat-code">
                                    <button type="button" class="cat-copy" @click="copiar($refs.src_{{ str_replace('-', '_', $nombre) }}, $el)">Copiar</button>
                                    <pre x-ref="src_{{ str_replace('-', '_', $nombre) }}">{!! $c['codigo'] !!}</pre>
                                </div>
                            </details>
                            <details>
                                <summary>Props y slots <span style="font-weight:400;color:var(--muni-hint);">{{ $contarProps($c) }} props</span></summary>
                                @foreach (['x-muni::'.$nombre => $c['meta']] + collect($c['subs'])->mapWithKeys(fn ($s, $n) => ['x-muni::'.$n => $s])->all() as $tag => $meta)
                                    @if (count($meta['props']))
                                        <div class="cat-props">
                                            <table>
                                                @if (count($c['subs']))<caption>&lt;{{ $tag }}&gt;</caption>@endif
                                                <thead><tr><th>Prop</th><th>Por defecto</th><th>Descripción</th></tr></thead>
                                                <tbody>
                                                    @foreach ($meta['props'] as $p)
                                                        <tr>
                                                            <td>{{ $p['nombre'] }}</td>
                                                            <td>@if ($p['defecto'] === null)<span class="cat-req">obligatoria</span>@else{{ $p['defecto'] }}@endif</td>
                                                            <td>{{ $p['doc'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                    @if (count($meta['slots']))
                                        <div class="cat-slots"><span>Slots de <code>{{ $tag }}</code>:</span>
                                            @foreach ($meta['slots'] as $s)<code>{{ $s === 'slot' ? 'contenido' : 'x-slot:'.$s }}</code>@endforeach
                                        </div>
                                    @endif
                                @endforeach
                                @foreach ($c['eventos'] ?? [] as $ev)
                                    <p class="cat-note">Evento de ventana: <code>{{ $ev }}</code></p>
                                @endforeach
                            </details>
                        </div>
                    </section>
                @endforeach
            </div>
        @endforeach

        <div x-show="!q">
            <div class="cat-group" id="recetas"><h2>Recetas de pantalla</h2><span>{{ count($recetas) }}</span></div>
            <p class="cat-desc" style="margin-bottom:22px;">Pantallas completas armadas solo con componentes del paquete. El código de cada una se puede pegar tal cual en una vista del sistema.</p>
            @foreach ($recetas as $slug => $r)
                <section class="cat-recipe" id="receta-{{ $slug }}">
                    <h3>{{ $r['titulo'] }}</h3>
                    <p class="cat-desc">{{ $r['para'] }}</p>
                    <div class="cat-uses">@foreach ($r['usa'] as $u)<a href="#{{ $id($u) }}">{{ $u }}</a>@endforeach</div>
                    <div class="cat-demo {{ $slug === 'marco' ? 'cat-demo--flush' : '' }}" data-shot="receta-{{ $slug }}">{!! $r['html'] !!}</div>
                    <div class="cat-more">
                        <details>
                            <summary>Código</summary>
                            <div class="cat-code">
                                <button type="button" class="cat-copy" @click="copiar($refs.receta_{{ $slug }}, $el)">Copiar</button>
                                <pre x-ref="receta_{{ $slug }}">{!! $r['codigo'] !!}</pre>
                            </div>
                        </details>
                    </div>
                </section>
            @endforeach

            <div class="cat-group" id="solapes"><h2>Solapamientos</h2><span>candidatos a fusionar</span></div>
            <div class="cat-mix">
                <article><h3>kpi ⊂ stat</h3><p>stat acepta value, label, tone y hint igual que kpi, y suma delta y spark. kpi podría ser un alias de stat.</p></article>
                <article><h3>data-table ↔ sortable-table</h3><p>Dos tablas con estilos propios. Una sola con sortable y searchable opcionales evitaría que se separen con el tiempo.</p></article>
                <article><h3>field ↔ input · select</h3><p>field pone una etiqueta en mayúsculas; input y select traen otra distinta. En un mismo formulario conviven dos estilos de etiqueta.</p></article>
                <article><h3>app-shell ↔ dashboard-shell</h3><p>app-shell usa topbar; dashboard-shell dibuja su propia barra. Reutilizar topbar unificaría el indicador de estado.</p></article>
                <article><h3>Mapa de tonos</h3><p>kpi, stat, chart-bar, chart-donut, ring, progress, rating y timeline repiten el mismo mapa tono → color. Un helper común evitaría que diverjan.</p></article>
            </div>
        </div>

        <p class="cat-empty" x-show="q" x-cloak>¿No aparece? Busca por nombre de prop (por ejemplo <code>tone</code>) o por uso (por ejemplo <code>tabla</code>).</p>
    </main>
</div>
</body>
</html>
