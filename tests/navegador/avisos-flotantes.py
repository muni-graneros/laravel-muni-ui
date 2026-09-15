#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::toast-host>` sobre el banco
`build/avisos-flotantes/banco.html`.

    .venv-a11y/bin/python tests/navegador/avisos-flotantes.py [build/avisos-flotantes]

La corre `tests/AvisosFlotantesTest.php` después de generar el banco (y se salta,
no se finge, si no está el entorno de la reja, que en CI no se instala).

Existe porque lo que esta ficha vino a reparar no es texto: las pruebas de Pest
leen el HTML que emite Blade y ejecutan el `x-data` bajo node con un reloj falso,
y las dos cosas juntas siguen sin decir nada de lo que el funcionario hace con el
aviso. Con Alpine de verdad, un `<template x-for>` de verdad y el foco de verdad
se mide acá, en Chromium Y en Firefox:

  · AL CARGAR nadie anuncia nada y nadie roba el foco: las dos regiones vivas
    existen vacías, dentro del árbol de accesibilidad, con el trío rol +
    aria-live + aria-atomic, con nombre y sin `tabindex`;
  · la PILA VISUAL no es región viva de nada (ni el contenedor ni cada aviso
    llevan `role` ni `aria-live`): el anuncio va por las dos regiones ocultas;
  · el TEXTO ENTRA en la región que le toca por tono —`danger` en la asertiva,
    todo lo demás en la cortés— y con el rótulo del tono por delante;
  · una RÁFAGA de la misma prioridad anuncia TODOS los avisos, no solo el
    último: dos errores de validación del mismo submit se oyen los dos;
  · la región se LIMPIA TARDE: el texto no se queda pegado al cursor virtual;
  · TIEMPO (2.2.1): `danger` y `warn` no se autocierran nunca, `duration: 0`
    tampoco, y `ok`/`info` viven al menos el piso de 5 s;
  · el temporizador se PAUSA con el puntero encima y mientras el aviso contenga
    el foco, y reanuda al salir;
  · `Escape` cierra SOLO el aviso que tiene el foco y NO llega a la ventana
    (un Escape global cerraría además el modal o el cajón que hubiera abierto);
  · al morir el aviso que tenía el foco, el foco va al botón × del otro aviso
    y, cuando no queda ninguno, al `<body>`: nunca por caída;
  · el botón × mide 24×24 y su foco es un `outline` sólido de 3 px;
  · con `prefers-reduced-motion: reduce` la transición del aviso computa 0 s, y
    sin la preferencia existe (si no, la comprobación sería vacía);
  · cada texto del aviso pasa 4,5:1 y cada indicador no textual (el glifo del
    tono, el borde de color) 3:1, en tema claro Y en tema oscuro.

En Chromium deja además capturas de los cuatro tonos apilados en escritorio y en
teléfono, claro y oscuro, en `<banco>/capturas/`.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "avisos-flotantes"
NAVEGADORES = ("chromium", "firefox")
TEMAS = ("light", "dark")
ESCRITORIO = {"width": 1440, "height": 900}
TELEFONO = {"width": 390, "height": 844}

# El piso de vida de un aviso que autocierra (5 s) con margen para el reloj del
# navegador: pasado esto, un `ok` sin pausa ya no puede seguir en la pila.
PISO = 5000
MARGEN = 1600

# ---------------------------------------------------------------------------
# Sondas
# ---------------------------------------------------------------------------

# Observa las dos regiones vivas ANTES de disparar nada: lo que importa no es el
# texto final del nodo sino la secuencia de textos que pasó por él, que es lo que
# el lector alcanza a leer. Un anuncio pisado por el siguiente no deja rastro en
# el DOM final.
OBSERVAR = """
() => {
  window.__escrito = { polite: [], assertive: [] };
  for (const [clave, sel] of [['polite', '[role="status"]'], ['assertive', '[role="alert"]']]) {
    const n = document.querySelector('#avisos ' + sel);
    if (!n) continue;
    new MutationObserver(() => {
      const t = n.textContent.trim();
      if (t && window.__escrito[clave][window.__escrito[clave].length - 1] !== t) window.__escrito[clave].push(t);
    }).observe(n, { childList: true, characterData: true, subtree: true });
  }
  window.__escapesEnLaVentana = 0;
  window.addEventListener('keydown', e => { if (e.key === 'Escape') window.__escapesEnLaVentana++; });
}
"""

