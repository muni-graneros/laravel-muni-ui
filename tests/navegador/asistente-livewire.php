<?php

declare(strict_types=1);

/*
 * BANCO CON LIVEWIRE REAL para <x-muni::asistente>: una aplicación Testbench
 * mínima con el Livewire del vendor (4.4.1, llega con filament/filament como
 * dependencia de desarrollo) servida por el servidor de PHP.
 *
 *   php -d xdebug.mode=off -S 127.0.0.1:8766 tests/navegador/asistente-livewire.php
 *
 * Lo mide `tests/navegador/asistente-livewire.py` (que además lo levanta y lo
 * apaga solo) y lo corre `tests/AsistenteDePasosTest.php`. Existe porque el banco
 * estático solo ve la CARGA de una página: lo que la ficha exige al cambiar de
 * paso —«el foco va al encabezado del paso nuevo»— dentro de Livewire depende del
 * morph, y el morph conserva el nodo del encabezado si no cambia su clave. Eso no
 * lo ve ni un str_contains ni una página estática.
 *
 * La receta es la documentada para Livewire: cada botón con `wire:click` y
 * `type="button"` por `next-attrs`/`prev-attrs`/`skip-attrs`, y el indicador con
 * `wire:click` por paso. La validación del paso 2 vive en el servidor.
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

final class BancoAsistenteCredencial extends Component
{
    public int $paso = 0;

    public string $nombre = '';

    public string $medico = '';

    public string $documento = '';

    public string $declaracion = '';

    public function siguiente(): void
    {
        if ($this->paso === 1 && trim($this->medico) === '') {
            $this->addError('medico', 'Falta el nombre del médico tratante.');

            return;
        }

        $this->resetErrorBag();
        $this->paso = min(4, $this->paso + 1);
    }

    public function omitir(): void
    {
        $this->resetErrorBag();
        $this->paso = min(4, $this->paso + 1);
    }

    public function anterior(): void
    {
        $this->resetErrorBag();
        $this->paso = max(0, $this->paso - 1);
    }

    public function irA(int $indice): void
    {
        // El salto lo autoriza el servidor: solo hacia atrás.
        if ($indice >= 0 && $indice < $this->paso) {
            $this->resetErrorBag();
            $this->paso = $indice;
        }
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            @php
                $rotulos = ['Identificación', 'Antecedentes médicos', 'Documentos', 'Declaración', 'Revisión'];
                $pasos = [];
                foreach ($rotulos as $i => $rotulo) {
                    $pasos[] = ['label' => $rotulo, 'attrs' => ['wire:click' => 'irA('.$i.')', 'type' => 'button']];
                }
            @endphp
            <x-muni::asistente
                :steps="$pasos"
                :current="$paso"
                :errors="$errors"
                :csrf="false"
                skippable
                title="Solicitud de credencial de discapacidad"
                :next-attrs="['wire:click' => 'siguiente', 'type' => 'button']"
                :prev-attrs="['wire:click' => 'anterior', 'type' => 'button']"
                :skip-attrs="['wire:click' => 'omitir', 'type' => 'button']">
                @if ($paso === 0)
                    <x-muni::input label="Nombre completo" name="nombre" wire:model="nombre" />
                @elseif ($paso === 1)
                    <x-muni::input label="Nombre del médico tratante" name="medico" wire:model="medico" />
                @elseif ($paso === 2)
                    <x-muni::input label="Número del informe" name="documento" wire:model="documento" />
                @elseif ($paso === 3)
                    <x-muni::input label="Declaración" name="declaracion" wire:model="declaracion" />
                @else
                    <p>Revisa los datos antes de enviar.</p>
                @endif
            </x-muni::asistente>
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

Livewire::component('banco-asistente-credencial', BancoAsistenteCredencial::class);

Route::get('/', function () use ($repo) {
    // El CSS NO pasa por Blade (DESIGN §8).
    $css = (string) file_get_contents($repo.'/resources/css/muni-ui.css');
    $cuerpo = Blade::render(
        '<main id="muni-contenido" tabindex="-1"><a href="#fuera" id="antes">Volver a mis trámites</a>'
        .'@livewire(\'banco-asistente-credencial\')</main>'
    );

    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>Banco Livewire asistente</title>'
        .'<style>'.$css.'</style>'
        .'<style>body{margin:0;padding:24px;background:var(--muni-bg);color:var(--muni-text);font-family:var(--muni-font-sans)}main{max-width:900px}</style>'
        .'</head><body>'.$cuerpo.'</body></html>';
})->middleware('web');

$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
