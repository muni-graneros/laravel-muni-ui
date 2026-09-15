#!/usr/bin/env python3
"""Reja de accesibilidad del paquete: abre cada demo en claro y en oscuro, mide el
contraste real del texto y corre axe-core. Devuelve 1 si algo falla.

    python3 scripts/a11y-check.py              # la vitrina (4 páginas) + demo/, ambos temas
    python3 scripts/a11y-check.py demo/app.html
    python3 scripts/a11y-check.py --json informe.json
    python3 scripts/a11y-check.py --tema oscuro

Por qué existe: en este paquete los defectos de contraste no se ven, se miden. La
etiqueta de los KPI estuvo en 1,22:1 y la pestaña activa en 1,52:1, y en una captura
las dos se veían «tenues», no ausentes. Cualquier revisión que dependa de mirar la
pantalla vuelve a dejarlas pasar.

Las DOS paletas, no una
-----------------------
El paquete tiene dos hojas con dos identidades a propósito: `muni-ui.css`, que cargan
las aplicaciones sueltas, y `muni-ui-filament.css`, que `MuniPanel` inyecta en los
paneles —y dentro de un panel `muni-ui.css` NO se carga (DESIGN §7)—. En la rama
oscura, 30 de los 32 tokens comparables valen DISTINTO. Por eso la vitrina son cuatro
páginas —`{claro,oscuro}.html` y `panel-{claro,oscuro}.html`— y las cuatro se miden por
omisión: medir solo la primera paleta no decía nada de los nueve sistemas municipales,
que corren sobre paneles Filament. La columna «Paleta» de la tabla dice cuál es cuál,
leída del atributo `data-vitrina-hoja` que estampa cada página.

Umbrales (WCAG 2.2 AA, obligatorio en sistemas del Estado por el Decreto N°1/2015):
  · texto normal          4.5:1
  · texto grande          3.0:1   (>= 24px, o >= 18.66px en negrita)
  · texto decorativo      3.0:1   (dentro de aria-hidden="true"; ver abajo)
  · axe-core              falla ante cualquier violación «serious» o «critical»

Texto decorativo (aria-hidden="true") — por qué NO se salta del todo
--------------------------------------------------------------------
Un «/» entre dos enlaces de la barra institucional no es información: es puntuación.
WCAG 1.4.3 exime el texto «incidental» (decoración y componentes inactivos) y axe-core
salta los subárboles con aria-hidden="true", porque no existen en el árbol de
accesibilidad. Esta reja hacía lo contrario: le exigía 4,5:1 a ese «/» y lo daba por
defecto en las cinco demos que llevan la barra.

Pero saltarlo del todo abre una puerta: cualquiera esconde un defecto real detrás de un
aria-hidden y la reja deja de verlo. No es hipotético — el indicador de orden de
`sortable-table` (resources/views/components) lleva aria-hidden="true" con opacity:.3,
o sea ~1,4:1, y un salto ciego lo volvería invisible para siempre.

Por eso no se salta: se RECLASIFICA.
  · se sigue midiendo, y por debajo de 3:1 FALLA igual. Está dibujado en pantalla, lo
    vea o no un lector: le aplica 1.4.11 como objeto gráfico.
  · entre 3:1 y el umbral de texto se informa aparte, con su medición, en la sección
    «Textos decorativos». No cuenta para el veredicto, pero queda escrito en cada
    corrida: la puerta existe, pero tiene una luz encendida encima.
Y esconder contenido INTERACTIVO detrás de un aria-hidden lo sigue cazando axe con
`aria-hidden-focus`, que en esta reja es «serious» y falla.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
CACHE = RAIZ / "scripts" / ".cache"
VENV_DIR = RAIZ / ".venv-a11y"
VENV_PY = VENV_DIR / "bin" / "python"


def _reejecutar_en_venv() -> None:
    """Si Playwright no está en este intérprete pero sí en el entorno del repo, saltar allá.

    En Debian y Ubuntu recientes el Python del sistema está «externally managed»
    (PEP 668) y `pip install` se rechaza, así que el entorno virtual del repo es
    la única instalación posible. Sin este salto, `npm run a11y` fallaría con un
    ImportError aunque la instalación esté hecha.
    """
    try:
        import playwright  # noqa: F401
        return
    except ImportError:
        pass
    # La comparación va por prefijo del entorno y NO por la ruta del binario: dentro
    # de un venv, bin/python es un enlace al intérprete del sistema, así que resolver
    # el enlace da la misma ruta en los dos casos y el salto no ocurriría nunca.
    ya_dentro = Path(sys.prefix).resolve() == VENV_DIR.resolve() if VENV_DIR.exists() else False
    if VENV_PY.is_file() and not ya_dentro:
        os.execv(str(VENV_PY), [str(VENV_PY), str(Path(__file__).resolve()), *sys.argv[1:]])


_reejecutar_en_venv()
AXE_VERSION = "4.10.2"
AXE_URL = f"https://cdnjs.cloudflare.com/ajax/libs/axe-core/{AXE_VERSION}/axe.min.js"

TEXTO_NORMAL = 4.5
TEXTO_GRANDE = 3.0

# ── Medición de contraste dentro de la página ────────────────────────────────
# Se hace con getComputedStyle y no con una captura: el fondo efectivo de un texto
# suele estar en un ancestro, y hay que subir hasta encontrar uno opaco. Cuando el
# fondo es un degradado o una imagen no se puede decidir por cálculo, así que se
# informa aparte en vez de dar un falso aprobado.
AYUDAS_JS = r"""
  const aRGB = (c) => {
    const m = c.match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const p = m[1].split(/[,\s\/]+/).filter(Boolean).map(Number);
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  };
  const lum = ({ r, g, b }) => {
    const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const sobre = (frente, fondo) => ({
    r: frente.r * frente.a + fondo.r * (1 - frente.a),
    g: frente.g * frente.a + fondo.g * (1 - frente.a),
    b: frente.b * frente.a + fondo.b * (1 - frente.a),
    a: 1,
  });
  const ratio = (a, b) => {
    const l1 = lum(a), l2 = lum(b);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  };
  const rutaDe = (el) => {
    const partes = [];
    for (let n = el; n && n.nodeType === 1 && partes.length < 4; n = n.parentElement) {
      let s = n.tagName.toLowerCase();
      if (n.id) { partes.unshift(s + '#' + n.id); break; }
      const cls = (n.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
      if (cls.length) s += '.' + cls.join('.');
      partes.unshift(s);
    }
    return partes.join(' > ');
  };
"""

# DOS PASADAS CON EL MISMO CÓDIGO: pantalla e impresión.
# `fondosApagados` no es un interruptor de conveniencia: es el caso REAL de una
# impresora municipal. Al imprimir, los navegadores no pintan `background-color`
# ni `background-image` salvo que el usuario marque «gráficos de fondo» en el
# diálogo —viene desmarcado— así que TODO el texto queda sobre el blanco del
# papel. Un texto claro sobre un chip de color, que en pantalla mide de sobra,
# en papel queda blanco sobre blanco. Eso no lo ve ni esta reja midiendo en
# pantalla ni axe, que no sabe de medios paginados.
# La pasada con los fondos ACTIVADOS es la otra mitad: es la casilla marcada, y
# es donde apareció D9 —el papel salía con el fondo oscuro del panel y el texto
# forzado a negro encima, 65 de 386 textos bajo umbral, el peor en 1,12:1—.
# El umbral no cambia en papel: 4,5:1 y 3:1 son los mismos (WCAG no exime la
# impresión; el Decreto N°1/2015 tampoco).
#
# Lo que esta pasada NO modela, a propósito: Chromium, al imprimir SIN fondos,
# oscurece por su cuenta el texto casi blanco (`Color::Dark()`). Medido sobre un
# PDF real de `page.pdf(print_background=False)`: `rgb(226,232,244)` salió como
# `rgb(148,152,160)`, ~3:1 sobre blanco. O sea que en Chromium la cifra «papel» de
# un texto casi blanco es peor que la real, pero sigue bajo 4,5:1; y es un
# paliativo de un motor, no algo que el paquete controle. Se mide el color que
# declara el CSS, que es lo que decide en cualquier motor.
MEDIR_CONTRASTE = r"""
(opciones) => {
""" + AYUDAS_JS + r"""
  const fondosApagados = !!(opciones && opciones.fondosApagados);
  const PAPEL = { r: 255, g: 255, b: 255, a: 1 };

  // Fondo efectivo: subir hasta el primer ancestro con color opaco, componiendo
  // las capas semitransparentes que haya en el camino. Con los fondos apagados
  // no hay nada que subir: el fondo es la hoja.
  const fondoEfectivo = (el) => {
    if (fondosApagados) return { fondo: PAPEL, degradado: false };
    let fondo = null; const capas = [];
    for (let n = el; n; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.backgroundImage && s.backgroundImage !== 'none') return { fondo: null, degradado: true };
      const c = aRGB(s.backgroundColor);
      if (!c || c.a === 0) continue;
      capas.push(c);
      if (c.a === 1) { fondo = c; break; }
    }
    if (!fondo) fondo = { r: 255, g: 255, b: 255, a: 1 };
    for (let i = capas.length - 2; i >= 0; i--) fondo = sobre(capas[i], fondo);
    return { fondo, degradado: false };
  };

  const fallas = [], indecidibles = [], decorativos = [];
  let medidos = 0;

  // Un texto dentro de aria-hidden="true" no llega al árbol de accesibilidad: no es
  // información, es dibujo. Se le exige 3:1 (1.4.11, objeto gráfico visible) en vez
  // del umbral de texto, y lo que quede entre 3:1 y ese umbral se informa aparte en
  // vez de desaparecer. El porqué y el riesgo están en el docstring del archivo.
  const registrar = (ruta, texto, px, peso, colorCss, fondo, r, decorativo) => {
    const grande = px >= 24 || (px >= 18.66 && peso >= 700);
    const barraTexto = grande ? 3.0 : 4.5;
    const minimo = decorativo ? 3.0 : barraTexto;
    const item = {
      ruta,
      texto: texto.slice(0, 60),
      ratio: Math.round(r * 100) / 100,
      minimo,
      px: Math.round(px * 10) / 10,
      peso,
      color: colorCss,
      fondo: `rgb(${Math.round(fondo.r)}, ${Math.round(fondo.g)}, ${Math.round(fondo.b)})`,
      decorativo,
    };
    // MISMA POLÍTICA DE REDONDEO QUE axe-core, y no por gusto: axe hace
    // `Math.floor(100*ratio)/100` antes de comparar. Acá había una tolerancia de
    // +0,005 que abría una ventana de cinco milésimas bajo el umbral donde esta
    // reja aprobaba y axe reprobaba EL MISMO NÚMERO, en la misma corrida. Pasó de
    // verdad con `.muni-tl__tone` en 4,495465: la reja decía 4,50 y axe 4,49.
    // Una reja más permisiva que la herramienta con la que se contrasta no sirve
    // de reja, y con `--sin-axe` el defecto pasaba en silencio.
    const truncado = Math.floor(r * 100) / 100;
    if (truncado < minimo) { fallas.push(item); return; }
    if (decorativo && truncado < barraTexto) { item.barraTexto = barraTexto; decorativos.push(item); }
  };

  // El contenido de ::before y ::after no está en el DOM, así que ni este script
  // ni axe lo veían. Ahí vivía un «✓» a 3,73:1 en dos landings. Se mide aparte,
  // leyendo el pseudo-elemento y atribuyéndoselo al elemento que lo genera.
  const pseudos = [];
  for (const el of document.querySelectorAll('*')) {
    for (const cual of ['::before', '::after']) {
      const ps = getComputedStyle(el, cual);
      const contenido = (ps.content || '').replace(/^["']|["']$/g, '').trim();
      if (!contenido || contenido === 'none' || contenido === 'normal') continue;
      if (ps.visibility === 'hidden' || ps.display === 'none') continue;
      pseudos.push({ el, cual, texto: contenido, estilo: ps });
    }
  }

  for (const el of document.querySelectorAll('*')) {
    // Solo elementos con texto propio visible.
    const propio = Array.from(el.childNodes)
      .filter((n) => n.nodeType === 3)
      .map((n) => n.textContent.trim())
      .join('');
    if (!propio) continue;

    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none' || parseFloat(cs.opacity) === 0) continue;
    const caja = el.getBoundingClientRect();
    if (caja.width < 1 || caja.height < 1) continue;

    const frente = aRGB(cs.color);
    if (!frente || frente.a === 0) continue;

    const { fondo, degradado } = fondoEfectivo(el);
    if (degradado) {
      indecidibles.push({ ruta: rutaDe(el), texto: propio.slice(0, 60), motivo: 'fondo con imagen o degradado' });
      continue;
    }

    // La opacidad de un ANCESTRO también apaga el texto, y hasta ahora no se
    // miraba: se leía solo la del propio elemento. Así pasaron un opacity:.5 en
    // el estado pendiente de la línea de tiempo (2,03:1 real) y un opacity:.6 en
    // el pie institucional de cinco demos. `opacity` no se hereda como valor
    // pero sí se acumula al componer, así que se multiplica en toda la cadena.
    let opacidadHeredada = 1;
    for (let n = el; n; n = n.parentElement) {
      const o = parseFloat(getComputedStyle(n).opacity);
      if (!isNaN(o)) opacidadHeredada *= o;
    }
    if (opacidadHeredada === 0) continue;

    const frenteConOpacidad = opacidadHeredada < 1
      ? { r: frente.r, g: frente.g, b: frente.b, a: frente.a * opacidadHeredada }
      : frente;
    const efectivo = frenteConOpacidad.a < 1 ? sobre(frenteConOpacidad, fondo) : frenteConOpacidad;
    medidos++;
    registrar(
      rutaDe(el), propio, parseFloat(cs.fontSize), parseInt(cs.fontWeight, 10) || 400,
      cs.color, fondo, ratio(efectivo, fondo), !!el.closest('[aria-hidden="true"]'),
    );
  }

  // Segunda pasada: los ::before/::after recogidos arriba. El fondo se compone
  // igual desde el elemento que los genera —el pseudo vive dentro de su caja—,
  // y arrastran la misma opacidad heredada.
  for (const { el, cual, texto, estilo } of pseudos) {
    const frente = aRGB(estilo.color);
    if (!frente || frente.a === 0) continue;
    const caja = el.getBoundingClientRect();
    if (caja.width < 1 || caja.height < 1) continue;

    const { fondo, degradado } = fondoEfectivo(el);
    if (degradado) {
      indecidibles.push({ ruta: rutaDe(el) + cual, texto: texto.slice(0, 60), motivo: 'fondo con imagen o degradado' });
      continue;
    }

    let op = 1;
    for (let n = el; n; n = n.parentElement) {
      const o = parseFloat(getComputedStyle(n).opacity);
      if (!isNaN(o)) op *= o;
    }
    const opPseudo = parseFloat(estilo.opacity);
    if (!isNaN(opPseudo)) op *= opPseudo;
    if (op === 0) continue;

    const conOp = op < 1 ? { r: frente.r, g: frente.g, b: frente.b, a: frente.a * op } : frente;
    const efectivo = conOp.a < 1 ? sobre(conOp, fondo) : conOp;
    medidos++;
    registrar(
      rutaDe(el) + cual, texto, parseFloat(estilo.fontSize), parseInt(estilo.fontWeight, 10) || 400,
      estilo.color, fondo, ratio(efectivo, fondo), !!el.closest('[aria-hidden="true"]'),
    );
  }

  fallas.sort((a, b) => a.ratio - b.ratio);
  decorativos.sort((a, b) => a.ratio - b.ratio);
  return { medidos, fallas, indecidibles, decorativos };
}
"""

# ── Contraste de NO-TEXTO (WCAG 1.4.11) ──────────────────────────────────────
# Ni esta reja ni axe-core miden esto: axe solo compara texto contra su fondo.
# 1.4.11 pide 3:1 para «la información visual necesaria para identificar un
# componente de interfaz y sus estados». En un formulario, el borde es lo ÚNICO
# que dice dónde empieza y dónde termina un campo, y el cambio de borde es lo
# único que dice que el campo está en error.
#
# Por eso se mide el borde de `input`, `select`, `textarea`, la casilla visible
# del `checkbox`/`switch` y el disparador del `combobox`, en DOS estados:
#   · normal — el que hay en la página;
#   · error  — el que ya viene marcado `aria-invalid="true"`, y además uno
#     SONDEADO: se le pone `aria-invalid="true"` al control (y al <input> real
#     que precede a la casilla visible, que es de donde cuelga la regla), se
#     relee el borde y se deshace. No es inventar un estado: es ejecutar el que
#     el propio CSS del componente declara, sin depender de que la vitrina
#     tenga una instancia fallida de cada componente.
#
# Qué se compara: el borde contra sus DOS colores adyacentes —el relleno del
# campo (interior) y la superficie sobre la que se apoya (exterior)—, y decide
# el MEJOR de los dos: un borde que se despega de cualquiera de los dos lados
# delimita el campo. Se informa el par completo para que la falla sea accionable.
#
# Qué NO es una falla: un borde de ancho 0, `style:none` o transparente. Ahí el
# componente se identifica por otra cosa (relleno, subrayado, icono) y esta reja
# no lo sabe: se lista aparte, sin veredicto, en vez de inventar un defecto.
#
# Defectos reales que pasaron por este hueco: el borde de un campo EN ERROR a
# 2,10:1 en claro y 1,53:1 en oscuro —MENOS visible que el de un campo normal—,
# y el borde de los controles antes de que existiera `--muni-field-border`.
MEDIR_NO_TEXTO = r"""
() => {
""" + AYUDAS_JS + r"""
  const MINIMO = 3.0;

  // Orden a propósito: lo específico primero, porque el disparador del combobox
  // y el buscador de la tabla TAMBIÉN son <input> y se medirían dos veces.
  const CONTROLES = [
    ['combobox', '.muni-combo__input, [role="combobox"]'],
    ['checkbox', '.muni-checkbox'],
    ['switch', '.muni-switch'],
    ['input', 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=file]):not([type=submit]):not([type=button]):not([type=reset]):not([type=image])'],
    ['select', 'select'],
    ['textarea', 'textarea'],
  ];

  const fondoDetras = (el) => {
    let fondo = null; const capas = [];
    for (let n = el; n; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.backgroundImage && s.backgroundImage !== 'none') return { fondo: null, degradado: true };
      const c = aRGB(s.backgroundColor);
      if (!c || c.a === 0) continue;
      capas.push(c);
      if (c.a === 1) { fondo = c; break; }
    }
    if (!fondo) fondo = { r: 255, g: 255, b: 255, a: 1 };
    for (let i = capas.length - 2; i >= 0; i--) fondo = sobre(capas[i], fondo);
    return { fondo, degradado: false };
  };

  const css = (n) => `rgb(${Math.round(n.r)}, ${Math.round(n.g)}, ${Math.round(n.b)})`;
  const visible = (el) => {
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') return false;
    let op = 1;
    for (let n = el; n; n = n.parentElement) {
      const o = parseFloat(getComputedStyle(n).opacity);
      if (!isNaN(o)) op *= o;
    }
    if (op === 0) return false;
    const c = el.getBoundingClientRect();
    return c.width >= 1 && c.height >= 1;
  };

  // El peor lado PINTADO del borde. Si un lado se pinta y no se ve, ese trozo
  // del contorno desaparece, así que manda el peor y no el promedio.
  const medirBorde = (el, exterior) => {
    const cs = getComputedStyle(el);
    const propio = aRGB(cs.backgroundColor) || { r: 0, g: 0, b: 0, a: 0 };
    const interior = propio.a === 0 ? exterior : (propio.a < 1 ? sobre(propio, exterior) : propio);
    let peor = null;
    let pintados = 0;
    for (const lado of ['Top', 'Right', 'Bottom', 'Left']) {
      const ancho = parseFloat(cs['border' + lado + 'Width']) || 0;
      const estilo = cs['border' + lado + 'Style'];
      const color = aRGB(cs['border' + lado + 'Color']);
      if (ancho <= 0 || estilo === 'none' || estilo === 'hidden' || !color || color.a === 0) continue;
      pintados++;
      const contra = (fondo) => ratio(color.a < 1 ? sobre(color, fondo) : color, fondo);
      const rInt = contra(interior), rExt = contra(exterior);
      const r = Math.max(rInt, rExt);
      if (!peor || r < peor.ratio) {
        peor = {
          lado: lado.toLowerCase(), ancho, estilo, ratio: r,
          color: cs['border' + lado + 'Color'],
          interior: css(interior), exterior: css(exterior),
          contraInterior: Math.round(rInt * 100) / 100,
          contraExterior: Math.round(rExt * 100) / 100,
        };
      }
    }
    return { peor, pintados, colores: ['Top', 'Right', 'Bottom', 'Left'].map((l) => cs['border' + l + 'Color']).join('|') };
  };

  const fallas = [], sinBorde = [], indecidibles = [], sinEstadoError = [];
  const cobertura = {};
  let medidos = 0;

  const anotar = (el, control, estado, m) => {
    medidos++;
    cobertura[control] = cobertura[control] || { normal: 0, error: 0 };
    cobertura[control][estado] = (cobertura[control][estado] || 0) + 1;
    const item = {
      ruta: rutaDe(el), control, estado, minimo: MINIMO,
      ratio: Math.round(m.peor.ratio * 100) / 100,
      lado: m.peor.lado, ancho: m.peor.ancho, estilo: m.peor.estilo,
      color: m.peor.color, interior: m.peor.interior, exterior: m.peor.exterior,
      contraInterior: m.peor.contraInterior, contraExterior: m.peor.contraExterior,
    };
    // MISMA POLÍTICA DE REDONDEO QUE arriba y que axe: truncar, no redondear.
    if (Math.floor(m.peor.ratio * 100) / 100 < MINIMO) fallas.push(item);
    return item;
  };

  const vistos = new Set();
  for (const [control, selector] of CONTROLES) {
    for (const el of document.querySelectorAll(selector)) {
      if (vistos.has(el)) continue;
      vistos.add(el);
      if (!visible(el)) continue;

      const { fondo: exterior, degradado } = fondoDetras(el.parentElement || el);
      if (degradado) {
        indecidibles.push({ ruta: rutaDe(el), control, motivo: 'el control se apoya en una imagen o degradado' });
        continue;
      }

      const yaEnError = el.getAttribute('aria-invalid') === 'true'
        || (el.previousElementSibling && el.previousElementSibling.getAttribute
            && el.previousElementSibling.getAttribute('aria-invalid') === 'true');
      const estado = yaEnError ? 'error' : 'normal';

      const base = medirBorde(el, exterior);
      if (!base.peor) {
        sinBorde.push({
          ruta: rutaDe(el), control, estado,
          motivo: 'sin borde pintado (ancho 0, style:none o color transparente): '
                + 'el componente se identifica por otra cosa y esta reja no la mide',
        });
        continue;
      }
      anotar(el, control, estado, base);
      if (yaEnError) continue;

      // Sonda del estado de error: se marca, se relee y se deshace.
      const deshacer = [];
      const marcar = (n) => {
        if (!n || !n.setAttribute) return;
        deshacer.push([n, n.getAttribute('aria-invalid')]);
        n.setAttribute('aria-invalid', 'true');
      };
      marcar(el);
      marcar(el.previousElementSibling);
      const conError = medirBorde(el, exterior);
      const cambio = conError.colores !== base.colores;
      for (const [n, v] of deshacer) {
        if (v === null) n.removeAttribute('aria-invalid'); else n.setAttribute('aria-invalid', v);
      }

      if (!cambio) {
        sinEstadoError.push({
          ruta: rutaDe(el), control,
          motivo: 'aria-invalid no cambia el borde: o el componente pinta el error '
                + 'desde el servidor (estilo en línea) o no lo señala con el borde',
        });
        continue;
      }
      if (!conError.peor) {
        sinBorde.push({ ruta: rutaDe(el), control, estado: 'error', motivo: 'en error el borde deja de pintarse' });
        continue;
      }
      const it = anotar(el, control, 'error', conError);
      it.sondeado = true;
      it.ratioNormal = Math.round(base.peor.ratio * 100) / 100;
      // Lo que motivó medir esto: un estado de error que se ve MENOS que el
      // estado normal invierte el significado del indicador.
      if (conError.peor.ratio < base.peor.ratio) it.peorQueNormal = true;
    }
  }

  fallas.sort((a, b) => a.ratio - b.ratio);
  return { medidos, fallas, sinBorde, indecidibles, sinEstadoError, cobertura };
}
"""

# ── Contador de páginas fuera de `@page` (D1) ────────────────────────────────
# `counter(page)` y `counter(pages)` SOLO resuelven dentro de las cajas de margen
# de `@page` (`@bottom-right { content: … }`). Pedidos desde un ::before/::after
# del documento —fijo o en el flujo— no degradan a vacío: degradan a un dato
# FALSO. Así salió «Página 0 de 0» en las 4 hojas de un acta en Chromium y
# «Página de» en Firefox (docs/VERIFICACION-NAVEGADOR.md, D1). No es un problema
# de contraste, así que ninguna medición de color lo ve: se busca el patrón.
# Las cajas de margen de `@page` no están en el DOM, así que la forma CORRECTA de
# numerar no aparece acá y no da falso positivo.
MEDIR_CONTADOR = r"""
() => {
""" + AYUDAS_JS + r"""
  const fallas = [];
  for (const el of document.querySelectorAll('*')) {
    for (const cual of ['::before', '::after']) {
      const ps = getComputedStyle(el, cual);
      const c = ps.content || '';
      if (!/counter\(\s*pages?\s*[,)]/.test(c) && !/counters\(\s*pages?\s*,/.test(c)) continue;
      if (ps.display === 'none' || ps.visibility === 'hidden') continue;
      const s = getComputedStyle(el);
      if (s.display === 'none' || s.visibility === 'hidden') continue;
      fallas.push({ ruta: rutaDe(el) + cual, content: c.slice(0, 120) });
    }
  }
  return fallas;
}
"""

# ── Transiciones apagadas antes de medir ─────────────────────────────────────
# `getComputedStyle` durante una transición CSS devuelve el valor a MITAD de
# camino, y justo después de cambiar un atributo devuelve el de ANTES. La sonda
# del estado de error de la pasada de no-texto pone `aria-invalid` y relee el
# borde en el mismo tick: con `transition: border-color var(--muni-dur)` releía
# el borde normal y concluía «aria-invalid no cambia el borde».
#
# No es hipotético, y pasaba justo en la paleta donde vivía D11: `muni-ui.css`
# lleva `--muni-dur: 0ms` bajo `prefers-reduced-motion`, pero `muni-ui-filament.css`
# deja 160ms. Medido en `panel-claro.html`, casilla del checkbox con
# aria-invalid: con transición, `rgb(144,129,91)` antes y DESPUÉS (sonda ciega);
# sin transición, `rgb(144,129,91)` → `rgb(224,163,173)`. La reja daba el
# checkbox y el combobox del panel como «sin estado de error» en vez de medirlos.
# El mismo riesgo corría para el cambio de tema (150 ms de espera contra 160 ms de
# transición) y para el paso a `media=print`.
#
# Solo `transition`, no `animation`: una animación con `fill-mode: forwards`
# (un fundido de entrada) volvería a su estado inicial —opacidad 0— al quitarla,
# y la reja dejaría de medir lo que la página sí muestra. Apagar una transición
# no cambia el valor final, solo lo adelanta.
SIN_TRANSICIONES = r"""
() => {
  const s = document.createElement('style');
  s.setAttribute('data-a11y-reja', 'sin-transiciones');
  s.textContent = '*, *::before, *::after { transition: none !important; }';
  document.head.appendChild(s);
}
"""

APLICAR_TEMA = r"""
(tema) => {
  const raiz = document.documentElement;
  raiz.setAttribute('data-muni-theme', tema);
  raiz.classList.toggle('dark', tema === 'dark');
  raiz.classList.toggle('light', tema === 'light');
  // Cuántos elementos fijan su propio tema: esos no siguen a la raíz y es correcto
  // que no lo hagan (las demos que muestran los dos temas lado a lado).
  return document.querySelectorAll('[data-muni-theme]').length - 1;
}
"""


def axe_js(ruta_dada: str | None) -> str:
    """Devuelve el código de axe-core. Prioriza lo instalado sobre la descarga."""
    candidatos = []
    if ruta_dada:
        candidatos.append(Path(ruta_dada))
    candidatos += [
        RAIZ / "node_modules" / "axe-core" / "axe.min.js",
        CACHE / f"axe-{AXE_VERSION}.min.js",
    ]
    for c in candidatos:
        if c.is_file():
            return c.read_text(encoding="utf-8")

    destino = CACHE / f"axe-{AXE_VERSION}.min.js"
    destino.parent.mkdir(parents=True, exist_ok=True)
    print(f"axe-core no está en disco; se descarga una vez a {destino.relative_to(RAIZ)}")
    try:
        with urllib.request.urlopen(AXE_URL, timeout=120) as r:
            destino.write_bytes(r.read())
    except Exception as e:  # sin red y sin caché no hay reja posible: se dice claro
        sys.exit(
            f"\nNo se pudo obtener axe-core y no hay copia en disco.\n"
            f"  Motivo: {e}\n"
            f"  Solución: `npm install` en el repo, o copiar axe.min.js a {destino}\n"
        )
    return destino.read_text(encoding="utf-8")


def hidratacion_rota(r: dict) -> str | None:
    """Devuelve por qué la hidratación no sirve, o None si sirvió.

    Son dos maneras de volver al agujero, y las dos fallan. La primera es obvia: no
    hay Alpine, o nunca terminó. La segunda es la traicionera: Alpine corrió, la
    página se marcó lista, pero algo que tenía que quedar ABIERTO quedó de alto 0.
    Ahí la reja mide de nuevo solo lo visible en reposo y da verde, que es
    exactamente lo que pasaba antes de hidratar la vitrina.
    """
    h = r.get("hidratacion")
    if not h:
        return None
    if not h.get("lista"):
        return ("SIN HIDRATAR: la página declaró que esperaba Alpine y nunca marcó "
                "data-vitrina-lista. Se midió solo lo visible en reposo, que es el "
                f"agujero que la vitrina hidratada vino a tapar. {h.get('motivo', '')}")
    if h.get("fallos"):
        return "ABRIÓ A MEDIAS, eso no se mide: " + " · ".join(h["fallos"])
    return None


def hoja_de(r: dict) -> str:
    """Qué paleta se midió en esta fila, para que la tabla diga cuál es cuál.

    Son dos hojas con dos identidades a propósito —`muni-ui.css` para las
    aplicaciones sueltas, `muni-ui-filament.css` para lo que corre dentro de un
    panel, que es donde viven los nueve sistemas municipales— y en la rama oscura
    30 de los 32 tokens comparables tienen valor DISTINTO. Una fila sin esta
    columna obliga a adivinar la paleta por el nombre del archivo.
    """
    return r.get("hoja") or "—"


def falla(r: dict) -> bool:
    return (
        bool(r["contraste"])
        or bool(r["axe_graves"])
        or hidratacion_rota(r) is not None
        or bool(r.get("no_texto"))
        or bool(r.get("impresion_papel"))
        or bool(r.get("impresion_tinta"))
        or bool(r.get("impresion_contador"))
    )


def falla_pantalla(r: dict) -> bool:
    return bool(r["contraste"]) or bool(r["axe_graves"]) or hidratacion_rota(r) is not None


def falla_impresion(r: dict) -> bool:
    return (bool(r.get("impresion_papel")) or bool(r.get("impresion_tinta"))
            or bool(r.get("impresion_contador")))


def esperar_hidratacion(page) -> dict | None:
    """Espera a que la página termine de hidratar y de ABRIR lo que tenga que abrir.

    Solo aplica a las páginas que lo declaran (`data-vitrina-espera-alpine`): la
    vitrina, que hornea Alpine 3 y abre a mano la lista del combobox, la burbuja del
    tooltip, el panel del popover, el <details> del timeline y la cabecera ordenable
    de sortable-table. Las demos de `demo/` son HTML plano y no declaran nada.

    Por qué es una espera y no un `wait_for_timeout` más largo: medir a mitad de la
    hidratación da un resultado distinto en cada corrida —y el que sale verde es el
    que mide menos—. Si la página dijo que esperaba Alpine y el apretón de manos no
    llega, eso FALLA: la reja volvió a medir solo lo visible en reposo, que es
    justamente el agujero que la vitrina hidratada vino a tapar.
    """
    if not page.evaluate("() => document.documentElement.hasAttribute('data-vitrina-espera-alpine')"):
        return None

    try:
        page.wait_for_function(
            "() => document.documentElement.getAttribute('data-vitrina-lista') === '1'",
            timeout=10000,
        )
    except Exception as e:
        return {"lista": False, "motivo": str(e).splitlines()[0], "abiertos": [], "fallos": []}

    estado = page.evaluate("() => window.__vitrinaEstado || null") or {}
    return {
        "lista": True,
        "alpine": estado.get("alpine"),
        "xdata": estado.get("xdata", 0),
        "abiertos": estado.get("abiertos", []),
        "fallos": estado.get("fallos", []),
    }


def revisar(pagina: Path, temas: list[str], axe_src: str, ctx) -> list[dict]:
    resultados = []
    for tema in temas:
        page = ctx.new_page()
        try:
            page.emulate_media(color_scheme="dark" if tema == "dark" else "light")
            page.goto(pagina.resolve().as_uri(), wait_until="load")
            page.wait_for_timeout(350)  # que Alpine hidrate y el CSS aplique
            hidratacion = esperar_hidratacion(page)
            # Qué paleta declara la página. La vitrina lo estampa en el <html>
            # (`muni-ui.css` o `muni-ui-filament.css`) y la reja lo imprime en la
            # tabla: son DOS identidades distintas —en oscuro, 30 de 32 tokens
            # comparables valen distinto— y un resultado que no diga cuál se midió
            # no dice nada. Las demos de `demo/` no declaran nada.
            hoja = page.evaluate(
                "() => document.documentElement.getAttribute('data-vitrina-hoja')"
            )
            # DESPUÉS de hidratar —la vitrina abre cosas con Alpine y no se le
            # toca nada hasta que avisa— y ANTES de cambiar tema, sondear estados
            # o pasar a impresión. El porqué, medido, en SIN_TRANSICIONES.
            page.evaluate(SIN_TRANSICIONES)
            propios = page.evaluate(APLICAR_TEMA, tema)
            page.wait_for_timeout(150)

            contraste = page.evaluate(MEDIR_CONTRASTE, {"fondosApagados": False})

            # No-texto (1.4.11) ANTES de axe: la sonda del estado de error toca
            # `aria-invalid` y lo deshace, y axe no debe ver la página a medias.
            no_texto = page.evaluate(MEDIR_NO_TEXTO)

            # ── Pasada de impresión ───────────────────────────────────────────
            # El paquete imprime: `<x-muni::hoja>` es una hoja carta con membrete
            # y folio, y el municipio emite actas y oficios todos los días. Bajo
            # `media=print` el CSS cambia entero —fuerza paleta clara, apaga
            # sombras, esconde el cromo—, así que lo medido en pantalla no dice
            # nada del papel. Se mide DOS veces, que son los dos casos reales del
            # diálogo de impresión (ver el comentario de MEDIR_CONTRASTE).
            page.emulate_media(media="print")
            page.wait_for_timeout(200)
            impr_papel = page.evaluate(MEDIR_CONTRASTE, {"fondosApagados": True})
            impr_tinta = page.evaluate(MEDIR_CONTRASTE, {"fondosApagados": False})
            impr_contador = page.evaluate(MEDIR_CONTADOR)
            page.emulate_media(media="screen")
            page.wait_for_timeout(100)

            page.add_script_tag(content=axe_src)
            axe = page.evaluate(
                """async () => {
                    const r = await axe.run(document, {
                        resultTypes: ['violations'],
                        runOnly: { type: 'tag', values: ['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa','best-practice'] },
                    });
                    return r.violations.map(v => ({
                        id: v.id, impact: v.impact, help: v.help, n: v.nodes.length,
                        ejemplo: (v.nodes[0] && v.nodes[0].target && v.nodes[0].target.join(' ')) || '',
                    }));
                }"""
            )
        finally:
            page.close()

        # Lo que falla SOLO con los fondos impresos, separado de lo que ya falla
        # en papel blanco. No es cosmético: con D9 reintroducido en una copia, el
        # total «con fondos» pasaba de 38 a 39 y parecía ruido, porque los
        # títulos claros que fallaban sobre blanco pasaban a leerse sobre el fondo
        # oscuro y salían de la lista a la vez que entraban 37 textos negros sobre
        # `#0f2025`. Contado aparte, el mismo caso pasa de 2 a 39.
        en_papel = {(f["ruta"], f["texto"]) for f in impr_papel["fallas"]}
        solo_con_fondos = [f for f in impr_tinta["fallas"] if (f["ruta"], f["texto"]) not in en_papel]

        graves = [v for v in axe if v["impact"] in ("serious", "critical")]
        # Una página de FUERA del repo —una copia con un defecto reintroducido a
        # mano, que es como se comprueba que la reja mide y no imprime ceros— no
        # tiene ruta relativa a la raíz y reventaba acá con un ValueError.
        try:
            nombre = str(pagina.relative_to(RAIZ))
        except ValueError:
            nombre = str(pagina)
        resultados.append({
            "pagina": nombre,
            "tema": tema,
            "hoja": hoja,
            "hidratacion": hidratacion,
            "temas_propios": propios,
            "medidos": contraste["medidos"],
            "contraste": contraste["fallas"],
            "indecidibles": contraste["indecidibles"],
            "decorativos": contraste["decorativos"],
            "impresion_medidos": impr_papel["medidos"],
            "impresion_papel": impr_papel["fallas"],
            "impresion_tinta": impr_tinta["fallas"],
            "impresion_solo_con_fondos": solo_con_fondos,
            "impresion_decorativos": impr_papel["decorativos"],
            "impresion_contador": impr_contador,
            "no_texto_medidos": no_texto["medidos"],
            "no_texto": no_texto["fallas"],
            "no_texto_sin_borde": no_texto["sinBorde"],
            "no_texto_sin_error": no_texto["sinEstadoError"],
            "no_texto_indecidibles": no_texto["indecidibles"],
            "no_texto_cobertura": no_texto["cobertura"],
            "axe_graves": graves,
            "axe_otros": [v for v in axe if v["impact"] not in ("serious", "critical")],
        })
    return resultados


def main() -> int:
    ap = argparse.ArgumentParser(description="Reja de accesibilidad de laravel-muni-ui")
    ap.add_argument(
        "paginas",
        nargs="*",
        help="archivos HTML; por defecto las 4 páginas de la vitrina (2 paletas × 2 temas) y todas las de demo/",
    )
    ap.add_argument("--tema", choices=["claro", "oscuro", "ambos"], default="ambos")
    ap.add_argument("--axe", help="ruta a axe.min.js (si no, node_modules o caché)")
    ap.add_argument("--json", help="escribe el informe completo en este archivo")
    ap.add_argument("--sin-axe", action="store_true", help="solo medir contraste")
    args = ap.parse_args()

    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        sys.exit(
            "\nFalta Playwright para Python.\n"
            "  npm run a11y:instalar\n"
            "que equivale a:\n"
            "  python3 -m venv .venv-a11y\n"
            "  .venv-a11y/bin/pip install playwright\n"
            "  .venv-a11y/bin/python -m playwright install chromium\n"
            "\nVa en un entorno virtual del repo porque el Python del sistema está gestionado\n"
            "por la distribución (PEP 668) y rechaza `pip install`.\n"
        )

    # La vitrina va PRIMERO y va por defecto, sus CUATRO páginas. Se construyó
    # justamente porque medir solo demo/*.html mide maquetas escritas a mano, no los
    # componentes que se publican; dejarla fuera del conjunto por omisión anulaba su
    # razón de existir. Y son cuatro y no dos porque son dos paletas distintas: el
    # glob las toma todas, así que añadir una variante no obliga a tocar esto.
    # La genera `vendor/bin/pest --filter=GeneraVitrina`, y no está versionada, así
    # que si falta se avisa en vez de fallar: la reja sigue sirviendo sin ella.
    vitrina = sorted((RAIZ / "build/vitrina").glob("*.html"))
    if args.paginas:
        paginas = [Path(p) for p in args.paginas]
    else:
        paginas = vitrina + sorted((RAIZ / "demo").glob("*.html"))
        if not vitrina:
            print(
                "Aviso: no hay vitrina en build/vitrina/. Se mide solo demo/*.html, que son\n"
                "       maquetas escritas a mano y no los componentes reales. Genérala con:\n"
                "         vendor/bin/pest --filter=GeneraVitrina\n"
            )
    paginas = [p if p.is_absolute() else (RAIZ / p) for p in paginas]
    faltan = [p for p in paginas if not p.is_file()]
    if faltan:
        sys.exit("No existen: " + ", ".join(str(p) for p in faltan))
    if not paginas:
        sys.exit("No hay nada que revisar: ni build/vitrina/*.html ni demo/*.html")

    temas = {"claro": ["light"], "oscuro": ["dark"], "ambos": ["light", "dark"]}[args.tema]
    axe_src = "" if args.sin_axe else axe_js(args.axe)

    print(f"Revisando {len(paginas)} página(s) × {len(temas)} tema(s) — umbral 4.5:1 texto normal, 3:1 texto grande\n")

    filas = []
    with sync_playwright() as p:
        navegador = p.chromium.launch()
        try:
            for pagina in paginas:
                # UN CONTEXTO POR PÁGINA, no uno para toda la corrida. Con uno solo,
                # el proceso de render acumulaba memoria carga tras carga —20 páginas
                # de vitrina de hasta 700 KB más las demos, en dos temas, cada una con
                # cuatro recorridos de contraste y axe— y con 88 componentes el sistema
                # mató la reja dos veces por falta de memoria. Cerrar solo la pestaña no
                # libera lo que retiene el contexto; cerrar el contexto sí.
                ctx = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion="reduce")
                try:
                    resultados_pagina = revisar(pagina, temas, axe_src, ctx)
                finally:
                    ctx.close()
                for r in resultados_pagina:
                    filas.append(r)
                    marca = "FALLA" if falla(r) else "ok"
                    peor = f"{r['contraste'][0]['ratio']:.2f}" if r["contraste"] else "—"
                    impr = (len(r["impresion_papel"]) + len(r["impresion_solo_con_fondos"])
                            + len(r["impresion_contador"]))
                    print(f"  [{marca:5s}] {r['pagina']:34s} {r['tema']:5s} {hoja_de(r):20s} "
                          f"texto={r['medidos']:4d} pantalla↓={len(r['contraste']):3d} "
                          f"peor={peor:6s} impresión↓={impr:3d} 1.4.11↓={len(r['no_texto']):2d} "
                          f"axe grave={len(r['axe_graves']):2d}")
        finally:
            navegador.close()

    # TRES TABLAS Y NO UNA. Una falla de impresión y una de pantalla no se
    # arreglan en el mismo sitio —la primera vive en el bloque `@media print`, la
    # segunda en los tokens de la paleta— y el contraste de un borde no es el de
    # un texto. Mezcladas en una sola columna «bajo umbral» obligan a abrir el
    # detalle para saber de qué se está hablando.
    print("\n" + "=" * 121)
    print("PANTALLA — contraste de texto (WCAG 1.4.3) y axe-core")
    print("-" * 121)
    print(f"{'Página':34s} {'Tema':6s} {'Paleta (hoja cargada)':21s} {'Textos':>7s} {'Bajo umbral':>12s} "
          f"{'Peor':>7s} {'axe grave':>10s} {'Veredicto':>10s}")
    print("-" * 121)
    for r in filas:
        peor = f"{r['contraste'][0]['ratio']:.2f}" if r["contraste"] else "—"
        ok = not falla_pantalla(r)
        print(f"{r['pagina']:34s} {r['tema']:6s} {hoja_de(r):21s} {r['medidos']:7d} {len(r['contraste']):12d} "
              f"{peor:>7s} {len(r['axe_graves']):10d} {'pasa' if ok else 'FALLA':>10s}")
    print("=" * 121)

    print("\n" + "=" * 121)
    print("IMPRESIÓN (media=print) — el mismo umbral, otro CSS. «papel» = gráficos de fondo")
    print("apagados, todo el texto sobre blanco (lo que hace una impresora por omisión); «+fondos» =")
    print("lo que falla SOLO si se marca «gráficos de fondo» (ahí vivía D9); «contador» =")
    print("counter(page)/counter(pages) fuera de @page, que imprime «Página 0 de 0» (D1).")
    print("-" * 121)
    print(f"{'Página':34s} {'Tema':6s} {'Paleta (hoja cargada)':21s} {'Textos':>7s} "
          f"{'papel↓':>7s} {'peor':>7s} {'+fondos↓':>9s} {'peor':>7s} {'contador':>9s} {'Veredicto':>10s}")
    print("-" * 121)
    for r in filas:
        pp, pt = r["impresion_papel"], r["impresion_solo_con_fondos"]
        peor_p = f"{pp[0]['ratio']:.2f}" if pp else "—"
        peor_t = f"{pt[0]['ratio']:.2f}" if pt else "—"
        ok = not falla_impresion(r)
        print(f"{r['pagina']:34s} {r['tema']:6s} {hoja_de(r):21s} {r['impresion_medidos']:7d} "
              f"{len(pp):7d} {peor_p:>7s} {len(pt):9d} {peor_t:>7s} {len(r['impresion_contador']):9d} "
              f"{'pasa' if ok else 'FALLA':>10s}")
    print("=" * 121)

    print("\n" + "=" * 121)
    print("NO-TEXTO (WCAG 1.4.11, mínimo 3:1) — borde de los controles de formulario contra")
    print("su fondo adyacente, en estado normal y en estado de error. axe no mide esto.")
    print("-" * 121)
    print(f"{'Página':34s} {'Tema':6s} {'Paleta (hoja cargada)':21s} {'Bordes':>7s} {'Bajo 3:1':>9s} "
          f"{'Peor':>7s} {'Sin borde':>10s} {'Veredicto':>10s}")
    print("-" * 121)
    for r in filas:
        nt = r["no_texto"]
        peor = f"{nt[0]['ratio']:.2f}" if nt else "—"
        print(f"{r['pagina']:34s} {r['tema']:6s} {hoja_de(r):21s} {r['no_texto_medidos']:7d} {len(nt):9d} "
              f"{peor:>7s} {len(r['no_texto_sin_borde']):10d} {'pasa' if not nt else 'FALLA':>10s}")
    print("=" * 121)

    hidratadas = [r for r in filas if r.get("hidratacion")]
    if hidratadas:
        print("\nHidratación (páginas que declaran que esperan Alpine)\n")
        for r in hidratadas:
            h = r["hidratacion"]
            if not h["lista"]:
                print(f"   {r['pagina']:34s} {r['tema']:6s} SIN HIDRATAR · {h.get('motivo', '')}")
                continue
            print(f"   {r['pagina']:34s} {r['tema']:6s} Alpine {h.get('alpine') or '?'} · "
                  f"{h.get('xdata', 0)} nodos [x-data] · abiertos: {', '.join(h['abiertos']) or 'ninguno'}")
            for f in h["fallos"]:
                print(f"      no abrió: {f}")
        print()

    def detalle_texto(fallas: list[dict], etiqueta: str, tope: int = 12) -> None:
        for f in fallas[:tope]:
            print(f"   [{etiqueta}] contraste {f['ratio']:5.2f}:1 (mínimo {f['minimo']}) · "
                  f"{f['px']}px/{f['peso']} · {f['color']} sobre {f['fondo']}\n"
                  f"      {f['ruta']}\n      «{f['texto']}»")
        if len(fallas) > tope:
            print(f"   … y {len(fallas) - tope} más en [{etiqueta}] (usa --json para verlas todas)")

    fallidas = [r for r in filas if falla(r)]
    if fallidas:
        print("\nDetalle de lo que falla\n")
        for r in fallidas:
            print(f"── {r['pagina']} · tema {r['tema']}")
            roto = hidratacion_rota(r)
            if roto:
                print(f"   {roto}")
            detalle_texto(r["contraste"], "pantalla")
            # La pasada de impresión va ETIQUETADA: el mismo selector puede pasar
            # en pantalla y fallar en papel, y son dos arreglos distintos.
            detalle_texto(r["impresion_papel"], "impresión·papel")
            # Con fondos, solo lo que NO salió ya en papel: el mismo selector dos
            # veces en el detalle no dice nada nuevo (el total está en el JSON).
            detalle_texto(r["impresion_solo_con_fondos"], "impresión·+fondos")
            for c in r["impresion_contador"]:
                print(f"   [impresión·contador] counter(page) fuera de @page: imprime un número FALSO "
                      f"(«Página 0 de 0»)\n      {c['ruta']}\n      content: {c['content']}")
            for f in r["no_texto"]:
                extra = " · SONDEADO poniendo aria-invalid" if f.get("sondeado") else ""
                if f.get("peorQueNormal"):
                    extra += (f" · MÁS INVISIBLE QUE EL ESTADO NORMAL "
                              f"({f['ratio']:.2f} en error contra {f['ratioNormal']:.2f} normal)")
                print(f"   [1.4.11·{f['estado']}] borde {f['ratio']:5.2f}:1 (mínimo {f['minimo']}) · "
                      f"{f['control']} · lado {f['lado']} {f['ancho']}px {f['estilo']}{extra}\n"
                      f"      {f['color']} sobre interior {f['interior']} ({f['contraInterior']:.2f}:1) "
                      f"y exterior {f['exterior']} ({f['contraExterior']:.2f}:1)\n"
                      f"      {f['ruta']}")
            for v in r["axe_graves"]:
                print(f"   axe [{v['impact']}] {v['id']}: {v['help']} ({v['n']} nodo(s)) · {v['ejemplo'][:70]}")
            print()

    # Lo que la pasada de no-texto NO pudo juzgar. No son fallas y no cuentan
    # para el veredicto, pero se escriben en cada corrida: un control sin borde
    # se identifica por otra cosa que esta reja no mide, y un control cuyo
    # `aria-invalid` no cambia nada pinta su error en otro lado (o no lo pinta).
    sin_borde: dict[tuple, set] = {}
    sin_error: dict[tuple, set] = {}
    for r in filas:
        for d in r.get("no_texto_sin_borde", []):
            sin_borde.setdefault((d["control"], d["ruta"], d["motivo"]), set()).add(r["pagina"])
        for d in r.get("no_texto_sin_error", []):
            sin_error.setdefault((d["control"], d["ruta"], d["motivo"]), set()).add(r["pagina"])
    if sin_borde or sin_error:
        print("\nNo-texto: lo que quedó sin juzgar (no cuenta para el veredicto)\n")
        for (control, ruta, motivo), pags in sorted(sin_borde.items()):
            print(f"   sin borde   · {control} · {motivo}\n      {ruta}\n      en: {', '.join(sorted(pags))}")
        for (control, ruta, motivo), pags in sorted(sin_error.items()):
            print(f"   sin estado de error · {control} · {motivo}\n      {ruta}\n      en: {', '.join(sorted(pags))}")
        print()

    # Cobertura: qué controles y qué estados se midieron de verdad. La lección de
    # `alert`, que la vitrina renderiza con un solo tono y por eso los otros tres
    # nunca se midieron, vale igual acá: un estado que no aparece en la página no
    # se mide, y eso tiene que verse sin abrir el JSON.
    cobertura: dict[str, dict[str, int]] = {}
    for r in filas:
        for control, est in (r.get("no_texto_cobertura") or {}).items():
            acc = cobertura.setdefault(control, {"normal": 0, "error": 0})
            for k, v in est.items():
                acc[k] = acc.get(k, 0) + v
    if cobertura:
        print("No-texto: cobertura por control (suma de las páginas × temas medidos)\n")
        for control in sorted(cobertura):
            c = cobertura[control]
            aviso = "" if c.get("error") else "   ← el estado de ERROR no se midió en ningún lado"
            print(f"   {control:10s} normal={c.get('normal', 0):3d}  error={c.get('error', 0):3d}{aviso}")
        print()

    # Textos decorativos: pasan el 3:1 de objeto gráfico pero no llegarían al umbral
    # de texto. No fallan —están fuera del árbol de accesibilidad— pero se listan en
    # cada corrida para que nadie use aria-hidden como escondite silencioso.
    decorativos: dict[tuple, dict] = {}
    for r in filas:
        for d in r.get("decorativos", []):
            clave = (d["ruta"], d["texto"], d["ratio"])
            entrada = decorativos.setdefault(clave, {**d, "paginas": set()})
            entrada["paginas"].add((r["pagina"], r["tema"]))
    if decorativos:
        print("\nTextos decorativos (aria-hidden=\"true\"): medidos con el 3:1 de objeto")
        print("gráfico (WCAG 1.4.11), no con el umbral de texto. No cuentan para el veredicto.\n")
        for d in sorted(decorativos.values(), key=lambda d: d["ratio"]):
            paginas = sorted({p for p, _ in d["paginas"]})
            print(f"   {d['ratio']:5.2f}:1 (mín. decorativo {d['minimo']}, texto sería {d['barraTexto']}) · "
                  f"{d['px']}px/{d['peso']} · {d['color']} sobre {d['fondo']}\n"
                  f"      {d['ruta']}\n      «{d['texto']}»\n"
                  f"      en {len(paginas)} página(s): {', '.join(paginas)}")
        print()

    indecidibles = sum(len(r["indecidibles"]) for r in filas)
    if indecidibles:
        print(f"Aviso: {indecidibles} texto(s) sobre imagen o degradado. El cálculo no decide ahí; "
              f"hay que medirlos sobre la captura, en el punto más claro del fondo.")

    if args.json:
        Path(args.json).write_text(json.dumps(filas, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"\nInforme completo en {args.json}")

    if fallidas:
        print(f"\n{len(fallidas)} de {len(filas)} combinaciones demo×tema fallan. El commit no debería salir así.")
        return 1
    print("\nTodo pasa.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