REPOSO = """
() => {
  const host = document.querySelector('#avisos');
  const region = rol => {
    const n = host.querySelector('[role="' + rol + '"]');
    if (!n) return null;
    const c = getComputedStyle(n);
    return {
      ariaLive: n.getAttribute('aria-live'),
      ariaAtomic: n.getAttribute('aria-atomic'),
      nombre: n.getAttribute('aria-label'),
      tabindex: n.getAttribute('tabindex'),
      texto: n.textContent.trim(),
      enElArbol: c.display !== 'none' && c.visibility !== 'hidden' && n.getClientRects().length > 0,
      display: c.display,
      visibility: c.visibility,
    };
  };

  return {
    hay: !!host,
    polite: region('status'),
    assertive: region('alert'),
    contenedorRole: host.getAttribute('role'),
    contenedorAriaLive: host.getAttribute('aria-live'),
    avisos: document.querySelectorAll('#avisos .muni-toast').length,
    activo: document.activeElement === document.body ? 'body' : (document.activeElement.id || document.activeElement.tagName.toLowerCase()),
  };
}
"""

PILA = """
() => [...document.querySelectorAll('#avisos .muni-toast')].map(t => ({
  tono: (t.className.match(/muni-toast--(\\w+)/) || [])[1] || null,
  role: t.getAttribute('role'),
  ariaLive: t.getAttribute('aria-live'),
  mensaje: (t.querySelector('.muni-toast__msg') || {}).textContent || '',
  cerrar: !!t.querySelector('.muni-toast__x'),
}))
"""

ACTIVO = """
() => {
  const a = document.activeElement;
  if (!a || a === document.body) return 'body';
  if (a.classList && a.classList.contains('muni-toast__x')) {
    const t = a.closest('.muni-toast');
    return 'cerrar:' + ((t.className.match(/muni-toast--(\\w+)/) || [])[1] || '?');
  }
  return a.id ? '#' + a.id : a.tagName.toLowerCase();
}
"""

# Colores de todo lo que el aviso pinta, ya resueltos por el navegador.
COLORES = """
() => {
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\\(\\s*0,\\s*0,\\s*0,\\s*0\\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  return [...document.querySelectorAll('#avisos .muni-toast')].map(t => {
    const c = getComputedStyle(t);
    const fondo = fondoDe(t);
    const glifo = t.querySelector('.muni-toast__glifo');
    const titulo = t.querySelector('.muni-toast__title');
    const msg = t.querySelector('.muni-toast__msg');
    const x = t.querySelector('.muni-toast__x');
    const cajaX = x.getBoundingClientRect();

    return {
      tono: (t.className.match(/muni-toast--(\\w+)/) || [])[1] || null,
      fondo,
      borde: c.borderLeftColor,
      glifo: glifo ? getComputedStyle(glifo).color : null,
      titulo: titulo ? { texto: titulo.textContent.trim(), color: getComputedStyle(titulo).color, tamano: parseFloat(getComputedStyle(titulo).fontSize) } : null,
      mensaje: { texto: msg.textContent.trim(), color: getComputedStyle(msg).color, tamano: parseFloat(getComputedStyle(msg).fontSize) },
      equis: { color: getComputedStyle(x).color, ancho: cajaX.width, alto: cajaX.height },
      desbordaAncho: t.getBoundingClientRect().right > document.documentElement.clientWidth + 1,
    };
  });
}
"""

FOCO_DEL_BOTON = """
() => {
  const x = document.activeElement;
  const c = getComputedStyle(x);
  return { estilo: c.outlineStyle, ancho: c.outlineWidth, color: c.outlineColor, offset: c.outlineOffset };
}
"""

# La transición del aviso vive en clases que Alpine pone y quita: se mide sobre un
# elemento de sonda con la misma clase, que es el CSS que el componente publica.
MOVIMIENTO = """
() => {
  const s = document.createElement('div');
  s.className = 'muni-toast-enter';
  document.body.appendChild(s);
  const d = getComputedStyle(s).transitionDuration;
  s.remove();
  return d;
}
"""


# ---------------------------------------------------------------------------
# Contraste
# ---------------------------------------------------------------------------

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


