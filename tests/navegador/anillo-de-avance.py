#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::ring>` sobre el banco `build/anillo-de-avance/`.

    .venv-a11y/bin/python tests/navegador/anillo-de-avance.py [build/anillo-de-avance]

La corre `tests/AnilloDeAvanceTest.php` después de generar el banco (y se salta si
no está el entorno de la reja). Las pruebas de Pest leen el TEXTO del componente;
esto mide, en Chromium Y Firefox, en las CUATRO páginas (dos paletas × dos temas:
dentro del panel solo se carga `muni-ui-filament.css`, DESIGN §7), con y sin
`prefers-reduced-motion`:

  · cada anillo es UN `progressbar` con nombre, `aria-valuenow` en 0-100,
    `aria-valuemax` 100 fijo y `aria-valuetext` «N %»; en el árbol ARIA el valor
    y el rótulo NO se repiten como texto suelto (la doble lectura que el juez
    señaló: el número vive dentro del contenedor con el rol);
  · la transición del arco computa 600 ms sin la preferencia y 0 s con ella,
    TAMBIÉN en el panel. Y no solo el estilo: se cambia el avance en vivo y se
    cuenta con `getAnimations()` que el arco anima (sin preferencia) o salta
    (con ella). Así se ve que no quedó en los 160 ms de --muni-dur;
  · el anillo no mete paradas de teclado (recorrido real de Tab);
  · la cifra y el rótulo pasan su contraste contra la superficie (4,5:1; 3:1 si
    el texto es grande), y el arco se mide contra la pista y la superficie
    (1.4.11 pide 3:1) y se INFORMA;
  · a 390 px el documento no se desplaza en horizontal;
  · la FAMILIA de la barrida (progress, chart-donut, chart-bar): cada transición
    computa 600 ms sin la preferencia y 0 s con ella, también en el panel, y al
    cambiar el avance en vivo anima 600 ms o salta; el svg del donut no aparece
    en el árbol ARIA y la leyenda sí, como texto.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "anillo-de-avance"
MOVIMIENTOS = ("reduce", "no-preference")
NAVEGADORES = ("chromium", "firefox")

SONDA = r"""
() => {
  const superficie = getComputedStyle(document.querySelector('.banco-grid')).backgroundColor;
  const anillos = [...document.querySelectorAll('.muni-ring')].map(raiz => {
    const barra = raiz.querySelector('[role="progressbar"]');
    const arco = raiz.querySelector('.muni-ring__arco');
    const pista = raiz.querySelector('circle:not(.muni-ring__arco)');
    const cifra = raiz.querySelector('[role="progressbar"] > div > span');
    const rotulo = raiz.querySelector(':scope > span');
    const ca = getComputedStyle(arco);
    const texto = el => el ? { color: getComputedStyle(el).color, tamano: parseFloat(getComputedStyle(el).fontSize),
      peso: parseInt(getComputedStyle(el).fontWeight, 10) || 400, texto: el.textContent.trim(),
      oculto: el.closest('[aria-hidden="true"]') !== null } : null;
    return {
      barras: raiz.querySelectorAll('[role="progressbar"]').length,
      nombre: barra.getAttribute('aria-label'),
      now: barra.getAttribute('aria-valuenow'), min: barra.getAttribute('aria-valuemin'),
      max: barra.getAttribute('aria-valuemax'), valuetext: barra.getAttribute('aria-valuetext'),
      svgOculto: raiz.querySelector('svg').getAttribute('aria-hidden'),
      duracion: ca.transitionDuration, propiedad: ca.transitionProperty,
      arco: ca.stroke, pista: getComputedStyle(pista).stroke,
      cifra: texto(cifra), rotulo: texto(rotulo),
    };
  });
  const ids = [...document.querySelectorAll('[id]')].map(e => e.id);
  return { superficie, fondo: getComputedStyle(document.body).backgroundColor, anillos,
           idsRepetidos: ids.filter((v, i, a) => a.indexOf(v) !== i) };
}
"""

