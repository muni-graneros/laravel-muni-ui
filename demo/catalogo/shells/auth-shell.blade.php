<x-muni::auth-shell :theme="$tema" title="Ingresar" system="Licencias de Conducir">
    <x-slot:head>@include('_head')</x-slot:head>
    <div style="display:flex;flex-direction:column;gap:14px;">
        <x-muni::input label="RUN" name="run" placeholder="12.345.678-9" />
        <x-muni::input label="Contraseña" name="password" type="password" />
        <x-muni::button size="lg" style="width:100%;justify-content:center;">Ingresar</x-muni::button>
    </div>
</x-muni::auth-shell>
