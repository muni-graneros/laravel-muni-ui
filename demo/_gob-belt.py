#!/usr/bin/env python3
"""Inyecta el cinturón institucional de Graneros en las landings de los subdominios.

Cada landing conserva su identidad propia; el cinturón (barra de gobierno con escudo,
franja de 7 colores y footer municipal) es lo que las hace pertenecer al ecosistema de
municipalidadgraneros.cl. Los colores institucionales son invariantes: no dependen del
tema de cada sistema.
"""
import re
import sys

GOB_VARS = """
    /* ── Identidad institucional de la Municipalidad de Graneros (invariante) ── */
    :root{--gob-lima:#adcd60;--gob-verde-d:#7fa33f;--gob-petroleo:#355a63;--gob-petroleo-d:#00404c;
        --gob-oro:#eab02c;--gob-naranja:#c76421;--gob-celeste:#7ccbe1;--gob-carmin:#ca3048;--gob-gris:#9c9b9b}
    .gob-bar{position:relative;z-index:61;background:var(--gob-petroleo-d);color:#e8f1f2;font-family:system-ui,-apple-system,'Segoe UI',sans-serif}
    .gob-bar__in{display:flex;align-items:center;gap:10px;max-width:1180px;margin:0 auto;padding:7px clamp(16px,3vw,26px);flex-wrap:wrap}
    .gob-bar__brand{display:inline-flex;align-items:center;gap:9px;color:inherit;text-decoration:none;font-weight:700;font-size:12.5px}
    .gob-bar__sep{opacity:.4}.gob-bar__sys{font-size:12.5px;opacity:.85;font-weight:500}
    .gob-bar .gsp{flex:1}
    .gob-bar__back{font-size:11.5px;font-weight:600;color:#e8f1f2;text-decoration:none;opacity:.8;white-space:nowrap}
    .gob-bar__back:hover{opacity:1;text-decoration:underline}
    @media(max-width:560px){.gob-bar__back{display:none}}
    .gob-stripe{height:5px;width:100%;background:linear-gradient(90deg,var(--gob-lima) 0 14.28%,var(--gob-petroleo) 14.28% 28.57%,var(--gob-oro) 28.57% 42.85%,var(--gob-naranja) 42.85% 57.14%,var(--gob-celeste) 57.14% 71.42%,var(--gob-carmin) 71.42% 85.71%,var(--gob-gris) 85.71% 100%)}
    .gob-footer{position:relative;z-index:5;background:var(--gob-petroleo-d);color:#d9e6e8;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;margin-top:60px}
    .gob-footer__in{display:flex;gap:clamp(24px,5vw,60px);max-width:1180px;margin:0 auto;padding:36px clamp(16px,3vw,26px) 28px;flex-wrap:wrap}
    .gob-footer__brand{display:flex;align-items:center;gap:14px}
    .gob-footer__name{font-size:15px;font-weight:800;color:#fff}
    .gob-footer__sys{font-size:12.5px;opacity:.75;margin-top:2px}
    .gob-footer__cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:26px;flex:1;min-width:260px}
    .gob-footer__col h4{font-family:ui-monospace,monospace;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--gob-lima);margin-bottom:10px}
    .gob-footer__col p{font-size:12.5px;line-height:1.55;margin-bottom:6px;opacity:.85}
    .gob-footer__col a{color:#d9e6e8;text-decoration:none}.gob-footer__col a:hover{color:#fff;text-decoration:underline}
    .gob-footer__legal{border-top:1px solid rgba(255,255,255,.12);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;max-width:1180px;margin:0 auto;padding:14px clamp(16px,3vw,26px);font-size:11.5px;opacity:.6}
"""

def escudo(size, cid):
    """Escudo oficial en SVG (ave sobre cerros y campos). `cid` evita colisión de ids."""
    return f'''<svg viewBox="0 0 100 106" width="{size}" height="{round(size*1.06)}" role="img" aria-label="Escudo de la Municipalidad de Graneros" style="flex-shrink:0;display:block">
<defs><clipPath id="{cid}"><path d="M6 12c0-3 2-5 5-5h78c3 0 5 2 5 5v52c0 18-16 29-31 35-8 3-13 5-13 5s-5-2-13-5C22 93 6 82 6 64V12z"/></clipPath></defs>
<g clip-path="url(#{cid})"><rect width="100" height="106" fill="#dff1f7"/>
<circle cx="72" cy="26" r="11" fill="none" stroke="var(--gob-oro)" stroke-width="3.4"/>
<path d="M45 20l1.7 4.2 4.3 1.7-4.3 1.7L45 32l-1.7-4.4-4.3-1.7 4.3-1.7z" fill="var(--gob-petroleo-d)"/>
<path d="M0 62c14-12 22-16 33-9s16 10 25 4 24-8 42 3v46H0z" fill="var(--gob-lima)"/>
<path d="M0 74c16-8 28-9 40-4s22 6 33 1 20-5 27-2v37H0z" fill="var(--gob-verde-d)" opacity=".55"/>
<g stroke="#fff" stroke-width="2.2" opacity=".55" fill="none" stroke-linecap="round"><path d="M4 86c18-6 34-6 46-2s28 4 46-2"/><path d="M6 94c18-6 34-6 46-2s26 4 44-2"/></g>
<g fill="var(--gob-petroleo)"><path d="M20 44c9-7 19-11 28-9 4 1 7 3 10 6 4 4 9 6 15 6-6 4-13 4-19 1-3-2-6-4-9-5-8-3-17 0-25 1z"/><path d="M31 41c6 3 12 8 16 14 2 3 2 6 1 9-3-4-6-8-10-11-3-3-6-6-7-12z"/></g>
<path d="M48 52c5 1 9 3 12 6-4 1-9 0-12-2z" fill="var(--gob-carmin)"/></g>
<path d="M6 12c0-3 2-5 5-5h78c3 0 5 2 5 5v52c0 18-16 29-31 35-8 3-13 5-13 5s-5-2-13-5C22 93 6 82 6 64V12z" fill="none" stroke="var(--gob-petroleo)" stroke-width="5"/></svg>'''

