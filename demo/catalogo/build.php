<?php

/**
 * Genera demo/catalogo.html: el catálogo de TODOS los componentes <x-muni::*>,
 * renderizados con Blade real desde resources/views/components (no maquetas).
 *
 *   composer install && php demo/catalogo/build.php
 *
 * Los shells de página completa (app/auth/dashboard-shell, error-page) emiten
 * su propio <html>, así que se renderizan aparte y se muestran en <iframe srcdoc>.
 * Alpine se incrusta inline (igual que el resto de demo/) si se pasa su ruta:
 *   php demo/catalogo/build.php /ruta/a/alpinejs/dist/cdn.min.js
 */

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

require __DIR__.'/../../vendor/autoload.php';

$root = realpath(__DIR__.'/../..');
$cache = sys_get_temp_dir().'/muni-catalogo-views';
@mkdir($cache, 0777, true);
array_map('unlink', glob($cache.'/*.php') ?: []);

// Sin app host: el namespace solo se usa para buscar componentes de clase, que aquí no hay.
$app = new class(sys_get_temp_dir()) extends Application {
    public function getNamespace() { return 'App\\'; }
};
Facade::setFacadeApplication($app);
$app->instance('config', new Repository([
    'view' => ['paths' => [__DIR__], 'compiled' => $cache],
    'broadcasting' => ['connections' => ['reverb' => []]],
]));
$app->register(Illuminate\Filesystem\FilesystemServiceProvider::class);
$app->register(Illuminate\Events\EventServiceProvider::class);
$app->register(Illuminate\View\ViewServiceProvider::class);

// asset() lo usa <x-muni::gob-escudo>: el escudo va embebido para que el HTML sea autocontenido.
$escudo = 'data:image/png;base64,'.base64_encode(file_get_contents($root.'/resources/images/logo-graneros.png'));
$app->instance('url', new class($escudo) {
    public function __construct(private string $escudo) {}
    public function asset($path) { return str_ends_with($path, 'logo-graneros.png') ? $this->escudo : $path; }
});
$app['blade.compiler']->anonymousComponentPath($root.'/resources/views/components', 'muni');
$factory = $app['view'];

$css = file_get_contents($root.'/resources/css/muni-ui.css');
$alpine = isset($argv[1]) ? file_get_contents($argv[1]) : null;
// Los shells se renderizan como documentos propios; se les inyecta el CSS del
// paquete por el slot `head`, que es como lo hace un sistema host.
$shells = [];
foreach (['app-shell', 'dashboard-shell', 'auth-shell', 'error-page'] as $shell) {
    foreach (['light', 'dark'] as $tema) {
        $shells[$shell][$tema] = $factory->make('shells.'.$shell, compact('css', 'alpine', 'tema', 'escudo'))->render();
    }
}

$html = $factory->make('catalogo', compact('css', 'alpine', 'shells', 'escudo'))->render();
file_put_contents($root.'/demo/catalogo.html', $html);
echo "demo/catalogo.html (".round(strlen($html) / 1024)." KB)\n";
