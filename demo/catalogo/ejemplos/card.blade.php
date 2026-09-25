<x-muni::card title="Recaudación del mes" subtitle="Septiembre 2026">
    <x-slot:actions>
        <x-muni::button size="sm" variant="ghost">Ver detalle</x-muni::button>
    </x-slot:actions>
    <x-muni::progress :value="64" label="Meta mensual" showValue />
</x-muni::card>

<x-muni::card title="Últimas solicitudes" flush>
    <x-muni::data-table :columns="['Folio', 'Estado']">
        <tr data-muni-row><td class="muni-num">2026-0412</td><td><x-muni::badge tone="info">En revisión</x-muni::badge></td></tr>
        <tr data-muni-row><td class="muni-num">2026-0411</td><td><x-muni::badge tone="ok">Aprobada</x-muni::badge></td></tr>
    </x-muni::data-table>
</x-muni::card>
