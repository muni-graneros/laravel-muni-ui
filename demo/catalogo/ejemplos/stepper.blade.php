<x-muni::stepper :current="1" :steps="[
    ['label' => 'Solicitante', 'hint' => 'RUN y contacto'],
    ['label' => 'Documentos', 'hint' => 'Cédula y certificado'],
    ['label' => 'Revisión'],
    ['label' => 'Envío'],
]" />

<x-muni::stepper orientation="vertical" :current="2" :steps="['Recepción', 'Evaluación', 'Resolución']" />
