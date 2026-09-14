#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::sesion-guardia>` sobre el banco `build/sesion-guardia/`.

    .venv-a11y/bin/python tests/navegador/guardia-de-sesion.py [build/sesion-guardia]

La corre `tests/GuardiaDeSesionTest.php` después de generar el banco (y se salta
si no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest
leen el TEXTO del componente; lo que de verdad promete la ficha solo se puede
ver con un reloj que avance y un foco que se mueva. Se sirve por HTTP en
127.0.0.1 —dos pestañas `file://` son orígenes opacos distintos y
BroadcastChannel no las junta— y se recorre en Chromium y en Firefox, en los
dos temas, con el reloj falso de Playwright (`page.clock`), que reemplaza
`Date.now`, `setInterval` y `setTimeout` en la página:

  · fase ACTIVA: nada visible, la región viva vacía, el foco sigue en el campo;
  · fase AVISO (T-5 min, un solo tick tras un salto de cinco minutos: si el
    contador acumulara ticks marcaría 9:59, no 5:00): la tira aparece sin robar
    el foco, la región viva dice «5 minutos» una vez y NO cada segundo, la tira
    mide ≥ 44 px, sus textos pasan 4,5:1 y Tab llega a ella con foco visible;
  · el diálogo abierto desde la tira: `role="alertdialog"`, foco inicial en
    «Seguir conectado», Tab y Shift+Tab atrapados, `<main>` queda aria-hidden y
    la región viva NO; Escape prorroga (POST al mock de `renovar-url`), el
    diálogo cierra, dice «Sesión prorrogada» y el foco VUELVE al campo que el
    funcionario había dejado para pulsar la tira;
  · fase URGENTE (T-1 min): el diálogo se abre solo, foco en «Seguir conectado»,
    la región viva dice «1 minuto»; un 500 del mock muestra el error dentro del
    diálogo (con su contraste) y no cierra nada;
  · T=0: velo con opacidad 1 y el color de `--muni-bg`, la ficha ya no está bajo
    el puntero (`elementFromPoint` sobre el RUT devuelve el diálogo), foco en
    «Ingresar de nuevo», Escape y Tab no hacen nada, y una recarga con la
    expiración ya pasada bloquea al instante;
  · `prefers-reduced-motion: reduce` deja las transiciones en 0 s, y sin la
    preferencia existen (si no, la comprobación sería vacía);
  · a 390 px la tira y el diálogo caben en la ventana;
  · `visibilitychange` recalcula contra `Date.now()` sin esperar al intervalo;
  · cargar ya en el último minuto abre el diálogo desde init() y NO deja rota la
    guardia: la prórroga siguiente sigue devolviendo el foco al campo;
  · dos pestañas: la que llega a T=0 bloquea a la otra por BroadcastChannel, y
    una prórroga le pasa la expiración nueva; sin BroadcastChannel, lo mismo por
    el evento `storage`; con `localStorage` que lanza, la pestaña sigue sola;
  · SIN `renovar-url` (`sin-ruta.html`): la tira no ofrece «Seguir conectado», el
    diálogo urgente abre con el foco en «Entendido», Escape lo cierra y devuelve
    el foco, y —el defecto que encontró el revisor— descartado el diálogo en el
    tramo urgente la tira SIGUE a la vista con el contador hasta T=0, que bloquea
    igual; la tira reabre el diálogo y «Entendido» vuelve al campo;
  · un 204 sin cuerpo del anfitrión: la expiración nueva es ahora + `duracion`
    (SESSION_LIFETIME en segundos, que pasa el anfitrión); sin `duracion`, lo que
    restaba al cargar la página (pesimista, y por eso se documenta);
  · cero `pageerror` en todo el recorrido.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
import threading
from datetime import datetime, timedelta, timezone
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "sesion-guardia"

# El banco declara expira-en="2030-01-01T12:00:00Z". El reloj falso se instala
# diez segundos antes del arranque y, cargada la página, se PAUSA en el arranque
# (T-10 min): tras `install()` el tiempo sigue corriendo de verdad hasta
# `pause_at()`, y sin la pausa cada espera real movería el contador.
EXPIRA = datetime(2030, 1, 1, 12, 0, 0, tzinfo=timezone.utc)
ARRANQUE = EXPIRA - timedelta(minutes=10)
INSTALACION = ARRANQUE - timedelta(seconds=10)
MIN = 60_000
ESPERA_ALPINE = "() => window.Alpine && document.querySelector('[data-teleport-target]')"

DATA = "Alpine.$data(document.getElementById('banco'))"

# Lo que se lee de la página con estilos computados: fase, foco, región viva,
# aria-hidden, velo, y el contraste de cada texto visible contra su fondo
# efectivo (capa por capa hasta el primer fondo opaco, como hace la reja).
SONDA = r"""
() => {
  const d = Alpine.$data(document.getElementById('banco'));
  const parse = s => { const m = (s || '').match(/[\d.]+/g) || []; return [+m[0] || 0, +m[1] || 0, +m[2] || 0, m.length > 3 ? +m[3] : 1]; };
  const fondoEfectivo = el => {
    const capas = [];
    for (let n = el; n; n = n.parentElement) {
      const c = parse(getComputedStyle(n).backgroundColor);
      if (c[3] > 0) capas.push(c);
      if (c[3] >= 1) break;
    }
    let [r, g, b] = [255, 255, 255];
    for (const c of capas.reverse()) { r = c[0] * c[3] + r * (1 - c[3]); g = c[1] * c[3] + g * (1 - c[3]); b = c[2] * c[3] + b * (1 - c[3]); }
    return [r, g, b];
  };
  const lum = ([r, g, b]) => { const f = c => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
  const ratio = (a, b) => { const l1 = lum(a), l2 = lum(b); return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05); };
  const visible = el => { const r = el.getBoundingClientRect(); const c = getComputedStyle(el); return r.width > 0 && r.height > 0 && c.visibility !== 'hidden' && c.display !== 'none'; };
  const caja = el => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y, ancho: r.width, alto: r.height }; };

  const textos = raiz => [...raiz.querySelectorAll('*')]
    .filter(el => visible(el) && !el.closest('.muni-sg__sr') && el.tagName !== 'svg' && el.tagName !== 'path')
    .map(el => {
      const propio = [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join('').trim();
      if (propio === '') return null;
      const c = getComputedStyle(el);
      const fg = parse(c.color);
      const fondo = fondoEfectivo(el);
      const frente = [fg[0] * fg[3] + fondo[0] * (1 - fg[3]), fg[1] * fg[3] + fondo[1] * (1 - fg[3]), fg[2] * fg[3] + fondo[2] * (1 - fg[3])];
      return { texto: propio, tamano: parseFloat(c.fontSize), peso: parseInt(c.fontWeight, 10) || 400, ratio: ratio(frente, fondo) };
    })
    .filter(Boolean);

  const activo = document.activeElement;
  const ca = activo ? getComputedStyle(activo) : null;
  const outline = ca ? parse(ca.outlineColor) : [0, 0, 0, 0];
  const capa = document.querySelector('[data-teleport-target]');
  const aviso = document.getElementById('banco-aviso');
  const dialogo = document.getElementById('banco-dialogo');
  const velo = dialogo ? dialogo.querySelector('.muni-sg__velo') : null;
  const region = document.querySelector('p[role="status"]');
  const rut = document.getElementById('rut');
  const rc = rut.getBoundingClientRect();
  const bajoElRut = document.elementFromPoint(rc.x + rc.width / 2, rc.y + rc.height / 2);
  const sonda = document.createElement('span');
  sonda.style.color = 'var(--muni-bg)';
  document.body.appendChild(sonda);
  const bg = getComputedStyle(sonda).color;
  sonda.remove();

  return {
    fase: d.fase, dialogo: d.dialogo, descartado: d.descartado, restante: d.restante, expiraMs: d.expiraMs,
    duracionMs: d.duracionMs, canalNulo: d.canal === null,
    tiempo: (dialogo.querySelector('.muni-sg__reloj') || {}).textContent || '',
    anuncio: region ? region.textContent : null,
    regionOculta: !!(region && region.closest('[aria-hidden="true"]')),
    mainOculto: document.querySelector('main').getAttribute('aria-hidden'),
    activo: activo ? { id: activo.id || null, texto: (activo.textContent || '').trim().slice(0, 40), clase: activo.className || '', enDialogo: !!activo.closest('#banco-dialogo'), enAviso: !!activo.closest('#banco-aviso') } : null,
    foco: ca ? { estilo: ca.outlineStyle, ancho: ca.outlineWidth, alfa: outline[3], ratioOutline: ratio(outline.slice(0, 3), fondoEfectivo(activo.parentElement || activo)) } : null,
    avisoVisible: !!(aviso && visible(aviso)), avisoCaja: aviso ? caja(aviso) : null,
    avisoBoton: aviso ? caja(aviso.querySelector('.muni-sg__aviso-btn')) : null,
    avisoTextos: aviso && visible(aviso) ? textos(aviso) : [],
    dialogoVisible: !!(dialogo && visible(dialogo)),
    rol: dialogo.getAttribute('role'), modal: dialogo.getAttribute('aria-modal'),
    opaca: dialogo.classList.contains('muni-sg__capa--opaca'),
    veloOpacidad: velo ? getComputedStyle(velo).opacity : null,
    veloFondo: velo ? getComputedStyle(velo).backgroundColor : null,
    veloTransicion: velo ? getComputedStyle(velo).transitionDuration : null,
    fondoTema: bg,
    panelCaja: caja(dialogo.querySelector('.muni-sg__panel')),
    botones: [...dialogo.querySelectorAll('.muni-sg__btn')].filter(visible).map(b => ({ texto: b.textContent.trim(), ...caja(b), transicion: getComputedStyle(b).transitionDuration })),
    dialogoTextos: dialogo && visible(dialogo) ? textos(dialogo.querySelector('.muni-sg__panel')) : [],
    titulo: [...dialogo.querySelectorAll('h2 span')].filter(visible).map(s => s.textContent.trim()).join('|'),
    error: (() => { const e = dialogo.querySelector('.muni-sg__error'); return e && visible(e) ? e.textContent.trim() : ''; })(),
    rutTapado: !!(bajoElRut && bajoElRut.closest('#banco-dialogo')),
    trampaEnCapa: !!(capa && capa.hasAttribute('x-trap.inert.noscroll.noreturn')),
    ventana: { ancho: innerWidth, alto: innerHeight },
  };
}
"""


class Servidor(SimpleHTTPRequestHandler):
    def log_message(self, *args):  # silencio: el resumen es lo único que se imprime
        pass


def servir(carpeta: Path) -> tuple[ThreadingHTTPServer, str]:
    servidor = ThreadingHTTPServer(("127.0.0.1", 0), partial(Servidor, directory=str(carpeta)))
    threading.Thread(target=servidor.serve_forever, daemon=True).start()
    return servidor, f"http://127.0.0.1:{servidor.server_address[1]}"


def es_texto_grande(tamano: float, peso: int) -> bool:
    return tamano >= 24 or (tamano >= 18.66 and peso >= 700)


class Recorrido:
    """Un recorrido sobre una página, acumulando fallos con contexto."""

    def __init__(self, pagina, donde: str, fallos: list[str]):
        self.p = pagina
        self.donde = donde
        self.fallos = fallos
        self.errores: list[str] = []
        pagina.on("pageerror", lambda e: self.errores.append(str(e)))
        self.respuesta = {"status": 200, "body": "{}"}
        pagina.route(re.compile(r"/renovar$"), self._renovar)

    def _renovar(self, ruta, request):
        # El banco no tiene token real: se exige que el POST lleve el header y el
        # campo `_token`, no que tengan contenido. Sin eso, 419, que para el
        # componente significa «el servidor ya cortó».
        if request.method != "POST" or request.header_value("x-csrf-token") is None or "_token=" not in (request.post_data or ""):
            ruta.fulfill(status=419, body="")
            return
        ruta.fulfill(status=self.respuesta["status"], content_type="application/json", body=self.respuesta["body"])

    def falla(self, mensaje: str) -> None:
        self.fallos.append(f"{self.donde}: {mensaje}")

    def exige(self, condicion: bool, mensaje: str) -> None:
        if not condicion:
            self.falla(mensaje)

    def medir(self) -> dict:
        return self.p.evaluate(SONDA)

    def asentar(self, ms: int = 100) -> None:
        """Deja correr los temporizadores falsos. Hace falta después de CADA cambio
        de estado: Alpine oculta al instante pero MUESTRA en un requestAnimationFrame
        (o setTimeout), y los dos están falseados; x-trap activa a los 15 ms y
        focus-trap enfoca en un setTimeout de 0."""
        self.p.clock.run_for(ms)

    def saltar(self, ms: int) -> None:
        """Salta en el tiempo disparando los temporizadores vencidos UNA sola vez
        (`fast_forward`): un contador que acumulara ticks quedaría atrasado."""
        self.p.clock.fast_forward(ms)
        self.asentar(50)

    def saltar_a(self, momento: datetime) -> None:
        ahora = self.p.evaluate("() => Date.now()")
        self.saltar(int(momento.timestamp() * 1000) - int(ahora))

    def esperar(self, expresion: str, intentos: int = 40) -> bool:
        """Espera algo asíncrono de verdad (fetch, BroadcastChannel) sin depender de
        los temporizadores de la página, que están falseados."""
        for _ in range(intentos):
            if self.p.evaluate(f"() => {expresion}"):
                return True
            self.p.wait_for_timeout(50)
        return False

    def contraste(self, textos: list[dict], que: str) -> float:
        peor = 21.0
        for t in textos:
            minimo = 3.0 if es_texto_grande(t["tamano"], t["peso"]) else 4.5
            peor = min(peor, t["ratio"])
            if t["ratio"] < minimo:
                self.falla(f"{que}: «{t['texto']}» a {t['ratio']:.2f}:1 (mínimo {minimo}:1)")
        return peor

    def foco_visible(self, m: dict, que: str) -> None:
        f = m["foco"]
        self.exige(f is not None and f["estilo"] == "solid" and f["ancho"] == "3px" and f["alfa"] > 0,
                   f"{que}: el foco no se ve como outline sólido de 3 px: {f}")
        if f and f["ratioOutline"] < 3.0:
            self.falla(f"{que}: el outline del foco contrasta {f['ratioOutline']:.2f}:1 con lo que lo rodea (mínimo 3:1)")


def abrir_banco(contexto, url: str, donde: str, fallos: list[str]) -> Recorrido:
    contexto.clock.install(time=INSTALACION)
    pagina = contexto.new_page()
    r = Recorrido(pagina, donde, fallos)
    pagina.goto(url)
    pagina.wait_for_function(ESPERA_ALPINE)
    contexto.clock.pause_at(ARRANQUE)
    r.asentar()
    return r


def recorrido_completo(contexto, url: str, donde: str, fallos: list[str]) -> str:
    r = abrir_banco(contexto, url, donde, fallos)
    p = r.p
    p.focus("#rut")

    # ACTIVA
    m = r.medir()
    r.exige(m["fase"] == "activa" and not m["avisoVisible"] and not m["dialogoVisible"], f"al cargar hay algo visible: {m['fase']}")
    r.exige(m["anuncio"] == "", f"la región viva no nace vacía: {m['anuncio']!r}")
    r.exige(m["activo"]["id"] == "rut", f"el foco no está en el RUT: {m['activo']}")
    r.exige(m["trampaEnCapa"], "la trampa no está en la raíz teleportada")
    r.exige(m["rol"] == "alertdialog" and m["modal"] == "true", f"rol/aria-modal: {m['rol']}/{m['modal']}")

    # AVISO: un salto de cinco minutos con UN solo tick.
    r.saltar(5 * MIN)
    m = r.medir()
    r.exige(m["fase"] == "aviso" and m["avisoVisible"] and not m["dialogoVisible"], f"a T-5 min no está la tira: fase={m['fase']} aviso={m['avisoVisible']}")
    r.exige(m["restante"] == 300, f"tras un salto de 5 min el contador marca {m['restante']} s: acumula ticks en vez de recalcular")
    r.exige(m["activo"]["id"] == "rut", f"la tira robó el foco: {m['activo']}")
    r.exige(m["anuncio"] == "Tu sesión termina en 5 minutos.", f"hito de 5 min: {m['anuncio']!r}")
    r.exige(m["mainOculto"] is None, "con la tira, <main> quedó aria-hidden")
    r.exige(m["avisoBoton"]["alto"] >= 44, f"la tira mide {m['avisoBoton']['alto']:.0f} px de alto")
    c = m["avisoCaja"]
    r.exige(c["x"] >= 0 and c["x"] + c["ancho"] <= m["ventana"]["ancho"], f"la tira se sale de la ventana: {c}")
    peor_aviso = r.contraste(m["avisoTextos"], "tira")

    # Tres segundos más de ticks: el contador baja, la región viva no cambia.
    p.clock.run_for(3000)
    m2 = r.medir()
    r.exige(m2["restante"] < m["restante"], f"el contador no baja con los ticks: {m2['restante']}")
    r.exige(m2["anuncio"] == m["anuncio"], f"la región viva habla cada segundo: {m2['anuncio']!r} (restante {m2['restante']})")

    # Teclado hasta la tira, con foco visible.
    p.keyboard.press("Tab")
    p.keyboard.press("Tab")
    m = r.medir()
    r.exige(m["activo"]["enAviso"] and "muni-sg__aviso-btn" in m["activo"]["clase"], f"dos Tab desde el RUT no llegan a la tira: {m['activo']}")
    r.foco_visible(m, "tira")
    p.keyboard.press("Tab")
    m = r.medir()
    r.exige(m["activo"]["enAviso"] and m["activo"]["texto"] == "Seguir conectado", f"el tercer Tab no llega al «Seguir conectado» de la tira: {m['activo']}")
    r.foco_visible(m, "tira/seguir")
    p.keyboard.press("Shift+Tab")
    p.keyboard.press("Enter")
    r.asentar()
    m = r.medir()
    r.exige(m["dialogoVisible"] and m["dialogo"], "Enter sobre la tira no abre el diálogo")
    r.exige(m["activo"]["enDialogo"] and m["activo"]["texto"] == "Seguir conectado", f"el foco inicial no cae en «Seguir conectado»: {m['activo']}")
    r.exige(m["mainOculto"] == "true", "con el diálogo abierto <main> no está aria-hidden (x-trap.inert)")
    r.exige(not m["regionOculta"], "con el diálogo abierto la región viva quedó aria-hidden: los hitos no se dirían")
    r.exige(not m["avisoVisible"], "la tira sigue visible con el diálogo abierto")
    r.exige(m["veloOpacidad"] != "1", f"antes de T=0 el velo ya es opaco ({m['veloOpacidad']})")
    peor_dialogo = r.contraste(m["dialogoTextos"], "diálogo")
    for b in m["botones"]:
        r.exige(b["alto"] >= 44 and b["ancho"] >= 44, f"botón «{b['texto']}» mide {b['ancho']:.0f}×{b['alto']:.0f}")

    # Tab y Shift+Tab atrapados.
    p.keyboard.press("Tab")
    m = r.medir(); r.exige(m["activo"]["texto"] == "Cerrar sesión", f"Tab: {m['activo']}"); r.foco_visible(m, "diálogo/cerrar sesión")
    p.keyboard.press("Tab")
    m = r.medir(); r.exige(m["activo"]["texto"] == "Seguir conectado", f"Tab no da la vuelta: {m['activo']}"); r.foco_visible(m, "diálogo/seguir")
    p.keyboard.press("Shift+Tab")
    m = r.medir(); r.exige(m["activo"]["texto"] == "Cerrar sesión", f"Shift+Tab no da la vuelta: {m['activo']}")
    p.keyboard.press("Shift+Tab")
    m = r.medir(); r.exige(m["activo"]["texto"] == "Seguir conectado", f"Shift+Tab: {m['activo']}")

    # Escape = «Seguir conectado»: POST al mock, que devuelve una expiración nueva.
    nueva = ARRANQUE + timedelta(minutes=15)
    r.respuesta = {"status": 200, "body": '{"expiraEn":"%s"}' % nueva.strftime("%Y-%m-%dT%H:%M:%SZ")}
    p.keyboard.press("Escape")
    r.exige(r.esperar(f"!{DATA}.dialogo"), "Escape no prorrogó: el diálogo sigue abierto")
    r.asentar()
    m = r.medir()
    r.exige(m["fase"] == "activa" and not m["avisoVisible"], f"tras prorrogar no vuelve a ACTIVA: {m['fase']}")
    r.exige(m["expiraMs"] == int(nueva.timestamp() * 1000), f"no adoptó la expiración del servidor: {m['expiraMs']}")
    r.exige(m["anuncio"] == "Sesión prorrogada.", f"no dijo «Sesión prorrogada.»: {m['anuncio']!r}")
    r.exige(m["mainOculto"] is None, "tras cerrar, <main> sigue aria-hidden")
    r.exige(m["activo"]["id"] == "dom", f"el foco no volvió al campo que dejó el funcionario (domicilio): {m['activo']}")

    # URGENTE: se abre solo. Salto exacto a un minuto de la expiración nueva.
    r.saltar_a(nueva - timedelta(minutes=1))
    m = r.medir()
    r.exige(m["fase"] == "urgente" and m["dialogoVisible"], f"a T-1 min no se abre solo: fase={m['fase']} diálogo={m['dialogoVisible']}")
    r.exige(m["restante"] == 60 and m["tiempo"] == "1:00", f"contador a T-1: {m['restante']} {m['tiempo']!r}")
    r.exige(m["activo"]["enDialogo"] and m["activo"]["texto"] == "Seguir conectado", f"foco inicial en URGENTE: {m['activo']}")
    r.exige(m["anuncio"] == "Tu sesión termina en 1 minuto.", f"hito de 1 min: {m['anuncio']!r}")
    r.exige(not m["regionOculta"], "en URGENTE la región viva quedó aria-hidden")

    # Un 500 del anfitrión: error dentro del diálogo, nada se cierra.
    r.respuesta = {"status": 500, "body": ""}
    p.keyboard.press("Escape")
    r.exige(r.esperar(f"{DATA}.error !== ''"), "un 500 no muestra error")
    r.asentar()
    m = r.medir()
    r.exige(m["dialogoVisible"] and "500" in m["error"], f"tras el 500 el diálogo cerró o no dice el código: {m['error']!r}")
    r.exige(m["anuncio"] == m["error"], "el error no se dijo por la región viva")
    r.exige(m["activo"]["enDialogo"] and m["activo"]["texto"] == "Seguir conectado", f"tras el error el foco no sigue en «Seguir conectado» (un disabled lo suelta): {m['activo']}")
    peor_error = r.contraste([t for t in m["dialogoTextos"] if "500" in t["texto"]], "error")

    # T=0: el velo pasa a opaco y tapa la ficha. La transición de opacidad corre
    # en tiempo REAL (el reloj falso no toca las transiciones CSS): se espera más
    # que --muni-dur (160 ms) antes de leer la opacidad computada.
    r.saltar_a(nueva)
    p.wait_for_timeout(250)
    m = r.medir()
    r.exige(m["fase"] == "terminada" and m["opaca"], f"a T=0 no bloquea: fase={m['fase']} opaca={m['opaca']}")
    r.exige(m["veloOpacidad"] == "1", f"el velo de T=0 no es opaco: {m['veloOpacidad']}")
    r.exige(m["veloFondo"] == m["fondoTema"], f"el velo no es el fondo del tema: {m['veloFondo']} vs {m['fondoTema']}")
    r.exige(m["rutTapado"], "el RUT sigue bajo el puntero: elementFromPoint no devuelve el diálogo")
    r.exige(m["titulo"] == "Sesión terminada", f"título en T=0: {m['titulo']!r}")
    r.exige(m["activo"]["texto"] == "Ingresar de nuevo", f"foco en T=0: {m['activo']}")
    r.exige(m["anuncio"].startswith("Tu sesión terminó"), f"anuncio de T=0: {m['anuncio']!r}")
    r.exige(m["error"] == "", f"en T=0 sigue el error viejo a la vista: {m['error']!r}")
    r.exige([b["texto"] for b in m["botones"]] == ["Ingresar de nuevo"], f"en T=0 hay más salidas que «Ingresar de nuevo»: {[b['texto'] for b in m['botones']]}")
    peor_fin = r.contraste(m["dialogoTextos"], "T=0")
    p.keyboard.press("Escape")
    p.keyboard.press("Tab")
    p.keyboard.press("Shift+Tab")
    p.clock.run_for(5000)
    m = r.medir()
    r.exige(m["fase"] == "terminada" and m["dialogoVisible"] and m["activo"]["texto"] == "Ingresar de nuevo", f"Escape/Tab en T=0 movieron algo: fase={m['fase']} foco={m['activo']}")

    # «Ingresar de nuevo» sin ingresar-url recarga; con la expiración ya pasada,
    # la página vuelve a nacer bloqueada.
    with p.expect_navigation():
        p.keyboard.press("Enter")
    p.wait_for_function(ESPERA_ALPINE)
    r.asentar()
    p.wait_for_timeout(250)
    m = r.medir()
    r.exige(m["fase"] == "terminada" and m["opaca"] and m["veloOpacidad"] == "1", f"tras recargar con la sesión vencida no bloquea al instante: {m['fase']} {m['veloOpacidad']}")
    r.exige(m["activo"]["texto"] == "Ingresar de nuevo", f"foco tras recargar vencida: {m['activo']}")

    r.exige(not r.errores, f"errores de página: {r.errores}")
    return (f"{donde:<24} tira={peor_aviso:.2f}:1 diálogo={peor_dialogo:.2f}:1 error={peor_error:.2f}:1 "
            f"T=0={peor_fin:.2f}:1 velo(T=0)={m['veloFondo']} transición(no-preference)={m['veloTransicion']}")


def movimiento_reducido(navegador, url: str, donde: str, fallos: list[str]) -> str:
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion="reduce")
    r = abrir_banco(contexto, url, donde, fallos)
    r.saltar(5 * MIN)
    r.p.evaluate(f"() => {DATA}.abrir()")
    r.asentar()
    m = r.medir()
    r.exige(m["dialogoVisible"], "no abre")
    r.exige(m["veloTransicion"] == "0s", f"con movimiento reducido el velo transiciona {m['veloTransicion']}")
    for b in m["botones"]:
        r.exige(b["transicion"] == "0s", f"con movimiento reducido «{b['texto']}» transiciona {b['transicion']}")
    r.exige(not r.errores, f"errores de página: {r.errores}")
    contexto.close()
    return f"{donde:<24} transición(reduce)={m['veloTransicion']}"


