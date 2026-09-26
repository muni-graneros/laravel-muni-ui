#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::plantilla-pantalla>` sobre `build/plantilla-pantalla/`.

    .venv-a11y/bin/python tests/navegador/plantilla-pantalla.py [build/plantilla-pantalla]

La corre `tests/PlantillaDePantallaTest.php` después de generar el banco (y se
salta si no está el entorno de la reja). Las pruebas de Pest leen el TEXTO del
fuente —que exista `scroll-margin-top`, que el pie vaya antes de `</section>`— y
eso no dice si el navegador lo aplica. Acá se mide, en Chromium Y Firefox, lo que
la ficha llama «teclas obligatorias»:

  · el PRIMER Tab de la página cae en «Saltar al contenido» y ese enlace se VE
    (caja de más de 1x1 y outline de 3 px);
  · Enter mueve el foco a `<main id="muni-contenido">`: el salto mueve el punto
    de lectura, no solo el scroll;
  · desde ahí, Tab recorre migas → acción de cabecera → contenido → pie, sin
    pasar por la barra lateral, y Shift+Tab vuelve por EXACTAMENTE el mismo
    camino;
  · cada parada tiene indicador de foco: outline sólido de 2 px o más;
  · el anillo de foco NUNCA queda debajo de la cabecera fija: desde el final del
    contenido, Shift+Tab sube a un enlace que está una pantalla y media más
    arriba, el navegador desplaza, y el borde superior del outline tiene que
    quedar por debajo de la cabecera. En `sin-margen` —la misma página con la
    reserva anulada— tiene que quedar TAPADO, o la medida no mide nada;
  · el aviso del servidor se ESCRIBE en la región viva cortés después de cargar
    (es lo que el lector anuncia), y el recuadro visible no lleva rol de mensaje;
  · landmarks: un solo `main`, cero `contentinfo` (el pie no es un landmark
    anidado), la región nombrada «Patentes morosas», la navegación «Ruta» y un
    solo `<h1>`;
  · el texto del pie, el del aviso y el de un enlace escrito a mano en el
    contenido pasan 4,5:1 contra su fondo efectivo (el enlace quedaba con el azul
    del navegador en el armazón de panel: 2,05:1 en oscuro);
  · con `prefers-reduced-motion: reduce` la transición del enlace de salto
    computa 0 s;
  · a 390 px el documento no se desplaza en horizontal (1.4.10);
  · la consola queda sin errores (un Alpine roto no avisa de otra forma);
  · en `sin-anunciador` (:announcer="false") no hay región viva de la plantilla
    y el aviso lo expone el navegador con rol `status`: si no, no se anuncia;
  · en `sin-alpine` el primer Tab revela el salto, Enter lleva el foco al
    `<main>`, hay un solo `<h1>`, el aviso y el pie SE VEN, y las regiones vivas
    quedan vacías: lo único que se pierde sin Alpine es el anuncio.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "plantilla-pantalla"
NAVEGADORES = ("chromium", "firefox")
PAGINAS = ("claro.html", "oscuro.html", "publica.html")
AVISO = "No se pudo conectar con Tesorería: el listado puede estar desactualizado."

ACTIVO = r"""
() => {
  const el = document.activeElement;
  if (!el || el === document.body) return { id: '', etiqueta: 'body', clase: '', texto: '', enNavRuta: false, enLateral: false, outlineEstilo: 'none', outlineAncho: 0, outlineOffset: 0, ancho: 0, alto: 0, arriba: 0 };
  const c = getComputedStyle(el);
  const r = el.getBoundingClientRect();
  return {
    id: el.id || '',
    etiqueta: el.tagName.toLowerCase(),
    clase: el.className && el.className.baseVal === undefined ? String(el.className) : '',
    texto: (el.textContent || '').trim().slice(0, 40),
    enNavRuta: !!el.closest('nav[aria-label="Ruta"]'),
    enLateral: !!el.closest('aside, [data-muni-sidebar], .muni-sb'),
    outlineEstilo: c.outlineStyle,
    outlineAncho: parseFloat(c.outlineWidth) || 0,
    outlineOffset: parseFloat(c.outlineOffset) || 0,
    ancho: r.width, alto: r.height, arriba: r.top,
  };
}
"""

