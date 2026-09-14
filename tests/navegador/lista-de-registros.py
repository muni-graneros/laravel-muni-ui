#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::record-list>` / `<x-muni::record-item>`.

    .venv-a11y/bin/python tests/navegador/lista-de-registros.py [build/lista-de-registros]

La corre `tests/ListaDeRegistrosTest.php` después de generar el banco (y se salta
si no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest
leen el TEXTO del CSS del componente —que la banda declare 3px, que el foco
declare su outline, que el objetivo diga 44px— y eso ya engañó una vez en `stat`:
la regla pasó el test de texto y en el navegador el defecto seguía vivo. Acá se
mide, en Chromium Y Firefox y en las CUATRO páginas (dos paletas × dos temas:
dentro del panel solo se carga `muni-ui-filament.css`, DESIGN §7):

  · el recorrido REAL de Tab con `document.activeElement`: el título de cada
    ficha y el control de la zona de acciones, en el orden del DOM, sin paradas
    de más ni fichas fuera del recorrido;
  · Enter sobre el título enfocado sigue el enlace (activación nativa del `<a>`);
  · con el foco puesto por teclado, el outline computa 3 px sólidos y su color
    llega a 3:1 contra el fondo (WCAG 2.2 AA 2.4.7 y 1.4.11). La box-shadow del
    anillo NO cuenta: dentro de Filament se computa transparente;
  · el objetivo táctil mide 44×44 px REALES —el de terreno, no el 24×24 mínimo
    de 2.5.8— tanto en el título como en el botón de la zona de acciones;
  · la banda de estado se PINTA: 3 px de borde izquierdo, opaco, y a 3:1 contra
    el fondo de la ficha (1.4.11); y la ficha SIN tono no pinta ninguna, para no
    inventar un estado que nadie declaró;
  · el estado no cuelga del color: la etiqueta textual existe, tiene alto real y
    pasa su propio contraste;
  · el folio se pinta en tipografía mono (la firma `.muni-num`), que es la razón
    por la que el componente declara esa clase en su propio bloque de estilos:
    en el portal del vecino no hay ninguna tabla en la página que la traiga;
  · todo texto de la ficha pasa 4,5:1 contra el fondo efectivo, en las cuatro
    páginas;
  · con `prefers-reduced-motion: reduce` la transición computa 0 s, y sin la
    preferencia existe (si no, la comprobación sería vacía);
  · a 390 px y a 320 px de ancho el documento NO se desplaza en horizontal
    (1.4.10) y la zona de acciones baja sola, sin una sola consulta de medios.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "lista-de-registros"
MOVIMIENTOS = ("reduce", "no-preference")
NAVEGADORES = ("chromium", "firefox")
# 320 px es el ancho de referencia de 1.4.10: la lista existe justamente porque
# la tabla no cabe en el teléfono del vecino, así que si no se mide ahí, la
# razón de ser del componente no tiene red.
ANCHOS = (("escritorio", 1440, 900), ("telefono", 390, 780), ("estrecho", 320, 780))

# Los títulos del banco, en orden del DOM.
TITULOS = (
    "Poda de árbol en Manuel Rodríguez 545",
    "Retiro de escombros en Los Aromos 120",
    "Permiso de ocupación de vereda",
    "Consulta por corte de agua",
)
# El objetivo táctil de TERRENO que exige la ficha del componente: 44, no 24.
TACTIL = 44

SONDA = r"""
() => {
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };

  // El primer ancestro con fondo opaco: la ficha pinta el suyo, pero el texto
  // que cuelga de ella es transparente.
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  const texto = (el, extra = {}) => {
    if (!el) return null;
    const c = getComputedStyle(el);
    return {
      texto: el.textContent.trim(),
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
      familia: c.fontFamily,
      ...caja(el),
      ...extra,
    };
  };

  const lista = document.querySelector('ul.muni-rec');
  const fichas = [...document.querySelectorAll('.muni-rec__item')].map(li => {
    const c = getComputedStyle(li);
    const enlace = li.querySelector('a.muni-rec__titulo');
    const accion = li.querySelector('.muni-rec__acciones a, .muni-rec__acciones button');
    return {
      etiqueta: li.tagName,
      banda: { ancho: c.borderLeftWidth, estilo: c.borderLeftStyle, color: c.borderLeftColor },
      fondo: c.backgroundColor,
      transicion: c.transitionDuration,
      titulo: texto(enlace || li.querySelector('.muni-rec__titulo'), {
        href: enlace ? enlace.getAttribute('href') : null,
        esEnlace: !!enlace,
      }),
      tono: texto(li.querySelector('.muni-rec__tono')),
      folio: texto(li.querySelector('.muni-rec__folio')),
      meta: texto(li.querySelector('.muni-rec__meta')),
      num: texto(li.querySelector('.muni-rec__meta .muni-num')),
      accion: accion ? texto(accion, { etiqueta: accion.tagName }) : null,
      // La zona de acciones bajó de línea = la ficha envolvió sin media queries.
      accionEnOtraLinea: accion
        ? Math.round(accion.getBoundingClientRect().top) >
          Math.round(li.querySelector('.muni-rec__cuerpo').getBoundingClientRect().top) + 4
        : null,
    };
  });

  return {
    lista: lista
      ? {
          rol: lista.getAttribute('role'),
          nombrada: !!(lista.getAttribute('aria-label') || lista.getAttribute('aria-labelledby')),
          marcador: getComputedStyle(lista).listStyleType,
        }
      : null,
    listitem: document.querySelectorAll('[role="listitem"]').length,
    tablas: document.querySelectorAll('table').length,
    fichas,
    enfocables: [...document.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')]
      .filter(e => e.getAttribute('tabindex') !== '-1').length,
    desbordaX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
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
  const r = a.getBoundingClientRect();
  return {
    etiqueta: a.tagName,
    texto: a.textContent.trim(),
    href: a.getAttribute('href'),
    clase: a.getAttribute('class'),
    outline: { ancho: c.outlineWidth, estilo: c.outlineStyle, color: c.outlineColor },
    fondo: fondoDe(a),
    ancho: r.width,
    alto: r.height,
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


def transparente(valor: str) -> bool:
    m = re.match(r"rgba\(\s*[\d.]+[,\s]+[\d.]+[,\s]+[\d.]+[,\s/]+([\d.]+)\s*\)", str(valor or ""))
    return valor in (None, "", "transparent") or (m is not None and float(m.group(1)) < 0.5)


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
    if d["lista"] is None:
        fallos.append(f"{donde}: no hay ninguna <ul class=\"muni-rec\">.")
        return

    if d["lista"]["rol"] != "list":
        fallos.append(
            f"{donde}: la lista no conserva role=\"list\" (Safari se la quita con list-style:none)."
        )
    if d["lista"]["marcador"] != "none":
        fallos.append(f"{donde}: la lista pinta marcadores ({d['lista']['marcador']}).")
    if not d["lista"]["nombrada"]:
        fallos.append(f"{donde}: la lista no tiene nombre accesible y el banco se lo pasa.")
    if d["listitem"]:
        fallos.append(
            f"{donde}: hay {d['listitem']} nodo(s) con role=\"listitem\"; el rol va en el contenedor."
        )
    if d["tablas"]:
        fallos.append(f"{donde}: hay {d['tablas']} tabla(s) en la página; esto es una lista, no un doble DOM.")

    if len(d["fichas"]) != len(TITULOS):
        fallos.append(f"{donde}: se esperaban {len(TITULOS)} fichas y hay {len(d['fichas'])}.")
        return

    for ficha, esperado in zip(d["fichas"], TITULOS):
        if ficha["etiqueta"] != "LI":
            fallos.append(f"{donde}: una ficha es <{ficha['etiqueta']}> y no <li>.")
        if not ficha["titulo"] or esperado not in ficha["titulo"]["texto"]:
            fallos.append(f"{donde}: falta el título «{esperado}».")

    # Una parada por título (4) más el único control de acciones del banco.
    if d["enfocables"] != len(TITULOS) + 1:
        fallos.append(
            f"{donde}: el documento tiene {d['enfocables']} paradas de teclado y se esperaban "
            f"{len(TITULOS) + 1}: el componente mete paradas de más o saca alguna del recorrido."
        )


def verificar_firma(d: dict, donde: str, fallos: list[str]) -> None:
    """Banda de estado, etiqueta en palabras, folio en mono y objetivo de 44px."""
    for i, ficha in enumerate(d["fichas"]):
        etiqueta = (ficha["titulo"] or {}).get("texto", f"#{i}")[:40]
        conTono = ficha["tono"] is not None

        ancho = float(re.sub(r"[^\d.]", "", ficha["banda"]["ancho"]) or 0)

        if conTono:
            if ancho < 3:
                fallos.append(
                    f"{donde} «{etiqueta}»: la banda de estado mide {ancho}px; la firma del sistema "
                    "es una franja de 3px al borde de la fila (DESIGN §9)."
                )
            if transparente(ficha["banda"]["color"]):
                fallos.append(f"{donde} «{etiqueta}»: la banda de estado es transparente.")
            else:
                r = contraste(ficha["banda"]["color"], ficha["fondo"])
                if r is not None and r < 3.0:
                    fallos.append(
                        f"{donde} «{etiqueta}»: la banda da {r:.2f}:1 contra la ficha (<3, WCAG 1.4.11)."
                    )
            if ficha["tono"]["alto"] < 8:
                fallos.append(f"{donde} «{etiqueta}»: la etiqueta de estado no tiene alto real.")
        else:
            # Sin tono declarado no se inventa un estado: ni banda de color ni palabra.
            if not transparente(ficha["banda"]["color"]):
                fallos.append(
                    f"{donde} «{etiqueta}»: pinta banda de estado ({ficha['banda']['color']}) sin que "
                    "el anfitrión haya declarado tono."
                )

        if ficha["folio"] and "mono" not in ficha["folio"]["familia"].lower():
            fallos.append(
                f"{donde} «{etiqueta}»: el folio no se pinta en mono ({ficha['folio']['familia']}). "
                "`.muni-num` vive solo en el @once de data-table y en esta página no hay ninguna tabla: "
                "es justo el defecto que el componente promete evitar."
            )
        if ficha["num"] and "mono" not in ficha["num"]["familia"].lower():
            fallos.append(
                f"{donde} «{etiqueta}»: el RUT que el anfitrión marcó con .muni-num sale en sans."
            )

        if ficha["titulo"] and ficha["titulo"]["esEnlace"]:
            if ficha["titulo"]["alto"] < TACTIL - 0.5 or ficha["titulo"]["ancho"] < TACTIL - 0.5:
                fallos.append(
                    f"{donde} «{etiqueta}»: el enlace mide "
                    f"{ficha['titulo']['ancho']:.0f}×{ficha['titulo']['alto']:.0f}px y el objetivo de "
                    f"terreno es {TACTIL}×{TACTIL} (la ficha del componente: «no 24, es terreno»)."
                )

        if ficha["accion"]:
            if ficha["accion"]["alto"] < TACTIL - 0.5 or ficha["accion"]["ancho"] < TACTIL - 0.5:
                fallos.append(
                    f"{donde} «{etiqueta}»: el control de acciones mide "
                    f"{ficha['accion']['ancho']:.0f}×{ficha['accion']['alto']:.0f}px (<{TACTIL})."
                )


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 99.0
    for ficha in d["fichas"]:
        etiqueta = (ficha["titulo"] or {}).get("texto", "?")[:40]
        for clave in ("titulo", "tono", "folio", "meta", "accion"):
            t = ficha[clave]
            if not t or not t["texto"]:
                continue
            r = contraste(t["color"], t["fondo"])
            if r is None:
                fallos.append(f"{donde} «{etiqueta}» ({clave}): no se pudo leer el color.")
                continue
            peor = min(peor, r)
            exigido = minimo(t["tamano"], t["peso"])
            if r < exigido:
                fallos.append(
                    f"{donde} «{etiqueta}» ({clave}): {r:.2f}:1 contra su fondo (exige {exigido}:1) "
                    f"[{t['color']} sobre {t['fondo']}]"
                )
    return peor


def verificar_movimiento(d: dict, donde: str, movimiento: str, fallos: list[str]) -> str:
    duraciones = {f["transicion"] for f in d["fichas"]}
    texto = ", ".join(sorted(duraciones))
    vivas = [t for t in duraciones if re.search(r"(?<![\d.])(?!0s)[\d.]+m?s", t)]
    if movimiento == "reduce" and vivas:
        fallos.append(
            f"{donde}: con movimiento reducido la transición de la ficha sigue en {texto}; "
            "tiene que computar 0 s. Dentro del panel --muni-dur no baja solo (DESIGN §7)."
        )
    if movimiento == "no-preference" and not vivas:
        fallos.append(
            f"{donde}: sin la preferencia no hay transición ninguna ({texto}): la comprobación "
            "de «reduce» sería vacía."
        )
    return texto


def verificar_teclado(pagina, donde: str, fallos: list[str]) -> str:
    """Tab desde el principio: título, acción y los títulos que siguen; Enter activa."""
    pagina.evaluate("() => { document.activeElement && document.activeElement.blur(); window.scrollTo(0, 0); }")
    pagina.keyboard.press("Tab")

    esperados = [TITULOS[0], "Ver detalle", TITULOS[1], TITULOS[2], TITULOS[3]]
    visto: list[str] = []

    for i, esperado in enumerate(esperados):
        foco = pagina.evaluate(FOCO)
        if not foco or foco["etiqueta"] != "A":
            fallos.append(f"{donde}: la parada #{i + 1} de Tab no cayó en un enlace: {foco!r}")
            return " → ".join(visto)

        rotulo = foco["texto"].split("\n")[0].strip()[:40]
        visto.append(rotulo)

        if esperado not in foco["texto"]:
            fallos.append(f"{donde}: la parada #{i + 1} de Tab es «{rotulo}» y se esperaba «{esperado}».")

        ancho = float(re.sub(r"[^\d.]", "", foco["outline"]["ancho"]) or 0)
        if ancho < 3 or foco["outline"]["estilo"] in ("none", "hidden"):
            fallos.append(
                f"{donde} «{rotulo}»: el foco de teclado no dibuja outline "
                f"({foco['outline']['estilo']} {foco['outline']['ancho']}). La box-shadow del anillo no cuenta."
            )
        else:
            r = contraste(foco["outline"]["color"], foco["fondo"])
            if r is not None and r < 3.0:
                fallos.append(f"{donde} «{rotulo}»: el outline da {r:.2f}:1 contra el fondo (<3, WCAG 1.4.11).")

        if foco["alto"] < TACTIL - 0.5 or foco["ancho"] < TACTIL - 0.5:
            fallos.append(
                f"{donde} «{rotulo}»: la parada de teclado mide "
                f"{foco['ancho']:.0f}×{foco['alto']:.0f}px (<{TACTIL}, el objetivo de terreno)."
            )

        pagina.keyboard.press("Tab")

    # Que no haya paradas de más se comprueba contando los enfocables del DOM
    # (verificar_estructura) y no con un Tab de más: en Firefox headless el foco
    # no sale del último enlace de la página y se mediría el navegador.

    # Enter sobre el título enfocado con teclado sigue el enlace: el <a> nativo.
    pagina.evaluate("() => document.querySelector('a.muni-rec__titulo[href=\"#solicitud-4655\"]').focus()")
    pagina.keyboard.press("Enter")
    if not pagina.url.endswith("#solicitud-4655"):
        fallos.append(f"{donde}: Enter sobre el título enfocado no siguió el enlace (URL {pagina.url}).")

    return " → ".join(visto)


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(
            f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
            "`vendor/bin/pest --filter=ListaDeRegistros`."
        )
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
                        verificar_firma(d, donde, fallos)
                        peor = verificar_contraste(d, donde, fallos)
                        transicion = verificar_movimiento(d, donde, movimiento, fallos)

                        if d["desbordaX"]:
                            fallos.append(f"{donde}: el documento se desplaza en horizontal (WCAG 1.4.10).")

                        # A 320px la zona de acciones tiene que haber bajado de línea sola:
                        # es lo que reemplaza a las container queries que el juez descartó.
                        baja = next((f["accionEnOtraLinea"] for f in d["fichas"] if f["accion"]), None)
                        if ancho <= 390 and baja is False:
                            fallos.append(
                                f"{donde}: a {ancho}px la zona de acciones sigue en la misma línea que el "
                                "cuerpo; la ficha no envuelve."
                            )

                        teclado = verificar_teclado(pagina, donde, fallos)

                        resumen.append(
                            f"{nombre:<9}{ruta.name:<18}[{movimiento:<13} {ancho_nombre:<10}] "
                            f"acciones abajo={baja} transición={transicion} peor contraste={peor:.2f}:1  Tab: {teclado}"
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
