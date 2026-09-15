#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::description-list>` / `<x-muni::description-item>`.

    .venv-a11y/bin/python tests/navegador/pares-dato-valor.py [build/pares-dato-valor]

La corre `tests/ParesDatoValorTest.php` después de generar el banco (y se salta si
no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest leen
el TEXTO del CSS del componente —que `.muni-num` esté declarada, que el `@media
print` diga `break-inside:avoid`, que haya un `display:grid`— y eso ya engañó una
vez en `stat`: la regla pasó el test de texto y en el navegador el defecto seguía
vivo. Acá se mide, en Chromium Y Firefox y en las CUATRO páginas (dos paletas ×
dos temas: dentro del panel solo se carga `muni-ui-filament.css`, DESIGN §7):

  · la `font-family` COMPUTADA del RUT y del monto en una página SIN UNA SOLA
    TABLA. Es la corrección #2 del juez medida donde importa: si `.muni-num` no
    viajara en el `@once` de este componente, acá saldría en sans proporcional;
  · las dos columnas de verdad —dt y dd en la misma línea, con el dd a la
    derecha— en escritorio, y el colapso a UNA en 390 y 320 px, sin una sola
    container query;
  · el drawer de 360 px dentro de una ventana de 1440: la lista suelta recibe
    dos columnas que no caben (el media query mira el viewport y miente) y la
    lista `stacked` se queda en una. Es la demostración de por qué existe esa
    prop, y queda escrita en el resumen de cada corrida;
  · la línea que separa un par del siguiente es CONTINUA: el aire entre columnas
    es relleno del <dt>, no `column-gap`, así que entre el borde del dt y el del
    dd no queda hueco. Con gap, la ficha sale con la regla partida en dos;
  · todo texto del par pasa 4,5:1 contra su fondo efectivo (y la raya del dato
    ausente, que es `aria-hidden`, pasa 3:1 como objeto gráfico: la política de
    `scripts/a11y-check.py`);
  · el «Sin dato» del dato ausente está oculto a la vista pero VIVO en el árbol
    de accesibilidad: 1px y `clip-path`, nunca `display:none` ni `visibility`;
  · el par es hijo directo del <dl> y dentro de la lista no hay un solo nodo que
    no sea dt o dd (la regla `definition-list` de axe apagaría el subárbol);
  · con `@media print` EMULADO, `break-inside` y `break-after` computan `avoid`
    en el motor, que es lo único que prueba que la regla de impresión llega;
  · con `prefers-reduced-motion: reduce` no queda ni una transición viva.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "pares-dato-valor"
NAVEGADORES = ("chromium", "firefox")
# 320 px es el ancho de referencia de 1.4.10; 390 el teléfono del vecino.
ANCHOS = (("escritorio", 1440, 900), ("telefono", 390, 780), ("estrecho", 320, 780))

# Las tres listas del banco, con las etiquetas de sus pares en orden del DOM y
# si esperamos mono en el valor.
FICHA = (
    ("RUT", True),
    ("Nombre completo", False),
    ("Domicilio", False),
    ("Clase solicitada", False),
    ("Estado", False),
    ("Correo", False),
    ("Derechos municipales", True),
)
DRAWER = (("RUT", True), ("Domicilio", False))
LISTAS = {"ficha": FICHA, "drawer-suelto": DRAWER, "drawer-apilado": DRAWER}

SONDA = r"""
() => {
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  const caja = el => { const r = el.getBoundingClientRect(); return { x: r.left, y: r.top, der: r.right, abajo: r.bottom, ancho: r.width, alto: r.height }; };

  const texto = el => {
    if (!el) return null;
    const c = getComputedStyle(el);
    return {
      texto: el.textContent.trim(),
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
      familia: c.fontFamily,
      transicion: c.transitionDuration,
      animacion: c.animationDuration,
      bordeAbajo: { ancho: parseFloat(c.borderBottomWidth) || 0, color: c.borderBottomColor, estilo: c.borderBottomStyle },
      corte: { dentro: c.breakInside, despues: c.breakAfter, dentroViejo: c.pageBreakInside, despuesViejo: c.pageBreakAfter },
      ...caja(el),
    };
  };

  const listas = [...document.querySelectorAll('dl')].map(dl => {
    const hijos = [...dl.children];
    const intrusos = hijos.filter(h => h.tagName !== 'DT' && h.tagName !== 'DD').map(h => h.tagName);
    const pares = [];

    for (let i = 0; i + 1 < hijos.length; i += 2) {
      const dt = hijos[i], dd = hijos[i + 1];
      if (dt.tagName !== 'DT' || dd.tagName !== 'DD') continue;

      const sr = dd.querySelector('.muni-sr');
      const oculto = dd.querySelector('[aria-hidden="true"]');
      const srC = sr ? getComputedStyle(sr) : null;

      pares.push({
        etiqueta: dt.textContent.trim(),
        dt: texto(dt),
        dd: texto(dd),
        mono: dd.classList.contains('muni-num'),
        clase: dd.getAttribute('class'),
        ultimo: dd === hijos[hijos.length - 1],
        sr: sr ? {
          texto: sr.textContent.trim(),
          display: srC.display,
          visibilidad: srC.visibility,
          posicion: srC.position,
          recorte: srC.clipPath,
          ...caja(sr),
        } : null,
        raya: oculto ? texto(oculto) : null,
      });
    }

    const c = getComputedStyle(dl);

    return {
      id: dl.getAttribute('data-banco'),
      nombre: dl.getAttribute('aria-label'),
      display: c.display,
      columnas: c.gridTemplateColumns,
      huecoColumna: c.columnGap,
      ancho: dl.getBoundingClientRect().width,
      intrusos,
      estilosDentro: dl.querySelectorAll('style').length,
      divsDentro: dl.querySelectorAll('div').length,
      pares,
    };
  });

  return {
    listas,
    tablas: document.querySelectorAll('table').length,
    desbordaX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
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
    """4,5:1, o 3:1 si el texto es grande (>=24px, o >=18.66px en negrita)."""
    grande = tamano >= 24 or (tamano >= 18.66 and peso >= 700)
    return 3.0 if grande else 4.5


def por_id(d: dict) -> dict:
    return {l["id"]: l for l in d["listas"] if l["id"]}


def dos_columnas(par: dict) -> bool:
    """dd a la derecha del dt y en la misma línea: la rejilla de dos columnas."""
    return par["dd"]["x"] >= par["dt"]["der"] - 0.5 and abs(par["dd"]["y"] - par["dt"]["y"]) < 4


def verificar_estructura(d: dict, donde: str, fallos: list[str]) -> None:
    listas = por_id(d)

    if d["tablas"]:
        fallos.append(
            f"{donde}: el banco trae {d['tablas']} tabla(s). Con una tabla en la página, `.muni-num` "
            "podría venir del @once de data-table y la medición de la mono dejaría de probar la "
            "corrección #2."
        )

    for id_lista, esperados in LISTAS.items():
        lista = listas.get(id_lista)
        if lista is None:
            fallos.append(f"{donde}: falta la lista «{id_lista}» en el banco.")
            continue

        if lista["display"] != "grid":
            fallos.append(
                f"{donde} [{id_lista}]: el <dl> computa display:{lista['display']} y no grid; sin "
                "rejilla sobre el propio <dl> no hay dos columnas conservando dt/dd hijos directos."
            )
        if lista["intrusos"]:
            fallos.append(
                f"{donde} [{id_lista}]: dentro del <dl> hay {lista['intrusos']}; el <dl> solo admite "
                "dt y dd, y axe apaga la revisión del subárbol con `definition-list`."
            )
        if lista["estilosDentro"] or lista["divsDentro"]:
            fallos.append(
                f"{donde} [{id_lista}]: hay un <style> o un <div> DENTRO del <dl> "
                f"(style={lista['estilosDentro']}, div={lista['divsDentro']})."
            )
        if not lista["nombre"]:
            fallos.append(f"{donde} [{id_lista}]: la lista no lleva el nombre accesible que el banco le pasa.")

        if len(lista["pares"]) != len(esperados):
            fallos.append(
                f"{donde} [{id_lista}]: {len(lista['pares'])} pares y se esperaban {len(esperados)}."
            )
            continue

        for par, (etiqueta, _) in zip(lista["pares"], esperados):
            if par["etiqueta"] != etiqueta:
                fallos.append(f"{donde} [{id_lista}]: el par dice «{par['etiqueta']}» y se esperaba «{etiqueta}».")


def verificar_mono(d: dict, donde: str, fallos: list[str]) -> None:
    """La firma del sistema (DESIGN §9) en una página sin una sola tabla."""
    for id_lista, esperados in LISTAS.items():
        lista = por_id(d).get(id_lista)
        if lista is None or len(lista["pares"]) != len(esperados):
            continue

        for par, (etiqueta, mono) in zip(lista["pares"], esperados):
            familia = par["dd"]["familia"].lower()
            if mono:
                if not par["mono"]:
                    fallos.append(f"{donde} [{id_lista}] «{etiqueta}»: el valor no lleva la clase .muni-num.")
                if "mono" not in familia:
                    fallos.append(
                        f"{donde} [{id_lista}] «{etiqueta}»: el valor sale en «{par['dd']['familia']}» y no "
                        "en mono. En esta página no hay ninguna tabla: si la declaración de `.muni-num` no "
                        "viaja en el @once de este componente, el RUT sale en sans proporcional y las "
                        "cifras bailan (corrección #2 del juez, DESIGN §7 y §9)."
                    )
            else:
                if par["mono"] or "mono" in familia:
                    fallos.append(
                        f"{donde} [{id_lista}] «{etiqueta}»: el valor sale en mono sin que se lo pidan "
                        f"({par['dd']['familia']}); un domicilio en mono se lee peor."
                    )


def verificar_rejilla(d: dict, donde: str, ancho: int, fallos: list[str]) -> str:
    """Dos columnas en escritorio, una en el teléfono, y el drawer de 360 px."""
    listas = por_id(d)
    estado = {}

    for id_lista, lista in listas.items():
        if not lista["pares"]:
            continue
        dobles = [dos_columnas(p) for p in lista["pares"]]
        estado[id_lista] = "2col" if all(dobles) else ("1col" if not any(dobles) else "mezcla")

        if estado[id_lista] == "mezcla":
            fallos.append(
                f"{donde} [{id_lista}]: unos pares quedan en dos columnas y otros en una; las "
                "etiquetas dejan de formar columna."
            )

    if ancho >= 1024:
        if estado.get("ficha") != "2col":
            fallos.append(
                f"{donde} [ficha]: a {ancho}px la lista NO está en dos columnas ({estado.get('ficha')}); "
                "el media query de 40em no llegó."
            )
        # El motivo de `stacked`, medido: en un contenedor de 360px dentro de una
        # ventana de 1440 el media query mira el VIEWPORT y da dos columnas.
        if estado.get("drawer-suelto") != "2col":
            fallos.append(
                f"{donde} [drawer-suelto]: se esperaba que el media query del viewport diera dos "
                f"columnas dentro del drawer de 360px ({estado.get('drawer-suelto')}). Si esto cambió, "
                "la prop `stacked` ya no haría falta y hay que revisar su razón de ser."
            )
        if estado.get("drawer-apilado") != "1col":
            fallos.append(
                f"{donde} [drawer-apilado]: `stacked` no fuerza una sola columna ({estado.get('drawer-apilado')}): "
                "en un drawer angosto dentro de una pantalla ancha las dos columnas no caben."
            )
    else:
        for id_lista, valor in estado.items():
            if valor != "1col":
                fallos.append(
                    f"{donde} [{id_lista}]: a {ancho}px sigue en dos columnas ({valor}); en el teléfono "
                    "del vecino la etiqueta y el valor tienen que apilarse."
                )

    return " ".join(f"{k}={v}" for k, v in sorted(estado.items()))


def verificar_linea(d: dict, donde: str, fallos: list[str]) -> None:
    """La regla que separa un par del siguiente, sin hueco y sin sobrar al final."""
    for id_lista, lista in por_id(d).items():
        if lista["huecoColumna"] not in ("normal", "0px", ""):
            fallos.append(
                f"{donde} [{id_lista}]: el <dl> declara column-gap {lista['huecoColumna']}; con hueco, "
                "la línea que separa un par del siguiente se corta en medio y la ficha sale con la "
                "regla partida en dos. El aire va como relleno del <dt>."
            )

        for par in lista["pares"]:
            dos = dos_columnas(par)
            borde_dt = par["dt"]["bordeAbajo"]
            borde_dd = par["dd"]["bordeAbajo"]

            if par["ultimo"]:
                if borde_dd["ancho"] > 0 and borde_dd["estilo"] != "none":
                    fallos.append(
                        f"{donde} [{id_lista}] «{par['etiqueta']}»: el último par pinta una línea al pie "
                        "de la lista, que queda suelta bajo la ficha."
                    )
                continue

            if dos:
                if borde_dt["ancho"] <= 0 or borde_dt["estilo"] == "none":
                    fallos.append(
                        f"{donde} [{id_lista}] «{par['etiqueta']}»: en dos columnas el <dt> no pinta su "
                        "mitad de la línea y la regla queda a medias, solo bajo el valor."
                    )
                elif borde_dt["color"] != borde_dd["color"]:
                    fallos.append(
                        f"{donde} [{id_lista}] «{par['etiqueta']}»: las dos mitades de la línea tienen "
                        f"colores distintos ({borde_dt['color']} vs {borde_dd['color']})."
                    )
                hueco = par["dd"]["x"] - par["dt"]["der"]
                if hueco > 0.5:
                    fallos.append(
                        f"{donde} [{id_lista}] «{par['etiqueta']}»: quedan {hueco:.1f}px de hueco entre la "
                        "columna de la etiqueta y la del valor: la línea se corta ahí."
                    )
            elif borde_dd["ancho"] <= 0 or borde_dd["estilo"] == "none":
                fallos.append(
                    f"{donde} [{id_lista}] «{par['etiqueta']}»: apilado, el par no se separa del siguiente "
                    "con ninguna línea."
                )


def verificar_dato_ausente(d: dict, donde: str, fallos: list[str]) -> None:
    ficha = por_id(d).get("ficha")
    if ficha is None:
        return

    par = next((p for p in ficha["pares"] if p["etiqueta"] == "Correo"), None)
    if par is None:
        return

    if par["sr"] is None:
        fallos.append(f"{donde}: el dato ausente no lleva texto para el lector de pantalla.")
    else:
        sr = par["sr"]
        if sr["texto"] != "Sin dato":
            fallos.append(f"{donde}: el texto del dato ausente es «{sr['texto']}» y se esperaba «Sin dato».")
        if sr["display"] == "none" or sr["visibilidad"] in ("hidden", "collapse"):
            fallos.append(
                f"{donde}: el «Sin dato» está oculto con display/visibility: sale del árbol de "
                "accesibilidad y el lector vuelve a callar."
            )
        if sr["alto"] > 2 or sr["ancho"] > 2:
            fallos.append(
                f"{donde}: el «Sin dato» mide {sr['ancho']:.0f}×{sr['alto']:.0f}px y se ve en pantalla: "
                "tiene que estar oculto a la vista, no a la voz."
            )

    if par["raya"] is None:
        fallos.append(f"{donde}: el dato ausente no pinta la raya que el funcionario ve.")
    else:
        r = contraste(par["raya"]["color"], par["raya"]["fondo"])
        if r is not None and r < 3.0:
            fallos.append(
                f"{donde}: la raya del dato ausente da {r:.2f}:1 contra su fondo (<3). Está dibujada en "
                "pantalla, la lea o no un lector: le aplica 1.4.11 como objeto gráfico."
            )


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 99.0
    for id_lista, lista in por_id(d).items():
        for par in lista["pares"]:
            for lado in ("dt", "dd"):
                t = par[lado]
                # El valor del badge trae su propio par de colores y lo mide su
                # propio componente en la vitrina; acá se mide el texto del par.
                if not t["texto"] or (lado == "dd" and par["etiqueta"] == "Estado"):
                    continue
                r = contraste(t["color"], t["fondo"])
                if r is None:
                    fallos.append(f"{donde} [{id_lista}] «{par['etiqueta']}» ({lado}): no se pudo leer el color.")
                    continue
                peor = min(peor, r)
                exigido = minimo(t["tamano"], t["peso"])
                if r < exigido:
                    fallos.append(
                        f"{donde} [{id_lista}] «{par['etiqueta']}» ({lado}): {r:.2f}:1 contra su fondo "
                        f"(exige {exigido}:1) [{t['color']} sobre {t['fondo']}]"
                    )
    return peor


def verificar_movimiento(d: dict, donde: str, fallos: list[str]) -> None:
    """El par no anima nada; si alguien le mete movimiento, que respete la preferencia."""
    for id_lista, lista in por_id(d).items():
        for par in lista["pares"]:
            for lado in ("dt", "dd"):
                for clave in ("transicion", "animacion"):
                    valor = par[lado][clave]
                    if re.search(r"(?<![\d.])(?!0s)[\d.]+m?s", valor or ""):
                        fallos.append(
                            f"{donde} [{id_lista}] «{par['etiqueta']}» ({lado}): con movimiento reducido "
                            f"la {clave} computa {valor} y tiene que ser 0 s."
                        )


def verificar_impresion(d: dict, donde: str, fallos: list[str]) -> str:
    """Con `@media print` emulado: lo que el MOTOR computa, no lo que dice el CSS."""
    visto = set()
    for id_lista, lista in por_id(d).items():
        for par in lista["pares"]:
            dt, dd = par["dt"]["corte"], par["dd"]["corte"]
            visto.add(f"dt:{dt['dentro']}/{dt['despues']} dd:{dd['dentro']}")

            if dt["dentro"] != "avoid" and dt["dentroViejo"] != "avoid":
                fallos.append(
                    f"{donde} [{id_lista}] «{par['etiqueta']}»: al imprimir, el <dt> computa "
                    f"break-inside:{dt['dentro']}; la etiqueta se puede partir entre dos hojas."
                )
            if dd["dentro"] != "avoid" and dd["dentroViejo"] != "avoid":
                fallos.append(
                    f"{donde} [{id_lista}] «{par['etiqueta']}»: al imprimir, el <dd> computa "
                    f"break-inside:{dd['dentro']}; un valor de dos líneas se parte entre hojas."
                )
            if dt["despues"] != "avoid" and dt["despuesViejo"] != "avoid":
                fallos.append(
                    f"{donde} [{id_lista}] «{par['etiqueta']}»: al imprimir, el <dt> computa "
                    f"break-after:{dt['despues']}; el salto puede caer JUSTO entre la etiqueta y su "
                    "valor, que es el corte que importa (Jurídica imprime el bloque del panel ARCOP)."
                )
    return "; ".join(sorted(visto))


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(
            f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
            "`vendor/bin/pest --filter=ParesDatoValor`."
        )
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()

            for ruta in paginas:
                for ancho_nombre, ancho, alto in ANCHOS:
                    contexto = navegador.new_context(viewport={"width": ancho, "height": alto})
                    pagina = contexto.new_page()
                    pagina.goto(ruta.as_uri())

                    donde = f"{nombre} {ruta.name} [{ancho_nombre}]"
                    d = pagina.evaluate(SONDA)

                    verificar_estructura(d, donde, fallos)
                    verificar_mono(d, donde, fallos)
                    rejilla = verificar_rejilla(d, donde, ancho, fallos)
                    verificar_linea(d, donde, fallos)
                    verificar_dato_ausente(d, donde, fallos)
                    peor = verificar_contraste(d, donde, fallos)

                    if d["desbordaX"]:
                        fallos.append(f"{donde}: el documento se desplaza en horizontal (WCAG 1.4.10).")

                    # Impresión: el mismo DOM con @media print emulado.
                    pagina.emulate_media(media="print")
                    impresion = verificar_impresion(pagina.evaluate(SONDA), donde + " print", fallos)
                    pagina.emulate_media(media="screen")

                    resumen.append(
                        f"{nombre:<9}{ruta.name:<18}[{ancho_nombre:<10}] {rejilla:<52}"
                        f"peor contraste={peor:.2f}:1  print: {impresion}"
                    )
                    contexto.close()

                # Movimiento reducido, una vez por página: el componente no anima
                # nada y esta es la red por si mañana alguien le pone transición.
                contexto = contexto = navegador.new_context(
                    viewport={"width": 1440, "height": 900}, reduced_motion="reduce"
                )
                pagina = contexto.new_page()
                pagina.goto(ruta.as_uri())
                verificar_movimiento(pagina.evaluate(SONDA), f"{nombre} {ruta.name} [reduce]", fallos)
                contexto.close()

            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(
        f"\nTodo pasa: {len(NAVEGADORES)} navegadores × {len(paginas)} páginas × "
        f"{len(ANCHOS)} anchos, más impresión emulada y movimiento reducido."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
