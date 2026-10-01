<x-muni::otp-input :length="6" name="codigo" />

{{-- Enlazado: wire:model="codigo" funciona igual que este x-model --}}
<div x-data="{ codigo: '' }" style="display:grid;gap:8px;">
    <x-muni::otp-input :length="4" name="pin" x-model="codigo" />
    <span>Código: <code x-text="codigo || '(vacío)'"></code></span>
    <x-muni::button size="sm" variant="ghost" x-on:click="codigo = '2468'">Rellenar 2468</x-muni::button>
</div>
