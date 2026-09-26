#!/usr/bin/env python3
"""Verificación en navegador del disparador por defecto de `<x-muni::command-palette>`
sobre el banco `build/rotulo-del-atajo/`.

    .venv-a11y/bin/python tests/navegador/rotulo-del-atajo.py [build/rotulo-del-atajo]
        [--navegadores chromium,firefox] [--capturas DIR]

La corre `tests/RotuloDelAtajoTest.php` después de generar el banco (y se salta si
no está el entorno de la reja, que en CI no se instala). Existe porque las pruebas
de Pest sobre la paleta leen el TEXTO del HTML y del CSS —que el ⌘ vaya bajo
`aria-hidden`, que `show()` no enfoque a mano, que la guardia de movimiento
reducido esté escrita— y lo que de verdad importa se mide acá, con estilos
computados, el nombre accesible que calcula el navegador y el teclado:

  · el nombre accesible del botón es «Buscar o ir a… Control K» (o «Comando K»
    cuando la plataforma es Mac), sin el ⌘ ni un «C-T-R-L» deletreado;
  · se ve UNA sola combinación (Ctrl K, o ⌘ K en Mac), nunca las dos;
  · `aria-keyshortcuts="Control+K Meta+K"`, `aria-haspopup="dialog"`, `type="button"`;
  · Tab llega al disparador y el foco se ve por `outline` de 3 px con ≥ 3:1;
  · Enter abre, el foco entra a la caja, Escape cierra y DEVUELVE el foco al
    disparador (el defecto que se midió en Firefox y en Chromium con movimiento
    reducido cuando `show()` enfocaba la caja a mano);
  · Ctrl+K abre la paleta k y Ctrl+P la p (mismo dato para rótulo y manejador), y
    con Bloq Mayús (`key === 'K'`) el atajo sigue vivo;
  · el texto del disparador y el de la tecla pasan 4,5:1 en reposo, en las dos
    paletas y los dos temas; la tecla no lleva sombra y cae en la mono del sistema;
  · con `prefers-reduced-motion: reduce` la transición del disparador computa a 0 s
    en las CUATRO páginas, incluidas `panel-*`, donde solo se carga
    `muni-ui-filament.css` y esa hoja NO baja `--muni-dur` (DESIGN §7). Sin la
    preferencia la transición existe: si no, la comprobación sería vacía;
  · la tecla «esc» del cuadro va oculta al lector y «Escape cierra la paleta» está
    en el árbol midiendo ≤ 1 px;
  · sin desborde horizontal a 1440 ni a 390 px, y sin errores de consola.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si el banco no está.
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
BANCO_POR_DEFECTO = RAIZ / "build" / "rotulo-del-atajo"
MOVIMIENTOS = ("no-preference", "reduce")

# Lo que se lee de cada paleta con estilos computados. El fondo efectivo se busca
# subiendo por los ancestros hasta el primero opaco, como hace la reja.
SONDA = r"""
() => {
  const norm = s => String(s).replace(/["']/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
  const transparente = bg => !bg || bg === 'transparent' || /^rgba\(\d+, \d+, \d+, 0\)$/.test(bg);
  const fondoEfectivo = el => {
    while (el) {
      const bg = getComputedStyle(el).backgroundColor;
      if (!transparente(bg)) return bg;
      el = el.parentElement;
    }
    return getComputedStyle(document.body).backgroundColor;
  };
  const caja = el => { const r = el.getBoundingClientRect(); return { ancho: r.width, alto: r.height }; };

  const paletas = ['paleta-k', 'paleta-p'].map(id => {
    const raiz = document.getElementById(id);
    const botones = raiz ? raiz.querySelectorAll('button.muni-cmdk__trigger') : [];
    if (!raiz || botones.length !== 1) return { id, botones: botones.length };
    const boton = botones[0];
    const cs = getComputedStyle(boton);
    const tecla = raiz.querySelector('kbd.muni-kbd');
    const ct = getComputedStyle(tecla);
    return {
      id,
      botones: 1,
      tipo: boton.getAttribute('type'),
      haspopup: boton.getAttribute('aria-haspopup'),
      keyshortcuts: boton.getAttribute('aria-keyshortcuts'),
      ...caja(boton),
      fondo: fondoEfectivo(boton),
      colorTexto: getComputedStyle(boton.querySelector(':scope > span')).color,
      colorTecla: ct.color,
      bordeTecla: ct.borderTopColor,
      sombraTecla: ct.boxShadow,
      familiaTecla: norm(ct.fontFamily),
      transicionDuracion: cs.transitionDuration,
      transicionPropiedad: cs.transitionProperty,
      combos: [...raiz.querySelectorAll('.muni-cmdk__combo')].map(c => ({
        visible: getComputedStyle(c).display !== 'none',
        texto: c.textContent.replace(/\s+/g, ''),
      })),
      teclas: [...raiz.querySelectorAll('kbd.muni-kbd')].map(k => k.textContent.trim()),
      ocultos: [...boton.querySelectorAll('.muni-cmdk__sr')].map(s => {
        const c = getComputedStyle(s);
        return { texto: s.textContent.trim(), display: c.display, visibility: c.visibility, ...caja(s) };
      }),
    };
  });

  return {
    mono: norm(getComputedStyle(document.documentElement).getPropertyValue('--muni-font-mono')),
    sinDesborde: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    paletas,
  };
}
"""

# Lo que se mira con la paleta ABIERTA: la tecla «esc» del cuadro y su texto.
SONDA_ABIERTA = r"""
() => {
  const dialogo = [...document.querySelectorAll('[role="dialog"][aria-label="Paleta de comandos"]')]
    .find(d => getComputedStyle(d).display !== 'none');
  if (!dialogo) return null;
  const esc = dialogo.querySelector('kbd.muni-kbd');
  const sr = [...dialogo.querySelectorAll('.muni-cmdk__sr')].map(s => {
    const c = getComputedStyle(s);
    const r = s.getBoundingClientRect();
    return { texto: s.textContent.trim(), display: c.display, visibility: c.visibility, ancho: r.width, alto: r.height };
  });
  return {
    escOculta: !!esc && (esc.getAttribute('aria-hidden') === 'true' || !!esc.closest('[aria-hidden="true"]')),
    sr,
    placeholderActivo: document.activeElement ? document.activeElement.placeholder : null,
  };
}
"""

MAC = """
Object.defineProperty(navigator, 'platform', { get: () => 'MacIntel' });
try { Object.defineProperty(navigator, 'userAgentData', { get: () => undefined }); } catch (e) {}
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


def contraste(frente: str, fondo: str) -> float:
    """Ratio WCAG de un color (con alfa, compuesto sobre el fondo) contra un fondo opaco."""
    f = _mezclar(frente, fondo)
    b = _mezclar(fondo, "rgb(0, 0, 0)")
    l1, l2 = _luminancia(f), _luminancia(b)
    claro, oscuro = max(l1, l2), min(l1, l2)
    return (claro + 0.05) / (oscuro + 0.05)


def _espera_caja_activa(pagina, placeholder: str, timeout: int = 3000) -> bool:
    try:
        pagina.wait_for_function(
            "(ph) => document.activeElement && document.activeElement.classList.contains('muni-cmdk__input')"
            " && document.activeElement.placeholder === ph",
            arg=placeholder,
            timeout=timeout,
        )
        return True
    except Exception:  # noqa: BLE001 — Playwright levanta TimeoutError; lo que importa es el booleano
        return False


def _cierra(pagina) -> None:
    pagina.keyboard.press("Escape")
    pagina.wait_for_function(
        "() => [...document.querySelectorAll('[role=dialog]')].every(d => getComputedStyle(d).display === 'none')"
    )
    # La devolución del foco del plugin Focus va en un setTimeout tras el cierre.
    pagina.wait_for_timeout(150)


def verificar_pagina(pagina, ruta: Path, movimiento: str, mac: bool, fallos: list[str], capturas: Path | None) -> dict:
    """Aplica la sonda y el recorrido de teclado a una página abierta; acumula fallos."""
    donde = f"{ruta.name} [{movimiento}{', mac' if mac else ''}]"
    d = pagina.evaluate(SONDA)

    if not d["sinDesborde"]:
        fallos.append(f"{donde}: la página desborda en horizontal a 1440 px")

    for p in d["paletas"]:
        quien = f"{donde} {p['id']}"
        if p["botones"] != 1:
            fallos.append(f"{quien}: hay {p['botones']} disparador(es) por defecto, esperaba 1")
            continue
        tecla = "K" if p["id"] == "paleta-k" else "P"

        if p["tipo"] != "button":
            fallos.append(f"{quien}: type={p['tipo']!r}, esperaba 'button'")
        if p["haspopup"] != "dialog":
            fallos.append(f"{quien}: aria-haspopup={p['haspopup']!r}, esperaba 'dialog'")
        if p["keyshortcuts"] != f"Control+{tecla} Meta+{tecla}":
            fallos.append(f"{quien}: aria-keyshortcuts={p['keyshortcuts']!r}")
        if p["alto"] < 24 or p["ancho"] < 24:
            fallos.append(f"{quien}: el disparador mide {p['ancho']:.0f}×{p['alto']:.0f} px (mínimo 24×24, WCAG 2.5.8)")

        # Una sola combinación visible, y la que corresponde a la plataforma.
        visibles = [c["texto"] for c in p["combos"] if c["visible"]]
        esperado = [f"⌘{tecla}"] if mac else [f"Ctrl{tecla}"]
        if visibles != esperado:
            fallos.append(f"{quien}: combinaciones visibles {visibles}, esperaba {esperado}")
        if sorted(p["teclas"]) != sorted(["Ctrl", tecla, "⌘", tecla]):
            fallos.append(f"{quien}: teclas rotuladas {p['teclas']}, esperaba Ctrl/{tecla} y ⌘/{tecla}")

        # Los nombres para el lector están en el árbol y miden 1 px, no display:none.
        for s in p["ocultos"]:
            if s["display"] == "none" or s["visibility"] == "hidden":
                continue  # la forma de la otra plataforma sí va display:none: es lo esperado
            if s["ancho"] > 1 or s["alto"] > 1:
                fallos.append(f"{quien}: el texto oculto «{s['texto']}» mide {s['ancho']}×{s['alto']} px, esperaba ≤ 1×1")
        expuestos = [s["texto"] for s in p["ocultos"] if s["display"] != "none"]
        if expuestos != [f"{'Comando' if mac else 'Control'} {tecla}"]:
            fallos.append(f"{quien}: textos expuestos al lector {expuestos}")

        # Contraste en reposo: texto del botón y de la tecla contra el fondo efectivo.
        r_txt = contraste(p["colorTexto"], p["fondo"])
        p["contrasteTexto"] = r_txt
        if r_txt < 4.5:
            fallos.append(f"{quien}: el texto del disparador está a {r_txt:.2f}:1 (mínimo 4.5:1)")
        r_kbd = contraste(p["colorTecla"], p["fondo"])
        p["contrasteTecla"] = r_kbd
        if r_kbd < 4.5:
            fallos.append(f"{quien}: la tecla está a {r_kbd:.2f}:1 (mínimo 4.5:1 aunque vaya aria-hidden)")
        p["contrasteBorde"] = contraste(p["bordeTecla"], p["fondo"])
        if p["sombraTecla"] != "none":
            fallos.append(f"{quien}: la tecla lleva sombra ({p['sombraTecla']}), la ficha pide tecla plana")
        if p["familiaTecla"] != d["mono"]:
            fallos.append(f"{quien}: la tecla computa {p['familiaTecla']!r} y el token mono es {d['mono']!r}")

        # Movimiento: con la preferencia la transición computa a 0 s, TAMBIÉN en el
        # panel; sin ella existe (si no, este chequeo no probaría nada).
        duraciones = [x.strip() for x in p["transicionDuracion"].split(",")]
        if movimiento == "reduce":
            if any(x != "0s" for x in duraciones) or p["transicionPropiedad"] != "none":
                fallos.append(
                    f"{quien}: con prefers-reduced-motion la transición sigue viva: "
                    f"{p['transicionPropiedad']} {p['transicionDuracion']}"
                )
        elif all(x == "0s" for x in duraciones):
            fallos.append(f"{quien}: sin la preferencia la transición ya está en 0 s: la comprobación sería vacía")

    # El nombre accesible, calculado por el navegador: excluye lo aria-hidden.
    nombre = "Comando" if mac else "Control"
    otro = "Control" if mac else "Comando"
    por_rol = pagina.locator("#paleta-k").get_by_role("button", name=re.compile(rf"^Buscar o ir a… {nombre} K$"))
    if por_rol.count() != 1:
        arbol = pagina.locator("#paleta-k button").first.aria_snapshot().strip()
        fallos.append(f"{donde} paleta-k: el nombre accesible no es «Buscar o ir a… {nombre} K»: {arbol!r}")
    if pagina.locator("#paleta-k").get_by_role("button", name=re.compile(rf"{otro} K|⌘|Ctrl")).count() != 0:
        fallos.append(f"{donde} paleta-k: el nombre accesible arrastra «{otro} K», el ⌘ o «Ctrl»")
    if pagina.locator("#paleta-p").get_by_role("button", name=re.compile(rf"^Ir a un trámite… {nombre} P$")).count() != 1:
        fallos.append(f"{donde} paleta-p: el nombre accesible no es «Ir a un trámite… {nombre} P»")

    # Teclado. Tab desde el enlace anterior cae en el disparador con foco visible.
    pagina.focus("#antes")
    pagina.keyboard.press("Tab")
    activo = pagina.evaluate("() => document.activeElement.className || document.activeElement.tagName")
    if activo != "muni-cmdk__trigger":
        fallos.append(f"{donde}: Tab no llega al disparador (activeElement={activo!r})")
    foco = pagina.evaluate(
        "() => { const cs = getComputedStyle(document.activeElement); return [cs.outlineStyle, cs.outlineWidth, cs.outlineColor]; }"
    )
    if foco[0] == "none" or foco[1] != "3px":
        fallos.append(f"{donde}: el foco no se ve por outline de 3 px ({foco})")
    else:
        fondo_k = next(p["fondo"] for p in d["paletas"] if p["id"] == "paleta-k" and p["botones"] == 1)
        r_foco = contraste(foco[2], fondo_k)
        d["contrasteFoco"] = r_foco
        if r_foco < 3:
            fallos.append(f"{donde}: el anillo de foco está a {r_foco:.2f}:1 (mínimo 3:1)")
    if capturas is not None:
        pagina.screenshot(path=str(capturas / f"{ruta.stem}-{movimiento}{'-mac' if mac else ''}-foco.png"),
                          clip={"x": 0, "y": 0, "width": 720, "height": 220})

    # Enter abre, el foco entra a la caja; Escape cierra y devuelve el foco al disparador.
    pagina.keyboard.press("Enter")
    if not _espera_caja_activa(pagina, "Buscar o ir a…"):
        fallos.append(f"{donde}: Enter no abre la paleta k con el foco en la caja de búsqueda")
    else:
        abierta = pagina.evaluate(SONDA_ABIERTA)
        if abierta is None:
            fallos.append(f"{donde}: no hay un diálogo visible tras Enter")
        else:
            if not abierta["escOculta"]:
                fallos.append(f"{donde}: la tecla «esc» del cuadro queda expuesta al lector")
            frases = [s["texto"] for s in abierta["sr"] if s["display"] != "none"]
            if frases != ["Escape cierra la paleta"]:
                fallos.append(f"{donde}: textos ocultos del cuadro {frases}, esperaba ['Escape cierra la paleta']")
            for s in abierta["sr"]:
                if s["display"] != "none" and (s["ancho"] > 1 or s["alto"] > 1):
                    fallos.append(f"{donde}: «{s['texto']}» mide {s['ancho']}×{s['alto']} px, esperaba ≤ 1×1")
        if capturas is not None:
            pagina.screenshot(path=str(capturas / f"{ruta.stem}-{movimiento}{'-mac' if mac else ''}-abierta.png"))
    pagina.keyboard.press("Escape")
    pagina.wait_for_function(
        "() => [...document.querySelectorAll('[role=dialog]')].every(d => getComputedStyle(d).display === 'none')"
    )
    try:
        pagina.wait_for_function(
            "() => document.activeElement && document.activeElement.classList.contains('muni-cmdk__trigger')", timeout=3000
        )
    except Exception:  # noqa: BLE001
        activo = pagina.evaluate("() => document.activeElement.className || document.activeElement.tagName")
        fallos.append(f"{donde}: Escape no devuelve el foco al disparador (activeElement={activo!r})")
    pagina.wait_for_timeout(150)

    # Seis aperturas seguidas por teclado. Es el detector de la carrera entre el
    # frame de x-show y el timer de 15 ms de x-trap: con la trampa armada sobre
    # `open` a secas, en headless una de cada tres aperturas dejaba el diálogo
    # abierto con el foco afuera. Con `trap` diferido tiene que ser 6 de 6.
    sin_foco = 0
    sin_devolucion = 0
    for _ in range(6):
        pagina.focus("#paleta-k button.muni-cmdk__trigger")
        pagina.keyboard.press("Enter")
        if not _espera_caja_activa(pagina, "Buscar o ir a…", timeout=1500):
            sin_foco += 1
        pagina.keyboard.press("Escape")
        pagina.wait_for_function(
            "() => [...document.querySelectorAll('[role=dialog]')].every(d => getComputedStyle(d).display === 'none')"
        )
        try:
            pagina.wait_for_function(
                "() => document.activeElement && document.activeElement.classList.contains('muni-cmdk__trigger')", timeout=1500
            )
        except Exception:  # noqa: BLE001
            sin_devolucion += 1
        pagina.wait_for_timeout(60)
    if sin_foco:
        fallos.append(f"{donde}: en {sin_foco} de 6 aperturas seguidas el foco NO entró al diálogo (carrera x-show/x-trap)")
    if sin_devolucion:
        fallos.append(f"{donde}: en {sin_devolucion} de 6 cierres seguidos el foco no volvió al disparador")

    # Ctrl+K abre la k; Ctrl+P la p; con Bloq Mayús (`key` en mayúscula) la k sigue abriendo.
    for combinacion, placeholder, como in (
        ("Control+k", "Buscar o ir a…", "tecla"),
        ("Control+p", "Ir a un trámite…", "tecla"),
        ("K", "Buscar o ir a…", "bloq-mayus"),
    ):
        pagina.focus("#antes")
        pagina.wait_for_timeout(80)
        if como == "tecla":
            pagina.keyboard.press(combinacion)
        else:
            pagina.evaluate(
                "(k) => window.dispatchEvent(new KeyboardEvent('keydown', {key: k, ctrlKey: true, bubbles: true, cancelable: true}))",
                combinacion,
            )
        if not _espera_caja_activa(pagina, placeholder):
            fallos.append(f"{donde}: {combinacion} ({como}) no abre la paleta con placeholder «{placeholder}»")
        _cierra(pagina)

    # Ancho de teléfono: sin desborde horizontal.
    pagina.set_viewport_size({"width": 390, "height": 800})
    pagina.wait_for_timeout(80)
    if not pagina.evaluate("() => document.documentElement.scrollWidth <= document.documentElement.clientWidth"):
        fallos.append(f"{donde}: la página desborda en horizontal a 390 px")
    if capturas is not None:
        pagina.screenshot(path=str(capturas / f"{ruta.stem}-{movimiento}{'-mac' if mac else ''}-movil.png"), full_page=True)

    return d


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("banco", nargs="?", default=str(BANCO_POR_DEFECTO))
    ap.add_argument("--navegadores", default="chromium", help="lista separada por comas: chromium,firefox")
    ap.add_argument("--capturas", help="carpeta donde dejar capturas (por defecto no se toman)")
    args = ap.parse_args(argv[1:])

    carpeta = Path(args.banco)
    paginas = sorted(carpeta.glob("*.html"))
    if len(paginas) < 4:
        print(f"Banco incompleto en {carpeta}: {len(paginas)} página(s); genera primero "
              "`vendor/bin/pest --filter=RotuloDelAtajo`.")
        return 2
    capturas = Path(args.capturas) if args.capturas else None
    if capturas is not None:
        capturas.mkdir(parents=True, exist_ok=True)

    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []
    with sync_playwright() as pw:
        for nombre in [n.strip() for n in args.navegadores.split(",") if n.strip()]:
            navegador = getattr(pw, nombre).launch()
            # Cada página con y sin la preferencia de movimiento, y una pasada
            # emulando Mac (`navigator.platform`, sin `userAgentData`) para la
            # forma ⌘: tres contextos, porque la detección corre al hidratar.
            for ruta in paginas:
                for movimiento, mac in [(m, False) for m in MOVIMIENTOS] + [("no-preference", True)]:
                    contexto = navegador.new_context(viewport={"width": 1440, "height": 900}, reduced_motion=movimiento)
                    if mac:
                        contexto.add_init_script(MAC)
                    pagina = contexto.new_page()
                    errores: list[str] = []
                    pagina.on("pageerror", lambda e, errores=errores: errores.append(str(e)))
                    pagina.on("console", lambda m, errores=errores: errores.append(m.text) if m.type == "error" else None)
                    pagina.goto(ruta.as_uri())
                    pagina.wait_for_function(
                        "() => window.Alpine && document.querySelector('#paleta-k') "
                        "&& window.Alpine.$data(document.querySelector('#paleta-k')).items.length === 3"
                    )
                    medida = verificar_pagina(pagina, ruta, movimiento, mac, fallos, capturas)
                    if errores:
                        fallos.append(f"{ruta.name} [{movimiento}{', mac' if mac else ''}]: errores de consola o de página: {errores[:3]}")
                    k = next((p for p in medida["paletas"] if p["id"] == "paleta-k" and p["botones"] == 1), None)
                    if k is not None:
                        etiqueta = f"{nombre}/{ruta.name:<18} [{movimiento}{', mac' if mac else ''}]"
                        if movimiento == "reduce":
                            resumen.append(
                                f"{etiqueta} transición(reduce)={k['transicionPropiedad']} {k['transicionDuracion']}"
                                f"  texto={k['contrasteTexto']:.2f}:1  tecla={k['contrasteTecla']:.2f}:1"
                                f"  borde={k['contrasteBorde']:.2f}:1  foco={medida.get('contrasteFoco', 0):.2f}:1"
                            )
                        else:
                            visibles = [c["texto"] for c in k["combos"] if c["visible"]]
                            resumen.append(
                                f"{etiqueta} transición(no-preference)={k['transicionPropiedad']} {k['transicionDuracion']}"
                                f"  visible={visibles}  alto={k['alto']:.0f}px"
                            )
                    contexto.close()
            navegador.close()

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa: {len(paginas)} páginas × {len(MOVIMIENTOS)} preferencias de movimiento + Mac, en {args.navegadores}.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
