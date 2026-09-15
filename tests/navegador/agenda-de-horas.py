#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::agenda-horas>` sobre `build/agenda-horas/`.

    .venv-a11y/bin/python tests/navegador/agenda-de-horas.py [build/agenda-horas]

La corre `tests/AgendaDeHorasTest.php` después de generar el banco (y se salta si
no está el entorno de la reja, que en CI no se instala). Las pruebas de Pest leen
el TEXTO del fuente —que exista `ArrowLeft`, que el `:tabindex` siga al foco, que
el outline se declare— y eso ya engañó una vez en `stat`: la regla pasó el test de
texto y en el navegador el defecto seguía vivo. Declarar un manejador no es que el
manejador CORRA, y declarar un outline no es que el outline GANE.

Se mide en Chromium Y Firefox, sobre las cuatro páginas con Alpine (dos temas ×
dentro y fuera del panel, donde solo se carga `muni-ui-filament.css`, DESIGN §7):

  · el recorrido REAL de la rejilla con `document.activeElement`: ←/→ un día,
    ↑/↓ una semana, Inicio/Fin al principio y al fin de la SEMANA, RePág/AvPág
    pidiendo el mes al anfitrión (`mesEvento`: la rejilla queda aria-busy con
    «Cargando abril de 2026…» y, si en 15 s nadie contesta, un error en texto).
    Son las ocho teclas que exige la ficha. En el BORDE del mes las flechas se
    quedan quietas: no piden otro mes ni recargan la página;
  · el bloqueante del revisor: elegir la 09:00 del 12 y pasar al 13 SUELTA la
    hora (FormData, radios marcados, aria-disabled y el evento muni-agenda:accion
    lo confirman), y «Reservar» sin hora del día elegido no emite nada;
  · un día SIN franjas (el 20) deja el foco en el día y dice «El miércoles 20 de
    mayo de 2026 no tiene horas publicadas.», visible y anunciado; un día completo
    (el 14) lleva el foco a su panel y Esc vuelve;
  · una hora de otro día fijada desde fuera (lo que hace `wire:model` por
    `x-modelable`) mueve el día a esa hora;
  · el tabindex itinerante: después de cada movimiento hay UNA sola parada de
    tabulación en el mes y es la que tiene el foco. Con 31 paradas el funcionario
    del mesón no llega nunca a las franjas;
  · Enter elige el día y el foco salta a la primera hora LIBRE (no a la tomada,
    que está `disabled`), Espacio la marca y Esc cancela devolviendo el foco al
    día del que vino. Es lo que la ficha pide y lo que ningún test de texto ve;
  · el foco puesto por TECLADO computa un outline de 3 px sólidos con 3:1 contra
    su fondo efectivo (WCAG 2.2 AA 2.4.7 y 1.4.11). La box-shadow del anillo no
    cuenta: dentro de Filament se computa transparente;
  · solo se ve el panel de un día: los demás computan `display:none` de verdad,
    TAMBIÉN con una hoja de anfitrión que le da `display:block` a fieldset y
    párrafos (la regla del navegador para `hidden` tiene especificidad mínima y
    esa hoja le ganaría si el componente no la blindara);
  · todo texto pasa 4,5:1 (3:1 si es grande) contra su fondo efectivo;
  · el área táctil de día, hora, navegación del mes y acción llega a 44 px
    (mesón municipal y pantallas táctiles);
  · con `prefers-reduced-motion: reduce` la transición computa 0 s, y sin la
    preferencia existe (si no, la comprobación sería vacía);
  · a 390 px y a 320 px el documento NO se desplaza en horizontal (1.4.10).

Sobre `enlaces.html` (`mesUrl`): ← en el día 1 y ↑ en la primera fila no navegan;
RePág va al enlace real del mes anterior. Sobre `sin-nav.html` (ni `mesUrl` ni
`mesEvento`): no hay ‹ › mudos y RePág dice «Esta agenda muestra solo mayo de 2026.».

Y sobre `sin-alpine.html`: sin el script, el día que eligió el servidor sigue
visible con sus radios operables y los demás siguen ocultos. El formulario es un
formulario.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "agenda-horas"
NAVEGADORES = ("chromium", "firefox")
CON_ALPINE = ("claro.html", "oscuro.html", "panel-claro.html", "panel-oscuro.html")
MOVIMIENTOS = ("reduce", "no-preference")
ANCHOS = (("telefono", 390, 780), ("estrecho", 320, 780))

DIA = "2026-05-12"        # martes, el que el servidor deja elegido
DIA_SIGUIENTE = "2026-05-13"

