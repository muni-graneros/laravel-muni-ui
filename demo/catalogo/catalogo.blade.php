@php
    $ico = [
        'home' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 9l7-6 7 6v8H3z" stroke-linejoin="round"/></svg>',
        'doc' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 2h7l3 3v13H5z" stroke-linejoin="round"/></svg>',
        'user' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="7" r="3.5"/><path d="M3 18c1-4 4-5 7-5s6 1 7 5" stroke-linecap="round"/></svg>',
        'plus' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4v12M4 10h12" stroke-linecap="round"/></svg>',
        'search' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16"><circle cx="9" cy="9" r="6"/><path d="M18 18l-4.5-4.5" stroke-linecap="round"/></svg>',
        'trash' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6h12M8 6V4h4v2M6 6l1 11h6l1-11" stroke-linejoin="round"/></svg>',
    ];
    $grupos = [
        'Identidad institucional' => ['gob-bar', 'gob-stripe', 'gob-escudo', 'gob-footer'],
        'Navegación y estructura' => ['topbar', 'page-header', 'breadcrumb', 'sidebar', 'tabs', 'stepper', 'pagination', 'accordion', 'card'],
        'Datos y métricas' => ['kpi', 'stat', 'chart-bar', 'chart-donut', 'ring', 'progress', 'data-table', 'sortable-table', 'timeline', 'badge', 'avatar', 'rating'],
        'Formularios' => ['button', 'input', 'select', 'field', 'filter-bar', 'segmented', 'switch', 'calendar', 'otp-input', 'file-dropzone'],
        'Feedback y estados' => ['alert', 'empty-state', 'skeleton', 'tooltip', 'toast-host'],
        'Superposiciones' => ['modal', 'drawer', 'dropdown', 'command-palette'],
        'Plantillas de página' => ['app-shell', 'dashboard-shell', 'auth-shell', 'error-page'],
    ];
    $total = array_sum(array_map('count', $grupos)) + 5; // + nav-item, nav-section, tab-panel, dropdown-item, reverb-meta
