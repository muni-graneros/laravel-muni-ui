@props([
    'label' => null,
    /*
     * `name` es una prop ACEPTADA Y SIN EFECTO, documentada a propósito: no es
     * un olvido y no hay nada que «arreglar» acá sin romper algo.
     *
     * Medido antes de decidir —`grep -rn "x-muni::field" ~/Dev`, 2026-09-10—:
     * fuera del propio paquete (README, registry.json, INVENTORY.md y el
     * comentario de filter-bar) NO hay un solo consumidor de esta envoltura, y
     * ninguno de esos ejemplos pasa `name`. O sea: cero llamadas que la usen.
     *
     * Por qué no se emite un `for` con ella, que era la otra salida: la
     * asociación de este campo hoy es IMPLÍCITA —el control va dentro del
     * <label>— y funciona. El HTML dice que si `for` está presente, el control
     * etiquetado es el elemento con ese id, y si ese id no existe en el
     * documento el <label> se queda SIN control etiquetado: el descendiente ya
     * no cuenta. Como el slot trae el control del anfitrión —el ejemplo del
     * README es un <input> crudo, sin id—, esta envoltura no puede garantizar el
     * destino, y un `for` colgando convertiría un contrato roto (que no daña a
     * nadie) en una falla real de 4.1.2: el campo se quedaría sin nombre
     * accesible.
     *
     * Por qué no se quita: el paquete es aditivo mientras la versión sea 0.x y
     * haya nueve sistemas consumiendo (DESIGN §10). Quitar la prop haría que un
     * `name="estado"` cayera a la bolsa de atributos y se derramara al HTML como
     * un atributo `name` sobre el <label>, que ahí no significa nada.
     *
     * Si algún día hace falta la asociación explícita, el camino es que el
     * control del slot sea x-muni::input o x-muni::select con el mismo `name`
     * —ellos sí generan un id determinista `muni-<name>`— y recién entonces
     * emitir el `for`. Eso es un cambio de contrato, con su prueba, no un
     * retoque.
     */
    'name' => null,
])

{{-- Envoltura de campo para la filter-bar: label + control (input/select en el slot).
     La asociación es implícita por anidamiento; ver arriba por qué no hay `for`. --}}
<label style="display:flex;flex-direction:column;gap:4px;">
    @if ($label)
        <span style="font-family:var(--muni-font-sans);font-size:11px;font-weight:600;color:var(--muni-muted);text-transform:uppercase;letter-spacing:.03em;">{{ $label }}</span>
    @endif
    {{ $slot }}
</label>
