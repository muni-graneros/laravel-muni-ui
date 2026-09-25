<x-muni::dashboard-shell system="Discapacidad" subtitle="Registro municipal" user="María Soto">
    <x-slot:head>@vite('resources/css/app.css')</x-slot:head>

    <x-slot:sidebar>
        <x-muni::sidebar width="220px">
            <x-muni::nav-section title="Registro">
                <x-muni::nav-item href="/" active>Resumen</x-muni::nav-item>
                <x-muni::nav-item href="/solicitudes" badge="12">Solicitudes</x-muni::nav-item>
                <x-muni::nav-item href="/personas">Personas</x-muni::nav-item>
            </x-muni::nav-section>
        </x-muni::sidebar>
    </x-slot:sidebar>

    <x-muni::page-header title="Resumen" subtitle="Semana actual" />
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:18px;">
        <x-muni::stat value="342" label="Inscritos" delta="+8%" deltaDir="up" :spark="[3, 5, 4, 6, 7, 6, 9]" />
        <x-muni::stat value="12" label="Pendientes" tone="warn" />
    </div>
</x-muni::dashboard-shell>