# Convierte cualquier color computado a [r,g,b,a] pintándolo en un canvas: los
# navegadores no coinciden en el formato (rgb, oklab, color(srgb …)).
CONTRASTE = r"""
(selector) => {
  const ctx = document.createElement('canvas').getContext('2d');
  const rgba = color => { ctx.clearRect(0, 0, 1, 1); ctx.fillStyle = '#000'; ctx.fillStyle = color; ctx.fillRect(0, 0, 1, 1); return Array.from(ctx.getImageData(0, 0, 1, 1).data); };
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && bg !== 'transparent' && rgba(bg)[3] === 255) return bg;
    }
    return getComputedStyle(document.documentElement).backgroundColor || 'rgb(255,255,255)';
  };
  const lum = ([r, g, b]) => { const f = v => { v /= 255; return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
  const el = document.querySelector(selector);
  if (!el) return null;
  const a = lum(rgba(getComputedStyle(el).color)), b = lum(rgba(fondoDe(el)));
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}
"""

CABECERA = r"""
() => {
  const h = document.querySelector('.muni-ds__top, .muni-topbar');
  return h ? h.getBoundingClientRect().bottom : 0;
}
"""


def recorrer(pagina, fallos: list[str], marca: str) -> bool:
    """El recorrido de teclado completo. Devuelve True si no hubo fallos."""
    antes = len(fallos)

    pagina.mouse.move(0, 0)
    pagina.keyboard.press("Tab")
    primero = pagina.evaluate(ACTIVO)

    if "muni-skip-link" not in primero.get("clase", ""):
        fallos.append(f"{marca}: el primer Tab cae en {primero}, no en el enlace de salto")
    elif primero["ancho"] <= 40 or primero["alto"] <= 20 or primero["outlineAncho"] < 3:
        fallos.append(f"{marca}: el enlace de salto recibe el foco pero no se ve: {primero}")

    pagina.keyboard.press("Enter")
    pagina.wait_for_timeout(50)
    destino = pagina.evaluate(ACTIVO)

    if destino.get("id") != "muni-contenido":
        fallos.append(f"{marca}: Enter en el salto deja el foco en {destino}, no en <main>")

    ida = []

    for _ in range(12):
        pagina.keyboard.press("Tab")
        pagina.wait_for_timeout(20)
        paso = pagina.evaluate(ACTIVO)
        ida.append(paso)

        # Fin del recorrido: el pie propio, el <body>, o el foco que sale del
        # documento hacia el navegador (Firefox deja el último activo repetido).
        if paso.get("id") == "banco-ayuda" or paso.get("etiqueta") == "body":
            break

        if len(ida) > 1 and (ida[-2].get("id"), ida[-2].get("texto")) == (paso.get("id"), paso.get("texto")):
            ida.pop()
            break

    ids = [p.get("id") or p.get("texto") for p in ida]

    if any(p["enLateral"] for p in ida):
        fallos.append(f"{marca}: después del salto el Tab volvió a la barra lateral: {ids}")

    migas = [p for p in ida if p["enNavRuta"]]

    if [m["texto"] for m in migas] != ["Inicio", "Rentas"]:
        fallos.append(f"{marca}: las migas no son las primeras paradas tras el salto: {ids}")

    esperado_tras_migas = ["banco-exportar", "banco-primero", "banco-ultimo"]
    resto = [p.get("id") for p in ida if not p["enNavRuta"]]

    if marca.startswith("publica"):
        # El pie de fábrica no tiene enlace: después del contenido no hay más paradas.
        if resto[:3] != esperado_tras_migas:
            fallos.append(f"{marca}: el orden tras las migas no es acción → contenido: {ids}")
    elif resto[:4] != esperado_tras_migas + ["banco-ayuda"]:
        fallos.append(f"{marca}: el orden tras las migas no es acción → contenido → pie: {ids}")

    for p in ida:
        if p.get("etiqueta") in ("a", "button") and (p["outlineEstilo"] in ("none", "") or p["outlineAncho"] < 2):
            fallos.append(f"{marca}: la parada «{p.get('id') or p.get('texto')}» no tiene outline visible: {p}")

    # Vuelta: Shift+Tab desde la última parada tiene que desandar el mismo camino.
    paradas = [p for p in ida if p.get("etiqueta") in ("a", "button")]
    vuelta = [paradas[-1]] if paradas else []

    # Si el último Tab sacó el foco del documento, un Shift+Tab lo devuelve a la
    # última parada: desde ahí se desanda.
    if paradas and pagina.evaluate(ACTIVO).get("id") != paradas[-1].get("id"):
        pagina.keyboard.press("Shift+Tab")
        pagina.wait_for_timeout(20)

    for _ in range(len(paradas) - 1):
        pagina.keyboard.press("Shift+Tab")
        pagina.wait_for_timeout(20)
        vuelta.append(pagina.evaluate(ACTIVO))

    ida_ids = [p.get("id") or p.get("texto") for p in paradas]
    vuelta_ids = [p.get("id") or p.get("texto") for p in reversed(vuelta)]

    if ida_ids != vuelta_ids:
        fallos.append(f"{marca}: Shift+Tab no vuelve por el mismo camino: ida={ida_ids} vuelta={vuelta_ids}")

    return len(fallos) == antes


