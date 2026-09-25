<x-muni::sidebar width="240px">
    <x-muni::nav-section title="General">
        <x-muni::nav-item href="/" active icon='<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 9l7-6 7 6v8H3z" stroke-linejoin="round"/></svg>'>Resumen</x-muni::nav-item>
        <x-muni::nav-item href="/solicitudes" badge="12" icon='<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 2h7l3 3v13H5z" stroke-linejoin="round"/></svg>'>Solicitudes</x-muni::nav-item>
        <x-muni::nav-item href="/personas" icon='<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="7" r="3.5"/><path d="M3 18c1-4 4-5 7-5s6 1 7 5" stroke-linecap="round"/></svg>'>Personas</x-muni::nav-item>
    </x-muni::nav-section>
    <x-muni::nav-section title="Administración">
        <x-muni::nav-item href="/usuarios">Usuarios</x-muni::nav-item>
        <x-muni::nav-item href="/bitacora">Bitácora</x-muni::nav-item>
    </x-muni::nav-section>
</x-muni::sidebar>
