<?php

namespace Muni\Ui\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Muni\Ui\Filament\MuniPanel;

/**
 * Un panel Filament de verdad para las pruebas del paquete.
 *
 * Existe para que lo que este repo publica —el plugin `MuniPanel` y, desde
 * ahora, el panel ARCOP— se ejercite montado en un panel, y no solo leyendo
 * archivos CSS. Es el equivalente mínimo del `PanelProvider` de un sistema
 * adoptante.
 */
class PanelDePruebaProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('prueba')
            ->path('prueba')
            ->plugin(MuniPanel::make()->departamento('Departamento de Prueba'));
    }
}
