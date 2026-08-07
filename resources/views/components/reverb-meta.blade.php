{{--
    Datos de conexión de Reverb para el navegador, en RUNTIME.

    La clave NO puede viajar en el bundle de Vite: `npm run build` corre al
    construir la imagen, donde `VITE_REVERB_APP_KEY` no existe, así que el JS
    queda con `undefined` y Reverb rechaza cada conexión. El síntoma es cruel:
    todo se ve sano —contenedores healthy, el WebSocket acepta el handshake si se
    prueba a mano— y simplemente ningún navegador llega a conectarse.

    Host, puerto y esquema se omiten a propósito cuando no están configurados:
    `echo.js` cae entonces a `window.location`, que es lo correcto detrás del
    ingress (mismo origen, /app* → Reverb) y evita fijar un dominio en la imagen.

    No emite nada si no hay clave: una meta vacía haría creer al JS que sí la hay.
--}}
@php($clave = config('broadcasting.connections.reverb.key'))
@if ($clave)
    <meta name="reverb-key" content="{{ $clave }}">
    @if (config('broadcasting.connections.reverb.options.host_publico'))
        <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host_publico') }}">
    @endif
    @if (config('broadcasting.connections.reverb.options.puerto_publico'))
        <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.puerto_publico') }}">
    @endif
    @if (config('broadcasting.connections.reverb.options.esquema_publico'))
        <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.esquema_publico') }}">
    @endif
@endif
