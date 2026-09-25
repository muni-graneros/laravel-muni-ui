@props([
    'columns' => [], // lista de ['key' => , 'label' => , 'align' => ?, 'mono' => ?, 'sortable' => ?]
    'rows' => [], // filas asociativas por key; '_tone' => 'danger' pinta la franja
    'empty' => 'Sin resultados.', // texto sin resultados
    'searchable' => false, // agrega un buscador sobre la tabla
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
            if(this.sortKey){ const k=this.sortKey,d=this.sortDir; r=[...r].sort((a,b)=>this.cmp(a[k],b[k])*d); }
            return r;
        },
        clave(v){
            if(v===null || v===undefined) return '';
            if(typeof v==='number') return v;
            const t=String(v).trim();
            const f=t.match(/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/);
            if(f) return f[3]+'-'+f[2].padStart(2,'0')+'-'+f[1].padStart(2,'0');
            const s=t.replace(/[\s\u00a0$%]/g,'');
            if(/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)) return parseFloat(s.replace(/\./g,'').replace(',','.'));
            if(/^-?\d+(,\d+)?$/.test(s)) return parseFloat(s.replace(',','.'));
            if(/^-?\d*\.\d+$/.test(s)) return parseFloat(s);
            return t;
        },
        cmp(a,b){
            const x=this.clave(a), y=this.clave(b);
            if(typeof x==='number' && typeof y==='number') return x-y;
            return String(x).localeCompare(String(y),'es',{numeric:true,sensitivity:'base'});
        }
    }"
    {{ $attributes }}
>
    @if ($searchable)
        <div style="margin-bottom:12px;position:relative;max-width:280px;">
            <input type="search" x-model="q" placeholder="Buscar…" aria-label="Buscar en la tabla" class="muni-st__search">
        </div>
    @endif

    <div style="overflow-x:auto;border:1px solid var(--muni-border);border-radius:var(--muni-radius);background:var(--muni-surface);">
        <table class="muni-st">
            <thead>
                <tr>
                    <template x-for="c in cols" :key="c.key">
                        <th scope="col" :style="`text-align:${c.align||'left'}`" :class="(c.sortable!==false) && 'muni-st__sortable'"
                            :aria-sort="c.sortable===false ? null : (sortKey===c.key ? (sortDir>0 ? 'ascending' : 'descending') : 'none')">
                            <template x-if="c.sortable!==false">
                                <button type="button" class="muni-st__sortbtn" @click="sort(c.key)">
                                    <span x-text="c.label"></span>
                                    <span class="muni-st__arrow" aria-hidden="true" :style="sortKey===c.key ? 'opacity:1' : 'opacity:.3'" x-text="sortKey===c.key ? (sortDir>0?'↑':'↓') : '↕'"></span>
                                </button>
                            </template>
                            <template x-if="c.sortable===false">
                                <span x-text="c.label"></span>
                            </template>
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
        .muni-st__sortbtn { display:inline-flex; align-items:center; gap:5px; padding:0; margin:0; border:0; background:none; font:inherit; letter-spacing:inherit; text-transform:inherit; color:inherit; cursor:pointer; border-radius:4px; }
        .muni-st__sortbtn:focus-visible { outline:none; box-shadow:var(--muni-ring); }
        .muni-st__arrow { font-family:var(--muni-font-mono); font-size:11px; }
        .muni-st td { padding:9px 12px; border-bottom:1px solid var(--muni-border); white-space:nowrap; color:var(--muni-text); }
        .muni-st tbody tr { transition:background var(--muni-dur) var(--muni-ease); }
        .muni-st tbody tr:hover { background:var(--muni-surface-2); }
        .muni-st__danger td:first-child { box-shadow:inset 3px 0 0 var(--muni-danger-fg); color:var(--muni-danger-fg); font-weight:600; }
        .muni-st__search { width:100%; padding:9px 12px; font-family:var(--muni-font-sans); font-size:13px; color:var(--muni-text); background:var(--muni-surface); border:1px solid var(--muni-border); border-radius:var(--muni-radius-sm); }
        .muni-st__search:focus { outline:none; border-color:var(--muni-accent); box-shadow:var(--muni-ring); }
    </style>
@endonce
