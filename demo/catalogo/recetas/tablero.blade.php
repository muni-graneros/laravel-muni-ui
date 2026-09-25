<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
    <x-muni::page-header title="Resumen de rentas" subtitle="Septiembre 2026" />
    <x-muni::segmented>
        <span class="muni-seg">Semana</span>
        <span class="muni-seg muni-seg--on">Mes</span>
        <span class="muni-seg">Año</span>
    </x-muni::segmented>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
    <x-muni::stat value="$48,2 M" label="Recaudado" tone="ok" delta="+12%" deltaDir="up" :spark="[12, 14, 13, 17, 16, 19, 22]" />
    <x-muni::stat value="97" label="Morosas" tone="danger" delta="-4" deltaDir="down" :spark="[110, 108, 104, 103, 99, 98, 97]" />
    <x-muni::stat value="41" label="Por vencer" tone="warn" hint="próximos 30 días" />
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">
    <x-muni::card title="Recaudación mensual" subtitle="Millones de pesos">
        <x-muni::chart-bar :height="140" :data="[
            ['label' => 'Abr', 'value' => 42], ['label' => 'May', 'value' => 55], ['label' => 'Jun', 'value' => 48],
            ['label' => 'Jul', 'value' => 61], ['label' => 'Ago', 'value' => 70], ['label' => 'Sep', 'value' => 48],
        ]" />
    </x-muni::card>
    <x-muni::card title="Estado de las patentes">
        <x-muni::chart-donut :size="130" centerLabel="1.284" :segments="[
            ['label' => 'Al día', 'value' => 1187, 'tone' => 'ok'],
            ['label' => 'Por vencer', 'value' => 41, 'tone' => 'warn'],
            ['label' => 'Morosas', 'value' => 97, 'tone' => 'danger'],
        ]" />
    </x-muni::card>
    <x-muni::card title="Meta anual">
        <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;">
            <x-muni::ring :value="71" tone="ok" label="Recaudación" />
            <x-muni::timeline :items="[
                ['title' => 'Cierre de agosto', 'time' => '01 sep', 'tone' => 'ok'],
                ['title' => 'Cobranza masiva', 'time' => '05 sep', 'tone' => 'info'],
            ]" />
        </div>
    </x-muni::card>
</div>
