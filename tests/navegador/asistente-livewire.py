#!/usr/bin/env python3
"""Mide con Livewire REAL adónde va el foco al cambiar de paso en <x-muni::asistente>.

    .venv-a11y/bin/python tests/navegador/asistente-livewire.py            # levanta y apaga el servidor solo
    .venv-a11y/bin/python tests/navegador/asistente-livewire.py http://127.0.0.1:8766/

La aplicación es `tests/navegador/asistente-livewire.php` (Testbench + el Livewire 4
del vendor) servida por `php -S`; la corre `tests/AsistenteDePasosTest.php`.

Por qué existe: el banco estático (`asistente.py`) mide la CARGA de una página, y
ahí el foco lo mueve `autofocus`. Dentro de Livewire no hay carga: hay un morph que
CONSERVA el nodo del encabezado si su clave no cambia, y un `x-init` conservado no
vuelve a correr. El revisor lo midió: del paso 1 al 2 el foco llegaba, del 2 al 3
se quedaba en «Siguiente». Acá se recorre el trámite entero, en Chromium y Firefox,
y en cada cambio de paso se lee `document.activeElement`:

  · carga en el paso 1: el foco NO se mueve (el enlace «Saltar al contenido»).
  · Enter en un campo del paso 1 → paso 2, foco en «Antecedentes médicos». Enter es
    «envía el paso»: tiene que avanzar, no retroceder por el indicador.
  · «Siguiente» con el médico vacío → el servidor devuelve error: el foco es del
    resumen de errores, no del encabezado.
  · Enter en el campo ya corregido → paso 3 (el caso que fallaba).
  · Siguiente (Espacio) → 4, Omitir → 5, Anterior → 4.
  · el indicador, paso «Identificación» → vuelta al PRIMER paso: el foco también
    va al encabezado, porque ahora sí hubo un cambio de paso de verdad.
  · y nunca hubo un POST nativo: `type="button"` por `next-attrs` se respeta, y el
    botón por defecto que atiende Enter no recarga la página.

Devuelve 0 si todo pasa; 1 con la lista de fallos; 2 si no hay `php` o el servidor
no contesta.
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
LOG = RAIZ / "build" / "asistente" / "livewire-servidor.log"
NAVEGADORES = ("chromium", "firefox")

FOCO = """() => {
  const a = document.activeElement;
  if (!a || a === document.body) return { que: 'body', clase: '', texto: '' };
  return { que: a.tagName.toLowerCase(), clase: a.getAttribute('class') || '', texto: (a.textContent || '').trim().replace(/\\s+/g, ' ').slice(0, 60) };
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


def esperar_paso(pagina, titulo: str) -> None:
    pagina.wait_for_function(
        "t => { const h = document.querySelector('.muni-asis__paso-titulo'); return h && h.textContent.trim() === t; }",
        arg=titulo,
        timeout=8000,
    )
    # El `$nextTick` del encabezado corre después del morph.
    pagina.wait_for_timeout(250)


def comprobar_foco_en_paso(pagina, donde: str, titulo: str, fallos: list[str]) -> str:
    foco = pagina.evaluate(FOCO)
    if foco["clase"].startswith("muni-asis__paso-titulo") and foco["texto"] == titulo:
        return "foco=paso"
    fallos.append(
        f"{donde}: al llegar a «{titulo}» el foco quedó en «{foco['que']} {foco['clase']} {foco['texto']!r}» y tiene que "
        "estar en el encabezado del paso nuevo (2.4.3)"
    )
    return "foco=perdido"


def medir(url: str) -> tuple[list[str], list[str]]:
    from playwright.sync_api import sync_playwright

    fallos: list[str] = []
    resumen: list[str] = []

    with sync_playwright() as pw:
        for nombre in NAVEGADORES:
            navegador = getattr(pw, nombre).launch()
            pagina = navegador.new_page(viewport={"width": 1440, "height": 900})
            # Un paso que no llega falla rápido: con los 30 s de fábrica, un envío
            # nativo que recarga la página deja el recorrido colgado varios minutos.
            pagina.set_default_timeout(8000)
            errores_js: list[str] = []
            pagina.on("pageerror", lambda e: errores_js.append(str(e)))
            pagina.on("console", lambda m: errores_js.append(m.text) if m.type == "error" else None)

            pagina.goto(url)
            pagina.wait_for_function("() => window.Livewire && document.querySelector('form.muni-asis')", timeout=10000)
            pagina.wait_for_timeout(300)
            # Si algo hace un POST nativo, la página se recarga y esta marca desaparece.
            pagina.evaluate("() => { window.__sinRecarga = true; }")

            def paso(etiqueta: str, accion, titulo: str, espera_error: bool = False) -> None:
                donde = f"[{nombre}] {etiqueta}"
                try:
                    accion()
                    if espera_error:
                        pagina.wait_for_selector("form.muni-asis .muni-errsum", timeout=8000)
                        pagina.wait_for_timeout(250)
                        foco = pagina.evaluate(FOCO)
                        marca = "foco=resumen" if "muni-errsum" in foco["clase"] else "foco=perdido"
                        if marca != "foco=resumen":
                            fallos.append(
                                f"{donde}: con error del servidor el foco quedó en «{foco['que']} {foco['clase']}» y es del resumen"
                            )
                    else:
                        esperar_paso(pagina, titulo)
                        marca = comprobar_foco_en_paso(pagina, donde, titulo, fallos)
                except Exception as e:  # noqa: BLE001 — un paso que no llega es un fallo, no un aborto
                    fallos.append(f"{donde}: no llegó a «{titulo}»: {str(e).splitlines()[0]}")
                    marca = "paso=no-llegó"
                try:
                    recargo = not pagina.evaluate("() => window.__sinRecarga === true")
                except Exception:  # noqa: BLE001 — la navegación en curso destruye el contexto
                    recargo = True
                if recargo:
                    fallos.append(f"{donde}: la página se RECARGÓ: hubo un envío nativo del formulario")
                    pagina.wait_for_load_state()
                    pagina.evaluate("() => { window.__sinRecarga = true; }")
                resumen.append(f"{donde}: {marca}")

            foco = pagina.evaluate(FOCO)
            if foco["que"] != "body":
                fallos.append(f"[{nombre}] carga en el paso 1: el componente robó el foco («{foco['que']} {foco['clase']}»)")
            resumen.append(f"[{nombre}] carga en el paso 1: {'foco=intacto' if foco['que'] == 'body' else 'foco=robado'}")

            def enter_en(selector: str, texto: str):
                def hacer():
                    pagina.focus(selector)
                    pagina.keyboard.type(texto)
                    pagina.keyboard.press("Enter")
                return hacer

            def tecla_en(selector: str, tecla: str):
                def hacer():
                    pagina.focus(selector)
                    pagina.keyboard.press(tecla)
                return hacer

            paso("Enter en «Nombre completo» (1→2)", enter_en("#muni-nombre", "Ana Soto Miranda"), "Antecedentes médicos")
            paso("Siguiente con el médico vacío", tecla_en(".muni-asis__btn--pri", "Enter"), "Antecedentes médicos", espera_error=True)
            paso("Enter en el campo corregido (2→3)", enter_en("#muni-medico", "Dra. Pérez"), "Documentos")
            paso("Siguiente con Espacio (3→4)", tecla_en(".muni-asis__btn--pri", "Space"), "Declaración")
            paso("Omitir (4→5)", tecla_en(".muni-asis__btn[value=omitir]", "Enter"), "Revisión")
            paso("Anterior (5→4)", tecla_en(".muni-asis__btn[value=anterior]", "Enter"), "Declaración")
            paso("Indicador → Identificación (4→1)", tecla_en(".muni-step__ctrl", "Enter"), "Identificación")

            if errores_js:
                fallos.append(f"[{nombre}] errores en consola: {errores_js}")
            navegador.close()

    return fallos, resumen


def main(argv: list[str]) -> int:
    proc = None
    try:
        if len(argv) > 1:
            url = argv[1]
        else:
            proc, url = servir()
            print(f"servidor: {url} (registro en {LOG})")
        fallos, resumen = medir(url)
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

    print("\n".join(resumen))
    if fallos:
        print(f"\n{len(fallos)} fallo(s):")
        print("\n".join("  - " + f for f in fallos))
        return 1
    print(f"\nTodo pasa en {' y '.join(NAVEGADORES)} con Livewire real.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