def movil(navegador, url: str, donde: str, fallos: list[str]) -> str:
    contexto = navegador.new_context(viewport={"width": 390, "height": 844}, reduced_motion="no-preference")
    r = abrir_banco(contexto, url, donde, fallos)
    r.saltar(5 * MIN)
    m = r.medir()
    c = m["avisoCaja"]
    r.exige(m["avisoVisible"] and c["x"] >= 0 and c["x"] + c["ancho"] <= 390, f"a 390 px la tira se sale: {c}")
    r.exige(m["avisoBoton"]["alto"] >= 44, f"a 390 px la tira mide {m['avisoBoton']['alto']:.0f} px")
    r.p.evaluate(f"() => {DATA}.abrir()")
    r.asentar()
    m = r.medir()
    pc = m["panelCaja"]
    r.exige(pc["x"] >= 0 and pc["x"] + pc["ancho"] <= 390 and pc["y"] >= 0 and pc["y"] + pc["alto"] <= 844, f"a 390 px el panel se sale: {pc}")
    for b in m["botones"]:
        r.exige(b["alto"] >= 44 and b["ancho"] >= 44, f"a 390 px «{b['texto']}» mide {b['ancho']:.0f}×{b['alto']:.0f}")
    r.exige(not r.errores, f"errores de página: {r.errores}")
    contexto.close()
    return f"{donde:<24} tira={c['ancho']:.0f}px panel={pc['ancho']:.0f}px"


