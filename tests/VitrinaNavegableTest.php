<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LA VITRINA NAVEGABLE — ficha `vitrina`, tal como la corrigió el juez
|--------------------------------------------------------------------------
|
| La ficha pedía una vitrina servida por el paquete detrás de una bandera de
| configuración. El juez la sacó del paquete: vive en `workbench/`, se sirve con
| `vendor/bin/testbench serve` y jamás llega a los sistemas que instalan el
| paquete. Y puso UNA condición para que valga la deuda: las tarjetas se generan
| RECORRIENDO el directorio de componentes, nunca escritas una por una, porque
| una vitrina escrita a mano es justo `demo/showcase.html`, que divergió.
|
| Lo que se vigila:
|
|   1. Fuera del paquete: ni ruta ni config en el service provider, ni vista en
|      `resources/views/components/`, ni etiqueta `<x-muni::vitrina>`.
|   2. Por construcción: cada `.blade.php` del directorio tiene su tarjeta, y el
|      índice enlaza a cada una. Un componente nuevo aparece el día que se crea.
|   3. Ninguna tarjeta revienta: si una no se puede renderizar, la vitrina lo
|      dice en rojo en su tarjeta y esta prueba falla con el nombre.
|   4. Los ejemplos no se pudren: ninguna clave apunta a un componente que ya no
|      existe, y todo RUT escrito ahí tiene dígito verificador válido.
|   5. No redefine la paleta: la hoja propia de la vitrina no declara ni un
|      `--muni-*` (el defecto de showcase.html era exactamente ese).
|   6. Los armazones que emiten su propio <html> se sirven en su propia URL, con
|      el nombre restringido a los del directorio (nada de rutas arbitrarias).
|
| `toContain('valor', 'mensaje')` NO sirve en Pest: el segundo argumento es otra
| aguja. Todo mensaje va con `expect(bool)->toBeTrue('mensaje')`. Los helpers
| llevan prefijo `vn` porque Pest carga todos los archivos en el mismo proceso.
*/

function vnRaiz(): string
{
    return dirname(__DIR__);
}

/** @return list<string> */
function vnComponentes(): array
{
    $nombres = array_map(
        fn (string $ruta) => substr(basename($ruta), 0, -strlen('.blade.php')),
        glob(vnRaiz().'/resources/views/components/*.blade.php') ?: []
    );
    sort($nombres);

    return $nombres;
}

/** Carga las rutas del workbench en la aplicación de prueba, como lo hace `testbench serve`. */
function vnCargarRutas(): void
{
    Route::group([], vnRaiz().'/workbench/routes/web.php');
}

/** El HTML sin el contenido de <style> ni <script>: los comentarios de la hoja citan etiquetas. */
function vnSinHojasNiScripts(string $html): string
{
    return (string) preg_replace('#<(style|script)\b[^>]*>.*?</\1>#s', '', $html);
}

beforeEach(function () {
    vnCargarRutas();
});

it('vive en el workbench y no en el paquete', function () {
    expect(is_file(vnRaiz().'/workbench/routes/web.php'))->toBeTrue('Falta workbench/routes/web.php')
        ->and(is_file(vnRaiz().'/testbench.yaml'))->toBeTrue('Falta testbench.yaml');

    $provider = (string) file_get_contents(vnRaiz().'/src/MuniUiServiceProvider.php');
    expect(str_contains($provider, 'loadRoutesFrom'))->toBeFalse('El service provider no puede registrar rutas')
        ->and(stripos($provider, 'vitrina'))->toBeFalse('El service provider no puede saber de la vitrina');

    expect(glob(vnRaiz().'/resources/views/components/vitrina*.blade.php'))->toBe([]);

    $yaml = (string) file_get_contents(vnRaiz().'/testbench.yaml');
    expect(str_contains($yaml, 'Muni\Ui\MuniUiServiceProvider'))->toBeTrue('testbench.yaml debe cargar el provider del paquete')
        ->and((bool) preg_match('/^\s+web:\s*true/m', $yaml))->toBeTrue('testbench.yaml debe descubrir workbench/routes/web.php')
        ->and(str_contains($yaml, '/vitrina'))->toBeTrue('La URL de arranque es fija: /vitrina');
});

