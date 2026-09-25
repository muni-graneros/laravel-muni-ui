#!/usr/bin/env python3
"""Verificación en navegador de las migas de ruta sobre el banco `build/migas-de-ruta/`.

    .venv-a11y/bin/python tests/navegador/migas-de-ruta.py [build/migas-de-ruta]

La corre `tests/MigasDeRutaTest.php` después de generar el banco (y se salta si
no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest leen
el TEXTO del CSS del componente —que el enlace declare `text-decoration:underline`,
que la miga declare `overflow-wrap:anywhere`, que el foco declare su outline, que
haya un bloque de impresión— y eso ya engañó una vez en `stat`: la regla pasó el
test de texto y en el navegador el defecto seguía vivo. Declarar una regla no es
que la regla GANE: basta una especificidad mayor, un `!important` de la hoja del
panel o un token vacío para que lo computado diga otra cosa.

Se mide en Chromium Y Firefox y en las CUATRO páginas (dos temas × dentro y fuera
del panel: ahí solo se carga `muni-ui-filament.css`, DESIGN §7):

  · el subrayado del enlace COMPUTA `underline` (WCAG 1.4.1: si el único
    diferenciador frente al texto plano fuera el color, sería un fallo);
  · la miga actual no es enlace, lleva `aria-current="page"` y pesa más;
  · el recorrido REAL de Tab con `document.activeElement`: para en cada enlace de
    miga y NUNCA en la miga actual, y con el foco puesto por teclado el outline
    computa 3 px sólidos y llega a 3:1 contra el fondo efectivo (2.4.7 y 1.4.11).
    La box-shadow del anillo NO cuenta: dentro de Filament se computa transparente;
  · todo texto de miga pasa 4,5:1 contra su fondo efectivo, en las cuatro páginas;
  · el objetivo mide 24 px de alto (2.5.8);
  · a 390 px, con un folio largo SIN espacios, el documento no se desplaza en
    horizontal y la miga se parte en varias líneas (1.4.10). Es el caso que
    desbordaba: un ítem flex no baja de su palabra más larga sin `min-width:0`;
  · con `prefers-reduced-motion: reduce` la transición computa 0 s, y sin la
    preferencia existe (si no, la comprobación sería vacía). Dentro del panel el
    token no basta: `muni-ui-filament.css` no baja `--muni-dur`;
  · emulando la impresión, el landmark entero computa `display:none`;
  · en la cabecera de página, la ruta se pinta ENCIMA del único `<h1>`.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "migas-de-ruta"
MOVIMIENTOS = ("reduce", "no-preference")
NAVEGADORES = ("chromium", "firefox")
ANCHOS = (("escritorio", 1440, 900), ("telefono", 390, 780))

SONDA = r"""
() => {
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height, arriba: r.top, derecha: r.right }; };

  // El primer ancestro con fondo opaco: la miga es transparente salvo en :hover.
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  const leerMiga = el => {
    const c = getComputedStyle(el);
    return {
      texto: el.textContent.trim(),
      etiqueta: el.tagName,
      href: el.getAttribute('href'),
      current: el.getAttribute('aria-current'),
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
      subrayado: c.textDecorationLine,
      transicion: c.transitionDuration,
      ...caja(el),
    };
  };

  const leerNav = nav => nav ? {
    etiqueta: nav.tagName,
    label: nav.getAttribute('aria-label'),
    display: getComputedStyle(nav).display,
    lista: !!nav.querySelector('ol.muni-crumbs__list[role="list"]'),
    items: nav.querySelectorAll('li.muni-crumbs__item').length,
    separadores: nav.querySelectorAll('.muni-crumbs__sep').length,
    migas: [...nav.querySelectorAll('.muni-crumb')].map(leerMiga),
    ...caja(nav),
  } : null;

  const h1 = document.querySelector('h1');
  const cabecera = document.querySelector('.muni-page-header__migas nav.muni-crumbs');

  return {
    ruta: leerNav(cabecera),
    larga: leerNav(document.querySelector('nav#ruta-larga')),
    h1: document.querySelectorAll('h1').length,
    h1Arriba: h1 ? h1.getBoundingClientRect().top : null,
    enfocables: [...document.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')]
      .filter(e => e.getAttribute('tabindex') !== '-1').length,
    desbordaX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    anchoDoc: document.documentElement.clientWidth,
    desborde: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  };
}
"""

DISPLAY_MIGAS = r"""
() => [...document.querySelectorAll('nav.muni-crumbs')].map(n => getComputedStyle(n).display)
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
    current: a.getAttribute('aria-current'),
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
    ruta = d["ruta"]
    if not ruta or ruta["etiqueta"] != "NAV":
        fallos.append(f"{donde}: la cabecera de página no rinde el landmark <nav> de la ruta en su slot `migas`.")
        return
    if not (ruta["label"] or "").strip():
        fallos.append(f"{donde}: el <nav> de la ruta no tiene nombre accesible.")
    if not ruta["lista"]:
        fallos.append(f"{donde}: la ruta no conserva <ol role=\"list\"> (Safari la pierde con list-style:none).")
    if ruta["items"] != 3:
        fallos.append(f"{donde}: se esperaban 3 migas y hay {ruta['items']}.")
    if ruta["separadores"] != 2:
        fallos.append(f"{donde}: hay {ruta['separadores']} separador(es) para 3 migas; tienen que ser 2, ninguno delante de la primera.")

    if d["h1"] != 1:
        fallos.append(f"{donde}: la banda emite {d['h1']} <h1>; la convención de la cabecera es exactamente uno.")
    if d["h1Arriba"] is not None and ruta["arriba"] >= d["h1Arriba"]:
        fallos.append(
            f"{donde}: la ruta se pinta en y={ruta['arriba']:.0f} y el <h1> en y={d['h1Arriba']:.0f}: "
            "las migas tienen que ir ENCIMA del título."
        )

    enlaces = [m for m in ruta["migas"] if m["etiqueta"] == "A"]
    actuales = [m for m in ruta["migas"] if m["current"] == "page"]

    if len(enlaces) != 2:
        fallos.append(f"{donde}: hay {len(enlaces)} enlace(s) de miga y se esperaban 2 (la actual nunca es enlace).")
    if len(actuales) != 1:
        fallos.append(f"{donde}: hay {len(actuales)} miga(s) con aria-current=\"page\"; tiene que haber una.")
    elif actuales[0]["etiqueta"] == "A":
        fallos.append(f"{donde}: la miga actual se emitió como enlace: es la página en la que ya estás.")

    for m in ruta["migas"]:
        if m["alto"] < 24:
            fallos.append(f"{donde} «{m['texto']}»: el objetivo mide {m['alto']:.1f}px de alto (<24, WCAG 2.5.8).")

    # 1.4.1: el enlace no puede distinguirse SOLO por el color.
    for m in enlaces:
        if "underline" not in (m["subrayado"] or ""):
            fallos.append(
                f"{donde} «{m['texto']}»: el enlace de la miga computa text-decoration-line={m['subrayado']!r}: "
                "sin subrayado su único diferenciador vuelve a ser el color (WCAG 1.4.1)."
            )
    if actuales and len(enlaces) >= 1:
        if actuales[0]["peso"] <= enlaces[0]["peso"]:
            fallos.append(
                f"{donde}: la miga actual pesa {actuales[0]['peso']} y las enlazadas {enlaces[0]['peso']}: "
                "sin diferencia de peso el estado cuelga del color."
            )


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 99.0
    for nav in (d["ruta"], d["larga"]):
        if not nav:
            continue
        for m in nav["migas"]:
            r = contraste(m["color"], m["fondo"])
            if r is None:
                fallos.append(f"{donde} «{m['texto']}»: no se pudo leer el color.")
                continue
            peor = min(peor, r)
            exigido = minimo(m["tamano"], m["peso"])
            if r < exigido:
                fallos.append(
                    f"{donde} «{m['texto'][:32]}»: {r:.2f}:1 contra su fondo (exige {exigido}:1) "
                    f"[{m['color']} sobre {m['fondo']}]"
                )
    return peor


