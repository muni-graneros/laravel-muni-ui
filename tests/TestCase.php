<?php

namespace Muni\Ui\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use Muni\Shared\MuniSharedServiceProvider;
use Muni\Shared\Privacidad\Contratos\VerificadorIdentidad;
use Muni\Ui\MuniUiServiceProvider;
use Muni\Ui\Tests\Fixtures\PanelArcopDePruebaProvider;
use Muni\Ui\Tests\Fixtures\PanelDePruebaProvider;
use Muni\Ui\Tests\Fixtures\VerificadorDePadron;
use Orchestra\Testbench\TestCase as Base;

/**
 * Base de las pruebas del paquete.
 *
 * Registra a mano los providers de Filament, Livewire y del módulo de
 * privacidad porque testbench no corre el autodescubrimiento del adoptante:
 * sin ellos, `Filament::` ni siquiera resuelve y el paquete solo se puede
 * probar leyendo archivos. Filament y `laravel-muni-shared` son dependencias
 * de DESARROLLO —un sistema que use nada más los componentes Blade no debe
 * arrastrar el panel entero—, así que este arnés es el único lugar del repo
 * donde se los asume presentes.
 */
abstract class TestCase extends Base
{
    use RefreshDatabase;

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            // Livewire va DESPUÉS de Filament y no antes: `filament/support`
            // hace `bind(DataStore::class, DataStoreOverride::class)` —un bind
            // NO compartido—, y si se registra después del `instance()` de
            // Livewire deja el DataStore devolviendo un objeto nuevo en cada
            // resolución. Con eso, todo lo que Livewire guarda por componente
            // (el error bag, entre otras cosas) se pierde y cualquier página
            // del panel truena al renderizar. En una aplicación real el orden
            // del autodescubrimiento ya deja Livewire al final.
            LivewireServiceProvider::class,
            MuniSharedServiceProvider::class,
            MuniUiServiceProvider::class,
            PanelDePruebaProvider::class,
            PanelArcopDePruebaProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Filament firma cosas en el HTML del panel; sin clave no renderiza.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Lo que declara el sistema adoptante imaginario. `sistema` no es
        // cosmético: `privacidad_solicitudes` la comparte todo el ecosistema y
        // es la clave por la que el recurso aísla lo suyo.
        $app['config']->set('privacidad.sistema', 'prueba');
        $app['config']->set('privacidad.disco_evidencia', 'evidencia');
        $app['config']->set('filesystems.disks.evidencia', [
            'driver' => 'local',
            'root' => storage_path('app/evidencia'),
        ]);
        $app['config']->set('auth.providers.users.model', Fixtures\UsuarioDePrueba::class);

        // Cómo se acredita la identidad es decisión del adoptante: el panel
        // solo sabe pedirle el veredicto a este contrato.
        $app->bind(VerificadorIdentidad::class, VerificadorDePadron::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }
}