# Cambia el avance de cada arco a 0 y cuenta las animaciones vivas un instante después.
CAMBIO = r"""
async () => {
  const arcos = [...document.querySelectorAll('.muni-ring__arco')];
  arcos.forEach(a => a.getBoundingClientRect());
  arcos.forEach(a => a.setAttribute('stroke-dashoffset', '0'));
  await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
  return arcos.map(a => a.getAnimations().map(x => ({
    nombre: x.transitionProperty || x.animationName || '',
    duracion: x.effect && x.effect.getComputedTiming ? x.effect.getComputedTiming().duration : null,
  })));
}
"""

# La familia: duración computada de cada elemento que anima, y cambio en vivo.
FAMILIA = r"""
async () => {
  const sel = { progress: '.muni-progress__relleno', donut: '.muni-donut__arco', bar: '.muni-chartbar__bar' };
  const estilos = {};
  for (const [k, s] of Object.entries(sel)) {
    estilos[k] = [...document.querySelectorAll(s)].map(e => getComputedStyle(e).transitionDuration);
  }
  const cambios = {
    progress: e => { e.style.width = '10%'; },
    donut: e => { e.setAttribute('stroke-dasharray', '1 262.89'); },
    bar: e => { e.style.height = '5%'; },
  };
  for (const [k, s] of Object.entries(sel)) {
    const els = [...document.querySelectorAll(s)];
    els.forEach(e => e.getBoundingClientRect());
    els.forEach(cambios[k]);
  }
  await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
  const vivas = {};
  for (const [k, s] of Object.entries(sel)) {
    vivas[k] = [...document.querySelectorAll(s)].map(e => e.getAnimations().map(a =>
      a.effect && a.effect.getComputedTiming ? a.effect.getComputedTiming().duration : null));
  }
  return { estilos, vivas };
}
"""


def _rgb(valor: str) -> tuple[float, float, float, float]:
    partes = re.findall(r"[\d.]+", valor)
    if len(partes) < 3:
        raise ValueError(f"color no reconocido: {valor!r}")
    r, g, b = (float(x) for x in partes[:3])
    a = float(partes[3]) if len(partes) > 3 else 1.0
    return r, g, b, a


def _lum(valor: str, fondo: str) -> float:
    fr, fg, fb, fa = _rgb(valor)
    br, bg, bb, _ = _rgb(fondo)
    rgb = (fr * fa + br * (1 - fa), fg * fa + bg * (1 - fa), fb * fa + bb * (1 - fa))

    def canal(c: float) -> float:
        c /= 255
        return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

    return 0.2126 * canal(rgb[0]) + 0.7152 * canal(rgb[1]) + 0.0722 * canal(rgb[2])


def contraste(a: str, b: str, fondo: str) -> float:
    la, lb = _lum(a, fondo), _lum(b, fondo)
    return (max(la, lb) + 0.05) / (min(la, lb) + 0.05)


