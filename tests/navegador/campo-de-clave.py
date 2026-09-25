#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::input-password>` sobre el banco `build/campo-de-clave/`.

    .venv-a11y/bin/python tests/navegador/campo-de-clave.py [build/campo-de-clave]

La corre `tests/CampoDeClaveTest.php` después de generar el banco (y se salta si
no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest leen
el HTML que emite Blade: que el alternador sea `type="button"`, que lleve
`aria-pressed`, que nazca con `x-cloak`. Lo que de verdad prometía la ficha
`campo-clave` solo se ve con un navegador de verdad, y es esto:

  · al cargar, los dos campos son `type="password"`, con el `autocomplete` que
    les corresponde (`current-password` y `new-password`), y el alternador ya
    está visible, mide al menos 24 × 24 px y se llama «Mostrar contraseña»
    (nombre accesible calculado por el navegador, no el texto que yo suponga);
  · el orden de tabulación es campo → alternador: Tab desde el correo cae en la
    clave, y el siguiente Tab en su botón, no al revés;
  · con el cursor puesto en medio de la clave, Enter sobre el botón revela el
    valor SIN perderlo y SIN mover el punto de inserción, el foco se queda en el
    botón, `aria-pressed` pasa a `true` y el nombre cambia a «Ocultar
    contraseña». Espacio hace lo mismo a la inversa. En ningún momento se envía
    el formulario: si el botón fuera `submit` —el bug clásico— el contador de
    envíos subiría;
  · volver al campo NO oculta la clave (el foco sigue dentro del componente),
    pero enviar el formulario sí; y salir con Tab fuera del componente, también;
  · Escape sobre la clave revelada la oculta, y cuando no hay nada que ocultar
    la tecla sigue su camino (no se la queda el campo);
  · el foco del botón es un `outline` sólido de 3 px con color, no una sombra;
  · con el ratón encima el fondo del alternador cambia (el hover se ve) y su
    borde sigue por encima de 3:1 sobre ese fondo nuevo;
  · el texto del alternador y su borde pasan WCAG 2.2 AA contra el fondo
    efectivo, en claro y en oscuro;
  · con `prefers-reduced-motion: reduce` la transición del botón computa 0 s, y
    sin la preferencia existe (si no, la comprobación sería vacía);
  · SIN JavaScript el alternador no se ofrece: no ocupa ni un píxel y el campo
    sigue siendo `type="password"`;
  · cero `pageerror` en todo el recorrido.

En Chromium, además, deja capturas de escritorio y de teléfono en
`<banco>/capturas/`, y comprueba que a 390 px no haya desplazamiento horizontal.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "campo-de-clave"
ESCRITORIO = {"width": 1440, "height": 900}
TELEFONO = {"width": 390, "height": 844}

# Lo que se lee de cada campo de clave con estilos computados.
SONDA = r"""
() => {
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };
  const visible = el => {
    const c = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return c.display !== 'none' && c.visibility !== 'hidden' && r.width > 0 && r.height > 0;
  };
  const textoVisible = el => [...el.querySelectorAll('span')]
    .filter(visible).map(s => s.textContent.trim()).filter(Boolean).join(' ');

  const campos = [...document.querySelectorAll('.muni-pw')].map(pw => {
    const campo = pw.querySelector('input');
    const boton = pw.querySelector('button');
    const cb = boton ? getComputedStyle(boton) : null;
    return {
      id: campo.id,
      tipo: campo.getAttribute('type'),
      tipoProp: campo.type,
      nombre: campo.getAttribute('name'),
      autocomplete: campo.getAttribute('autocomplete'),
      valor: campo.value,
      inicio: campo.selectionStart,
      fin: campo.selectionEnd,
      boton: boton === null ? null : {
        visible: visible(boton),
        ...caja(boton),
        tipo: boton.getAttribute('type'),
        pressed: boton.getAttribute('aria-pressed'),
        controla: boton.getAttribute('aria-controls'),
        texto: textoVisible(boton),
        color: cb.color,
        fondo: cb.backgroundColor,
        borde: cb.borderTopColor,
        outline: cb.outlineWidth + ' ' + cb.outlineStyle + ' ' + cb.outlineColor,
        transicionDuracion: cb.transitionDuration,
        sombra: cb.boxShadow,
      },
    };
  });

  const activo = document.activeElement;
  return {
    alpine: !!window.Alpine,
    fondoPagina: getComputedStyle(document.body).backgroundColor,
    activo: activo ? (activo.id || activo.className || activo.tagName) : null,
    envios: window.__envios || 0,
    desplazamiento: document.documentElement.scrollWidth > document.documentElement.clientWidth,
    campos,
  };
}
"""

# El banco no navega, pero hay que saber si el formulario intentó enviarse: un
# alternador sin `type="button"` es `submit` y ese es justamente el defecto.
CONTADOR = r"""
() => {
  window.__envios = 0;
  document.getElementById('banco-form').addEventListener('submit', () => { window.__envios++; });
}
"""


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


def contraste(frente: str, fondo: str, fondo_pagina: str) -> float:
    base = _mezclar(fondo, fondo_pagina)
    base_css = f"rgb({base[0]}, {base[1]}, {base[2]})"
    l1 = _luminancia(_mezclar(frente, base_css))
    l2 = _luminancia(base)
    claro, oscuro = max(l1, l2), min(l1, l2)
    return (claro + 0.05) / (oscuro + 0.05)


def recorrer(pagina, nombre: str, fallos: list[str]) -> dict:
    """El recorrido completo sobre una página con JS. Devuelve la línea de resumen."""
    quien = nombre

    pagina.evaluate(CONTADOR)
    d = pagina.evaluate(SONDA)

    if not d["alpine"]:
        fallos.append(f"{quien}: la página no cargó Alpine; el resto del recorrido no probaría nada")
        return d
    if len(d["campos"]) != 2:
        fallos.append(f"{quien}: hay {len(d['campos'])} campo(s) de clave, esperaba 2")
        return d

    esperados = {"clave_actual": "current-password", "clave_nueva": "new-password"}
    for c in d["campos"]:
        si = f"{quien} [{c['nombre']}]"
        if c["tipo"] != "password":
            fallos.append(f"{si}: nace como type={c['tipo']!r}; la clave nace SIEMPRE oculta")
        if c["autocomplete"] != esperados.get(c["nombre"]):
            fallos.append(f"{si}: autocomplete={c['autocomplete']!r}, esperaba {esperados.get(c['nombre'])!r}")
        b = c["boton"]
        if b is None:
            fallos.append(f"{si}: no hay alternador")
            continue
        if not b["visible"]:
            fallos.append(f"{si}: el alternador sigue oculto con Alpine cargado (x-cloak no se levantó)")
        if b["tipo"] != "button":
            fallos.append(f"{si}: el alternador es type={b['tipo']!r}: dentro de un form eso envía")
        if b["alto"] < 24 or b["ancho"] < 24:
            fallos.append(f"{si}: el alternador mide {b['ancho']:.0f}×{b['alto']:.0f} px (mínimo 24×24, WCAG 2.5.8)")
        if b["pressed"] != "false":
            fallos.append(f"{si}: nace con aria-pressed={b['pressed']!r}, esperaba 'false'")
        if b["controla"] != c["id"]:
            fallos.append(f"{si}: aria-controls={b['controla']!r} y el campo es {c['id']!r}")
        if b["texto"] != "Mostrar contraseña":
            fallos.append(f"{si}: el alternador se lee «{b['texto']}», esperaba «Mostrar contraseña»")

        # Contraste del texto y del borde del alternador contra el fondo efectivo.
        ratio = contraste(b["color"], b["fondo"], d["fondoPagina"])
        if ratio < 4.5:
            fallos.append(f"{si}: el texto del alternador da {ratio:.2f}:1 (mínimo 4,5:1)")
        ratio_borde = contraste(b["borde"], b["fondo"], d["fondoPagina"])
        if ratio_borde < 3.0:
            fallos.append(f"{si}: el borde del alternador da {ratio_borde:.2f}:1 (mínimo 3:1, WCAG 1.4.11)")

    # El nombre accesible, calculado por el navegador y no por mí.
    for rotulo, cuantos in (("Mostrar contraseña", 2), ("Ocultar contraseña", 0)):
        visto = pagina.get_by_role("button", name=rotulo).count()
        if visto != cuantos:
            fallos.append(f"{quien}: hay {visto} botón(es) «{rotulo}», esperaba {cuantos}")

    # ---- El ratón encima: el fondo cambia y el borde SIGUE dando 3:1 ----
    # El borde del alternador va en `style` en línea y no en la hoja del
    # componente porque `<x-muni::button>` ya emite `border:1px solid
    # transparent` en línea, y un estilo en línea le gana a cualquier regla sin
    # `!important`: medido aparte en Chromium, `.muni-btn--ghost` y su `:hover`
    # NO mueven el borde de ningún botón fantasma del paquete —el hover se
    # comunica por el fondo—. Acá se mide eso mismo sobre el alternador real: que
    # el fondo cambie de verdad al pasar el ratón (el hover se ve) y que el borde
    # se quede por encima de 3:1 también en ese estado, en los dos temas.
    reposo = d["campos"][0]["boton"]
    pagina.locator(".muni-pw__ojo").first.hover()
    pagina.wait_for_timeout(80)
    encima = pagina.evaluate(SONDA)["campos"][0]["boton"]
    hover_borde = contraste(encima["borde"], encima["fondo"], d["fondoPagina"])
    if encima["fondo"] == reposo["fondo"]:
        fallos.append(
            f"{quien}: con el ratón encima no cambia nada en el alternador (fondo {reposo['fondo']}); "
            "el hover no se ve"
        )
    if hover_borde < 3.0:
        fallos.append(
            f"{quien}: el borde del alternador con el ratón encima da {hover_borde:.2f}:1 (mínimo 3:1); "
            "el fondo del hover se comió el contraste del borde"
        )
    pagina.mouse.move(0, 0)

    # ---- Orden de tabulación: correo → clave → alternador ----
    pagina.focus("#banco-correo")
    pagina.keyboard.press("Tab")
    activo = pagina.evaluate("() => document.activeElement.id")
    if activo != "muni-clave_actual":
        fallos.append(f"{quien}: tras el correo el foco cae en {activo!r}, esperaba el campo de clave")

    pagina.keyboard.type("Clave2026#muni")
    pagina.evaluate("() => document.getElementById('muni-clave_actual').setSelectionRange(5, 5)")
    pagina.keyboard.press("Tab")
    activo = pagina.evaluate("() => document.activeElement.tagName + ':' + (document.activeElement.className || '')")
    if "BUTTON" not in activo or "muni-pw__ojo" not in activo:
        fallos.append(f"{quien}: tras el campo el foco cae en {activo!r}, esperaba el alternador")

    # ---- Enter revela sin perder el valor ni el cursor, y sin enviar ----
    pagina.keyboard.press("Enter")
    # El tipo del campo cambia en el mismo tick (lo hace el manejador); el rótulo
    # del botón lo pinta Alpine en el siguiente, y por eso se espera antes de leer.
    pagina.wait_for_timeout(60)
    d = pagina.evaluate(SONDA)
    c = d["campos"][0]
    b = c["boton"]
    if c["tipoProp"] != "text":
        fallos.append(f"{quien}: Enter no reveló la clave (type={c['tipoProp']!r})")
    if c["valor"] != "Clave2026#muni":
        fallos.append(f"{quien}: al alternar se perdió el valor: {c['valor']!r}")
    cursor = c["inicio"]
    if (c["inicio"], c["fin"]) != (5, 5):
        fallos.append(f"{quien}: al alternar el punto de inserción saltó a {c['inicio']}–{c['fin']}, esperaba 5–5")
    if b["pressed"] != "true":
        fallos.append(f"{quien}: aria-pressed={b['pressed']!r} con la clave a la vista")
    if b["texto"] != "Ocultar contraseña":
        fallos.append(f"{quien}: revelada, el alternador se lee «{b['texto']}»")
    if "muni-pw__ojo" not in (d["activo"] or ""):
        fallos.append(f"{quien}: el foco se fue del alternador al alternar: {d['activo']!r}")
    if d["envios"] != 0:
        fallos.append(f"{quien}: alternar ENVIÓ el formulario {d['envios']} vez/veces (el botón no es type=button)")
    if pagina.get_by_role("button", name="Ocultar contraseña").count() != 1:
        fallos.append(f"{quien}: el nombre accesible no cambió a «Ocultar contraseña»")

    # El foco del botón es un outline de 3 px, no una sombra.
    ancho, estilo, color = b["outline"].split(" ", 2)
    if ancho != "3px" or estilo != "solid":
        fallos.append(f"{quien}: el foco del alternador computa «{b['outline']}», esperaba 3px solid")
    if _rgb(color)[3] == 0:
        fallos.append(f"{quien}: el outline del foco es transparente: {color}")

    # ---- Espacio vuelve a ocultar, tampoco envía ----
    pagina.keyboard.press("Space")
    pagina.wait_for_timeout(60)
    d = pagina.evaluate(SONDA)
    c = d["campos"][0]
    if c["tipoProp"] != "password":
        fallos.append(f"{quien}: Espacio no volvió a ocultar la clave (type={c['tipoProp']!r})")
    if (c["inicio"], c["fin"]) != (5, 5):
        fallos.append(f"{quien}: al ocultar el punto de inserción saltó a {c['inicio']}–{c['fin']}")
    if d["envios"] != 0:
        fallos.append(f"{quien}: Espacio sobre el alternador envió el formulario")

    # ---- Escape oculta lo revelado, y no se queda la tecla si no hay nada ----
    # El oyente está en `document`: si el componente detiene la propagación, no
    # llega. Revelada, TIENE que detenerla (la tecla se usó); oculta, no.
    pagina.evaluate("""() => {
        window.__escape = 0;
        document.addEventListener('keydown', e => { if (e.key === 'Escape') window.__escape++; });
    }""")
    pagina.keyboard.press("Enter")
    pagina.keyboard.press("Escape")
    if pagina.evaluate("() => document.getElementById('muni-clave_actual').type") != "password":
        fallos.append(f"{quien}: Escape no ocultó la clave revelada")
    if pagina.evaluate("() => window.__escape") != 0:
        fallos.append(f"{quien}: la Escape que ocultó la clave siguió subiendo hasta el documento")
    pagina.keyboard.press("Escape")
    if pagina.evaluate("() => window.__escape") != 1:
        fallos.append(f"{quien}: sin nada que ocultar, el componente se queda con Escape (no llegó al documento)")

    # ---- Volver al campo NO oculta; enviar el formulario sí ----
    pagina.keyboard.press("Enter")
    pagina.locator("#muni-clave_actual").click()
    pagina.wait_for_timeout(50)
    if pagina.evaluate("() => document.getElementById('muni-clave_actual').type") != "text":
        fallos.append(f"{quien}: volver al campo ocultó la clave; el foco sigue dentro del componente")

    pagina.evaluate("() => document.getElementById('banco-form').requestSubmit()")
    pagina.wait_for_timeout(50)
    d = pagina.evaluate(SONDA)
    if d["campos"][0]["tipoProp"] != "password":
        fallos.append(f"{quien}: al enviar el formulario la clave quedó a la vista")
    if d["envios"] != 1:
        fallos.append(f"{quien}: el envío de prueba no llegó al formulario ({d['envios']})")

    # ---- Salir con Tab del componente oculta ----
    pagina.locator(".muni-pw__ojo").first.click()
    pagina.wait_for_timeout(30)
    if pagina.evaluate("() => document.getElementById('muni-clave_actual').type") != "text":
        fallos.append(f"{quien}: el clic sobre el alternador no reveló la clave")
    pagina.focus("#banco-correo")
    pagina.wait_for_timeout(60)
    d = pagina.evaluate(SONDA)
    if d["campos"][0]["tipoProp"] != "password":
        fallos.append(f"{quien}: al salir el foco del componente la clave siguió a la vista")
    if d["campos"][0]["boton"]["pressed"] != "false":
        fallos.append(f"{quien}: la clave se ocultó pero el botón sigue diciendo pulsado")

    d["cursor"] = cursor
    d["hoverBorde"] = f"{hover_borde:.2f}"
    return d


def sin_javascript(navegador, url: str, nombre: str, fallos: list[str]) -> None:
    contexto = navegador.new_context(viewport=ESCRITORIO, java_script_enabled=False, reduced_motion="reduce")
    pagina = contexto.new_page()
    pagina.goto(url)
    pagina.wait_for_timeout(150)

    d = pagina.evaluate(SONDA)
    if d["alpine"]:
        fallos.append(f"{nombre} [sin JS]: Alpine corrió igual; la comprobación sería vacía")
    for c in d["campos"]:
        if c["tipoProp"] != "password":
            fallos.append(f"{nombre} [sin JS] {c['nombre']}: type={c['tipoProp']!r}")
        b = c["boton"]
        if b["visible"] or b["alto"] > 0:
            fallos.append(
                f"{nombre} [sin JS] {c['nombre']}: el alternador ocupa {b['ancho']:.0f}×{b['alto']:.0f} px. "
                "Sin Alpine no alterna nada y el lector lo anuncia igual."
            )
    contexto.close()


def main(argv: list[str]) -> int:
    carpeta = Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 2:
        print(f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
              "`vendor/bin/pest --filter=CampoDeClave`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []
    capturas = carpeta / "capturas"
    capturas.mkdir(exist_ok=True)

    with sync_playwright() as pw:
        for marca in ("chromium", "firefox"):
            navegador = getattr(pw, marca).launch()
            for ruta in paginas:
                url = ruta.as_uri()
                erroresJs: list[str] = []

                contexto = navegador.new_context(viewport=ESCRITORIO, reduced_motion="reduce")
                pagina = contexto.new_page()
                pagina.on("pageerror", lambda e: erroresJs.append(str(e)))
                pagina.goto(url)
                pagina.wait_for_timeout(200)

                d = recorrer(pagina, f"{marca}/{ruta.name}", fallos)

                duraciones = [x.strip() for x in d["campos"][0]["boton"]["transicionDuracion"].split(",")] if d.get("campos") else []
                if any(x != "0s" for x in duraciones):
                    fallos.append(f"{marca}/{ruta.name}: con prefers-reduced-motion la transición sigue viva: {duraciones}")

                if marca == "chromium":
                    pagina.screenshot(path=str(capturas / f"{ruta.stem}-escritorio.png"), full_page=True)
                    pagina.set_viewport_size(TELEFONO)
                    pagina.wait_for_timeout(120)
                    if pagina.evaluate(SONDA)["desplazamiento"]:
                        fallos.append(f"{ruta.name}: a 390 px la página se desplaza en horizontal")
                    pagina.screenshot(path=str(capturas / f"{ruta.stem}-telefono.png"), full_page=True)

                if erroresJs:
                    fallos.append(f"{marca}/{ruta.name}: errores de JS: {erroresJs}")
                contexto.close()

                # Sin la preferencia la transición tiene que existir; si no, la
                # comprobación de arriba no probaría nada.
                contexto = navegador.new_context(viewport=ESCRITORIO, reduced_motion="no-preference")
                pagina = contexto.new_page()
                pagina.goto(url)
                pagina.wait_for_timeout(150)
                vivas = pagina.evaluate(SONDA)["campos"][0]["boton"]["transicionDuracion"]
                if all(x.strip() == "0s" for x in vivas.split(",")):
                    fallos.append(f"{marca}/{ruta.name}: sin la preferencia la transición ya es 0 s")
                contexto.close()

                sin_javascript(navegador, url, f"{marca}/{ruta.name}", fallos)

                # `transición(reduce)` sale de la MEDICIÓN de arriba (`duraciones`), no
                # de un literal: escrito a mano, el recuento que hace
                # tests/CampoDeClaveTest.php sobre esta línea daría 4 aunque la
                # transición siguiera viva, y aparentaría medir algo que no mide.
                reduccion = "0s" if duraciones and all(x == "0s" for x in duraciones) else ",".join(duraciones)

                resumen.append(
                    f"{marca:<9} {ruta.name:<12} cursor={d.get('cursor')} "
                    f"transición(reduce)={reduccion or '?'}  envíos={d.get('envios')}  "
                    f"borde/hover={d.get('hoverBorde')}:1  "
                    f"alternador={d['campos'][0]['boton']['ancho']:.0f}×{d['campos'][0]['boton']['alto']:.0f}px"
                )
            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print("\nSin fallos.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
