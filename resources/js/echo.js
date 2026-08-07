import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.getAttribute('content') || '';
const csrf = meta('csrf-token');
const isHttps = window.location.protocol === 'https:';

// Config en RUNTIME (meta tags desde .env) con fallback a window.location. Así el
// tiempo real funciona en dev (puerto directo de Reverb) y en prod tras el ingress/
// túnel (mismo origen, /app* → Reverb) SIN rehornear la imagen ni puertos fijos.
const host = meta('reverb-host') || window.location.hostname;
const scheme = meta('reverb-scheme') || window.location.protocol.replace(':', '');
const tls = scheme === 'https';
const puerto = meta('reverb-port')
    ? Number(meta('reverb-port'))
    : (window.location.port ? Number(window.location.port) : (isHttps ? 443 : 80));

// Sin clave NO se instancia. pusher-js lanza en el constructor si la clave falta,
// y como este módulo se importa desde `app.js` antes que todo lo demás, esa
// excepción se lleva puesto el bundle entero: Alpine no se registra, nada de lo
// que debería quedar en `window` llega a existir, y la página carga pero no
// responde a nada. Un entorno sin Reverb configurado debe perder el tiempo real,
// no la interfaz completa.
// La clave se lee del meta tag, NO del bundle: `import.meta.env` se hornea al
// construir la imagen, donde VITE_REVERB_APP_KEY no existe, y el bundle sale con
// `undefined`. Reverb rechaza entonces cada conexión y nada parece roto —los
// contenedores healthy, el handshake manual responde 101—, así que la avería no
// se ve. El fallback a la variable queda para `npm run dev` local.
const claveReverb = document.querySelector('meta[name="reverb-key"]')?.getAttribute('content')
    || import.meta.env.VITE_REVERB_APP_KEY;

if (!claveReverb) {
    console.warn('[echo] sin clave de Reverb: el tiempo real queda desactivado.');
}

window.Echo = !claveReverb ? undefined : new Echo({
    broadcaster: 'reverb',
    key: claveReverb,
    wsHost: host,
    wsPort: puerto,
    wssPort: puerto,
    forceTLS: tls,
    enabledTransports: ['ws', 'wss'],
    auth: { headers: { 'X-CSRF-TOKEN': csrf } },
});
