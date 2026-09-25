#!/usr/bin/env python3
"""Mide desde afuera, con Livewire REAL, lo que hace <x-muni::busy-region>.

    .venv-a11y/bin/python tests/navegador/carga-anunciada-livewire.py            # levanta y apaga el servidor solo
    .venv-a11y/bin/python tests/navegador/carga-anunciada-livewire.py http://127.0.0.1:8765/   # contra uno ya servido

La aplicación es `tests/navegador/carga-anunciada-livewire.php` (Testbench + el
Livewire 4 del vendor) servida por `php -S`; la corre `tests/CargaAnunciadaTest.php`.
El banco estático (`carga-anunciada.py`) lee estilos computados y el árbol ARIA
sin Livewire; este ejercita `wire:loading` y el morph de verdad, en Chromium y
Firefox, y es lo que sostiene lo que el componente afirma en su fuente:

  · «buscador»: `aria-busy="true"` se pone al SALIR la petición y se quita al
    terminar; el indicador aparece a los ~300 ms (`.delay.long`) en la petición
    lenta y NO parpadea en la rápida; el resultado aterriza en el MISMO nodo
    role=status que existía antes (el lector lo venía observando), y el foco
    nunca sale del campo. Se imprime, informativo, el orden medido entre «quitar
    aria-busy» y «morph del resultado»: no es candado, porque la región vive
    fuera del contenedor ocupado justamente para no depender de él.
  · «perezoso»: nace ocupada con `:busy="$total === null"` y `wire:init`; al
    llegar el dato queda libre. Línea esperada: «busy atado al dato: se apagó».
  · «trampa»: `:busy="true"` FIJO queda ocupada para siempre, con el resultado
    ya pintado. Se mide para que la advertencia del componente siga siendo
    cierta. Línea esperada: «busy fijo: quedó pegado (la trampa documentada
    sigue vigente)». Si algún día deja de pegarse, falla: hay que reescribir la
    doc, no borrar la medición.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si no hay `php` o el
servidor no contesta.
"""
from __future__ import annotations

import json
import os
import shutil
import socket
import subprocess
import sys
import time
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
ROUTER = Path(__file__).with_suffix(".php")
LOG = RAIZ / "build" / "carga-anunciada" / "livewire-servidor.log"
NAVEGADORES = ("chromium", "firefox")
RESULTADO_PADRON = "Padrón generado: 3.412 patentes morosas"

# Corre ANTES que cualquier script de la página: guarda el nodo role=status de cada
# caso para comprobar, después del morph, que sigue siendo EL MISMO.
INICIO = """
document.addEventListener('DOMContentLoaded', () => {
  window.__nodosIniciales = {};
  for (const s of document.querySelectorAll('[data-caso]')) {
    window.__nodosIniciales[s.dataset.caso] = s.querySelector('.muni-busy [role="status"]');
  }
});
"""

OBSERVADOR = """() => {
  const raiz = document.querySelector('[data-caso="buscador"] .muni-busy');
  const cont = raiz.querySelector('.muni-busy__content');
  const status = raiz.querySelector('[role="status"]');
  const ind = raiz.querySelector('.muni-busy__loading');
  window.__contNode = cont;
  window.__t0 = performance.now();
  window.__linea = [];
  const log = (evento, extra) => window.__linea.push({ t: Math.round(performance.now() - window.__t0), evento, ...extra });
  new MutationObserver(records => {
    for (const r of records) {
      if (r.type === 'attributes' && r.target === cont && r.attributeName === 'aria-busy') {
        log('aria-busy', { valor: cont.getAttribute('aria-busy') });
      }
      if (r.type === 'attributes' && r.target === raiz && r.attributeName === 'class') {
        log('clase', { loading: raiz.classList.contains('muni-busy--loading'), display: getComputedStyle(ind).display });
      }
      if ((r.type === 'characterData' || r.type === 'childList') && status.contains(r.target)) {
        log('status', { texto: status.textContent.trim() });
      }
    }
  }).observe(raiz, { attributes: true, subtree: true, childList: true, characterData: true });
  return { rolePersistente: status.getAttribute('role'), textoInicial: status.textContent, indicadorDisplay: getComputedStyle(ind).display };
}"""

ESTADO = """caso => {
  const raiz = document.querySelector(`[data-caso="${caso}"] .muni-busy`);
  const cont = raiz.querySelector('.muni-busy__content');
  const status = raiz.querySelector('[role="status"]');
  return {
    ariaBusy: cont.getAttribute('aria-busy'),
    clase: raiz.classList.contains('muni-busy--loading'),
    status: status.textContent.trim(),
    mismoNodoStatus: status === (window.__nodosIniciales || {})[caso],
    mismoNodoCont: caso !== 'buscador' || cont === window.__contNode,
    foco: document.activeElement && document.activeElement.id,
    indicadorDisplay: getComputedStyle(raiz.querySelector('.muni-busy__loading')).display,
    parrafos: [...cont.querySelectorAll('li, p')].map(e => e.textContent.trim()),
  };
}"""