def foco_bajo_cabecera(pagina) -> tuple[float, float]:
    """El caso en que el anillo se esconde de verdad.

    Cuando el destino del Tab está lejos, Chromium y Firefox lo CENTRAN y la
    cabecera no lo tapa nunca: esa medida no prueba nada (se probó y la
    contraprueba no distinguía). El defecto aparece cuando el destino ya está
    DENTRO de la ventana pero en la franja que ocupa la cabecera fija: para el
    navegador es visible y no desplaza. Se arma exactamente eso —el primer enlace
    del contenido a 20 px del borde superior, bajo la cabecera— y se tabula hacia
    él desde la acción de cabecera, que queda fuera de la vista.

    Devuelve (borde superior del outline, borde inferior de la cabecera).
    """
    pagina.evaluate("() => document.getElementById('banco-exportar').focus()")
    pagina.evaluate(
        "() => { const el = document.getElementById('banco-primero');"
        " window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 20); }"
    )
    pagina.wait_for_timeout(80)
    pagina.keyboard.press("Tab")
    pagina.wait_for_timeout(150)
    activo = pagina.evaluate(ACTIVO)

    if activo.get("id") != "banco-primero":
        return (-1.0, 0.0)

    borde = activo["arriba"] - activo["outlineOffset"] - activo["outlineAncho"]

    return (borde, pagina.evaluate(CABECERA))


