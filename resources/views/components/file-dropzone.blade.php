@props([
    'name' => 'file',
    'accept' => 'image/*,application/pdf',
    'label' => 'Arrastra un archivo o haz clic para subir',
    'hint' => 'PDF o imagen, hasta 10 MB',
    'multiple' => false,
    'maxMb' => 10,
])

@php
    /*
     * Identificador de la zona. NUNCA uniqid() (DESIGN §8): cambia en cada render, así
     * que bajo Livewire el `for` de la etiqueta y el `aria-describedby` de la ayuda
     * quedan apuntando a un id que ya no existe, y el diff reemplaza nodos que no
     * cambiaron. Sale del `name`, saneado —un name con notación de arreglo produciría
     * un id inválido— y con un trozo de hash para que dos zonas con nombres que se
     * sanean igual no colisionen. El `id` que pase el consumidor manda sobre todo.
     */
    $dzId = $attributes->get('id')
        ?: 'muni-dz-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $name), '-')
            .'-'.substr(sha1((string) $name), 0, 6);
@endphp

{{-- Zona de carga de archivos (Alpine 3 core). Arrastrar y soltar + lista de lo adjuntado.
     El `<input type="file">` REAL sigue siendo el control —envuelto, nunca sustituido—: de él
     vienen gratis el arrastrar y soltar nativo, la activación con Enter y con Espacio, y el
     archivo que viaja en el submit del formulario.

     Tres cosas que no se pueden volver a romper:

     1. NINGÚN texto del anfitrión entra en una expresión de Alpine. `{{ $label }}` vivía dentro
        de una cadena de comillas simples de un `x-text`: Blade escapa el apóstrofo a `&#039;`, el
        navegador lo decodifica DENTRO del valor del atributo y la expresión queda descuadrada, así
        que Alpine tumbaba la página entera —no solo el componente—. Y un label venido de
        configuración con `'+fetch(...)+'` se ejecutaba. Ahora el texto es contenido Blade escapado
        y lo que sí necesita JS (`maxMb`, `accept`) va por `@js()`.
     2. La zona muestra el foco con `.muni-dz:focus-within`: el input real está con `opacity:0`
        estirado por encima, sigue en el orden de tabulación y sin esa regla Tab no pintaba nada
        (WCAG 2.2 AA 2.4.7). `:focus-within` es universal desde 2017 y no necesita `@supports`.
     3. El resultado se anuncia en una región viva (WCAG 2.2 AA 4.1.3), que habla solo en `change`
        y en `drop`. En `dragover` JAMÁS: se dispara decenas de veces por segundo.

     Ojo con Livewire: `wire:model` sube por el evento `change` del input, así que toda
     reescritura del FileList lo vuelve a emitir. Y con Livewire el `x-data` se reinicia en cada
     respuesta del servidor: la lista pintada se repuebla leyendo el propio input, no memoria
     aparte. La validación de tipo y tamaño de acá es comodidad de cliente: la del servidor sigue
     siendo obligatoria. --}}
