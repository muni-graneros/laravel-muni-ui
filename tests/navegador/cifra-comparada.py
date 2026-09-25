#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::stat>` sobre el banco `build/cifra-comparada/`.

    .venv-a11y/bin/python tests/navegador/cifra-comparada.py [build/cifra-comparada]

La corre `tests/CifraComparadaTest.php` después de generar el banco (y se salta
si no está el entorno de la reja, que en CI no se instala). Existe porque las
pruebas de Pest sobre `stat` leen el TEXTO del CSS del componente —que la regla
de movimiento reducido lleve `!important`, que `.muni-num` declare
`tabular-nums`— y eso ya engañó una vez: la primera versión de esa regla pasó el
test de texto y en Chromium la elevación seguía en 160 ms. Lo que de verdad
importa se mide acá, con `getComputedStyle` y el árbol de accesibilidad:

  · con `prefers-reduced-motion: reduce` la transición de la tarjeta computa a 0 s
    en las CUATRO páginas, incluidas `panel-*`, donde solo se carga
    `muni-ui-filament.css` y ese CSS NO baja `--muni-dur` (DESIGN §7). Sin la
    preferencia la transición existe: si no, la comprobación sería vacía;
  · `.muni-num` cae en la fuente mono del sistema (`--muni-font-mono` resuelto
    en la raíz) y en `tabular-nums`, también dentro del panel;
  · «subió» / «bajó» están en el árbol ARIA de Chromium bajo el nombre del grupo
    (`group "Eventos hoy": 128 Eventos hoy subió 12 % …`) y en pantalla miden
    1 × 1 px sin `display:none` ni `visibility:hidden`;
  · la flecha mide 12 × 12 px y va `aria-hidden`;
  · `aria-labelledby` apunta a un id que existe, no hay ids repetidos en la
    página y la tarjeta no mete ninguna parada de teclado;
  · todo texto visible de la tarjeta pasa WCAG 2.2 AA contra el fondo de la
    tarjeta (4,5:1; 3:1 si es grande), en las dos paletas y los dos temas.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "cifra-comparada"
MOVIMIENTOS = ("reduce", "no-preference")

# Lo que se lee de cada tarjeta con estilos computados. Se normalizan las
# familias tipográficas (comillas y espacios) para comparar el `font-family` de
# `.muni-num` con el token resuelto en la raíz.
SONDA = r"""
() => {
  const norm = s => String(s).replace(/["']/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };
  const mono = norm(getComputedStyle(document.documentElement).getPropertyValue('--muni-font-mono'));

  // Texto visible: elementos con texto propio (nodos de texto directos) que no
  // sean el span oculto. El contraste se mide contra el fondo de la tarjeta.
  const textosVisibles = tarjeta => [...tarjeta.querySelectorAll('*')]
    .filter(el => !el.closest('.muni-stat__sr') && el.tagName !== 'svg' && el.tagName !== 'path')
    .map(el => {
      const propio = [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join('').trim();
      if (propio === '') return null;
      const c = getComputedStyle(el);
      return { texto: propio, color: c.color, tamano: parseFloat(c.fontSize), peso: parseInt(c.fontWeight, 10) || 400 };
    })
    .filter(Boolean);

  const tarjetas = [...document.querySelectorAll('.muni-stat')].map(el => {
    const c = getComputedStyle(el);
    const labelledby = el.getAttribute('aria-labelledby');
    const rotulo = labelledby ? document.getElementById(labelledby) : null;
    return {
      id: el.id || null,
      role: el.getAttribute('role'),
      labelledby,
      rotulo: rotulo ? rotulo.textContent.trim() : null,
      transicionDuracion: c.transitionDuration,
      transicionPropiedad: c.transitionProperty,
      fondo: c.backgroundColor,
      paradasDeTeclado: el.querySelectorAll('a[href],button,input,select,textarea,[tabindex]').length,
      nums: [...el.querySelectorAll('.muni-num')].map(n => {
        const cn = getComputedStyle(n);
        return { familia: norm(cn.fontFamily), variante: cn.fontVariantNumeric };
      }),
      ocultos: [...el.querySelectorAll('.muni-stat__sr')].map(s => {
        const cs = getComputedStyle(s);
        return { texto: s.textContent.trim(), display: cs.display, visibility: cs.visibility, ...caja(s) };
      }),
      flechas: [...el.querySelectorAll('.muni-stat__delta svg')].map(f => ({ ...caja(f), ariaHidden: f.getAttribute('aria-hidden') })),
      textos: textosVisibles(el),
    };
  });

  const ids = [...document.querySelectorAll('[id]')].map(e => e.id);
  return {
    mono,
    fondoDeLaPagina: getComputedStyle(document.body).backgroundColor,
    idsRepetidos: ids.filter((v, i, a) => a.indexOf(v) !== i),
    tarjetas,
  };
}
"""


