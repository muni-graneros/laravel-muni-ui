#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::ajustes-cuenta>` sobre `build/ajustes-cuenta/`.

    .venv-a11y/bin/python tests/navegador/ajustes-cuenta.py [build/ajustes-cuenta]

La corre `tests/AjustesDeCuentaTest.php` después de generar el banco (y se salta si
no está el entorno de la reja, que en CI no se instala).

POR QUÉ EXISTE. Este componente es TODO interacción —pestañas, diálogo de
confirmación, puerta de la zona de peligro— y las pruebas de Pest leen TEXTO del
fuente o del HTML servido. Eso ya dejó pasar el defecto más caro de la ficha: el
estado «en espera» del botón destructivo NO se dibujaba. `border-style:dashed` y
`cursor:not-allowed` son reglas de CLASE, y `<x-muni::button>` escribe
`border:1px solid transparent;` y `cursor:pointer;` en el atributo `style`, que
gana a cualquier regla de autor sin `!important`. El botón se veía idéntico
habilitado y en espera, y la prueba que lo vigilaba pasaba igual porque solo
buscaba la cadena `opacity:` en el fuente. Es la misma trampa que ya documenta
`tests/navegador/pantalla-bloqueo.py`: «la regla pasó el test de texto y en el
navegador el defecto seguía vivo».

Qué se mide, en Chromium Y Firefox, sobre las cuatro páginas (claro, oscuro y las
dos del panel, que cargan únicamente `muni-ui-filament.css` — DESIGN §7):

  · LAS PESTAÑAS SON LAS DE VERDAD: un `role="tablist"`, cuatro `role="tab"`, un
    solo panel visible, y ←/→ mueven foco y selección de forma circular, con
    Inicio y Fin (ficha, tecla obligatoria 2);
  · EL ESTADO «EN ESPERA» SE DIBUJA: el botón destructivo computa
    `border-top-style: dashed` y `cursor: not-allowed` mientras la palabra no
    coincide, y vuelve a `solid` / `pointer` cuando coincide. Es el hallazgo de
    arriba, medido en píxeles computados y no en el fuente;
  · LA PUERTA BLOQUEA DE VERDAD: con el campo vacío y con una palabra parecida
    («elimina» contra «ELIMINAR»), el `submit` llega a `window` ya con
    `defaultPrevented`, el aviso vivo trae texto y el foco vuelve al campo. Con la
    palabra EXACTA, el `submit` pasa;
  · EL DIÁLOGO CONFIRMA: el clic en «Cerrar sesión» no envía, abre un
    `role="alertdialog"` con el foco dentro (en Cancelar), Escape lo cierra sin
    enviar, y el botón del pie —que vive teletransportado en `<body>`, fuera del
    `<form>`— envía el POST de la sesión correcta gracias a `form=`;
  · LA SESIÓN ACTUAL NO OFRECE BOTÓN: cerrarse a sí mismo desde esa lista es el
    clic que se da por error;
  · el foco se ve: outline de 3 px con 3:1 contra su fondo (WCAG 2.4.7 y 1.4.11);
  · todo texto visible pasa 4,5:1 (3:1 si es grande) contra su fondo efectivo;
  · el blanco táctil de las acciones destructivas llega a 44 px;
  · con `prefers-reduced-motion: reduce` la transición del botón computa 0 s (y
    sin la preferencia existe, si no la comprobación sería vacía);
  · a 390 px y a 320 px el documento NO se desplaza en horizontal (1.4.10).

Y dos páginas más:

  · `una-sesion.html`: con UNA sola sesión ajena listada, la pantalla NO puede
    imprimir «No hay otras sesiones abiertas.». Es el defecto de contar sesiones
    en vez de sesiones ajenas, y este bloque es el que la ficha marca como el de
    riesgo;
  · SIN JAVASCRIPT (contexto con `java_script_enabled=False`) sobre `claro.html`:
    los cuatro paneles quedan visibles, el grupo de pestañas se oculta —sin
    Alpine sus botones no hacen nada— y los dos `<form method="post">` siguen
    alcanzables, con el botón destructivo SIN `disabled`. Sin el `<noscript>` del
    componente, `[x-cloak] { display:none !important }` de las dos hojas dejaba
    la pantalla entera invisible y la frase «sin JS el formulario envía igual»
    era falsa.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "ajustes-cuenta"
NAVEGADORES = ("chromium", "firefox")
PAGINAS = ("claro.html", "oscuro.html", "panel-claro.html", "panel-oscuro.html")
ANCHOS = (("telefono", 390, 800), ("estrecho", 320, 800))

PALABRA = "ELIMINAR"
PARECIDA = "elimina"

