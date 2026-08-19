# Ámbar de aviso y título de auth-shell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el color de aviso pase WCAG 2.2 AA en modo claro y que `auth-shell` produzca un `<title>` limpio, con una prueba que impida que ambos defectos vuelvan.

**Architecture:** El paquete no tiene todavía carpeta `tests/`, aunque `composer.json` ya declara `orchestra/testbench` y el espacio de nombres `Muni\Ui\Tests\`. La primera tarea monta esa infraestructura junto con la prueba de contraste; la segunda arregla el título. El cálculo de contraste se hace en PHP, sobre los valores leídos del CSS, para que la prueba falle sola si alguien cambia un color.

**Tech Stack:** PHP 8.3, Orchestra Testbench 9/10, PHPUnit, Blade, CSS con variables.

**Spec:** No hay spec: son dos defectos medidos el 2026-08-18. Las mediciones están en «Contexto».

## Global Constraints

- Umbral: **4.5:1** para texto, conforme a WCAG 2.2 AA. Obligatorio en sistemas del Estado por el Decreto N°1 de 2015 de SEGPRES.
- El modo oscuro **no se toca**: `#f59e0b` sobre `#2d1d00` da 7.6:1 y está correcto.
- El color de aviso debe seguir leyéndose como ámbar o tierra. No se sustituye por rojo ni por café: el color distingue «aviso» de «error» a simple vista.
- El color no puede ser el único portador de información: si un componente indica estado solo por color, se anota, pero arreglarlo no es parte de este plan.
- Commits en español, sin atribución a ninguna IA, bajo la cuenta `buguenocesar92`.

## Contexto

**Defecto 1 — el ámbar de aviso no pasa AA en claro.** Medido sobre `resources/css/muni-ui.css`:

| Modo | Selector | Primer plano | Fondo | Ratio | Veredicto |
|---|---|---|---|---|---|
| Claro | `:root` (línea 65) | `#c47a10` | `#fef3dc` | 3.11:1 | falla |
| Claro | `[data-muni-theme="light"]` (línea 199) | `#c47a10` | `#fef3dc` | 3.11:1 | falla |
| Oscuro | `@media (prefers-color-scheme: dark)` (116) | `#f59e0b` | `#2d1d00` | 7.60:1 | pasa |
| Oscuro | `.dark` (159) | `#f59e0b` | `#2d1d00` | 7.60:1 | pasa |

Candidatos calculados sobre el mismo fondo `#fef3dc`:

| Candidato | Ratio |
|---|---|
| `#a8650b` | 4.21:1 — insuficiente |
| `#96590a` | 5.11:1 — **elegido** |
| `#8a5209` | 5.79:1 — más oscuro de lo necesario |

Se elige `#96590a`: es el más claro de los que superan 4.5:1 con margen, así que conserva el carácter ámbar y no se confunde con el café.

`--muni-warn-fg` lo consume `resources/views/components/stat.blade.php:14`, entre otros.

**Defecto 2 — el título de `auth-shell`.** En `resources/views/components/auth-shell.blade.php:14-15`:

```blade
    <title>    <x-muni::reverb-meta />
{{ $title }} · {{ $system }}</title>
```

El componente `reverb-meta` se expande **dentro** del elemento `<title>`. Como `<title>` solo admite texto, el navegador se traga las etiquetas que salgan de ahí y el título termina con espacios de más o con contenido pegado. Las etiquetas `<meta>` que `reverb-meta` debería aportar tampoco quedan donde corresponde.

## File Structure

| Archivo | Responsabilidad |
|---|---|
| `phpunit.xml` (crear) | Configuración mínima de la suite |
| `tests/TestCase.php` (crear) | Caso base de Testbench que registra `MuniUiServiceProvider` |
| `tests/Contraste.php` (crear) | Cálculo de luminancia relativa y ratio, reutilizable |
| `tests/ContrasteTest.php` (crear) | Comprueba los pares de color del CSS contra el umbral |
| `tests/AuthShellTest.php` (crear) | Comprueba el `<title>` renderizado |
| `resources/css/muni-ui.css` (modificar, líneas 65 y 199) | Color de aviso en los dos bloques claros |
| `resources/views/components/auth-shell.blade.php` (modificar, 14-15) | Título y ubicación de `reverb-meta` |

