<x-muni::plantilla-pantalla
    title="Patentes comerciales"
    system="Rentas Municipales"
    eyebrow="Rentas"
    subtitle="1.284 patentes vigentes"
    user="María Soto"
    :migas="[['label' => 'Inicio', 'url' => '/'], ['label' => 'Patentes']]"
    aviso="La cobranza masiva de septiembre sale el lunes."
>
    <x-slot:head>@vite('resources/css/app.css')</x-slot:head>

    <x-slot:actions>
        <x-muni::button>Nueva patente</x-muni::button>
    </x-slot:actions>

    <x-muni::data-table :columns="['Razón social', 'RUT', 'Estado']">
        <tr data-muni-row><td>Almacén Don Pepe</td><td class="muni-num">76.123.456-7</td><td><x-muni::badge tone="ok">Al día</x-muni::badge></td></tr>
    </x-muni::data-table>
</x-muni::plantilla-pantalla>
