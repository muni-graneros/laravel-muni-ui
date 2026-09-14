#!/usr/bin/env python3
"""Verificación en navegador de la variante endurecida de `<x-muni::modal>`.

    .venv-a11y/bin/python tests/navegador/dialogo-de-confirmacion.py [build/banco-confirmacion]

La corre `tests/DialogoDeConfirmacionTest.php` después de generar el banco (y se
salta si no está el entorno de la reja, que en CI no se instala). Existe porque
las pruebas de Pest sobre `modal` leen el HTML que emite Blade —que el Cancelar
lleva `autofocus`, que el fondo no lleva `@click`— y nada de eso prueba lo que
importa: el plugin Focus lee `[autofocus]` al inicializarse, y si el `x-init`
llegara tarde el foco caería en el botón destructivo sin que el HTML lo delate.

Se mide en Chromium Y en Firefox, en las cuatro páginas del banco (dos paletas ×
dos temas), con `prefers-reduced-motion: reduce`:

  · al cargar la página nadie roba el foco por el `autofocus` del Cancelar;
  · abierto por teclado (Tab hasta el disparador, Enter), el foco cae en el
    Cancelar, con `:focus-visible` y un outline sólido de 3 px;
  · Tab y Shift+Tab giran entre Cancelar y la acción sin salir del diálogo;
  · Enter sobre el Cancelar cierra sin disparar la acción y devuelve el foco al
    disparador; Escape hace lo mismo;
  · el clic en el fondo NO cierra el alertdialog, y sí cierra el modal clásico;
  · `role`, `aria-modal`, `aria-labelledby` y `aria-describedby` resuelven a
    elementos con el texto esperado, y no hay botón × en la variante endurecida;
  · `x-trap.inert` aisló el resto de la página mientras estuvo abierto;
  · `muni-modal-open` / `muni-modal-close` abren y cierran SOLO el modal con ese
    id, y un id desconocido no abre nada;
  · con `initial-focus` como selector, el foco cae en el campo del consumidor;
  · con la preferencia, el fundido y el pop computan a 0 s TAMBIÉN en `panel-*`,
    donde solo se carga `muni-ui-filament.css` y `--muni-dur` no baja; sin la
    preferencia la transición existe (si no, la comprobación sería vacía);
  · todo texto visible del diálogo pasa WCAG 2.2 AA contra su fondo efectivo;
  · toda parada de teclado mide al menos 24 px de alto.

En Chromium, además, deja capturas del alertdialog abierto en escritorio y en
teléfono para cada página en `<banco>/capturas/`, y comprueba que en 390 px el
panel cabe en la ventana sin desplazamiento horizontal.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "banco-confirmacion"
NAVEGADORES = ("chromium", "firefox")
ESCRITORIO = {"width": 1440, "height": 900}
TELEFONO = {"width": 390, "height": 844}

# Quién tiene el foco, en una cadena comparable.
ACTIVO = """
() => {
  const a = document.activeElement;
  if (!a || a === document.body) return 'body';
  let s = a.id ? '#' + a.id : a.tagName.toLowerCase();
  if (a.hasAttribute('data-muni-cancel')) s += '[data-muni-cancel]';
  if (a.getAttribute('aria-label') === 'Cerrar') s += '[x]';
  return s;
}
"""

# ¿Está visible el panel con ese id?
VISIBLE = """
id => {
  const p = document.getElementById(id);
  if (!p) return false;
  const r = p.getBoundingClientRect();
  return r.height > 0 && getComputedStyle(p).visibility !== 'hidden';
}
"""

# El estado `open` de la raíz Alpine de una pieza del banco, por su API pública.
ESTADO = """
pieza => window.Alpine.$data(document.querySelector('[data-pieza="' + pieza + '"] [x-data]')).open
"""

AISLADOS = "() => document.querySelectorAll('[aria-hidden=\"true\"]').length"

# Lo que se lee del diálogo abierto con estilos computados y el DOM.
DIALOGO = """
id => {
  const panel = document.getElementById(id);
  const dialogo = panel.closest('[role="dialog"],[role="alertdialog"]');
  const ref = attr => {
    const v = dialogo.getAttribute(attr);
    if (!v) return null;
    return v.split(/\\s+/).map(i => { const e = document.getElementById(i); return e ? e.textContent.trim() : null; });
  };
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height, derecha: r.right, izquierda: r.left, arriba: r.top }; };
  const cancel = dialogo.querySelector('[data-muni-cancel]');
  const cs = cancel ? getComputedStyle(cancel) : null;
  return {
    role: dialogo.getAttribute('role'),
    ariaModal: dialogo.getAttribute('aria-modal'),
    labelledby: ref('aria-labelledby'),
    describedby: ref('aria-describedby'),
    tieneX: !!dialogo.querySelector('[aria-label="Cerrar"]'),
    cancelar: cancel ? {
      outlineStyle: cs.outlineStyle, outlineWidth: cs.outlineWidth, outlineColor: cs.outlineColor,
      focoVisible: cancel.matches(':focus-visible'), ...caja(cancel),
    } : null,
    botones: [...dialogo.querySelectorAll('button')].map(b => ({ quien: b.id || b.getAttribute('aria-label') || b.textContent.trim(), ...caja(b) })),
    panel: caja(panel),
    anchoVentana: window.innerWidth,
    altoVentana: window.innerHeight,
    scrollAncho: document.documentElement.scrollWidth,
  };
}
"""


def descentrado(d: dict) -> str | None:
    """None si el panel está centrado en la ventana (2 px de tolerancia); si no,
    la descripción. El contenedor centra con flex, y si ese `display` vive en el
    style inline, x-show lo borra al mostrar y el panel cae arriba a la izquierda."""
    p = d["panel"]
    cx = p["izquierda"] + p["ancho"] / 2
    cy = p["arriba"] + p["alto"] / 2
    dx = cx - d["anchoVentana"] / 2
    dy = cy - d["altoVentana"] / 2
    if abs(dx) > 2 or abs(dy) > 2:
        return f"el panel no está centrado: su centro queda a {dx:+.0f} px en horizontal y {dy:+.0f} px en vertical del centro de la ventana"
    return None

# Texto visible dentro del panel, con las capas de fondo hasta la primera opaca.
CONTRASTE = """
id => {
  const panel = document.getElementById(id);
  const alfa = c => {
    if (c === 'transparent') return 0;
    const m = c.match(/[\\d.]+/g) || [];
    return m.length > 3 ? parseFloat(m[3]) : 1;
  };
  const fondoDe = el => {
    const capas = [];
    let n = el;
    while (n && n !== document.documentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      const a = alfa(bg);
      if (a > 0) { capas.push(bg); if (a >= 1) break; }
      n = n.parentElement;
    }
    if (!capas.length || alfa(capas[capas.length - 1]) < 1) capas.push(getComputedStyle(document.body).backgroundColor);
    return capas;
  };
  return [...panel.querySelectorAll('*')]
    .filter(el => !['svg', 'path', 'SCRIPT', 'STYLE'].includes(el.tagName))
    .map(el => {
      const propio = [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join('').trim();
      if (!propio) return null;
      const c = getComputedStyle(el);
      return { texto: propio.slice(0, 40), color: c.color, tamano: parseFloat(c.fontSize), peso: parseInt(c.fontWeight, 10) || 400, capas: fondoDe(el) };
    })
    .filter(Boolean);
}
"""

# Las clases de transición del modal, sondeadas en un elemento de prueba: solo
# existen en el DOM mientras x-transition las aplica, y eso dura 0 ms con la
# preferencia. Además el valor computado del token en la raíz.
TRANSICION = """
() => {
  const out = {};
  for (const clase of ['muni-fade', 'muni-pop']) {
    const p = document.createElement('div');
    p.className = clase;
    document.body.appendChild(p);
    const c = getComputedStyle(p);
    out[clase] = { propiedad: c.transitionProperty, duracion: c.transitionDuration };
    p.remove();
  }
  out.dur = getComputedStyle(document.documentElement).getPropertyValue('--muni-dur').trim();
  return out;
}
"""


def _rgb(valor: str) -> tuple[float, float, float, float]:
    partes = re.findall(r"[\d.]+", valor)
    if len(partes) < 3:
        raise ValueError(f"color no reconocido: {valor!r}")
    r, g, b = (float(x) for x in partes[:3])
    a = float(partes[3]) if len(partes) > 3 else 1.0
    return r, g, b, a


def _mezclar(frente: str, fondo: tuple[float, float, float]) -> tuple[float, float, float]:
    fr, fg, fb, fa = _rgb(frente)
    br, bg, bb = fondo
    return (fr * fa + br * (1 - fa), fg * fa + bg * (1 - fa), fb * fa + bb * (1 - fa))


def _luminancia(rgb: tuple[float, float, float]) -> float:
    def canal(c: float) -> float:
        c /= 255
        return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

    r, g, b = rgb
    return 0.2126 * canal(r) + 0.7152 * canal(g) + 0.0722 * canal(b)


def contraste(color: str, capas: list[str]) -> float:
    """Ratio WCAG del texto contra el fondo efectivo, componiendo las capas de
    la más lejana (opaca) a la más cercana."""
    fondo = _rgb(capas[-1])[:3]
    for capa in reversed(capas[:-1]):
        fondo = _mezclar(capa, fondo)
    frente = _mezclar(color, fondo)
    l1, l2 = _luminancia(frente), _luminancia(fondo)
    return (max(l1, l2) + 0.05) / (min(l1, l2) + 0.05)


def es_texto_grande(tamano: float, peso: int) -> bool:
    return tamano >= 24 or (tamano >= 18.66 and peso >= 700)


def espera(pagina, expresion: str, arg=None, plazo: int = 3000) -> bool:
    """`wait_for_function` que devuelve False en vez de reventar: el fallo lo
    describe quien llama, con el estado real que encontró."""
    from playwright.sync_api import TimeoutError as PlazoAgotado

    try:
        pagina.wait_for_function(expresion, arg=arg, timeout=plazo)
        return True
    except PlazoAgotado:
        return False


def espera_foco(pagina, esperado: str) -> str:
    """Espera a que el foco llegue a `esperado` (cadena de ACTIVO) y devuelve el activo real."""
    espera(pagina, "esperado => (" + ACTIVO.strip() + ")() === esperado", arg=esperado)
    return pagina.evaluate(ACTIVO)


def espera_visible(pagina, id_panel: str, visible: bool) -> bool:
    return espera(pagina, "([id, v]) => (" + VISIBLE.strip() + ")(id) === v", arg=[id_panel, visible])


def abrir_por_teclado(pagina, fallos: list[str], donde: str) -> None:
    """Tab desde el campo de referencia hasta el disparador del alertdialog, Enter."""
    pagina.click("#banco-primero")
    pagina.keyboard.press("Tab")
    activo = espera_foco(pagina, "#banco-anular-abrir")
    if activo != "#banco-anular-abrir":
        fallos.append(f"{donde}: Tab desde el campo de referencia no llegó al disparador (activo: {activo})")
    pagina.keyboard.press("Enter")


def recorrido(pagina, ruta: Path, nav: str, fallos: list[str]) -> dict:
    """La batería completa sobre una página abierta con movimiento reducido."""
    donde = f"{ruta.name} [{nav}]"
    medida: dict = {}

    if not espera(pagina, "() => window.Alpine && window.Alpine.version"):
        fallos.append(f"{donde}: Alpine no arrancó")
        return medida
    pagina.wait_for_timeout(150)

    # 1. Nadie roba el foco al cargar: el Cancelar lleva `autofocus` en el DOM.
    activo = pagina.evaluate(ACTIVO)
    if activo != "body":
        fallos.append(f"{donde}: al cargar, el foco ya está en {activo}: el `autofocus` del Cancelar robó el foco")
    aislados_antes = pagina.evaluate(AISLADOS)

    # 2. Abierto por teclado: el foco cae en Cancelar, con foco visible.
    abrir_por_teclado(pagina, fallos, donde)
    activo = espera_foco(pagina, "button[data-muni-cancel]")
    if activo != "button[data-muni-cancel]":
        fallos.append(f"{donde}: al abrir el alertdialog el foco no cayó en Cancelar (activo: {activo})")
        return medida
    d = pagina.evaluate(DIALOGO, "banco-anular")
    medida["alertdialog"] = d
    if d["role"] != "alertdialog":
        fallos.append(f"{donde}: role={d['role']!r}, esperaba 'alertdialog'")
    if d["ariaModal"] != "true":
        fallos.append(f"{donde}: aria-modal={d['ariaModal']!r}")
    if not d["labelledby"] or "2024-118" not in (d["labelledby"][0] or ""):
        fallos.append(f"{donde}: aria-labelledby no resuelve al título: {d['labelledby']!r}")
    if not d["describedby"] or "Rosa Contreras" not in (d["describedby"][0] or ""):
        fallos.append(f"{donde}: aria-describedby no resuelve a la pregunta: {d['describedby']!r}")
    if d["tieneX"]:
        fallos.append(f"{donde}: el alertdialog trae botón ×")
    c = d["cancelar"]
    if not c["focoVisible"]:
        fallos.append(f"{donde}: el Cancelar tiene el foco pero no :focus-visible (abierto por teclado)")
    if c["outlineStyle"] != "solid" or c["outlineWidth"] != "3px":
        fallos.append(f"{donde}: outline del Cancelar {c['outlineStyle']} {c['outlineWidth']}, esperaba solid 3px")
    if _rgb(c["outlineColor"])[3] == 0:
        fallos.append(f"{donde}: el outline del Cancelar es transparente")
    for b in d["botones"]:
        if b["alto"] < 24:
            fallos.append(f"{donde}: el botón {b['quien']!r} mide {b['alto']:.0f} px de alto (mínimo 24)")
    if (motivo := descentrado(d)) is not None:
        fallos.append(f"{donde}: {motivo}")
    if pagina.evaluate(AISLADOS) <= aislados_antes:
        fallos.append(f"{donde}: al abrir no se aisló ni un nodo: x-trap.inert no corrió")

    # El árbol ARIA: un alertdialog con su nombre.
    snap = pagina.locator('[role="alertdialog"]').aria_snapshot().strip()
    medida["aria"] = snap
    if not snap.startswith("- alertdialog") or "2024-118" not in snap.splitlines()[0]:
        fallos.append(f"{donde}: el árbol ARIA no expone alertdialog con su nombre: {snap.splitlines()[0]!r}")

    # Contraste de todo texto visible del diálogo contra su fondo efectivo.
    peor = 99.0
    for x in pagina.evaluate(CONTRASTE, "banco-anular"):
        ratio = contraste(x["color"], x["capas"])
        peor = min(peor, ratio)
        minimo = 3.0 if es_texto_grande(x["tamano"], x["peso"]) else 4.5
        if ratio < minimo:
            fallos.append(f"{donde}: «{x['texto']}» a {ratio:.2f}:1 (mínimo {minimo}:1)")
    medida["peorContraste"] = peor

    # 3. La trampa: Tab y Shift+Tab giran entre Cancelar y la acción.
    pagina.keyboard.press("Tab")
    if (a := espera_foco(pagina, "#banco-anular-si")) != "#banco-anular-si":
        fallos.append(f"{donde}: Tab desde Cancelar no llegó a la acción (activo: {a})")
    pagina.keyboard.press("Tab")
    if (a := espera_foco(pagina, "button[data-muni-cancel]")) != "button[data-muni-cancel]":
        fallos.append(f"{donde}: Tab desde la acción se escapó del diálogo (activo: {a})")
    pagina.keyboard.press("Shift+Tab")
    if (a := espera_foco(pagina, "#banco-anular-si")) != "#banco-anular-si":
        fallos.append(f"{donde}: Shift+Tab desde Cancelar se escapó del diálogo (activo: {a})")
    pagina.keyboard.press("Shift+Tab")
    if (a := espera_foco(pagina, "button[data-muni-cancel]")) != "button[data-muni-cancel]":
        fallos.append(f"{donde}: Shift+Tab desde la acción no volvió a Cancelar (activo: {a})")

    # 4. Enter sobre Cancelar cierra, no ejecuta, y devuelve el foco al disparador.
    pagina.evaluate("() => { window.__anulado = 0; document.getElementById('banco-anular-si').addEventListener('click', () => { window.__anulado++; }); }")
    pagina.keyboard.press("Enter")
    if not espera_visible(pagina, "banco-anular", False):
        fallos.append(f"{donde}: Enter sobre Cancelar no cerró el alertdialog")
    if pagina.evaluate("() => window.__anulado") != 0:
        fallos.append(f"{donde}: Enter sobre Cancelar EJECUTÓ la acción destructiva")
    if (a := espera_foco(pagina, "#banco-anular-abrir")) != "#banco-anular-abrir":
        fallos.append(f"{donde}: al cerrar con Enter el foco no volvió al disparador (activo: {a})")
    if pagina.evaluate(AISLADOS) != aislados_antes:
        fallos.append(f"{donde}: al cerrar quedaron nodos aria-hidden de más")

    # 5. El clic en el fondo NO cierra; Escape sí, sin ejecutar.
    pagina.click("#banco-anular-abrir")
    if espera_foco(pagina, "button[data-muni-cancel]") != "button[data-muni-cancel]":
        fallos.append(f"{donde}: al reabrir con el ratón el foco no cayó en Cancelar")
    pagina.mouse.click(10, 10)
    pagina.wait_for_timeout(200)
    if not pagina.evaluate(ESTADO, "alertdialog") or not pagina.evaluate(VISIBLE, "banco-anular"):
        fallos.append(f"{donde}: el clic en el fondo CERRÓ el alertdialog")
    pagina.keyboard.press("Escape")
    if not espera_visible(pagina, "banco-anular", False):
        fallos.append(f"{donde}: Escape no cerró el alertdialog")
    if pagina.evaluate("() => window.__anulado") != 0:
        fallos.append(f"{donde}: Escape EJECUTÓ la acción destructiva")
    if (a := espera_foco(pagina, "#banco-anular-abrir")) != "#banco-anular-abrir":
        fallos.append(f"{donde}: al cerrar con Escape el foco no volvió al disparador (activo: {a})")

    # 6. El modal clásico sigue como siempre: ×, foco en lo primero tabulable, cierra al clic en el fondo.
    pagina.click("#banco-clasico-abrir")
    if (a := espera_foco(pagina, "button[x]")) != "button[x]":
        fallos.append(f"{donde}: el modal clásico ya no enfoca la × primero (activo: {a})")
    dc = pagina.evaluate(DIALOGO, "banco-clasico")
    if dc["role"] != "dialog" or not dc["tieneX"]:
        fallos.append(f"{donde}: el modal clásico cambió: role={dc['role']!r}, ×={dc['tieneX']}")
    pagina.mouse.click(10, 10)
    if not espera_visible(pagina, "banco-clasico", False):
        fallos.append(f"{donde}: el clic en el fondo dejó de cerrar el modal clásico")
    if (a := espera_foco(pagina, "#banco-clasico-abrir")) != "#banco-clasico-abrir":
        fallos.append(f"{donde}: al cerrar el clásico el foco no volvió al disparador (activo: {a})")

    # 7. El evento de navegador abre y cierra SOLO el modal con ese id.
    pagina.evaluate("() => window.dispatchEvent(new CustomEvent('muni-modal-open', { detail: { id: 'banco-anular' } }))")
    if espera_foco(pagina, "button[data-muni-cancel]") != "button[data-muni-cancel]":
        fallos.append(f"{donde}: muni-modal-open no abrió el alertdialog (o no enfocó Cancelar)")
    abiertos = {p: pagina.evaluate(ESTADO, p) for p in ("alertdialog", "clasico", "selector")}
    if abiertos != {"alertdialog": True, "clasico": False, "selector": False}:
        fallos.append(f"{donde}: muni-modal-open con id 'banco-anular' dejó {abiertos}")
    pagina.evaluate("() => window.dispatchEvent(new CustomEvent('muni-modal-close', { detail: { id: 'banco-anular' } }))")
    if not espera_visible(pagina, "banco-anular", False):
        fallos.append(f"{donde}: muni-modal-close no cerró el alertdialog")
    pagina.evaluate("() => window.dispatchEvent(new CustomEvent('muni-modal-open', { detail: { id: 'otro' } }))")
    pagina.wait_for_timeout(150)
    abiertos = {p: pagina.evaluate(ESTADO, p) for p in ("alertdialog", "clasico", "selector")}
    if any(abiertos.values()):
        fallos.append(f"{donde}: muni-modal-open con un id desconocido abrió {abiertos}")

    # 8. initial-focus como selector: el foco cae en el campo del consumidor.
    pagina.click("#banco-motivo-abrir")
    if (a := espera_foco(pagina, "#banco-motivo-campo")) != "#banco-motivo-campo":
        fallos.append(f"{donde}: con initial-focus por selector el foco no cayó en el campo (activo: {a})")
    pagina.keyboard.press("Escape")
    if not espera_visible(pagina, "banco-motivo", False):
        fallos.append(f"{donde}: Escape no cerró el modal con selector")

    # 9. La carrera con el cuadro de animación, FORZADA: x-trap arma la trampa 15 ms
    #    después de abrir y busca el destino en ese instante, pero x-show muestra el
    #    panel recién en el siguiente requestAnimationFrame. Con el cuadro retrasado
    #    120 ms la trampa no encuentra nada tabulable, y sin el respaldo del
    #    componente el foco se queda en el disparador. Se vio de verdad en Firefox
    #    sin forzar nada; acá se fuerza para que no dependa del reloj.
    #    Y no solo sobre el alertdialog: el juez pidió que la variante «arregle
    #    modal para todos», así que el clásico (sin initial-focus, lo primero
    #    tabulable es la ×) y el de selector sufren la misma carrera y tienen que
    #    salir igual de bien parados.
    pagina.reload()
    espera(pagina, "() => window.Alpine && window.Alpine.version")
    pagina.evaluate("() => { window.requestAnimationFrame = cb => setTimeout(() => cb(performance.now()), 120); }")
    carreras = (
        ("#banco-anular-abrir", "banco-anular", "button[data-muni-cancel]", "el alertdialog", "Cancelar"),
        ("#banco-clasico-abrir", "banco-clasico", "button[x]", "el modal clásico", "la ×, lo primero tabulable"),
        ("#banco-motivo-abrir", "banco-motivo", "#banco-motivo-campo", "el modal con selector", "el campo del consumidor"),
    )
    for disparador, panel, destino, cual, nombre_destino in carreras:
        pagina.click(disparador)
        if (a := espera_foco(pagina, destino)) != destino:
            fallos.append(f"{donde}: con el cuadro de animación retrasado, en {cual} el foco no llegó a {nombre_destino} "
                          f"(activo: {a}): x-trap armó la trampa antes de que el panel se mostrara y nadie lo remedió")
        pagina.keyboard.press("Escape")
        if not espera_visible(pagina, panel, False):
            fallos.append(f"{donde}: tras la carrera forzada, Escape no cerró {cual}")
        if (a := espera_foco(pagina, disparador)) != disparador:
            fallos.append(f"{donde}: tras la carrera forzada, Escape no devolvió el foco al disparador de {cual} (activo: {a})")

    # 10. Apilados: el alertdialog abierto SOBRE el drawer (la solicitud abierta al
    #     costado y la anulación encima). Escape cierra solo el de arriba: el
    #     drawer sigue abierto y recibe el foco, y un segundo Escape lo cierra a
    #     él. Un oyente de Escape en window cerraría los dos de un golpe.
    pagina.reload()
    espera(pagina, "() => window.Alpine && window.Alpine.version")
    pagina.wait_for_timeout(150)
    pagina.click("#banco-lateral-abrir")
    if (a := espera_foco(pagina, "button[x]")) != "button[x]":
        fallos.append(f"{donde}: el drawer no se abrió con el foco en su × (activo: {a})")
    pagina.evaluate("() => window.dispatchEvent(new CustomEvent('muni-modal-open', { detail: { id: 'banco-anular' } }))")
    if (a := espera_foco(pagina, "button[data-muni-cancel]")) != "button[data-muni-cancel]":
        fallos.append(f"{donde}: el alertdialog sobre el drawer no tomó el foco (activo: {a})")
    # La trampa del drawer marcó aria-hidden a todos sus hermanos bajo <body>, y el
    # modal teletransportado es uno de ellos: si no se sanea al abrirse, el lector
    # de pantalla no anuncia el diálogo que tiene el foco.
    if pagina.evaluate("() => !!document.querySelector('[role=\"alertdialog\"]').closest('[aria-hidden=\"true\"]')"):
        fallos.append(f"{donde}: apilado sobre el drawer, el alertdialog abierto sigue aria-hidden (lo marcó la trampa del drawer)")
    pagina.keyboard.press("Escape")
    if not espera_visible(pagina, "banco-anular", False):
        fallos.append(f"{donde}: apilado sobre el drawer, Escape no cerró el alertdialog")
    pagina.wait_for_timeout(200)
    if not pagina.evaluate(ESTADO, "vecinos") or not pagina.evaluate(VISIBLE, "banco-lateral-title"):
        fallos.append(f"{donde}: Escape sobre el alertdialog CERRÓ TAMBIÉN el drawer de abajo")
    else:
        if (a := espera_foco(pagina, "button[x]")) != "button[x]":
            fallos.append(f"{donde}: al cerrar el alertdialog el foco no volvió al drawer (activo: {a})")
        pagina.keyboard.press("Escape")
        if not espera_visible(pagina, "banco-lateral-title", False):
            fallos.append(f"{donde}: el segundo Escape no cerró el drawer")
        if (a := espera_foco(pagina, "#banco-lateral-abrir")) != "#banco-lateral-abrir":
            fallos.append(f"{donde}: al cerrar el drawer el foco no volvió a su disparador (activo: {a})")

    return medida


def transiciones(pagina, ruta: Path, nav: str, movimiento: str, fallos: list[str]) -> dict:
    donde = f"{ruta.name} [{nav}, {movimiento}]"
    t = pagina.evaluate(TRANSICION)
    for clase in ("muni-fade", "muni-pop"):
        duraciones = [x.strip() for x in t[clase]["duracion"].split(",")]
        if movimiento == "reduce":
            if any(x != "0s" for x in duraciones):
                fallos.append(f"{donde}: con prefers-reduced-motion .{clase} sigue en {t[clase]['propiedad']} {t[clase]['duracion']}")
        elif all(x == "0s" for x in duraciones):
            fallos.append(f"{donde}: sin la preferencia .{clase} ya está en 0 s: la comprobación sería vacía")
    return t


def capturar(navegador, ruta: Path, carpeta: Path, fallos: list[str]) -> list[str]:
    """Capturas del alertdialog abierto en escritorio y en teléfono (solo Chromium)."""
    lineas = []
    for etiqueta, viewport in (("escritorio", ESCRITORIO), ("telefono", TELEFONO)):
        contexto = navegador.new_context(viewport=viewport, reduced_motion="reduce")
        pagina = contexto.new_page()
        pagina.goto(ruta.as_uri())
        espera(pagina, "() => window.Alpine && window.Alpine.version")
        abrir_por_teclado(pagina, fallos, f"{ruta.name} [captura {etiqueta}]")
        if espera_foco(pagina, "button[data-muni-cancel]") == "button[data-muni-cancel]":
            d = pagina.evaluate(DIALOGO, "banco-anular")
            if d["panel"]["derecha"] > d["anchoVentana"] or d["panel"]["izquierda"] < 0:
                fallos.append(f"{ruta.name} [{etiqueta}]: el panel se sale de la ventana ({d['panel']['izquierda']:.0f}–{d['panel']['derecha']:.0f} de {d['anchoVentana']} px)")
            if d["scrollAncho"] > d["anchoVentana"]:
                fallos.append(f"{ruta.name} [{etiqueta}]: hay desplazamiento horizontal ({d['scrollAncho']} > {d['anchoVentana']} px)")
            if (motivo := descentrado(d)) is not None:
                fallos.append(f"{ruta.name} [{etiqueta}]: {motivo}")
        else:
            fallos.append(f"{ruta.name} [captura {etiqueta}]: no se pudo abrir el alertdialog para la captura")
        destino = carpeta / f"{ruta.stem}-{etiqueta}.png"
        pagina.screenshot(path=str(destino))
        # Relativa al repo cuando el banco está dentro; tal cual si es una copia en otra parte.
        lineas.append(f"    captura {destino.relative_to(RAIZ) if destino.is_relative_to(RAIZ) else destino}")
        contexto.close()
    return lineas


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
              "`vendor/bin/pest --filter=DialogoDeConfirmacion`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []
    capturas = carpeta / "capturas"
    capturas.mkdir(exist_ok=True)

    with sync_playwright() as pw:
        for nav in NAVEGADORES:
            navegador = getattr(pw, nav).launch()
            for ruta in paginas:
                contexto = navegador.new_context(viewport=ESCRITORIO, reduced_motion="reduce")
                pagina = contexto.new_page()
                pagina.goto(ruta.as_uri())
                medida = recorrido(pagina, ruta, nav, fallos)
                t = transiciones(pagina, ruta, nav, "reduce", fallos)
                resumen.append(
                    f"{ruta.name:<18} [{nav:<8}] transición(reduce)={t['muni-pop']['duracion']} "
                    f"--muni-dur={t['dur'] or '(sin token)'}  peor contraste={medida.get('peorContraste', 0):.2f}:1  "
                    f"aria={medida.get('aria', '').splitlines()[0] if medida.get('aria') else '—'}"
                )
                contexto.close()

                contexto = navegador.new_context(viewport=ESCRITORIO, reduced_motion="no-preference")
                pagina = contexto.new_page()
                pagina.goto(ruta.as_uri())
                t = transiciones(pagina, ruta, nav, "no-preference", fallos)
                resumen.append(f"{ruta.name:<18} [{nav:<8}] transición(no-preference)={t['muni-pop']['duracion']}")
                contexto.close()

                if nav == "chromium":
                    resumen.extend(capturar(navegador, ruta, capturas, fallos))
            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa: {len(paginas)} páginas × {len(NAVEGADORES)} navegadores.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