def main(argv: list[str]) -> int:
    carpeta = Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(f"Banco incompleto en {carpeta}: genera primero `vendor/bin/pest --filter=AnilloDeAvance`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []
    with sync_playwright() as pw:
        for motor in NAVEGADORES:
            navegador = getattr(pw, motor).launch()
            for ruta in paginas:
                for movimiento in MOVIMIENTOS:
                    donde = f"{motor} {ruta.name} [{movimiento}]"
                    contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion=movimiento)
                    pagina = contexto.new_page()
                    pagina.goto(ruta.as_uri())
                    d = pagina.evaluate(SONDA)

                    if len(d["anillos"]) != 5:
                        fallos.append(f"{donde}: esperaba 5 anillos, hay {len(d['anillos'])}")
                    if d["idsRepetidos"]:
                        fallos.append(f"{donde}: ids repetidos {sorted(set(d['idsRepetidos']))}")

                    peor_texto, peor_arco = 99.0, 99.0
                    for i, a in enumerate(d["anillos"]):
                        quien = f"{donde} anillo {i} ({a['nombre']})"
                        if a["barras"] != 1:
                            fallos.append(f"{quien}: {a['barras']} progressbar, esperaba 1")
                        if not a["nombre"]:
                            fallos.append(f"{quien}: progressbar sin nombre")
                        if a["min"] != "0" or a["max"] != "100":
                            fallos.append(f"{quien}: min/max = {a['min']}/{a['max']}, esperaba 0/100")
                        if not (a["now"] or "").isdigit() or not 0 <= int(a["now"]) <= 100:
                            fallos.append(f"{quien}: aria-valuenow={a['now']!r} fuera de 0-100")
                        if a["valuetext"] != f"{a['now']} %":
                            fallos.append(f"{quien}: aria-valuetext={a['valuetext']!r}")
                        if a["svgOculto"] != "true":
                            fallos.append(f"{quien}: el svg no va aria-hidden")
                        if a["cifra"] and not a["cifra"]["oculto"]:
                            fallos.append(f"{quien}: la cifra central no está oculta al lector")

                        duraciones = [x.strip() for x in a["duracion"].split(",")]
                        if movimiento == "reduce":
                            if any(x != "0s" for x in duraciones):
                                fallos.append(f"{quien}: con movimiento reducido el arco sigue en {a['propiedad']} {a['duracion']}")
                        elif a["duracion"] != "0.6s" or "stroke-dashoffset" not in a["propiedad"]:
                            fallos.append(f"{quien}: sin preferencia la transición es {a['propiedad']} {a['duracion']}, esperaba stroke-dashoffset 0.6s")

                        for nombre, t in (("cifra", a["cifra"]), ("rótulo", a["rotulo"])):
                            if not t:
                                continue
                            ratio = contraste(t["color"], d["superficie"], d["fondo"])
                            grande = t["tamano"] >= 24 or (t["tamano"] >= 18.66 and t["peso"] >= 700)
                            minimo = 3.0 if grande else 4.5
                            peor_texto = min(peor_texto, ratio)
                            if ratio < minimo:
                                fallos.append(f"{quien}: {nombre} «{t['texto']}» a {ratio:.2f}:1 (mínimo {minimo}:1)")
                        arco = min(contraste(a["arco"], a["pista"], d["superficie"]), contraste(a["arco"], d["superficie"], d["fondo"]))
                        peor_arco = min(peor_arco, arco)

                    # El árbol ARIA: un progressbar con nombre por anillo, y sin
                    # el valor ni el rótulo repetidos como texto.
                    for i, a in enumerate(d["anillos"]):
                        snap = pagina.locator(".muni-ring").nth(i).aria_snapshot().strip()
                        quien = f"{donde} anillo {i}"
                        if f'progressbar "{a["nombre"]}"' not in snap:
                            fallos.append(f"{quien}: el árbol no expone progressbar \"{a['nombre']}\": {snap!r}")
                        lineas = [l for l in snap.splitlines() if not l.strip().startswith("- progressbar")]
                        # Solo se admite el rótulo visible cuando NO es el nombre
                        # (el consumidor pasó otro aria-label): ahí es información extra.
                        rotulo_legible = a["rotulo"] and not a["rotulo"]["oculto"]
                        permitido = f'- text: {a["rotulo"]["texto"]}' if rotulo_legible else None
                        if rotulo_legible and a["rotulo"]["texto"] == a["nombre"]:
                            fallos.append(f"{quien}: el rótulo visible es el nombre y no va aria-hidden: se oye dos veces")
                        sobra = [l for l in lineas if l.strip() and l.strip() != permitido]
                        if sobra:
                            fallos.append(f"{quien}: el árbol repite texto fuera del progressbar: {sobra!r}")
                        if movimiento == "reduce" and ruta.name == "claro.html":
                            resumen.append(f"    {motor} {snap}")

                    # Recorrido de teclado: el anillo no es interactivo.
                    pagina.locator("body").focus()
                    for _ in range(4):
                        pagina.keyboard.press("Tab")
                        dentro = pagina.evaluate("() => !!(document.activeElement && document.activeElement.closest('.muni-ring'))")
                        if dentro:
                            fallos.append(f"{donde}: Tab entra en un anillo, que no es interactivo")
                            break

                    # El donut: el svg fuera del árbol, la leyenda dentro como texto.
                    snap_donut = pagina.locator(".banco-familia > div").nth(1).aria_snapshot()
                    for texto in ("Aprobadas", "86", "En revisión", "Rechazadas", "128"):
                        if texto not in snap_donut:
                            fallos.append(f"{donde} chart-donut: «{texto}» no está en el árbol ARIA: {snap_donut!r}")
                    if re.search(r"- (img|graphics|svg)", snap_donut):
                        fallos.append(f"{donde} chart-donut: el svg aparece en el árbol ARIA: {snap_donut!r}")

                    # La familia: estilo computado y cambio en vivo.
                    fam = pagina.evaluate(FAMILIA)
                    for clave, duraciones in fam["estilos"].items():
                        if not duraciones:
                            fallos.append(f"{donde} {clave}: no hay elementos que animen en el banco")
                        esperado = "0s" if movimiento == "reduce" else "0.6s"
                        if any(x != esperado for x in duraciones):
                            fallos.append(f"{donde} {clave}: transición {duraciones}, esperaba {esperado}")
                    for clave, por_elemento in fam["vivas"].items():
                        for anims in por_elemento:
                            if movimiento == "reduce" and anims:
                                fallos.append(f"{donde} {clave}: con movimiento reducido anima igual: {anims}")
                            if movimiento == "no-preference" and (not anims or any(x != 600 for x in anims)):
                                fallos.append(f"{donde} {clave}: al cambiar el avance anima {anims}, esperaba 600 ms")
                    n_familia = sum(len(x) for v in fam["vivas"].values() for x in v)

                    # Cambio de avance en vivo.
                    vivas = pagina.evaluate(CAMBIO)
                    for i, anims in enumerate(vivas):
                        quien = f"{donde} anillo {i}"
                        if movimiento == "reduce" and anims:
                            fallos.append(f"{quien}: con movimiento reducido el arco anima igual: {anims}")
                        if movimiento == "no-preference":
                            if not anims:
                                fallos.append(f"{quien}: al cambiar el avance el arco no anima (salta)")
                            elif any(x["duracion"] != 600 for x in anims):
                                fallos.append(f"{quien}: el arco anima con {anims}, esperaba 600 ms")
                    n_vivas = sum(len(x) for x in vivas)

                    primero = d["anillos"][0] if d["anillos"] else {"propiedad": "-", "duracion": "-"}
                    resumen.append(
                        f"{motor:<8} {ruta.name:<18} {movimiento:<13} transición={primero['propiedad']} "
                        f"{primero['duracion']}  animaciones al cambiar={n_vivas} (familia {n_familia})  "
                        f"peor texto={peor_texto:.2f}:1  peor arco={peor_arco:.2f}:1"
                    )
                    contexto.close()

                # Teléfono: sin desplazamiento horizontal.
                contexto = navegador.new_context(viewport={"width": 390, "height": 780})
                pagina = contexto.new_page()
                pagina.goto(ruta.as_uri())
                ancho = pagina.evaluate("() => [document.documentElement.scrollWidth, document.documentElement.clientWidth]")
                if ancho[0] > ancho[1]:
                    fallos.append(f"{motor} {ruta.name} a 390 px: scrollWidth {ancho[0]} > {ancho[1]}")
                contexto.close()
            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa: {len(NAVEGADORES)} navegadores × {len(paginas)} páginas × {len(MOVIMIENTOS)} preferencias.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
