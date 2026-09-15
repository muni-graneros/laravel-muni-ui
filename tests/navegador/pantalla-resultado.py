#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::pantalla-resultado>` sobre `build/pantalla-resultado/`.

    .venv-a11y/bin/python tests/navegador/pantalla-resultado.py [build/pantalla-resultado]

La corre `tests/PantallaDeResultadoTest.php` después de generar el banco (y se
salta si no está el entorno de la reja, que en CI no se instala). Las pruebas de
Pest leen el TEXTO del fuente —que exista «navigator.clipboard», que exista
«execCommand», que el `role="status"` nazca vacío— y eso ya engañó una vez en
`stat`: la regla pasó el test de texto y en el navegador el defecto seguía vivo.
Acá se mide, en Chromium Y Firefox:

  · el FOCO al cargar cae en el <h1> y su outline computa 3 px sólidos con 3:1
    contra el fondo (WCAG 2.2 AA 2.4.3, 2.4.7 y 1.4.11);
  · `autofocus="false"` NO enfoca nada: en `tarjeta.html` la pantalla vive debajo
    de una pantalla de alto, así que si el componente enfoca igual el navegador
    desplaza la página y `scrollY` deja de ser 0. Es el defecto que el revisor
    encontró: la prop apagaba el ATRIBUTO pero no la rama de Alpine;
  · los TRES eslabones del copiado, cada uno forzado por separado: portapapeles
    moderno, `execCommand` con el portapapeles amputado (que es la intranet
    municipal por http), y la selección por Range con los dos amputados. En los
    dos primeros el foco tiene que volver al botón: la caja temporal del
    `execCommand` se destruye y el teclado se quedaría en el <body>;
  · la región viva se LLENA con el texto, y al SEGUNDO copiado vuelve a mutar:
    si se reescribiera la misma cadena no hay mutación de DOM y el lector de
    pantalla se queda callado (la ficha pide «confirma en texto»);
  · sin Alpine el botón de copiar NO aparece (x-cloak) y el folio sigue visible
    y seleccionable de un clic (`user-select: all`), que es la última salida;
  · el recorrido REAL de Tab: copiar → imprimir → salida, y la salida es la
    ÚLTIMA parada («el último elemento es el único camino de salida»);
  · todo texto pasa 4,5:1 (3:1 si es grande) contra su fondo efectivo, en las
    CUATRO páginas: claro, oscuro y las dos del panel, que cargan únicamente
    `muni-ui-filament.css` (DESIGN §7) y son donde una clase declarada fuera del
    `@once` —`.muni-num`— se quedaría sin estilo;
  · el área táctil de los tres controles llega a 44 px (mesón y vecino mayor);
  · con `prefers-reduced-motion: reduce` la transición computa 0 s, y sin la
    preferencia existe (si no, la comprobación sería vacía);
  · a 390 px y a 320 px el documento NO se desplaza en horizontal (1.4.10).

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "pantalla-resultado"
NAVEGADORES = ("chromium", "firefox")
COMPLETAS = ("claro.html", "oscuro.html", "panel-claro.html", "panel-oscuro.html")
MOVIMIENTOS = ("reduce", "no-preference")
ANCHOS = (("telefono", 390, 780), ("estrecho", 320, 780))
FOLIO = "2026-04871"

# Amputaciones del entorno, inyectadas ANTES de que corra Alpine. Es la única
# forma de recorrer los tres eslabones de la cadena: en un navegador sano el
# primero siempre gana y los otros dos no los ejecuta nadie.
# Ojo: `add_init_script` recibe el CÓDIGO FUENTE y lo evalúa tal cual, así que
# una función flecha suelta se define y no la llama nadie: la amputación tiene
# que ser una sentencia que se ejecute sola.
SIN_PORTAPAPELES = """
try { Object.defineProperty(navigator, 'clipboard', { get: () => undefined, configurable: true }); } catch (e) {}
try { Object.defineProperty(window, 'isSecureContext', { get: () => false, configurable: true }); } catch (e) {}
"""