def visibilidad(navegador, url: str, donde: str, fallos: list[str]) -> str:
    """Pestaña de fondo: el reloj del sistema salta sin disparar el intervalo y
    `visibilitychange` tiene que recalcular contra Date.now()."""
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    r = abrir_banco(contexto, url, donde, fallos)
    r.p.clock.set_system_time(EXPIRA - timedelta(seconds=30))
    m = r.medir()
    r.exige(m["fase"] == "activa", f"set_system_time disparó el intervalo: {m['fase']}")
    r.p.evaluate("() => document.dispatchEvent(new Event('visibilitychange'))")
    r.asentar()
    m = r.medir()
    r.exige(m["fase"] == "urgente" and m["restante"] == 30 and m["dialogoVisible"], f"al volver la pestaña no recalculó: fase={m['fase']} restante={m['restante']}")
    r.exige(not r.errores, f"errores de página: {r.errores}")
    contexto.close()
    return f"{donde:<24} restante tras visibilitychange={m['restante']}"


def pestanas(navegador, url: str, donde: str, fallos: list[str], sin_broadcast: bool) -> str:
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    if sin_broadcast:
        contexto.add_init_script("delete window.BroadcastChannel;")
    contexto.clock.install(time=INSTALACION)
    a = Recorrido(contexto.new_page(), donde + " A", fallos)
    b = Recorrido(contexto.new_page(), donde + " B", fallos)
    for r in (a, b):
        r.p.goto(url)
        r.p.wait_for_function(ESPERA_ALPINE)
    contexto.clock.pause_at(ARRANQUE)
    a.asentar()
    ma = a.medir()
    a.exige(ma["canalNulo"] == sin_broadcast, f"canal nulo={ma['canalNulo']}, esperaba {sin_broadcast}")

    # Una prórroga en A le pasa la expiración nueva a B.
    nueva = ARRANQUE + timedelta(minutes=30)
    a.respuesta = {"status": 200, "body": '{"expiraEn":"%s"}' % nueva.strftime("%Y-%m-%dT%H:%M:%SZ")}
    a.p.evaluate(f"() => {DATA}.renovar()")
    b.exige(b.esperar(f"{DATA}.expiraMs === {int(nueva.timestamp() * 1000)}"), "B no adoptó la expiración que prorrogó A")

    # A llega a T=0 y bloquea a B.
    a.p.evaluate(f"() => {DATA}.terminar(true)")
    b.exige(b.esperar(f"{DATA}.fase === 'terminada'"), "B no se bloqueó cuando A llegó a T=0")
    b.asentar()
    mb = b.medir()
    b.exige(mb["opaca"] and mb["veloOpacidad"] == "1" and mb["activo"]["texto"] == "Ingresar de nuevo", f"B bloqueada a medias: {mb['opaca']} {mb['veloOpacidad']} {mb['activo']}")
    for r in (a, b):
        r.exige(not r.errores, f"errores de página: {r.errores}")
    contexto.close()
    return f"{donde:<24} canal={'storage' if sin_broadcast else 'BroadcastChannel'} B.expira adoptada y B bloqueada"