def verificar(banco: Path) -> int:
    from playwright.sync_api import sync_playwright

    fallos: list[str] = []

    with sync_playwright() as p:
        for nombre_nav in NAVEGADORES:
            navegador = getattr(p, nombre_nav).launch()

            for archivo in PAGINAS:
                url = (banco / archivo).as_uri()
                marca = f"{archivo.removesuffix('.html')}/{nombre_nav}"
                contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
                pagina = contexto.new_page()
                errores: list[str] = []
                pagina.on("console", lambda m, e=errores: e.append(m.text) if m.type == "error" else None)
                pagina.on("pageerror", lambda ex, e=errores: e.append(str(ex)))
                pagina.goto(url)
                pagina.wait_for_timeout(400)

                recorrido_ok = recorrer(pagina, fallos, marca)

                borde, cabecera = foco_bajo_cabecera(pagina)

                if borde < 0:
                    fallos.append(f"{marca}: Shift+Tab desde el final no llegó al primer enlace del contenido")
                    recorrido_ok = False
                elif borde < cabecera:
                    fallos.append(f"{marca}: el anillo de foco quedó DEBAJO de la cabecera (borde {borde:.1f} < cabecera {cabecera:.1f})")
                    recorrido_ok = False

                # El anuncio: la región cortés recibe el texto del aviso tras cargar.
                pagina.reload()
                pagina.wait_for_timeout(250)
                cortes = pagina.evaluate("() => [...document.querySelectorAll('[aria-live=\"polite\"]')].map(n => n.textContent.trim())")

                if cortes != [AVISO]:
                    fallos.append(f"{marca}: la región viva cortés no anunció el aviso: {cortes}")
                    recorrido_ok = False

                roles = pagina.evaluate("() => [...document.querySelectorAll('.muni-plantilla__aviso > div')].map(n => n.getAttribute('role'))")

                if not roles:
                    fallos.append(f"{marca}: no apareció el recuadro del aviso")
                    recorrido_ok = False

                if any(r in ("status", "alert") for r in roles):
                    fallos.append(f"{marca}: el recuadro del aviso lleva rol de mensaje y se anunciaría dos veces: {roles}")

                # Landmarks, por el motor de roles del navegador.
                cuentas = {
                    "main": pagina.get_by_role("main").count(),
                    "contentinfo": pagina.get_by_role("contentinfo").count(),
                    "region": pagina.get_by_role("region", name="Patentes morosas", exact=True).count(),
                    "ruta": pagina.get_by_role("navigation", name="Ruta", exact=True).count(),
                    "h1": pagina.locator("h1").count(),
                }

                if cuentas != {"main": 1, "contentinfo": 0, "region": 1, "ruta": 1, "h1": 1}:
                    fallos.append(f"{marca}: landmarks inesperados: {cuentas}")
                    recorrido_ok = False

                for selector in (".muni-plantilla__pie p", ".muni-plantilla__aviso > div", "#banco-primero"):
                    ratio = pagina.evaluate(CONTRASTE, selector)

                    if ratio is None or ratio < 4.5:
                        fallos.append(f"{marca}: «{selector}» a {ratio} contra su fondo (mínimo 4,5:1)")

                if errores:
                    fallos.append(f"{marca}: errores de consola: {errores[:3]}")

                print(f"{marca}: recorrido={'ok' if recorrido_ok else 'FALLA'} borde={borde:.1f} cabecera={cabecera:.1f}")
                contexto.close()

                # 390 px y movimiento reducido, en contexto aparte.
                for movimiento in ("reduce", "no-preference"):
                    contexto = navegador.new_context(viewport={"width": 390, "height": 780}, reduced_motion=movimiento)
                    pagina = contexto.new_page()
                    pagina.goto(url)
                    pagina.wait_for_timeout(250)
                    desborde = pagina.evaluate("() => document.documentElement.scrollWidth - document.documentElement.clientWidth")

                    if desborde > 0:
                        fallos.append(f"{marca}@390/{movimiento}: el documento se desplaza {desborde}px en horizontal")

                    duracion = pagina.evaluate("() => getComputedStyle(document.querySelector('.muni-skip-link')).transitionDuration")
                    en_cero = all(float(d.strip().rstrip("s") or 0) == 0 for d in duracion.split(","))

                    if movimiento == "reduce" and not en_cero:
                        fallos.append(f"{marca}: con movimiento reducido la transición del salto dura {duracion}")
                    if movimiento == "no-preference" and en_cero:
                        fallos.append(f"{marca}: sin preferencia la transición del salto no existe: la comprobación reducida sería vacía")

                    contexto.close()

            # La contraprueba: sin la reserva, el foco TIENE que quedar tapado.
            contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
            pagina = contexto.new_page()
            pagina.goto((banco / "sin-margen.html").as_uri())
            pagina.wait_for_timeout(400)
            # Una tecla antes, para que el foco posterior cuente como de teclado.
            pagina.keyboard.press("Tab")
            borde, cabecera = foco_bajo_cabecera(pagina)

            if 0 <= borde < cabecera:
                print(f"sin-margen/{nombre_nav}: contraprueba=tapado borde={borde:.1f} cabecera={cabecera:.1f}")
            else:
                fallos.append(
                    f"sin-margen/{nombre_nav}: sin la reserva el foco NO quedó tapado (borde {borde:.1f}, cabecera {cabecera:.1f}): "
                    "la medida de las otras páginas no prueba nada"
                )

            contexto.close()

            # Con el anunciador apagado el aviso no puede quedar mudo.
            contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
            pagina = contexto.new_page()
            errores = []
            pagina.on("pageerror", lambda ex, e=errores: e.append(str(ex)))
            pagina.goto((banco / "sin-anunciador.html").as_uri())
            pagina.wait_for_timeout(400)
            vivas = pagina.locator("[aria-live]").count()
            estado = pagina.get_by_role("status").filter(has_text=AVISO).count()
            alerta = pagina.get_by_role("alert").count()

            if vivas == 0 and estado == 1 and alerta == 0 and not errores:
                print(f"sin-anunciador/{nombre_nav}: sin-anunciador=status")
            else:
                fallos.append(f"sin-anunciador/{nombre_nav}: aria-live={vivas} status={estado} alert={alerta} errores={errores[:2]}")

            contexto.close()

            # Sin Alpine: todo lo que no es el anuncio sigue en pie.
            contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
            pagina = contexto.new_page()
            errores = []
            pagina.on("pageerror", lambda ex, e=errores: e.append(str(ex)))
            pagina.goto((banco / "sin-alpine.html").as_uri())
            pagina.wait_for_timeout(400)
            pagina.keyboard.press("Tab")
            primero = pagina.evaluate(ACTIVO)
            pagina.keyboard.press("Enter")
            pagina.wait_for_timeout(100)
            destino = pagina.evaluate("() => document.activeElement && document.activeElement.id")
            visibles = pagina.evaluate(
                "(aviso) => ['.muni-plantilla__aviso', '.muni-plantilla__pie', 'h1'].every(s => { const n = document.querySelector(s); return n && n.getBoundingClientRect().height > 0; })"
                " && document.querySelector('.muni-plantilla__aviso').textContent.includes(aviso)",
                AVISO,
            )
            h1 = pagina.locator("h1").count()
            vivas_vacias = pagina.evaluate("() => [...document.querySelectorAll('[aria-live]')].every(n => n.textContent.trim() === '')")
            salto_ok = "muni-skip-link" in primero["clase"] and primero["ancho"] > 1 and primero["alto"] > 1

            if salto_ok and destino == "muni-contenido" and visibles and h1 == 1 and vivas_vacias and not errores:
                print(f"sin-alpine/{nombre_nav}: sin-alpine=ok (el anuncio del aviso se pierde; lo visible queda)")
            else:
                fallos.append(
                    f"sin-alpine/{nombre_nav}: salto={salto_ok} destino={destino} visibles={visibles} h1={h1} vivas_vacias={vivas_vacias} errores={errores[:2]}"
                )

            contexto.close()
            navegador.close()

    if fallos:
        print("\nFALLOS:")
        for f in fallos:
            print(" -", f)
        return 1

    print("\nTodo pasa.")
    return 0


def main() -> int:
    banco = (Path(sys.argv[1]) if len(sys.argv) > 1 else BANCO_POR_DEFECTO).resolve()

    if not all((banco / a).is_file() for a in (*PAGINAS, "sin-margen.html", "sin-anunciador.html", "sin-alpine.html")):
        print(f"No está el banco en {banco}: corre `vendor/bin/pest --filter=PlantillaDePantalla`.")
        return 2

    return verificar(banco)


if __name__ == "__main__":
    sys.exit(main())
