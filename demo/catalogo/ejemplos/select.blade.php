<x-muni::select label="Tipo de patente" name="tipo" placeholder="Selecciona…"
    :options="['com' => 'Comercial', 'ind' => 'Industrial', 'pro' => 'Profesional']" />
<x-muni::select label="Sector" name="sector" selected="s"
    :options="['n' => 'Norte', 's' => 'Sur']" error="Ese sector aún no tiene cobertura" />
