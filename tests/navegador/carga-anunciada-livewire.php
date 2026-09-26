<?php

declare(strict_types=1);

/*
 * BANCO CON LIVEWIRE REAL para <x-muni::busy-region>: una aplicación Testbench
 * mínima con el Livewire del vendor (4.4.1, llega con filament/filament como
 * dependencia de desarrollo) servida por el servidor de PHP.
 *
 *   php -d xdebug.mode=off -S 127.0.0.1:8765 tests/navegador/carga-anunciada-livewire.php
 *
 * Lo mide `tests/navegador/carga-anunciada-livewire.py` (que además lo levanta y
 * lo apaga solo) y lo corre `tests/CargaAnunciadaTest.php`. Tres casos:
 *
 *   · «buscador»: la búsqueda por RUT del mesón. `wire:model.live` sobre `rut` y
 *     una espera artificial en el servidor: si el RUT contiene «lento» tarda
 *     700 ms (más que el retardo antiparpadeo de 300 ms), si no, 60 ms; si
 *     empieza por 9 no hay coincidencias. Con eso se mide desde afuera la
 *     secuencia real aria-busy → clase de carga → morph del resultado → fuera.
 *   · «perezoso»: nace ocupada con `:busy="$total === null"` y `wire:init`; al
 *     llegar el dato la prop se apaga y el morph deja la región libre.
 *   · «trampa»: lo mismo con `:busy="true"` FIJO. Queda ocupada para siempre:
 *     Livewire apaga el directive y el morph repinta lo que dice el servidor. Es
 *     la trampa documentada en el componente, y se mide para que siga siéndolo.
 *
 * PRUEBA MANUAL CON NVDA Y VOICEOVER (punto 4 del juez en docs/GAP-ANALYSIS.md,
 * «cargando»). No la reemplaza ningún test: hay que servir el banco hacia la red
 * y abrirlo desde el Windows (NVDA + Chrome o Firefox) y el Mac (VoiceOver +
 * Safari), que en este equipo no hay:
 *
 *   php -d xdebug.mode=off -S 0.0.0.0:8765 tests/navegador/carga-anunciada-livewire.php
 *   → http://<ip-tailscale-de-este-equipo>:8765/
 *
 * Qué tiene que pasar, en ese orden y nada más:
 *   1. Con el foco en «RUT», escribir «lento»: NO se anuncia «Buscando…» (el
 *      proceso es solo visual); pasado ~1 s se anuncia UNA vez «1 persona
 *      encontrada» sin mover el foco del campo.
 *   2. Borrar y escribir «9lento»: se anuncia «Sin coincidencias para el RUT
 *      ingresado».
 *   3. Escribir «1» (rápido, 60 ms): se anuncia «1 persona encontrada» y el
 *      indicador visible NO llega a parpadear.
 *   4. En VoiceOver, el rotor (VO+U → Puntos de referencia/Formularios) lista la
 *      región de estado; en NVDA, `Insert+F7` no la lista (no es región de
 *      navegación) pero el modo foco sigue en el campo.
 *   5. En «perezoso», al abrir la página se anuncia «Padrón generado: 3.412
 *      patentes morosas» una vez, y el lector no queda leyendo un contenedor
 *      ocupado. «trampa» está SOLO para medir: no es un caso de uso.
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

final class BancoBuscadorRut extends Component
{
    public string $rut = '';

    public string $resumen = '';

    /** @var list<string> */
    public array $personas = [];

    public function updatedRut(): void
    {
        usleep(str_contains($this->rut, 'lento') ? 700_000 : 60_000);

        if ($this->rut === '') {
            $this->personas = [];
            $this->resumen = '';

            return;
        }

        if (str_starts_with($this->rut, '9')) {
            $this->personas = [];
            $this->resumen = 'Sin coincidencias para el RUT ingresado';

            return;
        }

        $this->personas = ['Ana Soto Miranda · 12.345.678-9'];
        $this->resumen = '1 persona encontrada';
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            <label for="rut">RUT</label>
            <input id="rut" type="text" wire:model.live.debounce.100ms="rut" autocomplete="off">
            <x-muni::busy-region target="rut" loading="Buscando en el maestro de personas…" :status="$resumen">
                <ul id="personas">@foreach ($personas as $p)<li>{{ $p }}</li>@endforeach</ul>
            </x-muni::busy-region>
        </div>
        BLADE;
    }
}

/** Nace ocupada y el anfitrión apaga `busy` con el dato: el patrón documentado. */
final class BancoPadronPerezoso extends Component
{
    public ?int $total = null;

    public function generar(): void
    {
        usleep(500_000);
        $this->total = 3412;
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div wire:init="generar">
            <x-muni::busy-region target="generar" loading="Generando el padrón…"
                :busy="$total === null" :status="$total === null ? '' : 'Padrón generado: 3.412 patentes morosas'">
                @if ($total !== null)<p>3.412 patentes morosas</p>@endif
            </x-muni::busy-region>
        </div>
        BLADE;
    }
}

/** `:busy="true"` FIJO: la trampa. Solo para medir que sigue siéndolo. */
final class BancoPadronTrampa extends Component
{
    public ?int $total = null;

    public function generar(): void
    {
        usleep(500_000);
        $this->total = 3412;
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div wire:init="generar">
            <x-muni::busy-region target="generar" loading="Generando el padrón…"
                :busy="true" :status="$total === null ? '' : 'Padrón generado: 3.412 patentes morosas'">
                @if ($total !== null)<p>3.412 patentes morosas</p>@endif
            </x-muni::busy-region>
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

Livewire::component('banco-buscador-rut', BancoBuscadorRut::class);
Livewire::component('banco-padron-perezoso', BancoPadronPerezoso::class);
Livewire::component('banco-padron-trampa', BancoPadronTrampa::class);

Route::get('/', function () use ($repo) {
    // El CSS NO pasa por Blade (DESIGN §8): una directiva escrita como texto en
    // la hoja se compila igual y muere con «expecting endif». Por Blade va solo
    // el montaje de los componentes.
    $css = (string) file_get_contents($repo.'/resources/css/muni-ui.css');
    $cuerpo = Blade::render(
        '<main id="muni-contenido" tabindex="-1"><h1>Búsqueda por RUT en el mesón</h1>'
        .'<section data-caso="buscador"><h2>buscador</h2>@livewire(\'banco-buscador-rut\')</section>'
        .'<section data-caso="perezoso"><h2>perezoso: busy atado al dato</h2>@livewire(\'banco-padron-perezoso\')</section>'
        .'<section data-caso="trampa"><h2>trampa: busy fijo</h2>@livewire(\'banco-padron-trampa\')</section>'
        .'</main>'
    );

    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>Banco Livewire busy-region</title>'
        .'<style>'.$css.'</style>'
        .'<style>body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}'
        .'h1{font-size:20px;margin:0 0 16px}h2{font-size:12px;margin:24px 0 8px;color:var(--muni-muted)}section{max-width:520px}</style>'
        .'</head><body>'.$cuerpo.'</body></html>';
})->middleware('web');

$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