<div
    x-data="{
        over: false,
        files: [],
        anuncio: '',
        error: '',
        silencio: false,
        maxMb: @js((float) $maxMb),
        acepta: @js((string) $accept),
        multiple: @js((bool) $multiple),

        init() { this.repintar(); },

        /* Lo que el input tiene AHORA. Es la única fuente de verdad: los File no se
           guardan en el estado de Alpine porque el Proxy reactivo los envuelve y
           `DataTransfer.items.add()` rechaza cualquier cosa que no sea un File real. */
        crudos() { return this.$refs.input.files ? Array.from(this.$refs.input.files) : []; },

        repintar() {
            this.files = this.crudos().map(f => ({ name: f.name, size: (f.size / 1048576).toFixed(2) }));
        },

        admitido(f) {
            const reglas = (this.acepta || '').split(',').map(r => r.trim().toLowerCase()).filter(Boolean);
            if (! reglas.length) return true;
            const tipo = (f.type || '').toLowerCase();
            const nombre = (f.name || '').toLowerCase();
            return reglas.some(r => r.startsWith('.')
                ? nombre.endsWith(r)
                : (r.endsWith('/*') ? tipo.startsWith(r.slice(0, -1)) : tipo === r));
        },

        /* Reescribe el FileList del input con `DataTransfer` y reemite `change` para que
           `wire:model` vuelva a leerlo. `silencio` corta la recursión: el manejador propio
           se salta el evento que él mismo acaba de emitir. */
        sincronizar(lista) {
            const dt = new DataTransfer();
            lista.forEach(f => dt.items.add(f));
            this.$refs.input.files = dt.files;
            this.silencio = true;
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.silencio = false;
        },

        pick(lista, desdeElInput) {
            const entrantes = Array.from(lista);
            const validos = [];
            const rechazos = [];

            entrantes.forEach(f => {
                if (! this.admitido(f)) { rechazos.push(f.name + ': tipo de archivo no admitido'); return; }
                if (this.maxMb > 0 && f.size > this.maxMb * 1048576) {
                    rechazos.push(f.name + ': pesa ' + (f.size / 1048576).toFixed(2) + ' MB y el máximo es ' + this.maxMb + ' MB');
                    return;
                }
                validos.push(f);
            });

            const finales = this.multiple ? validos : validos.slice(0, 1);

            if (! desdeElInput || finales.length !== entrantes.length) {
                this.sincronizar(finales);
            }

            this.repintar();
            this.error = rechazos.join(' · ');
            this.anunciar();
        },

        quitar(i) {
            const restantes = this.crudos().filter((f, k) => k !== i);
            this.sincronizar(restantes);
            this.repintar();
            this.error = '';
            this.anunciar();

            /* El foco no se puede quedar en un botón que ya no existe: pasa al Quitar
               vecino y, si no queda ninguno, vuelve a la zona. */
            this.$nextTick(() => {
                const botones = this.$refs.lista.querySelectorAll('.muni-dz__quitar');
                const destino = botones[Math.min(i, botones.length - 1)] || this.$refs.input;
                if (destino) destino.focus();
            });
        },

        anunciar() {
            const nombres = this.files.map(f => f.name).join(', ');
            this.anuncio = this.files.length === 0
                ? 'No hay archivos adjuntos.'
                : (this.files.length === 1
                    ? 'Un archivo adjunto: ' + nombres + '.'
                    : this.files.length + ' archivos adjuntos: ' + nombres + '.');
        },

        drop(e) {
            this.over = false;
            const dt = e.dataTransfer;
            if (dt && dt.files.length) this.pick(dt.files, false);
        }
    }"
    @dragover.prevent="over = true"
    @dragleave.prevent="if (! $el.contains($event.relatedTarget)) over = false"
    @drop.prevent="drop($event)"
    {{ $attributes }}
>
    <label class="muni-dz" for="{{ $dzId }}" :class="over && 'muni-dz--over'">
        <input id="{{ $dzId }}" x-ref="input" type="file" name="{{ $name }}{{ $multiple ? '[]' : '' }}"
               accept="{{ $accept }}" @if ($multiple) multiple @endif
               aria-label="{{ $label }}" aria-describedby="{{ $dzId }}-hint"
               @change="if (! silencio) pick($event.target.files, true)"
               style="position:absolute;inset:0;opacity:0;cursor:pointer;">
        <span class="muni-dz__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="24" height="24"><path d="M12 16V4m0 0L8 8m4-4l4 4" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round"/></svg>
        </span>
        {{-- Texto del servidor, nunca pintado por Alpine: la zona dice qué hay que subir
             aunque el JS todavía no haya arrancado o falle. --}}
        <span class="muni-dz__label">{{ $label }}</span>
        <span class="muni-dz__hint" id="{{ $dzId }}-hint">{{ $hint }}</span>
    </label>

    {{-- La lista vive FUERA del <label>: dentro, su texto se le pegaba al nombre accesible del
         input (el control terminaba llamándose «informe.pdf 1,2 MB») y un <button> dentro de una
         etiqueta abre el diálogo de archivos en vez de hacer lo suyo. --}}
    <ul class="muni-dz__lista" x-ref="lista" x-show="files.length" style="display:none;">
        <template x-for="(f, i) in files" :key="f.name + '|' + i">
            <li class="muni-dz__file">
                <span x-text="f.name"></span>
                <span class="muni-dz__size muni-num" x-text="f.size + ' MB'"></span>
                <button type="button" class="muni-dz__quitar" @click="quitar(i)" :aria-label="'Quitar ' + f.name">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="12" height="12" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/></svg>
                </button>
            </li>
        </template>
    </ul>

    {{-- Una sola región viva para el resultado y para el error. El error se ve; el resumen es
         solo para el lector de pantalla, porque en pantalla ya están los chips. --}}
    <div role="status" aria-live="polite">
        <p class="muni-dz__error" x-show="error" x-text="error" style="display:none;"></p>
        <p class="muni-dz__sr" x-text="anuncio"></p>
    </div>