it('genera una tarjeta por cada componente del directorio, sin que nadie la escriba', function () {
    $html = $this->get('/vitrina')->assertOk()->getContent();

    $componentes = vnComponentes();
    expect(count($componentes))->toBeGreaterThan(50);

    foreach ($componentes as $nombre) {
        expect(str_contains($html, 'id="pieza-'.$nombre.'"'))->toBeTrue("Falta la tarjeta de {$nombre}")
            ->and(str_contains($html, 'href="#pieza-'.$nombre.'"'))->toBeTrue("El índice no enlaza a {$nombre}")
            ->and(str_contains($html, '&lt;x-muni::'.$nombre.'&gt;'))->toBeTrue("La tarjeta de {$nombre} no rotula su etiqueta");
    }

    expect(substr_count($html, ' data-vitrina-pieza="'))->toBe(count($componentes));
});

it('renderiza cada componente sin reventar', function () {
    $html = $this->get('/vitrina')->assertOk()->getContent();

    preg_match_all('/data-vitrina-fallo="([a-z0-9-]+)"/', $html, $m);

    expect($m[1])->toBe([], 'Tarjetas que no se pudieron renderizar: '.implode(', ', $m[1]));
});

it('usa los componentes reales, no una copia', function () {
    $html = $this->get('/vitrina')->assertOk()->getContent();

    // Marcas que solo emite el componente de verdad.
    expect(str_contains($html, 'class="muni-btn muni-btn--'))->toBeTrue('No hay un <x-muni::button> real')
        // Una etiqueta de componente sin compilar sale como texto literal (DESIGN §8).
        ->and(str_contains(vnSinHojasNiScripts($html), '<x-muni::'))->toBeFalse('Hay una etiqueta <x-muni::…> sin compilar en la página');
});

it('no redefine la paleta: la hoja propia no declara tokens --muni-*', function () {
    $html = $this->get('/vitrina')->assertOk()->getContent();

    preg_match('#<style data-vitrina="propia">(.*?)</style>#s', $html, $m);
    expect($m)->not->toBe([], 'Falta la hoja propia de la vitrina');

    $propia = (string) preg_replace('#/\*.*?\*/#s', '', $m[1]);
    expect((bool) preg_match('/--muni-[a-z0-9-]+\s*:/', $propia))->toBeFalse('La hoja de la vitrina redefine un token --muni-*');

    // Toda variable local lleva prefijo propio, y todo var(--muni-*) existe en las dos hojas.
    preg_match_all('/var\((--muni-[a-z0-9-]+)/', $propia, $usados);
    foreach (array_unique($usados[1]) as $token) {
        expect(str_contains(cssMuniUi(), $token.':'))->toBeTrue("{$token} no existe en muni-ui.css")
            ->and(str_contains(cssMuniUiFilament(), $token.':'))->toBeTrue("{$token} no existe en muni-ui-filament.css");
    }

    // La hoja del paquete sí viaja, tal cual, y es la única fuente de tokens.
    expect(str_contains($html, '<style data-vitrina="muni-ui.css">'))->toBeTrue('La vitrina no carga muni-ui.css del paquete');

    // Foco con outline, nunca solo box-shadow; movimiento con el token.
    expect(str_contains($propia, 'outline: 3px solid var(--muni-focus, var(--muni-accent, #767676))'))->toBeTrue('Foco sin el outline canónico');
    expect((bool) preg_match('/#[0-9a-fA-F]{3,8}\b/', str_replace('#767676', '', $propia)))->toBeFalse('Color literal en la hoja de la vitrina');
});

it('ofrece el conmutador de tema y de densidad con botones de verdad', function () {
    $html = $this->get('/vitrina')->assertOk()->getContent();

    foreach (['sistema', 'claro', 'oscuro'] as $tema) {
        expect((bool) preg_match('/<button type="button"[^>]*data-vitrina-tema="'.$tema.'"[^>]*:aria-pressed=/', $html))
            ->toBeTrue("Falta el botón de tema {$tema}");
    }
    foreach (['comoda', 'compacta'] as $densidad) {
        expect((bool) preg_match('/<button type="button"[^>]*data-vitrina-densidad="'.$densidad.'"[^>]*:aria-pressed=/', $html))
            ->toBeTrue("Falta el botón de densidad {$densidad}");
    }

    // El tema pedido por la URL se pinta desde el servidor: la URL de una captura es fija.
    $oscuro = $this->get('/vitrina?tema=oscuro&densidad=compacta')->assertOk()->getContent();
    expect((bool) preg_match('/<html[^>]*data-muni-theme="dark"/', $oscuro))->toBeTrue('?tema=oscuro no pinta el oscuro')
        ->and((bool) preg_match('/<html[^>]*data-muni-densidad="compacta"/', $oscuro))->toBeTrue('?densidad=compacta no se pinta');

    $sistema = $this->get('/vitrina?tema=<script>')->assertOk()->getContent();
    expect((bool) preg_match('/<html[^>]*data-muni-theme=/', $sistema))->toBeFalse('Un tema desconocido debe caer en el del sistema')
        ->and(str_contains($sistema, '<script>"'))->toBeFalse();
});

