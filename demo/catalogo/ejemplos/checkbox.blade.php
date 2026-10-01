<x-muni::checkbox name="acepta" label="Acepto el tratamiento de mis datos personales"
    description="Conforme a la Ley 21.719. Puedes revocarlo en cualquier momento." />

<x-muni::checkbox name="notificar" checked>Avisarme por correo cuando cambie el estado</x-muni::checkbox>

<x-muni::checkbox name="declara" label="Declaro que la información es verdadera"
    error="Debes aceptar la declaración para enviar la solicitud." />

{{-- Con Livewire: wire:model va directo al input --}}
{{-- <x-muni::checkbox name="acepta" wire:model="acepta" label="Acepto" /> --}}
