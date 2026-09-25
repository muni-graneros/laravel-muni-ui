<x-muni::accordion :default="0" :items="[
    ['title' => '¿Qué documentos necesito?', 'content' => 'Cédula de identidad vigente y certificado de residencia.'],
    ['title' => '¿Cuánto demora el trámite?', 'content' => 'Hasta 10 días hábiles desde que la solicitud queda completa.'],
    ['title' => '¿Puedo hacerlo en línea?', 'content' => 'Sí, desde este portal, con Clave Única.'],
]" />

{{-- O con el slot, cuando el contenido lleva HTML o componentes --}}
<x-muni::accordion multiple>
    <x-muni::accordion-item title="Requisitos" open>
        <x-muni::badge tone="ok">Cédula vigente</x-muni::badge>
        <x-muni::badge tone="ok">Certificado de residencia</x-muni::badge>
    </x-muni::accordion-item>
    <x-muni::accordion-item title="Plazos">
        Hasta <b>10 días hábiles</b> desde que la solicitud queda completa.
    </x-muni::accordion-item>
</x-muni::accordion>
