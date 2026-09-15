@props([
    'title',
    'armazon' => 'dashboard',
    'system' => 'Municipalidad de Graneros',
    'systemSubtitle' => null,
    'documentTitle' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'migas' => [],
    'aviso' => null,
    'avisoTone' => 'info',
    'avisoTitle' => null,
    'theme' => null,
    'status' => 'online',
    'user' => null,
    'announcer' => true,
])

{{-- EL PUNTO DE PARTIDA DE TODA PANTALLA NUEVA — armazón, salto al contenido, migas,
     un solo <h1>, región de mensajes, contenido y pie, en una sola etiqueta.

     Es composición, no un armazón nuevo: rinde `dashboard-shell` (por defecto) o
     `app-shell`, que son los que ya traen el `<html>`, el enlace de salto y el
     `<main id="muni-contenido" tabindex="-1">`. SKILL.md lo dice con esas palabras:
     «no los rehagas». Lo que esta plantilla agrega es la banda que hoy cada
     aplicación arma a mano —y a la que siempre le falta la mitad—:

         <x-muni::plantilla-pantalla
             title="Patentes morosas"
             subtitle="3.412 contribuyentes con deuda vigente."
             system="Rentas y Patentes"
             :migas="[
                 ['label' => 'Inicio', 'url' => '/'],
                 ['label' => 'Patentes morosas'],
             ]"
             :aviso="session('estado')"
         >
             <x-slot:sidebar>…</x-slot:sidebar>
             <x-slot:actions><x-muni::button>Exportar</x-muni::button></x-slot:actions>

             … el contenido de la pantalla …
         </x-muni::plantilla-pantalla>

     Nueve decisiones, y ninguna es decorativa:

     1. UN SOLO <h1>, y sale de `page-header`. La pantalla se identifica por su
        título; dos <h1> es la forma más barata de perder a quien navega por
        encabezados. `title` es obligatorio a propósito: una pantalla sin título
        no se puede anunciar, ni poner en el <title> del documento, ni nombrar su
        región. Si igual llega en blanco, cae en el nombre del sistema (y este en
        el del municipio): nunca un <h1> vacío ni un `aria-label=""`.
     2. LAS MIGAS VAN ENCIMA DEL TÍTULO, dentro de la ranura que `page-header` ya
        tiene para eso, y las rinde `breadcrumb`, que emite la lista, el
        `aria-current="page"` de la última y el nombre del landmark. Sin migas no
        se emite contenedor alguno: una región de navegación vacía es ruido.
        `migas` es PROP (un arreglo), no ranura; si igual llega como ranura se
        rinde tal cual, porque descartarla en silencio dejaba la pantalla sin la
        ruta y sin un aviso en consola (DESIGN §8). «Hay migas» lo decide lo que
        `breadcrumb` emite, no el largo del arreglo: un arreglo cuyas migas no
        traen `label` las descarta todas y no puede dejar el contenedor vacío.
     3. LA REGIÓN DE MENSAJES EXISTE SIEMPRE. Es el hueco que la ficha señala:
        hoy los avisos van por `toast-host` y un error de servidor tras un POST no
        tiene dónde anunciarse. La pone `announcer`, UNA vez por pantalla, y desde
        ahí cualquier componente anuncia con `$dispatch('muni-announce', …)`.
        Con `wire:navigate` no hace falta apagarlo: cada pantalla trae el suyo y
        el nuevo releva al anterior (el anunciador lo resuelve con `isConnected`).
        `:announcer="false"` es para el anfitrión que monta el suyo propio DENTRO
        de la pantalla —en la ranura `topbar` bajo `@persist`, por ejemplo— y no
        quiere dos regiones. `announcer="false"` escrito como texto apaga igual.
     4. EL AVISO SE VE Y SE ANUNCIA UNA SOLA VEZ. Se ve en un recuadro
        (`alert`) porque un anuncio invisible no le sirve a quien mira la
        pantalla, y se anuncia por el anunciador, que es lo que funciona en una
        carga completa de página (POST → redirect). El recuadro va SIN
        `role="status"` a propósito: con los dos puestos, el lector lo diría dos
        veces. Con el anunciador APAGADO es al revés: el recuadro toma
        `role="status"` (o `role="alert"` si el tono es `danger`), porque si no
        el aviso se vería y no se anunciaría nunca. Bajo Livewire el aviso se
        manda con el evento, porque un morph no vuelve a disparar el `init()` del
        anunciador.
     5. EL PIE VA DENTRO DE LA SECCIÓN. Un `<footer>` cuyo antecesor de sección
        más cercano es un `<section>` queda como pie DE LA SECCIÓN; si colgara
        directo del `<main>` del armazón, el HTML lo expondría como `contentinfo`
        —un segundo landmark de pie, anidado dentro de otro landmark— que es lo
        que axe marca y lo que confunde a quien navega por landmarks. El pie
        institucional completo (`gob-footer`) va FUERA del `<main>`, y hoy ningún
        armazón tiene ranura para él: por eso acá va el pie de la PANTALLA, no el
        del sitio. Una ranura `pie` vacía lo apaga; una con contenido lo reemplaza.
        La línea de fábrica no repite el municipio cuando el sistema ES el
        municipio (el valor por defecto de `system`).
     6. LA REGIÓN DE CONTENIDO VA NOMBRADA con el título de la pantalla. Los tres
        armazones emiten `<main>` sin nombre y la plantilla no puede rotularlo
        desde adentro; lo que sí puede es nombrar su propia sección, que es lo que
        deja «Patentes morosas» en la lista de regiones del lector.
     7. EL FOCO NO SE ESCONDE DEBAJO DE LA CABECERA. El topbar de los dos
        armazones es `position: sticky` (arriba, z-index 40), así que
        todo lo enfocable dentro de la banda reserva su alto con
        `scroll-margin-top`: sin eso, lo que ya está en la ventana pero bajo la
        cabecera se da por visible y el anillo queda tapado. Es la última tecla
        obligatoria de la ficha, y está medida en navegador con contraprueba.
     8. LO ESCRITO A MANO EN LA PANTALLA TAMBIÉN CUMPLE. Un enlace o un botón
        suelto del contenido recibe el anillo de 3 px y el color de enlace del
        token, con especificidad mínima para que el foco y el color propios de
        cada componente sigan ganando. Sin eso, en el armazón de panel quedaban el
        anillo de 1 px del navegador y el azul del navegador a 2,05:1 en oscuro.
     9. EL PAQUETE NO SABE QUIÉN MIRA. Nada de `request()`, `Auth`, `Gate` ni
        permisos: el título, las migas y el aviso llegan ya redactados por el
        anfitrión (Ley 21.719, minimización). El menú lateral también: se pasa por
        la ranura `sidebar` ya filtrado.

     Lo que la plantilla NO hace, a propósito:

     · No sirve para la pantalla de acceso. `auth-shell` no tiene migas, ni
       acciones de cabecera, ni barra lateral, y su `title` es el del documento:
       forzarlo acá sería una plantilla que miente en la mitad de sus props.
     · No inventa el menú ni las acciones: sin ranura `sidebar` el armazón de
       panel queda sin barra, que es lo correcto para una pantalla suelta.
     · `sidebar` solo lo usa el armazón de panel; con `armazon="app"` se ignora.
     · No decide el tema: `theme` sin valor sigue al sistema operativo, igual que
       en los armazones. Dentro de un panel Filament esta plantilla no se usa: el
       panel trae su propio armazón y su propio <h1>. --}}