def verificar_desborde(d: dict, donde: str, ancho_nombre: str, fallos: list[str]) -> None:
    if d["desbordaX"]:
        fallos.append(
            f"{donde}: el documento se desplaza {d['desborde']}px en horizontal (WCAG 1.4.10). "
            "El folio largo sin espacios es el caso que rompía."
        )

    larga = d["larga"]
    if not larga:
        fallos.append(f"{donde}: falta la ruta con el folio largo del banco.")
        return

    folio = max(larga["migas"], key=lambda m: len(m["texto"]))
    if folio["derecha"] > d["anchoDoc"] + 1:
        fallos.append(
            f"{donde}: el folio largo llega a x={folio['derecha']:.0f} con el documento en {d['anchoDoc']}px: "
            "la miga no se parte y empuja la página."
        )
    if ancho_nombre == "telefono" and folio["alto"] < folio["tamano"] * 2:
        fallos.append(
            f"{donde}: a 390px el folio largo sigue en una sola línea ({folio['alto']:.0f}px de alto): "
            "sin overflow-wrap:anywhere no se parte."
        )


def verificar_movimiento(d: dict, donde: str, movimiento: str, fallos: list[str]) -> str:
    enlaces = [m for m in (d["ruta"] or {"migas": []})["migas"] if m["etiqueta"] == "A"]
    duraciones = {m["transicion"] for m in enlaces}
    texto = ", ".join(sorted(duraciones)) or "—"
    vivas = [t for t in duraciones if re.search(r"(?<![\d.])(?!0s)[\d.]+m?s", t)]
    if movimiento == "reduce" and vivas:
        fallos.append(
            f"{donde}: con movimiento reducido la transición de la miga sigue en {texto}; tiene que computar 0 s. "
            "(Dentro del panel el token no alcanza: muni-ui-filament.css no baja --muni-dur.)"
        )
    if movimiento == "no-preference" and not vivas:
        fallos.append(f"{donde}: sin la preferencia no hay transición ninguna ({texto}): la comprobación de «reduce» sería vacía.")
    return texto


