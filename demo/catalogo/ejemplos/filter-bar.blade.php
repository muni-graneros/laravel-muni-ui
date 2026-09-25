<x-muni::filter-bar action="/patentes">
    <x-muni::field label="Buscar">
        <input name="buscar" placeholder="Razón social o RUT">
    </x-muni::field>
    <x-muni::field label="Morosidad">
        <select name="morosa">
            <option value="">Todas</option>
            <option value="SI">Solo morosas</option>
        </select>
    </x-muni::field>
</x-muni::filter-bar>
