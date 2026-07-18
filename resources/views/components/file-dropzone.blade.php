@props([
    'name' => 'file',
    'accept' => 'image/*,application/pdf',
    'label' => 'Arrastra un archivo o haz clic para subir',
    'hint' => 'PDF o imagen, hasta 10 MB',
    'multiple' => false,
])

{{-- Zona de carga de archivos (Alpine 3). Drag & drop + preview del nombre. El input real
     conserva el archivo para el submit del form. --}}
<div
    x-data="{
        over:false, files:[],
        pick(list){ this.files = Array.from(list).map(f => ({ name:f.name, size:(f.size/1024/1024).toFixed(2) })); },
        drop(e){ this.over=false; const dt=e.dataTransfer; if(dt && dt.files.length){ this.$refs.input.files = dt.files; this.pick(dt.files); } }
    }"
    @dragover.prevent="over=true" @dragleave.prevent="over=false" @drop.prevent="drop($event)"
    {{ $attributes }}
>
    <label class="muni-dz" :class="over && 'muni-dz--over'">
        <input x-ref="input" type="file" name="{{ $name }}{{ $multiple ? '[]' : '' }}" accept="{{ $accept }}" @if ($multiple) multiple @endif
               @change="pick($event.target.files)" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
        <span class="muni-dz__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="24" height="24"><path d="M12 16V4m0 0L8 8m4-4l4 4" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round"/></svg>
        </span>
        <span class="muni-dz__label" x-text="files.length ? '' : '{{ $label }}'"></span>
        <span class="muni-dz__hint" x-show="!files.length">{{ $hint }}</span>
        <template x-for="f in files" :key="f.name">
            <span class="muni-dz__file"><span x-text="f.name"></span><span class="muni-dz__size mono" x-text="f.size + ' MB'"></span></span>
        </template>
    </label>
</div>

@once
    <style>
        .muni-dz { position:relative; display:flex; flex-direction:column; align-items:center; gap:6px; padding:26px 20px; text-align:center;
            border:1.5px dashed var(--muni-border-2); border-radius:var(--muni-radius); background:var(--muni-surface-2); cursor:pointer;
            font-family:var(--muni-font-sans); transition:border-color var(--muni-dur) var(--muni-ease),background var(--muni-dur) var(--muni-ease); }
        .muni-dz:hover, .muni-dz--over { border-color:var(--muni-accent); background:var(--muni-accent-soft); }
        .muni-dz__icon { display:inline-flex; color:var(--muni-accent); }
        .muni-dz__label { font-size:13px; font-weight:600; color:var(--muni-text); }
        .muni-dz__hint { font-size:11.5px; color:var(--muni-hint); }
        .muni-dz__file { display:inline-flex; align-items:center; gap:10px; padding:6px 12px; margin-top:4px; border-radius:999px; background:var(--muni-surface); border:1px solid var(--muni-border); font-size:12.5px; font-weight:500; color:var(--muni-text); }
        .muni-dz__size { font-size:11px; color:var(--muni-muted); }
        .mono { font-family:var(--muni-font-mono); }
    </style>
@endonce
