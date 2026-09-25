<x-muni::gob-bar system="Feria Libre" />
<x-muni::topbar system="Feria Libre" subtitle="Permisos y puestos" status="online">
    <x-muni::command-palette :items="[
        ['label' => 'Puestos', 'url' => '/puestos', 'group' => 'Ir a'],
        ['label' => 'Permisos por vencer', 'url' => '/permisos?vencen=30', 'group' => 'Ir a'],
    ]">
        <x-slot:trigger><x-muni::button size="sm" variant="subtle">Buscar… ⌘K</x-muni::button></x-slot:trigger>
    </x-muni::command-palette>
</x-muni::topbar>

<div style="padding:24px 16px;">
    <x-muni::empty-state title="Aún no hay puestos asignados" description="Asigna el primer puesto de la temporada de primavera.">
        <x-slot:actions><x-muni::button href="/puestos/crear">Asignar puesto</x-muni::button></x-slot:actions>
    </x-muni::empty-state>
</div>

<x-muni::gob-footer system="Feria Libre" />