def carga_tardia(navegador, url: str, donde: str, fallos: list[str]) -> str:
    """La página carga ya en el último minuto: el diálogo se abre en init(), antes de
    que exista el clon teleportado. Con $refs eso dejaba las referencias vacías para
    toda la sesión (Alpine las cachea en el primer acceso) y el foco nunca volvía
    al campo en la prórroga siguiente. Se comprueba la prórroga desde la carga y,
    después, una segunda urgencia con retorno al campo."""
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    contexto.clock.install(time=EXPIRA - timedelta(seconds=60))
    pagina = contexto.new_page()
    r = Recorrido(pagina, donde, fallos)
    pagina.goto(url)
    pagina.wait_for_function(ESPERA_ALPINE)
    contexto.clock.pause_at(EXPIRA - timedelta(seconds=45))
    r.asentar()
    m = r.medir()
    r.exige(m["fase"] == "urgente" and m["dialogoVisible"], f"cargando a T-45 s no abre el diálogo: {m['fase']}")
    r.exige(m["activo"]["enDialogo"] and m["activo"]["texto"] == "Seguir conectado", f"foco al cargar tarde: {m['activo']}")

    nueva = EXPIRA + timedelta(minutes=10)
    r.respuesta = {"status": 200, "body": '{"expiraEn":"%s"}' % nueva.strftime("%Y-%m-%dT%H:%M:%SZ")}
    pagina.keyboard.press("Escape")
    r.exige(r.esperar(f"!{DATA}.dialogo"), "la prórroga desde la carga tardía no cierra el diálogo")
    r.asentar()

    pagina.focus("#dom")
    r.saltar_a(nueva - timedelta(minutes=1))
    m = r.medir()
    r.exige(m["fase"] == "urgente" and m["activo"]["texto"] == "Seguir conectado", f"segunda urgencia: {m['fase']} {m['activo']}")
    nueva2 = nueva + timedelta(minutes=10)
    r.respuesta = {"status": 200, "body": '{"expiraEn":"%s"}' % nueva2.strftime("%Y-%m-%dT%H:%M:%SZ")}
    pagina.keyboard.press("Escape")
    r.exige(r.esperar(f"!{DATA}.dialogo"), "la segunda prórroga no cierra el diálogo")
    r.asentar()
    m = r.medir()
    r.exige(m["activo"]["id"] == "dom", f"tras cargar tarde, la prórroga siguiente ya no devuelve el foco al campo: {m['activo']}")
    r.exige(not r.errores, f"errores de página: {r.errores}")
    contexto.close()
    return f"{donde:<24} abre en init() y la prórroga siguiente devuelve el foco a #{m['activo']['id']}"