def _rgb(valor: str) -> tuple[float, float, float, float]:
    """`rgb(r, g, b)` o `rgba(r, g, b, a)` → (r, g, b, a) en 0-255 y 0-1."""
    partes = re.findall(r"[\d.]+", valor)
    if len(partes) < 3:
        raise ValueError(f"color no reconocido: {valor!r}")
    r, g, b = (float(x) for x in partes[:3])
    a = float(partes[3]) if len(partes) > 3 else 1.0
    return r, g, b, a


def _mezclar(frente: str, fondo: str) -> tuple[float, float, float]:
    fr, fg, fb, fa = _rgb(frente)
    br, bg, bb, _ = _rgb(fondo)
    return (fr * fa + br * (1 - fa), fg * fa + bg * (1 - fa), fb * fa + bb * (1 - fa))


def _luminancia(rgb: tuple[float, float, float]) -> float:
    def canal(c: float) -> float:
        c /= 255
        return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

    r, g, b = rgb
    return 0.2126 * canal(r) + 0.7152 * canal(g) + 0.0722 * canal(b)


def contraste(texto: str, fondo_tarjeta: str, fondo_pagina: str) -> float:
    """Ratio WCAG del texto contra el fondo efectivo de la tarjeta (la tarjeta
    pinta un `--muni-surface` opaco; si tuviera alfa se compone sobre la página)."""
    fondo = _mezclar(fondo_tarjeta, fondo_pagina)
    frente = _mezclar(texto, f"rgb({fondo[0]}, {fondo[1]}, {fondo[2]})")
    l1, l2 = _luminancia(frente), _luminancia(fondo)
    claro, oscuro = max(l1, l2), min(l1, l2)
    return (claro + 0.05) / (oscuro + 0.05)


def es_texto_grande(tamano: float, peso: int) -> bool:
    return tamano >= 24 or (tamano >= 18.66 and peso >= 700)


def verificar_pagina(pagina, ruta: Path, movimiento: str, fallos: list[str]) -> dict:
    """Aplica la sonda a una página abierta y acumula los fallos. Devuelve la medida."""
    d = pagina.evaluate(SONDA)
    donde = f"{ruta.name} [{movimiento}]"

    if len(d["tarjetas"]) == 0:
        fallos.append(f"{donde}: no hay ninguna .muni-stat en la página")
        return d
    if d["idsRepetidos"]:
        fallos.append(f"{donde}: ids repetidos en la página: {sorted(set(d['idsRepetidos']))}")

    for i, t in enumerate(d["tarjetas"]):
        quien = f"{donde} tarjeta {i} ({t['rotulo'] or t['labelledby']})"

        # Movimiento: con la preferencia la transición computa a 0 s, TAMBIÉN en
        # el panel; sin ella existe (si no, este chequeo no probaría nada).
        duraciones = [x.strip() for x in t["transicionDuracion"].split(",")]
        if movimiento == "reduce":
            if any(x != "0s" for x in duraciones) or t["transicionPropiedad"] != "none":
                fallos.append(
                    f"{quien}: con prefers-reduced-motion la transición sigue viva: "
                    f"{t['transicionPropiedad']} {t['transicionDuracion']}"
                )
        elif all(x == "0s" for x in duraciones):
            fallos.append(f"{quien}: sin la preferencia la transición ya está en 0 s: la comprobación sería vacía")

        if t["role"] != "group":
            fallos.append(f"{quien}: role={t['role']!r}, esperaba 'group'")
        if not t["rotulo"]:
            fallos.append(f"{quien}: aria-labelledby={t['labelledby']!r} no apunta a un elemento con texto")
        if t["paradasDeTeclado"]:
            fallos.append(f"{quien}: la tarjeta mete {t['paradasDeTeclado']} parada(s) de teclado y no es interactiva")

        if not t["nums"]:
            fallos.append(f"{quien}: no hay ningún .muni-num")
        for n in t["nums"]:
            if n["familia"] != d["mono"]:
                fallos.append(f"{quien}: .muni-num computa {n['familia']!r} y el token mono es {d['mono']!r}")
            if "tabular-nums" not in n["variante"]:
                fallos.append(f"{quien}: .muni-num sin tabular-nums (font-variant-numeric={n['variante']!r})")

        for s in t["ocultos"]:
            if s["texto"] not in ("subió", "bajó"):
                fallos.append(f"{quien}: el span oculto dice {s['texto']!r}")
            if s["display"] == "none" or s["visibility"] == "hidden":
                fallos.append(f"{quien}: el span oculto está fuera del árbol (display={s['display']}, visibility={s['visibility']})")
            if s["ancho"] > 1 or s["alto"] > 1:
                fallos.append(f"{quien}: el span oculto mide {s['ancho']}×{s['alto']} px, esperaba ≤ 1×1")

        for f in t["flechas"]:
            if round(f["ancho"]) != 12 or round(f["alto"]) != 12:
                fallos.append(f"{quien}: la flecha mide {f['ancho']}×{f['alto']} px, esperaba 12×12")
            if f["ariaHidden"] != "true":
                fallos.append(f"{quien}: la flecha no va aria-hidden")

        for x in t["textos"]:
            ratio = contraste(x["color"], t["fondo"], d["fondoDeLaPagina"])
            minimo = 3.0 if es_texto_grande(x["tamano"], x["peso"]) else 4.5
            x["ratio"] = ratio
            if ratio < minimo:
                fallos.append(f"{quien}: «{x['texto']}» a {ratio:.2f}:1 contra el fondo de la tarjeta (mínimo {minimo}:1)")

    return d


