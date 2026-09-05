@props([
    'columns' => [],
    'rows' => [],
    'empty' => 'Sin resultados.',
    'searchable' => false,
])

@php
    // $columns: array de ['key'=>, 'label'=>, 'align'=>?, 'mono'=>?, 'sortable'=>? (default true)]
    // $rows: array de arrays asociativos por key. Cada fila puede traer '_tone'=>'danger' para la franja.
    $colsJson = json_encode(array_values($columns), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    $rowsJson = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

<div
    x-data="{
        cols: {{ $colsJson }},
        rows: {{ $rowsJson }},
        sortKey:null, sortDir:1, q:'',
        sort(k){ if(this.sortKey===k){ this.sortDir*=-1; } else { this.sortKey=k; this.sortDir=1; } },
        get view(){
            let r = this.rows;
            if(this.q.trim()){ const t=this.q.toLowerCase(); r = r.filter(row => Object.values(row).some(v => String(v).toLowerCase().includes(t))); }
            if(this.sortKey){ const k=this.sortKey,d=this.sortDir; r=[...r].sort((a,b)=>{ let x=a[k],y=b[k]; const nx=parseFloat(String(x).replace(/[^0-9.-]/g,'')), ny=parseFloat(String(y).replace(/[^0-9.-]/g,'')); if(!isNaN(nx)&&!isNaN(ny)){ return (nx-ny)*d; } return String(x).localeCompare(String(y),'es')*d; }); }
            return r;
        }
    }"
    {{ $attributes }}
>
    @if ($searchable)
        <div style="margin-bottom:12px;position:relative;max-width:280px;">
            <input x-model="q" placeholder="Buscar…" class="muni-st__search">
        </div>
    @endif

    <div style="overflow-x:auto;border:1px solid var(--muni-border);border-radius:var(--muni-radius);background:var(--muni-surface);">
        <table class="muni-st">
            <thead>
                <tr>
                    <template x-for="c in cols" :key="c.key">
                        <th :style="`text-align:${c.align||'left'}`" :class="(c.sortable!==false) && 'muni-st__sortable'" @click="c.sortable!==false && sort(c.key)">
                            <span style="display:inline-flex;align-items:center;gap:5px;">
                                <span x-text="c.label"></span>
                                <template x-if="c.sortable!==false">
                                    <span class="muni-st__arrow" :style="sortKey===c.key ? 'opacity:1' : 'opacity:.3'" x-text="sortKey===c.key ? (sortDir>0?'↑':'↓') : '↕'"></span>
                                </template>
                            </span>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row,ri) in view" :key="ri">
                    <tr :class="row._tone==='danger' && 'muni-st__danger'">
                        <template x-for="c in cols" :key="c.key">
                            <td :style="`text-align:${c.align||'left'};${c.mono?'font-family:var(--muni-font-mono);font-variant-numeric:tabular-nums;':''}`" x-text="row[c.key]"></td>
                        </template>
                    </tr>
                </template>
                <template x-if="view.length===0">
                    <tr><td :colspan="cols.length" style="text-align:center;padding:28px;color:var(--muni-muted);">{{ $empty }}</td></tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

@once
    <style>
        .muni-st { width:100%; border-collapse:collapse; font-family:var(--muni-font-sans); font-size:12.5px; }
        .muni-st th { text-align:left; white-space:nowrap; padding:9px 12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--muni-muted); background:var(--muni-surface-2); border-bottom:1px solid var(--muni-border); }
        .muni-st__sortable { cursor:pointer; user-select:none; transition:color var(--muni-dur) var(--muni-ease); }
        .muni-st__sortable:hover { color:var(--muni-text); }
        .muni-st__arrow { font-family:var(--muni-font-mono); font-size:11px; }
        .muni-st td { padding:9px 12px; border-bottom:1px solid var(--muni-border); white-space:nowrap; color:var(--muni-text); }
        .muni-st tbody tr { transition:background var(--muni-dur) var(--muni-ease); }
        .muni-st tbody tr:hover { background:var(--muni-surface-2); }
        .muni-st__danger td:first-child { box-shadow:inset 3px 0 0 var(--muni-danger-fg); color:var(--muni-danger-fg); font-weight:600; }
        .muni-st__search { width:100%; padding:9px 12px; font-family:var(--muni-font-sans); font-size:13px; color:var(--muni-text); background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-sm); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-st__search:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; border-color:var(--muni-accent); box-shadow:var(--muni-ring); }
    </style>
@endonce
