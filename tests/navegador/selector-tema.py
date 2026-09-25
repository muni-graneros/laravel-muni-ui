#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::selector-tema>` sobre el banco `build/banco-selector-tema/`.

    .venv-a11y/bin/python tests/navegador/selector-tema.py [build/banco-selector-tema]

La corre `tests/SelectorDeTemaTest.php` después de generar el banco (y se salta
si no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest
leen el HTML que emite Blade y el texto del componente; que elegir con las
flechas aplique el tema sin recargar, que el envío sea uno y no tres, que el
fallo del servidor se diga en texto, que `destroy()` cancele el envío pendiente
y que sin JS el formulario siga sirviendo solo se ve con un navegador real.

El banco son tres páginas sobre `app-shell` —el armazón real, con la
<meta name="color-scheme"> y el atributo derivados del prop— en los tres
estados guardados: `sistema.html` (sin activador), `claro.html`
(`data-muni-theme="light"`) y `oscuro.html` (`data-muni-theme="dark"`). Se
sirven por HTTP en 127.0.0.1 porque `fetch()` no existe sobre `file://`, y la
ruta del anfitrión (`POST /preferencias/tema`) es un mock de Playwright que
anota cada petición y responde 204, o 500 cuando la prueba lo pide.

Cada página se recorre en Chromium Y en Firefox, con el sistema operativo en
claro Y en oscuro (`color_scheme` del contexto) y con movimiento reducido:

  · al cargar: el activador del <html>, el `color-scheme` computado en la raíz y
    el fondo del <body> son los del estado guardado (y, sin activador, los del
    SO); una sola píldora encendida, la marcada; la región de estado vacía;
  · Tab desde el campo de referencia cae en el radio marcado, y la píldora
    entera lleva el anillo de foco: outline sólido de 3 px con color;
  · ArrowRight marca la siguiente y EN EL MISMO TICK cambia el atributo, el
    `color-scheme`, el fondo y la píldora encendida, y dice en texto «aplicado»;
    la página NO navega; a los ~400 ms llega UN POST con `muni-tema=<valor>`,
    `_token`, `Accept: application/json` y `X-Requested-With`, y el texto pasa
    a «guardado»; el botón no queda `aria-busy`;
  · dos flechas seguidas son UN solo POST, con el último valor;
  · con el mock en 500 el tema queda aplicado igual, el texto dice que no se
    pudo guardar y nombra a Guardar; Tab llega al botón con foco visible y
    Enter reintenta por fetch (sin navegar) hasta el «guardado»;
  · Espacio sobre un radio no marcado lo marca y aplica, como las flechas;
  · el radiogroup se anuncia con su nombre visible «Tema»;
  · en los tres estados, todo texto visible del selector pasa WCAG 2.2 AA
    contra su fondo efectivo, y toda parada mide al menos 24 px de alto;
  · con la preferencia, la transición del botón y de la píldora computan a 0 s;
    sin la preferencia existen (si no, la comprobación sería vacía);
  · si Livewire quita el formulario antes del retardo (`form.remove()`),
    `destroy()` cancela el envío: no llega ningún POST;
  · SIN JavaScript, sobre `sistema.html`: al marcar «Oscuro» la píldora
    encendida es una sola y es la marcada —la que dejó el servidor se apaga
    por `:has()`— y Guardar envía el formulario de verdad (POST de navegación
    con `muni-tema=oscuro` y `_token`);
  · cero `pageerror` en todo el recorrido.

En Chromium, además, deja capturas de cada página en escritorio y en teléfono
en `<banco>/capturas/`, y comprueba que a 390 px el formulario cabe en la
ventana sin desplazamiento horizontal.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
import threading
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "banco-selector-tema"
NAVEGADORES = ("chromium", "firefox")
ESCRITORIO = {"width": 1440, "height": 900}
TELEFONO = {"width": 390, "height": 844}
ACCION = re.compile(r"/preferencias/tema$")

# `--muni-bg` de las dos ramas de muni-ui.css, tal como lo computa el navegador.
FONDO = {"light": "rgb(245, 246, 248)", "dark": "rgb(11, 15, 20)"}
# Estado guardado => activador que el anfitrión pone en el <html>.
TEMA_DE = {"sistema": None, "claro": "light", "oscuro": "dark"}
# Orden del DOM: las flechas giran en círculo.
ORDEN = ["sistema", "claro", "oscuro"]

# Lo que se lee de la página con estilos computados y el DOM.
ESTADO = """
() => {
  const form = document.querySelector('form.muni-tema');
  const raiz = document.documentElement;
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height, izquierda: r.left, derecha: r.right }; };
  if (!form) return { sinForm: true, tema: raiz.getAttribute('data-muni-theme'), fondo: getComputedStyle(document.body).backgroundColor };
  const marcado = form.querySelector('input[type=radio]:checked');
  const activo = document.activeElement;
  let quien = 'body';
  if (activo && activo !== document.body) {
    quien = activo.id ? '#' + activo.id : activo.tagName.toLowerCase();
    if (activo.type === 'radio') quien = 'radio=' + activo.value;
    if (activo.classList.contains('muni-tema__guardar')) quien = 'button.muni-tema__guardar';
  }
  const pildoras = [...form.querySelectorAll('.muni-seg')].map(p => {
    const cs = getComputedStyle(p);
    return { valor: p.querySelector('input').value, encendida: p.classList.contains('muni-seg--on'),
      fondo: cs.backgroundColor, outlineStyle: cs.outlineStyle, outlineWidth: cs.outlineWidth, outlineColor: cs.outlineColor,
      transicion: cs.transitionDuration, ...caja(p) };
  });
  const boton = form.querySelector('.muni-tema__guardar');
  const cb = getComputedStyle(boton);
  return {
    sinForm: false,
    tema: raiz.getAttribute('data-muni-theme'),
    colorScheme: getComputedStyle(raiz).colorScheme,
    fondo: getComputedStyle(document.body).backgroundColor,
    marcado: marcado ? marcado.value : null,
    encendidas: pildoras.filter(p => p.encendida).map(p => p.valor),
    estado: (form.querySelector('[role="status"]') || { textContent: '' }).textContent.trim(),
    activo: quien,
    activoFocoVisible: !!(activo && activo !== document.body && activo.matches(':focus-visible')),
    pildoras,
    boton: { busy: boton.getAttribute('aria-busy'), outlineStyle: cb.outlineStyle, outlineWidth: cb.outlineWidth,
      outlineColor: cb.outlineColor, transicion: cb.transitionDuration, ...caja(boton) },
    form: caja(form),
    anchoVentana: window.innerWidth,
    scrollAncho: raiz.scrollWidth,
    alpine: !!(window.Alpine && window.Alpine.version),
  };
}
"""

# Texto visible dentro del selector, con las capas de fondo hasta la primera opaca.
CONTRASTE = """
() => {
  const form = document.querySelector('form.muni-tema');
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
  return [...form.querySelectorAll('*')]
    .filter(el => !['svg', 'path', 'SCRIPT', 'STYLE', 'INPUT'].includes(el.tagName))
    .map(el => {
      const propio = [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join('').trim();
      if (!propio) return null;
      const r = el.getBoundingClientRect();
      if (r.height === 0) return null;
      const c = getComputedStyle(el);
      return { texto: propio.slice(0, 40), color: c.color, tamano: parseFloat(c.fontSize), peso: parseInt(c.fontWeight, 10) || 400, capas: fondoDe(el) };
    })
    .filter(Boolean);
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


def campo(cuerpo: str, nombre: str) -> str | None:
    """El valor de un campo del cuerpo del POST, venga como `multipart/form-data`
    (lo que envía `new FormData(form)` por fetch) o urlencoded (el envío nativo
    del formulario sin JS). Laravel lee las dos igual; el banco también."""
    m = re.search(r'name="' + re.escape(nombre) + r'"\r?\n\r?\n(.*?)\r?\n', cuerpo)
    if m:
        return m.group(1)
    from urllib.parse import parse_qs

    # `keep_blank_values`: el banco no tiene sesión y el `_token` viaja vacío; sin
    # esto parse_qs lo descarta y parecería que el campo no fue.
    return (parse_qs(cuerpo, keep_blank_values=True).get(nombre) or [None])[0]


class Servidor(SimpleHTTPRequestHandler):
    def log_message(self, *args):  # silencio: el resumen es lo único que se imprime
        pass


def servir(carpeta: Path) -> tuple[ThreadingHTTPServer, str]:
    servidor = ThreadingHTTPServer(("127.0.0.1", 0), partial(Servidor, directory=str(carpeta)))
    threading.Thread(target=servidor.serve_forever, daemon=True).start()
    return servidor, f"http://127.0.0.1:{servidor.server_address[1]}"


class Ruta:
    """El mock de la ruta del anfitrión: anota cada POST y responde lo que se le pida."""

    def __init__(self, pagina):
        self.peticiones: list[dict] = []
        self.status = 204
        pagina.route(ACCION, self._atender)

    def _atender(self, ruta, request):
        self.peticiones.append({
            "metodo": request.method,
            "url": request.url,
            "cuerpo": request.post_data or "",
            "accept": request.header_value("accept") or "",
            "xrw": request.header_value("x-requested-with") or "",
            "navegacion": request.is_navigation_request(),
        })
        if request.is_navigation_request():
            # El camino sin JS: el anfitrión respondería `back()`; acá basta una página.
            ruta.fulfill(status=200, content_type="text/html",
                         body="<!doctype html><html lang='es'><title>Guardado</title><p id='banco-guardado'>Preferencia guardada</p></html>")
            return
        ruta.fulfill(status=self.status, body="")


def espera(pagina, expresion: str, arg=None, plazo: int = 3000) -> bool:
    from playwright.sync_api import TimeoutError as PlazoAgotado

    try:
        pagina.wait_for_function(expresion, arg=arg, timeout=plazo)
        return True
    except PlazoAgotado:
        return False


def espera_peticiones(pagina, ruta: Ruta, cuantas: int, plazo_ms: int = 2000) -> bool:
    """Espera a que el mock haya recibido al menos `cuantas` peticiones."""
    for _ in range(max(1, plazo_ms // 50)):
        if len(ruta.peticiones) >= cuantas:
            return True
        pagina.wait_for_timeout(50)
    return len(ruta.peticiones) >= cuantas


def espera_estado(pagina, fragmento: str) -> str:
    espera(pagina, "f => (document.querySelector('form.muni-tema [role=\"status\"]') || {textContent: ''}).textContent.includes(f)", arg=fragmento)
    return pagina.evaluate("() => (document.querySelector('form.muni-tema [role=\"status\"]') || {textContent: ''}).textContent.trim()")


def siguiente(valor: str, paso: int = 1) -> str:
    return ORDEN[(ORDEN.index(valor) + paso) % len(ORDEN)]


def efectivo(valor: str, so: str) -> str:
    """El tema que se ve: el del activador, o el del SO cuando no hay activador."""
    return TEMA_DE[valor] or so


class Recorrido:
    def __init__(self, pagina, donde: str, fallos: list[str]):
        self.p = pagina
        self.donde = donde
        self.fallos = fallos
        self.errores: list[str] = []
        self.navegaciones: list[str] = []
        pagina.on("pageerror", lambda e: self.errores.append(str(e)))
        pagina.on("framenavigated", lambda f: self.navegaciones.append(f.url) if f == pagina.main_frame else None)
        self.ruta = Ruta(pagina)

    def falla(self, mensaje: str) -> None:
        self.fallos.append(f"{self.donde}: {mensaje}")

    def exige(self, condicion: bool, mensaje: str) -> None:
        if not condicion:
            self.falla(mensaje)

    def medir(self) -> dict:
        return self.p.evaluate(ESTADO)

    def comprueba_aplicado(self, e: dict, valor: str, so: str, cuando: str) -> None:
        """El estado visible corresponde al valor `valor`: activador, color-scheme, fondo y píldora."""
        self.exige(e["marcado"] == valor, f"{cuando}: el radio marcado es {e['marcado']!r}, esperaba {valor!r}")
        self.exige(e["tema"] == TEMA_DE[valor], f"{cuando}: data-muni-theme={e['tema']!r}, esperaba {TEMA_DE[valor]!r}")
        self.exige(e["colorScheme"] == efectivo(valor, so),
                   f"{cuando}: color-scheme computado en la raíz {e['colorScheme']!r}, esperaba {efectivo(valor, so)!r} "
                   "(los desplegables nativos y los input date seguirían en el otro tema)")
        self.exige(e["fondo"] == FONDO[efectivo(valor, so)],
                   f"{cuando}: el fondo del body es {e['fondo']} y los tokens de «{valor}» dan {FONDO[efectivo(valor, so)]}")
        self.exige(e["encendidas"] == [valor], f"{cuando}: píldoras encendidas {e['encendidas']}, esperaba solo {[valor]}")

    def comprueba_post(self, peticion: dict, valor: str, cuando: str) -> None:
        self.exige(peticion["metodo"] == "POST", f"{cuando}: la petición fue {peticion['metodo']}, no POST")
        self.exige(not peticion["navegacion"], f"{cuando}: el envío fue una navegación (recargó la página) en vez de fetch")
        self.exige(campo(peticion["cuerpo"], "muni-tema") == valor,
                   f"{cuando}: el cuerpo lleva muni-tema={campo(peticion['cuerpo'], 'muni-tema')!r}, esperaba {valor!r}")
        # El banco no tiene sesión: se exige que el campo `_token` VIAJE, no que tenga contenido.
        self.exige(campo(peticion["cuerpo"], "_token") is not None, f"{cuando}: el cuerpo no lleva el campo _token del CSRF")
        self.exige("application/json" in peticion["accept"], f"{cuando}: Accept={peticion['accept']!r}, un 422 volvería como redirección")
        self.exige(peticion["xrw"] == "XMLHttpRequest", f"{cuando}: falta X-Requested-With: {peticion['xrw']!r}")

    def contraste_actual(self, cuando: str) -> float:
        peor = 99.0
        for x in self.p.evaluate(CONTRASTE):
            ratio = contraste(x["color"], x["capas"])
            peor = min(peor, ratio)
            minimo = 3.0 if es_texto_grande(x["tamano"], x["peso"]) else 4.5
            if ratio < minimo:
                self.falla(f"{cuando}: «{x['texto']}» a {ratio:.2f}:1 (mínimo {minimo}:1)")
        return peor

    def foco_visible(self, outline: dict, que: str) -> None:
        self.exige(outline["outlineStyle"] == "solid" and outline["outlineWidth"] == "3px",
                   f"{que}: outline {outline['outlineStyle']} {outline['outlineWidth']}, esperaba solid 3px")
        try:
            self.exige(_rgb(outline["outlineColor"])[3] > 0, f"{que}: el outline es transparente")
        except ValueError:
            self.falla(f"{que}: outline-color ilegible: {outline['outlineColor']!r}")


def recorrido(pagina, url: str, nombre: str, so: str, nav: str, fallos: list[str]) -> dict:
    """La batería completa sobre una página, con Alpine y movimiento reducido."""
    donde = f"{nombre}.html [{nav}, SO {so}]"
    r = Recorrido(pagina, donde, fallos)
    medida: dict = {"posts": 0, "peor": 99.0}

    pagina.goto(url)
    if not espera(pagina, "() => window.Alpine && window.Alpine.version"):
        r.falla("Alpine no arrancó")
        return medida
    pagina.wait_for_timeout(150)

    # 1. Estado de partida: lo que el servidor dejó, sin tocar nada.
    e = r.medir()
    r.comprueba_aplicado(e, nombre, so, "al cargar")
    r.exige(e["estado"] == "", f"al cargar la región de estado ya dice {e['estado']!r}: una región viva que nace con texto no se anuncia")
    medida["transicion"] = e["boton"]["transicion"]
    medida["pildoraTransicion"] = e["pildoras"][0]["transicion"]
    for p in e["pildoras"]:
        r.exige(p["alto"] >= 24, f"la píldora «{p['valor']}» mide {p['alto']:.0f} px de alto (mínimo 24)")
    r.exige(e["boton"]["alto"] >= 24, f"el botón Guardar mide {e['boton']['alto']:.0f} px de alto (mínimo 24)")
    medida["peor"] = min(medida["peor"], r.contraste_actual(f"contraste en «{nombre}»"))

    # 2. Tab desde el campo de referencia cae en el radio marcado, con anillo en la píldora.
    pagina.click("#banco-primero")
    pagina.keyboard.press("Tab")
    espera(pagina, "() => document.activeElement && document.activeElement.type === 'radio'")
    e = r.medir()
    r.exige(e["activo"] == f"radio={nombre}", f"Tab desde el campo de referencia no cayó en el radio marcado (activo: {e['activo']})")
    r.exige(e["activoFocoVisible"], "el radio tiene el foco por teclado pero no :focus-visible")
    pildora = next((p for p in e["pildoras"] if p["valor"] == nombre), None)
    if pildora:
        r.foco_visible(pildora, f"la píldora «{nombre}» con el foco")

    # 3. ArrowRight: aplica en el mismo tick, no navega, y a los ~400 ms hay UN POST.
    valor = siguiente(nombre)
    antes = len(r.ruta.peticiones)
    navegaciones_antes = len(r.navegaciones)
    pagina.keyboard.press("ArrowRight")
    e = r.medir()
    r.comprueba_aplicado(e, valor, so, "justo después de ArrowRight")
    r.exige(e["estado"].startswith("Tema «") and "aplicado" in e["estado"],
            f"tras ArrowRight la región de estado no dice «aplicado»: {e['estado']!r}")
    r.exige(len(r.ruta.peticiones) == antes, "el POST salió en el mismo tick de la flecha: recorrer las tres opciones serían tres envíos")
    r.exige(espera_peticiones(pagina, r.ruta, antes + 1), "a los 2 s de ArrowRight no llegó ningún POST a la ruta del anfitrión")
    pagina.wait_for_timeout(600)
    nuevas = r.ruta.peticiones[antes:]
    r.exige(len(nuevas) == 1, f"ArrowRight produjo {len(nuevas)} POST, esperaba 1")
    if nuevas:
        r.comprueba_post(nuevas[0], valor, "el POST de ArrowRight")
    estado = espera_estado(pagina, "guardado.")
    r.exige(estado.endswith("guardado."), f"tras el 204 la región de estado no dice «guardado»: {estado!r}")
    r.exige(len(r.navegaciones) == navegaciones_antes, f"la página NAVEGÓ al elegir con la flecha: {r.navegaciones[-1]}")
    e = r.medir()
    r.exige(e["boton"]["busy"] is None, f"el botón Guardar quedó aria-busy={e['boton']['busy']!r} después de guardar")
    r.exige(e["activo"] == f"radio={valor}", f"tras guardar el foco se movió (activo: {e['activo']})")
    medida["peor"] = min(medida["peor"], r.contraste_actual(f"contraste en «{valor}»"))

    # 4. Dos flechas seguidas son UN solo POST, con el último valor.
    antes = len(r.ruta.peticiones)
    pagina.keyboard.press("ArrowRight")
    pagina.keyboard.press("ArrowRight")
    valor = siguiente(valor, 2)
    r.exige(espera_peticiones(pagina, r.ruta, antes + 1), "tras dos flechas seguidas no llegó ningún POST")
    pagina.wait_for_timeout(600)
    nuevas = r.ruta.peticiones[antes:]
    r.exige(len(nuevas) == 1, f"dos flechas seguidas produjeron {len(nuevas)} POST, esperaba 1 (el retardo no agrupa)")
    if nuevas:
        r.comprueba_post(nuevas[-1], valor, "el POST agrupado")
    e = r.medir()
    r.comprueba_aplicado(e, valor, so, "tras dos flechas")
    medida["peor"] = min(medida["peor"], r.contraste_actual(f"contraste en «{valor}»"))

    # 5. El servidor falla: el tema queda aplicado, el texto lo dice y nombra a Guardar.
    r.ruta.status = 500
    antes = len(r.ruta.peticiones)
    pagina.keyboard.press("ArrowLeft")
    valor = siguiente(valor, -1)
    r.exige(espera_peticiones(pagina, r.ruta, antes + 1), "con el mock en 500 no llegó el POST")
    estado = espera_estado(pagina, "no se pudo guardar")
    r.exige("no se pudo guardar" in estado and "Guardar" in estado,
            f"con un 500 la región de estado no dice que no se pudo guardar ni nombra a Guardar: {estado!r}")
    e = r.medir()
    r.comprueba_aplicado(e, valor, so, "tras el 500")
    r.exige(e["boton"]["busy"] is None, "el botón Guardar quedó aria-busy tras el 500")

    # 6. Tab llega a Guardar con foco visible; Enter reintenta por fetch, sin navegar.
    r.ruta.status = 204
    pagina.keyboard.press("Tab")
    espera(pagina, "() => document.activeElement && document.activeElement.classList.contains('muni-tema__guardar')")
    e = r.medir()
    r.exige(e["activo"] == "button.muni-tema__guardar", f"Tab desde el radio no llegó al botón Guardar (activo: {e['activo']})")
    r.foco_visible(e["boton"], "el botón Guardar con el foco")
    antes = len(r.ruta.peticiones)
    navegaciones_antes = len(r.navegaciones)
    pagina.keyboard.press("Enter")
    r.exige(espera_peticiones(pagina, r.ruta, antes + 1), "Enter sobre Guardar no envió nada")
    if len(r.ruta.peticiones) > antes:
        r.comprueba_post(r.ruta.peticiones[antes], valor, "el reintento con Guardar")
    estado = espera_estado(pagina, "guardado.")
    r.exige(estado.endswith("guardado."), f"tras reintentar con Guardar la región no dice «guardado»: {estado!r}")
    r.exige(len(r.navegaciones) == navegaciones_antes, "Enter sobre Guardar NAVEGÓ: el @submit.prevent no interceptó")

    # 7. Espacio sobre un radio no marcado lo marca y aplica.
    otro = siguiente(valor)
    pagina.evaluate("v => document.querySelector('form.muni-tema input[type=radio][value=' + v + ']').focus()", otro)
    antes = len(r.ruta.peticiones)
    pagina.keyboard.press("Space")
    e = r.medir()
    r.comprueba_aplicado(e, otro, so, "justo después de Espacio")
    r.exige(espera_peticiones(pagina, r.ruta, antes + 1), "Espacio sobre el radio no produjo POST")
    espera_estado(pagina, "guardado.")
    valor = otro

    # 8. El grupo se anuncia con su nombre visible.
    snap = pagina.locator('form.muni-tema [role="radiogroup"]').aria_snapshot().strip()
    medida["aria"] = snap.splitlines()[0] if snap else ""
    r.exige(snap.startswith('- radiogroup "Tema"'), f"el árbol ARIA no expone radiogroup «Tema»: {medida['aria']!r}")

    # 9. Movimiento reducido: el botón y la píldora sin transición.
    medida["posts"] = len(r.ruta.peticiones)

    # 10. destroy(): si Livewire quita el formulario antes del retardo, no sale ningún POST.
    pagina.evaluate("v => document.querySelector('form.muni-tema input[type=radio][value=' + v + ']').focus()", valor)
    antes = len(r.ruta.peticiones)
    pagina.keyboard.press("ArrowRight")
    pagina.evaluate("() => document.querySelector('form.muni-tema').remove()")
    pagina.wait_for_timeout(800)
    r.exige(len(r.ruta.peticiones) == antes,
            f"quitado el formulario antes del retardo, salieron {len(r.ruta.peticiones) - antes} POST: destroy() no canceló el temporizador")

    r.exige(not r.errores, f"errores de página: {r.errores[:2]}")
    return medida


def transiciones_sin_preferencia(navegador, url: str, nav: str, fallos: list[str]) -> str:
    """Sin la preferencia la transición existe: si no, la comprobación de 0 s sería vacía."""
    contexto = navegador.new_context(viewport=ESCRITORIO, reduced_motion="no-preference")
    pagina = contexto.new_page()
    pagina.goto(url)
    espera(pagina, "() => window.Alpine && window.Alpine.version")
    e = pagina.evaluate(ESTADO)
    contexto.close()
    if e["boton"]["transicion"] in ("0s", "") or all(x.strip() == "0s" for x in e["boton"]["transicion"].split(",")):
        fallos.append(f"sistema.html [{nav}, no-preference]: el botón ya está en 0 s sin la preferencia: la comprobación sería vacía")
    return f"sistema.html [{nav:<8} no-preference] transición={e['boton']['transicion']}"


def sin_javascript(navegador, url: str, nav: str, fallos: list[str]) -> str:
    """Sin JS: `:has()` mueve la píldora, una sola encendida, y Guardar envía de verdad."""
    donde = f"sistema.html [{nav}, sin JS]"
    # Con movimiento reducido: la píldora cambia de fondo con una transición de
    # 160 ms, y medir a mitad de ella daba «dos encendidas» (0,01 y 0,99 de alfa).
    contexto = navegador.new_context(viewport=ESCRITORIO, java_script_enabled=False, reduced_motion="reduce")
    pagina = contexto.new_page()
    ruta = Ruta(pagina)
    pagina.goto(url)
    pagina.wait_for_timeout(250)

    def encendidas() -> list[str]:
        # Sin JS no hay clase que mirar: se lee el FONDO computado de cada píldora.
        return pagina.evaluate("""() => [...document.querySelectorAll('form.muni-tema .muni-seg')]
            .filter(p => { const c = getComputedStyle(p).backgroundColor; return c !== 'transparent' && !/rgba\\(0, 0, 0, 0\\)/.test(c); })
            .map(p => p.querySelector('input').value)""")

    alpine = pagina.evaluate("() => !!window.Alpine")
    if alpine:
        fallos.append(f"{donde}: el contexto sin JS arrancó Alpine igual: la medición no vale")
    if (antes := encendidas()) != ["sistema"]:
        fallos.append(f"{donde}: al cargar hay {antes} píldoras encendidas, esperaba solo ['sistema']")

    pagina.click('label[for="muni-tema-2"]')
    pagina.wait_for_timeout(250)
    marcado = pagina.evaluate("() => document.querySelector('form.muni-tema input:checked').value")
    if marcado != "oscuro":
        fallos.append(f"{donde}: el clic en «Oscuro» no marcó el radio (marcado: {marcado})")
    if (despues := encendidas()) != ["oscuro"]:
        fallos.append(f"{donde}: tras marcar «Oscuro» las píldoras encendidas son {despues}: la que dejó el servidor sigue prendida")
    if ruta.peticiones:
        fallos.append(f"{donde}: marcar un radio sin JS envió el formulario (no debería: el onchange en línea es JS)")

    pagina.click("button.muni-tema__guardar")
    espera(pagina, "() => !!document.getElementById('banco-guardado')")
    if not ruta.peticiones:
        fallos.append(f"{donde}: Guardar no envió el formulario")
    else:
        p = ruta.peticiones[-1]
        if not (p["navegacion"] and p["metodo"] == "POST"):
            fallos.append(f"{donde}: el envío sin JS no fue un POST de navegación: {p['metodo']} navegación={p['navegacion']}")
        if campo(p["cuerpo"], "muni-tema") != "oscuro" or campo(p["cuerpo"], "_token") is None:
            fallos.append(f"{donde}: el cuerpo del POST sin JS no lleva muni-tema=oscuro y _token: {p['cuerpo'][:80]!r}")
    contexto.close()
    return f"sistema.html [{nav:<8} sin JS       ] píldoras al cargar={antes} tras «Oscuro»={despues} POST de navegación={bool(ruta.peticiones)}"


def capturar(navegador, url: str, nombre: str, carpeta: Path, fallos: list[str]) -> list[str]:
    """Capturas en escritorio y en teléfono (solo Chromium), y el formulario cabe a 390 px."""
    lineas = []
    for etiqueta, viewport in (("escritorio", ESCRITORIO), ("telefono", TELEFONO)):
        contexto = navegador.new_context(viewport=viewport, reduced_motion="reduce")
        pagina = contexto.new_page()
        Ruta(pagina)
        pagina.goto(url)
        espera(pagina, "() => window.Alpine && window.Alpine.version")
        pagina.wait_for_timeout(150)
        e = pagina.evaluate(ESTADO)
        if e["form"]["derecha"] > e["anchoVentana"] or e["form"]["izquierda"] < 0:
            fallos.append(f"{nombre}.html [{etiqueta}]: el formulario se sale de la ventana ({e['form']['izquierda']:.0f}–{e['form']['derecha']:.0f} de {e['anchoVentana']} px)")
        if e["scrollAncho"] > e["anchoVentana"]:
            fallos.append(f"{nombre}.html [{etiqueta}]: hay desplazamiento horizontal ({e['scrollAncho']} > {e['anchoVentana']} px)")
        destino = carpeta / f"{nombre}-{etiqueta}.png"
        pagina.screenshot(path=str(destino))
        lineas.append(f"    captura {destino.relative_to(RAIZ) if destino.is_relative_to(RAIZ) else destino}")
        contexto.close()
    return lineas


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    paginas = [carpeta / f"{n}.html" for n in ORDEN]
    if not all(p.is_file() for p in paginas):
        print(f"Banco incompleto en {carpeta}: faltan páginas; genera primero `vendor/bin/pest --filter=SelectorDeTema`.")
        return 2

    from playwright.sync_api import sync_playwright

    servidor, base = servir(carpeta)
    fallos: list[str] = []
    resumen: list[str] = []
    capturas = carpeta / "capturas"
    capturas.mkdir(exist_ok=True)

    try:
        with sync_playwright() as pw:
            for nav in NAVEGADORES:
                navegador = getattr(pw, nav).launch()
                for ruta in paginas:
                    url = f"{base}/{ruta.name}"
                    for so in ("light", "dark"):
                        contexto = navegador.new_context(viewport=ESCRITORIO, reduced_motion="reduce", color_scheme=so)
                        pagina = contexto.new_page()
                        m = recorrido(pagina, url, ruta.stem, so, nav, fallos)
                        contexto.close()
                        transicion = m.get("transicion", "?")
                        if transicion != "?" and any(x.strip() != "0s" for x in transicion.split(",")):
                            fallos.append(f"{ruta.name} [{nav}, SO {so}]: con prefers-reduced-motion el botón sigue en {transicion}")
                        pt = m.get("pildoraTransicion", "?")
                        if pt != "?" and any(x.strip() != "0s" for x in pt.split(",")):
                            fallos.append(f"{ruta.name} [{nav}, SO {so}]: con prefers-reduced-motion la píldora sigue en {pt}")
                        resumen.append(
                            f"{ruta.name:<13} [{nav:<8} SO {so:<5}] transición(reduce)={transicion} "
                            f"POSTs={m.get('posts', 0)} peor contraste={m.get('peor', 0):.2f}:1 aria={m.get('aria', '—')}"
                        )
                    if nav == "chromium":
                        resumen.extend(capturar(navegador, url, ruta.stem, capturas, fallos))
                resumen.append(transiciones_sin_preferencia(navegador, f"{base}/sistema.html", nav, fallos))
                resumen.append(sin_javascript(navegador, f"{base}/sistema.html", nav, fallos))
                navegador.close()
    finally:
        servidor.shutdown()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa: {len(paginas)} páginas × 2 preferencias del SO × {len(NAVEGADORES)} navegadores, más sin JS y sin la preferencia de movimiento.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
