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
use Livewire\LivewireServiceProvider;
use Muni\Ui\MuniUiServiceProvider;
use Muni\Ui\Tests\Fixtures\PanelDePruebaProvider;
use Orchestra\Testbench\TestCase as Base;

/**
 * Base de las pruebas del paquete.
 *
 * Registra a mano los providers de Filament y Livewire porque testbench no
 * corre el autodescubrimiento del adoptante: sin ellos, `Filament::` ni
 * siquiera resuelve y el paquete solo se puede probar leyendo archivos.
 * Filament es dependencia de DESARROLLO —un sistema que use nada más los
 * componentes Blade no debe arrastrar el panel entero—, así que este arnés es
 * el único lugar del repo donde se lo asume presente.
 */
abstract class TestCase extends Base
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
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
            MuniUiServiceProvider::class,
            PanelDePruebaProvider::class,
        ];
    }
}