def puerto_libre() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def contesta(host: str, puerto: int) -> bool:
    try:
        with socket.create_connection((host, puerto), timeout=0.5):
            return True
    except OSError:
        return False


def servir() -> tuple[subprocess.Popen, str]:
    php = os.environ.get("PHP_BINARY") or shutil.which("php")
    if not php:
        raise RuntimeError("no hay `php` en el PATH ni PHP_BINARY")
    puerto = puerto_libre()
    LOG.parent.mkdir(parents=True, exist_ok=True)
    log = LOG.open("w")
    proc = subprocess.Popen(
        [php, "-d", "xdebug.mode=off", "-S", f"127.0.0.1:{puerto}", str(ROUTER)],
        stdout=log, stderr=log, cwd=str(RAIZ),
    )
    limite = time.monotonic() + 15
    while time.monotonic() < limite:
        if proc.poll() is not None:
            raise RuntimeError(f"php -S murió al arrancar (código {proc.returncode}); mira {LOG}")
        if contesta("127.0.0.1", puerto):
            return proc, f"http://127.0.0.1:{puerto}/"
        time.sleep(0.1)
    proc.terminate()
    raise RuntimeError(f"php -S no contestó en 15 s en el puerto {puerto}; mira {LOG}")


def seccion_html(html: str, caso: str) -> str:
    inicio = html.find(f'data-caso="{caso}"')
    fin = html.find("</section>", inicio)
    return html[inicio:fin] if inicio >= 0 else ""


def ciclo(pagina, valor: str, fallos: list[str], lento: bool, esperado: str) -> None:
    pagina.evaluate("() => { window.__linea = []; window.__t0 = performance.now(); }")
    pagina.fill("#rut", valor)
    pagina.wait_for_function(
        "e => document.querySelector('[data-caso=\"buscador\"] .muni-busy [role=\"status\"]').textContent.trim() === e"
        " && !document.querySelector('[data-caso=\"buscador\"] .muni-busy__content').hasAttribute('aria-busy')",
        arg=esperado,
        timeout=8000,
    )
    pagina.wait_for_timeout(150)
    linea = pagina.evaluate("() => window.__linea")
    estado = pagina.evaluate(ESTADO, "buscador")
    quien = f"rut={valor!r} ({'lento' if lento else 'rápido'})"
    print(f"  {quien}: {json.dumps(linea, ensure_ascii=False)}")
    print(f"    final: {json.dumps(estado, ensure_ascii=False)}")

    eventos = [e["evento"] + ":" + str(e.get("valor", e.get("loading", ""))) for e in linea]
    idx = lambda nombre: next((i for i, e in enumerate(eventos) if e == nombre), None)  # noqa: E731

    on = idx("aria-busy:true")
    off = idx("aria-busy:None")
    if on is None or off is None or on > off:
        fallos.append(f"{quien}: aria-busy no se puso y quitó en ese orden: {eventos}")
    st = next((i for i, e in enumerate(linea) if e["evento"] == "status" and e.get("texto") == esperado), None)
    if st is None:
        fallos.append(f"{quien}: el resultado nunca aterrizó en la región role=status")
    elif off is not None:
        # Informativo, no candado: el orden morph/apagado es detalle interno de
        # Livewire y la región vive FUERA del contenedor ocupado justamente para
        # no depender de él.
        print(f"    orden medido: {'aria-busy fuera → morph' if st > off else 'morph → aria-busy fuera'}"
              f" ({linea[st]['t'] - linea[off]['t']:+d} ms)")

    clase_on = idx("clase:True")
    if lento:
        if clase_on is None:
            fallos.append(f"{quien}: la petición tardó 700 ms y el indicador nunca se mostró")
        else:
            dt = linea[clase_on]["t"] - linea[on]["t"] if on is not None else None
            if dt is None or dt < 280:
                fallos.append(f"{quien}: el indicador apareció {dt} ms después de aria-busy; el retardo .delay.long son 300 ms")
            else:
                print(f"    retardo medido: {dt} ms entre aria-busy y el indicador (.delay.long = 300 ms)")
            if linea[clase_on].get("display") != "flex":
                fallos.append(f"{quien}: con la clase puesta el indicador computa display={linea[clase_on].get('display')!r}")
            if idx("clase:False") is None:
                fallos.append(f"{quien}: la clase de carga nunca se quitó")
    elif clase_on is not None:
        fallos.append(f"{quien}: la petición tardó 60 ms y el indicador parpadeó igual (la clase se puso en t={linea[clase_on]['t']} ms)")

    if estado["ariaBusy"] is not None or estado["clase"] or estado["indicadorDisplay"] != "none":
        fallos.append(f"{quien}: al terminar queda ocupado: {estado}")
    if estado["status"] != esperado:
        fallos.append(f"{quien}: la región dice {estado['status']!r}, esperaba {esperado!r}")
    if not estado["mismoNodoStatus"]:
        fallos.append(f"{quien}: el morph REEMPLAZÓ el nodo role=status: ya no es el que el lector venía observando")
    if not estado["mismoNodoCont"]:
        fallos.append(f"{quien}: el morph reemplazó el contenedor aria-busy")
    if estado["foco"] != "rut":
        fallos.append(f"{quien}: el foco se fue del campo durante la carga: activeElement={estado['foco']!r}")


