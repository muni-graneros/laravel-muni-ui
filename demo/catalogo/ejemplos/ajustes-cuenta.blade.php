<x-muni::ajustes-cuenta id="ejemplo-ajc-seguridad" default="1" :sessions="[['id' => 's-actual', 'device' => 'Chrome · Windows 11', 'detail' => 'Graneros, Región de O\'Higgins · ahora mismo', 'current' => true],['id' => 's-iphone', 'device' => 'Safari · iPhone', 'detail' => 'Rancagua, Región de O\'Higgins · hace 2 horas', 'current' => false],]" sessions-action="/mi-cuenta/sesiones/cerrar" danger-action="/mi-cuenta/eliminar" danger-word="ELIMINAR" danger-method="delete" danger-description="La cuenta deja de existir y el acceso se revoca en todos los sistemas.">
    <x-slot:perfil>
        <x-muni::card title="Datos personales">
            <x-muni::input label="Correo institucional" name="correo" value="c.bugueno@graneros.cl" />
        </x-muni::card>
    </x-slot:perfil>
    <x-slot:preferencias>
        <x-muni::card title="Accesibilidad">
            <x-muni::switch label="Reducir animaciones" name="mov" />
        </x-muni::card>
    </x-slot:preferencias>
</x-muni::ajustes-cuenta>
<x-muni::ajustes-cuenta id="ejemplo-ajc-peligro" default="3" :sessions="[['id' => 's-actual', 'device' => 'Chrome · Windows 11', 'detail' => 'Graneros, Región de O\'Higgins · ahora mismo', 'current' => true],['id' => 's-iphone', 'device' => 'Safari · iPhone', 'detail' => 'Rancagua, Región de O\'Higgins · hace 2 horas', 'current' => false],]" sessions-action="/mi-cuenta/sesiones/cerrar" danger-action="/mi-cuenta/eliminar" danger-word="ELIMINAR" danger-method="delete" danger-description="La cuenta deja de existir y el acceso se revoca en todos los sistemas.">
    <x-slot:perfil>
        <x-muni::card title="Datos personales">
            <x-muni::input label="Correo institucional" name="correo" value="c.bugueno@graneros.cl" />
        </x-muni::card>
    </x-slot:perfil>
    <x-slot:preferencias>
        <x-muni::card title="Accesibilidad">
            <x-muni::switch label="Reducir animaciones" name="mov" />
        </x-muni::card>
    </x-slot:preferencias>
</x-muni::ajustes-cuenta>