def contraste(frente: str, fondo: str) -> float:
    l1, l2 = _luminancia(_mezclar(frente, fondo)), _luminancia(_rgb(fondo)[:3])
    claro, oscuro = max(l1, l2), min(l1, l2)
    return (claro + 0.05) / (oscuro + 0.05)


# ---------------------------------------------------------------------------
# Utilidades del banco
# ---------------------------------------------------------------------------

def abrir(navegador, ruta: Path, movimiento: str, tema: str, viewport=None):
    contexto = navegador.new_context(viewport=viewport or ESCRITORIO, reduced_motion=movimiento)
    pagina = contexto.new_page()
    pagina.goto(ruta.as_uri())
    pagina.evaluate(f"() => document.documentElement.setAttribute('data-muni-theme', '{tema}')")
    pagina.evaluate(OBSERVAR)
    # El puntero lejos de la esquina de los avisos: un `mouseenter` accidental
    # pausa el temporizador y la medición de tiempo diría cualquier cosa.
    pagina.mouse.move(4, 4)
    return contexto, pagina


def disparar(pagina, tone: str, message: str, title=None, duration=None) -> None:
    pagina.evaluate(
        """([tone, message, title, duration]) => {
            const detail = { tone, message };
            if (title !== null) detail.title = title;
            if (duration !== null) detail.duration = duration;
            window.dispatchEvent(new CustomEvent('muni-toast', { detail }));
        }""",
        [tone, message, title, duration],
    )


def tabular_hasta_cerrar(pagina, limite: int = 14) -> str:
    for _ in range(limite):
        pagina.keyboard.press("Tab")
        activo = pagina.evaluate(ACTIVO)
        if activo.startswith("cerrar:"):
            return activo
    return pagina.evaluate(ACTIVO)


# ---------------------------------------------------------------------------
# Escenarios
# ---------------------------------------------------------------------------

def escenario_reposo(pagina, donde: str, fallos: list[str]) -> None:
    d = pagina.evaluate(REPOSO)

    if not d["hay"]:
        fallos.append(f"{donde}: no hay host `#avisos` en el banco")
        return

    for rol, clave, prioridad in (("status", "polite", "polite"), ("alert", "assertive", "assertive")):
        r = d[clave]
        if r is None:
            fallos.append(f"{donde}: falta la región viva role=\"{rol}\" en el HTML inicial")
            continue
        if r["ariaLive"] != prioridad:
            fallos.append(f"{donde} región {rol}: aria-live={r['ariaLive']!r}, esperaba {prioridad!r}")
        if r["ariaAtomic"] != "true":
            fallos.append(f"{donde} región {rol}: aria-atomic={r['ariaAtomic']!r}")
        if not r["nombre"]:
            fallos.append(f"{donde} región {rol}: sin nombre accesible")
        if r["tabindex"] is not None:
            fallos.append(f"{donde} región {rol}: lleva tabindex={r['tabindex']!r}; una región viva no recibe el foco")
        if r["texto"]:
            fallos.append(f"{donde} región {rol}: nace con texto ({r['texto']!r}) y se anuncia sola al cargar")
        if not r["enElArbol"]:
            fallos.append(
                f"{donde} región {rol}: fuera del árbol de accesibilidad "
                f"(display={r['display']}, visibility={r['visibility']}): la región queda muda"
            )

    if d["contenedorRole"] is not None or d["contenedorAriaLive"] is not None:
        fallos.append(
            f"{donde}: la pila visual es región viva (role={d['contenedorRole']!r}, "
            f"aria-live={d['contenedorAriaLive']!r}): con una región encima de otra el lector anuncia dos veces"
        )
    if d["avisos"]:
        fallos.append(f"{donde}: la pila nace con {d['avisos']} aviso(s)")
    if d["activo"] != "body":
        fallos.append(f"{donde}: al cargar el foco está en {d['activo']}: el aviso no es un diálogo y nunca roba el foco")


