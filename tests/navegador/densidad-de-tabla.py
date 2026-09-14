#!/usr/bin/env python3
"""Verificación en navegador de la densidad de tabla sobre `build/densidad-de-tabla/`.

    .venv-a11y/bin/python tests/navegador/densidad-de-tabla.py [build/densidad-de-tabla]

La corre `tests/DensidadDeTablaTest.php` después de generar el banco (y se salta
si no está el entorno de la reja, que en CI no se instala).

Existe porque TODO lo que las pruebas de Pest de esta ficha comprueban es el
TEXTO del `.blade.php` —que la clase salga en la `<table>`, que ciertas cadenas
estén en el CSS—, y eso ya engañó una vez en esta misma ficha: la banda de 3px de
la fila morosa de `sortable-table` estaba escrita en el CSS, con el candado en
verde, y en el navegador computaba `box-shadow: none`, porque las celdas salen de
un `<template x-for>` y `td:first-child` no casaba con ninguna. Lo que de verdad
importa de la densidad solo se ve con estilos computados:

  · la fila ENCOGE de verdad: alto medido de `<tr>`, no el padding declarado;
  · la herencia de `<html data-muni-densidad="compacta">` —la única vía de
    persistencia del diseño: el anfitrión lee la cookie EN SERVIDOR— alcanza a las
    dos tablas, y `densidad="comoda"` le gana a mano;
  · con el relleno en 4px, el enlace y el botón de la celda de acciones siguen
    midiendo 24×24 (WCAG 2.2 AA 2.5.8): la fila encoge, el objetivo no;
  · la banda de 3px de la fila morosa se PINTA en las dos tablas, con y sin
    columna de selección, y el rojo del dato no cae sobre la casilla (DESIGN §9);
  · el foco del botón de orden sigue siendo un `outline` de 3px llegando por
    teclado, también con la fila compacta (§5);
  · forzando `line-height: 1.5` (SC 1.4.12 Text Spacing) la fila CRECE y no se
    recorta ninguna celda, que es lo que pasaría con una `height` fija;
  · y cuántas filas caben a 1366×768 en cada densidad, que es la medición que la
    corrección del juez pedía antes de codear y que no era reproducible desde el
    repo.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "densidad-de-tabla"
NAVEGADORES = ("chromium", "firefox")

# El alto de la ventana del mesón: 1366×768 es la resolución con la que la
# corrección del juez pedía contar las filas de la bandeja.
VENTANA = {"width": 1366, "height": 768}

# Tolerancia de redondeo entre motores (Firefox devuelve fracciones distintas).
EPS = 0.6

# Lo que tiene que encoger de verdad respecto de la densidad cómoda.
MINIMO_ENCOGE = 3.0

SONDA = r"""
() => {
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height, top: r.top }; };

  // El rojo de la firma, resuelto: el token puede ser hex, y `box-shadow` y
  // `color` computan en rgb().
  const sonda = document.createElement('span');
  sonda.style.color = 'var(--muni-danger-fg)';
  document.body.appendChild(sonda);
  const rojo = getComputedStyle(sonda).color;
  sonda.remove();

  const casos = {};

  for (const sec of document.querySelectorAll('[data-caso]')) {
    const tabla = sec.querySelector('table');
    if (!tabla) { casos[sec.dataset.caso] = null; continue; }

    const filas = [...tabla.querySelectorAll('tbody tr')];
    const primera = filas[0] || null;
    const cabecera = tabla.querySelector('thead th');
    const celda = primera ? [...primera.children].find(n => n.tagName === 'TD') : null;

    // La fila morosa de cualquiera de las dos tablas.
    const morosa = tabla.querySelector('tbody tr.muni-row--danger, tbody tr.muni-st__danger');
    let banda = null;

    if (morosa) {
      // La PRIMERA celda de la fila: el borde izquierdo. Ojo, `children` incluye
      // el <template x-for> de la tabla ordenable, que no es una celda.
      const celdas = [...morosa.children].filter(n => n.tagName === 'TD');
      const primeraCelda = celdas[0] || null;
      const casilla = morosa.querySelector('td.muni-st__pick');
      // La primera celda de DATOS: la que lleva el rojo y la negrita.
      const dato = morosa.querySelector('td.muni-st__first')
        || celdas.find(t => !t.classList.contains('muni-st__pick'))
        || null;

      banda = {
        celdas: celdas.length,
        primeraEsCasilla: primeraCelda ? primeraCelda.classList.contains('muni-st__pick') : null,
        sombra: primeraCelda ? getComputedStyle(primeraCelda).boxShadow : null,
        colorDato: dato ? getComputedStyle(dato).color : null,
        pesoDato: dato ? getComputedStyle(dato).fontWeight : null,
        colorCasilla: casilla ? getComputedStyle(casilla).color : null,
      };
    }

    casos[sec.dataset.caso] = {
      clase: tabla.className,
      filas: filas.length,
      altoFila: primera ? caja(primera).alto : 0,
      altoCabecera: cabecera ? caja(cabecera).alto : 0,
      padding: celda ? getComputedStyle(celda).padding : null,
      altoCelda: celda ? getComputedStyle(celda).height : null,
      banda,
      objetivos: [...tabla.querySelectorAll('td a, td button')].map(el => ({
        etiqueta: el.tagName.toLowerCase(),
        texto: (el.textContent || '').trim().slice(0, 14),
        ...caja(el),
      })),
      recortadas: [...tabla.querySelectorAll('td')]
        .filter(td => td.scrollHeight > td.clientHeight + 1).length,
    };
  }

  return { rojo, anfitrion: document.documentElement.getAttribute('data-muni-densidad'), casos };
}
"""

# SC 1.4.12: el usuario fuerza el interlineado y el espaciado. Con `padding` la
# fila crece y el texto se lee; con una `height` fija se recortaría.
TEXTO_FORZADO = (
    "*{line-height:1.5 !important;letter-spacing:.12em !important;"
    "word-spacing:.16em !important;}"
)


def alto(medida: dict, caso: str) -> float:
    return float(medida["casos"].get(caso, {})["altoFila"])


def verificar(medida: dict, donde: str, fallos: list[str]) -> None:
    casos = medida["casos"]
    faltan = [c for c, v in casos.items() if not v]

    if faltan:
        fallos.append(f"{donde}: casos sin tabla renderizada: {faltan}")
        return

    for caso, v in casos.items():
        if v["filas"] == 0:
            fallos.append(f"{donde} [{caso}]: la tabla no pintó ni una fila (¿Alpine no arrancó?)")

    anfitrion = medida["anfitrion"] == "compacta"

    for prefijo in ("dt", "st"):
        defecto, compacta, comoda = (alto(medida, f"{prefijo}-{s}") for s in ("defecto", "compacta", "comoda"))

        if compacta >= comoda - MINIMO_ENCOGE:
            fallos.append(
                f"{donde} [{prefijo}]: la fila compacta mide {compacta:.1f} px y la cómoda {comoda:.1f} px: "
                f"no encoge ni {MINIMO_ENCOGE} px, la densidad no sirve de nada"
            )

        if anfitrion:
            # El anfitrión pintó el modo compacto en el <html>: la tabla SIN prop
            # tiene que heredarlo, y la que pide «comoda» a mano tiene que ganarle.
            if abs(defecto - compacta) > EPS:
                fallos.append(
                    f"{donde} [{prefijo}]: con data-muni-densidad=\"compacta\" en el <html>, la tabla sin prop "
                    f"mide {defecto:.1f} px y la compacta {compacta:.1f} px: la herencia del anfitrión no llega, "
                    "así que la cookie del funcionario no cambia nada"
                )
            if comoda <= compacta + MINIMO_ENCOGE:
                fallos.append(
                    f"{donde} [{prefijo}]: densidad=\"comoda\" mide {comoda:.1f} px dentro de un documento "
                    "compacto: la prop no le gana a la herencia y una nómina de tres filas no se puede dejar cómoda"
                )
        else:
            if abs(defecto - comoda) > EPS:
                fallos.append(
                    f"{donde} [{prefijo}]: sin atributo del anfitrión, la tabla sin prop mide {defecto:.1f} px y "
                    f"densidad=\"comoda\" {comoda:.1f} px: el defecto dejó de ser la densidad de siempre"
                )

    # El piso de objetivo, en TODAS las densidades: la fila encoge, el objetivo no.
    for caso, v in casos.items():
        for o in v["objetivos"]:
            if o["ancho"] < 24 - EPS or o["alto"] < 24 - EPS:
                fallos.append(
                    f"{donde} [{caso}]: el <{o['etiqueta']}> «{o['texto']}» mide "
                    f"{o['ancho']:.1f}×{o['alto']:.1f} px, bajo el mínimo de 24×24 de WCAG 2.2 AA 2.5.8"
                )

    # La firma del sistema: la banda de 3px tiene que estar PINTADA.
    rojo = medida["rojo"]

    for caso, v in casos.items():
        banda = v["banda"]

        if banda is None:
            fallos.append(f"{donde} [{caso}]: no hay ninguna fila con la marca de morosidad para medir la banda")
            continue

        sombra = banda["sombra"] or "none"

        if "inset" not in sombra or "3px" not in sombra:
            fallos.append(
                f"{donde} [{caso}]: la banda de 3px de la fila morosa NO se pinta: la primera celda computa "
                f"box-shadow «{sombra}». Es la firma del sistema y su único portador de estado no cromático "
                "(DESIGN §9)"
            )
        elif rojo not in sombra:
            fallos.append(f"{donde} [{caso}]: la banda no sale de --muni-danger-fg ({rojo}): «{sombra}»")

        if banda["colorDato"] != rojo:
            fallos.append(
                f"{donde} [{caso}]: la primera celda de DATOS computa color {banda['colorDato']} y el rojo del "
                f"estado es {rojo}: el dato de la fila morosa no se distingue"
            )

        if banda["colorCasilla"] is not None and banda["colorCasilla"] == rojo:
            fallos.append(
                f"{donde} [{caso}]: el rojo cae sobre la CASILLA de selección, no sobre el dato "
                "(ficha seleccion-lote, punto 4)"
            )


def verificar_texto_forzado(antes: dict, despues: dict, donde: str, fallos: list[str]) -> None:
    """SC 1.4.12: con `line-height: 1.5` forzado la fila crece y nada se recorta."""
    for caso, v in despues["casos"].items():
        if not v or not antes["casos"].get(caso):
            continue

        if v["altoFila"] < antes["casos"][caso]["altoFila"] - EPS:
            fallos.append(
                f"{donde} [{caso}]: con line-height 1.5 la fila ENCOGE "
                f"({antes['casos'][caso]['altoFila']:.1f} → {v['altoFila']:.1f} px): hay un alto fijo de por medio"
            )

        if v["recortadas"]:
            fallos.append(
                f"{donde} [{caso}]: {v['recortadas']} celda(s) recortan su texto con line-height 1.5 "
                "(WCAG 2.2 AA 1.4.12 Text Spacing)"
            )


def verificar_foco(pagina, donde: str, fallos: list[str]) -> str:
    """Llegando por TECLADO, el botón de orden conserva su outline de 3px."""
    pagina.evaluate("() => document.body.focus()")

    for _ in range(80):
        pagina.keyboard.press("Tab")
        dato = pagina.evaluate(
            """() => {
                const el = document.activeElement;
                if (!el || !el.classList.contains('muni-st__sort')) return null;
                const c = getComputedStyle(el);
                const caso = el.closest('[data-caso]');
                return {
                    caso: caso ? caso.dataset.caso : null,
                    ancho: c.outlineWidth, estilo: c.outlineStyle, color: c.outlineColor,
                    sombra: c.boxShadow,
                };
            }"""
        )

        if dato is None:
            continue

        if dato["estilo"] == "none" or not dato["ancho"].startswith("3"):
            fallos.append(
                f"{donde} [{dato['caso']}]: el botón de orden enfocado por teclado computa outline "
                f"{dato['ancho']} {dato['estilo']}: el compacto no puede reducir ni eliminar el indicador de foco"
            )

        return f"foco={dato['ancho']} {dato['estilo']} en {dato['caso']}"

    fallos.append(f"{donde}: tabulando 80 veces no se llegó a ningún botón de orden")

    return "foco=sin alcanzar"


def filas_por_pantallada(medida: dict) -> tuple[int, int, float, float]:
    """Cuántas filas de la nómina caben en la ventana del mesón, por densidad."""
    salida = []

    for caso in ("nomina-comoda", "nomina-compacta"):
        v = medida["casos"][caso]
        libre = VENTANA["height"] - v["altoCabecera"]
        salida.append((int(libre // v["altoFila"]), v["altoFila"]))

    return salida[0][0], salida[1][0], salida[0][1], salida[1][1]


def main(argv: list[str]) -> int:
    carpeta = Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO
    paginas = sorted(carpeta.glob("*.html"))

    if len(paginas) < 4:
        print(
            f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
            "`vendor/bin/pest --filter=DensidadDeTabla`."
        )
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()

            for ruta in paginas:
                donde = f"{nombre}/{ruta.stem}"
                contexto = navegador.new_context(viewport=VENTANA)
                pagina = contexto.new_page()
                pagina.goto(ruta.as_uri())
                # Sin Alpine pintado, la tabla ordenable no tiene ni una fila.
                pagina.wait_for_selector(".muni-st tbody td", timeout=10000)

                medida = pagina.evaluate(SONDA)
                verificar(medida, donde, fallos)
                foco = verificar_foco(pagina, donde, fallos)

                pagina.add_style_tag(content=TEXTO_FORZADO)
                verificar_texto_forzado(medida, pagina.evaluate(SONDA), donde, fallos)

                comodas, compactas, altoC, altoK = filas_por_pantallada(medida)
                resumen.append(
                    f"{donde:<28} fila cómoda={altoC:.1f}px compacta={altoK:.1f}px · "
                    f"filas por pantallada (1366×768): {comodas} → {compactas} (+{compactas - comodas}) · {foco}"
                )

                contexto.close()

            navegador.close()

    print("\n".join(resumen))

    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1

    print(f"\nTodo pasa: {len(paginas)} páginas × {len(NAVEGADORES)} motores.")

    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
