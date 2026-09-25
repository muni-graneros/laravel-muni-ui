<x-muni::dashboard-shell :theme="$tema" system="Discapacidad" subtitle="Registro municipal" user="María Soto">
    <x-slot:head>@include('_head')</x-slot:head>
    <x-slot:sidebar>
        <x-muni::sidebar width="220px">
            <x-muni::nav-section title="Registro">
                <x-muni::nav-item active>Resumen</x-muni::nav-item>
                <x-muni::nav-item badge="12">Solicitudes</x-muni::nav-item>
                <x-muni::nav-item>Personas</x-muni::nav-item>
            </x-muni::nav-section>
        </x-muni::sidebar>
    </x-slot:sidebar>
    <x-muni::page-header title="Resumen" subtitle="Semana actual" />
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:18px;">
        <x-muni::stat value="342" label="Inscritos" delta="+8%" deltaDir="up" :spark="[3,5,4,6,7,6,9]" />
        <x-muni::stat value="12" label="Pendientes" tone="warn" />
    </div>
</x-muni::dashboard-shell>
