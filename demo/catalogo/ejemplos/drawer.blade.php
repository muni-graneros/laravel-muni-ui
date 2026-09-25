<x-muni::drawer title="Ficha de Luis Pérez">
    <x-slot:trigger>
        <x-muni::button variant="ghost">Ver ficha</x-muni::button>
    </x-slot:trigger>

    <div style="display:flex;gap:12px;align-items:center;margin-bottom:18px;">
        <x-muni::avatar name="Luis Pérez" size="lg" />
        <div>
            <b>Luis Pérez</b>
            <div class="muni-num">9.876.543-2</div>
        </div>
        <x-muni::badge tone="danger" style="margin-left:auto;">Moroso</x-muni::badge>
    </div>
    <x-muni::timeline :items="[
        ['title' => 'Cuota 3 vencida', 'time' => '02 sep', 'tone' => 'danger'],
        ['title' => 'Aviso enviado', 'time' => '05 sep', 'tone' => 'info'],
    ]" />

    <x-slot:footer>
        <x-muni::button variant="ghost" x-on:click="open = false">Cerrar</x-muni::button>
        <x-muni::button>Registrar pago</x-muni::button>
    </x-slot:footer>
</x-muni::drawer>