def verificar_padrones(pagina, nombre: str, html_servidor: str, fallos: list[str]) -> None:
    # Los dos nacen ocupados desde el servidor: es lo que `busy` promete.
    for caso in ("perezoso", "trampa"):
        seccion = seccion_html(html_servidor, caso)
        if 'aria-busy="true"' not in seccion or "muni-busy--loading" not in seccion:
            fallos.append(f"[{nombre}] «{caso}» no nace ocupada en el HTML del servidor")

    pagina.wait_for_function(
        "e => ['perezoso', 'trampa'].every(c => document.querySelector(`[data-caso=\"${c}\"] .muni-busy [role=\"status\"]`).textContent.trim() === e)",
        arg=RESULTADO_PADRON,
        timeout=10000,
    )
    pagina.wait_for_timeout(300)

    perezoso = pagina.evaluate(ESTADO, "perezoso")
    print(f"[{nombre}] perezoso: {json.dumps(perezoso, ensure_ascii=False)}")
    if perezoso["clase"] or perezoso["ariaBusy"] is not None or perezoso["indicadorDisplay"] != "none":
        fallos.append(f"[{nombre}] «perezoso» con busy atado al dato sigue ocupada después del morph: {perezoso}")
    elif "3.412 patentes morosas" not in perezoso["parrafos"] or not perezoso["mismoNodoStatus"]:
        fallos.append(f"[{nombre}] «perezoso» quedó libre pero sin el contenido o con otro nodo role=status: {perezoso}")
    else:
        print(f"[{nombre}] busy atado al dato: se apagó")

    trampa = pagina.evaluate(ESTADO, "trampa")
    print(f"[{nombre}] trampa: {json.dumps(trampa, ensure_ascii=False)}")
    if trampa["clase"] and trampa["ariaBusy"] == "true" and trampa["indicadorDisplay"] == "flex":
        print(f"[{nombre}] busy fijo: quedó pegado (la trampa documentada sigue vigente)")
    else:
        fallos.append(
            f"[{nombre}] «trampa» con :busy=\"true\" fijo YA NO queda pegada ({trampa}): la advertencia del "
            "componente dejó de ser cierta y hay que reescribirla, no borrar esta medición"
        )


def medir(url: str) -> list[str]:
    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()
            pagina = navegador.new_page(viewport={"width": 1440, "height": 900})
            errores_js: list[str] = []
            pagina.on("pageerror", lambda e: errores_js.append(str(e)))
            pagina.on("console", lambda m: errores_js.append(m.text) if m.type == "error" else None)
            pagina.add_init_script(INICIO)

            html_servidor = pagina.request.get(url).text()
            pagina.goto(url)
            pagina.wait_for_function("() => window.Livewire && document.querySelectorAll('.muni-busy').length === 3", timeout=10000)

            inicial = pagina.evaluate(OBSERVADOR)
            print(f"[{nombre}] buscador inicial: {json.dumps(inicial, ensure_ascii=False)}")
            if inicial["rolePersistente"] != "status" or inicial["textoInicial"].strip() != "" or inicial["indicadorDisplay"] != "none":
                fallos.append(f"[{nombre}] estado inicial del buscador incorrecto: {inicial}")

            verificar_padrones(pagina, nombre, html_servidor, fallos)

            pagina.focus("#rut")
            ciclo(pagina, "lento", fallos, lento=True, esperado="1 persona encontrada")
            ciclo(pagina, "9lento", fallos, lento=True, esperado="Sin coincidencias para el RUT ingresado")
            ciclo(pagina, "1", fallos, lento=False, esperado="1 persona encontrada")
            ciclo(pagina, "9", fallos, lento=False, esperado="Sin coincidencias para el RUT ingresado")

            snap = pagina.locator('[data-caso="buscador"] .muni-busy').aria_snapshot()
            print(f"[{nombre}] árbol ARIA del buscador:\n" + "\n".join("    " + l for l in snap.strip().splitlines()))
            if "status" not in snap or "Sin coincidencias para el RUT ingresado" not in snap:
                fallos.append(f"[{nombre}] el árbol ARIA no expone la región status con el resultado: {snap!r}")
            if errores_js:
                fallos.append(f"[{nombre}] errores en consola: {errores_js}")
            navegador.close()
    return fallos


def main(argv: list[str]) -> int:
    proc = None
    try:
        if len(argv) > 1:
            url = argv[1]
        else:
            proc, url = servir()
            print(f"servidor: {url} (registro en {LOG})")
        fallos = medir(url)
    except RuntimeError as e:
        print(str(e))
        return 2
    finally:
        if proc is not None:
            proc.terminate()
            try:
                proc.wait(timeout=5)
            except subprocess.TimeoutExpired:
                proc.kill()

    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa en {' y '.join(NAVEGADORES)} con Livewire real.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
