#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::pantalla-bloqueo>` sobre `build/pantalla-bloqueo/`.

    .venv-a11y/bin/python tests/navegador/pantalla-bloqueo.py [build/pantalla-bloqueo]

La corre `tests/PantallaDeBloqueoTest.php` después de generar el banco (y se salta
si no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest
leen el TEXTO del fuente —que exista `x-trap.inert`, que Escape no asigne `false`,
que el velo sea opaco— y eso ya engañó una vez en `stat`: la regla pasó el test de
texto y en el navegador el defecto seguía vivo. Acá se mide, en Chromium Y Firefox:

  · el FOCO al aparecer cae en el campo de contraseña, sin tocar el ratón (ficha,
    tecla obligatoria 1), y su outline computa 3 px sólidos con 3:1 contra el
    fondo (WCAG 2.2 AA 2.4.7 y 1.4.11);
  · el contenido de ATRÁS queda INERTE de verdad: el plugin Focus le pone
    aria-hidden a todos los hermanos hasta el <body>, así que un lector de
    pantalla ya no puede leer el RUT ni el domicilio del vecino que quedó debajo
    («inert de verdad, no solo tapado»);
  · el recorrido de Tab tiene TRES paradas —campo, «Entrar», «Entrar como otro
    usuario»— y vuelve al campo: la trampa es cíclica y NUNCA cae en el botón de
    la ficha de atrás;
  · ESCAPE NO CIERRA: después de pulsarlo la capa sigue visible, el foco sigue
    dentro y la región viva lo dice con palabras;
  · el bloqueo por INACTIVIDAD aparece solo, se anuncia en la región viva y se
    lleva el foco al campo;
  · con ERROR del servidor: el `role="alert"` trae texto, el campo queda
    `aria-invalid` y su `aria-describedby` resuelve a ese mensaje;
  · el velo computa OPACO y con el color de fondo del tema: si dejara entrever la
    pantalla, el bloqueo no sirve de nada en un mesón (Ley 21.719);
  · todo texto pasa 4,5:1 (3:1 si es grande) contra su fondo efectivo, en las
    CUATRO páginas de capa: claro, oscuro y las dos del panel, que cargan
    únicamente `muni-ui-filament.css` (DESIGN §7);
  · el área táctil de los tres controles llega a 44 px (mesón y terreno);
  · con `prefers-reduced-motion: reduce` la transición computa 0 s, y sin la
    preferencia existe (si no, la comprobación sería vacía);
  · a 390 px y a 320 px el documento NO se desplaza en horizontal (1.4.10);
  · como PÁGINA propia (`modo="pagina"`): un solo <h1>, sin trampa, sin capa, y
    el mismo recorrido de tres paradas.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "pantalla-bloqueo"
NAVEGADORES = ("chromium", "firefox")
CAPAS = ("claro.html", "oscuro.html", "panel-claro.html", "panel-oscuro.html")
SUELTAS = ("error.html", "inactividad.html", "pagina.html", "con-guardia.html", "con-guardia-antes.html")
CONVIVENCIA_PAGINAS = ("con-guardia.html", "con-guardia-antes.html")
ANCHOS = (("telefono", 390, 780), ("estrecho", 320, 780))

SONDA = r"""
() => {
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
    const r = el.getBoundingClientRect();
    return {
      nombre,
      texto: (el.textContent || '').trim(),
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
      alto: r.height,
      ancho: r.width,
      display: c.display,
      transicion: c.transitionDuration,
    };
  };

  const activo = document.activeElement;
  const capa = document.querySelector('.muni-pb__capa');
  const velo = document.querySelector('.muni-pb__velo');
  const dialogo = document.querySelector('[role="dialog"]');
  const clave = document.querySelector('.muni-pb__clave');
  const detras = document.getElementById('detras');

  const describedby = clave ? clave.getAttribute('aria-describedby') : null;

  return {
    capa: capa ? { display: getComputedStyle(capa).display, opacidad: getComputedStyle(capa).opacity } : null,
    velo: velo ? { fondo: getComputedStyle(velo).backgroundColor, opacidad: getComputedStyle(velo).opacity } : null,
    dialogo: dialogo ? {
      modal: dialogo.getAttribute('aria-modal'),
      labelledby: dialogo.getAttribute('aria-labelledby'),
      nombrado: !!document.getElementById(dialogo.getAttribute('aria-labelledby') || ''),
      describedby: dialogo.getAttribute('aria-describedby'),
      descrito: !!document.getElementById(dialogo.getAttribute('aria-describedby') || ''),
      oculto: dialogo.closest('[aria-hidden="true"]') !== null,
    } : null,
    campo: clave ? {
      tipo: clave.getAttribute('type'),
      borde: getComputedStyle(clave).borderTopColor,
      fondo: fondoDe(clave.parentElement),
      invalido: clave.getAttribute('aria-invalid'),
      describedby,
      descrito: describedby ? !!document.getElementById(describedby) : null,
      textoDescripcion: describedby && document.getElementById(describedby)
        ? document.getElementById(describedby).textContent.trim() : '',
    } : null,
    alerta: (() => {
      const a = document.querySelector('[role="alert"]');
      return a ? { texto: a.textContent.trim() } : null;
    })(),
    viva: (() => {
      const v = document.querySelector('.muni-pb__sr');
      return v ? { rol: v.getAttribute('role'), vivo: v.getAttribute('aria-live'), texto: v.textContent.trim() } : null;
    })(),
    detras: detras ? {
      ocultoPorAria: detras.closest('[aria-hidden="true"]') !== null,
      inerte: detras.closest('[inert]') !== null,
    } : null,
    piezas: [
      leer('.muni-pb__nombre', 'nombre'),
      leer('.muni-pb__cargo', 'cargo'),
      leer('.muni-pb__titulo', 'titulo'),
      leer('.muni-pb__desc', 'mensaje'),
      leer('.muni-pb__etiqueta', 'etiqueta'),
      leer('.muni-pb__clave', 'clave'),
      leer('.muni-pb__entrar', 'entrar'),
      leer('.muni-pb__salida', 'salida'),
      leer('.muni-pb__error', 'error'),
    ].filter(Boolean),
    h1s: document.querySelectorAll('.muni-pb__panel h1').length,
    h2s: document.querySelectorAll('.muni-pb__panel h2').length,
    activo: activo ? { etiqueta: activo.tagName, clase: activo.className || '', id: activo.id || '' } : null,
    desbordaX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    scrollY: window.scrollY,
  };
}
"""

CONVIVENCIA = r"""
() => {
  const capa = document.querySelector('.muni-pb__capa');
  const panel = document.querySelector('.muni-pb__panel');
  const guardia = document.querySelector('.muni-sg__capa');
  const pii = document.getElementById('pii');
  const zDe = el => el ? getComputedStyle(el).zIndex : null;
  const oculto = el => el ? (el.closest('[aria-hidden="true"]') !== null || el.closest('[inert]') !== null) : null;

  const quienPinta = el => {
    if (! el) return 'nada';
    const r = el.getBoundingClientRect();
    const arriba = document.elementFromPoint(Math.round(r.left + r.width / 2), Math.round(r.top + r.height / 2));
    if (! arriba) return 'nada';
    if (arriba.closest('.muni-pb__capa')) return 'bloqueo';
    if (arriba.closest('.muni-sg__capa')) return 'guardia';
    return 'otro';
  };

  const activo = document.activeElement;
  const dondeElFoco = ! activo || activo === document.body ? 'nadie'
    : (activo.closest('.muni-pb__capa') ? 'bloqueo' : (activo.closest('.muni-sg__capa') ? 'guardia' : 'otro'));

  return {
    hayGuardia: !! guardia,
    guardiaVisible: guardia ? getComputedStyle(guardia).display !== 'none' : false,
    guardiaOculta: oculto(guardia),
    bloqueoVisible: capa ? getComputedStyle(capa).display !== 'none' : false,
    bloqueoOculto: oculto(capa),
    zBloqueo: zDe(capa),
    zGuardia: zDe(guardia),
    encimaDelPanel: quienPinta(panel),
    piiTapado: pii ? quienPinta(pii) !== 'otro' && quienPinta(pii) !== 'nada' : null,
    piiFueraDelArbol: oculto(pii),
    dondeElFoco,
    focoClase: activo ? (activo.className || '') : '',
  };
}
"""

FOCO = r"""
() => {
  const el = document.activeElement;
  if (!el) return null;
  const c = getComputedStyle(el);
  const fondoDe = n => {
    for (let x = n; x; x = x.parentElement) {
      const bg = getComputedStyle(x).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };
  // El anillo se dibuja DESPLAZADO hacia afuera (outline-offset), o sea sobre la
  // superficie que rodea al control y no sobre su propio relleno. «Entrar» es un
  // botón primario pintado con --muni-accent, y --muni-focus vale lo mismo: medirlo
  // contra su relleno daría 1:1 y la medición estaría mintiendo. Mismo criterio que
  // tests/navegador/pantalla-resultado.py.
  const desplazado = parseFloat(c.outlineOffset) >= 0 && el.parentElement;
  return {
    etiqueta: el.tagName,
    clase: el.className || '',
    id: el.id || '',
    fondo: fondoDe(desplazado ? el.parentElement : el),
    outline: { ancho: c.outlineWidth, estilo: c.outlineStyle, color: c.outlineColor, desplazamiento: c.outlineOffset },
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
    """4,5:1, o 3:1 si el texto es grande (≥24px, o ≥18.66px en negrita)."""
    grande = tamano >= 24 or (tamano >= 18.66 and peso >= 700)
    return 3.0 if grande else 4.5


def abrir(navegador, ruta: Path, **kwargs):
    contexto = navegador.new_context(viewport=kwargs.pop("viewport", {"width": 1440, "height": 900}), **kwargs)
    pagina = contexto.new_page()
    pagina.goto(ruta.as_uri())
    return contexto, pagina


def esperar_capa(pagina) -> None:
    """Hasta que la capa no está visible, no hay nada que medir (x-cloak + x-show)."""
    pagina.wait_for_selector(".muni-pb__capa", state="visible", timeout=10000)
    # El plugin Focus arma la trampa 15 ms después de abrir y el aria-hidden de
    # los hermanos lo pone en el nextTick siguiente: se espera la CONDICIÓN.
    try:
        pagina.wait_for_function(
            "() => document.activeElement && document.activeElement.classList.contains('muni-pb__clave')",
            timeout=5000,
        )
    except Exception:
        pass


def verificar_estructura(d: dict, donde: str, fallos: list[str], capa: bool) -> None:
    if not d["piezas"]:
        fallos.append(f"{donde}: no hay panel de bloqueo que medir.")
        return

    if capa:
        if d["capa"] is None or d["capa"]["display"] == "none":
            fallos.append(f"{donde}: la capa no está visible.")
        if d["dialogo"] is None:
            fallos.append(f"{donde}: no hay elemento con role=\"dialog\".")
        else:
            if d["dialogo"]["modal"] != "true":
                fallos.append(f"{donde}: el diálogo no declara aria-modal=\"true\".")
            if not d["dialogo"]["nombrado"]:
                fallos.append(
                    f"{donde}: el aria-labelledby apunta a un id inexistente "
                    f"({d['dialogo']['labelledby']!r}): el diálogo se anuncia sin nombre."
                )
            if not d["dialogo"]["descrito"]:
                fallos.append(f"{donde}: el aria-describedby del diálogo no resuelve.")
            if d["dialogo"]["oculto"]:
                fallos.append(
                    f"{donde}: el propio diálogo quedó bajo un aria-hidden. Es lo que pasa cuando la "
                    "trampa de OTRA capa lo marcó: el lector no anuncia lo que tiene el foco."
                )
        if d["h1s"] != 0 or d["h2s"] != 1:
            fallos.append(
                f"{donde}: la capa emite {d['h1s']} <h1> y {d['h2s']} <h2>; tiene que ser 0 y 1 "
                "(el <h1> es el de la página que quedó debajo)."
            )
        if d["velo"] is None:
            fallos.append(f"{donde}: no hay velo.")
        else:
            if float(d["velo"]["opacidad"]) < 0.999:
                fallos.append(
                    f"{donde}: el velo computa opacidad {d['velo']['opacidad']}: deja entrever la "
                    "ficha del vecino desde el otro lado del mesón (Ley 21.719)."
                )
            rgb = a_rgb(d["velo"]["fondo"])
            if rgb is None or d["velo"]["fondo"] in ("transparent",):
                fallos.append(f"{donde}: el velo no pinta fondo ({d['velo']['fondo']!r}).")
    else:
        if d["h1s"] != 1:
            fallos.append(f"{donde}: como página propia tiene que haber un <h1> y hay {d['h1s']}.")
        if d["capa"] is not None:
            fallos.append(f"{donde}: la página propia no puede emitir la capa fija.")

    campo = d["campo"]
    if campo is None:
        fallos.append(f"{donde}: no hay campo de contraseña.")
    elif campo["tipo"] != "password":
        fallos.append(f"{donde}: el campo no es de tipo password ({campo['tipo']!r}).")

    for nombre in ("clave", "entrar", "salida"):
        p = next((x for x in d["piezas"] if x["nombre"] == nombre), None)
        if p is None:
            fallos.append(f"{donde}: falta el control «{nombre}».")
        # Medio píxel de tolerancia: la caja se declara en 44 px exactos y el
        # navegador devuelve 43,999… según cómo caiga el redondeo del subpíxel.
        elif p["alto"] < 43.5:
            fallos.append(
                f"{donde} «{nombre}»: el blanco táctil mide {p['alto']:.2f}px de alto (<44). Es la "
                "pantalla del mesón, que se atiende de pie."
            )


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 99.0
    for p in d["piezas"]:
        if not p["texto"]:
            continue
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


def verificar_inerte(d: dict, donde: str, fallos: list[str]) -> str:
    """El contenido de atrás no puede quedar solo tapado: tiene que salir del árbol."""
    detras = d["detras"]
    if detras is None:
        fallos.append(f"{donde}: el banco no trae la ficha de atrás; la comprobación sería vacía.")
        return "inerte=?"
    if not (detras["ocultoPorAria"] or detras["inerte"]):
        fallos.append(
            f"{donde}: la ficha del vecino que quedó debajo sigue en el árbol de accesibilidad "
            "(sin aria-hidden ni inert). La ficha lo pide con estas palabras: «nada de atrás es "
            "alcanzable: inert de verdad, no solo tapado»."
        )
        return "inerte=NO"
    return "inerte=si"


def medir_foco(pagina, donde: str, quien: str, fallos: list[str]) -> dict:
    foco = pagina.evaluate(FOCO) or {}
    ancho = float(re.sub(r"[^\d.]", "", foco.get("outline", {}).get("ancho", "0")) or 0)
    if ancho < 3 or foco.get("outline", {}).get("estilo") in ("none", "hidden"):
        fallos.append(
            f"{donde} «{quien}»: el foco no dibuja outline "
            f"({foco.get('outline', {}).get('estilo')} {foco.get('outline', {}).get('ancho')}). "
            "La box-shadow del anillo no cuenta: dentro de Filament se computa transparente (DESIGN §5)."
        )
    else:
        r = contraste(foco["outline"]["color"], foco["fondo"])
        if r is not None and r < 3.0:
            fallos.append(f"{donde} «{quien}»: el outline da {r:.2f}:1 contra el fondo (<3, WCAG 1.4.11).")
    return foco


def verificar_foco_inicial(pagina, d: dict, donde: str, fallos: list[str]) -> str:
    activo = d["activo"] or {}
    if "muni-pb__clave" not in (activo.get("clase") or ""):
        fallos.append(
            f"{donde}: al aparecer el bloqueo el foco quedó en {activo.get('etiqueta')!r} "
            f"[{activo.get('clase')}] y no en el campo de contraseña (ficha, tecla obligatoria 1)."
        )
        return "foco=" + str(activo.get("etiqueta", "?")).lower()
    medir_foco(pagina, donde, "clave", fallos)
    return "foco=clave"


def verificar_teclado(pagina, donde: str, fallos: list[str], capa: bool) -> str:
    """Tab: campo → Entrar → Entrar como otro usuario, y de vuelta al campo."""
    pagina.evaluate("() => { const c = document.querySelector('.muni-pb__clave'); c && c.focus(); }")

    esperados = [("entrar", "muni-pb__entrar"), ("salida", "muni-pb__salida"), ("clave", "muni-pb__clave")]
    visto = ["clave"]

    for i, (nombre, clase) in enumerate(esperados):
        pagina.keyboard.press("Tab")
        foco = pagina.evaluate(FOCO) or {}
        if clase not in (foco.get("clase") or ""):
            # La tercera parada solo vuelve al campo si la trampa es cíclica; en la
            # página propia, en cambio, el foco se va al navegador y eso es correcto.
            if nombre == "clave" and not capa:
                visto.append("(sale de la pantalla)")
                break
            fallos.append(
                f"{donde}: la parada #{i + 1} de Tab tenía que ser «{nombre}» y cayó en "
                f"{foco.get('etiqueta')!r} [{foco.get('clase')}] id={foco.get('id')!r}."
            )
            return " → ".join(visto) or "(sin recorrido)"
        visto.append(nombre)
        if nombre != "clave":
            medir_foco(pagina, donde, nombre, fallos)

    if capa:
        # Y nunca, en ninguna vuelta, el teclado alcanza la ficha de atrás.
        for _ in range(6):
            pagina.keyboard.press("Tab")
            foco = pagina.evaluate(FOCO) or {}
            if foco.get("id") in ("detras", "detras-enlace"):
                fallos.append(
                    f"{donde}: tabulando se llega a «{foco.get('id')}», que está DEBAJO del bloqueo. "
                    "La trampa de foco no está atrapando nada."
                )
                return " → ".join(visto) + " → ¡ATRÁS!"

    return " → ".join(visto)


def verificar_escape(pagina, donde: str, fallos: list[str]) -> str:
    """Escape NO cierra: la ficha lo dice sin rodeos."""
    pagina.keyboard.press("Escape")
    pagina.wait_for_timeout(120)
    d = pagina.evaluate(SONDA)

    if d["capa"] is None or d["capa"]["display"] == "none":
        fallos.append(
            f"{donde}: Escape cerró el bloqueo. Un bloqueo que se descarta con una tecla no protege "
            "nada: la ficha del vecino vuelve a la vista del público."
        )
        return "escape=CIERRA"

    activo = d["activo"] or {}
    if "muni-pb" not in (activo.get("clase") or ""):
        fallos.append(
            f"{donde}: después de Escape el foco se fue a {activo.get('etiqueta')!r} "
            f"[{activo.get('clase')}]: la trampa se soltó."
        )
        return "escape=SUELTA-FOCO"

    if not (d["viva"] and d["viva"]["texto"]):
        fallos.append(
            f"{donde}: Escape no dice nada. Quien no ve la pantalla pulsa Escape y se queda sin "
            "saber por qué no pasó nada; la región viva tiene que explicarlo."
        )

    return "escape=sigue-bloqueado"


def verificar_movimiento(d: dict, donde: str, movimiento: str, fallos: list[str]) -> str:
    duraciones = {p["transicion"] for p in d["piezas"] if p["nombre"] in ("clave", "entrar", "salida")}
    texto = ", ".join(sorted(duraciones))
    vivas = [t for t in duraciones if re.search(r"(?<![\d.])(?!0s)[\d.]+m?s", t)]
    if movimiento == "reduce" and vivas:
        fallos.append(f"{donde}: con movimiento reducido la transición sigue en {texto}; tiene que computar 0 s.")
    if movimiento == "no-preference" and not vivas:
        fallos.append(f"{donde}: sin la preferencia no hay transición ninguna ({texto}): medir «reduce» sería vacío.")
    return texto


def verificar_error(navegador, ruta: Path, donde: str, fallos: list[str]) -> str:
    contexto, pagina = abrir(navegador, ruta)
    esperar_capa(pagina)
    d = pagina.evaluate(SONDA)
    contexto.close()

    ok = True
    if not (d["alerta"] and d["alerta"]["texto"]):
        ok = False
        fallos.append(f"{donde}: el error del servidor no llega como role=\"alert\" con texto.")
    campo = d["campo"] or {}
    if campo.get("invalido") != "true":
        ok = False
        fallos.append(f"{donde}: el campo no queda aria-invalid con el error (aria-invalid={campo.get('invalido')!r}).")
    if not campo.get("descrito"):
        ok = False
        fallos.append(f"{donde}: el aria-describedby del campo no resuelve ({campo.get('describedby')!r}).")
    elif "contraseña no coincide" not in campo.get("textoDescripcion", ""):
        ok = False
        fallos.append(f"{donde}: el campo describe otra cosa: {campo.get('textoDescripcion')!r}.")
    activo = d["activo"] or {}
    if "muni-pb__clave" not in (activo.get("clase") or ""):
        ok = False
        fallos.append(
            f"{donde}: con error el foco tiene que volver al campo y quedó en {activo.get('clase')!r} "
            "(ficha: «el foco vuelve al campo»)."
        )
    # El borde rojo del campo inválido tiene que VERSE contra la tarjeta: es un
    # indicador no textual (WCAG 2.2 AA 1.4.11). No es el único portador —el texto
    # del error lo dice— pero un borde que no se distingue del fondo no indica nada.
    r = contraste(campo.get("borde", ""), campo.get("fondo", ""))
    if r is not None and r < 3.0:
        ok = False
        fallos.append(
            f"{donde}: el borde del campo inválido da {r:.2f}:1 contra la tarjeta (<3, WCAG 1.4.11)."
        )

    verificar_contraste(d, donde, fallos)
    return "error=ok" if ok else "error=FALLA"


def verificar_inactividad(navegador, ruta: Path, donde: str, fallos: list[str]) -> str:
    """El banco declara inactividad=1 s: la capa tiene que aparecer sola."""
    contexto, pagina = abrir(navegador, ruta)
    try:
        pagina.wait_for_selector(".muni-pb__capa", state="visible", timeout=8000)
    except Exception:
        contexto.close()
        fallos.append(
            f"{donde}: pasado el plazo de inactividad la pantalla NO se bloqueó sola. Es lo único "
            "que la ficha pide que haga el componente por su cuenta."
        )
        return "inactividad=NO"

    try:
        pagina.wait_for_function(
            "() => document.activeElement && document.activeElement.classList.contains('muni-pb__clave')",
            timeout=4000,
        )
    except Exception:
        pass

    d = pagina.evaluate(SONDA)
    contexto.close()

    ok = True
    activo = d["activo"] or {}
    if "muni-pb__clave" not in (activo.get("clase") or ""):
        ok = False
        fallos.append(f"{donde}: al bloquearse por inactividad el foco no fue al campo ({activo.get('clase')!r}).")
    if not (d["viva"] and "inactividad" in d["viva"]["texto"].lower()):
        ok = False
        fallos.append(
            f"{donde}: el bloqueo automático no se anuncia ({d['viva']!r}). Quien no mira la pantalla "
            "no se entera de que dejó de poder escribir."
        )
    verificar_inerte(d, donde, fallos)
    return "inactividad=ok" if ok else "inactividad=FALLA"


def verificar_convivencia(navegador, ruta: Path, donde: str, fallos: list[str]) -> str:
    """El bloqueo y `sesion-guardia` en la MISMA pantalla, que es como viven en el mesón.

    No es un caso raro: es LA secuencia normal. Si nadie toca el teclado salta el bloqueo
    por inactividad, y el reloj de la sesión sigue corriendo hasta T=0. Los dos componentes
    se teletransportan al <body>, los dos atrapan el foco con `x-trap.inert` y los dos
    pintan un velo opaco fijo. Medido antes del arreglo: cada trampa estampaba un
    aria-hidden sobre la raíz de la otra y NINGUNO de los dos diálogos quedaba en el árbol
    de accesibilidad —se veía un diálogo con el foco dentro que el lector no anunciaba—, y
    cuál de los dos velos tapaba al otro lo decidía el orden del marcado.

    Lo que se exige acá es la coherencia entre las tres cosas que el usuario percibe:
    lo que está ARRIBA es lo que tiene el FOCO y es lo que el lector puede ANUNCIAR; y,
    pase lo que pase entre los dos, la ficha del vecino sigue tapada y fuera del árbol.
    Se mide en los dos órdenes de marcado para que el resultado no sea un accidente.
    """
    contexto, pagina = abrir(navegador, ruta)
    try:
        pagina.wait_for_selector(".muni-pb__capa", state="visible", timeout=8000)
    except Exception:
        contexto.close()
        fallos.append(f"{donde}: el bloqueo no llegó a verse conviviendo con la guardia.")
        return "convivencia=NO-ABRE"

    # La guardia entra en T=0 dentro de su propio init(); se le da el tiempo de armar la
    # trampa, estampar el aria-hidden en los hermanos y llevarse el foco.
    pagina.wait_for_timeout(500)
    d = pagina.evaluate(CONVIVENCIA)
    contexto.close()

    if not d["hayGuardia"] or not d["guardiaVisible"]:
        fallos.append(f"{donde}: la guardia no llegó a T=0 en el banco; la comprobación sería vacía.")
        return "convivencia=BANCO-VACIO"
    if not d["bloqueoVisible"]:
        fallos.append(f"{donde}: el bloqueo dejó de verse; no hay convivencia que medir.")
        return "convivencia=BANCO-VACIO"

    ok = True
    arriba = d["encimaDelPanel"]

    # 1. Quien pinta arriba es quien tiene el foco. El plugin Focus le da el foco a la
    #    ÚLTIMA trampa activada; si la pintura dijera otra cosa, el funcionario estaría
    #    escribiendo en un diálogo que no ve.
    if arriba != d["dondeElFoco"]:
        ok = False
        fallos.append(
            f"{donde}: arriba pinta «{arriba}» y el foco está en «{d['dondeElFoco']}» "
            f"[{d['focoClase']}] (z bloqueo={d['zBloqueo']}, z guardia={d['zGuardia']}). "
            "El funcionario estaría escribiendo en un diálogo tapado por el velo del otro."
        )

    # 2. Y lo que pinta arriba tiene que poder anunciarse: las dos trampas se estampan
    #    aria-hidden la una a la otra, y así ninguna de las dos quedaba en el árbol.
    if arriba == "guardia" and d["guardiaOculta"]:
        ok = False
        fallos.append(
            f"{donde}: la guardia manda (tiene el foco y pinta arriba) pero quedó bajo el "
            "aria-hidden que le puso la trampa del bloqueo: el lector de pantalla no anuncia "
            "el único diálogo que el funcionario puede usar."
        )
    if arriba == "bloqueo" and d["bloqueoOculto"]:
        ok = False
        fallos.append(
            f"{donde}: el bloqueo manda pero quedó bajo un aria-hidden ajeno: se ve, tiene el "
            "foco y el lector no lo anuncia."
        )

    # 3. Pase lo que pase entre los dos, la ficha del vecino sigue tapada y fuera del árbol.
    if not d["piiTapado"]:
        ok = False
        fallos.append(
            f"{donde}: la ficha del vecino volvió a verse en pantalla mientras los dos velos "
            "se disputan el frente (Ley 21.719)."
        )
    if not d["piiFueraDelArbol"]:
        ok = False
        fallos.append(
            f"{donde}: la ficha del vecino volvió al árbol de accesibilidad. El `inert` de "
            "Alpine deshace con una caché booleana: si una de las dos capas suelta su trampa, "
            "destapa lo que la otra creía tapado y un lector de pantalla lee el RUT."
        )

    return (
        f"convivencia={'ok' if ok else 'FALLA'} arriba={arriba} foco={d['dondeElFoco']} "
        f"z={d['zBloqueo']}/{d['zGuardia']}"
    )


def verificar_pagina(navegador, ruta: Path, donde: str, fallos: list[str]) -> str:
    contexto, pagina = abrir(navegador, ruta)
    pagina.wait_for_selector(".muni-pb__panel", state="visible", timeout=8000)
    d = pagina.evaluate(SONDA)

    verificar_estructura(d, donde, fallos, capa=False)
    verificar_contraste(d, donde, fallos)
    teclado = verificar_teclado(pagina, donde, fallos, capa=False)

    # Sin Alpine no puede haber diferencia: la página propia no lleva ni una
    # expresión (la ficha: «Alpine: ninguno si es página»).
    marcas = pagina.evaluate(
        "() => [...document.querySelectorAll('.muni-pb *')].some(e => [...e.attributes].some(a => a.name.startsWith('x-')))"
    )
    if marcas:
        fallos.append(f"{donde}: la página propia trae directivas de Alpine; la ficha pide ninguna.")

    contexto.close()
    return f"pagina: Tab: {teclado}"


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    faltan = [n for n in CAPAS + SUELTAS if not (carpeta / n).is_file()]
    if faltan:
        print(f"Banco incompleto en {carpeta}: faltan {', '.join(faltan)}; genera primero "
              "`vendor/bin/pest --filter=PantallaDeBloqueo`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()

            for pagina_nombre in CAPAS:
                ruta = carpeta / pagina_nombre
                donde = f"{nombre} {pagina_nombre}"

                contexto, pagina = abrir(navegador, ruta, reduced_motion="no-preference")
                esperar_capa(pagina)
                d = pagina.evaluate(SONDA)

                verificar_estructura(d, donde, fallos, capa=True)
                peor = verificar_contraste(d, donde, fallos)
                transicion = verificar_movimiento(d, donde, "no-preference", fallos)
                foco = verificar_foco_inicial(pagina, d, donde, fallos)
                inerte = verificar_inerte(d, donde, fallos)
                teclado = verificar_teclado(pagina, donde, fallos, capa=True)
                escape = verificar_escape(pagina, donde, fallos)
                contexto.close()

                # Movimiento reducido: la transición tiene que computar 0 s.
                contexto, pagina = abrir(navegador, ruta, reduced_motion="reduce")
                esperar_capa(pagina)
                verificar_movimiento(pagina.evaluate(SONDA), f"{donde} [reduce]", "reduce", fallos)
                contexto.close()

                # Teléfono y pantalla estrecha: nada se desplaza en horizontal.
                for ancho_nombre, ancho, alto in ANCHOS:
                    contexto, pagina = abrir(navegador, ruta, viewport={"width": ancho, "height": alto})
                    esperar_capa(pagina)
                    estrecho = pagina.evaluate(SONDA)
                    if estrecho["desbordaX"]:
                        fallos.append(
                            f"{donde} [{ancho_nombre}/{ancho}px]: el documento se desplaza en horizontal (WCAG 1.4.10)."
                        )
                    verificar_estructura(estrecho, f"{donde} [{ancho_nombre}]", fallos, capa=True)
                    contexto.close()

                resumen.append(
                    f"{nombre:<9}{pagina_nombre:<16}{foco}  {inerte}  {escape}  "
                    f"transición={transicion}  peor contraste={peor:.2f}:1  Tab: {teclado}"
                )

            resumen.append(f"{nombre:<9}{'error.html':<16}" + verificar_error(navegador, carpeta / "error.html", f"{nombre} error.html", fallos))
            resumen.append(f"{nombre:<9}{'inactividad':<16}" + verificar_inactividad(navegador, carpeta / "inactividad.html", f"{nombre} inactividad.html", fallos))
            resumen.append(f"{nombre:<9}{'pagina.html':<16}" + verificar_pagina(navegador, carpeta / "pagina.html", f"{nombre} pagina.html", fallos))
            for convivencia in CONVIVENCIA_PAGINAS:
                resumen.append(f"{nombre:<9}{convivencia[:-5]:<16}" + verificar_convivencia(
                    navegador, carpeta / convivencia, f"{nombre} {convivencia}", fallos))

            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(
        f"\nTodo pasa: {len(NAVEGADORES)} navegadores × {len(CAPAS)} páginas de capa "
        "(claro, oscuro y las dos del panel) + error + inactividad + página propia + "
        "convivencia con `sesion-guardia`."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
