<?php

/*
|--------------------------------------------------------------------------
| Paletas del paquete y tonos de la alerta
|--------------------------------------------------------------------------
|
| Vivían dentro de `ContrasteAlertaTest.php` y `AlertaRolEIconoTest.php` las
| tomaba prestadas, así que `pest tests/AlertaRolEIconoTest.php` solo, o
| `pest --parallel`, reventaba con «Call to undefined function». `cssMuniUi()` y
| `cssMuniUiFilament()` están en `tests/Pest.php` y se resuelven al llamar, no al
| cargar, así que el orden de carga no importa.
*/

/**
 * Las cuatro paletas del paquete: dos hojas × dos temas. Hay que medir las dos
 * hojas porque dentro de un panel Filament solo se carga la segunda (DESIGN §7)
 * y declara valores DISTINTOS para los mismos tokens.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function paletasDelPaquete(): array
{
    return [
        'muni-ui.css · claro' => [cssMuniUi(), 'Valores LIGHT (default)'],
        'muni-ui.css · oscuro' => [cssMuniUi(), 'Regla 2: activadores EXPLÍCITOS de dark'],
        'muni-ui-filament.css · claro' => [cssMuniUiFilament(), 'Tema Filament municipal'],
        'muni-ui-filament.css · oscuro' => [cssMuniUiFilament(), 'En oscuro mandan los tonos institucionales'],
    ];
}

/** Los cinco tonos que `alert` sabe rendir: los cuatro del mapa y el respaldo. */
function tonosDeAlerta(): array
{
    return ['ok', 'warn', 'danger', 'info', 'tono-inexistente'];
}