def escenario_ruta_por_tono(pagina, donde: str, fallos: list[str]) -> None:
    disparar(pagina, "ok", "Solicitud N.º 2026-4831 ingresada — derivada a Obras.", "Solicitud ingresada")
    disparar(pagina, "danger", "El RUT ya tiene licencia clase B vigente.", "No se pudo guardar")
    pagina.wait_for_timeout(600)

    escrito = pagina.evaluate("() => window.__escrito")

    if escrito["polite"] != ["Confirmación. Solicitud ingresada. Solicitud N.º 2026-4831 ingresada — derivada a Obras."]:
        fallos.append(f"{donde}: la región cortés recibió {escrito['polite']!r}")
    if escrito["assertive"] != ["Error. No se pudo guardar. El RUT ya tiene licencia clase B vigente."]:
        fallos.append(f"{donde}: la región asertiva recibió {escrito['assertive']!r}")

    for t in pagina.evaluate(PILA):
        if t["role"] is not None or t["ariaLive"] is not None:
            fallos.append(f"{donde}: el aviso {t['tono']} lleva role={t['role']!r}/aria-live={t['ariaLive']!r}")
        if not t["cerrar"]:
            fallos.append(f"{donde}: el aviso {t['tono']} no trae botón de cerrar")


def escenario_rafaga(pagina, donde: str, fallos: list[str]) -> None:
    """Dos errores del mismo submit, sin un tick de por medio."""
    pagina.evaluate(
        """() => {
            window.dispatchEvent(new CustomEvent('muni-toast', { detail: { tone: 'danger', message: 'La fecha de nacimiento es obligatoria.' } }));
            window.dispatchEvent(new CustomEvent('muni-toast', { detail: { tone: 'danger', message: 'El RUT no es válido.' } }));
        }"""
    )
    pagina.wait_for_timeout(3000)

    escrito = pagina.evaluate("() => window.__escrito")
    esperado = ["Error. La fecha de nacimiento es obligatoria.", "Error. El RUT no es válido."]

    if escrito["assertive"] != esperado:
        fallos.append(
            f"{donde}: la ráfaga de dos errores anunció {escrito['assertive']!r}. "
            "Con un solo hueco por región, el segundo pisa al primero antes de que llegue al lector."
        )

    # Limpieza tardía: el texto no se queda pegado al cursor virtual para siempre.
    pagina.wait_for_timeout(7000)
    pegado = pagina.evaluate("() => document.querySelector('#avisos [role=\"alert\"]').textContent.trim()")
    if pegado:
        fallos.append(f"{donde}: la región asertiva se quedó con el texto pegado: {pegado!r}")


def escenario_tiempo(pagina, donde: str, fallos: list[str]) -> None:
    disparar(pagina, "ok", "Guardado.")
    disparar(pagina, "warn", "La licencia clase B vence el 30 de septiembre.", "Documento por vencer")
    disparar(pagina, "danger", "El RUT ya tiene licencia clase B vigente.", "No se pudo guardar")
    disparar(pagina, "ok", "Folio 2026-4831.", None, 0)

    pagina.wait_for_timeout(PISO + MARGEN)
    tonos = [t["tono"] for t in pagina.evaluate(PILA)]

    if "warn" not in tonos or "danger" not in tonos:
        fallos.append(f"{donde}: pasados {PISO + MARGEN} ms la pila es {tonos}: `danger` y `warn` no se autocierran NUNCA")
    if tonos.count("ok") != 1:
        fallos.append(
            f"{donde}: pasados {PISO + MARGEN} ms quedan {tonos.count('ok')} avisos `ok` de 2: "
            "el del piso de 5 s tenía que irse y el de `duration: 0` tenía que quedarse"
        )
    mensajes = [t["mensaje"] for t in pagina.evaluate(PILA)]
    if "Folio 2026-4831." not in mensajes:
        fallos.append(f"{donde}: el aviso con `duration: 0` se autocerró igual; la pila dice {mensajes!r}")


def escenario_pausa_puntero(pagina, donde: str, fallos: list[str]) -> None:
    disparar(pagina, "ok", "Solicitud N.º 2026-4831 ingresada — derivada a Obras.")
    pagina.wait_for_timeout(300)

    caja = pagina.locator("#avisos .muni-toast").first.bounding_box()
    if caja is None:
        fallos.append(f"{donde}: el aviso no llegó a pintarse")
        return

    pagina.mouse.move(caja["x"] + caja["width"] / 2, caja["y"] + caja["height"] / 2)
    pagina.wait_for_timeout(PISO + MARGEN)

    if pagina.locator("#avisos .muni-toast").count() != 1:
        fallos.append(f"{donde}: con el puntero encima el aviso se autocerró igual (2.2.1, tiempo ajustable)")
        return

    pagina.mouse.move(4, 4)
    pagina.wait_for_timeout(PISO + MARGEN)

    if pagina.locator("#avisos .muni-toast").count() != 0:
        fallos.append(f"{donde}: al sacar el puntero el aviso no reanudó su temporizador y quedó colgado")


