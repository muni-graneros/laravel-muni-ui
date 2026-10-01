<x-muni::app-shell system="Patentes Comerciales" subtitle="Municipalidad de Graneros" status="online">
    <x-slot:head>@vite('resources/css/app.css')</x-slot:head>

    <x-muni::page-header eyebrow="Rentas" title="Patentes vigentes" subtitle="Listado con filtros y estado de pago" />
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin:18px 0;">
        <x-muni::kpi value="1.284" label="Patentes" />
        <x-muni::kpi value="97" label="Morosas" tone="danger" />
    </div>
    <x-muni::data-table :columns="['Razón social', 'RUT', 'Estado']">
        <tr data-muni-row><td>Almacén Don Pepe</td><td class="muni-num">76.123.456-7</td><td><x-muni::badge tone="ok">Al día</x-muni::badge></td></tr>
        <tr data-muni-row class="muni-row--danger"><td>Ferretería El Clavo</td><td class="muni-num">77.890.123-4</td><td><x-muni::badge tone="danger">Morosa</x-muni::badge></td></tr>
    </x-muni::data-table>
</x-muni::app-shell>
