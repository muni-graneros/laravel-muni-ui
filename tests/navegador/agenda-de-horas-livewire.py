#!/usr/bin/env python3
"""Verificación de `<x-muni::agenda-horas>` DENTRO de Livewire real.

    .venv-a11y/bin/python tests/navegador/agenda-de-horas-livewire.py                         # levanta y apaga php -S
    .venv-a11y/bin/python tests/navegador/agenda-de-horas-livewire.py http://127.0.0.1:8766/  # contra uno ya servido

La corre `tests/AgendaDeHorasTest.php`. El banco estático (`agenda-de-horas.py`)
no puede ver lo que hace el morph de Livewire, y el revisor refutó justo eso: que
el mes «se sincroniza con wire:model» (falso) y que un repintado deja la agenda
coherente. Se mide, en Chromium y Firefox, con el montaje del README:

  1. Enter sobre el 12 y Espacio sobre la 09:00: `wire:model.live` lleva la hora al
     servidor y, DESPUÉS del morph, el panel del 12 sigue visible, la hora sigue
     marcada y el foco sigue en ella.
  2. Enter sobre el 13: `muni-agenda:dia` lleva el día y la hora vuelve vacía al
     servidor (x-modelable); tras el morph el panel del 13 es el único visible y
     no queda hora marcada.
  3. AvPág: la rejilla queda aria-busy con «Cargando junio de 2026…» mientras el
     servidor tarda; al llegar junio, 30 días, UNA parada de tabulación, el foco
     dentro de la rejilla nueva, sin aria-busy ni «Cargando», y las flechas
     siguen moviendo el foco.
  4. En junio, Enter sobre el 3 lleva el foco a su única hora libre.
  5. Un anfitrión que NO sincroniza el día (solo `wire:model.live`): la respuesta
     repinta con `value` en el 12 y el panel visible sigue siendo el del 13, donde
     está la hora. Con `p.hidden = …` imperativo el morph devolvía el del 12.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si no hay servidor.
"""
from __future__ import annotations

import os
import shutil
import socket
import subprocess
import sys
import time
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent.parent
ROUTER = Path(__file__).with_suffix(".php")
LOG = RAIZ / "build" / "agenda-horas" / "livewire-servidor.log"
NAVEGADORES = ("chromium", "firefox")

ESTADO = """(caso) => {
  caso = caso || 'completo';
  const s = document.querySelector(`[data-caso="${caso}"]`);
  const r = s.querySelector('.muni-ag');
  const g = r.querySelector('table[role=grid]');
  const a = document.activeElement;
  const c = r.querySelector('.muni-ag__cargando');
  return {
    eco: s.querySelector('output').textContent.trim(),
    titulo: r.querySelector('.muni-ag__titulo').textContent.trim(),
    dias: g.querySelectorAll('[data-celda]').length,
    paradas: [...g.querySelectorAll('[data-celda]')].filter(b => b.getAttribute('tabindex') === '0').map(b => b.dataset.celda),
    busy: g.getAttribute('aria-busy'),
    cargando: getComputedStyle(c).display !== 'none' ? c.textContent.trim() : null,
    paneles: [...r.querySelectorAll('[data-panel]')].filter(p => getComputedStyle(p).display !== 'none').map(p => p.dataset.panel),
    marcados: [...r.querySelectorAll('[data-franja]')].filter(x => x.checked).map(x => x.value),
    celda: a && a.dataset ? (a.dataset.celda || null) : null,
    radio: a && a.type === 'radio' ? a.value : null,
    enRejilla: g.contains(a),
  };
}"""


def puerto_libre() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def contesta(puerto: int) -> bool:
    try:
        with socket.create_connection(("127.0.0.1", puerto), timeout=0.5):
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
        if contesta(puerto):
            return proc, f"http://127.0.0.1:{puerto}/"
        time.sleep(0.1)
    proc.terminate()
    raise RuntimeError(f"php -S no contestó en 15 s en el puerto {puerto}; mira {LOG}")


def esperar_eco(page, eco: str, caso: str = "completo") -> None:
    page.wait_for_function(
        "([c, e]) => document.querySelector(`[data-caso=\"${c}\"] output`).textContent.trim() === e", arg=[caso, eco], timeout=8000
    )
    page.wait_for_timeout(150)  # el morph y los $nextTick de Alpine


def a_la_rejilla(page) -> None:
    for _ in range(6):
        if page.evaluate("() => !!(document.activeElement && document.activeElement.dataset && document.activeElement.dataset.celda)"):
            return
        page.keyboard.press("Shift+Tab")


