<?php

/**
 * Genera demo/catalogo.html: la documentación de TODOS los componentes <x-muni::*>.
 *
 *   composer install && php demo/catalogo/build.php [ruta/a/alpinejs/dist/cdn.min.js]
 *
 * Cada ejemplo es un archivo en ejemplos/ (y cada receta uno en recetas/): el
 * catálogo lo renderiza con Blade real y muestra ese mismo archivo como código,
 * así que lo que se ve y lo que se copia no pueden divergir. Las props y slots
 * se leen del @props comentado de cada componente.
 *
 * Alpine se incrusta inline (como el resto de demo/) si se pasa su ruta; sin
 * ella el catálogo se genera igual pero los componentes interactivos quedan
 * estáticos.
 */

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

require __DIR__.'/../../vendor/autoload.php';

require __DIR__.'/lib.php';

$root = realpath(__DIR__.'/../..');
$cache = sys_get_temp_dir().'/muni-catalogo-views-'.getmypid();
@mkdir($cache, 0777, true);
array_map('unlink', glob($cache.'/*.php') ?: []);

// Sin app host: el namespace solo se usa para buscar componentes de clase, que aquí no hay.
$app = new class(sys_get_temp_dir()) extends Application
{
    public function getNamespace()
    {
        return 'App\\';
    }
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
$app->instance('url', new class($escudo)
{
    public function __construct(private string $escudo) {}

    public function asset($path)
    {
        return str_ends_with($path, 'logo-graneros.png') ? $this->escudo : $path;
    }
});

$css = file_get_contents($root.'/resources/css/muni-ui.css');
$alpine = isset($argv[1]) ? file_get_contents($argv[1]) : null;
$head = $app['view']->file(__DIR__.'/_head.blade.php', compact('css', 'alpine'))->render();

// Los ejemplos de plantillas escriben `@vite('resources/css/app.css')` en su slot
// `head`, igual que un sistema host. Aquí esa directiva inyecta el CSS del paquete.
CatalogoHead::$html = $head;
$app['blade.compiler']->directive('vite', fn () => '<?php echo \CatalogoHead::$html; ?>');
$app['blade.compiler']->anonymousComponentPath($root.'/resources/views/components', 'muni');

$catalogo = catalogo_datos($app['view'], $root);

$html = $app['view']->file(__DIR__.'/catalogo.blade.php', $catalogo + compact('head'))->render();
file_put_contents($root.'/demo/catalogo.html', $html);
echo 'demo/catalogo.html ('.round(strlen($html) / 1024).' KB, '.$catalogo['total'].' componentes, '.count($catalogo['recetas'])." recetas)\n";
