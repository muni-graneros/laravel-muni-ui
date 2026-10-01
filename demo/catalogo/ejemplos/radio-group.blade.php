<x-muni::radio-group label="¿Cómo quieres recibir la credencial?" name="entrega" value="retiro" required :options="[
    'retiro' => ['label' => 'Retiro en el mesón', 'description' => 'Av. Libertador Bernardo O\'Higgins 630, de lunes a viernes.'],
    'correo' => ['label' => 'Por correo', 'description' => 'Llega en 5 a 7 días hábiles.'],
]" />

<x-muni::radio-group label="Tipo de solicitud ARCOP" name="tipo" inline
    :options="['acceso' => 'Acceso', 'rectificacion' => 'Rectificación', 'cancelacion' => 'Cancelación', 'oposicion' => 'Oposición']"
    error="Elige el tipo de solicitud." />