# El oyente de los eventos que el componente emite hacia el anfitrión. Se instala
# ANTES de que corra Alpine para no perderse ninguno.
ESPIA = """
window.__agenda = [];
document.addEventListener('muni-agenda:mes', e => window.__agenda.push(['mes', e.detail.mes]));
document.addEventListener('muni-agenda:dia', e => window.__agenda.push(['dia', e.detail.dia]));
document.addEventListener('muni-agenda:franja', e => window.__agenda.push(['franja', e.detail.franja]));
document.addEventListener('muni-agenda:accion', e => window.__agenda.push(['accion', e.detail]));
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
  const caja = a.getBoundingClientRect();
  const offset = parseFloat(c.outlineOffset) || 0;
  // Con outline-offset positivo el trazo NO se pinta sobre el propio elemento
  // sino sobre lo que hay detrás: el fondo efectivo es el del padre.
  const debajo = offset > 0 && a.parentElement ? fondoDe(a.parentElement) : fondoDe(a);
  return {
    etiqueta: a.tagName,
    tipo: a.getAttribute('type'),
    celda: a.dataset ? (a.dataset.celda || null) : null,
    panel: a.dataset ? (a.dataset.panel || null) : null,
    valor: a.getAttribute('value'),
    marcado: a.tagName === 'INPUT' ? a.checked : null,
    etiquetaAria: a.getAttribute('aria-label'),
    outline: { ancho: c.outlineWidth, estilo: c.outlineStyle, color: c.outlineColor, offset },
    fondo: debajo,
    ancho: caja.width,
    alto: caja.height,
  };
}
"""

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
    const c = getComputedStyle(el);
    if (c.display === 'none' || c.visibility === 'hidden') return false;
    const r = el.getBoundingClientRect();
    return r.width > 1 && r.height > 1;
  };

  // Todo nodo de texto que de verdad se pinta, con su fondo efectivo.
  const textos = [];
  const raiz = document.querySelector('.muni-ag');
  const paseo = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT);
  let n;
  while ((n = paseo.nextNode())) {
    const t = (n.textContent || '').trim();
    if (!t) continue;
    const el = n.parentElement;
    if (!el || !visible(el)) continue;
    const c = getComputedStyle(el);
    textos.push({
      texto: t.slice(0, 40),
      clase: el.getAttribute('class'),
      color: c.color,
      fondo: fondoDe(el),
      tamano: parseFloat(c.fontSize),
      peso: parseInt(c.fontWeight, 10) || 400,
    });
  }

  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };

  return {
    rejilla: !!raiz.querySelector('table[role="grid"]'),
    columnas: raiz.querySelectorAll('[role="columnheader"]').length,
    dias: raiz.querySelectorAll('[data-celda]').length,
    paradas: [...raiz.querySelectorAll('[data-celda]')].filter(b => b.getAttribute('tabindex') === '0').length,
    panelesVisibles: [...raiz.querySelectorAll('[data-panel]')].filter(visible).map(p => p.dataset.panel),
    panelesOcultos: [...raiz.querySelectorAll('[data-panel]')]
      .filter(p => !visible(p)).map(p => getComputedStyle(p).display),
    radiosVisibles: [...raiz.querySelectorAll('[data-franja]')].filter(r => visible(r.closest('label'))).length,
    textos,
    cajaDia: caja(raiz.querySelector('[data-celda]')),
    cajaNav: caja(raiz.querySelector('.muni-ag__nav')),
    cajaAccion: caja(raiz.querySelector('.muni-ag__accion')),
    cajaFranja: caja(raiz.querySelector('[data-panel]:not([hidden]) .muni-ag__franja')),
    transicionDia: getComputedStyle(raiz.querySelector('[data-celda]')).transitionDuration,
    desbordaX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    desborde: document.documentElement.scrollWidth - document.documentElement.clientWidth,
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
    """4,5:1, o 3:1 si el texto es grande (≥24px, o ≥18.66px en negrita)."""
    return 3.0 if (tamano >= 24 or (tamano >= 18.66 and peso >= 700)) else 4.5


def al_dia(page) -> dict | None:
    """Tabula hasta caer en la rejilla. Se siembra el foco en el primer control del
    componente y a partir de ahí manda el TECLADO: `element.focus()` solo no
    dispara `:focus-visible` en todos los motores, pero el Tab siguiente sí, y así
    el recorrido arranca siempre en el mismo sitio. (`body.focus()` no sirve: el
    body no es enfocable y el Tab seguía desde donde lo dejó la prueba anterior.)"""
    page.evaluate("() => { const n = document.querySelector('.muni-ag__nav'); if (n) n.focus(); }")
    for _ in range(12):
        page.keyboard.press("Tab")
        foco = page.evaluate(FOCO)
        if foco and foco.get("celda"):
            return foco
    return None


def verificar_teclado(page, donde: str, fallos: list[str]) -> int:
    """Las ocho teclas de la ficha, una por una, mirando dónde queda el foco.

    Mayo de 2026 empieza en viernes: la primera fila es [–,–,–,–,1,2,3]. Desde el
    martes 12 se recorre la semana y se sube hasta el BORDE del mes, donde las
    flechas tienen que quedarse quietas: salir del mes es pedirle otro al servidor
    (recargar, con `mesUrl`), y eso no puede ocurrir por pasarse de una flecha."""
    foco = al_dia(page)
    if foco is None:
        fallos.append(f"{donde}: tabulando desde el principio nunca se llega a la rejilla del mes.")
        return 0
    if foco["celda"] != DIA:
        fallos.append(f"{donde}: la única parada de la rejilla es {foco['celda']} y tenía que ser el día elegido {DIA}.")

    aciertos = 0
    page.evaluate("() => { window.__agenda = []; }")

    def mueve(tecla: str, esperado: str, que: str) -> bool:
        page.keyboard.press(tecla)
        f = page.evaluate(FOCO)
        actual = (f or {}).get("celda")
        paradas = page.evaluate("() => [...document.querySelectorAll('[data-celda]')].filter(b => b.getAttribute('tabindex') === '0').length")
        if paradas != 1:
            fallos.append(f"{donde}: tras {tecla} hay {paradas} paradas de tabulación en el mes; tiene que haber una.")
        if actual != esperado:
            fallos.append(f"{donde}: {tecla} ({que}) dejó el foco en {actual} y tenía que ir a {esperado}.")
            return False
        return True

    flechas = [
        mueve("ArrowRight", "2026-05-13", "un día adelante"),
        mueve("ArrowLeft", "2026-05-12", "un día atrás"),
        mueve("ArrowDown", "2026-05-19", "una semana adelante"),
        mueve("ArrowUp", "2026-05-12", "una semana atrás"),
    ]
    aciertos += sum(flechas)
    semana = [mueve("End", "2026-05-17", "al fin de la semana"), mueve("Home", "2026-05-11", "al principio de la semana")]
    aciertos += sum(semana)

    # Al borde: del lunes 11 se sube al 4, y arriba del 4 no hay día.
    borde = [
        mueve("ArrowUp", "2026-05-04", "una semana atrás"),
        mueve("ArrowUp", "2026-05-04", "en el borde superior se queda"),
        mueve("End", "2026-05-10", "al fin de la semana"),
        mueve("ArrowUp", "2026-05-03", "una semana atrás"),
        mueve("Home", "2026-05-01", "al principio de la primera semana"),
        mueve("ArrowLeft", "2026-05-01", "en el día 1 se queda"),
    ]
    pedidos = [m for (t, m) in page.evaluate("() => window.__agenda") if t == "mes"]
    if pedidos:
        fallos.append(f"{donde}: una flecha en el borde del mes pidió otro mes ({pedidos}); solo RePág/AvPág y ‹ › lo piden.")
    if not all(borde):
        fallos.append(f"{donde}: el recorrido hasta el borde del mes no llegó donde tenía que llegar.")

    # RePág pide el mes al anfitrión y la rejilla queda OCUPADA hasta que llegue:
    # aria-busy, «Cargando abril de 2026…» en texto y ‹ › sin repetir la petición.
    page.keyboard.press("PageUp")
    page.keyboard.press("PageDown")
    estado = page.evaluate("""() => {
      const r = document.querySelector('.muni-ag');
      const c = r.querySelector('.muni-ag__cargando');
      return {
        pedidos: window.__agenda.filter(e => e[0] === 'mes').map(e => e[1]),
        busy: r.querySelector('table[role=grid]').getAttribute('aria-busy'),
        cargando: c && getComputedStyle(c).display !== 'none' ? c.textContent.trim() : null,
        nav: [...r.querySelectorAll('.muni-ag__nav')].map(n => n.getAttribute('aria-disabled')),
        campoMes: r.querySelector('input[name$="_mes"]').value,
        celda: document.activeElement && document.activeElement.dataset.celda,
      };
    }""")
    if estado["pedidos"] == ["2026-04"] and estado["busy"] == "true" and estado["cargando"] == "Cargando abril de 2026…" \
            and estado["nav"] == ["true", "true"] and estado["campoMes"] == "2026-05" and estado["celda"] == "2026-05-01":
        aciertos += 1
    else:
        fallos.append(
            f"{donde}: RePág tenía que pedir UNA vez 2026-04, dejar la rejilla aria-busy con «Cargando abril de 2026…», "
            f"‹ › en aria-disabled, el campo _mes en 2026-05 (el que se ve) y el foco en el día 1; quedó {estado}."
        )

    # Sin respuesta del anfitrión en 15 s, la carga se rinde con un error en texto.
    page.clock.run_for(16000)
    rendida = page.evaluate("""() => {
      const r = document.querySelector('.muni-ag');
      const errores = [...r.querySelectorAll('.muni-ag__error')].filter(e => getComputedStyle(e).display !== 'none').map(e => e.textContent.trim());
      return { busy: r.querySelector('table[role=grid]').getAttribute('aria-busy'), errores,
               nav: [...r.querySelectorAll('.muni-ag__nav')].map(n => n.getAttribute('aria-disabled')) };
    }""")
    if not any("No llegó abril de 2026. Vuelve a intentarlo." in e for e in rendida["errores"]) or rendida["busy"] is not None \
            or rendida["nav"] != [None, None]:
        fallos.append(f"{donde}: sin respuesta del anfitrión la carga no se rindió con un error en texto: {rendida}.")

    # AvPág, en una carga limpia de la página.
    page.reload()
    page.wait_for_function("() => window.Alpine && window.Alpine.version")
    if al_dia(page) is not None:
        page.evaluate("() => { window.__agenda = []; }")
        page.keyboard.press("PageDown")
        pedidos = [m for (t, m) in page.evaluate("() => window.__agenda") if t == "mes"]
        if pedidos == ["2026-06"]:
            aciertos += 1
        else:
            fallos.append(f"{donde}: AvPág pidió {pedidos} y tenía que pedir ['2026-06'].")

    return aciertos


def recargar(page) -> None:
    page.reload()
    page.wait_for_function("() => window.Alpine && window.Alpine.version")
    page.evaluate("() => { window.__agenda = []; }")


def ir_a_celda(page, destino: str) -> None:
    """Camina con las flechas (nunca con element.focus) hasta el día pedido."""
    for _ in range(40):
        actual = (page.evaluate(FOCO) or {}).get("celda") or ""
        if actual == destino or not actual:
            return
        if abs(int(actual[-2:]) - int(destino[-2:])) >= 7:
            page.keyboard.press("ArrowDown" if actual < destino else "ArrowUp")
        else:
            page.keyboard.press("ArrowRight" if actual < destino else "ArrowLeft")


FORMULARIO = """() => {
  const f = document.getElementById('banco-form');
  const d = new FormData(f);
  const acc = f.querySelector('.muni-ag__accion');
  return {
    hora: d.getAll('hora_examen'),
    dia: d.get('hora_examen_dia'),
    marcados: [...f.querySelectorAll('[data-franja]')].filter(r => r.checked).map(r => r.value),
    accionDeshabilitada: acc.getAttribute('aria-disabled'),
    anuncio: f.querySelector('.muni-ag__sr[aria-live]').textContent,
    acciones: window.__agenda.filter(e => e[0] === 'accion').map(e => e[1]),
    paneles: [...f.querySelectorAll('[data-panel]')].filter(p => getComputedStyle(p).display !== 'none').map(p => p.dataset.panel),
  };
}"""


def verificar_cambio_de_dia(page, donde: str, fallos: list[str]) -> bool:
    """El bloqueante del revisor: la 09:00 del 12, Enter sobre el 13 y «Reservar»
    salía con {dia: 13, franja: 12T09:00}. La hora elegida es SIEMPRE del día
    elegido: cambiar de día la suelta, y lo que envía el formulario lo confirma."""
    recargar(page)
    if al_dia(page) is None:
        fallos.append(f"{donde}: no se llega a la rejilla para probar el cambio de día.")
        return False

    page.keyboard.press("Enter")
    page.wait_for_function("() => document.activeElement && document.activeElement.type === 'radio'", timeout=2000)
    page.keyboard.press("Space")
    antes = page.evaluate(FORMULARIO)
    if antes["hora"] != [f"{DIA}T09:00"]:
        fallos.append(f"{donde}: Espacio sobre la 09:00 del 12 no la dejó en el formulario: {antes}.")
        return False

    # Shift+Tab vuelve a la rejilla, que tiene UNA parada: el día 12.
    for _ in range(4):
        page.keyboard.press("Shift+Tab")
        if (page.evaluate(FOCO) or {}).get("celda"):
            break
    ir_a_celda(page, DIA_SIGUIENTE)
    page.keyboard.press("Enter")
    page.wait_for_function("() => document.activeElement && document.activeElement.type === 'radio'", timeout=2000)

    despues = page.evaluate(FORMULARIO)
    ok = True
    if despues["hora"] or despues["marcados"]:
        fallos.append(f"{donde}: al cambiar al 13 sigue elegida la hora {despues['hora'] or despues['marcados']} del día anterior.")
        ok = False
    if despues["dia"] != DIA_SIGUIENTE or despues["paneles"] != [DIA_SIGUIENTE]:
        fallos.append(f"{donde}: tras Enter sobre el 13 el formulario dice día {despues['dia']} y se ven {despues['paneles']}.")
        ok = False
    if despues["accionDeshabilitada"] != "true":
        fallos.append(f"{donde}: sin hora del 13 elegida, «Reservar la hora» sigue habilitado.")
        ok = False

    # «Reservar» sin hora del día: no sale evento y se dice por qué.
    page.focus(".muni-ag__accion")
    page.keyboard.press("Enter")
    page.wait_for_timeout(50)
    sin = page.evaluate(FORMULARIO)
    if sin["acciones"]:
        fallos.append(f"{donde}: «Reservar» sin hora del día elegido emitió {sin['acciones']}.")
        ok = False
    if "Elige una hora libre del día elegido" not in sin["anuncio"]:
        fallos.append(f"{donde}: «Reservar» sin hora no dijo por qué (anuncio: «{sin['anuncio']}»).")
        ok = False

    # Con la 09:30 del 13, el ciclo se cierra coherente.
    page.focus(f'[data-franja][value="{DIA_SIGUIENTE}T09:30"]')
    page.keyboard.press("Space")
    page.focus(".muni-ag__accion")
    page.keyboard.press("Enter")
    page.wait_for_timeout(50)
    fin = page.evaluate(FORMULARIO)
    esperado = {"operacion": "reservar", "dia": DIA_SIGUIENTE, "franja": f"{DIA_SIGUIENTE}T09:30"}
    if fin["acciones"] != [esperado] or fin["hora"] != [f"{DIA_SIGUIENTE}T09:30"] or fin["accionDeshabilitada"] is not None:
        fallos.append(f"{donde}: con la 09:30 del 13 la acción tenía que salir {esperado} con el formulario coherente; salió {fin}.")
        ok = False

    return ok


def verificar_dia_sin_horas(page, donde: str, fallos: list[str]) -> bool:
    """El 20 no tiene franjas: Enter lo elige, el foco NO se va a un párrafo del que
    Esc no saca, y el aviso dice que ESE día no tiene horas (no «elige un día»).
    El 14 está completo: el foco va a su panel y Esc vuelve al día."""
    recargar(page)
    if al_dia(page) is None:
        return False
    ir_a_celda(page, "2026-05-20")
    page.keyboard.press("Enter")
    page.wait_for_timeout(150)
    r = page.evaluate("""() => {
      const v = document.querySelector('.muni-ag__vacio');
      return { celda: document.activeElement.dataset.celda || document.activeElement.tagName,
               aviso: getComputedStyle(v).display !== 'none' ? v.textContent.trim() : null,
               anuncio: document.querySelector('.muni-ag__sr[aria-live]').textContent,
               paneles: [...document.querySelectorAll('[data-panel]')].filter(p => getComputedStyle(p).display !== 'none').length };
    }""")
    texto = "El miércoles 20 de mayo de 2026 no tiene horas publicadas."
    ok = True
    if r["celda"] != "2026-05-20" or r["aviso"] != texto or r["anuncio"] != texto or r["paneles"] != 0:
        fallos.append(f"{donde}: el día sin horas tenía que dejar el foco en el 20 y decir «{texto}» (visible y anunciado); quedó {r}.")
        ok = False
    page.keyboard.press("Escape")
    if (page.evaluate(FOCO) or {}).get("celda") != "2026-05-20":
        fallos.append(f"{donde}: Esc sobre el día sin horas sacó el foco de la rejilla.")
        ok = False

    ir_a_celda(page, "2026-05-14")
    page.keyboard.press("Enter")
    page.wait_for_timeout(150)
    f = page.evaluate(FOCO)
    if not f or f.get("panel") != "2026-05-14":
        fallos.append(f"{donde}: el día completo (14) tenía que llevar el foco a su panel; quedó en {f and (f.get('celda') or f['etiqueta'])}.")
        ok = False
    page.keyboard.press("Escape")
    if (page.evaluate(FOCO) or {}).get("celda") != "2026-05-14":
        fallos.append(f"{donde}: Esc desde el panel del día completo no volvió al 14.")
        ok = False
    return ok


def verificar_modelo_externo(page, donde: str, fallos: list[str]) -> bool:
    """`wire:model`/`x-model` fija una hora de OTRO día: el día se mueve a esa hora
    (nunca queda una hora marcada en un panel oculto). Se escribe en la propiedad
    que ata `x-modelable`, que es lo que hace Livewire al hidratar."""
    recargar(page)
    page.evaluate("() => { Alpine.$data(document.querySelector('.muni-ag')).franja = '2026-05-13T09:30'; }")
    page.wait_for_timeout(100)
    r = page.evaluate(FORMULARIO)
    if r["hora"] != ["2026-05-13T09:30"] or r["dia"] != DIA_SIGUIENTE or r["paneles"] != [DIA_SIGUIENTE] or r["accionDeshabilitada"] is not None:
        fallos.append(f"{donde}: una hora del 13 fijada desde fuera tenía que mover el día al 13 con el formulario coherente; quedó {r}.")
        return False
    return True

def verificar_salto(page, donde: str, fallos: list[str]) -> bool:
    """Enter elige el día y salta a la primera hora LIBRE; Esc cancela y vuelve."""
    foco = al_dia(page)
    if foco is None:
        fallos.append(f"{donde}: después del recorrido de teclado ya no se puede volver a la rejilla con Tab.")
        return False

    # Se camina con → hasta el 13, que tiene la 09:00 tomada: el recorrido anterior
    # dejó el tabindex itinerante donde lo dejó, y eso es justo lo que se prueba.
    for _ in range(31):
        if (page.evaluate(FOCO) or {}).get("celda") == DIA_SIGUIENTE:
            break
        actual = (page.evaluate(FOCO) or {}).get("celda") or ""
        page.keyboard.press("ArrowRight" if actual < DIA_SIGUIENTE else "ArrowLeft")
    page.keyboard.press("Enter")

    # El salto de foco ocurre en el tick siguiente: hay que esperarlo, no suponerlo.
    try:
        page.wait_for_function(
            "() => document.activeElement && document.activeElement.type === 'radio'",
            timeout=2000,
        )
    except Exception:
        pass

    f = page.evaluate(FOCO)
    if not f or f["etiqueta"] != "INPUT" or f["tipo"] != "radio":
        fallos.append(f"{donde}: al elegir el día el foco quedó en {f and f['etiqueta']}, no en la lista de franjas.")
        return False
    if f["valor"] != f"{DIA_SIGUIENTE}T09:30":
        fallos.append(
            f"{donde}: el foco cayó en la hora {f['valor']}; tenía que caer en la primera LIBRE "
            f"({DIA_SIGUIENTE}T09:30), no en la tomada."
        )
        return False

    visibles = page.evaluate("() => [...document.querySelectorAll('[data-panel]')].filter(p => getComputedStyle(p).display !== 'none').map(p => p.dataset.panel)")
    if visibles != [DIA_SIGUIENTE]:
        fallos.append(f"{donde}: se ven los paneles {visibles} y tenía que verse solo el de {DIA_SIGUIENTE}.")
        return False

    page.keyboard.press("Space")
    f = page.evaluate(FOCO)
    if not f or not f["marcado"]:
        fallos.append(f"{donde}: Espacio no marcó la hora enfocada.")
        return False

    emitidos = [v for (t, v) in page.evaluate("() => window.__agenda") if t == "franja"]
    if emitidos[-1:] != [f"{DIA_SIGUIENTE}T09:30"]:
        fallos.append(f"{donde}: al marcar la hora no salió el evento muni-agenda:franja (salió {emitidos}).")
        return False

    page.keyboard.press("Escape")
    f = page.evaluate(FOCO)
    if not f or f.get("celda") != DIA_SIGUIENTE:
        fallos.append(f"{donde}: Esc dejó el foco en {f and (f.get('celda') or f['etiqueta'])}; tenía que volver al día {DIA_SIGUIENTE}.")
        return False

    marcados = page.evaluate("() => [...document.querySelectorAll('[data-franja]')].filter(r => r.checked).length")
    if marcados != 0:
        fallos.append(f"{donde}: Esc no canceló la elección: quedan {marcados} hora(s) marcada(s).")
        return False

    return True


def verificar_pagina(page, donde: str, fallos: list[str]) -> None:
    d = page.evaluate(SONDA)

    if not d["rejilla"]:
        fallos.append(f"{donde}: el mes no rinde como rejilla (role=\"grid\").")
    if d["columnas"] != 7:
        fallos.append(f"{donde}: la rejilla tiene {d['columnas']} cabeceras de columna y tienen que ser 7.")
    if d["dias"] != 31:
        fallos.append(f"{donde}: mayo de 2026 tiene 31 días y la rejilla dibuja {d['dias']}.")
    if d["paradas"] != 1:
        fallos.append(f"{donde}: hay {d['paradas']} paradas de tabulación en el mes; el tabindex itinerante deja una.")
    if d["panelesVisibles"] != [DIA]:
        fallos.append(f"{donde}: se ven los paneles {d['panelesVisibles']} y tenía que verse solo el de {DIA}.")
    for display in d["panelesOcultos"]:
        if display != "none":
            fallos.append(f"{donde}: un panel oculto computa display:{display}.")

    # La hoja de un anfitrión que le da display a fieldset y párrafos. Sin el
    # blindaje del componente, la regla del navegador para `hidden` pierde.
    page.add_style_tag(content=".muni-ag fieldset, .muni-ag p { display:block; }")
    ocultos = page.evaluate(
        "() => [...document.querySelectorAll('.muni-ag p[hidden]')].map(p => [p.className, getComputedStyle(p).display]).filter(([, d]) => d !== 'none')"
    )
    for clase, display in ocultos:
        fallos.append(f"{donde}: con la hoja del anfitrión el párrafo oculto «{clase}» computa display:{display}.")
    visibles = page.evaluate(
        "() => [...document.querySelectorAll('.muni-ag [data-panel]')].filter(p => getComputedStyle(p).display !== 'none').map(p => p.dataset.panel)"
    )
    vacio = page.evaluate("() => { const v = document.querySelector('.muni-ag__vacio'); return v.hidden ? getComputedStyle(v).display : 'visible-a-proposito'; }")
    if visibles != [DIA]:
        fallos.append(
            f"{donde}: con una hoja de anfitrión que da display a los fieldset se ven los paneles {visibles}: "
            "el atributo hidden solo no basta."
        )
    if vacio not in ("none", "visible-a-proposito"):
        fallos.append(f"{donde}: con la hoja del anfitrión el aviso «elige un día» oculto computa display:{vacio}.")
    page.evaluate("() => document.querySelectorAll('style').forEach(s => { if (s.textContent.includes('.muni-ag fieldset, .muni-ag p')) s.remove(); })")

    for nombre, caja, alto in (
        ("el día del mes", d["cajaDia"], 44),
        ("la navegación del mes", d["cajaNav"], 44),
        ("la hora", d["cajaFranja"], 44),
        ("la acción", d["cajaAccion"], 44),
    ):
        if caja["alto"] + 0.5 < alto:
            fallos.append(f"{donde}: {nombre} mide {caja['alto']:.1f} px de alto y el mínimo del mesón es {alto}.")

    for t in d["textos"]:
        r = contraste(t["color"], t["fondo"])
        if r is None:
            continue
        exigido = minimo(t["tamano"], t["peso"])
        if r + 0.005 < exigido:
            fallos.append(
                f"{donde}: «{t['texto']}» ({t['clase']}) da {r:.2f}:1 sobre su fondo y necesita {exigido}:1."
            )


def main() -> int:
    banco = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else BANCO_POR_DEFECTO

    if not banco.is_dir() or not (banco / "claro.html").is_file():
        print(f"No está el banco en {banco}: corre antes la prueba que lo genera.", file=sys.stderr)
        return 2

    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        print("Falta playwright en .venv-a11y (npm run a11y:instalar).", file=sys.stderr)
        return 2

    fallos: list[str] = []

    with sync_playwright() as p:
        for navegador in NAVEGADORES:
            motor = getattr(p, navegador).launch()

            for archivo in CON_ALPINE:
                donde = f"{navegador}/{archivo}"
                ctx = motor.new_context(viewport={"width": 1440, "height": 900})
                ctx.add_init_script(ESPIA)
                page = ctx.new_page()
                page.clock.install()
                page.goto((banco / archivo).as_uri())
                page.wait_for_function("() => window.Alpine && window.Alpine.version")

                verificar_pagina(page, donde, fallos)

                aciertos = verificar_teclado(page, donde, fallos)
                print(f"{donde}: teclado={aciertos}/8")

                salto = verificar_salto(page, donde, fallos)
                print(f"{donde}: salto={'ok' if salto else 'roto'}")

                dia = verificar_cambio_de_dia(page, donde, fallos)
                print(f"{donde}: cambio-de-dia={'ok' if dia else 'roto'}")

                vacio = verificar_dia_sin_horas(page, donde, fallos)
                print(f"{donde}: dia-sin-horas={'ok' if vacio else 'roto'}")

                modelo = verificar_modelo_externo(page, donde, fallos)
                print(f"{donde}: modelo-externo={'ok' if modelo else 'roto'}")

                # El foco puesto por teclado tiene que verse. Se mide DESPUÉS del
                # recorrido, con el foco donde lo dejó el teclado.
                foco = al_dia(page)
                if foco is None:
                    fallos.append(f"{donde}: no se pudo poner el foco en la rejilla para medir el outline.")
                else:
                    ancho = float(re.sub(r"[^\d.]", "", foco["outline"]["ancho"]) or 0)
                    if foco["outline"]["estilo"] != "solid" or ancho < 3:
                        fallos.append(
                            f"{donde}: el foco del día computa outline {ancho} px {foco['outline']['estilo']}; "
                            "tienen que ser 3 px sólidos (la box-shadow del anillo se pierde dentro de Filament)."
                        )
                    r = contraste(foco["outline"]["color"], foco["fondo"])
                    if r is not None and r + 0.005 < 3.0:
                        fallos.append(f"{donde}: el outline del foco da {r:.2f}:1 contra su fondo y necesita 3:1.")

                ctx.close()

                # Movimiento reducido y anchos angostos, en contextos propios.
                for movimiento in MOVIMIENTOS:
                    ctx = motor.new_context(viewport={"width": 1440, "height": 900}, reduced_motion=movimiento)
                    page = ctx.new_page()
                    page.goto((banco / archivo).as_uri())
                    page.wait_for_function("() => window.Alpine && window.Alpine.version")
                    dur = page.evaluate("() => getComputedStyle(document.querySelector('[data-celda]')).transitionDuration")
                    segundos = max(float(x) for x in re.findall(r"([\d.]+)s", dur) or ["0"])
                    if movimiento == "reduce" and segundos > 0:
                        fallos.append(f"{donde}: con prefers-reduced-motion el día sigue animando {dur}.")
                    if movimiento == "no-preference" and segundos == 0:
                        fallos.append(f"{donde}: sin la preferencia no hay transición: la comprobación de arriba sería vacía.")
                    ctx.close()

                for nombre, ancho, alto in ANCHOS:
                    ctx = motor.new_context(viewport={"width": ancho, "height": alto})
                    page = ctx.new_page()
                    page.goto((banco / archivo).as_uri())
                    page.wait_for_function("() => window.Alpine && window.Alpine.version")
                    d = page.evaluate(SONDA)
                    if d["desbordaX"]:
                        fallos.append(f"{donde} a {ancho} px ({nombre}): el documento se desplaza {d['desborde']} px en horizontal (1.4.10).")
                    ctx.close()

            # Con `mesUrl`: las flechas en el borde NO navegan; RePág sí (es el gesto
            # explícito) y va al enlace real del mes anterior.
            ctx = motor.new_context(viewport={"width": 1440, "height": 900})
            ctx.add_init_script(ESPIA)
            page = ctx.new_page()
            page.goto((banco / "enlaces.html").as_uri())
            page.wait_for_function("() => window.Alpine && window.Alpine.version")
            donde = f"{navegador}/enlaces.html"
            enlaces_ok = False
            if al_dia(page) is not None:
                page.keyboard.press("Home")
                ir_a_celda(page, "2026-05-01")
                page.keyboard.press("ArrowLeft")
                page.keyboard.press("ArrowUp")
                page.wait_for_timeout(300)
                if "mes=" in page.url:
                    fallos.append(f"{donde}: una flecha en el borde del mes recargó la página ({page.url}).")
                elif (page.evaluate(FOCO) or {}).get("celda") != "2026-05-01":
                    fallos.append(f"{donde}: la flecha en el borde sacó el foco del día 1.")
                else:
                    page.keyboard.press("PageUp")
                    try:
                        page.wait_for_url("**/enlaces.html?mes=2026-04", timeout=3000)
                        enlaces_ok = True
                    except Exception:
                        fallos.append(f"{donde}: RePág con `mesUrl` no fue al enlace del mes anterior (url {page.url}).")
            print(f"{donde}: enlaces={'ok' if enlaces_ok else 'roto'}")
            ctx.close()

            # Sin `mesUrl` ni `mesEvento`: ni ‹ › mudos ni RePág mudo.
            ctx = motor.new_context(viewport={"width": 1440, "height": 900})
            ctx.add_init_script(ESPIA)
            page = ctx.new_page()
            page.goto((banco / "sin-nav.html").as_uri())
            page.wait_for_function("() => window.Alpine && window.Alpine.version")
            donde = f"{navegador}/sin-nav.html"
            sin_nav_ok = False
            navs = page.evaluate("() => document.querySelectorAll('.muni-ag__nav').length")
            if navs:
                fallos.append(f"{donde}: sin mesUrl ni mesEvento se dibujaron {navs} botones de mes que no hacen nada.")
            elif al_dia(page) is not None:
                page.keyboard.press("PageUp")
                page.wait_for_timeout(100)
                r = page.evaluate("() => ({ anuncio: document.querySelector('.muni-ag__sr[aria-live]').textContent, celda: document.activeElement.dataset.celda, pedidos: window.__agenda.filter(e => e[0] === 'mes').length })")
                if r["anuncio"] == "Esta agenda muestra solo mayo de 2026." and r["celda"] == DIA and r["pedidos"] == 0:
                    sin_nav_ok = True
                else:
                    fallos.append(f"{donde}: RePág sin forma de cambiar de mes tenía que decirlo y dejar el foco en {DIA}; quedó {r}.")
            print(f"{donde}: sin-nav={'ok' if sin_nav_ok else 'roto'}")
            ctx.close()

            # Sin Alpine el formulario sigue siendo un formulario.
            ctx = motor.new_context(viewport={"width": 1440, "height": 900})
            page = ctx.new_page()
            page.goto((banco / "sin-alpine.html").as_uri())
            d = page.evaluate(SONDA)
            donde = f"{navegador}/sin-alpine.html"
            if d["panelesVisibles"] != [DIA]:
                fallos.append(f"{donde}: sin JS se ven los paneles {d['panelesVisibles']}; el servidor ya eligió {DIA}.")
            if d["radiosVisibles"] < 1:
                fallos.append(f"{donde}: sin JS no queda ninguna hora operable.")
            marcado = page.evaluate(
                "() => { const r = document.querySelector('[data-franja]:not([disabled])'); r.click(); return r.checked; }"
            )
            if not marcado:
                fallos.append(f"{donde}: sin JS la hora libre no se puede marcar.")
            print(f"{donde}: sin-alpine=ok")
            ctx.close()

            motor.close()

    if fallos:
        print("\n".join(f"FALLA · {f}" for f in fallos), file=sys.stderr)
        return 1

    print("La agenda de horas pasa en Chromium y Firefox.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