@php
    /*
     * El armazón se elige por nombre y un valor desconocido cae en el de panel:
     * quedarse sin armazón dejaría la pantalla sin <html>, sin enlace de salto y
     * sin el <main> destino del salto, que es justo lo que esta plantilla existe
     * para garantizar.
     */
    $armazonComponente = in_array($armazon, ['dashboard', 'app'], true)
        ? 'muni::'.$armazon.'-shell'
        : 'muni::dashboard-shell';

    $municipio = 'Municipalidad de Graneros';
    $nombreSistema = is_string($system) ? trim($system) : '';

    /*
     * Un título en blanco dejaba aria-label="" (una región sin nombre) y un <h1>
     * vacío. Cae en el nombre del sistema, y este en el del municipio: es lo único
     * que la plantilla sabe de la pantalla. En ese caso el <title> no se duplica.
     */
    $tituloPropio = trim((string) $title);
    $tituloPantalla = $tituloPropio !== '' ? $tituloPropio : ($nombreSistema !== '' ? $nombreSistema : $municipio);
    $tituloCalculado = $tituloPropio !== '' && $nombreSistema !== ''
        ? $tituloPantalla.' · '.$nombreSistema
        : $tituloPantalla;
    $tituloDocumento = $documentTitle !== null && trim((string) $documentTitle) !== ''
        ? (string) $documentTitle
        : $tituloCalculado;

    /*
     * Las migas llegan como arreglo (o Collection). Si el anfitrión igual manda una
     * ranura, se rinde tal cual en vez de desaparecer sin avisar. Lo que decide es
     * si es iterable, no su clase: una ranura y una cadena se tratan igual.
     */
    $migasEsLista = is_iterable($migas);
    $migasRanura = $migasEsLista ? null : $migas;

    /*
     * La lista se rinde ACÁ, con el mismo `breadcrumb`, y lo que decide si hay
     * migas es su salida y no el largo del arreglo: `breadcrumb` descarta las
     * migas sin `label` legible y, si no queda ninguna, no emite nada. Contar el
     * arreglo dejaba un contenedor vacío con su margen encima del <h1>. Rendirlo
     * una vez evita copiar aquí la regla de descarte, que se desincronizaría.
     * La salida es la de `breadcrumb`, que ya escapa cada texto.
     *
     * Lo que cuenta es el <nav>, no que la salida tenga algo: `breadcrumb` emite
     * su bloque de estilos de una sola vez aunque no quede ninguna miga. No se
     * tira —esa única vez ya quedó gastada y otra ruta de la página saldría sin
     * estilos—: sin migas se imprime suelto, antes de la cabecera, donde no ocupa
     * lugar.
     */
    $migasHtml = $migasEsLista && collect($migas)->isNotEmpty()
        ? trim(\Illuminate\Support\Facades\Blade::render(
            '<x-muni::breadcrumb :items="$items" />',
            ['items' => collect($migas)->all()]
        ))
        : '';
    $hayMigas = $migasEsLista ? str_contains($migasHtml, '<nav') : trim((string) $migasRanura) !== '';
    // HtmlString y no la impresión cruda: la salida ya viene escapada por `breadcrumb`, y así
    // no queda en el marcado una impresión cruda que otro tenga que auditar.
    $restoDeMigas = new \Illuminate\Support\HtmlString($migasEsLista && ! $hayMigas ? $migasHtml : '');
    $migasHtml = new \Illuminate\Support\HtmlString($migasHtml);

    $textoAviso = $aviso === null ? '' : trim((string) $aviso);
    $tonos = ['ok', 'warn', 'danger', 'info'];
    $tonoAviso = in_array($avisoTone, $tonos, true) ? $avisoTone : 'info';
    // Solo el tono grave interrumpe lo que el lector esté diciendo; el resto espera turno.
    $avisoAsertivo = $tonoAviso === 'danger';

    /*
     * `announcer="false"` escrito como texto llega como la cadena "false", que es
     * verdadera en PHP: el anfitrión creía apagarlo y obtenía dos regiones. Se lee
     * como booleano; lo que no se entiende deja el anunciador puesto.
     */
    $conAnunciador = is_bool($announcer)
        ? $announcer
        : (filter_var($announcer, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true);

    /*
     * Con el anunciador puesto el recuadro va SIN rol (se diría dos veces). Con el
     * anunciador apagado el recuadro es el único canal: toma el rol, o el aviso
     * se ve y no se anuncia nunca.
     */
    $rolAviso = $conAnunciador ? null : ($avisoAsertivo ? 'alert' : 'status');

    // Ranura de pie vacía = pie apagado; es la única forma de quitarlo sin una prop
    // que compita con la ranura por el mismo nombre (DESIGN §8).
    $pieRanura = $pie ?? null;
    $hayPie = $pieRanura === null || trim((string) $pieRanura) !== '';

    /*
     * La línea del pie se arma acá y no en el marcado. Escrita en línea
     * —«Graneros» pegado a una directiva— Blade NO la compila: su expresión exige
     * que el arroba no venga pegado a una palabra, así que la condición se imprime
     * como texto y el cierre sí se compila. El resultado es «unexpected endif», y
     * el error apunta al archivo compilado, no a la línea del marcado.
     */
    $sistemaNormalizado = mb_strtolower((string) preg_replace('/\s+/u', ' ', $nombreSistema));
    $sistemaEsElMunicipio = in_array($sistemaNormalizado, ['municipalidad de graneros', 'ilustre municipalidad de graneros'], true);
    $lineaDelPie = '© '.date('Y').' Ilustre '.$municipio
        .($nombreSistema !== '' && ! $sistemaEsElMunicipio ? ' · '.$nombreSistema : '');
@endphp

{{-- El armazón se elige con el componente dinámico: una sola banda, sin duplicarla
     por rama. OJO al probarlo: una ranura cerrada y pegada a texto
     —`</x-slot:pie>Contenido`— compila a una directiva desconocida, la ranura
     nunca se cierra y queda un búfer de salida abierto. Es la misma trampa del
     arroba pegado a una palabra; el marcado de acá no la tiene. --}}
<x-dynamic-component
    :component="$armazonComponente"
    :title="$tituloDocumento"
    :system="$system"
    :subtitle="$systemSubtitle"
    :theme="$theme"
    :status="$status"
    :user="$user"
>
    <x-slot:head>
        {{-- El bloque de estilos viaja DENTRO del componente (DESIGN §7) y entra por
             la ranura del <head> del armazón: puesto después del armazón quedaría
             detrás de </html>, que el navegador reubica en el <body> —funciona de
             casualidad— y en el fuente parece un documento roto. --}}
        @once
            <style>
                .muni-plantilla { display: block; }
                .muni-plantilla__aviso { margin: 0 0 18px; }
                .muni-plantilla__cuerpo { display: block; }

                /* El topbar de los dos armazones es sticky. Sin reservar su alto, cuando
                   lo que recibe el foco ya está dentro de la ventana pero en la franja
                   de la cabecera, el navegador lo da por visible y no desplaza: el
                   anillo queda debajo de la cabecera. Medido en Chromium y Firefox
                   con la reserva anulada: borde del anillo a 15 px, cabecera a 56.
                   La reserva va sobre TODO lo enfocable y no sobre el estado de foco:
                   así vale igual para el foco por programa y para el destino de un
                   ancla, y ninguna regla de foco queda sin su outline. */
                :where(.muni-plantilla) :where(a[href], button, input, select, textarea, summary, [tabindex], [id]) { scroll-margin-top: calc(var(--muni-topbar-h) + 12px); }

                .muni-plantilla__pie {
                    margin: 32px 0 0; padding: 14px 0 0;
                    border-top: 1px solid var(--muni-border);
                    color: var(--muni-muted);
                    font-family: var(--muni-font-sans); font-size: 12.5px; line-height: 1.5;
                }
                .muni-plantilla__pie p { margin: 0; }

                /* EL COLOR DE BASE de los enlaces del contenido. `app-shell` ya lo pone
                   en su documento y `dashboard-shell` no: medido con la reja, un enlace
                   escrito a mano en la pantalla de panel quedaba con el azul del
                   navegador, 2,05:1 en oscuro. Especificidad cero —todo va dentro de
                   :where—, así que el color propio de cualquier componente gana. */
                :where(.muni-plantilla) :where(a[href]) { color: var(--muni-accent); text-underline-offset: 3px; }

                /* EL ANILLO DE BASE del contenido de la pantalla. Sin esto, un enlace o
                   un botón escrito a mano dentro de la pantalla recibe el anillo del
                   navegador: 1 px automático, medido así en Chromium y en Firefox.
                   El indicador REAL es el outline: la box-shadow se pierde dentro de
                   Filament (DESIGN §5).

                   Va con especificidad de UNA clase —las dos envolturas de :where no
                   suman—, así que cualquier componente que declare su propio foco con
                   clase y pseudoclase le gana sin pelear. Quedan fuera los elementos
                   con tabindex -1: reciben el foco por programa (el título de
                   pantalla-resultado, el propio main del salto) y un anillo ahí es
                   ruido para quien no usa el teclado. */
                :where(.muni-plantilla) :where(a[href], button, input, select, textarea, summary, [tabindex]:not([tabindex="-1"])):focus-visible {
                    outline: 3px solid var(--muni-focus, var(--muni-accent, #767676));
                    outline-offset: 2px;
                }

                /* En papel el pie se lee con el color del texto, no atenuado. */
                @media print {
                    .muni-plantilla__pie { margin-top: 18px; color: var(--muni-text); }
                }
            </style>
        @endonce

        {{ $head ?? '' }}
    </x-slot:head>
    <x-slot:sidebar>{{ $sidebar ?? '' }}</x-slot:sidebar>
    <x-slot:topbar>{{ $topbar ?? '' }}</x-slot:topbar>

    <section {{ $attributes->merge(['class' => 'muni-plantilla', 'aria-label' => $tituloPantalla]) }}>
        @if ($conAnunciador)
            <x-muni::announcer :message="$textoAviso !== '' ? $textoAviso : null" :assertive="$avisoAsertivo" />
        @endif

        {{ $restoDeMigas }}

        <x-muni::page-header :title="$tituloPantalla" :subtitle="$subtitle" :eyebrow="$eyebrow">
            @if ($hayMigas)
                <x-slot:migas>
                    @if ($migasRanura !== null)
                        {{ $migasRanura }}
                    @else
                        {{ $migasHtml }}
                    @endif
                </x-slot:migas>
            @endif

            @isset($actions)
                <x-slot:actions>{{ $actions }}</x-slot:actions>
            @endisset
        </x-muni::page-header>

        @if ($textoAviso !== '')
            <div class="muni-plantilla__aviso">
                <x-muni::alert :tone="$tonoAviso" :title="$avisoTitle" :role="$rolAviso">{{ $textoAviso }}</x-muni::alert>
            </div>
        @endif

        <div class="muni-plantilla__cuerpo">{{ $slot }}</div>

        @if ($hayPie)
            <footer class="muni-plantilla__pie">
                @if ($pieRanura !== null)
                    {{ $pieRanura }}
                @else
                    <p>{{ $lineaDelPie }}</p>
                @endif
            </footer>
        @endif
    </section>
</x-dynamic-component>
