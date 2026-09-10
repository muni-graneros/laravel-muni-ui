#!/usr/bin/env python3
"""Genera registry.json leyendo los componentes del disco.

Uso: scripts/gen-registry.py <raiz_del_repo> <salida.json>

O, más corto, desde la raíz del repo: npm run registro

Todo sale del archivo, no de un inventario que puede haber quedado viejo:
las props del bloque @props, los tokens por grep, Alpine por las directivas
presentes, y la descripción de la primera frase del comentario Blade del
componente. Así el registro no puede mentir sobre lo que hay.
"""
import json
import re
import subprocess
import sys
from pathlib import Path

RAIZ = Path(sys.argv[1])
SALIDA = Path(sys.argv[2])
DIR = RAIZ / "resources/views/components"

PLUGINS = {
    "x-trap": "focus",
    "x-anchor": "anchor",
    "x-collapse": "collapse",
    "$persist": "persist",
    "x-mask": "mask",
    "x-intersect": "intersect",
}


def bloque_props(src: str) -> str | None:
    """Devuelve el interior de @props([...]) equilibrando corchetes."""
    i = src.find("@props(")
    if i == -1:
        return None
    j = src.find("[", i)
    if j == -1:
        return None
    prof, dentro, esc, comilla = 0, False, False, ""
    for k in range(j, len(src)):
        c = src[k]
        if dentro:
            if esc:
                esc = False
            elif c == "\\":
                esc = True
            elif c == comilla:
                dentro = False
            continue
        if c in "'\"":
            dentro, comilla = True, c
        elif c == "[":
            prof += 1
        elif c == "]":
            prof -= 1
            if prof == 0:
                return src[j + 1:k]
    return None


def partir_nivel_superior(txt: str) -> list[str]:
    """Corta por comas que no estén dentro de corchetes, paréntesis ni comillas."""
    partes, act, prof, dentro, esc, comilla = [], [], 0, False, False, ""
    for c in txt:
        if dentro:
            act.append(c)
            if esc:
                esc = False
            elif c == "\\":
                esc = True
            elif c == comilla:
                dentro = False
            continue
        if c in "'\"":
            dentro, comilla = True, c
            act.append(c)
        elif c in "[(":
            prof += 1
            act.append(c)
        elif c in ")]":
            prof -= 1
            act.append(c)
        elif c == "," and prof == 0:
            partes.append("".join(act))
            act = []
        else:
            act.append(c)
    if "".join(act).strip():
        partes.append("".join(act))
    return partes


def tipo_de(valor: str) -> str:
    v = valor.strip()
    if v in ("null",):
        return "mixed"
    if v in ("true", "false"):
        return "bool"
    if v.startswith("["):
        return "array"
    if re.fullmatch(r"-?\d+", v):
        return "int"
    if re.fullmatch(r"-?\d*\.\d+", v):
        return "float"
    if v.startswith(("'", '"')):
        return "string"
    return "mixed"


def limpiar(v: str) -> str:
    v = v.strip()
    if len(v) >= 2 and v[0] == v[-1] and v[0] in "'\"":
        return v[1:-1]
    return v


def props_de(src: str) -> list[dict]:
    interior = bloque_props(src)
    if interior is None:
        return []
    # Los comentarios de bloque traen comas y «=>» que rompen el troceo por comas
    # de nivel superior: se quitan ANTES de partir, no después de cada trozo.
    interior = re.sub(r"/\*.*?\*/", "", interior, flags=re.S)
    fuera = []
    for parte in partir_nivel_superior(interior):
        # Quitar comentarios de línea dentro del bloque.
        parte = re.sub(r"//[^\n]*", "", parte).strip()
        if not parte:
            continue
        if "=>" in parte:
            izq, der = parte.split("=>", 1)
            nombre, defecto, obligatoria = limpiar(izq), der.strip(), False
        else:
            nombre, defecto, obligatoria = limpiar(parte), None, True
        if not nombre or not re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]*", nombre):
            continue
        fuera.append({
            "name": nombre,
            "type": "mixed" if obligatoria else tipo_de(defecto or ""),
            "default": None if obligatoria else limpiar(defecto or ""),
            "required": obligatoria,
        })
    return fuera