</div>

@once
    <style>
        .muni-dz { position:relative; display:flex; flex-direction:column; align-items:center; gap:6px; padding:26px 20px; text-align:center;
            border:1.5px dashed var(--muni-border-2); border-radius:var(--muni-radius); background:var(--muni-surface-2); cursor:pointer;
            font-family:var(--muni-font-sans); transition:border-color var(--muni-dur) var(--muni-ease),background var(--muni-dur) var(--muni-ease); }
        .muni-dz:hover, .muni-dz--over { border-color:var(--muni-accent); background:var(--muni-accent-soft); }
        /* El input real está con opacity:0 encima de toda la zona: el foco del teclado lo
           recibe él, así que el indicador tiene que dibujarlo la zona. `outline` y no solo la
           box-shadow del anillo, que dentro de Filament se computa transparente; y con
           `outline-offset` POSITIVO, porque la zona es grande y hacia adentro se pierde
           dentro del borde punteado. */
        .muni-dz:focus-within { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); border-color:var(--muni-accent); }
        .muni-dz__icon { display:inline-flex; color:var(--muni-accent); }
        .muni-dz__label { font-size:13px; font-weight:600; color:var(--muni-text); }
        .muni-dz__hint { font-size:11.5px; color:var(--muni-hint); }
        .muni-dz__lista { list-style:none; margin:8px 0 0; padding:0; display:flex; flex-wrap:wrap; justify-content:center; gap:8px; }
        .muni-dz__file { display:inline-flex; align-items:center; gap:10px; padding:4px 6px 4px 12px; border-radius:999px; background:var(--muni-surface); border:1px solid var(--muni-border); font-size:12.5px; font-weight:500; color:var(--muni-text); }
        .muni-dz__size { font-size:11px; color:var(--muni-muted); }
        .muni-num { font-family:var(--muni-font-mono); font-variant-numeric:tabular-nums; }
        /* 24x24 es el mínimo táctil de WCAG 2.2 AA 2.5.8. */
        .muni-dz__quitar { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; padding:0;
            border:0; border-radius:999px; background:transparent; color:var(--muni-muted); cursor:pointer;
            transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-dz__quitar:hover { background:var(--muni-surface-2); color:var(--muni-text); }
        .muni-dz__quitar:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        /* El error se lee en texto, no en color: el mensaje nombra el archivo y el motivo.
           Y lleva fondo PROPIO, no el de la página: medido en el navegador,
           `--muni-danger-fg` sobre la superficie del anfitrión da 4,17:1 en oscuro —bajo el
           4,5:1 de WCAG 2.2 AA 1.4.3—, mientras que sobre `--muni-danger-bg` da 4,75:1 en
           oscuro y 5,48:1 en claro (6,10:1 y 5,23:1 en el tema de Filament). */
        .muni-dz__error { margin:8px 0 0; padding:6px 10px; border-radius:var(--muni-radius-sm);
            background:var(--muni-danger-bg); border:1px solid var(--muni-danger-border);
            font-family:var(--muni-font-sans); font-size:12px; font-weight:600; color:var(--muni-danger-fg); text-align:center; }
        .muni-dz__sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; border:0; }
        @media (prefers-reduced-motion:reduce) { .muni-dz, .muni-dz__quitar { transition:none; } }
    </style>
@endonce
