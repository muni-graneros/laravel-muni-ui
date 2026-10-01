<x-muni::auth-shell title="Ingresar" system="Licencias de Conducir">
    <x-slot:head>@vite('resources/css/app.css')</x-slot:head>

    <form method="post" action="/login" style="display:flex;flex-direction:column;gap:14px;">
        <x-muni::input label="RUN" name="run" placeholder="12.345.678-9" required />
        <x-muni::input label="Contraseña" name="password" type="password" required />
        <x-muni::button type="submit" size="lg">Ingresar</x-muni::button>
    </form>
</x-muni::auth-shell>
