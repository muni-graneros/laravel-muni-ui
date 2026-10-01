<x-muni::command-palette :items="[
    ['label' => 'Resumen', 'url' => '/', 'group' => 'Ir a'],
    ['label' => 'Patentes morosas', 'url' => '/patentes?morosa=SI', 'group' => 'Ir a'],
    ['label' => 'Nueva solicitud', 'url' => '/solicitudes/crear', 'group' => 'Acciones', 'hint' => 'N'],
    ['label' => 'Exportar CSV', 'url' => '/patentes/export', 'group' => 'Acciones'],
]">
    <x-slot:trigger>
        <x-muni::button variant="subtle" icon='<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16"><circle cx="9" cy="9" r="6"/><path d="M18 18l-4.5-4.5" stroke-linecap="round"/></svg>'>Buscar… ⌘K</x-muni::button>
    </x-slot:trigger>
</x-muni::command-palette>
