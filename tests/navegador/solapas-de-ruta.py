#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::tabs-ruta>` sobre el banco `build/solapas-de-ruta/`.

    .venv-a11y/bin/python tests/navegador/solapas-de-ruta.py [build/solapas-de-ruta]

La corre `tests/SolapasDeRutaTest.php` después de generar el banco (y se salta si
no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest leen
el TEXTO del CSS del componente —que la barra de la solapa activa exista, que el
foco declare su outline, que la fila envuelva— y eso ya engañó una vez en `stat`:
la regla pasó el test de texto y en el navegador el defecto seguía vivo. Acá se
mide, en Chromium Y Firefox y en las CUATRO páginas (dos paletas × dos temas:
dentro del panel solo se carga `muni-ui-filament.css`, DESIGN §7):

  · el recorrido REAL de Tab con `document.activeElement`: cuatro paradas, en el
    orden del DOM, una por solapa, y ninguna parada extra (nada de roving
    tabindex, que dejaría tres solapas fuera del recorrido);
  · Enter sigue el enlace de la solapa enfocada (activación nativa del `<a>`);
  · con el foco puesto por teclado, el outline computa 3 px sólidos y su color
    llega a 3:1 contra el fondo (WCAG 2.2 AA 2.4.7 y 1.4.11). La box-shadow del
    anillo NO cuenta: dentro de Filament se computa transparente;
  · la solapa actual se distingue sin color: `aria-current="page"` en el árbol,
    peso tipográfico mayor que las demás y la barra del `::after` con alto real y
    fondo opaco;
  · todo texto de la solapa pasa 4,5:1 contra el fondo efectivo, y el contador
    pasa contra el suyo, en las cuatro páginas;
  · con `prefers-reduced-motion: reduce` la transición computa 0 s, y sin la
    preferencia existe (si no, la comprobación sería vacía);
  · el área táctil de cada solapa llega a 24 px de alto (2.5.8);
  · a 390 px y a 320 px de ancho las solapas ENVUELVEN y el documento NO se
    desplaza en horizontal (1.4.10), que es la razón por la que la fila usa
    flex-wrap y no overflow-x como el tablist de `tabs`;
  · el contador de la solapa ACTIVA —la única regla que invierte los colores—
    existe en el banco y pasa su propio contraste;
  · el texto solo para lector del contador mide 1 px sin `display:none`.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "solapas-de-ruta"
MOVIMIENTOS = ("reduce", "no-preference")
NAVEGADORES = ("chromium", "firefox")
# 320 px es el ancho que el comentario CSS del componente usa para justificar
# haberse separado de `tabs` (envolver en vez de desplazarse en horizontal):
# si no se mide ahí, la justificación no tiene red.
ANCHOS = (("escritorio", 1440, 900), ("telefono", 390, 780), ("estrecho", 320, 780))

SONDA = r"""
() => {
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };

  // El primer ancestro con fondo opaco. La solapa es transparente salvo en
  // :hover, así que el fondo efectivo lo pone la sección o el <body>.
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  const nav = document.querySelector('nav.muni-tabr');
  const solapas = [...document.querySelectorAll('.muni-tabr__solapa')].map(a => {
    const c = getComputedStyle(a);
    const barra = getComputedStyle(a, '::after');
    const badge = a.querySelector('.muni-tabr__badge');
    const cb = badge ? getComputedStyle(badge) : null;
    return {
      texto: (a.querySelector('.muni-tabr__rotulo') || a).textContent.trim(),
      href: a.getAttribute('href'),
      current: a.getAttribute('aria-current'),
      activa: a.classList.contains('muni-tabr__solapa--on'),
      tabindex: a.getAttribute('tabindex'),
      rol: a.getAttribute('role'),
      color: c.color,
      fondo: fondoDe(a),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
      transicion: c.transitionDuration,
      ...caja(a),
      barra: { content: barra.content, alto: barra.height, fondo: barra.backgroundColor },
      badge: badge ? { texto: badge.textContent.trim(), color: cb.color, fondo: cb.backgroundColor, tamano: parseFloat(cb.fontSize), oculto: badge.getAttribute('aria-hidden') } : null,
    };
  });

  const sr = document.querySelector('.muni-tabr__sr');
  const csr = sr ? getComputedStyle(sr) : null;

  return {
    nav: nav ? { label: nav.getAttribute('aria-label'), rol: nav.getAttribute('role'), etiqueta: nav.tagName } : null,
    lista: !!document.querySelector('ul.muni-tabr__lista[role="list"]'),
    roles: [...document.querySelectorAll('[role="tablist"],[role="tab"],[role="tabpanel"],[aria-selected]')].length,
    enfocables: [...document.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')]
      .filter(e => e.getAttribute('tabindex') !== '-1').length,
    solapas,
    // Filas distintas = la fila envolvió. Se mide por la coordenada Y de cada solapa.
    filas: [...new Set([...document.querySelectorAll('.muni-tabr__solapa')].map(a => Math.round(a.getBoundingClientRect().top)))].length,
    desbordaX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    sr: sr ? { ...caja(sr), display: csr.display, visibility: csr.visibility, texto: sr.textContent.trim() } : null,
  };
}
"""

FOCO = r"""
() => {
  const a = document.activeElement;
  if (!a) return null;
  const c = getComputedStyle(a);
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };
  return {
    etiqueta: a.tagName,
    texto: a.textContent.trim(),
    href: a.getAttribute('href'),
    clase: a.getAttribute('class'),
    outline: { ancho: c.outlineWidth, estilo: c.outlineStyle, color: c.outlineColor },
    fondo: fondoDe(a),
  };
}
"""


def canal(v: float) -> float:
    v = v / 255
    return v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4


def luminancia(rgb: tuple[float, float, float]) -> float:
    r, g, b = (canal(c) for c in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def a_rgb(valor: str) -> tuple[float, float, float] | None:
    m = re.match(r"rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)", str(valor or ""))
    return (float(m.group(1)), float(m.group(2)), float(m.group(3))) if m else None


def contraste(frente: str, fondo: str) -> float | None:
    f, b = a_rgb(frente), a_rgb(fondo)
    if f is None or b is None:
        return None
    l1, l2 = luminancia(f), luminancia(b)
    claro, oscuro = max(l1, l2), min(l1, l2)
    return (claro + 0.05) / (oscuro + 0.05)


def minimo(tamano: float, peso: int) -> float:
    """4,5:1, o 3:1 si el texto es grande (≥24px, o ≥18.66px en negrita)."""
    grande = tamano >= 24 or (tamano >= 18.66 and peso >= 700)
    return 3.0 if grande else 4.5


def verificar_estructura(d: dict, donde: str, fallos: list[str]) -> None:
    if not d["nav"] or d["nav"]["etiqueta"] != "NAV":
        fallos.append(f"{donde}: no hay landmark <nav>.")
        return
    if not (d["nav"]["label"] or "").strip():
        fallos.append(f"{donde}: el <nav> no tiene nombre accesible.")
    if not d["lista"]:
        fallos.append(f"{donde}: la lista no conserva role=\"list\" (Safari la pierde con list-style:none).")
    if d["roles"]:
        fallos.append(f"{donde}: hay {d['roles']} nodo(s) con roles del patrón tabs; esto es navegación.")
    if len(d["solapas"]) != 4:
        fallos.append(f"{donde}: se esperaban 4 solapas y hay {len(d['solapas'])}.")
    if d["enfocables"] != 4:
        fallos.append(
            f"{donde}: el documento tiene {d['enfocables']} paradas de teclado y las solapas son 4: "
            "el componente mete paradas de más o saca alguna del recorrido."
        )

    activas = [s for s in d["solapas"] if s["current"] == "page"]
    if len(activas) != 1:
        fallos.append(f"{donde}: hay {len(activas)} solapa(s) con aria-current=\"page\"; tiene que haber una.")
    elif activas[0]["texto"] != "Documentos":
        fallos.append(f"{donde}: el aria-current cayó en «{activas[0]['texto']}» y no en «Documentos».")

    for s in d["solapas"]:
        if s["tabindex"] is not None:
            fallos.append(f"{donde} «{s['texto']}»: lleva tabindex={s['tabindex']}; son enlaces, sin roving tabindex.")
        if s["alto"] < 24:
            fallos.append(f"{donde} «{s['texto']}»: el área táctil mide {s['alto']:.1f}px de alto (<24, WCAG 2.5.8).")

    if d["sr"] is None:
        fallos.append(f"{donde}: no está el texto solo para lector del contador.")
    else:
        if d["sr"]["display"] == "none" or d["sr"]["visibility"] == "hidden":
            fallos.append(f"{donde}: el texto del contador se oculta sacándolo del árbol de accesibilidad.")
        if d["sr"]["ancho"] > 2 or d["sr"]["alto"] > 2:
            fallos.append(f"{donde}: el texto solo para lector ocupa {d['sr']['ancho']:.0f}×{d['sr']['alto']:.0f}px.")
        if "3 solicitudes abiertas" not in d["sr"]["texto"]:
            fallos.append(f"{donde}: el texto del contador no dice qué son tres: {d['sr']['texto']!r}")


def verificar_estado_sin_color(d: dict, donde: str, fallos: list[str]) -> None:
    activa = next((s for s in d["solapas"] if s["activa"]), None)
    inactivas = [s for s in d["solapas"] if not s["activa"]]
    if activa is None or not inactivas:
        return

    if activa["peso"] <= inactivas[0]["peso"]:
        fallos.append(
            f"{donde}: la solapa activa pesa {activa['peso']} y las demás {inactivas[0]['peso']}: "
            "sin diferencia de peso el estado queda colgando del color."
        )

    alto = float(re.sub(r"[^\d.]", "", activa["barra"]["alto"]) or 0)
    if alto < 1.5:
        fallos.append(f"{donde}: la barra de la solapa activa mide {alto}px de alto; no se ve.")
    if a_rgb(activa["barra"]["fondo"]) is None or "rgba(0, 0, 0, 0)" in activa["barra"]["fondo"]:
        fallos.append(f"{donde}: la barra de la solapa activa es transparente: {activa['barra']['fondo']!r}")
    else:
        r = contraste(activa["barra"]["fondo"], activa["fondo"])
        if r is not None and r < 3.0:
            fallos.append(f"{donde}: la barra de la solapa activa da {r:.2f}:1 contra el fondo (<3, WCAG 1.4.11).")

    for s in inactivas:
        if s["barra"]["content"] not in ("none", "normal", ""):
            fallos.append(f"{donde} «{s['texto']}»: una solapa inactiva dibuja la barra del activo.")

    # La solapa activa es la ÚNICA que invierte los colores del contador (fondo
    # --muni-accent, texto --muni-on-accent). Si el banco deja de traer contador
    # en la activa, esa regla se queda sin medir y nadie se entera: se exige aquí
    # para que `verificar_contraste` tenga siempre ese par que comparar.
    if activa["badge"] is None:
        fallos.append(
            f"{donde}: la solapa activa no trae contador, así que la única regla que invierte "
            "los colores (--muni-on-accent sobre --muni-accent) no la mide nadie."
        )
    elif a_rgb(activa["badge"]["fondo"]) is None or "rgba(0, 0, 0, 0)" in activa["badge"]["fondo"]:
        fallos.append(f"{donde}: el contador de la solapa activa no pinta su fondo propio: {activa['badge']['fondo']!r}")


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 99.0
    for s in d["solapas"]:
        r = contraste(s["color"], s["fondo"])
        if r is None:
            fallos.append(f"{donde} «{s['texto']}»: no se pudo leer el color.")
            continue
        peor = min(peor, r)
        exigido = minimo(s["tamano"], s["peso"])
        if r < exigido:
            fallos.append(
                f"{donde} «{s['texto']}»: {r:.2f}:1 contra su fondo (exige {exigido}:1) "
                f"[{s['color']} sobre {s['fondo']}]"
            )
        if s["badge"]:
            rb = contraste(s["badge"]["color"], s["badge"]["fondo"])
            if rb is not None:
                peor = min(peor, rb)
                if rb < minimo(s["badge"]["tamano"], 600):
                    fallos.append(
                        f"{donde} contador de «{s['texto']}»: {rb:.2f}:1 contra su propio fondo "
                        f"[{s['badge']['color']} sobre {s['badge']['fondo']}]"
                    )
    return peor


def verificar_movimiento(d: dict, donde: str, movimiento: str, fallos: list[str]) -> str:
    duraciones = {s["transicion"] for s in d["solapas"]}
    texto = ", ".join(sorted(duraciones))
    vivas = [t for t in duraciones if re.search(r"(?<![\d.])(?!0s)[\d.]+m?s", t)]
    if movimiento == "reduce" and vivas:
        fallos.append(f"{donde}: con movimiento reducido la transición sigue en {texto}; tiene que computar 0 s.")
    if movimiento == "no-preference" and not vivas:
        fallos.append(f"{donde}: sin la preferencia no hay transición ninguna ({texto}): la comprobación de «reduce» sería vacía.")
    return texto


def verificar_teclado(pagina, donde: str, fallos: list[str]) -> str:
    """Tab desde el principio del documento: cuatro paradas, en orden, y Enter activa."""
    pagina.evaluate("() => { document.activeElement && document.activeElement.blur(); window.scrollTo(0, 0); }")
    pagina.keyboard.press("Tab")

    esperados = ["Solicitudes", "Documentos", "Historial de contactos", "Domicilios"]
    visto = []

    for i, esperado in enumerate(esperados):
        foco = pagina.evaluate(FOCO)
        if not foco or foco["etiqueta"] != "A" or "muni-tabr__solapa" not in (foco["clase"] or ""):
            fallos.append(f"{donde}: la parada #{i + 1} de Tab no cayó en una solapa: {foco!r}")
            return " → ".join(visto)

        etiqueta = foco["texto"].split("\n")[0].strip()
        visto.append(etiqueta)
        if esperado not in foco["texto"]:
            fallos.append(f"{donde}: la parada #{i + 1} de Tab es «{etiqueta}» y se esperaba «{esperado}».")

        ancho = float(re.sub(r"[^\d.]", "", foco["outline"]["ancho"]) or 0)
        if ancho < 3 or foco["outline"]["estilo"] in ("none", "hidden"):
            fallos.append(
                f"{donde} «{etiqueta}»: el foco de teclado no dibuja outline "
                f"({foco['outline']['estilo']} {foco['outline']['ancho']}). La box-shadow del anillo no cuenta."
            )
        else:
            r = contraste(foco["outline"]["color"], foco["fondo"])
            if r is not None and r < 3.0:
                fallos.append(f"{donde} «{etiqueta}»: el outline da {r:.2f}:1 contra el fondo (<3, WCAG 1.4.11).")

        pagina.keyboard.press("Tab")

    # Que no haya paradas de más se comprueba contando los enfocables del DOM y
    # no con un quinto Tab: en Firefox headless el foco no sale del último enlace
    # de la página, así que ese quinto Tab mediría el navegador, no el componente.

    # Enter sobre la solapa enfocada con teclado sigue el enlace: el <a> nativo.
    pagina.evaluate("() => document.querySelector('.muni-tabr__solapa[href=\"#domicilios\"]').focus()")
    pagina.keyboard.press("Enter")
    if not pagina.url.endswith("#domicilios"):
        fallos.append(f"{donde}: Enter sobre la solapa enfocada no siguió el enlace (URL {pagina.url}).")

    return " → ".join(visto)


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
              "`vendor/bin/pest --filter=SolapasDeRuta`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()
            for ruta in paginas:
                for movimiento in MOVIMIENTOS:
                    for ancho_nombre, ancho, alto in ANCHOS:
                        contexto = navegador.new_context(
                            viewport={"width": ancho, "height": alto},
                            reduced_motion=movimiento,
                        )
                        pagina = contexto.new_page()
                        pagina.goto(ruta.as_uri())

                        donde = f"{nombre} {ruta.name} [{movimiento}/{ancho_nombre}]"
                        d = pagina.evaluate(SONDA)

                        verificar_estructura(d, donde, fallos)
                        verificar_estado_sin_color(d, donde, fallos)
                        peor = verificar_contraste(d, donde, fallos)
                        transicion = verificar_movimiento(d, donde, movimiento, fallos)

                        if d["desbordaX"]:
                            fallos.append(f"{donde}: el documento se desplaza en horizontal (WCAG 1.4.10).")
                        if ancho <= 390 and d["filas"] < 2:
                            fallos.append(f"{donde}: a {ancho}px las cuatro solapas siguen en una sola fila.")

                        teclado = verificar_teclado(pagina, donde, fallos)

                        resumen.append(
                            f"{nombre:<9}{ruta.name:<18}[{movimiento:<13} {ancho_nombre:<10}] "
                            f"filas={d['filas']} transición={transicion} peor contraste={peor:.2f}:1  Tab: {teclado}"
                        )
                        contexto.close()
            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(
        f"\nTodo pasa: {len(NAVEGADORES)} navegadores × {len(paginas)} páginas × "
        f"{len(MOVIMIENTOS)} preferencias de movimiento × {len(ANCHOS)} anchos."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
