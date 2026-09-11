#!/usr/bin/env python3
"""Reja de accesibilidad del paquete: abre cada demo en claro y en oscuro, mide el
contraste real del texto y corre axe-core. Devuelve 1 si algo falla.

    python3 scripts/a11y-check.py              # todas las demos, ambos temas
    python3 scripts/a11y-check.py demo/app.html
    python3 scripts/a11y-check.py --json informe.json
    python3 scripts/a11y-check.py --tema oscuro

Por qué existe: en este paquete los defectos de contraste no se ven, se miden. La
etiqueta de los KPI estuvo en 1,22:1 y la pestaña activa en 1,52:1, y en una captura
las dos se veían «tenues», no ausentes. Cualquier revisión que dependa de mirar la
pantalla vuelve a dejarlas pasar.

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
MEDIR_CONTRASTE = r"""
() => {
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
    if (r + 0.005 < minimo) { fallas.push(item); return; }
    if (decorativo && r + 0.005 < barraTexto) { item.barraTexto = barraTexto; decorativos.push(item); }
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

    // Fondo efectivo: subir hasta el primer ancestro con color opaco, componiendo
    // las capas semitransparentes que haya en el camino.
    let fondo = null, capas = [], degradado = false;
    for (let n = el; n; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.backgroundImage && s.backgroundImage !== 'none') { degradado = true; break; }
      const c = aRGB(s.backgroundColor);
      if (!c || c.a === 0) continue;
      capas.push(c);
      if (c.a === 1) { fondo = c; break; }
    }
    if (!fondo && !degradado) fondo = { r: 255, g: 255, b: 255, a: 1 };
    if (degradado) {
      indecidibles.push({ ruta: rutaDe(el), texto: propio.slice(0, 60), motivo: 'fondo con imagen o degradado' });
      continue;
    }
    for (let i = capas.length - 2; i >= 0; i--) fondo = sobre(capas[i], fondo);

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

    let fondo = null, capas = [], degradado = false;
    for (let n = el; n; n = n.parentElement) {
      const st = getComputedStyle(n);
      if (st.backgroundImage && st.backgroundImage !== 'none') { degradado = true; break; }
      const c = aRGB(st.backgroundColor);
      if (!c || c.a === 0) continue;
      capas.push(c);
      if (c.a === 1) { fondo = c; break; }
    }
    if (degradado) {
      indecidibles.push({ ruta: rutaDe(el) + cual, texto: texto.slice(0, 60), motivo: 'fondo con imagen o degradado' });
      continue;
    }
    if (!fondo) fondo = { r: 255, g: 255, b: 255, a: 1 };
    for (let i = capas.length - 2; i >= 0; i--) fondo = sobre(capas[i], fondo);

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


def falla(r: dict) -> bool:
    return bool(r["contraste"]) or bool(r["axe_graves"]) or hidratacion_rota(r) is not None


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
            propios = page.evaluate(APLICAR_TEMA, tema)
            page.wait_for_timeout(150)

            contraste = page.evaluate(MEDIR_CONTRASTE)

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

        graves = [v for v in axe if v["impact"] in ("serious", "critical")]
        resultados.append({
            "pagina": str(pagina.relative_to(RAIZ)),
            "tema": tema,
            "hidratacion": hidratacion,
            "temas_propios": propios,
            "medidos": contraste["medidos"],
            "contraste": contraste["fallas"],
            "indecidibles": contraste["indecidibles"],
            "decorativos": contraste["decorativos"],
            "axe_graves": graves,
            "axe_otros": [v for v in axe if v["impact"] not in ("serious", "critical")],
        })
    return resultados


def main() -> int:
    ap = argparse.ArgumentParser(description="Reja de accesibilidad de laravel-muni-ui")
    ap.add_argument(
        "paginas",
        nargs="*",
        help="archivos HTML; por defecto la vitrina de componentes y todas las de demo/",
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

    # La vitrina va PRIMERO y va por defecto. Se construyó justamente porque medir
    # solo demo/*.html mide maquetas escritas a mano, no los componentes que se
    # publican; dejarla fuera del conjunto por omisión anulaba su razón de existir.
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
        ctx = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion="reduce")
        try:
            for pagina in paginas:
                for r in revisar(pagina, temas, axe_src, ctx):
                    filas.append(r)
                    marca = "FALLA" if falla(r) else "ok"
                    peor = f"{r['contraste'][0]['ratio']:.2f}" if r["contraste"] else "—"
                    print(f"  [{marca:5s}] {r['pagina']:34s} {r['tema']:5s} "
                          f"texto={r['medidos']:4d} contraste↓={len(r['contraste']):3d} "
                          f"peor={peor:6s} axe grave={len(r['axe_graves']):2d}")
        finally:
            ctx.close()
            navegador.close()

    print("\n" + "=" * 100)
    print(f"{'Demo':34s} {'Tema':6s} {'Textos':>7s} {'Bajo umbral':>12s} {'Peor':>7s} {'axe grave':>10s} {'Veredicto':>10s}")
    print("-" * 100)
    for r in filas:
        peor = f"{r['contraste'][0]['ratio']:.2f}" if r["contraste"] else "—"
        ok = not falla(r)
        print(f"{r['pagina']:34s} {r['tema']:6s} {r['medidos']:7d} {len(r['contraste']):12d} "
              f"{peor:>7s} {len(r['axe_graves']):10d} {'pasa' if ok else 'FALLA':>10s}")
    print("=" * 100)

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

    fallidas = [r for r in filas if falla(r)]
    if fallidas:
        print("\nDetalle de lo que falla\n")
        for r in fallidas:
            print(f"── {r['pagina']} · tema {r['tema']}")
            roto = hidratacion_rota(r)
            if roto:
                print(f"   {roto}")
            for f in r["contraste"][:12]:
                print(f"   contraste {f['ratio']:5.2f}:1 (mínimo {f['minimo']}) · {f['px']}px/{f['peso']} · "
                      f"{f['color']} sobre {f['fondo']}\n"
                      f"      {f['ruta']}\n      «{f['texto']}»")
            if len(r["contraste"]) > 12:
                print(f"   … y {len(r['contraste']) - 12} más (usa --json para verlas todas)")
            for v in r["axe_graves"]:
                print(f"   axe [{v['impact']}] {v['id']}: {v['help']} ({v['n']} nodo(s)) · {v['ejemplo'][:70]}")
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