def verificar_impresion(pagina, donde: str, fallos: list[str]) -> str:
    """En papel la ruta no lleva a ninguna parte: el landmark entero desaparece."""
    antes = pagina.evaluate(DISPLAY_MIGAS)
    if any(v == "none" for v in antes):
        fallos.append(f"{donde}: en pantalla ya hay una ruta con display:none ({antes}).")

    try:
        pagina.emulate_media(media="print")
    except Exception as e:  # noqa: BLE001 — el navegador dirá por qué
        fallos.append(f"{donde}: no se pudo emular la impresión ({e}).")
        return "sin emulación"

    despues = pagina.evaluate(DISPLAY_MIGAS)
    pagina.emulate_media(media="screen")

    if not despues or any(v != "none" for v in despues):
        fallos.append(f"{donde}: emulando la impresión las migas siguen ocupando la hoja (display={despues}).")
    return ",".join(despues)


def verificar_teclado(pagina, donde: str, fallos: list[str]) -> str:
    """Tab desde el principio: para en cada enlace de miga y nunca en la actual."""
    pagina.evaluate("() => { document.activeElement && document.activeElement.blur(); window.scrollTo(0, 0); }")
    pagina.keyboard.press("Tab")

    esperados = ["Inicio", "Credenciales"]
    visto = []

    for i, esperado in enumerate(esperados):
        foco = pagina.evaluate(FOCO)
        if not foco or foco["etiqueta"] != "A" or "muni-crumb" not in (foco["clase"] or ""):
            fallos.append(f"{donde}: la parada #{i + 1} de Tab no cayó en un enlace de miga: {foco!r}")
            return " → ".join(visto)

        etiqueta = foco["texto"].split("\n")[0].strip()
        visto.append(etiqueta)
        if esperado not in foco["texto"]:
            fallos.append(f"{donde}: la parada #{i + 1} de Tab es «{etiqueta}» y se esperaba «{esperado}».")
        if foco["current"] == "page":
            fallos.append(f"{donde}: el recorrido paró en la miga actual; no es un destino al que se pueda ir.")

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

    # Enter sobre la miga enfocada sigue el enlace: el <a> nativo, sin JS.
    pagina.evaluate("() => document.querySelector('a.muni-crumb[href=\"#credenciales\"]').focus()")
    pagina.keyboard.press("Enter")
    if not pagina.url.endswith("#credenciales"):
        fallos.append(f"{donde}: Enter sobre la miga enfocada no siguió el enlace (URL {pagina.url}).")

    return " → ".join(visto)


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
              "`vendor/bin/pest --filter=MigasDeRuta`.")
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
                        peor = verificar_contraste(d, donde, fallos)
                        verificar_desborde(d, donde, ancho_nombre, fallos)
                        transicion = verificar_movimiento(d, donde, movimiento, fallos)
                        impresion = verificar_impresion(pagina, donde, fallos)
                        teclado = verificar_teclado(pagina, donde, fallos)

                        resumen.append(
                            f"{nombre:<9}{ruta.name:<18}[{movimiento:<13} {ancho_nombre:<10}] "
                            f"desborde={d['desborde']}px transición={transicion} papel={impresion} "
                            f"peor contraste={peor:.2f}:1  Tab: {teclado}"
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