@endphp
<!DOCTYPE html>
<html lang="es" data-muni-theme="light" x-data="{ tema: 'light' }" :data-muni-theme="tema">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catálogo muni-ui</title>
    @include('_head')
    <style>
        *,*::before,*::after{ box-sizing:border-box; }
        body{ margin:0; background:var(--muni-bg); color:var(--muni-text); font-family:var(--muni-font-sans); }
        .cat-wrap{ display:grid; grid-template-columns:220px 1fr; max-width:1320px; margin:0 auto; }
        .cat-toc{ position:sticky; top:0; align-self:start; height:100vh; overflow-y:auto; padding:20px 14px; border-right:1px solid var(--muni-border); font-size:12.5px; }
        .cat-toc h4{ margin:16px 0 4px; font-size:10.5px; letter-spacing:.08em; text-transform:uppercase; color:var(--muni-hint); }
        .cat-toc a{ display:block; padding:3px 8px; border-radius:6px; color:var(--muni-muted); text-decoration:none; font-family:var(--muni-font-mono); }
        .cat-toc a:hover{ background:var(--muni-surface-2); color:var(--muni-text); }
        .cat-main{ padding:28px 28px 80px; min-width:0; }
        .cat-hero{ display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:8px; }
        .cat-hero h1{ margin:0; font-size:26px; letter-spacing:-.01em; }
        .cat-hero p{ margin:6px 0 0; color:var(--muni-muted); font-size:14px; max-width:64ch; }
        .cat-group{ margin:44px 0 12px; font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:var(--muni-accent); font-family:var(--muni-font-mono); }
        .cat-item{ margin:0 0 22px; scroll-margin-top:16px; }
        .cat-item__head{ display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
        .cat-item__head code{ font-family:var(--muni-font-mono); font-size:13.5px; font-weight:600; color:var(--muni-text); }
        .cat-item__head span{ font-size:12.5px; color:var(--muni-muted); }
        .cat-demo{ padding:22px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-lg); display:flex; flex-wrap:wrap; gap:14px; align-items:flex-start; }
        .cat-demo--col{ flex-direction:column; align-items:stretch; }
        .cat-demo > *{ min-width:0; max-width:100%; }
        .cat-demo[data-shot=stepper]{ overflow-x:auto; }
        .cat-demo--bleed{ padding:0; overflow:hidden; display:block; }
        .cat-demo--canvas{ background:var(--muni-bg); }
        .cat-sb .muni-sb{ transform:none !important; position:static !important; box-shadow:none !important; }
        .cat-sb .muni-sb__inner{ height:auto; position:static; }
        .cat-frames{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .cat-frames iframe{ width:100%; height:420px; border:1px solid var(--muni-border); border-radius:var(--muni-radius); background:#fff; }
        .cat-frames figcaption{ font-size:11.5px; color:var(--muni-hint); font-family:var(--muni-font-mono); margin-top:4px; }
        .cat-note{ font-size:12.5px; color:var(--muni-muted); margin:6px 0 0; }
        .cat-note code, .cat-mix code{ font-family:var(--muni-font-mono); font-size:12px; background:var(--muni-surface-2); padding:1px 5px; border-radius:4px; }
        .cat-mix{ display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:14px; }
        .cat-mix article{ padding:18px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-lg); }
        .cat-mix h3{ margin:0 0 4px; font-size:15px; }
        .cat-mix p{ margin:0 0 10px; font-size:13px; color:var(--muni-muted); line-height:1.5; }
        .cat-mix ul{ margin:0; padding-left:18px; font-size:12.5px; color:var(--muni-muted); line-height:1.7; }
        @media (max-width:860px){ .cat-wrap{ grid-template-columns:1fr; } .cat-toc{ display:none; } .cat-main{ padding:20px 16px 60px; } .cat-frames{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<x-muni::toast-host />
<div class="cat-wrap">
<nav class="cat-toc" aria-label="Componentes">
    <strong style="font-size:13px;">muni-ui</strong>
    @foreach ($grupos as $g => $items)
        <h4>{{ $g }}</h4>
        @foreach ($items as $c)<a href="#c-{{ $c }}">{{ $c }}</a>@endforeach
    @endforeach
    <h4>Guía</h4><a href="#mezclas">combinaciones</a><a href="#solapes">solapamientos</a>
</nav>
<main class="cat-main">
    <div class="cat-hero">
        <div>
            <h1>Catálogo de componentes</h1>
            <p>Los {{ $total }} componentes <code>&lt;x-muni::*&gt;</code> de laravel-muni-ui, renderizados con Blade desde el paquete. Cambia el tema para ver ambos presets.</p>
        </div>
        <x-muni::segmented>
            <button type="button" class="muni-seg" :class="tema==='light' && 'muni-seg--on'" @click="tema='light'" style="border:0;background:transparent">Claro · discapacidad</button>
            <button type="button" class="muni-seg" :class="tema==='dark' && 'muni-seg--on'" @click="tema='dark'" style="border:0;background:transparent">Oscuro · feria</button>
        </x-muni::segmented>
    </div>

    {{-- ─────────── Identidad institucional ─────────── --}}
    <h2 class="cat-group">Identidad institucional</h2>
    <section class="cat-item" id="c-gob-bar"><div class="cat-item__head"><code>gob-bar</code><span>Barra municipal sobre cada subdominio (incluye escudo + franja)</span></div>
        <div class="cat-demo cat-demo--bleed" data-shot="gob-bar"><x-muni::gob-bar system="Licencias de Conducir" /></div></section>
    <section class="cat-item" id="c-gob-stripe"><div class="cat-item__head"><code>gob-stripe</code><span>Franja de 7 colores oficiales</span></div>
        <div class="cat-demo cat-demo--col" data-shot="gob-stripe"><x-muni::gob-stripe height="8px" /></div></section>
    <section class="cat-item" id="c-gob-escudo"><div class="cat-item__head"><code>gob-escudo</code><span>Escudo oficial, <code>size</code> en px</span></div>
        <div class="cat-demo" data-shot="gob-escudo" style="align-items:center"><x-muni::gob-escudo size="32" /><x-muni::gob-escudo size="56" /><x-muni::gob-escudo size="88" /></div></section>
    <section class="cat-item" id="c-gob-footer"><div class="cat-item__head"><code>gob-footer</code><span>Pie institucional con contacto</span></div>
        <div class="cat-demo cat-demo--bleed" data-shot="gob-footer"><x-muni::gob-footer system="Feria Libre" /></div></section>

    {{-- ─────────── Navegación y estructura ─────────── --}}
    <h2 class="cat-group">Navegación y estructura</h2>
    <section class="cat-item" id="c-topbar"><div class="cat-item__head"><code>topbar</code><span><code>status</code>: online · degraded · offline</span></div>
        <div class="cat-demo cat-demo--col cat-demo--bleed" data-shot="topbar" style="display:flex;gap:1px;background:var(--muni-border)">
            <x-muni::topbar system="Patentes Comerciales" subtitle="Municipalidad de Graneros" status="online" />
            <x-muni::topbar system="Control de Acceso" subtitle="Edificio consistorial" status="degraded" />
            <x-muni::topbar system="Seguridad" status="offline" />
        </div></section>
    <section class="cat-item" id="c-page-header"><div class="cat-item__head"><code>page-header</code><span><code>eyebrow</code>, <code>title</code>, <code>subtitle</code>, slot <code>actions</code></span></div>
        <div class="cat-demo cat-demo--col" data-shot="page-header">
            <x-muni::page-header eyebrow="Rentas municipales" title="Patentes comerciales" subtitle="1.284 patentes vigentes al 25 de septiembre">
                <x-slot:actions><x-muni::button variant="ghost">Exportar</x-muni::button><x-muni::button :icon="$ico['plus']">Nueva patente</x-muni::button></x-slot:actions>
            </x-muni::page-header>
        </div></section>
    <section class="cat-item" id="c-breadcrumb"><div class="cat-item__head"><code>breadcrumb</code><span>El último ítem es la página actual</span></div>
        <div class="cat-demo" data-shot="breadcrumb"><x-muni::breadcrumb :items="[['label' => 'Inicio', 'url' => '#'], ['label' => 'Patentes', 'url' => '#'], ['label' => 'Almacén Don Pepe']]" /></div></section>
    <section class="cat-item" id="c-sidebar"><div class="cat-item__head"><code>sidebar</code> + <code>nav-section</code> + <code>nav-item</code><span>Colapsa en móvil con el evento <code>muni-sidebar</code></span></div>
        <div class="cat-demo cat-demo--bleed cat-sb" data-shot="sidebar" style="max-width:260px">
            <x-muni::sidebar width="240px">
                <x-muni::nav-section title="General">
                    <x-muni::nav-item :icon="$ico['home']" active>Resumen</x-muni::nav-item>
                    <x-muni::nav-item :icon="$ico['doc']" badge="12">Solicitudes</x-muni::nav-item>
                    <x-muni::nav-item :icon="$ico['user']">Personas</x-muni::nav-item>
                </x-muni::nav-section>
                <x-muni::nav-section title="Administración">
                    <x-muni::nav-item>Usuarios</x-muni::nav-item>
                    <x-muni::nav-item>Bitácora</x-muni::nav-item>
                </x-muni::nav-section>
            </x-muni::sidebar>
        </div></section>
    <section class="cat-item" id="c-tabs"><div class="cat-item__head"><code>tabs</code> + <code>tab-panel</code><span>Alpine; flechas ←/→</span></div>
        <div class="cat-demo cat-demo--col" data-shot="tabs">
            <x-muni::tabs :tabs="['Datos', 'Documentos', 'Historial']">
                <x-muni::tab-panel :index="0"><p style="margin:14px 0 0;font-size:13.5px">Razón social, RUT y giro de la patente.</p></x-muni::tab-panel>
                <x-muni::tab-panel :index="1"><p style="margin:14px 0 0;font-size:13.5px">Documentos adjuntos.</p></x-muni::tab-panel>
                <x-muni::tab-panel :index="2"><p style="margin:14px 0 0;font-size:13.5px">Movimientos.</p></x-muni::tab-panel>
            </x-muni::tabs>
        </div></section>
    <section class="cat-item" id="c-stepper"><div class="cat-item__head"><code>stepper</code><span>horizontal · vertical</span></div>
        <div class="cat-demo cat-demo--col" data-shot="stepper">
            <x-muni::stepper :steps="[['label' => 'Solicitante', 'hint' => 'RUN y contacto'], ['label' => 'Documentos', 'hint' => 'Cédula y certificado'], ['label' => 'Revisión'], ['label' => 'Envío']]" :current="1" />
            <x-muni::stepper orientation="vertical" :steps="['Recepción', 'Evaluación', 'Resolución']" :current="2" style="max-width:280px;margin-top:10px" />
        </div></section>
    <section class="cat-item" id="c-pagination"><div class="cat-item__head"><code>pagination</code><span><code>url</code> es un closure fn($p)</span></div>
        <div class="cat-demo cat-demo--col" data-shot="pagination"><x-muni::pagination :current="4" :total="12" :url="fn ($p) => '#p'.$p" info="Mostrando 61–80 de 231" /></div></section>
    <section class="cat-item" id="c-accordion"><div class="cat-item__head"><code>accordion</code><span><code>items</code> [title, content], <code>multiple</code></span></div>
        <div class="cat-demo cat-demo--col" data-shot="accordion">
            <x-muni::accordion :default="0" :items="[['title' => '¿Qué documentos necesito?', 'content' => 'Cédula de identidad vigente y certificado de residencia.'], ['title' => '¿Cuánto demora?', 'content' => 'Hasta 10 días hábiles.'], ['title' => '¿Puedo hacerlo en línea?', 'content' => 'Sí, desde este portal.']]" />
        </div></section>
    <section class="cat-item" id="c-card"><div class="cat-item__head"><code>card</code><span><code>title</code>, <code>subtitle</code>, <code>flush</code>, slot <code>actions</code></span></div>
        <div class="cat-demo cat-demo--canvas" data-shot="card">
            <x-muni::card title="Resumen del mes" subtitle="Septiembre 2026" style="flex:1;min-width:260px">
                <x-slot:actions><x-muni::button size="sm" variant="ghost">Ver todo</x-muni::button></x-slot:actions>
                <p style="margin:0;font-size:13.5px;color:var(--muni-muted)">Contenido libre dentro de la tarjeta.</p>
            </x-muni::card>
            <x-muni::card title="Sin padding (flush)" flush style="flex:1;min-width:260px">
                <x-muni::progress :value="64" label="Meta de recaudación" showValue style="padding:16px" />
            </x-muni::card>
        </div></section>

    {{-- ─────────── Datos y métricas ─────────── --}}
    <h2 class="cat-group">Datos y métricas</h2>
    <section class="cat-item" id="c-kpi"><div class="cat-item__head"><code>kpi</code><span>tonos: neutral · ok · warn · danger · info</span></div>
        <div class="cat-demo cat-demo--canvas" data-shot="kpi">
            <x-muni::kpi value="1.284" label="Patentes" />
            <x-muni::kpi value="1.187" label="Al día" tone="ok" />
            <x-muni::kpi value="41" label="Por vencer" tone="warn" hint="próx. 30 días" />
            <x-muni::kpi value="97" label="Morosas" tone="danger" />
            <x-muni::kpi value="12" label="En revisión" tone="info" />
        </div></section>
    <section class="cat-item" id="c-stat"><div class="cat-item__head"><code>stat</code><span>KPI con <code>delta</code> y <code>spark</code> (sparkline)</span></div>
        <div class="cat-demo cat-demo--canvas" data-shot="stat" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
            <x-muni::stat value="$48,2 M" label="Recaudado" delta="+12%" deltaDir="up" :spark="[12,14,13,17,16,19,22]" tone="ok" />
            <x-muni::stat value="97" label="Morosas" delta="-4" deltaDir="down" :spark="[110,108,104,103,99,98,97]" tone="danger" />
            <x-muni::stat value="3,2 días" label="Tiempo de respuesta" hint="promedio 30 días" />
        </div></section>
    <section class="cat-item" id="c-chart-bar"><div class="cat-item__head"><code>chart-bar</code><span>Barras SVG sin JS</span></div>
        <div class="cat-demo cat-demo--col" data-shot="chart-bar">
            <x-muni::chart-bar :height="150" :data="[['label' => 'Abr', 'value' => 42], ['label' => 'May', 'value' => 55], ['label' => 'Jun', 'value' => 48], ['label' => 'Jul', 'value' => 61], ['label' => 'Ago', 'value' => 70], ['label' => 'Sep', 'value' => 38, 'tone' => 'warn']]" />
        </div></section>
    <section class="cat-item" id="c-chart-donut"><div class="cat-item__head"><code>chart-donut</code><span>Segmentos con tono y leyenda</span></div>
        <div class="cat-demo" data-shot="chart-donut">
            <x-muni::chart-donut centerLabel="1.284" :segments="[['label' => 'Al día', 'value' => 1187, 'tone' => 'ok'], ['label' => 'Por vencer', 'value' => 41, 'tone' => 'warn'], ['label' => 'Morosas', 'value' => 97, 'tone' => 'danger']]" />
        </div></section>
    <section class="cat-item" id="c-ring"><div class="cat-item__head"><code>ring</code><span>Progreso circular</span></div>
        <div class="cat-demo" data-shot="ring">
            <x-muni::ring :value="82" label="Cumplimiento" tone="ok" />
            <x-muni::ring :value="47" label="Avance" />
            <x-muni::ring :value="18" label="Riesgo" tone="danger" :size="72" />
        </div></section>
    <section class="cat-item" id="c-progress"><div class="cat-item__head"><code>progress</code><span>Barra lineal con etiqueta</span></div>
        <div class="cat-demo cat-demo--col" data-shot="progress">
            <x-muni::progress :value="72" label="Documentos cargados" showValue />
            <x-muni::progress :value="35" tone="warn" label="Plazo consumido" showValue />
            <x-muni::progress :value="90" tone="danger" label="Cupos usados" showValue />
        </div></section>
    <section class="cat-item" id="c-data-table"><div class="cat-item__head"><code>data-table</code><span>Tabla densa; <code>muni-row--danger</code> pinta franja de libro mayor</span></div>
        <div class="cat-demo cat-demo--col" data-shot="data-table">
            <x-muni::data-table :columns="['Razón social', 'RUT', 'Giro', 'Estado']">
                <tr data-muni-row><td>Almacén Don Pepe</td><td class="muni-num">76.123.456-7</td><td>Minimarket</td><td><x-muni::badge tone="ok">Al día</x-muni::badge></td></tr>
                <tr data-muni-row class="muni-row--danger"><td>Ferretería El Clavo</td><td class="muni-num">77.890.123-4</td><td>Ferretería</td><td><x-muni::badge tone="danger">Morosa</x-muni::badge></td></tr>
                <tr data-muni-row><td>Panadería La Espiga</td><td class="muni-num">78.456.789-0</td><td>Panadería</td><td><x-muni::badge tone="warn">Por vencer</x-muni::badge></td></tr>
            </x-muni::data-table>
        </div></section>
    <section class="cat-item" id="c-sortable-table"><div class="cat-item__head"><code>sortable-table</code><span>Orden y búsqueda en cliente (Alpine)</span></div>
        <div class="cat-demo cat-demo--col" data-shot="sortable-table">
            <x-muni::sortable-table searchable
                :columns="[['key' => 'nombre', 'label' => 'Nombre'], ['key' => 'rut', 'label' => 'RUN', 'mono' => true], ['key' => 'monto', 'label' => 'Deuda', 'align' => 'right', 'mono' => true]]"
                :rows="[['nombre' => 'Ana Rojas', 'rut' => '12.345.678-9', 'monto' => '$0'], ['nombre' => 'Luis Pérez', 'rut' => '9.876.543-2', 'monto' => '$184.500', '_tone' => 'danger'], ['nombre' => 'Carla Núñez', 'rut' => '15.111.222-3', 'monto' => '$12.000']]" />
        </div></section>
    <section class="cat-item" id="c-timeline"><div class="cat-item__head"><code>timeline</code><span>Bitácora con tonos</span></div>
        <div class="cat-demo cat-demo--col" data-shot="timeline" style="max-width:520px">
            <x-muni::timeline :items="[['title' => 'Solicitud recibida', 'time' => '09:14', 'description' => 'Ingresada en el mesón.', 'tone' => 'info'], ['title' => 'Documentos validados', 'time' => '11:02', 'tone' => 'ok'], ['title' => 'Falta certificado', 'time' => '11:40', 'description' => 'Se notificó al solicitante.', 'tone' => 'warn'], ['title' => 'Resuelta', 'time' => '16:25', 'tone' => 'accent']]" />
        </div></section>
    <section class="cat-item" id="c-badge"><div class="cat-item__head"><code>badge</code><span><code>tone</code>, <code>dot</code></span></div>
        <div class="cat-demo" data-shot="badge" style="align-items:center">
            <x-muni::badge>Neutral</x-muni::badge><x-muni::badge tone="ok">Al día</x-muni::badge><x-muni::badge tone="warn">Por vencer</x-muni::badge>
            <x-muni::badge tone="danger">Morosa</x-muni::badge><x-muni::badge tone="info">En revisión</x-muni::badge><x-muni::badge tone="ok" :dot="false">Sin punto</x-muni::badge>
        </div></section>
    <section class="cat-item" id="c-avatar"><div class="cat-item__head"><code>avatar</code><span>Iniciales o imagen; <code>size</code>, <code>tone</code></span></div>
        <div class="cat-demo" data-shot="avatar" style="align-items:center">
            <x-muni::avatar name="María Soto" size="sm" /><x-muni::avatar name="Juan Pérez" /><x-muni::avatar name="Ana Rojas" size="lg" tone="ok" /><x-muni::avatar name="Luis Díaz" size="lg" tone="warn" />
        </div></section>
    <section class="cat-item" id="c-rating"><div class="cat-item__head"><code>rating</code><span>Editable o <code>readonly</code></span></div>
        <div class="cat-demo" data-shot="rating" style="align-items:center;gap:28px"><x-muni::rating :value="4" name="nota" /><x-muni::rating :value="3" readonly tone="warn" /></div></section>

    {{-- ─────────── Formularios ─────────── --}}
    <h2 class="cat-group">Formularios</h2>
    <section class="cat-item" id="c-button"><div class="cat-item__head"><code>button</code><span>primary · ghost · subtle · danger — sm · md · lg</span></div>
        <div class="cat-demo" data-shot="button" style="align-items:center">
            <x-muni::button :icon="$ico['plus']">Nueva solicitud</x-muni::button>
            <x-muni::button variant="ghost">Cancelar</x-muni::button>
            <x-muni::button variant="subtle">Exportar</x-muni::button>
            <x-muni::button variant="danger" :icon="$ico['trash']">Dar de baja</x-muni::button>
            <x-muni::button size="sm">Pequeño</x-muni::button>
            <x-muni::button size="lg">Grande</x-muni::button>
        </div></section>
    <section class="cat-item" id="c-input"><div class="cat-item__head"><code>input</code><span><code>label</code>, <code>hint</code>, <code>error</code>, <code>icon</code></span></div>
        <div class="cat-demo" data-shot="input" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
            <x-muni::input label="RUN" name="run" placeholder="12.345.678-9" hint="Con puntos y guion" required />
            <x-muni::input label="Buscar" name="q" :icon="$ico['search']" placeholder="Nombre o RUT" />
            <x-muni::input label="Correo" name="email" value="ana@" error="Ingresa un correo válido" />
        </div></section>
    <section class="cat-item" id="c-select"><div class="cat-item__head"><code>select</code><span><code>options</code>, <code>placeholder</code>, <code>error</code></span></div>
        <div class="cat-demo" data-shot="select" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
            <x-muni::select label="Tipo de patente" name="tipo" placeholder="Selecciona…" :options="['com' => 'Comercial', 'ind' => 'Industrial', 'pro' => 'Profesional']" />
            <x-muni::select label="Sector" name="sector" :options="['n' => 'Norte', 's' => 'Sur']" selected="s" error="Sector sin cobertura" />
        </div></section>
    <section class="cat-item" id="c-field"><div class="cat-item__head"><code>field</code><span>Envoltura label + control nativo (para filter-bar)</span></div>
        <div class="cat-demo" data-shot="field"><x-muni::field label="Comuna"><input name="comuna" value="Graneros"></x-muni::field></div></section>
    <section class="cat-item" id="c-filter-bar"><div class="cat-item__head"><code>filter-bar</code><span>Form GET con campos + submit</span></div>
        <div class="cat-demo cat-demo--col" data-shot="filter-bar">
            <x-muni::filter-bar action="#">
                <x-muni::field label="Buscar"><input name="buscar" placeholder="Razón social o RUT"></x-muni::field>
                <x-muni::field label="Morosidad"><select name="morosa"><option>Todas</option><option>Solo morosas</option></select></x-muni::field>
            </x-muni::filter-bar>
        </div></section>
    <section class="cat-item" id="c-segmented"><div class="cat-item__head"><code>segmented</code><span>Radios reales sin JS</span></div>
        <div class="cat-demo" data-shot="segmented"><x-muni::segmented name="periodo" :options="['d' => 'Hoy', 's' => 'Semana', 'm' => 'Mes', 'a' => 'Año']" value="s" /></div></section>
    <section class="cat-item" id="c-switch"><div class="cat-item__head"><code>switch</code><span>Alpine; <code>description</code></span></div>
        <div class="cat-demo cat-demo--col" data-shot="switch" style="max-width:460px">
            <x-muni::switch label="Notificar por correo" name="n1" checked description="Avisar al solicitante cada cambio de estado." />
            <x-muni::switch label="Modo alto contraste" name="n2" />
        </div></section>
    <section class="cat-item" id="c-calendar"><div class="cat-item__head"><code>calendar</code><span>Selector de fecha, <code>min</code></span></div>
        <div class="cat-demo" data-shot="calendar"><x-muni::calendar name="fecha" /></div></section>
    <section class="cat-item" id="c-otp-input"><div class="cat-item__head"><code>otp-input</code><span>Código MFA, <code>length</code></span></div>
        <div class="cat-demo" data-shot="otp-input"><x-muni::otp-input :length="6" /></div></section>
    <section class="cat-item" id="c-file-dropzone"><div class="cat-item__head"><code>file-dropzone</code><span>Arrastrar y soltar</span></div>
        <div class="cat-demo cat-demo--col" data-shot="file-dropzone"><x-muni::file-dropzone name="doc" /></div></section>

    {{-- ─────────── Feedback ─────────── --}}
    <h2 class="cat-group">Feedback y estados</h2>
    <section class="cat-item" id="c-alert"><div class="cat-item__head"><code>alert</code><span>ok · warn · danger · info</span></div>
        <div class="cat-demo cat-demo--col" data-shot="alert">
            <x-muni::alert tone="ok" title="Solicitud enviada">Recibirás un correo con el número de seguimiento.</x-muni::alert>
            <x-muni::alert tone="warn" title="Documento por vencer">La cédula vence en 12 días.</x-muni::alert>
            <x-muni::alert tone="danger" title="Patente morosa">Registra 3 cuotas impagas.</x-muni::alert>
            <x-muni::alert tone="info">El sistema estará en mantención el domingo de 02:00 a 04:00.</x-muni::alert>
        </div></section>
    <section class="cat-item" id="c-empty-state"><div class="cat-item__head"><code>empty-state</code><span>Sin resultados + acciones</span></div>
        <div class="cat-demo cat-demo--col" data-shot="empty-state">
            <x-muni::empty-state title="Sin patentes para este filtro" description="Prueba quitando el filtro de morosidad o buscando por RUT.">
                <x-slot:actions><x-muni::button variant="ghost">Limpiar filtros</x-muni::button></x-slot:actions>
            </x-muni::empty-state>
        </div></section>
    <section class="cat-item" id="c-skeleton"><div class="cat-item__head"><code>skeleton</code><span>Placeholder con shimmer</span></div>
        <div class="cat-demo cat-demo--col" data-shot="skeleton" style="max-width:520px">
            <div style="display:flex;gap:12px;align-items:center"><x-muni::skeleton width="40px" height="40px" rounded="50%" /><div style="flex:1;display:flex;flex-direction:column;gap:8px"><x-muni::skeleton width="60%" /><x-muni::skeleton width="35%" height="10px" /></div></div>
            <x-muni::skeleton height="90px" rounded="var(--muni-radius)" />
        </div></section>
    <section class="cat-item" id="c-tooltip"><div class="cat-item__head"><code>tooltip</code><span>top · bottom · left · right (hover / foco)</span></div>
        <div class="cat-demo" data-shot="tooltip" style="padding:48px 22px 22px">
            <x-muni::tooltip text="Descarga el listado en CSV"><x-muni::button variant="ghost">Pasa el cursor</x-muni::button></x-muni::tooltip>
        </div></section>
    <section class="cat-item" id="c-toast-host"><div class="cat-item__head"><code>toast-host</code><span>Una vez por layout; se dispara con <code>$dispatch('muni-toast', …)</code></span></div>
        <div class="cat-demo" data-shot="toast-host">
            <x-muni::button x-on:click="$dispatch('muni-toast',{tone:'ok',title:'Guardado',message:'Los cambios se guardaron.'})">Toast ok</x-muni::button>
            <x-muni::button variant="danger" x-on:click="$dispatch('muni-toast',{tone:'danger',title:'Error',message:'No se pudo conectar.'})">Toast error</x-muni::button>
        </div></section>

    {{-- ─────────── Superposiciones ─────────── --}}
    <h2 class="cat-group">Superposiciones</h2>
    <section class="cat-item" id="c-modal"><div class="cat-item__head"><code>modal</code><span>slots <code>trigger</code>, <code>footer</code></span></div>
        <div class="cat-demo" data-shot="modal">
            <x-muni::modal title="Dar de baja la patente">
                <x-slot:trigger><x-muni::button variant="danger">Abrir modal</x-muni::button></x-slot:trigger>
                Se marcará <b>Ferretería El Clavo</b> como cesada. Esta acción queda en la bitácora.
                <x-slot:footer>
                    <x-muni::button variant="ghost" x-on:click="open=false">Cancelar</x-muni::button>
                    <x-muni::button variant="danger" x-on:click="open=false; $dispatch('muni-toast',{tone:'ok',message:'Patente dada de baja'})">Confirmar</x-muni::button>
                </x-slot:footer>
            </x-muni::modal>
        </div></section>
    <section class="cat-item" id="c-drawer"><div class="cat-item__head"><code>drawer</code><span>Panel lateral, <code>side</code>, <code>width</code></span></div>
        <div class="cat-demo" data-shot="drawer">
            <x-muni::drawer title="Ficha de Luis Pérez">
                <x-slot:trigger><x-muni::button variant="ghost">Abrir drawer</x-muni::button></x-slot:trigger>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:18px"><x-muni::avatar name="Luis Pérez" size="lg" /><div><b>Luis Pérez</b><div class="muni-num" style="font-size:12px;color:var(--muni-muted)">9.876.543-2</div></div><x-muni::badge tone="danger" style="margin-left:auto">Moroso</x-muni::badge></div>
                <x-muni::timeline :items="[['title' => 'Cuota 3 vencida', 'time' => '02 sep', 'tone' => 'danger'], ['title' => 'Aviso enviado', 'time' => '05 sep', 'tone' => 'info']]" />
                <x-slot:footer><x-muni::button variant="ghost" x-on:click="open=false">Cerrar</x-muni::button><x-muni::button>Registrar pago</x-muni::button></x-slot:footer>
            </x-muni::drawer>
        </div></section>
    <section class="cat-item" id="c-dropdown"><div class="cat-item__head"><code>dropdown</code> + <code>dropdown-item</code><span><code>align</code>, <code>tone="danger"</code></span></div>
        <div class="cat-demo" data-shot="dropdown" style="min-height:190px">
            <x-muni::dropdown align="start">
                <x-slot:trigger><x-muni::button variant="ghost">Acciones ▾</x-muni::button></x-slot:trigger>
                <x-muni::dropdown-item href="#" :icon="$ico['doc']">Ver ficha</x-muni::dropdown-item>
                <x-muni::dropdown-item href="#">Descargar certificado</x-muni::dropdown-item>
                <x-muni::dropdown-item tone="danger" :icon="$ico['trash']">Dar de baja</x-muni::dropdown-item>
            </x-muni::dropdown>
        </div></section>
    <section class="cat-item" id="c-command-palette"><div class="cat-item__head"><code>command-palette</code><span>⌘K / Ctrl+K</span></div>
        <div class="cat-demo" data-shot="command-palette">
            <x-muni::command-palette :items="[['label' => 'Resumen', 'url' => '#', 'group' => 'Ir a'], ['label' => 'Patentes morosas', 'url' => '#', 'group' => 'Ir a'], ['label' => 'Nueva solicitud', 'url' => '#', 'group' => 'Acciones', 'hint' => 'N'], ['label' => 'Exportar CSV', 'url' => '#', 'group' => 'Acciones']]">
                <x-slot:trigger><x-muni::button variant="subtle" :icon="$ico['search']">Buscar… <kbd style="font-family:var(--muni-font-mono);font-size:11px;opacity:.7">⌘K</kbd></x-muni::button></x-slot:trigger>
            </x-muni::command-palette>
        </div></section>

    {{-- ─────────── Plantillas de página ─────────── --}}
    <h2 class="cat-group">Plantillas de página</h2>
    <p class="cat-note" style="margin-bottom:14px">Emiten su propio <code>&lt;html&gt;</code>: se muestran en iframes, claro a la izquierda y oscuro a la derecha. <code>reverb-meta</code> no es visual (inyecta la clave de Reverb en runtime) y lo incluyen estos shells.</p>
    @foreach (['app-shell' => 'Topbar + contenido centrado', 'dashboard-shell' => 'Sidebar + barra superior + usuario', 'auth-shell' => 'Login / registro a dos columnas', 'error-page' => '403 · 404 · 500 · 503'] as $shell => $desc)
        <section class="cat-item" id="c-{{ $shell }}"><div class="cat-item__head"><code>{{ $shell }}</code><span>{{ $desc }}</span></div>
            <div class="cat-frames" data-shot="{{ $shell }}">
                @foreach (['light', 'dark'] as $t)
                    <figure style="margin:0"><iframe title="{{ $shell }} {{ $t }}" loading="lazy" srcdoc="{{ $shells[$shell][$t] }}"></iframe><figcaption>theme="{{ $t }}"</figcaption></figure>
                @endforeach
            </div></section>
    @endforeach

    {{-- ─────────── Guía de combinación ─────────── --}}
    <h2 class="cat-group" id="mezclas">Qué conviene combinar</h2>
    <div class="cat-mix">
        <article><h3>1 · Listado operativo</h3><p>La pantalla más repetida del ecosistema (patentes, feria, licencias).</p>
            <ul><li><code>page-header</code> + <code>breadcrumb</code></li><li>fila de <code>kpi</code> que resumen el filtro</li><li><code>filter-bar</code> con <code>field</code> y <code>segmented</code></li><li><code>data-table</code> con <code>badge</code> y franja <code>muni-row--danger</code></li><li><code>pagination</code>; <code>empty-state</code> si no hay filas; <code>skeleton</code> al cargar</li></ul></article>
        <article><h3>2 · Ficha sin salir del listado</h3><p>Abrir el detalle al lado en vez de navegar.</p>
            <ul><li><code>dropdown</code> de acciones por fila</li><li><code>drawer</code> con <code>avatar</code> + <code>badge</code> + <code>timeline</code></li><li><code>tabs</code> si la ficha tiene más de una sección</li><li>acción destructiva → <code>modal</code> de confirmación → <code>toast-host</code></li></ul></article>
        <article><h3>3 · Tablero de resumen</h3><p>Dirección y jefaturas: pocas cifras, tendencia clara.</p>
            <ul><li><code>dashboard-shell</code> + <code>sidebar</code> / <code>nav-item</code> con badge</li><li><code>stat</code> con sparkline en vez de <code>kpi</code></li><li><code>chart-bar</code> y <code>chart-donut</code> dentro de <code>card</code></li><li><code>ring</code> para metas; <code>timeline</code> de actividad reciente</li></ul></article>
        <article><h3>4 · Trámite en pasos</h3><p>Solicitudes ciudadanas (discapacidad, ARCOP, licencias).</p>
            <ul><li><code>stepper</code> arriba, un <code>card</code> por paso</li><li><code>input</code> / <code>select</code> / <code>calendar</code> / <code>file-dropzone</code></li><li><code>alert</code> para requisitos y errores</li><li><code>progress</code> de documentos; <code>accordion</code> de preguntas frecuentes</li></ul></article>
        <article><h3>5 · Acceso seguro</h3><p>Login con segundo factor.</p>
            <ul><li><code>auth-shell</code> + <code>gob-escudo</code> en el slot <code>logo</code></li><li><code>input</code> RUN + contraseña, luego <code>otp-input</code></li><li><code>switch</code> «recordar dispositivo»; <code>alert</code> de intento fallido</li></ul></article>
        <article><h3>6 · Marco institucional</h3><p>Lo que hace que cada subdominio se lea como municipal.</p>
            <ul><li><code>gob-bar</code> arriba (ya trae escudo y franja)</li><li><code>app-shell</code> o <code>dashboard-shell</code> al medio</li><li><code>gob-footer</code> abajo</li><li><code>command-palette</code> para saltar entre módulos</li></ul></article>
    </div>

    <h2 class="cat-group" id="solapes">Solapamientos: candidatos a fusionar</h2>
    <div class="cat-mix">
        <article><h3><code>kpi</code> ⊂ <code>stat</code></h3><p><code>stat</code> ya acepta <code>value</code>, <code>label</code>, <code>tone</code> y <code>hint</code>, y suma <code>delta</code> y <code>spark</code>. <code>kpi</code> podría pasar a ser un alias de <code>stat</code>.</p></article>
        <article><h3><code>data-table</code> ↔ <code>sortable-table</code></h3><p>Dos tablas con estilos distintos. Una sola tabla con <code>sortable</code> / <code>searchable</code> opcionales, que acepte slot o <code>rows</code>, evita que diverjan.</p></article>
        <article><h3><code>field</code> ↔ <code>input</code> / <code>select</code></h3><p><code>field</code> envuelve un control nativo con etiqueta en mayúsculas; <code>input</code> y <code>select</code> traen otra etiqueta propia. Dos estilos de label conviviendo en la misma pantalla.</p></article>
        <article><h3><code>app-shell</code> ↔ <code>dashboard-shell</code></h3><p><code>app-shell</code> usa <code>topbar</code>; <code>dashboard-shell</code> reimplementa su propia barra superior. Si reutilizara <code>topbar</code>, el estado online/degraded se vería igual en ambos.</p></article>
        <article><h3>Mapa de tonos repetido</h3><p>8 componentes (<code>kpi</code>, <code>stat</code>, <code>chart-bar</code>, <code>chart-donut</code>, <code>ring</code>, <code>progress</code>, <code>rating</code>, <code>timeline</code>) copian el mismo arreglo tono → <code>var(--muni-*-fg)</code>. Un token <code>--muni-tone</code> o un helper evitaría que se desalineen.</p></article>
    </div>
</main>
</div>
</body>
</html>
