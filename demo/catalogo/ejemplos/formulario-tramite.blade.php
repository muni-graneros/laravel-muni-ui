<x-muni::formulario-tramite title="Solicitud de patente comercial" subtitle="Ingreso presencial en el mesón de Rentas" action="/patentes" cancel-href="/patentes" requisitos-note="Sin los cuatro requisitos la solicitud queda observada." :requisitos="[['texto' => 'Cédula de identidad vigente', 'cumplido' => true, 'nota' => 'Ya está en el expediente'], ['texto' => 'Certificado de dominio', 'nota' => 'Emitido hace menos de 60 días'], ['texto' => 'Informe sanitario de la SEREMI', 'campo' => 'patente_informe'], ['texto' => 'Giro declarado', 'campo' => 'patente_giro']]">
    <x-muni::formulario-seccion legend="Identificación del solicitante" description="Los datos del titular, tal como están en la cédula.">
        <x-muni::input label="RUT del titular" name="patente_rut" hint="Formato 12.345.678-9" required required-text-visible />
        <x-muni::input label="Nombre completo" name="patente_nombre" required />
        <x-muni::input label="Correo de contacto" name="patente_correo" type="email" />
        <x-muni::input label="Teléfono" name="patente_telefono" />
    </x-muni::formulario-seccion>
    <x-muni::formulario-seccion legend="Antecedentes del local" columns="1">
        <x-muni::input label="Dirección del local" name="patente_direccion" hint="Calle, número y comuna" />
        <x-muni::textarea label="Giro solicitado" name="patente_giro" hint="Describe la actividad" :maxlength="500" />
    </x-muni::formulario-seccion>
    <x-muni::formulario-seccion legend="Documentos adjuntos">
        <x-muni::file-dropzone name="patente_informe" label="Informe sanitario" class="muni-fsec__ancho" />
    </x-muni::formulario-seccion>
</x-muni::formulario-tramite>
