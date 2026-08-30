/**
 * Le devuelve el nombre a los selects CON BUSCADOR del panel.
 *
 * Filament no pinta un `<select>` cuando el campo es `searchable()`: pinta un
 * botón que abre una lista, y deja el `<label>` apuntando con `for` a un id que
 * NO existe en la página. El resultado, medido en el navegador, es que quien
 * navega con lector de pantalla oye «Seleccione una opción» en vez de la
 * etiqueta del campo. Es WCAG 4.1.2 (nombre, función, valor), y afecta al campo
 * más importante de la recepción de solicitudes ARCOP: el titular de los datos.
 *
 * No hay forma de arreglarlo desde el campo: `extraInputAttributes()` -la vía
 * que documenta Filament para esto- no alcanza al botón, comprobado. Así que se
 * corrige acá, una vez, para TODOS los paneles del ecosistema.
 *
 * Qué hace: por cada etiqueta cuyo `for` no resuelve a ningún elemento, le pone
 * un id a la etiqueta y apunta el botón del campo con `aria-labelledby`. No
 * toca los campos que ya están bien, ni los que ya traen nombre propio.
 *
 * Se inyecta con `<script data-navigate-once src="…">` (ver MuniPanel): sin ese
 * atributo, cada navegación por wire:navigate volvería a registrar el
 * observador encima del anterior.
 */
(function () {
    var contador = 0;

    function nombrarSelectsConBuscador() {
        // `.fi-fo-field` es el contenedor que comparten la etiqueta y el
        // control; `.fi-fo-field-wrp` NO los agrupa a los dos.
        var etiquetas = document.querySelectorAll('.fi-fo-field label[for]');

        for (var i = 0; i < etiquetas.length; i++) {
            var etiqueta = etiquetas[i];

            // Si el `for` resuelve, el campo ya está bien: no se toca.
            if (document.getElementById(etiqueta.getAttribute('for'))) {
                continue;
            }

            var campo = etiqueta.closest('.fi-fo-field');
            var control = campo && campo.querySelector('button');

            if (! control) {
                continue;
            }

            // Respetar un nombre puesto a mano.
            if (control.getAttribute('aria-label') || control.getAttribute('aria-labelledby')) {
                continue;
            }

            if (! etiqueta.id) {
                etiqueta.id = 'muni-ui-etiqueta-' + (++contador);
            }

            control.setAttribute('aria-labelledby', etiqueta.id);
        }
    }

    // El formulario se redibuja con cada respuesta de Livewire —al elegir una
    // opción, al validar—, así que no alcanza con recorrerlo una vez al cargar.
    var observador = new MutationObserver(function () {
        nombrarSelectsConBuscador();
    });

    function arrancar() {
        nombrarSelectsConBuscador();
        observador.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }

    // `DOMContentLoaded` no se emite en una navegación SPA.
    document.addEventListener('livewire:navigated', nombrarSelectsConBuscador);
})();