def medir(url: str) -> list[str]:
    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()
            page = navegador.new_page(viewport={"width": 1440, "height": 900})
            errores: list[str] = []
            page.on("pageerror", lambda e: errores.append(str(e)))
            page.on("console", lambda m: errores.append(m.text) if m.type == "error" else None)
            page.goto(url)
            page.wait_for_function("() => window.Livewire && window.Alpine && document.querySelector('.muni-ag')", timeout=10000)
            esperar_eco(page, "2026-05|2026-05-12|")

            # 1. La hora del 12 viaja por wire:model.live y el morph no la deshace.
            page.focus('[data-caso="completo"] .muni-ag__nav')
            for _ in range(4):
                page.keyboard.press("Tab")
                if page.evaluate(ESTADO)["celda"]:
                    break
            page.keyboard.press("Enter")
            try:
                page.wait_for_function("() => document.activeElement && document.activeElement.type === 'radio'", timeout=3000)
            except Exception:
                fallos.append(f"[{nombre}] Enter sobre el 12 no llevó el foco a las horas: {page.evaluate(ESTADO)} {errores}")
                navegador.close()
                continue
            page.keyboard.press("Space")
            esperar_eco(page, "2026-05|2026-05-12|2026-05-12T09:00")
            e = page.evaluate(ESTADO)
            if e["paneles"] != ["2026-05-12"] or e["marcados"] != ["2026-05-12T09:00"] or e["radio"] != "2026-05-12T09:00":
                fallos.append(f"[{nombre}] tras el morph de wire:model.live la hora del 12 no quedó visible, marcada y con el foco: {e}")
            else:
                print(f"[{nombre}] hora por wire:model.live: ok")

            # 2. Cambiar al 13 suelta la hora, en el cliente Y en el servidor.
            a_la_rejilla(page)
            page.keyboard.press("ArrowRight")
            page.keyboard.press("Enter")
            esperar_eco(page, "2026-05|2026-05-13|")
            e = page.evaluate(ESTADO)
            if e["paneles"] != ["2026-05-13"] or e["marcados"] or e["radio"] != "2026-05-13T09:30":
                fallos.append(f"[{nombre}] tras cambiar al 13 y el morph, quedó {e}")
            else:
                print(f"[{nombre}] cambio de día sincronizado: ok")

            # 3. AvPág pide junio y la agenda queda ocupada hasta que llega.
            a_la_rejilla(page)
            page.keyboard.press("PageDown")
            carga = page.evaluate(ESTADO)
            if carga["busy"] != "true" or carga["cargando"] != "Cargando junio de 2026…":
                fallos.append(f"[{nombre}] mientras el servidor trae junio la rejilla no quedó ocupada: {carga}")
            esperar_eco(page, "2026-06||")
            e = page.evaluate(ESTADO)
            if (e["titulo"] != "junio 2026" or e["dias"] != 30 or e["paradas"] != ["2026-06-01"] or e["busy"] is not None
                    or e["cargando"] is not None or e["celda"] != "2026-06-01" or e["paneles"] or e["marcados"]):
                fallos.append(f"[{nombre}] junio llegó incoherente (una parada en el 1, el foco ahí, sin carga): {e}")
            else:
                page.keyboard.press("ArrowRight")
                page.keyboard.press("ArrowRight")
                e = page.evaluate(ESTADO)
                if e["celda"] != "2026-06-03" or e["paradas"] != ["2026-06-03"]:
                    fallos.append(f"[{nombre}] en junio las flechas no siguen al tabindex itinerante: {e}")
                else:
                    print(f"[{nombre}] cambio de mes por muni-agenda:mes: ok")

                    # 4. El panel del mes nuevo funciona.
                    page.keyboard.press("Enter")
                    esperar_eco(page, "2026-06|2026-06-03|")
                    e = page.evaluate(ESTADO)
                    if e["paneles"] != ["2026-06-03"] or e["radio"] != "2026-06-03T11:00":
                        fallos.append(f"[{nombre}] en junio Enter sobre el 3 no llevó el foco a su hora: {e}")
                    else:
                        print(f"[{nombre}] día del mes nuevo: ok")

            # 5. Un anfitrión que no sincroniza el día: la respuesta de wire:model.live
            #    repinta con `value` en el 12, y el panel visible tiene que seguir
            #    siendo el del 13, donde está la hora marcada.
            page.focus('[data-caso="sin-dia"] [data-celda="2026-05-12"]')
            page.keyboard.press("ArrowRight")
            page.keyboard.press("Enter")
            page.wait_for_function("() => document.activeElement && document.activeElement.type === 'radio'", timeout=3000)
            page.keyboard.press("Space")
            esperar_eco(page, "2026-05-13T09:30", "sin-dia")
            page.wait_for_timeout(200)
            e = page.evaluate(ESTADO, "sin-dia")
            if e["paneles"] != ["2026-05-13"] or e["marcados"] != ["2026-05-13T09:30"] or e["radio"] != "2026-05-13T09:30":
                fallos.append(f"[{nombre}] sin sincronizar el día, el morph devolvió el panel del servidor y dejó la hora en un panel oculto: {e}")
            else:
                print(f"[{nombre}] morph sin sincronizar el día: ok")

            if errores:
                fallos.append(f"[{nombre}] errores en consola: {errores}")
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
    print(f"\nLa agenda pasa con Livewire real en {' y '.join(NAVEGADORES)}.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