def verificar_arbol_aria(pagina, ruta: Path, medida: dict, fallos: list[str]) -> list[str]:
    """El nombre del grupo y el sentido, tal como los expone Chromium."""
    instantaneas = []
    for i, t in enumerate(medida["tarjetas"]):
        snap = pagina.locator(".muni-stat").nth(i).aria_snapshot()
        instantaneas.append(snap.strip())
        quien = f"{ruta.name} tarjeta {i}"
        if f'group "{t["rotulo"]}"' not in snap:
            fallos.append(f"{quien}: el árbol ARIA no expone group \"{t['rotulo']}\": {snap.strip()!r}")
        sentidos = [s["texto"] for s in t["ocultos"]]
        for sentido in ("subió", "bajó"):
            if (sentido in sentidos) != (sentido in snap):
                fallos.append(f"{quien}: «{sentido}» {'falta en' if sentido in sentidos else 'sobra en'} el árbol ARIA: {snap.strip()!r}")
    return instantaneas


def main(argv: list[str]) -> int:
    carpeta = Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
              "`vendor/bin/pest --filter=CifraComparada`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []
    with sync_playwright() as pw:
        navegador = pw.chromium.launch()
        for ruta in paginas:
            for movimiento in MOVIMIENTOS:
                contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion=movimiento)
                pagina = contexto.new_page()
                pagina.goto(ruta.as_uri())
                medida = verificar_pagina(pagina, ruta, movimiento, fallos)
                if movimiento == "reduce":
                    arbol = verificar_arbol_aria(pagina, ruta, medida, fallos)
                    peor = min((x["ratio"] for t in medida["tarjetas"] for x in t["textos"]), default=0.0)
                    resumen.append(
                        f"{ruta.name:<20} tarjetas={len(medida['tarjetas'])} "
                        f"transición(reduce)={medida['tarjetas'][0]['transicionPropiedad']} "
                        f"{medida['tarjetas'][0]['transicionDuracion']}  mono={medida['mono']!r}  "
                        f"peor contraste={peor:.2f}:1"
                    )
                    resumen.extend("    " + linea for linea in arbol)
                else:
                    resumen.append(
                        f"{ruta.name:<20} transición(no-preference)="
                        f"{medida['tarjetas'][0]['transicionPropiedad']} {medida['tarjetas'][0]['transicionDuracion']}"
                    )
                contexto.close()
        navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa: {len(paginas)} páginas × {len(MOVIMIENTOS)} preferencias de movimiento.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
