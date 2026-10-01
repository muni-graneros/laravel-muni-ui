@props([
    'name' => 'fecha', // name del input oculto con la fecha ISO
    'min' => null, // fecha mínima elegible, 'Y-m-d' o DateTimeInterface
    'value' => null, // fecha elegida, 'Y-m-d' o DateTimeInterface
])

@php
    /*
     * Las fechas que llegan del anfitrión se validan acá, en PHP, y FALLAN CERRADO:
     * pasa una cadena `Y-m-d` que además sea un día que exista (2026-02-30 casa con
     * el patrón y PHP lo correría a marzo), o un DateTimeInterface, que se formatea
     * acá mismo. Cualquier otra cosa se descarta entera: el calendario queda sin
     * mínimo o sin selección, nunca con un valor a medias ni «mejor escapado».
     *
     * Por qué importa: `$min` se interpolaba dentro de la cadena de `x-data` como
     * `new Date('…')`. El escape de Blade no protege ahí —el navegador decodifica las
     * entidades antes de que Alpine evalúe—, así que un valor con `') || fetch(...)`
     * se ejecutaba. Las fechas viajan ahora por atributos de datos escapados y Alpine
     * las lee con `$el.dataset`, que es texto, no código.
     */
    $fechaIso = function ($valor): ?string {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (! is_string($valor) || ! \Illuminate\Support\Carbon::hasFormat($valor, 'Y-m-d')) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $valor)->format('Y-m-d') === $valor ? $valor : null;
        } catch (\Throwable) {
            return null;
        }
    };

    $minIso = $fechaIso($min);
    $valueIso = $fechaIso($value);
@endphp

{{-- Calendario de mes (Alpine 3 core). Navega meses, elige un día y escribe el valor
     ISO en un input oculto. Sin dependencias de fechas externas.

     · `min` y `value` entran por `data-min` y `data-value`, ya validados arriba. NUNCA
       dentro de la expresión de `x-data`: ese era el sumidero.
     · El mínimo se normaliza UNA vez en `init()` a medianoche local. Antes `disabled(d)`
       llamaba `setHours(0,0,0,0)` sobre el mínimo en cada evaluación —muta el Date y
       devuelve un timestamp—, y el día del mínimo cambiaba de estado entre una pasada y
       la siguiente. Y se construye con `new Date(y, m-1, d)` y no desde la cadena: la
       cadena `Y-m-d` se interpreta como UTC y en Chile cae al día anterior a las 21:00.
     · Los días anteriores al mínimo llevan `aria-disabled` —no `disabled`: siguen siendo
       alcanzables y anunciables— y se ven tachados, no solo en otro color (1.4.1).
     · La selección vive en `valor` (cadena ISO o vacía), entrelazada con `x-modelable`:
       `wire:model` y `x-model` sobre `<x-muni::calendar>` caen en la raíz y Alpine los
       ata a `valor` EN LAS DOS DIRECCIONES. Elegir un día escribe en el modelo del
       anfitrión, y cuando el servidor cambia la propiedad (resetear tras guardar, cargar
       otra cita) la selección y el mes a la vista se mueven solos. Todo lo derivado (el
       Date elegido, el mes) sale de un `$watch` sobre `valor`, así da igual quién lo
       cambió. Con modelo externo manda el modelo al iniciar; `value` siembra cuando no
       hay modelo, y también el campo oculto para el formulario sin JS.
     · Los huecos previos al día 1 son elementos reales (`<span aria-hidden>`) en un
       `x-for` propio. Antes eran iteraciones cuyo único hijo era `<template x-if="c">`
       con `c = null`: Alpine deja un `<template>` con display:none que no es un ítem
       de la rejilla, y el día 1 de cualquier mes caía bajo «L».
     · La clave del `x-for` de los días sale de la fecha (`iso(c)`), no del índice: con
       `:key="i"` Alpine reutilizaba el nodo de la posición i al cambiar de mes y el botón
       enfocado pasaba a ser otro día.
     · La rejilla APG (role=grid, tabindex itinerante, flechas, RePág/AvPág) queda para
       cuando exista una pantalla que la consuma; hoy cada día es un botón. --}}
