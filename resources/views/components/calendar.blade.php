@props([
    'name' => 'fecha',
    'min' => null,
])

{{-- Calendario de mes (Alpine 3). Navega meses, selecciona un día, escribe el valor
     ISO en un input oculto. Sin dependencias de fechas externas. --}}
<div
    x-data="{
        sel: null,
        view: new Date(),
        min: {{ $min ? "new Date('".$min."')" : 'null' }},
        dias: ['L','M','X','J','V','S','D'],
        meses: ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
        get titulo(){ return this.meses[this.view.getMonth()] + ' ' + this.view.getFullYear(); },
        get celdas(){
            const y=this.view.getFullYear(), m=this.view.getMonth();
            const first=(new Date(y,m,1).getDay()+6)%7; // lunes=0
            const days=new Date(y,m+1,0).getDate();
            const out=[];
            for(let i=0;i<first;i++) out.push(null);
            for(let d=1;d<=days;d++) out.push(new Date(y,m,d));
            return out;
        },
        move(n){ this.view = new Date(this.view.getFullYear(), this.view.getMonth()+n, 1); },
        pick(d){ if(this.disabled(d)) return; this.sel = d; },
        disabled(d){ return this.min && d < this.min.setHours(0,0,0,0) && d < this.min; },
        same(a,b){ return a&&b && a.toDateString()===b.toDateString(); },
        iso(d){ return d ? d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0') : ''; }
    }"
    {{ $attributes->merge(['class' => 'muni-cal']) }}
>
    <input type="hidden" name="{{ $name }}" :value="iso(sel)">
    <div class="muni-cal__head">
        <button type="button" @click="move(-1)" class="muni-cal__nav" aria-label="Mes anterior">‹</button>
        <span class="muni-cal__title" x-text="titulo"></span>
        <button type="button" @click="move(1)" class="muni-cal__nav" aria-label="Mes siguiente">›</button>
    </div>
    <div class="muni-cal__grid">
        <template x-for="d in dias" :key="d"><span class="muni-cal__dow" x-text="d"></span></template>
        <template x-for="(c,i) in celdas" :key="i">
            <template x-if="c"><button type="button" class="muni-cal__day" :class="same(c,sel) && 'muni-cal__day--on'" @click="pick(c)" x-text="c.getDate()"></button></template>
        </template>
    </div>
</div>

@once
    <style>
        .muni-cal { display:inline-block; padding:14px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius); box-shadow:var(--muni-shadow); font-family:var(--muni-font-sans); width:280px; }
        .muni-cal__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .muni-cal__title { font-size:13.5px; font-weight:700; text-transform:capitalize; }
        .muni-cal__nav { width:30px; height:30px; border:1px solid var(--muni-border); background:var(--muni-surface); color:var(--muni-text); border-radius:var(--muni-radius-sm); cursor:pointer; font-size:16px; transition:.15s; }
        .muni-cal__nav:hover { border-color:var(--muni-accent); color:var(--muni-accent); }
        .muni-cal__grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
        .muni-cal__dow { text-align:center; font-family:var(--muni-font-mono); font-size:10.5px; font-weight:600; color:var(--muni-hint); padding-bottom:6px; }
        .muni-cal__day { aspect-ratio:1; border:none; background:transparent; color:var(--muni-text); border-radius:var(--muni-radius-sm); font-family:var(--muni-font-mono); font-size:12.5px; cursor:pointer; transition:background var(--muni-dur) var(--muni-ease); }
        .muni-cal__day:hover { background:var(--muni-surface-2); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-cal__day:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; box-shadow:var(--muni-ring); }
        .muni-cal__day--on { background:var(--muni-accent); color:var(--muni-on-accent); font-weight:700; }
    </style>
@endonce
