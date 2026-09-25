<x-muni::rating :value="4" name="nota" />
<x-muni::rating :value="3" readonly tone="warn" />

{{-- Enlazado: wire:model="nota" funciona igual que este x-model --}}
<div x-data="{ nota: 2 }" style="display:flex;gap:10px;align-items:center;">
    <x-muni::rating name="atencion" x-model="nota" aria-label="Calidad de la atención" />
    <span>Nota: <code x-text="nota"></code></span>
</div>
