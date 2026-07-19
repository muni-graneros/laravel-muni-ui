<?php

namespace Muni\Ui\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;

/**
 * Plugin de panel que viste cualquier panel Filament con la identidad de la
 * Municipalidad de Graneros, sin repetir la configuración en cada sistema.
 *
 * Aplica, de una sola vez y para todo el ecosistema:
 *   - el tema municipal (muni-ui-filament.css, sidebar oscuro y acentos),
 *   - la franja institucional de 7 colores sobre la barra superior,
 *   - el pie de la barra lateral con el escudo y el departamento,
 *   - la paleta primaria petróleo del escudo.
 *
 * Uso en el PanelProvider:
 *   ->plugin(MuniPanel::make()->departamento('Departamento de Tránsito'))
 *
 * El escudo y el CSS se sirven desde public/vendor/muni-ui/ (publicar con
 * `php artisan vendor:publish --tag=muni-ui-images --tag=muni-ui-filament`).
 */
class MuniPanel implements Plugin
{
    /** Franja institucional oficial de 7 colores (misma que el sitio madre). */
    private const FRANJA = 'linear-gradient(90deg,'
        .'#adcd60 0 14.28%,#355a63 14.28% 28.57%,#eab02c 28.57% 42.85%,'
        .'#c76421 42.85% 57.14%,#7ccbe1 57.14% 71.42%,#ca3048 71.42% 85.71%,#9c9b9b 85.71% 100%)';

    private ?string $departamento = null;

    private bool $aplicarColores = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'muni-panel';
    }

    /** Texto bajo el nombre del municipio en el pie del sidebar. */
    public function departamento(?string $departamento): static
    {
        $this->departamento = $departamento;

        return $this;
    }

    /**
     * Por defecto fija la paleta primaria al petróleo del escudo. Se puede
     * desactivar si el sistema define su propio acento (feria usa ámbar).
     */
    public function conColores(bool $aplicar = true): static
    {
        $this->aplicarColores = $aplicar;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $subtitulo = $this->departamento;

        $panel
            // Tema municipal: el panel deja de verse genérico.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<link rel="stylesheet" href="'.self::asset('vendor/muni-ui/filament.css').'">',
            )
            // Franja institucional del ecosistema sobre la barra superior.
            ->renderHook(
                PanelsRenderHook::TOPBAR_BEFORE,
                fn (): string => '<div role="presentation" aria-hidden="true" style="height:4px;background:'.self::FRANJA.'"></div>',
            )
            // Pie institucional de la barra lateral: escudo + departamento.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => '<div class="mg-sidebar-foot">'
                    .'<img src="'.self::asset('vendor/muni-ui/logo-graneros.png').'" alt="Escudo de Graneros">'
                    .'<span><b>Municipalidad de Graneros</b>'
                    .($subtitulo ? '<span>'.e($subtitulo).'</span>' : '')
                    .'</span></div>',
            );

        if ($this->aplicarColores) {
            $panel->colors(['primary' => Color::hex('#355a63')]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Usa el helper de assets versionados del proyecto si existe (cache-busting
     * en producción); si no, cae al `asset()` estándar de Laravel.
     */
    private static function asset(string $path): string
    {
        return function_exists('asset_versionado') ? asset_versionado($path) : asset($path);
    }
}
