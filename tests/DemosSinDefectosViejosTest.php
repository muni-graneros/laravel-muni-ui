<?php

/*
 * Las demos son HTML autocontenido: copian los tokens del paquete a mano dentro de
 * un <style>. Esa copia se desvía sola, y cuando se desvía nadie se entera, porque
 * la demo «se ve bien».
 *
 * Ya pasó dos veces con el mismo par de tokens:
 *
 *   --muni-warn-fg  #c47a10  →  3,11:1 sobre su fondo. Se corrigió en el paquete el
 *                               2026-08-18 y las nueve demos siguieron mostrando el
 *                               valor viejo durante meses.
 *   --muni-hint     #8a90a0  →  3,19:1 en claro
 *                   #4a5a74  →  2,59:1 en oscuro
 *
 * Esta prueba no exige que una demo use exactamente la paleta del paquete —showcase
 * tiene una propia a propósito—, solo que ningún valor CONOCIDO POR FALLAR vuelva a
 * aparecer. Es un candado de regresión, no una regla de estilo.
 */

/** @return array<string, string> valor prohibido => por qué */
function valoresQueYaFallaron(): array
{
    return [
        '#c47a10' => 'el ámbar de aviso que daba 3,11:1 y se corrigió a #8a5a00',
        '#8a90a0' => 'el gris de ayuda que daba 3,19:1 en claro',
        '#4a5a74' => 'el gris de ayuda que daba 2,59:1 en oscuro',
    ];
}

/** @return array<string, array{0: string}> */
function archivosDeDemo(): array
{
    $dir = __DIR__.'/../demo';
    $casos = [];

    foreach (glob($dir.'/*.html') ?: [] as $ruta) {
        $casos[basename($ruta)] = [$ruta];
    }

    return $casos;
}

it('no revive un color que ya se midió por debajo del mínimo', function (string $ruta) {
    $html = file_get_contents($ruta);
    $nombre = basename($ruta);

    foreach (valoresQueYaFallaron() as $valor => $motivo) {
        expect(stripos($html, $valor))->toBeFalse(
            "«{$nombre}» declara {$valor}: {$motivo}. Está corregido en el paquete; ".
            'la demo se quedó con la copia vieja.'
        );
    }
})->with(archivosDeDemo());

it('hay demos que revisar: el descubrimiento no puede quedar vacío', function () {
    // Candado del candado: si el glob deja de encontrar demos, la prueba de arriba
    // pasaría con cero casos y el candado se apagaría sin que nadie lo note.
    expect(count(archivosDeDemo()))->toBeGreaterThanOrEqual(14);
});
