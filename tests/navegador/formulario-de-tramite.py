#!/usr/bin/env python3
"""Verificación en navegador de `<x-muni::formulario-tramite>` / `<x-muni::formulario-seccion>`.

    .venv-a11y/bin/python tests/navegador/formulario-de-tramite.py [build/formulario-tramite]

La corre `tests/FormularioDeTramiteTest.php` después de generar el banco (y se
salta si no está el entorno de la reja, que en CI no se instala). Las pruebas de
Pest leen el TEXTO del fuente —que el aside no traiga un `<a>`, que el CSS diga
`min-height:44px`, que exista la comparación antes de reescribir el recuento— y
eso ya engañó una vez en `stat`: la regla pasó el test de texto y en el navegador
el defecto seguía vivo. Acá se mide, en Chromium Y Firefox:

  · EL RECORRIDO REAL DE TAB. La ficha exige que el resumen lateral «no robe
    tabulaciones»: se tabula el formulario entero y se cuenta cuántas paradas
    caen dentro del <aside>. Tienen que ser CERO, y la última parada antes del
    botón de enviar tiene que ser un campo, no un texto del resumen.
  · EL RECUENTO VIVO. Se escribe en el campo atado a un requisito y se comprueba
    que (1) el chip de ese requisito pasa de «Falta» a «Listo» y (2) el número
    del resumen baja. Es la mejora progresiva de la ficha, y ninguna prueba de
    Blade puede verla porque solo ocurre cuando Alpine corre.
  · Y EL SENTIDO INVERSO, sobre un requisito CON `campo` que NACE cumplido
    porque su control viene precargado: al vaciarlo tiene que quedar SOLO en
    «Falta», con la marca cuadrada y sin tachado. Se mide la forma y el tachado
    COMPUTADOS: con el `:class` en forma de cadena la clase que puso el servidor
    no se retiraba, el <li> quedaba con `es-listo es-falta` a la vez y el color
    contradecía a la palabra (DESIGN §10). Medir la clase, o solo el sentido
    directo, daba por bueno el defecto.
  · QUE EL RECUENTO NO HABLE DE MÁS. Con un MutationObserver se cuenta cuántas
    veces muta la región viva mientras se teclea dentro de un campo que NO está
    atado a ningún requisito: tiene que ser 0. Reescribir la misma cadena muta el
    DOM y el lector de pantalla vuelve a hablar tras cada tecla.
  · EL FOCO DEL RESUMEN DE ERRORES en la página `errores.html`: al cargar tiene
    que estar en el resumen (WCAG 2.2 AA 3.3.1 y 2.4.3), con outline de 3 px y
    3:1 contra su fondo, y su primer enlace tiene que llegar al campo de verdad.
  · SIN ALPINE el estado del servidor sigue a la vista y el botón de enviar sigue
    siendo un submit real: el trámite no depende de JS para poder ingresarse.
  · TODO TEXTO pasa 4,5:1 (3:1 si es grande) contra su fondo efectivo, en las
    CUATRO páginas completas: claro, oscuro y las dos del panel, que cargan
    únicamente `muni-ui-filament.css` (DESIGN §7) y son donde una clase declarada
    fuera del `@once` se quedaría sin estilo. Se mide en particular el par
    «Falta»/«Listo» sobre la superficie de la columna lateral, que es color de
    estado sobre un fondo que no es el suyo.
  · LAS DOS COLUMNAS: la rejilla da 2 pistas en escritorio y 1 a 390 px, y el
    <fieldset> no desborda el documento en horizontal (1.4.10).
  · CADA CAMPO OBLIGATORIO DICE «OBLIGATORIO» EN TEXTO. Se calcula el nombre
    accesible de cada control `required` como lo calcula el navegador —el texto
    de su <label> con los subárboles aria-hidden fuera— y tiene que contener la
    palabra. El asterisco no la cumple: es un glifo que el lector de pantalla lee
    «asterisco», o no lee, y quien no conoce la convención no sabe qué significa.
  · EL ÁREA TÁCTIL de enviar y cancelar llega a 44 px (mesón y vecino mayor).
  · con `prefers-reduced-motion: reduce` la transición computa 0 s, y sin la
    preferencia existe (si no, la comprobación sería vacía).

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "formulario-tramite"
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

  const form = document.querySelector('form.muni-ftr');
  const aside = document.querySelector('.muni-ftr__lateral');
  const enviar = document.querySelector('.muni-ftr__enviar');
  const cancelar = document.querySelector('.muni-ftr__cancelar');

  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height, arriba: r.top }; };

  // Solo nodos HOJA con texto propio: medir un contenedor da el color heredado
  // de un texto que no es suyo.
  const textos = [...(form ? form.querySelectorAll('*') : [])]
    .filter(el => {
      if (['STYLE', 'SCRIPT', 'SVG', 'PATH', 'RECT', 'CIRCLE'].includes(el.tagName)) return false;
      if (el.closest('svg')) return false;
      if (el.getAttribute('aria-hidden') === 'true') return false;
      const r = el.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) return false;
      return [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1);
    })
    .map(leerTexto);

  const grid = document.querySelector('.muni-fsec__grid[data-muni-cols="2"]');

  // El NOMBRE ACCESIBLE de cada control obligatorio, calculado como lo calcula el
  // navegador para este marcado: el texto de su <label>, sin los subárboles con
  // aria-hidden (que no existen en el árbol de accesibilidad). Es lo que oye quien
  // usa un lector de pantalla, y por eso el asterisco no cuenta.
  const obligatorios = [...(form ? form.querySelectorAll('input[required],select[required],textarea[required]') : [])]
    .map(el => {
      const lab = el.id ? document.querySelector('label[for="' + el.id + '"]') : null;
      let nombre = '';
      if (lab) {
        const copia = lab.cloneNode(true);
        copia.querySelectorAll('[aria-hidden="true"]').forEach(n => n.remove());
        nombre = (copia.textContent || '').replace(/\s+/g, ' ').trim();
      }
      return { campo: el.getAttribute('name'), nombre };
    });

  return {
    hayForm: !!form,
    metodo: form ? form.getAttribute('method') : null,
    aside: aside ? { ...caja(aside), posicion: getComputedStyle(aside).position } : null,
    resumen: aside ? (aside.querySelector('.muni-ftr__req-resumen').textContent || '').trim() : null,
    chips: [...document.querySelectorAll('.muni-ftr__req')].map(li => {
      const marca = li.querySelector('.muni-ftr__req-marca');
      const que = li.querySelector('.muni-ftr__req-que');
      const cm = getComputedStyle(marca);
      return {
        estado: (li.querySelector('.muni-ftr__req-estado').textContent || '').trim(),
        clase: li.className,
        que: (que.textContent || '').trim().slice(0, 40),
        // La FORMA y el TACHADO, no la clase: es lo que ve el funcionario. Con el
        // `:class` de cadena la clase del servidor no se retiraba y el <li> quedaba
        // con las dos, así que la marca seguia redonda y verde mientras la palabra
        // decia «Falta».
        radio: parseFloat(cm.borderTopLeftRadius) || 0,
        colorMarca: cm.color,
        tachado: getComputedStyle(que).textDecorationLine,
      };
    }),
    enviar: enviar ? { ...caja(enviar), tipo: enviar.getAttribute('type'), desactivado: enviar.disabled,
                       transicion: getComputedStyle(enviar).transitionDuration } : null,
    cancelar: cancelar ? caja(cancelar) : null,
    columnas: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length : null,
    fieldsets: document.querySelectorAll('form.muni-ftr fieldset').length,
    legends: document.querySelectorAll('form.muni-ftr fieldset > legend').length,
    resumenErrores: !!document.querySelector('.muni-errsum'),
    obligatorios,
    textos,
    alpine: !!(window.Alpine && window.Alpine.version),
    desborde: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    anchoDoc: document.documentElement.clientWidth,
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
  const r = a.getBoundingClientRect();
  // El outline se dibuja con `outline-offset` POSITIVO: cae FUERA del control, sobre el
  // fondo de lo que hay detrás. Medirlo contra el fondo del propio botón daba 1:1 —el
  // anillo es del color del acento, igual que el relleno— y ese 1:1 no existe en pantalla.
  const offset = parseFloat(c.outlineOffset) || 0;
  return {
    etiqueta: a.tagName,
    opacidad: parseFloat(c.opacity),
    fondoDetras: fondoDe(offset > 0 && a.parentElement ? a.parentElement : a),
    id: a.id || null,
    clase: a.getAttribute('class') || '',
    nombre: a.getAttribute('name') || null,
    texto: (a.textContent || '').trim().slice(0, 40),
    enAside: !!a.closest('.muni-ftr__lateral'),
    enForm: !!a.closest('form.muni-ftr'),
    esResumen: !!(a.classList && a.classList.contains('muni-errsum')),
    outline: { ancho: c.outlineWidth, estilo: c.outlineStyle, color: c.outlineColor },
    fondo: fondoDe(a),
    ancho: r.width,
    alto: r.height,
  };
}
"""

