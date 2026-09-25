<?php

namespace Workbench\App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Un formulario con cada control enlazable del paquete. La vista muestra los
 * valores tal como los tiene el SERVIDOR, así la prueba ve el viaje de ida y
 * vuelta completo, no solo el estado de Alpine.
 */
class Formulario extends Component
{
    use WithFileUploads;

    public string $fecha = '2026-10-05';
    public string $codigo = '';
    public int $nota = 2;
    public string $vista = 'lista';
    public string $tipo = 'acceso';
    public bool $acepta = false;
    public string $obs = '';
    public string $run = '';
    public bool $notificar = true;
    public $archivo = null;
    public string $orden = 'razon';

    public function reiniciar(): void
    {
        $this->fecha = '2026-12-24';
        $this->codigo = '1357';
        $this->nota = 4;
        $this->vista = 'mapa';
        $this->tipo = 'oposicion';
        $this->obs = 'Del servidor';
    }

    public function ordenarPor(string $clave): void
    {
        $this->orden = $clave;
    }

    public function render()
    {
        return view('workbench::formulario');
    }
}
