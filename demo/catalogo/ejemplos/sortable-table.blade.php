<x-muni::sortable-table searchable
    :columns="[
        ['key' => 'nombre', 'label' => 'Nombre'],
        ['key' => 'run', 'label' => 'RUN', 'mono' => true],
        ['key' => 'deuda', 'label' => 'Deuda', 'align' => 'right', 'mono' => true],
    ]"
    :rows="[
        ['nombre' => 'Ana Rojas', 'run' => '12.345.678-9', 'deuda' => '$0'],
        ['nombre' => 'Luis Pérez', 'run' => '9.876.543-2', 'deuda' => '$184.500', '_tone' => 'danger'],
        ['nombre' => 'Carla Núñez', 'run' => '15.111.222-3', 'deuda' => '$12.000'],
    ]"
/>
