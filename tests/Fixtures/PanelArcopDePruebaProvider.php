<?php

namespace Muni\Ui\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Muni\Ui\Filament\Privacidad\PanelArcopPlugin;

/**
 * El panel del sistema adoptante imaginario: monta el panel ARCOP del paquete
 * como lo montaría cualquier municipio, declarando lo suyo —quién es el
 * titular, cómo se lo busca y cómo se llaman sus permisos— y nada más.
 */
class PanelArcopDePruebaProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('arcop')
            ->path('arcop')
            ->authGuard('web')
            ->plugin(
                PanelArcopPlugin::make()
                    ->titulares(BuscadorDeVecinos::class)
            );
    }
}
