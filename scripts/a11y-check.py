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
  · axe-core              falla ante cualquier violación «serious» o «critical»
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

  const fallas = [], indecidibles = [];
  let medidos = 0;

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

    const efectivo = frente.a < 1 ? sobre(frente, fondo) : frente;
    const px = parseFloat(cs.fontSize);
    const peso = parseInt(cs.fontWeight, 10) || 400;
    const grande = px >= 24 || (px >= 18.66 && peso >= 700);
    const minimo = grande ? 3.0 : 4.5;
    const r = ratio(efectivo, fondo);
    medidos++;
    if (r + 0.005 < minimo) {
      fallas.push({
        ruta: rutaDe(el),
        texto: propio.slice(0, 60),
        ratio: Math.round(r * 100) / 100,
        minimo,
        px: Math.round(px * 10) / 10,
        peso,
        color: cs.color,
        fondo: `rgb(${Math.round(fondo.r)}, ${Math.round(fondo.g)}, ${Math.round(fondo.b)})`,
      });
    }
  }
  fallas.sort((a, b) => a.ratio - b.ratio);
  return { medidos, fallas, indecidibles };
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


def revisar(pagina: Path, temas: list[str], axe_src: str, ctx) -> list[dict]:
    resultados = []
    for tema in temas:
        page = ctx.new_page()
        try:
            page.emulate_media(color_scheme="dark" if tema == "dark" else "light")
            page.goto(pagina.resolve().as_uri(), wait_until="load")
            page.wait_for_timeout(350)  # que Alpine hidrate y el CSS aplique
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
            "temas_propios": propios,
            "medidos": contraste["medidos"],
            "contraste": contraste["fallas"],
            "indecidibles": contraste["indecidibles"],
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
                    marca = "FALLA" if (r["contraste"] or r["axe_graves"]) else "ok"
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
        ok = not r["contraste"] and not r["axe_graves"]
        print(f"{r['pagina']:34s} {r['tema']:6s} {r['medidos']:7d} {len(r['contraste']):12d} "
              f"{peor:>7s} {len(r['axe_graves']):10d} {'pasa' if ok else 'FALLA':>10s}")
    print("=" * 100)

    fallidas = [r for r in filas if r["contraste"] or r["axe_graves"]]
    if fallidas:
        print("\nDetalle de lo que falla\n")
        for r in fallidas:
            print(f"── {r['pagina']} · tema {r['tema']}")
            for f in r["contraste"][:12]:
                print(f"   contraste {f['ratio']:5.2f}:1 (mínimo {f['minimo']}) · {f['px']}px/{f['peso']} · "
                      f"{f['color']} sobre {f['fondo']}\n"
                      f"      {f['ruta']}\n      «{f['texto']}»")
            if len(r["contraste"]) > 12:
                print(f"   … y {len(r['contraste']) - 12} más (usa --json para verlas todas)")
            for v in r["axe_graves"]:
                print(f"   axe [{v['impact']}] {v['id']}: {v['help']} ({v['n']} nodo(s)) · {v['ejemplo'][:70]}")
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