def escenario_foco(pagina, donde: str, fallos: list[str]) -> None:
    """Pausa por foco, Escape acotado y devolución explícita del foco."""
    disparar(pagina, "ok", "Solicitud N.º 2026-4831 ingresada — derivada a Obras.")
    disparar(pagina, "danger", "El RUT ya tiene licencia clase B vigente.", "No se pudo guardar")
    pagina.wait_for_timeout(300)

    activo = tabular_hasta_cerrar(pagina)
    if activo != "cerrar:ok":
        fallos.append(f"{donde}: tabulando desde el principio el foco cayó en {activo}, no en el × del primer aviso")
        return

    foco = pagina.evaluate(FOCO_DEL_BOTON)
    if foco["estilo"] != "solid" or not foco["ancho"].startswith("3"):
        fallos.append(f"{donde}: el foco del × no es un outline sólido de 3px: {foco}")

    # Con el foco dentro, el aviso que autocerraría se queda; el otro es
    # persistente y tampoco se va.
    pagina.wait_for_timeout(PISO + MARGEN)
    tonos = [t["tono"] for t in pagina.evaluate(PILA)]
    if tonos != ["ok", "danger"]:
        fallos.append(
            f"{donde}: con el foco dentro del aviso `ok` la pila quedó en {tonos}: "
            "el temporizador no se pausó por foco (2.2.1)"
        )

    # Escape: cierra SOLO este aviso y no llega a la ventana.
    pagina.keyboard.press("Escape")
    pagina.wait_for_timeout(300)

    escapes = pagina.evaluate("() => window.__escapesEnLaVentana")
    if escapes:
        fallos.append(
            f"{donde}: el Escape del aviso llegó a la ventana ({escapes} vez/veces): "
            "cerraría además el modal o el cajón que estuviera abierto"
        )

    tonos = [t["tono"] for t in pagina.evaluate(PILA)]
    if tonos != ["danger"]:
        fallos.append(f"{donde}: Escape dejó la pila en {tonos}, esperaba solo el `danger`")

    activo = pagina.evaluate(ACTIVO)
    if activo != "cerrar:danger":
        fallos.append(
            f"{donde}: al morir el aviso que tenía el foco, el foco quedó en {activo!r} "
            "en vez del × del aviso que sigue vivo"
        )

    # El último: ya no queda a quién devolverlo, así que va al <body> a mano.
    pagina.keyboard.press("Enter")
    pagina.wait_for_timeout(300)

    if pagina.locator("#avisos .muni-toast").count():
        fallos.append(f"{donde}: Enter sobre el × no cerró el último aviso")
    activo = pagina.evaluate(ACTIVO)
    if activo != "body":
        fallos.append(f"{donde}: cerrado el último aviso el foco quedó en {activo!r}, no en el <body>")