---

### Task 1: Prueba de contraste y arreglo del ámbar

**Files:**
- Create: `phpunit.xml`, `tests/TestCase.php`, `tests/Contraste.php`, `tests/ContrasteTest.php`
- Modify: `resources/css/muni-ui.css:65`, `resources/css/muni-ui.css:199`

**Interfaces:**
- Consumes: nada de tareas anteriores.
- Produces: `Muni\Ui\Tests\TestCase` (caso base, usado por la Tarea 2) y `Muni\Ui\Tests\Contraste::ratio(string $fg, string $bg): float`.

- [ ] **Step 1: Crear la configuración de la suite**

Crear `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         colors="true">
    <testsuites>
        <testsuite name="muni-ui">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Crear `tests/TestCase.php`:

```php
<?php

namespace Muni\Ui\Tests;

use Muni\Ui\MuniUiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MuniUiServiceProvider::class];
    }
}
```

- [ ] **Step 2: Escribir el cálculo de contraste**

Crear `tests/Contraste.php`. Es la fórmula de luminancia relativa de WCAG 2.1, sin dependencias:

```php
<?php

namespace Muni\Ui\Tests;

class Contraste
{
    /**
     * Razón de contraste entre dos colores hexadecimales, según WCAG 2.1.
     * Devuelve un número entre 1 (idénticos) y 21 (negro sobre blanco).
     */
    public static function ratio(string $fg, string $bg): float
    {
        $l1 = self::luminancia($fg);
        $l2 = self::luminancia($bg);

        $claro = max($l1, $l2);
        $oscuro = min($l1, $l2);

        return round(($claro + 0.05) / ($oscuro + 0.05), 2);
    }

    private static function luminancia(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $canales = array_map(
            static function (string $par): float {
                $c = hexdec($par) / 255;

                return $c <= 0.04045
                    ? $c / 12.92
                    : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2)
        );

        return 0.2126 * $canales[0] + 0.7152 * $canales[1] + 0.0722 * $canales[2];
    }
}
```

- [ ] **Step 3: Escribir la prueba que falla**

Crear `tests/ContrasteTest.php`. Lee los valores del CSS real, no una copia: si alguien cambia el color, la prueba lo ve.

```php
<?php

namespace Muni\Ui\Tests;

class ContrasteTest extends TestCase
{
    private function variable(string $nombre, string $bloque): string
    {
        $css = file_get_contents(__DIR__.'/../resources/css/muni-ui.css');
        $desde = strpos($css, $bloque);

        $this->assertNotFalse($desde, "No se encontró el bloque «{$bloque}» en muni-ui.css");

        $trozo = substr($css, $desde, 3000);

        preg_match('/'.preg_quote($nombre, '/').':\s*(#[0-9a-fA-F]{3,6})/', $trozo, $m);

        $this->assertNotEmpty($m, "No se encontró «{$nombre}» dentro de «{$bloque}»");

        return $m[1];
    }

    public function test_el_aviso_pasa_AA_en_el_tema_claro_por_omision(): void
    {
        $fg = $this->variable('--muni-warn-fg', ':root {');
        $bg = $this->variable('--muni-warn-bg', ':root {');

        $this->assertGreaterThanOrEqual(
            4.5,
            Contraste::ratio($fg, $bg),
            "El aviso en claro ({$fg} sobre {$bg}) no alcanza 4.5:1"
        );
    }

    public function test_el_aviso_pasa_AA_en_el_tema_claro_explicito(): void
    {
        $fg = $this->variable('--muni-warn-fg', '[data-muni-theme="light"]');
        $bg = $this->variable('--muni-warn-bg', '[data-muni-theme="light"]');

        $this->assertGreaterThanOrEqual(
            4.5,
            Contraste::ratio($fg, $bg),
            "El aviso en claro explícito ({$fg} sobre {$bg}) no alcanza 4.5:1"
        );
    }