SIN_NADA = """
try { Object.defineProperty(navigator, 'clipboard', { get: () => undefined, configurable: true }); } catch (e) {}
try { Object.defineProperty(window, 'isSecureContext', { get: () => false, configurable: true }); } catch (e) {}
try { Object.defineProperty(document, 'execCommand', { value: () => false, configurable: true }); } catch (e) {}
"""

SONDA = r"""
() => {
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };

  // El primer ancestro con fondo opaco: la pantalla es transparente y el fondo
  // efectivo lo pone la sección del panel o el <body>.
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  const leer = (sel, nombre) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const c = getComputedStyle(el);
    return {
      nombre,
      texto: el.textContent.trim(),
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
      display: c.display,
      transicion: c.transitionDuration,
      familia: c.fontFamily,
      numerico: c.fontVariantNumeric,
      seleccion: c.webkitUserSelect || c.userSelect,
      ...caja(el),
    };
  };

  const seccion = document.querySelector('section.muni-res');
  const titulo = document.querySelector('.muni-res__titulo');

  return {
    seccion: seccion ? {
      etiqueta: seccion.tagName,
      labelledby: seccion.getAttribute('aria-labelledby'),
      labelledbyCrudos: (seccion.outerHTML.match(/aria-labelledby=/g) || []).length,
      nombrado: !!(seccion.getAttribute('aria-labelledby') &&
                   document.getElementById(seccion.getAttribute('aria-labelledby'))),
    } : null,
    h1s: document.querySelectorAll('.muni-res h1').length,
    tituloId: titulo ? titulo.getAttribute('id') : null,
    tituloTabindex: titulo ? titulo.getAttribute('tabindex') : null,
    piezas: [
      leer('.muni-res__titulo', 'título'),
      leer('.muni-res__mensaje', 'mensaje'),
      leer('.muni-res__rotulo', 'rótulo'),
      leer('.muni-res__valor', 'folio'),
      leer('.muni-res__detalle', 'detalle'),
      leer('.muni-res__copiar', 'copiar'),
      leer('.muni-res__accion', 'imprimir'),
      leer('.muni-res__salida', 'salida'),
    ].filter(Boolean),
    aviso: (() => {
      const p = document.querySelector('.muni-res__aviso');
      if (!p) return null;
      const c = getComputedStyle(p);
      return {
        texto: p.textContent.trim(),
        rol: p.getAttribute('role'),
        vivo: p.getAttribute('aria-live'),
        color: c.color,
        fondo: fondoDe(p),
        tamano: parseFloat(c.fontSize),
        peso: parseInt(c.fontWeight, 10) || 400,
        ...caja(p),
      };
    })(),
    copiarOculto: (() => {
      const b = document.querySelector('.muni-res__copiar');
      if (!b) return null;
      return { display: getComputedStyle(b).display, cloak: b.hasAttribute('x-cloak') };
    })(),
    enfocables: [...document.querySelectorAll('.muni-res a[href],.muni-res button,.muni-res input,.muni-res [tabindex]')]
      .filter(e => e.getAttribute('tabindex') !== '-1').length,
    desbordaX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    scrollY: window.scrollY,
    activo: (() => {
      const a = document.activeElement;
      return a ? { etiqueta: a.tagName, clase: a.getAttribute('class') || '', id: a.getAttribute('id') || '' } : null;
    })(),
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
  // El anillo se dibuja DESPLAZADO hacia afuera (outline-offset), o sea sobre la
  // superficie que rodea al control y no sobre su propio fondo. La salida es un
  // botón primario pintado con --muni-accent, y --muni-focus vale lo mismo: si
  // se midiera contra su relleno daría 1:1 y la medición estaría mintiendo.
  const desplazado = parseFloat(c.outlineOffset) >= 0 && a.parentElement;
  return {
    etiqueta: a.tagName,
    clase: a.getAttribute('class') || '',
    texto: a.textContent.trim().split('\n')[0].trim(),
    outline: { ancho: c.outlineWidth, estilo: c.outlineStyle, color: c.outlineColor, desplazamiento: c.outlineOffset },
    fondo: fondoDe(desplazado ? a.parentElement : a),
  };
}
"""