it('sirve los armazones en su propia URL, solo con nombres del directorio', function () {
    $html = $this->get('/vitrina')->assertOk()->getContent();

    // Un armazón emite su propio documento y no se puede anidar: la página entera
    // lleva UN solo <html>, y cada armazón va a su propia URL.
    expect(preg_match_all('/<html\b/i', vnSinHojasNiScripts($html)))->toBe(1, 'Hay un <html> anidado dentro de la vitrina');

    preg_match_all('/data-vitrina-armazon="([a-z0-9-]+)"/', $html, $m);
    $armazones = $m[1];
    foreach (['app-shell', 'auth-shell', 'dashboard-shell'] as $conocido) {
        expect(in_array($conocido, $armazones, true))->toBeTrue("{$conocido} debería abrirse en su propia página");
    }

    foreach ($armazones as $nombre) {
        expect(in_array($nombre, vnComponentes(), true))->toBeTrue();
        $pagina = $this->get('/vitrina/armazon/'.$nombre)->assertOk()->getContent();
        expect(str_contains($pagina, 'data-vitrina="muni-ui.css"'))->toBeTrue("El armazón {$nombre} no carga la hoja del paquete");
    }

    $oscuro = $this->get('/vitrina/armazon/app-shell?tema=oscuro')->assertOk()->getContent();
    expect(preg_match_all('/data-muni-theme=/', vnSinHojasNiScripts($oscuro)))->toBe(1)
        ->and((bool) preg_match('/<html[^>]*data-muni-theme="dark"/', $oscuro))->toBeTrue('El armazón no recibe el tema de la URL');

    // Solo nombres del directorio: ni recorridos de ruta ni componentes que no son armazón.
    $this->get('/vitrina/armazon/..%2F..%2Fsrc%2FMuniUiServiceProvider')->assertNotFound();
    $this->get('/vitrina/armazon/no-existe')->assertNotFound();
    $this->get('/vitrina/armazon/button')->assertNotFound();

    // El escudo del paquete se sirve para que gob-bar no pinte una imagen rota; nada más.
    foreach (glob(vnRaiz().'/resources/images/*') ?: [] as $imagen) {
        $this->get('/vendor/muni-ui/'.basename($imagen))->assertOk();
    }
    $this->get('/vendor/muni-ui/..%2F..%2Fcomposer.json')->assertNotFound();
    $this->get('/vendor/muni-ui/composer.json')->assertNotFound();
});

it('mantiene los ejemplos vigentes y con RUT ficticios válidos', function () {
    $ejemplos = require vnRaiz().'/workbench/ejemplos.php';
    expect($ejemplos)->toBeArray();

    $componentes = vnComponentes();
    foreach (array_keys($ejemplos) as $clave) {
        expect(in_array($clave, $componentes, true))->toBeTrue("El ejemplo «{$clave}» apunta a un componente que no existe");
    }

    $fuente = (string) file_get_contents(vnRaiz().'/workbench/ejemplos.php');
    preg_match_all('/\b(\d{1,2}(?:\.\d{3}){2})-([\dkK])\b/', $fuente, $ruts, PREG_SET_ORDER);
    expect($ruts)->not->toBe([]);

    foreach ($ruts as [$completo, $cuerpo, $dv]) {
        $n = (int) str_replace('.', '', $cuerpo);
        $suma = 0;
        $factor = 2;
        while ($n > 0) {
            $suma += ($n % 10) * $factor;
            $n = intdiv($n, 10);
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $esperado = 11 - ($suma % 11);
        $esperado = $esperado === 11 ? '0' : ($esperado === 10 ? 'K' : (string) $esperado);

        expect(strtoupper($dv) === $esperado)->toBeTrue("El RUT {$completo} no tiene DV válido (debería ser {$esperado})");
    }
});
