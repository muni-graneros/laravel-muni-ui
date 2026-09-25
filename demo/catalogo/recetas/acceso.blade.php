<div style="max-width:380px;display:flex;flex-direction:column;gap:16px;">
    <div>
        <h2 style="margin:0;font-size:20px;">Verifica que eres tú</h2>
        <p style="margin:6px 0 0;color:var(--muni-muted);font-size:13.5px;">Enviamos un código de 6 dígitos a m•••@graneros.cl.</p>
    </div>
    <x-muni::alert tone="warn">El código anterior no coincide. Te quedan 2 intentos.</x-muni::alert>
    <x-muni::otp-input :length="6" name="codigo" />
    <x-muni::switch label="Recordar este equipo" name="recordar" description="No pediremos el código en este equipo por 30 días." />
    <x-muni::button type="submit" size="lg">Verificar</x-muni::button>
</div>
