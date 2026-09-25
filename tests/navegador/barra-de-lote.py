#!/usr/bin/env python3
"""Verificación en navegador de la selección en lote sobre `build/barra-de-lote/`.

    .venv-a11y/bin/python tests/navegador/barra-de-lote.py [build/barra-de-lote]

La corre `tests/BarraDeLoteTest.php` después de generar el banco (y se salta si no
está el entorno de la reja). Las pruebas de Pest leen el TEXTO del fuente; esto
mide lo que solo existe en un navegador, en Chromium Y Firefox, en las cuatro
páginas (claro, oscuro y las dos del panel, que cargan únicamente
`muni-ui-filament.css`):

  · la barra nace visible con UNA solicitud marcada de fábrica, y su cuenta es la
    de las casillas MARCADAS: marcar otra dice «2 solicitudes seleccionadas»;
  · la casilla de cabecera queda INDETERMINADA con 2 de 3 (propiedad del DOM, no
    se puede escribir en HTML), marca las tres al pulsarla y deja de ser mixta;
  · «Quitar selección» desmarca la tabla Y la cabecera se entera: son dos
    componentes que no comparten estado, y es la sincronía que se rompe primero;
  · al vaciar, la barra se oculta y la línea para lectores dice «Sin filas
    seleccionadas.» (la región no se queda muda);
  · Escape sobre una fila deselecciona todo, y Espacio marca la casilla enfocada;
  · la barra acotada con `for` NO cuenta las filas de la otra bandeja;
  · el anillo de foco de la casilla y del botón computa 3 px sólidos con 3:1
    contra el fondo que lo rodea (WCAG 2.2 AA 2.4.7 y 1.4.11);
  · con `prefers-reduced-motion: reduce` la transición del botón computa 0 s, y
    sin la preferencia existe (si no, la comprobación sería vacía);
  · el botón «Aplicar» de respaldo de `segmented` aparece en un formulario SIN
    botón y NO dentro de `filter-bar`, que ya trae el suyo;
  · las FLECHAS ya no envían el formulario (la página no navega) y el clic sí;
  · a 390 px el documento no se desplaza en horizontal (1.4.10);
  · Enter sobre una casilla NO envía el formulario del lote (el envío implícito
    pulsaba la primera acción, «Cerrar», sin confirmación): sobre una fila abre su
    `data-muni-open`, y SIN JavaScript tampoco envía, por la guarda de la barra;
  · la acción de envío sigue enviando con su clic, con su name, su value y los ids;
  · con `x-model` en las casillas —lo que usa wire:model por debajo— «marcar
    todo», «Quitar selección» y Escape llegan al estado del anfitrión;
  · Escape desde la barra deselecciona, desde la ranura no, y al vaciar con el
    foco en la barra el foco vuelve a la cabecera de la tabla;
  · con `stickyColumn` + `selectable` quedan fijas la casilla (0) y la columna que
    identifica (44 px), y la fila con problema pinta el dato y no la casilla;
  · dos `segmented` en un formulario sin botón dan UN «Aplicar», y el `style` del
    consumidor sigue mandando sobre la caja real del grupo;
  · las cuatro capturas (escritorio y teléfono, claro y oscuro) en `capturas/`.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "barra-de-lote"
NAVEGADORES = ("chromium", "firefox")
PAGINAS = ("claro.html", "oscuro.html", "panel-claro.html", "panel-oscuro.html")

ESTADO = r"""
async () => {
  // Alpine agenda sus efectos en una microtarea: se le da un ciclo para pintar.
  await new Promise(r => setTimeout(r, 40));
  const barraA = document.querySelector('[data-caso="lote"] [role="region"][aria-live="polite"]');
  const barraB = document.querySelector('[data-caso="segunda"] [role="region"][aria-live="polite"]');
  const cuerpo = b => b.querySelector('.muni-bulkbar__cuerpo');
  const cabecera = document.querySelector('#tabla-a .muni-dt__pick-all');
  const filas = [...document.querySelectorAll('#tabla-a input[data-muni-pick]')];
  return {
    visibleA: getComputedStyle(cuerpo(barraA)).display !== 'none',
    textoA: barraA.querySelector('.muni-bulkbar__cuenta').textContent.trim(),
    anuncioA: barraA.querySelector('p.muni-sr').textContent.trim(),
    visibleB: getComputedStyle(cuerpo(barraB)).display !== 'none',
    textoB: barraB.querySelector('.muni-bulkbar__cuenta').textContent.trim(),
    marcadas: filas.filter(x => x.checked).length,
    cabeceraMarcada: cabecera.checked,
    cabeceraMixta: cabecera.indeterminate,
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
    clase: a.getAttribute('class') || '',
    pick: a.hasAttribute('data-muni-pick'),
    ancho: c.outlineWidth,
    estilo: c.outlineStyle,
    color: c.outlineColor,
    fondo: fondoDe(a.parentElement || a),
    transicion: c.transitionDuration,
  };
}
"""


def rgb(texto: str) -> tuple[float, float, float] | None:
    m = re.match(r"rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)", texto or "")
    if m:
        return tuple(float(x) for x in m.groups())  # type: ignore[return-value]
    # Firefox y Chromium pueden devolver `color(srgb r g b)` o `oklch(...)` según el
    # token: esos se resuelven pintando el color en un canvas desde la página.
    return None


def luminancia(c: tuple[float, float, float]) -> float:
    def canal(v: float) -> float:
        v /= 255
        return v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4

    r, g, b = (canal(x) for x in c)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def contraste(a: tuple[float, float, float], b: tuple[float, float, float]) -> float:
    la, lb = luminancia(a), luminancia(b)
    claro, oscuro = max(la, lb), min(la, lb)
    return (claro + 0.05) / (oscuro + 0.05)


A_RGB = r"""
(color) => {
  const c = document.createElement('canvas'); c.width = c.height = 1;
  const x = c.getContext('2d'); x.fillStyle = '#000'; x.fillStyle = color; x.fillRect(0, 0, 1, 1);
  const d = x.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2]];
}
"""


def color_rgb(pagina, texto: str) -> tuple[float, float, float]:
    directo = rgb(texto)
    if directo is not None:
        return directo
    return tuple(pagina.evaluate(A_RGB, texto))  # type: ignore[return-value]


def main() -> int:
    banco = Path(sys.argv[1]) if len(sys.argv) > 1 else BANCO_POR_DEFECTO
    if not all((banco / p).is_file() for p in PAGINAS):
        print(f"No está el banco en {banco}: corre antes `pest --filter=BarraDeLote`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []

    def comprobar(condicion: bool, mensaje: str) -> None:
        if not condicion:
            fallos.append(mensaje)

    with sync_playwright() as pw:
        for motor in NAVEGADORES:
            navegador = getattr(pw, motor).launch()
            try:
                for nombre in PAGINAS:
                    url = (banco / nombre).resolve().as_uri()
                    donde = f"{motor}/{nombre}"
                    contexto = navegador.new_context(viewport={"width": 1280, "height": 900})
                    pagina = contexto.new_page()
                    errores: list[str] = []
                    pagina.on("pageerror", lambda e: errores.append(str(e)))
                    pagina.goto(url)
                    pagina.wait_for_timeout(150)

                    # --- La cuenta sale de lo marcado --------------------------------
                    e = pagina.evaluate(ESTADO)
                    comprobar(e["visibleA"], f"{donde}: con una solicitud marcada de fábrica la barra no se ve.")
                    comprobar(e["textoA"] == "1 solicitud seleccionada",
                              f"{donde}: la cuenta inicial dice «{e['textoA']}», no «1 solicitud seleccionada».")
                    comprobar(e["anuncioA"] == "", f"{donde}: al cargar la región ya anuncia «{e['anuncioA']}».")
                    comprobar(not e["visibleB"], f"{donde}: la segunda barra se ve sin nada marcado en su tabla.")
                    comprobar(e["cabeceraMixta"], f"{donde}: con 1 de 3 la cabecera no está indeterminada.")

                    pagina.check('#tabla-a input[value="4822"]')
                    e = pagina.evaluate(ESTADO)
                    comprobar(e["textoA"] == "2 solicitudes seleccionadas",
                              f"{donde}: al marcar otra la cuenta dice «{e['textoA']}».")
                    comprobar(e["cabeceraMixta"] and not e["cabeceraMarcada"],
                              f"{donde}: con 2 de 3 la cabecera no es mixta (mixta={e['cabeceraMixta']}, marcada={e['cabeceraMarcada']}).")

                    # --- El acotado con `for` ----------------------------------------
                    pagina.check('#tabla-b input[value="5101"]')
                    e = pagina.evaluate(ESTADO)
                    comprobar(e["textoA"] == "2 solicitudes seleccionadas",
                              f"{donde}: marcar en la otra bandeja cambió la cuenta de la primera a «{e['textoA']}».")
                    comprobar(e["visibleB"] and e["textoB"] == "1 fila seleccionada",
                              f"{donde}: la segunda barra dice «{e['textoB']}» (visible={e['visibleB']}).")
                    pagina.uncheck('#tabla-b input[value="5101"]')

                    # --- Marcar todo lo visible --------------------------------------
                    pagina.click("#tabla-a .muni-dt__pick-all")
                    e = pagina.evaluate(ESTADO)
                    comprobar(e["marcadas"] == 3 and e["cabeceraMarcada"] and not e["cabeceraMixta"],
                              f"{donde}: la cabecera no marca las tres (marcadas={e['marcadas']}, mixta={e['cabeceraMixta']}).")
                    comprobar(e["textoA"] == "3 solicitudes seleccionadas",
                              f"{donde}: tras marcar todo la barra dice «{e['textoA']}»: la cabecera no avisó a la barra.")

                    # --- Quitar selección: la sincronía entre los dos componentes ----
                    pagina.click('[data-caso="lote"] .muni-bulkbar__quitar')
                    e = pagina.evaluate(ESTADO)
                    comprobar(e["marcadas"] == 0, f"{donde}: «Quitar selección» dejó {e['marcadas']} marcadas.")
                    comprobar(not e["cabeceraMarcada"] and not e["cabeceraMixta"],
                              f"{donde}: tras «Quitar selección» la cabecera sigue marcada o mixta: no se enteró.")
                    comprobar(not e["visibleA"], f"{donde}: con cero marcadas la barra sigue a la vista.")
                    comprobar(e["anuncioA"] == "Sin filas seleccionadas.",
                              f"{donde}: al vaciar, la región no anuncia nada («{e['anuncioA']}»).")

                    # --- Teclado: Espacio marca, Escape deselecciona ------------------
                    # El foco llega POR TECLADO: un focus() programático tras un clic no
                    # enciende :focus-visible en Firefox, y eso sería el navegador, no
                    # el componente.
                    pagina.focus('#tabla-a input[value="4823"]')
                    pagina.keyboard.press("Shift+Tab")
                    pagina.keyboard.press("Tab")
                    pagina.keyboard.press("Space")
                    e = pagina.evaluate(ESTADO)
                    comprobar(e["marcadas"] == 1 and e["textoA"] == "1 solicitud seleccionada",
                              f"{donde}: Espacio sobre la casilla no la marca (marcadas={e['marcadas']}).")

                    f = pagina.evaluate(FOCO)
                    comprobar(f is not None and f["pick"], f"{donde}: el foco no quedó en la casilla de la fila.")
                    if f:
                        comprobar(f["ancho"] == "3px" and f["estilo"] == "solid",
                                  f"{donde}: el foco de la casilla computa {f['ancho']} {f['estilo']}, no 3px solid.")
                        ratio = contraste(color_rgb(pagina, f["color"]), color_rgb(pagina, f["fondo"]))
                        comprobar(ratio >= 3, f"{donde}: el anillo de la casilla da {ratio:.2f}:1, bajo 3:1.")

                    pagina.keyboard.press("Escape")
                    e = pagina.evaluate(ESTADO)
                    comprobar(e["marcadas"] == 0 and not e["visibleA"],
                              f"{donde}: Escape no deseleccionó (marcadas={e['marcadas']}).")

                    # --- Foco del botón de la barra ----------------------------------
                    pagina.check('#tabla-a input[value="4821"]')
                    pagina.focus('#tabla-a input[value="4821"]')
                    # Recorrido real hacia atrás: desde la primera casilla, Shift+Tab
                    # pasa por la cabecera, la región desplazable de la tabla y llega
                    # al botón de la barra, que va antes en el documento.
                    llego = False
                    for _ in range(6):
                        pagina.keyboard.press("Shift+Tab")
                        f = pagina.evaluate(FOCO)
                        if f and "muni-bulkbar__quitar" in f["clase"]:
                            llego = True
                            break
                    comprobar(llego, f"{donde}: con Shift+Tab no se llega a «Quitar selección» desde la fila.")
                    if llego and f:
                        comprobar(f["ancho"] == "3px" and f["estilo"] == "solid",
                                  f"{donde}: el foco del botón computa {f['ancho']} {f['estilo']}.")
                        ratio = contraste(color_rgb(pagina, f["color"]), color_rgb(pagina, f["fondo"]))
                        comprobar(ratio >= 3, f"{donde}: el anillo del botón da {ratio:.2f}:1, bajo 3:1.")

                    # --- El respaldo de segmented ------------------------------------
                    solo = pagina.evaluate(
                        "() => getComputedStyle(document.querySelector('#form-seg .muni-seg-aplicar')).display"
                    )
                    filtro = pagina.evaluate(
                        "() => getComputedStyle(document.querySelector('[data-caso=\"filtro\"] .muni-seg-aplicar')).display"
                    )
                    comprobar(solo != "none", f"{donde}: en un formulario sin botón el «Aplicar» de respaldo no aparece.")
                    comprobar(filtro == "none", f"{donde}: dentro de filter-bar aparece un segundo «Aplicar».")

                    comprobar(not errores, f"{donde}: errores de JavaScript: {errores}")

                    # --- Las flechas no navegan; el clic sí --------------------------
                    antes = pagina.url
                    pagina.focus('#form-seg input[value="todos"]')
                    pagina.keyboard.press("ArrowRight")
                    pagina.wait_for_timeout(400)
                    marcado = pagina.evaluate("() => document.querySelector('#form-seg input:checked').value")
                    comprobar(pagina.url == antes,
                              f"{donde}: una flecha envió el formulario y la página navegó a {pagina.url}.")
                    comprobar(marcado == "lic", f"{donde}: la flecha no movió la selección (marcado={marcado}).")

                    # Tras las flechas, Enter aplica: es el envío implícito del formulario,
                    # que existe porque el grupo tiene un botón de envío a mano (el suyo
                    # de respaldo, o el de la barra de filtros).
                    try:
                        with pagina.expect_navigation(timeout=3000):
                            pagina.keyboard.press("Enter")
                        comprobar("tramite=lic" in pagina.url,
                                  f"{donde}: Enter envió otra cosa (url={pagina.url}).")
                    except Exception:
                        fallos.append(f"{donde}: tras moverse con las flechas, Enter no aplica el filtro.")

                    pagina.goto(url)
                    pagina.wait_for_timeout(150)
                    pagina.focus('[data-caso="filtro"] input[value="todos"]')
                    pagina.keyboard.press("ArrowRight")
                    pagina.wait_for_timeout(300)
                    comprobar(pagina.url == url,
                              f"{donde}: dentro de filter-bar una flecha envió el formulario ({pagina.url}).")
                    try:
                        with pagina.expect_navigation(timeout=3000):
                            pagina.keyboard.press("Enter")
                        comprobar("estado=pend" in pagina.url,
                                  f"{donde}: Enter en filter-bar envió otra cosa (url={pagina.url}).")
                    except Exception:
                        fallos.append(f"{donde}: dentro de filter-bar, Enter no aplica el filtro.")

                    pagina.goto(url)
                    pagina.wait_for_timeout(150)
                    with pagina.expect_navigation(timeout=3000):
                        pagina.click('#form-seg label:has(input[value="pat"])')
                    comprobar("tramite=pat" in pagina.url,
                              f"{donde}: el clic no envió el filtro (url={pagina.url}).")

                    # --- Enter no envía el lote; abre el detalle ----------------------
                    pagina.goto(url)
                    pagina.wait_for_timeout(150)
                    pagina.focus("#tabla-a .muni-dt__pick-all")
                    pagina.keyboard.press("Enter")
                    pagina.wait_for_timeout(400)
                    comprobar(pagina.url == url,
                              f"{donde}: Enter sobre la casilla de cabecera envió el formulario ({pagina.url}).")

                    pagina.focus('#tabla-a input[value="4822"]')
                    pagina.keyboard.press("Enter")
                    pagina.wait_for_timeout(400)
                    comprobar("accion=" not in pagina.url,
                              f"{donde}: Enter sobre la casilla de una fila ejecutó la acción en lote ({pagina.url}).")
                    comprobar(pagina.url.endswith("#detalle-4822"),
                              f"{donde}: Enter sobre la fila no abrió su detalle ({pagina.url}).")
                    comprobar(not pagina.is_checked('#tabla-a input[value="4822"]'),
                              f"{donde}: Enter marcó la casilla: eso es de Espacio.")

                    try:
                        with pagina.expect_navigation(timeout=3000):
                            pagina.click("#accion-cerrar")
                        comprobar("accion=cerrar" in pagina.url and "ids%5B%5D=4821" in pagina.url,
                                  f"{donde}: el clic en la acción no envió su valor y los ids ({pagina.url}).")
                    except Exception:
                        fallos.append(f"{donde}: la guarda del envío implícito rompió el clic en la acción de envío.")

                    # --- x-model en las casillas: el estado del anfitrión se entera ---
                    pagina.goto(url)
                    pagina.wait_for_timeout(150)
                    espejo = lambda: pagina.evaluate(
                        "async () => { await new Promise(r => setTimeout(r, 40)); return document.getElementById('espejo').textContent.trim(); }"
                    )
                    pagina.click("#tabla-c .muni-dt__pick-all")
                    comprobar(espejo() == "6001,6002",
                              f"{donde}: «marcar todo» no llegó al x-model del anfitrión (espejo={espejo()}).")
                    pagina.focus('[data-caso="espejo"] .muni-bulkbar__quitar')
                    pagina.keyboard.press("Enter")
                    comprobar(espejo() == "ninguna",
                              f"{donde}: «Quitar selección» no llegó al x-model del anfitrión (espejo={espejo()}).")
                    activo = pagina.evaluate(
                        "() => document.activeElement && document.activeElement.matches('#tabla-c .muni-dt__pick-all')"
                    )
                    comprobar(activo, f"{donde}: al vaciar desde la barra el foco no volvió a la cabecera de la tabla.")

                    pagina.check('#tabla-c input[value="6001"]')
                    pagina.focus('#tabla-c input[value="6001"]')
                    pagina.keyboard.press("Escape")
                    comprobar(espejo() == "ninguna",
                              f"{donde}: Escape en la tabla no llegó al x-model del anfitrión (espejo={espejo()}).")

                    pagina.check('#tabla-c input[value="6002"]')
                    # La barra aparece en el siguiente ciclo de Alpine: enfocar un botón
                    # todavía oculto no hace nada y el Escape caería en la casilla.
                    pagina.wait_for_selector('[data-caso="espejo"] .muni-bulkbar__acciones button', state="visible")
                    pagina.focus('[data-caso="espejo"] .muni-bulkbar__acciones button')
                    pagina.keyboard.press("Escape")
                    comprobar(espejo() == "6002",
                              f"{donde}: Escape dentro de la ranura borró la selección (espejo={espejo()}).")
                    comprobar(pagina.evaluate("() => document.activeElement.matches('[data-caso=\"espejo\"] .muni-bulkbar__acciones button')"),
                              f"{donde}: el foco no llegó al botón de la ranura: la prueba de Escape sería vacía.")
                    pagina.focus('[data-caso="espejo"] .muni-bulkbar__quitar')
                    pagina.keyboard.press("Escape")
                    comprobar(espejo() == "ninguna",
                              f"{donde}: Escape desde la barra no deseleccionó (espejo={espejo()}).")

                    # --- Columna fija + selección ------------------------------------
                    fija = pagina.evaluate(r"""
                      async () => {
                        const sc = document.getElementById('tabla-d').closest('.muni-dt__scroll');
                        sc.scrollLeft = 400;
                        await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
                        const fila = document.querySelectorAll('#tabla-d tbody tr')[1];
                        const [td1, td2] = fila.children;
                        const base = sc.getBoundingClientRect().left + sc.clientLeft;
                        const sonda = document.createElement('span');
                        sonda.style.color = 'var(--muni-danger-fg)';
                        td2.appendChild(sonda);
                        const peligro = getComputedStyle(sonda).color;
                        sonda.remove();
                        return {
                          scroll: sc.scrollLeft,
                          d1: td1.getBoundingClientRect().left - base,
                          d2: td2.getBoundingClientRect().left - base,
                          w1: td1.getBoundingClientRect().width,
                          color2: getComputedStyle(td2).color,
                          peso2: parseInt(getComputedStyle(td2).fontWeight, 10),
                          sombra1: getComputedStyle(td1).boxShadow,
                          peligro,
                        };
                      }
                    """)
                    comprobar(fija["scroll"] > 0, f"{donde}: la nómina ancha no desplaza: la prueba de la columna fija sería vacía.")
                    comprobar(abs(fija["d1"]) <= 1, f"{donde}: la casilla no queda fija al desplazar (a {fija['d1']:.1f}px).")
                    comprobar(abs(fija["d2"] - 44) <= 1,
                              f"{donde}: la columna que identifica no queda fija a 44px al desplazar (a {fija['d2']:.1f}px).")
                    comprobar(abs(fija["w1"] - 44) <= 1, f"{donde}: la columna de la casilla mide {fija['w1']:.1f}px, no 44.")
                    comprobar(fija["color2"] == fija["peligro"] and fija["peso2"] >= 600,
                              f"{donde}: la fila con problema no pinta el dato (color={fija['color2']}, peso={fija['peso2']}).")
                    comprobar("3px" in fija["sombra1"],
                              f"{donde}: la franja de la fila con problema desapareció del borde ({fija['sombra1']}).")

                    # --- Dos grupos, un «Aplicar»; la caja del consumidor ---------------
                    grupos = pagina.evaluate(
                        "() => [...document.querySelectorAll('#form-dos .muni-seg-aplicar')].map(b => getComputedStyle(b).display !== 'none')"
                    )
                    comprobar(grupos == [False, True],
                              f"{donde}: dos grupos sin botón muestran los «Aplicar» {grupos}; debe verse solo el último.")
                    ancho = pagina.evaluate(r"""
                      () => {
                        const f = document.getElementById('form-ancho');
                        const g = f.querySelector('[role=radiogroup]');
                        return { grupo: g.getBoundingClientRect().width, form: f.getBoundingClientRect().width,
                                 respaldo: getComputedStyle(f.querySelector('.muni-seg-aplicar')).display };
                      }
                    """)
                    comprobar(ancho["grupo"] >= ancho["form"] - 1,
                              f"{donde}: `style=\"width:100%\"` del consumidor ya no llega a la caja real ({ancho['grupo']:.0f} de {ancho['form']:.0f}px).")
                    comprobar(ancho["respaldo"] == "none", f"{donde}: un formulario con su botón muestra además el «Aplicar» de respaldo.")

                    comprobar(not errores, f"{donde}: errores de JavaScript tras los casos nuevos: {errores}")

                    contexto.close()

                # --- Movimiento reducido y ancho de teléfono (una página basta) -----
                for movimiento in ("reduce", "no-preference"):
                    contexto = navegador.new_context(viewport={"width": 390, "height": 800},
                                                     reduced_motion=movimiento)
                    pagina = contexto.new_page()
                    pagina.goto((banco / "claro.html").resolve().as_uri())
                    pagina.wait_for_timeout(150)
                    dur = pagina.evaluate(
                        "() => getComputedStyle(document.querySelector('[data-caso=\"lote\"] .muni-bulkbar__quitar')).transitionDuration"
                    )
                    if movimiento == "reduce":
                        comprobar(all(float(x.strip().rstrip("s") or 0) == 0 for x in dur.split(",")),
                                  f"{motor}: con reduced-motion la transición del botón dura {dur}.")
                    else:
                        comprobar(any(float(x.strip().rstrip("s") or 0) > 0 for x in dur.split(",")),
                                  f"{motor}: sin la preferencia la transición no existe ({dur}): la prueba sería vacía.")
                    desborda = pagina.evaluate(
                        "() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1"
                    )
                    comprobar(not desborda, f"{motor}: a 390 px el documento se desplaza en horizontal.")
                    contexto.close()

                # --- Sin JavaScript: Enter en la casilla tampoco envía el lote ------
                contexto = navegador.new_context(viewport={"width": 1280, "height": 900}, java_script_enabled=False)
                pagina = contexto.new_page()
                sin_js = (banco / "claro.html").resolve().as_uri()
                pagina.goto(sin_js)
                pagina.focus('#tabla-a input[value="4822"]')
                pagina.keyboard.press("Enter")
                pagina.wait_for_timeout(600)
                comprobar(pagina.url == sin_js,
                          f"{motor}: sin JavaScript, Enter sobre una casilla envió el formulario del lote ({pagina.url}).")
                contexto.close()

                # --- Las cuatro capturas: escritorio y teléfono, claro y oscuro -----
                if motor == "chromium":
                    capturas = banco / "capturas"
                    capturas.mkdir(exist_ok=True)
                    for tema in ("claro", "oscuro"):
                        for pantalla, ancho_px in (("escritorio", 1280), ("telefono", 390)):
                            contexto = navegador.new_context(viewport={"width": ancho_px, "height": 900})
                            pagina = contexto.new_page()
                            pagina.goto((banco / f"{tema}.html").resolve().as_uri())
                            pagina.wait_for_timeout(200)
                            pagina.screenshot(path=str(capturas / f"{pantalla}-{tema}.png"), full_page=True)
                            contexto.close()
            finally:
                navegador.close()

    if fallos:
        print("FALLOS:")
        for f in fallos:
            print(" -", f)
        return 1

    print(f"OK: {len(NAVEGADORES)} navegadores × {len(PAGINAS)} páginas, selección, teclado, foco y filtro verificados.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