def escenario_color(pagina, donde: str, fallos: list[str]) -> list[str]:
    disparar(pagina, "ok", "Solicitud N.º 2026-4831 ingresada — derivada a Obras.", "Solicitud ingresada")
    disparar(pagina, "info", "La agenda del sábado 12 está cerrada por mantención.")
    disparar(pagina, "warn", "La licencia clase B vence el 30 de septiembre.", "Documento por vencer")
    disparar(pagina, "danger", "El RUT ya tiene licencia clase B vigente.", "No se pudo guardar")
    pagina.wait_for_timeout(400)

    medidas = pagina.evaluate(COLORES)
    if len(medidas) != 4:
        fallos.append(f"{donde}: la pila muestra {len(medidas)} avisos de 4 (el tope es 4, no menos)")

    lineas = []
    for t in medidas:
        quien = f"{donde} aviso {t['tono']}"
        peor = 21.0

        for clave in ("titulo", "mensaje"):
            parte = t[clave]
            if parte is None:
                continue
            ratio = contraste(parte["color"], t["fondo"])
            peor = min(peor, ratio)
            if ratio < 4.5:
                fallos.append(f"{quien}: el {clave} «{parte['texto']}» a {ratio:.2f}:1 sobre el fondo del aviso")

        for clave, minimo in (("glifo", 3.0), ("borde", 3.0), ("equis", 3.0)):
            color = t[clave] if clave != "equis" else t["equis"]["color"]
            ratio = contraste(color, t["fondo"])
            peor = min(peor, ratio)
            if ratio < minimo:
                fallos.append(f"{quien}: el indicador «{clave}» a {ratio:.2f}:1 sobre el fondo (mínimo {minimo}:1)")

        if t["equis"]["ancho"] < 24 or t["equis"]["alto"] < 24:
            fallos.append(f"{quien}: el × mide {t['equis']['ancho']:.0f}×{t['equis']['alto']:.0f}, menos de 24×24 (2.5.8)")
        if t["desbordaAncho"]:
            fallos.append(f"{quien}: el aviso se sale del ancho de la ventana")

        lineas.append(f"    {t['tono']:<7} peor contraste={peor:.2f}:1  ×={t['equis']['ancho']:.0f}×{t['equis']['alto']:.0f}")

    return lineas


# ---------------------------------------------------------------------------

def main(argv: list[str]) -> int:
    carpeta = Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO
    banco = carpeta / "banco.html"

    if not banco.is_file():
        print(f"No está el banco {banco}: genéralo con `vendor/bin/pest --filter=AvisosFlotantes`.")
        return 2

    if "SIN ALPINE" in banco.read_text(encoding="utf-8")[:4000]:
        print("El banco se escribió sin Alpine (`npm install`): sin él no hay nada que medir.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []
    capturas = carpeta / "capturas"
    capturas.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()

            # La interacción no depende del tema —es el mismo `x-data`—, así que se
            # mide una vez por navegador; el color sí, y se mide en los dos.
            for escenario in (
                escenario_reposo,
                escenario_ruta_por_tono,
                escenario_rafaga,
                escenario_tiempo,
                escenario_pausa_puntero,
                escenario_foco,
            ):
                contexto, pagina = abrir(navegador, banco, "reduce", "light")
                escenario(pagina, f"{nombre} {escenario.__name__[10:]}", fallos)
                contexto.close()

            for tema in TEMAS:
                donde = f"{nombre} [{tema}]"

                contexto, pagina = abrir(navegador, banco, "reduce", tema)
                resumen.append(f"{nombre:<9}[{tema}] contraste de los cuatro tonos:")
                resumen.extend(escenario_color(pagina, donde, fallos))

                if nombre == "chromium":
                    pagina.screenshot(path=str(capturas / f"toast-{tema}-escritorio.png"))
                contexto.close()

                if nombre == "chromium":
                    contexto, pagina = abrir(navegador, banco, "reduce", tema, viewport=TELEFONO)
                    escenario_color(pagina, f"{donde} teléfono", fallos)
                    pagina.screenshot(path=str(capturas / f"toast-{tema}-telefono.png"))
                    contexto.close()

                # Movimiento: con la preferencia la transición computa 0 s; sin ella,
                # existe. Si no existiera, la primera comprobación sería vacía.
                for movimiento, espera_cero in (("reduce", True), ("no-preference", False)):
                    contexto, pagina = abrir(navegador, banco, movimiento, tema)
                    dur = pagina.evaluate(MOVIMIENTO)
                    cero = all(float(x.rstrip("s")) == 0 for x in dur.split(", "))
                    if espera_cero and not cero:
                        fallos.append(f"{donde}: con prefers-reduced-motion la transición del aviso computa {dur}")
                    if not espera_cero and cero:
                        fallos.append(f"{donde}: sin la preferencia la transición computa {dur}: la comprobación de arriba sería vacía")
                    resumen.append(f"{nombre:<9}[{tema}] transición({movimiento})={dur}")
                    contexto.close()

            navegador.close()

    print("\n".join(resumen))

    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1

    print(f"\nTodo pasa: {len(NAVEGADORES)} navegadores × 6 escenarios de interacción, color en {len(TEMAS)} temas.")
    print(f"Capturas en {capturas}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
