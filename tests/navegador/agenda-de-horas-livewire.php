<?php

declare(strict_types=1);

/*
 * BANCO CON LIVEWIRE REAL para <x-muni::agenda-horas>: la agenda de exámenes de
 * licencia de conducir dentro de un componente Livewire (4.x del vendor, llega con
 * filament/filament como dependencia de desarrollo), servida por `php -S`.
 *
 *   php -d xdebug.mode=off -S 127.0.0.1:8766 tests/navegador/agenda-de-horas-livewire.php
 *
 * Lo mide `tests/navegador/agenda-de-horas-livewire.py` (que lo levanta y lo apaga)
 * y lo corre `tests/AgendaDeHorasTest.php`. Existe porque el revisor refutó dos
 * afirmaciones que solo Livewire de verdad puede confirmar o desmentir:
 *
 *   · «el campo oculto `_mes` sirve para wire:model» — era falso. El montaje de
 *     abajo es el que documenta el README: `wire:model` ata SOLO la hora (por
 *     `x-modelable`), y el día y el mes se sincronizan escuchando
 *     `muni-agenda:dia` y `muni-agenda:mes` en la etiqueta del componente;
 *   · que un repintado del servidor (el morph) no deje la agenda incoherente: la
 *     rejilla nueva con el estado del mes anterior, sin parada de tabulación, o
 *     los paneles devueltos a lo que decía el HTML del servidor.
 *
 * `updatedMes()` tarda 400 ms a propósito, para que la carga («Cargando junio de
 * 2026…», aria-busy) se vea y se pueda medir.
 */

$repo = dirname(__DIR__, 2);

require $repo.'/vendor/autoload.php';

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Muni\Ui\MuniUiServiceProvider;
use Orchestra\Testbench\Foundation\Application;

final class BancoAgendaExamenes extends Component
{
    public string $mes = '2026-05';

    public ?string $dia = '2026-05-12';

    public string $hora = '';

    public function updatedMes(): void
    {
        usleep(400_000);
        $this->dia = null;
        $this->hora = '';
    }

    /** Lo que publicaría el anfitrión: strings ya redactados, nunca un registro. */
    public function franjas(): array
    {
        return [
            '2026-05-12' => [
                ['inicio' => '09:00', 'fin' => '09:30', 'estado' => 'libre'],
                ['inicio' => '09:30', 'fin' => '10:00', 'estado' => 'tomada', 'detalle' => 'Reservada'],
            ],
            '2026-05-13' => [
                ['inicio' => '09:00', 'fin' => '09:30', 'estado' => 'tomada'],
                ['inicio' => '09:30', 'fin' => '10:00', 'estado' => 'libre'],
            ],
            '2026-06-03' => [
                ['inicio' => '11:00', 'fin' => '11:30', 'estado' => 'libre'],
            ],
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            <x-muni::agenda-horas name="hora" :mes="$mes" :value="$dia" :franjas="$this->franjas()"
                label="Agenda de exámenes de licencia de conducir" mes-evento
                wire:model.live="hora"
                x-on:muni-agenda:mes="$wire.set('mes', $event.detail.mes)"
                x-on:muni-agenda:dia="$wire.set('dia', $event.detail.dia)" />
            <output>{{ $mes }}|{{ $dia }}|{{ $hora }}</output>
        </div>
        BLADE;
    }
}

/**
 * El anfitrión que NO sincroniza el día: solo `wire:model.live` sobre la hora. Cada
 * respuesta repinta la agenda con `value` en el 12 aunque el funcionario esté en el
 * 13. Con los paneles mostrados a mano (`p.hidden = …`) el morph devolvía el panel
 * del 12 y dejaba la hora del 13 marcada en un panel OCULTO (medido): por eso van
 * con `:hidden` declarado, que Alpine reevalúa sobre el HTML nuevo.
 */
final class BancoAgendaSinDia extends Component
{
    public string $hora = '';

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            <x-muni::agenda-horas name="hora_sin_dia" mes="2026-05" value="2026-05-12"
                label="Agenda sin sincronizar el día"
                :franjas="['2026-05-12' => [['inicio' => '09:00', 'estado' => 'libre']], '2026-05-13' => [['inicio' => '09:30', 'estado' => 'libre']]]"
                wire:model.live="hora" />
            <output>{{ $hora }}</output>
        </div>
        BLADE;
    }
}

$clave = 'base64:'.base64_encode(str_repeat('k', 32));

$app = Application::create(
    basePath: null,
    options: [
        'extra' => [
            'env' => [
                'APP_KEY='.$clave,
                'APP_DEBUG=true',
                'SESSION_DRIVER=cookie',
                'CACHE_STORE=array',
            ],
            'providers' => [
                LivewireServiceProvider::class,
                MuniUiServiceProvider::class,
            ],
        ],
    ],
);

$app['config']->set('app.key', $clave);
$app['config']->set('app.debug', true);
$app['config']->set('session.driver', 'cookie');

Livewire::component('banco-agenda-examenes', BancoAgendaExamenes::class);
Livewire::component('banco-agenda-sin-dia', BancoAgendaSinDia::class);

Route::get('/', function () use ($repo) {
    // El CSS NO pasa por Blade (DESIGN §8): por Blade va solo el montaje.
    $css = (string) file_get_contents($repo.'/resources/css/muni-ui.css');
    $cuerpo = Blade::render(
        '<main id="muni-contenido" tabindex="-1"><h1>Agenda de exámenes</h1>'
        .'<section data-caso="completo"><form action="#" onsubmit="return false">@livewire(\'banco-agenda-examenes\')</form></section>'
        .'<section data-caso="sin-dia"><h2>Sin sincronizar el día</h2><form action="#" onsubmit="return false">@livewire(\'banco-agenda-sin-dia\')</form></section></main>'
    );

    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>Banco Livewire agenda-horas</title>'
        .'<style>'.$css.'</style>'
        .'<style>body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}main{max-width:720px}</style>'
        .'</head><body>'.$cuerpo.'</body></html>';
})->middleware('web');

$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
