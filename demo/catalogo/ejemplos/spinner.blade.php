<x-muni::spinner size="sm" />
<x-muni::spinner />
<x-muni::spinner size="lg" tone="neutral" />
<x-muni::spinner showLabel label="Buscando en el padrón…" />

{{-- Con Livewire: aparece solo mientras corre guardar() --}}
<x-muni::button wire:click="guardar" wire:loading.attr="disabled">
    <x-muni::spinner size="sm" tone="neutral" wire:loading.inline-flex wire:target="guardar" label="Guardando" />
    Guardar
</x-muni::button>