# Recorre el panel visible y devuelve todo lo medible: textos con su color y su
# fondo efectivo, cajas de los controles y el estado del botón destructivo.
SONDA = r"""
() => {
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  const visible = el => {
    if (!el) return false;
    const c = getComputedStyle(el);
    if (c.display === 'none' || c.visibility === 'hidden') return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };

  const medir = (el, nombre) => {
    const c = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return {
      nombre,
      texto: (el.textContent || '').trim().slice(0, 60),
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
      alto: r.height,
      ancho: r.width,
    };
  };

  const raiz = document.querySelector('.muni-ajc');
  const paneles = [...document.querySelectorAll('.muni-ajc [role="tabpanel"]')];
  const pestanas = [...document.querySelectorAll('.muni-ajc [role="tab"]')];
  const lista = document.querySelector('.muni-ajc [role="tablist"]');

  const textos = [];
  for (const panel of paneles) {
    if (!visible(panel)) continue;
    for (const el of panel.querySelectorAll('span,p,label,button,h2,h3,li,div,legend,small')) {
      if (!visible(el)) continue;
      // Solo nodos de texto PROPIOS: si no, cada contenedor repite el texto de sus
      // hijos y el mismo par color/fondo se mide diez veces.
      const propio = [...el.childNodes]
        .filter(n => n.nodeType === 3)
        .map(n => n.textContent.trim())
        .join(' ')
        .trim();
      if (!propio) continue;
      textos.push(medir(el, propio.slice(0, 40)));
    }
  }

  const destruir = document.querySelector('.muni-ajc__destruir');
  const cd = destruir ? getComputedStyle(destruir) : null;

  const acciones = [...document.querySelectorAll('.muni-ajc__accion')]
    .filter(visible)
    .map(el => medir(el, el.className.includes('destruir') ? 'destruir' : 'cerrar'));

  const dialogo = document.querySelector('[role="alertdialog"]');

  return {
    hayRaiz: !!raiz,
    pestanas: pestanas.map(b => ({
      texto: b.textContent.trim(),
      seleccionada: b.getAttribute('aria-selected'),
      tabindex: b.getAttribute('tabindex'),
      controla: b.getAttribute('aria-controls'),
      visible: visible(b),
    })),
    listaVisible: visible(lista),
    panelesVisibles: paneles.filter(visible).length,
    panelesTotales: paneles.length,
    panelVisibleId: (paneles.find(visible) || {}).id || '',
    textos,
    acciones,
    destruir: destruir ? {
      ariaDisabled: destruir.getAttribute('aria-disabled'),
      desactivado: destruir.hasAttribute('disabled'),
      bordeEstilo: cd.borderTopStyle,
      bordeColor: cd.borderTopColor,
      cursor: cd.cursor,
      transicion: cd.transitionDuration,
      fondo: fondoDe(destruir),
      clase: destruir.className,
    } : null,
    aviso: (() => {
      const a = document.querySelector('.muni-ajc__aviso');
      return a ? { texto: a.textContent.trim(), vivo: a.getAttribute('aria-live'), rol: a.getAttribute('role') } : null;
    })(),
    formularios: [...document.querySelectorAll('.muni-ajc form')].map(f => ({
      metodo: (f.getAttribute('method') || '').toLowerCase(),
      accion: f.getAttribute('action'),
      id: f.id,
      token: !!f.querySelector('input[name="_token"]'),
      sesion: (f.querySelector('input[name="sesion"]') || {}).value || null,
      visible: visible(f),
    })),
    marcaActual: (() => {
      const m = document.querySelector('.muni-ajc__marca');
      return m ? m.textContent.trim() : null;
    })(),
    vacio: (() => {
      const v = document.querySelector('.muni-ajc__vacio');
      return v && visible(v) ? v.textContent.trim() : null;
    })(),
    dialogo: dialogo ? {
      visible: visible(dialogo),
      modal: dialogo.getAttribute('aria-modal'),
      labelledby: dialogo.getAttribute('aria-labelledby'),
      nombrado: !!document.getElementById(dialogo.getAttribute('aria-labelledby') || ''),
      describedby: dialogo.getAttribute('aria-describedby'),
      descrito: !!document.getElementById(dialogo.getAttribute('aria-describedby') || ''),
      confirmaA: (() => {
        const b = dialogo.querySelector('[form]');
        return b ? b.getAttribute('form') : null;
      })(),
      tieneFoco: dialogo.contains(document.activeElement),
    } : null,
    envios: window.__envios || [],
    desplazaH: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  };
}
"""