# Cuenta las mutaciones de la región viva del recuento. Se instala ANTES de
# teclear: si el componente reescribiera la misma cadena, el contador sube y el
# lector de pantalla estaría hablando en cada pulsación.
OBSERVADOR = r"""
() => {
  const p = document.querySelector('.muni-ftr__req-resumen');
  if (!p) return false;
  window.__mutaciones = 0;
  window.__obs = new MutationObserver(() => { window.__mutaciones++; });
  window.__obs.observe(p, { childList: true, characterData: true, subtree: true });
  return true;
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
    if not d["hayForm"]:
        fallos.append(f"{donde}: no hay <form class=\"muni-ftr\"> en la página.")
        return
    if d["metodo"] != "post":
        fallos.append(f"{donde}: el formulario envía por {d['metodo']!r} y no por POST.")
    if d["fieldsets"] != 3 or d["legends"] != 3:
        fallos.append(
            f"{donde}: se esperaban 3 secciones con <legend> y hay {d['fieldsets']} fieldset(s) "
            f"con {d['legends']} legend(s). Un <fieldset> sin <legend> se anuncia con la cadena vacía."
        )
    if not d["enviar"]:
        fallos.append(f"{donde}: no hay botón de envío.")
        return
    if d["enviar"]["tipo"] != "submit":
        fallos.append(f"{donde}: el botón de enviar no es type=\"submit\" ({d['enviar']['tipo']!r}).")
    if d["enviar"]["desactivado"]:
        fallos.append(f"{donde}: el botón de enviar nace deshabilitado; quién puede ingresar lo decide el servidor.")
    for nombre in ("enviar", "cancelar"):
        control = d[nombre]
        if control and control["alto"] < 44:
            fallos.append(
                f"{donde}: «{nombre}» mide {control['alto']:.0f}px de alto; el mesón y el vecino mayor piden 44."
            )


def verificar_obligatorios(d: dict, donde: str, fallos: list[str]) -> str:
    """Cada campo obligatorio dice «obligatorio» en TEXTO, no solo con un asterisco.

    Es una «tecla obligatoria» de la ficha, y el asterisco no la cumple: es un glifo
    decorativo que un lector de pantalla lee «asterisco» —o no lee—, y quien no conoce
    la convención no sabe qué significa. Se mide el NOMBRE ACCESIBLE, con los
    subárboles aria-hidden fuera: si el asterisco fuera el único indicador, el nombre
    quedaría en «RUT del titular» a secas y esto falla.
    """
    campos = d.get("obligatorios") or []

    if not campos:
        fallos.append(f"{donde}: no hay un solo control `required` en el banco; la comprobación sería vacía.")
        return "obl=0"

    malos = [c for c in campos if "obligatorio" not in (c["nombre"] or "")]

    for c in malos:
        fallos.append(
            f"{donde}: el campo «{c['campo']}» obligatorio se anuncia como {c['nombre']!r}: la palabra «obligatorio» "
            "no está en su nombre accesible. El asterisco es un glifo decorativo, no un texto."
        )

    return f"obl={len(campos) - len(malos)}/{len(campos)}"


def verificar_columnas(d: dict, donde: str, ancho_nombre: str, fallos: list[str]) -> str:
    esperado = 2 if ancho_nombre == "escritorio" else 1
    real = d["columnas"]
    if real != esperado:
        fallos.append(
            f"{donde}: la rejilla de la sección da {real} pista(s) y se esperaban {esperado}. "
            "En un teléfono dos columnas de 160px no son dos columnas."
        )
    if d["desborde"] > 1:
        fallos.append(
            f"{donde}: el documento desborda {d['desborde']}px en horizontal (1.4.10). "
            "Es lo que pasa cuando la rejilla se pone en el propio <fieldset>."
        )
    return f"{real}col"


def verificar_contraste(d: dict, donde: str, fallos: list[str]) -> float:
    peor = 99.0
    for t in d["textos"]:
        r = contraste(t["color"], t["fondo"])
        if r is None:
            continue
        peor = min(peor, r)
        exigido = minimo(t["tamano"], t["peso"])
        if r < exigido:
            fallos.append(
                f"{donde}: «{t['texto']}» ({t['clase'] or t['tamano']}) da {r:.2f}:1 "
                f"y necesita {exigido}:1 — {t['color']} sobre {t['fondo']}."
            )
    return peor


def verificar_movimiento(d: dict, donde: str, movimiento: str, fallos: list[str]) -> str:
    valor = (d["enviar"] or {}).get("transicion", "")
    vivas = re.search(r"(?<![\d.])(?!0s)[\d.]+m?s", valor or "")
    if movimiento == "reduce" and vivas:
        fallos.append(
            f"{donde}: con movimiento reducido el botón sigue en {valor}; tiene que computar 0 s. "
            "(Dentro del panel el token no alcanza: muni-ui-filament.css no baja --muni-dur.)"
        )
    if movimiento == "no-preference" and not vivas:
        fallos.append(f"{donde}: sin la preferencia no hay transición ninguna ({valor}): la comprobación de «reduce» sería vacía.")
    return valor or "—"


def verificar_teclado(pagina, donde: str, fallos: list[str]) -> str:
    """Tab desde el principio del documento hasta el botón de enviar."""
    pagina.evaluate("() => { document.activeElement && document.activeElement.blur(); window.scrollTo(0, 0); }")

    paradas: list[dict] = []
    en_aside = 0
    llego = False

    for _ in range(40):
        pagina.keyboard.press("Tab")
        foco = pagina.evaluate(FOCO)
        if not foco:
            break
        paradas.append(foco)
        if foco["enAside"]:
            en_aside += 1
        if "muni-ftr__enviar" in foco["clase"]:
            llego = True
            break

    if not llego:
        fallos.append(
            f"{donde}: Tab no llegó al botón de enviar en 40 paradas "
            f"(última: {paradas[-1]['etiqueta'] if paradas else 'ninguna'})."
        )

    if en_aside:
        fallos.append(
            f"{donde}: la columna lateral de requisitos se lleva {en_aside} parada(s) de tabulación. "
            "La ficha lo dice: es TEXTO, no un control — diez paradas entre el último campo y el botón "
            "de enviar son peores que no tener resumen."
        )

    # El foco de cada control del formulario tiene que verse. Se salta el control
    # nativo que `file-dropzone` superpone a su zona con `opacity:0`: el indicador
    # lo dibuja la zona, y ese componente tiene su propia prueba de foco
    # (`tests/ZonaArchivosAccesibleTest.php`). Medirle el outline acá sería medir
    # un elemento invisible a propósito.
    for foco in paradas:
        if not foco["enForm"] or foco["opacidad"] == 0:
            continue
        ancho = float(re.sub(r"[^\d.]", "", foco["outline"]["ancho"]) or 0)
        if ancho < 3 or foco["outline"]["estilo"] in ("none", "hidden"):
            fallos.append(
                f"{donde}: «{foco['nombre'] or foco['texto'] or foco['etiqueta']}» no dibuja outline al enfocarse "
                f"({foco['outline']['estilo']} {foco['outline']['ancho']}). La box-shadow del anillo no cuenta (DESIGN §5)."
            )
        else:
            r = contraste(foco["outline"]["color"], foco["fondoDetras"])
            if r is not None and r < 3.0:
                fallos.append(
                    f"{donde}: el outline de «{foco['nombre'] or foco['texto']}» da {r:.2f}:1 contra el fondo (<3, WCAG 1.4.11)."
                )

    return f"lateral={en_aside}-paradas ({len(paradas)} paradas)"


def verificar_recuento_vivo(pagina, donde: str, fallos: list[str]) -> str:
    """Escribir en un campo atado a un requisito lo marca, y el número baja."""
    antes = pagina.evaluate(SONDA)
    if not antes["alpine"]:
        fallos.append(f"{donde}: Alpine no arrancó; el recuento vivo no se puede medir.")
        return "vivo=0/2"

    resumen_antes = antes["resumen"] or ""
    chip_antes = next((c for c in antes["chips"] if "Giro declarado" in c["que"]), None)

    if chip_antes is None:
        fallos.append(f"{donde}: no está el requisito «Giro declarado» en la columna lateral.")
        return "vivo=0/2"
    if chip_antes["estado"] != "Falta":
        fallos.append(f"{donde}: «Giro declarado» nace en {chip_antes['estado']!r} y el servidor lo dio por pendiente.")

    # Se instala el observador ANTES de teclear en un campo que NO está atado a
    # ningún requisito: el recuento no puede cambiar, así que tampoco puede mutar.
    pagina.evaluate(OBSERVADOR)
    pagina.fill('[name="telefono"]', "+56 9 8765 4321")
    pagina.wait_for_timeout(700)
    mutaciones = pagina.evaluate("() => window.__mutaciones")

    if mutaciones:
        fallos.append(
            f"{donde}: la región viva del recuento mutó {mutaciones} vez(ces) escribiendo en un campo que no cambia "
            "el número. Reescribir la misma cadena hace hablar al lector de pantalla tras cada tecla."
        )

    pagina.fill('[name="giro"]', "Venta de abarrotes y frutas")
    pagina.wait_for_timeout(700)

    despues = pagina.evaluate(SONDA)
    chip_despues = next((c for c in despues["chips"] if "Giro declarado" in c["que"]), None)

    aciertos = 0

    if chip_despues and chip_despues["estado"] == "Listo" and "es-listo" in chip_despues["clase"]:
        aciertos += 1
    else:
        fallos.append(
            f"{donde}: al llenar el campo «giro» el requisito sigue en {chip_despues!r}: "
            "el estado del requisito no se refresca con Alpine."
        )

    cedula = next((c for c in despues["chips"] if "Cédula" in c["que"]), None)
    if not cedula or cedula["estado"] != "Listo":
        fallos.append(
            f"{donde}: el requisito cumplido FUERA del formulario se degradó a {cedula!r} al escribir en otro campo: "
            "la columna lateral está contradiciendo al servidor."
        )

    if despues["resumen"] and despues["resumen"] != resumen_antes and "Faltan 2" in despues["resumen"]:
        aciertos += 1
    else:
        fallos.append(
            f"{donde}: el recuento pasó de {resumen_antes!r} a {despues['resumen']!r}; se esperaba que bajara a "
            "«Faltan 2 requisitos de 5.»."
        )

    return f"vivo={aciertos}/2 mut={mutaciones}"


def verificar_reversion(pagina, donde: str, fallos: list[str]) -> str:
    """Vaciar el control de un requisito que NACIÓ cumplido lo devuelve a «Falta».

    Es el sentido inverso de `verificar_recuento_vivo`, y el que ninguna prueba de
    Blade puede ver. El `:class` de Alpine en forma de CADENA solo retira lo que él
    mismo agregó: la clase que escribió el servidor en el atributo `class` se queda
    para siempre, así que el <li> terminaba con `es-listo es-falta` a la vez —marca
    redonda y verde, texto tachado— mientras la palabra decía «Falta». Se mide la
    FORMA y el TACHADO computados, no el nombre de la clase: medir la clase habría
    dado por bueno el orden de dos reglas del bloque de estilos.
    """
    campo = '[name="nombre"]'
    antes = pagina.evaluate(SONDA)
    chip_antes = next((c for c in antes["chips"] if "Nombre del titular" in c["que"]), None)

    if chip_antes is None:
        fallos.append(f"{donde}: no está el requisito «Nombre del titular» en la columna lateral.")
        return "revertir=?"
    if chip_antes["estado"] != "Listo":
        fallos.append(
            f"{donde}: «Nombre del titular» nace en {chip_antes['estado']!r} con el control precargado; "
            "el servidor lo dio por cumplido y el campo tiene valor."
        )

    pagina.fill(campo, "")
    pagina.wait_for_timeout(700)

    despues = pagina.evaluate(SONDA)
    chip = next((c for c in despues["chips"] if "Nombre del titular" in c["que"]), None)

    if chip is None:
        fallos.append(f"{donde}: desapareció el requisito «Nombre del titular».")
        return "revertir=?"

    if chip["estado"] != "Falta":
        fallos.append(f"{donde}: al vaciar «nombre» el requisito sigue diciendo {chip['estado']!r}.")

    if "es-listo" in chip["clase"]:
        fallos.append(
            f"{donde}: el <li> quedó con class={chip['clase']!r}. El `:class` en forma de cadena no retira la clase "
            "que puso el servidor; la de OBJETO sí."
        )

    # Lo que de verdad ve el funcionario: forma redonda y texto tachado son «Listo».
    if chip["radio"] > 6:
        fallos.append(
            f"{donde}: la palabra dice «Falta» y la marca sigue REDONDA (radio {chip['radio']}px). "
            "El color y la forma no pueden contradecir a la palabra (DESIGN §10)."
        )

    if "line-through" in (chip["tachado"] or ""):
        fallos.append(
            f"{donde}: la palabra dice «Falta» y el texto del requisito sigue TACHADO ({chip['tachado']})."
        )

    if chip["colorMarca"] == chip_antes["colorMarca"]:
        fallos.append(
            f"{donde}: la marca conserva el color de «Listo» ({chip['colorMarca']}) después de volver a «Falta»."
        )

    # Y el sentido directo, sobre el mismo <li>: volver a llenarlo no puede dejar
    # las dos clases tampoco.
    pagina.fill(campo, "Juana Pérez Soto")
    pagina.wait_for_timeout(700)
    vuelta = pagina.evaluate(SONDA)
    chip2 = next((c for c in vuelta["chips"] if "Nombre del titular" in c["que"]), None)

    if not chip2 or "es-falta" in chip2["clase"] or chip2["estado"] != "Listo":
        fallos.append(
            f"{donde}: al volver a llenar «nombre» el requisito quedó en {chip2!r}: el sentido directo también "
            "tiene que retirar la clase contraria, y no depender del orden de las reglas del bloque de estilos."
        )

    return "revertir=ok"


def verificar_errores(pagina, donde: str, fallos: list[str]) -> str:
    """En la página con errores el resumen se lleva el foco y sus enlaces llegan."""
    foco = pagina.evaluate(FOCO)

    if not foco or not foco["esResumen"]:
        fallos.append(
            f"{donde}: al cargar con errores el foco está en {foco!r} y tiene que estar en el resumen. "
            "Quien envía treinta campos puede tener el error tres pantallas más arriba del botón que pulsó."
        )
        return "foco=?"

    ancho = float(re.sub(r"[^\d.]", "", foco["outline"]["ancho"]) or 0)
    if ancho < 3 or foco["outline"]["estilo"] in ("none", "hidden"):
        fallos.append(
            f"{donde}: el resumen recibe el foco pero no lo dibuja ({foco['outline']['estilo']} {foco['outline']['ancho']}): "
            "el foco se fue y no se ve a dónde."
        )
    else:
        r = contraste(foco["outline"]["color"], foco["fondoDetras"])
        if r is not None and r < 3.0:
            fallos.append(f"{donde}: el outline del resumen da {r:.2f}:1 contra su fondo (<3, WCAG 1.4.11).")

    # El primer enlace del resumen tiene que llevar a un campo que EXISTE.
    destino = pagina.evaluate(
        "() => { const a = document.querySelector('.muni-errsum__link');"
        " if (!a) return null;"
        " const id = (a.getAttribute('href') || '').slice(1);"
        " return { id, existe: !!document.getElementById(id) }; }"
    )

    if not destino:
        fallos.append(f"{donde}: el resumen no tiene un solo enlace a un campo (técnica G139).")
        return "foco=resumen"

    if not destino["existe"]:
        fallos.append(
            f"{donde}: el enlace del resumen apunta a #{destino['id']}, que no existe en la página. "
            "Un enlace muerto promete llegar al campo y no llega."
        )
    else:
        pagina.click(".muni-errsum__link")
        pagina.wait_for_timeout(120)
        tras = pagina.evaluate(FOCO)
        if not tras or tras["id"] != destino["id"]:
            fallos.append(f"{donde}: al seguir el enlace del resumen el foco quedó en {tras!r} y no en #{destino['id']}.")

    return "foco=resumen"


def verificar_sin_alpine(pagina, donde: str, fallos: list[str]) -> str:
    d = pagina.evaluate(SONDA)

    if d["alpine"]:
        fallos.append(f"{donde}: la página «sin-alpine» trae Alpine; no mide nada.")
        return "sin-alpine=?"

    if not d["resumen"] or "Faltan 3 requisitos de 5." not in d["resumen"]:
        fallos.append(
            f"{donde}: sin JS el recuento del servidor no está a la vista ({d['resumen']!r}). "
            "El estado llega resuelto del servidor; Alpine solo lo refresca."
        )

    listos = [c for c in d["chips"] if c["estado"] == "Listo"]
    if len(listos) != 2:
        fallos.append(f"{donde}: sin JS se esperaban 2 requisitos ya cumplidos y hay {len(listos)}.")

    if not d["enviar"] or d["enviar"]["tipo"] != "submit" or d["enviar"]["desactivado"]:
        fallos.append(f"{donde}: sin JS el formulario no se puede enviar ({d['enviar']!r}).")

    return f"sin-alpine={len(listos)}-listo"


def main(argv: list[str]) -> int:
    carpeta = (Path(argv[1]) if len(argv) > 1 else BANCO_POR_DEFECTO).resolve()
    completas = [carpeta / n for n in COMPLETAS if (carpeta / n).is_file()]

    if len(completas) < 4 or not (carpeta / "errores.html").is_file() or not (carpeta / "sin-alpine.html").is_file():
        print(f"Banco incompleto en {carpeta}: genera primero `vendor/bin/pest --filter=FormularioDeTramite`.")
        return 2

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()

            for ruta in completas:
                for movimiento in MOVIMIENTOS:
                    for ancho_nombre, ancho, alto in ANCHOS:
                        contexto = navegador.new_context(
                            viewport={"width": ancho, "height": alto},
                            reduced_motion=movimiento,
                        )
                        pagina = contexto.new_page()
                        pagina.goto(ruta.as_uri())
                        pagina.wait_for_timeout(150)

                        donde = f"{nombre} {ruta.name} [{movimiento}/{ancho_nombre}]"
                        d = pagina.evaluate(SONDA)

                        verificar_estructura(d, donde, fallos)
                        obligatorios = verificar_obligatorios(d, donde, fallos)
                        columnas = verificar_columnas(d, donde, ancho_nombre, fallos)
                        peor = verificar_contraste(d, donde, fallos)
                        transicion = verificar_movimiento(d, donde, movimiento, fallos)

                        # El recorrido de teclado y el recuento vivo se miden una
                        # vez por página y navegador —en escritorio y sin la
                        # preferencia de movimiento—: repetirlos en las cuatro
                        # combinaciones solo multiplica el tiempo, porque ninguno
                        # depende del ancho ni de `prefers-reduced-motion`.
                        extra = ""
                        if ancho_nombre == "escritorio" and movimiento == "no-preference":
                            teclado = verificar_teclado(pagina, donde, fallos)
                            vivo = verificar_recuento_vivo(pagina, donde, fallos)
                            revertir = verificar_reversion(pagina, donde, fallos)
                            extra = f" {teclado} {vivo} {revertir}"

                        resumen.append(
                            f"{nombre:<9}{ruta.name:<17}[{movimiento:<13} {ancho_nombre:<10}] "
                            f"{columnas} {obligatorios} desborde={d['desborde']}px transición={transicion} "
                            f"peor contraste={peor:.2f}:1{extra}"
                        )
                        contexto.close()

            # La página con errores y la que no carga Alpine: una pasada cada una.
            for archivo, verificador in (("errores.html", verificar_errores), ("sin-alpine.html", verificar_sin_alpine)):
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
        f"{len(MOVIMIENTOS)} preferencias de movimiento × {len(ANCHOS)} anchos, más la página con errores "
        "y la que corre sin Alpine."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
