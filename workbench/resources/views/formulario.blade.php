<div style="display:grid;gap:18px;max-width:640px;">
    <x-muni::calendar name="fecha" wire:model.live="fecha" />
    <x-muni::otp-input :length="4" name="codigo" wire:model.live="codigo" />
    <x-muni::rating name="nota" wire:model.live="nota" aria-label="Nota" />
    <x-muni::segmented name="vista" wire:model.live="vista" :options="['lista' => 'Lista', 'mapa' => 'Mapa']" />
    <x-muni::radio-group name="tipo" wire:model.live="tipo" :options="['acceso' => 'Acceso', 'oposicion' => 'Oposición']" />
    <x-muni::checkbox name="acepta" wire:model.live="acepta" label="Acepto" />
    <x-muni::switch name="notificar" wire:model.live="notificar" label="Notificar" />
    <x-muni::textarea name="obs" wire:model.live="obs" maxlength="50" />
    <x-muni::input name="run" wire:model.live="run" label="RUN" />
    <x-muni::file-dropzone name="archivo" wire:model="archivo" />
    <x-muni::data-table wireSort="ordenarPor" sort="razon" :columns="[['label' => 'Razón', 'sort' => 'razon'], ['label' => 'Deuda', 'sort' => 'deuda']]"><tr><td>x</td><td>y</td></tr></x-muni::data-table>
    <x-muni::button wire:click="reiniciar">Valores desde el servidor</x-muni::button>
    <x-muni::spinner id="cargando" wire:loading.inline-flex wire:target="reiniciar" label="Reiniciando" />

    <pre id="servidor">{{ json_encode(['fecha' => $fecha, 'codigo' => $codigo, 'nota' => $nota, 'vista' => $vista, 'tipo' => $tipo, 'acepta' => $acepta, 'notificar' => $notificar, 'obs' => $obs, 'run' => $run, 'archivo' => $archivo?->getClientOriginalName(), 'orden' => $orden]) }}</pre>
</div>
