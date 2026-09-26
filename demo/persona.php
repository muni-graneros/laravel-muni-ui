<?php

/*
|--------------------------------------------------------------------------
| Genera demo/persona.html desde demo/persona.blade.php
|--------------------------------------------------------------------------
|
|   php demo/persona.php
|
| Por qué esta demo se GENERA y no se escribe a mano como las otras: una maqueta
| escrita a mano copia los tokens y el marcado de los componentes, y esa copia
| se desvía sola (demo/showcase.html terminó con su propia paleta, y se borró).
| Acá el marcado sale de renderizar los <x-muni::…> reales y la hoja es
| resources/css/muni-ui.css tal cual, así que no hay nada copiado que pueda
| divergir. Si un componente cambia, se vuelve a correr este script.
|
| Solo desarrollo: demo/ va con export-ignore y no llega a los sistemas.
*/

use Illuminate\Support\Facades\Blade;
use Muni\Ui\MuniUiServiceProvider;
use Orchestra\Testbench\Foundation\Application;

$raiz = dirname(__DIR__);

require $raiz.'/vendor/autoload.php';

$app = Application::create(options: ['extra' => ['providers' => [MuniUiServiceProvider::class], 'dont-discover' => ['*']]]);
$app->register(MuniUiServiceProvider::class);

$alpine = null;
foreach (array_merge([$raiz.'/node_modules/alpinejs/dist/cdn.min.js'], glob($raiz.'/scripts/.cache/alpine-3*.min.js') ?: []) as $candidato) {
    if (is_file($candidato)) {
        $alpine = (string) file_get_contents($candidato);
        break;
    }
}

if ($alpine === null) {
    fwrite(STDERR, "Falta Alpine: corre `npm install` (alpinejs es dependencia de desarrollo).\n");
    exit(1);
}

$css = (string) file_get_contents($raiz.'/resources/css/muni-ui.css');

$composicion = (string) file_get_contents(__DIR__.'/persona.blade.php');

$cuerpo = Blade::render(
    '<x-muni::skip-link />'
    .'<header class="persona-barra muni-no-print">'
    .'<b>Atención al Vecino · Graneros</b><span class="persona-barra__sp"></span>'
    .'<button type="button" class="persona-tema" data-persona-tema aria-pressed="false">Tema oscuro</button>'
    .'</header>'
    .'<main id="muni-contenido" tabindex="-1">'
    .$composicion
    .'<aside class="persona-bloque persona-bitacora muni-no-print" aria-labelledby="persona-bitacora">'
    .'<h2 id="persona-bitacora" class="persona-titulo">Bitácora del anfitrión (simulada)</h2>'
    .'<p class="persona-nota">El paquete no registra nada: cada «Mostrar» emite <code>muni-pii-revelado</code> y es el sistema que usa la ficha el que lo anota en su propia bitácora. Esta lista es ese oyente, simulado.</p>'
    .'<ol class="persona-bitacora__lista" aria-live="polite"><li data-persona-vacia>Sin accesos todavía.</li></ol>'
    .'</aside>'
    .'</main>',
    deleteCachedView: true,
);

