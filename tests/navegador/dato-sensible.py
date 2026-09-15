#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::pii>` sobre el banco `build/dato-sensible/`.

    .venv-a11y/bin/python tests/navegador/dato-sensible.py [build/dato-sensible]

La corre `tests/DatoSensibleTest.php` después de generar el banco (y se salta si
no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest leen
el HTML que emite Blade —que el valor nazca con `display:none` en línea, que el
control nazca con `x-cloak`, que el evento se llame `muni-pii-revelado`—. Lo que
el componente promete de verdad solo se ve con un navegador, y es esto, medido
en Chromium Y Firefox sobre las CUATRO páginas (dos temas × dos paletas: dentro
de un panel Filament solo se carga `muni-ui-filament.css`, DESIGN §7):

  · al cargar, los cuatro datos servidos se ven ENMASCARADOS y el valor completo
    no ocupa un píxel; el que nace `revealed` —el correo, que es como vuelve la
    vista después de que el anfitrión registró el acceso— se ve abierto y con el
    botón anunciándose pulsado;
  · el control se alcanza CON EL TECLADO (Tab), su nombre incluye QUÉ dato
    revela —«Mostrar RUT», no cuatro «Mostrar» iguales— y mide al menos 24 × 24;
  · Enter revela: el valor aparece, `aria-pressed` pasa a `true`, el foco se
    queda en el botón y el oyente que la página tiene en `document` —el papel
    del anfitrión— recibe `muni-pii-revelado` con el campo, la etiqueta y el id.
    Ese evento es TODO lo que el paquete puede aportar a la trazabilidad de la
    Ley 21.719: la bitácora es del host (por eso el juez sacó `ficha-persona`);
  · Escape vuelve a taparlo, que es el gesto del mesón cuando alguien se acerca;
  · el dato en MODO DIFERIDO (el diagnóstico, que no se sirvió) no tiene el
    valor en el DOM: al pulsarlo aparece «Solicitando el dato…» y el evento
    llega con `diferido: true`. Es el único modo que cumple minimización de
    verdad: lo que no se sirvió no está en la página;
  · el foco del control es un `outline` sólido de 3 px con color, no una sombra
    (dentro de Filament la sombra se pierde, DESIGN §5);
  · la máscara, el valor revelado, el texto de espera y el texto del control
    pasan WCAG 2.2 AA contra su fondo efectivo, y el borde del control 3:1;
  · el RUT sale en mono tabular en una página SIN UNA SOLA TABLA: la clase viaja
    en el bloque de estilos del propio componente (DESIGN §7);
  · con `@media print` EMULADO el control computa `display:none` y el dato que
    esté en pantalla es el que sale;
  · con `prefers-reduced-motion: reduce` la transición del control computa 0 s,
    y sin la preferencia existe (si no, la comprobación sería vacía). En las dos
    páginas de panel NO se exige, y el motivo es un defecto del paquete que este
    componente no puede arreglar: `muni-ui-filament.css` declara
    `--muni-dur:160ms` y no trae el bloque `prefers-reduced-motion` que sí trae
    `muni-ui.css`, así que dentro de un panel Filament la preferencia no llega a
    NINGÚN componente. Se mide igual y sale en el resumen de cada corrida;
  · SIN JavaScript el control no se ofrece —no ocupa un píxel— y el dato sigue
    tapado: un componente de PII que se abriera solo al fallar Alpine sería
    exactamente la sobreexposición que el juez objetó;
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
BANCO_POR_DEFECTO = RAIZ / "build" / "dato-sensible"
NAVEGADORES = ("chromium", "firefox")
ESCRITORIO = {"width": 1440, "height": 900}
TELEFONO = {"width": 390, "height": 844}

# Los cinco datos del banco: campo → (lleva valor servido, nace revelado).
DATOS = {
    "rut": (True, False),
    "domicilio": (True, False),
    "telefono": (True, False),
    "diagnostico": (False, False),
    "correo": (True, True),
}

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
    const r = el.getBoundingClientRect();
    return c.display !== 'none' && c.visibility !== 'hidden' && r.width > 0 && r.height > 0;
  };

  const texto = el => {
    if (!el) return null;
    const c = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return {
      texto: (el.textContent || '').replace(/\s+/g, ' ').trim(),
      visible: visible(el),
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
      familia: c.fontFamily,
      display: c.display,
      ancho: r.width,
      alto: r.height,
    };
  };

  const datos = [...document.querySelectorAll('[data-muni-pii-campo]')].map(raiz => {
    const boton = raiz.querySelector('button');
    const cb = boton ? getComputedStyle(boton) : null;
    const rb = boton ? boton.getBoundingClientRect() : null;
    const valor = raiz.querySelector('.muni-pii__valor');
    const controla = boton ? boton.getAttribute('aria-controls') : null;

    return {
      campo: raiz.dataset.muniPiiCampo,
      etiqueta: raiz.dataset.muniPiiEtiqueta,
      id: raiz.id,
      mascara: texto(raiz.querySelector('.muni-pii__mascara')),
      pendiente: texto(raiz.querySelector('.muni-pii__pendiente')),
      real: texto(raiz.querySelector('.muni-pii__real')),
      hayReal: raiz.querySelector('.muni-pii__real') !== null,
      /* El valor completo NO puede estar en ningún rincón del subárbol cuando
         el anfitrión no lo sirvió: es la prueba de la minimización. */
      textoCrudo: (raiz.textContent || '').replace(/\s+/g, ' ').trim(),
      live: valor ? valor.getAttribute('aria-live') : null,
      busy: valor ? valor.getAttribute('aria-busy') : null,
      controla,
      controlaExiste: controla ? document.getElementById(controla) !== null : false,
      boton: boton === null ? null : {
        visible: visible(boton),
        ancho: rb.width,
        alto: rb.height,
        tipo: boton.getAttribute('type'),
        pressed: boton.getAttribute('aria-pressed'),
        /* El nombre que arma el lector: el texto del botón incluye el <span>
           solo para lector con la etiqueta del dato. */
        nombre: (boton.textContent || '').replace(/\s+/g, ' ').trim(),
        color: cb.color,
        fondo: fondoDe(boton),
        borde: cb.borderTopColor,
        tamano: parseFloat(cb.fontSize),
        peso: parseInt(cb.fontWeight, 10) || 400,
        display: cb.display,
        transicion: cb.transitionDuration,
        outline: {
          ancho: cb.outlineWidth,
          estilo: cb.outlineStyle,
          color: cb.outlineColor,
          sombra: cb.boxShadow,
        },
        enfocado: document.activeElement === boton,
      },
    };
  });

  const act = document.activeElement;

  return {
    alpine: !!window.Alpine,
    eventos: (window.__muniPii || []).slice(),
    datos,
    activo: act === null ? null : {
      tag: act.tagName,
      campo: act.closest ? (act.closest('[data-muni-pii-campo]') || {}).id || null : null,
      esBoton: act.tagName === 'BUTTON' && act.classList.contains('muni-pii__boton'),
    },
    desbordaX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
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
    """4,5:1, o 3:1 si el texto es grande (>=24px, o >=18.66px en negrita)."""
    grande = tamano >= 24 or (tamano >= 18.66 and peso >= 700)
    return 3.0 if grande else 4.5


def por_campo(d: dict) -> dict:
    return {x["campo"]: x for x in d["datos"]}


def verificar_inicial(d: dict, donde: str, fallos: list[str]) -> None:
    datos = por_campo(d)

    if not d["alpine"]:
        fallos.append(f"{donde}: Alpine no montó; todo lo demás se mediría sobre un DOM muerto.")

    if set(datos) != set(DATOS):
        fallos.append(f"{donde}: el banco trae {sorted(datos)} y se esperaban {sorted(DATOS)}.")
        return

    for campo, (tiene_valor, nace_abierto) in DATOS.items():
        x = datos[campo]
        b = x["boton"]

        if b is None:
            fallos.append(f"{donde} [{campo}]: no hay control.")
            continue

        if not b["visible"]:
            fallos.append(f"{donde} [{campo}]: el control no se ve; x-cloak no se levantó.")
        if min(b["ancho"], b["alto"]) < 24:
            fallos.append(
                f"{donde} [{campo}]: el control mide {b['ancho']:.0f}×{b['alto']:.0f} px; "
                "WCAG 2.2 AA 2.5.8 pide 24×24."
            )
        if b["tipo"] != "button":
            fallos.append(f"{donde} [{campo}]: el control es type={b['tipo']!r}; dentro de un <form> enviaría la ficha.")
        if x["etiqueta"] not in b["nombre"]:
            fallos.append(
                f"{donde} [{campo}]: el nombre del control es {b['nombre']!r} y no dice qué dato revela "
                f"({x['etiqueta']!r}): en una ficha con cuatro, el lector los anuncia iguales (2.4.6)."
            )
        if not x["controlaExiste"]:
            fallos.append(f"{donde} [{campo}]: aria-controls={x['controla']!r} apunta a un id que no existe.")
        if x["live"] != "polite":
            fallos.append(f"{donde} [{campo}]: el valor no es región viva educada (aria-live={x['live']!r}).")

        esperado = "true" if nace_abierto else "false"
        if b["pressed"] != esperado:
            fallos.append(f"{donde} [{campo}]: aria-pressed={b['pressed']!r} y se esperaba {esperado!r}.")

        if tiene_valor and not x["hayReal"]:
            fallos.append(f"{donde} [{campo}]: el anfitrión sirvió el valor y no está en el DOM.")

        if not tiene_valor:
            # Minimización: sin valor servido, el dato no puede estar en ningún
            # rincón del subárbol, ni tapado por CSS.
            if x["hayReal"]:
                fallos.append(f"{donde} [{campo}]: modo diferido y sin embargo sirve un contenedor de valor.")
            if x["pendiente"]["visible"]:
                fallos.append(f"{donde} [{campo}]: el texto de espera se ve antes de pulsar nada.")

        if nace_abierto:
            if not (x["real"] and x["real"]["visible"]):
                fallos.append(f"{donde} [{campo}]: nace con `revealed` y el valor no se ve.")
            if x["mascara"] and x["mascara"]["visible"]:
                fallos.append(f"{donde} [{campo}]: nace abierto y la máscara sigue a la vista.")
        else:
            if x["real"] and x["real"]["visible"]:
                fallos.append(
                    f"{donde} [{campo}]: el valor completo SE VE sin que nadie lo revelara "
                    f"({x['real']['texto']!r}). Es la sobreexposición que este componente existe para evitar."
                )
            if not (x["mascara"] and x["mascara"]["visible"]):
                fallos.append(f"{donde} [{campo}]: la máscara no se ve; el dato queda sin nada en pantalla.")


def verificar_mono(d: dict, donde: str, fallos: list[str]) -> None:
    """El RUT en mono tabular en una página sin una sola tabla (DESIGN §7)."""
    rut = por_campo(d).get("rut")
    if rut is None or rut["mascara"] is None:
        return
    familia = (rut["mascara"]["familia"] or "").lower()
    if "mono" not in familia and "courier" not in familia and "consol" not in familia:
        fallos.append(
            f"{donde} [rut]: la máscara computa font-family={familia!r}. `.muni-num` no llegó: en esta "
            "página no hay ninguna tabla, así que la clase tiene que viajar en el bloque de estilos "
            "del propio componente (DESIGN §7)."
        )


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 21.0

    for x in d["datos"]:
        piezas = [("máscara", x["mascara"]), ("valor", x["real"]), ("espera", x["pendiente"])]
        b = x["boton"]
        if b:
            piezas.append(("control", {
                "texto": b["nombre"], "visible": b["visible"], "color": b["color"], "fondo": b["fondo"],
                "tamano": b["tamano"], "peso": b["peso"],
            }))

        for nombre, pieza in piezas:
            if not pieza or not pieza["visible"] or not pieza["texto"]:
                continue
            r = contraste(pieza["color"], pieza["fondo"])
            if r is None:
                continue
            peor = min(peor, r)
            exigido = minimo(pieza["tamano"], pieza["peso"])
            if r < exigido:
                fallos.append(
                    f"{donde} [{x['campo']}/{nombre}]: {r:.2f}:1 contra su fondo efectivo; "
                    f"se exige {exigido}:1 (WCAG 2.2 AA 1.4.3)."
                )

        if b and b["visible"]:
            rb = contraste(b["borde"], b["fondo"])
            if rb is not None:
                peor = min(peor, rb)
                if rb < 3.0:
                    fallos.append(
                        f"{donde} [{x['campo']}/borde del control]: {rb:.2f}:1; WCAG 1.4.11 pide 3:1 "
                        "para el límite de un control."
                    )

    return peor


def tabular_hasta_el_control(pagina, fallos: list[str], donde: str) -> dict | None:
    """Tab desde el principio hasta el primer control del componente."""
    pagina.evaluate("() => document.body.focus()")
    for _ in range(14):
        pagina.keyboard.press("Tab")
        estado = pagina.evaluate(SONDA)
        if estado["activo"] and estado["activo"]["esBoton"]:
            return estado
    fallos.append(f"{donde}: el control no se alcanza con Tab en 14 saltos; con teclado el dato no se puede revelar.")
    return None


def verificar_foco(estado: dict, donde: str, fallos: list[str]) -> str:
    """El foco es un outline sólido de 3 px, no una sombra (DESIGN §5)."""
    activo = [x for x in estado["datos"] if x["boton"] and x["boton"]["enfocado"]]
    if not activo:
        fallos.append(f"{donde}: nadie tiene el foco tras tabular.")
        return "?"

    o = activo[0]["boton"]["outline"]
    ancho = float(re.sub(r"[^\d.]", "", o["ancho"]) or 0)

    if o["estilo"] in ("none", "hidden") or ancho < 3:
        fallos.append(
            f"{donde}: el foco del control computa outline {o['ancho']} {o['estilo']}; se exigen 3 px sólidos "
            "(la box-shadow del anillo se pierde dentro de Filament, DESIGN §5)."
        )
    if a_rgb(o["color"]) is None:
        fallos.append(f"{donde}: el outline del foco no tiene color resoluble ({o['color']!r}).")

    return f"{o['ancho']} {o['estilo']}"


def verificar_revelado(d: dict, donde: str, fallos: list[str]) -> None:
    rut = por_campo(d)["rut"]

    if not (rut["real"] and rut["real"]["visible"]):
        fallos.append(f"{donde} [rut]: Enter sobre el control no reveló el valor.")
    if rut["mascara"]["visible"]:
        fallos.append(f"{donde} [rut]: revelado y la máscara sigue a la vista: se ven los dos a la vez.")
    if rut["boton"]["pressed"] != "true":
        fallos.append(f"{donde} [rut]: tras revelar, aria-pressed={rut['boton']['pressed']!r}.")
    if not rut["boton"]["enfocado"]:
        fallos.append(f"{donde} [rut]: el foco se fue del control al revelar; quien usa teclado lo pierde.")

    eventos = [e for e in d["eventos"] if e.get("campo") == "rut"]
    if not eventos:
        fallos.append(
            f"{donde} [rut]: el oyente del anfitrión no recibió `muni-pii-revelado`. Sin ese evento el "
            "paquete estaría sirviendo PII sin darle al host con qué registrar el acceso."
        )
        return

    e = eventos[-1]
    if e.get("etiqueta") != "RUT" or not e.get("id") or e.get("diferido") is not False:
        fallos.append(f"{donde} [rut]: el detalle del evento llegó incompleto: {e!r}.")


def verificar_diferido(d: dict, donde: str, fallos: list[str]) -> None:
    dx = por_campo(d)["diagnostico"]

    if not dx["pendiente"]["visible"]:
        fallos.append(f"{donde} [diagnostico]: tras pulsar no aparece el texto de espera; la pantalla no dice nada.")
    if dx["busy"] != "true":
        fallos.append(f"{donde} [diagnostico]: la región del valor no se marca aria-busy mientras espera.")
    if dx["hayReal"]:
        fallos.append(f"{donde} [diagnostico]: el modo diferido sirvió un valor que el anfitrión no mandó.")

    eventos = [e for e in d["eventos"] if e.get("campo") == "diagnostico"]
    if not eventos:
        fallos.append(f"{donde} [diagnostico]: el anfitrión no recibió el evento del dato no servido.")
    elif eventos[-1].get("diferido") is not True:
        fallos.append(f"{donde} [diagnostico]: el evento no dice que el dato viene diferido: {eventos[-1]!r}.")


def verificar_impresion(d: dict, donde: str, fallos: list[str]) -> str:
    visto = set()
    for x in d["datos"]:
        b = x["boton"]
        if b is None:
            continue
        visto.add(b["display"])
        if b["display"] != "none":
            fallos.append(
                f"{donde} [{x['campo']}]: al imprimir el control computa display={b['display']!r}; en el acta "
                "que emite el mesón un botón «Mostrar» es tinta y ruido."
            )
    return ",".join(sorted(visto))


def sin_javascript(navegador, url: str, donde: str, fallos: list[str]) -> str:
    contexto = navegador.new_context(viewport=ESCRITORIO, java_script_enabled=False)
    pagina = contexto.new_page()
    pagina.goto(url)
    pagina.wait_for_timeout(150)

    d = pagina.evaluate(SONDA)
    estado = "tapado"

    if d["alpine"]:
        fallos.append(f"{donde} [sin JS]: Alpine corrió igual; la comprobación sería vacía.")
        estado = "?"

    for x in d["datos"]:
        b = x["boton"]
        if b and (b["visible"] or b["alto"] > 0):
            fallos.append(
                f"{donde} [sin JS] [{x['campo']}]: el control ocupa {b['ancho']:.0f}×{b['alto']:.0f} px. "
                "Sin Alpine no revela nada y el lector lo anuncia igual."
            )
            estado = "control a la vista"
        # El que nace `revealed` se ve a propósito: lo sirvió el anfitrión ya
        # revelado. Los demás tienen que seguir tapados sin JavaScript.
        if not DATOS[x["campo"]][1] and x["real"] and x["real"]["visible"]:
            fallos.append(
                f"{donde} [sin JS] [{x['campo']}]: el valor completo se ve sin JavaScript "
                f"({x['real']['texto']!r}): el `display:none` en línea no está haciendo su trabajo."
            )
            estado = "dato a la vista"

    contexto.close()
    return estado


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(
            f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
            "`vendor/bin/pest --filter=DatoSensible`."
        )
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []
    capturas = carpeta / "capturas"
    capturas.mkdir(exist_ok=True)

    with sync_playwright() as pw:
        for marca in NAVEGADORES:
            navegador = getattr(pw, marca).launch()

            for ruta in paginas:
                url = ruta.as_uri()
                donde = f"{marca}/{ruta.name}"
                errores: list[str] = []

                contexto = navegador.new_context(viewport=ESCRITORIO, reduced_motion="reduce")
                pagina = contexto.new_page()
                pagina.on("pageerror", lambda e: errores.append(str(e)))
                pagina.goto(url)
                pagina.wait_for_timeout(250)

                inicial = pagina.evaluate(SONDA)
                verificar_inicial(inicial, donde, fallos)
                verificar_mono(inicial, donde, fallos)

                # Teclado: Tab hasta el control, Enter revela, Escape tapa.
                estado = tabular_hasta_el_control(pagina, fallos, donde)
                foco = verificar_foco(estado, donde, fallos) if estado else "?"

                pagina.keyboard.press("Enter")
                pagina.wait_for_timeout(120)
                tras_enter = pagina.evaluate(SONDA)
                verificar_revelado(tras_enter, donde, fallos)
                verificar_contraste(tras_enter, donde + " [revelado]", fallos)

                pagina.keyboard.press("Escape")
                pagina.wait_for_timeout(120)
                tras_escape = pagina.evaluate(SONDA)
                rut = por_campo(tras_escape)["rut"]
                if rut["real"]["visible"]:
                    fallos.append(f"{donde} [rut]: Escape no tapa el dato revelado.")
                if rut["boton"]["pressed"] != "false":
                    fallos.append(f"{donde} [rut]: tras Escape el control sigue anunciándose pulsado.")

                # El dato diferido, con el ratón: es el gesto del mesón.
                pagina.click('[data-muni-pii-campo="diagnostico"] button')
                pagina.wait_for_timeout(150)
                tras_click = pagina.evaluate(SONDA)
                verificar_diferido(tras_click, donde, fallos)
                peor = verificar_contraste(tras_click, donde, fallos)

                eventos = "ok" if len(tras_click["eventos"]) >= 2 else f"faltan ({len(tras_click['eventos'])})"
                if eventos != "ok":
                    fallos.append(f"{donde}: el anfitrión recibió {len(tras_click['eventos'])} evento(s) y se esperaban 2.")

                # Movimiento reducido. El control no anima nada por su cuenta: la
                # transición es la de <x-muni::button>, que sale de `--muni-dur`,
                # y ese token baja a 0 ms bajo la preferencia... en `muni-ui.css`.
                # En `muni-ui-filament.css` NO: esa hoja declara `--muni-dur:160ms`
                # y no trae el bloque `prefers-reduced-motion` que lo apague, así
                # que DENTRO DE UN PANEL FILAMENT la preferencia no llega a
                # ningún componente del paquete. No es un defecto de este
                # componente y no se arregla desde acá (ningún componente puede
                # redefinir un token del sistema): se anota, se mide y se informa.
                transicion = [t.strip() for t in por_campo(tras_click)["rut"]["boton"]["transicion"].split(",")]
                es_panel = ruta.stem.startswith("panel")
                if any(t != "0s" for t in transicion) and not es_panel:
                    fallos.append(f"{donde}: con prefers-reduced-motion la transición del control sigue viva: {transicion}.")
                reduce = "0s" if all(t == "0s" for t in transicion) else (
                    f"{transicion[0]}(la hoja del panel no apaga --muni-dur)" if es_panel else ",".join(transicion)
                )

                # Impresión: el mismo DOM con @media print emulado.
                pagina.emulate_media(media="print")
                impresion = verificar_impresion(pagina.evaluate(SONDA), donde + " [print]", fallos)
                pagina.emulate_media(media="screen")

                if marca == "chromium":
                    pagina.screenshot(path=str(capturas / f"{ruta.stem}-escritorio.png"), full_page=True)
                    pagina.set_viewport_size(TELEFONO)
                    pagina.wait_for_timeout(150)
                    if pagina.evaluate(SONDA)["desbordaX"]:
                        fallos.append(f"{donde}: a 390 px la página se desplaza en horizontal (WCAG 1.4.10).")
                    pagina.screenshot(path=str(capturas / f"{ruta.stem}-telefono.png"), full_page=True)

                if errores:
                    fallos.append(f"{donde}: errores de JavaScript: {errores}")

                contexto.close()

                # Sin la preferencia la transición tiene que existir; si no, la
                # comprobación de arriba no probaría nada.
                contexto = navegador.new_context(viewport=ESCRITORIO, reduced_motion="no-preference")
                pagina = contexto.new_page()
                pagina.goto(url)
                pagina.wait_for_timeout(150)
                vivas = por_campo(pagina.evaluate(SONDA))["rut"]["boton"]["transicion"]
                if all(t.strip() == "0s" for t in vivas.split(",")):
                    fallos.append(f"{donde}: sin la preferencia la transición del control ya es 0 s.")
                contexto.close()

                sin_js = sin_javascript(navegador, url, donde, fallos)

                resumen.append(
                    f"{marca:<9}{ruta.name:<18}foco={foco:<12} eventos={eventos:<12} "
                    f"peor contraste={peor:.2f}:1  print(control)={impresion:<6} "
                    f"transición(reduce)={reduce:<44} sinJS={sin_js}"
                )

            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(
        f"\nTodo pasa: {len(NAVEGADORES)} navegadores × {len(paginas)} páginas, más impresión emulada, "
        "movimiento reducido y el recorrido sin JavaScript."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
