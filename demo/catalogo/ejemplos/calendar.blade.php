<x-muni::calendar name="fecha_visita" min="2026-09-10" />

{{-- Enlazado: wire:model="fecha" funciona igual que este x-model --}}
<div x-data="{ fecha: '2026-10-05' }" style="display:grid;gap:8px;">
    <x-muni::calendar name="fecha_retiro" x-model="fecha" />
    <span>Valor enlazado: <code x-text="fecha || '(vacío)'"></code></span>
    <x-muni::button size="sm" variant="ghost" x-on:click="fecha = '2026-12-24'">Poner 24-12-2026</x-muni::button>
</div>
