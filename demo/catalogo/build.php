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
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\ViewServiceProvider;

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
    'app' => ['url' => 'http://localhost', 'key' => 'base64:'.base64_encode(str_repeat('c', 32)), 'cipher' => 'AES-256-CBC'],
    'view' => ['paths' => [__DIR__], 'compiled' => $cache],
    'session' => ['driver' => 'array', 'lifetime' => 120, 'expire_on_close' => false, 'encrypt' => false, 'cookie' => 'catalogo', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false],
    'broadcasting' => ['connections' => ['reverb' => []]],
    'app.locale' => 'es', 'app.fallback_locale' => 'es',
]));
$app->register(FilesystemServiceProvider::class);
$app->register(EventServiceProvider::class);
$app->register(RoutingServiceProvider::class);
$app->register(SessionServiceProvider::class);
$app->register(ViewServiceProvider::class);
$app->register(TranslationServiceProvider::class);
$app->instance('path.lang', __DIR__.'/lang');
// Los componentes de develop leen la sesión (selector-tema, sesion-guardia) y
// arman URLs: una petición y una sesión de mentira alcanzan para renderizar.
$app->instance('request', Request::create('http://localhost/catalogo'));
$app['session']->driver()->setId(str_repeat('a', 40));
$app['session']->driver()->start();
// Token fijo: con uno aleatorio cada construcción daría otro HTML y CI no podría
// comprobar que el catálogo publicado está al día.
$app['session']->driver()->put('_token', str_repeat('catalogo', 5));
$app['request']->setLaravelSession($app['session']->driver());

// El escudo se embebe para que el HTML sea autocontenido: asset() arma la URL real
// y lib.php la reemplaza por la imagen al renderizar cada ejemplo.
CatalogoHead::$escudo = 'data:image/png;base64,'.base64_encode(file_get_contents($root.'/resources/images/logo-graneros.png'));

$css = file_get_contents($root.'/resources/css/muni-ui.css');
// Scripts en orden: primero los plugins (Focus, que da x-trap a modal, drawer y
// paleta) y al final Alpine, que al arrancar dispara el alpine:init que los registra.
$alpine = count($argv) > 1 ? implode(";\n", array_map('file_get_contents', array_slice($argv, 1))) : null;
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
