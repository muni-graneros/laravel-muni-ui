{{--
    Vitrina de desarrollo de laravel-muni-ui (workbench; nunca se instala en un sistema).

    Esta vista NO conoce ningún componente por nombre: recibe $piezas, que la ruta
    arma recorriendo resources/views/components/, y pinta la misma tarjeta para
    todas. Los tokens salen de la hoja del paquete, cargada tal cual; la hoja
    propia de acá solo dispone (rejilla, tarjeta, conmutadores) y no declara ni
    un solo token de la paleta, que es el defecto que hizo divergir a showcase.html.
--}}
@php
    $conError = array_values(array_filter($piezas, fn ($p) => $p['error'] !== null));
    $sinRegistro = array_values(array_filter($piezas, fn ($p) => ! $p['enRegistro']));
@endphp
<!doctype html>
<html lang="es-CL"@if ($atributoTema) data-muni-theme="{{ $atributoTema }}"@endif @if ($densidad === 'compacta') data-muni-densidad="compacta"@endif>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vitrina · laravel-muni-ui</title>
<style data-vitrina="muni-ui.css">{!! $css !!}</style>
<style data-vitrina="propia">
    /* Solo disposición. Colores, radios y tipografía: tokens del paquete. */
    html { color-scheme: light dark; }
    .vt { margin: 0; background: var(--muni-bg); color: var(--muni-text); font-family: var(--muni-font-sans); line-height: 1.5; }
    .vt-salto { position: absolute; left: 1rem; top: -100px; padding: .5rem 1rem; background: var(--muni-surface); color: var(--muni-text); border: 1px solid var(--muni-border); border-radius: var(--muni-radius-sm); }
    .vt-salto:focus { top: 1rem; }
    .vt a:focus-visible, .vt button:focus-visible, .vt summary:focus-visible, .vt iframe:focus-visible, .vt main:focus-visible {
        outline: 3px solid var(--muni-focus, var(--muni-accent, #767676));
        outline-offset: 2px;
    }
    .vt main:focus:not(:focus-visible) { outline: none; }
    .vt-cabecera { padding: 1.5rem clamp(1rem, 4vw, 2.5rem); border-bottom: 1px solid var(--muni-border); background: var(--muni-surface); }
    .vt-cabecera h1 { margin: 0 0 .25rem; font-size: 1.5rem; }
    .vt-cabecera p { margin: 0; color: var(--muni-muted); }
    .vt-controles { display: flex; flex-wrap: wrap; gap: 1rem 2rem; margin-top: 1rem; }
    .vt-grupo { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .vt-grupo > span { font-weight: 600; margin-right: .25rem; }
    .vt-boton {
        min-height: 44px; min-width: 44px; padding: .5rem .9rem; cursor: pointer;
        font: inherit; font-weight: 600; color: var(--muni-text); background: var(--muni-surface-2);
        border: 1px solid var(--muni-border-2); border-radius: var(--muni-radius-sm);
        transition: background var(--muni-dur) var(--muni-ease), border-color var(--muni-dur) var(--muni-ease);
    }
    .vt-boton[aria-pressed="true"] { background: var(--muni-accent); color: var(--muni-on-accent); border-color: var(--muni-accent-strong); }
    .vt-boton[aria-pressed="true"]::before { content: "✓ "; }
    .vt-aviso { margin-top: 1rem; padding: .75rem 1rem; border: 1px solid var(--muni-warn-border); background: var(--muni-warn-bg); color: var(--muni-warn-fg); border-radius: var(--muni-radius-sm); }
    .vt-cuerpo { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; padding: 1.5rem clamp(1rem, 4vw, 2.5rem); }
    @media (min-width: 1100px) { .vt-cuerpo { grid-template-columns: 15rem minmax(0, 1fr); align-items: start; } }
    .vt-indice ul { list-style: none; margin: 0; padding: 0; columns: 2 11rem; column-gap: 1rem; }
    @media (min-width: 1100px) { .vt-indice ul { columns: 1; } }
    .vt-indice h2 { font-size: 1rem; margin: 0 0 .5rem; }
    .vt-indice a { display: inline-flex; align-items: center; min-height: 28px; color: var(--muni-text); font-family: var(--muni-font-mono); font-size: .85rem; }
    .vt-rejilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 26rem), 1fr)); gap: 1.25rem; }
    .vt-pieza { min-width: 0; background: var(--muni-surface); border: 1px solid var(--muni-border); border-radius: var(--muni-radius-lg); scroll-margin-top: 1rem; }
    .vt-pieza > header { padding: .9rem 1.1rem; border-bottom: 1px solid var(--muni-border); }
    .vt-pieza h2 { margin: 0; font-size: 1rem; font-family: var(--muni-font-mono); overflow-wrap: anywhere; }
    .vt-pieza header p { margin: .25rem 0 0; font-size: .875rem; color: var(--muni-muted); }
    .vt-muestra { padding: 1.1rem; overflow-x: auto; }
    .vt-muestra--armazon iframe { display: block; width: 100%; height: 26rem; border: 1px solid var(--muni-border); border-radius: var(--muni-radius-sm); background: var(--muni-bg); }
    .vt-muestra--armazon p { margin: .5rem 0 0; }
    .vt-muestra--armazon a { color: var(--muni-text); }
    .vt-fallo { padding: .75rem 1rem; border: 1px solid var(--muni-danger-border); background: var(--muni-danger-bg); color: var(--muni-danger-fg); border-radius: var(--muni-radius-sm); overflow-wrap: anywhere; }
    .vt-fuente { border-top: 1px solid var(--muni-border); padding: .5rem 1.1rem; font-size: .85rem; }
    .vt-fuente summary { cursor: pointer; min-height: 28px; display: flex; align-items: center; }
    .vt-fuente pre { margin: .5rem 0; padding: .75rem; overflow-x: auto; white-space: pre-wrap; background: var(--muni-surface-2); color: var(--muni-text); border-radius: var(--muni-radius-sm); font-family: var(--muni-font-mono); font-size: .8rem; }
    @media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
