{{-- Solo el dibujo: decorativo (aria-hidden) y del color del texto que lo rodea. --}}
<div style="display:flex;align-items:center;gap:16px;">
    <x-muni::spinner size="16px" />
    <x-muni::spinner />
    <span style="color:var(--muni-accent);"><x-muni::spinner size="32px" /></span>
</div>

{{-- Con Livewire, dentro del botón que dispara la acción. Lo que se ANUNCIA lo pone
     <x-muni::busy-region>, no la ruedita. --}}
<x-muni::button wire:click="guardar" wire:loading.attr="disabled">
    <x-muni::spinner size="16px" wire:loading wire:target="guardar" />
    Guardar
</x-muni::button>