<div
    x-data="{
        valor: '',
        sel: null,
        view: new Date(),
        min: null,
        dias: ['L','M','X','J','V','S','D'],
        meses: ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
        init() {
            this.min = this.desdeIso(this.$el.dataset.min);
            this.valor = this.$el.dataset.value || '';
            this.sel = this.desdeIso(this.valor);
            const hoy = new Date();
            const base = this.sel || (this.min && this.min > hoy ? this.min : hoy);
            this.view = new Date(base.getFullYear(), base.getMonth(), 1);
            this.$watch('valor', (v) => {
                const d = this.desdeIso(v);
                if (this.same(d, this.sel)) return;
                this.sel = d;
                if (d && (d.getFullYear() !== this.view.getFullYear() || d.getMonth() !== this.view.getMonth())) {
                    this.view = new Date(d.getFullYear(), d.getMonth(), 1);
                }
            });
        },
        desdeIso(s) {
            if (! /^\d{4}-\d{2}-\d{2}$/.test(s || '')) return null;
            const [y, m, d] = s.split('-').map(Number);
            return new Date(y, m - 1, d);
        },
        get titulo(){ return this.meses[this.view.getMonth()] + ' ' + this.view.getFullYear(); },
        get primero(){ return (new Date(this.view.getFullYear(), this.view.getMonth(), 1).getDay() + 6) % 7; },
        get huecos(){ return Array.from({ length: this.primero }, (_, i) => i); },
        get fechas(){
            const y=this.view.getFullYear(), m=this.view.getMonth();
            const days=new Date(y,m+1,0).getDate();
            const out=[];
            for(let d=1;d<=days;d++) out.push(new Date(y,m,d));
            return out;
        },
        move(n){ this.view = new Date(this.view.getFullYear(), this.view.getMonth()+n, 1); },
        pick(d){ if(this.disabled(d)) return; this.valor = this.iso(d); this.emitir(); },
        emitir() {
            const campo = this.$refs.campo;
            campo.value = this.valor;
            campo.dispatchEvent(new Event('input', { bubbles: true }));
            campo.dispatchEvent(new Event('change', { bubbles: true }));
        },
        disabled(d){ return this.min !== null && d < this.min; },
        same(a,b){ return a === b || (!! a && !! b && a.toDateString() === b.toDateString()); },
        iso(d){ return d ? d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0') : ''; },
        largo(d){ return d.toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); }
    }"
    x-modelable="valor"
    @if ($minIso) data-min="{{ $minIso }}" @endif
    @if ($valueIso) data-value="{{ $valueIso }}" @endif
    {{ $attributes->merge(['class' => 'muni-cal']) }}
>
    <input type="hidden" name="{{ $name }}" value="{{ $valueIso }}" x-ref="campo" :value="valor">
    <div class="muni-cal__head">
        <button type="button" @click="move(-1)" class="muni-cal__nav" aria-label="Mes anterior">‹</button>
        <span class="muni-cal__title" aria-live="polite" aria-atomic="true" x-text="titulo"></span>
        <button type="button" @click="move(1)" class="muni-cal__nav" aria-label="Mes siguiente">›</button>
    </div>
    <div class="muni-cal__grid">
        <template x-for="d in dias" :key="d"><span class="muni-cal__dow" x-text="d"></span></template>
        <template x-for="h in huecos" :key="h"><span class="muni-cal__void" aria-hidden="true"></span></template>
        <template x-for="c in fechas" :key="iso(c)">
            <button type="button" class="muni-cal__day"
                    :class="{ 'muni-cal__day--on': same(c,sel), 'muni-cal__day--off': disabled(c) }"
                    :aria-label="largo(c)"
                    :aria-pressed="same(c,sel) ? 'true' : 'false'"
                    :aria-disabled="disabled(c) ? 'true' : null"
                    @click="pick(c)" x-text="c.getDate()"></button>
        </template>
    </div>
</div>

@once
    <style>
        .muni-cal { display:inline-block; padding:14px; background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius); box-shadow:var(--muni-shadow); font-family:var(--muni-font-sans); width:280px; }
        .muni-cal__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .muni-cal__title { font-size:13.5px; font-weight:700; text-transform:capitalize; }
        .muni-cal__nav { width:30px; height:30px; border:1px solid var(--muni-border); background:var(--muni-surface); color:var(--muni-text); border-radius:var(--muni-radius-sm); cursor:pointer; font-size:16px; transition:border-color var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-cal__nav:hover { border-color:var(--muni-accent); color:var(--muni-accent); }
        .muni-cal__nav:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; box-shadow:var(--muni-ring); }
        .muni-cal__grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
        .muni-cal__dow { text-align:center; font-family:var(--muni-font-mono); font-size:10.5px; font-weight:600; color:var(--muni-hint); padding-bottom:6px; }
        .muni-cal__day { aspect-ratio:1; border:none; background:transparent; color:var(--muni-text); border-radius:var(--muni-radius-sm); font-family:var(--muni-font-mono); font-size:12.5px; cursor:pointer; transition:background var(--muni-dur) var(--muni-ease),color var(--muni-dur) var(--muni-ease); }
        .muni-cal__day:hover { background:var(--muni-surface-2); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-cal__day:focus-visible { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:-2px; box-shadow:var(--muni-ring); }
        /* Anterior al mínimo: tachado y atenuado, no solo otro color. Sigue enfocable
           para que el lector de pantalla lo anuncie como no disponible. */
        .muni-cal__day--off { color:var(--muni-hint); text-decoration:line-through; cursor:not-allowed; }
        .muni-cal__day--off:hover { background:transparent; }
        /* Elegido. Va DESPUÉS de --off (gana el color) y su :hover es la ÚLTIMA regla
           :hover de la rejilla: todas empatan en (0,2,0) y antes `.muni-cal__day:hover`
           pisaba el fondo y dejaba --muni-on-accent sobre --muni-surface-2 (1,08:1). */
        .muni-cal__day--on { background:var(--muni-accent); color:var(--muni-on-accent); font-weight:700; }
        .muni-cal__day--on:hover { background:var(--muni-accent-strong); }
        /* Guardia local, como en stat: muni-ui.css baja --muni-dur a 0 ms con movimiento
           reducido, pero la hoja del panel —la única que se carga dentro de Filament— no,
           y medido el día seguía en 0,16 s. */
        @media (prefers-reduced-motion: reduce) { .muni-cal__nav, .muni-cal__day { transition: none; } }
    </style>
@endonce