</style>
</head>
<body class="vt"
    data-tema="{{ $tema }}" data-densidad="{{ $densidad }}"
    x-data="{
        tema: $el.dataset.tema,
        densidad: $el.dataset.densidad,
        pintar() {
            const raiz = document.documentElement;
            if (this.tema === 'sistema') { raiz.removeAttribute('data-muni-theme'); }
            else { raiz.setAttribute('data-muni-theme', this.tema === 'oscuro' ? 'dark' : 'light'); }
            if (this.densidad === 'compacta') { raiz.setAttribute('data-muni-densidad', 'compacta'); }
            else { raiz.removeAttribute('data-muni-densidad'); }
            const url = new URL(window.location.href);
            this.tema === 'sistema' ? url.searchParams.delete('tema') : url.searchParams.set('tema', this.tema);
            this.densidad === 'compacta' ? url.searchParams.set('densidad', 'compacta') : url.searchParams.delete('densidad');
            window.history.replaceState(null, '', url);
        }
    }"
    x-effect="pintar()">
<span id="vt-inicio" tabindex="-1"></span>
<a class="vt-salto" href="#vitrina-contenido">Saltar a los componentes</a>

<header class="vt-cabecera">
    <h1>Vitrina de laravel-muni-ui</h1>
    <p>{{ count($piezas) }} componentes, generados recorriendo <code>resources/views/components/</code>. Datos ficticios.</p>

    <div class="vt-controles">
        <div class="vt-grupo" role="group" aria-labelledby="vt-rotulo-tema">
            <span id="vt-rotulo-tema">Tema</span>
            @foreach (['sistema' => 'Sistema', 'claro' => 'Claro', 'oscuro' => 'Oscuro'] as $valor => $rotulo)
                <button type="button" class="vt-boton" data-vitrina-tema="{{ $valor }}" aria-pressed="{{ $tema === $valor ? 'true' : 'false' }}" :aria-pressed="tema === $el.dataset.vitrinaTema ? 'true' : 'false'" x-on:click="tema = $el.dataset.vitrinaTema">{{ $rotulo }}</button>
            @endforeach
        </div>
        <div class="vt-grupo" role="group" aria-labelledby="vt-rotulo-densidad">
            <span id="vt-rotulo-densidad">Densidad de tabla</span>
            @foreach (['comoda' => 'Cómoda', 'compacta' => 'Compacta'] as $valor => $rotulo)
                <button type="button" class="vt-boton" data-vitrina-densidad="{{ $valor }}" aria-pressed="{{ $densidad === $valor ? 'true' : 'false' }}" :aria-pressed="densidad === $el.dataset.vitrinaDensidad ? 'true' : 'false'" x-on:click="densidad = $el.dataset.vitrinaDensidad">{{ $rotulo }}</button>
            @endforeach
        </div>
    </div>

    @if ($alpine['core'] === null || $alpine['focus'] === null)
        <p class="vt-aviso" role="status">Aviso: no hay copia de Alpine 3 o de su plugin Focus en <code>node_modules/</code>. Lo interactivo no hidrata; corre <code>npm install</code>.</p>
    @endif
    @if ($conError !== [])
        <p class="vt-aviso" role="alert">Error: {{ count($conError) }} componente(s) no se pudieron renderizar: {{ implode(', ', array_column($conError, 'nombre')) }}.</p>
    @endif
    @if ($sinRegistro !== [])
        <p class="vt-aviso">Sin entrada en <code>registry.json</code> ({{ count($sinRegistro) }}): {{ implode(', ', array_column($sinRegistro, 'nombre')) }}.</p>
    @endif
