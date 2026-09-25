<x-muni::drawer title="Patente 2026-0412" width="440px">
    <x-slot:trigger>
        <x-muni::button variant="ghost">Abrir la ficha de Ferretería El Clavo</x-muni::button>
    </x-slot:trigger>

    <div style="display:flex;gap:12px;align-items:center;margin-bottom:18px;">
        <x-muni::avatar name="Ferretería El Clavo" size="lg" />
        <div>
            <b>Ferretería El Clavo</b>
            <div class="muni-num">77.890.123-4</div>
        </div>
        <x-muni::badge tone="danger" style="margin-left:auto;">Morosa</x-muni::badge>
    </div>

    <x-muni::tabs :tabs="['Historial', 'Datos']">
        <x-muni::tab-panel :index="0">
            <x-muni::timeline style="margin-top:16px;" :items="[
                ['title' => 'Cuota 3 vencida', 'time' => '02 sep', 'tone' => 'danger'],
                ['title' => 'Aviso de cobro enviado', 'time' => '05 sep', 'tone' => 'info'],
                ['title' => 'Cuota 2 pagada', 'time' => '12 jul', 'tone' => 'ok'],
            ]" />
        </x-muni::tab-panel>
        <x-muni::tab-panel :index="1">
            <p>Giro: ferretería. Dirección: Av. Libertador 1450, Graneros.</p>
        </x-muni::tab-panel>
    </x-muni::tabs>

    <x-slot:footer>
        <x-muni::modal title="Dar de baja la patente">
            <x-slot:trigger><x-muni::button variant="danger">Dar de baja</x-muni::button></x-slot:trigger>
            La patente quedará cesada desde hoy y el cambio queda en la bitácora.
            <x-slot:footer>
                <x-muni::button variant="ghost" x-on:click="open = false">Cancelar</x-muni::button>
                <x-muni::button variant="danger" x-on:click="open = false; $dispatch('muni-toast', { tone: 'ok', message: 'Patente dada de baja' })">Dar de baja</x-muni::button>
            </x-slot:footer>
        </x-muni::modal>
        <x-muni::button>Registrar pago</x-muni::button>
    </x-slot:footer>
</x-muni::drawer>

{{-- En el layout, una sola vez --}}
<x-muni::toast-host />
