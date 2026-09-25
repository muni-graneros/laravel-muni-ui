<x-muni::data-table :columns="['Razón social', 'RUT', 'Giro', 'Estado']">
    <tr data-muni-row>
        <td>Almacén Don Pepe</td>
        <td class="muni-num">76.123.456-7</td>
        <td>Minimarket</td>
        <td><x-muni::badge tone="ok">Al día</x-muni::badge></td>
    </tr>
    <tr data-muni-row class="muni-row--danger">
        <td>Ferretería El Clavo</td>
        <td class="muni-num">77.890.123-4</td>
        <td>Ferretería</td>
        <td><x-muni::badge tone="danger">Morosa</x-muni::badge></td>
    </tr>
    <tr data-muni-row>
        <td>Panadería La Espiga</td>
        <td class="muni-num">78.456.789-0</td>
        <td>Panadería</td>
        <td><x-muni::badge tone="warn">Por vencer</x-muni::badge></td>
    </tr>
</x-muni::data-table>

{{-- Orden en el servidor. Con el paginador de Laravel:
     :sortUrl="fn ($clave, $dir) => request()->fullUrlWithQuery(['orden' => $clave, 'dir' => $dir])"
     Con Livewire: wireSort="ordenarPor" (recibe la clave). --}}
<x-muni::data-table caption="Patentes ordenadas por razón social" sort="razon" direction="asc"
    :sortUrl="fn ($clave, $dir) => '?orden='.$clave.'&dir='.$dir"
    :columns="[
        ['label' => 'Razón social', 'sort' => 'razon'],
        ['label' => 'Deuda', 'sort' => 'deuda', 'align' => 'right'],
        'Estado',
    ]">
    <tr data-muni-row><td>Almacén Don Pepe</td><td class="muni-num" style="text-align:right;">$0</td><td><x-muni::badge tone="ok">Al día</x-muni::badge></td></tr>
    <tr data-muni-row class="muni-row--danger"><td>Ferretería El Clavo</td><td class="muni-num" style="text-align:right;">$184.500</td><td><x-muni::badge tone="danger">Morosa</x-muni::badge></td></tr>
</x-muni::data-table>