</header>

<div class="vt-cuerpo">
    <nav class="vt-indice" aria-labelledby="vt-rotulo-indice">
        <h2 id="vt-rotulo-indice">Componentes</h2>
        <ul>
            @foreach ($piezas as $pieza)
                <li><a href="#pieza-{{ $pieza['nombre'] }}">{{ $pieza['nombre'] }}</a></li>
            @endforeach
        </ul>
    </nav>

    <main id="vitrina-contenido" tabindex="-1">
        <div class="vt-rejilla">
            @foreach ($piezas as $pieza)
                <section class="vt-pieza" id="pieza-{{ $pieza['nombre'] }}" data-vitrina-pieza="{{ $pieza['nombre'] }}" aria-labelledby="pieza-{{ $pieza['nombre'] }}-titulo">
                    <header>
                        <h2 id="pieza-{{ $pieza['nombre'] }}-titulo">{{ '<x-muni::'.$pieza['nombre'].'>' }}</h2>
                        @if ($pieza['descripcion'])
                            <p>{{ $pieza['descripcion'] }}</p>
                        @endif
                        <p>
                            {{ $pieza['conEjemplo'] ? 'Con datos de ejemplo de la vitrina.' : 'Con sus valores por defecto.' }}
                            @if ($pieza['alpine']) Usa Alpine. @endif
                            @unless ($pieza['enRegistro']) Sin entrada en registry.json. @endunless
                        </p>
                    </header>

                    @if ($pieza['armazon'])
                        <div class="vt-muestra vt-muestra--armazon" data-vitrina-armazon="{{ $pieza['nombre'] }}">
                            <iframe title="{{ 'Armazón <x-muni::'.$pieza['nombre'].'> en su propia página' }}" loading="lazy"
                                src="{{ url('/vitrina/armazon/'.$pieza['nombre']) }}{{ $tema === 'sistema' ? '' : '?tema='.$tema }}"
                                data-base="{{ url('/vitrina/armazon/'.$pieza['nombre']) }}"
                                :src="$el.dataset.base + (tema === 'sistema' ? '' : '?tema=' + tema)"></iframe>
                            <p>Emite su propio documento, así que se muestra aparte: <a href="{{ url('/vitrina/armazon/'.$pieza['nombre']) }}">abrir {{ $pieza['nombre'] }} en su página</a>.</p>
                        </div>
                    @elseif ($pieza['error'] !== null)
                        <div class="vt-muestra">
                            <p class="vt-fallo" data-vitrina-fallo="{{ $pieza['nombre'] }}"><strong>No se pudo renderizar.</strong> {{ $pieza['error'] }}</p>
                        </div>
                    @else
                        <div class="vt-muestra">{!! $pieza['html'] !!}</div>
                    @endif

                    <details class="vt-fuente">
                        <summary>Ver el Blade</summary>
                        <pre><code>{{ $pieza['blade'] }}</code></pre>
                    </details>
                </section>
            @endforeach
        </div>
    </main>
</div>

{{-- Varios componentes toman el foco al hidratar (error-summary, pantalla-resultado…): están
     pensados para ser lo único de su pantalla. Con 88 juntos, el primero que lo toma deja al
     teclado a mitad de página. Tras hidratar, el foco vuelve al inicio del documento, así el
     primer Tab cae en el enlace de salto. --}}
<script data-vitrina="foco-inicial">
    document.addEventListener('alpine:initialized', function () {
        requestAnimationFrame(function () {
            if (location.hash) { return; }
            var inicio = document.getElementById('vt-inicio');
            inicio.focus({ preventScroll: true });
            inicio.blur();
            window.scrollTo(0, 0);
        });
    });
</script>
@if ($alpine['focus'] !== null)
<script data-vitrina="alpine-focus">{!! $alpine['focus'] !!}</script>
@endif
@if ($alpine['core'] !== null)
<script data-vitrina="alpine-core">{!! $alpine['core'] !!}</script>
@endif
</body>
</html>