def bar(system, slug):
    return f'''
<div class="gob-bar"><div class="gob-bar__in">
<a class="gob-bar__brand" href="https://www.municipalidadgraneros.cl/">{escudo(24, slug+'-eb')}Municipalidad de Graneros</a>
<span class="gob-bar__sep" aria-hidden="true">/</span><span class="gob-bar__sys">{system}</span>
<span class="gsp"></span><a class="gob-bar__back" href="https://www.municipalidadgraneros.cl/">Ir al sitio municipal ↗</a>
</div></div><div class="gob-stripe"></div>
'''

def footer(system, slug, sub, mail, links):
    ls = ''.join(f'<p><a href="#">{l}</a></p>' for l in links)
    return f'''
<footer class="gob-footer"><div class="gob-stripe"></div>
<div class="gob-footer__in">
<div class="gob-footer__brand">{escudo(50, slug+'-ef')}<div><div class="gob-footer__name">Municipalidad de Graneros</div><div class="gob-footer__sys">{sub}</div></div></div>
<div class="gob-footer__cols">
<div class="gob-footer__col"><h4>Contacto</h4><p>Av. Bernardo O'Higgins 630, Graneros</p><p><a href="tel:+56722491000">+56 72 249 1000</a></p><p><a href="mailto:{mail}">{mail}</a></p></div>
<div class="gob-footer__col"><h4>Ecosistema</h4><p><a href="https://www.municipalidadgraneros.cl/">Sitio municipal</a></p>{ls}</div>
<div class="gob-footer__col"><h4>Tus datos</h4><p>Tratamos tus datos personales conforme a la <b>Ley 21.719</b>. La información no sale de los sistemas del municipio.</p></div>
</div></div>
<div class="gob-footer__legal"><span>© 2026 Ilustre Municipalidad de Graneros</span><span>{slug}.municipalidadgraneros.cl</span></div>
</footer>'''

def apply(path, system, slug, sub, mail, links):
    html = open(path).read()
    if 'gob-bar' in html:
        print(f"  {path}: ya tiene cinturón, omito"); return
    # 1) estilos: antes del cierre del primer <style>
    html = html.replace('</style>', GOB_VARS + '</style>', 1)
    # 2) barra: justo después de <body ...>
    html = re.sub(r'(<body[^>]*>)', r'\1' + bar(system, slug), html, count=1)
    # 3) footer institucional: antes del primer <script> del final o al final del body
    foot = footer(system, slug, sub, mail, links)
    if '<script>' in html:
        i = html.rindex('<script>')
        html = html[:i] + foot + '\n' + html[i:]
    else:
        html += foot
    open(path, 'w').write(html)
    print(f"  {path}: cinturón aplicado ({len(html)} bytes)")

if __name__ == '__main__':
    base = '/home/cesar/Dev/laravel-muni-ui/demo/'
    apply(base+'landing-licencias.html', 'Licencias de Conducir', 'licencias',
          'Dirección de Tránsito', 'transito@municipalidadgraneros.cl',
          ['Patentes comerciales', 'Oficina de Inclusión', 'Transparencia'])
    apply(base+'landing-patentes.html', 'Patentes Comerciales', 'patentes',
          'Dirección de Administración y Finanzas', 'rentas@municipalidadgraneros.cl',
          ['Licencias de conducir', 'Oficina de Inclusión', 'Transparencia'])
    apply(base+'landing-control-acceso.html', 'Control de Acceso', 'acceso',
          'Dirección de Seguridad', 'seguridad@municipalidadgraneros.cl',
          ['Patentes comerciales', 'Licencias de conducir', 'Transparencia'])
    apply(base+'landing-hub.html', 'Ecosistema Digital', 'digital',
          'Dirección de Modernización', 'digital@municipalidadgraneros.cl',
          ['Patentes comerciales', 'Licencias de conducir', 'Oficina de Inclusión'])
