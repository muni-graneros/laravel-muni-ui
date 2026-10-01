{{-- En una página real el resumen toma el foco al aparecer (`focus` por defecto):
     es lo que lleva al lector de pantalla a los errores. Aquí va apagado para que
     el catálogo no salte hasta este ejemplo al abrirse. --}}
<x-muni::error-summary :focus="false" :errors="['rut' => 'El RUT no es válido.', 'fecha' => 'La fecha es obligatoria.']" />