    public function test_el_aviso_sigue_pasando_AA_en_oscuro(): void
    {
        $fg = $this->variable('--muni-warn-fg', '@media (prefers-color-scheme: dark)');
        $bg = $this->variable('--muni-warn-bg', '@media (prefers-color-scheme: dark)');

        $this->assertGreaterThanOrEqual(
            4.5,
            Contraste::ratio($fg, $bg),
            "El aviso en oscuro ({$fg} sobre {$bg}) no alcanza 4.5:1"
        );
    }

    public function test_la_formula_de_contraste_es_correcta(): void
    {
        // Negro sobre blanco es el máximo posible: 21:1.
        $this->assertSame(21.0, Contraste::ratio('#000000', '#ffffff'));
        // Un color consigo mismo no contrasta: 1:1.
        $this->assertSame(1.0, Contraste::ratio('#c47a10', '#c47a10'));
    }
}
```

El último caso no prueba el paquete, prueba la herramienta: sin él, una fórmula equivocada podría dar por buenos todos los colores.

- [ ] **Step 4: Instalar dependencias y correr la prueba para verla fallar**

Run: `composer install && vendor/bin/phpunit`
Expected: `test_el_aviso_pasa_AA_en_el_tema_claro_por_omision` y `..._explicito` FALLAN con 3.11 frente a 4.5. Los otros dos PASAN.

- [ ] **Step 5: Arreglar el color en los dos bloques claros**

En `resources/css/muni-ui.css`, línea 65 (dentro de `:root`) y línea 199 (dentro de `[data-muni-theme="light"]`), sustituir el valor de `--muni-warn-fg`:

```css
    /* 5.11:1 sobre --muni-warn-bg (#fef3dc). El #c47a10 anterior daba 3.11:1 y
       no alcanzaba el 4.5:1 de WCAG AA para texto. Se conserva el ámbar: el
       color es lo que distingue un aviso de un error de un vistazo. */
    --muni-warn-fg: #96590a;
```

No tocar `--muni-warn-border`: es borde, no texto, y le basta 3:1.

- [ ] **Step 6: Correr la prueba y verla pasar**

Run: `vendor/bin/phpunit`
Expected: los cuatro casos PASAN.

- [ ] **Step 7: Confirmar que la prueba detecta el defecto**

Devolver temporalmente `#c47a10` a la línea 65, correr `vendor/bin/phpunit` y comprobar que vuelve a fallar. Restaurar `#96590a`.

- [ ] **Step 8: Commit**

```bash
git add phpunit.xml tests/ resources/css/muni-ui.css
git commit -m "fix(a11y): el ámbar de aviso no se leía en el tema claro

Daba 3.11:1 sobre su propio fondo, por debajo del 4.5:1 que exige WCAG AA
para texto. El nuevo tono llega a 5.11:1 y sigue siendo ámbar. Va con una
prueba que lee los colores del CSS, para que el defecto no vuelva sin ruido."
```

---

### Task 2: El título de auth-shell

**Files:**
- Modify: `resources/views/components/auth-shell.blade.php:14-15`
- Test: `tests/AuthShellTest.php`

**Interfaces:**
- Consumes: `Muni\Ui\Tests\TestCase` de la Tarea 1.
- Produces: nada que otras tareas consuman.

- [ ] **Step 1: Leer el componente completo**

Run: `sed -n '1,30p' resources/views/components/auth-shell.blade.php`

Hay que saber qué produce `reverb-meta` antes de moverlo: si son etiquetas `<meta>`, su sitio es el `<head>`, fuera del `<title>`.

Run: `cat resources/views/components/reverb-meta.blade.php`

- [ ] **Step 2: Escribir la prueba que falla**

Crear `tests/AuthShellTest.php`:

```php
<?php

namespace Muni\Ui\Tests;

use Illuminate\Support\Facades\Blade;

class AuthShellTest extends TestCase
{
    private function render(): string
    {
        return Blade::render(
            '<x-muni::auth-shell title="Ingresar" system="Sistema de Prueba">contenido</x-muni::auth-shell>'
        );
    }

    public function test_el_titulo_es_solo_texto(): void
    {
        preg_match('/<title>(.*?)<\/title>/s', $this->render(), $m);

        $this->assertNotEmpty($m, 'La página no tiene <title>');
        $this->assertStringNotContainsString('<', $m[1], 'El <title> contiene etiquetas');
        $this->assertSame('Ingresar · Sistema de Prueba', trim($m[1]));
    }

    public function test_el_titulo_no_arrastra_espacios_ni_saltos(): void
    {
        preg_match('/<title>(.*?)<\/title>/s', $this->render(), $m);

        $this->assertSame(trim($m[1]), $m[1], 'El <title> empieza o termina con espacios');
    }
}
```

- [ ] **Step 3: Correr la prueba para verla fallar**

Run: `vendor/bin/phpunit --filter=AuthShellTest`
Expected: FALLA — el título arrastra el resultado de `reverb-meta` y espacios sobrantes.

- [ ] **Step 4: Arreglar el componente**

En `resources/views/components/auth-shell.blade.php`, separar las dos cosas que hoy están anidadas. El `<title>` queda con solo texto, y `reverb-meta` pasa a ser hermano suyo dentro del `<head>`:

```blade
    <title>{{ $title }} · {{ $system }}</title>
    <x-muni::reverb-meta />
```

- [ ] **Step 5: Correr la prueba y verla pasar**

Run: `vendor/bin/phpunit --filter=AuthShellTest`
Expected: los dos casos PASAN.

- [ ] **Step 6: Correr la suite completa**

Run: `vendor/bin/phpunit`
Expected: todo verde, incluidas las pruebas de contraste de la Tarea 1.

- [ ] **Step 7: Verificar en un sistema real**

El paquete se consume desde los sistemas Filament. Abrir la pantalla de ingreso de uno que use `auth-shell` y comprobar en el navegador que la pestaña muestra «Ingresar · <nombre del sistema>» y que en el `<head>` aparecen las etiquetas de Reverb. Una prueba de render no garantiza que Reverb siga funcionando: eso se mira en la página.

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/auth-shell.blade.php tests/AuthShellTest.php
git commit -m "fix: el título de la pantalla de ingreso venía con etiquetas dentro

reverb-meta se expandía dentro del propio <title>, que solo admite texto: el
nombre de la pestaña salía sucio y las etiquetas de Reverb quedaban donde no
correspondía."
```

---

### Task 3: Publicar la versión y avisar a los consumidores

**Files:**
- Modify: `CHANGELOG.md` si existe; si no, se omite y se deja constancia en el mensaje de la etiqueta.

**Interfaces:**
- Consumes: las tareas 1 y 2, ya commiteadas.
- Produces: la etiqueta `v0.12.2`.

- [ ] **Step 1: Comprobar que la suite está verde**

Run: `vendor/bin/phpunit`
Expected: todo pasa.

- [ ] **Step 2: Etiquetar**

Es una corrección compatible: sube el número de parche.

```bash
git tag -a v0.12.2 -m "Corrige el contraste del aviso en claro y el título de auth-shell"
```

- [ ] **Step 3: No actualizar los sistemas todavía**

El paquete está integrado en cuatro sistemas Filament mediante ramas `feat/muni-ui` sin fusionar, y `laravel-kraftdo-ui` es una bifurcación aparte. Subir la versión en cada sistema exige volver a mirar sus pantallas, y no es trabajo de este plan. Anotar en el traspaso qué sistemas quedan pendientes de actualizar.

- [ ] **Step 4: Anotar la divergencia con el fork de KraftDo**

`laravel-kraftdo-ui` nació de este paquete y arrastra los mismos dos defectos. No se toca desde aquí —es otra entidad y no se mezcla— pero queda anotado para que alguien decida si se le aplica la misma corrección.

---

## Self-review

- Los dos defectos del contexto tienen tarea: el ámbar en la Tarea 1, el título en la Tarea 2.
- `Contraste::ratio()` se define en la Tarea 1 y se usa solo ahí; `TestCase` se define en la Tarea 1 y se consume en la Tarea 2. Los nombres coinciden.
- El modo oscuro queda cubierto por un caso de prueba que confirma que **sigue** pasando: es la red que impide arreglar el claro rompiendo el oscuro.
