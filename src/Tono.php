<?php

namespace Muni\Ui;

/**
 * Tono semántico → color del tema, en un solo lugar.
 *
 * Antes cada componente (kpi, stat, chart-bar, chart-donut, ring, progress, rating,
 * timeline) copiaba su propio mapa y ya se habían separado: rating conocía tres
 * tonos y kpi no conocía `accent`. Un tono desconocido cae al color por defecto que
 * pida cada componente.
 */
final class Tono
{
    public const COLORES = [
        'neutral' => 'var(--muni-text)',
        'accent' => 'var(--muni-accent)',
        'ok' => 'var(--muni-ok-fg)',
        'warn' => 'var(--muni-warn-fg)',
        'danger' => 'var(--muni-danger-fg)',
        'info' => 'var(--muni-info-fg)',
        'muted' => 'var(--muni-border-2)',
    ];

    public static function color(?string $tono, string $defecto = 'accent'): string
    {
        return self::COLORES[$tono] ?? self::COLORES[$defecto];
    }
}
