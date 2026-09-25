#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::spinner>` y `<x-muni::busy-region>` sobre
el banco `build/carga-anunciada/`.

    .venv-a11y/bin/python tests/navegador/carga-anunciada.py [build/carga-anunciada]

La corre `tests/CargaAnunciadaTest.php` después de generar el banco (y se salta
si no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest
leen el TEXTO del CSS —que la animación esté dentro del bloque de movimiento
permitido, que el indicador nazca `display:none`— y eso ya engañó una vez en
`stat`: lo que importa se mide acá con `getComputedStyle` y el árbol de
accesibilidad, en Chromium Y Firefox, en las CUATRO páginas (dos paletas × dos
temas: dentro del panel solo se carga `muni-ui-filament.css`, DESIGN §7):

  · el arco del spinner gira (`animation-name: muni-spin`) SOLO sin
    `prefers-reduced-motion`; con la preferencia computa `none`. Sin la
    preferencia tiene que girar: si no, la comprobación sería vacía;
  · el spinner mide lo que dice `size`, va `aria-hidden` y no mete paradas de
    teclado;
  · la región `role="status"` existe en reposo, vacía, y NO está fuera del
    árbol (ni `display:none` ni `visibility:hidden`);
  · el indicador de proceso nace oculto y con `.muni-busy--loading` computa
    `inline-flex`; su texto pasa 4,5:1 contra su propio fondo, y el resultado
    pasa 4,5:1 contra el fondo de la tarjeta, también sobre `--muni-surface-3`;
  · el árbol ARIA de Chromium/Firefox expone `status` con el texto del resultado.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "carga-anunciada"
MOVIMIENTOS = ("reduce", "no-preference")
NAVEGADORES = ("chromium", "firefox")

SONDA = r"""
() => {
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };
  const fondoDe = el => {
    // El primer ancestro con fondo opaco: la tarjeta pinta un token sin alfa.
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };
  const texto = el => {
    const c = getComputedStyle(el);
    return { texto: el.textContent.trim(), color: c.color, fondo: fondoDe(el), tamano: parseFloat(c.fontSize), display: c.display, visibility: c.visibility };
  };

  const spinners = [...document.querySelectorAll('.muni-spinner')].map(s => {
    const svg = s.querySelector('svg');
    const c = getComputedStyle(svg);
    return { ...caja(s), ariaHidden: s.getAttribute('aria-hidden'), role: s.getAttribute('role'), animacion: c.animationName, duracion: c.animationDuration, texto: s.textContent.trim() };
  });

  const regiones = [...document.querySelectorAll('.muni-busy')].map(r => {
    const status = r.querySelector('[role="status"]');
    const ind = r.querySelector('.muni-busy__loading');
    const cont = r.querySelector('.muni-busy__content');
    const cs = getComputedStyle(status);
    return {
      caso: r.closest('[data-caso]').getAttribute('data-caso'),
      ocupada: r.classList.contains('muni-busy--loading'),
      ariaBusy: cont.getAttribute('aria-busy'),
      statusEnArbol: cs.display !== 'none' && cs.visibility !== 'hidden',
      statusAriaLive: status.getAttribute('aria-live'),
      status: texto(status),
      indicadorDisplay: getComputedStyle(ind).display,
      indicadorPuntero: getComputedStyle(ind).pointerEvents,
      indicador: texto(ind.querySelector('span')),
      indicadorFondo: getComputedStyle(ind).backgroundColor,
      spinnerDentro: !!ind.querySelector('.muni-spinner[aria-hidden="true"]'),
      paradasDeTeclado: r.querySelectorAll('a[href],button,input,select,textarea,[tabindex]').length,
      regionesVivas: r.querySelectorAll('[role="status"],[role="alert"],[aria-live]').length,
    };
  });

  const ids = [...document.querySelectorAll('[id]')].map(e => e.id);
  return { spinners, regiones, idsRepetidos: ids.filter((v, i, a) => a.indexOf(v) !== i) };
}
"""


def _rgb(valor: str) -> tuple[float, float, float, float]:
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


def contraste(texto: str, fondo: str) -> float:
    frente = _mezclar(texto, fondo)
    l1, l2 = _luminancia(frente), _luminancia(_rgb(fondo)[:3])
    claro, oscuro = max(l1, l2), min(l1, l2)
    return (claro + 0.05) / (oscuro + 0.05)


def verificar_pagina(pagina, ruta: Path, movimiento: str, navegador: str, fallos: list[str]) -> dict:
    d = pagina.evaluate(SONDA)
    donde = f"{navegador} {ruta.name} [{movimiento}]"

    if len(d["spinners"]) < 3 or len(d["regiones"]) < 3:
        fallos.append(f"{donde}: banco incompleto ({len(d['spinners'])} spinners, {len(d['regiones'])} regiones)")
        return d
    if d["idsRepetidos"]:
        fallos.append(f"{donde}: ids repetidos: {sorted(set(d['idsRepetidos']))}")

    for i, s in enumerate(d["spinners"]):
        quien = f"{donde} spinner {i}"
        if movimiento == "reduce":
            if s["animacion"] != "none":
                fallos.append(f"{quien}: con prefers-reduced-motion el arco sigue animado: {s['animacion']} {s['duracion']}")
        elif s["animacion"] != "muni-spin":
            fallos.append(f"{quien}: sin la preferencia no gira (animation-name={s['animacion']!r}): la comprobación sería vacía")
        if s["ariaHidden"] != "true" or s["role"] is not None or s["texto"] != "":
            fallos.append(f"{quien}: no es decorativo puro: aria-hidden={s['ariaHidden']} role={s['role']} texto={s['texto']!r}")

    tamanos = sorted(round(s["ancho"]) for s in d["spinners"][:3])
    if tamanos != [16, 20, 32]:
        fallos.append(f"{donde}: los spinners miden {tamanos}, esperaba [16, 20, 32] (prop size)")

    for r in d["regiones"]:
        quien = f"{donde} región «{r['caso']}»"
        if not r["statusEnArbol"]:
            fallos.append(f"{quien}: la región role=status está fuera del árbol de accesibilidad")
        if r["statusAriaLive"] is not None:
            fallos.append(f"{quien}: role=status y aria-live en el mismo nodo")
        if r["regionesVivas"] != 1:
            fallos.append(f"{quien}: {r['regionesVivas']} regiones vivas; tiene que haber exactamente una (la del resultado)")
        if r["paradasDeTeclado"]:
            fallos.append(f"{quien}: mete {r['paradasDeTeclado']} parada(s) de teclado")
        if not r["spinnerDentro"]:
            fallos.append(f"{quien}: el indicador no trae el spinner decorativo")

        if r["ocupada"]:
            # `flex` y no `inline-flex`: el indicador va `position:absolute`, y un
            # elemento posicionado se blockifica (CSS Display 3 §2.7), así que el
            # valor computado es `flex` aunque la hoja dijera `inline-flex`.
            if r["indicadorDisplay"] != "flex":
                fallos.append(f"{quien}: ocupada y el indicador computa display={r['indicadorDisplay']!r}")
            if r["ariaBusy"] != "true":
                fallos.append(f"{quien}: ocupada sin aria-busy=\"true\" en el contenedor")
            # Superpuesto sobre la esquina del contenido: sin `pointer-events:none`
            # intercepta el clic o el tap que iba a la fila de abajo.
            if r["indicadorPuntero"] != "none":
                fallos.append(f"{quien}: el indicador superpuesto computa pointer-events={r['indicadorPuntero']!r} e intercepta el puntero")
            ratio = contraste(r["indicador"]["color"], r["indicadorFondo"])
            r["indicador"]["ratio"] = ratio
            if ratio < 4.5:
                fallos.append(f"{quien}: el texto de carga «{r['indicador']['texto']}» a {ratio:.2f}:1 sobre su fondo")
        else:
            if r["indicadorDisplay"] != "none":
                fallos.append(f"{quien}: en reposo el indicador computa display={r['indicadorDisplay']!r}")
            if r["ariaBusy"] is not None:
                fallos.append(f"{quien}: en reposo nace aria-busy={r['ariaBusy']!r}")

        if r["status"]["texto"]:
            ratio = contraste(r["status"]["color"], r["status"]["fondo"])
            r["status"]["ratio"] = ratio
            if ratio < 4.5:
                fallos.append(f"{quien}: el resultado «{r['status']['texto']}» a {ratio:.2f}:1 contra el fondo de la tarjeta")

    return d


def verificar_arbol_aria(pagina, donde: str, fallos: list[str]) -> list[str]:
    lineas = []
    for caso in ("resultado", "resultado-3"):
        snap = pagina.locator(f'[data-caso="{caso}"] .muni-busy').aria_snapshot().strip()
        lineas.append(f"{caso}: {snap}")
        if "status" not in snap or "1 persona encontrada" not in snap:
            fallos.append(f"{donde} «{caso}»: el árbol ARIA no expone status con el resultado: {snap!r}")
    return lineas


def main(argv: list[str]) -> int:
    carpeta = Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
              "`vendor/bin/pest --filter=CargaAnunciada`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []
    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()
            for ruta in paginas:
                for movimiento in MOVIMIENTOS:
                    contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion=movimiento)
                    pagina = contexto.new_page()
                    pagina.goto(ruta.as_uri())
                    d = verificar_pagina(pagina, ruta, movimiento, nombre, fallos)
                    if d.get("spinners") and d.get("regiones"):
                        peor = min(
                            [r["status"]["ratio"] for r in d["regiones"] if "ratio" in r["status"]]
                            + [r["indicador"]["ratio"] for r in d["regiones"] if "ratio" in r["indicador"]],
                            default=0.0,
                        )
                        resumen.append(
                            f"{nombre:<9}{ruta.name:<20}[{movimiento:<13}] spinner={d['spinners'][0]['animacion']} "
                            f"{d['spinners'][0]['duracion']}  peor contraste={peor:.2f}:1"
                        )
                        if movimiento == "reduce":
                            resumen.extend("    " + l for l in verificar_arbol_aria(pagina, f"{nombre} {ruta.name}", fallos))
                    contexto.close()
            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa: {len(NAVEGADORES)} navegadores × {len(paginas)} páginas × {len(MOVIMIENTOS)} preferencias de movimiento.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
