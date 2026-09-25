<x-muni::modal title="Dar de baja la patente">
    <x-slot:trigger>
        <x-muni::button variant="danger">Dar de baja</x-muni::button>
    </x-slot:trigger>

    Se marcará <b>Ferretería El Clavo</b> como cesada. El cambio queda en la bitácora.

    <x-slot:footer>
        <x-muni::button variant="ghost" x-on:click="open = false">Cancelar</x-muni::button>
        <x-muni::button variant="danger" x-on:click="open = false; $dispatch('muni-toast', { tone: 'ok', message: 'Patente dada de baja' })">Confirmar</x-muni::button>
    </x-slot:footer>
</x-muni::modal>