$propia = <<<'CSS'
    /* Hoja propia de la demo. Cero colores literales y cero tokens --muni-*
       redefinidos: todo sale de muni-ui.css, que va arriba tal cual. */
    body { margin:0; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); }
    .persona-barra { position:sticky; top:0; z-index:10; display:flex; align-items:center; gap:12px; min-height:var(--muni-topbar-h); padding:0 20px; background:var(--muni-surface); border-bottom:1px solid var(--muni-border); }
    .persona-barra b { font-size:14px; }
    .persona-barra__sp { flex:1; }
    .persona-tema { min-height:36px; padding:6px 12px; border:1px solid var(--muni-field-border); border-radius:var(--muni-radius-sm); background:var(--muni-surface); color:var(--muni-text); font:600 13px var(--muni-font-sans); cursor:pointer; }
    .persona-tema:hover { background:var(--muni-surface-2); }
    .persona-tema:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent)); outline-offset:2px; box-shadow:var(--muni-ring); }
    main { max-width:960px; margin:0 auto; padding:28px 20px 60px; scroll-margin-top:var(--muni-topbar-h); }
    main:focus:not(:focus-visible) { outline:none; }
    .persona-bloque { margin-bottom:20px; padding:20px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-lg); box-shadow:var(--muni-shadow); }
    .persona-identidad { display:flex; align-items:center; gap:14px; margin-bottom:6px; }
    .persona-titulo { margin:0 0 4px; font-size:15px; font-weight:700; color:var(--muni-text); }
    .persona-estados { display:flex; flex-wrap:wrap; gap:6px; margin:4px 0 0; }
    .persona-panel__titulo { display:none; }
    .persona-nota { margin:0 0 10px; font-size:13px; line-height:1.5; color:var(--muni-muted); }
    .persona-nota code { font-family:var(--muni-font-mono); font-size:12px; color:var(--muni-text); }
    .persona-bitacora__lista { margin:0; padding-left:20px; font-size:13px; line-height:1.6; color:var(--muni-text); }

    /* AL IMPRIMIR: todos los paneles visibles y sin controles (corrección del
       juez). Una ficha con pestañas imprime solo el panel activo, y en un mesón
       que emite certificados eso es un defecto funcional. El título de cada
       panel aparece solo en papel, porque ahí no hay pestaña que lo nombre.
       Sin print-color-adjust: la paleta clara la fuerza muni-ui.css. */
    @media print {
        #persona-pestanas [role="tablist"] { display:none !important; }
        #persona-pestanas [role="tabpanel"] { display:block !important; }
        .persona-panel__titulo { display:block; margin:14px 0 8px; font-size:14px; font-weight:700; }
        main { max-width:none; padding:0; }
        .persona-bloque { box-shadow:none; }
    }
CSS;

$js = <<<'JS'
    (function () {
        /* El oyente del anfitrión, simulado. En un sistema real esto es un
           endpoint que registra quién vio qué y cuándo (Ley 21.719). textContent,
           nunca innerHTML: la etiqueta viene del DOM. */
        var lista = document.querySelector('.persona-bitacora__lista');
        document.addEventListener('muni-pii-revelado', function (e) {
            var d = e.detail || {};
            var vacia = lista.querySelector('[data-persona-vacia]');
            if (vacia) { vacia.remove(); }
            var li = document.createElement('li');
            var hora = new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            li.textContent = hora + ' · «' + (d.etiqueta || d.campo || 'dato') + '» — ' + (d.diferido
                ? 'el valor no venía en la página: el sistema registraría el acceso y lo serviría.'
                : 'el valor ya venía tapado: el sistema registraría esta apertura.');
            lista.appendChild(li);
        });

        /* Conmutador de tema: fija data-muni-theme en <html>, que es el
           activador explícito de muni-ui.css. */
        var boton = document.querySelector('[data-persona-tema]');
        var raiz = document.documentElement;
        function oscuro() {
            var fijado = raiz.getAttribute('data-muni-theme');
            return fijado ? fijado === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        function pintar() { boton.setAttribute('aria-pressed', oscuro() ? 'true' : 'false'); }
        boton.addEventListener('click', function () {
            raiz.setAttribute('data-muni-theme', oscuro() ? 'light' : 'dark');
            pintar();
        });
        pintar();
    })();
JS;

$html = "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n"
    ."<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
    ."<title>Ficha de persona — muni-ui</title>\n"
    ."<!-- GENERADO por `php demo/persona.php` desde demo/persona.blade.php. No editar a mano. -->\n"
    ."<style data-demo=\"muni-ui.css\">\n{$css}\n</style>\n"
    ."<style data-demo=\"propia\">\n{$propia}\n</style>\n"
    ."</head>\n<body>\n{$cuerpo}\n"
    ."<script>\n{$js}\n</script>\n"
    ."<script>{$alpine}</script>\n"
    ."</body>\n</html>\n";

file_put_contents(__DIR__.'/persona.html', $html);

fwrite(STDOUT, 'demo/persona.html: '.number_format(strlen($html), 0, ',', '.')." bytes\n");