def sin_almacenamiento(navegador, url: str, donde: str, fallos: list[str]) -> str:
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    contexto.add_init_script(
        "delete window.BroadcastChannel;"
        "Object.defineProperty(window, 'localStorage', { get() { throw new Error('bloqueado'); } });"
    )
    r = abrir_banco(contexto, url, donde, fallos)
    r.saltar(5 * MIN)
    m = r.medir()
    r.exige(m["fase"] == "aviso" and m["avisoVisible"], f"sin almacenamiento la guardia murió: {m['fase']}")
    r.saltar_a(EXPIRA)
    m = r.medir()
    r.exige(m["fase"] == "terminada" and m["opaca"], f"sin almacenamiento no llega a T=0: {m['fase']}")
    r.exige(not r.errores, f"errores de página: {r.errores}")
    contexto.close()
    return f"{donde:<24} sin BroadcastChannel ni localStorage: {m['fase']}"


def sin_ruta(navegador, url: str, donde: str, fallos: list[str]) -> str:
    """Sin `renovar-url` el componente avisa y bloquea, no prorroga. Es el único
    camino por el que el funcionario puede DESCARTAR el diálogo («Entendido» o
    Escape llaman a cerrar()), y ahí estaba el defecto que reprodujo el revisor:
    con la tira atada a `fase === 'aviso'`, descartar en URGENTE dejaba el último
    minuto sin contador ni aviso visible hasta el velo opaco."""
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion="no-preference")
    r = abrir_banco(contexto, url, donde, fallos)
    p = r.p
    p.focus("#rut")

    r.saltar(5 * MIN)
    m = r.medir()
    r.exige(m["fase"] == "aviso" and m["avisoVisible"], f"a T-5 min no está la tira: fase={m['fase']} aviso={m['avisoVisible']}")
    r.exige(all(t["texto"] != "Seguir conectado" for t in m["avisoTextos"]), "sin renovar-url la tira ofrece «Seguir conectado»: no hay ruta a la que pegar")
    r.exige(m["activo"]["id"] == "rut", f"la tira robó el foco: {m['activo']}")

    # URGENTE: se abre solo, con el foco en «Entendido» y sin prórroga a la vista.
    r.saltar_a(EXPIRA - timedelta(seconds=60))
    m = r.medir()
    r.exige(m["fase"] == "urgente" and m["dialogoVisible"], f"a T-1 min no se abre solo: fase={m['fase']} diálogo={m['dialogoVisible']}")
    r.exige(m["activo"]["enDialogo"] and m["activo"]["texto"] == "Entendido", f"sin ruta el foco inicial no cae en «Entendido»: {m['activo']}")
    r.exige([b["texto"] for b in m["botones"]] == ["Entendido", "Cerrar sesión"], f"sin ruta el diálogo ofrece otra cosa: {[b['texto'] for b in m['botones']]}")

    # Escape sin ruta = cerrar: el diálogo se va y el foco vuelve al RUT.
    p.keyboard.press("Escape")
    r.asentar()
    m = r.medir()
    r.exige(not m["dialogoVisible"] and m["descartado"], f"Escape sin ruta no cierra el diálogo: diálogo={m['dialogoVisible']} descartado={m['descartado']}")
    r.exige(m["activo"]["id"] == "rut", f"tras descartar, el foco no volvió al RUT: {m['activo']}")
    r.exige(m["mainOculto"] is None, "tras descartar, <main> sigue aria-hidden")

    # T-45 s, la medición del revisor: el último minuto NO puede quedar sin aviso.
    p.clock.run_for(15_000)
    m = r.medir()
    r.exige(m["fase"] == "urgente" and not m["dialogoVisible"], f"descartado, el diálogo volvió a abrirse solo: fase={m['fase']} diálogo={m['dialogoVisible']}")
    r.exige(m["avisoVisible"], f"descartado el diálogo en URGENTE, el último minuto queda sin ningún aviso visible: aviso={m['avisoVisible']} restante={m['restante']}")
    r.exige(m["restante"] == 45, f"a T-45 s el contador marca {m['restante']}")
    r.exige(any(t["texto"].startswith("Tu sesión termina en") for t in m["avisoTextos"]), f"la tira urgente no dice cuánto queda: {m['avisoTextos']}")
    r.contraste(m["avisoTextos"], "tira urgente")

    # La tira reabre el diálogo; «Entendido» (Enter) lo cierra y devuelve el foco
    # al campo que el funcionario dejó para llegar a la tira (el domicilio).
    p.keyboard.press("Tab")
    p.keyboard.press("Tab")
    m = r.medir()
    r.exige(m["activo"]["enAviso"] and "muni-sg__aviso-btn" in m["activo"]["clase"], f"dos Tab desde el RUT no llegan a la tira urgente: {m['activo']}")
    r.foco_visible(m, "tira urgente")
    p.keyboard.press("Enter")
    r.asentar()
    m = r.medir()
    r.exige(m["dialogoVisible"] and m["activo"]["texto"] == "Entendido", f"la tira urgente no reabre el diálogo con el foco en «Entendido»: {m['activo']}")
    p.keyboard.press("Enter")
    r.asentar()
    m = r.medir()
    r.exige(not m["dialogoVisible"] and m["avisoVisible"], f"«Entendido» no cierra dejando la tira: diálogo={m['dialogoVisible']} aviso={m['avisoVisible']}")
    r.exige(m["activo"]["id"] == "dom", f"tras «Entendido» el foco no volvió al campo que dejó el funcionario: {m['activo']}")

    # T=0 bloquea igual, y la tira desaparece bajo el velo.
    r.saltar_a(EXPIRA)
    p.wait_for_timeout(250)
    m = r.medir()
    r.exige(m["fase"] == "terminada" and m["opaca"] and m["veloOpacidad"] == "1", f"sin ruta no bloquea en T=0: fase={m['fase']} opaca={m['opaca']} velo={m['veloOpacidad']}")
    r.exige(not m["avisoVisible"], "en T=0 la tira sigue visible sobre el velo")
    r.exige(m["activo"]["texto"] == "Ingresar de nuevo", f"foco en T=0 sin ruta: {m['activo']}")
    r.exige(not r.errores, f"errores de página: {r.errores}")
    contexto.close()
    return f"{donde:<24} sin renovar-url: descartado en URGENTE la tira sigue (restante 45) y T=0 bloquea"


