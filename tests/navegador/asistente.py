#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::asistente>` y del `<x-muni::stepper>` reparado.

    .venv-a11y/bin/python tests/navegador/asistente.py [build/asistente]

La corre `tests/AsistenteDePasosTest.php` después de generar el banco (y se salta
si no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest
leen el TEXTO del fuente —que el botón traiga `formnovalidate`, que el CSS diga
`min-height:44px`, que exista el `tabindex="-1"`— y eso ya engañó una vez en
`stat`: la regla pasó el test de texto y en el navegador el defecto seguía vivo.
Acá se mide, en Chromium Y Firefox:

  · ADÓNDE VA EL FOCO DE VERDAD. Es la tecla de la ficha: «al cambiar de paso el
    foco va al encabezado del paso nuevo (tabindex=-1)». Se mide con
    `document.activeElement` al cargar el paso 2, con Alpine y también SIN él
    —ahí lo mueve el atributo `autofocus`, que es la mitad que cubre el envío
    real de formulario—. Y el contrario, que ninguna prueba de texto puede ver:
    en el PRIMER paso el foco NO se mueve, porque robarlo al cargar se salta el
    enlace «Saltar al contenido».
  · EL RECORRIDO REAL DE TAB. Tiene que ser: el paso navegable del indicador, los
    campos del paso, y después Anterior · Omitir · Siguiente en ese orden (WCAG
    2.2 AA 1.3.2). Y el bloque de avance —contador y barra— NO puede robar una
    sola parada: es información, no un control.
  · EL FOCO VISIBLE EN CADA PARADA, computado: `outline-width` de 3 px y estilo
    sólido. La `box-shadow` del anillo no cuenta, porque dentro de Filament se
    computa transparente (DESIGN §5).
  · EL ÁREA TÁCTIL de los botones y del paso navegable: 44 px (mesón municipal y
    vecino mayor, no los 24 del mínimo de 2.5.8).
  · TODO TEXTO pasa 4,5:1 (3:1 si es grande) contra su fondo efectivo, en las
    CUATRO páginas completas: claro, oscuro y las dos del panel, que cargan
    únicamente `muni-ui-filament.css` (DESIGN §7) y son donde una clase declarada
    fuera del bloque de estilos se quedaría sin estilo. Se miden en particular las
    dos palabras nuevas del stepper —«Con errores» y «Omitido»—, que son color de
    estado sobre un fondo que no es el suyo.
  · EL MOVIMIENTO: con `prefers-reduced-motion: reduce` la transición de la barra
    de avance computa 0 s, y sin la preferencia existe (si no, la comprobación
    sería vacía).
  · QUE NADA DESBORDE a 390 px de ancho (1.4.10).
  · EL RESUMEN DE ERRORES se lleva el foco al cargar la página con errores, y su
    primer enlace llega al campo de verdad.
  · SIN ALPINE el asistente sigue siendo un formulario que se envía: botón submit
    real, `formnovalidate` en el retroceso y el foco puesto por `autofocus`.
  · ENTER EN UN CAMPO ENVÍA EL PASO, con y sin Alpine: el `submitter` que recibe
    el evento `submit` es `_accion=siguiente`, y con un obligatorio vacío no sale
    nada (Enter valida). El revisor lo midió al revés: salía `_ir_a_paso=0` o
    `_accion=anterior`, porque el primer submit del DOM era un retroceso.
  · SIN ALPINE Y CON ERRORES el resumen se lleva el foco igual (por `autofocus`):
    el encabezado le cede el foco al resumen, y el resumen lo movía solo con JS.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "asistente"
NAVEGADORES = ("chromium", "firefox")
COMPLETAS = ("claro.html", "oscuro.html", "panel-claro.html", "panel-oscuro.html")
MOVIMIENTOS = ("reduce", "no-preference")
ANCHOS = (("escritorio", 1440, 900), ("telefono", 390, 780))

SONDA = r"""
() => {
  const fondoDe = el => {
    for (let n = el; n; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && !/rgba\(\s*0,\s*0,\s*0,\s*0\)/.test(bg) && bg !== 'transparent') return bg;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  const leerTexto = el => {
    const c = getComputedStyle(el);
    return {
      texto: (el.textContent || '').trim().slice(0, 48),
      clase: el.getAttribute('class') || '',
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
    };
  };

  const form = document.querySelector('form.muni-asis');
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };

  // Solo nodos HOJA con texto propio: medir un contenedor da el color heredado
  // de un texto que no es suyo.
  const textos = [...(form ? form.querySelectorAll('*') : [])]
    .filter(el => {
      if (['STYLE', 'SCRIPT', 'SVG', 'PATH'].includes(el.tagName)) return false;
      if (el.closest('svg')) return false;
      if (el.getAttribute('aria-hidden') === 'true') return false;
      const r = el.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) return false;
      return [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1);
    })
    .map(leerTexto);

  const barra = document.querySelector('.muni-asis__barra-fill');
  const avance = document.querySelector('.muni-asis__avance');
  const progress = document.querySelector('[role="progressbar"]');

  return {
    hayForm: !!form,
    textos,
    botones: [...document.querySelectorAll('.muni-asis__btn')].map(el => ({
      valor: el.getAttribute('value') || '', etiqueta: (el.textContent || '').trim().slice(0, 30), ...caja(el),
    })),
    pasosNav: [...document.querySelectorAll('.muni-step__ctrl')].map(el => ({ ...caja(el) })),
    transicionBarra: barra ? getComputedStyle(barra).transitionDuration : null,
    avanceVivo: avance ? avance.getAttribute('aria-live') : null,
    avanceTexto: avance ? (avance.textContent || '').trim() : '',
    valueText: progress ? progress.getAttribute('aria-valuetext') : null,
    desborde: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
    // La línea que une los pasos NO puede cruzar el rótulo. No hay rect de un
    // pseudo-elemento, así que se reconstruye su inicio con lo computado: si es
    // absoluta, desde su `left`; si es un ítem flexible, desde el borde derecho
    // del paso menos su margen y su ancho usado. Tiene que empezar DESPUÉS del
    // texto del paso.
    lineas: [...document.querySelectorAll('.muni-stepper:not(.muni-stepper--v) > .muni-step:not(:last-child)')].map(li => {
      const cuerpo = li.querySelector('.muni-step__body');
      const c = getComputedStyle(li, '::after');
      const r = li.getBoundingClientRect();
      const inicio = c.position === 'absolute'
        ? r.left + parseFloat(c.left)
        : r.right - parseFloat(c.marginRight) - parseFloat(c.width);
      const fin = cuerpo ? cuerpo.getBoundingClientRect().right : r.left;
      return { texto: (li.querySelector('.muni-step__label') || li).textContent.trim(), cruza: inicio < fin - 0.5, sobra: Math.round(inicio - fin) };
    }),
    // Las dos palabras nuevas del stepper, que son las que estrenan color.
    estados: [...document.querySelectorAll('.muni-step__estado')]
      .filter(el => el.getBoundingClientRect().width > 1)
      .map(leerTexto),
  };
}
"""

# Quién tiene el foco AHORA, descrito de forma estable entre navegadores.
FOCO = r"""
() => {
  const a = document.activeElement;
  const vacio = { que: 'body', id: '', clase: '', valor: '', nombre: '', enAvance: false, enForm: false, outline: '' };
  if (!a || a === document.body) return vacio;
  return {
    que: a.tagName.toLowerCase(),
    id: a.id || '',
    clase: a.getAttribute('class') || '',
    valor: a.getAttribute('value') || '',
    nombre: a.getAttribute('name') || '',
    enAvance: !!a.closest('.muni-asis__avance'),
    enForm: !!a.closest('form.muni-asis'),
    outline: getComputedStyle(a).outlineWidth + ' ' + getComputedStyle(a).outlineStyle,
  };
}
"""


def luminancia(rgb: tuple[float, float, float]) -> float:
    def canal(c: float) -> float:
        c = c / 255
        return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

    r, g, b = (canal(x) for x in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def a_rgb(valor: str) -> tuple[float, float, float] | None:
    valor = (valor or "").strip()
    if not valor.startswith("rgb"):
        return None
    partes = valor[valor.find("(") + 1: valor.find(")")].replace("/", " ").replace(",", " ").split()
    try:
        return tuple(float(p) for p in partes[:3])  # type: ignore[return-value]
    except ValueError:
        return None


def contraste(color: str, fondo: str) -> float | None:
    c, f = a_rgb(color), a_rgb(fondo)
    if c is None or f is None:
        return None
    l1, l2 = luminancia(c), luminancia(f)
    claro, oscuro = max(l1, l2), min(l1, l2)
    return (claro + 0.05) / (oscuro + 0.05)


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 21.0

    for t in d["textos"] + d["estados"]:
        ratio = contraste(t["color"], t["fondo"])
        if ratio is None:
            continue
        grande = t["tamano"] >= 24 or (t["tamano"] >= 18.66 and t["peso"] >= 700)
        minimo = 3.0 if grande else 4.5
        peor = min(peor, ratio)
        if ratio < minimo:
            fallos.append(
                f"{donde}: «{t['texto']}» ({t['clase']}) da {ratio:.2f}:1 sobre su fondo y necesita {minimo}:1"
            )

    return peor


def verificar_estructura(d: dict, donde: str, fallos: list[str]) -> str:
    if not d["hayForm"]:
        fallos.append(f"{donde}: no hay <form class=muni-asis>: el banco salió vacío")
        return "estructura=sin-form"

    if d["avanceVivo"] != "polite":
        fallos.append(f"{donde}: el bloque de avance no es una región viva `polite`")

    if not d["valueText"] or " de " not in d["valueText"]:
        fallos.append(f"{donde}: el progressbar no trae aria-valuetext con el avance en palabras")

    # El nombre del paso vive en aria-valuetext, no en el texto de la región viva:
    # el encabezado que recibe el foco ya lo dice, y dos anuncios seguidos con el
    # mismo texto son ruido.
    nombre_paso = (d["valueText"] or "").split(": ", 1)[-1]
    if nombre_paso and nombre_paso in d["avanceTexto"]:
        fallos.append(f"{donde}: la región viva repite el nombre del paso, que el encabezado enfocado ya anuncia")

    for b in d["botones"]:
        if b["alto"] < 44:
            fallos.append(f"{donde}: el botón «{b['etiqueta']}» mide {b['alto']:.0f}px de alto y el mínimo del mesón es 44")

    for p in d["pasosNav"]:
        if p["alto"] < 44:
            fallos.append(f"{donde}: un paso navegable mide {p['alto']:.0f}px de alto y el mínimo del mesón es 44")

    for linea in d["lineas"]:
        if linea["cruza"]:
            fallos.append(
                f"{donde}: la línea que une los pasos cruza el rótulo «{linea['texto']}» ({linea['sobra']}px): "
                "el paso sale tachado"
            )

    if d["desborde"] > 0:
        fallos.append(f"{donde}: el documento desborda {d['desborde']}px en horizontal (1.4.10)")

    return f"botones={len(d['botones'])} nav={len(d['pasosNav'])} líneas={len(d['lineas'])}"


def verificar_movimiento(d: dict, donde: str, movimiento: str, fallos: list[str]) -> str:
    dur = d["transicionBarra"]

    if dur is None:
        return "—"

    segundos = max(float(p.replace("s", "")) for p in dur.split(", ") if p.endswith("s"))

    if movimiento == "reduce" and segundos > 0:
        fallos.append(
            f"{donde}: con `prefers-reduced-motion: reduce` la barra de avance sigue animando {segundos}s: "
            "la duración no sale de var(--muni-dur)"
        )

    if movimiento == "no-preference" and segundos == 0:
        fallos.append(f"{donde}: sin la preferencia la transición computa 0s, así que la comprobación de arriba es vacía")

    return f"transición={segundos}s"


def verificar_foco_inicial(pagina, donde: str, fallos: list[str], espera_paso: bool) -> str:
    foco = pagina.evaluate(FOCO)

    if espera_paso:
        if foco["que"] == "h3" and foco["clase"].startswith("muni-asis__paso-titulo"):
            return "foco=paso"
        fallos.append(
            f"{donde}: al cargar un paso que no es el primero, el foco quedó en «{foco['que']}"
            f"{('.' + foco['clase']) if foco['clase'] else ''}» y tiene que estar en el encabezado del paso (2.4.3)"
        )
        return "foco=perdido"

    if foco["que"] == "body":
        return "foco=intacto"

    fallos.append(
        f"{donde}: en el PRIMER paso el componente robó el foco («{foco['que']}»): quien llega con el teclado se "
        "salta el enlace «Saltar al contenido»"
    )
    return "foco=robado"


def verificar_teclado(pagina, donde: str, fallos: list[str]) -> str:
    """Tab desde el enlace de arriba: paso navegable, campos, y Anterior · Omitir · Siguiente."""
    pagina.evaluate("() => document.getElementById('antes').focus()")

    paradas: list[dict] = []
    anterior = None

    for _ in range(22):
        pagina.keyboard.press("Tab")
        foco = pagina.evaluate(FOCO)

        # Un `input type=date` reparte varias paradas internas dentro del MISMO
        # nodo: se colapsan, o el recorrido diría que hay campos que no existen.
        firma = (foco["que"], foco["id"], foco["nombre"], foco["valor"], foco["clase"])
        if firma != anterior:
            paradas.append(foco)
            anterior = firma

        if foco["que"] == "body":
            break

    dentro = [p for p in paradas if p["enForm"]]

    if any(p["enAvance"] for p in dentro):
        fallos.append(f"{donde}: el bloque de avance robó una parada de tabulación: es información, no un control")

    if not dentro or "muni-step__ctrl" not in dentro[0]["clase"]:
        fallos.append(f"{donde}: la primera parada dentro del asistente tiene que ser el paso navegable del indicador")

    orden = [p["valor"] for p in dentro if p["clase"].startswith("muni-asis__btn")]

    if orden != ["anterior", "omitir", "siguiente"]:
        fallos.append(
            f"{donde}: el orden de tabulación de las acciones es {orden or 'ninguno'} y tiene que ser "
            "Anterior · Omitir · Siguiente, que es el orden visual (1.3.2)"
        )
        return "orden=roto"

    # Y el foco se ve en cada parada del asistente: 3 px de outline sólido.
    for p in dentro:
        ancho = p["outline"].split(" ")[0]
        try:
            px = float(ancho.replace("px", ""))
        except ValueError:
            px = 0.0
        if px < 3 or "solid" not in p["outline"]:
            fallos.append(
                f"{donde}: la parada «{p['clase'] or p['que']}» computa outline «{p['outline']}»: el indicador real es "
                "un outline de 3 px, porque dentro de Filament la box-shadow se computa transparente (DESIGN §5)"
            )
            return "orden=sin-foco"

    return "orden=ok"


def verificar_errores(pagina, donde: str, fallos: list[str]) -> str:
    foco = pagina.evaluate(FOCO)

    if "muni-err" not in foco["clase"] and foco["que"] not in ("div", "section"):
        fallos.append(f"{donde}: el resumen de errores no se llevó el foco al cargar (3.3.1 y 2.4.3): quedó en «{foco['que']}»")
        return "foco=fuera-del-resumen"

    # Y el primer enlace del resumen llega a un campo de verdad.
    llega = pagina.evaluate(
        """() => {
            const a = document.querySelector('form.muni-asis a[href^="#"]');
            if (!a) return 'sin-enlace';
            const destino = document.getElementById(decodeURIComponent(a.getAttribute('href').slice(1)));
            return destino ? destino.tagName.toLowerCase() : 'sin-destino';
        }"""
    )

    if llega not in ("input", "select", "textarea"):
        fallos.append(f"{donde}: el enlace del resumen de errores no llega a un campo (llegó a «{llega}»)")

    return "foco=resumen"


def verificar_enter(pagina, donde: str, fallos: list[str]) -> str:
    """Enter en un campo: con un obligatorio vacío no envía; completo, envía «siguiente»."""
    pagina.evaluate(
        """() => {
            window.__envios = [];
            document.querySelector('form.muni-asis').addEventListener('submit', e => {
                const b = e.submitter;
                window.__envios.push(b ? (b.getAttribute('name') || '') + '=' + (b.getAttribute('value') || '') : 'sin-submitter');
                e.preventDefault();
            });
        }"""
    )

    pagina.focus("#muni-diagnostico")
    pagina.keyboard.press("Enter")
    pagina.wait_for_timeout(150)
    vacio = pagina.evaluate("() => window.__envios.slice()")

    if vacio:
        fallos.append(
            f"{donde}: Enter con obligatorios vacíos envió {vacio}: se saltó la validación del paso (el botón que "
            "atendió Enter lleva formnovalidate)"
        )

    pagina.fill("#muni-medico", "Dra. Carolina Pérez Lagos")
    pagina.fill("#muni-fecha_informe", "2026-08-01")
    pagina.focus("#muni-diagnostico")
    pagina.keyboard.type("Discapacidad visual")
    pagina.keyboard.press("Enter")
    pagina.wait_for_timeout(150)
    envios = pagina.evaluate("() => window.__envios.slice()")

    if envios != ["_accion=siguiente"]:
        fallos.append(
            f"{donde}: Enter en «Diagnóstico declarado» envió {envios or 'nada'} y tiene que enviar el paso "
            "(`_accion=siguiente`): la ficha dice «Enter (envía el paso)»"
        )
        return "enter=roto"

    return "enter=siguiente"


def verificar_sin_alpine(pagina, donde: str, fallos: list[str]) -> str:
    d = pagina.evaluate(
        """() => ({
            alpine: typeof window.Alpine,
            submit: !!document.querySelector('form.muni-asis button[type=submit]'),
            retroceso: !!document.querySelector('form.muni-asis button[value=anterior][formnovalidate]'),
            campos: document.querySelectorAll('form.muni-asis input, form.muni-asis textarea').length,
        })"""
    )

    if d["alpine"] != "undefined":
        fallos.append(f"{donde}: esta página tenía que cargarse SIN Alpine y hay un Alpine vivo")

    if not d["submit"] or not d["retroceso"]:
        fallos.append(f"{donde}: sin JS el asistente deja de ser un formulario que se envía y se retrocede")
        return "sinjs=roto"

    # Y el foco lo mueve `autofocus`, que es la mitad que cubre el envío real.
    foco = pagina.evaluate(FOCO)

    if not (foco["que"] == "h3" and foco["clase"].startswith("muni-asis__paso-titulo")):
        fallos.append(
            f"{donde}: sin Alpine el `autofocus` del encabezado no movió el foco (quedó en «{foco['que']}»): en un "
            "envío real de formulario nadie anuncia el paso nuevo"
        )
        return "sinjs=sin-foco"

    return "sinjs=ok"


def main(argv: list[str]) -> int:
    carpeta = Path(argv[1]).resolve() if len(argv) > 1 else BANCO_POR_DEFECTO
    completas = [c for c in COMPLETAS if (carpeta / c).is_file()]

    if not completas or not (carpeta / "primer-paso.html").is_file():
        print(f"Banco incompleto en {carpeta}: genera primero `vendor/bin/pest --filter=AsistenteDePasos`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()

            for archivo in completas:
                ruta = carpeta / archivo

                for movimiento in MOVIMIENTOS:
                    for ancho_nombre, ancho, alto in ANCHOS:
                        contexto = navegador.new_context(
                            viewport={"width": ancho, "height": alto},
                            reduced_motion=movimiento,
                        )
                        pagina = contexto.new_page()
                        pagina.goto(ruta.as_uri())
                        pagina.wait_for_timeout(250)

                        donde = f"{nombre} {archivo} [{movimiento} {ancho_nombre}]"
                        d = pagina.evaluate(SONDA)

                        estructura = verificar_estructura(d, donde, fallos)
                        peor = verificar_contraste(d, donde, fallos)
                        transicion = verificar_movimiento(d, donde, movimiento, fallos)

                        # El foco y el recorrido se miden una vez por página y
                        # navegador: ninguno depende del ancho ni de la
                        # preferencia de movimiento, y repetirlos solo multiplica
                        # el tiempo.
                        extra = ""
                        if ancho_nombre == "escritorio" and movimiento == "no-preference":
                            foco = verificar_foco_inicial(pagina, donde, fallos, espera_paso=True)
                            teclado = verificar_teclado(pagina, donde, fallos)
                            enter = verificar_enter(pagina, donde, fallos) if archivo == "claro.html" else ""
                            extra = f" {foco} {teclado} {enter}"

                        resumen.append(
                            f"{nombre:<9}{archivo:<17}[{movimiento:<13} {ancho_nombre:<10}] "
                            f"{estructura} desborde={d['desborde']}px {transicion} "
                            f"peor contraste={peor:.2f}:1{extra}"
                        )
                        contexto.close()

            # Las tres páginas de un solo caso: una pasada cada una.
            for archivo, verificador in (
                ("primer-paso.html", lambda p, w, f: verificar_foco_inicial(p, w, f, espera_paso=False)),
                ("errores.html", verificar_errores),
                ("errores-sin-alpine.html", verificar_errores),
                ("sin-alpine.html", lambda p, w, f: verificar_sin_alpine(p, w, f) + " " + verificar_enter(p, w, f)),
            ):
                if not (carpeta / archivo).is_file():
                    continue

                contexto = navegador.new_context(viewport={"width": 1440, "height": 900})
                pagina = contexto.new_page()
                pagina.goto((carpeta / archivo).as_uri())
                pagina.wait_for_timeout(250)

                donde = f"{nombre} {archivo}"
                d = pagina.evaluate(SONDA)
                peor = verificar_contraste(d, donde, fallos)
                marca = verificador(pagina, donde, fallos)

                resumen.append(f"{nombre:<9}{archivo:<17}[{'—':<13} {'escritorio':<10}] {marca} peor contraste={peor:.2f}:1")
                contexto.close()

            navegador.close()

    print("\n".join(resumen))

    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1

    print(
        f"\nTodo pasa: {len(NAVEGADORES)} navegadores × {len(completas)} páginas completas × "
        f"{len(MOVIMIENTOS)} preferencias de movimiento × {len(ANCHOS)} anchos, más el primer paso, "
        "la página con errores y la que corre sin Alpine."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
