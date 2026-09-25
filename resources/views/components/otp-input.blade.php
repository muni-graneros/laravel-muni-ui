@props([
    'length' => 6, // cantidad de dígitos
    'name' => 'code', // name del input oculto con el código completo
])

{{-- Entrada de código de un solo uso / MFA (Alpine 3). Autofoco entre casillas, pega
     un código completo, retrocede con Backspace. El valor va en un input oculto, y
     wire:model / x-model en el componente enlazan el código completo (x-modelable). --}}
<div
    x-data="{
        digits: Array({{ (int) $length }}).fill(''),
        value: '',
        get code(){ return this.digits.join(''); },
        init(){ this.$watch('value', v => { v = String(v ?? '').replace(/\D/g, ''); if (v !== this.code) this.digits = this.digits.map((_, i) => v[i] || ''); });
                this.$watch('digits', () => { if (this.value !== this.code) this.value = this.code; }); },
        onInput(i, e){
            const v = e.target.value.replace(/\D/g,'');
            if(v.length > 1){ this.paste(v); return; }
            this.digits[i] = v;
            if(v && i < {{ (int) $length - 1 }}) this.$refs['d'+(i+1)].focus();
        },
        onKey(i, e){
            if(e.key==='Backspace' && !this.digits[i] && i>0){ this.$refs['d'+(i-1)].focus(); }
        },
        paste(v){ for(let i=0;i<this.digits.length;i++){ this.digits[i]=''; } v.split('').slice(0,{{ (int) $length }}).forEach((c,i)=>{ this.digits[i]=c; }); this.$nextTick(()=>{ const last=Math.min(v.length,{{ (int) $length }})-1; this.$refs['d'+Math.max(last,0)].focus(); }); }
    }"
    x-modelable="value"
    {{ $attributes->merge(['style' => 'display:flex;gap:9px;']) }}
>
    <input type="hidden" name="{{ $name }}" :value="code">
    @for ($i = 0; $i < (int) $length; $i++)
        <input
            x-ref="d{{ $i }}"
            x-model="digits[{{ $i }}]"
            @input="onInput({{ $i }}, $event)"
            @keydown="onKey({{ $i }}, $event)"
            @paste.prevent="paste(($event.clipboardData||window.clipboardData).getData('text').replace(/\D/g,''))"
            inputmode="numeric" maxlength="1" autocomplete="one-time-code"
            class="muni-otp"
            aria-label="Dígito {{ $i + 1 }}"
        >
    @endfor
</div>

@once
    <style>
        .muni-otp { width:46px; height:54px; text-align:center; font-family:var(--muni-font-mono); font-size:22px; font-weight:700; color:var(--muni-text);
            background:var(--muni-surface); border:1px solid var(--muni-border-strong, var(--muni-border)); border-radius:var(--muni-radius-sm);
            transition:border-color var(--muni-dur) var(--muni-ease),box-shadow var(--muni-dur) var(--muni-ease); }
        /* El outline es el indicador REAL: la box-shadow del anillo se pierde dentro de Filament (ver --muni-focus). */
        .muni-otp:focus { outline:3px solid var(--muni-focus, var(--muni-accent, #767676)); outline-offset:2px; border-color:var(--muni-accent); box-shadow:var(--muni-ring); }
        @media (max-width:420px){ .muni-otp { width:40px; height:48px; font-size:19px; } }
    </style>
@endonce