def prorroga_204(navegador, url: str, donde: str, fallos: list[str]) -> str:
    """El anfitrión responde 204 sin cuerpo. Con `duracion` (SESSION_LIFETIME en
    segundos) la expiración nueva es ahora + duracion; sin ella, ahora + lo que
    restaba al cargar, que es pesimista: por eso se documenta y no se vende como
    equivalente al JSON."""
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    r = abrir_banco(contexto, url, donde, fallos)
    p = r.p
    r.saltar(5 * MIN)
    r.respuesta = {"status": 204, "body": ""}

    ahora = p.evaluate("() => Date.now()")
    p.evaluate(f"() => {DATA}.renovar()")
    r.exige(r.esperar(f"{DATA}.fase === 'activa'"), "un 204 no prorrogó")
    m = r.medir()
    r.exige(m["expiraMs"] == ahora + 7200 * 1000, f"con duracion=7200 un 204 no concede ahora + 2 h: expira en {(m['expiraMs'] - ahora) / 1000:.0f} s")
    r.exige(m["anuncio"] == "Sesión prorrogada.", f"tras el 204 no dijo «Sesión prorrogada.»: {m['anuncio']!r}")

    # Sin `duracion`, lo que restaba al cargar (unos diez minutos en este banco).
    p.evaluate(f"() => {{ {DATA}.duracion = 0; }}")
    r.saltar(3 * MIN)
    ahora = p.evaluate("() => Date.now()")
    p.evaluate(f"() => {DATA}.renovar()")
    r.exige(r.esperar(f"{DATA}.expiraMs !== {m['expiraMs']}"), "el segundo 204 no cambió la expiración")
    m = r.medir()
    r.exige(m["expiraMs"] == ahora + m["duracionMs"], f"sin duracion un 204 no concede lo que restaba al cargar: {(m['expiraMs'] - ahora) / 1000:.0f} s vs {m['duracionMs'] / 1000:.0f} s")
    r.exige(not r.errores, f"errores de página: {r.errores}")
    contexto.close()
    return f"{donde:<24} 204 con duracion=7200 s → +2 h; sin duracion → +{m['duracionMs'] / 1000:.0f} s (lo que restaba al cargar)"


