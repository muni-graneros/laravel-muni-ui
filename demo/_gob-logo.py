#!/usr/bin/env python3
"""Reemplaza el escudo SVG reconstruido por el LOGO OFICIAL del municipio en las demos.

Las demos se publican como artifacts con CSP estricto (sin recursos externos), así que el
PNG oficial se embebe como data URI. Para no duplicar ~33 KB por cada aparición, se declara
UNA vez en `--gob-escudo` y los elementos lo usan como background-image.
"""
import re
import sys

SP = "/tmp/claude-1000/-home-cesar-Dev/556087fb-9dbd-4c73-a14b-4f32aa5c8adf/scratchpad"
DEMO = "/home/cesar/Dev/laravel-muni-ui/demo/"

with open(f"{SP}/logo_datauri.txt") as f:
    URI = f.read().strip()

# La variable con el escudo + las clases que lo pintan.
LOGO_CSS = (
    "\n    /* Escudo OFICIAL de la Municipalidad de Graneros (PNG institucional embebido) */\n"
    f"    :root{{--gob-escudo:url('{URI}')}}\n"
    "    .gob-escudo{background:var(--gob-escudo) center/contain no-repeat;flex-shrink:0;display:block}\n"
)

# Los <svg> del cinturón (barra y footer) generados antes.
SVG_RE = re.compile(
    r'<svg viewBox="0 0 100 106"[^>]*>.*?</svg>',
    re.DOTALL,
)


def apply(path):
    html = open(path).read()
    if '--gob-escudo' in html:
        print(f"  {path}: ya usa el logo oficial, omito")
        return

    n = len(SVG_RE.findall(html))
    if n == 0:
        print(f"  {path}: sin escudo SVG que reemplazar")
        return

    # Tamaño por posición: el primero es la barra (24px), el resto el footer (50px).
    counter = {'i': 0}

    def repl(m):
        counter['i'] += 1
        px = 24 if counter['i'] == 1 else 50
        return f'<span class="gob-escudo" style="width:{px}px;height:{px}px" role="img" aria-label="Escudo de la Municipalidad de Graneros"></span>'

    html = SVG_RE.sub(repl, html)
    html = html.replace('</style>', LOGO_CSS + '</style>', 1)
    open(path, 'w').write(html)
    print(f"  {path}: {n} escudo(s) → logo oficial ({len(html)} bytes)")


if __name__ == '__main__':
    for f in ['landing-hub.html', 'landing-licencias.html', 'landing-patentes.html',
              'landing-control-acceso.html', 'landing-discapacidad.html']:
        apply(DEMO + f)
