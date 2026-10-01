<x-muni::checkbox-group name="documentos" legend="Documentos que entrega el solicitante" required
    hint="Marca solo los que están en el mesón."
    :options="[
        'cedula' => 'Cédula de identidad vigente',
        'compin' => 'Resolución COMPIN',
        'residencia' => 'Certificado de residencia',
    ]"
    :selected="['cedula']" />

{{-- Con Livewire: wire:model="documentos" (un arreglo) --}}
