<x-muni::stepper :current="1" :steps="[
    ['label' => 'Solicitante', 'hint' => 'Listo'],
    ['label' => 'Documentos', 'hint' => 'Paso actual'],
    ['label' => 'Hora de atención'],
    ['label' => 'Envío'],
]" />

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;align-items:start;">
    <x-muni::card title="Documentos" subtitle="Credencial de discapacidad">
        <form style="display:flex;flex-direction:column;gap:14px;">
            <x-muni::alert tone="info" title="Antes de subir">Los documentos deben estar vigentes y legibles.</x-muni::alert>
            <x-muni::select label="Tipo de documento" name="tipo" placeholder="Selecciona…"
                :options="['cedula' => 'Cédula de identidad', 'compin' => 'Resolución COMPIN', 'residencia' => 'Certificado de residencia']" />
            <x-muni::file-dropzone name="documento" />
            <x-muni::progress :value="2" :max="3" label="Documentos cargados (2 de 3)" />
            <div style="display:flex;justify-content:space-between;gap:8px;">
                <x-muni::button variant="ghost">Volver</x-muni::button>
                <x-muni::button type="submit">Continuar</x-muni::button>
            </div>
        </form>
    </x-muni::card>

    <div style="display:flex;flex-direction:column;gap:12px;">
        <x-muni::card title="Hora de atención">
            <x-muni::calendar name="fecha" min="2026-09-28" />
        </x-muni::card>
        <x-muni::accordion :items="[
            ['title' => '¿Quién puede solicitarla?', 'content' => 'Personas con calificación de discapacidad vigente de la COMPIN.'],
            ['title' => '¿Cuánto demora?', 'content' => 'Hasta 10 días hábiles desde que la solicitud queda completa.'],
        ]" />
    </div>
</div>