FOCO = r"""
() => {
  const el = document.activeElement;
  if (!el || el === document.body) return null;
  const fondoDe = n => {
    for (let x = n; x; x = x.parentElement) {
      const bg = getComputedStyle(x).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };
  const c = getComputedStyle(el);
  // Con outline-offset >= 0 el anillo se dibuja FUERA del control: se mide contra
  // la superficie que lo rodea, no contra su propio relleno. Mismo criterio que
  // tests/navegador/pantalla-bloqueo.py.
  const desplazado = parseFloat(c.outlineOffset) >= 0 && el.parentElement;
  return {
    etiqueta: el.tagName,
    clase: typeof el.className === 'string' ? el.className : '',
    id: el.id || '',
    rol: el.getAttribute('role') || '',
    texto: (el.textContent || '').trim().slice(0, 40),
    fondo: fondoDe(desplazado ? el.parentElement : el),
    outline: { ancho: c.outlineWidth, estilo: c.outlineStyle, color: c.outlineColor },
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
    return (max(l1, l2) + 0.05) / (min(l1, l2) + 0.05)


def minimo(tamano: float, peso: int) -> float:
    """4,5:1, o 3:1 si el texto es grande (>=24px, o >=18.66px en negrita)."""
    grande = tamano >= 24 or (tamano >= 18.66 and peso >= 700)
    return 3.0 if grande else 4.5


def abrir(navegador, ruta: Path, **kwargs):
    contexto = navegador.new_context(viewport=kwargs.pop("viewport", {"width": 1440, "height": 900}), **kwargs)
    pagina = contexto.new_page()
    pagina.goto(ruta.as_uri())
    return contexto, pagina


def esperar_alpine(pagina) -> None:
    """Hasta que Alpine no monta, los paneles siguen bajo x-cloak y no hay nada que medir."""
    pagina.wait_for_function(
        "() => { const p = document.querySelector('.muni-ajc [role=\"tabpanel\"]');"
        " return p && getComputedStyle(p).display !== 'none'; }",
        timeout=10000,
    )


def ir_a_pestana(pagina, indice: int) -> None:
    pagina.evaluate(
        "i => { const t = document.querySelectorAll('.muni-ajc [role=\"tab\"]')[i]; t && t.click(); }",
        indice,
    )
    pagina.wait_for_timeout(120)


def esperar_dialogo(pagina, abierto: bool) -> bool:
    """Espera la CONDICIÓN, no un reloj.

    El diálogo se muestra en el cuadro de animación siguiente, `x-trap` arma la
    trampa 15 ms después y el respaldo de foco del modal espera otros 40. Pulsar
    Escape antes de que el foco haya entrado lo manda al <body>, donde el oyente
    —que vive EN el diálogo, no en window, a propósito— no se oye: eso salía como
    un «Escape no cierra» intermitente en una página de ocho.
    """
    condicion = (
        "() => { const d = document.querySelector('[role=\"alertdialog\"]');"
        " if (!d) return %s;"
        " const visible = getComputedStyle(d).display !== 'none';"
        " return %s; }"
    ) % ("true" if not abierto else "false",
         "visible && d.contains(document.activeElement)" if abierto else "!visible")

    try:
        pagina.wait_for_function(condicion, timeout=5000)
        return True
    except Exception:
        return False


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


def verificar_pestanas(pagina, d: dict, donde: str, fallos: list[str]) -> None:
    if not d["hayRaiz"]:
        fallos.append(f"{donde}: no hay componente que medir.")
        return

    if len(d["pestanas"]) != 4:
        fallos.append(f"{donde}: hay {len(d['pestanas'])} pestañas y tienen que ser cuatro.")
    if not d["listaVisible"]:
        fallos.append(f"{donde}: el grupo de pestañas no se ve.")
    if d["panelesVisibles"] != 1:
        fallos.append(
            f"{donde}: se ven {d['panelesVisibles']} paneles de {d['panelesTotales']}; tiene que verse uno."
        )

    seleccionadas = [p for p in d["pestanas"] if p["seleccionada"] == "true"]
    if len(seleccionadas) != 1:
        fallos.append(f"{donde}: hay {len(seleccionadas)} pestañas con aria-selected=\"true\".")

    for p in d["pestanas"]:
        if not p["controla"]:
            fallos.append(f"{donde}: la pestaña «{p['texto']}» no apunta a su panel con aria-controls.")


def verificar_teclado(pagina, donde: str, fallos: list[str]) -> None:
    """←/→ circulares, Inicio y Fin: el teclado lo pone <x-muni::tabs> y se mide acá."""
    pagina.evaluate("() => document.querySelectorAll('.muni-ajc [role=\"tab\"]')[0].focus()")
    medir_foco(pagina, donde, "pestaña 1", fallos)

    for esperado in (1, 2, 3):
        pagina.keyboard.press("ArrowRight")
        pagina.wait_for_timeout(60)
        activo = pagina.evaluate(
            "() => [...document.querySelectorAll('.muni-ajc [role=\"tab\"]')].indexOf(document.activeElement)"
        )
        if activo != esperado:
            fallos.append(f"{donde}: → tenía que llevar el foco a la pestaña #{esperado + 1} y quedó en #{activo + 1}.")
            return

    # Circular: desde la última, → vuelve a la primera.
    pagina.keyboard.press("ArrowRight")
    pagina.wait_for_timeout(60)
    if pagina.evaluate("() => [...document.querySelectorAll('.muni-ajc [role=\"tab\"]')].indexOf(document.activeElement)") != 0:
        fallos.append(f"{donde}: las flechas no son circulares.")

    pagina.keyboard.press("End")
    pagina.wait_for_timeout(60)
    if pagina.evaluate("() => [...document.querySelectorAll('.muni-ajc [role=\"tab\"]')].indexOf(document.activeElement)") != 3:
        fallos.append(f"{donde}: Fin no lleva a la última pestaña.")

    pagina.keyboard.press("Home")
    pagina.wait_for_timeout(60)
    if pagina.evaluate("() => [...document.querySelectorAll('.muni-ajc [role=\"tab\"]')].indexOf(document.activeElement)") != 0:
        fallos.append(f"{donde}: Inicio no lleva a la primera pestaña.")


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> None:
    for t in d["textos"]:
        r = contraste(t["color"], t["fondo"])
        if r is None:
            continue
        exigido = minimo(t["tamano"], t["peso"])
        if r < exigido:
            fallos.append(
                f"{donde} «{t['nombre']}»: {r:.2f}:1 contra su fondo (exige {exigido}:1) "
                f"[{t['color']} sobre {t['fondo']}]"
            )


def verificar_blanco(d: dict, donde: str, fallos: list[str]) -> None:
    for a in d["acciones"]:
        # Medio píxel de tolerancia por el redondeo del subpíxel.
        if a["alto"] < 43.5:
            fallos.append(
                f"{donde} «{a['nombre']}»: el blanco táctil mide {a['alto']:.2f}px de alto (<44). "
                "Cerrar una sesión y borrar una cuenta son destructivas: el blanco chico es el que se "
                "aprieta por error (WCAG 2.5.8)."
            )


def verificar_peligro(pagina, donde: str, fallos: list[str]) -> str:
    """La puerta de la zona de peligro, medida en píxeles computados y en envíos reales."""
    ir_a_pestana(pagina, 3)
    pagina.evaluate("() => { window.__envios = []; }")

    d = pagina.evaluate(SONDA)
    boton = d["destruir"]

    if boton is None:
        fallos.append(f"{donde}: no hay botón destructivo que medir.")
        return "espera=SIN-BOTON"

    if boton["desactivado"]:
        fallos.append(
            f"{donde}: el botón destructivo nace `disabled`: no recibe foco, no lo anuncia un lector, y "
            "si Alpine no montó deja la acción muerta. La puerta de verdad es el servidor."
        )

    if boton["ariaDisabled"] != "true":
        fallos.append(f"{donde}: el botón destructivo no nace con aria-disabled=\"true\" ({boton['ariaDisabled']!r}).")

    # EL HALLAZGO: la clase de espera existía y el navegador no la aplicaba nunca.
    estado = "espera=dashed"
    if boton["bordeEstilo"] != "dashed":
        estado = "espera=NO-SE-DIBUJA"
        fallos.append(
            f"{donde}: en espera el botón computa border-top-style={boton['bordeEstilo']!r}. La regla de "
            "clase pierde contra el `style` en línea que escribe <x-muni::button> "
            "(`border:1px solid transparent`): hace falta !important. Se ve idéntico al habilitado."
        )
    if boton["cursor"] != "not-allowed":
        estado = "espera=NO-SE-DIBUJA"
        fallos.append(
            f"{donde}: en espera el cursor computa {boton['cursor']!r}; el `cursor:pointer` en línea de "
            "<x-muni::button> gana sin !important."
        )

    borde = contraste(boton["bordeColor"], boton["fondo"])
    if borde is not None and borde < 3.0:
        fallos.append(
            f"{donde}: el borde discontinuo da {borde:.2f}:1 contra el fondo del botón (<3, WCAG 1.4.11): "
            "el estado se ve, pero no se distingue."
        )

    # 1) Con el campo VACÍO el envío tiene que quedar bloqueado.
    # `force=True` porque Playwright trata `aria-disabled="true"` como no accionable:
    # justamente el estado que se quiere ejercer. Un funcionario SÍ puede pulsarlo, y
    # ese es el clic sin querer que la puerta tiene que atajar.
    pagina.locator(".muni-ajc__destruir").click(force=True)
    pagina.wait_for_timeout(150)
    d = pagina.evaluate(SONDA)

    puerta = "puerta=bloquea"
    if not d["envios"] or not d["envios"][-1]["bloqueado"]:
        puerta = "puerta=DEJA-PASAR"
        fallos.append(f"{donde}: con el campo vacío el envío destructivo NO se bloqueó ({d['envios']}).")
    if not (d["aviso"] or {}).get("texto"):
        fallos.append(f"{donde}: al intentar sin la palabra, la región viva no dice nada en texto.")
    foco = pagina.evaluate(FOCO) or {}
    if "muni-input" not in (foco.get("clase") or "") and foco.get("etiqueta") != "INPUT":
        fallos.append(f"{donde}: tras el intento fallido el foco no volvió al campo (quedó en {foco.get('etiqueta')!r}).")

    # 2) Una palabra PARECIDA tampoco pasa: la comparación es exacta.
    pagina.fill(".muni-ajc__forma input[type='text'], .muni-ajc__forma input:not([type])", PARECIDA)
    pagina.wait_for_timeout(120)
    d = pagina.evaluate(SONDA)
    if d["destruir"]["ariaDisabled"] != "true":
        puerta = "puerta=DEJA-PASAR"
        fallos.append(f"{donde}: «{PARECIDA}» habilita el botón; la comparación con «{PALABRA}» no es exacta.")

    # `force=True` porque Playwright trata `aria-disabled="true"` como no accionable:
    # justamente el estado que se quiere ejercer. Un funcionario SÍ puede pulsarlo, y
    # ese es el clic sin querer que la puerta tiene que atajar.
    pagina.locator(".muni-ajc__destruir").click(force=True)
    pagina.wait_for_timeout(150)
    d = pagina.evaluate(SONDA)
    if not d["envios"][-1]["bloqueado"]:
        puerta = "puerta=DEJA-PASAR"
        fallos.append(f"{donde}: con «{PARECIDA}» el envío destructivo pasó.")

    # 3) Con la palabra EXACTA se abre: el estado vuelve a sólido y el envío pasa.
    pagina.fill(".muni-ajc__forma input[type='text'], .muni-ajc__forma input:not([type])", PALABRA)
    pagina.wait_for_timeout(150)
    d = pagina.evaluate(SONDA)

    if d["destruir"]["ariaDisabled"] != "false":
        puerta = "puerta=NO-ABRE"
        fallos.append(
            f"{donde}: con la palabra exacta el botón sigue en aria-disabled="
            f"{d['destruir']['ariaDisabled']!r}. Tiene que viajar como CADENA: con un booleano `false` "
            "Alpine QUITA el atributo."
        )
    if d["destruir"]["bordeEstilo"] == "dashed" or d["destruir"]["cursor"] == "not-allowed":
        puerta = "puerta=NO-ABRE"
        fallos.append(f"{donde}: con la palabra exacta el botón sigue dibujado en espera.")

    pagina.evaluate("() => { window.__envios = []; }")
    # `force=True` porque Playwright trata `aria-disabled="true"` como no accionable:
    # justamente el estado que se quiere ejercer. Un funcionario SÍ puede pulsarlo, y
    # ese es el clic sin querer que la puerta tiene que atajar.
    pagina.locator(".muni-ajc__destruir").click(force=True)
    pagina.wait_for_timeout(150)
    d = pagina.evaluate(SONDA)

    if not d["envios"]:
        puerta = "puerta=NO-ABRE"
        fallos.append(f"{donde}: con la palabra exacta el botón no envía nada.")
    elif d["envios"][-1]["bloqueado"]:
        puerta = "puerta=NO-ABRE"
        fallos.append(f"{donde}: con la palabra exacta el envío destructivo sigue bloqueado.")
    elif d["envios"][-1]["metodo"] != "post":
        fallos.append(f"{donde}: el formulario destructivo no es POST ({d['envios'][-1]['metodo']!r}).")

    # El foco se mide LLEGANDO CON TAB, no con .focus() desde el guion: `:focus-visible`
    # depende de la modalidad de entrada, y después de una tanda de clics un foco
    # programático no la satisface. Medido con .focus() el outline computaba `none` en
    # los ocho recorridos y la medición habría culpado al componente de un defecto que
    # era del método. Desde el campo, el siguiente tabulable es el botón.
    pagina.evaluate("() => document.querySelector('.muni-ajc__forma input:not([type=hidden])').focus()")
    pagina.keyboard.press("Tab")
    pagina.wait_for_timeout(80)
    foco = pagina.evaluate(FOCO) or {}
    if "muni-ajc__destruir" not in (foco.get("clase") or ""):
        fallos.append(
            f"{donde}: desde el campo, Tab tenía que llegar al botón destructivo y cayó en "
            f"{foco.get('etiqueta')!r} [{foco.get('clase')}]."
        )
    else:
        medir_foco(pagina, donde, "destruir", fallos)

    return f"{estado} {puerta}"


def verificar_dialogo(pagina, donde: str, fallos: list[str]) -> str:
    """Cerrar una sesión: el clic no envía, confirma el diálogo, y el POST lleva la sesión."""
    ir_a_pestana(pagina, 1)
    pagina.evaluate("() => { window.__envios = []; }")

    d = pagina.evaluate(SONDA)

    cerrables = [f for f in d["formularios"] if f["sesion"] is not None]
    if len(cerrables) != 1:
        fallos.append(
            f"{donde}: hay {len(cerrables)} formularios de cierre y tiene que haber uno: la sesión marcada "
            "como actual no ofrece cerrarse (es el clic que se da por error)."
        )
        return "dialogo=SIN-FORMULARIO"
    if cerrables[0]["sesion"] == "s-actual":
        fallos.append(f"{donde}: el formulario de cierre apunta a la sesión ACTUAL.")
    if not cerrables[0]["token"]:
        fallos.append(f"{donde}: el formulario de cierre no lleva token CSRF.")
    if d["marcaActual"] is None:
        fallos.append(f"{donde}: la sesión actual no se marca en texto («Este equipo»).")

    # El foco del botón de cerrar sesión, LLEGANDO CON TAB desde su propia pestaña:
    # `:focus-visible` depende de la modalidad de entrada y un .focus() del guion no
    # la satisface. El panel trae un control propio, así que no recibe tabindex="0" y
    # el Tab que sale de la pestaña cae directo en el botón.
    pagina.evaluate("() => document.querySelectorAll('.muni-ajc [role=\"tab\"]')[1].focus()")
    pagina.keyboard.press("Tab")
    pagina.wait_for_timeout(80)
    foco = pagina.evaluate(FOCO) or {}
    if "muni-ajc__accion" in (foco.get("clase") or ""):
        medir_foco(pagina, donde, "cerrar sesión", fallos)
    else:
        fallos.append(
            f"{donde}: desde la pestaña «Seguridad», Tab tenía que llegar al botón «Cerrar sesión» y "
            f"cayó en {foco.get('etiqueta')!r} [{foco.get('clase')}]."
        )

    pagina.click(".muni-ajc__form .muni-ajc__accion")
    if not esperar_dialogo(pagina, abierto=True):
        fallos.append(f"{donde}: el diálogo no llegó a abrirse con el foco dentro.")
    d = pagina.evaluate(SONDA)

    if d["envios"]:
        fallos.append(f"{donde}: el clic en «Cerrar sesión» envió directo sin pasar por la confirmación.")
    if d["dialogo"] is None or not d["dialogo"]["visible"]:
        fallos.append(f"{donde}: el clic en «Cerrar sesión» no abrió el diálogo de confirmación.")
        return "dialogo=NO-ABRE"
    if d["dialogo"]["modal"] != "true":
        fallos.append(f"{donde}: el diálogo no declara aria-modal=\"true\".")
    if not d["dialogo"]["nombrado"]:
        fallos.append(f"{donde}: el aria-labelledby del diálogo no resuelve: se anuncia sin nombre.")
    if not d["dialogo"]["descrito"]:
        fallos.append(f"{donde}: el aria-describedby del diálogo no resuelve.")
    if not d["dialogo"]["tieneFoco"]:
        fallos.append(f"{donde}: al abrir el diálogo el foco no entró en él (la ficha lo pide con esas palabras).")
    if d["dialogo"]["confirmaA"] != cerrables[0]["id"]:
        fallos.append(
            f"{donde}: el botón que confirma apunta a form={d['dialogo']['confirmaA']!r} y el formulario "
            f"es {cerrables[0]['id']!r}. El diálogo vive teletransportado en <body>, fuera del <form>."
        )

    # Escape cancela, y cancelar NO envía.
    pagina.keyboard.press("Escape")
    esperar_dialogo(pagina, abierto=False)
    d = pagina.evaluate(SONDA)
    if d["dialogo"] and d["dialogo"]["visible"]:
        fallos.append(f"{donde}: Escape no cierra el diálogo de confirmación.")
    if d["envios"]:
        fallos.append(f"{donde}: cancelar con Escape envió el POST igual.")

    # Reabrir y confirmar: ahí sí tiene que salir el POST, con la sesión correcta.
    pagina.click(".muni-ajc__form .muni-ajc__accion")
    if not esperar_dialogo(pagina, abierto=True):
        fallos.append(f"{donde}: el diálogo no se vuelve a abrir después de cancelar.")
    pagina.click("[role='alertdialog'] [form]")
    pagina.wait_for_timeout(200)
    d = pagina.evaluate(SONDA)

    if not d["envios"]:
        fallos.append(f"{donde}: confirmar en el diálogo no envía el formulario (¿se perdió el atributo form=?).")
        return "dialogo=NO-CONFIRMA"
    envio = d["envios"][-1]
    if envio["bloqueado"]:
        fallos.append(f"{donde}: el envío de la confirmación quedó bloqueado.")
        return "dialogo=NO-CONFIRMA"
    if envio["metodo"] != "post":
        fallos.append(f"{donde}: el cierre de sesión no viaja por POST ({envio['metodo']!r}).")
        return "dialogo=NO-CONFIRMA"

    # El diálogo sigue abierto (el envío se detuvo en el banco, así que la página no
    # navegó) y `x-trap.inert` deja INERTE todo lo demás: sin cerrarlo, las pestañas
    # no responden al clic y la medición de la zona de peligro se haría sobre una
    # pantalla que no escucha. Costó un rato encontrarlo.
    pagina.keyboard.press("Escape")
    esperar_dialogo(pagina, abierto=False)
    if (pagina.evaluate(SONDA)["dialogo"] or {}).get("visible"):
        fallos.append(f"{donde}: el diálogo no se deja cerrar después de confirmar.")

    return "dialogo=confirma"


def verificar_movimiento(navegador, ruta: Path, donde: str, fallos: list[str]) -> None:
    """Con la preferencia puesta, el botón destructivo no puede seguir animando."""
    for preferencia, espera_cero in (("reduce", True), ("no-preference", False)):
        contexto, pagina = abrir(navegador, ruta, reduced_motion=preferencia)
        try:
            esperar_alpine(pagina)
            ir_a_pestana(pagina, 3)
            d = pagina.evaluate(SONDA)
            if d["destruir"] is None:
                continue
            duraciones = [float(x.strip().rstrip("s")) for x in d["destruir"]["transicion"].split(",") if x.strip()]
            peor = max(duraciones) if duraciones else 0.0
            if espera_cero and peor > 0.0001:
                fallos.append(
                    f"{donde}: con prefers-reduced-motion:reduce el botón destructivo sigue animando "
                    f"{peor * 1000:.0f} ms. `button` escribe su transición en el atributo `style` y "
                    "muni-ui-filament.css no baja --muni-dur con la preferencia: hace falta el !important."
                )
            if not espera_cero and peor <= 0.0001:
                fallos.append(
                    f"{donde}: sin la preferencia la transición ya es 0 s, así que la comprobación de "
                    "movimiento reducido no mide nada."
                )
        finally:
            contexto.close()


def verificar_anchos(navegador, ruta: Path, donde: str, fallos: list[str]) -> None:
    for nombre, ancho, alto in ANCHOS:
        contexto, pagina = abrir(navegador, ruta, viewport={"width": ancho, "height": alto})
        try:
            esperar_alpine(pagina)
            for i in range(4):
                ir_a_pestana(pagina, i)
                d = pagina.evaluate(SONDA)
                if d["desplazaH"]:
                    fallos.append(
                        f"{donde} a {ancho}px ({nombre}), pestaña #{i + 1}: el documento se desplaza en "
                        "horizontal (WCAG 1.4.10)."
                    )
                    break
        finally:
            contexto.close()


def verificar_una_sesion(navegador, banco: Path, donde: str, fallos: list[str]) -> None:
    """Con UNA sola sesión ajena listada no se puede decir que no hay otras."""
    contexto, pagina = abrir(navegador, banco / "una-sesion.html")
    try:
        esperar_alpine(pagina)
        ir_a_pestana(pagina, 0)  # con solo sesiones, «Seguridad» es la única sección
        d = pagina.evaluate(SONDA)
        cerrables = [f for f in d["formularios"] if f["sesion"] is not None]
        if not cerrables:
            fallos.append(f"{donde} una-sesion: no se lista la sesión ajena; la comprobación sería vacía.")
            return
        if d["vacio"]:
            fallos.append(
                f"{donde} una-sesion: la pantalla lista {len(cerrables)} sesión ajena Y a la vez imprime "
                f"«{d['vacio']}». La condición cuenta sesiones, no sesiones ajenas, y este bloque es el "
                "que la ficha marca como el de riesgo."
            )
    finally:
        contexto.close()


def verificar_sin_js(navegador, banco: Path, donde: str, fallos: list[str]) -> str:
    """Sin JavaScript la pantalla tiene que seguir siendo alcanzable de punta a punta."""
    contexto, pagina = abrir(navegador, banco / "claro.html", java_script_enabled=False)
    try:
        pagina.wait_for_timeout(300)
        d = pagina.evaluate_handle  # no hay JS de página: se mide con locators
        paneles = pagina.locator(".muni-ajc [role='tabpanel']")
        total = paneles.count()
        visibles = sum(1 for i in range(total) if paneles.nth(i).is_visible())

        if total == 0:
            fallos.append(f"{donde} sin-js: no hay paneles en el marcado.")
            return "sin-js=SIN-PANELES"

        if visibles != total:
            fallos.append(
                f"{donde} sin-js: se ven {visibles} paneles de {total}. `tab-panel` emite x-cloak y las dos "
                "hojas lo esconden con display:none !important: sin el <noscript> del componente, con el JS "
                "apagado no se alcanza ni el POST que cierra la sesión ni la zona de peligro."
            )
            return "sin-js=INVISIBLE"

        if pagina.locator(".muni-ajc [role='tablist']").is_visible():
            fallos.append(
                f"{donde} sin-js: el grupo de pestañas sigue visible. Sin Alpine sus botones no hacen "
                "nada: serían cuatro paradas de tabulación mudas."
            )

        formularios = pagina.locator(".muni-ajc form")
        cuantos = formularios.count()
        if cuantos < 2:
            fallos.append(f"{donde} sin-js: hay {cuantos} formularios y tienen que estar los dos.")
            return "sin-js=INCOMPLETO"
        for i in range(cuantos):
            if not formularios.nth(i).is_visible():
                fallos.append(f"{donde} sin-js: el formulario #{i + 1} no es visible.")
                return "sin-js=INVISIBLE"

        destruir = pagina.locator(".muni-ajc__destruir")
        if destruir.count() and destruir.first.get_attribute("disabled") is not None:
            fallos.append(
                f"{donde} sin-js: el botón destructivo llega `disabled`, o sea la acción queda muerta "
                "justo en el escenario que el aria-disabled existe para cubrir."
            )
            return "sin-js=MUERTO"

        return "sin-js=alcanzable"
    finally:
        contexto.close()


def main() -> int:
    banco = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else BANCO_POR_DEFECTO

    faltan = [n for n in (*PAGINAS, "una-sesion.html") if not (banco / n).is_file()]
    if faltan:
        print(f"No está el banco en {banco} (faltan {', '.join(faltan)}): "
              "corre `vendor/bin/pest --filter=AjustesDeCuenta` primero.", file=sys.stderr)
        return 2

    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        print("Falta playwright en .venv-a11y: corre `npm run a11y:instalar`.", file=sys.stderr)
        return 2

    fallos: list[str] = []

    with sync_playwright() as p:
        for nombre_navegador in NAVEGADORES:
            navegador = getattr(p, nombre_navegador).launch()
            try:
                for pagina_html in PAGINAS:
                    ruta = banco / pagina_html
                    donde = f"{nombre_navegador} {pagina_html[:-5]}"
                    contexto, pagina = abrir(navegador, ruta)
                    try:
                        esperar_alpine(pagina)

                        d = pagina.evaluate(SONDA)
                        verificar_pestanas(pagina, d, donde, fallos)
                        verificar_teclado(pagina, donde, fallos)

                        for i in range(4):
                            ir_a_pestana(pagina, i)
                            dd = pagina.evaluate(SONDA)
                            verificar_contraste(dd, f"{donde} pestaña #{i + 1}", fallos)
                            verificar_blanco(dd, f"{donde} pestaña #{i + 1}", fallos)

                        marca_dialogo = verificar_dialogo(pagina, donde, fallos)
                        marca_peligro = verificar_peligro(pagina, donde, fallos)

                        print(f"{donde} {marca_peligro} {marca_dialogo}")
                    finally:
                        contexto.close()

                    verificar_movimiento(navegador, ruta, donde, fallos)
                    verificar_anchos(navegador, ruta, donde, fallos)

                verificar_una_sesion(navegador, banco, nombre_navegador, fallos)
                print(f"{nombre_navegador} {verificar_sin_js(navegador, banco, nombre_navegador, fallos)}")
            finally:
                navegador.close()

    if fallos:
        print("\nFALLOS:", file=sys.stderr)
        for f in fallos:
            print(f"  · {f}", file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
