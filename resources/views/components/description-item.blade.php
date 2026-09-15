@props([
    /*
     * El rótulo del dato. Va como <dt> y sale escapado: en una ficha municipal
     * hasta la etiqueta puede venir de una configuración del anfitrión.
     *
     * OBLIGATORIA, sin valor por defecto: un <dd> sin su <dt> rotulado no es un
     * par dato-valor, es un valor suelto que el lector de pantalla anuncia sin
     * decir de qué (WCAG 2.2 AA 1.3.1). Por eso el candado de humo de
     * `tests/TodosRendericanTest.php` la lleva en `propsObligatorias()`:
     *
     *     'description-item' => 'label="RUT"',
     */
    'label',
    /*
     * Marca el valor con `.muni-num`: mono tabular. Es para RUT, folios, montos
     * y fechas — lo que se lee en columna y se compara de un vistazo. Un
     * domicilio en mono se lee peor, así que no es el valor por defecto.
     */
    'mono' => false,
    /*
     * Lo que oye el lector de pantalla cuando el dato no está. En la pantalla se
     * ve una raya; la raya sola se lee «guion» o no se lee, y el funcionario no
     * puede distinguir «no hay dato» de «la pantalla se cortó».
     */
    'empty' => 'Sin dato',
])

@php
    $muniDiMono = filter_var($mono, FILTER_VALIDATE_BOOLEAN);

    /* El valor va por SLOT, no por prop: tiene que admitir un badge de estado,
       un enlace al expediente o un botón de copiar el RUT. Una API de array de
       pares se queda corta en el primer caso real y todos vuelven al <div>. */
    $muniDiSinDato = trim((string) $slot) === '';

    /* Sin `mono` y sin clase del consumidor no se toca el `class`: pedirle la
       clase a la bolsa igual dejaría un `class=""` vacío en el <dd> de cada par
       de cada ficha. Con `mono`, `class()` CONCATENA con la del consumidor en
       vez de pisarla (DESIGN §8). */
    $muniDiAtributos = $muniDiMono ? $attributes->class(['muni-num']) : $attributes;
@endphp

{{-- UN PAR DE LA FICHA. Emite <dt> y <dd> SUELTOS, sin envoltorio: tienen que
     ser hijos directos del <dl> de <x-muni::description-list> para que la
     rejilla alinee las etiquetas en columna.

     Los atributos del consumidor se derraman sobre el <dd>, que es el lado que
     alguien querría enganchar (`data-*`, `wire:`, una clase propia). `class` se
     concatena por merge, así que una clase del consumidor no borra `.muni-num`.

     Ley 21.719: el valor llega como string YA redactado por el anfitrión. El
     componente no recibe modelos ni registros completos, y por eso no hay
     ninguna prop que acepte un array del que sacar todas las claves.

     QUIÉN ESCAPA QUÉ, sin adornos. La etiqueta y el texto de «sin dato» los
     emite este componente y salen escapados. El VALOR no: entra por slot, y un
     slot es Htmlable —`{{ $slot }}` compila a `e($slot)`, que en un Htmlable
     devuelve `toHtml()` TAL CUAL—, así que el marcado del slot llega crudo al
     <dd>. Tiene que ser así: sin eso no hay badge de estado, ni enlace al
     expediente, ni botón de copiar el RUT, que es la mitigación que el juez
     exigió. Escapar el valor es del anfitrión, al interpolarlo con `{{ }}` en
     SU vista; acá dentro no hay un solo `{!! !!}` y hay una prueba que lo fija. --}}
<dt>{{ $label }}</dt>
<dd {{ $muniDiAtributos }}>@if ($muniDiSinDato)<span aria-hidden="true" style="color:var(--muni-muted);">—</span><span class="muni-sr">{{ $empty }}</span>@else{{ $slot }}@endif</dd>

{{-- Este componente NO lleva bloque de estilos, y no es un olvido: se renderiza
     DENTRO del <dl>, y el modelo de contenido de <dl> solo admite dt, dd y
     elementos de soporte de script (script y template). Un <style> ahí es HTML
     inválido y la regla `definition-list` de axe lo levanta como violación.
     Las clases que este par usa —`.muni-num` y `.muni-sr`— las emite el bloque
     de una sola emisión de <x-muni::description-list>, que es su único padre
     posible. Ahí está el comentario que explica por qué la mono viaja con el
     componente y no se hereda de la tabla. --}}