def descripcion_de(src: str, nombre: str) -> str:
    """Primera frase del primer comentario Blade que no sea una nota de licencia."""
    for m in re.finditer(r"\{\{--(.*?)--\}\}", src, re.S):
        txt = " ".join(m.group(1).split())
        if len(txt) < 15:
            continue
        frase = re.split(r"(?<=[.:])\s", txt)[0].strip().rstrip(".")
        if frase:
            return frase[:240]
    return f"Componente {nombre} del sistema de diseño municipal"


def slots_de(src: str) -> list[dict]:
    slots = []
    if re.search(r"\{\{\s*\$slot\s*\}\}", src) or "$slot->isEmpty()" in src:
        slots.append({"name": "default", "description": "Contenido principal"})
    for m in sorted(set(re.findall(r"\$(\w+)\s*\?\?|@isset\(\s*\$(\w+)\s*\)|isset\(\s*\$(\w+)\s*\)", src))):
        nombre = next((x for x in m if x), None)
        if nombre and nombre not in ("slot", "attributes") and not any(s["name"] == nombre for s in slots):
            slots.append({"name": nombre, "description": "Slot con nombre opcional"})
    return slots


componentes = []
for f in sorted(DIR.glob("*.blade.php")):
    # Ojo: Path.stem sobre «skip-link.blade.php» deja «skip-link.blade».
    nombre = f.name[: -len(".blade.php")]
    src = f.read_text(encoding="utf-8")
    directivas = sorted(set(re.findall(r"\b(x-[a-z-]+)", src)))
    usa_alpine = bool(directivas) or "$dispatch" in src
    plugins = sorted({p for d, p in PLUGINS.items() if d in src})
    props = props_de(src)
    ejemplo = "<x-muni::" + nombre
    for p in props[:2]:
        if p["required"]:
            ejemplo += f' :{p["name"]}="${p["name"]}"'
        elif p["type"] == "string" and p["default"]:
            ejemplo += f' {p["name"]}="{p["default"]}"'
    ejemplo += " />" if not any(s["name"] == "default" for s in slots_de(src)) else f"></x-muni::{nombre}>"

    componentes.append({
        "name": nombre,
        "tag": f"<x-muni::{nombre}>",
        "type": "registry:blade-component",
        "file": str(f.relative_to(RAIZ)),
        "description": descripcion_de(src, nombre),
        "props": props,
        "slots": slots_de(src),
        "alpine": {"required": usa_alpine, "directives": directivas, "plugins": plugins},
        "tokens": sorted(set(re.findall(r"--muni-[a-z0-9-]+", src))),
        "example": ejemplo,
    })

version = subprocess.run(["git", "-C", str(RAIZ), "describe", "--tags", "--abbrev=0"],
                         capture_output=True, text=True).stdout.strip().lstrip("v") or "0.0.0"

registro = {
    "$schema": "https://muni-graneros.github.io/laravel-muni-ui/registry.schema.json",
    "name": "laravel-muni-ui",
    "homepage": "https://github.com/muni-graneros/laravel-muni-ui",
    "baseVersion": version,
    "namespace": "muni",
    "usage": "Los componentes se usan como <x-muni::NOMBRE>. No requieren registro manual: el service provider del paquete declara el namespace.",
    "rules": [
        "Nunca escribir un color literal ni una clase de Tailwind de color dentro de una vista: usar los componentes y los tokens --muni-*.",
        "Toda regla de color clara necesita contraparte oscura, o debe salir de un token con ambas ramas.",
        "El indicador de foco es un outline con var(--muni-focus), no una box-shadow: dentro de Filament la sombra se pierde.",
        "El movimiento usa var(--muni-dur), que baja a 0ms con prefers-reduced-motion.",
        "Los componentes deben funcionar en Livewire 3 y 4: nada de @island, wire:show, wire:sort ni #[Transition].",
        "Antes de cerrar una pantalla: npm run a11y:vitrina, que mide los componentes reales en claro y oscuro. npm run a11y suma las demos, que hoy arrastran deuda conocida.",
    ],
    "components": componentes,
}

SALIDA.write_text(json.dumps(registro, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
con_alpine = sum(1 for c in componentes if c["alpine"]["required"])
con_plugin = sum(1 for c in componentes if c["alpine"]["plugins"])
sin_props = sum(1 for c in componentes if not c["props"])
print(f"OK → {SALIDA} · {len(componentes)} componentes · {con_alpine} con Alpine · "
      f"{con_plugin} con plugin · {sin_props} sin props")
