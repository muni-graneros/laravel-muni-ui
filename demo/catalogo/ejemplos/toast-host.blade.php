{{-- Una sola vez por layout --}}
<x-muni::toast-host position="bottom-right" />

{{-- Desde cualquier parte de la página --}}
<x-muni::button x-on:click="$dispatch('muni-toast', { tone: 'ok', title: 'Guardado', message: 'Los cambios se guardaron.' })">
    Mostrar aviso
</x-muni::button>
<x-muni::button variant="danger" x-on:click="$dispatch('muni-toast', { tone: 'danger', title: 'Sin conexión', message: 'Revisa tu red e inténtalo de nuevo.' })">
    Mostrar error
</x-muni::button>
