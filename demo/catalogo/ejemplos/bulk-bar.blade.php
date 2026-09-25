<form method="post" action="#">
    <x-muni::bulk-bar for="ejemplo-bandeja" label="Acciones sobre las solicitudes" count-singular="1 solicitud seleccionada" count-plural=":n solicitudes seleccionadas">
        <button type="submit" name="accion" value="derivar">Derivar a Obras</button>
    </x-muni::bulk-bar>
    <x-muni::data-table id="ejemplo-bandeja" selectable :columns="['Folio', 'Vecino', 'Materia', 'Detalle']" caption="Bandeja de requerimientos">
        <tr data-muni-row>
            <td>
                <input type="checkbox" data-muni-pick name="ids[]" value="4821" checked aria-label="Seleccionar la solicitud 4821">
            </td>
            <td class="muni-num">4821</td>
            <td>Ana Soto Miranda</td>
            <td>Alumbrado público</td>
            <td>
                <a href="#detalle-4821" data-muni-open aria-label="Ver la solicitud 4821">Ver</a>
            </td>
        </tr>
        <tr data-muni-row class="muni-row--danger">
            <td>
                <input type="checkbox" data-muni-pick name="ids[]" value="4823" aria-label="Seleccionar la solicitud 4823">
            </td>
            <td class="muni-num">4823</td>
            <td>Rosa Díaz Núñez</td>
            <td>Tapa de cámara suelta</td>
            <td>
                <a href="#detalle-4823" data-muni-open aria-label="Ver la solicitud 4823">Ver</a>
            </td>
        </tr>
    </x-muni::data-table>
</form>