# Un observador sobre la región viva: lo que importa del SEGUNDO copiado no es
# el texto —es el mismo— sino que el DOM vuelva a mutar. Sin mutación el lector
# de pantalla no repite nada y la segunda confirmación es muda.
ESPIAR_AVISO = r"""
() => {
  const p = document.querySelector('.muni-res__aviso');
  window.__mutaciones = 0;
  window.__vaciado = false;
  const obs = new MutationObserver(ms => {
    window.__mutaciones += ms.length;
    if (p.textContent.trim() === '') window.__vaciado = true;
  });
  obs.observe(p, { childList: true, characterData: true, subtree: true });
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
    """4,5:1, o 3:1 si el texto es grande (≥24px, o ≥18.66px en negrita)."""
    grande = tamano >= 24 or (tamano >= 18.66 and peso >= 700)
    return 3.0 if grande else 4.5


def verificar_estructura(d: dict, donde: str, fallos: list[str]) -> None:
    if not d["seccion"]:
        fallos.append(f"{donde}: no hay <section class=\"muni-res\">.")
        return
    if d["h1s"] != 1:
        fallos.append(f"{donde}: la pantalla de cierre tiene {d['h1s']} <h1> y tiene que tener uno.")
    if d["tituloTabindex"] != "-1":
        fallos.append(f"{donde}: el <h1> no es enfocable (tabindex={d['tituloTabindex']}).")
    if d["seccion"]["labelledbyCrudos"] != 1:
        fallos.append(
            f"{donde}: el <section> trae {d['seccion']['labelledbyCrudos']} aria-labelledby. "
            "Escrito a mano antes del derrame salen dos: HTML inválido y el del anfitrión se pierde."
        )
    if not d["seccion"]["nombrado"]:
        fallos.append(f"{donde}: el aria-labelledby apunta a un id que no existe: {d['seccion']['labelledby']!r}")
    if not d["aviso"]:
        fallos.append(f"{donde}: no hay región viva para el resultado del copiado.")
    else:
        if d["aviso"]["rol"] != "status" or d["aviso"]["vivo"] != "polite":
            fallos.append(f"{donde}: la región del aviso no es una región viva educada.")
        if d["aviso"]["texto"]:
            fallos.append(
                f"{donde}: la región viva nace con texto ({d['aviso']['texto']!r}); así no se anuncia el cambio."
            )
        if d["aviso"]["alto"] < 10:
            fallos.append(f"{donde}: la región viva no reserva su línea ({d['aviso']['alto']:.1f}px): el aviso empujará los botones (CLS).")

    folio = next((p for p in d["piezas"] if p["nombre"] == "folio"), None)
    if folio is None:
        fallos.append(f"{donde}: no está el folio.")
    else:
        if "mono" not in folio["familia"].lower() and "Mono" not in folio["familia"]:
            fallos.append(f"{donde}: el folio no sale en mono ({folio['familia']}); DESIGN §9.")
        if "tabular-nums" not in folio["numerico"]:
            fallos.append(
                f"{donde}: el folio no computa tabular-nums ({folio['numerico']!r}). Es lo que se "
                "pierde cuando `.muni-num` se declara fuera del `@once` y la hoja del panel no la trae."
            )
        if folio["seleccion"] != "all":
            fallos.append(
                f"{donde}: el folio no se selecciona de un clic (user-select: {folio['seleccion']!r}); "
                "es la última alternativa al portapapeles."
            )

    for nombre in ("copiar", "imprimir", "salida"):
        p = next((x for x in d["piezas"] if x["nombre"] == nombre), None)
        if p is None:
            fallos.append(f"{donde}: falta el control «{nombre}».")
        # Medio píxel de tolerancia: la caja se declara en 44px exactos y el
        # navegador devuelve 43.999… según cómo caiga el redondeo del subpíxel.
        # Sin la tolerancia, el mismo componente pasaba o fallaba según la
        # corrida, que es peor que no medir: un candado que parpadea se ignora.
        elif p["alto"] < 43.5:
            fallos.append(
                f"{donde} «{nombre}»: el blanco táctil mide {p['alto']:.2f}px de alto (<44). Es la "
                "pantalla del mesón y del vecino mayor."
            )


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 99.0
    piezas = list(d["piezas"])
    if d["aviso"] and d["aviso"]["texto"]:
        piezas.append({**d["aviso"], "nombre": "aviso"})
    for p in piezas:
        r = contraste(p["color"], p["fondo"])
        if r is None:
            fallos.append(f"{donde} «{p['nombre']}»: no se pudo leer el color.")
            continue
        peor = min(peor, r)
        exigido = minimo(p["tamano"], p["peso"])
        if r < exigido:
            fallos.append(
                f"{donde} «{p['nombre']}»: {r:.2f}:1 contra su fondo (exige {exigido}:1) "
                f"[{p['color']} sobre {p['fondo']}]"
            )
    return peor


def verificar_movimiento(d: dict, donde: str, movimiento: str, fallos: list[str]) -> str:
    duraciones = {p["transicion"] for p in d["piezas"] if p["nombre"] in ("copiar", "imprimir", "salida")}
    texto = ", ".join(sorted(duraciones))
    vivas = [t for t in duraciones if re.search(r"(?<![\d.])(?!0s)[\d.]+m?s", t)]
    if movimiento == "reduce" and vivas:
        fallos.append(f"{donde}: con movimiento reducido la transición sigue en {texto}; tiene que computar 0 s.")
    if movimiento == "no-preference" and not vivas:
        fallos.append(f"{donde}: sin la preferencia no hay transición ninguna ({texto}): medir «reduce» sería vacío.")
    return texto


def verificar_foco_inicial(pagina, d: dict, donde: str, fallos: list[str]) -> str:
    """El foco al cargar cae en el <h1> y se VE."""
    activo = d["activo"] or {}
    if activo.get("etiqueta") != "H1":
        fallos.append(
            f"{donde}: al cargar el foco quedó en {activo.get('etiqueta')!r} y no en el <h1>: "
            "el lector de pantalla no anuncia el resultado (ficha, tecla obligatoria 1)."
        )
        return "foco=" + str(activo.get("etiqueta", "?")).lower()

    foco = pagina.evaluate(FOCO)
    ancho = float(re.sub(r"[^\d.]", "", foco["outline"]["ancho"]) or 0)
    if ancho < 3 or foco["outline"]["estilo"] in ("none", "hidden"):
        fallos.append(
            f"{donde}: el <h1> enfocado no dibuja outline ({foco['outline']['estilo']} "
            f"{foco['outline']['ancho']}). La box-shadow del anillo no cuenta: dentro de Filament se "
            "computa transparente (DESIGN §5)."
        )
    else:
        r = contraste(foco["outline"]["color"], foco["fondo"])
        if r is not None and r < 3.0:
            fallos.append(f"{donde}: el outline del <h1> da {r:.2f}:1 contra el fondo (<3, WCAG 1.4.11).")
    return "foco=h1"


def verificar_teclado(pagina, donde: str, fallos: list[str]) -> str:
    """Tab desde el principio: copiar → imprimir → salida, y la salida es la ÚLTIMA."""
    pagina.evaluate("() => { document.activeElement && document.activeElement.blur(); window.scrollTo(0, 0); }")

    esperados = [
        ("copiar", "muni-res__copiar"),
        ("imprimir", "muni-res__accion"),
        ("salida", "muni-res__salida"),
    ]
    visto = []

    for i, (nombre, clase) in enumerate(esperados):
        pagina.keyboard.press("Tab")
        foco = pagina.evaluate(FOCO)
        if not foco or clase not in foco["clase"]:
            fallos.append(
                f"{donde}: la parada #{i + 1} de Tab tenía que ser «{nombre}» y cayó en "
                f"{foco['etiqueta'] if foco else None!r} [{foco['clase'] if foco else ''}]."
            )
            return " → ".join(visto) or "(sin recorrido)"
        visto.append(nombre)

        ancho = float(re.sub(r"[^\d.]", "", foco["outline"]["ancho"]) or 0)
        if ancho < 3 or foco["outline"]["estilo"] in ("none", "hidden"):
            fallos.append(
                f"{donde} «{nombre}»: el foco de teclado no dibuja outline "
                f"({foco['outline']['estilo']} {foco['outline']['ancho']})."
            )
        else:
            r = contraste(foco["outline"]["color"], foco["fondo"])
            if r is not None and r < 3.0:
                fallos.append(f"{donde} «{nombre}»: el outline da {r:.2f}:1 contra el fondo (<3).")

    # Que la salida sea la ÚLTIMA parada se comprueba contando los enfocables del
    # componente y no con un cuarto Tab: en Firefox headless el foco no sale del
    # último enlace y ese Tab mediría el navegador, no el componente.
    enfocables = pagina.evaluate(
        "() => [...document.querySelectorAll('.muni-res a[href],.muni-res button')].map(e => e.className)"
    )
    if not enfocables or "muni-res__salida" not in enfocables[-1]:
        fallos.append(
            f"{donde}: la salida no es lo último enfocable de la pantalla; el orden es {enfocables!r}. "
            "La ficha lo pide con esas palabras: «el último elemento es el único camino de salida»."
        )
    return " → ".join(visto)


def esperar_alpine(pagina) -> None:
    """Hasta que Alpine no corre, el botón sigue con x-cloak y no es enfocable.

    Sin esta espera el recorrido de Tab empezaba a veces en «imprimir» —el botón
    todavía estaba en display:none— y el fallo dependía de la carga de la
    máquina, no del componente.
    """
    pagina.wait_for_selector(".muni-res__copiar", state="visible", timeout=10000)


def copiar_y_leer(pagina) -> dict:
    """Pulsa «Copiar folio» y devuelve lo que quedó: aviso, foco y selección."""
    pagina.click(".muni-res__copiar")
    try:
        # El copiado es asíncrono (await sobre el portapapeles) y el aviso se
        # escribe en el $nextTick siguiente: se espera la CONDICIÓN, no un reloj.
        pagina.wait_for_function(
            "() => document.querySelector('.muni-res__aviso').textContent.trim() !== ''",
            timeout=5000,
        )
    except Exception:
        pass
    return pagina.evaluate(
        """() => ({
            aviso: document.querySelector('.muni-res__aviso').textContent.trim(),
            foco: document.activeElement ? document.activeElement.className : '',
            seleccion: (window.getSelection() || { toString: () => '' }).toString().trim(),
        })"""
    )


def verificar_cadena(navegador, ruta, donde: str, fallos: list[str]) -> str:
    """Los tres eslabones del copiado, cada uno forzado por separado."""
    eslabones = 0

    # 1. Portapapeles moderno (o lo que el navegador deje): el aviso se llena, el
    #    foco vuelve al botón y el SEGUNDO copiado vuelve a mutar la región.
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    pagina = contexto.new_page()
    pagina.goto(ruta.as_uri())
    esperar_alpine(pagina)
    r = copiar_y_leer(pagina)
    if not r["aviso"]:
        fallos.append(f"{donde} [portapapeles]: la región viva quedó vacía después de copiar: nadie anuncia nada.")
    elif FOLIO not in r["aviso"] and "No se pudo copiar" not in r["aviso"]:
        fallos.append(f"{donde} [portapapeles]: el aviso no dice qué pasó: {r['aviso']!r}")
    else:
        eslabones += 1
    if "muni-res__copiar" not in r["foco"]:
        fallos.append(
            f"{donde} [portapapeles]: después de copiar el foco quedó en {r['foco']!r} y no en el "
            "botón: la caja temporal del execCommand se destruye y el teclado se queda en el <body>."
        )

    pagina.evaluate(ESPIAR_AVISO)
    pagina.click(".muni-res__copiar")
    try:
        pagina.wait_for_function("() => window.__mutaciones > 0", timeout=3000)
    except Exception:
        pass
    espia = pagina.evaluate("() => ({ mutaciones: window.__mutaciones, vaciado: window.__vaciado })")
    if espia["mutaciones"] < 1 or not espia["vaciado"]:
        fallos.append(
            f"{donde}: el segundo copiado no vuelve a mutar la región viva "
            f"(mutaciones={espia['mutaciones']}, vaciado={espia['vaciado']}): el lector de pantalla "
            "se queda callado y la segunda confirmación es muda."
        )
    contexto.close()

    # 2. Sin portapapeles: es la intranet municipal servida por http.
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    contexto.add_init_script(SIN_PORTAPAPELES)
    pagina = contexto.new_page()
    pagina.goto(ruta.as_uri())
    esperar_alpine(pagina)
    r = copiar_y_leer(pagina)
    if not r["aviso"]:
        fallos.append(f"{donde} [execCommand]: sin `navigator.clipboard` el botón no hizo absolutamente nada.")
    elif "No se pudo copiar" in r["aviso"]:
        fallos.append(
            f"{donde} [execCommand]: la alternativa al portapapeles moderno no copió: {r['aviso']!r}"
        )
    else:
        eslabones += 1
    if "muni-res__copiar" not in r["foco"]:
        fallos.append(f"{donde} [execCommand]: el foco no volvió al botón, quedó en {r['foco']!r}.")
    contexto.close()

    # 3. Sin portapapeles NI execCommand: queda seleccionar el folio y decirlo.
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    contexto.add_init_script(SIN_NADA)
    pagina = contexto.new_page()
    pagina.goto(ruta.as_uri())
    esperar_alpine(pagina)
    r = copiar_y_leer(pagina)
    if "No se pudo copiar" not in r["aviso"]:
        fallos.append(
            f"{donde} [selección]: con las dos vías caídas el componente no avisa que no copió "
            f"({r['aviso']!r}): el vecino se va creyendo que tiene el folio."
        )
    elif FOLIO not in r["seleccion"]:
        fallos.append(
            f"{donde} [selección]: el folio no quedó seleccionado ({r['seleccion']!r}), así que el "
            "aviso miente: no hay nada que copiar con Control y C."
        )
    else:
        eslabones += 1
    contexto.close()

    return f"cadena={eslabones}/3"


def verificar_tarjeta(navegador, ruta, donde: str, fallos: list[str]) -> str:
    """`autofocus="false"`: ni enfoca el título ni jala el scroll."""
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    pagina = contexto.new_page()
    pagina.goto(ruta.as_uri())
    esperar_alpine(pagina)
    d = pagina.evaluate(SONDA)
    contexto.close()

    activo = d["activo"] or {}
    ok = True
    if "muni-res__titulo" in (activo.get("clase") or ""):
        ok = False
        fallos.append(
            f"{donde}: con autofocus=\"false\" el componente enfocó el título igual. La prop apagaba "
            "solo el ATRIBUTO y el init() de Alpine seguía llamando a focus() porque al cargar "
            "`activeElement` ES el body."
        )
    if d["scrollY"] > 2:
        ok = False
        fallos.append(
            f"{donde}: la página se desplazó sola hasta {d['scrollY']}px. Con varias tarjetas en la "
            "vitrina, cada una pelearía por el foco y por el scroll."
        )
    return "no-roba-foco" if ok else "ROBA-FOCO"


def verificar_sin_alpine(navegador, ruta, donde: str, fallos: list[str]) -> str:
    """Sin Alpine el botón no aparece, pero el folio sigue ahí."""
    contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
    pagina = contexto.new_page()
    pagina.goto(ruta.as_uri())
    pagina.wait_for_timeout(150)
    d = pagina.evaluate(SONDA)
    contexto.close()

    boton = d["copiarOculto"] or {}
    if boton.get("display") != "none":
        fallos.append(
            f"{donde}: sin Alpine el botón de copiar se dibuja igual (display={boton.get('display')!r}) "
            "y no hace nada al pulsarlo. Para eso está el x-cloak."
        )
    folio = next((p for p in d["piezas"] if p["nombre"] == "folio"), None)
    if folio is None or folio["ancho"] < 10:
        fallos.append(f"{donde}: sin Alpine el folio tampoco se ve: la pantalla se queda sin resultado.")
    elif folio["seleccion"] != "all":
        fallos.append(f"{donde}: sin Alpine el folio no se puede seleccionar de un clic ({folio['seleccion']!r}).")
    return "sin-alpine=ok"


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    faltan = [n for n in COMPLETAS + ("tarjeta.html", "sin-alpine.html") if not (carpeta / n).is_file()]
    if faltan:
        print(f"Banco incompleto en {carpeta}: faltan {', '.join(faltan)}; genera primero "
              "`vendor/bin/pest --filter=PantallaDeResultado`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()

            for pagina_nombre in COMPLETAS:
                ruta = carpeta / pagina_nombre
                donde = f"{nombre} {pagina_nombre}"

                # Escritorio, sin preferencia de movimiento: estructura, foco,
                # contraste, blanco táctil y recorrido de teclado.
                contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion="no-preference")
                pagina = contexto.new_page()
                pagina.goto(ruta.as_uri())
                esperar_alpine(pagina)
                d = pagina.evaluate(SONDA)

                verificar_estructura(d, donde, fallos)
                peor = verificar_contraste(d, donde, fallos)
                transicion = verificar_movimiento(d, donde, "no-preference", fallos)
                foco = verificar_foco_inicial(pagina, d, donde, fallos)
                teclado = verificar_teclado(pagina, donde, fallos)
                contexto.close()

                # Movimiento reducido: la transición tiene que computar 0 s.
                contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion="reduce")
                pagina = contexto.new_page()
                pagina.goto(ruta.as_uri())
                esperar_alpine(pagina)
                verificar_movimiento(pagina.evaluate(SONDA), f"{donde} [reduce]", "reduce", fallos)
                contexto.close()

                # Teléfono y pantalla estrecha: nada se desplaza en horizontal.
                for ancho_nombre, ancho, alto in ANCHOS:
                    contexto = navegador.new_context(viewport={"width": ancho, "height": alto})
                    pagina = contexto.new_page()
                    pagina.goto(ruta.as_uri())
                    esperar_alpine(pagina)
                    estrecho = pagina.evaluate(SONDA)
                    if estrecho["desbordaX"]:
                        fallos.append(f"{donde} [{ancho_nombre}/{ancho}px]: el documento se desplaza en horizontal (WCAG 1.4.10).")
                    verificar_estructura(estrecho, f"{donde} [{ancho_nombre}]", fallos)
                    contexto.close()

                cadena = verificar_cadena(navegador, ruta, donde, fallos)

                resumen.append(
                    f"{nombre:<9}{pagina_nombre:<18}{foco}  {cadena}  transición={transicion}  "
                    f"peor contraste={peor:.2f}:1  Tab: {teclado}"
                )

            tarjeta = verificar_tarjeta(navegador, carpeta / "tarjeta.html", f"{nombre} tarjeta.html", fallos)
            resumen.append(f"{nombre:<9}{'tarjeta.html':<18}{tarjeta}")

            sin_alpine = verificar_sin_alpine(navegador, carpeta / "sin-alpine.html", f"{nombre} sin-alpine.html", fallos)
            resumen.append(f"{nombre:<9}{'sin-alpine.html':<18}{sin_alpine}")

            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(
        f"\nTodo pasa: {len(NAVEGADORES)} navegadores × {len(COMPLETAS)} páginas completas "
        "(claro, oscuro y las dos del panel) + la tarjeta sin autofoco + la página sin Alpine."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
