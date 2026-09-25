{{--
    FICHA DE PERSONA — composición, no componente.

    Fuente de demo/persona.html. Para regenerarla:  php demo/persona.php

    Por qué no existe <x-muni::ficha-persona> (corrección del juez en
    docs/GAP-ANALYSIS.md): Atención al Vecino, Licencias, Discapacidad y Patentes
    tienen modelos de datos distintos. Un componente con props para foto, RUN,
    domicilio y estado se forkea en el primer sistema que no calce. Lo que viaja en
    el paquete son las piezas; la ficha la arma cada sistema con ellas, así.

    DATOS FICTICIOS. Nadie de acá existe: el RUT 12.345.678-5 es el de ejemplo de
    todo el paquete (dígito verificador válido, inventado) y el domicilio es de
    una calle que no hay. Ley 21.719: todo dato personal que no hace falta ver para
    atender va dentro de <x-muni::pii>, tapado por omisión.
--}}
<x-muni::page-header
    eyebrow="Atención al Vecino · Ficha de persona"
    title="Valentina Ruiz Olmedo"
    subtitle="Persona ficticia de demostración. Ningún dato de esta ficha corresponde a alguien real."
>
    <x-slot:migas>
        <x-muni::breadcrumb :items="[
            ['label' => 'Inicio', 'url' => '#'],
            ['label' => 'Vecinos', 'url' => '#'],
            ['label' => 'Valentina Ruiz Olmedo'],
        ]" />
    </x-slot:migas>
    <x-slot:actions>
        <x-muni::button variant="ghost" type="button" class="muni-no-print">Editar datos</x-muni::button>
        <x-muni::button type="button" class="muni-no-print">Emitir certificado</x-muni::button>
    </x-slot:actions>
</x-muni::page-header>

<section class="persona-bloque" aria-labelledby="persona-identidad">
    <div class="persona-identidad">
        <x-muni::avatar name="Valentina Ruiz Olmedo" size="lg" />
        <div class="persona-identidad__texto">
            <h2 id="persona-identidad" class="persona-titulo">Identificación</h2>
            <p class="persona-estados">
                <x-muni::badge tone="ok">Registro vigente</x-muni::badge>
                <x-muni::badge tone="warn">1 trámite con observación</x-muni::badge>
            </p>
        </div>
    </div>

    <x-muni::description-list label="Datos de la persona">
        {{-- Con valor en el slot: el RUT viaja en el HTML pero nace tapado. Sirve
             contra la mirada por encima del hombro en el mesón. --}}
        <x-muni::description-item label="RUT">
            <x-muni::pii label="RUT" name="rut" masked="12.***.***-5" class="muni-num">12.345.678-5</x-muni::pii>
        </x-muni::description-item>
        <x-muni::description-item label="Edad" mono>34 años</x-muni::description-item>
        <x-muni::description-item label="Comuna">Graneros</x-muni::description-item>
        <x-muni::description-item label="Domicilio">
            <x-muni::pii label="Domicilio" name="domicilio" masked="Calle F••••••, Graneros">Calle Ficticia 1234, Villa Ejemplo, Graneros</x-muni::pii>
        </x-muni::description-item>
        {{-- Modo diferido (sin slot): el valor NO está en el HTML. El anfitrión
             escucha `muni-pii-revelado`, registra el acceso y devuelve la vista con
             el valor y `revealed`. Es la única minimización real. --}}
        <x-muni::description-item label="Teléfono">
            <x-muni::pii label="Teléfono" name="telefono" masked="+56 9 •••• ••78" />
        </x-muni::description-item>
        <x-muni::description-item label="Correo">
            <x-muni::pii label="Correo" name="correo" masked="v•••••@ejemplo.cl" />
        </x-muni::description-item>
    </x-muni::description-list>
</section>

<section class="persona-bloque" aria-labelledby="persona-municipio">
    <h2 id="persona-municipio" class="persona-titulo">En el municipio</h2>

    <x-muni::tabs :tabs="['Trámites', 'Historial', 'Documentos']" id="persona-pestanas" label="Lo que la persona tiene en el municipio">
        <x-muni::tab-panel :index="0">
            <h3 class="persona-panel__titulo">Trámites</h3>
            <x-muni::data-table caption="Trámites de la persona" :columns="['Folio', 'Trámite', 'Estado', 'Ingreso']">
                <tr data-muni-row>
                    <td class="muni-num">AV-2026-0412</td>
                    <td>Certificado de residencia</td>
                    <td><x-muni::badge tone="ok">Emitido</x-muni::badge></td>
                    <td class="muni-num">02/09/2026</td>
                </tr>
                <tr data-muni-row>
                    <td class="muni-num">AV-2026-0388</td>
                    <td>Solicitud de poda de árbol</td>
                    <td><x-muni::badge tone="warn">Con observación</x-muni::badge></td>
                    <td class="muni-num">21/08/2026</td>
                </tr>
                <tr data-muni-row>
                    <td class="muni-num">AV-2026-0107</td>
                    <td>Retiro de voluminosos</td>
                    <td><x-muni::badge tone="neutral">Cerrado</x-muni::badge></td>
                    <td class="muni-num">14/03/2026</td>
                </tr>
            </x-muni::data-table>
        </x-muni::tab-panel>

        <x-muni::tab-panel :index="1">
            <h3 class="persona-panel__titulo">Historial</h3>
            <x-muni::timeline :items="[
                ['title' => 'Certificado de residencia emitido', 'time' => '02 sep 2026 · 12:10', 'datetime' => '2026-09-02T12:10:00-03:00', 'tone' => 'ok', 'actor' => 'Oficina de Partes'],
                ['title' => 'Poda observada: falta la foto del árbol', 'time' => '25 ago 2026 · 09:32', 'datetime' => '2026-08-25T09:32:00-03:00', 'tone' => 'warn', 'current' => true, 'actor' => 'Dirección de Aseo y Ornato'],
                ['title' => 'Actualizó su domicilio', 'time' => '18 ago 2026 · 16:05', 'datetime' => '2026-08-18T16:05:00-03:00', 'tone' => 'info', 'detail' => 'Domicilio: Pasaje Uno 10 → Calle Ficticia 1234'],
                ['title' => 'Retiro de voluminosos cerrado', 'time' => '20 mar 2026 · 10:00', 'datetime' => '2026-03-20T10:00:00-03:00', 'tone' => 'muted'],
            ]" />
        </x-muni::tab-panel>

        <x-muni::tab-panel :index="2">
            <h3 class="persona-panel__titulo">Documentos</h3>
            <x-muni::description-list label="Documentos en el expediente">
                <x-muni::description-item label="Cédula de identidad">Verificada en el mesón el 02/09/2026</x-muni::description-item>
                <x-muni::description-item label="Comprobante de domicilio">Boleta de servicios, agosto 2026</x-muni::description-item>
                <x-muni::description-item label="Foto del árbol" />
            </x-muni::description-list>
        </x-muni::tab-panel>
    </x-muni::tabs>
</section>
