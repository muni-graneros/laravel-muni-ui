<x-muni::segmented name="periodo" value="s"
    :options="['d' => 'Hoy', 's' => 'Semana', 'm' => 'Mes', 'a' => 'Año']" />

{{-- Enlazado: con wire:model o x-model no envía el form; el valor se actualiza en vivo --}}
<div x-data="{ vista: 'lista' }" style="display:flex;gap:10px;align-items:center;">
    <x-muni::segmented name="vista" value="lista" x-model="vista" :options="['lista' => 'Lista', 'mapa' => 'Mapa']" />
    <span>Vista: <code x-text="vista"></code></span>
</div>
