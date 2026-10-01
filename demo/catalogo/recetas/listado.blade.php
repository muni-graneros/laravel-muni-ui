<x-muni::breadcrumb :items="[['label' => 'Inicio', 'url' => '/'], ['label' => 'Patentes']]" />

<x-muni::page-header eyebrow="Rentas municipales" title="Patentes comerciales" subtitle="Actualizado hoy a las 08:00">
    <x-slot:actions>
        <x-muni::button variant="ghost" href="/patentes/export">Exportar CSV</x-muni::button>
        <x-muni::button href="/patentes/crear">Nueva patente</x-muni::button>
    </x-slot:actions>
</x-muni::page-header>

<div style="display:flex;gap:12px;flex-wrap:wrap;">
    <x-muni::kpi value="1.284" label="Resultado del filtro" />
    <x-muni::kpi value="97" label="Morosas" tone="danger" />
    <x-muni::kpi value="1.187" label="Al día" tone="ok" />
</div>

<x-muni::filter-bar action="/patentes">
    <x-muni::field label="Buscar"><input name="buscar" placeholder="Razón social o RUT"></x-muni::field>
    <x-muni::field label="Estado">
        <x-muni::segmented name="estado" value="" :options="['' => 'Todas', 'morosa' => 'Morosas', 'al-dia' => 'Al día']" />
    </x-muni::field>
</x-muni::filter-bar>

<x-muni::data-table :columns="['Razón social', 'RUT', 'Giro', 'Estado', '']">
    <tr data-muni-row>
        <td>Almacén Don Pepe</td><td class="muni-num">76.123.456-7</td><td>Minimarket</td>
        <td><x-muni::badge tone="ok">Al día</x-muni::badge></td>
        <td style="text-align:right;">
            <x-muni::dropdown>
                <x-slot:trigger><x-muni::button size="sm" variant="ghost">Acciones</x-muni::button></x-slot:trigger>
                <x-muni::dropdown-item href="/patentes/1">Ver ficha</x-muni::dropdown-item>
                <x-muni::dropdown-item href="/patentes/1/certificado">Certificado</x-muni::dropdown-item>
            </x-muni::dropdown>
        </td>
    </tr>
    <tr data-muni-row class="muni-row--danger">
        <td>Ferretería El Clavo</td><td class="muni-num">77.890.123-4</td><td>Ferretería</td>
        <td><x-muni::badge tone="danger">Morosa</x-muni::badge></td>
        <td></td>
    </tr>
    <tr data-muni-row>
        <td>Panadería La Espiga</td><td class="muni-num">78.456.789-0</td><td>Panadería</td>
        <td><x-muni::badge tone="warn">Por vencer</x-muni::badge></td>
        <td></td>
    </tr>
</x-muni::data-table>

<x-muni::pagination :current="1" :total="65" :url="fn ($p) => '?page='.$p" info="Mostrando 1–20 de 1.284" />