def main(argv: list[str]) -> int:
    carpeta = Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO
    paginas = [carpeta / f"{tema}.html" for tema in ("claro", "oscuro")]
    sin_ruta_html = carpeta / "sin-ruta.html"
    if not all(p.is_file() for p in paginas) or not sin_ruta_html.is_file():
        print(f"Banco incompleto en {carpeta}: faltan claro.html, oscuro.html o sin-ruta.html; genera primero "
              "`vendor/bin/pest --filter=GuardiaDeSesion`.")
        return 2

    from playwright.sync_api import sync_playwright

    servidor, base = servir(carpeta)
    fallos: list[str] = []
    resumen: list[str] = []
    try:
        with sync_playwright() as pw:
            for nombre in ("chromium", "firefox"):
                navegador = getattr(pw, nombre).launch()
                for ruta in paginas:
                    url = f"{base}/{ruta.name}"
                    donde = f"{nombre} {ruta.stem}"
                    contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion="no-preference")
                    resumen.append(recorrido_completo(contexto, url, donde, fallos))
                    contexto.close()
                url = f"{base}/claro.html"
                resumen.append(movimiento_reducido(navegador, url, f"{nombre} reduce", fallos))
                resumen.append(movil(navegador, url, f"{nombre} 390px", fallos))
                resumen.append(visibilidad(navegador, url, f"{nombre} visibilidad", fallos))
                resumen.append(pestanas(navegador, url, f"{nombre} pestañas", fallos, sin_broadcast=False))
                resumen.append(pestanas(navegador, url, f"{nombre} pestañas", fallos, sin_broadcast=True))
                resumen.append(carga_tardia(navegador, url, f"{nombre} carga tardía", fallos))
                resumen.append(sin_almacenamiento(navegador, url, f"{nombre} sin storage", fallos))
                resumen.append(prorroga_204(navegador, url, f"{nombre} 204", fallos))
                resumen.append(sin_ruta(navegador, f"{base}/{sin_ruta_html.name}", f"{nombre} sin ruta", fallos))
                navegador.close()
    finally:
        servidor.shutdown()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa: {len(paginas)} páginas × 2 navegadores, más los escenarios de movimiento, móvil, visibilidad, pestañas, almacenamiento, 204 y sin ruta.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
