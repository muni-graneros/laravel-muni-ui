<?php

namespace Muni\Ui;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Registra el sistema de diseño municipal: los componentes Blade anónimos bajo el
 * namespace `muni` (`<x-muni::kpi>`) y publica el CSS de tokens para que la app lo
 * importe en su pipeline de Tailwind v4.
 */
class MuniUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Componentes anónimos bajo <x-muni::...>. Los .blade.php viven en el paquete;
        // la app no necesita publicarlos (se resuelven desde vendor/).
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'muni');

        // CSS de tokens: la app hace `@import` desde vendor/ (ver README) o lo publica
        // a resources/css para personalizarlo por proyecto.
        $this->publishes([
            __DIR__.'/../resources/css/muni-ui.css' => resource_path('css/vendor/muni-ui.css'),
        ], 'muni-ui-css');

        // Escudo oficial del municipio. `<x-muni::gob-escudo>` lo sirve desde
        // public/vendor/muni-ui/, así que hay que publicarlo en cada sistema:
        //   php artisan vendor:publish --tag=muni-ui-images
        $this->publishes([
            __DIR__.'/../resources/images' => public_path('vendor/muni-ui'),
        ], 'muni-ui-images');
    }
}
